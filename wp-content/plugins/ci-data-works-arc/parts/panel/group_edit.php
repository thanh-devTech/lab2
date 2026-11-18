<?php
global $is_ci_admin;
global $current_role;
global $is_ci_current_user;


$id = isset($_GET['id']) ? $_GET['id'] : 0;
$normalize_multi_ids = function ($value) {
	if (is_array($value)) {
		$ids = $value;
	} else if ($value === null || $value === '' || $value === 0 || $value === '0') {
		return array();
	} else {
		$ids = explode(',', (string) $value);
	}

	$return = array();
	foreach ($ids as $id) {
		$id = trim((string) $id);
		if ($id !== '' && $id !== '0') {
			$return[] = $id;
		}
	}

	return array_values(array_unique($return));
};

if ($act == 'edit') {
	$group = $wpdb->get_row("select * from ci_test_groups where tg_ID = {$id} ", ARRAY_A);
	$group['tg_other_options'] = json_decode($group['tg_other_options'], true);



	if (!isset($group['tg_other_options']['rnasep_notrerun_urine'])) {
		$group['tg_other_options']['rnasep_notrerun_urine'] = 0;
		if (isset($group['tg_other_options']['rerun_test_id']) && !empty($group['tg_other_options']['rerun_test_id'])) {
			$group['tg_other_options']['rnasep_notrerun_urine'] = 1;
		} else if (isset($group['tg_other_options']['rerun_test_id_or']) && !empty($group['tg_other_options']['rerun_test_id_or'])) {
			$group['tg_other_options']['rnasep_notrerun_urine'] = 1;
		}
	}

	if (isset($group['tg_cli' . '_ID'])) {

		$wpdb->query('update ci_test_groups set tg_show_cli_IDs = tg_cli_ID');
		$wpdb->query('alter table ci_test_groups drop column tg_cli_ID');
		if ($group['tg_cli' . '_ID'] > 0) {
			$group['tg_show_cli_IDs'] = $group['tg_cli' . '_ID'];
		}
	}
	$group['tg_show_cli_IDs'] = !empty($group['tg_show_cli_IDs']) ? explode(',', $group['tg_show_cli_IDs']) : array();
	$group['tg_notshow_cli_IDs'] = !empty($group['tg_notshow_cli_IDs']) ? explode(',', $group['tg_notshow_cli_IDs']) : array();
	$group['tg_lab_ID'] = $normalize_multi_ids(isset($group['tg_lab_ID']) ? $group['tg_lab_ID'] : '');
	$group['tg_hide_lab_ID'] = $normalize_multi_ids(isset($group['tg_hide_lab_ID']) ? $group['tg_hide_lab_ID'] : '');
	$group['tg_show_for_ref_lab_ID'] = $normalize_multi_ids(isset($group['tg_show_for_ref_lab_ID']) ? $group['tg_show_for_ref_lab_ID'] : '');
	$group['tg_notshow_for_ref_lab_ID'] = $normalize_multi_ids(isset($group['tg_notshow_for_ref_lab_ID']) ? $group['tg_notshow_for_ref_lab_ID'] : '');
} else {
	$group = array();
	$group['tg_show_req'] = 1;
	$group['tg_other_options'] = array();
	$group['tg_show_cli_IDs'] = array();
	$group['tg_notshow_cli_IDs'] = array();
	$group['tg_lab_ID'] = array();
	$group['tg_hide_lab_ID'] = array();
	$group['tg_show_for_ref_lab_ID'] = array();
	$group['tg_notshow_for_ref_lab_ID'] = array();
	$group['tg_position'] = $wpdb->get_var("select max(tg_position) from ci_test_groups ") + 1;
}
$clinics = $wpdb->get_results("select cID,cli_clinic from ci_clinic where cli_status = 1 order by cli_clinic asc");
$parents = $wpdb->get_results("select tg_ID, tg_name, tg_show_req from ci_test_groups 
	    where tg_parent = 0
	     and tg_type='{$tes_type}'
	    order by tg_show_req desc, tg_position asc, tg_name asc");

$ref_labs 	= CI_Base_Lab::get_ref_labs();
$labs 		= CI_Base_Lab::get_labs();


$adding_options = array(
	'tg_show_req' => array('type' => 'all', 'label' => 'Show'),
	'show_as_pcr_groups' => array('type' => 'gene', 'other' => 1, 'ftype' => 'checkbox', 'label' => 'Show as PCR Groups'),
	'tg_only_group' => array('type' => 'all', 'label' => 'Only show Group'),
	'tg_manual_edit' => array('type' => 'all', 'label' => 'Manual Edit Result'),
	'tg_notestcheckbox' => array('type' => 'all', 'label' => 'Remove Test Checkbox'),
	'tg_auto_batchid' => array('type' => 'all', 'label' => 'Auto Current Batch when resulted'),
	'tg_auto_approve_neg' => array('type' => 'all', 'label' => 'Auto approve when all Neg'),
	'tg_auto_approve_pos_neg' => array('type' => 'all', 'label' => 'Auto approve when all Pos or Neg'),
	'groupnegs' => array('type' => 'pcr', 'other' => 1, 'label' => 'Group Test name when all Neg'),
	'tg_manual_antibiotic' => array('type' => 'all', 'label' => 'Manual Antibiotics Result (MIC only)'),
	'uav2' => array('type' => 'all', 'other' => 1, 'label' => 'UA v2'),
	'mantibiotic' => array('type' => 'pcr', 'other' => 1, 'label' => 'Manual Antibiotics Result(NO MIC data)'),
	'tg_auto_position' => array('type' => 'all', 'label' => 'Auto Position when put result'),
	'tg_aw_results' => array('type' => 'all', 'label' => 'Always Show Result If Imported'),
	'show_qc' => array('type' => 'pcr',  'other' => 1, 'label' => 'Show in Data QC'),
	'amp_cq_array' => array('type' => 'pcr',  'other' => 1, 'label' => 'Enable Amp/CQ Array'),

	'tg_hide_pdf_ct' => array('type' => 'all', 'label' => 'Hide ' . (!in_array($tes_type, array('blood', 'tox')) ? 'CT column(Cycle Threshold*)' : 'Value Column') . ' (PDF)', 'default' => 1),
	'tg_hide_pending' => array('type' => 'all', 'label' => 'Hide in Pending (PDF)'),
	//'only_show_pos' => array('type' => 'blood', 'other' => 1, 'label' => 'Only Show Positive flag(PDF)'),
	'tg_pos_2_top' => array('type' => 'all', 'label' => 'Move Positive to top (PDF)'),
	'tg_hide_phone_address' => array('type' => 'all', 'label' => 'Hide Patient Phone & Address (PDF)'),
	'tg_comment_next_row' => array('type' => 'all', 'label' => 'Move comments to next row (PDF)'),
	'hide_comments' => array('type' => 'all', 'other' => 1, 'label' => 'Hide comments (PDF)', 'default' => 1),
	'comment_below' => array('type' => 'pcr', 'other' => 1, 'label' => 'Move comments below(PDF)'),
	'tg_remove_controls' => array('type' => 'pcr', 'label' => 'Hide Controls box(PDF)'),
	'tg_always_show_controls' => array('type' => 'pcr', 'other' => 1, 'label' => 'Always Show Controls box(PDF)'),
	'tg_show_controls_pe' => array('type' => 'pcr', 'label' => 'Show Patient Extraction (in controls box)(PDF)'),
	'show_controls_neg' => array('type' => 'pcr', 'other' => 1, 'label' => 'Show Reference Range is negative (in controls box)(PDF)'),
	'controls_rmpa' => array('type' => 'pcr', 'other' => 1, 'label' => 'Remove Pathogen word (in controls box)(PDF)'),

	'normal_pdf' => array('type' => 'req', 'other' => 1, 'label' => 'Normal PDF'),
	'show_range' => array('type' => 'all', 'other' => 1, 'label' => 'Show Range Column(PDF)'),
	'hide_units_col' => array('type' => 'gene', 'other' => 1, 'label' => 'Hide Units column(PDF)'),
	'show_value_in_result' => array('type' => 'all', 'other' => 1, 'label' => 'Show Value in Result Column(PDF)'),
	'red_sample_type' => array('type' => 'pcr', 'other' => 1, 'label' => 'Show Red Sample Type(PDF)'),
	'e_specimen_source' => array('type' => 'pcr', 'other' => 1, 'label' => 'Enable Specimen Source in Req'),
	'show_units_col' => array('type' => 'pcr', 'other' => 1, 'label' => 'Show Units Column(PDF)'),
	'pdf_show_summary' => array('type' => 'all', 'other' => 1, 'label' => 'Show Summary(PDF)'),
	'breakpage' => array('type' => 'pcr', 'other' => 1, 'label' => 'Go to new page for next group(PDF)'),
	'percent_positive' => array('type' => 'pcr', 'other' => 1, 'label' => 'Enable % Positive column'),
	'split_table' => array('type' => 'all', 'other' => 1, 'label' => 'Split Table (one group in 2 tables)(PDF)'),
	'pdf_small_tbl' => array('type' => 'pcr', 'ftype' => 'select', 'options' => array('' => 'table auto width', 1 => 'small table in Column 1', 2 => 'small table in Column 2', 3 => 'table full width '), 'other' => 1, 'label' => 'Small Table(PDF)'),

	'tg_hide_pdf_units' => array('type' => 'blood', 'label' => 'Hide Units column(PDF)'),
	'auto_checked' => array('type' => 'blood', 'other' => 1, 'label' => ' Panel Checked in req form'),
	'default_neg' => array('type' => 'all', 'other' => 1, 'ftype' => 'select', 'options' => array('' => 'Default Result Flag', 1 => 'Default: Neg', '2' => 'Default:N/A'), 'label' => 'Default Result Flag'),

	'tg_antibiotic' => array('type' => 'all', 'label' => 'Antibiotic Resistance'),
	'pa_description' => array('type' => 'all', 'other' => 1, 'ftype' => 'textarea', 'label' => 'Description(in Patient Site)'),
	'panel_column' => array('type' => 'blood', 'other' => 1, 'ftype' => 'text', 'label' => 'Panel column'),
	'show_loinc' => array('type' => 'blood', 'other' => 1, 'ftype' => '', 'label' => 'show LOINC'),
	'guide_pdf' => array('type' => 'all', 'other' => 1, 'ftype' => 'file', 'label' => 'Guide PDF'),
	// 'purpose' => array('type' => 'all', 'other' => 1, 'ftype' => 'textarea', 'label' => 'Purpose'),
	// 'preparation' => array('type' => 'all', 'other' => 1, 'ftype' => 'textarea', 'label' => 'Preparation'),
	'new_abx' => array('type' => 'pcr', 'subtype' => 'abx', 'other' => 1, 'label' => '<b>Enable New ABX</b>'),
	'new_abx_title' 	=> array('type' => 'pcr', 'subtype' => 'abx', 'ftype' => 'text', 'other' => 1, 'label' => 'New Title'),
	'new_abx_sub_title' 	=> array('type' => 'pcr', 'subtype' => 'abx', 'ftype' => 'text', 'other' => 1, 'label' => 'Sub Title'),
	'new_abx_date' 	=> array('type' => 'pcr', 'subtype' => 'abx', 'ftype' => 'date', 'other' => 1, 'label' => 'Start Date (by approve date)'),
	'abx_type' => array(
		'type' => 'pcr',
		'subtype' => 'abx',
		'ftype' => 'select',
		'options' => array(
			'' => 'Select Type',
			'uti' => 'UTI',
			'wound' => 'Wound',
			'gi' => 'GI',
			'ei' => 'ENT',
			'nail' => 'Nail',
			'r' => 'Respiratory',
			'v' => 'Vaginitis',
			'sm' => 'V-Male STI',
			'sr' => 'V-Rectal STI',
			'sp' => 'V-Pharyngeal',
			'sl' => 'V-Skin Lesions',
			'es' => 'V-Eye STI',
		),
		'other' => 1,
		'label' => 'ABX Type'
	),
	'abx_pregnancy_by' => array(
		'type' => 'pcr',
		'subtype' => 'abx',
		'ftype' => 'select',
		'options' => array(
			'' => 'Pregnancy : None',
			'age' => 'By : Age + Female',
			'aoe' => 'By : AoE',
		),
		'other' => 1,
		'label' => 'Pregnancy By',
		'class' => 'onlyfor_ciadmin'
	),
	'quantitative' 	=> array('type' => 'pcr', 'subtype' => 'abx', 'ftype' => 'select', 'options' => array('' => 'Semiquantitative', '1' => 'Quantitative'), 'other' => 1, 'label' => 'Quantitative'),
	'dismkquantitative' => array('type' => 'pcr', 'subtype' => 'abx', 'ftype' => 'checkbox', 'other' => 1, 'label' => 'Disable Quantitative for Maker'),
	'abx_renal_aoe' => array('type' => 'pcr', 'subtype' => 'abx', 'ftype' => 'checkbox', 'other' => 1, 'label' => 'Enable Renal Dose by AoE'),
	'abx_pie_chart' => array('type' => 'pcr', 'subtype' => 'abx', 'ftype' => 'checkbox', 'other' => 1, 'label' => 'Enable PIE Chart'),
	'perquantitative' => array('type' => 'pcr', 'subtype' => 'abx', 'ftype' => 'checkbox', 'other' => 1, 'label' => '%Quantitative'),
	'abx_formula' 	=> array('type' => 'pcr', 'subtype' => 'abx', 'ftype' => 'select', 'options' => array('' => 'Select Formula', 'GL' => 'GL', 'NQC' => 'NQC', 'NQY' => 'NQY', 'Q10' => 'Q10', 'Q100' => 'Q100', 'SY' => 'SY', 'QG' => 'QG', 'QG1' => 'QG1', 'QG_HL' => 'QG_HL', 'QG_HM' => 'QG_HM'), 'other' => 1, 'label' => 'Formula'),
	'abx_ct1' 		=> array('type' => 'pcr', 'subtype' => 'abx', 'ftype' => 'text', 'other' => 1, 'label' => 'CT1', 'class' => 'fieldabxvalue formu_Q10 formu_Q100 formu_QG formu_QG1 formu_QG_HL formu_QG_HM', 'desc' => ' Leave empty to use in Test range'),
	'abx_ct2' 		=> array('type' => 'pcr', 'subtype' => 'abx', 'ftype' => 'text', 'other' => 1, 'label' => 'CT2', 'class' => 'fieldabxvalue formu_Q10 formu_Q100 formu_QG formu_QG1 formu_QG_HL formu_QG_HM', 'desc' => ' Leave empty to use in Test range'),
	'abx_load1' 	=> array('type' => 'pcr', 'subtype' => 'abx', 'ftype' => 'text', 'other' => 1, 'label' => 'Load1', 'class' => 'fieldabxvalue formu_QG formu_QG1 formu_QG_HL formu_QG_HM'),
	'abx_load2' 	=> array('type' => 'pcr', 'subtype' => 'abx', 'ftype' => 'text', 'other' => 1, 'label' => 'Load2', 'class' => 'fieldabxvalue formu_QG formu_QG1 formu_QG_HL formu_QG_HM'),
	'abx_delta' 	=> array('type' => 'pcr', 'subtype' => 'abx', 'ftype' => 'text', 'other' => 1, 'label' => 'Delta', 'class' => 'fieldabxvalue formu_NQY'),
	'abx_y_intercept' => array('type' => 'pcr', 'subtype' => 'abx', 'ftype' => 'text', 'other' => 1, 'label' => 'Y_intercept', 'class' => 'fieldabxvalue formu_GL formu_SY'),
	'abx_slope' 	=> array('type' => 'pcr', 'subtype' => 'abx', 'ftype' => 'text', 'other' => 1, 'label' => 'Slope', 'class' => 'fieldabxvalue formu_GL formu_NQC formu_NQY formu_SY'),
	'abx_load_cutoff' 	=> array('type' => 'pcr', 'subtype' => 'abx', 'ftype' => 'text', 'other' => 1, 'label' => 'Load_cutoff', 'class' => 'fieldabxvalue formu_NQC'),
	'abx_load_high' 	=> array('type' => 'pcr', 'subtype' => 'abx', 'ftype' => 'text', 'other' => 1, 'label' => 'Load_High', 'class' => 'fieldabxvalue2'),
);

if (!defined('TRPDD_ARK')) {
	unset($adding_options['uav2']);
}

$updatefix = array();
if (tr_get_option('ci_result_settings_' . $tes_type, 'e_mic2')) {

	unset($adding_options['tg_manual_antibiotic']);

	if ($id > 0 && isset($group['tg_manual_antibiotic']) && $group['tg_manual_antibiotic']) {
		unset($group['tg_manual_antibiotic']);
		$group['tg_other_options']['mantibiotic'] = 1;
		$updatefix['tg_other_options'] = json_encode($group['tg_other_options']);
	}
} else {

	unset($adding_options['mantibiotic']);
	if ($id > 0 && isset($group['tg_other_options']['mantibiotic']) && $group['tg_other_options']['mantibiotic']) {
		unset($group['tg_other_options']['mantibiotic']);
		if (isset($group['tg_manual_antibiotic'])) {
			$updatefix['tg_manual_antibiotic'] = 1;
		}

		$updatefix['tg_other_options'] = json_encode($group['tg_other_options']);
	}
}

if (!tr_get_option('ci_req_settings_' . $tes_type, 'enable_panel_specimen_source')) {
	unset($adding_options['e_specimen_source']);
}

if (isset($group['tg_parent']) && $group['tg_parent'] > 0) {
	$adding_options['tg_show_req']['label'] = 'Show In self box';
}

if (!$is_ci_admin || !tr_get_option('ci_pdf_data', 'new_abx')) {
	$adding_options['new_abx']['hide'] = true;
	$adding_options['abx_type']['hide'] = true;
	$adding_options['quantitative']['hide'] = true;
	$adding_options['new_abx_date']['hide'] = true;
}

if ($group && $group['tg_parent'] > 0) {
	$adding_options['abx_type']['options'] = array(
		'' => 'Select Subtype (ABX)',
		'yuti' => 'Yeast-UTI',
		'ywound' => 'Yeast-Wound',
		'vwound' => 'Virus-Wound',
		'yent' => 'Yeast-ENT',
		'ynail' => 'Yeast-Nail',
		'sti' => 'STI',
	);

	$adding_options['new_abx_date']['label'] = 'Start date Subtype (ABX)';
}

if (!$is_ci_admin) {
	$adding_options['pdf_small_tbl']['hide'] = true;
	$adding_options['controls_rmpa']['hide'] = true;
	$adding_options['tg_hide_phone_address']['hide'] = true;
}

if (stripos($group['tg_name'], 'abr') !== false || stripos($group['tg_name'], 'Antibiotic') !== false) {
	$adding_options['tg_antibiotic']['hide'] = false;
} else if (!$is_ci_admin && !empty($group['tg_ID'])) {
	$adding_options['tg_antibiotic']['hide'] = true;
}

if (isset($group['tg_name']) && !empty($group['tg_name']) && $group['tg_parent'] == 0) {
	if (stripos($group['tg_name'], 'uti') !== false || stripos($group['tg_name'], 'wound') !== false || stripos($group['tg_name'], 'gi') !== false) {
		$adding_options['tg_antibiotic']['hide'] = true;
		if ($group['tg_antibiotic']) {
			$updatefix['tg_antibiotic'] = 0;
		}
	}
}

if (!empty($updatefix) && $id > 0) {
	$result = $wpdb->update('ci_test_groups', $updatefix, array('tg_ID' => $id));
	if ($result === false) {
		Tr_Session_Class::add_error('Database update failed: ' . $wpdb->last_error);
	} else {
		Tr_Session_Class::add('Group updated successfully.');
	}
}
?>

<div class="wrap">
	<h2><?php echo ($act == 'add') ? 'Add New' : 'Edit' ?> Group <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '', 'gr' => '')) ?>" class="add-new-h2">All Groups</a>
		<?php if ($act == 'edit') { ?>
			<a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2">Add New Group</a>
			<a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '', 'tabtype' => '', 'gr' => $id)) ?>" class="add-new-h2">List Tests</a>
			<?php if (!in_array($tes_type, array('req'))) { ?>
				<a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '', 'tabtype' => '', 'gr' => $id)) ?>" class="add-new-h2">Add New Test</a>
			<?php } ?>
		<?php } ?>
	</h2>

	<?php Tr_Session_Class::show() ?>
	<br>


	<form method="post" enctype="multipart/form-data">
		<div class="frow">
			<div class="col3">

				<div class="frow">
					<label>Name</label>
					<input type="text" name="group[tg_name]" value="<?php echo isset($group['tg_name']) ? $group['tg_name'] : '' ?>" required />
				</div>
				<div class="frow">
					<label>Group Description (req only)</label>
					<input type="text" name="group[tg_other_options][desc]" value="<?php echo isset($group['tg_other_options']['desc']) ? $group['tg_other_options']['desc'] : '' ?>" />
				</div>
				<div class="frow">
					<label>Short Name</label>
					<input type="text" name="group[tg_short_name]" value="<?php echo isset($group['tg_short_name']) ? $group['tg_short_name'] : '' ?>" />
				</div>
				<div class="frow">
					<label>Print Label</label>
					<input type="text" name="group[tg_label_name]" value="<?php echo isset($group['tg_label_name']) ? $group['tg_label_name'] : '' ?>" maxlength="5" />
				</div>
				<div class="frow">
					<label>Order code</label>
					<input type="text" name="group[tg_cpt_code]" value="<?php echo isset($group['tg_cpt_code']) ? $group['tg_cpt_code'] : '' ?>" />
				</div>
				<div class="frow">
					<label>Position</label>
					<input type="text" name="group[tg_position]" value="<?php echo isset($group['tg_position']) ? $group['tg_position'] : '' ?>" required />
				</div>
				<div class="frow">
					<label>Popup Approve</label>
					<input type="text" name="group[tg_popup_approve]" value="<?php echo isset($group['tg_popup_approve']) ? $group['tg_popup_approve'] : '' ?>" />

				</div>
				<div class="frow">
					<label>Popup Approve when verified</label>
					<input type="text" name="group[tg_popup_verified]" value="<?php echo isset($group['tg_popup_verified']) ? $group['tg_popup_verified'] : '' ?>" />

				</div>

				<div class="frow">
					<label>Parent</label>
					<select name="group[tg_parent]" style="max-width:100%">
						<option value="">Select</option>
						<?php
						foreach ($parents as $parent) {
							if ($parent->tg_ID == $group['tg_ID']) continue;
							$selected = isset($group['tg_parent']) && $parent->tg_ID == $group['tg_parent'] ? 'selected' : '';
							if ($parent->tg_show_req == 0) {
								$parent->tg_name = $parent->tg_name . ' (Hidden)';
							}
							echo '<option value="' . $parent->tg_ID . '" ' . $selected . '>' . $parent->tg_name . '</option>';
						}
						?>
					</select>

				</div>
				<div class="frow">
					<label>Relate To</label>
					<select name="group[tg_relate_to][]" multiple class="js_select2" style="max-width:100%">
						<?php
						$group['tg_relate_to'] = !empty($group['tg_relate_to']) ? explode(',', $group['tg_relate_to']) : array();
						foreach ($parents as $parent) {
							if ($parent->tg_ID == $group['tg_ID']) continue;
							$selected =  in_array($parent->tg_ID, $group['tg_relate_to']) ? 'selected' : '';
							echo '<option value="' . $parent->tg_ID . '" ' . $selected . '>' . $parent->tg_name . '</option>';
						}
						?>
					</select>
				</div>

				<?php if (in_array($tes_type, array('pcr', 'blood'))) { ?>
					<div class="frow">
						<label>Reflex</label>
						<select name="group[tg_reflex_group_ID][]" class="js_select2" multiple="true" style="max-width:100%">
							<?php
							$has_refelex_id = false;
							$reflexids = explode(',', $group['tg_reflex_group_ID']);
							foreach ($parents as $gr) {
								if ($gr->tg_ID == $group['tg_ID']) continue;
								if ($group['tg_parent'] > 0 && $gr->tg_ID != $group['tg_parent']) continue;

								$selected = in_array($gr->tg_ID, $reflexids) ? 'selected' : '';
								if ($selected) {
									$has_refelex_id = true;
								}
								echo '<option value="' . $gr->tg_ID . '" ' . $selected . '>' . $gr->tg_name . '</option>';
								$childs = $wpdb->get_results("select tg_ID, tg_name from ci_test_groups where tg_parent = {$gr->tg_ID} ");
								foreach ($childs as $ch) {
									if ($ch->tg_ID == $group['tg_ID']) continue;
									$selected = in_array($ch->tg_ID, $reflexids) ? 'selected' : '';
									if ($selected) {
										$has_refelex_id = true;
									}
									echo '<option value="' . $ch->tg_ID . '" ' . $selected . '>  -- ' . $ch->tg_name . '</option>';
								}
							}
							if (!$has_refelex_id && !empty($group['tg_reflex_group_ID'])) {
								$wpdb->update('ci_test_groups', array('tg_reflex_group_ID' => ''), array('tg_ID' => $group['tg_ID']));
							}
							?>
						</select>
					</div>
					<?php


					/*
						<div class="frow">
							<label>
								<input type="hidden" name="group[tg_reflex_hide4nopos]" value="0" />
								<input type="checkbox" name="group[tg_reflex_hide4nopos]" value="1" <?php checked($group['tg_reflex_hide4nopos']) ?> /> Reflexed
							</label>
						</div>
						<?php */ ?>
				<?php } ?>

				<div class="frow">
					<label>When this panel selected then change Ref Lab to</label>
					<select name="group[tg_ref_lab_ID]" class="js_select2" style="max-width:100%">
						<?php
						echo '<option value="">Select</option>';
						foreach ($ref_labs as $rlab) {
							$selected = $rlab->lab_ID == $group['tg_ref_lab_ID']  ? 'selected' : '';
							echo '<option value="' . $rlab->lab_ID . '" ' . $selected . '>' . $rlab->lab_name . '</option>';
						}
						?>
					</select>
				</div>
				<?php if (count($labs) > 1) { ?>
					<div class="frow">
						<label>Show For Lab</label>
						<select name="group[tg_lab_ID][]" multiple class="js_select2" style="max-width:100%">
							<?php
							foreach ($labs as $lab) {
								$selected = in_array((string) $lab->lab_ID, $group['tg_lab_ID'], true) ? 'selected' : '';
								echo '<option value="' . $lab->lab_ID . '" ' . $selected . '>' . $lab->lab_name . ' - ' . $lab->lab_dba_init . '</option>';
							}
							?>
						</select>
					</div>
					<div class="frow">
						<label>Hide for Lab</label>
						<select name="group[tg_hide_lab_ID][]" multiple class="js_select2" style="max-width:100%">
							<?php
							foreach ($labs as $lab) {
								$selected = in_array((string) $lab->lab_ID, $group['tg_hide_lab_ID'], true) ? 'selected' : '';
								echo '<option value="' . $lab->lab_ID . '" ' . $selected . '>' . $lab->lab_name . ' - ' . $lab->lab_dba_init . '</option>';
							}
							?>
						</select>
					</div>
				<?php } ?>
				<div class="frow">
					<label>Show for Clinics</label>
					<select name="group[tg_show_cli_IDs][]" multiple class="js_select2" style="max-width:100%">
						<?php
						foreach ($clinics as $cli) {
							$selected = in_array($cli->cID, $group['tg_show_cli_IDs'])  ? 'selected' : '';
							echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
						}
						?>
					</select>

				</div>
				<div class="frow">
					<label>Do Not Show for Clinics</label>
					<select name="group[tg_notshow_cli_IDs][]" multiple class="js_select2" style="max-width:100%">
						<?php
						foreach ($clinics as $cli) {
							$selected = in_array($cli->cID, $group['tg_notshow_cli_IDs'])  ? 'selected' : '';
							echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
						}
						?>
					</select>

				</div>

				<div class="frow">
					<label>Only Show for Ref Lab</label>
					<select name="group[tg_show_for_ref_lab_ID][]" multiple class="js_select2" style="max-width:100%">
						<?php
						foreach ($ref_labs as $rlab) {
							$selected = in_array((string) $rlab->lab_ID, $group['tg_show_for_ref_lab_ID'], true) ? 'selected' : '';
							echo '<option value="' . $rlab->lab_ID . '" ' . $selected . '>' . $rlab->lab_name . '</option>';
						}
						?>
					</select>
				</div>
				<div class="frow">
					<label>Do Not Show for Ref Lab</label>
					<select name="group[tg_notshow_for_ref_lab_ID][]" multiple class="js_select2" style="max-width:100%">
						<?php
						foreach ($ref_labs as $rlab) {
							$selected = in_array((string) $rlab->lab_ID, $group['tg_notshow_for_ref_lab_ID'], true) ? 'selected' : '';
							echo '<option value="' . $rlab->lab_ID . '" ' . $selected . '>' . $rlab->lab_name . '</option>';
						}
						?>
					</select>
				</div>
				<div class="frow">
					<label>Limit Days (warning)</label>
					<input type="number" name="group[tg_limit_days]" value="<?php echo isset($group['tg_limit_days']) ? $group['tg_limit_days'] : '' ?>" />
				</div>


				<div class="frow2">
					<label>Background</label>
					<input type="text" name="group[tg_bgcolor]" class="my-color-field" value="<?php echo isset($group['tg_bgcolor']) ? $group['tg_bgcolor'] : '' ?>" />
				</div>

				<?php if ($group['tg_parent'] == 0) { ?>
					<div class="frow">
						<label>Start Date</label>
						<input type="text" name="group[tg_date_start]" value="<?php if (isset($group['tg_date_start']) && $group['tg_date_start'] > '1000-01-01') echo $group['tg_date_start'] ?>" class="hasdate" autocomplete="off" data-write="1">
					</div>
					<div class="frow">
						<label>End Date</label>
						<input type="text" name="group[tg_date_end]" value="<?php if (isset($group['tg_date_end']) && $group['tg_date_end'] > '1000-01-01') echo $group['tg_date_end'] ?>" class="hasdate" autocomplete="off" data-write="1">
					</div>
				<?php } else {

					$wpdb->update('ci_test_groups', array('tg_date_start' => '', 'tg_date_end' => ''), array('tg_ID' => $group['tg_ID']));
				?>
					<input type="hidden" name="group[tg_date_start]" value="">
					<input type="hidden" name="group[tg_date_end]" value="">
				<?php
				}
				?>
			</div>
			<div class="col3">

				<?php
				if ($tes_type == 'pcr' && tr_get_option('ci_pdf_data', 'api_athena')) {
				?>
					<div class="frow">
						<label>Athena Code</label>
						<input type="text" name="group[tg_other_options][athena]" value="<?php echo isset($group['tg_other_options']['athena']) ? $group['tg_other_options']['athena'] : '' ?>" />
					</div>
				<?php
				}
				if ($tes_type == 'pcr' && tr_get_option('ci_pdf_data', 'api_athena_intralab')) {
				?>
					<div class="frow">
						<label>Athena Intralab Code</label>
						<input type="text" name="group[tg_other_options][athena_intralab]" value="<?php echo isset($group['tg_other_options']['athena_intralab']) ? $group['tg_other_options']['athena_intralab'] : '' ?>" />
					</div>
				<?php
				}
				?>
				<?php
				if ($tes_type == 'pcr' && tr_get_option('ci_pdf_data', 'api_greenway')) {
				?>
					<div class="frow">
						<label>Greenway Code</label>
						<input type="text" name="group[tg_greenway_code]" value="<?php echo $group['tg_greenway_code'] ?>" />
					</div>
				<?php
				}
				if (tr_get_option('ci_pdf_data', 'api_hl7_sftp_msu')) {
				?>
					<div class="frow">
						<label>MSU Code</label>
						<input type="text" name="group[tg_msu_code]" value="<?php echo $group['tg_msu_code'] ?>" />
					</div>
				<?php
				}

				if (tr_get_option('ci_pdf_data', 'api_hl7_sftp_glml')) {
				?>
					<div class="frow">
						<label>GLML Code</label>
						<input type="text" name="group[tg_glml_code]" value="<?php echo $group['tg_glml_code'] ?>" />
					</div>
				<?php
				}
				if (tr_get_option('ci_pdf_data', 'api_hl7_sftp_nwlabs')) {
				?>
					<div class="frow">
						<label>NWlabs Code</label>
						<input type="text" name="group[tg_nwlabs_code]" value="<?php echo $group['tg_nwlabs_code'] ?>" />
					</div>
				<?php
				}
				?>

				<div class="frow">
					<label>Result Top Title(PDF)</label>
					<input type="text" name="group[tg_pdf_title]" value="<?php echo isset($group['tg_pdf_title']) ? $group['tg_pdf_title'] : '' ?>" />
				</div>
				<div class="frow">
					<label>Panel Title(PDF)</label>
					<input type="text" name="group[tg_other_options][panel_pdf_title]" value="<?php echo isset($group['tg_other_options']['panel_pdf_title']) ? $group['tg_other_options']['panel_pdf_title'] : '' ?>" />
				</div>
				<?php if (empty($group['tg_parent'])) { ?>
					<div class="frow">
						<label>PDF Style <a class="preview_pdf_style" href="#">preview</a></label>
						<select name="group[tg_blood_style]">
							<option value="">Default (#<?php echo tr_get_option('ci_pdf_data', $tes_type . '_pdf_style', 1) ?>)</option>
							<?php
							foreach (trpdd_list_report_styles($tes_type) as $k => $txt) {
								if ($tes_type == 'pcr' && $k == 3) continue;
								$selected = isset($group['tg_blood_style']) && $k == $group['tg_blood_style'] ? 'selected' : '';
								echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
							}
							?>
						</select>
					</div>
				<?php }
				$results_options = $wpdb->get_results("select tg_other_options from ci_test_groups where tg_show_req=1 and tg_type = 'pcr'");
				$all_filters = ['UTI', 'Respiratory', 'STI', 'Vaginosis', 'Fungal', 'Wound', 'GI'];

				foreach ($results_options as $row) {
					$options = json_decode($row->tg_other_options, true);

					if (!empty($options['filters'])) {
						$filters_array = array_map('trim', explode(',', $options['filters']));
						$all_filters = array_merge($all_filters, $filters_array);
					}
				}
				$all_filters = array_unique($all_filters);
				?>
				<div class="frow">
					<label>Filter</label>
					<input type="hidden" name="group[tg_other_options][filters]" value="" />
					<select class="js_select2 editable" multiple name="group[tg_other_options][filters][]">
						<?php
						foreach ($all_filters as $filter) {
							$current_filters = array_map('trim', explode(',', $group['tg_other_options']['filters'] ?? ''));
							$selected = in_array($filter, $current_filters) ? 'selected' : '';
							echo '<option value="' . $filter . '" ' . $selected . '>' . $filter . '</option>';
						}
						?>
					</select>
				</div>
				<div class="frow">
					<label>PDF Logo</label>
					<input type="file" name="tg_logo" />
					<input type="hidden" name="group[tg_logo]" value="<?php echo isset($group['tg_logo']) ? $group['tg_logo'] : '' ?>" />
					<?php if (!empty($group['tg_logo'])) {
						echo '<span class="ct_logo_image">';
						echo '<img src="' . $logo_url . '/' . $group['tg_logo'] . '?" style="width:50px;height:auto">';
						echo '<a class="button remove_logo_image">Remove</a>';
						echo '</span>';
					}
					?>
				</div>
				<div class="frow">
					<label>if this panel is chosen then show this comment.</label>
					<textarea name="group[tg_comments]"><?php echo isset($group['tg_comments']) ? $group['tg_comments'] : '' ?></textarea>
				</div>
				<?php if ($is_ci_admin && empty($group['tg_parent'])) { ?>
					<div class="frow">
						<label>
							Plate Layout #1
							<?php if ($is_ci_admin) { ?>
								<a class="" target="_plates" href="<?php Tr_Base_Class_V6::link(array('act' => 'plate_maps', 'trajax' => 1, 'width' => '1000')) ?>">maps</a>
							<?php } ?>
						</label>
						<select name="group[tg_plate_style]">
							<option value="">None</option>
							<?php
							foreach (trpdd_list_plate_styles($tes_type) as $k => $txt) {
								$selected = isset($group['tg_plate_style']) && $k == $group['tg_plate_style'] ? 'selected' : '';
								echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
							}
							?>
						</select>
					</div>
					<div class="frow">
						<label>Rack Title #1(in dropdown)</label>
						<input type="text" name="group[tg_other_options][rack_name]" value="<?php echo isset($group['tg_other_options']['rack_name']) ? $group['tg_other_options']['rack_name'] : '' ?>" />
					</div>
					<div class="frow">
						<label>Plate Layout #2</label>
						<select name="group[tg_plate_style2]">
							<option value="">None</option>
							<?php
							foreach (trpdd_list_plate_styles($tes_type) as $k => $txt) {
								$selected = isset($group['tg_plate_style']) && $k == $group['tg_plate_style2'] ? 'selected' : '';
								echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
							}
							?>
						</select>
					</div>

					<div class="frow">
						<label>Rack Title #2(in dropdown)</label>
						<input type="text" name="group[tg_other_options][rack_name2]" value="<?php echo isset($group['tg_other_options']['rack_name2']) ? $group['tg_other_options']['rack_name2'] : '' ?>" />
					</div>
				<?php } ?>

				<?php if (isset($result_settings['e_reruntest']) && $result_settings['e_reruntest'] && $group['tg_ID'] > 0) {
					$tests_in_groups = $wpdb->get_results(
						"select te.te_ID, te.te_name , te.te_abrev
						from ci_test_panels te
						jOIN ci_test_groups g ON te.te_group_id = g.tg_ID
						where (g.tg_ID = {$group['tg_ID']} or g.tg_parent = {$group['tg_ID']} )
						and te_is_control = 1
						order by te.te_position desc"
					);
					$rerunids = isset($group['tg_other_options']['rerun_test_id']) ? explode(',',  (string)$group['tg_other_options']['rerun_test_id']) : array();
					$rerunids_or = isset($group['tg_other_options']['rerun_test_id_or']) ? explode(',', (string)$group['tg_other_options']['rerun_test_id_or']) : array();
				?>
					<div class="frow">
						<label>Grouped Controls (rerun on all fail)</label>
						<input type="hidden" name="group[tg_other_options][rerun_test_id]" value="" />
						<select name="group[tg_other_options][rerun_test_id][]" multiple class="js_select2">
							<?php
							foreach ($tests_in_groups as $test) {
								$selected = in_array($test->te_ID, $rerunids) ? 'selected' : '';
								echo '<option value="' . $test->te_ID . '" ' . $selected . '>' . $test->te_name . '</option>';
							}
							?>
						</select>
					</div>
					<div class="frow">
						<label> Independent Controls (any or all)</label>
						<input type="hidden" name="group[tg_other_options][rerun_test_id_or]" value="" />
						<select name="group[tg_other_options][rerun_test_id_or][]" multiple class="js_select2">
							<?php
							foreach ($tests_in_groups as $test) {
								$selected = in_array($test->te_ID, $rerunids_or) ? 'selected' : '';
								echo '<option value="' . $test->te_ID . '" ' . $selected . '>' . $test->te_name . '</option>';
							}
							?>
						</select>
					</div>
					<div class="frow">
						<label>
							<input type="hidden" name="group[tg_other_options][rnasep_notrerun_urine]" value="0" />
							<input type="checkbox" name="group[tg_other_options][rnasep_notrerun_urine]" value="1" <?php checked($group['tg_other_options']['rnasep_notrerun_urine']) ?> />
							Specimen type RNASep rule
						</label>
					</div>
					<div class="frow">
						<label>
							<input type="hidden" name="group[tg_other_options][not_rerun_or]" value="0" />
							<input type="checkbox" name="group[tg_other_options][not_rerun_or]" value="1" <?php checked($group['tg_other_options']['not_rerun_or']) ?> />
							Dont rerun when any test Detected
						</label>
					</div>

					<div class="frow">
						<label>Rerun Specimen</label>
						<input type="hidden" name="group[tg_other_options][rerun_specimen]" value="" />
						<select name="group[tg_other_options][rerun_specimen][]" multiple class="js_select2">
							<?php
							$specimens = $wpdb->get_results(
								"select sp.ts_name
									from ci_test_specimens sp
									where sp.ts_group_id = {$group['tg_ID']} 
									order by sp.ts_name desc"
							);
							$rerunids = explode(',', $group['tg_other_options']['rerun_specimen']);
							foreach ($specimens as $spec) {
								$selected = in_array($spec->ts_name, $rerunids) ? 'selected' : '';
								echo '<option value="' . $spec->ts_name . '" ' . $selected . '>' . $spec->ts_name . '</option>';
							}
							?>
						</select>
					</div>
				<?php } ?>

				<?php

				if ($tes_type == 'pcr' && tr_get_option('ci_pdf_data', 'new_abx')) {
					echo '<div id="pcr_abx_box" style="border:1px solid #aaa;padding:5px;">';

					foreach ($adding_options as $k => $opts) {
						if (!isset($opts['other'])) {
							$opts['other'] = false;
						}
						if ($opts['type'] !== 'all' && $opts['type'] !== $tes_type) {
							continue;
						}
						if (!isset($opts['subtype']) || $opts['subtype'] != 'abx') {
							continue;
						}
						$opts['ftype'] 	= isset($opts['ftype']) ? $opts['ftype'] : '';
						$opname 		= 'group' . ($opts['other'] ? '[tg_other_options]' : '') . '[' . $k . ']';
						$opvalue 		= isset($opts['other']) && $opts['other'] ? (isset($group['tg_other_options'][$k]) ? $group['tg_other_options'][$k] : '') : $group[$k];
						if (!isset($group['tg_ID']) && isset($opts['default'])) {
							$opvalue = $opts['default'];
						}

						echo '<div class="frow ' . (isset($opts['hide']) && $opts['hide'] ? ' alwayshide' : '') . ' ' . (isset($opts['class']) ? $opts['class'] : '') . '" id="field_' . $k . '" >';
						if ($opts['ftype'] == 'text') {
							echo '<label>' . $opts['label'] . '</label>';
							echo '<input type="text" name="' . $opname . '" value="' . $opvalue . '"/>';
						} else  if ($opts['ftype'] == 'textarea') {
							echo '<label>' . $opts['label'] . '</label>';
							echo '<textarea name="' . $opname . '">' . $opvalue . '</textarea>';
						} else if ($opts['ftype'] == 'date') {
							echo '<label>' . $opts['label'] . '</label>';
							echo '<input type="text" class="hasdate" name="' . $opname . '" value="' . $opvalue . '"/>';
						} else if ($opts['ftype'] == 'select') {
							echo tr_select_option($opname, $opts['options'], $opvalue);
						} else {
							echo '<input type="hidden" name="' . $opname . '" value="0" />';
							echo '<label><input type="checkbox" name="' . $opname . '" value="1" ' . checked($opvalue, true, false) . '/> ';
							echo $opts['label'];
							echo '</label>';
						}
						echo '</div>';
					}
					if ($is_ci_admin) {
						$cids = isset($group['tg_other_options']['not_show_for_cids']) ? explode(',',  (string)$group['tg_other_options']['not_show_for_cids']) : array();
				?>
						<div class="frow">
							<label>Not Show for Clinics</label>
							<select name="group[tg_other_options][not_show_for_cids][]" multiple class="js_select2" style="max-width:100%">
								<?php
								foreach ($clinics as $cli) {
									$selected = in_array($cli->cID, $cids) ? 'selected' : '';
									echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
								}
								?>
							</select>
						</div>
				<?php }
					echo '</div>';
				}
				?>
			</div>
			<div class="col3">
				<?php
				foreach ($adding_options as $k => $opts) {
					if (!isset($opts['other'])) {
						$opts['other'] = false;
					}
					if ($opts['type'] !== 'all' && $opts['type'] !== $tes_type) {
						continue;
					}
					if (isset($opts['subtype']) && $opts['subtype'] == 'abx') {
						continue;
					}
					$opts['ftype'] = isset($opts['ftype']) ? $opts['ftype'] : '';
					$opname = 'group' . ($opts['other'] ? '[tg_other_options]' : '') . '[' . $k . ']';
					$opvalue = isset($opts['other']) && $opts['other'] ? (isset($group['tg_other_options'][$k]) ? $group['tg_other_options'][$k] : '') : $group[$k];
					if (!isset($group['tg_ID']) && isset($opts['default'])) {
						$opvalue = $opts['default'];
					}
					echo '<div class="frow ' . (isset($opts['hide']) && $opts['hide'] ? ' alwayshide' : '') . ' ' . (isset($opts['class']) ? $opts['class'] : '') . '" id="field_' . $k . '" >';
					if ($opts['ftype'] == 'text') {
						echo '<label>' . $opts['label'] . '</label>';
						echo '<input type="text" name="' . $opname . '" value="' . $opvalue . '"/>';
					} else  if ($opts['ftype'] == 'textarea') {
						echo '<label>' . $opts['label'] . '</label>';
						echo '<textarea name="' . $opname . '">' . $opvalue . '</textarea>';
					} else if ($opts['ftype'] == 'file') {
						echo '<label>' . $opts['label'] . '</label>';
						echo '<input type="file" name="' . $opname . '" accept=".pdf"/>';

						if ($k === 'guide_pdf' && !empty($opvalue)) {
							$pdf_filename = basename($opvalue);
							echo '<div class="current-guide-pdf" style="margin-top:5px;padding:5px;background:#f0f0f0;border-radius:3px;">';
							echo '<span style="display:inline-block;margin-right:10px;">Current: <strong>' . esc_html($pdf_filename) . '</strong></span>';
							echo '<a href="' . esc_url($opvalue) . '" target="_blank" class="button button-small">View PDF</a> ';
							echo '<button type="button" class="button button-small remove-guide-pdf">Remove</button>';
							echo '<input type="hidden" name="guide_pdf_remove" value="0" class="guide-pdf-remove-flag"/>';
							echo '<input type="hidden" name="' . $opname . '" value="' . $opvalue . '"/>';
							echo '</div>';
						}
					} else if ($opts['ftype'] == 'date') {
						echo '<label>' . $opts['label'] . '</label>';
						echo '<input type="text" class="hasdate" name="' . $opname . '" value="' . $opvalue . '"/>';
					} else if ($opts['ftype'] == 'select') {
						tr_select_option($opname, $opts['options'], $opvalue);
					} else {
						echo '<input type="hidden" name="' . $opname . '" value="0" />';
						echo '<label><input type="checkbox" name="' . $opname . '" value="1" ' . checked($opvalue, true, false) . '/> ';
						echo $opts['label'];
						echo '</label>';
					}
					echo '</div>';
				}


				if ($is_ci_admin && $tes_type == 'pcr') {
				?>
					<br><br>
					<div>Only For Admin</div>

					<br>


					<label><input type="radio" name="group[tg_other_options][is_rpp]" value="0" <?php checked(isset($group['tg_other_options']['is_rpp']) ? $group['tg_other_options']['is_rpp'] == '0' : '') ?> /> Default</label>
					<label><input type="radio" name="group[tg_other_options][is_rpp]" value="1" <?php checked(isset($group['tg_other_options']['is_rpp']) ? $group['tg_other_options']['is_rpp'] == '1' : '') ?> /> enabel (Amp,Cq)</label>
					<label><input type="radio" name="group[tg_other_options][is_rpp]" value="2" <?php checked(isset($group['tg_other_options']['is_rpp']) ? $group['tg_other_options']['is_rpp'] == '2' : '') ?> /> disable(Amp,cq)</label>

					<br>
					<br>

					<label><input type="radio" name="group[tg_is_covid]" value="0" />Not Covid</label>
					<label><input type="radio" name="group[tg_is_covid]" value="1" <?php checked(isset($group['tg_is_covid']) ? $group['tg_is_covid'] == 1 : '') ?> /> Main Covid Group</label>
					<label><input type="radio" name="group[tg_is_covid]" value="2" <?php checked(isset($group['tg_is_covid']) ? $group['tg_is_covid'] == 2 : '') ?> /> Other Covid Group</label>


				<?php
				} else {
				?>
					<input type="hidden" name="group[tg_other_options][is_rpp]" value="<?php echo isset($group['tg_other_options']['is_rpp']) ? $group['tg_other_options']['is_rpp'] : '' ?>" />
				<?php
				}
				?>
				<?php if ($group['tg_ID'] > 0 && $is_ci_admin) { ?>
					<br>
					<br>
					<a href="<?php Tr_Base_Class_V6::link(array('act' => 'bulk_edit_tests', 'trajax' => '1', 'width' => 1400, 'height' => 800)) ?>" class="thickbox">Bulk Edit Tests</a>
				<?php } ?>
			</div>
		</div>
		<div class="frow">
			<input type="submit" class="button-primary" value="Submit" />


			<input type="hidden" name="group[tg_ID]" value="<?php echo isset($group['tg_ID']) ? $group['tg_ID'] : 0 ?>" />
			<input type="hidden" name="group[tg_type]" value="<?php echo @$tes_type ?>" />
		</div>
	</form>

</div>

<style>
	body .frow>textarea {
		height: 70px;
	}

	body .alwayshide {
		display: none !important;
	}

	.fieldabxvalue label,
	.fieldabxvalue2 label {
		width: 29%;
		float: left;
		text-align: right;
		line-height: 35px;
		padding-right: 1%;
		box-sizing: border-box;
	}

	.fieldabxvalue input[type="text"],
	.fieldabxvalue2 input[type="text"] {
		width: 69%;
	}

	#TB_ajaxContent .frow h4 {
		font-weight: normal;
		font-size: 11px;
		padding: 0;
		margin: 0;
		line-height: 12px;
		text-align: center;
		min-height: 25px;
	}

	<?php if (!$is_ci_admin) {
		echo '.onlyfor_ciadmin,#pcr_abx_box {display: none !important;}';
	} ?>
