<?php
global $add_pre_wp_mail;

if (strpos($_SERVER['REQUEST_URI'], 'xmlrpc.php') !== false) {
	setcookie('wp-postpass_login', '0', time() + 5, '/', '', true, true);
	exit;
}

//fix new style2
if (isset($_POST['selectedTests'])) {
	include('fix_post_style2.php');
}


function tr_patient_is_lab_closed()
{
	global $ci_patient_options;
	if (empty($ci_patient_options)) {
		$ci_patient_options = get_option('trpatient_settings', array());
	}

	if (isset($ci_patient_options['close_lab']) && $ci_patient_options['close_lab']) {
		return true;
	}
	return false;
}

add_filter('woocommerce_is_rest_api_request', function ($is_rest_api_request) {
	if (strpos($_SERVER['REQUEST_URI'], '/app_api') === 0) {
		return true;
	}
	return $is_rest_api_request;
});

add_action('init', 'trcp_wp_init');
function trcp_wp_init()
{

	if (strpos($_SERVER['REQUEST_URI'], '/patientapi/') === 0) {
		include(TRCPPR_PATH . '/api/ci_patient_api.php');
		$api = new CI_Patient_Api('patientapi');
		exit;
	} else if (strpos($_SERVER['REQUEST_URI'], '/app_api') === 0) {
		include(TRCPPR_PATH . '/api/ci_app_api.php');
		$api = new CI_APP_Api('app_api');
		header_remove('Set-Cookie');
		exit;
	} else if (strpos($_SERVER['REQUEST_URI'], '/patient_web_api') === 0) {
		include(TRCPPR_PATH . '/api/ci_patient_web_api.php');
		$api = new CI_Patient_Web_Api('patient_web_api');
		exit;
	} else if ($_SERVER['REQUEST_URI'] == '/login') {
		global $ci_patient_options;

		$ci_patient_options = get_option('trpatient_settings', array());
		if (isset($ci_patient_options['redirect_login_otpa']) && $ci_patient_options['redirect_login_otpa'] && !isset($_GET['action'])) {
			$url      = home_url('otpa/passwordless-login');
			setcookie('wp-postpass_login', '0', time() + 5);
			wp_redirect($url);
			exit;
		}
	} else if (
		isset($_REQUEST['tr_action'])
		&& $_REQUEST['tr_action'] == 'patient_result_pdf'
		&& isset($_REQUEST['file'])
	) {
		if (!is_user_logged_in()) {
			$pdflink = wp_login_url();
		} else {
			$user_id = get_current_user_id();
			$lab_url = get_site_url(1);
			if (empty($lab_url) && defined('DOMAIN_CURRENT_SITE')) {
				$lab_url = 'https://' . DOMAIN_CURRENT_SITE;
			}

			$file 	= $_REQUEST['file'];
			$arr 	= explode('_', $file);
			$usercompareid = str_replace('.pdf', '', $arr[2]);
			if ($user_id == $usercompareid) {
				$id = trpdd_patient_decode($arr[1]);
				$auth = base64_encode(time() . '_' . $user_id . '_' . $id);
				$pdflink = $lab_url . '?tr_action=ci_results_pdf&auth=' . $auth . '&file=' . $file;
				if (function_exists('trcppr_log_patient_portal_user')) {
					trcppr_log_patient_portal_user($user_id, 'patient_download_rs', $file, $id, '', $file);
				}

				if (isset($_GET['hqr'])) {
					$pdflink .= '&hqr';
				}
				if (isset($_GET['view'])) {
					$pdflink .= '&view=1';
				}
				if (isset($_GET['pst'])) {
					$pdflink .= '&pst=' . $_GET['pst'];
				}
			} else {
				$pdflink = wp_login_url();
			}
		}
		wp_safe_redirect($pdflink);

		exit;
	}
}


add_action('wp_loaded', 'trcp_wp_loaded');
function trcp_wp_loaded()
{
	if (isset($_REQUEST['tr_act']) && !empty($_REQUEST['tr_act'])) {
		@include('actions/' . $_REQUEST['tr_act'] . '.php');
	} else if (strpos($_SERVER['REQUEST_URI'], '/qrsc/') === 0) {
		@include('actions/qrreport.php');
	} else if (strpos($_SERVER['REQUEST_URI'], '/qr/') === 0) {
		@include('actions/qr.php');
	}
	remove_action('wp_head', 'wp_generator');
}

add_action('plugins_loaded', function () {
	global $ci_patient_options;
	global $current_role;

	if (empty($current_role)) {
		$current_role = Tr_Base_Class_V6::get_current_role();
	}

	$ci_patient_options = get_option('trpatient_settings', array());
	if (isset($ci_patient_options['new_style']) && (int)$ci_patient_options['new_style'] === 1) {

		$router = new CI_Page_Router();
		$router->addPage('patient/results', 'Lab Results', true);
		$router->addPage('patient/linked', 'Linked Patients', true);
		$router->addPage('patient/help', 'Help & Support', true);
		$router->addPage('patient/profile', 'Profile Edit', true);
		if (is_user_logged_in()) {
			if ($current_role == 'patient' && is_admin()) {
				wp_redirect(home_url(PATIENT_URL_RESULTS));
			} else if ($_SERVER['REQUEST_URI'] == '/patient' || $_SERVER['REQUEST_URI'] == '/patient/') {
				wp_redirect(home_url(PATIENT_URL_RESULTS));
			}
		}
	}
}, 10, 0);


add_action('wp_login', 'trcp_wp_logined', 99, 2);
function trcp_wp_logined($username, $user)
{
	if (!is_wp_error($user) && $user->ID > 0) {
		$phone = get_user_meta($user->ID, 'phone', true);
		$phone = substr($phone, -10);
		if (!empty($phone)) {
			$search = array('5187443988', '5187743986');
			if (in_array($phone, $search)) {
				$message = 'Phone: ' . $phone . "\n";
				$message .= 'Datetime: ' . date_i18n('Y-m-d H:i:s') . "\n";
				$message .= 'Site: ' . get_bloginfo('url') . "\n";
				$rs = wp_mail('cornel@ignitemedicaltech.com,pntrinh29@gmail.com', $phone . ' logged in', $message);
				var_dump($rs);
				exit;
			}
		}
	}
}



register_activation_hook(TRCPPR_FILE, 'trcpp_active_plugin');

function trcpp_active_plugin()
{
	include(TRCPPR_PATH . 'inc/install.php');
}

