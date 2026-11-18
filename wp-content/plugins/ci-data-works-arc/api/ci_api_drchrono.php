<?php
defined('ABSPATH') || exit;

class DrChrono_Lab
{
    const VERSION         = '1.0.0';
    const API_BASE        = 'https://app.drchrono.com';
    const TOKEN_URL       = 'https://drchrono.com/o/token/';
    const TOKEN_TRANSIENT = 'drchrono_access_token';
    const OPTION_KEY      = 'tr_dchrono_st';
    const REST_NAMESPACE  = 'drchrono-lab/v1';

    const SLUG_PAGE = 'lab-requisition';
    const CRON_HOOK     = 'drchrono_send_lab_results';
    const META_KEY_SENT = 'drchrono_result_sent_at';
    const META_KEY_RECEIVED = 'drchrono_result_received_at';
    const BTYPES        = ['blood', 'tox', 'pcr'];

    private static ?DrChrono_Lab $instance = null;
    public $api_id = -86;

    public static function get_instance(): DrChrono_Lab
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function init(): void
    {
        // Front-end: iFrame shortcode
        add_action('wp_loaded', [$this, 'maybe_create_iframe_page']);
        add_action('template_redirect', [$this, 'handle_form_submit'], 5);
        add_action('template_redirect', [$this, 'render_iframe_form'], 10);
        add_action('send_headers', [$this, 'maybe_allow_iframe']);

        // AJAX: Submit order from iFrame
        add_action('drchrono_req_saved', [$this, 'on_req_saved'], 10, 5);

        // Admin: Settings page hooks
        add_action('admin_post_drchrono_create_sublab', [$this, 'handle_create_sublab']);
    }
    // cron setup
    public function init_cron(): void
    {
        add_action('get_drchrono_results', [$this, 'run_drchrono_results']);

        if (!wp_next_scheduled('get_drchrono_results')) {
            wp_schedule_event(
                time() + 300,
                'daily',
                'get_drchrono_results'
            );
        }
    }

    public function run_drchrono_results(): void
    {
        $this->log('[cron] get_drchrono_results started');

        foreach (self::BTYPES as $btype) {
            $this->cron_get_drchrono_results($btype);
        }

        $this->log('[cron] get_drchrono_results finished');
    }

