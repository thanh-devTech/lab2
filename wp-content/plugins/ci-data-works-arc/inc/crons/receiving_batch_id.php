<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb;

$today = date_i18n('Y-m-d');
$hournow = date_i18n('H');
$datetime_now = date_i18n('Y-m-d H:i:s');

if (!defined('TRPDD_HOUR_CHANGE_BATCH')) {
	echo 'not defiled TRPDD_HOUR_CHANGE_BATCH';
	return;
}

if (TRPDD_HOUR_CHANGE_BATCH > 13 && $hournow < TRPDD_HOUR_CHANGE_BATCH) {
	echo 'not time yet(>13)';
	return;
	//only change for today
} else if (TRPDD_HOUR_CHANGE_BATCH < 7 && !isset($_GET['testcron'])) {
	if ($hournow > TRPDD_HOUR_CHANGE_BATCH + 1 || (TRPDD_HOUR_CHANGE_BATCH > 0 && $hournow < TRPDD_HOUR_CHANGE_BATCH)) {
		echo 'not time yet(<7)';
		return;
		//only in 1hr
	}
	$today = date('Y-m-d', strtotime($today) - 86400);
}


$log = $datetime_now;

$batch_idby_date = array();
foreach (tr_pdd_get_batch_types() as $btype) {
	$table = tr_pdd_get_table_by_btype($btype);

	$sql = "select ba.mid,
				ri.re_today
			from {$table} ba
			join ci_receiving_batches rb ON rb.mid = ba.mid
			join ci_receiving ri ON ri.re_ID = rb.re_ID
			where rb.btype='{$btype}'
			and ba.batch_id = 0 
			and ri.re_today <= '{$today}'
			order by ri.re_today desc ";
	$rs = $wpdb->get_results($sql);


	foreach ($rs as $r) {
		$batch_id = str_replace('-', '', $r->re_today);

		if (tr_get_option('ci_pdf_data', 'multi_batch_id')) {
			$batch_id .= '-1';
		}

		$update = array(
			'batch_id' => $batch_id,
		);
		$wpdb->update($table, $update, array('mid' => $r->mid));

		$log .= "\n";
		$log .= "mid:{$r->mid} {$btype} => {$batch_id} ";

		trpdd_log_user(-1, 'receiving_add_batch', $batch_id, $r->mid, $btype);



		if (!$wpdb->get_var("select count(*) from ci_batch_ids where batch_id = '{$batch_id}' ")) {
			$wpdb->query("insert IGNORE into ci_batch_ids set batch_id = '{$batch_id}' ");
		}
	}
}

file_put_contents(WP_CONTENT_DIR . '/log/log_cron_receiving_batchid.txt', $log);

//wp_schedule_single_event(time() + 3600 , 'cron_receiving_batch_id');