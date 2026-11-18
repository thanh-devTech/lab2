<?php

$imagedata = $_POST['imagedata'];
$orientation = isset($_POST['orientation']) ? $_POST['orientation'] : 'landscape';

//$html = 'abc';
$month_year = isset($_POST['month_year'])? $_POST['month_year']: date_i18n('M_Y');
$filename = $tab_title.'_Statistics_Report_'.$report_object.'_'.$month_year.'.pdf';

header("Pragma: public");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: private",false);
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=\"$filename\";" );
header("Content-Transfer-Encoding: binary");

$tmp_name = microtime(true);
$imageurl = WP_CONTENT_DIR.'/log/'.$tmp_name.'.png';
$imagedata = tr_get_image_from_base64($imagedata);
file_put_contents($imageurl,$imagedata);

$pdfurl = WP_CONTENT_DIR.'/log/'.$tmp_name.'.pdf';
exec("convert {$imageurl} {$pdfurl}");
readfile($pdfurl);
unlink($imageurl);
unlink($pdfurl);
exit;
