<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
include (TRCPDD_PATH.'api/ci_api_noah.php');

$options = get_option('tr_noah_st',array());

$dir = dirname(ABSPATH).'/noah/';
$order_dir = $dir.'orders/';
$order_log_dir = $dir.'archive/';
$result_dir = $dir.'results/';

try{
	$client = new CI_Api_Noah($options['lab_id'],$options['cli_id'],$options['doc_id']);

	$client->sync_reqs($order_dir);
	$client->sync_reports($result_dir);


}catch(Exception $e)
{
	Tr_Session_Class::add_error($e->getMessage());
}