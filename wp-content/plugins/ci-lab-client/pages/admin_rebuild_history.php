<?php

global $wpdb;

if (
	!function_exists('tr_pdd_get_batch_types')
	|| !function_exists('tr_pdd_get_table_by_btype')
	|| !function_exists('tr_pdd_get_table_report_by_btype')
	|| !function_exists('tr_pdd_get_report_by_id')
	|| !function_exists('trpdd_get_last_result_history_html')
	|| !function_exists('trpdd_export_results_pdf')
	|| !function_exists('trpdd_build_history_pdf')
	|| !function_exists('trpdd_put_content_pdf_html')
) {
	echo '<div class="tool-notice tool-notice-error"><p>History rebuild functions are not available.</p></div>';
	return;
}

$all_btypes = tr_pdd_get_batch_types();

if (isset($_POST['trajax']) && $_POST['trajax'] === 'history_load_btype') {
	$btype = isset($_POST['btype']) ? sanitize_text_field($_POST['btype']) : '';
	$mode = isset($_POST['history_mode']) ? sanitize_text_field($_POST['history_mode']) : 'search';
	$batch_id = isset($_POST['batch_id']) ? trim(sanitize_text_field($_POST['batch_id'])) : '';
	$accession = isset($_POST['accession']) ? trim(sanitize_text_field($_POST['accession'])) : '';
	$dob = isset($_POST['dob']) ? trim(sanitize_text_field($_POST['dob'])) : '';
	$lab_id = isset($_POST['lab_id']) ? absint($_POST['lab_id']) : 0;
	$ref_lab_id = isset($_POST['ref_lab_id']) ? ($_POST['ref_lab_id']) : 'all';
	$clinic_id = isset($_POST['clinic_id']) ? absint($_POST['clinic_id']) : 0;
	$doc_from = isset($_POST['doc_from']) ? trim(sanitize_text_field($_POST['doc_from'])) : '';
	$doc_to = isset($_POST['doc_to']) ? trim(sanitize_text_field($_POST['doc_to'])) : '';
	$history_from = isset($_POST['history_from']) ? trim(sanitize_text_field($_POST['history_from'])) : '';
	$history_to = isset($_POST['history_to']) ? trim(sanitize_text_field($_POST['history_to'])) : '';
	$has_search = $batch_id !== '' || $accession !== '' || $dob !== '' || $lab_id > 0 || $ref_lab_id > 0 || $clinic_id > 0 || $doc_from !== '' || $doc_to !== '' || $history_from !== '' || $history_to !== '';
	$history_from_ts = $history_from !== '' ? strtotime($history_from . ' 00:00:00') : 0;
	$history_to_ts = $history_to !== '' ? strtotime($history_to . ' 23:59:59') : 0;
	$response = array(
		'btype' => $btype,
		'rows' => array(),
		'failed_details' => array(),
		'stats' => array(
			'db_rows' => 0,
			'with_history' => 0,
			'skipped_without_history' => 0,
			'skipped_history_date' => 0,
			'rebuilt' => 0,
			'failed' => 0,
		),
	);

	if (!$has_search) {
		wp_send_json_error(array('message' => 'Please enter at least one search field.'));
	}

	if (!in_array($btype, $all_btypes, true)) {
		wp_send_json_error(array('message' => 'Invalid btype.'));
	}

	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);

	if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $batch_tbl)) || !$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $report_tbl))) {
		wp_send_json_success($response);
	}

	$where = array('re.id > 0');
	$params = array();

	if ($batch_id !== '') {
		$where[] = 'ba.batch_id = %d';
		$params[] = absint($batch_id);
	}
	if ($accession !== '') {
		$where[] = 'ba.assen LIKE %s';
		$params[] = '%' . $wpdb->esc_like($accession) . '%';
	}
	if ($dob !== '') {
		$where[] = '(ba.dob = %s OR pa.pa_dob = %s)';
		$params[] = $dob;
		$params[] = $dob;
	}
	if ($lab_id > 0) {
		$where[] = 'ba.lab_id = %d';
		$params[] = $lab_id;
	}
	if ($ref_lab_id > 0) {
		$where[] = 'ba.ref_lab_ID = %d';
		$params[] = $ref_lab_id;
	} else if ($ref_lab_id === 'all') {
		$where[] = 'ba.ref_lab_ID  > 0 ';
	} else if ($ref_lab_id == 0) {
		$where[] = 'ba.ref_lab_ID = 0';
	}
	if ($clinic_id > 0) {
		$where[] = 'ba.cli_ID = %d';
		$params[] = $clinic_id;
	}
	if ($doc_from !== '') {
		$where[] = 'ba.doc >= %s';
		$params[] = $doc_from . ' 00:00:00';
	}
	if ($doc_to !== '') {
		$where[] = 'ba.doc <= %s';
		$params[] = $doc_to . ' 23:59:59';
	}

	$sql = "
		SELECT
			%s AS btype,
			ba.mid,
			ba.batch_id,
			ba.assen,
			ba.dob,
			ba.doc,
			ba.lab_id,
			ba.ref_lab_ID,
			ba.cli_ID,
			lab.lab_name,
			ref_lab.lab_name AS ref_lab_name,
			cli.cli_clinic,
			pa.pa_dob,
			re.id AS re_id,
			re.estatus,
			re.final_results,
			re.approve_date
		FROM {$batch_tbl} ba
		JOIN {$report_tbl} re ON re.patient_id = ba.assen
		LEFT JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
		LEFT JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
		LEFT JOIN ci_labs ref_lab ON ref_lab.lab_ID = ba.ref_lab_ID
		LEFT JOIN ci_clinic cli ON cli.cID = ba.cli_ID
		WHERE " . implode(' AND ', $where) . "
		GROUP BY re.id
		ORDER BY re.approve_date DESC, re.id DESC
	";
	$params = array_merge(array($btype), $params);
	$rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
	$response['stats']['db_rows'] = count($rows);

	foreach ($rows as $row) {
		$report = tr_pdd_get_report_by_id($row['re_id'], $btype);
		if (empty($report)) {
			continue;
		}

		global $ci_history_file_cache_path;
		$ci_history_file_cache_path = '';
		ob_start();
		$history_html = trpdd_get_last_result_history_html($report);
		ob_get_clean();
		$filepath = $ci_history_file_cache_path;

		if (empty($filepath) || !file_exists($filepath)) {
			$response['stats']['skipped_without_history']++;
			continue;
		}

		$history_filetime = filemtime($filepath);
		if (($history_from_ts > 0 && $history_filetime < $history_from_ts) || ($history_to_ts > 0 && $history_filetime > $history_to_ts)) {
			$response['stats']['skipped_history_date']++;
			continue;
		}

		$row['history_file'] = $filepath;
		$row['history_name'] = basename($filepath);
		$row['history_size'] = size_format(filesize($filepath));
		$row['history_mtime'] = date_i18n('Y-m-d H:i:s', $history_filetime);
		$row['history_has_html'] = !empty($history_html);
		$row['rebuilt'] = null;
		$row['rebuild_message'] = '';

		if ($mode === 'rebuild') {
			ob_start();
			$content = trpdd_export_results_pdf($row['re_id'], 'html', false, $row['btype']);
			$export_output = trim(wp_strip_all_tags(ob_get_clean()));
			if (!empty($content) && file_exists($row['history_file'])) {
				$saved = trpdd_put_content_pdf_html($row['history_file'], $content);
				if ($saved === false && is_writable(dirname($row['history_file']))) {
					$tmp_file = tempnam(dirname($row['history_file']), 'rebuild_history_');
					if (!empty($tmp_file)) {
						$tmp_saved = trpdd_put_content_pdf_html($tmp_file, $content);
						if ($tmp_saved !== false && @rename($tmp_file, $row['history_file'])) {
							$saved = $tmp_saved;
						} else if (file_exists($tmp_file)) {
							@unlink($tmp_file);
						}
					}
				}
				if ($saved !== false) {
					$response['stats']['rebuilt']++;
					$row['rebuilt'] = true;
					clearstatcache(true, $row['history_file']);
					$row['history_size'] = size_format(filesize($row['history_file']));
					$row['history_mtime'] = date_i18n('Y-m-d H:i:s', filemtime($row['history_file']));
					if (!is_writable($row['history_file'])) {
						$row['rebuild_message'] = 'Rebuilt by replacing the file because the original file was not writable.';
					}
				} else {
					$response['stats']['failed']++;
					$row['rebuilt'] = false;
					$reason = 'Could not write the last history file.';
					if (!is_writable($row['history_file'])) {
						$reason .= ' File is not writable.';
					} else if (!is_writable(dirname($row['history_file']))) {
						$reason .= ' History directory is not writable.';
					}
					if (!empty($wpdb->last_error)) {
						$reason .= ' DB: ' . $wpdb->last_error;
					}
					$row['rebuild_message'] = $reason;
					$response['failed_details'][] = array('assen' => $row['assen'], 'btype' => $row['btype'], 're_id' => $row['re_id'], 'reason' => $reason, 'history_file' => $row['history_file']);
				}
			} else {
				$report = tr_pdd_get_report_by_id($row['re_id'], $row['btype']);
				ob_start();
				$fallback_saved = !empty($report) ? trpdd_build_history_pdf($report, $row['btype']) : false;
				$fallback_output = trim(wp_strip_all_tags(ob_get_clean()));
				if ($fallback_saved) {
					$ci_history_file_cache_path = '';
					ob_start();
					$history_html = trpdd_get_last_result_history_html($report);
					ob_get_clean();
					$filepath = $ci_history_file_cache_path;
					if (!empty($filepath) && file_exists($filepath) && !empty($history_html)) {
						$response['stats']['rebuilt']++;
						$row['rebuilt'] = true;
						$row['rebuild_message'] = 'Rebuilt by trpdd_build_history_pdf().';
						$row['history_file'] = $filepath;
						$row['history_name'] = basename($filepath);
						$row['history_size'] = size_format(filesize($filepath));
						$row['history_mtime'] = date_i18n('Y-m-d H:i:s', filemtime($filepath));
					} else {
						$response['stats']['failed']++;
						$row['rebuilt'] = false;
						$reason = 'trpdd_build_history_pdf() ran but no history HTML file was found after rebuild.';
						if (!empty($fallback_output)) {
							$reason .= ' Output: ' . substr($fallback_output, 0, 300);
						}
						if (!empty($wpdb->last_error)) {
							$reason .= ' DB: ' . $wpdb->last_error;
						}
						$row['rebuild_message'] = $reason;
						$response['failed_details'][] = array('assen' => $row['assen'], 'btype' => $row['btype'], 're_id' => $row['re_id'], 'reason' => $reason, 'history_file' => $row['history_file']);
					}
				} else {
					$response['stats']['failed']++;
					$row['rebuilt'] = false;
					if (empty($report)) {
						$reason = 'Report could not be loaded by tr_pdd_get_report_by_id().';
					} else if (empty($content)) {
						$reason = 'Export HTML is empty. Check result data, requested panels, and the current report template.';
						if (isset($row['final_results']) && (int) $row['final_results'] <= 0) {
							$reason .= ' final_results is not greater than 0, so trpdd_build_history_pdf() will not create a history file.';
						}
					} else {
						$reason = 'The last history file no longer exists.';
					}
					if (!empty($export_output)) {
						$reason .= ' Export output: ' . substr($export_output, 0, 300);
					}
					if (!empty($fallback_output)) {
						$reason .= ' Fallback output: ' . substr($fallback_output, 0, 300);
					}
					if (!empty($wpdb->last_error)) {
						$reason .= ' DB: ' . $wpdb->last_error;
					}
					$row['rebuild_message'] = $reason;
					$response['failed_details'][] = array('assen' => $row['assen'], 'btype' => $row['btype'], 're_id' => $row['re_id'], 'reason' => $reason, 'history_file' => $row['history_file']);
				}
			}
		}

		$response['stats']['with_history']++;
		$row['pdf_url'] = add_query_arg(array(
			'tr_action' => 'report_history_pdf',
			'reid' => $row['re_id'],
			'btype' => $row['btype'],
			'd' => strtolower($row['assen']),
			'f' => str_replace('.html', '', $row['history_name']),
			'view' => 1,
		), admin_url('admin.php'));
		$response['rows'][] = $row;
	}

	wp_send_json_success($response);
}

