<?php

$return = array('status' => 'ok');
$re_ID = $_REQUEST['re_ID'];

$wpdb->delete('ci_racks', array('re_ID' => $re_ID));
$wpdb->delete('ci_rack_batches', array('re_ID' => $re_ID));

if (tr_get_option('ci_pdf_data', 'rack_plate_sync')) {
	//delete plate
	$plate_ID = $wpdb->get_var("select pl_ID from ci_plates where pl_rack_ID = {$re_ID} ");
	if ($plate_ID > 0) {
		$wpdb->delete('ci_plates', array('pl_ID' => $plate_ID));
		$wpdb->delete('ci_plates_batches', array('pl_ID' => $plate_ID));
	}
}
wp_send_json($return);
