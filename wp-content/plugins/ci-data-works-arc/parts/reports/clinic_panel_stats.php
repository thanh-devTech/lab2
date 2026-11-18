<?php
global $wpdb, $current_role;

$schedule_report 	= new CI_Report_Schedule('clinic_panel_stats', 'Clinic Panel Stats');
include_once(TRCPDD_PATH . 'pages/formdata.php');

$from_date = !empty($_REQUEST['from']) ? $_REQUEST['from'] : date_i18n('Y-m-d');
$to_date = !empty($_REQUEST['to']) ? $_REQUEST['to'] : $from_date;
$cli_ID = isset($_REQUEST['cli_ID']) ? $_REQUEST['cli_ID'] : 0;
$panel_ID = isset($_REQUEST['panel_ID']) ? $_REQUEST['panel_ID'] : 0;
$summary = isset($_REQUEST['summary']) && $_REQUEST['summary'];
$show_notdetected = isset($_REQUEST['notdetected']) && $_REQUEST['notdetected'];
$btype_selected = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'pcr';

$trajax         	= isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : '';

$export_func 		= isset($_REQUEST['export_func']) ? $_REQUEST['export_func'] : '';
$export_type 		= isset($_REQUEST['export_type']) ? $_REQUEST['export_type'] : '';

if ($cli_ID == '') $cli_ID = 0;

$user_id = get_current_user_id();
$adding_tests_markers = array();
$clinics = array();

$pos_neg_arr = array(
	'NEG' => 'NEG',
	'POS' => 'POS',
	'PEN' => 'Pending'
);


if ($btype == 'pcr') {
	$pos_neg_arr = array(
		'' => '',
		'NEG' => 'Not Detected',
		'POS' => 'Detected',
		'POL' => 'Detected-Low',
		'POM' => 'Detected-Medium',
		'POH' => 'Detected-High',
		'PEN' => 'Pending',
		'INC' => 'Inconclusive',
		'INV' => 'Invalid',
		'IND' => 'Indeterminate',
		'CAN' => 'Test Cancelled',
		'REJ' => 'Rejected'
	);
} else if ($btype_selected == 'blood') {

	$pos_neg_arr = array(
		'' => '',
		'NEG' => 'Not Detected',
		'POS' => 'Detected',
		'POL' => 'Detected-Low',
		'POM' => 'Detected-Medium',
		'POH' => 'Detected-High',
		'PEN' => 'Pending',
		'INC' => 'Inconclusive',
		'INV' => 'Invalid',
		'IND' => 'Indeterminate',
		'CAN' => 'Test Cancelled',
		'REJ' => 'Rejected'
	);
}



switch ($current_role) {
	case 'lab_rep':
	case 'lab_rep_w2':

		$rep_id = get_user_meta($user_id, '_rep_id', true);
		$rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);


		$clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
                                        where cli.cli_status = 1 and `cli_rep-ID`={$rep_id} order by cli_clinic asc");


		break;
	case 'doctor':
		$doc_ID = get_user_meta($user_id, '_doc_id', true);

		$clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
		                                join ci_clinic_doc cd ON cli.cID = cd.cli_ID
                                    where cli.cli_status = 1 and cd.doc_ID = {$doc_ID}
                                     order by cli.cli_clinic asc");
		break;

	case 'collector':
		$col_id = get_user_meta($user_id, '_collector_id', true);
		$cids = $wpdb->get_col("select cli_ID from ci_clinic_collector where col_ID ={$col_id}");
		$cids = implode(',', $cids);
		$clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
                                    where cli.cli_status = 1 and cli.cID in ({$cids}) order by cli_clinic asc");
		break;

	case 'clinic':
		$cli_ID = get_user_meta($user_id, '_clinic_id', true);
		break;

	case 'office_manager':
	case 'multi_site_manager':
		$cid = get_user_meta($user_id, '_clinic_id', true);
		$cids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");

		$cids = implode(',', $cids);
		$clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
                                    where cli.cli_status = 1 and cli.cID in ({$cids}) order by cli_clinic asc");

		break;

	default:
		$clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic 
		                                    from ci_clinic cli
                                            where cli.cli_status = 1
                                            group by cli.cID
                                            order by cli_clinic asc");
		$show_all = true;
		break;
}


