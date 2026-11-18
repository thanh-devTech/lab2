<?php
global $wpdb;

$options = get_option('ci_pdf_data', array());
$old_close_lab = !empty($options['close_lab']);
$old_enable_new_abx = !empty($options['new_abx']);

if (isset($_POST)) {
    $options = array_merge($options, $_POST);
}


if (isset($options['insurance_partab']) && $options['insurance_partab']) {
    $wpdb->query("ALTER TABLE `ci_insurances` ADD `ins_part_A` tinyint(1) default 0");
    $wpdb->query("ALTER TABLE `ci_insurances_history` ADD `ins_part_A` tinyint(1) default 0");
    $wpdb->query("ALTER TABLE `ci_insurances` ADD `ins_part_B` tinyint(1) default 0");
    $wpdb->query("ALTER TABLE `ci_insurances_history` ADD `ins_part_B` tinyint(1) default 0");
}

if (isset($options['enable_patient_notes']) && $options['enable_patient_notes']) {
    $wpdb->query("ALTER TABLE `ci_patients` ADD `pa_notes` text;");
    $wpdb->query("ALTER TABLE `ci_patients_history` ADD `pa_notes` text;");
}

if (isset($options['show_patient_sogi']) && $options['show_patient_sogi']) {
    $wpdb->query("ALTER TABLE `ci_patients` ADD `pa_sexual_oriention` text;");
    $wpdb->query("ALTER TABLE `ci_patients_history` ADD `pa_sexual_oriention` text;");
    $wpdb->query("ALTER TABLE `ci_patients` ADD `pa_gender_identity` text;");
    $wpdb->query("ALTER TABLE `ci_patients_history` ADD `pa_gender_identity` text;");
}
$has_patient_history = $wpdb->get_var("SHOW TABLES LIKE 'ci_patients_history'");
$wpdb->query("ALTER TABLE `ci_patients` ADD COLUMN IF NOT EXISTS `pa_unit` text;");
$wpdb->query("ALTER TABLE `ci_patients` ADD COLUMN IF NOT EXISTS `pa_room` text;");
$wpdb->query("ALTER TABLE `ci_patients` ADD COLUMN IF NOT EXISTS `pa_bed` text;");
if ($has_patient_history) {
    $wpdb->query("ALTER TABLE `ci_patients_history` ADD COLUMN IF NOT EXISTS `pa_unit` text;");
    $wpdb->query("ALTER TABLE `ci_patients_history` ADD COLUMN IF NOT EXISTS `pa_room` text;");
    $wpdb->query("ALTER TABLE `ci_patients_history` ADD COLUMN IF NOT EXISTS `pa_bed` text;");
}

if (isset($options['inb_showmf_fa']) && $options['inb_showmf_fa']) {
    $wpdb->query("ALTER TABLE `ci_batches_blood` ADD `blood_fa` tinyint(1) default 0;");
    $wpdb->query("ALTER TABLE `ci_batches_blood` ADD `blood_mf` tinyint(1) default 0;");
}

if (isset($options['plate_coating']) && $options['plate_coating']) {
    $existtbl = $wpdb->get_var("SHOW TABLES LIKE 'ci_plate_coating'");
    if (!$existtbl) {
        trpdd_plugin_activate();
    }
}

$instrument_cols = array();

include(TRCPDD_PATH . 'inc/define_instruments.php');
include(TRCPDD_PATH . 'inc/define_apis.php');

foreach ($define_instruments as $k => $arr) {
    if (isset($arr['add_fields']) && !empty($arr['add_fields'])) {
        $instrument_cols[$k] = $arr['add_fields'];
    }
}

foreach ($define_apis as $k => $arr) {
    if (isset($arr['add_fields']) && !empty($arr['add_fields'])) {
        $instrument_cols[$k] = $arr['add_fields'];
    }
}

