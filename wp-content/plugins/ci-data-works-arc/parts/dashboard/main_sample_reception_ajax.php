<?php
global $wpdb;
$func   =$_REQUEST['func'] ? $_REQUEST['func'] : 'get_tests_by_sample';

if($func=='get_tests_by_sample'){
    $panel_groups = $wpdb->get_results("
        select tg_ID, tg_name 
        from ci_test_groups 
        where tg_parent = 0 and tg_show_req = 1 and tg_type = '{$btype}'
        order by tg_position asc
    ");
    echo json_encode($panel_groups);
}


if ($func == 'register_sample') {
    global $wpdb;

    $patient_id     = intval($_REQUEST['patient_id']);
    $priority_level = intval($_REQUEST['priority_level']);
    $sample_type    = sanitize_text_field($_REQUEST['sample_type']);
    $test_type      = sanitize_text_field($_REQUEST['test_type']);
    $collection_date= sanitize_text_field($_REQUEST['collection_date']);
    $collection_time= sanitize_text_field($_REQUEST['collection_time']);

    $table_batches = tr_pdd_get_table_by_btype($test_type );

    $doc_datetime   = date("Y-m-d H:i:s", strtotime($collection_date . " " . $collection_time));
    $now_datetime   = current_time('mysql');

    $patient = $wpdb->get_row(
    $wpdb->prepare("
        SELECT pa_first, pa_last
        FROM ci_patients
        WHERE pa_ID = %d
    ", $patient_id));

    $insert_data = array(
        'batch_id'        => rand(1000, 9999),
        'batch_no'        => 1,
        'patient_id'      => $patient_id,
        'patient_name'    => $patient->pa_first.','.$patient->pa_last,
        'priority_hours'  => $priority_level,
        'type_of_specimen'=> $sample_type,
        'panel_tests_requested' => $test_type,
        'doc'             => $doc_datetime,
        'batch_created'   => $now_datetime,
        'batch_modified'  => $now_datetime,
        'batch_status'    => 'processing',
    );

    $format = array('%d','%d','%d','%s','%d','%s','%s','%s','%s','%s');

    $inserted = $wpdb->insert($table_batches, $insert_data, $format);

    if ($inserted !== false) {
        $response = array(
            'status'  => 'success',
            'message' => 'Sample registered successfully',
            'data'    => $insert_data
        );
    } else {
        $response = array(
            'status'  => 'error',
            'message' => 'Database insert failed',
            'sql'     => $wpdb->last_query,
            'error'   => $wpdb->last_error
        );
    }

    echo json_encode($response);
    wp_die();
}

?>