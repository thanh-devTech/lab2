<?php
global $wpdb,$current_role;
$user_id = get_current_user_id();
$current_month = ($_REQUEST['m'])? $_REQUEST['m']: $current_month;
$from_date = $current_month.'-01';
$to_date = date('Y-m-t',strtotime($from_date));

$medis = tr_pdd_get_report_medis();
$pre = $_REQUEST['pre'];
$cli_ids = '';
if(($_REQUEST['cg'])>0){
    $cli_ids  = $wpdb->get_col("select cID from ci_clinic where cli_status = 1 and cli_group_ID = {$_REQUEST['cg']}");
    $cli_ids = implode(',',$cli_ids);
}
else if($_REQUEST['cid'] > 0){
    $cli_ids = $_REQUEST['cid'];
    if(empty($cli_ids)){
        return;
    }
}
else if(tr_current_role_in(array('office_manager','multi_site_manager','clinic'))){
    $cid = get_user_meta($user_id,'_clinic_id',true);
    $cli_ids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
    $cli_ids = implode(',',$cli_ids);
}else{
    $cli_ids = $_REQUEST['cid'];
    if(empty($cli_ids)){
        return;
    }
}

$where = '';
$where_ba = '';
if($cli_ids!=='all'){
    $where_ba .=" and ba.cli_ID in ({$cli_ids}) ";
}

$where.= " and re.estatus='C'";

if(!empty($pre))
{
    if($pre=='w_o'){
        $where_ba.= " and ba.current_medication not like '%Vivitrol%' ";
    }
    else if($pre=='just_vivitrol'){
        $where_ba.= " and ba.current_medication like '%Vivitrol%' ";
    }
}

$sql_total = "select count(*) from ci_reports re 
                    join ci_batches ba ON ba.assen = re.patient_id
                    where ba.doc >='{$from_date}' and ba.doc <= '{$to_date} 23:59:59' {$where} {$where_ba}";
$total_result = $wpdb->get_var($sql_total);

if(isset($_REQUEST['debug'])){
    echo $sql_total;
    echo '<br><br>';
}




$data = array();
foreach($medis as $code)
{
    $sql_all = "select 
                    COUNT(re.id) as c, 
                    SUM(re.has_{$code}_pos) as pos_count
                from ci_reports re
                join (select ba.assen,ba.doc from ci_batches ba where ba.doc >='{$from_date}' and ba.doc <= '{$to_date} 23:59:59' {$where_ba} ) 
                        as ba ON ba.assen = re.patient_id
                    where 1 {$where} ";
    $r = $wpdb->get_row($sql_all, ARRAY_A);

    $data[$code]['POS'] = $r['pos_count'];
    $data[$code]['NEG'] = max(0,$r['c'] - $r['pos_count']);
}




if(isset($_REQUEST['debug'])){
    echo $sql_all;echo '<br><br>';
    var_dump($data_all);echo '<br><br>';
    echo ($sql_pos);echo '<br><br>';
}
$googledata = array();
$googledata[]  = array('Tests','POS','NEG',array('role'=>'annotation'));
global $google_chart_load;
$google_chart_load[] = 'drawChart_report1';
$chartjs = array('labels'=>array(),'datasets'=>array(
    0=> array('label'=>'NEG','fillColor'=>'blue','strokeColor'=>'blue'),//neg
    1=> array('label'=>'POS','fillColor'=>'red','strokeColor'=>'red'),//pos
));
?>

<div style="float:left;width:35%">
    <table class="dashboard_tbl">
        <tr>
            <th>Total Results: </th>
            <td><?php echo $total_result?></td>
            <td></td>
        </tr>
        <tr>
            <th>Tests</th>
            <th>Pos/Neg</th>
            <th>Percent Positive</th>
        </tr>
        <?php foreach($medis as $txt => $code):

            $pos = intval($data[$code]['POS']);
            $neg = intval($data[$code]['NEG']) ;

            $googledata[] = array($txt,intval($pos),intval($neg),'');
            //$chartjs['labels'][]=$txt;
           // $chartjs['datasets'][1]['data'][]=$pos;
           // $chartjs['datasets'][0]['data'][]=$neg;
            ?>
            <tr>
                <td title="<?php echo $code?>"><?php echo $txt?></td>
                <td>
                    <?php echo $pos?> / <?php echo $neg?>
                </td>
                <td>
                    <?php if($pos+$neg>0)echo round($pos/($pos+$neg)*100,2)?>%
                </td>
            </tr>

        <?php endforeach;?>
    </table>
</div>
<div style="float:right;width:62%">
    <div id="chart_report_div" style="width:700px; height:550px"></div>
    <?php /**<div id="js-legend" class="chart-legend inline-legend center"></div>
    <canvas width="700" height="450" id="chart_report_canvas"></canvas>*/?>

    <script type="text/javascript">

        function drawChart_report1() {

            var data = google.visualization.arrayToDataTable(<?php echo json_encode($googledata)?>);

            var options = {
                width: 700,
                height: 550,
                legend: { position: 'top', maxLines: 8 },
                //bar: { groupWidth: '75%' },
                isStacked: true,
                colors: ['red','blue'],
                };

            // Instantiate and draw our chart, passing in some options.
            var chart = new google.visualization.BarChart(document.getElementById('chart_report_div'));
            chart.draw(data, options);
        }
        <?php if(isset($_REQUEST['tr_action'])):?>
        drawChart_report1();
        <?php endif;?>
        <?php /**
                var data = <?php echo json_encode($chartjs)?>;
                var ctx = document.getElementById('chart_report_canvas').getContext('2d');
                var myChart = new Chart(ctx).StackedBar(data, { });
                document.getElementById('js-legend').innerHTML = myChart.generateLegend();
         */?>

    </script>

</div>
<div class="clear"></div>


