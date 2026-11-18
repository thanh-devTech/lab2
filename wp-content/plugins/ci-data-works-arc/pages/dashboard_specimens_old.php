<?php

global $dashboard_specimens;
global $dashboard_specimens_chart;

global $total_specimens_ytd;
global $total_specimens_mtd;
global $total_specimens_td;

$total_specimens_ytd = 0;
$total_specimens_mtd = 0;
$total_specimens_td = 0;



$datey_from = $current_year . '-01-01';
$datey_to = $current_year . '-12-31';

$datem_from = date('Y-m', $last_time) . '-01';
$datem_to = date('Y-m', $last_time) . '-31';

$yesterday = date('Y-m-d', $last_time);



$where2 = " and (WI.ins_in_net!='1' or WI.ins_in_net is null)
            and (WI.ins_out_net!='1' or WI.ins_out_net is null)
            and (WI.ins_unknown!='1' or WI.ins_unknown is null)
            and (WI.ins_auto_wc!='1' or WI.ins_auto_wc is null)
            and (WI.ins_hmo!='1' or WI.ins_hmo is null)
            and (WI.ins_referenced!='1' or WI.ins_referenced is null) ";

if ($lab_id > 0) {
    $where2 .= " and LB.lab_ID ='{$lab_id}' ";
}

$query = "
    select y.pinscode,count_ytd,count_mtd,count_td from
    (select pinscode,pinsname, COUNT(DISTINCT patient_id)  as count_ytd
     from
    (select bb.pinscode, bb.pinsname, bb.patient_id
        from ci_batch_billings bb
        JOIN `ci_labs` as LB on bb.dname =  LB.lab_name
         JOIN `ci_insurancemix` WI ON WI.lab_ID = LB.lab_ID and WI.ins_code = bb.pinscode
        where  bb.servdate >= '{$datey_from}' and bb.servdate <= '{$datey_to}' {$where2}  ) as rs group by rs.pinscode) as y

    left join
    (select pinscode,pinsname, COUNT(DISTINCT patient_id)  as count_mtd
     from
    (select bb.pinscode, bb.pinsname, bb.patient_id
        from ci_batch_billings bb
        JOIN `ci_labs` as LB on bb.dname =  LB.lab_name
         JOIN `ci_insurancemix` WI ON WI.lab_ID = LB.lab_ID and WI.ins_code = bb.pinscode
        where  bb.servdate >= '{$datem_from}' and bb.servdate <= '{$datem_to}' {$where2}  ) as rs group by rs.pinscode) as m
    ON m.pinscode = y.pinscode

    left join
    (select pinscode,pinsname, COUNT(DISTINCT patient_id)  as count_td
     from
    (select bb.pinscode, bb.pinsname, bb.patient_id
        from ci_batch_billings bb
        JOIN `ci_labs` as LB on bb.dname =  LB.lab_name
         JOIN `ci_insurancemix` WI ON WI.lab_ID = LB.lab_ID and WI.ins_code = bb.pinscode
        where  bb.servdate = '{$yesterday}'  {$where2}  ) as rs group by rs.pinscode) as d
    ON d.pinscode = y.pinscode";

if (isset($_GET['debug1'])) {
    var_dump($query);
    exit;
}

$rows = $wpdb->get_results($query);







ob_start();
$pieData = array();
$googledata = array(array('Name', 'Value'));

?>

<div class="wrap_db">
    <table class="dashboard_tbl">
        <tr>
            <th></th>
            <th><span><?php echo date('M d, Y', $last_time); ?></span></th>
            <th><span>MTD</span></th>
            <th><span>YTD</span></th>
        </tr>

        <?php foreach ($rows as $row):
            $row->count_ytd = intval($row->count_ytd);
            $total_specimens_ytd += $row->count_ytd;
            $total_specimens_mtd += intval($row->count_mtd);
            $total_specimens_td += intval($row->count_td);

            $googledata[] = array($row->pinscode, $row->count_ytd);
        ?>
            <tr>
                <td><?php echo $row->pinscode ?></td>
                <td><?php echo intval($row->count_td); ?></td>
                <td><?php echo intval($row->count_mtd); ?></td>
                <td><?php echo $row->count_ytd ?></td>
            </tr>
        <?php endforeach; ?>

        <?php

        foreach ($ins_mix as $k => $txt) {
            $where3 = " and WI.{$k}='1' ";
            if ($lab_id > 0) {
                $where3 .= " and LB.lab_ID ='{$lab_id}' ";
            }
            $query = "
                select count_ytd,count_mtd,count_td from
                (select COUNT(DISTINCT bb.patient_id) as count_ytd
                    from ci_batch_billings bb
                    JOIN `ci_labs` as LB on bb.dname =  LB.lab_name
                     JOIN `ci_insurancemix` WI ON WI.lab_ID = LB.lab_ID and WI.ins_code = bb.pinscode
                    where  bb.servdate >= '{$datey_from}' and bb.servdate <= '{$datey_to}' {$where3}  )  as y

                LEFT JOIN
                (select COUNT(DISTINCT bb.patient_id) as count_mtd
                    from ci_batch_billings bb
                    JOIN `ci_labs` as LB on bb.dname =  LB.lab_name
                     JOIN `ci_insurancemix` WI ON WI.lab_ID = LB.lab_ID and WI.ins_code = bb.pinscode
                    where  bb.servdate >= '{$datem_from}' and bb.servdate <= '{$datem_to}' {$where3}  )  as m
                on 1
                LEFT JOIN
                (select COUNT(DISTINCT bb.patient_id) as count_td
                    from ci_batch_billings bb
                    JOIN `ci_labs` as LB on bb.dname =  LB.lab_name
                     JOIN `ci_insurancemix` WI ON WI.lab_ID = LB.lab_ID and WI.ins_code = bb.pinscode
                    where  bb.servdate = '{$yesterday}'  {$where3}  ) as d
                on 1
                ";

            $row = $wpdb->get_row($query);

            $row->count_ytd = intval($row->count_ytd);

            //if($row->count_ytd==0)continue;

            $total_specimens_ytd += intval($row->count_ytd);
            $total_specimens_mtd += intval($row->count_mtd);
            $total_specimens_td += intval($row->count_td);
            if ($row->count_ytd > 0) {
                $googledata[] = array($txt, $row->count_ytd);
            }

        ?>
            <tr>
                <td><?php echo $txt ?></td>
                <td><?php echo intval($row->count_td); ?></td>
                <td><?php echo intval($row->count_mtd); ?></td>
                <td><?php echo intval($row->count_ytd) ?></td>
            </tr>
        <?php
        }
        ?>
        <tr class="totalrow">
            <td></td>
            <td><?php echo $total_specimens_td ?></td>
            <td><?php echo $total_specimens_mtd ?></td>
            <td><?php echo $total_specimens_ytd ?></td>
        </tr>
    </table>


</div>
<?php
$dashboard_specimens = ob_get_clean();
ob_start();
?>

<script type="text/javascript">
    google.charts.load("visualization", "1", {
        packages: ["corechart"]
    });
    google.charts.setOnLoadCallback(drawChart);

    function drawChart() {

        var data = google.visualization.arrayToDataTable(<?php echo json_encode($googledata) ?>);

        // Set chart options
        var options = {
            legend: 'none',
            pieSliceText: 'label'


        };

        // Instantiate and draw our chart, passing in some options.
        var chart = new google.visualization.PieChart(document.getElementById('chart_div'));
        chart.draw(data, options);
    }
</script>
<div id="chart_div" style="width:600px; height:600px"></div>


<?php
$dashboard_specimens_chart = ob_get_clean();
