<?php
global $wpdb;

$from_date = isset($_REQUEST['from']) ? $_REQUEST['from'] : '';
$to_date = isset($_REQUEST['to']) ? $_REQUEST['to'] : '';
$sel_date_rep = isset($_REQUEST['sel_date_rep']) ? $_REQUEST['sel_date_rep'] : '90_days';

$text_sel_date_rep = 'Customer';
if ($sel_date_rep === 'custom') {
	$from_date = !empty($_GET['from']) ? $_GET['from'] : date_i18n('Y-m-01');
	$to_date = !empty($_GET['to']) ? $_GET['to'] : date_i18n('Y-m-d');
	$text_sel_date_rep = date_i18n('M j', strtotime($from_date)) . " - " . date_i18n('M j', strtotime($to_date));
} else {
	$to_date = date_i18n('Y-m-d');
	switch ($sel_date_rep) {
		case '7_days':
			$text_sel_date_rep = 'Last 7 days';
			$from_date = date_i18n('Y-m-d', strtotime('-7 days', strtotime($to_date)));
			break;
		case '14_days':
			$text_sel_date_rep = 'Last 14 days';
			$from_date = date_i18n('Y-m-d', strtotime('-14 days', strtotime($to_date)));
			break;
		case '30_days':
			$text_sel_date_rep = 'Last 30 days';
			$from_date = date_i18n('Y-m-d', strtotime('-30 days', strtotime($to_date)));
			break;
		case '90_days':
			$text_sel_date_rep = 'Last 90 days';
			$from_date = date_i18n('Y-m-d', strtotime('-90 days', strtotime($to_date)));
			break;
		default:
			$from_date = date_i18n('Y-m-01');
			break;
	}
}


$filter_date_select = [];
$filter_date_select[] = ['value' => '7_days', 'text' => '7 days'];
$filter_date_select[] = ['value' => '14_days', 'text' => '14 days'];
$filter_date_select[] = ['value' => '30_days', 'text' => '30 days'];
$filter_date_select[] = ['value' => '90_days', 'text' => '90 days'];
$filter_date_select[] = ['value' => 'custom', 'text' => 'Custom'];

$results = array();
if ($from_date && $to_date) {
	$where = " where ba.doc >= '{$from_date}' and ba.doc <= '{$to_date} 23:59:59' ";
	$where .= " and ba.cli_ID > 0 ";

	$batch_statuses = tr_pdd_get_batch_statuses(false, true);
	$rejected_status = array();
	foreach ($batch_statuses as $k => $vl) {
		$rejected_status[] = $k;
	}
	$rejected_status = implode("','", $rejected_status);



	$rows = array();
	foreach (tr_pdd_get_batch_types() as $btype) {
		$batch_tbl = tr_pdd_get_table_by_btype($btype);
		$report_tbl = tr_pdd_get_table_report_by_btype($btype);

		$sql = "
			select count(*) as count_req,
				sum(IF(ba.doc>'2000-01-01',1,0)) as count_doc,
				sum(IF(ba.spec_received>'2000-01-01',1,0)) as count_rec,
				sum(IF(re.estatus='C',1,0)) as count_rs,
				SUM(IF(batch_status in ('{$rejected_status}'), 1, 0)) as count_rejected
            from {$batch_tbl} ba 
            LEFT JOIN {$report_tbl} re ON ba.assen = re.patient_id
             {$where} ";

		$row = $wpdb->get_row($sql);
		if ($row && $row->count_req > 0) {
			$rows[$btype] = array(
				'count_req' => $row->count_req,
				'count_doc' => $row->count_doc,
				'count_rec' => $row->count_rec,
				'count_rs' => $row->count_rs,
				'count_rejected' => $row->count_rejected,
			);
		}
	}
}

$total = array(
	'count_req' => 0,
	'count_doc' => 0,
	'count_rec' => 0,
	'count_rs' => 0,
	'count_rejected' => 0
);

if (isset($rows) && count($rows) > 0) {

	foreach ($rows as $btype => $row) {
		$total['count_req'] += $row['count_req'];
		$total['count_doc'] += $row['count_doc'];
		$total['count_rec'] += $row['count_rec'];
		$total['count_rs'] += $row['count_rs'];
		$total['count_rejected'] += $row['count_rejected'];
	}
}

?>

