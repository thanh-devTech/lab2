<?php
$settings = get_option('ci_pdf_data',array());
$height = $settings['barcode_height']>0? $settings['barcode_height']:96;
$width = $settings['barcode_width']>0? $settings['barcode_width']:200;

$arr = array(
    //'moretext' => $moretext,
    //'height' => $height,
    //'width' => $width,
    //'w' => $width,
    'type' => 'code128b',
    'transparency' => 1,
    //'rotation' => 90,
   // 'b2' => 1
);
$furl = add_query_arg($arr,'admin.php?tr_action=show_qrcode');

$url_img = apply_filters('barcode',$arr);
?>

<div class="wrap">
    <h2>Barcode</h2>
    <div class="row">
        <label>Text</label>
        <input type="text" id="text" value="Text">
        <input type="button" class="button-primary print_barcode2" value="Print"/>
    </div>

    <div class="row">
        <label>Width</label>
        <input type="text" id="width" value="<?php echo $width?>">
    </div>

    <div class="row">
        <label>Height</label>
        <input type="text" id="height" value="<?php echo $height?>">
    </div>
    <div class="row">
        <label>Text More</label>
        <input type="text" id="textmore" value="John Doe   01/01/2000">
    </div>
    <div class="row">
        <label>Margin left</label>
        <input type="text" id="left" value="0">px
    </div>
    <div class="row">
        <label>Margin Top</label>
        <input type="text" id="top" value="0">px
    </div>
    <div class="row">
        <label>Horizontal</label>
        <input type="checkbox" id="horizontal" checked value="1">
    </div>

</div>
<iframe id="frame1" style="width:1000px;height:1000px;overflow: hidden;" src="about:blank"></iframe>
<style>
    .row label{
        float:left;
        width:100px;
    }
    .row{
        clear: both;
        margin-bottom:5px;
    }
</style>
<script>
    jQuery(function ($) {
        $('body').on('click','.print_barcode',function () {
            var $text = $('#text').val();
            var $width = $('#width').val();
            var $height = $('#height').val();
            var $left = $('#left').val();
            var $top = $('#top').val();
            var $more = $('#textmore').val();
            var $rotation = $('#horizontal').is(':checked')? 0 : 90;
            var $url = '<?php echo $furl?>&text='+$text+'&width='+$width+'&height='+$height+'&left='+$left+'&top='+$top+'&moretext='+$more+'&rotation='+$rotation+'&print=1';
            $('#frame1').attr('src',$url);
            return false;
        })

        $('body').on('click','.print_barcode2',function () {
            var $text = $('#text').val();
            var $width = $('#width').val();
            var $height = $('#height').val()-77;
            var $more = $('#textmore').val();
            var $rotation = $('#horizontal').is(':checked')? 0 : 90;
            var $url = '<?php echo $url_img?>&text='+$text+'&width='+$width+'&height='+$height+'&moretext='+$more+'&rotation='+$rotation;
            $('#frame1').attr('src',$url);
            var $called = false;
            $('#frame1').on('load',function () {
                wd = document.getElementById('frame1').contentWindow;
                wd.print();
                $called = true;
            })
            setTimeout(function(){
                if($called==false)
                {
                    wd = document.getElementById('frame1').contentWindow;
                    wd.print();
                }
            },5000);
            return false;
        })
    })
</script>