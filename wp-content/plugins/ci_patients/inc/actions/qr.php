<?php
global $wpdb;

$arr = explode('/qr/',$_SERVER['REQUEST_URI']);
$keystr = $arr[1];
if(strpos($keystr,'?')>0)
{
	list($keystr,$notkey) = explode('?',$keystr);
}
$arr = tr_patient_decode_id_btype($keystr);

$report_id = intval($arr['id']);
$btype = $arr['btype'];

if($report_id>0 && !empty($btype))
{
	$QR = tr_patient_qr_by_report($report_id,$btype,360,2);

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


exit;