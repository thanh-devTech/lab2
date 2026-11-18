<?php
global $wpdb;

$cli_ID = $_REQUEST['cli_ID'];
$btype = isset($_REQUEST['rtab'])? $_REQUEST['rtab'] : 'tox';
$date_from = isset($_REQUEST['date_from'])? $_REQUEST['date_from']: '';
$date_to   = isset($_REQUEST['date_to'])? $_REQUEST['date_to'] :'';
$where_ba = "";
if(!empty($date_from))
{
    $where_ba.= " and ba.doc >= '{$date_from}' ";
}

if(!empty($date_to))
{
    $where_ba.= " and ba.doc <= '{$date_to} 23:59:59' ";
}

$batch_tbl = tr_pdd_get_table_by_btype($btype);
$report_tbl = tr_pdd_get_table_report_by_btype($btype);
$result_tbl = tr_pdd_get_table_result_by_btype($btype);

$sql = "select rs.drug, count(rs.drug) as count_drug 
        from {$result_tbl} rs
        join {$report_tbl} re ON re.id = rs.report_id
        join {$batch_tbl} ba On ba.assen = re.patient_id
        where ba.cli_ID = {$cli_ID}  and rs.has_order = 1
        {$where_ba}
        group by rs.drug";

$tests = $wpdb->get_results($sql);

if(!empty($wpdb->last_error))
{
    var_dump($wpdb->last_error);
}
?>
<div class="list_drug frow" style="margin:15px">
    <?php
    foreach($tests as $test)
    {
        ?>
        <div class="col3">
            <div class="frow">
                <div class="col23"><?php echo $test->drug?></div>
                <div class="col13"><?php echo $test->count_drug?></div>
            </div>
        </div>
    <?php
    }
    if(count($tests)==0)
    {
        echo '<p>Not found</p>';
    }
    ?>
</div>