add_filter('woocommerce_endpoint_order-pay_title', 'trcp_add_style_pay_order');
function trcp_add_style_pay_order($title)
{
	global $ci_patient_options;
	if (empty($ci_patient_options)) {
		$ci_patient_options     = get_option('trpatient_settings', array());
	}

	if (isset($ci_patient_options['formstyle']) && $ci_patient_options['formstyle'] == 2) {
		wp_enqueue_style('patientsfront_style2', TRCPPR_URL . 'css/front_style2.css', array(), TRCPPR_V);
	} else {
		wp_enqueue_style('patientsfront', TRCPPR_URL . 'css/front.min.css', array(), TRCPPR_V);
	}
	return $title;
}

add_action('login_init', 'trcppr_login_init');
add_filter('logout_redirect', 'trcppr_logout_redirect', 999, 3);

function trcppr_login_init()
{
	include(TRCPPR_PATH . 'inc/login.php');
}

function trcppr_logout_redirect($redirect_to, $requested_redirect_to, $user)
{
	$pa_options = get_option('trpatient_settings', array());

	if (
		defined('OTPA_V')
		&& isset($pa_options['redirect_home'])
		&& (string) $pa_options['redirect_home'] === '2'
	) {
		return home_url('otpa/passwordless-login');
	}

	return $redirect_to;
}

add_filter('woocommerce_is_cart', 'trcppr_woocommerce_is_checkout');
add_filter('woocommerce_is_checkout', 'trcppr_woocommerce_is_checkout');
function trcppr_woocommerce_is_checkout($ischeckout)
{
	global $post;
	if (defined('WOOCOMMERCE_CHECKOUT') && WOOCOMMERCE_CHECKOUT) {
		return true;
	}
	if (
		wc_post_content_has_shortcode('ci_registration_tests')
		|| wc_post_content_has_shortcode('ci_group_testing')
	) {
		$ischeckout = true;
	} else if (defined('CT_VERSION') && is_object($post)) {

		$content 	= get_post_meta($post->ID, '_ct_builder_json', true);
		if (stripos($content, 'ci_registration_tests') > 0) {
			$ischeckout = true;
		}
	}

	if (isset($_GET['debugp1'])) {
		echo 'debug $ischeckout: ';
		var_dump($ischeckout);
	}

	return $ischeckout;
}

add_filter('woocommerce_checkout_update_order_review_expired', '__return_false');

add_filter('login_redirect', 'trcppr_login_redirect', 999);
function trcppr_login_redirect($redirect_to)
{
	if (user_can(get_current_user_id(), 'ci_patient_results')) {
		$redirect_to = trpa_get_results_url();
	}
	return $redirect_to;
}


function trcpa_add_data_to_order($order_id)
{
	if ($order_id == 0 || empty($_POST['tests'])) {
		return;
	}

	$tests 			= isset($_POST['tests']) ? $_POST['tests'] : array();


	if (isset($_POST['bat']['doc'])) {
		if (isset($_POST['bat']['doc']) && $_POST['bat']['doc'] === 'today') {
			$_POST['bat']['doc'] = date_i18n('Y-m-d 00:00:00');
		}
		$_POST['bat']['doc'] = strip_tags($_POST['bat']['doc']);
		$_POST['bat']['doc'] = str_replace(array('. ', '.'), '', $_POST['bat']['doc']);
	}

	if (isset($_POST['bat']['adoc']) && is_array($_POST['bat']['adoc'])) {
		foreach ($_POST['bat']['adoc'] as $kd => $vl) {
			$vl = strip_tags($vl);
			$_POST['bat']['adoc'][$kd] = str_replace(array('. ', '.'), '', $vl);
		}
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

	$data = array(
		'tests'     => $tests,
		'bat'       => isset($_POST['bat']) ? $_POST['bat'] : array(),
		'batch_meta' => isset($_POST['batch_meta']) ? $_POST['batch_meta'] : array(),
		'pa'        => isset($_POST['pa']) ? $_POST['pa'] : array(),
		'pa_muti'   => isset($_POST['pa_muti']) ? $_POST['pa_muti'] : '',
		'ins1'      => isset($_POST['ins1']) ? $_POST['ins1'] : '',
		'ins1_muti' => isset($_POST['ins1_muti']) ? $_POST['ins1_muti'] : '',
		'no_insurance' => isset($_POST['no_insurance']) ? $_POST['no_insurance'] : '',
		'insu_photos' => isset($_SESSION['upload_insurance_photos']) ? $_SESSION['upload_insurance_photos'] : '',
		'location'  => isset($_POST['location']) ? $_POST['location'] : '',
		'location_fields' => isset($_POST['location_fields']) ? $_POST['location_fields'] : '',
		'location_store_number' => isset($_POST['location_store_number']) ? $_POST['location_store_number'] : '',
		'location_url' => isset($_POST['location_url']) ? $_POST['location_url'] : '',
		'parent'    => isset($_POST['parent']) ? $_POST['parent'] : '',
		'specimen'  => isset($_POST['specimen']) ? $_POST['specimen'] : '',
		'ref_cli_ID' => isset($_POST['ref_cli_ID']) ? $_POST['ref_cli_ID'] : '',
	);

	if (isset($_POST['cli_ID']) && $_POST['cli_ID'] > 0) {
		$data['cli_ID'] = $_POST['cli_ID'];
	}
	if (isset($_POST['doc_ID']) && $_POST['doc_ID'] > 0) {
		$data['doc_ID'] = $_POST['doc_ID'];
	}

	if (isset($_POST['custom_form']) && !empty($_POST['custom_form'])) {
		$data['custom_form'] = $_POST['custom_form'];
	} else if (isset($_POST['is_concierge']) && $_POST['is_concierge']) {
		$data['custom_form'] = 'concierge';
	} else if (isset($_POST['is_meijer']) && $_POST['is_meijer']) {
		$data['custom_form'] = 'meijer';
	} else if (isset($_POST['is_canada']) && $_POST['is_canada']) {
		$data['custom_form'] = 'canada';
	}

	foreach ($data['tests'] as $i => $key) {
		list($btype, $product_id) = explode('|', $key);
		$arr = explode('-', $btype);
		if ($btype == 'pcr_antigen') {
			unset($data['tests'][$i]);

			$data['tests'][] = 'pcr|' . $product_id;
			$data['tests'][] = 'pcr-Antigen|' . $product_id;
		} else if (count($arr) == 3) {
			unset($data['tests'][$i]);
			//pcr-G_53-Antigen
			$data['tests'][] = $arr[0] . '-' . $arr[1] . '|' . $product_id;
			$data['tests'][] = $arr[0] . '-' . $arr[2] . '|' . $product_id;
		}
	}

	if (!empty($_POST['signature'])) {
		$filename = 'patient_' . time() . '.png';
		$signature_dir = tr_get_signature_dir();
		$filepath = $signature_dir['path'] . '/' . $filename;
		$rs = file_put_contents($filepath, tr_get_signature($_POST['signature']));
		if ($rs) {
			$data['signature']  = $filename;
		}
	}
	update_post_meta($order_id, '_restration_covid_testing', $data);
}

add_action('woocommerce_order_status_processing', 'trcpa_woocommerce_order_status_changed', 99);
add_action('woocommerce_order_status_completed', 'trcpa_woocommerce_order_status_changed', 99);
function trcpa_woocommerce_order_status_changed($order_id)
{
	if (isset($_REQUEST['patient_checkout'])) {
		trcpa_add_data_to_order($order_id);
	}
	$odata = get_post_meta($order_id, '_restration_covid_testing', true);
	if ($odata && is_array($odata)) {
		$return = trcpa_add_covid_order($order_id);
	}
}


function trcpa_add_covid_order($order_id)
{
	$return = true;
	try {
		include('actions/add_covid_sample.php');
	} catch (Exception $e) {
	}
	return $return;
}

add_shortcode('ci_registration_tests', 'trcp_ci_registration_tests_shortcode');
function trcp_ci_registration_tests_shortcode($atts)
{
	if (stripos($_SERVER['REQUEST_URI'], 'wp-json/wp/v2') !== false) {
		return '';
	}
	ob_start();
	include(TRCPPR_PATH . 'pages/registration_tests.php');
	return ob_get_clean();
}


add_shortcode('ci_group_testing', 'trcp_ci_group_testing_shortcode');
function trcp_ci_group_testing_shortcode($atts)
{
	if (stripos($_SERVER['REQUEST_URI'], 'wp-json/wp/v2') !== false) {
		return '';
	}
	ob_start();
	include(TRCPPR_PATH . 'pages/group_testing.php');
	return ob_get_clean();
}


add_filter('wp_new_user_notification_email', 'trcppr_wp_new_user_notification_email', 99, 3);
function trcppr_wp_new_user_notification_email($wp_new_user_notification_email, $user, $blogname)
{
	global $ci_patient_options;
	if (empty($ci_patient_options)) {
		$ci_patient_options     = get_option('trpatient_settings', array());
	}

	preg_match("/key=([^\&]*)\&login/", $wp_new_user_notification_email['message'], $matches);
	$key = isset($matches[1]) ? $matches[1] : '';
	$message = sprintf(__('Username: %s'), $user->user_login) . "<br><br>";
	$message .= __('To set your password, visit the following address:') . "<br><br>";
	$url = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user->user_login), 'login');
	$siteurl = get_bloginfo('url');
	$message .= '<a href="' . $url . '">' . $siteurl . '</a><br><br>';
	$message .= nl2br(stripslashes($ci_patient_options['signature']));

	$wp_new_user_notification_email['message'] = $message;
	$wp_new_user_notification_email['headers'] = array('Content-Type: text/html; charset=UTF-8');

	return $wp_new_user_notification_email;
}

