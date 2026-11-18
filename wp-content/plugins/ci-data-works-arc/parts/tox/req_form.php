<?php
global $wpdb;
global $current_role;
global $ci_all_btypes;


$btype              = 'tox';
$table_batch        = 'ci_batches';
$mid              = 0;
if (isset($is_prefill_covid) && $is_prefill_covid) {
    $table_batch = 'ci_saved_batches';
}


$user_id            = get_current_user_id();
$current_clinic_id  = 0;
$has_billing_edit   = true;
$is_hold            = false;
$current_doctor_sign  = '';
$where_doctor       = '';
$batmeta            = array();
$settings           = get_option('ci_req_settings_tox', array());
$show_npi           = tr_get_option('ci_pdf_data', 'req_show_npi');
$print_times        = tr_get_option('ci_pdf_data', 'tox_print_times', 1);
$required_insurance = 'required';
if (tr_get_option('ci_pdf_data', 'unrequired_insu')) {
    $required_insurance = '';
}
$required_insurance_address = $required_insurance;
if (tr_get_option('ci_pdf_data', 'unrequired_ainsu')) {
    $required_insurance_address = '';
}


$rolecantuploadrs = array('office_manager', 'doctor', 'collector');
if (tr_get_option('ci_pdf_data', 'collectorcanuprs')) {
    $rolecantuploadrs = array('office_manager', 'doctor');
}

$id                 = isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;
$saved_id           = 0;
$act                = isset($_REQUEST['act']) ? $_REQUEST['act'] : @$act;

$current_medications = tr_pdd_get_current_medications();
$Medications        = tr_pdd_get_medications();


$tabs = array(
    '' => 'Toxicology Form',
    //'temp_batches' => 'Temp Batches',
    'current_batches' => 'Completed Req Forms',
    'req_hold' => 'Reqs On Hold',
    'saved_form' => 'Saved'
);


if (!tr_get_option('ci_pdf_data', 'req_show_hold')) {
    unset($tabs['req_hold']);
}

$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);

if ($act == 'print') {
?>
    <link href="<?php echo TRCPDD_URL ?>css/print.css?<?php echo TRCPDD_VS ?>" media="all" type="text/css" rel='stylesheet' />
<?php
} else if (@$_REQUEST['page'] == 'ci_short_list' || (isset($hide_tabs) && $hide_tabs)) {
    include_once(TRCPDD_PATH . 'pages/formdata.php');
} else if (!isset($_REQUEST['not_show_tab']) && !isset($_REQUEST['trajax'])) {
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
} else if (isset($billing_page) && $billing_page) {
    include_once(TRCPDD_PATH . 'pages/formdata.php');
}

$required_insurance = 'required';
if (tr_get_option('ci_pdf_data', 'unrequired_insu')) {
    $required_insurance = '';
}
$required_insurance_address = $required_insurance;
if (tr_get_option('ci_pdf_data', 'unrequired_ainsu')) {
    $required_insurance_address = '';
}



$conditions     = trpdd_get_batch_conditions(0, 'tox');
$cli            = array();
$collector      = array();
$current_lab    = array();
$doctors        = array();
$collectors     = array();
$clinics        = array('' => 'Please Select');
$where_cli      = $conditions['where_clinic'];

$lab_id         = CI_Base_Lab::get_current_lab_id();

$where_ref_lab  = '';
$cli_id         = 0;


if ($current_role == 'collector') {

    $col_id = get_user_meta(get_current_user_id(), '_collector_id', true);
    $collector = $wpdb->get_row("select * from ci_collector where col_ID ={$col_id}", ARRAY_A);
    $rep_id = $collector['rep_ID'];
    $where_cli .= " and cc.col_ID = {$col_id} ";
} else if ($current_role == 'distributor') {
    $dis_id = $wpdb->get_var("select dis_ID from ci_distributors where dis_user_ID = {$user_id}");

    $arr_marketing_groups = $wpdb->get_results("SELECT * FROM `ci_marketing_groups` where `gr_distributor_ID`= {$dis_id} and `gr_inactive`='0'");
    $g_ids = array(-1);
    foreach ($arr_marketing_groups as $g) {
        $g_ids[] = $g->gr_ID;
    }
    $g_ids = implode(',', $g_ids);

    $where_rep .= " and (`rep_marketing_group_ID` IN ({$g_ids}) or rep_distributor_ID={$dis_id}) ";
    $where_group .= " and gr_distributor_ID = {$dis_id} ";
} else if ($current_role == 'doctor') {
    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where_cli .= " and cli.cID = {$current_clinic} ";
    }
    $doc_id = get_user_meta($user_id, '_doc_id', true);
    $clinics = $wpdb->get_col("select cli_ID from ci_clinic_doc where doc_ID={$doc_id} ");
    $clinics = implode(',', $clinics);
    if (empty($clinics)) {
        $clinics = '0';
    }
    $where_cli .= " and cli.cID in ({$clinics}) ";
    $where_doctor .= " and doc.doc_ID = {$doc_id} ";
} else if (tr_current_role_in(array('ref_lab_tech', 'reflab_manager'))) {
    $ref_lab_id = get_user_meta($user_id, '_ref_lab_id', true);
    $where_ref_lab = " and lab_ID = {$ref_lab_id} ";
}


$required_sign = true;
if (tr_current_role_in(array('administrator', 'administrator_p', 'AdminCIDW', 'labsecman', 'leadlabsecman', 'labmanager'))) {
    $required_sign = false;
}


if ($tab == 'current_batches') {
    include(trpdd_get_file_path('tox', 'req_list'));
    return;
} else if ($tab == 'saved_form') {
    if (!empty($act) && $act != 'delete') {
        $saved_id = $_REQUEST['id'];
    } else {
        include(trpdd_get_file_path('tox', 'req_saved'));
        return;
    }
} else if ($tab == 'prior_authorization') {
    include(trpdd_get_file_path('global', 'prior_authorization.php'));
    return;
} else if (!empty($tab)) {
    $tabfile = trpdd_get_file_path($btype, $tab);
    if (file_exists($tabfile)) {
        include($tabfile);
        return;
    }
}

$assen_readonly = false;
$bat['medication'] = '';
$collectors = array();

if ($act == 'view' || $act == 'edit' || $act == 'print') {

    $action_form = '';
    $id = $_REQUEST['id'];
    $bat = $wpdb->get_row("select * from {$table_batch} where mid='{$id}' ", ARRAY_A);
    $cli['cID'] = $bat['cli_ID'];
    $cli_id = $cli['cID'];
    $cli = $wpdb->get_row("select * from ci_clinic where `cID`={$cli_id}", ARRAY_A);

    $pa = $wpdb->get_row("select pa.* from ci_patients pa
                           where pa.pa_ID = '{$bat['patient_id']}' ", ARRAY_A);

    if ($cli_id > 0) {
        $where_cli .= " or cID = {$cli_id} ";
    }

    if (!$pa) {
        $pa = array();
        list($pa['pa_last'], $pa['pa_first']) = explode(',', @$bat['patient_name']);
        $pa['pa_dob'] = $bat['dob'];

        $pa['pa_first'] = trim($pa['pa_first']);
        $pa['pa_last'] = trim($pa['pa_last']);
    }
    $ins1 = $wpdb->get_row("select * from ci_insurances where ins_ID = '{$bat['insurance1']}' ", ARRAY_A);
    $ins2 = $wpdb->get_row("select * from ci_insurances where ins_ID = '{$bat['insurance2']}' ", ARRAY_A);

    $collector = $wpdb->get_row("select * from ci_collector where col_ID ={$bat['col_ID']}", ARRAY_A);

    $bat['current_medication'] = !empty($bat['current_medication']) ? explode(',', $bat['current_medication']) : array();
    $bat['current_medication_other'] = !empty($bat['current_medication_other']) ? explode(',', $bat['current_medication_other']) : array();


    $bat['current_medication'] = array_map('trim', $bat['current_medication']);
    $bat['current_medication_other'] = array_map('trim', $bat['current_medication_other']);


    if (count($bat['current_medication_other']) == 1 && trim($bat['current_medication_other'][0]) == '') {
        $bat['current_medication_other'] = array();
    }
    foreach ($bat['current_medication'] as $m) {
        $m = trim($m);
        if ($m == 'Fioricet' || $m == 'Lortab') continue;
        if (!in_array($m, $current_medications) && !empty($m) && $m != 'None Listed') {
            $bat['current_medication_other'][] = $m;
        }
    }

    if (isset($_GET['debugmedication'])) {
        var_dump($bat['current_medication']);
        var_dump($bat['current_medication_other']);
    }

    if (strpos($bat['panel_tests_requested'], '+') > 0) {
        $bat['panel_tests_requested'] = explode('+', $bat['panel_tests_requested']);
        $bat['panel_tests_requested'] = end($bat['panel_tests_requested']);
    }
    $bat['panel_tests_requested'] = explode(',', @$bat['panel_tests_requested']);
    $bat['panel_tests_requested'] = array_map('trim', $bat['panel_tests_requested']);
    $bat['panel_tests_requested'] = array_map('strtoupper', $bat['panel_tests_requested']);

    foreach ($bat['panel_tests_requested'] as $k => $v) {
        if (strpos($v, '(') > 0) {
            $bat['panel_tests_requested'][] = trim(substr($v, 0, strpos($v, '(')));
        }
    }


    $bat['medication'] = maybe_unserialize(@$bat['medication']);

    if (empty($bat['custom_profile'])) {
        $bat['custom_profile'] = 'Do Not Use Custom Profile';
    }

    if ($bat['custom_profile'] == 'Highrisk') {
        $bat['custom_profile'] = 'Custom High Risk';
    } else if ($bat['custom_profile'] == 'Standard') {
        $bat['custom_profile'] = 'Custom Mid Risk';
    } else if ($bat['custom_profile'] == 'Basic') {
        $bat['custom_profile'] = 'Custom Low Risk';
    }

    //var_dump(@$bat);
    $batch_key = $bat['assen'];

    if ($billing_page) {

        $sql = "SELECT
            lab.*,
            ba.*,
            ba.patient_id as pa_ID,
            rl.lab_additional as rl_lab_additional,
            re.*
        FROM ci_reports re
    
        JOIN {$table_batch} ba ON  ba.assen = re.patient_id
    
        left JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
        LEFT JOIN ci_labs rl ON rl.lab_ID = ba.ref_lab_ID
        WHERE ba.mid='" . $id . "' group by ba.mid limit 1";

        $report = $wpdb->get_row($sql, ARRAY_A);
    }

    if ($bat['lab_id'] > 0) {
        $lab_id = $bat['lab_id'];
    }

    if ($act == 'edit') {
        $action_form = 'ci_save_form_requisition';
        $mid = $id;
    }

    $barcode_key = tr_get_option('ci_pdf_data', 'barcode_key');
    if (!empty($bat['assen']) && !empty($barcode_key) && stripos($bat['assen'], $barcode_key) === 0) {
        $assen_readonly = true;
    }

    //get meta
    $batmeta = array();
    $rs = $wpdb->get_results("select * from ci_batches_meta where mid = {$id}");
    foreach ($rs as $r) {
        $batmeta[$r->meta_key] = $r->meta_value;
    }

    $notallowchangepatient = tr_get_option('ci_pdf_data', 'disable_c_patient');

    $is_hold = $bat['batch_status'] == 'hold';
} else {
    $bat['panel_tests_requested'] = array();
    $bat['custom_profile'] = 'Do Not Use Custom Profile';
    $bat['medication'] = array();
    $bat['current_medication'] = array();
    $bat['poc_perform'] = '0';
    $bat['dagnostic_codes1'] = '';
    $bat['temperature'] = '';
    $bat['col_ID'] = 0;
    $pa = array();
    $ins1 = array();
    $ins2 = array();

    $action_form = 'ci_save_form_requisition';


    if ($cli_id == 0 && $current_clinic_id > 0) {
        $cli_id = $bat['cli_ID'] = $cli['cID'] = $current_clinic_id;
    }

    if (tr_get_option('ci_pdf_data', 'current_doc')) {
        $bat['doc'] = date_i18n('Y-m-d H:i:s');
    }
}



if ($current_clinic_id > 0) {
    $where_cli .= " and (cli.cID = {$current_clinic_id} or cli.cID = '{$bat['cli_ID']}')";
}

if (isset($_POST['clinic_id'])) {
    $cli_id = $_POST['clinic_id'];
    $cli = $wpdb->get_row("select * from ci_clinic where `cID`={$cli_id}", ARRAY_A);
}


if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $where_cli .= " and cli.`cli_lab-ID` in ( {$lab_str} ) ";
}

if (empty($bat['type_of_specimen'])) {
    $bat['type_of_specimen'] = 'Urine';
}
if (empty($bat['ins_comp'])) {
    $bat['ins_comp'] = 'ins';
}
$card_dir = tr_get_ins_card_dir();
$sign_dir = tr_get_signature_dir();

$clinic_more_fields = '';
if (isset($settings['e_phase']) && $settings['e_phase']) {
    $clinic_more_fields .= ', (select count(*) from ci_marketing_groups where gr_ID = cli.cli_marketing_group_ID and gr_tox_phase=1) as phase ';
}
$sql_clinic = "select cli.* 
                    {$clinic_more_fields}
					from ci_clinic cli
                    Left JOIn ci_clinic_collector cc ON cc.cli_ID = cli.cID

                    left join ci_lab_reps lr on cli.`cli_rep-ID` = lr.rep_ID
                    where {$where_cli} group by cli.cID order by cli.cli_clinic";
$clinics = $wpdb->get_results($sql_clinic, ARRAY_A);
if (count($clinics) == 1) {
    $cli = $clinics[0];
    $cli_id = $bat['cli_ID'] = $cli['cID'];
}

