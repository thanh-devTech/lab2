<?php
global $wpdb;
$type = trim($_REQUEST['type']);
$search = trim($_REQUEST['term']);

switch ($type)
{
	case 'diagnostic_code':
		$codes = tr_pdd_get_dagnostic_codes('pcr');
		$return = array();
		foreach($codes as $code => $desc)
		{
			$code = trim($code);
			if(stripos($code,$search)===0 || stripos($desc,$search)===0)
			{
				$return[] = array(
					'value' => $code,
					'label' => $desc,
				);
			}

		}
		wp_send_json($return);
		break;
}


exit;