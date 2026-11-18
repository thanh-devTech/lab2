<?php
global $wpdb, $current_role, $blog_id, $ci_all_btypes, $is_ci_admin, $wp_roles;

if (isset($_GET['tab']) && $_GET['tab'] == 'new_account_form_list') {
    $_GET['tab'] = 'new_account_form';
}
if (isset($_GET['current_role'])) {
    // $current_role = $_GET['current_role'];
}

$user               = wp_get_current_user();
$user_id            = get_current_user_id();
$lab_id             = 0;
$doctors            = array();
$blood_settings     = get_option('ci_req_settings_blood', array());
$need_assign_doctors = tr_get_option('ci_pdf_data', 'doc_o_cli');
$enable_abx         = tr_get_option('ci_pdf_data', 'new_abx');

$lab                = array();
$cli                = array();
$join_clinic        = '';
$join_clinic        = '';
$where_clinic       = '';
$where_group        = '';
$where_cli_group    = '';
$where_rep          = '';

$can_change_status  = false;
$can_add_new        = true;
$can_delete         = false;
$can_edit           = true;


if ($need_assign_doctors) {
    if (get_option('ci_last_assign_docs') > time() - 600 && !isset($_GET['fixdocall'])) {
        $need_assign_doctors = false;
    } else {
        update_option('ci_last_assign_docs', time(), 'no');
        $doctors = $wpdb->get_col("select doc_ID from ci_doc");
    }
}

$courier_types = array(
    'fedex' => 'FedEx',
    'ups' => 'UPS',
    'courier' => 'Courier',
);
$courier_types2 = array(
    'willcall' => 'Will Call',
    'dailypickup' => 'Daily Pickup',
);


$sign_dir           = tr_get_signature_dir();

if (isset($_POST['act']) && $_POST['act'] == 'remove_doc_signature') {
    $id = $_REQUEST['id'];
    ob_clean();
    $wpdb->update('ci_doc', array('doc_signature' => ''), array('doc_ID' => $id));
    wp_send_json(array('status' => 'ok'));
} else if (isset($_POST['act']) && $_POST['act'] == 'checkuniquekey') {
    $return = array('status' => 'ok');
    $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
    if ($id <= 0 && isset($_REQUEST['clinic_id'])) {
        $id = intval($_REQUEST['clinic_id']);
    }
    $fieldname = str_replace(array('cli[', ']'), '', $_POST['fieldname']);
    $fieldvalue = $_POST['fieldvalue'];
    $sql = "select cID from ci_clinic where {$fieldname} = '{$fieldvalue}' ";
    if ($id > 0) {
        $sql .= " and cID != {$id} ";
    }
    $exist = $wpdb->get_var($sql);
    $return['last_error'] = $wpdb->last_error;
    if ($exist) {
        $return['status'] = 'duplicate';
    }
    wp_send_json($return);
}


if ($current_role == 'clinic') {
    $cli_id = get_user_meta(get_current_user_id(), '_clinic_id', true);
    $cli = $wpdb->get_row("select * from ci_clinic where `cID`={$cli_id}", ARRAY_A);
} else if (isset($_REQUEST['clinic_id'])) {
    $cli_id = $_REQUEST['clinic_id'];
    $cli = $wpdb->get_row("select * from ci_clinic where `cID`={$cli_id}", ARRAY_A);
}

wp_enqueue_script('signature-pad', TRCPDD_URL . 'js/signature_pad.min.js', array(), TRCPDD_VS, false);
include_once(TRCPDD_PATH . 'pages/formdata.php');

if (tr_current_role_in(array('office_manager', 'clinic'))) {

    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where_clinic .= " and cID = {$current_clinic} ";
    } else {
        if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
            $cid = get_user_meta($user_id, '_clinic_id', true);
            $cli_ids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
            $cli_ids = implode(',', $cli_ids);
            if (empty($cli_ids)) {
                $cli_ids = '0';
            }
            $where_clinic .= " and cID in ( {$cli_ids} )";
        }
    }
    $where_rep .= " and 1=0 ";

    if ($act == '' || $act == 'add') {
        $act = 'list';
    }
    $can_add_new = false;
    $can_delete = false;
} else if (tr_current_role_in(array('reflab_manager'))) {
    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where_clinic .= " and cID = {$current_clinic} ";
    } else {
        $ref_lab_id = get_user_meta($user_id, '_ref_lab_id', true);
        $where_clinic .= " and (`cli_ref_lab_ID` = {$ref_lab_id} or (cli_ref_lab_ID=0 and `cli_lab-ID` = {$ref_lab_id})) ";
    }
    $where_rep .= " and 1=0 ";

    if ($act == '' || $act == 'add') {
        $act = 'list';
    }
    $can_add_new = false;
    $can_delete = false;
} else if (tr_current_role_in(array('lab_manager'))) { //Sales regional manager
    //find group
    $srm_where_clinic = ' and 1 = 0 ';
    $srm_where_reqp = ' and 1 = 0 ';
    $groups = $wpdb->get_results("select gr_ID, gr_members from ci_marketing_groups where find_in_set({$user_id}, gr_admins)  ");
    $all_member_ids = array();
    foreach ($groups as $group) {
        $member_ids = explode(',', $group->gr_members);
        $all_member_ids = array_merge($all_member_ids, $member_ids);
    }
    $all_member_ids = array_unique($all_member_ids);
    $all_member_ids = array_filter($all_member_ids, function ($val) use ($user_id) {
        return $val != $user_id;
    });
    if (!empty($all_member_ids)) {
        $placeholders = implode(',', array_fill(0, count($all_member_ids), '%d'));
        $srm_where_reqp = " and rep_ID IN ($placeholders) ";

        $sql = $wpdb->prepare(
            "SELECT cID FROM ci_clinic WHERE `cli_rep-ID` IN ($placeholders)",
            $all_member_ids
        );
        $cls_ids = $wpdb->get_col($sql);
        if (!empty($cls_ids)) {
            $cls_ids_str = implode(', ', $cls_ids);
            $srm_where_clinic = " and cID in  ({$cls_ids_str})";
        }
    }

    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where_clinic .= " and cID = {$current_clinic} ";
    } else {
        $where_clinic .= $srm_where_clinic;
    }
    $where_rep .= $srm_where_reqp;

    if ($act == '' || $act == 'add') {
        $act = 'list';
    }
    $can_add_new = false;
    $can_delete = false;
} else if (tr_current_role_in(array('multi_site_manager'))) {

    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where_clinic .= " and cID = {$current_clinic} ";
    } else if (!tr_get_option('ci_pdf_data', 'msm_can_show_all')) {
        $ms_cli_ids = get_user_meta($user_id, '_ms_clinic_ids', true);
        if (!is_array($ms_cli_ids) || empty($ms_cli_ids)) {
            $ms_cli_ids = $wpdb->get_col("select cID from ci_clinic where cli_status = 1 ");
            update_user_meta($user_id, '_ms_clinic_ids', $ms_cli_ids);
        }
        $ms_cli_ids_str = implode(',', $ms_cli_ids);

        if (empty($ms_cli_ids_str)) {
            $ms_cli_ids_str = '0';
        }
        $where_clinic .= " and cID in ( {$ms_cli_ids_str} )";
    }
    $where_rep .= " and 1=0 ";

    if ($act == '' || $act == 'add') {
        $act = 'list';
    }
    $can_add_new = false;
    $can_delete = false;
} else if ($current_role == 'lab_rep'  || $current_role == 'lab_rep_w2') {
    $conditions = trpdd_get_batch_conditions(0);
    if (!empty($conditions['where_clinic'])) {
        $where_clinic .= " and " . $conditions['where_clinic'];
    }
    $rep_id = get_user_meta(get_current_user_id(), '_rep_id', true);
    $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);

    $where_rep .= " and rep_ID = {$rep_id} ";

    if ($rep['rep_inactive'] == 1) {
        $can_change_status = false;
        $can_add_new = false;
        $can_edit = false;
        $can_delete = false;
    }
} else if ($current_role == 'distributor') {
    $dis_id = $wpdb->get_var("select dis_ID from ci_distributors where dis_user_ID = {$user_id}");

    $arr_marketing_groups = $wpdb->get_results("SELECT * FROM `ci_marketing_groups` where `gr_distributor_ID`= {$dis_id} and `gr_inactive`='0'");
    $g_ids = array(-1);
    foreach ($arr_marketing_groups as $g) {
        $g_ids[] = $g->gr_ID;
    }
    $g_ids = implode(',', $g_ids);


    $where_rep .= " and (`rep_marketing_group_ID` IN ({$g_ids}) or rep_distributor_ID={$dis_id}) ";

    $where_clinic .= " and (cli_distributor_ID={$dis_id} or (`rep_marketing_group_ID` IN ({$g_ids}) or rep_distributor_ID={$dis_id})) ";
    $where_group .= " and gr_distributor_ID = {$dis_id} ";

    //$can_change_status = true;
    $can_delete = false;
} else if (tr_current_role_in(array('administrator', 'administrator_p', 'AdminCIDW'))) {
    $can_change_status = true;
} else if ($current_role == 'lab_director') {
    $can_change_status = true;
    $can_delete = false;
} else if ($current_role == 'collector') {
    $can_change_status = false;
    $can_add_new = false;
    $can_delete = false;
    if ($act == '' || $act == 'add') {
        $act = 'list';
    }
    $col_id = get_user_meta($user_id, '_collector_id', true);
    $cids = $wpdb->get_col("select cli_ID from ci_clinic_collector where col_ID = {$col_id} ");
    $cids = implode(',', $cids);
    $where_rep .= '';
    $join_clinic .= "";
    if (empty($cids)) {
        $cids = '0';
    }
    $where_clinic .= " and cID in ({$cids})";
}

if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $where_clinic .= " and `cli_lab-ID` in ( {$lab_str} ) ";
}

if ($user->user_login == 'Administrat0r') {
    $can_delete = true;
}


