<?php

global $wpdb;
global $current_role;
global $is_ci_admin;

$btype				= 'study';
$table_batch        = 'ci_batches_study';
$id                 = isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;

$exist_cols         = $wpdb->get_col("explain ci_batches_study");

$tabs = array(
	'' => 'Study Requisition Form',
	//'temp_batches' => 'Temp Batches',
	'completed_req' => 'Completed Req Forms',
	//'req_hold' => 'Reqs On Hold',
	//	'saved_form' => 'Saved'
);


if (!tr_get_option('ci_pdf_data', 'req_show_hold')) {
	unset($tabs['req_hold']);
}

$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);
if (isset($_REQUEST['trajax'])) {
	$hide_tabs = true;
}
if (!isset($hide_tabs) || $hide_tabs === false) {

	if ($act == 'print') {
?>
		<link href="<?php echo TRCPDD_URL ?>css/print.min.css?<?php echo TRCPDD_VS ?>" media="print" type="text/css" rel='stylesheet' />
	<?php
	} else if (!isset($_REQUEST['not_show_tab'])) {

		include_once(TRCPDD_PATH . 'pages/formdata.php');
	?>
		<h2 class="nav-tab-wrapper">
			<?php

			foreach ($tabs as $k => $txt) {
				$class = $tab == $k ? 'nav-tab-active' : '';
				$link = Tr_Base_Class_V6::link(array('tab' => $k, 'act' => '', 'id' => '', 'mid' => '', 'paged' => '', 's' => ''), false);
				echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
			}
			?>
		</h2>

<?php
	}
}

if ($tab == 'completed_req') {
	include(TRCPDD_PATH . 'parts/study/req_list.php');
	return;
} else if ($tab == 'saved') {
	include(TRCPDD_PATH . 'parts/study/req_saved.php');
	return;
} else if (!empty($tab)) {
	$tabfile = trpdd_get_file_path($btype, $tab);
	if (file_exists($tabfile)) {
		include($tabfile);
		return;
	}
}


