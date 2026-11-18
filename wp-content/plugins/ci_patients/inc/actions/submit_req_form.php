<?php
global $wpdb;

$return = array('status'=>'error','html'=>'');


$custom_form = isset($_REQUEST['custom_form']) && !empty($_REQUEST['custom_form'])? $_REQUEST['custom_form'] : '';
if(isset($_REQUEST['is_concierge']) && $_REQUEST['is_concierge'])
{
	$custom_form = 'concierge';
}
else if(isset($_REQUEST['is_meijer']) && $_REQUEST['is_meijer'])
{
	$custom_form = 'meijer';
}
else if(isset($_REQUEST['is_canada']) && $_REQUEST['is_canada'])
{
	$custom_form = 'canada';
}

$form_key = 'trpatient_product_settings';
if(!empty($custom_form))
{
	$form_key = 'trpatient_'.$custom_form.'_settings';
}


$form_options = get_option($form_key, array());
$pa_options = get_option('trpatient_settings',array());

$success_msg = !empty($form_options['success_msg'])? $form_options['success_msg'] : 'Your order sent successfully!';
$btn_text = !empty($form_options['purchase_more_text'])? $form_options['purchase_more_text'] : 'Purchase More';
$message = '<p class="center">'.$success_msg.'</p><br>';
$message .= '<p class="center"><a href="#" class="button qbutton purchase_more">'.$btn_text.'</a></p>';
$return['html'] = $message;



$tests 			= isset($_POST['tests'])? $_POST['tests'] : array();
$custom_price 	= $_POST['custom_price'];
$is_group_testing = isset($_POST['group_testing']);
$is_insurance 	= false;
$count_patient 	= 1;
if(isset($_POST['pa_muti']) && is_array($_POST['pa_muti']))
{
	$count_patient+= count($_POST['pa_muti']);
}
try{
	if(is_admin())
	{
		WC()->frontend_includes();
		wc_load_cart();
	}
	WC()->cart->empty_cart();
}catch (Exception $e){

}


if($form_options['flatfee']>0)
{
	WC()->cart->add_to_cart($form_options['flatfee'],1);
}

//products_adding

if(isset($_POST['products_adding']) && is_array($_POST['products_adding']) && count($_POST['products_adding'])>0){
	if(is_array($form_options['products_adding']) && count($form_options['products_adding'])>0)
	{
		foreach($_POST['products_adding'] as $pid)
		{
			WC()->cart->add_to_cart($pid,1);
		}
	}
}


if($is_group_testing)
{

	try {
		if($pa_options['g_testing_pid']>0)
		{
			$rs = WC()->cart->add_to_cart($pa_options['g_testing_pid'],$count_patient,0, array(),array('custom_price'=>$custom_price));
			if(!$rs)
			{
				wc_print_notices();
				echo 'Error while setup page. Please try again later!';
				return;
			}
		}
	}catch(Error $e){
		var_dump($e);
	}

}else{
	if(empty($tests))exit;

	try {

		foreach($tests as $key)
		{
			list($type,$product_id) = explode('|',$key);

			$product = wc_get_product($product_id);
			if($product)
			{
				$secondup_price = '';
				if($count_patient>1)
				{
					$secondup_price = get_post_meta($product_id,'_secondup_price',true);
				}

				if($secondup_price!=='' && !is_null($secondup_price)){
					$rs = WC()->cart->add_to_cart($product_id,1);
					if($secondup_price>0)
					{
						$rs = WC()->cart->add_to_cart($product_id,$count_patient-1,0,array(),array('custom_price'=>floatval($secondup_price)));
					}

				}else{
					$rs = WC()->cart->add_to_cart($product_id,$count_patient);
				}
				if(!$rs)
				{
					wc_print_notices();
					echo 'Error while setup page. Please try again later!';
					return;
				}
			}

			if(!$is_insurance && !empty($form_options['insurance_text']))
			{
				foreach($form_options['products_insurance'] as $arr)
				{
					if($product_id == $arr['ID'])
					{
						$is_insurance = true;
					}
				}
			}
		}


	}catch(Error $e){
		var_dump($e);
	}

	add_filter('woocommerce_checkout_get_value','trajax_func_woocommerce_checkout_get_value',99,2);
	function trajax_func_woocommerce_checkout_get_value($value,$field)
	{
		$pa = $_POST['pa'];
		$fields =array(
			'billing_first_name' => 'pa_first',
			'billing_last_name' => 'pa_last',
			'billing_address_1' => 'pa_address',
			'billing_city' => 'pa_city',
			'billing_state' => 'pa_state',
			'billing_postcode' => 'pa_zip',
			'billing_phone' => 'pa_phone',
			'billing_email' => 'pa_email',
		);
		if(isset($fields[$field])){
			$paf = $fields[$field];
			if(empty($value))
			{
				$value = $pa[$paf];
			}
		}

		if($field=='billing_country'){
			$canada_states = array('AB' => 'Alberta',
			                       'BC' => 'British Columbia',
			                       'MB' => 'Manitoba',
			                       'NB' => 'New Brunswick',
			                       'NL' => 'Newfoundland and Labrador',
			                       'NS' => 'Nova Scotia',
			                       'ON' => 'Ontario',
			                       'PE' => 'Prince Edward Island',
			                       'QC' => 'Quebec',
			                       'SK' => 'Saskatchewan',
			                       'NT' => 'Northwest Territories',
			                       'NU' => 'Nunavut',
			                       'YT' => 'Yukon');
			if(isset($canada_states[$pa['pa_state']])){
				$value = 'CA';
			}else{
				$value = 'US';
			}
		}


		return $value;
	}


}


do_action( 'woocommerce_check_cart_items' );
WC()->cart->calculate_totals();

// Get checkout object.
$checkout = WC()->checkout();
if(isset($_REQUEST['order_id']) && $_REQUEST['order_id']>0){
	$order_id = $_REQUEST['order_id'];
}
else{
	$data = $checkout->get_checkout_fields();
	$order_id = $checkout->create_order($data);
}

if($order_id>0)
{
	$return['order_id']= $order_id;
	trcpa_add_data_to_order($order_id);
	$odata = get_post_meta($order_id, '_restration_covid_testing', true);
	if ($odata && is_array($odata)) {
		if(trcpa_add_covid_order($order_id))
		{
			$return['status'] = 'ok';
			$wpdb->update($wpdb->posts,array('post_status'=>'wc-processing'),array('ID'=>$order_id));
		}
	}
}

wp_send_json($return);