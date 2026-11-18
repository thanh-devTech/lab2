<?php

global $wpdb;


$options_panel->OpenTabs_container();
$options_panel->TabsListing(array(
    'links' => array(
        'general_tab' => __('General'),
        'pverify_tab' =>__('PVerify Soap')
    )
));

function trpddunq_wp_timezone_choice2($selected)
{
    if(is_array($selected))
    {
        $selected = isset($selected[0])? $selected[0] : '';
    }
    return '<option value="">select</option>'.wp_timezone_choice($selected);
}



$options_panel->OpenTab('general_tab');
$options_panel->Title(__("General"));

$options_panel->addText('email_doc_notify',array('name'=>'Notify Email New Doctor','size'=>70));
$options_panel->addText('email_clinic_notify',array('name'=>'Notify Email New Clinic','size'=>70));
$options_panel->addText('email_collector_notify',array('name'=>'Notify Email New Collector','size'=>70));
$options_panel->addCheckbox('log_forms', array('name' => 'Log Forms'));
$options_panel->addSelect('timezone',null,array('name' => 'Time Zone all (if set)','func'=>'trpddunq_wp_timezone_choice2'));
$options_panel->CloseTab();


$options_panel->OpenTab('pverify_tab');
$options_panel->Title(__("PVerify Soap"));

$options_panel->addText('Pverify_UserID',array('name'=>'UserID','desc'=>'','size'=>70));
$options_panel->addText('Pverify_Password',array('name'=>'Password','desc'=>'','size'=>70));
$options_panel->CloseTab();
