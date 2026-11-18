<?php
global $ci_all_btypes;

$current_user = wp_get_current_user();
$is_admin = $current_user->user_login == 'Administrat0r';
$has_access_antibiotics = $is_admin || isset($_REQUEST['view']) || isset($_COOKIE['trquery_monitor']) || current_user_can('manage_antibiotics_pathogens');
if (defined('TRPDD_ARK') && TRPDD_ARK) {
    $has_access_antibiotics = false;
}
$can_edit_statuses = current_user_can('ci_edit_statuses') || $is_admin;

?>
<?php if (!in_array($tes_type, array('req'))) { ?>
    <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2 bsty4">Add New Test</a>
<?php } ?>
<?php if (in_array($tes_type, $ci_all_btypes)) { ?>
    <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'groups', 'type' => $tes_type, 'act' => '')) ?>" class="add-new-h2 bsty5">Groups</a>
<?php } ?>
<!-- <-?php if ($tes_type == 'tox') { ?>
    <a href="<-?php Tr_Base_Class_V6::link(array('tabtype' => 'default_profile', 'type' => $tes_type, 'act' => '')) ?>" class="add-new-h2">Default Profile</a>
<-?php } ?> -->
<?php if ($can_edit_statuses) { ?>
    <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'batch_status', 'id' => '', 'act' => '')) ?>" class="add-new-h2">Statuses</a>
<?php } ?>
<?php if (!in_array($tes_type, array('req'))) { ?>
    <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'diagnostic_codes', 'id' => '', 'act' => '')) ?>" class="add-new-h2">Diagnostic Codes</a>
<?php }

if ($tes_type == 'pcr' && tr_get_option('ci_req_settings_pcr', 'e_wound_sources')) {
?>
    <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'wound_sample_source', 'type' => $tes_type, 'act' => '')) ?>" class="add-new-h2">Specimen Location/Source</a>
<?php
}
?>
<a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'specimens', 'id' => '', 'act' => '')) ?>" class="add-new-h2">Specimens</a>
<a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'questions', 'type' => $tes_type, 'act' => '')) ?>" class="add-new-h2">AOE Questions</a>
<a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'req_settings', 'type' => $tes_type, 'act' => '')) ?>" class="add-new-h2">Req Settings</a>
<a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'result_settings', 'type' => $tes_type, 'act' => '')) ?>" class="add-new-h2">Result Settings</a>

<?php

if ($is_admin) { ?>
    <a title="Only show for admin" href="<?php Tr_Base_Class_V6::link(array('type' => $tes_type, 'tabtype' => 'logs')) ?>" class="add-new-h2">Logs</a>
<?php
}
?>

<br>

<?php if (in_array($tes_type, array('tox', 'blood', 'gene'))) { ?>
    <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'medications', 'id' => '', 'act' => '')) ?>" class="add-new-h2">Medications</a>
<?php } ?>

<a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'warn', 'id' => '', 'act' => '')) ?>" class="add-new-h2">Warn Pop-up</a>


<a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'pdf_notes', 'type' => $tes_type, 'act' => '')) ?>" class="add-new-h2">PDF Notes</a>

<a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'pdf_footer', 'type' => $tes_type, 'act' => '')) ?>" class="add-new-h2">PDF Footer</a>
<?php

if (isset($rs_settings['result_cancel_rejected']) && $rs_settings['result_cancel_rejected']) {
?>
    <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'rejected_comments', 'id' => '', 'act' => '')) ?>" class="add-new-h2">Comments</a>
    <?php
}
if ($tes_type == 'pcr') {


    if (tr_get_option('ci_result_settings_pcr', 'e_leyvey')) {
    ?>
        <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'pcr_theoretical', 'type' => $tes_type, 'act' => '')) ?>" class="add-new-h2">Theoretical</a>
    <?php } ?>

    <?php if ($has_access_antibiotics) { ?>
        <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'antibiotics', 'type' => $tes_type, 'act' => '')) ?>" class="add-new-h2">Antibiotics</a>
        <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'pathogens', 'type' => $tes_type, 'act' => '')) ?>" class="add-new-h2">Pathogens</a>
    <?php } ?>

    <?php if (!defined('TRPDD_ARK') || (defined('TRPDD_ARK') && !TRPDD_ARK)) { ?>
        <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'biomic_messages', 'type' => $tes_type, 'act' => '')) ?>" class="add-new-h2">BIOMIC Messages</a>
        <?php
    }
    if ($is_admin) {
        if (tr_get_option('ci_pdf_data', 'new_abx')) {
        ?>
            <a title="Only show for admin" href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'local_resistance', 'trajax' => 1, 'type' => $tes_type, 'act' => '')) ?>" class="add-new-h2 thickbox">Local Resistance</a>
    <?php
        }
    }
} else if ($tes_type == 'blood') {
    ?>

    <a href="<?php Tr_Base_Class_V4::link(array('tabtype' => 'cu_pathogens', 'type' => $tes_type)) ?>" class="add-new-h2">CU Pathogen</a>
    <a href="<?php Tr_Base_Class_V4::link(array('tabtype' => 'cu_antibiotics', 'type' => $tes_type)) ?>" class="add-new-h2">CU Antibiotics</a>
<?php
}

?>


<a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'import_export', 'type' => $tes_type, 'act' => '', 'step' => '')) ?>" class="add-new-h2">Import & Export</a>

<?php



do_action('lab_addon_admin_action', 'panel_buttons', array('btype' => $tes_type));
?>