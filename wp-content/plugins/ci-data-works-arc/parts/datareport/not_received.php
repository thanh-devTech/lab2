<?php

return include trpdd_get_file_path('reports', 'not_received');

global $ci_all_btypes;



$headers = array(
	'doc' 		=> 'Date of Collection',
	'assen' 	=> 'Accession',
	'received' 	=> 'Received?'
);

wp_enqueue_script('jquery-ui-datepicker');

wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');
wp_enqueue_script('jquery-validate', TRCPDD_URL . 'admin/js/jquery.validate.min.js', array('jquery'), true);
wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);

$table 	= new CI_Report_Table(array('title' => 'Not Received', 'export' => true));
$table->addTab('btypes');
$table->setHeader($headers);

$doc 		= $table->addFilter('doc', 'date', 'DOC');
$nor		= $table->addFilter('nor', 'checkbox', 'Only Not Received', 1);
$btype 		= $table->get_tab();
$batch_tbl 	= tr_pdd_get_table_by_btype($btype);

$sql = "select
doc,
assen,
IF(rb.mid is null , 'no' , 'yes') as received
from {$batch_tbl} ba 
Left JOIN ci_receiving_batches rb ON rb.mid = ba.mid and rb.btype='{$btype}'
where doc >= '{$doc}' and doc <= '{$doc} 23:59:59' and ba.batch_id = 0 ";
if ($nor) {
	$sql .= " and rb.mid is null ";
}
$sql .= " order by doc asc";
$rows = $wpdb->get_results($sql, ARRAY_A);

$table->setData($rows);
$table->display();

if (isset($_GET['debug'])) {
	echo 'total: ' . count($rows);
}
