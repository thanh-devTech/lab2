<div class="wrap">
    <?php
    global $wpdb,$current_role;

    $priorities = array('low' => 'Low','med'=>'Med', 'high' => 'High');
    $timings = array('24' => '24hrs','48'=>'48hrs','72'=>'72hrs','120'=>'5days','168'=>'7days');

    include_once(TRCPDD_PATH.'pages/formdata.php');
    $current_time = time()+(get_option( 'gmt_offset' ) * HOUR_IN_SECONDS);
    $today = date('Y-m-d',$current_time);

    $tabs = array(
        'priority' => 'Priority',
        'tecan' => 'Tecan',
        'cold_storage' => 'Cold Storage',
        //'processing'=> 'Processing Queue'
    );

    $tab = isset($_REQUEST['tab'])? $_REQUEST['tab']: key($tabs);
    if(!isset($tabs[$tab])){
        $tab = key($tabs);
    }
    ?>
    <h2 class="nav-tab-wrapper">
        <?php

        foreach($tabs as $k => $txt){
            $class = $tab == $k ? 'nav-tab-active':'';
            $link = Tr_Base_Class_V6::link(array('tab'=>$k,'act'=>'','id'=>'','date'=>'','paged'=>''),false);
            echo '<a class="nav-tab '.$class.'" href="'.$link.'">'.$txt.'</a>';
        }
        ?>
    </h2>

    <?php

    if($tab=='tecan')
    {
        include('admin_ci_tecan_upload.php');
    }
    else if($tab=='processing')
    {
        include('admin_ci_tecan_processing.php');
    }
    else if($tab=='cold_storage')
    {
        include('admin_ci_tecan_batches.php');
    }
    else {
        include('admin_ci_receiving_priority.php');
    }
    ?>
</div>
