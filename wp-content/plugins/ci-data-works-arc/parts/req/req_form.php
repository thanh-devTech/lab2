<?php
global $wpdb;
global $current_role, $form_error;
global $blog_id;
global $ci_all_btypes;


$btype                  = 'req';
$table_batch            = 'ci_batches_req';
$table_report           = 'ci_reports_req';
$table_results          = 'ci_results_req';

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

$settings               = get_option('ci_req_settings_req', array());
$show_npi               = tr_get_option('ci_pdf_data', 'req_show_npi');
$print_times            = tr_get_option('ci_pdf_data', 'req_print_times', 1);
if (!isset($settings['disable_auto_doc'])) {
    $settings['disable_auto_doc'] = false;
}


$user_id                = get_current_user_id();
$dagnostic_codes        = tr_pdd_get_dagnostic_codes('req');
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

            trpdd_log_user(get_current_user_id(), 'approve_rs', $report['assen'], $report['mid'], 'req', '', '', $report['assen']);

            trpdd_add_cron_after_approve('req');
        }
    }
    wp_send_json($return);
}


$billing_page = isset($billing_page) ? $billing_page : false;

if (isset($_REQUEST['trajax'])) {
    $hide_tabs = true;
}

$tabs = array(
    '' => 'Req Form',
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
                $link = Tr_Base_Class_V6::link(array('tab' => $k, 'act' => '', 'id' => '', 'mid' => '', 's' => ''), false);
                echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
            }
            ?>
        </h2>

