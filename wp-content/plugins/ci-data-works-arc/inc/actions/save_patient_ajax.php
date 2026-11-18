<?php
global $wpdb;
$id = $_REQUEST['id'];
$value = $_REQUEST['value'];
$key = $_REQUEST['key'];
$return = array('status'=>'ok');

if($key=='mrn'){
	$key = 'pa_mrn';

}

$wpdb->update('ci_patients',array($key=>$value), array('pa_ID'=>$id));


wp_send_json($return);