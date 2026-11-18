<?php

/**
 * Flow:
 *   1. Verify nonce
 *   2. Sanitise & normalise input
 *   3. Create / find Doctor  → doc_ID
 *   4. Create / find Patient → patient_id
 *   5. Create / find Insurance records
 *   6. Insert ci_batches_pcr row (always new – add-only form)
 *   7. Return JSON success / error
 */

global $wpdb;

/* ── 1. Nonce ─────────────────────────────────────────────── */
if (!check_ajax_referer('drchrono_lab_nonce', 'nonce', false)) {
    wp_send_json_error(['message' => 'Security check failed.'], 403);
}

/* ── 2. Raw input ─────────────────────────────────────────── */
// Strip slashes added by WordPress magic-quotes
$_POST = function_exists('tr_pdd_stripslashes_deep')
    ? tr_pdd_stripslashes_deep($_POST)
    : wp_unslash($_POST);

$btype     = 'pcr';
$table_bat = 'ci_batches_pcr';

$batch = isset($_POST['bat'])  && is_array($_POST['bat'])  ? $_POST['bat']  : [];
$batmeta_raw = isset($_POST['batmeta']) && is_array($_POST['batmeta']) ? $_POST['batmeta'] : [];
$pa    = isset($_POST['pa'])   && is_array($_POST['pa'])   ? $_POST['pa']   : [];
$ins1  = isset($_POST['ins1']) && is_array($_POST['ins1']) ? $_POST['ins1'] : [];
$doc   = isset($_POST['doc'])  && is_array($_POST['doc'])  ? $_POST['doc']  : [];
$patient_signature = $_POST['patient_signature'];
$provider_signature = $_POST['provider_signature'];
$drchrono_patient_id = $_POST['drchrono_patient_id'] ?? '';
$drchrono_doctor_id = $_POST['drchrono_doctor_id'] ?? '';

// Restore textarea fields (allow newlines, basic HTML stripped)
foreach (['notes', 'notes_report', 'internal_notes'] as $f) {
    if (isset($_POST['bat'][$f])) {
        $batch[$f] = sanitize_textarea_field(wp_unslash($_POST['bat'][$f]));
    }
}

if (empty($batch['doc']) || $batch['doc'] === 'auto') {
    $batch['doc'] = date_i18n('Y-m-d H:i:s');
} else {
    $ts = strtotime($batch['doc']);
    $batch['doc'] = $ts ? date('Y-m-d H:i:s', $ts) : date_i18n('Y-m-d H:i:s');
}

$signature_id = trim(strtolower($batch['assen']));
if (empty($signature_id)) {
    $signature_id = 'batch_' . time();
}

//patient signature
$patient_sign = tr_save_signature($signature_id . '_patient', $patient_signature, $_FILES['patient_signature']);
if ($patient_sign) {
    $batch['patient_signature'] = $patient_sign;
} else if (isset($_REQUEST['onfile'])) {
    $batch['patient_signature'] = 'onfile';
} else if (isset($_REQUEST['img_patient_signature'])) {
    $batch['patient_signature'] = $_REQUEST['img_patient_signature'];
}

//provider signature
$provider_sign = tr_save_signature($signature_id . '_provider', $provider_signature, $_FILES['provider_signature']);
if ($provider_sign) {
    $batch['provider_signature'] = $provider_sign;
} else if (isset($_REQUEST['img_provider_signature'])) {
    $batch['provider_signature'] = $_REQUEST['img_provider_signature'];
}


/* ── 5. Type of specimen ──────────────────────────────────── */
if (isset($batch['type_of_specimen'])) {
    if (!is_array($batch['type_of_specimen'])) {
        $batch['type_of_specimen'] = [];
    }
    $batch['type_of_specimen'] = implode(',', array_unique(
        array_map('sanitize_text_field', $batch['type_of_specimen'])
    ));
}

