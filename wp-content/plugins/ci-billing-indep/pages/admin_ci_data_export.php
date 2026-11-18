<?php
global $wpdb, $blog_id;

if (!current_user_can('ci_data_export')) {
	wp_die(__('Sorry, you are not allowed to access this page.'));
}

$export_nonce = wp_create_nonce('ci_close_lab_export_data');
$can_debug_export = current_user_can('manage_options');
$logs = $wpdb->get_results("
	select l.*, u.user_email
	from ci_log_users l
	join {$wpdb->users} u ON u.ID = l.user_id
	where l.user_action = 'data_export'
	and (l.lab_id is null or l.lab_id = " . intval($blog_id) . ")
	order by l.created desc
	limit 50
");
?>
<style>
	.ci-data-export-page {
		margin: 0;
		max-width: 100%;
		overflow-x: hidden
	}

	.ci-data-export-page * {
		box-sizing: border-box
	}

	.ci-data-export-page [data-slot="card"] {
		background: #fff;
		border: 1px solid #d9dde3;
		border-radius: 14px;
		box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
		max-width: 100%;
		min-width: 0
	}

	.ci-data-export-page [data-slot="card"].border-red-300 {
		background: #fef2f2;
		border-color: #ff8585;
		color: #991b1b
	}

	.ci-data-export-page table {
		border-collapse: collapse;
		table-layout: fixed;
		width: 100%
	}

	.ci-data-export-page th,
	.ci-data-export-page td {
		overflow-wrap: anywhere;
		word-break: break-word
	}

	.ci-data-export-page pre {
		margin: 0;
		max-width: 100%;
		overflow-wrap: anywhere;
		white-space: pre-wrap;
		word-break: break-word
	}

	.ci-data-export-page #authorization {
		align-items: center;
		background: #fff;
		border: 1px solid #d1d5db;
		border-radius: 4px;
		color: #fff;
		display: inline-flex;
		height: 14px;
		justify-content: center;
		padding: 0;
		width: 14px;
		margin-right: 10px;
	}

	.ci-data-export-page #authorization[data-state="checked"] {
		background: #e83500;
		border-color: #e83500
	}

	.ci-data-export-page #authorization svg {
		height: 12px;
		width: 12px
	}

	.ci-data-export-page #ci-start-export {
		background: #f09883;
		border-color: #f09883;
		color: #fff
	}

	.ci-data-export-page #ci-start-export:not(:disabled) {
		background: #e83500 !important;
		border-color: #e83500 !important;
		color: #fff !important;
		opacity: 1
	}

	.ci-data-export-page #ci-start-export:hover:not(:disabled) {
		background: #d93200 !important;
		border-color: #d93200 !important
	}

	.ci-data-export-page #ci-cancel-export:not(:disabled) {
		opacity: 1
	}

	.ci-data-export-page #ci-export-error-details {
		background: #fff;
		border: 1px solid #dcdcde;
		max-width: 100%;
		padding: 12px;
		white-space: pre-wrap
	}

	.ci-data-export-page .ci-log-table-wrap {
		max-width: 100%;
		overflow-x: auto;
		width: 100%
	}

	.ci-data-export-page .ci-log-table {
		min-width: 0;
		width: 100%
	}

	h1,
	h4 {
		margin: unset;
	}

	h1 {
		margin-bottom: 5px !important;
	}

	h4 {
		margin-bottom: 5px;
	}

	h5 {
		font-weight: var(--font-weight-medium);
		margin: 0px;
		--tw-font-weight: var(--font-weight-medium);
		font-size: 13px;
		margin-bottom: 10.5px;
	}

	h4 {
		font-size: 14.5px;
	}