wp_enqueue_script('jquery-ui-datepicker');
if (defined('TRCPDD_URL')) {
	wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');
}

$selected_btypes = isset($_REQUEST['btypes']) ? (array) $_REQUEST['btypes'] : array('all');
$batch_id = isset($_REQUEST['batch_id']) ? trim(sanitize_text_field($_REQUEST['batch_id'])) : '';
$accession = isset($_REQUEST['accession']) ? trim(sanitize_text_field($_REQUEST['accession'])) : '';
$dob = isset($_REQUEST['dob']) ? trim(sanitize_text_field($_REQUEST['dob'])) : '';
$lab_id = isset($_REQUEST['lab_id']) ? absint($_REQUEST['lab_id']) : 0;
$ref_lab_id = isset($_REQUEST['ref_lab_id']) ? ($_REQUEST['ref_lab_id']) : 0;
$clinic_id = isset($_REQUEST['clinic_id']) ? absint($_REQUEST['clinic_id']) : 0;
$doc_from = isset($_REQUEST['doc_from']) ? trim(sanitize_text_field($_REQUEST['doc_from'])) : '';
$doc_to = isset($_REQUEST['doc_to']) ? trim(sanitize_text_field($_REQUEST['doc_to'])) : '';
$history_from = isset($_REQUEST['history_from']) ? trim(sanitize_text_field($_REQUEST['history_from'])) : '';
$history_to = isset($_REQUEST['history_to']) ? trim(sanitize_text_field($_REQUEST['history_to'])) : '';
$labs = $wpdb->get_results("SELECT lab_ID, lab_name, lab_dba_init FROM ci_labs ORDER BY lab_name ASC");
$clinics = $wpdb->get_results("SELECT cID, cli_clinic FROM ci_clinic ORDER BY cli_clinic ASC");
?>

