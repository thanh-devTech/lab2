<?php
global $wpdb, $current_role;

$act = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';
if (!isset($is_v2_3)) {
	$is_v2_3 = false;
}

include_once(TRCPDD_PATH . 'pages/formdata.php');

$from_date = !empty($_GET['from']) ? $_GET['from'] : date_i18n('Y-m-d');
$to_date = !empty($_GET['to']) ? $_GET['to'] : $from_date;
$cli_ID = isset($_REQUEST['cli_ID']) ? $_REQUEST['cli_ID'] : 0;
$rtype = isset($_REQUEST['rtype']) ? $_REQUEST['rtype'] : 'pcr';
$is_summary = isset($_REQUEST['summary']) ? $_REQUEST['summary'] : 0;

$show_pending = false;
$tests_str = '';

$options = get_option('ci_send_hub_tests', array());
$obj_option = array_column($options, null, 'tid');

$btypes = array_column($options, 'btype');
$btypes = array_unique($btypes);

$list_pos_neg_default = array(
	'POS' => 'Detected',
	'NEG' => 'Negative',
	'POL' => 'Detected Low',
	'POM' => 'Detected Medium',
	'POH' => 'Detected High',
	'PVL' => 'Detected Very Low',
	'PVH' => 'Detected Very High',
);

$rejected_status = tr_pdd_where_batch_rejected(true, false, 'pcr');
$races = array(
	'1002-5' => 'American Indian or Alaska Native',
	'2028-9' => 'Asian',
	'2054-5' => 'Black / African American',
	'2076-8' => 'Native Hawaiian or Other  Pacific Islander',
	'2106-3' => 'White',
	'2131-1' => 'Other'
);
$Ethnicitis = array(
	'H' => 'Hispanic or Latino',
	'N' => 'Not Hispanic or Latino',
	'U' => 'Unknown'
);

$use_racedesc = tr_get_option('ci_pdf_data', 'use_racedesc');

$doc_ID = 0;
$user_id = get_current_user_id();
$show_all = false;
$clinics = array();

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
		$cid = get_user_meta($user_id, '_clinic_id', true);
		$where_cli = "";
		if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
			$cids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
			$cids = implode(',', $cids);
			$where_cli .= " and cli.cID in ({$cids}) ";
		}

		$clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
                                    where cli.cli_status = 1 {$where_cli} order by cli_clinic asc");

		break;


	case 'multi_site_manager':
		$where_cli = '';
		if (!tr_get_option('ci_pdf_data', 'msm_can_show_all')) {
			$ms_cli_ids = get_user_meta($user_id, '_ms_clinic_ids', true);
			if (!is_array($ms_cli_ids) || empty($ms_cli_ids)) {
				$ms_cli_ids = $wpdb->get_col("select cID from ci_clinic where cli_status = 1 ");
				update_user_meta($user_id, '_ms_clinic_ids', $ms_cli_ids);
			}
			$ms_cli_ids_str = implode(',', $ms_cli_ids);
			$where_cli .= " and cli.cID in ({$ms_cli_ids_str}) ";
		}


		$clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
                                    where cli.cli_status = 1 {$where_cli} order by cli_clinic asc");
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



$pos_neg_detected = ['POS', 'POL', 'POM', 'POH', 'PVH', 'PVL'];

$child_drugs = array();
$child_drugs_str = '';

$default_arr = array();

$title_export = '';

