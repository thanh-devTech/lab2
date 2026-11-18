<?php
/**
 * E2E domain seed — infrastructure fixtures (D085 "infrastructure" tier).
 *
 * Run:  wp eval-file seed.php --skip-plugins --skip-themes --url=http://localhost
 *
 * Idempotent: every entity is keyed on a natural unique value and reused if present.
 * Emits one `KEY=value` line per created/found id so seed.sh can eval the output.
 *
 * Domain rows (requisitions/batches) are deliberately NOT created here — D085 says
 * they go through the app's own saver over HTTP. seed.sh does that part.
 */

global $wpdb;

$P = 'E2E';                       // synthetic-data prefix; nothing real starts with this
$today = date('Y-m-d');
$now   = date('Y-m-d H:i:s');

/** get-or-create: returns the primary key. */
function e2e_get_or_create($table, $pk, $where_sql, $data)
{
    global $wpdb;
    $id = $wpdb->get_var("select `$pk` from `$table` where $where_sql limit 1");
    if ($id > 0) {
        return array((int) $id, 'reused');
    }
    $ok = $wpdb->insert($table, $data);
    if ($ok === false) {
        fwrite(STDERR, "INSERT FAILED $table: " . $wpdb->last_error . "\n");
        exit(1);
    }
    return array((int) $wpdb->insert_id, 'created');
}

// NOTE: `wp eval-file` includes this file inside a function, so file-scope
// variables are NOT globals. Report straight to STDERR instead of collecting.
function e2e_emit($key, $pair)
{
    fwrite(STDERR, sprintf("  %-22s %-8s id=%s\n", $key, $pair[1], $pair[0]));
    echo $key . '=' . $pair[0] . "\n";
}

// ---------------------------------------------------------------- 1. lab
// lab_dba_init is the accession prefix consumed by tr_get_new_assen().
$lab = e2e_get_or_create('ci_labs', 'lab_ID', "lab_dba_init='{$P}'", array(
    'lab_name'        => 'E2E Test Lab',
    'lab_dba'         => 'E2E Test Lab',
    'lab_dba_init'    => $P,
    'lab_address'     => '1 Test Way',
    'lab_city'        => 'Testville',
    'lab_state'       => 'IL',
    'lab_zip'         => '00000',
    'lab_phone'       => '000-000-0000',
    'lab_email'       => 'e2e-lab@example.invalid',
    'lab_npi'         => 'E2ENPI0000',
    'lab_tax_id'      => 'E2ETAX0000',
    'lab_clia'        => 'E2ECLIA000',
    'lab_is_ref'      => 0,
    'lab_status'      => 1,
    'lab_start_date'  => $today,
));
e2e_emit('E2E_LAB_ID', $lab);
$lab_id = $lab[0];

// ---------------------------------------------------------------- 2. clinic
$cli = e2e_get_or_create('ci_clinic', 'cID', "cli_clinic='E2E Test Clinic'", array(
    'cli_clinic'   => 'E2E Test Clinic',
    'clinic_name'  => 'E2E Test Clinic',
    'cli_lab-ID'   => $lab_id,
    'cli_street'   => '2 Test Way',
    'cli_city'     => 'Testville',
    'cli_state'    => 'IL',
    'cli_zip'      => '00000',
    'cli_om-phone' => '000-000-0001',
    'cli_om-email' => 'e2e-clinic@example.invalid',
    'cli_sdate'    => $today,
    'cli_status'   => 1,
    'cli_tox'      => 1,
));
e2e_emit('E2E_CLINIC_ID', $cli);
$cli_id = $cli[0];

// ------------------------------------------- 2b. SECOND clinic (D204 gap 3)
// With one clinic every multi-clinic tenancy scenario is vacuous: a persona
// scoped to clinic A cannot be shown NOT to see clinic B when B does not
// exist. Same lab, so the scope under test is the CLINIC and not the lab.
$cli2 = e2e_get_or_create('ci_clinic', 'cID', "cli_clinic='E2E Test Clinic Two'", array(
    'cli_clinic'   => 'E2E Test Clinic Two',
    'clinic_name'  => 'E2E Test Clinic Two',
    'cli_lab-ID'   => $lab_id,
    'cli_street'   => '5 Test Way',
    'cli_city'     => 'Testville',
    'cli_state'    => 'IL',
    'cli_zip'      => '00000',
    'cli_om-phone' => '000-000-0006',
    'cli_om-email' => 'e2e-clinic2@example.invalid',
    'cli_sdate'    => $today,
    'cli_status'   => 1,
    'cli_tox'      => 1,
));
e2e_emit('E2E_CLINIC2_ID', $cli2);
$cli2_id = $cli2[0];

