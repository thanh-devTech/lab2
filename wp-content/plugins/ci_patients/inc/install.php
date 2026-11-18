<?php
global $wpdb, $blog_id;
if ($blog_id == 1) return;


$wpdb->suppress_errors(true);

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');


$sql_notify = "CREATE TABLE `ci_sent_notify` (
 `ID` int(11) NOT NULL AUTO_INCREMENT,
 `pa_ID` int(11),
 `btype` varchar(10) default 'tox',
 `send_type` varchar(5) default 'rs',
 `mid` int(11),
 `created` datetime,
  PRIMARY KEY (`ID`),
  KEY `mid_btype` (`mid`,`btype`),
  KEY `send_type` (`send_type`)
) ENGINE=InnoDB";
$rs = dbDelta($sql_notify);


$sql_tbl = "CREATE TABLE `ci_scheduling` (
 `ID` int(11) NOT NULL AUTO_INCREMENT,
edate date,
etime varchar(6),
max_number int(5) default 0,
count_req int(5) default 0,
created datetime,
  PRIMARY KEY (`ID`),
  KEY `edate` (`edate`),
  KEY `max_number` (`max_number`),
  KEY `count_req` (`count_req`)
) ENGINE=InnoDB";
$rs = dbDelta($sql_tbl);

$sql_tbl = "CREATE TABLE `ci_emails` (
`ID` int(11) NOT NULL AUTO_INCREMENT,
`to` varchar(250),
`subject` text,
`message` text,
`headers` text,
`attachments` text,
`created` datetime,
`send_time` datetime,
`istatus` tinyint(1) default 0,
`run_in_seconds` int(10) default 0,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql_tbl);

$sql_tbl = "CREATE TABLE `ci_user_tokens` (
`user_id` int(11),
`os` varchar(10) default '',
`deviceID` varchar(50) default '',
`token` varchar(40),
`created` datetime,
`last_active` datetime,
KEY `token` (`token`)
) ENGINE=InnoDB";
$rs = dbDelta($sql_tbl);

$sql_tbl = "CREATE TABLE `ci_user_notifications` (
`ID` int(11) NOT NULL AUTO_INCREMENT,
`user_id` int(11),
`title` varchar(255) default '',
`message` text,
`type` varchar(20) default '',
`target` varchar(20) default '',
`viewed` tinyint(1) default 0,
`created` datetime,
PRIMARY KEY (`ID`)
) ENGINE=InnoDB";
$rs = dbDelta($sql_tbl);



$wpdb->query("alter table ci_clinic add column `cli_send_result_sms` tinyint(1) default 0");
$wpdb->query("alter table ci_clinic add column `cli_nowc_sms` tinyint(1) default 0");
$wpdb->query("alter table ci_clinic add column `cli_send_ocovid_sms` tinyint(1) default 0");

$adding_pa_cols = array(
	'pa_sport',
	'pa_marital_status',
	'pa_height',
	'pa_weight',
	'pa_sleep',
	'pa_exercise',
);

foreach ($adding_pa_cols as $colk) {
	$wpdb->query("alter table ci_patients add column `{$colk}` text");
	$wpdb->query("alter table ci_patients_history add column `{$colk}` text");
}


$wpdb->query("alter table {$wpdb->users} add column `patient_id` int(11)");
$wpdb->query("alter table {$wpdb->users} add key `patient_id` (`patient_id`)");
$wpdb->query("alter table {$wpdb->usermeta} add key `meta_value`(`meta_value`(30))");


$wpdb->query("alter table tr_log_sms add column patient_id int(11) ");
$wpdb->query("alter table tr_log_sms add key patient_id (patient_id) ");
$wpdb->query("alter table tr_log_sms add key assen (assen) ");


if (function_exists('tr_pdd_get_batch_types')) {
	foreach (tr_pdd_get_batch_types() as $btype) {
		$batch_tbl = tr_pdd_get_table_by_btype($btype);
		$wpdb->query("alter table {$batch_tbl} add column `priority_hours` int(3) default 0");
		$wpdb->query("alter table {$batch_tbl} add column `paid` tinyint(1) default 0");
		$wpdb->query("alter table {$batch_tbl} add column `patient_portal_type` varchar(5) default '' ");
	}
}