if ($from_date && $to_date) {
	$where = '';

	if ((empty($cli_ID) || $cli_ID === 'all') && !$show_all) {
		$cids = array();
		foreach ($clinics as $clinic) {
			$cids[] = $clinic->cID;
		}
		$cids = implode(',', $cids);
		$where .= " and ba.cli_ID in ({$cids}) ";
	} else if ($cli_ID != 'all' && $cli_ID > 0) {
		$where .= " and ba.cli_ID = {$cli_ID} ";
		$clinic = $wpdb->get_row("select * from ci_clinic where cID = {$cli_ID}");
		$title_export = $clinic->cli_clinic;
	}

	if ($doc_ID > 0) {
		$where .= " and ba.doc_ID = {$doc_ID} ";
	}
	$from_b = str_replace('-', '', $from_date);
	$to_b = str_replace('-', '', $to_date);

	if (isset($_REQUEST['bybat'])) {
		$fromb = str_replace('-', '', $from_date);
		$tob = str_replace('-', '', $to_date);
		$where .= "and ba.batch_id >= {$fromb} and ba.batch_id <= {$tob} ";
	} else if (isset($_REQUEST['bydoc'])) {
		$where .= "and ba.doc >= '{$from_date}' and ba.doc <= '{$to_date} 23:59:59' ";
	} else {
		$where .= "and re.approve_date >= '{$from_date}' and re.approve_date <= '{$to_date} 23:59:59' ";
	}

	$option_by_testid = [];
	foreach ($options as $opt) {
		if (!empty($opt['tid'])) {
			$option_by_testid[(int) $opt['tid']] = $opt;
		}
	}
	// Lấy danh sách tid trong report to state
	$option_tids = array_keys($option_by_testid);
	$find_in_set_conditions = [];

	foreach ($option_tids as $tid) {
		$find_in_set_conditions[] = "find_in_set('{$tid}', ba.panel_tests_requested)";
	}

	if (!empty($find_in_set_conditions)) {
		$where .= " AND (" . implode(' OR ', $find_in_set_conditions) . ")";
	}

	$more_fields = '';
	$joins = '';
	if ($is_v2_3) {
		$more_fields .= ',ba.type_of_specimen,
            doc.doc_first,
            doc.doc_last,
            doc.doc_npi,
            doc.doc_phone';
		$joins .= 'join ci_doc doc ON doc.doc_ID = ba.doc_ID';
	}

	$sql = "select 
		        ba.patient_name, 
	            ba.doc,
	            ba.spec_received,
	            ba.requesting_practice,
	            ba.requesting_physician,
	            ba.assen,
	            ba.oldreq,
	            ba.batch_status,
	            ba.p_condition,
	            ba.panel_tests_requested,
	            
	            pa.*,
                
                cli.cli_clinic,
                cli.cli_street,
                cli.cli_city,
                cli.cli_state,
                cli.cli_zip,
                cli.`cli_om-phone`,
                
                lab.lab_dba_init,
                lab.lab_clia,
                lab.lab_name,
                lab.lab_address,
                lab.lab_city,
                lab.lab_state,
                lab.lab_zip,
                
                re.id,
	
                re.created_date,
                re.approve_date,
                re.instrument
                {$more_fields}
                from ci_batches_pcr ba
                join ci_clinic cli On cli.cID = ba.cli_ID
                left join ci_labs lab ON lab.lab_ID = ba.lab_ID
                left join ci_patients pa ON ba.patient_id = pa.pa_ID
                join ci_reports_pcr re ON re.patient_id = ba.assen
                {$joins}
                where re.estatus = 'C'
                    {$where}
                order by ba.patient_name asc";
	$rows = $wpdb->get_results($sql);


	$count_status = array();

	if (isset($_GET['debug'])) {
		echo '<br>count: ' . count($rows) . '<br>';
	}
	$results = [];
	$report = [
		'all_item' => []
	];

	foreach ($rows as $row) {

		if (isset($rejected_status[$row->batch_status])) {
			continue;
		}

		$row->age = trdd_dob2ages($row->pa_dob);

		// lấy danh sách tid trong panel
		$test_ids = array_filter(array_map('intval', explode(',', $row->panel_tests_requested)));

		// lấy tid tồn tại trong options
		$existing_tids = array_intersect($test_ids, array_keys($option_by_testid));
		if (empty($existing_tids))
			continue;


		$row->existing_tids = $existing_tids;

		// lấy GROUP_*
		$groups = [];
		foreach (explode(',', $row->panel_tests_requested) as $p) {
			if (stripos($p, 'GROUP_') !== false) {
				$groups[] = (int) str_replace('GROUP_', '', $p);
			}
		}

		$listTest = tr_pdd_get_results_by_testsid(
			$row->id,
			$rtype,
			$test_ids,
			$groups,
			$row->instrument,
			true
		);


		$matchedTests = 0;
		$t_names = [];
		$r_result = [];
		$r_pn = ['all_item' => []];
		$list_test_pos_neg = [];
		foreach ($listTest as $t_ID => $pn) {
			$list_test_pos_neg[$t_ID] = $pn['pos-neg'];
		}

		foreach ($listTest as $pn) {
			$te_ID = $pn['te_ID'];
			$pos_neg_val = $pn['pos-neg'];
			$list_pos_neg = $pn['pos_neg_list'] ?? [];
			if (empty($list_pos_neg)) {
				$list_pos_neg = $list_pos_neg_default;
			}

			if (!isset($option_by_testid[$te_ID])) {
				continue;
			}

			$opt_cur = $option_by_testid[$te_ID];
			$is_matched = true;

			if (!empty($opt_cur['cond_only_tids']) && is_array($opt_cur['cond_only_tids'])) {

				$intersect = array_intersect($opt_cur['cond_only_tids'], $test_ids);
				if (empty($intersect)) {
					$is_matched = false;
				}
				if ($is_matched) {
					$cond_only_pn = isset($opt_cur['cond_only_pos_neg']) ? $opt_cur['cond_only_pos_neg'] : 0;
					if ($cond_only_pn > 0) {
						$has_valid_pos_neg = false;

						foreach ($opt_cur['cond_only_tids'] as $check_tid) {
							$check_pos_neg = isset($list_test_pos_neg[$check_tid]) ? $list_test_pos_neg[$check_tid] : '';
							if ($cond_only_pn == 1) {
								if (in_array($check_pos_neg, $pos_neg_detected)) {
									$has_valid_pos_neg = true;
									break;
								}
							} elseif ($cond_only_pn == 2) {
								if ($check_pos_neg === 'NEG') {
									$has_valid_pos_neg = true;
									break;
								}
							}
						}
						if (!$has_valid_pos_neg) {
							$is_matched = false;
						}
					}
				}
			}

			if ($is_matched) {
				if (!in_array($te_ID, $existing_tids)) {
					$is_matched = false;
				}
			}

			if ($is_matched) {

				$cond = isset($opt_cur['cond_pos_neg']) ? $opt_cur['cond_pos_neg'] : 0;
				if ($cond > 0) {
					if ($cond == 1 && !in_array($pos_neg_val, $pos_neg_detected)) {
						$is_matched = false;
					} elseif ($cond == 2 && $pos_neg_val !== 'NEG') {
						$is_matched = false;
					}
				}
			}

			if (!$is_matched) {
				continue;
			}

			$matchedTests++;

			// P/N text
			$t_names[] = $pn['te_name'];
			$r_result[] = isset($list_pos_neg[$pos_neg_val]) ? $list_pos_neg[$pos_neg_val] : $pos_neg_val;

			if (!isset($report['all_item'][$te_ID])) {
				$report['all_item'][$te_ID] = [
					'id' => $te_ID,
					'name' => $pn['te_name'],
					'p' => 0,
					'n' => 0
				];
			}

			if (in_array($pos_neg_val, $pos_neg_detected)) {
				$report['all_item'][$te_ID]['p']++;
			} elseif ($pos_neg_val === 'NEG') {
				$report['all_item'][$te_ID]['n']++;
			}
		}

		if ($matchedTests > 0 && !empty($t_names)) {
			$row->te_names = $t_names;
			$row->te_result = $r_result;
			$results[] = $row;
		}
	}
	$total_rs = count($results);
} else {
	$total_rs = 0;
	$results = array();
}

