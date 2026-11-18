<?php

function tradmin_action_ci_load_specimen_billings()
{
    include(TRCDBI_PATH . 'inc/actions/ci_load_specimen_billings.php');
    exit;
}

function tradmin_action_ci_change_batch_bill_status()
{
    global $wpdb;
    $id = $_REQUEST['id'];
    $status = $_REQUEST['status'];
    $btype = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'tox';
    $batch_tbl = tr_pdd_get_table_by_btype($btype);

    $bat = $wpdb->get_row("select * from {$batch_tbl} where mid = {$id} ");

    $where_exist = " assen = '{$bat->assen}' ";
    if (!empty($bat->claim_id) && tr_billing_get_settings('enable_claim_no')) {
        $where_exist .= " or claim_no = '{$bat->claim_id}' ";
    }


    $exist_id = $wpdb->get_var("select id from ci_batch_billings where {$where_exist}");

    if ($exist_id > 0) {
        $wpdb->update('ci_batch_billings', array('bill_status' => $status), array('id' => $exist_id));
    } else {
        $insert_data = array(
            'assen' => $bat->assen,
            'claim_no' => $bat->claim_id,
            'bill_status' => $status
        );
        $wpdb->insert('ci_batch_billings', $insert_data);
    }


    echo 'Changed Status Successfully';


    if ($status == 'Need Billing') {
        tradmin_action_ci_send_rep_billing($id, $btype);
    }

    exit;
}

