<?php
global $wpdb;

$summary = isset($_REQUEST['summary']) ? $_REQUEST['summary'] : '';
$date_from = isset($_REQUEST['date_from']) ? $_REQUEST['date_from'] : '';
$date_to = isset($_REQUEST['date_to']) ? $_REQUEST['date_to'] : '';

wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');


$results = array();
if ($date_from && $date_to) {
	$where = " where ba.doc >= '{$date_from}' and ba.doc <= '{$date_to} 23:59:59' ";
	$where .= " and cli.cli_report_by_collector = 1 and col.col_middle!='' ";

	$child_q = array();
	foreach (tr_pdd_get_batch_types() as $btype) {
		$batch_tbl = tr_pdd_get_table_by_btype($btype);
		$child_q[] = "(SELECT
			cli.cID,
		    cli.cli_clinic,
		    col.col_ID,
			col.col_middle,
			DATE_FORMAT(ba.doc,'%Y-%m-%d') AS doc_date,
            count(*) as count1
           
            from {$batch_tbl} ba
            JOIN ci_collector col ON col.col_ID = ba.col_ID
            JOIN ci_clinic cli ON cli.cId = ba.cli_ID
             {$where}
            GROUP BY cli.cID, col.col_ID, doc_date)";
	}
	$child_q = implode(" UNION ALL ", $child_q);
	$sql = "
	    select *,
	        sum(count1) as count_all
            from ({$child_q}) ba
         group by cID, col_ID, doc_date
         order by doc_date asc
         ";
	$results = $wpdb->get_results($sql);

	$clinics = array();
	foreach ($results as $row) {
		if (!isset($clinics[$row->cID])) {
			$clinics[$row->cID] = array(
				'name' => $row->cli_clinic,
				'rows' => array()
			);
		}

		if (!isset($clinics[$row->cID]['rows'][$row->col_ID])) {
			$clinics[$row->cID]['rows'][$row->col_ID] = array(
				'name' => $row->col_middle,
				'count' => 0,
				'rows' => array()
			);
		}

		$clinics[$row->cID]['rows'][$row->col_ID]['rows'][] = $row;
		$clinics[$row->cID]['rows'][$row->col_ID]['count'] += $row->count_all;
	}

	if (isset($_GET['debug'])) {
		var_dump($sql);
		echo '<br><br>';
		var_dump($results);
	}
}


?>

<script>
	jQuery(function($) {
		$("#from").datepicker({
			changeMonth: true,
			changeYear: true,
			numberOfMonths: 1,
			dateFormat: 'yy-mm-dd',
			onClose: function(selectedDate) {
				$("#to").datepicker("option", "minDate", selectedDate);
			}
		});
		$("#to").datepicker({
			changeMonth: true,
			changeYear: true,
			numberOfMonths: 1,
			dateFormat: 'yy-mm-dd',
			onClose: function(selectedDate) {
				$("#from").datepicker("option", "maxDate", selectedDate);
			}
		});
	})
</script>
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

