<?php
global $wpdb;
define('TR_ABX_TESTING', true);
$panel_id 	= isset($_REQUEST['gid']) ? $_REQUEST['gid'] : 0;
$all_values = isset($_REQUEST['all_values']) ? $_REQUEST['all_values'] : array();
$all_cq_values = isset($_REQUEST['all_cq_values']) ? $_REQUEST['all_cq_values'] : array();
$all_amp_values = isset($_REQUEST['all_amp_values']) ? $_REQUEST['all_amp_values'] : array();
$result_settings = get_option('ci_result_settings_pcr', array());
$ins = isset($_REQUEST['ins']) ? $_REQUEST['ins'] : '*';

$groupdata = array();
if ($panel_id > 0) {
	$groupdata = $wpdb->get_row("select * from ci_test_groups where tg_ID = {$panel_id}", ARRAY_A);
	$groupdata['others'] = json_decode($groupdata['tg_other_options'], true);
}

$is_amp_cq = tr_get_option('ci_result_settings_pcr', 'enable_rs_ct_amp_cq') ? true : false;
if (!$is_amp_cq) {
	$is_amp_cq = tr_get_option('ci_result_settings_pcr', 'rs_hml_amp_cq') ? true : false;
}

$is_whqc = false;
if (!$is_amp_cq && isset($groupdata['others']) && $groupdata['others']['is_rpp'] == 1) {
	$is_amp_cq = true;
}

$pos_neg = array(
	'' => '',
	'NEG' => 'Not-Detected',
	'POS' => 'Detected',
	'PVL' => 'Detected-Very Low',
	'POL' => 'Detected-Low',
	'POM' => $result_settings['pos_moderate'] ? 'Detected-Moderate' : 'Detected-Medium',
	'POH' => 'Detected-High',
	'PVH' => 'Detected-Very High',
	'PEN' => 'Pending',
	'INC' => 'Inconclusive',
	'PRP' => 'Positive',
	'INV' => 'Invalid',
	'NGE' => 'Negative',
	'RER' => 'ReRun',
	'EQU' => 'Equivocal',
	'N/A' => 'N/A',
);

