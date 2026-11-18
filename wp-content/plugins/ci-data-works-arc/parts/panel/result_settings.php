<?php
global $wpdb, $is_ci_admin;


$act    = isset($act) ? $act : '';
if (!isset($tes_type)) {
	$tes_type  = !empty($_REQUEST['type']) ? $_REQUEST['type'] : 'tox';
}

$key        =  'ci_result_settings_' . $tes_type;
$options    = get_option($key);

$result_dir     = tr_get_result_upload_dir();

if (isset($_POST['import_settings'])) {
	if (isset($_FILES['settings_import_file']) && !empty($_FILES['settings_import_file']['tmp_name'])) {
		$import = json_decode(file_get_contents($_FILES['settings_import_file']['tmp_name']), true);
		$import_options = array();

		if (is_array($import) && isset($import['option_key']) && $import['option_key'] == $key && isset($import['options']) && is_array($import['options'])) {
			$import_options = $import['options'];
		}

		if (!empty($import_options)) {
			$logdata = $import_options;
			$logdata['old'] = $options;
			update_option($key, $import_options);
			trpdd_log_user(0, 'import_result_st_' . $tes_type, $logdata);
			Tr_Session_Class::add('Imported Successfully!');
			$options = get_option($key);
		} else {
			Tr_Session_Class::add('Invalid import file.');
		}
	} else {
		Tr_Session_Class::add('Please select an import file.');
	}
}

if (isset($_POST['options'])) {
	$options = tr_pdd_stripslashes_deep($_POST['options']);

	//upload file
	if (isset($_FILES['pdf_logo']) && !empty($_FILES['pdf_logo']['name'])) {
		$filename = 'pdflogo.jpg';

		if (stripos($_FILES['pdf_logo']['type'], 'image/') !== false) {
			if (move_uploaded_file($_FILES['pdf_logo']['tmp_name'], $result_dir['path'] . '/' . $filename)) {
				$options['pdf_logo'] = $filename;
			}
		}
	}

	//upload file 2
	if (isset($_FILES['pdf_logo2']) && !empty($_FILES['pdf_logo2']['name'])) {
		$filename = 'pdflogo2.jpg';

		if (stripos($_FILES['pdf_logo2']['type'], 'image/') !== false) {
			if (move_uploaded_file($_FILES['pdf_logo2']['tmp_name'], $result_dir['path'] . '/' . $filename)) {
				$options['pdf_logo2'] = $filename;
			}
		}
	}

	//upload file 2
	if (isset($_FILES['pdf_logocopy']) && !empty($_FILES['pdf_logocopy']['name'])) {
		$filename = 'pdflogocopy.jpg';

		if (stripos($_FILES['pdf_logocopy']['type'], 'image/') !== false) {
			if (move_uploaded_file($_FILES['pdf_logocopy']['tmp_name'], $result_dir['path'] . '/' . $filename)) {
				$options['pdf_logocopy'] = $filename;
			}
		}
	}
	//upload patient guide pdf
	if (isset($_FILES['abx_patient_guide_pdf']) && !empty($_FILES['abx_patient_guide_pdf']['name'])) {
		$ext = strtolower(pathinfo($_FILES['abx_patient_guide_pdf']['name'], PATHINFO_EXTENSION));
		if ($ext === 'pdf' || stripos($_FILES['abx_patient_guide_pdf']['type'], 'pdf') !== false) {
			$result_pdf = tr_save_result_upload('abx_patient_guide', $_FILES['abx_patient_guide_pdf']);
			if (!empty($result_pdf)) {
				$options['abx_patient_guide_pdf'] = $result_pdf;
			}
		}
	}

	update_option($key, $options);

	trpdd_log_form('resultst' . $btype);

	Tr_Session_Class::add('Updated Successfully!');

	if ($options['enable_rs_ct_amp_cq'] || $options['rs_hml_amp_cq']) {
		$wpdb->query("alter table ci_results_pcr add column cq varchar(10);");
		$wpdb->query("alter table ci_results_pcr add column amp_score varchar(10);");
	}
}



