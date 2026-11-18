<?php if (!$billing_page || isset($_REQUEST['show_tests'])) { ?>
	<div class="frow <?php if (!tr_get_option('ci_pdf_data', 'pcr_card_images')) echo 'insidebreak' ?>">
		<div>
			<div class="frow">
				<h4>
					<?php if (!empty(tr_get_option('ci_req_settings_pcr', 'abovepanels_title'))) {
						echo tr_get_option('ci_req_settings_pcr', 'abovepanels_title');
					} else {
						echo tr_get_option('ci_req_settings_pcr', 'disable_workercomp') ? 'Menu :' : 'TEST LIST :';
					?>
						<small>
							Please check appropriate <?php echo tr_get_option('ci_req_settings_pcr', 'disable_workercomp') ? 'test menus that address your patients needs.' : 'panels that address your patients needs. Tests can be ordered individually' ?>
						</small>
					<?php } ?>
				</h4>
			</div>

			<input type="hidden" name="has_panel_requests" value="1" />
			<?php
			global $wpdb;
			if (tr_get_option('ci_req_settings_pcr', 'enable_filter')) {
				$results = $wpdb->get_results("select tg_other_options from ci_test_groups where tg_show_req=1 and tg_type = 'pcr'");
				$all_filters = [];
				$priority = [
					"Respiratory",
					"UTI",
					"STI",
					"Vaginosis",
					"Fungal",
					"Wound",
					"GI"
				];

				foreach ($results as $row) {
					$options = json_decode($row->tg_other_options, true);

					if (!empty($options['filters'])) {
						$filters_array = array_map('trim', explode(',', $options['filters']));
						$all_filters = array_merge($all_filters, $filters_array);
					}
				}
				$all_filters = array_unique($all_filters);
				usort($all_filters, function ($a, $b) use ($priority) {
					$posA = array_search($a, $priority);
					$posB = array_search($b, $priority);

					if ($posA === false) $posA = 9999;
					if ($posB === false) $posB = 9999;

					return $posA - $posB;
				});
				if (!empty($all_filters)) {

			?>
					<div class="filter_buttons dontprint flex items-center gap-2 mt-4 flex-wrap" style="margin-bottom:15px">
						<span class="text-sm text-muted-foreground">Filter:</span>
						<div class="filter_btn active faq-filter-btn cursor-pointer inline-flex items-center justify-center rounded-md px-2 py-1 text-sm font-medium border bg-primary text-white" data-filter="all">All</div>
						<?php foreach ($all_filters as $f): ?>
							<div class="filter_btn faq-filter-btn cursor-pointer inline-flex items-center justify-center rounded-md px-2 py-1 text-sm font-medium border bg-muted text-foreground" data-filter="<?php echo esc_attr($f) ?>"><?php echo esc_html($f) ?></div>
						<?php endforeach; ?>
					</div>
			<?php }
			} ?>

			<div id="default_requests" class="frow blabel panel_requests masonry" style="font-size:12px;column-count:<?php echo isset($settings['panel_column']) && $settings['panel_column'] >= 2 ? $settings['panel_column'] : 5 ?>">
				<?php
				$tl = 0;
				$enable_panel_specimen_source = tr_get_option('ci_req_settings_pcr', 'enable_panel_specimen_source');
				$selected_specimen_sources = trpdd_get_batch_meta($bat['mid'], 'pcr', 'specimen_source');
				if (!is_array($selected_specimen_sources)) {
					$selected_specimen_sources = array();
				}

				foreach (tr_pdd_get_profiles_tests_requesteds('pcr', true, false, $bat['doc']) as $group_id => $vl) {
					$has_select_group   = false;
					$has_checked        = false;
					$group_class        = '';
					$group_key          = 'GROUP_' . $group_id;
					$in_group           = @in_array($group_key, $bat['panel_tests_requested']);
					$group              = tr_pdd_get_panel_groups($group_id, 'pcr');
					$options = $group['other_options'];
					$filters = !empty($options['filters']) ? array_map('trim', explode(',', $options['filters'])) : [];

					if (isset($_GET['debug1'])) {
						var_dump($group);
					}

					if (!$group['show'] && !$in_group) continue;


					$group['test_IDs'] = array();
					if ($group['parent'] > 0 && stripos($group['label'], 'reflex') === false) {
						$group_class .= ' parent_group_' . $group['parent'] . ' ';
					}
					if (!empty($group['relate_to'])) {
						$group_class .= ' relate_to_group_' . $group['relate_to'] . ' ';
					}
					if ($group['only_group']) {
						$vl = array();
						$group_class .= ' only_group ';
					}


					if ($in_group) {
						$has_checked = true;
					}
					$group['color'] = trpdd_getContrastColor($group['bgcolor']);
					$group['spec_bgcolor'] = strlen($group['bgcolor']) == 7 ? $group['bgcolor'] . '88' : $group['bgcolor'];
					$group['box_bgcolor'] = strlen($group['bgcolor']) == 7 ? $group['bgcolor'] . '66' : $group['bgcolor'];
					if ($act == 'print') {
						$group['box_bgcolor'] = '#f8f8f8';
						$group['spec_bgcolor'] = '#eeeeee';
					}

					if ($in_group) {
						$group_class .= ' in_group ';
					}

					if (!empty($group['cli_IDs'])) {
						$group_class .= ' by_clinic ';
						if ($in_group) {
							$group_class .= '  cactive';
						}
						foreach (explode(',', $group['cli_IDs']) as $pcid) {
							$group_class .= ' by_clinic cli_id_' . $pcid . ' ';

							if ($bat['cli_ID'] == $pcid) {
								$group_class .= ' cactive ';
							}
						}
					}
					if (!empty($group['not_cli_IDs'])) {
						$group_class .= ' not_by_clinic ';
						foreach (explode(',', $group['not_cli_IDs']) as $pcid) {
							$group_class .= ' not_cli_id_' . $pcid . ' ';

							if ($bat['cli_ID'] == $pcid && !$in_group) {
								$group_class .= ' notcactive ';
							}
						}
					}

					if ($act == 'print' && $bat && $bat['mid'] > 0) {
						ob_start();
					}

					$hide_group_specimen = false;
					$cb_group_class = 'batcustom_profile cb_test group_print_labels';


					if (stripos($group['label'], '(ua)') > 0) {
						$cb_group_class .= ' gua';
						if (isset($settings['pcr_orderable_ua']) && $settings['pcr_orderable_ua']) {
				?>
							<div class="groupparent item" style="background:<?php echo $group['box_bgcolor'] ?>" data-gid="<?php echo $group['id'] ?>" data-filters="<?php echo esc_attr(implode(',', $filters)) ?>">
								<div class="frow box_tests">
									<label style="padding-right:10px">
										<input type="hidden" name="bat[orderable_test]" value="0" />
										<input type="checkbox" class="cb_test orderable_ua" name="bat[orderable_test]" <?php checked($bat['orderable_test'], '-1') ?> value="-1" />
										UA + UTI w/ABX
									</label>

									<label style="padding-right:10px">
										<input type="checkbox" class="cb_test orderable_ua" name="bat[orderable_test]" <?php checked($bat['orderable_test'], '-2') ?> value="-2" />
										UA reflex to UTI w/ABX
									</label>

									<label>
										<input type="checkbox" class="cb_test orderable_ua" name="bat[orderable_test]" <?php checked($bat['orderable_test'], '-3') ?> value="-3" />
										UA reflex to UTI w/ABX reflex
									</label>
								</div>
								<?php
								$hide_group_specimen = true;
								$rs = $wpdb->get_results("select * from ci_test_specimens where ts_group_id={$group_id} and ts_type='pcr'");
								if (count($rs) > 0) {

								?>
									<div class="specimens">
										<label class="title" style="background:<?php echo $group['spec_bgcolor'] ?>;color: <?php echo $group['color'] ?>">SPECIMEN
											TYPE</label>
										<?php
										$specimens = explode(',', strtolower($bat['type_of_specimen']));
										foreach ($rs as $r) {
											$checked_spec = in_array(strtolower($r->ts_name), $specimens);
											if (!$has_checked) {
												$checked_spec = false;
											}
										?>
											<label><input type="checkbox" name="bat[type_of_specimen][]" <?php checked($checked_spec) ?> value="<?php echo $r->ts_name ?>"> <?php echo $r->ts_name ?>
											</label>
										<?php
										}
										?>
									</div>
								<?php
								}
								if ($enable_panel_specimen_source && isset($options['e_specimen_source']) && $options['e_specimen_source']) {
									$specimen_source = isset($selected_specimen_sources[$group_id]) ? $selected_specimen_sources[$group_id] : '';
								?>
									<div class="panel_specimen_source">
										<label class="title" style="background:<?php echo $group['spec_bgcolor'] ?>;color: <?php echo $group['color'] ?>">SPECIMEN
											SOURCE</label>
										<input type="text" name="batmeta[specimen_source][<?php echo $group_id ?>]" value="<?php echo esc_attr($specimen_source) ?>" <?php if ($act != 'print' && $act != 'view' && $in_group) echo 'required' ?> />
									</div>
								<?php
								}
								?>
							</div>
					<?php
						}
					} else if (stripos($group['label'], 'Antibiotic Resistance') !== false && stripos($group['label'], 'UTI') !== false) {
						$cb_group_class .= ' gutiabx';
					} else if (stripos($group['label'], 'uti') > 0 || stripos($group['label'], 'Urinary Tract Infection') !== false) {
						$cb_group_class .= ' guti';
						$group_class .= ' boxguti';
					}

					?>

					<div class="groupparent item <?php echo $group_class ?> " data-gid="<?php echo $group['id'] ?>" style="background:<?php echo $group['box_bgcolor'] ?>" data-filters="<?php echo esc_attr(implode(',', $filters)) ?>">
						<label class="block bold group_label" style="background:<?php echo $group['bgcolor'] ?>;color: <?php echo $group['color'] ?>">
							<input type="checkbox" class="<?php echo $cb_group_class ?>" name="bat[panel_tests_requested][]" value="<?php echo $group_key ?>" data-relate_to="<?php echo $group['relate_to'] ?>" data-grouprint="<?php echo $group['label_name'] ?>" <?php checked($in_group) ?> />
							<?php echo $group['label'] ?>
						</label>
						<div class="frow box_tests">
							<?php
							if (isset($group['other_options']['desc']) && !empty($group['other_options']['desc'])) {
								echo '<div class="panel_desc">' . $group['other_options']['desc'] . '</div>';
							}
							?>
							<?php
							$list_nocheckbox = array();
							$subgroups = array();
							foreach ($vl as $k => $r) {
								$txt = $r->te_name;
								$key = $r->te_ID;
								if ($r->te_font_style == 1) {
									$txt = '<i>' . $txt . '</i>';
								} else if ($r->te_font_style == 2) {
									$txt = '<b>' . $txt . '</b>';
								}
								if (empty($key)) continue;

								if ($group['notestcheckbox']) {
									//$list_nocheckbox[$k] = $r;
									// continue;
								} else {
									$group['test_IDs'][] = $r->te_ID;
								}

								$checked = @in_array($key, $bat['panel_tests_requested']);
								if ($checked) {
									$has_checked = true;
									$has_select_group = true;
								} else if ($act == 'print' && $bat && $bat['mid'] > 0) {
									continue;
								}

								if ($is_hold) {
									$r->class .= ' readonly ';
								}

								if ($r->tg_parent > 0 && !in_array($r->tg_ID, $subgroups) && $group_id != $r->tg_ID) {

									$c_group    = tr_pdd_get_panel_groups($r->tg_ID, 'pcr');

									$subgroups[] = $r->tg_ID;
									$subgrouup_key = 'GROUP_' . $r->tg_ID;
									$in_subgroup =  @in_array($subgrouup_key, $bat['panel_tests_requested']);
									$cb_subgroup_class = '';
							?>
									<div style="font-weight: bold;text-align: center">
										<label>
											<?php if (stripos($r->main_group, 'Antibiotic Resistance Genes (UTI qPCR)') !== false) { ?>
												<input type="checkbox" class="<?php echo $cb_subgroup_class ?>" name="bat[panel_tests_requested][]" value="<?php echo $subgrouup_key ?>" <?php checked($in_subgroup) ?> />
											<?php } ?>
											<?php echo $r->main_group ?>
										</label>

									</div>
									<?php
									if (isset($c_group['other_options']['desc']) && !empty($c_group['other_options']['desc'])) {
										echo '<div class="panel_desc">' . $c_group['other_options']['desc'] . '</div>';
									} ?>
								<?php
								}

								?>
								<label class="c lcb <?php if ($r->te_nocheckbox || $group['notestcheckbox']) echo 'nocb' ?>">
									<?php if (!$r->te_nocheckbox && !$group['notestcheckbox']) { ?>
										<input type="checkbox" class="<?php echo $r->class ?>  cb_test" name="bat[panel_tests_requested][]" value="<?php echo $key ?>" <?php checked($checked) ?> />
									<?php } ?>
									<span><?php echo $txt ?></span>
								</label>
							<?php

							}
							foreach ($list_nocheckbox as $k2 => $r2) {
								$txt = $r2->te_name;
								$key = $r2->te_ID;
							?>
								<label class="c rcb nocb"><span><?php echo $txt ?></span></label>
								<?php
							}

							if (!$hide_group_specimen) {
								$rs = $wpdb->get_results("select * from ci_test_specimens where ts_group_id={$group_id} and ts_type='pcr'");
								if (count($rs) > 0) {

								?>
									<div class="specimens">
										<label class="title" style="background:<?php echo $group['spec_bgcolor'] ?>;color: <?php echo $group['color'] ?>">SPECIMEN
											TYPE</label>
										<?php
										$specimens = explode(',', strtolower($bat['type_of_specimen']));
										foreach ($rs as $r) {
											$checked_spec = in_array(strtolower($r->ts_name), $specimens);
											if (!$has_checked) {
												$checked_spec = false;
											}
										?>
											<label><input type="checkbox" name="bat[type_of_specimen][]" <?php checked($checked_spec) ?> value="<?php echo $r->ts_name ?>"> <?php echo $r->ts_name ?>
											</label>
										<?php
										}
										?>
									</div>
							<?php
								}
							}

							?>
							<?php
							if ($enable_panel_specimen_source && isset($options['e_specimen_source']) && $options['e_specimen_source']) {
								$specimen_source = isset($selected_specimen_sources[$group_id]) ? $selected_specimen_sources[$group_id] : '';
							?>
								<div class="panel_specimen_source">
									<label class="title" style="background:<?php echo $group['spec_bgcolor'] ?>;color: <?php echo $group['color'] ?>">SPECIMEN
										SOURCE</label>
									<input type="text" name="batmeta[specimen_source][<?php echo $group_id ?>]" value="<?php echo esc_attr($specimen_source) ?>" <?php if ($act != 'print' && $act != 'view' && $in_group) echo 'required' ?> />
								</div>
							<?php
							}
							?>
						</div>
					</div>
				<?php
					/*
					//fix no tests in panel but panel is selected
					if (!$group['only_group'] && !$group['notestcheckbox'] && $in_group && !$has_select_group && count($group['test_IDs']) > 0 && !empty($bat['panel_tests_requested_str'])) {
						if (isset($_GET['debug111'])) {
							var_dump($group['test_IDs']);
							var_dump($bat['panel_tests_requested_str']);
							echo $group['label'] . ' has tests but no test selected, auto selecting tests. <br>';
						}
						$old_tests = explode(',', $bat['panel_tests_requested_str']);
						foreach ($group['test_IDs'] as $tid) {
							if (!in_array($tid, $old_tests)) {
								$old_tests[] = $tid;
							}
						}
						$bat['panel_tests_requested_str'] = implode(',', $old_tests);
						$wpdb->update($table_batch, array('panel_tests_requested' => $bat['panel_tests_requested_str']), array('mid' => $bat['mid']));
						echo '<meta http-equiv="refresh" content="0">';
					}
						*/
					if ($act == 'print' && $bat && $bat['mid'] > 0) {
						if ($in_group || $has_select_group) {
							echo ob_get_clean();
						} else {
							ob_get_clean();
						}
					}
				}
				?>

			</div>
			<div style="clear:both;float:none;margin-right:10px">
				<?php
				if (isset($settings['enabel_cultureid']) && $settings['enabel_cultureid']) {
				?>
					<label>
						<input type="hidden" name="bat[culture]" value="0" />
						<input type="checkbox" name="bat[culture]" <?php checked(isset($bat['culture']) ? $bat['culture'] : '') ?> value="1" />
						Culture ID with Reflexive Antibiotic Sensitivity testing
					</label>
				<?php
				}

				if (isset($settings['pcr_orderable_ua']) && $settings['pcr_orderable_ua']) {
				} else if (isset($settings['e_orderable_test']) && $settings['e_orderable_test']) {
				?>
					<div class="col3 tests_group ">
						<label>
							<input type="hidden" name="bat[orderable_test]" value="0" />
							<input type="checkbox" id="orderable_test_cb" class="cb_test" name="bat[orderable_test]" <?php checked($bat['orderable_test']) ?> value="1" />
							Orderable Test
						</label>
					</div>
				<?php
				}

				if ($current_lab['lab_dba_init'] == 'ARC') { ?>
					<div>
						<label>
							<input type="hidden" name="bat[swabbed]" value="0" />
							<input type="checkbox" name="bat[swabbed]" <?php checked(isset($bat['swabbed']) ? $bat['swabbed'] : '') ?> value="1" />
							Swabbed by ARC Diagnostics
						</label>
					</div>
				<?php
				}
				?>
			</div>
		</div>
	</div>
<?php
	if (isset($settings['below_panels']) && !empty($settings['below_panels'])) {
		echo '<div class="below_panels">' . $settings['below_panels'] . '</div>';
	}
} ?>

<script>
	jQuery(document).ready(function($) {
		function uncheck_filter_hidden_panel($panel) {
			$panel.find('input[type="checkbox"]:checked').prop('checked', false);
			$panel.find('.specimens').removeClass('grequired gerror');
		}

		$('.filter_btn').on('apply_filter', function() {
			var filter = $(this).data('filter');

			$('.groupparent.item').each(function() {
				var gfilters = $(this).data('filters');
				const isNotActive = $(this).hasClass('notcactive');
				gfilters = gfilters ? gfilters.split(',').map(function(f) {
					return f.trim();
				}) : [];

				if (filter === 'all') {
					if (!isNotActive) {
						$(this).removeClass('filter_hidden').show();
					}
				} else {
					if (gfilters.includes(filter)) {
						if (!isNotActive) {
							$(this).removeClass('filter_hidden').show();
						}
					} else {
						if (!isNotActive) {
							uncheck_filter_hidden_panel($(this));
							$(this).addClass('filter_hidden').hide();
						}
					}
				}
			});
		}).on('click', function() {
			// remove active class
			$('.filter_btn').removeClass('active bg-primary text-white');
			$(this).addClass('active bg-primary text-white');
			$(this).trigger('apply_filter');
		});
	});
</script>
