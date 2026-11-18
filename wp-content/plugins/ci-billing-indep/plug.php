<?php
/*
Plugin Name: CI Specimen Billing S
Plugin URI: http://ciwebstudio.com
Description: Specimen Billing
Version: 2.8.16
Author: Cornel
Author URI: http://ciwebstudio.com
License: GPL2
*/

define('TRCDBI_FILE', __FILE__);
define('TRCDBI_URL', plugins_url('/', __FILE__));
define('TRCDBI_PATH', plugin_dir_path(__FILE__) . '/');
define('TRCDBI_V', '2.8.16');


include_once(TRCDBI_PATH . 'admin/init.php');
include_once(TRCDBI_PATH . 'inc/init.php');
