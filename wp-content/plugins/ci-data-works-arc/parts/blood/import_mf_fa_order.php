<?php
global $wpdb;

$user_id 	= -1;
$all_clinics = array();
$rs = $wpdb->get_results("select cID, cli_clinic
                from ci_clinic ");
foreach ($rs as $r) {
	$all_clinics[$r->cID] = $r->cli_clinic;
}


$all_medications = $wpdb->get_col("select me_name
                                        from ci_medications 
                                         where me_name !='other' ");
$all_medications = array_map('strtolower', $all_medications);

$fix_duplicate = true;

if (!empty($_FILES['file']['name'])) {
	$info = pathinfo($_FILES['file']['name']);
	$ext = $info['extension'];
	$filepath = $_FILES['file']['tmp_name'];
	$data = trpdd_get_data($filepath, $ext, array('patient name'), true);

	if (count($data['data']) == 0) {
		echo 'No data';
	}
	foreach ($data['data'] as $row) {
		$oldreq = $row['ff sample id'];

		if (empty($oldreq)) continue;

		$bat1 = $wpdb->get_row("select * from ci_batches_blood where oldreq ='{$oldreq}' ");
		$bat2 = $wpdb->get_row("select * from ci_batches_blood where oldreq ='{$oldreq}S' ");



		list($pa_last, $pa_first) = explode(',', $row['patient name']);
		$pa_first = trim($pa_first);
		$pa_last = trim($pa_last);

		$pa_dob = $row['dob'];
		$pa_gender = $row['sex'];
		$pa_mrn = $row['mrn #'];
		$doc = $row['date and time of draw'];
		$spec_received = $row['date and time of draw'];
		$result_date = $row['assay date'];

		if (in_array($ext, array('xls', 'xlsx'))) {
			if (!empty($doc) && strpos($doc, '/') === false && strpos($doc, '-') === false) {
				$doc = gmdate('Y-m-d H:i:s', PHPExcel_Shared_Date::ExcelToPHP($doc));
			}

			if (!empty($spec_received) && strpos($spec_received, '/') === false && strpos($spec_received, '-') === false) {
				$spec_received = gmdate('Y-m-d H:i:s', PHPExcel_Shared_Date::ExcelToPHP($spec_received));
			}

			if (!empty($result_date) && strpos($result_date, '/') === false && strpos($result_date, '-') === false) {
				$result_date = gmdate('Y-m-d H:i:s', PHPExcel_Shared_Date::ExcelToPHP($result_date));
			}

			if (!empty($pa_dob) && strpos($pa_dob, '/') === false && strpos($pa_dob, '-') === false) {
				$pa_dob = gmdate('Y-m-d', PHPExcel_Shared_Date::ExcelToPHP($pa_dob));
			}
		}

		if (isset($_REQUEST['onlyresultdate'])) {
			$update = array(
				'created' => $result_date,
				'created_date' => date('Y-m-d', strtotime($result_date)),
				'approve_date' => $result_date
			);
			if ($bat1) {
				$wpdb->update('ci_reports_blood', $update, array('patient_id' => $bat1->assen));
			}

			if ($bat2) {
				$wpdb->update('ci_reports_blood', $update, array('patient_id' => $bat2->assen));
			}

			continue;
		}

		$patient = array(
			'pa_first' => $pa_first,
			'pa_last' => $pa_last,
			'pa_dob' => $pa_dob,
			'pa_gender' => $pa_gender,
			'pa_mrn' => $pa_mrn,
			'pa_patient_id' => $row['ff patient id #']
		);
		$patient_id =  $bat1 && $bat1->patient_id > 0 ? $bat1->patient_id : 0;
		$patient_id =  $bat2 && $bat2->patient_id > 0 ? $bat2->patient_id : $patient_id;
		if ($patient_id == 0) {
			$patient_id = $wpdb->get_var("select pa_ID from ci_patients where
								pa_first = '{$pa_first}'
								and pa_last = '{$pa_last}'
								and pa_dob = '{$pa_dob}'
								 and pa_patient_id = '{$patient['pa_patient_id']}'");
			if ($patient_id == 0) {
				$wpdb->insert('ci_patients', $patient);
				$patient_id = $wpdb->insert_id;
			}
		} else {
			$wpdb->update('ci_patients', $patient, array('pa_ID' => $patient_id));

			//fix duplicate patient
			if ($fix_duplicate) {
				$wpdb->query("
					update ci_batches_blood set patient_id = {$patient_id}
						where patient_id in (select pa_ID from ci_patients where
								pa_first = '{$pa_first}'
								and pa_last = '{$pa_last}'
								and pa_dob = '{$pa_dob}'
								 and pa_patient_id = '{$patient['pa_patient_id']}'
								 and pa_ID != {$patient_id} ) ");
				$wpdb->query("delete from ci_patients where
								pa_first = '{$pa_first}'
								and pa_last = '{$pa_last}'
								and pa_dob = '{$pa_dob}'
								 and pa_patient_id = '{$patient['pa_patient_id']}'
								 and pa_ID != {$patient_id} ");
			}
		}
		//var_dump($patient);

		$clinic_name = $row['hospital system'];
		$cli_ID = 0;
		foreach ($all_clinics as $cid => $clinicn) {
			if (stripos($clinicn, $clinic_name) > 0) {
				$clinic_name = $clinicn;
				$cli_ID = $cid;
				break;
			}
		}

		$doc_name = $row['ordering physician'];
		list($doc_last, $doc_first) = explode(',', $doc_name);
		$doc_last = trim($doc_last);
		$doc_first = trim($doc_first);

		$doc_ID = $wpdb->get_var("select doc_ID from ci_doc where doc_first='{$doc_first}' 
					and doc_last='{$doc_last}'");



		$medications = array();
		$medication_other = array();
		$medication_other2 = array();
		if (!empty($row['pain medication 1'])) {
			if (in_array(strtolower($row['pain medication 1']), $all_medications)) {
				$medications[] = $row['pain medication 1'];
			} else {
				$medication_other[] = $row['pain medication 1'];
			}
		}
		if (!empty($row['pain medication 2'])) {
			if (in_array(strtolower($row['pain medication 2']), $all_medications)) {
				$medications[] = $row['pain medication 2'];
			} else {
				$medication_other[] = $row['pain medication 2'];
			}
		}
		$medication_other = implode(',', $medication_other);
		if (!empty($medication_other)) {
			$medications[] = 'other:' . $medication_other;
		}

		if (!empty($row['scd medication 1'])) {
			if (in_array(strtolower($row['scd medication 1']), $all_medications)) {
				$medications[] = $row['scd medication 1'];
			} else {
				$medication_other2[] = $row['scd medication 1'];
			}
		}
		if (!empty($row['scd medication 2'])) {
			if (in_array(strtolower($row['scd medication 2']), $all_medications)) {
				$medications[] = $row['scd medication 2'];
			} else {
				$medication_other2[] = $row['scd medication 2'];
			}
		}
		$medication_other2 = implode(',', $medication_other2);
		if (!empty($medication_other2)) {
			$medications[] = 'other2:' . $medication_other2;
		}


		$medications = implode('|', $medications);

		$insurance_name = 'No Insurance - Cash';
		$insurance_id = $bat1 && $bat1->insurance1 > 0 ? $bat1->insurance1 : 0;
		$insurance_id = $bat2 && $bat2->insurance1 > 0 ? $bat2->insurance1 : $insurance_id;
		if ($insurance_id == 0) {
			$wpdb->insert(
				'ci_insurance',
				array(
					'ins_first' => $pa_first,
					'ins_last' => $pa_last,
					'ins_relation' => 'Self'
				)
			);
			$insurance_id = $wpdb->insert_id;
		}
		$batch_id = str_replace('-', '', $doc);
		$batdata = array(
			'doc' => $doc,
			'batch_id' => $batch_id,
			'spec_received' => $spec_received . '',
			'batch_type' => 'M',
			'lab_id' => CI_Base_Lab::get_current_lab_id(),

			'patient_id' => $patient_id,
			'patient_name' => $pa_last . ', ' . $pa_first,
			'dob' => $pa_dob,
			'insurance1' => $insurance_id,
			'insurance' => $insurance_name,
			'author_ID' => $user_id,
			'oldreq' => $oldreq,
			'medication' => $medications,

			'cli_ID' => $cli_ID . '',
			'requesting_practice' => $clinic_name . '',
			'doc_ID' => $doc_ID . '',
			'requesting_physician' => $doc_name,

			'p_pain_score' => $row['pain score'] . '',
			'p_fatigue_score' => $row['fatigue score'] . '',
			'p_condition' => trim($row['reason for testing (1=baseline, 2= therapy start, 3= monitoring therapy, 4=acute crises)']) . '',

			'notes' => $row['lab comments'],
			'batch_created' => $doc,
		);

		$status = $row['sample rejected'];
		if (stripos($status, 'yes') !== false) {
			$batdata['batch_status'] = 'rejected';
		}

		$mffa = stripos($row['mf + fa ordered'], 'yes') !== false;
		if ($mffa) {
			$batdata1 = $batdata;

			$batdata1['panel_tests_requested'] = 'GROUP_24,GROUP_26';


			if (!empty($row['mf+fa clinical comments'])) {
				$batdata1['notes_report'] = $row['mf+fa clinical comments'];
			}


			if ($bat1) {
				$assen = $bat1->assen;
				$wpdb->update('ci_batches_blood', $batdata1, array('mid' => $bat1->mid));
			} else {
				$assen = $batdata1['assen'] = tr_get_new_assen('blood');
				$wpdb->insert('ci_batches_blood', $batdata1);
			}

			$reportdata = array(
				'patient_id' => $assen,
				'created' => $result_date,
				'created_date' => date('Y-m-d', strtotime($result_date)),
				'lab_id' => CI_Base_Lab::get_current_lab_id(),
				'estatus' => 'C',
				'approve_date' => $result_date
			);

			$report_id1 = $wpdb->get_var("select id from ci_reports_blood where patient_id = '{$assen}'");
			if ($report_id1 == 0) {
				$wpdb->insert('ci_reports_blood', $reportdata);
				$report_id1 = $wpdb->insert_id;
			} else {
				$wpdb->update('ci_reports_blood', $reportdata, array('id' => $report_id1));
			}

			if ($report_id1 > 0) {
				//insert results
				$results = array(
					'VCAM1' => $row['vcam ai'],
					'PSEL' => number_format($row['psel ai'], 2, '.', ''),
					'MFI-3' => $row['auc 3'],
					'MFI-10' => $row['auc 10'],
					'PREEXISTING HEMOLYSIS' => $row['pre-existing hemolysis %']
				);
				if ($results['PSEL'] == '0.00') {
					$results['PSEL'] = '';
				}
				if (stripos($results['PREEXISTING HEMOLYSIS'], '%') === false) {
					$results['PREEXISTING HEMOLYSIS'] = ($results['PREEXISTING HEMOLYSIS'] * 100) . '%';
				}

				foreach ($results as $k => $vl) {
					$ex = $wpdb->get_var("select id from ci_results_blood where report_id = {$report_id1} 
										and drug = '{$k}'");
					if ($ex == 0) {
						$wpdb->insert('ci_results_blood', array(
							'report_id' => $report_id1,
							'drug' => $k,
							'conc' => $vl
						));
					} else {
						//$wpdb->update('ci_results_blood',array('conc'=>$vl),array('id'=>$ex));
					}
				}
			}
		}

		$Serology_Ordered = stripos($row['serology ordered'], 'yes') !== false;
		if ($Serology_Ordered) {
			$batdata2 = $batdata;

			$batdata2['oldreq'] .= 'S';
			$batdata2['panel_tests_requested'] = 'GROUP_23';
			$batdata2['notes_report'] = $row['serology clinical comment'];
			
			// notes: $row['diagnostic information for covid'],


			if ($bat2) {
				$assen2 = $bat2->assen;
				$wpdb->update('ci_batches_blood', $batdata2, array('mid' => $bat2->mid));
			} else {
				$assen2 = $batdata2['assen'] = tr_get_new_assen('blood');
				$wpdb->insert('ci_batches_blood', $batdata2);
			}

			$reportdata = array(
				'patient_id' => $assen2,
				'created' => $doc,
				'created_date' => date('Y-m-d', strtotime($doc)),
				'lab_id' => CI_Base_Lab::get_current_lab_id(),
				'estatus' => 'C',
				'approve_date' => $doc
			);
			$report_id2 = $wpdb->get_var("select id from ci_reports_blood where patient_id = '{$assen2}'");
			if ($report_id2 == 0) {
				$wpdb->insert('ci_reports_blood', $reportdata);
				$report_id2 = $wpdb->insert_id;
			} else {
				$wpdb->update('ci_reports_blood', $reportdata, array('id' => $report_id2));
			}

			if ($report_id2 > 0) {
				//insert results
				$results = array(
					'SARS-COV-2 IGG ANTIBODY' => $row['igg'],
					'SARS-COV-2 IGM ANTIBODY' => $row['igm'],
				);
				foreach ($results as $k => $vl) {
					$ex = $wpdb->get_var("select id from ci_results_blood where report_id = {$report_id2} 
										and drug = '{$k}'");
					if ($ex == 0) {
						$wpdb->insert('ci_results_blood', array(
							'report_id' => $report_id2,
							'drug' => $k,
							'conc' => $vl
						));
					}
				}
			}
		}

		//var_dump($batdata);
		//echo '<br><br>';
		//		var_dump($row);
		//		exit;
	}
}

?>
<form method="post" enctype="multipart/form-data">
	<input type="file" name="file" />
	<br>
	<label>
		<input type="checkbox" name="onlyresultdate" value="1" /> Only update result date
	</label>
	<input type="submit" class="button" />
</form>