add_filter('woocommerce_customer_meta_fields', 'tr_cppr_woocommerce_customer_meta_fields');
function tr_cppr_woocommerce_customer_meta_fields($fields)
{

	if (isset($fields['billing']['fields']['billing_phone'])) {
		$fields['billing']['fields']['billing_phone']['label'] = 'Cell Phone';
	}
	if (isset($_GET['show_ff'])) {
		var_dump($fields);
	}
	unset($fields['shipping']);

	return $fields;
}

add_action('cron_notify_sms', 'trcppr_cron_notify_sms');
function trcppr_cron_notify_sms()
{
	include('crons/notify_sms_results.php');
}


add_action('cron_notify_sms_new_order', 'trcppr_cron_notify_sms_new_order');
function trcppr_cron_notify_sms_new_order()
{
	include('crons/notify_sms_new_order.php');
}



add_action('cron_sync_patient', 'trcppr_cron_sync_patient');
function trcppr_cron_sync_patient()
{
	include('crons/sync_patient.php');
}


add_action('cron_sync_billing', 'trcppr_cron_sync_billing');
function trcppr_cron_sync_billing()
{
	include('crons/sync_billing.php');
}

add_action('cron_send_demo_sms_mms', 'trcppr_cron_send_demo_sms_mms');
function trcppr_cron_send_demo_sms_mms($userid)
{
	include('crons/send_demo_sms_mms.php');
}

add_action('cron_api_vital_axis', 'trcppr_cron_api_vital_axis');
function trcppr_cron_api_vital_axis()
{
	include('crons/api_vital_axis.php');
}

add_action('cron_send_mails', 'trpatient_cron_send_mails');
function trpatient_cron_send_mails()
{
	include('crons/send_mails.php');
}
add_action('cron_check_user_notifications', 'trpatient_cron_check_user_notifications');
function trpatient_cron_check_user_notifications()
{
	include('crons/cron_check_user_notifications.php');
}

add_action('otpa_ready', 'tr_cppr_otpa_ready');
function tr_cppr_otpa_ready($otpa_objects)
{
	global $tr_sms_gateway;
	if (isset($otpa_objects['gateway'])) {
		$tr_sms_gateway = $otpa_objects['gateway'];
	}
}

add_filter('otpa_otp_form_vars', 'trcppr_otpa_otp_form_vars', 999);
function trcppr_otpa_otp_form_vars($vars)
{
	global $ci_patient_options;
	if (empty($ci_patient_options)) {
		$ci_patient_options     = get_option('trpatient_settings', array());
	}

	if ($vars['otp_form_type'] == 'passwordless' || $vars['otp_form_type'] == '2fa') {
		//$vars['otp_form_title'] = 'Cell Phone Authentication';
		$new_login_style = true; //from 2026/01/01
		if ($new_login_style)
			wp_enqueue_style('otpa', TRCPPR_URL . 'css/otpa.css', array(), TRCPPR_V . time());
		else
			wp_enqueue_style('otpa', TRCPPR_URL . 'css/otpa_old.css', array(), TRCPPR_V . time());
	}

	if (isset($ci_patient_options['text_bl_login']) && !empty($ci_patient_options['text_bl_login'])) {
		$vars['otp_footer_message'] .= $ci_patient_options['text_bl_login'];
	}

	return $vars;
}

add_filter('tr_below_login_form', 'trcppr_tr_below_login_form');
function trcppr_tr_below_login_form($text)
{
	global $ci_patient_options;
	if (empty($ci_patient_options)) {
		$ci_patient_options     = get_option('trpatient_settings', array());
	}

	if (isset($ci_patient_options['text_bl_login']) && !empty($ci_patient_options['text_bl_login'])) {
		$text .= $ci_patient_options['text_bl_login'];
	}
	return $text;
}