Tr_Base_Class_V4::create_role('patient', 'Patient', array(
	'ci_patient_results' => true,
));

$role = get_role('patient');
$role->add_cap('view_admin_dashboard');
$role->add_cap('ci_patient_results');


$role = get_role('administrator');
$role->add_cap('ci_patient_results');
$role->add_cap('ci_log_sms');


Tr_Base_Class_V4::create_role('order', 'Order', array(
	'assign_shop_coupon_terms' => true,
	'assign_shop_order_terms' => true,
	'ci_log_sms' => true,
	'delete_others_shop_coupons' => true,
	'delete_others_shop_orders' => true,
	'delete_private_shop_coupons' => true,
	'delete_private_shop_orders' => true,
	'delete_published_shop_coupons' => true,
	'delete_published_shop_orders' => true,
	'delete_shop_coupon' => true,
	'delete_shop_coupon_terms' => true,
	'delete_shop_coupons' => true,
	'delete_shop_order' => true,
	'delete_shop_order_terms' => true,
	'delete_shop_orders' => true,
	'edit_others_shop_coupons' => true,
	'edit_others_shop_orders' => true,
	'edit_private_shop_coupons' => true,
	'edit_private_shop_orders' => true,
	'edit_published_shop_coupons' => true,
	'edit_published_shop_orders' => true,
	'edit_shop_coupon' => true,
	'edit_shop_coupon_terms' => true,
	'edit_shop_coupons' => true,
	'edit_shop_order' => true,
	'edit_shop_order_terms' => true,
	'edit_shop_orders' => true,
	'manage_shop_coupon_terms' => true,
	'manage_shop_order_terms' => true,
	'publish_shop_coupons' => true,
	'publish_shop_orders' => true,
	'read' => true,
	'read_private_shop_coupons' => true,
	'read_private_shop_orders' => true,
	'read_shop_coupon' => true,
	'read_shop_order' => true,
));

if ($blog_id > 1) {
	$exclude_roles = array('administrator', 'patient', 'order');
	$all_roles      = get_editable_roles();
	foreach ($all_roles as $role_name => $role_info) {
		if (stripos($role_name, 'shop') !== false) {
			continue;
		}
		if (!in_array($role_name, $exclude_roles)) {
			Tr_Base_Class_V4::remove_role($role_name);
		}
	}
}
if (isset($_GET['remove_roles'])) {

	Tr_Base_Class_V4::remove_role('lab_tech');
	Tr_Base_Class_V4::remove_role('ref_lab_tech');
	Tr_Base_Class_V4::remove_role('reflab_manager');
	Tr_Base_Class_V4::remove_role('lab');
	Tr_Base_Class_V4::remove_role('labb');
	Tr_Base_Class_V4::remove_role('lab_director');
	Tr_Base_Class_V4::remove_role('lab_manager');
	Tr_Base_Class_V4::remove_role('lab_rep');
	Tr_Base_Class_V4::remove_role('lab_rep_w2');
	Tr_Base_Class_V4::remove_role('labmanager');
	Tr_Base_Class_V4::remove_role('doctor');
	Tr_Base_Class_V4::remove_role('Phlebotomist');
	Tr_Base_Class_V4::remove_role('collector');
	Tr_Base_Class_V4::remove_role('office_manager');
	Tr_Base_Class_V4::remove_role('multi_site_manager');
	Tr_Base_Class_V4::remove_role('labsecman');
	Tr_Base_Class_V4::remove_role('leadlabsecman');
	Tr_Base_Class_V4::remove_role('lab_sec');
	Tr_Base_Class_V4::remove_role('ResultsUpload');
	Tr_Base_Class_V4::remove_role('RefManager');
	Tr_Base_Class_V4::remove_role('sales_accounting');
	Tr_Base_Class_V4::remove_role('clinic');
	Tr_Base_Class_V4::remove_role('scientist');
	Tr_Base_Class_V4::remove_role('shipping');
	Tr_Base_Class_V4::remove_role('Billing');
	Tr_Base_Class_V4::remove_role('distributor');
	Tr_Base_Class_V4::remove_role('results');
	Tr_Base_Class_V4::remove_role('pcr_verify');
	Tr_Base_Class_V4::remove_role('BloodLabManager');
	Tr_Base_Class_V4::remove_role('administrator_p');
	Tr_Base_Class_V4::remove_role('wp_admin');
	Tr_Base_Class_V4::remove_role('user_management');
	Tr_Base_Class_V4::remove_role('AdminCIDW');
	Tr_Base_Class_V4::remove_role('IT');
	Tr_Base_Class_V4::remove_role('Lab_Reporting');
}

