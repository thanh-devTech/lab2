<?php


global $wpdb;

/* ── 1. Nonce ────────────────────────────────────────────── */
if (!check_ajax_referer('drchrono_lab_nonce', 'nonce', false)) {
    wp_send_json_error(['message' => 'Security check failed.'], 403);
    exit;
}

/* ── 2. Raw input ────────────────────────────────────────── */
$_POST = function_exists('tr_pdd_stripslashes_deep')
    ? tr_pdd_stripslashes_deep($_POST)
    : wp_unslash($_POST);

$btype         = 'tox';
$table_batch   = 'ci_batches';
$table_report  = 'ci_reports';
$specimen_tbl  = 'ci_result_test_speciments';

$batch   = isset($_POST['bat'])     && is_array($_POST['bat'])     ? $_POST['bat']     : [];
$pa      = isset($_POST['pa'])      && is_array($_POST['pa'])      ? $_POST['pa']      : [];
$ins1    = isset($_POST['ins1'])    && is_array($_POST['ins1'])    ? $_POST['ins1']    : [];
$ins2    = isset($_POST['ins2'])    && is_array($_POST['ins2'])    ? $_POST['ins2']    : [];
$col     = isset($_POST['col'])     && is_array($_POST['col'])     ? $_POST['col']     : [];
$doc     = isset($_POST['doc'])     && is_array($_POST['doc'])     ? $_POST['doc']     : [];
$batmeta = isset($_POST['batmeta']) && is_array($_POST['batmeta']) ? $_POST['batmeta'] : [];
$patient_signature = $_POST['patient_signature'];
$provider_signature = $_POST['provider_signature'];
$drchrono_patient_id = $_POST['drchrono_patient_id'] ?? '';
$drchrono_doctor_id = $_POST['drchrono_doctor_id'] ?? '';

/* ── Textarea fields ─────────────────────────────────────── */
foreach (['notes', 'notes_report', 'billing_notes', 'internal_notes'] as $f) {
    if (isset($batch[$f])) {
        $batch[$f] = sanitize_textarea_field(wp_unslash($batch[$f]));
    }
}

$signature_id = trim(strtolower($batch['assen']));
if (empty($signature_id)) {
    $signature_id = 'batch_' . time();
}

$patient_sign = tr_save_signature($signature_id . '_patient', $patient_signature, $_FILES['patient_signature']);
if ($patient_sign) {
    $batch['patient_signature'] = $patient_sign;
} else if (isset($_REQUEST['onfile'])) {
    $batch['patient_signature'] = 'onfile';
} else if (isset($_REQUEST['img_patient_signature'])) {
    $batch['patient_signature'] = $_REQUEST['img_patient_signature'];
}

$provider_sign = tr_save_signature($signature_id . '_provider', $provider_signature, $_FILES['provider_signature']);
if ($provider_sign) {
    $batch['provider_signature'] = $provider_sign;
} else if (isset($_REQUEST['img_provider_signature'])) {
    $batch['provider_signature'] = $_REQUEST['img_provider_signature'];
}

if (empty($batch['doc']) || $batch['doc'] === 'auto') {
    $batch['doc'] = date_i18n('Y-m-d H:i:s');
} else {
    $ts = strtotime($batch['doc']);
    $batch['doc'] = $ts ? date('Y-m-d H:i:s', $ts) : date_i18n('Y-m-d H:i:s');
}

/* ── Type of specimen ────────────────────────────────────── */
if (empty($batch['type_of_specimen'])) {
    $batch['type_of_specimen'] = 'Urine';
}

/* ── 4. Lab ID ───────────────────────────────────────────── */
$cli_id = (int)($batch['cli_ID'] ?? 0);

