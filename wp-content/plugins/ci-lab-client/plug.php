<?php
/*
Plugin Name: CI Lab Addon
Plugin URI: http://ciwebstudio.com
Description: Lab Addon (only active on main site)
Version: 2.1.1
Author: Cornel
Author URI: http://ciwebstudio.com
License: GPL2
*/

define('TRLCN_FILE', __FILE__);
define('TRLCN_URL', plugins_url('/', __FILE__));
define('TRLCN_PATH', plugin_dir_path(__FILE__));
define('TRLCN_V', '2.1.1');


if (strpos($_SERVER['REQUEST_URI'], '/lis/') !== false || strpos($_SERVER['REQUEST_URI'], '/lis-login/') !== false) {
    add_filter('login_url', function ($url) {
        $url = str_replace('wp-login.php', 'lis-login', $url);
        return $url;
    });

    add_filter('site_url', function ($url) {
        $url = str_replace('wp-admin', 'lis', $url);
        return $url;
    });
}

include_once(TRLCN_PATH . 'inc/init.php');
include_once(TRLCN_PATH . 'inc/admin.php');

spl_autoload_register(function ($classname) {
    if (class_exists($classname)) return;

    $classname = strtolower($classname);

    $full_path = TRLCN_PATH . "inc/" . $classname . '.php';

    if (is_file($full_path) && file_exists($full_path)) {
        include_once $full_path;
    }
});

header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
