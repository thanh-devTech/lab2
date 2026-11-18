<?php
global $wpdb;
global $current_role, $form_error;
global $blog_id;
global $ci_all_btypes;


$btype                  = 'blood';
$table_batch            = 'ci_batches_blood';
$table_report           = 'ci_reports_blood';
$table_results          = 'ci_results_blood';

if (isset($is_prefill_covid) && $is_prefill_covid) {
    $table_batch = 'ci_saved_batches';
}


$id                     = isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;
$act                    = isset($_REQUEST['act']) ? $_REQUEST['act'] : @$act;

$disable_submit         = false;
$notallowchangepatient  = false;
$is_hold                = false;
$current_doctor_sign    = '';
$saved_id               = 0;

$settings               = get_option('ci_req_settings_blood', array());
$show_npi               = tr_get_option('ci_pdf_data', 'req_show_npi');
$print_times            = tr_get_option('ci_pdf_data', 'blood_print_times', 1);
if (!isset($settings['disable_auto_doc'])) {
    $settings['disable_auto_doc'] = false;
}
$settings['specimen_checkbox'] = true;


$user_id                = get_current_user_id();
$dagnostic_codes        = tr_pdd_get_dagnostic_codes('blood');
if (isset($_GET['debug'])) {
    ob_start();
}



if ($act == 'remove_hold') {

    $return = array('status' => 'ok');
    $id = $_REQUEST['id'];
    if ($id > 0) {
        $report = $wpdb->get_row("select re.*, 
	                ba.mid, ba.batch_status, ba.panel_tests_requested, ba.assen
	                from {$table_report} re
	                join {$table_batch} ba ON ba.assen = re.patient_id
	                where ba.mid={$id}", ARRAY_A);
        $new_status = $report['final_results'] === '1' ? 'completed' : 'resulted';
        $return['newstatus'] = $new_status;

        $wpdb->update($table_batch, array('batch_status' => $new_status), array('mid' => $report['mid']));

        if ($report['estatus'] != 'C' && $report['final_results'] === '1') {
            $status = 'C';
            $now = date_i18n('Y-m-d H:i:s');
            $update_report = array(
                'estatus' => 'C',
                'approve_date' => $now,
                'faxed' => 0,
            );

            $wpdb->update($table_report, $update_report, array('id' => $report['id']));

            trpdd_log_user(get_current_user_id(), 'approve_rs', $report['assen'], $report['mid'], 'blood', '', '', $report['assen']);

            trpdd_add_cron_after_approve('blood');

            trpdd_trigger_approve_report($report, 'blood');
        }
    }
    wp_send_json($return);
}


$billing_page = isset($billing_page) ? $billing_page : false;

if (isset($_REQUEST['trajax'])) {
    $hide_tabs = true;
}

$tabs = array(
    '' => 'Blood Req Form',
    'completed_req' => 'Completed Req Forms',
    'req_hold' => 'Reqs On Hold',
    'saved_form' => 'Saved'
);


if (!tr_get_option('ci_pdf_data', 'req_show_hold')) {
    unset($tabs['req_hold']);
}

$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);
if (!isset($tabs[$tab])) {
    $tab = key($tabs);
}

if (!isset($hide_tabs) || $hide_tabs === false) {

    if ($act == 'print') {
?>
        <link href="<?php echo TRCPDD_URL ?>css/print.min.css?<?php echo TRCPDD_VS ?>" media="all" type="text/css" rel='stylesheet' />
    <?php
    } else {

        include_once(TRCPDD_PATH . 'pages/formdata.php');
    ?>
        <h2 class="nav-tab-wrapper">
            <?php

            foreach ($tabs as $k => $txt) {
                $class = $tab == $k ? 'nav-tab-active' : '';
                $link = Tr_Base_Class_V6::link(array('tab' => $k, 'act' => '', 'id' => '', 'mid' => ''), false);
                echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
            }
            ?>
        </h2>

<?php
    }
} else if ($billing_page) {
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






$cli            = array();
$collector      = array();
$current_lab    = array();
$clinics        = array('' => 'Please Select');
$where_clis     = " and cli.cli_clinic !='' and cli.cli_clinic !='0' ";
$cli_id         = 0;
$lab_id         = CI_Base_Lab::get_current_lab_id();

$where_ref_lab  = '';


if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $where_clis .= " and cli.`cli_lab-ID` in ( {$lab_str} ) ";
}

if ($current_role == 'lab_rep'  || $current_role == 'lab_rep_w2') {
    $rep_id = get_user_meta(get_current_user_id(), '_rep_id', true);
    $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);
    $reps = array($rep_id);
    $members = tr_dpp_get_users_in_group(get_current_user_id());
    foreach ($members as $uid) {
        $reps[] = get_user_meta($uid, '_rep_id', true);
    }
    $reps = implode(',', $reps);
    $where_clis .= " and cli.`cli_rep-ID` in ($reps) ";
} else if ($current_role == 'collector') {
    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where_clis .= " and cli.cID = {$current_clinic} ";
    }
    $col_id = get_user_meta(get_current_user_id(), '_collector_id', true);
    $collector = $wpdb->get_row("select * from ci_collector where col_ID ='{$col_id}'", ARRAY_A);
    $rep_id = $collector['rep_ID'];
    //$where_clis = " and cli.`cli_rep-ID`={$rep_id} ";
    $where_clis .= " and cc.col_ID = {$col_id} ";
} else if ($current_role == 'distributor') {
    $dis_id = $wpdb->get_var("select dis_ID from ci_distributors where dis_user_ID = {$user_id}");

    $arr_marketing_groups = $wpdb->get_results("SELECT * FROM `ci_marketing_groups` where `gr_distributor_ID`= {$dis_id} and `gr_inactive`='0'");
    $g_ids = array(-1);
    foreach ($arr_marketing_groups as $g) {
        $g_ids[] = $g->gr_ID;
    }
    $g_ids = implode(',', $g_ids);

    $where_rep .= " and (`rep_marketing_group_ID` IN ({$g_ids}) or rep_distributor_ID={$dis_id}) ";

    $where_clis .= " and (cli_distributor_ID={$dis_id} or (`rep_marketing_group_ID` IN ({$g_ids}) or rep_distributor_ID={$dis_id})) ";
    $where_group .= " and gr_distributor_ID = {$dis_id} ";
} else if ($current_role == 'office_manager' || $current_role == 'clinic') {
    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where_clis .= " and cli.cID = {$current_clinic} ";
    } else {
        if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
            $cid = get_user_meta($user_id, '_clinic_id', true);
            $cli_ids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
            $cli_ids = implode(',', $cli_ids);

            $where_clis .= " and cli.cID in ( {$cli_ids} )";
        }
    }
} else if ($current_role == 'doctor') {
    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where_clis .= " and cli.cID = {$current_clinic} ";
    }
    $doc_id = get_user_meta($user_id, '_doc_id', true);
    $clinics = $wpdb->get_col("select cli_ID from ci_clinic_doc where doc_ID={$doc_id} ");
    $clinics = implode(',', $clinics);
    if (empty($clinics)) {
        $clinics = '0';
    }
    $where_clis .= " and cli.cID in ({$clinics}) ";
    $where_doctor .= " and doc.doc_ID = {$doc_id} ";
}


$required_sign = true;
if (tr_current_role_in(array('administrator', 'administrator_p', 'AdminCIDW', 'labsecman', 'leadlabsecman', 'labmanager'))) {
    $required_sign = false;
    //$can_view_ref_lab = true;
}


if ($tab == 'completed_req') {
    include(trpdd_get_file_path('blood', 'req_list'));
    return;
} else if ($tab == 'saved_form') {
    if (!empty($act) && $act != 'delete') {
        $saved_id = $_REQUEST['id'];
    } else {
        include(trpdd_get_file_path('blood', 'req_saved'));
        return;
    }
} else if ($tab == 'prior_authorization') {
    include(trpdd_get_file_path('blood', 'prior_authorization'));
    return;
} else if (!empty($tab)) {
    $tabfile = trpdd_get_file_path($btype, $tab);
    if (file_exists($tabfile)) {
        include($tabfile);
        return;
    }
}

$assen_readonly     = false;
$bat['medication']  = '';
$collectors         = array();
$cli                = array();


$by_type_id = 0;
$by_btype = '';
if ($id == 'by_btype' && isset($_SESSION['add_new_by_btype'][0])) {
    $by_type_id  = $_SESSION['add_new_by_btype'][0];
    $by_btype    = $_SESSION['add_new_by_btype'][3];
}

