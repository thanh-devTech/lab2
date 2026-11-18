<?php
global $wpdb, $is_ci_admin;

$can_store_admin = current_user_can('ci_estore_admin');

$table_name = 'ci_estore_products';
$wpdb->query("CREATE TABLE IF NOT EXISTS `{$table_name}` (
	`ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	`title` varchar(255) NOT NULL,
	`sku` varchar(150) NOT NULL DEFAULT '',
	`image` varchar(500) NOT NULL DEFAULT '',
	`category` varchar(150) NOT NULL DEFAULT '',
	`max_qty` int(10) unsigned NOT NULL DEFAULT 1,
	`display_order` int(10) unsigned NOT NULL DEFAULT 0,
	`description` text,
	`created` datetime NOT NULL,
	`updated` datetime NOT NULL,
	PRIMARY KEY (`ID`),
	KEY `category` (`category`),
	KEY `display_order` (`display_order`)
) ENGINE=InnoDB");
if (!$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$table_name}` LIKE %s", 'sku'))) {
	$wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `sku` varchar(150) NOT NULL DEFAULT '' AFTER `title`");
}
if (!$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$table_name}` LIKE %s", 'display_order'))) {
	$wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN `display_order` int(10) unsigned NOT NULL DEFAULT 0 AFTER `max_qty`, ADD KEY `display_order` (`display_order`)");
	$wpdb->query("UPDATE `{$table_name}` SET display_order = ID WHERE display_order = 0");
}
$orders_table = 'ci_estore_orders';
$order_items_table = 'ci_estore_order_items';
$can_estore_settings = $can_store_admin;
$ci_estore_primary_lab = array();
if (class_exists('CI_Base_Lab')) {
	$ci_estore_primary_lab = CI_Base_Lab::get_current_lab();
} else {
	$ci_estore_blog_id = function_exists('get_current_blog_id') ? absint(get_current_blog_id()) : 0;
	if ($ci_estore_blog_id > 0) $ci_estore_primary_lab = $wpdb->get_row($wpdb->prepare('SELECT * FROM ci_labs WHERE lab_site = %d ORDER BY lab_ID ASC LIMIT 1', $ci_estore_blog_id), ARRAY_A);
	if (empty($ci_estore_primary_lab)) $ci_estore_primary_lab = $wpdb->get_row('SELECT * FROM ci_labs WHERE lab_status = 1 AND lab_is_ref IN (0, 2) ORDER BY lab_ID ASC LIMIT 1', ARRAY_A);
	if (empty($ci_estore_primary_lab)) $ci_estore_primary_lab = $wpdb->get_row('SELECT * FROM ci_labs ORDER BY lab_ID ASC LIMIT 1', ARRAY_A);
}
$ci_estore_primary_lab_phone = !empty($ci_estore_primary_lab['lab_phone']) ? sanitize_text_field($ci_estore_primary_lab['lab_phone']) : '';
$ci_estore_primary_lab_email = !empty($ci_estore_primary_lab['lab_email']) && is_email($ci_estore_primary_lab['lab_email']) ? sanitize_email($ci_estore_primary_lab['lab_email']) : '';
$ci_estore_default_from_email = sanitize_email(get_option('admin_email'));
$estore_settings_defaults = array(
	'notification_email' => '',
	'from_email' => $ci_estore_default_from_email,
	'reply_to_email' => $ci_estore_primary_lab_email,
	'email_subject' => 'New Supply Store order #{order_id}',
	'email_body' => "A new Supply Store order has been submitted.\n\nOrder: #{order_id}\nClinic: {clinic_name}\nItems: {items}",
	'order_shipped_subject' => 'Your Supply Store order #{order_id} has shipped',
	'order_shipped_body' => "Your Supply Store order #{order_id} has shipped.\n\nClinic: {clinic_name}\nTracking: {tracking}\nShip date: {ship_date}",
	'order_success_text' => 'Your supply order has been sent to the lab.',
	'support_phone' => $ci_estore_primary_lab_phone,
	'support_email' => $ci_estore_primary_lab_email,
);
$estore_settings = wp_parse_args(get_option('ci_estore_settings', array()), $estore_settings_defaults);
if (!is_email($estore_settings['from_email'])) $estore_settings['from_email'] = $ci_estore_default_from_email;
if (($estore_settings['support_phone'] === '' || $estore_settings['support_phone'] === '800-555-0100') && $ci_estore_primary_lab_phone !== '') $estore_settings['support_phone'] = $ci_estore_primary_lab_phone;
if ((!is_email($estore_settings['support_email']) || $estore_settings['support_email'] === 'supplies@labcorp.com') && $ci_estore_primary_lab_email !== '') $estore_settings['support_email'] = $ci_estore_primary_lab_email;
if (!is_email($estore_settings['reply_to_email']) && $ci_estore_primary_lab_email !== '') $estore_settings['reply_to_email'] = $ci_estore_primary_lab_email;
$ci_estore_demo_products = array(
	array('title' => 'Gray Top Tubes', 'sku' => 'B-D364951Z', 'image' => 'https://dev.lis-demo.com/wp-content/uploads/2026/07/unnamed.png', 'category' => 'pcr', 'max_qty' => 10, 'description' => 'Urine collection | 100/pk'),
	array('title' => 'Nasopharyngeal Swab', 'sku' => 'COP-482C', 'image' => 'https://dev.lis-demo.com/wp-content/uploads/2026/07/unnamed-3.png', 'category' => 'pcr', 'max_qty' => 5, 'description' => 'Respiratory collection | 500/case'),
	array('title' => 'Specimen Bags 4x6', 'sku' => 'DYND30246Z', 'image' => 'https://dev.lis-demo.com/wp-content/uploads/2026/07/unnamed-4.png', 'category' => 'pcr', 'max_qty' => 5, 'description' => 'Individual specimen | 100/pk'),
	array('title' => 'Specimen Bags 8x10', 'sku' => 'DYND302810Z', 'image' => 'https://dev.lis-demo.com/wp-content/uploads/2026/07/unnamed-5.png', 'category' => 'pcr', 'max_qty' => 5, 'description' => 'Multiple specimen | 100/pk'),
	array('title' => 'Specimen Containers', 'sku' => 'DYND364976', 'image' => 'https://dev.lis-demo.com/wp-content/uploads/2026/07/unnamed-1-1.png', 'category' => 'pcr', 'max_qty' => 10, 'description' => 'Urine collection | 100/case'),
	array('title' => 'Wound Swabs', 'sku' => 'CPO480CZ', 'image' => 'https://dev.lis-demo.com/wp-content/uploads/2026/07/unnamed-2.png', 'category' => 'pcr', 'max_qty' => 10, 'description' => 'Wound collection | 50/BX'),
);
$store_test_types = function_exists('tr_pdd_get_batch_types') ? array_values(tr_pdd_get_batch_types()) : array();
$store_categories = array();
foreach ($store_test_types as $store_test_type) $store_categories[$store_test_type] = function_exists('tr_pdd_get_typename') ? tr_pdd_get_typename($store_test_type) : strtoupper($store_test_type);
$store_category_label = function ($category) use ($store_categories) {
	return isset($store_categories[$category]) ? $store_categories[$category] : ucwords(str_replace(array('-', '_'), ' ', $category));
};
$ci_estore_pcr_panel_enabled = function_exists('tr_get_option') ? tr_get_option('ci_pdf_data', 'e_ful_panels') : false;
$ci_estore_pcr_panels = array('uri' => 'URI', 'uti' => 'UTI', 'wound' => 'Wound', 'covid' => 'Covid-19');
$ci_estore_pcr_panel_ids = array();
$store_activity_types = array();
foreach ($store_test_types as $store_test_type) {
	if ($store_test_type === 'tox') {
		$store_activity_types['tox_oral'] = 'Oral';
		$store_activity_types['tox_urine'] = 'Urine';
	} else if ($store_test_type === 'pcr' && $ci_estore_pcr_panel_enabled) {
		foreach ($ci_estore_pcr_panels as $ci_estore_pcr_panel_key => $ci_estore_pcr_panel_label) $store_activity_types['pcr_' . $ci_estore_pcr_panel_key] = $ci_estore_pcr_panel_label;
		$ci_estore_pcr_panel_ids['uri'] = $wpdb->get_var("SELECT tg_ID FROM ci_test_groups WHERE tg_name = 'Upper Respiratory' OR tg_short_name = 'uri' LIMIT 1");
		$ci_estore_pcr_panel_ids['uti'] = $wpdb->get_var("SELECT tg_ID FROM ci_test_groups WHERE tg_name = 'UTI+ w/ABX Resistance' OR tg_short_name = 'UTI+' LIMIT 1");
		$ci_estore_pcr_panel_ids['wound'] = $wpdb->get_var("SELECT tg_ID FROM ci_test_groups WHERE tg_name = 'Wound' OR tg_short_name = 'WOU' LIMIT 1");
		$ci_estore_pcr_panel_ids['covid'] = $wpdb->get_var("SELECT tg_ID FROM ci_test_groups WHERE tg_name = 'nCoV-2019' OR tg_is_covid = '1' LIMIT 1");
	} else {
		$store_activity_types[$store_test_type] = isset($store_categories[$store_test_type]) ? $store_categories[$store_test_type] : strtoupper($store_test_type);
	}
}
$wpdb->query("CREATE TABLE IF NOT EXISTS `{$orders_table}` (
	`ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	`clinic_id` int(11) unsigned NOT NULL DEFAULT 0,
	`user_id` bigint(20) unsigned NOT NULL DEFAULT 0,
	`status` varchar(20) NOT NULL DEFAULT 'Pending',
	`order_notes` text,
	`lab_notes` text,
	`tracking` varchar(150) NOT NULL DEFAULT '',
	`ship_date` date DEFAULT NULL,
	`created` datetime NOT NULL,
	`updated` datetime NOT NULL,
	PRIMARY KEY (`ID`), KEY `clinic_id` (`clinic_id`), KEY `status` (`status`), KEY `created` (`created`)
) ENGINE=InnoDB");
$wpdb->query("CREATE TABLE IF NOT EXISTS `{$order_items_table}` (
	`ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	`order_id` bigint(20) unsigned NOT NULL,
	`product_id` bigint(20) unsigned NOT NULL DEFAULT 0,
	`title` varchar(255) NOT NULL,
	`sku` varchar(150) NOT NULL DEFAULT '',
	`category` varchar(150) NOT NULL DEFAULT '',
	`ordered_qty` int(10) unsigned NOT NULL DEFAULT 0,
	`fulfilled_qty` int(10) unsigned NOT NULL DEFAULT 0,
	PRIMARY KEY (`ID`), KEY `order_id` (`order_id`)
) ENGINE=InnoDB");

$action = isset($_REQUEST['act']) ? sanitize_key(wp_unslash($_REQUEST['act'])) : '';
$product_id = isset($_REQUEST['id']) ? absint($_REQUEST['id']) : 0;
$page_url = admin_url('admin.php?page=ci_estore');
$admin_url = add_query_arg(array('tab' => 'admin_store', 'admin_tab' => 'products'), $page_url);

if ($can_estore_settings && $action === 'save_settings' && isset($_POST['ci_estore_settings_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ci_estore_settings_nonce'])), 'ci_estore_save_settings')) {
	$estore_settings = array(
		'notification_email' => sanitize_email(wp_unslash($_POST['notification_email'])),
		'from_email' => is_email($estore_settings['from_email']) ? sanitize_email($estore_settings['from_email']) : $ci_estore_default_from_email,
		'reply_to_email' => isset($_POST['reply_to_email']) ? sanitize_email(wp_unslash($_POST['reply_to_email'])) : '',
		'email_subject' => sanitize_text_field(wp_unslash($_POST['email_subject'])),
		'email_body' => sanitize_textarea_field(wp_unslash($_POST['email_body'])),
		'order_shipped_subject' => isset($_POST['order_shipped_subject']) ? sanitize_text_field(wp_unslash($_POST['order_shipped_subject'])) : $estore_settings_defaults['order_shipped_subject'],
		'order_shipped_body' => isset($_POST['order_shipped_body']) ? sanitize_textarea_field(wp_unslash($_POST['order_shipped_body'])) : $estore_settings_defaults['order_shipped_body'],
		'order_success_text' => sanitize_text_field(wp_unslash($_POST['order_success_text'])),
		'support_phone' => isset($_POST['support_phone']) ? sanitize_text_field(wp_unslash($_POST['support_phone'])) : '',
		'support_email' => isset($_POST['support_email']) ? sanitize_email(wp_unslash($_POST['support_email'])) : '',
	);
	update_option('ci_estore_settings', wp_parse_args($estore_settings, $estore_settings_defaults));
	wp_safe_redirect(add_query_arg(array('tab' => 'admin_store', 'admin_tab' => 'settings', 'message' => 'settings-updated'), $page_url));
	exit;
}

if ($can_store_admin && $action === 'save_product_order' && isset($_POST['ci_estore_order_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ci_estore_order_nonce'])), 'ci_estore_save_product_order')) {
	$product_order = isset($_POST['product_order']) ? explode(',', sanitize_text_field(wp_unslash($_POST['product_order']))) : array();
	$display_order = 1;
	foreach ($product_order as $ordered_product_id) {
		$ordered_product_id = absint($ordered_product_id);
		if ($ordered_product_id > 0) {
			$wpdb->update($table_name, array('display_order' => $display_order), array('ID' => $ordered_product_id));
			$display_order++;
		}
	}
	wp_safe_redirect(add_query_arg(array('tab' => 'admin_store', 'admin_tab' => 'products', 'message' => 'order-updated'), $page_url));
	exit;
}

if ($can_store_admin && $action === 'import_demo_products' && isset($_POST['ci_estore_import_demo_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ci_estore_import_demo_nonce'])), 'ci_estore_import_demo_products')) {
	$product_count = absint($wpdb->get_var("SELECT COUNT(*) FROM `{$table_name}`"));
	if ($product_count > 0) {
		wp_safe_redirect(add_query_arg(array('tab' => 'admin_store', 'admin_tab' => 'products', 'message' => 'demo-skipped'), $page_url));
		exit;
	}
	$category_label_map = array();
	foreach ($store_categories as $category_key => $category_label) $category_label_map[sanitize_key($category_label)] = $category_key;
	$display_order = 1;
	foreach ($ci_estore_demo_products as $demo_product) {
		$demo_category = sanitize_key($demo_product['category']);
		if (!empty($store_categories) && !isset($store_categories[$demo_category])) $demo_category = isset($category_label_map[$demo_category]) ? $category_label_map[$demo_category] : key($store_categories);
		$wpdb->insert($table_name, array(
			'title' => sanitize_text_field($demo_product['title']),
			'sku' => sanitize_text_field($demo_product['sku']),
			'image' => esc_url_raw($demo_product['image']),
			'category' => $demo_category,
			'max_qty' => max(1, absint($demo_product['max_qty'])),
			'display_order' => $display_order,
			'description' => wp_kses_post($demo_product['description']),
			'created' => current_time('mysql'),
			'updated' => current_time('mysql'),
		));
		$display_order++;
	}
	wp_safe_redirect(add_query_arg(array('tab' => 'admin_store', 'admin_tab' => 'products', 'message' => 'demo-imported'), $page_url));
	exit;
}

if ($action === 'place_order' && isset($_POST['ci_estore_order_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ci_estore_order_nonce'])), 'ci_estore_place_order')) {
	$cart = json_decode(wp_unslash($_POST['cart']), true);
	$cart = is_array($cart) ? $cart : array();
	$clinic_id = absint(get_user_meta(get_current_user_id(), '_use_current_clinic_id', true));
	if ($clinic_id < 1) $clinic_id = absint(get_user_meta(get_current_user_id(), '_clinic_id', true));
	if ($clinic_id < 1) $clinic_id = absint($wpdb->get_var($wpdb->prepare('SELECT ma_cli_ID FROM ci_clinic_manager WHERE ma_user_ID = %d ORDER BY ma_ID ASC LIMIT 1', get_current_user_id())));
	if (empty($cart)) {
		$notice = 'Add at least one product before placing an order.';
	} else {
		$wpdb->insert($orders_table, array('clinic_id' => $clinic_id, 'user_id' => get_current_user_id(), 'status' => 'Pending', 'order_notes' => sanitize_textarea_field(wp_unslash($_POST['order_notes'])), 'created' => current_time('mysql'), 'updated' => current_time('mysql')));
		$order_id = $wpdb->insert_id;
		foreach ($cart as $item) {
			$product_id = absint($item['id']);
			$product_row = $wpdb->get_row($wpdb->prepare("SELECT ID, title, sku, category, max_qty FROM `{$table_name}` WHERE ID = %d", $product_id), ARRAY_A);
			$quantity = min(max(1, absint($item['qty'])), $product_row ? absint($product_row['max_qty']) : 1);
			if ($product_row) $wpdb->insert($order_items_table, array('order_id' => $order_id, 'product_id' => $product_row['ID'], 'title' => $product_row['title'], 'sku' => $product_row['sku'], 'category' => $product_row['category'], 'ordered_qty' => $quantity, 'fulfilled_qty' => $quantity));
		}
		if (is_email($estore_settings['notification_email'])) {
			$order_items = $wpdb->get_col($wpdb->prepare("SELECT CONCAT(title, ' ×', ordered_qty) FROM `{$order_items_table}` WHERE order_id = %d ORDER BY ID ASC", $order_id));
			$clinic_name = $clinic_id ? $wpdb->get_var($wpdb->prepare('SELECT cli_clinic FROM ci_clinic WHERE cID = %d', $clinic_id)) : 'Unassigned clinic';
			$email_tokens = array('{order_id}', '{clinic_name}', '{items}');
			$email_values = array($order_id, $clinic_name, implode(', ', $order_items));
			$email_headers = array();
			if (is_email($estore_settings['from_email'])) $email_headers[] = 'From: Supply Store <' . $estore_settings['from_email'] . '>';
			if (is_email($estore_settings['reply_to_email'])) $email_headers[] = 'Reply-To: ' . $estore_settings['reply_to_email'];
			wp_mail($estore_settings['notification_email'], str_replace($email_tokens, $email_values, $estore_settings['email_subject']), str_replace($email_tokens, $email_values, $estore_settings['email_body']), $email_headers);
		}
		wp_safe_redirect(add_query_arg(array('message' => 'order-submitted'), $page_url));
		exit;
	}
}

if ($can_store_admin && $action === 'save_fulfillment' && isset($_POST['ci_estore_fulfillment_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ci_estore_fulfillment_nonce'])), 'ci_estore_save_fulfillment')) {
	$order_id = absint($_POST['order_id']);
	$status = sanitize_text_field(wp_unslash($_POST['status']));
	$ship_date = sanitize_text_field(wp_unslash($_POST['ship_date']));
	if (!in_array($status, array('Pending', 'Processing', 'Shipped', 'Cancelled'), true)) $status = 'Pending';
	$wpdb->update($orders_table, array('status' => $status, 'lab_notes' => sanitize_textarea_field(wp_unslash($_POST['lab_notes'])), 'tracking' => sanitize_text_field(wp_unslash($_POST['tracking'])), 'ship_date' => $ship_date !== '' ? $ship_date : null, 'updated' => current_time('mysql')), array('ID' => $order_id));
	if (!empty($_POST['fulfilled_qty']) && is_array($_POST['fulfilled_qty'])) foreach ($_POST['fulfilled_qty'] as $item_id => $quantity) $wpdb->update($order_items_table, array('fulfilled_qty' => max(0, absint($quantity))), array('ID' => absint($item_id), 'order_id' => $order_id));
	if ($status === 'Shipped') {
		$order_email = $wpdb->get_var($wpdb->prepare("SELECT u.user_email FROM `{$orders_table}` o INNER JOIN `{$wpdb->users}` u ON u.ID = o.user_id WHERE o.ID = %d", $order_id));
		if (is_email($order_email)) {
			$order_clinic_name = $wpdb->get_var($wpdb->prepare("SELECT c.cli_clinic FROM `{$orders_table}` o LEFT JOIN ci_clinic c ON c.cID = o.clinic_id WHERE o.ID = %d", $order_id));
			$email_headers = array();
			if (is_email($estore_settings['from_email'])) $email_headers[] = 'From: Supply Store <' . $estore_settings['from_email'] . '>';
			if (is_email($estore_settings['reply_to_email'])) $email_headers[] = 'Reply-To: ' . $estore_settings['reply_to_email'];
			$tokens = array('{order_id}', '{clinic_name}', '{tracking}', '{ship_date}');
			$values = array($order_id, $order_clinic_name, sanitize_text_field(wp_unslash($_POST['tracking'])), $ship_date);
			wp_mail($order_email, str_replace($tokens, $values, $estore_settings['order_shipped_subject']), str_replace($tokens, $values, $estore_settings['order_shipped_body']), $email_headers);
		}
	}
	wp_safe_redirect(add_query_arg(array('tab' => 'admin_store', 'admin_tab' => 'fulfillment', 'message' => 'fulfillment-updated'), $page_url));
	exit;
}

if ($can_store_admin && $action === 'save' && isset($_POST['ci_estore_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ci_estore_nonce'])), 'ci_estore_save')) {
	$image = isset($_POST['image']) ? esc_url_raw(wp_unslash($_POST['image'])) : '';
	if (!empty($_FILES['image_file']['tmp_name'])) {
		require_once(ABSPATH . 'wp-admin/includes/file.php');
		require_once(ABSPATH . 'wp-admin/includes/media.php');
		require_once(ABSPATH . 'wp-admin/includes/image.php');
		$attachment_id = media_handle_upload('image_file', 0);
		if (!is_wp_error($attachment_id)) $image = wp_get_attachment_url($attachment_id);
	}
	$data = array(
		'title' => sanitize_text_field(wp_unslash($_POST['title'])),
		'sku' => sanitize_text_field(wp_unslash($_POST['sku'])),
		'image' => $image,
		'category' => sanitize_key(wp_unslash($_POST['category'])),
		'max_qty' => max(1, absint($_POST['max_qty'])),
		'description' => wp_kses_post(wp_unslash($_POST['description'])),
		'updated' => current_time('mysql'),
	);
	if ($data['title'] === '') {
		$notice = 'Product title is required.';
	} else if (!isset($store_categories[$data['category']])) {
		$notice = 'Choose an enabled LIS test type for the product category.';
	} else if ($product_id > 0) {
		$wpdb->update($table_name, $data, array('ID' => $product_id));
		wp_safe_redirect(add_query_arg('message', 'updated', $admin_url));
		exit;
	} else {
		$data['display_order'] = absint($wpdb->get_var("SELECT COALESCE(MAX(display_order), 0) + 1 FROM `{$table_name}`"));
		$data['created'] = current_time('mysql');
		$wpdb->insert($table_name, $data);
		wp_safe_redirect(add_query_arg('message', 'created', $admin_url));
		exit;
	}
}

if ($can_store_admin && $action === 'delete' && $product_id > 0 && isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'ci_estore_delete_' . $product_id)) {
	$wpdb->delete($table_name, array('ID' => $product_id));
	wp_safe_redirect(add_query_arg('message', 'deleted', $admin_url));
	exit;
}

$ci_estore_auto_import_notice = '';
$ci_estore_products_tab_requested = isset($_REQUEST['tab'], $_REQUEST['admin_tab']) && sanitize_key(wp_unslash($_REQUEST['tab'])) === 'admin_store' && sanitize_key(wp_unslash($_REQUEST['admin_tab'])) === 'products';
if ($can_store_admin && $action === '' && $ci_estore_products_tab_requested && absint($wpdb->get_var("SELECT COUNT(*) FROM `{$table_name}`")) === 0) {
	$ci_estore_old_product_table = $wpdb->prefix . 'wp_eStore_tbl';
	$ci_estore_old_product_table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $ci_estore_old_product_table));
	if ($ci_estore_old_product_table_exists === $ci_estore_old_product_table) {
		$ci_estore_old_product_columns = $wpdb->get_col("SHOW COLUMNS FROM `{$ci_estore_old_product_table}`", 0);
		$ci_estore_old_product_order = in_array('id', $ci_estore_old_product_columns, true) ? ' ORDER BY id ASC' : '';
		$ci_estore_old_products = $wpdb->get_results("SELECT * FROM `{$ci_estore_old_product_table}`{$ci_estore_old_product_order}", ARRAY_A);
		$ci_estore_category_map = array();
		foreach ($store_categories as $ci_estore_category_key => $ci_estore_category_label) {
			$ci_estore_category_map[sanitize_key($ci_estore_category_key)] = $ci_estore_category_key;
			$ci_estore_category_map[sanitize_key($ci_estore_category_label)] = $ci_estore_category_key;
		}
		$ci_estore_old_product_categories = array();
		$ci_estore_old_rel_table = $wpdb->prefix . 'wp_eStore_cat_prod_rel_tbl';
		$ci_estore_old_cat_table = $wpdb->prefix . 'wp_eStore_cat_tbl';
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $ci_estore_old_rel_table)) === $ci_estore_old_rel_table && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $ci_estore_old_cat_table)) === $ci_estore_old_cat_table) {
			$ci_estore_old_rel_columns = $wpdb->get_col("SHOW COLUMNS FROM `{$ci_estore_old_rel_table}`", 0);
			$ci_estore_old_cat_columns = $wpdb->get_col("SHOW COLUMNS FROM `{$ci_estore_old_cat_table}`", 0);
			$ci_estore_old_rel_prod_column = in_array('prod_id', $ci_estore_old_rel_columns, true) ? 'prod_id' : (in_array('product_id', $ci_estore_old_rel_columns, true) ? 'product_id' : '');
			$ci_estore_old_rel_cat_column = in_array('cat_id', $ci_estore_old_rel_columns, true) ? 'cat_id' : (in_array('category_id', $ci_estore_old_rel_columns, true) ? 'category_id' : '');
			$ci_estore_old_cat_id_column = in_array('cat_id', $ci_estore_old_cat_columns, true) ? 'cat_id' : (in_array('id', $ci_estore_old_cat_columns, true) ? 'id' : '');
			$ci_estore_old_cat_name_column = in_array('cat_name', $ci_estore_old_cat_columns, true) ? 'cat_name' : (in_array('name', $ci_estore_old_cat_columns, true) ? 'name' : '');
			if ($ci_estore_old_rel_prod_column !== '' && $ci_estore_old_rel_cat_column !== '' && $ci_estore_old_cat_id_column !== '' && $ci_estore_old_cat_name_column !== '') {
				$ci_estore_old_category_rows = $wpdb->get_results("SELECT rel.`{$ci_estore_old_rel_prod_column}` AS product_id, cat.`{$ci_estore_old_cat_name_column}` AS category_name FROM `{$ci_estore_old_rel_table}` rel INNER JOIN `{$ci_estore_old_cat_table}` cat ON cat.`{$ci_estore_old_cat_id_column}` = rel.`{$ci_estore_old_rel_cat_column}`", ARRAY_A);
				foreach ($ci_estore_old_category_rows as $ci_estore_old_category_row) $ci_estore_old_product_categories[absint($ci_estore_old_category_row['product_id'])] = $ci_estore_old_category_row['category_name'];
			}
		}
		$ci_estore_imported_count = 0;
		$ci_estore_display_order = 1;
		foreach ($ci_estore_old_products as $ci_estore_old_product) {
			$ci_estore_old_title = '';
			foreach (array('name', 'product_name', 'title') as $ci_estore_old_title_column) if ($ci_estore_old_title === '' && isset($ci_estore_old_product[$ci_estore_old_title_column])) $ci_estore_old_title = $ci_estore_old_product[$ci_estore_old_title_column];
			$ci_estore_old_title = sanitize_text_field($ci_estore_old_title);
			if ($ci_estore_old_title === '') continue;
			$ci_estore_old_sku = '';
			foreach (array('product_code', 'sku', 'item_number', 'item_no') as $ci_estore_old_sku_column) if ($ci_estore_old_sku === '' && isset($ci_estore_old_product[$ci_estore_old_sku_column])) $ci_estore_old_sku = $ci_estore_old_product[$ci_estore_old_sku_column];
			if ($ci_estore_old_sku === '' && isset($ci_estore_old_product['id'])) $ci_estore_old_sku = 'OLD-' . absint($ci_estore_old_product['id']);
			$ci_estore_old_image = '';
			foreach (array('thumbnail_url', 'thumbnail', 'image_url', 'product_image', 'image') as $ci_estore_old_image_column) if ($ci_estore_old_image === '' && isset($ci_estore_old_product[$ci_estore_old_image_column])) $ci_estore_old_image = trim($ci_estore_old_product[$ci_estore_old_image_column]);
			if ($ci_estore_old_image !== '' && strpos($ci_estore_old_image, '://') === false) $ci_estore_old_image = strpos($ci_estore_old_image, '/') === 0 ? home_url($ci_estore_old_image) : home_url('/' . ltrim($ci_estore_old_image, '/'));
			$ci_estore_old_description = '';
			foreach (array('description', 'product_description', 'details') as $ci_estore_old_description_column) if ($ci_estore_old_description === '' && isset($ci_estore_old_product[$ci_estore_old_description_column])) $ci_estore_old_description = $ci_estore_old_product[$ci_estore_old_description_column];
			$ci_estore_old_max_qty = 10;
			foreach (array('available_copies', 'available_qty', 'quantity', 'qty') as $ci_estore_old_qty_column) if (isset($ci_estore_old_product[$ci_estore_old_qty_column]) && absint($ci_estore_old_product[$ci_estore_old_qty_column]) > 0) {
				$ci_estore_old_max_qty = absint($ci_estore_old_product[$ci_estore_old_qty_column]);
				break;
			}
			$ci_estore_old_category = '';
			if (isset($ci_estore_old_product['id'], $ci_estore_old_product_categories[absint($ci_estore_old_product['id'])])) $ci_estore_old_category = $ci_estore_old_product_categories[absint($ci_estore_old_product['id'])];
			foreach (array('category', 'product_category', 'cat_name') as $ci_estore_old_category_column) if ($ci_estore_old_category === '' && isset($ci_estore_old_product[$ci_estore_old_category_column])) $ci_estore_old_category = $ci_estore_old_product[$ci_estore_old_category_column];
			$ci_estore_category = sanitize_key($ci_estore_old_category);
			if (!empty($store_categories) && !isset($store_categories[$ci_estore_category])) $ci_estore_category = isset($ci_estore_category_map[$ci_estore_category]) ? $ci_estore_category_map[$ci_estore_category] : key($store_categories);
			$wpdb->insert($table_name, array(
				'title' => $ci_estore_old_title,
				'sku' => sanitize_text_field($ci_estore_old_sku),
				'image' => esc_url_raw($ci_estore_old_image),
				'category' => $ci_estore_category,
				'max_qty' => max(1, $ci_estore_old_max_qty),
				'display_order' => $ci_estore_display_order,
				'description' => wp_kses_post($ci_estore_old_description),
				'created' => current_time('mysql'),
				'updated' => current_time('mysql'),
			));
			$ci_estore_imported_count++;
			$ci_estore_display_order++;
		}
		if ($ci_estore_imported_count > 0) $ci_estore_auto_import_notice = sprintf('%d products were imported from the old eStore.', $ci_estore_imported_count);
	}
}

$product = array('ID' => 0, 'title' => '', 'sku' => '', 'image' => '', 'category' => key($store_categories), 'max_qty' => 1, 'description' => '');
if ($action === 'edit' && $product_id > 0) {
	$product = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table_name}` WHERE ID = %d", $product_id), ARRAY_A);
	if (empty($product)) $product = array('ID' => 0, 'title' => '', 'sku' => '', 'image' => '', 'category' => key($store_categories), 'max_qty' => 1, 'description' => '');
}

