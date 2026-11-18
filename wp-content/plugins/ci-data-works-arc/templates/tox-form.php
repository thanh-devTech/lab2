<?php

/**
 * Toxicology Requisition Form – Frontend / iFrame Template
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

// ── Pre-fill from DrChrono ───────────────────────────────────────────────────
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
$settings          = get_option('ci_req_settings_tox', []);
$panel_column      = isset($settings['panel_column']) && $settings['panel_column'] >= 2
    ? (int)$settings['panel_column'] : 2;
$show_npi          = tr_get_option('ci_pdf_data', 'req_show_npi');
$hide_poc          = !empty($settings['hide_poc']);
$dagnostic_required = !empty($settings['dagnostic_code_required']);
$enable_phase      = !empty($settings['e_phase']);
$required_insurance         = tr_get_option('ci_pdf_data', 'unrequired_insu') ? '' : 'required';
$required_insurance_address = tr_get_option('ci_pdf_data', 'unrequired_ainsu') ? '' : $required_insurance;
$dropdown_insurance = tr_get_option('ci_pdf_data', 'dropdown_insurance');
$enable_client_bill = tr_get_option('ci_req_settings_tox', 'enable_client_bill');

// ── Clinics ───────────────────────────────────────────────────────────────────
$clinics = $wpdb->get_results(
    "SELECT cli.* FROM ci_clinic cli WHERE cli.cli_status = 1 ORDER BY cli.cli_clinic",
    ARRAY_A
);

// ── Tests / panels (LCMS confirmation) ───────────────────────────────────────
$lcms_tests = tr_pdd_get_profiles_tests_requesteds('tox', true, false, date('Y-m-d'));

$all_groups_meta = [];
$groups_raw = $wpdb->get_results(
    "SELECT tg_ID, tg_name, tg_label_name, tg_bgcolor, tg_show_req,
            tg_parent, tg_relate_to, tg_only_group, tg_notestcheckbox,
            tg_show_cli_IDs, tg_notshow_cli_IDs,
            tg_show_for_ref_lab_ID, tg_notshow_for_ref_lab_ID,
            tg_other_options, tg_position
       FROM ci_test_groups
      WHERE tg_type = 'tox'
      ORDER BY tg_position ASC, tg_ID ASC",
    ARRAY_A
);
foreach ($groups_raw as $g) {
    $all_groups_meta[(int)$g['tg_ID']] = $g;
}

// ── Custom profiles ────────────────────────────────────────────────────────────
$custom_profiles = function_exists('tr_pdd_get_custom_profiles')
    ? tr_pdd_get_custom_profiles() : [];

// ── Screening tests (urine validity) ─────────────────────────────────────────
$screenings = function_exists('tr_pdd_get_result_validity')
    ? tr_pdd_get_result_validity(1, '', 0, false) : [];

$screening_groups = $wpdb->get_results(
    "SELECT * FROM ci_test_groups WHERE tg_type='scr'",
    ARRAY_A
);

// ── Current medications ────────────────────────────────────────────────────────
$current_medications = function_exists('tr_pdd_get_current_medications')
    ? tr_pdd_get_current_medications() : [];
$medications = function_exists('tr_pdd_get_medications')
    ? tr_pdd_get_medications() : [];

// ── Payer list ────────────────────────────────────────────────────────────────
$payer_list = [];
if ($dropdown_insurance) {
    $payer_list = $wpdb->get_results(
        "SELECT payer_name FROM ci_payer_list ORDER BY payer_name ASC"
    );
}

// ── Diagnostic codes ─────────────────────────────────────────────────────────
$dagnostic_codes = function_exists('tr_pdd_get_dagnostic_codes')
    ? tr_pdd_get_dagnostic_codes() : [];

// ── Batch statuses ────────────────────────────────────────────────────────────
$status_options = tr_pdd_get_batch_statuses_on_hold(
    tr_pdd_get_batch_statuses(false, false, false, 'tox'),
    '',
    '',
    'ci_batches',
    'tox'
);

$us_states = tr_get_states();

// ── Ref labs ──────────────────────────────────────────────────────────────────
$ref_labs = $wpdb->get_results(
    "SELECT lab_ID, lab_name FROM ci_labs WHERE lab_is_ref IN (1,2) ORDER BY lab_dba"
);

// ── Helper: contrast colour ────────────────────────────────────────────────────
if (!function_exists('tox_front_contrast')) {
    function tox_front_contrast(string $hex): string
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
<style>
    .req-scr-grid {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
    }

    .req-scr-item {
        min-width: 160px;
    }

    .req-scr-item label {
        display: flex;
        align-items: center;
        gap: .3rem;
        font-size: 12px;
    }

    /* ── Medications POC table ───────────────────────────────── */
    .req-poc-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }

    .req-poc-table th {
        background: #f1f5f9;
        padding: .3rem .5rem;
        text-align: center;
        font-weight: 700;
        font-size: 11px;
        text-transform: uppercase;
        border: 1px solid var(--tf-border);
    }

    .req-poc-table td {
        padding: .25rem .5rem;
        border: 1px solid var(--tf-border);
        vertical-align: middle;
    }

    .req-poc-table td:first-child {
        font-size: 12px;
    }

    .req-poc-table input[type="radio"] {
        margin: 0 auto;
        display: block;
    }
</style>

