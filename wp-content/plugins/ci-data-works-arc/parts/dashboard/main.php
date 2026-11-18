<?php

global $wpdb, $current_role;
$user_id	= get_current_user_id();
$from_date 	= date_i18n('Y-m-d');
$sub_page 	= isset($_REQUEST['sub_page']) ? $_REQUEST['sub_page'] : '';

if (isset($_REQUEST['trajax'])) {
	$filepath = dirname(__FILE__) . '/' . $_REQUEST['trajax'] . '.php';
	if (file_exists($filepath)) {
		include($filepath);
	}
	exit;
}
add_thickbox();
$show_sale_chanel = tr_get_option('ci_pdf_data', 'show_new_sale_channel', false);


$conditions 		= trpdd_get_batch_conditions();
$clinics 			= $wpdb->get_results("select cli.* from ci_clinic cli
                    where {$conditions['where_clinic']}  
					order by cli.cli_clinic asc");

$panels = $wpdb->get_results("select tg_ID, 
REPLACE(tg_name,'(new)','') as tg_name, 
tg_type 
from ci_test_groups 
where tg_parent=0 
and tg_show_req =1
order by tg_position asc");

?>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/jquery/latest/jquery.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?' . TRCPDD_VS ?>"></script>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
	var $panels = <?php echo json_encode($panels); ?>;
	setTimeout(() => {
		initCustomSelect("select");
	}, 500);
</script>

<div id=root class="wrap">
	<div data-slot=sidebar-wrapper
		class="group/sidebar-wrapper has-data-[variant=inset]:bg-sidebar flex min-h-svh w-full"
		style=--sidebar-width:16rem;--sidebar-width-icon:3rem>
		<div class="flex w-full">
			<main id="main_content" class="flex-1 overflow-hidden">
				<?php
				if ($sub_page != '') {
					$filepath = dirname(__FILE__) . '/' . $sub_page . '.php';
					if (file_exists($filepath)) {
						include($filepath);
					} else {
						echo "<h3>Page not found</h3>";
					}
				} else {
				?>
					<div class="h-full flex flex-col">
						<div class="flex-1 overflow-auto p-8">
							<div class=space-y-5>
								<div class="flex items-center justify-between">
									<div>
										<h1 class="text-3xl font-semibold">Laboratory Dashboard</h1>
									</div>
									<div class="flex items-center gap-3"><button data-slot=button
											class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[>svg]:px-3 shadow-card hover:shadow-card-hover transition-shadow-smooth"><svg
												xmlns=http://www.w3.org/2000/svg width=24 height=24 viewBox="0 0 24 24" fill=none
												stroke=currentColor stroke-width=2 stroke-linecap=round stroke-linejoin=round
												class="lucide lucide-calendar h-5 w-5 mr-2">
												<path d="M8 2v4"></path>
												<path d="M16 2v4"></path>
												<rect width=18 height=18 x=3 y=4 rx=2></rect>
												<path d="M3 10h18"></path>
											</svg>Today</button></div>
								</div>

								<?php
								$dashboard_widgets = [];
								if (current_user_can('ci_dashboard_topbox')) {
									include(TRCPDD_PATH . 'parts/dashboard/top_boxes.php');
									$dashboard_widgets[] = 'summary';
								}
								if (current_user_can('ci_dashboard_current_queue_panel') && (tr_get_option('ci_pdf_data', 'dashboard_curr_queue', false) || isset($_GET['current_queue']))) {
									include(TRCPDD_PATH . 'parts/dashboard/current_queue_by_panel.php');
									$dashboard_widgets[] = 'queue';
								}
								if (current_user_can('ci_dashboard_daily_volume')) {
									include(TRCPDD_PATH . 'parts/dashboard/daily_sample_volume.php');
									$dashboard_widgets[] = 'daily_volume';
								}
								if (current_user_can('ci_dashboard_tat')) {
									include(TRCPDD_PATH . 'parts/dashboard/tat.php');
									$dashboard_widgets[] = 'tat';
								}
								if (current_user_can('ci_dashboard_test_type_distribution')) {
									include(TRCPDD_PATH . 'parts/dashboard/test_type_distribution.php');
									$dashboard_widgets[] = 'test_distribution';
								}
								if ($show_sale_chanel && current_user_can('ci_dashboard_sales_channels')) {
									include(TRCPDD_PATH . 'parts/dashboard/new_sales_channels.php');
								}

								if (current_user_can('ci_dashboard_system')) {
									include(TRCPDD_PATH . 'parts/dashboard/system.php');
									$dashboard_widgets[] = 'system_status';
								}
								?>
							</div>
						</div>
					</div>
				<?php } ?>
			</main>
		</div>
	</div>
</div>
<?php include TRCPDD_PATH . "parts/reports/user_guide.php" ?>
<script>
	window.tr_show_main = function(page_file) {
		const url = new URL(window.location.href);
		url.searchParams.set('sub_page', page_file);
		window.location.href = url.toString();
	}
	jQuery(function($) {
		let syncing_btype = false;

		$('body').on('change', '.select-btype', function() {
			if (syncing_btype) return;

			syncing_btype = true;
			const id = this.id;
			const val = $(this).val();
			$('.select-btype').not(this).each(function() {
				if ($(this).val() !== val) {
					$(this).val(val).trigger('change');
				}

			});
			syncing_btype = false;
		});
	})
</script>