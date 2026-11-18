<?php
global $wpdb, $is_ci_admin;

$act    = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';
$id     = isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;

$show_adv = isset($_GET['debug']) || isset($_REQUEST['view']) || isset($_COOKIE['trquery_monitor']);

$where  = " and k.wsdl_url!='' and k.wsdl_url is not null ";
$admin_view         = isset($_REQUEST['view']) || isset($_COOKIE['trquery_monitor']) || $is_ci_admin;

if (!function_exists('trpdd_render_wsdl_client_log_history')) {
    function trpdd_render_wsdl_client_log_history($key, $btype, $batch_tbl, $is_result, $log_search = '', $log_paged = 1)
    {
        global $wpdb;

        $key = (array) $key;
        $log_search = sanitize_text_field(wp_unslash($log_search));
        $log_paged = max(1, absint($log_paged));
        $log_per_page = 25;
        $log_offset = ($log_paged - 1) * $log_per_page;
        $log_where = '';
        if ($log_search != '') {
            $log_like = '%' . $wpdb->esc_like($log_search) . '%';
            $log_where .= $wpdb->prepare(
                " and (
                    ba.assen like %s
                    or ba.patient_name like %s
                    or ba.panel_tests_requested like %s
                    or c_batch.cli_clinic like %s
                    or c_cli.cli_clinic like %s
                ) ",
                $log_like,
                $log_like,
                $log_like,
                $log_like,
                $log_like
            );
        }

        $log_total = (int) $wpdb->get_var($wpdb->prepare(
            "select count(*)
            from ci_wsdl_batch wb
            left join {$batch_tbl} ba ON wb.b_mid = ba.mid and wb.btype = %s
            left join ci_clinic c_batch ON c_batch.cID = ba.cli_ID
            left join ci_clinic c_cli ON c_cli.cID = wb.b_mid and wb.btype = 'cli'
            where wb.b_wsdl_ID = %d
            and wb.btype in (%s, 'cli')
            {$log_where}",
            $btype,
            $key['wsdl_ID'],
            $btype
        ));
        $log_total_pages = max(1, ceil($log_total / $log_per_page));
        if ($log_paged > $log_total_pages) {
            $log_paged = $log_total_pages;
            $log_offset = ($log_paged - 1) * $log_per_page;
        }

        $wsdl_logs = $wpdb->get_results($wpdb->prepare(
            "select wb.b_mid, wb.btype, wb.b_status, wb.bdate,
                    ba.assen, ba.patient_name, ba.panel_tests_requested,
                    if(wb.btype = 'cli', c_cli.cli_clinic, c_batch.cli_clinic) as clinic_name,
                    if(wb.btype = 'cli', '', l_batch.lab_dba_init) as lab_name
            from ci_wsdl_batch wb
            left join {$batch_tbl} ba ON wb.b_mid = ba.mid and wb.btype = %s
            left join ci_clinic c_batch ON c_batch.cID = ba.cli_ID
            left join ci_labs l_batch ON l_batch.lab_ID = if(ba.lab_id > 0, ba.lab_id, ba.ref_lab_id)
            left join ci_clinic c_cli ON c_cli.cID = wb.b_mid and wb.btype = 'cli'
            where wb.b_wsdl_ID = %d
            and wb.btype in (%s, 'cli')
            {$log_where}
            order by wb.bdate desc, wb.b_mid desc
            limit %d,%d",
            $btype,
            $key['wsdl_ID'],
            $btype,
            $log_offset,
            $log_per_page
        ));

        ob_start();
?>
        <div class="wsdl-log-history-content">
            <h3 style="margin-top:0;">Log History</h3>
            <form method="get" class="ci-wsdl-client-log-search" style="margin:0 0 12px;">
                <input type="search" name="log_search" value="<?php echo esc_attr($log_search) ?>" placeholder="Search assen, test, clinic" style="width:300px;max-width:100%;" />
                <input type="submit" class="button" value="Search" />
                <?php if ($log_search != '') { ?>
                    <a class="button js-wsdl-log-reset" href="#">Reset</a>
                <?php } ?>
                <span class="sloading" style="display:none;"></span>
            </form>
            <div class="tablenav top" style="margin:0 0 8px;">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo number_format_i18n($log_total) ?> items</span>
                    <span class="pagination-links">
                        <a class="button first-page js-wsdl-log-page<?php echo $log_paged <= 1 ? ' disabled' : '' ?>" data-page="1" href="#">&laquo;</a>
                        <a class="button prev-page js-wsdl-log-page<?php echo $log_paged <= 1 ? ' disabled' : '' ?>" data-page="<?php echo max(1, $log_paged - 1) ?>" href="#">&lsaquo;</a>
                        <span class="paging-input"><?php echo (int) $log_paged ?> of <span class="total-pages"><?php echo (int) $log_total_pages ?></span></span>
                        <a class="button next-page js-wsdl-log-page<?php echo $log_paged >= $log_total_pages ? ' disabled' : '' ?>" data-page="<?php echo min($log_total_pages, $log_paged + 1) ?>" href="#">&rsaquo;</a>
                        <a class="button last-page js-wsdl-log-page<?php echo $log_paged >= $log_total_pages ? ' disabled' : '' ?>" data-page="<?php echo $log_total_pages ?>" href="#">&raquo;</a>
                    </span>
                </div>
            </div>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Assen</th>
                        <th>Patient Name</th>
                        <th>Panels</th>
                        <th>Clinics</th>
                        <th>Lab</th>
                        <th>Action</th>
                        <th>Send</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($wsdl_logs)) {
                        foreach ($wsdl_logs as $row) {
                            $action = $row->btype == 'cli' ? 'Sync Clinic' : ($is_result ? 'Send Result' : 'Send Request');
                            $send_status = 'Pending';
                            if ((int)$row->b_status === 1) {
                                $send_status = 'Sent';
                            } else if ((int)$row->b_status === 2) {
                                $send_status = 'Resend';
                            }
                    ?>
                            <tr>
                                <td><?php echo esc_html(!empty($row->assen) ? $row->assen : '-') ?></td>
                                <td><?php echo esc_html(!empty($row->patient_name) ? $row->patient_name : '-') ?></td>
                                <td><?php echo esc_html(!empty($row->panel_tests_requested) ? $row->panel_tests_requested : '-') ?></td>
                                <td><?php echo esc_html(!empty($row->clinic_name) ? $row->clinic_name : '-') ?></td>
                                <td><?php echo esc_html(!empty($row->lab_name) ? $row->lab_name : '-') ?></td>
                                <td>
                                    <?php if ($row->btype != 'cli') { ?>
                                        <a href="#" class="js-reset-log-status" data-mid="<?php echo (int) $row->b_mid ?>" data-btype="<?php echo esc_attr($row->btype) ?>" data-wsdl-id="<?php echo (int) $key['wsdl_ID'] ?>"><?php echo esc_html($action) ?></a>
                                    <?php } else { ?>
                                        <?php echo esc_html($action) ?>
                                    <?php } ?>
                                </td>
                                <td class="log-send-status"><?php echo esc_html($send_status . (!empty($row->bdate) ? ' (' . $row->bdate . ')' : '')) ?></td>
                            </tr>
                        <?php }
                    } else { ?>
                        <tr>
                            <td colspan="7">No log history found.</td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            <div class="tablenav bottom" style="margin-top:8px;">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo number_format_i18n($log_total) ?> items</span>
                    <span class="pagination-links">
                        <a class="button first-page js-wsdl-log-page<?php echo $log_paged <= 1 ? ' disabled' : '' ?>" data-page="1" href="#">&laquo;</a>
                        <a class="button prev-page js-wsdl-log-page<?php echo $log_paged <= 1 ? ' disabled' : '' ?>" data-page="<?php echo max(1, $log_paged - 1) ?>" href="#">&lsaquo;</a>
                        <span class="paging-input"><?php echo (int) $log_paged ?> of <span class="total-pages"><?php echo (int) $log_total_pages ?></span></span>
                        <a class="button next-page js-wsdl-log-page<?php echo $log_paged >= $log_total_pages ? ' disabled' : '' ?>" data-page="<?php echo min($log_total_pages, $log_paged + 1) ?>" href="#">&rsaquo;</a>
                        <a class="button last-page js-wsdl-log-page<?php echo $log_paged >= $log_total_pages ? ' disabled' : '' ?>" data-page="<?php echo $log_total_pages ?>" href="#">&raquo;</a>
                    </span>
                </div>
            </div>
        </div>
<?php
        return ob_get_clean();
    }
}

