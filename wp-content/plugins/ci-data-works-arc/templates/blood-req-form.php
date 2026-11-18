<?php

/**
 * Flow:
 *   2. Sanitise & normalise input
 *   3. Create / find Doctor  → doc_ID
 *   4. Create / find Patient → patient_id
 *   5. Create / find Insurance records (ins1, ins2)
 *   6. Create / find Collector → col_ID
 *   7. Insert ci_batches_blood row (always new – add-only form)
 *   8. Return JSON success / error
 */


global $wpdb;

$_POST = function_exists('tr_pdd_stripslashes_deep')
    ? tr_pdd_stripslashes_deep($_POST)
    : wp_unslash($_POST);

$btype     = 'blood';
$table_bat = 'ci_batches_blood';

$batch = isset($_POST['bat'])  && is_array($_POST['bat'])  ? $_POST['bat']  : [];
$batmeta_raw = isset($_POST['batmeta']) && is_array($_POST['batmeta']) ? $_POST['batmeta'] : [];
$pa    = isset($_POST['pa'])   && is_array($_POST['pa'])   ? $_POST['pa']   : [];
$ins1  = isset($_POST['ins1']) && is_array($_POST['ins1']) ? $_POST['ins1'] : [];
$ins2  = isset($_POST['ins2']) && is_array($_POST['ins2']) ? $_POST['ins2'] : [];
$doc   = isset($_POST['doc'])  && is_array($_POST['doc'])  ? $_POST['doc']  : [];
$col   = isset($_POST['col'])  && is_array($_POST['col'])  ? $_POST['col']  : [];
$patient_signature = $_POST['patient_signature'];
$provider_signature = $_POST['provider_signature'];
$drchrono_patient_id = $_POST['drchrono_patient_id'] ?? '';
$drchrono_doctor_id = $_POST['drchrono_doctor_id'] ?? '';

foreach (['notes', 'notes_report', 'billing_notes'] as $f) {
    if (isset($_POST['bat'][$f])) {
        $batch[$f] = sanitize_textarea_field(wp_unslash($_POST['bat'][$f]));
    }
}

/* ── 3. Date of collection ────────────────────────────────── */
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

/* ── 4. Type of specimen ──────────────────────────────────── */
if (isset($batch['type_of_specimen'])) {
    if (!is_array($batch['type_of_specimen'])) {
        $batch['type_of_specimen'] = [];
    }
    $batch['type_of_specimen'] = implode(',', array_unique(
        array_map('sanitize_text_field', $batch['type_of_specimen'])
    ));
}

/* ── 5. Panel tests ───────────────────────────────────────── */
if (isset($batch['panel_tests_requested'])) {
    if (!is_array($batch['panel_tests_requested'])) {
        $batch['panel_tests_requested'] = [];
    }

    // Ensure parent GROUP is added when only a child test is ticked
    // (mirrors file-4 logic)
    $all_groupid_by_testids = [];
    $rsts = $wpdb->get_results(
        "SELECT te.te_ID, tg.tg_ID, tg.tg_parent
               FROM ci_test_panels te
               JOIN ci_test_groups tg ON tg.tg_ID = te.te_group_id
              WHERE te.te_type = 'blood'"
    );
    foreach ($rsts as $t) {
        $all_groupid_by_testids[$t->te_ID] = $t->tg_parent > 0 ? $t->tg_parent : $t->tg_ID;
    }

    foreach ($batch['panel_tests_requested'] as $tid) {
        if (stripos($tid, 'GROUP_') === false) {
            $gid = $all_groupid_by_testids[$tid] ?? 0;
            if ($gid > 0 && !in_array('GROUP_' . $gid, $batch['panel_tests_requested'])) {
                $batch['panel_tests_requested'][] = 'GROUP_' . $gid;
            }
        }
    }
    $batch['panel_tests_requested'] = implode(',', array_unique($batch['panel_tests_requested']));
}

/* ── 6. Diagnostic codes ──────────────────────────────────── */
if (isset($batch['dagnostic_codes'])) {
    $batch['dagnostic_codes1'] = implode(',', (array)$batch['dagnostic_codes']);
    unset($batch['dagnostic_codes']);
}

/* ── 7. Medication ────────────────────────────────────────── */
if (isset($_POST['has_edit_medication'])) {
    if (!is_array($batch['medication'] ?? null)) {
        $batch['medication'] = [];
    }
    if (!empty($_POST['medication_other'])) {
        $batch['medication'][] = 'other:' . sanitize_text_field($_POST['medication_other']);
    }
    if (!empty($_POST['medication_other2'])) {
        $batch['medication'][] = 'other2:' . sanitize_text_field($_POST['medication_other2']);
    }
    $batch['medication'] = implode('|', $batch['medication']);
}

