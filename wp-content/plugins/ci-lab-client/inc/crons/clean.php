<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb;

if (TRLCN_V != get_option('TRLCN_V')) {
	include_once(TRLCN_PATH . 'inc/install.php');
}

include(TRLCN_PATH . 'inc/functions_clean.php');

$log_file = WP_CONTENT_DIR . '/log/lab_client_clean.txt';

$ftime = filemtime($log_file);
if ($ftime > time() && !isset($_GET['resetkey'])) {
	$log = "Last run was in the future. Skipping execution.\n";
	return;
}


$f_log = fopen($log_file, 'a+');
touch($log_file, time() + 1000);

$st = get_option('ci_lab_client_st', array());

if (isset($st['remove_usermeta']) && $st['remove_usermeta']) {
	$wpdb->query("delete from `wp_usermeta` where  meta_key='syntax_highlighting' limit 100");
	$wpdb->query("delete from `wp_usermeta` where  meta_key='rich_editing' limit 100");
	$wpdb->query("delete from `wp_usermeta` where  meta_key='use_ssl' limit 100");
	$wpdb->query("delete from `wp_usermeta` where  meta_key='comment_shortcuts' limit 100");
	$wpdb->query("delete from `wp_usermeta` where  meta_key='show_admin_bar_front' limit 100");
	$wpdb->query("delete from `wp_usermeta` where  meta_key='locale' limit 100");
	$wpdb->query("delete from `wp_usermeta` where  meta_key='admin_color' limit 100");
	$wpdb->query("delete from `wp_usermeta` where  meta_key='description' limit 100");

	$log .= "Removed usermeta entries.\n";
}

if (!isset($st['repair_pa_phone']) || $st['repair_pa_phone']) {

	$wpdb->query(" update ci_patients 
			set pa_phone = REPLACE(
					REPLACE(
						REPLACE(
							REPLACE(
								REPLACE(pa_phone,'-','')
								,'.','')
							,'(','')
						,')','')
					,' ','')
                    where (pa_phone like '%-%' 
                            or pa_phone like '%\.%'
                            or pa_phone like '%(%'
                            or pa_phone like '% %'
                            ) limit 20000");
	$log .= "Repaired pa_phone in ci_patients.\n";
}



//check errors
//panel error

if (!isset($st['last_check_panel']) || $st['last_check_panel'] > time() - 86400) {
	$count = $wpdb->get_var("select count(*) from `ci_test_groups` g 
	join ci_test_panels t ON t.te_group_id = g.tg_ID
	where tg_type != te_type");

	if ($count > 0) {
		//send_error("");
	}
	//$st['last_check_panel'] = time();

	//update_option('ci_lab_client_st',$st);
}

if (empty(get_option('panel_types', []))) {
	if (!class_exists('CI_Lab_Client_Api')) {
		include_once(TRLCN_PATH . '/api/ci_lab_client.php');
	}
	$client = new CI_Lab_Client_Api(true);
	$client->sync_panel_types();
	$log .= "Synced panel types.\n";
}


//run each 7 days

if (get_option('ci_lab_client_clean_user', 0) < time() - 604800) {
	$wpdb->query("delete from `wp_usermeta` where  user_id not in (select ID from `wp_users`) ");
	update_option('ci_lab_client_clean_user', time(), false);
	$log .= "Cleaned orphan usermeta entries.\n";
}

$removeFiles = array(
	WP_CONTENT_DIR . '/log.html',
	WP_CONTENT_DIR . '/log.txt',
	WP_CONTENT_DIR . '/logimport.txt',
	WP_CONTENT_DIR . '/client_requests.txt',
	WP_CONTENT_DIR . '/export_result.pdf',
	WP_CONTENT_DIR . '/phptradmin.php',
);
foreach ($removeFiles as $file) {
	if (file_exists($file)) {
		unlink($file);
		$log .= "Removed file: {$file}\n";
	}
}

//remove sql files
$removeSqlFiles = glob(WP_CONTENT_DIR . '/*.sql');
foreach ($removeSqlFiles as $file) {
	if (file_exists($file)) {
		unlink($file);
		$log .= "Removed SQL file: {$file}\n";
	}
}

$publicDocFiles = array(
	ABSPATH . 'readme.html',
	ABSPATH . 'readme.txt',
	ABSPATH . 'license.txt',
);
foreach ($publicDocFiles as $file) {
	if (file_exists($file) && is_writable($file) && filesize($file) > 0) {
		file_put_contents($file, '');
		$log .= "Cleared public documentation file: {$file}\n";
	}
}

//remove old log file

trclient_clear_by_dir(WP_CONTENT_DIR . '/log/nginx/', 86400, time());
