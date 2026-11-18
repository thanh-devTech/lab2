<?php
include_once(TRCPDD_PATH . 'pages/formdata.php');

$sub_tabs = array(
    'thunderbolt' => 'Thunderbolt',
    'qc_setup' => 'QC Setup'
);
$subtab = !empty($_REQUEST['subtab'])? $_REQUEST['subtab']: key($sub_tabs);
if(!isset($_REQUEST['trajax'])){
    echo '<h2 class="nav-tab-wrapper">';
    foreach($sub_tabs as $k => $txt){
        $class = $subtab == $k ? 'nav-tab-active':'';
        $link = Tr_Base_Class_V4::link(array('subtab'=>$k,'act'=>'','id'=>'','mid'=>'','paged'=>'','s'=>''),false);
        echo '<a class="nav-tab '.$class.'" href="'.$link.'">'.$txt.'</a>';
    }
    echo '</h2>';
}

if($subtab=='thunderbolt'){

    include( trpdd_get_file_path('req','results_qc_thunderbolt') );

}else if($subtab=='centaur'){
    include( trpdd_get_file_path('req','results_qc_centaur') );
}
else{
    include( trpdd_get_file_path('req','results_qc_setup') );
}