/* ── 8. Lab ID ────────────────────────────────────────────── */
$cli_id = (int)($batch['cli_ID'] ?? 0);

if (empty($batch['lab_id']) || (int)$batch['lab_id'] < 1) {
    if ($cli_id > 0 && function_exists('tr_get_lab_id_by_clinic')) {
        $batch['lab_id'] = (int) tr_get_lab_id_by_clinic($cli_id);
    }
    if (empty($batch['lab_id']) || (int)$batch['lab_id'] < 1) {
        $batch['lab_id'] = function_exists('CI_Base_Lab')
            ? (int) CI_Base_Lab::get_current_lab_id()
            : 1;
    }
}

if ((int)$batch['lab_id'] > 0 && function_exists('tr_get_lab_short')) {
    $batch['lab'] = tr_get_lab_short((int)$batch['lab_id']);
}

/* ── 9. Clinic → requesting practice ─────────────────────── */
if ($cli_id > 0) {
    $cli = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM ci_clinic WHERE cID = %d",
        $cli_id
    ));
    if ($cli) {
        $batch['requesting_practice'] = ucwords($cli->cli_clinic);
        if (empty($batch['lab_id']) || (int)$batch['lab_id'] < 1) {
            $batch['lab_id'] = (int)$cli->{'cli_lab-ID'};
        }
        // Auto ref lab from clinic (mirrors file-4)
        if (
            empty($batch['ref_lab_ID']) && $cli->cli_ref_lab_ID > 0
            && !tr_get_option('ci_pdf_data', 'disable_rlbcl', false)
        ) {
            $batch['ref_lab_ID'] = $cli->cli_ref_lab_ID;
        }
    }
}

/* ── STEP A: DOCTOR – find or create ─────────────────────── */
$doc_id    = 0;
$doc_first = sanitize_text_field($doc['doc_first']    ?? '');
$doc_last  = sanitize_text_field($doc['doc_last']     ?? '');
$doc_npi   = sanitize_text_field($doc['doc_npi']      ?? '');
$doc_spec  = sanitize_text_field($doc['doc_specialty'] ?? '');

if (!empty($doc_first) && !empty($doc_last)) {
    $npi_where = $doc_npi
        ? $wpdb->prepare(" AND doc.doc_npi = %s", $doc_npi)
        : '';

    $doc_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT doc.doc_ID
               FROM ci_doc doc
               JOIN ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
              WHERE doc.doc_first = %s
                AND doc.doc_last  = %s
                AND cd.cli_ID     = %d
                {$npi_where}
              LIMIT 1",
        $doc_first,
        $doc_last,
        $cli_id
    ));

    if (!$doc_id) {
        // Try without clinic join (fallback)
        $doc_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT doc_ID FROM ci_doc
                  WHERE doc_first = %s AND doc_last = %s AND doc_specialty = %s LIMIT 1",
            $doc_first,
            $doc_last,
            $doc_spec
        ));
    }

    if (!$doc_id) {
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
                $wpdb->replace('ci_clinic_doc', ['doc_ID' => $doc_id, 'cli_ID' => $cli_id]);
            }
        }
    }

    if ($doc_id > 0) {
        $batch['doc_ID'] = $doc_id;
        $batch['requesting_physician'] = ucwords(trim($doc_last . ', ' . $doc_first));
        // Pull doctor phone
        $doc_row = $wpdb->get_row($wpdb->prepare(
            "SELECT doc_phone FROM ci_doc WHERE doc_ID = %d",
            $doc_id
        ));
        if ($doc_row) {
            $batch['physician_phone'] = $doc_row->doc_phone;
        }
    }
}

/* ── STEP B: PATIENT – find or create ─────────────────────── */
if (isset($pa['pa_dob'])) {
    $pa['pa_dob'] = function_exists('tr_format_date_default')
        ? tr_format_date_default($pa['pa_dob'], 'Y-m-d')
        : date('Y-m-d', strtotime($pa['pa_dob']));
}
if (isset($pa['pa_phone'])) {
    $pa['pa_phone'] = preg_replace('/[^0-9+]/', '', $pa['pa_phone']);
}

$patient_id  = 0;
$pa_id_given = (int)($pa['pa_ID'] ?? 0);
unset($pa['pa_ID']);

$patient = null;

// 1) Exact match by name + DOB (mirrors file-4)
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
// 2) Fallback to supplied ID
if (!$patient && $pa_id_given > 0) {
    $patient = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM ci_patients WHERE pa_ID = %d",
        $pa_id_given
    ));
}

if ($cli_id > 0) {
    $pa['pa_clinic_ID'] = $cli_id;
}

