<?php
/*
Plugin Name: CI Patients
Plugin URI: http://ciwebstudio.com
Description: Data Works for patient site
Version: 5.8.1
Author: Cornel
Author URI: http://ciwebstudio.com
License: GPL2
*/

define('TRCPPR_FILE', __FILE__);
define('TRCPPR_URL', plugins_url('/', __FILE__));
define('TRCPPR_PATH', plugin_dir_path(__FILE__));

// Composer-managed dependencies (endroid/qr-code + the legacy QRcode shim). Guarded so a
// deploy without `composer install` degrades QR generation rather than fataling the plugin.
if (file_exists(TRCPPR_PATH . 'vendor/autoload.php')) {
	require_once TRCPPR_PATH . 'vendor/autoload.php';
}
define('TRPDD_PATIENT_SITE', true);
define('TRCPPR_V', '5.8.1');
define('TRCPPR_REMOVE_ULTRA_LOGIN', true);

define('PATIENT_URL_RESULTS', '/patient/results');
define('PATIENT_URL_LINKED', '/patient/linked');
define('PATIENT_URL_HELP', '/patient/help');


include(TRCPPR_PATH . '/admin/init.php');
include(TRCPPR_PATH . '/inc/functions.php');
include(TRCPPR_PATH . '/inc/patient_activity.php');
include(TRCPPR_PATH . '/inc/init.php');

if (is_admin()) {
	include(TRCPPR_PATH . '/inc/admin.php');
}
