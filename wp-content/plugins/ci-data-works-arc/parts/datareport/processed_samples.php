<?php
global $wpdb, $current_role, $ci_all_btypes;

return include(trpdd_get_file_path('reports', 'processed_samples'));

include_once(TRCPDD_PATH . 'pages/formdata.php');

$from_date      = !empty($_GET['from']) ? $_GET['from'] : date_i18n('Y-m-1');
$to_date        = !empty($_GET['to']) ? $_GET['to'] : date_i18n('Y-m-d');
$cli_ID         = isset($_REQUEST['cli_ID']) ? $_REQUEST['cli_ID'] : 0;
$btype_selected = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'pcr';
$date_type 		= isset($_REQUEST['datetype']) ? $_REQUEST['datetype'] : 'doc';
$user_id        = get_current_user_id();
$adding_tests_markers = array();
$clinics 		= array();
$clinicname		= '';
if (count($ci_all_btypes) === 1 || ($btype_selected == 'pcr' && !in_array('pcr', $ci_all_btypes))) {
	$btype_selected = array_shift(array_values($ci_all_btypes));
}



$labname    = CI_Base_Lab::getCurrentLabName();


$conditions = trpdd_get_batch_conditions(0, 'pcr');
$edit_time  = $conditions['edit_time'];
$can_edit   = $conditions['can_edit'];
$can_delete = $conditions['can_delete'];
$can_hold   = $conditions['can_hold_req'];
$where          = ' is_saved = 0 ' . $conditions['where'];
$where_clinic   = $conditions['where_clinic'];
if ($conditions['check_author']) {
	$where      .= $return['where_author'];
}


$clinics_rs 	= $wpdb->get_results("select cID, cli_clinic, cli_status from ci_clinic where {$where_clinic}");
foreach ($clinics_rs as $r) {
	$clinics[$r->cID] = $r;
}

$inactive_clinics = array();
$where_clinic_inactive = str_replace('cli_status = 1', 'cli_status = 0', $where_clinic);
$clinics_rs 	= $wpdb->get_results("select cID, cli_clinic, cli_status from ci_clinic where {$where_clinic_inactive}");
foreach ($clinics_rs as $r) {
	$inactive_clinics[$r->cID] = $r;
}

if (isset($_GET['debugconditions'])) {
	var_dump($conditions);
	var_dump($clinics);
	var_dump($inactive_clinics);
}

$batch_tbl  = tr_pdd_get_table_by_btype($btype_selected);
$report_tbl = tr_pdd_get_table_report_by_btype($btype_selected);
$result_tbl = tr_pdd_get_table_result_by_btype($btype_selected);
$panel      = array();
$submited   = false;
$reports    = array();
$reports_clinics    = array();
$tests      = array();


$all_panels 	= array();
$all_labs 		= array();

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
					'panel' 	=> $all_panels[$panelid],
					'processed' => 0,
					'main' 		=> 0,
					'ref' 		=> 0,
					'refs' 		=> array(),
					'rejected' 	=> 0,
					'rerun'		=> 0,
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


if (isset($_REQUEST['trajax']) && stripos($_REQUEST['trajax'], 'export') !== false) {
	$filename = 'processed_samples.csv';
	$headers = array('Panel', 'Proccessed', $labname, 'Referenced', 'Rejected', 'Rerun Rate');
	tr_pdd_header('text/csv', $filename);
	$f = fopen('php://output', 'w');
	fputcsv($f, $headers);

	if ($cli_ID == -1) {
		foreach ($report_clids as $cid) {
			$cli = isset($clinics[$cid]) ? $clinics[$cid] : $inactive_clinics[$cid];
			fputcsv($f, array('', '', $cli->cli_clinic));
			foreach ($reports as $gid => $row) {

				unset($row['refs']);
				list($panelid, $rcliid) = explode('_', $gid);
				if ($rcliid == $cid) {
					fputcsv($f, $row);
				}
			}
			fputcsv($f, array(''));
		}
	} else {
		foreach ($reports as $row) {

			unset($row['refs']);
			fputcsv($f, $row);
		}
	}

	exit;
}


