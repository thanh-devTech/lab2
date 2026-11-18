<?php

$qr_dir = WP_CONTENT_DIR.'/qrimages/';
if(!file_exists($qr_dir))
{
	wp_mkdir_p($qr_dir);
}

if(isset($_REQUEST['delete'])){
	$filename = basename($_REQUEST['imageurl']);
	unlink($qr_dir.$filename);
	unlink($qr_dir.$filename.'.txt');

	wp_send_json(array('success'=>1));
}

$need_save = isset($_REQUEST['save']) && $_REQUEST['save'];
$size = isset($_REQUEST['s']) && $_REQUEST['s']>100 ? $_REQUEST['s'] : 100;
$url = urldecode($_REQUEST['url']);

$errorCorrectionLevel = 'H';
if (isset($_REQUEST['level']) && in_array($_REQUEST['level'], array('L','M','Q','H')))
	$errorCorrectionLevel = $_REQUEST['level'];

$matrixPointSize = 15;
if (isset($_REQUEST['size']))
	$matrixPointSize = min(max((int)$_REQUEST['size'], 1), $matrixPointSize);


$QR = trpdd_get_qr_code($url,$size,$errorCorrectionLevel,$matrixPointSize);


if($need_save)
{
	$return = array('success'=>'1');
	$filename = time().'.png';
	imagepng($QR, $qr_dir.$filename);

	file_put_contents($qr_dir.$filename.'.txt',$url.'|'.$size);
	$return['filename'] = $filename;
	$return['imageurl'] = get_bloginfo('url').'/wp-content/qrimages/'.$filename;
	$return['size'] = $size;
	wp_send_json($return);

}else{
	header("Content-type: image/png");
	if(isset($_GET['download'])){
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename=qr.png');
		header('Content-Transfer-Encoding: binary');
	}
	imagepng($QR);
	imagedestroy($QR);
}
