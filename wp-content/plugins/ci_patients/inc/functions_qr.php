<?php
function trpdd_get_qr_code($url,$size=100,$errorCorrectionLevel='H',$matrixPointSize=15,$margin=1)
{
	require_once(TRCPPR_PATH.'vendor/autoload.php'); // QRcode -> endroid/qr-code shim (inc/qrcode-compat.php)
	define('QR_CACHE_DIR',WP_CONTENT_DIR.'/cache/qrcache/');
	if(!file_exists(QR_CACHE_DIR))
	{
		wp_mkdir_p(QR_CACHE_DIR);
	}
	$filename = tempnam('/tmp','qrcode');

	QRcode::png($url, $filename, $errorCorrectionLevel, $matrixPointSize, $margin);

	$QR = imagecreatefrompng($filename);
	unlink($filename);

	$QR = imagescale($QR , $size, $size);

	$logo = imagecreatefromjpeg(TRCPPR_PATH.'images/logo_qr.jpg');

	$logo_width = imagesx($logo);
	$logo_height = imagesy($logo);

	$QR_width = imagesx($QR);
	$QR_height = imagesy($QR);

	// Scale logo to fit in the QR Code
	$logo_qr_width = ($QR_width-10)/3;
	$scale = $logo_width/$logo_qr_width;
	$logo_qr_height = $logo_height/$scale;

	imagecopyresampled($QR, $logo, $QR_width/3, $QR_height/3, 0, 0, $logo_qr_width, $logo_qr_height, $logo_width, $logo_height);

	return $QR;
}