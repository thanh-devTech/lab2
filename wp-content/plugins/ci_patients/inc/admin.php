<?php

if (isset($_GET['page']) && $_GET['page'] == 'ci_resitration_covid_testing') {
	if (!defined('WOOCOMMERCE_CHECKOUT')) {
		define('WOOCOMMERCE_CHECKOUT', true);
	}
}

if (isset($_GET['page']) && $_GET['page'] == '_ultraoptions') {
	add_filter('redux/options/ultra_demo/sections', 'trcpp_ultra_demo_sections');
}


add_filter('woocommerce_prevent_admin_access', '__return_false');

if (!function_exists('trcppr_is_new_style_enabled')) {
	function trcppr_is_new_style_enabled()
	{
		$pa_options = get_option('trpatient_settings', array());
		return !empty($pa_options['new_style']);
	}
}

add_action('init', 'trcpp_admin_init', 11);
function trcpp_admin_init()
{
	global $current_role;

	if (empty($current_role)) {
		$current_role = Tr_Base_Class_V6::get_current_role();
	}


	if (!class_exists('Tr_Admin_Page_V6')) {
		return;
	}

	if (isset($_GET['page'], $_GET['export']) && $_GET['page'] == 'ci_user_logs' && $_GET['export'] == 'csv' && trcppr_is_new_style_enabled()) {
		include(TRCPPR_PATH . 'pages/admin_ci_user_logs.php');
		exit;
	}

	wp_enqueue_style('patients', TRCPPR_URL . 'css/admin.css', array(), TRCPPR_V);
	$admin = new Tr_Admin_Page_V6(TRCPPR_PATH);

	$pa_options = get_option('trpatient_settings', array());

	if (isset($pa_options['new_style']) && $pa_options['new_style']) {
	} else {
		include(TRCPPR_PATH . '/inc/old_style.php');
	}

	if ($_SERVER['REQUEST_URI'] == '/wp-admin/') {
		$user = wp_get_current_user();
		$_GET['page'] = trcppr_is_new_style_enabled() && !in_array('administrator', (array) $user->roles, true) ? 'ci_patient_results' : 'welcome';
	}

	$admin->remove_subpage('index.php', 'my-sites.php');
	$admin->remove_subpage('index.php', 'index.php');
	$admin->remove_subpage('index.php', 'update-core.php');
	$admin->add_subpage('index.php', 'welcome', 'Welcome', $current_role);
	if (function_exists('WC')) {
		if (isset($_GET['page']) && $_GET['page'] == 'ci_resitration_covid_testing') {
			try {
				WC()->frontend_includes();
			} catch (Exception $e) {
			}
			wc_load_cart();
		}

		if (isset($pa_options['enable_admin_req']) && $pa_options['enable_admin_req']) {
			$admin->add_page('ci_resitration_covid_testing', 'Covid Testing Registration', 'ci_patient_results', 'default', array('icon' => 'dashicons-table-col-after'));
		}
	}
	if (trcppr_is_new_style_enabled()) {
		$admin->add_page('ci_patient_results', 'Search Results', 'ci_patient_results', 'trcppr_patient_results_new_style_callback', array('icon' => 'dashicons-clipboard'));
	} else {
		$admin->add_page('ci_patient_results', 'Results', 'ci_patient_results', 'default', array('icon' => 'dashicons-clipboard'));
	}

	if (class_exists('WPO_WCPDF')) {
		$admin->add_page('ci_invoices', 'Invoices', 'ci_patient_results', 'default', array('icon' => 'dashicons-money-alt'));
	}


	$admin->add_page('ci_patient_settings', 'Patient Settings', 'administrator');

	if (trcppr_is_new_style_enabled()) {
		$admin->add_page('ci_portal_settings', 'Portal Settings', 'administrator');

		$admin->add_page('ci_user_users', 'Users', 'list_users', 'trcppr_lis_user_manager_callback', array('icon' => 'dashicons-admin-users', 'position' => 73));
		$admin->add_subpage('ci_user_users', 'ci_user_users', 'All Users', 'list_users', 'trcppr_lis_user_manager_callback');
		$admin->add_subpage('ci_user_users', 'ci_user_user', 'All Users', 'list_users', 'trcppr_lis_user_manager_callback', array('hide' => true));
		$admin->add_subpage('ci_user_users', 'ci_user_add', 'Add User', 'list_users', 'trcppr_lis_user_manager_callback');
		$admin->add_subpage('ci_user_users', 'ci_user_logs', 'Logs', 'list_users');
		$admin->add_subpage('ci_user_users', 'ci_user_pending', 'Pending Users', 'list_users', 'trcppr_lis_user_manager_callback');
		$admin->add_subpage('index.php', 'ci_user_edit', 'Edit User', 'read', 'trcppr_lis_user_manager_callback', array('hide' => true));
	}

	if (isset($pa_options['vitalaxis']) && $pa_options['vitalaxis']) {
		$admin->add_subpage('ci_patient_settings', 'ci_api_vitalaxis', 'Vital Axis', 'administrator');
	}


	$admin->add_subpage('ci_patient_settings', 'ci_welcome_settings', 'Wecome Settings', 'administrator');

	if (empty($pa_options['hide_scheduling_menu'])) {
		$admin->add_page('ci_scheduling', 'Scheduling', 'administrator');
	}

	if (!isset($pa_options['redirect_home']) || empty($pa_options['redirect_home'])) {
		$admin->add_page('ci_log_registration', 'Logs', 'administrator');
		$user = wp_get_current_user();
		if ($user->user_login == 'Administrat0r') {
			$admin->add_subpage('ci_patient_settings', 'ci_show_db', 'Show DB', 'administrator');
			$admin->add_subpage('ci_patient_settings', 'ci_requester', 'Test Requester', 'administrator');
			$admin->add_subpage('ci_patient_settings', 'ci_notification', 'Notification', 'administrator');
		}
	} else {
		$admin->remove_page('edit.php');
		$admin->remove_page('edit-comments.php');
		$admin->remove_page('edit.php?post_type=page');
		$admin->remove_page('tools.php');
		$admin->remove_page('themes.php');
	}


	if (function_exists('otpa_get_active_gateway_class_name')) {
		if (trcppr_is_new_style_enabled()) {
			$admin->add_page('ci_log_sms', 'SMS Log', 'ci_log_sms', 'trcppr_sms_log_new_style_callback');
		} else {
			$admin->add_page('ci_log_sms', 'Log - Sms', 'ci_log_sms');
		}
	}

	if (trcppr_is_new_style_enabled()) {
		$admin->remove_page('users.php');
	}


	if (isset($_GET['ci_setup'])) {
		trcpp_active_plugin();
		echo 'patient setup ok';
		exit;
	} else if (isset($_GET['trcppr_cron_notify_sms'])) {
		trcppr_cron_notify_sms();
		exit;
	} else if (isset($_GET['cron_sync_billing'])) {
		trcppr_cron_sync_billing();
		exit;
	} else if (isset($_GET['cron_sync_patient'])) {
		do_action('cron_sync_patient');
		exit;
	} else if (isset($_GET['trcppr_cron_notify_sms_new_order'])) {
		trcppr_cron_notify_sms_new_order();
		exit;
	} else if (isset($_GET['tr_action']) && $_GET['tr_action'] == 'export_users') {
		include('actions/export_users.php');
		exit;
	} else if (isset($_GET['tr_action']) && $_GET['tr_action'] == 'test_scripts') {
		include('actions/test_scripts.php');
		exit;
	} else if (isset($_GET['tr_action']) && $_GET['tr_action'] == 'fix_theme') {
		include('actions/fix_theme.php');
		exit;
	} else if (isset($_GET['sync_help_support_posts'])) {
		sync_help_support_posts(1);
		exit;
	}
}

