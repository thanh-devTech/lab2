<?php

$datetimenow = date_i18n('Y-m-d H:i:s');

$past_24h = date('Y-m-d H:00:00', strtotime($datetimenow) - 3600 * 24);
$past_48h = date('Y-m-d H:00:00', strtotime($datetimenow) - 3600 * 48);

$from_date = date_i18n('Y-m-d');

if (isset($_GET['date'])) {
	$from_date = ($_GET['date']);
}

$to_date        = $from_date . ' 23:59:59';

$today_batch_id = date('Ymd', strtotime($from_date));


$archive_date   = tr_get_option('ci_pdf_data', 'archive_date', '');


$where_doc  	= " and ba.doc >= '{$from_date}' and ba.doc <= '{$to_date}'";
$where_not_received = " and ba.doc <= '{$past_24h}'";

$where_inbound  = " and batch_id = 0 
					and ba.batch_status ='' ";

$where_received = " and ba.batch_id = '{$today_batch_id}' ";
$where_batch 	= " and ba.batch_id = '{$today_batch_id}' ";
$where_resulted = " and ba.batch_id = '{$today_batch_id}' and re.estatus = 'C' ";



$where_pending24h = " and ba.batch_id > 0 and ba.spec_received <= '{$past_24h}' and re.id is null";
$where_pending24h .= " and " . tr_pdd_where_batch_rejected(false, true, '', ['archived', 'cancelled']);
$limit_pending_date     = date('Y-m-d', strtotime('-60days'));
$where_pending24h  .= " and ba.doc >'{$limit_pending_date}' ";


$where_rejected = " and ba.batch_id = '{$today_batch_id}' ";
$where_rejected .= " and " . tr_pdd_where_batch_rejected(false, false);
$where_rerun 	= " and ba.batch_id = '{$today_batch_id}' and re.rerun_count > 0 ";

if (!empty($archive_date)) {
	$archive_bid 		= str_replace('-', '', $archive_date);
	$where_inbound 		.= " and ba.doc > '{$archive_date}' ";
	$where_pending24h 	.= " and ba.doc > '{$archive_date}' ";
	$where_not_received .= " and ba.doc > '{$archive_date}' ";
}

$days_of_operation_lab = CI_Base_Lab::get_lab_operations();

function count_not_received_over_24h($btype, $archive_date, $days_of_operation_lab)
{
	global $wpdb;
	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$date_prev_1month = date('Y-m-01', strtotime('-1 months', strtotime(date_i18n('Y-m-d'))));
	$where = " AND ba.doc >= '{$date_prev_1month}' AND " . tr_pdd_where_batch_rejected(false, true, $btype, ['deleted', 'archived', 'cancelled']);

	if (!empty($archive_date) && $date_prev_1month < $archive_date) {
		$where .= " AND ba.doc > '{$archive_date}' ";
	}
	$sql = "select ba.doc, ba.lab_ID
		from {$batch_tbl} ba 
		WHERE ba.is_saved = 0 and ba.batch_id = 0 {$where} ";
	$rs_batches = $wpdb->get_results($sql);

	if (empty($rs_batches)) return 0;

	$count = 0;

	foreach ($rs_batches as $b) {
		$completed_time = date_i18n('Y-m-d H:i:s');
		$age_in_hours = floor(CI_Base_Lab::calculate_business_hours($b->doc, $completed_time, $b->lab_ID, $days_of_operation_lab));
		if ($age_in_hours >= 24) $count += 1;
	}

	return $count;
}



$report = array(
	'inbound' 		=> 0,
	'received' 		=> 0,
	'in_processing' => 0,
	'resulted' 		=> 0,
	'not_received' 	=> 0,
	'pending_24h' 	=> 0,
	'rejected' 		=> 0,
	'rerun' 		=> 0,
);

