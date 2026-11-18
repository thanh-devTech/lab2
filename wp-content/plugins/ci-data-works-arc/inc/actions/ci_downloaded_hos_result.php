<?php
global $wpdb;
$type = $_REQUEST['type'];
$subfolder = $_REQUEST['folder'];
$dl_type = $type.'-'.$subfolder;

$sql = "select * from ci_hos_download where dl_type='{$dl_type}' order by dl_ID asc";

$downloads = $wpdb->get_results($sql);

?>

    <div class="wraptbl">
<table class="wp-list-table widefat striped posts blue_tbl ">
    <thead>
    <tr>
        <th>#</th>
        <th>File</th>
        <th>Date</th>
        <th>By</th>
    </tr>
    </thead>
    <tbody>
    <?php
    foreach($downloads as $row){
        $filename = $subfolder.' '.$row->dl_from.'-'.($row->dl_from + $row->dl_count -1).'.pdf';
        $user_ids = explode(',',$row->dl_user_ids);
        ?>
        <tr>
            <td><?php echo $row->dl_ID?></td>
            <td>
                <a href="?tr_action=ci_download_hos_result&id=<?php echo $row->dl_ID?>&type=<?php echo $type?>&folder=<?php echo $subfolder?>"><?php echo $filename?></a>
            </td>
            <td><?php echo $row->dl_created?></td>
            <td>
                <?php
                $users = array();
                foreach($user_ids as $uid)
                {
                    $user = get_user_by('id',$uid);
                    //var_dump($user);
                    $users[]= $user->user_nicename;
                }
                echo implode('<br>',$users);
                ?>
            </td>
        </tr>
        <?php
    }
    ?>
    </tbody>
</table>
    </div>

<?php
exit;