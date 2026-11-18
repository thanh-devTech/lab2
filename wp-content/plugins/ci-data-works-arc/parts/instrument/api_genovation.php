<?php

global $wpdb, $current_role;

if (!class_exists('CI_Api_Genovation')) {
    require_once(TRCPDD_PATH . 'api/ci_api_genovation.php');
}

$log_dir = WP_CONTENT_DIR . '/log/ci_api_genovation/';
$log_file           = WP_CONTENT_DIR . '/log/ci_api_genovation.txt';

wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);

// ─── Save settings ────────────────────────────────────────────────────────────

if (isset($_POST['options'])) {
    $options = $_POST['options'];
    if (empty($options['cron'])) {
        $options['cron'] = 10;
    }
    $old_options = get_option('api_genovation_st', array());
    trpdd_log_user(get_current_user_id(), 'api_genovation_st', ['old' => $old_options, 'new' => $options]);
    update_option('api_genovation_st', $options);
    Tr_Session_Class::add('Saved Settings Successfully');

    wp_clear_scheduled_hook('cron_all_api', array('CI_Api_Genovation'));
    if (!empty($options['enable'])) {
        wp_schedule_event(time() + 1, '10min', 'cron_all_api', array('CI_Api_Genovation'));
    }
    $options = get_option('api_genovation_st', array());
}

$options = get_option('api_genovation_st', array());
if (empty($options['cron'])) {
    $options['cron'] = 10;
}
$is_configured = false;
try {
    $client        = new CI_Api_Genovation();
    $client->debug = true;
    if (!empty($client->options['client_id']) && !empty($client->options['client_secret'])) {
        $webhook_info  = $client->get_webhook();
        $is_configured = ($webhook_info['isConfigured'] ?? false) || !empty(($webhook_info['subscriptions'] ?? []));
    }
} catch (Exception $e) {
    Tr_Session_Class::add_error($e->getMessage());
}

// ─── Actions ──────────────────────────────────────────────────────────────────

if ($act === 'auth') {
    $rs = $client->auth();
    var_dump($rs);
    exit;
} elseif ($act === 'send_orders') {
    try {
        $rs = $client->send_orders();
        var_dump($rs);
    } catch (Exception $e) {
        Tr_Session_Class::add_error($e->getMessage());
    }
    Tr_Session_Class::show();
    exit;
} elseif ($act === 'get_results') {
    try {
        $rs = $client->get_result_file();
        var_dump($rs);
    } catch (Exception $e) {
        Tr_Session_Class::add_error($e->getMessage());
    }
    Tr_Session_Class::show();
    exit;
} elseif ($act === 'resend') {
    try {
        $assen = sanitize_text_field($_REQUEST['assen'] ?? '');
        $rs    = $client->send_orders($assen);
        var_dump($rs);
    } catch (Exception $e) {
        var_dump($e);
    }
    exit;
} elseif ($act === 'download_again') {
    try {
        $assen = sanitize_text_field($_REQUEST['assen'] ?? '');
        $rs    = $client->get_result_file($assen);
        wp_send_json(['success' => true, 'data' => $rs]);
    } catch (Exception $e) {
        wp_send_json_error(['error' => $e->getMessage()]);
    }
    exit;
} elseif ($act === 'register_webhook') {
    try {
        $rs = $client->register_webhook();
        var_dump($rs);
    } catch (Exception $e) {
        Tr_Session_Class::add_error($e->getMessage());
    }
    Tr_Session_Class::show();
    exit;
} elseif ($act === 'get_webhook') {
    $webhook_list  = $client->get_webhook();
    $subscriptions = $webhook_list['subscriptions'] ?? [];
?>
    <h3>Registered Webhooks</h3>
    <?php if (empty($subscriptions)): ?>
        <p>No subscriptions found.</p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:80px">ID</th>
                    <th>URL</th>
                    <th style="width:80px">Has Secret</th>
                    <th style="width:140px">Created</th>
                    <th style="width:80px">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subscriptions as $sub): ?>
                    <tr>
                        <td><?php echo esc_html($sub['webhookSubscriptionId']) ?></td>
                        <td style="word-break:break-all;font-size:12px"><?php echo esc_html($sub['url']) ?></td>
                        <td><?php echo $sub['hasSecret'] ? '✓' : '—' ?></td>
                        <td><?php echo esc_html($sub['createdAt']) ?></td>
                        <td>
                            <a class="action_ button button-small" href="#"
                                data-sub-id="<?php echo esc_attr($sub['webhookSubscriptionId']) ?>"
                                data-action="delete_webhook_ajax">
                                Delete
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <details style="margin-top:16px">
        <summary style="cursor:pointer;color:#666">Raw JSON</summary>
        <pre style="background:#f6f7f7;padding:12px;font-size:11px;overflow:auto">
            <?php
            echo esc_html(wp_json_encode($webhook_list, JSON_PRETTY_PRINT));
            ?></pre>
    </details>