?>
<div class="wrap">
	<h2>Result Settings: <?php echo tr_pdd_get_typename($tes_type) ?>
		<a href="#" class="add-new-h2 trpdd-settings-import-export">Export/Import</a>
	</h2>
	<?php Tr_Session_Class::show() ?>

	<br><br>

	<div id="trpdd_settings_import_export_modal" style="display:none;position:fixed;z-index:100000;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.35);">
		<div style="background:#fff;width:420px;max-width:90%;margin:120px auto 0;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.2);">
			<h3 style="margin-top:0;">Export/Import Settings</h3>
			<p>
				<a href="#" class="button button-primary trpdd-export-settings">Export</a>
			</p>
			<form method="post" enctype="multipart/form-data">
				<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
					<input type="file" name="settings_import_file" accept="application/json,.json" />
					<input type="submit" name="import_settings" class="button" value="Import" />
					<a href="#" class="button trpdd-close-settings-import-export">Close</a>
				</div>
			</form>
		</div>
	</div>
	<?php
	if (!$is_ci_admin) {
		echo '<style>.onlyadmin{display:none}</style>';
	}
	?>

	<form method="post" enctype="multipart/form-data">

		<div class="frow">
			<div class="col2">

				<?php
				if (in_array($tes_type, array('blood'))) {
				?>

					<div class="frow">
						<input type="hidden" name="options[show_questions]" value="0" />
						<label><input type="checkbox" name="options[show_questions]" value="1" <?php checked(isset($options['show_questions']) ? $options['show_questions'] : '') ?> />
							Show Questions </label>
					</div>

					<div class="frow">
						<input type="hidden" name="options[show_genotype]" value="0" />
						<label><input type="checkbox" name="options[show_genotype]" value="1" <?php checked($options['show_genotype']) ?> />
							Show Genotype in pdf </label>
					</div>
					<div class="frow">
						<input type="hidden" name="options[onlyposua]" value="0" />
						<label><input type="checkbox" name="options[onlyposua]" value="1" <?php checked($options['onlyposua']) ?> />
							Only show Pos for Urinalisys / UA </label>
					</div>
					<div class="frow">
						<input type="hidden" name="options[result_cancel_rejected]" value="0" />
						<label><input type="checkbox" name="options[result_cancel_rejected]" value="1" <?php checked($options['result_cancel_rejected']) ?> />
							Enable Cancel/Rejected/Comments </label>
					</div>

					<div class="frow">
						<input type="hidden" name="options[result_import_mf]" value="0" />
						<label><input type="checkbox" name="options[result_import_mf]" value="1" <?php checked($options['result_import_mf']) ?> />
							Enable Import FA/MF </label>
					</div>
					<div class="frow">
						<input type="hidden" name="options[new_results_import]" value="0" />
						<label><input type="checkbox" name="options[new_results_import]" value="1" <?php checked($options['new_results_import']) ?> />
							Enable Import Results Multi test in one line</label>
					</div>

				<?php
				} else if ($tes_type == 'pcr') {
				?>
					<div class="frow">
						<input type="hidden" name="options[enable_rs_ct_amp_cq]" value="0" />
						<label><input type="checkbox" name="options[enable_rs_ct_amp_cq]" value="1" <?php checked($options['enable_rs_ct_amp_cq']) ?> />
							Enable CT, Amp & CQ </label>
					</div>
					<div class="frow">
						<input type="hidden" name="options[rs_hml_amp_cq]" value="0" />
						<label><input type="checkbox" name="options[rs_hml_amp_cq]" value="1" <?php checked($options['rs_hml_amp_cq']) ?> />
							Amp & CQ with High/Med/Low</label>
					</div>
					<div class="frow">
						<input type="hidden" name="options[e_concentration]" value="0" />
						<label><input type="checkbox" name="options[e_concentration]" value="1" <?php checked($options['e_concentration']) ?> />
							enable Concentration show (below cutoffs)</label>
					</div>
					<div class="frow">
						<input type="hidden" name="options[pos_moderate]" value="0" />
						<label><input type="checkbox" name="options[pos_moderate]" value="1" <?php checked($options['pos_moderate']) ?> />
							Use "Detected-Moderate" instead of "Detected-Medium" </label>
					</div>
					<div class="frow">
						<input type="hidden" name="options[enable_i_fam_gene_hex]" value="0" />
						<label><input type="checkbox" name="options[enable_i_fam_gene_hex]" value="1" <?php checked($options['enable_i_fam_gene_hex']) ?> />
							Enable Import Covid/IC/FluB/FluA same row</label>
					</div>
					<div class="frow">
						<input type="hidden" name="options[enable_solana]" value="0" />
						<label><input type="checkbox" name="options[enable_solana]" value="1" <?php checked($options['enable_solana']) ?> />
							Enable import Solana</label>
					</div>
					<div class="frow">
						<input type="hidden" name="options[enable_im_oncosu]" value="0" />
						<label><input type="checkbox" name="options[enable_im_oncosu]" value="1" <?php checked($options['enable_im_oncosu']) ?> />
							Enable import oncosuretesting</label>
					</div>
					<div class="frow">
						<input type="hidden" name="options[enable_ref_postest]" value="0" />
						<label><input type="checkbox" name="options[enable_ref_postest]" value="1" <?php checked($options['enable_ref_postest']) ?> />
							Test Setup Reflex</label>
					</div>
					<div class="frow">
						<input type="hidden" name="options[enable_verylh]" value="0" />
						<label><input type="checkbox" name="options[enable_verylh]" value="1" <?php checked($options['enable_verylh']) ?> />
							Turn on Semi-quant 5</label>
					</div>
					<div class="frow">
						<input type="hidden" name="options[enable_rangebyspec]" value="0" />
						<label><input type="checkbox" name="options[enable_rangebyspec]" value="1" <?php checked($options['enable_rangebyspec']) ?> />
							Enable Range by Specimen</label>
					</div>
					<div class="frow">
						<input type="hidden" name="options[enable_lqc]" value="0" />
						<label><input type="checkbox" name="options[enable_lqc]" value="1" <?php checked($options['enable_lqc']) ?> />
							Enable LQC</label>
					</div>

					<div class="frow">
						<input type="hidden" name="options[range_not_col]" value="0" />
						<label><input type="checkbox" name="options[range_not_col]" value="1" <?php checked($options['range_not_col']) ?> />
							Enable "Not Detected" range column all tests</label>
					</div>

					<div class="frow">
						<input type="hidden" name="options[default_empty_neg]" value="0" />
						<label><input type="checkbox" name="options[default_empty_neg]" value="1" <?php checked($options['default_empty_neg']) ?> />
							Default "Not-Detected" for blank value</label>
					</div>

					<div class="frow">
						<input type="hidden" name="options[default_empty_pos_ctrl]" value="0" />
						<label><input type="checkbox" name="options[default_empty_pos_ctrl]" value="1" <?php checked($options['default_empty_pos_ctrl']) ?> />
							Default Control to "Detected" for blank value</label>
					</div>

					<div class="frow">
						<input type="hidden" name="options[e_listby_plate]" value="0" />
						<label><input type="checkbox" name="options[e_listby_plate]" value="1" <?php checked($options['e_listby_plate']) ?> />
							Enable List by Plate</label>
					</div>

					<div class="frow">
						<input type="hidden" name="options[result_ins_filec]" value="0" />
						<label><input type="checkbox" name="options[result_ins_filec]" value="1" <?php checked($options['result_ins_filec']) ?> />
							Use Instrument Name in file content</label>
					</div>
					<div class="frow">
						<input type="hidden" name="options[abx_not_group_urea]" value="0" />
						<label><input type="checkbox" name="options[abx_not_group_urea]" value="1" <?php checked($options['abx_not_group_urea']) ?> />
							ABX dont group 'ureaplasma urealyticum','mycoplasma hominis'</label>
					</div>
					<div class="frow">
						<input type="hidden" name="options[e_reruntest]" value="0" />
						<label><input type="checkbox" name="options[e_reruntest]" value="1" <?php checked($options['e_reruntest']) ?> />
							Enable Rerun by Test Control</label>
					</div>
					<div class="frow">
						<input type="hidden" name="options[e_rerunttoinvalid]" value="0" />
						<label><input type="checkbox" name="options[e_rerunttoinvalid]" value="1" <?php checked($options['e_rerunttoinvalid']) ?> />
							Change Tests to Invalid when rerun</label>
					</div>


					<div class="frow">
						<input type="hidden" name="options[e_leyvey]" value="0" />
						<label><input type="checkbox" name="options[e_leyvey]" value="1" <?php checked($options['e_leyvey']) ?> />
							Enable PCR Leyvey Jennings</label>
					</div>

					<div class="frow">
						<input type="hidden" name="options[e_qc]" value="0" />
						<label><input type="checkbox" name="options[e_qc]" value="1" <?php checked($options['e_qc']) ?> />
							Enable PCR QC</label>
					</div>
					<div class="frow">
						<input type="hidden" name="options[edit_show_iptest]" value="0" />
						<label><input type="checkbox" name="options[edit_show_iptest]" value="1" <?php checked($options['edit_show_iptest']) ?> />
							Show Test in file without ordered</label>
					</div>
					<div class="frow">
						<input type="hidden" name="options[bulk_approve_date]" value="0" />
						<label><input type="checkbox" name="options[bulk_approve_date]" value="1" <?php checked($options['bulk_approve_date']) ?> />
							Show Bulk edit Date when approve all</label>
					</div>

					<div class="frow">
						<input type="hidden" name="options[cli_pha_guid]" value="0" />
						<label><input type="checkbox" name="options[cli_pha_guid]" value="1" <?php checked($options['cli_pha_guid']) ?> />
							Enable "Clinical Pharmacy Guidance"</label>
					</div>


					<div class="frow">
						<input type="hidden" name="options[pdf_obx_source]" value="0" />
						<label><input type="checkbox" name="options[pdf_obx_source]" value="1" <?php checked($options['pdf_obx_source']) ?> />
							Put OBX Source to PDF</label>
					</div>

					<div class="frow">
						<input type="hidden" name="options[time_pending]" value="0" />
						<label><input type="checkbox" name="options[time_pending]" value="1" <?php checked($options['time_pending']) ?> />
							Show Time/Pending columns in Pending</label>
					</div>
					<div class="frow">
						<input type="hidden" name="options[full_editor_mic]" value="0" />
						<label><input type="checkbox" name="options[full_editor_mic]" value="1" <?php checked($options['full_editor_mic']) ?> />
							Full editor for Manual Antibiotics Result</label>
					</div>

					<div class="frow">
						<label>MIC style</label>
						<select name="options[e_mic2]">
							<option value="0" <?php selected($options['e_mic2'], 0) ?>>MIC #0</option>
							<option value="1" <?php selected($options['e_mic2'], 1) ?>>MIC #1</option>
							<option value="2" <?php selected($options['e_mic2'], 2) ?>>MIC #2</option>
						</select>
					</div>

					<div class="frow">
						<label>Wound Source Meta Key in pdf</label>
						<input type="text" name="options[wound_pdf_source]" value="<?php echo $options['wound_pdf_source'] ?>" />

					</div>

					<div class="frow">
						<label>Style #5 Start Date</label>
						<input type="text" class="hasdate" name="options[start_style_5]" value="<?php echo $options['start_style_5'] ?>" />

					</div>
					<div class="frow">
						<label>Start Date to remove "CLINICAL PHARMACY GUIDANCE"</label>
						<input type="text" class="hasdate" name="options[start_rm_pha_guid]" value="<?php echo $options['start_rm_pha_guid'] ?>" />

					</div>


					<?php
					$parent_groups = $wpdb->get_results("select tg_ID, tg_name 
						from ci_test_groups 
						where tg_type='pcr'
						and tg_parent = 0 
						order by tg_name asc");
					?>
					<div class="frow">
						<label>Clinic Statistics Group</label>
						<select name="options[clinic_statistics_groupid]">
							<?php
							echo '<option value="">Select</option>';
							foreach ($parent_groups as $group) {
								$selected = $group->tg_ID == $options['clinic_statistics_groupid'] ? 'selected' : '';
								echo '<option value="' . $group->tg_ID . '" ' . $selected . '>' . $group->tg_name . '</option>';
							}
							?>
						</select>
					</div>


					<div class="frow">
						<label>Accession ID text (in pdf):</label>
						<input type="text" name="options[assentxt]" value="<?php echo $options['assentxt'] ?>" />
					</div>
					<div class="frow">
						<label>ABX Disclaimer:</label>
						<textarea name="options[abx_disclamer]"><?php echo $options['abx_disclamer'] ?></textarea>
					</div>
					<div class="frow">
						<label>Import Target Column name:</label>
						<input type="text" name="options[import_target]" value="<?php echo $options['import_target'] ?>" />
					</div>
					<div class="frow">
						<label>Import Conc Column name:</label>
						<input type="text" name="options[import_conc]" value="<?php echo $options['import_conc'] ?>" />
					</div>
					<div class="frow">
						<label>Default Value for Blank in file:</label>
						<input type="text" name="options[blank_vl]" value="<?php echo $options['blank_vl'] ?>" />
					</div>
					<div class="frow">
						<label>Value for NaN:</label>
						<input type="text" name="options[nan_value]" value="<?php echo !empty($options['nan_value']) ? $options['nan_value']  : 40 ?>" />
					</div>
					<div class="frow">
						<label>Value for UNDETERMINED:</label>
						<input type="text" name="options[undetecter_value]" value="<?php echo !empty($options['undetecter_value']) ? $options['undetecter_value']  : 45 ?>" />
					</div>
					<div class="frow">
						<label>Default Instrument</label>
						<input type="text" name="options[default_ms]" value="<?php echo !empty($options['default_ms']) ? $options['default_ms']  : '' ?>" />
					</div>
					<div class="frow">
						<label>Not-Detected Range for all Tests</label>
						<input type="text" name="options[notdtecall]" value="<?php echo !empty($options['notdtecall']) ? $options['notdtecall']  : '' ?>" />
					</div>
					<div class="frow">
						<label>Rerun Range for all Tests</label>
						<input type="text" name="options[rerunall]" value="<?php echo !empty($options['rerunall']) ? $options['rerunall']  : '' ?>" />
					</div>




				<?php
				} else if ($tes_type == 'tox') {
				?>
					<div class="frow">
						<input type="hidden" name="options[pdf_hide_comments]" value="0" />
						<label><input type="checkbox" name="options[pdf_hide_comments]" value="1" <?php checked(isset($options['pdf_hide_comments']) ? $options['pdf_hide_comments'] : '') ?> />
							Hide Comments Column</label>
					</div>
					<div class="frow">
						<input type="hidden" name="options[pdf_hide_detectionwindow]" value="0" />
						<label><input type="checkbox" name="options[pdf_hide_detectionwindow]" value="1" <?php checked(isset($options['pdf_hide_detectionwindow']) ? $options['pdf_hide_detectionwindow'] : '') ?> />
							Hide Detection Window</label>
					</div>
					<div class="frow">
						<input type="hidden" name="options[enable_tox_mqc]" value="0" />
						<label><input type="checkbox" name="options[enable_tox_mqc]" value="1" <?php checked(isset($options['enable_tox_mqc']) ? $options['enable_tox_mqc'] : '') ?> />
							Enable Medium QC </label>
					</div>
					<div class="frow">
						<input type="hidden" name="options[leveyj3]" value="0" />
						<label><input type="checkbox" name="options[leveyj3]" value="1" <?php checked(isset($options['leveyj3']) ? $options['leveyj3'] : '') ?> />
							Show 3 lines in one graphic </label>
					</div>
					<div class="frow">
						<input type="hidden" name="options[hide_approve]" value="0" />
						<label><input type="checkbox" name="options[hide_approve]" value="1" <?php checked($options['hide_approve']) ?> />
							Hide approve in list table </label>
					</div>
					<div style="background:#fff;padding:20px">
						<div class="frow">
							<input type="hidden" name="options[lcms_final]" value="0" />
							<label><input type="checkbox" name="options[lcms_final]" value="1" <?php checked($options['lcms_final']) ?> />
								Only show LCMS when Final & Amended </label>
						</div>
						<div class="frow">
							<label>Start Date</label>
							<input type="text" class="hasdate" name="options[lcms_final_date]" value="<?php echo $options['lcms_final_date'] ?>" style="width:120px" />
						</div>
					</div>
					<div class="frow">
						<input type="hidden" name="options[pdf_show_temp_after_panel]" value="0" />
						<label><input type="checkbox" name="options[pdf_show_temp_after_panel]" value="1" <?php checked(isset($options['pdf_show_temp_after_panel']) ? $options['pdf_show_temp_after_panel'] : '') ?> />
							Show Temperature after Panel</label>
					</div>
				<?php
				} else if ($tes_type == 'req') {
				?>
					<div class="frow">
						<label>PDF Title</label>
						<input type="text" name="options[pdf_title]" value="<?php echo $options['pdf_title'] ?>" />
					</div>
					<div class="frow">
						<label>PDF Site</label>
						<input type="text" name="options[pdf_site]" value="<?php echo $options['pdf_site'] ?>" />
					</div>

					<div class="frow">
						<label>PDF Contact Us Text</label>
						<input type="text" name="options[pdf_contactus]" value="<?php echo $options['pdf_contactus'] ?>" />
					</div>

					<div class="frow">
						<label>PDF Logo #1</label>
						<input type="hidden" name="options[pdf_logo]" value="<?php echo $options['pdf_logo'] ?>" />
						<?php if (!empty($options['pdf_logo'])) {
							echo '<div class="viewpdfdiv"><a target=_blank href="' . $result_dir['url'] . '/' . $options['pdf_logo'] . '">View</a> <a class="removepdf button">Remove</a></div>';
						}
						?>
						<input type="file" name="pdf_logo" accept="image/*" />
					</div>
					<br><br>
					<div class="frow">
						<label>PDF Logo #2</label>
						<input type="hidden" name="options[pdf_logo2]" value="<?php echo $options['pdf_logo2'] ?>" />
						<?php if (!empty($options['pdf_logo2'])) {
							echo '<div class="viewpdfdiv"><a target=_blank href="' . $result_dir['url'] . '/' . $options['pdf_logo2'] . '">View</a> <a class="removepdf button">Remove</a></div>';
						}
						?>
						<input type="file" name="pdf_logo2" accept="image/*" />
					</div>
					<br><br>
					<div class="frow">
						<label>PDF Logo copyright</label>
						<input type="hidden" name="options[pdf_logocopy]" value="<?php echo $options['pdf_logocopy'] ?>" />
						<?php if (!empty($options['pdf_logocopy'])) {
							echo '<div class="viewpdfdiv"><a target=_blank href="' . $result_dir['url'] . '/' . $options['pdf_logocopy'] . '">View</a> <a class="removepdf button">Remove</a></div>';
						}
						?>
						<input type="file" name="pdf_logocopy" accept="image/*" />
					</div>
				<?php
				}
				?>

			</div>
			<div class="col2">
				<div class="frow">
					<input type="hidden" name="options[result_e_summary]" value="0" />
					<label><input type="checkbox" name="options[result_e_summary]" value="1" <?php checked($options['result_e_summary']) ?> />
						Enable summary in pdf </label>
				</div>
				<?php

				if ($tes_type != 'req') {
				?>

					<div class="frow">
						<input type="hidden" name="options[disable_test_comments]" value="0" />
						<label><input type="checkbox" name="options[disable_test_comments]" value="1" <?php checked($options['disable_test_comments']) ?> />
							Disable Test Comments in pdf </label>
					</div>
					<div class="frow">
						<input type="hidden" name="options[final_bold]" value="0" />
						<label><input type="checkbox" name="options[final_bold]" value="1" <?php checked($options['final_bold']) ?> />
							Make Final/Prelim/Amended large font in pdf </label>
					</div>

					<div class="frow">
						<input type="hidden" name="options[result_hide_doctor]" value="0" />
						<label><input type="checkbox" name="options[result_hide_doctor]" value="1" <?php checked($options['result_hide_doctor']) ?> />
							Hide Doctor </label>
					</div>

					<div class="frow">
						<input type="hidden" name="options[sort_df_assen]" value="0" />
						<label><input type="checkbox" name="options[sort_df_assen]" value="1" <?php checked($options['sort_df_assen']) ?> />
							Default Sort by Accession </label>
					</div>

					<div class="frow">
						<input type="hidden" name="options[instrument_required]" value="0" />
						<label><input type="checkbox" name="options[instrument_required]" value="1" <?php checked($options['instrument_required']) ?> />
							Instrument Required in filename</label>
					</div>

					<div class="frow">
						<input type="hidden" name="options[reflab_status]" value="0" />
						<label><input type="checkbox" name="options[reflab_status]" value="1" <?php checked($options['reflab_status']) ?> />
							Show Ref lab in status (when received result)</label>
					</div>

					<div class="frow">
						<input type="hidden" name="options[list_show_rlab]" value="0" />
						<label><input type="checkbox" name="options[list_show_rlab]" value="1" <?php checked(isset($options['list_show_rlab']) ? $options['list_show_rlab'] : '') ?> />
							Show Ref lab(sent) in result list</label>
					</div>

					<div class="frow">
						<input type="hidden" name="options[notfify_approve]" value="0" />
						<label><input type="checkbox" name="options[notfify_approve]" value="1" <?php checked($options['notfify_approve']) ?> />
							Notify No Data file when approve</label>
					</div>

					<div class="frow">
						<input type="hidden" name="options[notfify_reflab_esam]" value="0" />
						<label><input type="checkbox" name="options[notfify_reflab_esam]" value="1" <?php checked($options['notfify_reflab_esam']) ?> />
							Notify Ref Lab sample empty when upload result(The following samples were uploaded but they have not been sent by the referring lab)</label>
					</div>

					<div class="frow">
						<input type="hidden" name="options[pos_at_top]" value="0" />
						<label><input type="checkbox" name="options[pos_at_top]" value="1" <?php checked($options['pos_at_top']) ?> />
							Show Positive at Top(PDF) </label>
					</div>


					<br><br>
					<?php
					if ($tes_type == 'gene') {
					?>
						<div class="frow onlyadmin">
							<input type="hidden" name="options[gene_result_off]" value="0" />
							<label><input type="checkbox" name="options[gene_result_off]" value="1" <?php checked($options['gene_result_off']) ?> />
								Turn off Result PDF </label>
						</div>
						<div class="frow">
							<input type="hidden" name="options[gene_genovation_pdf]" value="0" />
							<label><input type="checkbox" name="options[gene_genovation_pdf]" value="1" <?php checked($options['gene_genovation_pdf']) ?> />
								Show Received Genovation PDF </label>
						</div>
						<div class="frow">
							<input type="hidden" name="options[gene_import_genotyping]" value="0" />
							<label><input type="checkbox" name="options[gene_import_genotyping]" value="1" <?php checked($options['gene_import_genotyping']) ?> />
								Import Genotyping/CNV to Results </label>
						</div>
						<div class="frow">

							<label>Upload zip folder:</label>
							<input type="text" name="options[gene_rs_folder]" value="<?php echo $options['gene_rs_folder'] ?>" />

						</div>
						<div class="frow">
							<label for="gene_extend_file">XY extension file:</label>
							<input type="text"
								name="options[gene_extend_file]"
								value="<?php echo esc_attr($options['gene_extend_file'] ?? ''); ?>"
								placeholder=".csv, .oa, .cnv, .vcf, .rpt"
								style="width: 300px;" />
						</div>
				<?php
					}
				}
				?>

				<?php if ($tes_type == 'pcr') { ?>
					<div class="frow">
						<label>Patient guide for ABX(PDF)</label>
						<div class="card_file">
							<a class="button selectfile" href="#">Upload PDF</a>
							<input type="file" name="abx_patient_guide_pdf" accept="application/pdf" />
							<input type="hidden" name="options[abx_patient_guide_pdf]" value="<?php echo isset($options['abx_patient_guide_pdf']) ? $options['abx_patient_guide_pdf'] : '' ?>" />
							<?php
							if (!empty($options['abx_patient_guide_pdf'])) {
								$patient_guide_url = $result_dir['url'] . '/' . $options['abx_patient_guide_pdf'];
							?>
								<span><a href="<?php echo $patient_guide_url ?>" class="button view_card_file" target="_blank">View Patient Guide</a></span>
								<a class="button remove">Remove</a>
							<?php
							} else {
								echo '<span></span>';
							}
							?>
						</div>
					</div>
				<?php } ?>
			</div>
		</div>

		<hr>
		<div class="frow">
			<input type="submit" class="button-primary" value="Save" />
		</div>
	</form>
</div>
<script>
	jQuery(function($) {
		var trpddSettingsExport = <?php echo wp_json_encode(array(
										'option_key' => $key,
										'type' => $tes_type,
										'tabtype' => 'result_settings',
										'exported_at' => current_time('mysql'),
										'options' => is_array($options) ? $options : array(),
									), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
		var trpddSettingsExportFilename = <?php echo wp_json_encode($tes_type . '-result-settings-' . date('Ymd-His') . '.json') ?>;

		$('body').on('click', '.trpdd-settings-import-export', function(e) {
			e.preventDefault();
			$('#trpdd_settings_import_export_modal').show();
		}).on('click', '.trpdd-close-settings-import-export', function(e) {
			e.preventDefault();
			$('#trpdd_settings_import_export_modal').hide();
		}).on('click', '#trpdd_settings_import_export_modal', function(e) {
			if (e.target === this) {
				$(this).hide();
			}
		}).on('click', '.trpdd-export-settings', function(e) {
			e.preventDefault();
			var blob = new Blob([JSON.stringify(trpddSettingsExport, null, 2)], {
				type: 'application/json'
			});
			var url = URL.createObjectURL(blob);
			var link = document.createElement('a');
			link.href = url;
			link.download = trpddSettingsExportFilename;
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
			URL.revokeObjectURL(url);
		});
	})
</script>