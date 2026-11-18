<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
include (TRCPDD_PATH.'api/ci_api_meenta.php');
$options = get_option('tr_meenta_st',array());

try{
	$client = new CI_Api_Meenta($options['lab_id'],$options['cli_id'],$options['root_folder']);

	$client->connect();
	$client->login($options['username'], $options['password']);
	echo 'logged in<br>';
	$client->sync_reqs();
	$client->sync_reports();


}catch(Exception $e)
{
	Tr_Session_Class::add_error($e->getMessage());
}