<?php
    }
} else if ($billing_page) {
    include_once(TRCPDD_PATH . 'pages/formdata.php');
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
    include(trpdd_get_file_path('req', 'req_list'));
    return;
} else if ($tab == 'saved_form') {
    if (!empty($act) && $act != 'delete') {
        $saved_id = $_REQUEST['id'];
    } else {
        include(trpdd_get_file_path('req', 'req_saved'));
        return;
    }
} else if ($tab == 'prior_authorization') {
    include(trpdd_get_file_path('req', 'prior_authorization'));
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
        $action_form    = 'ci_save_form_req';
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

    $collector = $wpdb->get_row("select * from ci_collector where col_ID ='{$bat['col_ID']}' ", ARRAY_A);

    $bat['panel_tests_requested_str'] = $bat['panel_tests_requested'];
    $bat['panel_tests_requested'] = explode(',', @$bat['panel_tests_requested']);
    $bat['panel_tests_requested'] = array_map('trim', $bat['panel_tests_requested']);
    $bat['panel_tests_requested'] = array_map('strtoupper', $bat['panel_tests_requested']);
    $bat['btype'] = 'req';
    //var_dump(@$bat);
    $batch_key = $bat['assen'];

    if ($billing_page) {

        $sql = "SELECT
            lab.*,
            ba.*,
            ba.patient_id as pa_ID,
            rl.lab_additional as rl_lab_additional,
            re.*
        FROM ci_reports_req re
    
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
        $action_form = 'ci_save_req';
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


    $action_form = 'ci_save_req';

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

if (isset($_REQUEST['debug'])) {
    var_dump($sql_clinic);
}
?>
<div class="wrap_form wrap">

    <?php if ($act == 'print') : ?>
        <style>
            body {
                zoom: 80% !important;
            }
        </style>

    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" data-btype="req" class="need_validate main_form <?php if (!$bat || !$bat['mid']) echo 'emptyform' ?>" id="requisition_form">
        <?php if ($act == 'print') include(trpdd_get_file_path('global', 'print_req_header')); ?>
        <h2 class="center">Requisition Form</h2>
        <?php Tr_Session_Class::show() ?>

        <?php if ($act == 'view' || $billing_page) : ?>
            <div class="right foutrow">
                <label>Accession Number:</label>
                <span class="text bold binline" style="width:200px"><?php echo isset($bat['assen']) ? $bat['assen'] : '' ?>&nbsp;&nbsp;</span>
            </div>
        <?php endif; ?>

        <div class="frow blabel hlabel">
            <div class="col3">
                <?php if (count($labs) > 1) {
                    $labs_options = array('' => 'Select Lab');
                    $only_labids = array();
                    if (count($labs) > 1 && tr_current_role_in(array('office_manager', 'doctor'))) {
                        foreach ($clinics as $clinic) {
                            $only_labids[] = $clinic['cli_lab-ID'];
                        }
                    }
                    if (isset($mid) && $mid > 0) {
                        $only_labids[] = $bat['lab_id'];
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
                ?>
                    <label>Lab</label>
                    <?php tr_select_option('bat[lab_id]', $labs_options, $bat['lab_id'], array('attr' => 'required')) ?>
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
            <div class="col3" id="cli_change_div">
                <label>Ordering Physician</label>
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
                <label><?php echo 'Collector Name' ?></label>

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
                <?php } ?>

                <label>Collector Phone Number</label>
                <input type="text" class="has_phone" name="col[col_phone]" value="<?php echo isset($collector['col_phone']) ? $collector['col_phone'] : '' ?>" <?php if (!empty($collector['col_phone'])) echo 'readonly' ?> />
            </div>

            <div class="col3">

                <label>Specimen Information</label>

                <div class="frow">
                    <div class="ilabel">
                        <?php
                        $types = tr_pdd_get_type_of_specimens('req');

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
            <div class="col4">
                <label>Collection agency name</label>
                <input type="text" name="bat[collection_agency_name]" value="<?php echo isset($bat['collection_agency_name']) ? $bat['collection_agency_name'] : '' ?>" />
            </div>
            <div class="col4">
                <label>Collection agency address</label>
                <input type="text" name="bat[collection_agency_address]" value="<?php echo isset($bat['collection_agency_address']) ? $bat['collection_agency_address'] : '' ?>" />
            </div>
            <div class="col4">
                <label>Collection agency city</label>
                <input type="text" name="bat[collection_agency_city]" value="<?php echo isset($bat['collection_agency_city']) ? $bat['collection_agency_city'] : '' ?>" />
            </div>
            <div class="col4">
                <label>Collection agency state</label>
                <input type="text" name="bat[collection_agency_state]" value="<?php echo isset($bat['collection_agency_state']) ? $bat['collection_agency_state'] : '' ?>" />
            </div>
        </div>

        <div class="frow">
            <div class="col4">
                <label>Collection agency zip</label>
                <input type="text" name="bat[collection_agency_zip]" value="<?php echo isset($bat['collection_agency_zip']) ? $bat['collection_agency_zip'] : '' ?>" />
            </div>
            <div class="col4">
                <label>Collection agency phone</label>
                <input type="text" name="bat[collection_agency_phone]" value="<?php echo isset($bat['collection_agency_phone']) ? $bat['collection_agency_phone'] : '' ?>" />
            </div>
            <div class="col4">
                <label>Collection datetime</label>
                <input type="text" class="hasdatetime" name="bat[collection_datetime]" value="<?php echo isset($bat['collection_datetime']) ? $bat['collection_datetime'] : '' ?>" />
            </div>
            <div class="col4">
                <label>Shipment picked up datetime</label>
                <input type="text" class="hasdatetime" name="bat[shipment_picked_up_datetime]" value="<?php echo isset($bat['shipment_picked_up_datetime']) ? $bat['shipment_picked_up_datetime'] : '' ?>" />
            </div>
        </div>

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
                    <input type="text" class="<?php if ((!$notallowchangepatient || empty($pa['pa_dob']) || $pa['pa_dob'] == '0000-00-00')) echo 'hasdate' ?> birthday limitfuture" name="pa[pa_dob]" value="<?php echo isset($pa['pa_dob']) ? $pa['pa_dob'] : '' ?>" <?php if ($notallowchangepatient && !empty($pa['pa_dob'])) echo 'readonly' ?> required="" />
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

        <?php
        $headers  = $wpdb->get_results("select * from ci_test_aoe_headers 
			                                    where tag_btype='req' 
			                                    and tag_status = 1
			                                    order by tag_order asc");
        foreach ($headers as $header) {
            $questions = $wpdb->get_results("select * from ci_test_aoe 
				                            where ta_btype= 'req' 
				                                and ta_header_id = {$header->tag_ID}
				                                order by ta_order asc, ta_ID asc");
            if (count($questions) == 0) {
                continue;
            }
        ?>
            <div class="frow">
                <h4><?php echo $header->tag_header ?></h4>
            </div>

            <?php
            echo '<div class="frow">';
            $last_col_num = 0;
            $last_type = '';
            $group_adding = '';
            $cnum = 0;
            foreach ($questions as $question) {
                $col_num = 3;

                if (in_array($question->ta_question_type, array('texta', 'cond2', 'cond4', 'cond8'))) {
                    $col_num = 1;
                }

                $value = $bat[$question->ta_slug];
                $name = 'bat[' . $question->ta_slug . ']';

                if ($col_num == 1) {
                    echo '</div><div class="frow full">' . "\n";
                } else {
                    if ($cnum > 0 && ($cnum % $col_num == 0 || ($last_col_num > 0 && $last_col_num != $col_num))) {
                        echo '</div><div class="frow">' . "\n";
                    } else if ($question->ta_question_type == 'cb2' && $last_type != '' && $last_type != $question->ta_question_type) {
                        echo '</div><div class="frow cb2">' . "\n";
                        $cnum = 0;
                    }
                    echo '<div class="col' . $col_num . '">' . "\n";
                }

                if (!in_array($question->ta_question_type, array('checkbox', 'cb2', 'cond4', 'cond2', 'cond8'))) {
                    echo '<label>' . $question->ta_question . '</label><br>' . "\n";
                }

                $cnum++;
                $last_col_num   = $col_num;
                $last_type      = $question->ta_question_type;

                switch ($question->ta_question_type) {
                    case 'select':
                    case 'radio':
                        $type = 1;
                        if (strpos($question->ta_answers, "\r") > 0) {
                            $options = explode("\r", $question->ta_answers);
                            $type = 2;
                        } else {
                            $options = explode('|', $question->ta_answers);
                        }

                        echo '<select name="' . $name . '">';
                        echo '<option value="">Select</option>';
                        foreach ($options as $vl) {
                            list($k, $txt) = explode(',', $vl);
                            if (empty($txt)) $txt = $k;

                            $k = trim($k);
                            $selected = $value == $k ? 'selected' : '';
                            echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
                        }
                        echo '</select>' . "\n";
                        break;

                    case 'mselect':
                        $type = 1;
                        if (strpos($question->ta_answers, "\r") > 0) {
                            $options = explode("\r", $question->ta_answers);
                            $type = 2;
                        } else {
                            $options = explode(',', $question->ta_answers);
                        }
                        $adding_slug = $question->ta_slug . '_data';
                        $adding_name = 'bat[' . $adding_slug . '_json]';
                        $adding_value = '';
                        if ($question->ta_start_end_date) {
                            $adding_value = json_decode($bat[$adding_slug], true);
                            if (!in_array($adding_slug, $exist_cols)) {
                                $wpdb->query("alter table ci_batches_req add column {$adding_slug} text");
                            }
                        }

                        echo '<select name="' . $name . '[]" data-slug="' . $question->ta_slug . '" multiple class="js_select2 ' . ($question->ta_start_end_date ? 'has_adding_mselect' : '') . '">';
                        $values = explode('|', $value);
                        foreach ($options as $txt) {
                            $txt = trim($txt);
                            $selected = in_array($txt, $values) ? 'selected' : '';
                            echo '<option value="' . $txt . '" ' . $selected . '>' . $txt . '</option>';

                            if ($question->ta_start_end_date) {
                                $group_adding .= '<div class="col3 adding_mselect mselect_' . $question->ta_slug . '" data-value="' . $txt . '">';
                                $group_adding .= '<fieldset>';
                                $group_adding .= '<legend><label>' . $txt . ' &nbsp;&nbsp; ';
                                $group_adding .= '</label></legend>';
                                $group_adding .= '<div class="frow">';
                                $group_adding .= '<div class="col2">';
                                $group_adding .= '<label>Start</label>';
                                $group_adding .= '<input type="text" class="hasdate" name="' . $adding_name . '[' . $txt . '][0]" value="' . $adding_value[$txt][0] . '"/> ';
                                $group_adding .= '</div>';
                                $group_adding .= '<div class="col2">';
                                $group_adding .= '<label>End</label>';
                                $group_adding .= '<input type="text" class="hasdate" name="' . $adding_name . '[' . $txt . '][1]" value="' . $adding_value[$txt][1] . '"/> ';
                                $group_adding .= '</div>';
                                $group_adding .= '</div>';
                                $group_adding .= '</div>';

                                if ($txt == 'Other') {
                                    $group_adding .= '<div class="col3 adding_mselect mselect_' . $question->ta_slug . '" data-value="' . $txt . '">';
                                    $group_adding .= '<fieldset>';
                                    $group_adding .= '<legend><label>' . $txt . ' &nbsp;&nbsp; ';
                                    $group_adding .= '</label></legend>';
                                    $group_adding .= '<div class="frow">';
                                    $group_adding .= '<input type="text" name="' . $adding_name . '[' . $txt . '][Other]" value="' . $adding_value[$txt]['Other'] . '"/> ';
                                    $group_adding .= '</div>';
                                    $group_adding .= '</div>';
                                }
                            }
                        }
                        echo '</select>' . "\n";

                        break;

                    case 'checkbox':
                        echo '<input type="hidden" name="' . $name . '" value="0"/>';
                        echo '<label> <input type="checkbox" name="' . $name . '" value="1" ' . checked($value, '1', false) . '/> ';
                        echo $question->ta_question;
                        echo '</label>' . "\n";
                        break;

                    case 'cb2':
                        $values = explode('|', $value);

                        echo '<fieldset>';
                        echo '<legend><label>' . $question->ta_question . ' &nbsp;&nbsp; ';
                        echo '<input type="hidden" name="' . $name . '[0]" value="0"/>';
                        echo '<input type="checkbox" name="' . $name . '[0]" value="1" ' . checked($values[0], '1', false) . '/> ';
                        echo '</label></legend>';
                        echo '<div class="frow">';
                        echo '<div class="col2">';
                        echo '<label>#</label>';
                        echo '<input type="text"  name="' . $name . '[1]" value="' . $values[1] . '"/>' . "\n";
                        echo '</div>';
                        echo '<div class="col2">';
                        echo '<label>Date</label>';
                        echo '<input type="text" class="hasdate" name="' . $name . '[2]" value="' . $values[2] . '"/>' . "\n";
                        echo '</div>';

                        echo '</div>';
                        echo '</fieldset>';
                        break;

                    case 'date':
                        echo '<input type="text" class="hasdate" name="' . $name . '" value="' . $value . '"/>' . "\n";
                        break;
                    /*
						case 'radio':
							$options = explode('|',$question->ta_answers);
							foreach($options as $vl)
							{
								list($k,$txt) = explode(',',$vl);
								$k = trim($k);
								$selected = $value==$k?'checked':'';
								echo ' <label><input type="radio" name="'.$name.'" value="'.$k.'" '.$selected.'/> '.$txt.'</label> ';
							}
							break;*/

                    case 'texta':
                        echo '<textarea name="' . $name . '">' . $value . '</textarea>' . "\n";
                        break;

                    case 'textu':
                        $values = explode('|', $value);
                        if (empty($values[1])) {
                            $values[1] = $question->ta_answers;
                        }
                        echo '<input type="text" name="' . $name . '[0]" value="' . $values[0] . '" style="width:60%"/>';
                        echo '<input type="text" name="' . $name . '[1]" value="' . $values[1] . '" style="width:35%"/>';
                        break;

                    case 'cond2':
                        $values = explode('|', $value);
                        $options = explode('|', $question->ta_answers);
                        for ($j = 0; $j < 2; $j++) {
                            echo '<div class="col4">';
                            if (count($options) >= 2) {
                                echo '<label>' . $options[$j] . '</label>';
                            }
                            echo '<input type="text" name="' . $name . '[' . $j . ']" value="' . $values[$j] . '"/>';
                            echo '</div>';
                        }
                        echo '<div class="col4">&nbsp;</div>' . "\n";
                        break;

                    case 'cond4':
                        $values = explode('|', $value);
                        $options = explode('|', $question->ta_answers);

                        if (count($options) < 4) {
                            echo '<label><b>' . $question->ta_question . '</b></label></div><div class="frow">';
                        }

                        $key = 0;
                        for ($i = 1; $i <= 2; $i++) {
                            echo '<div class="col2 ">';
                            echo '<fieldset>';
                            $col2_label = '';
                            if (count($options) == 2) {
                                $col2_label = $options[$i - 1];
                                echo '<legend>' . $col2_label . '</legend>';
                            }
                            echo '<div class="frow">';
                            for ($j = 1; $j <= 2; $j++) {
                                echo '<div class="col2">';
                                echo '<label>' . ($j == 1 ? 'AUC' : 'SD') . '</label>';
                                echo '<input type="text" name="' . $name . '[' . $key . ']" value="' . $values[$key] . '"/>';
                                echo '</div>';
                                $key++;
                            }
                            echo '</div>';
                            echo '</fieldset>';
                            echo '</div>' . "\n";
                        }

                        break;

                    case 'cond8':
                        $values = explode('|', $value);
                        $options = explode('|', $question->ta_answers);
                        if (count($options) < 8) {
                            echo '<label><b>' . $question->ta_question . '</b></label></div><div class="frow full2">' . "\n";
                        }

                        $key = 0;
                        for ($i = 1; $i <= 2; $i++) {
                            echo '<div class="col2 ">';
                            echo '<fieldset>';
                            $col2_label = '';
                            if (count($options) == 2) {
                                $col2_label = $options[$i - 1];
                                echo '<legend>' . $col2_label . '</legend>';
                            }
                            echo '<div class="frow">';
                            for ($j = 1; $j <= 4; $j++) {
                                $label = '&nbsp;';
                                if (in_array($col2_label, array('Normoxia', 'Hypoxia'))) {
                                    if ($j % 4 == 1) {
                                        $label = 'AUC';
                                    } else if ($j % 4 == 3) {
                                        $label = 'SD';
                                    }
                                }

                                echo '<div class="col4">';
                                //echo '<label>'.$label.'</label>';
                                echo '<input type="text" name="' . $name . '[' . $key . ']" value="' . $values[$key] . '"/>';
                                echo '</div>';

                                $key++;
                            }
                            echo '</div>';
                            echo '</fieldset>';
                            echo '</div>' . "\n";
                        }


                        break;

                    case 'calc_age':
                        if (!empty($bat['dob']) && $bat['dob'] != '0000-00-00') {
                            $value = trdd_dob2ages($bat['dob'], $bat['doc']);
                        }

                        echo '<input type="number" readonly class="calc" name="' . $name . '" value="' . $value . '"/>';
            ?>
                        <script>
                            jQuery(function($) {
                                $('[name="bat[doc]"],[name="bat[dob]"]').change(function() {
                                    var $dob = $('[name="bat[dob]"]').val();
                                    var $doc = $('[name="bat[doc]"]').val();
                                    var $age = trdd_dob2ages($dob, $doc);
                                    console.log($age);
                                    $('<?php echo '[name="' . $name . '"]' ?>').val($age);
                                })
                            })
                        </script>
        <?php
                        break;

                    default:
                        echo '<input type="text" name="' . $name . '" value="' . $value . '"/>';
                        break;
                }
                if ($col_num > 1) {
                    echo '</div>' . "\n"; //end col
                }
            }
            echo '</div>' . "\n"; //end row

            if (!empty($group_adding)) {
                echo '<div class="frow">';
                echo $group_adding;
                echo '<div>';
            }
        }
        ?>


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





        <?php

        if (!$billing_page || isset($_REQUEST['show_tests'])) { ?>
            <div class="frow ">
                <h4>Requisition Testing Requested</h4>
            </div>

            <div class="frow blabel">
                <input type="hidden" name="has_panel_requests" value="1" />

                <div id="default_requests" class="frow blabel panel_requests masonry" style="font-size:12px;column-count:<?php echo $settings['panel_column'] >= 2 ? $settings['panel_column'] : 5 ?>">
                    <?php
                    $groups = tr_pdd_get_panel_groups(null, 'req');

                    foreach ($groups as $group_id => $group) {

                        if (!$group['show']) {
                            continue;
                        }
                        $has_checked = false;
                        $group_class = '';

                        if ($group['parent'] > 0) {
                            $group_class .= ' parent_group_' . $group['parent'] . ' ';
                        }
                        if (!empty($group['relate_to'])) {
                            $group_class .= ' relate_to_group_' . $group['relate_to'] . ' ';
                        }


                        $group_key              = 'GROUP_' . $group_id;
                        $in_group               = @in_array($group_key, $bat['panel_tests_requested']);
                        $mid                    = isset($mid) ? $mid : 0;
                        if ($mid == 0 && isset($group['other_options']['auto_checked']) && $group['other_options']['auto_checked']) {
                            $in_group = true;
                        }

                        $group['color']         = trpdd_getContrastColor($group['bgcolor']);
                        $group['spec_bgcolor']  = strlen($group['bgcolor']) == 7 ? $group['bgcolor'] . '88' : $group['bgcolor'];
                        $group['box_bgcolor']   = strlen($group['bgcolor']) == 7 ? $group['bgcolor'] . '66' : $group['bgcolor'];

                        if ($act == 'print') {
                            $group['box_bgcolor'] = '#f8f8f8';
                            $group['spec_bgcolor'] = '#eeeeee';
                        }

                        $group_cb_class = ' batcustom_profile cb_test group_print_labels ';
                        if ($is_hold) {
                            $group_cb_class .= ' readonly ';
                        }

                    ?>
                        <div class="groupparent item <?php echo $group_class ?>" style="background:<?php echo $group['box_bgcolor'] ?>">
                            <label class="block bold group_label" style="background:<?php echo $group['bgcolor'] ?>;color: <?php echo $group['color'] ?>">

                                <input type="checkbox" class="<?php echo $group_cb_class ?>" name="bat[panel_tests_requested][]" value="<?php echo $group_key ?>" data-relate_to="<?php echo $group['relate_to'] ?>" data-limit_days="<?php echo $group['limit_days'] ?>" <?php checked($in_group) ?> />

                                <?php echo $group['label'] ?>
                            </label>

                        </div>
                    <?php
                    }
                    ?>

                </div>
            </div>

            <?php if (isset($settings['e_orderable_test']) && $settings['e_orderable_test']) { ?>
                <div class="col3 tests_group ">
                    <label>
                        <input type="hidden" name="bat[orderable_test]" value="0" />
                        <input type="checkbox" id="orderable_test_cb" class="cb_test" name="bat[orderable_test]" <?php checked($bat['orderable_test']) ?> value="1" />
                        Orderable Test
                    </label>
                </div>
            <?php } ?>

        <?php
        }



        if (!tr_get_option('ci_pdf_data', 'hide_physicalnotes')) { ?>
            <div class="frow">
                <h4>NOTES</h4>
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
        <?php
        } else {
        ?>
            <div class="frow dontprint">
                <div class="col2">
                    <label>Status</label>
                    <?php
                    tr_select_option('bat[batch_status]', tr_pdd_get_batch_statuses(false, false, false, 'req', 'show_in_req_form'), isset($bat['batch_status']) ? $bat['batch_status'] : '') ?>
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
                                echo '<label>Hold Reason:</label> ' . esc_html($hold_reason_arr['reason']);
                            } else {
                                echo '<label>Hold Reason:</label> ' . esc_html($hold_reason);
                            }
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
                        <?php trpdd_render_req_upload_files('original_req', isset($bat['original_req']) ? $bat['original_req'] : '', 'Choose Original Req', 'original_reqs', $act != 'view'); ?>
                    </div>
                </div>
        <?php
            }
        }

        ?>
        <div class="frow">
            <div class="col4">
                <label>Received Date</label>
                <input type="text" class="hasdatetime" name="bat[spec_received]" value="<?php echo $bat['spec_received'] ?>" />
            </div>
        </div>
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
                                where ts_type='req' ", ARRAY_A);
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

                    tr_bill_get_billing_code($ins1['ins_company'], $report['id'], $bat);
                    ?>
                </div>
            <?php
            } ?>

            <?php
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

            ?>

            <?php do_action('end_form_req', $bat) ?>

            <div class="frow submitdiv">
                <input type="submit" class="button-primary" style="margin-right:150px" value="Submit">
                <input type="submit" class="button-primary" id="submit_print_btn" name="save_print" value="Print Barcode / Submit">
                <a class="iprint button bsty2 " href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_print_req', 'id' => $bat['mid'], 'type' => 'req', 'tab' => '')) ?>">Print Form</a>
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
        var $alwayshowprintlb = <?php echo !tr_current_role_in(array('office_manager', 'doctor')) ? 'true' : 'false'; ?>;
        var $t_assen = false;
        var $t_check_limit = false;
        var $changebychild = false;
        var $currentdate = '<?php echo date_i18n('Y-m-d') ?>';
        var $sign_dir = '<?php echo $sign_dir['url'] ?>';

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
                            btype: 'req'
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
                                btype: 'req'
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
                    var $pr = $(this).parents('.groupparent');
                    var $limit_days = $(this).data('limit_days');
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

                })
                .on('click', 'a.remove_tests', function() {
                    $(this).parents('li').remove();
                })
                .on('submit', '#requisition_form', function() {
                    if ($('input.cb_test:checked').length == 0 && !$('#requisition_form').hasClass('only_save_form')) {
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
