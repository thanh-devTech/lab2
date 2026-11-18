<?php
global $wpdb, $table_prefix;
$wpdb->suppress_errors(true);

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

$sql = "CREATE TABLE `ci_estore_products` (
	`ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	`title` varchar(255) NOT NULL,
	`sku` varchar(150) NOT NULL DEFAULT '',
	`image` varchar(500) NOT NULL DEFAULT '',
	`category` varchar(150) NOT NULL DEFAULT '',
	`max_qty` int(10) unsigned NOT NULL DEFAULT 1,
	`display_order` int(10) unsigned NOT NULL DEFAULT 0,
	`description` text,
	`created` datetime NOT NULL,
	`updated` datetime NOT NULL,
	PRIMARY KEY (`ID`),
	KEY `category` (`category`),
	KEY `display_order` (`display_order`)
) ENGINE=InnoDB";
dbDelta($sql);

$sql = "CREATE TABLE `ci_estore_orders` (
	`ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	`clinic_id` int(11) unsigned NOT NULL DEFAULT 0,
	`user_id` bigint(20) unsigned NOT NULL DEFAULT 0,
	`status` varchar(20) NOT NULL DEFAULT 'Pending',
	`order_notes` text,
	`lab_notes` text,
	`tracking` varchar(150) NOT NULL DEFAULT '',
	`ship_date` date DEFAULT NULL,
	`created` datetime NOT NULL,
	`updated` datetime NOT NULL,
	PRIMARY KEY (`ID`),
	KEY `clinic_id` (`clinic_id`),
	KEY `status` (`status`),
	KEY `created` (`created`)
) ENGINE=InnoDB";
dbDelta($sql);

