<?php

$randnum=$_REQUEST['text'];
$editable = isset($_REQUEST['editable']);
$type = $_REQUEST['type'];
$moretext = '';
$pa = trim($_REQUEST['pa']);
$dob = trim($_REQUEST['dob']);

$settings = get_option('ci_pdf_data',array());

if(!empty($pa))
{
    $pa = str_replace('|',',',$pa);
    $pa = trim($pa,',');
    if($pa!=','){
        $pa = str_replace(',',', ',$pa);
        $moretext.= $pa;
    }

}
if(!empty($dob))
{
    $dob = date('m/d/Y',strtotime($dob));
    $moretext.= '  '.$dob;
}
if(isset($_REQUEST['reset_number'])){
    update_option('randoum_create_r_index',0);
}
if(trim($randnum)==''){
    $r_index=get_option("randoum_create_r_index",1);
    $randnum = $settings['barcode_key'].sprintf('%0' . 6 . 's', $r_index);
    update_option('randoum_create_r_index',intval($r_index)+1);
}


if($type=='short'){
    $height = $settings['barcode_height_short']>0? $settings['barcode_height_short']:96;
    $width = $settings['barcode_width_short']>0? $settings['barcode_width_short']:200;

}else{
    $height = $settings['barcode_height']>0? $settings['barcode_height']:96;
    $width = $settings['barcode_width']>0? $settings['barcode_width']:200;

}

$arr = array(
    'moretext' => $moretext,
    'height' => $height,
    'width' => $width,
    'w' => $width,
    'type' => 'code128b',
    'transparency' => 1,
    'rotation' => 90
);
$url_img = apply_filters('barcode',$arr);
$furl = add_query_arg($arr,'admin.php?tr_action=show_qrcode');


unset($arr['rotation']);
$img = apply_filters('barcode',$arr);
?>
    <br />
    <input type="text" id="assen_auto_num" data-old="<?php echo $randnum;?>" value="<?php echo $randnum;?>" <?php if(!$editable)echo 'readonly'?> />
    <input type="button" id="button_printer" class="button" value="<?php echo (class_exists('barcode_qrcode_generator'))?'Print':'Use'?>"/><br />

<?php if(class_exists('barcode_qrcode_generator')):?>
    <br>
    <img id="image_code1" style="width:100%;max-height: 250px;" src="<?php echo $img?>&text=<?php echo $randnum;?>" />
    <iframe id="frame1" style="width:300px;height:300px;position:absolute;left:-999999px" name="frame1" src="<?php echo $furl?>&text=<?php echo $randnum;?>"></iframe>
<?php endif;?>
    <script type="application/javascript">
        jQuery(function($){
            var $has_change_ass = false;
            $("#box_create_rand").data("a",'<?php echo $randnum;?>');
            function ci_change_assen_num(needprint)
            {
                $('#button_printer').val('Loading...');
                $has_change_ass = true;
                var txt = jQuery('#assen_auto_num').val();
                $('#assen_auto_num').data('old',txt);
                $('#frame1').on('load',function(){
                    $has_change_ass = false;
                    $('#button_printer').val('Print');
                    if(needprint)
                    {
                        $('#number_assen').val(txt);
                        $('#confirm_assen').val(txt);
                        $("#box_create_rand").data("a",txt);
                        setTimeout(function(){document.getElementById("frame1").contentWindow.print();},1000);

                    }
                });
                $('#frame1').attr('src','<?php echo $furl?>&text='+txt);
                $('#image_code1').attr('src','<?php echo $img?>&text='+txt);
            }
            $('#button_printer').click(function(){
                var txt = $('#assen_auto_num').val();
                if(txt!=$('#assen_auto_num').data('old')){
                    ci_change_assen_num(true);
                    return;
                }
                $('#number_assen').val(txt);
                $('#confirm_assen').val(txt);
                $("#box_create_rand").data("a",txt);
                <?php if(class_exists('barcode_qrcode_generator')){?>
                document.getElementById("frame1").contentWindow.print();
                <?php }else{?>
                tb_remove();
                <?php };?>
            })
        })
    </script>
<?php
exit;