<?php
global $wpdb;
global $only_insert;
global $condition_selected;
global $timepoint_selected;

require TRCPDD_PATH . 'inc/PHPExcel/IOFactory.php';

$act 					= isset($_REQUEST['act']) ? $_REQUEST['act'] : '';
$only_insert 			= isset($_REQUEST['only_insert']) ? true : false;
$timepoint_selected	 	= isset($_REQUEST['timepoint']) ? $_REQUEST['timepoint'] : '';
$condition_selected	 	= isset($_REQUEST['condition']) ? $_REQUEST['condition'] : '';
$rs_settings    		= get_option('ci_result_settings_study', array());

$check_pass_type = ''; //user,barcode
$approve_user = '';
$excludebc = array(
	'WH POS',
	'WH NEG',
	'NTC',
	'Positive Control',
	'Extraction Control',
	'Patient Positive',
	'WO NEG',
	'WO POS',
	'UTI NEG',
	'UTI POS',
	'STI NEG',
	'STI POS',
	'POSITIVE 1: 100',
	'POSITIVE 1: 1000',
	'Bank 1',
	'Blank 2',
	'Negative 1',
	'Negative 2',
	'POSITIVE 1:100,',
	'POSITIVE 1:1000,',
	'POSITIVE 1:1000'
);

wp_enqueue_script('jquery-ui');
wp_enqueue_script('jquery-ui-dialog');
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');

$user_id            = get_current_user_id();
$created_date       = date_i18n('Y-m-d');
$created_datetime   = date_i18n('Y-m-d H:i:s');

$table              = 'ci_batches_study';
$table_report       = 'ci_reports_study';
$table_results      = 'ci_results_study';


