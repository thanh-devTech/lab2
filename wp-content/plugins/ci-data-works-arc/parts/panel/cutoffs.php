<?php
global $wpdb;

$group_id = isset($panel['te_group_id']) ? $panel['te_group_id'] : 0;
$specimens = array();
if ($group_id > 0) {
	$group_parent_id = $wpdb->get_var("select tg_parent from ci_test_groups where tg_ID = {$group_id} ");
	if ($group_parent_id == 0) {
		$group_parent_id = $group_id;
	}
	$specimens = $wpdb->get_results("select ts_ID, ts_name 
	from ci_test_specimens 
	where ts_type = '{$tes_type}' 
	and ts_group_id = {$group_parent_id} 
	order by ts_name asc");
}


if (!in_array($tes_type, array('study'))) {

?>
	<hr />
	<br>

	<div class="frow blabel">
		<?php
		if ($is_rpp && !tr_get_option('ci_result_settings_pcr', 'rs_hml_amp_cq')) {
		?>
			<div class="col10">
				<h4>Ct</h4>
			</div>
			<div class="col10">
				<h4>Amp Score</h4>
			</div>
			<div class="col10">
				<h4>CQ Conf</h4>
			</div>
			<?php
		} else {
			if (isset($result_settings['enable_lqc']) && $result_settings['enable_lqc']) {
			?>
				<div class="col16">
					<h4>LQC</h4>
				</div>
			<?php
			}
			?>
			<div class="col16">
				<h4>Invalid</h4>
			</div>
			<?php if ($tes_type == 'blood') { ?>
				<div class="col16">
					<h4 title="Lower Limit">LLOQ</h4>
				</div>
				<?php if ($panel['te_is_detected'] != 6) { ?>
					<div class="col12">
						<h4>Critical Low</h4>
					</div>
				<?php } ?>
			<?php } else if ($tes_type == 'pcr' && ($result_settings['enable_verylh'] && $panel['te_is_detected'] != 4)) {
			?>
				<div class="col16">
					<h4>very Low</h4>
				</div>
			<?php
			} ?>
			<?php if (!($tes_type == 'blood' && $panel['te_is_detected'] == 6)) { ?>
				<div class="col16">
					<h4><?php
						if ($tes_type == 'pcr' && $panel['te_is_detected'] == 4) {
							echo 'Indeterminate';
						} else if ($tes_type == 'blood' && $panel['te_is_detected'] == 4) {
							echo 'Equivocal';
						} else {
							echo $tes_type == 'tox' ? 'Cutoff' : 'Low';
						}
						?></h4>
				</div>
			<?php } ?>
			<?php if ($tes_type != 'tox') { ?>
				<div class="col14">
					<h4><?php
						if ($tes_type == 'blood' && $panel['te_is_detected'] == 4) {
							echo 'Negative';
						} else {
							echo $tes_type == 'blood' ? 'Normal' : 'Medium';
						}
						?></h4>
				</div>
			<?php } ?>
			<div class="col16">
				<h4><?php
					if ($tes_type == 'blood' && $panel['te_is_detected'] == 6) {
						echo 'At Risk';
					} else if ($tes_type == 'blood' && $panel['te_is_detected'] == 3) {
						echo 'Reactive';
					} else if ($tes_type == 'blood' && $panel['te_is_detected'] == 4) {
						echo 'Positive';
					} else {
						echo 'High';
					}
					?></h4>
			</div>
			<?php if ($tes_type == 'blood') { ?>
				<div class="col12">
					<h4>
						<?php
						echo $panel['te_is_detected'] == 6 ? 'High' : 'Critical High';
						?>
					</h4>
				</div>
				<div class="col16">
					<h4 title="Upper Limit">ULOQ</h4>
				</div>
			<?php } else if ($tes_type == 'pcr' && ($result_settings['enable_verylh'] && $panel['te_is_detected'] != 4)) {
			?>
				<div class="col16">
					<h4>very High</h4>
				</div>
			<?php
			} ?>
			<?php if ($tes_type == 'tox') { ?>
				<div class="col12">
					<h4>LQC</h4>
				</div>
				<?php
				if ($result_settings['enable_tox_mqc']) {
				?>
					<div class="col12">
						<h4>MQC</h4>
					</div>
				<?php
				}
				?>
				<div class="col12">
					<h4>HQC</h4>
				</div>
			<?php } ?>

			<?php
			if ($is_rpp && tr_get_option('ci_result_settings_pcr', 'rs_hml_amp_cq')) {
			?>
				<div class="col14">
					<h4>Amp Score</h4>
				</div>
				<div class="col14">
					<h4>CQ Conf</h4>
				</div>
			<?php
			}

			if ($result_settings['enable_rangebyspec']) {
			?>
				<div class="col14">
					<h4>Specimen</h4>
				</div>
		<?php
			}
		} //end not rpp
		?>
		<div class="col12">
			<h4>Instrument</h4>
		</div>
		<?php if ($tes_type != 'tox') { ?>
			<div class="col16">
				<h4>Gende<a class="make_all_male">r</a></h4>
			</div>
			<div class="col16">
				<h4>Age(Yr)</h4>
			</div>
		<?php } ?>
		<div class="col11">
			<h4>Start Date</h4>
		</div>
		<div class="col10">
			<h4>End Date</h4>
		</div>
	</div>
	<ol id="doctors_dv" class="more">
		<?php
		$max_j = count($cutoffs) > 1 ? count($cutoffs) : 1;
		for ($j = 0; $j < $max_j; $j++) {
			if (isset($cutoffs[$j]['cu_gender']) && $cutoffs[$j]['cu_gender']) {
				$has_gender[$cutoffs[$j]['cu_gender']] = true;
			}
			if (!isset($cutoffs[$j]['cu_start']) || $cutoffs[$j]['cu_start'] == '0000-00-00') {
				$cutoffs[$j]['cu_start'] = '';
			}
			if (!isset($cutoffs[$j]['cu_end']) || $cutoffs[$j]['cu_end'] == '0000-00-00') {
				$cutoffs[$j]['cu_end'] = '';
			}
		?>
			<li class="frow blabel pdoctorrow <?php if ($j == 0) echo 'default' ?>" data-gender="<?php echo isset($cutoffs[$j]['cu_gender']) ? $cutoffs[$j]['cu_gender'] : '' ?>">
				<div class="frow">
					<?php
					if ($is_rpp && !tr_get_option('ci_result_settings_pcr', 'rs_hml_amp_cq')) {
					?>
						<div class="col10">
							<input type="text" name="cutoffs[<?php echo $j ?>][cu_upper_limit]" value="<?php echo isset($cutoffs[$j]['cu_upper_limit']) ? trpdd_show_symbol($cutoffs[$j]['cu_upper_limit']) : '' ?>" maxlength="20" />
						</div>
						<div class="col10">
							<input type="text" name="cutoffs[<?php echo $j ?>][cu_amp_score]" value="<?php echo isset($cutoffs[$j]['cu_amp_score']) ? trpdd_show_symbol($cutoffs[$j]['cu_amp_score']) : '' ?>" maxlength="20" />
						</div>
						<div class="col10">
							<input type="text" name="cutoffs[<?php echo $j ?>][cu_cq]" value="<?php echo isset($cutoffs[$j]['cu_cq']) ? trpdd_show_symbol($cutoffs[$j]['cu_cq']) : '' ?>" maxlength="20" />
						</div>
						<?php
					} else {
						if (isset($result_settings['enable_lqc']) && $result_settings['enable_lqc']) {
						?>
							<div class="col16">
								<input type="text" name="cutoffs[<?php echo $j ?>][cu_lqc]" value="<?php echo isset($cutoffs[$j]['cu_lqc']) ? trpdd_show_symbol($cutoffs[$j]['cu_lqc']) : '' ?>" maxlength="20" />
							</div>
						<?php
						}
						?>
						<div class="col16">
							<input type="text" name="cutoffs[<?php echo $j ?>][cu_invalid]" value="<?php echo isset($cutoffs[$j]['cu_invalid']) ? trpdd_show_symbol($cutoffs[$j]['cu_invalid']) : '' ?>" maxlength="20" />
							<?php
							if (isset($result_settings['e_concentration']) && $result_settings['e_concentration']) {
								echo '<div class="small" style="padding-top:10px">Concentration</div>';
							}
							?>
						</div>
						<?php if ($tes_type == 'blood') { ?>
							<div class="col16">
								<input type="text" name="cutoffs[<?php echo $j ?>][cu_ll]" value="<?php echo isset($cutoffs[$j]['cu_ll']) ? trpdd_show_symbol($cutoffs[$j]['cu_ll']) : '' ?>" maxlength="20" />
							</div>
							<?php if ($panel['te_is_detected'] != 6) { ?>
								<div class="col12">
									<input type="text" name="cutoffs[<?php echo $j ?>][cu_critical_low]" value="<?php echo isset($cutoffs[$j]['cu_critical_low']) ? trpdd_show_symbol($cutoffs[$j]['cu_critical_low']) : '' ?>" maxlength="20" />
								</div>
							<?php } ?>
						<?php } else if ($tes_type == 'pcr' && ($result_settings['enable_verylh'] && $panel['te_is_detected'] != 4)) {
						?>
							<div class="col16">
								<input type="text" name="cutoffs[<?php echo $j ?>][cu_critical_low]" value="<?php echo isset($cutoffs[$j]['cu_critical_low']) ? trpdd_show_symbol($cutoffs[$j]['cu_critical_low']) : '' ?>" maxlength="20" />
								<?php
								if (isset($result_settings['e_concentration']) && $result_settings['e_concentration']) {
								?>
									<input type="text" name="cutoffs[<?php echo $j ?>][cu_critical_low_concen]" value="<?php echo isset($cutoffs[$j]['cu_critical_low_concen']) ? trpdd_show_symbol($cutoffs[$j]['cu_critical_low_concen']) : '' ?>" maxlength="20" />
								<?php
								}
								?>
							</div>
						<?php
						} ?>

						<?php if (!($tes_type == 'blood' && $panel['te_is_detected'] == 6)) { ?>
							<div class="col16">
								<input type="text" name="cutoffs[<?php echo $j ?>][cu_cutoff]" value="<?php echo isset($cutoffs[$j]['cu_cutoff']) ? trpdd_show_symbol($cutoffs[$j]['cu_cutoff']) : '' ?>" maxlength="20" />
								<?php
								if (isset($result_settings['e_concentration']) && $result_settings['e_concentration']) {
								?>
									<input type="text" name="cutoffs[<?php echo $j ?>][cu_cutoff_concen]" value="<?php echo isset($cutoffs[$j]['cu_cutoff_concen']) ? trpdd_show_symbol($cutoffs[$j]['cu_cutoff_concen']) : '' ?>" maxlength="20" />
								<?php
								}
								?>
							</div>
						<?php } ?>
						<?php if ($tes_type != 'tox') { ?>
							<div class="col14">
								<input type="text" name="cutoffs[<?php echo $j ?>][cu_medium]" value="<?php echo isset($cutoffs[$j]['cu_medium']) ? trpdd_show_symbol($cutoffs[$j]['cu_medium']) : '' ?>" maxlength="20" />
								<?php
								if (isset($result_settings['e_concentration']) && $result_settings['e_concentration']) {
								?>
									<input type="text" name="cutoffs[<?php echo $j ?>][cu_medium_concen]" value="<?php echo isset($cutoffs[$j]['cu_medium_concen']) ? trpdd_show_symbol($cutoffs[$j]['cu_medium_concen']) : '' ?>" maxlength="20" />
								<?php
								}
								?>
							</div>
						<?php } ?>
						<div class="col16">
							<input type="text" name="cutoffs[<?php echo $j ?>][cu_upper_limit]" value="<?php echo isset($cutoffs[$j]['cu_upper_limit']) ? trpdd_show_symbol($cutoffs[$j]['cu_upper_limit']) : '' ?>" maxlength="20" />
							<?php
							if (isset($result_settings['e_concentration']) && $result_settings['e_concentration']) {
							?>
								<input type="text" name="cutoffs[<?php echo $j ?>][cu_upper_limit_concen]" value="<?php echo isset($cutoffs[$j]['cu_upper_limit_concen']) ? trpdd_show_symbol($cutoffs[$j]['cu_upper_limit_concen']) : '' ?>" maxlength="20" />
							<?php
							}
							?>
						</div>
						<?php if ($tes_type == 'blood') { ?>
							<div class="col12">
								<input type="text" name="cutoffs[<?php echo $j ?>][cu_critical_high]" value="<?php echo isset($cutoffs[$j]['cu_critical_high']) ? trpdd_show_symbol($cutoffs[$j]['cu_critical_high']) : '' ?>" maxlength="20" />
							</div>
							<div class="col16">
								<input type="text" name="cutoffs[<?php echo $j ?>][cu_ul]" value="<?php echo isset($cutoffs[$j]['cu_ul']) ? trpdd_show_symbol($cutoffs[$j]['cu_ul']) : '' ?>" maxlength="20" />
							</div>
						<?php } else if ($tes_type == 'pcr' && ($result_settings['enable_verylh'] && $panel['te_is_detected'] != 4)) {
						?>
							<div class="col16">
								<input type="text" name="cutoffs[<?php echo $j ?>][cu_critical_high]" value="<?php echo isset($cutoffs[$j]['cu_critical_high']) ? trpdd_show_symbol($cutoffs[$j]['cu_critical_high']) : '' ?>" maxlength="20" />
								<?php
								if (isset($result_settings['e_concentration']) && $result_settings['e_concentration']) {
								?>
									<input type="text" name="cutoffs[<?php echo $j ?>][cu_critical_high_concen]" value="<?php echo isset($cutoffs[$j]['cu_critical_high_concen']) ? trpdd_show_symbol($cutoffs[$j]['cu_critical_high_concen']) : '' ?>" maxlength="20" />
								<?php
								}
								?>
							</div>
						<?php
						} ?>
						<?php if ($tes_type == 'tox') { ?>
							<div class="col12">
								<input type="text" name="cutoffs[<?php echo $j ?>][cu_lqc]" value="<?php echo isset($cutoffs[$j]['cu_lqc']) ? trpdd_show_symbol($cutoffs[$j]['cu_lqc']) : '' ?>" />
							</div>
							<?php
							if ($result_settings['enable_tox_mqc']) {
							?>
								<div class="col12">
									<input type="text" name="cutoffs[<?php echo $j ?>][cu_medium]" value="<?php echo isset($cutoffs[$j]['cu_medium']) ? trpdd_show_symbol($cutoffs[$j]['cu_medium']) : '' ?>" maxlength="20" />
								</div>
							<?php
							}
							?>
							<div class="col12">
								<input type="text" name="cutoffs[<?php echo $j ?>][cu_hqc]" value="<?php echo isset($cutoffs[$j]['cu_hqc']) ? trpdd_show_symbol($cutoffs[$j]['cu_hqc']) : '' ?>" />
							</div>
						<?php } ?>
						<?php
						if ($is_rpp && tr_get_option('ci_result_settings_pcr', 'rs_hml_amp_cq')) {
						?>
							<div class="col14">
								<input type="text" name="cutoffs[<?php echo $j ?>][cu_amp_score]" value="<?php echo isset($cutoffs[$j]['cu_amp_score']) ? trpdd_show_symbol($cutoffs[$j]['cu_amp_score']) : '' ?>" maxlength="20" />
							</div>
							<div class="col14">
								<input type="text" name="cutoffs[<?php echo $j ?>][cu_cq]" value="<?php echo isset($cutoffs[$j]['cu_cq']) ? trpdd_show_symbol($cutoffs[$j]['cu_cq']) : '' ?>" maxlength="20" />
							</div>
						<?php
						}
						if ($result_settings['enable_rangebyspec']) {
						?>
							<div class="col14">
								<select name="cutoffs[<?php echo $j ?>][cu_type_of_specimen]">
									<option value="">All type</option>
									<?php
									foreach ($specimens as $spec) {
										$selected = isset($cutoffs[$j]['cu_type_of_specimen']) && $spec->ts_ID == $cutoffs[$j]['cu_type_of_specimen'] ? 'selected' : '';
										echo '<option value="' . $spec->ts_ID . '" ' . $selected . '>' . $spec->ts_name . '</option>';
									}
									?>
								</select>
							</div>
					<?php
						}
					} //end not rpp
					?>
					<div class="col12">
						<input type="text" name="cutoffs[<?php echo $j ?>][cu_ms]" value="<?php
																							if (isset($cutoffs[$j]['cu_ms']) && $cutoffs[$j]['cu_ms'] !== '') {
																								echo $cutoffs[$j]['cu_ms'];
																							} else if (isset($act) && $act == 'bulk_edit_tests') {
																								echo '*';
																							}
																							?>" />
					</div>

					<?php if ($tes_type != 'tox') { ?>
						<div class="col16">
							<select name="cutoffs[<?php echo $j ?>][cu_gender]">
								<option value=""></option>
								<?php
								foreach (array('F', 'M') as $g) {
									$selected = isset($cutoffs[$j]['cu_gender']) && $g == $cutoffs[$j]['cu_gender'] ? 'selected' : '';
									echo '<option value="' . $g . '" ' . $selected . '>' . $g . '</option>';
								}
								?>
							</select>
						</div>
						<div class="col16">
							<input type="text" name="cutoffs[<?php echo $j ?>][cu_age]" title="<?php echo isset($cutoffs[$j]['order_age']) ? $cutoffs[$j]['order_age'] : '' ?>" value="<?php echo isset($cutoffs[$j]['cu_age']) ? trpdd_show_symbol($cutoffs[$j]['cu_age']) : '' ?>" maxlength="20" />
						</div>
					<?php } ?>
					<div class="col11">
						<input type="search" class="hasdate" autocomplete="off" data-write="true" name="cutoffs[<?php echo $j ?>][cu_start]" value="<?php echo isset($cutoffs[$j]['cu_start']) ? $cutoffs[$j]['cu_start'] : '' ?>" style="font-size:12px" />
					</div>
					<div class="col10">
						<input type="search" class="hasdate" style="width:100px;font-size:12px" autocomplete="off" data-write="true" name="cutoffs[<?php echo $j ?>][cu_end]" value="<?php echo isset($cutoffs[$j]['cu_end']) ? $cutoffs[$j]['cu_end'] : '' ?>" />
						<?php if (!isset($act) || $act != 'bulk_edit_tests') { ?>
							<a style="position:absolute;" class="rm_doctor_row button">-</a>
						<?php } ?>
					</div>
				</div>
			</li>
		<?php } ?>
	</ol>
	<?php if (!isset($act) || $act != 'bulk_edit_tests') { ?>
		<div class="frow">
			<a class="button add_doctor_row" data-id="<?php echo $j ?>">+</a>
			<?php
			if ($has_gender['M'] && !$has_gender['F']) {
				echo '<a class="button duplicate_gender" data-gender="F">Duplicate Gender Rows</a>';
			} else if (!$has_gender['M'] && $has_gender['F']) {
				echo '<a class="button duplicate_gender" data-gender="M">Duplicate Gender Rows</a>';
			}
			?>
			<a class="button thickbox" href="#TB_inline&inlineId=add_multilines&width=400&height=250">Add Multi</a>

			<span style="padding-left:15px">(Normal is out of range above)</span>

		</div>
	<?php } ?>


	<div class="frow">
		<div>&nbsp;</div>
	</div>


<?php
} //not study
