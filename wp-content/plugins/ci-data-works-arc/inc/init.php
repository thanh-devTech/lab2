<?php
if (strpos($_SERVER['REQUEST_URI'], 'xmlrpc.php') !== false || strpos($_SERVER['REQUEST_URI'], '/wsdl') === 0) {
	$hdr = file_get_contents("php://input");
	if (isset($_REQUEST['wsdl']) || isset($_REQUEST['WSDL']) || isset($_REQUEST['PHPSOAPCLIENT']) || isset($_REQUEST['data-server']) || !empty($hdr)) {
		include(TRCPDD_PATH . 'inc/server.php');
		exit;
	} else if (isset($_GET['demo'])) {
		include(TRCPDD_PATH . 'demo_xml/demo_xml.php');
		exit;
	}
} else if (isset($_REQUEST['wsdl_funcs'])) {
	include(TRCPDD_PATH . 'inc/wsdl_funcs.php');
	exit;
} else if (strpos($_SERVER['REQUEST_URI'], '/resultapi/') === 0) {
	include(TRCPDD_PATH . '/api/ci_result_api.php');
	$api = new CI_Result_Api('resultapi');
	exit;
} else if (strpos($_SERVER['REQUEST_URI'], '/reopenlabs/') === 0) {
	include(TRCPDD_PATH . '/api/ci_reopenlabs.php');
	$api = new CI_ReopenLabs_Api('reopenlabs');
	exit;
} else if (strpos($_SERVER['REQUEST_URI'], '/powerbi/') === 0) {
	include(TRCPDD_PATH . '/api/ci_powerbi.php');
	$api = new CI_PowerBI_Api('powerbi');
	exit;
} else if (strpos($_SERVER['REQUEST_URI'], '/jsonapi/') === 0) {
	include(TRCPDD_PATH . '/api/ci_json_api.php');
	$api = new CI_Json_Api('jsonapi');
	exit;
} else if (strpos($_SERVER['REQUEST_URI'], '/medhiwa/') === 0) {
	include(TRCPDD_PATH . '/api/resful_medhiwa_api.php');
	$api = new Resful_Medhiwa_api('medhiwa');
	exit;
} else if (strpos($_SERVER['REQUEST_URI'], '/provista/') === 0) {
	include(TRCPDD_PATH . '/api/resful_provista_api.php');
	$api = new Resful_Provista_api('provista');
	exit;
} else if (strpos($_SERVER['REQUEST_URI'], '/cynergy') === 0) {
	include(TRCPDD_PATH . '/api/ci_api_cynergy.php');
	$api = new CI_API_Cynergy();
	$api->callback();
	exit;
} else if (tr_get_option('ci_pdf_data', 'api_genovation') && strpos($_SERVER['REQUEST_URI'], '/genovation-callback') === 0) {
	include(TRCPDD_PATH . '/api/ci_api_genovation.php');
	$api = new CI_Api_Genovation();
	$api->callback();
	exit;
} else if (strpos($_SERVER['REQUEST_URI'], '.pdf') > 0 && strpos($_SERVER['REQUEST_URI'], 'pdfresult/') > 0) {
	global $wpdb;
	//get id
	$arr = explode('pdfresult/', $_SERVER['REQUEST_URI']);
	list($btype, $id, $keymd5) = explode('/', $arr[1]);
	$nkey = substr(md5($btype . $id), 2, 7);
	if ($nkey != $keymd5) {
		echo 'not found';
		exit;
	}
	$filename = basename($_SERVER['REQUEST_URI']);

	$outfile = trpdd_export_results_pdf($id, '', true, $btype);

	//var_dump($outfile);exit;
	header('Content-Type: application/pdf');
	header('Content-Disposition: attachment; filename="' . $filename . '"');

	readfile($outfile);
	exit;
} else if (strpos($_SERVER['REQUEST_URI'], '/wp-json') === 0) {
	setcookie('wp-postpass_login', '0', time() + 5);
	if (strpos($_SERVER['REQUEST_URI'], '/wp-json/user') === 0) {
		exit;
	}
} else if (strpos($_SERVER['REQUEST_URI'], '/callback/') === 0) {
	$args 	= explode('?', str_replace('/callback/', '', $_SERVER['REQUEST_URI']));
	$args 	= explode('/', $args[0]);
	$class 	= isset($args[0]) ? $args[0] : '';
	$act 	= isset($args[1]) ? $args[1] : '';
	$filepath = TRCPDD_PATH . '/api/ci_api_' . $class . '.php';

	if (file_exists($filepath)) {
		include($filepath);
		$class = 'CI_Api_' . ucfirst($class);
		$api = new $class();
		$callbackact = !empty($act) ? 'callback_' . $act : 'callback';
		if (method_exists($api, $callbackact)) {
			$api->{$callbackact}();
		}
	}

	exit;
}