if ($act == 'export') {
	ob_clean();
	$file_name = "state_reporting.csv";
	if (!empty($title_export)) {
		$file_name = $title_export . '_' . $file_name;
	}
	header("Content-disposition: attachment; filename={$file_name}");
	header("Content-Type: text/csv");
	$f = fopen('php://output', 'w');

	if (!empty($title_export)) {
		fputcsv($f, array($title_export));
	}

	$headers = array(
		'Accession',
		'Patient First',
		'Patient Last',
		'Patient ID',
		'Birthday',
		'Age',
		'Sex',
		'Race',
		'Ethnicity',
		'Address',
		'City',
		'State',
		'Zip',
		'Phone',
		'Doctor Last Name',
		'Doctor First Name',
		'Clinic',
		'Clinic Address',
		'Clinic City',
		'Clinic State',
		'Clinic Zip',
		'Clinic Phone',
		'Lab Address',
		'Lab City',
		'Lab State',
		'Lab Zip',
		'DOC',
		'Collection Date Time',
		'Received Date',
		'Result Date',
		'Date Resulted'
	);

	$show_pid_crp = tr_get_option('ci_pdf_data', 'show_pid_crp');
	if ($show_pid_crp) {
		$pid_title = tr_get_option('ci_pdf_data', 'title_pid_field');
		if (empty($pid_title)) {
			$pid_title = 'PID';
		}
		$headers[] = $pid_title;
	}

	$headers[] = 'Tests';
	$headers[] = 'Test Result';

	fputcsv($f, $headers);

	foreach ($results as $row) {
		if (isset($rejected_status[$row->batch_status])) {
			continue;
		}

		if (strpos($row->requesting_physician, ',') > 0) {
			list($doctor_last, $doctor_first) = explode(',', $row->requesting_physician);
		} else {
			list($doctor_first, $doctor_last) = explode(' ', $row->requesting_physician, 2);
		}
		$doctor_first = trim($doctor_first);
		$doctor_last = trim($doctor_last);

		$clinic_phone = trpdd_format_phone_number($row->{'cli_om-phone'});

		$race = $use_racedesc ? $races[$row->pa_race] : $row->pa_race;
		if (empty($race)) {
			$race = 'Unknown';
		}
		if (empty($row->pa_ethnicity)) {
			$row->pa_ethnicity = 'Unknown';
		} else {
			$row->pa_ethnicity = isset($Ethnicitis[$row->pa_ethnicity]) ? $Ethnicitis[$row->pa_ethnicity] : $row->pa_ethnicity;
		}

		$row->pa_phone = str_replace(array('(', ')', '.', '-', ' '), '', $row->pa_phone);
		$row->pa_phone = substr($row->pa_phone, -10);
		if (strlen($row->pa_phone) < 10 || $row->pa_phone == 0) {
			$row->pa_phone = '';
		}
		$data = array(
			$row->assen,
			$row->pa_first,
			$row->pa_last,
			$row->pa_mrn,
			$row->pa_dob,
			$row->age,
			$row->pa_gender,
			$race,
			$row->pa_ethnicity,
			$row->pa_address,
			$row->pa_city,
			$row->pa_state,
			$row->pa_zip,
			$row->pa_phone,
			$doctor_last,
			$doctor_first,
			$row->requesting_practice,
			$row->cli_street,
			$row->cli_city,
			$row->cli_state,
			$row->cli_zip,
			$clinic_phone,
			$row->lab_address,
			$row->lab_city,
			$row->lab_state,
			$row->lab_zip,
			tr_pdd_display_date($row->doc, 'Y-m-d'),
			tr_pdd_display_date($row->doc, 'YmdHis-0000'),
			tr_pdd_display_date($row->spec_received, 'YmdHis-0000'),
			tr_pdd_display_date($row->approve_date, 'Y-m-d'),
			tr_pdd_display_date($row->approve_date, 'YmdHis-0000'),

		);
		if ($show_pid_crp) {
			$data[] = $row->oldreq;
		}

		$data[] = implode("\n", $row->te_names);
		$data[] = implode("\n", $row->te_result);
		fputcsv($f, $data);
	}
	$data_log = array(
		'type' => 'Covid Reporting',
		'file_name' => $file_name,
		'total_records' => count($results),
		'from_date' => $from_date,
		'to_date' => $to_date,
	);
	trpdd_log_user(get_current_user_id(), 'download_report', $data_log, '', '', '', '', 'Report_' . date_i18n('mdY'));

	@fclose($f);
	exit;
}

