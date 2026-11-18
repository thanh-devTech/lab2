<?php
global $wpdb;

require TRCPDD_PATH . 'inc/PHPExcel/IOFactory.php';

$act 			= isset($_REQUEST['act']) ? $_REQUEST['act'] : '';
$only_insert 	= isset($_REQUEST['only_insert']) ? true : false;
$req_cv_file 	= tr_get_option('ci_pdf_data', 'req_cv_file');
$rs_settings    = get_option('ci_result_settings_req', array());

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

$table              = 'ci_batches_req';
$table_report       = 'ci_reports_req';
$table_results      = 'ci_results_req';

$all_instruments = $wpdb->get_col("select instrument from `ci_instruments` where btype='req' ");
$all_instruments = array_map("strtoupper", $all_instruments);
usort($all_instruments, function ($a, $b) {
	return strlen($b) - strlen($a);
});

$rs             = $wpdb->get_results("select * from ci_test_groups where tg_type = 'req' ");
$all_groups     = array();
$adding_groups  = array();
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

if (isset($_GET['debug'])) {
	var_dump($all_groups);
}

if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'check_user_pass') {

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
} else if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'get_mf_data') {
	$return = array();
	$info = pathinfo(str_replace('\\', '/', stripslashes($_REQUEST['file'])));
	$assen = $info['filename'];
	$assen = str_replace('redo', '', $assen);
	$assen = trim($assen);
	//$return['info'] = $info;
	$return['assen'] = $assen;
	$mf_data = $wpdb->get_var("select m.comments from ci_results_req m
	join ci_reports_req re On re.id = m.report_id 
	where re.patient_id = '{$assen}' 
	and m.drug = '_mfdata' ");
	$return['mf_data'] = json_decode($mf_data, true);
	wp_send_json($return);
} else if ($act == 'approve_import_checkboxes') {
	$ids = $_REQUEST['ids'];
	$ids = explode(',', $ids);
	$return = array('status' => 'ok');

	foreach ($_SESSION['files_req_import'] as $filename => $meta) {

		if (count($meta['duplicates']) > 0) {
			foreach ($meta['duplicates'] as $barcode => $dups) {
				foreach ($dups as $drug => $arr) {
					if (in_array($arr['id'], $ids)) {
						$update = array('conc' => $arr['conc']);
						if ($req_cv_file && isset($arr['pos-neg']) &&  in_array($arr['pos-neg'], array('POS', 'NEG'))) {
							$update = array('pos-neg' => $arr['pos-neg']);
						}
						$rs = $wpdb->update('ci_results_req', $update, array('id' => $arr['id']));
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

	foreach ($_SESSION['files_req_import'] as $filename => $meta) {

		if (count($meta['duplicates']) > 0) {
			foreach ($meta['duplicates'] as $barcode => $dups) {
				foreach ($dups as $drug => $arr) {
					if (in_array($arr['id'], $ids)) {
						$update = array('conc' => $arr['conc']);
						if ($req_cv_file && isset($arr['pos-neg']) &&  in_array($arr['pos-neg'], array('POS', 'NEG'))) {
							$update = array('pos-neg' => $arr['pos-neg']);
						}

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
		$filename = $file['name'];
		$filetmp  = $file['tmp_name'];
		$info = pathinfo($filename);
		$ext = strtolower($info['extension']);

		if (in_array($ext, array('csv', 'xls', 'xlsx'))) {
			$filepath = $log_dir . $filename;

			if (move_uploaded_file($filetmp, $filepath)) {
				$_SESSION['files_req_import'][$filename] = array(
					'imported' => 0,
					'done' => false
				);
				$uploaded++;

				preg_match("/([0-9]{8})/", $filename, $matches);
				$batch_id = @$matches[1];
				if (empty($batch_id)) {
					preg_match("/Batch ([0-9]+)/", $filename, $matches_lab);
					$batch_id = $matches_lab[1];
				}
				if (empty($batch_id)) {
					$batch_id = date_i18n('Ymd');
				}
				if (!empty($batch_id)) {
					$lab_id = CI_Base_Lab::get_current_lab_id();
					$old = $wpdb->get_row("select rf_ID,rf_count from ci_reports_files 
		                                    WHERE rf_batch_id ='{$batch_id}' 
		                                    AND rf_file='{$filename}' 
		                                    AND rf_lab_site_id='{$lab_id}'  
		                                    and rf_btype='req' ");

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
							'rf_btype' => 'req',
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

					$_SESSION['files_req_import'][$filename]['file_id'] = $file_id;
				}
			}
		} else {
			Tr_Session_Class::add_error('Error: File not support: ' . $file['name']);
			$uploaded = 0;
			break;
		}
	}

	if ($uploaded > 0) {
		$linkimport = Tr_Base_Class_V6::link(array('act' => 'doimport'), false);
		if (isset($_REQUEST['meta_data']['import_type'])) {
			switch ($_REQUEST['meta_data']['import_type']) {
				case 'FA':
					$linkimport = Tr_Base_Class_V6::link(array('act' => 'doimport_FA'), false);
					break;
				case 'MF':
					$linkimport = Tr_Base_Class_V6::link(array('act' => 'doimport_MF'), false);
					break;
			}
		}
		if (isset($_REQUEST['only_insert'])) {
			$linkimport .= '&only_insert';
		}
		if (isset($_REQUEST['debug1'])) {
			$linkimport .= '&debug1';
		}
		if (isset($_REQUEST['debug'])) {
			$linkimport .= '&debug';
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
		</style>
		<script>
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
	include(trpdd_get_file_path('req', 'results_import_fa'));

	if (isset($_REQUEST['trajax'])) {

		Tr_Session_Class::show();
		echo '<div class="success_import">Imported Successfully! <br><a href="' . Tr_Base_Class_V6::link(array('act' => '', 'trajax' => ''), false) . '">Click here to back</a></div>';
		exit;
	}
} else if ($act == 'doimport_MF') {
	include(trpdd_get_file_path('req', 'results_import_mf'));

	if (isset($_REQUEST['trajax'])) {

		Tr_Session_Class::show();
		echo '<div class="success_import">Imported Successfully! <br><a href="' . Tr_Base_Class_V6::link(array('act' => '', 'trajax' => ''), false) . '">Click here to back</a></div>';
		exit;
	}
} else if ($act == 'doimport') {

	$files = $_SESSION['files_req_import'];
	$limit = 1500;
	$match_header = array(
		'barcode' => 'barcode',
		'sample name' => 'barcode',
		'sample' => 'barcode',
		'sample description 1' => 'barcode',
		'dia' => 'barcode',
		'ffid' => 'barcode',
		'target name' => 'drug',
		'target' => 'drug',
		'dyename[any]' => 'drug',
		'assay' => 'drug',
		'ct' => 'conc',
		'cq' => 'conc',
		'conc[any]' => 'conc',
		'sample stock concentration' => 'conc',
		'vcam1' => 'vcam1',
		'psel' => 'psel',
		'mfi-3' => 'mfi-3',
		'mfi-10' => 'mfi-10',
		'preexisting hemolysis' => 'preexisting hemolysis',
		'rfu' => 'rfu',
		'a1c' => 'a1c',
		'insulin' => 'insulin',
		'c-peptide' => 'c-peptide',
		'beta core' => 'beta core',
	);

	$reports = array();
	$batches = array();
	$logged_barcode = array();

	$settings_warn = get_option('tr_warn_popup_req');
	if (!is_array($settings_warn['tests'])) $settings_warn['tests']  = array();
	$testids = implode(',', $settings_warn['tests']);
	if (empty($testids)) {
		$testids  = '0';
	}
	$tests = $wpdb->get_results("select * from ci_test_panels where te_ID in ({$testids})");
	$test_warns = array();
	foreach ($tests as $t) {
		$test_warns[] = strtoupper($t->te_name);
		if (!empty($t->te_abrev)) {
			$test_warns[] = strtoupper($t->te_abrev);
		}
	}


	$tests = $wpdb->get_results("select te_abrev,te_abrev2,te_name from ci_test_panels where te_type in ('blood','pcr') ");
	$test_abrev = array();
	$test_abrev2 = array();
	$all_test_drugs = array();

	foreach ($tests as $t) {
		$t->te_name = strtoupper($t->te_name);
		if (!empty($t->te_abrev)) {
			$t->te_abrev = strtoupper($t->te_abrev);
			$test_abrev[$t->te_abrev] = $t->te_name;
		}
		if (!empty($t->te_abrev2)) {
			$t->te_abrev2 = strtoupper($t->te_abrev2);
			$test_abrev2[$t->te_abrev2] = $t->te_abrev2;
		}

		$all_test_drugs[] = $t->te_name;
	}



	$instruments = array();
	$group_ids = array();

	foreach ($files as $filename => $meta) {
		if ($meta['done']) continue;

		$fname = basename($filename);
		$file_id = $meta['file_id'];

		list($bidtm, $filepanel) = explode('_', $fname, 2);
		$filepanel = substr($filepanel, 0, 4);

		if (empty($instruments[$filename])) {

			foreach ($all_instruments as $mc) {
				if (stripos($fname, $mc) > 0) {
					$instruments[$filename] = substr(strtoupper($mc), 0, 15);
					break;
				}
			}
		}

		if (empty($group_ids[$filename])) {
			foreach ($all_groups as $gname => $gid) {
				if (stripos($fname, $gname) > 0) {
					$group_ids[$filename] = $gid;
					break;
				}
			}
		}

		$headers 		= array();
		$count_import 	= 0;
		$is_ddPCR 		= false;
		$is_dx11 		= false;
		$ddPCR_data 	= array();
		$dx11_data 		= array();

		$filepath 		= $log_dir . $filename;

		$filetype 		= PHPExcel_IOFactory::identify($filepath);
		$objReader 		= PHPExcel_IOFactory::createReader($filetype);

		if (stripos($filename, '.csv') > 0) {

			$contents = file_get_contents($filepath);

			// Remove all non-ASCII (UTF-8) characters
			$clean = preg_replace('/[^\x00-\x7F]/', '', $contents);

			file_put_contents($filepath, $clean);


			//check semicolon
			$datafile           = file_get_contents($filepath);
			$count_semicolon    = substr_count($datafile, ';');
			$count_comma        = substr_count($datafile, ',');
			$datafile           = '';
			if ($count_semicolon > $count_comma) {
				$objReader->setDelimiter(';');
			}
		}


		$objPHPExcel 	= $objReader->load($filepath);

		$sheet = $objPHPExcel->getSheet(0);
		$highestRow = $sheet->getHighestRow();
		$highestColumn = $sheet->getHighestColumn();


		for ($row = 1; $row <= $highestRow; $row++) {
			$rowdt = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, NULL, TRUE, FALSE);
			$rowdt = $rowdt[0];

			if ($row == 1 || count($headers) < 2) {
				$headers = array();
				foreach ($rowdt as $k => $t) {
					$t = trim(strtolower($t));

					if ($t == 'sample stock concentration') {
						$is_dx11 = true;
						$limit = 0;
					}

					if (isset($match_header[$t])) {
						$headers[$match_header[$t]] = $k;
					} else {
						//check [any]
						$haskey = false;
						foreach ($match_header as $mk => $mt) {
							if (strpos($mk, '[any]') !== false) {
								$mk2 = str_replace('[any]', '', $mk);

								if (stripos($t, $mk2) !== false) {
									$headers[$mt] = $k;
									$haskey = true;
									break;
								}
							}
						}
						if (!$haskey) {
							$headers[$t] = $k;
						}
					}
				}



				if (isset($headers['sample description 2']) && isset($headers['accepted droplets'])) {
					$is_ddPCR = true;
					$limit = 0;
				}

				if (
					!isset($headers['barcode'])
					|| (
						(!isset($headers['drug']) || !isset($headers['conc']))
						&& !isset($headers['vcam1'])
						&& !isset($headers['a1c'])
						&& !isset($headers['mfi-3'])
						&& !isset($headers['rfu'])
						&& !$is_dx11
						&& !$is_ddPCR
					)
				) {
					$headers = array();
				}
			} else {
				//do import here
				if ($row < $meta['imported']) {
					continue;
				}


				$barcode 	= trim($rowdt[$headers['barcode']]);
				$conc 		= trim(isset($rowdt[$headers['conc']]) ? $rowdt[$headers['conc']] : '');
				$batch_id 	= '';
				$instrument = $instruments[$filename];
				$pass_override = false;
				$drug 		= '';

				if ($is_dx11) {
					$drug = '_dx11';
				} else if (isset($headers['drug'])) {
					$drug = strtoupper(trim($rowdt[$headers['drug']]));
				}

				if (isset($headers['vcam1'])) {
					$drug = 'VCAM1';
					$conc = $rowdt[$headers['vcam1']];
				} else if (isset($headers['mfi-3'])) {
					$drug = 'MFI-3';
					$conc = $rowdt[$headers['mfi-3']];
				} else if (isset($headers['a1c'])) {
					$drug = 'HbA1c';
					$conc = str_replace(',', '.', $rowdt[$headers['a1c']]);
				}


				if (strlen($barcode) < 5) {
					continue;
				}

				$otherdata = array();
				if (empty($drug)) {
					foreach ($headers as $mk => $mn) {
						if (!in_array($mk, array('barcode', 'drug', 'conc', 'other'))) {
							$otherdata[$mk] = $rowdt[$mn];
						}
					}
					if (!empty($otherdata)) {
						$drug = 'other';
					}
				}


				if (
					empty($drug) || empty($barcode) || in_array($barcode, $excludebc) || stripos($barcode, ' neg') > 0
					|| stripos($barcode, ' pos') > 0
				) {
					//echo 'empty';
					continue;
				}

				if (!is_array($meta['assen_list'])) {
					$meta['assen_list'] = array();
				}
				if (!in_array($barcode, $meta['assen_list'])) {
					$meta['assen_list'][] = $barcode;
				}

				$report_id = $reports[$barcode];

				if (!isset($reports[$barcode]) || $report_id == 0) {
					$report = $wpdb->get_row("select id,upload_files from {$table_report} where patient_id = '{$barcode}' ", ARRAY_A);
					if ($report && $report['id'] > 0) {
						$report_id = $report['id'];
					}
				}

				if (!empty($check_pass_type)) {

					if ($check_pass_type == 'user') {
						if (isset($_REQUEST['pass_override_all'])) {
							$_SESSION['req_passoverride_all'] = intval($_REQUEST['pass_override_all']);
						}

						$pass_override = $_SESSION['req_passoverride_all'];
					} else {
						if (isset($_REQUEST['pass_override']) && !empty($_REQUEST['barcode'])) {
							$_SESSION['req_passoverride'][$_REQUEST['barcode']] = $_REQUEST['pass_override'];
						}
						$pass_override = isset($_SESSION['req_passoverride'][$barcode]) ? $_SESSION['req_passoverride'][$barcode] : false;
					}


					if ($check_pass_type == 'user' && ($pass_override === 1 || $pass_override === 0)) {
						//dont check again
					} else if (!isset($reports[$barcode])) {

						if ($report_id > 0) {
							//show confirm
							if (!isset($_SESSION['req_passoverride'][$barcode]) && !empty($report['upload_files'])) {
								$hasoldpanel = false;
								$old_upload_files = explode(',', $report['upload_files']);

								foreach ($old_upload_files as $ofi) {
									if ($ofi == $fname) {
										$hasoldpanel = true;
									}
								}

								if ($hasoldpanel && !$only_insert) {
									$_SESSION['files_req_import'] = $files;
									echo $row . '/' . $highestRow . ' rows';
									if ($check_pass_type == 'user') {
										echo '<div class="check_pass_user"></div>';
									} else {
										echo '<div class="check_pass" data-barcode="' . $barcode . '"></div>';
									}

									//var_dump($_SESSION['req_passoverride']);
									exit;
								} else {
									$_SESSION['req_passoverride'][$barcode] = false;
								}
							}
						}
					}

					if ($pass_override === '0' || $pass_override === 0) {
						$files[$filename]['skipped'][$barcode] = 1;
						$_SESSION['files_req_import'] = $files;
						//echo $barcode.'continue';
						continue;
					}
				}




				$batch = $batches[$barcode];

				if (empty($batch)) {
					$batch = $wpdb->get_row("select mid,
					            batch_id,
					            doc,
					            batch_status,
					            batch_created
					 			from {$table} where assen = '{$barcode}' ", ARRAY_A);
					$batches[$barcode] = $batch;
				}

				$batch_id = $batch['batch_id'];

				if (empty($batch_id)) {
					//search in receiving first
					$re_today = $wpdb->get_var("select ri.re_today 
                                        from ci_receiving ri
                                        join ci_receiving_batches rb ON ri.re_ID = rb.re_ID
                                        where rb.btype='req' and rb.mid = '{$batch['mid']}' ");
					if (!empty($re_today)) {
						$batch_id = str_replace('-', '', $re_today);
					} else {
						if (
							empty($batch['doc'])
							|| $batch['doc'] === '0000-00-00'
							|| $batch['doc'] === '0000-00-00 00:00:00'
						) {
							$batch['doc'] = $batch['batch_created'];
						}
						$batch_id = date_i18n('Ymd', strtotime($batch['doc']));
					}

					$wpdb->update($table, array('batch_id' => $batch_id), array('mid' => $batch['mid']));
				}


				if ($report_id == 0) {
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

					if ($drug == 'other' && !empty($otherdata)) {
						$data['other_data'] = json_encode($otherdata);
						$drug = '';
					}
					$wpdb->insert($table_report, $data);

					$report_id = $wpdb->insert_id;

					$files[$filename]['inserted'][$barcode] = 1;
				} else if (!$only_insert && !isset($reports[$barcode])) {

					$old_upload_files = $wpdb->get_var("select upload_files from {$table_report} where id = {$report_id}");
					$old_upload_files = explode(',', $old_upload_files);
					$old_upload_files[] = $filename;
					$old_upload_files = array_unique($old_upload_files);
					$old_upload_files = implode(',', $old_upload_files);
					$data = array(
						'patient_id' => $barcode,
						'batch_id' => $batch_id,
						'lab_id' => CI_Base_Lab::get_current_lab_id(),
						'author_id' => $user_id,
						'instrument' => $instrument,
						'upload_files' => $old_upload_files,
					);
					if ($pass_override === '1') {
						$data['estatus'] = 'M';
					}
					if ($drug == 'other' && !empty($otherdata)) {
						$data['other_data'] = json_encode($otherdata);
						$drug = '';
					}
					$wpdb->update($table_report, $data, array('id' => $report_id));

					$files[$filename]['updated'][$barcode] = 1;
				}

				if ($report_id > 0) {
					$reports[$barcode] = $report_id;
				}

				if ($report_id > 0 && $is_ddPCR) {
					$well = $rowdt[$headers['well']];
					if (stripos($well, 'm') !== 0) continue; //only M
					$msre = strpos($rowdt[$headers['sample description 2']], '+') > 0 ? '+' : '-';
					if (!isset($ddPCR_data[$report_id])) $ddPCR_data[$report_id] = array();
					if (!isset($ddPCR_data[$report_id][$drug])) $ddPCR_data[$report_id][$drug] = array();
					if (!isset($ddPCR_data[$report_id][$drug][$msre])) $ddPCR_data[$report_id][$drug][$msre] = array();
					$ddPCR_data[$report_id][$drug][$msre][] = array(
						'conc' => $conc,
						'accd' => $rowdt[$headers['accepted droplets']],
					);
				} else if ($report_id > 0 && $is_dx11) {
					$dx11_data[$report_id] = $conc;

					$data_result = array(
						'report_id' => $report_id,
						'drug' => '_dx11',
						'conc' => $conc,
						'has_order' => 0
					);

					$exists = $wpdb->get_row("select id
						from {$table_results} 
						where report_id = {$report_id} and drug='_dx11'");

					if ($exists && $exists->id > 0) {
						//log to duplicate
						$rsq = $wpdb->update($table_results, $data_result, array('id' => $exists->id));
						if ($rsq === false) {
							$files[$filename]['error'][$barcode]['_dx11'] = $data_result;
						}
					} else {
						$rsq = $wpdb->insert($table_results, $data_result);
						if ($rsq === false) {
							$files[$filename]['error'][$barcode]['_dx11'] = $data_result;
						}
					}
				} else if ($report_id > 0 && !empty($drug)) {

					$group_id = isset($group_ids[$filename]) ? $group_ids[$filename] : 0;


					$drug_rs = array($drug => array('conc' => $conc));
					if ($drug == 'HbA1c') {
						if (isset($headers['insulin'])) {
							$drug_rs['Insulin'] = array('conc' => str_replace(',', '.', $rowdt[$headers['insulin']]));
						}
						if (isset($headers['c-peptide'])) {
							$drug_rs['C-peptide'] = array('conc' => str_replace(',', '.', $rowdt[$headers['c-peptide']]));
						}
						if (isset($headers['beta core'])) {
							$drug_rs['BETA SCORE'] = array('conc' => str_replace(',', '.', $rowdt[$headers['beta core']]));
						}
					} else if ($drug === 'VCAM1' && isset($headers['psel'])) {
						$drug_rs['PSEL'] = array('conc' => $rowdt[$headers['psel']]);
					} else if ($drug === 'MFI-3') {
						if (isset($headers['mfi-10'])) {
							$drug_rs['MFI-10'] = array('conc' => $rowdt[$headers['mfi-10']]);
						}
						if (isset($headers['preexisting hemolysis'])) {
							$drug_rs['PreExisting Hemolysis'] = array('conc' => $rowdt[$headers['preexisting hemolysis']]);
						}
					}


					foreach ($drug_rs as $drug => $darr) {
						$conc = $darr['conc'];
						$drug = strtoupper($drug);

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

						if (isset($test_abrev2[$drug])) {
							$group_id = 0;
						} else if (isset($test_abrev[$drug]) && !in_array($drug, $all_test_drugs)) {

							$drug = $test_abrev[$drug];
						}


						$data_result = array(
							'report_id' => $report_id,
							'drug' => $drug,
							'conc' => substr($conc, 0, 15),

						);
						if (!empty($instrument)) {
							$data_result['instrument'] = $instrument;
						}

						if (isset($darr['pos-neg'])) {
							$data_result['pos-neg'] = $darr['pos-neg'];
						}

						$exists = $wpdb->get_row("select id,conc,`pos-neg` from {$table_results} where report_id = {$report_id} and drug='{$drug}'");

						if ($exists && $exists->id > 0) {
							//log to duplicate
							$data_result['id'] = $exists->id;
							$data_result['old'] = $exists->conc;
							$data_result['barcode'] = $barcode;
							if (empty($data_result['conc']) && !empty($data_result['pos-neg'])) {
								$data_result['old'] = $exists->{'pos-neg'};
								$data_result['conc'] = $data_result['pos-neg'];
							}
							$files[$filename]['duplicates'][$barcode][$drug] = $data_result;
						} else {
							$rsq = $wpdb->insert($table_results, $data_result);
							if ($rsq === false) {
								$files[$filename]['error'][$barcode][$drug] = $data_result;
							}
						}
					} //end for drugs


					//log user
					if ($batch['mid'] > 0 && !in_array($barcode, $logged_barcode)) {
						trpdd_log_user($user_id, 'uploaded_rs', array('assen' => $barcode, 'file' => basename($filename)), $batch['mid'], 'req', '', '', $barcode);

						$logged_barcode[] = $barcode;
					}
				}



				$count_import++;
				$files[$filename]['imported'] = $row;
				$files[$filename]['assen_list'] = $meta['assen_list'];
				if ($limit > 0 && $count_import > $limit) {
					$_SESSION['files_req_import'] = $files;
					echo $row . '/' . $highestRow . ' rows';
					exit;
				}
			}
		}

		if ($is_ddPCR || $is_dx11) {

			if ($is_ddPCR) {

				foreach ($ddPCR_data as $report_id => $ddata) {
					$barcode = array_search($report_id, $reports);


					$data_result = array(
						'report_id' => $report_id,
						'drug' => '_ddpcr',
						'comments' => json_encode($ddata),
						'has_order' => 0
					);

					$exists = $wpdb->get_row("select id
						from {$table_results} 
						where report_id = {$report_id} and drug='_ddpcr'");

					if ($exists && $exists->id > 0) {
						//log to duplicate
						$rsq = $wpdb->update($table_results, $data_result, array('id' => $exists->id));
						if ($rsq === false) {
							$files[$filename]['error'][$barcode]['_ddpcr'] = $data_result;
						}
					} else {
						$rsq = $wpdb->insert($table_results, $data_result);
						if ($rsq === false) {
							$files[$filename]['error'][$barcode]['_ddpcr'] = $data_result;
						}
					}
				}

				//get dx11
				$report_ids_str = implode(', ', $reports);
				$rsd = $wpdb->get_results("select report_id, conc from {$table_results} 
				where report_id in ({$report_ids_str}) and drug = '_dx11' ");
				foreach ($rsd as $r) {
					$dx11_data[$r->report_id] = $r->conc;
				}
			} else {
				//getddpcr
				$report_ids_str = implode(', ', $reports);
				$ddPCR_data = array();
				$rsd = $wpdb->get_results("select report_id, comments from {$table_results} 
				where report_id in ({$report_ids_str}) and drug = '_ddpcr' ");
				foreach ($rsd as $r) {
					$ddPCR_data[$r->report_id] = json_decode($r->comments, true);
				}
			}

			foreach ($ddPCR_data as $report_id => $ddata) {
				$barcode = array_search($report_id, $reports);

				if (!isset($dx11_data[$report_id]) || empty($dx11_data[$report_id])) {
					Tr_Session_Class::add_error("No DX11 Data for " . $barcode);
					continue;
				}
				$dx11value = $dx11_data[$report_id];

				//calculate
				$scores 	= array();
				$avg 		= array();
				$allconcs 	= array();


				foreach ($ddata['FAM'] as $ens => $arrs) {
					foreach ($arrs as $a) {
						$allconcs['FAM' . $ens][] = $a['conc'];
					}
				}
				foreach ($ddata['HEX'] as $ens => $arrs) {
					foreach ($arrs as $a) {
						$allconcs['HEX' . $ens][] = $a['conc'];
					}
				}

				foreach ($allconcs as $k => $arrs) {
					$avg[$k] = array_sum($arrs) / count($arrs);
				}


				$scores['Insulin+'] = 62.5 * $avg['FAM+'] / $dx11value;
				$scores['Insulin-'] = 62.5 * $avg['FAM-'] / $dx11value;
				$scores['TTC5+'] = 62.5 * $avg['HEX+'] / $dx11value;
				$scores['TTC5-'] = 62.5 * $avg['HEX-'] / $dx11value;

				$scores['%Methylated_Insulin'] = ($scores['Insulin+'] / $scores['TTC5+']) / ($scores['Insulin-'] / $scores['TTC5-']);
				$scores['%UnMethylated_Insulin'] = number_format(100 - $scores['%Methylated_Insulin'] * 100, 2);
				/*
				var_dump($barcode);
				echo '<br><br>dx11:';
				var_dump($dx11value);
				echo '<br><br>';
				echo '$ddata:';
				var_dump($ddata);
				echo '<br><br>';
				echo '$allconcs:';
				var_dump($allconcs);
				echo '<br><br>';
				echo '$scores:';
				var_dump($scores);
				echo '<br><br>';
				echo '<br><br>'; //*/
				//update BETA SCORE

				Tr_Session_Class::add($barcode . ": BETA SCORE: {$scores['%UnMethylated_Insulin']}");

				$exists = $wpdb->get_row("select id
					from {$table_results} 
					where report_id = {$report_id} and drug='BETA SCORE'");

				if ($exists && $exists->id > 0) {
					//log to duplicate
					$rsq = $wpdb->update($table_results, array(
						'conc' => $scores['%UnMethylated_Insulin']
					), array('id' => $exists->id));
				} else {
					$rsq = $wpdb->insert($table_results, array(
						'report_id' => $report_id,
						'drug' => 'BETA SCORE',
						'conc' => $scores['%UnMethylated_Insulin']
					));
				}
			}
		}

		if (!is_array($headers) || count($headers) <= 1) {
			echo '<div class="error">';
			echo $filename . ': Format not correct';
			echo '</div>';
		}

		$files[$filename]['done'] = true;
		$_SESSION['files_req_import'] = $files;


		//update file
		$assen_list = implode(',', $meta['assen_list']);
		$wpdb->update('ci_reports_files', array('rf_assen_list' => $assen_list), array('rf_ID' => $meta['file_id']));
	}


	$duplicates = '';

	foreach ($_SESSION['files_req_import'] as $filename => $meta) {
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

$_SESSION['req_passoverride'] = array();
$_SESSION['req_passoverride_all'] = false;
$_SESSION['files_req_import'] = array();
?>
<div class="wrap">
	<h2 class="center">Upload Raw Data</h2>

	<?php Tr_Session_Class::show() ?>
	<div class="form-wrap center">
		<form enctype="multipart/form-data" method="post">
			<input type="hidden" name="act" value="ci_doctor_process">

			<p class="alert message warning">Please only upload CSV files!</p>

			<?php
			if ($rs_settings['result_import_mf']) {
				$mf_fields = array('i12' => 1, 'j12' => 100, 'k12' => 2, 'l12' => 200, 'm12' => 3, 'n12' => 300);
				$vmax_fields = array('f9' => 'Hb', 'k3' => 'Zero (undiluted)');

				$mf_df = array();
				$vmax_df = array();



			?>
				<label><input type="checkbox" class="import_typecb" name="meta_data[import_type]" value="FA" /> Import FA</label>
				<label><input type="checkbox" class="import_typecb" id="import_type_mf" name="meta_data[import_type]" value="MF" /> Import MF</label>



				<br>
			<?php } ?>


			<div class="frow card_file">
				<a class="button selectfile" href="#">Choose Files</a>
				<span class="view"></span>
				<input type="file" id="upload-files" require="true" name="files[]" accept="text/csv" multiple />
			</div>

			<?php
			if ($rs_settings['result_import_mf']) {
			?>
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
				<script>
					jQuery(function($) {
						$('body').on('change', '#upload-files,#import_type_mf', function() {
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
					})
				</script>
			<?php
			}
			?>

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
		})
	})
</script>