<style>
	h1,
	h2,
	h3,
	h4,
	h5,
	h6 {
		font-weight: unset !important;
		font-size: inherit;
		margin-bottom: 6px !important;
	}

	h1,
	h4 {
		margin: unset;
	}

	h1 {
		margin-bottom: 2px;
	}

	h4 {
		margin-bottom: 5px;
	}

	h5 {
		font-weight: var(--font-weight-medium);
		margin: 0px;
		--tw-font-weight: var(--font-weight-medium);
		font-size: 13px;
		margin-bottom: 10px;
	}

	.text-primary {
		color: var(--primary);
	}

	ol {
		list-style-type: none;
		margin: 0px;
		margin-top: px;
		padding-top: 5px;
		padding-left: 5px;
	}

	ol li a {
		color: unset;
	}


	.text-2xl {
		font-size: var(--text-2xl);
		line-height: var(--tw-leading, var(--text-2xl--line-height));
	}
</style>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?' . TRCPDD_VS ?>"></script>
<div class="wrap">
	<div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
		<div class="space-y-6">
			<div class="bg-white border rounded-lg px-3 sm:px-6 py-2 sm:py-3 shadow-sm">
				<nav aria-label="breadcrumb" data-slot="breadcrumb">
					<ol data-slot="breadcrumb-list"
						class="text-muted-foreground flex items-center gap-1.5 text-sm break-words sm:gap-2.5 flex-wrap">
						<li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5">
							<a data-slot="breadcrumb-link"
								class="hover:text-foreground transition-colors flex items-center gap-1 text-xs sm:text-sm"
								href="?page=dashboard_main">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
									fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
									stroke-linejoin="round" class="lucide lucide-house h-3 w-3" aria-hidden="true">
									<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"></path>
									<path
										d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z">
									</path>
								</svg>
								<span class="truncate max-w-[120px] sm:max-w-none">Dashboard</span>
							</a>
						</li>

						<li data-slot="breadcrumb-separator" role="presentation" aria-hidden="true"
							class="[&amp;&gt;svg]:size-3.5">
							<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
								fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
								stroke-linejoin="round" class="lucide lucide-chevron-right">
								<path d="m9 18 6-6-6-6"></path>
							</svg>
						</li>

						<li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5">
							<a data-slot="breadcrumb-link"
								class="hover:text-foreground transition-colors flex items-center gap-1 text-xs sm:text-sm"
								href="?page=ci_reports_main">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
									fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
									stroke-linejoin="round" class="lucide lucide-file-text h-3 w-3" aria-hidden="true">
									<path
										d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z">
									</path>
									<path d="M14 2v5a1 1 0 0 0 1 1h5"></path>
									<path d="M10 9H8"></path>
									<path d="M16 13H8"></path>
									<path d="M16 17H8"></path>
								</svg>
								<span class="truncate max-w-[120px] sm:max-w-none">Reports</span>
							</a>
						</li>

						<li data-slot="breadcrumb-separator" role="presentation" aria-hidden="true"
							class="[&amp;&gt;svg]:size-3.5"><svg xmlns="http://www.w3.org/2000/svg" width="24"
								height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
								stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right">
								<path d="m9 18 6-6-6-6"></path>
							</svg></li>
						<li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5"><span
								data-slot="breadcrumb-page" role="link" aria-disabled="true" aria-current="page"
								class="text-foreground font-normal flex items-center gap-1 text-xs sm:text-sm">

								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
									fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
									stroke-linejoin="round" class="lucide lucide-chart-column h-3 w-3"
									aria-hidden="true">
									<path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
									<path d="M18 17V9"></path>
									<path d="M13 17V5"></path>
									<path d="M8 17v-3"></path>
								</svg>

								<span class="truncate max-w-[120px] sm:max-w-none">Lab Flow</span></span></li>
					</ol>
				</nav>
			</div>
			<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
				<div>
					<h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">
						Lab Flow</h1>
					<p class="text-muted-foreground mt-1">Sample flow through laboratory
						departments
						and processing stages</p>
				</div>
			</div>
			<div>
				<div class="space-y-5">
					<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
						<div data-slot="card"
							class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
							<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
								<div class="flex items-center justify-between">
									<div>
										<p class="text-sm text-muted-foreground">Requisitions</p>
										<p class="text-2xl font-bold"><?php echo $total['count_req'] ?></p>
									</div>
									<div class="p-2.5 bg-blue-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
											width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
											stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
											class="lucide lucide-chart-column h-4 w-4 text-blue-600" aria-hidden="true">
											<path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
											<path d="M18 17V9"></path>
											<path d="M13 17V5"></path>
											<path d="M8 17v-3"></path>
										</svg></div>
								</div>
							</div>
						</div>
						<div data-slot="card"
							class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
							<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
								<div class="flex items-center justify-between">
									<div>
										<p class="text-sm text-muted-foreground">Collected</p>
										<p class="text-2xl font-bold"><?php echo $total['count_doc'] ?></p>
									</div>
									<div class="p-2.5 bg-green-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
											width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
											stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
											class="lucide lucide-activity h-4 w-4 text-green-600" aria-hidden="true">
											<path
												d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2">
											</path>
										</svg></div>
								</div>
							</div>
						</div>
						<div data-slot="card"
							class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
							<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
								<div class="flex items-center justify-between">
									<div>
										<p class="text-sm text-muted-foreground">Received</p>
										<p class="text-2xl font-bold"><?php echo $total['count_rec'] ?></p>
									</div>
									<div class="p-2.5 bg-red-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
											width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
											stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
											class="lucide lucide-trending-up h-4 w-4 text-red-600" aria-hidden="true">
											<path d="M16 7h6v6"></path>
											<path d="m22 7-8.5 8.5-5-5L2 17"></path>
										</svg></div>
								</div>
							</div>
						</div>
						<div data-slot="card"
							class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
							<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
								<div class="flex items-center justify-between">
									<div>
										<p class="text-sm text-muted-foreground">Rejected</p>
										<p class="text-2xl font-bold"><?php echo $total['count_rejected'] ?></p>
									</div>
									<div class="p-2.5 bg-orange-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
											width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
											stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
											class="lucide lucide-file-text h-4 w-4 text-orange-600" aria-hidden="true">
											<path
												d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z">
											</path>
											<path d="M14 2v5a1 1 0 0 0 1 1h5"></path>
											<path d="M10 9H8"></path>
											<path d="M16 13H8"></path>
											<path d="M16 17H8"></path>
										</svg></div>
								</div>
							</div>
						</div>
					</div>
					<div data-slot="card"
						class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
						<div data-slot="card-header"
							class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 pb-3">
							<div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
								<div>
									<h4 data-slot="card-title" class="leading-none flex items-center gap-2">
										<div class="p-1.5 bg-blue-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
												width="24" height="24" viewBox="0 0 24 24" fill="none"
												stroke="currentColor" stroke-width="2" stroke-linecap="round"
												stroke-linejoin="round"
												class="lucide lucide-chart-column h-4 w-4 text-blue-600"
												aria-hidden="true">
												<path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
												<path d="M18 17V9"></path>
												<path d="M13 17V5"></path>
												<path d="M8 17v-3"></path>
											</svg></div>Volume by Lab Flow<span
											class="text-sm text-muted-foreground ml-2">(<?php echo $text_sel_date_rep; ?>)</span>
									</h4>
								</div>

							</div>
							<div class="flex flex-wrap items-center gap-3 mt-4">

								<select id="sel_date_rep" name="sel_date_rep">
									<?php foreach ($filter_date_select as $item): ?>
										<option <?php if ($item['value'] == $sel_date_rep) { ?>selected<?php } ?>
											value="<?php echo $item['value']; ?>">
											<?php echo $item['text']; ?>
										</option>
									<?php endforeach; ?>
								</select>


								<button id="btn_day_customer" data-slot="popover-trigger"
									class="items-center justify-center whitespace-nowrap font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 rounded-md gap-1.5 has-[&gt;svg]:px-2.5 h-8 px-2 text-sm inline-flex"
									title="Select custom date range" type="button" style="">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
										fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
										stroke-linejoin="round" class="lucide lucide-calendar h-3 w-3 mr-1"
										aria-hidden="true">
										<path d="M8 2v4"></path>
										<path d="M16 2v4"></path>
										<rect width="18" height="18" x="3" y="4" rx="2"></rect>
										<path d="M3 10h18"></path>
									</svg>
									<span class="txt_show_customer_day" data-slot="select-value"
										style="pointer-events:none"><?php echo date_i18n('M j', strtotime($from_date)) . " - " . date_i18n('M j', strtotime($to_date)); ?></span>
								</button>

							</div>
						</div>
						<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-6">
							<div class="border rounded-lg overflow-hidden">
								<div class="overflow-x-auto">
									<table class="w-full text-sm">
										<thead class="bg-gray-100 border-b">
											<tr>
												<th class="px-4 py-3 text-left font-medium text-gray-800">Type</th>
												<th class="px-4 py-3 text-center font-medium text-gray-800">Requisitions
												</th>
												<th class="px-4 py-3 text-center font-medium text-gray-800">Collected
												</th>
												<th class="px-4 py-3 text-center font-medium text-gray-800">Received
												</th>
												<th class="px-4 py-3 text-center font-medium text-gray-800">Resulted
												</th>
												<th class="px-4 py-3 text-center font-medium text-gray-800">Rejected
												</th>
											</tr>
										</thead>
										<tbody class="divide-y divide-gray-100">
											<tr class="hover:bg-gray-50 bg-gray-50">
												<td class="px-4 py-3  text-left  font-medium text-gray-900">Total</td>
												<td class="px-4 py-3 text-center font-medium text-gray-900">
													<?php echo $total['count_req'] ?>
												</td>
												<td class="px-4 py-3 text-center font-medium text-gray-900">
													<?php echo $total['count_doc'] ?>
												</td>
												<td class="px-4 py-3 text-center font-medium text-gray-900">
													<?php echo $total['count_rec'] ?>
												</td>
												<td class="px-4 py-3 text-center font-medium text-red-600">
													<?php echo $total['count_rs'] ?>
												</td>
												<td class="px-4 py-3 text-center font-medium text-red-600">
													<?php echo $total['count_rejected'] ?>
												</td>
											</tr>
											<?php
											if (isset($rows) && count($rows) > 0) {
												$total = array();
												foreach ($rows as $btype => $row) {
											?>
													<tr class="hover:bg-gray-50 ">
														<td class="px-4 py-3  text-left  font-medium text-gray-900">
															<?php echo ucfirst($btype) ?>
														</td>
														<td class="px-4 py-3 text-center font-medium text-gray-900">
															<?php echo $row['count_req'] ?>
														</td>
														<td class="px-4 py-3 text-center font-medium text-gray-900">
															<?php echo $row['count_doc'] ?>
														</td>
														<td class="px-4 py-3 text-center font-medium text-gray-900">
															<?php echo $row['count_rec'] ?>
														</td>
														<td class="px-4 py-3 text-center font-medium text-red-600">
															<?php echo $row['count_rs'] ?>
														</td>
														<td class="px-4 py-3 text-center font-medium text-red-600">
															<?php echo $row['count_rejected'] ?>
														</td>
													</tr>
											<?php
												}
											}
											?>
										</tbody>
									</table>
								</div>
							</div>
						</div>
					</div>

					<div class="bg-gray-50 border border-gray-200 rounded-lg py-4 px-4">
						<p class="text-xs text-gray-600 text-center">
							<strong style="display: block;padding-bottom:3px;">Ignite Laboratory Information System</strong>
							igniteLIS analytics reports are intended for authorized laboratory and administrative users. These reports provide aggregated operational data to support workflow monitoring, performance oversight, and applicable compliance or public-health reporting.
						</p>
					</div>

				</div>
			</div>
		</div>
	</div>
