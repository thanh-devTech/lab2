<?php

$mic_style = (int) tr_get_option('ci_result_settings_pcr', 'e_mic2', 0);

if (($has_manual2_antibiotic && $mic_style === 1) || ($has_manual2_antibiotic && $mic_style === 2)) {
	$vlkeys = array(
		'' => '',
		'I' => 'I',
		'R' => 'R',
		'S' => 'S',
		'P' => 'P',
		'NG' => 'NG',
		//'H' => 'H',
	);

	$where_anti = "";

	if ($has_manual2_antibiotic > 0) {
		$where_anti .= " where (
						find_in_set({$has_manual2_antibiotic}, ta_groups) 
						or ta_groups = ''
						or ta_groups is null
						)";

		$where_anti .= " and (ta_start_date='' or ta_start_date <= '{$report['created_date']}' )
											and (ta_end_date = '' or ta_end_date > '{$report['created_date']}' ) ";
	}

	$sql_anti = "select an.ID,
				an.ta_name,
				ra.result
				from ci_test_antibiotics an
				left join ci_results_antibiotics ra ON ra.ta_ID = an.ID
				and ra.report_id = {$id} 
				and ra.btype = 'pcr'
				and ra.te_ID = -2
				{$where_anti}
				order by ta_name asc";

	$antibiotics = $wpdb->get_results($sql_anti);


	$biomic_keys = array('quantification', 'comments');
	$biomic_options = array();
	foreach ($biomic_keys as $bkey) {
		$arrs = get_option('ci_biomic_messages_' . $bkey);
		if (!is_array($arrs)) {
			$arrs = array();
		}
		foreach ($arrs as $i => $row) {
			if (count($main_panel_ids) > 0 && $row['panel'] > 0 && !isset($main_panel_ids[$row['panel']])) {
				unset($arrs[$i]);
			}
		}


		$biomic_options[$bkey] = $arrs;
	}

	$tinymce_settings = array(
		'plugins' => 'fullscreen,textcolor,lists',
		// Optional: you can specify which buttons appear on which row
		'toolbar1' => 'fontsizeselect,|,bold,italic,underline,|,bullist,numlist2,blockquote,|,justifyleft,justifycenter,justifyright,justifyfull,|,forecolor,undo,redo,fullscreen',
		'toolbar2' => false,
	);
	add_filter('tiny_mce_before_init', function ($initArray) {
		$initArray['fontsize_formats'] = "9px 10px 12px 13px 14px 16px";
		$initArray['content_style'] = 'body { font-size:10px; zoom:1.3;}';
		return $initArray;
	});

	if (!function_exists('trrs_get_edit_result')) {
		function trrs_get_edit_result($reportid, $drug)
		{
			global $wpdb;
			$qog = $wpdb->get_row("select * from ci_results_pcr where report_id = {$reportid} and drug='{$drug}'");
			if (!$qog) {
				$wpdb->insert('ci_results_pcr', array('report_id' => $reportid, 'drug' => $drug, 'conc' => '0', 'has_order' => 0));
				$qogid = $wpdb->insert_id;
			} else {
				$qogid = $qog->id;
				$qog_text = $qog->comments;
			}
			return array('id' => $qogid, 'comments' => $qog_text);
		}
	}