$sql = "CREATE TABLE `ci_estore_order_items` (
	`ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	`order_id` bigint(20) unsigned NOT NULL,
	`product_id` bigint(20) unsigned NOT NULL DEFAULT 0,
	`title` varchar(255) NOT NULL,
	`sku` varchar(150) NOT NULL DEFAULT '',
	`category` varchar(150) NOT NULL DEFAULT '',
	`ordered_qty` int(10) unsigned NOT NULL DEFAULT 0,
	`fulfilled_qty` int(10) unsigned NOT NULL DEFAULT 0,
	PRIMARY KEY (`ID`),
	KEY `order_id` (`order_id`)
) ENGINE=InnoDB";
dbDelta($sql);


$sql = "CREATE TABLE `ci_hub_statistics` (
  `assen` varchar(12),
  `date_report` datetime,  
  UNIQUE KEY (`assen`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);



$sql = "CREATE TABLE `ci_welcome_messages` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `text` text,
  `istatus` tinyint(1) default 0,
  `created` datetime,
  `created_by` int(11),
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB";
dbDelta($sql);


//client abx
/*
$sql = "CREATE TABLE `ci_abx_assay` (
	`ID` int(11) NOT NULL AUTO_INCREMENT,
	`assay` varchar(150),
	`marker_IDs` varchar(150),
	`type` varchar(5),
	`created` datetime,
	`created_by` int(11) NOT NULL,
	 PRIMARY KEY (`ID`)
   ) ENGINE=InnoDB";
$rs = dbDelta($sql);
*/

$wpdb->query('drop table ci_abx_assay');


$sql = "CREATE TABLE `ci_abx_clinic_local_resistance` (
	`ID` int(11) NOT NULL AUTO_INCREMENT,
	`cli_ID` int(11),
	`bacteria_ID` int(11),
	`antibiotic_ID` int(11),
	`score` varchar(10),
	PRIMARY KEY (`ID`)
  ) ENGINE=InnoDB";
$rs = dbDelta($sql);


$wpdb->query("alter table ci_test_groups add column tg_unique_id varchar(50)");

if (function_exists('tr_pdd_get_batch_types')) {
	foreach (tr_pdd_get_batch_types() as $btype) {
		$report_tbl = tr_pdd_get_table_report_by_btype($btype);
		$wpdb->query("alter table {$report_tbl} add column sent_to_hub tinyint(1) default 0");
	}
}


$offset = get_option('gmt_offset') * HOUR_IN_SECONDS;
$time_mid_night = strtotime(date_i18n('Y-m-d'))  -  $offset;

wp_clear_scheduled_hook('cron_lab_client_first');
wp_clear_scheduled_hook('cron_lab_client');

if (!wp_next_scheduled('cron_lab_client')) {
	wp_schedule_event(time() + 400, 'hourly', 'cron_lab_client');
}

$local_now = current_time('timestamp');
$mid_month_local = strtotime(date_i18n('Y-m-16 00:30:00', $local_now));
if ($mid_month_local <= $local_now) {
	$next_month = strtotime(date_i18n('Y-m-01', $local_now) . ' +1 month');
	$mid_month_local = strtotime(date_i18n('Y-m-16 00:30:00', $next_month));
}
$end_month_local = strtotime(date_i18n('Y-m-01 00:45:00', $local_now) . ' +1 month');
if ($end_month_local <= $local_now) {
	$next_end_month = strtotime(date_i18n('Y-m-01', $local_now) . ' +2 months');
	$end_month_local = strtotime(date_i18n('Y-m-01 00:45:00', $next_end_month));
}
wp_clear_scheduled_hook('cron_lab_client_mid_month_statistics');
wp_clear_scheduled_hook('cron_lab_client_end_month_statistics');
wp_schedule_single_event($mid_month_local - $offset, 'cron_lab_client_mid_month_statistics');
wp_schedule_single_event($end_month_local - $offset, 'cron_lab_client_end_month_statistics');

wp_clear_scheduled_hook('cron_lab_client_clean');
wp_schedule_event($time_mid_night + 3900, 'daily', 'cron_lab_client_clean');

if (!wp_next_scheduled('cron_check_unsent_state_report')) {
	wp_schedule_event(time() + 60, 'hourly', 'cron_check_unsent_state_report');
}

//remove wp cron
wp_clear_scheduled_hook('wp_update_plugins');
wp_clear_scheduled_hook('wp_update_themes');
wp_clear_scheduled_hook('wp_version_check');
wp_clear_scheduled_hook('wp_scheduled_delete');
wp_clear_scheduled_hook('wp_scheduled_auto_draft_delete');
wp_clear_scheduled_hook('wp_privacy_delete_old_export_files');

//check redirect in blankforlab theme
$filefooter = WP_CONTENT_DIR . '/themes/blankforlab/footer.php';
if (file_exists($filefooter)) {
	$contentfooter = file_get_contents($filefooter);
	$contentfooter = str_replace('<meta http-equiv="refresh" content="0;url=/" />', '', $contentfooter);
	file_put_contents($filefooter, $contentfooter);
}


if (!file_exists(WP_CONTENT_DIR . '/log/nginx')) {
	wp_mkdir_p(WP_CONTENT_DIR . '/log/nginx');
}





//create roles
if (!function_exists('tr_pdd_lis_caps')) {
	include_once(TRCPDD_PATH . 'inc/form_define.php');
}
if (!function_exists('get_editable_roles')) {
	include_once(ABSPATH . 'wp-admin/includes/user.php');
}



$all_lis_caps = array();
$all_lis_caps['read'] = true;
foreach (tr_pdd_lis_caps() as $cap => $arg) {
	$all_lis_caps[$cap] = true;
}


Tr_Base_Class_V4::create_role('ci_dev', 'Dev', array(
	'read' => true, // true allows this capability
	'edit_users' => true,
	'edit_user' => true,
	'create_users' => true,
	'do_not_allow' => true,
	'promote_users' => true,
	'list_users' => true,
	'ci_add_notices' => true,
) + $all_lis_caps);

Tr_Base_Class_V4::create_role('ci_support', 'Support', array(
	'read' => true, // true allows this capability
	'edit_users' => true,
	'edit_user' => true,
	'create_users' => true,
	'do_not_allow' => true,
	'promote_users' => true,
	'list_users' => true,
	'ci_add_notices' => true,
) + $all_lis_caps);


//fix barcode_key
if (class_exists('CI_Base_Lab')) {
	$main_labkey = CI_Base_Lab::get_current_lab_init();
	$settings = get_option('ci_pdf_data', array());
	if (in_array($settings['barcode_key'], array('ILS', 'LIS')) && $main_labkey != $settings['barcode_key']) {
		$settings['barcode_key'] = $main_labkey;
		update_option('ci_pdf_data', $settings);
	}
}





update_option('TRLCN_V', TRLCN_V, false);

$log = '';
if (version_compare(TRLCN_V, '1.902') < 1) {
	$log .= 'deleteplugins';
	try {
		// Ensure WordPress environment is loaded
		require_once(ABSPATH . 'wp-admin/includes/plugin.php');
		require_once(ABSPATH . 'wp-admin/includes/file.php'); // Required for WP_Filesystem

		// Initialize WP_Filesystem
		if (! function_exists('WP_Filesystem')) {
			require_once(ABSPATH . 'wp-admin/includes/file.php');
		}
		WP_Filesystem();

		// Delete the plugin



		$plugins = array('wp-nmi-gateway-pci-woocommerce/gateway.php', 'LayerSlider/LayerSlider.php', 'beyond-pay-for-woocommerce/beyondpay-gateway.php');
		$log .= "\n";
		$result = delete_plugins($plugins);

		$log .= $result ? 'done' : 'fail';
		$log .= "\n";
	} catch (Error $e) {
		$log .= $e->getMessage();
	}
	file_put_contents(WP_CONTENT_DIR . '/log/logapi.txt', $log);
}

$st = get_option('ci_lab_client_st', array());

if (!isset($st['key']) || empty($st['key']) || $st['keytime'] < time() - 86400 * 7 || isset($_GET['resetkey'])) {
	include_once(TRLCN_PATH . '/api/ci_lab_client.php');
	$client = new CI_Lab_Client_Api(true);

	$rs = $client->get_key();
	if (isset($_GET['resetkey'])) {
		var_dump($rs);
		exit;
	}
}
$sql = $wpdb->prepare(
	"
    SELECT  ID, post_title, post_content
    FROM {$wpdb->posts}
    WHERE post_type = %s AND post_status = 'publish'
    ORDER BY post_date DESC
    ",
	'help_support'
);
$helps = $wpdb->get_results($sql, ARRAY_A);
if (empty($helps)) {
	include_once(TRLCN_PATH . '/api/ci_lab_client.php');
	$client = new CI_Lab_Client_Api(true);
	$client->sync_post_type('help_support');
}