<div class="req-form-wrap" id="req-form-wrap">
    <form id="req-requisition-form" novalidate autocomplete="off">

        <!-- ══ PRACTICE & PHYSICIAN ══════════════════════════════ -->
        <div class="req-section">
            <div class="req-section__head">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                    <polyline points="9 22 9 12 15 12 15 22" />
                </svg>
                Practice &amp; Physician
            </div>
            <div class="req-section__body">
                <div class="req-g3">

                    <div class="req-field">
                        <label class="req-lbl" for="cli_ID">Practice / Clinic<span class="req">*</span></label>
                        <select name="bat[cli_ID]" id="cli_ID" class="req-select" required>
                            <option value="">— Select Clinic —</option>
                            <?php foreach ($clinics as $c): ?>
                                <option value="<?php echo (int)$c['cID'] ?>"
                                    data-address="<?php echo esc_attr($c['cli_street'] ?? '') ?>"
                                    data-city="<?php echo esc_attr($c['cli_city'] ?? '') ?>"
                                    data-state="<?php echo esc_attr($c['cli_state'] ?? '') ?>"
                                    data-zip="<?php echo esc_attr($c['cli_zip'] ?? '') ?>"
                                    data-phone="<?php echo esc_attr($c['cli_om-phone'] ?? '') ?>"
                                    data-reflab="<?php echo (int)($c['cli_ref_lab_ID'] ?? 0) ?>"
                                    data-autoa="<?php echo (int)($c['cli_auto_assen'] ?? 0) ?>"
                                    data-screening="<?php echo (int)($c['cli_screening'] ?? 0) ?>"
                                    data-only_screening="<?php echo (int)($c['cli_only_screening'] ?? 0) ?>"
                                    data-tox_df_profile="<?php echo esc_attr($c['cli_tox_df_profile'] ?? '') ?>"
                                    data-phase="<?php echo (int)($c['phase'] ?? 0) ?>">
                                    <?php echo esc_html($c['cli_clinic']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="req-field req-span2" id="doc_select_wrap" style="display:none;">
                        <label class="req-lbl" for="doc_ID_sel">Ordering Physician<span class="req">*</span></label>
                        <select name="bat[doc_ID]" id="doc_ID_sel" class="req-select">
                            <option value="">— Select Doctor —</option>
                        </select>
                    </div>
                    <div class="req-field" id="doc_first_wrap">
                        <label class="req-lbl" for="doc_first">Physician First Name<span class="req">*</span></label>
                        <input type="text" id="doc_first" name="doc[doc_first]" class="req-input"
                            value="<?php echo $pre_doc_first ?>" required placeholder="First">
                    </div>
                    <div class="req-field" id="doc_last_wrap">
                        <label class="req-lbl" for="doc_last">Physician Last Name<span class="req">*</span></label>
                        <input type="text" id="doc_last" name="doc[doc_last]" class="req-input"
                            value="<?php echo $pre_doc_last ?>" required placeholder="Last">
                    </div>

                    <?php if ($show_npi): ?>
                        <div class="req-field">
                            <label class="req-lbl" for="doc_npi">NPI Number</label>
                            <input type="text" id="doc_npi" name="doc[doc_npi]" class="req-input"
                                value="<?php echo $pre_doc_npi ?>" maxlength="10" placeholder="10 digits">
                        </div>
                    <?php endif; ?>

                    <div class="req-field">
                        <label class="req-lbl" for="bat_doc">Date of Collection<span class="req">*</span></label>
                        <input type="datetime-local" id="bat_doc" name="bat[doc]" class="req-input"
                            value="<?php echo date('Y-m-d\TH:i') ?>" required>
                    </div>

                    <?php if (count($ref_labs) > 0): ?>
                        <div class="req-field">
                            <label class="req-lbl" for="ref_lab_ID">Reference Lab</label>
                            <select id="ref_lab_ID" name="bat[ref_lab_ID]" class="req-select">
                                <option value="">— None —</option>
                                <?php foreach ($ref_labs as $rl): ?>
                                    <option value="<?php echo (int)$rl->lab_ID ?>"><?php echo esc_html($rl->lab_name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                </div>

                <!-- Temperature -->
                <div style="margin-top:.75rem;">
                    <div class="req-lbl" style="margin-bottom:.4rem;">Temperature (read within 4 minutes)</div>
                    <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
                        <label class="req-chklbl"><input type="radio" name="bat[temperature]" value="&lt;91"> &lt;91</label>
                        <label class="req-chklbl"><input type="radio" name="bat[temperature]" value="91-99.6"> 91–99.6</label>
                        <label class="req-chklbl"><input type="radio" name="bat[temperature]" value="&gt;99.6"> &gt;99.6</label>
                        <label class="req-chklbl"><input type="radio" name="bat[temperature]" value="no"> No</label>
                    </div>
                </div>

                <!-- Type of specimen -->
                <div style="margin-top:.75rem;">
                    <div class="req-lbl" style="margin-bottom:.4rem;">Type of Specimen<span class="req">*</span></div>
                    <div style="display:flex;gap:.65rem;flex-wrap:wrap;">
                        <?php foreach (tr_pdd_get_type_of_specimens() as $k => $vl): ?>
                            <label class="req-chklbl">
                                <input type="radio" name="bat[type_of_specimen]" value="<?php echo esc_attr($k) ?>"
                                    <?php if ($k === 'Urine') echo 'checked'; ?>>
                                <?php echo esc_html($vl) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($enable_phase): ?>
                    <!-- Treatment Phase -->
                    <div id="phase_div" style="margin-top:.75rem;display:none;">
                        <div class="req-field" style="max-width:220px;">
                            <label class="req-lbl" for="batmeta_treatment">Treatment Phase</label>
                            <select id="batmeta_treatment" name="batmeta[treatment]" class="req-select">
                                <option value="">Select</option>
                                <?php foreach ([1, 2, 3, 4, 5, 6, 13, 28] as $phase): ?>
                                    <option value="<?php echo $phase ?>">Phase <?php echo $phase ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- ══ PATIENT INFORMATION ════════════════════════════════ -->
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
                        <label class="req-lbl" for="pa_ssn">Social Security Number</label>
                        <input type="text" id="pa_ssn" name="pa[pa_ssn]" class="req-input" maxlength="12" placeholder="XXX-XX-XXXX">
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="pa_mrn">Medical Record #</label>
                        <input type="text" id="pa_mrn" name="pa[pa_mrn]" class="req-input" value="<?php echo $pre_mrn ?>" maxlength="15">
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="pa_phone">Phone<span class="req">*</span></label>
                        <input type="tel" id="pa_phone" name="pa[pa_phone]" class="req-input" value="<?php echo $pre_phone ?>" required maxlength="12">
                    </div>

                    <div class="req-field req-span2">
                        <label class="req-lbl" for="pa_address">Street Address<span class="req">*</span></label>
                        <input type="text" id="pa_address" name="pa[pa_address]" class="req-input" value="<?php echo $pre_address ?>" required>
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
                        <label class="req-lbl" for="pa_email">Email</label>
                        <input type="email" id="pa_email" name="pa[pa_email]" class="req-input" value="<?php echo $pre_email ?>">
                    </div>

                </div>
            </div>
        </div>

        <!-- ══ INSURANCE / BILLING ════════════════════════════════ -->
        <div class="req-section">
            <div class="req-section__head">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                </svg>
                Insurance / Billing
            </div>
            <div class="req-section__body">

                <div style="display:flex;gap:1.2rem;flex-wrap:wrap;margin-bottom:.85rem;">
                    <label class="req-chklbl">
                        <input type="radio" name="bat[ins_comp]" value="ins" id="ins_type_ins" checked>
                        Primary Insurance
                    </label>
                    <label class="req-chklbl">
                        <input type="radio" name="bat[ins_comp]" value="cash">
                        No Insurance – Cash
                    </label>
                    <label class="req-chklbl">
                        <input type="radio" name="bat[ins_comp]" value="comp">
                        Worker's Comp / Auto
                    </label>
                    <?php if ($enable_client_bill): ?>
                        <label class="req-chklbl">
                            <input type="radio" name="bat[ins_comp]" value="client">
                            Client Bill
                        </label>
                    <?php endif; ?>
                </div>

                <!-- Primary insurance -->
                <div id="ins-fields">
                    <div class="req-g3">
                        <div class="req-field">
                            <label class="req-lbl" for="ins1_last">Insured Last Name<span class="req ins-req">*</span></label>
                            <input type="text" id="ins1_last" name="ins1[ins_last]" class="req-input ins-required" required>
                        </div>
                        <div class="req-field">
                            <label class="req-lbl" for="ins1_first">Insured First Name<span class="req ins-req">*</span></label>
                            <input type="text" id="ins1_first" name="ins1[ins_first]" class="req-input ins-required" required>
                        </div>
                        <div class="req-field">
                            <label class="req-lbl">Relation to Patient<span class="req ins-req">*</span></label>
                            <div class="req-radio-group">
                                <label class="req-chklbl"><input type="radio" name="ins1[ins_relation]" value="Self" id="ins_rel_self" checked> Self</label>
                                <label class="req-chklbl"><input type="radio" name="ins1[ins_relation]" value="Parent"> Parent</label>
                                <label class="req-chklbl"><input type="radio" name="ins1[ins_relation]" value="Spouse"> Spouse</label>
                            </div>
                        </div>
                        <div class="req-field">
                            <label class="req-lbl" for="ins1_company">Insurance Company<span class="req ins-req">*</span></label>
                            <?php if ($dropdown_insurance): ?>
                                <select id="ins1_company" name="ins1[ins_company]" class="req-select ins-required" required>
                                    <option value="">— Select —</option>
                                    <?php foreach ($payer_list as $p): ?>
                                        <option value="<?php echo esc_attr($p->payer_name) ?>"><?php echo esc_html($p->payer_name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" id="ins1_company" name="ins1[ins_company]" class="req-input ins-required" required>
                            <?php endif; ?>
                        </div>
                        <div class="req-field">
                            <label class="req-lbl" for="ins1_member">Member ID<span class="req ins-req">*</span></label>
                            <input type="text" id="ins1_member" name="ins1[ins_member_id]" class="req-input ins-required" required maxlength="24">
                        </div>
                        <div class="req-field">
                            <label class="req-lbl" for="ins1_group">Group #</label>
                            <input type="text" id="ins1_group" name="ins1[ins_group_id]" class="req-input" maxlength="20">
                        </div>
                        <div class="req-field">
                            <label class="req-lbl" for="ins1_addr">Street Address</label>
                            <input type="text" id="ins1_addr" name="ins1[ins_address]" class="req-input" <?php echo $required_insurance_address ?>>
                        </div>
                        <div class="req-field">
                            <label class="req-lbl" for="ins1_city">City</label>
                            <input type="text" id="ins1_city" name="ins1[ins_city]" class="req-input" <?php echo $required_insurance_address ?>>
                        </div>
                        <div class="req-field">
                            <label class="req-lbl" for="ins1_phone">Insurance Phone</label>
                            <input type="tel" id="ins1_phone" name="ins1[ins_phone]" class="req-input">
                        </div>
                        <div class="req-field">
                            <label class="req-lbl" for="ins1_auth">Authorization #</label>
                            <input type="text" id="ins1_auth" name="ins1[ins_authorization_id]" class="req-input">
                        </div>
                        <div class="req-field">
                            <label class="req-lbl" for="ins1_auth_date">Authorization Date</label>
                            <input type="date" id="ins1_auth_date" name="ins1[ins_authorization_date]" class="req-input">
                        </div>
                        <div class="req-field">
                            <label class="req-lbl">Pre-authorization</label>
                            <div class="req-radio-group">
                                <label class="req-chklbl"><input type="radio" name="ins1[ins_authorization]" value="1"> Yes</label>
                                <label class="req-chklbl"><input type="radio" name="ins1[ins_authorization]" value="0"> No</label>
                            </div>
                        </div>
                    </div>

                    <!-- Secondary insurance -->
                    <details style="margin-top:.85rem;">
                        <summary style="font-size:.74rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#374151;cursor:pointer;padding:.35rem 0;">
                            + Secondary Insurance
                        </summary>
                        <div class="req-g3" style="margin-top:.6rem;">
                            <div class="req-field">
                                <label class="req-lbl">Last Name</label>
                                <input type="text" name="ins2[ins_last]" class="req-input">
                            </div>
                            <div class="req-field">
                                <label class="req-lbl">First Name</label>
                                <input type="text" name="ins2[ins_first]" class="req-input">
                            </div>
                            <div class="req-field">
                                <label class="req-lbl">Relation</label>
                                <div class="req-radio-group">
                                    <label class="req-chklbl"><input type="radio" name="ins2[ins_relation]" value="Self"> Self</label>
                                    <label class="req-chklbl"><input type="radio" name="ins2[ins_relation]" value="Parent"> Parent</label>
                                    <label class="req-chklbl"><input type="radio" name="ins2[ins_relation]" value="Spouse"> Spouse</label>
                                </div>
                            </div>
                            <div class="req-field">
                                <label class="req-lbl">Insurance Company</label>
                                <input type="text" name="ins2[ins_company]" class="req-input">
                            </div>
                            <div class="req-field">
                                <label class="req-lbl">Member ID</label>
                                <input type="text" name="ins2[ins_member_id]" class="req-input" maxlength="24">
                            </div>
                            <div class="req-field">
                                <label class="req-lbl">Group #</label>
                                <input type="text" name="ins2[ins_group_id]" class="req-input">
                            </div>
                        </div>
                    </details>
                </div>

                <!-- Worker's Comp -->
                <div id="wc-fields" style="display:none;margin-top:.75rem;">
                    <div class="req-g3">
                        <div class="req-field">
                            <label class="req-lbl">Subscriber Last Name</label>
                            <input type="text" name="bat[subscriber_last]" class="req-input">
                        </div>
                        <div class="req-field">
                            <label class="req-lbl">Subscriber First Name</label>
                            <input type="text" name="bat[subscriber_first]" class="req-input">
                        </div>
                        <div class="req-field">
                            <label class="req-lbl">Date of Injury</label>
                            <input type="date" name="bat[date_of_injury]" class="req-input">
                        </div>
                        <div class="req-field req-span2">
                            <label class="req-lbl">Address of Claim</label>
                            <input type="text" name="bat[address_of_claim]" class="req-input">
                        </div>
                        <div class="req-field">
                            <label class="req-lbl">Zip Code</label>
                            <input type="text" name="bat[subscriber_zip]" class="req-input">
                        </div>
                        <div class="req-field req-span2">
                            <label class="req-lbl">Insurance</label>
                            <input type="text" name="bat[subscriber_insurance]" class="req-input">
                        </div>
                        <div class="req-field">
                            <label class="req-lbl">WC#</label>
                            <input type="text" name="bat[subscriber_wc]" class="req-input">
                        </div>
                        <div class="req-field">
                            <label class="req-lbl">Claim Number</label>
                            <input type="text" name="bat[claim_number]" class="req-input">
                        </div>
                        <div class="req-field">
                            <label class="req-lbl">Attorney</label>
                            <input type="text" name="bat[attorney_name]" class="req-input">
                        </div>
                        <div class="req-field">
                            <label class="req-lbl">Attorney Phone</label>
                            <input type="tel" name="bat[attorney_phone]" class="req-input">
                        </div>
                        <div class="req-field">
                            <label class="req-lbl">Adjuster Name</label>
                            <input type="text" name="bat[adjuster_name]" class="req-input">
                        </div>
                        <div class="req-field">
                            <label class="req-lbl">Adjuster Phone</label>
                            <input type="tel" name="bat[adjuster_phone]" class="req-input">
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ══ DIAGNOSTIC CODES ═══════════════════════════════════ -->
        <?php if (!empty($dagnostic_codes)): ?>
            <div class="req-section">
                <div class="req-section__head">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 11l3 3L22 4" />
                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
                    </svg>
                    Diagnostic Codes (ICD-10) <?php if ($dagnostic_required): ?><span style="color:var(--req-danger);margin-left:4px;font-size:12px;">*</span><?php endif; ?>
                </div>
                <div class="req-section__body">
                    <div id="diag-chips" style="display:flex;flex-wrap:wrap;gap:.35rem;min-height:22px;margin-bottom:.6rem;"></div>
                    <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
                        <select id="diag-select" class="req-select" style="max-width:310px;">
                            <option value="">— Select code —</option>
                            <?php foreach ($dagnostic_codes as $code => $label): ?>
                                <?php if (empty($code)) continue; ?>
                                <option value="<?php echo esc_attr($code) ?>"><?php echo esc_html($code . ' – ' . $label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" id="diag-custom" class="req-input" style="max-width:150px;" placeholder="Enter additional code">
                        <button type="button" class="req-btn req-btn--secondary" id="diag-add-btn" style="padding:.4rem .75rem;">+ Add</button>
                    </div>
                    <div id="diag-hidden"></div>
                    <p id="diag-error" style="color:var(--req-danger);font-size:12px;margin-top:.4rem;display:none;">
                        Please select at least one diagnostic code.
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- ══ PATIENT CURRENT MEDICATIONS ════════════════════════ -->
        <?php if (!empty($current_medications)): ?>
            <div class="req-section">
                <div class="req-section__head">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.5 20H4a2 2 0 0 1-2-2V5c0-1.1.9-2 2-2h3.93a2 2 0 0 1 1.66.9l.82 1.2a2 2 0 0 0 1.66.9H20a2 2 0 0 1 2 2v3" />
                        <circle cx="17" cy="17" r="3" />
                        <path d="m21 21-1.5-1.5" />
                    </svg>
                    Patient Current Medications
                </div>
                <div class="req-section__body">
                    <p style="font-size:12px;color:var(--req-muted);margin-bottom:.65rem;">
                        I order the lab to test for/confirm the prescribed medications listed below.
                    </p>
                    <input type="hidden" name="has_edit_medication" value="1">
                    <div style="display:flex;flex-wrap:wrap;gap:.45rem;margin-bottom:.65rem;">
                        <?php foreach ($current_medications as $m):
                            $k = $m;
                            if ($k === 'Fioricet/Fiorinal') $k = 'Fioricet';
                            elseif ($k === 'Lorcet/Lortab') $k = 'Lortab';
                        ?>
                            <label class="req-chklbl" style="min-width:130px;">
                                <input type="checkbox" name="bat[current_medication][]" value="<?php echo esc_attr($k) ?>">
                                <?php echo esc_html(ucfirst(strtolower($m))) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <label class="req-chklbl" style="margin-bottom:.65rem;">
                        <input type="checkbox" name="current_medication_none" value="1" id="med_none_cb">
                        No Prescribed Medication
                    </label>
                    <div class="req-g3" style="margin-top:.55rem;" id="more_med_div">
                        <div class="req-field">
                            <label class="req-lbl">Other Medication 1</label>
                            <input type="text" name="bat[current_medication_other][]" class="req-input" placeholder="Other">
                        </div>
                        <div class="req-field">
                            <label class="req-lbl">Other Medication 2</label>
                            <input type="text" name="bat[current_medication_other][]" class="req-input" placeholder="Other">
                        </div>
                        <div class="req-field">
                            <label class="req-lbl">Other Medication 3</label>
                            <input type="text" name="bat[current_medication_other][]" class="req-input" placeholder="Other">
                        </div>
                    </div>
                    <p style="font-size:11px;color:var(--req-muted);margin-top:.4rem;">
                        Not all drugs listed under "other" will be available.
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- ══ POC RESULTS ════════════════════════════════════════ -->
        <?php if (!$hide_poc && !empty($medications)): ?>
            <div class="req-section">
                <div class="req-section__head">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                    </svg>
                    POC Results
                </div>
                <div class="req-section__body">
                    <div style="display:flex;gap:1.5rem;margin-bottom:.85rem;">
                        <label class="req-chklbl">
                            <input type="radio" name="bat[poc_perform]" value="1" required> POC Performed
                        </label>
                        <label class="req-chklbl">
                            <input type="radio" name="bat[poc_perform]" value="0" required> POC Not Performed
                        </label>
                    </div>
                    <?php
                    $half = round(count($medications) / 2);
                    $med_chunks = [
                        array_slice($medications, 0, $half),
                        array_slice($medications, $half),
                    ];
                    ?>
                    <div class="req-g2" style="gap:.5rem;">
                        <?php foreach ($med_chunks as $chunk): ?>
                            <div>
                                <table class="req-poc-table">
                                    <tr>
                                        <th style="text-align:left;">Medication or Drug</th>
                                        <th>+</th>
                                        <th>/</th>
                                        <th>−</th>
                                    </tr>
                                    <?php foreach ($chunk as $med): ?>
                                        <tr>
                                            <td><?php echo esc_html($med) ?></td>
                                            <td><input type="radio" name="bat[medication][<?php echo esc_attr($med) ?>]" value="1"></td>
                                            <td></td>
                                            <td><input type="radio" name="bat[medication][<?php echo esc_attr($med) ?>]" value="0"></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ══ TESTING REQUESTED ══════════════════════════════════ -->
        <div class="req-section">
            <div class="req-section__head">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2v-4M9 21H5a2 2 0 0 1-2-2v-4m0 0h18" />
                </svg>
                Tests Ordered / Custom Profile &amp; LCMS Confirmation
            </div>
            <div class="req-section__body">

                <input type="hidden" name="has_panel_requests" value="1">

                <!-- Custom profile -->
                <?php if (!empty($custom_profiles)): ?>
                    <div class="req-field" style="max-width:300px;margin-bottom:.85rem;">
                        <label class="req-lbl" for="batcustom_profile">Custom Profile</label>
                        <select id="batcustom_profile" name="bat[custom_profile]" class="req-select" required>
                            <?php foreach ($custom_profiles as $k => $v): ?>
                                <option value="<?php echo esc_attr($k) ?>"><?php echo esc_html($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- Drug Screening -->
                <?php if (!empty($screenings)): ?>
                    <div style="margin-bottom:.85rem;">
                        <div style="display:flex;gap:1.5rem;flex-wrap:wrap;margin-bottom:.55rem;align-items:center;">
                            <label class="req-chklbl"><input type="radio" name="screening" value="1" id="scr_yes"> Drug Screening</label>
                            <label class="req-chklbl"><input type="radio" name="screening" value="0" id="scr_no"> No Screening</label>
                            <label class="req-chklbl">
                                <input type="hidden" name="bat[only_screening]" value="0">
                                <input type="checkbox" name="bat[only_screening]" value="1" id="only_screening"> Only Screening
                            </label>
                        </div>

                        <?php if (!empty($screening_groups)): ?>
                            <div class="req-field" style="max-width:280px;margin-bottom:.6rem;">
                                <label class="req-lbl" for="screen_group_id">Screening Panel</label>
                                <select id="screen_group_id" name="batmeta[screen_group_id]" class="req-select">
                                    <option value="">Select Screening Panel</option>
                                    <?php foreach ($screening_groups as $sg): ?>
                                        <option value="<?php echo (int)$sg['tg_ID'] ?>"
                                            data-items="<?php echo esc_attr($sg['tg_comments'] ?? '') ?>">
                                            <?php echo esc_html($sg['tg_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="req-scr-grid" id="screening_div">
                            <?php foreach ($screenings as $arr):
                                $key = $arr['u_ID'];
                                $sc_cls = !empty($arr['u_not_auto_checked']) ? 'notautocheck' : '';
                            ?>
                                <input type="hidden" name="data_specimen_screening[<?php echo $key ?>][checked]" value="0">
                                <div class="req-scr-item">
                                    <label>
                                        <input type="checkbox"
                                            class="screening_cb screening_<?php echo $key ?> <?php echo $sc_cls ?>"
                                            name="data_specimen_screening[<?php echo $key ?>][checked]"
                                            value="1"
                                            data-u_reflex="<?php echo (int)($arr['u_reflex'] ?? 0) ?>"
                                            data-outcome="<?php echo esc_attr($arr['outcome'] ?? '') ?>"
                                            data-u_reflex_tests="<?php echo esc_attr($arr['u_reflex_tests'] ?? '') ?>"
                                            data-u_reflex_clinics="<?php echo esc_attr($arr['u_reflex_clinics'] ?? '') ?>">
                                        <?php echo esc_html($arr['u_name']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- LCMS panels -->
                <?php if (!empty($lcms_tests)): ?>
                    <div class="req-panel-masonry" id="default_requests"
                        style="column-count:<?php echo $panel_column ?>;">
                        <?php foreach ($lcms_tests as $group_id => $tests):
                            $group = $all_groups_meta[$group_id] ?? null;
                            if (!$group) continue;

                            $label   = $group['tg_name'];
                            $bgcolor = !empty($group['tg_bgcolor']) ? $group['tg_bgcolor'] : '#5b21b6';
                            $fgcolor = tox_front_contrast($bgcolor);
                            $bg_soft = $bgcolor . '18';
                            $opts    = @json_decode($group['tg_other_options'], true) ?: [];
                            $desc    = $opts['desc'] ?? '';

                            $group_class = 'req-groupparent';
                            if (!empty($group['tg_show_cli_IDs'])) {
                                $group_class .= ' by_clinic';
                                foreach (explode(',', $group['tg_show_cli_IDs']) as $pcid) {
                                    $group_class .= ' cli_id_' . trim($pcid);
                                }
                            }
                            if (!empty($group['tg_notshow_cli_IDs'])) {
                                $group_class .= ' not_by_clinic';
                                foreach (explode(',', $group['tg_notshow_cli_IDs']) as $pcid) {
                                    $group_class .= ' not_cli_id_' . trim($pcid);
                                }
                            }
                            if (!empty($group['tg_show_for_ref_lab_ID'])) {
                                $group_class .= ' by_ref_lab';
                                foreach (explode(',', $group['tg_show_for_ref_lab_ID']) as $rflid) {
                                    $group_class .= ' ref_lab_id_' . trim($rflid);
                                }
                            }
                            if (!empty($group['tg_notshow_for_ref_lab_ID'])) {
                                $group_class .= ' not_by_ref_lab';
                                foreach (explode(',', $group['tg_notshow_for_ref_lab_ID']) as $rflid) {
                                    $group_class .= ' not_ref_lab_id_' . trim($rflid);
                                }
                            }
                        ?>
                            <div class="<?php echo $group_class ?>"
                                data-gid="<?php echo $group_id ?>"
                                style="background:<?php echo $bg_soft ?>;">

                                <div class="req-group-head" style="background:<?php echo $bgcolor ?>;color:<?php echo $fgcolor ?>;"
                                    data-gid="<?php echo $group_id ?>">
                                    <?php echo esc_html($label) ?>
                                </div>

                                <div class="req-group-tests">
                                    <?php if ($desc): ?>
                                        <div class="req-panel-desc"><?php echo wp_kses_post($desc) ?></div>
                                    <?php endif; ?>
                                    <?php foreach ($tests as $t):
                                        $key     = strtoupper($t->key);
                                        $tname   = esc_html($t->te_name);
                                        $tclass  = trim($t->class ?? '');
                                        $ref_lab = (int)($t->te_ref_lab_ID ?? 0);
                                        $not_ref = (int)($t->te_not_ref_lab_ID ?? 0);
                                        $row_cls = '';
                                        if ($ref_lab) $row_cls .= " by_ref_lab ref_lab_id_{$ref_lab}";
                                        if ($not_ref) $row_cls .= " not_by_ref_lab not_ref_lab_id_{$not_ref}";
                                    ?>
                                        <div class="req-test-row <?php echo $row_cls ?>">
                                            <input type="checkbox"
                                                class="cb_test <?php echo esc_attr($tclass) ?>"
                                                name="bat[panel_tests_requested][]"
                                                value="<?php echo esc_attr($key) ?>"
                                                data-group="<?php echo $group_id ?>">
                                            <span><?php echo $tname ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <p id="req-panel-error" style="color:var(--req-danger);font-size:12px;margin-top:.5rem;display:none;">
                    Please select at least one test panel or test.
                </p>
            </div>
        </div>

        <!-- ══ INSURANCE CARD & PHOTO ID UPLOAD ══════════════════ -->
        <div class="req-section">
            <div class="req-section__head">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="4" width="20" height="16" rx="2" />
                    <line x1="2" y1="10" x2="22" y2="10" />
                </svg>
                Insurance Card &amp; Photo ID
            </div>
            <div class="req-section__body">
                <div class="req-g4">
                    <?php
                    $tf_uploads = [
                        ['ins_card_front',   'Ins Card — Front'],
                        ['ins_card_back',    'Ins Card — Back'],
                        ['patient_id_front', 'Patient ID — Front'],
                        ['patient_id_back',  'Patient ID — Back'],
                    ];
                    foreach ($tf_uploads as [$fname, $flabel]):
                    ?>
                        <div class="req-field">
                            <label class="req-lbl"><?php echo $flabel ?></label>
                            <div class="req-card-upload">
                                <label class="req-btn req-btn--secondary" style="font-size:11px;padding:.35rem .6rem;cursor:pointer;justify-content:center;">
                                    Choose File
                                    <input type="file" name="<?php echo $fname ?>" accept="image/*,application/pdf" style="display:none;">
                                </label>
                                <span style="font-size:11px;color:var(--req-muted);">No file chosen</span>
                            </div>
                            <input type="hidden" name="img_<?php echo $fname ?>" value="">
                        </div>
                    <?php endforeach; ?>
                </div>
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

        <!-- ══ NOTES & STATUS ════════════════════════════════════ -->
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
                        <label class="req-lbl" for="bat_internal">Internal Notes</label>
                        <textarea id="bat_internal" name="bat[internal_notes]" class="req-textarea" placeholder="Internal / lab only…"></textarea>
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="bat_notes_report">Notes (visible on report)</label>
                        <textarea id="bat_notes_report" name="bat[notes_report]" class="req-textarea" placeholder="Notes shown on patient report…"></textarea>
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="bat_status">Status</label>
                        <select id="bat_status" name="bat[batch_status]" class="req-select">
                            <?php foreach ($status_options as $sval => $slbl): ?>
                                <option value="<?php echo esc_attr($sval) ?>"><?php echo esc_html($slbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ SUBMIT ════════════════════════════════════════════ -->
        <div style="display:flex;gap:.7rem;justify-content:flex-end;flex-wrap:wrap;padding:.25rem 0 .5rem;">
            <input type="hidden" name="lab_action" value="tox_save_req_form">
            <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce) ?>">
            <input type="hidden" name="btype" value="tox">
            <input type="hidden" name="bat[btype]" value="tox">
            <input type="hidden" name="bat[batch_type]" value="E">
            <input type="hidden" name="bat[is_dna]" value="0">
            <input type="hidden" name="bat[lab_id]" value="1" id="req-hidden-lab-id">
            <input type="hidden" name="drchrono_patient_id" value="<?php echo esc_attr($patient_id ?? '') ?>">
            <input type="hidden" name="drchrono_doctor_id" value="<?php echo esc_attr($doctor_id ?? '') ?>">
            <input type="hidden" name="drchrono_practice_id" value="<?php echo esc_attr($practice_id ?? '') ?>">

            <button type="submit" class="req-btn req-btn--primary" id="req-submit-btn">
                <svg style="width:13px;height:13px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12" />
                </svg>
                Submit Requisition
            </button>
        </div>

    </form>
</div>
<div id="req-toast"></div>

<script>
    (function() {
        'use strict';
        var AJAX_URL = <?php echo json_encode(admin_url('admin-ajax.php')) ?>;
        var USE_PHASE = <?php echo $enable_phase ? 'true' : 'false' ?>;
        var LAB_URL = '<?php echo home_url("/lab-requisition/") ?>';

        /* ── Utilities ────────────────────────────────────── */
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

        /* ── File upload previews ─────────────────────────── */
        document.querySelectorAll('.req-card-upload input[type="file"]').forEach(function(inp) {
            inp.addEventListener('change', function() {
                var sp = this.closest('.req-card-upload').querySelector('span');
                if (sp) sp.textContent = this.files[0] ? this.files[0].name : 'No file chosen';
            });
        });

        /* ── Panel / clinic / ref-lab visibility ─────────── */
        function showHidePanels() {
            var cid = document.getElementById('cli_ID').value;
            var rl = (document.getElementById('ref_lab_ID') || {}).value || '';

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
                g.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
                    cb.checked = false;
                });
            });

            document.querySelectorAll('.by_ref_lab').forEach(function(g) {
                g.classList.remove('cactive');
                g.querySelectorAll('input').forEach(function(i) {
                    i.disabled = true;
                    i.checked = false;
                });
            });
            if (rl) document.querySelectorAll('.by_ref_lab.ref_lab_id_' + rl).forEach(function(g) {
                g.classList.add('cactive');
                g.querySelectorAll('input').forEach(function(i) {
                    i.disabled = false;
                });
            });

            document.querySelectorAll('.not_by_ref_lab').forEach(function(g) {
                g.classList.remove('notcactive');
            });
            if (rl) document.querySelectorAll('.not_by_ref_lab.not_ref_lab_id_' + rl).forEach(function(g) {
                g.classList.add('notcactive');
                g.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
                    cb.checked = false;
                });
            });
        }

        /* ── Load doctors via AJAX when clinic changes ───── */
        function loadDoctors(cliId) {
            if (!cliId) return;
            fetch(LAB_URL, {
                    method: 'POST',
                    body: new URLSearchParams({
                        lab_action: 'load_clinic_doctors',
                        nonce: document.querySelector('[name="nonce"]').value,
                        cli_id: cliId
                    })
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(data) {
                    if (!data.success) return;
                    var sel = document.getElementById('doc_ID_sel');
                    if (!sel) return;
                    sel.innerHTML = '<option value="">— Select Doctor —</option>';
                    (data.data || []).forEach(function(d) {
                        var opt2 = document.createElement('option');
                        opt2.value = d.doc_ID;
                        opt2.textContent = d.label;
                        opt2.dataset.low = (d.cus_individuals || '').toUpperCase();
                        opt2.dataset.med = (d.cus_med_risk || '').toUpperCase();
                        opt2.dataset.high = (d.cus_high_risk || '').toUpperCase();
                        opt2.dataset.cus_df_profile = d.cus_df_profile || '';
                        sel.appendChild(opt2);
                    });
                    // Switch to doctor dropdown
                    document.getElementById('doc_select_wrap').style.display = '';
                    document.getElementById('doc_first_wrap').style.display = 'none';
                    document.getElementById('doc_last_wrap').style.display = 'none';
                    document.getElementById('doc_first').removeAttribute('required');
                    document.getElementById('doc_last').removeAttribute('required');
                    sel.setAttribute('required', '');
                    // If only one doctor, auto-select
                    if (data.data && data.data.length === 1) {
                        sel.value = data.data[0].doc_ID;
                        sel.dispatchEvent(new Event('change'));
                    }
                })
                .catch(function() {});
        }

        document.getElementById('cli_ID').addEventListener('change', function() {
            var cid = this.value;
            showHidePanels();
            var opt = this.options[this.selectedIndex];
            if (!opt) return;

            // Load doctors for this clinic
            loadDoctors(cid);

            // Screening auto-select from clinic data
            var scrVal = parseInt(opt.dataset.screening || '0');
            var onlyScrVal = parseInt(opt.dataset.only_screening || '0');
            if (scrVal) {
                var scrYes = document.querySelector('input[name="screening"][value="1"]');
                if (scrYes) {
                    scrYes.checked = true;
                    scrYes.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                }
            } else {
                var scrNo = document.querySelector('input[name="screening"][value="0"]');
                if (scrNo) scrNo.checked = true;
            }
            var onlyCb = document.querySelector('input[name="bat[only_screening]"][type="checkbox"]');
            if (onlyCb) onlyCb.checked = !!onlyScrVal;

            // Default profile from clinic
            var profSel2 = document.getElementById('batcustom_profile');
            if (profSel2 && opt.dataset.tox_df_profile) {
                profSel2.value = opt.dataset.tox_df_profile;
                profSel2.dispatchEvent(new Event('change'));
            }

            // Phase show/hide
            if (USE_PHASE) {
                var phaseDiv = document.getElementById('phase_div');
                if (phaseDiv) phaseDiv.style.display = (parseInt(opt.dataset.phase || '0') ? '' : 'none');
            }
        });

        /* Doctor select change → apply default profile ─── */
        document.addEventListener('change', function(e) {
            if (e.target.id !== 'doc_ID_sel') return;
            var selOpt = e.target.options[e.target.selectedIndex];
            if (!selOpt) return;
            var profSel3 = document.getElementById('batcustom_profile');
            if (profSel3 && selOpt.dataset.cus_df_profile) {
                profSel3.value = selOpt.dataset.cus_df_profile;
                profSel3.dispatchEvent(new Event('change'));
            }
        });

        var rlSel = document.getElementById('ref_lab_ID');
        if (rlSel) rlSel.addEventListener('change', showHidePanels);

        /* ── LCMS group-label click → toggle all tests ────── */
        document.addEventListener('click', function(e) {
            var head = e.target.closest('.req-group-head[data-gid]');
            if (!head) return;
            var box = head.closest('.req-groupparent');
            var cbs = box.querySelectorAll('.cb_test');
            var anyChecked = Array.from(cbs).some(function(c) {
                return c.checked;
            });
            cbs.forEach(function(c) {
                c.checked = !anyChecked;
            });
            box.classList.toggle('selected', !anyChecked);
        });

        /* ── Insurance billing-type toggle ───────────────── */
        var insFields = document.getElementById('ins-fields');
        var wcFields = document.getElementById('wc-fields');
        var insReqs = insFields ? insFields.querySelectorAll('.ins-required') : [];
        var insStars = insFields ? insFields.querySelectorAll('.ins-req') : [];

        document.querySelectorAll('[name="bat[ins_comp]"]').forEach(function(r) {
            r.addEventListener('change', function() {
                var val = this.value;
                if (insFields) insFields.style.display = (val === 'ins') ? '' : 'none';
                if (wcFields) wcFields.style.display = (val === 'comp') ? '' : 'none';
                insReqs.forEach(function(el) {
                    val === 'ins' ? el.setAttribute('required', '') : el.removeAttribute('required');
                });
                insStars.forEach(function(el) {
                    el.style.display = val === 'ins' ? '' : 'none';
                });
            });
        });

        /* Self auto-fill insurance */
        function syncSelf() {
            var rel = document.querySelector('[name="ins1[ins_relation]"]:checked');
            if (!rel || rel.value !== 'Self') return;
            var iL = document.getElementById('ins1_last'),
                pL = document.getElementById('pa_last');
            var iF = document.getElementById('ins1_first'),
                pF = document.getElementById('pa_first');
            if (pL && iL && !iL.value) iL.value = pL.value;
            if (pF && iF && !iF.value) iF.value = pF.value;
        }
        var pLast = document.getElementById('pa_last'),
            pFirst = document.getElementById('pa_first'),
            rSelf = document.getElementById('ins_rel_self');
        if (pLast) pLast.addEventListener('change', syncSelf);
        if (pFirst) pFirst.addEventListener('change', syncSelf);
        if (rSelf) rSelf.addEventListener('change', syncSelf);

        /* ── Screening radio toggle ───────────────────────── */
        document.querySelectorAll('input[name="screening"]').forEach(function(r) {
            r.addEventListener('change', function() {
                if (this.value === '1') {
                    document.querySelectorAll('.screening_cb').forEach(function(cb) {
                        if (!cb.classList.contains('notautocheck')) cb.checked = true;
                    });
                } else {
                    document.querySelectorAll('.screening_cb').forEach(function(cb) {
                        cb.checked = false;
                    });
                    var onlyCb = document.querySelector('input[name="bat[only_screening]"][type="checkbox"]');
                    if (onlyCb) {
                        onlyCb.checked = false;
                    }
                }
            });
        });

        /* Screening individual cb → update radio */
        document.addEventListener('change', function(e) {
            if (!e.target.classList.contains('screening_cb')) return;
            var any = !!document.querySelector('.screening_cb:checked');
            var r = document.querySelector('input[name="screening"][value="' + (any ? '1' : '0') + '"]');
            if (r) r.checked = true;

            // Reflex logic
            var cb = e.target;
            if (cb.dataset.u_reflex === '1' && cb.checked && cb.dataset.outcome === 'POS') {
                var tests = (cb.dataset.u_reflex_tests || '').split(',').filter(Boolean);
                var clinics = (cb.dataset.u_reflex_clinics || '').split(',').filter(Boolean);
                var cli_id = document.getElementById('cli_ID').value;
                var inCli = !clinics.length || clinics.indexOf(cli_id) !== -1;
                if (inCli) tests.forEach(function(t) {
                    var inp = document.querySelector('input.cb_test[value="' + t + '"]');
                    if (inp) inp.checked = true;
                });
            }
        });

        var scrGroupSel = document.getElementById('screen_group_id');
        if (scrGroupSel) scrGroupSel.addEventListener('change', function() {
            var opt = this.options[this.selectedIndex];
            var items = (opt.dataset.items || '').split(',').filter(Boolean);
            if (!items.length) return;
            document.querySelectorAll('.screening_cb').forEach(function(cb) {
                cb.checked = false;
            });
            items.forEach(function(k) {
                var cb = document.querySelector('.screening_cb.screening_' + k);
                if (cb) {
                    cb.checked = true;
                    cb.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                }
            });
        });

        /* ── Diag chip UI ────────────────────────────────── */
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
                    var inp = diagHidden && diagHidden.querySelector('input[value="' + code + '"]');
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

        var profSel = document.getElementById('batcustom_profile');
        if (profSel) {
            profSel.addEventListener('change', function() {
                var tpl = (this.value || '').toLowerCase();

                // Clear all LCMS checkboxes first
                document.querySelectorAll('#default_requests input[type="checkbox"]').forEach(function(cb) {
                    cb.checked = false;
                });

                if (!tpl || tpl === 'do not use custom profile') return;

                // Map profile name → class suffix and doctor data-key
                var key = '';
                if (tpl.indexOf('mid ') > -1 || tpl === 'standard') key = 'med';
                else if (tpl.indexOf('low ') > -1 || tpl === 'basic') key = 'low';
                else if (tpl.indexOf('high ') > -1 || tpl === 'highrisk') key = 'high';
                else if (tpl.indexOf('comprehensive') > -1) key = 'comprehensive';

                // 1) Try doctor custom profile data first
                var docSel = document.querySelector('[name="bat[doc_ID]"]');
                var testStr = '';
                if (key && docSel) {
                    var selOpt = docSel.options[docSel.selectedIndex];
                    if (selOpt) testStr = selOpt.dataset[key] || '';
                }

                if (key && testStr && testStr.length > 0) {
                    var tests = testStr.split(',');
                    tests.forEach(function(t) {
                        t = t.trim();
                        if (!t) return;
                        var cb = document.querySelector('#default_requests input[type="checkbox"][value="' + t + '"]');
                        if (cb) {
                            cb.checked = true;
                            return;
                        }
                        document.querySelectorAll('#default_requests input[type="checkbox"]').forEach(function(inp) {
                            if (inp.value.indexOf(',') > -1 && testStr.indexOf(inp.value) > -1) {
                                inp.checked = true;
                            }
                        });
                    });
                } else if (tpl === 'previous testing') {
                    var paId = document.querySelector('[name="pa[pa_ID]"]');
                    var lastTests = paId ? (paId.dataset.last_tests || '') : '';
                    if (lastTests) {
                        lastTests.split(',').forEach(function(t) {
                            t = t.trim();
                            var cb = document.querySelector('#default_requests input[type="checkbox"][value="' + t + '"]');
                            if (cb) cb.checked = true;
                        });
                    }
                } else {
                    var clsSuffix = tpl;
                    if (tpl.indexOf('mid ') > -1) clsSuffix = 'standard';
                    else if (tpl.indexOf('low ') > -1) clsSuffix = 'basic';
                    else if (tpl.indexOf('high ') > -1) clsSuffix = 'highrisk';
                    document.querySelectorAll('#default_requests input.te_' + clsSuffix).forEach(function(cb) {
                        cb.checked = true;
                    });
                }

                document.querySelectorAll('#default_requests input[disabled]').forEach(function(cb) {
                    cb.checked = false;
                });

                document.querySelectorAll('.screening_cb').forEach(function(cb) {
                    cb.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                });
            });
        }

        /* ── Validation ──────────────────────────────────── */
        function validate(isSave) {
            var ok = true;
            var form = document.getElementById('req-requisition-form');

            form.querySelectorAll('[required]').forEach(function(el) {
                if (el.type === 'radio') return;
                if (el.offsetParent === null) return;
                if (el.classList.contains('ins-required')) {
                    var insType = document.querySelector('[name="bat[ins_comp]"]:checked');
                    if (!insType || insType.value !== 'ins') return;
                }
                if (!el.value.trim()) {
                    el.classList.add('error');
                    ok = false;
                } else {
                    el.classList.remove('error');
                }
            });

            var radioGroups = ['bat[type_of_specimen]'];
            radioGroups.forEach(function(rname) {
                var checked = document.querySelector('input[name="' + rname + '"]:checked');
                if (!checked) {
                    // Highlight the container
                    var container = document.querySelector('input[name="' + rname + '"]');
                    if (container) container.closest('div[style]') && container.closest('div').classList.add('error');
                    ok = false;
                }
            });

            <?php if ($dagnostic_required): ?>
                var de = document.getElementById('diag-error');
                if (addedCodes.size === 0) {
                    if (de) de.style.display = 'block';
                    ok = false;
                } else {
                    if (de) de.style.display = 'none';
                }
            <?php endif; ?>

            var onlyCb = document.querySelector('input[name="bat[only_screening]"][type="checkbox"]');
            if (!onlyCb || !onlyCb.checked) {
                var pError = document.getElementById('req-panel-error');
                var anyTest = !!(
                    document.querySelector('#default_requests input.cb_test:checked') ||
                    document.querySelector('.screening_cb:checked')
                );
                if (!anyTest) {
                    if (pError) pError.style.display = 'block';
                    ok = false;
                } else {
                    if (pError) pError.style.display = 'none';
                }
            }
            return ok;
        }
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

        /* ── Submit ──────────────────────────────────────── */
        function doSubmit() {
            if (!validate()) {
                toast('Please fill in all required fields.', 'err');
                var firstErr = document.querySelector('.error');
                console.log(firstErr);

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
            form.querySelectorAll('.req-groupparent.not_by_ref_lab.notcactive input[type="checkbox"]').forEach(function(cb) {
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
            doSubmit(false);
        });

        document.getElementById('req-requisition-form').addEventListener('input', function(e) {
            e.target.classList.remove('error');
        });

        // Init
        showHidePanels();

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
