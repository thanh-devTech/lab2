<?php
global $wpdb, $current_role;
include_once(TRCPDD_PATH . 'pages/formdata.php');

require_once(TRCPDD_PATH . 'api/ci_sample_manifest.php');

$show_no_results_tab = tr_get_option('ci_pdf_data', 'sample_manifest_no_results_tab');
$manifest_tab = isset($_REQUEST['tab']) ? sanitize_text_field($_REQUEST['tab']) : '';
if ($manifest_tab == 'no_results' && !$show_no_results_tab) {
	$manifest_tab = '';
}
$is_no_results_tab = $manifest_tab == 'no_results';
$default_from_date = $is_no_results_tab ? date_i18n('Y-m-d', strtotime('-30 days', current_time('timestamp'))) : date_i18n('Y-m-d');
$from_date = !empty($_REQUEST['fromdate']) ? sanitize_text_field($_REQUEST['fromdate']) : $default_from_date;
$to_date = !empty($_REQUEST['todate']) ? sanitize_text_field($_REQUEST['todate']) : ($is_no_results_tab ? date_i18n('Y-m-d') : $from_date);
$cli_ID = isset($_REQUEST['cli_ID']) ? (int) $_REQUEST['cli_ID'] : 0;
$reflabid = isset($_REQUEST['reflab']) ? (int) $_REQUEST['reflab'] : 0;
$user_id = get_current_user_id();
$no_results_hours = 100;

$server_api_ids = $wpdb->get_col("select wsdl_ID from ci_wsdl_keys where wsdl_url = '' or wsdl_url is null");
$client_api_ids = $wpdb->get_col("select wsdl_ID from ci_wsdl_keys where wsdl_url != '' and wsdl_url is not null");

$conditions = trpdd_get_batch_conditions(0);
$edit_time = $conditions['edit_time'];
$can_edit = $conditions['can_edit'];
$can_delete = $conditions['can_delete'];
$can_hold = $conditions['can_hold_req'];
$where = ' is_saved = 0 ' . $conditions['where'];
$where_cli = $conditions['where_clinic'];

$ref_assen_title = tr_get_option('ci_pdf_data', 'title_pid_field', 'PID');
$show_ref_assen = tr_get_option('ci_pdf_data', 'show_pid_field');
$show_pa_mrn = tr_get_option('ci_pdf_data', 'show_pa_mrn_todayb');
$show_athena_id = tr_get_option('ci_pdf_data', 'report_show_athena_id');
$show_panel = tr_get_option('ci_pdf_data', 'report_show_panel');


$blood_settings = get_option('ci_req_settings_blood', array());
$Phlebotomist = isset($blood_settings['Phlebotomist']) && $blood_settings['Phlebotomist'];
$Phlebotomist_clinics = array();
if ($Phlebotomist) {
	$Phlebotomist_clinics = $wpdb->get_col("select cID from ci_clinic where cli_status = 1 and cli_phlebotomist = 1 ");
}


$sql_clinic = "select cli.* from ci_clinic cli
                    where {$where_cli} 
					group by cli.cID 
					order by cli.cli_clinic";


$clinics = $wpdb->get_results($sql_clinic);
$reflabs = CI_Base_Lab::get_ref_labs();


$select_fields = '';
$joins = '';

if ($reflabid > 0 || $is_no_results_tab) {
	$select_fields .= ", wb.bdate as sent_at, TIMESTAMPDIFF(HOUR, wb.bdate, now()) as sent_hours ";
	//where sent by client api
	$joins .= " JOIN ci_wsdl_batch wb 
					ON wb.btype='[btype]'and wb.b_mid = ba.mid and wb.b_status >0 
					and wb.b_wsdl_ID in (" . implode(',', $client_api_ids) . ") ";
}

if ($reflabid > 0) {
	$where .= " and ba.ref_lab_ID = {$reflabid} ";



	if (!empty($from_date)) {
		$where .= " and wb.bdate >= '{$from_date}' ";
	}
	if (!empty($to_date)) {
		$where .= " and wb.bdate <= '{$to_date} 23:59:59' ";
	}
} else {
	if (!empty($from_date)) {
		$where .= " and ba.doc >= '{$from_date}' ";
	}
	if (!empty($to_date)) {
		$where .= " and ba.doc <= '{$to_date} 23:59:59' ";
	}
}