register_activation_hook(TRCPDD_FILE, 'trpdd_plugin_activate');

function trpdd_plugin_activate()
{
	include(TRCPDD_PATH . 'inc/install.php');
	include(TRCPDD_PATH . 'inc/install_roles.php');
}

add_action('init', 'trpdd_force_logout_disabled_role_user', 1);
function trpdd_force_logout_disabled_role_user()
{
	if (!is_user_logged_in() || (defined('DOING_CRON') && DOING_CRON)) {
		return;
	}

	$user = wp_get_current_user();
	if (!$user || is_wp_error($user) || empty($user->ID) || !trpdd_user_has_disabled_role($user)) {
		return;
	}

	trpdd_log_user($user->ID, 'logout_disabled_role', implode(',', (array) $user->roles));
	wp_logout();

	$is_ajax = (function_exists('wp_doing_ajax') && wp_doing_ajax()) || (defined('DOING_AJAX') && DOING_AJAX);
	if ($is_ajax) {
		wp_send_json_error('Your role is disabled for this site.', 403);
	}

	wp_safe_redirect(add_query_arg('disabled_role', '1', wp_login_url()));
	exit;
}


add_action('init', 'trpdd_data_init', 9);
function trpdd_data_init()
{
	global $ci_all_btypes;
	//load batch types
	$ci_all_btypes 	= tr_pdd_get_batch_types();

	if (defined('DOING_CRON')) {

		if (tr_get_option('ci_pdf_data', 'close_lab')) {
			echo 'Site Closed';
			exit;
		}

		if (isset($argv[0]) && $argv[0] == 'iscronjob') {
			$cronlogfile = WP_CONTENT_DIR . '/log/cronjob.txt';
			file_put_contents($cronlogfile, date_i18n('Y-m-d H:i:s') . "\n", FILE_APPEND);
			if (filesize($cronlogfile) > 100000) {
				file_put_contents($cronlogfile, substr(file_get_contents($cronlogfile), 90000));
			}
		}
		return;
	} else if (isset($_REQUEST['trupdate'])) {
		return;
	}


	if (stripos($_SERVER['REQUEST_URI'], '/results_robots') !== false) {
		include(trpdd_get_file_path('global', 'results_for_robots'));
		exit;
	}

	if (isset($_REQUEST['code'])) {
		if (isset($_SERVER['HTTP_REFERER']) && stripos($_SERVER['HTTP_REFERER'], 'ringcentral') > 0) {
			try {
				include_once(TRCPDD_PATH . 'api/ci_fax_ringcentral.php');
				$ring = CI_Fax_RingCentral::getObject();
				$rs = $ring->authorize_code($_GET['code']);
				if ($rs === true) {
					Tr_Session_Class::add('Authentication Successfully');
				} else {
					Tr_Session_Class::add_error('Authentication Error: ' . (string)$rs);
				}
			} catch (Exception $e) {
				Tr_Session_Class::add_error('Error: ' . $rs['error_description']);
			}
			$url = get_bloginfo('home') . '/wp-admin/admin.php?page=ci_pdf_data&pagetab=ci_wsdl_keys&tab=fax_sms&subtab=api_ringcentral';
			$url = str_replace('https://', 'http://', $url);
			wp_redirect($url);
			exit;
		} else if ((isset($_GET['auth']) && $_GET['auth'] == 'dropbox') || (isset($_SERVER['HTTP_REFERER']) && stripos($_SERVER['HTTP_REFERER'], 'dropbox.com') > 0 && empty($_REQUEST['page']))) {
			$url = get_bloginfo('home') . '/wp-admin/admin.php?page=ci_pdf_data&pagetab=ci_wsdl_keys&tab=other&subtab=api_dropbox&trajax=1&code=' . $_REQUEST['code'];
			$url = str_replace('https://', 'http://', $url);
			wp_redirect($url);
			exit;
		} else if (isset($_SERVER['HTTP_REFERER']) && stripos($_SERVER['HTTP_REFERER'], 'box.com') > 0 && empty($_REQUEST['page'])) {
			try {
				$box = new CI_Box_Works(false, true);
				$rs = $box->authorize($_GET['code']);

				if ($rs === -1) {
					//done
				} else if ($rs->headers->code == 200) {
					Tr_Session_Class::add('Authentication Successfully');
				} else {
					Tr_Session_Class::add_error('Authentication Error: ' . $rs->response['error_description']);
				}
			} catch (Exception $e) {
				Tr_Session_Class::add_error('Error: ' . $rs->response['error_description']);
			}

			$url = get_bloginfo('url') . '/wp-admin/admin.php?page=ci_pdf_data&pagetab=ci_wsdl_keys&tab=box';
			$url = str_replace('https://', 'http://', $url);
			wp_redirect($url);
			exit;
		}
	}


	if (!is_admin()) {
		if (
			tr_get_option('ci_pdf_data', 'close_lab')
			&& !isset($_COOKIE['trquery_monitor'])
			&& !isset($_GET['trquery_monitor'])
			&& !isset($_GET['ci_admin'])
		) {

			echo 'Site Closed!';
			exit;
		} else if (
			tr_get_option('ci_pdf_data', 'redirect_front')
			&& $_SERVER['REQUEST_URI'] === '/'
			&& stripos($_SERVER['REQUEST_URI'], 'wp-json') === false
			&& (!isset($_REQUEST['REQUEST_METHOD']) || strtoupper($_REQUEST['REQUEST_METHOD']) != 'POST')
		) {
			wp_redirect(admin_url('/'));
			exit;
		}
	} else {
		if (strpos($_SERVER['REQUEST_URI'], 'ci_connect_box') > 0 && !empty($_REQUEST['code']) && !is_user_logged_in()) {
			$url = get_bloginfo('home') . '/wp-admin/admin.php?page=ci_pdf_data&pagetab=ci_connect_box&code=' . $_REQUEST['code'];
			$url = str_replace('https://', 'http://', $url);
			wp_redirect($url);
			exit;
		}
	}

	if (class_exists('WooCommerce') && (!defined('TRPDD_PATIENT_SITE') || !TRPDD_PATIENT_SITE)) {
		include('init_woocommerce.php');
	}
}