if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'test_client') {
    $_GET['testcron'] = true;
    $data       = isset($_POST['key']) ? $_POST['key'] : '';
    $test_type  = isset($_POST['typet']) ? $_POST['typet'] : '';

    ob_get_clean();
    $return = array('status' => 'error', 'msg' => 'It works');

    if ($test_type == 'run') {

        ob_start();
        do_action('ci_cron_sync_wsdl_client_req');
        do_action('ci_cron_sync_wsdl_client_result');

        $return['msg'] = ob_get_clean();
        wp_send_json($return);
        exit;
    }


    try {
        $client = new CI_Data_Work_Client($data);
        if ($test_type == 'pdf') {
            $doctors = $wpdb->get_results("select doc_ID,doc_first,doc_last,doc_npi,doc_email,doc_phone from ci_doc");
            //$return['clinics'] = $clinics;
            $rs = $client->get_pdf_results($doctors, -1, 10);
        } else if ($data['wsdl_type'] == 'getreqrec') {
            $rs = $client->get_req_by_assen('MDR12801967');
            $rs = (object) $rs;
        } else if ($test_type == 'run') {
        } else {
            $rs = $client->test_key();
        }
        $return['rs'] = $rs;
        $return['show'] = json_encode($rs);

        if ($rs && ($rs->status == 'OK' || $rs->status == 'EMPTY')) {
            $return['status'] = 'ok';
        } else {
            $return['msg'] = $rs->msg . '';
        }
    } catch (Exception $e) {
        $return['msg'] = $e->getMessage() . '';
    }
    wp_send_json($return);
    exit;
} else if (isset($_REQUEST['trajax'])  && $_REQUEST['trajax'] == 'do_cron') {
    echo 'do cron:<br>';
    $_GET['do_cron'] = true;
    define('DOING_CRON', true);
    include_once(TRCPDD_PATH . 'inc/crons.php');

    do_action('ci_cron_sync_wsdl_client_req', array('pcr'));
    do_action('ci_cron_sync_wsdl_client_result', array('pcr'));
    echo '<br>ok1';
    exit;
} else if (isset($_REQUEST['trajax'])  && $_REQUEST['trajax'] == 'resetall') {
    $wpdb->query("update ci_wsdl_batch set b_status = 2 where b_wsdl_ID = {$id}");
    echo 'ok';
    exit;
} else if (isset($_REQUEST['trajax'])  && $_REQUEST['trajax'] == 'resetlogstatus') {
    $batch_mid = isset($_POST['batch_mid']) ? (int) $_POST['batch_mid'] : 0;
    $batch_type = isset($_POST['batch_type']) ? sanitize_text_field($_POST['batch_type']) : '';
    $wsdl_id = isset($_POST['wsdl_id']) ? (int) $_POST['wsdl_id'] : 0;

    if ($batch_mid <= 0 || $wsdl_id <= 0 || $batch_type == '') {
        wp_send_json(array('success' => false, 'msg' => 'Invalid log item.'));
    }

    $updated = $wpdb->update(
        'ci_wsdl_batch',
        array('b_status' => 2),
        array(
            'b_mid' => $batch_mid,
            'btype' => $batch_type,
            'b_wsdl_ID' => $wsdl_id
        )
    );

    if ($updated === false) {
        wp_send_json(array('success' => false, 'msg' => $wpdb->last_error ? $wpdb->last_error : 'Update failed.'));
    }

    if ($updated === 0) {
        $current_status = $wpdb->get_var($wpdb->prepare(
            "select b_status from ci_wsdl_batch where b_mid = %d and btype = %s and b_wsdl_ID = %d limit 1",
            $batch_mid,
            $batch_type,
            $wsdl_id
        ));

        if ($current_status === null) {
            wp_send_json(array('success' => false, 'msg' => 'No matching log row was found.'));
        }

        if ((int) $current_status === 2) {
            wp_send_json(array('success' => true, 'msg' => 'Already marked for resend.'));
        }

        wp_send_json(array('success' => true, 'msg' => 'Log row matched but status was unchanged.'));
    }

    wp_send_json(array('success' => true, 'msg' => 'Marked for resend.'));
} else if (isset($_REQUEST['trajax'])  && $_REQUEST['trajax'] == 'wsdl_client_log_history') {
    $wsdl_id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
    $log_search = isset($_REQUEST['log_search']) ? sanitize_text_field(wp_unslash($_REQUEST['log_search'])) : '';
    $log_paged = isset($_REQUEST['logpaged']) ? max(1, absint($_REQUEST['logpaged'])) : 1;
    $key = $wpdb->get_row($wpdb->prepare("select * from ci_wsdl_keys where wsdl_ID = %d and wsdl_url != '' and wsdl_url is not null", $wsdl_id), ARRAY_A);

    if (!$key || (strpos($key['wsdl_type'], 'req_') === false && strpos($key['wsdl_type'], 'result_') === false)) {
        wp_send_json(array('success' => false, 'msg' => 'Invalid client key.'));
    }

    $is_result = stripos($key['wsdl_type'], 'result_') !== false;
    $btype = str_replace(array('req_', 'result_'), '', $key['wsdl_type']);
    $batch_tbl = tr_pdd_get_table_by_btype($btype);

    wp_send_json(array(
        'success' => true,
        'html' => trpdd_render_wsdl_client_log_history($key, $btype, $batch_tbl, $is_result, $log_search, $log_paged)
    ));
}

