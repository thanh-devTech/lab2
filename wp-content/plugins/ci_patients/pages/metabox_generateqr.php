<?php
global $wpdb;
if (!function_exists('trpdd_get_qr_code')) {
	include_once(TRCPPR_PATH . 'inc/functions_qr.php');
}
$postid = isset($postid) && $postid > 0 ? $postid : $post->ID;
if ($postid > 0) {
	$qr_dir = WP_CONTENT_DIR . '/qrimages/';
	if (!file_exists($qr_dir)) {
		wp_mkdir_p($qr_dir);
	}

	$url = get_permalink($postid);
	$size = 400;
	$filename = $postid . '.png';

	$errorCorrectionLevel = 'H';
	if (isset($_REQUEST['level']) && in_array($_REQUEST['level'], array('L', 'M', 'Q', 'H')))
		$errorCorrectionLevel = $_REQUEST['level'];

	$matrixPointSize = 15;
	if (isset($_REQUEST['size']))
		$matrixPointSize = min(max((int)$_REQUEST['size'], 1), $matrixPointSize);



	$QR = trpdd_get_qr_code($url, $size, $errorCorrectionLevel, $matrixPointSize);
	imagepng($QR, $qr_dir . $filename);
	$qrurl = get_bloginfo('url') . '/wp-content/qrimages/' . $filename;
	$qrurl .= '?v=' . time();
	echo '<img class="pageqr" src="' . $qrurl . '" style="max-width:100%;height:auto">';

	$qrdownload = '?tr_act=download_qr&f=' . $filename;
	echo '<a class="button" href="' . $qrdownload . '" target=_blank>Download</a>';

	//check form
	$post_content = $wpdb->get_var("SELECT post_content FROM $wpdb->posts WHERE ID = $postid");
	if (strpos($post_content, '[ci_registration_tests form=') !== false) {
		$pa_options = get_option('trpatient_settings', array());

		$form = preg_replace('/.*\[ci_registration_tests form=[\'"]([^\'"]+)[\'"].*/s', '$1', $post_content);
		if (!empty($form)) {
			$clpageid = isset($pa_options['client_pageids'][$form]) ? $pa_options['client_pageids'][$form] : 0;
			if ($clpageid != $postid) {
				$pa_options['client_pageids'][$form] = $postid;
				update_option('trpatient_settings', $pa_options);
				echo 'updated pid';
			}
		}
	}
}
