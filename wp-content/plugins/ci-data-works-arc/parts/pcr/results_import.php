<?php
global $wpdb;
global $is_ci_admin;

require TRCPDD_PATH . 'inc/PHPExcel/IOFactory.php';
include_once(TRCPDD_PATH . 'inc/functions_rack_plate.php');

wp_enqueue_script('jquery-ui');
wp_enqueue_script('jquery-ui-dialog');
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');



$pcr_cv_file        = tr_get_option('ci_pdf_data', 'pcr_cv_file');
$pcrcovid_in1crpic  = tr_get_option('ci_pdf_data', 'pcrcovid_in1crpic');
$pcr_importuti      = tr_get_option('ci_pdf_data', 'pcr_importuti');
$settings           = get_option('ci_result_settings_pcr', array());

$settings['enable_im_oncosu'] = isset($settings['enable_im_oncosu']) ? $settings['enable_im_oncosu'] : false;

$user_id            = get_current_user_id();
$created_date       = date_i18n('Y-m-d');
$created_datetime   = date_i18n('Y-m-d H:i:s');

$act                = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';

$table              = 'ci_batches_pcr';
$table_report       = 'ci_reports_pcr';
$table_results      = 'ci_results_pcr';

$IC_covid_tests     = trpdd_get_covid_tests('IC');
$all_covid_tests    = trpdd_get_covid_tests('*', true);
$type_by_covid_tests = array();
$up_covid_tests     = array();
$ctrl_positive	    = '';

$qc_barcodes     	= array();
$Instruments		= array();
if (tr_get_option('ci_result_settings_pcr', 'e_qc')) {
	$basecode = tr_get_option('ci_pdf_data', 'barcode_key');
	$options = get_option('tr_pcr_qc_st', array());
	for ($i = 1001; $i <= 1033; $i++) {
		$qcbarcode = $basecode . '0000' . $i;
		if (isset($options[$qcbarcode]) && !empty($options[$qcbarcode])) {
			$qc_barcodes[$qcbarcode] = $options[$qcbarcode];
		}
	}
	if (empty($qc_barcodes)) {
		$qc_barcodes['NEG'] = 'NEGATIVE CONTROL';
		$qc_barcodes['POS'] = 'POSITIVE CONTROL';
	}

	$Instruments = get_option('trinstruments_pcr', array());
}

foreach ($all_covid_tests as $tt => $ts) {
	foreach ($ts as $t) {
		$t = strtoupper($t);
		$up_covid_tests[] = $t;
		$type_by_covid_tests[$t] = $tt;
	}
}


$check_pass_type = ''; //user,barcode
$approve_user = '';
$excludebc = array(
	'WH POS',
	'WH NEG',
	'NTC',
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
	'POSITIVE 1:1000',
	'NEC-1',
	'NEC-2',
	'NEC-3',
	'NEC-4',
	'NEC-5',
	'HSC-1',
	'HSC-2',
	'HSC-3',
	'HSC-4',
	'HSC-5'
);



$all_instruments = $wpdb->get_col("select instrument from `ci_instruments` where btype='pcr' ");
$all_instruments = array_map("strtoupper", $all_instruments);
usort($all_instruments, function ($a, $b) {
	return strlen($b) - strlen($a);
});

if (!empty($Instruments)) {
	$all_instruments = array_merge($all_instruments, $Instruments);
}

if (isset($_GET['debug'])) {
	echo 'instruments:<br>';
	var_dump($all_instruments);
	echo '<br>';
}

$rs         = $wpdb->get_results("select * from ci_test_groups where tg_type = 'pcr' ");
$all_groups = array();
$adding_groups = array();
foreach ($rs as $r) {
	$r->tg_name = strtoupper($r->tg_name);
	$all_groups[$r->tg_name] = $r->tg_ID;

	if (strpos($r->tg_name, ' ') > 0) {
		$a = explode(' ', $r->tg_name);
		$a = trim($a[0]);

		$adding_groups[$a] = $r->tg_ID;
	}
}

$all_groups = array_merge($all_groups, $adding_groups);


