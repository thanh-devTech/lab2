<?php
global $wpdb, $current_role, $blog_id;

$all_groups_tests = array();
$rs = $wpdb->get_results("select te_ID, te_name from ci_test_panels 
                where te_type='blood' and te_in_req =1 ");
foreach ($rs as $r) {
	$all_groups_tests[$r->te_ID] = $r->te_name;
}
$rs = $wpdb->get_results("select tg_ID, tg_name from ci_test_groups
                    where tg_type='blood' and tg_show_req = 1");
foreach ($rs as $r) {
	$all_groups_tests['GROUP_' . $r->tg_ID] = $r->tg_name;
}


wp_enqueue_script('jquery-ui-datepicker');

wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');
wp_enqueue_script('jquery-validate', TRCPDD_URL . 'admin/js/jquery.validate.min.js', array('jquery'), true);
wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);


$date_from = isset($_REQUEST['date_from']) ? $_REQUEST['date_from'] : '';
$date_to   = isset($_REQUEST['date_to']) ? $_REQUEST['date_to'] : '';
$lab_id = isset($_REQUEST['lab_id']) ? $_REQUEST['lab_id'] : 0;
$sort = isset($_REQUEST['sort']) && $_REQUEST['sort'] == 'desc' ? 'desc' : 'asc';
$sortby = isset($_REQUEST['sortby']) ? $_REQUEST['sortby'] : 'doc';

if (isset($_REQUEST['submit'])) {
	if (empty($date_from)) $date_from = date_i18n('Y-m-d');
	if (empty($date_to)) $date_to = date_i18n('Y-m-d');


	$where = " and ba.doc >= '{$date_from}' and ba.doc <= '{$date_to} 23:59:59' ";

	if (isset($_GET['byb'])) {
		$batch_from = str_replace('-', '', $date_from);
		$batch_to = str_replace('-', '', $date_to);
		$where = " and ba.batch_id >= {$batch_from} and ba.batch_id <= {$batch_to} ";
	}


	if (CI_Base_Lab::has_user_labs()) {
		$lab_str = CI_Base_Lab::get_user_lab_ids_str();
		$where .= " and ba.lab_id in ( {$lab_str} ) ";
	}

	$headers = array(

		'assen' => 'Accession',
		'oldreq' => 'Ref ID',
		'batch_status' => 'case_status',
		'panel_tests_requested' => 'Panels Ordered',
		'pa_code' => 'patient ID',
		'pa_last' => 'patient_last_name',
		'pa_first' => 'patient_first_name',
		'pa_dob' => 'patient_dob',
		'pa_address' => 'patient_address',
		'null_2' => 'patient_address_2',
		'pa_city' => 'patient_city',
		'pa_state' => 'patient_state',
		'pa_zip' => 'patient_zip',
		'pa_mrn' => 'patient_MRN',
		'pa_gender' => 'patient_gender',
		'pa_phone' => 'patient_phone',
		'pa_email' => 'Patient_email',
		'pa_ssn' => 'Patient_ssn',
		'pa_race' => 'Patient_race',
		'pa_ethnicity' => 'Patient_ethnicity',
		'p_pain_score' => 'Pain Score',
		'p_fatigue_score' => 'Fatigue Score',
		'p_condition' => 'Reason for Testing',
		'dagnostic_codes1' => 'Diagnosis Codes',
		'doc' => 'Collection Date',
		'approve_date' => 'Reported Date',
		'doc_last' => 'physician_last_name',
		'doc_first' => 'physician_first_name',
		'requesting_practice' => 'facility_name',
		'doc2' => 'Collection Date',
		'p_fin' => 'FIN #',
		'pa_mrn2' => 'MRN #',
		'p_ehr' => 'EHR #',
		'medication' => 'Pain Medication',
		'result_hb' => 'Hb Reading (g/dl)',
		'result_MFI-3' => 'AUC @3',
		'result_MFI-10' => 'AUC @10',
		'result_PreExisting Hemolysis' => 'Pre-Existing Hemolysis %',
		'result_VCAM1' => 'VCAM AI',
		'result_PSEL' => 'PSEL AI',
		'notes' => 'Notes 1',
		'billing_notes' => 'Billing Notes',
		'notes_report' => 'Clinical Notes',
	);


	$fields = array();
	foreach ($headers as $k => $v) {
		$len = strlen($k);
		if (stripos($k, 'null_') !== false) {
			$k = " null as {$k} ";
		} else if ($k[$len - 1] == '2') {
			$k = substr($k, 0, $len - 1);
			$k = "{$k} as {$k}2";
		} else if (substr($k, 0, 7) == 'result_') {
			$drug = str_replace('result_', '', $k);
			$k = "(select conc from ci_results_blood where report_id = re.id and drug='{$drug}' limit 1) as `{$k}`";
		}
		$fields[] = $k;
	}
	$fields = implode(', ', $fields);
	$sql = "
            SELECT
               {$fields}

                from ci_batches_blood ba
                join ci_reports_blood re ON ba.assen = re.patient_id
                left join ci_patients pa ON pa.pa_ID = ba.patient_id
                left join ci_doc doc ON doc.doc_ID = ba.doc_ID
                where  re.estatus = 'C'
                {$where} 
                group by ba.mid 
                ";

	$rows = $wpdb->get_results($sql, ARRAY_A);

	if (isset($_GET['debug'])) {
		echo $sql;
		echo '<br><br>';
		var_dump($wpdb->last_error);
		echo '<br><br>';
		var_dump($sql);
		echo '<br><br>';
	}
	$file_name = 'export.csv';
	header("Content-disposition: attachment; filename={$file_name}");
	header("Content-Type: text/csv");
	$f = fopen('php://output', 'w');
	fputcsv($f, $headers);
	foreach ($rows as $row) {
		$panels = array();
		foreach (explode(',', $row['panel_tests_requested']) as $t) {
			if (isset($all_groups_tests[$t])) {
				$panels[] = $all_groups_tests[$t];
			}
		}

		$row['panel_tests_requested'] = implode(', ', $panels);
		fputcsv($f, $row);
	}
	$data_log = [
		'type' => 'Blood Result Report',
		'file_name' => $file_name,
		'total_records' => count($rows),
		'from_date' => $date_from,
		'to_date' => $date_to,
	];
	trpdd_log_user(get_current_user_id(), 'download_report', $data_log, '', '', '', '', 'Report_' . date_i18n('mdY'));
	exit;
}
?>

<h2>Blood Result Report</h2>
<?php Tr_Session_Class::show() ?>

<form method="get">
	<span class="onelinemobile">
		<label>From: </label>
		<input type="search" autocomplete="off" name="date_from" id="from" value="<?php echo $date_from ?>" />
	</span>
	<span class="onelinemobile">
		<label>To: </label>
		<input type="search" autocomplete="off" name="date_to" id="to" value="<?php echo $date_to ?>" />
	</span>

	<input type="submit" name="submit" value="Export" class="button" />
	<input type="hidden" name="trajax" value="1" />
	<input type="hidden" name="act" value="export" />
	<input type="hidden" name="page" value="<?php echo $_GET['page'] ?>">
</form>


<script>
	jQuery(function($) {
		$("#from").datepicker({
			changeMonth: true,
			changeYear: true,
			numberOfMonths: 1,
			dateFormat: 'yy-mm-dd',
			onClose: function(selectedDate) {
				$("#to").datepicker("option", "minDate", selectedDate);
			}
		});
		$("#to").datepicker({
			changeMonth: true,
			changeYear: true,
			numberOfMonths: 1,
			dateFormat: 'yy-mm-dd',
			onClose: function(selectedDate) {
				$("#from").datepicker("option", "maxDate", selectedDate);
			}
		});
	})
</script>