</style>
<div class="ci-data-export-page flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
	<div class="space-y-6 pb-8">
		<div>
			<h1 class="text-2xl font-semibold">Data Export</h1>
			<p class="text-muted-foreground mt-2">Generate and download completed patient testing records for archival, transition, or laboratory closure purposes.</p>
		</div>
		<div data-slot="card" class="text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card border-red-300 bg-red-50">
			<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
				<div class="flex gap-3"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-triangle-alert h-6 w-6 text-red-600 flex-shrink-0 mt-1">
						<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path>
						<path d="M12 9v4"></path>
						<path d="M12 17h.01"></path>
					</svg>
					<div class="space-y-2">
						<p class="font-bold text-red-900 text-base">This export may contain protected health information (PHI).</p>
						<p class="text-red-800 text-sm">The laboratory is responsible for securely storing, transferring, and retaining any downloaded files in accordance with applicable privacy, security, and record retention requirements.</p>
					</div>
				</div>
			</div>
		</div>
		<div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
			<div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
				<h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-database h-5 w-5 text-primary">
						<ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
						<path d="M3 5V19A9 3 0 0 0 21 19V5"></path>
						<path d="M3 12A9 3 0 0 0 21 12"></path>
					</svg>This export includes:</h4>
			</div>
			<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
				<div class="border border-gray-200 rounded-lg overflow-hidden">
					<table class="w-full text-sm">
						<thead>
							<tr class="bg-gray-50 border-b border-gray-200">
								<th class="py-3 px-4 text-left font-semibold text-gray-900">Included</th>
								<th class="py-3 px-4 text-left font-semibold text-gray-900">Details</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-200">
							<tr class="hover:bg-gray-50">
								<td class="py-3 px-4 font-medium text-gray-900">Completed patient testing records</td>
								<td class="py-3 px-4 text-gray-700">Records with completed/finalized testing status</td>
							</tr>
							<tr class="hover:bg-gray-50">
								<td class="py-3 px-4 font-medium text-gray-900">Patient demographics</td>
								<td class="py-3 px-4 text-gray-700">Patient information associated with each test record</td>
							</tr>
							<tr class="hover:bg-gray-50">
								<td class="py-3 px-4 font-medium text-gray-900">Accession information</td>
								<td class="py-3 px-4 text-gray-700">Accession number and related identifiers</td>
							</tr>
							<tr class="hover:bg-gray-50">
								<td class="py-3 px-4 font-medium text-gray-900">Ordering information</td>
								<td class="py-3 px-4 text-gray-700">Ordering provider, clinic, and facility details where available</td>
							</tr>
							<tr class="hover:bg-gray-50">
								<td class="py-3 px-4 font-medium text-gray-900">Test information</td>
								<td class="py-3 px-4 text-gray-700">Panel/test type, collection date, received date, resulted date, completed date</td>
							</tr>
							<tr class="hover:bg-gray-50">
								<td class="py-3 px-4 font-medium text-gray-900">Result data</td>
								<td class="py-3 px-4 text-gray-700">Reported test results associated with completed records</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
			<div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
				<h4 data-slot="card-title" class="leading-none">Authorization Required</h4>
			</div>
			<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-6">
				<div class="flex items-start space-x-3"><button type="button" role="checkbox" aria-checked="false" data-state="unchecked" value="on" data-slot="checkbox" class="peer bg-input-background dark:bg-input/30 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground dark:data-[state=checked]:bg-primary data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] cursor-pointer disabled:cursor-not-allowed disabled:opacity-50 mt-1" id="authorization"><span data-slot="checkbox-indicator" class="flex items-center justify-center text-current transition-none" style="display: none; pointer-events: none;"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check size-3.5">
								<path d="M20 6 9 17l-5-5"></path>
							</svg></span></button><label data-slot="label" class="flex items-center gap-2 text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 font-normal cursor-pointer text-sm leading-relaxed" id="authorization-label">I confirm that I am authorized by the laboratory to generate and download this export. I understand that the export may contain PHI and that the laboratory is responsible for securely storing, transferring, and retaining the downloaded file. I also understand that export activity from this page will be logged for security and compliance purposes.</label></div>
				<div class="space-y-2"><label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-sm text-gray-700" for="ci-export-password">Re-enter LIS password:</label><input type="password" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive max-w-md bg-yellow-50 border-gray-300 focus:bg-yellow-50" id="ci-export-password" placeholder="Enter your password" value=""></div>
				<div class="flex items-center gap-3 pt-4"><button type="button" id="ci-start-export" data-slot="button" class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all cursor-pointer disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive h-9 px-4 py-2 has-[&gt;svg]:px-3 bg-primary hover:bg-primary/90 text-white" disabled><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-download h-4 w-4 mr-2">
							<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
							<polyline points="7 10 12 15 17 10"></polyline>
							<line x1="12" x2="12" y1="15" y2="3"></line>
						</svg>Prepare Export</button><button type="button" id="ci-cancel-export" data-slot="button" class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all cursor-pointer disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3" disabled>Cancel Export</button></div>
				<div id="ci-export-progress" style="display:none;max-width:560px;">
					<div style="height:20px;background:#dcdcde;border-radius:3px;overflow:hidden;">
						<div class="ci-export-progress-bar" style="height:20px;width:0;background:#2271b1;transition:width .2s;"></div>
					</div>
					<p class="ci-export-progress-status text-sm text-gray-700">Starting export...</p>
				</div>
				<p id="ci-export-message" class="text-sm text-gray-700" style="display:none;"></p>
				<pre id="ci-export-error-details" style="display:none;"></pre>
			</div>
		</div>
		<div data-slot="card" class="text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card bg-gray-50">
			<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
				<p class="text-sm text-gray-700">Export activity from this page is logged for security and compliance purposes, including the user, laboratory, date/time, IP address, export status, and number of records included.</p>
			</div>
		</div>
		<div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
			<div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
				<h4 data-slot="card-title" class="leading-none">Data Export Log</h4>
			</div>
			<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
				<div class="ci-log-table-wrap">
					<table class="widefat striped ci-log-table text-sm">
						<thead>
							<tr>
								<th>Date</th>
								<th>User Email</th>
								<th>Action</th>
								<th>IP</th>
								<th>Number of Records Exported</th>
								<th>Export Status</th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($logs)) { ?>
								<tr>
									<td colspan="6">No data export activity has been logged yet.</td>
								</tr>
							<?php } ?>
							<?php foreach ($logs as $log) {
								$data = json_decode($log->data, true);
								$records_exported = is_array($data) && isset($data['records_exported']) ? (int) $data['records_exported'] : '';
								$status = is_array($data) && isset($data['status']) ? $data['status'] : '';
							?>
								<tr>
									<td><?php echo esc_html($log->created); ?></td>
									<td><?php echo esc_html($log->user_email); ?></td>
									<td>Data Export</td>
									<td><?php echo esc_html($log->ip); ?></td>
									<td><?php echo esc_html($records_exported); ?></td>
									<td><?php echo esc_html($status); ?></td>
								</tr>
							<?php } ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>