    public function run_cron(): void
    {
        $this->log('[cron] drchrono_send_lab_results started');

        foreach (self::BTYPES as $btype) {
            $this->cron_process_btype($btype);
        }

        $this->log('[cron] drchrono_send_lab_results finished');
    }
    private function cron_process_btype(string $btype): void
    {
        global $wpdb;

        $meta_table = ($btype === 'tox') ? 'ci_batches_meta' : "ci_batches_{$btype}_meta";

        // Lấy batch có drchrono_order_id nhưng chưa gửi result
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT m.mid,
                m.meta_value AS drchrono_order_id
         FROM {$meta_table} m
         WHERE m.meta_key = 'drchrono_order_id'
           AND NOT EXISTS (
               SELECT 1 FROM {$meta_table} s
               WHERE s.mid      = m.mid
                 AND s.meta_key = %s
           )",
            self::META_KEY_SENT
        ), ARRAY_A);

        if (empty($rows)) {
            $this->log("[cron][{$btype}] No pending batches");
            return;
        }

        $this->log("[cron][{$btype}] Found " . count($rows) . " pending batch(es)");

        $batch_table = "ci_batches_{$btype}";

        foreach ($rows as $row) {
            $this->cron_process_batch(
                $btype,
                (int) $row['mid'],
                (int) $row['drchrono_order_id'],
                $batch_table,
                $meta_table
            );
        }
    }
    private function cron_get_drchrono_results(string $btype): void
    {
        global $wpdb;

        $meta_table = ($btype === 'tox') ? 'ci_batches_meta' : "ci_batches_{$btype}_meta";

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT m.mid,
                m.meta_value AS drchrono_order_id
         FROM {$meta_table} m
         WHERE m.meta_key = 'drchrono_order_id'
           AND NOT EXISTS (
               SELECT 1 FROM {$meta_table} s
               WHERE s.mid      = m.mid
                 AND s.meta_key = %s
           )",
            self::META_KEY_RECEIVED
        ), ARRAY_A);

        if (empty($rows)) {
            $this->log("[cron][{$btype}] No pending batches");
            return;
        }

        $this->log("[cron][{$btype}] Found " . count($rows) . " pending batch(es)");

        $batch_table = "ci_batches_{$btype}";

        foreach ($rows as $row) {
            $this->cron_process_batch_get_results(
                $btype,
                (int) $row['mid'],
                (int) $row['drchrono_order_id'],
                $batch_table,
                $meta_table
            );
        }
    }
    private function cron_process_batch_get_results(
        string $btype,
        int    $batch_id,
        int    $order_id,
        string $batch_table,
        string $meta_table
    ): void {
        global $wpdb;
        $report = $this->cron_get_report($btype, $batch_id, $batch_table);
        if (!$report) {
            $this->log("[cron][{$btype}] batch={$batch_id}: No finalized report, skipping");
            return;
        }
        $this->log("[cron][{$btype}] batch={$batch_id} order={$order_id}: Fetching results from DrChrono...");

        $results_response = $this->api_get("/api/lab_results?order={$order_id}");

        $all_results = $results_response['results'] ?? [];

        if (empty($all_results)) {
            $this->log("[cron][{$btype}] batch={$batch_id} order={$order_id}: No results available yet, skipping");
            return;
        }
        $this->insert_results_into_db($btype, $report['id'], $all_results);

        $wpdb->insert(
            $meta_table,
            [
                'mid'        => $batch_id,
                'meta_key'   => self::META_KEY_RECEIVED,
                'meta_value' => current_time('mysql'),
            ],
            ['%d', '%s', '%s']
        );

        $this->log("[cron][{$btype}] batch={$batch_id} order={$order_id}: Results received and marked ✓ (total=" . count($all_results) . ")");
    }

    private function cron_process_batch(
        string $btype,
        int    $batch_id,
        int    $order_id,
        string $batch_table,
        string $meta_table
    ): void {
        global $wpdb;

        $pdf_path = WP_CONTENT_DIR . '/uploads/pdf/panel/Patient-Education-Encyclopedia-STI-and-Vaginitis-Panel-v1-1.pdf';

        // send result to DrChrono
        $success = $this->process_lab_result($order_id, $pdf_path);

        if (!$success) {
            $this->log("[cron][{$btype}] batch={$batch_id} order={$order_id}: FAILED to send result");
            return;
        }

        $wpdb->insert(
            $meta_table,
            [
                'mid'        => $batch_id,
                'meta_key'   => self::META_KEY_SENT,
                'meta_value' => current_time('mysql'),
            ],
            ['%d', '%s', '%s']
        );

        $this->log("[cron][{$btype}] batch={$batch_id} order={$order_id}: Sent successfully ✓");
    }

    function insert_results_into_db(string $btype, int $report_id, array $results): void
    {
        global $wpdb;

        $table = ($btype === 'tox') ? 'ci_results' : "ci_results_{$btype}";

        foreach ($results as $result) {
            $obs_desc = $result['observation_description'] ?? '';
            $obs_code = $result['observation_code']        ?? '';
            $group_id = 0;

            if ($obs_desc || $obs_code) {
                $te_group_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT CASE WHEN tg.tg_parent = 0 THEN tg.tg_ID ELSE tg.tg_parent END AS group_id
                    FROM ci_test_panels tp
                    JOIN ci_test_groups tg ON tg.tg_ID = tp.te_group_id
                    WHERE tp.te_type = %s
                    AND (
                        tp.te_name   = %s OR tp.te_name   = %s
                        OR tp.te_abrev  = %s OR tp.te_abrev  = %s
                        OR tp.te_abrev2 = %s OR tp.te_abrev2 = %s
                    )
                    LIMIT 1",
                    $btype,
                    $obs_desc,
                    $obs_code,
                    $obs_desc,
                    $obs_code,
                    $obs_desc,
                    $obs_code,
                ));

                $group_id = $te_group_id ? (int) $te_group_id : 0;
            }
            $wpdb->insert(
                $table,
                [
                    'report_id' => $report_id,
                    'drug' => $result['observation_description'] ?: $result['observation_code'] ?: '',
                    'conc'     => $result['value'] ?? '',
                    'pos-neg'      => $result['is_abnormal'] ? 'POS' : 'NEG',
                    'comments'    => $result['comments'] ?? '',
                    'has_order'  => 1,
                    'group_id' => $group_id,
                ],
                ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d']
            );
        }
    }

    private function cron_get_report(string $btype, int $batch_id, string $batch_table): ?array
    {
        global $wpdb;

        $report_table = ($btype === 'tox') ? 'ci_reports' : "ci_reports_{$btype}";

        $report = $wpdb->get_row($wpdb->prepare(
            "SELECT re.id, re.estatus, re.approve_date
            FROM {$batch_table}  ba
            JOIN {$report_table} re ON re.patient_id = ba.assen
            WHERE ba.mid = %d
            ORDER BY re.approve_date DESC
            LIMIT 1",
            $batch_id
        ), ARRAY_A) ?: null;

        if ($report) {
            return $report;
        }

        $batch = $wpdb->get_row($wpdb->prepare(
            "SELECT assen, batch_id FROM {$batch_table} WHERE mid = %d LIMIT 1",
            $batch_id
        ), ARRAY_A);

        if (empty($batch) || empty($batch['assen'])) {
            $this->log("[cron][{$btype}] batch={$batch_id}: assen not found in {$batch_table}, cannot insert report");
            return null;
        }
        if (empty($batch['batch_id'])) {
            $batch['batch_id'] = date('Ymd', strtotime(date_i18n('Y-m-d H:i:s')));
            $wpdb->update($batch_table, ['batch_id' => $batch['batch_id']], ['mid' => $batch_id]);
        }

        $inserted = $wpdb->insert(
            $report_table,
            [
                'patient_id'   => $batch['assen'],
                'batch_id'    => $batch['batch_id'] ?: '',
                'author_id'    => 0,
                'created'      => date_i18n('Y-m-d H:i:s'),
                'created_date' => date_i18n('Y-m-d'),
                'instrument'   => $this->api_id,
            ],
            ['%s', '%d', '%s', '%s', '%s']
        );

        if (!$inserted) {
            $this->log("[cron][{$btype}] batch={$batch_id}: Failed to insert new report for assen={$batch['assen']}");
            return null;
        }
        $new_id = $wpdb->insert_id;

        return [
            'id'           => $new_id,
            'estatus'      => '',
            'approve_date' => null,
        ];
    }

    public function maybe_create_iframe_page(): void
    {
        $slug     = self::SLUG_PAGE;
        $existing = get_page_by_path($slug);

        if ($existing) {
            return;
        }

        wp_insert_post([
            'post_title'   => 'Lab Requisition',
            'post_name'    => $slug,
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
        ]);
    }

    public function set_option($key, $value = null): void
    {
        $options = get_option(self::OPTION_KEY, []);

        if (is_array($key)) {
            $options = array_merge($options, $key);
        } else {
            $options[$key] = $value;
        }

        update_option(self::OPTION_KEY, $options);

        // Clear token cache if credentials change
        if (in_array(is_array($key) ? 'client_id' : $key, ['client_id', 'client_secret'])) {
            delete_transient(self::TOKEN_TRANSIENT);
        }
    }
    public function get_option(?string $key = null, $default = '')
    {
        $options = get_option(self::OPTION_KEY, []);
        if ($key === null) return $options;
        return $options[$key] ?? $default;
    }

    public function get_token()
    {
        // Check transient cache first
        $cached = get_transient(self::TOKEN_TRANSIENT);
        if ($cached) return $cached;

        // Try to get from options (fallback)
        $token   = $this->get_option('access_token');
        $expires = (int)$this->get_option('token_expires', 0);

        if ($token && ($expires === 0 || $expires > time())) {
            // Cache it
            $ttl = $expires > 0 ? ($expires - time()) : 172800;
            set_transient(self::TOKEN_TRANSIENT, $token, $ttl);
            return $token;
        }

        return $this->refresh_token();
    }

    public function refresh_token()
    {
        $client_id     = $this->get_option('client_id');
        $client_secret = $this->get_option('client_secret');
        $refresh_token = $this->get_option('refresh_token');

        if (!$client_id || !$client_secret || !$refresh_token) {
            $this->log('refresh_token: Missing credentials');
            return false;
        }

        $response = wp_remote_post(self::TOKEN_URL, [
            'timeout' => 15,
            'body'    => [
                'refresh_token' => $refresh_token,
                'grant_type'    => 'refresh_token',
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
            ],
        ]);

        if (is_wp_error($response)) {
            $this->log('refresh_token error: ' . $response->get_error_message());
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data['access_token'])) {
            $this->log('refresh_token: No access_token in response');
            return false;
        }

        // Save new tokens
        $expires_in = (int)($data['expires_in'] ?? 172800);

        $this->set_option([
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $refresh_token,
            'token_expires' => time() + $expires_in,
        ]);

        set_transient(self::TOKEN_TRANSIENT, $data['access_token'], $expires_in);

        return $data['access_token'];
    }

    private function api_headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->get_token(),
            'Content-Type'  => 'application/json',
        ];
    }


    public function api_get(string $endpoint)
    {
        $response = wp_remote_get(self::API_BASE . $endpoint, [
            'timeout' => 15,
            'headers' => $this->api_headers(),
        ]);

        return $this->parse_response($response, 'GET ' . $endpoint);
    }


    public function api_post(string $endpoint, array $body)
    {
        $response = wp_remote_post(self::API_BASE . $endpoint, [
            'timeout' => 15,
            'headers' => $this->api_headers(),
            'body'    => wp_json_encode($body),
        ]);

        return $this->parse_response($response, 'POST ' . $endpoint);
    }


    public function api_patch(string $endpoint, array $body)
    {
        $response = wp_remote_request(self::API_BASE . $endpoint, [
            'method'  => 'PATCH',
            'timeout' => 15,
            'headers' => $this->api_headers(),
            'body'    => wp_json_encode($body),
        ]);

        return $this->parse_response($response, 'PATCH ' . $endpoint);
    }


    public function api_post_file(string $endpoint, string $file_path, array $fields)
    {
        if (!file_exists($file_path)) {
            $this->log("api_post_file: File not found: {$file_path}");
            return false;
        }

        $boundary     = wp_generate_password(24, false);
        $file_content = file_get_contents($file_path);
        $filename     = basename($file_path);
        $body         = '';

        // Add text fields
        foreach ($fields as $name => $value) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
            $body .= "{$value}\r\n";
        }

        // Add file field
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"document\"; filename=\"{$filename}\"\r\n";
        $body .= "Content-Type: application/pdf\r\n\r\n";
        $body .= $file_content . "\r\n";
        $body .= "--{$boundary}--\r\n";

        $response = wp_remote_post(self::API_BASE . $endpoint, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->get_token(),
                'Content-Type'  => "multipart/form-data; boundary={$boundary}",
            ],
            'body' => $body,
        ]);

        return $this->parse_response($response, 'POST_FILE ' . $endpoint);
    }

    /**
     * Parse HTTP response
     */
    private function parse_response($response, string $context)
    {
        if (is_wp_error($response)) {
            $this->log("{$context} WP_Error: " . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            $this->log("{$context} HTTP {$code}: " . wp_json_encode($body));
            return false;
        }

        return $body ?? [];
    }

    public function create_sublab(string $name = '', string $facility_code = '')
    {
        $name          = $name          ?: $this->get_option('lab_name');
        $facility_code = $facility_code ?: $this->get_option('facility_code');

        if (!$name || !$facility_code) {
            $this->log('create_sublab: Missing name or facility_code');
            return false;
        }

        $result = $this->api_post('/api/sublabs', [
            'name'          => $name,
            'facility_code' => $facility_code,
        ]);

        if (!empty($result['id'])) {
            $this->set_option('sublab_id', $result['id']);
            return (int)$result['id'];
        }

        return false;
    }

    /**
     * Admin handler for creating sublab
     */
    public function handle_create_sublab(): void
    {
        check_admin_referer('drchrono_create_sublab');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');

        $sublab_id = $this->create_sublab();
        $status    = $sublab_id ? "created&id={$sublab_id}" : 'failed';

        wp_redirect(admin_url("options-general.php?page=drchrono-lab&sublab={$status}"));
        exit;
    }

    public function create_order(
        int    $doctor_id,
        int    $patient_id,
        array  $icd10_codes = [],
        string $notes       = '',
        string $priority    = 'N',
        string $accession   = ''
    ) {
        $sublab_id = (int)$this->get_option('sublab_id');

        if (!$sublab_id) {
            $this->log('create_order: sublab_id not set');
            return false;
        }

        $payload = [
            'doctor'       => $doctor_id,
            'patient'      => $patient_id,
            'sublab'       => $sublab_id,
            'status'       => 'S',
            'notes'        => $notes,
            'priority'     => $priority,
            'icd10_codes'  => $icd10_codes ?: [],
        ];

        if ($accession) {
            $payload['accession_number'] = $accession;
        }

        return $this->api_post('/api/lab_orders', $payload);
    }

    public function upload_req_document(int $order_id, string $pdf_path, string $type = 'REQ')
    {
        return $this->api_post_file('/api/lab_documents', $pdf_path, [
            'lab_order' => $order_id,
            'type'      => $type,
        ]);
    }

    public function update_order_status(int $order_id, string $status)
    {
        return $this->api_patch("/api/lab_orders/{$order_id}", ['status' => $status]);
    }

    public function create_lab_tests(int $order_id, array $tests)
    {
        $payload = array_map(function ($test) use ($order_id) {
            return [
                'lab_order'             => $order_id,
                'code'                  => $test['code']             ?? '',
                'name'                  => $test['name']             ?? '',
                'description'           => $test['description']      ?? '',
                'collection_date'       => $test['collection_date']  ?? current_time('c'),
                'internal_notes'        => $test['internal_notes']   ?? '',
                'report_notes'          => $test['report_notes']     ?? '',
                'status'                => $test['status']           ?? 'F',
                'specimen_source'       => $test['specimen_source']  ?? '',
                'specimen_condition'    => $test['specimen_condition'] ?? '',
                'specimen_total_volume' => $test['specimen_total_volume'] ?? 0,
            ];
        }, $tests);

        return $this->api_post('/api/lab_tests', $payload);
    }

    public function upload_result_document(int $order_id, string $pdf_path)
    {
        return $this->api_post_file('/api/lab_documents', $pdf_path, [
            'lab_order' => $order_id,
            'type'      => 'RES',
        ]);
    }


    public function create_lab_results(int $order_id, int $document_id, array $results)
    {
        $payload = array_map(function ($result) use ($order_id, $document_id) {
            return [
                'lab_order'               => $order_id,
                'lab_test'                => $result['lab_test_id'],
                'document'                => $document_id,
                'status'                  => $result['status']           ?? 'F',
                'abnormal_status'         => $result['abnormal_status']   ?? 'N',
                'is_abnormal'             => $result['is_abnormal']       ?? false,
                'normal_range'            => $result['normal_range']      ?? '',
                'observation_description' => $result['name']              ?? '',
                'observation_code'        => $result['loinc_code']        ?? '',
                'test_performed'          => $result['test_performed']    ?? current_time('c'),
                'unit'                    => $result['unit']              ?? '',
                'value'                   => (string)($result['value']   ?? ''),
                'value_is_numeric'        => $result['value_is_numeric']  ?? 'True',
                'comments'                => $result['comments']          ?? '',
            ];
        }, $results);

        return $this->api_post('/api/lab_results', $payload);
    }

    public function process_lab_result(int $order_id, string $result_pdf): bool
    {
        $existing_tests = $this->api_get("/api/lab_tests?order={$order_id}&page_size=100");

        if (empty($existing_tests['results'])) {
            $this->log("process_lab_result: No existing tests found for order {$order_id}");
            return false;
        }


        $pdf_path = $result_pdf;
        $is_temp  = false;
        if (filter_var($result_pdf, FILTER_VALIDATE_URL)) {
            $pdf_path = $this->download_temp_file($result_pdf, "result_{$order_id}.pdf");
            $is_temp  = true;
        }

        $doc_response = $this->upload_result_document($order_id, $pdf_path);
        if ($is_temp) @unlink($pdf_path);

        if (empty($doc_response['id'])) {
            $this->log("process_lab_result: Failed to upload result document for order {$order_id}");
            return false;
        }
        $document_id = (int) $doc_response['id'];

        $results = [];
        foreach ($existing_tests['results'] as $t) {
            $results[] = [
                'lab_test_id'    => $t['id'],
                'name'           => $t['name'] ?? '',
                'loinc_code'     => $t['code'] ?? '_',
                'status'         => 'F',
                'abnormal_status' => 'N',
                'is_abnormal'    => false,
                'normal_range'   => '',
                'value'          => rand(1, 100), // random
                'unit'           => '_',
                'value_is_numeric' => 'True',
                'test_performed' => current_time('c'),
                'comments'       => 'test result value placeholder',
            ];
        }

        $final = $this->create_lab_results($order_id, $document_id, $results);
        if (!$final) {
            $this->log("process_lab_result: Failed to create lab results for order {$order_id}");
            return false;
        }

        $this->update_order_status($order_id, 'R');
        return true;
    }

    public function verify_jwt(string $jwt, int $doctor_id = 0, int $patient_id = 0): bool
    {
        if (!$jwt) return false;

        $parts = explode('.', $jwt);
        if (count($parts) !== 3) return false;

        [$header_b64, $payload_b64, $signature_b64] = $parts;

        // Verify signature
        $secret   = $this->get_option('client_secret');
        $expected = $this->base64url_encode(hash_hmac('sha256', "{$header_b64}.{$payload_b64}", $secret, true));

        if (!hash_equals($expected, $signature_b64)) {
            $this->log('verify_jwt: Signature mismatch');
            return false;
        }

        // Decode payload
        $payload = json_decode($this->base64url_decode($payload_b64), true);

        // Check expiry
        if (!empty($payload['exp']) && $payload['exp'] < time()) {
            $this->log('verify_jwt: Token expired');
            return false;
        }

        // Verify claims if provided
        if ($doctor_id && !empty($payload['doctor_id']) && (int)$payload['doctor_id'] !== $doctor_id) {
            $this->log('verify_jwt: doctor_id mismatch');
            return false;
        }
        if ($patient_id && !empty($payload['patient_id']) && (int)$payload['patient_id'] !== $patient_id) {
            $this->log('verify_jwt: patient_id mismatch');
            return false;
        }

        return true;
    }

    private function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64url_decode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }

    public function render_iframe_form(): void
    {
        $slug = self::SLUG_PAGE;
        if (!is_page($slug)) {
            return;
        }

        // Get params from URL (passed by DrChrono)
        $doctor_id   = absint($_GET['doctor_id']   ?? 0);
        $reqtype   = $_GET['reqtype'] ?? '';
        $patient_id  = absint($_GET['patient_id']  ?? 0);
        $practice_id = absint($_GET['practice_id'] ?? 0);
        $iat         = absint($_GET['iat']         ?? 0);
        $jwt         = sanitize_text_field($_GET['jwt'] ?? '');

        // Validate required params
        if (!$doctor_id || !$patient_id) {
            echo '<div class="drc-error">Missing required parameters (doctor_id or patient_id).</div>';
            return;
        }

        // Verify JWT
        if (!$this->verify_jwt($jwt, $doctor_id, $patient_id)) {
            echo '<div class="drc-error">Unauthorized: Invalid or expired session.</div>';
            return;
        }

        // Fetch data from DrChrono API
        $patient = $this->api_get("/api/patients/{$patient_id}");
        $doctor  = $this->api_get("/api/doctors/{$doctor_id}");

        if (!$patient || !$doctor) {
            echo '<div class="drc-error">Unable to load patient or doctor data from DrChrono.</div>';
            return;
        }
        wp_dequeue_style('style');

        remove_action('wp_print_scripts', 'gf_placeholder_enqueue_scripts');

        wp_enqueue_style('jquery-ui');
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui', '', array('jquery'));
        wp_enqueue_style('data-admin', TRCPDD_URL . 'css/admin_new.css', array(), TRCPDD_VS);

        wp_enqueue_script('select2', TRCPDD_URL . '/js/select2.min.js');
        wp_enqueue_style('select2', TRCPDD_URL . 'css/select2.min.css', array(), TRCPDD_VS);
        wp_enqueue_style('admin-dashboard', TRCPDD_URL . 'css/admin_dashboard.css', array(), filemtime(TRSCSC_PATH . 'css/admin_dashboard.css'));
        wp_enqueue_style('req-form', TRCPDD_URL . 'templates/req-form.css', array(), filemtime(TRSCSC_PATH . 'css/req-form.css'));
        wp_enqueue_script('signature-pad', TRCPDD_URL . 'js/signature_pad.min.js', array(), TRCPDD_VS, false);

        // Render form template
        ob_start();
        include TRCPDD_PATH . 'templates/req-form.php';
        echo ob_get_clean();
        exit;
    }

    /**
     * Allow DrChrono to embed this page in iFrame
     */
    public function maybe_allow_iframe(): void
    {
        $slug = self::SLUG_PAGE;
        if (!is_page($slug)) {
            return;
        }
        $site_url = home_url();
        $parsed   = parse_url($site_url);
        $site_origin = $parsed['scheme'] . '://' . $parsed['host'];
        if (!empty($parsed['port'])) {
            $site_origin .= ':' . $parsed['port'];
        }

        header_remove('X-Frame-Options');

        $csp = "frame-ancestors 'self' {$site_origin} "
            . "https://app.drchrono.com https://drchrono.com https://*.drchrono.com "
            . "http://localhost:* https://localhost:* "
            . "https://*.ngrok-free.app https://*.ngrok.io "
            . "https://*.local";

        header("Content-Security-Policy: {$csp}", true);
        add_filter('show_admin_bar', '__return_false');
    }


    public function on_req_saved(string $btype, int $batch_id, int $drchrono_patient_id, int $drchrono_doctor_id, array $insert): void
    {
        global $wpdb;

        if (!$drchrono_patient_id || !$drchrono_doctor_id) {
            $this->log("on_req_saved: missing drchrono IDs for batch {$batch_id} and btype {$btype}");
            return;
        }

        // ICD-10
        $icd10_codes = [];
        foreach (explode(',', $insert['dagnostic_codes1'] ?? '') as $code) {
            $code = trim($code);
            if ($code) $icd10_codes[] = ['code' => $code];
        }

        // Lấy test IDs
        $test_ids = array_filter(
            array_map('intval', explode(',', $insert['panel_tests_requested'] ?? ''))
        );

        if (empty($test_ids)) {
            $this->log("on_req_saved: no tests for batch {$batch_id} and btype {$btype}");
            return;
        }

        // Query ci_test_panels
        $placeholders = implode(',', array_fill(0, count($test_ids), '%d'));
        $panels = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT te_ID, te_name, te_abrev, te_abrev2, te_loinc_code
             FROM ci_test_panels
             WHERE te_ID IN ({$placeholders}) AND te_type = %s",
                ...[...$test_ids, $btype]
            ),
            ARRAY_A
        );

        if (empty($panels)) {
            $this->log("on_req_saved: no panels found for batch {$batch_id} and btype {$btype}");
            return;
        }

        // Tạo order
        $order = $this->create_order(
            $drchrono_doctor_id,
            $drchrono_patient_id,
            $icd10_codes,
            $insert['notes'] ?? '',
            !empty($insert['priority_hours']) ? 'S' : 'N',
        );

        if (empty($order['id'])) {
            $this->log("on_req_saved: failed to create order for batch {$batch_id} and btype {$btype}");
            return;
        }

        $order_id = (int)$order['id'];

        $specimen = match ($btype) {
            'tox'   => 'Urine',
            'pcr'   => 'Swab',
            default => 'Blood',
        };

        $tests_data = array_map(function ($p) use ($specimen) {
            return [
                'code'                  => $p['te_abrev'] ?: $p['te_abrev2'] ?: $p['te_name'],
                'name'                  => $p['te_name'] ?: $p['te_abrev'] ?: $p['te_abrev2'],
                'description'           => $p['te_name'],
                'collection_date'       => current_time('c'),
                'status'                => 'P',
                'specimen_source'       => $specimen,
                'specimen_condition'    => '_',
                'specimen_total_volume' => 0,
                'internal_notes'        => '',
                'report_notes'          => '',
            ];
        }, $panels);

        $this->create_lab_tests($order_id, $tests_data);

        $table = "ci_batches_{$btype}_meta";
        if ($btype === 'tox') {
            $table = "ci_batches_meta";
        }

        $wpdb->insert(
            $table,
            ['meta_key' => 'drchrono_order_id', 'meta_value' => $order_id, 'mid' => $batch_id],
            ['%s', '%d', '%d']
        );

        $this->log("on_req_saved: order {$order_id} created for batch {$batch_id} and btype {$btype}");
    }

    public function handle_form_submit(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (strpos($_SERVER['REQUEST_URI'], '/lab-requisition/') !== 0) {
            return;
        }

        $action = sanitize_text_field($_POST['lab_action'] ?? '');
        if (!$action) {
            return;
        }

        switch ($action) {
            case 'pcr_save_req_form':
                require_once TRCPDD_PATH . 'templates/pcr-req-form.php';
                break;

            case 'blood_save_req_form':
                require_once TRCPDD_PATH . 'templates/blood-req-form.php';
                break;

            case 'tox_save_req_form':
                require_once TRCPDD_PATH . 'templates/tox-req-form.php';
                break;
            case 'load_clinic_doctors':
                $this->load_get_clinic_doctors_handler();
                break;

            default:
                wp_send_json_error(['message' => 'Unknown action'], 400);
                exit;
        }
    }

    public function load_get_clinic_doctors_handler(): void
    {
        global $wpdb;
        if (!check_ajax_referer('drchrono_lab_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed.'], 403);
        }
        $cli_id = (int)($_POST['cli_id'] ?? 0);
        if ($cli_id < 1) {
            wp_send_json_success([]);
        }

        $doctors = $wpdb->get_results($wpdb->prepare(
            "SELECT doc.doc_ID, doc.doc_first, doc.doc_last, doc.doc_npi, doc.doc_signature,
                cp.cus_individuals, cp.cus_med_risk, cp.cus_high_risk, cp.cus_ID, cp.cus_df_profile
           FROM ci_doc doc
           JOIN ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
           LEFT JOIN ci_custom_providers cp ON doc.doc_ID = cp.doc_ID
               AND cp.cus_type = 'tox'
               AND (cp.cli_ID = %d OR (
                   SELECT COUNT(*) FROM ci_custom_providers cp2
                   WHERE cp2.doc_ID = doc.doc_ID AND cp2.cli_ID = %d AND cp2.cus_type = 'tox'
               ) = 0)
          WHERE cd.cli_ID = %d
          GROUP BY doc.doc_ID
          ORDER BY doc.doc_last ASC",
            $cli_id,
            $cli_id,
            $cli_id
        ), ARRAY_A);

        $show_npi = tr_get_option('ci_pdf_data', 'req_show_npi');
        $out = [];
        foreach ($doctors as $d) {
            $label = trim($d['doc_last'] . ', ' . $d['doc_first']);
            if ($show_npi && !empty($d['doc_npi'])) {
                $label .= ' - ' . $d['doc_npi'];
            }
            $out[] = [
                'doc_ID'         => (int)$d['doc_ID'],
                'label'          => $label,
                'cus_individuals' => $d['cus_individuals'] ?? '',
                'cus_med_risk'   => $d['cus_med_risk']    ?? '',
                'cus_high_risk'  => $d['cus_high_risk']   ?? '',
                'cus_df_profile' => $d['cus_df_profile']  ?? '',
                'doc_signature'  => $d['doc_signature']   ?? '',
            ];
        }
        wp_send_json_success($out);
        exit;
    }

    protected function forward_to_lis(int $order_id, int $doctor_id, int $patient_id, array $tests, string $notes = ''): void
    {
        $lis_url = $this->get_option('lis_endpoint_url');
        if (!$lis_url) {
            $this->log('forward_to_lis: LIS endpoint URL not configured');
            return;
        }

        $lis_secret = $this->get_option('lis_secret');

        wp_remote_post($lis_url, [
            'timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-LIS-Auth'   => $lis_secret,
            ],
            'body' => wp_json_encode([
                'drchrono_order_id' => $order_id,
                'doctor_id'         => $doctor_id,
                'patient_id'        => $patient_id,
                'tests'             => $tests,
                'notes'             => $notes,
                'callback_url'      => rest_url(self::REST_NAMESPACE . '/result'),
            ]),
        ]);
    }

    public function rest_verify_lis(WP_REST_Request $request): bool
    {
        $lis_secret = $this->get_option('lis_secret');

        // Allow if secret not configured (dev mode)
        if (!$lis_secret) return true;

        $header = $request->get_header('X-LIS-Auth');
        return hash_equals($lis_secret, (string)$header);
    }

    private function download_temp_file(string $url, string $filename): string
    {
        $response = wp_remote_get($url, ['timeout' => 30]);
        if (is_wp_error($response)) return '';

        $path = sys_get_temp_dir() . '/' . sanitize_file_name($filename);
        file_put_contents($path, wp_remote_retrieve_body($response));
        return $path;
    }

    private function log(string $message): void
    {
        $log_dir  = WP_CONTENT_DIR . '/log';
        $log_file = $log_dir . '/ci_drchrono.log';

        if (!is_dir($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        $time = current_time('Y-m-d H:i:s');
        $formatted_message = "[{$time}] {$message}" . PHP_EOL;

        file_put_contents($log_file, $formatted_message, FILE_APPEND | LOCK_EX);
    }
}
