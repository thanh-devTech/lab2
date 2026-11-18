<?php
if (empty($mid)) {
	$mid = isset($report['mid']) ? $report['mid'] : $bat['mid'];
}

$headers  = $wpdb->get_results("select * from ci_test_aoe_headers 
			                                    where tag_btype='blood' 
			                                    and tag_status = 1
			                                    order by tag_order asc");

if (count($headers) == 0) {
	return;
}

$group_selected = array();
$groupsed = is_array($bat['panel_tests_requested']) ? $bat['panel_tests_requested'] : explode(',', $bat['panel_tests_requested']);
foreach ($groupsed as $gt) {
	if (strpos($gt, 'GROUP_') === 0) {
		$group_selected[] = str_replace('GROUP_', '', $gt);
	}
}

foreach ($headers as $header) {
	$questions = $wpdb->get_results("select * from ci_test_aoe 
				                            where ta_btype= 'blood' 
				                                and ta_header_id = {$header->tag_ID}
				                                order by ta_order asc, ta_ID asc");
	if (count($questions) == 0) {
		continue;
	}
	$header_html = '<div class="questions_section" data-headerid="' . $header->tag_ID . '">';
	$header_html .= '<div class="frow" style="margin-bottom:0">';
	$header_html .= '<h4>' . $header->tag_header . '</h4>';
	$header_html .= '</div>';

	$header_html .= '<div class="frow ">';
	$count_q_showed = 0;
	$last_col_num = 0;
	$last_type = '';
	$group_adding = '';
	$cnum = 0;
	$fieldnum = 0;
	foreach ($questions as $question) {
		$has_open_column = false;
		$col_num = 3;

		if (in_array($question->ta_question_type, array('texta', 'cond2', 'cond4', 'cond8', 'mtext', 'mcheckbox'))) {
			$col_num = 1;
		}
		if ($question->ta_cols > 0) {
			$col_num = $question->ta_cols;
		}

		$value = trpdd_get_batch_meta($mid, 'blood', $question->ta_slug);

		$name = 'batmeta[' . $question->ta_slug . ']';

		if ($question->ta_question_type == 'mtext') {
			$col_num = 1;
			$cnum = 0;
		} else {
			$question_class = ' question_item ';
			if ($col_num == 1) {
				$header_html .= '</div>';
				$question_class .= ' frow full ' . $question->ta_question_type;
				$cnum = 0;
			} else {

				if ($last_col_num > 0 && ($cnum % $col_num == 0 || ($last_col_num > 0 && $last_col_num != $col_num))) {
					$header_html .= '</div><div class="frow">' . "\n";
				} else if ($question->ta_question_type == 'cb2' && $last_type != '' && $last_type != $question->ta_question_type) {
					$header_html .= '</div><div class="frow cb2">' . "\n";
					$cnum = 0;
				}
				if ($cnum >= $col_num || $last_col_num != $col_num) {
					$cnum = 0;
				}
				$has_open_column = true;
				$question_class .= ' col' . $col_num . ' ' . $question->ta_question_type;
				$cnum++;
			}

			if (!empty($question->ta_group_ids)) {
				$groupids = explode(',', $question->ta_group_ids);
				$found = false;
				foreach ($groupids as $gid) {
					if (in_array($gid, $group_selected)) {
						$found = true;
						break;
					}
				}
				if (!$found) {
					$question_class .= ' hide_by_group ';
				} else {
					$count_q_showed++;
				}
			}


			$header_html .= '<div class="' . $question_class . '" data-key="' . $question->ta_slug . '" data-panels="' . $question->ta_group_ids . '">' . "\n";
		}

		if (!in_array($question->ta_question_type, array('checkbox', 'cb2', 'cond4', 'cond2', 'cond8', 'mtext'))) {
			$header_html .= '<label class="question questionblock">' . $question->ta_question . '</label>' . "\n";
		}

		$atts = '';
		if ($question->ta_required) {
			$atts .= ' data-required="1" ';
		}


		$last_col_num   = $col_num;
		$last_type      = $question->ta_question_type;

		switch ($question->ta_question_type) {
			case 'select':
				$type = 1;
				if (strpos($question->ta_answers, "\r") > 0) {
					$options = explode("\r", $question->ta_answers);
					$type = 2;
				} else {
					$options = explode('|', $question->ta_answers);
				}

				$header_html .= '<select name="' . $name . '" ' . $atts . '>';
				$header_html .= '<option value="">Select</option>';
				foreach ($options as $vl) {
					$vl = trim($vl);
					if (strpos($vl, ',') > 0) {
						list($k, $txt) = explode(',', $vl);
					} else {
						$k = $txt = $vl;
					}
					$k = trim($k);
					$selected = $value == $k ? 'selected' : '';
					$header_html .= '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
				}
				$header_html .= '</select>' . "\n";
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
				$adding_name = 'batmeta[' . $adding_slug . ']';
				$adding_value = '';
				if ($question->ta_start_end_date) {
					$adding_value = trpdd_get_batch_meta($mid, 'blood', $adding_slug);
				}

				$header_html .= '<select name="' . $name . '[]" data-slug="' . $question->ta_slug . '" multiple class="js_select2 ' . ($question->ta_start_end_date ? 'has_adding_mselect' : '') . '" ' . $atts . '>' . "\n";
				$values = explode('|', $value);
				foreach ($options as $txt) {
					$txt = trim($txt);
					$selected = in_array($txt, $values) ? 'selected' : '';
					$header_html .= '<option value="' . $txt . '" ' . $selected . '>' . $txt . '</option>';

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
				$header_html .= '</select>' . "\n";

				break;

			case 'checkbox':
				$header_html .= '<input type="hidden" name="' . $name . '" value="0"/>';
				$header_html .= '<label> <input type="checkbox" name="' . $name . '" value="1" ' . checked($value, '1', false) . ' ' . $atts . '/> ';
				$header_html .= $question->ta_question;
				$header_html .= '</label>' . "\n";
				break;

			case 'mcheckbox':
				$type = 1;
				if (strpos($question->ta_answers, "\r") > 0) {
					$options = explode("\r", $question->ta_answers);
					$type = 2;
				} else {
					$options = explode(',', $question->ta_answers);
				}
				$adding_value = '';


				$value = is_array($value) ? $value : array();
				foreach ($options as $txt) {
					$txt 		= trim($txt);
					$checked 	= in_array($txt, $value);
					$fieldnum++;
					if (isset($only_display_questions) && $only_display_questions && !$checked) {
						continue;
					}
					$has_other = strtolower($txt) == 'other:' || strtolower($txt) == 'other';
					$header_html .= '<label class="mcbox"> <input type="checkbox" class="' . ($has_other ? 'hasother' : '') . '" data-id="' . $fieldnum . '" name="' . $name . '[]" value="' . $txt . '" ' . ($checked ? 'checked' : '') . ' ' . $atts . '/> ';
					$header_html .= ucfirst($txt);
					$header_html .= '</label>' . "\n";

					if ($has_other) {
						$header_html .= '<label class="forother mcbox hide fieldnum_' . $fieldnum . '">';
						$header_html .= '<input type="text" name="' . $name . '[]" placeholder="' . $txt . '" />';
						$header_html .= '</label>';
					}
				}

				break;

			case 'cb2':
				$values = explode('|', $value);

				$header_html .= '<fieldset>';
				$header_html .= '<legend><label>' . $question->ta_question . ' &nbsp;&nbsp; ';
				$header_html .= '<input type="hidden" name="' . $name . '[0]" value="0"/>';
				$header_html .= '<input type="checkbox" name="' . $name . '[0]" value="1" ' . checked($values[0], '1', false) . '/> ';
				$header_html .= '</label></legend>';
				$header_html .= '<div class="frow">';
				$header_html .= '<div class="col2">';
				$header_html .= '<label>#</label>';
				$header_html .= '<input type="text"  name="' . $name . '[1]" value="' . $values[1] . '"/>' . "\n";
				$header_html .= '</div>';
				$header_html .= '<div class="col2">';
				$header_html .= '<label>Date</label>';
				$header_html .= '<input type="text" class="hasdate" name="' . $name . '[2]" value="' . $values[2] . '"/>' . "\n";
				$header_html .= '</div>';

				$header_html .= '</div>';
				$header_html .= '</fieldset>';
				break;

			case 'date':
				$header_html .= '<input type="text" class="hasdate" name="' . $name . '" value="' . $value . '"/>' . "\n";
				break;

			case 'radio':
				if (strpos($question->ta_answers, "\r") > 0) {
					$options = explode("\r", $question->ta_answers);
					$type = 2;
				} else {
					$options = explode('|', $question->ta_answers);
				}

				foreach ($options as $vl) {
					$vl = trim($vl);
					if (strpos($vl, ',') > 0) {
						list($k, $txt) = explode(',', $vl);
					} else {
						$k = $txt = $vl;
					}
					$k = trim($k);
					$selected = $value == $k ? 'checked' : '';
					$header_html .= ' <label class="rinput"><input type="radio" name="' . $name . '" value="' . $k . '" ' . $selected . ' ' . $atts . '/> ' . $txt . '</label> ';
				}
				break;

			case 'texta':
				$header_html .= '<textarea name="' . $name . '">' . $value . '</textarea>' . "\n";
				break;

			case 'textu':
				$values = explode('|', $value);
				if (empty($values[1])) {
					$values[1] = $question->ta_answers;
				}
				$header_html .= '<input type="text" name="' . $name . '[0]" value="' . $values[0] . '" style="width:60%" ' . $atts . '/>';
				$header_html .= '<input type="text" name="' . $name . '[1]" value="' . $values[1] . '" style="width:35%" ' . $atts . '/>';
				break;

			case 'cond2':
				$values = explode('|', $value);
				$options = explode('|', $question->ta_answers);
				for ($j = 0; $j < 2; $j++) {
					$header_html .= '<div class="col4">';
					if (count($options) >= 2) {
						$header_html .= '<label>' . $options[$j] . '</label>';
					}
					$header_html .= '<input type="text" name="' . $name . '[' . $j . ']" value="' . $values[$j] . '" ' . $atts . '/>';
					$header_html .= '</div>';
				}
				$header_html .= '<div class="col4">&nbsp;</div>' . "\n";
				break;

			case 'cond4':
				$values = explode('|', $value);
				$options = explode('|', $question->ta_answers);

				if (count($options) < 4) {
					$header_html .= '<label><b>' . $question->ta_question . '</b></label></div><div class="frow">';
				}

				$key = 0;
				for ($i = 1; $i <= 2; $i++) {
					$header_html .= '<div class="col2 ">';
					$header_html .= '<fieldset>';
					$col2_label = '';
					if (count($options) == 2) {
						$col2_label = $options[$i - 1];
						$header_html .= '<legend>' . $col2_label . '</legend>';
					}
					$header_html .= '<div class="frow">';
					for ($j = 1; $j <= 2; $j++) {
						$header_html .= '<div class="col2">';
						$header_html .= '<label>' . ($j == 1 ? 'AUC' : 'SD') . '</label>';
						$header_html .= '<input type="text" name="' . $name . '[' . $key . ']" value="' . $values[$key] . '"/>';
						$header_html .= '</div>';
						$key++;
					}
					$header_html .= '</div>';
					$header_html .= '</fieldset>';
					$header_html .= '</div>' . "\n";
				}

				break;

			case 'cond8':
				$values = explode('|', $value);
				$options = explode('|', $question->ta_answers);
				if (count($options) < 8) {
					$header_html .= '<label><b>' . $question->ta_question . '</b></label></div><div class="frow full2">' . "\n";
				}

				$key = 0;
				for ($i = 1; $i <= 2; $i++) {
					$header_html .= '<div class="col2 ">';
					$header_html .= '<fieldset>';
					$col2_label = '';
					if (count($options) == 2) {
						$col2_label = $options[$i - 1];
						$header_html .= '<legend>' . $col2_label . '</legend>';
					}
					$header_html .= '<div class="frow">';
					for ($j = 1; $j <= 4; $j++) {
						$label = '&nbsp;';
						if (in_array($col2_label, array('Normoxia', 'Hypoxia'))) {
							if ($j % 4 == 1) {
								$label = 'AUC';
							} else if ($j % 4 == 3) {
								$label = 'SD';
							}
						}

						$header_html .= '<div class="col4">';
						$header_html .= '<input type="text" name="' . $name . '[' . $key . ']" value="' . $values[$key] . '"/>';
						$header_html .= '</div>';

						$key++;
					}
					$header_html .= '</div>';
					$header_html .= '</fieldset>';
					$header_html .= '</div>' . "\n";
				}


				break;

			case 'calc_age':
				if (!empty($batch['dob']) && $batch['dob'] != '0000-00-00') {
					$value = trdd_dob2ages($batch['dob'], $batch['doc']);
				}

				$header_html .= '<input type="number" readonly class="calc" name="' . $name . '" value="' . $value . '"/>';
				if ($act != 'print') {
					$header_html .= '<script>
					jQuery(function($) {
						$(\'[name="batch[doc]"],[name="batch[dob]"]\').change(function() {
							var $dob = $(\'[name="batch[dob]"]\').val();
							var $doc = $(\'[name="batch[doc]"]\').val();
							var $age = trdd_dob2ages($dob, $doc);
							console.log($age);
							$(\'[name="' . $name . '"]\').val($age);
						})
					})
					</script>';
				}
				break;

			case 'mtext':
				$options = explode('|', $question->ta_question . '|' . $question->ta_answers);

				$header_html .= '<div class="mtext_row">';

				$header_html .= '<div class="frow">';
				foreach ($options as $txt) {
					$col_num1 = count($options);
					$header_html .= '<div class="col' . $col_num1 . '">';
					$header_html .= '<label>' . $txt . '</label><br>' . "\n";
					$header_html .= '</div>';
				}
				$header_html .= '</div>';

				$countitem = isset($value[0]) && count($value[0]) > 1 ? count($value[0]) : 1;
				for ($iit = 0; $iit < $countitem; $iit++) {
					$header_html .= '<div class="frow main_row">';
					foreach ($options as $i2 => $txt) {
						$col_num1 	= count($options);

						$name 		= 'batmeta[' . $question->ta_slug . '][' . $i2 . '][]';
						$vl			= $value[$i2][$iit];
						$header_html .= '<div class="col' . $col_num1 . '">';
						$header_html .= '<input type="text" name="' . $name . '" value="' . $vl . '"/>';
						$header_html .= '</div>';
					}
					$header_html .= '</div>';
				}

				$header_html .= '<a class="addnew_mtext">+</a>';

				$header_html .= '</div>';
				$col_num = 1;
				break;

			default:
				$header_html .= '<input type="text" name="' . $name . '" value="' . $value . '" ' . $atts . '/>';
				break;
		}


		if ($has_open_column) {
			$header_html .= '</div>' . "\n"; //end col
		}
	}
	$header_html .= '</div>' . "\n"; //end row

	if (!empty($group_adding)) {
		$header_html .= '<div class="frow">';
		$header_html .= $group_adding;
		$header_html .= '<div>';
	}
	$header_html .= '</div>'; //end questions_section

	if ($act != 'print' || $count_q_showed > 0) {
		echo $header_html;
	}
} //end foreach headers
?>


<style>
	.mcheckbox label.mcbox {
		width: 24.4%;
		display: inline-block
	}

	.mcheckbox label.hide {
		display: none;
	}

	.questions_section .frow {
		margin-bottom: 0px;
	}

	.questions_section .frow.full {
		margin-bottom: 15px;
	}

	.questions_section .frow label.question {
		font-weight: bold;
	}

	.questions_section .frow label.questionblock {
		display: block;
		padding-bottom: 6px;
	}

	.questions_section .mtext_row {
		margin-bottom: 20px;
	}

	.questions_section .mtext_row .frow {
		margin-bottom: 10px;
	}

	.questions_section .question_item label.rinput {
		display: inline-block;
	}

	.hide_by_group {
		display: none !important;
	}
</style>
<?php if ($act != 'print') { ?>
	<script>
		jQuery(function($) {
			function tr_question_panel_changed() {
				var $panels_selected = $('input.batcustom_profile:checked').map(function() {
					return $(this).val().replace('GROUP_', '');
				});
				console.log($panels_selected);
				$('.questions_section').each(function() {
					var $q = $(this);
					var $count_itemshowed = 0;
					$('.question_item', $q).each(function() {
						var $qi = $(this);
						var $data_panels = $qi.data('panels');
						var $item_key = $qi.data('key');
						var $found = true;
						if ($data_panels) {
							var $data_panels_arr = $data_panels.toString().split(',');
							$found = false;

							$.each($panels_selected, function(i, v) {
								if ($.inArray(v, $data_panels_arr) >= 0) {
									$found = true;
								}
							});

						}
						if ($found && $item_key == 'pregnancy') {
							var $patient_dob = parseInt($('input[name="pa[pa_dob]"]').val());
							var $patient_age = isNaN($patient_dob) ? 0 : (new Date().getFullYear() - $patient_dob);
							var $patient_gender = $('select[name="pa[pa_gender]"]').val();
							console.log('Patient age: ' + $patient_age + ', gender: ' + $patient_gender);
							if ($patient_gender == 'M' || isNaN($patient_age) || $patient_age < 12 || $patient_age > 50) {
								$found = false;
							}
						}
						if ($found) {
							$qi.show();
							$qi.removeClass('hide_by_group');
							$('input,select,textarea', $qi).each(function() {
								var $rq = $(this).data('required');
								if ($rq && $rq == 1) {
									$(this).attr('required', true);
								}
							})
							$count_itemshowed++;
						} else {
							$qi.hide();
							$qi.addClass('hide_by_group');
							$('input,select,textarea', $qi).each(function() {
								$(this).removeAttr('required');
							})
						}
					})
					console.log($count_itemshowed);
					if ($count_itemshowed == 0) {
						$q.hide();
					} else {
						$q.show();
					}
				})
			}
			$('body').on('click', '.addnew_mtext', function() {
					var $pr = $(this).parents('.mtext_row');
					var $html = '<div class="frow">' + $('.frow.main_row', $pr).html() + '</div>';
					var $newrow = $($html);
					$('input', $newrow).val('');
					$newrow.insertBefore($(this));
				}).on('click', 'input.hasother', function() {
					var $oid = $(this).data('id');
					if ($(this).is(':checked')) {
						$('.fieldnum_' + $oid).removeClass('hide');
					} else {
						$('.fieldnum_' + $oid).addClass('hide');
					}
				}).on('change', 'input.batcustom_profile', tr_question_panel_changed)
				.on('change', 'select[name="pa[pa_gender]"]', tr_question_panel_changed)
				.on('change', 'input[name="pa[pa_dob]"]', tr_question_panel_changed);

			tr_question_panel_changed();
		})
	</script>
<?php } ?>