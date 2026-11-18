<?php
include(TRCPDD_PATH . 'inc/define_apis.php');

$domain 		= get_bloginfo('url');
$domain 		= str_replace(array('http://', 'https://'), '', $domain);
$main_domain 	= preg_replace("/^lab\./", '', $domain);
$main_domain = strtolower($main_domain);


$patient_site_id = function_exists('get_blog_option') ? 2 : 0;
$patient_site_name = '';
$sms_password = '';
if ($patient_site_id > 0) {
	$patient_site_name = get_blog_option($patient_site_id, 'blogname');
	if (empty($patient_site_name)) {
		$patient_site_id = 0;
	}
}
$upload_dir = tr_wp_upload_dir();
$logo_dir = $upload_dir['basedir'] . '/logos';
$logo_url = $upload_dir['baseurl'] . '/logos';

$lab = $wpdb->get_row("select * from ci_labs where lab_ID = {$lab_id}", ARRAY_A);



if (isset($_POST['reinstallnewlab']) && $_POST['pwd'] == 'abc123' && $_POST['domain'] == $main_domain) {

	Tr_Session_Class::add('Run Install');
	$site_name = $_POST['main_site_name'];

	$labarr = $_POST['lab'];
	$labfooter = stripslashes($_POST['labfooter']);


	$logoname = 'lab_' . $lab_id;
	$remove_images = false;
	if (isset($_POST['remove_labimg'])) {
		$remove_images = true;
		$labarr['lab_logo'] = '';
	}
	$logo_filename = '';
	if ($_FILES['lab_logo']['name']) {
		$adm = new Tr_Admin_Page_V7(TRCPDD_PATH);
		$logo_filename = $adm->uploadfile($_FILES['lab_logo'], $logoname, $logo_dir);


		if (!empty($logo_filename)) {
			$labarr['lab_logo'] =  $logo_filename;

			$remove_images = true;
		}
	}

	if ($remove_images) {

		$_GET['fix_patients'] = true;
	}

	if (empty($labarr['lab_city'])) {
		$lab_addrs = trpdd_parse_address($labarr['lab_address']);
		$labarr['lab_address'] = $lab_addrs[0];
		$labarr['lab_city'] = $lab_addrs[1];
		$labarr['lab_state'] = substr(trim($lab_addrs[2]), 0, 2);
		$labarr['lab_zip'] = $lab_addrs[3];
	}

	$labarr['lab_state'] = substr(trim($labarr['lab_state']), 0, 2);

	if (stripos($labarr['lab_email'], $main_domain) == false) {
		$labarr['lab_email'] = 'admin@' . $main_domain;
	}

	$oldlabname = $wpdb->get_var("select lab_name from ci_labs where lab_ID = {$lab_id} ");
	$wpdb->update('ci_labs', $labarr, array('lab_ID' => $lab_id));
	$lab = $wpdb->get_row("select * from ci_labs where lab_ID = {$lab_id}", ARRAY_A);


	$req_pcr_settings = get_option('ci_req_settings_pcr', array());
	$req_pcr_settings['abovep_text'] = str_replace('ARC Diagnostics', $labarr['lab_name'], $req_pcr_settings['abovep_text']);
	$req_pcr_settings['abovep_text'] = str_replace($oldlabname, $labarr['lab_name'], $req_pcr_settings['abovep_text']);
	update_option('ci_req_settings_pcr', $req_pcr_settings);


	$options = get_option('ci_pdf_data');
	$options['barcode_key'] = $lab['lab_dba_init'];
	$options['hide_wp_menu'] = true;
	$options['new_dashboard'] = true;
	$options['new_abx_dashboard'] = true;
	$options['abx_db_base_batch_id'] = true;
	$options['enable_utire'] = false; //old abx
	$options['enable_utinrow'] = false; //old abx
	$options['enable_woundabx'] = false; //old abx
	$options['enable_giabx'] = false; //old abx
	$options['off_see_all'] = '';

	update_option('ci_pdf_data', $options);
	update_option('randoum_create_r_index', 10);

	update_option('blogname', $site_name);
	update_option('blogdescription', $site_name);
	update_option('ci_ringcentral_st', array());
	update_site_option('site_name', $site_name);

	if (!empty($_POST['patient_site_name']) && $patient_site_id > 0) {
		update_blog_option($patient_site_id, 'blogname', $_POST['patient_site_name']);
		$widget_text = get_blog_option($patient_site_id, 'widget_text');
		$widget_text = tr_replace_data('Blue Ocean Lab Services', $_POST['patient_site_name'], $widget_text);
		update_blog_option($patient_site_id, 'widget_text', $widget_text);

		if (!empty($_POST['sms_password'])) {
			$sms_st = get_blog_option($patient_site_id, 'otpa_bandwidth_sms_gateway_settings');
			$sms_st['user_name'] = $_POST['sms_user'];
			$sms_st['user_pass'] = $_POST['sms_password'];
			update_blog_option($patient_site_id, 'otpa_bandwidth_sms_gateway_settings', $sms_st);
		}
	}

	if (!empty($_POST['billing_df'])) {
		$optionsbl = get_option('tr_billing_ind_st', array());
		$optionsbl['enable_medicare'] = '1';
		$optionsbl['allow_empty_insurance'] = '1';
		$optionsbl['enable_pay_type'] = '1';
		$optionsbl['enable_doctortb'] = '1';
		$optionsbl['enable_ins_upload'] = '1';
		$optionsbl['enable_ins_report'] = '1';

		update_option('tr_billing_ind_st', $optionsbl);
	}



	trpdd_clear_by_dir(WP_CONTENT_DIR . '/log/results/', 1, time(), false);
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/qrimages/', 1, time(), false);
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/log/results_blood/', 1, time(), false);
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/log/results_pcr/', 1, time(), false);
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/log/tecan/', 1, time(), false);
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/log/prefillcovid/', 1, time(), false);
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/log/', 1, time(), false);
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/paperwork/', 1, time(), false);
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/doctor/', 1, time(), false);
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/qrimages/', 1, time(), false);
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/qrs/', 1, time(), true);
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/tmp/', 1, time(), true);

	trpdd_clear_by_dir(WP_CONTENT_DIR . '/cache/queries/', 1, time(), true);
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/cache/', 1, time(), false);
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/results/', 1, time(), true);
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/uploads/signatures/', 1, time(), true);
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/uploads/ins_cards/', 1, time(), true);
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/uploads/issue_photo/', 1, time(), true);
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/uploads/bill_imports/', 1, time(), true);
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/uploads/batch_imports/', 1, time(), true);
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/uploads/pb_backupbuddy/', 1, time(), true);
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/uploads/redux/', 1, time(), true);
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/uploads/ins_mix/', 1, time(), true);
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/uploads/docs/', 1, time(), true);
	trpdd_clear_by_dir(WP_CONTENT_DIR . '/qrimages/', 1, time(), true);

	trpdd_plugin_activate();


	$wpdb->query("truncate table `ci_log_forms`");
	$wpdb->query("truncate table `ci_phlebotomist_logs`");
	$wpdb->query("truncate table `ci_batches`");
	$wpdb->query("truncate table `ci_batches_meta`");
	$wpdb->query("truncate table `ci_batches_allergy`");
	$wpdb->query("truncate table `ci_batches_allergy_meta`");
	$wpdb->query("truncate table `ci_batches_blood`");
	$wpdb->query("truncate table `ci_batches_blood_meta`");
	$wpdb->query("truncate table `ci_batches_pcr`");
	$wpdb->query("truncate table `ci_batches_pcr_meta`");
	$wpdb->query("truncate table `ci_batches_gene`");
	$wpdb->query("truncate table `ci_batches_gene_meta`");
	$wpdb->query("truncate table `ci_insurances`");
	$wpdb->query("truncate table `ci_box_upload`");
	$wpdb->query("truncate table `ci_clinic`");
	$wpdb->query("truncate table `ci_custom_providers`");
	$wpdb->query("truncate table `ci_clinic_collector`");
	$wpdb->query("truncate table `ci_clinic_doc`");
	$wpdb->query("truncate table `ci_doc`");
	$wpdb->query("truncate table `ci_clinic_manager`");
	$wpdb->query("truncate table `ci_clinician_groups`");
	$wpdb->query("truncate table `ci_collector`");
	$wpdb->query("truncate table `ci_covid_questions`");
	$wpdb->query("truncate table `ci_customer_orders`");
	$wpdb->query("truncate table `ci_log_users`");
	$wpdb->query("truncate table `ci_patients`");
	$wpdb->query("truncate table `ci_patients_history`");
	$wpdb->query("truncate table `ci_reports`");
	$wpdb->query("truncate table `ci_reports_blood`");
	$wpdb->query("truncate table `ci_reports_pcr`");
	$wpdb->query("truncate table `ci_result_metha`");
	$wpdb->query("truncate table `ci_result_test_speciments`");
	$wpdb->query("truncate table `ci_results`");
	$wpdb->query("truncate table `ci_results_blood`");
	$wpdb->query("truncate table `ci_results_pcr`");
	$wpdb->query("truncate table `ci_results_start`");
	$wpdb->query("truncate table `ci_saved" . "_batches`");
	$wpdb->query("truncate table `ci_scheduling`");
	$wpdb->query("truncate table `ci_sent_notify`");
	$wpdb->query("truncate table `ci_sync_api_files`");
	$wpdb->query("truncate table `ci_sync_api_orders`");
	$wpdb->query("truncate table `ci_wsdl_keys`");
	$wpdb->query("truncate table `ci_wsdl_log`");
	$wpdb->query("truncate table `tr_log_sms`");
	$wpdb->query("truncate table `ci_racks`");
	$wpdb->query("truncate table `ci_rack_batches`");
	$wpdb->query("truncate table `ci_plates`");
	$wpdb->query("truncate table `ci_plates_batches`");
	$wpdb->query("truncate table `ci_receiving`");
	$wpdb->query("truncate table `ci_receiving_batches`");
	$wpdb->query("truncate table `ci_batch_billings`");
	$wpdb->query("truncate table `ci_analytical_errors`");
	$wpdb->query("truncate table `ci_autoimportqc`");
	$wpdb->query("truncate table `ci_batch_ids`");
	$wpdb->query("truncate table `ci_hub_statistics`");
	$wpdb->query("truncate table `ci_fix_duplicate`");
	$wpdb->query("truncate table `ci_insurances_history`");
	$wpdb->query("truncate table `ci_reports_files`");
	$wpdb->query("truncate table `ci_sent_notify_clinic`");
	$wpdb->query("truncate table `ci_wsdl_batch`");
	$wpdb->query("truncate table `wp_2_actionscheduler_logs`");
	$wpdb->query("truncate table `wp_2_comments`");
	$wpdb->query("truncate table `wp_2_commentmeta`");
	$wpdb->query("truncate table `wp_2_woocommerce_order_itemmeta`");
	$wpdb->query("truncate table `wp_2_wcpdf_invoice_number`");
	$wpdb->query("truncate table `wp_actionscheduler_actions`");
	$wpdb->query("truncate table `wp_2_woocommerce_order_items`");
	$wpdb->query("truncate table `wp_2_woocommerce_sessions`");
	$wpdb->query("truncate table `wp_2_woocommerce_payment_tokenmeta`");
	$wpdb->query("truncate table `wp_2_woocommerce_payment_tokens`");
	$wpdb->query("truncate table `wp_2_wc_tax_rate_classes`");
	$wpdb->query("truncate table `wp_2_wc_product_download_directories`");
	$wpdb->query("truncate table `wp_2_wc_order_stats`");
	$wpdb->query("truncate table `wp_2_wc_order_product_lookup`");
	$wpdb->query("truncate table `wp_2_wc_customer_lookup`");
	$wpdb->query("truncate table `wp_2_wc_category_lookup`");
	$wpdb->query("truncate table `wp_2_wc_admin_notes`");
	$wpdb->query("truncate table `wp_2_wc_admin_note_actions`");

	$wpdb->query("truncate table `wp_2_otpa_logs`");
	$wpdb->query("truncate table `wp_2_actionscheduler_groups`");
	$wpdb->query("truncate table `wp_2_actionscheduler_claims`");
	$wpdb->query("truncate table `wp_2_actionscheduler_actions`");
	$wpdb->query("truncate table `ci_wsdl_key_clinics`");
	$wpdb->query("truncate table `wp_2_yoast_indexable`");
	$wpdb->query("truncate table `wp_2_yoast_indexable_hierarchy`");
	$wpdb->query("truncate table `wp_2_yoast_migrations`");
	$wpdb->query("truncate table `wp_2_yoast_primary_term`");
	$wpdb->query("truncate table `wp_2_yoast_seo_links`");
	$wpdb->query("truncate table `wp_tr_security_log`");
	$wpdb->query("truncate table `wp_tr_lock_ip`");
	$wpdb->query("truncate table `ci_lab_reps`");
	$wpdb->query("truncate table `ci_instruments`");
	$wpdb->query("truncate table `ci_plates_combine`");
	$wpdb->query("truncate table `ci_vendors`");
	$wpdb->query("truncate table `ci_pdf_footer`");
	$wpdb->query("truncate table `ci_scheduling_cform_ChinaTravel`");


	$wpdb->query("delete from ci_labs where lab_site is null and lab_is_ref=1");

	$wpdb->query("delete from {$wpdb->options} where option_name like 'ci_abx_type_%'");
	$wpdb->query("delete from {$wpdb->options} where option_name like 'ci_billing_list_cache%'");
	$wpdb->query("delete from {$wpdb->options} 
		                where ( option_name like 'tr_%_st' or option_name like 'tr_%_sts') 
		                and option_name not in ('trcs_otherst','tr_billing_ind_st') ");

	foreach ($define_apis as $k => $arr) {
		$wpdb->query("delete from {$wpdb->options} where option_name = '{$arr['st']}' ");
	}

	$wpdb->query("update `ci_pdf_notes` set text = replace(text,'ARC Diagnostics','{$labarr['lab_name']}')");
	$wpdb->query("update `ci_pdf_notes` set text = replace(text,'Arc Diagnostics','{$labarr['lab_name']}')");


	wp_clear_scheduled_hook('cron_api_sftp_experity');
	wp_clear_scheduled_hook('cron_api_evident');
	wp_clear_scheduled_hook('cron_api_hl7_athena');
	wp_clear_scheduled_hook('cron_api_hl7_athena_api');
	wp_clear_scheduled_hook('cron_api_billing_sftp');
	wp_clear_scheduled_hook('cron_api_cteh');
	wp_clear_scheduled_hook('cron_api_elink');
	wp_clear_scheduled_hook('cron_api_hl7_ellkay');
	wp_clear_scheduled_hook('cron_api_lifetime');
	wp_clear_scheduled_hook('cron_api_medhiwa');
	wp_clear_scheduled_hook('cron_api_meenta');
	wp_clear_scheduled_hook('cron_api_noah');
	wp_clear_scheduled_hook('cron_api_sftp_hl7');
	wp_clear_scheduled_hook('cron_api_solv');

	$wpdb->insert('ci_lab_reps', array(
		'lab_ID' => 1,
		'rep_first' => 'Rep',
		'rep_last' => 'User',
		'rep_email' => 'rep@' . $domain,
		'rep_sdate' => date_i18n('Y-m-d')
	));
	echo 'setup lab rep: ' . $wpdb->last_error . '<br>';

	//add clinic
	$wpdb->insert('ci_clinic', array(
		'cli_lab-ID' => 1,
		'cli_rep-ID' => 1,
		'cli_auto_assen' => 1,
		'cli_clinic' => $labarr['lab_name'],
		'cli_street' => $labarr['lab_address'],
		'cli_city' => $labarr['lab_city'],
		'cli_state' => substr(trim($labarr['lab_state']), 0, 2),
		'cli_zip' => $labarr['lab_zip'],

	));
	echo 'setup clinic: ' . $labarr['lab_name'] . ' => ' . $wpdb->last_error . '<br>';
	//add doctor
	$wpdb->insert('ci_doc', array(
		'doc_first' => 'Doctor',
		'doc_last' => 'Dr',
		'doc_specialty' => 'D',
		'doc_npi' => '00001111',
	));

	$wpdb->insert('ci_clinic_doc', array('cli_ID' => 1, 'doc_ID' => 1));
	echo $wpdb->last_error;

	//add footer pdf
	if (!empty($labfooter)) {
		foreach (tr_pdd_get_batch_types() as $btype) {
			$footertext = $labfooter;

			$footer_arr = array(
				'btype' => $btype,
				'lab_ID' => $lab_id,
				'group_ID' => '-1',
				'text' => $footertext,
				'created' => date_i18n('Y-m-d H:i:s'),
				'created_by' => get_current_user_id()
			);
			$wpdb->insert('ci_pdf_footer', $footer_arr);
		}
	}

	//fix more
	update_option('eStore_download_email_address', $site_name . ' <sales@' . $domain . '>');
	//fix swpsmtp_options
	update_option('swpsmtp_options', array());

	if ($patient_site_id > 0) {
		update_blog_option($patient_site_id, 'swpsmtp_options', array());
	}


	//fix posts
	$wpdb->query("update {$wpdb->posts} set post_content = REPLACE(post_content,'Arc Diagnostics','{$lab['lab_name']}') ");
	$wpdb->query("update wp_2_posts set post_content = REPLACE(post_content,'Arc Diagnostics','{$lab['lab_name']}') ");

	//remove logo media
	$logoid = $wpdb->get_var("select ID from {$wpdb->posts} where post_title='BOLS Logo'");
	$wpdb->delete($wpdb->posts, array('ID' => $logoid));
	$wpdb->delete($wpdb->postmeta, array('post_id' => $logoid));

	//remove users
	if (isset($_POST['removeusers'])) {
		$users = $wpdb->get_results("select * from {$wpdb->users} where user_login !='Administrat0r' and ID !=1");
		foreach ($users as $user) {
			$wpdb->delete($wpdb->users, array('ID' => $user->ID));
			$wpdb->delete($wpdb->usermeta, array('user_id' => $user->ID));
		}

		$wpdb->query("delete from {$wpdb->usermeta} 
                            where user_id not in (select ID from {$wpdb->users} )");
	}
	//update notify
	if ($patient_site_id > 0) {
		$pa_options = get_blog_option($patient_site_id, 'trpatient_settings');

		if (isset($_POST['remove_labimg']) && !empty($pa_options['logo_login'])) {
			$filelogo = $wpdb->get_var("select meta_value from {$wpdb->prefix}{$patient_site_id}_postmeta
	                                where post_id = {$pa_options['logo_login']} and meta_key='_wp_attached_file' ");

			if (!empty($filelogo)) {
				$filelogo_path = WP_CONTENT_DIR . '/uploads/sites/' . $patient_site_id . '/' . $filelogo;
				unlink($filelogo_path);
				if (!empty($logo_filename)) {
					copy($logo_dir . '/' . $logo_filename, $filelogo_path);
				} else {
					$rs = copy(TRCPDD_PATH . '/images/logo.png', $filelogo_path);
				}
			}
		}

		if ($pa_options) {
			$pa_options['email_new_notify'] = 1;
			$pa_options['email_result_notify'] = 1;
			$pa_options['sms_new_order_notify'] = 1;
			$pa_options['sms_notify'] = 1;
			$pa_options['sms_new_order_msg'] = 'Welcome to {lab name} {patient name}. Your Patient Code is {accession}.';
			$pa_options['sms_msg'] = '{patient name} your Test Results are now available! Your Patient Code is {accession}.';
			$pa_options['mms_notify'] = '0';
			$pa_options['mms_msg'] = '';
			update_blog_option($patient_site_id, 'trpatient_settings', $pa_options);
		}
	}

	if ($_POST['setup_statuses']) {
		include(trpdd_get_file_path('scripts', 'setup_statuses'));
	}
}

if (isset($_GET['fix_patients']) && $patient_site_id > 1) {
	//remove images

	if (!isset($logo_filename) || empty($logo_filename)) {
		$logo_filename = 'lab_' . $lab_id . '.png';
	}


	$imagedir = WP_CONTENT_DIR . '/uploads/sites/2/';

	$logo2img = $imagedir . '2020/12/lab_1.png';

	$logo_filename_path = !empty($logo_filename) ? $logo_dir . '/' . $logo_filename : TRCPDD_PATH . 'images/logo.png';
	unlink($logo2img);
	$rs = file_put_contents($logo2img, file_get_contents($logo_filename_path));

	echo 'fixed logo patient<br>';

	$imgs = $wpdb->get_results("select * from `wp_2_posts` where post_type='attachment' 
		                        and (guid like '%BOLS%' 
		                                or post_name='bg-tr-logo'
		                                or post_name='bols-logo-bl-tr'
		                                or post_name='blue-oc-bl'
		                                or post_name='bols-logo-tr'
		                                or post_name='blue-oc'
		                                or post_name='bo-lab-logo'
		                                or post_name='bg6'
		                                or post_name='lab_1.png'
		                                )");
	foreach ($imgs as $img) {
		$file = $wpdb->get_var("select meta_value from wp_2_postmeta where post_id = {$img->ID} and meta_key='_wp_attached_file'");
		$imagepath = $imagedir . $file;
		unlink($imagepath);

		$meta = $wpdb->get_var("select meta_value from wp_2_postmeta where post_id = {$img->ID} and meta_key='_wp_attachment_metadata'");
		$meta = maybe_unserialize($meta);
		foreach ($meta['sizes'] as $s) {
			$imagepath = dirname($imagepath) . '/' . $s['file'];

			unlink($imagepath);
		}




		$wpdb->delete('wp_2_posts', array('ID' => $img->ID));
		$wpdb->delete('wp_2_postmeta', array('post_id' => $img->ID));
	}

	$wpdb->query("update wp_2_posts set post_content = REPLACE(post_content,'Arc Diagnostics','{$lab['lab_name']}') ");


	$pa_options = get_blog_option($patient_site_id, 'trpatient_settings');
	$filelogo = $wpdb->get_var("select meta_value from {$wpdb->prefix}{$patient_site_id}_postmeta
	                                where post_id = {$pa_options['logo_login']} and meta_key='_wp_attached_file' ");

	if (empty($filelogo)) {
		$filelogo = '2021/09/image001-7.png';
	}
	if (!empty($filelogo)) {
		$filelogo_path = WP_CONTENT_DIR . '/uploads/sites/' . $patient_site_id . '/' . $filelogo;
		unlink($filelogo_path);
		if (!empty($logo_filename)) {
			copy($logo_dir . '/' . $logo_filename, $filelogo_path);
			echo '<br>' . $logo_dir . '/' . $logo_filename . ' => ';
			echo $filelogo_path . '<br>';
		} else {
			$rs = copy(TRCPDD_PATH . '/images/logo.png', $filelogo_path);
		}
	}
}

if ($patient_site_id > 0) {
	$patient_site_name = get_blog_option($patient_site_id, 'blogname');
	$sms_st = get_blog_option($patient_site_id, 'otpa_bandwidth_sms_gateway_settings');
	$sms_password = $sms_st['user_pass'];
	$sms_user = $sms_st['user_name'];
}

$labfooter = '<p class="center">[lab_address] - Phone: [lab_phone]</p>' . "\n";
$labfooter .= '<p class="center">Medical Director: [lab_director] - CLIA # [lab_clia]</p>';

?>
<a href="<?php Tr_Base_Class_V6::link(array('fix_patients' => '1')) ?>">Fix Patient Site</a>

<?php Tr_Session_Class::show() ?>

<form method="post" id="newlabform" enctype="multipart/form-data">
	<h2>Setup New Lab</h2>
	<div class="frow">
		<div class="col2">

			<div class="frow">
				<label>Lab Name</label>
				<input type="text" name="lab[lab_name]" value="<?php echo $lab['lab_name'] ?>" required />
			</div>
			<div class="frow">
				<label>Lab DBA</label>
				<input type="text" name="lab[lab_dba]" value="<?php echo $lab['lab_dba'] ?>" required />
			</div>
			<div class="frow">
				<label>Lab INIT</label>
				<input type="text" name="lab[lab_dba_init]" value="<?php echo $lab['lab_dba_init'] ?>" required />
			</div>
			<div class="frow">
				<label>Lab Address</label>
				<input type="text" name="lab[lab_address]" value="<?php echo $lab['lab_address'] ?>" />
			</div>
			<div class="frow">
				<label>Lab City</label>
				<input type="text" name="lab[lab_city]" value="<?php echo $lab['lab_city'] ?>" />
			</div>
			<div class="frow">
				<label>Lab State</label>
				<input type="text" name="lab[lab_state]" value="<?php echo $lab['lab_state'] ?>" />
			</div>
			<div class="frow">
				<label>Lab Zip</label>
				<input type="text" name="lab[lab_zip]" value="<?php echo $lab['lab_zip'] ?>" />
			</div>
			<div class="frow">
				<label>Lab Phone</label>
				<input type="text" name="lab[lab_phone]" value="<?php echo $lab['lab_phone'] ?>" />
			</div>
			<div class="frow">
				<label>Lab Fax</label>
				<input type="text" name="lab[lab_fax]" value="<?php echo $lab['lab_fax'] ?>" />
			</div>
			<div class="frow">
				<label>Lab Clia</label>
				<input type="text" name="lab[lab_clia]" value="<?php echo $lab['lab_clia'] ?>" />
			</div>
			<div class="frow">
				<label>Lab Director</label>
				<input type="text" name="lab[lab_director]" value="<?php echo $lab['lab_director'] ?>" />
			</div>
			<div class="frow">
				<label>Lab Email</label>
				<input type="text" name="lab[lab_email]" value="<?php echo $lab['lab_email'] ?>" />
			</div>

			<div class="frow">
				<label>Lab logo</label>
				<input type="file" name="lab_logo" />
			</div>

			<div class="frow">
				<label><input type="checkbox" name="remove_labimg" value="1" /> Remove Logo</label>
			</div>

			<div class="frow">
				<label><input type="checkbox" name="removeusers" value="1" /> Remove Users</label>
			</div>

			<div class="frow">
				<label><input type="checkbox" name="billing_df" value="1" /> update Billing Settings</label>
			</div>
			<div class="frow">
				<label><input type="checkbox" name="setup_statuses" value="1" onclick="return confirm('Are you sure you want to setup batch statuses?');" />Setup batch statuses</label>
			</div>

			<div class="frow">
				<label>Password</label>
				<input type="text" name="pwd" required />
			</div>
			<div class="frow">
				<label>Domain</label>
				<input type="text" name="domain" required />
			</div>

		</div>
		<div class="col2">
			<div class="frow">
				<label>Main Site Name</label>
				<input type="text" name="main_site_name" value="<?php echo get_option('blogname') ?>" required />
			</div>
			<?php if (!empty($patient_site_name)) { ?>
				<div class="frow">
					<label>Patient Site Name</label>
					<input type="text" name="patient_site_name" value="<?php echo $patient_site_name ?>" required />
				</div>
			<?php } ?>
			<?php if (!empty($sms_password)) { ?>
				<div class="frow">
					<label>Patient Sms User</label>
					<input type="text" name="sms_user" value="<?php echo $sms_user ?>" required />
				</div>
				<div class="frow">
					<label>Patient Sms Password</label>
					<input type="text" name="sms_password" value="<?php echo $sms_password ?>" required />
				</div>
			<?php } ?>


			<div class="frow">
				<label>Results Footer</label>
				<textarea name="labfooter"><?php echo $labfooter ?></textarea>
			</div>



		</div>
	</div>
	<input type="submit" class="button-primary" name="reinstallnewlab" value="Reset to New lab" />

</form>
<script>
	jQuery(function($) {
		$('#newlabform').submit(function() {
			if (confirm('Are you sure remove all data?')) {
				return true;
			}
			return false;
		})
	})
</script>