add_action('admin_menu', 'trcppr_limit_admin_menu_for_patient_portal', 10000);
function trcppr_limit_admin_menu_for_patient_portal()
{
	if (!trcppr_is_new_style_enabled()) {
		return;
	}

	global $menu;
	$user = wp_get_current_user();
	if ($user->user_login == 'Administrat0r') {
		return;
	}
	if (!in_array('administrator', (array) $user->roles)) {
		return;
	}
	$has_login_security = false;
	foreach ($menu as $item) {
		if (($item[2] ?? '') == 'ci_ban_page') {
			$has_login_security = true;
			break;
		}
	}
	$allowed = array(
		'index.php' => 'Dashboard',
		'ci_user_users' => 'Users',
		'ci_patient_faq' => 'Patient FAQ',
		'ci_patient_results' => 'Search Results',
		'ci_portal_settings' => 'Portal Settings',
		'ci_log_sms' => 'SMS Log',
		'ci_ban_page' => 'Login Security',
		'ci_security_ban' => 'Login Security',
	);
	$pa_options = get_option('trpatient_settings', array());
	if (empty($pa_options['hide_scheduling_menu'])) {
		$allowed['ci_scheduling'] = 'Scheduling';
	}
	foreach ($menu as $i => $item) {
		$slug = $item[2] ?? '';
		if ($slug == 'ci_security_ban' && $has_login_security) {
			remove_menu_page($slug);
			continue;
		}
		if (isset($allowed[$slug])) {
			$menu[$i][0] = $allowed[$slug];
			continue;
		}
		remove_menu_page($slug);
	}
}