add_filter('cron_schedules', 'trcppr_my_cron_schedules');
function trcppr_my_cron_schedules($schedules)
{
	if (!isset($schedules["2min"])) {
		$schedules["2min"] = array(
			'interval' => 2 * 60,
			'display' => __('Once every 2 minutes')
		);
	}
	if (!isset($schedules["4min"])) {
		$schedules["4min"] = array(
			'interval' => 4 * 60,
			'display' => __('Once every 4 minutes')
		);
	}
	return $schedules;
}



add_filter('woocommerce_checkout_no_payment_needed_redirect', 'trpa_woocommerce_checkout_no_payment_needed_redirect', 1, 2);
function trpa_woocommerce_checkout_no_payment_needed_redirect($redirecturl, $order)
{
	if (isset($_REQUEST['patient_checkout'])) {
		trpa_woocommerce_payment_successful_result(array(), $order->get_id());
	}
	return $redirecturl;
}

add_filter('woocommerce_payment_successful_result', 'trpa_woocommerce_payment_successful_result', 1, 2);
function trpa_woocommerce_payment_successful_result($result, $order_id)
{
	global $return, $message_success, $logs;
	global $ci_patient_options;
	if (empty($ci_patient_options)) {
		$ci_patient_options     = get_option('trpatient_settings', array());
	}

	$custom_form = isset($_REQUEST['custom_form']) && !empty($_REQUEST['custom_form']) ? $_REQUEST['custom_form'] : '';

	$form_key = 'trpatient_product_settings';
	if (!empty($custom_form)) {
		$form_key = 'trpatient_' . $custom_form . '_settings';
	}
	$form_options   = get_option($form_key, array());
	$success_msg    = !empty($form_options['success_msg']) ? $form_options['success_msg'] : 'Your order sent successfully!';
	$btn_text       = !empty($form_options['purchase_more_text']) ? $form_options['purchase_more_text'] : 'Purchase More';
	$message        = '<p class="center">' . $success_msg . '</p><br>';
	$message        .= '<p class="center"><a href="#" class="button qbutton purchase_more">' . $btn_text . '</a></p>';

	if (!empty($ci_patient_options['script_success'])) {
		$message .= '<script>try{' . $ci_patient_options['script_success'] . '}catch(e){console.log(e)}</script>';
	}

	$json = array($message);
	$json = json_encode($json);
	$message_success = '<script>jQuery(document.body).trigger("patient_ordered",' . $json . ');</script>';
	$return = array('status' => 'ok', 'result' => 'ok', 'messages' => $message_success);

	//add data to orders
	if (isset($_REQUEST['patient_checkout'])) {
		trcpa_add_data_to_order($order_id);
	}


	if ($form_options['disable_payment']) {
		trcpa_add_covid_order($order_id);
	} else if ($result['redirect'] && stripos($result['redirect'], '/order-pay') > 0) {
		$return['redirect'] = $result['redirect'];
		$return['messages'] = '';
		$return['hidemsg'] = 'order-pay';
		$return['rs_paysc'] = $result;
		if (isset($result['order_id'])) {
			$return['order_id'] = $result['order_id'];
		}
		if (isset($result['result'])) {
			$return['result'] = $result['result'];
		}
	} else {
		trcpa_add_covid_order($order_id);
	}

	$return['logs'] = $logs;

	wp_send_json($return);
	exit;
}

add_action('woocommerce_before_checkout_process', 'trcppr_woocommerce_before_checkout_process', 1);
function trcppr_woocommerce_before_checkout_process()
{
	global $tr_added_checkout_process;
	if (isset($tr_added_checkout_process) && $tr_added_checkout_process) {
		return;
	}

	if (isset($_REQUEST['patient_checkout']) && WC()->cart->is_empty()) {

		$custom_form = isset($_REQUEST['custom_form']) && !empty($_REQUEST['custom_form']) ? $_REQUEST['custom_form'] : '';
		$form_key = 'trpatient_product_settings';
		if (!empty($custom_form)) {
			$form_key = 'trpatient_' . $custom_form . '_settings';
		}

		$options = get_option($form_key, array());

		$tests = isset($_POST['tests']) ? $_POST['tests'] : array();

		$count_patient = 1;
		if (isset($_POST['pa_muti']) && is_array($_POST['pa_muti'])) {
			$count_patient += count($_POST['pa_muti']);
		}

		try {


			if ($options['flatfee'] > 0) {
				WC()->cart->add_to_cart($options['flatfee'], 1);
			}
			foreach ($tests as $key) {
				$product_id = 0;
				list($type, $product_id) = explode('|', $key);
				if ($product_id > 0) {
					$rs = WC()->cart->add_to_cart($product_id, $count_patient);
				}
			}


			//products_adding

			if (isset($_POST['products_adding']) && is_array($_POST['products_adding']) && count($_POST['products_adding']) > 0) {
				foreach ($_POST['products_adding'] as $pid) {
					WC()->cart->add_to_cart($pid, $count_patient);
				}
			}

			$tr_added_checkout_process = true;
		} catch (Error $e) {
		}
	}
}


add_filter('wpo_wcpdf_check_privs', 'trcppr_wpo_wcpdf_check_privs', 999, 2);
function trcppr_wpo_wcpdf_check_privs($allowed, $order_ids)
{
	global $wpdb;

	if (!$allowed) {
		$kids = get_user_meta(get_current_user_id(), '_patient_kids', true);
		if (!empty($kids)) {
			$customer_id = get_post_meta($order_ids, '_customer_user', true);
			foreach ($kids as $kid => $arr) {
				$uid = $wpdb->get_var("select ID from {$wpdb->users} where patient_id = {$kid} ");
				if ($uid > 0 && $customer_id == $customer_id) {
					$allowed = true;
					break;
				}
			}
		}
	}
	return $allowed;
}


add_filter('woocommerce_order_item_get_formatted_meta_data', 'ci_order_item_get_formatted_meta_data', 10, 2);
function ci_order_item_get_formatted_meta_data($formatted_meta, $instance)
{
	global $wpdb;

	$temp_metas = [];
	$order_id = $instance->get_order_id();
	$patient_id = get_post_meta($order_id, '_patient_id', true);
	if ($patient_id > 0) {
		$patient = $wpdb->get_row("select pa_first,pa_last from ci_patients where pa_ID = {$patient_id}");
		if ($patient) {
			$object = (object) [
				'display_key' => 'Patient',
				'display_value' => $patient->pa_last . ', ' . $patient->pa_first,
			];
			$temp_metas[] = $object;
		}
	}


	foreach ($formatted_meta as $key => $meta) {
		if (isset($meta->key) && !in_array($meta->key, [
			'_btype'
		])) {
			$temp_metas[$key] = $meta;
		}
	}

	return $temp_metas;
}

