<?php
global $wpdb;
$id 	= $_REQUEST['id'];
$btype 	= $_REQUEST['btype'];

$report = tr_pdd_get_report_by_id($id, $btype);



include(trpdd_get_file_path('global', 'report_history'));

if (!isset($files) || count($files) == 0) {
	echo 'Not found';
}

exit;
