<?php

$options_panel->OpenTab('abx_tab');
$updateabxlink = '';
$latest_abx_updated = '';

if ($is_ci_admin) {
	$updateabxlink = "<a href='" . Tr_Base_Class_V6::link(array('trajax' => 'update_abx', 'width' => 1400), false) . "' class='thickbox'>Update DB</a>";
	$latest_abx_updated = get_option('_ci_last_update_abx', null);
	if ($latest_abx_updated) {
		$latest_abx_updated = date_i18n('Y-m-d H:i:s', $latest_abx_updated);
	}
}
$options_panel->Title(__("New ABX " . $updateabxlink . " - (Last update: " . $latest_abx_updated . ")"));


$options_panel->addCheckbox('new_abx', array('name' => 'Enable New ABX', 'target' => 'new_abx_rm0,new_abx_orphancol,new_abx_prio_oral,new_abx_prio_acti,new_abx_ob4acti,new_abx_mkrbrs,cli_new_abx,new_abx_style,new_abx_woral,new_abx_v_style,new_abx_contact,new_abx_pie,new_abx_dcache,new_abx_percentp'));
$options_panel->addCheckbox('new_abx_rm0', array('name' => 'shade risk 0'));
$options_panel->addCheckbox('new_abx_orphancol', array('name' => 'show Column Orphan'));
$options_panel->addCheckbox('new_abx_pregnancy_uti', array('name' => 'Pregnancy UTI', 'target' => 'new_abx_pregnancy_by'));
$options_panel->addSelect('new_abx_pregnancy_by', array('age' => 'by Age + Female', 'aoe' => 'by AoE'), array('name' => 'Pregnancy By'));
$options_panel->addCheckbox('new_abx_baseabx', array('name' => 'Show Base ABX'));
$options_panel->addCheckbox('new_abx_prio_oral', array('name' => 'Prioritize Oral (default: true)', 'std' => true));
$options_panel->addCheckbox('new_abx_prio_acti', array('name' => 'Prioritize Activity (default: true)', 'std' => true));
$options_panel->addCheckbox('new_abx_ob4acti', array('name' => 'Prioritize Oral before Activity (default: false)', 'std' => false));
$options_panel->addCheckbox('new_abx_mkrbrs', array('name' => 'Markers by resulted (default: false)', 'std' => false));
$options_panel->addCheckbox('cli_new_abx', array('name' => 'Enable New ABX only for Clinic Selected'));
$options_panel->addCheckbox('new_abx_woral', array('name' => 'Show Warning when no Oral match'));
$options_panel->addCheckbox('new_abx_percentp', array('name' => 'Show % in Pathogen detected '));
$options_panel->addCheckbox('new_abx_dcache', array('name' => 'Disable Cache abx(only for demo)'));
$options_panel->addCheckbox('new_abx_pie', array('name' => 'Enable PIE Chart'));
$options_panel->addCheckbox('new_abx_biofilm', array('name' => 'Enable BioFilm'));
$options_panel->addCheckbox('new_abx_piefactor10', array('name' => 'pie chart for a factor of 10 (UTI/Wound)'));
$options_panel->addCheckbox('new_abx_piefactor10_bv', array('name' => 'pie chart for a factor of 10 (BV)'));
$options_panel->addCheckbox('new_abx_bvnewrisk', array('name' => 'Enable New Risk Calc for BV'));
$options_panel->addCheckbox('new_abx_bvdesc', array('name' => 'Show Description for BV'));
$options_panel->addCheckbox('new_abx_contact', array('name' => 'Enable ABX Contact'));
$options_panel->addSelect('new_abx_style', array(1 => 'Style 1', 2 => 'Style 2'), array('name' => 'New ABX Style'));
$options_panel->addSelect('new_abx_v_style', array(1 => 'Style 1', 2 => 'Style 2'), array('name' => 'V Style'));
$options_panel->addCheckbox('new_abx_pediatric', array('name' => 'Pediatric ranges'));
$options_panel->addCheckbox('new_abx_quant_detected', array('name' => 'Hide Detected in pie chart for Quantitative'));
$options_panel->addCheckbox('new_abx_respiratory_algo', array('name' => 'Enable Respiratory-specific algorithm'));
$options_panel->addCheckbox('new_abx_v_amr', array('name' => 'Enable V-AMR rules'));
$options_panel->addCheckbox('new_abx_yeast_separate_groups', array('name' => 'Enable Yeast Separate Groups'));
$options_panel->addCheckbox('new_abx_hide_infection_complexity', array('name' => 'Hide Infection Complexity'));
$options_panel->addCheckbox('new_abx_change_treatment_title', array('name' => 'change "TOP ALGORITHMIC ANTIBIOTIC OPTIONS" to "TREATMENT OPTIONS"'));
$options_panel->addCheckbox('new_abx_pagination', array('name' => 'Show Pagination separately for ABX report'));
$options_panel->addCheckbox('new_abx_separate_opportunistic', array('name' => 'Separate Opportunistic'));
$options_panel->addCheckbox('new_abx_p_quant_new', array('name' => 'Enable %quantitative New'));
$options_panel->addCheckbox('new_abx_sumquantscore', array('name' => 'Enable Quantitative Sum ABX-score'));
$options_panel->addCheckbox('new_abx_pair_30_oral90', array('name' => 'Change Pair < 3 Oral >90'));

