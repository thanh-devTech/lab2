<?php
global $wpdb;
$schedule_report = new CI_Report_Schedule('rack', 'Rack Report');
include_once(TRCPDD_PATH . 'inc/functions_rack_plate.php');

wp_enqueue_script('jquery-ui-datepicker');

wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');
wp_enqueue_script('jquery-validate', TRCPDD_URL . 'admin/js/jquery.validate.min.js', array('jquery'), true);
wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);

$trajax         = isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : '';

$export_func = isset($_REQUEST['export_func']) ? $_REQUEST['export_func'] : '';
$export_type = isset($_REQUEST['export_type']) ? $_REQUEST['export_type'] : '';

$filepath_save_pdf = isset($_REQUEST['filepath_save_pdf']) ? $_REQUEST['filepath_save_pdf'] : '';
$filepath_save_csv = isset($_REQUEST['filepath_save_csv']) ? $_REQUEST['filepath_save_csv'] : '';


$rack_selected = isset($_REQUEST['rack']) ? $_REQUEST['rack'] : 1;
$date_selected = isset($_REQUEST['date']) ? $_REQUEST['date'] : '';
$results = array();
$rack = new stdClass();
if ($rack_selected > 0 && $date_selected != '') {
	$sql_rack = "select * from ci_racks where re_today='{$date_selected}' and re_number = '{$rack_selected}'";
	$rack = $wpdb->get_row($sql_rack);
	$child_sql = array();
	foreach (tr_pdd_get_batch_types() as $btype) {
		$batch_tbl = tr_pdd_get_table_by_btype($btype);

		$child_sql[] = "(select 
						ba.requesting_practice,
                        ba.assen,
                        ba.doc,
                        ba.patient_name,
                        ba.patient_id,
                        rb.*
                        from  ci_rack_batches rb 
                          JOIN {$batch_tbl} ba ON rb.mid=ba.mid 
                            where rb.re_ID = '{$rack->re_ID}' and rb.btype='{$btype}' ) ";
	}

	$child_sql = implode(' UNION ALL ', $child_sql);

	$sql = "select * from ({$child_sql}) as ball
			join ci_patients pa ON ball.patient_id = pa.pa_ID
                                order by ball.ID asc";
	$results = $wpdb->get_results($sql);

	if (count($results) == 0) {
		Tr_Session_Class::reset();
		Tr_Session_Class::add_error("Not found!");
	}
}

$schedule_report->ajax();

if ($trajax != "" || $export_func != "") {

	if (count($results) > 0) {
		$headers = array(
			'Well',
			'Accession',
			'Age',
			'Gender',
			'Race',
			'Ethnicity',
			'Zip Code',
			'DOC',
			'Clinic Name',
		);

		tr_pdd_header('text/csv', 'sapphire.csv');
		if ($filepath_save_csv != ''){
			$file = fopen($filepath_save_csv, 'w');
			$csv_out_file = basename('Sapphire.csv');
		}
		else{
			$file = fopen('php://output', 'w');
		}

		fputcsv($file, $headers);
		foreach ($results as $j => $row) {
			$row->pa_age = trdd_dob2ages($row->pa_dob);
			$welld = tr_rack_well_by_position($j, $rack->re_is_uti_panel);
			$data = array(
				$welld,
				$row->assen,
				$row->pa_age,
				$row->pa_gender,
				$row->pa_race,
				$row->pa_ethnicity,
				$row->pa_zip,
				$row->doc,
				$row->requesting_practice
			);
			fputcsv($file, $data);
		}
	} else {
		if ($filepath_save_csv == "") wp_redirect(Tr_Base_Class_V6::link(array('trajax' => ''), false));
	}
	if ($filepath_save_csv == "") exit;
}

