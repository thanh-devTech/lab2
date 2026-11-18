<?php
global $wpdb;
global $is_ci_admin;

//check db version
$db_version = get_option('TRCPPR_V');
if (TRCPPR_V != $db_version) {
	trcpp_active_plugin();
}

$act = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';
if ($act == 'import_locations') {
	include('admin_import_locations.php');
}


$can_send_sms = function_exists('otpa_get_active_gateway_class_name')
	&& otpa_get_active_gateway_class_name() == 'Otpa_Bandwidth_Sms_Gateway';


$tabs = array(
	'main' => 'Main',
	'newuser' => 'New User',
	'sms_notify' => 'Notify',
	'results_page' => 'Results Page',
	'group_testing' => 'Group Testing',
	'parental_agree' => 'Agreements',
	'register_page' => 'Order Form',
	'concierge' => 'Concierge',
	'canada' => 'Canada',
	'meijer' => 'Meijer',
	'change_payment_methods' => 'Payment Methods'
);



$pa_options = get_option('trpatient_settings', array());
if (!isset($pa_options['enable_concierge']) || !$pa_options['enable_concierge']) {
	unset($tabs['concierge']);
}
if (!isset($pa_options['enable_meijer']) || !$pa_options['enable_meijer']) {
	unset($tabs['meijer']);
}
if (!isset($pa_options['enable_canada']) || !$pa_options['enable_canada']) {
	unset($tabs['canada']);
}
if (!$can_send_sms) {
	unset($tabs['sms_notify']);
}

if (!isset($pa_options['allow_change_payments']) || !$pa_options['allow_change_payments']) {
	unset($tabs['change_payment_methods']);
}

if (isset($pa_options['formtypes']) && is_array($pa_options['formtypes'])) {
	foreach ($pa_options['formtypes'] as $arr) {
		$tabs['cform_' . $arr['value']] = $arr['title'];
	}
}

if (isset($_GET['new_site']) || isset($_GET['debug'])) {
	$tabs['import'] = 'Import/Export';
}
$tabs['qr'] = 'QR Generator';



