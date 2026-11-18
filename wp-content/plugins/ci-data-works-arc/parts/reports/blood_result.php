<?php
global $wpdb, $current_role, $blog_id;

$schedule_report 	= new CI_Report_Schedule('blood_result', 'Blood Result Report');

$trajax         	= isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : '';

$export_func 		= isset($_REQUEST['export_func']) ? $_REQUEST['export_func'] : '';
$export_type 		= isset($_REQUEST['export_type']) ? $_REQUEST['export_type'] : '';

$filepath_save_pdf 	= isset($_REQUEST['filepath_save_pdf']) ? $_REQUEST['filepath_save_pdf'] : '';
$filepath_save_csv 	= isset($_REQUEST['filepath_save_csv']) ? $_REQUEST['filepath_save_csv'] : '';

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
$date_to = isset($_REQUEST['date_to']) ? $_REQUEST['date_to'] : '';
$lab_id = isset($_REQUEST['lab_id']) ? $_REQUEST['lab_id'] : 0;
$sort = isset($_REQUEST['sort']) && $_REQUEST['sort'] == 'desc' ? 'desc' : 'asc';
$sortby = isset($_REQUEST['sortby']) ? $_REQUEST['sortby'] : 'doc';


$schedule_report->ajax();

if ($export_func != '' || $filepath_save_csv != "") {
	if (empty($date_from))
		$date_from = date_i18n('Y-m-d');
	if (empty($date_to))
		$date_to = date_i18n('Y-m-d');


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
	tr_pdd_header('csv', 'Blood_Result_Report.csv');
	if ($filepath_save_csv != '') {
		$f = fopen($filepath_save_csv, 'w');
		$csv_out_file = basename('Blood Result Report.csv');
	} else {
		$f = fopen('php://output', 'w');
	}
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
	fclose($f);
	if ($filepath_save_csv == '') exit;
}

if ($filepath_save_csv != '' || $filepath_save_pdf != '') {
	return;
}
?>
<style>
	h1,
	h2,
	h3,
	h4,
	h5,
	h6 {
		font-weight: unset !important;
		font-size: inherit;
		margin-bottom: 6px !important;
	}

	h1,
	h4 {
		margin: unset;
	}

	h1 {
		margin-bottom: 2px;
	}

	h4 {
		margin-bottom: 5px;
	}

	h5 {
		font-weight: var(--font-weight-medium);
		margin: 0px;
		--tw-font-weight: var(--font-weight-medium);
		font-size: 13px;
		margin-bottom: 10px;
	}

	.text-primary {
		color: var(--primary);
	}

	ol {
		list-style-type: none;
		margin: 0px;
		margin-top: px;
		padding-top: 5px;
		padding-left: 5px;
	}

	ol li a {
		color: unset;
	}


	.text-2xl {
		font-size: var(--text-2xl);
		line-height: var(--tw-leading, var(--text-2xl--line-height));
	}