?>
	<input type="hidden" name="has_antibiotics" value="1" />
	<div class="frow">
		<h4>Gram Stain</h4>
	</div>
	<div class="frow">
		<label>Internal Note <small style="font-weight:400">(from instrument or manual entry — not shown on Result PDF)</small></label>
		<?php
		$mic_internal_note = trrs_get_edit_result($id, 'mic_internal_note');
		?>
		<textarea name="data_results[<?php echo $mic_internal_note['id'] ?>][comments]" style="width:100%;height:50px"><?php echo esc_textarea($mic_internal_note['comments']) ?></textarea>
	</div>
	<?php if ($mic_style == 1) { ?>

		<div class="frow">
			<div class="col2">
				<label>QUANTIFICATION OF GROWTH</label>
				<?php
				$qogarr = trrs_get_edit_result($id, 'antibiotic_cmm');
				if (tr_get_option('ci_result_settings_pcr', 'full_editor_mic')) {

					wp_editor($qogarr['comments'], 'qogid', array(
						'textarea_name' => "data_results[{$qogarr['id']}][comments]",
						'textarea_rows' => 5,
						'media_buttons' => false,
						'teeny' => false,
						'quicktags' => false,
						'tinymce' => $tinymce_settings,
					));
				} else {
					echo '<textarea id="qogid" name="data_results[' . $qogarr['id'] . '][comments]">' . $qogarr['comments'] . '</textarea>';
				}

				if (count($biomic_options['quantification']) > 0) {
				?>
					<select class="change_comments" data-target="qogid">
						<?php
						echo '<option value="">Select</option>';
						foreach ($biomic_options['quantification'] as $arr) {
							echo '<option value="' . $arr['message'] . '">' . $arr['title'] . '</option>';
						}
						?>
					</select>
				<?php
				}
				?>
			</div>
			<div class="col2">
				<label>Comments</label>
				<?php
				$commentarr = trrs_get_edit_result($id, 'antibiotic_comments');
				if (tr_get_option('ci_result_settings_pcr', 'full_editor_mic')) {

					wp_editor($commentarr['comments'], 'cmid', array(
						'textarea_name' => "data_results[{$commentarr['id']}][comments]",
						'textarea_rows' => 5,
						'media_buttons' => false,
						'teeny' => false,
						'quicktags' => false,
						'tinymce' => $tinymce_settings,
					));
				} else {
					echo '<textarea id="cmid" name="data_results[' . $commentarr['id'] . '][comments]">' . $commentarr['comments'] . '</textarea>';
				}

				if (count($biomic_options['comments']) > 0) {
				?>
					<select class="change_comments" data-target="cmid">
						<?php
						echo '<option value="">Select</option>';
						foreach ($biomic_options['comments'] as $arr) {
							echo '<option value="' . $arr['message'] . '">' . $arr['title'] . '</option>';
						}
						?>
					</select>
				<?php
				}
				?>
			</div>
		</div>

		<div class="wraptbl frow">

			<?php
			$cpercol = ceil(count($antibiotics) / 3);
			echo '<div class="col3">';
			echo '<table class="tbl_results">';
			foreach ($antibiotics as $i => $anti) {
				if ($i > 0 && $i % $cpercol == 0) {
					echo '</table>';
					echo '</div>';
					echo '<div class="col3">';
					echo '<table class="tbl_results" >';
				}
			?>
				<tr class="bordertop">
					<td style="padding-right:20px"><?php echo $anti->ta_name ?></td>
					<td style="width:50px">
						<select name="antibioticsdata[-2][<?php echo $anti->ID ?>]" style="padding-left:5px">
							<?php
							foreach ($vlkeys as $k => $txt) {
								$selected = $k == $anti->result ? 'selected' : '';
								echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
							}
							?>
						</select>
					</td>
				</tr>
			<?php
			}
			echo '</table>';
			echo '</div>';
			?>

		</div>
	<?php
	} else if ($mic_style == 2) {
		$quantitationarr = trrs_get_edit_result($id, 'antibiotic_quantitation');
		$quancats = array(
			'',
			'Scant',
			'Light',
			'Moderate',
			'Heavy',
			'No growth',
			'Note: Mixed heavy growth',
			'Note: PCR Detection Only'
		);
	?>
		<div class="frow">
			<div class="col3">
				<label>Quantitation</label>
				<select name="data_results[<?php echo $quantitationarr['id']; ?>][comments]">
					<?php
					foreach ($quancats as $cat) {
						$selected = $cat == $quantitationarr['comments'] ? 'selected' : '';
						echo '<option value="' . $cat . '" ' . $selected . '>' . $cat . '</option>';
					}
					?>
				</select>
				<label>Preliminary/Final Quantification of Growth</label>
				<?php
				$qogarr = trrs_get_edit_result($id, 'antibiotic_pqog_cmm');
				if (tr_get_option('ci_result_settings_pcr', 'full_editor_mic')) {

					wp_editor($qogarr['comments'], 'qogid', array(
						'textarea_name' => "data_results[{$qogarr['id']}][comments]",
						'textarea_rows' => 5,
						'media_buttons' => false,
						'teeny' => false,
						'quicktags' => false,
						'tinymce' => $tinymce_settings,
					));
				} else {
					echo '<textarea id="qogid" name="data_results[' . $qogarr['id'] . '][comments]">' . $qogarr['comments'] . '</textarea>';
				}

				if (count($biomic_options['quantification']) > 0) {
				?>
					<select class="change_comments" data-target="qogid">
						<?php
						echo '<option value="">Select</option>';
						foreach ($biomic_options['quantification'] as $arr) {
							echo '<option value="' . $arr['message'] . '">' . $arr['title'] . '</option>';
						}
						?>
					</select>
				<?php
				}
				?>
			</div>
			<div class="col23">
				<label> Gram Stain</label>
				<div id="gram_stain_form" class="form-group" style="display: flex; align-items: center; gap: 10px;">
					<!-- Quantity Dropdown -->
					<select id="quantity" style="width:150px">
						<option value="">Select Quantity</option>
						<option value="No">No</option>
						<option value="Rare">Rare</option>
						<option value="Few">Few</option>
						<option value="Moderate">Moderate</option>
						<option value="Many">Many</option>
					</select>

					<!-- Gram Reaction Dropdown -->
					<select id="reaction" style="width:150px" onchange="filterMorphology()">
						<option value="">Select Reaction</option>
						<option value="Gram-positive">Gram-positive</option>
						<option value="Gram-negative">Gram-negative</option>
						<option value="Gram-variable">Gram-variable</option>
						<option value="White Blood Cells">White Blood Cells</option>
						<option value="None">None (Atypical/Fungal)</option>
					</select>

					<!-- Morphology Dropdown -->
					<select id="morphology" style="width:150px" onchange="filterArrangement()">
						<option value="">Select Shape</option>
						<option value="___" data-type="none" data-rx="Gram-positive,Gram-negative,Gram-variable,White Blood Cells,None">——</option>
						<!-- Standard Bacteria -->
						<option value="cocci" data-type="bact" data-rx="Gram-positive,Gram-negative,Gram-variable,White Blood Cells">cocci</option>
						<option value="bacilli" data-type="bact" data-rx="Gram-positive,Gram-negative,Gram-variable,White Blood Cells">bacilli</option>
						<option value="rods" data-type="bact" data-rx="Gram-positive,Gram-negative,Gram-variable,White Blood Cells">rods</option>
						<option value="coccobacilli" data-type="bact" data-rx="Gram-positive,Gram-negative,Gram-variable,White Blood Cells">coccobacilli</option>
						<!-- Atypical Shapes -->
						<option value="pleomorphic rods" data-type="atyp" data-rx="Gram-positive,Gram-negative">pleomorphic rods</option>
						<option value="pleomorphic bacilli" data-type="atyp" data-rx="Gram-positive,Gram-negative">pleomorphic bacilli</option>
						<option value="diphtheroid rods" data-type="atyp" data-rx="Gram-positive">diphtheroid rods</option>
						<option value="branching rods" data-type="atyp" data-rx="Gram-positive">branching rods</option>
						<option value="filamentous rods" data-type="atyp" data-rx="Gram-positive">filamentous rods</option>
						<option value="spore-forming bacilli" data-type="atyp" data-rx="Gram-positive">spore-forming bacilli</option>
						<option value="curved rods" data-type="atyp" data-rx="Gram-negative">curved rods</option>
						<option value="comma-shaped rods" data-type="atyp" data-rx="Gram-negative">comma-shaped rods</option>
						<option value="spirilla" data-type="atyp" data-rx="Gram-negative">spirilla</option>
						<option value="spirochetes" data-type="atyp" data-rx="Gram-negative">spirochetes</option>
						<!-- Fungal / Organisms -->
						<option value="budding yeast cells" data-type="fung" data-rx="Gram-positive">budding yeast cells</option>
						<option value="yeast with pseudohyphae" data-type="fung" data-rx="Gram-positive">yeast with pseudohyphae</option>
						<option value="fungal hyphae" data-type="fung" data-rx="Gram-positive">fungal hyphae</option>
						<option value="organism" data-type="none" data-rx="None">organism</option>
					</select>

					<!-- Arrangement Dropdown -->
					<select id="arrangement" style="width:150px">
						<option value="">Select Arrangement</option>
						<option value="single" data-morph="cocci,bacilli,rods,coccobacilli">single</option>
						<option value="isolated" data-morph="cocci,bacilli,rods,coccobacilli">isolated</option>
						<option value="in pairs" data-morph="cocci,bacilli,rods,coccobacilli">in pairs</option>
						<option value="diplococci" data-morph="cocci">diplococci</option>
						<option value="in chains" data-morph="cocci,bacilli,rods">in chains</option>
						<option value="in short chains" data-morph="cocci">in short chains</option>
						<option value="in long chains" data-morph="cocci">in long chains</option>
						<option value="in clusters" data-morph="cocci">in clusters</option>
						<option value="pairs & clusters" data-morph="cocci,bacilli,rods,coccobacilli">pairs & clusters</option>
						<option value="in tetrads" data-morph="cocci">in tetrads</option>
						<option value="in packets of four" data-morph="cocci">in packets of four</option>
						<option value="in Chinese letter arrangements" data-morph="rods">in Chinese letter arrangements</option>
						<option value="N/A" data-type="atypical">Not Applicable (Atypical/Fungal)</option>
					</select>
					<a class="button" id="generate_gram_stain">Generate</a>
				</div>

				<div id="gram_stain_list">
					<?php
					$gram_stains = trrs_get_edit_result($id, 'antibiotic_gram_stains');
					$gram_stains_other = trrs_get_edit_result($id, 'antibiotic_gram_other');

					$gram_stains_list = !empty($gram_stains['comments']) ? explode('|', $gram_stains['comments']) : [];

					foreach ($gram_stains_list as $gs) {
						echo '<div class="gram_stain_item">';
						echo esc_html($gs);
						echo '<a href="#" class="button remove_gram_stain">-</a>';
						echo '<input type="hidden" name="data_results[' . $gram_stains['id'] . '][comments][]"  value="' . esc_attr($gs) . '" />';
						echo '</div>';
					}
					?>
				</div>

				<input type="hidden" name="data_results[<?php echo $gram_stains['id'] ?>][drug]" value="antibiotic_gram_stains" />

				<div>
					<label>Other</label>
					<textarea name="data_results[<?php echo $gram_stains_other['id'] ?>][comments]" style="width:100%;height:60px"><?php echo esc_textarea($gram_stains_other['comments']) ?></textarea>
				</div>
				<style>
					#gram_stain_list {
						border-top: 1px solid #ddd;
						margin-top: 10px;
					}

					#gram_stain_list .gram_stain_item {
						padding: 4px 10px;
						border-bottom: 1px solid #ddd;
						position: relative;
						overflow: hidden;
					}

					#gram_stain_list .remove_gram_stain {
						float: right;
						min-height: 20px;
						line-height: 20px;

					}
				</style>
				<script>
					var $gram_stains_id = <?php echo $gram_stains['id'] ?>;
					// Dynamic filtering logic to prevent impossible combinations in the UI
					function filterMorphology() {
						const rxSelect = document.getElementById('reaction');
						const morphSelect = document.getElementById('morphology');
						const selectedRx = rxSelect.value;

						morphSelect.selectedIndex = 0;
						document.getElementById('arrangement').selectedIndex = 0;

						for (let i = 0; i < morphSelect.options.length; i++) {
							const option = morphSelect.options[i];
							if (!option.value) continue;

							const allowedRx = option.getAttribute('data-rx').split(',');
							if (allowedRx.includes(selectedRx)) {
								option.style.display = 'block';
							} else {
								option.style.display = 'none';
							}
						}
					}

					function filterArrangement() {
						const morphSelect = document.getElementById('morphology');
						const arrangeSelect = document.getElementById('arrangement');
						const selectedMorph = morphSelect.value;
						const selectedOption = morphSelect.options[morphSelect.selectedIndex];
						const type = selectedOption.getAttribute('data-type');

						arrangeSelect.selectedIndex = 0;

						for (let i = 0; i < arrangeSelect.options.length; i++) {
							const option = arrangeSelect.options[i];
							if (!option.value) continue;

							if (type === 'atyp' || type === 'fung' || type === 'none') {
								if (option.getAttribute('data-type') === 'atypical') {
									option.style.display = 'block';
								} else {
									option.style.display = 'none';
								}
							} else {
								const allowedMorphs = option.getAttribute('data-morph') ? option.getAttribute('data-morph').split(',') : [];
								if (allowedMorphs.includes(selectedMorph) && option.getAttribute('data-type') !== 'atypical') {
									option.style.display = 'block';
								} else {
									option.style.display = 'none';
								}
							}
						}
					}
					jQuery(function($) {
						$('#generate_gram_stain').on('click', function() {
							const $quantity = $('#quantity').val();
							const $reaction = $('#reaction').val();
							const $morphology = $('#morphology').val();
							const $arrangement = $('#arrangement').val();

							if ($quantity === '' || $morphology === '' || $reaction === '') {
								alert('Please select all fields.');
								return;
							}

							$reaction_str = ($reaction === 'None') ? '' : $reaction;
							$arrangement_str = ($arrangement === 'N/A') ? '' : $arrangement;

							// Build the result sentence logically
							$words = [$quantity, $reaction_str, $morphology, $arrangement_str].filter(Boolean);
							$result_string = $words.join(' ') + ' seen';

							// Grammar clean up for "No organism seen" vs standard plural items
							if ($quantity === 'No' && $morphology === 'organism') {
								$result_string = "No organisms seen";
							}

							var $newrow = '<div class="gram_stain_item">';
							$newrow += ($result_string);
							$newrow += '<a href="#" class="button remove_gram_stain">-</a>';
							$newrow += '<input type="hidden" name="data_results[' + $gram_stains_id + '][comments][]"  value="' + ($result_string) + '" />';
							$newrow += '</div>';
							$('#gram_stain_list').append($newrow);
							// Reset the form fields after adding
							$('#quantity').val('');
							$('#reaction').val('');
							$('#morphology').val('');
							$('#arrangement').val('');
						});
						$('.remove_gram_stain').on('click', function(e) {
							e.preventDefault();
							$(this).closest('.gram_stain_item').remove();
						});
					})
				</script>
			</div>
		</div>
		<div class="frow">
			<div class="col3">
				<label>Preliminary Comment</label>
				<?php
				$preli1_arr = trrs_get_edit_result($id, 'antibiotic_preliminary1');
				if (tr_get_option('ci_result_settings_pcr', 'full_editor_mic')) {
					wp_editor($preli1_arr['comments'], 'preli1_id', array(
						'textarea_name' => "data_results[{$preli1_arr['id']}][comments]",
						'textarea_rows' => 5,
						'media_buttons' => false,
						'teeny' => false,
						'quicktags' => false,
						'tinymce' => $tinymce_settings,
					));
				} else {
					echo '<textarea id="preli1_id" name="data_results[' . $preli1_arr['id'] . '][comments]">' . $preli1_arr['comments'] . '</textarea>';
				}

				?>
			</div>
			<div class="col3">
				<label>Preliminary 2</label>
				<?php
				$preli2_arr = trrs_get_edit_result($id, 'antibiotic_preliminary2');
				if (tr_get_option('ci_result_settings_pcr', 'full_editor_mic')) {
					wp_editor($preli2_arr['comments'], 'preli2_id', array(
						'textarea_name' => "data_results[{$preli2_arr['id']}][comments]",
						'textarea_rows' => 5,
						'media_buttons' => false,
						'teeny' => false,
						'quicktags' => false,
						'tinymce' => $tinymce_settings,
					));
				} else {
					echo '<textarea id="preli2_id" name="data_results[' . $preli2_arr['id'] . '][comments]">' . $preli2_arr['comments'] . '</textarea>';
				}
				?>
			</div>
			<div class="col3">
				<label>Final Comments</label>
				<?php
				$final_arr = trrs_get_edit_result($id, 'antibiotic_final');
				if (tr_get_option('ci_result_settings_pcr', 'full_editor_mic')) {
					wp_editor($final_arr['comments'], 'final_id', array(
						'textarea_name' => "data_results[{$final_arr['id']}][comments]",
						'textarea_rows' => 5,
						'media_buttons' => false,
						'teeny' => false,
						'quicktags' => false,
						'tinymce' => $tinymce_settings,
					));
				} else {
					echo '<textarea id="final_id" name="data_results[' . $final_arr['id'] . '][comments]">' . $final_arr['comments'] . '</textarea>';
				}
				?>
			</div>
		</div>
	<?php
	}
}