// ------------------------------------------------------- 3. provider/doctor
$doc = e2e_get_or_create('ci_doc', 'doc_ID', "doc_last='Testprovider' and cli_ID={$cli_id}", array(
    'cli_ID'      => $cli_id,
    'doc_first'   => 'Ordering',
    'doc_last'    => 'Testprovider',
    'doc_address' => '2 Test Way',
    'doc_city'    => 'Testville',
    'doc_state'   => 'IL',
    'doc_zip'     => '00000',
    'doc_phone'   => '000-000-0002',
    'doc_email'   => 'e2e-provider@example.invalid',
    'doc_npi'     => 'E2ENPI0002',
    'doc_sdate'   => $today,
    'doc_created' => $now,
));
e2e_emit('E2E_DOC_ID', $doc);
$doc_id = $doc[0];

// A clinic with no ordering provider cannot take a requisition, so the second
// clinic gets its own. The collector is SHARED (linked to both clinics below):
// one collector working two clinics is the realistic shape and adds no row.
$doc2 = e2e_get_or_create('ci_doc', 'doc_ID', "doc_last='Testprovidertwo' and cli_ID={$cli2_id}", array(
    'cli_ID'      => $cli2_id,
    'doc_first'   => 'Ordering',
    'doc_last'    => 'Testprovidertwo',
    'doc_address' => '5 Test Way',
    'doc_city'    => 'Testville',
    'doc_state'   => 'IL',
    'doc_zip'     => '00000',
    'doc_phone'   => '000-000-0007',
    'doc_email'   => 'e2e-provider2@example.invalid',
    'doc_npi'     => 'E2ENPI0007',
    'doc_sdate'   => $today,
    'doc_created' => $now,
));
e2e_emit('E2E_DOC2_ID', $doc2);

// ---------------------------------------------------------- 4. collector
$col = e2e_get_or_create('ci_collector', 'col_ID', "col_last='Testcollector'", array(
    'doc_ID'    => $doc_id,
    'lab_ID'    => $lab_id,
    'col_first' => 'Sample',
    'col_last'  => 'Testcollector',
    'col_street' => '2 Test Way',
    'col_city'  => 'Testville',
    'col_state' => 'IL',
    'col_zip'   => '00000',
    'col_phone' => '000-000-0003',
    'col_email' => 'e2e-collector@example.invalid',
    'col_sdate' => $today,
));
e2e_emit('E2E_COLLECTOR_ID', $col);
$col_id = $col[0];
$wpdb->query("insert ignore into ci_clinic_collector (col_ID, cli_ID) values ({$col_id}, {$cli_id})");
$wpdb->query("insert ignore into ci_clinic_collector (col_ID, cli_ID) values ({$col_id}, {$cli2_id})");

// ------------------------------------------- 5. orderable panel (S-ORD-01)
// Preconditions from 10-scenarios.md: tg_show_req=1 and te_in_req=1.
$tg = e2e_get_or_create('ci_test_groups', 'tg_ID', "tg_name='E2E Orderable Panel'", array(
    'tg_name'       => 'E2E Orderable Panel',
    'tg_short_name' => 'E2EPNL',
    'tg_label_name' => 'E2E',
    'tg_show_req'   => 1,
    'tg_type'       => 'tox',
    'tg_position'   => 1,
    'tg_panel_type' => '',
));
e2e_emit('E2E_TESTGROUP_ID', $tg);
$tg_id = $tg[0];

$te = e2e_get_or_create('ci_test_panels', 'te_ID', "te_name='E2E Analyte One'", array(
    'te_name'     => 'E2E Analyte One',
    'te_abrev'    => 'E2E1',
    'te_group_id' => $tg_id,
    'te_type'     => 'tox',
    'te_in_req'   => 1,
    'te_basic'    => 1,
    'te_standard' => 1,
    'te_comprehensive' => 1,
    'te_cutoff'   => '10',
    'te_upper_limit' => '1000',
    'te_highrisk' => 0,
    'te_start'    => $today,
    'te_position' => 1,
));
e2e_emit('E2E_TESTPANEL_ID', $te);

