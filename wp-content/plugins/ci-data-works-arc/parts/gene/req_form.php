<?php
global $wpdb;
global $current_role, $form_error;

$by_type_id             = 'gene';
$table_batch            = 'ci_batches_gene';
$table_report           = 'ci_reports_gene';
$table_results          = 'ci_results_gene';

$mid                    = isset($mid) ? $mid : 0;
$act                    = isset($_REQUEST['act']) ? $_REQUEST['act'] : @$act;
$id                     = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
$user_id                = get_current_user_id();

$can_edit               = false;
$notallowchangepatient  = false;

$current_clinic_id      = 0;
$collectors             = array();
$can_view_ref_lab       = false;
$report                 = array();
$current_doctor_sign     = '';

$billing_page           = isset($billing_page) ? $billing_page : false;

$tabs = array(
    '' => 'Genetics Requisition Form',
    'completed_req' => 'Completed Req Forms',
    //'req_hold' => 'Reqs On Hold',
    'saved_form' => 'Saved'
);


if (!tr_get_option('ci_pdf_data', 'req_show_hold')) {
    unset($tabs['req_hold']);
}


$saved_id               = 0;

$settings               = get_option('ci_req_settings_gene', array());
$show_npi               = tr_get_option('ci_pdf_data', 'req_show_npi');
if (!isset($settings['disable_auto_doc'])) {
    $settings['disable_auto_doc'] = false;
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


            trpdd_log_user(get_current_user_id(), 'approve_rs', $report['assen'], $report['mid'], 'gene', '', '', $report['assen']);

            trpdd_trigger_approve_report($report, 'gene');

            trpdd_add_cron_after_approve('gene');
        }
    }
    wp_send_json($return);
}


$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);
if (isset($_REQUEST['trajax'])) {
    $hide_tabs = true;
}
if (!isset($hide_tabs) || $hide_tabs === false) {

    if ($act == 'print') {
?>
        <link href="<?php echo TRCPDD_URL ?>css/print.min.css?<?php echo TRCPDD_VS ?>" media="all" type="text/css" rel='stylesheet' />
    <?php
    } else if (@$_REQUEST['page'] == 'ci_short_list') {
    } else if (!isset($_REQUEST['not_show_tab'])) {

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
$doctors        = array();

$conditions     = trpdd_get_batch_conditions(0, 'gene');
$where_cli      = $conditions['where_clinic'];
$where_doctor   = '';
$where_lab      = '';
$where_ref_lab  = '';
$cli_id         = 0;

if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $where_lab .= " and lab_ID in ( {$lab_str} ) ";
}


if ($current_role == 'lab_rep') {
    $rep_id = get_user_meta(get_current_user_id(), '_rep_id', true);
    $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);
    $reps = array($rep_id);
    $members = tr_dpp_get_users_in_group(get_current_user_id());
    foreach ($members as $uid) {
        $reps[] = get_user_meta($uid, '_rep_id', true);
    }
    $reps = implode(',', $reps);
    $where_cli .= " and cli.`cli_rep-ID` in ($reps) ";
} else if ($current_role == 'collector') {
    $col_id = get_user_meta(get_current_user_id(), '_collector_id', true);
    $collector = $wpdb->get_row("select * from ci_collector where col_ID ={$col_id}", ARRAY_A);
    $rep_id = $collector['rep_ID'];
    //$where_cli = " and cli.`cli_rep-ID`={$rep_id} ";
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

    $where_cli .= " and (cli_distributor_ID={$dis_id} or (`rep_marketing_group_ID` IN ({$g_ids}) or rep_distributor_ID={$dis_id})) ";
    $where_group .= " and gr_distributor_ID = {$dis_id} ";
} else if ($current_role == 'office_manager' || $current_role == 'clinic') {
    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where_cli .= " and cli.cID = {$current_clinic} ";
    } else {
        if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
            $cid = get_user_meta($user_id, '_clinic_id', true);
            $cli_ids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
            $cli_ids = implode(',', $cli_ids);

            $where_cli .= " and cli.cID in ( {$cli_ids} )";
        }
    }
} else if ($current_role == 'doctor') {
    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where .= " and ba.cli_ID = {$current_clinic} ";
    }
    $doc_id = get_user_meta($user_id, '_doc_id', true);
    $clinics = $wpdb->get_col("select cli_ID from ci_clinic_doc where doc_ID={$doc_id} ");
    $clinics = implode(',', $clinics);
    if (empty($clinics)) {
        $clinics = '0';
    }
    $where_cli .= " and cli.cID in ({$clinics}) ";
    $where_doctor .= " and doc.doc_ID = {$doc_id} ";
} else if ($current_role == 'ref_lab_tech') {
    $ref_lab_id = get_user_meta($user_id, '_ref_lab_id', true);
    $where_ref_lab = " and lab_ID = {$ref_lab_id} ";
}


$required_sign = true;
if (tr_current_role_in(array('administrator', 'administrator_p', 'AdminCIDW', 'labsecman', 'leadlabsecman', 'labmanager'))) {
    $required_sign = false;
    //$can_view_ref_lab = true;
}


if ($tab == 'completed_req') {
    include(trpdd_get_file_path('gene', 'req_list'));
    return;
} else if ($tab == 'saved_form') {
    if (!empty($act) && $act != 'delete') {
        $saved_id = $_REQUEST['id'];
    } else {
        include(trpdd_get_file_path('gene', 'req_saved'));
        return;
    }
} else if (!empty($tab)) {
    $tabfile = trpdd_get_file_path($btype, $tab);
    if (file_exists($tabfile)) {
        include($tabfile);
        return;
    }
}

