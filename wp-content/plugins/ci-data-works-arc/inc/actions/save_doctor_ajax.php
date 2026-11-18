<?php
global $wpdb;
$id = $_REQUEST['id'];
$value = $_REQUEST['value'];
$key = $_REQUEST['key'];
$return = array('status'=>'ok');
$wpdb->update('ci_doc',array($key=>$value), array('doc_ID'=>$id));


wp_send_json($return);