?>
<div class="wrap">
	<form method="get" style="margin-bottom:20px" id="formsearch">
		<input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
		<input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />
		From: <input type="text" class="hasdate" name="from" style="width:100px" value="<?php echo @$from_date ?>" required />
		<label id="summarytodate">To:</label>
		<input type="text" class="hasdate today" name="to" style="width:100px" value="<?php echo @$to_date ?>" required />
		<select name="datetype">
			<?php
			foreach (array('doc' => 'DOC', 'approve' => 'Approved Date', 'received' => 'Received', 'bid' => 'Batch#') as $k => $txt) {
				$selected = $k == $date_type ? 'selected' : '';
				echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
			}
			?>
		</select>
		<select name="cli_ID" class="js_select2" style="max-width:270px">
			<option value="">All Clinics</option>
			<?php
			echo '<option value="-1" ' . ($cli_ID == -1 ? 'selected' : '') . '>All Clinics - Separate</option>';
			foreach ($clinics as $cli) {
				$selected = '';
				if ($cli->cID == $cli_ID) {
					$clinicname = $cli->cli_clinic;
					$selected = 'selected';
				}


				echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
			}
			?>
		</select>
		<?php if (count($ci_all_btypes) > 1) { ?>
			<select name="btype" required>
				<?php
				foreach ($ci_all_btypes as $bt) {
					$selected = isset($_REQUEST['btype']) && $_REQUEST['btype'] == $bt ? 'selected' : '';
					echo '<option value="' . $bt . '" ' . $selected . '>' . tr_pdd_get_typename($bt) . '</option>';
				}
				?>
			</select>
		<?php } ?>

		<input type="submit" class="button-primary" value="Report" />
		<input type="submit" class="button" name="trajax" value="Export" />

	</form>


	<table class="report_tbl">
		<thead>
			<tr>
				<th>Panel</th>
				<th>Processed</th>
				<th><?php echo $labname ?></th>
				<th>Referenced</th>
				<th>Rejected</th>
				<th>Rejected %</th>
				<th>Rerun Rate</th>
			</tr>
		</thead>
		<tbody>
			<?php

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
						'main' 		=> 0,
						'ref' 		=> 0,
						'rejected' 	=> 0,
						'rejected_percent' => 0,
						'rerun' 	=> 0,
					);

					foreach ($reports as $gid => $row) {
						list($panelid, $rcliid) = explode('_', $gid);

						if ($rcliid === $cid) {
							$row['rejected_percent'] = $row['processed'] > 0 ? $row['rejected'] / $row['processed'] * 100 : 0;

							foreach ($totals as $k => $vl) {
								$totals[$k] += $row[$k];
							}
							$ref_by_labs = array();
							foreach ($row['refs'] as $lid => $vl) {
								$ref_by_labs[] = $vl . $all_labs[$lid]->lab_dba_init;
							}
							$trrows .= '<tr class="childp">';
							$trrows .= '<td>' . $row['panel'] . '</td>';
							$trrows .= '<td>' . $row['processed'] . '</td>';
							$trrows .= '<td>' . $row['main'] . '</td>';
							$trrows .= '<td>' . $row['ref'] . implode(' &nbsp;&nbsp;', $ref_by_labs) . '</td>';
							$trrows .= '<td>' . $row['rejected'] . '</td>';
							$trrows .= '<td>' . number_format($row['rejected_percent'], 2) . '%' . '</td>';
							$trrows .= '<td>' . $row['rerun'] . '</td>';
							$trrows .= '</tr>';
						}
					}

					echo '<tr class="clinic_status_' . $cli->cli_status . '"><th class="clinic_name">' . $cli->cli_clinic . '</th>';

					$totals['rejected_percent'] = $totals['processed'] > 0 ? $totals['rejected'] / $totals['processed'] * 100 : 0;
					$totals['rejected_percent'] = number_format($totals['rejected_percent'], 2) . '%';
					foreach ($totals as $k => $vl) {
						echo '<td>' . $vl . '</td>';
					}
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
					$row['rejected_percent'] = $row['processed'] > 0 ?  $row['rejected'] / $row['processed'] * 100 : 0;

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

					$trrows .= '<tr class="childp">';
					$trrows .= '<td>' . $row['panel'] . '</td>';
					$trrows .= '<td>' . $row['processed'] . '</td>';
					$trrows .= '<td>' . $row['main'] . '</td>';
					$trrows .= '<td>' .  implode(' &nbsp;&nbsp; ', $ref_by_labs) . '</td>';
					$trrows .= '<td>' . $row['rejected'] . '</td>';
					$trrows .= '<td>' . number_format($row['rejected_percent'], 2) . '%' . '</td>';
					$trrows .= '<td>' . $row['rerun'] . '</td>';
					$trrows .= '</tr>';
				}

				echo '<tr><th>' . ($cli_ID > 0 ? $clinicname : 'All Clinics') . '</th>';

				$totals['rejected_percent'] = $totals['processed'] > 0 ? $totals['rejected'] / $totals['processed'] * 100 : 0;
				$totals['rejected_percent'] = number_format($totals['rejected_percent'], 2) . '%';
				foreach ($totals as $k => $vl) {
					if ($k == 'ref') {
						$ref_by_labs = array();
						foreach ($totalrefs as $lid => $vl) {
							$ref_by_labs[] = $vl . $all_labs[$lid]->lab_dba_init;
						}
						$vl = implode(' &nbsp;&nbsp; ', $ref_by_labs);
					}
					echo '<td>' . $vl . '</td>';
				}
				echo '</tr>';

				echo $trrows;
			}

			?>
		</tbody>
	</table>


</div>
<style>
	tr.clinic_status_0 .clinic_name {
		color: red;
	}
</style>