<div class="form-container wrap rebuild-history-tool">
	<h3>Rebuild History Results</h3>
	<form method="post" id="history-search-form">
		<div class="form-grid">
			<label>
				BType
				<select name="btypes[]" multiple size="6">
					<option value="all" <?php selected(in_array('all', $selected_btypes, true)); ?>>All btypes</option>
					<?php foreach ($all_btypes as $btype) : ?>
						<option value="<?php echo esc_attr($btype); ?>" <?php selected(in_array($btype, $selected_btypes, true)); ?>><?php echo esc_html($btype); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				Batch ID
				<input type="text" name="batch_id" value="<?php echo esc_attr($batch_id); ?>" placeholder="20260430">
			</label>
			<label>
				Accession
				<input type="text" name="accession" value="<?php echo esc_attr($accession); ?>" placeholder="">
			</label>
			<label>
				DOB
				<input type="text" class="history-date" name="dob" value="<?php echo esc_attr($dob); ?>" placeholder="yyyy-mm-dd" autocomplete="off">
			</label>
			<label>
				Lab
				<select name="lab_id">
					<option value="0">All labs</option>
					<?php foreach ($labs as $lab) : ?>
						<option value="<?php echo esc_attr($lab->lab_ID); ?>" <?php selected($lab_id, $lab->lab_ID); ?>><?php echo esc_html($lab->lab_name . ' (' . $lab->lab_dba_init . ')'); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				Ref Lab
				<select name="ref_lab_id">
					<option value="all">All ref labs</option>
					<option value="0">No Ref Lab</option>
					<?php foreach ($labs as $lab) : ?>
						<option value="<?php echo esc_attr($lab->lab_ID); ?>" <?php selected($ref_lab_id, $lab->lab_ID); ?>><?php echo esc_html($lab->lab_name . ' (' . $lab->lab_dba_init . ')'); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				Clinic
				<select name="clinic_id">
					<option value="0">All clinics</option>
					<?php foreach ($clinics as $clinic) : ?>
						<option value="<?php echo esc_attr($clinic->cID); ?>" <?php selected($clinic_id, $clinic->cID); ?>><?php echo esc_html($clinic->cli_clinic); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<div class="range-field">
				<span class="range-title">Doc Date Range</span>
				<div class="date-range-inputs">
					<label>
						<span>From</span>
						<input type="text" class="history-date history-date-from" data-range="doc" name="doc_from" value="<?php echo esc_attr($doc_from); ?>" placeholder="yyyy-mm-dd" autocomplete="off">
					</label>
					<label>
						<span>To</span>
						<input type="text" class="history-date history-date-to" data-range="doc" name="doc_to" value="<?php echo esc_attr($doc_to); ?>" placeholder="yyyy-mm-dd" autocomplete="off">
					</label>
				</div>
			</div>
			<div class="range-field">
				<span class="range-title">History File Date Range</span>
				<div class="date-range-inputs">
					<label>
						<span>From</span>
						<input type="text" class="history-date history-date-from" data-range="history" name="history_from" value="<?php echo esc_attr($history_from); ?>" placeholder="yyyy-mm-dd" autocomplete="off">
					</label>
					<label>
						<span>To</span>
						<input type="text" class="history-date history-date-to" data-range="history" name="history_to" value="<?php echo esc_attr($history_to); ?>" placeholder="yyyy-mm-dd" autocomplete="off">
					</label>
				</div>
			</div>
		</div>
		<div class="button-row">
			<button type="button" id="history-search-btn" class="btn-primary">Search History</button>
			<button type="button" id="history-rebuild-btn" class="btn-primary">Rebuild Last History Files</button>
		</div>
	</form>

	<div id="history-notices"></div>
	<div id="history-progress" class="history-progress" style="display:none;">
		<div id="history-progress-title">Ready</div>
	</div>
	<div id="history-failed-details" class="tool-notice tool-notice-error tool-failed-details" style="display:none;">
		<p><strong>Failure details</strong></p>
		<ul></ul>
	</div>

	<p class="history-summary" id="history-summary" style="display:none;">
		Loaded: <span id="history-loaded">0</span> /
		Matched: <span id="history-db-rows">0</span>.
		Skipped: <span id="history-skipped">0</span>.
		Rebuilt: <span id="history-rebuilt">0</span>.
		Failed: <span id="history-failed">0</span>.
	</p>

	<table class="widefat striped history-table" id="history-results-table" style="display:none;">
		<thead>
			<tr>
				<th>#</th>
				<th>BType</th>
				<th>Batch ID</th>
				<th>Accession</th>
				<th>DOB</th>
				<th>Lab</th>
				<th>Ref Lab</th>
				<th>Clinic</th>
				<th>Doc</th>
				<th>Report ID</th>
				<th>Status</th>
				<th>Last history file</th>
				<th>Updated</th>
				<th>Size</th>
				<th>Action</th>
			</tr>
		</thead>
		<tbody></tbody>
	</table>