<div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
	<div class="space-y-6">
		<div class="bg-white border rounded-lg px-3 sm:px-6 py-2 sm:py-3 shadow-sm">
			<nav aria-label="breadcrumb" data-slot="breadcrumb">
				<ol data-slot="breadcrumb-list"
					class="text-muted-foreground flex items-center gap-1.5 text-sm break-words sm:gap-2.5 flex-wrap">
					<li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5"><a
							data-slot="breadcrumb-link"
							class="hover:text-foreground transition-colors flex items-center gap-1 text-xs sm:text-sm"
							href="#"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
								fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
								stroke-linejoin="round" class="lucide lucide-house h-3 w-3" aria-hidden="true">
								<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"></path>
								<path
									d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z">
								</path>
							</svg><span class="truncate max-w-[120px] sm:max-w-none">Dashboard</span></a></li>
					<li data-slot="breadcrumb-separator" role="presentation" aria-hidden="true"
						class="[&amp;&gt;svg]:size-3.5"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
							viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
							stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right">
							<path d="m9 18 6-6-6-6"></path>
						</svg></li>
					<li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5"><a
							data-slot="breadcrumb-link"
							class="hover:text-foreground transition-colors flex items-center gap-1 text-xs sm:text-sm"
							href="#"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
								fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
								stroke-linejoin="round" class="lucide lucide-file-text h-3 w-3" aria-hidden="true">
								<path
									d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z">
								</path>
								<path d="M14 2v5a1 1 0 0 0 1 1h5"></path>
								<path d="M10 9H8"></path>
								<path d="M16 13H8"></path>
								<path d="M16 17H8"></path>
							</svg><span class="truncate max-w-[120px] sm:max-w-none">Reports</span></a></li>
					<li data-slot="breadcrumb-separator" role="presentation" aria-hidden="true"
						class="[&amp;&gt;svg]:size-3.5"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
							viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
							stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right">
							<path d="m9 18 6-6-6-6"></path>
						</svg></li>
					<li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5"><span
							data-slot="breadcrumb-page" role="link" aria-disabled="true" aria-current="page"
							class="text-foreground font-normal flex items-center gap-1 text-xs sm:text-sm"><svg
								xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
								fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
								stroke-linejoin="round" class="lucide lucide-chart-column h-3 w-3" aria-hidden="true">
								<path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
								<path d="M18 17V9"></path>
								<path d="M13 17V5"></path>
								<path d="M8 17v-3"></path>
							</svg><span class="truncate max-w-[120px] sm:max-w-none">Collector Initial
								Report</span></span></li>
				</ol>
			</nav>
		</div>
		<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
			<div>
				<h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">
					Collector Initial Report</h1>
				<p class="text-muted-foreground mt-1">Sample volume analysis by Collector Initial
					Report</p>
			</div>
		</div>
		<div>
			<div class="space-y-5">

				<div data-slot="card"
					class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
					<div data-slot="card-header"
						class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 pb-3">
						<div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
							<div>
								<h4 data-slot="card-title" class="leading-none flex items-center gap-2">
									<div class="p-1.5 bg-blue-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
											width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
											stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
											class="lucide lucide-chart-column h-4 w-4 text-blue-600" aria-hidden="true">
											<path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
											<path d="M18 17V9"></path>
											<path d="M13 17V5"></path>
											<path d="M8 17v-3"></path>
										</svg></div>Volume by Collector Initial Report
								</h4>
							</div>
						</div>
						<div class="flex flex-wrap items-center gap-3 mt-4">

							<form method="get">
								<input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
								<input type="hidden" name="tab"
									value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />
								<label>From: </label>
								<input type="text" name="date_from" id="from" value="<?php echo $date_from ?>" />
								<label>To: </label>
								<input type="text" name="date_to" id="to" value="<?php echo $date_to ?>" />

								<label><input type="checkbox" name="summary" value="1" <?php checked($summary) ?> />
									Summary </label>

								<input type="submit" value="Run Report" class="button bsty6" />
							</form>

						</div>
					</div>
					<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-6">
						<div class="border rounded-lg overflow-hidden">
							<div class="overflow-x-auto">
								<?php
								if (isset($clinics)) {
									if (count($clinics) > 0) {
										foreach ($clinics as $cid => $arr) {
								?>
											<table class="clinic w-full text-sm" style="width:250px;margin-bottom:30px">
												<tr>
													<th colspan="2" class="px-4 py-3 text-left font-medium text-gray-800"
														style="text-align: left"><?php echo $arr['name'] ?></th>
												</tr>
												<?php
												foreach ($arr['rows'] as $colid => $col_arr) {
													echo '<tr class="hover:bg-gray-50"><td class="px-4 py-3 font-medium ">' . $col_arr['name'] . '</td><td class="px-4 py-3 text-center  font-medium">' . $col_arr['count'] . '</td></tr>';

													if (!$summary) {
														foreach ($col_arr['rows'] as $row) {
															echo '<tr class="hover:bg-gray-50"><td class="px-4 py-3 text-center font-medium">' . $row->doc_date . '</td><td class="px-4 py-3 text-center font-medium">' . $row->count_all . '</td></tr>';
														}
														echo '<tr><td colspan=2>&nbsp;</td></tr>';
													}
												}
												?>
											</table>
								<?php
										}
									} else {
										echo '<p class="center"> Not found</p>';
									}
								}
								?>
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