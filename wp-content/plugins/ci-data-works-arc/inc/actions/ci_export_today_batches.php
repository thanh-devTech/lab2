<?php
global $wpdb;

$btypes         = tr_pdd_get_batch_types();
$inb_show_pcode = tr_get_option('ci_pdf_data', 'inb_show_pcode');
$order_by       = 'ba.position asc, ba.mid';
$order          = @$_REQUEST['order'] == 'desc' ? 'desc' : 'asc';
$type           = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';
$lab_id         = isset($_REQUEST['lab_id']) ? $_REQUEST['lab_id'] : $lab_id;
$date           = isset($_REQUEST['date']) ? $_REQUEST['date'] : $date;

list($lab_id, $labby) = explode('_', $lab_id);

if (@$_REQUEST['orderby'] == 'spec_received') {
	$order_by = 'ba.spec_received ' . $order . ' , lab asc, lab_ref asc';
} else if (@$_REQUEST['orderby'] == 'doc') {
	$order_by = 'ba.doc ' . $order . ' , lab asc, lab_ref asc';
} else if (@$_REQUEST['orderby'] == 'cli') {
	$order_by = 'ba.requesting_practice ' . $order;
} else if (@$_REQUEST['orderby'] == 'spec_received') {
	$order_by = 'ba.requesting_physician ' . $order;
}


$all_status = tr_pdd_get_batch_statuses(false, false, false, 'all');

$where = '';

if ($lab_id == 0) {
	$lab_id = CI_Base_Lab::get_current_lab_id();
}

if ($lab_id > 0) {
	if ($labby == 'r') {
		$where .= " and  ba.ref_lab_ID={$lab_id} ";
	} else if ($labby == 'm') {
		$where .= " and ba.lab_id = {$lab_id} ";
	} else {
		$where .= " and (ba.lab_id = {$lab_id} or ba.ref_lab_ID={$lab_id})";
	}
}



if (isset($batch_id) && $batch_id !== '') {
	$where .= " and ba.batch_id = '{$batch_id}' ";
}

if (!$date) {
	$from_date = @$_REQUEST['from'];
	$to_date = @$_REQUEST['to'];
	if (empty($from_date)) {
		$from_date = Tr_Base_Class_V4::date();
	}
	if (empty($to_date)) {
		$to_date = Tr_Base_Class_V4::date();
	}

	$batch_from = str_replace('-', '', $from_date);
	$batch_to = str_replace('-', '', $to_date);
	$where .= " and ba.batch_id>= {$batch_from} ";
	$where .= " and ba.batch_id<= {$batch_to} ";

	//$where.= " and ba.spec_received>='{$from_date}' ";
	//$where.= " and ba.spec_received<='{$to_date} 23:59:59' ";
	$date = $from_date;
} else {
	$where .= " and ba.spec_received>='{$date}' and  ba.spec_received<='{$date} 23:59:59' ";
}


$rs = $wpdb->get_results("select * from ci_test_panels where te_type !='tox' ");
$all_tests = array();
foreach ($rs as $r) {
	if ($inb_show_pcode) {
		if (!empty($r->te_loinc_code)) {
			$all_tests[$r->te_ID] = $r->te_loinc_code;
		}
	} else {
		$all_tests[$r->te_ID] = $r->te_name;
	}
}
$all_groups = array();
$ts = $wpdb->get_results("select * from ci_test_groups");
foreach ($ts as $t) {

	$all_groups[$t->tg_ID] = $t;
}


$sql_child = array();
foreach ($btypes as $btype) {
	$table = tr_pdd_get_table_by_btype($btype);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);

	$sql_child[] = "
        select ba.mid,ba.position,ba.assen,ba.batch_id,ba.dob,ba.patient_name, ba.spec_received,ba.doc,
                ba.current_medication_other,ba.current_medication,ba.insurance,ba.subscriber_insurance, 
                ba.panel_tests_requested,ba.custom_profile,ba.requesting_practice,ba.type_of_specimen,ba.batch_status,ba.notes,
                ba.requesting_physician,
                cli.cli_city,
                doc.doc_phone,
                lab.lab_dba_init as lab,
                lr.lab_dba_init as lab_ref,
                IF(re.id is not null or ba.result_pdf is not null, 1 , 0) as resulted,
                pa.pa_address,pa.pa_city,pa.pa_state,pa.pa_zip,pa.pa_email,pa.pa_mrn,pa_phone,pa.pa_gender,
                '{$btype}' as btype
                from {$table} ba
                left join {$report_tbl} re on re.patient_id = ba.assen
                left JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
                left JOIN ci_labs lr ON lr.lab_ID = ba.ref_lab_ID
                left JOIN ci_clinic cli ON cli.cID = ba.cli_ID or (ba.cli_ID<1 and cli.cli_clinic = ba.requesting_practice)
				left join ci_doc doc ON doc.doc_ID = ba.doc_ID
                left join ci_patients pa On pa.pa_ID = ba.patient_id
                where 1  {$where} 
        ";
}

