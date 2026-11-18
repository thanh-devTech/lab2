<?php

global $wpdb, $current_role;

if (!class_exists('CI_Api_Tebra')) {
    require_once(TRCPDD_PATH . 'api/ci_api_tebra.php');
}

$log_dir = WP_CONTENT_DIR . '/log/ci_api_tebra/';

wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);

// ─── Save settings ────────────────────────────────────────────────────────────

if (isset($_POST['options'])) {
    $options = $_POST['options'];
    if (empty($options['cron'])) {
        $options['cron'] = 10;
    }
    $old_options = get_option('api_tebra_st', array());
    trpdd_log_user(get_current_user_id(), 'api_tebra_st', ['old' => $old_options, 'new' => $options]);
    update_option('api_tebra_st', $options);
    Tr_Session_Class::add('Saved Settings Successfully');

    wp_clear_scheduled_hook('cron_all_api', array('CI_Api_Tebra'));
    if (!empty($options['enable'])) {
        wp_schedule_event(time() + 1, '10min', 'cron_all_api', array('CI_Api_Tebra'));
    }
    $options = get_option('api_tebra_st', array());
}

$options = get_option('api_tebra_st', array());
if (empty($options['cron'])) {
    $options['cron'] = 10;
}

$client        = new CI_Api_Tebra();
$client->debug = true;

// ─── Actions ──────────────────────────────────────────────────────────────────

