<?php
global $wpdb, $current_role;
include_once(TRCPDD_PATH . 'pages/formdata.php');

$from_date      = !empty($_GET['from']) ? $_GET['from'] : date_i18n('Y-m-d');
$to_date        = !empty($_GET['to']) ? $_GET['to'] : $from_date;
$cli_ID         = isset($_REQUEST['cli_ID']) ? $_REQUEST['cli_ID'] : 0;
$panel_ID       = isset($_REQUEST['panel_ID']) ? $_REQUEST['panel_ID'] : 0;
$summary        = isset($_REQUEST['summary']) && $_REQUEST['summary'];
$show_notdetected    = isset($_REQUEST['notdetected']) && $_REQUEST['notdetected'];
$btype_selected = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'pcr';
$user_id        = get_current_user_id();
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


$batch_tbl  = tr_pdd_get_table_by_btype($btype_selected);
$report_tbl = tr_pdd_get_table_report_by_btype($btype_selected);
$result_tbl = tr_pdd_get_table_result_by_btype($btype_selected);
$panel      = array();
$submited   = false;
$reports    = array();
$tests      = array();
$test_ids   = array();
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


if (!empty($from_date) && ($panel_ID > 0 || $panel_ID == 'all')) {
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
						if (empty($arr['outcome'])) $arr['outcome'] = 'NEG';

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

if (isset($_GET['trajax'])) {

	if (stripos($_GET['trajax'], 'export') !== false) {
		ob_clean();
		$file_name = "clinic_panel_stats.csv";
		if ($summary) {
			$file_name = 'summary_clinic_panel_stats.csv';
		}
		if ($cli_ID > 0) {
			$file_name = str_replace('.csv', '_' . $cli_ID . '.csv', $file_name);
		}
		header("Content-disposition: attachment; filename={$file_name}");
		header("Content-Type: text/csv");
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
	}
	exit;
}

$all_panels = array();
foreach (tr_pdd_get_batch_types() as $bt) {
	$all_panels[$bt] = $wpdb->get_results("select tg_ID, tg_name, tg_type from ci_test_groups 
								where tg_type = '{$bt}' and tg_show_req = 1
								order by tg_position asc", ARRAY_A);
}


?>
<div class="wrap">
	<form method="get" style="margin-bottom:20px" id="formsearch">
		<input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
		<input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />
		From: <input type="text" class="hasdate" name="from" style="width:100px" value="<?php echo @$from_date ?>" required />
		<label id="summarytodate">To:</label>
		<input type="text" class="hasdate today" name="to" style="width:100px" value="<?php echo @$to_date ?>" required />
		<select name="cli_ID" class="js_select2" style="max-width:270px">
			<option value="">All Clinics</option>
			<?php
			foreach ($clinics as $cli) {
				$selected = $cli->cID == $cli_ID ? 'selected' : '';
				echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
			}
			?>
		</select>

		<select name="btype" required>
			<?php
			foreach (tr_pdd_get_batch_types() as $bt) {
				$selected = isset($_REQUEST['btype']) && $_REQUEST['btype'] == $bt ? 'selected' : '';
				echo '<option value="' . $bt . '" ' . $selected . '>' . tr_pdd_get_typename($bt) . '</option>';
			}
			?>
		</select>

		<select name="panel_ID" class="js_select2" data-value="<?php echo isset($_REQUEST['panel_ID']) ? $_REQUEST['panel_ID'] : '' ?>" data-placeholder="Select Panel" style="max-width:270px;width:200px" required></select>

		<label class="lcb"> <input type="checkbox" name="summary" value="1" <?php checked($summary) ?> />Summary</label>
		<label class="lcb"> <input type="checkbox" name="notdetected" value="1" <?php checked($show_notdetected) ?> />Show "Not-Detected"</label>
		<input type="submit" class="button-primary" value="Report" />

		<input type="submit" name="trajax" value="Export" class="button" />
	</form>


	<div class="wraptbl">
		<?php
		if ($submited) {
			if (count($reports) == 0) {
				echo '<p class="center">Not found</p>';
			} else {
				if ($summary) {
		?>
					<table id="table_report" class="wp-list-table widefat striped posts blue_tbl hover_tbl smalltbl">
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
					<table id="table_report" class="wp-list-table widefat striped posts blue_tbl hover_tbl smalltbl">
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

										<table class="resulttbl wp-list-table widefat striped blue_tbl smalltbl">
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
									<table class="resulttbl wp-list-table widefat striped blue_tbl smalltbl">
										<thead>
											<tr>
												<th><?php if ($btype_selected == 'tox') echo 'Comfirmation ' ?>Test Name</th>
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
		}
		?>
	</div>

</div>
<style>
	table.resulttbl {
		width: 100%;
		margin: 0 auto;
	}

	table.resulttbl tr td {
		width: 33%;
	}
</style>
<script>
	var $all_panels = <?php echo json_encode($all_panels) ?>;
	jQuery(function($) {
		$('body').on('change', 'select[name="btype"]', function() {
			var $btype = $(this).val();
			var $selector = $('[name="panel_ID"]');
			var $panels = $all_panels[$btype];
			var $vl = $selector.data('value');
			var $opts = '<option value="all">All Panels</option>';
			for (var i in $panels) {
				var $selected = $vl == $panels[i].tg_ID ? 'selected' : '';
				$opts += '<option value="' + $panels[i].tg_ID + '" ' + $selected + '>' + $panels[i].tg_name + '</option>';
			}
			$selector.html($opts).select2();

		})
		$('select[name="btype"]').change();
	})
</script>