/* ── 6. Panel tests ───────────────────────────────────────── */
if (isset($batch['panel_tests_requested'])) {
    if (!is_array($batch['panel_tests_requested'])) {
        $batch['panel_tests_requested'] = [];
    }

    $batch['panel_tests_requested'] = array_unique($batch['panel_tests_requested']);
    $batch['panel_tests_requested'] = implode(',', $batch['panel_tests_requested']);
}

/* ── 7. Diagnostic codes ──────────────────────────────────── */
if (isset($batch['dagnostic_codes'])) {
    $batch['dagnostic_codes1'] = implode(',', (array)$batch['dagnostic_codes']);
    unset($batch['dagnostic_codes']);
}

/* ── 8. Lab ID ────────────────────────────────────────────── */
if (empty($batch['lab_id']) || $batch['lab_id'] < 1) {
    if (!empty($batch['cli_ID']) && (int)$batch['cli_ID'] > 0) {
        $batch['lab_id'] = function_exists('tr_get_lab_id_by_clinic')
            ? (int) tr_get_lab_id_by_clinic((int)$batch['cli_ID'])
            : 1;
    }
    if (empty($batch['lab_id']) || $batch['lab_id'] < 1) {
        $batch['lab_id'] = function_exists('CI_Base_Lab')
            ? (int) CI_Base_Lab::get_current_lab_id()
            : 1;
    }
}
if ($batch['lab_id'] > 0) {
    $batch['lab'] = function_exists('tr_get_lab_short')
        ? tr_get_lab_short((int)$batch['lab_id'])
        : '';
}

/* ── 9. Clinic → requesting practice name ─────────────────── */
$cli_id = (int)($batch['cli_ID'] ?? 0);
if ($cli_id > 0) {
    $cli = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM ci_clinic WHERE cID = %d",
        $cli_id
    ));
    if ($cli) {
        $batch['requesting_practice'] = ucwords($cli->cli_clinic);
        if (empty($batch['lab_id']) || $batch['lab_id'] < 1) {
            $batch['lab_id'] = (int)$cli->{'cli_lab-ID'};
        }
    }
}

//STEP A — DOCTOR: find or create
$doc_id  = 0;
$doc_first   = sanitize_text_field($doc['doc_first']    ?? '');
$doc_last    = sanitize_text_field($doc['doc_last']     ?? '');
$doc_npi     = sanitize_text_field($doc['doc_npi']      ?? '');
$doc_spec    = sanitize_text_field($doc['doc_specialty'] ?? '');

if (!empty($doc_first) && !empty($doc_last)) {

    // Try to find existing doctor for this clinic
    $where_npi  = $doc_npi
        ? $wpdb->prepare(" AND doc.doc_npi = %s", $doc_npi)
        : '';

    $doc_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT doc.doc_ID
               FROM ci_doc doc
               JOIN ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
              WHERE doc.doc_first = %s
                AND doc.doc_last  = %s
                AND cd.cli_ID     = %d
                {$where_npi}
              LIMIT 1",
        $doc_first,
        $doc_last,
        $cli_id
    ));

    if (!$doc_id) {
        // Create new doctor
        $new_doc = [
            'doc_first'     => $doc_first,
            'doc_last'      => $doc_last,
            'doc_npi'       => $doc_npi,
            'doc_specialty' => $doc_spec,
        ];
        if (function_exists('tr_data_update_doc')) {
            $new_doc['cli_ID'] = $cli_id;
            $saved = tr_data_update_doc($new_doc);
            $doc_id = (int)($saved['doc_ID'] ?? 0);
        } else {
            $wpdb->insert('ci_doc', $new_doc);
            $doc_id = (int) $wpdb->insert_id;
            if ($doc_id > 0 && $cli_id > 0) {
                $wpdb->replace('ci_clinic_doc', [
                    'doc_ID' => $doc_id,
                    'cli_ID' => $cli_id,
                ]);
            }
        }
    }

    if ($doc_id > 0) {
        $batch['doc_ID'] = $doc_id;
        $batch['requesting_physician'] = ucwords(trim($doc_last . ', ' . $doc_first));
    }
}