if (isset($_GET['debug'])) {
	var_dump($sql);
	echo '<hr/>Options:';
	var_dump($obj_option);
	echo '<hr/>Report:';
	var_dump($report);
	echo '<hr/>Row 1:';
	var_dump($results[0]);
}

?>

<div class="wrap">
	<h2>State Reporting</h2>
	<form method="get" style="margin-bottom:20px" id="formsearch">
		<input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
		<input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />
		From Date: <input type="text" class="hasdate" name="from" style="width:100px"
			value="<?php echo isset($from_date) ? $from_date : '' ?>" required />
		<label id="summarytodate">To:</label>
		<input type="text" class="hasdate today" name="to" style="width:100px"
			value="<?php echo isset($to_date) ? $to_date : '' ?>" required />
		<?php
		if (isset($clinics)) {
		?>
			<select name="cli_ID" class="select2 njs_select2" style="max-width:350px" data-placeholder="Select Clinic">
				<option value="all">All Clinic</option>
				<?php
				foreach ($clinics as $cli) {
					$selected = $cli->cID == $cli_ID ? 'selected' : '';
					echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
				}
				?>
			</select>
		<?php
		}
		?>
		<label class="lcb" title="based on date of collection"><input type="checkbox" name="bydoc" value="1" <?php checked(isset($_GET['bydoc']) ? $_GET['bydoc'] : '') ?> />DOC </label>
		<label class="lcb"> <input type="checkbox" name="summary" value="1" <?php checked(isset($_GET['summary']) ? $_GET['summary'] : '') ?> />Summary</label>

		<select name="rtype" class="select2 njs_select2" style="max-width:350px" data-placeholder="Select type">
			<?php
			foreach ($btypes as $bt) {
				$selected = $bt == $rtype ? 'selected' : '';
				echo '<option value="' . $bt . '" ' . $selected . '>' . $bt . '</option>';
			}
			?>
		</select>

		<input type="submit" class="button-primary" value="Report" />
		<input type="button" name="export" value="Export" class="button" />
		<?php if (tr_get_option('ci_pdf_data', 'e_export_ma')) { ?>
			<input type="button" name="export_ma" value="Export MA" class="button" />
		<?php } ?>
	</form>


	<div class="wraptbl">

		<?php if ($is_summary == 1) {

			$grouped = [];

			$total_p = 0;
			$total_n = 0;

			foreach ($report['all_item'] as $id => $item) {
				$name = $item['name'];

				if (!isset($grouped[$name])) {
					$grouped[$name] = [
						'name' => $name,
						'p' => 0,
						'n' => 0,
						'ids' => []
					];
				}

				$grouped[$name]['p'] += $item['p'];
				$grouped[$name]['n'] += $item['n'];
				$grouped[$name]['ids'][] = $id;
				$total_p += $item['p'];
				$total_n += $item['n'];
			}

			foreach ($grouped as $name => $g) {
				$total = $g['p'] + $g['n'];
				$percent = $total > 0 ? round(($g['p'] / $total) * 100, 2) : 0;
				$grouped[$name]['percent_p'] = $percent;
			}

			$report['total_p'] = $total_p;
			$report['total_n'] = $total_n;

			if (!empty($report)) {
				$googledata = array();
				$count_status['POS'] = intval($count_status['POS']);
				$count_status['NEG'] = intval($count_status['NEG']);
				$count_status['INV'] = intval($count_status['INV']);
				$count_status['INC'] = intval($count_status['INC']);
				$count_status['PEN'] = intval($count_status['PEN']);


				$title = 'Pos/Neg';
				$google_title = array('Tests', 'POS', 'NEG');
				$google_values = array('Total', $report['total_p'], $report['total_n']);
				$google_title[] = array('role' => 'annotation');
				$google_values[] = '';

				$googledata = array($google_title, $google_values);

		?>
				<div style="width:45%;float:left">
					<br><br>
					<table class="wp-list-table widefat striped posts blue_tbl hover_tbl border_tbl">
						<thead>

							<tr>
								<th>Tests</th>
								<th><?php echo $title ?></th>
								<th>Positive Negative</th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ($grouped as $key => $item) {
							?>
								<tr>
									<td><?php echo $item['name']; ?></td>
									<td><?php echo $item['p'] . '/' . $item['n'] ?></td>
									<td><?php echo $item['percent_p']; ?>%</td>
								</tr>
							<?php } ?>
						</tbody>
					</table>
					<div>
						Total: <?php echo $total_rs; ?>
					</div>
				</div>
				<div style="width:50%;float:right">
					<div id="chart_report_div"></div>
				</div>

				<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

				<script type="text/javascript">
					google.charts.load('current', {
						'packages': ['corechart', 'bar']
					});
					google.charts.setOnLoadCallback(drawChart_all);

					function drawChart_all() {
						var data = google.visualization.arrayToDataTable(<?php echo json_encode($googledata) ?>);

						var options = {
							width: 500,
							height: 550,
							legend: {
								position: 'top',
								maxLines: 8
							},
							bar: {
								groupWidth: '45%'
							},
							isStacked: true,
							colors: ['red', 'blue', 'green'],
						};

						// Instantiate and draw our chart, passing in some options.
						var chart = new google.visualization.ColumnChart(document.getElementById('chart_report_div'));
						chart.draw(data, options);
					}
				</script>
			<?php
			} else {
				echo 'None Data';
			}
		} else {

			if (!empty($_GET['orderby'])) {
				function tr_state_sort_reporting($a, $b)
				{
					$order = $_GET['order'] == 'asc' ? 'asc' : 'desc';
					$key = 'age';
					switch ($_GET['orderby']) {
						case 'result':
							$key = 'test_result';
							break;
					}

					$vl1 = $a->{$key};
					$vl2 = $b->{$key};

					if ($order == 'asc') {
						return strcmp($vl1, $vl2);
					} else {
						return strcmp($vl2, $vl1);
					}
				}
				usort($results, 'tr_state_sort_reporting');
			}

			?>
			<table class="wp-list-table widefat striped posts blue_tbl hover_tbl smalltbl">
				<thead class="sticky">
					<tr>
						<?php tr_tbl_sort('assen', 'Accession', '', 'width:110px') ?>
						<?php tr_tbl_sort('pa_first', 'First Name', '', 'width:70px') ?>
						<?php tr_tbl_sort('pa_last', 'Last Name', '', 'width:70px') ?>
						<th>Birthday</th>
						<?php tr_tbl_sort('age', 'Age', '', 'width:52px') ?>
						<?php tr_tbl_sort('pa_gender', 'Sex', '', 'width:52px') ?>
						<th>Address</th>
						<th>City</th>
						<th>Zip</th>
						<th>State</th>
						<th>Phone</th>
						<?php
						if (tr_get_option('ci_pdf_data', 'show_racecols')) {
						?>
							<th>Race</th>
							<th>Ethnicity</th>
						<?php
						}
						if (tr_get_option('ci_pdf_data', 'cv_show_sexgen')) {
						?>
							<th>Sexual Orientation</th>
							<th>Gender Identity</th>
						<?php
						}
						?>
						<th>Doctor</th>
						<th>DOC</th>
						<th>Result Date</th>
						<th>Test</th>
						<th>Result</th>
					</tr>
				</thead>
				<tbody>
					<?php

					if (count($results) > 0) {
						foreach ($results as $row) {
					?>
							<tr class="status_<?php echo $row->test_result; ?>">
								<td><?php echo $row->assen ?></td>
								<td><?php echo tr_pdd_capitalized($row->pa_first) ?></td>
								<td><?php echo tr_pdd_capitalized($row->pa_last) ?></td>
								<td><?php echo tr_pdd_display_date($row->pa_dob, 'Y-m-d') ?></td>
								<td><?php echo $row->age ?></td>
								<td><?php echo $row->pa_gender ?></td>
								<td><?php echo $row->pa_address ?></td>
								<td><?php echo $row->pa_city ?></td>
								<td><?php echo $row->pa_zip ?></td>
								<td><?php echo $row->pa_state ?></td>
								<td><?php echo $row->pa_phone ?></td>
								<?php
								if (tr_get_option('ci_pdf_data', 'show_racecols')) {
								?>
									<td><?php echo $use_racedesc && isset($races[$row->pa_race]) ? $races[$row->pa_race] : $row->pa_race ?>
									</td>
									<td><?php echo $row->pa_ethnicity ?></td>
								<?php
								}
								if (tr_get_option('ci_pdf_data', 'cv_show_sexgen')) {
								?>
									<td><?php echo $row->pa_sexual_oriention ?></td>
									<td><?php echo $row->pa_gender_identity ?></td>
								<?php
								}
								?>
								<td title="<?php echo $row->requesting_practice ?>"><?php echo $row->requesting_physician ?></td>
								<td><?php echo tr_pdd_display_date($row->doc, 'Y-m-d') ?></td>
								<td><?php echo tr_pdd_display_date($row->approve_date, 'Y-m-d') ?></td>
								<td style="white-space: nowrap;">
									<?php echo implode('<br>', $row->te_names); ?>
								</td>
								<td>
									<?php echo implode('<br>', $row->te_result); ?>
								</td>
							</tr>
					<?php
						}
					} else {
						echo '<tr><td colspan=5>No Results Found.</td></tr>';
					}
					?>
				</tbody>
			</table>
			<div>
				Total: <?php echo $total_rs; ?>
			</div>
		<?php
		}
		?>
	</div>
</div>
<style>
	tr.status_POS td,
	tr div.c_POS {
		background: lightpink;
	}

	label.lcb {
		padding: 0 7px;
	}
</style>

<script>
	jQuery(function($) {
		$('input[name="export"]').click(function() {
			$('#formsearch').append('<input type="hidden" name="trajax" value="1"/>');
			$('#formsearch').append('<input type="hidden" name="act" value="export"/>');
			$('#formsearch').submit();
		})
		$('input[name="export_ma"]').click(function() {
			$('#formsearch').append('<input type="hidden" name="trajax" value="1"/>');
			$('#formsearch').append('<input type="hidden" name="act" value="export_ma"/>');
			$('#formsearch').submit();
		})
		$('input[type="submit"]').click(function() {
			$('#formsearch input[name="trajax"]').remove();
			$('#formsearch input[name="act"]').remove();
		})
		$('[name="from"]').change(function() {
			$('[name="to"]').val($(this).val());
		})

		$('.select2').select2();
	})
</script>