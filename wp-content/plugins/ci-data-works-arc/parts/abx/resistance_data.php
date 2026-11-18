<?php
global $wpdb;
$cli_ID = isset($_REQUEST['cid'])? $_REQUEST['cid'] : 0;

$bacteria_options   = array();
$antibiotic_options = array();
$clinic_options     = array();

$testnames = $wpdb->get_col("select te_name from ci_test_panels te
JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
LEFT JOIN ci_test_groups gp ON gp.tg_ID = g.tg_parent
where g.tg_show_req = 1 or gp.tg_show_req = 1");
$testnames = array_map('strtolower',$testnames);


$clinics_rs = $wpdb->get_results("select cID, cli_clinic, cli_state from ci_clinic order by cli_clinic asc");
foreach($clinics_rs as $r)
{
    $clinic_options[$r->cID.' '] = $r->cli_clinic.' - '.$r->cli_state;
}

$bacteria_rs = $wpdb->get_results("select * from ci_abx_bacterias order by bacteria_name asc");
foreach($bacteria_rs as $r)
{
    if(!in_array(strtolower($r->bacteria_name), $testnames))
    {
        continue;
    }
    $bacteria_options[$r->ID] = $r->bacteria_name;
}


$antibiotic_rs = $wpdb->get_results("select * from ci_abx_antibiotics order by abx asc");
foreach($antibiotic_rs as $r)
{
    $antibiotic_options[$r->ID] = $r->abx;
}

$tableconfig = array(
    'name' => 'abx_local_resistance',
    'table' => 'ci_abx_clinic_local_resistance',
    'stickyfc' => false,
    'save_all' => true,
    'new_row' => true,
    'column_border' => true,
    'width' =>'auto',
    'columns' => array(
        'cli_ID' => array('type'=>'select2','label'=>'Clinic','options'=>$clinic_options),
        'bacteria_ID' => array('type'=>'select2','label'=>'Bacteria','options'=>$bacteria_options),
        'antibiotic_ID' => array('type'=>'select2','label'=>'Antibiotic','options'=>$antibiotic_options),
        'score' => array('type'=>'text','label'=>'% Local Susceptibility'),
    ),
    
   
    'actions' => array(
       // 'export' => 'Export',
        //'import_frame' => 'Import',
    )
);


if($cli_ID>0)
{
    $tableconfig['hide_fields'] = array('cli_ID'=>$cli_ID);
    unset($tableconfig['columns']['cli_ID']);
    $tableconfig['hasfilter'] = true;
    
    
    $sql = "select * 
    from {$tableconfig['table']} 
    where cli_ID = {$cli_ID}
    order by ID asc";
    $tableconfig['data']  = $wpdb->get_results($sql);
}



$table  = new CI_Table($tableconfig);
$table->setUrl(Tr_Base_Class_V6::link(array('act'=>''),false));
?>

<div class="wrap">
    <h2>Local Resistance<br>
    </h2>
    <?php $table->show()?>
</div>