// ----------------------------------------------------------- 6. patient
$pa = e2e_get_or_create('ci_patients', 'pa_ID', "pa_first='Testpatient' and pa_last='Alpha'", array(
    'pa_first'      => 'Testpatient',
    'pa_last'       => 'Alpha',
    'pa_dob'        => '1900-01-01',
    'pa_gender'     => 'F',
    'pa_phone'      => '0000000004',
    'pa_address'    => '3 Test Way',
    'pa_city'       => 'Testville',
    'pa_state'      => 'IL',
    'pa_zip'        => '00000',
    'pa_email'      => 'e2e-patient@example.invalid',
    'pa_clinic_ID'  => $cli_id,
    'pa_code'       => 'E2EPA0001',
    'pa_created'    => $now,
));
e2e_emit('E2E_PATIENT_ID', $pa);
$pa_id = $pa[0];

// ------------------------------------ 6b. synthetic lab rep (R13/D-refA-R13)
// CI_Base_Lab::get_user_lab_id() falls back through _rep_id/_collector_id
// (inc/ci_base_lab.php:71-95) for the lab_rep and collector personas. Those
// branches JOIN ci_lab_reps, which sql/30-strip-pii.sql has just truncated, so
// without this row they resolve to nothing. The strip runs BEFORE this file --
// reversing that order deletes the row again.
$rep = e2e_get_or_create('ci_lab_reps', 'rep_ID', "rep_last='Testrep'", array(
    'lab_ID' => $lab_id, 'rep_first' => 'Sales', 'rep_last' => 'Testrep', 'rep_middle' => '',
    'rep_address' => '4 Test Way', 'rep_city' => 'Testville', 'rep_state' => 'IL', 'rep_zip' => '00000',
    'rep_phone' => '000-000-0005', 'rep_fax' => '', 'rep_cell' => '', 'rep_website' => '',
    'rep_company_type' => '', 'rep_state_inc' => '', 'rep_state_inc_ein' => '',
    'rep_contact_name' => 'Sales Testrep', 'rep_contact_phone' => '000-000-0005', 'rep_contact_fax' => '',
    'rep_email' => 'e2e-rep@example.invalid', 'rep_sdate' => $today, 'rep_edate' => '',
    'rep_princial' => '', 'rep_payment_type' => '', 'rep_company_type_other' => '',
    'rep_distributor_ID' => 0, 'rep_marketing_group_ID' => 0,
));
e2e_emit('E2E_REP_ID', $rep);
$wpdb->update('ci_collector', array('rep_ID' => $rep[0]), array('col_ID' => $col_id));

// ------------------------------------------------- 7. staff + portal users
// D032: BOTH _lab_id and _lab_ids, else the user sees every active non-ref lab
// and every tenancy assertion is vacuous.
$users = array(
    'e2e_sec'     => array('role' => 'lab_sec',       'name' => 'E2E Accessioner',  'blog' => 1),
    'e2e_tech'    => array('role' => 'lab_tech',      'name' => 'E2E Technologist', 'blog' => 1),
    'e2e_dir'     => array('role' => 'administrator', 'name' => 'E2E Director',     'blog' => 1),
    'e2e_patient' => array('role' => 'subscriber',    'name' => 'Testpatient Alpha','blog' => 2),
);
$pw = getenv('E2E_SEED_PASSWORD') ?: 'e2e-seed-pw';

function e2e_upsert_user($login, $name, $role, $blog, $pw)
{
    $uid = username_exists($login);
    $state = 'reused';
    if (!$uid) {
        $uid = wp_insert_user(array(
            'user_login'   => $login,
            'user_pass'    => $pw,
            'user_email'   => $login . '@example.invalid',
            'display_name' => $name,
            'role'         => '',
        ));
        if (is_wp_error($uid)) {
            fwrite(STDERR, "wp_insert_user $login: " . $uid->get_error_message() . "\n");
            exit(1);
        }
        $state = 'created';
    }
    add_user_to_blog($blog, $uid, $role);
    return array((int) $uid, $state);
}

foreach ($users as $login => $u) {
    $pair = e2e_upsert_user($login, $u['name'], $u['role'], $u['blog'], $pw);
    update_user_meta($pair[0], '_lab_id', $lab_id);
    update_user_meta($pair[0], '_lab_ids', (string) $lab_id);
    update_user_meta($pair[0], '_clinic_id', $cli_id);
    e2e_emit('E2E_USER_' . strtoupper(str_replace('e2e_', '', $login)), $pair);
}