//PATIENT: find or create
if (isset($pa['pa_dob'])) {
    $pa['pa_dob'] = function_exists('tr_format_date_default')
        ? tr_format_date_default($pa['pa_dob'], 'Y-m-d')
        : date('Y-m-d', strtotime($pa['pa_dob']));
}

// Normalise phone
if (isset($pa['pa_phone'])) {
    $pa['pa_phone'] = preg_replace('/[^0-9+]/', '', $pa['pa_phone']);
}

$patient_id  = 0;
$pa_id_given = (int)($pa['pa_ID'] ?? 0);
unset($pa['pa_ID']);  // never trust client-supplied pa_ID for INSERT

$patient = null;

// 1) Try exact match by name+DOB
if (!empty($pa['pa_first']) && !empty($pa['pa_last']) && !empty($pa['pa_dob'])) {
    $patient = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM ci_patients
              WHERE pa_first = %s AND pa_last = %s AND pa_dob = %s
              LIMIT 1",
        $pa['pa_first'],
        $pa['pa_last'],
        $pa['pa_dob']
    ));
}

// 2) Or fall back to the ID given if valid
if (!$patient && $pa_id_given > 0) {
    $patient = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM ci_patients WHERE pa_ID = %d",
        $pa_id_given
    ));
}

if ($patient) {
    $patient_id = (int)$patient->pa_ID;
    // Update demographics (name, address, phone, etc.)
    if (function_exists('trpdd_update_patient')) {
        trpdd_update_patient($pa, $patient_id);
    } else {
        $wpdb->update('ci_patients', $pa, ['pa_ID' => $patient_id]);
    }
    $patient = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM ci_patients WHERE pa_ID = %d",
        $patient_id
    ));
    $batch['patient_name'] = trim($patient->pa_last . ', ' . $patient->pa_first);
} else {
    // Create new patient
    $pa['pa_created']    = date_i18n('Y-m-d H:i:s');
    $pa['pa_created_by'] = get_current_user_id();
    if ($cli_id > 0) {
        $pa['pa_clinic_ID'] = $cli_id;
    }
    $wpdb->insert('ci_patients', $pa);
    $patient_id = (int) $wpdb->insert_id;
    if ($patient_id < 1) {
        wp_send_json_error(['message' => 'Failed to create patient record. ' . $wpdb->last_error]);
        exit;
    }
    $batch['patient_name'] = trim(($pa['pa_last'] ?? '') . ', ' . ($pa['pa_first'] ?? ''));
}
$batch['patient_id'] = $patient_id;
$batch['dob']        = $pa['pa_dob'] ?? '';

//STEP C — INSURANCE: find or create

// Handle billing-type shortcuts
$billing_override = sanitize_text_field($_POST['bat']['ins_comp'] ?? 'ins');
if ($billing_override === 'cash') {
    $batch['insurance'] = 'No Insurance - Cash';
} elseif ($billing_override === 'client') {
    $batch['insurance'] = 'Client bill';
} elseif (!empty($ins1['ins_first']) || !empty($ins1['ins_last']) || !empty($ins1['ins_company'])) {
    // Capitalise company name
    if (!empty($ins1['ins_company'])) {
        $ins1['ins_company'] = function_exists('tr_pdd_capitalized')
            ? tr_pdd_capitalized($ins1['ins_company'])
            : ucwords(strtolower($ins1['ins_company']));
    }

    $ins1_id = (int)($ins1['ins_ID'] ?? 0);

    if ($ins1_id > 0) {
        // Update existing
        if (function_exists('trpdd_update_insurance')) {
            trpdd_update_insurance($ins1, $ins1_id);
        } else {
            $wpdb->update('ci_insurances', $ins1, ['ins_ID' => $ins1_id]);
        }
        $batch['insurance1'] = $ins1_id;
        $batch['insurance']  = $ins1['ins_company'] ?? '';
    } else {
        // Insert new
        $ins1['pa_ID']  = $patient_id;
        $ins1['lab_ID'] = $batch['lab_id'];
        unset($ins1['ins_ID']);
        $wpdb->insert('ci_insurances', $ins1);
        $batch['insurance1'] = (int) $wpdb->insert_id;
        $batch['insurance']  = $ins1['ins_company'] ?? '';
    }
}