$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);
if (!isset($tabs[$tab])) {
	$tab = key($tabs);
}
if (!isset($_REQUEST['trajax'])) {
?>
	<div class="wrap">
		<h2 class="nav-tab-wrapper ">
			<?php
			foreach ($tabs as $k => $txt) {
				$class = $tab == $k ? 'nav-tab-active' : '';
				$link = Tr_Base_Class_V4::link(array('tab' => $k, 'act' => '', 'id' => '', 'batch_id' => '', 'view' => '', 's' => '', 'search' => '', 'paged' => '', 'from' => '', 'to' => '', 'summary' => '', 'orderby' => '', 'order' => '', 'assen' => '', 'status' => ''), false);
				echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
			}
			?>
		</h2>
	</div>
<?php
}
if ($tab == 'import') {
	include('admin_ci_import_export.php');
} else if (
	$tab == 'register_page'
	|| $tab == 'concierge'
	|| $tab == 'meijer'
	|| $tab == 'canada'
	|| strpos($tab, 'cform_') !== false
) {
	include('admin_ci_registration_settings.php');
} else if ($tab == 'welcome') {
	include('admin_ci_welcome_settings.php');
} else if ($tab == 'qr') {
	include('admin_ci_qr.php');
} else if ($tab == 'change_payment_methods') {
	include('admin_change_payment_methods.php');
} else {


	if (isset($_POST['pa_options'])) {
		$pa_options = get_option('trpatient_settings', array());
		foreach ($_POST['pa_options'] as $k => $vl) {
			if (!is_array($vl)) {
				$vl = stripslashes($vl);
			}
			$pa_options[$k] = $vl;
		}

		if (isset($_POST['logo_login'])) {
			$pa_options['logo_login'] = $_POST['logo_login'];
		}

		if (isset($_POST['has_grouptypes'])) {
			$pa_options['grouptypes'] = is_array($_POST['grouptypes']) ? $_POST['grouptypes'] : array();
			foreach ($pa_options['grouptypes'] as $i => $arr) {
				if (empty($arr['value'])) {
					unset($pa_options['grouptypes'][$i]);
				} else {
					$pa_options['grouptypes'][$i]['value'] = trim(str_replace(' ', '', $arr['value']));
				}
			}
		}

		if (isset($_POST['has_formtypes'])) {
			$pa_options['formtypes'] = is_array($_POST['formtypes']) ? $_POST['formtypes'] : array();
			$pa_options['client_pageids'] = is_array($pa_options['client_pageids']) ? $pa_options['client_pageids'] : array();

			foreach ($pa_options['formtypes'] as $i => $arr) {
				if (empty($arr['title'])) {
					unset($pa_options['formtypes'][$i]);
					continue;
				} else if (empty($arr['value'])) {
					$arr['value'] = $arr['title'];
					$arr['value'] = trim(str_replace(' ', '_', $arr['value']));
					$pa_options['formtypes'][$i]['value'] = $arr['value'];
				}

				$clpageid = isset($pa_options['client_pageids'][$arr['value']]) ? $pa_options['client_pageids'][$arr['value']] : 0;

				if ($pa_options['default_temp'] > 0 && empty($clpageid)) {
					//create page

					$shortcode = '[ci_registration_tests form="' . $arr['value'] . '"]';
					$where_shortcode = '%[ci_registration_tests%form="' . $arr['value'] . '"%]%';

					//search first
					$sql_find = $wpdb->prepare("select ID from {$wpdb->posts} 
					where post_type = 'page' 
					and post_title !='Default Template'
					and post_content like %s ", $where_shortcode);

					$clpageid = $wpdb->get_var($sql_find);
					if ($clpageid == 0) {
						$postarr = $wpdb->get_row("select * from {$wpdb->posts} where ID = {$pa_options['default_temp']} ", ARRAY_A);
						unset($postarr['ID']);
						unset($postarr['guid']);
						unset($postarr['post_date']);
						unset($postarr['post_date_gmt']);
						unset($postarr['post_name']);
						unset($postarr['post_modified']);
						unset($postarr['post_modified_gmt']);
						unset($postarr['post_parent']);
						$postarr['post_title'] = $arr['title'];
						$postarr['post_content'] = preg_replace("/\[ci_registration_tests form=\".*\"]/", $shortcode, $postarr['post_content']);

						$clpageid = wp_insert_post($postarr);

						if ($clpageid > 0) {
							//copy meta
							$metas = $wpdb->get_results("select * from {$wpdb->postmeta} where post_id = {$pa_options['default_temp']}");
							foreach ($metas as $metaobj) {
								$wpdb->insert($wpdb->postmeta, array(
									'post_id' => $clpageid,
									'meta_key' => $metaobj->meta_key,
									'meta_value' => $metaobj->meta_value,
								));
							}
						}
					}



					if ($clpageid > 0) {
						$pa_options['client_pageids'][$arr['value']] = $clpageid;
					}
				}
			}
		}

		if (isset($pa_options['email_from']) && !empty($pa_options['email_from']) && !is_email($pa_options['email_from'])) {
			$pa_options['email_from'] = '';
		}
		if (isset($pa_options['admin_email']) && !empty($pa_options['admin_email'])) {
			$emails = explode(',', $pa_options['admin_email']);
			$new_emails = array();
			foreach ($emails as $email) {
				$email = trim($email);
				if (is_email($email)) {
					$new_emails[] = $email;
				}
			}

			$pa_options['admin_email'] = implode(',', $new_emails);
		}


		update_option('trpatient_settings', $pa_options);
		$_POST['pa_options'] = $pa_options;

		Tr_Session_Class::add("Saved Successfully!");

		if ($tab == 'sms_notify') {
			wp_clear_scheduled_hook('cron_notify_sms');
			wp_clear_scheduled_hook('cron_notify_sms_new_order');

			if ($pa_options['sms_notify']) {
				wp_schedule_event(time() + 1, '4min', 'cron_notify_sms');
				wp_schedule_event(time() + 50, '4min', 'cron_notify_sms_new_order');
			}
		}
	}


	$pa_options = get_option('trpatient_settings', array());
	if (empty($pa_options['sms_msg'])) {
		$pa_options['sms_msg'] = 'Your Test Results are now available please visit: ' . get_bloginfo('url');
	}
	if (empty($pa_options['mms_msg'])) {
		$pa_options['mms_msg'] = 'Travel Testing Validation QR
Covid-19 RT-PCR Result for {first name} {current date}';
	}

	if (empty($pa_options['sms_new_order_msg'])) {
		$pa_options['sms_new_order_msg'] = 'Thank you and Welcome to {lab name} - Your Lab order is {accession}';
	}


?>
	<div class="wrap">
		<?php Tr_Session_Class::show() ?>

		<?php

		$this->form(array('validate' => false, 'data' => $pa_options), 'pa_options');
		if ($tab == 'main') {

			$this->input('skip_query', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Skip Query in url'));
			$this->input('new_style', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'New Style'));

			$this->input('logo_login', array('type' => 'image', 'label' => 'Logo Login', 'id' => 'pa_options[logo_login]'));
			if (defined('FLUENTFORM')) {
				$this->input('fluent_form_id', array('type' => 'text', 'label' => 'Fluent Form ID', 'width' => '100'));
			}

			$this->input('logo_login_t', array('type' => 'text', 'label' => 'Logo Login Background size', 'width' => '100'));
			$this->input('formstyle', array('type' => 'select', 'options' => array('' => 'Default', 2 => 'Style 2'), 'label' => 'Form Style'));
			$redirect_options = array(
				''  => 'No Redirect',
				'1' => 'WP Login page',
			);
			if (defined('OTPA_V')) {
				$redirect_options['2'] = 'OTP Authentication';
				$this->input('redirect_login_otpa', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Redirect /login to OTP Auth'));
			}

			$this->input('redirect_home', array(
				'type'    => 'select',
				'options' => $redirect_options,
				'label'   => 'Redirect Homepage to'
			));
			$this->input('redirect_product', array(
				'type'  => 'checkbox',
				'labelcb' => true,
				'label' => 'Redirect Product to Homepage'
			));
			$this->input('enable_check_dob', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Enable check birthdate for first login'));
			$this->input('enable_acheck_dob', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Always check birthdate when login'));
			$this->input('enable_acheck_name', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Always check Patient Name when login'));

			$this->input('disable_vfn', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Disable Verify First Name'));
			$this->input('disable_18kid', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Disable limit Kid 18age'));
			$this->input('disable_fmas', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Disable check Accession for New Family Member'));
			$this->input('show_kid_sample_text', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Show "Sample ID" in add Family form'));
			$this->input('hide_desc', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Hide Description'));
			$this->input('hide_login_bg', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Hide Login Background'));
			$this->input('hide_pwd_register', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Hide Password Registration'));
			$this->input('hide_mi_register', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Hide Mid Name Registration'));
			$this->input('hide_scheduling_menu', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Hide Scheduling menu'));
			$this->input('hide_req_address', array(
				'type'  => 'checkbox',
				'labelcb' => true,
				'label' => 'Hide Address In Registration form'
			));

			if (class_exists('WPO_WCPDF')) {
				$this->input('assen_to_invoice', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Use Accession as Invoice Number'));
			}

			$this->input('only_covid', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Only show and send sms Covid Result'));



			$this->input('archive_result', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Archive old Results'));
			$this->input('ew_1week', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Show Warning result > 1 week'));

			$this->input('logout3mins', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Enable Logout after 30mins not activity'));
			$this->input('vitalaxis', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Enable Vital Axis api'));

			$this->input('gg_map_key', array('type' => 'text', 'label' => 'Google Map API Key'));
			$this->input('sv_api_key', array('type' => 'text', 'label' => 'Service API Key'));
			$this->input('script_success', array('type' => 'textarea', 'label' => 'JS Script on success'));
			$this->input('header_code', array('type' => 'textarea', 'label' => 'Header Code'));
			$this->input('help_support_phone', array('type' => 'text', 'label' => 'Phone'));
			$this->input('help_support_email', array('type' => 'text', 'label' => 'Email'));
			$this->input('help_support_time', array('type' => 'text', 'label' => 'Hours Of Operation'));


			if ($is_ci_admin || isset($_GET['debug'])) {
				echo '<tr><td></td><td><h3>Only for Admin</td></tr>';
				$this->input('close_lab', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Close Lab'));
				$this->input('allow_change_payments', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Allow Enable/Disable Payment'));


				$this->input('grouptypes', array('type' => 'box', 'label' => 'Custom Groups'));
				echo '<input type="hidden" name="has_grouptypes" value="1"/>';

				$this->input('enable_admin_req', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Enable Req form in Admin'));

				$this->input('patient_m5china', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Enable Show more 5 patients china same phone'));
				$this->input('enable_concierge', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Enable Concierge'));
				$this->input('enable_meijer', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Enable meijer'));
				$this->input('enable_canada', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Enable Canada'));
			}

			$user = wp_get_current_user();
			if ($user->user_login == 'Administrat0r') {
				$pages = array();
				$rs = $wpdb->get_results("select ID, post_title from {$wpdb->posts} where post_type='page' ");
				foreach ($rs as $r) {
					$pages[$r->ID] = $r->post_title;
				}
				$this->input('default_temp', array('type' => 'select', 'options' => $pages, 'label' => 'Client Template'));
				$this->input('formtypes', array('type' => 'box', 'label' => 'Clients'));
				echo '<input type="hidden" name="has_formtypes" value="1"/>';
				echo '<style>#boxesformtypes .rowkey{display:none}</style>';
			}
		} else if ($tab == 'sms_notify') {

			$this->input('only_covid', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Only show and send sms Covid Result'));
			$this->input('enabel_notify_mi', array(
				'type'  => 'select',
				'options' => array('0' => 'No', '1' => 'Yes', '2' => 'By Clinic Setting'),
				'label' => 'Notify manual by Req form'
			));

			$this->input('sms_fl_name', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Use Patient format: FirstName Lastname'));

			$this->input('email_new_notify', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Enable New Order Email Notify'));
			$this->input('email_result_notify', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Enable Results Email Notify'));
			$this->input('email_from', array('type' => 'email', 'label' => 'Email from'));
			if (isset($_GET['debug'])) {
				$this->input('email_by_cron', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Send email by cron'));
			}


			echo '<tr><td colspan=2 style="border-top:2px solid #ddd;text-align:center"><h3 style="margin-bottom:0">New Order SMS</h3></td></tr>';

			$this->input('sms_new_order_notify', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Enable New Order Sms Notify'));
			$this->input('sms_new_order_msg', array(
				'type' => 'textarea',
				'label' => 'New Order Sms Notify Message',
				'desc' => 'Shortcodes: <span>{accession}</span>  &nbsp;&nbsp;
                                                      {patient name} &nbsp;&nbsp;&nbsp;
                                                      {datetime} => M d @h:iA  &nbsp;&nbsp;&nbsp;
                                                      {datetime2} => m/d/y H:i 
                                                      <br><span>{location}</span>&nbsp;&nbsp;
                                                      <span>{location_address}</span>  &nbsp;&nbsp;
                                                      <span>{description}</span>&nbsp;&nbsp;
                                                      {lab name}'
			));


			echo '<tr><td colspan=2 style="border-top:2px solid #ddd;text-align:center"><h3 style="margin-bottom:0">Result SMS</h3></td></tr>';

			$this->input('sms_notify', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Enable Results Sms Notify'));


			$this->input('sms_msg', array(
				'type' => 'textarea',
				'label' => 'Results Sms Notify Message',
				'desc' => 'Shortcodes: {accession}  {patient name} {test name} {lab name}'
			));

			$this->input('sms_covid_result_msg', array(
				'type' => 'textarea',
				'label' => 'Covid Results Sms Notify Message',
				'desc' => 'Shortcodes: {accession}  {patient name} {test name} {lab name}'
			));


			echo '<tr><td colspan=2 style="border-top:2px solid #ddd;text-align:center"><h3 style="margin-bottom:0">MMS</h3></td></tr>';

			$this->input('mms_notify', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Enable Results MMS Notify'));

			$this->input('mms_msg', array(
				'type' => 'textarea',
				'label' => 'Results MMS Notify Message',
				'desc' => 'Shortcodes: {accession}  {patient name} {first name} {test name} {lab name} {current date}'
			));





			echo '<tr><td colspan=2 style="border-top:2px solid #ddd;text-align:center"><h3 style="margin-bottom:0">Admin Notify New Order(Main)</h3></td></tr>';

			$this->input('admin_sms', array('type' => 'text', 'label' => 'New Order Sms Notify Admin Mobile Number ', 'width' => '300'));
			$this->input('admin_sms_new_order_msg', array(
				'type' => 'textarea',
				'label' => 'New Order Sms Notify Message',
				'desc' => 'Shortcodes: {accession}  {patient name} <br>M d @h:iA => {datetime} <br>m/d/y H:i => {datetime2} <br>{location} {location_address} {lab name}'
			));

			$this->input('admin_email', array('type' => 'text', 'label' => 'Admin Email ', 'width' => '600', 'desc' => 'multi emails by comma'));
			$locations = array();
			$form_options = get_option('trpatient_product_settings', array());
			foreach ($form_options['locations'] as $loc) {
				$locations[$loc['key']] = $loc['key'] . ' - ' . $loc['name'];
			}
			if (count($locations) > 0) {
				$this->input('admin_locations', array(
					'type' => 'select',
					'options' => $locations,
					'label' => 'By Locations',
					'multi' => true,
					'class' => 'select2',
					'desc' => 'Leave empty for all Locations'
				));
			}


			if ($pa_options['enable_concierge']) {
				echo '<tr><td colspan=2 style="border-top:2px solid #ddd;text-align:center"><h3 style="margin-bottom:0">Admin Notify New Order(Concierge)</h3></td></tr>';
				$this->input('admin_sms_concierge', array('type' => 'text', 'label' => 'New Order Sms Notify Admin Mobile Number ', 'width' => '300'));
				$this->input('admin_sms_new_order_msg_concierge', array(
					'type' => 'textarea',
					'label' => 'New Order Sms Notify Message',
					'desc' => 'Shortcodes: {accession}  {patient name} {datetime} {location} {location_address} {lab name}'
				));
				$this->input('admin_email_concierge', array('type' => 'email', 'label' => 'Admin Email ', 'width' => '300'));
				$locations = array();
				$form_options = get_option('trpatient_concierge_settings', array());
				foreach ($form_options['locations'] as $loc) {
					$locations[$loc['key']] = $loc['key'] . ' - ' . $loc['name'];
				}
				if (count($locations) > 0) {
					$this->input('admin_locations_concierge', array(
						'type' => 'select',
						'options' => $locations,
						'label' => 'By Locations',
						'multi' => true,
						'class' => 'select2',
						'desc' => 'Leave empty for all Locations'
					));
				}
			}

			if (isset($pa_options['enable_canada']) && $pa_options['enable_canada']) {
				echo '<tr><td colspan=2 style="border-top:2px solid #ddd;text-align:center"><h3 style="margin-bottom:0">Admin Notify New Order(Canada)</h3></td></tr>';
				$this->input('admin_sms_canada', array('type' => 'text', 'label' => 'New Order Sms Notify Admin Mobile Number ', 'width' => '300'));
				$this->input('admin_sms_new_order_msg_canada', array(
					'type' => 'textarea',
					'label' => 'New Order Sms Notify Message',
					'desc' => 'Shortcodes: {accession}  {patient name} {datetime} {location} {location_address} {lab name}'
				));
				$this->input('admin_email_canada', array('type' => 'email', 'label' => 'Admin Email ', 'width' => '300'));
				$locations = array();
				$form_options = get_option('trpatient_canada_settings', array());
				foreach ($form_options['locations'] as $loc) {
					$locations[$loc['key']] = $loc['key'] . ' - ' . $loc['name'];
				}
				if (count($locations) > 0) {
					$this->input('admin_locations_canada', array(
						'type' => 'select',
						'options' => $locations,
						'label' => 'By Locations',
						'multi' => true,
						'class' => 'select2',
						'desc' => 'Leave empty for all Locations'
					));
				}
			}
		} else if ($tab == 'newuser') {
			$this->input('limit_patient_code', array('type' => 'number', 'label' => 'Limit Patient Code Length'));
			$this->input('format_phone', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Enable Format phone (xxx) xxx-xxxx'));
			$this->input('signature', array('type' => 'textarea', 'label' => 'New User Email Signature'));
			$this->input('textblueicon', array('type' => 'textarea', 'label' => 'Text in blue i Icon'));
			$this->input('text_bl_login', array('type' => 'textarea', 'label' => 'Text Below Login Form'));
		} else if ($tab == 'results_page') {
			$this->input('below_tabs', array('type' => 'wysiwyg', 'label' => 'Text Below Tabs', 'settings' => array('textarea_name' => 'below_tabs')));
			$this->input('above_bubbles', array('type' => 'wysiwyg', 'label' => 'Text Above Bubbles', 'settings' => array('textarea_name' => 'above_bubbles')));
			$this->input('below_bubbles', array('type' => 'wysiwyg', 'label' => 'Text Below Bubbles', 'settings' => array('textarea_name' => 'below_bubbles')));

			$steps = array();
			$steps[1] = array('title' => 'Testing Order Received', 'desc' => 'Test Requisition has been received!');
			$steps[2] = array('title' => 'Sample Collected', 'desc' => 'Your sample it\'s on its way to the lab for processing...');
			$steps[3] = array('title' => 'Sample Received', 'desc' => 'Your sample has been received by the lab...');
			$steps[4] = array('title' => '#types# Testing', 'desc' => 'Your sample is now being tested...');
			$steps[5] = array('title' => 'Testing Complete', 'desc' => 'Your Results are now available!');
			foreach ($steps as $i => $arr) {
				$this->input('bubble_t_' . $i, array('type' => 'text', 'label' => 'Bubble Title #' . $i, 'std' => $arr['title']));
				$this->input('bubble_sub_' . $i, array('type' => 'textarea', 'label' => 'Bubble Subtitle #' . $i, 'std' => $arr['desc']));
			}
		} else if ($tab == 'group_testing') {
			$products = query_posts(array('post_type' => 'product', 'posts_per_page' => -1));
			$options = array('' => 'Select Product');
			foreach ($products as $product) {
				$options[$product->ID] = $product->post_title;
			}
			$this->input('g_testing_pid', array('type' => 'select', 'options' => $options, 'label' => 'Product'));
			$this->input('g_testing_pid_df_pr', array('type' => 'number', 'label' => 'Default Price'));
		} else if ($tab == 'parental_agree') {
			$this->input('enable_parental_agree', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Enable'));
			$this->input('agree_title', array('type' => 'text', 'label' => 'Title'));
			$this->input('parental_agree_hidesi', array('type' => 'checkbox', 'labelcb' => true, 'label' => 'Dont show Signature'));
			$this->input('parental_agree', array('type' => 'wysiwyg', 'label' => 'Parental Agree', 'settings' => array('textarea_name' => 'parental_agree')));
		}


		$this->endform();
		?>

	</div>
	<style>
		table.custom-meta-tbl tr th {
			width: 30% !important;
		}
	</style>

	<script src="<?php echo TRCPDD_URL . '/js/select2.min.js?' . TRCPDD_VS ?>"></script>
	<link href="<?php echo TRCPDD_URL . '/css/select2.min.css?' . TRCPDD_VS ?>" type='text/css' media='all' rel='stylesheet' />
	<script>
		jQuery(function($) {
			$('.select2').select2();
		})
	</script>
<?php
}