if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'get_mf_data') {
	$return 	= array();
	$info 		= pathinfo(str_replace('\\', '/', stripslashes($_REQUEST['file'])));
	$filename 	= $info['filename'];

	$assen_splitchars = array(' ', '.', '-', '_');

	$assen = $filename;
	$assen = str_replace('redo.', '.', $assen);
	foreach ($assen_splitchars as $aschar) {
		if (strpos($assen, $aschar) > 0) {
			list($assen) = explode($aschar, $assen);
		}
	}
	$assen = trim($assen);
	//$return['info'] = $info;
	$return['assen'] = $assen;

	//get testid
	$mf_test_id = isset($_REQUEST['testid']) ? $_REQUEST['testid'] : 0;
	if (empty($mf_test_id)) {
		$listkeys = array('0C', '3C', 'BSA');
		$testkey = '';
		foreach ($listkeys as $lk) {
			if (stripos($filename, '_' . $lk) > 0) {
				$testkey = $lk;
				break;
			}
		}

		if (!empty($testkey)) {
			$mf_test_id = $wpdb->get_var("select ID from ci_study_tests where abrev like 'MF%' and abrev like '%{$testkey}' ");
		} else {
			$mf_test_id = $wpdb->get_var("select ID from ci_study_tests where abrev = 'MF' ");
		}
	}
	$return['assen'] = $assen;
	$return['testid'] = $mf_test_id;
	$metakey 	= '_mfdata_' . $mf_test_id;

	$mf_data = $wpdb->get_var("select m.comments from ci_results_study m
	join ci_reports_study re On re.id = m.report_id 
	where re.patient_id = '{$assen}' 
	and m.drug = '{$metakey}' ");

	$return['mf_data'] = json_decode($mf_data, true);
	$return['mf_data']['excl_tube'] = '';
	wp_send_json($return);
} else if ($act == 'approve_import_checkboxes') {
	$ids = $_REQUEST['ids'];
	$ids = explode(',', $ids);
	$return = array('status' => 'ok');

	foreach ($_SESSION['files_study_import'] as $filename => $meta) {

		if (count($meta['duplicates']) > 0) {
			foreach ($meta['duplicates'] as $barcode => $dups) {
				foreach ($dups as $drug => $arr) {
					if (in_array($arr['id'], $ids)) {
						$update = array('conc' => $arr['conc']);
						$rs = $wpdb->update('ci_results_study', $update, array('id' => $arr['id']));
						$return['rs'][$arr['id']] = $rs;
					}
				}
			}
		}
	}
	ob_clean();
	wp_send_json($return);
} else if ($act == 'approve_import_dup') {
	$ids = $_REQUEST['id'];
	$ids = explode(',', $ids);

	$return = array('status' => 'ok');

	foreach ($_SESSION['files_study_import'] as $filename => $meta) {

		if (count($meta['duplicates']) > 0) {
			foreach ($meta['duplicates'] as $barcode => $dups) {
				foreach ($dups as $drug => $arr) {
					if (in_array($arr['id'], $ids)) {
						$update = array('conc' => $arr['conc']);

						$rs = $wpdb->update($table_results, $update, array('id' => $arr['id']));
						$return['rs'][$arr['id']] = $rs;
					}
				}
			}
		}
	}

	ob_clean();
	wp_send_json($return);
} else if ($act == 'ci_doctor_process') {
	$wpdb->suppress_errors(true);
	$files = array();
	if (!is_array($_FILES['files']['tmp_name'])) {
		$files[] = $_FILES['files'];
	} else {
		foreach ($_FILES['files']['tmp_name'] as $i => $t) {
			$new_file = array();
			foreach ($_FILES['files'] as $key => $f) {
				$new_file[$key] = $f[$i];
			}
			$files[] = $new_file;
		}
	}
	$uploaded = 0;
	foreach ($files as $file) {

		$filename 	= $file['name'];
		$filetmp  	= $file['tmp_name'];
		$info 		= pathinfo($filename);
		$ext 		= strtolower($info['extension']);

		if (in_array($ext, array('csv', 'xls', 'xlsx'))) {
			$filepath = $log_dir . $filename;

			//change old file name if exist
			$num = 1;
			$oldfilename = $filename;
			while (file_exists($filepath)) {
				$filename = str_replace('.' . $ext, '_' . $num . '.' . $ext, $oldfilename);
				$filepath = $log_dir . $filename;
				$num++;
			}

			if (move_uploaded_file($filetmp, $filepath)) {
				$_SESSION['files_study_import'][$filename] = array(
					'imported' => 0,
					'done' => false
				);
				$uploaded++;
				$batch_id = date_i18n('Ymd');
				$lab_id = CI_Base_Lab::get_current_lab_id();
				$old = $wpdb->get_row("select rf_ID,rf_count from ci_reports_files 
										WHERE rf_batch_id ='{$batch_id}' 
										AND rf_file='{$filename}' 
										AND rf_lab_site_id='{$lab_id}'  
										and rf_btype='study' ");

				if ($old) {
					$file_id = $old->rf_ID;
					$update_data = array(
						'rf_count' => $old->rf_count + 1,
						'rf_upload_date' => date_i18n('Y-m-d'),
					);
					if (isset($_REQUEST['meta_data'])) {
						$update_data['rf_meta_data'] = json_encode($_REQUEST['meta_data']);
					}
					$wpdb->update('ci_reports_files', $update_data, array('rf_ID' => $old->rf_ID));
				} else {
					$insert_data =  array(
						'rf_batch_id' => $batch_id,
						'rf_lab_site_id' => CI_Base_Lab::get_current_lab_id(),
						'rf_file' => $filename,
						'rf_count' => 1,
						'rf_btype' => 'study',
						'rf_upload_date' => date_i18n('Y-m-d'),
						'rf_created' => date_i18n('Y-m-d H:i:s'),
						'rf_meta_data' => json_encode(isset($_REQUEST['meta_data']) ? $_REQUEST['meta_data'] : array()),
					);
					if (isset($_REQUEST['meta_data'])) {
						$insert_data['rf_meta_data'] = json_encode($_REQUEST['meta_data']);
					}
					$wpdb->insert('ci_reports_files', $insert_data);
					$file_id = $wpdb->insert_id;
				}

				$_SESSION['files_study_import'][$filename]['file_id'] = $file_id;
			}
		} else {
			Tr_Session_Class::add_error('Error: File not support: ' . $file['name']);
			$uploaded = 0;
			break;
		}
	}

	if ($uploaded > 0) {
		$args = array('condition' => $condition_selected, 'timepoint' => $timepoint_selected, 'act' => 'doimport');

		if (isset($_REQUEST['meta_data']['import_type'])) {
			switch ($_REQUEST['meta_data']['import_type']) {
				case 'FA':
					$args['act'] = 'doimport_FA';
					break;
				case 'MF':
					$args['act'] = 'doimport_MF';
					break;
			}
		}

		if (isset($_REQUEST['only_insert'])) {
			$args['only_insert'] = 1;
		}
		if (isset($_REQUEST['debug1'])) {
			$args['debug1'] = 1;
		}
		if (isset($_REQUEST['debug'])) {
			$args['debug'] = 1;
		}

		$linkimport = Tr_Base_Class_V6::link($args, false);
?>
		<div class="wrap">
			<div style="width:900px;margin:30px auto;padding:30px">
				<p>Uploaded File Successfully!</p>
				<p id="importstatus">...</p>
				<div id="displaydone"></div>
			</div>
		</div>

		<style>
			.ui-dialog .ui-dialog-titlebar-close {
				display: none
			}
		</style>

		<style>
			tr.hidebarcode .barcode {
				color: #999;
			}
		</style>
		<script>
			jQuery(function($) {


				function osp_call_import($params) {
					$('#importstatus').html('<b>Importing data. Don\'t turn off page...</b><span class="loading"></span>');
					$.ajax({
						url: '<?php echo $linkimport ?>&trajax=1&' + $params,
						success: function(rs) {
							if (rs.indexOf('check_pass_user') > -1) {
								dialog.dialog("open");
							} else if (rs.indexOf('check_pass') > -1) {
								$('#displaydone').html(rs);
								$('.check_pass').hide();
								var $barcode = $('.check_pass').data('barcode');
								var $confirm = $barcode + ' has already been resulted and you are uploading its data again, is this ok?';
								var $pass_override = confirm($confirm) ? 1 : 0;
								osp_call_import('&pass_override=' + $pass_override + '&barcode=' + $barcode);
							} else if (rs.indexOf('success_import') > -1) {
								$('.loading').hide();
								$('#displaydone').html(rs);
							} else {
								$('#displaydone').html(rs);
								osp_call_import();
							}
						},
						error: function() {
							osp_call_import();
						}
					});
				}
				osp_call_import();


				$('body').on('submit', '#formduplicate', function() {
					var $f = $(this);
					var $btn = $('input[type="submit"]', $f);
					if ($btn.val() == 'Importing...') return false;
					$btn.val('Importing...');
					var $ids = '';
					$('input[type="checkbox"]:checked', $f).each(function() {
						$ids += $(this).val() + ',';
					});
					$.ajax({
						data: {
							ids: $ids,
							trajax: 1,
							act: 'approve_import_checkboxes'
						},
						type: 'post',
						dataType: 'json',
						success: function(json) {
							if (json.status == 'ok') {
								$btn.val('Done');
							}
						},
						error: function() {
							alert('Error. Please try again!');
							$btn.val('Import');
						}
					});
					return false;
				}).on('click', '#formduplicate .imp_approve', function() {
					var $this = $(this);
					var $btext = $(this).html();
					if ($btext == 'Importing...' || $btext == 'Done') return false;
					$this.html('Importing...');
					var $pr = $(this).parents('tr.tbrow');
					var $id = $pr.data('id');
					$.ajax({
						data: {
							id: $id,
							trajax: 1,
							act: 'approve_import_dup'
						},
						type: 'post',
						dataType: 'json',
						success: function(json) {
							if (json.status == 'ok') {
								$('.cb input', $pr).remove();
								$('.imp_deny', $pr).remove();
								$this.html('Done');
							}
						},
						error: function() {
							alert('Error. Please try again!');
							$this.html('Approve');
						}
					});
					return false;
				}).on('click', '#formduplicate .imp_deny', function() {
					var $pr = $(this).parents('tr.tbrow');
					$pr.remove();
					return false;
				}).on('click', '.checkall', function() {
					var $checked = $(this).is(':checked');
					$('#formduplicate td input[type="checkbox"]').prop('checked', $checked);
				})
			})
		</script>
	<?php
		return;
	} else {
		Tr_Session_Class::add_error('Error: Can\'t upload file');
	}
} else if ($act == 'doimport_FA') {
	include(trpdd_get_file_path('study', 'results_import_fa'));

	if (isset($_REQUEST['trajax'])) {

		Tr_Session_Class::show();
		echo '<div class="success_import">Imported Successfully! <br><a href="' . Tr_Base_Class_V6::link(array('act' => '', 'trajax' => ''), false) . '">Click here to back</a></div>';
		exit;
	}
} else if ($act == 'doimport_MF') {
	include(trpdd_get_file_path('study', 'results_import_mf'));

	if (isset($_REQUEST['trajax'])) {

		Tr_Session_Class::show();
		echo '<div class="success_import">Imported Successfully! <br><a href="' . Tr_Base_Class_V6::link(array('act' => '', 'trajax' => ''), false) . '">Click here to back</a></div>';
		exit;
	}
} else if ($act == 'doimport') {

	$files 	= $_SESSION['files_study_import'];

	global $reports_cache;

	$reports_cache = array();
	$logged_barcode = array();


	foreach ($files as $filename => $meta) {
		if ($meta['done']) continue;

		$fname = basename($filename);
		$file_id = $meta['file_id'];

		$count_import = 0;

		$filepath 		= $log_dir . $filename;
		$instrument 	= '';
		$instrument_sub 	= '';

		$ins_keys = array(
			'Hemox' => array('Hemox', ''),
			' agg ' => array('LORCCA', 'agg'),
			'cms' => array('LORCCA', 'cell'),
			'Osmo' => array('LORCCA', 'Osmo'),
			'oxy' => array('LORCCA', 'oxy'),
			'deformability' => array('LORCCA', 'deformability'),
			'dsa' => array('DSA', ''),
			'hplc' => array('HPLC', ''),
		);

		foreach ($ins_keys as $k => $arr1) {
			if (stripos($filename, $k) !== false) {
				$instrument = $arr1[0];
				$instrument_sub = $arr1[1];
				break;
			}
		}

		if (!empty($instrument)) {
			$insClass = 'Ins_' . $instrument;
			include_once(TRCPDD_PATH . 'parts/study/' . strtolower($insClass) . '.php');
			$ins = new $insClass();
			$arr = $ins->import($filepath, $instrument_sub);

			$meta = array_merge($meta, $arr);
		} else {
			echo $filename . ': Format not correct';
		}

		$meta['done'] = true;
		$files[$filename] = $meta;
		$_SESSION['files_study_import'] = $files;


		//update file
		$assen_list = implode(',', $meta['assen_list']);
		$wpdb->update('ci_reports_files', array('rf_assen_list' => $assen_list), array('rf_ID' => $meta['file_id']));
	}


	$duplicates = '';

	foreach ($_SESSION['files_study_import'] as $filename => $meta) {
		$meta['skipped'] = isset($meta['skipped']) && is_array($meta['skipped']) ? $meta['skipped'] : array();
		$meta['updated'] = isset($meta['updated']) && is_array($meta['updated']) ? $meta['updated'] : array();
		$meta['inserted'] = isset($meta['inserted']) && is_array($meta['inserted']) ? $meta['inserted'] : array();
		$meta['error'] = isset($meta['error']) && is_array($meta['error']) ? $meta['error'] : array();

		$skipped = count($meta['skipped']);
		$updated = count($meta['updated']);
		$inserted = count($meta['inserted']);
		$error = count($meta['error']);
		$total = $inserted + $updated + $skipped + $error;

		//$filename = preg_replace('/[0-9]*\_/','',$filename);

		if (isset($meta['duplicates']) && is_array($meta['duplicates']) && count($meta['duplicates']) > 0) {
			$count_dup = 0;
			$showed_barcodes = array();
			foreach ($meta['duplicates'] as $barcode => $dups) {
				foreach ($dups as $drug => $arr) {
					$class = '';
					if (!in_array($barcode, $showed_barcodes)) {
						$showed_barcodes[] = $barcode;
					} else {
						$class .= ' hidebarcode ';
					}

					$duplicates .= '<tr class="tbrow ' . $class . '" data-id="' . $arr['id'] . '" data-report_id="' . $arr['report_id'] . '">    
                    <td class="barcode">' . $arr['barcode'] . '</td>
                    <td class="drug">' . $arr['drug'] . '</td>
                    <td class="old">' . $arr['old'] . '</td>
                    <td class="new">' . $arr['conc'] . '</td>
                    <td class="cb"><input type="checkbox" value="' . $arr['id'] . '"/></td>
                    <td><a class="imp_approve button">Approve</a>  <a class="imp_deny button">Deny</a></td>
                    </tr>';
					$count_dup++;
				}
			}
			$total = $inserted + $updated + $skipped + $error + $count_dup;
			Tr_Session_Class::add('File: ' . $filename .
				'<br>Total: ' . $total .
				'<br>Inserted: ' . $inserted .
				'<br>Duplicate: ' . $count_dup .
				'<br>Error: ' . $error);
		} else {
			Tr_Session_Class::add('File: ' . $filename .
				'<br>Total: ' . $total .
				'<br>Inserted: ' . $inserted .
				'<br>Updated: ' . $updated .
				'<br>Skipped: ' . $skipped .
				'<br>Error: ' . $error);
		}
	}

	if (!empty($duplicates)) {
		Tr_Session_Class::show();

	?>
		<h2>Duplicates</h2>
		<form method="post" id="formduplicate">
			<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
				<thead>
					<tr>
						<th style="width: 110px;">Barcode</th>
						<th style="width:270px">Test</th>
						<th>Old CT</th>
						<th>New CT</th>
						<th style="width:30px"><input type="checkbox" class="checkall" /></th>
						<th style="width:155px">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php echo $duplicates ?>
				</tbody>
			</table>
			<br>
			<input type="submit" class="button-primary" value="Import" />
		</form>
		<br><br>

<?php

	}


	if (isset($_REQUEST['trajax'])) {

		Tr_Session_Class::show();
		echo '<div class="success_import">Imported Successfully! <br><a href="' . Tr_Base_Class_V6::link(array('act' => '', 'trajax' => ''), false) . '">Click here to back</a></div>';


		exit;
	}
}

$_SESSION['study_passoverride'] = array();
$_SESSION['study_passoverride_all'] = false;
$_SESSION['files_study_import'] = array();


$studies = $wpdb->get_results("select * from ci_study");
$timepoints = array();
foreach ($studies as $study) {
	$study->time_points = json_decode($study->time_points, true);
	if (is_array($study->time_points)) {
		foreach ($study->time_points as $timepoint) {
			$conditions = array();
			foreach ($timepoint['conditions'] as $condition) {
				$conditionkey  = strtolower(substr(str_replace(' ', '', $condition['name']), 0, 35));
				if (!empty($conditionkey)) {
					$conditions[$conditionkey] = $condition['name'];
				}
			}
			ksort($conditions);
			$timepoint['conditions'] = $conditions;
			$timepoints[] = $timepoint;
		}
	}
}

?>
<div class="wrap">
	<h2 class="center">Upload Raw Data</h2>

	<?php Tr_Session_Class::show() ?>
	<div class="form-wrap center">
		<form enctype="multipart/form-data" method="post">
			<input type="hidden" name="act" value="ci_doctor_process">

			<p class="alert message warning">Please only upload CSV files!</p>

			<?php

			$mf_fields = array('i12' => 1, 'j12' => 100, 'k12' => 2, 'l12' => 200, 'm12' => 3, 'n12' => 300);
			$vmax_fields = array('f9' => 'Hb', 'k3' => 'Zero (undiluted)');

			$mf_df = array();
			$vmax_df = array();

			?>
			<label><input type="checkbox" class="import_typecb" name="meta_data[import_type]" value="FA" /> Import FA</label>
			<label><input type="checkbox" class="import_typecb" id="import_type_mf" name="meta_data[import_type]" value="MF" /> Import MF</label>

			<br>





			<div class="frow meta_data_area for_MF hidden">
				<div>
					<select name="meta_data[excl_tube]">
						<option value="">Select Exclude:</option>
						<?php
						for ($i = 1; $i <= 3; $i++) {
							echo '<option value="' . $i . '">Tube ' . $i . '</option>';
						}
						?>
					</select>
				</div>
				<br>
				<table class="wp-list-table widefat fixed striped posts" style="margin:10px auto;width:500px">
					<thead>
						<tr>
							<?php
							foreach ($vmax_fields as $mff) {
								echo '<th class="center">' . $mff . '</th>';
							}
							?>
						</tr>
						<tr>
					</thead>
					<?php
					foreach ($vmax_fields as $vk => $mff) {
						echo '<td><input type="text"  name="meta_data[' . $vk . ']" value="' . (isset($vmax_df[$vk]) ? $vmax_df[$vk] : '') . '"></td>';
					}
					?>
					</tr>
				</table>
				<br>
				<table class="wp-list-table widefat fixed striped posts" style="margin:10px auto;width:500px">
					<thead>
						<tr>
							<th colspan="<?php echo count($mf_fields) ?>" class="center"><b>NanoDrop</b></th>
						</tr>
						<tr>
							<?php
							foreach ($mf_fields as $mff) {
								echo '<th class="center">' . $mff . '</th>';
							}
							?>
						</tr>
						<tr>
					</thead>
					<?php
					foreach ($mf_fields as $mfk => $mff) {
						echo '<td><input type="text"  name="meta_data[' . $mfk . ']" value="' . (isset($mf_df[$mfk]) ? $mf_df[$mfk] : '') . '"></td>';
					}
					?>
					</tr>
				</table>

			</div>

			<div class="frow">
				<label>Time Point</label>
				<select name="timepoint" style="width:250px">
				</select>
			</div>
			<div class="frow">
				<label>Condition</label>
				<select name="condition" style="width:250px">
				</select>
			</div>

			<div class="frow card_file">
				<a class="button selectfile" href="#">Choose Files</a>
				<span class="view"></span>
				<input type="file" id="upload-files" require="true" name="files[]" accept="text/csv" multiple />
			</div>


			<div class="frow" style="margin-top:35px">
				<input type="submit" value="Upload" class="button-primary" />
			</div>

			<br>

			<label><input type="checkbox" name="only_insert" value="1" /> Only Insert New</label>

			<p><em>To upload multiple files, hold down SHIFT while clicking to select a group or CTRL to
					select multiple files individually.</em></p>
		</form>
	</div>
</div>
<style>
	.meta_data_area {
		display: none;
	}

	.meta_data_area table .center {
		text-align: center !important;
	}

	.meta_data_area td input {
		max-width: 70px !important;
	}
</style>
<script>
	jQuery(function($) {
		var $timepoints = <?php echo json_encode($timepoints) ?>;
		$('body').on('change', 'input.import_typecb', function() {
			var $vl = $(this).val();
			$('.meta_data_area').hide();
			$('.meta_data_area input').prop('required', false);
			if ($(this).is(':checked')) {
				if ($vl == 'MF') {
					$('#upload-files').attr('accept', '*/*');
				}
				$('.meta_data_area.for_' + $vl).show();
				$('.meta_data_area.for_' + $vl + ' input[data-required="true"]').prop('required', true);
				$('input.import_typecb').each(function() {
					if ($(this).val() != $vl) {
						$(this).prop('checked', false);
					}
				})
			}
		}).on('change', '#upload-files,#import_type_mf', function() {
			if ($('#import_type_mf').is(':checked') == false) {
				return;
			}
			$.ajax({
				type: 'post',
				dataType: 'json',
				data: {
					'trajax': 'get_mf_data',
					'file': $('#upload-files').val()
				},
				success: function(json) {
					for (i in json.mf_data) {
						var $ipb = $('[name="meta_data[' + i + ']"]');
						if ($ipb.val() == '') {
							$ipb.val(json.mf_data[i]);
						}

					}
				},
				error: function() {
					setTimeout(function() {
						$('#upload-files').change();
					}, 1000);
				}
			});
		})
		$('#import_type_mf').change();

		var $tpobj = $('[name="timepoint"]');
		$tpobj.append('<option value="">By File</option>');
		for (i in $timepoints) {
			var opt = $('<option value="' + $timepoints[i].name + '">' + $timepoints[i].name + '</option>');
			opt.data('conditions', $timepoints[i].conditions);
			$tpobj.append(opt);
		}
		$tpobj.on('change', function() {
			var $conobj = $('[name="condition"]');
			$conobj.html('<option value="">Select</option>');
			var $conditions = $('option:selected', $tpobj).data('conditions');
			if ($conditions) {
				for (i in $conditions) {
					$conobj.append('<option value="' + i + '">' + $conditions[i] + '</option>');
				}
			}

		})
	})
</script>