<?php
global $wpdb,$current_role;
$batch_id = $_REQUEST['batch_id'];


$files = $wpdb->get_results("select distinct(te_filename)  as file,count(te_filename) as count
                    from ci_tecan where te_batch_id='{$batch_id}' ");

if(count($files)==0)
{
    echo 'Not found';
    return;
}
?>
<div>Total: <?php echo count($files)?></div>

<div class="wraptbl">
<table class="wp-list-table widefat striped posts blue_tbl hover_tbl">
    <thead>
    <tr>
        <th>File Name</th>
        <th>Count</th>
    </tr>
    </thead>
    <tbody>
    <?php
    foreach($files as $file)
    {
        ?>
        <tr>
            <td><?php echo $file->file?></td>
            <td>1</td>
        </tr>
        <?php
    }
    ?>
    </tbody>
</table>
</div>