if (empty($batch['lab_id']) || (int)$batch['lab_id'] < 1) {
    if ($cli_id > 0) {
        $batch['lab_id'] = function_exists('tr_get_lab_id_by_clinic')
            ? (int) tr_get_lab_id_by_clinic($cli_id)
            : 1;
    }
    if (empty($batch['lab_id']) || (int)$batch['lab_id'] < 1) {
        $batch['lab_id'] = function_exists('CI_Base_Lab')
            ? (int) CI_Base_Lab::get_current_lab_id()
            : 1;
    }
}

$lab = [];
if ((int)$batch['lab_id'] > 0) {
    $lab = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM ci_labs WHERE lab_ID = %d", $batch['lab_id']),
        ARRAY_A
    ) ?: [];
    $batch['lab'] = function_exists('tr_get_lab_short')
        ? tr_get_lab_short((int)$batch['lab_id'])
        : ($lab['lab_dba_init'] ?? '');
}

/* ── Clinic → requesting practice ───────────────────────── */
$cli = null;
if ($cli_id > 0) {
    $cli = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM ci_clinic WHERE cID = %d", $cli_id)
    );
    if ($cli) {
        $batch['requesting_practice'] = ucwords($cli->cli_clinic);
        if (empty($batch['lab_id']) || (int)$batch['lab_id'] < 1) {
            $batch['lab_id'] = (int)$cli->{'cli_lab-ID'};
        }
        // Ref lab from clinic
        if (
            empty($batch['ref_lab_ID']) && $cli->cli_ref_lab_ID > 0
            && !tr_get_option('ci_pdf_data', 'disable_rlbcl', false)
        ) {
            $batch['ref_lab_ID'] = (int)$cli->cli_ref_lab_ID;
        }
    }
}

// Same-as-lab guard
if (!empty($batch['ref_lab_ID']) && (int)$batch['ref_lab_ID'] === (int)$batch['lab_id']) {
    $batch['ref_lab_ID'] = 0;
}

/* ── Accession prefix (lab replace) ─────────────────────── */
if (function_exists('CI_Base_Lab')) {
    if (isset($batch['assen'])) {
        $batch['assen'] = CI_Base_Lab::replace_assen_by_lab($batch['assen'], null, $batch, $btype);
    }
}

/* ══ STEP 5 — DOCTOR: find or create ═══════════════════════════════════ */
$doc_id    = 0;
$doc_first = sanitize_text_field($doc['doc_first']    ?? '');
$doc_last  = sanitize_text_field($doc['doc_last']     ?? '');
$doc_npi   = sanitize_text_field($doc['doc_npi']      ?? '');
$doc_spec  = sanitize_text_field($doc['doc_specialty'] ?? '');
$doc_phone = sanitize_text_field($doc['doc_phone']    ?? '');

if (!empty($doc_first) && !empty($doc_last)) {
    $where_npi = $doc_npi
        ? $wpdb->prepare(" AND doc.doc_npi = %s", $doc_npi) : '';

    $doc_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT doc.doc_ID
               FROM ci_doc doc
               JOIN ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
              WHERE doc.doc_first = %s AND doc.doc_last = %s AND cd.cli_ID = %d {$where_npi}
              LIMIT 1",
        $doc_first,
        $doc_last,
        $cli_id
    ));

    if (!$doc_id) {
        $new_doc = [
            'doc_first'     => $doc_first,
            'doc_last'      => $doc_last,
            'doc_npi'       => $doc_npi,
            'doc_specialty' => $doc_spec,
            'doc_phone'     => $doc_phone,
        ];
        if (function_exists('tr_data_update_doc')) {
            $new_doc['cli_ID'] = $cli_id;
            $saved  = tr_data_update_doc($new_doc);
            $doc_id = (int)($saved['doc_ID'] ?? 0);
        } else {
            $wpdb->insert('ci_doc', $new_doc);
            $doc_id = (int) $wpdb->insert_id;
            if ($doc_id > 0 && $cli_id > 0) {
                $wpdb->replace('ci_clinic_doc', ['doc_ID' => $doc_id, 'cli_ID' => $cli_id]);
            }
        }
    }

    if ($doc_id > 0) {
        $batch['doc_ID']               = $doc_id;
        $batch['requesting_physician'] = ucwords(trim($doc_last . ', ' . $doc_first));
        $batch['physician_phone']      = $doc_phone;
    }
}

