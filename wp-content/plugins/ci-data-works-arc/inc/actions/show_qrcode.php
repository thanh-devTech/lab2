<?php
$settings = get_option('ci_pdf_data',array());
if($type=='short'){
    $height = $settings['barcode_height_short']>0? $settings['barcode_height_short']:96;
    $width = $settings['barcode_width_short']>0? $settings['barcode_width_short']:200;

}else{
    $height = $settings['barcode_height']>0? $settings['barcode_height']:96;
    $width = $settings['barcode_width']>0? $settings['barcode_width']:200;

}

if(empty($_GET['width'])){
    $_GET['width'] = $width;
}
if(empty($_GET['height'])){
    $_GET['height'] = $height;
}
$arr = shortcode_atts(array(
    'text'     => '',
    'moretext' => '',
    'type'     => "code128b",
    'imgtype'  => 'png',
    'height'   => 60,
    'width'    => 100,
    'w'        => 100,
    'rotation' => 90
), $_GET);


$arr['w'] = $arr['width'];

$left = $_GET['left']>0? $_GET['left']:0;
$top = $_GET['top']>0? $_GET['top']:0;
?>
    <html>
    <body style="margin:0;padding:0;height:<?php echo $arr['height']?>px">
    <?php
    $url_img = apply_filters('barcode',$arr);

    if($arr['rotation']==90)
    {
        $w = $arr['heigth'];
        $h = $arr['width'] ;
    }else{
        $w = $arr['width'];
        $h = $arr['height'] + ($arr['height']/20);
    }

    $size = 'width="'.$w.'" height="'.$h.'"';

    echo  '<img src="'.$url_img.'" '.$size.' alt="'.esc_attr($arr['text']).'" style="margin-left:'.$left.'px;margin-top:'.$top.'px"  />';
    if(isset($_GET['b2']))
    {
        echo  '<img src="'.$url_img.'" '.$size.' alt="'.esc_attr($arr['text']).'" style="margin-left:'.$left.'px;margin-top:30px"  />';
    }

    if(isset($_REQUEST['print'])){
        ?>
        <script>
            setTimeout(function(){
                window.print();
                parent.tb_remove();
            },700);
        </script>
        <?php
    }
    ?>
    <?php if(isset($_GET['ps'])):?>
    <style type="text/css" media="print">
        @page { size: <?php echo $w?>px  <?php echo $h+60?>px; }
    </style>
    <?php endif;?>
    </body>
    </html>
<?php
exit;