</style>
<?php $schedule_report->style(); ?>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?' . TRCPDD_VS ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
	<div class="space-y-6">
		<div class="bg-white border rounded-lg px-3 sm:px-6 py-2 sm:py-3 shadow-sm">
			<nav aria-label="breadcrumb" data-slot="breadcrumb">
				<ol data-slot="breadcrumb-list"
					class="text-muted-foreground flex items-center gap-1.5 text-sm break-words sm:gap-2.5 flex-wrap">
					<li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5">
						<a data-slot="breadcrumb-link"
							class="hover:text-foreground transition-colors flex items-center gap-1 text-xs sm:text-sm"
							href="?page=dashboard_main">
							<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
								fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
								stroke-linejoin="round" class="lucide lucide-house h-3 w-3" aria-hidden="true">
								<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"></path>
								<path
									d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z">
								</path>
							</svg>
							<span class="truncate max-w-[120px] sm:max-w-none">Dashboard</span>
						</a>
					</li>

					<li data-slot="breadcrumb-separator" role="presentation" aria-hidden="true"
						class="[&amp;&gt;svg]:size-3.5">
						<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
							stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
							class="lucide lucide-chevron-right">
							<path d="m9 18 6-6-6-6"></path>
						</svg>
					</li>

					<li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5">
						<a data-slot="breadcrumb-link"
							class="hover:text-foreground transition-colors flex items-center gap-1 text-xs sm:text-sm"
							href="?page=ci_reports_main">
							<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
								fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
								stroke-linejoin="round" class="lucide lucide-file-text h-3 w-3" aria-hidden="true">
								<path
									d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z">
								</path>
								<path d="M14 2v5a1 1 0 0 0 1 1h5"></path>
								<path d="M10 9H8"></path>
								<path d="M16 13H8"></path>
								<path d="M16 17H8"></path>
							</svg>
							<span class="truncate max-w-[120px] sm:max-w-none">Reports</span>
						</a>
					</li>

					<li data-slot="breadcrumb-separator" role="presentation" aria-hidden="true"
						class="[&amp;&gt;svg]:size-3.5">
						<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
							stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
							class="lucide lucide-chevron-right">
							<path d="m9 18 6-6-6-6"></path>
						</svg>
					</li>
					<li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5"><span
							data-slot="breadcrumb-page" role="link" aria-disabled="true" aria-current="page"
							class="text-foreground font-normal flex items-center gap-1 text-xs sm:text-sm"><svg
								xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
								fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
								stroke-linejoin="round" class="lucide lucide-chart-column h-3 w-3" aria-hidden="true">
								<path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
								<path d="M18 17V9"></path>
								<path d="M13 17V5"></path>
								<path d="M8 17v-3"></path>
							</svg><span class="truncate max-w-[120px] sm:max-w-none">Blood Result Report</span></span>
					</li>
				</ol>
			</nav>
		</div>
		<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
			<div>
				<h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">
					Blood Result Report</h1>
				<p class="text-muted-foreground mt-1">Export Blood Result Report</p>
			</div>
		</div>
		<div>
			<div class="space-y-5">
				<div data-slot="card"
					class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
					<div data-slot="card-header"
						class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 pb-3">
						<div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
							<div>
								<h4 data-slot="card-title" class="leading-none flex items-center gap-2">
									<div class="p-1.5 bg-blue-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
											width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
											stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
											class="lucide lucide-chart-column h-4 w-4 text-blue-600" aria-hidden="true">
											<path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
											<path d="M18 17V9"></path>
											<path d="M13 17V5"></path>
											<path d="M8 17v-3"></path>
										</svg></div>Export Blood Result Report data to csv file
								</h4>
							</div>

							<div class="flex items-center gap-3">
								<div style="position:relative;display:inline-block;">

									<button id="export_report" ; data-slot="dropdown-menu-trigger"
										class="inline-flex btn-export cursor-pointer items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive text-primary-foreground h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 bg-primary hover:bg-primary/90"
										type="button" id="radix-:rkg:" aria-haspopup="menu" aria-expanded="false"
										data-state="closed"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
											viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
											stroke-linecap="round" stroke-linejoin="round"
											class="lucide lucide-download h-4 w-4 mr-1" aria-hidden="true">
											<path d="M12 15V3"></path>
											<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
											<path d="m7 10 5 5 5-5"></path>
										</svg>Export Report
										</svg>
									</button>
								</div>
								<?php $schedule_report->btn_schedule(); ?>
							</div>

						</div>

					</div>
					<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-6">
						<?php Tr_Session_Class::show() ?>

						<form method="get">
							<span class="onelinemobile">
								<label>From: </label>
								<input type="search" autocomplete="off" name="date_from" id="from"
									value="<?php echo $date_from ?>" />
							</span>
							<span class="onelinemobile">
								<label>To: </label>
								<input type="search" autocomplete="off" name="date_to" id="to"
									value="<?php echo $date_to ?>" />
							</span>

							<input type="hidden" name="trajax" value="1" />
							<input type="hidden" name="act" value="export" />
							<input type="hidden" name="page" value="<?php echo $_GET['page'] ?>">
						</form>
					</div>
				</div>

				<div class="bg-gray-50 border border-gray-200 rounded-lg py-4 px-4">
					<p class="text-xs text-gray-600 text-center">
						<strong style="display: block;padding-bottom:3px;">Ignite Laboratory Information System</strong>
						igniteLIS analytics reports are intended for authorized laboratory and administrative users. These reports provide aggregated operational data to support workflow monitoring, performance oversight, and applicable compliance or public-health reporting.
					</p>
				</div>

			</div>
		</div>
	</div>
</div>
<script>
	var url_main_page = 'ci_reports_blood_result';
	var from_date = '<?php echo $date_from; ?>';
	var to_date = '<?php echo $date_to; ?>';
	jQuery(function($) {
		$("#from").datepicker({
			changeMonth: true,
			changeYear: true,
			numberOfMonths: 1,
			dateFormat: 'yy-mm-dd',
			onClose: function(selectedDate) {
				from_date = selectedDate;
				$("#to").datepicker("option", "minDate", selectedDate);
			}
		});
		$("#to").datepicker({
			changeMonth: true,
			changeYear: true,
			numberOfMonths: 1,
			dateFormat: 'yy-mm-dd',
			onClose: function(selectedDate) {
				to_date = selectedDate;
				$("#from").datepicker("option", "maxDate", selectedDate);
			}
		});

		$(".btn-export").click(async function() {
			var $url = location.href + '&trajax=export_report&export_type=csv';
			$url += '&export_func=all';
			$url += '&date_from=' + from_date;
			$url += '&date_to=' + to_date;
			showToast('info', 'Preparing your download...', 'Generating...', 4000);
			try {
				const response = await fetch($url);

				// 🔥 Lấy filename từ header
				const disposition = response.headers.get("Content-Disposition");
				let filename = "download.csv";

				if (disposition && disposition.includes("filename=")) {
					const match = disposition.match(/filename="?([^"]+)"?/);
					if (match && match[1]) filename = match[1];
				}

				const blob = await response.blob();

				const a = document.createElement("a");
				a.href = URL.createObjectURL(blob);
				a.download = filename; // giữ nguyên tên file
				a.click();

				showToast('success', 'Download ready!', 'Your file "' + filename +
					'" has been downloaded.', 4000);

			} catch (err) {
				showToast('error', 'Error', 'Download failed.', 4000);
			}
			return false;
		});
	})
</script>
<?php $schedule_report->js(); ?>