/*
$options_panel->Title(__("<br>old ABX"));


$options_panel->addCheckbox('enable_utire', array('name' => 'Enable UTI ABX report', 'target' => 'enable_utire_start,enable_utire_ref_lab'));

$options_panel->addCheckbox('enable_utinrow', array('name' => 'Enable UTI new row after panel title'));
$options_panel->addCheckbox('enable_utire_ref_lab', array('name' => 'Enable UTI ABX report for ref lab'));
$options_panel->addDate('enable_utire_start', array('name' => 'UTI ABX report start date', 'format' => 'yy-mm-dd'));

$options_panel->addCheckbox('enable_woundabx', array('name' => 'Enable Wound ABX report', 'target' => 'enable_woundabx_start,enable_woundabx_ref_lab'));
$options_panel->addCheckbox('enable_woundabx_ref_lab', array('name' => 'Enable Wound ABX report for ref lab'));
$options_panel->addDate('enable_woundabx_start', array('name' => 'Wound ABX report start date', 'format' => 'yy-mm-dd'));

$options_panel->addCheckbox('enable_giabx', array('name' => 'Enable GI ABX report', 'target' => 'enable_giabx_start,enable_giabx_ref_lab'));
$options_panel->addCheckbox('enable_giabx_ref_lab', array('name' => 'Enable GI ABX report for ref lab'));
$options_panel->addDate('enable_giabx_start', array('name' => 'GI ABX report start date', 'format' => 'yy-mm-dd'));
*/

if (defined('TRPDD_ARK')) {
	$options_panel->Title(__("<br>old ABX"));

	$options_panel->addCheckbox('enable_utire', array('name' => 'Enable UTI ABX report', 'target' => 'enable_utire_start,enable_utire_ref_lab'));

	$options_panel->addCheckbox('enable_utinrow', array('name' => 'Enable UTI new row after panel title'));
	$options_panel->addCheckbox('enable_utire_ref_lab', array('name' => 'Enable UTI ABX report for ref lab'));
	$options_panel->addDate('enable_utire_start', array('name' => 'UTI ABX report start date', 'format' => 'yy-mm-dd'));

	$options_panel->addCheckbox('enable_woundabx', array('name' => 'Enable Wound ABX report', 'target' => 'enable_woundabx_start,enable_woundabx_ref_lab'));
	$options_panel->addCheckbox('enable_woundabx_ref_lab', array('name' => 'Enable Wound ABX report for ref lab'));
	$options_panel->addDate('enable_woundabx_start', array('name' => 'Wound ABX report start date', 'format' => 'yy-mm-dd'));

	$options_panel->addCheckbox('enable_giabx', array('name' => 'Enable GI ABX report', 'target' => 'enable_giabx_start,enable_giabx_ref_lab'));
	$options_panel->addCheckbox('enable_giabx_ref_lab', array('name' => 'Enable GI ABX report for ref lab'));
	$options_panel->addDate('enable_giabx_start', array('name' => 'GI ABX report start date', 'format' => 'yy-mm-dd'));
}

$options_panel->CloseTab();
