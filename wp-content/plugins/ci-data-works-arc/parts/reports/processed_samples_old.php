<?php
global $wpdb, $current_role, $ci_all_btypes;
$schedule_report = new CI_Report_Schedule('processed_samples', 'Processed Samples Report');
include_once(TRCPDD_PATH . 'pages/formdata.php');

$cli_ID = isset($_REQUEST['cli_ID']) ? $_REQUEST['cli_ID'] : 0;
$btype_selected = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'pcr';
$date_type = isset($_REQUEST['datetype']) ? $_REQUEST['datetype'] : 'doc';
$month_range    = isset($_REQUEST['month_range']) ? $_REQUEST['month_range'] : 'last_0_month';
$export_func = isset($_REQUEST['export_func']) ? $_REQUEST['export_func'] : '';
$export_type = isset($_REQUEST['export_type']) ? $_REQUEST['export_type'] : '';

$filepath_save_pdf = isset($_REQUEST['filepath_save_pdf']) ? $_REQUEST['filepath_save_pdf'] : '';
$filepath_save_csv = isset($_REQUEST['filepath_save_csv']) ? $_REQUEST['filepath_save_csv'] : '';

$user_id = get_current_user_id();
$adding_tests_markers = array();
$clinics = array();
$clinicname = '';

if (count($ci_all_btypes) === 1 || ($btype_selected == 'pcr' && !in_array('pcr', $ci_all_btypes))) {
	$btype_selected = array_shift(array_values($ci_all_btypes));
}

$ftimethismonth = strtotime(date_i18n('Y-m-01'));
$filter_date_select = [];
for ($i = 0; $i <= 5; $i++) {
	$filter_date_select[] = [
		'value' => 'last_' . $i . '_month',
		'text' => date_i18n('F Y', strtotime("first day of -$i month", $ftimethismonth))
	];
}
$filter_date_select[] = ['value' => 'custom', 'text' => 'Custom'];

$from_date = date_i18n('Y-m-01');
$to_date = date_i18n('Y-m-t');

switch ($month_range) {
	case 'last_0_month':
		$from_date = date_i18n('Y-m-01');
		$to_date = date_i18n('Y-m-t');
		break;

	case 'last_1_month':
	case 'last_2_month':
	case 'last_3_month':
	case 'last_4_month':
	case 'last_5_month':
		$offset = intval(str_replace(['last_', '_month'], '', $month_range));
		$from_date = date_i18n('Y-m-01', strtotime("first day of -$offset month", $ftimethismonth));
		$to_date = date_i18n('Y-m-t', strtotime("last day of -$offset month", $ftimethismonth));
		break;

	case 'custom_range':
	default:
		$from_date = isset($_REQUEST['from']) ? $_REQUEST['from'] : date_i18n('Y-m-01');
		$to_date = isset($_REQUEST['to']) ? $_REQUEST['to'] : date_i18n('Y-m-t');
		break;
}
$text_sel_date_rep = date_i18n('M j', strtotime($from_date)) . " - " . date_i18n('M j', strtotime($to_date));


$labname = CI_Base_Lab::getCurrentLabName();


$date_types = array('doc' => 'DOC', 'approve' => 'Approved Date', 'received' => 'Received', 'bid' => 'Batch#');


$conditions = trpdd_get_batch_conditions(0, 'pcr');
$edit_time = $conditions['edit_time'];
$can_edit = $conditions['can_edit'];
$can_delete = $conditions['can_delete'];
$can_hold = $conditions['can_hold_req'];
$where = ' is_saved = 0 ' . $conditions['where'];
$where_clinic = $conditions['where_clinic'];
if ($conditions['check_author']) {
	$where .= $return['where_author'];
}


$clinics_rs = $wpdb->get_results("select cID, cli_clinic, cli_status from ci_clinic where {$where_clinic}");
foreach ($clinics_rs as $r) {
	$clinics[$r->cID] = $r;
}

$inactive_clinics = array();
$where_clinic_inactive = str_replace('cli_status = 1', 'cli_status = 0', $where_clinic);
$clinics_inactive_rs = $wpdb->get_results("select cID, cli_clinic, cli_status from ci_clinic where {$where_clinic_inactive}");
foreach ($clinics_inactive_rs as $r) {
	$inactive_clinics[$r->cID] = $r;
}

if (isset($_GET['debugconditions'])) {
	var_dump($conditions);
	var_dump($clinics);
	var_dump($inactive_clinics);
}

$batch_tbl = tr_pdd_get_table_by_btype($btype_selected);
$report_tbl = tr_pdd_get_table_report_by_btype($btype_selected);
$result_tbl = tr_pdd_get_table_result_by_btype($btype_selected);
$panel = array();
$submited = false;
$reports = array();
$reports_clinics = array();
$tests = array();


$all_panels = array();
$all_labs = array();

$all_tests = array();
if ($btype_selected == 'tox') {
	$rs = $wpdb->get_results("select te_group_id , te_name from ci_test_panels where te_type = '{$btype_selected}' ");
	foreach ($rs as $r) {
		$all_tests[strtoupper($r->te_name)] = $r->te_group_id;
	}
}