add_action('login_redirect', 'trpdd_login_redirect', 99, 3);
function trpdd_login_redirect($redirect_to, $requested_redirect_to, $user)
{
	if (!trpdd_is_ci_page($redirect_to)) {
		$redirect_to = admin_url('index.php?page=welcome');
	}

	return $redirect_to;
}

add_action('tr_change_role', 'trpdd_user_register_assign_site', 10, 2);
function trpdd_user_register_assign_site($user_id, $user_role)
{
	global $wpdb, $blog_id;
	if ($user_role == 'customer' || empty($user_role)) {
		return;
	}
	$lab_id = CI_Base_Lab::get_user_lab_id($user_id);

	if ($lab_id > 0) {
		$site_id = $wpdb->get_var("select lab_site from ci_labs where lab_ID = {$lab_id}");
		if ($site_id > 0 && function_exists('add_user_to_blog')) {
			add_user_to_blog($site_id, $user_id, $user_role);
		}
	}

	if (
		get_user_meta($user_id, '_access_all_site', true)
		&& function_exists('add_user_to_blog')
		&& function_exists('get_sites')
	) {
		$sites = get_sites(array('fields' => 'ids'));
		foreach ($sites as $siteid) {
			add_user_to_blog($siteid, $user_id, $user_role);
		}
	}
}