if (isset($_POST['post_act']) && $_POST['post_act'] == 'save' && !trpdd_check_form_unique_id()) {
	$is_saved_form = false;
	$batch = tr_pdd_stripslashes_deep($_POST['bat']);

	if (empty($batch['doc']) || $batch['doc'] == 'auto') {
		$batch['doc'] = date_i18n('Y-m-d H:i:s');
	}
	$batch['batch_id'] = (int) date('Ymd', strtotime($batch['doc']));

	if (isset($batch['assen'])) {
		$batch['assen'] = trim($batch['assen']);
	}
	foreach ($batch as $k => $vl) {
		if (stripos($k, '_json') > 0) {
			unset($batch[$k]);
			$newk = str_replace('_json', '', $k);
			$batch[$newk] = json_encode($vl);
		} else if (is_array($vl) && !in_array($k, array('panel_tests_requested', 'conditions'))) {
			$batch[$k] = implode('|', $vl);
		}
	}
	if (isset($batch['conditions']) && is_array($batch['conditions'])) {
		$batch['conditions'] = implode(';', $batch['conditions']);
	} else if (isset($_POST['has_conditions'])) {
		$batch['conditions'] = '';
	}

	if (isset($batch['timeframe']) && !empty($batch['timeframe'])) {
		if (!isset($batch['oldreq'])) {
			$batch['oldreq'] = $wpdb->get_var("select oldreq from ci_batches_study where mid = {$batch['mid']}");
		}
		$wpdb->update('ci_receiving_study_batches', array(
			'timepoint' => $batch['timeframe']
		), array('code' => $batch['oldreq']));
	}

	if (empty($batch['lab_id'])) {
		$batch['lab_id'] = CI_Base_Lab::get_current_lab_id();
	}


	if (empty($batch['assen']) && isset($_REQUEST['auto_generated'])) {
		$barcode_key = $wpdb->get_var("select short_name from ci_study where ID = {$batch['project_ID']}");

		$batch['assen'] = tr_get_new_assen('study', false, $barcode_key);
	}

	if (!is_array($batch['panel_tests_requested'])) {
		$batch['panel_tests_requested'] = array();
	}
	$batch['panel_tests_requested'] = implode(',', $batch['panel_tests_requested']);



	//save $original_req

	$signature_id = trim(strtolower($batch['assen']));
	if (empty($signature_id)) {
		$signature_id = 'batch_' . time();
	}
	if (isset($batch['original_req'])) {
		$original_req = tr_save_multi_result_upload(
			$signature_id . 'original',
			$batch['original_req'],
			isset($_FILES['original_reqs']) ? $_FILES['original_reqs'] : null,
			isset($_FILES['original_req']) ? $_FILES['original_req'] : null
		);
		if ($original_req) {
			$batch['original_req'] = $original_req;
		}
	}


	foreach ($batch as $k => $vl) {
		if (!in_array($k, $exist_cols)) {
			$wpdb->query("alter table {$table_batch} add column `{$k}` text;");
		}
	}
	if ($batch['mid'] > 0) {
		$rs = $wpdb->update($table_batch, $batch, array('mid' => $batch['mid']));
		if ($rs !== false) {
			Tr_Session_Class::add('Updated Study Successfully!');
			$batch = array();
		} else {
			Tr_Session_Class::add_error('Error. Please try again!' . $wpdb->last_error);
		}
		$act = 'edit';
	} else {
		$batch['batch_created'] = date_i18n('Y-m-d H:i:s');
		$batch['author_ID'] = get_current_user_id();
		$rs = $wpdb->insert($table_batch, $batch);
		$batch['mid'] = $wpdb->insert_id;
		if ($rs !== false) {
			$edit_link = 'admin.php?page=' . $_GET['page'] . '&act=edit&id=' . $batch['mid'];
			if (isset($_GET['tab'])) {
				$edit_link .= '&tab=' . $_GET['tab'];
			}
			if ($is_saved_form) {
				$edit_link = 'admin.php?page=' . $_GET['page'] . '&tab=saved_form&act=edit&id=' . $batch['mid'];
			}
			Tr_Session_Class::add('Added new Study Successfully!  <a href="' . $edit_link . '">Edit</a>');
			$batch = array();
		} else {
			Tr_Session_Class::add_error('Error. Please try again!' . $wpdb->last_error);
		}
	}

	trpdd_log_form('save_study');
}



$projects = $wpdb->get_results("select * from ci_study where istatus=1 order by project_name asc");