if ($show_pa_mrn) {
	$select_fields = ', pa.pa_mrn ';
	$joins .= " LEFT JOIN ci_patients pa ON pa.pa_ID = ba.patient_id ";
}
if ($show_athena_id) {
	$select_fields .= ', cli.cli_athena_departmentid ';
	$joins .= " LEFT JOIN ci_clinic cli ON cli.cID = ba.cli_ID ";
}
$all_groups = [];
$all_tests = [];
if ($show_panel) {
	$select_fields .= ', ba.panel_tests_requested ';
	$rs_groups = $wpdb->get_results("select 
	tg_ID, 
	tg_name,
	tg_parent
	from ci_test_groups");
	foreach ($rs_groups as $g) {
		$all_groups[$g->tg_ID] = $g;
	}
	unset($rs_groups);
	$rs_tests = $wpdb->get_results("select 
	te_name,
	te_group_id
	from ci_test_panels");
	foreach ($rs_tests as $t) {
		$all_tests[$t->te_name] = $t->te_group_id;
	}
	unset($rs_tests);
}


if ($cli_ID > 0) {
	$where .= " and ba.cli_ID = {$cli_ID} ";
}

$act = isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : (isset($_REQUEST['act']) ? $_REQUEST['act'] : '');

if ($act == 'settings') {

	if (isset($_POST['opt'])) {
		$opt = $_POST['opt'];
		update_option('tr_sample_manifest_st', $opt, false);
		$hassc = !empty($opt['email']) && !empty($opt['time']);
		if ($hassc) {
			$timezone_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;
			$runtime = strtotime(date('Y-m-d ' . $opt['time'] . ':00')) - $timezone_offset;
			wp_clear_scheduled_hook('cron_all_api', array('CI_Sample_Manifest'));
			wp_schedule_event($runtime, 'daily', 'cron_all_api', array('CI_Sample_Manifest'));
		}
		wp_send_json(array('status' => 'ok'));
	}
	$opt = get_option('tr_sample_manifest_st', array());
?>
	<form method="post" class="ajaxsaveform">
		<div class="frow">
			<label>Email:</label>
			<input type="text" name="opt[email]" value="<?php echo $opt['email'] ?>">
		</div>
		<div class="frow">
			<label>Time</label>
			<select name="opt[time]">
				<?php
				echo '<option value=""></option>';
				for ($i = 1; $i < 24; $i++) {
					for ($j = 0; $j <= 1; $j++) {
						$t = $j == 0 ? $i . ':00' : $i . ':30';
						$selected = $opt['time'] == $t ? 'selected' : '';
						echo '<option value="' . $t . '" ' . $selected . '>' . $t . '</option>';
					}
				}
				?>
			</select>
		</div>
		<div class="frow">
			<input type="submit" class="button-primary" value="Save" />
			<input type="hidden" name="trajax" value="settings" />
		</div>
	</form>
<?php
	exit;
} else if ($act == 'sendmail') {
	$opt = get_option('tr_sample_manifest_st', array());
?>
	<form method="post">
		<div class="frow">
			<label>Email:</label>
			<input type="text" name="opt[email]" value="<?php echo $opt['email'] ?>">
		</div>

		<div class="frow">
			<input type="submit" class="button-primary" value="Send" />
			<input type="hidden" name="act" value="dosendmail" />
		</div>
	</form>
<?php
	exit;
}

$results = array();
if (!empty($act)) {
	$child_qrs = array();
	foreach (tr_pdd_get_batch_types() as $btype) {
		$batch_tbl = tr_pdd_get_table_by_btype($btype);
		$report_tbl = tr_pdd_get_table_report_by_btype($btype);

		$wherebtype = $where;
		$joinsbtype = $joins;

		$joinsbtype = str_replace('[btype]', $btype, $joinsbtype);

		if ($is_no_results_tab) {
			$joinsbtype .= " LEFT JOIN {$report_tbl} re ON re.patient_id = ba.assen  ";
			$wherebtype .= " and (re.re_import_ID = 0 or re.re_import_ID is null)";
		}


		if ($Phlebotomist && count($Phlebotomist_clinics) > 0) {
			$wherebtype .= " and (
					ba.cli_ID  not in (" . implode(',', $Phlebotomist_clinics) . ") 
					or (ba.cli_ID in (" . implode(',', $Phlebotomist_clinics) . ") and ba.batch_status = 'so_collected')
				)";
		}

		$child_qrs[] = "select
			ba.assen,
			ba.oldreq,
			ba.doc,
			ba.cli_ID,
			ba.requesting_practice,
			ba.requesting_physician,
			ba.patient_name,
			ba.insurance
			{$select_fields}
			from {$batch_tbl} ba
			{$joinsbtype}
			where {$wherebtype} ";
	}

	if (!empty($child_qrs)) {
		$sql = "select * from (
			" . implode(' UNION ALL ', $child_qrs) . "
		) as ba ";
		$sql .= $is_no_results_tab ? " order by sent_at asc, requesting_practice asc" : " order by requesting_practice asc, doc desc";
		$results = $wpdb->get_results($sql);
	}
}