if ($act == 'view' || $act == 'edit' || $act == 'print' || $by_type_id > 0) {

    $action_form = '';
    if ($by_type_id > 0) {
        $batch_other_tbl = tr_pdd_get_table_by_btype($by_btype);
        $bat = $wpdb->get_row("select * from {$batch_other_tbl} where mid='{$by_type_id}' ", ARRAY_A);
        $action_form    = 'ci_save_form_blood';
        $bat['assen']   = '';
        $bat['panel_tests_requested'] = '';
    } else {
        $bat = $wpdb->get_row("select * from {$table_batch} where mid='{$id}' ", ARRAY_A);
    }



    $cli['cID'] = $bat['cli_ID'];
    $cli_id     = $cli['cID'];

    if ($cli_id > 0) {
        $cli = $wpdb->get_row("select * from ci_clinic where `cID`={$cli_id}", ARRAY_A);
    }


    $pa = $wpdb->get_row("select pa.* from ci_patients pa
                           where pa.pa_ID = '{$bat['patient_id']}' ", ARRAY_A);

    if ($cli_id > 0) {
        $where_clis .= " or cID = {$cli_id} ";
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

    if ((!$ins1 || empty($ins1['ins_firs'])) && $bat['insurance'] == 'No Insurance - Cash') {
        $ins1['ins_first']      = $pa['pa_first'];
        $ins1['ins_last']       = $pa['pa_last'];
        $ins1['ins_relation']   = 'Self';
    }
    if (isset($ins1['ins_cares_act']) && $ins1['ins_cares_act'] && $bat['insurance'] == 'No Insurance - Cash') {
        $bat['insurance'] = '';
    }

    $collector = $wpdb->get_row("select * from ci_collector where col_ID ='{$bat['col_ID']}' ", ARRAY_A);

    $bat['panel_tests_requested_str'] = $bat['panel_tests_requested'];
    $bat['panel_tests_requested'] = explode(',', @$bat['panel_tests_requested']);
    $bat['panel_tests_requested'] = array_map('trim', $bat['panel_tests_requested']);
    $bat['panel_tests_requested'] = array_map('strtoupper', $bat['panel_tests_requested']);
    $bat['btype'] = 'blood';
    //var_dump(@$bat);
    $batch_key = $bat['assen'];

    if ($billing_page) {

        $sql = "SELECT
            lab.*,
            ba.*,
            ba.patient_id as pa_ID,
            rl.lab_additional as rl_lab_additional,
            re.*
        FROM ci_reports_blood re
    
        JOIN {$table_batch} ba ON  ba.assen = re.patient_id
    
        left JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
        LEFT JOIN ci_labs rl ON rl.lab_ID = ba.ref_lab_ID
        WHERE ba.mid='" . $id . "' group by ba.mid limit 1";

        $report = $wpdb->get_row($sql, ARRAY_A);
    }

    if ($bat['lab_id'] > 0) {
        $where_clis .= " and cli.`cli_lab-ID` = {$bat['lab_id']}";
    }

    if ($act == 'edit') {
        $action_form = 'ci_save_blood';
        $mid = $id;
    }

    if ($bat['lab_id'] > 0) {
        $lab_id = $bat['lab_id'];
    }

    $barcode_key = tr_get_option('ci_pdf_data', 'barcode_key');
    if (!empty($bat['assen']) && !empty($barcode_key) && stripos($bat['assen'], $barcode_key) === 0) {
        $assen_readonly = true;
    }

    $notallowchangepatient = tr_get_option('ci_pdf_data', 'disable_c_patient');

    $is_hold = $bat['batch_status'] == 'hold';
} else {
    $bat['mid'] = 0;
    $bat['panel_tests_requested'] = array();
    $bat['custom_profile'] = '';

    $bat['current_medication'] = array();
    $bat['type_of_specimen'] = '';
    $bat['p_pregnant'] = '';
    $bat['poc_perform'] = '0';
    $bat['fasting'] = 0;
    $bat['p_pain_score'] = '';
    $bat['p_fatigue_score'] = '';
    $bat['doc'] = '';


    $action_form = 'ci_save_blood';

    if ($cli_id == 0) {
        $cli_id = $bat['cli_ID'] = $cli['cID'] = get_user_meta($user_id, '_use_current_clinic_id', true);
    }

    if (tr_get_option('ci_pdf_data', 'current_doc')) {
        $bat['doc'] = date_i18n('Y-m-d H:i:s');
    }
}

if (isset($_POST['doc_id'])) {
    $_POST = tr_pdd_stripslashes_deep($_POST);

    $doc_id = $_POST['doc_id'];
    $doc = $wpdb->get_row("select * from ci_doc where doc_ID = {$doc_id}");
    $cli_id = $doc->cli_ID;
    $bat['physician_phone'] = $doc->doc_phone;
    $bat['doc_ID'] = $doc_id;
} else if (isset($_POST['clinic_id'])) {
    $cli_id = $_POST['clinic_id'];
    $cli = $wpdb->get_row("select * from ci_clinic where `cID`={$cli_id}", ARRAY_A);
}


if (empty($bat['ins_comp'])) {
    $bat['ins_comp'] = 'ins';
}
$card_dir = tr_get_ins_card_dir();
$sign_dir = tr_get_signature_dir();

$sql_clinic = "select cli.* 
                    from ci_clinic cli
                    Left JOIn ci_clinic_collector cc ON cc.cli_ID = cli.cID

                    left join ci_lab_reps lr on cli.`cli_rep-ID` = lr.rep_ID
                    where  cli.cli_status = 1 {$where_clis} group by cli.cID order by cli.cli_clinic";
$clinics = $wpdb->get_results($sql_clinic, ARRAY_A);
if (count($clinics) == 1) {
    $cli = $clinics[0];
    $cli_id = $bat['cli_ID'] = $cli['cID'];
}

if ($cli_id > 0) {
    $bat['col_ID'] = isset($bat['col_ID']) ? $bat['col_ID'] : 0;
    $doctors = $wpdb->get_results("select doc.* from ci_doc doc
                        JOIn ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
                        where cd.cli_ID = {$cli_id}
                        order by doc.doc_last,doc.doc_first asc", ARRAY_A);

    //checkdoctor
    if ($bat['doc_ID'] > 0) {
        $doc_ids = array();
        foreach ($doctors as $doctor) {
            $doc_ids[] = $doctor['doc_ID'];
        }
        if (!in_array($bat['doc_ID'], $doc_ids)) {
            //find doctorid
            foreach ($doctors as $doctor) {
                if ($bat['requesting_physician'] == $doctor['doc_last'] . ' ' . $doctor['doc_first']) {
                    $bat['doc_ID'] = $doctor['doc_ID'];
                    $wpdb->update($table_batch, array('doc_ID' => $bat['doc_ID']), array('mid' => $bat['mid']));
                    break;
                }
            }
        }
    }



    $current_lab = $wpdb->get_row("select * from ci_labs Where lab_ID = {$lab_id}", ARRAY_A);

    $collectors = $wpdb->get_results("select col.* from ci_collector col
                            JOIN ci_clinic_collector cc ON cc.col_ID = col.col_ID
                            where cc.cli_ID ={$cli_id} 
                            and (col.col_status=1 or col.col_ID = '{$bat['col_ID']}')", ARRAY_A);
} else {
    $current_lab = CI_Base_Lab::get_current_lab();
}

$labs       = CI_Base_Lab::get_user_labs();
$ref_labs   = $wpdb->get_results("select * from ci_labs  
                where lab_ID != '{$current_lab['lab_ID']}' {$where_ref_lab} 
                order by lab_dba");
$highlight_selected_tests_enabled = !empty($settings['highlight_selected_tests_arc_byte']);

if (isset($_REQUEST['debug'])) {
    var_dump($sql_clinic);
    var_dump($bat);
}
?>
<div class="wrap_form wrap">

    <?php if ($act == 'print') include(trpdd_get_file_path('global', 'print_req_header')); ?>

    <form method="post" enctype="multipart/form-data" data-btype="blood" class="need_validate main_form <?php if (!$bat || !$bat['mid']) echo 'emptyform' ?>" id="requisition_form">
        <h2 class="center">Blood Requisition Form</h2>
        <?php Tr_Session_Class::show() ?>

        <?php
        if ($act == 'view' || $billing_page) { ?>
            <div class="right foutrow">
                <label>Accession Number:</label>
                <span class="text bold binline" style="width:200px"><?php echo isset($bat['assen']) ? $bat['assen'] : '' ?>&nbsp;&nbsp;</span>
            </div>
        <?php } ?>

        <div class="frow blabel hlabel">
            <div class="col4">
                <?php if (count($labs) > 1) {
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
                ?>
                        <label>Lab</label>
                        <?php tr_select_option('bat[lab_id]', $labs_options, $bat['lab_id'], array('attr' => 'required')) ?>
                    <?php
                    }
                }

                if (tr_get_option('ci_pdf_data', 'show_reflab_comreqs') && count($ref_labs) > 0) {
                    $ref_labs_options = array('' => 'Select Ref Lab');
                    if (tr_current_role_in(array('ref_lab_tech', 'reflab_manager'))) {
                        $ref_labs_options = array();
                    }
                    foreach ($ref_labs as $l) {
                        $ref_labs_options[$l->lab_ID] = $l->lab_name;
                    }

                    if (!tr_get_option('ci_pdf_data', 'disable_rlbcl')) {
                        if (is_array($cli) && isset($cli['cli_ref_lab_ID']) && $cli['cli_ref_lab_ID'] > 0) {
                            $ref_labs_options = array();
                        } else if (tr_current_role_in(array('office_manager'))) {
                            $has_cli_ref_lab = false;
                            foreach ($clinics as $clinic) {
                                if ($clinic['cli_ref_lab_ID'] > 0) {
                                    $ref_labs_options = array();
                                    break;
                                }
                            }
                        }
                    }

                    if (count($ref_labs_options) > 0) {
                    ?>
                        <label>Ref Lab</label>
                        <div id="ref_lab_area">
                            <?php tr_select_option('bat[ref_lab_ID]', $ref_labs_options, $bat['ref_lab_ID'], array()) ?>
                        </div>
                <?php
                    }
                }
                ?>
                <label>Practice Information</label>
                <?php
                if (tr_current_role_in(array('lab_sec'))) {

                    $addrs = ' data-address="' . $cli['cli_street'] . '" ';
                    $addrs .= ' data-city="' . $cli['cli_city'] . '" ';
                    $addrs .= ' data-state="' . $cli['cli_state'] . '" ';
                    $addrs .= ' data-zip="' . $cli['cli_zip'] . '" ';
                    $addrs .= ' data-phone="' . $cli['cli_om-phone'] . '" ';
                    $addrs .= ' data-print2="' . $cli['cli_print_2labels'] . '" ';
                    $addrs .= ' data-pbtn="' . $cli['cli_show_submit_print_bc'] . '" ';
                    $addrs .= ' data-autoa="' . $cli['cli_auto_assen'] . '" ';
                    $addrs .= ' data-reflab="' . $cli['cli_ref_lab_ID'] . '" ';

                ?>
                    <input type="text" class="cfname requesting_practice_auto" name="bat[requesting_practice]" value="<?php echo isset($bat['requesting_practice']) ? $bat['requesting_practice'] : '' ?>" required />
                    <input type="hidden" name="bat[cli_ID]" value="<?php echo isset($bat['cli_ID']) ? $bat['cli_ID'] : '' ?>" <?php echo $addrs ?> />
                <?php
                } else { ?>
                    <select name="bat[cli_ID]" required id="cli_change" class="js_select2">
                        <option value="">Select Clinic</option>
                        <?php
                        foreach ($clinics as $clinic) {
                            $selected = $cli['cID'] == $clinic['cID'] ? 'selected' : '';
                            $addrs = ' data-address="' . $clinic['cli_street'] . '" ';
                            $addrs .= ' data-city="' . $clinic['cli_city'] . '" ';
                            $addrs .= ' data-state="' . $clinic['cli_state'] . '" ';
                            $addrs .= ' data-zip="' . $clinic['cli_zip'] . '" ';
                            $addrs .= ' data-phone="' . $clinic['cli_om-phone'] . '" ';
                            $addrs .= ' data-print2="' . $clinic['cli_print_2labels'] . '" ';
                            $addrs .= ' data-pbtn="' . $clinic['cli_show_submit_print_bc'] . '" ';
                            $addrs .= ' data-autoa="' . $clinic['cli_auto_assen'] . '" ';
                            $addrs .= ' data-reflab="' . $clinic['cli_ref_lab_ID'] . '" ';

                            echo '<option value="' . $clinic['cID'] . '" ' . $selected . ' ' . $addrs . '>' . $clinic['cli_clinic'] . '</option>';
                        }
                        ?>
                    </select>
                <?php } ?>
            </div>
            <div class="col4" id="cli_change_div">
                <label>Ordering Physician</label>
                <?php if (tr_current_role_in(array('lab_sec'))) {
                ?>
                    <input type="text" class="requesting_physician_auto cfname" name="bat[requesting_physician]" value="<?php echo isset($bat['requesting_physician']) ? $bat['requesting_physician'] : '' ?>" required />
                    <input type="hidden" name="bat[doc_ID]" value="<?php echo isset($bat['doc_ID']) ? $bat['doc_ID'] : '' ?>">
                <?php
                } else {
                ?>
                    <select name="bat[doc_ID]" required class="js_select2">
                        <option value="">Select Doctor</option>
                        <?php
                        if (empty($bat['doc_ID']) && isset($cli['cli_default_doc_ID']) && $cli['cli_default_doc_ID'] > 0) {
                            $bat['doc_ID'] = $cli['cli_default_doc_ID'];
                        }
                        $doctors = isset($doctors) && is_array($doctors) ? $doctors : array();
                        foreach ($doctors as $d) {
                            $selected = '';
                            if ((isset($bat['doc_ID']) && $d['doc_ID'] == $bat['doc_ID']) || count($doctors) == 1) {
                                $selected = 'selected';
                                $current_doctor_sign = $d['doc_signature'];
                            }
                            $doctor_display = tr_trim_name($d['doc_last'] . ' ' . $d['doc_first']);
                            if ($show_npi && !empty($d['doc_npi'])) {
                                $doctor_display .= ' - ' . $d['doc_npi'];
                            }
                            echo '<option value="' . $d['doc_ID'] . '" ' . $selected . ' data-signature="' . $d['doc_signature'] . '" >' . $doctor_display . '</option>';
                        }
                        ?>
                    </select>
                <?php
                }
                ?>
                <label><?php echo isset($settings['Phlebotomist']) && $settings['Phlebotomist'] ? 'Phlebotomist' : 'Collector Name' ?></label>

                <?php if (($collectors && count($collectors) > 0 && $current_role != 'collector')
                    && !tr_get_option('ci_pdf_data', 'allow_type_col', false)
                    && !tr_get_option('ci_pdf_data', 'discreatedcol')
                ) { ?>
                    <select name="col[col_ID]" id="col_id_sel" class="js_select2">
                        <?php
                        foreach ($collectors as $collector) {
                            $selected = $collector['col_ID'] == $bat['col_ID'] ? 'selected' : '';
                            echo '<option value="' . $collector['col_ID'] . '" ' . $selected . ' 
				                    data-phlebotomist="' . $collector['col_phlebotomist_code'] . '"
				                    data-phone="' . $collector['col_phone'] . '">' . tr_trim_name(@$collector['col_first'] . ' ' . @$collector['col_last']) . '</option>';
                        }
                        $collector['col_phone'] = $collectors[0]['col_phone'];
                        $collector['col_phlebotomist_code'] = $collectors[0]['col_phlebotomist_code'];
                        ?>
                    </select>
                <?php } else {
                    if (!$collector) {
                        $collector = isset($collectors[0]) ? $collectors[0] : array();
                    }

                    $collector_name = isset($collector['col_first']) ? tr_trim_name(@$collector['col_first'] . ' ' . @$collector['col_last']) : '';
                    if (empty($collector_name) && tr_get_option('ci_pdf_data', 'discreatedcol')) {
                        $author_id = (isset($bat['author_ID']) && $bat['author_ID']) > 0 || $bat['mid'] > 0 ? $bat['author_ID'] : get_current_user_id();

                        if ($author_id > 0) {
                            $collector_name = get_user_meta($author_id, 'first_name', true) . ' ' .
                                get_user_meta($author_id, 'last_name', true);
                            $collector['col_ID'] = -1;
                        }
                    }
                ?>
                    <input type="text" class="<?php if ($current_role != 'collector') echo 'col_auto' ?>" name="col[col_name]" value="<?php echo $collector_name ?>" />
                    <input type="hidden" name="col[col_ID]" value="<?php echo isset($collector['col_ID']) ? $collector['col_ID'] : '' ?>" />
                <?php } ?>

                <?php if (isset($settings['Phlebotomist']) && $settings['Phlebotomist']) {
                ?>
                    <label>Phlebotomist Code</label>
                    <input type="text" name="col[col_phlebotomist_code]" value="<?php echo isset($collector['col_phlebotomist_code']) ? $collector['col_phlebotomist_code'] : '' ?>" <?php if (!empty($collector['col_phlebotomist_code'])) echo 'readonly' ?> />
                <?php
                } else { ?>
                    <label>Collector Phone Number</label>
                    <input type="text" class="has_phone" name="col[col_phone]" value="<?php echo isset($collector['col_phone']) ? $collector['col_phone'] : '' ?>" <?php if (!empty($collector['col_phone'])) echo 'readonly' ?> />
                <?php } ?>
            </div>
            <div class="col4">
                <label>Diagnostic Codes</label>

                <div class="dagnostic_code" data-name="bat[dagnostic_codes][]" <?php if (tr_get_option('ci_pdf_data', 'blood_dc_required')) echo 'data-required="true"' ?>>
                    <ul id="list_dagnostic_codes">
                        <?php
                        $bat['dagnostic_codes'] = explode(',', isset($bat['dagnostic_codes1']) ? $bat['dagnostic_codes1'] : '');
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
                            <?php tr_select_option('dagnostic_code_select', $dagnostic_codes, null) ?>
                            <a class="itip thickbox" href="<?php echo TRCPDD_URL ?>images/ICD10Crosswalk.png"></a>
                            <input type="text" id="dagnostic_code_text" placeholder="Enter Additional Codes" />
                        </div>
                    <?php endif; ?>
                </div>

            </div>
            <div class="col4">

                <label>Specimen Information</label>

                <div class="frow">
                    <div class="ilabel">
                        <?php
                        $types = tr_pdd_get_type_of_specimens('blood');

                        if (isset($settings['specimen_checkbox']) && $settings['specimen_checkbox']) {
                            $type_of_specimens = explode(',', $bat['type_of_specimen']);

                            echo '<span class="group_radio">';
                            foreach ($types as $k => $vl) {
                                echo '<label><input type="checkbox" name="bat[type_of_specimen][]" 
									    value="' . $k . '" ' . checked(in_array($k, $type_of_specimens), true, false) . '/> ' . $vl . '</label>';
                            }
                            echo '</span>';
                        } else {
                            if (!empty($bat['type_of_specimen']) && !isset($types[$bat['type_of_specimen']])) {
                                $types[$bat['type_of_specimen']] = $bat['type_of_specimen'];
                            }
                            if (count($types) == 1) {
                                $bat['type_of_specimen'] = array_key_first($types);
                            }
                            tr_radio('bat[type_of_specimen]', $types, isset($bat['type_of_specimen']) ? $bat['type_of_specimen'] : '', array('attr' => 'required'));
                        }
                        ?>
                    </div>
                </div>
                <div class="frow2">
                    <label>Date of Collection</label>
                    <input type="text" class="hasdatetime <?php if (tr_get_option('ci_pdf_data', 'limit_doc')) echo 'limitfuture' ?>" name="bat[doc]" value="<?php echo empty($bat['doc']) && $act != 'print' && !$settings['disable_auto_doc'] ? 'auto' : $bat['doc'] ?>" <?php if (!empty($bat['doc'])) echo 'readonly' ?> required="" />

                    <?php if (!tr_get_option('ci_pdf_data', 'hide_fasting', false)) { ?>
                        <div class="ilabel" style="margin-top:5px;">
                            <label class="bold">Fasting</label>
                            <?php tr_radio('bat[fasting]', array(0 => 'No', 1 => 'Yes'), @$bat['fasting']) ?>
                        </div>
                    <?php } ?>
                    <?php if (tr_get_option('ci_pdf_data', 'enable_stat')) { ?>
                        <div class="ilabel" style="margin-top:5px;">
                            <label><input type="checkbox" name="bat[stat]" value="1" <?php checked(isset($bat['stat']) ? $bat['stat'] : 0) ?> /> STAT</label>
                        </div>
                    <?php } ?>
                </div>

            </div>
        </div>

        <div class="frow">
            <?php if (tr_get_option('ci_pdf_data', 'show_pregnant')) { ?>
                <div class="col4">
                    <label>Pregnant</label>
                    <?php tr_select_option('bat[p_pregnant]', array('' => 'Select', 'Y' => 'Yes', 'N' => 'No'), @$bat['p_pregnant']) ?>
                </div>
            <?php } ?>
            <?php if (tr_get_option('ci_pdf_data', 'show_fin')) { ?>
                <div class="col4">
                    <label>FIN</label>
                    <input type="text" maxlength="50" name="bat[p_fin]" value="<?php echo isset($bat['p_fin']) ? $bat['p_fin'] : '' ?>" />
                </div>
            <?php } ?>
            <?php if (tr_get_option('ci_pdf_data', 'show_ehr')) { ?>
                <div class="col4">
                    <label>EHR</label>
                    <input type="text" maxlength="50" name="bat[p_ehr]" value="<?php echo isset($bat['p_ehr']) ? $bat['p_ehr'] : '' ?>" />
                </div>
            <?php } ?>
        </div>
        <?php
        if (tr_get_option('ci_pdf_data', 'show_bnt_file_pa_ins')) {
        ?>
            <div class="frow">
                <h4>Attach Face Sheet and Attach Demographics</h4>
            </div>
            <div class="frow">
                <div class="col2">
                    <div class="card_file">
                        <a class="button selectfile" href="#">Choose Attach Face Sheet</a>
                        <input type="file" name="attach_face_sheet" />
                        <input type="hidden" id="attach_face_sheet" name="attach_face_sheet" value="<?php echo isset($bat['attach_face_sheet']) ? $bat['attach_face_sheet'] : '' ?>" />
                        <?php if (!empty($bat['attach_face_sheet'])) {
                            $original_req = $card_dir['url'] . '/' . $bat['attach_face_sheet'] . '?' . time();
                        ?>
                            <span><a href="<?php echo $original_req ?>" class="button view_card_file" target="_blank">View Attach Face Sheet</a></span>
                            <a class="button remove">Remove</a>
                        <?php
                        } else {
                            echo '<span></span>';
                        } ?>
                    </div>
                </div>
                <div class="col2">
                    <div class="card_file">
                        <a class="button selectfile" href="#">Choose Attach Demographics</a>
                        <input type="file" name="attach_demographics" />
                        <input type="hidden" id="attach_demographics" name="attach_demographics" value="<?php echo isset($bat['attach_demographics']) ? $bat['attach_demographics'] : '' ?>" />
                        <?php if (!empty($bat['attach_demographics'])) {
                            $original_req = $card_dir['url'] . '/' . $bat['attach_demographics'] . '?' . time();
                        ?>
                            <span><a href="<?php echo $original_req ?>" class="button view_card_file" target="_blank">View Attach Demographics</a></span>
                            <a class="button remove">Remove</a>
                        <?php
                        } else {
                            echo '<span></span>';
                        } ?>
                    </div>
                </div>
            </div>
        <?php
        }
        ?>

        <div class="frow">
            <h4>Patient Information</h4>
        </div>

        <div class="patient_block">
            <div class="frow blabel">
                <div class="col4">
                    <label>Social Security Number</label>
                    <input type="text" name="pa[pa_ssn]" id="pa_ssn" maxlength="<?php echo tr_get_option('ci_pdf_data', 'ssn_limit', 12) ?>" value="<?php echo isset($pa['pa_ssn']) ? $pa['pa_ssn'] : '' ?>" <?php if ($act != 'print') echo 'style="width:60%"' ?> />
                    <input type="hidden" name="pa[pa_ID]" value="<?php echo isset($pa['pa_ID']) ? $pa['pa_ID'] : '' ?>" />

                    <?php if ($act != 'print') : ?>
                        <a class="button find_patient samefheight" href="#" data-type="ssn">find</a>
                    <?php endif; ?>
                </div>
                <div class="col4">
                    <label>Last Name</label>
                    <input type="text" class="cfname requesting_physician_last_auto" name="pa[pa_last]" value="<?php echo isset($pa['pa_last']) ? $pa['pa_last'] : '' ?>" <?php if ($notallowchangepatient && !empty($pa['pa_last']) && $saved_id > 0) echo 'readonly' ?> required="" />
                </div>
                <div class="col4">
                    <label>First Name</label>
                    <input type="text" class="cfname" name="pa[pa_first]" value="<?php echo isset($pa['pa_first']) ? $pa['pa_first'] : '' ?>" <?php if ($notallowchangepatient && !empty($pa['pa_first']) && $saved_id > 0) echo 'readonly' ?> required="" />
                </div>
                <div class="col4">
                    <label>Middle Initial</label>
                    <input type="text" class="cfname" name="pa[pa_middle]" value="<?php echo isset($pa['pa_middle']) ? $pa['pa_middle'] : '' ?>" <?php if (tr_get_option('ci_pdf_data', 'pa_mid_required')) echo 'required' ?> />
                </div>
            </div>

            <div class="frow blabel">
                <div class="col4">
                    <label>Medical Record Number</label>
                    <input type="text" id="med_number" name="pa[pa_mrn]" value="<?php echo isset($pa['pa_mrn']) ? $pa['pa_mrn'] : '' ?>" maxlength="15" <?php
                                                                                                                                                        if ($act != 'print') echo ' style="width:60%" ';
                                                                                                                                                        if (tr_get_option('ci_pdf_data', 'mrn_required')) echo ' required ';
                                                                                                                                                        ?> />
                    <?php if ($act != 'print') : ?>
                        <a class="button find_patient samefheight" href="#" data-type="med_number">find</a>
                    <?php endif; ?>
                </div>
                <div class="col4">
                    <label>Street Address <span class="right">NH <input type="checkbox" class="nh_address" /> </span> </label>
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
                <div class="col4 label2d">
                    <label>Gender</label><br>
                    <?php tr_select_option('pa[pa_gender]', trpdd_genders(), isset($pa['pa_gender']) ? $pa['pa_gender'] : '', array('attr' => 'required="" ')) ?>
                </div>
                <div class="col4">
                    <label>DOB</label>
                    <input type="text" class="<?php if ((!$notallowchangepatient || empty($pa['pa_dob']) || $pa['pa_dob'] == '0000-00-00') && $saved_id == 0) echo 'hasdate' ?> birthday limitfuture" name="pa[pa_dob]" value="<?php echo isset($pa['pa_dob']) ? $pa['pa_dob'] : '' ?>" <?php if ($notallowchangepatient && !empty($pa['pa_dob'])) echo 'readonly' ?> required="" <?php if (tr_get_option('ci_pdf_data', 'e_hand_editdob')) echo 'data-write="1"' ?> />
                </div>
                <div class="col4">
                    <label>Phone Number</label>
                    <input type="text" class="has_phone" name="pa[pa_phone]" value="<?php echo isset($pa['pa_phone']) ? $pa['pa_phone'] : '' ?>" maxlength="12" required="" />
                </div>

                <div class="col4">
                    <label>Email</label>
                    <input type="email" name="pa[pa_email]" value="<?php echo isset($pa['pa_email']) ? $pa['pa_email'] : '' ?>" <?php if (tr_get_option('ci_pdf_data', 'pa_email_required')) echo 'required' ?> />
                </div>
            </div>

            <div class="frow">
                <?php
                $count_colinrow = 0;
                if (tr_get_option('ci_pdf_data', 'show_symtomatic') && !tr_get_option('ci_pdf_data', 'disable_ssn')) {
                    $count_colinrow++;
                    if ($count_colinrow > 1 && $count_colinrow % 4 == 1) {
                        $count_colinrow = 0;
                        echo '</div><div class="frow">';
                    }
                ?>
                    <div class="col4">
                        <label>Is patient Symptomatic?</label>
                        <?php tr_select_option('pa[pa_symptomatic]', array('' => 'Select', 'Y' => 'Yes', 'N' => 'No'), isset($pa['pa_symptomatic']) ? $pa['pa_symptomatic'] : '', array('attr' => 'required="" ')) ?>
                    </div>
                <?php
                }

                if (tr_get_option('ci_pdf_data', 'show_patient_passport')) {
                    $count_colinrow++;
                    if ($count_colinrow > 1 && $count_colinrow % 4 == 1) {
                        $count_colinrow = 0;
                        echo '</div><div class="frow">';
                    }
                ?>
                    <div class="col4">
                        <label>Passport Number</label>
                        <input type="text" name="pa[pa_passport]" value="<?php echo isset($pa['pa_passport']) ? $pa['pa_passport'] : '' ?>" maxlength="100" />
                    </div>
                <?php
                }

                $show_race = true;
                if ($show_race) {
                    $count_colinrow++;
                    if ($count_colinrow > 1 && $count_colinrow % 4 == 1) {
                        $count_colinrow = 0;
                        echo '</div><div class="frow">';
                    }
                ?>
                    <div class="col4">
                        <label>Race</label>
                        <?php
                        $races = array(
                            ''       => 'Select',
                            '1002-5' => 'American Indian or Alaska  Native',
                            '2028-9' => 'Asian',
                            '2054-5' => 'Black / African American',
                            '2076-8' => 'Native Hawaiian or Other  Pacific Islander',
                            '2106-3' => 'White',
                            '2131-1' => 'Other'
                        );
                        tr_select_option('pa[pa_race]', $races, isset($pa['pa_race']) ? $pa['pa_race'] : '');
                        ?>
                    </div>
                <?php
                }

                $show_ethnicity = true;
                if ($show_ethnicity) {
                    $count_colinrow++;
                    if ($count_colinrow > 1 && $count_colinrow % 4 == 1) {
                        $count_colinrow = 0;
                        echo '</div><div class="frow">';
                    }
                ?>
                    <div class="col4">
                        <label>Ethnicity</label>
                        <?php
                        $Ethnicitis = array(
                            ''  => 'Select',
                            'H' => 'Hispanic or Latino',
                            'N' => 'Not Hispanic or Latino',
                            'U' => 'Unknown'
                        );
                        tr_select_option('pa[pa_ethnicity]', $Ethnicitis, isset($pa['pa_ethnicity']) ? $pa['pa_ethnicity'] : '');
                        ?>
                    </div>

                <?php
                }

                if (tr_get_option('ci_pdf_data', 'req_patient_location')) {
                    if ($count_colinrow >= 4) {
                        $count_colinrow = 0;
                        echo '</div><div class="frow">';
                    }
                    $count_colinrow++;
                    echo '<div class="col4">';
                    echo '<label>Location</label>';
                    echo '<input type="text" name="pa[pa_location]" value="' . (isset($pa['pa_location']) ? $pa['pa_location'] : '') . '" />';
                    echo '</div>';
                }

                if (isset($settings['show_pa_bmi']) && $settings['show_pa_bmi']) {
                    $count_colinrow++;
                    if ($count_colinrow > 1 && $count_colinrow % 4 == 1) {
                        $count_colinrow = 0;
                        echo '</div><div class="frow">';
                    }
                ?>
                    <div class="col4">
                        <div class="frow">
                            <div class="col2">
                                <label>Height</label>
                                <input type="text" name="pa[pa_height]" value="<?php echo isset($pa['pa_height']) ? esc_attr($pa['pa_height']) : '' ?>" placeholder="inches" maxlength="10" />
                            </div>
                            <div class="col2">
                                <label>Weight</label>
                                <input type="text" name="pa[pa_weight]" value="<?php echo isset($pa['pa_weight']) ? $pa['pa_weight'] : '' ?>" placeholder="LBs" maxlength="10" />
                            </div>
                        </div>
                    </div>

                <?php
                }

                if (isset($settings['show_pa_bmi']) && $settings['show_pa_bmi']) {
                    $count_colinrow++;
                    if ($count_colinrow > 1 && $count_colinrow % 4 == 1) {
                        $count_colinrow = 0;
                        echo '</div><div class="frow">';
                    }
                ?>
                    <div class="col4">
                        <label>Marital Status</label>
                        <?php
                        $marital_statuses = array(
                            '' => 'Select',
                            's' => 'Single',
                            'm' => 'Married',
                            'd' => 'Decline to respond',
                            'o' => 'Other'
                        );
                        tr_select_option('pa[pa_marital_status]', $marital_statuses, isset($pa['pa_marital_status']) ? $pa['pa_marital_status'] : '');
                        ?>
                    </div>

                <?php
                }



                if (tr_get_option('ci_pdf_data', 'show_patient_sogi')) {
                    $count_colinrow++;
                    if ($count_colinrow > 1 && $count_colinrow % 4 == 1) {
                        $count_colinrow = 0;
                        echo '</div><div class="frow">';
                    }

                ?>
                    <div class="col4">
                        <label>Sexual Orientation</label>
                        <input type="text" name="pa[pa_sexual_oriention]" value="<?php echo isset($pa['pa_sexual_oriention']) ? $pa['pa_sexual_oriention'] : '' ?>" maxlength="100" />
                    </div>
                    <?php
                    $count_colinrow++;
                    if ($count_colinrow > 1 && $count_colinrow % 4 == 1) {
                        $count_colinrow = 0;
                        echo '</div><div class="frow">';
                    }
                    ?>
                    <div class="col4">
                        <label>Gender Identity</label>
                        <input type="text" name="pa[pa_gender_identity]" value="<?php echo isset($pa['pa_gender_identity']) ? $pa['pa_gender_identity'] : '' ?>" maxlength="100" />
                    </div>
                <?php

                }

                if (isset($settings['show_pa_genotypes']) && $settings['show_pa_genotypes'] && !empty($settings['pa_genotypes'])) {
                    $count_colinrow++;
                    if ($count_colinrow > 1 && $count_colinrow % 4 == 1) {
                        $count_colinrow = 0;
                        echo '</div><div class="frow">';
                    }
                ?>
                    <div class="col4">
                        <label>Genotype</label>
                        <?php
                        tr_select_option('pa[pa_genotypes]', $settings['pa_genotypes'], isset($pa['pa_genotypes']) ? $pa['pa_genotypes'] : '', array('attr' => 'required'));
                        ?>
                    </div>

                <?php
                }

                if (isset($settings['show_pa_hbgenotypes']) && $settings['show_pa_hbgenotypes']) {
                    $count_colinrow++;
                    if ($count_colinrow > 1 && $count_colinrow % 4 == 1) {
                        $count_colinrow = 0;
                        echo '</div><div class="frow">';
                    }
                ?>
                    <div class="col4">
                        <label>HB Genotype</label>
                        <input type="text" name="pa[pa_hb_genotype]" value="<?php echo $pa['pa_hb_genotype'] ?>" />
                    </div>

                <?php
                }

                if (isset($settings['Phlebotomist']) ? $settings['Phlebotomist'] : '') {
                    $count_colinrow++;
                    if ($count_colinrow > 1 && $count_colinrow % 4 == 1) {
                        $count_colinrow = 0;
                        echo '</div><div class="frow">';
                    }
                ?>
                    <div class="col4">
                        <label>Class</label>
                        <?php
                        $classes = array(
                            '' => 'Select',
                            'A' => 'A',
                            'H' => 'H'
                        );
                        tr_select_option('pa[pa_phlebotomist_class]', $classes, isset($pa['pa_phlebotomist_class']) ? $pa['pa_phlebotomist_class'] : '');
                        ?>
                    </div>

                <?php
                }
                ?>

            </div>
            <?php if (tr_get_option('ci_pdf_data', 'enable_patient_score')) { ?>
                <div class="frow">
                    <div class="col4">
                        <label>Pain Score</label>
                        <select name="bat[p_pain_score]" class="js_select2">
                            <option value="">Select</option>
                            <?php
                            for ($i = 0; $i <= 10; $i++) {
                                $selected = !in_array($bat['p_pain_score'], array(null, '')) && isset($bat['p_pain_score']) && $bat['p_pain_score'] == $i ? 'selected' : '';
                                echo '<option value="' . $i . '" ' . $selected . '>' . $i . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col4">
                        <label>Fatigue Score</label>
                        <select name="bat[p_fatigue_score]" class="js_select2">
                            <option value="">Select</option>
                            <?php
                            for ($i = 0; $i <= 10; $i++) {
                                $selected = !in_array(@$bat['p_fatigue_score'], array(null, '')) && isset($bat['p_fatigue_score']) && $bat['p_fatigue_score'] == $i ? 'selected' : '';
                                echo '<option value="' . $i . '" ' . $selected . '>' . $i . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col4">
                        <label>Reason for Testing</label>
                        <select name="bat[p_condition]" class="js_select2">
                            <option value="">Select</option>
                            <?php
                            $conditions = array('Baseline', 'Therapy Start', 'Monitor Therapy', 'Acute Crisis');
                            foreach ($conditions as $condition) {
                                $selected = isset($bat['p_condition']) && $condition == $bat['p_condition'] ? 'selected' : '';
                                echo '<option value="' . $condition . '" ' . $selected . '>' . $condition . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col4">
                        <label>Hemoglobin</label>
                        <input type="text" name="bat[p_hemoglobin]" value="<?php echo isset($bat['p_hemoglobin']) ? $bat['p_hemoglobin'] : '' ?>" maxlength="40" />
                    </div>
                </div>
            <?php } ?>
            <?php if (tr_get_option('ci_pdf_data', 'show_patient_r3snh')) { ?>
                <div class="frow">
                    <?php
                    $patient_types = array(
                        '' => 'None',
                        'SC' => 'Symptomatic',
                        'NH' => 'Nursing Home',
                        'HW' => 'Healthcare Worker'
                    );
                    tr_radio('pa[pa_patient_type]', $patient_types, $pa['pa_patient_type']);
                    ?>
                </div>
            <?php } ?>
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
        </div>

        <?php if (tr_get_option('ci_pdf_data', 'show_patient_county_pregnant')) { ?>
            <div class="frow">
                <div class="col4">
                    <label>Pt County</label>
                    <input type="text" name="pa[pa_pt_county]" value="<?php echo $pa['pa_pt_county'] ?>" />
                </div>
                <div class="col4">
                    <label>Employed in Healthcare?</label>
                    <?php tr_select_option('pa[pa_employed_in_healthcar]', array(
                        ''  => 'Select',
                        'Y' => 'Yes',
                        'N' => 'No'
                    ), @$pa['pa_employed_in_healthcar'], array()) ?>

                </div>
                <div class="col4">
                    <label>Symptomatic per cdc?</label>
                    <?php tr_select_option('pa[pa_symptomatic]', array(
                        ''  => 'Select',
                        'Y' => 'Yes',
                        'N' => 'No'
                    ), @$pa['pa_symptomatic'], array()) ?>

                </div>
                <div class="col4">
                    <label>Date of Symptom Onset?</label>
                    <input type="text" class="hasdate" name="pa[pa_date_of_symptom]" placeholder="yyyy-mm-dd" value="<?php echo $pa['pa_date_of_symptom'] != '0000-00-00' ? $pa['pa_date_of_symptom'] : '' ?>" />
                </div>
            </div>
            <div class="frow">
                <div class="col4">
                    <label>Hospitalized in ICU?</label>
                    <?php tr_select_option('pa[pa_hospitalized_in_icu]', array(
                        ''  => 'Select',
                        'Y' => 'Yes',
                        'N' => 'No'
                    ), @$pa['pa_hospitalized_in_icu'], array()) ?>

                </div>
                <div class="col4">
                    <label>Resident in Congregate Care</label>
                    <?php tr_select_option('pa[pa_resident_in_congregate_care]', array(
                        ''  => 'Select',
                        'Y' => 'Yes',
                        'N' => 'No'
                    ), @$pa['pa_resident_in_congregate_care'], array()) ?>

                </div>
                <div class="col4">
                    <label>Pregnant?</label>
                    <?php tr_select_option('pa[pa_pregnant]', array(
                        ''  => 'Select',
                        'Y' => 'Yes',
                        'N' => 'No'
                    ), @$pa['pa_pregnant']) ?>

                </div>
            </div>
        <?php } ?>

        <?php if (!tr_get_option('ci_pdf_data', 'hide_insurance')) { ?>

            <?php if (!tr_get_option('ci_pdf_data', 'blood_hide_worker')) { ?>
                <div class="frow">
                    <div class="col23">
                        <h4><label><input type="radio" name="bat[ins_comp]" value="ins" <?php checked('ins', @$bat['ins_comp']) ?> /> PRIMARY INSURANCE BILLING <span>(include copy of both sides of insurance card)</span></label></h4>
                    </div>
                    <div class="col13">
                        <h4><label><input type="radio" name="bat[ins_comp]" value="comp" <?php checked('comp', @$bat['ins_comp']) ?> /> WORKER'S COMP AND AUTO INFORMATION</label></h4>
                    </div>
                </div>
            <?php } else { ?>
                <div class="frow">
                    <h4><label>Primary Insurance Billing <span>(include copy of both sides of insurance card)</span></label></h4>
                </div>
            <?php } ?>

            <div class="frow blabel">
                <div class="<?php echo (!tr_get_option('ci_pdf_data', 'blood_hide_worker')) ? 'col23' : 'full' ?> insurance_block1" id="use_required_ins">

                    <div class="frow">
                        <span>
                            <input type="hidden" name="no_insurance" value="" />
                            <label class="inline"><input type="checkbox" name="no_insurance" value="No Insurance - Cash" <?php checked(isset($bat['insurance']) ? $bat['insurance'] : '', 'No Insurance - Cash') ?> /> No Insurance - Cash</label>
                        </span>
                        <?php if (tr_get_option('ci_pdf_data', 'req_blood_cares_act')) { ?>
                            <span style="padding-left:30px">
                                <input type="hidden" name="ins1[ins_cares_act]" value="" />
                                <label class="inline"><input type="checkbox" name="ins1[ins_cares_act]" value="1" <?php checked(isset($ins1['ins_cares_act']) ? $ins1['ins_cares_act'] : '', '1') ?> /> No Insurance - CARES Act</label>
                            </span>
                        <?php } ?>

                        <?php if (tr_get_option('ci_req_settings_blood', 'enable_client_bill')) { ?>
                            <span style="padding-left:30px">
                                <input type="hidden" name="client_bill" value="" />
                                <label class="inline"><input type="checkbox" name="client_bill" value="Client bill" <?php checked(isset($bat['insurance']) ? $bat['insurance'] : '', "Client bill") ?> /> Client bill</label>
                            </span>
                        <?php } ?>
                    </div>
                    <div class="frow">
                        <div class="col3">
                            <label>Last Name</label>
                            <input type="text" class="cfname" name="ins1[ins_last]" value="<?php echo isset($ins1['ins_last']) ? $ins1['ins_last'] : '' ?>" <?php echo $required_insurance ?> />
                            <input type="hidden" name="ins1[ins_ID]" value="<?php echo isset($ins1['ins_ID']) ? $ins1['ins_ID'] : '' ?>" />
                        </div>
                        <div class="col3">
                            <label>First Name</label>
                            <input type="text" class="cfname" name="ins1[ins_first]" value="<?php echo isset($ins1['ins_first']) ? $ins1['ins_first'] : '' ?>" <?php echo $required_insurance ?> />
                        </div>
                        <div class="col3">
                            <label>Relation to Patient?</label>
                            <?php
                            if ($act == 'print') {
                                echo '<span class="text">' . $ins1['ins_relation'] . '</span>';
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
                    <div class="frow">
                        <div class="col3">
                            <label>Insurance Company Name</label>
                            <?php if (tr_get_option('ci_pdf_data', 'dropdown_insurance')) { ?>
                                <select name="ins1[ins_company]" class="js_select2" <?php echo $required_insurance ?>>
                                    <option value="">Select</option>
                                    <?php
                                    $insurances = $wpdb->get_results("select * from ci_payer_list order by payer_name asc");
                                    $has_ins_selected = false;
                                    foreach ($insurances as $ins) {
                                        $selected = isset($ins1['ins_company']) && $ins->payer_name == $ins1['ins_company'] ? 'selected' : '';
                                        if ($selected) $has_ins_selected = true;
                                        echo '<option value="' . $ins->payer_name . '" ' . $selected . '>' . $ins->payer_name . '</option>';
                                    }
                                    if (!$has_ins_selected && !empty($ins1['ins_company'])) {
                                        echo '<option value="' . $ins1['ins_company'] . '" selected>' . $ins1['ins_company'] . '</option>';
                                    }
                                    ?>
                                </select>
                            <?php } else { ?>
                                <input type="text" class="cfname ins_company_auto" name="ins1[ins_company]" value="<?php echo isset($ins1['ins_company']) ? $ins1['ins_company'] : '' ?>" <?php echo $required_insurance ?> />
                            <?php } ?>
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
                            <input type="text" name="ins1[ins_group_id]" maxlength="20" value="<?php echo isset($ins1['ins_group_id']) ? $ins1['ins_group_id'] : '' ?>" />
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

                    <h4>Secondary Insurance Billing <span>(include copy of both sides of insurance card)</span></h4>
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
                        <div class="col3">
                            <label>Relation to Patient?</label>
                            <?php
                            if ($act == 'print') {
                                echo '<span class="text">' . $ins2['ins_relation'] . '</span>';
                            } else {
                                tr_radio('ins2[ins_relation]', array(
                                    'Self'   => 'Self',
                                    'Parent' => 'Parent',
                                    'Spouse' => 'Spouse'
                                ), isset($ins2['ins_relation']) ? $ins2['ins_relation'] : '');
                            }
                            ?>
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
                <?php if (!tr_get_option('ci_pdf_data', 'blood_hide_worker')) { ?>
                    <div class="col13" id="use_required_com">
                        <div class="frow">
                            <div class="col2">
                                <label>Subscriber Last Name</label>
                                <input type="text" class="cfname" name="bat[subscriber_last]" value="<?php echo isset($bat['subscriber_last']) ? $bat['subscriber_last'] : '' ?>" data-required="" />
                            </div>
                            <div class="col2">
                                <label>Subscriber First Name</label>
                                <input type="text" class="cfname" name="bat[subscriber_first]" value="<?php echo isset($bat['subscriber_first']) ? $bat['subscriber_first'] : '' ?>" data-required="" />
                            </div>
                        </div>
                        <div class="frow">
                            <label>Date of Injury</label>
                            <input type="text" class="hasdate" name="bat[date_of_injury]" value="<?php echo isset($bat['date_of_injury']) ? $bat['date_of_injury'] : '' ?>" data-required="" />
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
                                <input type="text" class="cfname" name="bat[subscriber_insurance]" value="<?php echo isset($bat['subscriber_insurance']) ? $bat['subscriber_insurance'] : '' ?>" data-required="" />
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
                                <label class="printsmall">Attorney Phone</label>
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
                            <input type="text" name="bat[claim_number]" value="<?php echo isset($bat['claim_number']) ? $bat['claim_number'] : '' ?>" data-required="" />
                        </div>
                        <div class="frow">
                            <label>Claim under litigation?</label>
                            <?php tr_yes_no('bat[claim_under_litigation]', isset($bat['claim_under_litigation']) ? $bat['claim_under_litigation'] : ''); ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>

        <div class="frow">
            <h4>Insert Insurance Card and Photo ID Pictures</h4>
        </div>
        <?php if (!isset($billing_page) || !$billing_page) { ?>
            <div class="frow">
                By clicking the icon below, you will be prompted to insert a photo of both the patient current insurance card and photo ID. The photo
                can be obtained directly from the computer (saved as a pdf) or a scanner.
            </div>
        <?php } ?>
        <div class="frow blabel">
            <?php
            $col_num = ($action_form != 'ci_save_blood') ? 'col2' : 'col4';
            ?>
            <div class="<?php echo $col_num ?>">
                <?php
                $rq1        = false;
                $card_link  = '';
                if (!empty($bat['ins_card_front']) && file_exists($card_dir['path'] . '/' . $bat['ins_card_front']) && filesize($card_dir['path'] . '/' . $bat['ins_card_front']) > 0) {
                    $imageurl = $card_dir['url'] . '/' . $bat['ins_card_front'] . '?r=' . time();
                    if (stripos($bat['ins_card_front'], '.pdf') > 0) {
                        $imageurl = '?tr_control=ci_pdf_image&action=view_card_image&mid=' . $bat['mid'] . '&btype=blood&field=ins_card_front&saved_id=' . $saved_id;
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
                        $imageurl = '?tr_control=ci_pdf_image&action=view_card_image&mid=' . $bat['mid'] . '&btype=blood&field=ins_card_back&saved_id=' . $saved_id;
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
                        $imageurl = '?tr_control=ci_pdf_image&action=view_card_image&mid=' . $bat['mid'] . '&btype=blood&field=patient_id_front&saved_id=' . $saved_id;
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
                        $imageurl = '?tr_control=ci_pdf_image&action=view_card_image&mid=' . $bat['mid'] . '&btype=blood&field=patient_id_back&saved_id=' . $saved_id;
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

        <?php
        if ($act == 'print') {
            include('box_signature.php');
        }

        if (!$billing_page || isset($_REQUEST['show_tests'])) { ?>
            <?php if (!empty($settings['show_ordered_tests_box']) && $act != 'print' && !$billing_page) { ?>
                <div class="frow">
                    <h4>Ordered Tests</h4>
                </div>
                <div class="frow">
                    <div id="ordered_tests_box_wrapper" class="ordered-tests-box">
                        <div id="ordered_tests_box" class="ordered-tests-box__content"></div>
                    </div>
                </div>
            <?php } ?>
            <div class="frow ">
                <h4>Blood Testing Requested</h4>
            </div>
            <div class="frow">
                <p>NOTE: Only tests that are medically necessary for the diagnosis or treatment of the patient may be
                    submitted for Medicare and / or Medicaid reimbursement. Documentation supporting medical necessity
                    must be present in the medical record.</p>
            </div>
            <div class="frow blabel printbreak">
                <input type="hidden" name="has_panel_requests" value="1" />

                <div id="default_requests" class="frow blabel panel_requests masonry" style="font-size:12px;column-count:<?php echo $settings['panel_column'] >= 2 ? $settings['panel_column'] : 5 ?>">
                    <?php
                    $tl = 0;
                    $mid                    = isset($mid) ? $mid : 0;
                    foreach (tr_pdd_get_profiles_tests_requesteds('blood', true, false, @$bat['doc']) as $group_id => $tests) {

                        $has_select_group   = false;
                        $has_checked        = false;
                        $group_class        = '';
                        $group_key          = 'GROUP_' . $group_id;
                        $in_group           = @in_array($group_key, $bat['panel_tests_requested']);
                        $group              = tr_pdd_get_panel_groups($group_id, 'blood');
                        if ($mid == 0 && isset($group['other_options']['auto_checked']) && $group['other_options']['auto_checked']) {
                            $in_group = true;
                        }
                        if (isset($_GET['debug1'])) {
                            var_dump($group);
                        }
                        if (!$group['show'] && !$in_group) continue;


                        if ($group['parent'] > 0) {
                            $group_class .= ' parent_group_' . $group['parent'] . ' ';
                        }
                        if (!empty($group['relate_to'])) {
                            $group_class .= ' relate_to_group_' . $group['relate_to'] . ' ';
                        }
                        if ($group['only_group']) {
                            $tests = array();
                            $group_class .= ' only_group ';
                        }


                        if ($in_group) {
                            $has_checked = true;
                        }
                        $group['color']         = trpdd_getContrastColor($group['bgcolor']);
                        $group['spec_bgcolor']  = strlen($group['bgcolor']) == 7 ? $group['bgcolor'] . '88' : $group['bgcolor'];
                        $group['box_bgcolor']   = strlen($group['bgcolor']) == 7 ? $group['bgcolor'] . '66' : $group['bgcolor'];
                        if ($act == 'print') {
                            $group['box_bgcolor'] = '#f8f8f8';
                            $group['spec_bgcolor'] = '#eeeeee';
                        }

                        $group_cb_class = ' batcustom_profile cb_test group_print_labels ';

                        if (!empty($group['cli_IDs'])) {
                            $group_class .= ' by_clinic ';
                            foreach (explode(',', $group['cli_IDs']) as $pcid) {
                                $group_class .= '  cli_id_' . $pcid . ' ';

                                if ($bat['cli_ID'] == $pcid) {
                                    $group_class .= ' cactive ';
                                }
                            }
                        }

                        if (!empty($group['not_cli_IDs'])) {
                            $group_class .= ' not_by_clinic ';
                            foreach (explode(',', $group['not_cli_IDs']) as $pcid) {
                                $group_class .= '  not_cli_id_' . $pcid . ' ';

                                if ($bat['cli_ID'] == $pcid) {
                                    $group_class .= ' notcactive ';
                                }
                            }
                        }

                        if ($is_hold) {
                            $group_cb_class .= ' readonly ';
                        }
                        if ($act == 'print' && $bat && $bat['mid'] > 0) {
                            ob_start();
                        }
                        $is_custom_panel = !empty($group['other_options']['panel_column']);
                    ?>
                        <div class="groupparent item <?php echo $group_class ?>" style="background:<?php echo $group['box_bgcolor'] ?>; <?php echo $is_custom_panel ? "column-span: all;width: 100%;margin-top: 16px;" : "" ?>">
                            <label class="block bold group_label" style="background:<?php echo $group['bgcolor'] ?>;color: <?php echo $group['color'] ?>">

                                <input type="checkbox" class="<?php echo $group_cb_class ?>" name="bat[panel_tests_requested][]" value="<?php echo $group_key ?>" data-relate_to="<?php echo $group['relate_to'] ?>" data-limit_days="<?php echo $group['limit_days'] ?>" <?php checked($in_group) ?> />

                                <?php echo $group['label'] ?>
                            </label>
                            <div class="frow <?php if ($is_custom_panel) echo 'is_custom_panel' ?>" style="<?php echo $is_custom_panel ? "column-count: " . $group['other_options']['panel_column'] . ";column-gap: 16px;" : "" ?>">
                                <?php
                                if (isset($group['other_options']['desc']) && !empty($group['other_options']['desc'])) {
                                    echo '<div class="panel_desc">' . $group['other_options']['desc'] . '</div>';
                                }
                                ?>
                                <?php
                                $subgroups = array();
                                foreach ($tests as $k => $r) {
                                    $txt = $r->te_name;
                                    $key = $r->te_ID;
                                    if (empty($key)) continue;

                                    $checked = @in_array($key, $bat['panel_tests_requested']);
                                    if ($checked) {
                                        $has_checked = true;
                                        $has_select_group = true;
                                    } else if ($act == 'print' && $bat && $bat['mid'] > 0) {
                                        continue;
                                    }

                                    $r->class .= ' cb_test ';
                                    if ($is_hold) {
                                        $r->class .= ' readonly ';
                                    }

                                    if ($r->tg_parent > 0 && !in_array($r->tg_ID, $subgroups) && $group_id != $r->tg_ID) {
                                        $subgroups[] = $r->tg_ID;
                                        $subgrouup_key = 'GROUP_' . $r->tg_ID;
                                        $in_subgroup =  @in_array($subgrouup_key, $bat['panel_tests_requested']);
                                        $cb_subgroup_class = '';
                                ?>
                                        <div style="font-weight: bold;padding-left:20px">
                                            <label>
                                                <?php
                                                $subgroup_cb_class = '';
                                                if ($r->tg_only_group && false) {
                                                    $subgroup_key          = 'GROUP_' . $r->tg_ID;
                                                    $in_subgroup           = @in_array($subgroup_key, $bat['panel_tests_requested']);
                                                ?>
                                                    <input type="checkbox" class="<?php echo $subgroup_cb_class ?>" name="bat[panel_tests_requested][]" value="<?php echo $subgroup_key ?>" <?php checked($in_subgroup) ?> />
                                                <?php
                                                } ?>
                                                <?php echo $r->main_group ?>
                                            </label>
                                        </div>
                                    <?php

                                    }
                                    if ($r->tg_only_group) {
                                        continue;
                                    }
                                    ?>
                                    <label class="c lcb panel_test_option<?php echo $checked ? ' panel-highlight-active' : '' ?>">
                                        <?php if (!$group['notestcheckbox']) { ?>
                                            <input type="checkbox" class="<?php echo $r->class ?>" data-te_type_id="<?php echo $r->te_type_id ?>" name="bat[panel_tests_requested][]" data-relations="<?php echo $r->te_relations ?>" value="<?php echo $key ?>" <?php checked($checked) ?> />
                                        <?php } ?>
                                        <span class="test-row">
                                            <?php if (!empty($group['other_options']['show_loinc']) && !empty($r->te_loinc_code)) { ?>
                                                <span class="loinc"><?php echo $r->te_loinc_code ?> - </span>
                                                <span class="test-name"><?php echo $txt ?></span>
                                            <?php } else { ?>
                                                <?php echo $txt ?>
                                            <?php } ?>
                                        </span>
                                    </label>
                                <?php
                                }

                                ?>
                            </div>
                        </div>
                    <?php
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
            </div>

            <?php if (isset($settings['e_orderable_test']) && $settings['e_orderable_test']) { ?>
                <div class="col3 tests_group ">
                    <label>
                        <input type="hidden" name="bat[orderable_test]" value="0" />
                        <input type="checkbox" id="orderable_test_cb" class="cb_test" name="bat[orderable_test]" <?php checked(isset($bat['orderable_test']) ? $bat['orderable_test'] : false) ?> value="1" />
                        Orderable Test
                    </label>
                </div>
            <?php } ?>

            <?php
            if (tr_get_option('ci_pdf_data', 'blood_medication')) {

                $bat['medication'] = explode('|', $bat['medication']);

                $mgroups = $wpdb->get_results("select mg_ID,mg_name
                                                from ci_medication_groups 
                                                where mg_btype='blood' 
                                                order by mg_name asc");

                ob_start();
                $has_medication = false;
                foreach ($mgroups as $mgroup) {
                    $medications = $wpdb->get_results("select * 
                                        from ci_medications 
                                        where me_group_id = {$mgroup->mg_ID}
                                        order by me_ID asc");
                    if (empty($medications)) {
                        continue;
                    }
                    $has_medication = true;
            ?>
                    <div class="col4 groupparent group_medication item ">
                        <label class="block bold group_label"><?php echo $mgroup->mg_name ?></label>
                        <div class="frow">
                            <?php
                            foreach ($medications as $medication) {
                                $low_name = strtolower($medication->me_name);
                                $medication->me_name = str_replace('Other.', 'Other', $medication->me_name);
                            ?>
                                <div class="meitem">
                                    <?php
                                    if ($low_name == 'other') {
                                        $other_vl = '';
                                        foreach ($bat['medication'] as $vl) {
                                            if (stripos($vl, 'other:') === 0) {
                                                $other_vl = str_ireplace('other:', '', $vl);
                                                break;
                                            }
                                        }
                                        echo '<label><input type="text" name="medication_other" placeholder="Other" value="' . $other_vl . '" ></label>';
                                    } else if ($low_name == 'other.') {
                                        $other_vl = '';
                                        foreach ($bat['medication'] as $vl) {
                                            if (stripos($vl, 'other2:') === 0) {
                                                $other_vl = str_ireplace('other2:', '', $vl);
                                                break;
                                            }
                                        }
                                        echo '<label><input type="text" name="medication_other2" placeholder="Other" value="' . $other_vl . '" ></label>';
                                    } else {
                                        $checked    = false;
                                        $me_value   = $medication->me_name;
                                        $me_start   = '';
                                        $me_end     = '';
                                        foreach ($bat['medication'] as $vl) {
                                            $arr = explode(';', $vl);
                                            $me_name = $arr[0];
                                            if ($me_name == $medication->me_name) {
                                                $checked = true;
                                                $me_start = isset($arr[1]) ? $arr[1] : '';
                                                $me_end = isset($arr[2]) ? $arr[2] : '';
                                                $me_value = $me_name . ';' . $me_start . ';' . $me_end;
                                                break;
                                            }
                                        }
                                    ?>
                                        <label>
                                            <input type="checkbox" name="bat[medication][]" data-key="<?php echo $medication->me_name ?>" value="<?php echo $me_value ?>" <?php checked($checked) ?> />
                                            <?php echo $medication->me_name ?>
                                        </label>
                                    <?php
                                        if ($settings['medication_date'] && stripos($mgroup->mg_name, 'Other Therapy') === false) {
                                            echo '<div class="daterange">';
                                            echo '<input type="text" class="hasdate mstart_date" value="' . $me_start . '" placeholder="Start Date"/>';
                                            echo '<input type="text" class="hasdate mend_date" value="' . $me_end . '" placeholder="End Date"/>';
                                            echo '</div>';
                                        }
                                    }
                                    ?>

                                </div>
                            <?php
                            }
                            ?>
                        </div>
                    </div>
                <?php
                }
                $medication_html = ob_get_clean();

                if ($has_medication) {
                ?>
                    <div class="frow">
                        <h4>Medications</h4>
                    </div>
                    <div class="frow blabel">
                        <?php echo $medication_html ?>
                    </div>
                    <input type="hidden" name="has_edit_medication" value="1" />
            <?php
                }
            }
        }

        include('questions.php');
        if ($act != 'print') {
            include('box_signature.php');
        }

        if (!tr_get_option('ci_pdf_data', 'hide_physicalnotes')) { ?>
            <div class="frow">
                <h4><?php echo tr_get_option('ci_pdf_data', 'cht_physicalnotes') ? 'Chief Complaint' : 'Notes' ?></h4>
            </div>
            <div class="frow">
                <textarea name="bat[notes]" <?php if ($act != 'print') echo 'placeholder="Please enter ' . (tr_get_option('ci_pdf_data', 'cht_physicalnotes') ? 'Chief Complaint' : 'Physician Notes') . '"' ?>><?php if (isset($bat['notes'])) echo @$bat['notes'] ?></textarea>
            </div>
        <?php } ?>

        <?php if (tr_get_option('ci_pdf_data', 'enable_patient_notes')) { ?>
            <div class="frow">
                <h4>PATIENT NOTES</h4>
            </div>
            <div class="frow">
                <textarea name="pa[pa_notes]" <?php if ($act != 'print') echo 'placeholder="Please enter Patient Notes"' ?>><?php if (isset($pa['pa_notes'])) echo $pa['pa_notes'] ?></textarea>
            </div>
        <?php } ?>

        <div class="frow dontprint">
            <h4>BILLING NOTES</h4>
        </div>
        <div class="frow dontprint">
            <textarea name="bat[billing_notes]" maxlength="200" <?php if ($act != 'print') echo 'placeholder="Please enter any Billing Notes"' ?>><?php if (isset($bat['billing_notes'])) echo @$bat['billing_notes'] ?></textarea>
        </div>

        <div class="frow dontprint">
            <h4>NOTES (visible on reports)</h4>
        </div>
        <div class="frow dontprint">
            <textarea name="bat[notes_report]"><?php if (isset($bat['notes_report'])) echo @$bat['notes_report'] ?></textarea>
        </div>
        <?php if ($billing_page) {
        ?>
            <div class="frow">
                <h4>Billing notes</h4>
            </div>
            <div class="frow">
                <textarea name="bat[billing_notes]" maxlength="200" <?php if ($act != 'print') echo 'placeholder="Please enter any Billing Notes"' ?>><?php if (isset($bat['billing_notes'])) echo @$bat['billing_notes'] ?></textarea>
            </div>
            <div class="frow">
                <h4>Upload</h4>
            </div>
            <div class="frow">
                <div class="col3">
                    <div class="req_result_pdf">
                        <?php if ($act != 'view') { ?>
                            <a class="button addfile" data-fname="original_reqs" href="#">Choose Original Req</a>
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
        } else {
        ?>
            <div class="frow dontprint">
                <div class="col2">
                    <label>Status</label>
                    <?php
                    $status_options = tr_pdd_get_batch_statuses_on_hold(tr_pdd_get_batch_statuses(false, false, false, 'blood'), $bat['batch_status'], $bat['mid'], $table_batch, 'blood');
                    $disabled_keys = tr_pdd_get_batch_statuses_disabled_keys('blood', 'show_in_req_form', $bat['batch_status']);
                    tr_select_option('bat[batch_status]', $status_options, @$bat['batch_status'], ['disabled_options' => $disabled_keys]);
                    ?>
                </div>
                <div class="col2">
                    <?php
                    //get reason hold
                    if ($is_hold) {
                        $hold_reason = $wpdb->get_var("select data 
                        from ci_log_users 
                        where user_action = 'hold_rs'
                        and mid = {$bat['mid']} and btype='{$btype}' 
                        order by id desc");
                        if (!empty($hold_reason)) {
                            $hold_reason_arr = json_decode($hold_reason, true);
                            if (is_array($hold_reason_arr)) {
                                $hold_reason = esc_html($hold_reason_arr['reason']);
                            } else {
                                $hold_reason = esc_html($hold_reason);
                            }
                            echo '<label>Hold Reason:</label> ' . $hold_reason;
                        }
                    }
                    ?>
                </div>

            </div>


            <?php if ($act != 'print') { ?>
                <div class="frow">
                    <h4>Original Req and Results Upload</h4>
                </div>
                <div class="frow">
                    <div class="col3">
                        <div class="req_result_pdf">
                            <?php if ($act != 'view') { ?>
                                <a class="button addfile">Choose Result PDF</a>
                            <?php } ?>
                            <input type="hidden" class="result_value" name="bat[result_pdf]" value="<?php echo isset($bat['result_pdf']) ? $bat['result_pdf'] : '' ?>" />
                            <ul class="listresultfiles">
                                <?php
                                if (!empty($bat['result_pdf'])) {
                                    $rs_files = explode(',', $bat['result_pdf']);
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
                </div>
        <?php
            }
        }

        ?>

        <?php
        if (!empty($action_form) && $act != 'print') {
        ?>

            <div class="frow">
                <h4>&nbsp;</h4>
            </div>
            <?php if (tr_get_option('ci_pdf_data', 'show_pid_field') && !$billing_page) { ?>
                <div class="frow">
                    <span class="ilabel">
                        <label style="display:inline-block;width:120px"><?php echo tr_get_option('ci_pdf_data', 'title_pid_field', 'PID') ?></label>
                        <input type="text" style="width:300px" name="bat[oldreq]" value="<?php echo isset($bat['oldreq']) ? $bat['oldreq'] : '' ?>" <?php if (tr_get_option('ci_pdf_data', 'oldreq_required')) echo 'required' ?> />
                    </span>
                </div>
            <?php } ?>
            <div class=" row" style="margin-bottom:15px;">

                <div class=" foutrow ilabel">
                    <?php if ($act != 'print' && $act != 'view' && !$billing_page) { ?>
                        <span id="show_assen">
                            <label>Accession Number</label>
                            <input type="text" id="number_assen" name="bat[assen]" value="<?php echo isset($bat['assen']) ? $bat['assen'] : '' ?>" required />
                            <label>Confirm</label>
                            <input type="text" id="confirm_assen" value="<?php echo isset($bat['assen']) ? $bat['assen'] : '' ?>" required />
                            <span class="confirm_status dashicons dashicons-yes"></span>
                            <a class="button print_assen_btn" <?php echo isset($print_times) && $print_times > 0 ? 'data-print_times="' . $print_times . '"' : '' ?> style="<?php if (empty($bat['assen']) || $saved_id > 0) echo 'display:none' ?>" href="#">Print Barcode</a>
                            <?php
                            if (isset($settings['print_assen_specimen']) && $settings['print_assen_specimen']) {
                                $rs = $wpdb->get_results("select ts_ID,ts_name from ci_test_specimens 
                                where ts_type='blood' ", ARRAY_A);
                                foreach ($rs as $r) {
                                    $r['ts_name'] = strtolower($r['ts_name']);
                                    $samtypes[$r['ts_ID']] = $r['ts_name'];
                                    if (stripos($r['ts_name'], 'serum') !== false) {
                                        $samtypes[0] = $r['ts_name'];
                                    }
                                }
                                echo '<script>';
                                echo 'print_assen_specimen=true;';
                                echo 'samtypes=' . json_encode($samtypes) . ';';
                                echo '</script>';
                            }
                            ?>
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
            if ($billing_page) {
                $where_exist = " assen = '{$bat['assen']}' ";
                if (!empty($bat['claim_id'])) {
                    $where_exist .= " or claim_no = '{$bat['claim_id']}' ";
                }

                $bb = $wpdb->get_row("select * from ci_batch_billings where {$where_exist} ", ARRAY_A);
                $bb['claim_date'] = date('Y-m-d', strtotime($bat['doc']));

            ?>
                <div class="frow">
                    <div class="col3">
                        <label>Claim ID</label>
                        <input type="text" name="bb[claim_no]" value="<?php echo !empty($bb['claim_no']) ? $bb['claim_no'] : $bat['claim_id'] ?>" />
                    </div>
                    <div class="col3">
                        <label>Claim Date</label>
                        <input type="text" data-write="1" name="bb[claim_date]" value="<?php echo $bb['claim_date'] ?>" readonly />
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

                    tr_bill_get_billing_code($ins1['ins_company'], $report['id'], $bat);
                    ?>
                </div>
                <?php
            } else {
                if (isset($settings['add_standing_order']) && $settings['add_standing_order'] && current_user_can('ci_standing_orders') && empty($mid)) {
                ?>
                    <div class="checkbox_box">
                        <div class="frow">
                            <label><input type="checkbox" name="add_standing_order" value="1" /> Add Standing Order</label>
                        </div>
                        <div class="frow show_by_checkbox">
                            <div class="col4">
                                <label>Date Range:</label>
                                <input type="text" class="hasdate" data-rq="true" name="standing_start" />
                            </div>
                            <div class="col4">
                                <label>to: </label>
                                <input type="text" class="hasdate" data-rq="true" name="standing_end" />
                            </div>
                            <div class="col3">
                                <label>Frequency</label><br>
                                <input type="number" data-rq="true" name="standing_frequency" style="width:50%" /> in days
                            </div>
                        </div>
                    </div>
                <?php
                }

                if (isset($settings['add_blood_pcr']) && $settings['add_blood_pcr'] && (!isset($by_type_id) || $by_type_id == 0)) {
                ?>
                    <div class="frow">
                        <label><input type="checkbox" name="add_new_type" value="pcr" /> Add Molecular Tests</label>
                        <br>
                        <br>
                    </div>
            <?php
                }
            }

            include(trpdd_get_file_path('global', 'end_req_form'));
            ?>

            <?php do_action('end_form_blood', $bat) ?>

            <div class="frow submitdiv">
                <input type="submit" class="button-primary" style="margin-right:150px" value="Submit">
                <input type="submit" class="button-primary" id="submit_print_btn" name="save_print" value="Print Barcode / Submit">
                <a class="iprint button bsty2 " href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_print_req', 'id' => $bat['mid'], 'type' => 'blood', 'tab' => '')) ?>">Print Form</a>
                <?php
                if (@$_REQUEST['page'] == 'ci_short_list') {
                ?>
                    <input type="hidden" name="bat[short_completed]" value="0">
                    <label><input type="checkbox" name="bat[short_completed]" value="1" <?php checked(@$bat['short_completed'], 1) ?>> Completed</label>
                <?php
                }
                if (!isset($mid) || $mid == 0 || $saved_id > 0) {
                ?>
                    <input type="submit" class="button only_save_form right" name="only_save_form" value="Save temporary">
                    <input type="hidden" name="saved_id" value="<?php echo $saved_id ?>" />
                <?php
                }
                ?>

                <input type="hidden" name="tr_action" value="<?php echo $action_form ?>" />
                <input type="hidden" name="bat[is_dna]" value="2" />
                <?php if (isset($mid) && $mid > 0) { ?>
                    <input type="hidden" name="bat[mid]" value="<?php echo $mid ?>" />
                <?php } ?>
            </div>
        <?php
        }
        ?>

        <input type="hidden" name="batch_tbl" value="<?php echo $table_batch ?>" />

        <?php trpdd_form_unique_id() ?>

    </form>
</div>

<?php if ($act == 'print') { ?>
    <script>
        window.print();
    </script>
<?php } else { ?>
    <script>
        var $onlyonepanel = <?php echo isset($settings['onlyonepanel']) ? intval($settings['onlyonepanel']) : 0 ?>;
        var $alwayshowprintlb = <?php echo !tr_current_role_in(array('office_manager', 'doctor')) ? 'true' : 'false'; ?>;
        var $t_assen = false;
        var $t_check_limit = false;
        var $changebychild = false;
        var $currentdate = '<?php echo date_i18n('Y-m-d') ?>';
        var $sign_dir = '<?php echo $sign_dir['url'] ?>';
        var $highlightSelectedTestsEnabled = <?php echo $highlight_selected_tests_enabled ? 'true' : 'false' ?>;
        var $showOrderedTestsBox = <?php echo !empty($settings['show_ordered_tests_box']) ? 'true' : 'false' ?>;

        jQuery(function($) {
            function trpdd_get_ordered_test_label($checkbox) {
                var $label = $checkbox.closest('label');
                var $testRow = $('.test-row', $label).first();
                if ($testRow.length) {
                    return $.trim($testRow.text());
                }

                var $labelClone = $label.clone();
                $labelClone.find('input').remove();
                return $.trim($labelClone.text());
            }

            function trpdd_render_ordered_tests_box() {
                var $wrapper = $('#ordered_tests_box_wrapper');
                var $target = $('#ordered_tests_box');
                var $orderedItems = [];

                if (!$showOrderedTestsBox || !$wrapper.length || !$target.length) {
                    return;
                }

                $target.empty();

                $('#default_requests > .groupparent').each(function() {
                    var $sourceGroup = $(this);
                    var $groupCheckbox = $('.group_label input.cb_test', $sourceGroup).first();
                    var $selectedTests = $('.c input.cb_test:checked', $sourceGroup);
                    var $groupTitle = $.trim($('.group_label', $sourceGroup).clone().children().remove().end().text());

                    if (!$groupCheckbox.is(':checked') && !$selectedTests.length) {
                        return;
                    }

                    if ($selectedTests.length) {
                        $selectedTests.each(function() {
                            $orderedItems.push(trpdd_get_ordered_test_label($(this)));
                        });
                    } else {
                        $orderedItems.push($groupTitle);
                    }
                });

                $.each($orderedItems, function($index, $label) {
                    var $item = $('<label class="ordered-tests-box__item"></label>');
                    $item.text($label);
                    $target.append($item);
                });

                if (!$orderedItems.length) {
                    $target.append('<div class="ordered-tests-box__empty">No tests selected.</div>');
                }
            }

            function trpdd_update_selected_test_highlights() {
                var $root = $('#default_requests');
                var $enabled = !!$highlightSelectedTestsEnabled;

                if (!$root.length) {
                    return;
                }

                $root.toggleClass('highlight-selected-tests-enabled', $enabled);

                $('.panel_test_option', $root).each(function() {
                    var $label = $(this);
                    var $checkbox = $('input.cb_test', $label).first();
                    $label.toggleClass('panel-highlight-active', $enabled && $checkbox.length && $checkbox.is(':checked'));
                });

                trpdd_render_ordered_tests_box();
            }

            function change_clinic_printbtn() {

                var $show_pbtn = $('select[name="bat[cli_ID]"]').length > 0 ? $('select[name="bat[cli_ID]"] option:selected') : $('[name="bat[cli_ID]"]');

                if ($alwayshowprintlb || $show_pbtn.data('pbtn') == '1') {
                    $('#submit_print_btn').show();
                } else {
                    $('#submit_print_btn').hide();
                }
            }

            function change_patient_insu_required($rq) {
                console.log($rq);
                if ($rq) {
                    $('.patient_block .has_required').attr('required', true);
                    $('.insurance_block1 .has_required').attr('required', true);
                } else {
                    $('.patient_block :required, .insurance_block1 :required').each(function() {
                        $(this).addClass('has_required');
                        $(this).removeAttr('required');
                        $(this).removeAttr('aria-required');
                    })
                }
            }

            function trpdd_check_limit_days($silent) {
                var $this = $(this);
                if ($t_check_limit) {
                    clearTimeout($t_check_limit);
                }
                $t_check_limit = setTimeout(function() {
                    var $mid = $('[name="bat[mid]"]').val();
                    var $pa_id = $('[name="pa[pa_ID]"]').val();
                    var $group_limits = [];
                    $('input.batcustom_profile:checked').each(function() {
                        var $limit_days = $(this).data('limit_days');
                        if ($limit_days > 0) {
                            $group_limits.push($(this).val());
                        }
                    })
                    if ($mid > 0 || !$pa_id || $group_limits.length == 0) {
                        return;
                    }

                    $('[type="submit"]').attr('disabled', 'disabled');
                    $.ajax({
                        data: {
                            tr_action: 'ci_check_number_assen',
                            act: 'check_limit_days',
                            mid: $mid,
                            pa_id: $pa_id,
                            group_limits: $group_limits,
                            btype: 'blood'
                        },
                        type: 'post',
                        dataType: 'json',
                        success: function(rs) {
                            $t_check_limit = false;

                            if (rs.status == 'limit') {
                                if (!$silent) {
                                    alert(rs.msg);
                                }

                                for (i in rs.groups) {
                                    $('input.batcustom_profile[value="GROUP_' + rs.groups[i] + '"]').prop('checked', false).change();
                                }
                            } else if (rs.status == 'limit_ask') {
                                if ($silent || !confirm(rs.msg)) {
                                    for (i in rs.groups) {
                                        $('input.batcustom_profile[value="GROUP_' + rs.groups[i] + '"]').prop('checked', false).change();
                                    }
                                }
                            }

                            $('[type="submit"]').removeAttr('disabled');
                        },
                        error: function() {
                            setTimeout(function() {
                                $t_check_limit = false;
                                $this.change();
                            }, 1000);
                        }
                    })
                }, 500);
            }

            change_clinic_printbtn();
            trpdd_render_ordered_tests_box();


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
                                btype: 'blood'
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
                .on('change', '[name="bat[lab_id]"]', function() {
                    var $lab = $('option[value="' + $(this).val() + '"]', $(this)).data('short');
                    $('#lab_name_change_div').html($lab);
                    trpdd_update_selected_test_highlights();
                })
                .on('click', 'input.group_checkbox', function() {
                    if ($(this).hasClass('readonly')) {
                        return false;
                    }
                    var $pr = $(this).parents('.tests_group');
                    if ($(this).is(":checked")) {
                        $('input[type="checkbox"]', $pr).prop('checked', true);
                    } else {
                        $('input[type="checkbox"]', $pr).removeAttr('checked');
                    }
                })
                .on('change', '.batcustom_profile', function() {
                    if ($(this).hasClass('readonly')) {
                        return false;
                    }
                    var $vl = $(this).val();
                    var $pr = $(this).parents('.groupparent');
                    var $limit_days = $(this).data('limit_days');

                    if ($onlyonepanel && $(this).is(":checked")) {
                        var $has_other_panel = false;
                        $('.batcustom_profile:checked').each(function() {
                            if ($(this).val() != $vl) {
                                $has_other_panel = true;
                            }
                        })
                        if ($has_other_panel) {
                            alert('Only one panel can be selected');
                            $(this).prop('checked', false);
                            $('input.cb_test', $pr).prop('checked', false);
                            return false;
                        }
                    }
                    var $rl_ids = $(this).data('relate_to');
                    var $rlids = ($rl_ids === undefined || $rl_ids === null || $rl_ids === '') ? [] : String($rl_ids).split(',').map(function($id) {
                        return $.trim(String($id));
                    }).filter(function($id) {
                        return $id !== '';
                    });
                    if ($(this).is(":checked")) {
                        if (!$changebychild) {
                            $('.c input', $pr).prop('checked', true);
                        }
                        if ($limit_days > 0) {
                            console.log($limit_days);
                            //check days
                            trpdd_check_limit_days();
                        }

                        if ($rlids.length > 0) {
                            for (var i = 0; i < $rlids.length; i++) {
                                var $rl = $('.groupparent .group_label input[value="GROUP_' + $rlids[i] + '"]');
                                if (!$rl.length || $rl.is(':checked')) {
                                    continue;
                                }
                                $rl.data('byrelated', true);
                                $rl.prop('checked', true).change();
                            }
                        }

                    } else {
                        if (!$changebychild) {
                            $('.c input', $pr).removeAttr('checked');
                        }
                        if ($rlids.length > 0) {
                            for (var j = 0; j < $rlids.length; j++) {
                                var $rl_uncheck = $('.groupparent .group_label input[value="GROUP_' + $rlids[j] + '"]');
                                if (!$rl_uncheck.length || !$rl_uncheck.is(':checked')) {
                                    continue;
                                }
                                $rl_uncheck.prop('checked', false).change();
                            }
                        }
                    }

                    $changebychild = false;
                    trpdd_update_selected_test_highlights();

                })
                .on('click', 'a.remove_tests', function() {
                    $(this).parents('li').remove();
                })
                .on('submit', '#requisition_form', function() {
                    if ($('input.cb_test').length > 0 && $('input.cb_test:checked').length == 0 && !$('#requisition_form').hasClass('only_save_form')) {
                        alert('You must select least 1 test');
                        return false;
                    }
                })
                .on('change', '[name="bat[cli_ID]"]', function() {
                    var $cli_id = $(this).val();
                    var $cli_opt = $('[name="bat[cli_ID]"] option:selected');
                    $('.requesting_physician_last_auto').autocomplete('option', 'source', ajaxurl + '?action=ajax&autocomplete=requesting_patients_last_auto&cli_id=' + $cli_id);
                    change_clinic_printbtn();
                    if ($('.nh_address').is(':checked') && $(this).val() != '') {
                        $('[name="pa[pa_address]"]').val($cli_opt.data('address'));
                        $('[name="pa[pa_city]"]').val($cli_opt.data('city'));
                        $('[name="pa[pa_state]"]').val($cli_opt.data('state')).change();
                        $('[name="pa[pa_zip]"]').val($cli_opt.data('zip'));

                        if ($('[name="pa[pa_phone]"]').val() == '' && $cli_opt.data('phone')) {
                            $('[name="pa[pa_phone]"]').val($cli_opt.data('phone'));
                        }
                    }

                    <?php if (tr_get_option('ci_pdf_data', 'show_cbaa_clinic')) { ?>
                        if ($('#number_assen').val() == '') {
                            if ($('option:selected', $(this)).data('autoa')) {
                                $('[name="auto_generated"]').prop('checked', true);
                                $('#number_assen,#confirm_assen').removeAttr('required').attr('readonly', '1');
                            } else {
                                $('[name="auto_generated"]').prop('checked', false);
                                $('#number_assen,#confirm_assen').attr('required', 'required').removeAttr('readonly');
                            }
                        }
                    <?php } ?>
                    trpdd_update_selected_test_highlights();
                })
                .on('change', '[name="bat[doc_ID]"]', function() {

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
                })
                .on('click', '[name="auto_generated"]', function() {
                    if ($(this).is(':checked')) {
                        $('#number_assen,#confirm_assen').removeAttr('required').val('').attr('readonly', '1');
                    } else {
                        $('#number_assen,#confirm_assen').attr('required', 'required').removeAttr('readonly');
                    }
                })
                .on('change', '[name="bat[assen]"]', function() {
                    if ($(this).val() != '') {
                        $('#show_auto_gennerated').hide();
                    } else {
                        $('#show_auto_gennerated').show();
                    }
                })
                .on('change', '#attach_face_sheet, #attach_demographics', function() {
                    var $has_vl = $('#attach_face_sheet').val() != '' || $('#attach_demographics').val() != '';
                    if ($has_vl) {
                        change_patient_insu_required(false);
                    } else {
                        change_patient_insu_required(true);
                    }
                })
                .on('click change', '.groupparent .c input.cb_test', function() {
                    if ($(this).hasClass('readonly')) {
                        return false;
                    }
                    var $pr = $(this).parents('.groupparent');
                    var $prcb = $('.group_label input', $pr);
                    var $relations = $(this).data('relations');
                    if ($(this).is(':checked')) {
                        $changebychild = true;
                        $prcb.prop('checked', true).change();

                        if ($relations) {
                            $relations = $relations.split(',');
                            for (i in $relations) {
                                var $rlid = $relations[i];
                                $('.c input.cb_test[value="' + $rlid + '"]', $pr).prop('checked', true);
                            }
                        }
                    } else {
                        if ($('.c input.cb_test:checked', $pr).length == 0) {
                            $prcb.prop('checked', false).change();
                        }
                    }
                    trpdd_update_selected_test_highlights();
                })
                .on('change', 'select#col_id_sel', function() {
                    var $phone = $('option[value="' + $(this).val() + '"]', $(this)).data('phone');
                    var $phlebotomist = $('option[value="' + $(this).val() + '"]', $(this)).data('phlebotomist');
                    $('[name="col[col_phone]"]').val($phone);
                    $('[name="col[col_phlebotomist_code]"]').val($phlebotomist);
                })
                .on('found_patient', 'a.find_patient', function(e, data) {
                    if (data['last_tests']) {
                        var $tests = data['last_tests'].split(',');
                        for (var i in $tests) {
                            var $t = $tests[i];
                            $('input.cb_test[value="' + $t + '"]').prop('checked', true);
                        }
                        $('.batcustom_profile:checked').each(function() {
                            if ($(this).data('limit_days') > 0) {
                                trpdd_check_limit_days(true);
                            }

                        })
                    }
                    if (data['medication']) {
                        var $medications = data['medication'].split('|');
                        for (var i in $medications) {
                            var $marr = $medications[i].split(';');
                            var $t = $marr[0];
                            var $cb = $('input[name="bat[medication][]"][data-key="' + $t + '"]');
                            if ($cb.length > 0) {
                                var $startd = $marr[1] ? $marr[1] : '';
                                var $endd = $marr[2] ? $marr[2] : '';
                                if ($endd != '' && $endd < $currentdate) {
                                    continue;
                                }
                                $cb.prop('checked', true).change();
                                var $pr = $cb.parents('.meitem');
                                $('input.mstart_date', $pr).val($startd).change();
                                $('input.mend_date', $pr).val($endd).change();

                            } else if ($t.indexOf('other2:') > -1) {
                                var $newt = $t.replace("other2:", '');
                                $('[name="medication_other2"]').val($newt);
                            } else if ($t.indexOf('other:') > -1) {
                                var $newt = $t.replace("other:", '');
                                $('[name="medication_other"]').val($newt);
                            }

                        }
                    }
                    trpdd_update_selected_test_highlights();
                })
                .on('keyup', '#confirm_assen,[name="bat[assen]"]', function() {
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
                .on('change', '.meitem input[type="checkbox"]', function() {
                    var $pr = $(this).parents('.meitem');
                    var $startf = $('input.mstart_date', $pr);
                    if ($(this).is(':checked')) {
                        $pr.addClass('active');

                        if ($startf) {
                            //$startf.prop('required', true);
                            if ($startf.val() == '') {
                                //$startf.val($currentdate).change();
                            }
                        }

                    } else {
                        $pr.removeClass('active');
                        if ($startf) {
                            // $startf.prop('required', false);
                        }
                    }
                })
                .on('change', '.meitem .daterange input', function() {
                    var $pr = $(this).parents('.meitem');
                    var $cb = $('input[type="checkbox"]', $pr);
                    var $startd = $('input.mstart_date', $pr).val();
                    var $endd = $('input.mend_date', $pr).val();
                    var $arr = $cb.val().split(';');
                    var $newvl = $arr[0] + ';' + $startd + ';' + $endd;
                    $cb.val($newvl);
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
                }).on('change', '[name="bat[cli_ID]"]', function() {
                    var $cli_id = $(this).val();
                    $('.groupparent.by_clinic').removeClass('cactive');
                    $('.groupparent.by_clinic.cli_id_' + $cli_id).addClass('cactive');

                    $('.groupparent.not_by_clinic').removeClass('notcactive');
                    $('.groupparent.not_by_clinic.not_cli_id_' + $cli_id).addClass('notcactive');
                    $('.groupparent.not_by_clinic.not_cli_id_' + $cli_id + ' input[type="checkbox"]').prop('checked', false);
                    trpdd_update_selected_test_highlights();
                })


            $('.requesting_physician_last_auto').autocomplete({
                source: ajaxurl + '?action=ajax&autocomplete=requesting_patients_last_auto',
                minLength: 1,
                search: function(event, ui) {
                    var $cli_id = $('[name="bat[cli_ID]"]').val();
                    console.log($cli_id);
                    if ($cli_id == 0) {
                        return false;
                    } else {
                        $('.requesting_physician_last_auto').autocomplete('option', 'source', ajaxurl + '?action=ajax&autocomplete=requesting_patients_last_auto&cli_id=' + $cli_id);
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
                                    $('span', $pr).html('<div><img src="/"><' + ui.item.card_url + '/' + vl + 'div>');
                                }
                            }
                        }
                        try {
                            if (ui.item.diagnostic) {
                                var $codes = ui.item.diagnostic.split(',');
                                var $list = $('#list_dagnostic_codes');
                                $list.empty();
                                for (var i in $codes) {
                                    var $code = $.trim($codes[i]);
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

            if ($('[name="auto_generated"]').is(":checked")) {
                $('#number_assen,#confirm_assen').removeAttr('required').val('').attr('readonly', '1');

            }
            $('[name="bat[doc_ID]"]').change();
            $('#attach_face_sheet').change();
            $('.meitem input[type="checkbox"]').change();
            trpdd_update_selected_test_highlights();
        })
    </script>
<?php } ?>
<?php
if (isset($_GET['debug'])) {
    $content = ob_get_clean();
    $content = str_replace('required', 'datarequired', $content);
    echo $content;
}
?>
