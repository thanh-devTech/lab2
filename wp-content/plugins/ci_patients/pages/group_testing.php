<?php
global $wpdb;

if(isset($_GET['post']) && $_GET['post']>0)
{
	return;
}
if(isset($_GET['wc-ajax']) || defined('DOING_AJAX'))
{
	return;
}

wp_enqueue_script('datedropdown', TRCPPR_URL.'js/jquery.date-dropdowns.min.js',array(),TRCPPR_V);

wp_enqueue_script('parentfront', TRCPPR_URL.'js/front.min.js',array(),TRCPPR_V.time());
wp_enqueue_style('patientsfront', TRCPPR_URL.'css/front.min.css',array(), TRCPPR_V);

$pa_options = get_option('trpatient_settings',array());

$user_id = get_current_user_id();
$patient_id = tr_patient_get_id_patient($user_id);

$patient = array();

//add to cart
$product = null;

try {
	if($pa_options['g_testing_pid']>0)
	{
		$product = wc_get_product($pa_options['g_testing_pid']);
		if($product)
		{
			$price_txt = $product->get_price_html();
			if(WC()->cart->is_empty())
			{
				$rs = WC()->cart->add_to_cart($pa_options['g_testing_pid']);
				if(!$rs)
				{
					wc_print_notices();
					echo 'Error while setup page. Please try again later!';
					return;
				}
			}

		}
	}
}catch(Error $e){
	var_dump($e);
}
$step_count = 1;

global $ultraadmin;
if(!empty($form_options['acc_bg_color']) || !empty($form_options['acc_bg_hover_color']) || !empty($ultraadmin['accordion-bg'])){

	if(empty($form_options['acc_bg_color']))
	{
		$form_options['acc_bg_color'] = $ultraadmin['accordion-bg'];
	}
	if(empty($form_options['acc_text_color']))
	{
		$form_options['acc_text_color'] = $ultraadmin['accordion-text'];
	}
	if(empty($form_options['acc_content_color']))
	{
		$form_options['acc_content_color'] = $ultraadmin['accordion-content'];
	}
	if(empty($form_options['acc_bg_hover_color']))
	{
		$form_options['acc_bg_hover_color'] = $form_options['acc_bg_color'];
	}
	if(empty($form_options['acc_text_hover_color']))
	{
		$form_options['acc_text_hover_color'] = $form_options['acc_text_color'];
	}
	?>
	<style>
		body .accordions .accordion-section-title, body .accordions .active .accordion-section-title,
		body .accordions .button:hover,body .accordions .button.samebar, body .accordions .pass .accordion-section-title:hover{
			background:<?php echo $form_options['acc_bg_color'];?>;
			color:<?php echo $form_options['acc_text_color'];?>;
			border-color:<?php echo $form_options['acc_bg_color'];?>;
		}
		body .accordion-section-content{
			color:<?php echo $form_options['acc_content_color'];?>;
		}
		body .accordions .accordion-section-title:hover, body .accordions .active .accordion-section-title,
		body .accordions .button{
			background:<?php echo $form_options['acc_bg_hover_color'];?>;
			color:<?php echo $form_options['acc_text_hover_color'];?>;
			border-color:<?php echo $form_options['acc_bg_hover_color'];?>;
		}
	</style>
	<?php
}

$checkouturl = '';
if(function_exists('wc_get_checkout_url')){
	$checkouturl = wc_get_checkout_url();
}
?>

	<div class="wrap_form wrap">

		<form method="post" name="checkout" action="<?php echo esc_url( $checkouturl ); ?>"  enctype="multipart/form-data" class="need_validate checkout woocommerce-checkout" id="requisition_form">
			<input type="hidden" name="group_testing" value="1"/>
			<div class="accordions">
				<div data-step="<?php echo $step_count++?>" class="accordion-section selecttests">
					<div class="accordion-section-title">Price </div>
					<div class="accordion-section-content " style="text-align: center">
						<table style="border:none;margin:0 auto" class="btypetbl outinsurance">
							<tr>
								<td colspan="2" class="center">
									<label>
									<?php echo $product->get_name()?> &nbsp;&nbsp</label>
								</td>
							</tr>
							<tr>
								<td>Price: </td>
								<td>
									<input type="number" step="1" min="1" value="<?php echo $pa_options['g_testing_pid_df_pr']?>" name="custom_price" required/>
								</td>
							</tr>
						</table>

						<div class="nextarea" style="margin-top:20px;text-align: right">
							<a class="gotostep button qbutton small" data-step="2" href="#">Next</a>
						</div>
					</div>
				</div>


				<div data-step="<?php echo $step_count++?>" class="accordion-section checkout">
					<div class="accordion-section-title">Payment </div>
					<div class="accordion-section-content">
						<div id="checkout_section"></div>
					</div>
				</div>

				<div data-step="10" class="accordion-section">
					<div class="accordion-section-title">Order Detail </div>
					<div class="accordion-section-content">
						<div id="order_section"></div>
					</div>
				</div>

			</div>

		</form>


	</div>

<style>
    p#billing_company_field{
        display: block !important;
    }
</style>
<?php

if(is_admin())
{
	$wc_url = plugins_url( '/', WC_PLUGIN_FILE );
	wp_enqueue_script('wc-checkout',$wc_url.'assets/js/frontend/checkout.min.js',array( 'jquery', 'woocommerce', 'wc-country-select', 'wc-address-i18n' ));
	do_action('wp_enqueue_scripts');
	do_action('wp_print_scripts');
	do_action('wp_print_footer_scripts');
}