$batch_tbl = tr_pdd_get_table_by_btype($btype_selected);
$report_tbl = tr_pdd_get_table_report_by_btype($btype_selected);
$result_tbl = tr_pdd_get_table_result_by_btype($btype_selected);
$panel = array();
$submited = false;
$reports = array();
$tests = array();
$test_ids = array();
$test_ids_str = '';

if ($panel_ID > 0) {
	$panel = $wpdb->get_row("select * from ci_test_groups where tg_ID = {$panel_ID}", ARRAY_A);
	$tests = $wpdb->get_results("select * from ci_test_panels 
									where te_group_ID = {$panel_ID}
									or te_group_ID in (select tg_ID from ci_test_groups where tg_parent = {$panel_ID}) ", ARRAY_A);
	if ($panel['tg_parent'] == 0) {
		$marker_group_id = $wpdb->get_var("select tg_ID from ci_test_groups where tg_parent = {$panel_ID}
																and tg_name like '%antibiotic resistance%' ");
	}
	foreach ($tests as $t) {
		$test_ids[] = $t['te_ID'];
		if ($marker_group_id > 0 && $t['te_group_ID'] == $marker_group_id) {
			$adding_tests_markers[] = $t['te_ID'];
		}
	}

	$test_ids_str = implode(',', $test_ids);
}


if ((!empty($from_date) && ($panel_ID > 0 || $panel_ID == 'all')) || $export_func != "") {

	$where = " ba.doc >= '{$from_date}' ";
	if (!empty($to_date)) {
		$where .= " and ba.doc <= '{$to_date} 23:59:59' ";
	}
	if ($cli_ID > 0) {
		$where .= " and ba.cli_ID = {$cli_ID} ";
	}

	if ($panel_ID !== 'all') {
		$where .= " and FIND_IN_SET('GROUP_{$panel_ID}', ba.panel_tests_requested) ";
	}

	$where .= " and " . tr_pdd_where_batch_rejected(false, true);
	$where .= " and re.estatus = 'C' ";

	$sql_reports = "select ba.mid,
					ba.assen,
					ba.doc,
					ba.patient_name,
					ba.requesting_practice,
					ba.requesting_physician,
					ba.patient_id,
					ba.panel_tests_requested,
					re.id,
					re.instrument,
					re.created_date,
					re.covid_pos
					from {$batch_tbl} ba
					join {$report_tbl} re ON re.patient_id = ba.assen
					where {$where}
					order by ba.doc desc";
	$reports = $wpdb->get_results($sql_reports, ARRAY_A);

	if (isset($_GET['debug'])) {
		var_dump($sql_reports);
	}
	foreach ($reports as $i => $report) {
		$results = array();
		$screeningrows = array();

		if ($panel['tg_is_covid'] == 1 && $btype_selected == 'pcr') {
			$arr = trpdd_cal_ncovid19(array(), $report['id'], $report['instrument'], $report['created_date']);

			if ($show_notdetected || !in_array($arr['pos-neg'], array('NEG', ''))) {
				$results = array($arr);
			}
		} else {

			$groupids = array();
			foreach (explode(',', $report['panel_tests_requested']) as $tid) {
				if (stripos($tid, 'group_') !== false) {
					$groupids[] = str_ireplace('GROUP_', '', $tid);
				}
			}

			$orderby = "te_name asc";
			if (!$summary && $test_ID > 0) {
				$test_ids_str = $test_ID;
				if (!empty($adding_tests_markers)) {
					$test_ids_str .= ',' . implode(',', $adding_tests_markers);
					$orderby = " FIND_IN_SET(te.te_ID, '{$test_ID},{$test_ids_str}') ";
				}
			}

			$wheretests = '';
			if ($btype_selected == 'blood' && $test_ID == 0 && count($groupids) > 0) {
				$groupid_str = implode(',', $groupids);
				$wheretests .= " and te_group_id in ({$groupid_str})";
			}

			$sql_results = "select te.te_name as drug, 
									te.te_ID,
			                        te.te_group_ID,
			                        te.te_is_detected,
			                        te.te_is_hlm_detected,
			                        te.te_result_values,
									rs.report_id,
									rs.conc, 
									rs.`pos-neg`
									from {$result_tbl} rs
								join ci_test_panels te ON te.te_name = rs.drug or te.te_abrev = rs.drug
								where report_id = {$report['id']} and te.te_in_req = 1
								{$wheretests}
								 ";
			if ($panel_ID !== 'all') {
				$sql_results .= " and te_ID in ({$test_ids_str}) ";
			}
			$sql_results .= " group by te_ID";
			$sql_results .= " order by {$orderby} ";

			$rs = $wpdb->get_results($sql_results, ARRAY_A);

			if (isset($_GET['debug2'])) {
				var_dump($sql_results);
			}


			$hasdrugs = array();
			foreach ($rs as $j => $result) {
				if (empty($result['pos-neg'])) {
					if ($btype_selected == 'pcr') {
						$result = tr_pdd_parse_pcr_test($result, $report['instrument'], $result['te_group_ID'], $report['created_date']);
					} else if ($btype_selected == 'blood') {
						$result = tr_pdd_parse_blood_test($result, $report['instrument'], $result['te_group_ID'], $report);
						if (!empty($result['conc']) && empty($result['pos-neg'])) {
							$result['pos-neg'] = 'POS';
						}
					}
				}
				if (isset($hasdrugs[strtolower($result['drug'])])) {
					continue;
				}
				$hasdrugs[strtolower($result['drug'])] = true;

				$result['flag'] = tr_pdd_get_result_flag($result, $btype_selected);

				if (!$show_notdetected && in_array($result['pos-neg'], array('NEG', ''))) {
					continue;
				}
				$results[$j] = $result;
			}


			if ($btype_selected == 'tox') {
				//get screening
				$screenings = tr_pdd_get_result_validity(1, '', $report['mid']);

				foreach ($screenings as $arr) {
					$key = $arr['u_ID'];

					if ($arr['checked']) {
						$show_validity = true;
					} else {
						continue;
					}

					$arr['conc'] = trim($arr['conc']);
					$arr['conc'] = str_replace(' ', '', $arr['conc']);

					$arr['conc_num'] = $arr['conc'];

					$arr['conc_num'] = preg_replace("/[^0-9-.,]/", '', $arr['conc_num']);
					if (strpos($arr['conc'], '<') !== false) {
						$arr['conc_num'] -= 1;
					}

					if (
						!empty($arr['conc_num'])
						&& (
							($arr['conc_num'] < $arr['u_low'] && $arr['u_low'] > 0)
							|| (($arr['conc_num'] > $arr['u_high']
								|| $arr['conc'] == '>' . $arr['u_high']) && $arr['u_high'] > 0))
					) {
						$arr['outcome'] = $arr['u_neg_pos'] ? 'POS' : 'ABNORMAL';
					} else {
						$arr['outcome'] = $arr['u_neg_pos'] ? 'NEG' : 'NORMAL';
					}

					if ($arr['u_neg_pos']) {
						if (empty($arr['outcome']))
							$arr['outcome'] = 'NEG';

						if (!empty($arr['conc']) && $arr['conc'] < $arr['u_low'] && !empty($arr['u_low'])) {
							$arr['conc'] = 'Low';
						} else if (!empty($arr['conc']) && $arr['conc'] > $arr['u_high'] && $arr['u_high'] > 0) {
							$arr['conc'] = 'High';
						} else {
							//$arr['conc'] = '';
						}
					} else if (!empty($arr['conc']) && $arr['conc'] < $arr['u_low'] && !empty($arr['u_low'])) {
						$arr['conc'] = '<' . $arr['u_low'];
					} else if (!empty($arr['conc']) && $arr['conc'] > $arr['u_high'] && $arr['u_high'] > 0) {
						$arr['conc'] = '>' . $arr['u_high'];
					}


					$screeningrows[] = array(
						'drug' => $arr['u_name'],
						'conc' => $arr['conc'],
						'flag' => $arr['outcome'],

					);
				}
			}
		}
		if (empty($results)) {
			unset($reports[$i]);
		} else {
			$reports[$i]['results'] = $results;
			$reports[$i]['screening'] = $screeningrows;
		}
	}

	if ($summary) {

		if ($panel_ID === 'all') {
			$all_groups = array();
			$rspn = $wpdb->get_results("select tg_ID, tg_name from ci_test_groups where tg_type = '{$btype_selected}'");
			foreach ($rspn as $pn) {
				$all_groups[$pn->tg_ID] = $pn->tg_name;
			}


			$tmp_reports = $reports;
			$reports = array();
			foreach ($tmp_reports as $report) {
				$arr_grs = array();
				foreach ($report['results'] as $result) {
					$group_name = $all_groups[$result['te_group_ID']];

					if (!isset($arr_grs[$group_name])) {
						$arr_grs[$group_name] = false;
					}

					if ($result['pos-neg'] == 'POS') {
						$arr_grs[$group_name] = true;
					}
				}

				foreach ($arr_grs as $gr => $ispos) {
					$key = $gr . '_' . ($ispos ? 'POS' : 'NEG');
					if (!isset($reports[$key])) {
						$reports[$key] = 0;
					}
					$reports[$key]++;
				}
			}
		} else {
			$tmp_reports = $reports;
			$reports = array();
			foreach ($tmp_reports as $report) {
				foreach ($report['results'] as $result) {
					$key = $result['drug'] . '_' . $result['pos-neg'];

					if (!isset($reports[$key])) {
						$reports[$key] = 0;
					}

					$reports[$key]++;
				}
			}
		}
	}

	$submited = true;
}

$schedule_report->ajax();

if (isset($_GET['trajax']) || $export_func != "" || $filepath_save_csv != "") {

	$file_name = "clinic_panel_stats.csv";
	if ($summary) {
		$file_name = 'summary_clinic_panel_stats.csv';
	}
	if ($cli_ID > 0) {
		$file_name = str_replace('.csv', '_' . $cli_ID . '.csv', $file_name);
	}

	$f = null;
	if ($filepath_save_csv != '') {
		$f = fopen($filepath_save_csv, 'w');
		$csv_out_file = basename('Summary Clinic Panel Stats.csv');
	} else {
		tr_pdd_header('csv', $file_name);
	}
	$f = fopen('php://output', 'w');

	if ($summary) {
		$headers = array('Number of patients', 'Test Name', 'Result');
	} else {
		$headers = array('Accssion Number', 'DOC', 'Patient Name', 'Clinic Name', 'Doctor Name');
	}

	fputcsv($f, $headers);
	foreach ($reports as $i => $report) {
		if ($summary) {
			list($drug, $posneg) = explode('_', $i);
			$rowdata = array(
				$report,
				$drug,
				$pos_neg_arr[$posneg],
			);
			fputcsv($f, $rowdata);
		} else {
			$rowdata = array(
				$report['assen'],
				$report['doc'],
				$report['patient_name'],
				$report['requesting_practice'],
				$report['requesting_physician'],
			);
			fputcsv($f, $rowdata);

			$testnamecoltxt = $btype_selected == 'tox' ? 'Comfirmation Test Name' : 'Test Name';
			if (count($report['screening']) > 0) {
				$headers_2 = array('', 'Screening Test Name', 'Value', 'Result');
				fputcsv($f, $headers_2);
				foreach ($report['screening'] as $result) {
					$rowdata = array(
						'',
						$result['drug'],
						$result['conc'],
						$result['flag'],
					);
					fputcsv($f, $rowdata);
				}
				fputcsv($f, array(''));
			}

			if ($panel['tg_is_covid'] == 1) {
				$headers_2 = array('', $testnamecoltxt, 'Result');
			} else {
				$headers_2 = array('', $testnamecoltxt, 'Value', 'Result');
			}
			fputcsv($f, $headers_2);

			foreach ($report['results'] as $result) {
				if ($panel['tg_is_covid'] == 1) {
					$rowdata = array(
						'',
						$result['drug'],
						$result['flag']
					);
				} else {
					$rowdata = array(
						'',
						$result['drug'],
						$result['conc'],
						$result['flag'],
					);
				}
				fputcsv($f, $rowdata);
			}
		}
	}
	fclose($f);
	if ($filepath_save_csv == "") {
		$data_log = [
			'type' => 'Clinic Panel Stats Report',
			'file_name' => $file_name,
			'total_records' => count($reports),
			'from_date' => $from_date,
			'to_date' => $to_date,
		];
		trpdd_log_user(get_current_user_id(), 'download_report', $data_log, '', '', '', '', 'Report_' . date_i18n('mdY'));
		exit;
	}
}


if ($filepath_save_csv != '' || $filepath_save_pdf != '') {
	return;
}


$all_panels = array();
foreach (tr_pdd_get_batch_types() as $bt) {
	$all_panels[$bt] = $wpdb->get_results("select tg_ID, tg_name, tg_type from ci_test_groups 
								where tg_type = '{$bt}' and tg_show_req = 1
								order by tg_position asc", ARRAY_A);
}


?>
<style>
	table.resulttbl {
		width: 100%;
		margin: 0 auto;
	}

	table.resulttbl tr td {
		width: 33%;
	}

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
							</svg><span class="truncate max-w-[120px] sm:max-w-none">Clinic Panel Stats</span></span>
					</li>
				</ol>
			</nav>
		</div>
		<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
			<div>
				<h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">
					Clinic Panel Stats</h1>
				<p class="text-muted-foreground mt-1 ">Panel-specific statistics by clinic including rejection rates and
					turnaround times</p>
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
										</svg></div>Volume by Clinic Panel Stats
								</h4>
							</div>

							<div class="flex items-center gap-3">
								<div style="position:relative;display:inline-block;">

									<button id="export_report" ; data-slot="dropdown-menu-trigger"
										class="inline-flex export_item btn-export cursor-pointer items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive text-primary-foreground h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 bg-primary hover:bg-primary/90"
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
						<div class="flex flex-wrap items-center gap-3 mt-4">

							<form method="get" style="margin-bottom:20px" id="formsearch">
								<input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
								<input type="hidden" name="tab"
									value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />
								From: <input type="text" class="hasdate" name="from" id="date_from" style="width:100px"
									value="<?php echo @$from_date ?>" required />
								<label id="summarytodate">To:</label>
								<input type="text" class="hasdate today" name="to" id="date_to" style="width:100px"
									value="<?php echo @$to_date ?>" required />
								<select name="cli_ID" id="cli_ID" class="js_select2" style="max-width:270px">
									<option value="">All Clinics</option>
									<?php
									foreach ($clinics as $cli) {
										$selected = $cli->cID == $cli_ID ? 'selected' : '';
										echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
									}
									?>
								</select>

								<select name="btype" id="btype" required>
									<?php
									foreach (tr_pdd_get_batch_types() as $bt) {
										$selected = isset($_REQUEST['btype']) && $_REQUEST['btype'] == $bt ? 'selected' : '';
										echo '<option value="' . $bt . '" ' . $selected . '>' . tr_pdd_get_typename($bt) . '</option>';
									}
									?>
								</select>

								<select name="panel_ID" id="panel_ID" class="js_select2"
									data-value="<?php echo isset($_REQUEST['panel_ID']) ? $_REQUEST['panel_ID'] : '' ?>"
									data-placeholder="Select Panel" style="max-width:270px;width:200px"
									required></select>

								<label class="lcb"> <input id="check_summary" type="checkbox" name="summary" value="1" <?php checked($summary) ?> />Summary</label>
								<label class="lcb"> <input id="check_notdetected" type="checkbox" name="notdetected" value="1" <?php checked($show_notdetected) ?> />Show "Not-Detected"</label>
								<input type="submit" class="button-primary" value="Report" />
							</form>

						</div>
					</div>

					<?php
					if ($submited) {
					?>
						<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-6">
							<div class="border rounded-lg overflow-hidden">
								<div class="overflow-x-auto">
									<div class="wraptbl">
										<?php
										if (count($reports) == 0) {
											echo '<p class="center">Not found</p>';
										} else {
											if ($summary) {
										?>
												<table id="table_report"
													class="wp-list-table widefat striped posts blue_tbl hover_tbl smalltbl">
													<thead>
														<tr>
															<th>Number of Patients</th>
															<th>Test Name</th>
															<th>Test Result</th>
														</tr>
													</thead>
													<?php
													foreach ($reports as $i => $num) {
														list($drug, $posneg) = explode('_', $i);
													?>
														<tr>
															<td><?php echo $num ?></td>
															<td><?php echo $drug ?></td>
															<td><?php echo $pos_neg_arr[$posneg] ?></td>
														</tr>
													<?php
													}
													?>
												</table>

											<?php
											} else {
											?>
												<table id="table_report"
													class="wp-list-table widefat striped posts blue_tbl hover_tbl smalltbl">
													<thead>
														<tr>
															<th>Accession Number</th>
															<th>DOC</th>
															<th>Patient Name</th>
															<th>Clinic Name</th>
															<th>Doctor Name</th>
														</tr>
													</thead>
													<?php
													foreach ($reports as $report) {
													?>
														<tr>
															<td rowspan="2"><?php echo $report['assen'] ?></td>
															<td rowspan="2"><?php echo $report['doc'] ?></td>
															<td rowspan="2"><?php echo $report['patient_name'] ?></td>
															<td><?php echo $report['requesting_practice'] ?></td>
															<td><?php echo $report['requesting_physician'] ?></td>
														</tr>
														<tr>
															<td colspan="2">
																<?php
																if (count($report['screening']) > 0) {
																?>

																	<table
																		class="resulttbl wp-list-table widefat striped blue_tbl smalltbl">
																		<thead>
																			<tr>
																				<th>Screening Test Name</th>
																				<th>Value</th>
																				<th>Result</th>
																			</tr>
																		</thead>
																		<?php
																		foreach ($report['screening'] as $result) {
																		?>
																			<tr>
																				<td><?php echo $result['drug'] ?></td>
																				<td><?php echo $result['conc'] ?></td>
																				<td><?php echo $result['flag'] ?></td>
																			</tr>
																		<?php
																		}
																		?>
																	</table>
																	<br>
																<?php
																}
																?>
																<table
																	class="resulttbl wp-list-table widefat striped blue_tbl smalltbl">
																	<thead>
																		<tr>
																			<th><?php if ($btype_selected == 'tox')
																					echo 'Comfirmation ' ?>Test Name</th>
																			<?php if ($panel['tg_is_covid'] != 1) { ?>
																				<th>Value</th>
																			<?php } ?>
																			<th>Result</th>
																			<?php if ($panel['tg_is_covid'] == 1) { ?>
																				<th></th>
																			<?php } ?>
																		</tr>
																	</thead>
																	<?php
																	foreach ($report['results'] as $result) {

																	?>
																		<tr>
																			<td><?php echo $result['drug'] ?></td>
																			<?php if ($panel['tg_is_covid'] != 1) { ?>
																				<td><?php echo $result['conc'] ?></td>
																			<?php } ?>
																			<td><?php echo $result['flag'] ?></td>
																			<?php if ($panel['tg_is_covid'] == 1) { ?>
																				<td></td>
																			<?php } ?>
																		</tr>
																	<?php
																	}
																	?>
																</table>
															</td>
														</tr>
														<tr class="borderrow">
															<td colspan="5"></td>
														</tr>
													<?php
													}
													?>
												</table>
										<?php
											}
										}
										?>
									</div>

								</div>
							</div>
						</div>
					<?php
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
<script>
	var $all_panels = <?php echo json_encode($all_panels) ?>;
	var url_main_page = 'ci_reports_clinic_panel_stats';
	jQuery(function($) {
		$('body').on('change', 'select[name="btype"]', function() {
			var $btype = $(this).val();
			var $selector = $('[name="panel_ID"]');
			var $panels = $all_panels[$btype];
			var $vl = $selector.data('value');
			var $opts = '<option value="all">All Panels</option>';
			for (var i in $panels) {
				var $selected = $vl == $panels[i].tg_ID ? 'selected' : '';
				$opts += '<option value="' + $panels[i].tg_ID + '" ' + $selected + '>' + $panels[i]
					.tg_name + '</option>';
			}
			$selector.html($opts).select2();

		})
		$('select[name="btype"]').change();

		$(".export_item").click(async function() {
			var export_type = "csv";
			var baseUrl = location.href.split('?')[0];
			var params = new URLSearchParams({
				page: new URLSearchParams(location.search).get('page'),
				tab: new URLSearchParams(location.search).get('tab') || '',
				trajax: 'export_report',
				export_type: export_type,
				export_func: 'all',
				from: $("#date_from").val(),
				to: $("#date_to").val(),
				cli_ID: $("#cli_ID").val(),
				btype: $("#btype").val(),
				panel_ID: $("#panel_ID").val(),
				summary: $('#check_summary').is(':checked') ? 1 : 0,
				notdetected: $('#check_notdetected').is(':checked') ? 1 : 0,
			});
			var $url = baseUrl + '?' + params.toString();
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
	})
</script>
<?php $schedule_report->js(); ?>