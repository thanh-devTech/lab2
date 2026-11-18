<?php
global $wpdb, $current_role;
$user_id            = get_current_user_id();



?>
<div id="box_daily_chart" data-slot=card class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
	<div data-slot=card-header
		class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 pb-3">
		<div class="flex items-center justify-between">
			<div class="grid-rows-[auto_auto]">
				<h4 data-slot="card-title" class="leading-none flex items-center gap-2">
					<div class="p-1.5 bg-primary/10 rounded-lg">
						<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chart-no-axes-column-increasing h-4 w-4 text-primary" aria-hidden="true">
							<path d="M5 21v-6"></path>
							<path d="M12 21V9"></path>
							<path d="M19 21V3"></path>
						</svg>
					</div>
					Daily Sample Volume
					<svg id="daily_loading" xmlns="http://www.w3.org/2000/svg" width="64" height="18" viewBox="0 0 64 18" aria-hidden="true" role="img">
						<circle cx="9" cy="9" r="6" fill="#6B7280">
							<animate attributeName="opacity" values="0.2;1;0.2" dur="1s" repeatCount="indefinite" begin="0s" />
						</circle>
						<circle cx="32" cy="9" r="6" fill="#6B7280">
							<animate attributeName="opacity" values="0.2;1;0.2" dur="1s" repeatCount="indefinite" begin="0.15s" />
						</circle>
						<circle cx="55" cy="9" r="6" fill="#6B7280">
							<animate attributeName="opacity" values="0.2;1;0.2" dur="1s" repeatCount="indefinite" begin="0.3s" />
						</circle>
					</svg>
				</h4>
				<div id="txtShowDayDaily" class="text-sm text-gray-600">Last 14 days</div>
			</div>

			<div class="flex items-center gap-3">
				<button id="btn_live_report_daily" data-slot="button" class="inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 text-muted-foreground hover:text-primary hover:bg-transparent">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-activity h-4 w-4 mr-1" aria-hidden="true">
						<path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"></path>
					</svg>
					<textshow>Live Report</textshow>
				</button>

				<div style="position:relative;display:inline-block;">
					<button id="daily_export_btn" data-slot="dropdown-menu-trigger"
						class="inline-flex items-center justify-center whitespace-nowrap text-sm font-medium disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 rounded-md gap-1.5 has-[&gt;svg]:px-2.5 h-7 px-2 text-muted-foreground hover:text-primary hover:bg-transparent transition-colors"
						type="button" id="radix-:r9:" aria-haspopup="menu" aria-expanded="false" data-state="closed">
						<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
							stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
							class="lucide lucide-download h-4 w-4 mr-1" aria-hidden="true">
							<path d="M12 15V3"></path>
							<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
							<path d="m7 10 5 5 5-5"></path>
						</svg>Export<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
							fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
							stroke-linejoin="round" class="lucide lucide-chevron-down h-3 w-3 ml-1" aria-hidden="true">
							<path d="m6 9 6 6 6-6"></path>
						</svg>
					</button>


					<div id="menu_daily_export" data-radix-popper-content-wrapper="" dir="ltr" style="position:absolute;left:-17px;top:100%;margin-top:4px;display:none;min-width: max-content; will-change: transform; z-index: 50; --radix-popper-available-width: 1424px; --radix-popper-available-height: 568.25px; --radix-popper-anchor-width: 115.609375px; --radix-popper-anchor-height: 31.5px; --radix-popper-transform-origin: 100% 0px;">
						<div data-side="bottom" data-align="end" role="menu" aria-orientation="vertical" data-state="open" data-radix-menu-content="" dir="ltr" id="radix-:rm:" aria-labelledby="radix-:rl:" data-slot="dropdown-menu-content" class="bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 max-h-(--radix-dropdown-menu-content-available-height) min-w-[8rem] origin-(--radix-dropdown-menu-content-transform-origin) overflow-x-hidden overflow-y-auto rounded-md border p-1 shadow-md" tabindex="-1" data-orientation="vertical" style="outline: none; --radix-dropdown-menu-content-transform-origin: var(--radix-popper-transform-origin); --radix-dropdown-menu-content-available-width: var(--radix-popper-available-width); --radix-dropdown-menu-content-available-height: var(--radix-popper-available-height); --radix-dropdown-menu-trigger-width: var(--radix-popper-anchor-width); --radix-dropdown-menu-trigger-height: var(--radix-popper-anchor-height); pointer-events: auto;">
							<div export_type="pdf" role="menuitem" data-slot="dropdown-menu-item" data-variant="default" class="daily_export_item focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4" tabindex="-1" data-orientation="vertical" data-radix-collection-item=""><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text h-4 w-4 mr-2" aria-hidden="true">
									<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
									<path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
									<path d="M10 9H8"></path>
									<path d="M16 13H8"></path>
									<path d="M16 17H8"></path>
								</svg>Export as PDF</div>
							<div export_type="csv" role="menuitem" data-slot="dropdown-menu-item" data-variant="default" class="daily_export_item focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4" tabindex="-1" data-orientation="vertical" data-radix-collection-item=""><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text h-4 w-4 mr-2" aria-hidden="true">
									<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
									<path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
									<path d="M10 9H8"></path>
									<path d="M16 13H8"></path>
									<path d="M16 17H8"></path>
								</svg>Export as CSV</div>
						</div>
					</div>

				</div>

			</div>
		</div>

		<div class="mt-3">
			<div class="flex flex-wrap items-center gap-3 tat-bar">
				<div class="flex items-center gap-2 text-sm text-muted-foreground"><svg
						xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
						stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
						class="lucide lucide-funnel h-4 w-4" aria-hidden="true">
						<path
							d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z">
						</path>
					</svg></div>
				<div class="flex items-center gap-2">

					<select id="daily_sel_days" class="none_border">
						<option value="14_days">14 Days</option>
						<option value="30_days">30 Days</option>
						<option value="90_days">90 Days</option>
						<option value="customer_range">Custom Range</option>
					</select>

					<button id="btn_day_customer_daily" data-slot="popover-trigger"
						class="inline-flex items-center justify-center whitespace-nowrap font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 rounded-md gap-1.5 has-[&gt;svg]:px-2.5 h-8 px-2 text-sm"
						title="Select custom date range" type="button">
						<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
							stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
							class="lucide lucide-calendar h-3 w-3 mr-1" aria-hidden="true">
							<path d="M8 2v4"></path>
							<path d="M16 2v4"></path>
							<rect width="18" height="18" x="3" y="4" rx="2"></rect>
							<path d="M3 10h18"></path>
						</svg>
						<span class="txt_show_customer_day" data-slot=select-value style=pointer-events:none>-- - --</span>
					</button>

					<select id="daily_btype" class="none_border select-btype">
						<?php
						foreach (tr_pdd_get_batch_types() as $btype_val) {
							echo '<option value="' . $btype_val . '">' . tr_pdd_get_typename($btype_val) . '</option>';
						}
						?>
					</select>

					<select id="daily_panel_id" class="none_border" style="max-width: 150px;">
						<option value="">All Panels</option>
					</select>

					<?php if (count($clinics) > 0) { ?>
						<select id="daily_clinic" class="none_border">
							<?php if (count($clinics) > 1) : ?>
								<option value="">All Clinics</option>
							<?php endif; ?>
							<?php
							foreach ($clinics as $c) {
								echo '<option value="' . $c->cID . '" >' . $c->cli_clinic . '</option>';
							}
							?>
						</select>
					<?php } ?>

					<button id="btn_daily_clear" data-slot="button"
						class="inline-flex cursor-pointer items-center justify-center whitespace-nowrap font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:bg-accent dark:hover:bg-accent/50 rounded-md gap-1.5 has-[&gt;svg]:px-2.5 h-7 px-2 text-xs text-muted-foreground hover:text-foreground">Clear</button>
				</div>
			</div>
		</div>

	</div>

	<div id="pane_live_report_daily" style="display:none" data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-0 pb-3"></div>

	<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-0 pb-3">
		<div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-5">
			<div class="p-3 bg-gray-50 rounded-lg border">
				<div class="text-xs text-gray-600 mb-1">Total Completed</div>
				<div class="text-xl font-bold text-green-600" id="daily_total_completed">--</div>
				<div class="text-xs text-green-600 mt-1" id="daily_rate_completed">--% success rate</div>
			</div>
			<div class="p-3 bg-gray-50 rounded-lg border">
				<div class="text-xs text-gray-600 mb-1">Total Rejected</div>
				<div class="text-xl font-bold text-red-700" id="daily_total_rejected">--</div>
				<div class="text-xs text-red-700 mt-1" id="daily_rate_rejected">--% rejection rate</div>
			</div>
			<div class="p-3 bg-gray-50 rounded-lg border">
				<div class="text-xs text-gray-600 mb-1">Daily Average</div>
				<div class="text-xl font-bold" id="daily_average">--</div>
				<div class="text-xs text-gray-600 mt-1">samples per day</div>
			</div>
		</div>
		<div id="custom-tooltip-daily" class="recharts-tooltip-wrapper"></div>
		<div id="daily_chart_contain" style="height:300px; width:100%; margin:3px 3px">
			<canvas id="daily_sample_volume_chart" height="300">Loading</canvas>
		</div>
	</div>