add_filter('authenticate', 'tr_pdd_filter_authenticate', 999, 3);
function tr_pdd_filter_authenticate($user, $username = '', $password = '')
{
	global $wpdb;

	if ($user && !is_wp_error($user) && tr_get_option('ci_pdf_data', 'close_lab') && $user->user_login != 'Administrat0r') {
		$user = false;
	}

	if ($user && !is_wp_error($user) && $user->ID > 0) {
		if (trpdd_user_has_disabled_role($user)) {
			trpdd_log_user($user->ID, 'login_disabled_role', implode(',', (array) $user->roles));
			return new WP_Error('disabled_role', 'This role is disabled for this site.');
		}

		$last_login = get_user_meta($user->ID, '_current_login', true);
		if (empty($last_login)) {
			$last_login = date('Y-m-d H:i:s', strtotime('-2days'));
		}

		update_user_meta($user->ID, '_last_login', $last_login);
		update_user_meta($user->ID, '_current_login', date('Y-m-d H:i:s'));

		trpdd_log_user($user->ID, 'login', 'success');
	} else {
		if (
			is_wp_error($user) &&
			(isset($user->errors['invalid_username'])
				|| isset($user->errors['captcha'])
				|| isset($user->errors['admin_login_user'])
				|| isset($user->errors['max_login_user']))
		) {
			return $user;
		}
		$uid = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->users} WHERE user_login = %s", $username));
		if ($uid > 0) {
			if (strtolower($username) == 'administrator') {
				$password = '';
			}
			trpdd_log_user($uid, 'loginfail', $password);
		}
	}
	return $user;
}
add_filter('logout_redirect', 'tr_pdd_logout_redirect', 99, 3);
function tr_pdd_logout_redirect($redirect_to, $requested_redirect_to, $user)
{
	trpdd_log_user($user->ID, 'logout', 'success');
	return $redirect_to;
}


add_filter('wpmu_validate_user_signup', 'tr_pdd_wpmu_validate_user_signup');
function tr_pdd_wpmu_validate_user_signup($result)
{
	if (
		strtolower(trim($result['user_name'])) == strtolower(trim($result['user_email']))
		&& is_object($result['errors'])
	) {
		$result['errors']->remove('user_name');
		if (username_exists($result['user_name'])) {
			//check blogid
			$user = get_user_by('email', $result['user_email']);
			if ($user && empty($user->roles[0])) {

				$user->add_role($_POST['role']);
				$result['errors']->add('user_name', 'User already exists! You can login now');
			} else {
				$result['errors']->add('user_name', __('Sorry, that username already exists!'));
			}
		}
	}
	return $result;
}


add_filter('login_url', 'tr_pdd_login_url');
function tr_pdd_login_url($url)
{

	if (isset($_REQUEST['id'])) {
		$args = array();
		$args['id'] = sanitize_text_field(wp_unslash($_REQUEST['id']));
		$url = add_query_arg($args, $url);
	}

	return $url;
}

add_filter('network_site_url', 'tr_pdd_network_site_url', 10, 3);
function tr_pdd_network_site_url($url, $path = '', $scheme = null)
{
	if (strpos($path, 'wp-login.php') !== false) {
		$args = array();
		if (isset($_REQUEST['id'])) {
			$args['id'] = sanitize_text_field(wp_unslash($_REQUEST['id']));
		}
		$url = add_query_arg($args, site_url($path, $scheme));
	}
	return $url;
}

add_action('add_admin_bar_menus', 'tr_pdd_add_admin_bar_menus');
function tr_pdd_add_admin_bar_menus()
{
	remove_action('admin_bar_menu', 'wp_admin_bar_my_account_item', 7);
	add_action('admin_bar_menu', 'tr_pdd_wp_admin_bar_my_account_item', 7);
}
function tr_pdd_wp_admin_bar_my_account_item($wp_admin_bar)
{
	$user_id      = get_current_user_id();
	$current_user = wp_get_current_user();

	if (!$user_id)
		return;

	if (current_user_can('read')) {
		$profile_url = get_edit_profile_url($user_id);
	} elseif (is_multisite()) {
		$profile_url = get_dashboard_url($user_id, 'profile.php');
	} else {
		$profile_url = false;
	}

	$avatar = get_avatar($user_id, 26);
	$howdy  = sprintf(__('Welcome, %1$s'), $current_user->display_name);
	$class  = empty($avatar) ? '' : 'with-avatar';

	$wp_admin_bar->add_menu(array(
		'id'        => 'my-account',
		'parent'    => 'top-secondary',
		'title'     => $howdy . $avatar,
		'href'      => $profile_url,
		'meta'      => array(
			'class'     => $class,
		),
	));
}

add_shortcode('ci_form_pgx', 'trpdd_shortcode_form_pgx');
function trpdd_shortcode_form_pgx($atts, $content)
{
	$_hide_title = true;
	ob_start();
	include(TRCPDD_PATH . 'pages/admin_form_pgx_supplies.php');
	return ob_get_clean();
}

