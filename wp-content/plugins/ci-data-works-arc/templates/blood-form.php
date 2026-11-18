<?php

/**
 * Blood Requisition Form – Frontend / iFrame Template
 * Converted from admin blood form (file-1) to match PCR frontend style (file-2)
 *
 * Available from parent:
 * @var array  $patient      DrChrono patient data
 * @var array  $doctor       DrChrono doctor data
 * @var int    $doctor_id
 * @var int    $patient_id
 * @var int    $practice_id
 * @var string $nonce        wp_create_nonce('drchrono_lab_nonce')
 */

global $wpdb;

// ── Pre-fill from DrChrono ────────────────────────────────────────────────────
$pre_first   = esc_attr(trim($patient['first_name']  ?? ''));
$pre_last    = esc_attr(trim($patient['last_name']   ?? ''));
$pre_dob     = esc_attr($patient['date_of_birth']    ?? '');
$pre_gender  = esc_attr($patient['gender']           ?? '');
$pre_mrn     = esc_attr($patient['chart_id']         ?? '');
$pre_phone   = esc_attr($patient['home_phone'] ?? $patient['cell_phone'] ?? '');
$pre_email   = esc_attr($patient['email']            ?? '');
$pre_address = esc_attr($patient['address']          ?? '');
$pre_city    = esc_attr($patient['city']             ?? '');
$pre_state   = esc_attr($patient['state']            ?? '');
$pre_zip     = esc_attr($patient['zip_code']         ?? '');

$pre_doc_first = esc_attr(trim($doctor['first_name'] ?? ''));
$pre_doc_last  = esc_attr(trim($doctor['last_name']  ?? ''));
$pre_doc_npi   = esc_attr($doctor['npi_number']      ?? '');

// ── Settings ─────────────────────────────────────────────────────────────────
$settings           = get_option('ci_req_settings_blood', []);
$panel_column       = isset($settings['panel_column']) && $settings['panel_column'] >= 2
    ? (int)$settings['panel_column'] : 5;
$show_npi           = tr_get_option('ci_pdf_data', 'req_show_npi');
$enable_stat        = tr_get_option('ci_pdf_data', 'enable_stat');
$show_pregnant      = tr_get_option('ci_pdf_data', 'show_pregnant');
$show_fin           = tr_get_option('ci_pdf_data', 'show_fin');
$show_ehr           = tr_get_option('ci_pdf_data', 'show_ehr');
$hide_fasting       = tr_get_option('ci_pdf_data', 'hide_fasting', false);
$hide_insurance     = tr_get_option('ci_pdf_data', 'hide_insurance');
$hide_worker        = tr_get_option('ci_pdf_data', 'blood_hide_worker');
$enable_score       = tr_get_option('ci_pdf_data', 'enable_patient_score');
$blood_dc_required  = tr_get_option('ci_pdf_data', 'blood_dc_required');
$enable_client_bill = tr_get_option('ci_req_settings_blood', 'enable_client_bill');
$req_blood_cares    = tr_get_option('ci_pdf_data', 'req_blood_cares_act');
$show_pa_bmi        = !empty($settings['show_pa_bmi']);
$phlebotomist_mode  = !empty($settings['Phlebotomist']);
$blood_medication   = tr_get_option('ci_pdf_data', 'blood_medication');
$drop_insurance     = tr_get_option('ci_pdf_data', 'dropdown_insurance');
$insurance_partab   = tr_get_option('ci_pdf_data', 'insurance_partab');
$onlyonepanel       = isset($settings['onlyonepanel']) ? intval($settings['onlyonepanel']) : 0;
$e_orderable_test   = !empty($settings['e_orderable_test']);

// ── Clinics ───────────────────────────────────────────────────────────────────
$clinics = $wpdb->get_results(
    "SELECT cID, cli_clinic FROM ci_clinic WHERE cli_status=1 ORDER BY cli_clinic ASC",
    ARRAY_A
);

// ── Test panels / groups ──────────────────────────────────────────────────────
$profiles_tests = tr_pdd_get_profiles_tests_requesteds('blood', true, false, date('Y-m-d'));

$all_groups_meta = [];
$groups_raw = $wpdb->get_results(
    "SELECT tg_ID, tg_name, tg_label_name, tg_bgcolor, tg_show_req,
            tg_parent, tg_relate_to, tg_only_group, tg_notestcheckbox,
            tg_show_cli_IDs, tg_notshow_cli_IDs, tg_other_options, tg_position
       FROM ci_test_groups
      WHERE tg_type = 'blood'
      ORDER BY tg_position ASC, tg_ID ASC",
    ARRAY_A
);
foreach ($groups_raw as $g) {
    $all_groups_meta[(int)$g['tg_ID']] = $g;
}

$specimens_by_group = [];
foreach (
    $wpdb->get_results(
        "SELECT ts_group_id, ts_name FROM ci_test_specimens WHERE ts_type='blood'",
        ARRAY_A
    ) as $s
) {
    $specimens_by_group[(int)$s['ts_group_id']][] = $s['ts_name'];
}

$render_group_ids = [];
foreach ($groups_raw as $g) {
    if (!$g['tg_show_req']) continue;
    $render_group_ids[] = (int)$g['tg_ID'];
}

// ── Diagnostic codes ──────────────────────────────────────────────────────────
$diag_codes = function_exists('tr_pdd_get_dagnostic_codes')
    ? tr_pdd_get_dagnostic_codes('blood') : [];

// ── Medication groups ─────────────────────────────────────────────────────────
$mgroups = [];
if ($blood_medication) {
    $mgroups = $wpdb->get_results(
        "SELECT mg_ID, mg_name FROM ci_medication_groups WHERE mg_btype='blood' ORDER BY mg_name ASC"
    );
}

// ── Specimen types ────────────────────────────────────────────────────────────
$specimen_types = function_exists('tr_pdd_get_type_of_specimens')
    ? tr_pdd_get_type_of_specimens('blood')
    : ['blood' => 'Blood', 'serum' => 'Serum'];

$us_states = tr_get_states();

// ── Contrast helper ───────────────────────────────────────────────────────────
if (!function_exists('blood_front_contrast')) {
    function blood_front_contrast(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        if (strlen($hex) !== 6) return '#000000';
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255 > 0.5 ? '#1a1a1a' : '#ffffff';
    }
}
?>