/* ══ STEP 6 — PATIENT: find or create ══════════════════════════════════ */
if (isset($pa['pa_dob'])) {
    $pa['pa_dob'] = function_exists('tr_format_date_default')
        ? tr_format_date_default($pa['pa_dob'], 'Y-m-d')
        : date('Y-m-d', strtotime($pa['pa_dob']));
    $batch['dob'] = $pa['pa_dob'];
}

if (isset($pa['pa_phone'])) {
    $pa['pa_phone'] = preg_replace('/[^0-9+]/', '', $pa['pa_phone']);
}
if (isset($pa['pa_ssn'])) {
    $pa['pa_ssn'] = trim($pa['pa_ssn']);
}
// Sanitize name fields
foreach (['pa_first', 'pa_last'] as $nf) {
    if (!empty($pa[$nf])) {
        $pa[$nf] = str_replace('9275', '', $pa[$nf]);
    }
}

$patient_id  = 0;
$pa_id_given = (int)($pa['pa_ID'] ?? 0);
unset($pa['pa_ID']);

$patient = null;
if (!empty($pa['pa_first']) && !empty($pa['pa_last']) && !empty($pa['pa_dob'])) {
    $patient = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM ci_patients WHERE pa_first=%s AND pa_last=%s AND pa_dob=%s LIMIT 1",
        $pa['pa_first'],
        $pa['pa_last'],
        $pa['pa_dob']
    ));
}
if (!$patient && $pa_id_given > 0) {
    $patient = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM ci_patients WHERE pa_ID=%d",
        $pa_id_given
    ));
}

if ($cli_id > 0) $pa['pa_clinic_ID'] = $cli_id;

if ($patient) {
    $patient_id = (int)$patient->pa_ID;
    if (function_exists('trpdd_update_patient')) {
        trpdd_update_patient($pa, $patient_id);
    } else {
        $wpdb->update('ci_patients', $pa, ['pa_ID' => $patient_id]);
    }
    $patient = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM ci_patients WHERE pa_ID=%d",
        $patient_id
    ));
    $batch['patient_name'] = trim($patient->pa_last . ', ' . $patient->pa_first);
} else {
    if (!empty($pa)) {
        $pa['pa_created']    = date_i18n('Y-m-d H:i:s');
        $pa['pa_created_by'] = get_current_user_id();
        $wpdb->insert('ci_patients', $pa);
        $patient_id = (int)$wpdb->insert_id;
        if ($patient_id < 1) {
            wp_send_json_error(['message' => 'Failed to create patient. ' . $wpdb->last_error]);
            exit;
        }
        $batch['patient_name'] = trim(($pa['pa_last'] ?? '') . ', ' . ($pa['pa_first'] ?? ''));
    }
}
if ($patient_id > 0) {
    $batch['patient_id'] = $patient_id;
}

/* ══ STEP 7 — INSURANCE ════════════════════════════════════════════════ */
$billing_type = sanitize_text_field($batch['ins_comp'] ?? 'ins');

