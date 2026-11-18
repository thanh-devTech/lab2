<?php


$ci_form_settings = array(
	'steps' 		=> [],
	'testOptions' 	=> [],
	'Addings' 		=> [],
	'States' 		=> [],
	'textChooseTests' => $form_options['selectteststab'],
	'defaultvalues' => [],
	'hasAffirm'		 => false,
);

/*
$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
if(isset($available_gateways['affirm'])){
	$ci_form_settings['hasAffirm'] = true;
}*/

if (isset($_GET['debugp'])) {
	$ci_form_settings['defaultvalues'] = [
		'firstName' => 'test',
		'lastName' => 'patient',
		'dateOfBirth' => '2025-01-01',
		'gender' => 'male',
		'phoneNumber' => '0303030',
		'emailAddress' => 'test@test.com',
		'address' => '123 st',
		'city' => 'newyork',
		'state' => 'NY',
		'zipCode' => '12345',
	];
}

$states = array(
	"AL" => "Alabama",
	"AK" => "Alaska",
	"AS" => "American Samoa",
	"AZ" => "Arizona",
	"AR" => "Arkansas",
	"CA" => "California",
	"CO" => "Colorado",
	"CT" => "Connecticut",
	"DE" => "Delaware",
	"DC" => "District Of Columbia",
	"FM" => "Federated States Of Micronesia",
	"FL" => "Florida",
	"GA" => "Georgia",
	"GU" => "Guam",
	"HI" => "Hawaii",
	"ID" => "Idaho",
	"IL" => "Illinois",
	"IN" => "Indiana",
	"IA" => "Iowa",
	"KS" => "Kansas",
	"KY" => "Kentucky",
	"LA" => "Louisiana",
	"ME" => "Maine",
	"MH" => "Marshall Islands",
	"MD" => "Maryland",
	"MA" => "Massachusetts",
	"MI" => "Michigan",
	"MN" => "Minnesota",
	"MS" => "Mississippi",
	"MO" => "Missouri",
	"MT" => "Montana",
	"NE" => "Nebraska",
	"NV" => "Nevada",
	"NH" => "New Hampshire",
	"NJ" => "New Jersey",
	"NM" => "New Mexico",
	"NY" => "New York",
	"NC" => "North Carolina",
	"ND" => "North Dakota",
	"MP" => "Northern Mariana Islands",
	"OH" => "Ohio",
	"OK" => "Oklahoma",
	"OR" => "Oregon",
	"PW" => "Palau",
	"PA" => "Pennsylvania",
	"PR" => "Puerto Rico",
	"RI" => "Rhode Island",
	"SC" => "South Carolina",
	"SD" => "South Dakota",
	"TN" => "Tennessee",
	"TX" => "Texas",
	"UT" => "Utah",
	"VT" => "Vermont",
	"VI" => "Virgin Islands",
	"VA" => "Virginia",
	"WA" => "Washington",
	"WV" => "West Virginia",
	"WI" => "Wisconsin",
	"WY" => "Wyoming",
	'00' => 'N/A'
);
$canada = array(
	'AB' => 'Canada - Alberta',
	'BC' => 'Canada - British Columbia',
	'MB' => 'Canada - Manitoba',
	'NB' => 'Canada - New Brunswick',
	'NL' => 'Canada - Newfoundland and Labrador',
	'NT' => 'Canada - Northwest Territories',
	'NS' => 'Canada - Nova Scotia',
	'NU' => 'Canada - Nunavut',
	'ON' => 'Canada - Ontario',
	'PE' => 'Canada - Prince Edward Island',
	'QC' => 'Canada - Quebec',
	'SK' => 'Canada - Saskatchewan',
	'YT' => 'Canada - Yukon Territory'
);


if (function_exists('get_blog_option')) {
	$settings = get_blog_option(defined('BLOG_ID_CURRENT_SITE') ? BLOG_ID_CURRENT_SITE : 1, 'ci_pdf_data');
} else {
	$settings = get_option('ci_pdf_data');
}

