<?php
global $wpdb;

$from_btype = $_REQUEST['from'];
$to_btype   = $_REQUEST['to'];
$id   		= $_REQUEST['id'];

$from_batch_tbl = tr_pdd_get_table_by_btype($from_btype);
$to_batch_tbl = tr_pdd_get_table_by_btype($to_btype);

$batch = $wpdb->get_row("select * from {$from_batch_tbl} where mid = {$id} ", ARRAY_A);

$dupfields = array(
	'cli_ID',
	'doc_ID',
	'lab_id',
	'col_ID',
	'author_ID',
	'doc',
	'lab',
	'patient_id',
	'patient_name',
	'dob',
	'requesting_practice',
	'requesting_physician',
	'physician_phone',
	'spec_received',
	'insurance',
	'insurance1',
	'insurance2',
	'patient_signature',
	'provider_signature',
);
$lab = array();
$lab['lab_ID'] = $batch['lab_id'];
$newdata = array();
foreach ($dupfields as $fk) {
	$newdata[$fk] = isset($batch[$fk]) ? $batch[$fk] : '';
}
$newdata['assen'] = tr_get_new_assen($to_btype, false, '', $lab);
$newdata['batch_type'] = 'D';
$newdata['is_dna'] = '18';
$newdata['batch_created'] = date_i18n('Y-m-d H:i:s');
$newdata['req_form_time'] = date_i18n('Y-m-d H:i:s');

$wpdb->insert($to_batch_tbl, $newdata);
if (!empty($wpdb->last_error)) {
	echo $wpdb->last_error;
	exit;
}
$newid = $wpdb->insert_id;
$redirecturl = Tr_Base_Class_V6::link(array('page' => $to_btype . '_req_form', 'act' => 'edit', 'id' => $newid, 'from' => '', 'to' => ''), false);

Tr_Session_Class::add('Req duplicated successfully');
Tr_Session_Class::add('New Accession: ' . $newdata['assen']);
wp_redirect($redirecturl);