<?php
    exit;
} elseif ($act === 'delete_webhook_ajax') {
    $sub_id = intval($_REQUEST['sub_id'] ?? 0);
    if (empty($sub_id)) {
        wp_send_json_error(['error' => 'Missing subscription ID']);
    }

    $rs = $client->delete_webhook($sub_id);
    $is_error = is_wp_error($rs) || (!empty($rs['_status']) && $rs['_status'] >= 400);

    if ($is_error) {
        $err_detail = is_wp_error($rs)
            ? $rs->get_error_message()
            : 'HTTP ' . ($rs['_status'] ?? 'unknown') . ' — server error (likely Genovation-side issue)';
        wp_send_json_error(['error' => $err_detail]);
    }

    wp_send_json_success(['message' => "Deleted subscription #{$sub_id}"]);
    exit;
} elseif ($act === 'delete_webhook') {
    $webhook_list = $client->get_webhook();
    $subscriptions = $webhook_list['subscriptions'] ?? [];
?>
    <h3>Delete Webhook Subscription</h3>
    <?php if (empty($subscriptions)): ?>
        <p>No subscriptions found.</p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped" style="margin-bottom:16px">
            <thead>
                <tr>
                    <th style="width:80px">ID</th>
                    <th>URL</th>
                    <th style="width:80px">Has Secret</th>
                    <th style="width:140px">Created</th>
                    <th style="width:80px">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subscriptions as $sub): ?>
                    <tr>
                        <td><?php echo esc_html($sub['webhookSubscriptionId']) ?></td>
                        <td style="word-break:break-all;font-size:12px"><?php echo esc_html($sub['url']) ?></td>
                        <td><?php echo $sub['hasSecret'] ? '✓' : '—' ?></td>
                        <td><?php echo esc_html($sub['createdAt']) ?></td>
                        <td>
                            <a class="action_ button button-small" href="#"
                                data-sub-id="<?php echo esc_attr($sub['webhookSubscriptionId']) ?>"
                                data-action="delete_webhook_ajax">
                                Delete
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <hr>
    <p><strong>Or enter ID manually:</strong></p>
    <form method="get">
        <?php
        // Preserve existing query params
        foreach ($_GET as $k => $v) {
            if ($k === 'sub_id') continue;
            echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr($v) . '">';
        }
        ?>
        <input type="number" name="sub_id" placeholder="Subscription ID" style="width:160px" required />
        <button type="submit" class="button" style="color:#b32d2e"
            onclick="return confirm('Are you sure?')">Delete</button>
    </form>
