<?php

$_POST['tests'] = $_POST['selectedTests'];
if (isset($_POST['selectedAddings']) && !isset($_POST['products_adding'])) {
	$_POST['products_adding'] = $_POST['selectedAddings'];
}

if (!isset($_POST['pa'])) {
	if (strlen($_POST['gender']) > 1) {
		if (stripos($_POST['gender'], 'female') !== false) {
			$_POST['gender'] = 'F';
		} else if (stripos($_POST['gender'], 'male') !== false) {
			$_POST['gender'] = 'M';
		} else {
			$_POST['gender'] = 'O';
		}
	}
	$_POST['pa'] = [
		'pa_first' 	=> $_POST['firstName'],
		'pa_last' 	=> $_POST['lastName'],
		'pa_dob' 	=> $_POST['dateOfBirth'],
		'pa_gender' => $_POST['gender'],
		'pa_phone' 	=> $_POST['phoneNumber'],
		'pa_email' 	=> $_POST['emailAddress'],
		'pa_address' => $_POST['address'],
		'pa_city' 	=> $_POST['city'],
		'pa_state' 	=> $_POST['state'],
		'pa_zip' 	=> $_POST['zipCode'],
	];
}

$billingfields = array(
	'billing_first_name' 	=> 'pa_first',
	'billing_last_name' 	=> 'pa_last',
	'billing_company' 		=> '',
	'billing_address_1' 	=> 'pa_address',
	'billing_address_2' 	=> '',
	'billing_city' 			=> 'pa_city',
	'billing_country' 		=> '',
	'billing_state' 		=> 'pa_state',
	'billing_postcode' 		=> 'pa_zip',
	'billing_phone' 		=> 'pa_phone',
	'billing_email' 		=> 'pa_email'
);


foreach ($billingfields as $bk => $pak) {
	if (empty($pak)) continue;

	if (!isset($_POST[$bk]) || empty($_POST[$bk])) {
		$_POST[$bk] = $_POST['pa'][$pak];
	}
}

$_POST['billing_address_2'] = '';
$_POST['billing_company'] = '';
$_POST['billing_country'] = 'US';

$_POST['terms-field'] = 1;