if (isset($_GET['debug'])) {
	echo '<pre>';
	print_r($sql);
	echo '</pre>';
}


if ($act === 'Export') {

	header("Content-disposition: attachment; filename=" . ($is_no_results_tab ? "sample_manifest_no_results.csv" : "sample_manifest.csv"));
	header("Content-Type: text/csv");
	$output = fopen("php://output", 'w');
	$headers = array();
	if ($show_ref_assen) {
		$headers[] = $ref_assen_title;
	}
	$headers[] = 'Accession';
	$headers[] = 'Practice';
	$headers[] = 'Ordering Physician';
	$headers[] = 'Patient';
	if ($show_athena_id) {
		$headers[] = "Athena ID";
	}
	if ($show_panel) {
		$headers[] = "Panel";
	}
	$headers[] = 'Insurance';
	if ($is_no_results_tab) {
		$headers[] = 'Sent At';
		$headers[] = 'Hours Since Sent';
	}
	$headers[] = 'Date';
	$headers[] = 'Sample Present?';

	fputcsv($output, $headers);
	$clinicarr = array();
	foreach ($results as $i => $r) {
		if (!in_array($r->requesting_practice, $clinicarr)) {
			if (count($clinicarr) > 0) {
				fputcsv($output, array(''));
				fputcsv($output, array(''));
			}
			$row = array(
				'',
				'',
				'',
				$r->requesting_practice,
			);

			fputcsv($output, $row);
			//fputcsv($output, array(''));

			$clinicarr[] = $r->requesting_practice;
		}
		$row = array();
		if ($show_ref_assen) {
			$row[] = $r->oldreq . "\0";
		}
		$row[] = $r->assen . "\0";
		$row[] = $r->requesting_practice;
		$row[] = $r->requesting_physician;
		$row[] = $r->patient_name;
		if ($show_athena_id) {
			$row[] = $r->cli_athena_departmentid;
		}
		if ($show_panel) {
			$panels = [];
			foreach (explode(',', $r->panel_tests_requested) as $t) {
				if (stripos($t, 'group') !== false) {
					$group_id = str_ireplace('group_', '', $t);
					if (isset($all_groups[$group_id])) {
						$panels[] = $all_groups[$group_id]->tg_name;
					}
				} else {
					if (isset($all_tests[$t]) && isset($all_groups[$all_tests[$t]])) {
						$panels[] = $all_groups[$all_tests[$t]]->tg_name;
					}
				}
			}

			$row[] = implode(', ', array_unique($panels));
		}
		$row[] = $r->insurance;
		if ($is_no_results_tab) {
			$row[] = $r->sent_at;
			$row[] = $r->sent_hours;
		}
		$row[] = $r->doc;
		$row[] = '';


		fputcsv($output, $row);
	}
	@fclose($output);
	$data_log = [
		'type' => $is_no_results_tab ? 'Sample Manifest No Results Report' : 'Sample Manifest Report',
		'file_name' => $is_no_results_tab ? 'sample_manifest_no_results.csv' : 'sample_manifest.csv',
		'total_records' => count($results),
		'from_date' => $from_date,
		'to_date' => $to_date,
	];
	trpdd_log_user(get_current_user_id(), 'download_report', $data_log, '', '', '', '', 'Report_' . date_i18n('mdY'));

	exit;
} else if ($act === 'PDF') {
	ob_start();
	include('sample_manifest_html.php');
	$html = ob_get_clean();
	$html = trpdd_pdf_html($html);
	$filename = 'sample_manifest.pdf';
	header("Pragma: public");
	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename=\"$filename\";");
	trpdd_build_pdf($html, array('filename' => $filename));
	exit;
} else if ($act === 'print') {
	ob_start();
	include('sample_manifest_html.php');
	$html = ob_get_clean();
	$html = trpdd_pdf_html($html);
	echo $html;
	echo '<script>window.print()</script>';
	exit;
} else if ($act == 'dosendmail') {

	$client = new CI_Sample_Manifest();
	$emails = explode(',', $_POST['opt']['email']);
	$rs = false;
	foreach ($emails as $email) {
		$email = trim($email);
		if (!empty($email)) {
			$rs = $client->send($email);
		}
	}


	if ($rs) {
		Tr_Session_Class::add('Sent!');
	} else {
		Tr_Session_Class::add_error('Empty');
	}
}