if ($billing_type === 'cash') {
    $batch['insurance'] = 'No Insurance - Cash';
} elseif ($billing_type === 'client') {
    $batch['insurance'] = 'Client bill';
} else {
    // Primary
    if (!empty($ins1['ins_company'])) {
        $ins1['ins_company'] = function_exists('tr_pdd_capitalized')
            ? tr_pdd_capitalized($ins1['ins_company'])
            : ucwords(strtolower($ins1['ins_company']));
    }

    $ins1_id = (int)($ins1['ins_ID'] ?? 0);
    if ($ins1_id > 0) {
        $old = $wpdb->get_row($wpdb->prepare(
            "SELECT ins_company FROM ci_insurances WHERE ins_ID=%d",
            $ins1_id
        ), ARRAY_A);
        if ($old && $old['ins_company'] !== ($ins1['ins_company'] ?? '')) {
            $ins1_id = 0;
        }
    }

    if ($ins1_id > 0) {
        if (function_exists('trpdd_update_insurance')) {
            trpdd_update_insurance($ins1, $ins1_id);
        } else {
            $wpdb->update('ci_insurances', $ins1, ['ins_ID' => $ins1_id]);
        }
        $batch['insurance1'] = $ins1_id;
        $batch['insurance']  = $ins1['ins_company'] ?? '';
    } elseif (!empty($ins1['ins_first']) || !empty($ins1['ins_last']) || !empty($ins1['ins_company'])) {
        $ins1['pa_ID']  = $patient_id;
        $ins1['lab_ID'] = $batch['lab_id'];
        unset($ins1['ins_ID']);
        $wpdb->insert('ci_insurances', $ins1);
        $batch['insurance1'] = (int)$wpdb->insert_id;
        $batch['insurance']  = $ins1['ins_company'] ?? '';
    }

    // Secondary
    $ins2_id = (int)($ins2['ins_ID'] ?? 0);
    if ($ins2_id > 0) {
        $batch['insurance2'] = $ins2_id;
        $wpdb->update('ci_insurances', $ins2, ['ins_ID' => $ins2_id]);
    } elseif (!empty($ins2['ins_first']) || !empty($ins2['ins_last']) || !empty($ins2['ins_company'])) {
        $ins2['pa_ID']         = $patient_id;
        $ins2['lab_ID']        = $batch['lab_id'];
        $ins2['ins_is_second'] = 1;
        unset($ins2['ins_ID']);
        $wpdb->insert('ci_insurances', $ins2);
        $batch['insurance2'] = (int)$wpdb->insert_id;
    }
}

/* ══ STEP 8 — COLLECTOR ════════════════════════════════════════════════ */
$col_id = (int)($col['col_ID'] ?? 0);
if ($col_id > 0) {
    $collector   = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM ci_collector WHERE col_ID=%d", $col_id),
        ARRAY_A
    );
    $update_col = [];
    if (empty(trim($collector['col_phone'] ?? ''))) {
        $update_col['col_phone'] = sanitize_text_field($col['col_phone'] ?? '');
    }
    if (empty($collector['col_first'])) {
        $update_col['col_first'] = sanitize_text_field($col['col_name'] ?? '');
    }
    if ($update_col) {
        $wpdb->update('ci_collector', $update_col, ['col_ID' => $col_id]);
    }
    $batch['col_ID'] = $col_id;
} elseif (!empty($col['col_name'])) {
    $wpdb->insert('ci_collector', [
        'col_first' => sanitize_text_field($col['col_name']),
        'col_phone' => sanitize_text_field($col['col_phone'] ?? ''),
    ]);
    $new_col_id = (int)$wpdb->insert_id;
    if ($new_col_id > 0) {
        $batch['col_ID'] = $new_col_id;
        if ($cli_id > 0) {
            $wpdb->insert('ci_clinic_collector', ['col_ID' => $new_col_id, 'cli_ID' => $cli_id]);
        }
    }
}

/* ══ STEP 9 — MEDICATIONS ══════════════════════════════════════════════ */
if (!empty($_POST['has_edit_medication'])) {
    // POC medication table (serialised for tox)
    if (isset($batch['medication']) && is_array($batch['medication'])) {
        $batch['medication'] = maybe_serialize($batch['medication']);
    }

    // Current medications list
    if (!is_array($batch['current_medication'] ?? null)) {
        $batch['current_medication'] = [];
    }
    foreach ((array)($batch['current_medication_other'] ?? []) as $vl) {
        $vl = trim($vl);
        if ($vl) $batch['current_medication'][] = $vl;
    }
    unset($batch['current_medication_other']);
    $batch['current_medication'] = implode(',', $batch['current_medication']);

    if (!empty($_POST['current_medication_none']) && $_POST['current_medication_none'] == '1') {
        $batch['current_medication'] = 'None Listed';
    }
}

