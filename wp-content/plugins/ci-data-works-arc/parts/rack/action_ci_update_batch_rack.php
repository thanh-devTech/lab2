<?php

$return = array('status' => 'ok', 'message' => '');
$data   = $_POST['data'];
$rbid   = $data['rbid'];
$updateby = $_POST['updateby'];
$rerun  = isset($_POST['rerun']) && $_POST['rerun'];
$subtype  = $_POST['subtype'];
$reason_rerun  = $_POST['reason_rerun'];
$not_rerun = false;
unset($data['rbid']);

$batchr = $wpdb->get_row("select * from ci_rack_batches where ID = {$rbid} ");

$return['updateby'] = $updateby;

if ($updateby == 'vp_type' && !$rerun) {
	//check rerun
	$reid = $batchr->re_ID;
	$mid = $batchr->mid;
	$test = $data[$updateby];
	$count = $wpdb->get_var("select count(*) from ci_rack_batches 
        where re_ID = {$reid} and subtype='{$subtype}' 
        and other_data like '%vp_type\":\"{$test}\"%' 
        and ID != {$rbid} and mid = {$mid}");
	$return['count'] = $count;
	if ($count > 0) {
		$return['status'] = 'rerun';

		$return['message'] = 'This sample is a ReRun - are you sure you want to rack it?';
		$return['ask_reason_rerun'] = true;

		$return['list_reason']         = array(
			'High Variability',
			'Out of Range, High VCAM-1',
			'Out of Range, Low VCAM-1',
			'Out of Range, High P-selectin',
			'Channel failed >2'
		);

		wp_send_json($return);
	} else {
		$not_rerun = true;
	}
}

$olddata = $batchr->other_data;
$olddata = !empty($olddata) ? json_decode($olddata, true) : array();
foreach ($data as $k => $vl) {
	$olddata[$k] = $vl;
}

$update =  array('other_data' => json_encode($olddata));
if ($rerun) {
	$update['is_rerun'] = 1;
	$update['reason_rerun'] = $reason_rerun;
	$return['reason_rerun'] = $reason_rerun;
	$return['addrerun'] = true;
} else if ($not_rerun) {
	$update['is_rerun'] = 0;
	$update['reason_rerun'] = '';
	$return['removererun'] = true;
}

$wpdb->update('ci_rack_batches', $update, array('ID' => $rbid));

wp_send_json($return);
