<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$wp_htaccess_file = ABSPATH.'/.htaccess';
$wp_config_file = ABSPATH.'/wp-config.php';
include_once (TRSCSC_PATH . 'inc/actions.php');

if(!@opendir(TRSCSC_CACHE_PATH))
    wp_mkdir_p(TRSCSC_CACHE_PATH);

if(!file_exists(TRSCSC_CACHE_QUERIES))
{
    wp_mkdir_p(TRSCSC_CACHE_QUERIES);
}

if(!file_exists(WP_CONTENT_DIR.TRSCSC_CACHE_CSS)){
    wp_mkdir_p(WP_CONTENT_DIR.TRSCSC_CACHE_CSS);
}
if(!file_exists(WP_CONTENT_DIR.TRSCSC_CACHE_JS)){
    wp_mkdir_p(WP_CONTENT_DIR.TRSCSC_CACHE_JS);
}

$cssinfo = str_replace('/css','/cssinfo',WP_CONTENT_DIR.TRSCSC_CACHE_CSS);
if(!file_exists($cssinfo)){
    wp_mkdir_p($cssinfo);
}

$jsinfo = str_replace('/js','/jsinfo',WP_CONTENT_DIR.TRSCSC_CACHE_JS);
if(!file_exists($jsinfo)){
    wp_mkdir_p($jsinfo);
}

if(!wp_is_writable($wp_config_file) )
{
    ?>
    <h4>Your wp-config.php file not writeable. So you need add below content to file</h4>
    <textarea readonly="true" style="width: 100%;height:150px">
    <?php echo trfront_action_change_config_file(true)?>
    </textarea>
    <?php
}
if(!wp_is_writable($wp_htaccess_file))
{
    ?>
    <h4>Your .htaccess file not writeable. So you need add below content to file</h4>
    <textarea readonly="true" style="width: 100%;height:150px">
    <?php echo trfront_action_change_htaccess(true)?>
    </textarea>
    <?php
}