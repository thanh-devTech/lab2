<?php
$basecode = tr_get_option('ci_pdf_data','barcode_key');
$tableconfig = array(
    'table' => '',
    //'limit' => 10,
    //'width' => '3000',
    //'stickyfc' => true,//sticky first column
    'save_all' => true,
    'new_row' => false,
    'columns' => array(
        'qc' => array('type' => 'desc', 'label' => 'QC'),
        'test' => array('type' => 'text', 'label' => 'QC Name')
    ),
    'data' => array(),
   // 'actions' => array('export' => 'Export')
);

$optionskey = 'tr_pcr_qc_st';

if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'save_table') {
    $return = array('status' => 'ok');

    $data = is_array($_REQUEST['data']) ? $_REQUEST['data'] : json_decode(stripslashes($_REQUEST['data']), true);

    $options = array();
    foreach($data as $k => $vl)
    {
        list($assen) = explode('-',$k);
        $options[$assen] = $vl;
    }
    update_option($optionskey, $options);

    wp_send_json($return);
}
$options = get_option($optionskey,array());

for($i=1001;$i<=1033;$i++)
{
    $assen = $basecode.'0000'.$i;
    $test = isset($options[$assen])? $options[$assen] : '';
    $row = array(
        'ID' => $assen,
        'qc' => $assen,
        'test' => $test
    );
    $tableconfig['data'][] = $row;
}

wp_enqueue_style( 'admin_table', TRCPDD_URL. 'css/admin_table.min.css',array(), TRCPDD_VS );
?>
<div class="wrap">
    <h2></h2>

    <div id="table_show"></div>
</div>
<script src="<?php echo TRCPDD_URL . 'js/admin_table.js?v=' . time() ?>"></script>
<script>
    var $tableconfig = <?php echo json_encode($tableconfig) ?>;
    new Tr_Table('table_show', $tableconfig);
</script>