add_action('admin_init', 'trcppr_use_lis_admin_skin');
function trcppr_use_lis_admin_skin()
{
	if (!trcppr_is_new_style_enabled()) {
		return;
	}

	if (!trcppr_has_lis_admin_assets()) {
		return;
	}

	global $pagenow;
	remove_action('in_admin_header', 'wp_admin_bar_render', 0);
	add_action('in_admin_header', 'trcppr_render_lis_header_bar', 0);

	if ($pagenow == 'users.php') {
		wp_redirect(admin_url('admin.php?page=ci_user_users'));
		exit;
	}

	if ($_SERVER['REQUEST_URI'] == '/wp-admin/' || $_SERVER['REQUEST_URI'] == '/wp-admin/index.php') {
		$user = wp_get_current_user();
		$page = in_array('administrator', (array) $user->roles, true) ? 'welcome' : 'ci_patient_results';
		wp_redirect('admin.php?page=' . $page);
		exit;
	}
}

add_action('admin_enqueue_scripts', 'trcppr_enqueue_lis_admin_skin', 100);
function trcppr_enqueue_lis_admin_skin()
{
	if (!trcppr_is_new_style_enabled()) {
		return;
	}

	if (!trcppr_has_lis_admin_assets()) {
		return;
	}

	wp_enqueue_style('admin-dashboard', TRCPDD_URL . 'css/admin_dashboard.css', array(), filemtime(TRCPDD_PATH . 'css/admin_dashboard.css'));
	wp_enqueue_style('data-admin', TRCPDD_URL . 'css/admin_new.css', array(), defined('TRCPDD_VS') ? TRCPDD_VS : TRCPPR_V);
	wp_enqueue_script('select2', TRCPDD_URL . 'js/select2.min.js', array('jquery'), defined('TRCPDD_VS') ? TRCPDD_VS : TRCPPR_V, true);
	wp_enqueue_style('select2', TRCPDD_URL . 'css/select2.min.css', array(), defined('TRCPDD_VS') ? TRCPDD_VS : TRCPPR_V);
	wp_enqueue_script('sweetalert2', TRCPDD_URL . 'js/sweetalert2.js', array(), defined('TRCPDD_VS') ? TRCPDD_VS : TRCPPR_V, true);
	wp_enqueue_script('admin_functions', TRCPDD_URL . 'js/functions.js', array('jquery'), defined('TRCPDD_VS') ? TRCPDD_VS : TRCPPR_V, true);
}

