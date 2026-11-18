<?php
global $wpdb, $current_role, $wp_roles;
$user = wp_get_current_user();

include TRCPDD_PATH . 'parts/dashboard/user_survey_ajax.php';

$labinfo = CI_Base_Lab::get_current_lab();
$labinfo['address'] = $labinfo['lab_city'] . ', ' . $labinfo['lab_state'];


$user->last_login = $wpdb->get_var("select created from ci_log_users 
				where user_id = {$user->ID} 
				and user_action = 'login' 
				and data = 'success' 
				order by created desc 
				limit 1");
if (empty($user->last_login)) {
	$user->last_login = date_i18n('Y-m-d H:i:s');
}
$online_seconds = strtotime(date_i18n('Y-m-d H:i:s')) - strtotime($user->last_login);

$user->time_online =  trpdd_display_time_duration($online_seconds, false);


$user->session_this_week = $wpdb->get_var("select count(*) from ci_log_users 
				where user_id = {$user->ID} 
				and user_action = 'login' 
				and data = 'success' 
				and created >= '" . date_i18n('Y-m-d', strtotime('-7 days')) . " 00:00:00' ");
if (!empty($user->last_login)) {
	$user->last_login = date_i18n('F j, Y, g:i A', strtotime($user->last_login));
}

$objs_role = $wp_roles->roles[$current_role];
$user->role = $objs_role['name'];
//$user->role = ucfirst(translate_user_role($current_role));


$tip_wish = $wpdb->get_row("
    SELECT text 
    FROM ci_welcome_messages 
    WHERE istatus = 1
    ORDER BY RAND() 
    LIMIT 1
");

$user->hello = 'Good Morning';
$user->lucide = 'sun';
$hour = (int) date_i18n('H');

if ($hour >= 5 && $hour < 12) {
	$user->hello = 'Good Morning';
	$user->lucide = 'sun';
} elseif ($hour >= 12 && $hour < 18) {
	$user->hello = 'Good Afternoon';
	$user->lucide = 'sun';
} else {
	$user->hello = 'Good Evening';
	$user->lucide = 'moon';
}

$noti_per_page = 3;
$page_show = 1;

$roles_in = array($current_role);
if (in_array($current_role, array('office_manager', 'doctor', 'clinic', 'collector', 'lab_director', 'lab_rep', 'sales_accounting'))) {
	$roles_in[] = 'acli';
} else if (in_array($current_role, array('lab', 'labb', 'labmanager', 'AdminCIDW', 'administrator_p', 'wp_admin'))) {
	$roles_in[] = 'alab';
}

$where_roles = array('me.read_roles is null', ' me.read_roles = \'\'');
foreach ($roles_in as $ri) {
	$where_roles[] = " FIND_IN_SET('{$ri}', me.read_roles)  ";
}

$where = " me.msg_type = 'n'";
$where .= " and (mu.user_id > 0  OR " . implode(' OR ', $where_roles) . ") ";

$where .= " AND (me.from_date IS NULL OR me.from_date <= CURDATE()) ";
$where .= " AND (me.to_date IS NULL OR me.to_date >= CURDATE()) ";

$user_id = get_current_user_id();
$sql_count = "
    SELECT COUNT(DISTINCT me.ID)
    FROM ci_messages AS me
    LEFT JOIN ci_message_users mu ON mu.msg_ID = me.ID
    WHERE {$where}
	AND (mu.readed IS NULL OR mu.readed = 0)
	GROUP BY me.ID
";
$total_notifications = (int) $wpdb->get_var($sql_count);
$offset        = ($page_show - 1) * $noti_per_page;
$sql = "SELECT 
        me.*, 
		um.meta_value AS user_roles
    FROM ci_messages AS me
    LEFT JOIN ci_message_users mu 
        ON mu.msg_ID = me.ID and mu.user_id = {$user_id}
    LEFT JOIN {$wpdb->usermeta} um 
        ON um.user_id = me.author 
        AND um.meta_key = '{$wpdb->prefix}capabilities'
    WHERE {$where}
		AND (mu.readed IS NULL OR mu.readed = 0)
	GROUP BY me.ID
    ORDER BY me.created_date DESC
    LIMIT {$offset}, {$noti_per_page}";

$messages = $wpdb->get_results($sql);


$labpayment = get_option('_lab_payment_status', array());
if (isset($labpayment['status']) && $labpayment['status'] > 0) {

	$defaultmsgss = array(
		'1' => array(
			'background' => 'reminder',
			'subject' => '1-30 days - Account Notice',
			'msg' => 'Sometimes things get overlooked when labs get busy. This is a reminder that your payment for igniteLIS is now overdue.'
		),
		'2' => array(
			'background' => 'required',
			'subject' => '30-60 days - Account Action Required',
			'msg' => 'This account is seriously past due. Please contact your accounting immediately to keep the system running.'
		),
		'3' => array(
			'background' => 'suspended',
			'subject' => '60+ Days - Account Suspended',
			'msg' => 'This account has been suspended. Please contact your lab administrator for assistance.'
		),
		'4' => array(
			'background' => 'reminder',
			'subject' => 'Grace Period - Temporary Access',
			'msg' => 'This account has been suspended. Please contact your lab administrator for assistance.'
		),
	);
	$newmessage = new stdClass();
	$newmessage->ID = -1;
	$newmessage->role = 'administrator';
	$newmessage->background = isset($labpayment['background']) ? $labpayment['background'] : '';
	$newmessage->subject = isset($labpayment['subject']) ? $labpayment['subject'] : '';
	$newmessage->msg = isset($labpayment['notes']) ?  $labpayment['notes'] : '';
	$newmessage->created_date = date_i18n('Y-m-d');

	if (empty($newmessage->background)) {
		$newmessage->background = $defaultmsgss[$labpayment['status']]['background'];
	}

	if (empty($newmessage->subject)) {
		$newmessage->subject = $defaultmsgss[$labpayment['status']]['subject'];
	}

	if (empty($newmessage->msg)) {
		$newmessage->msg = $defaultmsgss[$labpayment['status']]['msg'];
	}

	$roles_show_payment_notes = array();
	if ($labpayment['role'] == 'admin') {
		$roles_show_payment_notes = array(
			'administrator_p',
			'wp_admin',
			'AdminCIDW',
			'administrator'
		);
	} else if ($labpayment['role'] == 'labs') {
		$roles_show_payment_notes = array(
			'lab',
			'lab_tech',
			'ref_lab_tech',
			'reflab_manager',
			'labb',
			'lab_director',
			'lab_manager',
			'lab_rep',
			'labmanager',
			'labsecman',
			'lab_sec'
		);
	} else if ($labpayment['role'] == 'admin_labs') {
		$roles_show_payment_notes =  array(
			'administrator_p',
			'wp_admin',
			'AdminCIDW',
			'administrator',
			'lab',
			'lab_tech',
			'ref_lab_tech',
			'reflab_manager',
			'labb',
			'lab_director',
			'lab_manager',
			'lab_rep',
			'labmanager',
			'labsecman',
			'lab_sec'
		);
	}

	if (empty($roles_show_payment_notes) || in_array($current_role, $roles_show_payment_notes)) {
		$messages = array_merge(array($newmessage), $messages);
	}
}

$all_on_hold = [];

foreach (tr_pdd_get_batch_types() as $bt) {
	$btype = $bt;
	$conditions = trpdd_get_batch_conditions($user->ID, $btype);
	$where_conditions = $conditions['where'];
	$batch_tbl = tr_pdd_get_table_by_btype($btype);

	$sql = $wpdb->prepare("
        SELECT l.*, ba.*
        FROM ci_log_users AS l
        INNER JOIN {$batch_tbl} AS ba ON ba.mid = l.mid
        WHERE l.user_action = 'hold_rs' AND ba.batch_status='hold'
        AND l.btype = %s {$where_conditions}
    ", $btype);

	$results = $wpdb->get_results($sql);

	if ($results) {
		foreach ($results as $row) {
			if (!empty($row->data)) {
				$hold_reason_arr = json_decode($row->data, true);
				if (is_array($hold_reason_arr) && isset($hold_reason_arr['reason'])) {
					if (isset($hold_reason_arr['reason_title']) && ($hold_reason_arr['reason_title'] === 'Clinic Issue')) {
						$row->hold_reason = $hold_reason_arr['reason'];
						$all_on_hold[] = $row;
					}
				}
			}
		}
	}
}

if (!empty($all_on_hold)) {
	usort($all_on_hold, function ($a, $b) {
		return strtotime($b->created) - strtotime($a->created);
	});

	$latest_created = $all_on_hold[0]->created;

	$msg_body = '';
	foreach ($all_on_hold as $row) {
		$msg_body .= '<div><a href="?page=' . $row->btype . '_req_form&act=edit&id=' . $row->mid . '">' . $row->assen . '</a> - ' . $row->hold_reason . '</div>';
	}

	$newmessage = new stdClass();
	$newmessage->ID = -1;
	$newmessage->role = 'lab';
	$newmessage->background = 'medium';
	$newmessage->subject = "On Hold";
	$newmessage->msg = $msg_body;
	$newmessage->created_date = $latest_created;

	$messages = array_merge([$newmessage], $messages);
}

$total_pages = ceil($total_notifications / $noti_per_page);
$christmax = false;
if (date_i18n('md') < '0105' || date_i18n('md') >= '1220') {
	$christmax = true;
}
?>
<style>
	.update-messages h2,
	.update-php h2,
	h4 {
		font-size: unset !important;
		margin: unset !important;
	}

	h1 {
		font-weight: 600 !important;
	}

	p.font-medium {
		font-size: var(--text-lg);
		margin: 0.4em 0;
		--tw-font-weight: var(--font-weight-medium);
		font-weight: var(--font-weight-medium);
	}

	p.text-lg {
		font-size: var(--text-lg);
		line-height: var(--tw-leading, var(--text-lg--line-height));
	}
</style>

<div class="flex-1 overflow-auto p-8 wrap">
	<div class="space-y-5">

		<?php if (!isset($is_old_welcome) || !$is_old_welcome) { ?>
			<div class="flex items-center justify-between">
				<div class="flex items-center gap-3">
					<h1 class="text-2xl font-semibold">Welcome</h1>
					<?php if ($christmax) { ?>
						<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-snowflake h-5 w-5 text-blue-400" aria-hidden="true">
							<path d="m10 20-1.25-2.5L6 18"></path>
							<path d="M10 4 8.75 6.5 6 6"></path>
							<path d="m14 20 1.25-2.5L18 18"></path>
							<path d="m14 4 1.25 2.5L18 6"></path>
							<path d="m17 21-3-6h-4"></path>
							<path d="m17 3-3 6 1.5 3"></path>
							<path d="M2 12h6.5L10 9"></path>
							<path d="m20 10-1.5 2 1.5 2"></path>
							<path d="M22 12h-6.5L14 15"></path>
							<path d="m4 10 1.5 2L4 14"></path>
							<path d="m7 21 3-6-1.5-3"></path>
							<path d="m7 3 3 6h4"></path>
						</svg>
					<?php } ?>
				</div>
				<button type="button"
					onclick="window.location.href='?page=dashboard_main'"
					data-slot="button"
					class="inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:bg-accent dark:hover:bg-accent/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 text-muted-foreground hover:text-foreground">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
						stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
						class="lucide lucide-arrow-left h-4 w-4 mr-2" aria-hidden="true">
						<path d="m12 19-7-7 7-7"></path>
						<path d="M19 12H5"></path>
					</svg>Dashboard
				</button>
			</div>
			<?php
			if ($christmax) {
			?>
				<div data-slot="card" class="text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card bg-gradient-to-r from-red-50 via-white to-green-50 relative z-10">
					<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 py-4">
						<div class="flex items-center justify-center gap-3 text-center"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-snowflake h-6 w-6 text-blue-400" aria-hidden="true">
								<path d="m10 20-1.25-2.5L6 18"></path>
								<path d="M10 4 8.75 6.5 6 6"></path>
								<path d="m14 20 1.25-2.5L18 18"></path>
								<path d="m14 4 1.25 2.5L18 6"></path>
								<path d="m17 21-3-6h-4"></path>
								<path d="m17 3-3 6 1.5 3"></path>
								<path d="M2 12h6.5L10 9"></path>
								<path d="m20 10-1.5 2 1.5 2"></path>
								<path d="M22 12h-6.5L14 15"></path>
								<path d="m4 10 1.5 2L4 14"></path>
								<path d="m7 21 3-6-1.5-3"></path>
								<path d="m7 3 3 6h4"></path>
							</svg>
							<div>
								<p class="font-medium text-lg">🎄 Season's Greetings from the ignite Team! 🎄</p>
								<p class="text-sm text-muted-foreground mt-1">Wishing you and your loved ones a wonderful holiday season and a Happy New Year.</p>
							</div><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-snowflake h-6 w-6 text-blue-500" aria-hidden="true">
								<path d="m10 20-1.25-2.5L6 18"></path>
								<path d="M10 4 8.75 6.5 6 6"></path>
								<path d="m14 20 1.25-2.5L18 18"></path>
								<path d="m14 4 1.25 2.5L18 6"></path>
								<path d="m17 21-3-6h-4"></path>
								<path d="m17 3-3 6 1.5 3"></path>
								<path d="M2 12h6.5L10 9"></path>
								<path d="m20 10-1.5 2 1.5 2"></path>
								<path d="M22 12h-6.5L14 15"></path>
								<path d="m4 10 1.5 2L4 14"></path>
								<path d="m7 21 3-6-1.5-3"></path>
								<path d="m7 3 3 6h4"></path>
							</svg>
						</div>
					</div>
				</div>
				<div id="snowfall-container"></div>
				<style>
					#snowfall-container {
						position: fixed;
						inset: 0;
						pointer-events: none;
						overflow: hidden;
						z-index: 9999;
					}

					.snowflake {
						position: absolute;
						color: #bfdbfe;
						opacity: 0.5;
						animation-name: snowfall;
						animation-timing-function: linear;
						animation-iteration-count: infinite;
					}

					@keyframes snowfall {
						0% {
							transform: translateY(0) rotate(0deg);
						}

						100% {
							transform: translateY(100vh) rotate(360deg);
						}
					}
				</style>
				<script>
					jQuery(function($) {
						window.$ = jQuery;
						const snowCount = 15;
						const $container = $('#snowfall-container');

						for (let i = 0; i < snowCount; i++) {
							const size = 12 + Math.random() * 15;
							const left = Math.random() * 100;
							const duration = 8 + Math.random() * 8;
							const delay = Math.random() * 5;

							const $snow = $('<div class="snowflake">❄</div>').css({
								left: left + '%',
								top: (-10 - Math.random() * 20) + 'px',
								fontSize: size + 'px',
								animationDuration: duration + 's',
								animationDelay: delay + 's'
							});

							$container.append($snow);
						}

					});
				</script>
			<?php } ?>

			<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
				<div data-slot="card"
					class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
					<div data-slot="card-header"
						class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 text-center">
						<?php if ($christmax) { ?>
							<div class="w-16 h-16 mx-auto mb-4 rounded-xl flex items-center justify-center relative bg-gradient-to-br from-red-100 to-green-100"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building2 lucide-building-2 h-8 w-8 text-gray-600" aria-hidden="true">
									<path d="M10 12h4"></path>
									<path d="M10 8h4"></path>
									<path d="M14 21v-3a2 2 0 0 0-4 0v3"></path>
									<path d="M6 10H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2"></path>
									<path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"></path>
								</svg><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-snowflake h-4 w-4 text-blue-400 absolute -top-1 -right-1" aria-hidden="true">
									<path d="m10 20-1.25-2.5L6 18"></path>
									<path d="M10 4 8.75 6.5 6 6"></path>
									<path d="m14 20 1.25-2.5L18 18"></path>
									<path d="m14 4 1.25 2.5L18 6"></path>
									<path d="m17 21-3-6h-4"></path>
									<path d="m17 3-3 6 1.5 3"></path>
									<path d="M2 12h6.5L10 9"></path>
									<path d="m20 10-1.5 2 1.5 2"></path>
									<path d="M22 12h-6.5L14 15"></path>
									<path d="m4 10 1.5 2L4 14"></path>
									<path d="m7 21 3-6-1.5-3"></path>
									<path d="m7 3 3 6h4"></path>
								</svg></div>
						<?php } else { ?>
							<div class="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-xl flex items-center justify-center">
								<svg
									xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
									stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
									class="lucide lucide-building2 lucide-building-2 h-8 w-8 text-gray-600" aria-hidden="true">
									<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"></path>
									<path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"></path>
									<path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"></path>
									<path d="M10 6h4"></path>
									<path d="M10 10h4"></path>
									<path d="M10 14h4"></path>
									<path d="M10 18h4"></path>
								</svg>
							</div>
						<?php } ?>
						<h4 data-slot="card-title" class="text-xl"><?php echo CI_Base_Lab::get_current_lab_name() ?></h4>
						<p data-slot="card-description" class="text-muted-foreground">Your laboratory information</p>
					</div>
					<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-4">
						<div class="space-y-3">
							<div class="flex items-center justify-between py-2 border-b">
								<div class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="24"
										height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
										stroke-linecap="round" stroke-linejoin="round"
										class="lucide lucide-map-pin h-4 w-4 text-muted-foreground" aria-hidden="true">
										<path
											d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0">
										</path>
										<circle cx="12" cy="10" r="3"></circle>
									</svg><span class="font-medium text-sm">Location</span></div><span class="text-sm"><?php echo $labinfo['address'] ?></span>
							</div>
							<div class="flex items-center justify-between py-2 border-b">
								<div class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="24"
										height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
										stroke-linecap="round" stroke-linejoin="round"
										class="lucide lucide-file-text h-4 w-4 text-muted-foreground" aria-hidden="true">
										<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
										<path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
										<path d="M10 9H8"></path>
										<path d="M16 13H8"></path>
										<path d="M16 17H8"></path>
									</svg><span class="font-medium text-sm">CLIA</span></div>
								<span data-slot="badge"
									class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent bg-secondary text-secondary-foreground [a&amp;]:hover:bg-secondary/90 text-xs">
									<?php echo $labinfo['lab_clia'] ?>
								</span>
							</div>
							<div class="flex items-center justify-between py-2">
								<div class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="24"
										height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
										stroke-linecap="round" stroke-linejoin="round"
										class="lucide lucide-circle-check-big h-4 w-4 text-muted-foreground"
										aria-hidden="true">
										<path d="M21.801 10A10 10 0 1 1 17 3.335"></path>
										<path d="m9 11 3 3L22 4"></path>
									</svg><span class="font-medium text-sm">Status</span></div><span data-slot="badge"
									class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden [a&amp;]:hover:bg-primary/90 bg-green-100 text-green-800 border-green-200"><?php echo $labinfo['lab_status'] ? 'Active' : 'Deactive' ?></span>
							</div>
						</div>
					</div>
				</div>
				<div data-slot="card"
					class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
					<div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 text-center">
						<?php if ($christmax) { ?>
							<div class="w-16 h-16 mx-auto mb-4 rounded-xl flex items-center justify-center bg-gradient-to-br from-red-100 to-green-100"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles h-6 w-6 text-red-500" aria-hidden="true">
									<path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"></path>
									<path d="M20 2v4"></path>
									<path d="M22 4h-4"></path>
									<circle cx="4" cy="20" r="2"></circle>
								</svg></div>
						<?php } else { ?>
							<div class="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-xl flex items-center justify-center">
								<?php
								if ($user->lucide == "sun") {
								?>
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sun h-6 w-6 text-yellow-500" aria-hidden="true">
										<circle cx="12" cy="12" r="4"></circle>
										<path d="M12 2v2"></path>
										<path d="M12 20v2"></path>
										<path d="m4.93 4.93 1.41 1.41"></path>
										<path d="m17.66 17.66 1.41 1.41"></path>
										<path d="M2 12h2"></path>
										<path d="M20 12h2"></path>
										<path d="m6.34 17.66-1.41 1.41"></path>
										<path d="m19.07 4.93-1.41 1.41"></path>
									</svg>
								<?php } else { ?>
									<svg
										xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
										stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
										class="lucide lucide-moom h-6 w-6 text-indigo-400" aria-hidden="true">
										<path
											d="M20.985 12.486a9 9 0 1 1-9.473-9.472c.405-.022.617.46.402.803a6 6 0 0 0 8.268 8.268c.344-.215.825-.004.803.401">
										</path>
									</svg>
								<?php } ?>
							</div>
						<?php } ?>
						<h4 data-slot="card-title" class="text-xl">
							<?php if ($christmax) { ?>
								Happy Holidays, <?php echo $user->display_name ?>
							<?php } else { ?>
								<?php echo $user->hello ?>, <?php echo $user->display_name ?>
							<?php } ?>
						</h4>
						<p data-slot="card-description" class="text-muted-foreground">Welcome to your lab dashboard</p>
					</div>
					<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-6">
						<div class="bg-slate-50 rounded-lg p-4 text-center">
							<?php
							echo '<p class="text-sm font-medium text-slate-600 mb-1">' . date_i18n('l, F j, Y') . '</p>';
							echo '<p class="text-lg font-semibold text-slate-700">' . date_i18n('h:i A') . '</p>';
							?>
						</div>
						<div class="space-y-3 text-sm text-muted-foreground">
							<?php if ($christmax) { ?>
								<p>Your work helps guide better outcomes for patient care. Thank you for your dedication throughout the year.</p>
							<?php } else { ?>
								<p><?php if ($tip_wish && !empty($tip_wish->text)) {
										echo $tip_wish->text;
									} ?></p>
							<?php } ?>
						</div>
					</div>
				</div>
				<div data-slot="card"
					class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
					<div data-slot="card-header"
						class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 text-center">
						<?php if ($christmax) { ?>
							<div class="w-16 h-16 mx-auto mb-4 rounded-xl flex items-center justify-center relative bg-gradient-to-br from-blue-100 to-indigo-100"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user h-8 w-8 text-blue-500" aria-hidden="true">
									<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
									<circle cx="12" cy="7" r="4"></circle>
								</svg><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles h-4 w-4 text-yellow-400 absolute -top-1 -right-1" aria-hidden="true">
									<path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"></path>
									<path d="M20 2v4"></path>
									<path d="M22 4h-4"></path>
									<circle cx="4" cy="20" r="2"></circle>
								</svg></div>
						<?php } else { ?>
							<div class="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-xl flex items-center justify-center">
								<svg
									xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
									stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
									class="lucide lucide-user h-8 w-8 text-blue-500" aria-hidden="true">
									<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
									<circle cx="12" cy="7" r="4"></circle>
								</svg>
							</div>
						<?php } ?>

						<h4 data-slot="card-title" class="text-xl">Your Activity</h4>
						<p data-slot="card-description" class="text-muted-foreground">Personal usage statistics</p>
					</div>
					<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-4">
						<div class="space-y-3">
							<div class="flex items-center justify-between py-2 border-b">
								<div class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="24"
										height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
										stroke-linecap="round" stroke-linejoin="round"
										class="lucide lucide-log-in h-4 w-4 text-muted-foreground" aria-hidden="true">
										<path d="m10 17 5-5-5-5"></path>
										<path d="M15 12H3"></path>
										<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
									</svg><span class="font-medium text-sm">Last Login</span></div><span
									class="text-sm"><?php echo $user->last_login; ?></span>
							</div>
							<div class="flex items-center justify-between py-2 border-b">
								<div class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="24"
										height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
										stroke-linecap="round" stroke-linejoin="round"
										class="lucide lucide-timer h-4 w-4 text-muted-foreground" aria-hidden="true">
										<line x1="10" x2="14" y1="2" y2="2"></line>
										<line x1="12" x2="15" y1="14" y2="11"></line>
										<circle cx="12" cy="14" r="8"></circle>
									</svg><span class="font-medium text-sm">Time Online</span></div><span class="text-sm"><?php echo $user->time_online ?></span>
							</div>
							<div class="flex items-center justify-between py-2 border-b">
								<div class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="24"
										height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
										stroke-linecap="round" stroke-linejoin="round"
										class="lucide lucide-activity h-4 w-4 text-muted-foreground" aria-hidden="true">
										<path
											d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2">
										</path>
									</svg><span class="font-medium text-sm">Sessions This Week</span></div><span
									data-slot="badge"
									class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent bg-secondary text-secondary-foreground [a&amp;]:hover:bg-secondary/90 text-xs"><?php echo $user->session_this_week ?></span>
							</div>
							<div class="flex items-center justify-between py-2">
								<div class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="24"
										height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
										stroke-linecap="round" stroke-linejoin="round"
										class="lucide lucide-user h-4 w-4 text-muted-foreground" aria-hidden="true">
										<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
										<circle cx="12" cy="7" r="4"></circle>
									</svg><span class="font-medium text-sm">Role</span></div><span
									class="text-sm"><?php echo $user->role ?></span>
							</div>
						</div>
					</div>
				</div>
			</div>
		<?php
		}
		include TRCPDD_PATH . 'parts/dashboard/user_survey.php';
		?>

		<?php if ($messages): ?>
			<div id="welcome_notifi_area" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
				<div data-slot="card-header"
					class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
					<h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg
							xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
							stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
							class="lucide lucide-megaphone h-5 w-5 text-primary" aria-hidden="true">
							<path
								d="M11 6a13 13 0 0 0 8.4-2.8A1 1 0 0 1 21 4v12a1 1 0 0 1-1.6.8A13 13 0 0 0 11 14H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z">
							</path>
							<path d="M6 14a12 12 0 0 0 2.4 7.2 2 2 0 0 0 3.2-2.4A8 8 0 0 1 10 14"></path>
							<path d="M8 6v8"></path>
						</svg>Notices</h4>
				</div>
				<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
					<div id="all_notifications" class="space-y-4">

						<?php
						foreach ($messages as $msg) {
							include TRCPDD_PATH . 'parts/dashboard/noti_item.php';
						}
						?>

					</div>
					<?php if ($messages) { ?>
						<div id="area_btn_read_more" class="mt-4 pt-4 border-t">
							<button id="btn_read_more_notification" data-slot="button"
								class="inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*=\'size-\'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 w-full">View All Notices
							</button>
						</div>
					<?php } ?>

				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<script>
	var total_notifications = <?php echo (int) $total_notifications; ?>;
	var total_pages = <?php echo (int) $total_pages; ?>;
	var page_show = <?php echo (int) $page_show; ?>;
	var noti_per_page = <?php echo (int) $noti_per_page; ?>;
	var count_item_noti = 0;

	jQuery(function($) {
		$(document).on("click", "#btn_read_more_notification", function(e) {
			page_show++;
			count_item_noti = count_item_noti + noti_per_page;
			$("#btn_read_more_notification").prop("disabled", true).text("⏳ Loading...");

			$.ajax({
				data: {
					tr_action: 'dashboard_ajax',
					func: 'read_more_notification',
					page_show: page_show,
					noti_per_page: noti_per_page
				},
				success: function(data) {
					$("#all_notifications").append(data);
					if (count_item_noti < total_notifications) {
						$("#btn_read_more_notification").prop("disabled", false).text("View All Notices");
					} else {
						$("#btn_read_more_notification").remove();
					}
				},
				error: function(xhr, status, err) {
					console.error("AJAX error:", status, err);
					$("#btn_read_more_notification").prop("disabled", false).text("⚠️ Try again");
				}
			});
		});

		$(document).on("click", ".btn_del_noti", function() {
			var id_noti = $(this).attr("noti-id");
			var id_n_db = $(this).attr("noti-id-db");
			$(this).html("⌛️");
			$.ajax({
				data: {
					tr_action: 'dashboard_ajax',
					func: 'remove_notification',
					id_notification: id_n_db,
					type: 'n'
				},
				success: function(data) {
					if (data.status == "ok") {
						$("#" + id_noti).remove();
						if ($("#all_notifications").children().length === 0) {
							$("#welcome_notifi_area").remove();
						};
					} else {
						alert(data.msg);
					}
				},
				error: function(xhr, status, err) {
					console.error("AJAX error:", status, err);
				}
			});
		});

	});
</script>