</div>

<style>
	.rebuild-history-tool {
		max-width: 100%;
		box-sizing: border-box;
	}

	.rebuild-history-tool .form-grid {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
		gap: 15px;
		max-width: 1100px;
		margin-bottom: 15px;
	}

	.rebuild-history-tool .tool-notice {
		box-sizing: border-box;
		display: block;
		width: 100%;
		max-width: 1100px;
		margin: 12px 0;
		padding: 10px 12px;
		background: #fff;
		border-left: 4px solid #72aee6;
		box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
	}

	.rebuild-history-tool .tool-notice p {
		margin: 0;
		line-height: 1.5;
	}

	.rebuild-history-tool .tool-failed-details ul {
		margin: 8px 0 0 18px;
		list-style: disc;
	}

	.rebuild-history-tool .tool-failed-details li {
		margin: 6px 0;
		line-height: 1.5;
	}

	.rebuild-history-tool .tool-notice-success {
		border-left-color: #00a32a;
	}

	.rebuild-history-tool .tool-notice-warning {
		border-left-color: #dba617;
	}

	.rebuild-history-tool .tool-notice-error {
		border-left-color: #d63638;
	}

	.rebuild-history-tool label {
		display: flex;
		flex-direction: column;
		gap: 6px;
		font-weight: 600;
	}

	.rebuild-history-tool .range-field {
		display: flex;
		flex-direction: column;
		gap: 6px;
		font-weight: 600;
	}

	.rebuild-history-tool .range-title {
		display: block;
	}

	.rebuild-history-tool .date-range-inputs {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 8px;
	}

	.rebuild-history-tool .date-range-inputs label {
		gap: 4px;
		font-size: 12px;
		font-weight: 500;
		color: #555;
	}

	.rebuild-history-tool input,
	.rebuild-history-tool select {
		width: 100%;
		max-width: 100%;
	}

	.rebuild-history-tool .button-row {
		display: flex;
		gap: 10px;
		margin: 10px 0 20px;
	}

	.rebuild-history-tool .btn-primary {
		background: #0073aa;
		color: white;
		padding: 9px 16px;
		border: none;
		border-radius: 4px;
		cursor: pointer;
		font-size: 14px;
	}

	.rebuild-history-tool .btn-primary:hover {
		background: #005a87;
	}

	.rebuild-history-tool .btn-primary:disabled {
		background: #ccc;
		cursor: not-allowed;
	}

	.history-progress {
		max-width: 1100px;
		margin: 12px 0;
	}

	#history-progress-title {
		font-weight: 600;
	}

	.history-summary {
		margin: 14px 0;
		font-weight: 600;
	}

	.history-table .history-path {
		color: #666;
		font-size: 11px;
		margin-top: 4px;
		word-break: break-all;
	}

	.history-table .history-rebuild-message {
		color: #555;
		font-size: 12px;
		margin-top: 5px;
	}

	.rebuild-ok,
	.rebuild-failed {
		display: inline-block;
		margin-left: 6px;
		padding: 2px 6px;
		border-radius: 3px;
		font-size: 11px;
	}

	.rebuild-ok {
		background: #e5f5e0;
		color: #267326;
	}

	.rebuild-failed {
		background: #fbeaea;
		color: #b32d2e;
	}