// ------------------------------------- 8. tenancy pool personas (design §3.4)
// ONE persona per pool lab, not one shared persona re-pointed per test. The
// tenant binding is user meta, so two workers sharing a user would race
// _lab_id and the tenancy assertion would be reading the other worker's lab.
// Binding it in the seed also means it is inside baseline.sql: it survives
// every reset.sh, and no test ever has to write user meta at runtime.
//
// Role `leadlabsecman`, NOT `lab_sec` (R5/D162): verified against the RUNNING
// instance, the installed lab_sec role has 29 caps and form_requisition is not
// among them, so it 403s on admin.php?page=tox_req_form. install_roles.php:344
// does grant it in source, but Tr_Base_Class_V4::create_role does not rewrite an
// existing role, so the stale default.sql definition is what is in force.
$pool = $wpdb->get_results(
    "select lab_ID, lab_dba_init from ci_labs where lab_dba_init regexp '^LA[A-P]$' order by lab_dba_init",
    ARRAY_A
);
if (count($pool) < 8) {
    fwrite(STDERR, "FAIL: lab pool has " . count($pool) . " labs, expected 8 (sql/25-lab-pool.sql)\n");
    exit(1);
}
$pool_out = array();
foreach ($pool as $i => $p) {
    $login = 'e2e_lead' . $i;
    $pair  = e2e_upsert_user($login, 'E2E Lead Sec ' . $p['lab_dba_init'], 'leadlabsecman', 1, $pw);
    update_user_meta($pair[0], '_lab_id', $p['lab_ID']);
    update_user_meta($pair[0], '_lab_ids', (string) $p['lab_ID']);
    update_user_meta($pair[0], '_clinic_id', $cli_id);
    if (!user_can($pair[0], 'form_requisition')) {
        fwrite(STDERR, "FAIL: {$login} lacks form_requisition -- the S-ORD-01 persona cannot reach tox_req_form\n");
        exit(1);
    }
    // WAVE 0.2 -- ONE CLINIC PER POOL LAB.
    //
    // Both clinics above carry cli_lab-ID = the lab-35 E2E lab, and
    // parts/tox/req_form.php:349 filters the clinic dropdown by the persona's
    // own labs (`cli.cli_lab-ID in (<user labs>)`) while `bat[cli_ID]` is
    // `required`. Measured before this block: 0 clinic options for e2e_lead0,
    // so NO pool persona could submit a requisition through the UI at all.
    //
    // The two link tables are not decoration. The Ordering Provider dropdown
    // is `ci_doc JOIN ci_clinic_doc` (:382) and the Collector dropdown is
    // `ci_collector JOIN ci_clinic_collector` (:394); a clinic without both
    // rows renders an empty provider or collector list and the form still
    // cannot be completed. The COLLECTOR is the shared one -- one collector
    // working every clinic is realistic and adds no row.
    $init  = $p['lab_dba_init'];
    $pcli  = e2e_get_or_create('ci_clinic', 'cID', "cli_clinic='E2E Clinic {$init}'", array(
        'cli_clinic'   => "E2E Clinic {$init}",
        'clinic_name'  => "E2E Clinic {$init}",
        'cli_lab-ID'   => $p['lab_ID'],
        'cli_street'   => '6 Test Way',
        'cli_city'     => 'Testville',
        'cli_state'    => 'IL',
        'cli_zip'      => '00000',
        'cli_om-phone' => '000-000-0008',
        'cli_om-email' => 'e2e-clinic-' . strtolower($init) . '@example.invalid',
        'cli_sdate'    => $today,
        'cli_status'   => 1,
        'cli_tox'      => 1,
    ));
    $pdoc = e2e_get_or_create('ci_doc', 'doc_ID', "doc_last='Testprovider{$init}'", array(
        'cli_ID'      => $pcli[0],
        'doc_first'   => 'Ordering',
        'doc_last'    => "Testprovider{$init}",
        'doc_address' => '6 Test Way',
        'doc_city'    => 'Testville',
        'doc_state'   => 'IL',
        'doc_zip'     => '00000',
        'doc_phone'   => '000-000-0008',
        'doc_email'   => 'e2e-provider-' . strtolower($init) . '@example.invalid',
        'doc_npi'     => 'E2ENPI' . $init,
        'doc_sdate'   => $today,
        'doc_created' => $now,
    ));
    $wpdb->query("insert ignore into ci_clinic_doc (doc_ID, cli_ID) values ({$pdoc[0]}, {$pcli[0]})");
    $wpdb->query("insert ignore into ci_clinic_collector (col_ID, cli_ID) values ({$col_id}, {$pcli[0]})");

    fwrite(STDERR, sprintf("  %-22s %-8s id=%s lab=%s(%s) clinic=%s doc=%s\n",
        $login, $pair[1], $pair[0], $init, $p['lab_ID'], $pcli[0], $pdoc[0]));
    $pool_out[] = $login . ':' . $p['lab_ID'] . ':' . $p['lab_dba_init'];
}
echo "E2E_LAB_POOL=" . implode(',', $pool_out) . "\n";