if (function_exists('tr_save_ins_card')) {
    $file_map = [
        'ins_card_front'   => 'img_ins_card_front',
        'ins_card_back'    => 'img_ins_card_back',
        'patient_id_front' => 'img_patient_id_front',
        'patient_id_back'  => 'img_patient_id_back',
    ];
    foreach ($file_map as $ff => $hf) {
        $saved = tr_save_ins_card($signature_id . '_' . $ff, $_FILES[$ff] ?? []);
        if ($saved && $saved !== '_' . $ff . '.jpg') {
            $batch[$ff] = $saved;
        } elseif (!empty($_REQUEST[$hf])) {
            $batch[$ff] = sanitize_text_field($_REQUEST[$hf]);
        }
    }
}

if (function_exists('tr_save_issue_photo') && isset($_FILES['issue_photo'])) {
    $saved_issue = tr_save_issue_photo($signature_id . '_issue', $_FILES['issue_photo']);
    if ($saved_issue) $batch['issue_photo'] = $saved_issue;
}

if (function_exists('tr_save_result_upload')) {
    if (isset($batch['result_pdf']) || isset($_FILES['result_pdfs'])) {
        $new_pdf = function_exists('tr_save_result_pdfs_upload')
            ? tr_save_result_pdfs_upload($signature_id, $batch['result_pdf'] ?? '', $_FILES['result_pdfs'] ?? null)
            : tr_save_result_upload($signature_id, $_FILES['result_pdfs'] ?? []);
        if ($new_pdf) {
            $batch['result_pdf'] = $new_pdf;
        }
    }
    if (isset($batch['original_req']) || isset($_FILES['original_reqs'])) {
        $new_orig = function_exists('tr_save_multi_result_upload')
            ? tr_save_multi_result_upload($signature_id . 'original', $batch['original_req'] ?? '', $_FILES['original_reqs'] ?? null)
            : tr_save_result_upload($signature_id . 'original', $_FILES['original_reqs'] ?? []);
        if ($new_orig) $batch['original_req'] = $new_orig;
    }
}

//STEP D — ACCESSION NUMBER
if (empty($batch['assen'])) {
    $lab_arr = [];
    if ($batch['lab_id'] > 0) {
        $lab_arr = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM ci_labs WHERE lab_ID = %d", $batch['lab_id']),
            ARRAY_A
        ) ?: [];
    }
    $batch['assen'] = function_exists('tr_get_new_assen')
        ? tr_get_new_assen($btype, false, '', $lab_arr)
        : strtoupper(uniqid('PCR'));
}
$batch['assen'] = sanitize_text_field(trim($batch['assen']));

// Handle duplicate assen (append -01, -02 …)
$exist = $wpdb->get_var($wpdb->prepare(
    "SELECT assen FROM {$table_bat}
          WHERE assen LIKE %s OR assen = %s
          ORDER BY assen DESC LIMIT 1",
    $wpdb->esc_like($batch['assen']) . '-%',
    $batch['assen']
));
if ($exist) {
    $num = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT CAST(SUBSTRING_INDEX(assen,'-',-1) AS DECIMAL) AS n
               FROM {$table_bat}
              WHERE assen LIKE %s
              ORDER BY n DESC LIMIT 1",
        $wpdb->esc_like($batch['assen']) . '-%'
    ));
    $num = $num > 0 ? $num + 1 : 1;
    $batch['assen'] = $batch['assen'] . '-' . str_pad($num, 2, '0', STR_PAD_LEFT);
}

//STEP E — FINAL BATCH FIELDS
$batch['batch_created']  = date_i18n('Y-m-d H:i:s');
$batch['author_ID']      = get_current_user_id();
$batch['req_form_by']    = get_current_user_id();
$batch['req_form_time']  = date_i18n('Y-m-d H:i:s');
$batch['batch_type']          = 'F';
$batch['is_saved']       = 0;