<div class="req-form-wrap" id="req-form-wrap">
    <form id="req-requisition-form" novalidate autocomplete="off">

        <!-- SECTION 1: PRACTICE & PHYSICIAN -->
        <div class="req-section">
            <div class="req-section__head">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                    <polyline points="9 22 9 12 15 12 15 22" />
                </svg>
                Practice &amp; Physician
            </div>
            <div class="req-section__body">
                <div class="req-g4">
                    <div class="req-field">
                        <label class="req-lbl" for="cli_ID">Practice / Clinic<span class="req">*</span></label>
                        <select name="bat[cli_ID]" id="cli_ID" class="req-select" required>
                            <option value="">— Select Clinic —</option>
                            <?php foreach ($clinics as $c): ?>
                                <option value="<?php echo (int)$c['cID'] ?>"><?php echo esc_html($c['cli_clinic']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="doc_first">Physician First Name<span class="req">*</span></label>
                        <input type="text" id="doc_first" name="doc[doc_first]" class="req-input" value="<?php echo $pre_doc_first ?>" required>
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="doc_last">Physician Last Name<span class="req">*</span></label>
                        <input type="text" id="doc_last" name="doc[doc_last]" class="req-input" value="<?php echo $pre_doc_last ?>" required>
                    </div>
                    <?php if ($show_npi): ?>
                        <div class="req-field">
                            <label class="req-lbl" for="doc_npi">NPI Number</label>
                            <input type="text" id="doc_npi" name="doc[doc_npi]" class="req-input" value="<?php echo $pre_doc_npi ?>" maxlength="10">
                        </div>
                    <?php endif; ?>
                    <div class="req-field">
                        <label class="req-lbl" for="bat_doc">Date of Collection<span class="req">*</span></label>
                        <input type="datetime-local" id="bat_doc" name="bat[doc]" class="req-input" value="<?php echo date('Y-m-d\TH:i') ?>" required>
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="col_name"><?php echo $phlebotomist_mode ? 'Phlebotomist' : 'Collector Name' ?></label>
                        <input type="text" id="col_name" name="col[col_name]" class="req-input">
                        <input type="hidden" name="col[col_ID]" value="0">
                    </div>
                    <?php if ($phlebotomist_mode): ?>
                        <div class="req-field">
                            <label class="req-lbl" for="col_code">Phlebotomist Code</label>
                            <input type="text" id="col_code" name="col[col_phlebotomist_code]" class="req-input">
                        </div>
                    <?php else: ?>
                        <div class="req-field">
                            <label class="req-lbl" for="col_phone">Collector Phone</label>
                            <input type="tel" id="col_phone" name="col[col_phone]" class="req-input" maxlength="15">
                        </div>
                    <?php endif; ?>
                    <?php if (!$hide_fasting): ?>
                        <div class="req-field">
                            <label class="req-lbl">Fasting</label>
                            <div style="display:flex;gap:.9rem;align-items:center;padding-top:.3rem;">
                                <label class="req-chklbl"><input type="radio" name="bat[fasting]" value="0" checked> No</label>
                                <label class="req-chklbl"><input type="radio" name="bat[fasting]" value="1"> Yes</label>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($enable_stat): ?>
                        <div class="req-field">
                            <label class="req-lbl">STAT</label>
                            <label class="req-chklbl" style="padding-top:.35rem;">
                                <input type="hidden" name="bat[stat]" value="0">
                                <input type="checkbox" name="bat[stat]" value="1"> STAT Order
                            </label>
                        </div>
                    <?php endif; ?>
                </div>
                <div style="margin-top:.75rem;">
                    <label class="req-lbl">Specimen Type</label>
                    <div style="display:flex;flex-wrap:wrap;gap:.75rem;margin-top:.35rem;">
                        <?php foreach ($specimen_types as $k => $vl): ?>
                            <label class="req-chklbl">
                                <input type="checkbox" name="bat[type_of_specimen][]" value="<?php echo esc_attr($k) ?>">
                                <?php echo esc_html($vl) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 2: PATIENT INFORMATION -->
        <div class="req-section">
            <div class="req-section__head">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                    <circle cx="12" cy="7" r="4" />
                </svg>
                Patient Information
            </div>
            <div class="req-section__body">
                <div class="req-g4">
                    <div class="req-field">
                        <label class="req-lbl" for="pa_last">Last Name<span class="req">*</span></label>
                        <input type="text" id="pa_last" name="pa[pa_last]" class="req-input" value="<?php echo $pre_last ?>" required>
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="pa_first">First Name<span class="req">*</span></label>
                        <input type="text" id="pa_first" name="pa[pa_first]" class="req-input" value="<?php echo $pre_first ?>" required>
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="pa_middle">Middle Initial</label>
                        <input type="text" id="pa_middle" name="pa[pa_middle]" class="req-input" maxlength="2">
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="pa_dob">Date of Birth<span class="req">*</span></label>
                        <input type="date" id="pa_dob" name="pa[pa_dob]" class="req-input" value="<?php echo $pre_dob ?>" required>
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="pa_gender">Gender<span class="req">*</span></label>
                        <select id="pa_gender" name="pa[pa_gender]" class="req-select" required>
                            <option value="">— Select —</option>
                            <?php
                            $genders = function_exists('trpdd_genders') ? trpdd_genders() : ['M' => 'Male', 'F' => 'Female', 'U' => 'Unknown'];
                            foreach ($genders as $gk => $gv):
                            ?>
                                <option value="<?php echo esc_attr($gk) ?>" <?php selected($pre_gender, $gv) ?>><?php echo esc_html($gv) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="pa_phone">Phone<span class="req">*</span></label>
                        <input type="tel" id="pa_phone" name="pa[pa_phone]" class="req-input" value="<?php echo $pre_phone ?>" required maxlength="15">
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="pa_email">Email</label>
                        <input type="email" id="pa_email" name="pa[pa_email]" class="req-input" value="<?php echo $pre_email ?>">
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="pa_mrn">Medical Record #</label>
                        <input type="text" id="pa_mrn" name="pa[pa_mrn]" class="req-input" value="<?php echo $pre_mrn ?>" maxlength="15">
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="pa_ssn">SSN</label>
                        <input type="text" id="pa_ssn" name="pa[pa_ssn]" class="req-input" maxlength="12" placeholder="XXX-XX-XXXX">
                    </div>
                    <div class="req-field req-span2">
                        <label class="req-lbl" for="pa_address">Street Address<span class="req">*</span></label>
                        <input type="text" id="pa_address" name="pa[pa_address]" class="req-input" value="<?php echo $pre_address ?>" required maxlength="150">
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="pa_city">City<span class="req">*</span></label>
                        <input type="text" id="pa_city" name="pa[pa_city]" class="req-input" value="<?php echo $pre_city ?>" required>
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="pa_state">State<span class="req">*</span></label>
                        <select id="pa_state" name="pa[pa_state]" class="req-select" required>
                            <option value="">— Select —</option>
                            <?php foreach ($us_states as $abbr => $name): ?>
                                <option value="<?php echo $abbr ?>" <?php selected($pre_state, $abbr) ?>><?php echo $abbr ?> — <?php echo $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="pa_zip">ZIP<span class="req">*</span></label>
                        <input type="text" id="pa_zip" name="pa[pa_zip]" class="req-input" value="<?php echo $pre_zip ?>" required maxlength="12">
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="pa_race">Race</label>
                        <select id="pa_race" name="pa[pa_race]" class="req-select">
                            <option value="">— Select —</option>
                            <option value="1002-5">American Indian or Alaska Native</option>
                            <option value="2028-9">Asian</option>
                            <option value="2054-5">Black / African American</option>
                            <option value="2076-8">Native Hawaiian or Other Pacific Islander</option>
                            <option value="2106-3">White</option>
                            <option value="2131-1">Other</option>
                        </select>
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="pa_ethnicity">Ethnicity</label>
                        <select id="pa_ethnicity" name="pa[pa_ethnicity]" class="req-select">
                            <option value="">— Select —</option>
                            <option value="H">Hispanic or Latino</option>
                            <option value="N">Not Hispanic or Latino</option>
                            <option value="U">Unknown</option>
                        </select>
                    </div>
                    <?php if ($show_pregnant): ?>
                        <div class="req-field">
                            <label class="req-lbl" for="bat_pregnant">Pregnant</label>
                            <select id="bat_pregnant" name="bat[p_pregnant]" class="req-select">
                                <option value="">— Select —</option>
                                <option value="Y">Yes</option>
                                <option value="N">No</option>
                            </select>
                        </div>
                    <?php endif; ?>
                    <?php if ($show_fin): ?>
                        <div class="req-field">
                            <label class="req-lbl" for="bat_fin">FIN</label>
                            <input type="text" id="bat_fin" name="bat[p_fin]" class="req-input" maxlength="50">
                        </div>
                    <?php endif; ?>
                    <?php if ($show_ehr): ?>
                        <div class="req-field">
                            <label class="req-lbl" for="bat_ehr">EHR</label>
                            <input type="text" id="bat_ehr" name="bat[p_ehr]" class="req-input" maxlength="50">
                        </div>
                    <?php endif; ?>
                    <?php if ($show_pa_bmi): ?>
                        <div class="req-field">
                            <label class="req-lbl" for="pa_height">Height (inches)</label>
                            <input type="text" id="pa_height" name="pa[pa_height]" class="req-input" maxlength="10">
                        </div>
                        <div class="req-field">
                            <label class="req-lbl" for="pa_weight">Weight (lbs)</label>
                            <input type="text" id="pa_weight" name="pa[pa_weight]" class="req-input" maxlength="10">
                        </div>
                        <div class="req-field">
                            <label class="req-lbl" for="pa_marital">Marital Status</label>
                            <select id="pa_marital" name="pa[pa_marital_status]" class="req-select">
                                <option value="">— Select —</option>
                                <option value="s">Single</option>
                                <option value="m">Married</option>
                                <option value="d">Decline to respond</option>
                                <option value="o">Other</option>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($enable_score): ?>
                    <div style="margin-top:.75rem;">
                        <div class="req-g4">
                            <div class="req-field">
                                <label class="req-lbl" for="p_pain_score">Pain Score</label>
                                <select id="p_pain_score" name="bat[p_pain_score]" class="req-select">
                                    <option value="">—</option>
                                    <?php for ($i = 0; $i <= 10; $i++): ?><option value="<?php echo $i ?>"><?php echo $i ?></option><?php endfor; ?>
                                </select>
                            </div>
                            <div class="req-field">
                                <label class="req-lbl" for="p_fatigue_score">Fatigue Score</label>
                                <select id="p_fatigue_score" name="bat[p_fatigue_score]" class="req-select">
                                    <option value="">—</option>
                                    <?php for ($i = 0; $i <= 10; $i++): ?><option value="<?php echo $i ?>"><?php echo $i ?></option><?php endfor; ?>
                                </select>
                            </div>
                            <div class="req-field">
                                <label class="req-lbl" for="p_condition">Reason for Testing</label>
                                <select id="p_condition" name="bat[p_condition]" class="req-select">
                                    <option value="">— Select —</option>
                                    <?php foreach (['Baseline', 'Therapy Start', 'Monitor Therapy', 'Acute Crisis'] as $c): ?>
                                        <option value="<?php echo $c ?>"><?php echo $c ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="req-field">
                                <label class="req-lbl" for="p_hemoglobin">Hemoglobin</label>
                                <input type="text" id="p_hemoglobin" name="bat[p_hemoglobin]" class="req-input" maxlength="40">
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- SECTION 3: INSURANCE / BILLING -->
        <?php if (!$hide_insurance): ?>
            <div class="req-section">
                <div class="req-section__head">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                    </svg>
                    Insurance / Billing
                </div>
                <div class="req-section__body">
                    <div style="display:flex;gap:1.2rem;flex-wrap:wrap;margin-bottom:.85rem;">
                        <label class="req-chklbl"><input type="radio" name="bat[ins_comp]" value="ins" id="ins_type_ins" checked> Primary Insurance</label>
                        <?php if (!$hide_worker): ?>
                            <label class="req-chklbl"><input type="radio" name="bat[ins_comp]" value="comp"> Worker's Comp / Auto</label>
                        <?php endif; ?>
                        <label class="req-chklbl"><input type="radio" name="bat[ins_comp]" value="cash"> No Insurance – Cash</label>
                        <?php if ($enable_client_bill): ?>
                            <label class="req-chklbl"><input type="radio" name="bat[ins_comp]" value="client"> Client Bill</label>
                        <?php endif; ?>
                        <?php if ($req_blood_cares): ?>
                            <label class="req-chklbl">
                                <input type="hidden" name="ins1[ins_cares_act]" value="0">
                                <input type="checkbox" name="ins1[ins_cares_act]" value="1"> No Insurance – CARES Act
                            </label>
                        <?php endif; ?>
                    </div>

                    <!-- Primary insurance fields -->
                    <div id="ins-primary-fields">
                        <div class="req-g3">
                            <div class="req-field">
                                <label class="req-lbl" for="ins_last">Insured Last Name<span class="req ins-req">*</span></label>
                                <input type="text" id="ins_last" name="ins1[ins_last]" class="req-input ins-required" required>
                                <input type="hidden" name="ins1[ins_ID]" value="0">
                            </div>
                            <div class="req-field">
                                <label class="req-lbl" for="ins_first">Insured First Name<span class="req ins-req">*</span></label>
                                <input type="text" id="ins_first" name="ins1[ins_first]" class="req-input ins-required" required>
                            </div>
                            <div class="req-field">
                                <label class="req-lbl">Relation to Patient<span class="req ins-req">*</span></label>
                                <div style="display:flex;gap:.8rem;align-items:center;padding-top:.3rem;">
                                    <label class="req-chklbl"><input type="radio" name="ins1[ins_relation]" value="Self" id="ins_rel_self" checked> Self</label>
                                    <label class="req-chklbl"><input type="radio" name="ins1[ins_relation]" value="Parent"> Parent</label>
                                    <label class="req-chklbl"><input type="radio" name="ins1[ins_relation]" value="Spouse"> Spouse</label>
                                </div>
                            </div>
                            <div class="req-field">
                                <label class="req-lbl" for="ins_company">Insurance Company<span class="req ins-req">*</span></label>
                                <?php if ($drop_insurance): ?>
                                    <select id="ins_company" name="ins1[ins_company]" class="req-select ins-required" required>
                                        <option value="">— Select —</option>
                                        <?php
                                        $payers = $wpdb->get_results("SELECT payer_name FROM ci_payer_list ORDER BY payer_name ASC");
                                        foreach ($payers as $pay):
                                        ?>
                                            <option value="<?php echo esc_attr($pay->payer_name) ?>"><?php echo esc_html($pay->payer_name) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="text" id="ins_company" name="ins1[ins_company]" class="req-input ins-required" required>
                                <?php endif; ?>
                            </div>
                            <div class="req-field">
                                <label class="req-lbl" for="ins_member_id">Member ID<span class="req ins-req">*</span></label>
                                <input type="text" id="ins_member_id" name="ins1[ins_member_id]" class="req-input ins-required" required maxlength="24">
                            </div>
                            <div class="req-field">
                                <label class="req-lbl" for="ins_group_id">Group #</label>
                                <input type="text" id="ins_group_id" name="ins1[ins_group_id]" class="req-input" maxlength="20">
                            </div>
                            <div class="req-field">
                                <label class="req-lbl" for="ins_address">Street Address</label>
                                <input type="text" id="ins_address" name="ins1[ins_address]" class="req-input">
                            </div>
                            <div class="req-field">
                                <label class="req-lbl" for="ins_city">City</label>
                                <input type="text" id="ins_city" name="ins1[ins_city]" class="req-input">
                            </div>
                            <div class="req-field">
                                <label class="req-lbl" for="ins_state">State</label>
                                <select id="ins_state" name="ins1[ins_state]" class="req-select">
                                    <option value="">—</option>
                                    <?php foreach ($us_states as $abbr => $name): ?>
                                        <option value="<?php echo $abbr ?>"><?php echo $abbr ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="req-field">
                                <label class="req-lbl" for="ins_zip">ZIP</label>
                                <input type="text" id="ins_zip" name="ins1[ins_zip]" class="req-input" maxlength="12">
                            </div>
                            <div class="req-field">
                                <label class="req-lbl" for="ins_phone">Insurance Phone</label>
                                <input type="tel" id="ins_phone" name="ins1[ins_phone]" class="req-input">
                            </div>
                            <div class="req-field" id="ins1_dob_field" style="display:none">
                                <label class="req-lbl" for="ins1_dob">Parent DOB</label>
                                <input type="date" id="ins1_dob" name="ins1[ins_dob]" class="req-input">
                            </div>
                            <div class="req-field">
                                <label class="req-lbl" for="ins_email">Insurance Email</label>
                                <input type="email" id="ins_email" name="ins1[ins_email]" class="req-input">
                            </div>
                            <div class="req-field">
                                <label class="req-lbl" for="ins_auth">Pre-authorization</label>
                                <div style="display:flex;gap:.9rem;align-items:center;padding-top:.3rem;">
                                    <label class="req-chklbl"><input type="radio" name="ins1[ins_authorization]" value="Y"> Yes</label>
                                    <label class="req-chklbl"><input type="radio" name="ins1[ins_authorization]" value="N" checked> No</label>
                                </div>
                            </div>
                            <div class="req-field">
                                <label class="req-lbl" for="ins_auth_id">Authorization #</label>
                                <input type="text" id="ins_auth_id" name="ins1[ins_authorization_id]" class="req-input">
                            </div>
                            <div class="req-field">
                                <label class="req-lbl" for="ins_auth_date">Authorization Date</label>
                                <input type="date" id="ins_auth_date" name="ins1[ins_authorization_date]" class="req-input">
                            </div>
                            <?php if ($insurance_partab): ?>
                                <div class="req-field">
                                    <label class="req-lbl">Medicare Part</label>
                                    <div style="display:flex;gap:.9rem;padding-top:.3rem;">
                                        <label class="req-chklbl"><input type="hidden" name="ins1[ins_part_A]" value="0"><input type="checkbox" name="ins1[ins_part_A]" value="1"> Part A</label>
                                        <label class="req-chklbl"><input type="hidden" name="ins1[ins_part_B]" value="0"><input type="checkbox" name="ins1[ins_part_B]" value="1"> Part B</label>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Secondary Insurance -->
                        <div style="margin-top:1rem;padding-top:.75rem;border-top:1px solid var(--req-border);">
                            <p style="font-size:.74rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--req-muted);margin:0 0 .65rem;">Secondary Insurance (optional)</p>
                            <div class="req-g3">
                                <div class="req-field"><label class="req-lbl">Last Name</label><input type="text" name="ins2[ins_last]" class="req-input"><input type="hidden" name="ins2[ins_ID]" value="0"></div>
                                <div class="req-field"><label class="req-lbl">First Name</label><input type="text" name="ins2[ins_first]" class="req-input"></div>
                                <div class="req-field">
                                    <label class="req-lbl">Relation</label>
                                    <div style="display:flex;gap:.8rem;align-items:center;padding-top:.3rem;">
                                        <label class="req-chklbl"><input type="radio" name="ins2[ins_relation]" value="Self"> Self</label>
                                        <label class="req-chklbl"><input type="radio" name="ins2[ins_relation]" value="Parent"> Parent</label>
                                        <label class="req-chklbl"><input type="radio" name="ins2[ins_relation]" value="Spouse"> Spouse</label>
                                    </div>
                                </div>
                                <div class="req-field"><label class="req-lbl">Company</label><input type="text" name="ins2[ins_company]" class="req-input"></div>
                                <div class="req-field"><label class="req-lbl">Member ID</label><input type="text" name="ins2[ins_member_id]" class="req-input" maxlength="24"></div>
                                <div class="req-field"><label class="req-lbl">Group #</label><input type="text" name="ins2[ins_group_id]" class="req-input" maxlength="20"></div>
                                <div class="req-field"><label class="req-lbl">Phone</label><input type="tel" name="ins2[ins_phone]" class="req-input"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Worker's Comp fields -->
                    <?php if (!$hide_worker): ?>
                        <div id="ins-worker-fields" style="display:none;">
                            <div class="req-g3">
                                <div class="req-field"><label class="req-lbl">Subscriber Last Name</label><input type="text" name="bat[subscriber_last]" class="req-input"></div>
                                <div class="req-field"><label class="req-lbl">Subscriber First Name</label><input type="text" name="bat[subscriber_first]" class="req-input"></div>
                                <div class="req-field"><label class="req-lbl">Date of Injury</label><input type="date" name="bat[date_of_injury]" class="req-input"></div>
                                <div class="req-field req-span2"><label class="req-lbl">Address of Claim</label><input type="text" name="bat[address_of_claim]" class="req-input"></div>
                                <div class="req-field"><label class="req-lbl">Zip</label><input type="text" name="bat[subscriber_zip]" class="req-input"></div>
                                <div class="req-field req-span2"><label class="req-lbl">Insurance</label><input type="text" name="bat[subscriber_insurance]" class="req-input"></div>
                                <div class="req-field"><label class="req-lbl">WC#</label><input type="text" name="bat[subscriber_wc]" class="req-input"></div>
                                <div class="req-field req-span2"><label class="req-lbl">Attorney</label><input type="text" name="bat[attorney_name]" class="req-input"></div>
                                <div class="req-field"><label class="req-lbl">Attorney Phone</label><input type="tel" name="bat[attorney_phone]" class="req-input"></div>
                                <div class="req-field req-span2"><label class="req-lbl">Adjuster Name</label><input type="text" name="bat[adjuster_name]" class="req-input"></div>
                                <div class="req-field"><label class="req-lbl">Adjuster Phone</label><input type="tel" name="bat[adjuster_phone]" class="req-input"></div>
                                <div class="req-field"><label class="req-lbl">Claim Number</label><input type="text" name="bat[claim_number]" class="req-input"></div>
                                <div class="req-field">
                                    <label class="req-lbl">Claim Under Litigation?</label>
                                    <div style="display:flex;gap:.9rem;padding-top:.3rem;">
                                        <label class="req-chklbl"><input type="radio" name="bat[claim_under_litigation]" value="Y"> Yes</label>
                                        <label class="req-chklbl"><input type="radio" name="bat[claim_under_litigation]" value="N" checked> No</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; // !$hide_insurance 
        ?>

        <!-- SECTION 4: INSURANCE CARD & ID UPLOAD -->
        <div class="req-section">
            <div class="req-section__head">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="5" width="20" height="14" rx="2" />
                    <line x1="2" y1="10" x2="22" y2="10" />
                </svg>
                Insurance Card &amp; Photo ID
            </div>
            <div class="req-section__body">
                <p style="font-size:12px;color:var(--req-muted);margin:0 0 .75rem;">Upload both sides of the patient's insurance card and photo ID.</p>
                <div class="req-g4">
                    <?php
                    $uploads = [
                        'ins_card_front'   => ['icon' => '💳', 'label' => 'Ins Card Front',  'accept' => 'image/*,application/pdf'],
                        'ins_card_back'    => ['icon' => '💳', 'label' => 'Ins Card Back',   'accept' => 'image/*,application/pdf'],
                        'patient_id_front' => ['icon' => '🪪', 'label' => 'Patient ID Front', 'accept' => 'image/*'],
                        'patient_id_back'  => ['icon' => '🪪', 'label' => 'Patient ID Back', 'accept' => 'image/*'],
                    ];
                    foreach ($uploads as $fname => $udata):
                    ?>
                        <label class="req-file-btn" id="lbl_<?php echo $fname ?>" for="file_<?php echo $fname ?>">
                            <span style="font-size:22px;"><?php echo $udata['icon'] ?></span>
                            <span id="lbl_txt_<?php echo $fname ?>"><?php echo $udata['label'] ?></span>
                            <input type="file" id="file_<?php echo $fname ?>" name="<?php echo $fname ?>"
                                accept="<?php echo $udata['accept'] ?>" style="display:none">
                            <input type="hidden" name="img_<?php echo $fname ?>" value="">
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- SECTION 5: DIAGNOSTIC CODES -->
        <?php if (!empty($diag_codes)): ?>
            <div class="req-section">
                <div class="req-section__head">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 11l3 3L22 4" />
                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
                    </svg>
                    Diagnostic Codes (ICD-10)<?php if ($blood_dc_required): ?><span class="req" style="margin-left:.3rem">*</span><?php endif; ?>
                </div>
                <div class="req-section__body">
                    <div id="diag-chips" style="display:flex;flex-wrap:wrap;gap:.35rem;min-height:22px;margin-bottom:.6rem;"></div>
                    <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
                        <select id="diag-select" class="req-select" style="max-width:310px;">
                            <option value="">— Select code —</option>
                            <?php foreach ($diag_codes as $code => $label): ?>
                                <?php if (empty($code)) continue; ?>
                                <option value="<?php echo esc_attr($code) ?>"><?php echo esc_html($code . ' – ' . $label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" id="diag-custom" class="req-input" style="max-width:150px;" placeholder="Enter additional code">
                        <button type="button" class="req-btn req-btn--secondary" id="diag-add-btn" style="padding:.4rem .75rem;">+ Add</button>
                    </div>
                    <div id="diag-hidden"></div>
                    <?php if ($blood_dc_required): ?>
                        <p id="diag-error" style="color:var(--req-danger);font-size:12px;display:none;margin-top:.35rem;">Please add at least one diagnostic code.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- SECTION 6: BLOOD TESTS -->
        <div class="req-section">
            <div class="req-section__head">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M8 3l4 8 5-5 5 15H2L8 3z" />
                </svg>
                Blood Testing Requested
            </div>
            <div class="req-section__body">
                <p style="font-size:12px;color:var(--req-muted);margin:0 0 .75rem;">
                    NOTE: Only tests medically necessary for diagnosis/treatment may be submitted for Medicare/Medicaid reimbursement.
                </p>
                <input type="hidden" name="has_panel_requests" value="1">

                <div class="req-panel-masonry" id="req-panel-masonry" style="column-count:<?php echo $panel_column ?>;">
                    <?php foreach ($render_group_ids as $gid):
                        $group = $all_groups_meta[$gid] ?? null;
                        if (!$group) continue;

                        $bgcolor  = !empty($group['tg_bgcolor']) ? $group['tg_bgcolor'] : '#3b82f6';
                        $fgcolor  = blood_front_contrast($bgcolor);
                        $bg_soft  = $bgcolor . '18';
                        $bg_spec  = $bgcolor . '44';
                        $opts     = @json_decode($group['tg_other_options'], true) ?: [];
                        $desc     = $opts['desc'] ?? '';
                        $tests    = $profiles_tests[$gid] ?? [];
                        $specimens = $specimens_by_group[$gid] ?? [];
                        if (!empty($group['tg_only_group'])) $tests = [];

                        $gclass = 'req-groupparent';
                        if (!empty($group['tg_show_cli_IDs'])) {
                            $gclass .= ' by_clinic';
                            foreach (explode(',', $group['tg_show_cli_IDs']) as $pcid) {
                                $gclass .= ' cli_id_' . trim($pcid);
                            }
                        }
                        if (!empty($group['tg_notshow_cli_IDs'])) {
                            $gclass .= ' not_by_clinic';
                            foreach (explode(',', $group['tg_notshow_cli_IDs']) as $pcid) {
                                $gclass .= ' not_cli_id_' . trim($pcid);
                            }
                        }
                        if ((int)$group['tg_parent'] > 0) $gclass .= ' parent_group_' . $group['tg_parent'];
                        if (!empty($group['tg_relate_to'])) $gclass .= ' relate_to_' . $group['tg_relate_to'];
                    ?>
                        <div class="<?php echo $gclass ?>" data-gid="<?php echo $gid ?>" style="background:<?php echo $bg_soft ?>;">
                            <label class="req-group-head" style="background:<?php echo $bgcolor ?>;color:<?php echo $fgcolor ?>;">
                                <input type="checkbox"
                                    class="batcustom_profile cb_test"
                                    name="bat[panel_tests_requested][]"
                                    value="GROUP_<?php echo $gid ?>"
                                    data-relate_to="<?php echo esc_attr($group['tg_relate_to']) ?>"
                                    data-grouprint="<?php echo esc_attr($group['tg_label_name']) ?>">
                                <?php echo esc_html($group['tg_name']) ?>
                            </label>
                            <div class="req-group-tests">
                                <?php if ($desc): ?>
                                    <div class="req-panel-desc"><?php echo wp_kses_post($desc) ?></div>
                                <?php endif; ?>
                                <?php
                                $seen_sub = [];
                                foreach ($tests as $t):
                                    if (empty($t->te_ID)) continue;
                                    $tgid_row = (int)($t->tg_ID ?? $gid);
                                    $no_cb = !empty($t->te_nocheckbox) || !empty($group['tg_notestcheckbox']);
                                    if ($tgid_row !== $gid && !in_array($tgid_row, $seen_sub)):
                                        $seen_sub[] = $tgid_row;
                                ?>
                                        <div class="req-subgroup-head"><?php echo esc_html($t->main_group ?? '') ?></div>
                                    <?php endif; ?>
                                    <div class="req-test-row <?php echo $no_cb ? 'nocb' : '' ?>">
                                        <?php if (!$no_cb): ?>
                                            <input type="checkbox" class="cb_test" name="bat[panel_tests_requested][]"
                                                value="<?php echo esc_attr($t->te_ID) ?>" data-group="<?php echo $gid ?>">
                                        <?php endif; ?>
                                        <span><?php echo esc_html($t->te_name) ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (!empty($specimens)): ?>
                                    <div class="req-specimens" data-group="<?php echo $gid ?>">
                                        <span class="req-spec-title" style="background:<?php echo $bg_spec ?>;color:<?php echo $fgcolor ?>;">SPECIMEN SOURCE</span>
                                        <?php foreach ($specimens as $spec): ?>
                                            <label class="req-chklbl">
                                                <input type="checkbox" name="bat[type_of_specimen][]" value="<?php echo esc_attr($spec) ?>">
                                                <?php echo esc_html($spec) ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($e_orderable_test): ?>
                    <div style="margin-top:.5rem;">
                        <label class="req-chklbl">
                            <input type="hidden" name="bat[orderable_test]" value="0">
                            <input type="checkbox" class="cb_test" name="bat[orderable_test]" value="1"> Orderable Test
                        </label>
                    </div>
                <?php endif; ?>

                <p id="req-panel-error" style="color:var(--req-danger);font-size:12px;margin-top:.5rem;display:none;">
                    Please select at least one test or panel.
                </p>
            </div>
        </div>

        <!-- SECTION 7: MEDICATIONS -->
        <?php
        if ($blood_medication && !empty($mgroups)):
            $has_any_med = false;
            ob_start();
            foreach ($mgroups as $mgroup):
                $medications = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM ci_medications WHERE me_group_id = %d ORDER BY me_ID ASC",
                    $mgroup->mg_ID
                ));
                if (empty($medications)) continue;
                $has_any_med = true;
        ?>
                <div class="req-med-group">
                    <div class="req-med-group-head"><?php echo esc_html($mgroup->mg_name) ?></div>
                    <div class="req-med-group-tests">
                        <?php foreach ($medications as $med):
                            $low = strtolower($med->me_name);
                        ?>
                            <div class="req-med-item">
                                <?php if ($low === 'other'): ?>
                                    <input type="text" name="medication_other" class="req-input" placeholder="Other" style="font-size:12px;">
                                <?php elseif ($low === 'other.'): ?>
                                    <input type="text" name="medication_other2" class="req-input" placeholder="Other" style="font-size:12px;">
                                <?php else: ?>
                                    <label class="req-chklbl">
                                        <input type="checkbox" name="bat[medication][]" data-key="<?php echo esc_attr($med->me_name) ?>" value="<?php echo esc_attr($med->me_name) ?>">
                                        <?php echo esc_html($med->me_name) ?>
                                    </label>
                                    <?php if (!empty($settings['medication_date']) && stripos($mgroup->mg_name, 'Other Therapy') === false): ?>
                                        <div class="req-date-range">
                                            <input type="date" class="req-input mstart_date" style="font-size:11px;padding:.28rem .45rem;" placeholder="Start">
                                            <input type="date" class="req-input mend_date" style="font-size:11px;padding:.28rem .45rem;" placeholder="End">
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach;
            $med_html = ob_get_clean();
            if ($has_any_med):
            ?>
                <div class="req-section">
                    <div class="req-section__head">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="9" cy="9" r="5" />
                            <path d="M13 13l6 6" />
                        </svg>
                        Medications
                    </div>
                    <div class="req-section__body">
                        <input type="hidden" name="has_edit_medication" value="1">
                        <div class="req-med-grid"><?php echo $med_html ?></div>
                    </div>
                </div>
        <?php endif;
        endif; ?>
        <div class="req-section" id="req-aoe-wrap">
            <div class="req-section__head">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10" />
                    <path d="M12 8v4M12 16h.01" />
                </svg>
                Additional Order Entry (AOE)
            </div>
            <div class="req-section__body req-aoe-body">
                <?php include __DIR__ . '/aoe-questions.php'; ?>
            </div>
        </div>

        <?php include('box_signature.php'); ?>

        <div class="req-section">
            <div class="req-section__head">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                    <polyline points="17 8 12 3 7 8" />
                    <line x1="12" y1="3" x2="12" y2="15" />
                </svg>
                Original Req &amp; Results Upload
            </div>
            <div class="req-section__body">
                <div class="req-g3">
                    <div class="req-field">
                        <label class="req-lbl">Result PDF</label>
                        <div class="req-upload-box" id="upload_result_pdf">
                            <label class="req-file-btn" id="lbl_result_pdf" for="file_result_pdf">
                                <svg style="width:20px;height:20px;opacity:.6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                    <polyline points="14 2 14 8 20 8" />
                                </svg>
                                <span id="lbl_txt_result_pdf">Choose Result PDF</span>
                                <input type="file" id="file_result_pdf" name="result_pdfs" accept="application/pdf,.pdf" multiple style="display:none">
                            </label>
                            <input type="hidden" class="result_value" name="bat[result_pdf]" value="" id="val_result_pdf">
                            <ul class="req-file-list" id="list_result_pdf"></ul>
                        </div>
                    </div>
                    <div class="req-field">
                        <label class="req-lbl">Paper Req</label>
                        <div class="req-upload-box" id="upload_original_req">
                            <label class="req-file-btn" id="lbl_original_req" for="file_original_req">
                                <svg style="width:20px;height:20px;opacity:.6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                    <polyline points="14 2 14 8 20 8" />
                                </svg>
                                <span id="lbl_txt_original_req">Choose Paper Req</span>
                                <input type="file" id="file_original_req" name="original_reqs" accept="application/pdf,image/*" multiple style="display:none">
                            </label>
                            <input type="hidden" class="result_value" name="bat[original_req]" value="" id="val_original_req">
                            <ul class="req-file-list" id="list_original_req"></ul>
                        </div>
                    </div>

                    <div class="req-field">
                        <label class="req-lbl">Issue Photo</label>
                        <label class="req-file-btn <?php if (!empty($bat['issue_photo'])) echo 'has-file' ?>"
                            id="lbl_issue_photo" for="file_issue_photo">
                            <svg style="width:20px;height:20px;opacity:.6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                <circle cx="8.5" cy="8.5" r="1.5" />
                                <polyline points="21 15 16 10 5 21" />
                            </svg>
                            <span id="lbl_txt_issue_photo">
                                <?php echo !empty($bat['issue_photo']) ? esc_html($bat['issue_photo']) : 'Upload Issue Photo' ?>
                            </span>
                            <input type="file" id="file_issue_photo" name="issue_photo" accept="image/*" style="display:none">
                        </label>
                        <input type="hidden" name="bat[issue_photo]" value="<?php echo isset($bat['issue_photo']) ? esc_attr($bat['issue_photo']) : '' ?>" id="val_issue_photo">
                    </div>

                </div>
            </div>
        </div>
        <!-- SECTION 8: NOTES & STATUS -->
        <div class="req-section">
            <div class="req-section__head">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                </svg>
                Notes &amp; Status
            </div>
            <div class="req-section__body">
                <div class="req-g2">
                    <div class="req-field">
                        <label class="req-lbl" for="bat_notes">Physician Notes</label>
                        <textarea id="bat_notes" name="bat[notes]" class="req-textarea" placeholder="Clinical notes…"></textarea>
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="bat_notes_report">Notes (visible on report)</label>
                        <textarea id="bat_notes_report" name="bat[notes_report]" class="req-textarea" placeholder="Notes shown on patient report…"></textarea>
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="bat_billing_notes">Billing Notes</label>
                        <textarea id="bat_billing_notes" name="bat[billing_notes]" class="req-textarea" maxlength="200" placeholder="Billing notes…"></textarea>
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="bat_status">Status</label>
                        <?php
                        $status_options = function_exists('tr_pdd_get_batch_statuses')
                            ? tr_pdd_get_batch_statuses_on_hold(tr_pdd_get_batch_statuses(false, false, false, 'blood'), '', '', 'ci_batches_blood', 'blood')
                            : ['received' => 'Received'];
                        ?>
                        <select id="bat_status" name="bat[batch_status]" class="req-select">
                            <?php foreach ($status_options as $skey => $slabel): ?>
                                <option value="<?php echo $skey ?>"><?php echo esc_html($slabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- HIDDEN + SUBMIT -->
        <div style="display:flex;gap:.7rem;justify-content:flex-end;flex-wrap:wrap;padding:.25rem 0 .5rem;">
            <input type="hidden" name="lab_action" value="blood_save_req_form">
            <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce) ?>">
            <input type="hidden" name="btype" value="blood">
            <input type="hidden" name="bat[btype]" value="blood">
            <input type="hidden" name="bat[is_dna]" value="2">
            <input type="hidden" name="bat[lab_id]" value="1" id="hidden_lab_id">
            <input type="hidden" name="drchrono_patient_id" value="<?php echo esc_attr($patient_id  ?? '') ?>">
            <input type="hidden" name="drchrono_doctor_id" value="<?php echo esc_attr($doctor_id   ?? '') ?>">
            <input type="hidden" name="drchrono_practice_id" value="<?php echo esc_attr($practice_id ?? '') ?>">

            <button type="submit" class="req-btn req-btn--primary" id="req-submit-btn">
                <svg style="width:13px;height:13px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12" />
                </svg>
                Submit Blood Requisition
            </button>
        </div>

    </form>