update_option('TRCPPR_V', TRCPPR_V, false);



wp_clear_scheduled_hook('cron_sync_patient');
wp_schedule_event(time() + 200, 'hourly', 'cron_sync_patient');


wp_clear_scheduled_hook('cron_sync_billing');
wp_schedule_event(time() + 400, 'hourly', 'cron_sync_billing');


wp_clear_scheduled_hook('cron_check_user_notifications');
wp_schedule_event(time() + 400, '4min', 'cron_check_user_notifications');

$rs = $wpdb->get_results("select u.ID,
                            m.meta_value as patient_id
                            from {$wpdb->users} u
					join {$wpdb->usermeta} m ON m.user_id = u.ID and m.meta_key='_patient_id'
					where (u.patient_id ='' or u.patient_id is null)
					and m.meta_value!=''
					");
foreach ($rs as $r) {
	if (!empty($r->patient_id)) {
		$rs2 = $wpdb->update($wpdb->users, array('patient_id' => $r->patient_id), array('ID' => $r->ID));
		if ($rs2) {
			$wpdb->delete($wpdb->usermeta, array('user_id' => $r->ID, 'meta_key' => '_patient_id'));
		}
	}
}

update_option('woocommerce_myaccount_lost_password_endpoint', '');

//disable comment
update_option('comments_notify', 0);
update_option('default_comment_status', 0);
update_option('default_ping_status', 0);
update_option('default_pingback_flag', 0);

update_option('require_name_email', 1);
update_option('comment_registration', 1);
update_option('close_comments_for_old_posts', 1);
update_option('close_comments_days_old', 1);
update_option('comment_moderation', 1);
update_option('comment_previously_approved', 1);

$wpdb->query("upldate {$wpdb->posts} set comment_status='closed', ping_status='closed' ");



wp_clear_scheduled_hook('cron_check_covid_status');
wp_clear_scheduled_hook('ci_cron_clean_results');
wp_clear_scheduled_hook('upgrader_scheduled_cleanup');
wp_clear_scheduled_hook('ci_today_batch_cron');
wp_clear_scheduled_hook('ci_auto_upload_results');
wp_clear_scheduled_hook('ci_fix_db');
wp_clear_scheduled_hook('cron_receiving_batch_id');
wp_clear_scheduled_hook('ci_check_pdf_card_cron');
wp_clear_scheduled_hook('ci_check_upload_apis');
wp_clear_scheduled_hook('ci_check_upload_apis');
wp_clear_scheduled_hook('ci_cron_sync_wsdl_client');
wp_clear_scheduled_hook('ci_cron_sync_wsdl_client_req');
wp_clear_scheduled_hook('ci_cron_sync_wsdl_get_ref_lab_reqs');
wp_clear_scheduled_hook('ci_cron_sync_wsdl_client_result');
wp_clear_scheduled_hook('cron_all_api', array('CI_Api_Clean_Req'));
wp_clear_scheduled_hook('ci_auto_fax_results');
wp_clear_scheduled_hook('ci_cron_2mins');

//fix intl_message
$old_options = get_option('otpa_bandwidth_sms_gateway_settings', array());
if (isset($old_options['intl_message'])) {
	$labname = $wpdb->get_var("select lab_name from ci_labs order by lab_ID asc limit 1");
	if (!empty($labname)) {
		$old_options['intl_message'] = str_replace('ARC Diagnostics', $labname, $old_options['intl_message']);
	}


	update_option('otpa_bandwidth_sms_gateway_settings', $old_options);
}