foreach ($instrument_cols as $ins => $col) {
    if (isset($options[$ins]) && $options[$ins]) {

        if (!is_array($col)) {
            $col = array($col);
        }
        foreach ($col as $c) {
            if (stripos($c, 'cli_') === 0) {
                $wpdb->query("ALTER TABLE `ci_clinic` ADD {$c} text default '' ");
            } else if (stripos($c, 'tg_') === 0) {
                $wpdb->query("ALTER TABLE `ci_test_groups` ADD {$c} text;");
            } else if (stripos($c, 'u_') === 0) {
                $wpdb->query("ALTER TABLE `ci_urine_validity` ADD {$c} text;");
            } else {
                $wpdb->query("ALTER TABLE `ci_test_panels` ADD {$c} text;");
            }
        }
    }
}

if (isset($options['api_sftp_emr']) && $options['api_sftp_emr']) {
    $wpdb->query("ALTER TABLE `ci_clinic` ADD cli_sftp_emr_code text;");
}

if (isset($options['api_mako']) && $options['api_mako']) {
    $wpdb->query("ALTER TABLE `ci_clinic` ADD cli_mako_id text;");
}
if (isset($options['api_opentext_pcc']) && $options['api_opentext_pcc']) {
    $wpdb->query("ALTER TABLE `ci_clinic` ADD cli_pcc_id text;");
}

//install abx

if ($wpdb->get_var("explain ci_bacteria_wound") == null) {
    //import wound
}

//install cron

wp_clear_scheduled_hook('cron_api_covid_csv_sftp');
if (isset($options['e_export_csvsftp']) && $options['e_export_csvsftp']) {
    $offset = get_option('gmt_offset') * HOUR_IN_SECONDS;
    $time_mid_night = strtotime(date_i18n('Y-m-d 00:02:02'))  - $offset;
    wp_schedule_event($time_mid_night, 'hourly', 'cron_api_covid_csv_sftp');
}

if (isset($options['new_abx']) && $options['new_abx']) {
    $nextcron = wp_next_scheduled('cron_update_abx');
    if ($nextcron == 0) {
        wp_schedule_single_event(time() + 30, 'cron_update_abx');
    }
}
if (!$old_enable_new_abx && isset($_POST['new_abx'])) {
    wp_clear_scheduled_hook('cron_all_api', array('CI_Api_Build_Report_Cache'));
    wp_schedule_event(time() + $cron_next_time, '30min', 'cron_all_api', array('CI_Api_Build_Report_Cache'));
    $cron_next_time += 60;
} else {
    wp_clear_scheduled_hook('cron_all_api', array('CI_Api_Build_Report_Cache'));
}

wp_clear_scheduled_hook('ci_auto_fax_results', array('bytime'));
if (isset($options['fax_time']) && $options['fax_time'] > 0) {
    $offset = get_option('gmt_offset') * HOUR_IN_SECONDS;
    $time = strtotime(date('Y-m-d ' . $options['fax_time'] . ':00:00')) - $offset;
    wp_schedule_event($time, 'daily', 'ci_auto_fax_results', array('bytime'));
}

wp_clear_scheduled_hook('cron_all_api', array('CI_Cron_Auto_Reject'));
if ((isset($options['rejectbydocdates']) && $options['rejectbydocdates'] > 0)
    || isset($options['canceledbydocdates']) && $options['canceledbydocdates'] > 0
) {
    wp_schedule_event(time() + 100, 'hourly', 'cron_all_api', array('CI_Cron_Auto_Reject'));
}

// sync close lab
if (($old_close_lab && !isset($_POST['close_lab'])) || (!$old_close_lab && isset($_POST['close_lab']))) {
    if (!class_exists('CI_Lab_Client_Api')) {
        include_once(TRLCN_PATH . '/api/ci_lab_client.php');
    }
    $client = new CI_Lab_Client_Api(true);
    $rs = $client->sync_close_lab(['lab_closed' => isset($_POST['close_lab'])]);
}