if ($act == 'abx_local_resistance' || (isset($_REQUEST['table']) && $_REQUEST['table'] == 'ci_abx_clinic_local_resistance')) {
    include(trpdd_get_file_path('abx', 'resistance_data'));
    exit;
} else if ($act == 'export') {
    $output = array(
        'fromurl' => get_bloginfo('url'),
        'name' => get_bloginfo('name'),
        'clinics' => array(),
    );
    $rows = $wpdb->get_results("select * from ci_clinic", ARRAY_A);

    foreach ($rows as $row) {
        $row['doctors'] = $wpdb->get_results("select doc.* from ci_doc doc 
	                            JOIN ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
	                            where cd.cli_ID = {$row['cID']} ", ARRAY_A);

        $row['rep'] = $wpdb->get_row("select * from ci_lab_reps 
	                                where rep_ID = {$row['cli_rep-ID']}", ARRAY_A);

        $row['collectors'] = $wpdb->get_results("select co.* from ci_collector co 
	                                JOIN ci_clinic_collector cl ON cl.col_ID = co.col_ID 
	                                where cl.cli_ID = {$row['cID']} ", ARRAY_A);

        $row['managers'] = $wpdb->get_results("select ma.* 
	                                    from ci_clinic_manager ma 
	                                    where ma_cli_ID = {$row['cID']} 
	                                    group by ma_email", ARRAY_A);

        $output['clinics'][] = $row;
    }

    header('Content-disposition: attachment; filename=clinics.json');
    header('Content-type: application/json');
    wp_send_json($output);
    exit;
} else if ($act == 'import') {
    include(trpdd_get_file_path('accountforms', 'import_clinic'));
    exit;
} else if ($act == 'list' || $act == 'pdelete') {
    return include(trpdd_get_file_path('accountforms', 'new_account_list'));
} else if ($act == 'edit' || ($act == 'print' && $id > 0)) {
    $id = isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;
    $cli = $wpdb->get_row("select * from ci_clinic where cID = {$id}", ARRAY_A);

    $lab_id = $cli['cli_lab-ID'];
    $rep_id = $cli['cli_rep-ID'];
    $cli_id = $cli['cID'];

    $cli['cli_payor-mix'] = explode('|', $cli['cli_payor-mix']);

    $cli['cli_other_options'] = json_decode($cli['cli_other_options'], true);
} else {
    $cli['cli_lab-ID'] = CI_Base_Lab::get_current_lab_id();

    foreach ($ci_all_btypes as $btype) {
        $cli['cli_' . $btype] = true;
    }
}

if ($act == 'print') {
?>
    <link href="<?php echo TRCPDD_URL ?>css/print.min.css?<?php echo TRCPDD_VS ?>" media="all" type="text/css" rel='stylesheet' />
<?php
}

$col        = array();
$reps       = array('' => 'Select Rep');
$labs       = array('' => 'Select Lab');
$ref_labs   = array('' => 'Select Ref Lab');
$refls      = $wpdb->get_results("select * from ci_labs where (lab_is_ref = 1 or lab_is_ref = 2) ");

foreach ($refls as $l) {
    $ref_labs[$l->lab_ID] = $l->lab_dba_init . ' (' . $l->lab_name . ')';
}

if ($current_role == 'lab_rep'  || $current_role == 'lab_rep_w2') {
    $rep_id = get_user_meta(get_current_user_id(), '_rep_id', true);
    $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);
} else if (tr_current_role_in(array('administrator', 'AdminCIDW', 'administrator_p', 'clms'))) {

    if ($lab_id == 0) {
        $lab_id = CI_Base_Lab::get_current_lab_id();
    }
    $sql = "select * from ci_lab_reps where rep_inactive = 0 ";


    if ($lab_id > 0 && !defined('TRPDD_SAME_LAB')) {
        $rep['lab_ID'] = $lab_id;
        $sql .= " and lab_ID = {$lab_id}";
    }

    $rep_rs = $wpdb->get_results($sql . ' order by rep_first ASC ');
    $las = $wpdb->get_results("select * from ci_labs  where (lab_is_ref=0 or lab_is_ref=2) ");
    foreach ($las as $l) {
        $labs[$l->lab_ID] = $l->lab_dba_init;
    }
} else {
    if ($lab_id == 0) {
        $lab_id = CI_Base_Lab::get_current_lab_id();
    }
    if (CI_Base_Lab::has_user_labs()) {
        $lab_str = CI_Base_Lab::get_user_lab_ids_str();
        $where_rep .= " and lab_ID in ( {$lab_str} ) ";
    } else if (!defined('TRPDD_SAME_LAB')) {
        $where_rep .= " and lab_ID = $lab_id ";
    }
    $rep_rs = $wpdb->get_results("select * from ci_lab_reps where rep_inactive=0  {$where_rep} order by rep_first ASC ");
}


if (isset($rep_rs) && count($rep_rs) > 0) {
    foreach ($rep_rs as $r) {
        $reps[$r->rep_ID] = $r->rep_first . ' ' . $r->rep_last . ' (' . $r->rep_email . ')';
    }
}

if ($lab_id > 0) {
    $lab = $wpdb->get_row("select * from ci_labs where lab_ID = {$lab_id}", ARRAY_A);
}



if (isset($cli['cID']) && $cli['cID'] > 0) {
    $docs = $wpdb->get_results("select doc.* from ci_doc doc
                                JOIN ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
                                where cd.cli_ID = {$cli['cID']}
                                order by doc_first asc, doc_npi desc", ARRAY_A);



    $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$cli['cli_rep-ID']}", ARRAY_A);
    $col = $wpdb->get_results("select co.* from ci_collector co JOIN ci_clinic_collector cl ON cl.col_ID = co.col_ID where cl.cli_ID = {$cli['cID']} ", ARRAY_A);
    $managers = $wpdb->get_results("select * from ci_clinic_manager where ma_cli_ID = {$cli['cID']} group by ma_email", ARRAY_A);

    if (tr_get_option('ci_pdf_data', 'off_o_cli')) {
        $managers = $wpdb->get_results("select * from ci_clinic_manager group by ma_email", ARRAY_A);
    }

    if ($rep && !isset($reps[$rep['rep_ID']])) {
        $reps[$rep['rep_ID']] = $rep['rep_first'] . ' ' . $rep['rep_last'] . ' (' . $rep['rep_email'] . ')';
    }
    if (!$managers) {
        $managers = array();
        $managers[] = array(
            'ma_manager' => '',
            'ma_email' => $cli['cli_om-email'],
        );
    }

    $ms_manager_user = get_users(array(
        'role'   => 'multi_site_manager',
        'fields' => 'all' // đảm bảo có đủ property
    ));

    $ms_managers = array();

    if (!empty($ms_manager_user)) {
        foreach ($ms_manager_user as $user) {
            $ms_cli_ids = get_user_meta($user->ID, '_ms_clinic_ids', true);
            if (!is_array($ms_cli_ids)) {
                $ms_cli_ids = $wpdb->get_col("select cID from ci_clinic where cli_status = 1 ");

                update_user_meta($user->ID, '_ms_clinic_ids', $ms_cli_ids);
            }
            if (is_array($ms_cli_ids) && !in_array($cli['cID'], $ms_cli_ids)) {
                continue;
            }
            $ms_managers[] = array(
                'ma_ID'    => $user->ID,
                'ma_name'  => $user->display_name,
                'ma_email' => $user->user_email,
            );
        }
    }

    //fix duplicate
    $exist_docs = array();
    foreach ($docs as $di => $doc) {
        $dockey = strtolower($doc['doc_first'] . '_' . $doc['doc_last'] . '_' . $doc['doc_npi']);

        if (isset($exist_docs[$dockey])) {
            unset($docs[$di]);
            $wpdb->delete('ci_clinic_doc', array('doc_ID' => $doc['doc_ID'], 'cli_ID' => $cli['cID']));
        } else {
            $exist_docs[$dockey] = $doc['doc_ID'];
        }
    }
} else {
    if (isset($_GET['fromlog'])) {
        $dt = $wpdb->get_var("select data from `ci_log_forms` where id = {$_GET['fromlog']}");
        if (!empty($dt)) {
            $dt = json_decode($dt, true);
            extract($dt);

            if ($cli['cID'] > 0) {
                $wpdb->insert('ci_clinic', $cli);
            }

            $managers = $wpdb->get_results("select * from ci_clinic_manager where ma_cli_ID = {$cli['cID']} group by ma_email", ARRAY_A);


            $docs = $wpdb->get_results("select doc.* from ci_doc doc
                                JOIN ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
                                where cd.cli_ID = {$cli['cID']}", ARRAY_A);
        }
    }
    $cli['cID'] = 0;
    $cli['cli_status'] = 1;
    $cli['cli_show_submit_print_bc'] = 1;
    $cli['cli_auto_assen'] = 1;
    if ($act != 'print') {
        $cli['cli_sdate'] = date_i18n('Y-m-d');
    }


    if (tr_get_option('ci_pdf_data', 'doc_o_cli')) {
        $docs = $wpdb->get_results("select doc.* from ci_doc doc
                                JOIN ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
                                group by doc.doc_ID
                                order by doc_first asc, doc_npi desc
                                ", ARRAY_A);
    }

    if (tr_get_option('ci_pdf_data', 'off_o_cli')) {
        $managers = $wpdb->get_results("select * from ci_clinic_manager group by ma_email", ARRAY_A);
    }
}


?>

<div class="wrap">
    <?php if ($act == 'print') { ?>

        <?php
        $current_lab    = CI_Base_Lab::get_current_lab();
        $upload_dir     = tr_wp_upload_dir();
        $logo           = $current_lab['lab_logo'];
        $logo_path      = $upload_dir['basedir'] . '/logos/' . $logo;
        $logo_url       = $upload_dir['baseurl'] . '/logos/' . $logo;
        ?>
        <div style="text-align: center">
            <img src="<?php echo $logo_url ?>" align="center" style="max-width:400px;max-height:60px" />
        </div>
        <h2 class="center">New Account Form</h2>
    <?php } else { ?>
        <h2>
            <?php echo isset($cli['cID']) && $cli['cID'] > 0 ? 'EDIT' : 'NEW' ?> ACCOUNT
            <a class="add-new-h2 right" href="<?php echo Tr_Base_Class_V6::link(array('act' => 'list', 'id' => '')) ?>">Saved Forms</a>
        </h2>

        <?php Tr_Session_Class::show() ?>

        <form method="post" class="need_validate main_form" enctype="multipart/form-data">
        <?php
    }
        ?>

        <div class="frow">
            <div class="col2">
                <h4>Primary Facility Account Information</h4>
            </div>
            <div class="col2">
                <h4>Hours Of Operation</h4>
            </div>

        </div>

        <div class="frow blabel">
            <div class="col2">
                <div class="frow">
                    <label>Practice/Clinic Name</label>
                    <input type="text" name="cli[cli_clinic]" value="<?php echo isset($cli['cli_clinic']) ? $cli['cli_clinic'] : '' ?>" required="" />
                    <input type="hidden" name="cli[cID]" value="<?php echo isset($cli['cID']) ? $cli['cID'] : '' ?>" />


                </div>
                <div class="frow">
                    <?php if ($can_change_status) { ?>
                        <div class="col2">
                            <label>Status</label>
                            <select name="cli[cli_status]" required class="njs_select2">
                                <?php
                                foreach (array(1 => 'Active', 0 => 'Inactive') as $k => $vl) {
                                    $selected = $k == @$cli['cli_status'] ? 'selected' : '';
                                    echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    <?php } ?>
                    <div class="col2">
                        <label>Start Date</label>
                        <input type="text" name="cli[cli_sdate]" <?php echo (isset($cli['cID']) && $cli['cID'] > 0) ? 'readonly' : 'class="hasdate"' ?> value="<?php echo isset($cli['cli_sdate']) ? $cli['cli_sdate'] : ''; ?>" required="" />
                    </div>
                </div>
            </div>

            <div class="col2">

                <div class="frow blabel hours_area">
                    <div class="col5">
                        <label>Mon</label>
                        <input type="text" name="cli[cli_mon]" value="<?php echo isset($cli['cli_mon']) ? $cli['cli_mon'] : '' ?>" />
                    </div>
                    <div class="col5">
                        <label>Tue</label>
                        <input type="text" name="cli[cli_tue]" value="<?php echo isset($cli['cli_tue']) ? $cli['cli_tue'] : ''; ?>" />
                    </div>
                    <div class="col5">
                        <label>Wed</label>
                        <input type="text" name="cli[cli_wed]" value="<?php echo isset($cli['cli_wed']) ? $cli['cli_wed'] : ''; ?>" />
                    </div>
                    <div class="col5">
                        <label>Thu</label>
                        <input type="text" name="cli[cli_thu]" value="<?php echo isset($cli['cli_thu']) ? $cli['cli_thu'] : ''; ?>" />
                    </div>
                    <div class="col5">
                        <label>Fri</label>
                        <input type="text" name="cli[cli_fri]" value="<?php echo isset($cli['cli_fri']) ? $cli['cli_fri'] : ''; ?>" />
                    </div>

                </div>
                <div class="frow ">
                    <div class="col5 hours_area">
                        <label>Sat</label>
                        <input type="text" name="cli[cli_sat]" value="<?php echo isset($cli['cli_sat']) ? $cli['cli_sat'] : ''; ?>" />
                    </div>
                    <div class="col5 hours_area">
                        <label>Sun</label>
                        <input type="text" name="cli[cli_sun]" value="<?php echo isset($cli['cli_sun']) ? $cli['cli_sun'] : ''; ?>" />
                    </div>
                    <div class="col5">&nbsp;</div>
                    <div class="col5">
                        <label>Courier Type</label>
                        <select name="cli[cli_courier_type]" class="njs_select2 editable" style="width:100px;">
                            <?php
                            if (!empty($cli['cli_courier_type']) && !isset($courier_types[$cli['cli_courier_type']])) {
                                $courier_types[$cli['cli_courier_type']] = $cli['cli_courier_type'];
                            }
                            foreach ($courier_types as $tkey => $tname) {
                                $selected = isset($cli['cli_courier_type']) && $tkey == $cli['cli_courier_type'] ? 'selected' : '';
                                echo '<option value="' . $tkey . '" ' . $selected . '>' . $tname . '</option>';
                            }
                            ?>
                        </select>

                    </div>
                    <div class="col5">
                        <label>&nbsp;</label>
                        <select name="cli[cli_courier_type2]" class="njs_select2 editable" style="width:100px;">
                            <option value="">Select</option>
                            <?php
                            if (!empty($cli['cli_courier_type2']) && !isset($courier_types2[$cli['cli_courier_type2']])) {
                                $courier_types2[$cli['cli_courier_type2']] = $cli['cli_courier_type2'];
                            }
                            foreach ($courier_types2 as $tkey => $tname) {
                                $selected = isset($cli['cli_courier_type2']) && $tkey == $cli['cli_courier_type2'] ? 'selected' : '';
                                echo '<option value="' . $tkey . '" ' . $selected . '>' . $tname . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="frow blabel">
            <div class="col4">
                <label>Practice/Clinic Address</label>
                <input type="text" name="cli[cli_street]" value="<?php echo isset($cli['cli_street']) ? $cli['cli_street'] : ''; ?>" required="" />
            </div>
            <div class="col4">
                <label>City</label>
                <input type="text" name="cli[cli_city]" value="<?php echo isset($cli['cli_city']) ? $cli['cli_city'] : ''; ?>" required="" />
            </div>
            <div class="col4">
                <label>State</label>
                <?php tr_select_option('cli[cli_state]', tr_get_states(), isset($cli['cli_state']) ? $cli['cli_state'] : '', array('attr' => 'required')) ?>
            </div>
            <div class="col4">
                <label>Zip Code</label>
                <input type="text" name="cli[cli_zip]" value="<?php echo isset($cli['cli_zip']) ? $cli['cli_zip'] : ''; ?>" required="" />
            </div>
        </div>


        <div class="frow blabel">
            <div class="col2">
                <label>Phone No.</label>
                <input type="text" class="has_phone" name="cli[cli_om-phone]" value="<?php echo isset($cli['cli_om-phone']) ? $cli['cli_om-phone'] : ''; ?>" required="" />
            </div>
            <div class="col2">
                <label>Fax No.</label>
                <input type="text" class="has_phone" name="cli[cli_om-fax]" value="<?php echo isset($cli['cli_om-fax']) ? $cli['cli_om-fax'] : ''; ?>" required="" />
            </div>
        </div>

        <?php if (tr_get_option('ci_pdf_data', 'show_note_clinic')) { ?>
            <div class="frow">
                <label>Note</label>
                <textarea name="cli[cli_notes]"><?php echo esc_html(isset($cli['cli_notes']) ? $cli['cli_notes'] : '') ?></textarea>
            </div>
        <?php } ?>

        <?php if (tr_get_option('ci_pdf_data', 'msm_in_setupf')) { ?>

            <div class="frow">
                <h4>Multi-Site Manager</h4>
            </div>

            <div class="frow blabel">
                <div class="col2">
                    <label>Name</label>
                </div>
                <div class="col2">
                    <label>Email Address</label>
                </div>
            </div>
            <ol id="multisites_dv" class="more multirows showlistnum" style="overflow: auto;max-height:300px">
                <?php
                $max_ms = isset($ms_managers) && count($ms_managers) > 0 ? count($ms_managers) : ($act == 'print' && $id == 0 ? 5 : 1);
                for ($k = 0; $k < $max_ms; $k++) : ?>
                    <li class="frow  blabel pcorow <?php echo ($k == 0) ? 'default' : 'secondrow' ?>">
                        <div class="col2">
                            <input type="text" name="ms_manager[<?php echo $k ?>][ma_name]"
                                value="<?php echo isset($ms_managers[$k]['ma_name']) ? $ms_managers[$k]['ma_name'] : '' ?>" />
                            <input type="hidden" name="ms_manager[<?php echo $k ?>][ma_ID]"
                                value="<?php echo isset($ms_managers[$k]['ma_ID']) ? $ms_managers[$k]['ma_ID'] : '' ?>" />
                        </div>
                        <div class="col2">
                            <input type="email" name="ms_manager[<?php echo $k ?>][ma_email]"
                                value="<?php echo isset($ms_managers[$k]['ma_email']) ? $ms_managers[$k]['ma_email'] : '' ?>" />
                            <?php if ($act != 'print') : ?>
                                <a class="button rm_ms_manager_row remove_multi_row">-</a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endfor; ?>
            </ol>
            <?php if ($act != 'print') : ?>
                <a class="button add_multi_row add_multimanagerbtn" data-key="ms_manager" data-id="<?php echo $k ?>">+</a>
                <a class="button import_csv" data-type="ms_managers">Import from csv</a>
                <a href="<?php Tr_Base_Class_V6::link(array('trajax' => 'demo_ms_manager_csv')) ?>" target="_blank">Template csv</a>
            <?php endif; ?>

        <?php } ?>

        <div class="frow">
            <h4>Office Manager</h4>
        </div>

        <div class="frow blabel">
            <div class="col2">
                <label>Name</label>
            </div>
            <div class="col2">
                <label>Email Address</label>
            </div>
        </div>

        <ol id="managers_dv" class="more multirows" style="overflow: auto;max-height:300px">
            <?php
            $offic_required = tr_get_option('ci_pdf_data', 'forms_droffic') ? '' : 'required';
            $max_j = isset($managers) &&  count($managers) > 0 ? count($managers) : ($act == 'print' && $id == 0 ? 5 : 1);
            for ($j = 0; $j < $max_j; $j++) : ?>
                <li class="frow blabel pmanagerrow <?php echo ($j == 0) ? 'default' : 'secondrow' ?> ">
                    <div class="col2">
                        <input type="text" name="manager[<?php echo $j ?>][ma_manager]" value="<?php echo isset($managers[$j]['ma_manager']) ? $managers[$j]['ma_manager'] : '' ?>" <?php echo $offic_required ?> />
                        <input type="hidden" name="manager[<?php echo $j ?>][ma_ID]" value="<?php echo isset($managers[$j]['ma_ID']) ? $managers[$j]['ma_ID'] : '' ?>" />
                    </div>
                    <div class="col2">
                        <input type="email" name="manager[<?php echo $j ?>][ma_email]" value="<?php echo isset($managers[$j]['ma_email']) ? $managers[$j]['ma_email'] : '' ?>" <?php echo $offic_required ?> />
                        <?php if ($act != 'print') : ?>
                            <a class="button remove_multi_row">-</a>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endfor; ?>
        </ol>
        <?php if ($act != 'print') : ?>
            <a class="button add_multi_row add_managerbtn" data-key="manager" data-id="<?php echo $j ?>">+</a>
            <a class="button import_csv" data-type="managers">Import from csv</a>
            <a href="<?php Tr_Base_Class_V6::link(array('trajax' => 'demo_manager_csv')) ?>" target="_blank">Template csv</a>
        <?php endif; ?>

        <div class="frow">
            <h4>Sales Rep Information (required)</h4>
        </div>

        <div class="frow blabel" id="cli_change_div">

            <?php if (tr_current_role_in(array('administrator', 'AdminCIDW', 'administrator_p', 'clms'))) { ?>
                <div class="col4">
                    <label>Lab</label>
                    <?php if (count($labs) > 2) { ?>
                        <?php tr_select_option('lab[lab_ID]', $labs, $cli['cli_lab-ID'], array('attr' => 'required')) ?>
                    <?php } else { ?>
                        <div><?php echo $lab['lab_name'] ?></div>
                        <input type="hidden" name="lab[lab_ID]" value="<?php echo $lab['lab_ID'] ?>">
                    <?php } ?>
                </div>
                <div class="col4">
                    <label>Ref Lab</label>
                    <?php tr_select_option('cli[cli_ref_lab_ID]', $ref_labs, isset($cli['cli_ref_lab_ID']) ? $cli['cli_ref_lab_ID'] : 0) ?>
                    <input type="hidden" name="old_cli_ref_lab_ID" value="<?php echo isset($cli['cli_ref_lab_ID']) ? intval($cli['cli_ref_lab_ID']) : 0 ?>">
                </div>
                <div class="col4">
                    <label>Sales Rep Name</label>
                    <?php tr_select_option('rep[rep_ID]', $reps, isset($rep['rep_ID']) ? $rep['rep_ID'] : '', array('attr' => 'required')) ?>
                </div>
                <div class="col4">
                    <label>Account Manager</label>
                    <input type="text" name="cli[cli_account_manager]" value="<?php echo isset($cli['cli_account_manager']) ? $cli['cli_account_manager'] : ''; ?>" maxlength="50" />
                </div>
            <?php } else if ($current_role == 'lab_rep'  || $current_role == 'lab_rep_w2') { ?>
                <div class="col5">
                    <label>Sales Rep Last Name</label>
                    <input type="text" name="rep[rep_last]" value="<?php echo isset($rep['rep_last']) ? $rep['rep_last'] : ''; ?>" required="" />
                    <input type="hidden" name="rep[rep_ID]" value="<?php echo isset($rep['rep_ID']) ? $rep['rep_ID'] : ''; ?>" />
                </div>
                <div class="col5">
                    <label>First Name</label>
                    <input type="text" name="rep[rep_first]" value="<?php echo isset($rep['rep_first']) ? $rep['rep_first'] : ''; ?>" required="" />
                </div>
                <div class="col5">
                    <label>Middle Initial</label>
                    <input type="text" name="rep[rep_middle]" value="<?php echo isset($rep['rep_middle']) ? $rep['rep_middle'] : ''; ?>" />
                </div>
                <div class="col5">
                    <label>Account Manager</label>
                    <input type="text" name="cli[cli_account_manager]" value="<?php echo isset($cli['cli_account_manager']) ? $cli['cli_account_manager'] : ''; ?>" maxlength="50" />
                </div>
            <?php } else { ?>
                <div class="col4">
                    <label>Sales Rep Name</label>
                    <?php tr_select_option('rep[rep_ID]', $reps, @$rep['rep_ID'], array('attr' => 'required')); ?>
                </div>
                <div class="col4">
                    <label>Account Manager</label>
                    <input type="text" name="cli[cli_account_manager]" value="<?php echo isset($cli['cli_account_manager']) ? $cli['cli_account_manager'] : ''; ?>" maxlength="50" />
                </div>
                <div class="col4"></div>

            <?php } ?>

        </div>
        <div class="frow">
            <?php if (tr_get_option('ci_pdf_data', 'enable_clinic_group')) { ?>
                <div class="col4">
                    <label>Clinician Group</label>
                    <?php
                    $groups = $wpdb->get_results("select * from ci_clinician_groups  where 1  {$where_cli_group}");

                    if (isset($_GET['clig'])) {
                        $cli['cli_group_ID'] = $_GET['clig'];
                    }
                    ?>
                    <select name="cli[cli_group_ID]" class="njs_select2">
                        <option value="">Select Clinician Group</option>
                        <?php
                        foreach ($groups as $g) {
                            $selected = isset($cli['cli_group_ID']) && $g->clig_ID == $cli['cli_group_ID'] ? 'selected' : '';
                            echo '<option value="' . $g->clig_ID . '" ' . $selected . ' data-reps="|' . $g->clig_rep_ID . '|">' . $g->clig_name . '</option>';
                        }
                        ?>
                    </select>
                    <a class="add_new_group">Add New Clinician Group</a>
                    <div class="new_group" style="display: none;">
                        <label>New Group</label>
                        <input type="text" name="cli_new_group" value="" />
                    </div>
                </div>
            <?php } ?>
            <div class="col4">
                <label>Marketing Group</label>
                <?php
                if (tr_current_role_in(array('administrator', 'AdminCIDW', 'administrator_p', 'lab', 'distributor'))) {

                    $groups = $wpdb->get_results("select g.*,group_concat('|',rep.rep_ID,'|') as reps from ci_marketing_groups g
                    left JOIN ci_lab_reps rep ON rep.rep_marketing_group_ID = g.gr_ID
                    where 1  {$where_group}
                    Group by g.gr_ID ");


                ?>
                    <select name="cli[cli_marketing_group_ID]" class="njs_select2">
                        <option value="">Select Marketing Group</option>
                        <?php
                        foreach ($groups as $g) {
                            $selected = isset($cli['cli_marketing_group_ID']) && $g->gr_ID == $cli['cli_marketing_group_ID'] ? 'selected' : '';
                            echo '<option value="' . $g->gr_ID . '" ' . $selected . ' data-reps="' . $g->reps . '">' . $g->gr_name . '</option>';
                        }
                        ?>
                    </select>
                    <?php
                } else if ($current_role == 'lab_rep'  || $current_role == 'lab_rep_w2') {
                    $group_leader = tr_dpp_get_marketing_group_leader($user_id);
                    if ($group_leader) {
                    ?>
                        <input type="text" value="<?php echo $group_leader->gr_name ?>" readonly />
                        <input type="hidden" name="cli[cli_marketing_group_ID]" value="<?php echo $group_leader->gr_ID ?>" />
                    <?php
                    } else {
                        $group = tr_dpp_get_marketing_group($user_id);
                    ?>
                        <input type="text" value="<?php echo $group->gr_name ?>" readonly />
                        <input type="hidden" name="cli[cli_marketing_group_ID]" value="<?php echo $group->gr_ID ?>" />
                <?php
                    }
                }

                ?>
            </div>
            <div class="col4">
                <label>Distributor</label>
                <?php
                if ($current_role == 'distributor') {
                    $distributor = $wpdb->get_var("select dis_name from ci_distributors where dis_user_ID = {$user_id}");
                ?>
                    <input type="text" value="<?php echo $distributor ?>" readonly />
                <?php
                } else {
                    $groups = $wpdb->get_results("select dis.*,group_concat('|',rep.rep_ID,'|') as reps from ci_distributors dis
                    left JOIN ci_lab_reps rep ON rep.rep_distributor_ID = dis.dis_ID
                    Group by dis.dis_ID ");
                ?>
                    <select name="cli[cli_distributor_ID]" class="njs_select2">
                        <option value="">Select Distributor</option>
                        <?php
                        foreach ($groups as $dis) {
                            $selected = isset($cli['cli_distributor_ID']) && $dis->dis_ID == $cli['cli_distributor_ID'] ? 'selected' : '';
                            if (count($groups) == 1) $selected = 'selected';
                            echo '<option value="' . $dis->dis_ID . '" ' . $selected . ' data-reps="' . $dis->reps . '">' . $dis->dis_name . '</option>';
                        }
                        ?>
                    </select>
                <?php
                }
                ?>
            </div>
            <div class="col4">
                <label>Date</label>
                <input type="text" class="hasdate today" name="rep[rep_sdate]" value="<?php echo isset($rep['rep_sdate']) ? $rep['rep_sdate'] : ''; ?>" required="" />
            </div>
        </div>

        <div class="frow">
            <h4>PRIMARY FACILITY PHYSICIAN INFORMATION - Please fill out a separate forms for separate locations</h4>
        </div>

        <?php
        $doctor_cols = 7;
        if (tr_is_api_field_active('api_athena_api')) {
            $doctor_cols++;
        }
        if ($act == 'print') {
            $doctor_cols--;
        }
        ?>
        <div class="frow blabel">
            <div class="<?php echo 'col' . $doctor_cols ?>"><label>FIRST NAME</label></div>
            <div class="<?php echo 'col' . $doctor_cols ?>"><label>LAST NAME</label></div>
            <div class="col9 col6print"><label class="small2">SPECIALTY/CREDENTIAL</label></div>
            <div class="<?php echo 'col' . $doctor_cols ?>"><label>NPI</label></div>
            <?php
            if (tr_is_api_field_active('api_athena_api')) {
                echo '<div class="col9"><label>Athena</label></div>';
            }
            ?>
            <div class="<?php echo 'col' . $doctor_cols ?>"><label>PHONE NO.</label></div>
            <div class="<?php echo 'col' . $doctor_cols ?>"><label>EMAIL ADDRESS</label></div>
            <?php if ($act != 'print') { ?>
                <div class="<?php echo 'col' . $doctor_cols ?>"></div>
            <?php } ?>
        </div>
        <ol id="doctors_dv">
            <?php
            $max_j = isset($docs) && count($docs) > 0 ? count($docs) : ($act == 'print' && $id == 0 ? 8 : 1);
            for ($j = 0; $j < $max_j; $j++) : ?>
                <li class="frow blabel pdoctorrow <?php if ($j == 0) echo 'default' ?>">
                    <div class="<?php echo 'col' . $doctor_cols ?>">
                        <input type="text" name="doc[<?php echo $j ?>][doc_first]" value="<?php echo isset($docs[$j]['doc_first']) ? $docs[$j]['doc_first'] : ''; ?>" required="" maxlength="100" />

                        <input type="hidden" name="doc[<?php echo $j ?>][doc_ID]" value="<?php echo isset($docs[$j]['doc_ID']) ? $docs[$j]['doc_ID'] : ''; ?>" />
                    </div>
                    <div class="<?php echo 'col' . $doctor_cols ?>">
                        <input type="text" name="doc[<?php echo $j ?>][doc_last]" value="<?php echo isset($docs[$j]['doc_last']) ? $docs[$j]['doc_last'] : ''; ?>" required="" maxlength="100" />
                    </div>
                    <div class="col9 col6print">
                        <input type="text" name="doc[<?php echo $j ?>][doc_specialty]" value="<?php echo isset($docs[$j]['doc_specialty']) ? $docs[$j]['doc_specialty'] : ''; ?>" maxlength="30" />
                    </div>
                    <div class="<?php echo 'col' . $doctor_cols ?>">
                        <input type="text" name="doc[<?php echo $j ?>][doc_npi]" value="<?php echo isset($docs[$j]['doc_npi']) ? $docs[$j]['doc_npi'] : ''; ?>" <?php if (empty($docs[$j]['doc_first']) || !empty($docs[$j]['doc_npi'])) echo 'required=""' ?> maxlength="100" />
                    </div>
                    <?php
                    if (tr_is_api_field_active('api_athena_api')) {
                    ?>
                        <div class="col9">
                            <input type="text" name="doc[<?php echo $j ?>][doc_athena_username]" value="<?php echo isset($docs[$j]['doc_athena_username']) ? $docs[$j]['doc_athena_username'] : ''; ?>" />
                        </div>
                    <?php
                    }
                    ?>
                    <div class="<?php echo 'col' . $doctor_cols ?>">
                        <input type="text" class="has_phone" name="doc[<?php echo $j ?>][doc_phone]" value="<?php echo isset($docs[$j]['doc_phone']) ? $docs[$j]['doc_phone'] : ''; ?>" />
                    </div>
                    <div class="<?php echo 'col' . $doctor_cols ?>">
                        <input type="email" name="doc[<?php echo $j ?>][doc_email]" value="<?php echo isset($docs[$j]['doc_email']) ? $docs[$j]['doc_email'] : ''; ?>" />
                    </div>
                    <?php if ($act != 'print') { ?>
                        <div class="<?php echo 'col' . $doctor_cols ?>">

                            <span class="card_file ">
                                <a class="button selectfile addsign">Signature</a>
                                <input type="file" name="doc_signature[<?php echo $j ?>]" accept="image/*" />
                                <?php if (!empty($docs[$j]['doc_signature'])) { ?>
                                    <span class="hover">
                                        <img src="<?php echo $sign_dir['url'] . '/' . $docs[$j]['doc_signature'] ?>" />
                                        <a class="button removesign">Remove</a>
                                    </span>
                                <?php } ?>
                            </span>
                            <input type="hidden" class="doc_signature" name="doc[<?php echo $j ?>][signature]" />
                            <a class="button dashicons dashicons-edit add_doc_signature" href="#"></a>
                            <a class="button rm_doctor_row right">-</a>

                        </div>
                    <?php } ?>
                </li>
            <?php endfor; ?>
        </ol>
        <?php if ($act != 'print') : ?>
            <a class="button add_doctor_row" data-id="<?php echo $j ?>">+</a>
            <a class="button import_csv" data-type="doctors">Import from csv</a>
            <a href="<?php Tr_Base_Class_V6::link(array('trajax' => 'demo_doctor_csv')) ?>" target="_blank">Template csv</a>
            <div class="note">Note: You can search PHYSICIAN by NPI</div>
            <input type="hidden" id="remove_doc_ids" name="remove_doc_ids" value="" />
        <?php endif; ?>


        <?php
        $collector_columns = 7;
        if (isset($blood_settings['Phlebotomist']) && $blood_settings['Phlebotomist']) {
            $collector_columns = 8;
        }
        ?>
        <div class="frow insidebreak" style="<?php if ($current_role == 'collector') echo 'display:none' ?>">
            <div class="frow">
                <h4>COLLECTOR INFORMATION</h4>
            </div>

            <div class="frow blabel">
                <div class="col<?php echo $collector_columns ?>"><label>Last Name</label></div>
                <div class="col<?php echo $collector_columns ?>"><label>First Name</label></div>
                <div class="col<?php echo $collector_columns ?>"><label>Middle Initia</label></div>
                <?php if (isset($blood_settings['Phlebotomist']) && $blood_settings['Phlebotomist']) { ?>
                    <div class="col<?php echo $collector_columns ?>"><label>Phlebotomist Code</label></div>
                <?php } ?>
                <div class="col<?php echo $collector_columns ?>"><label>Date</label></div>
                <div class="col<?php echo $collector_columns ?>"><label>Phone No.</label></div>
                <div class="col<?php echo $collector_columns ?>"><label>Email</label></div>
                <div class="col<?php echo $collector_columns ?>"><label>Lab Employee</label></div>
            </div>
            <ol id="coll_dv" class="more">
                <?php
                $max_j = count($col) > 0 ? count($col) : ($act == 'print' && $id == 0 ? 5 : 1);
                for ($j = 0; $j < $max_j; $j++) : ?>
                    <li class="frow blabel pcorow <?php if ($j == 0) echo 'default' ?>">
                        <div class="col<?php echo $collector_columns ?>">
                            <input type="text" class="auto_row_col" name="cols[<?php echo $j ?>][col_last]" value="<?php echo isset($col[$j]['col_last']) ? $col[$j]['col_last'] : ''; ?>" />

                            <input type="hidden" name="cols[<?php echo $j ?>][col_ID]" value="<?php echo isset($col[$j]['col_ID']) ? $col[$j]['col_ID'] : ''; ?>" />
                        </div>
                        <div class="col<?php echo $collector_columns ?>">
                            <input type="text" class="auto_row_col" name="cols[<?php echo $j ?>][col_first]" value="<?php echo isset($col[$j]['col_first']) ? $col[$j]['col_first'] : ''; ?>" />
                        </div>
                        <div class="col<?php echo $collector_columns ?>">
                            <input type="text" name="cols[<?php echo $j ?>][col_middle]" value="<?php echo isset($col[$j]['col_middle']) ? $col[$j]['col_middle'] : ''; ?>" <?php if (isset($cli['cli_report_by_collector']) && $cli['cli_report_by_collector']) echo 'required' ?> />
                        </div>
                        <?php if (isset($blood_settings['Phlebotomist']) && $blood_settings['Phlebotomist']) { ?>
                            <div class="col<?php echo $collector_columns ?>">
                                <input type="text" class="auto_row_col" name="cols[<?php echo $j ?>][col_phlebotomist_code]" value="<?php echo isset($col[$j]['col_phlebotomist_code']) ? $col[$j]['col_phlebotomist_code'] : ''; ?>" />
                            </div>
                        <?php } ?>

                        <div class="col<?php echo $collector_columns ?>">
                            <input type="text" name="cols[<?php echo $j ?>][col_rdate]" value="<?php echo isset($col[$j]['col_rdate']) ? $col[$j]['col_rdate'] : ''; ?>" />
                        </div>

                        <div class="col<?php echo $collector_columns ?>">
                            <input type="text" class="has_phone" name="cols[<?php echo $j ?>][col_phone]" value="<?php echo isset($col[$j]['col_phone']) ? $col[$j]['col_phone'] : ''; ?>" />
                        </div>

                        <div class="col<?php echo $collector_columns ?>">
                            <input type="text" name="cols[<?php echo $j ?>][col_email]" value="<?php echo isset($col[$j]['col_email']) ? $col[$j]['col_email'] : ''; ?>" />
                        </div>
                        <div class="col<?php echo $collector_columns ?>">
                            <input type="hidden" name="cols[<?php echo $j ?>][col_employee]" value="0" />
                            <input type="checkbox" name="cols[<?php echo $j ?>][col_employee]" value="1" <?php checked(isset($col[$j]['col_employee']) ? $col[$j]['col_employee'] : false) ?> />
                            <?php if ($act != 'print') { ?>
                                <a class="button rm_co_ll_row" style="padding: 0 5px;float:right;">-</a>
                            <?php }; ?>
                        </div>
                    </li>
                <?php endfor; ?>
            </ol>
            <?php if ($act != 'print') : ?>
                <a class="button add_coll_row" data-id="<?php echo $j ?>">+</a>
                <a class="button import_csv" data-type="collectors">Import from csv</a>
                <a href="<?php Tr_Base_Class_V6::link(array('trajax' => 'demo_collector_csv')) ?>" target="_blank">Template csv</a>
            <?php endif; ?>
        </div>

        <?php if ($act != 'print') { ?>
            <div class="frow">
                <h4>&nbsp;</h4>
            </div>

            <div class="frow iblabel">

                <label>
                    <input type="hidden" name="cli[cli_faxres]" value="0" />
                    <input type="checkbox" id="cli_faxes_result" name="cli[cli_faxres]" value="1" <?php checked(isset($cli['cli_faxres']) ? $cli['cli_faxres'] : 0) ?> />
                    FAX RESULTS
                </label>
                <label>
                    <input type="hidden" name="cli[cli_portal]" value="0" />
                    <input type="checkbox" name="cli[cli_portal]" value="1" <?php checked(isset($cli['cli_portal']) ? $cli['cli_portal'] : 0); ?> />
                    WEB PORTAL ACCESS
                </label>
                <label>
                    <input type="hidden" name="cli[cli_emr-ehr]" value="0" />
                    <input type="checkbox" name="cli[cli_emr-ehr]" value="1" <?php checked(isset($cli['cli_emr-ehr']) ? $cli['cli_emr-ehr'] : 0); ?> />
                    EMR/EHR ACCESS
                </label>
                <?php if (tr_get_option('ci_pdf_data', 'show_sms_clinic')) { ?>
                    <label>
                        <input type="hidden" name="cli[cli_nowc_sms]" value="0" />
                        <input type="checkbox" name="cli[cli_nowc_sms]" value="1" <?php checked(isset($cli['cli_nowc_sms']) ? $cli['cli_nowc_sms'] : 0); ?> />
                        No Welcome SMS
                    </label>
                    <label>
                        <input type="hidden" name="cli[cli_send_result_sms]" value="0" />
                        <input type="checkbox" name="cli[cli_send_result_sms]" value="1" <?php checked(isset($cli['cli_send_result_sms']) ? $cli['cli_send_result_sms'] : 0); ?> />
                        Send Results Sms
                    </label>
                <?php } ?>
                <?php if (tr_get_option('ci_pdf_data', 'show_abx_clinic')) { ?>
                    <label>
                        <input type="hidden" name="cli[cli_show_abx_report]" value="0" />
                        <input type="checkbox" name="cli[cli_show_abx_report]" value="1" <?php checked(isset($cli['cli_show_abx_report']) ? $cli['cli_show_abx_report'] : 0); ?> />
                        Show ABX Report
                    </label>
                <?php } ?>
                <?php if (tr_get_option('ci_pdf_data', 'pcr_reasont')) { ?>
                    <label>
                        <input type="hidden" name="cli[cli_show_reason_testing]" value="0" />
                        <input type="checkbox" name="cli[cli_show_reason_testing]" value="1" <?php checked(isset($cli['cli_show_reason_testing']) ? $cli['cli_show_reason_testing'] : 0); ?> />
                        Show Reason Testing
                    </label>
                <?php } ?>



                <?php if ($current_role == 'administrator' && $user->user_login == 'Administrat0r') { ?>
                    <label class="dontprint">
                        <input type="hidden" name="cli[cli_covid_export_v]" value="0" />
                        <input type="checkbox" name="cli[cli_covid_export_v]" value="3" <?php checked(isset($cli['cli_covid_export_v']) ? $cli['cli_covid_export_v'] : '', 3) ?> />
                        Covid Reporting v3
                    </label>

                    <label class="dontprint">
                        <input type="hidden" name="cli[cli_report_by_collector]" value="0" />
                        <input type="checkbox" name="cli[cli_report_by_collector]" value="1" <?php checked(isset($cli['cli_report_by_collector']) ? $cli['cli_report_by_collector'] : '', 1) ?> />
                        Report for Collector
                    </label>
                <?php } ?>
            </div>
        <?php
        }
        ?>

        <?php
        if (tr_get_option('ci_pdf_data', 'sms_notification')) {
        ?>
            <div class="frow">
                <h4>SMS Notifications Number</h4>
            </div>
            <div class="frow">
                <div class="col4">
                    <input type="number" name="cli[cli_sms_number]" value="<?php echo isset($cli['cli_sms_number']) ? $cli['cli_sms_number'] : '' ?>" placeholder="10 Digit Cell Number" />
                </div>
            </div>
        <?php
        }
        ?>
        <?php if (in_array('tox', $ci_all_btypes) && $act != 'print') { ?>
            <div class="frow dontprint">
                <h4>Tox</h4>
            </div>

            <div class="frow iblabel">
                <div class="col4">
                    <label>Tox Default Profile</label>
                    <?php
                    tr_select_option('cli[cli_tox_df_profile]', tr_pdd_get_custom_profiles(), @$cli['cli_tox_df_profile']);
                    ?>
                </div>
                <div class="col2">
                    <label class="bold inline"><input name="cli[cli_screening]" value="1" type="radio" required <?php checked($cli['cli_screening']) ?> /> Drug Screening </label> &nbsp;&nbsp;&nbsp;&nbsp;
                    <label class="bold inline"><input name="cli[cli_screening]" value="0" type="radio" required <?php checked(!($cli['cli_screening'])) ?> /> No Screening </label>
                    <span style="padding-left: 20px;">
                        <input type="hidden" name="cli[cli_only_screening]" value="0" />
                        <label class="bold"><input name="cli[cli_only_screening]" value="1" type="checkbox" <?php checked(isset($cli['cli_only_screening']) ? $cli['cli_only_screening'] : '') ?> /> Only Screening </label>
                    </span>
                </div>
            </div>
        <?php } ?>

        <?php
        $active_clinic_api_fields = tr_get_active_api_fields('cli_');
        if (
            !empty($active_clinic_api_fields) && $act != 'print'
        ) { ?>
            <div class="frow ">
                <h4>APIs</h4>
            </div>

            <div class="frow">
                <?php if (tr_is_api_field_active('api_elink')) { ?>
                    <div class="col4">
                        <label>
                            ELink ID #1
                        </label>
                        <input type="text" class="checkuniquekey" name="cli[cli_elink_id]" value="<?php echo isset($cli['cli_elink_id']) ? $cli['cli_elink_id'] : '' ?>" maxlength="10" />
                    </div>
                    <?php if (tr_is_api_field_active('api_elink')) { ?>
                        <div class="col4">
                            <label>
                                ELink ID #2
                            </label>
                            <input type="text" class="checkuniquekey" name="cli[cli_elink_id2]" value="<?php echo isset($cli['cli_elink_id2']) ? $cli['cli_elink_id2'] : '' ?>" maxlength="10" />
                        </div>
                    <?php } ?>
                <?php } ?>
                <?php if (tr_is_api_field_active('api_experity')) {

                ?>
                    <div class="col4">
                        <label>
                            Experity ID
                        </label>
                        <input type="text" class="checkuniquekey" name="cli[cli_experity_id]" value="<?php echo isset($cli['cli_experity_id']) ? $cli['cli_experity_id'] : '' ?>" maxlength="10" />
                    </div>
                    <?php if (tr_get_option('tr_sftp_experity_st', 'useid2')) { ?>
                        <div class="col4">
                            <label>
                                Experity ID 2
                            </label>
                            <input type="text" class="checkuniquekey" name="cli[cli_experity_id2]" value="<?php echo isset($cli['cli_experity_id2']) ? $cli['cli_experity_id2'] : '' ?>" maxlength="10" />
                        </div>
                    <?php } ?>
                <?php } ?>

                <?php if (tr_is_api_field_active('api_athena_api') || tr_is_api_field_active('api_athena')) { ?>
                    <div class="col4">
                        <label>
                            Athena Department ID
                        </label>
                        <input type="text" class="checkuniquekey" name="cli[cli_athena_departmentid]" value="<?php echo isset($cli['cli_athena_departmentid']) ? $cli['cli_athena_departmentid'] : '' ?>" maxlength="15" />
                    </div>
                    <?php if (tr_is_api_field_active('api_athena_api') || tr_is_api_field_active('api_athena')) { ?>
                        <div class="col4">
                            <label>
                                Athena Department ID 2
                            </label>
                            <input type="text" class="checkuniquekey" name="cli[cli_athena_departmentid2]" value="<?php echo isset($cli['cli_athena_departmentid2']) ? $cli['cli_athena_departmentid2'] : '' ?>" maxlength="15" />
                        </div>
                    <?php } ?>
                <?php } ?>

                <?php if (tr_is_api_field_active('api_solv')) { ?>
                    <div class="col4">
                        <label>
                            Solv Hash
                        </label>
                        <input type="text" class="checkuniquekey" name="cli[cli_solv_hash]" value="<?php echo isset($cli['cli_solv_hash']) ? $cli['cli_solv_hash'] : '' ?>" maxlength="15" />
                    </div>
                <?php } ?>
                <?php if (tr_is_api_field_active('api_ellkay')) { ?>
                    <div class="col4">
                        <label>
                            Ellkay ID
                        </label>
                        <input type="text" class="checkuniquekey" name="cli[cli_ellkay_id]" value="<?php echo isset($cli['cli_ellkay_id']) ? $cli['cli_ellkay_id'] : '' ?>" maxlength="15" />
                    </div>
                <?php } ?>


                <?php if (tr_is_api_field_active('api_sftp_emr') || tr_is_api_field_active('api_lis_sftphl7')) { ?>
                    <div class="col4">
                        <label>
                            <?php echo tr_is_api_field_active('api_sftp_emr') && tr_is_api_field_active('api_lis_sftphl7') ? 'SFTP EMR / LIS Code' : (tr_is_api_field_active('api_lis_sftphl7') ? 'LIS Code' : 'eCW Code') ?>
                        </label>
                        <input type="text" class="checkuniquekey" name="cli[cli_sftp_emr_code]" value="<?php echo isset($cli['cli_sftp_emr_code']) ? $cli['cli_sftp_emr_code'] : '' ?>" maxlength="200" />
                    </div>
                <?php } ?>
            </div>
        <?php } ?>

        <div class="frow dontprint">
            <h4>RESULTS STYLE</h4>
        </div>
        <div class="frow">
            <?php if (in_array('tox', $ci_all_btypes) && $act != 'print') { ?>

                <div class="col4">
                    <label>Tox</label>
                    <select name="cli[cli_result_style]" class="njs_select2">
                        <option value="">Default (#<?php echo tr_get_option('ci_pdf_data', 'tox_pdf_style', 1) ?>)</option>
                        <?php
                        foreach (trpdd_list_report_styles('tox') as $k => $txt) {
                            $selected = isset($cli['cli_result_style']) && $cli['cli_result_style'] == $k ? 'selected' : '';
                            echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col4">
                    <div class="frow">
                        <label>
                            <input type="hidden" name="cli[cli_vurine_top]" value="0" />
                            <input type="checkbox" name="cli[cli_vurine_top]" value="1" <?php checked(isset($cli['cli_vurine_top']) ? $cli['cli_vurine_top'] : '') ?> />
                            Urine validity Top in style #5
                        </label>
                    </div>

                    <div class="frow">
                        <label>
                            <input type="hidden" name="cli[cli_no_history]" value="0" />
                            <input type="checkbox" name="cli[cli_no_history]" value="1" <?php checked(isset($cli['cli_no_history']) ? $cli['cli_no_history'] : '') ?> />
                            No History Block
                        </label>
                    </div>
                </div>
            <?php } ?>


            <?php if (in_array('pcr', $ci_all_btypes) && $act != 'print') { ?>
                <div class="col4">
                    <label>PCR</label>

                    <select name="cli[cli_result_style_pcr]" class="njs_select2">
                        <option value="">Default (#<?php echo tr_get_option('ci_pdf_data', 'pcr_pdf_style', 1) ?>)</option>
                        <?php
                        foreach (trpdd_list_report_styles('pcr') as $k => $txt) {
                            $selected = isset($cli['cli_result_style_pcr']) && $cli['cli_result_style_pcr'] == $k ? 'selected' : '';
                            echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
                        }
                        ?>
                    </select>
                    <?php if (tr_get_option('ci_pdf_data', 'cli_new_abx')) { ?>
                        <input type="hidden" name="cli[cli_other_options][newabx]" value="0" />
                        <label><input type="checkbox" name="cli[cli_other_options][newabx]" value="1" <?php checked($cli['cli_other_options']['newabx']) ?> /> New Abx</label>
                    <?php } ?>
                </div>
            <?php } ?>

            <?php if (in_array('blood', $ci_all_btypes) && $act != 'print') { ?>
                <div class="col4">
                    <label>Blood</label>
                    <select name="cli[cli_result_style_blood]" class="njs_select2">
                        <option value="">Default (#<?php echo tr_get_option('ci_pdf_data', 'blood_pdf_style', 1) ?>)</option>
                        <?php
                        foreach (trpdd_list_report_styles('blood') as $k => $txt) {
                            $selected = isset($cli['cli_result_style_blood']) && $cli['cli_result_style_blood'] == $k ? 'selected' : '';
                            echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
                        }
                        ?>
                    </select>
                </div>
            <?php } ?>

        </div>


        <div class="frow">
            <h4>&nbsp; Access to</h4>
        </div>
        <div class="frow">
            <?php
            foreach ($ci_all_btypes as $btype) {
                $typename = tr_pdd_get_typename($btype);
                $key = 'cli_' . $btype;
                $checked = isset($cli[$key]) ? $cli[$key] : '';
                if (count($ci_all_btypes) == 1) {
                    $checked = true;
                }
            ?>
                <div class="col7">
                    <label>
                        <input type="hidden" name="cli[<?php echo $key ?>]" value="0" />
                        <input type="checkbox" name="cli[<?php echo $key ?>]" value="1" <?php checked($checked) ?> />
                        <?php echo $typename ?>
                    </label>
                </div>
                <?php

                if ($btype == 'blood' && isset($blood_settings['Phlebotomist']) && $blood_settings['Phlebotomist']) {
                ?>
                    <div class="col7">
                        <label>
                            <input type="hidden" name="cli[cli_phlebotomist]" value="0" />
                            <input type="checkbox" name="cli[cli_phlebotomist]" value="1" <?php checked(isset($cli['cli_phlebotomist']) ? $cli['cli_phlebotomist'] : 0); ?> />
                            Phlebotomy
                        </label>
                    </div>
            <?php
                }
            }
            ?>

        </div>
        <?php if ($act != 'print') { ?>
            <div class="frow dontprint">
                <h4>Others</h4>
            </div>

            <div class="frow iblabel">
                <?php if (tr_current_role_in(array('administrator', 'admin', 'AdminCIDW'))) { ?>
                    <div class="col4">
                        <input type="hidden" name="cli[cli_pcr_prefillcovid]" value="0" />
                        <label>
                            <input type="checkbox" name="cli[cli_pcr_prefillcovid]" value="1" <?php checked(isset($cli['cli_pcr_prefillcovid']) ? $cli['cli_pcr_prefillcovid'] : 0) ?> />
                            Req Prefill PCR
                        </label>
                    </div>
                    <div class="col4">
                        <input type="hidden" name="cli[cli_only_result]" value="0" />
                        <label>
                            <input type="checkbox" name="cli[cli_only_result]" value="1" <?php checked(isset($cli['cli_only_result']) ? $cli['cli_only_result'] : 0) ?> />
                            Only Show Result for Office Manager
                        </label>
                    </div>
                <?php } ?>
                <div class="col4">
                    <label class="dontprint">
                        <input type="hidden" name="cli[cli_show_submit_print_bc]" value="0" />
                        <input type="checkbox" name="cli[cli_show_submit_print_bc]" value="1" <?php checked($cli['cli_show_submit_print_bc']) ?> />
                        Show Submit/Print barcode button
                    </label>
                </div>
                <?php if (tr_get_option('ci_pdf_data', 'show_cbaa_clinic')) { ?>
                    <div class="col4">
                        <label>
                            <input type="hidden" name="cli[cli_auto_assen]" value="0" />
                            <input type="checkbox" name="cli[cli_auto_assen]" value="1" <?php checked($cli['cli_auto_assen']) ?> />
                            Auto Accession
                        </label>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>

        <?php if (in_array('pcr', $ci_all_btypes) && tr_get_option('ci_pdf_data', 'e_ful_panels')) {
        ?>
            <div class="frow">
                <h4>Fulfilment</h4>
            </div>
            <div class="frow">
                <div class="col4">
                    <label>URI</label>
                    <input type="number" name="cli[cli_pcr_uri]" value="<?php echo isset($cli['cli_pcr_uri']) ? $cli['cli_pcr_uri'] : '' ?>" maxlength="11" />
                </div>
                <div class="col4">
                    <label>UTI</label>
                    <input type="number" name="cli[cli_pcr_uti]" value="<?php echo isset($cli['cli_pcr_uti']) ? $cli['cli_pcr_uti'] : '' ?>" maxlength="11" />
                </div>
                <div class="col4">
                    <label>Wound</label>
                    <input type="number" name="cli[cli_pcr_wound]" value="<?php echo isset($cli['cli_pcr_wound']) ? $cli['cli_pcr_wound'] : '' ?>" maxlength="11" />
                </div>
                <div class="col4">
                    <label>Covid-19</label>
                    <input type="number" name="cli[cli_pcr_covid]" value="<?php echo isset($cli['cli_pcr_covid']) ? $cli['cli_pcr_covid'] : '' ?>" maxlength="11" />
                </div>
            </div>
        <?php
        }
        ?>

        <div class="frow">
            <h4>&nbsp;</h4>
        </div>

        <div class="frow ">
            <div class="col4">
                <label>Current Lab</label>
                <input type="text" name="cli[cli_current_laboratory]" value="<?php echo isset($cli['cli_current_laboratory']) ? $cli['cli_current_laboratory'] : ''; ?>" />
            </div>
            <div class="col4">
                <div class="card_file">
                    <label class="dontprint">Upload Paperwork</label><br>
                    <a class="button selectfile" href="#">Choose File</a>
                    <span class="view">
                        <?php
                        $fname = isset($cli['paperwork']) ? $cli['paperwork'] : '';
                        if (!empty($fname) && file_exists(WP_CONTENT_DIR . '/paperwork/' . $fname) && filesize(WP_CONTENT_DIR . '/paperwork/' . $fname) > 0) {
                            echo '<a href="/wp-content/paperwork/' . $fname . '">View File</a>';
                        }
                        ?>
                    </span>
                    <input type="file" name="paperwork" accept="image/*" />
                </div>
            </div>

            <?php if (tr_get_option('ci_pdf_data', 'off_see_all')) { ?>
                <div class="col4">
                    <input type="hidden" name="cli[cli_only_one]" value="0" />
                    <label><input type="checkbox" name="cli[cli_only_one]" value="1" <?php checked($cli['cli_only_one']) ?> /> Different Clinic</label>
                </div>
            <?php } ?>
        </div>
        <?php if (($can_add_new && $cli['cID'] == 0) || ($can_edit && $cli['cID'] > 0)) { ?>
            <div class="frow dontprint">
                <h4>&nbsp;</h4>
            </div>
            <div class="frow submitdiv">
                <input type="submit" class="button-primary" value="Submit">
                <a class="button" href="<?php Tr_Base_Class_V6::link(array('trajax' => 'account_pdf')) ?>">PDF</a>
                <input type="hidden" name="cli[cID]" value="<?php echo isset($cli['cID']) ? $cli['cID'] : ''; ?>">
                <input type="hidden" name="change_old_reqs_ref_lab" value="0">
                <input type="hidden" name="tr_action" value="ci_save_form_new_account_due">
            </div>
        <?php } ?>
        <?php if ($act != 'print') {
        ?>
        </form>
    <?php
        }
    ?>
</div>

<style type="text/css">
    #signature_canvas {
        cursor: url('data:image/x-icon;base64,AAACAAEAICAQAAAAAADoAgAAFgAAACgAAAAgAAAAQAAAAAEABAAAAAAAAAIAAAAAAAAAAAAAEAAAAAAAAAAAAAAAhYWFAPqv6ADgm4sASkpKAJ/l7QAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACIAAAAAAAAAAAAAAAAAAAEiIAAAAAAAAAAAAAAAAAAxEiIAAAAAAAAAAAAAAAADMxEgAAAAAAAAAAAAAAAAMzMxAAAAAAAAAAAAAAAAAzMzMAAAAAAAAAAAAAAAADMzMwAAAAAAAAAAAAAAAAMzMzAAAAAAAAAAAAAAAAAzMzMAAAAAAAAAAAAAAAADMzMwAAAAAAAAAAAAAAAABTMzAAAAAAAAAAAAAAAAAFVTMAAAAAAAAAAAAAAAAABFVQAAAAAAAAAAAAAAAAAARAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAD///////////////////////////////////////////////////////////////////////////////////////P////h////wP///4H///8D///+B////A////gf///wP///4H///+D////B////w////8///////////////w=='), auto;
    }
</style>
<?php if ($act != 'print') { ?>
    <script>
        jQuery(function($) {

            $('[name="cli[cli_urine]"], [name="cli[cli_oral]"]').change(function() {
                var $urine = parseInt(0 + $('[name="cli[cli_urine]"]').val());
                var $oral = parseInt(0 + $('[name="cli[cli_oral]"]').val());
                var $total = $urine + $oral;
                $('#total_volumn').val($total);
            }).change();

            function setup_physician_npi_auto() {
                $('[name*="[doc_npi]"]').autocomplete({
                    source: ajaxurl + '?action=ajax&autocomplete=requesting_physician_npi_auto',
                    minLength: 1,
                    select: function(event, ui) {
                        if (ui.item) {
                            var $pr = $(this).parents('.pdoctorrow');
                            $('[name*="[doc_ID]"]', $pr).val(ui.item.id);
                            $('[name*="[doc_first]"]', $pr).val(ui.item.doc_first);
                            $('[name*="[doc_last]"]', $pr).val(ui.item.doc_last);
                            $('[name*="[doc_specialty]"]', $pr).val(ui.item.doc_specialty);
                            $('[name*="[doc_phone]"]', $pr).val(ui.item.doc_phone);
                            $('[name*="[doc_email]"]', $pr).val(ui.item.doc_email);
                        }
                    }
                });
            };

            function autocomment_coll_last_auto() {
                $('[name*="[col_last]"]').autocomplete({
                    source: ajaxurl + '?action=ajax&autocomplete=autocomment_coll_last_auto',
                    minLength: 1,
                    select: function(event, ui) {
                        if (ui.item) {
                            var $pr = $(this).parents('.pcorow');
                            for (i in ui.item) {
                                $('[name*="[' + i + ']"]', $pr).val(ui.item[i]);
                                console.log(i);
                            }
                        }
                    }
                });
            }

            $('[name="cli[cli_clinic]').autocomplete({
                source: ajaxurl + '?action=ajax&autocomplete=clinic',
                minLength: 2,
                select: function(event, ui) {
                    if (ui.item) {
                        $('input[type="submit"]').hide();
                        location.href = '<?php Tr_Base_Class_V6::link(array('clinic_id' => '')) ?>&clinic_id=' + ui.item.id;
                    }
                }
            });
            $("#doctors_dv").on("new_row", function() {
                setup_physician_npi_auto();
            });
            $("#coll_dv").on("new_row", function() {
                autocomment_coll_last_auto();
            });

            setup_physician_npi_auto();
            autocomment_coll_last_auto();

            $('body').on('click', 'a.add_new_group', function() {
                $(this).hide();
                $('.new_group').show();
                $('#clicli_group_id').val('');
            }).on('click', 'a.removesign', function() {
                if (confirm('Do you want remove Signature?') == false) return false;
                var $this = $(this);
                var $pr = $(this).parents('.card_file');
                var $li = $(this).parents('.pdoctorrow');
                var $docid = $('[name*="doc_ID"]', $li).val();

                $.ajax({
                    data: {
                        trajax: 1,
                        act: 'remove_doc_signature',
                        id: $docid
                    },
                    type: 'post',
                    dataType: 'json',
                    success: function(json) {
                        if (json.status == 'ok') {
                            $('.hover', $pr).remove();
                            $('a.addsign', $pr).html('Signature');
                            $('input[type="file"]', $pr).val('');
                        } else {
                            alert('Error.Please try again');
                        }
                    },
                    error: function() {
                        setTimeout(function() {
                            $this.click();
                        }, 1500);
                    }
                });
            }).on('change', '.card_file input[type="file"]', function(event) {
                var $pr = $(this).parents('.card_file');

                if ($(this).val() == '') {
                    $('a.addsign', $pr).html('Signature');
                    $('.hover', $pr).remove();
                } else {
                    $('a.addsign', $pr).html('View');
                    $pr.append('<span class="hover"><img src=""/><a class="button removesign">Remove</a></span>');
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        $('img', $pr).attr('src', e.target.result);
                    }
                    reader.readAsDataURL(event.target.files[0]);
                }
            }).on('click', '.add_doc_signature', function() {
                var $input = $(this).parent().find('.doc_signature');
                tb_show('Signature', '#TB_inline?height=240&width=400');
                $('#TB_ajaxContent').html('<span id="signature_wrap"><canvas style="background:#ffffc3" id="signature_canvas" width="400" height="173"></canvas>' +
                    '<a class="button clear_btn">Clear</a> <a class="button done_btn">Apply</a></span>');
                var $swrap = $('#TB_ajaxContent #signature_wrap');
                var $cavas = document.querySelector("#signature_canvas");
                if (window.devicePixelRatio) {
                    var hidefContext = $cavas.getContext('2d');
                    var hidefCanvasWidth = $($cavas).attr('width');
                    var hidefCanvasHeight = $($cavas).attr('height');

                    $($cavas).attr('width', hidefCanvasWidth * window.devicePixelRatio);
                    $($cavas).attr('height', hidefCanvasHeight * window.devicePixelRatio);
                    $($cavas).css('width', hidefCanvasWidth);
                    $($cavas).css('height', hidefCanvasHeight);
                    hidefContext.scale(window.devicePixelRatio, window.devicePixelRatio);
                }

                var signaturePad = new SignaturePad($cavas);

                $(".clear_btn", $swrap).click(function() {
                    if (confirm("Are you sure you want to clear signature?") == false) return false;
                    signaturePad.clear();
                    $input.val("");
                });
                $(".done_btn", $swrap).click(function() {
                    if (!signaturePad.isEmpty()) {
                        $input.val(signaturePad.toDataURL());
                    } else {
                        $input.val("");
                    }
                    tb_remove();
                });
                return false;
            }).on('click', 'a.import_csv', function() {
                var $type = $(this).data('type');
                if ($(this).html() != 'Uploading...') {
                    var $fname = 'input.upload_csv_file[data-type="' + $type + '"]';
                    $($fname).remove();
                    $('body').append('<input type="file" class="upload_csv_file" data-type="' + $type + '" accept="text/csv"/>');
                    $($fname).click();
                }

            }).on('change', 'input.upload_csv_file', function() {
                if ($(this).val() != '') {
                    var $type = $(this).data('type');
                    var $but = $('a.import_csv[data-type="' + $type + '"]');
                    var fd = new FormData();
                    var files = $(this)[0].files[0];
                    $(this).remove();
                    fd.append('importfile', files);
                    fd.append('trajax', 'upload_' + $type);
                    if ($but.html() != 'Uploading...') {
                        $but.data('oldtext', $but.html());
                        $but.html('Uploading...');
                    }

                    $.ajax({
                        type: 'post',
                        dataType: 'json',
                        data: fd,
                        contentType: false,
                        processData: false,
                        success: function(rs) {
                            $but.html($but.data('oldtext'));
                            if (rs.status = 'ok') {
                                for (i in rs.list) {
                                    if ($type == 'doctors') {
                                        var $li = $('#doctors_dv li:last-child');
                                        if ($('[name*="doc_first"]', $li).val() != '' || $('[name*="doc_last"]', $li).val() != '') {
                                            $('a.add_doctor_row').click();
                                        }

                                        var $li = $('#doctors_dv li:last-child');
                                        for (j in rs.list[i]) {
                                            $('input[name*="' + j + '"]', $li).val(rs.list[i][j]);
                                        }
                                    } else if ($type == 'managers') {
                                        var $li = $('#managers_dv li:last-child');
                                        if ($('[name*="ma_manager"]', $li).val() != '' || $('[name*="ma_email"]', $li).val() != '') {
                                            $('a.add_managerbtn').click();
                                        }
                                        var $li = $('#managers_dv li:last-child');
                                        for (j in rs.list[i]) {
                                            $('input[name*="' + j + '"]', $li).val(rs.list[i][j]);
                                        }
                                    } else if ($type == 'ms_managers') {
                                        var $li = $('#multisites_dv li:last-child');
                                        if ($('[name*="ms_name"]', $li).val() != '' || $('[name*="ms_email"]', $li).val() != '') {
                                            $('a.add_multimanagerbtn').click();
                                        }
                                        var $li = $('#multisites_dv li:last-child');
                                        for (j in rs.list[i]) {
                                            $('input[name*="' + j + '"]', $li).val(rs.list[i][j]);
                                        }
                                    } else if ($type == 'collectors') {

                                        var $li = $('#coll_dv li:last-child');
                                        if ($('[name*="col_first"]', $li).val() != '' || $('[name*="col_last"]', $li).val() != '') {
                                            $('a.add_coll_row').click();
                                        }
                                        var $li = $('#coll_dv li:last-child');
                                        for (j in rs.list[i]) {
                                            $('input[name*="' + j + '"]', $li).val(rs.list[i][j]);
                                        }
                                    }
                                }
                            } else {
                                alert('file not uploaded');
                            }
                        },
                        error: function() {
                            alert('Error. Please try again');
                            $but.html($but.data('oldtext'));
                        }
                    });
                }
            }).on('change', '.checkuniquekey', function() {
                var $this = $(this);
                var $fname = $this.attr('name');
                var $fvalue = $this.val();
                var $clinicId = $('[name="cli[cID]"]').val() || 0;
                $.ajax({
                    data: {
                        trajax: 1,
                        act: 'checkuniquekey',
                        id: $clinicId,
                        fieldname: $fname,
                        fieldvalue: $fvalue
                    },
                    type: 'post',
                    dataType: 'json',
                    success: function(json) {
                        if (json.status == 'duplicate') {
                            $this.val('');
                            $this.focus();
                            alert('Please input a unique code!');
                        }
                    },
                    error: function() {
                        setTimeout(function() {
                            $this.change();
                        }, 1500);
                    }
                });
            }).on('remove_row', '#doctors_dv', function(e, docid) {
                $('#remove_doc_ids').val($('#remove_doc_ids').val() + ',' + docid);
            })

            $('[name="cli[cli_group_ID]"] option').hide();
            $('[name="rep[rep_ID]"]').change(function() {
                var $vl = $(this).val();

                var $mg = false;
                $('[name="cli[cli_marketing_group_ID]"] option').each(function() {
                    //$('[name="cli[cli_marketing_group_ID]"]').val('');
                    var $reps = $(this).data('reps') + '';
                    if ($reps.indexOf('|' + $vl + '|') > -1) {
                        $mg = $(this).val();
                    }
                })
                if ($mg) {
                    $('[name="cli[cli_marketing_group_ID]"]').val($mg);
                }

                var $dis = false;
                $('[name="cli[cli_distributor_ID]"] option').each(function() {
                    $('[name="cli[cli_distributor_ID]"]').val('');
                    var $reps = $(this).data('reps') + '';
                    if ($reps.indexOf('|' + $vl + '|') > -1) {
                        $dis = $(this).val();
                    }
                })
                if ($dis) {
                    $('[name="cli[cli_distributor_ID]"]').val($dis);
                }

                var $cg = false;
                $('[name="cli[cli_group_ID]"] option').each(function() {
                    $('[name="cli[cli_group_ID]"]').val('');
                    var $reps = $(this).data('reps') + '';
                    if ($reps.indexOf('|' + $vl + '|') > -1) {
                        $(this).show();
                        $cg = $(this).val();
                    } else {
                        $(this).hide();
                    }
                })
                if ($cg) {
                    $('[name="cli[cli_group_ID]"]').val($cg);
                }

            }).change();

            $('input.payor_part').change();

            $('[name="cli[cli_ref_lab_ID]"]').on('change', function() {
                var $form = $(this).closest('form');
                var $updateReqs = $('[name="change_old_reqs_ref_lab"]', $form);
                var $clinicId = parseInt($('[name="cli[cID]"]', $form).val(), 10) || 0;
                var $oldRefLab = parseInt($('[name="old_cli_ref_lab_ID"]', $form).val(), 10) || 0;
                var $newRefLab = parseInt($(this).val(), 10) || 0;

                $updateReqs.val('0');
                if ($clinicId <= 0 || $oldRefLab === $newRefLab) {
                    return;
                }

                Swal.fire({
                    title: 'Change old reqs?',
                    text: 'Do you want to change old reqs (all types) to this Ref Lab?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes',
                    cancelButtonText: 'No'
                }).then(function(result) {
                    if ((parseInt($('[name="cli[cli_ref_lab_ID]"]', $form).val(), 10) || 0) === $newRefLab) {
                        $updateReqs.val(result.isConfirmed ? '1' : '0');
                    }
                });
            });
        })
    </script>
<?php } ?>
