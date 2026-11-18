<?php
global $wpdb;

//$btype,$mid,$batch,$pa

$add_multi_btypes = isset($_POST['add_multi_btype']) ? $_POST['add_multi_btype'] : array();
$rel_ids = array();
foreach ($add_multi_btypes as $bt) {
	if ($bt == $btype) continue;

	$table_batch = tr_pdd_get_table_by_btype($bt);
	unset($batch['mid']);
	unset($batch['is_dna']);
	unset($batch['original_req']);
	unset($batch['result_pdf']);
	unset($batch['panel_tests_requested']);
	unset($batch['dagnostic_codes1']);
	unset($batch['current_medication']);
	unset($batch['is_saved_form']);

	if (!isset($batch['lab_id']) || empty($batch['lab_id'])) {
		$batch['lab_id'] = CI_Base_Lab::get_current_lab_id();
	}

	$batch['assen'] = tr_get_new_assen($bt, false, '', $batch['lab_id']);
	$rs = $wpdb->insert($table_batch, $batch);
	$submid = $wpdb->insert_id;
	if ($submid > 0) {
		$rel_ids[$bt] = $submid;
		trpdd_update_batch_meta($submid, $bt, '_rel_id', $btype . '-' . $mid);
	}
}

trpdd_update_batch_meta($mid, $btype, '_rel_ids', $rel_ids);
