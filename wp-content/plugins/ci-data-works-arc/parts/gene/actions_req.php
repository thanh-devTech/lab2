<?php
$trajax = isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : '';

if ($act == 'single_change_hold') {
	$return = array('status' => 'ok');
	$mid        = $_REQUEST['mid'];
	$reason     = $_REQUEST['reason'];
	$reason_title     = isset($_REQUEST['reason_title']) ? $_REQUEST['reason_title'] : '';

	$batch = $wpdb->get_row("select * from {$batch_tbl} where mid = {$mid}");
	if ($batch->batch_status != 'hold') {
		$wpdb->update($batch_tbl, array('batch_status' => 'hold'), array('mid' => $mid));
		$wpdb->replace($batch_tbl . '_meta', [
			'mid' => $mid,
			'meta_key' => 'hold_batch_status',
			'meta_value' => $batch->batch_status
		]);

		trpdd_log_user(get_current_user_id(), 'hold_rs', array(
			'reason' => $reason,
			'reason_title' => $reason_title
		), $mid, $btype, '', '', $batch->assen);
	}
	wp_send_json($return);
} else if ($act == 'single_remove_hold') {
	$return     = array('status' => 'ok');
	$mid        = $_REQUEST['mid'];
	$reason     = '';

	$batch = $wpdb->get_row("select * from {$batch_tbl} where mid = {$mid}");
	if ($batch->batch_status == 'hold') {
		$hold_status = $wpdb->get_var("select meta_value from {$batch_tbl}_meta where mid={$mid} and meta_key = 'hold_batch_status'") ?: '';
		$wpdb->update($batch_tbl, array('batch_status' => $hold_status), array('mid' => $mid));
		$wpdb->delete($batch_tbl . '_meta', ['mid' => $mid, 'meta_key' => 'hold_batch_status']);

		trpdd_log_user(get_current_user_id(), 'removehold_rs', $reason, $mid, $btype, '', '', $batch->assen);
	}
	wp_send_json($return);
} else if ($act == 'single_cancel_order') {
	$return     = array('status' => 'ok');
	$mid        = $_REQUEST['mid'];
	$isundo     = isset($_REQUEST['undo']) && boolval($_REQUEST['undo']);
	$reason     = '';

	$batch = $wpdb->get_row("select * from {$batch_tbl} where mid = {$mid}");
	if (($batch->batch_status == 'archived' && $isundo) || ($batch->batch_status != 'archived' && !$isundo)) {
		$update = array('batch_status' => $isundo ? '' : 'archived', 'archived_date' => date_i18n('Y-m-d H:i:s'));
		$wpdb->update($batch_tbl, $update, array('mid' => $mid));

		trpdd_log_user(get_current_user_id(), $isundo ? 'unarchive' : 'archived', $reason, $mid, $btype, '', '', $batch->assen);
		$return['newstatus'] = $update['batch_status'];
	} else {
		$return['newstatus'] = 'nochange';
	}
	$return['isundo'] = $isundo;
	wp_send_json($return);
} else if ($act == 'delete' && $_REQUEST['id'] > 0 && $can_delete) {
	$return = array('status' => 'ok', 'message' => '');
	$bat = trpdd_delete_batch($_REQUEST['id'], $btype);

	if ($trajax) {
		if ($bat) {
			$return['message'] = 'Deleted Req record: ' . $bat->assen . ' successfully.';
		} else {
			$return['status'] = 'error';
			$return['message'] = 'Delete failed.';
		}
		wp_send_json($return);
	} else {
		if ($bat) {
			Tr_Session_Class::add('Deleted Req record: ' . $bat->assen . ' successfully.', 'success');
		} else {
			Tr_Session_Class::add('Delete Req record failed.', 'error');
		}
	}
}