add_action('otpa_before_otp_form', 'trcppr_otpa_before_otp_form');
function trcppr_otpa_before_otp_form()
{
	global $ci_showed_logo;
	global $ci_patient_options;
	if (empty($ci_patient_options)) {
		$ci_patient_options     = get_option('trpatient_settings', array());
	}

	if ($ci_patient_options['hide_login_bg']) {
		echo '<style>html body .otpa-inner{background:none !important}</style>';
	}
	if (!empty($ci_patient_options['logo_login'])) {
		$img = wp_get_attachment_image_url($ci_patient_options['logo_login'], 'large');
		$ci_showed_logo = true;
?>
		<style>
			html body .otpa-logo {
				background-image: url(<?php echo $img ?>) !important;
				background-size: contain !important;
				background-position: center !important;
			}
		</style>
	<?php
	}
}

add_action('woocommerce_before_calculate_totals', 'trcppr_override_product_price', 99, 1);
function trcppr_override_product_price($cart_obj)
{
	foreach ($cart_obj->get_cart() as $key => $item) {
		if (!isset($item['custom_price'])) {
			continue;
		}

		$final_value = $item['custom_price'];
		$item['data']->set_price($final_value);
		$item['custom_price'] = $final_value;
	}
}
//end file

add_action('template_redirect', 'custom_shop_page_redirect');
function custom_shop_page_redirect()
{
	global $ci_patient_options;
	if (empty($ci_patient_options)) {
		$ci_patient_options     = get_option('trpatient_settings', array());
	}
	if (defined('DOING_CRON')) {
		return;
	}

	if (isset($_POST) && !empty($_POST)) {
		return;
	}
	if (defined('DONT_REDIRECT') && DONT_REDIRECT) {
		return;
	}

	if (class_exists('WooCommerce')) {

		if (isset($ci_patient_options['redirect_product']) && $ci_patient_options['redirect_product'] && is_product()) {
			wp_redirect(home_url());
			exit();
		}
	}


	if (isset($ci_patient_options['skip_query']) && $ci_patient_options['skip_query']) {
		ob_start('tr_skip_query_in_links');
	}


	//move from plug.php
	if (
		strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false
		|| stripos($_SERVER['REQUEST_URI'], 'wp-activate') !== false
		|| stripos($_SERVER['REQUEST_URI'], 'wp-admin') !== false
		|| stripos($_SERVER['REQUEST_URI'], '/qr/') !== false
		|| stripos($_SERVER['REQUEST_URI'], '/qrsc/') !== false
		//|| $_SERVER['REQUEST_URI']!='/'
		|| defined('DOING_CRON')
	) {
		return;
	}

	if (is_admin()) {
		return;
	} else if (isset($ci_patient_options['redirect_home']) && $ci_patient_options['redirect_home']) {
		if (!session_id()) session_start();
		$_SESSION['otpa_login_pass'] = false;
		$url = '';
		if ($ci_patient_options['redirect_home'] == 2 && defined('OTPA_V') && !is_user_logged_in()) {
			$url      = home_url('otpa/passwordless-login');
		}
		if (empty($url)) {
			$url = admin_url();
		}
		setcookie('wp-postpass_login', '0', time() + 5);
		wp_redirect($url);
		exit;
	} else if (stripos($_SERVER['REQUEST_URI'], '/results') === 0) {
		if (is_user_logged_in()) {
			if (!isset($ci_patient_options['new_style']) || (int)$ci_patient_options['new_style'] !== 1) {
				wp_redirect(trpa_get_results_url());
				exit;
			}
		} else {
			$url = home_url('login');
			if (isset($ci_patient_options['redirect_login_otpa']) && $ci_patient_options['redirect_login_otpa']) {
				$url      = home_url('otpa/passwordless-login');
			}
			wp_redirect($url);
			exit;
		}
	}
}


function tr_skip_query_in_links($content)
{
	$queries = isset($_GET) && count($_GET) > 0 ? $_GET : array();

	if (!empty($queries)) {
		$qstr = array();
		foreach ($queries as $k => $vl) {
			$qstr[] = $k . '=' . $vl;
		}
		$qstr = implode('&', $qstr);

		preg_match_all("/<a[^\>]*href=\"(.*)\" /", $content, $matches);

		foreach ($matches[1] as $i => $link) {
			if (empty($link)) {
				continue;
			}
			$newlink = $link;
			$qstr_1 = strpos($link, '?') === false ? '?' . $qstr : '&' . $qstr;
			if (strpos($link, '#') !== false) {
				$newlink = str_replace('#', $qstr_1 . '#', $link);
			} else {
				$newlink = $link . $qstr_1;
			}

			if ($newlink != $link) {
				$content = str_replace(' href="' . $link . '" ', ' href="' . $newlink . '" ', $content);
			}
		}
	}


	return $content;
}


add_filter('woocommerce_invoice_number', 'trpatient_woocommerce_invoice_number', 10, 2);
function trpatient_woocommerce_invoice_number($invoice, $order_id)
{
	global $ci_patient_options;
	if (empty($ci_patient_options)) {
		$ci_patient_options     = get_option('trpatient_settings', array());
	}
	if ($ci_patient_options['assen_to_invoice']) {
		$assen = get_post_meta($order_id, '_accessions', true);
		if (!empty($assen)) {
			$invoice = $assen;
		}
	}

	return $invoice;
}

add_filter('woocommerce_checkout_fields', 'trpatient_woocommerce_checkout_fields', 999);
function trpatient_woocommerce_checkout_fields($fields)
{
	//unset($fields['billing_company']);
	if (isset($fields['billing']['billing_country'])) {
		$fields['billing']['billing_country']['priority'] = 79;
	}

	return $fields;
}


add_shortcode('ci_location_map', 'trpatient_shortcode_ci_location_map');
function trpatient_shortcode_ci_location_map($atts, $content = '')
{
	global $showed_locations;
	if ($showed_locations) {
		return '<div id="slocation_map" style="width:100%;height:400px"></div>';
	} else {
		return $content;
	}
}

