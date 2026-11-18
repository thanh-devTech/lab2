<?php
include(TRCPDD_PATH . 'inc/functions_instrument.php');
include(TRCPDD_PATH . 'inc/define_apis.php');
include(TRCPDD_PATH . 'inc/define_instruments.php');

$uptime_percentage = '99.98'; // Placeholder value, replace with actual uptime calculation if available.
$uptime_file = '/proc/uptime';
$offset_seconds = get_option('gmt_offset') * 3600;
$total_interface = 0;
$online_interface = 0;


if (file_exists($uptime_file)) {
	$str = file_get_contents($uptime_file);
	$parts = explode(" ", $str);
	$total_uptime_seconds = floatval($parts[0]);


	$total_period_seconds = 24 * 3600;


	if ($total_period_seconds > 0) {
		$uptime_percentage = min(100, ($total_uptime_seconds / $total_period_seconds) * 100);
	} else {
		$uptime_percentage = 0; // Handle division by zero
	}
}
$apisdata = array();
$instruments = array();

//check instruments
foreach ($define_instruments as $instk => $instv) {
	if (tr_get_option('ci_pdf_data', $instk)) {

		$instst = get_option($instv['opt'], array());
		$status = isset($instst['auto_start']) ? $instst['auto_start'] : false;


		$logpath    = WP_CONTENT_DIR . '/log/' . $instv['dir'];
		$port		= isset($instst['port']) ? $instst['port'] : '';
		$instst['last_seen'] = '';
		$log = trpdd_get_logfiles($logpath, $port);
		if ($log['lasttime'] > 0) {
			$instst['last_seen'] =  human_time_diff($log['lasttime']) . ' ago';
		} else {
			$status = 0;
		}
		$status_text = $status ? 'Online' : 'Offline';

		$instruments[] = array(
			'name' => $instv['name'],
			'status' => $status,
			'status_text' => $status_text,
			'last_seen' => isset($instst['last_seen']) ? $instst['last_seen'] : ''
		);

		$total_interface++;
		if ($status) {
			$online_interface++;
		}
	}
}

$apis = array(
	'api_ellkay' => 'Ellkay',
	'api_athena' => 'Athena sFtp',
	'api_athena_sftp2' => 'Athena sFtp2',
	'api_athena_api' => 'Athena API',
	'api_solv' => 'Solv',
	'api_billing_sftp' => 'Telcor',
	'api_medhiwa' => 'Medhiwa',
	'api_lifetime' => 'Lifetime',
	'api_evident' => 'Evident',
	'api_provista' => 'Provista/TruMed',
	'api_md_connection' => 'MD Connection',
	'api_sftp_emr' => 'eClinicalWorks',
	'api_coriell' => 'Coriell',
	'api_sftp_pfizer' => 'Pfizer/GBH1',
	'api_sftp_seacoast' => 'Seacoast',
	'api_sftp_allscripts' => 'Allscripts',
);
foreach ($define_apis as $k => $arr) {
	$apis[$k] = $arr['name'];
}

foreach ($apis as $k => $apiname) {
	if (tr_get_option('ci_pdf_data', $k)) {

		$apistkey =  $k;
		$activekey = 'enable';
		$orm = '';
		$oru = '';
		if (isset($define_apis[$k])) {
			$dfapi = $define_apis[$k];
			$apistkey = $dfapi['st'];
			$activekey = $dfapi['active'];

			if (isset($dfapi['order_folder'])) {
				$orm = filemtime(WP_CONTENT_DIR . '/log/' . $dfapi['order_folder'] . '/log.txt');
				$oru = filemtime(WP_CONTENT_DIR . '/log/' . $dfapi['result_folder'] . '/log.txt');
			}
		}
		if ($k == 'api_athena') {
			$apistkey = 'tr_hl7_athena_st';
		}
		$st = get_option($apistkey, array());
		$status = isset($st[$activekey]) ? $st[$activekey] : false;
		$status_text = $status ? 'Online' : 'Offline';
		if ($status) {

			if ($orm > 0) {
				$orm = round((time() - $orm) / 60) . ' m';
			}
			if ($oru > 0) {
				$oru = round((time() - $oru) / 60) . ' m';
			}

			$apisdata[$k] = array(
				'name' => $apiname,
				'status' => $status,
				'status_text' => $status_text,
				'oru' => $oru,
				'orm' => $orm
			);
		}
	}
}

