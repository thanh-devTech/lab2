<?php
if (! defined('ABSPATH')) exit; // Exit if accessed directly
global $current_role;

add_action('admin_init', 'tr_cache_security_admin_init');
add_action('init', 'tr_cache_security_admin_init_init', 0);
add_action('wp_set_comment_status', 'tr_cache_invalidate_comment', 0, 2);
add_action('wp_update_nav_menu', 'tr_cache_invalidate', 0);
add_action('switch_theme', 'tr_cache_invalidate', 0);
add_action('load-plugin-editor.php', 'tr_cache_load_plugin_editor', 0);
add_action('load-theme-editor.php', 'tr_cache_load_plugin_editor', 0);
add_filter('plugin_action_links', 'tr_cache_filter_plugin_action_links', 10, 3);
add_filter('install_plugins_pre_upload', 'tr_cache_install_plugins_pre_upload');



function tr_cache_security_admin_init()
{
    if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'activate-plugin' && isset($_GET['success']) && isset($_GET['plugin'])) {
        //upgrade plugin
        if ($_GET['plugin'] == plugin_basename(TRSCSC_FILE))
            tr_cache_security_activate();
    } else if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'resend_welcome_user') {
        $user_ids = $_REQUEST['users'];

        foreach ($user_ids as $uid) {
            wp_new_user_notification($uid, null, 'user');
        }
        Tr_Session_Class::add("Sent Welcome!", 'all');
        $url = wp_get_referer();
        if (empty($url)) {
            $url = 'users.php';
        }
        wp_redirect($url);
        exit;
    } else if (isset($_REQUEST['sc_setup'])) {
        tr_cache_security_activate();
    }
    if (function_exists('cjfm_verify_epcp')) {
        remove_action('admin_notices', 'cjfm_verify_epcp');
    }
}

function tr_cache_security_admin_init_init()
{
    global $is_ci_admin;
    if (!is_bool($is_ci_admin)) {
        $user = wp_get_current_user();
        $is_ci_admin = $user->user_login == 'Administrat0r';
        if (isset($_GET['notadmin'])) {
            $is_ci_admin = false;
        }
    }

    $config = array(
        'menu' => array('top' => 'trcs_settings'), //sub page to settings page
        'slug' => 'trcs_settings',
        'page_title' => 'Cache & Security', //The name of this page
        //'menu_title' => 'Cache & Security',
        'capability' => 'trcs_cache', // The capability needed to view the page
        'option_group' => 'trcs_cache', //the name of the option to create in the database
        'local_images' => true, // Use local or hosted images (meta box images for add/remove)
        'icon_url' => 'dashicons-shield',
        'use_with_theme' => false, //change path if used with theme set to true, false for a plugin or anything else for a custom path(default false).
        'path' => dirname(dirname(__FILE__)),
        'usebackup' => true,
        //'network' => true,
        'tabs' => array(
            'this' => array('label' => 'Cache', 'menu' => 'Cache'),
            'trcs_opcache' => array('label' => 'OPCache', 'menu' => true, 'file' => TRSCSC_PATH . 'pages/admin_obcache.php'),
            'trcs_security' => array('option' => 'tr_security', 'label' => 'Security', 'menu' => true),
            'trcs_otherst' => array('option' => 'trcs_otherst', 'label' => 'Other Settings', 'menu' => true, 'network' => false),
            'trcs_crons' => array('label' => 'Crons', 'menu' => true, 'file' => TRSCSC_PATH . 'pages/admin_cron_page.php'),
            //'trcs_scan' => array('label' => 'Security Scan', 'menu' => true,'file'=>TRSCSC_PATH.'pages/admin_scan_page.php'),
            'trcs_db_optimize' => array('label' => 'Optimize DB', 'menu' => true, 'file' => TRSCSC_PATH . 'pages/admin_db_optimize.php'),
            'trcs_image_optimize' => array('label' => 'Optimize Images', 'menu' => true, 'file' => TRSCSC_PATH . 'pages/admin_image_optimize.php'),
            'trcs_status' => array('label' => 'Test', 'menu' => true, 'file' => TRSCSC_PATH . 'pages/admin_status.php'),
        ),
        'menu_pos' => 2000
    );

    if (defined('WP_NETWORK_ADMIN') && WP_NETWORK_ADMIN === true) {
        $config = array(
            'menu' => array('top' => 'trcs_settings'), //sub page to settings page
            'slug' => 'trcs_settings',
            'page_title' => 'CI Security', //The name of this page
            'capability' => 'edit_themes', // The capability needed to view the page
            'option_group' => 'trcs_nw_security', //the name of the option to create in the database
            'local_images' => true, // Use local or hosted images (meta box images for add/remove)
            // 'icon_url' => TRJM_URL.'images/mobile.png',
            'use_with_theme' => false, //change path if used with theme set to true, false for a plugin or anything else for a custom path(default false).
            'path' => dirname(dirname(__FILE__)),
            //'usebackup' => true,
            'network' => true,
        );
    }

    /**
     * Initiate your admin page
     */

    if ($is_ci_admin) {
        new TR_Admin_Page_Class_V10($config);
    } else if (class_exists('Tr_Admin_Page_V7')) {
        add_action('admin_menu', function () {
            $admin              = new Tr_Admin_Page_V7(TRSCSC_PATH);
            $admin->add_page('ci_ban_page', 'Login Security', 'trcs_cache', 'default', array('icon' => 'dashicons-shield', 'position' => 900));
        });
    }
}


