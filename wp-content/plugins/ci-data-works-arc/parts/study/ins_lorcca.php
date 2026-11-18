<?php
class Ins_LORCCA
{
	public $api_id = -41;

	public function cron()
	{
	}

	public function import($filepath, $sub = '')
	{
		global $reports_cache;
		global $wpdb;
		global $only_insert;
		global $condition_selected;

		$btype 			= 'study';
		$table_batch    = tr_pdd_get_table_by_btype($btype);
		$table_report 	= tr_pdd_get_table_report_by_btype($btype);
		$table_results	= tr_pdd_get_table_result_by_btype($btype);
		$user_id 		= get_current_user_id();
		$created_date       = date_i18n('Y-m-d');
		$created_datetime   = date_i18n('Y-m-d H:i:s');

		$filename		= basename($filepath);

		$filedata 			= trpdd_get_data($filepath, null, null, false, true);


		$all_tests = array();
		$sql = "select * from ci_study_tests where abrev like '%LORRCA%' ";
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

			$report = $reports_cache[$barcode];

			if (!isset($reports_cache[$barcode]) || empty($report)) {
				$report = $wpdb->get_row(
					"select re.id,
					re.upload_files ,
					ba.project_ID,
					ba.conditions
					from {$table_batch} ba
					left join {$table_report} re ON re.patient_id = ba.assen
					where ba.assen = '{$barcode}' ",
					ARRAY_A
				);
				
				$report['conditions'] = explode(';', $report['conditions']);

				foreach($report['conditions'] as $cond)
				{
					if(!isset($report['first_conditon'])){
						$report['first_conditon'] = $cond;
					}
					if(stripos($filename,$cond)>0)
					{
						$report['condition'] = $cond;
						break;
					}
				}

				$reports_cache[$barcode] = $report;
				
			}

			if ($report['id']==0) {
				$reportdata = array(
					'patient_id' => $barcode,
					'author_id' => $user_id,
					'created_date' => $created_date,
					'created' => $created_datetime,
					'upload_files' => $filename,
				);
				$wpdb->insert($table_report, $reportdata);

				$report['id']= $wpdb->insert_id;
				$reports_cache[$barcode] = $report;
				$inserted_list[] = $barcode;
			} else if (!$only_insert && !isset($reports[$barcode])) {

				$old_upload_files = $wpdb->get_var("select upload_files from {$table_report} where id = {$report['id']}");
				$old_upload_files = explode(',', $old_upload_files);
				$old_upload_files[] = $filename;
				$old_upload_files = array_unique($old_upload_files);
				$old_upload_files = implode(',', $old_upload_files);
				$old_upload_files = trim($old_upload_files,',');
				$reportdata = array(
					'patient_id' => $barcode,
					'author_id' => $user_id,
					'upload_files' => $old_upload_files,
				);

				$wpdb->update($table_report, $reportdata, array('id' => $report['id']));

				$updated_list[] = $barcode;
			}

			if ($report['id'] > 0) {

				

				foreach ($filedata['data'] as $row) {

					$key = strtolower(trim($row[0]));
					$key = str_replace(array(' ', '(', ')', '.'), '', $key);

					$conc = number_format($row[1], 3);

					if (isset($all_tests[$key])) {

						$drugarr = $all_tests[$key];

						$drug = $drugarr['drug'];
						$testid = $drugarr['testid'];

						

						$exists = $wpdb->get_row("select * from {$table_results} 
							where report_id = {$report['id']} 
							and drug='{$drug}'
							and group_id = {$testid} ");

					

						if (!$exists) {
							$exists = $wpdb->get_row("select * from {$table_results} 
							where report_id = {$report['id']} 
							and drug='{$drug}'");

							if ($exists && $exists->id > 0 && $exists->group_id != $testid) {
								$exists = null;
							}
						}

						$conc_arr = array();
						if($exists && !empty($exists->conc)){
							$conc_arr = json_decode($exists->conc,true);
							if(!is_array($conc_arr))$conc_arr = array();
						}

						if(empty($report['condition']))
						{
							$report['condition'] = $condition_selected;
						}
						$condition  = strtolower(substr(str_replace(' ', '', $report['condition']), 0, 35));

						$conc_arr[$condition] = $conc;

						$data_result = array(
							'report_id' => $report['id'],
							'drug' => $drug,
							'group_id' => $testid,
							'conc' => json_encode($conc_arr),
						);


						
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