// ------------------------------- 8b. secondary role personas (D204 gap 2)
// Every role slug below EXISTS live and was read out of the RUNNING instance
// with get_role(), never from install_roles.php -- D178: create_role does not
// rewrite an existing role, so source and live disagree and LIVE wins.
//
// `requires` is the capability that makes the persona worth having. It is
// asserted with user_can() AFTER the role is attached, so a role that is
// silently missing its capability stops the seed here instead of producing a
// green test that proves nothing.
//
// BillbyClinic's required capability is the string "0". That is not a typo in
// this file. The live role literally carries a capability whose KEY is "0" and
// whose VALUE is the string "billing_admin" -- a numeric-key collapse in the
// role definition, verified live. The practical consequence is that the role
// grants the meaningless cap "0" and does NOT grant billing_admin. It is app
// state, it is what distinguishes the role, and it is deliberately NOT "fixed"
// here.
//
// The check below is a LITERAL key lookup in $user->allcaps, not user_can().
// user_can($uid, "0") reroutes a numeric capability through WordPress's
// deprecated user-LEVEL handling and answers true off `level_0`, so it would
// have passed without the "0" capability being present at all -- a vacuous
// check of exactly the kind D141 is about.
$secondary = array(
    'e2e_billing'   => array('role' => 'Billing',            'requires' => 'billing_admin',
                             'name' => 'E2E Biller'),
    'e2e_billclin'  => array('role' => 'BillbyClinic',       'requires' => '0',
                             'name' => 'E2E Biller By Clinic'),
    'e2e_doctor'    => array('role' => 'doctor',             'requires' => 'ci_patient_chart',
                             'name' => 'E2E Ordering Doctor'),
    'e2e_collector' => array('role' => 'collector',          'requires' => 'ci_phlebotomist',
                             'name' => 'E2E Specimen Collector'),
    'e2e_officemgr' => array('role' => 'office_manager',     'requires' => 'ci_data_forms',
                             'name' => 'E2E Office Manager'),
    'e2e_multisite' => array('role' => 'multi_site_manager', 'requires' => 'clinic_statistics',
                             'name' => 'E2E Multi Site Manager'),
    'e2e_pcrverify' => array('role' => 'pcr_verify',         'requires' => 'ci_resutls_pcr_verification',
                             'name' => 'E2E PCR Verifier'),
    'e2e_scientist' => array('role' => 'scientist',          'requires' => 'ci_results',
                             'name' => 'E2E Scientist'),
);
foreach ($secondary as $login => $u) {
    if (!get_role($u['role'])) {
        fwrite(STDERR, "FAIL: role {$u['role']} does not exist on the running instance\n");
        exit(1);
    }
    $pair = e2e_upsert_user($login, $u['name'], $u['role'], 1, $pw);
    update_user_meta($pair[0], '_lab_id', $lab_id);
    update_user_meta($pair[0], '_lab_ids', (string) $lab_id);
    update_user_meta($pair[0], '_clinic_id', $cli_id);
    // D178, enforced: LIVE capability, read back after the grant, by literal key.
    $allcaps = (new WP_User($pair[0]))->allcaps;
    if (empty($allcaps[$u['requires']])) {
        fwrite(STDERR, "FAIL: {$login} (role {$u['role']}) lacks '{$u['requires']}' on the "
            . "running instance -- the persona is useless without it\n");
        exit(1);
    }
    fwrite(STDERR, sprintf("  %-22s %-8s id=%s role=%s cap=%s ok\n",
        $login, $pair[1], $pair[0], $u['role'], $u['requires']));
    e2e_emit('E2E_USER_' . strtoupper(str_replace('e2e_', '', $login)), $pair);
}

// portal user <-> patient link lives on wp_users.patient_id (non-core column)
$portal_uid = username_exists('e2e_patient');
$wpdb->update($wpdb->users, array('patient_id' => $pa_id), array('ID' => $portal_uid));
echo "E2E_PORTAL_PATIENT_LINK=" . $wpdb->get_var("select patient_id from {$wpdb->users} where ID={$portal_uid}") . "\n";