$rs = $wpdb->get_results("select tg_ID, tg_name
				from ci_test_groups 
				where tg_type = '{$btype_selected}' 
				order by tg_position asc");


foreach ($rs as $r) {
	$all_panels[$r->tg_ID] = $r->tg_name;
}

$rs = $wpdb->get_results("select * from ci_labs");
foreach ($rs as $r) {
	$all_labs[$r->lab_ID] = $r;
}

$all_rejected_status = tr_pdd_where_batch_rejected(true);

$report_clids = array();
if (!empty($from_date)) {

	$coldate = $date_type == 'doc' ? 'ba.doc' : 're.approve_date';
	if ($date_type == 'received') {
		$coldate = 'ba.spec_received';
	}


	if ($date_type == 'bid') {
		$coldate = 'ba.batch_id';
		$batch_from = str_replace('-', '', $from_date);
		$batch_to = str_replace('-', '', $to_date);

		$where .= " and {$coldate} >= '{$batch_from}' ";
		if (!empty($to_date)) {
			$where .= " and {$coldate} <= '{$batch_to}' ";
		}
	} else {
		$where .= " and {$coldate} >= '{$from_date}' ";
		if (!empty($to_date)) {
			$where .= " and {$coldate} <= '{$to_date} 23:59:59' ";
		}
	}




	if ($cli_ID > 0) {
		$where .= " and ba.cli_ID = {$cli_ID} ";
	}

	$where .= " and re.estatus = 'C' ";

	$rerunfield = 're.covid_pos';
	if ($btype_selected == 'pcr') {
		$rerunfield .= ',re.rerun_count';
	}

	$sql_reports = "select 
					ba.panel_tests_requested,
					ba.cli_ID,
					ba.ref_lab_ID,
					ba.batch_status,
					re.id,
					re.estatus,
					{$rerunfield}
					from {$batch_tbl} ba
					join {$report_tbl} re ON re.patient_id = ba.assen
					where {$where}";
	$rows = $wpdb->get_results($sql_reports);

	if (isset($_GET['debug'])) {
		var_dump($sql_reports);
	}

	foreach ($rows as $row) {
		$groupsids = array();
		foreach (explode(',', $row->panel_tests_requested) as $t) {
			if (stripos($t, 'GROUP_') !== false) {
				$gid = str_replace('GROUP_', '', $t);
				if (!in_array($gid, $groupsids)) {
					$groupsids[] = $gid;
				}
			} else if ($btype_selected == 'tox' && isset($all_tests[strtoupper($t)])) {
				$gid = $all_tests[strtoupper($t)];

				if (!in_array($gid, $groupsids)) {
					$groupsids[] = $gid;
				}
			}
		}


		foreach ($groupsids as $panelid) {

			$gid = $panelid;
			if ($cli_ID == -1) {
				$gid .= '_' . $row->cli_ID;
				$report_clids[$row->cli_ID] = $row->cli_ID;
			}
			if (!isset($reports[$gid])) {
				$reports[$gid] = array(
					'panel' => $all_panels[$panelid],
					'processed' => 0,
					'main' => 0,
					'ref' => 0,
					'refs' => array(),
					'rejected' => 0,
					'rerun' => 0,
				);
			}

			$reports[$gid]['processed']++;

			if ($row->ref_lab_ID > 0) {

				$reports[$gid]['ref']++;
				if (!isset($reports[$gid]['refs'][$row->ref_lab_ID])) {
					$reports[$gid]['refs'][$row->ref_lab_ID] = 0;
				}
				$reports[$gid]['refs'][$row->ref_lab_ID]++;
			} else {
				$reports[$gid]['main']++;
			}

			if (isset($all_rejected_status[$row->batch_status])) {
				$reports[$gid]['rejected']++;
				continue;
			}


			if (isset($row->rerun_count) && $row->rerun_count > 0) {
				$reports[$gid]['rerun']++;
			} else if (isset($row->covid_pos) && $row->covid_pos == -2) {
				$reports[$gid]['rerun']++;
			}
		}
	}
}

ksort($reports);

$schedule_report->ajax();

if ($export_func != "" || $filepath_save_csv != "") {
	$generated_on = date_i18n("m/d/Y \\a\\t h:i A");
	$filename = generate_report_filename('RPT', 'ProcessedSamples', date_i18n('Y-m-d'), "", 'csv');
	$headers = array('Panel', 'Proccessed', '%', $labname, 'Referenced', 'Rejected', 'Rerun Rate');
	tr_pdd_header('text/csv', $filename);
	if ($filepath_save_csv != '') {
		$f = fopen($filepath_save_csv, 'w');
		$pdf_out_file = basename($filename);
	} else {
		$f = fopen('php://output', 'w');
	}
	$clinic_filter = '';
	if ($cli_ID == 0) {
		$clinic_filter = 'All Clinics';
	} else if ($cli_ID == -1) {
		$clinic_filter = 'All Clinics - Separate';
	} else {
		$clinic_filter = $clinics[$cli_ID]->cli_clinic;
	}

	fputcsv($f, ["Processed Samples Report", "", "", "", "", "", ""]);
	fputcsv($f, [$lab_name]);
	fputcsv($f, ["Generated on: $generated_on"]);
	fputcsv($f, ["Report Parameters"]);
	fputcsv($f, ["Clinic:", $clinic_filter]);
	fputcsv($f, ["Count type:", isset($date_types[$date_type]) ? $date_types[$date_type] : 'DOC']);
	fputcsv($f, ["Test Type:", $btype_selected]);
	fputcsv($f, ["Time Period:", $from_date . " to " .  $to_date]);
	fputcsv($f, [""]);

	fputcsv($f, $headers);
	$total_processed = 0;
	foreach ($reports as $row) {
		$total_processed += $row['processed'];
	}
	if ($cli_ID == -1) {
		foreach ($report_clids as $cid) {
			$cli = isset($clinics[$cid]) ? $clinics[$cid] : $inactive_clinics[$cid];

			fputcsv($f, ["", "", "", $cli->cli_clinic, "", "", "", ""]);
			foreach ($reports as $gid => $row) {
				unset($row['refs']);
				list($panelid, $rcliid) = explode('_', $gid);
				if ($rcliid == $cid) {
					$row['percent'] = $total_processed > 0 ? number_format(($row['processed'] / $total_processed) * 100, 1) . '%' : '0%';
					$output_row = array(
						$row['panel'],
						$row['processed'],
						$row['percent'],
						$row['main'],
						$row['ref'],
						$row['rejected'],
						$row['rerun']
					);
					fputcsv($f, $output_row);
				}
			}
			fputcsv($f, array("", "", "", "", "", "", ""));
		}
	} else {
		foreach ($reports as $row) {
			unset($row['refs']);
			$row['percent'] = $total_processed > 0 ? number_format(($row['processed'] / $total_processed) * 100, 1) . '%' : '0%';
			$output_row = array(
				$row['panel'],
				$row['processed'],
				$row['percent'],
				$row['main'],
				$row['ref'],
				$row['rejected'],
				$row['rerun']
			);
			fputcsv($f, $output_row);
		}
	}

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

	tr.clinic_status_0 .clinic_name {
		color: red;
	}

	.font-medium {
		font-weight: 550;
	}
</style>
<?php $schedule_report->style(); ?>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?' . TRCPDD_VS ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<div id="root" class="wrap">
	<div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
		<div class="space-y-6">
			<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
				<div>
					<h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">
						Processed Samples</h1>
					<p class="text-muted-foreground mt-1">Processing results by panel and clinic</p>
				</div>
			</div>
			<?php
			$total_top = array(
				'processed' => 0,
				'main' => 0,
				'rejected' => 0,
				'rejected_percent' => 0,
			);
			foreach ($reports as $row) {

				foreach ($total_top as $k => $vl) {
					if ($k == 'rejected_percent') continue;
					$total_top[$k] += $row[$k];
				}

				$total_top['rejected_percent'] = $total_top['processed'] > 0 ? $total_top['rejected'] / $total_top['processed'] * 100 : 0;
				$total_top['rejected_percent'] = number_format($total_top['rejected_percent'], 2) . '%';
			}
			?>
			<div>
				<div class="space-y-5">
					<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
						<div data-slot="card"
							class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
							<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
								<div class="flex items-center justify-between">
									<div>
										<p class="text-sm text-muted-foreground">Processed</p>
										<p class="text-2xl font-bold"><?php echo $total_top['processed'] ?></p>
									</div>
									<div class="p-2.5 bg-blue-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
											width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
											stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
											class="lucide lucide-chart-column h-4 w-4 text-blue-600" aria-hidden="true">
											<path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
											<path d="M18 17V9"></path>
											<path d="M13 17V5"></path>
											<path d="M8 17v-3"></path>
										</svg></div>
								</div>
							</div>
						</div>
						<div data-slot="card"
							class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
							<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
								<div class="flex items-center justify-between">
									<div>
										<p class="text-sm text-muted-foreground"><?php echo $labname ?></p>
										<p class="text-2xl font-bold"><?php echo $total_top['main']; ?></p>
									</div>
									<div class="p-2.5 bg-green-50 rounded-lg">
										<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flask-conical-icon lucide-flask-conical h-4 w-4 text-green-600">
											<path d="M14 2v6a2 2 0 0 0 .245.96l5.51 10.08A2 2 0 0 1 18 22H6a2 2 0 0 1-1.755-2.96l5.51-10.08A2 2 0 0 0 10 8V2" />
											<path d="M6.453 15h11.094" />
											<path d="M8.5 2h7" />
										</svg>
									</div>
								</div>
							</div>
						</div>
						<div data-slot="card"
							class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
							<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
								<div class="flex items-center justify-between">
									<div>
										<p class="text-sm text-muted-foreground">Rejected</p>
										<p class="text-2xl font-bold"><?php echo $total_top['rejected']; ?></p>
									</div>
									<div class="p-2.5 bg-red-50 rounded-lg">
										<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-x-icon lucide-file-x h-4 w-4 text-red-600">
											<path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z" />
											<path d="M14 2v5a1 1 0 0 0 1 1h5" />
											<path d="m14.5 12.5-5 5" />
											<path d="m9.5 12.5 5 5" />
										</svg>
									</div>
								</div>
							</div>
						</div>
						<div data-slot="card"
							class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
							<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
								<div class="flex items-center justify-between">
									<div>
										<p class="text-sm text-muted-foreground">Rejection Rate</p>
										<p class="text-2xl font-bold">
											<?php echo $total_top['rejected_percent']; ?>
										</p>
									</div>
									<div class="p-2.5 bg-orange-50 rounded-lg">
										<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-percent-icon lucide-percent h-4 w-4 text-orange-600">
											<line x1="19" x2="5" y1="5" y2="19" />
											<circle cx="6.5" cy="6.5" r="2.5" />
											<circle cx="17.5" cy="17.5" r="2.5" />
										</svg>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div data-slot="card"
						class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
						<div data-slot="card-header"
							class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 pb-3">
							<div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
								<div>
									<h4 data-slot="card-title" class="leading-none flex items-center gap-2">
										<div class="p-1.5 bg-blue-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
												width="24" height="24" viewBox="0 0 24 24" fill="none"
												stroke="currentColor" stroke-width="2" stroke-linecap="round"
												stroke-linejoin="round"
												class="lucide lucide-chart-column h-4 w-4 text-blue-600"
												aria-hidden="true">
												<path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
												<path d="M18 17V9"></path>
												<path d="M13 17V5"></path>
												<path d="M8 17v-3"></path>
											</svg></div>Specimen Processing by Panel<span
											class="text-sm text-muted-foreground ml-2">(<?php echo $text_sel_date_rep; ?>)</span>
									</h4>
								</div>
								<div class="flex flex-wrap items-center gap-3">

									<button export_func="samples_report" export_type="pdf"
										data-slot="dropdown-menu-trigger"
										class="export_item cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 text-muted-foreground hover:text-primary hover:bg-transparent"
										type="button" id="radix-:rf9:" aria-haspopup="menu" aria-expanded="false"
										data-state="closed"><svg xmlns="http://www.w3.org/2000/svg" width="24"
											height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
											stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
											class="lucide lucide-download h-4 w-4 mr-1" aria-hidden="true">
											<path d="M12 15V3"></path>
											<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
											<path d="m7 10 5 5 5-5"></path>
										</svg>Export<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
											viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
											stroke-linecap="round" stroke-linejoin="round"
											class="lucide lucide-chevron-down h-3 w-3 ml-1" aria-hidden="true">
											<path d="m6 9 6 6 6-6"></path>
										</svg>
									</button>
									<?php $schedule_report->btn_schedule(); ?>
								</div>
							</div>
							<div class="flex flex-wrap items-center gap-3 mt-4">

								<select id="month_range" name="month_range">
									<?php foreach ($filter_date_select as $item): ?>
										<option <?php if ($item['value'] == $month_range) { ?>selected<?php } ?>
											value="<?php echo $item['value']; ?>">
											<?php echo $item['text']; ?>
										</option>
									<?php endforeach; ?>
								</select>

								<select id="sel_datetype" name="datetype">
									<?php
									foreach ($date_types as $k => $txt) {
										$selected = $k == $date_type ? 'selected' : '';
										echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
									}
									?>
								</select>

								<select id="sel_clinic_rep" name="sel_clinic_rep">
									<option value="0">All Clinics</option>
									<option value="-1" <?php echo $cli_ID == -1 ? "selected" : ''; ?>>All Clinics - Separate</option>
									<?php foreach ($clinics_rs as $item): ?>
										<option value="<?php echo $item->cID; ?>" <?php if ($cli_ID == $item->cID) { ?>selected<?php } ?>>
											<?php echo $item->cli_clinic; ?>
										</option>
									<?php endforeach; ?>
								</select>

								<?php if (count($ci_all_btypes) > 1) { ?>
									<select name="btype" id="sel_btype" required>
										<?php
										foreach ($ci_all_btypes as $bt) {
											$selected = $btype_selected == $bt ? 'selected' : '';
											echo '<option value="' . $bt . '" ' . $selected . '>' . tr_pdd_get_typename($bt) . '</option>';
										}
										?>
									</select>
								<?php } ?>

								<button id="btn_day_customer" data-slot="popover-trigger"
									class="items-center justify-center whitespace-nowrap font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 rounded-md gap-1.5 has-[&gt;svg]:px-2.5 h-8 px-2 text-sm inline-flex"
									title="Select custom date range" type="button" style="">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
										fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
										stroke-linejoin="round" class="lucide lucide-calendar h-3 w-3 mr-1"
										aria-hidden="true">
										<path d="M8 2v4"></path>
										<path d="M16 2v4"></path>
										<rect width="18" height="18" x="3" y="4" rx="2"></rect>
										<path d="M3 10h18"></path>
									</svg>
									<span class="txt_show_customer_day" data-slot="select-value"
										style="pointer-events:none"><?php echo date_i18n('M j', strtotime($from_date)) . " - " . date_i18n('M j', strtotime($to_date)); ?></span>
								</button>
							</div>
						</div>
						<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-6">
							<div class="border rounded-lg overflow-hidden">
								<div class="overflow-x-auto">
									<table class="w-full text-sm">
										<thead class="bg-gray-100 border-b">
											<tr>
												<th data-col="0" class="px-4 py-3 text-left font-medium text-gray-800 cursor-pointer select-none hover:bg-gray-200 transition" onclick="sortProcessedTable(this)">
													Panel
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up-down h-3 w-3 ml-1 inline opacity-40">
														<path d="m21 16-4 4-4-4"></path>
														<path d="M17 20V4"></path>
														<path d="m3 8 4-4 4 4"></path>
														<path d="M7 4v16"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up h-3 w-3 ml-1" style="display:none">
														<path d="m5 12 7-7 7 7"></path>
														<path d="M12 19V5"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-down h-3 w-3 ml-1" style="display:none">
														<path d="M12 5v14"></path>
														<path d="m19 12-7 7-7-7"></path>
													</svg>
												</th>
												<th data-col="1" class="px-4 py-3 text-center font-medium text-gray-800 cursor-pointer select-none hover:bg-gray-200 transition" onclick="sortProcessedTable(this)">
													Processed
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up-down h-3 w-3 ml-1 inline opacity-40">
														<path d="m21 16-4 4-4-4"></path>
														<path d="M17 20V4"></path>
														<path d="m3 8 4-4 4 4"></path>
														<path d="M7 4v16"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up h-3 w-3 ml-1" style="display:none">
														<path d="m5 12 7-7 7 7"></path>
														<path d="M12 19V5"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-down h-3 w-3 ml-1" style="display:none">
														<path d="M12 5v14"></path>
														<path d="m19 12-7 7-7-7"></path>
													</svg>
												</th>
												<th data-col="2" class="px-4 py-3 text-center font-medium text-gray-800 cursor-pointer select-none hover:bg-gray-200 transition" onclick="sortProcessedTable(this)">
													%
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up-down h-3 w-3 ml-1 inline opacity-40">
														<path d="m21 16-4 4-4-4"></path>
														<path d="M17 20V4"></path>
														<path d="m3 8 4-4 4 4"></path>
														<path d="M7 4v16"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up h-3 w-3 ml-1" style="display:none">
														<path d="m5 12 7-7 7 7"></path>
														<path d="M12 19V5"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-down h-3 w-3 ml-1" style="display:none">
														<path d="M12 5v14"></path>
														<path d="m19 12-7 7-7-7"></path>
													</svg>
												</th>
												<th data-col="3" class="px-4 py-3 text-center font-medium text-gray-800 cursor-pointer select-none hover:bg-gray-200 transition" onclick="sortProcessedTable(this)">
													<?php echo $labname ?>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up-down h-3 w-3 ml-1 inline opacity-40">
														<path d="m21 16-4 4-4-4"></path>
														<path d="M17 20V4"></path>
														<path d="m3 8 4-4 4 4"></path>
														<path d="M7 4v16"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up h-3 w-3 ml-1" style="display:none">
														<path d="m5 12 7-7 7 7"></path>
														<path d="M12 19V5"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-down h-3 w-3 ml-1" style="display:none">
														<path d="M12 5v14"></path>
														<path d="m19 12-7 7-7-7"></path>
													</svg>
												</th>
												<th data-col="4" class="px-4 py-3 text-center font-medium text-gray-800 cursor-pointer select-none hover:bg-gray-200 transition" onclick="sortProcessedTable(this)">
													Referenced
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up-down h-3 w-3 ml-1 inline opacity-40">
														<path d="m21 16-4 4-4-4"></path>
														<path d="M17 20V4"></path>
														<path d="m3 8 4-4 4 4"></path>
														<path d="M7 4v16"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up h-3 w-3 ml-1" style="display:none">
														<path d="m5 12 7-7 7 7"></path>
														<path d="M12 19V5"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-down h-3 w-3 ml-1" style="display:none">
														<path d="M12 5v14"></path>
														<path d="m19 12-7 7-7-7"></path>
													</svg>
												</th>
												<th data-col="5" class="px-4 py-3 text-center font-medium text-gray-800 cursor-pointer select-none hover:bg-gray-200 transition" onclick="sortProcessedTable(this)">
													Rejected
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up-down h-3 w-3 ml-1 inline opacity-40">
														<path d="m21 16-4 4-4-4"></path>
														<path d="M17 20V4"></path>
														<path d="m3 8 4-4 4 4"></path>
														<path d="M7 4v16"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up h-3 w-3 ml-1" style="display:none">
														<path d="m5 12 7-7 7 7"></path>
														<path d="M12 19V5"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-down h-3 w-3 ml-1" style="display:none">
														<path d="M12 5v14"></path>
														<path d="m19 12-7 7-7-7"></path>
													</svg>
												</th>
												<th data-col="6" class="px-4 py-3 text-center font-medium text-gray-800 cursor-pointer select-none hover:bg-gray-200 transition" onclick="sortProcessedTable(this)">
													Rejected %
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up-down h-3 w-3 ml-1 inline opacity-40">
														% values
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up h-3 w-3 ml-1" style="display:none">
														<path d="m5 12 7-7 7 7"></path>
														<path d="M12 19V5"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-down h-3 w-3 ml-1" style="display:none">
														<path d="M12 5v14"></path>
														<path d="m19 12-7 7-7-7"></path>
													</svg>
												</th>
												<th data-col="7" class="px-4 py-3 text-center font-medium text-gray-800 cursor-pointer select-none hover:bg-gray-200 transition" onclick="sortProcessedTable(this)">
													Rerun Rate
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up-down h-3 w-3 ml-1 inline opacity-40">
														<path d="m21 16-4 4-4-4"></path>
														<path d="M17 20V4"></path>
														<path d="m3 8 4-4 4 4"></path>
														<path d="M7 4v16"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up h-3 w-3 ml-1" style="display:none">
														<path d="m5 12 7-7 7 7"></path>
														<path d="M12 19V5"></path>
													</svg>
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-down h-3 w-3 ml-1" style="display:none">
														<path d="M12 5v14"></path>
														<path d="m19 12-7 7-7-7"></path>
													</svg>
												</th>
											</tr>
										</thead>

										<tbody class="divide-y divide-gray-100">
											<?php
											$grand_total = 0;
											foreach ($reports as $row) {
												$grand_total += $row['processed'];
											}

											if ($cli_ID == -1) {
												foreach ($report_clids as $cid) {
													$cli = isset($clinics[$cid]) ? $clinics[$cid] : $inactive_clinics[$cid];
													if (!$cli) {
														$cli = new stdClass();
														$cli->cli_clinic = $wpdb->get_var("select requesting_practice from {$batch_tbl} where cli_ID = {$cid} limit 1");
														$cli->cli_status = 0;
														$cli->cli_clinic .= ' - Deleted';
														if (!empty($cli->cli_clinic)) {
															$inactive_clinics[$cid] = $cli;
														}
													}
													$trrows = '';
													$totals = array(
														'processed' => 0,
														'main' => 0,
														'ref' => 0,
														'rejected' => 0,
														'rejected_percent' => 0,
														'rerun' => 0,
													);

													foreach ($reports as $gid => $row) {
														list($panelid, $rcliid) = explode('_', $gid);

														if ($rcliid === $cid) {
															$row['rejected_percent'] = $row['processed'] > 0 ? $row['rejected'] / $row['processed'] * 100 : 0;
															$row['percent_of_total'] = $grand_total > 0 ? ($row['processed'] / $grand_total) * 100 : 0;

															foreach ($totals as $k => $vl) {
																$totals[$k] += $row[$k];
															}
															$ref_by_labs = array();
															foreach ($row['refs'] as $lid => $vl) {
																$ref_by_labs[] = $vl . $all_labs[$lid]->lab_dba_init;
															}
															$trrows .= '<tr class="hover:bg-gray-50 bg-gray-50 processed-detail-row"'
																. ' data-subgroup="' . htmlspecialchars($cid) . '"'
																. ' data-panel="' . htmlspecialchars($row['panel']) . '"'
																. ' data-processed="' . intval($row['processed']) . '"'
																. ' data-percent="' . $row['percent_of_total'] . '"'
																. ' data-main="' . intval($row['main']) . '"'
																. ' data-ref="' . intval($row['ref']) . '"'
																. ' data-rejected="' . intval($row['rejected']) . '"'
																. ' data-rejected-pct="' . ($row['processed'] > 0 ? $row['rejected'] / $row['processed'] * 100 : 0) . '"'
																. ' data-rerun="' . intval($row['rerun']) . '"'
																. '>';
															$trrows .= '<td class="px-4 py-3 font-medium text-gray-900">' . $row['panel'] . '</td>';
															$trrows .= '<td class="px-4 py-3 text-center font-medium text-gray-900">' . $row['processed'] . '</td>';
															$trrows .= '<td class="px-4 py-3 text-center text-blue-600 font-medium">' . number_format($row['percent_of_total'], 1) . '%</td>';
															$trrows .= '<td class="px-4 py-3 text-center text-green-600 font-medium">' . $row['main'] . '</td>';
															$trrows .= '<td class="px-4 py-3 text-center text-gray-600">' . $row['ref'] . implode(' &nbsp;&nbsp;', $ref_by_labs) . '</td>';
															$trrows .= '<td class="px-4 py-3 text-center text-red-600 font-medium">' . $row['rejected'] . '</td>';
															$trrows .= '<td class="px-4 py-3 text-center text-orange-600 font-medium">' . number_format($row['rejected_percent'], 2) . '%' . '</td>';
															$trrows .= '<td  class="px-4 py-3 text-center text-orange-600 font-medium">' . $row['rerun'] . '</td>';
															$trrows .= '</tr>';
														}
													}

													echo '<tr class="hover:bg-gray-50 bg-gray-50 clinic_status_' . $cli->cli_status . ' processed-summary-row"'
														. ' data-subgroup="' . htmlspecialchars($cid) . '"'
														. '>'
														. '<th class="clinic_name">' . $cli->cli_clinic . '</th>';
													$totals['rejected_percent'] = $totals['processed'] > 0 ? $totals['rejected'] / $totals['processed'] * 100 : 0;
													$totals['rejected_percent'] = number_format($totals['rejected_percent'], 2) . '%';

													echo '<td class="px-4 py-3 text-center font-medium text-gray-900">' . $totals['processed'] . '</td>';
													echo '<td class="px-4 py-3 text-center text-blue-600 font-medium"></td>';
													echo '<td class="px-4 py-3 text-center font-medium text-gray-900">' . $totals['main'] . '</td>';
													echo '<td class="px-4 py-3 text-center font-medium text-gray-900">' . $totals['ref'] . '</td>';
													echo '<td class="px-4 py-3 text-center font-medium text-gray-900">' . $totals['rejected'] . '</td>';
													echo '<td class="px-4 py-3 text-center font-medium text-gray-900">' . $totals['rejected_percent'] . '</td>';
													echo '<td class="px-4 py-3 text-center font-medium text-gray-900">' . $totals['rerun'] . '</td>';
													echo '</tr>';

													echo $trrows;
												}
											} else {
												$trrows = '';
												$totals = array(
													'processed' => 0,
													'main' => 0,
													'ref' => 0,
													'rejected' => 0,
													'rejected_percent' => 0,
													'rerun' => 0,
												);
												$totalrefs = array();
												foreach ($reports as $row) {
													$row['rejected_percent'] = $row['processed'] > 0 ? $row['rejected'] / $row['processed'] * 100 : 0;
													$row['percent_of_total'] = $grand_total > 0 ? ($row['processed'] / $grand_total) * 100 : 0;

													foreach ($totals as $k => $vl) {
														$totals[$k] += $row[$k];
													}

													$ref_by_labs = array();
													foreach ($row['refs'] as $lid => $vl) {
														$ref_by_labs[] = $vl . $all_labs[$lid]->lab_dba_init;
														$totalrefs[$lid] += $vl;
													}
													if (empty($ref_by_labs)) {
														$ref_by_labs[] = 0;
													}

													$trrows .= '<tr class="childp hover:bg-gray-50 processed-detail-row"'
														. ' data-subgroup="0"'
														. ' data-panel="' . htmlspecialchars($row['panel']) . '"'
														. ' data-processed="' . intval($row['processed']) . '"'
														. ' data-percent="' . $row['percent_of_total'] . '"'
														. ' data-main="' . intval($row['main']) . '"'
														. ' data-ref="' . intval($row['ref']) . '"'
														. ' data-rejected="' . intval($row['rejected']) . '"'
														. ' data-rejected-pct="' . ($row['processed'] > 0 ? $row['rejected'] / $row['processed'] * 100 : 0) . '"'
														. ' data-rerun="' . intval($row['rerun']) . '"'
														. '>';
													$trrows .= '<td class="px-4 py-3 font-medium text-gray-900">' . $row['panel'] . '</td>';
													$trrows .= '<td class="px-4 py-3 text-center font-medium text-gray-900">' . $row['processed'] . '</td>';
													$trrows .= '<td class="px-4 py-3 text-center text-blue-600 font-medium">' . number_format($row['percent_of_total'], 1) . '%</td>';
													$trrows .= '<td class="px-4 py-3 text-center text-green-600 font-medium">' . $row['main'] . '</td>';
													$trrows .= '<td class="px-4 py-3 text-center text-gray-600">' . implode(' &nbsp;&nbsp; ', $ref_by_labs) . '</td>';
													$trrows .= '<td class="px-4 py-3 text-center text-red-600 font-medium">' . $row['rejected'] . '</td>';
													$trrows .= '<td class="px-4 py-3 text-center text-orange-600 font-medium">' . number_format($row['rejected_percent'], 2) . '%' . '</td>';
													$trrows .= '<td class="px-4 py-3 text-center text-gray-600">' . $row['rerun'] . '</td>';
													$trrows .= '</tr>';
												}

												echo '<tr class="childp hover:bg-gray-50 bg-gray-50 processed-summary-row"'
													. ' data-subgroup="0"'
													. '>'
													. '<th class="px-4 py-3 text-left font-medium text-gray-800">' . ($cli_ID > 0 ? $clinicname : 'All Clinics') . '</th>';

												$totals['rejected_percent'] = $totals['processed'] > 0 ? $totals['rejected'] / $totals['processed'] * 100 : 0;
												$totals['rejected_percent'] = number_format($totals['rejected_percent'], 2) . '%';

												echo '<td class="px-4 py-3 text-center font-medium text-gray-900">' . $totals['processed'] . '</td>';
												echo '<td class="px-4 py-3 text-center text-blue-600 font-medium"></td>';
												echo '<td class="px-4 py-3 text-center font-medium text-gray-900">' . $totals['main'] . '</td>';

												$ref_by_labs = array();
												foreach ($totalrefs as $lid => $vl) {
													$ref_by_labs[] = $vl . $all_labs[$lid]->lab_dba_init;
												}
												echo '<td class="px-4 py-3 text-center font-medium text-gray-900">' . implode(' &nbsp;&nbsp; ', $ref_by_labs) . '</td>';
												echo '<td class="px-4 py-3 text-center font-medium text-gray-900">' . $totals['rejected'] . '</td>';
												echo '<td class="px-4 py-3 text-center font-medium text-gray-900">' . $totals['rejected_percent'] . '</td>';
												echo '<td class="px-4 py-3 text-center font-medium text-gray-900">' . $totals['rerun'] . '</td>';
												echo '</tr>';

												echo $trrows;
											}

											?>
										</tbody>
									</table>
								</div>
							</div>
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
</div>

<script>
	var month_range = '<?php echo $month_range; ?>';
	var from_date = '<?php echo $from_date; ?>';
	var to_date = '<?php echo $to_date; ?>';
	var cli_ID = '<?php echo $cli_ID; ?>';
	var btype = '<?php echo $btype_selected; ?>';
	var date_type = '<?php echo $date_type; ?>';
	var url_main_page = 'ci_reports_processed_samples';

	function goto_url() {
		var $url = location.href;

		$url = $url.replace(/&month_range=[^&]*/g, '');
		$url = $url.replace(/&from=[^&]*/g, '');
		$url = $url.replace(/&to=[^&]*/g, '');
		$url = $url.replace(/&cli_ID=[^&]*/g, '');
		$url = $url.replace(/&btype=[^&]*/g, '');
		$url = $url.replace(/&datetype=[^&]*/g, '');

		// Thêm các param mới
		$url += '&month_range=' + month_range;

		if (month_range === 'custom') {
			$url += '&from=' + from_date;
			$url += '&to=' + to_date;
		}

		$url += '&cli_ID=' + cli_ID;
		$url += '&btype=' + btype;
		$url += '&datetype=' + date_type;

		window.location.href = $url;
	}

	function sortProcessedTable(thEl) {
		const $th = $(thEl);
		const $table = $th.closest('table');
		const colIndex = parseInt($th.data('col'));
		const isAsc = !$th.data('asc');

		// Reset all icons
		$table.find('thead th .lucide-arrow-up-down').show().css('opacity', '0.4').addClass('inline');
		$table.find('thead th .lucide-arrow-up, thead th .lucide-arrow-down').removeClass('inline').hide();
		$th.find('.lucide-arrow-up-down').removeClass('inline').hide();

		// Show the active icon
		if (isAsc) {
			$th.find('.lucide-arrow-up').show().addClass('inline');
		} else {
			$th.find('.lucide-arrow-down').show().addClass('inline');
		}

		// Group detail rows by subgroup
		const subgroups = {};
		$table.find('tbody tr.processed-detail-row').each(function() {
			const sg = $(this).data('subgroup');
			if (!subgroups[sg]) subgroups[sg] = [];
			subgroups[sg].push($(this));
		});

		Object.keys(subgroups).forEach(sg => {
			const rows = subgroups[sg];

			rows.sort(function(a, b) {
				let valA, valB;

				switch (colIndex) {
					case 0: // Panel (text)
						valA = a.data('panel').toString().toLowerCase();
						valB = b.data('panel').toString().toLowerCase();
						break;
					case 1: // Processed
						valA = parseInt(a.data('processed')) || 0;
						valB = parseInt(b.data('processed')) || 0;
						break;
					case 2: // % (percent of total)
						valA = parseFloat(a.data('percent')) || 0;
						valB = parseFloat(b.data('percent')) || 0;
						break;
					case 3: // Lab (main)
						valA = parseInt(a.data('main')) || 0;
						valB = parseInt(b.data('main')) || 0;
						break;
					case 4: // Referenced
						valA = parseInt(a.data('ref')) || 0;
						valB = parseInt(b.data('ref')) || 0;
						break;
					case 5: // Rejected
						valA = parseInt(a.data('rejected')) || 0;
						valB = parseInt(b.data('rejected')) || 0;
						break;
					case 6: // Rejected %
						valA = parseFloat(a.data('rejected-pct')) || 0;
						valB = parseFloat(b.data('rejected-pct')) || 0;
						break;
					case 7: // Rerun
						valA = parseInt(a.data('rerun')) || 0;
						valB = parseInt(b.data('rerun')) || 0;
						break;
				}

				if (typeof valA === 'string') {
					return isAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
				}
				return isAsc ? (valA - valB) : (valB - valA);
			});

			// Re-insert sorted detail rows after their summary row
			const $summary = $table.find(`tr.processed-summary-row[data-subgroup="${sg}"]`);
			if ($summary.length) {
				let $insertAfter = $summary;
				rows.forEach(row => {
					$insertAfter.after(row);
					$insertAfter = row;
				});
			} else {
				// fallback: just append to tbody
				rows.forEach(row => {
					$table.find('tbody').append(row);
				});
			}
		});

		$th.data('asc', isAsc);
	}

	jQuery(function($) {
		window.$ = jQuery;


		$('.processed-table thead th[data-col]').on('click', function() {
			sortProcessedTable(this);
		});


		if (month_range != "custom") $("#btn_day_customer").removeClass("inline-flex").hide();

		update_ui_select();

		$("#month_range").change(function() {
			month_range = $(this).val();
			if (month_range == "custom") {
				$("#btn_day_customer").addClass("inline-flex").show();
				$("#btn_day_customer").data('daterangepicker').show();
			} else {
				$("#btn_day_customer").removeClass("inline-flex").hide();
				goto_url();
			}
		});

		$(".btn-export").on("click", function(e) {
			e.stopPropagation();
			var id_btn = $(this).attr("id");
			$("#menu_" + id_btn).toggle();
		});

		$(document).on("click", function(e) {
			if (!$(e.target).closest(".custom-select").length) {
				$(".custom-select .options").hide();
			}

			if (!$(e.target).closest(".menu-export").length) {
				$(".menu-export").hide();
			}
		});

		$(".export_item").click(async function() {
			var export_func = $(this).attr("export_func");
			var export_type = $(this).attr("export_type");

			var $url = location.href + '&trajax=export_report&export_type=' + export_type;
			$url += '&export_func=' + export_func;
			$url += '&month_range=' + month_range;
			$url += '&from=' + from_date;
			$url += '&to=' + to_date;
			$url += '&cli_ID=' + cli_ID;
			$url += '&btype=' + btype;
			$url += '&datetype=' + date_type;
			showToast('info', 'Preparing your download...', 'Generating...', 4000);
			try {
				const response = await fetch($url);

				// 🔥 Lấy filename từ header
				const disposition = response.headers.get("Content-Disposition");
				let filename = "download." + export_type;

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

		$('#btn_day_customer').daterangepicker({
				opens: 'right'
			},
			function(start, end, label) {
				$('.txt_show_customer_day').html(start.format('MMM D') + ' - ' + end.format('MMM D'));
				from_date = start.format('YYYY-MM-DD');
				to_date = end.format('YYYY-MM-DD');
				goto_url();
			}
		);

		$("#sel_clinic_rep").change(function() {
			cli_ID = $(this).val();
			goto_url();
		});

		$("#sel_btype").change(function() {
			btype = $(this).val();
			goto_url();
		});

		$("#sel_datetype").change(function() {
			date_type = $(this).val();
			goto_url();
		});

	});
</script>
<?php $schedule_report->js(); ?>