if ($cli_id > 0) {
    $doctors = $wpdb->get_results("select doc.*,
    cp.cus_individuals,cp.cus_med_risk,cp.cus_high_risk,cp.cus_ID,cp.cus_df_profile
    from ci_doc doc
    JOIn ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID 
    left JOIN ci_custom_providers cp ON doc.doc_ID = cp.doc_ID and cp.cus_type='tox'
    where cd.cli_ID = {$cli_id} and
    (cp.cli_ID = {$cli_id} or (select count(*) from ci_custom_providers cp2 where doc.doc_ID = cp2.doc_ID and cp2.cli_ID = {$cli_id} and cp2.cus_type='tox' )=0 )
    
    {$where_doctor}
    group by doc.doc_ID
    order by doc.doc_last asc", ARRAY_A);

    $current_lab = $wpdb->get_row("select * from ci_labs Where lab_ID = {$lab_id}", ARRAY_A);

    $collectors = $wpdb->get_results("select col.* from ci_collector col
                            JOIN ci_clinic_collector cc ON cc.col_ID = col.col_ID
                            where cc.cli_ID ={$cli_id} and (col.col_status=1 or col.col_ID = '{$bat['col_ID']}')", ARRAY_A);
} else {
    $current_lab = CI_Base_Lab::get_current_lab();
}

$labs       = CI_Base_Lab::get_user_labs();
$ref_labs   = $wpdb->get_results("select * from ci_labs  
                where (lab_is_ref=1 or lab_is_ref=2)
                 {$where_ref_lab} 
                order by lab_dba");

$bat['show_phase'] = false;
if (isset($_REQUEST['debug'])) {
    var_dump($sql_clinic);
}
?>
<div class="wrap_form wrap wraptoxform">

    <?php if ($act == 'print') include(trpdd_get_file_path('global', 'print_req_header')); ?>

    <form method="post" enctype="multipart/form-data" class="need_validate main_form <?php if (!$bat || !isset($bat['mid']) || !$bat['mid']) echo 'emptyform' ?>" id="requisition_form">
        <h2 class="center">THERAPEUTIC DRUG TEST REQUISITION FORM</h2>
        <?php Tr_Session_Class::show() ?>

        <?php
        if ($act == 'view' || $billing_page) {  ?>
            <div class="right foutrow">
                <label>Accession Number</label>
                <span class="text bold binline" style="width:200px"><?php echo @$bat['assen'] ?>&nbsp;&nbsp;</span>
            </div>
        <?php } ?>

        <div class="frow blabel hlabel">
            <div class="col5">
                <?php
                $has_lab_label = false;
                if (count($labs) > 1) {
                    $labs_options = array('' => 'Select Lab');
                    $only_labids = array();
                    if (count($labs) > 1 && tr_current_role_in(array('office_manager', 'doctor'))) {
                        foreach ($clinics as $clinic) {
                            $only_labids[] = $clinic['cli_lab-ID'];
                        }
                        if (isset($mid) && $mid > 0) {
                            $only_labids[] = $bat['lab_id'];
                        }
                    }


                    if (empty($only_labids) && tr_current_role_in(array('office_manager', 'doctor', 'collector'))) {
                        $labs = array();
                    }
                    $one_lab_id = 0;
                    foreach ($labs as $l) {
                        if (count($only_labids) > 0 && !in_array($l['lab_ID'], $only_labids)) {
                            continue;
                        }
                        $labs_options[$l['lab_ID']] = $l['lab_dba'];
                        $one_lab_id = $l['lab_ID'];
                    }
                    if (!isset($mid) || empty($mid)) {
                        if (count($labs_options) == 2) {
                            $bat['lab_id'] = $one_lab_id;
                        } else if (empty($bat['lab_id'])) {
                            $bat['lab_id'] = CI_Base_Lab::get_current_lab_id();
                        }
                    }

                    if (count($labs_options) > 1) {
                        $has_lab_label = true;
                ?>
                        <label>Lab</label>
                        <?php tr_select_option('bat[lab_id]', $labs_options, $bat['lab_id'], array('attr' => 'required')) ?>
                    <?php
                    }
                }

                if (count($ref_labs) > 0) {
                    $hide_ref_lab = !tr_get_option('ci_pdf_data', 'show_reflab_comreqs');
                    $ref_labs_options = array('' => 'Select Ref Lab');
                    if (tr_current_role_in(array('ref_lab_tech', 'reflab_manager'))) {
                        $ref_labs_options = array();
                    }
                    foreach ($ref_labs as $l) {
                        $ref_labs_options[$l->lab_ID] = $l->lab_name;
                    }

                    if (!tr_get_option('ci_pdf_data', 'disable_rlbcl')) {
                        if (is_array($cli) && $cli['cli_ref_lab_ID'] > 0) {
                            $hide_ref_lab = true;
                        } else if (tr_current_role_in(array('office_manager'))) {
                            $has_cli_ref_lab = false;
                            foreach ($clinics as $clinic) {
                                if ($clinic['cli_ref_lab_ID'] > 0) {
                                    $hide_ref_lab = true;
                                    break;
                                }
                            }
                        }
                    }

                    if (count($ref_labs_options) > 0 && ! tr_current_role_in(array('office_manager', 'doctor', 'collector', 'ref_lab_tech'))) {
                        $has_lab_label = !$hide_ref_lab;
                    ?>

                        <div id="ref_lab_area" <?php if ($hide_ref_lab) echo 'style="display:none"' ?>>
                            <label class="h4">Ref Lab</label>
                            <?php tr_select_option('bat[ref_lab_ID]', $ref_labs_options, $bat['ref_lab_ID'], array()) ?>
                        </div>
                <?php
                    }
                }
                ?>
                <label <?php if (!$has_lab_label) echo 'class="h4"' ?>>PRACTICE INFORMATION</label>
                <?php
                if (tr_current_role_in(array('lab_sec'))) {
                    $oattr = ' data-address="' . (isset($clinic['cli_street']) ? $clinic['cli_street'] : '') . '" ';
                    $oattr .= ' data-city="' . (isset($clinic['cli_city']) ? $clinic['cli_city'] : '') . '" ';
                    $oattr .= ' data-state="' . (isset($clinic['cli_state']) ? $clinic['cli_state']  : '') . '" ';
                    $oattr .= ' data-zip="' . (isset($clinic['cli_zip']) ? $clinic['cli_zip'] : '') . '" ';
                    $oattr .= ' data-phone="' . (isset($clinic['cli_om-phone']) ? $clinic['cli_om-phone'] : '') . '" ';
                    $oattr .= ' data-print2="' . (isset($clinic['cli_print_2labels']) ? $clinic['cli_print_2labels'] : '') . '" ';
                    $oattr .= ' data-pbtn="' . (isset($clinic['cli_show_submit_print_bc']) ? $clinic['cli_show_submit_print_bc'] : '') . '" ';
                    $oattr .= ' data-autoa="' . (isset($clinic['cli_auto_assen']) ? $clinic['cli_auto_assen'] : '') . '" ';
                    $oattr .= ' data-reflab="' . (isset($clinic['cli_ref_lab_ID']) ? $clinic['cli_ref_lab_ID'] : '') . '" ';
                    $oattr .= ' data-phase="' . (isset($clinic['phase']) ? $clinic['phase'] : '') . '" ';
                    $oattr .= ' data-screening="' . (isset($clinic['cli_screening']) ? $clinic['cli_screening'] : '') . '" ';
                    $oattr .= ' data-only_screening="' . (isset($clinic['cli_only_screening']) ? $clinic['cli_only_screening'] : '') . '" ';
                    $oattr .= ' data-tox_df_profile="' . (isset($clinic['cli_tox_df_profile']) ? $clinic['cli_tox_df_profile'] : '') . '" ';
                ?>
                    <input type="text" class="cfname requesting_practice_auto" name="bat[requesting_practice]" value="<?php echo @$bat['requesting_practice'] ?>" required />
                    <input type="hidden" name="bat[cli_ID]" value="<?php echo @$bat['cli_ID'] ?>" <?php echo $oattr ?> />
                <?php
                } else {
                ?>
                    <select name="bat[cli_ID]" required id="cli_change" class="js_select2">
                        <option value="">Select Clinic</option>
                        <?php
                        foreach ($clinics as $clinic) {
                            $selected = isset($cli['cID']) && $cli['cID'] == $clinic['cID'] ? 'selected' : '';

                            $oattr = ' data-address="' . (isset($clinic['cli_street']) ? $clinic['cli_street'] : '') . '" ';
                            $oattr .= ' data-city="' . (isset($clinic['cli_city']) ? $clinic['cli_city'] : '') . '" ';
                            $oattr .= ' data-state="' . (isset($clinic['cli_state']) ? $clinic['cli_state']  : '') . '" ';
                            $oattr .= ' data-zip="' . (isset($clinic['cli_zip']) ? $clinic['cli_zip'] : '') . '" ';
                            $oattr .= ' data-phone="' . (isset($clinic['cli_om-phone']) ? $clinic['cli_om-phone'] : '') . '" ';
                            $oattr .= ' data-print2="' . (isset($clinic['cli_print_2labels']) ? $clinic['cli_print_2labels'] : '') . '" ';
                            $oattr .= ' data-pbtn="' . (isset($clinic['cli_show_submit_print_bc']) ? $clinic['cli_show_submit_print_bc'] : '') . '" ';
                            $oattr .= ' data-autoa="' . (isset($clinic['cli_auto_assen']) ? $clinic['cli_auto_assen'] : '') . '" ';
                            $oattr .= ' data-reflab="' . (isset($clinic['cli_ref_lab_ID']) ? $clinic['cli_ref_lab_ID'] : '') . '" ';
                            $oattr .= ' data-phase="' . (isset($clinic['phase']) ? $clinic['phase'] : '') . '" ';
                            $oattr .= ' data-screening="' . (isset($clinic['cli_screening']) ? $clinic['cli_screening'] : '') . '" ';
                            $oattr .= ' data-only_screening="' . (isset($clinic['cli_only_screening']) ? $clinic['cli_only_screening'] : '') . '" ';
                            $oattr .= ' data-tox_df_profile="' . (isset($clinic['cli_tox_df_profile']) ? $clinic['cli_tox_df_profile'] : '') . '" ';


                            if ($selected == 'selected' && isset($clinic['phase']) && $clinic['phase']) {
                                $bat['show_phase'] = true;
                            }

                            echo '<option value="' . $clinic['cID'] . '" ' . $selected . ' ' . $oattr . '>' . $clinic['cli_clinic'] . '</option>';
                        }
                        ?>
                    </select>
                <?php } ?>


            </div>
            <div class="col5" id="cli_change_div">
                <label>ORDERING PHYSICIAN</label>
                <?php if (tr_current_role_in(array('lab_sec'))) {
                ?>
                    <input type="text" class="requesting_physician_auto cfname" name="bat[requesting_physician]" value="<?php echo @$bat['requesting_physician'] ?>" required />
                    <input type="hidden" name="bat[doc_ID]" value="<?php echo @$bat['doc_ID'] ?>">
                <?php
                } else {
                ?>
                    <select name="bat[doc_ID]" class="njs_select2">
                        <option value="">Select Doctor</option>
                        <?php
                        foreach ((array)$doctors as $d) {
                            $d['cus_individuals'] = strtoupper($d['cus_individuals']);
                            $d['cus_med_risk'] = strtoupper($d['cus_med_risk']);
                            $d['cus_high_risk'] = strtoupper($d['cus_high_risk']);
                            $selected = (isset($bat['doc_ID']) && $d['doc_ID'] == $bat['doc_ID']) || count($doctors) == 1 ? 'selected' : '';

                            if ($selected) {
                                $current_doctor_sign = $d['doc_signature'];
                            }

                            $doctor_display = tr_trim_name($d['doc_last'] . ' ' . $d['doc_first']);
                            if ($show_npi && !empty($d['doc_npi'])) {
                                $doctor_display .= ' - ' . $d['doc_npi'];
                            }
                            echo '<option value="' . $d['doc_ID'] . '" ' . $selected . ' data-cus_ID="' . $d['cus_ID'] . '" data-low="' . esc_attr($d['cus_individuals']) . '" data-med="' . esc_attr($d['cus_med_risk']) . '" data-high="' . esc_attr($d['cus_high_risk']) .  '" data-cus_df_profile="' . $d['cus_df_profile'] .  '" data-signature="' . $d['doc_signature'] . '">' . $doctor_display . '</option>';
                        }
                        ?>
                    </select>
                <?php
                }
                ?>
                <label>Collector Name</label>
                <?php if (is_array($collectors) && count($collectors) > 0 && $current_role != 'collector') : ?>
                    <select name="col[col_ID]" id="col_id_sel" class="njs_select2">
                        <?php
                        foreach ($collectors as $collector) {
                            $selected = $collector['col_ID'] == $bat['col_ID'] ? 'selected' : '';
                            echo '<option value="' . $collector['col_ID'] . '" ' . $selected . ' data-phone="' . $collector['col_phone'] . '">' . tr_trim_name(@$collector['col_first'] . ' ' . @$collector['col_last']) . '</option>';
                        }
                        $collector['col_phone'] = $collectors[0]['col_phone'];
                        ?>
                    </select>
                <?php else :
                    if (!$collector) {
                        $collector = isset($collectors[0]) ? $collectors[0] : array('col_first' => '', 'col_last' => '', 'col_phone' => '');
                    }
                    $col_name = $collector['col_first'] . ' ' . @$collector['col_last'];
                ?>
                    <input type="text" class="col_auto" name="col[col_name]" value="<?php echo tr_trim_name($col_name) ?>" />
                    <input type="hidden" name="col[col_ID]" value="<?php echo isset($collector['col_ID']) ? $collector['col_ID'] : '' ?>" />
                <?php endif; ?>
                <label>Collector Phone Number</label>
                <input type="text" class="has_phone" name="col[col_phone]" value="<?php echo isset($collector['col_phone']) ? $collector['col_phone'] : '' ?>" <?php if (!empty($collector['col_phone'])) echo 'readonly' ?> />
            </div>
            <div class="col5">
                <label>DIAGNOSTIC CODES</label>

                <div class="dagnostic_code" data-name="bat[dagnostic_codes][]" <?php if (isset($settings['dagnostic_code_required']) && $settings['dagnostic_code_required']) echo 'data-required="true"' ?>>
                    <ul id="list_dagnostic_codes">
                        <?php
                        $bat['dagnostic_codes'] = explode(',', @$bat['dagnostic_codes1']);
                        if (empty($bat['mid'])) {
                            $bat['dagnostic_codes'] = array();
                        }
                        if (is_array(@$bat['dagnostic_codes']) && count($bat['dagnostic_codes']) > 0) {
                            foreach ($bat['dagnostic_codes'] as $vl) {
                                if (empty($vl)) continue;
                        ?>
                                <li><input type="hidden" name="bat[dagnostic_codes][]" value="<?php echo $vl ?>" /> <?php echo $vl ?> <span></span></li>
                        <?php
                            }
                        }
                        ?>
                    </ul>
                    <?php if ($act != 'print' && $act != 'view') : ?>
                        <div class="dagnostic_edit">
                            <?php tr_select_option('dagnostic_code_select', tr_pdd_get_dagnostic_codes(), null) ?>
                            <a class="itip thickbox" href="<?php echo TRCPDD_URL ?>images/ICD10Crosswalk.png?"></a>
                            <input type="text" id="dagnostic_code_text" placeholder="Enter Additional Codes" />
                        </div>
                    <?php endif; ?>
                </div>

            </div>
            <div class="col5">
                <label>Type of Specimen</label>
                <div class=" ilabel label2">
                    <?php
                    $types = tr_pdd_get_type_of_specimens();
                    if (!empty($bat['type_of_specimen']) && !isset($types[$bat['type_of_specimen']])) {
                        $types[$bat['type_of_specimen']] = $bat['type_of_specimen'];
                    }
                    tr_radio('bat[type_of_specimen]', $types, @$bat['type_of_specimen'], array('attr' => 'required'));
                    ?>
                </div>

                <?php
                if (isset($settings['e_phase']) && $settings['e_phase']) {
                    echo '<div class="phase_div" style="margin-top:15px;' . ($bat['show_phase'] ? '' : 'display:none') . '"><label>Treatment</label>';
                    echo '<select name="batmeta[treatment]">';
                    echo '<option value="">Select</option>';
                    foreach (array(1, 2, 3, 4, 5, 6, 13, 28) as $phase) {
                        $selected = $phase == $batmeta['treatment'] ? 'selected' : '';
                        echo '<option value="' . $phase . '" ' . $selected . '>Phase ' . $phase . '</option>';
                    }
                    echo '</select></div>';
                }
                ?>
            </div>
            <div class="col5 small">
                <label>Date of Collection</label>
                <input type="text" data-write="true" class="hasdatetime <?php if (tr_get_option('ci_pdf_data', 'limit_doc')) echo 'limitfuture' ?>" <?php if (tr_current_role_in(array('administrator', 'AdminCIDW', 'administrator_p', 'lab', 'labsecman', 'leadlabsecman', 'lab_manager', 'lab_sec', 'lab_tech'))) echo 'data-write="true"' ?> name="bat[doc]" value="<?php echo empty($bat['doc']) ? 'auto' : $bat['doc'] ?>" <?php if (!empty($bat['doc'])) echo 'readonly' ?> required="" />
                <?php if (!tr_current_role_in(array('lab_sec', 'lab_tech'))) {
                    $temperature_required = tr_get_option('ci_req_settings_tox', 'temperature_required'); ?>
                    <div class="ilabel">
                        Temperature read within 4 minutes in
                        the range of:<br />
                        <label><input type="radio" id="tempe_1" name="bat[temperature]" <?php if ($temperature_required) echo 'required' ?> <?php checked(@$bat['temperature'], '<91') ?> value="<91" /> &lt;91 </label>
                        <label><input type="radio" id="tempe_2" name="bat[temperature]" <?php if ($temperature_required) echo 'required' ?> <?php checked(@$bat['temperature'], '91-99.6') ?> value="91-99.6" /> 91-99.6 </label>
                        <label><input type="radio" id="tempe_3" name="bat[temperature]" <?php if ($temperature_required) echo 'required' ?> <?php checked(@$bat['temperature'], '>99.6') ?> value=">99.6" /> >99.6 </label>

                        <label><input type="radio" id="tempe_no" name="bat[temperature]" <?php if ($temperature_required) echo 'required' ?> <?php checked(@$bat['temperature'], 'no') ?> value="no" /> No</label>
                    </div>
                <?php } ?>
            </div>
        </div>

        <div class="frow">
            <h4>PATIENT INFORMATION</h4>
        </div>

        <div class="frow blabel">
            <div class="col4">
                <label>Social Security Number</label>
                <input type="text" name="pa[pa_ssn]" id="pa_ssn" maxlength="12" value="<?php echo isset($pa['pa_ssn']) ? $pa['pa_ssn']  : '' ?>" <?php if (tr_get_option('ci_pdf_data', 'required_ssn')) echo 'required' ?> <?php if ($act != 'print') echo 'style="width:74%"' ?> />
                <input type="hidden" name="pa[pa_ID]" value="<?php echo isset($pa['pa_ID']) ? $pa['pa_ID'] : '' ?>" />

                <?php if ($act != 'print' && $act != 'view') : ?>
                    <a class="button find_patient samefheight" href="#" data-type="ssn">find</a>
                <?php endif; ?>
            </div>
            <div class="col4">
                <label>Last Name</label>
                <input type="text" class="cfname requesting_patients_last_auto" name="pa[pa_last]" value="<?php echo isset($pa['pa_last']) ? $pa['pa_last'] : '' ?>" required="" />
            </div>
            <div class="col4">
                <label>First Name</label>
                <input type="text" class="cfname" name="pa[pa_first]" value="<?php echo isset($pa['pa_first']) ? $pa['pa_first'] : '' ?>" required="" />
            </div>
            <div class="col4">
                <label>Middle Initial</label>
                <input type="text" class="cfname" name="pa[pa_middle]" value="<?php echo isset($pa['pa_middle']) ? $pa['pa_middle'] : '' ?>" />
            </div>
        </div>

        <div class="frow blabel">
            <div class="col4">
                <label>Medical Record Number</label>
                <input type="text" id="med_number" name="pa[pa_mrn]" value="<?php echo isset($pa['pa_mrn']) ? $pa['pa_mrn'] : '' ?>" maxlength="15" <?php
                                                                                                                                                    if ($act != 'print') echo ' style="width:74%" ';
                                                                                                                                                    if (tr_get_option('ci_pdf_data', 'mrn_required')) echo ' required ';
                                                                                                                                                    ?> />
                <?php if ($act != 'print' && $act != 'view') : ?>
                    <a class="button find_patient samefheight" href="#" data-type="med_number">find</a>
                <?php endif; ?>
            </div>
            <div class="col4">
                <label>Street Address <span class="right dontprint">NH <input type="checkbox" class="nh_address" /> </span> </label>
                <input type="text" name="pa[pa_address]" id="pa_address" value="<?php echo isset($pa['pa_address']) ? $pa['pa_address'] : '' ?>" required="" />
            </div>
            <?php if (tr_get_option('ci_pdf_data', 'enable_patient_room') && !tr_get_option('ci_pdf_data', 'enable_patient_unit_room_bed')) { ?>
                <div class="col12">
                    <label>Room</label>
                    <input type="text" name="pa[pa_room]" value="<?php echo isset($pa['pa_room']) ? $pa['pa_room'] : '' ?>" />
                </div>
            <?php } ?>
            <div class="<?php echo tr_get_option('ci_pdf_data', 'enable_patient_room') && !tr_get_option('ci_pdf_data', 'enable_patient_unit_room_bed') ? 'col6' : 'col4' ?>">
                <label>City</label>
                <input type="text" name="pa[pa_city]" id="pa_city" value="<?php echo isset($pa['pa_city']) ? $pa['pa_city'] : '' ?>" required="" />
            </div>
            <div class="col4">
                <div class="frow blabel">
                    <div class="col2">
                        <label>State</label>
                        <?php tr_select_option('pa[pa_state]', tr_get_states(), isset($pa['pa_state']) ? $pa['pa_state'] : '', array('attr' => 'required')) ?>
                    </div>
                    <div class="col2">
                        <label>Zip</label>
                        <input type="text" name="pa[pa_zip]" id="pa_zip" value="<?php echo isset($pa['pa_zip']) ? $pa['pa_zip'] : '' ?>" required="" />
                    </div>
                </div>
            </div>
        </div>
        <div class="frow">
            <div class="col4 ">
                <label>Gender</label>
                <?php tr_select_option('pa[pa_gender]', trpdd_genders(), isset($pa['pa_gender']) ? $pa['pa_gender'] : '', array('attr' => 'required="" ')) ?>
            </div>
            <div class="col4">
                <label>DOB</label>
                <input type="text" class="hasdate birthday limitfuture" name="pa[pa_dob]" value="<?php echo isset($pa['pa_dob']) ? $pa['pa_dob'] : '' ?>" required="" <?php if (tr_get_option('ci_pdf_data', 'e_hand_editdob')) echo 'data-write="1"' ?> />
            </div>
            <div class="col4">
                <label>Phone Number</label>
                <input type="text" class="has_phone" name="pa[pa_phone]" value="<?php echo isset($pa['pa_phone']) ? $pa['pa_phone'] : '' ?>" required="" />
            </div>
            <div class="col4">
                <label>Email</label>
                <input type="email" name="pa[pa_email]" value="<?php echo isset($pa['pa_email']) ? $pa['pa_email'] : '' ?>" <?php if (tr_get_option('ci_pdf_data', 'pa_email_required')) echo 'required' ?> />
            </div>
        </div>
        <?php

        if (tr_get_option('ci_pdf_data', 'req_patient_location')) {
            echo '<div class="frow">';
            echo '<div class="col4">';
            echo '<label>Location</label>';
            echo '<input type="text" name="pa[pa_location]" value="' . (isset($pa['pa_location']) ? $pa['pa_location'] : '') . '" />';
            echo '</div>';
            echo '</div>';
        }
        ?>

        <?php if (tr_get_option('ci_pdf_data', 'enable_patient_unit_room_bed')) { ?>
            <div class="frow">
                <div class="col4">
                    <label>Unit</label>
                    <input type="text" name="pa[pa_unit]" value="<?php echo isset($pa['pa_unit']) ? $pa['pa_unit'] : '' ?>" />
                </div>
                <div class="col4">
                    <label>Room</label>
                    <input type="text" name="pa[pa_room]" value="<?php echo isset($pa['pa_room']) ? $pa['pa_room'] : '' ?>" />
                </div>
                <div class="col4">
                    <label>Bed</label>
                    <input type="text" name="pa[pa_bed]" value="<?php echo isset($pa['pa_bed']) ? $pa['pa_bed'] : '' ?>" />
                </div>
            </div>
        <?php } ?>

        <div class="frow">
            <div class="col23">
                <h4><label><input type="radio" name="bat[ins_comp]" value="ins" <?php checked('ins', isset($bat['ins_comp']) ? $bat['ins_comp'] : '') ?> /> PRIMARY INSURANCE BILLING <span>(include copy of both sides of insurance card)</span></label></h4>
            </div>
            <div class="col13">
                <h4><label><input type="radio" name="bat[ins_comp]" value="comp" <?php checked('comp', isset($bat['ins_comp']) ? $bat['ins_comp'] : '') ?> /> WORKER'S COMP AND AUTO INFORMATION</label></h4>
            </div>
        </div>

        <div class="frow blabel">
            <div class="col23" id="use_required_ins">

                <div class="frow">
                    <input type="hidden" name="no_insurance" value="" />
                    <label><input type="checkbox" name="no_insurance" value="No Insurance - Cash" <?php checked(isset($bat['insurance']) ? $bat['insurance'] : '', 'No Insurance - Cash') ?> /> No Insurance - Cash</label>
                </div>
                <div class="frow">
                    <div class="col3">
                        <label>Last Name</label>
                        <input type="text" class="cfname has_required" name="ins1[ins_last]" value="<?php echo isset($ins1['ins_last']) ? $ins1['ins_last'] : '' ?>" <?php echo $required_insurance ?> />
                        <input type="hidden" name="ins1[ins_ID]" value="<?php echo isset($ins1['ins_ID']) ? $ins1['ins_ID'] : 0 ?>" />
                    </div>
                    <div class="col3">
                        <label>First Name</label>
                        <input type="text" class="cfname has_required" name="ins1[ins_first]" value="<?php echo isset($ins1['ins_first']) ? $ins1['ins_first'] : '' ?>" <?php echo $required_insurance ?> />
                    </div>
                    <div class="col3">
                        <label>Relation to Patient?</label>
                        <div class="ilabel printsmall">
                            <?php
                            if ($act == 'print') {
                                echo '<span class="text">' . (isset($ins1['ins_relation']) ? $ins1['ins_relation'] : '') . '</span>';
                            } else {
                                tr_radio('ins1[ins_relation]', array(
                                    'Self'   => 'Self',
                                    'Parent' => 'Parent',
                                    'Spouse' => 'Spouse'
                                ), isset($ins1['ins_relation']) ? $ins1['ins_relation'] : '', array('attr' => $required_insurance));
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <div class="frow">
                    <div class="col3">
                        <label>Insurance Company Name</label>
                        <input type="text" class="cfname ins_company_auto" name="ins1[ins_company]" value="<?php echo isset($ins1['ins_company']) ? $ins1['ins_company'] : "" ?>" <?php echo $required_insurance ?> />
                    </div>
                    <div class="col3">
                        <label>Email</label>
                        <input type="email" name="ins1[ins_email]" value="<?php echo isset($ins1['ins_email']) ? $ins1['ins_email'] : '' ?>" />
                    </div>
                    <div class="col3" id="dob_ins1" <?php if (isset($ins1['ins_relation']) && $ins1['ins_relation'] == 'Self') echo 'style="display: none"' ?>>
                        <label><span>Parent</span> DOB</label>
                        <input type="text" class="hasdate birthday limitfuture <?php if ($required_insurance == '') echo 'notrequired' ?>" name="ins1[ins_dob]" value="<?php echo isset($ins1['ins_dob']) ? $ins1['ins_dob'] : '' ?>" />
                    </div>
                </div>
                <?php
                if (tr_get_option('ci_pdf_data', 'insurance_partab')) {
                ?>
                    <div class="ilabel">
                        <input type="hidden" name="ins1[ins_part_A]" value="0" />
                        <label><input type="checkbox" name="ins1[ins_part_A]" value="1" <?php checked(isset($ins1['ins_part_A']) ? $ins1['ins_part_A'] : '') ?> /> Part A</label>
                        &nbsp;&nbsp;
                        <input type="hidden" name="ins1[ins_part_B]" value="0" />
                        <label><input type="checkbox" name="ins1[ins_part_B]" value="1" <?php checked(isset($ins1['ins_part_B']) ? $ins1['ins_part_B'] : '') ?> /> Part B</label>
                    </div>
                <?php
                }
                ?>
                <div class="frow">
                    <div class="col3">
                        <label>Street Address</label>
                        <input type="text" name="ins1[ins_address]" value="<?php echo isset($ins1['ins_address']) ? $ins1['ins_address'] : '' ?>" <?php echo $required_insurance_address ?> />
                    </div>
                    <div class="col3">
                        <label>City</label>
                        <input type="text" name="ins1[ins_city]" value="<?php echo isset($ins1['ins_city']) ? $ins1['ins_city'] : '' ?>" <?php echo $required_insurance_address ?> />
                    </div>
                    <div class="col3">
                        <div class="frow">
                            <div class="col2">
                                <label>State</label>
                                <?php tr_select_option('ins1[ins_state]', tr_get_states(), isset($ins1['ins_state']) ? $ins1['ins_state'] : '', array('attr' => $required_insurance_address)) ?>
                            </div>
                            <div class="col2">
                                <label>Zip</label>
                                <input type="text" name="ins1[ins_zip]" value="<?php echo isset($ins1['ins_zip']) ? $ins1['ins_zip'] : '' ?>" <?php echo $required_insurance_address ?> />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="frow">
                    <div class="col3">
                        <label>Insurance Phone</label>
                        <input type="text" class="has_phone" name="ins1[ins_phone]" value="<?php echo isset($ins1['ins_phone']) ? $ins1['ins_phone'] : '' ?>" />
                    </div>
                    <div class="col3">
                        <label>Member ID</label>
                        <input type="text" name="ins1[ins_member_id]" maxlength="24" value="<?php echo isset($ins1['ins_member_id']) ? $ins1['ins_member_id'] : '' ?>" <?php echo $required_insurance ?> />
                    </div>
                    <div class="col3">
                        <label>Group #</label>
                        <input type="text" name="ins1[ins_group_id]" value="<?php echo isset($ins1['ins_group_id']) ? $ins1['ins_group_id'] : '' ?>" />
                    </div>
                </div>
                <div class="frow">
                    <div class="col3">
                        <label>Pre-authorization</label>
                        <?php tr_yes_no('ins1[ins_authorization]', isset($ins1['ins_authorization']) ? $ins1['ins_authorization'] : '', array('attr' => $required_insurance)) ?>
                    </div>
                    <div class="col3">
                        <label>Authorization #</label>
                        <input type="text" name="ins1[ins_authorization_id]" value="<?php echo isset($ins1['ins_authorization_id']) ? $ins1['ins_authorization_id'] : '' ?>" />
                    </div>
                    <div class="col3">
                        <label>Authorization Date</label>
                        <input type="text" class="hasdate" name="ins1[ins_authorization_date]" value="<?php echo isset($ins1['ins_authorization_date']) ? $ins1['ins_authorization_date'] : '' ?>" />
                    </div>
                </div>

                <h4>SECONDARY INSURANCE BILLING <span>(include copy of both sides of insurance card)</span></h4>
                <div class="frow">
                    <div class="col3">
                        <label>Last Name</label>
                        <input type="text" class="cfname" name="ins2[ins_last]" value="<?php echo isset($ins2['ins_last']) ? $ins2['ins_last'] : '' ?>" />
                        <input type="hidden" name="ins2[ins_ID]" value="<?php echo isset($ins2['ins_ID']) ? $ins2['ins_ID'] : '' ?>" />
                    </div>
                    <div class="col3">
                        <label>First Name</label>
                        <input type="text" class="cfname" name="ins2[ins_first]" value="<?php echo isset($ins2['ins_first']) ? $ins2['ins_first'] : '' ?>" />
                    </div>
                    <div class="col3 ">
                        <label>Relation to Patient?</label>
                        <div class="ilabel printsmall">
                            <?php
                            if ($act == 'print') {
                                echo '<span class="text">' . (isset($ins2['ins_relation']) ? $ins2['ins_relation'] : '') . '</span>';
                            } else {
                                tr_radio('ins2[ins_relation]', array(
                                    'Self'   => 'Self',
                                    'Parent' => 'Parent',
                                    'Spouse' => 'Spouse'
                                ), isset($ins2['ins_relation']) ? $ins2['ins_relation'] : '');
                            }
                            ?>
                        </div>
                    </div>
                    <div class="col3"></div>
                </div>
                <div class="frow">
                    <div class="col3">
                        <label>Insurance Company Name</label>
                        <input type="text" class="cfname ins_company_auto2" name="ins2[ins_company]" value="<?php echo isset($ins2['ins_company']) ? $ins2['ins_company'] : '' ?>" />
                    </div>
                    <div class="col3">
                        <label>Email</label>
                        <input type="email" name="ins2[ins_email]" value="<?php echo isset($ins2['ins_email']) ? $ins2['ins_email'] : '' ?>" />
                    </div>
                    <div class="col3" id="dob_ins2" <?php if (isset($ins2['ins_relation']) && $ins2['ins_relation'] == 'Self') echo 'style="display: none"' ?>>
                        <label><span>Parent</span> DOB</label>
                        <input type="text" class="hasdate birthday limitfuture" name="ins2[ins_dob]" value="<?php echo isset($ins2['ins_dob']) ? $ins2['ins_dob'] : '' ?>" />
                    </div>
                </div>
                <div class="frow">
                    <div class="col3">
                        <label>Insurance Phone</label>
                        <input type="text" class="has_phone" name="ins2[ins_phone]" value="<?php echo isset($ins2['ins_phone']) ? $ins2['ins_phone'] : '' ?>" />
                    </div>
                    <div class="col3">
                        <label>Member ID</label>
                        <input type="text" name="ins2[ins_member_id]" maxlength="24" value="<?php echo isset($ins2['ins_member_id']) ? $ins2['ins_member_id'] : '' ?>" />
                    </div>
                    <div class="col3">
                        <label>Group #</label>
                        <input type="text" name="ins2[ins_group_id]" value="<?php echo isset($ins2['ins_group_id']) ? $ins2['ins_group_id'] : '' ?>" />
                    </div>
                </div>

            </div>
            <div class="col13" id="use_required_com">
                <div class="frow">
                    <div class="col2">
                        <label>Subscriber Last Name</label>
                        <input type="text" class="cfname" name="bat[subscriber_last]" value="<?php echo isset($bat['subscriber_last']) ? $bat['subscriber_last'] : '' ?>" required="" />
                    </div>
                    <div class="col2">
                        <label>Subscriber First Name</label>
                        <input type="text" class="cfname" name="bat[subscriber_first]" value="<?php echo isset($bat['subscriber_first']) ? $bat['subscriber_first']  : '' ?>" required="" />
                    </div>
                </div>
                <div class="frow">
                    <label>Date of Injury</label>
                    <input type="text" class="hasdate" name="bat[date_of_injury]" value="<?php echo isset($bat['date_of_injury']) ? $bat['date_of_injury'] : '' ?>" required="" />
                </div>
                <div class="frow">
                    <div class="col23">
                        <label>Address of Claim</label>
                        <input type="text" name="bat[address_of_claim]" value="<?php echo isset($bat['address_of_claim']) ? $bat['address_of_claim'] : '' ?>" />
                    </div>
                    <div class="col13">
                        <label>Zip Code</label>
                        <input type="text" name="bat[subscriber_zip]" value="<?php echo isset($bat['subscriber_zip']) ? $bat['subscriber_zip'] : '' ?>" />
                    </div>
                </div>
                <div class="frow">
                    <div class="col23">
                        <label>Insurance</label>
                        <input type="text" class="cfname" name="bat[subscriber_insurance]" value="<?php echo isset($bat['subscriber_insurance']) ? $bat['subscriber_insurance'] : '' ?>" required="" />
                    </div>
                    <div class="col13">
                        <label>WC#</label>
                        <input type="text" name="bat[subscriber_wc]" value="<?php echo isset($bat['subscriber_wc']) ? $bat['subscriber_wc'] : '' ?>" />
                    </div>
                </div>
                <div class="frow">
                    <div class="col23">
                        <label>Attorney</label>
                        <input type="text" name="bat[attorney_name]" value="<?php echo isset($bat['attorney_name']) ? $bat['attorney_name'] : '' ?>" />
                    </div>
                    <div class="col13">
                        <label>Attorney Phone</label>
                        <input type="text" class="has_phone" name="bat[attorney_phone]" value="<?php echo isset($bat['attorney_phone']) ? $bat['attorney_phone'] : '' ?>" />
                    </div>
                </div>
                <div class="frow">
                    <label>Adjuster Name</label>
                    <input type="text" class="cfname" name="bat[adjuster_name]" value="<?php echo isset($bat['adjuster_name']) ? $bat['adjuster_name'] : '' ?>" />
                </div>
                <div class="frow">
                    <label>Adjuster Phone Number</label>
                    <input type="text" class="has_phone" name="bat[adjuster_phone]" value="<?php echo isset($bat['adjuster_phone']) ? $bat['adjuster_phone'] : '' ?>" />
                </div>
                <div class="frow">
                    <label>Claim Number</label>
                    <input type="text" name="bat[claim_number]" value="<?php echo isset($bat['claim_number']) ? $bat['claim_number'] : '' ?>" required="" />
                </div>
                <div class="frow">
                    <label>Claim under litigation?</label>
                    <?php tr_yes_no('bat[claim_under_litigation]', isset($bat['claim_under_litigation']) ? $bat['claim_under_litigation'] : ''); ?>
                </div>
            </div>
        </div>
        <?php if (!isset($billing_page) || !$billing_page) { ?>
            <div class="frow frowmedtests">
                <div class="col2 blabel col2medications">
                    <?php
                    if (count($current_medications) > 0) {
                        $numcolmedi = 4;
                    ?>
                        <div class="medication_area frow insidebreak ">
                            <h4>PATIENT CURRENT MEDICATIONS</h4>
                            <span class="small">I order the lab to test for/confirm the prescribed medications listed below</span>
                            <input type="hidden" name="has_edit_medication" value="1" />
                            <div class="frow blabel">
                                <?php
                                $fourn = round(count($current_medications) / $numcolmedi) + 1;
                                foreach ($current_medications as $i => $m) :
                                    if ($i % $fourn == 0) {
                                        if ($i > 1) echo '</div>';
                                        echo '<div class="col' . $numcolmedi . '">';
                                    }
                                    $k = $m;
                                    if ($k == 'Fioricet/Fiorinal') $k = 'Fioricet';
                                    else if ($k == 'Lorcet/Lortab') $k = 'Lortab';
                                ?>
                                    <label>
                                        <input type="checkbox" name="bat[current_medication][]" value="<?php echo $k ?>" <?php checked(in_array($k, isset($bat['current_medication']) ? $bat['current_medication'] : array())) ?> />
                                        <?php echo ucfirst(strtolower($m)) ?>
                                    </label>
                                <?php endforeach;
                                echo '</div>' ?>
                            </div>

                            <div class="frow printnopaddingtop" style="padding-top:10px">
                                <input type="hidden" name="current_medication_none" value="" />
                                <input type="checkbox" name="current_medication_none" value="1" <?php checked(in_array('None Listed', isset($bat['current_medication']) ? $bat['current_medication'] : array())) ?> /> No Prescribed Medication
                            </div>

                            <div class="frow printnopaddingtop" style="padding-top:20px" id="more_current_medication_div">
                                <?php
                                if (!isset($bat['current_medication_other']) || !is_array($bat['current_medication_other'])) $bat['current_medication_other'] = array();
                                $count = count($bat['current_medication_other']) >= 3 ? count($bat['current_medication_other']) : 3;
                                for ($i = 0; $i < $count; $i++) : ?>
                                    <div class="col3 <?php if ($i == 0) echo 'default' ?>">
                                        <label>Other:</label>
                                        <input type="text" name="bat[current_medication_other][]" value="<?php echo isset($bat['current_medication_other'][$i]) ? $bat['current_medication_other'][$i] : '' ?>" />
                                    </div>
                                <?php endfor; ?>
                            </div>
                            <?php if ($act != 'print') { ?>
                                <a class="add_more_current_medication button">+</a>
                            <?php } ?>
                            <div class="frow">
                                Not all drugs LISTED under "other" will be available.
                            </div>
                        </div>
                    <?php
                    }
                    if (!isset($settings['hide_poc']) || !$settings['hide_poc']) {
                        if ($act == 'print') {
                            //  echo '</div><div class="col2 blabel">';
                        }
                    ?>
                        <div class="frow">
                            <div class="frow">
                                <h4>POC RESULTS</h4>
                            </div>
                            <div class="frow">
                                <div class="col2">
                                    <label>POC PERFORMED
                                        <input type="radio" name="bat[poc_perform]" value="1" <?php checked('1', isset($bat['poc_perform']) ? $bat['poc_perform'] : '') ?> required="" /> </label>
                                </div>
                                <div class="col2">
                                    <label>POC NOT PERFORMED
                                        <input type="radio" name="bat[poc_perform]" value="0" <?php checked('0', isset($bat['poc_perform']) ? $bat['poc_perform'] : '') ?> required="" /> </label>
                                </div>
                            </div>

                            <div class=" tbl_2box">
                                <table style="width:100%">
                                    <tr>
                                        <td style="width:50%">
                                            <table style="width:99%">
                                                <tr>
                                                    <th>Medication or Drug</th>
                                                    <th>+</th>
                                                    <th>/</th>
                                                    <th>-</th>
                                                </tr>
                                                <?php
                                                $from = round(count($Medications) / 2);
                                                foreach ($Medications as $i => $med) :
                                                    if ($i >= $from) break; ?>
                                                    <tr>
                                                        <td><?php echo $med ?></td>
                                                        <td><input type="radio" name="bat[medication][<?php echo $med ?>]" value="1" <?php checked('1', isset($bat['medication'][$med]) ? $bat['medication'][$med] : '') ?> /> </td>
                                                        <td></td>
                                                        <td><input type="radio" name="bat[medication][<?php echo $med ?>]" value="0" <?php checked('0', isset($bat['medication'][$med]) ? $bat['medication'][$med] : '') ?> /> </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </table>
                                        </td>
                                        <td>
                                            <table style="width:99%">
                                                <tr>
                                                    <th>Medication or Drug</th>
                                                    <th>+</th>
                                                    <th>/</th>
                                                    <th>-</th>
                                                </tr>
                                                <?php foreach ($Medications as $i => $med) :
                                                    if ($i < $from) continue; ?>
                                                    <tr>
                                                        <td><?php echo $med ?></td>
                                                        <td><input type="radio" name="bat[medication][<?php echo $med ?>]" value="1" <?php checked('1', isset($bat['medication'][$med]) ? $bat['medication'][$med] : '') ?> /> </td>
                                                        <td></td>
                                                        <td><input type="radio" name="bat[medication][<?php echo $med ?>]" value="0" <?php checked('0', isset($bat['medication'][$med]) ? $bat['medication'][$med] : '') ?> /> </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    <?php } ?>
                </div>

                <?php
                $lcms_tests = tr_pdd_get_profiles_tests_requesteds('tox', true, false, isset($bat['doc']) ? $bat['doc'] : '');
                ?>
                <div class="col2 col2tests">
                    <?php if (count($lcms_tests) > 0) { ?>
                        <div class="frow">
                            <h4>TESTS ORDERED/CUSTOM PROFILE OPTIONS</h4>
                        </div>
                        <div class="frow">
                            <?php tr_select_option('bat[custom_profile]', tr_pdd_get_custom_profiles(), isset($bat['custom_profile']) ? $bat['custom_profile'] : '', array('attr' => 'required' . ($is_hold ? 'disabled' : ''))) ?>
                        </div>
                    <?php } ?>
                    <div class="frow">
                        <h4>TESTING REQUESTED</h4>
                    </div>

                    <?php
                    $screenings = tr_pdd_get_result_validity(1, isset($bat['batch_created']) ? $bat['batch_created'] : '', $id, $saved_id > 0);

                    if (count($screenings) > 0) {
                        $use_screening = false;
                        foreach ($screenings as $arr) {
                            if (isset($arr['checked']) && $arr['checked']) {
                                $use_screening = true;
                            }
                        }
                        if ($mid > 0) {
                            if ($use_screening) {
                                $wpdb->update('ci_batches', array('has_screening' => 1), array('mid' => $mid));
                            } else if ($bat['has_screening']) {
                                $wpdb->update('ci_batches', array('has_screening' => 0), array('mid' => $mid));
                            }
                        }

                        $sgroups = $wpdb->get_results("select * from ci_test_groups where tg_type='scr' ");
                        if (count($sgroups) > 0) {
                            echo '<div class="frow">';
                            echo '<select name="batmeta[screen_group_id]">';
                            echo '<option value="">Select Screening Panel</option>';
                            foreach ($sgroups as $sgroup) {
                                $selected = $sgroup->tg_ID == $batmeta['screen_group_id'] ? 'selected' : '';
                                echo '<option value="' . $sgroup->tg_ID . '" ' . $selected . ' data-items="' . $sgroup->tg_comments . '">' . $sgroup->tg_name . '</option>';
                            }
                            echo '</select>';
                            echo '</div>';
                        }

                        if ($act == 'print' && $bat['only_screening']) {
                            $lcms_tests = array();
                        }
                    ?>
                        <div class="frow">
                            <label class="bold inline"><input name="screening" value="1" type="radio" required <?php checked($use_screening) ?> /> Drug Screening </label> &nbsp;&nbsp;&nbsp;&nbsp;
                            <label class="bold inline"><input name="screening" value="0" type="radio" required <?php checked(!$use_screening && (isset($mid) && $mid > 0)) ?> /> No Screening </label>

                            <input type="hidden" name="bat[only_screening]" value="0" />
                            <?php if (!tr_get_option('ci_req_settings_tox', 'hide_only_screening')) { ?>
                                <span style="padding-left: 20px;">
                                    <label class="bold"><input name="bat[only_screening]" value="1" type="checkbox" <?php checked(isset($bat['only_screening']) ? $bat['only_screening'] : '') ?> /> Only Screening </label>
                                </span>
                            <?php } ?>

                            <div class="frow" id="screening_div" style="margin-top:10px">
                                <?php
                                foreach ($screenings as $arr) {
                                    $test_class = '';
                                    $sc_class = '';
                                    $key = $arr['u_ID'];
                                    $arr['conc'] = preg_replace("/[^0-9-.,]/", '', isset($arr['conc']) ? $arr['conc'] : '');

                                    if (!empty($arr['conc']) && ($arr['conc'] < $arr['u_low'] || ($arr['conc'] > $arr['u_high'] && $arr['u_high'] > 0))) {
                                        $arr['outcome'] = $arr['u_neg_pos'] ? 'POS' : 'ABNORMAL';
                                    } else {
                                        $arr['outcome'] = $arr['u_neg_pos'] ? 'NEG' : 'NORMAL';
                                    }

                                    if ($arr['u_show_for_ref_lab_ID'] > 0) {
                                        $test_class .= ' by_ref_lab ref_lab_id_' . $arr['u_show_for_ref_lab_ID'] . ' ';
                                        if ($bat['ref_lab_ID'] == $arr['u_show_for_ref_lab_ID']) {
                                            $test_class .= ' cactive ';
                                        } else {
                                            $arr['is_hide'] = true;
                                        }
                                    }

                                    if ($arr['u_notshow_for_ref_lab_ID'] > 0) {
                                        $test_class .= ' not_by_ref_lab not_ref_lab_id_' . $arr['u_notshow_for_ref_lab_ID'] . ' ';
                                        if ($bat['ref_lab_ID'] == $arr['u_notshow_for_ref_lab_ID']) {
                                            $test_class .= ' notcactive ';
                                            $arr['is_hide'] = true;
                                        }
                                    }

                                    if ($arr['u_not_auto_checked']) {
                                        $sc_class .= 'notautocheck';
                                    }
                                ?>
                                    <input type="hidden" name="data_specimen_screening[<?php echo $key ?>][checked]" value="0" />

                                    <div class="col3 <?php echo $test_class ?>">
                                        <label><input type="checkbox" class="screening_cb screening_<?php echo $key ?> <?php echo $sc_class ?>" name="data_specimen_screening[<?php echo $key ?>][checked]" value="1" <?php checked(isset($arr['checked']) ? $arr['checked'] : '') ?> data-u_reflex="<?php echo isset($arr['u_reflex']) ? $arr['u_reflex'] : '' ?>" data-outcome="<?php echo $arr['outcome'] == 'ABNORMAL' ? 'POS' : $arr['outcome'] ?>" data-u_reflex_tests="<?php echo $arr['u_reflex_tests'] ?>" data-u_reflex_clinics="<?php echo $arr['u_reflex_clinics'] ?>" data-u_reflex_pos_not_medication="<?php echo isset($arr['u_reflex_pos_not_medication']) ? $arr['u_reflex_pos_not_medication'] : '' ?>" data-related_medications="<?php echo esc_attr(trim((isset($arr['u_related_medications']) ? $arr['u_related_medications'] : '') . ',' . $arr['u_name'] . ',' . $arr['u_abreviation'], ',')) ?>" />
                                            <?php echo htmlspecialchars($arr['u_name']) ?></label>
                                    </div>
                                <?php
                                }
                                ?>
                            </div>

                        </div>
                    <?php
                    }


                    if (count($lcms_tests) > 0) {
                    ?>
                        <div class="frow ">
                            <label class="bold">LCMS Confirmation</label>
                            <div id="default_requests" class="frow blabel panel_requests masonry" style="font-size:12px;column-count:<?php echo isset($settings['panel_column']) && $settings['panel_column'] >= 2 ? $settings['panel_column'] : 2 ?>">
                                <input type="hidden" name="has_panel_requests" value="1" />
                                <?php
                                $tl = 0;
                                foreach ($lcms_tests as $group_id => $vl) {
                                    $group = tr_pdd_get_panel_groups($group_id);
                                    $group_class = '';
                                    if (empty($group['bgcolor'])) {
                                        $group['bgcolor'] = '#dddddd';
                                    }
                                    $group['color'] = trpdd_getContrastColor($group['bgcolor']);
                                    $group['spec_bgcolor'] = strlen($group['bgcolor']) == 7 ? $group['bgcolor'] . '88' : $group['bgcolor'];
                                    $group['box_bgcolor'] = strlen($group['bgcolor']) == 7 ? $group['bgcolor'] . '66' : $group['bgcolor'];
                                    if ($act == 'print') {
                                        $group['box_bgcolor'] = '#f8f8f8';
                                        $group['spec_bgcolor'] = '#eeeeee';
                                    }


                                    $group['is_hide'] = false;

                                    if (!empty($group['cli_IDs'])) {
                                        $group_class .= ' by_clinic ';
                                        foreach (explode(',', $group['cli_IDs']) as $pcid) {
                                            $group_class .= '  cli_id_' . $pcid . ' ';

                                            if ($bat['cli_ID'] == $pcid) {
                                                $group_class .= ' cactive ';
                                                $group['is_hide'] = 0;
                                            } else if ($group['is_hide'] === false) {
                                                $group['is_hide'] = true;
                                            }
                                        }
                                    }

                                    if (!empty($group['show_for_ref_lab_ID'])) {
                                        $group_class .= ' by_ref_lab ';
                                        foreach (explode(',', $group['show_for_ref_lab_ID']) as $rflid) {
                                            $group_class .= '  ref_lab_id_' . $rflid . ' ';

                                            if ($bat['ref_lab_ID'] == $rflid) {
                                                $group_class .= ' cactive ';
                                                $group['is_hide'] = 0;
                                            } else if ($group['is_hide'] === false) {
                                                $group['is_hide'] = true;
                                            }
                                        }
                                    }

                                    if (!empty($group['notshow_for_ref_lab_ID'])) {
                                        $group_class .= ' not_by_ref_lab ';
                                        foreach (explode(',', $group['notshow_for_ref_lab_ID']) as $rflid) {
                                            $group_class .= '  not_ref_lab_id_' . $rflid . ' ';

                                            if ($bat['ref_lab_ID'] == $rflid) {
                                                $group_class .= ' notcactive ';
                                                if ($group['is_hide'] === false) {
                                                    $group['is_hide'] = true;
                                                }
                                            }
                                        }
                                    }

                                    if (!empty($group['not_cli_IDs'])) {
                                        $group_class .= ' not_by_clinic ';
                                        foreach (explode(',', $group['not_cli_IDs']) as $pcid) {
                                            $group_class .= '  not_cli_id_' . $pcid . ' ';

                                            if ($bat['cli_ID'] == $pcid) {
                                                $group_class .= ' notcactive ';
                                                if ($group['is_hide'] === false) {
                                                    $group['is_hide'] = true;
                                                }
                                            }
                                        }
                                    }
                                ?>
                                    <div class="groupparent item <?php echo $group_class ?> " style="background:<?php echo $group['box_bgcolor'] ?>">
                                        <label class="block bold group_label" style="background:<?php echo $group['bgcolor'] ?>;color: <?php echo $group['color'] ?>">
                                            <?php echo $group['label'] ?>
                                        </label>
                                        <?php
                                        if (isset($group['other_options']['desc']) && !empty($group['other_options']['desc'])) {
                                            echo '<div class="panel_desc">' . $group['other_options']['desc'] . '</div>';
                                        }
                                        ?>
                                        <div class="frow box_tests">
                                            <?php

                                            foreach ($vl as $k => $r) {
                                                $test_class = '';
                                                $txt = $r->te_name;
                                                $key = strtoupper($r->key);
                                                $checked = @in_array($key, $bat['panel_tests_requested']);
                                                if (strpos($key, ',') > 0 && !$checked) {
                                                    $keys = explode(',', $key);
                                                    foreach ($keys as $kc) {
                                                        $checked = @in_array(strtoupper($kc), $bat['panel_tests_requested']);
                                                        if ($checked) {
                                                            break;
                                                        }
                                                    }
                                                }

                                                if ($r->te_ref_lab_ID > 0) {
                                                    $test_class .= ' by_ref_lab ref_lab_id_' . $r->te_ref_lab_ID . ' ';
                                                    if ($bat['ref_lab_ID'] == $r->te_ref_lab_ID) {
                                                        $test_class .= ' cactive ';
                                                    } else {
                                                        $checked = false;
                                                    }
                                                }

                                                if ($r->te_not_ref_lab_ID > 0) {
                                                    $test_class .= ' not_by_ref_lab not_ref_lab_id_' . $r->te_not_ref_lab_ID . ' ';
                                                    if ($bat['ref_lab_ID'] == $r->te_not_ref_lab_ID) {
                                                        $test_class .= ' notcactive ';
                                                        $checked = false;
                                                    }
                                                }

                                                if ($group['is_hide']) {
                                                    $checked = false;
                                                }

                                                if ($is_hold) {
                                                    $r->class .= ' readonly ';
                                                }
                                            ?>

                                                <label class="testlabel c lcb <?php echo $test_class ?>">
                                                    <input type="checkbox" class="<?php echo $r->class ?>" name="bat[panel_tests_requested][]" value="<?php echo $key ?>" <?php checked($checked) ?> />
                                                    <?php echo $txt ?></label>

                                            <?php
                                            }

                                            ?>
                                        </div>
                                    </div>
                                <?php
                                }
                                ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>

        <?php } ?>



        <?php include('box_signature.php'); ?>

        <div class="frow">
            <h4>INSERT INSURANCE CARD AND PHOTO ID PICTURES</h4>
        </div>
        <?php if (!isset($billing_page) || !$billing_page) { ?>
            <div class="frow">
                By clicking the icon below, you will be prompted to insert a photo of both the patient current insurance card and photo ID. The photo
                can be obtained directly from the computer or a scanner.
            </div>
        <?php } ?>
        <div class="frow blabel">
            <?php
            $col_num = ($action_form != 'ci_save_form_requisition') ? 'col2' : 'col4';
            ?>
            <div class="<?php echo $col_num ?>">
                <?php
                $rq1        = false;
                $card_link  = '';
                if (!empty($bat['ins_card_front']) && file_exists($card_dir['path'] . '/' . $bat['ins_card_front']) && filesize($card_dir['path'] . '/' . $bat['ins_card_front']) > 0) {
                    $imageurl = $card_dir['url'] . '/' . $bat['ins_card_front'] . '?r=' . time();
                    if (stripos($bat['ins_card_front'], '.pdf') > 0) {
                        $imageurl = '?tr_control=ci_pdf_image&action=view_card_image&mid=' . $bat['mid'] . '&btype=tox&field=ins_card_front&saved_id=' . $saved_id;
                    }
                    $card_link = '<a href="' . $imageurl . '" target=_blank><img src="' . $imageurl . '" style="width:100%"></a>';
                }
                if (!empty($action_form) && (!isset($billing_page) || !$billing_page)) {
                ?>
                    <div class="card_file">
                        <a class="button selectfile <?php if ($rq1) echo 'required' ?>">Choose Ins Card Front File</a>
                        <span class="block"><?php echo $card_link; ?></span>
                        <input type="file" <?php if ($rq1) echo 'data-required="1" required ' ?> name="ins_card_front" accept="image/*,application/pdf" />
                    </div>
                <?php

                } else {
                    echo '<label>Ins Card Front</label>';
                    echo $card_link;
                }
                ?>
                <input type="hidden" name="img_ins_card_front" value="<?php echo isset($bat['ins_card_front']) ? $bat['ins_card_front'] : '' ?>" />
            </div>
            <div class="<?php echo $col_num ?>">
                <?php
                $card_link  = '';
                if (!empty($bat['ins_card_back']) && file_exists($card_dir['path'] . '/' . $bat['ins_card_back']) && filesize($card_dir['path'] . '/' . $bat['ins_card_back']) > 0) {
                    $imageurl = $card_dir['url'] . '/' . $bat['ins_card_back'] . '?r=' . time();
                    if (stripos($bat['ins_card_back'], '.pdf') > 0) {
                        $imageurl = '?tr_control=ci_pdf_image&action=view_card_image&mid=' . $bat['mid'] . '&btype=tox&field=ins_card_back&saved_id=' . $saved_id;
                    }
                    $card_link = '<a href="' . $imageurl . '" target=_blank><img src="' . $imageurl . '" style="width:100%"></a>';
                }
                if (!empty($action_form)  && (!isset($billing_page) || !$billing_page)) {
                ?>
                    <div class="card_file">
                        <a class="button selectfile" href="#">Choose Ins Card Back File</a>
                        <span><?php echo $card_link ?></span>
                        <input type="file" name="ins_card_back" accept="image/*,application/pdf" />
                    </div>
                <?php
                } else {
                    echo '<label>Ins Card Back</label>';
                    echo $card_link;
                }
                ?>
                <input type="hidden" name="img_ins_card_back" value="<?php echo isset($bat['ins_card_back']) ? $bat['ins_card_back'] : '' ?>" />
            </div>

            <?php
            if ($col_num == 'col2') {
                echo '</div><div class="frow">';
            }
            ?>

            <div class="<?php echo $col_num ?>">
                <?php
                $card_link  = '';
                if (!empty($bat['patient_id_front']) && file_exists($card_dir['path'] . '/' . $bat['patient_id_front']) && filesize($card_dir['path'] . '/' . $bat['patient_id_front']) > 0) {
                    $imageurl = $card_dir['url'] . '/' . $bat['patient_id_front'] . '?r=' . time();
                    if (stripos($bat['patient_id_front'], '.pdf') > 0) {
                        $imageurl = '?tr_control=ci_pdf_image&action=view_card_image&mid=' . $bat['mid'] . '&btype=tox&field=patient_id_front&saved_id=' . $saved_id;
                    }
                    $card_link = '<a href="' . $imageurl . '" target=_blank><img src="' . $imageurl . '" style="width:100%"></a>';
                }
                if (!empty($action_form) && (!isset($billing_page) || !$billing_page)) {
                ?>
                    <div class="card_file">
                        <a class="button selectfile" href="#">Choose Patient ID Front File</a>
                        <span><?php echo $card_link ?></span>
                        <input type="file" name="patient_id_front" />
                    </div>
                <?php
                } else {
                    echo '<label>Patient ID Front</label>';
                    echo $card_link;
                }
                ?>
                <input type="hidden" name="img_patient_id_front" value="<?php echo isset($bat['patient_id_front']) ? $bat['patient_id_front'] : '' ?>" />
            </div>
            <div class="<?php echo $col_num ?>">
                <?php
                $card_link  = '';
                if (!empty($bat['patient_id_back']) && file_exists($card_dir['path'] . '/' . $bat['patient_id_back']) && filesize($card_dir['path'] . '/' . $bat['patient_id_back']) > 0) {
                    $imageurl = $card_dir['url'] . '/' . $bat['patient_id_back'] . '?r=' . time();
                    if (stripos($bat['patient_id_back'], '.pdf') > 0) {
                        $imageurl = '?tr_control=ci_pdf_image&action=view_card_image&mid=' . $bat['mid'] . '&btype=tox&field=patient_id_back&saved_id=' . $saved_id;
                    }
                    $card_link = '<a href="' . $imageurl . '" target=_blank><img src="' . $imageurl . '" style="width:100%"></a>';
                }
                if (!empty($action_form) && (!isset($billing_page) || !$billing_page)) {
                ?>
                    <div class="card_file">
                        <a class="button selectfile" href="#">Choose Patient ID Back File</a>
                        <span><?php echo $card_link ?></span>
                        <input type="file" name="patient_id_back" />
                    </div>
                <?php
                } else {
                    echo '<label>Patient ID Back</label>';
                    echo $card_link;
                }
                ?>
                <input type="hidden" name="img_patient_id_back" value="<?php echo isset($bat['patient_id_back']) ? $bat['patient_id_back'] : '' ?>" />
            </div>
        </div>

        <div class="frow">
            <h4><?php echo tr_get_option('ci_pdf_data', 'cht_physicalnotes') ? 'Chief Complaint' : 'Notes' ?></h4>
        </div>
        <div class="frow notesarea">
            <textarea name="bat[notes]" <?php if ($act != 'print') echo 'placeholder="Please enter any ' . (tr_get_option('ci_pdf_data', 'cht_physicalnotes') ? 'Chief Complaint' : 'Physician Notes') . '"' ?>><?php if (isset($bat['notes'])) echo $bat['notes'] ?></textarea>
        </div>

        <div class="frow">
            <h4>Internal Notes</h4>
        </div>
        <div class="frow notesarea">
            <textarea name="bat[internal_notes]" <?php if ($act != 'print') echo 'placeholder="Please enter any Internal Notes"' ?>><?php if (isset($bat['internal_notes'])) echo $bat['internal_notes'] ?></textarea>
        </div>

        <?php if ((isset($bat['batch_short']) && $bat['batch_short'] == 1) || (isset($billing_page) && $billing_page)) : ?>
            <div class="frow notesarea">
                <textarea name="bat[billing_notes]" maxlength="200" <?php if ($act != 'print') echo 'placeholder="Please enter any Billing Notes"' ?>><?php if (isset($bat['billing_notes'])) echo $bat['billing_notes'] ?></textarea>
            </div>
        <?php endif; ?>

        <?php if ((!isset($billing_page) || !$billing_page) && !tr_current_role_in(array('office_manager', 'doctor', 'collector'))) { ?>
            <div class="frow">
                <h4>NOTES (visible on reports)</h4>
            </div>
            <div class="frow notesarea">
                <textarea name="bat[notes_report]"><?php if (isset($bat['notes_report'])) echo @$bat['notes_report'] ?></textarea>
            </div>
        <?php }

        ?>


        <?php if (!empty($action_form) && $act != 'print') : ?>


            <?php if (tr_current_role_in(array('labsecman', 'leadlabsecman', 'lab_sec', 'lab_tech', 'labmanager', 'administrator', 'administrator_p', 'AdminCIDW')) && (!isset($billing_page) || !$billing_page)) { ?>
                <div class="frow">
                    <div class="col2">
                        <label>Status</label>
                        <?php
                        $status_options = tr_pdd_get_batch_statuses_on_hold(tr_pdd_get_batch_statuses(false, false, false, 'tox'), $bat['batch_status'], $bat['mid'], $table_batch, 'tox');
                        $disabled_keys = tr_pdd_get_batch_statuses_disabled_keys('tox', 'show_in_req_form', $bat['batch_status']);
                        tr_select_option('bat[batch_status]', $status_options, @$bat['batch_status'], ['disabled_options' => $disabled_keys]);
                        ?>
                    </div>
                    <div class="col3">
                        <label>Photo</label><br>
                        <input type="file" name="issue_photo" />
                    </div>
                </div>
            <?php } ?>


            <?php if ($act != 'print' && (!isset($billing_page) || !$billing_page) && (!tr_current_role_in($rolecantuploadrs) || get_user_meta(get_current_user_id(), '_upload_results', true))) { ?>
                <div class="frow uploadrsbox">
                    <h4>Upload</h4>
                </div>
                <div class="frow uploadrsbox">
                    <div class="col3">
                        <div class="req_result_pdf">
                            <a class="button addfile">Choose Result PDF</a>
                            <input type="hidden" class="result_value" name="bat[result_pdf]" value="<?php echo isset($bat['result_pdf']) ? $bat['result_pdf'] : '' ?>" />
                            <ul class="listresultfiles">
                                <?php
                                if (!empty($bat['result_pdf'])) {
                                    $rs_files = explode(',', $bat['result_pdf']);
                                    foreach ($rs_files as $f) {
                                        $result_dir = tr_get_result_upload_dir();
                                        $result_pdf = $result_dir['url'] . '/' . $f . '?t=' . time();
                                ?>
                                        <li class="result_pdf_view" data-f="<?php echo $f ?>">
                                            <a href="<?php echo $result_pdf ?>" target="_blank"><?php echo $f ?></a> -
                                            <a class=" remove_result_pdf" href="#">Remove</a>
                                        </li>
                                <?php
                                    }
                                }
                                ?>
                            </ul>
                        </div>
                    </div>

                    <?php if (!tr_current_role_in(array('office_manager', 'doctor', 'collector'))) { ?>
                        <div class="col3">
                            <div class="req_result_pdf">
                                <?php if ($act != 'view') { ?>
                                    <a class="button addfile" data-fname="original_reqs" href="#">Choose Paper Req</a>
                                <?php } ?>

                                <input type="hidden" class="result_value" name="bat[original_req]" value="<?php echo isset($bat['original_req']) ? $bat['original_req'] : '' ?>" />
                                <ul class="listresultfiles">
                                    <?php
                                    if (!empty($bat['original_req'])) {
                                        $rs_files = explode(',', $bat['original_req']);
                                        foreach ($rs_files as $f) {

                                            $result_dir = tr_get_result_upload_dir();
                                            $result_pdf = $result_dir['url'] . '/' . $f;
                                    ?>
                                            <li class="result_pdf_view" data-f="<?php echo $f ?>">
                                                <a href="<?php echo $result_pdf ?>" target="_blank"><?php echo $f ?></a> -
                                                <a class=" remove_result_pdf" href="#">Remove</a>
                                            </li>
                                    <?php
                                        }
                                    }
                                    ?>
                                </ul>
                            </div>
                        </div>
                    <?php } ?>

                </div>
            <?php } else if ($billing_page) {
            ?>
                <div class="frow">
                    <h4>Upload</h4>
                </div>
                <div class="frow">
                    <div class="col3">
                        <div class="req_result_pdf">
                            <?php if ($act != 'view') { ?>
                                <a class="button addfile" data-fname="original_reqs" href="#">Choose Paper Req</a>
                            <?php } ?>

                            <input type="hidden" class="result_value" name="bat[original_req]" value="<?php echo isset($bat['original_req']) ? $bat['original_req'] : '' ?>" />
                            <ul class="listresultfiles">
                                <?php
                                if (!empty($bat['original_req'])) {
                                    $rs_files = explode(',', $bat['original_req']);
                                    foreach ($rs_files as $f) {

                                        $result_dir = tr_get_result_upload_dir();
                                        $result_pdf = $result_dir['url'] . '/' . $f;
                                ?>
                                        <li class="result_pdf_view" data-f="<?php echo $f ?>">
                                            <a href="<?php echo $result_pdf ?>" target="_blank"><?php echo $f ?></a>
                                        </li>
                                <?php
                                    }
                                }
                                ?>
                            </ul>
                        </div>
                    </div>

                </div>
            <?php
            } ?>

            <div class="frow">
                <h4>&nbsp;</h4>
            </div>
            <div class="frow row" style="margin-bottom:15px;">

                <div class=" foutrow ilabel">
                    <?php if ($act != 'print' && $act != 'view' && (!isset($billing_page) || !$billing_page)) { ?>
                        <span id="show_assen">
                            <label>Accession Number</label>
                            <input type="text" id="number_assen" name="bat[assen]" value="<?php echo isset($bat['assen']) ? $bat['assen'] : '' ?>" <?php if ($assen_readonly) echo 'readonly' ?> required />
                            <label>Confirm</label>
                            <input type="text" id="confirm_assen" value="<?php echo isset($bat['assen']) ? $bat['assen'] : '' ?>" required />
                            <span class="confirm_status dashicons dashicons-yes"></span>
                            <a class="button print_assen_btn" <?php echo isset($print_times) && $print_times > 0 ? 'data-print_times="' . $print_times . '"' : '' ?> style="<?php if (empty($bat['assen']) || $saved_id > 0) echo 'display:none' ?>" href="#">Print Barcode</a>
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

            <?php
            if (isset($billing_page) && $billing_page) {
                $where_exist = " assen = '{$bat['assen']}' ";
                if (!empty($bat['claim_id'])) {
                    $where_exist .= " or claim_no = '{$bat['claim_id']}' ";
                }

                $bb = $wpdb->get_row("select * from ci_batch_billings where {$where_exist} ", ARRAY_A);
                if (empty($bb['claim_date'])) {
                    $bb['claim_date'] = date('Y-m-d', strtotime($bat['doc']));
                }
            ?>
                <div class="frow">
                    <div class="col3">
                        <label>Claim ID</label>
                        <input type="text" name="bb[claim_no]" value="<?php echo !empty($bb['claim_no']) ? $bb['claim_no'] : $bat['claim_id'] ?>" />
                    </div>
                    <div class="col3">
                        <label>Claim Date</label>
                        <input type="text" class="hasdate today" data-write="1" name="bb[claim_date]" value="<?php echo $bb['claim_date'] ?>" />
                    </div>

                </div>
                <div class="frow">
                    <div class="col3">
                        <label>Status</label>
                        <?php
                        tr_select_option('bb[bill_status]', $bill_statuses, @$bb['bill_status']) ?>
                    </div>
                    <div class="col3">
                        <label>Billed Amount</label>
                        <input type="text" name="bb[billed_amount]" value="<?php echo $bb['billed_amount'] ?>" />
                    </div>
                </div>
                <div id="billing_codes_div">
                    <div class="frow show_classes">
                        <h4>Billing Codes</h4>
                    </div>
                    <?php


                    if (isset($_REQUEST['debug'])) {
                        var_dump($bat['insurance']);
                        var_dump($ins1['ins_company']);
                    }

                    if (function_exists('tr_bill_get_billing_code')) {
                        tr_bill_get_billing_code($ins1['ins_company'], $report['id'], $bat);
                    }

                    ?>
                </div>
            <?php
            }


            include(trpdd_get_file_path('global', 'end_req_form'));

            ?>
            <?php do_action('end_form_tox', $bat) ?>

            <div class="frow submitdiv">
                <input type="submit" class="button-primary" style="margin-right:150px;" value="Submit">
                <input type="submit" class="button-primary" id="submit_print_btn" name="save_print" value="Print Barcode / Submit">
                <a class="iprint button bsty2" href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_print_req', 'id' => isset($bat['mid']) ? $bat['mid'] : '', 'type' => 'tox', 'tab' => '')) ?>">Print Form</a>

                <?php if (@$_REQUEST['page'] == 'ci_short_list') {
                ?>
                    <input type="hidden" name="bat[short_completed]" value="0">
                    <label><input type="checkbox" name="bat[short_completed]" value="1" <?php checked(@$bat['short_completed'], 1) ?>> Completed</label>
                <?php
                } ?>


                <?php if (@$mid == 0 || $saved_id > 0) { ?>
                    <input type="submit" class="button only_save_form right" name="only_save_form" value="Save">
                    <input type="hidden" name="saved_id" value="<?php echo $saved_id ?>" />
                <?php } ?>

                <input type="hidden" name="tr_action" value="<?php echo $action_form ?>" />
                <?php if (@$mid > 0) { ?>
                    <input type="hidden" name="bat[mid]" value="<?php echo $mid ?>" />
                <?php } else { ?>

                    <input type="hidden" name="bat[batch_type]" value="E" />
                <?php } ?>
            </div>
        <?php endif; ?>


        <input type="hidden" name="batch_tbl" value="<?php echo $table_batch ?>" />

        <?php trpdd_form_unique_id() ?>

    </form>
</div>
<input type="hidden" id="profile_link2" value="admin.php?page=ci_data_forms&tab=custom_provider" />

<?php if ($act == 'print') : ?>
    <script>
        window.print();
    </script>
<?php endif; ?>

<script>
    var $current_role = '<?php echo $current_role ?>';
    var $t_assen = false;
    var $sign_dir = '<?php echo $sign_dir['url'] ?>';
    var $use_phase = <?php echo isset($settings['e_phase']) && $settings['e_phase'] ? 'true' : 'false' ?>;
    var $alert_check_screening = <?php echo isset($settings['alert_check_screening']) && $settings['alert_check_screening'] ? 'true' : 'false' ?>;
    jQuery(function($) {
        function change_custom_profile_text($isphase) {
            var $cdd = $('select#batcustom_profile');
            var $opts = $('option', $cdd);
            if ($isphase) {
                $opts.each(function() {
                    var $vl = $(this).attr('value');
                    if (!$(this).data('oldtext')) {
                        $(this).data('oldtext', $(this).html());
                    }
                    $newtext = $(this).html();
                    if ($vl == 'Custom High Risk') {
                        $newtext = 'Phase 1';
                    } else if ($vl == 'Custom Mid Risk') {
                        $newtext = 'Phase 5';
                    } else if ($vl == 'Custom Low Risk') {
                        $newtext = 'Phase 28';
                    }
                    $(this).html($newtext);
                })
            } else {
                $opts.each(function() {
                    if ($(this).data('oldtext')) {
                        $(this).html($(this).data('oldtext'));
                    }
                })
            }
            $cdd.change();
        }

        function get_current_medications_for_reflex() {
            var $medications = [];
            $('[name="bat[current_medication][]"]:checked').each(function() {
                $medications.push($(this).val());
            });
            $('[name="bat[current_medication_other][]"]').each(function() {
                if ($(this).val() != '') {
                    $medications.push($(this).val());
                }
            });

            return $medications.map(function($medication) {
                return $.trim($medication + '').replace(/\s+/g, ' ').toUpperCase();
            });
        }

        function has_related_screening_medication($screening) {
            if (($screening.data('u_reflex_pos_not_medication') + '') != '1') {
                return false;
            }

            var $related = ($screening.attr('data-related_medications') + '').split(',');
            var $medications = get_current_medications_for_reflex();
            for (var $i in $related) {
                var $medication = $.trim($related[$i] + '').replace(/\s+/g, ' ').toUpperCase();
                if ($medication != '' && $medications.indexOf($medication) > -1) {
                    return true;
                }
            }

            return false;
        }

        function remove_screening_reflex_tests($screening) {
            var $ts = $screening.data('added_tests');
            if ($ts) {
                $screening.data('added_tests', false);
                for (var $i in $ts) {
                    $('[name="bat[panel_tests_requested][]"][value="' + $ts[$i] + '"]').removeAttr('checked');
                }
            }
        }

        function show_hide_panel() {
            var $cli_id = $('[name="bat[cli_ID]"]').val();
            var $rl_id = $('[name="bat[ref_lab_ID]"]').val();

            console.log('show_hide_panel, cliid:', $cli_id, ' rlid:', $rl_id);
            $('.groupparent.by_clinic').removeClass('cactive');
            $('.by_ref_lab').removeClass('cactive');
            $('.not_by_ref_lab').removeClass('notcactive');

            $('.groupparent.by_clinic.cli_id_' + $cli_id).addClass('cactive');
            $('.groupparent.by_clinic:not(.cactive) input[type="checkbox"]').prop('checked', false);

            $('.groupparent.not_by_clinic').removeClass('notcactive');
            $('.groupparent.not_by_clinic.not_cli_id_' + $cli_id).addClass('notcactive');
            $('.groupparent.not_by_clinic.not_cli_id_' + $cli_id + ' input[type="checkbox"]').prop('checked', false);


            $('.by_ref_lab.ref_lab_id_' + $rl_id).addClass('cactive');
            $('.by_ref_lab.cactive input[type="checkbox"]').prop('disabled', false);
            $('.by_ref_lab:not(.cactive) input[type="checkbox"]').prop('checked', false).prop('disabled', true);

            $('.not_by_ref_lab.not_ref_lab_id_' + $rl_id).addClass('notcactive');
            $('.not_by_ref_lab.not_ref_lab_id_' + $rl_id + ' input[type="checkbox"]').prop('checked', false);
        }


        $('body').on('change', '.warning_doc', function() {
                var $d = $(this).val();
                var currentTime = new Date();
                var month = currentTime.getMonth() + 1;
                var day = currentTime.getDate();
                var year = currentTime.getFullYear();
                if (month < 10) month = '0' + month;
                if (day < 10) day = '0' + day;
                var d1 = new Date(year + '-' + month + '-' + day);
                $d = $d.split(' ');
                $d = $d[0];
                var d2 = new Date($d);

                if (d1.getTime() < d2.getTime()) {
                    alert($(this).data('warning'));
                    $(this).val('');
                }
            })
            .on('change', '#number_assen', function() {
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
                            btype: 'tox'
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
            })
            .on('change', '[name="bat[cli_ID]"]', function() {
                var $cli_id = $(this).val();
                var $cli_opt = $('[name="bat[cli_ID]"] option:selected');
                $('.requesting_patients_last_auto').autocomplete('option', 'source', ajaxurl + '?action=ajax&autocomplete=requesting_patients_last_auto&cli_id=' + $cli_id);
                $('.screening_cb').change();
                if ($('.nh_address').is(':checked') && $(this).val() != '') {
                    $('[name="pa[pa_address]"]').val($cli_opt.data('address'));
                    $('[name="pa[pa_city]"]').val($cli_opt.data('city'));
                    $('[name="pa[pa_state]"]').val($cli_opt.data('state')).change();
                    $('[name="pa[pa_zip]"]').val($cli_opt.data('zip'));

                    if ($('[name="pa[pa_phone]"]').val() == '' && $cli_opt.data('phone')) {
                        $('[name="pa[pa_phone]"]').val($cli_opt.data('phone'));
                    }
                }
                if ($use_phase) {
                    if ($cli_opt.data('phase') && $cli_opt.data('phase') == '1') {
                        $('.phase_div').show();
                        change_custom_profile_text(1);
                    } else {
                        $('.phase_div').hide();
                        change_custom_profile_text(0);
                    }
                }
                $('#batcustom_profile').val($cli_opt.data('tox_df_profile'));
                $('#batcustom_profile').trigger('change');
                let screening = $cli_opt.data('screening')
                let only_screening = $cli_opt.data('only_screening')
                if (screening) {
                    $('input[name="screening"][value="1"]').prop('checked', true);
                    $('input[name="screening"][value="1"]').change();
                } else {
                    $('input[name="screening"][value="0"]').prop('checked', true);
                    $('input[name="screening"][value="0"]').change();
                }
                $('input[name="bat[only_screening]"]').prop('checked', !!only_screening);

                show_hide_panel();
            })
            .on('click', '.only_save_form', function() {
                $('form [required]').removeAttr('required');
            })
            .on('change', 'select#col_id_sel', function() {
                var $phone = $('option[value="' + $(this).val() + '"]', $(this)).data('phone');
                $('[name="col[col_phone]"]').val($phone);
            })
            .on('click change', '.screening_cb', function() {

                if ($(this).data('u_reflex') == '1') {

                    if ($(this).is(':checked') && $(this).data('outcome') == 'POS') {
                        if (has_related_screening_medication($(this))) {
                            remove_screening_reflex_tests($(this));
                            return;
                        }
                        var $cli_id = $('[name="bat[cli_ID]"]').val();
                        var $ts = ($(this).data('u_reflex_tests') + '').split(',');
                        var $clinics = $(this).data('u_reflex_clinics') + '';

                        var $inClinic = true;
                        if ($clinics && $clinics != '') {
                            $clinics = $clinics.split(',');
                            $inClinic = false;
                            for (var j in $clinics) {
                                if ($cli_id == $clinics[j]) {
                                    $inClinic = true;
                                    break;
                                }
                            }
                        }

                        if ($inClinic) {
                            for (var i in $ts) {
                                $('[name="bat[panel_tests_requested][]"][value="' + $ts[i] + '"]').prop('checked', true);
                            }
                            $(this).data('added_tests', $ts);
                        }
                    } else {
                        remove_screening_reflex_tests($(this));
                    }
                }

            })
            .on('change keyup', '[name="bat[current_medication][]"], [name="bat[current_medication_other][]"]', function() {
                $('.screening_cb').change();
            })
            .on('change', '[name="bat[doc_ID]"]', function() {
                $('#batcustom_profile').val('');
                $('[name="bat[current_medication][]"]').removeAttr('checked');
                $('.panel_requests input').removeAttr('checked');
                $('#batcustom_profile').change();

                var $pr = $('#provider_signdiv');
                var $sign = $('option:selected', $(this));
                $('.signimg img.bydoc', $pr).remove();
                if ($('img', $pr).length == 0 && $sign && $sign.data('signature')) {
                    var $url = $sign_dir + '/' + $sign.data('signature');
                    $('.signimg', $pr).html('<img class="sign_img bydoc" src="' + $url + '"/>');
                    $('[name="img_provider_signature"]', $pr).val($sign.data('signature'));
                    $('.signature_canvas', $pr).hide();
                } else {
                    $('.signature_canvas', $pr).show();
                }
                if ($sign.data('cus_df_profile')) {
                    $('#batcustom_profile').val($sign.data('cus_df_profile'));
                    $('#batcustom_profile').trigger('change');
                }



            })
            .on('click', '.group_label', function() {
                var $pl = $(this).parents('.groupparent');
                if (!$(this).data('checked')) {
                    $('.lcb input', $pl).prop('checked', true).change();
                    $(this).data('checked', true);
                } else {
                    $('.lcb input', $pl).prop('checked', false).change();
                    $(this).data('checked', false);
                }
            })
            .on('change', '#batcustom_profile', function() {
                var $tpl = $(this).val();
                $tpl = $tpl.toLowerCase();
                $('#default_requests input').removeAttr('checked');
                try {

                    var $key = '';
                    if ($tpl.indexOf('mid ') > -1) $key = 'med';
                    else if ($tpl.indexOf('low ') > -1) $key = 'low';
                    else if ($tpl.indexOf('high ') > -1) $key = 'high';
                    else if ($tpl.indexOf('comprehensive') > -1) $key = 'comprehensive';
                    var $test_str = '';
                    if ($key) {
                        $test_str = $('[name="bat[doc_ID]"] option:selected').data($key);
                    }

                    if ($key && $test_str && $test_str.length > 0) {
                        var $tests = $test_str.split(',');
                        for (i in $tests) {
                            $('#default_requests input[value="' + $tests[i] + '"]').prop('checked', true);
                        }
                        $('#default_requests input').each(function() {
                            var $vl = $(this).val();
                            if ($vl.indexOf(',') > 0 && $test_str.indexOf($vl) > -1) {
                                $(this).prop('checked', true);
                            }
                        })
                    } else if ($tpl == 'previous testing') {
                        var $test_str = $('[name="pa[pa_ID]"]').data('last_tests');
                        var $tests = $test_str.split(',');
                        for (i in $tests) {
                            $('#default_requests input[value="' + $tests[i] + '"]').prop('checked', true);
                        }
                        $('#default_requests input').each(function() {
                            var $vl = $(this).val();
                            if ($vl.indexOf(',') > 0 && $test_str.indexOf($vl) > -1) {
                                $(this).prop('checked', true);
                            }
                        })
                    } else {
                        if ($tpl.indexOf('mid ') > -1) $tpl = 'standard';
                        else if ($tpl.indexOf('low ') > -1) $tpl = 'basic';
                        else if ($tpl.indexOf('high ') > -1) $tpl = 'highrisk';

                        $('#default_requests input.te_' + $tpl).prop('checked', true);
                    }
                } catch (e) {
                    console.log(e);
                }
                $('#default_requests input[disabled]').removeAttr('checked');
                $('.screening_cb').change();
            })
            .on('mousedown', '[name="bat[temperature]"]', function() {
                $(this).data('waschecked', $(this).is(':checked'));
            })
            .on('click', '[name="bat[temperature]"]', function() {
                var $temperature = $(this);
                if ($temperature.data('waschecked')) {
                    $('input[name="bat[temperature]"]').prop('checked', false);
                }

                $temperature.data('waschecked', false);
            })
            .on('click', '#box_create_rand', function() {
                var $ptient = encodeURI($('[name="pa[pa_last]"]').val() + '|' + $('[name="pa[pa_first]"]').val());
                var $dob = encodeURI($('[name="pa[pa_dob]"]').val())
                tb_show('', '<?php echo get_home_url(); ?>/wp-admin/admin.php?&tr_action=qrcode&width=400&height=400&text=' + $(this).data('a') + '&pa=' + $ptient + '&dob=' + $dob);
                return false;
            })
            .on('submit', '#requisition_form', function() {
                $('.loading', $(this)).remove();
                //check screening from clinic and if no screening selected then alert
                if ($alert_check_screening) {
                    var $cli_opt = $('[name="bat[cli_ID]"] option:selected');
                    var $cli_screening = $cli_opt.data('screening') && $cli_opt.data('screening') == '1';
                    var $screening_selected = false;
                    $('.screening_cb').each(function() {
                        if ($(this).is(':checked')) {
                            $screening_selected = true;
                        }
                    })
                    if ($cli_screening != $screening_selected) {
                        if ($cli_screening && !confirm('Clinic requires screening tests to be selected. Do you want to continue without selecting any screening tests?')) {
                            return false;
                        } else if (!$cli_screening && !confirm('Clinic does not require screening tests to be selected. Do you want to order screening tests?')) {
                            $('input[name="screening"][value="0"]').prop('checked', true).change();
                        }
                    }
                }

                if ($('#confirm_assen').val() != $('[name="bat[assen]"]').val()) {
                    alert('Please Confirm Accession Number');
                    $('#confirm_assen').focus();
                    return false;
                } else {
                    //$('[type="submit"]').addClass('submitting');
                    $(this).append('<span class="loading"></span>');
                }
            })
            .on('keyup', '#confirm_assen,[name="bat[assen]"]', function() {
                if ($('#confirm_assen').val() != '' && $('#confirm_assen').val() == $('[name="bat[assen]"]').val()) {
                    $('.confirm_status').addClass('confirmed');
                    $('.confirm_status').removeClass('error');
                } else {
                    $('.confirm_status').removeClass('confirmed');
                    $('.confirm_status').addClass('error');
                }
            })
            .on('change', 'input[name="screening"]', function() {
                if ($(this).is(':checked') && $(this).val() == 1) {
                    $('#screening_div input').prop('checked', true).change();
                    $('#screening_div input.notautocheck').removeAttr('checked');
                } else {
                    $('#screening_div input').prop('checked', false);
                    $('[name="bat[only_screening]"][type="checkbox"]').prop('checked', false).change();
                }
            })
            .on('change', '#screening_div input', function() {
                var use_screening = false;
                $('#screening_div input').each(function() {
                    if ($(this).is(":checked")) {
                        use_screening = true;
                    }
                })
                if (use_screening) {
                    $('input[name="screening"][value="1"]').prop('checked', true);
                } else {
                    $('input[name="screening"][value="0"]').prop('checked', true);
                }
            })
            .on('click', '[name="auto_generated"]', function() {
                if ($(this).is(':checked')) {
                    $('#number_assen,#confirm_assen').removeAttr('required').val('').attr('readonly', '1');
                } else {
                    $('#number_assen,#confirm_assen').attr('required', 'required').removeAttr('readonly');
                }
            })
            .on('change', '[name="bat[only_screening]"][type="checkbox"]', function() {
                if ($(this).is(':checked')) {
                    $('#batcustom_profile').prop('required', false);
                } else {
                    $('#batcustom_profile').prop('required', true);
                }
            })
            .on('click', '.nh_address', function() {
                if ($(this).is(':checked') && $('[name="bat[cli_ID]"]').val() != '') {
                    var $cli_opt = $('[name="bat[cli_ID]"] option:selected');
                    $('[name="pa[pa_address]"]').val($cli_opt.data('address'));
                    $('[name="pa[pa_city]"]').val($cli_opt.data('city'));
                    $('[name="pa[pa_state]"]').val($cli_opt.data('state')).change();
                    $('[name="pa[pa_zip]"]').val($cli_opt.data('zip'));
                    if ($('[name="pa[pa_phone]"]').val() == '' && $cli_opt.data('phone')) {
                        $('[name="pa[pa_phone]"]').val($cli_opt.data('phone'));
                    }

                }
            }).on('change', '[name="batmeta[screen_group_id]"]', function() {
                try {
                    var $items = $('option:selected', $(this)).data('items');
                    if ($items) {
                        $('input.screening_cb').prop('checked', false);
                        $items = $items.split(',');
                        for (i in $items) {
                            $('input.screening_cb.screening_' + $items[i]).prop('checked', true).change();
                        }
                    }
                } catch (e) {}
            }).on('change', '[name="bat[is_employee]"]', function() {
                if ($current_role == 'collector') {
                    if ($(this).is(':checked')) {
                        $('.uploadrsbox').show();
                    } else {
                        $('.uploadrsbox').hide();
                    }
                }
            }).on('change', 'select[name="bat[ref_lab_ID]"]', function() {
                show_hide_panel();
            })


        $('.requesting_patients_last_auto').autocomplete({
            source: ajaxurl + '?action=ajax&autocomplete=requesting_patients_last_auto',
            minLength: 1,
            search: function(event, ui) {
                var $cli_id = $('[name="bat[cli_ID]"]').val();
                console.log($cli_id);
                if ($cli_id == 0) {
                    return false;
                } else {
                    $('.requesting_patients_last_auto').autocomplete('option', 'source', ajaxurl + '?action=ajax&autocomplete=requesting_patients_last_auto&cli_id=' + $cli_id);
                }
            },
            select: function(event, ui) {
                if (ui.item) {
                    $('[name="pa[pa_ID]"]').val(ui.item.id);
                    for (k in ui.item.pa) {
                        $.tr_fill_value('[name="pa[' + k + ']"]', ui.item.pa[k]);
                    }
                    if (ui.item.ins1) {
                        for (k in ui.item.ins1) {
                            $.tr_fill_value('[name="ins1[' + k + ']"]', ui.item.ins1[k]);
                        }
                    }
                    if (ui.item.ins2) {
                        for (k in ui.item.ins2) {
                            $.tr_fill_value('[name="ins2[' + k + ']"]', ui.item.ins2[k]);
                        }
                    }
                    if (ui.item.images) {
                        for (k in ui.item.images) {
                            var vl = ui.item.images[k];
                            if (vl !== '') {
                                var $obj = $('[name="img_' + k + '"]');
                                var $pr = $obj.parents('.card_file');
                                $obj.val(vl);
                                $('span', $pr).html('<div><img src="' + ui.item.card_url + '/' + vl + '"><div>');
                            }
                        }
                    }
                    try {
                        if (ui.item.medication) {
                            let $meds = ui.item.medication.split(',');
                            for (let i2 in $meds) {
                                $('input[name="bat[current_medication][]"][value="' + $meds[i2] + '"]').prop('checked', true);
                            }
                        }
                        if (ui.item.diagnostic) {
                            var $codes = ui.item.diagnostic.split(',');
                            var $list = $('#list_dagnostic_codes');
                            $list.empty();
                            for (var i3 in $codes) {
                                var $code = $.trim($codes[i3]);
                                if ($code === '') continue;
                                $list.append(
                                    '<li>' +
                                    '<input type="hidden" name="bat[dagnostic_codes][]" value="' + $code + '" />' +
                                    $code +
                                    ' <span></span>' +
                                    '</li>'
                                );
                            }
                        }
                    } catch (e) {
                        console.log(e);
                    }
                    try {
                        $('a.find_patient').trigger("found_patient", [ui.item]);
                    } catch (e) {
                        console.log(e);
                    }
                } else {
                    $('[name="pa[pa_ID]').val('');
                }
            }
        });

        if ($('[name="bat[mid]"]').val() > 0) {
            $('#screening_div input').change();
        }

        $('[name="bat[only_screening]"][type="checkbox"]').change();
        if ($('[name="auto_generated"]').is(":checked")) {
            $('#number_assen,#confirm_assen').removeAttr('required').val('').attr('readonly', '1');

        }

        if ($use_phase && $('[name="bat[cli_ID]"] option:selected[data-phase="1"]').length > 0) {
            change_custom_profile_text(1);
        }
        if ($('[name="bat[cli_ID]"] option').length == 2 && $('[name="bat[cli_ID]"] option:selected').val() != '') {
            $('[name="bat[cli_ID]"]').change();
        }

        if ($current_role == 'collector' && $('[name="bat[is_employee]"]').is(':checked') == false) {
            $('.uploadrsbox').hide();
        }

    })
</script>