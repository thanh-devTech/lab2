<?php
define('TRBQG_PATH', dirname(__FILE__) . '/');

$type           = 'code128';
$sendtobrowser  = true;
if (isset($attr['return_image']) && $attr['return_image']) {
    $sendtobrowser = false;
}

if (!isset($attr)) {
    $attr = $_GET;
}
extract($attr);

if (empty($text)) return;

$inc =  ini_get('include_path');
ini_set('include_path', $inc . ':' . dirname(__FILE__));



require_once('Image/Barcode2.php');
$code = new Image_Barcode2();

if ($showtext === 'false') $showtext = false;
$showtext = (bool) $showtext;
if ($transparency === 'false') $transparency = false;
$transparency = (bool) $transparency;

if (empty($rotation)) {
    $rotation = Image_Barcode2::ROTATE_NONE;
}

if (empty($width)) {
    $width = 1;
}
if (empty($height)) {
    $height = 60;
}

$img = $code->draw($text, $type, $imgtype, $sendtobrowser, $height - 20, $width, $showtext, $rotation, $moretext, $number);

return $img;