</div>

<script>
	var sel_date_rep = '<?php echo $sel_date_rep; ?>';
	var from_date = '<?php echo $from_date; ?>';
	var to_date = '<?php echo $to_date; ?>';


	function goto_url() {
		var $url = location.href;
		$url += '&sel_date_rep=' + sel_date_rep;
		$url += '&from=' + from_date;
		$url += '&to=' + to_date;
		window.location.href = $url;
	}

	jQuery(function($) {
		window.$ = jQuery;

		if (sel_date_rep != "custom") $("#btn_day_customer").removeClass("inline-flex").hide();

		update_ui_select();

		$("#sel_date_rep").change(function() {
			sel_date_rep = $(this).val();
			if (sel_date_rep == "custom") {
				$("#btn_day_customer").addClass("inline-flex").show();
				$("#btn_day_customer").data('daterangepicker').show();
			} else {
				$("#btn_day_customer").removeClass("inline-flex").hide();
				goto_url();
			}
		});

		$(".btn-export").on("click", function(e) {
			e.stopPropagation();
			var id_btn = $(this).attr("id");
			$("#menu_" + id_btn).toggle();
		});

		$(document).on("click", function(e) {
			if (!$(e.target).closest(".custom-select").length) {
				$(".custom-select .options").hide();
			}

			if (!$(e.target).closest(".menu-export").length) {
				$(".menu-export").hide();
			}
		});

		$('#btn_day_customer').daterangepicker({
				opens: 'right'
			},
			function(start, end, label) {
				$('.txt_show_customer_day').html(start.format('MMM D') + ' - ' + end.format('MMM D'));
				from_date = start.format('YYYY-MM-DD');
				to_date = end.format('YYYY-MM-DD');
				goto_url();
			}
		);

		$("#sel_clinic_rep").change(function() {
			cli_ID = $(this).val();
			goto_url();
		});

		$("#sel_btype").change(function() {
			btype = $(this).val();
			goto_url();
		});

		$("#sel_datetype").change(function() {
			date_type = $(this).val();
			goto_url();
		});

	})
</script>
