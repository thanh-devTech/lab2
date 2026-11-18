<?php
$return     = array('status' => 'ok');
$re_ID         = $_REQUEST['re_ID'];
$rbids         = isset($_REQUEST['rbids']) ? $_REQUEST['rbids'] : array();

foreach ($rbids as $rbid) {
	if (empty($rbid)) continue;

	$wpdb->delete('ci_rack_batches', array('re_ID' => $re_ID, 'ID' => $rbid));

	$return['idrb'][] = $rbid;
	if ($allow_rack4s && $rbid > 0) {
		$wpdb->delete('ci_rack_batches', array('re_ID' => $re_ID, 'iparent' => $rbid));
	}

	$return['error'] = $wpdb->last_error;
}

$data         = trrack_display_samples($re_ID);

$return['batches'] = $data['html'];

if (tr_get_option('ci_pdf_data', 'rack_plate_sync')) {
	$plate_exist = $wpdb->get_var("select pl_ID from ci_plates where pl_rack_ID = {$re_ID}");
	if ($plate_exist) {
		//$return['sync_plate'] = true;
	}
}

wp_send_json($return);
