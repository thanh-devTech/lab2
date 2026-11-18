<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}


function trpdd_get_unsent_state_reports($hours_threshold = 1)
{
    global $wpdb;

    $results = array();

    $options = get_option('ci_send_hub_tests', array());
    if (empty($options)) {
        return $results;
    }

    $configured_state_codes = array();
    foreach ($options as $opt) {
        $state_code = strtoupper(trim((string) ($opt['state'] ?? '')));
        if ($state_code !== '') {
            $configured_state_codes[$state_code] = $state_code;
        }
    }
    if (empty($configured_state_codes)) {
        return $results;
    }

    $option_tids = array();
    foreach ($options as $opt) {
        $tid = isset($opt['tid']) ? (int) $opt['tid'] : 0;
        if ($tid > 0) {
            $option_tids[$tid] = $tid;
        }
    }
    if (empty($option_tids)) {
        return $results;
    }

    $find_in_set_conditions = array();
    foreach ($option_tids as $tid) {
        $find_in_set_conditions[] = "find_in_set('{$tid}', ba.panel_tests_requested)";
    }

    $rejected_status = function_exists('tr_pdd_where_batch_rejected')
        ? tr_pdd_where_batch_rejected(true, false, 'pcr')
        : array();

    $state_placeholders = implode(',', array_fill(0, count($configured_state_codes), '%s'));

    $sql = $wpdb->prepare(
        "select
			ba.assen,
			ba.patient_name,
			ba.batch_status,
			re.id as re_id,
			re.approve_date,
			pa.pa_state
		from ci_batches_pcr ba
		join ci_reports_pcr re on re.patient_id = ba.assen
		left join ci_patients pa on ba.patient_id = pa.pa_ID
		where re.estatus = 'C'
			and pa.pa_state is not null and trim(pa.pa_state) <> ''
			and upper(pa.pa_state) in ({$state_placeholders})
			and re.approve_date <= %s
			and (" . implode(' OR ', $find_in_set_conditions) . ")
		order by re.approve_date asc",
        array_merge(
            array_values($configured_state_codes),
            array(date_i18n('Y-m-d H:i:s', strtotime("-{$hours_threshold} hours")))
        )
    );

    $rows = $wpdb->get_results($sql);
    if (empty($rows)) {
        return $results;
    }

    foreach ($rows as $row) {
        if (!empty($rejected_status) && isset($rejected_status[$row->batch_status])) {
            continue;
        }
        $results[] = $row;
    }

    if (empty($results)) {
        return $results;
    }

    $assens = array_values(array_unique(array_map(function ($r) {
        return $r->assen;
    }, $results)));

    $sent_assens = array();

    $has_hub_statistics = (bool) $wpdb->get_var("SHOW TABLES LIKE 'ci_hub_statistics'");
    if ($has_hub_statistics && !empty($assens)) {
        $holders = implode(',', array_fill(0, count($assens), '%s'));
        $sent_rows = $wpdb->get_col($wpdb->prepare(
            "select distinct assen from ci_hub_statistics where assen in ({$holders})",
            $assens
        ));
        foreach ($sent_rows as $a) {
            $sent_assens[$a] = true;
        }
    }

    $unsent = array();
    foreach ($results as $row) {
        if (!isset($sent_assens[$row->assen])) {
            $unsent[] = $row;
        }
    }

    return $unsent;
}

$unsent = trpdd_get_unsent_state_reports(1);
$count = count($unsent);
if ($count === 0) {
    return 0;
}
if (!class_exists('CI_Lab_Client_Api')) {
    include_once(TRLCN_PATH . '/api/ci_lab_client.php');
}
try {
    $client = new CI_Lab_Client_Api(true);
    $client->send_unsent_state_reports($unsent);
} catch (\Throwable $th) {
    return 0;
}
