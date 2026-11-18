<?php
function trcppr_ensure_ci_log_users_table()
{
	global $wpdb;
	static $checked = false;
	static $ready = false;
	if ($checked) {
		return $ready;
	}
	$checked = true;
	if ($wpdb->get_var("SHOW TABLES LIKE 'ci_log_users'") == 'ci_log_users') {
		$columns = $wpdb->get_col("SHOW COLUMNS FROM ci_log_users");
		$ready = empty(array_diff(array('user_id', 'user_action', 'data', 'ip', 'mid', 'btype', 'assen', 'created'), $columns));
		return $ready;
	}
	return false;
}

function trcppr_log_patient_portal_user($user_id, $action, $data = '', $mid = '', $btype = '', $assen = '')
{
	if (function_exists('trcppr_is_new_style_enabled') && !trcppr_is_new_style_enabled()) {
		return false;
	}

	global $wpdb, $blog_id;
	$user_id = (int) $user_id;
	if ($user_id <= 0) {
		$user_id = get_current_user_id();
	}
	if ($user_id <= 0) {
		return false;
	}
	if (!trcppr_ensure_ci_log_users_table()) {
		return false;
	}
	$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
	return $wpdb->insert('ci_log_users', array(
		'user_id' => $user_id,
		'lab_id' => (int) $blog_id,
		'user_action' => $action,
		'data' => is_array($data) ? json_encode($data) : $data,
		'ip' => $ip,
		'mid' => $mid,
		'btype' => $btype,
		'assen' => $assen,
		'created' => date_i18n('Y-m-d H:i:s'),
	));
}

add_action('wp_login', function ($username, $user) {
	if (!is_wp_error($user) && isset($user->ID)) {
		trcppr_log_patient_portal_user($user->ID, 'patient_login', 'patient_portal');
	}
}, 100, 2);

add_action('wp_logout', function ($user_id = 0) {
	trcppr_log_patient_portal_user($user_id, 'patient_logout', 'patient_portal');
}, 10, 1);