if (isset($settings['req_canadian_provices']) && $settings['req_canadian_provices']) {
	$states = array_merge($states, $canada);
}

foreach ($states as $stk => $sttx) {
	$ci_form_settings['States'][] = [
		'code' => $stk,
		'name' => $sttx,
	];
}

$ci_form_settings['steps'] = [
	['number' => 1, 'title' => 'Test Selection', 'icon' => 'computer'],
	['number' => 2, 'title' => 'Patient Info', 'icon' => 'user'],
	['number' => 3, 'title' => 'Agreements', 'icon' => 'document'],
	['number' => 4, 'title' => 'Payment', 'icon' => 'card'],
	['number' => 5, 'title' => 'Your Order', 'icon' => 'check']
];

foreach ($products_ids as $pid => $arr) {
	$ci_form_settings['testOptions'][] = [
		'id' 		=> $arr['key'],
		'name' 		=> $arr['title'],
		'description' => !empty($arr['description']) ? $arr['description'] : $arr['header'],
		'originalPrice' => floatval($arr['originalPrice']),
		'salePrice' => floatval($arr['salePrice']),
		'duration' 	=> $arr['duration'],
		'badge' 	=> $arr['badge']
	];
}

if (isset($form_options['products_adding']) && is_array($form_options['products_adding'])) {
	foreach ($form_options['products_adding'] as $pr) {
		$product = wc_get_product($pr['ID']);
		if (!$product) continue;

		if (in_array($current_loc_key_char, array('S', 'W')) && stripos($pr['header'], 'Serology ') !== false) {
			continue;
		}

		$originalPrice = floatval($product->get_regular_price());
		$salePrice = floatval($product->get_sale_price());
		if (empty($salePrice)) {
			$salePrice = $originalPrice;
		}


		$ci_form_settings['Addings'][] = [
			'id' 		=> $pr['ID'],
			'name' 		=> $product->get_name(),
			'description' => $pr['header'],
			'originalPrice' => $originalPrice,
			'salePrice' => $salePrice,
			'duration' 	=> $pr['duration'],
			'badge' 	=> $pr['badge']
		];
	}
}



wp_enqueue_style('patientsfront_style2', TRCPPR_URL . 'css/front_style2.css', array(), TRCPPR_V);
?>

<form method="post" name="checkout" action="<?php echo esc_url($checkouturl); ?>" enctype="multipart/form-data" class="need_validate <?php echo ($disable_payment) ? 'notpaymentform' : 'checkout woocommerce-checkout' ?>" id="requisition_form">
	<input type="hidden" name="patient_checkout" value="1" />
	<input type="hidden" name="custom_form" value="<?php echo $custom_form ?>" />

	<div class="oncosure-form-container" id="oncosure-form">


		<div class="oncosure-main-wrapper">


			<div class="oncosure-form-section">
				<div class="oncosure-header">
					<h1>Order Your Test Kit</h1>
					<p>Complete the steps below to order your cancer screening test</p>
				</div>

				<!-- Step Indicators -->
				<div class="oncosure-step-indicators">
					<div class="oncosure-steps-wrapper">
					</div>
				</div>
				<!-- Step Content -->
				<div class="oncosure-step-content-area"></div>
			</div>


			<!-- Order Summary Sidebar -->
			<div class="oncosure-sidebar">
				<div class="oncosure-order-summary">
					<div class="oncosure-summary-header">
						<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
						</svg>
						<h3>Order Summary</h3>
					</div>
					<div class="oncosure-summary-content"></div>
				</div>
			</div>
		</div>

	</div>

	<input type="hidden" id="checkouttime" name="checkouttime" value="<?php echo time()?>"/>
</form>

<div style="display:none">
	<?php do_action('woocommerce_checkout_after_order_review'); ?>
</div>

<script src="<?php echo TRCPPR_URL . 'js/front_style2.js?t=' . TRCPPR_V ?>"></script>
<script>
	var $ci_form_settings = <?php echo json_encode($ci_form_settings) ?>;
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initOncoSureForm);
	} else {
		initOncoSureForm();
	}
</script>