</div>

<div id="req-toast"></div>

<script>
    (function() {
        'use strict';

        var AJAX_URL = <?php echo json_encode(admin_url('admin-ajax.php')) ?>;
        var DC_REQUIRED = <?php echo $blood_dc_required ? 'true' : 'false' ?>;
        var ONLYONEPANEL = <?php echo $onlyonepanel ?>;
        var LAB_URL = '<?php echo home_url("/lab-requisition/") ?>';
        var changebychild = false;

        /* ── Utility ────────────────────────────────────────── */
        function toast(msg, type) {
            var wrap = document.getElementById('req-toast');
            var el = document.createElement('div');
            el.className = 'req-toast-item ' + (type || 'ok');
            el.textContent = msg;
            wrap.appendChild(el);
            setTimeout(function() {
                el.remove();
            }, 4500);
        }

        function setLoading(btn, on) {
            if (on) {
                btn._orig = btn.innerHTML;
                btn.innerHTML = '<span class="req-spinner"></span> Saving…';
                btn.disabled = true;
            } else {
                btn.innerHTML = btn._orig || btn.innerHTML;
                btn.disabled = false;
            }
        }

        function parseIds(raw) {
            if (!raw) return [];
            return String(raw).split(',').map(function(s) {
                return s.trim();
            }).filter(Boolean);
        }

        function updateSelected(box) {
            box.classList.toggle('selected', !!box.querySelector('.batcustom_profile:checked'));
        }

        /* ── Group master checkbox ───────────────────────────── */
        document.addEventListener('change', function(e) {
            var cb = e.target;
            if (!cb.classList.contains('batcustom_profile')) return;
            var box = cb.closest('.req-groupparent');
            if (!box) return;
            var checked = cb.checked;

            if (ONLYONEPANEL && checked && !cb.dataset.byrelated) {
                var others = document.querySelectorAll('.batcustom_profile:checked');
                var conflict = false;
                others.forEach(function(o) {
                    if (o !== cb) conflict = true;
                });
                if (conflict) {
                    alert('Only one panel can be selected');
                    cb.checked = false;
                    return;
                }
            }
            delete cb.dataset.byrelated;

            if (!changebychild) {
                box.querySelectorAll('.cb_test').forEach(function(t) {
                    t.checked = checked;
                });
            }
            var specDiv = box.querySelector('.req-specimens');
            if (specDiv) {
                if (checked) {
                    specDiv.classList.add('grequired');
                    var spCbs = specDiv.querySelectorAll('input[type="checkbox"]');
                    if (spCbs.length === 1) spCbs[0].checked = true;
                } else {
                    specDiv.classList.remove('grequired', 'gerror');
                    specDiv.querySelectorAll('input[type="checkbox"]').forEach(function(s) {
                        s.checked = false;
                    });
                }
            }
            parseIds(cb.dataset.relate_to).forEach(function(rid) {
                var rel = document.querySelector('.batcustom_profile[value="GROUP_' + rid + '"]');
                if (!rel) return;
                if (checked && !rel.checked) {
                    rel.dataset.byrelated = '1';
                    rel.checked = true;
                    rel.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                } else if (!checked && rel.checked) {
                    rel.checked = false;
                    rel.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                }
            });
            changebychild = false;
            updateSelected(box);
        });

        /* ── Individual test checkbox ────────────────────────── */
        document.addEventListener('change', function(e) {
            var cb = e.target;
            if (!cb.classList.contains('cb_test') || cb.classList.contains('batcustom_profile')) return;
            var gid = cb.dataset.group;
            if (!gid) return;
            var parentCb = document.querySelector('.batcustom_profile[value="GROUP_' + gid + '"]');
            if (!parentCb) return;
            var any = !!document.querySelector('.cb_test[data-group="' + gid + '"]:checked');
            changebychild = true;
            parentCb.checked = any;
            parentCb.dispatchEvent(new Event('change', {
                bubbles: true
            }));
            var box = parentCb.closest('.req-groupparent');
            if (box) updateSelected(box);
        });

        /* ── Clinic change → visibility ─────────────────────── */
        document.getElementById('cli_ID').addEventListener('change', function() {
            var cid = this.value;
            document.querySelectorAll('.by_clinic').forEach(function(g) {
                g.classList.remove('cactive');
            });
            if (cid) document.querySelectorAll('.by_clinic.cli_id_' + cid).forEach(function(g) {
                g.classList.add('cactive');
            });
            document.querySelectorAll('.not_by_clinic').forEach(function(g) {
                g.classList.remove('notcactive');
            });
            if (cid) document.querySelectorAll('.not_by_clinic.not_cli_id_' + cid).forEach(function(g) {
                g.classList.add('notcactive');
            });
        });

        /* ── Insurance type toggle ───────────────────────────── */
        var insFields = document.getElementById('ins-primary-fields');
        var workerFlds = document.getElementById('ins-worker-fields');
        var insReqs = insFields ? insFields.querySelectorAll('.ins-required') : [];
        var insStars = insFields ? insFields.querySelectorAll('.ins-req') : [];

        document.querySelectorAll('[name="bat[ins_comp]"]').forEach(function(r) {
            r.addEventListener('change', function() {
                var val = this.value;
                if (insFields) insFields.style.display = val === 'ins' ? '' : 'none';
                if (workerFlds) workerFlds.style.display = val === 'comp' ? '' : 'none';
                insReqs.forEach(function(el) {
                    val === 'ins' ? el.setAttribute('required', '') : el.removeAttribute('required');
                });
                insStars.forEach(function(el) {
                    el.style.display = val === 'ins' ? '' : 'none';
                });
            });
        });

        /* ── Ins1 parent DOB visibility ─────────────────────── */
        document.querySelectorAll('[name="ins1[ins_relation]"]').forEach(function(r) {
            r.addEventListener('change', function() {
                var dob = document.getElementById('ins1_dob_field');
                if (dob) dob.style.display = this.value === 'Self' ? 'none' : '';
            });
        });

        /* ── Auto-fill Self insurance ────────────────────────── */
        function syncSelf() {
            var rel = document.querySelector('[name="ins1[ins_relation]"]:checked');
            if (!rel || rel.value !== 'Self') return;
            var iL = document.getElementById('ins_last'),
                pL = document.getElementById('pa_last');
            var iF = document.getElementById('ins_first'),
                pF = document.getElementById('pa_first');
            if (pL && iL && !iL.value) iL.value = pL.value;
            if (pF && iF && !iF.value) iF.value = pF.value;
        }
        ['pa_last', 'pa_first'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('change', syncSelf);
        });
        var relSelf = document.getElementById('ins_rel_self');
        if (relSelf) relSelf.addEventListener('change', syncSelf);

        /* ── Medication date range ───────────────────────────── */
        document.addEventListener('change', function(e) {
            if (!e.target.classList.contains('mstart_date') && !e.target.classList.contains('mend_date')) return;
            var pr = e.target.closest('.req-med-item');
            if (!pr) return;
            var cb = pr.querySelector('input[type="checkbox"]');
            if (!cb) return;
            var sd = (pr.querySelector('.mstart_date') || {}).value || '';
            var ed = (pr.querySelector('.mend_date') || {}).value || '';
            cb.value = cb.value.split(';')[0] + ';' + sd + ';' + ed;
        });

        /* ── Diag chips ──────────────────────────────────────── */
        var diagChips = document.getElementById('diag-chips');
        var diagHidden = document.getElementById('diag-hidden');
        var diagSelect = document.getElementById('diag-select');
        var diagCustom = document.getElementById('diag-custom');
        var diagAddBtn = document.getElementById('diag-add-btn');
        var addedCodes = new Set();

        function addCode(code) {
            code = code.trim().toUpperCase();
            if (!code || addedCodes.has(code)) return;
            addedCodes.add(code);
            if (diagChips) {
                var chip = document.createElement('span');
                chip.className = 'req-chip';
                chip.innerHTML = code + ' <button type="button">&times;</button>';
                chip.querySelector('button').addEventListener('click', function() {
                    chip.remove();
                    addedCodes.delete(code);
                    var inp = diagHidden ? diagHidden.querySelector('input[value="' + code + '"]') : null;
                    if (inp) inp.remove();
                });
                diagChips.appendChild(chip);
            }
            if (diagHidden) {
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'bat[dagnostic_codes][]';
                inp.value = code;
                diagHidden.appendChild(inp);
            }
        }
        if (diagAddBtn) diagAddBtn.addEventListener('click', function() {
            if (diagSelect && diagSelect.value) {
                addCode(diagSelect.value);
                diagSelect.value = '';
            }
            if (diagCustom && diagCustom.value) {
                addCode(diagCustom.value);
                diagCustom.value = '';
            }
        });
        if (diagSelect) diagSelect.addEventListener('change', function() {
            if (this.value) {
                addCode(this.value);
                this.value = '';
            }
        });

        /* ── File upload UI ──────────────────────────────────── */
        ['ins_card_front', 'ins_card_back', 'patient_id_front', 'patient_id_back'].forEach(function(fname) {
            var inp = document.getElementById('file_' + fname);
            var lbl = document.getElementById('lbl_' + fname);
            var txt = document.getElementById('lbl_txt_' + fname);
            if (!inp) return;
            inp.addEventListener('change', function() {
                if (inp.files && inp.files[0]) {
                    if (txt) txt.textContent = inp.files[0].name;
                    if (lbl) lbl.classList.add('has-file');
                }
            });
        });

        /* ── Validation ──────────────────────────────────────── */
        function validate() {
            var ok = true;
            var form = document.getElementById('req-requisition-form');
            form.querySelectorAll('[required]').forEach(function(el) {
                if (!el.value.trim()) {
                    el.classList.add('error');
                    ok = false;
                } else el.classList.remove('error');
            });
            var anyTest = form.querySelectorAll('.batcustom_profile:checked,.cb_test:not(.batcustom_profile):checked').length > 0;
            var panelErr = document.getElementById('req-panel-error');
            if (!anyTest) {
                if (panelErr) panelErr.style.display = 'block';
                ok = false;
            } else {
                if (panelErr) panelErr.style.display = 'none';
            }
            if (DC_REQUIRED && addedCodes.size === 0) {
                var dErr = document.getElementById('diag-error');
                if (dErr) dErr.style.display = 'block';
                ok = false;
            } else {
                var dErr2 = document.getElementById('diag-error');
                if (dErr2) dErr2.style.display = 'none';
            }
            form.querySelectorAll('.req-specimens.grequired').forEach(function(sd) {
                if (sd.querySelectorAll('input:checked').length === 0) {
                    sd.classList.add('gerror');
                    ok = false;
                }
            });
            if (form.querySelectorAll('.req-specimens.gerror').length > 0) toast('Please select a Specimen Source.', 'err');
            return ok;
        }
        /* ── Issue photo UI ──────────────────────────────────────── */
        var issueInp = document.getElementById('file_issue_photo');
        var issueLbl = document.getElementById('lbl_issue_photo');
        var issueTxt = document.getElementById('lbl_txt_issue_photo');
        var issueVal = document.getElementById('val_issue_photo');
        var collectedFiles = {
            result_pdfs: [],
            original_reqs: []
        };

        if (issueInp) {
            issueInp.addEventListener('change', function() {
                if (issueInp.files && issueInp.files[0]) {
                    if (issueTxt) issueTxt.textContent = issueInp.files[0].name;
                    if (issueLbl) issueLbl.classList.add('has-file');
                }
            });
        }

        /* ── Submit ──────────────────────────────────────────── */
        function submitForm() {
            if (!validate()) {
                toast('Please fill in all required fields.', 'err');
                var firstErr = document.querySelector('.error,.gerror');
                if (firstErr) firstErr.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                return;
            }
            var btn = document.getElementById('req-submit-btn');
            setLoading(btn, true);
            var form = document.getElementById('req-requisition-form');
            form.querySelectorAll('.req-groupparent.notcactive input[type="checkbox"]').forEach(function(cb) {
                cb.checked = false;
            });
            var fd = new FormData(form);
            ['ins_card_front', 'ins_card_back', 'patient_id_front', 'patient_id_back'].forEach(function(fname) {
                var inp = document.getElementById('file_' + fname);
                if (inp && inp.files[0]) fd.set(fname, inp.files[0]);
            });
            collectedFiles.result_pdfs.forEach(function(f) {
                fd.append('result_pdfs[]', f);
            });
            collectedFiles.original_reqs.forEach(function(f) {
                fd.append('original_reqs[]', f);
            });
            if (issueInp && issueInp.files && issueInp.files[0]) {
                fd.set('issue_photo', issueInp.files[0]);
            }
            fetch(LAB_URL, {
                    method: 'POST',
                    body: fd
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    setLoading(btn, false);
                    if (data.success) {
                        toast(data.data.message || 'Saved!', 'ok');
                        if (btnBack) {
                            setTimeout(function() {
                                btnBack.click();
                            }, 1300);
                        }
                    } else {
                        toast((data.data && data.data.message) || 'An error occurred.', 'err');
                    }
                })
                .catch(function() {
                    setLoading(btn, false);
                    toast('Network error. Please try again.', 'err');
                });
        }

        document.getElementById('req-submit-btn').addEventListener('click', function(e) {
            e.preventDefault();
            submitForm();
        });
        document.getElementById('req-requisition-form').addEventListener('input', function(e) {
            e.target.classList.remove('error');
            var sp = e.target.closest('.req-specimens');
            if (sp) sp.classList.remove('gerror');
        });

        function initMultiUpload(inputId, listId, lblId, lblTxtId) {
            var inp = document.getElementById(inputId);
            var list = document.getElementById(listId);
            var lbl = document.getElementById(lblId);
            var txt = document.getElementById(lblTxtId);
            if (!inp || !list) return;

            inp.addEventListener('change', function() {
                if (!inp.files || !inp.files.length) return;
                Array.from(inp.files).forEach(function(file) {
                    if (inp.id === 'file_result_pdf' && !/\.pdf$/i.test(file.name)) {
                        alert('Please choose PDF files only.');
                        return;
                    }
                    collectedFiles[inp.name].push(file);
                    var li = document.createElement('li');
                    var a = document.createElement('a');
                    a.textContent = file.name;
                    a.href = '#';
                    a.addEventListener('click', function(e) {
                        e.preventDefault();
                    });
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'bl-remove-file';
                    btn.textContent = '×';
                    btn.addEventListener('click', function() {
                        collectedFiles[inp.name] = collectedFiles[inp.name]
                            .filter(f => f.name !== file.name);
                        li.remove();
                        updateHasFile();
                    });
                    li.appendChild(a);
                    li.appendChild(btn);
                    list.appendChild(li);
                });
                if (txt) txt.textContent = collectedFiles[inp.name].length + ' file(s) selected';
                if (lbl) lbl.classList.add('has-file');
                inp.value = '';
            });

            function updateHasFile() {
                if (lbl) lbl.classList.toggle('has-file', list.children.length > 0);
                if (txt && list.children.length === 0) txt.textContent = lblId === 'lbl_result_pdf' ? 'Choose Result PDF' : 'Choose Paper Req';
            }
        }
        initMultiUpload('file_result_pdf', 'list_result_pdf', 'lbl_result_pdf', 'lbl_txt_result_pdf');
        initMultiUpload('file_original_req', 'list_original_req', 'lbl_original_req', 'lbl_txt_original_req');
    })();
</script>
