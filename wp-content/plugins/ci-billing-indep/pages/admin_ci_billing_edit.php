<?php

$billing_page = true;
$has_billing_edit = false;

if(function_exists('tr_pdd_get_req_file'))
{
	$file = tr_pdd_get_req_file('tox');

	ob_start();
	include_once( $file );
	$content = ob_get_clean();
	if($has_billing_edit)
	{
		echo $content;
	}
}

if(!$has_billing_edit)
{
	$file = TRCDBI_PATH . 'pages/old_tox_edit.php';
	include_once( $file );
}


