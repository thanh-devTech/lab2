<?php
global $wpdb;

if (!defined('WOOCOMMERCE_CHECKOUT')) {
	define('WOOCOMMERCE_CHECKOUT', true);
}
$return = array(
	'status' => 'ok',
	'html' => '',
	'scripts' => '',
	'coupon_code' => '',
	'total' => 0,
	'checkouttime' => time(),
	'hasAffirm' => false
);

$options = tr_patient_get_form_options();
$pa_options = get_option('trpatient_settings', array());

$tests 			= isset($_POST['tests']) ? $_POST['tests'] : array();
if (!is_array($tests)) {
	$tests = array();
}
$custom_price 	= isset($_POST['custom_price']) ? $_POST['custom_price'] : 0;
$style			= isset($_REQUEST['style']) ? $_REQUEST['style'] : 1;
$coupon_code	= isset($_REQUEST['coupon_code']) ? $_REQUEST['coupon_code'] : '';


$is_group_testing = isset($_POST['group_testing']);
$is_insurance 	= false;
$count_patient 	= 1;
if (isset($_POST['pa_muti']) && is_array($_POST['pa_muti'])) {
	$count_patient += count($_POST['pa_muti']);
}


try {
	if (is_admin()) {
		WC()->frontend_includes();
		wc_load_cart();
	}
	WC()->cart->empty_cart();
} catch (Exception $e) {
	var_dump($e);
}


if (!empty($coupon_code)) {
	$code = wc_format_coupon_code(wp_unslash($coupon_code));
	$code = trim($code);

	//check $code by date
	$pass_date = true;
	$coupon_id = $wpdb->get_var("select ID from {$wpdb->posts} where post_title='{$code}'");
	if ($coupon_id > 0) {
		$limitdate = get_post_meta($coupon_id, '_ticket_date', true);

		if (!empty($limitdate)) {
			$limitdate = date('Y-m-d', strtotime($limitdate));
			$pass_date = $limitdate == $ticket_date;
		}
	}

	if ($pass_date) {

		$rs = WC()->cart->add_discount($code);
		if (!$rs) {
			$code = '';
		}
		$return['coupon_code'] = $code;

		$return['coupon_code_alert'] = wc_print_notices(true);
	} else {
		$rs = false;
		WC()->cart->remove_coupon($code);
		$return['coupon_code'] = '';
	}
} else {
	$return['coupon_code'] = '';
}

if ($is_group_testing) {

	try {
		if ($pa_options['g_testing_pid'] > 0) {
			$rs = WC()->cart->add_to_cart($pa_options['g_testing_pid'], $count_patient, 0, array(), array('custom_price' => $custom_price));
			if (!$rs) {
				wc_print_notices();
				echo 'Error while setup page. Please try again later!';
				return;
			}
		}
	} catch (Error $e) {
		var_dump($e);
	}
} else {
	if (empty($tests)) {
		echo 'empty tests';
		exit;
	}

	try {

		$productids = array();
		foreach ($tests as $key) {
			list($type, $product_id, $paytype, $quantitytest) = explode('|', $key);

			$productids[] = $product_id;

			$product = wc_get_product($product_id);
			if ($product) {
				$price = get_post_meta($product_id, '_regular_price', true);
				if ($price === '') {
					update_post_meta($product_id, '_regular_price', '0');
				}
				$secondup_price = '';
				if ($count_patient > 1) {
					$secondup_price = get_post_meta($product_id, '_secondup_price', true);
				}

				if ($secondup_price !== '' && !is_null($secondup_price)) {
					$quantity = 1;
					if ($quantitytest > 0) {
						$quantity = $quantitytest;
					}
					$rs = WC()->cart->add_to_cart($product_id, $quantity);
					if ($secondup_price > 0) {
						$rs = WC()->cart->add_to_cart($product_id, $count_patient - 1, 0, array(), array('custom_price' => floatval($secondup_price)));
					}
				} else {
					$quantity = $count_patient;
					if ($quantitytest > 0) {
						$quantity = $quantitytest * $count_patient;
					}
					$rs = WC()->cart->add_to_cart($product_id, $quantity);
				}
				if (!$rs) {
					wc_print_notices();
					echo 'Error while setup page. Please try again later!';
					return;
				}
			}

			if (!$is_insurance && !empty($options['insurance_text'])) {
				foreach ($options['products_insurance'] as $arr) {
					if ($product_id == $arr['ID']) {
						$is_insurance = true;
					}
				}
			}
		}
	} catch (Error $e) {
		var_dump($e);
		exit;
	}

	add_filter('woocommerce_checkout_get_value', 'trajax_func_woocommerce_checkout_get_value', 99, 2);
	function trajax_func_woocommerce_checkout_get_value($value, $field)
	{
		$pa = $_POST['pa'];
		$fields = array(
			'billing_first_name' => 'pa_first',
			'billing_last_name' => 'pa_last',
			'billing_address_1' => 'pa_address',
			'billing_city' => 'pa_city',
			'billing_state' => 'pa_state',
			'billing_postcode' => 'pa_zip',
			'billing_phone' => 'pa_phone',
			'billing_email' => 'pa_email',
		);
		if (isset($fields[$field])) {
			$paf = $fields[$field];
			if (empty($value)) {
				$value = $pa[$paf];
			}
		}

		if ($field == 'billing_country') {
			$canada_states = array(
				'AB' => 'Alberta',
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
				'YT' => 'Yukon'
			);
			if (isset($canada_states[$pa['pa_state']])) {
				$value = 'CA';
			} else {
				$value = 'US';
			}
		}


		return $value;
	}
}


