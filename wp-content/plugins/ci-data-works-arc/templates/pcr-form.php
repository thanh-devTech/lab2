<?php

/**
 * PCR Requisition Form – Frontend / iFrame Template
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

// Pre-fill from DrChrono
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

// Settings (mirrors file-1 $settings)
$settings      = get_option('ci_req_settings_pcr', []);
$panel_column  = isset($settings['panel_column']) && $settings['panel_column'] >= 2
    ? (int)$settings['panel_column'] : 4;
$pcr_orderable_ua  = !empty($settings['pcr_orderable_ua']);
$enable_cultureid  = !empty($settings['enabel_cultureid']);
$e_orderable_test  = !empty($settings['e_orderable_test']);
$below_panels_html = $settings['below_panels'] ?? '';
$enable_filter     = (bool) tr_get_option('ci_req_settings_pcr', 'enable_filter');

// Clinics
$clinics = $wpdb->get_results(
    "SELECT cID, cli_clinic, cli_show_reason_testing FROM ci_clinic ORDER BY cli_clinic ASC",
    ARRAY_A
);

$profiles_tests = tr_pdd_get_profiles_tests_requesteds('pcr', true, false, date('Y-m-d'));

$all_groups_meta = [];
$groups_raw = $wpdb->get_results(
    "SELECT tg_ID, tg_name, tg_label_name, tg_bgcolor, tg_show_req,
            tg_parent, tg_relate_to, tg_only_group, tg_notestcheckbox,
            tg_show_cli_IDs, tg_notshow_cli_IDs, tg_other_options, tg_position
       FROM ci_test_groups
      WHERE tg_type = 'pcr'
      ORDER BY tg_position ASC, tg_ID ASC",
    ARRAY_A
);
foreach ($groups_raw as $g) {
    $all_groups_meta[(int)$g['tg_ID']] = $g;
}

// ── Specimen sources per group ───────────────────────────────────────────────
$specimens_by_group = [];
foreach (
    $wpdb->get_results(
        "SELECT ts_group_id, ts_name FROM ci_test_specimens WHERE ts_type='pcr'",
        ARRAY_A
    ) as $s
) {
    $specimens_by_group[(int)$s['ts_group_id']][] = $s['ts_name'];
}

$render_group_ids = [];
foreach ($groups_raw as $g) {
    $gid = (int)$g['tg_ID'];
    if (!$g['tg_show_req']) continue;
    // include if the group itself is show_req OR if it has tests returned by the function
    if (isset($profiles_tests[$gid]) || $g['tg_show_req']) {
        $render_group_ids[] = $gid;
    }
}

// ── Filter tags (from tg_other_options JSON, sorted by priority list) ────────
$priority_filters = ['Respiratory', 'UTI', 'STI', 'Vaginosis', 'Fungal', 'Wound', 'GI'];
$all_filters = [];
if ($enable_filter) {
    foreach ($all_groups_meta as $g) {
        $opts = @json_decode($g['tg_other_options'], true) ?: [];
        if (!empty($opts['filters'])) {
            foreach (array_map('trim', explode(',', $opts['filters'])) as $f) {
                $all_filters[$f] = true;
            }
        }
    }
    $all_filters = array_keys($all_filters);
    usort($all_filters, function ($a, $b) use ($priority_filters) {
        $pa = array_search($a, $priority_filters);
        if ($pa === false) $pa = 9999;
        $pb = array_search($b, $priority_filters);
        if ($pb === false) $pb = 9999;
        return $pa - $pb;
    });
}

// Diagnostic codes
$diag_codes = function_exists('tr_pdd_get_dagnostic_codes')
    ? tr_pdd_get_dagnostic_codes('pcr') : [];

$us_states = tr_get_states();

// Helper: contrast colour for panel headers
if (!function_exists('pcr_front_contrast')) {
    function pcr_front_contrast(string $hex): string
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
                                    data-reason="<?php echo (int)$c['cli_show_reason_testing'] ?>">
                                    <?php echo esc_html($c['cli_clinic']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="req-field">
                        <label class="req-lbl" for="doc_first">Physician First Name<span class="req">*</span></label>
                        <input type="text" id="doc_first" name="doc[doc_first]" class="req-input"
                            value="<?php echo $pre_doc_first ?>" required placeholder="First">
                    </div>

                    <div class="req-field">
                        <label class="req-lbl" for="doc_last">Physician Last Name<span class="req">*</span></label>
                        <input type="text" id="doc_last" name="doc[doc_last]" class="req-input"
                            value="<?php echo $pre_doc_last ?>" required placeholder="Last">
                    </div>

                    <div class="req-field">
                        <label class="req-lbl" for="doc_npi">NPI Number</label>
                        <input type="text" id="doc_npi" name="doc[doc_npi]" class="req-input"
                            value="<?php echo $pre_doc_npi ?>" maxlength="10" placeholder="10 digits">
                    </div>

                    <div class="req-field">
                        <label class="req-lbl" for="doc_specialty">Specialty</label>
                        <input type="text" id="doc_specialty" name="doc[doc_specialty]" class="req-input"
                            placeholder="e.g. Internal Medicine">
                    </div>

                    <div class="req-field">
                        <label class="req-lbl" for="bat_doc">Date of Collection<span class="req">*</span></label>
                        <input type="datetime-local" id="bat_doc" name="bat[doc]" class="req-input"
                            value="<?php echo date('Y-m-d\TH:i') ?>" required>
                    </div>

                </div>
            </div>
        </div>

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
                        <input type="tel" id="pa_phone" name="pa[pa_phone]" class="req-input" value="<?php echo $pre_phone ?>" required maxlength="18">
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="pa_email">Email</label>
                        <input type="email" id="pa_email" name="pa[pa_email]" class="req-input" value="<?php echo $pre_email ?>">
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="pa_mrn">Medical Record #</label>
                        <input type="text" id="pa_mrn" name="pa[pa_mrn]" class="req-input" value="<?php echo $pre_mrn ?>" maxlength="15">
                    </div>

                    <div class="req-field req-span2">
                        <label class="req-lbl" for="pa_address">Street Address<span class="req">*</span></label>
                        <input type="text" id="pa_address" name="pa[pa_address]" class="req-input" value="<?php echo $pre_address ?>" required maxlength="150">
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="pa_city">City<span class="req">*</span></label>
                        <input type="text" id="pa_city" name="pa[pa_city]" class="req-input" value="<?php echo $pre_city ?>" required maxlength="100">
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
                        <label class="req-lbl" for="pa_ssn">SSN</label>
                        <input type="text" id="pa_ssn" name="pa[pa_ssn]" class="req-input" maxlength="11" placeholder="XXX-XX-XXXX">
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

                </div>
            </div>
        </div>

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
                        <input type="radio" name="bat[ins_comp]" value="client">
                        Client Bill
                    </label>
                </div>

                <div id="ins-fields">
                    <div class="req-g3">

                        <div class="req-field">
                            <label class="req-lbl" for="ins_last">Insured Last Name<span class="req ins-req">*</span></label>
                            <input type="text" id="ins_last" name="ins1[ins_last]" class="req-input ins-required" required>
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
                            <input type="text" id="ins_company" name="ins1[ins_company]" class="req-input ins-required" required>
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
                            <label class="req-lbl" for="ins_phone">Insurance Phone</label>
                            <input type="tel" id="ins_phone" name="ins1[ins_phone]" class="req-input">
                        </div>
                        <div class="req-field">
                            <label class="req-lbl" for="ins_auth_id">Authorization #</label>
                            <input type="text" id="ins_auth_id" name="ins1[ins_authorization_id]" class="req-input">
                        </div>
                        <div class="req-field">
                            <label class="req-lbl" for="ins_auth_date">Authorization Date</label>
                            <input type="date" id="ins_auth_date" name="ins1[ins_authorization_date]" class="req-input">
                        </div>

                    </div>
                </div><!-- /ins-fields -->

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

        <?php if (!empty($diag_codes)): ?>
            <div class="req-section">
                <div class="req-section__head">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 11l3 3L22 4" />
                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
                    </svg>
                    Diagnostic Codes (ICD-10)
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
                </div>
            </div>
        <?php endif; ?>

        <div class="req-section">
            <div class="req-section__head">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2v-4M9 21H5a2 2 0 0 1-2-2v-4m0 0h18" />
                </svg>
                TEST LIST — Please check appropriate panels &amp; tests
            </div>
            <div class="req-section__body">

                <input type="hidden" name="has_panel_requests" value="1">

                <?php if ($enable_filter && !empty($all_filters)): ?>
                    <div class="req-filter-bar">
                        <span class="req-filter-lbl">Filter:</span>
                        <button type="button" class="req-filter-btn active" data-filter="all">All</button>
                        <?php foreach ($all_filters as $f): ?>
                            <button type="button" class="req-filter-btn" data-filter="<?php echo esc_attr($f) ?>"><?php echo esc_html($f) ?></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="req-panel-masonry" id="req-panel-masonry"
                    style="column-count:<?php echo $panel_column ?>;">

                    <?php foreach ($render_group_ids as $gid):
                        // ── Resolve group meta ───────────────────────────────────────────────
                        $group     = $all_groups_meta[$gid] ?? null;
                        if (!$group) continue;

                        $label      = $group['tg_name'];
                        $label_name = $group['tg_label_name'];
                        $bgcolor    = !empty($group['tg_bgcolor']) ? $group['tg_bgcolor'] : '#3b82f6';
                        $fgcolor    = pcr_front_contrast($bgcolor);
                        $bg_soft    = $bgcolor . '18';
                        $bg_spec    = $bgcolor . '44';

                        $opts    = @json_decode($group['tg_other_options'], true) ?: [];
                        $filters = !empty($opts['filters'])
                            ? implode(',', array_map('trim', explode(',', $opts['filters'])))
                            : '';
                        $desc    = $opts['desc'] ?? '';

                        $tests     = $profiles_tests[$gid] ?? [];
                        $specimens = $specimens_by_group[$gid] ?? [];

                        if (!empty($group['tg_only_group'])) $tests = [];

                        // ── CSS classes ───────────────────────────────────────────────────────
                        $group_class = 'req-groupparent';

                        if (!empty($group['tg_cli_IDs'])) {
                            $group_class .= ' by_clinic';
                            foreach (explode(',', $group['tg_cli_IDs']) as $pcid) {
                                $group_class .= ' cli_id_' . trim($pcid);
                            }
                        }
                        if (!empty($group['tg_not_cli_IDs'])) {
                            $group_class .= ' not_by_clinic';
                            foreach (explode(',', $group['tg_not_cli_IDs']) as $pcid) {
                                $group_class .= ' not_cli_id_' . trim($pcid);
                            }
                        }
                        if ((int)$group['tg_parent'] > 0 && stripos($label, 'reflex') === false) {
                            $group_class .= ' parent_group_' . $group['tg_parent'];
                        }
                        if (!empty($group['tg_relate_to'])) {
                            $group_class .= ' relate_to_' . $group['tg_relate_to'];
                        }

                        $cb_group_class = 'batcustom_profile cb_test';
                        $is_ua = stripos($label, '(ua)') !== false;

                        if ($is_ua) {
                            $cb_group_class .= ' gua';
                        } elseif (stripos($label, 'Antibiotic Resistance') !== false && stripos($label, 'UTI') !== false) {
                            $cb_group_class .= ' gutiabx';
                        } elseif (stripos($label, 'uti') !== false || stripos($label, 'Urinary Tract Infection') !== false) {
                            $cb_group_class .= ' guti';
                            $group_class    .= ' boxguti';
                        }
                        // SPECIAL: Orderable UA box (mirrors file-2 logic)
                        if ($is_ua && $pcr_orderable_ua):
                    ?>
                            <div class="<?php echo $group_class ?>"
                                data-gid="<?php echo $gid ?>"
                                data-filters="<?php echo esc_attr($filters) ?>"
                                style="background:<?php echo $bg_soft ?>;">

                                <label class="req-group-head" style="background:<?php echo $bgcolor ?>;color:<?php echo $fgcolor ?>;">
                                    <input type="checkbox"
                                        class="<?php echo $cb_group_class ?>"
                                        name="bat[panel_tests_requested][]"
                                        value="GROUP_<?php echo $gid ?>"
                                        data-relate_to="<?php echo esc_attr($group['tg_relate_to']) ?>"
                                        data-grouprint="<?php echo esc_attr($label_name) ?>">
                                    <?php echo esc_html($label) ?>
                                </label>

                                <div class="req-ua-box">
                                    <input type="hidden" name="bat[orderable_test]" value="0">
                                    <label class="req-chklbl">
                                        <input type="checkbox" class="cb_test orderable_ua" name="bat[orderable_test]" value="-1">
                                        UA + UTI w/ABX
                                    </label>
                                    <label class="req-chklbl">
                                        <input type="checkbox" class="cb_test orderable_ua" name="bat[orderable_test]" value="-2">
                                        UA reflex to UTI w/ABX
                                    </label>
                                    <label class="req-chklbl">
                                        <input type="checkbox" class="cb_test orderable_ua" name="bat[orderable_test]" value="-3">
                                        UA reflex to UTI w/ABX reflex
                                    </label>

                                    <?php if (!empty($specimens)): ?>
                                        <div class="req-specimens" style="margin-top:.4rem;">
                                            <span class="req-spec-title" style="background:<?php echo $bg_spec ?>;color:<?php echo $fgcolor ?>;">
                                                SPECIMEN SOURCE
                                            </span>
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

                        <?php
                            continue; // ← done with UA group, skip normal render
                        endif;
                        // NORMAL group box
                        ?>

                        <div class="<?php echo $group_class ?>"
                            data-gid="<?php echo $gid ?>"
                            data-filters="<?php echo esc_attr($filters) ?>"
                            style="background:<?php echo $bg_soft ?>;">

                            <!-- Group master checkbox header -->
                            <label class="req-group-head" style="background:<?php echo $bgcolor ?>;color:<?php echo $fgcolor ?>;">
                                <input type="checkbox"
                                    class="<?php echo $cb_group_class ?>"
                                    name="bat[panel_tests_requested][]"
                                    value="GROUP_<?php echo $gid ?>"
                                    data-relate_to="<?php echo esc_attr($group['tg_relate_to']) ?>"
                                    data-grouprint="<?php echo esc_attr($label_name) ?>">
                                <?php echo esc_html($label) ?>
                            </label>

                            <div class="req-group-tests">
                                <?php if ($desc): ?>
                                    <div class="req-panel-desc"><?php echo wp_kses_post($desc) ?></div>
                                <?php endif; ?>

                                <?php
                                $seen_subgroups = [];

                                foreach ($tests as $t):
                                    if (empty($t->te_ID)) continue; // group-only row with no test

                                    $tid      = $t->te_ID;
                                    $tname    = $t->te_name;
                                    $tgid_row = (int)$t->tg_ID;
                                    $no_cb    = (bool)$t->te_nocheckbox || !empty($group['tg_notestcheckbox']);
                                    $font     = (int)$t->te_font_style;
                                    $tclass   = trim($t->class ?? '');

                                    if ($font === 1)     $tname = '<i>'  . esc_html($tname) . '</i>';
                                    elseif ($font === 2) $tname = '<b>'  . esc_html($tname) . '</b>';
                                    else                 $tname = esc_html($tname);

                                    if ($tgid_row !== $gid && !in_array($tgid_row, $seen_subgroups)):
                                        $seen_subgroups[] = $tgid_row;
                                        $sub_key = 'GROUP_' . $tgid_row;
                                        $is_abx  = stripos($t->main_group ?? '', 'Antibiotic Resistance Genes (UTI qPCR)') !== false;
                                ?>
                                        <div class="req-subgroup-head">
                                            <?php if ($is_abx): ?>
                                                <label class="req-chklbl" style="justify-content:center;">
                                                    <input type="checkbox" class="batcustom_profile"
                                                        name="bat[panel_tests_requested][]"
                                                        value="<?php echo esc_attr($sub_key) ?>">
                                                    <?php echo esc_html($t->main_group ?? '') ?>
                                                </label>
                                            <?php else: ?>
                                                <?php echo esc_html($t->main_group ?? '') ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php
                                    endif;
                                    ?>

                                    <div class="req-test-row <?php echo $no_cb ? 'nocb' : '' ?>">
                                        <?php if (!$no_cb): ?>
                                            <input type="checkbox"
                                                class="cb_test <?php echo esc_attr($tclass) ?>"
                                                name="bat[panel_tests_requested][]"
                                                value="<?php echo esc_attr($tid) ?>"
                                                data-group="<?php echo $gid ?>">
                                        <?php endif; ?>
                                        <span><?php echo $tname ?></span>
                                    </div>

                                <?php endforeach;
                                ?>

                                <!-- Specimen source (mirrors file-2) -->
                                <?php if (!empty($specimens)): ?>
                                    <div class="req-specimens" data-group="<?php echo $gid ?>">
                                        <span class="req-spec-title"
                                            style="background:<?php echo $bg_spec ?>;color:<?php echo $fgcolor ?>;">
                                            SPECIMEN SOURCE
                                        </span>
                                        <?php foreach ($specimens as $spec): ?>
                                            <label class="req-chklbl">
                                                <input type="checkbox" name="bat[type_of_specimen][]"
                                                    value="<?php echo esc_attr($spec) ?>">
                                                <?php echo esc_html($spec) ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                            </div><!-- /req-group-tests -->
                        </div><!-- /req-groupparent -->

                    <?php endforeach; // $render_group_ids 
                    ?>
                </div><!-- /req-panel-masonry -->

                <!-- Below-panel extras (mirrors file-2 bottom section) -->
                <?php if ($enable_cultureid): ?>
                    <div style="margin-top:.6rem;">
                        <label class="req-chklbl">
                            <input type="hidden" name="bat[culture]" value="0">
                            <input type="checkbox" name="bat[culture]" value="1">
                            Culture ID with Reflexive Antibiotic Sensitivity testing
                        </label>
                    </div>
                <?php endif; ?>

                <?php if (!$pcr_orderable_ua && $e_orderable_test): ?>
                    <div style="margin-top:.4rem;">
                        <label class="req-chklbl">
                            <input type="hidden" name="bat[orderable_test]" value="0">
                            <input type="checkbox" class="cb_test" name="bat[orderable_test]" value="1">
                            Orderable Test
                        </label>
                    </div>
                <?php endif; ?>

                <?php if (!empty($below_panels_html)): ?>
                    <div style="margin-top:.6rem;"><?php echo wp_kses_post($below_panels_html) ?></div>
                <?php endif; ?>

                <p id="req-panel-error"
                    style="color:var(--req-danger);font-size:12px;margin-top:.5rem;display:none;">
                    Please select at least one test panel or test.
                </p>

            </div>
        </div>
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
                        <label class="req-file-btn"
                            id="lbl_issue_photo" for="file_issue_photo">
                            <svg style="width:20px;height:20px;opacity:.6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                <circle cx="8.5" cy="8.5" r="1.5" />
                                <polyline points="21 15 16 10 5 21" />
                            </svg>
                            <span id="lbl_txt_issue_photo">Upload Issue Photo</span>
                            <input type="file" id="file_issue_photo" name="issue_photo" accept="image/*" style="display:none">
                        </label>
                        <input type="hidden" name="bat[issue_photo]" value="" id="val_issue_photo">
                    </div>

                </div>
            </div>
        </div>
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
                        <label class="req-lbl" for="bat_internal">Internal Notes</label>
                        <textarea id="bat_internal" name="bat[internal_notes]" class="req-textarea" placeholder="Internal / lab only…"></textarea>
                    </div>
                    <div class="req-field">
                        <label class="req-lbl" for="bat_status">Status</label>
                        <?php
                        $status_options = tr_pdd_get_batch_statuses_on_hold(tr_pdd_get_batch_statuses(false, false, false, 'pcr'), '', '', 'ci_batches_pcr', 'pcr');
                        ?>
                        <select id="bat_status" name="bat[batch_status]" class="req-select">
                            <?php foreach ($status_options as $status => $label): ?>
                                <option value="<?php echo $status; ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>
            </div>
        </div>

        <div style="display:flex;gap:.7rem;justify-content:flex-end;flex-wrap:wrap;padding:.25rem 0 .5rem;">
            <input type="hidden" name="lab_action" value="pcr_save_req_form">
            <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce) ?>">
            <input type="hidden" name="btype" value="pcr">
            <input type="hidden" name="bat[btype]" value="pcr">
            <input type="hidden" name="bat[is_dna]" value="16">
            <input type="hidden" name="bat[lab_id]" value="1" id="hidden_lab_id">
            <input type="hidden" name="drchrono_patient_id" value="<?php echo esc_attr($patient_id  ?? '') ?>">
            <input type="hidden" name="drchrono_doctor_id" value="<?php echo esc_attr($doctor_id   ?? '') ?>">
            <input type="hidden" name="drchrono_practice_id" value="<?php echo esc_attr($practice_id ?? '') ?>">

            <button type="submit" class="req-btn req-btn--primary" id="req-submit-btn">
                <svg style="width:13px;height:13px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12" />
                </svg>
                Submit Requisition
            </button>
        </div>

    </form>
</div><!-- /req-form-wrap -->

<div id="req-toast"></div>

<script>
    (function() {
        'use strict';

        var AJAX_URL = <?php echo json_encode(admin_url('admin-ajax.php')) ?>;
        var LAB_URL = '<?php echo home_url("/lab-requisition/") ?>';

        /* ── Utility ─────────────────────────────────────────────── */
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

        /* ══════════════════════════════════════════════════════════
           PANEL INTERACTIONS
           Mirrors the jQuery logic from file-1 and file-2 JS,
           rewritten in vanilla JS.
        ══════════════════════════════════════════════════════════ */

        var $onlyonepanel = <?php echo isset($settings['onlyonepanel']) ? intval($settings['onlyonepanel']) : 0 ?>;
        var $changebychild = false;

        function parseIds(raw) {
            if (!raw) return [];
            return String(raw).split(',').map(function(s) {
                return s.trim();
            }).filter(Boolean);
        }

        function updateSelected(box) {
            var any = box.querySelector('.batcustom_profile:checked');
            box.classList.toggle('selected', !!any);
        }

        /* Group master checkbox → all child tests + specimens */
        document.addEventListener('change', function(e) {
            var cb = e.target;
            if (!cb.classList.contains('batcustom_profile')) return;

            var box = cb.closest('.req-groupparent');
            if (!box) return;
            var checked = cb.checked;

            // Only-one-panel guard
            if ($onlyonepanel && checked && !cb.dataset.byrelated) {
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

            // Toggle child tests
            if (!$changebychild) {
                box.querySelectorAll('.cb_test').forEach(function(t) {
                    t.checked = checked;
                });
            }

            // Toggle specimens required state
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

            // Relate-to group cascade
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

            $changebychild = false;
            updateSelected(box);
        });

        /* Individual test checkbox → update parent group */
        document.addEventListener('change', function(e) {
            var cb = e.target;
            if (!cb.classList.contains('cb_test') || cb.classList.contains('batcustom_profile')) return;
            var gid = cb.dataset.group;
            if (!gid) return;
            var parentCb = document.querySelector('.batcustom_profile[value="GROUP_' + gid + '"]');
            if (!parentCb) return;
            var any = !!document.querySelector('.cb_test[data-group="' + gid + '"]:checked');
            $changebychild = true;
            parentCb.checked = any;
            parentCb.dispatchEvent(new Event('change', {
                bubbles: true
            }));
            var box = parentCb.closest('.req-groupparent');
            if (box) updateSelected(box);
        });

        /* Orderable UA – only one at a time + mirror gua/guti/gutiabx */
        document.addEventListener('change', function(e) {
            if (!e.target.classList.contains('orderable_ua')) return;
            if (!e.target.checked) return;
            var val = e.target.value;
            document.querySelectorAll('.orderable_ua').forEach(function(o) {
                if (o !== e.target) o.checked = false;
            });

            function setGrp(cls, state) {
                var gc = document.querySelector('.batcustom_profile.' + cls);
                if (!gc || gc.checked === state) return;
                $changebychild = !state;
                gc.checked = state;
                gc.dispatchEvent(new Event('change', {
                    bubbles: true
                }));
            }
            if (val === '-1') {
                setGrp('gua', true);
                setGrp('guti', true);
                setGrp('gutiabx', true);
                document.querySelectorAll('.boxguti .req-specimens input[type="checkbox"]').forEach(function(s) {
                    s.checked = true;
                });
            } else if (val === '-2') {
                setGrp('gua', true);
                setGrp('guti', true);
                setGrp('gutiabx', false);
                document.querySelectorAll('.boxguti .req-specimens input[type="checkbox"]').forEach(function(s) {
                    s.checked = true;
                });
            } else if (val === '-3') {
                setGrp('gua', true);
                setGrp('guti', false);
                setGrp('gutiabx', false);
            }
        });

        /* ── Clinic change → by_clinic visibility ──────────────── */
        document.getElementById('cli_ID').addEventListener('change', function() {
            var cid = this.value;
            // by_clinic: hide all, show matching
            document.querySelectorAll('.by_clinic:not(.in_group)').forEach(function(g) {
                g.classList.remove('cactive');
            });
            if (cid) {
                document.querySelectorAll('.by_clinic.cli_id_' + cid).forEach(function(g) {
                    g.classList.add('cactive');
                });
            }
            // not_by_clinic
            document.querySelectorAll('.not_by_clinic').forEach(function(g) {
                g.classList.remove('notcactive');
            });
            if (cid) {
                document.querySelectorAll('.not_by_clinic.not_cli_id_' + cid + ':not(.in_group)').forEach(function(g) {
                    g.classList.add('notcactive');
                });
            }
        });

        /* ── Filter buttons ────────────────────────────────────── */
        document.querySelectorAll('.req-filter-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.req-filter-btn').forEach(function(b) {
                    b.classList.remove('active');
                });
                this.classList.add('active');
                var filter = this.dataset.filter;
                document.querySelectorAll('.req-groupparent').forEach(function(g) {
                    if (g.classList.contains('notcactive')) return;
                    var gf = (g.dataset.filters || '').split(',').map(function(s) {
                        return s.trim();
                    });
                    g.style.display = (filter === 'all' || gf.indexOf(filter) !== -1) ? '' : 'none';
                });
            });
        });

        /* ── Insurance billing-type toggle ──────────────────────── */
        var insFields = document.getElementById('ins-fields');
        var insReqs = insFields ? insFields.querySelectorAll('.ins-required') : [];
        var insStars = insFields ? insFields.querySelectorAll('.ins-req') : [];

        document.querySelectorAll('[name="bat[ins_comp]"]').forEach(function(r) {
            r.addEventListener('change', function() {
                var show = this.value === 'ins';
                if (insFields) insFields.style.display = show ? '' : 'none';
                insReqs.forEach(function(el) {
                    show ? el.setAttribute('required', '') : el.removeAttribute('required');
                });
                insStars.forEach(function(el) {
                    el.style.display = show ? '' : 'none';
                });
            });
        });

        /* ── Auto-fill insurance "Self" ─────────────────────────── */
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
        var paLast = document.getElementById('pa_last');
        var paFirst = document.getElementById('pa_first');
        var relSelf = document.getElementById('ins_rel_self');
        if (paLast) paLast.addEventListener('change', syncSelf);
        if (paFirst) paFirst.addEventListener('change', syncSelf);
        if (relSelf) relSelf.addEventListener('change', syncSelf);

        /* ── Diagnostic codes chip UI ────────────────────────────── */
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
                chip.innerHTML = code + ' <button type="button" aria-label="Remove">&times;</button>';
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
        if (diagAddBtn) {
            diagAddBtn.addEventListener('click', function() {
                if (diagSelect && diagSelect.value) {
                    addCode(diagSelect.value);
                    diagSelect.value = '';
                }
                if (diagCustom && diagCustom.value) {
                    addCode(diagCustom.value);
                    diagCustom.value = '';
                }
            });
        }
        if (diagSelect) {
            diagSelect.addEventListener('change', function() {
                if (this.value) {
                    addCode(this.value);
                    this.value = '';
                }
            });
        }

        /* ══════════════════════════════════════════════════════════
           VALIDATION
        ══════════════════════════════════════════════════════════ */
        function validate() {
            var ok = true;
            var form = document.getElementById('req-requisition-form');

            // Required fields
            form.querySelectorAll('[required]').forEach(function(el) {
                if (!el.value.trim()) {
                    el.classList.add('error');
                    ok = false;
                } else {
                    el.classList.remove('error');
                }
            });

            // At least one test/group selected
            var anyTest = form.querySelectorAll(
                '.batcustom_profile:checked, .cb_test:not(.batcustom_profile):checked'
            ).length > 0;
            var panelErr = document.getElementById('req-panel-error');
            if (!anyTest) {
                if (panelErr) panelErr.style.display = 'block';
                ok = false;
            } else {
                if (panelErr) panelErr.style.display = 'none';
            }

            // Specimen sources
            form.querySelectorAll('.req-specimens.grequired').forEach(function(sd) {
                if (sd.querySelectorAll('input:checked').length === 0) {
                    sd.classList.add('gerror');
                    ok = false;
                }
            });
            if (form.querySelectorAll('.req-specimens.gerror').length > 0) {
                toast('Please select at least one Specimen Source.', 'err');
            }

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


        function submitForm() {
            if (!validate()) {
                toast('Please fill in all required fields.', 'err');
                var firstErr = document.querySelector('.error, .gerror');
                if (firstErr) firstErr.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                return;
            }

            var btn = document.getElementById('req-submit-btn');
            setLoading(btn, true);

            var form = document.getElementById('req-requisition-form');

            // Uncheck clinic-hidden panels before serialising (mirrors file-1 submit handler)
            form.querySelectorAll('.req-groupparent.notcactive input[type="checkbox"]')
                .forEach(function(cb) {
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

        // Clear error class on input
        document.getElementById('req-requisition-form').addEventListener('input', function(e) {
            e.target.classList.remove('error');
            var sp = e.target.closest('.req-specimens');
            if (sp) sp.classList.remove('gerror');
        });
        /* ── Multi-file upload UI (Result PDF, Paper Req) ─────────── */
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
