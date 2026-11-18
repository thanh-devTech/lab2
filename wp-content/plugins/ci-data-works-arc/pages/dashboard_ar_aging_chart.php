<?php
global $wpdb;
global $ins_mix;
global $lab_id;

$googledata = array();

$trows[0] = array('X');
$trows[1] = array('0');
$trows[2] = array('30');
$trows[3] = array('60');
$trows[4] = array('90');
$trows[5] = array('120');

$where = '';
if($lab_id > 0)
{
    $where .= " and WI.lab_ID ='{$lab_id}' ";
}else{
    $rows = $wpdb->get_results("
        select * from `ci_ar_aging` where aging_ID = 1
        ");
    foreach($rows as $row)
    {
        if(empty($row->insname))$row->insname = 'Self Pay';
        $trows[0][] = $row->insname;
        $trows[1][] = floatval($row->cbalance00);
        $trows[2][] = floatval($row->cbalance30);
        $trows[3][] = floatval($row->cbalance60);
        $trows[4][] = floatval($row->cbalance90);
        $trows[5][] = floatval($row->cbalance120);
    }
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
               left join ci_insurancemix WI ON AA.insname = WI.ins_name and AA.inscode = WI.ins_code
               where WI.{$k} = '1' {$where}
            ";

    $row = $wpdb->get_row($query);
    $trows[0][] = $txt;
    $trows[1][] = floatval($row->ibalance00);
    $trows[2][] = floatval($row->ibalance30);
    $trows[3][] = floatval($row->ibalance60);
    $trows[4][] = floatval($row->ibalance90);
    $trows[5][] = floatval($row->ibalance120);
}

foreach($trows as $r)
{
    $googledata[] = $r;
}
global $google_chart_load;
$google_chart_load[] = 'drawChart_aging';
?>
<script type="text/javascript">

    //google.charts.load("visualization", "1", {packages:["corechart"]});
   // google.charts.setOnLoadCallback(drawChart_aging);
    function drawChart_aging() {

        var data = google.visualization.arrayToDataTable(<?php echo json_encode($googledata)?>);

// Set chart options
        var options = {

        };

// Instantiate and draw our chart, passing in some options.
        var chart = new google.visualization.AreaChart(document.getElementById('chart_aging'));
        chart.draw(data, options);
    }
</script>
<div id="chart_aging" style="width:600px; height:600px"></div>