if (isset($_POST['key'])) {
    $_POST  = tr_pdd_stripslashes_deep($_POST);
    $key    = $_POST['key'];

    $key['wsdl_group_IDs'] = implode(',', is_array($key['wsdl_group_IDs']) ? $key['wsdl_group_IDs'] : array());

    $clinics = is_array($_POST['cli_IDs']) ? $_POST['cli_IDs'] : array();

    $key_id = $key['wsdl_ID'];

    if ($key_id > 0) {
        $rs = $wpdb->update('ci_wsdl_keys', $key, array('wsdl_ID' => $key_id));
        if ($rs === false) {
            Tr_Session_Class::add_error('Error: ' . $wpdb->last_error);
        } else {
            Tr_Session_Class::add('Updated Key Successfully!');
        }
    } else {
        $rs     = $wpdb->insert('ci_wsdl_keys', $key);
        $key_id = $wpdb->insert_id;

        if ($key_id > 0) {
            Tr_Session_Class::add('Created New Key Successfully! <a href="' . Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $key_id), false) . '">Edit</a>');
            try {
                $client = new CI_Data_Work_Client($key);
                $rs = $client->test_key();

                if ($rs && ($rs->status == 'OK' || $rs->status == 'EMPTY')) {

                    Tr_Session_Class::add('This Key Works!');
                } else {
                    $key['wsdl_active'] = 0;
                    Tr_Session_Class::add_error('Error: The Url or Key is wrong');
                }
            } catch (Exception $e) {
                Tr_Session_Class::add_error('Error: ' . $e->getMessage());
            }
            $key = array();
        } else {
            Tr_Session_Class::add_error('Error: ' . $wpdb->last_error);
        }
        $_POST = array();
        //test

    }

    if ($key_id > 0) {
        $wpdb->delete('ci_wsdl_key_clinics', array('wsdl_ID' => $key_id));
        foreach ($clinics as $cli_ID) {
            $wpdb->insert('ci_wsdl_key_clinics', array('wsdl_ID' => $key_id, 'cli_ID' => $cli_ID));
        }
    }
}

if ($act == 'delete') {
    $id = (int) $id;
    $can_delete = $wpdb->get_var($wpdb->prepare(
        "select count(*) from ci_wsdl_keys where wsdl_ID = %d and wsdl_active = 0 and wsdl_url != '' and wsdl_url is not null",
        $id
    ));

    if ($can_delete) {
        $wpdb->delete('ci_wsdl_keys', array('wsdl_ID' => $id), array('%d'));
        $wpdb->delete('ci_wsdl_key_clinics', array('wsdl_ID' => $id), array('%d'));

        Tr_Session_Class::add('Deleted Key Successfully!');
    } else {
        Tr_Session_Class::add('Cant delete enabled client server!');
    }
}