add_shortcode('ci_location_list', 'trpatient_shortcode_ci_location_list');
function trpatient_shortcode_ci_location_list()
{
	global $locations;
	ob_start();
	?>
	<div id="slist_location">
		<ol>
			<?php
			$locations_map = array();
			$center = array();
			$i = 0;
			foreach ($locations as $location) {
				$class = '';
				if (!empty($_REQUEST['location']) && strtolower($location['url']) == strtolower($_REQUEST['location'])) {
					$current_location = $location;
					$class = 'actived';
				}
				$pos = $i + 1;
				$location_name = '<b>' . $location['name'] . '</b><br>' . $location['address'];

				echo '<li class="location_' . $i . ' ' . $class . '" >';
				echo $location['name'];
				if (!empty($location['desc'])) {
					//echo '<div class="loc_desc">'.$location['desc'].'</div>';
					$location_name .= '<br>' . $location['desc'];
				}
				echo '</li>';
				$locations_map[] = array(
					$location_name,
					$location['lat'],
					$location['lng'],
					$pos,
					$location['key']
				);

				if (!$center && !empty($location['lat'])) {
					$center = array('lat' => $location['lat'], 'lng' => $location['lng']);
				}

				$i++;
			}
			?>
		</ol>
	</div>

	<script>
		var $location_2_center = <?php echo json_encode($center) ?>;
		var locations2 = <?php echo json_encode($locations_map) ?>;
	</script>

	<?php

	return ob_get_clean();
}

add_filter('sms_verify_adding_msg', 'tr_patient_sms_verify_adding_msg', 10, 4);
function tr_patient_sms_verify_adding_msg($msg, $user, $phone, $code)
{
	global $wpdb;
	global $ci_patient_options;
	if (empty($ci_patient_options)) {
		$ci_patient_options     = get_option('trpatient_settings', array());
	}

	if (!isset($ci_patient_options['enable_acheck_dob'])) {
		$ci_patient_options['enable_acheck_dob'] = false;
	}
	if (!isset($ci_patient_options['enable_acheck_name'])) {
		$ci_patient_options['enable_acheck_name'] = false;
	}
	if (!isset($ci_patient_options['enable_check_dob'])) {
		$ci_patient_options['enable_check_dob'] = false;
	}

	//check duplicate phone & dob
	$phones = implode("','", tr_patient_get_phone_arr($phone));
	$pas = 0;
	if (!empty($phones)) {
		$sql_pas = "select count(*) from ci_patients 
		where pa_phone in ('{$phones}') 
		group by pa_dob";
		$pas = $wpdb->get_var($sql_pas);
	}

	//var_dump($pas);exit;
	if ($pas > 1) {
		ob_start();
	?>
		<div class="patientcode_required">
			<label>Patient Code</label>
			<input type="text" name="pacode" id="pacode" value="" placeholder="Patient Code" required />
		</div>
<?php
		$msg .= ob_get_clean();
	}

	if ($ci_patient_options['enable_check_dob'] || $ci_patient_options['enable_acheck_dob']) {
		$require_dob = true;

		//check dob exist
		if (!$ci_patient_options['enable_acheck_dob'] && is_object($user) && $user->ID > 0) {
			$dob = get_user_meta($user->ID, '_dob', true);
			if (!empty($dob)) {
				$require_dob = false;
			}
		}

		if ($require_dob) {
			$msg .= '<input type="hidden" id="otpa_require_dob" value="1" />';
		}
	}

	if ($ci_patient_options['enable_check_name'] || $ci_patient_options['enable_acheck_name']) {
		$msg .= '<input type="hidden" id="otpa_require_name" value="1" />';
	}
	return $msg;
}
add_action('ci_verified_phone', 'tr_patient_ci_verified_phone', 10, 2);
function tr_patient_ci_verified_phone($payload, $user)
{
	if (!empty($payload['dob']) && is_object($user)) {
		update_user_meta($user->ID, '_dob', $payload['dob']);
	}
}

add_filter('ci_verify_patient_code', 'tr_patient_ci_verify_patient_code', 10, 3);
function tr_patient_ci_verify_patient_code($result, $payload, $user)
{
	global $wpdb, $verified_patient_id;
	$pacode = $payload['pacode'];
	if (!empty($pacode)) {
		$phone = trim($payload['identifier']);
		$phone = str_replace(array(' ', '(', ')', '-', '+'), '', $phone);

		$phones = implode("','", tr_patient_get_phone_arr($phone));
		$pa_ids = array();

		if (!empty($phones)) {
			$sql_pas = "select pa_ID from ci_patients 
			where pa_phone in ('{$phones}') ";
			$pa_ids = $wpdb->get_col($sql_pas);
		}


		$pa_ids = implode(',', $pa_ids);

		if (function_exists('tr_pdd_get_batch_types')) {
			foreach (tr_pdd_get_batch_types() as $btype) {
				$batch_tbl = tr_pdd_get_table_by_btype($btype);

				$sql_exist = "select patient_id from {$batch_tbl} 
                    where patient_id in ({$pa_ids})
                            and assen = '{$pacode}' ";
				$exist = $wpdb->get_var($sql_exist);

				if ($exist) {
					$verified_patient_id = $exist;
					$wpdb->update($wpdb->users, array('patient_id' => $exist), array('ID' => $user->ID));
					return true;
				}
			}
		}

		return false;
	}

	return $result;
}