foreach (tr_pdd_get_batch_types() as $btype) {
	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);

	$report['inbound']  += $wpdb->get_var("select count(*) from {$batch_tbl} ba WHERE ba.is_saved = 0 {$where_inbound}");

	$report['received'] += $wpdb->get_var("select count(*) from {$batch_tbl} ba where ba.is_saved = 0  {$where_received}");



	$report['in_processing'] += $wpdb->get_var("select count(distinct(ba.mid))
							from {$batch_tbl} ba 
							LEFT join {$report_tbl} re on ba.assen = re.patient_id and re.estatus = 'C'
                            WHERE ba.is_saved = 0 
							and re.id is null
							and ba.batch_id = '{$today_batch_id}' 
							and ba.batch_status in ('plated')  ");

	$report['resulted'] += $wpdb->get_var("select count(*) FROM (select mid from {$batch_tbl} ba 
                                                  JOIN {$report_tbl} re ON ba.assen = re.patient_id
                                                  where 1  {$where_resulted}
                                                  group by ba.mid) as rs ");

	$report['not_received'] += count_not_received_over_24h($btype, $archive_date, $days_of_operation_lab);

	$report['pending_24h'] 	+= $wpdb->get_var("select count(*) from {$batch_tbl} ba 
							Left JOIN {$report_tbl} re ON ba.assen = re.patient_id and re.estatus = 'C'
							WHERE ba.is_saved = 0 {$where_pending24h}");

	$report['rejected'] 	+= $wpdb->get_var("select count(*) from {$batch_tbl} ba WHERE ba.is_saved = 0 {$where_rejected}");
	if ($btype == 'pcr') {
		$report['rerun'] 		+= $wpdb->get_var("select count(*) from {$batch_tbl} ba 
							JOIN {$report_tbl} re ON ba.assen = re.patient_id 
							WHERE ba.is_saved = 0 {$where_rerun}");
	}
}

$not_received_24h_link 	= 'admin.php?page=ci_reports_pending_and_outstanding&sel_bucke=over_24&sel_status=not_received';
$pending_24h_link 		= 'admin.php?page=ci_reports_pending_and_outstanding&sel_bucke=over_24&sel_status=pending';
if (!tr_get_option('ci_pdf_data', 'new_reports')) {
	$not_received_24h_link 	= 'admin.php?page=ci_datareport_pending_and_outstanding&sel_bucket=over_24&sel_status=not_received';
	$pending_24h_link 		= 'admin.php?page=ci_datareport_pending_and_outstanding&sel_bucket=over_24&sel_status=pending';
}

?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
	<div onclick="window.location.href='admin.php?page=ci_batches_view'" data-slot=card
		class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card hover:shadow-card-hover transition-shadow-smooth cursor-pointer">
		<div data-slot=card-header
			class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1">
			<h4 data-slot=card-title class="text-base font-semibold">Inbound</h4>
			<div class="p-1.5 bg-primary/10 rounded-lg"><svg xmlns=http://www.w3.org/2000/svg width=24
					height=24 viewBox="0 0 24 24" fill=none stroke=currentColor stroke-width=2
					stroke-linecap=round stroke-linejoin=round
					class="lucide lucide-test-tube h-4 w-4 text-primary">
					<path d="M14.5 2v17.5c0 1.4-1.1 2.5-2.5 2.5c-1.4 0-2.5-1.1-2.5-2.5V2"></path>
					<path d="M8.5 2h7"></path>
					<path d="M14.5 16h-5"></path>
				</svg></div>
		</div>
		<div data-slot=card-content class="px-6 [&amp;:last-child]:pb-6 pb-2">
			<div class="text-2xl font-bold"><?php echo $report['inbound'] ?></div>
			<p class="text-xs text-muted-foreground">Incoming orders</p>
		</div>
	</div>
	<div onclick="window.location.href='admin.php?page=ci_receiving&tab=list_receiving&date=<?php echo date_i18n('Y-m-d') ?>&month&assen'" data-slot=card
		class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card hover:shadow-card-hover transition-shadow-smooth cursor-pointer">
		<div data-slot=card-header
			class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1">
			<h4 data-slot=card-title class="text-base font-semibold">Received</h4>
			<div class="p-1.5 bg-blue-50 rounded-lg"><svg xmlns=http://www.w3.org/2000/svg width=24 height=24
					viewBox="0 0 24 24" fill=none stroke=currentColor stroke-width=2 stroke-linecap=round
					stroke-linejoin=round class="lucide lucide-circle-check-big h-4 w-4 text-blue-600">
					<path d="M21.801 10A10 10 0 1 1 17 3.335"></path>
					<path d="m9 11 3 3L22 4"></path>
				</svg></div>
		</div>
		<div data-slot=card-content class="px-6 [&amp;:last-child]:pb-6 pb-2">
			<div class="text-2xl font-bold"><?php echo $report['received'] ?></div>
			<p class="text-xs text-muted-foreground">Arrived at the lab</p>
		</div>
	</div>
	<div onclick="window.location.href='admin.php?page=ci_tecan_processing&tab=plates&date=<?php echo date_i18n('Y-m-d') ?>'" data-slot=card
		class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card hover:shadow-card-hover transition-shadow-smooth cursor-pointer">
		<div data-slot=card-header
			class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1">
			<h4 data-slot=card-title class="text-base font-semibold">In Process</h4>
			<div class="p-1.5 bg-amber-50 rounded-lg"><svg xmlns=http://www.w3.org/2000/svg width=24 height=24
					viewBox="0 0 24 24" fill=none stroke=currentColor stroke-width=2 stroke-linecap=round
					stroke-linejoin=round class="lucide lucide-clock h-4 w-4 text-amber-600">
					<circle cx=12 cy=12 r=10></circle>
					<polyline points="12 6 12 12 16 14"></polyline>
				</svg></div>
		</div>
		<div data-slot=card-content class="px-6 [&amp;:last-child]:pb-6 pb-2">
			<div class="text-2xl font-bold"><?php echo $report['in_processing'] ?><span class="text-gray-300"><!--/--><?php //echo $report['received'] 
																														?></span></div>
			<p class="text-xs text-muted-foreground">Plating/Processing</p>
		</div>
	</div>
	<div onclick="window.location.href='admin.php?page=pcr_results'" data-slot=card
		class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card hover:shadow-card-hover transition-shadow-smooth cursor-pointer">
		<div data-slot=card-header
			class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1">
			<h4 data-slot=card-title class="text-base font-semibold">Completed</h4>
			<div class="p-1.5 bg-green-50 rounded-lg"><svg xmlns=http://www.w3.org/2000/svg width=24 height=24
					viewBox="0 0 24 24" fill=none stroke=currentColor stroke-width=2 stroke-linecap=round
					stroke-linejoin=round class="lucide lucide-circle-check-big h-4 w-4 text-green-600">
					<path d="M21.801 10A10 10 0 1 1 17 3.335"></path>
					<path d="m9 11 3 3L22 4"></path>
				</svg></div>
		</div>
		<div data-slot=card-content class="px-6 [&amp;:last-child]:pb-6 pb-2">
			<div class="text-2xl font-bold"><?php echo $report['resulted'] ?><span class="text-gray-300">/<?php echo $report['received'] ?></span></div>
			<p class="text-xs text-muted-foreground">Results approved</p>
		</div>
	</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
	<div onclick="window.location.href='<?php echo $not_received_24h_link ?>'" data-slot=card
		class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card hover:shadow-card-hover transition-shadow-smooth border-red-100 cursor-pointer">
		<div data-slot=card-header
			class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1">
			<h4 data-slot=card-title class="text-base font-semibold">Not Received 24h</h4>
			<div class="p-1.5 bg-orange-50 rounded-lg"><svg xmlns=http://www.w3.org/2000/svg width=24
					height=24 viewBox="0 0 24 24" fill=none stroke=currentColor stroke-width=2
					stroke-linecap=round stroke-linejoin=round
					class="lucide lucide-triangle-alert h-4 w-4 text-orange-600">
					<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path>
					<path d="M12 9v4"></path>
					<path d="M12 17h.01"></path>
				</svg></div>
		</div>
		<div data-slot=card-content class="px-6 [&amp;:last-child]:pb-6 pb-2">
			<div class="text-2xl font-bold text-orange-700"><?php echo $report['not_received'] ?></div>
			<p class="text-xs text-muted-foreground">Over 24h since collection</p>
		</div>
	</div>
	<div onclick="window.location.href='<?php echo $pending_24h_link ?>'" data-slot=card
		class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card hover:shadow-card-hover transition-shadow-smooth border-red-100 cursor-pointer">
		<div data-slot=card-header
			class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1">
			<h4 data-slot=card-title class="text-base font-semibold">Pending 24h</h4>
			<div class="p-1.5 bg-red-50 rounded-lg"><svg xmlns=http://www.w3.org/2000/svg width=24 height=24
					viewBox="0 0 24 24" fill=none stroke=currentColor stroke-width=2 stroke-linecap=round
					stroke-linejoin=round class="lucide lucide-clock h-4 w-4 text-red-600">
					<circle cx=12 cy=12 r=10></circle>
					<polyline points="12 6 12 12 16 14"></polyline>
				</svg></div>
		</div>
		<div data-slot=card-content class="px-6 [&amp;:last-child]:pb-6 pb-2">
			<div class="text-2xl font-bold text-red-700"><?php echo $report['pending_24h'] ?></div>
			<p class="text-xs text-muted-foreground">Over 24h since receipt</p>
		</div>
	</div>
	<div onclick="window.location.href='admin.php?page=ci_batches_view&tab=specimen_issues'" data-slot=card
		class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card hover:shadow-card-hover transition-shadow-smooth border-red-100 cursor-pointer">
		<div data-slot=card-header
			class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1">
			<h4 data-slot=card-title class="text-base font-semibold">Rejected</h4>
			<div class="p-1.5 bg-red-50 rounded-lg"><svg xmlns=http://www.w3.org/2000/svg width=24 height=24
					viewBox="0 0 24 24" fill=none stroke=currentColor stroke-width=2 stroke-linecap=round
					stroke-linejoin=round class="lucide lucide-triangle-alert h-4 w-4 text-red-600">
					<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path>
					<path d="M12 9v4"></path>
					<path d="M12 17h.01"></path>
				</svg></div>
		</div>
		<div data-slot=card-content class="px-6 [&amp;:last-child]:pb-6 pb-2">
			<div class="text-2xl font-bold text-red-700"><?php echo $report['rejected'] ?></div>
			<p class="text-xs text-muted-foreground">Quality issues</p>
		</div>
	</div>
	<div onclick="window.location.href='admin.php?page=pcr_results&tab=incomplete&panelid'" data-slot=card
		class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card hover:shadow-card-hover transition-shadow-smooth border-red-100 cursor-pointer">
		<div data-slot=card-header
			class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1">
			<h4 data-slot=card-title class="text-base font-semibold">Rerun</h4>
			<div class="p-1.5 bg-yellow-50 rounded-lg"><svg xmlns=http://www.w3.org/2000/svg width=24
					height=24 viewBox="0 0 24 24" fill=none stroke=currentColor stroke-width=2
					stroke-linecap=round stroke-linejoin=round
					class="lucide lucide-activity h-4 w-4 text-yellow-600">
					<path
						d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2">
					</path>
				</svg></div>
		</div>
		<div data-slot=card-content class="px-6 [&amp;:last-child]:pb-6 pb-2">
			<div class="text-2xl font-bold text-red-700"><?php echo $report['rerun'] ?></div>
			<p class="text-xs text-muted-foreground">QC Failure</p>
		</div>
	</div>
</div>