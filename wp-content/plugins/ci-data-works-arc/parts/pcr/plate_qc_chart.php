<?php
global $wpdb;

$id     = $_REQUEST['rfid'];
$assen  = $_REQUEST['assen'];
$data   = trpdd_pcr_build_WH_plate_pos($id);
$chart_data = array();

foreach ($data['rows'] as $row) {
    if ($row['samplename'] != $assen || empty($row['AS']) ) {
        continue;
    }
    
    $row['AS'] = str_replace('%', '', $row['AS']);
    $row['AS'] = floatval($row['AS']);

    $chart_data[$row['test_name']] = $row['AS'];
}
?>
<div id="chartContainer" style="height: 300px; width: 100%;"></div>
<script src="https://cdn.canvasjs.com/canvasjs.min.js"></script>

<script type="text/javascript">
    var $chart_data = <?php echo json_encode($chart_data)?>;
    function setupchart()
    {
        var $data = [];
        for(i in $chart_data)
        {
            $data.push({y:$chart_data[i], label: i});
        }
        console.log($data);
        var chart = new CanvasJS.Chart("chartContainer", {
            animationEnabled: true,
            title: {
                text: "<?php echo $assen?>"
            },
            borderWidth:5,
            data: [{
                type: "doughnut",
                startAngle: 240,
                yValueFormatString: "##0.00\"%\"",
                indexLabel: "{label} {y}",
                dataPoints: $data
            }]
        });
        chart.render();
    }
    setupchart();
</script>
<style>
    .canvasjs-chart-credit{
        display:none !important;
    }
</style>