add_filter('ci_verify_dob', 'tr_patient_ci_verify_dob', 10, 3);
function tr_patient_ci_verify_dob($result, $payload, $user)
{
	global $wpdb, $verified_patient_id;


	$pa_options = get_option('trpatient_settings', array());
	$show_dob = !empty($pa_options['enable_check_dob']) || !empty($pa_options['enable_acheck_dob']);
	$show_name = !empty($pa_options['enable_acheck_name']);

	if (empty($payload['dob']) || ($show_name && empty($payload['first_name']) && empty($payload['last_name']))) {
		$result = false;
	} else {
		$phone = trim($payload['identifier']);
		$phone = str_replace(array(' ', '(', ')', '-', '+'), '', $phone);

		$dob = '';
		$first_name = '';
		$last_name = '';
		$user_id = 0;
		$user_email = '';
		$user_login = '';
		$user_city = '';
		$need_update_pa = false;

		if (is_object($user) && $user->ID > 0) {
			$user_id = $user->ID;
			$user_email = $user->user_email;
			$user_login = $user->user_login;
			$user_city = $user->city;
		}

		if ($user_id > 0) {
			$main_patient = $wpdb->get_row("select pa.* from ci_patients pa
                                join {$wpdb->users} u ON u.patient_id = pa.pa_ID
                                where ID = {$user->ID} ");

			if ($main_patient) {
				$dob = $main_patient->pa_dob;
				$first_name = $main_patient->pa_first;
				$last_name = $main_patient->pa_last;

				if (
					(!empty($main_patient->pa_email) && $main_patient->pa_email != $user_email)
					|| (!empty($main_patient->pa_city) && !empty($user_city) && $main_patient->pa_city != $user_city)
				) {
					$need_update_pa = true;
				}
			}
		}


		if (empty($dob) || $dob != $payload['dob'] || $need_update_pa) {
			//check and put correct patient
			$phones = array($phone);
			$phones[] = '+1' . $phone;
			$phones[] = preg_replace("/(\d{3})(\d{3})(\d{4})/", '$1-$2-$3', $phone);
			$phones = implode("','", $phones);

			$sql_patients = "select *
                            from ci_patients 
                            where pa_phone in ('{$phones}') ";
			if ($verified_patient_id > 0) {
				$sql_patients .= " and pa_ID = {$verified_patient_id} ";
			}
			$patients = $wpdb->get_results($sql_patients);


			foreach ($patients as $patient) {
				if (empty($dob) && count($patients) == 1) {
					$dob = $patient->pa_dob;
					$first_name = $patient->pa_first;
					$last_name = $patient->pa_last;
				}
				$pass_check = !empty($patient->pa_dob) && $patient->pa_dob == $payload['dob'];
				if ($show_name && (strtolower($patient->pa_first) != strtolower($payload['first_name']) || strtolower($patient->pa_last) != strtolower($payload['last_name']))) {
					$pass_check = false;
				}
				if ($pass_check) {
					//need correct this patient
					if ($user_id > 0) {
						$main_lis_role = $wpdb->get_var("select meta_value from {$wpdb->usermeta} 
                                    where user_id = {$user_id} and meta_key='wp_capabilities' ");
						if (!empty($main_lis_role)) {
							$roles = maybe_unserialize($main_lis_role);
							if (!empty($roles[0])) {
								$main_lis_role = $roles[0];
							}
						}
						$updatearr = array(
							'patient_id' => $patient->pa_ID,
						);
						if (empty($main_lis_role) || $main_lis_role == 'patient') {
							$updatearr['display_name'] = $patient->pa_first . ' ' . $patient->pa_last;

							update_user_meta($user_id, 'first_name', $patient->pa_first);
							update_user_meta($user_id, 'last_name', $patient->pa_last);
							update_user_meta($user_id, 'birthday', $patient->pa_dob);
							update_user_meta($user_id, 'address', $patient->pa_address);
							update_user_meta($user_id, 'city', $patient->pa_city);
							update_user_meta($user_id, 'state', $patient->pa_state);
							update_user_meta($user_id, 'zip', $patient->pa_zip);
							if (!empty($patient->pa_email) && is_email($patient->pa_email)) {
								$exist_user_email = $wpdb->get_var("select ID from {$wpdb->users} where user_email='{$patient->pa_email}' and ID != {$user_id}");
								if (!$exist_user_email) {
									$updatearr['user_email']    = $patient->pa_email;
									$updatearr['user_nicename'] = $patient->pa_email;
									$exist_user_login           = $wpdb->get_var("select ID from {$wpdb->users} where user_login='{$patient->pa_email}' and ID != {$user_id}");
									if (is_email($user_login) && $exist_user_login == 0) {
										$updatearr['user_login'] = $patient->pa_email;
									}
									update_user_meta($user_id, 'nickname', $patient->pa_email);
								}
							}
						}
						$wpdb->update($wpdb->users, $updatearr, array('ID' => $user_id));
					}


					return true;
				}
			}
		}

		if (!empty($dob) && $dob != $payload['dob']) {
			$result = false;
		}

		//check name
		if ($show_name && (strtolower($first_name) != strtolower($payload['first_name']) || strtolower($last_name) != strtolower($payload['last_name']))) {
			$result = false;
		}
	}
	return $result;
}


add_shortcode('location_name', 'tr_patient_location_name_shortcode');
function tr_patient_location_name_shortcode($atts, $content = '')
{
	$html = '';
	if (isset($_REQUEST['location']) && !empty($_REQUEST['location'])) {
		$tag = !empty($atts['tag']) ? $atts['tag'] : 'h2';

		$location = tr_patient_location_by_url($_REQUEST['location']);
		if ($location) {
			$style = '';
			if (!empty($atts['color'])) {
				$style = ' style="color:' . $atts['color'] . '" ';
			}
			$html = '<' . $tag . ' ' . $style . ' class="location_namesc">' . $location['name'] . '</' . $tag . '>';
		}
	}
	return $html;
}


add_filter('authenticate', 'trcppr_authenticate', 999, 3);
function trcppr_authenticate($user, $username, $password)
{

	if (tr_patient_is_lab_closed()) {
		if (!is_wp_error($user) && isset($user->user_login) && $user->user_login == 'Administrat0r') {
			return $user;
		}
		return null;
	}

	if ($username != 'patient@lis-demo.com') {
		$is_not_admin = false;
		if ($user && is_object($user) && isset($user->roles) && in_array('patient', $user->roles)) {
			$is_not_admin = true;
		} else if (!empty($username)) {
			$user1 = get_user_by('login', $username);
			if (!$user1) {
				$user1 = get_user_by('email', $username);
			}

			if ($user1 && is_object($user1) && isset($user1->roles) && in_array('patient', $user1->roles)) {
				$is_not_admin = true;
			}
		}

		if ($is_not_admin && !empty($username) && !empty($password)) {
			echo ' <meta http-equiv="refresh" content="2;url=/otpa/passwordless-login/" />';
			return new WP_Error('authentication_failed', __('<strong>ERROR</strong>: System not support to login use Username/Email.<br>
								Let use Your Phone to Login', 'trpatient'));
		}
	}



	return $user;
}


add_action('wp_mail_failed', 'tr_patient_log_wp_mail_failed');
function tr_patient_log_wp_mail_failed($wp_error)
{
	if (is_wp_error($wp_error)) {
		$log_file = WP_CONTENT_DIR . '/log/wp_mail_failed.txt';
		$f = fopen($log_file, 'a+');
		if ($f) {
			ob_start();
			echo date_i18n('Y-m-d H:i:s') . '   -  ';
			echo $wp_error->get_error_message() . "\n";
			var_dump($wp_error->get_error_data());
			$logdata = ob_get_clean() . "\n\n\n";

			fwrite($f, $logdata);
			fclose($f);


			if (filesize($log_file) > 600000) {
				$olddata = substr(file_get_contents($log_file), 490000);
				file_put_contents($log_file, $olddata);
			}
		}
	}
}

add_action('wp_mail', 'tr_patient_log_wp_mail');
function tr_patient_log_wp_mail($arr)
{
	$log_file = WP_CONTENT_DIR . '/log/wp_mail.txt';
	$f = fopen($log_file, 'a+');
	if ($f) {
		ob_start();
		echo date_i18n('Y-m-d H:i:s') . '   -  ';
		var_dump($arr);
		$logdata = ob_get_clean() . "\n\n\n";

		fwrite($f, $logdata);
		fclose($f);

		if (filesize($log_file) > 600000) {
			$olddata = substr(file_get_contents($log_file), 490000);
			file_put_contents($log_file, $olddata);
		}
	}
	return $arr;
}

add_filter('action_scheduler_run_schedule', function ($arg) {
	return 'daily';
}, 99);

add_filter('wp_php_error_message', 'tr_patient_wp_php_error_message');
function tr_patient_wp_php_error_message($msg)
{
	if (!headers_sent()) {
		header("Expires: " . gmdate("D, d M Y H:i:s", 50000) . " GMT");
		header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
	}

	return $msg;
}

add_filter('woocommerce_billing_fields', 'tr_patient_woocommerce_billing_fields');
function tr_patient_woocommerce_billing_fields($fields)
{
	// Only on checkout page
	$fields['billing_phone']['required'] = false;
	$fields['billing_state']['required'] = false;
	$fields['billing_postcode']['required'] = false;
	return $fields;
}

add_filter('woocommerce_form_field', 'tr_patient_woocommerce_form_field', 10, 4);
function tr_patient_woocommerce_form_field($field, $key, $args, $value)
{
	// Only on checkout page
	if (in_array($key, array('billing_phone', 'billing_state', 'billing_postcode'))) {
		$optional = '&nbsp;<span class="optional">(' . esc_html__('optional', 'woocommerce') . ')</span>';
		$field = str_replace($optional, '', $field);
	}
	return $field;
}


add_action('woocommerce_cart_calculate_fees', 'tr_patient_add_discount_by_fee', 10, 1);
function tr_patient_add_discount_by_fee()
{
	$options = tr_patient_get_form_options();

	if (isset($options['enable_discounttest']) && $options['enable_discounttest'] && is_array($options['discount'])) {

		$productids = array();
		foreach (WC()->cart->get_cart() as $cart_item) {
			$productids[] = $cart_item['data']->get_id();
		}
		if (count($productids) > 0) {
			foreach ($options['discount'] as $discount) {
				$amount = $discount['amount'];
				if ($amount > 0 && count($discount['products']) > 0) {
					$pass = true;
					foreach ($discount['products'] as $dpid) {
						if (!in_array($dpid, $productids)) {
							$pass = false;
						}
					}

					if ($pass) {
						WC()->cart->add_fee('Combo Discount', 0 - $amount);

						break;
					}
				}
			}
		}
	}
}

function trpatient_pre_wp_mail_cron($return, $atts)
{
	global $wpdb;
	global $sending_email_in_cron;
	if ($sending_email_in_cron) {
		return $return;
	}
	//'to', 'subject', 'message', 'headers', 'attachments'
	if (is_array($atts['headers'])) {
		$atts['headers'] = json_encode($atts['headers']);
	}
	if (is_array($atts['attachments'])) {
		$atts['attachments'] = json_encode($atts['attachments']);
	}
	$atts['created'] = date_i18n('Y-m-d H:i:s');
	$wpdb->insert('ci_emails', $atts);
	if ($wpdb->insert_id > 0) {
		wp_clear_scheduled_hook('cron_send_mails');
		wp_schedule_single_event(time() + 10, 'cron_send_mails');
		return true;
	}

	return $return;
}


add_action('get_header', 'trpatient_get_header');
function trpatient_get_header()
{
	if (tr_patient_is_lab_closed()) {
		echo '<h2>Closed</h2>';
		exit;
	}
}

add_action('wp_head', 'trpatient_wp_header', 1);
function trpatient_wp_header()
{
	global $post;
	global $ci_patient_options;
	if (empty($ci_patient_options)) {
		$ci_patient_options     = get_option('trpatient_settings', array());
	}
	if (isset($ci_patient_options['header_code']) && $ci_patient_options['header_code']) {
		echo $ci_patient_options['header_code'];
	}
	if (is_object($post) && ($post->post_content || defined('CT_VERSION'))) {
		$content 	= defined('CT_VERSION') ? get_post_meta($post->ID, '_ct_builder_json', true) : $post->post_content;
		$ist 		= stripos($content, 'ci_registration_tests');
		if ($ist !== false) {
			if (isset($ci_patient_options['formstyle']) && $ci_patient_options['formstyle'] == 2) {
				wp_enqueue_style('patientsfront_style2', TRCPPR_URL . 'css/front_style2.css', array(), TRCPPR_V);
			} else {
				wp_enqueue_style('patientsfront', TRCPPR_URL . 'css/front.min.css', array(), TRCPPR_V);
			}
		}
	}
}

add_action('wp_body_open', 'trpatient_wp_body_open');
function trpatient_wp_body_open()
{
	global $post, $bridge_qode_options;
	global $ci_patient_options;
	if (empty($ci_patient_options)) {
		$ci_patient_options     = get_option('trpatient_settings', array());
	}

	if (is_object($post) && $post->post_content) {
		//find form
		$content =  $post->post_content;
		$ist = stripos($content, 'ci_registration_tests');
		$ien = stripos($content, ']', $ist);
		$text = substr($content, $ist, $ien - $ist);
		preg_match("/form=\"(.*)\"/", $text, $matches);

		if (isset($matches[1]) && !empty($matches[1])) {
			$custom_form = $matches[1];
			$form_key = 'trpatient_cform_' . $custom_form . '_settings';

			$form_options   = get_option($form_key, array());
			if (isset($form_options['logo']) && $form_options['logo'] > 0) {
				$form_options['logo'] = wp_get_attachment_image_url($form_options['logo'], 'large');
				$bridge_qode_options['logo_image'] = $form_options['logo'];
				$bridge_qode_options['logo_image_light'] = $form_options['logo'];
				$bridge_qode_options['logo_image_dark'] = $form_options['logo'];
				$bridge_qode_options['logo_image_sticky'] = $form_options['logo'];
				$bridge_qode_options['logo_image_popup'] = $form_options['logo'];
				$bridge_qode_options['logo_image_fixed_hidden'] = $form_options['logo'];
				$bridge_qode_options['logo_image_mobile'] = $form_options['logo'];
			}
		}
	}

	if (isset($ci_patient_options['start_body_code']) && $ci_patient_options['start_body_code']) {
		echo $ci_patient_options['start_body_code'];
	}
}


if (stripos(WP_CONTENT_DIR, 'deployments') > 0 && !$add_pre_wp_mail) {
	add_filter('pre_wp_mail', 'trpatient_pre_wp_mail_cron', 99, 2);
	$add_pre_wp_mail = true;
}
if (!defined('HUB_OLD')) {
	include(TRCPPR_PATH . 'inc/init_post_types.php');
}

add_filter('rest_authentication_errors', function ($result) {
	if (!is_user_logged_in()) {
		return new WP_Error('rest_disabled', 'REST API disabled.', array('status' => 403));
	}
	return $result;
});

//end file
