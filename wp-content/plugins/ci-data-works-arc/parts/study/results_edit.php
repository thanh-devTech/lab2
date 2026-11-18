<?php
global $wpdb, $report, $ci_cutoffs, $current_role;
global $results;
$id = $_GET['id'];

$user_id = get_current_user_id();
$checked_types = array();


$can_edit = true;
$can_delete   = true;
$can_fax      = true;
$use_style = 1;
$can_approve = true;
$is_qc_form = isset($_GET['qc']);

if ($current_role == 'results') {
	if (tr_get_option('ci_pdf_data', 'results_oview')) {
		$can_edit     = false;
		$can_delete   = false;
		$can_fax      = false;
	}
} else if ($current_role == 'lab_tech') {
	$can_approve = false;
}


if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'mf_file') {
	$meta_key = '_mfdata_' . $_GET['testid'];
	$metadata = $wpdb->get_var("select comments from ci_results_study 
    where report_id = {$id}
    and drug = '{$meta_key}' ");
	$metadata = json_decode($metadata, true);

	$file =  $metadata['file'];
	$fileurl = '/wp-content/log/results_study/' . $file;
	wp_redirect($fileurl);
	exit;
} else if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'reimport_mf') {
	$meta_key = '_mfdata_' . $_GET['testid'];
	$mf_data = array();
	$mfrow = $wpdb->get_row("select * from ci_results_study 
	where report_id = {$id}
    and drug = '{$meta_key}' ");

	if (!$mfrow) {
		$wpdb->insert('ci_results_study', array('report_id' => $id, 'drug' => $meta_key, 'has_order' => '0'));
		$mf_id = $wpdb->insert_id;
	} else {
		$mf_id = $mfrow->id;
		$mf_data = json_decode($mfrow->comments, true);
	}

	$meta = $_POST['data_results'][$mf_id]['comments'];
	foreach ($meta as $k => $vl) {
		$mf_data[$k] = $vl;
	}

	$wpdb->update('ci_results_study', array('comments' => json_encode($mf_data)), array('id' => $mf_id));


	$return = array('status' => 'ok');
	//$_GET['debug1'] = $_REQUEST['debug1'] = true;

	$metadata = $wpdb->get_var("select comments from ci_results_study 
    where report_id = {$id}
    and drug = '{$meta_key}' ");
	$metadata = json_decode($metadata, true);

	$file = $metadata['file'];
	$filepath = $log_dir . $file;
	$return['file'] = $file;

	if (file_exists($filepath)) {
		$files = array($file => array());
		include(trpdd_get_file_path('study', 'results_import_mf'));
	}

	wp_send_json($return);
	exit;
}


include_once(TRCPDD_PATH . 'pages/formdata.php');

$sql = "select ba.*, re.* from ci_batches_study ba
join ci_reports_study re ON re.patient_id = ba.assen
where re.id = {$id}";
$report = $wpdb->get_row($sql, ARRAY_A);

if (!$report['id']) {
	echo '<h2>No Results Found!</h2>';
	return;
}


$condition_selecteds = !empty($report['conditions']) ? explode(';', $report['conditions']) : array();

$conditions = array();

$all_drugs 			= array();
$in_panel_tests_requested = array();
$all_test_groups 	= array();
foreach (explode(',', $report['panel_tests_requested']) as $t) {
	$in_panel_tests_requested[] = $t;
}

$test_ids = implode(',', $in_panel_tests_requested);
if (empty($test_ids)) {
	$test_ids = '0';
}

$sql = "select te.ID, te.abrev,
te.units,
te.reported_values
from ci_study_tests te
where te.ID in ({$test_ids})  ";
$tests = $wpdb->get_results($sql, ARRAY_A);



$study 			 = $wpdb->get_row("select * from ci_study  where ID = {$report['project_ID']} ");
$study->template = json_decode($study->template, true);
if (!is_array($study->template)) {
	$study->template = array();
}
$study->time_points = json_decode($study->time_points, true);
if (!is_array($study->time_points)) {
	$study->time_points = array();
}

foreach ($study->time_points as $timepoint) {
	$timepoint['name'] = trim($timepoint['name']);
	if (strtolower($timepoint['name']) == strtolower($report['timeframe'])) {
		foreach ($timepoint['conditions'] as $condition) {

			if (empty($condition_selecteds) || in_array($condition['name'], $condition_selecteds)) {
				$conditions[$condition['name']] = array();
				foreach ($condition['tests'] as $t) {
					$conditions[$condition['name']][] = $t;
				}
			}
		}
		break;
	}
}

if (empty($condition_selecteds)) {
	$condition_selecteds = array();
	foreach ($conditions as $cname => $ctests) {
		$condition_selecteds[] = $cname;
	}
	$report['conditions'] = implode(';', $condition_selecteds);

	$wpdb->update('ci_batches_study', array('conditions' => $report['conditions']), array('mid' => $report['mid']));
}

$reportables = array();
foreach ($study->template as $arr) {

	preg_match_all("/\[([^\]]+)\]/", $arr[1], $matches);
	foreach ($matches[1] as $k) {
		$k = strtolower(trim($k));
		$reportables[] = $k;
	}
}

$rs = $wpdb->get_results("select * from ci_results_study where report_id = {$id}", ARRAY_A);
$results = array();
foreach ($rs as $i4 => $r) {
	if (stripos($r['drug'], '_mfdata') !== false) continue;

	$rskey = strtolower($r['drug']);

	$results[$rskey] = $r;

	if (!empty($r['group_id'])) {
		$rskey = $r['group_id'] . '_' . $rskey;

		$results[$rskey] = $r;
	}
}

if (empty($report['spec_received'])) {
	$report['spec_received'] = $wpdb->get_var("select created from  ci_receiving_study_batches
	where ID = {$report['receivingID']}");

	if (!empty($report['spec_received'])) {
		$wpdb->update('ci_batches_study', array('spec_received' => $report['spec_received']), array('mid' => $report['mid']));
	}
}

$req_form_url = 'admin.php?page=study_req_form&act=edit&id=' . $report['mid'];
?>

<div class="wrap">

	<h2>Edit </h2>
	<?php Tr_Session_Class::show() ?>

	<form method="post">

		<div class="frow">
			<h4>Patient Info</h4>
		</div>



		<div class="frow">
			<div class="col4">
				<label>Patient DOB</label>
				<input type="text" placeholder="YYYY-mm-dd" value="<?php echo $report['dob'] ?>" readonly>
			</div>
			<div class="col4">
				<label>Patient Gender</label>
				<input type="text" value="<?php echo $report['gender'] ?>" readonly>
			</div>
			<div class="col4">
				<label>Patient ID <a target="_blank" href="<?php echo $req_form_url ?>">.</a></label>
				<input type="text" value="<?php echo $report['sequenceno'] ?>" readonly>
			</div>
			<div class="col4">
				<label>Study</label>
				<input type="text" value="<?php echo $study->project_name ?>" readonly>
			</div>

		</div>

		<div class="frow">
			<div class="col4">
				<label>Time Point</label>
				<input type="text" value="<?php echo $report['timeframe'] ?>" readonly>
			</div>
			<div class="col4">
				<label>Barcode</label>
				<input type="text" value="<?php echo $report['assen'] ?>" readonly>
			</div>
			<div class="col4">
				<label>Collection Date</label>
				<input type="text" value="<?php echo $report['doc'] ?>" readonly>
			</div>
			<div class="col4">
				<label>Received in Lab</label>
				<input type="text" data-rid="<?php echo $report['receivingID'] ?>" value="<?php echo $report['spec_received'] ?>" readonly>
			</div>
		</div>
		<div class="frow">
			<div class="full">
				<label>Conditions</label>
				<input type="text" value="<?php echo str_replace(';', ',   ', $report['conditions']) ?>" readonly>
			</div>

		</div>
		<div class="header_fix_top"></div>


		<?php
		if (count($in_panel_tests_requested) == 0) {
		?>
			<h1 title="<?php echo count($in_panel_tests_requested) ?>" style="color: red;">No Tests Requested Otherwise</h1>
		<?php
		} else if (!empty($report['batch_status']) && ($report['batch_status'] == 'rejected' || strpos($report['batch_status'], 're_') !== false)) {
			$rejected = tr_pdd_get_batch_statuses($report['batch_status']);
		?>
			<h1 style="color:red;font-size:20px;text-align: center"><?php echo $rejected ?></h1><br>
			<div style="text-align: center;"><?php echo $report['billing_notes'] ?></div>
		<?php
		} else { ?>


			<div class="frow">
				<h4>&nbsp;</h4>
			</div>

			<table style="width:100%" class="tbl_results">
				<?php
				$irid = 0;
				$test_rejecteds = trpdd_get_batch_meta($report['mid'], 'study', 'test_rejected');

				foreach ($tests as $arr) {
					$has_mf = false;
					if ($arr['abrev'] == 'MF' || stripos($arr['abrev'], 'MF-') === 0) {
						$has_mf = true;
					}
					$reported_values = json_decode($arr['reported_values'], true);
					$arr['count_condition'] = 0;
					$arr['rejectedname'] = 'batmeta[test_rejected][' . $arr['ID'] . ']';
					$arr['rejected'] = isset($test_rejecteds[$arr['ID']]) ? $test_rejecteds[$arr['ID']] : 0;
					ob_start();

					if ($arr['abrev'] == 'Sickle Count Ave') {
						$conditions = array('Value' => array($arr['ID']));
					}
				?>
					<tr class="group_header" data-id="<?php echo $arr['ID'] ?>">
						<th style="width:250px" title="<?php echo $arr['ID'] ?>"><strong><?php echo $arr['abrev'] ?></strong></th>
						<?php
						foreach ($conditions as $condition => $testsarr) {

							if (!in_array($arr['ID'], $testsarr)) continue;
							echo '<th>' . $condition . '</th>';
							$arr['count_condition']++;
						}
						?>
						<th><strong>Units</strong></th>

						<td class="center" style="width:100px">
							<b>Rejected</b>
							<input type="hidden" name="<?php echo $arr['rejectedname'] ?>" value="0" />
							<input type="checkbox" class="reject" name="<?php echo $arr['rejectedname'] ?>" value="1" <?php checked($arr['rejected']) ?> <?php if (!$can_edit) echo 'readonly' ?> />
						</td>
					</tr>
					<?php

					$headertest = ob_get_clean();

					if ($arr['count_condition'] == 0 && $arr['abrev'] != 'Sickle Count Ave') {
						continue;
					} else {
						echo $headertest;
					}
					$av_numbers = array();
					foreach ($reported_values as $arr2) {
						$irid++;


						$drug = strtolower($arr2['v']);
						$drug1 = $arr['ID'] . '_' . $drug;


						if (isset($results[$drug1])) {
							$result = $results[$drug1];
						} else if (isset($results[$drug])) {
							$result = $results[$drug];
						} else {
							$result = array('id' => 'new_' . $irid);
						}

						if (isset($result['comments'])) {
							$result['comments'] = json_decode($result['comments'], true);
						}

						$reportclass = 'contentrow testid_' . $arr['ID'];

						if (!in_array($drug, $reportables)) {
							$reportclass .= ' notrequired ';
						}


						if (isset($arr2['c']) && !empty($arr2['c'])) {
							$calc = $arr2['c'];

							try {
								$calc = preg_replace_callback('/\[([A-Za-z0-9\-\s\=]+)\]/', function ($matches) {
									global $results;
									$rskey = strtolower(trim($matches[1]));
									return isset($results[$rskey]) ? $results[$rskey]['conc'] : '';
								}, $calc);

								$rscalc = 0;
								eval('$rscalc = ' . $calc . ';');
								$result['conc'] = $rscalc;
							} catch (Error $e) {
							}
						}

						if ($arr['rejected']) {
							$result['conc'] = '';
							$reportclass .= ' rejected ';
						}

						if ($arr2['v'] == 'Sickle Count Ave') {

							$result['conc'] = array_sum($av_numbers['value']) / count($av_numbers['value']);
						}


					?>
						<tr class="<?php echo $reportclass ?>">
							<td class="colname">
								<input type="hidden" <?php if (stripos($result['id'], 'new') !== false) echo 'name="data_results[' . $result['id'] . '][drug]"' ?> value="<?php echo $drug ?>" />
								<input type="hidden" <?php echo 'name="data_results[' . $result['id'] . '][group_id]"' ?> value="<?php echo $arr['ID'] ?>" />
								<input type="text" value="<?php echo $arr2['v'] ?>" readonly />
							</td>
							<?php
							$values 	= json_decode($result['conc'], true);

							foreach ($conditions as $condition => $testsarr) {
								if (!in_array($arr['ID'], $testsarr)) continue;

								$condition  = strtolower(substr(str_replace(array(' ', ']', '['), '', $condition), 0, 35));
								if (empty($condition)) {
									$condition = 0;
								}


								$fieldname 	= 'data_results[' . $result['id'] . '][conc][' . $condition . ']';


								if (!is_array($values)) {
									$values = array(
										$condition => $result['conc'],
									);
								}
								$value 			=  isset($values[$condition]) ? $values[$condition] : '';

								if ($arr2['v'] != 'Sickle Count Ave') {
									$av_numbers[$condition][] = $value;
								}
							?>
								<td>
									<input type="text" class="conc" name="<?php echo $fieldname ?>" title="<?php echo $arr2['c'] ?>" value="<?php echo $value ?>" <?php if (!$can_edit) echo 'readonly' ?> />
								</td>
							<?php
							}
							?>
							<td>
								<?php echo $arr2['u'] ?>
							</td>
							<td></td>
						</tr>
					<?php
					}

					if ($has_mf) {
						$meta_key = '_mfdata_' . $arr['ID'];
						$mf_fields      = array('i12' => 1, 'j12' => 100, 'k12' => 2, 'l12' => 200, 'm12' => 3, 'n12' => 300);
						$vmax_fields    = array('f9' => 'Hb', 'k3' => 'Zero (undiluted)');
						$mf_data        = array();
						$mfrow          = $wpdb->get_row("select * from ci_results_study where report_id = {$id}
																and drug = '{$meta_key}' ");
						if (!$mfrow) {
							$wpdb->insert('ci_results_study', array('report_id' => $id, 'drug' => $meta_key, 'has_order' => '0'));
							$mf_id = $wpdb->insert_id;
						} else {
							$mf_id = $mfrow->id;
							$mf_data = json_decode($mfrow->comments, true);
						}
					?>
						<tr>
							<td colspan="3">
								<br>
								<div class="frow">
									<h4 class="center" style="width:500px;margin:0 auto">NanoDrop <a target="_blank" href="<?php Tr_Base_Class_V6::link(array('trajax' => 'mf_file', 'testid' => $arr['ID'])) ?>">.</a></h4>
								</div>
								<div style="width:300px;margin:0 auto">
									<?php
									if (!empty($mf_data['auto_excl_tube'])) {
										echo '<div class="center">';
										echo 'Auto Exclude Tube ' . $mf_data['auto_excl_tube'];
										echo '</div>';
									}

									?>
									<select name="<?php echo 'data_results[' . $mf_id . '][comments][excl_tube]' ?>">
										<option value="">Select Exclude Tube:</option>
										<?php
										for ($i = 1; $i <= 3; $i++) {
											$selected = $mf_data['excl_tube'] == $i ? 'selected' : '';
											echo '<option value="' . $i . '" ' . $selected . '>Tube ' . $i . '</option>';
										}
										?>
									</select>
								</div>
								<table class="wp-list-table widefat fixed striped posts" style="margin:10px auto;width:500px">
									<thead>
										<tr>
											<?php
											foreach ($vmax_fields as $mff) {
												echo '<th class="center">' . $mff . '</th>';
											}
											?>
											<th>Dilution Calculation</th>
										</tr>
										<tr>
									</thead>
									<tr>
										<?php
										foreach ($vmax_fields as $vk => $mff) {
											echo '<td><input type="text" id="field_' . $vk . '" data-required="true" name="data_results[' . $mf_id . '][comments][' . $vk . ']" value="' . (isset($mf_data[$vk]) ? $mf_data[$vk] : '') . '"></td>';
										}

										echo '<td><input type="text" data-calculator="3000*0.5/[field_f9]" readonly value="';
										if (($mf_data['f9']) > 0) echo round(3000 * 0.5 / $mf_data['f9'], 3);
										echo '"></td>';
										?>
									</tr>
								</table>

								<?php
								if (!empty($mf_data['rerunby'])) {
									echo '<div class="center" style="color:red">';
									echo $mf_data['rerunby'];
									echo '</div>';
								}

								?>

								<br>

								<table class="wp-list-table widefat fixed striped posts" style="margin:10px auto;width:500px">
									<thead>
										<tr>
											<?php
											foreach ($mf_fields as $mff) {
												echo '<th class="center">' . $mff . '</th>';
											}
											?>
										</tr>
										<tr>
									</thead>
									<tr>
										<?php
										foreach ($mf_fields as $mfk => $mff) {
											echo '<td><input type="text" data-required="true" name="data_results[' . $mf_id . '][comments][' . $mfk . ']" value="' . (isset($mf_data[$mfk]) ? $mf_data[$mfk] : '') . '"></td>';
										}
										?>
									</tr>
								</table>
								<br><br>

							</td>
						</tr>
				<?php
					}
				}
				?>

			</table>

		<?php
		} //end rejected

		?>


		<div class="frow">
			<h4>Status</h4>
			<div class="col3">
				<?php
				$status_options = tr_pdd_get_batch_statuses_on_hold(tr_pdd_get_batch_statuses(false, false, false, 'study'), $report['batch_status'], $report['mid'], $table_batch, 'study');
				$disabled_keys = tr_pdd_get_batch_statuses_disabled_keys('study', 'show_in_result_form', $report['batch_status']);
				tr_select_option('updatebat[batch_status]', $status_options, @$report['batch_status'], ['disabled_options' => $disabled_keys]);
				?>
			</div>
		</div>


		<div class="frow">
			<h4>Notes (Show on Report)</h4>
		</div>
		<div class="frow" title="<?php echo $count_test_show ?>">
			<div class="">
				<textarea name="updatebat[notes_report]" <?php if (!$can_edit) echo 'readonly' ?>><?php echo stripslashes($report['notes_report']) ?></textarea>
			</div>
		</div>



		<div class="frow">
			<h4>Internal Notes</h4>
		</div>
		<div class="frow" title="<?php echo $count_test_show ?>">
			<div class="">
				<textarea name="updatebat[internal_notes]" <?php if (!$can_edit) echo 'readonly' ?>><?php echo stripslashes($report['internal_notes']) ?></textarea>
			</div>
		</div>
		<br>

		<div class="frow">
			<label style="margin-right:20px"><input type="radio" name="info[final_results]" value="0" <?php checked($report['final_results'], '0') ?> <?php if (!$can_edit) echo 'disabled' ?> /> None </label>
			<label style="margin-right:20px"><input type="radio" name="info[final_results]" value="2" <?php checked($report['final_results'], '2') ?> <?php if (!$can_edit) echo 'disabled' ?> /> Preliminary </label>
			<label style="margin-right:20px"><input type="radio" name="info[final_results]" value="1" <?php checked($report['final_results'], '1') ?> <?php if (!$can_edit) echo 'disabled' ?> /> Final </label>
			<label><input type="radio" name="info[final_results]" value="3" <?php checked($report['final_results'], '3') ?> <?php if (!$can_edit) echo 'disabled' ?> /> Amended Report </label>
		</div>


		<div class="frow">
			<?php
			$next = tr_pdd_next_result($id, $report['project_ID'], $report['lab_id'], 'study');
			if ($can_edit) {
				echo ' <input type="submit" name="submit" value="Submit" class="button-primary" /> ';
			}
			if ($next > 0) {
				echo ' <input type="submit" name="submit_next" value="Submit & Next" class="button"> ';
			}

			if ($can_approve && $report['estatus'] != 'C') {
				echo '<input type="submit" name="approval" value="Approve" class="button approve_btn">';
			}
			if ($next > 0) {
				$next_url = Tr_Base_Class_V6::link(array('id' => $next), false);
			?>
				<input type="hidden" name="next_link" value="<?php echo $next_url ?>" />

				<input type="submit" <?php if (!$can_approve) echo 'style="display:none"' ?> name="approval_next" value="Approve & Next" class="button approve_btn">

				<a href="<?php echo $next_url ?>" class="button">Next</a>
			<?php
			}
			?>



			<input type="hidden" name="tr_action" value="ci_save_result_edit" />
			<input type="hidden" name="btype" value="study" />
			<input type="hidden" id="pop_msg" value="<?php echo $pop_msg ?>" />
			<input type="hidden" name="id" value="<?php echo $report['id'] ?>" />

			<?php trpdd_form_unique_id() ?>
		</div>
	</form>
</div>




<div id="popup_selecter" style="display: none;"></div>
<style>
	input.conc,
	input.cutoff {
		width: 80px;
	}

	tr.group_header {
		background: #ddd;
		height: 60px;
	}

	tr.notrequired td input[type="text"] {
		background: #f5f5f5 !important;
	}

	tr.rejected td {
		background: #e7e7e7 !important;
	}

	tr.rejected td input[type="text"] {
		background: #e7e7e7 !important;
	}

	.rowline .frow {
		border-bottom: 1px solid #ddd;
		padding-bottom: 4px;
		padding-top: 4px;
		margin-bottom: 0;
	}

	.in_medications {
		background: #80d4ff
	}

	.in_medications input,
	.in_medications textarea {
		background: rgba(128, 212, 255, 0.9);
	}

	.bg_red,
	.bg_red input,
	.bg_red textarea {
		background: #ff8080;
	}
</style>

<script>
	var $need_final = <?php echo ($current_role == 'results') ? 'true' : 'false' ?>;
	var $rejecteds = <?php echo json_encode(tr_pdd_where_batch_rejected(true, false, 'study')) ?>;
	jQuery(function($) {
		$('body').on('change', '[name="updatebat[batch_status]"]', function() {
			var $vl = $(this).val();
			if ($rejecteds[$vl]) {
				$('.approve_btn').show();
			} else {
				$('.approve_btn').hide();
			}
		}).on('click', 'input.reject', function() {
			var $tbl = $(this).parents('table');
			var $tr = $(this).parents('tr');
			var $tid = $tr.data('id');
			if ($(this).is(':checked')) {
				$('tr.contentrow.testid_' + $tid, $tbl).addClass('rejected');
				$('tr.contentrow.testid_' + $tid + ' input.conc', $tbl).val('');
			} else {
				$('tr.contentrow.testid_' + $tid, $tbl).removeClass('rejected');
			}
		})
		$('.approve_btn').click(function() {
			var $pop_msg = $('#pop_msg').val();
			if ($pop_msg.length > 10 && confirm($pop_msg) == false) {
				return false;
			}
			if ($('[name="info[final_results]"]:checked').val() != '1' && $need_final) {
				alert('Status must be Final!');
				return false;
			}
		})
		$('a.show_test_panels').click(function() {
			$('.list_test_panels').toggle();
			return false;
		})
		$(window).scroll(function() {
			var $t = $(this).scrollTop();

			$f = $('.header_fix_top').offset().top;
			if ($t > $f - 20) {
				$('.header_fix').show();
			} else {
				$('.header_fix').hide();
				$('.list_test_panels').hide();
			}

		})
	})
</script>

<script>
	jQuery(function($) {
		$('a.recalculate_btn').click(function() {
			var $this = $(this);
			if ($this.html() != 'Recalculate') {
				return false;
			}
			$this.html('Calculating...');
			$.ajax({
				data: $('form#result_form').serialize() + '&trajax=reimport_mf',
				type: 'post',
				dataType: 'json',
				success: function(json) {
					$this.html('Reloading....');
					location.reload();
				}
			});
			return false;
		})
	})
</script>