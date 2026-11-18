<?php



add_filter('wp_is_large_user_count', function ($args) {
	return true;
}, 99);

/*

add_action('pre_user_query', function ($query) {
	if (!is_admin()) return;
	global $pagenow;
	if ($pagenow !== 'users.php') return; // Tắt SQL_CALC_FOUND_ROWS để giảm tải 
	$query->query_fields = str_replace('SQL_CALC_FOUND_ROWS', '', $query->query_fields);
});


add_filter('found_users_query', function ($sql, $query) {
	global $wpdb;
	$sql = "SELECT count(*) from $wpdb->users ";
	return $sql;
}, 10, 2);
*/