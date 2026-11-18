<?php
global $wpdb;


$batch_id = $_REQUEST['batch_id'];
$count = $wpdb->get_var("select count(ba.mid) from `ci_tecan` te 
                JOIN ci_batches ba ON te.te_assen = ba.assen 
                where ba.batch_id= '{$batch_id}'
                ");
echo 'Samples: '.$count;

$files = $wpdb->get_results("select DISTINCT (te.te_filename) from `ci_tecan` te 
                JOIN ci_batches ba ON te.te_assen = ba.assen 
                where ba.batch_id= '{$batch_id}'");

?>
<div class="wraptbl">
<table class="wp-list-table widefat striped posts blue_tbl hover_tbl">
    <thead>
    <tr>
        <th>File Name</th>
    </tr>
    </thead>
    <tbody>
    <?php
    foreach($files as $file)
    {
        ?>
        <tr>
            <td><a target="_blank" href="<?php echo WP_CONTENT_URL.'/log/tecan/'.$file->te_filename?>"><?php echo $file->te_filename?></a> </td>
        </tr>
        <?php
    }
    ?>
    </tbody>
</table>
</div>