function tr_cache_invalidate()
{
    global $tr_invalidated;
    if ($tr_invalidated)
        return;
    @touch(TRSCSC_CACHE_PATH . '/_global.dat');
    $tr_invalidated = true;
}


function tr_cache_invalidate_comment($comment_id, $comment_status)
{
    if (!$comment = get_comment($comment_id))
        return false;

    $post_id = $comment->comment_post_ID;
    tr_cache_invalidate_post($post_id);
}

function trcache_alert_button_admin_save_buttons_area($html)
{
    wp_enqueue_script('thickbox');
    wp_enqueue_style('thickbox');

    $wp_htaccess_file = ABSPATH . '/.htaccess';
    $wp_config_file = ABSPATH . '/wp-config.php';
    $button = '';
    if (!wp_is_writable($wp_config_file) || !wp_is_writable($wp_htaccess_file)) {
        $button = '<a class=" btn btn-warning thickbox" href="' . admin_url('admin-ajax.php') . '?tr_action=need_fix_plugin&action=need_fix_plugin" style="margin-left:20px;background:red">Need Fix</a>';
    }
    if (!empty($button))
        $html = $button . $html;
    return $html;
}

function tr_cache_load_plugin_editor()
{
    if (!tr_get_option('tr_security', 'allow_edit_plug')) {
        exit;
    }
}

function tr_cache_filter_plugin_action_links($actions, $plugin_file, $plugin_data)
{
    if (strpos($plugin_file, 'cache-and-security/plug.php') > 0 && !tr_get_option('tr_security', 'allow_deactiate_sc')) {
        unset($actions['deactivate']);
    }
    return $actions;
}

function tr_cache_install_plugins_pre_upload()
{
    if (!tr_get_option('tr_security', 'allow_install_plug')) {
        wp_redirect('plugins.php');
        exit;
    }
}



add_filter('bulk_actions-users', 'tr_cs_bulk_actions_users');
function tr_cs_bulk_actions_users($actions)
{
    $actions['resend_welcome_user'] = 'Resend Welcome';
    return $actions;
}

add_action('all_admin_notices', 'tr_cs_all_admin_notices');
function tr_cs_all_admin_notices()
{
    Tr_Session_Class::show('all');
}

function tr_sc_ci_ban_page()
{
    include(TRSCSC_PATH . 'pages/admin_ci_ban_page.php');
}

add_filter('pre_site_option_update_core', 'trsc_specific_version');
add_filter('site_transient_update_core', 'trsc_specific_version');
function trsc_specific_version($updates)
{

    $sprache = get_locale() . '/';
    if ($sprache == 'en_US/' or $sprache == 'en') {
        $sprache = '';
    };
    $dg_version = tr_get_option('trcs_otherst', 'wp_version');
    if ($dg_version < 1)
        return $updates;

    global $wp_version;
    // If current version is target version then stop

    if (version_compare($wp_version, $dg_version) == 0) {
        return;
    } //https://downloads.wordpress.org/release/de_DE/wordpress-4.5.zip

    $updates->updates[0]->download = trcs_wp_get_url($dg_version);
    $updates->updates[0]->packages->full = trcs_wp_get_url($dg_version);
    $updates->updates[0]->packages->no_content = '';
    $updates->updates[0]->packages->new_bundled = '';
    $updates->updates[0]->current = $dg_version;

    return $updates;
}

function trcs_wp_get_url($version)
{
    $sprache = get_locale() . '/';
    if ($sprache == 'en_US/' or $sprache == 'en') {
        $sprache = '';
    };
    $url = "https://downloads.wordpress.org/release/" . $sprache . "wordpress-" . $version . ".zip";
    return $url;
}