$products = $wpdb->get_results("SELECT * FROM `{$table_name}` ORDER BY display_order ASC, category ASC, title ASC", ARRAY_A);
$orders = $wpdb->get_results("SELECT o.*, COALESCE(c.cli_clinic, 'Unassigned clinic') AS clinic_name, CONCAT_WS(', ', NULLIF(c.cli_street, ''), NULLIF(c.cli_city, ''), NULLIF(c.cli_state, ''), NULLIF(c.cli_zip, '')) AS clinic_address, c.`cli_om-phone` AS clinic_phone, c.`cli_om-email` AS clinic_email, u.user_email AS order_user_email, (SELECT ma_manager FROM ci_clinic_manager WHERE ma_cli_ID = o.clinic_id ORDER BY ma_ID ASC LIMIT 1) AS contact_name FROM `{$orders_table}` o LEFT JOIN ci_clinic c ON c.cID = o.clinic_id LEFT JOIN `{$wpdb->users}` u ON u.ID = o.user_id ORDER BY o.created DESC", ARRAY_A);
$ci_estore_activity_months = array();
for ($ci_estore_month_index = 0; $ci_estore_month_index < 3; $ci_estore_month_index++) {
	$ci_estore_month_timestamp = strtotime('-' . $ci_estore_month_index . ' months', current_time('timestamp'));
	$ci_estore_activity_months[date_i18n('Y-m', $ci_estore_month_timestamp)] = array(
		'label' => date_i18n('M', $ci_estore_month_timestamp),
		'from' => date_i18n('Y-m-01', $ci_estore_month_timestamp),
		'to' => date_i18n('Y-m-t', $ci_estore_month_timestamp),
	);
}
foreach ($orders as $order_index => $order) {
	$orders[$order_index]['items'] = $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$order_items_table}` WHERE order_id = %d ORDER BY ID ASC", $order['ID']), ARRAY_A);
	$orders[$order_index]['counts'] = array();
	$orders[$order_index]['breakdowns'] = array();
	foreach ($store_activity_types as $store_activity_type => $store_activity_label) {
		$orders[$order_index]['breakdowns'][$store_activity_type] = array();
		foreach ($ci_estore_activity_months as $ci_estore_month_key => $ci_estore_month_data) $orders[$order_index]['breakdowns'][$store_activity_type][$ci_estore_month_key] = array('label' => $ci_estore_month_data['label'], 'count' => 0);
		$ci_estore_total_sql = '';
		$ci_estore_total_args = array(absint($order['clinic_id']));
		if ($store_activity_type === 'tox_oral' || $store_activity_type === 'tox_urine') {
			$ci_estore_total_sql = "SELECT COUNT(*) FROM ci_batches ba WHERE ba.cli_ID = %d AND ba.type_of_specimen = %s";
			$ci_estore_total_args[] = $store_activity_type === 'tox_oral' ? 'oral' : 'urine';
		} else if (strpos($store_activity_type, 'pcr_') === 0) {
			$ci_estore_panel_key = str_replace('pcr_', '', $store_activity_type);
			$ci_estore_panel_id = isset($ci_estore_pcr_panel_ids[$ci_estore_panel_key]) ? absint($ci_estore_pcr_panel_ids[$ci_estore_panel_key]) : 0;
			$batch_table = function_exists('tr_pdd_get_table_by_btype') ? tr_pdd_get_table_by_btype('pcr') : '';
			if ($batch_table && $ci_estore_panel_id > 0) {
				$ci_estore_total_sql = "SELECT COUNT(*) FROM `{$batch_table}` ba WHERE ba.cli_ID = %d AND FIND_IN_SET(%s, ba.panel_tests_requested)";
				$ci_estore_total_args[] = 'GROUP_' . $ci_estore_panel_id;
			}
		} else {
			$batch_table = function_exists('tr_pdd_get_table_by_btype') ? tr_pdd_get_table_by_btype($store_activity_type) : '';
			if ($batch_table) $ci_estore_total_sql = "SELECT COUNT(*) FROM `{$batch_table}` ba WHERE ba.cli_ID = %d";
		}
		if ($ci_estore_total_sql !== '') {
			$orders[$order_index]['counts'][$store_activity_type] = absint($wpdb->get_var($wpdb->prepare($ci_estore_total_sql, $ci_estore_total_args)));
			foreach ($ci_estore_activity_months as $ci_estore_month_key => $ci_estore_month_data) {
				$ci_estore_month_args = $ci_estore_total_args;
				$ci_estore_month_args[] = $ci_estore_month_data['from'];
				$ci_estore_month_args[] = $ci_estore_month_data['to'];
				$orders[$order_index]['breakdowns'][$store_activity_type][$ci_estore_month_key]['count'] = absint($wpdb->get_var($wpdb->prepare($ci_estore_total_sql . ' AND ba.doc >= %s AND ba.doc <= %s', $ci_estore_month_args)));
			}
		} else {
			$orders[$order_index]['counts'][$store_activity_type] = 0;
		}
	}
}
$stats_product_rows = $wpdb->get_results($wpdb->prepare("SELECT oi.title, SUM(oi.ordered_qty) AS total_ordered, COUNT(DISTINCT oi.order_id) AS order_count FROM `{$order_items_table}` oi INNER JOIN `{$orders_table}` o ON o.ID = oi.order_id WHERE o.created >= DATE_SUB(%s, INTERVAL 3 MONTH) GROUP BY oi.title ORDER BY total_ordered DESC, oi.title ASC", current_time('mysql')), ARRAY_A);
$stats_summary = array('active' => 0, 'pending' => 0, 'processing' => 0, 'shipped' => 0);
$stats_clinics = array();
foreach ($orders as $order) {
	if (strtotime($order['created']) < strtotime('-3 months', current_time('timestamp'))) continue;
	$status_key = strtolower($order['status']);
	if (isset($stats_summary[$status_key])) $stats_summary[$status_key]++;
	if ($order['status'] !== 'Cancelled') $stats_summary['active']++;
	$clinic_key = $order['clinic_id'] ? $order['clinic_id'] : 'order-' . $order['ID'];
	if (!isset($stats_clinics[$clinic_key])) {
		$stats_clinics[$clinic_key] = array(
			'clinic_name' => $order['clinic_name'],
			'clinic_address' => $order['clinic_address'],
			'status' => $order['status'],
			'supply_items' => 0,
			'counts' => $order['counts'],
			'breakdowns' => $order['breakdowns'],
			'created' => $order['created'],
		);
	}
	if (strtotime($order['created']) > strtotime($stats_clinics[$clinic_key]['created'])) {
		$stats_clinics[$clinic_key]['status'] = $order['status'];
		$stats_clinics[$clinic_key]['created'] = $order['created'];
		$stats_clinics[$clinic_key]['counts'] = $order['counts'];
		$stats_clinics[$clinic_key]['breakdowns'] = $order['breakdowns'];
	}
	foreach ($order['items'] as $item) $stats_clinics[$clinic_key]['supply_items'] += absint($item['ordered_qty']);
}
$stats_clinics = array_values($stats_clinics);
$message = isset($_GET['message']) ? sanitize_key(wp_unslash($_GET['message'])) : '';
$toast_messages = array(
	'order-submitted' => array('Order submitted!', $estore_settings['order_success_text']),
	'created' => array('Product added!', 'The product is now available in Supply Store.'),
	'updated' => array('Product updated!', 'Your product changes have been saved.'),
	'fulfillment-updated' => array('Order updated!', 'Fulfillment details and order status have been saved.'),
	'deleted' => array('Product deleted!', 'The product has been removed from Supply Store.'),
	'settings-updated' => array('Settings updated!', 'Supply Store notification settings have been saved.'),
	'order-updated' => array('Product order updated!', 'The Supply Store product order has been saved.'),
	'demo-imported' => array('Sample products imported!', 'Sample Supply Store products have been added.'),
	'demo-skipped' => array('Import skipped.', 'Sample products can only be imported when the product list is empty.'),
);
$toast_message = isset($toast_messages[$message]) ? $toast_messages[$message] : null;
$active_tab_request = isset($_REQUEST['tab']) ? sanitize_key(wp_unslash($_REQUEST['tab'])) : '';
$active_tab = in_array($active_tab_request, array('admin_store', 'admin'), true) ? 'admin_store' : 'supply_store';
$active_admin_tab_request = isset($_REQUEST['admin_tab']) ? sanitize_key(wp_unslash($_REQUEST['admin_tab'])) : '';
$active_admin_tab = $active_admin_tab_request === 'products' ? 'products' : ($active_admin_tab_request === 'settings' && $can_estore_settings ? 'settings' : ($active_admin_tab_request === 'stats' ? 'stats' : 'fulfillment'));
if ($action === 'edit') {
	$active_tab = 'admin_store';
	$active_admin_tab = 'products';
}
if ($active_tab === 'admin_store' && !$can_store_admin) $active_tab = 'supply_store';
?>
<div class="wrap ci-estore" data-active-tab="<?php echo esc_attr($active_tab); ?>" data-active-admin-tab="<?php echo esc_attr($active_admin_tab); ?>">
	<div class="ci-estore-page-header">
		<h1>Supply Store</h1>
		<?php if ($can_store_admin) : ?><div class="ci-estore-tabs" role="tablist" aria-label="Store sections">
			<a class="ci-estore-tab<?php echo $active_tab === 'supply_store' ? ' is-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=ci_estore&tab=supply_store')); ?>">Supply Store</a>
			<a class="ci-estore-tab<?php echo $active_tab === 'admin_store' ? ' is-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=ci_estore&tab=admin_store&admin_tab=fulfillment')); ?>">Store Admin</a>
		</div><?php endif; ?>
	</div>

	<?php if (!empty($notice)) : ?><div class="notice notice-error"><p><?php echo esc_html($notice); ?></p></div><?php endif; ?>
	<?php if (!empty($ci_estore_auto_import_notice)) : ?><div class="notice notice-success"><p><?php echo esc_html($ci_estore_auto_import_notice); ?></p></div><?php endif; ?>

	<section class="ci-estore-panel<?php echo $active_tab === 'supply_store' ? ' is-active' : ''; ?>" data-store-panel="supply_store">
		<div class="ci-estore-store-layout">
			<div class="ci-estore-store-products">
				<?php if (empty($products)) : ?>
					<div class="ci-estore-empty"><span class="dashicons dashicons-cart"></span><strong>Your Supply Store is ready.</strong><p>Add products from Store Admin to begin.</p></div>
				<?php else : ?>
					<?php foreach ($products as $item) : ?>
						<article class="ci-estore-store-card" data-product-id="<?php echo esc_attr($item['ID']); ?>" data-product-title="<?php echo esc_attr($item['title']); ?>" data-product-sku="<?php echo esc_attr($item['sku']); ?>" data-product-category="<?php echo esc_attr($item['category'] !== '' ? $item['category'] : 'General supplies'); ?>" data-product-max="<?php echo esc_attr($item['max_qty']); ?>">
							<div class="ci-estore-store-image"><?php if ($item['image'] !== '') : ?><button type="button" class="ci-estore-image-popover-trigger" data-image-src="<?php echo esc_url($item['image']); ?>" data-image-title="<?php echo esc_attr($item['title']); ?>"><img src="<?php echo esc_url($item['image']); ?>" alt="<?php echo esc_attr($item['title']); ?>"></button><?php else : ?><span class="ci-estore-image-placeholder"><?php echo esc_html($item['title']); ?></span><?php endif; ?></div>
							<div class="ci-estore-store-card-body"><div><span class="ci-estore-category" data-category="<?php echo esc_attr(strtolower($item['category'] !== '' ? $item['category'] : 'general supplies')); ?>"><?php echo esc_html($item['category'] !== '' ? $store_category_label($item['category']) : 'General supplies'); ?></span><h2><?php echo esc_html($item['title']); ?></h2><?php if ($item['sku'] !== '') : ?><div class="ci-estore-store-sku"><?php echo esc_html($item['sku']); ?></div><?php endif; ?><?php if ($item['description'] !== '') : ?><div class="ci-estore-store-description"><?php echo wp_kses_post(wpautop($item['description'])); ?></div><?php endif; ?></div><div class="ci-estore-store-card-footer"><button type="button" class="ci-estore-quantity-button" data-quantity-action="decrease" aria-label="Decrease quantity" disabled><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"></path></svg></button><span class="ci-estore-product-quantity">0</span><button type="button" class="ci-estore-quantity-button" data-quantity-action="increase" aria-label="Increase quantity"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M12 5v14"></path></svg></button><span class="ci-estore-max-quantity">max <?php echo esc_html($item['max_qty']); ?></span></div></div>
						</article>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
			<aside class="ci-estore-cart-column"><div class="ci-estore-cart"><div class="ci-estore-cart-title"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg><span class="ci-estore-cart-label">Your Order</span><span class="ci-estore-cart-count">0</span></div><div class="ci-estore-cart-items"><p>No items added yet.</p></div><div class="ci-estore-cart-notes"><label for="ci-estore-comments">Notes (optional)</label><textarea id="ci-estore-comments" rows="2" placeholder="Any special requests…"></textarea></div><button type="button" class="ci-estore-checkout" disabled><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg>Place Order</button><form class="ci-estore-place-order-form" method="post" action="<?php echo esc_url($page_url); ?>"><input type="hidden" name="act" value="place_order"><input type="hidden" name="cart" value=""><input type="hidden" name="order_notes" value=""><?php wp_nonce_field('ci_estore_place_order', 'ci_estore_order_nonce'); ?></form></div><?php if ($estore_settings['support_phone'] !== '' || is_email($estore_settings['support_email'])) : ?><div class="ci-estore-help-box"><p>Need help?</p><?php if ($estore_settings['support_phone'] !== '') : ?><a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $estore_settings['support_phone'])); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg><?php echo esc_html($estore_settings['support_phone']); ?></a><?php endif; ?><?php if (is_email($estore_settings['support_email'])) : ?><a href="mailto:<?php echo esc_attr($estore_settings['support_email']); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path></svg><?php echo esc_html($estore_settings['support_email']); ?></a><?php endif; ?></div><?php endif; ?></aside>
		</div>
	</section>

		<?php if ($can_store_admin) : ?><section class="ci-estore-panel<?php echo $active_tab === 'admin_store' ? ' is-active' : ''; ?>" data-store-panel="admin_store">
			<?php
			$ci_estore_view_path = __DIR__ . '/ci-estore';
			require $ci_estore_view_path . '/admin-tabs.php';
			require $ci_estore_view_path . '/fulfillment.php';
			require $ci_estore_view_path . '/products.php';
			require $ci_estore_view_path . '/settings.php';
			require $ci_estore_view_path . '/stats.php';
			?>
</section><?php endif; ?>
	<div class="ci-estore-toast" role="status" aria-live="polite"><span class="dashicons dashicons-yes-alt"></span><div><strong><?php echo $toast_message ? esc_html($toast_message[0]) : ''; ?></strong><span><?php echo $toast_message ? esc_html($toast_message[1]) : ''; ?></span></div></div>
	<div class="ci-estore-image-popover" aria-hidden="true"><button type="button" class="ci-estore-image-popover-backdrop" data-image-popover-close></button><div class="ci-estore-image-popover-dialog"><button type="button" class="ci-estore-image-popover-close" data-image-popover-close aria-label="Close image preview"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"></path></svg></button><img src="" alt=""><p></p></div></div>
</div>
<script>
jQuery(function($) {
	var store = $('.ci-estore');
	var cart = {};
	var toastTimer;
	var popupIcons = {
		close: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>',
		truck: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"></path><path d="M15 18H9"></path><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path><circle cx="17" cy="18" r="2"></circle><circle cx="7" cy="18" r="2"></circle></svg>',
		save: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path><path d="M7 3v4a1 1 0 0 0 1 1h7"></path></svg>',
		chevron: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>'
	};
	store.find('.ci-estore-modal-head-actions [data-modal-close] svg').replaceWith(popupIcons.close);
	store.find('.ci-estore-modal-shipping label span svg').replaceWith(popupIcons.truck);
	store.find('.ci-estore-modal-save svg').replaceWith(popupIcons.save);
	store.find('.ci-estore-status-menu-toggle svg').replaceWith(popupIcons.chevron);
	store.on('click', '[data-template-tab]', function() { var template = $(this).data('template-tab'); store.find('[data-template-tab]').removeClass('is-active'); $(this).addClass('is-active'); store.find('[data-template-panel]').removeClass('is-active'); store.find('[data-template-panel="' + template + '"]').addClass('is-active'); });
	var productToolbar = $('<div class="ci-estore-product-toolbar"><div class="ci-estore-category-filters"><button type="button" class="is-active" data-product-filter="">All</button></div><label class="ci-estore-search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg><input type="search" data-product-search placeholder="Search products…"></label></div>');
	store.find('.ci-estore-store-layout').before(productToolbar);
	store.find('.ci-estore-store-card').each(function() { var category = $(this).data('product-category'); if (!productToolbar.find('[data-product-filter="' + category + '"]').length) productToolbar.find('.ci-estore-category-filters').append($('<button type="button">').attr('data-product-filter', category).text(category)); });
	function filterProducts() { var category = productToolbar.find('[data-product-filter].is-active').data('product-filter'); var query = productToolbar.find('[data-product-search]').val().toLowerCase(); store.find('.ci-estore-store-card').each(function() { var card = $(this); card.toggle((!category || card.data('product-category') === category) && (!query || card.text().toLowerCase().indexOf(query) !== -1)); }); }
	store.on('click', '[data-product-filter]', function() { productToolbar.find('[data-product-filter]').removeClass('is-active'); $(this).addClass('is-active'); filterProducts(); });
	store.on('input', '[data-product-search]', filterProducts);
	var fulfillmentTable = store.find('.ci-estore-table').not('.ci-estore-product-table'); var testTotalCount = fulfillmentTable.find('thead th.ci-estore-test-total').length;
	if (fulfillmentTable.length && testTotalCount && !fulfillmentTable.find('.ci-estore-activity-row').length) fulfillmentTable.find('thead').append('<tr class="ci-estore-activity-row"><th colspan="3"></th><th colspan="' + testTotalCount + '">3-Month Test Activity</th><th colspan="3"></th></tr>');
	var statusSummary = store.find('.ci-estore-status-summary');
	store.on('click', '[data-status-filter]', function() { var selected = $(this).hasClass('is-active'); var status = selected ? '' : $(this).data('status-filter'); statusSummary.find('button').removeClass('is-active'); if (!selected) $(this).addClass('is-active'); fulfillmentTable.find('tbody .ci-estore-order-row').each(function() { $(this).toggle(!status || $(this).find('.ci-estore-status').text().trim() === status); }); });
	store.on('click', '.ci-estore-status-sort', function() { var rows = fulfillmentTable.find('tbody .ci-estore-order-row').get(); var ascending = !$(this).data('ascending'); $(this).data('ascending', ascending).attr('data-sort-order', ascending ? 'ascending' : 'descending'); rows.sort(function(a, b) { var av = $(a).find('.ci-estore-status').text(); var bv = $(b).find('.ci-estore-status').text(); return ascending ? av.localeCompare(bv) : bv.localeCompare(av); }); $.each(rows, function(_, row) { fulfillmentTable.find('tbody').append(row); }); });
	store.on('click', '.ci-estore-order-row', function(event) { if ($(event.target).closest('button, a, input, textarea, select').length) return; $(this).find('.ci-estore-fulfillment-edit').trigger('click'); });
	function setStoreTab(tab) { store.find('[data-store-tab]').removeClass('is-active'); store.find('[data-store-tab="' + tab + '"]').addClass('is-active'); store.find('[data-store-panel]').removeClass('is-active'); store.find('[data-store-panel="' + tab + '"]').addClass('is-active'); var url = new URL(window.location.href); url.searchParams.set('tab', tab); if (tab === 'admin_store') { if (!url.searchParams.get('admin_tab')) url.searchParams.set('admin_tab', 'fulfillment'); } else { url.searchParams.delete('admin_tab'); } window.history.replaceState({}, '', url.toString()); }
	function setAdminTab(tab) { store.find('[data-admin-tab]').removeClass('is-active'); store.find('[data-admin-tab="' + tab + '"]').addClass('is-active'); store.find('[data-admin-panel]').removeClass('is-active'); store.find('[data-admin-panel="' + tab + '"]').addClass('is-active'); var url = new URL(window.location.href); url.searchParams.set('tab', 'admin_store'); url.searchParams.set('admin_tab', tab); window.history.replaceState({}, '', url.toString()); }
	function renderCart() { var items = Object.keys(cart); var total = 0; var holder = store.find('.ci-estore-cart-items'); holder.empty(); store.find('.ci-estore-store-card').each(function() { var card = $(this); var item = cart[card.data('product-id')]; var quantity = item ? item.qty : 0; card.find('.ci-estore-product-quantity').text(quantity).toggleClass('is-in-cart', quantity > 0); card.find('[data-quantity-action="decrease"]').prop('disabled', quantity === 0); card.find('[data-quantity-action="increase"]').prop('disabled', quantity >= parseInt(card.data('product-max'), 10)); }); if (!items.length) { holder.html('<p>No items added yet.</p>'); } else { $.each(items, function(_, id) { total += cart[id].qty; var row = $('<div class="ci-estore-cart-item">'); var itemInfo = $('<div class="ci-estore-cart-item-info">').append($('<strong>').text(cart[id].title)).append($('<small>').text(cart[id].sku || cart[id].category)); var itemActions = $('<div class="ci-estore-cart-item-actions">').append($('<span>').text('×' + cart[id].qty)).append($('<button type="button" class="ci-estore-cart-remove" aria-label="Remove item">').attr('data-product-id', id).append($('<span class="dashicons dashicons-no-alt">'))); holder.append(row.append(itemInfo).append(itemActions)); }); } store.find('.ci-estore-cart').toggleClass('is-filled', items.length > 0); store.find('.ci-estore-cart-label').text('Your Order'); store.find('.ci-estore-cart-count').text(total); store.find('.ci-estore-checkout').prop('disabled', !items.length); }
	store.on('click', '[data-store-tab]', function() { setStoreTab($(this).data('store-tab')); });
	store.on('click', '[data-admin-tab]', function() { setAdminTab($(this).data('admin-tab')); });
	store.on('click', '.ci-estore-quantity-button', function() { var card = $(this).closest('.ci-estore-store-card'); var id = card.data('product-id'); var quantity = cart[id] ? cart[id].qty : 0; var max = parseInt(card.data('product-max'), 10); quantity += $(this).data('quantity-action') === 'increase' ? 1 : -1; quantity = Math.max(0, Math.min(quantity, max)); if (quantity === 0) delete cart[id]; else cart[id] = { title: card.data('product-title'), sku: card.data('product-sku'), category: card.data('product-category'), qty: quantity }; renderCart(); });
	store.on('click', '.ci-estore-cart-remove', function() { delete cart[$(this).data('product-id')]; renderCart(); });
	store.on('click', '.ci-estore-checkout', function() { if (!Object.keys(cart).length) return; var orderItems = $.map(cart, function(item, id) { return { id: id, qty: item.qty }; }); var orderForm = store.find('.ci-estore-place-order-form'); orderForm.find('[name="cart"]').val(JSON.stringify(orderItems)); orderForm.find('[name="order_notes"]').val(store.find('#ci-estore-comments').val()); orderForm.trigger('submit'); });
	store.on('click', '.ci-estore-fulfillment-edit', function() { var modal = store.find('[data-order-modal-content="' + $(this).data('order-modal') + '"]'); modal.addClass('is-open').attr('aria-hidden', 'false'); });
	store.on('click', '[data-modal-close]', function() { $(this).closest('.ci-estore-modal').removeClass('is-open').attr('aria-hidden', 'true'); });
	store.find('.ci-estore-status-menu').each(function() { var menu = $(this); menu.find('button').each(function() { $(this).text('Save as ' + $(this).data('status')); }); if (!menu.find('[data-status="Cancelled"]').length) menu.append($('<button type="button" data-status="Cancelled">Save as Cancelled</button>')); });
	store.on('click', '.ci-estore-status-menu-toggle', function() { var statusSave = $(this).closest('.ci-estore-status-save'); var willOpen = !statusSave.hasClass('is-open'); store.find('.ci-estore-status-save').removeClass('is-open').find('.ci-estore-status-menu-toggle').attr('aria-expanded', 'false'); statusSave.toggleClass('is-open', willOpen); $(this).attr('aria-expanded', willOpen ? 'true' : 'false'); });
	store.on('click', '.ci-estore-status-menu button', function() { var statusSave = $(this).closest('.ci-estore-status-save'); statusSave.find('[name="status"]').val($(this).data('status')); statusSave.find('.ci-estore-save-label').text($(this).data('status')); statusSave.removeClass('is-open').find('.ci-estore-status-menu-toggle').attr('aria-expanded', 'false'); statusSave.closest('form').trigger('submit'); });
	$(document).on('click', function(event) { if (!$(event.target).closest('.ci-estore-status-save').length) store.find('.ci-estore-status-save').removeClass('is-open').find('.ci-estore-status-menu-toggle').attr('aria-expanded', 'false'); });
	store.on('click', '.ci-estore-open-product', function() { store.find('.ci-estore-product-form').addClass('is-open'); });
	store.on('click', '.ci-estore-delete', function() { return window.confirm('Delete this product?'); });
	store.on('input', '[data-search-target]', function() { var query = $(this).val().toLowerCase(); $($(this).data('search-target')).each(function() { $(this).toggle($(this).text().toLowerCase().indexOf(query) !== -1); }); });
	store.on('click', '.ci-estore-image-popover-trigger', function() { var popover = store.find('.ci-estore-image-popover'); popover.find('img').attr({ src: $(this).data('image-src'), alt: $(this).data('image-title') }); popover.find('p').text($(this).data('image-title')); popover.addClass('is-open').attr('aria-hidden', 'false'); });
	store.on('click', '[data-image-popover-close]', function() { var popover = store.find('.ci-estore-image-popover'); popover.removeClass('is-open').attr('aria-hidden', 'true'); popover.find('img').attr({ src: '', alt: '' }); });
	store.on('change', '#ci-estore-image', function() { var input = this; var preview = store.find('.ci-estore-product-image-preview'); if (!input.files || !input.files[0]) return; var reader = new FileReader(); reader.onload = function(event) { preview.html($('<img>').attr({ src: event.target.result, alt: 'Product image preview' })); }; reader.readAsDataURL(input.files[0]); });
	var draggedProductRow = null;
	store.on('dragstart', '.ci-estore-product-row', function(event) { draggedProductRow = this; $(this).addClass('is-dragging'); event.originalEvent.dataTransfer.effectAllowed = 'move'; });
	store.on('dragover', '.ci-estore-product-row', function(event) { event.preventDefault(); if (this !== draggedProductRow) $(this).addClass('is-drop-target'); });
	store.on('dragleave', '.ci-estore-product-row', function() { $(this).removeClass('is-drop-target'); });
	store.on('drop', '.ci-estore-product-row', function(event) { event.preventDefault(); if (draggedProductRow && this !== draggedProductRow) { var rows = store.find('.ci-estore-product-sortable .ci-estore-product-row'); if (rows.index(draggedProductRow) < rows.index(this)) $(this).after(draggedProductRow); else $(this).before(draggedProductRow); var order = store.find('.ci-estore-product-sortable .ci-estore-product-row').map(function() { return $(this).data('product-id'); }).get(); store.find('.ci-estore-product-order-form [name="product_order"]').val(order.join(',')); store.find('.ci-estore-product-order-form').trigger('submit'); } store.find('.ci-estore-product-row').removeClass('is-drop-target is-dragging'); draggedProductRow = null; });
	store.on('dragend', '.ci-estore-product-row', function() { store.find('.ci-estore-product-row').removeClass('is-drop-target is-dragging'); draggedProductRow = null; });
	<?php if ($toast_message) : ?>store.find('.ci-estore-toast').addClass('is-visible'); toastTimer = window.setTimeout(function() { store.find('.ci-estore-toast').removeClass('is-visible'); }, 4000);<?php endif; ?>
});
</script>
