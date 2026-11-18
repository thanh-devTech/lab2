<?php

/**
 * Class CI_Api_Genovation
 * Genovation LIS API — multipart panel request + PDF report retrieval
 */
class CI_Api_Genovation
{
    public $log_file_slug = 'genovation';
    public $log_dir       = WP_CONTENT_DIR . '/log/ci_api_genovation/';

    var $api_url = 'https://api.oncosure.genovation.cloud';

    public $debug    = false;
    public $all_btypes = array('gene');

    var $api_id  = -88;
    var $options = array();

    public function __construct()
    {
        $this->get_options();
    }

    public function get_options()
    {
        $this->options = get_option('api_genovation_st', array());
    }

    public function set_option($k, $vl)
    {
        $this->options[$k] = $vl;
        update_option('api_genovation_st', $this->options);
    }

    public function cron()
    {
        $this->send_orders();
    }

    // ─── Webhook callback ────────────────────────────────────────────────────

    public function callback()
    {
        global $wpdb;
        // Verify HMAC signature if secret is configured
        $secret = $this->options['webhook_secret'] ?? '';
        if (!empty($secret)) {
            $body_raw   = file_get_contents('php://input');
            $sig_header = $_SERVER['HTTP_X_GENOVATION_SIGNATURE'] ?? '';
            $received   = str_replace('sha256=', '', $sig_header);
            $expected   = hash_hmac('sha256', $body_raw, $secret);
            if (!hash_equals($expected, $received)) {
                wp_send_json(['status' => 'error', 'message' => 'Invalid signature'], 401);
            }
            $data = json_decode($body_raw, true);
        } else {
            $data = json_decode(file_get_contents('php://input'), true);
        }

        if (!is_array($data) || empty($data['patientGuid'])) {
            wp_send_json(['status' => 'error', 'message' => 'Invalid payload'], 400);
        }

        $patient_guid    = sanitize_text_field($data['patientGuid']);
        $pipeline_status = sanitize_text_field($data['pipelineStatus'] ?? '');
        $error_summary   = sanitize_text_field($data['errorSummary'] ?? '');

        $this->putlog($data, 'webhook', $patient_guid);

        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM ci_sync_api_orders WHERE mo_peg_ID = %s AND mo_file_id = %d",
            $patient_guid,
            $this->api_id
        ));

        if (empty($order)) {
            $this->putlog(['error' => 'Order not found', 'data' => $data], 'webhook', $patient_guid);
            wp_send_json(['status' => 'error', 'message' => 'Order not found'], 404);
        }

        $success_statuses = ['Complete', 'CompleteAmended'];
        $fail_statuses    = [
            'BlockedByInvalidSubmission',
            'VcfImportFailed',
            'HaplotypeImportBlockedByMissingKbCoverage',
            'ReportGenerationFailed',
        ];

        if (in_array($pipeline_status, $success_statuses)) {
            $btype     = $order->mo_btype;
            $batch_tbl = tr_pdd_get_table_by_btype($btype);
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$batch_tbl} WHERE mid = %d",
                $order->mo_mid
            ));

            $received_info = [
                'time'   => date_i18n('Y-m-d H:i:s'),
                'status' => $pipeline_status,
            ];

            if (!empty($row)) {
                $results = $this->download_and_save_report($patient_guid, $row, $order, $btype);
                if (empty($results)) {
                    $received_info['error'] = 'Pipeline complete but failed to download report for mid ' . $order->mo_mid;
                }
            } else {
                $this->putlog(['error' => 'Not found batch row for mid ' . $order->mo_mid, 'data' => $data], 'webhook', $patient_guid);
                $received_info['error'] = 'Not found batch row for mid ' . $order->mo_mid;
            }

            $this->merge_order_info($order->mo_ID, 'received', $received_info);

            if (!empty($received_info['error'])) {
                $wpdb->update($batch_tbl, [
                    'internal_notes' => 'Receive Error: ' . $received_info['error'],
                ], ['mid' => $order->mo_mid]);
            }
        } elseif (in_array($pipeline_status, $fail_statuses)) {
            $batch_tbl = tr_pdd_get_table_by_btype($order->mo_btype);

            $this->merge_order_info($order->mo_ID, 'received', [
                'time'   => date_i18n('Y-m-d H:i:s'),
                'status' => $pipeline_status,
                'error'  => $error_summary,
            ]);

            //$wpdb->update('ci_sync_api_orders', ['mo_sent' => 2], ['mo_ID' => $order->mo_ID]);

            $wpdb->update($batch_tbl, [
                'internal_notes' => 'Receive Error: ' . $pipeline_status . (!empty($error_summary) ? ' - ' . $error_summary : ''),
            ], ['mid' => $order->mo_mid]);
        }

        wp_send_json(['status' => 'ok', 'pipelineStatus' => $pipeline_status]);
    }

    // ─── Logging ─────────────────────────────────────────────────────────────

    public function putlog($msg, $type, $identifier)
    {
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
        }
        $filepath = $this->log_dir . sanitize_file_name($type . '_' . $identifier) . '.json';
        $now  = date_i18n('Y-m-d H:i:s');
        $logs = [];

        if (file_exists($filepath)) {
            $decoded = json_decode(file_get_contents($filepath), true);
            if (is_array($decoded)) {
                $logs = $decoded;
            }
        }

        $logs[] = ['time' => $now, 'message' => $msg];

        file_put_contents(
            $filepath,
            wp_json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }
    protected function merge_order_info($mo_ID, $key, $data)
    {
        global $wpdb;
        $current = $wpdb->get_var($wpdb->prepare(
            "SELECT other_info FROM ci_sync_api_orders WHERE mo_ID = %d",
            $mo_ID
        ));
        $info = !empty($current) ? json_decode($current, true) : [];
        if (!is_array($info)) $info = [];
        $info[$key] = $data;

        $wpdb->update('ci_sync_api_orders', [
            'other_info' => wp_json_encode($info),
        ], ['mo_ID' => $mo_ID]);
    }

    // ─── HTTP helpers ─────────────────────────────────────────────────────────

    protected function apiCall($urlpath, $args = array())
    {
        $url = $this->api_url . str_replace(' ', '%20', $urlpath);

        if (!isset($args['headers'])) {
            $args['headers'] = array();
        }

        // Auto-refresh token when needed (skip for token endpoint itself)
        if ($urlpath !== '/api/ClientToken/token') {
            if ($this->is_token_expired()) {
                $auth_rs = $this->auth();
                if (empty($auth_rs['token'])) {
                    error_log('Oncosure Auth failed: cannot refresh token');
                    return new WP_Error('auth_failed', 'Cannot refresh token');
                }
            }
            $token = $this->options['access_token'] ?? '';
            $args['headers']['Authorization'] = 'Bearer ' . $token;
        }

        if (!isset($args['timeout'])) {
            $args['timeout'] = 60;
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body        = wp_remote_retrieve_body($response);

        // PDF endpoints — return raw bytes + status
        if (isset($args['_return_raw']) && $args['_return_raw']) {
            return ['status_code' => $status_code, 'body' => $body];
        }

        $decoded = json_decode($body, true);
        return $decoded !== null ? $decoded : ['_raw' => $body, '_status' => $status_code];
    }

    protected function is_token_expired()
    {
        $expires = $this->options['token_expires'] ?? '';
        if (empty($expires)) {
            return true;
        }
        return strtotime($expires) <= (time() + 60); // 60s buffer
    }

    // ─── Authentication ───────────────────────────────────────────────────────

    public function auth()
    {
        $rs = $this->apiCall('/api/ClientToken/token', array(
            'method'  => 'POST',
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => wp_json_encode(array(
                'clientId'     => $this->options['client_id'] ?? '',
                'clientSecret' => $this->options['client_secret'] ?? '',
            )),
        ));

        if (!empty($rs['token'])) {
            $this->set_option('access_token', $rs['token']);
            $this->set_option('token_expires', $rs['expires'] ?? '');
        }

        return $rs;
    }

    public function register_webhook()
    {
        $webhook_url    = $this->options['webhook_url'] ?? '';
        $webhook_secret = $this->options['webhook_secret'] ?? '';

        if (empty($webhook_url)) {
            return new WP_Error('no_url', 'Webhook URL not configured');
        }

        $body = [
            'url'    => $webhook_url,
            'secret' => !empty($webhook_secret) ? $webhook_secret : null,
            'label'  => 'IgniteLIS',
        ];

        return $this->apiCall('/api/webhook', array(
            'method'  => 'POST',
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => wp_json_encode($body),
        ));
    }

    public function get_webhook()
    {
        return $this->apiCall('/api/webhook', array('method' => 'GET'));
    }

    public function delete_webhook($subscription_id)
    {
        $rs = $this->apiCall('/api/webhook/' . intval($subscription_id), array(
            'method' => 'DELETE',
        ));
        return $rs;
    }

    // ─── Send orders ──────────────────────────────────────────────────────────

    public function send_orders($assen = null)
    {
        global $wpdb;
        $limit = 10;
        $limitbid = date('Ymd', strtotime('-50 day', current_time('timestamp')));

        foreach ($this->all_btypes as $btype) {

            $batch_tbl  = tr_pdd_get_table_by_btype($btype);
            $report_tbl = tr_pdd_get_table_report_by_btype($btype);

            $where = '';
            if (!empty($assen)) {
                $where .= $wpdb->prepare(" AND ba.assen = %s ", $assen);
            } else {
                $where .= " AND (api.mo_ID IS NULL OR api.mo_sent = 0 OR api.mo_sent = 2) ";
            }

            $where .= " AND ba.batch_id > {$limitbid} AND ba.patient_id > 0 ";
            $where .= " AND (re.estatus != 'C' OR re.estatus IS NULL) ";
            $where .= " AND EXISTS (
                SELECT 1 FROM ci_reports_files rf
                WHERE rf.rf_btype = '{$btype}'
                AND (rf.rf_file LIKE '%.rpt%' OR rf.rf_file LIKE '%.vcf%')
                AND FIND_IN_SET(ba.assen, rf.rf_assen_list)
            ) ";

            $sql = "SELECT ba.*,
                        cli.*,
                        doc.*,
                        pa.*,
                        lab.lab_name,
                        api.mo_ID,
                        api.mo_patient_ID,
                        api.other_info,
                        '{$btype}' as btype
                    FROM {$batch_tbl} ba
                    LEFT JOIN {$report_tbl} re ON re.patient_id = ba.assen
                    JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
                    JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
                    JOIN ci_doc doc ON doc.doc_ID = ba.doc_ID
                    JOIN ci_clinic cli ON cli.cID = ba.cli_ID
                    LEFT JOIN ci_sync_api_orders api ON api.mo_mid = ba.mid
                                                    AND api.mo_file_id = '{$this->api_id}'
                                                    AND api.mo_btype = '{$btype}'
                    WHERE 1 {$where}
                    ORDER BY mid DESC
                    LIMIT {$limit}";


            $rows = $wpdb->get_results($sql);
            echo $wpdb->last_error;
            if (isset($_GET['debug'])) {
                echo '<pre>';
                echo $wpdb->last_query;
                echo '</pre>';
            }

            $rs   = null;

            if (empty($rows)) return null;

            foreach ($rows as $row) {

                $files = $this->get_instrument_files([$row->assen]);

                if (empty($files['rpt']) && empty($files['vcf'])) {
                    continue;
                }

                $row->plate_location = $wpdb->get_var("select meta_value from ci_batches_gene_meta 
                                where meta_key = '_plate_location' and mid = {$row->mid} ");

                $groupsids = [];
                foreach (explode(',', $row->panel_tests_requested ?? "") as $t) {
                    if (stripos($t, 'GROUP_') !== false) {
                        $gid = str_replace('GROUP_', '', $t);
                        if (!in_array($gid, $groupsids)) {
                            $groupsids[] = $gid;
                        }
                    }
                }
                $row->report_types = [];

                if (!empty($groupsids)) {
                    $placeholders = implode(',', array_fill(0, count($groupsids), '%d'));

                    $sql = $wpdb->prepare(
                        "SELECT tg_cpt_code FROM ci_test_groups WHERE tg_ID IN ($placeholders) AND tg_parent = 0 AND tg_show_req = 1",
                        $groupsids
                    );

                    $row->report_types = $wpdb->get_col($sql);
                }

                // Build multipart/form-data body
                $patient = $this->build_patient_payload($row);

                $this->putlog([
                    'patient'    => $patient,
                    'files_info' => [
                        'rpt_path' => $files['rpt'] ?? null,
                        'vcf_path' => $files['vcf'] ?? null,
                    ],
                ], 'order_request', $row->assen);

                $rs = $this->send_panel_request_curl(
                    $this->api_url . '/api/PanelRequest',
                    $patient,
                    $files
                );

                if (!empty($files['_tmp'])) {
                    foreach ($files['_tmp'] as $tmp) {
                        if (file_exists($tmp)) unlink($tmp);
                    }
                }

                if (is_wp_error($rs)) {
                    $err_msg = $rs->get_error_message();
                    $this->putlog(['error' => $err_msg], 'order_response', $row->assen);

                    $existing_info = !empty($row->other_info) ? json_decode($row->other_info, true) : [];
                    if (!is_array($existing_info)) $existing_info = [];
                    $existing_info['sent'] = [
                        'time'   => date_i18n('Y-m-d H:i:s'),
                        'status' => 'Error',
                        'error'  => $err_msg,
                    ];

                    $fail_update = [
                        'mo_file_id' => $this->api_id,
                        'mo_mid'     => $row->mid,
                        'mo_btype'   => $btype,
                        'mo_sent'    => 2,
                        'other_info' => wp_json_encode($existing_info),
                    ];
                    if (!empty($row->mo_ID)) {
                        $wpdb->update('ci_sync_api_orders', $fail_update, ['mo_ID' => $row->mo_ID]);
                    } else {
                        $wpdb->insert('ci_sync_api_orders', $fail_update);
                    }
                    continue;
                }

                $this->putlog($rs, 'order_response', $row->assen);

                if (!empty($rs['_status']) && $rs['_status'] >= 400) {
                    $err_msg = $rs['_raw'] ?? wp_json_encode($rs);

                    $existing_info = !empty($row->other_info) ? json_decode($row->other_info, true) : [];
                    if (!is_array($existing_info)) $existing_info = [];
                    $existing_info['sent'] = [
                        'time'   => date_i18n('Y-m-d H:i:s'),
                        'status' => 'HTTP ' . $rs['_status'],
                        'error'  => $err_msg,
                    ];

                    $fail_update = [
                        'mo_file_id' => $this->api_id,
                        'mo_mid'     => $row->mid,
                        'mo_btype'   => $btype,
                        'mo_sent'    => 1,
                        'mo_sent_dt' => date_i18n('Y-m-d H:i:s'),
                        'other_info' => wp_json_encode($existing_info),
                    ];
                    if (!empty($row->mo_ID)) {
                        $wpdb->update('ci_sync_api_orders', $fail_update, ['mo_ID' => $row->mo_ID]);
                    } else {
                        $wpdb->insert('ci_sync_api_orders', $fail_update);
                    }
                    continue;
                }

                $patient_guid = $rs['patientGuid'] ?? '';
                $status       = $rs['status'] ?? '';

                $accepted_statuses = ['Accepted', 'AcceptedForAmendment', 'InProgress'];

                if (!empty($patient_guid) && in_array($status, $accepted_statuses)) {
                    $existing_info = !empty($row->other_info) ? json_decode($row->other_info, true) : [];
                    if (!is_array($existing_info)) $existing_info = [];
                    $existing_info['sent'] = [
                        'time'   => date_i18n('Y-m-d H:i:s'),
                        'status' => $status,
                    ];

                    $update = [
                        'mo_file_id' => $this->api_id,
                        'mo_mid'     => $row->mid,
                        'mo_btype'   => $btype,
                        'mo_sent'    => 1,
                        'mo_sent_dt' => date_i18n('Y-m-d H:i:s'),
                        'mo_peg_ID'  => $patient_guid,
                        'other_info' => wp_json_encode($existing_info),
                    ];
                    if (!empty($row->mo_ID)) {
                        $wpdb->update('ci_sync_api_orders', $update, ['mo_ID' => $row->mo_ID]);
                    } else {
                        $wpdb->insert('ci_sync_api_orders', $update);
                    }
                } else {
                    $err_msg = 'No patientGuid in response: ' . wp_json_encode($rs);
                }
            }

            return $rs;
        }
    }

    // ─── Build multipart body ─────────────────────────────────────────────────

    protected function build_patient_payload($row)
    {
        return [
            'firstName'                      => $row->pa_first ?? '',
            'lastName'                       => $row->pa_last ?? '',
            'middleName'                     => $row->pa_middle ?? '',
            'suffix'                         => $row->pa_suffix ?? '',
            'dateOfBirth'                    => $this->to_iso8601($row->pa_dob ?? '', true),
            'sexAtBirth'                     => $row->pa_gender ?? '',
            'sourceSystemOfRecordIdentifier' => $row->assen,
            'addressLine1'                   => $row->pa_address ?? '',
            'addressLine2'                   => '',
            'city'                           => $row->pa_city ?? '',
            'state'                          => $row->pa_state ?? '',
            'postalCode'                     => $row->pa_zip ?? '',
            'country'                        => 'US',
            'labDateReceived'                => $this->to_iso8601($row->spec_received ?? '', true),
            'orderingPhysician'              => trim(($row->doc_first ?? '') . ' ' . ($row->doc_last ?? '')),
            'medicalFacilityName'            => $row->cli_clinic ?? '',
            'medicalFacilityIdentifier'      => $row->cID ?? '',
            'sourceLabName'                  => $row->lab_name ?? '',
            'specimenType'                   => $row->type_of_specimen ?? 'Gene',
            'specimenCollectionDate'         => $this->to_iso8601($row->doc ?? '', true),
            'specimenReceivedDate'           => $this->to_iso8601($row->spec_received ?? '', true),
            'sourceAccessionNumber'          => $row->assen,
            'plateLocation'                  => $row->plate_location ?? 'A01.CEL',
            'reportTypes'                    => $row->report_types,
        ];
    }

    protected function send_panel_request_curl($url, $patient, $files)
    {
        if ($this->is_token_expired()) {
            $auth_rs = $this->auth();
            if (empty($auth_rs['token'])) {
                return new WP_Error('auth_failed', 'Cannot refresh token');
            }
        }
        $token = $this->options['access_token'] ?? '';

        $post_fields = [
            'patient' => wp_json_encode($patient),
        ];

        if (!empty($files['rpt'])) {
            $post_fields['RptFile'] = new CURLFile(
                $files['rpt'],
                'application/octet-stream',
                strtolower(basename($files['rpt']))
            );
        }

        if (!empty($files['vcf'])) {
            $vcf_filename = strtolower(basename($files['vcf']));
            if (!str_ends_with($vcf_filename, '.gz') && !str_ends_with($vcf_filename, '.zip')) {
                $vcf_filename .= '.gz';
            }
            $post_fields['VcfFile'] = new CURLFile(
                $files['vcf'],
                'application/octet-stream',
                $vcf_filename
            );
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post_fields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 600,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
            ],
        ]);

        $body        = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err    = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return new WP_Error('curl_error', $curl_err);
        }

        $decoded = json_decode($body, true);
        return $decoded !== null ? array_merge($decoded, ['_status' => $status_code]) : ['_raw' => $body, '_status' => $status_code];
    }

    protected function to_iso8601($date_str, $fallback_now = false)
    {
        if (!empty($date_str)) {
            $ts = strtotime($date_str);
            if ($ts) {
                return date_i18n('Y-m-d\TH:i:s\Z', $ts);
            }
        }

        if ($fallback_now) {
            return date_i18n('Y-m-d\TH:i:s\Z');
        }

        return '';
    }

    // ─── Get instrument files ─────────────────────────────────────────────────

    public function get_instrument_files($assens, $info_only = false)
    {
        global $wpdb;

        if (empty($assens) || !is_array($assens)) {
            return [];
        }

        $assen_conditions = array_map(function ($a) use ($wpdb) {
            return $wpdb->prepare("FIND_IN_SET(%s, rf_assen_list)", $a);
        }, $assens);
        $assen_sql = '(' . implode(' OR ', $assen_conditions) . ')';

        $result_files = $wpdb->get_col("
        SELECT rf_file
        FROM ci_reports_files
        WHERE rf_btype = 'gene'
        AND {$assen_sql}
        ORDER BY rf_created DESC
    ");

        if (empty($result_files)) {
            return [];
        }

        $base_dir = WP_CONTENT_DIR . '/log/results_gene/';
        $files    = ['rpt' => null, 'vcf' => null, '_tmp' => []];

        foreach ($result_files as $fname) {
            $full_path = $base_dir . $fname;
            if (!file_exists($full_path)) continue;

            $lower = strtolower($fname);

            if ($files['rpt'] === null && str_contains($lower, '.rpt')) {
                $files['rpt'] = $info_only
                    ? ['path' => $full_path, 'size' => filesize($full_path)]
                    : $full_path;
                continue;
            }

            if ($files['vcf'] === null && str_contains($lower, '.vcf')) {
                $ext = pathinfo($lower, PATHINFO_EXTENSION);

                if ($info_only) {
                    $files['vcf'] = ['path' => $full_path, 'size' => filesize($full_path)];
                    continue;
                }

                if (in_array($ext, ['gz', 'zip'])) {
                    $files['vcf'] = $full_path;
                } else {
                    $tmp_path = get_temp_dir() . uniqid('vcf_') . '.vcf.gz';
                    $this->gzip_file_stream($full_path, $tmp_path);
                    $files['vcf']    = $tmp_path;
                    $files['_tmp'][] = $tmp_path;
                }
                continue;
            }
        }

        return array_filter($files, fn($v) => $v !== null && $v !== []);
    }
    protected function gzip_file_stream($src, $dst, $level = 6)
    {
        $fp_in  = fopen($src, 'rb');
        $fp_out = gzopen($dst, 'wb' . $level);
        while (!feof($fp_in)) {
            gzwrite($fp_out, fread($fp_in, 1024 * 512));
        }
        fclose($fp_in);
        gzclose($fp_out);
    }

    // ─── Download reports (called from webhook or manual) ─────────────────────

    public function get_result_file($assen = '', $onlybtype = '')
    {
        global $wpdb;
        $rs = null;

        foreach ($this->all_btypes as $btype) {

            if (!empty($onlybtype) && $onlybtype !== $btype) {
                continue;
            }

            $batch_tbl = tr_pdd_get_table_by_btype($btype);

            $where = '';
            if (!empty($assen)) {
                $where = $wpdb->prepare(" AND ba.assen = %s ", $assen);
            } else {
                $time_check = date_i18n('Y-m-d H:i:s', strtotime('-5 minutes', current_time('timestamp')));
                $where      = $wpdb->prepare("
                    AND api.mo_sent >= 1
                    AND api.mo_status = 0
                    AND api.mo_sent_dt < %s
                ", $time_check);
                $where .= " AND ba.batch_id > 0 AND ba.patient_id > 0 ";
            }

            $sql = "SELECT ba.*, api.mo_ID, api.mo_peg_ID, '{$btype}' as btype
                    FROM {$batch_tbl} ba
                    JOIN ci_sync_api_orders api ON api.mo_mid = ba.mid
                                                AND api.mo_file_id = '{$this->api_id}'
                                                AND api.mo_btype = '{$btype}'
                    WHERE 1 {$where}
                    ORDER BY mid DESC
                    LIMIT 5";

            $rows = $wpdb->get_results($sql);

            foreach ($rows as $row) {
                $patient_guid = $row->mo_peg_ID ?? '';
                if (empty($patient_guid)) continue;

                $rs = $this->download_and_save_report($patient_guid, $row, (object)['mo_ID' => $row->mo_ID], $btype);
            }
        }

        return $rs;
    }

    public function download_and_save_report($patient_guid, $row, $order, $btype)
    {
        global $wpdb;
        $batch_tbl = tr_pdd_get_table_by_btype($btype);
        $results   = [];

        $existing_raw = $row->result_pdf ?? '';
        $pdf_list     = !empty($existing_raw) ? explode(',', $existing_raw) : [];
        $has_new_pdf  = false;

        foreach (['pgx', 'disease'] as $report_type) {
            $rs = $this->download_report($patient_guid, $report_type);
            $this->putlog(
                ['status_code' => $rs['status_code'] ?? null, 'bytes' => strlen($rs['body'] ?? '')],
                'download_' . $report_type,
                $patient_guid
            );

            if (
                !is_wp_error($rs) &&
                ($rs['status_code'] ?? 0) === 200 &&
                strlen($rs['body'] ?? '') > 1000
            ) {
                $dir = tr_get_result_upload_dir();
                if (!file_exists($dir['path'])) {
                    wp_mkdir_p($dir['path']);
                }

                $filename = strtolower($row->assen) . '_' . $this->log_file_slug . '_' . $report_type . '.pdf';
                $pdf_path = $dir['path'] . $filename;

                file_put_contents($pdf_path, $rs['body']);

                $already_exists = false;
                foreach ($pdf_list as $f) {
                    if (strtolower($f) === strtolower($filename)) {
                        $already_exists = true;
                        break;
                    }
                }
                if (!$already_exists) {
                    $pdf_list[] = $filename;
                }

                $has_new_pdf = true;
                $results[$report_type] = $filename;
            }
        }

        if ($has_new_pdf) {
            $wpdb->update($batch_tbl, ['result_pdf' => implode(',', $pdf_list)], ['mid' => $row->mid]);
            $wpdb->update('ci_sync_api_orders', ['mo_status' => 1], ['mo_ID' => $order->mo_ID]);
        }

        return $results;
    }

    public function download_report($patient_guid, $report_type = 'pgx')
    {
        $report_type = in_array($report_type, ['pgx', 'disease']) ? $report_type : 'pgx';
        $this->putlog(['patientGuid' => $patient_guid, 'type' => $report_type], 'download_request', $patient_guid);

        return $this->apiCall('/api/reports/' . urlencode($patient_guid) . '/' . $report_type, array(
            'method'       => 'GET',
            '_return_raw'  => true,
        ));
    }
}
