<?php
global $wpdb;
global $is_ci_admin;


$current_user = wp_get_current_user();
$can_edit_statuses = current_user_can('ci_edit_statuses') || $is_ci_admin;

if (!$can_edit_statuses) {
	echo 'You do not have permission to access this page.';
	return;
}

$types = array(
	're' => 'Rejected',
	'in' => 'Incomplete',
	'ca' => 'Cancelled',
);

if (tr_get_option('ci_req_settings_blood', 'Phlebotomist') && in_array('blood', tr_pdd_get_batch_types())) {
	$types['so'] = 'Standing Order';
}
$act = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';

if ($act == 'save_bstatus') {

	ob_clean();
	$return = array('status' => 'ok');
	$k = $_REQUEST['key'];
	$vl = $_REQUEST['value'] ? 1 : 0;
	$id = $_REQUEST['id'];
	$wpdb->update('ci_batches_status', array($k => $vl), array('ID' => $id));

	wp_send_json($return);
}
if ($act == 'export_status') {
	ob_clean();
	$rs = $wpdb->get_results(
		"SELECT s_key, s_text, s_type, istatus, iorder,
		        show_in_req_form, show_in_inbound, show_in_result_form
		 FROM ci_batches_status
		 WHERE btype = '{$tes_type}'
		 ORDER BY iorder ASC",
		ARRAY_A
	);
	error_log(print_r($rs, true));

	$format = isset($_REQUEST['format']) ? $_REQUEST['format'] : 'json';

	if ($format === 'csv') {
		header('Content-type: application/csv');
		header('Content-Disposition: attachment; filename="statuses_' . $tes_type . '_' . date('Ymd_His') . '.csv"');
		$out = fopen('php://output', 'w');
		if (!empty($rs)) {
			fputcsv($out, array_keys($rs[0]));
			foreach ($rs as $row) {
				fputcsv($out, $row);
			}
		}
		fclose($out);
	} else {
		header('Content-Type: application/json');
		header('Content-Disposition: attachment; filename="statuses_' . $tes_type . '_' . date('Ymd_His') . '.json"');
		echo json_encode($rs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	}
	exit;
}

// ── IMPORT: read uploaded file and insert/update rows ────────────────────────
if ($act == 'do_import_status' && isset($_FILES['import_file'])) {
	ob_clean();
	$return = array('status' => 'ok', 'inserted' => 0, 'updated' => 0, 'errors' => array());

	$file     = $_FILES['import_file'];
	$ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
	$content  = file_get_contents($file['tmp_name']);
	$rows     = array();

	if ($ext === 'json') {
		$decoded = json_decode($content, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			$return['status']   = 'error';
			$return['errors'][] = 'Invalid JSON file.';
			wp_send_json($return);
		}
		$rows = $decoded;
	} elseif ($ext === 'csv') {
		$lines = array_filter(array_map('trim', explode("\n", $content)));
		$headers = null;
		foreach ($lines as $line) {
			$cols = str_getcsv($line);
			if ($headers === null) {
				$headers = $cols;
				continue;
			}
			if (count($cols) === count($headers)) {
				$rows[] = array_combine($headers, $cols);
			}
		}
	} else {
		$return['status']   = 'error';
		$return['errors'][] = 'Only .json and .csv files are supported.';
		wp_send_json($return);
	}

	$allowed_fields = array(
		's_key',
		's_text',
		's_type',
		'istatus',
		'iorder',
		'show_in_req_form',
		'show_in_inbound',
		'show_in_result_form',
	);

	$import_mode = isset($_POST['import_mode']) ? $_POST['import_mode'] : 'skip'; // skip | overwrite

	foreach ($rows as $idx => $row) {
		// Only keep known columns; always force btype
		$data = array_intersect_key($row, array_flip($allowed_fields));
		$data['btype'] = $tes_type;

		if (empty($data['s_text'])) {
			$return['errors'][] = "Row " . ($idx + 1) . ": missing s_text, skipped.";
			continue;
		}

		// Check if s_key already exists for this btype
		if (!empty($data['s_key'])) {
			$exists = $wpdb->get_var($wpdb->prepare(
				"SELECT ID FROM ci_batches_status WHERE s_key = %s AND btype = %s",
				$data['s_key'],
				$tes_type
			));
		} else {
			$exists = false;
		}

		if ($exists) {
			if ($import_mode === 'overwrite') {
				$wpdb->update('ci_batches_status', $data, array('ID' => $exists));
				$return['updated']++;
			}
			// else: skip silently
		} else {
			if (empty($data['s_key'])) {
				$data['s_key'] = $data['s_text'];
				if (strpos($data['s_key'], '-') > 0) {
					list($prefix, $data['s_key']) = explode('-', $data['s_key'], 2);
				}
				$data['s_key'] = strtolower(str_replace(' ', '_', $data['s_key']));
				$data['s_key'] = substr($data['s_key'], 0, 20); // enforce max length

			}

			if (empty($data['iorder'])) {
				$data['iorder'] = $wpdb->get_var(
					"SELECT MAX(iorder) FROM ci_batches_status WHERE btype='{$tes_type}'"
				) + 1;
			}
			$wpdb->insert('ci_batches_status', $data);
			$new_id = $wpdb->insert_id;
			if (empty($data['s_key'])) {
				$wpdb->update('ci_batches_status', array('s_key' => $new_id), array('ID' => $new_id));
			}
			$return['inserted']++;
		}
	}

	wp_send_json($return);
	exit;
}
if ($act == 'add' || $act == 'edit') {

	$batch_status = array();
	if (isset($_POST['batch_status'])) {
		$batch_status = stripslashes_deep($_POST['batch_status']);

		$batch_status['btype'] = $tes_type;

		if ($batch_status['ID'] > 0) {
			if (empty($batch_status['s_key'])) {
				$batch_status['s_key'] = $batch_status['ID'];
			}


			$rs = $wpdb->update('ci_batches_status', $batch_status, array('ID' => $batch_status['ID']));

			Tr_Session_Class::add('Saved Successfully ' . $batch_status['s_text']);
		} else {
			$batch_status['iorder'] = $wpdb->get_var("select max(iorder) from ci_batches_status where btype='{$tes_type}'") + 1;
			$batch_status['s_text'] = str_replace("\r", "\n", $batch_status['s_text']);
			$list = explode("\n", $batch_status['s_text']);
			foreach ($list as $title) {
				$title = trim($title);
				if (!empty($title)) {
					$stkey = '';
					if (stripos($title, "|") > 0) {
						list($title, $stkey) = explode("|", $title);
					}

					$batch_status['s_text'] = $title;

					if (empty($stkey)) {
						$stkey = $batch_status['s_text'];
						if (strpos($stkey, '-') > 0) {
							list($prefix, $stkey) = explode('-', $stkey, 2);
						}
						$stkey = strtolower(str_replace(' ', '_', $stkey));
						$stkey = substr($stkey, 0, 20); // enforce max length

					}

					$rs = $wpdb->insert('ci_batches_status', $batch_status);
					$id = $wpdb->insert_id;

					$batch_status['s_key'] = !empty($stkey) ? $stkey :  $id;
					$rs = $wpdb->update('ci_batches_status', array('s_key' => $batch_status['s_key']), array('ID' => $id));
					$batch_status['iorder']++;
					Tr_Session_Class::add('Added Successfully ' . $batch_status['s_text']);
				}
			}
		}


		$batch_status = $_POST = array();
	}

	if (isset($_REQUEST['id']) && $_REQUEST['id'] > 0) {
		$id = $_REQUEST['id'];
		$batch_status = $wpdb->get_row("select * from ci_batches_status where ID = {$id}", ARRAY_A);
	}
?>
	<h2><?php echo ($act == 'add') ? 'Add New' : 'Edit' ?> Status <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">All Status</a>
		<?php if ($act == 'edit') : ?><a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2">Add New</a><?php endif; ?>
	</h2>
	<?php Tr_Session_Class::show() ?>
	<form method="post">
		<div class="frow">
			<div class="">
				<div class="frow">
					<label>Name <?php echo !empty($batch_status['s_key']) ? '( ' . $batch_status['s_key'] . ' )' : '' ?></label>
					<?php if ($act == 'edit') { ?>
						<input type="text" name="batch_status[s_text]" value="<?php echo isset($batch_status['s_text']) ? $batch_status['s_text'] : '' ?>" />
					<?php } else {
					?>
						<textarea name="batch_status[s_text]"></textarea>
					<?php
					} ?>

				</div>
				<div class="frow">
					<label>Type</label>
					<?php tr_radio('batch_status[s_type]', array_merge(array('' => 'None'), $types), isset($batch_status['s_type']) ? $batch_status['s_type'] : '') ?>
				</div>
				<div class="frow">
					<input type="hidden" name="batch_status[istatus]" value="0" />
					<label><input type="checkbox" name="batch_status[istatus]" value="1" <?php checked(isset($batch_status['istatus']) ? $batch_status['istatus'] : '1') ?> /> Enable</label>

				</div>
				<div class="frow">
					<input type="submit" class="button-primary" value="Save" />
					<input type="hidden" name="batch_status[ID]" value="<?php echo isset($batch_status['ID']) ? $batch_status['ID'] : '' ?>" />
					<input type="hidden" name="batch_status[s_key]" value="<?php echo isset($batch_status['s_key']) ? $batch_status['s_key'] : '' ?>" />
				</div>
			</div>
		</div>
	</form>
	<script>
		jQuery(function($) {
			$('body').on('change keyup', 'input[name="batch_status[s_text]"]', function() {
				if ($(this).val().indexOf('Rejected') > -1) {
					$('[name="batch_status[s_type]"][value="re"]').prop('checked', true);
				} else if ($(this).val().indexOf('Incomplete') > -1) {
					$('[name="batch_status[s_type]"][value="in"]').prop('checked', true);
				}
			})
		})
	</script>
<?php
} else {

	include_once(TRCPDD_PATH . 'parts/scripts/add_status_standing_orders.php');
	wp_enqueue_script('jquery-ui-sortable');

	if (isset($_POST['save'])) {
		$items = array();

		foreach ($_POST['postions'] as $id => $pos) {
			$wpdb->update('ci_batches_status', array('iorder' => $pos), array('ID' => $id));
		}


		Tr_Session_Class::add('Saved Successfully!');
	}

	if ($act == 'pdelete' && $_POST['idd'] > 0) {
		$wpdb->delete('ci_batches_status', array('ID' => $_POST['idd']));
		Tr_Session_Class::add("Deleted Successfully");
	}
	$rs = $wpdb->get_results("select * from ci_batches_status where btype='{$tes_type}' order by iorder asc");

	if (count($rs) == 0) {
		//import
		trpdd_plugin_activate();

		$statuss = tr_pdd_get_batch_statuses(false, false, false, $tes_type);
		$iorder = 0;
		foreach ($statuss as $k => $txt) {
			if (empty($k)) continue;
			$iorder++;
			$stype = '';
			if ($k == 'rejected') {
				$stype = 're';
			} else if ($k == 'incomplete') {
				$stype = 'in';
			}
			if (strpos($k, '_') > 0) {
				list($stype, $k) = explode('_', $k);
			}

			$arr = array(
				's_key' => $k,
				's_text' => $txt,
				's_type' => $stype,
				'btype' => $tes_type,
				'iorder' => $iorder
			);
			$wpdb->insert('ci_batches_status', $arr);
		}
		$rs = $wpdb->get_results("select * from ci_batches_status where btype='{$tes_type}' order by iorder asc");
	}
?>
	<h2>Statuses for <?php echo strtoupper($tes_type) ?>
		<a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2">Add New</a>
		<a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '', 'tabtype' => '')) ?>" class="add-new-h2">Tests</a>
	</h2>
	<?php Tr_Session_Class::show() ?>

	<div class="export-import-bar" style="margin-bottom:16px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">

		<!-- Export buttons -->
		<a href="<?php Tr_Base_Class_V6::link(array('act' => 'export_status', 'format' => 'json', 'trajax' => 1)) ?>"
			class="button">⬇ Export JSON</a>
		<a href="<?php Tr_Base_Class_V6::link(array('act' => 'export_status', 'format' => 'csv', 'trajax' => 1)) ?>"
			class="button">⬇ Export CSV</a>

		<!-- Import trigger -->
		<button type="button" class="button" id="btn-toggle-import">⬆ Import</button>
	</div>

	<!-- Import panel (hidden by default) -->
	<div id="import-panel" style="display:none; background:#f9f9f9; border:1px solid #ddd; padding:16px; margin-bottom:16px;">
		<h3 style="margin-top:0;">Import Statuses</h3>
		<p style="color:#555; font-size:13px;">
			Supported formats: <strong>.json</strong>, <strong>.csv</strong><br>
			The file must match the export structure (columns: <code>s_key, s_text, s_type, istatus, iorder, show_in_req_form, show_in_inbound, show_in_result_form</code>).
		</p>
		<div class="frow" style="margin-bottom:10px;">
			<label style="display:block; margin-bottom:4px;"><strong>File</strong></label>
			<input type="file" id="import-file" accept=".json,.csv" />
		</div>
		<div class="frow" style="margin-bottom:12px;">
			<label style="display:block; margin-bottom:4px;"><strong>If s_key already exists</strong></label>
			<label style="margin-right:12px;">
				<input type="radio" name="import_mode" value="skip" checked /> Skip (keep existing)
			</label>
			<label>
				<input type="radio" name="import_mode" value="overwrite" /> Overwrite
			</label>
		</div>
		<button type="button" class="button-primary" id="btn-do-import">Import</button>
		<span id="import-spinner" style="display:none; margin-left:8px;">⏳ Importing…</span>
		<div id="import-result" style="margin-top:10px;"></div>
	</div>

	<div class="wraptbl">
		<form method="post">
			<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
				<thead>
					<tr>
						<th style="width:50px">#</th>
						<th style="width:40%">Name</th>
						<th style="width:60px">Position</th>
						<th style="width:80px">Type</th>

						<th>Req Form</th>
						<th>Inbound</th>
						<th>Results Form</th>

						<th style="width:40px">Active</th>
						<th style="width:80px">Actions</th>
					</tr>
				</thead>
				<tbody id="tablesort">
					<?php foreach ($rs as $i => $te) {
						$te->editable = true;
						$te->can_delete = true;
						if (in_array($te->s_key, array('pending', 'completed', 'cancelled', 'rejected', 'incomplete', 'received', 'archived', 'processing', 'hold', 'resulted', 'plated', 'received'))) {
							if (!$is_ci_admin) {
								$te->editable = false;
							}

							$te->can_delete = false;
						} else if ($te->s_type == 'so' && !$is_ci_admin) {
							$te->editable = false;
							$te->can_delete = false;
						}
					?>
						<tr data-id="<?php echo $te->ID ?>">
							<td><?php echo $i + 1 ?></td>
							<td><?php echo $te->s_text ?></td>
							<td class="position"><input type="number" name="postions[<?php echo $te->ID ?>]" value="<?php echo $te->iorder ?>" /> </td>
							<td><?php echo isset($types[$te->s_type]) ? $types[$te->s_type] : '' ?></td>

							<td class="checkboxable center"><input type="checkbox" value="<?php echo $te->ID ?>" data-k="show_in_req_form" <?php echo $te->editable ? '' : 'disabled' ?> <?php checked($te->show_in_req_form, 1) ?> /> </td>
							<td class="checkboxable center"><input type="checkbox" value="<?php echo $te->ID ?>" data-k="show_in_inbound" <?php echo $te->editable ? '' : 'disabled' ?> <?php checked($te->show_in_inbound, 1) ?> /> </td>
							<td class="checkboxable center"><input type="checkbox" value="<?php echo $te->ID ?>" data-k="show_in_result_form" <?php echo $te->editable ? '' : 'disabled' ?> <?php checked($te->show_in_result_form, 1) ?> /> </td>

							<td class="checkboxable center"><input type="checkbox" value="<?php echo $te->ID ?>" data-k="istatus" <?php echo $te->editable ? '' : 'disabled' ?> <?php checked($te->istatus, 1) ?> /> </td>
							<td>
								<?php if ($te->can_delete) { ?>
									<a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $te->ID)) ?>">Edit</a> -
									<a class="pdelete" href="#" data-id="<?php echo $te->ID ?>">Delete</a>
								<?php } ?>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>

			<div class="frow actions" style="padding-top:20px">
				<input type="submit" name="save" value="Save" class="button-primary" />
			</div>
		</form>
	</div>


	<div class="total">
		Total: <?php echo count($rs); ?>
	</div>

	<script>
		jQuery(function($) {
			function trpdd_save_bstatus($pr, $id, $k, $value) {
				$.ajax({
					type: 'POST',
					dataType: 'json',
					data: {
						trajax: '1',
						'act': 'save_bstatus',
						id: $id,
						key: $k,
						value: $value
					},
					success: function(rs) {
						if (rs.status == 'ok') {

						} else {
							alert('Error Please try again');
						}
						$('.sloading', $pr).remove();

					},
					error: function() {
						setTimeout(function() {
							trpdd_save_bstatus($pr, $id, $k, $value);
						}, 1000);
					}
				})
			}

			$('body').on('click', 'td.checkboxable input', function() {
				var $pr = $(this).parents('td.checkboxable');
				var $value = $(this).is(':checked') ? 1 : 0;
				var $id = $pr.parent().data('id');
				var $k = $(this).data('k');
				if ($pr.find('.sloading').length > 0) return false;

				$pr.append('<div class="sloading"></div>');
				trpdd_save_bstatus($pr, $id, $k, $value);
			}).on('change', '#tablesort td.position input', function() {
				var $this = $(this);
				var $cur = $(this).parents('tr');
				var $vl = parseInt($(this).val());
				//find new pos

				for (var $newpos = $vl; $newpos > 0; $newpos--) {
					var $found = false;
					$('#tablesort td.position input').each(function() {
						if ($(this).attr('name') != $this.attr('name')) {
							var $cpos = parseInt($(this).val());
							if ($cpos == $newpos) {
								$found = $(this);
							}
						}

					})
					if ($found !== false) {
						var $it = $found.parents('tr');
						if ($newpos >= $vl) {
							$cur.insertBefore($it);
						} else {
							$cur.insertAfter($it);
						}

						break;
					}
				}
				var $pos = 0;
				$('#tablesort td.position input').each(function() {
					$pos++;
					$(this).val($pos);

				})

			}).on('focus', '#tablesort td.position input', function() {
				$(this).select();
			})


			$('#tablesort').sortable({
				stop: function(event, ui) {
					var $pos = 0;
					$('td.position input').each(function() {
						$pos++;
						$(this).val($pos);

					})
				}
			});

			$('#btn-toggle-import').on('click', function() {
				$('#import-panel').slideToggle(200);
			});

			$('#btn-do-import').on('click', function() {
				var file = $('#import-file')[0].files[0];
				if (!file) {
					alert('Please select a file first.');
					return;
				}

				var ext = file.name.split('.').pop().toLowerCase();
				if (ext !== 'json' && ext !== 'csv') {
					alert('Only .json and .csv files are supported.');
					return;
				}

				var mode = $('input[name="import_mode"]:checked').val();
				var fd = new FormData();
				fd.append('trajax', '1');
				fd.append('act', 'do_import_status');
				fd.append('import_mode', mode);
				fd.append('import_file', file);

				$('#import-spinner').show();
				$('#import-result').html('');
				$('#btn-do-import').prop('disabled', true);

				$.ajax({
					type: 'POST',
					data: fd,
					dataType: 'json',
					processData: false,
					contentType: false,
					success: function(rs) {
						var html = '';
						if (rs.status === 'ok') {
							html += '<p style="color:green;">✔ Done! Inserted: <strong>' + rs.inserted +
								'</strong>, Updated: <strong>' + rs.updated + '</strong>.</p>';
							if (rs.errors.length) {
								html += '<ul style="color:orange; font-size:12px;">';
								$.each(rs.errors, function(i, e) {
									html += '<li>' + e + '</li>';
								});
								html += '</ul>';
							}
							// reload to show new rows
							setTimeout(function() {
								location.reload();
							}, 1500);
						} else {
							html += '<p style="color:red;">✖ Error: ' + (rs.errors.join(', ') || 'Unknown error') + '</p>';
						}
						$('#import-result').html(html);
					},
					error: function() {
						$('#import-result').html('<p style="color:red;">✖ Request failed. Please try again.</p>');
					},
					complete: function() {
						$('#import-spinner').hide();
						$('#btn-do-import').prop('disabled', false);
					}
				});
			});
		})
	</script>

<?php
}
