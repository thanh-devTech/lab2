<?php
global $wpdb,$current_role;

$batch_id   = $_REQUEST['batch_id'];

$wpdb->query("SET SESSION group_concat_max_len = 1000000;");

$where = " ba.batch_id = '{$batch_id}' ";

if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $where .= " and ba.lab_id in ( {$lab_str} ) ";
}

$labs = $wpdb->get_results("select lab.lab_dba_init as lab,
                count(ba.mid) as count_batch,
                count(re.id) as count_re,
                sum(re.box_uploaded) as uploaded,
                group_concat(if(re.id>0,'',ba.position)) as pos,
                group_concat(if(re.estatus='C',re.id,0)) as report_ids,
                group_concat(if(re.id>0,'',ba.assen)) as assens
                from ci_batches ba
                JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
                left join ci_reports re ON ba.assen = re.patient_id
                where {$where}
                group by ba.lab_id order by lab.lab_dba_init");
?>

<div class="wraptbl">
<table class="wp-list-table widefat striped posts blue_tbl hover_tbl">
    <thead>
    <tr>
        <th>Lab</th>
        <th>Batches</th>
        <th>Results</th>
        <th>Pos</th>
        <th>number of rejected</th>
        <th>Uploaded</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach($labs as $lab):
        $num_rejected = 0;
        $pos = explode(',',$lab->pos);
        $assens = explode(',',$lab->assens);


        ?>
        <tr>
            <td><?php echo $lab->lab?></td>
            <td><?php echo $lab->count_batch?></td>
            <td><?php echo $lab->count_re?></td>
            <td><?php
                foreach($assens as $i =>$p)
                {
                    if(empty($p) || ($p>=5000 && $p<6000) || ($p>=8000 && $p<9000))
                    {
                        continue;
                    }
                    else if($p>=7000 && $p<8000 )
                    {
                        $num_rejected++;
                        continue;
                    }
                    echo '<span title="'.$assens[$i].'">'.$assens[$i],'</span>, ';
                }
                ?></td>
            <td><?php echo $num_rejected?></td>
            <td><?php echo $lab->uploaded?></td>
        </tr>
    <?php endforeach?>
    </tbody>
</table>
</div>
