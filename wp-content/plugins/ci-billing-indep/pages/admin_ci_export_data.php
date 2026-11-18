<?php
$export_nonce = wp_create_nonce('ci_close_lab_export_data');
$can_debug_export = current_user_can('manage_options');
?>
<div class="ci-export-data-section" style="padding:0 0 20px;margin-bottom:20px;border-bottom:1px solid #dcdcde;">
	<h2>Patient Testing Data Export</h2>
	<p>Download all completed patient testing data for all labs, pay types, testing types, and dates.</p>
	<p>
		<button type="button" id="ci-start-export" class="button button-primary">Prepare Export CSV</button>
		<button type="button" id="ci-cancel-export" class="button" disabled>Cancel Export</button>
	</p>
	<div id="ci-export-progress" style="display:none;max-width:560px;">
		<div style="height:20px;background:#dcdcde;border-radius:3px;overflow:hidden;">
			<div class="ci-export-progress-bar" style="height:20px;width:0;background:#2271b1;transition:width .2s;"></div>
		</div>
		<p class="ci-export-progress-status">Starting export...</p>
	</div>
	<p id="ci-export-message" style="display:none;"></p>
	<pre id="ci-export-error-details" style="display:none;max-width:900px;padding:12px;background:#fff;border:1px solid #dcdcde;white-space:pre-wrap;"></pre>
</div>
<script>
jQuery(function($) {
	var token = '';
	var requestId = '';
	var running = false;
	var cancelRequested = false;
	var currentRequest = null;
	var $button = $('#ci-start-export');
	var $cancel = $('#ci-cancel-export');
	var $progress = $('#ci-export-progress');
	var $bar = $('.ci-export-progress-bar', $progress);
	var $status = $('.ci-export-progress-status', $progress);
	var $message = $('#ci-export-message');
	var $errorDetails = $('#ci-export-error-details');

	function runExportBatch() {
		if (cancelRequested) {
			return;
		}
		currentRequest = $.ajax({
			url: ajaxurl,
			type: 'post',
			dataType: 'json',
			data: {
				action: 'ajax',
				tr_action: 'ci_export_data_batch',
				nonce: '<?php echo esc_js($export_nonce); ?>',
				token: token,
				request_id: requestId
			}
		}).done(function(response) {
			if (cancelRequested) {
				return;
			}
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
					'Export initialized; next request: ' + response.data.next_testing_type
					+ ' (testing type 1 / ' + response.data.type_total + ')'
				);
				runExportBatch();
				return;
			}
			$bar.css('width', response.data.percent + '%');
			$status.text(
				'Preparing file: ' + response.data.percent + '% ('
				+ response.data.processed + ' records processed; testing type '
				+ response.data.type_position + ' / ' + response.data.type_total + ': '
				+ response.data.testing_type + ')'
			);
			if (response.data.done) {
				running = false;
				$button.prop('disabled', false).text('Prepare New Export CSV');
				$cancel.prop('disabled', false);
				$message.html('<a class="button button-primary" href="' + response.data.download + '">Download CSV</a>').show();
				return;
			}
			$status.append('; next request: ' + response.data.next_testing_type);
			runExportBatch();
		}).fail(function(xhr, textStatus, errorThrown) {
			if (cancelRequested || textStatus === 'abort') {
				return;
			}
			var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
				? xhr.responseJSON.data.message
				: 'The export request failed. Please start the export again.';
			var details = 'Reference: ' + requestId + '\nHTTP: ' + xhr.status + ' ' + xhr.statusText
				+ '\nRequest status: ' + textStatus;
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
		$button.prop('disabled', false).text('Try Again');
		$cancel.prop('disabled', !token);
		$status.text(message);
		$message.hide();
		if (<?php echo $can_debug_export ? 'true' : 'false'; ?> && details) {
			$errorDetails.text(details).show();
		}
	}

	$button.on('click', function() {
		if (running) {
			return;
		}
		running = true;
		cancelRequested = false;
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
		cancelRequested = true;
		if (currentRequest && currentRequest.readyState !== 4) {
			currentRequest.abort();
		}
		if (!token) {
			running = false;
			$button.prop('disabled', false).text('Prepare Export CSV');
			$cancel.prop('disabled', true);
			$status.text('Export canceled.');
			$message.hide();
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
		}).always(function(xhr) {
			running = false;
			token = '';
			$button.prop('disabled', false).text('Prepare Export CSV');
			$cancel.prop('disabled', true);
			$status.text('Export canceled. No active export session remains.');
			$message.hide();
			$errorDetails.hide().empty();
		});
	});
});
</script>
