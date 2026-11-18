<?php
global $current_role;


wp_enqueue_script('data-admin',TRCPDD_URL. 'js/admin.min.js',array(),TRCPDD_VS );

$forms = array(
    'ci_results_list' => 'Results',
    'form_requisition' =>'New Tox Orders',
    'ci_results_incomplete' => 'Incomplete Orders',
);


$tab = isset($_REQUEST['tab'])? $_REQUEST['tab']: key($forms);
$formf = 0;
$_REQUEST['not_show_tab'] = true;
echo '<h2 class="nav-tab-wrapper">';
foreach($forms as $k =>$txt)
{
    $class = $tab == $k ? 'nav-tab-active':'';
    $link = Tr_Base_Class_V6::link(array('tab'=>$k,'act'=>'','id'=>''),false);
    echo '<a class="nav-tab '.$class.'" href="'.$link.'">'.$txt.'</a>';
}
echo '</h2>';

echo '<div class="wrap_tabs">';
include(TRCPDD_PATH . 'pages/admin_'.$tab.'.php');
echo '</div>';

?>