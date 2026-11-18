<?php
global $import_file;
global $import_act;

if(isset($_GET['import_act'])){
    $import_act = $_GET['import_act'];
    $import_file= 'zip';
}

$upload_dir = tr_wp_upload_dir();


if ( ini_get( 'upload_max_filesize' ) ) {
    $upload_max = filter_var( ini_get( 'upload_max_filesize' ), FILTER_SANITIZE_STRING );
} else 	{
    $upload_max = __( 'N/A');
}

?>
<h2>Import Data</h2>
<p>Max file size: <?php echo $upload_max;?></p>
<?php Tr_Session_Class::show()?>

<?php if($import_act=='batch' || $import_act=='zip_batch'):

    $import_dir = $upload_dir['basedir'].'/batch_imports';
    $total = 0;
    if($import_act=='batch'){

        $file = $import_dir.'/'.$import_file;
        $f = fopen($file,'r');
        while($row = fgetcsv($f)) {
            if(empty($row[0]) && empty($row[1]) )continue;
            if(count($row)>0){
                $total++;
            }
        }
        if($total>0)$total-=2;
        $total.= ' records';
    }else{
        $f = opendir($import_dir);
        while($file = readdir($f))
        {
            if(is_file($import_dir.'/'.$file) && stripos($file,'.csv')!==false)
            {
                $total++;
            }
        }
        $total.= ' files';
    }

    ?>
    <div class="">
        <h3>Import Loadlist</h3>
        <p>Uploaded successfully. Please waiting import...</p>
        Total: <?php echo $total?>
        <ol id="import_status"></ol>
    </div>
    <script>
        jQuery(function($){
            function tr_run_import()
            {
                $('#import_status').append('<li class="loading">Importing...</li>');
                $.ajax({
                    url: ajaxurl,
                    type:'post',
                    data:{action:'tr_ajax',tr_action:'run_import_batch_data','file':'<?php echo $import_file?>'},
                    dataType:'json',
                    success:function(rs)
                    {
                        $('#import_status .loading').remove();
                        $('#import_status').append('<li>'+rs.html+'</li>');
                        if(rs.status!='done'){
                            tr_run_import();
                        }else{
                            $('#import_status').append('<div>Import done!</div>');
                        }
                    },
                    error: function()
                    {
                        $('#import_status .loading').remove();
                        tr_run_import();
                    }
                });
            }
            tr_run_import();
        })
    </script>


<?php elseif($import_act=='bill' || $import_act=='zip_bill'):

        $import_dir = $upload_dir['basedir'].'/bill_imports';
        $total = 0;
        if($import_act=='bill'){

            $file = $import_dir.'/'.$import_file;
            $f = fopen($file,'r');
            while($row = fgetcsv($f)) {
                if(count($row)>0){
                    $total++;
                }
            }
            if($total>0)$total-=1;
            $total.= ' records';
        }else{
            $f = opendir($import_dir);
            while($file = readdir($f))
            {
                if(is_file($import_dir.'/'.$file) && stripos($file,'.csv')!==false)
                {
                    $total++;
                }
            }
            $total.= ' files';
        }

        ?>
        <div class="">
            <h3>Import eThomas Data</h3>
            <p>Uploaded successfully. Please waiting import...</p>
            Total: <?php echo $total?>
            <ol id="import_status"></ol>
            <div id="import_repair_status"></div>
        </div>
        <script>
            jQuery(function($){
                function tr_run_import_repair()
                {
                    $('#import_repair_status').append('<div class="loading">Repairing...</div>');
                    $.ajax({
                        url: ajaxurl,
                        type:'post',
                        data:{action:'tr_ajax',tr_action:'run_ci_import_billing_data_repair'},
                        dataType:'json',
                        success:function(rs)
                        {
                            $('#import_repair_status .loading').remove();
                            $('#import_repair_status').html(rs.html);
                            if(rs.status!='done'){
                                tr_run_import_repair();
                            }
                        },
                        error: function()
                        {
                            $('#import_repair_status .loading').remove();
                            tr_run_import_repair();
                        }
                    });
                }
                function tr_run_import()
                {
                    $('#import_status').append('<li class="loading">Importing...</li>');
                    $.ajax({
                        url: ajaxurl,
                        type:'post',
                        data:{action:'tr_ajax',tr_action:'run_ci_import_billing_data','file':'<?php echo $import_file?>'},
                        dataType:'json',
                        success:function(rs)
                        {
                            $('#import_status .loading').remove();
                            $('#import_status').append('<li>'+rs.html+'</li>');
                            if(rs.status!='done'){
                                tr_run_import();
                            }else{
                                $('#import_status').append('<div>Import done!</div>');
                                tr_run_import_repair();
                            }
                        },
                        error: function()
                        {
                            $('#import_status .loading').remove();
                            tr_run_import();
                        }
                    });
                }
                tr_run_import();
            })
        </script>
    <?php else:?>
<div style="float: left;width:48%">

    <h3>Import LoadList</h3>

    <form method="post" class="main_form" enctype="multipart/form-data">
        <label>Batch CSV File: </label>
        <input type="file" name="batch" />
        <br>
        <input type="submit" class="button" value="Upload & Import"/>
        <input type="hidden" name="tr_action" value="ci_import_batch_data"/>
    </form>
</div>
<div style="float:right;width: 48%">
    <h3>Import eThomas Data</h3>
    <form method="post" class="main_form" enctype="multipart/form-data">
        <label>Billing CSV File: </label>
        <input type="file" name="billing" />
        <br>
        <input type="submit" class="button" value="Upload & Import"/>
        <input type="hidden" name="tr_action" value="ci_import_billing_data"/>
    </form>
</div>
<?php endif;?>