</div>
<script>
	var tooltipDaily = document.getElementById('custom-tooltip-daily');
	var isShowDailyReport = false;
	var daily_chart = null;
	var daily_day_from;
	var daily_day_to;
	showLoading("box_daily_chart");

	jQuery(function($) {
		$("#btn_daily_clear").removeClass("inline-flex").hide();
		$("#btn_day_customer_daily").removeClass("inline-flex").hide();

		$("#daily_sel_days").change(function() {
			var sel_day = $(this).val();
			if (sel_day == 'customer_range') {
				$("#btn_day_customer_daily").addClass("inline-flex").show();
				$("#btn_day_customer_daily").data('daterangepicker').show();
			} else {
				$("#btn_day_customer_daily").removeClass("inline-flex").hide();
				load_daily_sample_volume();
			}
			$("#btn_daily_clear").addClass("inline-flex").show();
		});

		$("#daily_btype,#daily_clinic,#daily_panel_id").change(function() {
			$("#btn_daily_clear").addClass("inline-flex").show();
			load_daily_sample_volume();
		});

		$('body').on('change', 'select#daily_btype', function() {
			var $vl = $(this).val();
			$('#daily_panel_id').val('');
			$('#emp_daily_panel_id').show();
			$html = '<option value="">All Panels</option>';
			for (i in $panels) {
				if ($panels[i].tg_type == $vl) {
					$html += '<option value="' + $panels[i].tg_ID + '">' + $panels[i].tg_name +
						'</option>';
				}

			}

			$('#daily_panel_id').html($html);
			refreshCustomSelect("daily_panel_id");
		})

		$("#btn_daily_clear").click(function() {
			$("#daily_sel_days").val('14_days');
			$("#daily_tat").val('tat');
			$("#daily_clinic").val('');
			$("#daily_panel_id").val('');
			$("#btn_daily_clear").removeClass("inline-flex").hide();
			$("#btn_day_customer_daily").removeClass("inline-flex").hide();
			refreshCustomSelect('daily_panel_id');
			refreshCustomSelect('daily_clinic');
			refreshCustomSelect('daily_sel_days');
			refreshCustomSelect('daily_tat');
			load_daily_sample_volume();
		});

		function load_daily_sample_volume() {
			$("#daily_loading").show();
			let sel_days = $("#daily_sel_days").val();
			let tat = $("#daily_tat").val();
			let clinic = $("#daily_clinic").val();
			let btype_val = $("#daily_btype").val();
			let panel_id_val = $("#daily_panel_id").val();
			var txtNavDaily = ""
			txtNavDaily = (sel_days == '14_days' ? 'Last 14 days' : sel_days == '30_days' ? 'Last 30 days' : sel_days == '90_days' ? 'Last 90 days' : 'Custom Range');
			if (btype_val != "tox") txtNavDaily = txtNavDaily + " • " + $('#daily_btype option:selected').text();
			if (panel_id_val != "") txtNavDaily = txtNavDaily + " • " + $('#daily_panel_id option:selected').text();
			if (clinic != "") txtNavDaily = txtNavDaily + " • " + $('#daily_clinic option:selected').text();
			$("#txtShowDayDaily").text(txtNavDaily);

			$.ajax({
				data: {
					trajax: "daily_sample_volume_ajax",
					selDay: sel_days,
					tat: tat,
					panel_id: panel_id_val,
					panel_label: $('#daily_panel_id option:selected').text(),
					cid_label: $('#daily_clinic option:selected').text(),
					clinic: clinic,
					btype: btype_val,
					from: daily_day_from,
					to: daily_day_to
				},
				success: function(response) {
					hideLoading('box_daily_chart');
					$("#daily_loading").hide();
					var reData = JSON.parse(response);
					var ctx_daily_chart = document.getElementById("daily_sample_volume_chart");
					var daily_summary = reData.summary;

					$("#pane_live_report_daily").html(reData.live_report);

					$("#daily_total_completed").html(daily_summary.total_completed);
					$("#daily_rate_completed").html(daily_summary.success_rate + "% success rate");
					$("#daily_total_rejected").html(daily_summary.total_rejected);
					$("#daily_rate_rejected").html(daily_summary.rejection_rate + "% rejection rate");
					$("#daily_average").html(daily_summary.average_daily);

					if (daily_chart !== null) {
						daily_chart.destroy();
					}

					daily_chart = new Chart(ctx_daily_chart, {
						type: "bar",
						data: reData.data,
						options: {
							responsive: true,
							maintainAspectRatio: false,
							plugins: {
								tooltip: {
									mode: "index",
									intersect: false,
									enabled: false,
									external: function(context) {
										let elementTag = document.getElementById('daily_sample_volume_chart');
										let tooltipModel = context.tooltip;

										if (tooltipModel.opacity === 0) {
											tooltipDaily.classList.remove("active");
											return;
										} else {
											tooltipDaily.classList.add("active");
										}
										let dataPoints = tooltipModel.dataPoints;

										let total = 0;
										dataPoints.forEach(dp => total += dp.raw || 0);
										let formattedTotal = parseFloat(total.toFixed(2));

										let innerHtml = `
											<div class="font-medium" style="font-weight:bold; margin-bottom:4px;">
											${dataPoints[0].label}
											</div>
										`;

										dataPoints.forEach(dp => {
											let color = dp.element?.options?.backgroundColor || dp.dataset.backgroundColor;
											innerHtml += `
											<div class="text-sm">
												<span style="color:${color}; font-size:12px;"> ${dp.dataset.label}: ${parseFloat((dp.raw || 0).toFixed(2))}</span>
											</div>
											`;
										});

										tooltipDaily.innerHTML = innerHtml;
										tooltipDaily.style.display = 'block';

										let index = tooltipModel.dataPoints[0].dataIndex; // vị trí cột
										let offsetX = 0;
										if (index > 6) {
											offsetX = -130; // dịch trái
										} else {
											offsetX = 60; // dịch phải
										}

										let x = tooltipModel.caretX + offsetX;

										tooltipDaily.style.left = x + 'px';
									}
								},
								legend: {
									position: 'bottom',
									labels: {
										usePointStyle: true,
										pointStyle: 'circle',
										boxWidth: 8,
										boxHeight: 8,
										font: {
											size: 13,
										},
										padding: 20,
										color: '#5a5a5aff'
									}
								},
							},
							scales: {
								x: {
									stacked: true
								},
								y: {
									stacked: true,
									beginAtZero: true,
									suggestedMax: 14
								}
							}
						}
					});
					$("#daily_panel_id").html(reData.panels_emp);
				},
				error: function(xhr, status, error) {
					alert("An error occurred: " + error);
				}
			});
		}

		$('#btn_day_customer_daily').daterangepicker({
				opens: 'right'
			},
			function(start, end, label) {
				$('#btn_day_customer_daily span').html(start.format('MMM D') + ' - ' + end.format('MMM D'));
				daily_day_from = start.format('YYYY-MM-DD');
				daily_day_to = end.format('YYYY-MM-DD');
				load_daily_sample_volume();
			}
		);

		$(".daily_export_item").click(function() {
			var export_type = $(this).attr("export_type");
			showToast('info', 'Preparing your download...', 'Your ' + export_type.toUpperCase() + ' export is being generated. This may take a few moments.', 6000);
			var $url = location.href + '&trajax=daily_sample_volume_ajax&export=true&export_type=' + export_type;
			$url += '&selDay=' + $("#daily_sel_days").val();
			$url += '&from=' + daily_day_from;
			$url += '&to=' + daily_day_to;
			$url += '&tat=' + $('#daily_tat').val();
			$url += '&btype=' + $('#daily_btype').val();
			$url += '&clinic=' + $('#daily_clinic').val();
			$url += '&panel_id=' + $('#daily_panel_id').val();
			$url += '&panel_label=' + $('#daily_panel_id option:selected').text();
			$url += '&cid_label=' + $("#daily_clinic option:selected").text();
			location.href = $url;
			setTimeout(() => {
				showToast('success', 'Download ready!', 'Your sample report has been downloaded successfully.', 6000);
			}, 5000);
			return false;
		});

		$("#daily_export_btn").on("click", function(e) {
			e.stopPropagation();
			$("#menu_daily_export").toggle();
		});

		$(document).on("click", function(e) {
			if (!$(e.target).closest("#daily_export_btn, #menu_daily_export").length) {
				$("#menu_daily_export").hide();
			}
		});

		$("#btn_live_report_daily").click(function() {
			var valHtml = $(this).find("textshow").html();
			if (valHtml.trim() == "Live Report") {
				$(this).find("textshow").html("Hide Live Report");
				$("#pane_live_report_daily").show();
				isShowDailyReport = true;
			} else {
				$(this).find("textshow").html("Live Report");
				$("#pane_live_report_daily").hide();
				isShowDailyReport = false;
			}
		});


		$("#daily_btype").change();
	});
</script>