?>

<style>
	.viewhtml table {
		border-collapse: collapse;
		width: 100%;
	}

	.viewhtml table th {
		background: #ddd;
	}

	.viewhtml table td,
	.viewhtml table th {
		border: 1px solid #ddd;
	}

	.viewhtml table tr.no-result-warning td {
		background: #f8d7da;
		color: #721c24;
	}
</style>
<script>
	jQuery(function($) {
		$('body').on('click', '.printbtn', function() {
			$('.frameprint').remove();
			var $url = '?' + $('#formsearch').serialize() + '&trajax=print';
			$('body').append('<iframe class="frameprint" style="position:absolute;left:-99999px" src="' +
				$url + '"></iframe>');
			return false;
		}).on('submit', 'form.ajaxsaveform', function() {
			var $btn = $('input[type="submit"]', $(this));
			$btn.val('Saving...');
			$.ajax({
				data: $(this).serialize(),
				dataType: 'json',
				type: 'post',
				success: function(rs) {
					$btn.val('Save');
					if (rs.status == 'ok') {

					} else {
						alert('Error. Please try again!');
					}
				}
			});
			return false;
		})
	})
</script>

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
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?' . TRCPDD_VS ?>"></script>


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
							</svg><span class="truncate max-w-[120px] sm:max-w-none">Sample Manifest
							</span></span></li>
				</ol>
			</nav>
		</div>
		<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
			<div>
				<h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">
					<?php echo $is_no_results_tab ? 'No Result' : 'Sample Manifest' ?>
				</h1>
				<p class="text-muted-foreground mt-1">Complete sample tracking and chain of custody documentation</p>
			</div>
		</div>
		<?php if ($show_no_results_tab) { ?>
			<?php
			$manifest_url = esc_url(add_query_arg(array(
				'page' => isset($_GET['page']) ? $_GET['page'] : '',
				'tab' => '',
				'fromdate' => $from_date,
				'todate' => $to_date
			)));
			$no_result_url = esc_url(add_query_arg(array(
				'page' => isset($_GET['page']) ? $_GET['page'] : '',
				'tab' => 'no_results',
				'fromdate' => date_i18n('Y-m-d', strtotime('-30 days', current_time('timestamp'))),
				'todate' => date_i18n('Y-m-d')
			)));
			?>
			<div class="space-y-3">
				<div class="bg-gradient-to-r to-gray-100 rounded-2xl p-3 shadow-sm">
					<div class="flex flex-wrap gap-2">
						<button onclick="window.location='<?php echo $manifest_url ?>'" class="cursor-pointer inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm <?php echo !$is_no_results_tab ? 'bg-gradient-to-r from-primary to-primary/90 text-white shadow-md' : 'text-gray-600 hover:bg-white hover:shadow-sm' ?>">
							<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
								fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
								stroke-linejoin="round" class="lucide lucide-clipboard-list h-3.5 w-3.5" aria-hidden="true">
								<rect width="8" height="4" x="8" y="2" rx="1" ry="1"></rect>
								<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
								<path d="M12 11h4"></path>
								<path d="M12 16h4"></path>
								<path d="M8 11h.01"></path>
								<path d="M8 16h.01"></path>
							</svg><span>Manifest</span>
						</button>
						<button onclick="window.location='<?php echo $no_result_url ?>'" class="cursor-pointer inline-flex items-center gap-2 px-4 py-2.5 rounded-xl transition-all duration-200 font-medium text-sm <?php echo $is_no_results_tab ? 'bg-gradient-to-r from-primary to-primary/90 text-white shadow-md' : 'text-gray-600 hover:bg-white hover:shadow-sm' ?>">
							<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
								fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
								stroke-linejoin="round" class="lucide lucide-circle-alert h-3.5 w-3.5" aria-hidden="true">
								<circle cx="12" cy="12" r="10"></circle>
								<line x1="12" x2="12" y1="8" y2="12"></line>
								<line x1="12" x2="12.01" y1="16" y2="16"></line>
							</svg><span>No Result</span>
						</button>
					</div>
				</div>
			</div>
		<?php } ?>
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
										</svg></div>Volume by Sample Manifest
								</h4>
							</div>
						</div>
						<div class="flex flex-wrap items-center gap-3 mt-4">
							<?php Tr_Session_Class::show() ?>
							<br><br>
							<form method="get" target="_blank" style="margin-bottom:20px" id="formsearch">
								<input type="hidden" name="page"
									value="<?php echo isset($_GET['page']) ? $_GET['page'] : '' ?>" />
								<input type="hidden" name="tab"
									value="<?php echo esc_attr($manifest_tab) ?>" />
								From: <input type="text" class="hasdate" name="fromdate" style="width:100px"
									value="<?php echo @$from_date ?>" required />
								<label id="summarytodate">To:</label>
								<input type="text" class="hasdate today" name="todate" style="width:100px"
									value="<?php echo @$to_date ?>" required />
								<select name="cli_ID" class="js_select2" style="max-width:270px">
									<option value="">All Clinics</option>
									<?php
									foreach ($clinics as $cli) {
										$selected = $cli->cID == $cli_ID ? 'selected' : '';
										echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
									}
									?>
								</select>

								<?php
								if (count($reflabs) > 0) {
								?>
									<select id="reflab" name="reflab">
										<?php
										echo '<option value="">Select</option>';
										foreach ($reflabs as $rlab) {
											$selected = $rlab->lab_ID == $reflabid ? 'selected' : '';
											echo '<option value="' . $rlab->lab_ID . '" ' . $selected . '>' . $rlab->lab_name . '</option>';
										}
										?>
									</select>
								<?php
								}
								?>

								<input type="submit" name="act" value="View"
									onclick="jQuery('#formsearch').removeAttr('target')" class="button" />
								<?php if (!$is_no_results_tab) { ?>
									<input type="submit" name="trajax" value="Export" class="button" />
									<input type="button" name="print" value="Print" class="button printbtn" />
									<input type="submit" name="trajax" value="PDF" class="button" />
									<?php
									if (tr_current_role_in(array('administrator', 'lab', 'labb'))) {
									?>
										<a title="Send Mail" class="thickbox button"
											href="<?php Tr_Base_Class_V6::link(array('trajax' => 'sendmail')) ?>">Send Mail</a>
										<a title="Auto Send Manifest" class="thickbox button"
											href="<?php Tr_Base_Class_V6::link(array('trajax' => 'settings')) ?>">Settings</a>
									<?php
									}
									?>
								<?php
								}
								?>
							</form>
						</div>
					</div>

					<?php if ($act == 'View') {
						echo '<div class="viewhtml">';
						include('sample_manifest_html.php');
						echo '</div>';
					}
					?>
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