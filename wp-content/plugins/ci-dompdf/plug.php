<?php
/*
Plugin Name: CI Dompdf
Plugin URI: http://ciwebstudio.com
Description: API create pdf
Version: 2.8.01
Author: Cornel
Author URI: http://ciwebstudio.com
License: GPL2
*/

define('TRCDPDF_FILE', __FILE__);
define('TRCDPDF_V', '2.8.01');
define('TRCDPDF_URL', plugins_url('/', __FILE__));
define('TRCDPDF_PATH', plugin_dir_path(__FILE__) . '/');
define('TRCDPDF_NEW', true);

function trdomepdf_build_pdf($content, $args = array())
{
	if (!isset($args['size']) || empty($args['size'])) {
		$args['size'] = 'letter';
	}
	if (!isset($args['orientation']) || empty($args['orientation'])) {
		$args['orientation'] = 'portrait'; //landscape
	}

	if (isset($args['attachment']) && $args['attachment']) {
		$args['download'] = 1;
	} else if (!isset($args['download'])) {
		$args['download'] = 1;
	}

	// Dompdf 2.x (dompdf8/) requires PHP 7.2+. The legacy bundled Dompdf 0.6.x
	// copy was removed for CVE-2014-2383 (arbitrary file read). WordPress 6.8
	// already requires PHP 7.2.24+, so no pre-7.2 fallback is needed.
	return include(TRCDPDF_PATH . 'dompdf8/dompdf.php');
}
