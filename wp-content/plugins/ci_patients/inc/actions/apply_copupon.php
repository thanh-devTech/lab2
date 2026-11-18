<?php
global $wpdb;

if(!session_id())
{
	session_start();
}

if(!defined('WOOCOMMERCE_CHECKOUT'))
{
	define( 'WOOCOMMERCE_CHECKOUT', true );
}
if(is_admin())
{
	WC()->frontend_includes();
	wc_load_cart();
}
define('DOING_AJAX',true);
$return = array('status'=>'error','summary'=>'','alert'=>'');

if($_POST['doc']==='today'){
	$_POST['doc'] = date_i18n('Y-m-d 00:00:00');
}
$ticket_date = date('Y-m-d',strtotime($_POST['doc']));
if($_POST['coupon_code'])
{
	$code = wc_format_coupon_code( wp_unslash( $_POST['coupon_code'] ) );
	$code = trim($code);

	//check $code by date
	$pass_date = true;
	$coupon_id = $wpdb->get_var("select ID from {$wpdb->posts} where post_title='{$code}'");
	if($coupon_id>0)
	{
		$limitdate = get_post_meta($coupon_id,'_ticket_date',true);

		if(!empty($limitdate))
		{
			$limitdate = date('Y-m-d',strtotime($limitdate));
			$pass_date = $limitdate==$ticket_date;
		}
	}

	if($pass_date)
	{

		$rs = WC()->cart->add_discount( $code );

		$return['alert'] = wc_print_notices(true);
		$return['alert2'] = $return['alert'];
	}else{
		$rs = false;
		WC()->cart->remove_coupon($code);
	}


	if($rs || stripos($return['alert'],'applied successfully')>0 || stripos($return['alert'],'already applied')>0)
	{
		$_SESSION['trcouponcode'] = $code;
		$return['status'] = 'ok';
		$return['alert'] = '<p class="alert success">COUPON CODE APPLIED SUCCESSFULLY.</p>';

	}else{
		$return['alert'] = '<p class="alert error">COUPON "'.$code.'" DOES NOT EXIST!</p>';
		$return['status'] = 'error';
		$_SESSION['trcouponcode'] = '';
	}

	ob_start();
	woocommerce_order_review();
	$return['summary'] = ob_get_clean();
}else{
	$return['status'] = 'error';
}



wp_send_json($return);