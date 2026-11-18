<?php
global $wpdb;
global $ins_mix;
global $lab_id;

$where = '';
$join = '';
foreach($ins_mix as $k =>$txt)
{
    $where .= " and (WI.{$k} != '1' or WI.{$k} is null)";
}

if($lab_id > 0)
{
    //$where .= " and WI.lab_ID ='{$lab_id}' ";
}
$sql = "
        select AA.insname,AA.inscode,
        sum(AA.ibalance00) as ibalance00,
         sum(AA.ibalance30) as ibalance30,
         sum(AA.ibalance60) as ibalance60,
         sum(AA.ibalance90) as ibalance90,
         sum(AA.ibalance120) as ibalance120
         from `ci_ar_aging` AA
         LEFT JOIN `ci_payer_list` WI ON WI.payer_name = AA.insname
         {$join}
        where 1  {$where} group by AA.inscode
        ";
//echo $sql;
$rows = $wpdb->get_results($sql);
?>
<table class="dashboard_tbl">
    <tr class="center">
        <th></th>
        <th><span>Current</span></th>
        <th><span>30</span></th>
        <th><span>60</span></th>
        <th><span>90</span></th>
        <th><span>>120</span></th>
        <th><span>Total</span></th>
        <th><span>Notes</span></th>
    </tr>
    <?php foreach($rows as $row):
        if(empty($row->insname))
        {
            $row->insname = 'Self pay';
            $row->inscode = 'Self pay';
           // continue;
        }

        $total = $row->ibalance00 + $row->ibalance30 + $row->ibalance60 + $row->ibalance90 + $row->ibalance120 ;
        ?>
    <tr>
        <td><?php echo $row->insname?></td>
        <td><?php echo $row->ibalance00?></td>
        <td><?php echo $row->ibalance30?></td>
        <td><?php echo $row->ibalance60?></td>
        <td><?php echo $row->ibalance90?></td>
        <td><?php echo $row->ibalance120?></td>
        <td><?php echo $total?></td>
        <td><?php echo @$row->notes?></td>
    </tr>
    <?php endforeach;?>
    <?php
    $where2 = '';
    if($lab_id > 0)
    {
        //$where2 .= " and WI.lab_ID ='{$lab_id}' ";
    }
    foreach($ins_mix as $k =>$txt)
    {
        $query = "
               select
               sum(ibalance00) as ibalance00,
               sum(ibalance30) as ibalance30,
               sum(ibalance60) as ibalance60,
               sum(ibalance90) as ibalance90,
               sum(ibalance120) as ibalance120
               from ci_ar_aging AA
               LEFT JOIN `ci_payer_list` WI ON WI.payer_name = AA.insname
               where WI.{$k} = '1' {$where2}
            ";

        $row = $wpdb->get_row($query);

        $total = @$row->cbalance00 + @$row->ibalance30 + @$row->ibalance60 + $row->ibalance90 + $row->ibalance120 ;
        ?>
        <tr>
            <td><?php echo @$txt?></td>
            <td><?php echo @$row->ibalance00?></td>
            <td><?php echo @$row->ibalance30?></td>
            <td><?php echo @$row->ibalance60?></td>
            <td><?php echo @$row->ibalance90?></td>
            <td><?php echo @$row->ibalance120?></td>
            <td><?php echo @$total?></td>
            <td><?php echo @$row->notes?></td>
        </tr>
    <?php
    }
    ?>
</table>