<?php
global $return, $message_success,$wpdb,$logs;
if(!session_id())session_start();

wc_maybe_define_constant( 'WOOCOMMERCE_CHECKOUT', true );

$return = array('status'=>'error','result'=>'failure','html'=>'');
//add to cart

$options = tr_patient_get_form_options();

$pa_options = get_option('trpatient_settings',array());

$message_success = '<p class="center">Your order sent successfully!</p><br>';
$message_success .= '<p class="center"><a href="#" class="button qbutton purchase_more">Purchase More</a></p>';
if(!empty($pa_options['script_success'])){
	$message_success.= '<script>try{'.$pa_options['script_success'].'}catch(e){console.log(e)}</script>';
}

if(is_admin())
{
	WC()->frontend_includes();
	wc_load_cart();
}

$count_patient = 1;
if(isset($_POST['pa_muti']) && is_array($_POST['pa_muti']))
{
	$count_patient+= count($_POST['pa_muti']);
}
$custom_price = $_POST['custom_price'];
$is_group_testing = isset($_POST['group_testing']);



if($is_group_testing)
{
	try {

		if(WC()->cart->is_empty())
		{
			WC()->cart->empty_cart();

			$rs = WC()->cart->add_to_cart($pa_options['g_testing_pid'],$count_patient,0, array(),array('custom_price'=>$custom_price));
			if(!$rs)
			{
				$return['messages'] = 'Cant add product to cart';
				wp_send_json($return);
			}
		}


	}catch(Error $e){
		var_dump($e);
	}
}
else{
	if($_SESSION['current_order_id']>0)
	{
		$return = array('status'=>'ok','result'=>'success','html'=>$message_success);
		$mid = trcpa_add_covid_order($_SESSION['current_order_id']);

		wp_send_json($return);

	}
	$tests = $_POST['tests'];
	if(empty($tests))
	{
		exit;
	}
	try {

		if(WC()->cart->is_empty())
		{
			WC()->cart->empty_cart();


			if($options['flatfee']>0)
			{
				WC()->cart->add_to_cart($options['flatfee'],1);
			}


			//products_adding

			if(isset($_POST['products_adding']) && is_array($_POST['products_adding']) && count($_POST['products_adding'])>0){
				if(is_array($options['products_adding']) && count($options['products_adding'])>0)
				{
					foreach($_POST['products_adding'] as $pid)
					{
						$quantity = 1;
						WC()->cart->add_to_cart($pid,$quantity);
					}
				}
			}
			$productids = array();

			foreach($tests as $key)
			{
				list($type,$product_id,$paytype,$quantitytest) = explode('|',$key);

				$productids[] = $product_id;

				$product = wc_get_product($product_id);
				if($product)
				{
					$secondup_price = '';
					if($count_patient>1)
					{
						$secondup_price = get_post_meta($product_id,'_secondup_price',true);
					}

					if($secondup_price!=='' && !is_null($secondup_price)){
						$quantity = 1;
						if($quantitytest>0)
						{
							$quantity = $quantitytest;
						}
						$rs = WC()->cart->add_to_cart($product_id,$quantity);
						if($secondup_price>0)
						{
							$rs = WC()->cart->add_to_cart($product_id,$count_patient-1,0,array(),array('custom_price'=>floatval($secondup_price)));
						}

					}else{
						$rs = WC()->cart->add_to_cart($product_id,$count_patient);
					}
					if(!$rs)
					{
						$return['messages'] = 'Cant add product to cart';
						wp_send_json($return);
					}
				}
			}


		}


	}catch(Error $e){
		var_dump($e);
	}

}


WC()->checkout()->process_checkout();
ob_start();
wc_print_notices();
$msg = ob_get_clean();
$return['messages'] = $msg;
wp_send_json($return);
exit;