if ($act === 'test_connection') {
    try {
        $soap = new SoapClient($client->wsdl_url, array('trace' => true, 'exceptions' => true, 'cache_wsdl' => WSDL_CACHE_DISK));
        $rs   = $soap->GetCustomerIdFromKey(array('request' => array(
            'CustomerKey' => $options['customer_key'] ?? '',
            'User'        => $options['api_user'] ?? '',
            'Password'    => $options['api_password'] ?? '',
        )));
        var_dump($rs);
    } catch (Exception $e) {
        Tr_Session_Class::add_error($e->getMessage());
    }
    Tr_Session_Class::show();
    exit;
} elseif ($act === 'run_cron') {
    try {
        $rs = $client->cron();
        var_dump($rs);
    } catch (Exception $e) {
        Tr_Session_Class::add_error($e->getMessage());
    }
    Tr_Session_Class::show();
    exit;
} elseif ($act === 'resend_patient') {
    try {
        $mid = intval($_REQUEST['mid'] ?? 0);
        $rs  = $client->resend_patient($mid);
        wp_send_json(['success' => !empty($rs['success']), 'data' => $rs]);
    } catch (Exception $e) {
        wp_send_json_error(['error' => $e->getMessage()]);
    }
    exit;
} elseif ($act === 'resend_encounter') {
    try {
        $mid = intval($_REQUEST['mid'] ?? 0);
        $rs  = $client->resend_encounter($mid);
        wp_send_json(['success' => !empty($rs['success']), 'data' => $rs]);
    } catch (Exception $e) {
        wp_send_json_error(['error' => $e->getMessage()]);
    }
    exit;
} elseif ($act === 'log') {

    $btype      = 'pcr'; // pcr only
    $batch_tbl  = tr_pdd_get_table_by_btype($btype);
    $report_tbl = tr_pdd_get_table_report_by_btype($btype);

    $where = '1=1';

    if (!empty($_REQUEST['s'])) {
        $s = sanitize_text_field($_REQUEST['s']);
        $where .= $wpdb->prepare(
            " AND (ba.assen LIKE %s OR ba.patient_name LIKE %s)",
            $s . '%',
            '%' . $s . '%'
        );
    }

    $row_per_page = 200;
    $pagination   = new Tr_Pagination_class2($row_per_page);
    $pagination->setTotal($wpdb->get_var("
        SELECT COUNT(*) FROM {$batch_tbl} ba
        LEFT JOIN {$report_tbl} re ON re.patient_id = ba.assen
        WHERE ba.batch_id > 0 AND ba.patient_id > 0 AND {$where}
    "));
    $offset = $pagination->offset();

    $rows = $wpdb->get_results("
        SELECT ba.mid, ba.assen, ba.patient_name, ba.requesting_practice,
               ba.requesting_physician, ba.dagnostic_codes1, ba.batch_created,
               re.id as report_id, re.estatus,
               mp.meta_value as tebra_patient_id,
               ms.meta_value as tebra_status,
               me.meta_value as tebra_encounter_id,
               merr.meta_value as tebra_error,
               mpd.meta_value as patient_sent_dt,
               med.meta_value as encounter_sent_dt
        FROM {$batch_tbl} ba
        LEFT JOIN {$report_tbl} re ON re.patient_id = ba.assen
        LEFT JOIN {$batch_tbl}_meta mp   ON mp.mid = ba.mid AND mp.meta_key = '_tebra_patient_id'
        LEFT JOIN {$batch_tbl}_meta ms   ON ms.mid = ba.mid AND ms.meta_key = '_tebra_status'
        LEFT JOIN {$batch_tbl}_meta me   ON me.mid = ba.mid AND me.meta_key = '_tebra_encounter_id'
        LEFT JOIN {$batch_tbl}_meta merr ON merr.mid = ba.mid AND merr.meta_key = '_tebra_error'
        LEFT JOIN {$batch_tbl}_meta mpd  ON mpd.mid = ba.mid AND mpd.meta_key = '_tebra_patient_sent_dt'
        LEFT JOIN {$batch_tbl}_meta med  ON med.mid = ba.mid AND med.meta_key = '_tebra_encounter_sent_dt'
        WHERE ba.batch_id > 0 AND ba.patient_id > 0 AND {$where}
        ORDER BY ba.mid DESC
        LIMIT {$offset},{$row_per_page}
    ");
    echo $wpdb->last_error;
    if (isset($_GET['debug'])) {
        echo $wpdb->last_query;
    }
?>
    <div>
        <input type="search" id="assen_search" value="<?php echo esc_attr($_REQUEST['s'] ?? '') ?>"
            placeholder="Accession or patient name">
        <a class="button seach_assen" href="<?php Tr_Base_Class_V6::link(['s' => '']) ?>">Search</a>
    </div>
    <div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>

    <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
        <thead>
            <tr>
                <th style="width:60px">#</th>
                <th style="width:100px">Accession</th>
                <th>Patient</th>
                <th>Clinic</th>
                <th style="width:60px">Approved</th>
                <th style="width:120px">Tebra Patient ID</th>
                <th style="width:120px">Tebra Encounter ID</th>
                <th style="width:130px">Status</th>
                <th>Last Error</th>
                <th style="width:110px">Actions</th>
                <th style="width:110px">Logs</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row) : ?>
                <tr>
                    <td><?php echo esc_html($row->mid) ?></td>
                    <td>
                        <a target="_blank"
                            href="?page=ci_batches_view&act=edit&id=<?php echo esc_attr('pcr_' . $row->mid) ?>">
                            <?php echo esc_html($row->assen) ?>
                        </a>
                    </td>
                    <td><?php echo esc_html($row->patient_name) ?></td>
                    <td><?php echo esc_html($row->requesting_practice) ?></td>
                    <td>
                        <a href="?page=ci_results_pcr&act=edit&id=<?php echo $row->report_id ?>" target="_blank">
                            <?php echo $row->estatus === 'C' ? '✓' : '' ?>
                        </a>
                    </td>
                    <td style="font-size:11px;word-break:break-all"><?php echo esc_html($row->tebra_patient_id) ?></td>
                    <td style="font-size:11px;word-break:break-all"><?php echo esc_html($row->tebra_encounter_id) ?></td>
                    <td>
                        <?php
                        $status = $row->tebra_status ?? '';
                        $color  = match (true) {
                            str_contains($status, 'failed') => '#b32d2e',
                            str_contains($status, 'sent')   => 'green',
                            default => '#999',
                        };
                        ?>
                        <span style="color:<?php echo $color ?>"><?php echo esc_html($status ?: '—') ?></span>
                        <?php if ($row->patient_sent_dt): ?>
                            <br><small>P: <?php echo esc_html($row->patient_sent_dt) ?></small>
                        <?php endif; ?>
                        <?php if ($row->encounter_sent_dt): ?>
                            <br><small>E: <?php echo esc_html($row->encounter_sent_dt) ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:11px;color:#b32d2e"><?php echo esc_html($row->tebra_error) ?></td>
                    <td>
                        <a style="display:block" class="action_" href="#"
                            data-mid="<?php echo esc_attr($row->mid) ?>"
                            data-action="resend_patient">
                            Resend Patient
                        </a>
                        <?php if (!empty($row->tebra_patient_id) && $row->estatus === 'C'): ?>
                            <a style="display:block" class="action_" href="#"
                                data-mid="<?php echo esc_attr($row->mid) ?>"
                                data-action="resend_encounter">
                                Resend Encounter
                            </a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a target="_blank"
                            href="<?php echo Tr_Base_Class_V4::link(['trajax' => 1, 'act' => 'view_request_log', 'identifier' => $row->assen], false) ?>">
                            View Logs
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>

    <script>
        jQuery(function($) {
            $('body').off('click', '.action_').on('click', '.action_', function(e) {
                e.preventDefault();
                var $this = $(this);
                var $pr = $this.parent();
                var $mid = $this.data('mid');
                var $action = $this.data('action');

                $pr.append('<span class="sloading"></span>');
                $.ajax({
                    data: {
                        act: $action,
                        trajax: 1,
                        mid: $mid
                    },
                    type: 'post',
                    dataType: 'json',
                    success: function(rs) {
                        if (!rs.success) alert('✗ ' + (rs.data?.error || 'Error'));
                        else alert('✓ Done');
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
<?php
    exit;
} elseif ($act === 'get_practices') {
    try {
        $soap = new SoapClient($client->wsdl_url, array('trace' => true, 'exceptions' => true, 'cache_wsdl' => WSDL_CACHE_DISK));
        $rs = $soap->GetPractices(array('request' => array(
            'RequestHeader' => array(
                'CustomerKey' => $options['customer_key'] ?? '',
                'User'        => $options['api_user'] ?? '',
                'Password'    => $options['api_password'] ?? '',
            ),
            'Filter' => new stdClass(),
            'Fields' => array(
                'PracticeID'   => true,
                'PracticeName' => true,
            ),
        )));
        echo '<pre style="white-space:pre-wrap;font-size:12px">';
        print_r($rs);
        echo '</pre>';
    } catch (Exception $e) {
        echo 'Error: ' . esc_html($e->getMessage());
    }
    exit;
} elseif ($act === 'inspect_wsdl_types') {
    if (!current_user_can('manage_options')) {
        echo 'Permission denied';
        exit;
    }
    try {
        $soap  = new SoapClient($client->wsdl_url, array('trace' => true, 'exceptions' => true, 'cache_wsdl' => WSDL_CACHE_DISK));
        $types = $soap->__getTypes();

        // Supports a comma-separated list of filters, e.g. filter=EncounterCreate,ServiceLineReq
        $filter_raw = sanitize_text_field($_REQUEST['filter'] ?? '');
        $filters    = array_filter(array_map('trim', explode(',', $filter_raw)));

        if (!empty($filters)) {
            $types = array_filter($types, function ($t) use ($filters) {
                foreach ($filters as $f) {
                    if (stripos($t, $f) !== false) return true;
                }
                return false;
            });
        }

        echo '<pre style="white-space:pre-wrap;font-size:12px">';
        foreach ($types as $t) {
            echo esc_html($t) . "\n\n";
        }
        echo '</pre>';
    } catch (Exception $e) {
        echo 'Error: ' . esc_html($e->getMessage());
    }
    exit;
} elseif ($act === 'tebra_get_service_locations') {
    if (!current_user_can('manage_options')) {
        echo 'Permission denied';
        exit;
    }
    $tebra  = new CI_Api_Tebra();
    $result = $tebra->get_service_locations();

    echo '<pre style="white-space:pre-wrap;font-size:12px">';
    if (!empty($result['Locations']['ServiceLocationData'])) {
        $locations = $result['Locations']['ServiceLocationData'];
        // If only 1 result, SOAP/json won't wrap it in an array — normalize it.
        if (isset($locations['ID'])) {
            $locations = array($locations);
        }
        foreach ($locations as $loc) {
            echo esc_html(sprintf(
                "ID: %s | Name: %s | City: %s | State: %s | Practice: %s\n",
                $loc['ID'] ?? '',
                $loc['Name'] ?? '',
                $loc['City'] ?? '',
                $loc['State'] ?? '',
                $loc['PracticeName'] ?? ''
            ));
        }
    } else {
        echo "No data found or an error occurred:\n";
        echo esc_html(print_r($result, true));
    }
    echo '</pre>';
    exit;
} elseif ($act === 'tebra_get_providers') {
    if (!current_user_can('manage_options')) {
        echo 'Permission denied';
        exit;
    }
    $tebra  = new CI_Api_Tebra();
    $result = $tebra->get_providers();

    echo '<pre style="white-space:pre-wrap;font-size:12px">';
    if (!empty($result['Providers']['ProviderData'])) {
        $providers = $result['Providers']['ProviderData'];
        if (isset($providers['ID'])) {
            $providers = array($providers);
        }
        foreach ($providers as $p) {
            echo esc_html(sprintf(
                "ID: %s | Name: %s | Type: %s | Active: %s | Practice: %s\n",
                $p['ID'] ?? '',
                $p['FullName'] ?? '',
                $p['Type'] ?? '',
                $p['Active'] ?? '',
                $p['PracticeName'] ?? ''
            ));
        }
    } else {
        echo "No data found or an error occurred:\n";
        echo esc_html(print_r($result, true));
    }
    echo '</pre>';
    exit;
} elseif ($act === 'tebra_get_procedure_codes') {
    if (!current_user_can('manage_options')) {
        echo 'Permission denied';
        exit;
    }
    $tebra  = new CI_Api_Tebra();
    $result = $tebra->get_procedure_codes();

    echo '<pre style="white-space:pre-wrap;font-size:12px">';
    if (!empty($result['ProcedureCodes']['ProcedureCodeData'])) {
        $codes = $result['ProcedureCodes']['ProcedureCodeData'];
        if (isset($codes['ID'])) {
            $codes = array($codes);
        }
        foreach ($codes as $c) {
            echo esc_html(sprintf(
                "ID: %s | Code: %s | Name: %s | Active: %s\n",
                $c['ID'] ?? '',
                $c['ProcedureCode'] ?? '',
                $c['OfficialName'] ?? '',
                $c['Active'] ?? ''
            ));
        }
    } else {
        echo "No data found or an error occurred:\n";
        echo esc_html(print_r($result, true));
    }
    echo '</pre>';
    exit;
} elseif ($act === 'view_request_log') {
    $identifier   = sanitize_text_field($_REQUEST['identifier'] ?? '');
    $days_to_scan = 14;
    $found        = array();

    for ($i = 0; $i < $days_to_scan; $i++) {
        $date = date_i18n('Y-m-d', strtotime("-{$i} days"));
        $entries = $client->read_log('requests', $date, $identifier, null, 0);
        if (!empty($entries)) {
            $found = array_merge($found, $entries);
        }
    }
?>
    <h3>Request log — <?php echo esc_html($identifier) ?></h3>
    <?php if (empty($found)): ?>
        <p>No log entries found in the last <?php echo $days_to_scan ?> days.</p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:150px">Time</th>
                    <th style="width:200px">Type</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($found as $entry): ?>
                    <tr>
                        <td style="font-size:12px"><?php echo esc_html($entry['time'] ?? '') ?></td>
                        <td style="font-size:12px"><?php echo esc_html($entry['type'] ?? '') ?></td>
                        <td>
                            <pre style="white-space:pre-wrap;font-size:11px;margin:0"><?php
                                                                                        echo esc_html(wp_json_encode($entry['message'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                                                                        ?></pre>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php
    exit;
} elseif ($act === 'view_cron_log') {
    $date = sanitize_text_field($_REQUEST['date'] ?? date_i18n('Y-m-d'));
    $available_dates = $client->list_log_dates('cron');
    $entries = $client->read_log('cron', $date, null, null, 300);
?>
    <h3>Cron log</h3>
    <form method="get" style="margin-bottom:10px">
        <?php foreach ($_GET as $k => $v) {
            if ($k === 'date') continue;
            echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr($v) . '">';
        } ?>
        <select name="date" onchange="this.form.submit()">
            <?php foreach ($available_dates as $d): ?>
                <option value="<?php echo esc_attr($d) ?>" <?php selected($d, $date) ?>><?php echo esc_html($d) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php if (empty($entries)): ?>
        <p>No log entries for this date.</p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:150px">Time</th>
                    <th style="width:150px">Type</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td style="font-size:12px"><?php echo esc_html($entry['time'] ?? '') ?></td>
                        <td style="font-size:12px"><?php echo esc_html($entry['type'] ?? '') ?></td>
                        <td>
                            <pre style="white-space:pre-wrap;font-size:11px;margin:0"><?php
                                                                                        echo esc_html(is_scalar($entry['message'] ?? null) ? $entry['message'] : wp_json_encode($entry['message'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                                                                                        ?></pre>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php
    exit;
}

// ─── Settings form ────────────────────────────────────────────────────────────
?>

<div class="wrap">
    <h2>Tebra (Kareo) Settings</h2>
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
                    <label>Customer Key</label>
                    <input type="text" name="options[customer_key]"
                        value="<?php echo esc_attr($options['customer_key'] ?? '') ?>" required />
                </div>
                <div class="row">
                    <label>API User</label>
                    <input type="text" name="options[api_user]"
                        value="<?php echo esc_attr($options['api_user'] ?? '') ?>" required />
                </div>
                <div class="row">
                    <label>API Password</label>
                    <input type="password" name="options[api_password]"
                        value="<?php echo esc_attr($options['api_password'] ?? '') ?>" required />
                </div>
                <div class="row">
                    <label>Practice ID</label>
                    <input type="text" name="options[practice_id]"
                        value="<?php echo esc_attr($options['practice_id'] ?? '') ?>" />
                </div>
                <div class="row">
                    <label>Service Location ID</label>
                    <input type="text" name="options[service_location_id]"
                        value="<?php echo esc_attr($options['service_location_id'] ?? '') ?>" />
                </div>
                <div class="row">
                    <label>Rendering Provider ID</label>
                    <input type="text" name="options[rendering_provider_id]"
                        value="<?php echo esc_attr($options['rendering_provider_id'] ?? '') ?>" />
                </div>
                <div class="row">
                    <label>Default CPT Code</label>
                    <input type="text" name="options[default_cpt_code]"
                        value="<?php echo esc_attr($options['default_cpt_code'] ?? '') ?>"
                        placeholder="PLACEHOLDER — to be replaced with official CPT mapping" />
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
            </div>
        </div>

        <div class="frow row" style="margin-top:16px">
            <input type="submit" value="Save" class="button-primary" /> &nbsp;

            <a class="button thickbox"
                href="<?php Tr_Base_Class_V6::link(['trajax' => 1, 'act' => 'test_connection']) ?>&width=800">
                Test Connection
            </a> &nbsp;

            <a class="button thickbox"
                href="<?php Tr_Base_Class_V6::link(['trajax' => 1, 'act' => 'get_practices']) ?>&width=900&height=600">
                Get Practices
            </a> &nbsp;

            <a class="button thickbox"
                href="<?php Tr_Base_Class_V6::link(['trajax' => 1, 'act' => 'inspect_wsdl_types', 'filter' => 'EncounterCreate']) ?>&width=900&height=600">
                Inspect WSDL Types
            </a> &nbsp;

            <a class="button thickbox"
                href="<?php Tr_Base_Class_V6::link(['trajax' => 1, 'act' => 'tebra_get_service_locations']) ?>&width=900&height=600">
                Service Locations
            </a> &nbsp;

            <a class="button thickbox"
                href="<?php Tr_Base_Class_V6::link(['trajax' => 1, 'act' => 'tebra_get_providers']) ?>&width=900&height=600">
                Providers
            </a> &nbsp;

            <a class="button thickbox"
                href="<?php Tr_Base_Class_V6::link(['trajax' => 1, 'act' => 'run_cron']) ?>&width=1000">
                Run Cron Now
            </a> &nbsp;

            <a class="button thickbox"
                href="<?php Tr_Base_Class_V6::link(['trajax' => 1, 'act' => 'log']) ?>&width=1400&height=700">
                Log / Status
            </a> &nbsp;

            <a class="button thickbox"
                href="<?php Tr_Base_Class_V6::link(['trajax' => 1, 'act' => 'view_cron_log']) ?>&width=1000&height=600">
                Cron Log
            </a>

            <br><br>
            <?php
            global $wpdb;
            $batch_tbl = tr_pdd_get_table_by_btype('pcr');
            $count = $wpdb->get_row("
                SELECT
                    SUM(IF(mp.meta_value IS NOT NULL, 1, 0)) as count_patients_sent,
                    SUM(IF(me.meta_value IS NOT NULL, 1, 0)) as count_encounters_sent,
                    SUM(IF(ms.meta_value LIKE '%failed%', 1, 0)) as count_failed
                FROM {$batch_tbl} ba
                LEFT JOIN {$batch_tbl}_meta mp ON mp.mid = ba.mid AND mp.meta_key = '_tebra_patient_id'
                LEFT JOIN {$batch_tbl}_meta me ON me.mid = ba.mid AND me.meta_key = '_tebra_encounter_id'
                LEFT JOIN {$batch_tbl}_meta ms ON ms.mid = ba.mid AND ms.meta_key = '_tebra_status'
                WHERE ba.batch_id > 0 AND ba.patient_id > 0
            ");
            if (isset($_GET['debug'])) {
                echo $wpdb->last_query;
            }

            echo 'Patients sent: '   . ($count->count_patients_sent   ?? 0) . '<br>';
            echo 'Encounters sent: ' . ($count->count_encounters_sent ?? 0) . '<br>';
            echo 'Failed: '          . ($count->count_failed          ?? 0) . '<br>';
            echo 'Now: '             . date_i18n('Y-m-d H:i:s')             . '<br>';

            $next_cron      = wp_next_scheduled('cron_all_api', ['CI_Api_Tebra']);
            $current_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;
            if ($next_cron) {
                echo 'Next Cron: ' . date('Y-m-d H:i:s', $next_cron + $current_offset) . '<br>';
            }
            ?>
        </div>
    </form>
</div>

<style>
    .row label {
        display: inline-block;
        min-width: 160px;
        font-weight: 600;
    }

    .row {
        margin-bottom: 10px;
    }
</style>