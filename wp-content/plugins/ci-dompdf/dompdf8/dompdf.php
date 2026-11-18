<?php
global $ci_last_pdf_options;
require(__DIR__ . '/vendor/autoload.php');

use Dompdf\Dompdf;

if (isset($_REQUEST['debugpdf'])) {

	global $_dompdf_warnings;
	$_dompdf_warnings = array();
	global $_dompdf_show_warnings;
	$_dompdf_show_warnings = true;
	global  $_dompdf_debug;
	$_dompdf_debug = true;
}


//$content = str_ireplace('DejaVu Sans','Times-Roman',$content);
if (!defined('DOMPDF_ENABLE_PHP')) {
	define("DOMPDF_ENABLE_PHP", true);
}
if (!defined('DOMPDF_ENABLE_REMOTE')) {
	define("DOMPDF_ENABLE_REMOTE", true);
}

if (!defined('DOMPDF_ENABLE_CSS_FLOAT')) {
	define("DOMPDF_ENABLE_CSS_FLOAT", true);
}
if (!defined('DOMPDF_ENABLE_JAVASCRIPT')) {
	define("DOMPDF_ENABLE_JAVASCRIPT", true);
}





$contxt = stream_context_create([
	'ssl' => [
		'verify_peer' => FALSE,
		'verify_peer_name' => FALSE,
		'allow_self_signed' => TRUE
	]
]);
$opts = [
	'isRemoteEnabled' => true,
	'isJavascriptEnabled' => true,
	'isHtml5ParserEnabled' => true,
	'isPhpEnabled' => true,
	'chroot' => ABSPATH,
	'tempDir' => '/tmp',
	'fontCache' => '/tmp',
	'fontDir' => __DIR__ . '/vendor/dompdf/dompdf/lib/fonts',
];

foreach ($args as $k => $v) {
	$opts[$k] = $v;
}



$ci_last_pdf_options = $opts;

$dompdf = new Dompdf($opts);


$dompdf->setHttpContext($contxt);

$dompdf->loadHtml($content);

$dompdf->setPaper($args['size'], $args['orientation']);

$dompdf->render();

if (strpos($content, 'need_add_page_numbers') !== false) {
	$canvas = $dompdf->getCanvas();
	$fontMetrics = $dompdf->getFontMetrics();

	$font = $fontMetrics->get_font('helvetica', 'normal');
	$size = 8;

	$y = $canvas->get_height() - 15;
	$x = $canvas->get_width() - 20 - $fontMetrics->get_text_width('Page 8 of 8', $font, $size);

	$canvas->page_text(
		$x,
		$y,
		'Page {PAGE_NUM} of {PAGE_COUNT}',
		$font,
		$size,
		[0.55, 0.55, 0.55]
	);
}

if (isset($args['filename'])) {
	$dompdf->stream($args['filename'], array(
		'Attachment' => $args['download'] ? true : false,
		'compress' => isset($args['compress']) && $args['compress']
	));

	return $args['filename'];
} else if (isset($args['filepath'])) {
	$pdf = $dompdf->output();
	file_put_contents($args['filepath'], $pdf);
	return $args['filepath'];
} else {
	return true;
}
