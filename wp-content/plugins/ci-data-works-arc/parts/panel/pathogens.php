<?php
global $wpdb;
if (empty($tes_type)) {
	return;
}
$mic_type = tr_get_option('ci_result_settings_pcr', 'e_mic2', 0);
$te_type = 'pats';
if ((isset($_GET['tabtype']) && $_GET['tabtype'] == 'cu_pathogens')) {
	$te_type  = 'cupat';
} else if ((isset($_GET['tabtype']) && $_GET['tabtype'] == 'cu_antibiotics')) {
	$te_type  = 'cuant';
}


if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'remove_row') {

	$return = array('status' => 'ok');
	$id = $_REQUEST['id'];

	$wpdb->delete('ci_test_panels', array('te_ID' => $id, 'te_type' => $te_type));

	trpdd_log_user(get_current_user_id(), 'rmpats', $id);

	ob_clean();
	wp_send_json($return);
	exit;
} else if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'export') {
	$rs = $wpdb->get_col("select te_name from ci_test_panels 
						where  te_type='{$te_type}' ");
	echo implode("\n", $rs);
	exit;
}

wp_enqueue_script('jquery-ui-sortable');

$panels = $wpdb->get_results("select tg_ID, tg_name, tg_cpt_code from ci_test_groups where tg_parent = 0 and tg_show_req = 1 order by tg_name asc");
$panel_names_by_id = array();
foreach ($panels as $panel) {
	$panel_names_by_id[$panel->tg_ID] = $panel->tg_name;
	$panel->sub_panels = $wpdb->get_results("select tg_ID, tg_name,tg_cpt_code from ci_test_groups where tg_parent = {$panel->tg_ID} order by tg_name asc");
	foreach ($panel->sub_panels as $sub_panel) {
		$panel_names_by_id[$sub_panel->tg_ID] = $sub_panel->tg_name . ' (' . $panel->tg_cpt_code . ')';
	}
}

include_once(TRCPDD_PATH . 'pages/formdata.php');

if (isset($_POST['import_settings'])) {
	if (isset($_FILES['settings_import_file']) && !empty($_FILES['settings_import_file']['tmp_name'])) {
		$file = fopen($_FILES['settings_import_file']['tmp_name'], 'r');
		$headers = $file ? fgetcsv($file) : false;
		$header_map = array();
		if (is_array($headers)) {
			foreach ($headers as $i => $header) {
				$header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
				$header_map[strtolower(trim($header))] = $i;
			}
		}

		if (!empty($header_map) && isset($header_map['pathogen'])) {
			$count_insert = 0;
			$count_update = 0;
			while (($data = fgetcsv($file)) !== false) {
				$te_name = isset($data[$header_map['pathogen']]) ? trim($data[$header_map['pathogen']]) : '';
				if (empty($te_name)) {
					continue;
				}
				$row = array(
					'te_ID' => isset($header_map['id']) && isset($data[$header_map['id']]) ? (int)$data[$header_map['id']] : 0,
					'te_name' => $te_name,
					'te_type' => $te_type,
					'te_position' => isset($header_map['order']) && isset($data[$header_map['order']]) ? (int)$data[$header_map['order']] : 0,
					'te_start' => date_i18n('Y-m-d H:i:s'),
				);
				$existing_id = $wpdb->get_var($wpdb->prepare("select te_ID from ci_test_panels where te_name = %s and te_type = %s limit 1", $te_name, $te_type));
				if ($existing_id) {
					unset($row['te_ID']);
					unset($row['te_start']);
					if ($wpdb->update('ci_test_panels', $row, array('te_ID' => $existing_id)) !== false) {
						$count_update++;
					}
					continue;
				}
				if (empty($row['te_ID']) || $wpdb->get_var($wpdb->prepare("select te_ID from ci_test_panels where te_ID = %d", $row['te_ID']))) {
					unset($row['te_ID']);
				}
				if ($wpdb->insert('ci_test_panels', $row) !== false) {
					$count_insert++;
				}
			}
			if ($file) {
				fclose($file);
			}
			trpdd_log_user(0, 'import_pathogens_' . $te_type, array('inserted' => $count_insert, 'updated' => $count_update));
			Tr_Session_Class::add('Inserted: ' . $count_insert);
			Tr_Session_Class::add('Updated: ' . $count_update);
		} else {
			if ($file) {
				fclose($file);
			}
			Tr_Session_Class::add('Invalid import file.');
		}
	} else {
		Tr_Session_Class::add('Please select an import file.');
	}
}

if (isset($_POST['importanti'])) {


	if (!empty($_POST['rows'])) {
		$rows = array();
		$lines = explode("\n", $_POST['rows']);
		foreach ($lines as $l) {
			$l = trim($l);
			if (empty($l)) continue;
			$rows[] = array($l);
		}
		$data = array('data' => $rows);
	} else {
		$data = trpdd_get_data($_FILES['importfile']['tmp_name'], null, array(), true, true);
	}

	$count = 0;
	foreach ($data['data'] as $row) {
		$te_name = trim($row[0]);

		if (empty($te_name) || strtoupper($te_name) == 'DRUGS') continue;
		$count++;


		$exist = $wpdb->get_var("select te_ID from ci_test_panels 
						where `te_name`='{$te_name}' and te_type='{$te_type}' ");
		if (!$exist) {
			$wpdb->insert('ci_test_panels', array(
				'te_name' => $te_name,
				'te_type' => $te_type,
				'te_start' => date_i18n('Y-m-d H:i:s'),

			));
		} else {
			$wpdb->update('ci_test_panels', array('te_name' => $te_name), array('te_ID' => $exist));
		}
	}
	if ($count > 0) {
		Tr_Session_Class::add("import success");
	} else {
		Tr_Session_Class::add_error("cant import. Missing column drugs");
	}
}
if (isset($_POST['save'])) {
	$items = array();
	$iorder = count($_POST['settings']) + 10;
	foreach ($_POST['settings'] as $k => $data) {
		$iorder--;
		$data['te_position'] = $iorder;
		$data['te_name'] = trim($data['te_name']);
		//$data['te_name'] = ucwords(strtolower($data['te_name']));
		$data['te_type'] = $te_type;

		if (isset($data['position'])) {
			$data['te_position'] = count($_POST['settings']) + 10 - $data['position'];

			unset($data['position']);
		}

		if ($mic_type == 2) {
			if (!is_array($data['te_groups_pathogen'])) {
				$data['te_groups_pathogen'] = array();
			}
			$data['te_groups_pathogen'] = implode(',', $data['te_groups_pathogen']);
		} else {
			unset($data['te_groups_pathogen']);
		}

		if (stripos($k, 'new_') !== false) {
			$data['te_start'] = date_i18n('Y-m-d H:i:s');

			$wpdb->insert('ci_test_panels', $data);
		} else {
			$wpdb->update('ci_test_panels', $data, array('te_ID' => $k));
		}
	}

	trpdd_log_form('diag', $_POST['settings']);

	trpdd_log_user(get_current_user_id(), 'savediag', '');
	Tr_Session_Class::add('Saved Successfully!');
}

$items = $wpdb->get_results("select te_ID, te_name, te_position, te_groups_pathogen
            from ci_test_panels 
                where te_type='{$te_type}'
				order by te_position desc, te_name asc");
$export_items = array();
foreach ($items as $item) {
	$row = array(
		'ID' => $item->te_ID,
		'Pathogen' => $item->te_name,
	);
	if ($mic_type == 2) {
		$group_names = array();
		$group_ids = !empty($item->te_groups_pathogen) ? explode(',', $item->te_groups_pathogen) : array();
		foreach ($group_ids as $group_id) {
			$group_id = trim($group_id);
			if (isset($panel_names_by_id[$group_id])) {
				$group_names[] = $panel_names_by_id[$group_id];
			}
		}
		$row['Group'] = implode(' | ', $group_names);
	}
	$row['Order'] = isset($item->te_position) ? $item->te_position : '';
	$export_items[] = $row;
}

$title = 'Pathogens';
if ($_GET['tabtype'] == 'cu_pathogens') {
	$title  = 'CU Pathogens';
} else if ($_GET['tabtype'] == 'cu_antibiotics') {
	$title  = 'CU Antibiotics';
}
?>
<div class="wrap">
	<h2><?php echo $title ?>
		<a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '', 'tabtype' => '')) ?>" class="add-new-h2">All Tests</a>
		<a href="#" class="add-new-h2 trpdd-panel-import-export">Import/Export</a>
	</h2>
	<?php Tr_Session_Class::show() ?>
	<div id="trpdd_panel_import_export_modal" style="display:none;position:fixed;z-index:100000;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.35);">
		<div style="background:#fff;width:420px;max-width:90%;margin:120px auto 0;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.2);">
			<h3 style="margin-top:0;">Import/Export <?php echo $title ?></h3>
			<form method="post" enctype="multipart/form-data">
				<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
					<input type="file" name="settings_import_file" accept="text/csv,.csv" />
					<input type="submit" name="import_settings" class="button" value="Import" />
					<a href="#" class="button button-primary trpdd-export-settings">Export</a>
					<a href="#" class="button trpdd-close-panel-import-export">Close</a>
				</div>
			</form>
		</div>
	</div>
	<form method="post">

		<div class="group_type">
			<div class="frow">
				<div class="col2">
					<h4>Pathogen</h4>
				</div>
				<?php if ($mic_type == 2) { ?>
					<div class="col5">
						<h4>Group</h4>
					</div>
				<?php } ?>
				<div class="col7">
					<h4>Action</h4>
				</div>
			</div>
			<div id="type_items" data-items="<?php echo esc_attr(json_encode($items)) ?>"></div>
			<a class="button add_item">+</a>
		</div>
		<div class="frow actions" style="padding-top:20px">
			<input type="submit" name="save" value="Save" class="button-primary" />
		</div>
	</form>
</div>

<div style="display: none" id="default_item">
	<div class="item frow">
		<div class="col2">
			<input type="text" class="te_name" />
		</div>
		<?php if ($mic_type == 2) { ?>
			<div class="col5">
				<select class="te_groups_pathogen" multiple>
					<?php
					foreach ($panels as $panel) {
						echo '<option value="' . $panel->tg_ID . '">' . $panel->tg_name . '</option>';
						if (isset($panel->sub_panels) && is_array($panel->sub_panels)) {
							foreach ($panel->sub_panels as $sub_panel) {
								echo '<option value="' . $sub_panel->tg_ID . '">&nbsp;&nbsp;- ' . $sub_panel->tg_name . '(' . $panel->tg_cpt_code . ')</option>';
							}
						}
					}
					?>
				</select>
			</div>
		<?php } ?>
		</script>
		<div class="col7">
			<a class="button remove_item">-</a>
			<span class="dashicons dashicons-menu"></span>
		</div>
	</div>
</div>

<style>
	.ui-sortable-handle {
		cursor: move;
	}

	#type_items select {
		width: 100%;
	}

	#type_items .select2 {
		width: 100% !important;
	}