if ($filepath_save_csv != '' || $filepath_save_pdf != '') {
	return;
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
<?php $schedule_report->style(); ?>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?' . TRCPDD_VS ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
							</svg><span class="truncate max-w-[120px] sm:max-w-none">Rack Report</span></span></li>
				</ol>
			</nav>
		</div>
		<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
			<div>
				<h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">
					Rack Report</h1>
				<p class="text-muted-foreground mt-1">Sample rack locations, storage tracking, and inventory management
				</p>
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
										</svg></div>Volume by Rack Report
								</h4>
							</div>
							<div class="flex flex-wrap items-center gap-3">
								<button data-slot="dropdown-menu-trigger" export_func="export_collector"
									export_type="pdf"
									class="inline-flex export_item cursor-pointer items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive text-primary-foreground h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 bg-primary hover:bg-primary/90"
									type="button" id="radix-:reo:" aria-haspopup="menu" aria-expanded="false"
									data-state="closed">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
										viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
										stroke-linecap="round" stroke-linejoin="round"
										class="lucide lucide-download h-4 w-4 mr-1" aria-hidden="true">
										<path d="M12 15V3"></path>
										<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
										<path d="m7 10 5 5 5-5"></path>
									</svg>
									Export
								</button>

								<?php $schedule_report->btn_schedule(); ?>
							</div>
						</div>
						<div class="flex flex-wrap items-center gap-3 mt-4">

							<form method="get">
								<input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
								<input type="search" id="date_search" name="date" class="hasdate" value="<?php echo $date_selected ?>" />
								<select name="rack" id="sel_rack">
									<?php
									for ($i = 1; $i <= 10; $i++) {
										$selected = $rack_selected == $i ? 'selected' : '';
										echo '<option value="' . $i . '" ' . $selected . '>Rack #' . $i . '</option>';
									}
									?>
								</select>
								<input type="submit" class="button-primary" value="Search" />
							</form>

						</div>
					</div>


					<?php Tr_Session_Class::show() ?>
					<?php
					if (!empty($results)) {
					?>
						<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-6">
							<div class="border rounded-lg overflow-hidden">
								<div class="overflow-x-auto">
									<table class="posts report_tbl batch_tbl subretbl">
										<thead>
											<tr>
												<th>Well</th>
												<th>Accession</th>
												<th>Age</th>
												<th>Gender</th>
												<th>Race</th>
												<th>Ethnicity</th>
												<th>Zip Code</th>
												<th>DOC</th>
												<th>Clinic Name</th>
											</tr>
										</thead>
										<tbody>
											<?php
											foreach ($results as $j => $row) {
												$row->pa_age = trdd_dob2ages($row->pa_dob);
												$welld = tr_rack_well_by_position($j, $rack->re_is_uti_panel);
											?>
												<tr class="childp">
													<td><?php echo $welld ?></td>
													<td><?php echo $row->assen ?></td>
													<td><?php echo $row->pa_age ?></td>
													<td><?php echo $row->pa_gender ?></td>
													<td><?php echo $row->pa_race ?></td>
													<td><?php echo $row->pa_ethnicity ?></td>
													<td><?php echo $row->pa_zip ?></td>
													<td><?php echo $row->doc ?></td>
													<td><?php echo $row->requesting_practice ?></td>
												</tr>
											<?php
											}
											?>
										</tbody>
									</table>
								</div>
							</div>
						</div>
					<?php
					}
					?>

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
	var url_main_page = 'ci_reports_sapphire';
	jQuery(function($) {

		$(".export_item").click(async function() {
			var export_type = "csv";
			var $url = location.href + '&trajax=export_report&export_type=' + export_type;
			$url += '&export_func=all';
			$url += '&date=' + $("#date_search").val();
			$url += '&rack=' + $("#sel_rack").val();
			showToast('info', 'Preparing your download...', 'Generating...', 4000);
			try {
				const response = await fetch($url);

				// 🔥 Lấy filename từ header
				const disposition = response.headers.get("Content-Disposition");
				let filename = "download." + export_type;

				if (disposition && disposition.includes("filename=")) {
					const match = disposition.match(/filename="?([^"]+)"?/);
					if (match && match[1]) filename = match[1];
				}

				const blob = await response.blob();

				const a = document.createElement("a");
				a.href = URL.createObjectURL(blob);
				a.download = filename; // giữ nguyên tên file
				a.click();

				showToast('success', 'Download ready!', 'Your file "' + filename +
					'" has been downloaded.', 4000);

			} catch (err) {
				showToast('error', 'Error', 'Download failed.', 4000);
			}
			return false;
		});
	});
</script>
<?php $schedule_report->js(); ?>