</style>

<script>
	jQuery(function($) {
		var allBtypes = <?php echo wp_json_encode(array_values($all_btypes)); ?>;
		var totals = {};
		var rowCount = 0;

		if ($.fn.datepicker) {
			$('.rebuild-history-tool .history-date').datepicker({
				dateFormat: 'yy-mm-dd',
				changeMonth: true,
				changeYear: true
			});

			$('.rebuild-history-tool .history-date-from').on('change', function() {
				var range = $(this).data('range');
				var value = $(this).val() || null;
				$('.rebuild-history-tool .history-date-to[data-range="' + range + '"]').datepicker('option', 'minDate', value);
			}).trigger('change');

			$('.rebuild-history-tool .history-date-to').on('change', function() {
				var range = $(this).data('range');
				var value = $(this).val() || null;
				$('.rebuild-history-tool .history-date-from[data-range="' + range + '"]').datepicker('option', 'maxDate', value);
			}).trigger('change');
		}

		function esc(value) {
			return $('<div>').text(value == null ? '' : value).html();
		}

		function getBtypes() {
			var values = $('[name="btypes[]"]').val() || ['all'];
			if (values.indexOf('all') !== -1 || values.length === 0) {
				return allBtypes;
			}
			return values;
		}

		function hasSearchValue() {
			var hasValue = false;
			$('#history-search-form').serializeArray().forEach(function(item) {
				if (item.name === 'btypes[]' || item.name === 'history_mode' || item.name === 'trajax') {
					return;
				}
				if (item.value && item.value !== '0') {
					hasValue = true;
				}
			});
			return hasValue;
		}

		function resetUi() {
			totals = {
				db_rows: 0,
				with_history: 0,
				skipped_without_history: 0,
				skipped_history_date: 0,
				rebuilt: 0,
				failed: 0
			};
			rowCount = 0;
			$('#history-notices').empty();
			$('#history-failed-details').hide().find('ul').empty();
			$('#history-results-table tbody').empty();
			$('#history-results-table').hide();
			$('#history-summary').show();
			updateSummary(0, 0);
			$('#history-progress').show();
			$('#history-progress-title').text('Ready');
		}

		function updateSummary(step, totalSteps) {
			$('#history-db-rows').text(totals.db_rows);
			$('#history-skipped').text(totals.skipped_without_history + totals.skipped_history_date);
			$('#history-rebuilt').text(totals.rebuilt);
			$('#history-failed').text(totals.failed);
			$('#history-loaded').text(rowCount);
		}

		function addNotice(message, type) {
			$('#history-notices').append('<div class="tool-notice tool-notice-' + esc(type || 'success') + '"><p>' + esc(message) + '</p></div>');
		}

		function addFailure(detail) {
			$('#history-failed-details').show().find('ul').append(
				'<li><strong>' + esc(detail.assen) + '</strong> (' + esc(detail.btype) + ', report #' + esc(detail.re_id) + '): ' +
				esc(detail.reason) + '<div class="history-path">' + esc(detail.history_file) + '</div></li>'
			);
		}

		function appendRow(row) {
			rowCount++;
			var badge = '';
			if (row.rebuilt === true) {
				badge = ' <span class="rebuild-ok">rebuilt</span>';
			} else if (row.rebuilt === false) {
				badge = ' <span class="rebuild-failed">failed</span>';
			}
			var message = row.rebuild_message ? '<div class="history-rebuild-message">' + esc(row.rebuild_message) + '</div>' : '';
			var html = '<tr>' +
				'<td>' + rowCount + '</td>' +
				'<td>' + esc(row.btype) + '</td>' +
				'<td>' + esc(row.batch_id) + '</td>' +
				'<td>' + esc(row.assen) + '</td>' +
				'<td>' + esc(row.pa_dob || row.dob) + '</td>' +
				'<td>' + esc(row.lab_name) + '</td>' +
				'<td>' + esc(row.ref_lab_name) + '</td>' +
				'<td>' + esc(row.cli_clinic) + '</td>' +
				'<td>' + esc(row.doc) + '</td>' +
				'<td>' + esc(row.re_id) + '</td>' +
				'<td>' + esc((row.estatus || '') + ' / final:' + (row.final_results || '0')) + '</td>' +
				'<td>' + esc(row.history_name) + badge + '<div class="history-path">' + esc(row.history_file) + '</div>' + message + '</td>' +
				'<td>' + esc(row.history_mtime) + '</td>' +
				'<td>' + esc(row.history_size) + '</td>' +
				'<td><a target="_blank" href="' + esc(row.pdf_url) + '">View PDF</a></td>' +
				'</tr>';
			$('#history-results-table').show().find('tbody').append(html);
		}

		function runHistory(mode) {
			if (!hasSearchValue()) {
				$('#history-notices').html('<div class="tool-notice tool-notice-warning"><p>Please enter at least one search field.</p></div>');
				return;
			}

			resetUi();
			var btypes = getBtypes();
			var totalSteps = btypes.length;
			var step = 0;
			$('.rebuild-history-tool .btn-primary').prop('disabled', true);

			function runNext() {
				if (step >= totalSteps) {
					$('#history-progress-title').text('Completed ' + totalSteps + ' step(s).');
					if (mode === 'rebuild') {
						addNotice('Rebuilt ' + totals.rebuilt + ' history file(s).', 'success');
						if (totals.failed > 0) {
							addNotice('Failed ' + totals.failed + ' file(s).', 'error');
						}
					}
					if (rowCount === 0) {
						addNotice('No matching result has a history file.', 'warning');
					}
					$('.rebuild-history-tool .btn-primary').prop('disabled', false);
					return;
				}

				var btype = btypes[step];
				var displayStep = step + 1;
				$('#history-progress-title').text('Step ' + displayStep + '/' + totalSteps + ': loading ' + btype + '...');

				var data = $('#history-search-form').serializeArray();
				data.push({
					name: 'trajax',
					value: 'history_load_btype'
				});
				data.push({
					name: 'history_mode',
					value: mode
				});
				data.push({
					name: 'btype',
					value: btype
				});

				$.ajax({
					type: 'POST',
					dataType: 'json',
					data: data,
					success: function(res) {
						if (!res || !res.success) {
							addNotice((res && res.data && res.data.message) ? res.data.message : 'AJAX request failed for ' + btype + '.', 'error');
							return;
						}
						var stats = res.data.stats || {};
						totals.db_rows += parseInt(stats.db_rows || 0, 10);
						totals.with_history += parseInt(stats.with_history || 0, 10);
						totals.skipped_without_history += parseInt(stats.skipped_without_history || 0, 10);
						totals.skipped_history_date += parseInt(stats.skipped_history_date || 0, 10);
						totals.rebuilt += parseInt(stats.rebuilt || 0, 10);
						totals.failed += parseInt(stats.failed || 0, 10);

						(res.data.rows || []).forEach(appendRow);
						(res.data.failed_details || []).forEach(addFailure);
					},
					error: function(xhr) {
						addNotice('AJAX request failed for ' + btype + ': ' + (xhr.responseText || xhr.statusText), 'error');
					},
					complete: function() {
						step++;
						updateSummary(step, totalSteps);
						runNext();
					}
				});
			}

			runNext();
		}

		$('#history-search-btn').on('click', function() {
			runHistory('search');
		});

		$('#history-rebuild-btn').on('click', function() {
			runHistory('rebuild');
		});
	});
</script>