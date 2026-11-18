<?php
global $wpdb;
if(!session_id())
{
	session_start();
}
$file       = $_FILES['insu_file'];
$type       = $_POST['type'];


$dir        = tr_get_ins_card_dir();
$ext        =  pathinfo($file['name'],PATHINFO_EXTENSION);


$filename   = $type.'_'.time().rand(100,999).'.'.$ext;
$filepath   = $dir['path'].'/'.$filename;

while (file_exists($filepath)){
	$filename   = $type.'_'.time().rand(100,999).'.'.$ext;
	$filepath   = $dir['path'].'/'.$filename;
}


if(!in_array($ext,array('jpg','png','jpeg','pdf','gif')))
{
	exit;
}

if(move_uploaded_file($file['tmp_name'],$filepath))
{
	echo 'ok';
	$log = is_array($_SESSION['upload_insurance_photos'])? $_SESSION['upload_insurance_photos'] : array();
	$log[$type] = $filename;

	$_SESSION['upload_insurance_photos'] = $log;
}
else{
	echo 'error';
}

exit;