$panels = $wpdb->get_results("select * from ci_test_groups  where tg_parent = 0
and tg_type = 'pcr'
and tg_other_options like '%new_abx\":\"1%'
and tg_antibiotic = 0 
order by tg_position asc");

$dob = (date('Y') - 25) . '-05-01';
$report = array();
$report['assen'] = 'Test00001';
$report['patient_name'] = 'Patient Demo';
$report['id'] = '-10';
$report['panel_tests_requested'] = 'GROUP_' . $panel_id;
$report['instrument'] = $ins;
$report['requesting_practice'] = 'Demo';
$report['physician'] = 'Demo';
$report['pa_dob'] = $dob;
$report['dob'] = $dob;
$report['approve_date'] = date_i18n('Y-m-d H:i:s');
$report['created'] = date_i18n('Y-m-d H:i:s');
$report['doc'] = date_i18n('Y-m-d H:i:s');
$report['pa_gender'] = 'F';
$report['cli_state'] = 'MI';
$report['pa_state'] = 'MI';
$report['type_of_specimen'] = isset($_REQUEST['specimen']) ? $_REQUEST['specimen'] : '';

if ($panel_id > 0) {

	$exist_ids = array();


	$groupchilds = $wpdb->get_results("select * from ci_test_groups where tg_parent = {$panel_id} order by tg_position asc");
	if (empty($groupchilds)) {
		$groupchilds = $wpdb->get_results("select * from ci_test_groups where tg_ID = {$panel_id} order by tg_position asc");
	}

	foreach ($groupchilds as $ig => $g) {
		$g->tests = $wpdb->get_results("select * from ci_test_panels where te_group_id = {$g->tg_ID} and te_in_req = 1", ARRAY_A);
		foreach ($g->tests as $it => $arr) {

			if (empty($ins) || $ins == '*') {
				$arr['instrument'] = $wpdb->get_var("select cu_ms from ci_test_panels_cutoffs where cu_te_ID = {$arr['te_ID']} limit 1");
				if (strpos($arr['instrument'], ',') !== false) {
					$instruments = explode(',', $arr['instrument']);
					$arr['instrument'] = trim($instruments[0]);
					$report['instrument'] = $arr['instrument'];
				}
			} else {
				$arr['instrument'] = $ins;
			}

			$arr['drug'] = strtoupper($arr['te_name']);
			if (strlen($arr['drug']) > 60 && !empty($arr['te_abrev'])) {
				$arr['drug'] = strtoupper($arr['te_abrev']);
			}
			$report['panel_tests_requested'] .= ',' . $arr['te_ID'];
			$rsrow = $wpdb->get_row("select * from ci_results_pcr where report_id = {$report['id']} and drug = '{$arr['drug']}' ", ARRAY_A);

			$arr['id'] =  -1;
			$arr['report_id'] =  $report['id'];

			$arr['conc'] = $rsrow ? $rsrow['conc'] : 40;
			$arr['conc'] = isset($all_values[$arr['te_ID']]) ? $all_values[$arr['te_ID']] : $arr['conc'];
			if ($is_amp_cq) {
				$arr['cq'] = $rsrow ? $rsrow['cq'] : 20;
				$arr['amp_score'] = $rsrow ? $rsrow['amp_score'] : 20;

				$arr['cq'] = isset($all_cq_values[$arr['te_ID']]) ? $all_cq_values[$arr['te_ID']] : $arr['cq'];
				$arr['amp_score'] = isset($all_amp_values[$arr['te_ID']]) ? $all_amp_values[$arr['te_ID']] : $arr['amp_score'];
			}


			$arr = tr_pdd_parse_pcr_test($arr, $report['instrument'], $g->tg_ID, date_i18n('Y-m-d'));


			if (isset($_GET['debug12']) && $arr['conc'] < 40) {
				var_dump($arr);
				echo '<br><br>';
			}

			if (isset($_GET['debug14']) && $arr['cq'] == 55) {
				var_dump($arr);
				echo '<br><br>';
			}

			$rowdata = array(
				'report_id' => $report['id'],
				'drug' => $arr['drug'],
				'conc' => $arr['conc']
			);
			if ($is_amp_cq) {
				$rowdata['cq'] = $arr['cq'];
				$rowdata['amp_score'] = $arr['amp_score'];
			}

			if (!$rsrow) {
				$wpdb->insert('ci_results_pcr', $rowdata);
				$arr['id'] = $wpdb->insert_id;
				echo !empty($wpdb->last_error) ? json_encode($rowdata) . ' => ' . $wpdb->last_error : '';
			} else {

				if ($rsrow['conc'] != $arr['conc'] || ($is_amp_cq && ($rsrow['cq'] != $arr['cq'] || $rsrow['amp_score'] != $arr['amp_score']))) {
					$wpdb->update('ci_results_pcr', $rowdata, array('id' => $rsrow['id']));
					echo !empty($wpdb->last_error) ? json_encode($rowdata) . ' => ' . $wpdb->last_error : '';
				}

				$arr['id'] = $rsrow['id'];
			}

			if (isset($_GET['debug15'])) {
				var_dump($arr);
				echo '<br><br>';
			}
			$exist_ids[] = $arr['id'];

			$g->tests[$it] = $arr;
		}

		$groupchilds[$ig] = $g;
	}


	$wpdb->query("delete from ci_results_pcr where report_id = {$report['id']} and id not in (" . implode(',', $exist_ids) . ") ");
	//check rs


}


$report['panel_tests_requested'] = trim($report['panel_tests_requested'], ',');

$id = $_REQUEST['id'] = $report['id'];
$_REQUEST['shownewabx'] = $_GET['shownewabx'] = 1;


if (isset($_REQUEST['trajax']) && isset($_GET['html'])) {

	$_GET['debugabx'] = true;
	$_GET['html'] = true;

	echo '<div class="wrapreport">';
	include(TRCPDD_PATH . 'reports/abx.php');
	echo '</div>';
	exit;
}
if (isset($_REQUEST['trajax'])) {
	ob_start();
	include(TRCPDD_PATH . 'reports/abx.php');
	$content = ob_get_clean();
	$filename = 'demo.pdf';
	header("Pragma: public");
	header("Expires: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Cache-Control: private", false);
	header('Content-Type: application/pdf');
	ob_start("ob_gzhandler");

	trpdd_build_pdf($content, array('filename' => $filename));
	exit;
}
?>
<br>

<form method="get" id="reportform">
	<input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
	<input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
	<input type="hidden" name="test" value="1" />

	<div class="frow">
		<div style="text-align: center;">
			<label>Panel</label>
			<select name="gid" id="gid" require style="width:450px">
				<?php
				echo '<option value="">Select</option>';
				foreach ($panels as $go) {
					$selected = $panel_id == $go->tg_ID ? 'selected' : '';
					echo '<option value="' . $go->tg_ID . '" ' . $selected . '>' . $go->tg_name . '</option>';
				}
				?>
			</select>
			<?php if ($panel_id > 0) {
			?>
				<input type="submit" class="button" name="report" value="Report" />
				<input type="submit" class="button" name="trajax" value="PDF" />
			<?php
			}
			?>
		</div>

	</div>


	<div class="frow">
		<div style="text-align: center;">

			<label style="width:100px;display:inline-block">Renal</label>
			<select name="renal" style="width:220px">
				<?php
				foreach (array('' => 'None', 'normal' => 'Renal CrCl >30', 'moderate' => 'Renal CrCl 10-30', 'severe' => 'Renal CrCl ≤10', 'dialysis' => 'Dialysis') as $k => $txt) {
					$selected = $_REQUEST['renal'] == $k ? 'selected' : '';
					echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
				}
				?>
			</select>

		</div>
		<div style="text-align: center;">
			<label style="width:100px;display:inline-block">Specimen</label>
			<input type="text" name="specimen" value="<?php echo isset($_REQUEST['specimen']) ? $_REQUEST['specimen'] : '' ?>" style="width:220px" />
		</div>
		<div style="text-align: center;">
			<label style="width:100px;display:inline-block">Instrument</label>
			<input type="text" name="ins" value="<?php echo $ins ?>" style="width:220px" />
		</div>
	</div>
	<div class="frow">
		<table id="datatbl" style="width:1100px;margin:0 auto">
			<thead>
				<tr>
					<th>Test Name</th>
					<th>Abrrev</th>
					<th>CT</th>
					<?php if ($is_amp_cq) {
						echo '<th>CQ Inf</th>';
						echo '<th>AMP Score</th>';
					}
					?>
					<th style="width:120px">Result</th>
				</tr>
			</thead>
			<tbody>
				<?php

				foreach ($groupchilds as $g) {
					if (empty($g->tests)) continue;
				?>
					<tr>
						<th colspan="<?php echo $is_amp_cq ? 6 : 4 ?>" class="center" style="font-weight: bold;">
							<h4><?php echo $g->tg_name ?></h4>
						</th>
					</tr>
					<?php

					foreach ($g->tests as $arr) {
						$rowclass = 'rowitem';
						if (in_array($arr['pos-neg'], array('POS', 'PRP', 'POM', 'POH', 'PVH', 'PVL', 'POL'))) {
							$rowclass = 'rowitem positive_result';
						}
					?>
						<tr class="<?php echo $rowclass ?>">
							<td><?php echo $arr['te_name'] ?></td>
							<td><?php echo $arr['te_abrev'] ?></td>
							<td><input type="text" name="all_values[<?php echo $arr['te_ID'] ?>]" value="<?php echo $arr['conc'] ?>" /></td>
							<?php if ($is_amp_cq) {
							?>
								<td><input type="text" name="all_cq_values[<?php echo $arr['te_ID'] ?>]" value="<?php echo $arr['cq'] ?>" /></td>
								<td><input type="text" name="all_amp_values[<?php echo $arr['te_ID'] ?>]" value="<?php echo $arr['amp_score'] ?>" /></td>
							<?php
							}
							?>
							<td title="<?php echo $arr['pos-neg'] ?>"><?php echo $pos_neg[$arr['pos-neg']] ?></td>
						</tr>
				<?php
					}
				}
				?>
			</tbody>
		</table>

	</div>
</form>

<style>
	body {
		margin-top: 0 !important;
	}

	body #header {
		position: relative;
	}

	body #footer {
		display: none !important;
	}

	.wrapreport {
		position: relative;
		background: #fff;
		padding: 20px;
	}

	.positive_result {
		background-color: #ffcccc;
	}

	#datatbl input[type="text"] {
		width: 80px;
		height: 20px;
		min-height: 20px;
		padding: 0 5px;
		margin: 0;
	}

	#datatbl td {
		vertical-align: middle;
	}

	.frow h4 {
		margin: 3px;
		line-height: 24px;
	}

	img {
		display: inline;
	}

	.groupicons .charicon img {
		margin-top: 0 !important;
	}

	.groupicons .iconpill {
		top: 0;
	}
</style>
<?php
if (isset($_REQUEST['report'])) {
	//echo '<iframe style="width:100%;height:8800px;border:1px solid #ccc;margin:0 auto" src="' . Tr_Base_Class_V6::link(array('trajax' => 1, 'html' => 1), false) . '"></iframe>';
	$_GET['debugabx'] = true;
	$_GET['html'] = true;
	echo '<style>.abxstyle2,.abxstylev,#footer,#header{max-width:770px;margin:0 auto !important;} table.abxestimatedtbl{width:100%!important}</style>';
	echo '<div class="wrapreport">';
	include(TRCPDD_PATH . 'reports/abx.php');
	echo '</div>';
}
?>
<script>
	jQuery(function($) {
		$('body').on('change', 'select#gid', function() {
			$(this).parents('form').submit();
		})
	})
</script>