if ($patient) {
    $patient_id = (int)$patient->pa_ID;
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
    $pa['pa_created']    = date_i18n('Y-m-d H:i:s');
    $pa['pa_created_by'] = get_current_user_id();
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

/* ── STEP C: INSURANCE – find or create ───────────────────── */
$billing_type = sanitize_text_field($batch['ins_comp'] ?? 'ins');

if ($billing_type === 'cash') {
    $batch['insurance'] = 'No Insurance - Cash';
} elseif ($billing_type === 'client') {
    $batch['insurance'] = 'Client bill';
} elseif (!empty($ins1['ins_first']) || !empty($ins1['ins_last']) || !empty($ins1['ins_company'])) {
    // Capitalise company
    if (!empty($ins1['ins_company'])) {
        $ins1['ins_company'] = function_exists('tr_pdd_capitalized')
            ? tr_pdd_capitalized($ins1['ins_company'])
            : ucwords(strtolower($ins1['ins_company']));
    }
    $ins1_id = (int)($ins1['ins_ID'] ?? 0);

    // If company changed, force new record (mirrors file-4)
    if ($ins1_id > 0) {
        $old_ins = $wpdb->get_row($wpdb->prepare(
            "SELECT ins_company FROM ci_insurances WHERE ins_ID = %d",
            $ins1_id
        ));
        if ($old_ins && $old_ins->ins_company !== $ins1['ins_company']) {
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
    } else {
        unset($ins1['ins_ID']);
        $ins1['pa_ID']  = $patient_id;
        $ins1['lab_ID'] = (int)$batch['lab_id'];
        $wpdb->insert('ci_insurances', $ins1);
        $batch['insurance1'] = (int) $wpdb->insert_id;
    }
    $batch['insurance'] = $ins1['ins_company'] ?? '';
}

// Secondary insurance
if (!empty($ins2['ins_first']) || !empty($ins2['ins_last']) || !empty($ins2['ins_company'])) {
    $ins2_id = (int)($ins2['ins_ID'] ?? 0);
    if ($ins2_id > 0) {
        $wpdb->update('ci_insurances', $ins2, ['ins_ID' => $ins2_id]);
        $batch['insurance2'] = $ins2_id;
    } else {
        unset($ins2['ins_ID']);
        $ins2['pa_ID']       = $patient_id;
        $ins2['lab_ID']      = (int)$batch['lab_id'];
        $ins2['ins_is_second'] = 1;
        $wpdb->insert('ci_insurances', $ins2);
        $batch['insurance2'] = (int) $wpdb->insert_id;
    }
}

// Handle no_insurance / client_bill overrides (mirrors file-4)
if (!empty($_POST['no_insurance'])) {
    $batch['insurance'] = sanitize_text_field($_POST['no_insurance']);
}
if (!empty($_POST['client_bill'])) {
    $batch['insurance'] = sanitize_text_field($_POST['client_bill']);
}

/* ── STEP D: COLLECTOR – find or create ───────────────────── */
$col_id   = (int)($col['col_ID'] ?? 0);
$col_name = sanitize_text_field($col['col_name'] ?? '');
$col_phone = sanitize_text_field($col['col_phone'] ?? '');
$col_code = sanitize_text_field($col['col_phlebotomist_code'] ?? '');

if ($col_id > 0) {
    // Update missing fields (mirrors file-4)
    $collector = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM ci_collector WHERE col_ID = %d",
        $col_id
    ), ARRAY_A);
    $update_col = [];
    if ($collector) {
        if (empty(trim($collector['col_phone'])) && !empty($col_phone)) {
            $update_col['col_phone'] = $col_phone;
        }
        if (empty($collector['col_first']) && !empty($col_name)) {
            $update_col['col_first'] = $col_name;
        }
        if (!empty($update_col)) {
            $wpdb->update('ci_collector', $update_col, ['col_ID' => $col_id]);
        }
    }
} elseif (!empty($col_name)) {
    $wpdb->insert('ci_collector', [
        'col_first' => $col_name,
        'col_phone' => $col_phone,
        'col_phlebotomist_code' => $col_code,
    ]);
    $col_id = (int) $wpdb->insert_id;
}
if ($col_id > 0) {
    $batch['col_ID'] = $col_id;
}

/* ── STEP E: ACCESSION NUMBER ─────────────────────────────── */
if (empty($batch['assen'])) {
    $lab_arr = [];
    if ((int)$batch['lab_id'] > 0) {
        $lab_arr = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM ci_labs WHERE lab_ID = %d", $batch['lab_id']),
            ARRAY_A
        ) ?: [];
    }
    $batch['assen'] = function_exists('tr_get_new_assen')
        ? tr_get_new_assen($btype, false, '', $lab_arr)
        : strtoupper(uniqid('BLD'));
}
$batch['assen'] = sanitize_text_field(trim($batch['assen']));

