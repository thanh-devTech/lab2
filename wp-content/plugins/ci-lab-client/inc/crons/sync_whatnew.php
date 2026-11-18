<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
$st = get_option('ci_lab_client_st', array());
if ($st['dbv'] != TRLCN_V) {
	include_once(TRLCN_PATH . 'inc/install.php');
	$st['dbv'] = TRLCN_V;
	update_option('ci_lab_client_st', $st, false);
}


include_once(TRLCN_PATH . '/api/ci_lab_client.php');
$client = new CI_Lab_Client_Api(true);
if (!isset($st['key']) || empty($st['key']) || $st['keytime'] < time() - 86400 * 7 || isset($_GET['resetkey'])) {
	$rs = $client->get_key();
	echo 'request key : ';
	var_dump($rs);
	if (!$rs) {
		return;
	}
}

$client->sync_post_type('whatnew');
$client->sync_post_type('guides');
$client->sync_post_type('videos');
$client->sync_post_type('faqs');
