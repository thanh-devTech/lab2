<?php
class Ins_Hemox
{
	public $api_id = -40;

	public function cron()
	{
	}

	public function get_report($barcode)
	{
		global $wpdb;
		$btype 			= 'study';
		$table_batch    = tr_pdd_get_table_by_btype($btype);
		$table_report 	= tr_pdd_get_table_report_by_btype($btype);

		$report = $wpdb->get_row("select 
							ba.mid, ba.assen, ba.project_ID, ba.conditions, ba.timeframe,
							re.id, re.upload_files 
							from {$table_batch} ba 
							LEFT JOIN {$table_report} re ON re.patient_id = ba.assen
							where ba.assen = '{$barcode}' ", ARRAY_A);

		return $report;
	}

	public function import($filepath)
	{
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

		$data 			= trpdd_get_data($filepath, null, array(), true, false, true);

		$found_tests = array();
		$reports = array();

		$all_tests = array();
		$trs = $wpdb->get_results("select * from ci_study_tests where abrev like '%HEM%'", ARRAY_A);
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

		foreach ($data['header'] as $k) {
			if (isset($all_tests[$k])) {
				$found_tests[$k] = $all_tests[$k];
			}
		}

		$updated_list = array();
		$inserted_list = array();
		foreach ($data['data'] as $row) {

			$barcode 		= trim($row['accessionid']);
			$conditionid 	= trim($row['conditionid']);
			$conditionid  	= strtolower(substr(str_replace(' ', '', $conditionid), 0, 35));

			
			if (!isset($reports[$barcode])) {
				$reports[$barcode]  = $this->get_report($barcode);
			} 

			if (empty($reports[$barcode]) || $reports[$barcode]['id'] == 0) {
				$reportdata = array(
					'patient_id' => $barcode,
					'author_id' => $user_id,
					'created_date' => $created_date,
					'created' => $created_datetime,
					'upload_files' => $filename,
				);
				$wpdb->insert($table_report, $reportdata);

				$reports[$barcode] = $this->get_report($barcode);
				$inserted_list[] = $barcode;
			} else if (!$only_insert) {

				$old_upload_files = $reports[$barcode]['upload_files'];
				$old_upload_files = explode(',', $old_upload_files);
				$old_upload_files[] = $filename;
				$old_upload_files = array_unique($old_upload_files);
				$old_upload_files = implode(',', $old_upload_files);
				$reportdata = array(
					'patient_id' => $barcode,
					'author_id' => $user_id,
					'upload_files' => trim($old_upload_files,','),
				);

				$wpdb->update($table_report, $reportdata, array('id' => $reports[$barcode]['id']));

				$updated_list[] = $barcode;
			}

			if ($reports[$barcode] && $reports[$barcode]['id'] > 0 ) {

				if (empty($conditionid) && !isset($reports[$barcode]['conditions_arr'])) {
					//get condition
					$condition_selecteds = explode(';', $reports[$barcode]['conditions']);
					$study 			 = $wpdb->get_row("select * from ci_study  where ID = {$reports[$barcode]['project_ID']} ");
					
					$study->time_points = json_decode($study->time_points, true);
					if (!is_array($study->time_points)) {
						$study->time_points = array();
					}
					$reports[$barcode]['conditions_arr'] = array();
					$reports[$barcode]['current_condition'] = 0;

					foreach ($study->time_points as $timepoint) {
						$timepoint['name'] = trim($timepoint['name']);
						if (strtolower($timepoint['name']) == strtolower($reports[$barcode]['timeframe'])) {
							foreach ($timepoint['conditions'] as $condition) {
								if (in_array($condition['name'], $condition_selecteds)) {

									$conditionkey  = strtolower(substr(str_replace(' ','',$condition['name']),0,35));

									$carr = array(
										'key' => $conditionkey,
										'tests' => array()
									);
									foreach ($condition['tests'] as $t) {
										$carr['tests'][] = $t;
									}
									$reports[$barcode]['conditions_arr'][] = $carr;
								}
							}
							break;
						}
					}
				}

				$conditionarr = array('key'=>'','tests'=>array());
				if(!empty($reports[$barcode]['conditions_arr']))
				{
					$num = $reports[$barcode]['current_condition']++;
					$conditionarr = $reports[$barcode]['conditions_arr'][$num];
				}else if(!empty($condition_selected) && empty($conditionid))
				{
					$conditionid = strtolower(substr(str_replace(' ','',$condition_selected),0,35));
				}

				foreach ($found_tests as $k => $drugarr) {

					
					$drug 	= $drugarr['drug'];
					$testid = $drugarr['testid'];
					$conc 	= $row[$k];

					$cur_conditionid = $conditionid;
					if(empty($cur_conditionid) && in_array($testid, $conditionarr['tests']))
					{
						$cur_conditionid = $conditionarr['key'];
					}

					$data_result = array(
						'report_id' => $reports[$barcode]['id'],
						'drug' => $drug,
						'group_id' => $testid,
						'conc' => substr($conc, 0, 15),
					);

					$exists = $wpdb->get_row("select * from {$table_results} 
					where report_id = {$reports[$barcode]['id']} 
					and drug='{$drug}'
					and group_id = {$testid} ");

					if (!$exists) {
						$exists = $wpdb->get_row("select * from {$table_results} 
						where report_id = {$reports[$barcode]['id']} 
						and drug='{$drug}'");

						if ($exists && $exists->id > 0 && $exists->group_id != $testid) {
							$exists = null;
						}
					}
					$oldconc = array();
					if ($exists) {
						$oldconc = $exists ? json_decode($exists->conc, true) : array();
						if (!is_array($oldconc)) {
							$oldconc = array();
						}
					}

					if (!empty($cur_conditionid)) {
						$oldconc[$cur_conditionid] = $conc;
						$data_result['conc'] = json_encode($oldconc);
					} else if ($exists && count($oldconc) > 0) {
						Tr_Session_Class::add_error('Empty Condition ID');
						continue;
					}


					if ($exists && $exists->id > 0) {
						$rsq = $wpdb->update($table_results, $data_result, array('id' => $exists->id));
					} else {
						$rsq = $wpdb->insert($table_results, $data_result);
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
