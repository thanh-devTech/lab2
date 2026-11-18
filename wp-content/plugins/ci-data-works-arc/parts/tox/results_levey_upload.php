<?php
include_once(TRCPDD_PATH.'pages/formdata.php');


$chart = new CI_Matlab_Chart();

if(isset($_GET['debug'])){
    $data_file = TRCPDD_PATH.'inc/data.xlsx';
    $chart_data = $chart->parse_excel($data_file);
    include(TRCPDD_PATH.'pages/chartjs.php');
    return;
}


if(isset($_POST['submit'])){
    $data_file = $_FILES['data_file']['tmp_name'];
    if(!empty($data_file))
    {
        $chart_data = $chart->parse_excel($data_file);
        include(TRCPDD_PATH.'pages/chartjs.php');
        return;
    }
    else{
        Tr_Session_Class::add_error('Please select a excel file');
    }
}



?>
<div class="wrap">
    <h2>Levey–Jennings QC</h2>
    <?php Tr_Session_Class::show()?>


    <div class="form-wrap center">
        <form method="post" enctype="multipart/form-data">

            <h3>Upload Data File</h3>
            <input type="file" name="data_file"/>
            <input type="submit" name="submit" class="button-primary" value="Upload">

        </form>
    </div>

</div>