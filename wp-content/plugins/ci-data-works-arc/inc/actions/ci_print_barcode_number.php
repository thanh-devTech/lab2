<?php
$settings = get_option('ci_pdf_data',array());
$randnum = $_REQUEST['code'];
$cnum = $_REQUEST['cnum'];
$type = $_REQUEST['type'];
$dup = $_REQUEST['dup'];

$moretext = '';
if(!empty($cnum))
{
    $moretext = $cnum;
}

$rotation = 0;
if($type=='rec'){
    $height = $settings['barcode_height_rec']>0? $settings['barcode_height_rec']:120;
    $width = $settings['barcode_width_rec']>0? $settings['barcode_width_rec']:200;
    $rotation = $settings['barcode_vertical_rec']? 90 : 0;
}
else if($type=='short'){
    $height = $settings['barcode_height_short']>0? $settings['barcode_height_short']:96;
    $width = $settings['barcode_width_short']>0? $settings['barcode_width_short']:200;
    $rotation = $settings['barcode_vertical_short']? 90 : 0;

}else{
    $height = $settings['barcode_height']>0? $settings['barcode_height']:96;
    $width = $settings['barcode_width']>0? $settings['barcode_width']:200;
    $rotation = $settings['barcode_vertical']? 90 : 0;

}
$arr = array(
    'moretext' => $moretext,
    'height' => $height,
    'width' => $width,
    'w' => $width,
    'type' => 'code128b',
    'transparency' => 1,
    'rotation' => $rotation
);
$url_img = apply_filters('barcode',$arr);
$furl = add_query_arg($arr,'admin.php?tr_action=show_qrcode');


//unset($arr['rotation']);
$img = apply_filters('barcode',$arr);
?>
<?php if(class_exists('barcode_qrcode_generator')):?>
    <br>
    <img id="image_code1" style="max-width:90%;max-height:250px;" src="<?php echo $img?>&text=<?php echo $randnum;?>" />
    <iframe id="frame1" style="width:300px;height:300px;position:absolute;left:-999999px" name="frame1" src="<?php echo $furl?>&text=<?php echo $randnum;?>&print=1&dup=<?php echo $dup?>"></iframe>
<?php endif;?>

<?php
exit;