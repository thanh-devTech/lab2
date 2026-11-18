<?php
if (! defined('ABSPATH')) exit; // Exit if accessed directly

global $wpdb;
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
//create tables


$sql_fields = "CREATE TABLE `wp_tr_lock_ip` (
  `ip` varchar(50) DEFAULT NULL,
  `loginfail` int(11) default 0,
  `cookiefail` int(11) default 0,
  `lasttime` bigint(15) default 0,
  `bantime` bigint(15) default 0,
  `unlock` VARCHAR (50) default '',
  PRIMARY KEY (`ip`)
) ENGINE=InnoDB";
$rs = @dbDelta($sql_fields);


$old_name = $wpdb->prefix . 'tr_security_log';
$new_name = 'wp_tr_security_log';
if ($old_name != $new_name) {
    @dbDelta("RENAME TABLE {$old_name} TO {$new_name}");
}

$sql_fields = "CREATE TABLE `{$new_name}` (
  `msg` text,
  `username` varchar(50),
  `ip` varchar(50),
  `ltype` varchar(10) not null,
  `ltime` int(11)
) ENGINE=InnoDB ;";
$rs = dbDelta($sql_fields);



$sql_fields = "CREATE TABLE `wp_tr_log` (
  `ip` varchar(50),
  `url` varchar(500),
  `log` text,
  `agent` text,
  `load_time` varchar(15),
  `load_start` datetime,
  `log_type` varchar(10) not null,
  `ip_count` int(11)
) ENGINE=InnoDB ;";

$rs = dbDelta($sql_fields);


$sql = "CREATE TABLE `wp_tr_file_logs` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `file` varchar(250),
  `ftype` char(1),
  `lasttime` datetime,
  `changed` datetime,
  `sizechanged` int(8),
  `synced` int(1) default 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB";
dbDelta($sql);


$sql = "CREATE TABLE `wp_tr_image_link_posts` (
  `image_id` int(11),
  `post_id` int(11),
  UNIQUE KEY `imgpostid` (`image_id`, `post_id`)
) ENGINE=InnoDB";
dbDelta($sql);

$sql = "CREATE TABLE `ci_user_last_activity` (
`user_ID` bigint(20) NOT NULL,
`token` varchar(80) NOT NULL,
`last_activity` int(11) default 0,
`blog_id` int(5) default 1,
KEY `user_ID_token`(`user_ID`, `token`)
) ENGINE=InnoDB";
$rs = dbDelta($sql);


if (isset($only_setup_table) && $only_setup_table) {
    return;
}

if (get_option('tr_security') === false) {
    //default security
    $config = array(
        'option_group' => 'tr_security',
        'path' => dirname(dirname(__FILE__)),
    );
    $secu = new TR_Admin_Page_Class_V9($config);
    $secu->loadconfig('trcs_security');
    $secu->restore();
}

if (get_option('trcs_cache') === false) {
    //default cache
    $config = array(
        'option_group' => 'trcs_cache',
        'path' => dirname(dirname(__FILE__)),
    );
    $secu = new TR_Admin_Page_Class_V9($config);
    $secu->loadconfig('trcs_cache');
    $secu->restore();
}


wp_clear_scheduled_hook('cache_security');
wp_schedule_event(time() + 300, 'hourly', 'cache_security');

if (!@opendir(TRSCSC_CACHE_PATH))
    wp_mkdir_p(TRSCSC_CACHE_PATH);

if (!file_exists(TRSCSC_CACHE_QUERIES)) {
    wp_mkdir_p(TRSCSC_CACHE_QUERIES);
}

if (!file_exists(WP_CONTENT_DIR . TRSCSC_CACHE_CSS)) {
    wp_mkdir_p(WP_CONTENT_DIR . TRSCSC_CACHE_CSS);
}
if (!file_exists(WP_CONTENT_DIR . TRSCSC_CACHE_JS)) {
    wp_mkdir_p(WP_CONTENT_DIR . TRSCSC_CACHE_JS);
}

$cssinfo = str_replace('/css', '/cssinfo', WP_CONTENT_DIR . TRSCSC_CACHE_CSS);
if (!file_exists($cssinfo)) {
    wp_mkdir_p($cssinfo);
}

$jsinfo = str_replace('/js', '/jsinfo', WP_CONTENT_DIR . TRSCSC_CACHE_JS);
if (!file_exists($jsinfo)) {
    wp_mkdir_p($jsinfo);
}

include_once(TRSCSC_PATH . 'inc/admin_actions.php');
//  tradmin_action_generate_config(false,false);



//add htaccess to uploads folder
$htcontent = file_get_contents(TRSCSC_PATH . 'wp-content/.htaccess');
@file_put_contents(WP_CONTENT_DIR . '/uploads/.htaccess', $htcontent);
@file_put_contents(WP_CONTENT_DIR . '/wflogs/.htaccess', $htcontent);
@file_put_contents(get_template_directory() . '/include/plugin/really-simple-captcha/tmp/.htaccess', $htcontent);
@file_put_contents(WP_CONTENT_DIR . '/plugins/really-simple-captcha/tmp/.htaccess', $htcontent);
@file_put_contents(WP_CONTENT_DIR . '/db-error.php', file_get_contents(TRSCSC_PATH . 'wp-content/db-error.php'));

//check robots.txt
$robots_file = ABSPATH . '/robots.txt';
if (!file_exists($robots_file)) {
    $d = file_get_contents(TRSCSC_PATH . 'wp-content/robots.txt');
    file_put_contents($robots_file, $d);
}


$admin = get_role('administrator');
$admin->add_cap('view_query_monitor');
$admin->add_cap('trcs_cache');

$admin = get_role('AdminCIDW');
if ($admin) {
    $admin->add_cap('view_query_monitor');
    $admin->add_cap('trcs_cache');
}




update_option('TRSCSC_VS', TRSCSC_VS, false);