/* ══ STEP 10 — PANEL TESTS & DIAGNOSTIC CODES ══════════════════════════ */
if (!empty($_POST['has_panel_requests']) || isset($batch['panel_tests_requested'])) {
    if (isset($batch['panel_tests_requested']) && is_array($batch['panel_tests_requested'])) {
        $batch['panel_tests_requested'] = implode(',', $batch['panel_tests_requested']);
    }
    // Run the tox-specific panel normaliser if available
    if (function_exists('trpdd_panel_tests_requested') && !empty($batch['panel_tests_requested'])) {
        $batch['panel_tests_requested'] = trpdd_panel_tests_requested(
            $batch,
            $batch['doc_ID'] ?? 0,
            $cli_id
        );
    }
}

if (isset($batch['dagnostic_codes'])) {
    $batch['dagnostic_codes1'] = implode(',', (array)$batch['dagnostic_codes']);
    unset($batch['dagnostic_codes']);
}

/* ══ STEP 11 — FILE UPLOADS ════════════════════════════════════════════ */
$signature_id = sanitize_text_field(trim(strtolower($batch['assen'] ?? '')));
if (empty($signature_id)) $signature_id = 'tox_' . time();

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
            // Update approve_date on existing report
            $wpdb->update('ci_reports', ['approve_date' => date_i18n('Y-m-d H:i:s')], ['patient_id' => $batch['assen'] ?? '']);
        }
    }
    if (isset($batch['original_req']) || isset($_FILES['original_reqs'])) {
        $new_orig = function_exists('tr_save_multi_result_upload')
            ? tr_save_multi_result_upload($signature_id . 'original', $batch['original_req'] ?? '', $_FILES['original_reqs'] ?? null)
            : tr_save_result_upload($signature_id . 'original', $_FILES['original_reqs'] ?? []);
        if ($new_orig) $batch['original_req'] = $new_orig;
    }
}

/* ══ STEP 12 — ACCESSION NUMBER ════════════════════════════════════════ */
if (empty($batch['assen'])) {
    $batch['assen'] = function_exists('tr_get_new_assen')
        ? tr_get_new_assen($btype, false, '', $lab)
        : strtoupper(uniqid('TOX'));
}
$batch['assen'] = sanitize_text_field(trim($batch['assen'] ?? ''));

// Duplicate accession guard
$has_duplicate_assen = false;
if (!empty($batch['assen'])) {
    $exist = $wpdb->get_var($wpdb->prepare(
        "SELECT assen FROM {$table_batch}
              WHERE assen LIKE %s OR assen = %s
              ORDER BY assen DESC LIMIT 1",
        $wpdb->esc_like($batch['assen']) . '-%',
        $batch['assen']
    ));
    if ($exist) {
        $assen_num = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT CAST(SUBSTRING_INDEX(assen,'-',-1) AS DECIMAL) AS n
                   FROM {$table_batch}
                  WHERE assen LIKE %s ORDER BY n DESC LIMIT 1",
            $wpdb->esc_like($batch['assen']) . '-%'
        ));
        $assen_num = $assen_num > 0 ? $assen_num + 1 : 1;
        $batch['assen'] = $batch['assen'] . '-' . str_pad($assen_num, 2, '0', STR_PAD_LEFT);
        $has_duplicate_assen = true;
    }
}

// Re-stamp signature_id with final assen
$signature_id = sanitize_text_field(trim(strtolower($batch['assen'])));

/* ══ STEP 13 — AUTHOR & INSERT ══════════════════════════════════════════ */
$batch['author_ID']     = get_current_user_id();
$batch['req_form_by']   = get_current_user_id();
$batch['req_form_time'] = date_i18n('Y-m-d H:i:s');
$batch['batch_created'] = date_i18n('Y-m-d H:i:s');
$batch['batch_type']    = 'F';
$batch['is_saved'] = 0;