<script>
	jQuery(function($) {
		var token = '';
		var requestId = '';
		var running = false;
		var $authorization = $('#authorization');
		var $password = $('#ci-export-password');
		var $button = $('#ci-start-export');
		var $cancel = $('#ci-cancel-export');
		var $progress = $('#ci-export-progress');
		var $bar = $('.ci-export-progress-bar', $progress);
		var $status = $('.ci-export-progress-status', $progress);
		var $message = $('#ci-export-message');
		var $errorDetails = $('#ci-export-error-details');

		function isAuthorized() {
			return $authorization.attr('data-state') === 'checked';
		}

		function setAuthorization(checked) {
			$authorization
				.attr('data-state', checked ? 'checked' : 'unchecked')
				.attr('aria-checked', checked ? 'true' : 'false');
			$authorization.find('[data-slot="checkbox-indicator"]').toggle(checked);
			refreshPrepareState();
		}

		function refreshPrepareState() {
			var ready = isAuthorized() && $password.val();
			$button.prop('disabled', running || !ready);
			$cancel.prop('disabled', running ? false : !ready && !token);
		}

		function runExportBatch() {
			$.ajax({
				url: ajaxurl,
				type: 'post',
				dataType: 'json',
				data: {
					action: 'ajax',
					tr_action: 'ci_export_data_batch',
					nonce: '<?php echo esc_js($export_nonce); ?>',
					token: token,
					request_id: requestId,
					authorized: isAuthorized() ? 1 : 0,
					password: token ? '' : $password.val()
				}
			}).done(function(response) {
				if (!response.success) {
					showError(
						response.data && response.data.message ? response.data.message : 'Export failed.',
						response.data && response.data.detail ? response.data.detail : ''
					);
					return;
				}
				token = response.data.token;
				$cancel.prop('disabled', false);
				if (response.data.initialized) {
					$status.text(
						'Export initialized; next request: ' + response.data.next_testing_type +
						' (testing type 1 / ' + response.data.type_total + ')'
					);
					runExportBatch();
					return;
				}
				$bar.css('width', response.data.percent + '%');
				$status.text(
					'Preparing file: ' + response.data.percent + '% (' +
					response.data.processed + ' records processed; testing type ' +
					response.data.type_position + ' / ' + response.data.type_total + ': ' +
					response.data.testing_type + ')'
				);
				if (response.data.done) {
					running = false;
					$button.text('Prepare New Export').prop('disabled', false);
					$cancel.prop('disabled', false);
					$message.html('<a class="button button-primary" href="' + response.data.download + '">Download CSV</a>').show();
					return;
				}
				$status.append('; next request: ' + response.data.next_testing_type);
				runExportBatch();
			}).fail(function(xhr, textStatus, errorThrown) {
				var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ?
					xhr.responseJSON.data.message :
					'The export request failed. Please start the export again.';
				var details = 'Reference: ' + requestId + '\nHTTP: ' + xhr.status + ' ' + xhr.statusText +
					'\nRequest status: ' + textStatus;
				if (errorThrown) {
					details += '\nError: ' + errorThrown;
				}
				if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.detail) {
					details += '\n\n' + xhr.responseJSON.data.detail;
				} else if (xhr.responseText) {
					details += '\n\nResponse:\n' + xhr.responseText.substring(0, 3000);
				}
				showError(message + ' Reference: ' + requestId, details);
			});
		}

		function showError(message, details) {
			running = false;
			$button.text('Try Again');
			$cancel.prop('disabled', !token);
			refreshPrepareState();
			$status.text(message);
			$message.hide();
			if (<?php echo $can_debug_export ? 'true' : 'false'; ?> && details) {
				$errorDetails.text(details).show();
			}
		}

		$authorization.add('#authorization-label').on('click', function(e) {
			e.preventDefault();
			setAuthorization(!isAuthorized());
		});
		$password.on('input', refreshPrepareState);

		$button.on('click', function() {
			if (running) {
				return;
			}
			running = true;
			token = '';
			requestId = 'ci-export-' + Date.now() + '-' + Math.floor(Math.random() * 1000000);
			$button.prop('disabled', true).text('Preparing...');
			$cancel.prop('disabled', false);
			$bar.css('width', '0');
			$status.text('Starting export...');
			$message.hide().empty();
			$errorDetails.hide().empty();
			$progress.show();
			runExportBatch();
		});

		$cancel.on('click', function() {
			if (!token) {
				running = false;
				$password.val('');
				setAuthorization(false);
				$status.text('Export canceled.');
				$message.hide();
				refreshPrepareState();
				return;
			}
			$.ajax({
				url: ajaxurl,
				type: 'post',
				dataType: 'json',
				data: {
					action: 'ajax',
					tr_action: 'ci_export_data_cancel',
					nonce: '<?php echo esc_js($export_nonce); ?>',
					token: token
				}
			}).always(function() {
				running = false;
				token = '';
				$button.text('Prepare Export');
				$cancel.prop('disabled', true);
				refreshPrepareState();
				$status.text('Export canceled.');
				$message.hide();
			});
		});

		refreshPrepareState();
	});
</script>