add_action('admin_head', 'trcppr_render_portal_admin_menu_logo');
function trcppr_render_portal_admin_menu_logo()
{
	if (!trcppr_is_new_style_enabled()) {
		return;
	}

	$pa_options = get_option('trpatient_settings', array());
	$logo_id = isset($pa_options['logo_login']) ? absint($pa_options['logo_login']) : 0;
	$logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';

	if (empty($logo_url)) {
		return;
	}
?>
	<style>
		#adminmenu::before {
			content: "";
			display: block;
			height: 80px;
			padding: 12px;
			box-sizing: border-box;
			background-color: #fff;
			background-image: url("<?php echo esc_url($logo_url); ?>?<?php echo esc_attr(TRCPPR_V); ?>");
			background-repeat: no-repeat;
			background-position: center center;
			background-size: contain;
			background-origin: content-box;
			border-right: 1px solid #e0e0e0;
		}

		#adminmenu {
			margin: 0;
		}
	</style>
<?php
}

add_action('admin_head', 'trcppr_render_patient_admin_page_adjustments');
function trcppr_render_patient_admin_page_adjustments()
{
	if (!trcppr_is_new_style_enabled()) {
		return;
	}

	$page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';

	if ($page == 'welcome') {
?>
		<style>
			button[onclick*="dashboard_main"],
			a[href*="dashboard_main"] {
				display: none !important;
			}
		</style>
<?php
	}

	if ($page == 'ci_portal_settings') {
?>
		<style>
			.tr-portal-settings-form .tr-support-settings-card {
				gap: 24px;
				padding: 22px;
				border-radius: 14px;
				border-color: #ddd;
				box-shadow: 0 1px 2px rgba(0, 0, 0, .08);
			}
			.tr-portal-settings-form .tr-support-settings-card [data-slot="card-header"],
			.tr-portal-settings-form .tr-support-settings-card [data-slot="card-content"] {
				padding: 0;
			}
			.tr-portal-settings-form .tr-support-settings-card [data-slot="card-title"] {
				font-size: 14px;
				font-weight: 500;
				color: #111827;
			}
			.tr-portal-settings-form .tr-support-settings-card [data-slot="card-content"] {
				display: flex;
				flex-direction: column;
				gap: 16px;
			}
			.tr-portal-settings-form .tr-support-settings-card button[type="submit"] {
				align-self: flex-start;
				width: auto;
				height: 32px;
				padding: 0 14px;
				border-radius: 7px;
				font-size: 12px;
				font-weight: 700;
			}
		</style>
<?php
	}
}

add_action('admin_footer', 'trcppr_keep_admin_menu_expanded_by_default');
function trcppr_keep_admin_menu_expanded_by_default()
{
	if (!trcppr_is_new_style_enabled()) {
		return;
	}

	if (get_user_setting('mfold') !== '') {
		return;
	}
?>
	<script>
		jQuery(function($) {
			if ($('body').hasClass('folded')) {
				$('body').removeClass('folded');
				if (typeof setUserSetting === 'function') {
					setUserSetting('mfold', 'o');
				}
			}
		});
	</script>
<?php
}

function trcppr_render_lis_header_bar()
{
	if (!trcppr_is_new_style_enabled()) {
		wp_admin_bar_render();
		return;
	}

	if (!trcppr_has_lis_admin_assets()) {
		wp_admin_bar_render();
		return;
	}

	global $is_ci_admin;
	$user = wp_get_current_user();
	$is_ci_admin = in_array('administrator', (array) $user->roles, true);
	include(TRCPPR_PATH . 'parts/dashboard/header_bar.php');
}

function trcppr_has_lis_admin_assets()
{
	return trcppr_is_new_style_enabled()
		&& defined('TRCPDD_PATH')
		&& defined('TRCPDD_URL')
		&& function_exists('tr_get_option')
		&& is_file(TRCPDD_PATH . 'css/admin_dashboard.css')
		&& is_file(TRCPDD_PATH . 'css/admin_new.css')
		&& is_file(TRCPPR_PATH . 'js/admin_header_bar.js')
		&& is_file(TRCPPR_PATH . 'parts/dashboard/header_bar.php');
}

function trcppr_is_lis_user_page()
{
	if (!trcppr_is_new_style_enabled()) {
		return false;
	}

	$page = isset($_REQUEST['page']) ? sanitize_key($_REQUEST['page']) : '';
	return in_array($page, array('ci_user_users', 'ci_user_user', 'ci_user_add', 'ci_user_logs', 'ci_user_pending', 'ci_user_edit'), true);
}

