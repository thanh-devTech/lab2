<?php

/**
 * Class CI_Api_Tebra
 * Tebra (Kareo) SOAP API 2.1 — CreatePatient + CreateEncounter (billing)
 */
class CI_Api_Tebra
{
    public $log_file_slug = 'tebra';
    public $log_dir       = WP_CONTENT_DIR . '/log/ci_api_tebra/';

    var $wsdl_url = 'https://webservice.kareo.com/services/soap/2.1/KareoServices.svc?singleWsdl';

    public $debug      = false;
    public $all_btypes = array('pcr');

    var $api_id  = -90;
    var $options = array();

    /** @var SoapClient|null */
    protected $client = null;

    public function __construct()
    {
        $this->get_options();
    }

    public function get_options()
    {
        $this->options = get_option('api_tebra_st', array());
    }

    public function set_option($k, $vl)
    {
        $this->options[$k] = $vl;
        update_option('api_tebra_st', $this->options);
    }

    // ─── Cron entry point ──────────────────────────────────────────────────────

    public function cron()
    {
        $lock_key = 'tebra_cron_lock';
        if (get_transient($lock_key)) {
            $this->putlog_cron('cron_skipped', 'Previous cron still running, skip this run');
            return array('skipped' => true);
        }
        set_transient($lock_key, 1, 5 * MINUTE_IN_SECONDS);

        try {
            $this->putlog_cron('cron_start', 'Tebra cron started');
            $patient_result   = $this->process_pending_patients();
            $encounter_result = $this->process_pending_encounters();
            $this->putlog_cron('cron_end', array(
                'patients_processed'   => $patient_result,
                'encounters_processed' => $encounter_result,
            ));
            return array('patients' => $patient_result, 'encounters' => $encounter_result);
        } finally {
            delete_transient($lock_key);
        }
    }

    // ─── SOAP client ───────────────────────────────────────────────────────────

    protected function get_client()
    {
        if ($this->client === null) {
            try {
                $this->client = new SoapClient($this->wsdl_url, array(
                    'trace'              => true,
                    'exceptions'         => true,
                    'cache_wsdl'         => WSDL_CACHE_DISK,
                    'connection_timeout' => 30,
                ));
            } catch (SoapFault $e) {
                $this->putlog(array('error' => $e->getMessage()), 'soap_init_error', 'system');
                throw $e;
            }
        }
        return $this->client;
    }

    protected function build_request_header()
    {
        return array(
            'CustomerKey' => $this->options['customer_key'] ?? '',
            'User'        => $this->options['api_user'] ?? '',
            'Password'    => $this->options['api_password'] ?? '',
        );
    }

    public function putlog($msg, $type, $identifier)
    {
        $this->append_log_line('requests', array(
            'time'       => date_i18n('Y-m-d H:i:s'),
            'type'       => $type,
            'identifier' => $identifier,
            'message'    => $msg,
        ));
    }

    /**
     * Log riêng cho cron run (tổng quan, không phải từng request/response)
     * File: cron-YYYY-MM-DD.log (1 dòng JSON / event)
     */
    public function putlog_cron($type, $msg)
    {
        $this->append_log_line('cron', array(
            'time'    => date_i18n('Y-m-d H:i:s'),
            'type'    => $type,
            'message' => $msg,
        ));
    }

    /**
     * @param string $prefix
     * @param array  $entry
     */
    protected function append_log_line($prefix, $entry)
    {
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
        }

        $filepath = $this->log_dir . $prefix . '-' . date_i18n('Y-m-d') . '.log';
        $line     = wp_json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n";