if ($options['flatfee'] > 0) {
	WC()->cart->add_to_cart($options['flatfee'], 1);
}

//products_adding

if (isset($_POST['products_adding']) && is_array($_POST['products_adding']) && count($_POST['products_adding']) > 0) {
	if (is_array($options['products_adding']) && count($options['products_adding']) > 0) {
		foreach ($_POST['products_adding'] as $pid) {
			WC()->cart->add_to_cart($pid, 1);
		}
	}
}


do_action('woocommerce_check_cart_items');
WC()->cart->calculate_totals();

// Get checkout object.
$checkout = WC()->checkout();

$return['total'] =  floatval(WC()->cart->get_total('number'));
$return['discount'] =  floatval(WC()->cart->get_discount_total('number'));

ob_clean();
ob_start();

if ($style == 2) {



?>
	<div class="oncosure-payment-methods">
		<h4>Payment Method *</h4>

		<?php if (WC()->cart->needs_payment()) : ?>
			<ul class="wc_payment_methods payment_methods methods">
				<?php
				$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
				WC()->payment_gateways()->set_current_gateway($available_gateways);
				if (! empty($available_gateways)) {

					foreach ($available_gateways as $ggname => $gateway) {

						if ($ggname == 'affirm') {
							$return['hasAffirm'] = true;
						}
						wc_get_template('checkout/payment-method.php', array('gateway' => $gateway));
					}
				} else {
					echo '<li>';
					wc_print_notice(apply_filters('woocommerce_no_available_payment_methods_message', WC()->customer->get_billing_country() ? esc_html__('Sorry, it seems that there are no available payment methods. Please contact us if you require assistance or wish to make alternate arrangements.', 'woocommerce') : esc_html__('Please fill in your details above to see available payment methods.', 'woocommerce')), 'notice'); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
					echo '</li>';
				}
				?>
			</ul>
		<?php endif; ?>
	</div>


	<?php wp_nonce_field('woocommerce-process_checkout', 'woocommerce-process-checkout-nonce'); ?>
	<?php do_action('woocommerce_checkout_after_order_review'); ?>

<?php
} else {
?>

	<div id="checkout_alert"></div>
	<div id="checkout_alert2"></div>
	<?php
	//do_action( 'woocommerce_before_checkout_form', $checkout );
	if (!empty($options['insurance_text']) && $is_insurance) {
		echo '<div class="center">' . $options['insurance_text'] . '</div>';
	}
	?>
	<div class="col2-set" id="customer_details">
		<div class="col-1">
			<?php do_action('woocommerce_checkout_billing'); ?>
		</div>

		<div class="col-2">
			<?php do_action('woocommerce_checkout_shipping'); ?>
		</div>
	</div>
	<h3 id="order_review_heading"><?php esc_html_e('Your order', 'woocommerce'); ?></h3>

	<?php do_action('woocommerce_checkout_before_order_review'); ?>

	<div id="order_review" class="woocommerce-checkout-review-order">
		<?php
		echo '<div id="order_review_table">';
		woocommerce_order_review();
		echo '</div>';
		?>
		<?php if (wc_coupons_enabled()) { ?>
			<div class="coupon_alert"></div>
			<div class="coupon coupon_div">
				<label for="coupon_code"><?php esc_html_e('Coupon:', 'woocommerce'); ?></label> <input type="text" name="coupon_code" class="input-text" id="coupon_code" value="" placeholder="<?php esc_attr_e('Coupon code', 'woocommerce'); ?>" /> <button type="submit" class="button" name="apply_coupon" value="<?php esc_attr_e('Apply coupon', 'woocommerce'); ?>"><?php esc_attr_e('Apply coupon', 'woocommerce'); ?></button>
				<?php do_action('woocommerce_cart_coupon'); ?>
			</div>
		<?php } ?>
		<?php
		woocommerce_checkout_payment();
		//do_action( 'woocommerce_checkout_order_review' ); 
		?>
	</div>
<?php
}


$countries = get_option('woocommerce_specific_allowed_countries', array());
if (is_array($countries) && count($countries) > 1) {
	echo '<style>body #billing_country_field{display:block}</style>';
}


$return['html'] = ob_get_clean();

$return['html'] = str_replace('class="button', 'class="button sbutton', $return['html']);
$return['html'] = str_replace('checked=\'checked\'', 'n', $return['html']);
$return['html'] = str_replace('>Phone', '>Cell Phone', $return['html']);

ob_start();

do_action('wp_enqueue_scripts');
do_action('wp_print_footer_scripts');
$return['scripts'] = ob_get_clean();

$return['scripts']  = preg_replace("/<script[^>]*src=\"https[^\"]+\"[^>]*><\/script>/", '', $return['scripts']);
$return['scripts']  = preg_replace("/<script id=\"wc-country-select-js-extra\">[^<]+<\/script>/", '', $return['scripts']);
$return['scripts']  = preg_replace("/<script id=\"wc-address-i18n-js-extra\">[^<]+<\/script>/", '', $return['scripts']);
$return['scripts']  = preg_replace("/<style[^>]+>[^<]+<\/style>/", '', $return['scripts']);
$return['scripts']  = preg_replace("/<link[^>]*>/", '', $return['scripts']);

wp_send_json($return);
