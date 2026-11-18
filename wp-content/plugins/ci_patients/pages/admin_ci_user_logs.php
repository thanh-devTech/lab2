<?php
global $wpdb, $blog_id;

if (!function_exists('trcppr_is_new_style_enabled') || !trcppr_is_new_style_enabled()) {
	wp_die(__('This page is only available when New Style is enabled.'));
}

if (!current_user_can('list_users')) {
	wp_die(__('Sorry, you are not allowed to access this page.'));
}

if (function_exists('trcppr_ensure_ci_log_users_table')) {
	trcppr_ensure_ci_log_users_table();
}

$actions = array(
	'' => 'All Actions',
	'view_req_form' => 'View Req Form',
	'save_result_pcr' => 'Save Result PCR',
	'save_approve_result_pcr' => 'Save/Approve Result PCR',
	'patient_login' => 'User Login',
	'patient_logout' => 'User Logout',
);
$_GET = array_merge(array(
	'page' => 'ci_user_logs',
	'user_search' => '',
	'uact' => '',
	'record_id' => '',
	'from' => '',
	'to' => '',
), $_GET);
$where = $wpdb->prepare(" and (l.lab_id is null or l.lab_id = %d)", (int) $blog_id);

if (!empty($_GET['user_search'])) {
	$user_search = '%' . $wpdb->esc_like(sanitize_text_field($_GET['user_search'])) . '%';
	$where .= $wpdb->prepare(" and (u.user_login like %s or u.user_email like %s or u.display_name like %s)", $user_search, $user_search, $user_search);
}

if (!empty($_GET['uact']) && isset($actions[sanitize_key($_GET['uact'])])) {
	$uact = sanitize_key($_GET['uact']);
	if ($uact == 'patient_login') {
		$where .= " and (l.user_action = 'patient_login' or (l.user_action = 'login' and l.data = 'patient_portal'))";
	} else if ($uact == 'patient_logout') {
		$where .= " and (l.user_action = 'patient_logout' or (l.user_action = 'logout' and l.data = 'patient_portal'))";
	} else if ($uact == 'view_req_form') {
		$where .= " and l.user_action in ('view_req_form','view_req','view_requisition_form')";
	} else if ($uact == 'save_result_pcr') {
		$where .= " and l.user_action in ('save_result_pcr','save_result')";
	} else if ($uact == 'save_approve_result_pcr') {
		$where .= " and l.user_action in ('save_approve_result_pcr','save_approve_result','approve_result_pcr','approve_result')";
	} else {
		$where .= $wpdb->prepare(' and l.user_action = %s', $uact);
	}
} else {
	$where .= " and (l.user_action in ('view_req_form','view_req','view_requisition_form','save_result_pcr','save_result','save_approve_result_pcr','save_approve_result','approve_result_pcr','approve_result','patient_login','patient_logout') or (l.user_action in ('login','logout') and l.data = 'patient_portal'))";
}

if (!empty($_GET['record_id'])) {
	$record_id = '%' . $wpdb->esc_like(sanitize_text_field($_GET['record_id'])) . '%';
	$where .= $wpdb->prepare(" and (l.mid like %s or l.assen like %s or l.data like %s)", $record_id, $record_id, $record_id);
}

if (!empty($_GET['from'])) {
	$from = date_i18n('Y-m-d', strtotime($_GET['from']));
	$where .= $wpdb->prepare(' and l.created >= %s', $from);
}

if (!empty($_GET['to'])) {
	$to = date_i18n('Y-m-d', strtotime($_GET['to'])) . ' 23:59:59';
	$where .= $wpdb->prepare(' and l.created <= %s', $to);
}