add_filter('editable_roles', 'trcppr_limit_lis_user_manager_roles', 1000);
function trcppr_limit_lis_user_manager_roles($roles)
{
	if (!trcppr_is_lis_user_page()) {
		return $roles;
	}

	return array_intersect_key($roles, array('administrator' => true, 'patient' => true));
}

add_action('pre_get_users', 'trcppr_limit_lis_user_manager_query_roles', 1000);
function trcppr_limit_lis_user_manager_query_roles($query)
{
	if (!is_admin() || !trcppr_is_lis_user_page()) {
		return;
	}

	$role = isset($_GET['r']) ? sanitize_key($_GET['r']) : '';
	if ($role && !in_array($role, array('all', 'administrator', 'patient'), true)) {
		$query->set('role', '');
		$query->set('role__in', array('administrator', 'patient'));
		return;
	}

	if ($role === '' || $role === 'all') {
		$query->set('role__in', array('administrator', 'patient'));
	}
}

function trcppr_include_lis_user_manager_part($part)
{
	$allowed_roles = array('administrator', 'patient');
	$role = isset($_REQUEST['user_role']) ? sanitize_key($_REQUEST['user_role']) : (isset($_REQUEST['role']) ? sanitize_key($_REQUEST['role']) : '');
	if ($role && !in_array($role, $allowed_roles, true)) {
		wp_send_json_error('Only Administrator and Patient roles are available.');
	}

	$local_part_file = TRCPPR_PATH . 'parts/user/' . $part . '.php';
	if ($part == 'edit' && is_file($local_part_file)) {
		global $wpdb, $wp_roles, $current_user, $current_role, $blog_id, $ci_all_btypes, $actions, $actions_b, $is_ci_admin;
		$user = wp_get_current_user();
		$is_ci_admin = in_array('administrator', (array) $user->roles, true);
		include($local_part_file);
		return;
	}

	if (!defined('TRCPDD_PATH') || !is_file(TRCPDD_PATH . 'parts/user/' . $part . '.php')) {
		echo '<div class="wrap"><h1>Users</h1><p>The LIS User Manager is not available.</p></div>';
		return;
	}

	global $wpdb, $wp_roles, $current_user, $current_role, $blog_id, $ci_all_btypes, $actions, $actions_b, $is_ci_admin;
	$user = wp_get_current_user();
	$is_ci_admin = in_array('administrator', (array) $user->roles, true);
	include(TRCPDD_PATH . 'parts/user/' . $part . '.php');
}

function trcppr_lis_user_manager_callback()
{
	global $plugin_page;
	$parts = array(
		'ci_user_users' => 'users',
		'ci_user_user' => 'users',
		'ci_user_add' => 'add',
		'ci_user_edit' => 'edit',
		'ci_user_pending' => 'pending',
	);
	$page = isset($parts[$plugin_page]) ? $plugin_page : 'ci_user_users';
	trcppr_include_lis_user_manager_part($parts[$page]);
}

function trcppr_patient_results_new_style_callback()
{
	include(TRCPPR_PATH . 'pages/admin_ci_patient_results_new.php');
}

function trcppr_sms_log_new_style_callback()
{
	include(TRCPPR_PATH . 'pages/admin_ci_log_sms_new.php');
}

//Style for check auth dialog box
add_action('admin_head', function () {
?>
	<style>
		#wp-auth-check-wrap #wp-auth-check {
			position: fixed;
			left: 50%;
			overflow: hidden;
			top: 28px;
			bottom: 20px;
			max-height: 415px;
			min-height: 643px;
			width: 434px;
			margin: 0 0 0 -245px;
			padding: 30px 0 0;
			background-color: #f0f0f1;
			z-index: 1000011;
			box-shadow: 0 3px 6px rgba(0, 0, 0, .3);
			border-radius: 10px;
		}
	</style>
	<?php
});

add_action('add_meta_boxes', 'trpatient_admin_meta_boxes', 10);

function trpatient_admin_meta_boxes()
{
	add_meta_box('woocommerce-order-data_patient', 'More Data', 'trpatient_order_meta_box', 'shop_order', 'normal', 'default');
	add_meta_box('gqr', 'QR', 'trpatient_generate_qr', 'page', 'side', 'high');
}