if ($act == 'edit' && $id > 0) {
	$bat = $wpdb->get_row("select * from ci_batches_study where mid = {$id}", ARRAY_A);
	$reba = null;
	$panel_by_receiving = null;
	if (!empty($bat['oldreq'])) {
		$reba = $wpdb->get_row("select * from ci_receiving_study_batches rb
    	where code = '{$bat['oldreq']}' order by ID desc");
	}
	if ($reba) {
		$reba->study_assigned = explode(',', $reba->study_assigned);
		$reba->condition = explode(',', $reba->condition);
		$reba->timepoint = explode(',', $reba->timepoint);
		$studyind = array_search($bat['project_ID'], $reba->study_assigned);
		//var_dump($bat['project_ID']);
		//var_dump($reba->study_assigned);
		if ($studyind !== false) {
			$condition_selecteds = explode(';', $reba->condition[$studyind]);
			$condition_selecteds = array_map('strtolower', $condition_selecteds);

			$timepoint_selected = $reba->timepoint[$studyind];
			$timepoint_selected = trim($timepoint_selected);

			$tests = array();
			$study = $wpdb->get_row("select * from ci_study where ID = {$bat['project_ID']}");
			$time_points = json_decode($study->time_points, true);
			foreach ($time_points as $timepoint) {
				$timepoint['name'] = trim($timepoint['name']);

				if (strtolower($timepoint['name']) == strtolower($timepoint_selected)) {
					$bat['list_conditions'] = $timepoint['conditions'];
					foreach ($timepoint['conditions'] as $condition) {
						if (in_array(strtolower($condition['name']), $condition_selected)) {
							$tests[] = implode(',', $condition['tests']);
						}
					}
				}
			}
			$panel_by_receiving = $tests;
		}
	}

	if (empty($bat['panel_tests_requested']) && !empty($panel_by_receiving)) {
		//find in receiving
		$bat['panel_tests_requested'] = $panel_by_receiving;
		$wpdb->update('ci_batches_study', array('panel_tests_requested' => implode(',', $panel_by_receiving)), array('mid' => $id));
	}
	if (empty($bat['panel_tests_requested'])) {
		$bat['panel_tests_requested'] = array();
	} else {
		$bat['panel_tests_requested'] = explode(',', $bat['panel_tests_requested']);
	}
} else if (empty($bat)) {
	$bat = array();
	$bat['panel_tests_requested'] = array();
}
?>

<div class="wrap">
	<h2 class="center">Study Req Form</h2>
	<?php Tr_Session_Class::show() ?>
	<br><br>

	<form method="post" id="requisition_form" enctype="multipart/form-data" class="need_validate">
		<div class="frow">
			<div class="col2">
				<h4>Study</h4>
				<select name="bat[project_ID]" required>
					<option value="">Select</option>
					<?php
					foreach ($projects as $project) {
						$selected = $project->ID == $bat['project_ID'] ? 'selected' : '';
						echo '<option value="' . $project->ID . '" ' . $selected . ' >' . $project->project_name . '</option>';
					}
					?>
				</select>
			</div>
			<div class="col2">
				<h4>Collection Date</h4>
				<input type="text" class="hasdatetime" name="bat[doc]" value="<?php echo $bat['doc'] ?>" />
			</div>
		</div>
		<div class="frow">
			<div class="col2">
				<label>Phase</label>
				<select name="bat[phase]">
					<?php
					foreach (array('NA', '0', '1', '2', '3', '4') as $k) {
						$selected = $bat['phase'] === $k ? 'selected' : '';
						if ($bat['phase'] === '' && $k === 'NA') {
							$selected = 'selected';
						}
						echo '<option value="' . $k . '" ' . $selected . '>' . $k . '</option>';
					}
					?>
				</select>
			</div>
			<div class="col2">
				<?php if (isset($time_points) && count($time_points) > 0) { ?>
					<label>Timepoint</label>
					<select name="bat[timeframe]">
						<?php
						foreach ($time_points as $tp) {
							$selected = $tp['name'] == $bat['timeframe'] ? 'selected' : '';
							echo '<option value="' . $tp['name'] . '" ' . $selected . '>' . $tp['name'] . '</option>';
						}
						?>
					</select>
				<?php } ?>
				<label>Condition</label>
				<?php if ($is_ci_admin && isset($bat['list_conditions'])) { ?>
					<select name="bat[conditions][]" multiple class="js_select2">
						<?php
						$bat['conditions_arr'] = explode(';', $bat['conditions']);
						foreach ($bat['list_conditions'] as $condition) {
							$selected = in_array($condition['name'], $bat['conditions_arr']) ? 'selected' : '';
							echo '<option value="' . $condition['name'] . '" ' . $selected . '>' . $condition['name'] . '</option>';
						}
						?>
					</select>
					<input type="hidden" name="has_conditions" value="1" />
				<?php

				} else { ?>
					<input type="text" name="bat[conditions]" value="<?php echo $bat['conditions'] ?>" readonly />
				<?php } ?>
			</div>
		</div>

		<?php
		$headers  = $wpdb->get_results("select * from ci_test_aoe_headers 
			                                    where tag_btype='study' 
			                                    and tag_status = 1
			                                    order by tag_order asc");
		foreach ($headers as $header) {
			$questions = $wpdb->get_results("select * from ci_test_aoe 
				                            where ta_btype= 'study' 
				                                and ta_header_id = {$header->tag_ID}
				                                order by ta_order asc, ta_ID asc");
			if (count($questions) == 0) {
				continue;
			}
		?>
			<div class="frow">
				<h4><?php echo $header->tag_header ?></h4>
			</div>

			<?php
			echo '<div class="frow">';
			$last_col_num = 0;
			$last_type = '';
			$group_adding = '';
			$cnum = 0;
			foreach ($questions as $question) {
				$col_num = 3;

				if (in_array($question->ta_question_type, array('texta', 'cond2', 'cond4', 'cond8'))) {
					$col_num = 1;
				}

				$value = $bat[$question->ta_slug];
				$name = 'bat[' . $question->ta_slug . ']';

				if ($col_num == 1) {
					echo '</div><div class="frow full">' . "\n";
				} else {
					if ($cnum > 0 && ($cnum % $col_num == 0 || ($last_col_num > 0 && $last_col_num != $col_num))) {
						echo '</div><div class="frow">' . "\n";
					} else if ($question->ta_question_type == 'cb2' && $last_type != '' && $last_type != $question->ta_question_type) {
						echo '</div><div class="frow cb2">' . "\n";
						$cnum = 0;
					}
					echo '<div class="col' . $col_num . '">' . "\n";
				}

				if (!in_array($question->ta_question_type, array('checkbox', 'cb2', 'cond4', 'cond2', 'cond8'))) {
					echo '<label>' . $question->ta_question . '</label><br>' . "\n";
				}

				$cnum++;
				$last_col_num   = $col_num;
				$last_type      = $question->ta_question_type;

				switch ($question->ta_question_type) {
					case 'select':
					case 'radio':
						$type = 1;
						if (strpos($question->ta_answers, "\r") > 0) {
							$options = explode("\r", $question->ta_answers);
							$type = 2;
						} else {
							$options = explode('|', $question->ta_answers);
						}

						echo '<select name="' . $name . '">';
						echo '<option value="">Select</option>';
						foreach ($options as $vl) {
							list($k, $txt) = explode(',', $vl);
							if (empty($txt)) $txt = $k;
							$k = trim($k);
							$selected = $value == $k ? 'selected' : '';
							echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
						}
						echo '</select>' . "\n";
						break;

					case 'mselect':
						$type = 1;
						if (strpos($question->ta_answers, "\r") > 0) {
							$options = explode("\r", $question->ta_answers);
							$type = 2;
						} else {
							$options = explode(',', $question->ta_answers);
						}
						$adding_slug = $question->ta_slug . '_data';
						$adding_name = 'bat[' . $adding_slug . '_json]';
						$adding_value = '';
						if ($question->ta_start_end_date) {
							$adding_value = json_decode($bat[$adding_slug], true);
							if (!in_array($adding_slug, $exist_cols)) {
								$wpdb->query("alter table ci_batches_study add column {$adding_slug} text");
							}
						}

						echo '<select name="' . $name . '[]" data-slug="' . $question->ta_slug . '" multiple class="js_select2 ' . ($question->ta_start_end_date ? 'has_adding_mselect' : '') . '">';
						$values = explode('|', $value);
						foreach ($options as $txt) {
							$txt = trim($txt);
							$selected = in_array($txt, $values) ? 'selected' : '';
							echo '<option value="' . $txt . '" ' . $selected . '>' . $txt . '</option>';

							if ($question->ta_start_end_date) {
								$group_adding .= '<div class="col3 adding_mselect mselect_' . $question->ta_slug . '" data-value="' . $txt . '">';
								$group_adding .= '<fieldset>';
								$group_adding .= '<legend><label>' . $txt . ' &nbsp;&nbsp; ';
								$group_adding .= '</label></legend>';
								$group_adding .= '<div class="frow">';
								$group_adding .= '<div class="col2">';
								$group_adding .= '<label>Start</label>';
								$group_adding .= '<input type="text" class="hasdate" name="' . $adding_name . '[' . $txt . '][0]" value="' . $adding_value[$txt][0] . '"/> ';
								$group_adding .= '</div>';
								$group_adding .= '<div class="col2">';
								$group_adding .= '<label>End</label>';
								$group_adding .= '<input type="text" class="hasdate" name="' . $adding_name . '[' . $txt . '][1]" value="' . $adding_value[$txt][1] . '"/> ';
								$group_adding .= '</div>';
								$group_adding .= '</div>';
								$group_adding .= '</div>';

								if ($txt == 'Other') {
									$group_adding .= '<div class="col3 adding_mselect mselect_' . $question->ta_slug . '" data-value="' . $txt . '">';
									$group_adding .= '<fieldset>';
									$group_adding .= '<legend><label>' . $txt . ' &nbsp;&nbsp; ';
									$group_adding .= '</label></legend>';
									$group_adding .= '<div class="frow">';
									$group_adding .= '<input type="text" name="' . $adding_name . '[' . $txt . '][Other]" value="' . $adding_value[$txt]['Other'] . '"/> ';
									$group_adding .= '</div>';
									$group_adding .= '</div>';
								}
							}
						}
						echo '</select>' . "\n";

						break;

					case 'checkbox':
						echo '<input type="hidden" name="' . $name . '" value="0"/>';
						echo '<label> <input type="checkbox" name="' . $name . '" value="1" ' . checked($value, '1', false) . '/> ';
						echo $question->ta_question;
						echo '</label>' . "\n";
						break;

					case 'cb2':
						$values = explode('|', $value);

						echo '<fieldset>';
						echo '<legend><label>' . $question->ta_question . ' &nbsp;&nbsp; ';
						echo '<input type="hidden" name="' . $name . '[0]" value="0"/>';
						echo '<input type="checkbox" name="' . $name . '[0]" value="1" ' . checked($values[0], '1', false) . '/> ';
						echo '</label></legend>';
						echo '<div class="frow">';
						echo '<div class="col2">';
						echo '<label>#</label>';
						echo '<input type="text"  name="' . $name . '[1]" value="' . $values[1] . '"/>' . "\n";
						echo '</div>';
						echo '<div class="col2">';
						echo '<label>Date</label>';
						echo '<input type="text" class="hasdate" name="' . $name . '[2]" value="' . $values[2] . '"/>' . "\n";
						echo '</div>';

						echo '</div>';
						echo '</fieldset>';
						break;

					case 'date':
						echo '<input type="text" class="hasdate" name="' . $name . '" value="' . $value . '"/>' . "\n";
						break;
					/*
						case 'radio':
							$options = explode('|',$question->ta_answers);
							foreach($options as $vl)
							{
								list($k,$txt) = explode(',',$vl);
								$k = trim($k);
								$selected = $value==$k?'checked':'';
								echo ' <label><input type="radio" name="'.$name.'" value="'.$k.'" '.$selected.'/> '.$txt.'</label> ';
							}
							break;*/

					case 'texta':
						echo '<textarea name="' . $name . '">' . $value . '</textarea>' . "\n";
						break;

					case 'textu':
						$values = explode('|', $value);
						if (empty($values[1])) {
							$values[1] = $question->ta_answers;
						}
						echo '<input type="text" name="' . $name . '[0]" value="' . $values[0] . '" style="width:60%"/>';
						echo '<input type="text" name="' . $name . '[1]" value="' . $values[1] . '" style="width:35%"/>';
						break;

					case 'cond2':
						$values = explode('|', $value);
						$options = explode('|', $question->ta_answers);
						for ($j = 0; $j < 2; $j++) {
							echo '<div class="col4">';
							if (count($options) >= 2) {
								echo '<label>' . $options[$j] . '</label>';
							}
							echo '<input type="text" name="' . $name . '[' . $j . ']" value="' . $values[$j] . '"/>';
							echo '</div>';
						}
						echo '<div class="col4">&nbsp;</div>' . "\n";
						break;

					case 'cond4':
						$values = explode('|', $value);
						$options = explode('|', $question->ta_answers);

						if (count($options) < 4) {
							echo '<label><b>' . $question->ta_question . '</b></label></div><div class="frow">';
						}

						$key = 0;
						for ($i = 1; $i <= 2; $i++) {
							echo '<div class="col2 ">';
							echo '<fieldset>';
							$col2_label = '';
							if (count($options) == 2) {
								$col2_label = $options[$i - 1];
								echo '<legend>' . $col2_label . '</legend>';
							}
							echo '<div class="frow">';
							for ($j = 1; $j <= 2; $j++) {
								echo '<div class="col2">';
								echo '<label>' . ($j == 1 ? 'AUC' : 'SD') . '</label>';
								echo '<input type="text" name="' . $name . '[' . $key . ']" value="' . $values[$key] . '"/>';
								echo '</div>';
								$key++;
							}
							echo '</div>';
							echo '</fieldset>';
							echo '</div>' . "\n";
						}

						break;

					case 'cond8':
						$values = explode('|', $value);
						$options = explode('|', $question->ta_answers);
						if (count($options) < 8) {
							echo '<label><b>' . $question->ta_question . '</b></label></div><div class="frow full2">' . "\n";
						}

						$key = 0;
						for ($i = 1; $i <= 2; $i++) {
							echo '<div class="col2 ">';
							echo '<fieldset>';
							$col2_label = '';
							if (count($options) == 2) {
								$col2_label = $options[$i - 1];
								echo '<legend>' . $col2_label . '</legend>';
							}
							echo '<div class="frow">';
							for ($j = 1; $j <= 4; $j++) {
								$label = '&nbsp;';
								if (in_array($col2_label, array('Normoxia', 'Hypoxia'))) {
									if ($j % 4 == 1) {
										$label = 'AUC';
									} else if ($j % 4 == 3) {
										$label = 'SD';
									}
								}

								echo '<div class="col4">';
								//echo '<label>'.$label.'</label>';
								echo '<input type="text" name="' . $name . '[' . $key . ']" value="' . $values[$key] . '"/>';
								echo '</div>';

								$key++;
							}
							echo '</div>';
							echo '</fieldset>';
							echo '</div>' . "\n";
						}


						break;

					case 'calc_age':
						if (!empty($bat['dob']) && $bat['dob'] != '0000-00-00') {
							$value = trdd_dob2ages($bat['dob'], $bat['doc']);
						}

						echo '<input type="number" readonly class="calc" name="' . $name . '" value="' . $value . '"/>';
			?>
						<script>
							jQuery(function($) {
								$('[name="bat[doc]"],[name="bat[dob]"]').change(function() {
									var $dob = $('[name="bat[dob]"]').val();
									var $doc = $('[name="bat[doc]"]').val();
									var $age = trdd_dob2ages($dob, $doc);
									console.log($age);
									$('<?php echo '[name="' . $name . '"]' ?>').val($age);
								})
							})
						</script>
		<?php
						break;

					default:
						echo '<input type="text" name="' . $name . '" value="' . $value . '"/>';
						break;
				}
				if ($col_num > 1) {
					echo '</div>' . "\n"; //end col
				}
			}
			echo '</div>' . "\n"; //end row

			if (!empty($group_adding)) {
				echo '<div class="frow">';
				echo $group_adding;
				echo '<div>';
			}
		}
		?>

		<div class="frow">
			<h4>Tests</h4>
		</div>
		<div class="frow">
			<?php
			$tests = $wpdb->get_results("select te.* from ci_study_tests te  ");
			foreach ($tests as $test) {
				$checked = in_array($test->ID, $bat['panel_tests_requested']);
			?>
				<div class="col4 test_item test_<?php echo $test->ID ?>">
					<label><input type="checkbox" name="bat[panel_tests_requested][]" value="<?php echo $test->ID ?>" <?php checked($checked) ?> />
						<?php echo $test->abrev ?> </label>
				</div>
			<?php
			}
			?>
		</div>

		<div class="frow">
			<h4>NOTES</h4>
		</div>
		<div class="frow">
			<textarea name="bat[notes]" <?php if ($act != 'print') echo 'placeholder="Please enter any ' . (tr_get_option('ci_pdf_data', 'cht_physicalnotes') ? 'Chief Complaint' : 'Physician Notes') . '"' ?>><?php if (isset($bat['notes'])) echo @$bat['notes'] ?></textarea>
		</div>

		<div class="frow">
			<div class="col2">
				<label>Status</label>
				<?php
				$status_options = tr_pdd_get_batch_statuses_on_hold(tr_pdd_get_batch_statuses(false, false, false, 'study'), $bat['batch_status'], $bat['mid'], $table_batch, 'study');
				$disabled_keys = tr_pdd_get_batch_statuses_disabled_keys('study', 'show_in_req_form', $bat['batch_status']);
				tr_select_option('bat[batch_status]', $status_options, @$bat['batch_status'], ['disabled_options' => $disabled_keys]);
				?>
			</div>
			<div class="col2">
				<?php trpdd_render_req_upload_files('original_req', isset($bat['original_req']) ? $bat['original_req'] : '', 'Upload Paper Req'); ?>
			</div>
		</div>


		<div class="frow">
			<h4>&nbsp;</h4>
		</div>
		<?php if (tr_get_option('ci_pdf_data', 'show_pid_field')) { ?>
			<div class="frow">
				<span class="ilabel">
					<label style="display:inline-block;width:120px">Sample Code</label>
					<input type="text" style="width:300px" name="bat[oldreq]" value="<?php echo $bat['oldreq'] ?>" <?php if (tr_get_option('ci_pdf_data', 'oldreq_required')) echo 'required' ?> />
				</span>
			</div>
		<?php } ?>
		<div class="frow row" style="margin-bottom:15px;">

			<div class=" foutrow ilabel">
				<?php if ($act != 'print' && $act != 'view' && !$billing_page) { ?>
					<span id="show_assen">
						<label>Accession Number</label>
						<input type="text" id="number_assen" name="bat[assen]" value="<?php echo @$bat['assen'] ?>" <?php if ($assen_readonly) echo 'readonly' ?> required />
						<label>Confirm</label>
						<input type="text" id="confirm_assen" value="<?php echo @$bat['assen'] ?>" required />
						<span class="confirm_status dashicons dashicons-yes"></span>
						<a class="button print_assen_btn" style="<?php if (empty($bat['assen']) || $saved_id > 0) echo 'display:none' ?>" href="#">Print Barcode</a>
					</span>
					<?php if (empty($bat['assen'])) { ?>
						<label id="show_auto_gennerated" style="margin-left:10px">
							<input type="checkbox" name="auto_generated" value="1" checked />
							<span> Auto Generated</span>
						</label>
					<?php } ?>

				<?php } ?>

			</div>
			<div class="clear"></div>
		</div>

		<div class="frow">
			<input type="submit" class="button-primary" value="Save" />
			<input type="hidden" name="bat[mid]" value="<?php echo $bat['mid'] ?>" />
			<input type="hidden" name="post_act" value="save" />
		</div>

		<?php trpdd_form_unique_id() ?>
	</form>
</div>



<?php
if ($act == 'print') {
	if (!isset($print_canvas)) echo '<script>window.print();</script>';
} else {
?>
	<style>
		.adding_mselect {
			display: none;
		}
	</style>

	<script>
		var $pa_required = false;
		var $sign_dir = '<?php echo $sign_dir['url'] ?>';
		var $t_assen = false;
		jQuery(function($) {

			$('body').on('change', '#number_assen', function() {
				var $this = $(this);
				var $vl = $(this).val().trim();
				var $mid = $('[name="bat[mid]"]').val();
				$(this).val($vl);
				if ($vl.length < 4) return;
				$('[type="submit"]').attr('disabled', 'disabled');
				if ($t_assen) {
					clearTimeout($t_assen);
				}
				$t_assen = setTimeout(function() {
					$.ajax({
						data: {
							tr_action: 'ci_check_number_assen',
							assen: $vl,
							mid: $mid,
							btype: 'study'
						},
						type: 'post',
						dataType: 'json',
						success: function(rs) {
							$t_assen = false;
							if (rs.status == 'dublicate') {
								$this.val('');
								$('#confirm_assen').val('');
								alert(rs.msg);
							}

							$('[type="submit"]').removeAttr('disabled');
						},
						error: function() {
							setTimeout(function() {
								$t_assen = false;
								$this.change();
							}, 1000);
						}
					})
				}, 500);
			}).on('change', '#attach_face_sheet, #attach_demographics', function() {
				var $has_vl = $('#attach_face_sheet').val() != '' || $('#attach_demographics').val() != '';
				if ($has_vl) {
					change_patient_insu_required(false);
				} else {
					change_patient_insu_required(true);
				}
			}).on('change', '[name="bat[project_ID]"]', function() {
				var $tests = $('option:selected', $(this)).data('tests');
				$('.test_item').show();
				if ($tests && $tests != '') {
					$tests = $tests.split(',');
					if ($tests.length > 0) {
						$('.test_item').hide();
						for (i in $tests) {
							var t = $tests[i];
							$('.test_' + t).show();
						}
					}
				}
			}).on('click', '.only_save_form', function() {
				$('form [required]').removeAttr('required');
			}).on('change', 'select#col_id_sel', function() {
				var $phone = $('option[value="' + $(this).val() + '"]', $(this)).data('phone');
				$('[name="col[col_phone]"]').val($phone);
			}).on('click', '[name="auto_generated"]', function() {
				if ($(this).is(':checked')) {
					$('#number_assen,#confirm_assen').removeAttr('required').val('').attr('readonly', '1');
				} else {
					$('#number_assen,#confirm_assen').attr('required', 'required').removeAttr('readonly');
				}
			}).on('change', '[name="bat[assen]"]', function() {
				if ($(this).val() != '') {
					$('#show_auto_gennerated').hide();
				} else {
					$('#show_auto_gennerated').show();
				}
			}).on('click', '.groupparent input.cb_test', function() {
				var $pl = $('.groupparent.parent_' + $(this).val().toLowerCase() + ' .group_label input');
				if ($(this).is(':checked')) {
					$pl.prop('checked', true).change();
				} else {
					$pl.prop('checked', false).change();
				}
			}).on('change', 'select.has_adding_mselect', function() {
				var $this = $(this);
				var $target = $(this).data('slug');
				$('.adding_mselect.mselect_' + $target).each(function() {

					var $vl = $(this).data('value');
					if ($('option[value="' + $vl + '"]', $this).is(':selected')) {
						$(this).show();
					} else {
						$(this).hide();
					}
				})
			})



			$('#requisition_form').submit(function() {
				if ($('#confirm_assen').val() != $('[name="bat[assen]"]').val()) {
					alert('Please Confirm Accession Number');
					$('#confirm_assen').focus();
					return false;
				}
			});

			$('#confirm_assen,[name="bat[assen]"]').keyup(function() {
				if ($('#confirm_assen').val() != '' && $('#confirm_assen').val() == $('[name="bat[assen]"]').val()) {
					$('.confirm_status').addClass('confirmed');
					$('.confirm_status').removeClass('error');
					$('#print_assen_btn').show();
				} else if ($('[name="bat[assen]"]').val() != '') {
					$('.confirm_status').removeClass('confirmed');
					$('.confirm_status').addClass('error');
					$('#print_assen_btn').hide();
				}
			})

			$('[name="bat[project_ID]"]').change();
			$('#attach_face_sheet').change();
			$('[name="bat[assen]"]').change();
			$('select.has_adding_mselect').change();

			if ($('[name="auto_generated"]').is(":checked")) {
				$('#number_assen,#confirm_assen').removeAttr('required').val('').attr('readonly', '1');

			}

		})
	</script>
<?php
}
?>