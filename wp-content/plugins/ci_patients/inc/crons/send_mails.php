<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb;
global $sending_email_in_cron;
$sending_email_in_cron = true;
$limit = 10;
$list = $wpdb->get_results("select * from ci_emails where istatus = 0 order by ID asc limit {$limit}");

foreach ($list as $item) {
	$run_in_seconds = time();
	$headers = json_decode($item->headers, true);
	if (is_array($headers)) {
		$item->headers = $headers;
	}

	$attachments = json_decode($item->attachments, true);
	if (is_array($attachments)) {
		$item->attachments = $attachments;
	}

	$rs = wp_mail($item->to, $item->subject, $item->message, $item->headers, $item->attachments);
	if ($rs) {
		$istatus = 1;
	} else {
		$istatus = -1;
	}
	$run_in_seconds = time() - $run_in_seconds;
	$wpdb->update('ci_emails', array(
		'istatus' => $istatus,
		'run_in_seconds' => $run_in_seconds,
		'send_time' => date_i18n('Y-m-d H:i:s')
	), array('ID' => $item->ID));

	sleep(5);
}







if (count($list) == $limit) {
	wp_clear_scheduled_hook('cron_send_mails');
	wp_schedule_single_event(time() + 30, 'cron_send_mails');
}