</style>
<script>
	jQuery(function($) {
		var trpddSettingsExport = <?php echo wp_json_encode($export_items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
		var trpddSettingsExportFilename = <?php echo wp_json_encode($tes_type . '-' . (isset($_GET['tabtype']) ? $_GET['tabtype'] : 'pathogens') . '-' . date_i18n('Ymd-His') . '.csv') ?>;
		var $trow = 0;
		var trpddShowGroupColumn = <?php echo $mic_type == 2 ? 'true' : 'false' ?>;

		function add_item($data, $next) {
			var $default_html = $($('#default_item').html());
			var $tid = $data['te_ID'];
			$trow++;
			if (!$tid) {
				$tid = 'new_' + $trow;
			}
			$default_html.data('id', $tid);
			$('.te_name', $default_html).attr('name', 'settings[' + $tid + '][te_name]').val($data['te_name']);
			$('.position', $default_html).attr('name', 'settings[' + $tid + '][position]').val($trow).data('oldpos', $trow);
			if ($('.te_groups_pathogen', $default_html).length > 0) {
				$('.te_groups_pathogen', $default_html).attr('name', 'settings[' + $tid + '][te_groups_pathogen][]');
				if ($data['te_groups_pathogen']) {
					var $groups = $data['te_groups_pathogen'].split(',');
					for (var i in $groups) {
						$('.te_groups_pathogen option[value="' + $groups[i] + '"]', $default_html).prop('selected', true);
					}
				}
			}
			$('select', $default_html).select2();
			$('#type_items').append($default_html);
		}

		function init_items() {
			var $type_items = $('#type_items');
			var $items = $type_items.data('items');
			for (var i in $items) {
				add_item($items[i]);
			}
		}
		$('body').on('click', 'a.add_item', function() {
			add_item({});
		}).on('click', 'a.add_next', function() {
			var $pr = $(this).parents('.item');
			add_item({}, $pr);
		}).on('click', 'a.remove_item', function() {
			if (confirm('Do you want remove?')) {
				var $this = $(this);
				var $pr = $(this).parents('.item');
				var $id = $pr.data('id');
				$this.html('Removing...');
				$.ajax({
					type: 'post',
					dataType: 'json',
					data: {
						trajax: 'remove_row',
						id: $id
					},
					success: function(json) {
						if (json.status == 'ok') {
							$pr.remove();
						} else {
							alert('Error. Please try again!');
							$this.html('-');
						}
					},
					error: function() {
						alert('Error. Please try again!');
						$this.html('-');
					}
				})
			}
			return false;
		}).on('change', '#type_items input.position', function() {
			var $this = $(this);
			var $cur = $(this).parents('div.item');
			var $vl = parseInt($(this).val());
			//find new pos

			for (var $newpos = $vl; $newpos > 0; $newpos--) {
				var $found = false;
				$('#type_items input.position').each(function() {
					if ($(this).attr('name') != $this.attr('name')) {
						var $cpos = parseInt($(this).val());
						if ($cpos == $newpos) {
							$found = $(this);
						}
					}

				})
				if ($found !== false) {
					var $it = $found.parents('div.item');
					if ($newpos >= $vl) {
						$cur.insertBefore($it);
					} else {
						$cur.insertAfter($it);
					}

					break;
				}
			}
			var $pos = 0;
			$('#type_items input.position').each(function() {
				$pos++;
				$(this).val($pos);

			})

		}).on('focus', '#type_items input.position', function() {
			$(this).select();
		}).on('click', '.trpdd-panel-import-export', function(e) {
			e.preventDefault();
			$('#trpdd_panel_import_export_modal').show();
		}).on('click', '.trpdd-close-panel-import-export', function(e) {
			e.preventDefault();
			$('#trpdd_panel_import_export_modal').hide();
		}).on('click', '#trpdd_panel_import_export_modal', function(e) {
			if (e.target === this) {
				$(this).hide();
			}
		}).on('click', '.trpdd-export-settings', function(e) {
			e.preventDefault();
			var headers = ['ID', 'Pathogen'];
			if (trpddShowGroupColumn) headers.push('Group');
			headers.push('Order');
			var csvEscape = function(value) {
				value = value == null ? '' : String(value);
				return '"' + value.replace(/"/g, '""') + '"';
			};
			var csv = headers.map(csvEscape).join(',') + "\n";
			for (var i in trpddSettingsExport) {
				csv += headers.map(function(header) {
					return csvEscape(trpddSettingsExport[i][header]);
				}).join(',') + "\n";
			}
			var blob = new Blob([csv], {
				type: 'text/csv'
			});
			var url = URL.createObjectURL(blob);
			var link = document.createElement('a');
			link.href = url;
			link.download = trpddSettingsExportFilename;
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
			URL.revokeObjectURL(url);
		})

		init_items();
		$('#type_items').sortable({
			stop: function(event, ui) {
				var $pos = 0;
				$('#type_items input.position').each(function() {
					$pos++;
					$(this).val($pos);

				})
			}
		});
	})
</script>