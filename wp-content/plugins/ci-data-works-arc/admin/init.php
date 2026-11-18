<?php
if (!session_id() && !defined('DOING_CRON')) session_start();


spl_autoload_register(function ($classname) {
    if (class_exists($classname)) return;

    $dir = dirname(__FILE__);
    $classname = strtolower($classname);
    $filenames = array();
    $filenames[] = $dir . "/classes/" . $classname;
    $filenames[] = dirname($dir) . "/inc/" . $classname;

    foreach ($filenames as $fn) {
        $full_path = $fn . ".php";

        if (is_file($full_path)) {
            include_once $full_path;
            break;
        }
    }
});


if (!function_exists('tr_get_option')) {
    function tr_get_option($key, $name, $default = false)
    {
        global $option_key_cache;
        if (isset($option_key_cache[$key])) {
            $data = $option_key_cache[$key];
        } else {
            $data = get_option($key);

            if (!is_array($option_key_cache)) $option_key_cache = array();
            $option_key_cache[$key] = $data;
        }

        return (isset($data[$name])) ? $data[$name] : $default;
    }
}

$current_dir = dirname(__FILE__);
$inc_dir     = dirname($current_dir);
$tr_action = isset($_REQUEST['tr_action']) ? $_REQUEST['tr_action'] : '';
$tr_control = isset($_REQUEST['tr_control']) ? $_REQUEST['tr_control'] : '';
if (is_admin()) {

    //custom admin.php
    $custom_admin = $inc_dir . '/inc/admin.php';

    if (is_file($custom_admin)) {
        include_once($custom_admin);
    }

    if (!empty($tr_action)) {
        $action_admin = $inc_dir . '/inc/admin_actions.php';
        if (is_file($action_admin)) {
            include_once($action_admin);
            $function_action = 'tradmin_action_' . $tr_action;
            if (function_exists($function_action)) {
                add_action('admin_init', $function_action);
                unset($_REQUEST['tr_action']);
            }
        }
    }
} else {
    if (!empty($tr_action)) {
        $action_file = $inc_dir . '/inc/actions.php';
        if (is_file($action_file)) {
            include_once($action_file);
            $function_action = 'trfront_action_' . $tr_action;
            if (function_exists($function_action)) {
                add_action('init', $function_action);
                unset($_REQUEST['tr_action']);
            }
        }
    }
}
//new function use class
if (!empty($tr_control)) {
    new Tr_Controller_Call($inc_dir);
}
