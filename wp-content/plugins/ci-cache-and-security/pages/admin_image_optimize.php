<?php
$tabs = array(
    'opt_images' => 'Optimize Images',
    'fix_image_alt' => 'Fix Alt tag',
    'edit_image_alt' => 'Edit Alt Tag',
);
$tab = isset($_REQUEST['tab'])? $_REQUEST['tab']: key($tabs);
if(!isset($tabs[$tab]))
{
    $tab = key($tabs);
}
?>
<h2 class="nav-tab-wrapper">
    <?php
    foreach($tabs as $k => $txt){
        $class = $tab == $k ? 'nav-tab-active':'';
        $link = Tr_Base_Class_VS::link(array('tab'=>$k,'act'=>'','id'=>''),false);
        echo '<a class="nav-tab '.$class.'" href="'.$link.'">'.$txt.'</a>';
    }
    ?>
</h2>

<?php
if($tab=='fix_image_alt'){
    include('admin_ci_fix_image_alt.php');
    return;
}
else if($tab=='edit_image_alt'){
    include('admin_ci_edit_image_alt.php');
    return;
}
?>

<div class="wrap">
    <form method="post" id="form_optimize">
        <div class="row">
            <label>Max Width</label>
            <input type="number" name="max_width" value="1920" min="900"/>px
        </div>
        <div class="row">
            <label>Max Height</label>
            <input type="number" name="max_height" value="1100" min="600"/>px
        </div>
        <div class="row">
            <label>Max Size</label>
            <input type="number" name="max_size" value="500" min="300"/>KB
        </div>
        <div class="row">
            <input type="submit" class="button" value="Run">
            <input type="button" class="button" id="stop_button" style="display: none;" value="Stop">
            <input type="hidden" name="tr_action" value="ci_run_optimize_images">
        </div>
    </form>
</div>
<div id="result_total"></div>
<ol id="result_preview" style="height:300px;overflow: auto;padding-left:70px;border:1px solid #ddd"></ol>
<script>
    jQuery(function ($) {
        var $data = null;
        var $position = '0';
        var $stopped = false;
        function run_optimize_images() {
            $.ajax({
                data: $data +'&position=' + $position,
                dataType:'json',
                type:'post',
                success:function (rs) {
                    if(rs.status=='done'){
                        $('#result_preview').append('<li>Done</li>');
                        $(this).hide();
                    }else{
                        $position = rs.position;
                        $('#result_preview').append('<li>'+rs.msg+'</li>');
                        if($stopped==false)
                        {
                            setTimeout(run_optimize_images,1000);
                        }

                    }
                    $('#result_total').html(rs.status_msg);
                },
                error:function () {
                    if($stopped==false)
                    {
                        setTimeout(run_optimize_images,3000);
                    }
                }
            })
        }
        $('#stop_button').click(function () {
            $stop = true;
            $(this).hide();
        });
        $('#form_optimize').submit(function () {
            $data = $(this).serialize();
            $position = 0;
            $('#result_preview').html('');
            $stopped = false;
            $('#stop_button').show();
            run_optimize_images();
            return false;
        })
    })
</script>