</style>

<script>
	jQuery(function($) {
		$('body').on('click', '.remove_logo_image', function() {
				$('[name="notes[pdf_logo]"]').val('');
				$('.ct_logo_image').remove();
			})
			.on('change', 'select[name="group[tg_parent]"]', function() {
				var $prid = $(this).val();
				if ($prid > 0) {
					$('#field_new_abx').hide();
					$('#field_new_abx input[type="checkbox"]').prop('checked', false);
					$('#field_new_abx_date').show();
					$('#field_abx_type').show();
					$('#field_new_abx_title').hide();
					$('#field_quantitative').hide();
					$('#field_abx_formula').hide();
					$('.fieldabxvalue').hide();
					$('.fieldabxvalue input[type="text"]').val('');
					if ($('input[name="group[tg_ID]"]').val() == '0') {
						$('input[name="group[tg_show_req]"]').prop('checked', false);
					}

				} else {
					$('#field_new_abx').show();
					$('#field_new_abx_date').show();
					$('#field_abx_type').show();
					$('#field_new_abx_title').show();
					$('#field_quantitative').show();
					$('#field_abx_formula').show();
				}
			}).on('change', '#field_abx_formula select', function() {
				var $formu = $(this).val();
				$('.fieldabxvalue').hide();
				if ($formu != '') {
					$('.fieldabxvalue.formu_' + $formu).show();
				}
			}).on('change', 'input[type="checkbox"][name="group[tg_antibiotic]"]', function() {
				if ($(this).is(':checked')) {
					$('#pcr_abx_box').css("visibility", "hidden");
					$('[name="group[tg_other_options][new_abx]"]').prop('checked', false);
					$('#grouptg_other_optionsabx_type').val('');
				} else {
					$('#pcr_abx_box').css("visibility", "visible");
				}
			}).on('click', '.remove-guide-pdf', function(e) {
				e.preventDefault();
				if (confirm('Are you sure you want to remove this PDF?')) {
					$('.guide-pdf-remove-flag').val('1');
					$(this).closest('.current-guide-pdf').fadeOut();
				}
				return false;
			}).on('change', 'select[name="group[tg_notshow_for_ref_lab_ID][]"]', function() {
				var selected = $(this).val() || [];
				if (selected.length > 0) {
					$('select[name="group[tg_show_for_ref_lab_ID][]"]').val([]).change();
				}
			}).on('change', 'select[name="group[tg_show_for_ref_lab_ID][]"]', function() {
				var selected = $(this).val() || [];
				if (selected.length > 0) {
					$('select[name="group[tg_notshow_for_ref_lab_ID][]"]').val([]).change();
				}
			}).on('change', 'select[name="group[tg_show_cli_IDs][]"]', function() {
				var selected = $(this).val() || [];
				if (selected.length > 0) {
					$('select[name="group[tg_notshow_cli_IDs][]"]').val([]).change();
				}
			}).on('change', 'select[name="group[tg_notshow_cli_IDs][]"]', function() {
				var selected = $(this).val() || [];
				if (selected.length > 0) {
					$('select[name="group[tg_show_cli_IDs][]"]').val([]).change();
				}
			})

		$('.my-color-field').wpColorPicker();
		$('select[name="group[tg_parent]"]').change();
		$('#field_abx_formula select').change();
		$('input[type="checkbox"][name="group[tg_antibiotic]"]').change();
	})
</script>