if ($act == 'edit' || $act == 'add') {

    $cli_IDs = array();

    if ($id > 0 && $act == 'edit') {
        $key = $wpdb->get_row("select * from ci_wsdl_keys where wsdl_ID = {$id}", ARRAY_A);
        $cli_IDs = $wpdb->get_col("select cli_ID from ci_wsdl_key_clinics where wsdl_ID = {$id}");
        $key['wsdl_group_IDs'] = !empty($key['wsdl_group_IDs']) ? explode(',', $key['wsdl_group_IDs']) : array();
        try {
            $client = new CI_Data_Work_Client($key);
            $rs = $client->test_key();
            if ($rs && ($rs->status == 'OK' || $rs->status == 'EMPTY')) {

                Tr_Session_Class::add('This Key Works!');
            } else {
                $key['wsdl_active'] = 0;
                Tr_Session_Class::add_error('Error: The Url or Key is wrong');
            }
        } catch (Exception $e) {
            Tr_Session_Class::add_error('Error: ' . $e->getMessage());
        }
    } else {
        $key = array();
        $key['wsdl_group_IDs'] = array();
        $key['wsdl_type'] = '';
    }


    $clinics    = $wpdb->get_results("select * from ci_clinic where cli_clinic !='' order by cli_clinic");
    $groups     = $wpdb->get_results("select * from ci_test_groups where tg_show_req = 1 order by tg_name asc ");
    $labs       = $wpdb->get_results("select * from ci_labs where (lab_is_ref = 0 or lab_is_ref = 2) order by lab_dba_init asc");
    $ref_labs   = $wpdb->get_results("select * from ci_labs where (lab_is_ref = 1 or lab_is_ref = 2) order by lab_dba_init asc");

    $doctors = $wpdb->get_results("select doc.doc_ID, 
	                                        doc.doc_first, 
	                                        doc.doc_last,
	                                        doc.doc_npi,
	                                        cd.cli_ID
	                            from ci_doc doc
	                            join ci_clinic_doc cd ON doc.doc_ID = cd.doc_ID
	                            order by doc.doc_first asc", ARRAY_A);
?>

    <div class="wrap wsdl-client-editor">
        <h2><?php echo ($act == 'add') ? 'Add New' : 'Edit' ?> Key <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">All Keys</a> </h2>
        <?php Tr_Session_Class::show() ?>

        <form method="post" id="form_wsdl">
            <div class="frow">
                <input type="hidden" name="key[wsdl_active]" value="0" />
                <label><input type="checkbox" name="key[wsdl_active]" value="1" <?php checked(isset($key['wsdl_active']) ? $key['wsdl_active'] : '') ?> /> Active</label>
            </div>
            <div class="frow blabel">
                <div class="col2">
                    <label>Server Url</label>
                    <input type="text" name="key[wsdl_url]" value="<?php echo isset($key['wsdl_url']) ? $key['wsdl_url'] : '' ?>" maxlength="300" required />
                </div>
                <div class="col2">
                    <label>Key</label>
                    <input type="text" name="key[wsdl_key]" value="<?php echo isset($key['wsdl_key']) ? $key['wsdl_key'] : '' ?>" maxlength="50" required />
                </div>
            </div>

            <div class="frow blabel">
                <div class="col2">
                    <div class="frow">
                        <label>API Type</label>
                        <select name="key[wsdl_api_type]">
                            <?php
                            foreach (array('0' => 'Wsdl', 1 => 'Json') as $k => $txt) {
                                $selected = isset($key['wsdl_api_type']) && $key['wsdl_api_type'] == $k ? 'selected' : '';
                                echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="frow">
                        <label> Clinics</label>
                        <select name="cli_IDs[]" multiple style="height:200px" class="js_select2_tag">
                            <?php

                            foreach ($clinics as $cli) {
                                $selected = in_array($cli->cID, $cli_IDs) ? 'selected' : '';
                                echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div class="frow">
                        <label>Default Clinic</label>
                        <select name="key[wsdl_df_cli_ID]">
                            <option value="">Select</option>
                            <?php
                            foreach ($clinics as $cli) {
                                $selected = $cli->cID == $key['wsdl_df_cli_ID'] ? 'selected' : '';
                                echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div class="frow">
                        <label>Default Doctor</label>
                        <select name="key[wsdl_df_doc_ID]"></select>
                    </div>

                    <div class="frow">
                        <label>Lab</label>
                        <select name="key[wsdl_lab_ID]">
                            <option value="">Select Lab</option>
                            <?php
                            foreach ($labs as $l) {
                                $selected = isset($key['wsdl_lab_ID']) && $l->lab_ID == $key['wsdl_lab_ID'] ? 'selected' : '';
                                echo '<option value="' . $l->lab_ID . '" ' . $selected . '>' . $l->lab_dba_init . ' - ' . $l->lab_name . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="frow">
                        <label>Ref Lab</label>
                        <select name="key[wsdl_ref_lab_ID]">
                            <option value="">Select Ref Lab</option>
                            <?php
                            foreach ($ref_labs as $l) {
                                $selected = isset($key['wsdl_ref_lab_ID']) && $l->lab_ID == $key['wsdl_ref_lab_ID'] ? 'selected' : '';
                                echo '<option value="' . $l->lab_ID . '" ' . $selected . '>' . $l->lab_dba_init . ' - ' . $l->lab_name  . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="frow">
                        <label>Start Date</label>
                        <input type="text" name="key[wsdl_start]" value="<?php if (isset($key['wsdl_start']) && $key['wsdl_start'] != '0000-00-00') echo $key['wsdl_start'] ?>" class="hasdate" data-write="1" />
                    </div>
                </div>
                <div class="col2">
                    <div class="frow">
                        <label>Action</label>
                        <select name="key[wsdl_type]" id="wsdl_type" required>
                            <option value="">Select Type</option>
                            <?php
                            $types = array(
                                'pdf' => 'PDF Result',
                                'ref' => 'Put Req (all types) with ref lab',
                            );
                            foreach (tr_pdd_get_batch_types() as $btype) {
                                $types['req_' . $btype] = 'Put ' . tr_pdd_get_typename($btype) . ' Req';
                                $types['result_' . $btype] = 'Put ' . tr_pdd_get_typename($btype) . ' Result';

                                $types['get_' . $btype] = 'GET ' . tr_pdd_get_typename($btype) . ' Req';
                                $types['getrs_' . $btype] = 'GET ' . tr_pdd_get_typename($btype) . ' Result';

                                if ($btype == 'pcr') {

                                    $types['pcovid'] = 'Put Covid';
                                    $types['getreqrec'] = 'GET Req Data in Receiving or Move to ref lab';
                                }
                            }
                            foreach ($types as $k => $vl) {
                                $selected = isset($key['wsdl_type']) && $k == $key['wsdl_type'] ? 'selected' : '';
                                echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="frow">
                        <label>Test Groups</label>
                        <select name="key[wsdl_group_IDs][]" multiple class="js_select2">
                            <option value="">All Groups</option>
                            <?php
                            foreach ($groups as $group) {
                                $selected = in_array($group->tg_ID, $key['wsdl_group_IDs']) ? 'selected' : '';
                                echo '<option value="' . $group->tg_ID . '" ' . $selected . '>' . $group->tg_name . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="frow">
                        <label>Clinic Name(override)</label>
                        <input type="text" name="key[wsdl_clinicname]" value="<?php echo $key['wsdl_clinicname'] ?>" />
                    </div>
                    <div class="frow">
                        <label>Doctor Name(override)</label>
                        <input type="text" name="key[wsdl_doctorname]" value="<?php echo $key['wsdl_doctorname'] ?>" />
                    </div>
                    <div class="frow">
                        <label>Instrument (with cutoff)</label>
                        <input type="text" name="key[wsdl_send_instrument]" value="<?php echo $key['wsdl_send_instrument'] ?>" />
                    </div>

                    <div class="frow">
                        <input type="hidden" name="key[wsdl_sync_clinic]" value="0" />
                        <label><input type="checkbox" name="key[wsdl_sync_clinic]" value="1" <?php checked(isset($key['wsdl_sync_clinic']) ? $key['wsdl_sync_clinic'] : '') ?> /> Sync Clinic</label>
                    </div>
                    <div class="frow showbytype type_req_tox">
                        <input type="hidden" name="key[wsdl_sync_confirmation]" value="0" />
                        <label><input type="checkbox" name="key[wsdl_sync_confirmation]" value="1" <?php checked(isset($key['wsdl_sync_confirmation']) ? $key['wsdl_sync_confirmation'] : '') ?> /> Only Confirmation Tests</label>
                    </div>
                    <div class="frow showbytype type_req_tox">
                        <input type="hidden" name="key[wsdl_dont_send_bid]" value="0" />
                        <label><input type="checkbox" name="key[wsdl_dont_send_bid]" value="1" <?php checked(isset($key['wsdl_dont_send_bid']) ? $key['wsdl_dont_send_bid'] : '') ?> /> Dont send Batch#</label>
                    </div>
                    <?php if ($show_adv) { ?>
                        <div class="frow">
                            <input type="hidden" name="key[wsdl_dont_approve]" value="0" />
                            <label><input type="checkbox" name="key[wsdl_dont_approve]" value="1" <?php checked(isset($key['wsdl_dont_approve']) ? $key['wsdl_dont_approve'] : '') ?> /> Dont approve</label>
                        </div>
                        <div class="frow">
                            <input type="hidden" name="key[wsdl_fix_ref_test]" value="0" />
                            <label><input type="checkbox" name="key[wsdl_fix_ref_test]" value="1" <?php checked(isset($key['wsdl_fix_ref_test']) ? $key['wsdl_fix_ref_test'] : '') ?> /> Fix Ref Test</label>
                        </div>
                        <div class="frow">
                            <input type="hidden" name="key[wsdl_dont_send_screening]" value="0" />
                            <label><input type="checkbox" name="key[wsdl_dont_send_screening]" value="1" <?php checked(isset($key['wsdl_dont_send_screening']) ? $key['wsdl_dont_send_screening'] : '') ?> /> Dont send Screening</label>
                        </div>
                    <?php } ?>

                    <div class="frow">
                        <label>Notes</label>
                        <textarea name="key[wsdl_desc]"><?php echo isset($key['wsdl_desc']) ? $key['wsdl_desc'] : '' ?></textarea>
                    </div>

                </div>
            </div>
            <div class="frow">
                <input type="submit" class="button-primary" value="Submit">
                <a class="test_btn button" data-type="test" href="#">Test</a>
                <?php if ($admin_view) {
                ?>
                    <a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => 'do_cron')) ?>">Do Cron</a>
                    <?php if (isset($_GET['debug'])) { ?>
                        <a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => 'resetall')) ?>">Reset sent</a>
                    <?php } ?>
                <?php
                } ?>
                <a class="test_btn button" id="test_btn_pdf" data-type="pdf" href="#">Test PDF</a>
                <?php if (!empty($id)) { ?>
                    <a class="button wsdl-log-history-btn" href="#TB_inline?width=1400&height=800&inlineId=wsdl-log-history">Log</a>
                <?php } ?>
            </div>
            <input type="hidden" name="key[wsdl_ID]" value="<?php echo isset($key['wsdl_ID']) ? $key['wsdl_ID'] : '' ?>" />
        </form>

        <div id="show_testing"></div>
    </div>

    <?php
    if (strpos($key['wsdl_type'], 'req_') !== false || strpos($key['wsdl_type'], 'result_') !== false) {
        $is_result = stripos($key['wsdl_type'], 'result_') !== false;

        $btype = str_replace(array('req_', 'result_'), '', $key['wsdl_type']);
        $batch_tbl = tr_pdd_get_table_by_btype($btype);
        $report_tbl = tr_pdd_get_table_report_by_btype($btype);

        $where = '';
        $cli_ids = $wpdb->get_col("select cli_ID from ci_wsdl_key_clinics where wsdl_ID = {$key['wsdl_ID']} ");
        if (count($cli_ids) > 0) {
            $cli_ids = implode(',', $cli_ids);
            $where .= " and ba.cli_ID in ({$cli_ids}) ";
        }

        if ($key['wsdl_lab_ID'] > 0) {
            $where .= " and ba.lab_id = {$key['wsdl_lab_ID']} ";
        }
        if ($key['wsdl_ref_lab_ID'] > 0) {
            $where .= " and ba.ref_lab_id = {$key['wsdl_ref_lab_ID']} ";
        }


        if (!empty($key['wsdl_group_IDs'])) {
            $where_group = array();
            foreach ($key['wsdl_group_IDs'] as $gid) {
                if ($gid > 0) {
                    $where_group[] = " FIND_IN_SET('GROUP_{$gid}', ba.panel_tests_requested ) ";
                }
            }
            if (!empty($where_group)) {
                $where .= ' and (' . implode(' or ', $where_group) . ' ) ';
            }
        }

        if (!empty($key['wsdl_start']) && $key['wsdl_start'] != '0000-00-00') {
            $where .= " and ba.batch_created >= '{$key['wsdl_start']}' ";
        }
        $joins = '';
        if ($is_result) {
            $joins .= " JOIN {$report_tbl} re ON re.patient_id = ba.assen and re.estatus = 'C' ";
            $where .= " and (re.re_import_ID = 0 or re.re_import_ID is null) "; //not send received
        } else {
            $where .= " and ba.paid != -57 "; //not by received by other
        }

        if ($key['wsdl_sync_confirmation']) {
            $where .= " and CHAR_LENGTH(ba.panel_tests_requested) > 1 ";
        }


        $left_sql = "select count(*) from {$batch_tbl} ba
                                    {$joins}
                                    left join ci_wsdl_batch wb ON ba.mid = wb.b_mid 
                                                        and wb.b_wsdl_ID = '{$key['wsdl_ID']}'
                                                       and wb.btype = '{$btype}'
                                    where ba.batch_status !='archived' 
                                    and ba.patient_id >0
                                    and ba.cli_ID > 0 
                                    and (wb.b_status is null or wb.b_status = 0 or wb.b_status = 2)
                                    {$where}";



        $done = $wpdb->get_var("select count(*) from ci_wsdl_batch 
                                            where b_wsdl_ID={$key['wsdl_ID']} 
                                            and btype = '{$btype}'
                                            and b_status in(1,2) ");

        $left = $wpdb->get_var($left_sql);

        $current_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;
        $nextcron_rs = 0;
        $logfile = '';
        if (strpos($key['wsdl_type'], 'result_') !== false) {
            $nextcron_rs = wp_next_scheduled('ci_cron_sync_wsdl_client_result');
            $nextcron2 = wp_next_scheduled('ci_cron_sync_wsdl_client_result', array(1));
            if ($nextcron2 > 0 && $nextcron2 < $nextcron_rs) {
                $nextcron_rs = $nextcron2;
            }
        } else if (strpos($key['wsdl_type'], 'req_') !== false) {
            $nextcron_rs = wp_next_scheduled('ci_cron_sync_wsdl_client_req');
            $nextcron2 = wp_next_scheduled('ci_cron_sync_wsdl_client_req', array(1));
            if ($nextcron2 > 0 && $nextcron2 < $nextcron_rs) {
                $nextcron_rs = $nextcron2;
            }
            $logfile = '/wp-content/log/cron_wsdl_put_req.txt';
        }


    ?>
        <div id="show_status">
            <ul>
                <li>Sent: <?php echo $done ?></li>
                <li>Left: <?php echo $left ?></li>
                <?php if (!empty($nextcron_rs) && isset($key['wsdl_active']) && $key['wsdl_active']) { ?>
                    <li>Next Cron: <?php echo date('Y-m-d H:i:s', $nextcron_rs + $current_offset) ?></li>
                <?php } ?>
                <?php if (!empty($logfile)) { ?>
                    <li><a target="_blank" href="<?php echo $logfile ?>">Log</a></li>
                <?php } ?>
            </ul>
        </div>

        <div id="wsdl-log-history" data-wsdl-id="<?php echo (int) $key['wsdl_ID'] ?>" style="display:none;">
            <?php echo trpdd_render_wsdl_client_log_history($key, $btype, $batch_tbl, $is_result); ?>
        </div>
    <?php

    }
    ?>
    </div>

    <script>
        var $doctors = <?php echo json_encode($doctors) ?>;
        var $selected_doc = <?php echo intval(isset($key['wsdl_df_doc_ID']) ? $key['wsdl_df_doc_ID'] : 0) ?>;
        jQuery(function($) {
            var $tb = window.parent && window.parent !== window && window.parent.jQuery ? window.parent.jQuery : $;

            function sync_client_ui(open_log) {
                var $view = $tb === $ ? $(window) : $tb(window.parent);
                var is_log = open_log === true;
                var single_column = $(window).width() <= 1100;
                var width = Math.max(is_log ? 1200 : 640, Math.round($view.width() * (is_log ? 0.96 : 0.9)));
                var height = Math.max(is_log ? 720 : 480, Math.round($view.height() * (is_log ? 0.94 : 0.9)));
                var $tb_window = $tb('#TB_window');
                var $tb_content = $tb('#TB_ajaxContent');

                $('.wsdl-client-editor').css({
                    maxWidth: 2400,
                    margin: 0
                });
                $('.wsdl-client-editor .frow').css('overflow', 'visible');
                $('.wsdl-client-editor textarea').css('minHeight', 110);
                $('.wsdl-client-editor .select2-container').css('width', '100%');
                $('.wsdl-client-editor .frow > .col2').css(single_column ? {
                    float: 'none',
                    width: '100%',
                    marginRight: 0
                } : {
                    float: '',
                    width: '',
                    marginRight: ''
                });

                if (is_log) {
                    tb_show('', '#TB_inline?width=' + width + '&height=' + height + '&inlineId=wsdl-log-history');
                    return setTimeout(sync_client_ui, 50);
                }

                if (!$tb_window.length || !$tb_content.length) {
                    return;
                }

                $tb_window.css({
                    width: width,
                    maxWidth: width,
                    height: height,
                    maxHeight: height
                });

                $tb_content.css({
                    width: '100%',
                    height: height - 45,
                    maxHeight: height - 45,
                    overflowY: 'auto'
                });
            }

            function save_ids_data($this) {
                var $pr = $this.parent();
                var $tr = $pr.parent();
                var $vl = $this.val();
                var $col = $this.data('k');
                var $id = $tr.data('id');

                $pr.append('<div class="sloading"></div>');
                $.ajax({
                    data: {
                        act: 'save_ids',
                        trajax: 1,
                        col: $col,
                        id: $id,
                        val: $vl
                    },
                    type: 'post',
                    dataType: 'json',
                    success: function(rs) {
                        if (rs.success) {

                        } else {
                            alert(rs.msg);
                        }
                        $('.sloading', $pr).remove();
                    }
                });
            }

            $('[name="key[wsdl_df_cli_ID]"]').change(function() {
                var $cli_id = $(this).val();
                var $html = '<option value="">Select Doctor</option>';
                for (i in $doctors) {
                    var $doctor = $doctors[i];
                    if ($doctor.cli_ID == $cli_id) {
                        var $selected = $doctor.doc_ID == $selected_doc ? 'selected' : '';
                        var $docnpi = $doctor.doc_npi ? '(' + $doctor.doc_npi + ')' : '';
                        $html += '<option value="' + $doctor.doc_ID + '" ' + $selected + '>' + $doctor.doc_first + ' ' + $doctor.doc_last + $docnpi + '</option>';
                    }
                }
                $('[name="key[wsdl_df_doc_ID]"]').html($html);


            }).change();


            $('#ma_ids_cb').change();

            $('.js_select2_tag').select2({
                tags: true
            });
            $('#wsdl_type').change(function() {
                if ($(this).val() == 'pdf') {
                    $('#test_btn_pdf').show();
                } else {
                    $('#test_btn_pdf').hide();
                }
                $('.showbytype').hide();
                $('.type_' + $(this).val()).show();
            }).change();

            $('a.test_btn').click(function() {
                $('#show_testing').html('loading...');
                $.ajax({
                    data: $('#form_wsdl').serialize() + '&trajax=test_client&typet=' + $(this).data('type'),
                    type: 'post',
                    dataType: 'json',
                    success: function(rs) {

                        $('#show_testing').html('<div>' + rs.msg + '</div>');
                        if (rs.status == 'ok') {
                            $('#show_testing').append(rs.show);
                        }
                    }
                });
                return false;
            });

            function load_wsdl_log_history(page, search) {
                var $box = $('#wsdl-log-history');
                var $target = $('#TB_ajaxContent .wsdl-log-history-content').length ? $('#TB_ajaxContent') : $box;
                var $content = $target.find('.wsdl-log-history-content');

                if ($content.length) {
                    $content.css('opacity', 0.55);
                    $content.find('.sloading').show();
                }

                $.ajax({
                    url: window.location.href,
                    type: 'get',
                    dataType: 'json',
                    data: {
                        trajax: 'wsdl_client_log_history',
                        id: $box.data('wsdl-id'),
                        logpaged: page || 1,
                        log_search: search || ''
                    },
                    success: function(rs) {
                        if (!rs.success) {
                            return alert(rs.msg);
                        }
                        $target.html(rs.html);
                        sync_client_ui();
                    },
                    error: function(xhr) {
                        alert('Load log failed: ' + (xhr.responseText || 'Unknown error'));
                    },
                    complete: function() {
                        $target.find('.wsdl-log-history-content').css('opacity', 1);
                    }
                });
            }

            $('.wsdl-log-history-btn').click(function() {
                sync_client_ui(true);
                return false;
            });

            $(document).on('submit', '#TB_ajaxContent .ci-wsdl-client-log-search, #wsdl-log-history .ci-wsdl-client-log-search', function(e) {
                e.preventDefault();
                load_wsdl_log_history(1, $(this).find('[name="log_search"]').val());
            });

            $(document).on('click', '#TB_ajaxContent .js-wsdl-log-page, #wsdl-log-history .js-wsdl-log-page', function(e) {
                e.preventDefault();
                if ($(this).hasClass('disabled')) {
                    return false;
                }
                load_wsdl_log_history($(this).data('page'), $(this).closest('.wsdl-log-history-content').find('[name="log_search"]').val());
            });

            $(document).on('click', '#TB_ajaxContent .js-wsdl-log-reset, #wsdl-log-history .js-wsdl-log-reset', function(e) {
                e.preventDefault();
                load_wsdl_log_history(1, '');
            });

            $(document).on('click', '.js-reset-log-status', function() {
                var $link = $(this);
                var old_text = $link.text();
                var $msg = $link.closest('td').find('.reset-log-status-msg');

                if (!$msg.length) {
                    $msg = $('<span class="reset-log-status-msg"></span>').insertAfter($link);
                }
                $msg.text(' Updating...');

                $.ajax({
                    url: window.location.href,
                    type: 'post',
                    dataType: 'json',
                    data: {
                        trajax: 'resetlogstatus',
                        batch_mid: $link.data('mid'),
                        batch_type: $link.data('btype'),
                        wsdl_id: $link.data('wsdl-id')
                    },
                    success: function(rs) {
                        if (!rs.success) {
                            $link.text(old_text);
                            $msg.text(' ' + rs.msg);
                            return alert(rs.msg);
                        }

                        $link.closest('tr').find('.log-send-status').text('Resend');
                        $msg.text(' ' + rs.msg);
                    },
                    error: function(xhr) {
                        $link.text(old_text);
                        $msg.text(' Update failed');
                        alert('Update failed: ' + (xhr.responseText || 'Unknown error'));
                    }
                });

                return false;
            });

            sync_client_ui();
            setTimeout(sync_client_ui, 150);
            setTimeout(sync_client_ui, 500);
            $(window).on('resize.wsdlClientThickbox', function() {
                sync_client_ui();
            });
        });
    </script>
<?php
} else {

    $row_per_page = 100;
    $pagination = new Tr_Pagination_class2($row_per_page);
    $pagination->setTotal($wpdb->get_var("select count(*) as c from ci_wsdl_keys k where 1  {$where}"));
    $offset = $pagination->offset();

    $wsdl_keyss = $wpdb->get_results("select k.*,
                                    group_concat(' ',cli.cli_clinic,' ') as cli_clinic,
                                    ug.gr_name,
                                    lab.lab_dba_init

                                    from ci_wsdl_keys k
                                    left JOIN ci_wsdl_key_clinics kc ON kc.wsdl_ID = k.wsdl_ID
                                    left JOIN ci_clinic cli ON cli.cID = kc.cli_ID
                                    left join ci_user_groups ug ON ug.gr_ID = k.wsdl_user_group_ID
                                    left join ci_labs lab ON lab.lab_ID = k.wsdl_lab_ID
                                    where 1 {$where} 
                                    group by k.wsdl_ID 
                                    limit {$offset},{$row_per_page}");

    //echo $wpdb->last_error;
?>
    <div class="wrap">
        <h2>Servers <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2">Add
                New</a></h2>
        <?php Tr_Session_Class::show() ?>

        <div class="wraptbl">
            <table class="wp-list-table widefat striped pages blue_tbl hover_tbl" style="table-layout: auto">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th style="width: 200px">Server</th>
                        <th style="width:200px">Key</th>
                        <th style="width: 20%">Clinic</th>
                        <th style="width:15%">Group</th>
                        <th style="width:80px">Lab</th>
                        <th>Notes</th>
                        <th>Status</th>
                        <th style="width:120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (isset($wsdl_keyss) && count($wsdl_keyss) > 0) {
                        foreach ($wsdl_keyss as $i => $row) {
                            $domaininfo = parse_url($row->wsdl_url);
                            $row->server = $domaininfo['host'];
                    ?>
                            <tr data-id="<?php echo $row->wsdl_ID ?>">
                                <td><?php echo $i + 1 ?></td>
                                <td><?php echo $row->server ?></td>
                                <td><?php echo $row->wsdl_key ?></td>
                                <td><?php echo $row->cli_clinic ?></td>
                                <td><?php echo $row->gr_name ?></td>
                                <td><?php echo $row->lab_dba_init ?></td>
                                <td><?php echo $row->wsdl_desc ?></td>
                                <td><?php echo $row->wsdl_active ? 'Enabled' : 'Disabled' ?></td>
                                <td>
                                    <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->wsdl_ID)) ?>">Edit</a>
                                    <?php if ($is_ci_admin && !$row->wsdl_active) { ?>
                                        - <a class="delete" href="<?php Tr_Base_Class_V6::link(array('act' => 'delete', 'id' => $row->wsdl_ID)) ?>">Delete</a>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php
                        }
                    } else if (isset($wsdl_keyss)) {
                        ?>
                        <tr>
                            <td colspan="7">Not found</td>
                        </tr>
                    <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
    </div>

    <script>
        jQuery(function($) {
            $('a.delete').click(function() {
                if (confirm('Do you want delete?') == false) return false;
            });
        })
    </script>
<?php
}
?>