if (empty($batch['batch_status'])) {
    $batch['batch_status'] = '';
}

$allowed_cols = [
    'assen',
    'batch_created',
    'batch_modified',
    'batch_status',
    'batch_id',
    'batch_type',
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
    'billing_notes',
    'internal_notes',
    'custom_profile',
    'medication',
    'current_medication',
    'current_medication_other',
    'poc_perform',
    'temperature',
    'has_screening',
    'only_screening',
    'fasting',
    'stat',
    'is_saved',
    'is_dna',
    'author_ID',
    'req_form_by',
    'req_form_time',
    'short_form_by',
    'short_form_time',
    'patient_signature',
    'provider_signature',
    'ins_card_front',
    'ins_card_back',
    'patient_id_front',
    'patient_id_back',
    'result_pdf',
    'original_req',
    'issue_photo',
    'oldreq',
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
    'drchrono_patient_id',
    'drchrono_doctor_id',
    'drchrono_practice_id',
];

$insert = [];
foreach ($allowed_cols as $col_name) {
    if (array_key_exists($col_name, $batch)) {
        $insert[$col_name] = $batch[$col_name];
    }
}

$rs  = $wpdb->insert($table_batch, $insert);
$mid = (int)$wpdb->insert_id;

if ($rs === false || $mid < 1) {
    wp_send_json_error(['message' => 'Failed to save requisition. ' . $wpdb->last_error]);
    exit;
}

/* ══ STEP 14 — BATCH META ═══════════════════════════════════════════════ */
foreach ($batmeta as $mk => $mv) {
    $mk = sanitize_key($mk);
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT mid FROM ci_batches_meta WHERE mid=%d AND meta_key=%s",
        $mid,
        $mk
    ));
    if ($exists) {
        $wpdb->update('ci_batches_meta', ['meta_value' => $mv], ['mid' => $mid, 'meta_key' => $mk]);
    } else {
        $wpdb->insert('ci_batches_meta', ['mid' => $mid, 'meta_key' => $mk, 'meta_value' => $mv]);
    }
}

/* ══ STEP 15 — SPECIMEN SCREENING ══════════════════════════════════════ */
$has_screening = false;

if (!empty($_POST['data_specimen_screening'])) {
    foreach ($_POST['data_specimen_screening'] as $k => $vl) {
        $k = (int)$k;
        if (!$k) continue;
        $update = ['mid' => $mid, 'testid' => $k];
        if (isset($vl['conc']))     $update['conc']     = sanitize_text_field($vl['conc']);
        if (isset($vl['outcome']))  $update['outcome']  = sanitize_text_field($vl['outcome']);
        if (isset($vl['comments'])) $update['comments'] = sanitize_textarea_field($vl['comments']);
        if (isset($vl['checked'])) {
            $update['checked'] = (int)$vl['checked'];
            if ($update['checked']) $has_screening = true;
        }

        $ex = $wpdb->get_var($wpdb->prepare(
            "SELECT mid FROM {$specimen_tbl} WHERE mid=%d AND testid=%d",
            $mid,
            $k
        ));
        if ($ex) {
            $wpdb->update($specimen_tbl, $update, ['mid' => $mid, 'testid' => $k]);
        } elseif (
            (isset($update['conc']) && $update['conc'] !== '')
            || !empty($update['checked'])
            || !empty($update['comments'])
        ) {
            $wpdb->insert($specimen_tbl, $update);
        }
    }
}

// Update has_screening flag on the batch
if ($has_screening) {
    $wpdb->update($table_batch, ['has_screening' => 1], ['mid' => $mid]);
}


$update_report = [];
$group_ids     = [];
$test_ids      = [];
$report_db     = null;
$has_manual    = false;
$tg_auto_batch = false;
$tg_auto_approve = false;

