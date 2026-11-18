<?php

$schedule_report = new CI_Report_Schedule('', '');
$dispatch_schedule = new CI_Report_Dispatch_Schedule();

$tableconfig = array(
	'name' => 'schedule_list',
	'table' => '',
	'stickyfc' => false,
	'save_all' => false,
	'new_row' => false,
	'column_border' => true,
	'removerow' => true,
	'disable_save' => true,
	'width' => 'auto',
	'columns' => array(
		'type' => array('type' => 'desc', 'label' => 'Type'),
		'frequency' => array('type' => 'desc', 'label' => 'Frequency'),
		'date' => array('type' => 'desc', 'label' => 'Start Date'),
		'time' => array('type' => 'desc', 'label' => 'Time'),
		'pause' => array('type' => 'desc', 'label' => 'Pause'),
		'email' => array('type' => 'desc', 'label' => 'Email'),
		'user' => array('type' => 'desc', 'label' => 'User'),
		'formats' => array('type' => 'desc', 'label' => 'File Format'),
		'created' => array('type' => 'desc', 'label' => 'Created'),
	),

);
$tableconfig['data'] = array();
$schedules = get_option('ci_user_schedules', []);
$dispatch_schedules = get_option('ci_user_dispatch_schedules', []);

if (!function_exists('ci_schedule_row_id')) {
	function ci_schedule_row_id($group, $user_id, $schedule_id)
	{
		return $group . '::' . $user_id . '::' . $schedule_id;
	}
}

if (!function_exists('ci_schedule_parse_row_id')) {
	function ci_schedule_parse_row_id($id)
	{
		$parts = explode('::', $id, 3);
		if (count($parts) === 3) {
			return array(
				'group' => $parts[0],
				'user_id' => intval($parts[1]),
				'schedule_id' => $parts[2],
			);
		}

		$legacy = explode('_', $id, 2);
		return array(
			'group' => 'report',
			'user_id' => intval($legacy[0] ?? 0),
			'schedule_id' => $legacy[1] ?? '',
		);
	}
}

$act = !empty($_POST['trajax']) ? $_POST['trajax'] : $act;
if ($act == 'remove_table_row') {
	$return = array('status' => 'ok', 'ID' => 0);
	$parsed = ci_schedule_parse_row_id($_POST['ID']);
	$user_id = $parsed['user_id'];
	$schedule_id = $parsed['schedule_id'];
	$group = $parsed['group'];
	$return['$user_id'] = $user_id;
	$return['$schedule_id'] = $schedule_id;
	if (
		$group === 'dispatch' &&
		!empty($dispatch_schedules[$user_id]) &&
		!empty($dispatch_schedules[$user_id][$schedule_id])
	) {
		unset($dispatch_schedules[$user_id][$schedule_id]);
		if (empty($dispatch_schedules[$user_id])) {
			unset($dispatch_schedules[$user_id]);
		}
		update_option('ci_user_dispatch_schedules', $dispatch_schedules);
			$return['ID'] = $_POST['ID'];

			wp_clear_scheduled_hook(
				'cron_all_api',
				['ci_cron_report_dispatch', '', [$user_id, $schedule_id]]
			);
		} elseif (
		!empty($schedules[$user_id]) &&
		!empty($schedules[$user_id][$schedule_id])
	) {
		unset($schedules[$user_id][$schedule_id]);
		if (empty($schedules[$user_id])) {
			unset($schedules[$user_id]);
		}
		update_option('ci_user_schedules', $schedules);
		$return['ID'] = $_POST['ID'];

		wp_clear_scheduled_hook(
			'cron_all_api',
			['ci_cron_reports', '', [$user_id, $schedule_id]]
		);
	}

	wp_send_json($return);
}
foreach ($schedules as $user_id => $user_schedules) {
	if (empty($user_schedules)) {
		unset($schedules[$user_id]);
	}
}
foreach ($dispatch_schedules as $user_id => $user_schedules) {
	if (empty($user_schedules)) {
		unset($dispatch_schedules[$user_id]);
	}
}

if (empty($schedules) && empty($dispatch_schedules)) {
	$crons = _get_cron_array();

	foreach ($crons as $timestamp => $cron) {
		if (isset($cron['cron_all_api'])) {
			foreach ($cron['cron_all_api'] as $tt => $cron1) {
				if (!in_array($cron1['args'][0], array('ci_cron_reports', 'ci_cron_report_dispatch'))) continue;
				$rs = wp_unschedule_event($timestamp, 'cron_all_api', $cron1['args'], true);
			}
		}
	}
}


foreach ($schedules as $user_id => $user_schedules) {
	foreach ($user_schedules as $schedule_id => $sc) {
		$user = new WP_User($user_id);
		$row = array();
		$row['ID'] = ci_schedule_row_id('report', $user_id, $schedule_id);
		$row['type'] = $schedule_report->get_title_by_type($sc['type_report'], $sc['tab'] ?? '', $sc['view_by'] ?? '');
		//$row['type'] = json_encode($sc);
		$row['frequency'] = $sc['frequency'];
		$row['date'] = !empty($sc['start_date']) ? date('Y-m-d', strtotime($sc['start_date'])) : 'N/A';
		$row['time'] = !empty($sc['time']) ? esc_html($sc['time']) : 'N/A';
		$row['email'] = !empty($sc['email']) ? esc_html($sc['email']) : 'N/A';
		$row['user'] = $user->display_name ?? 'N/A';
		$row['pause'] = (!empty($sc['is_pause']) && $sc['is_pause'] == 1) ? 'Yes' : 'No';
		$format_labels = array();
		if (!empty($sc['is_pdf'])) {
			$format_labels[] = 'PDF';
		}
		if (!empty($sc['is_csv'])) {
			$format_labels[] = 'CSV';
		}
		$row['formats'] = !empty($format_labels) ? implode(', ', $format_labels) : 'N/A';
		$row['created'] = !empty($sc['updated_at']) ? esc_html($sc['updated_at']) : 'N/A';
		$tableconfig['data'][] = $row;
	}
}
foreach ($dispatch_schedules as $user_id => $user_schedules) {
	foreach ($user_schedules as $schedule_id => $sc) {
		$user = new WP_User($user_id);
		$row = array();
		$row['ID'] = ci_schedule_row_id('dispatch', $user_id, $schedule_id);
		$row['type'] = !empty($sc['report_label']) ? $sc['report_label'] . ' Dispatch' : 'Report Dispatch';
		$row['frequency'] = $dispatch_schedule->get_frequency_label($sc['frequency'] ?? 'hourly');
		$row['date'] = 'Daily';
		$row['time'] = (!empty($sc['start_time']) && !empty($sc['end_time']))
			? date('h:i A', strtotime($sc['start_time'])) . ' - ' . date('h:i A', strtotime($sc['end_time']))
			: 'N/A';
		$row['email'] = !empty($sc['email']) ? esc_html($sc['email']) : 'N/A';
		$row['user'] = $user->display_name ?? 'N/A';
		$row['pause'] = (!empty($sc['is_pause']) && $sc['is_pause'] == 1) ? 'Yes' : 'No';
		$row['formats'] = 'CSV';
		$row['created'] = !empty($sc['updated_at']) ? esc_html($sc['updated_at']) : 'N/A';
		$tableconfig['data'][] = $row;
	}
}
$table = new CI_Table($tableconfig);


?>
<div class="wrap">
	<h2>Schedules<br>
	</h2>
	<?php $table->show();
	if (empty($tableconfig['data'])) {
		echo '<p>No schedules found.</p>';
	}
	?>
</div>