if (isset($_GET['export']) && $_GET['export'] == 'csv') {
	$export_logs = $wpdb->get_results("
		select l.*, u.user_email, u.display_name, u.user_login
		from ci_log_users l
		join {$wpdb->users} u ON u.ID = l.user_id
		where 1 {$where}
		order by l.created desc
	");
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename=user-logs-' . date_i18n('Y-m-d-His') . '.csv');
	$output = fopen('php://output', 'w');
	fputcsv($output, array('Date', 'Action', 'Data', 'User Email', 'IP'));
	foreach ($export_logs as $log) {
		$action_label = $actions[$log->user_action] ?? str_replace('_', ' ', $log->user_action);
		if ($log->data == 'patient_portal' && $log->user_action == 'login') {
			$action_label = 'User Login';
		} else if ($log->data == 'patient_portal' && $log->user_action == 'logout') {
			$action_label = 'User Logout';
		} else if (in_array($log->user_action, array('view_req', 'view_requisition_form'), true)) {
			$action_label = 'View Req Form';
		} else if ($log->user_action == 'save_result') {
			$action_label = 'Save Result PCR';
		} else if (in_array($log->user_action, array('save_approve_result', 'approve_result_pcr', 'approve_result'), true)) {
			$action_label = 'Save/Approve Result PCR';
		}
		fputcsv($output, array($log->created, $action_label, $log->data, $log->user_email, $log->ip));
	}
	fclose($output);
	exit;
}

$row_per_page = 100;
$pagination = new Tr_Pagination_class2($row_per_page);
$offset = $pagination->offset();
$total = (int) $wpdb->get_var("
	select count(*)
	from ci_log_users l
	join {$wpdb->users} u ON u.ID = l.user_id
	where 1 {$where}
");
$pagination->setTotal($total);
$logs = $wpdb->get_results("
	select l.*, u.user_email, u.display_name, u.user_login
	from ci_log_users l
	join {$wpdb->users} u ON u.ID = l.user_id
	where 1 {$where}
	order by l.created desc
	limit {$offset},{$row_per_page}
");
$current_page = max(1, (int) ($_GET['paged'] ?? 1));
$total_pages = max(1, (int) ceil($total / $row_per_page));
$page_url_args = array(
	'page' => $_GET['page'],
	'uact' => $_GET['uact'] ?? '',
	'from' => $_GET['from'] ?? '',
	'to' => $_GET['to'] ?? '',
);
$page_url_args = array_filter($page_url_args, function($value) {
	return $value !== '';
});
$page_url = function($page_num) use ($page_url_args) {
	return esc_url(add_query_arg(array_merge($page_url_args, array('paged' => max(1, (int) $page_num))), admin_url('admin.php')));
};
$pagination_button = function($label, $url, $disabled = false) {
	$class = 'inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 hover:bg-accent hover:text-accent-foreground h-8 rounded-md gap-1.5 px-3';
	if ($disabled) {
		return '<button type="button" class="' . esc_attr($class) . '" disabled>' . $label . '</button>';
	}
	return '<a class="' . esc_attr($class) . '" href="' . $url . '">' . $label . '</a>';
};
?>
<style>
	:root {
		--primary: #d83d00;
		--primary-foreground: #fff;
		--card: #fff;
		--card-foreground: #1a1a1a;
		--accent: #fef3f0;
		--accent-foreground: #d83d00;
		--border: #0000001f;
		--input-background: #f8f9fb;
		--input-border: #00000026;
		--ring: #d83d00;
		--text-sm: .875rem;
		--text-2xl: 1.5rem;
	}
	.tr-user-logs * { box-sizing: border-box; }
	.tr-user-logs { color: #1a1a1a; margin: 0 0 0 -20px; }
	.tr-user-logs svg { display: block; vertical-align: middle; }
	.tr-user-logs table { border-collapse: collapse; text-indent: 0; }
	.tr-user-logs button,
	.tr-user-logs input,
	.tr-user-logs select { font: inherit; letter-spacing: inherit; }
	.tr-user-logs .flex-1 { flex: 1 1 0%; }
	.tr-user-logs .overflow-auto { overflow: auto; }
	.tr-user-logs .overflow-x-auto { overflow-x: auto; }
	.tr-user-logs .p-4 { padding: 1rem; }
	.tr-user-logs .p-0 { padding: 0; }
	.tr-user-logs .pt-6 { padding-top: 1.5rem; }
	.tr-user-logs .px-2 { padding-left: .5rem; padding-right: .5rem; }
	.tr-user-logs .px-3 { padding-left: .75rem; padding-right: .75rem; }
	.tr-user-logs .px-4 { padding-left: 1rem; padding-right: 1rem; }
	.tr-user-logs .px-6 { padding-left: 1.5rem; padding-right: 1.5rem; }
	.tr-user-logs .py-1 { padding-top: .25rem; padding-bottom: .25rem; }
	.tr-user-logs .py-2 { padding-top: .5rem; padding-bottom: .5rem; }
	.tr-user-logs .py-3 { padding-top: .75rem; padding-bottom: .75rem; }
	.tr-user-logs .pb-6 { padding-bottom: 1.5rem; }
	.tr-user-logs .mb-1\.5 { margin-bottom: .375rem; }
	.tr-user-logs .min-w-0 { min-width: 0; }
	.tr-user-logs .min-w-\[150px\] { min-width: 150px; }
	.tr-user-logs .min-w-\[200px\] { min-width: 200px; }
	.tr-user-logs .w-full { width: 100%; }
	.tr-user-logs .h-4 { height: 1rem; }
	.tr-user-logs .w-4 { width: 1rem; }
	.tr-user-logs .h-8 { height: 2rem; }
	.tr-user-logs .h-9 { height: 2.25rem; }
	.tr-user-logs .size-4 { width: 1rem; height: 1rem; }
	.tr-user-logs .flex { display: flex; }
	.tr-user-logs .inline-flex { display: inline-flex; }
	.tr-user-logs .block { display: block; }
	.tr-user-logs .items-center { align-items: center; }
	.tr-user-logs .items-end { align-items: flex-end; }
	.tr-user-logs .justify-center { justify-content: center; }
	.tr-user-logs .justify-between { justify-content: space-between; }
	.tr-user-logs .justify-end { justify-content: flex-end; }
	.tr-user-logs .flex-col { flex-direction: column; }
	.tr-user-logs .flex-wrap { flex-wrap: wrap; }
	.tr-user-logs .gap-1\.5 { gap: .375rem; }
	.tr-user-logs .gap-2 { gap: .5rem; }
	.tr-user-logs .gap-4 { gap: 1rem; }
	.tr-user-logs .gap-6 { gap: 1.5rem; }
	.tr-user-logs .space-y-6 > :not(:last-child) { margin-bottom: 1.5rem; }
	.tr-user-logs .rounded-md { border-radius: .375rem; }
	.tr-user-logs .rounded-xl { border-radius: .75rem; }
	.tr-user-logs .border { border: 1px solid var(--border); }
	.tr-user-logs .border-b { border-bottom: 1px solid var(--border); }
	.tr-user-logs .border-input { border-color: var(--input-border); }
	.tr-user-logs .bg-\[\#f8f9fa\] { background: #f8f9fa; }
	.tr-user-logs .bg-card { background: var(--card); }
	.tr-user-logs .bg-white { background: #fff; }
	.tr-user-logs .bg-gray-50 { background: #f9fafb; }
	.tr-user-logs .bg-input-background { background: var(--input-background); }
	.tr-user-logs .bg-primary { background: var(--primary); }
	.tr-user-logs .text-card-foreground { color: var(--card-foreground); }
	.tr-user-logs .text-primary-foreground { color: var(--primary-foreground); }
	.tr-user-logs .text-gray-600 { color: #4b5563; }
	.tr-user-logs .text-gray-700 { color: #374151; }
	.tr-user-logs .text-gray-900 { color: #111827; }
	.tr-user-logs .text-left { text-align: left; }
	.tr-user-logs .text-sm { font-size: var(--text-sm); line-height: 1.25rem; }
	.tr-user-logs .text-base { font-size: 1rem; line-height: 1.5rem; }
	.tr-user-logs .text-2xl { font-size: var(--text-2xl); line-height: 2rem; }
	.tr-user-logs .font-medium { font-weight: 500; }
	.tr-user-logs .font-semibold { font-weight: 600; }
	.tr-user-logs .whitespace-nowrap { white-space: nowrap; }
	.tr-user-logs .transition-all { transition: all .15s ease-in-out; }
	.tr-user-logs .outline-none { outline: 2px solid transparent; outline-offset: 2px; }
	.tr-user-logs .select-none { user-select: none; }
	.tr-user-logs .opacity-50 { opacity: .5; }
	.tr-user-logs .shrink-0 { flex-shrink: 0; }
	.tr-user-logs .divide-y > :not(:last-child) { border-bottom: 1px solid #e5e7eb; }
	.tr-user-logs .hover\:bg-gray-50:hover { background: #f9fafb; }
	.tr-user-logs .hover\:bg-accent:hover { background: var(--accent); }
	.tr-user-logs .hover\:text-accent-foreground:hover { color: var(--accent-foreground); }
	.tr-user-logs .hover\:bg-primary\/90:hover { background: #c03600; }
	.tr-user-logs input,
	.tr-user-logs select {
		border: 1px solid var(--input-border);
		box-shadow: none;
		color: #1a1a1a;
		max-width: 100%;
	}
	.tr-user-logs input:focus,
	.tr-user-logs select:focus,
	.tr-user-logs button:focus,
	.tr-user-logs a:focus {
		border-color: var(--ring);
		box-shadow: 0 0 0 3px rgba(216, 61, 0, .18);
		outline: none;
	}
	.tr-user-logs button,
	.tr-user-logs a {
		border: 0;
		cursor: pointer;
		text-decoration: none;
	}
	.tr-user-logs button:disabled {
		cursor: not-allowed;
		opacity: .5;
	}
	.tr-user-logs .secondary-button {
		background: #fff;
		border: 1px solid var(--border);
		color: #1a1a1a;
	}
	.tr-user-logs .filter-button {
		background: #d83d00;
		color: #fff;
	}
	.tr-user-logs .log-select {
		appearance: none;
		background-image: linear-gradient(45deg, transparent 50%, #6b7280 50%), linear-gradient(135deg, #6b7280 50%, transparent 50%);
		background-position: calc(100% - 18px) 50%, calc(100% - 13px) 50%;
		background-size: 5px 5px, 5px 5px;
		background-repeat: no-repeat;
		padding-right: 2.25rem;
	}
	@media (min-width: 640px) {
		.tr-user-logs .sm\:p-6 { padding: 1.5rem; }
	}
	@media (min-width: 1024px) {
		.tr-user-logs .lg\:p-8 { padding: 2rem; }
	}
</style>
<div class="tr-user-logs">
	<div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
		<div class="space-y-6">
			<div>
				<h1 class="text-2xl font-semibold">User Logs</h1>
			</div>
			<div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
				<div data-slot="card-content" class="px-6 pb-6 pt-6">
					<form method="get" class="flex flex-wrap items-end gap-4">
						<input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>" />
						<div class="flex-1 min-w-[150px]">
							<label data-slot="label" class="items-center gap-2 font-medium text-gray-700 select-none text-sm mb-1.5 block" for="dateFrom">Date From</label>
							<input type="date" data-slot="input" class="border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-all outline-none" id="dateFrom" name="from" value="<?php echo esc_attr($_GET['from'] ?? ''); ?>">
						</div>
						<div class="flex-1 min-w-[150px]">
							<label data-slot="label" class="items-center gap-2 font-medium text-gray-700 select-none text-sm mb-1.5 block" for="dateTo">Date To</label>
							<input type="date" data-slot="input" class="border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-all outline-none" id="dateTo" name="to" value="<?php echo esc_attr($_GET['to'] ?? ''); ?>">
						</div>
						<div class="flex-1 min-w-[200px]">
							<select name="uact" class="log-select border-input text-gray-600 flex h-9 w-full items-center justify-between gap-2 rounded-md border bg-input-background px-3 py-2 text-sm whitespace-nowrap transition-all outline-none">
								<?php foreach ($actions as $k => $label) { ?>
									<option value="<?php echo esc_attr($k); ?>" <?php selected($k, $_GET['uact'] ?? ''); ?>><?php echo esc_html($label); ?></option>
								<?php } ?>
							</select>
						</div>
						<button data-slot="button" class="filter-button inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all h-9 px-4 py-2 hover:bg-primary/90" type="submit">Filter</button>
						<button data-slot="button" class="secondary-button inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all h-9 px-4 py-2 hover:bg-accent hover:text-accent-foreground" type="submit" name="export" value="csv">Export</button>
					</form>
				</div>
			</div>
			<div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border overflow-hidden">
				<div data-slot="card-content" class="p-0 pb-6">
					<div class="flex items-center justify-end gap-4 px-6 py-3 border-b bg-white">
						<span class="text-sm text-gray-600"><?php echo esc_html(number_format_i18n($total)); ?> items</span>
						<div class="flex items-center gap-1.5">
							<?php
							echo $pagination_button('<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m11 17-5-5 5-5"></path><path d="m18 17-5-5 5-5"></path></svg>', $page_url(1), $current_page <= 1);
							echo $pagination_button('<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"></path></svg>', $page_url($current_page - 1), $current_page <= 1);
							?>
							<span class="text-sm text-gray-600 px-2"><?php echo esc_html($current_page); ?> of <?php echo esc_html($total_pages); ?></span>
							<?php
							echo $pagination_button('<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"></path></svg>', $page_url($current_page + 1), $current_page >= $total_pages);
							echo $pagination_button('<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 17 5-5-5-5"></path><path d="m13 17 5-5-5-5"></path></svg>', $page_url($total_pages), $current_page >= $total_pages);
							?>
						</div>
					</div>
					<div class="overflow-x-auto">
						<table class="w-full">
							<thead class="bg-gray-50 border-b">
								<tr>
									<th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Date</th>
									<th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Action</th>
									<th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Data</th>
									<th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">User Email</th>
									<th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">IP</th>
								</tr>
							</thead>
							<tbody class="divide-y bg-white">
								<?php if (empty($logs)) { ?>
									<tr class="hover:bg-gray-50"><td colspan="5" class="px-6 py-3 text-sm text-gray-700">No activity found.</td></tr>
								<?php } ?>
								<?php foreach ($logs as $log) { ?>
									<?php
									$action_label = $actions[$log->user_action] ?? str_replace('_', ' ', $log->user_action);
									if ($log->data == 'patient_portal' && $log->user_action == 'login') {
										$action_label = 'User Login';
									} else if ($log->data == 'patient_portal' && $log->user_action == 'logout') {
										$action_label = 'User Logout';
									} else if (in_array($log->user_action, array('view_req', 'view_requisition_form'), true)) {
										$action_label = 'View Req Form';
									} else if ($log->user_action == 'save_result') {
										$action_label = 'Save Result PCR';
									} else if (in_array($log->user_action, array('save_approve_result', 'approve_result_pcr', 'approve_result'), true)) {
										$action_label = 'Save/Approve Result PCR';
									}
									?>
									<tr class="hover:bg-gray-50">
										<td class="px-6 py-3 text-sm text-gray-700 whitespace-nowrap"><?php echo esc_html($log->created); ?></td>
										<td class="px-6 py-3 text-sm text-gray-700"><?php echo esc_html($action_label); ?></td>
										<td class="px-6 py-3 text-sm text-gray-700"><?php echo esc_html($log->data); ?></td>
										<td class="px-6 py-3 text-sm text-gray-700"><a href="mailto:<?php echo esc_attr($log->user_email); ?>"><?php echo esc_html($log->user_email); ?></a></td>
										<td class="px-6 py-3 text-sm text-gray-700"><?php echo esc_html($log->ip); ?></td>
									</tr>
								<?php } ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
