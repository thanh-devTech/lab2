<?php
function trpdd_parse_shl7($msg)
{

	$multis = array('R', 'C', 'O');
	$out = array();

	$segs = explode("\r", $msg);
	foreach ($segs as $fseg) {
		$fseg = trim($fseg);
		if (empty($fseg)) {
			continue;
		}
		$segments = explode('|', $fseg);
		$segname = $segments[0];
		$i = 1;
		$ic = isset($out[$segname]) ? count($out[$segname]) : 0;

		foreach ($segments as $k => $seg) {

			$j = 1;
			$sf = explode('^', $seg);
			foreach ($sf as $f) {
				if (in_array($segname, $multis)) {
					$out[$segname][$ic][$segname . '.' . $i][$segname . '.' . $i . '.' . $j] = $f;
				} else {
					$out[$segname][$segname . '.' . $i][$segname . '.' . $i . '.' . $j] = $f;
				}

				$j++;
			}
			$i++;
		}
	}

	return $out;
}


$only_insert 	= isset($_REQUEST['only_insert']) ? true : false;
$files 			= $_SESSION['files_pcr_import'];
$btype 			= 'pcr';
$table_batch 	= tr_pdd_get_table_by_btype($btype);
$table_report 	= tr_pdd_get_table_report_by_btype($btype);

$antibiotics 	= array();
$an_rs 			= $wpdb->get_results("select * from ci_test_antibiotics");
foreach ($an_rs as $r) {
	$antibiotics[strtoupper($r->ta_name)] = $r->ID;
	if (!empty($r->ta_abrev)) {
		$antibiotics[strtoupper($r->ta_abrev)] = $r->ID;
	}
}

foreach ($files as $filename => $meta) {
	$filepath = $log_dir . $filename;

	$contents = file_get_contents($filepath);

	$packages = explode('H|\^&|', $contents);
	foreach ($packages as $package) {

		$package = trim($package);
		if (empty($package)) continue;
		$package = 'H|\^&|' . $package;

		$arr = trpdd_parse_shl7($package);
		//var_dump($arr);
		$barcode = $arr['O'][0]['O.3']['O.3.1'];

		$batch = $wpdb->get_row("select mid from {$table_batch} where assen='{$barcode}'");

		if ($batch) {
			$report_id  = 0;
			$batch_id = $batch->batch_id;
			$report = $wpdb->get_row("select id,upload_files from {$table_report} where patient_id = '{$barcode}' ", ARRAY_A);
			if ($report && $report['id'] > 0) {
				$report_id = $report['id'];
			}
			if (empty($report_id)) {
				$instrument = 'MIC';
				$acquisition_date = preg_replace("/([0-9]{4})([0-9]{2})([0-9]{2})/", '$1-$2-$3', $batch_id);
				$data = array(
					'patient_id' => $barcode,
					'batch_id' => $batch_id . '',
					'lab_id' => CI_Base_Lab::get_current_lab_id(),
					'acquisition_date' => $acquisition_date . '',
					'author_id' => $user_id,
					'created_date' => $created_date,
					'created' => $created_datetime,
					'instrument' => $instrument,
					'upload_files' => $filename,
				);

				$rsins = $wpdb->insert($table_report, $data);
				if ($rsins === false) {
				}

				$report_id = $wpdb->insert_id;
			} else if (!$only_insert) {

				$old_upload_files = $wpdb->get_var("select upload_files from {$table_report} where id = '{$report_id}' ");
				$old_upload_files = explode(',', $old_upload_files);
				$old_upload_files[] = $filename;
				$old_upload_files = array_unique($old_upload_files);
				$old_upload_files = implode(',', $old_upload_files);
				$data = array(
					'patient_id' => $barcode,
					'batch_id' => $batch_id,
					'lab_id' => CI_Base_Lab::get_current_lab_id(),
					'author_id' => $user_id,
					'instrument' => $instrument,
					'upload_files' => $old_upload_files,
				);

				$wpdb->update($table_report, $data, array('id' => $report_id));
			}

			if ($report_id > 0) {

				$currentid = 0;
				$results = array();
				$test_id = -2;
				foreach ($arr['R'] as $arrR) {

					$rtid = $arrR['R.3']['R.3.4'];
					if (empty($rtid)) {
						$rtid = 'no' . $currentid;
					}

					if ($arrR['R.3']['R.3.5'] == 'NAME') {
						$currentid++;

						if (!isset($results[$rtid])) {
							$results[$rtid] = array(
								'te_ID' => $test_id,
								'btype' => 'pcr',
								'report_id' => $report_id,
							);
						}
						$drug 	= trim($arrR['R.4']['R.4.1']);
						$ta_id 	= $antibiotics[strtoupper($drug)];
						$results[$rtid]['ta_ID'] = $ta_id;
					} else if ($arrR['R.3']['R.3.5'] == 'INTERP') {
						$results[$rtid]['result'] = trim($arrR['R.4']['R.4.1']);
					} else if ($arrR['R.3']['R.3.5'] == 'MIC2') {
						$results[$rtid]['MIC2'] = trim($arrR['R.4']['R.4.1']);
					}
				}

				//get comments
				if (isset($arr['C'])) {
					foreach ($arr['C'] as $arrC) {
						$comment = trim($arrC['C.4']['C.4.2']);
						if (!empty($comment)) {
							$existid = $wpdb->get_var("select id from ci_results_pcr 
										where report_id = {$report_id} 
										and drug = 'mic_internal_note'  ");
							if ($existid > 0) {
								$wpdb->update('ci_results_pcr', array(
									'comments' => $comment
								), array('id' => $existid));
							} else {
								$wpdb->insert('ci_results_pcr', array(
									'report_id' => $report_id,
									'drug' => 'mic_internal_note',
									'comments' => $comment
								));
							}
						}
					}
				}

				foreach ($results as $arr) {
					if ($arr['ta_ID'] > 0) {
						if (isset($arr['MIC2']) && $arr['MIC2'] == 4) {
							$arr['result'] = 'P';
						}

						unset($arr['drug']);
						unset($arr['MIC2']);
						$rsid = $wpdb->get_var("select ID 
						from ci_results_antibiotics 
						where ta_ID={$arr['ta_ID']}
						and report_id = {$report_id} 
						and te_ID = {$test_id} 
						and btype = 'pcr' ");
						if ($rsid > 0) {
							$wpdb->update('ci_results_antibiotics', array(
								'result' => $arr['result']
							), array('ID' => $rsid));
						} else {
							$wpdb->insert('ci_results_antibiotics', $arr);
						}
					}
				}

				Tr_Session_Class::add('Imported ' . $barcode);
			}
		} else {
			$error_no = 1;
			Tr_Session_Class::add_error($barcode . ' Not found !');
		}
	}
}

$nextcron = wp_next_scheduled('ci_cron_clean_results');
if ($nextcron == 0 || $nextcron > time() + 360) {
	wp_schedule_single_event(time() + 180, 'ci_cron_clean_results', array('btype' => 'pcr'));
}


if (isset($_REQUEST['trajax'])) {

	Tr_Session_Class::show();
	if ($error_no == 0) {
		echo '<div class="success_import">Imported Successfully! <br><a href="' . Tr_Base_Class_V6::link(array('act' => '', 'trajax' => ''), false) . '">Click here to back</a></div>';
	} else {
		echo '<div class="success_import"><a href="' . Tr_Base_Class_V6::link(array('act' => '', 'trajax' => ''), false) . '">Click here to back</a></div>';
	}



	exit;
}
