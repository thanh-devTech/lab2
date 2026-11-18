<?php
global $wpdb;

$markers_options = array();

$type_options = array('uti' => 'UTI', 'wound' => 'Wound', 'gi' => 'GI');

$testrs = $wpdb->get_results("select ID, resistance_marker from ci_abx_resistance_markers
     order by resistance_marker asc ");

foreach ($testrs as $t) {
    $markers_options[$t->ID . ' '] = $t->resistance_marker;
}


$tableconfig = array(
    'name' => 'abx_assay',
    'table' => 'ci_abx_assay',
    'stickyfc' => false,
    'save_all' => true,
    'new_row' => true,
    'column_border' => true,
    'width' => 'auto',
    'columns' => array(
        'assay' => array(
            'type' => 'text',
            'label' => 'Assay',
            'width' => 200
        ),
        'marker_IDs' => array(
            'type' => 'select2m',
            'label' => 'Markers',
            'split' => ',',
            'options' => $markers_options,
        ),
        'type' => array(
            'type' => 'select', 'label' => 'Panel',
            'options' => $type_options,
            'width' => 100,
        ),
    ),

    'actions' => array(
        // 'export' => 'Export',
        //'import_frame' => 'Import',
    )
);



$table  = new CI_Table($tableconfig);
$table->setUrl(Tr_Base_Class_V6::link(array('act'=>''),false));
?>

<div class="wrap">
    <h2>Assay </h2>
    <?php $table->show(true) ?>
</div>