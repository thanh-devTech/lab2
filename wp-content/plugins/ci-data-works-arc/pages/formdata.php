<?php

if (isset($_REQUEST['trajax'])) {
	return;
}

$print_zebra = tr_get_option('ci_pdf_data', 'print_zebra');
$print_zebra_size = tr_get_option('ci_pdf_data', 'print_zebra_size');

if ($print_zebra) {
	wp_enqueue_script('BrowserPrint', TRCPDD_URL . 'js/BrowserPrint-3.0.216.min.js', array(), TRCPDD_VS);
	wp_enqueue_script('BrowserPrint-Zebra', TRCPDD_URL . 'js/BrowserPrint-Zebra-1.0.216.min.js', array(), TRCPDD_VS);
	wp_enqueue_script('tr-printer', TRCPDD_URL . 'js/printer.js', array(), TRCPDD_VS);
?><script>
			$use_zebra = true;
			var $use_zebra_size = '<?php echo !empty($print_zebra_size) ? $print_zebra_size : '2x1' ?>';
			var $add_doc_to_label = <?php echo tr_get_option('ci_pdf_data', 'barcode_doc') ? 'true' : 'false' ?>;
			var $disable_successful_print_popup = <?php echo tr_get_option('ci_pdf_data', 'disable_successful_print_popup') ? 'true' : 'false' ?>;
		</script>
<?php
}

wp_enqueue_script('jquery-ui-core');
wp_enqueue_script('jquery-ui-slider');
wp_enqueue_script('jquery-ui', '', array('jquery'));
wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_script('jquery-ui-tooltip');
wp_enqueue_script('jquery-ui-autocomplete');
wp_enqueue_script('at-timepicker', TRCPDD_URL . 'admin/js/time-and-date/jquery-ui-timepicker-addon.js', array('jquery'), null, true);

wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');
wp_enqueue_script('jquery-validate', TRCPDD_URL . 'admin/js/jquery.validate.min.js', array('jquery'), true);
wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.js', array(), TRCPDD_VS);

if (tr_get_option('ci_pdf_data', 'auto_pa_address')) {
	$google_key = 'AIzaSyCYrucjzQZcXwhRBuPcQDPkzgxhlyA3mTE';
	wp_enqueue_script('googlemapauto',TRCPDD_URL.'js/googlemapauto.js', array('jquery'), TRCPDD_VS, true);
	wp_enqueue_script('googlemap', 'https://maps.googleapis.com/maps/api/js?key='.$google_key.'&callback=initAutocomplete&libraries=places&v=weekly&language=en', array('jquery'), TRCPDD_VS, true);
}

add_thickbox();
$act = isset($act) ? $act : '';
$act = isset($_REQUEST['act']) ? $_REQUEST['act'] : $act;
$current_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;


$upload_dir = tr_wp_upload_dir();
$logo_dir = $upload_dir['basedir'] . '/logos';
$logo_url = $upload_dir['baseurl'] . '/logos';