function tradmin_action_ci_save_billing_data()
{
    global $wpdb;
    $return = array('status' => 'ok', 'changes' => array());

    $mid = $_REQUEST['mid'];
    $value = $_REQUEST['value'];
    $field = $_REQUEST['field'];
    $btype = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'tox';

    $batch_tbl = tr_pdd_get_table_by_btype($btype);
    $report_tbl = tr_pdd_get_table_report_by_btype($btype);

    $fields = array('claim_no', 'claim_date', 'billed_amount', 'received_amount', 'allowed_amount');
    $where_join = " bb.assen = ba.assen ";
    if (tr_billing_get_settings('enable_claim_no')) {
        $where_join = " (bb.claim_no!='' and bb.claim_no is not null and bb.claim_no = ba.claim_id)
                                                        or (bb.assen = ba.assen) ";
    }
    $bat = $wpdb->get_row("select 
                        ba.mid,
                        ba.assen,
                        
                        re.created,
                        
                        bb.claim_date,
                        bb.received_amount,
                        bb.billed_amount,
                        bb.claim_no,
                        bb.id
                        
                        from {$batch_tbl} ba
                        left JOIN {$report_tbl} re ON ba.assen = re.patient_id
                        left join ci_batch_billings bb ON {$where_join}
                        where ba.mid = {$mid} group by ba.mid ");

    $bat->{$field} = $value;

    if (in_array($field, $fields)) {
        $update_billing = array($field => $value);
        $update_bat = array();

        if ($field == 'claim_no') {
            if ($bat->claim_date == '') {
                $update_billing['claim_date'] = date_i18n('Y-m-d');
            }
            $update_bat['claim_id'] = $value;
        }



        if (count($update_bat) > 0) {
            $wpdb->update($batch_tbl, $update_bat, array('mid' => $mid));
        }

        if ($bat->id > 0) {
            $rs = $wpdb->update('ci_batch_billings', $update_billing, array('id' => $bat->id));
        } else {
            if (!empty($bat->claim_id)) {
                $update_billing['claim_no'] = $bat->claim_id;
            }

            $update_billing['assen'] = $bat->assen;
            $rs = $wpdb->insert('ci_batch_billings', $update_billing);
            $bat->id = $wpdb->insert_id;
        }

        if ($rs === false) {
            $return['status'] = 'error';
            $return['msg'] = $wpdb->last_error;
            $return['update'] = $update_billing;
        } else {


            if ($field == 'billed_amount') {
                $return['changes']['billed'] = ($value > 0) ? '<span class="checkmark checked"></span>' : '';
            }
            if ($field == 'received_amount') {
                $return['changes']['received'] = ($value > 0) ? '<span class="checkmark checked"></span>' : '';
            }

            if (in_array($field, array('billed_amount', 'received_amount'))) {
                $return['changes']['balance'] = number_format($bat->received_amount - $bat->billed_amount, 2);
            }
        }
    } else {
        $return['status'] = 'error';
    }

    wp_send_json($return);
    exit;
}

function tradmin_action_ci_get_change_billing_codes()
{
    global $wpdb;
    $ins = trim($_REQUEST['ins']);
    $report_id = $_REQUEST['report_id'];
    $btype = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'tox';

    $batch_tbl = tr_pdd_get_table_by_btype($btype);
    $report_tbl = tr_pdd_get_table_report_by_btype($btype);

    $bat = $wpdb->get_row("select ba.* from {$batch_tbl} ba 
            JOIN {$report_tbl} re ON  ba.assen = re.patient_id 
            where re.id = {$report_id} ", ARRAY_A);
    tr_bill_get_billing_code($ins, $report_id, $bat);
    exit;
}

function tradmin_action_ci_fix_char_content()
{
    global $wpdb;
    $count = 0;
    $rs = $wpdb->get_results("select * from ci_batch_billings ");
    foreach ($rs as $r) {
        $update = array(
            'claim_no' => preg_replace("/[^a-zA-Z0-9\s\.\,\-\_\/]/", '', $r->claim_no),
            'claim_date' => preg_replace("/[^a-zA-Z0-9\s\.\,\-\_\/]/", '', $r->claim_date),
            'insurance_payment' => preg_replace("/[^a-zA-Z0-9\s\.\,\-\_\/]/", '', $r->insurance_payment),
        );
        $wpdb->update('ci_batch_billings', $update, array('id' => $r->id));
        $count++;
    }
    echo $count;
    exit;
}


function tradmin_action_ci_send_rep_billing($mid = 0, $btype = 'tox')
{
    global $wpdb;

    $id = $_REQUEST['mid'];
    if ($mid > 0) {
        $id = $mid;
    }
    $batch_tbl = tr_pdd_get_table_by_btype($btype);

    $bat = $wpdb->get_row("select * from {$batch_tbl} WHERE mid = {$id}");
    $rep = $wpdb->get_row("select rep.* from ci_lab_reps rep JOIN  ci_clinic cli ON cli.`cli_rep-ID` = rep.rep_ID 
                              WHERE  cli.cID = {$bat->cli_ID}");
    $email = $rep->rep_email;
    //need email
    if (!empty($email)) {
        $email_from = get_bloginfo('admin_email');
        $settings = get_option('tr_billing_ind_st');
        if (empty($settings['email_need_info'])) {
            $settings['email_need_info'] = $email_from;
        }
        $headers = 'From: ' . get_bloginfo('name') . ' <' . $email_from . '>' . "\r\n";
        $headers .= 'Reply-To: ' . $settings['email_need_info'] . "\r\n";
        $headers .= "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";



        $subject = "More info needed for patient billing - '{$bat->assen}' ";

        $message = $subject . " - Please call.<br>";
        $message .= "Barcode: " . $bat->assen . "<br>";
        $message .= "Billing Notes: " . $bat->billing_notes . "<br>";

        //$message.= $email;
        //$email = 'pntrinh29@gmail.com';

        wp_mail($email, $subject, $message, $headers);
    }
    if ($mid == 0) {
        if (!empty($email)) {
            echo 'Sent to rep: ' . $email;
        } else {
            echo 'No email for this rep';
        }

        exit;
    }
}

function tradmin_action_ci_patient_billing_notes()
{
    include(TRCDBI_PATH . 'inc/actions/ci_patient_billing_notes.php');
    exit;
}

function tradmin_action_ci_save_billing_pecos()
{
    global $wpdb;

    $doc_id = $_REQUEST['doc_id'];
    $checked = $_REQUEST['checked'];
    $wpdb->update('ci_doc', array('doc_pecos' => $checked), array('doc_ID' => $doc_id));

    $return = array(
        'status' => 'ok'
    );
    wp_send_json($return);
}

function tradmin_action_billing_export_patients()
{
    global $wpdb, $show_menus_options, $current_role, $ci_active_menu;

    $settings       = tr_billing_get_settings();
    $user_id        = get_current_user_id();
    $file_name      = 'export.csv';

    $where_ba       = '';
    $where          = " and estatus in ('C','F') ";

    if ($current_role == 'BillbyClinic') {
        $clinics = get_user_meta($user_id, '_bill_cli_IDs', true);
        if (!is_array($clinics)) {
            $clinics = array(0);
        }
        $clinics = implode(',', $clinics);
        $where_ba .= " and ba.cli_ID in ({$clinics}) ";
    } else  if ($current_role == 'BillbyRefLab') {
        $reflabid = get_user_meta($user_id, '_ref_lab_id', true);
        if (empty($reflabid)) {
            $reflabid = -1;
        }
        $where_ba .= " and ba.ref_lab_ID in ({$reflabid}) ";
    } else if ($current_role == 'office_manager') {

        $clinics  = $wpdb->get_col("select cID from ci_clinic where cli_om_user_ID={$user_id} ");
        $clinics  = implode(',', $clinics);
        $where_ba .= " and ba.cli_ID in ({$clinics}) ";
    } else if ($current_role == 'Billing' && !$settings['disable_billbyclinic']) {
        $excludecids = get_option('ci_bill_cli_IDs', array());

        if (is_array($excludecids) && count($excludecids) > 0) {
            $excludecids = implode(',', $excludecids);
            $where_ba .= " and ba.cli_ID not in ({$excludecids}) ";
        }
    }

    $sql_child = array();

    if (function_exists('tr_pdd_get_batch_types')) {
        foreach (trbilling_req_types() as $t) {

            if ($t == 'study') continue;

            if (
                $show_menus_options['all']['form_' . $t]
                || $ci_active_menu['form_' . $t]
                || in_array($current_role, array('Billing', 'BillbyClinic', 'BillbyRefLab', 'AdminCIDW'))
            ) {
                $batch_tbl = tr_pdd_get_table_by_btype($t);
                $report_tbl = tr_pdd_get_table_report_by_btype($t);

                $sql_child[] = " select ba.mid, 
                        ba.assen, 
                        ba.requesting_practice,
                        ba.requesting_physician,
                        ba.lab_id,
                        ba.batch_created,
                        ba.batch_modified,
                        ba.result_pdf,
                        
                        pa.pa_first,
                        pa.pa_last,
                        pa.pa_ssn,
                        pa.pa_dob,
                        ins.ins_company,
                        ins.ins_member_id,
                        ins.ins_group_id,
                        re.id,
                        re.created_date,
                        re.approve_date,
                        re.estatus
                 from {$batch_tbl} ba
                 join ci_patients pa ON pa.pa_ID = ba.patient_id
                 left join ci_insurances ins ON ins.ins_ID = ba.insurance1
                 left join {$report_tbl} re on  ba.assen = re.patient_id 
                 where 1=1  {$where_ba} ";
            }
        }
    }


    $sql_child = implode(" UNION ALL ", $sql_child);

    $sql = "select * from ({$sql_child}) ba
    where 1=1 {$where} ";
    $results = $wpdb->get_results($sql);



    header("Content-disposition: attachment; filename={$file_name}");
    header("Content-Type: text/csv");
    $f = fopen('php://output', 'w');
    $headers = array(
        'Accession Number',
        'Result date',
        'Clinic',
        'Doctor',
        'SSN',
        'First name',
        'Last name',
        'DOB',
        'Insurance',
        'Member ID',
        'Group #',
        ''
    );
    fputcsv($f, $headers);
    foreach ($results as $r) {
        if (empty($r->created_date)) {
            $r->created_date = date('Y-m-d', strtotime($r->batch_modified));
            if (empty($r->created_date)) {
                $r->created_date = date('Y-m-d', strtotime($r->batch_created));
            }
        }
        $has_pdf = !empty($r->result_pdf) ? 1 : '';
        $row = array(
            $r->assen,
            $r->created_date,
            $r->requesting_practice,
            $r->requesting_physician,
            $r->pa_ssn,
            $r->pa_first,
            $r->pa_last,
            $r->pa_dob,
            $r->ins_company,
            $r->ins_member_id,
            $r->ins_group_id,
            $has_pdf
        );
        fputcsv($f, $row);
    }
    exit;
}

function tradmin_action_ci_export_data_batch()
{
    check_ajax_referer('ci_close_lab_export_data', 'nonce');

    global $is_ci_admin;
    $user_id = get_current_user_id();
    if ($user_id < 1) {
        wp_send_json_error(array('message' => 'Please sign in to export data.'), 403);
    }
    if (!$is_ci_admin && !current_user_can('ci_data_export')) {
        wp_send_json_error(array('message' => 'You do not have permission to export patient testing data.'), 403);
    }
    $request_id = isset($_POST['request_id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['request_id']) : '';
    if (empty($request_id)) {
        $request_id = 'ci-export-' . time() . '-' . wp_rand(100000, 999999);
    }
    $request_id = substr($request_id, 0, 80);
    $can_debug_export = current_user_can('manage_options');

    register_shutdown_function(function () use ($request_id) {
        $error = error_get_last();
        $fatal_types = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
        if (!empty($error) && in_array($error['type'], $fatal_types, true)) {
            error_log("[CI Export Data {$request_id}] Fatal: {$error['message']} in {$error['file']}:{$error['line']}");
        }
    });

    $token = isset($_POST['token']) ? sanitize_key($_POST['token']) : '';
    $is_new_export = empty($token);
    if ($is_new_export) {
        if (!$is_ci_admin) {
            $authorized = isset($_POST['authorized']) && (int) $_POST['authorized'] === 1;
            $password = isset($_POST['password']) ? wp_unslash($_POST['password']) : '';
            $user = get_user_by('id', $user_id);
            if (!$authorized) {
                wp_send_json_error(array('message' => 'Please confirm the export authorization.'), 403);
            }
            if (!$user || empty($password) || !wp_check_password($password, $user->user_pass, $user_id)) {
                wp_send_json_error(array('message' => 'Please re-enter your LIS password to prepare the export.'), 403);
            }
        }
        require_once(ABSPATH . 'wp-admin/includes/file.php');

        $previous_token = get_user_meta($user_id, '_ci_export_data_token', true);
        if (!empty($previous_token)) {
            $previous_state = get_transient('ci_export_data_' . $previous_token);
            if (is_array($previous_state) && !empty($previous_state['file']) && file_exists($previous_state['file'])) {
                @unlink($previous_state['file']);
            }
            if (is_array($previous_state) && function_exists('trpdd_log_user')) {
                trpdd_log_user($user_id, 'data_export', array(
                    'status' => 'canceled',
                    'records_exported' => isset($previous_state['processed']) ? (int) $previous_state['processed'] : 0,
                    'request_id' => isset($previous_state['request_id']) ? $previous_state['request_id'] : '',
                    'reason' => 'Replaced by a new export',
                ));
            }
            delete_transient('ci_export_data_' . $previous_token);
        }

        $token = strtolower(wp_generate_password(24, false, false));
        $file = wp_tempnam('ci_export_data_' . $user_id . '_' . $token . '.csv');
        if (empty($file)) {
            wp_send_json_error(array('message' => 'Could not create export file.'), 500);
        }
        $state = array(
            'user_id' => $user_id,
            'file' => $file,
            'processed' => 0,
            'type_index' => 0,
            'last_mid' => 0,
            'done' => false,
            'request_id' => $request_id,
        );
        update_user_meta($user_id, '_ci_export_data_token', $token);
        set_transient('ci_export_data_' . $token, $state, HOUR_IN_SECONDS);
        wp_schedule_single_event(time() + HOUR_IN_SECONDS, 'ci_export_data_cleanup_file', array($token, $user_id));
    } else {
        $state = get_transient('ci_export_data_' . $token);
        if (!is_array($state) || (int) $state['user_id'] !== $user_id || !empty($state['done'])) {
            wp_send_json_error(array('message' => 'Export session has expired.'), 404);
        }
    }

    $export_types = array_values(array_diff(tr_pdd_get_batch_types(), array('study')));
    if (empty($export_types)) {
        wp_send_json_error(array('message' => 'No testing types are available for export.'), 500);
    }
    if ($is_new_export) {
        wp_send_json_success(array(
            'token' => $token,
            'processed' => 0,
            'percent' => 0,
            'done' => false,
            'request_id' => $request_id,
            'initialized' => true,
            'type_total' => count($export_types),
            'next_testing_type' => $export_types[0],
        ));
    }
    $state['processed'] = isset($state['processed']) ? (int) $state['processed'] : 0;
    $state['type_index'] = isset($state['type_index']) ? (int) $state['type_index'] : 0;
    $state['last_mid'] = isset($state['last_mid']) ? (int) $state['last_mid'] : 0;
    if (!isset($export_types[$state['type_index']])) {
        wp_send_json_error(array('message' => 'Export session has expired. Please start again.'), 404);
    }
    $current_type = $export_types[$state['type_index']];
    $current_type_position = $state['type_index'] + 1;

    $ci_close_lab_export = true;
    $ci_close_lab_export_batch = true;
    $ci_close_lab_export_current_type = $current_type;
    $ci_close_lab_export_last_mid = $state['last_mid'];
    $ci_close_lab_export_offset = $state['processed'];
    $ci_close_lab_export_limit = 100;
    $ci_close_lab_export_write_headers = $state['processed'] === 0;
    $ci_close_lab_export_processed = 0;
    $return_file = $state['file'];
    $_REQUEST['trajax'] = 'Export';

    ob_start();
    try {
        include(TRCDBI_PATH . 'pages/admin_ci_insurance_reports.php');
    } catch (Throwable $e) {
        $diagnostic_output = ob_get_clean();
        error_log("[CI Export Data {$request_id}] " . get_class($e) . ': ' . $e->getMessage() . " in {$e->getFile()}:{$e->getLine()}");
        $data = array('message' => 'Export failed on the server. Reference: ' . $request_id);
        if ($can_debug_export) {
            $data['detail'] = get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine();
            if (!empty(trim($diagnostic_output))) {
                $data['detail'] .= "\n\nServer output:\n" . substr($diagnostic_output, 0, 3000);
            }
        }
        wp_send_json_error($data, 500);
    }
    $diagnostic_output = ob_get_clean();
    if (!empty(trim($diagnostic_output))) {
        error_log("[CI Export Data {$request_id}] Unexpected output: " . substr(strip_tags($diagnostic_output), 0, 3000));
        $data = array('message' => 'Export returned unexpected server output. Reference: ' . $request_id);
        if ($can_debug_export) {
            $data['detail'] = substr($diagnostic_output, 0, 3000);
        }
        wp_send_json_error($data, 500);
    }

    $state['processed'] += (int) $ci_close_lab_export_processed;
    if ($ci_close_lab_export_processed < $ci_close_lab_export_limit) {
        $state['type_index']++;
        $state['last_mid'] = 0;
    } else {
        $last_row = end($rows);
        $state['last_mid'] = !empty($last_row->mid) ? (int) $last_row->mid : 0;
    }
    $state['done'] = $state['type_index'] >= count($export_types);
    set_transient('ci_export_data_' . $token, $state, HOUR_IN_SECONDS);

    $percent = $state['done'] ? 100 : floor(($state['type_index'] / count($export_types)) * 100);
    $response = array(
        'token' => $token,
        'processed' => $state['processed'],
        'percent' => $percent,
        'done' => $state['done'],
        'request_id' => $request_id,
        'testing_type' => $current_type,
        'type_position' => $current_type_position,
        'type_total' => count($export_types),
        'next_testing_type' => $state['done'] ? '' : $export_types[$state['type_index']],
    );
    if ($state['done']) {
        $response['download'] = wp_nonce_url(
            admin_url('admin.php?tr_action=ci_export_data_download&token=' . rawurlencode($token)),
            'ci_export_data_download_' . $token
        );
    }
    wp_send_json_success($response);
}

function tradmin_action_ci_export_data_download()
{
    if (get_current_user_id() < 1) {
        wp_die('Please sign in to export data.');
    }
    global $wpdb, $blog_id, $current_lab_id, $is_ci_admin;
    if (!$is_ci_admin && !current_user_can('ci_data_export')) {
        wp_die('You do not have permission to export patient testing data.', 'Access denied', array('response' => 403));
    }

    $token = isset($_GET['token']) ? sanitize_key($_GET['token']) : '';
    check_admin_referer('ci_export_data_download_' . $token);
    $state = get_transient('ci_export_data_' . $token);
    if (!is_array($state) || (int) $state['user_id'] !== get_current_user_id() || empty($state['done']) || !file_exists($state['file'])) {
        wp_die('The export file is no longer available.');
    }

    $lab_id = !empty($current_lab_id) ? (int) $current_lab_id : 0;
    if ($lab_id < 1 && !empty($blog_id)) {
        $lab_id = (int) $wpdb->get_var($wpdb->prepare("select lab_ID from ci_labs where lab_site = %d limit 1", $blog_id));
    }
    $lab_code = $lab_id > 0 && function_exists('tr_get_lab_short') ? tr_get_lab_short($lab_id) : 'LAB';
    $lab_code = preg_replace('/[^A-Za-z0-9_-]/', '', $lab_code);
    if (empty($lab_code)) {
        $lab_code = 'LAB';
    }
    $filename = 'igniteLIS_testing_data_export_all_records_' . $lab_code . '_' . date_i18n('Ymd-Hi') . '.csv';
    if (function_exists('trpdd_log_user')) {
        trpdd_log_user(get_current_user_id(), 'data_export', array(
            'status' => 'completed',
            'records_exported' => isset($state['processed']) ? (int) $state['processed'] : 0,
            'file_name' => $filename,
            'request_id' => isset($state['request_id']) ? $state['request_id'] : '',
        ));
    }

    header('Content-disposition: attachment; filename=' . $filename);
    header('Content-Type: text/csv');
    header('Content-Length: ' . filesize($state['file']));
    readfile($state['file']);
    @unlink($state['file']);
    delete_transient('ci_export_data_' . $token);
    delete_user_meta(get_current_user_id(), '_ci_export_data_token', $token);
    exit;
}

function tradmin_action_ci_export_data_cancel()
{
    check_ajax_referer('ci_close_lab_export_data', 'nonce');

    global $is_ci_admin;
    $user_id = get_current_user_id();
    if ($user_id < 1) {
        wp_send_json_error(array('message' => 'Please sign in to cancel the export.'), 403);
    }
    if (!$is_ci_admin && !current_user_can('ci_data_export')) {
        wp_send_json_error(array('message' => 'You do not have permission to cancel this export.'), 403);
    }

    $token = isset($_POST['token']) ? sanitize_key($_POST['token']) : '';
    if (empty($token)) {
        wp_send_json_error(array('message' => 'No export session was found.'), 404);
    }
    $state = get_transient('ci_export_data_' . $token);
    if (!is_array($state) || (int) $state['user_id'] !== $user_id) {
        wp_send_json_error(array('message' => 'Export session has expired.'), 404);
    }
    if (!empty($state['file']) && file_exists($state['file'])) {
        @unlink($state['file']);
    }
    if (function_exists('trpdd_log_user')) {
        trpdd_log_user($user_id, 'data_export', array(
            'status' => 'canceled',
            'records_exported' => isset($state['processed']) ? (int) $state['processed'] : 0,
            'request_id' => isset($state['request_id']) ? $state['request_id'] : '',
            'reason' => 'Canceled by user',
        ));
    }
    delete_transient('ci_export_data_' . $token);
    delete_user_meta($user_id, '_ci_export_data_token', $token);

    wp_send_json_success(array('message' => 'Export canceled.'));
}