$assen_readonly = false;

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
        $bat        = $wpdb->get_row("select * from {$batch_other_tbl} where mid='{$by_type_id}' ", ARRAY_A);
        $can_edit = true;
        $bat['assen'] = '';
        $bat['panel_tests_requested'] = '';
    } else {
        $bat = $wpdb->get_row("select * from {$table_batch} where mid='{$id}' ", ARRAY_A);
    }
    $cli['cID'] = $bat['cli_ID'];
    $cli_id     = $cli['cID'];
    $cli        = $wpdb->get_row("select * from ci_clinic where `cID`={$cli_id}", ARRAY_A);

    $pa         = $wpdb->get_row("select pa.* from ci_patients pa
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


    if ((!$ins1 || empty($ins1['ins_firs'])) && $bat['insurance'] == 'No Insurance - Cash') {
        $ins1['ins_first'] = $pa['pa_first'];
        $ins1['ins_last'] = $pa['pa_last'];
        $ins1['ins_relation'] = 'Self';
    }
    if ($ins1['ins_cares_act'] && $bat['insurance'] == 'No Insurance - Cash') {
        $bat['insurance'] = '';
    }

    $collector = $wpdb->get_row("select * from ci_collector where col_ID ={$bat['col_ID']}", ARRAY_A);

    $bat['panel_tests_requested_str'] = $bat['panel_tests_requested'];
    $bat['panel_tests_requested'] = explode(',', @$bat['panel_tests_requested']);
    $bat['panel_tests_requested'] = array_map('trim', $bat['panel_tests_requested']);
    $bat['panel_tests_requested'] = array_map('strtoupper', $bat['panel_tests_requested']);
    $bat['btype'] = 'gene';


    $bat['current_medication'] = explode(',', @$bat['current_medication']);
    $bat['current_medication'] = array_map('trim', $bat['current_medication']);
    $bat['current_medication'] = array_values(array_unique(array_filter($bat['current_medication'])));
    $bat['current_medication_other'] = explode(',', @$bat['current_medication_other']);
    if (count($bat['current_medication_other']) == 1 && trim($bat['current_medication_other'][0]) == '') {
        $bat['current_medication_other'] = array();
    }
    $current_medications = tr_pdd_get_current_medications('gene');
    foreach ($bat['current_medication'] as $m) {
        if ($m == 'Fioricet' || $m == 'Lortab') continue;
        if (!in_array($m, $current_medications) && !empty($m) && $m != 'None Listed') {
            $bat['current_medication_other'][] = $m;
        }
    }
    $bat['current_medication_other'] = array_values(array_unique(array_filter($bat['current_medication_other'])));

    if (substr($bat['doc'], 0, 4) == '1970') {
        $bat['doc'] = date_i18n('Y-m-d H:i:s');
    }



    $batch_key = $bat['assen'];

    if ($billing_page) {

        $sql = "SELECT
            lab.*,
            ba.*,
            ba.patient_id as pa_ID,
            rl.lab_additional as rl_lab_additional,
            re.*
        FROM ci_reports_gene re
    
        JOIN {$table_batch} ba ON  ba.assen = re.patient_id
    
        JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
        LEFT JOIN ci_labs rl ON rl.lab_ID = ba.ref_lab_ID
        WHERE ba.mid='" . $id . "' group by ba.mid limit 1";

        $report = $wpdb->get_row($sql, ARRAY_A);
    }


    if ($act == 'edit') {
        $can_edit = true;
        $mid = $id;
    }

    $barcode_key = tr_get_option('ci_pdf_data', 'barcode_key');
    if (!empty($bat['assen']) && !empty($barcode_key) && stripos($bat['assen'], $barcode_key) === 0) {
        $assen_readonly = true;
    }

    $notallowchangepatient = tr_get_option('ci_pdf_data', 'disable_c_patient');
    $notallowchangepatientdob = tr_get_option('ci_pdf_data', 'disable_c_patientdob');
} else {
    $notallowchangepatient      = false;
    $notallowchangepatientdob   = false;
    $bat['panel_tests_requested'] = array();
    $bat['custom_profile']      = '';
    $bat['medication']          = array();
    $bat['current_medication']  = array();
    $bat['poc_perform']         = '0';
    $bat['doc']                 = '';
    $bat['type_of_specimen']    = '';
    $bat['patient_signature']   = '';
    $bat['batch_status']   = '';
    $bat['p_condition']   = '';

    $pa                         = array();

    $can_edit = true;

    if ($form_error) {
        $bat = $_POST['bat'];
    }
    $bat['lab_id'] = 1;

    if ($cli_id == 0 && $current_clinic_id > 0) {
        $cli_id = $bat['cli_ID'] = $cli['cID'] = $current_clinic_id;
    }

    $ins1 = array(
        'ins_relation' => 'Self',
        'ins_company' => 'N/A',
        //'ins_email' => 'N/A',
        'ins_address' => 'N/A',
        'ins_city' => 'N/A',
        'ins_state' => 'N/A',
        'ins_zip' => 'N/A',
        'ins_member_id' => 'N/A',
    );

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


if (empty($bat['ins_comp'])) {
    $bat['ins_comp'] = 'ins';
}
$card_dir = tr_get_ins_card_dir();
$sign_dir = tr_get_signature_dir();

$sql_clinic = "select cli.* from ci_clinic cli
                    Left JOIn ci_clinic_collector cc ON cc.cli_ID = cli.cID
                    left join ci_lab_reps lr on cli.`cli_rep-ID` = lr.rep_ID
                    where {$where_cli} group by cli.cID order by cli.cli_clinic";

if (isset($_GET['debug'])) {
    echo '<br>' . $sql_clinic . '<br>';
}
$clinics = $wpdb->get_results($sql_clinic, ARRAY_A);
if (count($clinics) == 1) {
    $cli = $clinics[0];
    $cli_id = $bat['cli_ID'] = $cli['cID'];
}

if ($cli_id > 0) {
    $bat['col_ID'] = isset($bat['col_ID']) ? $bat['col_ID'] : 0;

    $doctors = $wpdb->get_results("select doc.* 
                    from ci_doc doc
                        JOIn ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
                        where cd.cli_ID = {$cli_id}  {$where_doctor}
                        order by doc.doc_last,doc.doc_first asc", ARRAY_A);


    if ($current_role != 'collector') {
        $collectors = $wpdb->get_results("select col.* from ci_collector col
                                JOIN ci_clinic_collector cc ON cc.col_ID = col.col_ID
                                where cc.cli_ID ={$cli_id} and (col.col_status=1 or col.col_ID = '{$bat['col_ID']}')", ARRAY_A);
    }
}

if (isset($bat['lab_ID']) && $bat['lab_ID'] > 0) {
    $current_lab = $wpdb->get_row("select * from ci_labs Where lab_ID = {$bat['lab_ID']}", ARRAY_A);
} else {
    $current_lab = CI_Base_Lab::get_current_lab();
}

$las = $wpdb->get_results("select * from ci_labs  where (lab_is_ref=0 or lab_is_ref=2) {$where_lab} order by lab_dba");
$ref_labs = $wpdb->get_results("select * from ci_labs  
                where (lab_is_ref=1 or lab_is_ref=2) {$where_ref_lab} 
                order by lab_dba");


if (isset($_REQUEST['debug'])) {
    var_dump($sql_clinic);
    echo '<br><br>$current_lab:<br>';
    var_dump($current_lab);
    echo '<br><br>reflabs:<br>';
    var_dump($ref_labs);
    echo '<br><br>$cli:<br>';
    var_dump($cli);
}
?>
<div class="wrap_form wrap">

    <form method="post" enctype="multipart/form-data" class="need_validate main_form <?php if (!$bat || !isset($bat['mid']) || !$bat['mid']) echo 'emptyform' ?>" id="requisition_form">
        <div style="<?php if (isset($print_canvas)) echo 'min-height:1056px' ?>">

            <?php if ($act == 'print') include(trpdd_get_file_path('global', 'print_req_header')); ?>

            <h2 class="center">Genetics Requisition Form</h2>
            <?php
            if ($act != 'print') {
                Tr_Session_Class::show();
            }
            ?>

            <?php
            if ($act == 'view' || $billing_page) {  ?>
                <div class="right foutrow">
                    <label>Accession Number:</label>
                    <span class="text bold binline" style="width:200px"><?php echo isset($bat['assen']) ? $bat['assen'] : '' ?>&nbsp;&nbsp;</span>
                </div>
            <?php } ?>

            <div class="frow blabel hlabel">
                <div class="col4">
                    <?php
                    if (count($las) > 1) {
                        $labs = array('' => 'Select Lab');
                        $only_labids = array();
                        if (count($las) > 1 && tr_current_role_in(array('office_manager', 'doctor'))) {
                            foreach ($clinics as $clinic) {
                                $only_labids[] = $clinic['cli_lab-ID'];
                            }
                        }
                        if (isset($mid) && $mid > 0) {
                            $only_labids[] = $bat['lab_id'];
                        }
                        $one_lab_id = 0;
                        foreach ($las as $l) {
                            if (count($only_labids) > 0 && !in_array($l->lab_ID, $only_labids)) {
                                continue;
                            }
                            $labs[$l->lab_ID] = $l->lab_dba;
                            $one_lab_id = $l->lab_ID;
                        }
                        if (!isset($mid) || empty($mid)) {
                            if (count($labs) == 2) {
                                $bat['lab_id'] = $one_lab_id;
                            } else if (empty($bat['lab_id'])) {
                                $bat['lab_id'] = CI_Base_Lab::get_current_lab_id();
                            }
                        }
                    ?>
                        <label>Lab</label>
                        <?php tr_select_option('bat[lab_id]', $labs, $bat['lab_id'], array('attr' => 'required')) ?>
                        <?php
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
                            if (is_array($cli) && $cli['cli_ref_lab_ID'] > 0) {
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
                        $addrs .= ' data-reason="' . $cli['cli_show_reason_testing'] . '" ';
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
                                $selected = $cli && $cli['cID'] == $clinic['cID'] ? 'selected' : '';
                                $addrs = ' data-address="' . $clinic['cli_street'] . '" ';
                                $addrs .= ' data-city="' . $clinic['cli_city'] . '" ';
                                $addrs .= ' data-state="' . $clinic['cli_state'] . '" ';
                                $addrs .= ' data-zip="' . $clinic['cli_zip'] . '" ';
                                $addrs .= ' data-phone="' . $clinic['cli_om-phone'] . '" ';
                                $addrs .= ' data-print2="' . $clinic['cli_print_2labels'] . '" ';
                                $addrs .= ' data-pbtn="' . $clinic['cli_show_submit_print_bc'] . '" ';
                                $addrs .= ' data-autoa="' . $clinic['cli_auto_assen'] . '" ';
                                $addrs .= ' data-reason="' . $clinic['cli_show_reason_testing'] . '" ';
                                $addrs .= ' data-reflab="' . $clinic['cli_ref_lab_ID'] . '" ';

                                echo '<option value="' . $clinic['cID'] . '" ' . $selected . ' ' . $addrs . '>' . $clinic['cli_clinic'] . '</option>';
                            }
                            ?>
                        </select>
                    <?php } ?>

                    <?php if (tr_get_option('ci_pdf_data', 'show_priority_comreqs')) { ?>
                        <div class="frow">
                            <label>Priority</label>
                            <?php
                            tr_select_option('bat[priority_hours]', array(
                                '0' => 'None',
                                '12' => '12 hrs',
                                '24' => '24 hrs',
                                '48' => '48 hrs',
                                '72' => '72 hrs',
                                '120' => '5 days',
                                '168' => '7 days',
                                '-1' => 'Regular',
                                '-2' => 'STAT',
                            ), @$bat['priority_hours']) ?>
                        </div>
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
                            foreach ((array)$doctors as $d) {
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
                    <label>Collector Name</label>
                    <?php if (($collectors && count($collectors) > 0 && $current_role != 'collector')
                        && !tr_get_option('ci_pdf_data', 'allow_type_col', false)
                        && !tr_get_option('ci_pdf_data', 'discreatedcol')
                    ) { ?>
                        <select name="col[col_ID]" id="col_id_sel" class="js_select2">
                            <?php
                            foreach ($collectors as $collector) {
                                $selected = $collector['col_ID'] == $bat['col_ID'] ? 'selected' : '';
                                echo '<option value="' . $collector['col_ID'] . '" ' . $selected . ' data-phone="' . $collector['col_phone'] . '">' . tr_trim_name(@$collector['col_first'] . ' ' . @$collector['col_last']) . '</option>';
                            }
                            $collector['col_phone'] = $collectors[0]['col_phone'];
                            ?>
                        </select>
                    <?php } else {
                        if (!$collector) {
                            $collector = isset($collectors[0]) ? $collectors[0] : array('col_first' => '', 'col_last' => '');
                        }
                        $collector_name = tr_trim_name($collector['col_first'] . ' ' . @$collector['col_last']);
                        if (empty($collector_name) && tr_get_option('ci_pdf_data', 'discreatedcol')) {
                            $author_id = $bat['author_ID'] > 0 || $bat['mid'] > 0 ? $bat['author_ID'] : get_current_user_id();

                            if ($author_id > 0) {
                                $collector_name = get_user_meta($author_id, 'first_name', true) . ' ' .
                                    get_user_meta($author_id, 'last_name', true);
                                $collector['col_ID'] = -1;
                            }
                        }
                    ?>
                        <input type="text" class="<?php if ($current_role != 'collector') echo 'col_auto' ?>" name="col[col_name]" value="<?php echo $collector_name ?>" />
                        <input type="hidden" name="col[col_ID]" value="<?php echo isset($collector['col_ID']) ? $collector['col_ID'] : '' ?>" />
                    <?php }; ?>
                    <label>Collector Phone Number</label>
                    <input type="text" class="has_phone" name="col[col_phone]" value="<?php echo isset($collector['col_phone']) ? $collector['col_phone'] : '' ?>" <?php if (!empty($collector['col_phone'])) echo 'readonly' ?> />
                </div>
                <div class="col4">
                    <label>Diagnostic Codes</label>

                    <div class="dagnostic_code" data-name="bat[dagnostic_codes][]" <?php if (tr_get_option('ci_pdf_data', 'gene_diagnostic_required')) echo 'data-required="true"' ?>>
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
                                <?php tr_select_option('dagnostic_code_select', tr_pdd_get_dagnostic_codes('gene'), null) ?>
                                <input type="text" id="dagnostic_code_text" placeholder="Enter Additional Codes" />
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
                <div class="col4">
                    <label>Date of Collection</label>
                    <input type="text" class="hasdatetime <?php if (tr_get_option('ci_pdf_data', 'limit_doc')) echo 'limitfuture' ?>" <?php if (tr_current_role_in(array('administrator', 'AdminCIDW', 'administrator_p', 'lab', 'labsecman', 'leadlabsecman', 'lab_manager', 'lab_sec', 'lab_tech'))) echo 'data-write="true"' ?> name="bat[doc]" value="<?php echo empty($bat['doc']) && $act != 'print' && !$settings['disable_auto_doc'] ? 'auto' : $bat['doc'] ?>" <?php if (!empty($bat['doc'])) echo 'readonly' ?> required="" />

                    <?php if (tr_get_option('ci_pdf_data', 'show_basic_demographics')) { ?>
                        <div class="frow" style="margin-bottom:5px;margin-top:15px">
                            <h4 style="margin:0">BASIC DEMOGRAPHICS</h4>
                        </div>
                        <div>
                            <span style="display: inline-block;width:50px">Name: </span> <span id="patient_name" style="font-size:15px"><?php echo (isset($pa['pa_last']) ? $pa['pa_last'] : '') . ', ' . (isset($pa['pa_first']) ? $pa['pa_first'] : '') ?></span>
                        </div>
                        <div style="margin-top:5px">
                            <span style="display: inline-block;width:50px">DOB:</span> <span id="patient_dob" style="font-size:15px"><?php echo isset($pa['pa_dob']) ? $pa['pa_dob'] : '' ?></span>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="frow">
                <?php if (tr_get_option('ci_pdf_data', 'show_pregnant')) { ?>
                    <div class="col4">
                        <label>Pregnant</label>
                        <?php tr_select_option('bat[p_pregnant]', array('' => 'Select', 'Y' => 'Yes', 'N' => 'No'), $bat['p_pregnant']) ?>
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

            <input type="hidden" name="pa[pa_ID]" value="<?php echo isset($pa['pa_ID']) ? $pa['pa_ID'] : '' ?>" />
            <div class="patient_block">
                <div class="frow blabel">
                    <?php if (!tr_get_option('ci_pdf_data', 'disable_ssn')) { ?>
                        <div class="col4">

                            <label>Social Security Number </label>
                            <input type="text" name="pa[pa_ssn]" id="pa_ssn" maxlength="11" value="<?php echo isset($pa['pa_ssn']) ? ($act == 'print' ? $pa['pa_ssn'] . ' ' : $pa['pa_ssn']) : '' ?>" <?php if (tr_get_option('ci_pdf_data', 'required_ssn')) echo 'required' ?> <?php if ($act != 'print') echo 'style="width:76%"' ?> />
                            <?php if ($act != 'print' && $act != 'view') : ?>
                                <a class="button find_patient samefheight" href="#" data-type="ssn">find</a>
                            <?php endif; ?>
                        </div>
                    <?php } else if (tr_get_option('ci_pdf_data', 'show_symtomatic')) {
                    ?>
                        <div class="col4">
                            <label>Is patient Symptomatic?</label>
                            <?php tr_select_option('pa[pa_symptomatic]', array('' => 'Select', 'Y' => 'Yes', 'N' => 'No'), isset($pa['pa_symptomatic']) ? $pa['pa_symptomatic'] : '', array('attr' => ' ')) ?>
                        </div>
                    <?php
                    } ?>
                    <div class="col4">
                        <input type="hidden" name="bat[is_employee]" value="0" />
                        <label>Last Name <span class="right">Employee <input type="checkbox" name="bat[is_employee]" value="1" <?php checked(isset($bat['is_employee']) ? $bat['is_employee'] : 0) ?> /> </span></label>
                        <input type="text" class="cfname requesting_patients_last_auto" name="pa[pa_last]" autocomplete="none" value="<?php echo isset($pa['pa_last']) ? $pa['pa_last'] : '' ?>" <?php if ($notallowchangepatient && isset($pa['pa_last']) && !empty($pa['pa_last']) && $saved_id > 0) echo 'readonly' ?> required="" />
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
                                                                                                                                                            if ($act != 'print') echo ' style="width:76%" ';
                                                                                                                                                            if (tr_get_option('ci_pdf_data', 'mrn_required')) echo ' required ';
                                                                                                                                                            ?> />
                        <?php if ($act != 'print' && $act != 'view') : ?>
                            <a class="button find_patient samefheight" href="#" data-type="med_number">find</a>
                        <?php endif; ?>
                    </div>
                    <div class="col4">
                        <label>Street Address <span class="right">NH <input type="checkbox" class="nh_address" /> </span> </label>
                        <input type="text" name="pa[pa_address]" id="pa_address" value="<?php echo isset($pa['pa_address']) ? $pa['pa_address'] : '' ?>" required="" maxlength="150" />
                    </div>
                    <?php if (tr_get_option('ci_pdf_data', 'enable_patient_room') && !tr_get_option('ci_pdf_data', 'enable_patient_unit_room_bed')) { ?>
                        <div class="col12">
                            <label>Room</label>
                            <input type="text" name="pa[pa_room]" value="<?php echo isset($pa['pa_room']) ? $pa['pa_room'] : '' ?>" />
                        </div>
                    <?php } ?>
                    <div class="<?php echo tr_get_option('ci_pdf_data', 'enable_patient_room') && !tr_get_option('ci_pdf_data', 'enable_patient_unit_room_bed') ? 'col6' : 'col4' ?>">
                        <label>City</label>
                        <input type="text" name="pa[pa_city]" id="pa_city" value="<?php echo isset($pa['pa_city']) ? $pa['pa_city'] : '' ?>" required="" maxlength="100" />
                    </div>
                    <div class="col4">
                        <div class=" blabel frow">
                            <div class="col2">
                                <label>State</label>
                                <?php tr_select_option('pa[pa_state]', tr_get_states(), isset($pa['pa_state']) ? $pa['pa_state'] : '', array('attr' => 'required')) ?>
                            </div>
                            <div class="col2">
                                <label>Zip</label>
                                <input type="text" name="pa[pa_zip]" id="pa_zip" value="<?php echo isset($pa['pa_zip']) ? $pa['pa_zip'] : '' ?>" required="" maxlength="12" />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="frow">
                    <div class="col4">
                        <label>Gender</label>
                        <?php tr_select_option('pa[pa_gender]', trpdd_genders(), isset($pa['pa_gender']) ? $pa['pa_gender'] : '', array('attr' => 'required="" ')) ?>
                    </div>
                    <div class="col4">
                        <label>DOB</label>
                        <input type="text" class="<?php if ((!$notallowchangepatientdob || empty($pa['pa_dob'])) && $saved_id == 0) echo 'hasdate' ?> birthday limitfuture" name="pa[pa_dob]" value="<?php echo isset($pa['pa_dob']) ? $pa['pa_dob'] : '' ?>" <?php if ($notallowchangepatientdob && !empty($pa['pa_dob'])) echo 'readonly' ?> required="" <?php if (tr_get_option('ci_pdf_data', 'e_hand_editdob')) echo 'data-write="1"' ?> />
                    </div>
                    <div class="col4">
                        <label>Phone Number</label>
                        <input type="text" class="has_phone" name="pa[pa_phone]" value="<?php echo isset($pa['pa_phone']) ? $pa['pa_phone'] : '' ?>" <?php if (!tr_get_option('ci_pdf_data', 'pa_phone_notrequired')) echo 'required' ?> maxlength="18" />
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
                    ?>
                        <div class="col4">
                            <label>Is patient Symptomatic?</label>
                            <?php tr_select_option('pa[pa_symptomatic]', array('' => 'Select', 'Y' => 'Yes', 'N' => 'No'), isset($pa['pa_symptomatic']) ? $pa['pa_symptomatic'] : '', array('attr' => ' ')) ?>
                        </div>
                    <?php } ?>

                    <?php if (tr_get_option('ci_pdf_data', 'show_patient_passport')) {
                        $count_colinrow++;
                    ?>
                        <div class="col4">
                            <label>Passport Number</label>
                            <input type="text" name="pa[pa_passport]" value="<?php echo isset($pa['pa_passport']) ? $pa['pa_passport'] : '' ?>" maxlength="100" />
                        </div>
                    <?php } ?>

                    <?php
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
                            '' => 'Select',
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
                            '' => 'Select',
                            'H' => 'Hispanic or Latino',
                            'N' => 'Not Hispanic or Latino',
                            'U' => 'Unknown'
                        );
                        tr_select_option('pa[pa_ethnicity]', $Ethnicitis, isset($pa['pa_ethnicity']) ? $pa['pa_ethnicity'] : '');
                        ?>
                    </div>

                    <?php

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
                            tr_select_option('pa[pa_genotypes]', $settings['pa_genotypes'], @$pa['pa_genotypes']);
                            ?>
                        </div>

                    <?php
                    }
                    ?>
                    <?php
                    if (tr_get_option('ci_pdf_data', 'gene_patient_fvaccine')) {
                        if ($count_colinrow >= 4) {
                            $count_colinrow = 0;
                            echo '</div><div class="frow">';
                        }
                        $count_colinrow++;
                        echo '<div class="col4">';
                        echo '<label>Are you fully Vaccinated?</label>';
                        tr_select_option('pa[pa_fully_vaccinated]', array('' => 'Select', 'yes' => 'Yes', 'no' => 'No'), $pa['pa_fully_vaccinated']);
                        echo '</div>';
                    }
                    ?>
                </div>
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
                        <input type="text" name="pa[pa_pt_county]" value="<?php echo isset($pa['pa_pt_county']) ? $pa['pa_pt_county'] : '' ?>" />
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
                <div class="frow">
                    <div class="col23">
                        <h4><label><input type="radio" name="bat[ins_comp]" value="ins" <?php checked('ins', @$bat['ins_comp']) ?> /> Primary Insurance Billing <span>(include copy of both sides of insurance card)</span></label></h4>
                    </div>
                    <div class="col13">
                        <h4><label><input type="radio" name="bat[ins_comp]" value="comp" <?php checked('comp', @$bat['ins_comp']) ?> /> Worker's Comp and Auto Information</label></h4>
                    </div>
                </div>

                <div class="frow blabel">
                    <div class="col23 insurance_block1" id="use_required_ins">

                        <div class="frow">
                            <div class="col3">
                                <input type="hidden" name="no_insurance" value="" />
                                <label class="inline"><input type="checkbox" name="no_insurance" value="No Insurance - Cash" <?php checked(isset($bat['insurance']) ? $bat['insurance'] : '', 'No Insurance - Cash') ?> /> No Insurance - Cash</label>
                            </div>
                            <?php if (tr_get_option('ci_pdf_data', 'req_cares_act')) { ?>
                                <div class="col2">
                                    <input type="hidden" name="ins1[ins_cares_act]" value="" />
                                    <label class="inline"><input type="checkbox" name="ins1[ins_cares_act]" value="1" <?php checked(isset($ins1['ins_cares_act']) ? $ins1['ins_cares_act'] : '', '1') ?> /> No Insurance - CARES Act</label>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="frow">
                            <div class="col3">
                                <label>Last Name</label>
                                <input type="text" class="cfname has_required" name="ins1[ins_last]" value="<?php echo isset($ins1['ins_last']) ? $ins1['ins_last'] : '' ?>" <?php echo $required_insurance ?> />
                                <input type="hidden" name="ins1[ins_ID]" value="<?php echo isset($ins1['ins_ID']) ? $ins1['ins_ID'] : '' ?>" />
                            </div>
                            <div class="col3">
                                <label>First Name</label>
                                <input type="text" class="cfname has_required" name="ins1[ins_first]" value="<?php echo isset($ins1['ins_first']) ? $ins1['ins_first'] : '' ?>" <?php echo $required_insurance ?> />
                            </div>
                            <div class="col3 ilabel">
                                <label>Relation to Patient?</label>
                                <div>
                                    <?php if ($act == 'print') {
                                        echo '<span class="text">' . $ins1['ins_relation'] . '</span>';
                                    } else {
                                        tr_radio('ins1[ins_relation]', array('Self' => 'Self', 'Parent' => 'Parent', 'Spouse' => 'Spouse'), @$ins1['ins_relation'], array('attr' => $required_insurance));
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="col3" id="dob_ins1" <?php if (@$ins1['ins_relation'] == 'Self') echo 'style="display: none"' ?>>
                                <label><span>Parent</span> DOB</label>
                                <input type="text" class="hasdate birthday limitfuture <?php if ($required_insurance == '') echo 'notrequired' ?>" name="ins1[ins_dob]" value="<?php echo isset($ins1['ins_dob']) ? $ins1['ins_dob'] : '' ?>" />
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
                                            $selected = $ins->payer_name == $ins1['ins_company'] ? 'selected' : '';
                                            if ($selected) $has_ins_selected = true;
                                            echo '<option value="' . $ins->payer_name . '" ' . $selected . '>' . $ins->payer_name . '</option>';
                                            if (!empty($ins->payer_name_search)) {
                                                echo '<option value="' . $ins->payer_name . '" ' . $selected . '>' . $ins->payer_name_search . ' (' . $ins->payer_name . ')</option>';
                                            }
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
                                <label>Mnemonic</label>
                                <input type="text" name="ins1[ins_mnemonic]" value="<?php echo isset($ins1['ins_mnemonic']) ? $ins1['ins_mnemonic'] : '' ?>" readonly maxlength="30" />
                            </div>
                            <div class="col3">
                                <label>Email</label>
                                <input type="email" name="ins1[ins_email]" value="<?php echo isset($ins1['ins_email']) ? $ins1['ins_email'] : '' ?>" />
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
                                        <?php tr_select_option('ins1[ins_state]', tr_get_states(), @$ins1['ins_state'], array('attr' => $required_insurance_address)) ?>
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
                            <div class="col3 ilabel">
                                <label>Relation to Patient?</label>
                                <div>
                                    <?php if ($act == 'print') {
                                        echo '<span class="text">' . (isset($ins2['ins_relation']) ? $ins2['ins_relation'] : '') . '</span>';
                                    } else {
                                        tr_radio('ins2[ins_relation]', array('Self' => 'Self', 'Parent' => 'Parent', 'Spouse' => 'Spouse'), isset($ins2['ins_relation']) ? $ins2['ins_relation'] : '');
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
                                <input type="text" class="cfname" name="bat[subscriber_last]" value="<?php echo isset($bat['subscriber_last']) ? $bat['subscriber_last'] : '' ?>" data-required="true" />
                            </div>
                            <div class="col2">
                                <label>Subscriber First Name</label>
                                <input type="text" class="cfname" name="bat[subscriber_first]" value="<?php echo isset($bat['subscriber_first']) ? $bat['subscriber_first'] : '' ?>" data-required="true" />
                            </div>
                        </div>
                        <div class="frow">
                            <label>Date of Injury</label>
                            <input type="text" class="hasdate" name="bat[date_of_injury]" value="<?php echo isset($bat['date_of_injury']) ? $bat['date_of_injury'] : '' ?>" data-required="true" />
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
                                <input type="text" class="cfname" name="bat[subscriber_insurance]" value="<?php echo isset($bat['subscriber_insurance']) ? $bat['subscriber_insurance'] : '' ?>" data-required="true" />
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
                            <input type="text" name="bat[claim_number]" value="<?php echo isset($bat['claim_number']) ? $bat['claim_number'] : '' ?>" data-required="true" />
                        </div>
                        <div class="frow">
                            <label>Claim under litigation?</label>
                            <?php tr_yes_no('bat[claim_under_litigation]', isset($bat['claim_under_litigation']) ? $bat['claim_under_litigation'] : ''); ?>
                        </div>
                    </div>
                </div>
            <?php } ?>

        </div>

        <?php if (tr_get_option('ci_pdf_data', 'gene_card_images')) { ?>
            <div class="insidebreak">
                <div class="frow">
                    <h4>INSERT INSURANCE CARD AND PHOTO ID PICTURES</h4>
                </div>
                <?php if (!$billing_page) { ?>
                    <div class="frow">
                        By clicking the icon below, you will be prompted to insert a photo of both the patient current insurance card and photo ID. The photo
                        can be obtained directly from the computer (saved as a image) or a scanner.
                    </div>
                <?php } ?>
                <div class="frow blabel">
                    <?php
                    $col_num = (!$can_edit) ? 'col2' : 'col4';
                    ?>
                    <div class="<?php echo $col_num ?>">
                        <?php
                        $rq1        = false;
                        $card_link  = '';
                        if (!empty($bat['ins_card_front']) && file_exists($card_dir['path'] . '/' . $bat['ins_card_front']) && filesize($card_dir['path'] . '/' . $bat['ins_card_front']) > 0) {
                            $imageurl = $card_dir['url'] . '/' . $bat['ins_card_front'] . '?r=' . time();
                            if (stripos($bat['ins_card_front'], '.pdf') > 0) {
                                $imageurl = '?tr_control=ci_pdf_image&action=view_card_image&mid=' . $bat['mid'] . '&btype=gene&field=ins_card_front&saved_id=' . $saved_id;
                            }
                            $card_link = '<a href="' . $imageurl . '" target=_blank><img src="' . $imageurl . '" style="width:100%"></a>';
                        }
                        if ($can_edit && (!isset($billing_page) || !$billing_page)) {
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
                                $imageurl = '?tr_control=ci_pdf_image&action=view_card_image&mid=' . $bat['mid'] . '&btype=gene&field=ins_card_back&saved_id=' . $saved_id;
                            }
                            $card_link = '<a href="' . $imageurl . '" target=_blank><img src="' . $imageurl . '" style="width:100%"></a>';
                        }
                        if ($can_edit  && (!isset($billing_page) || !$billing_page)) {
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
                                $imageurl = '?tr_control=ci_pdf_image&action=view_card_image&mid=' . $bat['mid'] . '&btype=gene&field=patient_id_front&saved_id=' . $saved_id;
                            }
                            $card_link = '<a href="' . $imageurl . '" target=_blank><img src="' . $imageurl . '" style="width:100%"></a>';
                        }
                        if ($can_edit && (!isset($billing_page) || !$billing_page)) {
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
                                $imageurl = '?tr_control=ci_pdf_image&action=view_card_image&mid=' . $bat['mid'] . '&btype=gene&field=patient_id_back&saved_id=' . $saved_id;
                            }
                            $card_link = '<a href="' . $imageurl . '" target=_blank><img src="' . $imageurl . '" style="width:100%"></a>';
                        }
                        if ($can_edit && (!isset($billing_page) || !$billing_page)) {
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
            </div>
        <?php } ?>

        <?php if (!$billing_page || isset($_REQUEST['show_tests'])) { ?>
            <div class="frow gene_med_tests_section">
                <?php if ($act == 'print') : ?>
                    <div class="frow gene_medications_box" style="width:100%;display:block;float:none;clear:both">
                        <h4>PATIENT’S CURRENT MEDICATIONS</h4>
                        <div class="gene_medications_inner" style="padding-top:8px">
                <?php else : ?>
                    <table class="gene_med_tests_table" style="width:100%;border-collapse:collapse">
                        <tr>
                            <td style="width:49.75%;vertical-align:top;padding-right:4px">
                                <h4>PATIENT’S CURRENT MEDICATIONS</h4>
                                <div class="lable4cl" style="padding-top:20px">
                <?php endif; ?>
                        <input type="hidden" name="has_edit_medication" value="1" />
                        <?php
                        if (!isset($bat['current_medication_other']) || !is_array($bat['current_medication_other'])) $bat['current_medication_other'] = array();
                        $current_medications = tr_pdd_get_current_medications('gene');
                        $med_i = 0;
                        if ($act == 'print') {
                            echo '<table class="gene_medications_list" style="width:100%;border-collapse:collapse"><tr>';
                        }
                        foreach ($current_medications as $m) :
                            $k = $m;
                            if ($k == 'Fioricet/Fiorinal') $k = 'Fioricet';
                            else if ($k == 'Lorcet/Lortab') $k = 'Lortab';
                            if (in_array($k, $bat['current_medication_other'])) {
                                //remove in other
                                $bat['current_medication_other'] = array_values(array_diff($bat['current_medication_other'], array($k)));
                            }
                            if ($act == 'print') {
                                if ($med_i > 0 && $med_i % 4 == 0) echo '</tr><tr>';
                        ?>
                                <td style="width:25%;vertical-align:top;padding:2px 6px 2px 0">
                                    <label style="display:block;float:none;width:auto;white-space:normal;line-height:13px">
                                        <input type="checkbox" name="bat[current_medication][]" value="<?php echo $k ?>" <?php checked(in_array($k, @$bat['current_medication'])) ?> />
                                        <?php echo $m ?>
                                    </label>
                                </td>
                            <?php
                                $med_i++;
                            } else {
                            ?>
                                <label>
                                    <input type="checkbox" name="bat[current_medication][]" value="<?php echo $k ?>" <?php checked(in_array($k, @$bat['current_medication'])) ?> />
                                    <?php echo $m ?>
                                </label>
                            <?php
                            }
                        endforeach;
                        if ($act == 'print') {
                            while ($med_i > 0 && $med_i % 4 != 0) {
                                echo '<td style="width:25%;padding:2px 6px 2px 0">&nbsp;</td>';
                                $med_i++;
                            }
                            echo '</tr></table>';
                        }
                        ?>

                        <div class="frow" style="padding-top:10px">
                            <input type="hidden" name="current_medication_none" value="" />
                            <input type="checkbox" name="current_medication_none" value="1" <?php checked(in_array('None Listed', @$bat['current_medication'])) ?> /> No Prescribed Medication
                        </div>

                        <div class="frow" style="padding-top:20px" id="more_current_medication_div">
                            <?php

                            $count = count($bat['current_medication_other']) >= 3 ? count($bat['current_medication_other']) : 3;
                            for ($i = 0; $i < $count; $i++) : ?>
                                <div class="col3 <?php if ($i == 0) echo 'default' ?>">
                                    <label>Other:</label>
                                    <input type="text" name="bat[current_medication_other][]" value="<?php echo isset($bat['current_medication_other'][$i]) ? $bat['current_medication_other'][$i] : '' ?>" />
                                </div>
                            <?php endfor; ?>
                        </div>
                        <?php if ($act != 'print') : ?>
                            <a class="add_more_current_medication button">+</a>
                        <?php endif; ?>
                        <div class="frow">
                            Not all drugs LISTED under "other" will be available.
                        </div>
                <?php if ($act == 'print') : ?>
                        </div>
                    </div>
                    <div class="frow gene_testing_ordered_section" style="width:100%;display:block;float:none;clear:both">
                        <h4>TESTING ORDERED</h4>
                <?php else : ?>
                                </div>
                            </td>
                            <td style="width:49.75%;vertical-align:top;padding-left:4px">
                                <h4>TESTING ORDERED</h4>
                <?php endif; ?>
                        <input type="hidden" name="has_panel_requests" value="1" />
                        <?php
                        $i = 0;
                        $adding_tests = trpdd_get_batch_meta($mid, 'gene', 'adding_tests', $saved_id > 0);
                        foreach (tr_pdd_get_profiles_tests_requesteds('gene', true, false, $bat['doc']) as $group_id => $vl) {
                            $group_key   = 'GROUP_' . $group_id;
                            $group       = tr_pdd_get_panel_groups($group_id, 'gene');
                            $group_tests = array();
                            foreach ($vl as $t) {
                                $group_tests[] = $t->te_name;
                            }
                            $tests        = isset($adding_tests[$group_id]) ? $adding_tests[$group_id] : array(array());

                            $in_group     = @in_array($group_key, $bat['panel_tests_requested']);

                            if (!$group['show'] && !$in_group) continue;

                            if (empty($group['bgcolor'])) {
                                $group['bgcolor'] = '#f8f8f8';
                            }
                            $group['color'] = trpdd_getContrastColor($group['bgcolor']);
                            $group['spec_bgcolor'] = strlen($group['bgcolor']) == 7 ? $group['bgcolor'] . '88' : $group['bgcolor'];
                            $group['box_bgcolor'] = strlen($group['bgcolor']) == 7 ? $group['bgcolor'] . '66' : $group['bgcolor'];
                            if ($act == 'print') {
                                $group['box_bgcolor'] = '#f8f8f8';
                                $group['spec_bgcolor'] = '#eeeeee';
                            }
                        ?>
                            <?php
                            $show_as_pcr = isset($group['other_options']['show_as_pcr_groups']) && $group['other_options']['show_as_pcr_groups'];
                            if ($show_as_pcr) {
                                $has_select_group = false;
                                $has_checked = false;
                                $group_class = '';
                                if ($group['parent'] > 0) {
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

                            ?>
                                <div class="groupparent item <?php echo $group_class ?>" style="background:<?php echo $group['box_bgcolor'] ?>">
                                    <label class="block bold group_label" style="background:<?php echo $group['bgcolor'] ?>;color: <?php echo $group['color'] ?>">
                                        <input type="checkbox" class="<?php echo $group_cb_class ?>" name="bat[panel_tests_requested][]" value="<?php echo $group_key ?>" data-relate_to="<?php echo $group['relate_to'] ?>" data-limit_days="<?php echo $group['limit_days'] ?>" <?php checked($in_group) ?> />
                                        <?php echo $group['label'] ?>
                                    </label>
                                    <div class="frow" style="padding: 10px;">
                                        <?php
                                        if (isset($group['other_options']['desc']) && !empty($group['other_options']['desc'])) {
                                            echo '<div class="panel_desc">' . $group['other_options']['desc'] . '</div>';
                                        }
                                        $subgroups = array();
                                        echo '<div class="two_col_tests" style="display:flex;flex-wrap:wrap;margin:0 -6px">';
                                        foreach ($vl as $k => $r) {
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

                                            $r->class = isset($r->class) ? $r->class . ' cb_test ' : ' cb_test ';
                                            if ($is_hold) {
                                                $r->class .= ' readonly ';
                                            }

                                            if ($r->tg_parent > 0 && !in_array($r->tg_ID, $subgroups) && $group_id != $r->tg_ID) {
                                                $subgroups[] = $r->tg_ID;
                                                $subgrouup_key = 'GROUP_' . $r->tg_ID;
                                                $in_subgroup =  @in_array($subgrouup_key, $bat['panel_tests_requested']);
                                                $cb_subgroup_class = '';
                                        ?>
                                                <div style="width:100%">
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
                                                </div>
                                            <?php
                                            }
                                            if ($r->tg_only_group) {
                                                continue;
                                            }
                                            ?>
                                            <div style="width:50%;padding:0 6px;box-sizing:border-box">
                                                <label class="c lcb" style="display:block">
                                                    <?php if (!$group['notestcheckbox']) { ?>
                                                        <input type="checkbox" class="<?php echo $r->class ?>" data-te_type_id="<?php echo $r->te_type_id ?>" name="bat[panel_tests_requested][]" data-relations="<?php echo $r->te_relations ?>" value="<?php echo $key ?>" <?php checked($checked) ?> />
                                                    <?php } ?>
                                                    <span><?php echo $txt ?></span>
                                                </label>
                                            </div>
                                        <?php
                                        }

                                        echo '</div>';

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
                            } else {
                                ?>
                                <div class="frow blabel testing_ordered_box " style="padding:5px;background:<?php echo $group['box_bgcolor'] ?>">
                                    <label>
                                        <input type="checkbox" name="bat[panel_tests_requested][]" <?php checked($in_group) ?> value="<?php echo $group_key ?>">
                                        <strong class="tooltip" title="<?php echo implode(', ', $group_tests) ?>"><?php echo $group['label'] ?>: <i class="itip"></i></strong>
                                    </label>
                                    <div class="testingordered_wrap">
                                        <table class="testing_ordered_list">
                                            <?php foreach ($tests as $j => $arr) {
                                                $arr['type'] = isset($arr['type']) ? $arr['type'] : '';
                                                $arr['name']  = isset($arr['name']) ? $arr['name']  : '';
                                            ?>
                                                <tr>
                                                    <td class="tdclpn"><label>- <input type="radio" name="testing_ordered_add[<?php echo $group_id ?>][<?php echo $j ?>]" value="-" <?php checked($arr['type'], '-') ?> /></label> </td>
                                                    <td class="tdclpn"><label>+ <input type="radio" name="testing_ordered_add[<?php echo $group_id ?>][<?php echo $j ?>]" value="+" <?php checked($arr['type'], '+') ?> /></label> </td>
                                                    <td><input type="text" name="testing_ordered_name[<?php echo $group_id ?>][<?php echo $j ?>]" value="<?php echo $arr['name'] ?>" /></td>
                                                </tr>
                                            <?php } ?>
                                        </table>
                                        <div class="alignright"><a class="button add_new_testing" data-nextid="<?php echo $j + 1 ?>">+</a></div>

                                        <div class="specimens">
                                            <?php
                                            $rs = $wpdb->get_results("select * from ci_test_specimens where (ts_group_id={$group_id} or ts_group_id ='') and ts_type='gene'");
                                            if (count($rs) > 0) {
                                            ?>
                                                <div class="specimens" style="padding:5px;margin-top:5px;">
                                                    <label class="title">SPECIMEN SOURCE</label>
                                                    <?php
                                                    $specimens = explode(',', strtolower($bat['type_of_specimen']));
                                                    foreach ($rs as $r) {
                                                        $checked_spec = in_array(strtolower($r->ts_name), $specimens);
                                                        if (!$in_group) {
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
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            <?php
                            }
                            ?>

                        <?php
                            $i++;
                        }
                        ?>
                <?php if ($act == 'print') : ?>
                    </div>
                <?php else : ?>
                            </td>
                        </tr>
                    </table>
                <?php endif; ?>
            </div>
        <?php } ?>


        <?php include('box_signature.php'); ?>


        <?php if (!tr_get_option('ci_pdf_data', 'hide_physicalnotes')) { ?>
            <div class="frow">
                <h4>NOTES</h4>
            </div>
            <div class="frow">
                <textarea name="bat[notes]" <?php if ($act != 'print') echo 'placeholder="Please enter any ' . (tr_get_option('ci_pdf_data', 'cht_physicalnotes') ? 'Chief Complaint' : 'Physician Notes') . '"' ?>><?php if (isset($bat['notes'])) echo @$bat['notes'] ?></textarea>
            </div>
        <?php } ?>

        <?php if (tr_get_option('ci_pdf_data', 'enable_patient_notes')) { ?>
            <div class="frow">
                <h4>PATIENT NOTES</h4>
            </div>
            <div class="frow">
                <textarea name="pa[pa_notes]" <?php if ($act != 'print') echo 'placeholder="Please enter Patient Notes"' ?>><?php if (isset($pa['pa_notes'])) echo @$pa['pa_notes'] ?></textarea>
            </div>
        <?php } ?>

        <?php if ($act != 'print') {
        ?>
            <div class="frow">
                <h4>NOTES (visible on reports)</h4>
            </div>
            <div class="frow">
                <textarea name="bat[notes_report]"><?php if (isset($bat['notes_report'])) echo @$bat['notes_report'] ?></textarea>
            </div>
        <?php
        } ?>


        <?php if ($billing_page) {
        ?>
            <div class="frow">
                <h4>Billing notes</h4>
            </div>
            <div class="frow">
                <textarea name="bat[billing_notes]" maxlength="200" <?php if ($act != 'print') echo 'placeholder="Please enter any Billing Notes"' ?>><?php if (isset($bat['billing_notes'])) echo @$bat['billing_notes'] ?></textarea>
            </div>
        <?php
        } else {
        ?>

            <div class="frow insidebreak">
                <div class="col2">
                    <label>Status</label>
                    <?php
                    $status_options = tr_pdd_get_batch_statuses_on_hold(tr_pdd_get_batch_statuses(false, false, false, 'gene'), $bat['batch_status'], $bat['mid'], $table_batch, 'gene');
                    $disabled_keys = tr_pdd_get_batch_statuses_disabled_keys('gene', 'show_in_req_form', $bat['batch_status']);
                    tr_select_option('bat[batch_status]', $status_options, @$bat['batch_status'], ['disabled_options' => $disabled_keys]);
                    ?>
                </div>
                <?php if (tr_get_option('ci_pdf_data', 'gene_reasont')) { ?>
                    <div class="col2 " id="p_condition">
                        <label>Reason for Testing</label>
                        <?php
                        tr_select_option('bat[p_condition]', array(
                            '' => 'Select',
                            'Weekly Testing' => 'Weekly Testing',
                            'Symptoms/Exposure' => 'Symptoms/Exposure',
                            'Positive Retest' => 'Positive Retest',
                            'Other' => 'Other'
                        ), @$bat['p_condition']) ?>
                    </div>
                <?php } ?>
            </div>
        <?php
        }
        ?>



        <?php if ($can_edit && $act != 'print') { ?>

            <?php if (!$billing_page) { ?>
                <div class="frow">
                    <h4>Upload</h4>
                </div>
                <div class="frow">
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
                        <?php trpdd_render_req_upload_files('original_req', isset($bat['original_req']) ? $bat['original_req'] : '', 'Upload Paper Req'); ?>
                    </div>
                    <div class="col3 notprint">
                        <div class="card_file">
                            <a class="button selectfile" href="#">Upload Issue Photo</a>
                            <span class="view">
                                <?php
                                $dir = tr_get_issue_photo_dir();
                                $fname = isset($bat['issue_photo']) ? $bat['issue_photo'] : '';
                                if (!empty($fname) && file_exists($dir['path'] . '/' . $fname) && filesize($dir['path'] . '/' . $fname) > 0) {
                                    echo '<a href="' . $dir['url'] . '/' . $fname . '">View File</a>';
                                }
                                ?>
                            </span>
                            <input type="file" name="issue_photo" accept="image/*" />
                        </div>

                    </div>

                </div>


                <div class="frow">
                    <h4>&nbsp;</h4>
                </div>
                <?php if (tr_get_option('ci_pdf_data', 'show_pid_field')) { ?>
                    <div class="frow">
                        <span class="ilabel">
                            <label style="display:inline-block;width:120px"><?php echo tr_get_option('ci_pdf_data', 'title_pid_field', 'PID') ?></label>
                            <input type="text" style="width:300px" name="bat[oldreq]" value="<?php echo isset($bat['oldreq']) ? $bat['oldreq'] : '' ?>" <?php if (tr_get_option('ci_pdf_data', 'oldreq_required')) echo 'required' ?> />
                        </span>
                    </div>
                <?php } ?>
                <div class="frow row" style="margin-bottom:15px;">

                    <div class=" foutrow ilabel">
                        <?php if ($act != 'print' && $act != 'view' && !$billing_page) { ?>
                            <span id="show_assen">
                                <label>Accession Number</label>
                                <input type="text" id="number_assen" name="bat[assen]" value="<?php echo isset($bat['assen']) ? $bat['assen'] : '' ?>" <?php if ($assen_readonly) echo 'readonly' ?> required />
                                <label>Confirm</label>
                                <input type="text" id="confirm_assen" value="<?php echo isset($bat['assen']) ? $bat['assen'] : '' ?>" required />
                                <span class="confirm_status dashicons dashicons-yes"></span>
                                <a class="button print_assen_btn" style="<?php if (empty($bat['assen']) || $saved_id > 0) echo 'display:none' ?>" href="#">Print Barcode</a>
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
            } else {

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

                    tr_bill_get_billing_code($ins1['ins_company'], $report['id'], $bat);
                    ?>
                </div>


                <?php
                if (!empty($bat['original_req'])) {
                    $result_dir = tr_get_result_upload_dir();
                    $original_req = $result_dir['url'] . '/' . $bat['original_req'];
                ?>
                    <div class="frow">
                        <h4>Paper Req</h4>
                        <div class="col3">
                            <span><a href="<?php echo $original_req ?>" class="original_req_view" target="_blank">Paper Req</a></span>
                        </div>
                    </div>
            <?php
                }
            }
            ?>


            <?php
            if (isset($settings['add_standing_order']) && $settings['add_standing_order'] && current_user_can('ci_standing_orders_gene') && empty($mid)) {
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
            if (isset($settings['add_blood_gene']) && $settings['add_blood_gene'] && (!isset($by_type_id) || $by_type_id == 0)) {
            ?>
                <div class="frow">
                    <label><input type="checkbox" name="add_new_type" value="blood" /> Add Blood Tests</label>
                    <br><br>
                </div>
            <?php
            }
            ?>

            <?php do_action('end_form_gene', $bat) ?>

            <div class="frow submitdiv">
                <input type="submit" class="button-primary" style="margin-right: 150px;" id="save_gene_form" value="Submit">
                <input type="submit" class="button-primary" id="submit_print_btn" name="save_print" value="Print Barcode / Submit">
                <a class="iprint button bsty2" href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_print_req', 'id' => isset($bat['mid']) ? $bat['mid'] : '', 'type' => 'gene', 'tab' => '')) ?>">Print Form</a>
                <?php if (isset($_REQUEST['page']) && $_REQUEST['page'] == 'ci_short_list') {
                ?>
                    <input type="hidden" name="bat[short_completed]" value="0">
                    <label><input type="checkbox" name="bat[short_completed]" value="1" <?php checked(@$bat['short_completed'], 1) ?>> Completed</label>
                <?php
                } ?>

                <?php if (!isset($mid) || $mid == 0 || $saved_id > 0) { ?>
                    <input type="submit" class="button only_save_form right" name="only_save_form" value="Save temporary">
                    <input type="hidden" name="saved_id" value="<?php echo $saved_id ?>" />
                <?php } ?>



                <input type="hidden" name="tr_action" value="<?php echo $can_edit ? 'ci_save_form_gene' : '' ?>" />
                <input type="hidden" name="bat[is_dna]" value="18" />
                <?php if (isset($mid) && $mid > 0) : ?>
                    <input type="hidden" name="bat[mid]" value="<?php echo $mid ?>" />
                <?php endif; ?>
            </div>

        <?php
        } else if ($act == 'view') {
        ?>
            <div class="frow">
                <div class="col3">
                    <h4>Result Files</h4>
                    <div class="req_result_pdf">
                        <ul class="listresultfiles">
                            <?php
                            if (!empty($bat['result_pdf'])) {
                                $rs_files = explode(',', $bat['result_pdf']);
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

                <div class="col3">
                    <h4>Req Files</h4>
                    <div class="card_file">
                        <?php
                        if (!empty($bat['original_req'])) {
                            $result_dir = tr_get_result_upload_dir();
                            $original_req = $result_dir['url'] . '/' . $bat['original_req'];
                        ?>
                            <span><a href="<?php echo $original_req ?>" class="original_req_view" target="_blank">Paper Req</a></span>

                        <?php
                        } else {
                            echo '<span></span>';
                        }
                        ?>
                    </div>
                </div>
                <div class="col3 notprint">
                    <h4>Issue Photos</h4>
                    <div class="card_file">
                        <span class="view">
                            <?php
                            $dir = tr_get_issue_photo_dir();
                            $fname = isset($bat['issue_photo']) ? $bat['issue_photo'] : '';
                            if (!empty($fname) && file_exists($dir['path'] . '/' . $fname) && filesize($dir['path'] . '/' . $fname) > 0) {
                                echo '<a href="' . $dir['url'] . '/' . $fname . '">View File</a>';
                            }
                            ?>
                        </span>
                    </div>

                </div>

            </div>
            <br><br>
            <input type="hidden" id="number_assen" value="<?php echo isset($bat['assen']) ? $bat['assen'] : '' ?>" />
            <a class="button print_assen_btn" style="<?php if (empty($bat['assen'])) echo 'display:none' ?>" href="#">Print Barcode</a>
        <?php
        } ?>

        <?php trpdd_form_unique_id() ?>

    </form>
    <input type="hidden" id="position" value="<?php echo isset($bat['position']) ? $bat['position'] : '' ?>" />
</div>

<?php
if ($act == 'print') {
    if (!isset($print_canvas)) echo '<script>window.print();</script>';
} else {
?>

    <script>
        var $alwayshowprintlb = <?php echo !tr_current_role_in(array('office_manager', 'doctor')) ? 'true' : 'false'; ?>;
        var $pa_required = false;
        var $sign_dir = '<?php echo $sign_dir['url'] ?>';
        var $t_assen = false;
        var $changebychild = false;
        jQuery(function($) {

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
                                btype: 'gene'
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
                    $('.requesting_patients_last_auto').attr('autocomplete', 'new-password');
                    if ($('.nh_address').is(':checked') && $(this).val() != '') {

                        $('[name="pa[pa_address]"]').val($cli_opt.data('address'));
                        $('[name="pa[pa_city]"]').val($cli_opt.data('city'));
                        $('[name="pa[pa_state]"]').val($cli_opt.data('state')).change();
                        $('[name="pa[pa_zip]"]').val($cli_opt.data('zip'));

                        if ($('[name="pa[pa_phone]"]').val() == '' && $cli_opt.data('phone')) {
                            $('[name="pa[pa_phone]"]').val($cli_opt.data('phone'));
                        }
                    }


                    change_clinic_printbtn();

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

                    $('.groupparent.by_clinic').removeClass('cactive');
                    $('.groupparent.by_clinic.cli_id_' + $cli_id).addClass('cactive');

                    if ($('#p_condition').length > 0 && $('#p_condition').hasClass('showallcl') == false) {
                        if ($('option:selected', $(this)).data('reason')) {
                            $('#p_condition').show();
                        } else {
                            $('#p_condition').hide();
                        }
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
                    var $pr = $(this).parents('.groupparent');
                    var $prcb = $('.group_label input', $pr);
                    if ($(this).is(':checked')) {
                        $changebychild = true;
                        $prcb.prop('checked', true).change();
                    } else {
                        if ($('.c input.cb_test:checked', $pr).length == 0) {
                            $prcb.prop('checked', false).change();
                        }
                    }
                })
                .on('change', '.batcustom_profile', function() {
                    var $pr = $(this).parents('.groupparent');
                    if ($(this).is(":checked")) {
                        if (!$changebychild) {
                            $('.c input', $pr).prop('checked', true);
                        }
                        $('.specimens', $pr).addClass('grequired');
                    } else {
                        if (!$changebychild) {
                            $('.c input', $pr).removeAttr('checked');
                        }
                        $('.specimens', $pr).removeClass('grequired');
                        $('.specimens', $pr).removeClass('gerror');
                    }
                    $changebychild = false;

                })
                .on('submit', '#requisition_form', function() {
                    if ($('#confirm_assen').val() != $('[name="bat[assen]"]').val()) {
                        alert('Please Confirm Accession Number');
                        $('#confirm_assen').focus();
                        return false;
                    }
                    if ($('.panel_requests').length == 0) return;
                    var $spec_required = false;
                    $('.specimens.grequired').each(function() {
                        if ($('input:checked', $(this)).length == 0) {
                            $spec_required = true;
                            $('input', $(this)).focus();
                            $(this).addClass('gerror');
                        }
                    })
                    if ($spec_required) {
                        alert('Please select least one Specimen Source');
                        return false;
                    } else if ($('.panel_requests input.cb_test:checked').length == 0 && !$('#requisition_form').hasClass('only_save_form')) {
                        alert('You must select least 1 test');
                        return false;
                    }
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
                .on('click', '.only_save_form', function() {
                    $('form [required]').removeAttr('required');
                })
                .on('change', 'select#col_id_sel', function() {
                    var $phone = $('option[value="' + $(this).val() + '"]', $(this)).data('phone');
                    $('[name="col[col_phone]"]').val($phone);
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
                })
                .on('change', '[name="pa[pa_last]"]', function() {
                    if ($('[name="ins1[ins_relation]"][value="Self"]').is(":checked")) {
                        $('[name="ins1[ins_last]"]').val($('[name="pa[pa_last]"]').val());
                    }
                })
                .on('change', '[name="pa[pa_first]"]', function() {
                    if ($('[name="ins1[ins_relation]"][value="Self"]').is(":checked")) {
                        $('[name="ins1[ins_first]"]').val($('[name="pa[pa_first]"]').val());
                    }
                })
                .on('click', '[name="ins1[ins_relation]"]', function() {
                    $('[name="pa[pa_last]"]').change();
                    $('[name="pa[pa_first]"]').change();
                })
                .on('change', '[name="pa[pa_first]"], [name="pa[pa_last]"], [name="pa[pa_dob]"], [name="pa[pa_ID]"]', function() {
                    var $fname = $('[name="pa[pa_first]"]').val();
                    var $lname = $('[name="pa[pa_last]"]').val();
                    var $dob = $('[name="pa[pa_dob]"]').val();
                    var $mid = $('[name="bat[mid]"]').val();

                    if ($fname != '' && $lname != '' && $dob != '' && (!$mid || $mid == '')) {
                        $.ajax({
                            data: {
                                tr_action: "ci_check_number_assen",
                                btype: 'gene',
                                fname: $fname,
                                lname: $lname,
                                dob: $dob,
                                mid: $mid
                            },
                            type: 'post',
                            dataType: 'json',
                            success: function(json) {
                                if (json.status == 'dublicate') {
                                    if (!confirm("A requisition for this patient already exists. Do you want to create an additional one?")) {
                                        $('[name*="pa[pa"]').each(function() {
                                            $(this).val('');
                                        })
                                    }
                                }
                            }
                        });
                    }
                })
                .on('click change', '.groupparent .group_label input.cb_test', function() {
                    var $rl_id = $(this).data('relate_to');
                    var $rl_ids = ($rl_id === undefined || $rl_id === null || $rl_id === '') ? [] : String($rl_id).split(',').map(function($id) {
                        return $.trim(String($id));
                    }).filter(function($id) {
                        return $id !== '';
                    });
                    var $pl = $('.groupparent.parent_' + $(this).val().toLowerCase() + ' .group_label input');

                    if ($(this).is(':checked')) {
                        if ($pl.length && !$pl.is(':checked')) {
                            $pl.prop('checked', true).change();
                        }
                        if ($rl_ids.length > 0) {
                            for (var i = 0; i < $rl_ids.length; i++) {
                                var $rl = $('.groupparent .group_label input[value="GROUP_' + $rl_ids[i] + '"]');
                                if (!$rl.length || $rl.is(':checked')) {
                                    continue;
                                }
                                $rl.prop('checked', true).change();
                            }
                        }

                    } else {
                        if ($pl.length && $pl.is(':checked')) {
                            $pl.prop('checked', false).change();
                        }
                        if ($rl_ids.length > 0) {
                            for (var j = 0; j < $rl_ids.length; j++) {
                                var $rl_uncheck = $('.groupparent .group_label input[value="GROUP_' + $rl_ids[j] + '"]');
                                if (!$rl_uncheck.length || !$rl_uncheck.is(':checked')) {
                                    continue;
                                }
                                $rl_uncheck.prop('checked', false).change();
                            }
                        }
                    }
                })
                .on('click', '.add_new_testing', function() {
                    var $nid = parseInt($(this).data('nextid'));
                    var $tb = $(this).parents('.testing_ordered_box').find('.testing_ordered_list');
                    $new = $tb.find('tr:first-child').html();
                    $new = $new.replace(/\[[0-9]+\]\"/g, '[' + $nid + ']"');
                    $new = $('<tr>' + $new + '</tr>');
                    $('input[type="text"]', $new).val('');
                    $('input[type="radio"]', $new).removeAttr('checked');
                    $tb.append($new);
                    $(this).data('nextid', $nid + 1);
                });


            $('.tooltip').tooltip({
                content: function() {
                    return $(this).attr('title');
                }
            });

            $('#attach_face_sheet').change();

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
                        $('.requesting_patients_last_auto').attr('autocomplete', 'new-password');
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
            $('.requesting_patients_last_auto').attr('autocomplete', 'new-password');

            $('[name="bat[doc_ID]"]').change();
            $('[name="bat[assen]"]').change();
            if ($('[name="auto_generated"]').is(":checked")) {
                $('#number_assen,#confirm_assen').removeAttr('required').val('').attr('readonly', '1');

            }

            change_clinic_printbtn();
            if ($('[name="bat[cli_ID]"]').val() !== '') {
                $('[name="bat[cli_ID]"]').change();
            }

        })
    </script>
<?php
}
?>