foreach (explode(',', $insert['panel_tests_requested'] ?? '') as $t) {
    if (stripos($t, 'GROUP_') !== false) {
        $group_ids[] = (int)str_ireplace('GROUP_', '', $t);
    } elseif ($t) {
        // For tox, panel tests are stored by name, resolve to ID
        $tid = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT te_ID FROM ci_test_panels WHERE te_name=%s AND te_type='tox'",
            $t
        ));
        if ($tid > 0) $test_ids[] = $tid;
    }
}

if ($group_ids || $test_ids) {
    $group_ids_str = $group_ids ? implode(',', $group_ids) : '0';
    $test_ids_str  = $test_ids  ? implode(',', $test_ids)  : '0';

    if ($group_ids) {
        $has_manual = $wpdb->get_var(
            "SELECT COUNT(*) FROM ci_test_groups
                      WHERE tg_type='tox' AND tg_ID IN ({$group_ids_str}) AND tg_manual_edit=1"
        ) > 0;

        $tg_auto_batch = $wpdb->get_var(
            "SELECT COUNT(*) FROM ci_test_groups
                      WHERE tg_type='tox' AND tg_ID IN ({$group_ids_str}) AND tg_auto_batchid=1"
        ) > 0;

        $tg_auto_approve = (int)$wpdb->get_var(
            "SELECT COUNT(*) FROM ci_test_groups
                      WHERE tg_type='tox' AND tg_ID IN ({$group_ids_str}) AND tg_auto_approve_pos_neg=1"
        ) === count($group_ids);
    }

    if (!$has_manual && $test_ids) {
        $has_manual = $wpdb->get_var(
            "SELECT COUNT(*) FROM ci_test_groups
                      WHERE tg_type='tox'
                      AND tg_ID IN (SELECT te_group_id FROM ci_test_panels WHERE te_ID IN ({$test_ids_str}))
                      AND tg_manual_edit=1"
        ) > 0;
    }

    if (!empty($insert['result_pdf'])) {
        $has_manual = true;
    }
}

// Screening also requires a report row
if (!$has_manual && $has_screening && tr_get_option('ci_urine_validity_settings', 'manual_edit_screening')) {
    $has_manual = true;
}

if ($has_manual) {
    $report_db = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_report} WHERE patient_id=%s",
        $insert['assen']
    ), ARRAY_A);

    if (!$report_db) {
        $wpdb->insert($table_report, [
            'patient_id'   => $insert['assen'],
            'author_id'    => get_current_user_id(),
            'created'      => date_i18n('Y-m-d H:i:s'),
            'created_date' => date_i18n('Y-m-d'),
        ]);
        $report_db = ['id' => (int)$wpdb->insert_id];
    }
}

if (function_exists('trpdd_log_user')) {
    trpdd_log_user(get_current_user_id(), 'save_req', json_encode([]), $mid, $btype, '', '', $insert['assen']);
}


/* ── do_action hook ─────────────────────────────────────── */
do_action('saved_req', $btype, $mid, $insert, $pa);

/* ── Cron sync ──────────────────────────────────────────── */
$nextcron = wp_next_scheduled('ci_cron_sync_wsdl_client_req');
if (!$nextcron || $nextcron > time() + 300) {
    wp_schedule_single_event(time() + 180, 'ci_cron_sync_wsdl_client_req', [$btype]);
}

/* ── Build response ─────────────────────────────────────── */
$edit_url = admin_url('admin.php?page=tox_req_form&act=edit&id=' . $mid);

$messages = ['Requisition submitted! Accession #: ' . esc_html($insert['assen'])];
if ($has_duplicate_assen) {
    $messages[] = 'Warning: duplicate accession — suffix appended.';
}
do_action('drchrono_req_saved', $btype, $mid, $drchrono_patient_id, $drchrono_doctor_id, $insert);

wp_send_json_success([
    'message'  => implode(' ', $messages),
    'mid'      => $mid,
    'assen'    => $insert['assen'],
    'edit_url' => $edit_url,
]);
exit;
