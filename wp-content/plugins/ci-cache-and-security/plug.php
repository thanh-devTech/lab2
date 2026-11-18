<?php
/*
Plugin Name: CI Cache and Security
Plugin URI: http://ciwebstudio.com/
Description:  Cache and security your site
Version: 1.8.80
Author: Cornel
Author URI: http://ciwebstudio.com/
License: GPL2
*/

define('TRSCSC_VS', '1.8.80');

define('TRSCSC_FILE', __FILE__);
define('TRSCSC_URL', plugins_url('/', __FILE__));
define('TRSCSC_PATH', plugin_dir_path(__FILE__));
define('TRSCSC_CACHE_PATH', WP_CONTENT_DIR . '/cache/tr-cache');
define('TRSCSC_CACHE_JS', '/cache/js');
define('TRSCSC_CACHE_CSS', '/cache/css');
define('TRSCSC_CACHE_QUERIES', WP_CONTENT_DIR . '/cache/queries');
define('TRSCSC_SERVER', 'https://ciwebstudio.com/wp-load.php');
define('TRSCSC_CM_CACHE', TRSCSC_PATH . 'cm-cache.php');


if ((isset($_SERVER['HTTP_CF_VISITOR']) && stripos($_SERVER['HTTP_CF_VISITOR'], 'https') > 0)) {
    $_SERVER['HTTPS'] = 'on';
}



if (isset($_GET['ci_catpcha_img'])) {

    ob_get_clean();
    require(TRSCSC_PATH . "inc/phpcaptcha.php");

    $aFonts = array(TRSCSC_PATH . 'font/VeraBd.ttf');

    $oVisualCaptcha = new PhpCaptcha($aFonts, 140, 25);


    $oVisualCaptcha->UseColour(true);

    //$oVisualCaptcha->SetFileType('jpeg');

    $oVisualCaptcha->SetNumChars(4);

    $oVisualCaptcha->SetMinFontSize(16);

    $oVisualCaptcha->SetMaxFontSize(18);

    $oVisualCaptcha->Create();

    exit;
}


include_once(TRSCSC_PATH . 'admin/init.php');

include_once(TRSCSC_PATH . 'inc/init.php');
if (is_admin()) {
    include_once(TRSCSC_PATH . 'inc/admin.php');
}


if (!class_exists('TR_Upgrade_all_plugins')) {
    @include_once(dirname(__FILE__) . '/inc/upgrade.php');
}


if ((isset($_GET['trquery_monitor']) || isset($_COOKIE['trquery_monitor']))  && !defined('DOING_AJAX')) {
    if (!defined('SITECOOKIEPATH')) {
        define('SITECOOKIEPATH', '/');
    }
    if (isset($_GET['remove'])) {
        setcookie('trquery_monitor', 1, time() - 11, SITECOOKIEPATH, null);
    } else if (!isset($_COOKIE['trquery_monitor'])) {
        setcookie('trquery_monitor', 1, time() + 86400 * 300, SITECOOKIEPATH, null);
    }

    @ini_set('opcache.revalidate_freq', 2);
    if (!class_exists('QM_Plugin')) {
        include_once(TRSCSC_PATH . 'plugins/query-monitor/query-monitor.php');
    }
}