// Lab-specific assen transform (mirrors file-4)
if (function_exists('CI_Base_Lab') && method_exists('CI_Base_Lab', 'replace_assen_by_lab')) {
    $batch['assen'] = CI_Base_Lab::replace_assen_by_lab($batch['assen'], null, $batch);
}

// Duplicate assen handling (mirrors file-4)
$exist_assen = $wpdb->get_var($wpdb->prepare(
    "SELECT assen FROM {$table_bat}
          WHERE assen LIKE %s OR assen = %s
          ORDER BY assen DESC LIMIT 1",
    $wpdb->esc_like($batch['assen']) . '-%',
    $batch['assen']
));
if ($exist_assen) {
    $assen_num = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT CAST(SUBSTRING_INDEX(assen,'-',-1) AS DECIMAL) AS num
               FROM {$table_bat}
              WHERE assen LIKE %s
              ORDER BY num DESC LIMIT 1",
        $wpdb->esc_like($batch['assen']) . '-%'
    ));
    $assen_num  = $assen_num > 0 ? $assen_num + 1 : 1;
    $batch['assen'] = $batch['assen'] . '-' . str_pad($assen_num, 2, '0', STR_PAD_LEFT);
}

/* ── STEP F: CARD FILE UPLOADS ────────────────────────────── */

$file_fields = [
    'ins_card_front'   => 'ins_card_front',
    'ins_card_back'    => 'ins_card_back',
    'patient_id_front' => 'patient_id_front',
    'patient_id_back'  => 'patient_id_back',
];
foreach ($file_fields as $file_key => $batch_col) {
    $suffix_map = [
        'ins_card_front'   => '_ins',
        'ins_card_back'    => '_ins_back',
        'patient_id_front' => '_id',
        'patient_id_back'  => '_id_back',
    ];
    $suffix = $suffix_map[$file_key] ?? '_' . $file_key;
    if (!empty($_FILES[$file_key]['tmp_name']) && function_exists('tr_save_ins_card')) {
        $saved = tr_save_ins_card($signature_id . $suffix, $_FILES[$file_key]);
        if ($saved && $saved !== $signature_id . $suffix . '.jpg') {
            $batch[$batch_col] = $saved;
        }
    }
    // Fallback to hidden field (existing file)
    if (empty($batch[$batch_col]) && !empty($_POST['img_' . $file_key])) {
        $batch[$batch_col] = sanitize_text_field($_POST['img_' . $file_key]);
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

/* ── STEP G: FINAL BATCH FIELDS ───────────────────────────── */
$batch['batch_created'] = date_i18n('Y-m-d H:i:s');
$batch['author_ID']     = get_current_user_id();
$batch['req_form_by']   = get_current_user_id();
$batch['req_form_time'] = date_i18n('Y-m-d H:i:s');
$batch['batch_type']    = 'F';
$batch['is_saved']      = 0;

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
    'patient_signature',
    'provider_signature',
    'type_of_specimen',
    'dagnostic_codes1',
    'notes',
    'notes_report',
    'billing_notes',
    'internal_notes',
    'fasting',
    'stat',
    'p_pregnant',
    'p_fin',
    'p_ehr',
    'p_pain_score',
    'p_fatigue_score',
    'p_condition',
    'p_hemoglobin',
    'medication',
    'orderable_test',
    'ins_card_front',
    'ins_card_back',
    'patient_id_front',
    'patient_id_back',
    'result_pdf',
    'original_req',
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
    'batch_type',
    'is_saved',
    'is_dna',
    'author_ID',
    'req_form_by',
    'req_form_time',
    'short_form_by',
    'short_form_time',
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

/* ── STEP H: INSERT ───────────────────────────────────────── */
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

/* ── Post-insert hooks & cron ─────────────────────────────── */
if (function_exists('trpdd_log_user')) {
    trpdd_log_user(get_current_user_id(), 'save_req', '{}', $mid, $btype, '', '', $insert['assen']);
}

do_action('saved_req', $btype, $mid, $insert, $pa);

// Schedule WSDL sync (mirrors file-4)
$nextcron = wp_next_scheduled('ci_cron_sync_wsdl_client_req');
if (!$nextcron || $nextcron > time() + 300) {
    wp_schedule_single_event(time() + 180, 'ci_cron_sync_wsdl_client_req', [$btype]);
}

/* ── Build edit URL ───────────────────────────────────────── */
$edit_url = admin_url(
    'admin.php?page=blood_req_form&act=edit&id=' . $mid
);
do_action('drchrono_req_saved', $btype, $mid, $drchrono_patient_id, $drchrono_doctor_id, $insert);

wp_send_json_success([
    'message'  => 'Requisition submitted! Accession #: ' . esc_html($insert['assen']),
    'mid'      => $mid,
    'assen'    => $insert['assen'],
    'edit_url' => $edit_url,
    'redirect' => $edit_url,
]);
exit;
