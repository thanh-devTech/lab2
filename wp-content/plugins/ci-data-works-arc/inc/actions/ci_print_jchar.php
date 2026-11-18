<html>
<body>
<div id="div_print">
    <?php
    global $wpdb,$current_role;
    $user_id = get_current_user_id();

    $where = $where_qc = " and (re.patient_name='LQC' or re.patient_name='HQC' or re.patient_name='MQC')";

    $orderby = ' order by re.batch_id desc,re.sublab asc';

    if(CI_Base_Lab::has_user_labs())
    {
        $lab_str = CI_Base_Lab::get_user_lab_ids_str();
        $where.= " and re.lab_id in ( {$lab_str} )";
    }else{
        $lab_id = CI_Base_Lab::get_current_lab_id();
        $where.= " and (re.lab_id = {$lab_id} or re.lab_id = 0 ) ";
    }

    if($user_id>0 && !tr_current_role_in( array('administrator','AdminCIDW','administrator_p','scientist','labmanager','lab_tech')) )
    {
        $where.= " and re.author_id = {$user_id} ";
    }

    $month = isset($_REQUEST['month'])? $_REQUEST['month'] :'';
    $test = isset($_REQUEST['testc'])? $_REQUEST['testc'] :'';
    $instrument = isset($_REQUEST['instrument'])? $_REQUEST['instrument'] :'';
    $lot = isset($_REQUEST['lot'])? $_REQUEST['lot'] :'';
    if(!empty($month))
    {
        $date_from = $month.'-1';
        $date_to = $month.'-31';
    }else{
        $date_from = date('Y-m-1');
        $date_to = date('Y-m-31');
    }

    $where.= " and re.collection_date >= '{$date_from}' and re.collection_date <= '{$date_to}' ";
    $where.= " and rs.drug='{$test}' ";
    if(!empty($instrument))
    {
        $where.= " and re.instrument = '{$instrument}' ";
    }

    if(!empty($lot))
    {
        $where.= " and re.patient_name like '% {$lot}' ";
    }

    $sql = "select re.*, rs.conc,rs.id as rs_id,re.collection_date as collection_date
    from ci_reports re
    join ci_results rs ON re.id = rs.report_id
    where 1 {$where}  {$orderby}";

    if(!empty($test))
    {
        $results = $wpdb->get_results($sql);

    }
    function formatDate(&$aVal) {
        $aVal = date('Y-m-d H:i',$aVal);
    }

    $sql = "select distinct(te_name) as name from ci_test_panels  order by te_name";
    $data=null;

    $chart_data = array();
    if(count($results)>0)
    {

        tr_pdd_get_chart_data($results,$test,$instrument,$type,$batches,$chart_data);
    }
    foreach($chart_data['charts'] as $i => $data) {
        $values = array();
        $dates = array();
        $other = array();
        foreach ($data['value'] as $date => $value) {
            if(is_numeric($date))
            {
                $date = date('Y-m-d', $date);
            }
            $dates[] = $date;
            $values[] = floatval(number_format($value,3,'.',''));
        }
        foreach ($data['other'] as $ii=>$row) {
            foreach($row as $date => $value)
            {
                if(is_numeric($date))
                {
                    $date = date('Y-m-d', $date);
                }
                $other[$ii][] = floatval(number_format($value,3,'.',''));
            }

        }
        $data['dates'] = $dates;
        $data['values'] = $values;
        $data['other'] = $other;

        $graph  = new Graph(800, 350);
        $graph->title->Set($data['name']);
        $graph->SetScale('textlin');

        $graph->SetMarginColor('green@0.95');

        $graph->SetMargin(40,30,40,120);

        $graph->xaxis->SetTickSide(SIDE_BOTTOM);
        $graph->yaxis->SetTickSide(SIDE_LEFT);


        $l1plot=new LinePlot($data['values']);
        $graph->Add($l1plot);


        $graph->xaxis->SetTickLabels($data['dates']);
        $graph->xaxis->SetTextLabelInterval(1);


        $graph->xaxis->SetLabelAngle(70);



        $img = $graph->Stroke(_IMG_HANDLER);

        ob_start();
        imagepng($img);
        $imageData = ob_get_contents();
        ob_end_clean();
        ?>
        <img src="data:image/png;base64,<?php echo(base64_encode($imageData)); ?>" />
        <?php
    }
    ?>

</div>
<button onclick="printDiv('div_print');">Print</button>
<script>
    function printDiv(divName) {
        var printContents = document.getElementById(divName).innerHTML;
        var originalContents = document.body.innerHTML;

        document.body.innerHTML = printContents;

        window.print();

        document.body.innerHTML = originalContents;
    }
</script>
</body>
</html>