if ((($has_manual_antibiotic && $mic_style == 0) || ($has_manual2_antibiotic && $mic_style == 2))) {

	$vlkeys = array(
		'' => '',
		'I' => 'I',
		'R' => 'R',
		'S' => 'S',
		//'NG' => 'NG',
	);


	$anti_pathogen = array();
	$anti_pathogen_pcr = array();
	foreach ($drug_groups as $groupid => $groupdata) {
		if (isset($groupdata['manual_antibiotic']) && $groupdata['manual_antibiotic']) {
			foreach ($groupdata['tests'] as $arr) {
				if (empty($arr['te_name']) || empty($arr['te_ID'])) continue;
				if ($mic_style == 2 && !in_array($arr['pos-neg'], array('POS', 'POL', 'POM', 'POH', 'PVH', 'PVL'))) continue;
				$anti_pathogen_pcr[$arr['te_ID']] = $arr;
			}
			if ($groupdata['subgroups']) {
				foreach ($groupdata['subgroups'] as $subgroup) {
					foreach ($subgroup['tests'] as $arr) {
						if (empty($arr['te_name']) || empty($arr['te_ID'])) continue;
						if ($mic_style == 2 && !in_array($arr['pos-neg'], array('POS', 'POL', 'POM', 'POH', 'PVH', 'PVL'))) continue;
						$anti_pathogen_pcr[$arr['te_ID']] = $arr;
					}
				}
			}
		}
	}
	uasort($anti_pathogen_pcr, function ($a, $b) {
		return strcmp($a['te_name'], $b['te_name']);
	});

	//more pathgeno
	$anti_pathogen_pats = array();
	$morepathogens = $wpdb->get_results("select te_ID,te_name,te_groups_pathogen as te_group_id from ci_test_panels where te_type='pats'", ARRAY_A);
	foreach ($morepathogens as $r) {
		$anti_pathogen_pats[$r['te_ID']] = $r;
	}
	uasort($anti_pathogen_pats, function ($a, $b) {
		return strcmp($a['te_name'], $b['te_name']);
	});
	$anti_pathogen = $anti_pathogen_pcr + $anti_pathogen_pats;

	$repdb = $wpdb->get_row("select * from ci_reports_pcr where id = {$report['id']}");
	$show3pathogen_note = false;
	$shownogrowth_note = false;
	if ($repdb) {
		$show3pathogen_note = $repdb->show3pathogen_note;
		$shownogrowth_note = $repdb->shownogrowth_note;
	}

	$where_anti = '';
	if ($has_manual2_antibiotic > 0 && $mic_style == 2) {
		$where_child = array(" ta_groups = '' ", " ta_groups is null ", " find_in_set({$has_manual2_antibiotic}, ta_groups) ");

		foreach ($report['group_ids'] as $gid) {
			$gid = intval($gid);
			if ($gid == $has_manual2_antibiotic || $gid == 0) continue;
			$where_child[] = 'find_in_set(' . $gid . ', ta_groups)';
		}

		$where_anti = " and (" . implode(' or ', $where_child) . ")";

		$where_anti .= " and (ta_start_date='' or ta_start_date is NULL or ta_start_date <= '{$report['created_date']}' )
											and (ta_end_date = '' or ta_end_date is NULL or ta_end_date > '{$report['created_date']}' ) ";
	}

	$antibiotics = $wpdb->get_results("select ID,ta_name, ta_abrev, ta_groups
                                            from ci_test_antibiotics 
											where 1 {$where_anti}
                                            order by ta_name asc");

	$mic_data = array();
	$mic_rs = $wpdb->get_results("select ra.*
                                                from ci_results_antibiotics ra
                                                where report_id = {$id} 
                                                and btype = 'pcr'
                                                and ra.te_ID = -1");
	foreach ($mic_rs as $r) {
		$mic_data[$r->ta_ID] = $r->result;
	}

	$anti_rs = $wpdb->get_results("select ra.*,
										te.te_name, 
										te.te_abrev, 
										IF(te.te_type = 'pats', te.te_groups_pathogen, te.te_group_id) as te_group_id
                                                from ci_results_antibiotics ra
                                                join ci_test_panels te ON te.te_ID = ra.te_ID
                                                where report_id = {$id}
                                                and btype in( 'pcr', 'pats')
                                                order by te_name asc");


	$labresults = $wpdb->get_results("select ra.*,
										te.te_name, 
										te.te_abrev, 
										IF(te.te_type = 'pats', te.te_groups_pathogen, te.te_group_id) as te_group_id
                                            from ci_results_labresults ra
                                            join ci_test_panels te ON te.te_ID = ra.te_ID
                                            where report_id = {$id}
                                                order by te_name asc ");


	$anti_data = array();
	foreach ($anti_rs as $r) {
		$kt = 'k' . $r->te_ID;
		if (!isset($anti_data[$kt])) {
			$anti_data[$kt] = array(
				'te_ID' => $r->te_ID,
				'name' => $r->te_name,
				'group_id' => $r->te_group_id,
				'rs' => array()
			);
		}
		$anti_data[$kt]['rs'][$r->ta_ID] = $r->result;
	}

	foreach ($labresults as $r) {
		$kt = 'k' . $r->te_ID;
		if (!isset($anti_data[$kt])) {
			$anti_data[$kt] = array(
				'te_ID' => $r->te_ID,
				'name' => $r->te_name,
				'group_id' => $r->te_group_id,
				'rs' => array()
			);
		}
		$anti_data[$kt]['labresult'] = $r->result;
	}


	?>
	<div class="frow">
		<h4>MIC</h4>
	</div>

	<select id="list_pathogen"></select>

	<a class="button add_antibiotics">+</a>
	<span style="margin-left:16px">
		<input type="text" id="new_antibiotic_name" placeholder="New antibiotic name" style="width:180px" />
		<a class="button" id="add_new_antibiotic">Add New Antibiotic</a>
	</span>
	<span style="margin-left:16px">
		<input type="text" id="new_pathogen_name" placeholder="New pathogen name" style="width:180px" />
		<a class="button" id="add_new_pathogen">Add New Pathogen</a>
	</span>
	<?php if ($mic_style == 2) { ?>
		<div id="new_pathogen_group_modal" style="display:none;position:fixed;z-index:100000;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.35);">
			<div style="background:#fff;width:420px;max-width:90%;margin:120px auto 0;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.2);">
				<h3 style="margin-top:0;">Select Group for New Pathogen</h3>
				<select id="new_pathogen_groups" multiple style="width:100%">
					<?php
					foreach ($drug_groups as $gid => $groupdata) {
						$gid = intval($gid);
						if ($groupdata['parent'] > 0 || $gid == 0 || stripos($groupdata['name'], 'fungi') !== false) continue;
						echo '<option value="' . $gid . '">' . $groupdata['name'] . '</option>';
						foreach ($groupdata['subgroups'] as $cgid => $sub_panel) {
							if (stripos($sub_panel['name'], 'fungi') !== false) continue;

							echo '<option value="' . $cgid . '">&nbsp;&nbsp;- ' . $sub_panel['name'] . '</option>';
						}
					}
					?>
				</select>
				<div style="margin-top:15px;text-align:right">
					<a href="#" class="button" id="cancel_new_pathogen_group">Cancel</a>
					<a href="#" class="button button-primary" id="confirm_new_pathogen_group">Add Pathogen</a>
				</div>
			</div>
		</div>
	<?php } ?>
	<?php if ($mic_style != 2) { ?>
		<label style="padding-left: 16px;">
			<input type="hidden" name="info[show3pathogen_note]" value="0" />
			<input type="checkbox" name="info[show3pathogen_note]" value="1" <?php checked($show3pathogen_note) ?> /> 3 or more pathogen note
		</label>

		<label style="padding-left: 16px;">
			<input type="hidden" name="info[shownogrowth_note]" value="0" />
			<input type="checkbox" name="info[shownogrowth_note]" value="1" <?php checked($shownogrowth_note) ?> /> No Growth
		</label>
	<?php } ?>
	<div id="display_antibiotics" style="margin:10px 0;display:none"></div>
	<input type="hidden" name="has_antibiotics" value="1" />
	<script>
		var $group_pos_ids = <?php echo json_encode($report['group_pos_ids']) ?>;
		var $manual_group_id = <?php echo intval($has_manual2_antibiotic) ?>;
		var $mic_style = <?php echo $mic_style ?>;
		var $vlkeys = <?php echo json_encode($vlkeys) ?>;
		var $anti_pathogen_pcr = <?php echo json_encode($anti_pathogen_pcr) ?>;
		var $anti_pathogen_pats = <?php echo json_encode($anti_pathogen_pats) ?>;
		var $anti_pathogen = <?php echo json_encode($anti_pathogen) ?>;
		var $antibiotics = <?php echo json_encode($antibiotics) ?>;
		var $anti_data = <?php echo json_encode($anti_data) ?>;
		var $mic_data = <?php echo json_encode($mic_data) ?>;
		var $symbols = ['>', '<', '>=', '<='];
		var $mic_result2 = ['', '<10,000', '10,000 to 50,0000', '60,000 to 100,000', '>100,000', 'Scant', 'Light', 'Moderate', 'Heavy', 'No Growth'];
		jQuery(function($) {
			function tr_build_antibiotic_row_html($ta_ID, $ta_name, $ta_code, $ta_groups) {
				var $nrow = 'antibioticsdata[-1][' + $ta_ID + ']';
				var $html = '<tr class="rowdata bordertop" data-taid="' + $ta_ID + '" data-tagroups="' + $ta_groups + '"><td class="drugs_col">' + $ta_name + '</td><td>' + ($ta_code || '') + '</td>';
				if ($mic_style == 0) {
					$html += '<td class="">';
					var $mic_vl = $mic_data[$ta_ID] || '';

					$html += '<select name="symbol_' + $nrow + '" style="width:46%;"><option value=""></option>';
					for (var isb in $symbols) {
						var sb = $symbols[isb];
						var sbt = sb;
						if (sbt == '>=') {
							sbt = '&ge;';
						} else if (sbt == '<=') {
							sbt = '&le;';
						}
						var $selected = '';
						if ($mic_vl.indexOf(sb) == 0 && (sb.length == 2 || (sb.length == 1 && $mic_vl.indexOf('=') !== 1))) {
							$selected = 'selected';
							$mic_vl = $mic_vl.replace(sb, '');
						}
						$html += '<option value="' + sb + '" ' + $selected + '>' + sbt + '</option>';
					}
					$html += '</select>';
					$html += '<input type="text" name="' + $nrow + '" value="' + $mic_vl + '" style="width:49%"/></td>';
				}
				$html += '</tr>';
				return $html;
			}

			function tr_fill_antibio_drugcol() {
				var $html = '<div class="wraptbl"><table class="tbl_results" id="antibiotics_tbl">';
				$html += '<thead class="sticky top2 style2"><tr class="group_header"><th class="drugs_col" style="width:20%">DRUGS</th><th style="width:10%">Code</th>';
				if ($mic_style == 0) {
					$html += '<th style="width:20%">MIC Data</th>';
				}
				$html += '</tr></thead>';
				$html += '<tr class="rowdata labvalue"><td class="drugs_col"><b>Lab Result</b></td><td></td>';
				if ($mic_style == 0) {
					$html += '<td></td>';
				}
				$html += '</tr>';
				for (var j in $antibiotics) {
					$html += tr_build_antibiotic_row_html($antibiotics[j].ID, $antibiotics[j].ta_name, $antibiotics[j].ta_abrev, $antibiotics[j].ta_groups);
				}
				$html += '</table></div>';
				$('#display_antibiotics').append($html);
			}

			function tr_add_antibiotic_row($ta_ID, $ta_name, $ta_code, $ta_groups) {
				var $rowHtml = tr_build_antibiotic_row_html($ta_ID, $ta_name, $ta_code, $ta_groups);
				$('#display_antibiotics table').append($rowHtml);

				$('#display_antibiotics tr.group_header th.te_head').each(function() {
					var $teid = $(this).data('teid');
					var $nrow = 'antibioticsdata[' + $teid + '][' + $ta_ID + ']';
					var $cellHtml = '<td data-teid="' + $teid + '" class="te_row"><select name="' + $nrow + '">';
					for (var k in $vlkeys) {
						$cellHtml += '<option value="' + k + '">' + $vlkeys[k] + '</option>';
					}
					$cellHtml += '</select></td>';
					$('#display_antibiotics tr.rowdata[data-taid="' + $ta_ID + '"]').append($cellHtml);
				});
			}

			function tr_show_hide_anti_row() {
				if ($mic_style != 2) return; // Only apply this logic for mic_style 2
				$('#antibiotics_tbl tr.rowdata').each(function() {
					var $tr = $(this);
					if ($tr.hasClass('labvalue')) return;
					if ($tr.find('td.hasedit').length > 0) {
						$tr.show();
					} else {
						$tr.hide();
					}
				});
			}

			function tr_fill_antibio_col($data) {
				var $header = '<th data-teid="' + $data.te_ID + '" class="te_head" style="max-width:150px;width:150px">' + $data['name'] + '<br><a class="removecol button">-</a></th>';
				$('#display_antibiotics tr.group_header').append($header);

				//add labresult
				var $lrs = '<td data-teid="' + $data.te_ID + '" class="te_row">';
				if ($mic_style == 2) {
					//use dropdown for mic_style 2
					$lrs += '<select name="labresult[' + $data.te_ID + ']">';
					for (var i in $mic_result2) {
						var $selected = $mic_result2[i] == ($data.labresult || '') ? 'selected' : '';
						$lrs += '<option value="' + $mic_result2[i] + '" ' + $selected + '>' + $mic_result2[i] + '</option>';
					}
					$lrs += '</select>';
				} else {
					$lrs += '<input type="text" name="labresult[' + $data.te_ID + ']" value="' + ($data.labresult || '') + '"/>';
				}

				$lrs += '</td>';
				$('#display_antibiotics tr.rowdata.labvalue').append($lrs);

				for (var j in $antibiotics) {
					var $ta_ID = $antibiotics[j].ID;
					var $nrow = 'antibioticsdata[' + $data.te_ID + '][' + $ta_ID + ']';

					var $showselect = true;
					if ($mic_style == 2) {
						$data.group_id = $data.group_id + ''; // Ensure group_id is a string for comparison
						console.log('Comparing pathogen group_id:', $data.group_id, 'with antibiotic ta_groups:', $antibiotics[j].ta_groups);

						$showselect = $antibiotics[j].ta_groups == '' || $antibiotics[j].ta_groups == null || $data.group_id == '' || $antibiotics[j].ta_groups == $data.group_id || ($data.group_id && $antibiotics[j].ta_groups && $data.group_id.split(',').some(gid => $antibiotics[j].ta_groups.split(',').includes(gid)));
					}

					var $html = '<td data-teid="' + $data.te_ID + '" class="te_row ' + ($showselect ? 'hasedit' : 'noedit') + '">';
					if ($showselect) {
						$html += '<select name="' + $nrow + '">';
						for (var k in $vlkeys) {
							var $dresult = $data.rs[$ta_ID] || '';
							var $selected = k == $dresult ? 'selected' : '';
							$html += '<option value="' + k + '" ' + $selected + '>' + $vlkeys[k] + '</option>';
						}
						$html += '</select>';
					}
					$html += '</td>';
					$('#display_antibiotics tr.rowdata[data-taid="' + $ta_ID + '"]').append($html);
				}

				$('#display_antibiotics').show();
			}

			function tr_refresh_pathogen_select2() {
				if ($.fn.select2 && $('#list_pathogen').hasClass('select2-hidden-accessible')) {
					$('#list_pathogen').select2('destroy');
				}
				if ($.fn.select2) {
					$('#list_pathogen').select2({
						width: '260px',
						templateResult: function(state) {
							if (state.element && state.element.value === '' && $(state.element).hasClass('pathogen_dd_separator')) {
								return $('<span style="display:block;border-top:1px solid #ddd;margin-top:2px;padding-top:2px;text-align:center;color:#ccc;pointer-events:none;">──────────</span>');
							}
							return state.text;
						}
					});
				}
			}

			function tr_fill_pathogen_dd() {
				var $html = '<option value="">Select Pathogen</option>';

				for (var $tid in $anti_pathogen_pcr) {
					$html += '<option value="' + $tid + '" data-groupid="' + $anti_pathogen_pcr[$tid]['te_group_id'] + '">' + $anti_pathogen_pcr[$tid]['te_name'] + '</option>';
				}
				if (Object.keys($anti_pathogen_pcr).length > 0) {
					$html += '<option value="" disabled class="pathogen_dd_separator">──────────</option>';
				}

				for (var $tid in $anti_pathogen_pats) {
					$html += '<option value="' + $tid + '" data-groupid="' + $anti_pathogen_pats[$tid]['te_group_id'] + '">' + $anti_pathogen_pats[$tid]['te_name'] + '</option>';
				}

				$('#list_pathogen').html($html);
				tr_refresh_pathogen_select2();
			}
			$('body').on('click', '.add_antibiotics', function() {
				var $vl = $('#list_pathogen').val();
				if ($vl == '') return false;
				var $opt = $('#list_pathogen option[value="' + $vl + '"]');
				var $tname = $opt.html();
				tr_fill_antibio_col({
					'name': $tname,
					'te_ID': $vl,
					'group_id': $opt.data('groupid') || '',
					'rs': []
				});
				tr_show_hide_anti_row();
				console.log($opt)
				$opt.prop('disabled', true);
				$('#list_pathogen').val('');
				tr_refresh_pathogen_select2();
			}).on('click', '#display_antibiotics .removecol', function() {
				if (confirm("Do you want remove?") == false) return false;
				var teid = $(this).parent().data('teid');
				$(this).parent().remove();
				$('#display_antibiotics td.te_row[data-teid="' + teid + '"]').remove();
				if ($('#display_antibiotics tr.group_header th').length <= 1) {
					$('#display_antibiotics').hide();
				}
				$('#list_pathogen option[value="' + teid + '"]').prop('disabled', false);
				tr_refresh_pathogen_select2();
				tr_show_hide_anti_row();
			});
			$('#add_new_antibiotic').on('click', function() {
				var $name = $('#new_antibiotic_name').val().trim();
				if (!$name) return false;
				$.ajax({
					type: 'POST',
					data: {
						action: 'trajax',
						trajax: 'add_antibiotic_pcr',
						ta_name: $name,
						group_id: $manual_group_id
					},
					success: function(res) {
						if (res.status == 'ok') {
							var $newTa = {
								ID: res.ID,
								ta_name: $name,
								ta_abrev: res.ta_abrev || '',
								ta_groups: res.ta_groups || ''
							};
							$antibiotics.push($newTa);
							tr_add_antibiotic_row($newTa.ID, $newTa.ta_name, $newTa.ta_abrev, $newTa.ta_groups);
							$('#new_antibiotic_name').val('');
						} else {
							alert(res.message || 'Error adding antibiotic');
						}
					},
					error: function() {
						alert('Error adding antibiotic');
					}
				});
			});

			$('#add_new_pathogen').on('click', function() {
				var $name = $('#new_pathogen_name').val().trim();
				if (!$name) return false;
				if ($mic_style == 2) {
					$('#new_pathogen_groups').val(null);
					if ($.fn.select2) {
						if ($('#new_pathogen_groups').hasClass('select2-hidden-accessible')) {
							$('#new_pathogen_groups').val(null).trigger('change');
						} else {
							$('#new_pathogen_groups').select2({
								dropdownParent: $('#new_pathogen_group_modal')
							});
						}
					}
					$('#new_pathogen_group_modal').show();
					return false;
				}
				tr_do_add_new_pathogen($name, []);
			});

			function tr_do_add_new_pathogen($name, $groups) {
				$.ajax({
					type: 'POST',
					data: {
						action: 'trajax',
						trajax: 'add_pathogen_pcr',
						te_name: $name,
						group_id: $manual_group_id,
						te_groups_pathogen: $groups
					},
					success: function(res) {
						if (res.status == 'ok') {
							$anti_pathogen[res.te_ID] = res;
							$anti_pathogen_pats[res.te_ID] = res;

							var $newOpt = '<option value="' + res.te_ID + '" data-groupid="' + (res.group_id || '') + '" disabled>' + $name + '</option>';
							$('#list_pathogen optgroup[label="Pathogens"]').append($newOpt);
							tr_refresh_pathogen_select2();

							tr_fill_antibio_col({
								'name': $name,
								'te_ID': res.te_ID,
								'rs': [],
								'group_id': res.group_id || ''
							});
							tr_show_hide_anti_row();
							$('#new_pathogen_name').val('');
						} else {
							alert(res.message || 'Error adding pathogen');
						}
					},
					error: function() {
						alert('Error adding pathogen');
					}
				});
			}

			$('#cancel_new_pathogen_group').on('click', function(e) {
				e.preventDefault();
				$('#new_pathogen_group_modal').hide();
			});

			$('#confirm_new_pathogen_group').on('click', function(e) {
				e.preventDefault();
				var $name = $('#new_pathogen_name').val().trim();
				if (!$name) return false;
				var $groups = $('#new_pathogen_groups').val() || [];
				if ($groups.length === 0) {
					alert('Please select at least one group for the new pathogen.');
					return false;
				}
				$('#new_pathogen_group_modal').hide();
				tr_do_add_new_pathogen($name, $groups);
			});
			tr_fill_pathogen_dd();
			tr_fill_antibio_drugcol();
			for (var tkey in $anti_data) {
				var item = $anti_data[tkey];
				tr_fill_antibio_col(item);
				$('#list_pathogen option[value="' + item.te_ID + '"]').prop('disabled', true);
			}
			tr_refresh_pathogen_select2();
			tr_show_hide_anti_row();
		})
	</script>
<?php
}
