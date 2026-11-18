<?php
global $wpdb, $current_role;
$bm = isset($_REQUEST['bm']) ? $_REQUEST['bm'] : 1;

if ($bm != 0) {
	$user_id = get_current_user_id();
	$today = date_i18n("Y-m-d");
	$from_date = date('Y-m-d', strtotime("-{$bm} months"));
	$bm1 = $bm + 1;
	$from_date1 = date('Y-m-d', strtotime("-{$bm1} months"));

	$where = '';
	if (!tr_current_role_in(array('lab', 'AdminCIDW', 'administrator', 'administrator_p', 'labmanager'))) {
		//find rep in group
		$rep_ids = array();
		$users_reps = tr_dpp_get_users_in_group($user_id);
		foreach ($users_reps as $uid) {
			$rid = get_user_meta($uid, '_rep_id', true);
			if ($rid > 0) {
				$rep_ids[] = $rid;
			}
		}
		if ($current_role == 'distributor') {
			$rep_ids2 = $wpdb->get_col("select rep.rep_ID from ci_lab_reps AS rep 
                                    JOIN ci_distributors dis ON dis.dis_ID = rep.rep_distributor_ID
                                    WHERE dis.dis_user_ID = {$user_id}");
			if (count($rep_ids2) > 0) {
				$rep_ids = array_merge($rep_ids, $rep_ids2);
			}
		}

		if (count($rep_ids) > 0) {
			$rep_ids = implode(',', $rep_ids);
			$where .= " and cli.`cli_rep-ID` in ({$rep_ids}) ";
		} else {
			switch ($current_role) {
				case 'lab_rep':
				case 'lab_rep_w2':
					$rep_id = get_user_meta($user_id, '_rep_id', true);
					$where .= " and cli.`cli_rep-ID` = {$rep_id} ";
					break;
				case 'office_manager':
				case 'multi_site_manager':
					$cids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID = '{$user_id}'");
					$cids = implode(',', $cids);
					$where .= " and cli.cID in ({$cids})";
					break;
				default:
					$where .= " and 1=2";
					break;
			}
		}
	}

	$childq = array();
	$cli_IDs = array(0);
	$not_cids = array(0);
	foreach (tr_pdd_get_batch_types() as $btype) {
		$batch_tbl = tr_pdd_get_table_by_btype($btype);

		$cids = $wpdb->get_col("select distinct(ba.cli_ID) from {$batch_tbl} ba 
	                                where ba.spec_received < '{$from_date}' 
                                     and ba.spec_received>= '$from_date1' 
                        ");
		if (count($cids) > 0) {
			$cli_IDs = array_merge($cli_IDs, $cids);
		}

		$nocid = $wpdb->get_col("select distinct(ba.cli_ID) from {$batch_tbl} ba 
                            where ba.spec_received >= '{$from_date}'  
                        ");
		if (count($nocid) > 0) {
			$not_cids = array_merge($not_cids, $nocid);
		}
	}

	$cli_IDs = implode(',', $cli_IDs);
	$not_cids = implode(',', $not_cids);
	$where .= " and cli.cID in ({$cli_IDs}) ";
	$where .= " and cli.cID not in ({$not_cids}) ";

	$sql = "select cli.*,
                    rep.rep_first,
                    rep.rep_last,
                    (select group_concat(ma_manager) from ci_clinic_manager cm where cm.ma_cli_ID = cli.cID) as office_manager
                    
                    from ci_clinic cli 
                        left join ci_lab_reps rep ON rep.rep_ID = cli.`cli_rep-ID`
                        where  cli.cli_status = 1
                        {$where}
                        order by cli.cli_clinic asc";
	$rows = $wpdb->get_results($sql);
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
						<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
							stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
							class="lucide lucide-chevron-right">
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
						class="[&amp;&gt;svg]:size-3.5">
						<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
							stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
							class="lucide lucide-chevron-right">
							<path d="m9 18 6-6-6-6"></path>
						</svg>
					</li>

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
							</svg><span class="truncate max-w-[120px] sm:max-w-none">Lost Providers</span></span></li>
				</ol>
			</nav>
		</div>
		<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
			<div>
				<h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">
					Lost Providers</h1>
				<p class="text-muted-foreground mt-1">Track providers who have reduced or stopped ordering tests</p>
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
									<p class="text-sm text-muted-foreground">Total</p>
									<p class="text-2xl font-bold"><?php echo isset($rows) ? count($rows) : 0 ?></p>
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

				</div>
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
										</svg></div>Volume by Lost Providers<span
										class="text-sm text-muted-foreground ml-2">(Last <?php echo $bm; ?>
										month)</span>
								</h4>
							</div>

						</div>
						<div class="flex flex-wrap items-center gap-3 mt-4">

							<select id="sel_mb" name="bm" class="njs_select2">
								<?php

								for ($i = 1; $i <= 12; $i++) {
									$selected = $bm && $i == $bm ? 'selected' : '';
									$txt = $i > 1 ? $i . ' Months' : $i . ' Month';
									echo '<option value="' . $i . '" ' . $selected . '>' . $txt . '</option>';
								}
								?>
							</select>

						</div>
					</div>
					<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-6">
						<div class="border rounded-lg overflow-hidden">
							<div class="overflow-x-auto">
								<table class="w-full text-sm">
									<thead class="bg-gray-100 border-b">
										<tr>
											<th class="px-4 py-3 text-left font-medium text-gray-800">Clinic</th>
											<th class="px-4 py-3 text-center font-medium text-gray-800">Address</th>
											<th class="px-4 py-3 text-center font-medium text-gray-800">Phone</th>
											<th class="px-4 py-3 text-center font-medium text-gray-800">Office Manager
											</th>
											<th class="px-4 py-3 text-center font-medium text-gray-800">Salesperson</th>
											<th class="px-4 py-3 text-center font-medium text-gray-800">Last Date</th>
										</tr>
									</thead>
									<tbody class="divide-y divide-gray-100">
										<?php
										foreach ($rows as $row) {
											$last_date = '';
											$childq = array();
											foreach (tr_pdd_get_batch_types() as $btype) {
												$batch_tbl = tr_pdd_get_table_by_btype($btype);
												$childq[] = "(select spec_received from {$batch_tbl} ba
	                                 where ba.cli_ID = {$row->cID} 
	                                 order by ba.spec_received desc
	                                 limit 1)";
											}
											$childq = implode(' UNION ALL ', $childq);
											$last_date = $wpdb->get_var("select spec_received from ({$childq}) r 
					                                order by spec_received desc 
					                                limit 1");
										?>
											<tr class="hover:bg-gray-50 ">
												<td class="px-4 py-3 font-medium text-gray-900">
													<?php echo $row->cli_clinic ?>
												</td>
												<td class="px-4 py-3 text-center font-medium text-gray-900">
													<?php echo $row->cli_street . ' ' . $row->cli_city . ' ' . $row->cli_state . ' ' . $row->cli_zip ?>
												</td>
												<td class="px-4 py-3 text-center font-medium text-gray-900">
													<?php echo $row->{'cli_om-phone'} ?>
												</td>
												<td class="px-4 py-3 text-center font-medium text-gray-900">
													<?php echo $row->office_manager ?>
												</td>
												<td class="px-4 py-3 text-center font-medium text-gray-900">
													<?php echo $row->rep_first . ' ' . $row->rep_last ?>
												</td>
												<td class="px-4 py-3 text-center font-medium text-gray-900">
													<?php echo $last_date ?>
												</td>
											</tr>
										<?php
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

<script>
	var sel_mb = '<?php echo $bm; ?>';

	function goto_url() {
		var $url = location.href;
		$url += '&bm=' + sel_mb;
		window.location.href = $url;
	}

	jQuery(function($) {
		window.$ = jQuery;
		update_ui_select();
		$("#sel_mb").change(function() {
			sel_mb = $(this).val();
			goto_url();
		});
	});
</script>
