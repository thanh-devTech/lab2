<?php
global $wpdb;
$print_mids = trim($_REQUEST['mids'],',');

$settings = get_option('ci_pdf_data',array());
$height = $settings['barcode_height_rec']>0? $settings['barcode_height_rec']:120;
$width = $settings['barcode_width_rec']>0? $settings['barcode_width_rec']:200;
$rotation = $settings['barcode_vertical_rec']? 90 : 0;
$top = 0;
$left = 0;
$export_img = isset($_REQUEST['img']);

if($export_img) {
	header ('Content-Type: image/png');
}else{
    echo '<html>
<body style="margin:0;padding:0;">
';
}
//$bats = $wpdb->get_results("select * from ci_batches where mid in ({$print_mids})");
$imagedata = array();
$print_mids = explode(',',$print_mids);
$has_barcode = false;
foreach($print_mids as $mid) {

    list($table,$mid) = tr_pdd_get_table_mid_by_id($mid);

    $bat = $wpdb->get_row("select * from {$table} where mid = {$mid} ");

    if(!$bat)continue;

    $count_t = (stripos($bat->type_of_specimen,'Urine')!==false)? 2 : 1;
    if($count_t==2 && stripos($bat->panel_tests_requested,'ETG')!==false){
        $count_t = 3;
    }

    if(!isset($_REQUEST['always'])){
	    if(isset($_GET['reprint']) && stripos($bat->type_of_specimen,'Oral')!==false){
		    continue;
	    }

	    if($bat->type_of_specimen=='JVHL' || stripos($bat->initials1,'JVHL')!==false){
		    continue;
	    }
    }


    $moretext = '';
    if (!empty($bat->patient_name)) {
        $moretext .= $bat->patient_name;
    }
    $dob_time = strtotime($bat->dob);
    if(!empty($bat->dob) && $dob_time > 0)
    {
        $dob = date('m/d/Y',$dob_time);
        $moretext.= '  '.$dob;
    }
    $arr = array(
        'text' => $bat->assen,
        'moretext' => urlencode($moretext),
        'number' => $bat->position,
        'height' => $height,
        'width' => $width,
        'w' => $width,
        'type' => 'code128b',
        'transparency' => 1,
        'rotation' => $rotation,
        'imgtype'  => 'png',
        'showtext' => 1,
        'remake'   => 0
    );

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

    for($i=1;$i<=$count_t;$i++)
    {
        if($export_img)
        {
            echo file_get_contents($url_img);
        }else{
	        echo '<div style="page-break-after: always;margin-bottom:100px">';
	        echo  '<img src="'.$url_img.'" '.$size.' alt="'.esc_attr($arr['text']).'" style="margin-left:'.$left.'px;margin-top:'.$top.'px;"  />';
	        echo '</div>';
        }

    }
    $has_barcode = true;

}


if($export_img) {

}else{

	if ( $has_barcode && isset( $_REQUEST['print'] ) ) {
		?>
        <script>
            setTimeout(function () {
                window.print();
            }, 700);
        </script>
		<?php
	} else if ( ! $has_barcode ) {
		?>
        <script>alert('Not found')</script>
		<?php
	}
	echo '</body>
</html>';
}