$sql_child = implode(" UNION ALL ", $sql_child);

$sql = " select * from ({$sql_child}) ba
                order by {$order_by}
             ";

$batches = $wpdb->get_results($sql);

if (isset($_GET['debug'])) {
	var_dump($sql);
	echo '<br><br>';
	var_dump($wpdb->last_error);
	echo '<br><br>';
	var_dump($batches);
	exit;
}

if ($filepath) {
	if (count($batches) == 0) {
		return false;
	}
	$f = fopen($filepath, 'w');
} else {

	$filename = 'Batch_' . $date . '.csv';
	header("Pragma: public");
	header("Expires: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Cache-Control: private", false);
	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename=\"$filename\";");
	header("Content-Transfer-Encoding: binary");
	$f = fopen('php://output', 'w');
}

$i = 1;
$keys = array(
	'Position',
	'Resulted',
	'Accession',
	'Medical Record Number',
	'Old Req Number',
	'Patient Name',
	'Patient Address',
	'Patient Phone',
	'Medication',
	'DOB',
	'Gender',
	'Email',
	'Facility',
	'Clinic City',
	'Provider',
	'Dr Phone',
	'Collection',
	'Received',
	'Lab',
	'Ref',
	'Panels',
	'Tests Requested',
	'Insurance',
	'Notes',
	'Specimen Type',
	'Status'
);
if (tr_get_option('ci_pdf_data', 'inb_tbpphone')) {
}
fputcsv($f, $keys);

foreach ($batches as $row) {

	//format medication
	$row->current_medication = str_replace('|', ',', $row->current_medication);
	$medications = explode(',', trim($row->current_medication));
	if (!empty($row->current_medication_other)) {
		$row->current_medication_other = str_replace('|', ',', $row->current_medication_other);
		$others = explode(',', trim($row->current_medication_other));
		$medications = array_merge($medications, $others);
	}
	foreach ($medications as $j => &$med) {
		$med = trim($med);
		$med = ucfirst(strtolower($med));
		if (empty($med)) {
			unset($medications[$j]);
		}
	}
	$medications = implode(',', $medications);
	$medications = str_replace(',', ', ', $medications);

	//check insurance_name
	if (empty($row->insurance)) {
		$row->insurance = $row->subscriber_insurance;
	}

	//get panel
	$testnames = '';
	$panels = '';
	if ($row->btype == 'tox') {
		if ($row->custom_profile == 'Use Custom Profile' && !empty($row->panel_tests_requested)) {
			$testnames = $row->panel_tests_requested;
		} else if (strpos($row->panel_tests_requested, '+') === false && !empty($row->custom_profile)) {
			$testnames = trim(ucfirst($row->custom_profile) . ' +' . $row->panel_tests_requested, '+');
		} else {
			$testnames = $row->panel_tests_requested;
		}
	} else {
		$testids = explode(',',  $row->panel_tests_requested);
		$testnames_arr = array();
		$panels_arr = array();
		foreach ($testids as $testid) {
			$testid = trim($testid);
			if (stripos($testid, 'group') !== false) {
				$testid = str_ireplace('GROUP_', '', $testid);
				$panels_arr[] = $inb_show_pcode ? $all_groups[$testid]->tg_cpt_code : $all_groups[$testid]->tg_name;
			} else if (isset($all_tests[$testid])) {
				$testnames_arr[] = $all_tests[$testid];
			}
		}
		$testnames = implode(', ', $testnames_arr);
		$panels = implode(', ', $panels_arr);
	}

	if (!empty($row->pa_city)) {
		$row->pa_address .= ', ' . $row->pa_city;
	}

	if (!empty($row->pa_state)) {
		$row->pa_address .= ', ' . $row->pa_state;
	}

	if (!empty($row->pa_zip)) {
		$row->pa_address .= ' ' . $row->pa_zip;
	}

	$i++;
	$row_data = array(
		$row->position,
		$row->resulted ? 'Yes' : 'No',
		$row->assen,
		$row->pa_mrn,
		$row->oldreq,
		$row->patient_name,
		$row->pa_address,
		$row->pa_phone,
		$medications,
		$row->dob,
		$row->pa_gender,
		$row->pa_email,
		$row->requesting_practice,
		$row->cli_city,
		$row->requesting_physician,
		$row->doc_phone,
		$row->doc,
		$row->spec_received,
		$row->lab,
		$row->lab_ref,
		$panels,
		$testnames,
		$row->insurance,
		$row->notes,
		$row->type_of_specimen,
		$all_status[$row->batch_status],
	);


	fputcsv($f, $row_data);
}
$data_log = array(
	'type' => 'Today Batches',
	'file_name' => 'Batch_' . $date . '.csv',
	'total_records' => count($batches),
	'from_date' => $from_date,
	'to_date' => $to_date,
);
trpdd_log_user(get_current_user_id(), 'download_batches', $data_log, '', '', '', '', 'Batches_' . date_i18n('mdY'));

@fclose($f);
if ($filepath) {
	return $filepath;
} else {
	exit;
}
