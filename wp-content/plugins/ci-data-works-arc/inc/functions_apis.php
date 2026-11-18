<?php

function tr_get_defined_apis()
{
	static $defined_apis = null;

	if ($defined_apis === null) {
		$defined_apis = array();
		$define_apis_file = dirname(__FILE__) . '/define_apis.php';

		if (is_file($define_apis_file)) {
			include $define_apis_file;

			if (isset($define_apis) && is_array($define_apis)) {
				$defined_apis = $define_apis;
			}
		}
	}

	return $defined_apis;
}

function tr_get_api_add_fields($api_key)
{
	if (empty($api_key)) {
		return array();
	}

	$define_apis = tr_get_defined_apis();

	if (isset($define_apis[$api_key]['add_fields']) && is_array($define_apis[$api_key]['add_fields'])) {
		return $define_apis[$api_key]['add_fields'];
	}

	return array();
}

function tr_is_api_field_active($api_key)
{
	return tr_get_option('ci_pdf_data', $api_key);
}

function tr_get_active_api_fields($field_prefix = '')
{
	$active_fields = array();
	$define_apis = tr_get_defined_apis();

	foreach ($define_apis as $api_key => $api_arr) {
		if (!tr_is_api_field_active($api_key)) {
			continue;
		}

		$fields = tr_get_api_add_fields($api_key);
		foreach ($fields as $field_name) {
			if ($field_prefix !== '' && strpos($field_name, $field_prefix) !== 0) {
				continue;
			}

			$active_fields[] = $field_name;
		}
	}

	return array_values(array_unique($active_fields));
}

function tr_get_all_api_fields($field_prefix = '')
{
	$all_fields = array();
	$api_keys = array_keys(tr_get_defined_apis());

	foreach ($api_keys as $api_key) {
		$fields = tr_get_api_add_fields($api_key);
		foreach ($fields as $field_name) {
			if ($field_prefix !== '' && strpos($field_name, $field_prefix) !== 0) {
				continue;
			}

			$all_fields[] = $field_name;
		}
	}

	return array_values(array_unique($all_fields));
}

function tr_ensure_table_columns($table, $columns)
{
	global $wpdb;

	if (empty($table) || empty($columns)) {
		return;
	}

	if (!is_array($columns)) {
		$columns = array($columns);
	}

	$exists = $wpdb->get_col("SHOW COLUMNS FROM `{$table}`");

	foreach ($columns as $column_name) {
		if (empty($column_name) || in_array($column_name, $exists)) {
			continue;
		}

		if (strpos($column_name, 'cli_') === 0) {
			$column_type = "text default ''";
		} else if (strpos($column_name, 'tg_') === 0 || strpos($column_name, 'u_') === 0 || strpos($column_name, 'te_') === 0) {
			$column_type = 'text';
		} else {
			$column_type = 'text';
		}

		$wpdb->query("ALTER TABLE `{$table}` ADD `{$column_name}` {$column_type}");
		$exists[] = $column_name;
	}
}


function tr_ensure_clinic_api_columns($fields = array())
{
	if (empty($fields)) {
		$fields = tr_get_active_api_fields('cli_');
	}

	tr_ensure_table_columns('ci_clinic', $fields);
}