if ($act == 'list_files') {
	$log_url = '/wp-content/log/' . basename($log_dir) . '/';
	$files = scandir($log_dir);
	$list_files = array();
	foreach ($files as $f) {
		if (in_array($f, array('.', '..'))) continue;
		$list_files[] = array(
			'time' => filemtime($log_dir . $f),
			'file' => $f
		);
	}
	usort($list_files, function ($a, $b) {
		if ($a['time'] == $b['time']) {
			return 0;
		}
		return ($a['time'] < $b['time']) ? -1 : 1;
	});

	foreach ($list_files as $arr) {
		$datetime = date('Y-m-d H:i:s', $arr['time']);
		echo '<li>';
		echo '<a href="' . $log_url . $arr['file'] . '">' . $arr['file'] . '</a> | ' . $datetime;
		echo '</li>';
	}
} else if ($act == 'demo_file') {

	header("Content-type: text/csv");
	header("Content-Disposition: attachment; filename=demo_import.csv");
	header("Pragma: no-cache");
	ob_clean();
	$f = fopen('php://output', 'w');
	$headers = array('Sample', 'Target', 'ct');
	fputcsv($f, $headers);
	$data = array('Test1222333', 'Covid-19', '1.1');
	fputcsv($f, $data);
	exit;
} else if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'check_user_pass') {

	$return = array('status' => 'error', 'alert' => '');
	$email = $_REQUEST['email'];
	$email = $approve_user;
	$password = $_REQUEST['password'];

	$user = get_user_by('email', $email);

	if ($user) {
		if (wp_check_password($password, $user->user_pass, $user->ID)) {
			$return['status'] = 'ok';
		} else {
			$return['alert'] = 'Wrong Password!';
		}
	} else {
		$return['alert'] = 'User Not exist!';
	}
	ob_clean();
	wp_send_json($return);
} else if ($act == 'approve_import_checkboxes') {
	$ids = $_REQUEST['ids'];
	$ids = explode(',', $ids);
	$return = array('status' => 'ok');
	$report_ids = array();

	foreach ($_SESSION['files_pcr_import'] as $filename => $meta) {

		if (count($meta['duplicates']) > 0) {
			$logbyassen = array();
			foreach ($meta['duplicates'] as $barcode => $dups) {
				if (!isset($logbyassen[$barcode])) {
					$logbyassen[$barcode] = array();
				}
				foreach ($dups as $drug => $arr) {
					if (in_array($arr['id'], $ids)) {

						$old_arr = $wpdb->get_row($wpdb->prepare("select * from ci_results_pcr where id=%d", $arr['id']), ARRAY_A);

						$update = array('conc' => $arr['conc']);
						$logbyassen[$barcode][$drug] = array(
							'old' => array('conc' => $old_arr['conc']),
							'new' => array('conc' => $arr['conc'])
						);

						if ((isset($settings['enable_rs_ct_amp_cq']) && $settings['enable_rs_ct_amp_cq']) || (isset($settings['rs_hml_amp_cq']) && $settings['rs_hml_amp_cq'])) {
							$update['cq'] = $arr['cq'];
							$update['amp_score'] = $arr['amp_score'];

							$logbyassen[$barcode][$drug]['old']['cq'] = $old_arr['cq'];
							$logbyassen[$barcode][$drug]['new']['cq'] = $arr['cq'];
							$logbyassen[$barcode][$drug]['old']['amp_score'] = $old_arr['amp_score'];
							$logbyassen[$barcode][$drug]['new']['amp_score'] = $arr['amp_score'];
						}

						if (isset($arr['pos-neg']) &&  in_array($arr['pos-neg'], array('POS', 'NEG'))) {
							$update['pos-neg'] = $arr['pos-neg'];

							$logbyassen[$barcode][$drug]['old']['pos-neg'] = $old_arr['pos-neg'];
							$logbyassen[$barcode][$drug]['new']['pos-neg'] = $arr['pos-neg'];
						}

						if ($arr['report_id'] > 0) {
							$report_ids[$arr['report_id']] = $arr['report_id'];
						}



						$rs = $wpdb->update('ci_results_pcr', $update, array('id' => $arr['id']));
						$return['rs'][$arr['id']] = $rs;
					}
				}
			}

			foreach ($logbyassen as $assen => $drugs) {
				foreach ($drugs as $drug => $logarr) {
					$filename_b = basename($filename);

					$mid = $wpdb->get_var($wpdb->prepare("select mid from ci_batches_pcr where assen=%s  ", $assen));
					$oldlob = $wpdb->get_row("select * from ci_log_users 
								where user_id = {$user_id} 
								and user_action = 'uploaded_rs'
								and mid = {$mid} 
								and data like '%{$filename_b}%'
								and btype='pcr' 
								order by id desc limit 1", ARRAY_A);
					if ($oldlob) {
						$old_data = json_decode($oldlob['data'], true);
						$old_data = array_merge($old_data, $drugs);
						$wpdb->update('ci_log_users', array(
							'data' => json_encode($old_data)
						), array('id' => $oldlob['id']));
					}
				}
			}
		}
	}

	if (count($report_ids) > 0) {
		$report_ids_str = implode(',', $report_ids);
		$wpdb->query("update {$table_report} set estatus = 'M' where estatus = 'C' and id in ({$report_ids_str})");
	}
	$nextcron = wp_next_scheduled('ci_cron_clean_results');
	if ($nextcron == 0 || $nextcron > time() + 360) {
		wp_schedule_single_event(time() + 180, 'ci_cron_clean_results', array('btype' => 'pcr'));
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

	if (empty($files) && count($_POST['files']) > 0) {
		foreach ($_POST['files'] as $filename) {
			$path = $log_dir . '/' . $filename;
			$files[] = array(
				'name' => $filename,
				'tmp_name' => $path,
			);

			tr_pdd_activity("File uploaded successfully: <em>" . $filename . "</em>");
		}
		var_dump($files);
		exit;
	}
	$uploaded = 0;
	if (isset($_GET['debug'])) {
		var_dump(sys_get_temp_dir());
		var_dump($files);
	}
	foreach ($files as $file) {
		$filename = $file['name'];
		$filetmp  = $file['tmp_name'];
		$filename = str_replace('  ', ' ', $filename);
		$info = pathinfo($filename);
		$ext = strtolower($info['extension']);

		$act_import = 'doimport';
		$allow_upload = in_array($ext, array('csv', 'xls', 'xlsx'));

		if (($ext == 'txt' && stripos($filename, 'MIC') > 0) || $ext == 'hl7') {
			$allow_upload = true;
			$act_import = 'doimport_mic';
		}

		if ($allow_upload) {
			$filepath = $log_dir . $filename;

			//change old file name if exist
			$num = 1;
			$oldfilename = $filename;
			while (file_exists($filepath)) {
				$filename = str_replace('.' . $ext, '_' . $num . '.' . $ext, $oldfilename);
				$filepath = $log_dir . $filename;
				$num++;
			}


			if (isset($_GET['debug'])) {
				var_dump($filetmp);
				var_dump($filepath);
			}

			if (move_uploaded_file($filetmp, $filepath)) {
				$_SESSION['files_pcr_import'][$filename] = array(
					'imported' => 0,
					'done' => false,
					'file_id' => 0,
					'batch_id' => '',
					'assen_list' => array()
				);
				$uploaded++;

				preg_match("/([0-9]{8})/", $filename, $matches);
				$batch_id = isset($matches[1]) ? $matches[1] : '';
				if (empty($batch_id)) {
					preg_match("/Batch ([0-9]+)/", $filename, $matches_lab);
					$batch_id = isset($matches_lab[1]) ? $matches_lab[1] : '';
				}
				if (empty($batch_id)) {
					preg_match("/([0-9]{1,2})([0-9]{2})([0-9]{2})/", $filename, $matches_lab);

					if (isset($matches_lab[3]) && $matches_lab[3] > 20) {
						$batch_id = '20' . $matches_lab[3] . (strlen($matches_lab[1]) < 2 ? '0' . $matches_lab[1] : $matches_lab[1]) . $matches_lab[2];
					}
				}
				if (empty($batch_id)) {
					$batch_id = date_i18n('Ymd');
				}
				if (!empty($batch_id)) {
					$lab_id = CI_Base_Lab::get_current_lab_id();
					$old = $wpdb->get_row("select rf_ID,rf_count from ci_reports_files 
		                                    WHERE rf_file='{$filename}' 
		                                    AND rf_lab_site_id='{$lab_id}'  
		                                    and rf_btype='pcr' 
											order by rf_ID desc");

					if ($old) {
						$file_id = $old->rf_ID;
						$wpdb->update('ci_reports_files', array(
							'rf_count' => $old->rf_count + 1,
							'rf_upload_date' => date_i18n('Y-m-d')
						), array('rf_ID' => $old->rf_ID));
					} else {

						$wpdb->insert('ci_reports_files', array(
							'rf_batch_id' => $batch_id,
							'rf_lab_site_id' => CI_Base_Lab::get_current_lab_id(),
							'rf_file' => $filename,
							'rf_count' => 1,
							'rf_btype' => 'pcr',
							'rf_upload_date' => date_i18n('Y-m-d'),
							'rf_created' => date_i18n('Y-m-d H:i:s')
						));
						$file_id = $wpdb->insert_id;
					}

					$_SESSION['files_pcr_import'][$filename]['file_id'] = $file_id;
					$_SESSION['files_pcr_import'][$filename]['batch_id'] = $batch_id;
				}
			} else {
				Tr_Session_Class::add_error('Error: Can\'t upload file: ' . basename($filename));
			}
		} else {
			Tr_Session_Class::add_error('Error: File not support: ' . $file['name']);
			$uploaded = 0;
			break;
		}
	}

	if ($uploaded > 0) {
		$linkimport = Tr_Base_Class_V6::link(array('act' => $act_import), false);
		if (isset($_REQUEST['only_insert'])) {
			$linkimport .= '&only_insert';
		}
		if (isset($_REQUEST['onlyqc'])) {
			$linkimport .= '&onlyqc';
		}
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
		<div id="alertaddialog" title="Alert" style="display: none;">
			<p>Please ask a Andrew to approve result overwrite.</p>
			<form id="form_comfirmalert">
				<div class="frow">
					<label for="email">Email</label>
					<input type="text" name="email" id="email" value="<?php echo $approve_user ?>" class="text ui-widget-content ui-corner-all">
				</div>
				<div class="frow">
					<label for="password">Password</label>
					<input type="password" name="password" id="password" value="" class="text ui-widget-content ui-corner-all">
				</div>
			</form>
		</div>
		<style>
			tr.hidebarcode .barcode {
				color: #999;
			}

			tfoot th,
			tfoot td {
				position: sticky;
				bottom: 0;
				background-color: #fff;
				/* Cần màu nền để không bị lộ nội dung bên dưới khi cuộn */
				z-index: 2;
				/* Đảm bảo chân bảng nằm trên các hàng dữ liệu */
			}
		</style>
		<script>
			var $confirmed_approve = null;
			jQuery(function($) {
				var dialog = $("#alertaddialog").dialog({
					autoOpen: false,
					closeOnEscape: false,
					width: 400,
					modal: true,
					buttons: {
						"Comfirm": function() {
							$.ajax({
								data: {
									'trajax': 'check_user_pass'
								},
								type: 'post',
								dataType: 'json',
								success: function(json) {
									if (json.status == 'ok') {
										//continue
										osp_call_import('&pass_override_all=1');
									} else {
										alert('Wrong User/Password');
									}
								},
								error: function() {
									alert('Error. Please try again!');

								}
							})
						},
						"Abort": function() {
							dialog.dialog("close");
							osp_call_import('&pass_override_all=0');
						}
					}
				});

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


				function trapprove_selected($row) {
					if ($row.data('estatus') == 'C') {

						if ($confirmed_approve === null) {
							var $status_type = $row.data('status_type');
							$confirmed_approve = false;
							if (confirm('The test values you are attempting  to change have been approved as "' + $status_type + ' results"?') == false) {
								return;
							}
							$confirmed_approve = true;
						} else if ($confirmed_approve === false) {
							return;
						}

					}
					$('.cb input', $row).remove();
					$row.addClass('approved');
					$('.imp_deny', $row).hide();
					$('.imp_approve', $row).hide();
					$('.checkmark', $row).show();
				}


				$('body').on('submit', '#formduplicate', function() {
					var $f = $(this);
					var $btn = $('input[type="submit"]', $f);

					var $ids = '';
					$('tr.tbrow.approved', $f).each(function() {
						$ids += $(this).data('id') + ',';
					});
					if ($ids == '') {
						alert('No approved records to import!');
						return false;
					}

					if ($btn.val() == 'Importing...') return false;
					else if ($btn.val() == 'Done') {
						location.reload();
						return;
					}

					$btn.val('Importing...');

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
								$('#deny_selected', $f).hide();
								$('#approve_selected', $f).hide();
							}
						},
						error: function() {
							alert('Error. Please try again!');
							$btn.val('Import');
						}
					});
					return false;
				}).on('click', '#formduplicate .imp_approve', function() {
					$confirmed_approve = null;
					trapprove_selected($(this).parents('tr.tbrow'));
					return false;
				}).on('click', '#formduplicate .imp_deny', function() {
					var $pr = $(this).parents('tr.tbrow');
					$pr.remove();
					return false;
				}).on('click', '.checkall', function() {
					var $checked = $(this).is(':checked');
					$('#formduplicate td input[type="checkbox"]').prop('checked', $checked);
				}).on('click', '#formduplicate #approve_selected', function() {
					$confirmed_approve = null;
					$('#formduplicate input[type="checkbox"]:checked').each(function() {
						trapprove_selected($(this).parents('tr.tbrow'));
					});
				}).on('click', '#formduplicate #deny_selected', function() {
					$('#formduplicate input[type="checkbox"]:checked').each(function() {
						var $pr = $(this).parents('tr.tbrow');
						$pr.remove();
					});
				});
			})
		</script>
	<?php
		return;
	} else {
		Tr_Session_Class::add_error('No file to import');
	}
} else if ($act == 'doimport_mic') {
	include('results_import_mic.php');
} else if ($act == 'doimport') {

	$only_insert = isset($_REQUEST['only_insert']) ? true : false;
	$only_qc 	= isset($_REQUEST['onlyqc']) ? true : false;


	$files = $_SESSION['files_pcr_import'];
	$limit = 1500;
	$match_header = array(
		'barcode'       => 'barcode',
		'sample name'   => 'barcode',
		'sample'        => 'barcode',
		'accession'     => 'barcode',
		'target name'   => 'drug',
		'fluor'         => 'drug',
		'target'        => 'drug',
		'detector'      => 'drug',
		'gene'      	=> 'drug',
		'dye'      		=> 'drug',
		'ct'            => 'conc',
		'cq'            => 'conc',
		'cq mean'       => 'conc',
		'crt'           => 'conc',
		'tm'            => 'conc',
		'melt temperature' => 'mtemp',
		'mtemp'         => 'mtemp',
		'result'        => 'result',
		'well'        => 'well',
		'% positive' => 'percent_pos'
	);



	$settings['import_target'] 	= isset($settings['import_target']) ? strtolower($settings['import_target']) : '';
	$settings['import_conc'] 	= isset($settings['import_conc']) ? strtolower($settings['import_conc']) : '';
	$settings['notfify_reflab_esam'] = isset($settings['notfify_reflab_esam']) && $settings['notfify_reflab_esam'];

	$reflab_letters = array();
	if ($settings['notfify_reflab_esam']) {
		$reflab_letters = $wpdb->get_col("select lab_dba_init from ci_labs where lab_is_ref = 1");
	}


	if (!empty($settings['import_target'])) {
		unset($match_header['gene']);
		unset($match_header['dye']);
		$match_header[$settings['import_target']] = 'drug';
	}
	if (!empty($settings['import_conc'])) {
		unset($match_header[$settings['import_conc']]);
		$match_header = array_merge(array(
			$settings['import_conc'] => 'conc'
		), $match_header);
	}

	if ((isset($settings['enable_rs_ct_amp_cq']) && $settings['enable_rs_ct_amp_cq']) || (isset($settings['rs_hml_amp_cq']) && $settings['rs_hml_amp_cq'])) {
		$match_header['cq confidence'] = 'cq';
		$match_header['cq conf'] = 'cq';
		$match_header['amp score'] = 'amp_score';
		$match_header['amp status'] = 'amp_status';
	}

	if (isset($settings['enable_solana'])) {
		$match_header['sample id'] = 'barcode';
		$match_header['name'] = 'mutiname';
		$match_header['result'] = 'mutiresult';
	}

	if (isset($settings['enable_im_oncosu'])) {
		$match_header['sample id'] = 'barcode';
		$match_header['result'] = 'result';
		$match_header['multitests'] = 'multitests';
	}

	if (isset($settings['enable_i_fam_gene_hex']) && $settings['enable_i_fam_gene_hex']) {
		$match_header['fam - gene'] = 'mutiname';
		$match_header['fam - standard concentration'] = 'mutiresult';
		$match_header['hex_2 - gene'] = 'mutiname';
		$match_header['hex_2 - standard concentration'] = 'mutiresult';
		$match_header['rox - gene'] = 'mutiname';
		$match_header['rox - standard concentration'] = 'mutiresult';
		$match_header['cy5 - gene'] = 'mutiname';
		$match_header['cy5 - standard concentration'] = 'mutiresult';
	}

	if ($pcr_importuti) {
		$match_header['name'] = 'barcode';
		$match_header['well'] = 'drugwell';
		$match_header['c(t)'] = 'result';
	}

	if (!tr_get_option('ci_pdf_data', 'pcr_importfluor')) {
		unset($match_header['fluor']);
	}

	if (isset($_GET['debugheader'])) {
		echo '<pre>';
		print_r($match_header);
		echo '</pre>';
		exit;
	}

	$reports = array();
	$batches = array();
	$logged_barcode = array();

	$settings_warn = get_option('tr_warn_popup_pcr', array());

	if (!isset($settings_warn['tests']) || !is_array($settings_warn['tests'])) {
		$settings_warn['tests']  = array();
	}

	$testids = implode(',', $settings_warn['tests']);
	if (empty($testids)) {
		$testids  = '0';
	}
	$tests = $wpdb->get_results("select * from ci_test_panels where te_ID in ({$testids})");
	$test_warns = array();
	foreach ($tests as $t) {
		$t->te_name = trim($t->te_name);
		$t->te_abrev = trim($t->te_abrev);

		$test_warns[] = strtoupper($t->te_name);
		if (!empty($t->te_abrev)) {
			$test_warns[] = strtoupper($t->te_abrev);
		}
	}

	$groupsids = $wpdb->get_col("select tg_ID from ci_test_groups tg
	where tg.tg_type = 'pcr' 
	and (
		(tg.tg_parent = 0 and tg.tg_show_req = 1)
	or 
		tg.tg_parent in (select tg_ID from ci_test_groups where tg_type='pcr' and tg_parent = 0 and tg_show_req = 1)
	) ");



	$groupsids_str = implode(',', $groupsids);

	$sql_abrevs = "select te_abrev,te_abrev2,te_name from ci_test_panels 
	where te_type='pcr' and te_in_req = 1 and te_group_id in ({$groupsids_str}) ";
	$tests = $wpdb->get_results($sql_abrevs);
	$test_abrev = array();
	$test_abrev2 = array();
	$all_test_names = array();
	$dontuseabrev = array();
	$covid_drug = '';

	foreach ($tests as $t) {
		$t->te_name = strtoupper($t->te_name);
		$t->te_name = trim($t->te_name);
		$t->te_abrev = trim($t->te_abrev);

		if (!empty($t->te_abrev)) {
			$t->te_abrev = strtoupper($t->te_abrev);
			if (isset($test_abrev[$t->te_abrev])) {
				unset($test_abrev[$t->te_abrev]);
				$dontuseabrev[] = $t->te_abrev;
			} else if (!in_array($t->te_abrev, $dontuseabrev)) {
				$test_abrev[$t->te_abrev] = $t->te_name;
			}
		}
		if (!empty($t->te_abrev2)) {
			$t->te_abrev2 = strtoupper($t->te_abrev2);
			$test_abrev2[$t->te_abrev2] = $t->te_abrev2;
		}

		if (stripos($t->te_abrev, 'nCoV-2019') !== false) {
			$covid_drug = $t->te_name;
		}

		$all_test_names[] = $t->te_name;
	}



	$instruments        = array();
	$group_ids          = array();
	$update_covid_ids   = array();

	$result2times		= array();
	$error_no           = 0;


	foreach ($files as $filename => $meta) {
		if ($meta['done']) continue;

		$fname = basename($filename);
		$file_id = $meta['file_id'];
		$covid_file_type = '';
		$main_batch_id = '';

		$arrex = explode('_', $fname, 2);
		$bidtm = isset($arrex[0]) ? $arrex[0] : '';
		$filepanel = isset($arrex[1]) ? $arrex[1] : '';

		$filepanel = substr($filepanel, 0, 4);

		if (empty($instruments[$filename])) {

			foreach ($all_instruments as $mc) {
				if (stripos($fname, $mc) !== false) {
					$instruments[$filename] = substr(strtoupper($mc), 0, 15);
					break;
				}
			}
		}

		if (empty($instruments[$filename]) && stripos($fname, 'Bio-Rad2') > 0) {
			$instruments[$filename] = 'BioRad2';
		}

		if (empty($instruments[$filename]) && isset($settings['instrument_required']) && $settings['instrument_required']) {
			$error_no = 3;
			Tr_Session_Class::add_error('No Instrument Name in file!');
			break;
		}

		if (empty($group_ids[$filename])) {
			foreach ($all_groups as $gname => $gid) {
				if (stripos($fname, $gname) !== false) {
					$group_ids[$filename] = $gid;
					break;
				}
			}
		}

		$headers            = array();
		$headers_abrevs     = array();
		$reports_to_M  = array();
		$headers_abrevs_ct  = array();
		$is_uti_new_tmp     = 0;
		$count_import       = 0;

		$last_assen 		= '';
		$num_row_has_assen  = 0;
		$headers_mtests		= array();

		$filepath = $log_dir . $filename;

		$filetype = PHPExcel_IOFactory::identify($filepath);
		$objReader = PHPExcel_IOFactory::createReader($filetype);

		if (stripos($filename, '.csv') > 0) {
			//check semicolon
			$datafile           = file_get_contents($filepath);
			$count_semicolon    = substr_count($datafile, ';');
			$count_comma        = substr_count($datafile, ',');
			$datafile           = '';
			if ($count_semicolon > $count_comma) {
				$objReader->setDelimiter(';');
			}
		}

		$objPHPExcel = $objReader->load($filepath);

		$sheet = $objPHPExcel->getSheet(0);
		$highestRow = $sheet->getHighestRow();
		$highestColumn = $sheet->getHighestColumn();




		for ($row = 1; $row <= $highestRow; $row++) {
			$rowdt = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, NULL, TRUE, FALSE);
			$rowdt = $rowdt[0];

			if ($row == 1 || count($headers) <= 2) {
				$headers = array();
				$is_uti_new_tmp = 0;
				$last_k = '';
				$colnum_of_cq = -1;
				foreach ($rowdt as $k => $t) {
					$t = trim(strtolower($t));

					if (isset($match_header[$t]) && in_array($match_header[$t], array('mutiname', 'mutiresult'))) {
						if (!isset($headers[$match_header[$t]])) {
							$headers[$match_header[$t]] = array();
						}
						if ($match_header[$t] == 'mutiresult' && !isset($headers['mutiname'])) {
							continue;
						}
						$headers[$match_header[$t]][] = $k;
					} else if (isset($match_header[$t]) && !isset($headers[$match_header[$t]])) {
						$headers[$match_header[$t]] = $k;
						$last_k = $t;
					} else if ($settings['enable_im_oncosu'] && strpos($t, ';') > 0) {
						$t = preg_replace('/-[a-zA-Z0-9]+/', '', $t);
						$headers[$t] = $k;
						$headers_mtests[] = $t;
						$last_k = $t;
					} else if ($last_k == 'dye' && $t == 'gene') {
						$headers[$match_header[$t]] = $k;
					}

					if ($t == 'cq') {
						$colnum_of_cq = $k;
					}

					if ($pcr_importuti && in_array($t, array('sample no', 'assay id', 'c(t)'))) {
						$is_uti_new_tmp++;
					}
				}

				if ((isset($settings['enable_rs_ct_amp_cq']) && $settings['enable_rs_ct_amp_cq']) || (isset($settings['rs_hml_amp_cq']) && $settings['rs_hml_amp_cq'])) {
					if (!isset($headers['cq']) && isset($headers['amp_score'])) {
						$headers['cq'] = $colnum_of_cq;
					}
				}

				if (
					!isset($headers['barcode'])
					|| (!isset($headers['drug']) && !isset($headers['drugwell']) && !isset($headers['well']) && !isset($headers['mutiname']))
					|| (!isset($headers['conc']) && !isset($headers['cq']) && !isset($headers['result']) && !isset($headers['mutiresult']))
				) {
					$headers = array();
				} else if ($is_uti_new_tmp >= 3) {

					if (isset($headers['drugwell'])) {
						$headers['drug'] = $headers['drugwell'];
					}

					foreach ($rowdt as $k => $t) {
						$t = trim(strtoupper($t));
						if (isset($test_abrev[$t])) {
							$headers_abrevs[$t] = $k;
							$headers_abrevs_ct[$t] = $k + 1;
						}
					}
					if (count($headers_abrevs) == 0) {
						$is_uti_new_tmp = 0;
					}
				} else {
					$is_uti_new_tmp = 0;
				}

				//search instrument in content

				if (tr_get_option('ci_result_settings_pcr', 'result_ins_filec')) {
					if ((stripos($rowdt[0], 'Instrument Name') !== false
							|| stripos($rowdt[0], 'Instrument Serial Number') !== false)
						&& (strlen($rowdt[1]) > 3 || strpos($rowdt[0], ': ') > 0)
					) {
						$insname = isset($rowdt[1]) ? $rowdt[1] : '';
						if (empty($insname)) {
							$arr22 = explode(':', $rowdt[0]);
							$insname = trim($arr22[1]);
						}
						$instruments[$filename] = $insname;
					}
				}
			} else {
				//do import here

				if ($row < $meta['imported']) {
					continue;
				}



				$barcode    = trim($rowdt[$headers['barcode']]);

				if (preg_match('/^\d+$/', $barcode) === 1 && strlen($barcode) >= 8 && strlen($barcode) <= 10) {
					$labinit = CI_Base_Lab::get_current_lab_init();
					$barcode = $labinit . $barcode;
				}

				if ($settings['enable_im_oncosu']) {

					if (empty($barcode) && !empty($last_assen)) {
						$barcode = $last_assen;
						$num_row_has_assen++;
						if ($num_row_has_assen >= 4) {
							$last_assen = '';
							$barcode = '';
						}
					} else {
						$num_row_has_assen = 0;
					}
				}
				$last_assen = $barcode;



				$conc       = trim(isset($rowdt[$headers['conc']]) ? $rowdt[$headers['conc']] : (isset($headers['cq']) && isset($rowdt[$headers['cq']]) ? $rowdt[$headers['cq']] : ''));
				if ($conc === '' && isset($settings['blank_vl']) && !empty($settings['blank_vl'])) {
					$conc = $settings['blank_vl'];
				} else if (strtolower($conc) === 'nan' && isset($settings['nan_value']) && !empty($settings['nan_value'])) {
					$conc = $settings['nan_value'];
				} else if (strtolower($conc) === 'undetermined' && isset($settings['undetecter_value']) && !empty($settings['undetecter_value'])) {
					$conc = $settings['undetecter_value'];
				}

				$batch_id   = isset($meta['batch_id']) ? $meta['batch_id'] : '';
				$instrument = isset($instruments[$filename]) ? $instruments[$filename] : '';
				if (empty($instrument) && isset($settings['default_ms'])) {
					$instrument = $settings['default_ms'];
				}
				$pass_override = false;
				$drug = '';
				if (isset($headers['mtemp'])) {
					$drug = $IC_covid_tests[0];
				} else if (isset($headers['drug'])) {
					$drug = strtoupper(trim($rowdt[$headers['drug']]));
				}


				if (isset($headers_mtests) && count($headers_mtests) > 0) {
					$drug = 'multitests';
				} else if (isset($headers['mutiname'])) {
					$drug = 'mutiname';
					if (strtolower($barcode) == 'ctrl positive') {
						$ctrl_positive = 'POS';
						foreach ($headers['mutiresult'] as $i => $npos) {
							$posneg = $rowdt[$npos];
							if (stripos($posneg, 'negative') !== false) {
								$ctrl_positive = 'NEG';
							}
						}
						continue;
					}
				} else if ($is_uti_new_tmp) {
					$drug = 'abrev';
				}
				if (empty($drug) && isset($headers['well'])) {
					$drug = 'well';
				}



				$is_qc = false;
				if (stripos($barcode, 'PC:') === 0) {
					$barcode = str_replace('PC:', '', $barcode);
				} else if (stripos($barcode, 'NC:') === 0) {
					$barcode = str_replace('NC:', '', $barcode);
				}

				if (stripos($barcode, 'low') !== false || strtoupper($barcode) == 'LQC') {
					$barcode = 'LQC';
					$is_qc = true;
				} else if (stripos($barcode, 'high') !== false || strtoupper($barcode) == 'HQC') {
					$barcode = 'HQC';
					$is_qc = true;
				} else if (isset($qc_barcodes[$barcode])) {
					$is_qc = true;
				} else if (in_array($barcode, $qc_barcodes)) {
					$is_qc = true;
					$barcode = array_search($barcode, $qc_barcodes);
				}


				if (
					empty($drug)
					|| empty($barcode)
					|| in_array($barcode, $excludebc)
					|| stripos($barcode, 'GDL-') === 0
					|| (!$is_qc && stripos($barcode, 'neg') !== false)
					|| (!$is_qc && stripos($barcode, 'pos') !== false)
					|| stripos($barcode, 'patient ') === 0
					|| (!$is_qc && strlen($barcode) < 7)
				) {
					//echo $barcode. 'empty';
					continue;
				}


				if (!$is_qc && $only_qc) {
					continue;
				}



				if (!isset($meta['assen_list']) || !is_array($meta['assen_list'])) {
					$meta['assen_list'] = array();
				}
				if (!in_array($barcode, $meta['assen_list'])) {
					$meta['assen_list'][] = $barcode;
				}

				$report_id = isset($reports[$barcode]) ? $reports[$barcode] : '';


				if ($is_qc) {

					if ($drug == 'well') continue;
					//check exist
					$datetime 	= date_i18n('Y-m-d');
					if (!empty($batch_id)) {
						$datetime = date('Y-m-d', strtotime($batch_id));
					}
					$data = array(
						'assen' 	=> $barcode,
						'DateTime' 	=> $datetime,
						'Instrument' => $instrument,
						'created' 	=> date('Y-m-d H:i:s'),
					);

					$qc_id = $wpdb->get_var("select ID from ci_pcr_qc 
											where assen = '{$barcode}' 
											and DateTime = '{$datetime}' ");

					if ($qc_id == 0) {

						if (!isset($reports[$barcode])) {
							Tr_Session_Class::add('QC: ' . $barcode);
						}
						$wpdb->insert('ci_pcr_qc', $data);
						$qc_id = $wpdb->insert_id;
					} else {
						$wpdb->update('ci_pcr_qc', array(
							'Instrument' => $instrument
						), array('ID' => $qc_id));
					}

					//insert tests
					if ($qc_id > 0) {

						$files[$filename]['inserted'][$barcode] = 1;
						$conc = substr($conc, 0, 20);

						$reports[$barcode] = $qc_id;
						$Units = '';
						$qctid = $wpdb->get_var("select ID from ci_pcr_qc_tests
												where QC_ID = {$qc_id} 
												and TestName = '{$drug}' ");



						if ($qctid > 0) {
							$urs = $wpdb->update('ci_pcr_qc_tests', array(
								'TestResult' => $conc,
								'Units' => $Units,
							), array('ID' => $qctid));
						} else {
							$urs = $wpdb->insert('ci_pcr_qc_tests', array(
								'QC_ID' => $qc_id,
								'TestName' => $drug,
								'TestResult' => $conc,
								'Units' => $Units,
							));
						}

						//Tr_Session_Class::add('QC: ' . $barcode . ':' . $drug . '=>' . ($urs ? 'ok' : $wpdb->last_query . $wpdb->last_error));
					}


					continue; //important
				}

				if (!isset($reports[$barcode]) || $report_id == 0) {
					$report = $wpdb->get_row("select * from {$table_report} where patient_id = '{$barcode}' ", ARRAY_A);
					if ($report && $report['id'] > 0) {
						$report_id = $report['id'];
					}
				}


				if (!empty($check_pass_type)) {

					if ($check_pass_type == 'user') {
						if (isset($_REQUEST['pass_override_all'])) {
							$_SESSION['pcr_passoverride_all'] = intval($_REQUEST['pass_override_all']);
						}

						$pass_override = $_SESSION['pcr_passoverride_all'];
					} else {
						if (isset($_REQUEST['pass_override']) && !empty($_REQUEST['barcode'])) {
							$_SESSION['pcr_passoverride'][$_REQUEST['barcode']] = $_REQUEST['pass_override'];
						}
						$pass_override = isset($_SESSION['pcr_passoverride'][$barcode]) ? $_SESSION['pcr_passoverride'][$barcode] : false;
					}


					if ($check_pass_type == 'user' && ($pass_override === 1 || $pass_override === 0)) {
						//dont check again
					} else if (!isset($reports[$barcode])) {

						if ($report_id > 0) {
							//show confirm
							if (!isset($_SESSION['pcr_passoverride'][$barcode]) && !empty($report['upload_files'])) {
								$hasoldpanel = false;
								$old_upload_files = explode(',', $report['upload_files']);

								foreach ($old_upload_files as $ofi) {
									if ($ofi == $fname) {
										$hasoldpanel = true;
									}
									/*
									list($bidtm,$pane) = explode('_',$ofi,2);
									$pane = substr($pane, 0, 4);
									if($pane==$filepanel)
									{
										$hasoldpanel = true;
									}
									*/
								}

								if ($hasoldpanel && !$only_insert) {
									$_SESSION['files_pcr_import'] = $files;
									echo $row . '/' . $highestRow . ' rows';
									if ($check_pass_type == 'user') {
										echo '<div class="check_pass_user"></div>';
									} else {
										echo '<div class="check_pass" data-barcode="' . $barcode . '"></div>';
									}

									//var_dump($_SESSION['pcr_passoverride']);
									exit;
								} else {
									$_SESSION['pcr_passoverride'][$barcode] = false;
								}
							}
						}
					}

					if ($pass_override === '0' || $pass_override === 0) {
						$files[$filename]['skipped'][$barcode] = 1;
						$_SESSION['files_pcr_import'] = $files;
						//echo $barcode.' continue';
						continue;
					}
				}

				$need_check_reverse = false;
				$batch = isset($batches[$barcode]) ? $batches[$barcode] : array();

				if (empty($batch)) {
					$batch = $wpdb->get_row("select mid,
					            batch_id,
					            doc,
					            batch_status,
					            batch_created
					 			from {$table} where assen = '{$barcode}' ", ARRAY_A);

					if ($batch) {
						//reset reflex
						if ($batch['mid'] > 0) {
							$wpdb->delete('ci_batches_pcr_meta', array('mid' => $batch['mid'], 'meta_key' => '_last_reflex'));
						}

						if ($settings['notfify_reflab_esam'] && !empty($batch) && empty($batch['patient_id'])) {
							$lab3lt = substr($barcode, 0, 3);
							if (in_array($lab3lt, $reflab_letters)) {
								if (!isset($_SESSION['ref_lab_empty_samples'])) {
									$_SESSION['ref_lab_empty_samples'] = array();
								}
								if (!in_array($barcode, $_SESSION['ref_lab_empty_samples'])) {
									$_SESSION['ref_lab_empty_samples'][] = $barcode;
								}
							}
						}
					}
					$batches[$barcode] = $batch;
				}

				$batch_id = isset($batch['batch_id']) ? $batch['batch_id'] : '';




				if (empty($batch_id) && isset($batch['mid']) && $batch['mid'] > 0) {
					//search in receiving first
					$re_today = $wpdb->get_var("select ri.re_today 
                                        from ci_receiving ri
                                        join ci_receiving_batches rb ON ri.re_ID = rb.re_ID
                                        where rb.btype='pcr' and rb.mid = '{$batch['mid']}' ");
					if (!empty($re_today)) {
						$batch_id = str_replace('-', '', $re_today);
					} else {
						if (
							empty($batch['doc'])
							|| $batch['doc'] === '0000-00-00'
							|| $batch['doc'] === '0000-00-00 00:00:00'
						) {
							$batch['doc'] = isset($batch['batch_created']) ? $batch['batch_created'] : '';
						}
						$batch_id = date_i18n('Ymd', strtotime($batch['doc']));
					}

					$wpdb->update($table, array('batch_id' => $batch_id), array('mid' => $batch['mid']));
				}

				if (!empty($batch_id)) {
					$main_batch_id = $batch_id;
				}


				if (empty($report_id)) {
					$acquisition_date = preg_replace("/([0-9]{4})([0-9]{2})([0-9]{2})/", '$1-$2-$3', $batch_id);
					$data = array(
						'patient_id' => $barcode,
						'batch_id' => $batch_id . '',
						'lab_id' => CI_Base_Lab::get_current_lab_id(),
						'acquisition_date' => $acquisition_date . '',
						'author_id' => $user_id,
						'created_date' => $created_date,
						'created' => $created_datetime,
						'instrument' => $instrument,
						'upload_files' => $filename,
					);

					$rsins = $wpdb->insert($table_report, $data);
					if ($rsins === false) {
					}

					$report_id = $wpdb->insert_id;
				} else if (!$only_insert && !isset($reports[$barcode])) {

					$oldreport = $wpdb->get_row("select estatus, upload_files from {$table_report} where id = '{$report_id}' ");
					$old_upload_files = $oldreport->upload_files;
					$old_upload_files = explode(',', $old_upload_files);
					$old_upload_files[] = $filename;
					$old_upload_files = array_unique($old_upload_files);
					$old_upload_files = implode(',', $old_upload_files);
					$data = array(
						'patient_id' => $barcode,
						'batch_id' 	=> $batch_id,
						'lab_id' 	=> CI_Base_Lab::get_current_lab_id(),
						'author_id' => $user_id,
						'instrument' => $instrument,
						'upload_files' => $old_upload_files,
						'modified' => time(),
					);

					if ($oldreport->estatus == 'C') {
						if ($pass_override === '1') {
							$data['estatus'] = 'M';
						}
						//$data['estatus'] = 'M';
						$need_check_reverse = true;
					}

					$wpdb->update($table_report, $data, array('id' => $report_id));
				}


				if ($report_id > 0) {

					if (!isset($reports[$barcode])) {
						Tr_Session_Class::add('Accession: ' . $barcode);
					}
					$reports[$barcode] = $report_id;


					$group_id = isset($group_ids[$filename]) ? $group_ids[$filename] : 0;

					if ($drug == 'COVID-19' && tr_get_option('ci_pdf_data', 'pcrcovid_covidnot19')) {
						$drug = 'COVID';
					} else if ($drug == 'COVID' && !tr_get_option('ci_pdf_data', 'pcrcovid_covidnot19')) {
						$drug = 'COVID-19';
					} else if ($drug == 'NCOV-2019_N1') {
						$drug = 'N1';
					} else if ($drug == 'NCOV-2019_N2') {
						$drug = 'N2';
					} else if ($drug == 'TARGET 1') {
						$drug = 'N1';
					} else if ($drug == 'TARGET 2') {
						$drug = 'N2';
					} else if ($drug == 'TARGET 3') {
						$drug = 'RP';
					} else if ($pcrcovid_in1crpic) {
						if ($drug == 'N1') {
							$drug = 'COVID-19';
						} else if ($drug == 'RP') {
							$drug = 'IC';
						}
					} else if ($drug == 'I/C' && tr_get_option('ci_pdf_data', 'pcrcovid_famvic')) {
						$drug = 'IC';
					}


					$mutiname = false;
					if ($drug == 'multitests') {
						$mutiname = true;
						$drug_rs = array();
						foreach ($headers_mtests as $mtkey) {

							$testsnames = explode(';', $mtkey);
							$testsnames = array_map('trim', $testsnames);
							$drug 		= $testsnames[$num_row_has_assen];
							$conc 		= $rowdt[$headers[$mtkey]];
							$drug_rs[$drug] = array('conc' => $conc);
						}
					} else if ($drug == 'mutiname') {
						$mutiname = true;
						$drug_rs = array();
						foreach ($headers['mutiname'] as $i => $npos) {
							$drug = $rowdt[$npos];
							$conc = $rowdt[$headers['mutiresult'][$i]];
							$posneg = '';
							if (stripos($conc, 'negative') !== false) {
								$posneg = 'NEG';
								$conc = '';
							} else if (stripos($conc, 'positive') !== false) {
								$posneg = 'POS';
								$conc = '';
							}
							$drug_rs[$drug] = array('conc' => $conc, 'pos-neg' => $posneg);
						}
					} else if ($drug == 'well') {
						$drug = trppd_get_drug_by_well($barcode, strtoupper(trim($rowdt[$headers['well']])), 'pcr');
						$drug_rs = array($drug => array('conc' => $conc));
					} else if ($is_uti_new_tmp) {
						$drug_rs = array();
						foreach ($headers_abrevs as $habrev => $abrevpos) {
							$conc = $rowdt[$headers_abrevs_ct[$habrev]];
							$pos_neg = $rowdt[$abrevpos];
							if ($pos_neg == '+') {
								$pos_neg = 'POS';
							} else if ($pos_neg == '-') {
								$pos_neg = 'NEG';
							}

							$drug_rs[$habrev] = array(
								'conc' => $conc,
								'pos-neg' => $pos_neg,
							);
						}
					} else {
						$drug_rs = array($drug => array('conc' => $conc));
						if ($settings['enable_rs_ct_amp_cq'] || $settings['rs_hml_amp_cq']) {
							$drug_rs[$drug]['cq'] = substr($rowdt[$headers['cq']], 0, 8);
							$drug_rs[$drug]['amp_score'] = substr($rowdt[$headers['amp_score']], 0, 8);

							if (isset($rowdt[$headers['amp_status']]) && stripos($rowdt[$headers['amp_status']], 'NO_AMP') !== false) {
								$drug_rs[$drug]['amp_score'] = '0';
							}

							if (isset($headers['percent_pos'])) {
								$drug_rs[$drug]['percent_positive'] = substr($rowdt[$headers['percent_pos']], 0, 8);
							}
						}



						if ($conc === '' && isset($rowdt[$headers['result']])) {
							$pnr = $rowdt[$headers['result']];
							if (stripos($pnr, 'NEG') !== false) {
								$drug_rs[$drug]['pos-neg'] = 'NEG';
							} else if (stripos($pnr, 'POS') !== false) {
								$drug_rs[$drug]['pos-neg'] = 'POS';
							}
						}

						if ($drug === $IC_covid_tests[0] && isset($headers['mtemp'])) {
							$drug_rs[$IC_covid_tests[1]] = array('conc' => $rowdt[$headers['mtemp']]);

							if ($pcr_cv_file && !empty($covid_drug) && isset($headers['result'])) {
								$pnr = $rowdt[$headers['result']];

								if (stripos($pnr, 'NEG') !== false) {
									$drug_rs[$covid_drug] = array('conc' => '', 'pos-neg' => 'NEG');
								} else if (stripos($pnr, 'POS') !== false) {
									$drug_rs[$covid_drug] = array('conc' => '', 'pos-neg' => 'POS');
								}
							}
						}
					}


					$logarr = array(
						'file' => basename($filename),
					);

					foreach ($drug_rs as $drug => $darr) {
						$conc = $darr['conc'];
						$drug = trim($drug);
						$drug = strtoupper($drug);
						$main_drug = $drug;
						if (empty($drug)) continue;

						if (isset($test_abrev2[$drug])) {
							$group_id = 0;
						} else if (strpos($drug, '-') > 0) {
							list($newdrug, $groupk) = explode('-', $drug);
							$newdrug = trim($newdrug);
							$groupk = strtoupper(trim($groupk));
							if (isset($all_groups[$groupk])) {
								$group_id = $all_groups[$groupk];
								$drug = $newdrug;
							}
						}

						$drug_by_abrev = false;

						if ($drug != 'IC' && !in_array($drug, $all_test_names)) {
							if (isset($test_abrev[$drug])) {
								$drug = $test_abrev[$drug];
								$drug_by_abrev = true;
							} else if (isset($test_abrev2[$drug])) {
								$drug = $test_abrev2[$drug];
								$drug_by_abrev = true;
							}
						} else if (isset($test_abrev[$drug]) && $drug == $test_abrev[$drug]) {
							$drug_by_abrev = true;
						}

						$is_covid = in_array($drug, $up_covid_tests);


						if (empty($conc) && $is_covid) {
							$conc = 'NaN';
						} else if (strtolower($conc) === 'noct') {
							$conc = 40;
						}


						if (empty($instrument) || stripos($instrument, 'studio1') !== false) {
							if ($drug == 'PRC') {
								$drug = 'IC';
							} else if ($drug == 'SARS-COV-2' && !isset($test_abrev[$drug]) && !$drug_by_abrev && !$mutiname) {
								$drug = 'COVID-19';
							}

							if ($conc == 'Undetermined') {
								$conc = 'NaN';
							}
						}


						if ($is_covid) {
							$update_covid_ids[$report_id] = $report_id;
							$covid_file_type = $type_by_covid_tests[$drug];
						}

						$data_result = array(
							'report_id' => $report_id,
							'drug' => $drug,
							'conc' => substr($conc, 0, 15),
							'instrument' => $instrument
						);
						if ((isset($settings['enable_rs_ct_amp_cq']) && $settings['enable_rs_ct_amp_cq']) || (isset($settings['rs_hml_amp_cq']) && $settings['rs_hml_amp_cq'])) {
							$data_result['cq'] = substr($darr['cq'], 0, 10);
							$data_result['amp_score'] = substr($darr['amp_score'], 0, 10);

							if (empty($covid_file_type) && in_array($main_drug, array('AV_BA04646222_S1', 'LI_BA04646257_S1'))) {
								$covid_file_type = 'PWH';
							}
						}

						if (isset($darr['pos-neg'])) {
							$data_result['pos-neg'] = $darr['pos-neg'];
						}

						$exists = $wpdb->get_row("select * 
						from {$table_results} 
						where report_id = {$report_id} and drug='{$drug}'");

						if (!$exists && $drug != $main_drug) {
							$exists = $wpdb->get_row("select * 
							from {$table_results} 
							where report_id = {$report_id} and drug='{$main_drug}'");
						}

						if (isset($result2times[$report_id][$drug])) {
							$numdrug2 = 2;
							$drug2 = trim($drug . '-' . $numdrug2);
							while (isset($result2times[$report_id][$drug2])) {
								$numdrug2++;
								$drug2 = trim($drug . '-' . $numdrug2);
							}
							//check second
							$drug = $drug2;
							$data_result['drug'] = $drug2;
							$exists = $wpdb->get_row("select * from {$table_results} 
							where report_id = {$report_id} and drug='{$drug2}'");

							$limit = 0;
						}

						if ($exists && $exists->id > 0) {
							//log to duplicate

							if (empty($exists->conc)) {
								$wpdb->update($table_results, $data_result, array(
									'id' => $exists->id
								));


								$logarr[$drug] = array('old' => array(), 'new' => array());
								$logarr[$drug]['new']['conc'] = $data_result['conc'];
								if (isset($data_result['pos-neg']) && !empty($data_result['pos-neg'])) {
									$logarr[$drug]['new']['pos-neg'] = $data_result['pos-neg'];
								}
								if (isset($data_result['cq']) && !empty($data_result['cq'])) {
									$logarr[$drug]['new']['cq'] = $data_result['cq'];
								}
								if (isset($data_result['amp_score']) && !empty($data_result['amp_score'])) {
									$logarr[$drug]['new']['amp_score'] = $data_result['amp_score'];
								}
							} else {
								//add duplicates to session
								$data_result['id'] = $exists->id;
								$data_result['old'] = $exists->conc;
								if (isset($data_result['cq'])) {
									$data_result['old_cq'] = $exists->cq;
									$data_result['old_amp_score'] = $exists->amp_score;
								}
								$data_result['old-pos-neg'] = $exists->{'pos-neg'};
								$data_result['barcode'] = $barcode;
								if (empty($data_result['conc']) && !empty($data_result['pos-neg'])) {
									$data_result['old'] = $exists->{'pos-neg'};
									$data_result['conc'] = $data_result['pos-neg'];
								}
								if ($report && $report['id'] > 0) {
									$data_result['estatus'] = $report['estatus'];
									$data_result['final_results'] = $report['final_results'];
								}
								$files[$filename]['duplicates'][$barcode][$drug] = $data_result;
							}
						} else {
							if ($need_check_reverse) {
								$reports_to_M[$report_id] = $report_id;
							}
							$rsq = $wpdb->insert($table_results, $data_result);


							if ($rsq === false) {
								$files[$filename]['error'][$barcode][$drug] = $data_result;
							} else {
								$files[$filename]['inserted'][$barcode] = 1;

								$logarr[$drug] = array('old' => array(), 'new' => array());
								$logarr[$drug]['new']['conc'] = $data_result['conc'];
								if (isset($data_result['pos-neg']) && !empty($data_result['pos-neg'])) {
									$logarr[$drug]['new']['pos-neg'] = $data_result['pos-neg'];
								}
								if (isset($data_result['cq']) && !empty($data_result['cq'])) {
									$logarr[$drug]['new']['cq'] = $data_result['cq'];
								}
								if (isset($data_result['amp_score']) && !empty($data_result['amp_score'])) {
									$logarr[$drug]['new']['amp_score'] = $data_result['amp_score'];
								}
							}
						}


						if (!isset($result2times[$report_id])) {
							$result2times[$report_id] = array();
						}
						$result2times[$report_id][$drug] = $data_result['conc'];
					} //end for drugs


					//log user
					if (isset($batch['mid']) && $batch['mid'] > 0 && !in_array($barcode, $logged_barcode)) {
						trpdd_log_user($user_id, 'uploaded_rs', $logarr, $batch['mid'], 'pcr', '', '', $barcode);

						$logged_barcode[] = $barcode;
					}
				}



				$count_import++;
				$files[$filename]['imported'] = $row;
				$files[$filename]['assen_list'] = $meta['assen_list'];
				if ($limit > 0 && $count_import > $limit) {
					$_SESSION['files_pcr_import'] = $files;
					echo $row . '/' . $highestRow . ' rows';

					if (!empty($ctrl_positive)) {
						foreach ($reports as $report_id) {
							$rsid1 = $wpdb->get_var("select id from {$table_results} 
							where report_id = {$report_id} and drug='CTRL_POSITIVE' ");
							$ctrdata = array('drug' => 'CTRL_POSITIVE', 'report_id' => $report_id, 'pos-neg' => $ctrl_positive);
							if ($rsid1 > 0) {
								$wpdb->update($table_results, $ctrdata, array('id' => $rsid1));
							} else {
								$wpdb->insert($table_results, $ctrdata);
							}
						}
					}

					if (count($reports_to_M) > 0) {
						$rpids_str = implode(',', $reports_to_M);
						$wpdb->query("update {$table_report} set estatus = 'M' where estatus = 'C' and id in ({$rpids_str})");
					}
					exit;
				}
			}
		}

		if (count($reports_to_M) > 0) {
			$rpids_str = implode(',', $reports_to_M);
			$wpdb->query("update {$table_report} set estatus = 'M' where estatus = 'C' and id in ({$rpids_str})");
		}

		if (
			!isset($headers['barcode'])
			|| (!isset($headers['drug'])  && !isset($headers['mtemp']) && !isset($headers['mutiname']))
			|| (!isset($headers['conc']) && !isset($headers['result']) && !isset($headers['mutiresult']))
		) {
			echo $filename . ': Format not correct';
		}

		$files[$filename]['done'] = true;
		$_SESSION['files_pcr_import'] = $files;

		if (!empty($ctrl_positive)) {
			foreach ($reports as $report_id) {
				$rsid1 = $wpdb->get_var("select id from {$table_results} 
				where report_id = {$report_id} and drug='CTRL_POSITIVE' ");
				$ctrdata = array('drug' => 'CTRL_POSITIVE', 'report_id' => $report_id, 'pos-neg' => $ctrl_positive);
				if ($rsid1 > 0) {
					$wpdb->update($table_results, $ctrdata, array('id' => $rsid1));
				} else {
					$wpdb->insert($table_results, $ctrdata);
				}
			}
		}


		//update file
		$assen_list = implode(',', is_array($meta['assen_list']) ? $meta['assen_list'] : array());
		$updatefile = array(
			'rf_assen_list' => $assen_list,
			'rf_covid_type' => substr($covid_file_type, 0, 7),
		);
		if (!empty($main_batch_id)) {
			$updatefile['rf_batch_id'] = $main_batch_id;
		}
		$wpdb->update('ci_reports_files', $updatefile, array('rf_ID' => $meta['file_id']));
	}


	if (count($update_covid_ids) > 0) {
		$update_covid_ids = implode(',', $update_covid_ids);
		$wpdb->query("update {$table_report} set covid_pos = null 
	                            where id in ({$update_covid_ids})");
		$update_covid_ids = array();
	}

	$duplicates = '';

	foreach ($_SESSION['files_pcr_import'] as $filename => $meta) {
		$meta['skipped'] = isset($meta['skipped']) && is_array($meta['skipped']) ? $meta['skipped'] : array();
		$meta['updated'] = isset($meta['updated']) && is_array($meta['updated']) ? $meta['updated'] : array();
		$meta['inserted'] = isset($meta['inserted']) && is_array($meta['inserted']) ? $meta['inserted'] : array();
		$meta['error'] = isset($meta['error']) && is_array($meta['error']) ? $meta['error'] : array();

		$skipped = count($meta['skipped']);
		$updated = count($meta['updated']);
		$inserted = count($meta['inserted']);
		$error = count($meta['error']);
		$total = $inserted + $updated + $skipped + $error;

		if (isset($_GET['debug'])) {
			var_dump($meta['error']);
		}

		//$filename = preg_replace('/[0-9]*\_/','',$filename);

		if (isset($meta['duplicates']) && is_array($meta['duplicates']) && count($meta['duplicates']) > 0) {
			$count_dup = 0;
			$showed_barcodes = array();
			foreach ($meta['duplicates'] as $barcode => $dups) {
				$covid1 = array();
				foreach ($dups as $drug => $arr) {
					$class = '';
					if (!in_array($barcode, $showed_barcodes)) {
						$showed_barcodes[] = $barcode;
					} else {
						$class .= ' hidebarcode ';
					}

					if (in_array($drug, $up_covid_tests)) {
						$covid1[$drug] = $arr;
					} else {
						$oldvl = $arr['old'];
						$newvl = $arr['conc'];
						if (isset($arr['cq']) && !empty($arr['cq'])) {
							$oldvl .= ' (CQ: ' . $arr['old_cq'] . ')';
							$newvl .= ' (CQ: ' . $arr['cq'] . ')';
						}
						if (isset($arr['amp_score']) && !empty($arr['amp_score'])) {
							$oldvl .= ' (Amp: ' . $arr['old_amp_score'] . ')';
							$newvl .= ' (Amp: ' . $arr['amp_score'] . ')';
						}
						if (empty($newvl) && !empty($arr['pos-neg'])) {
							$newvl = $arr['pos-neg'];
							$oldvl = $arr['old-pos-neg'];
						}
						if ($newvl === $oldvl) {
							//continue;
						}

						$arr['status_type'] = tr_pdd_get_result_status($arr['final_results'], false);
						if (empty($arr['status_type'])) {
							$arr['status_type'] = 'Approved';
						}

						$duplicates .= '<tr class="tbrow ' . $class . '" data-id="' . $arr['id'] . '" data-estatus="' . $arr['estatus'] . '" data-status_type="' . $arr['status_type'] . '" data-report_id="' . $arr['report_id'] . '">    

									<td class="cb"><span class="checkmark" style="display:none"></span><input type="checkbox" value="' . $arr['id'] . '"/></td>
									<td class="barcode">' . $arr['barcode'] . '</td>
									<td class="drug">' . $arr['drug'] . '</td>
									<td class="old">' . $oldvl . '</td>
									<td class="new">' . $newvl . '</td>
									<td><a class="imp_approve button">Approve</a>  <a class="imp_deny button">Deny</a></td>
									</tr>';
						$count_dup++;
					}
				}

				if (count($covid1) > 0) {
					$covidids = array();
					$arr = array();
					$drugs = array();
					$olds = array();
					$news = array();
					foreach ($covid1 as $drug => $arr) {
						$covidids[] = $arr['id'];
						$repid = $arr['report_id'];
						$drugs[] = $arr['drug'];
						$olds[] = $arr['old'];
						$news[] = $arr['conc'];
					}
					$covidids = implode(',', $covidids);
					$drugs = implode('<br>', $drugs);
					$olds = implode('<br>', $olds);
					$news = implode('<br>', $news);

					$duplicates .= '<tr class="tbrow" data-id="' . $covidids . '" data-estatus="' . $arr['estatus'] . '" data-report_id="' . $repid . '">    
                    <td class="cb"><span class="checkmark" style="display:none"></span><input type="checkbox" value="' . $covidids . '"/></td>
                    <td class="barcode">' . $arr['barcode'] . '</td>
                    <td class="drug">' . $drugs . '</td>
                    <td class="old">' . $olds . '</td>
                    <td class="new">' . $news . '</td>
                    <td><a class="imp_approve button">Approve</a>  <a class="imp_deny button">Deny</a></td>
                    </tr>';
					$count_dup++;
				}
			}
			$total = $inserted + $updated + $skipped + $error + $count_dup;
			if ($error_no == 0) {
				Tr_Session_Class::add('File: ' . $filename .
					'<br>Total: ' . $total .
					'<br>Inserted: ' . $inserted .
					'<br>Duplicate: ' . $count_dup .
					'<br>Error: ' . $error);
			}
		} else if ($error_no == 0) {
			Tr_Session_Class::add('File: ' . $filename .
				'<br>Total: ' . $total .
				'<br>Inserted: ' . $inserted .
				'<br>Updated: ' . $updated .
				'<br>Skipped: ' . $skipped .
				'<br>Error: ' . $error);
		}
	}

	if (isset($_SESSION['ref_lab_empty_samples']) && count($_SESSION['ref_lab_empty_samples']) > 0) {
		Tr_Session_Class::add_error('The following samples were uploaded but they have not been sent by the referring lab:<br>' . implode('<br>', $_SESSION['ref_lab_empty_samples']));
	}



	if (!empty($duplicates)) {
		Tr_Session_Class::show();

	?>
		<h2>Duplicates</h2>

		<form method="post" id="formduplicate">

			<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">

				<thead>
					<tr>
						<th style="width:30px"><input type="checkbox" class="checkall" /></th>
						<th style="width: 110px;">Barcode</th>
						<th style="width:270px">Test</th>
						<th>Old CT</th>
						<th>New CT</th>
						<th style="width:155px">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php echo $duplicates ?>
				</tbody>

				<tfoot>
					<tr>
						<td colspan="6">
							<div class="fix_bar center">
								<input type="button" id="approve_selected" class="button" value="Approve Selected" />
								<input type="button" id="deny_selected" class="button" value="Deny Selected" />
								<input type="submit" class="button-primary" value="Import" />
							</div>
						</td>
					</tr>
				</tfoot>
			</table>
			<br>
		</form>
		<br><br>
		<div class="success_import"></div>

<?php

	} else {
		Tr_Session_Class::show();
		if ($error_no == 0) {
			echo '<div class="success_import">Imported Successfully! <br><a href="' . Tr_Base_Class_V6::link(array('act' => '', 'trajax' => '', 'tab' => ''), false) . '">Click to go back to Results!</a></div>';
		} else {
			echo '<div class="success_import"><a href="' . Tr_Base_Class_V6::link(array('act' => '', 'trajax' => '', 'tab' => ''), false) . '">Click to go back to Results!</a></div>';
		}
	}


	$nextcron = wp_next_scheduled('ci_cron_clean_results');
	if ($nextcron == 0 || $nextcron > time() + 360) {
		wp_schedule_single_event(time() + 180, 'ci_cron_clean_results', array('btype' => 'pcr'));
	}


	if (isset($_REQUEST['trajax'])) {
		exit;
	}
}

$_SESSION['pcr_passoverride'] = array();
$_SESSION['pcr_passoverride_all'] = false;
$_SESSION['files_pcr_import'] = array();

$allow_files = 'text/csv';
$has_manual2_antibiotic = false;
$drug_groups = tr_pdd_get_drug_groups('pcr', array(), true);
foreach ($drug_groups as $groupdata) {
	if (isset($groupdata['other_options']['mantibiotic']) && $groupdata['other_options']['mantibiotic']) {
		$has_manual2_antibiotic = true;
	}
}
if ($has_manual2_antibiotic) {
	$allow_files .= ',.txt';
}

if (tr_get_option('ci_pdf_data', 'ins_biomic_pcr')) {
	$allow_files .= ',.hl7';
}

?>
<div class="wrap">
	<h2 class="center">Upload Raw Data</h2>

	<?php Tr_Session_Class::show() ?>
	<div class="form-wrap center">
		<form enctype="multipart/form-data" method="post">
			<input type="hidden" name="act" value="ci_doctor_process">

			<p class="alert message warning">Please only upload CSV files!</p>


			<div class="frow card_file">
				<a class="button selectfile" href="#">Choose Files</a>
				<span class="view"></span>
				<input type="file" id="upload-files" name="files[]" accept="<?php echo $allow_files ?>" multiple />
			</div>


			<div class="frow">
				<input type="submit" value="Upload" class="button-primary" />
			</div>

			<br>

			<label>
				<input type="checkbox" name="onlyqc" value="1" /> Only Import QC</label>

			<label>
				<input type="checkbox" name="only_insert" value="1" /> Only Insert New</label>
			<p><em>To upload multiple files, hold down SHIFT while clicking to select a group or CTRL to
					select multiple files individually.</em></p>

			<?php if (isset($_GET['debug']) || $is_ci_admin) { ?>
				<a href="<?php Tr_Base_Class_V6::link(array('act' => 'demo_file', 'trajax' => 1, 'debug' => '')) ?>">Demo file</a>
			<?php } ?>
		</form>
	</div>
</div>