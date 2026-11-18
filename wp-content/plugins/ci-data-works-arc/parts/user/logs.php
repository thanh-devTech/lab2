<?php
global $wpdb;
global $actions, $actions_b;
global $blog_id;

global $current_user;
global $is_ci_admin;

$aduser_ids 	= $wpdb->get_col("select ID from {$wpdb->users} u 
					join {$wpdb->usermeta} um ON um.user_id = u.ID and um.meta_key = '{$wpdb->prefix}capabilities' 
						where (u.user_login = 'Administrat0r' 
						or u.user_email like '%@ciwebstudio%'
						or um.meta_value like '%ci_dev%'
						or um.meta_value like '%ci_support%') ");
$aduser_ids_str = implode(',', $aduser_ids);


$from 		= isset($_GET['from']) ? $_GET['from'] : '';
$to 		= isset($_GET['to']) ? $_GET['to'] : '';
$uact 		= isset($_GET['uact']) ? $_GET['uact'] : '';
$trajax		= isset($_GET['trajax']) ? $_GET['trajax'] : '';
$user_view	= isset($_GET['user_view']) ? trim($_GET['user_view']) : '';
$record_id	= isset($_GET['record_id']) ? trim($_GET['record_id']) : '';

$allowed_edit = false;

$allowed_roles = ['administrator', 'clms', 'AdminCIDW'];

if (intval($is_ci_admin) === 1) {
	$allowed_edit = true;
}

if (!empty(array_intersect($current_user->roles, $allowed_roles))) {
	$allowed_edit = true;
}

if (current_user_can('list_users')) {
	$allowed_edit = true;
}

if (!$allowed_edit) {
	if ($trajax == '') {
		wp_die(__('Sorry, you are not allowed to access this page.'));
	} else {
		wp_send_json_error('Sorry, you are not allowed to access this page.');
	}
}

$actions = array(
	'login' => 'Login',
	'logout' => 'Logout',
	'loginfail' => 'Login Fail',
	'uploaded_rs' => 'Upload Result',
	'editapprove_rs' => 'Save/Approve result',
	'view_edit' => 'View Req Form',
	// 'savers' => 'Save Result',
	'approve_rs' => 'Approve Result',
	'approve_auto_rs' => 'Auto Approve Result',
	'view_req' => 'View Req',
	'save_req' => 'Edit Req',
	'delete_req' => 'Delete Req',
	'view_rs' => 'View Result',
	'edit_rs' => 'Edit Result',
	'reverse_rs' => 'Reverse Result',
	'hold_rs' => 'Hold',
	'download_rs' => 'Download Result',
	'download_results' => 'Download Results',
	'download_report' => 'Download Report',
	'download_batches' => 'Download Batches',
	'download_reqs' => 'Download Reqs',
	'download_billing' => 'Download Billing',
	'delete_user' => 'Delete User',
	'create_user' => 'Create User',
	'edit_user' => 'Edit User',
	'save_role' => 'Edit Role',
	'2fa_user' => "2FA User",
	'delete_role' => 'Delete Role',
	'add_role' => 'Add Role',
);
$actions_b = [
	'save_req_cd' => 'Edit Req',
	'edit_rs_cd' => 'Edit Result',
	'view_edit_lab' => 'View Lab',
	'view_edit_panel' => 'View Test Panel',
	'view_edit_panel_groups' => 'View Group Panel',
	'view_edit_payer' => 'View Payer',
	'view_edit_levey_jennings' => 'View Levey Jennings',
	'view_edit_apis' => 'View APIs',
	'view_edit_new_account_form' => 'View Clinic',
	'rmdiag' => 'Delete Diagnostic Code',
	'savediag' => 'Save Diagnostic Code',
	'edit_urine_validity' => 'Edit Urine Validity',
	'delete_urine_validity' => 'Delete Urine Validity',
	'change_btypes' => 'Change Batch Types',
	'delete_cli' => 'Delete Clinic',
	'edit_clinic' => 'Edit Clinic',
	'change_user_role' => 'Change User Role',
	'change_user' => 'Change User'
];
if (!function_exists('get_record_type')) {
	function get_record_type($action)
	{
		$map = [
			'logout'      => 'Logout',
			'login'       => 'Login',
			'role'        => 'Role',
			'user'        => 'User',
			'batches'      => 'Batch',
			'rs'          => 'Result',
			'result'      => 'Result',
			'hold'      => 'Result',
			'batch types' => 'Batch Type',
			'receiving' => 'Receiving',
			'req'         => 'Req Form',
			'data_works'  => 'Settings',
			'batch'       => 'Req Form',
			'test'        => 'Test',
			'panel'       => 'Panel',
			'report'      => 'Report',
			'billing'     => 'Billing',
			'cli'         => 'Clinic',
			'archived'    => 'Req Form',
			'unarchive'   => 'Req Form',
			'ref'   	  => 'Req Form',
			'lab'         => 'Lab',
			'payer' 	  => 'Payer',
			'apis'		  => 'APIs',
			'new_account_form' => 'Clinic',
			'diag'	  => 'Diagnostic Code',
			'urine validity' => 'Urine Validity',
		];

		$action = strtolower($action);

		foreach ($map as $key => $value) {
			if (strpos($action, $key) !== false) {
				return $value;
			}
		}

		return '';
	}
}
if (!function_exists('tr_filter_log')) {
	function tr_filter_log($log)
	{
		global $wpdb;
		global $actions, $actions_b;

		$log->user_action = str_replace('dna', 'pgx', $log->user_action);

		if ($log->user_action == 'loginfail') {
			$log->data = '';
		}

		$log->action = $log->user_action;
		if (isset($actions[$log->user_action])) {
			$log->action = $actions[$log->user_action];
		}
		if (isset($actions_b[$log->user_action])) {
			$log->action = $actions_b[$log->user_action];
		}

		if (in_array($log->user_action, array('save_req', 'editapprove_rs'))) {
			$log->action .=  ' ' . strtoupper($log->btype);
			$batch_tbl = tr_pdd_get_table_by_btype($log->btype);
			$assen = $wpdb->get_var("select assen from {$batch_tbl} where mid = {$log->mid} ");
			$log->data = $assen . ' ' . $log->data;
		}

		if ($log->user_action == 'receiving_add_batch') {
			$log->action = 'Receiving Add Batch ';
			$batch_tbl = tr_pdd_get_table_by_btype($log->btype);
			$assen = $wpdb->get_var("select assen from {$batch_tbl} where mid = {$log->mid} ");
			if (empty($log->data)) {
				$log->data = '=> ' . $wpdb->get_var("select batch_id from {$batch_tbl} where mid = {$log->mid} ");
			}
			$log->data = $assen . ' ' . $log->data;
		}
		$log->data = str_replace('[]', '', $log->data);
		$decoded = json_decode($log->data, true);
		if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
			array_walk_recursive($decoded, function (&$val) {
				if (is_string($val)) {
					$val = strip_tags($val);
				}
			});
			$log->data = '<pre style="margin:0;white-space:pre-wrap;font-size:12px;">'
				. esc_html(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
				. '</pre>';
		}

		if ($log->btype && $log->mid && empty($log->assen) && (strpos($log->user_action, 'rs') !== false || strpos($log->user_action, 'req') !== false)) {
			$batch_tbl = tr_pdd_get_table_by_btype($log->btype);
			$assen = $wpdb->get_var("select assen from {$batch_tbl} where mid = {$log->mid} ");
			if ($assen) {
				$log->assen = $assen;
			}
		}
		return $log;
	}
}



$where = " AND l.user_id > 0 ";

if (!empty($user_view)) {
	$where .= " AND l.user_id = " . intval($user_view) . " ";
} else if (!$is_ci_admin) {
	$where .= " AND l.user_id NOT IN ({$aduser_ids_str}) ";
}

if (!empty($from)) {
	$where .= " and created >= '{$from}' ";
}
if (!empty($to)) {
	$where .= " and created <= '{$to} 23:59:59' ";
}
if (!empty($uact)) {
	$where .= " and user_action = '{$uact}' ";
}
if (!empty($record_id)) {
	$where .= " and assen = '{$record_id}' ";
}
$where .= " and (lab_id is null or lab_id = {$blog_id} ) ";



if ($trajax == 'export') {

	$filename = 'Logs_' . $from . '.csv';
	tr_pdd_header('csv', $filename);
	$tmp_name = tempnam("/tmp", "logusers");
	$f = fopen($tmp_name, 'a+');
	$headers = array('Date', 'User Email', 'Action', 'Data', 'IP');
	fputcsv($f, $headers);

	$row_per_page = 10000;
	$pagination = new Tr_Pagination_class2($row_per_page);

	$total 		= $wpdb->get_var("select count(*) from ci_log_users l where 1 {$where}");

	if ($total > 100000) $total = 100000;
	$pagination->setTotal($total);

	for ($p = 1; $p <= $pagination->total_page; $p++) {
		$_REQUEST['paged'] = $p;
		$offset     = $pagination->offset();
		$limitstr = " limit {$offset},{$row_per_page} ";

		$logs = $wpdb->get_results("select l.* , u.user_email
		from ci_log_users l 
		join {$wpdb->users} u ON u.ID = l.user_id
		where 1 {$where}
		order by l.created desc 
		{$limitstr}");


		foreach ($logs as $log) {
			$log = tr_filter_log($log);
			$rowdata = array(
				$log->created,
				$log->user_email,
				$log->action,
				$log->data,
				$log->ip
			);
			fputcsv($f, $rowdata);
		}
	}

	fclose($f);
	readfile($tmp_name);
	@unlink($tmp_name);
	exit;
}

if (isset($_GET['debug'])) {
	var_dump($wpdb->last_error);
	var_dump($wpdb->last_query);
}

$users_for_filter = $wpdb->get_results("
    SELECT DISTINCT u.ID, u.display_name, u.user_login, u.user_email
    FROM {$wpdb->users} u
    INNER JOIN ci_log_users l ON l.user_id = u.ID
    WHERE u.ID > 0
    AND (l.lab_id IS NULL OR l.lab_id = {$blog_id})
    ORDER BY u.display_name ASC
");


$row_per_page = 100;
$pagination = new Tr_Pagination_class2($row_per_page);
$offset     = $pagination->offset();
$total 		= $wpdb->get_var("
  SELECT COUNT(*)
  FROM ci_log_users l
  JOIN {$wpdb->users} u ON u.ID = l.user_id
  WHERE 1 {$where}
");
$pagination->setTotal($total);
$limitstr = " limit {$offset},{$row_per_page} ";
$logs = $wpdb->get_results("select l.* , u.user_email
from ci_log_users l 
join {$wpdb->users} u ON u.ID = l.user_id
where 1
{$where}
order by l.created desc 
{$limitstr}");

if (isset($_GET['debug'])) {
	echo 'Debug2:</br>';
	var_dump($wpdb->last_error);
	var_dump($wpdb->last_query);
}

include_once(TRCPDD_PATH . 'pages/formdata.php');

?>
<style>
	input[type=email],
	input[type=button],
	input[type=email],
	input[type=search],
	input[type=date],
	input[type=submit],
	input[type=tel],
	input[type=text],
	input[type=number],
	input[type=color],
	input[type=password],
	select,
	textarea,
	.selected {
		border-radius: calc(var(--radius) - 2px) !important;
		background-color: oklch(0.98 0 0) !important;
		border: none;
	}

	.swal2-container {
		z-index: 9999;
	}

	.pagination-links a {
		border-radius: 5px !important;
		background-color: white !important;
		border: solid 1px #e0e0e0 !important;
		color: #314158 !important;
	}

	.pagination-links a:hover {
		background-color: #ffc9c9 !important;
	}

	.pagination-links a.disabled {
		background-color: #f9fafb !important;
		color: #d1d5dc !important;
	}

	.data-cell-content {
		display: -webkit-box;
		-webkit-line-clamp: 3;
		-webkit-box-orient: vertical;
		overflow: hidden;
		max-height: 4.5em;
		transition: max-height 0.2s ease;
	}

	.data-cell-content.expanded {
		display: block;
		max-height: none;
		-webkit-line-clamp: unset;
	}

	.btn-view-more {
		display: none;
		margin-top: 4px;
		font-size: 12px;
		color: rgb(216, 61, 0);
		background: none;
		border: none;
		padding: 0;
		cursor: pointer;
		font-weight: 500;
	}

	.btn-view-more:hover {
		text-decoration: underline;
	}
</style>

<div class="wrap">
	<div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
		<div class="space-y-6">
			<div>
				<h1 class="text-2xl font-semibold">User Logs</h1>
			</div>
			<form id="frm_log" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
				<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
					<input type="hidden" name="page" value="<?php echo isset($_GET['page']) ? $_GET['page'] : '' ?>" />
					<input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />
					<div class="flex flex-wrap items-end gap-4">
						<div class="flex-1 min-w-[150px]">
							<label data-slot="label" class="items-center gap-2 font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-sm mb-1.5 block" for="dateFrom">Date From</label>
							<input name="from" type="date" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="dateFrom" placeholder="yyyy-mm-dd" value="<?php echo $from ?>">
						</div>

						<div class="flex-1 min-w-[150px]">
							<label data-slot="label" class="items-center gap-2 font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-sm mb-1.5 block" for="dateTo">Date To</label>
							<input name="to" type="date" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="dateTo" placeholder="yyyy-mm-dd" value="<?php echo $to; ?>">
						</div>

						<div class="flex-1 min-w-[150px]">
							<label data-slot="label" class="items-center gap-2 font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-sm mb-1.5 block" for="record_id">Record ID</label>
							<input name="record_id" type="text" data-slot="input" class="placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none  disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" id="record_id" placeholder="Record ID" value="<?php echo $record_id; ?>">
						</div>


						<div class="flex-1 min-w-[200px]">
							<label data-slot="label" class="items-center gap-2 font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-sm mb-1.5 block" for="user_view">User</label>
							<select name="user_view" data-search="1" id="user_view">
								<?php
								echo '<option value="0">All User</option>';
								foreach ($users_for_filter as $u) {
									$selected = $u->ID == $user_view ? 'selected' : '';
									echo '<option value="' . $u->ID . '" ' . $selected . '>' . $u->user_email . '</option>';
								}
								?>
							</select>
						</div>

						<div class="flex-1 min-w-[200px]">
							<label data-slot="label" class="items-center gap-2 font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-sm mb-1.5 block" for="uact">Action</label>
							<select name="uact" data-search="1" id="uact">
								<?php
								echo '<option value="0">All Actions</option>';
								foreach ($actions as $vl => $txt) {
									$selected = $vl == $uact ? 'selected' : '';
									echo '<option value="' . $vl . '" ' . $selected . '>' . $txt . '</option>';
								}
								?>
							</select>
						</div>

						<button id="btn_filter" type="submit" value="Filter" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground hover:bg-primary/90 h-9 px-4 py-2 has-[&gt;svg]:px-3" style="background-color: rgb(216, 61, 0);">Filter</button>
						<button id="btn_export" type="submit" value="Export" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3">Export</button>
					</div>
				</div>
			</form>

			<div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border overflow-hidden">
				<div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-0">

					<div class="flex items-center justify-end gap-4 px-6 py-3 border-b bg-white">
						<?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?>
					</div>

					<div class="overflow-x-auto">
						<table class="w-full">
							<thead class="bg-gray-50 border-b">
								<tr>
									<th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Date</th>
									<th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">User Email</th>
									<th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Action</th>
									<th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Record Type</th>
									<th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Record ID</th>
									<th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Data</th>
									<th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">IP</th>
								</tr>
							</thead>
							<tbody class="divide-y divide-gray-200 bg-white">
								<?php
								foreach ($logs as $log) {
									$log = tr_filter_log($log);
								?>
									<tr class="hover:bg-gray-50">
										<td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap"><?php echo $log->created ?></td>
										<td class="px-4 py-3 text-sm text-gray-700">
											<button onclick="window.location='?page=ci_user_edit&user_id=<?php echo $log->user_id; ?>'" class="cursor-pointer text-sm font-medium hover:underline block" style="color: rgb(216, 61, 0);">
												<?php echo $log->user_email ?>
											</button>
										</td>
										<td class="px-4 py-3 text-sm text-gray-700"><?php echo $log->action ?></td>
										<td class="px-4 py-3 text-sm text-gray-700"><?php echo get_record_type($log->action ?? '') ?></td>
										<td class="px-4 py-3 text-sm text-gray-700"><?php echo !empty($log->assen) ? $log->assen : '' ?></td>
										<td style="max-width: 640px;word-break: break-word;" class="px-4 py-3 text-sm text-gray-700">
											<div class="data-cell-content"><?php echo $log->data ?></div>
											<button class="btn-view-more" onclick="toggleDataCell(this)">View more</button>
										</td>
										<td class="px-4 py-3 text-sm text-gray-700"><?php echo $log->ip ?></td>
									</tr>
								<?php
								}
								?>
							</tbody>
						</table>
					</div>

					<div class="flex items-center justify-end gap-4 px-6 py-3 border-b bg-white">
						<?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?>
					</div>

				</div>
			</div>
		</div>
	</div>
</div>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?v=' . TRCPDD_VS ?>"></script>
<script>
	var url_main_page = 'ci_user_logs';

	function updateCheckboxUI($el) {
		const isChecked = $el.attr("data-state") === "checked";
		$el.attr({
			"aria-checked": isChecked ? "true" : "false",
			"value": isChecked ? "1" : "0"
		});
		$el.find("span").toggleClass("flex", isChecked).toggleClass("hidden", !isChecked);
	}

	jQuery(document).ready(function($) {
		initCustomSelect("select");

		$("#btn_filter").click(function() {
			$("#act_form").remove();
		});

		$("#btn_export").click(function() {
			$("#btn_export").after('<input id="act_form" type="hidden" name="trajax" value="export"/>');
		});

	});

	function toggleDataCell(btn) {
		var content = btn.previousElementSibling;
		var isExpanded = content.classList.contains('expanded');
		content.classList.toggle('expanded');
		btn.textContent = isExpanded ? 'View more' : 'View less';
	}

	document.querySelectorAll('.data-cell-content').forEach(function(el) {
		if (el.scrollHeight > el.clientHeight + 2) {
			el.nextElementSibling.style.display = 'inline-block';
		}
	});
</script>