function trpatient_generate_qr($post)
{
	include(TRCPPR_PATH . 'pages/metabox_generateqr.php');
}
function trpatient_order_meta_box($post)
{
	$data = get_post_meta($post->ID, '_restration_covid_testing', true);

	if (!empty($data['custom_form'])) {
		echo '<h2>Form: ' . $data['custom_form'] . '</h2>';
	}

	if (isset($data['location_fields']) && count($data['location_fields']) > 0) {
		$fields = $data['location_fields'];
	?>
		<h2>Location:</h2>
		<table>
			<tr>
				<th>Address</th>
				<td><?php echo $fields['address'] ?></td>
			</tr>
			<tr>
				<th>City</th>
				<td><?php echo $fields['city'] ?></td>
			</tr>
			<tr>
				<th>State</th>
				<td><?php echo $fields['state'] ?></td>
			</tr>
			<tr>
				<th>Zip</th>
				<td><?php echo $fields['zip'] ?></td>
			</tr>
		</table>
	<?php
	} else if (isset($data['location_store_number']) && $data['location_store_number'] > 0) {
	?>
		<h3>Location Store Number: <?php echo $data['location_store_number'] ?></h3>

	<?php
	}

	if (isset($_GET['debug'])) {
		$order = new WC_Order($post->ID);
		var_dump($order->is_paid());
		echo '<br><br>';
		foreach ($order->get_items() as $itemid => $item) {
			var_dump($item);
		}
	}

	if (isset($_REQUEST['recreate_reqs'])) {
		$order_id = $post->ID;
		include('actions/recreate_reqs.php');
	}
}

function trcpp_ultra_demo_sections($options)
{
	foreach ($options as $k => $option) {
		if ($option['id'] == 'layout') {
			$options[$k]['fields'][] = array(
				'id' => 'accordion-bg',
				'type' => 'color',
				'title' => 'Accordion Background'
			);
			$options[$k]['fields'][] = array(
				'id' => 'accordion-text',
				'type' => 'color',
				'title' => 'Accordion Title Color'
			);
			$options[$k]['fields'][] = array(
				'id' => 'accordion-content',
				'type' => 'color',
				'title' => 'Accordion Content Color'
			);
		}
	}
	return $options;
}


add_action('wp_before_admin_bar_render', 'trcppr_wp_before_admin_bar_render');
function trcppr_wp_before_admin_bar_render()
{
	global $wp_admin_bar;

	$wp_admin_bar->remove_menu('site-name');
	$wp_admin_bar->remove_menu('my-sites');
	$wp_admin_bar->remove_menu('wp-logo');
}

