<?php
global $wpdb;

header("Content-type: image/png");
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename=qr.png');
header('Content-Transfer-Encoding: binary');

$qr_dir = WP_CONTENT_DIR . '/qrimages/';
$f = $_GET['f'];
readfile($qr_dir.$f);
exit;