        file_put_contents($filepath, $line, FILE_APPEND | LOCK_EX);
    }

    public function read_log($prefix, $date = null, $identifier = null, $type = null, $limit = 200)
    {
        $date     = $date ?: date_i18n('Y-m-d');
        $filepath = $this->log_dir . $prefix . '-' . $date . '.log';

        if (!file_exists($filepath)) {
            return array();
        }

        $results = array();
        $handle  = fopen($filepath, 'r');
        if ($handle === false) {
            return array();
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') continue;

            $decoded = json_decode($line, true);
            if (!is_array($decoded)) continue;

            if ($identifier !== null && (string)($decoded['identifier'] ?? '') !== (string)$identifier) {
                continue;
            }
            if ($type !== null && ($decoded['type'] ?? '') !== $type) {
                continue;
            }

            $results[] = $decoded;
        }
        fclose($handle);

        $results = array_reverse($results);
        if ($limit > 0) {
            $results = array_slice($results, 0, $limit);
        }

        return $results;
    }

    public function list_log_dates($prefix = 'requests')
    {
        if (!file_exists($this->log_dir)) {
            return array();
        }
        $files = glob($this->log_dir . $prefix . '-*.log');
        $dates = array();
        foreach ($files as $f) {
            if (preg_match('/' . preg_quote($prefix, '/') . '-(\d{4}-\d{2}-\d{2})\.log$/', basename($f), $m)) {
                $dates[] = $m[1];
            }
        }
        rsort($dates);
        return $dates;
    }

    // ─── Meta helpers ({$batch_tbl}_meta) ──────────────────────────────────────

    protected function get_meta($batch_tbl, $mid, $meta_key, $default = null)
    {
        global $wpdb;
        $val = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$batch_tbl}_meta WHERE mid = %d AND meta_key = %s LIMIT 1",
            $mid,
            $meta_key
        ));
        return $val !== null ? $val : $default;
    }

    protected function set_meta($batch_tbl, $mid, $meta_key, $meta_value)
    {
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT mid FROM {$batch_tbl}_meta WHERE mid = %d AND meta_key = %s LIMIT 1",
            $mid,
            $meta_key
        ));

        if ($exists !== null) {
            $wpdb->update(
                "{$batch_tbl}_meta",
                array('meta_value' => $meta_value),
                array('mid' => $mid, 'meta_key' => $meta_key)
            );
        } else {
            $wpdb->insert(
                "{$batch_tbl}_meta",
                array(
                    'mid'        => $mid,
                    'meta_key'   => $meta_key,
                    'meta_value' => $meta_value,
                )
            );
        }
    }

    protected function delete_meta($batch_tbl, $mid, $meta_key)
    {
        global $wpdb;
        $wpdb->delete("{$batch_tbl}_meta", array('mid' => $mid, 'meta_key' => $meta_key));
    }

    // ─── 1) CREATE PATIENT — batch processing ──────────────────────────────────

    public function process_pending_patients($limit = 10)
    {
        global $wpdb;
        $processed = array('sent' => 0, 'failed' => 0, 'skipped' => 0);

        foreach ($this->all_btypes as $btype) {
            $batch_tbl = tr_pdd_get_table_by_btype($btype);

            $sql = "SELECT ba.*, lab.lab_name, doc.*, pa.*, cli.*
                    FROM {$batch_tbl} ba
                    JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
                    JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
                    JOIN ci_doc doc ON doc.doc_ID = ba.doc_ID
                    JOIN ci_clinic cli ON cli.cID = ba.cli_ID
                    LEFT JOIN {$batch_tbl}_meta m
                           ON m.mid = ba.mid AND m.meta_key = '_tebra_patient_id'
                    WHERE ba.batch_id > 0
                      AND ba.patient_id > 0
                      AND m.mid IS NULL
                    ORDER BY ba.mid DESC
                    LIMIT {$limit}";

            $rows = $wpdb->get_results($sql);

            if (empty($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                $result = $this->create_patient($batch_tbl, $row);
                if (!empty($result['success'])) {
                    $processed['sent']++;
                } else {
                    $processed['failed']++;
                }
            }
        }

        return $processed;
    }

    public function create_patient($batch_tbl, $row)
    {
        $identifier = $row->assen ?? $row->mid;

        try {
            $client = $this->get_client();

            $patient_create = array(
                'FirstName'         => $row->pa_first ?? '',
                'LastName'          => $row->pa_last ?? '',
                'MiddleName'        => $row->pa_middle ?? '',
                'DateofBirth'       => $this->to_date($row->pa_dob ?? ''),
                'Gender'            => $this->map_gender_code($row->pa_gender ?? ''),
                'AddressLine1'      => $row->pa_address ?? '',
                'City'              => $row->pa_city ?? '',
                'State'             => $row->pa_state ?? '',
                'ZipCode'           => $row->pa_zip ?? '',
                'HomePhone'         => $row->pa_phone ?? '',
                'EmailAddress'      => $row->pa_email ?? '',
                'PatientExternalID' => (string) $row->patient_id,
                'Practice'          => array(
                    'PracticeID' => (int) ($this->options['practice_id'] ?? 0),
                ),
                'Cases' => array(
                    'PatientCaseCreateReq' => array(
                        array(
                            'Active'   => true,
                            'CaseName' => 'Default Case',
                            'ExternalID' => (string) $row->mid,
                            'SendPatientStatements' => false,
                        ),
                    ),
                ),
            );

            $request = array(
                'RequestHeader' => $this->build_request_header(),
                'Patient'       => $patient_create,
            );

            $this->putlog(array('request' => $this->redact($request)), 'create_patient_request', $identifier);

            $response = $client->CreatePatient(array('request' => $request));
            $result   = $response->CreatePatientResult ?? $response;

            $this->putlog($this->to_loggable($result), 'create_patient_response', $identifier);

            if (!empty($result->ErrorResponse->IsError)) {
                $err = $result->ErrorResponse->ErrorMessage ?? 'Unknown Tebra error';
                $this->set_meta($batch_tbl, $row->mid, '_tebra_status', 'patient_failed');
                $this->set_meta($batch_tbl, $row->mid, '_tebra_error', $err);
                return array('success' => false, 'patient_id' => null, 'error' => $err);
            }

            $patient_id = $result->PatientID ?? null;

            if (empty($patient_id)) {
                $err = 'No PatientID in response';
                $this->set_meta($batch_tbl, $row->mid, '_tebra_status', 'patient_failed');
                $this->set_meta($batch_tbl, $row->mid, '_tebra_error', $err);
                return array('success' => false, 'patient_id' => null, 'error' => $err);
            }

            $case_id = null;
            $cases = $result->Cases->PatientCaseRes ?? null;
            if (!empty($cases)) {
                $first_case = is_array($cases) ? $cases[0] : $cases;
                $case_id = $first_case->CaseID ?? null;
            }

            $this->set_meta($batch_tbl, $row->mid, '_tebra_patient_id', $patient_id);
            if (!empty($case_id)) {
                $this->set_meta($batch_tbl, $row->mid, '_tebra_case_id', $case_id);
            } else {
                $err = 'Missing CaseID in Tebra response';
                $this->set_meta($batch_tbl, $row->mid, '_tebra_status', 'patient_failed');
                $this->set_meta($batch_tbl, $row->mid, '_tebra_error', $err);
                return array('success' => false, 'patient_id' => $patient_id, 'case_id' => null, 'error' => $err);
            }
            $this->set_meta($batch_tbl, $row->mid, '_tebra_status', 'patient_sent');
            $this->set_meta($batch_tbl, $row->mid, '_tebra_patient_sent_dt', date_i18n('Y-m-d H:i:s'));
            $this->delete_meta($batch_tbl, $row->mid, '_tebra_error');

            return array('success' => true, 'patient_id' => $patient_id, 'case_id' => $case_id, 'error' => null);
        } catch (SoapFault $e) {
            $err = $e->getMessage();
            $this->putlog(array('error' => $err), 'create_patient_error', $identifier);
            $this->set_meta($batch_tbl, $row->mid, '_tebra_status', 'patient_failed');
            $this->set_meta($batch_tbl, $row->mid, '_tebra_error', $err);
            return array('success' => false, 'patient_id' => null, 'error' => $err);
        }
    }

    // ─── 2) CREATE ENCOUNTER — batch processing ────────────────────────────────

    public function process_pending_encounters($limit = 10)
    {
        global $wpdb;
        $processed = array('sent' => 0, 'failed' => 0, 'skipped' => 0);

        foreach ($this->all_btypes as $btype) {
            $batch_tbl  = tr_pdd_get_table_by_btype($btype);
            $report_tbl = tr_pdd_get_table_report_by_btype($btype);

            $sql = "SELECT ba.*, re.estatus, re.id as report_id,
                    mp.meta_value as tebra_patient_id,
                    mc.meta_value as tebra_case_id,
                    ins1.ins_company as tebra_ins_company
                FROM {$batch_tbl} ba
                JOIN {$report_tbl} re ON re.patient_id = ba.assen
                JOIN {$batch_tbl}_meta mp
                    ON mp.mid = ba.mid AND mp.meta_key = '_tebra_patient_id'
                LEFT JOIN {$batch_tbl}_meta mc
                    ON mc.mid = ba.mid AND mc.meta_key = '_tebra_case_id'
                LEFT JOIN {$batch_tbl}_meta me
                    ON me.mid = ba.mid AND me.meta_key = '_tebra_encounter_id'
                LEFT JOIN ci_insurances ins1 ON ins1.ins_ID = ba.insurance1
                WHERE re.estatus = 'C'
                AND me.mid IS NULL
                ORDER BY ba.mid DESC
                LIMIT {$limit}";

            $rows = $wpdb->get_results($sql);

            if (empty($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                $result = $this->create_encounter($batch_tbl, $row);
                if (!empty($result['success'])) {
                    $processed['sent']++;
                } else {
                    $processed['failed']++;
                }
            }
        }

        return $processed;
    }

    /**
     * Call CreateEncounter for 1 batch row.
     *
     * @param string $batch_tbl
     * @param object $row  (tebra_patient_id, dagnostic_codes1, report_id, ...)
     * @return array ['success' => bool, 'encounter_id' => string|null, 'error' => string|null]
     */
    public function create_encounter($batch_tbl, $row)
    {
        $identifier = $row->assen ?? $row->mid;

        try {
            $client = $this->get_client();
            $service_date = $this->to_date($row->spec_received ?? $row->batch_created ?? '');

            $icd_codes = array_filter(array_map('trim', explode(',', $row->dagnostic_codes1 ?? '')));
            $icd_codes = array_values($icd_codes);
            $icd1 = $icd_codes[0] ?? '';
            $icd2 = $icd_codes[1] ?? '';
            $icd3 = $icd_codes[2] ?? '';
            $icd4 = $icd_codes[3] ?? '';

            if (empty($row->tebra_case_id)) {
                $err = 'Missing Tebra case_id for this patient';
                $this->putlog(array('error' => $err), 'create_encounter_error', $identifier);
                $this->set_meta($batch_tbl, $row->mid, '_tebra_status', 'encounter_failed');
                $this->set_meta($batch_tbl, $row->mid, '_tebra_error', $err);
                return array('success' => false, 'encounter_id' => null, 'error' => $err);
            }

            $procedure_codes = $this->get_procedure_codes($row, $service_date);

            if (empty($procedure_codes)) {
                $err = 'No billing/CPT code resolved for this batch';
                $this->putlog(array('error' => $err), 'create_encounter_error', $identifier);
                $this->set_meta($batch_tbl, $row->mid, '_tebra_status', 'encounter_failed');
                $this->set_meta($batch_tbl, $row->mid, '_tebra_error', $err);
                return array('success' => false, 'encounter_id' => null, 'error' => $err);
            }

            $service_lines = array();
            foreach ($procedure_codes as $pc) {
                $service_lines[] = array(
                    'ProcedureCode'    => $pc['code'],
                    'DiagnosisCode1'   => $icd1,
                    'DiagnosisCode2'   => $icd2,
                    'DiagnosisCode3'   => $icd3,
                    'DiagnosisCode4'   => $icd4,
                    'Units'            => $pc['units'],
                    'ServiceStartDate' => $service_date,
                    'ServiceEndDate'   => $service_date,
                );
            }

            $encounter_create = array(
                'Patient' => array(
                    'PatientID' => (int) $row->tebra_patient_id,
                ),
                'Practice' => array(
                    'PracticeID' => (int) ($this->options['practice_id'] ?? 0),
                ),
                'Case' => array(
                    'CaseID' => (int) $row->tebra_case_id,
                ),
                'ServiceLocation' => array(
                    'LocationID' => (int) ($this->options['service_location_id'] ?? 0),
                ),
                'RenderingProvider' => array(
                    'ProviderID' => (int) ($this->options['rendering_provider_id'] ?? 0),
                ),
                'ServiceStartDate'    => $service_date,
                'ServiceEndDate'      => $service_date,
                'ServiceLines'        => array('ServiceLineReq' => $service_lines),
                'EncounterExternalID' => (string) $row->mid,
            );

            $request = array(
                'RequestHeader' => $this->build_request_header(),
                'Encounter'     => $encounter_create,
            );

            $this->putlog(array('request' => $this->redact($request)), 'create_encounter_request', $identifier);

            $response = $client->CreateEncounter(array('request' => $request));
            $result   = $response->CreateEncounterResult ?? $response;

            $this->putlog($this->to_loggable($result), 'create_encounter_response', $identifier);

            if (!empty($result->ErrorResponse->IsError)) {
                $err = $result->ErrorResponse->ErrorMessage ?? 'Unknown Tebra error';
                $this->set_meta($batch_tbl, $row->mid, '_tebra_status', 'encounter_failed');
                $this->set_meta($batch_tbl, $row->mid, '_tebra_error', $err);
                return array('success' => false, 'encounter_id' => null, 'error' => $err);
            }

            $encounter_id = $result->EncounterID ?? null;

            if (empty($encounter_id)) {
                $err = 'No EncounterID in response';
                $this->set_meta($batch_tbl, $row->mid, '_tebra_status', 'encounter_failed');
                $this->set_meta($batch_tbl, $row->mid, '_tebra_error', $err);
                return array('success' => false, 'encounter_id' => null, 'error' => $err);
            }

            $this->set_meta($batch_tbl, $row->mid, '_tebra_encounter_id', $encounter_id);
            $this->set_meta($batch_tbl, $row->mid, '_tebra_status', 'encounter_sent');
            $this->set_meta($batch_tbl, $row->mid, '_tebra_encounter_sent_dt', date_i18n('Y-m-d H:i:s'));
            $this->delete_meta($batch_tbl, $row->mid, '_tebra_error');

            return array('success' => true, 'encounter_id' => $encounter_id, 'error' => null);
        } catch (SoapFault $e) {
            $err = $e->getMessage();
            $this->putlog(array('error' => $err), 'create_encounter_error', $identifier);
            $this->set_meta($batch_tbl, $row->mid, '_tebra_status', 'encounter_failed');
            $this->set_meta($batch_tbl, $row->mid, '_tebra_error', $err);
            return array('success' => false, 'encounter_id' => null, 'error' => $err);
        }
    }

    protected function get_procedure_codes($row, $service_date)
    {
        $bat = (array) $row;
        $bat['btype']                     = 'pcr';
        $bat['mid']                       = $row->mid;
        $bat['assen']                     = $row->assen;
        $bat['cli_ID']                    = $row->cli_ID ?? 0;
        $bat['ref_lab_ID']                = $row->ref_lab_ID ?? 0;
        $bat['doc']                       = $service_date;
        $bat['insurance']                 = $row->insurance ?? '';
        $bat['panel_tests_requested']     = isset($row->panel_tests_requested)
            ? array_map('trim', explode(',', $row->panel_tests_requested))
            : array();
        $bat['panel_tests_requested_str'] = $row->panel_tests_requested ?? '';
        $bat['withtimes']                 = true;

        $insurance_company = $row->tebra_ins_company ?? '';

        $codes_str = tr_bill_get_billing_code($insurance_company, $row->report_id, $bat, true);

        if (empty($codes_str)) {
            return array();
        }

        $codes = array_filter(array_map('trim', explode(',', $codes_str)));
        $result = array();

        foreach ($codes as $c) {
            $units = 1.0;
            $code  = $c;
            if (preg_match('/^(\d+)x(.+)$/i', $c, $m)) {
                $units = (float) $m[1];
                $code  = $m[2];
            }
            if ($code === '') continue;
            $result[] = array('code' => $code, 'units' => $units);
        }

        return $result;
    }

    // ─── Manual resend ──────────────────────

    public function resend_patient($mid)
    {
        global $wpdb;
        foreach ($this->all_btypes as $btype) {
            $batch_tbl = tr_pdd_get_table_by_btype($btype);
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT ba.*, lab.lab_name, doc.*, pa.*, cli.*
                 FROM {$batch_tbl} ba
                 JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
                 JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
                 JOIN ci_doc doc ON doc.doc_ID = ba.doc_ID
                 JOIN ci_clinic cli ON cli.cID = ba.cli_ID
                 WHERE ba.mid = %d",
                $mid
            ));
            if (!empty($row)) {
                return $this->create_patient($batch_tbl, $row);
            }
        }
        return array('success' => false, 'patient_id' => null, 'error' => 'Batch not found');
    }

    public function resend_encounter($mid)
    {
        global $wpdb;
        foreach ($this->all_btypes as $btype) {
            $batch_tbl  = tr_pdd_get_table_by_btype($btype);
            $report_tbl = tr_pdd_get_table_report_by_btype($btype);
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT ba.*, re.estatus, re.id as report_id,
                        mp.meta_value as tebra_patient_id,
                        mc.meta_value as tebra_case_id,
                        ins1.ins_company as tebra_ins_company
                FROM {$batch_tbl} ba
                JOIN {$report_tbl} re ON re.patient_id = ba.assen
                LEFT JOIN {$batch_tbl}_meta mp
                    ON mp.mid = ba.mid AND mp.meta_key = '_tebra_patient_id'
                LEFT JOIN {$batch_tbl}_meta mc
                    ON mc.mid = ba.mid AND mc.meta_key = '_tebra_case_id'
                LEFT JOIN ci_insurances ins1 ON ins1.ins_ID = ba.insurance1
                WHERE ba.mid = %d",
                $mid
            ));
            if (!empty($row)) {
                if (empty($row->tebra_patient_id)) {
                    return array('success' => false, 'encounter_id' => null, 'error' => 'Not found tebra patient');
                }
                return $this->create_encounter($batch_tbl, $row);
            }
        }
        return array('success' => false, 'encounter_id' => null, 'error' => 'Batch not found');
    }

    public function get_service_locations()
    {
        try {
            $client = $this->get_client();

            $request = array(
                'RequestHeader' => $this->build_request_header(),
                'Fields' => array(
                    'ID'          => true,
                    'Name'        => true,
                    'PracticeID'  => true,
                    'PracticeName' => true,
                    'City'        => true,
                    'State'       => true,
                ),
                'Filter' => array(
                    'PracticeID' => (string) ($this->options['practice_id'] ?? ''),
                ),
            );

            $this->putlog(array('request' => $this->redact(array('RequestHeader' => $request['RequestHeader']))), 'get_service_locations_request', 'system');

            $response = $client->GetServiceLocations(array('request' => $request));
            $result   = $response->GetServiceLocationsResult ?? $response;

            $this->putlog($this->to_loggable($result), 'get_service_locations_response', 'system');

            return $this->to_loggable($result);
        } catch (SoapFault $e) {
            $err = $e->getMessage();
            $this->putlog(array('error' => $err), 'get_service_locations_error', 'system');
            return array('error' => $err);
        }
    }

    public function get_providers()
    {
        try {
            $client = $this->get_client();

            $request = array(
                'RequestHeader' => $this->build_request_header(),
                'Fields' => array(
                    'ID'       => true,
                    'FullName' => true,
                    'FirstName' => true,
                    'LastName' => true,
                    'PracticeID' => true,
                    'PracticeName' => true,
                    'Type'     => true,
                    'Active'   => true,
                ),
                'Filter' => array(
                    'PracticeID' => (string) ($this->options['practice_id'] ?? ''),
                ),
            );

            $this->putlog(array('request' => $this->redact(array('RequestHeader' => $request['RequestHeader']))), 'get_providers_request', 'system');

            $response = $client->GetProviders(array('request' => $request));
            $result   = $response->GetProvidersResult ?? $response;

            $this->putlog($this->to_loggable($result), 'get_providers_response', 'system');

            return $this->to_loggable($result);
        } catch (SoapFault $e) {
            $err = $e->getMessage();
            $this->putlog(array('error' => $err), 'get_providers_error', 'system');
            return array('error' => $err);
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    protected function to_date($date_str)
    {
        if (empty($date_str)) return null;
        $ts = strtotime($date_str);
        return $ts ? date_i18n('Y-m-d', $ts) : null;
    }

    protected function map_gender_code($g)
    {
        $g = strtoupper(substr((string) $g, 0, 1));
        if ($g === 'M') return 'Male';
        if ($g === 'F') return 'Female';
        return 'Unknown';
    }

    protected function redact($request)
    {
        $copy = $request;
        if (isset($copy['RequestHeader']['Password'])) {
            $copy['RequestHeader']['Password'] = '***';
        }
        if (isset($copy['RequestHeader']['CustomerKey'])) {
            $copy['RequestHeader']['CustomerKey'] = '***';
        }
        return $copy;
    }

    protected function to_loggable($obj)
    {
        return json_decode(json_encode($obj), true);
    }
}
