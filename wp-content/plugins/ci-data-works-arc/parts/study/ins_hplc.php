<?php
class Ins_HPLC
{
	public $api_id = -42;

	public function cron()
	{
		
	}

	public function import($filepath, $sub = '')
	{
		global $reports_cache;
		global $wpdb;
		global $only_insert;

		$btype 			= 'study';
		$table_report 	= tr_pdd_get_table_report_by_btype($btype);
		$table_results	= tr_pdd_get_table_result_by_btype($btype);
		$user_id 		= get_current_user_id();
		$created_date       = date_i18n('Y-m-d');
		$created_datetime   = date_i18n('Y-m-d H:i:s');

		$filename		= basename($filepath);

		$filedata 			= trpdd_get_data($filepath, null, array('name','area%'), true);
		var_dump($filedata);


		$all_tests = array();
		$sql = "select * from ci_study_tests where abrev like '%HPLC%' ";
		if (!empty($sub)) {
			$sql .= " and abrev like '%{$sub}%' ";
		}
		
		$trs = $wpdb->get_results($sql, ARRAY_A);
		foreach ($trs as $r) {
			$reported_values = json_decode($r['reported_values'], true);
			foreach ($reported_values as $arr) {
				$arr['drug'] =  strtolower($arr['v']);
				$arr['testid'] = $r['ID'];
				$key = !empty($arr['i']) ? $arr['i'] : $arr['v'];
				$key = strtolower(trim($key));
				$key = str_replace(array(' ', '(', ')', '.'), '', $key);
				$all_tests[$key] = $arr;
			}
		}

		

		$updated_list = array();
		$inserted_list = array();

		//get barcode
		list($barcode) = explode(' ', $filename);
		if (!empty($barcode)) {

			$report_id = $reports_cache[$barcode];

			if (!isset($reports_cache[$barcode]) || $report_id == 0) {
				$report = $wpdb->get_row("select id,upload_files from {$table_report} where patient_id = '{$barcode}' ", ARRAY_A);
				if ($report && $report['id'] > 0) {
					$report_id = $report['id'];
				}
			}

			if ($report_id == 0) {
				$reportdata = array(
					'patient_id' => $barcode,
					'author_id' => $user_id,
					'created_date' => $created_date,
					'created' => $created_datetime,
					'upload_files' => $filename,
				);
				$wpdb->insert($table_report, $reportdata);

				$report_id = $wpdb->insert_id;

				$inserted_list[] = $barcode;
			} else if (!$only_insert && !isset($reports[$barcode])) {

				$old_upload_files = $wpdb->get_var("select upload_files from {$table_report} where id = {$report_id}");
				$old_upload_files = explode(',', $old_upload_files);
				$old_upload_files[] = $filename;
				$old_upload_files = array_unique($old_upload_files);
				$old_upload_files = implode(',', $old_upload_files);
				$reportdata = array(
					'patient_id' => $barcode,
					'author_id' => $user_id,
					'upload_files' => $old_upload_files,
				);

				$wpdb->update($table_report, $reportdata, array('id' => $report_id));

				$updated_list[] = $barcode;
			}

			if ($report_id > 0) {

				foreach ($filedata['data'] as $row) {

					
					if(!isset($row['name']) || !isset($row['area%'])){
						continue;
					}

					$key = strtolower(trim($row['name']));
					$key = str_replace(array(' ', '(', ')', '.'), '', $key);

					$conc = number_format($row['area%'],3);

					if (isset($all_tests[$key])) {

						$drugarr = $all_tests[$key];

						$drug = $drugarr['drug'];
						$testid = $drugarr['testid'];

						$data_result = array(
							'report_id' => $report_id,
							'drug' => $drug,
							'group_id' => $testid,
							'conc' => substr($conc, 0, 15),

						);

						$exists = $wpdb->get_row("select * from {$table_results} 
							where report_id = {$report_id} 
							and drug='{$drug}'
							and group_id = {$testid} ");

							if($drug == 'area'){
								var_dump($exists);
							}

						if (!$exists) {
							$exists = $wpdb->get_row("select * from {$table_results} 
							where report_id = {$report_id} 
							and drug='{$drug}'");

							if($exists && $exists->id > 0 && $exists->group_id != $testid)
							{
								$exists = null;
							}
						}

					

						if ($exists && $exists->id > 0) {
							$rsq = $wpdb->update($table_results, $data_result, array('id' => $exists->id));
						} else {
							$rsq = $wpdb->insert($table_results, $data_result);
						}
					}
				}
			}
		}

		return array(
			'inserted' => $inserted_list,
			'updated' => $updated_list,
		);
	}
}