add_shortcode('ci_form_blood_supplies', 'trpdd_shortcode_form_blood_supplies');
function trpdd_shortcode_form_blood_supplies($atts, $content)
{
	$_hide_title = true;
	ob_start();
	include(TRCPDD_PATH . 'pages/admin_form_blood_supplies.php');
	return ob_get_clean();
}

add_filter('wp_mail_from', 'tr_filter_wp_mail_from');
function tr_filter_wp_mail_from($email)
{
	if (stripos($email, 'wordpress@') !== false) {
		$email = get_bloginfo('admin_email');
	}
	return $email;
}

add_filter('gettext', 'trpdd_filter_gettext');
function trpdd_filter_gettext($text)
{
	return str_ireplace(array('wordpress.org', 'Wordpress'), array('ignitemedicaltech.com', 'igniteLIS'), $text);
}

add_filter('cron_schedules', 'tr_pdd_cron_schedules');
function tr_pdd_cron_schedules($schedules)
{
	if (!isset($schedules["1min"])) {
		$schedules["1min"] = array(
			'interval' => 60,
			'display' => __('Once every 1 minute')
		);
	}
	if (!isset($schedules["2min"])) {
		$schedules["2min"] = array(
			'interval' => 2 * 60,
			'display' => __('Once every 2 minutes')
		);
	}
	if (!isset($schedules["5min"])) {
		$schedules["5min"] = array(
			'interval' => 5 * 60,
			'display' => __('Once every 5 minutes')
		);
	}
	if (!isset($schedules["10min"])) {
		$schedules["10min"] = array(
			'interval' => 10 * 60,
			'display' => __('Once every 10 minutes')
		);
	}
	if (!isset($schedules["15min"])) {
		$schedules["15min"] = array(
			'interval' => 15 * 60,
			'display' => __('Once every 15 minutes')
		);
	}
	if (!isset($schedules["30min"])) {
		$schedules["30min"] = array(
			'interval' => 30 * 60,
			'display' => __('Once every 30 minutes')
		);
	}
	$schedules['weekly'] = [
		'interval' => 7 * DAY_IN_SECONDS,
		'display'  => __('Once Weekly')
	];

	$schedules['monthly'] = [
		'interval' => 30 * DAY_IN_SECONDS,
		'display'  => __('Once Monthly')
	];

	return $schedules;
}

add_filter('woocommerce_prevent_admin_access', '__return_false', 100);

//if (tr_get_option('ci_pdf_data', 'new_login')) {
add_action('login_init', 'trpdd_login_init');
function trpdd_login_init()
{
	if (defined('TRPDD_PATIENT_SITE') && TRPDD_PATIENT_SITE) {
		return;
	}
	include(TRCPDD_PATH . 'inc/login.php');
}
/*
} else {
	add_action('login_head', 'trpdd_data_login_head', 999);
	function trpdd_data_login_head()
	{
		if (defined('TRPDD_PATIENT_SITE') && TRPDD_PATIENT_SITE) {
			return;
		}
		include(TRCPDD_PATH . 'inc/login_head.php');
	}
}*/
//end file

if (tr_get_option('ci_pdf_data', 'api_drchrono')) {

	if (is_admin()) {
		add_action('admin_menu', function () {
			add_submenu_page(
				null,
				'DrChrono Settings',
				'DrChrono',
				'manage_options',
				'drchrono-lab',
				function () {
					include(TRCPDD_PATH . 'templates/test-iframe.php');
				}
			);
		});
	}
	if (!class_exists('DrChrono_Lab')) {
		require_once(TRCPDD_PATH . 'api/ci_api_drchrono.php');
	}

	if (defined('DOING_CRON') && DOING_CRON) {
		$lab = DrChrono_Lab::get_instance();
		$lab->init_cron();
	}

	add_action('wp_loaded', function () {
		if ((defined('DOING_CRON') && DOING_CRON) || (defined('REST_REQUEST')  && REST_REQUEST)) {
			return;
		}
		if (is_admin() || strpos($_SERVER['REQUEST_URI'], '/lab-requisition/') !== 0) {
			return;
		}

		if (!class_exists('DrChrono_Lab')) {
			require_once(TRCPDD_PATH . 'api/ci_api_drchrono.php');
		}

		$lab = DrChrono_Lab::get_instance();
		$lab->init();
	});
}
