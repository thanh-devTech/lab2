<?php

$billing_page = true;


if(function_exists('tr_pdd_get_req_file'))
{
	$file = tr_pdd_get_req_file('pcr');
}else{
	$file = TRCPDD_PATH . 'pages/admin_form_pcr.php';
}


include_once( $file );