$state_reports = array();
$state_tests 	= get_option('ci_send_hub_tests', array());

foreach ($state_tests as $st) {
	if (!isset($state_reports[$st['state']])) {
		$state_reports[$st['state']] = array(
			'state' => $st['state'],
			'online' => 0,
			'percent' => 0,
			'total' => 0,
			'active' => 0,
		);
	}
	$state_reports[$st['state']]['total']++;
	if ($st['status'] >= 2) {
		$state_reports[$st['state']]['active']++;
	}
}

foreach ($state_reports as $state => $rarr) {
	$total = $rarr['total'];
	$active = $rarr['active'];
	if ($active > 0) {
		$state_reports[$state]['online'] = 1;
		$state_reports[$state]['percent'] = round(($active / $total) * 100, 2);
	} else {
		$state_reports[$state]['percent'] = 0;
	}
}

if (isset($_GET['debug'])) {
	var_dump($state_tests);
	var_dump($state_reports);
}

?>
<div data-slot=card
	class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
	<div data-slot=card-header
		class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 pb-3">
		<div class="flex items-center justify-between">
			<div>
				<h4 data-slot=card-title class="leading-none flex items-center gap-2">
					<div class="p-1.5 bg-blue-50 rounded-lg"><svg xmlns=http://www.w3.org/2000/svg width=24
							height=24 viewBox="0 0 24 24" fill=none stroke=currentColor stroke-width=2
							stroke-linecap=round stroke-linejoin=round
							class="lucide lucide-monitor h-4 w-4 text-blue-600">
							<rect width=20 height=14 x=2 y=3 rx=2></rect>
							<line x1=8 x2=16 y1=21 y2=21></line>
							<line x1=12 x2=12 y1=17 y2=21></line>
						</svg></div>System &amp; Interface Monitoring
				</h4>
				<p data-slot=card-description class=text-muted-foreground>Real-time status of systems and
					integrations</p>
			</div>

		</div>
	</div>
	<div data-slot=card-content class="px-6 [&amp;:last-child]:pb-6 pt-0 pb-3">
		<div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
			<div class="p-3 bg-gray-50 rounded-lg border">
				<div class="text-xs text-gray-600 mb-1">Interfaces Online</div>
				<div class="text-lg font-semibold"><?php echo $online_interface . '/' . $total_interface ?></div>
			</div>

			<div class="p-3 bg-gray-50 rounded-lg border">
				<div class="text-xs text-gray-600 mb-1">Failed Transmissions (24h)</div>
				<div class="text-lg font-semibold">0</div>
				<div class="text-xs text-gray-500">EMR 0 • ELR 0</div>
			</div>

			<?php /*
			<div class="p-3 bg-gray-50 rounded-lg border">
				<div class="text-xs text-gray-600 mb-1">Oldest Unsent</div>
				<div class="text-lg font-semibold"></div>
				<div class="text-xs text-gray-500">(ELR-PA)</div>
			</div>*/ ?>
			<div class="p-3 bg-gray-50 rounded-lg border">
				<div class="text-xs text-gray-600 mb-1">Uptime (24h)</div>
				<div class="text-lg font-semibold"><?php echo round($uptime_percentage, 4) ?>%</div>
			</div>

		</div>
		<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
			<div>
				<h4 class="font-semibold text-sm mb-3">Instruments</h4>
				<div class=space-y-3>
					<?php
					foreach ($instruments as $instrument) { ?>
						<div class="flex items-center justify-between p-2 rounded border">
							<div class="flex items-center gap-2">
								<?php if ($instrument['status']) { ?>
									<div class="w-2 h-2 rounded-full bg-green-500"></div>
								<?php } else { ?>
									<svg xmlns=http://www.w3.org/2000/svg width=24
										height=24 viewBox="0 0 24 24" fill=none stroke=currentColor stroke-width=2
										stroke-linecap=round stroke-linejoin=round
										class="lucide lucide-triangle-alert w-3 h-3 text-yellow-500">
										<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3">
										</path>
										<path d="M12 9v4"></path>
										<path d="M12 17h.01"></path>
									</svg>
								<?php } ?>
								<div>
									<div class="text-sm font-medium"><?php echo $instrument['name']; ?></div>
									<div class="text-xs text-gray-500"><?php echo $instrument['status_text']; ?></div>
								</div>
							</div>
							<div class="text-xs text-gray-500"><?php echo $instrument['last_seen']; ?></div>
						</div>
					<?php } ?>
					<?php /*
					<div class="flex items-center justify-between p-2 rounded border">
						<div class="flex items-center gap-2"><svg xmlns=http://www.w3.org/2000/svg width=24
								height=24 viewBox="0 0 24 24" fill=none stroke=currentColor stroke-width=2
								stroke-linecap=round stroke-linejoin=round
								class="lucide lucide-triangle-alert w-3 h-3 text-yellow-500">
								<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3">
								</path>
								<path d="M12 9v4"></path>
								<path d="M12 17h.01"></path>
							</svg>
							<div>
								<div class="text-sm font-medium">Opus 384</div>
								<div class="text-xs text-gray-500">Warning</div>
							</div>
						</div>
						<div class="text-xs text-gray-500">Last seen 5 min ago</div>
					</div>
					<div class="flex items-center justify-between p-2 rounded border">
						<div class="flex items-center gap-2">
							<div class="w-2 h-2 rounded-full bg-green-500"></div>
							<div>
								<div class="text-sm font-medium">QuantStudio 5</div>
								<div class="text-xs text-gray-500">Online</div>
							</div>
						</div>
						<div class="text-xs text-gray-500">Last seen 2 min ago</div>
					</div>
					<?php */ ?>

				</div>
				<?php /*
				<div class="flex gap-2 mt-3"><button data-slot=button
						class="inline-flex items-center justify-center whitespace-nowrap font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 text-xs h-7"><svg
							xmlns=http://www.w3.org/2000/svg width=24 height=24 viewBox="0 0 24 24" fill=none
							stroke=currentColor stroke-width=2 stroke-linecap=round stroke-linejoin=round
							class="lucide lucide-file-text w-3 h-3 mr-1">
							<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
							<path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
							<path d="M10 9H8"></path>
							<path d="M16 13H8"></path>
							<path d="M16 17H8"></path>
						</svg>Logs</button><button data-slot=button
						class="inline-flex items-center justify-center whitespace-nowrap font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 text-xs h-7"><svg
							xmlns=http://www.w3.org/2000/svg width=24 height=24 viewBox="0 0 24 24" fill=none
							stroke=currentColor stroke-width=2 stroke-linecap=round stroke-linejoin=round
							class="lucide lucide-rotate-ccw w-3 h-3 mr-1">
							<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
							<path d="M3 3v5h5"></path>
						</svg>Retry</button></div>*/ ?>
			</div>
			<?php
			if (count($apisdata) > 0) {
				foreach ($apisdata as $apik => $apiarr) {
					break;
				}
			?>
				<div>
					<h4 class="font-semibold text-sm mb-3">EMR (<?php echo $apiarr['name'] ?>)</h4>
					<div class=space-y-3>

						<div class="flex items-center justify-between p-2 rounded border">
							<div class="flex items-center gap-2">
								<div class="w-2 h-2 rounded-full bg-green-500"></div>
								<div>
									<div class="text-sm font-medium">ORM Online</div>
									<div class="text-xs text-gray-500">Online</div>
								</div>
							</div>
							<div class="text-xs text-gray-500">Last <?php echo $apiarr['orm'] ?></div>
						</div>

						<div class="flex items-center justify-between p-2 rounded border">
							<div class="flex items-center gap-2">
								<div class="w-2 h-2 rounded-full bg-green-500"></div>
								<div>
									<div class="text-sm font-medium">ORU Online</div>
									<div class="text-xs text-gray-500">Online</div>
								</div>
							</div>
							<div class="text-xs text-gray-500">Last <?php echo $apiarr['oru'] ?></div>
						</div>


						<div class="flex items-center justify-between p-2 rounded border">
							<div class="flex items-center gap-2">
								<div class="w-2 h-2 rounded-full bg-green-500"></div>
								<div>
									<div class="text-sm font-medium">EMR Interface</div>
									<div class="text-xs text-gray-500">Online</div>
								</div>
							</div>
							<div class="text-xs text-gray-500">Backlog 0 Errors 0</div>
						</div>

						<div class="flex items-center justify-between p-2 rounded border">
							<div class="flex items-center gap-2">
								<div class="w-2 h-2 rounded-full bg-green-500"></div>
								<div>
									<div class="text-sm font-medium">REST, SOAP, FHIR APIs</div>
									<div class="text-xs text-gray-500">Online</div>
								</div>
							</div>
							<div class="text-xs text-gray-500">Uptime 100%</div>
						</div>

					</div>
					<div class="flex gap-2 mt-3"><button data-slot=button
							data-href="?page=ci_pdf_data&pagetab=ci_wsdl_keys&tab=other&subtab=<?php echo $apik ?>&trajax=1&act=log&width=1200"
							class="show_api_log inline-flex items-center justify-center whitespace-nowrap font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 text-xs h-7"><svg
								xmlns=http://www.w3.org/2000/svg width=24 height=24 viewBox="0 0 24 24" fill=none
								stroke=currentColor stroke-width=2 stroke-linecap=round stroke-linejoin=round
								class="lucide lucide-file-text w-3 h-3 mr-1">
								<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
								<path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
								<path d="M10 9H8"></path>
								<path d="M16 13H8"></path>
								<path d="M16 17H8"></path>
							</svg>Logs</button>
						<?php
						/*<button data-slot=button
							class="inline-flex items-center justify-center whitespace-nowrap font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 text-xs h-7"><svg
								xmlns=http://www.w3.org/2000/svg width=24 height=24 viewBox="0 0 24 24" fill=none
								stroke=currentColor stroke-width=2 stroke-linecap=round stroke-linejoin=round
								class="lucide lucide-rotate-ccw w-3 h-3 mr-1">
								<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
								<path d="M3 3v5h5"></path>
							</svg>Retry</button>*/ ?>

					</div>
				</div>
			<?php } ?>

			<div>
				<h4 class="font-semibold text-sm mb-3">State Reporting (ELR/HIE)</h4>
				<div class=space-y-3>
					<?php foreach ($state_reports as $rarr) { ?>
						<div class="flex items-center justify-between p-2 rounded border">
							<div class="flex items-center gap-2">
								<div class="w-2 h-2 rounded-full bg-<?php echo $rarr['online'] ? 'green' : 'red' ?>-500"></div>
								<div>
									<div class="text-sm font-medium"><?php echo $rarr['state'] ?> ELR</div>
									<div class="text-xs text-gray-500"><?php echo $rarr['online'] ? 'Online' : 'Not Active' ?></div>
								</div>
							</div>
							<div class="text-xs text-gray-500"><?php echo $rarr['percent'] . '%' ?></div>
						</div>
					<?php } ?>



					<?php /*
					
					
				</div><button data-slot=button
					class="inline-flex items-center justify-center whitespace-nowrap font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 text-xs h-7 w-full"><svg
						xmlns=http://www.w3.org/2000/svg width=24 height=24 viewBox="0 0 24 24" fill=none
						stroke=currentColor stroke-width=2 stroke-linecap=round stroke-linejoin=round
						class="lucide lucide-refresh-cw w-3 h-3 mr-1">
						<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path>
						<path d="M21 3v5h-5"></path>
						<path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path>
						<path d="M8 16H3v5"></path>
					</svg>Update</button>
					*/ ?>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	jQuery(function($) {
		$('body').on('click', '.show_api_log', function() {
			tb_show('Logs', $(this).data('href'));
		}).on('click', 'a.show_popup', function() {
			if ($(this).hasClass('seach_assen')) return;
			var $url = $(this).attr('href');
			if ($(this).hasClass('resend')) return;
			//tb_remove();
			setTimeout(function() {
				tb_show('', $url);
			}, 300);
			return false;
		}).on('click', 'a.seach_assen', function() {
			var $s = $('#assen_search').val();
			var $url = $(this).attr('href');
			setTimeout(function() {
				tb_show('', $url + '&s=' + $s);
			}, 500);
			return false;
		})
	})
</script>