<?php
    exit;
} elseif ($act === 'check_files') {
    global $wpdb;
    $btype     = 'gene';
    $batch_tbl = tr_pdd_get_table_by_btype($btype);
    $report_tbl = tr_pdd_get_table_report_by_btype($btype);

    $where = "
        WHERE 1
        AND (api.mo_ID IS NULL OR api.mo_sent = 0 OR api.mo_sent = 2)
        AND ba.batch_id > 0 AND ba.patient_id > 0
        AND (re.estatus != 'C' OR re.estatus IS NULL)
        AND EXISTS (
            SELECT 1 FROM ci_reports_files rf
            WHERE rf.rf_btype = '{$btype}'
            AND (rf.rf_file LIKE '%.rpt%' OR rf.rf_file LIKE '%.vcf%')
            AND FIND_IN_SET(ba.assen, rf.rf_assen_list)
        )
    ";

    $row_per_page = 50;
    $pagination   = new Tr_Pagination_class2($row_per_page);
    $pagination->setTotal($wpdb->get_var("
        SELECT COUNT(*)
        FROM {$batch_tbl} ba
        LEFT JOIN {$report_tbl} re ON re.patient_id = ba.assen
        LEFT JOIN ci_sync_api_orders api ON api.mo_mid = ba.mid
                                        AND api.mo_file_id = '{$client->api_id}'
                                        AND api.mo_btype = '{$btype}'
        JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
        JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
        JOIN ci_doc doc ON doc.doc_ID = ba.doc_ID
        JOIN ci_clinic cli ON cli.cID = ba.cli_ID
        {$where}
    "));
    $offset = $pagination->offset();

    $rows = $wpdb->get_results("
        SELECT ba.mid, ba.assen, ba.patient_name,
            api.mo_ID, api.mo_sent
        FROM {$batch_tbl} ba
        LEFT JOIN {$report_tbl} re ON re.patient_id = ba.assen
        LEFT JOIN ci_sync_api_orders api ON api.mo_mid = ba.mid
                                        AND api.mo_file_id = '{$client->api_id}'
                                        AND api.mo_btype = '{$btype}'
        JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
        JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
        JOIN ci_doc doc ON doc.doc_ID = ba.doc_ID
        JOIN ci_clinic cli ON cli.cID = ba.cli_ID
        {$where}
        ORDER BY ba.mid DESC
        LIMIT {$offset},{$row_per_page}
    ");

    $base_dir = WP_CONTENT_DIR . '/log/results_gene/';
?>
    <h3>File check — pending assens</h3>
    <div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width:50px">MID</th>
                <th style="width:120px">Accession</th>
                <th>Patient</th>
                <th style="width:80px">mo_sent</th>
                <th style="width:200px">RPT file</th>
                <th style="width:200px">VCF file</th>
                <th style="width:80px">Can send?</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row):
                $files     = $client->get_instrument_files([$row->assen], true);
                $rpt_size  = $files['rpt']['size'] ?? null;
                $vcf_size  = $files['vcf']['size'] ?? null;
                $can_send  = $rpt_size !== null || $vcf_size !== null;

                $assen_conditions = $wpdb->prepare("FIND_IN_SET(%s, rf_assen_list)", $row->assen);
                $db_files = $wpdb->get_results("
                SELECT rf_file, rf_created
                FROM ci_reports_files
                WHERE rf_btype = '{$btype}' AND ({$assen_conditions})
                ORDER BY rf_created DESC
            ");

                $rpt_info = $vcf_info = '—';
                foreach ($db_files as $f) {
                    $lower     = strtolower($f->rf_file);
                    $full_path = $base_dir . $f->rf_file;
                    $exists    = file_exists($full_path);
                    $disk_size = $exists ? filesize($full_path) : null;
                    $label     = $f->rf_file . '<br><small style="color:#888">'
                        . ($exists ? number_format($disk_size) . ' bytes' : '<span style="color:red">file missing</span>')
                        . '</small>';

                    if (str_contains($lower, '.rpt') && $rpt_info === '—') {
                        $rpt_info = $label;
                    } elseif (str_contains($lower, '.vcf') && $vcf_info === '—') {
                        $vcf_info = $label;
                    }
                }
            ?>
                <tr>
                    <td><?php echo esc_html($row->mid) ?></td>
                    <td><?php echo esc_html($row->assen) ?></td>
                    <td><?php echo esc_html($row->patient_name) ?></td>
                    <td><?php echo $row->mo_sent !== null ? esc_html($row->mo_sent) : '<span style="color:#999">NULL</span>' ?></td>
                    <td style="font-size:12px"><?php echo $rpt_info ?></td>
                    <td style="font-size:12px"><?php echo $vcf_info ?></td>
                    <td style="text-align:center">
                        <?php if ($can_send): ?>
                            <span style="color:green;font-weight:bold">✓</span>
                        <?php else: ?>
                            <span style="color:red">✗</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
<?php
    exit;
} elseif ($act === 'log') {

    $btype      = sanitize_text_field($_REQUEST['btype'] ?? 'gene');
    $batch_tbl  = tr_pdd_get_table_by_btype($btype);
    $report_tbl = tr_pdd_get_table_report_by_btype($btype);

    $where = $wpdb->prepare("o.mo_file_id = %d", $client->api_id);

    if (!empty($_REQUEST['s'])) {
        $s = sanitize_text_field($_REQUEST['s']);
        $where .= $wpdb->prepare(
            " AND (ba.assen LIKE %s OR ba.patient_name LIKE %s OR o.mo_patient_ID = %s)",
            $s . '%',
            '%' . $s . '%',
            $s
        );
    }

    $row_per_page = 200;
    $pagination   = new Tr_Pagination_class2($row_per_page);
    $pagination->setTotal($wpdb->get_var("
        SELECT COUNT(*) FROM {$batch_tbl} ba
        LEFT JOIN {$report_tbl} re ON re.patient_id = ba.assen
        JOIN ci_sync_api_orders o ON o.mo_mid = ba.mid AND o.mo_btype = '{$btype}'
        WHERE {$where}
    "));
    $offset = $pagination->offset();

    $rows = $wpdb->get_results("
        SELECT ba.mid, ba.assen, ba.patient_name, ba.requesting_practice,
               ba.requesting_physician, ba.doc, ba.spec_received, ba.batch_created,
               re.id, re.estatus,
               o.mo_ID, o.mo_status, o.mo_peg_ID, o.mo_sent_dt, o.other_info, o.mo_sent
        FROM {$batch_tbl} ba
        LEFT JOIN {$report_tbl} re ON re.patient_id = ba.assen
        JOIN ci_sync_api_orders o ON o.mo_mid = ba.mid AND o.mo_btype = '{$btype}'
        WHERE {$where}
        ORDER BY mo_ID DESC
        LIMIT {$offset},{$row_per_page}
    ");
    echo $wpdb->last_error;
?>
    <div>
        <input type="search" id="assen_search" value="<?php echo esc_attr($_REQUEST['s'] ?? '') ?>"
            placeholder="Accession, patient name, or patientGuid">
        <a class="button seach_assen" href="<?php Tr_Base_Class_V6::link(['s' => '']) ?>">Search</a>
    </div>
    <div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>

    <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
        <thead>
            <tr>
                <th style="width:30px">#</th>
                <th style="width:100px">Accession</th>
                <th style="width:220px">patientGuid</th>
                <th>Patient</th>
                <th>Clinic</th>
                <th>Doctor</th>
                <th style="width:60px">Resulted</th>
                <th style="width:120px">Sent</th>
                <th style="width:120px">Received</th>
                <th style="width:120px">Actions</th>
                <th style="width:120px">Logs</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row) :
                $patient_guid  = $row->mo_peg_ID ?? '';
                $other_info    = !empty($row->other_info) ? json_decode($row->other_info, true) : [];
                $sent_info     = $other_info['sent'] ?? [];
                $received_info = $other_info['received'] ?? [];

                $order_file    = sanitize_file_name('order_request_' . $row->assen . '.json');
                $order_path    = $log_dir . $order_file;
                $dl_pgx_file   = sanitize_file_name('download_pgx_' . $patient_guid . '.json');
                $dl_pgx_path   = $log_dir . $dl_pgx_file;
                $wh_file       = sanitize_file_name('webhook_' . $patient_guid . '.json');
                $wh_path       = $log_dir . $wh_file;
            ?>
                <tr>
                    <td><?php echo esc_html($row->mid) ?></td>
                    <td>
                        <a target="_blank"
                            href="?page=ci_batches_view&act=edit&id=<?php echo esc_attr($btype . '_' . $row->mid) ?>">
                            <?php echo esc_html($row->assen) ?>
                        </a>
                    </td>
                    <td style="font-size:11px;word-break:break-all"><?php echo esc_html($patient_guid) ?></td>
                    <td><?php echo esc_html($row->patient_name) ?></td>
                    <td><?php echo esc_html($row->requesting_practice) ?></td>
                    <td><?php echo esc_html($row->requesting_physician) ?></td>
                    <td>
                        <a href="?page=<?php echo  $btype . '_results' ?>&act=edit&id=<?php echo $row->id ?>" target="_blank">link</a>
                    </td>
                    <td>
                        <?php if (empty($sent_info['error'])): ?>
                            <span style="color:green">✓ Sent</span>
                        <?php else: ?>
                            <span style="color:#b32d2e">✗ Failed</span>
                        <?php endif; ?>
                        <?php $sent_time = $sent_info['time'] ?? $row->mo_sent_dt; ?>
                        <?php if (!empty($sent_time)): ?>
                            <br><small><?php echo esc_html($sent_time) ?></small>
                        <?php endif; ?>
                        <?php if (!empty($sent_info['error'])): ?>
                            <br><small style="color:#b32d2e" title="<?php echo esc_attr($sent_info['error']) ?>">
                                <?php echo esc_html(wp_trim_words($sent_info['error'], 8)) ?>
                            </small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($received_info)): ?>
                            <?php if (empty($received_info['error'])): ?>
                                <span style="color:green">✓ <?php echo esc_html($received_info['status'] ?? 'Received') ?></span>
                            <?php else: ?>
                                <span style="color:#b32d2e">✗ <?php echo esc_html($received_info['status'] ?? 'Error') ?></span>
                            <?php endif; ?>
                            <br><small><?php echo esc_html($received_info['time'] ?? '') ?></small>
                            <?php if (!empty($received_info['error'])): ?>
                                <br><small style="color:#b32d2e" title="<?php echo esc_attr($received_info['error']) ?>">
                                    <?php echo esc_html(wp_trim_words($received_info['error'], 8)) ?>
                                </small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color:#999">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a style="display:block" target="_blank"
                            href="<?php echo Tr_Base_Class_V6::link(['trajax' => 1, 'act' => 'resend', 'assen' => $row->assen], false) ?>">
                            Resend
                        </a>
                        <?php if (!empty($patient_guid)): ?>
                            <a style="display:block" class="log_action_" href="#"
                                data-assen="<?php echo esc_attr($row->assen) ?>"
                                data-action="download_again">
                                Download again
                            </a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $links = [];
                        if (file_exists($order_path)) {
                            $links[] = '<a target="_blank" href="' . Tr_Base_Class_V4::link(['trajax' => 1, 'act' => 'viewrshl7', 'file' => $order_file], false) . '">Order</a>';
                        }
                        if (file_exists($dl_pgx_path)) {
                            $links[] = '<a target="_blank" href="' . Tr_Base_Class_V4::link(['trajax' => 1, 'act' => 'viewrshl7', 'file' => $dl_pgx_file], false) . '">DL Log</a>';
                        }
                        if (file_exists($wh_path)) {
                            $links[] = '<a target="_blank" href="' . Tr_Base_Class_V4::link(['trajax' => 1, 'act' => 'viewrshl7', 'file' => $wh_file], false) . '">Webhook</a>';
                        }
                        echo implode(' | ', $links);
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>

    <script>
        jQuery(function($) {
            $('body').off('click', '.log_action_').on('click', '.log_action_', function(e) {
                e.preventDefault();
                var $this = $(this);
                var $pr = $this.parent();
                var $assen = $this.data('assen');
                var $action = $this.data('action');

                $pr.append('<span class="sloading"></span>');
                $.ajax({
                    data: {
                        act: $action,
                        trajax: 1,
                        assen: $assen
                    },
                    type: 'post',
                    dataType: 'json',
                    success: function(rs) {
                        if (!rs.success) alert(rs.data?.error || 'Error');
                        else alert('Done');
                    },
                    error: function(err) {
                        console.error('Error:', err);
                    },
                    complete: function() {
                        $pr.find('.sloading').remove();
                    }
                });
            });
        });
    </script>
<?php
    exit;
} elseif ($act === 'viewrshl7') {
    $file_name = sanitize_file_name($_REQUEST['file'] ?? '');
    $safe_path = $log_dir . $file_name;

    if ($safe_path && file_exists($safe_path)) {
        header('Content-Type: application/json');
        echo file_get_contents($safe_path);
    } else {
        echo 'File not found!';
    }
    exit;
}

// ─── Settings form ────────────────────────────────────────────────────────────

$key      = $wpdb->get_row("SELECT wsdl_key FROM ci_wsdl_keys WHERE wsdl_type LIKE 'json_onco'");
$site_url = get_site_url();

// Auto-build webhook URL if empty
if (empty($options['webhook_url'])) {
    $options['webhook_url'] = $site_url . '/genovation-callback';
}
?>

<div class="wrap">
    <h2>Genovation Settings</h2>
    <?php Tr_Session_Class::show() ?>

    <form method="post">
        <div class="row" style="display:flex;gap:24px">
            <div style="flex:1">
                <div class="row">
                    <label>Enable Cron</label>
                    <input type="checkbox" value="1" name="options[enable]"
                        <?php checked($options['enable'] ?? 0) ?> />
                </div>
                <div class="row">
                    <label>Client ID</label>
                    <input type="text" name="options[client_id]"
                        value="<?php echo esc_attr($options['client_id'] ?? '') ?>" required />
                </div>
                <div class="row">
                    <label>Client Secret</label>
                    <input type="text" name="options[client_secret]"
                        value="<?php echo esc_attr($options['client_secret'] ?? '') ?>" required />
                </div>
                <div class="row">
                    <label>Webhook URL</label>
                    <input type="text" name="options[webhook_url]"
                        value="<?php echo esc_attr($options['webhook_url'] ?? '') ?>" style="width:100%" />
                </div>
                <div class="row">
                    <label>Webhook Secret</label>
                    <input type="text" name="options[webhook_secret]"
                        value="<?php echo esc_attr($options['webhook_secret'] ?? '') ?>" />
                </div>
                <div class="row">
                    <label>Schedule</label>
                    <select name="options[cron]">
                        <?php foreach ([2, 5, 10, 30] as $m): ?>
                            <option value="<?php echo $m ?>"
                                <?php selected($m, $options['cron'] ?? 10) ?>>
                                each <?php echo $m ?> min(s)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="flex:1">
                <div class="frow">
                    <label>Notes</label>
                    <textarea name="options[notes]" style="height:260px;width:100%"><?php
                                                                                    echo esc_textarea($options['notes'] ?? '') ?></textarea>
                </div>
                <?php if (!empty($options['access_token'])): ?>
                    <div class="row" style="background:#f6f7f7;padding:8px;border-radius:4px;margin-top:8px">
                        <strong>Token status:</strong>
                        <?php
                        $expires = $options['token_expires'] ?? '';
                        if (!empty($expires)):
                            $exp_ts  = strtotime($expires);
                            $expired = $exp_ts <= time();
                        ?>
                            <span style="color:<?php echo $expired ? '#b32d2e' : 'green' ?>">
                                <?php echo $expired ? '✗ Expired' : '✓ Valid' ?>
                            </span>
                            — expires <?php echo esc_html(date_i18n('Y-m-d H:i:s', $exp_ts)) ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="frow row" style="margin-top:16px">
            <input type="submit" value="Save" class="button-primary" /> &nbsp;

            <a class="button thickbox"
                href="<?php Tr_Base_Class_V6::link(['trajax' => 1, 'act' => 'auth']) ?>&width=800">
                Auth / Refresh Token
            </a> &nbsp;

            <a class="button thickbox"
                href="<?php Tr_Base_Class_V6::link(['trajax' => 1, 'act' => 'send_orders']) ?>&width=1000">
                Test Send Orders
            </a> &nbsp;

            <?php if ($is_configured): ?>
                <a class="button thickbox"
                    href="<?php Tr_Base_Class_V6::link(['trajax' => 1, 'act' => 'delete_webhook']) ?>&width=1200"
                    onclick="return confirm('Are you sure you want to delete the webhook?')">
                    Delete Webhook
                </a>
            <?php endif; ?>
            <a class="button thickbox"
                href="<?php Tr_Base_Class_V6::link(['trajax' => 1, 'act' => 'register_webhook']) ?>&width=800">
                Register Webhook
            </a> &nbsp;

            <a class="button thickbox"
                href="<?php Tr_Base_Class_V6::link(['trajax' => 1, 'act' => 'get_webhook']) ?>&width=1200">
                View Webhook
            </a> &nbsp;

            <a class="button thickbox"
                href="<?php Tr_Base_Class_V6::link(['trajax' => 1, 'act' => 'get_results']) ?>&width=1000">
                Test Download Reports
            </a> &nbsp;

            <a class="button thickbox"
                href="<?php Tr_Base_Class_V6::link(['trajax' => 1, 'act' => 'log']) ?>&width=1400&height=700">
                Log
            </a>
            &nbsp;
            <?php if (isset($_GET['debug'])): ?>
                <a class="button thickbox"
                    href="<?php Tr_Base_Class_V6::link(['trajax' => 1, 'act' => 'check_files']) ?>&width=1200&height=700">
                    Check Files
                </a> &nbsp;
            <?php endif; ?>
            <a class="button" target="_blank" href="<?php echo '/wp-content/log/ci_api_genovation.txt' ?>">Log File</a> &nbsp;&nbsp;

            <br><br>
            <?php
            $count = $wpdb->get_row($wpdb->prepare("
                SELECT COUNT(*) as count_reqs,
                       SUM(IF(o.mo_sent > 0, 1, 0))   as count_sent,
                       SUM(IF(o.mo_status = 1, 1, 0))  as count_received
                FROM ci_batches_gene ba
                JOIN ci_sync_api_orders o ON o.mo_mid = ba.mid
                WHERE o.mo_file_id = %d
            ", $client->api_id));

            echo 'Reqs: '      . ($count->count_reqs      ?? 0) . '<br>';
            echo 'Sent: '      . ($count->count_sent      ?? 0) . '<br>';
            echo 'Received: '  . ($count->count_received  ?? 0) . '<br>';
            echo 'Now: '       . date_i18n('Y-m-d H:i:s')      . '<br>';

            $next_cron      = wp_next_scheduled('cron_all_api', ['CI_Api_Genovation']);
            $current_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;

            $last_cron = file_exists($log_file) ? filemtime($log_file) : 0;

            if ($last_cron > 0) {
                echo 'Last Cron: ' . date_i18n('Y-m-d H:i:s', $last_cron + $current_offset) . '<br>';
            }
            if ($next_cron) {
                echo 'Next Cron: ' . date_i18n('Y-m-d H:i:s', $next_cron + $current_offset) . '<br>';
            }
            ?>
        </div>
    </form>
</div>

<script>
    jQuery(function($) {
        $('body').on('click', '.action_', function(e) {
            e.preventDefault();
            var $this = $(this);
            var $pr = $this.parent();
            var $assen = $this.data('assen');
            var $subId = $this.data('sub-id');
            var $action = $this.data('action');

            if ($action === 'delete_webhook_ajax' &&
                !confirm('Delete subscription #' + $subId + '?')) {
                return;
            }

            $pr.append('<span class="sloading"></span>');
            $.ajax({
                data: {
                    act: $action,
                    trajax: 1,
                    assen: $assen,
                    sub_id: $subId
                },
                dataType: 'json',
                success: function(rs) {
                    if (!rs.success) {
                        alert('✗ ' + (rs.data?.error || 'Error'));
                    } else {
                        alert('✓ ' + (rs.data?.message || 'Done'));
                        $this.closest('tr').fadeOut();
                    }
                },
                error: function(err) {
                    alert('✗ Request failed');
                    console.error('Error:', err);
                },
                complete: function() {
                    $pr.find('.sloading').remove();
                }
            });
        });
    });
</script>

<style>
    #edittbl input.editfield {
        width: 100%;
    }

    .row label {
        display: inline-block;
        min-width: 160px;
        font-weight: 600;
    }

    .row {
        margin-bottom: 10px;
    }
</style>