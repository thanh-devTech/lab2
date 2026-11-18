<?php

$billing_page = true;

if(function_exists('tr_pdd_get_req_file'))
{
	$file = tr_pdd_get_req_file('blood');
}else{
	$file = TRCPDD_PATH . 'pages/admin_form_blood.php';
}


include_once( $file );