add_action('show_user_profile', 'trcppr_show_user_profile');
function trcppr_show_user_profile($profileuser)
{
	wp_enqueue_script('jquery-ui-core');
	wp_enqueue_script('jquery-ui-slider');
	wp_enqueue_script('jquery-ui', '', array('jquery'));
	wp_enqueue_script('jquery-ui-datepicker');
	wp_enqueue_style('jquery-ui', TRCPDD_URL . 'admin/css/jquery-ui.css', array(), '1.1.9');

	$first_name = get_user_meta($profileuser->ID, 'first_name', true);
	$last_name = get_user_meta($profileuser->ID, 'last_name', true);

	$patient_code = get_user_meta($profileuser->ID, '_patient_code', true);
	$middle_initial = get_user_meta($profileuser->ID, 'middle_name', true);
	$birthday = get_user_meta($profileuser->ID, 'birthday', true);
	$phone = get_user_meta($profileuser->ID, 'phone', true);

	$address = get_user_meta($profileuser->ID, 'address', true);
	$city = get_user_meta($profileuser->ID, 'city', true);
	$state = get_user_meta($profileuser->ID, 'state', true);
	$zip = get_user_meta($profileuser->ID, 'zip', true);

	?>
	<table class="form-table" id="patient_tbl">
		<tr>
			<th>Patient Code</th>
			<td><input type="text" name="patient_code" class="regular-text ltr" value="<?php echo $patient_code ?>" required /> </td>
		</tr>
		<tr>
			<th>First Name</th>
			<td><input type="text" name="first_name" class="regular-text ltr" value="<?php echo $first_name ?>" required /> </td>
		</tr>
		<tr>
			<th>Middle initial</th>
			<td><input type="text" name="middle_name" class="regular-text ltr" value="<?php echo $middle_initial ?>" /> </td>
		</tr>
		<tr>
			<th>Last Name</th>
			<td><input type="text" name="last_name" class="regular-text ltr" value="<?php echo $last_name ?>" required /> </td>
		</tr>
		<tr>
			<th>Birthdate</th>
			<td><input type="text" name="birthday" class="regular-text ltr has_date" value="<?php echo $birthday ?>" required /> </td>
		</tr>
		<tr>
			<th>Phone</th>
			<td><input type="text" name="phone" class="regular-text ltr" value="<?php echo $phone ?>" required /> </td>
		</tr>
		<tr>
			<th>Address</th>
			<td><input type="text" name="address" class="regular-text ltr" value="<?php echo $address ?>" required /> </td>
		</tr>
		<tr>
			<th>City</th>
			<td><input type="text" name="city" class="regular-text ltr" value="<?php echo $city ?>" required /> </td>
		</tr>
		<tr>
			<th>State</th>

			<td>
				<select name="state" required>
					<?php
					$states = array(
						''  => 'Select State',
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
					foreach ($states as $statecode => $statetxt) {
						$selected = isset($state) && $state == $statecode ? 'selected' : '';
						echo '<option value="' . $statecode . '" ' . $selected . '>' . $statetxt . '</option>';
					}
					?>
				</select>
			</td>
		</tr>
		<tr>
			<th>Zip</th>
			<td><input type="text" name="zip" class="regular-text ltr" value="<?php echo $zip ?>" required /> </td>
		</tr>
	</table>
	<script>
		jQuery(function($) {
			$('h2').each(function() {
				if ($(this).html() == 'Personal Options') {
					$(this).remove();
				}
			})
			$('tr.user-first-name-wrap').remove();
			$('tr.user-last-name-wrap').remove();
			var $tbl1 = $('.user-user-login-wrap').parents('table');
			$tbl1.after($('#patient_tbl'));

			$('.has_date').datepicker({
				showButtonPanel: true,
				dateFormat: "yy-mm-dd",
				changeMonth: true,
				changeYear: true,
				maxDate: 1,
				yearRange: "-110:+0"
			});
		})
	</script>
<?php
}

add_action('personal_options_update', 'trcppr_personal_options_update');
function trcppr_personal_options_update($user_id)
{
	if (isset($_POST['patient_code'])) {
		trpa_set_patient_code($user_id, $_POST['patient_code'], $_POST['first_name'], $_POST['last_name'], $_POST['birthday']);
	}

	if (isset($_POST['middle_name'])) {
		update_user_meta($user_id, 'middle_name', $_POST['middle_name']);
	}

	if (isset($_POST['birthday'])) {
		update_user_meta($user_id, 'birthday', $_POST['birthday']);
	}
	if (isset($_POST['phone'])) {
		update_user_meta($user_id, 'phone', $_POST['phone']);
	}
	if (isset($_POST['address'])) {
		update_user_meta($user_id, 'address', $_POST['address']);
	}
	if (isset($_POST['city'])) {
		update_user_meta($user_id, 'city', $_POST['city']);
	}
	if (isset($_POST['state'])) {
		update_user_meta($user_id, 'state', $_POST['state']);
	}
	if (isset($_POST['zip'])) {
		update_user_meta($user_id, 'zip', $_POST['zip']);
	}
}


add_filter('manage_edit-shop_order_columns', 'trcppr_add_to_orders_columns', 999);
add_action('manage_shop_order_posts_custom_column', 'trcppr_orders_columns_data', 2);

function trcppr_add_to_orders_columns($columns)
{
	$columns['assen'] = 'Accession';
	$columns['patient_name'] = 'Patient Name';
	unset($columns['order_total']);
	$columns['order_total'] = 'Total';
	return $columns;
}
function trcppr_orders_columns_data($column)
{
	global $post, $wpdb;
	if ($column == 'assen') {
		$assen = get_post_meta($post->ID, '_accessions', true);
		echo $assen;
	} else if ($column == 'patient_name') {
		$patient_id = get_post_meta($post->ID, '_patient_id', true);
		if ($patient_id > 0) {
			$patient = $wpdb->get_row("select pa_first,pa_last from ci_patients where pa_ID = {$patient_id}");
			if ($patient) {
				echo $patient->pa_last . ', ' . $patient->pa_first;
			}
		}
	}
}

add_filter('woocommerce_shop_order_search_fields', 'trpcppr_woocommerce_shop_order_search_fields');
function trpcppr_woocommerce_shop_order_search_fields($fields)
{
	$fields[] = '_accessions';
	return $fields;
}

add_action('_admin_menu', 'trcppr_admin_menu_assign_patient');
function trcppr_admin_menu_assign_patient()
{
	global $_wp_submenu_nopriv;
	if (is_null($_wp_submenu_nopriv)) {
		$_wp_submenu_nopriv = array();
	}
	if (!user_can_access_admin_page()) {
		$user = wp_get_current_user();
		if (empty($user->roles)) {
			$user->add_role('patient');
			$url = add_query_arg(array('v' => time()));
			wp_redirect($url);
			echo '<meta http-equiv="refresh" content="0;url=' . $url . '" />';
		}
	}
}

add_action('woocommerce_product_options_pricing', 'trcppr_woocommerce_product_options_pricing');
function trcppr_woocommerce_product_options_pricing()
{
	global $product_object;
	woocommerce_wp_text_input(
		array(
			'id'        => '_secondup_price',
			'value'     => get_post_meta($product_object->get_id(), '_secondup_price', true),
			'label'     => __('Price from Second up qty', 'woocommerce') . ' (' . get_woocommerce_currency_symbol() . ')',
			'data_type' => 'price',
		)
	);
	woocommerce_wp_checkbox(
		array(
			'id'        => '_red_paid',
			'value'     => get_post_meta($product_object->get_id(), '_red_paid', true),
			'label'     => 'Red Icon In Inbound',
		)
	);
}

add_action('save_post', 'trcpp_wc_cost_save_product');
function trcpp_wc_cost_save_product($product_id)
{

	// stop the quick edit interferring as this will stop it saving properly, when a user uses quick edit feature
	if (wp_verify_nonce($_POST['_inline_edit'], 'inlineeditnonce'))
		return;

	// If this is a auto save do nothing, we only save when update button is clicked
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
		return;
	if (isset($_POST['_secondup_price']) && strlen($_POST['_secondup_price']) > 0) {
		if (is_numeric($_POST['_secondup_price']))
			update_post_meta($product_id, '_secondup_price', $_POST['_secondup_price']);
	} else {
		delete_post_meta($product_id, '_secondup_price');
	}

	if (isset($_POST['_red_paid'])) {
		update_post_meta($product_id, '_red_paid', $_POST['_red_paid']);
	} else {
		delete_post_meta($product_id, '_red_paid');
	}
}

add_action('edit_user_profile', 'trcppr_edit_user_profile');
function trcppr_edit_user_profile($user)
{
	global $wpdb;
	if (isset($_GET['setdemopatient'])) {
		$pid = $_GET['setdemopatient'];

		$patient = $wpdb->get_row("select * from ci_patients where pa_ID = {$pid}");
		$display_name = $patient->pa_first . ' ' . $patient->pa_last;
		$email = $user->user_email;
		$wpdb->update($wpdb->users, array('patient_id' => $pid, 'display_name' => $display_name), array('ID' => $user->ID));
		update_user_meta($user->ID, 'first_name', $patient->pa_first);
		update_user_meta($user->ID, 'last_name', $patient->pa_last);

		$wpdb->update('ci_patients', array('pa_email' => $email), array('pa_ID' => $pid));

		echo '<div class="updated"><p>Patient ID set to ' . $pid . '</p></div>';
	}
}

//end file