if (empty($batch['batch_status'])) {
    $batch['batch_status'] = '';
}

$allowed_cols = [
    'assen',
    'batch_created',
    'batch_modified',
    'batch_status',
    'batch_id',
    'patient_id',
    'patient_name',
    'dob',
    'cli_ID',
    'requesting_practice',
    'doc_ID',
    'requesting_physician',
    'physician_phone',
    'col_ID',
    'doc',
    'lab_id',
    'lab',
    'ref_lab_ID',
    'insurance',
    'insurance1',
    'insurance2',
    'ins_comp',
    'panel_tests_requested',
    'type_of_specimen',
    'dagnostic_codes1',
    'notes',
    'notes_report',
    'internal_notes',
    'billing_notes',
    'p_condition',
    'priority_hours',
    'batch_short',
    'is_dna',
    'is_saved',
    'author_ID',
    'req_form_by',
    'req_form_time',
    'short_form_by',
    'short_form_time',
    'patient_signature',
    'provider_signature',
    'result_pdf',
    'original_req',
    'ins_card_front',
    'ins_card_back',
    'patient_id_front',
    'patient_id_back',
    'issue_photo',
    'medication',
    'current_medication',
    'orderable_test',
    'culture',
    'swabbed',
    'oldreq',
    'p_fin',
    'p_ehr',
    'p_pregnant',
    'temperature',
    'position',
    'subscriber_last',
    'subscriber_first',
    'date_of_injury',
    'address_of_claim',
    'subscriber_zip',
    'subscriber_insurance',
    'subscriber_wc',
    'attorney_name',
    'attorney_phone',
    'adjuster_name',
    'adjuster_phone',
    'claim_number',
    'claim_under_litigation',
    'attach_face_sheet',
    'attach_demographics',
    'drchrono_patient_id',
    'drchrono_doctor_id',
    'drchrono_practice_id',
];
$insert = [];
foreach ($allowed_cols as $col) {
    if (array_key_exists($col, $batch)) {
        $insert[$col] = $batch[$col];
    }
}

//STEP F — INSERT BATCH
$rs  = $wpdb->insert($table_bat, $insert);
$mid = (int) $wpdb->insert_id;

if ($rs === false || $mid < 1) {
    wp_send_json_error([
        'message' => 'Failed to save requisition. ' . $wpdb->last_error,
    ]);
    exit;
}
if (!empty($batmeta_raw) && $mid > 0) {
    if (function_exists('trpdd_update_batch_meta')) {
        foreach ($batmeta_raw as $key => $value) {
            $key  = sanitize_key($key);
            trpdd_update_batch_meta($mid, $btype, $key, $value);
        }
    }
}

/* ── Optional: log ── */
if (function_exists('trpdd_log_user')) {
    trpdd_log_user(get_current_user_id(), 'save_req', '{}', $mid, $btype, '', '', $insert['assen']);
}

/* ── Optional: do_action hook for downstream integrations ── */
do_action('saved_req', $btype, $mid, $insert, $pa);

/* ── Trigger WSDL / cron sync ── */
$nextcron = wp_next_scheduled('ci_cron_sync_wsdl_client_req');
if (!$nextcron || $nextcron > time() + 300) {
    wp_schedule_single_event(time() + 180, 'ci_cron_sync_wsdl_client_req', [$btype]);
}

/* ── Build edit URL ── */
$edit_url = admin_url(
    'admin.php?page=pcr_req_form&act=edit&id=' . $mid
);
do_action('drchrono_req_saved', $btype, $mid, $drchrono_patient_id, $drchrono_doctor_id, $insert);

wp_send_json_success([
    'message'  => 'Requisition submitted! Accession #: ' . esc_html($insert['assen']),
    'mid'      => $mid,
    'assen'    => $insert['assen'],
    'edit_url' => $edit_url,
]);
exit;
