<?php
/*
Plugin Name: CI Data Works
Plugin URI: http://ciwebstudio.com
Description: Data Works
Version: 2.4.7.67
Author: Cornel
Author URI: http://ciwebstudio.com
License: GPL2
*/

defined('ABSPATH') || exit;
define('TRCPDD_FILE', __FILE__);
define('TRCPDD_URL', plugins_url('/', __FILE__));
define('TRCPDD_PATH', plugin_dir_path(__FILE__));
define('TRCPDD_V', '2.4.7.67');
define('TRCPDD_VS', '5.9.511');

define('TR_CSV_SEPARATER', ',');


define('TRPDD_INS_CARES_ACT', true);
define('TRPDD_UPSAVED', true);

if (!defined('TRPDD_SAME_LAB')) {
	define('TRPDD_SAME_LAB', true);
}

if (!defined('TRPDD_CLINIC_BY_LAB')) {
	define('TRPDD_CLINIC_BY_LAB', false);
}

if (!defined('TRPP_USER_INACTIVITY_TIMEOUT')) {
	define('TRPP_USER_INACTIVITY_TIMEOUT', 20 * 60); //20 minutes
}


if (!defined('TRPDD_HOUR_CHANGE_BATCH')) {
	define('TRPDD_HOUR_CHANGE_BATCH', 0); //0 is disabled
}



if (function_exists('mysqli_report')) {
	mysqli_report(MYSQLI_REPORT_OFF);
}

if (isset($_GET['tr_debug'])) {
	ini_set('display_errors', true);
}

ini_set('error_log', WP_CONTENT_DIR . '/log/errors/error_log.log');
ini_set('log_errors', true);


include_once(TRCPDD_PATH . 'inc/form_define.php');
include_once(TRCPDD_PATH . 'inc/functions.php');
include_once(TRCPDD_PATH . 'inc/functions_covid.php');
include_once(TRCPDD_PATH . 'inc/functions_user.php');
include_once(TRCPDD_PATH . 'inc/functions_file.php');
include_once(TRCPDD_PATH . 'inc/functions_calculate.php');
include_once(TRCPDD_PATH . 'inc/functions_apis.php');
include_once(TRCPDD_PATH . 'admin/init.php');
include_once(TRCPDD_PATH . 'inc/init.php');

if (defined('DOING_CRON') && DOING_CRON) {
	include_once(TRCPDD_PATH . 'inc/crons.php');
}
