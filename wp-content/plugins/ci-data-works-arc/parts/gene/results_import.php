<?php
global $wpdb;

require TRCPDD_PATH . 'inc/PHPExcel/IOFactory.php';
include_once(TRCPDD_PATH . 'inc/functions_rack_plate.php');

wp_enqueue_script('jquery-ui');
wp_enqueue_script('jquery-ui-dialog');
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');



$gene_cv_file        = tr_get_option('ci_pdf_data', 'gene_cv_file');
$gene_importuti      = tr_get_option('ci_pdf_data', 'gene_importuti');
$settings           = get_option('ci_result_settings_gene', array());

$user_id            = get_current_user_id();
$created_date       = date_i18n('Y-m-d');
$created_datetime   = date_i18n('Y-m-d H:i:s');

$act                = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';

$table              = 'ci_batches_gene';
$table_report       = 'ci_reports_gene';
$table_results      = 'ci_results_gene';


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



$all_instruments = $wpdb->get_col("select instrument from `ci_instruments` where btype='gene' ");
$all_instruments = array_map("strtoupper", $all_instruments);
usort($all_instruments, function ($a, $b) {
	return strlen($b) - strlen($a);
});

if (isset($_GET['debug'])) {
	echo 'instruments:<br>';
	var_dump($all_instruments);
	echo '<br>';
}

$rs         = $wpdb->get_results("select * from ci_test_groups where tg_type = 'gene' ");
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

$options = get_option('ci_result_settings_gene');
if (!isset($options['gene_extend_file']) || empty($options['gene_extend_file'])) {
	$options['gene_extend_file'] = '.csv,.vcf,.rpt,.oa,.cnv';
}


if ($act == 'xygene_get_assens') {

	$options = get_option('tr_xygene_st', array());


	$return = array('status' => 'ok', 'assens' => array(), 'files' => array());


	$where = '';
	if (!empty($options['start_date'])) {
		$where .= " and ba.doc >= '{$options['start_date']}' ";
	}
	$assens = $wpdb->get_results("select ba.assen,
	ba.patient_name,
	mo.mo_sent
	from ci_batches_gene ba 
	LEFT JOIN ci_reports_gene re ON re.patient_id = ba.assen
	LEFT JOIN ci_sync_api_orders mo ON mo.mo_mid = ba.mid and mo.mo_file_id = -59 and mo.mo_btype = 'gene'
	where ba.batch_id > 0 and (re.estatus !='C' or re.estatus is null)
	{$where}
	order by mid desc
	limit 100");
	foreach ($assens as $row) {
		$return['assens'][$row->assen] = array(
			'sent' => $row->mo_sent,
			'patient_name' => $row->patient_name,
			'file' => ''
		);
	}

	wp_send_json($return);
} else if ($act == 'search_xygene_assen') {
	$search = isset($_REQUEST['keyword']) ? trim($_REQUEST['keyword']) : '';
	$return = array('status' => 'ok', 'assens' => array());

	if (strlen($search) >= 3) {
		$like = '%' . $wpdb->esc_like($search) . '%';
		$rows = $wpdb->get_results($wpdb->prepare(
			"select ba.assen,
			        ba.patient_name,
			        mo.mo_sent
			 from ci_batches_gene ba 
			 LEFT JOIN ci_reports_gene re ON re.patient_id = ba.assen
			 LEFT JOIN ci_sync_api_orders mo ON mo.mo_mid = ba.mid and mo.mo_file_id = -59 and mo.mo_btype = 'gene'
			 where ba.assen like %s
			 order by ba.mid desc
			 limit 30",
			$like
		));
		foreach ($rows as $row) {
			$return['assens'][$row->assen] = array(
				'sent'         => $row->mo_sent,
				'patient_name' => $row->patient_name,
			);
		}
	}

	wp_send_json($return);
} else if ($act == 'check_xygene') {
	$return = array();

	$files = json_decode(stripslashes($_POST['files']), true);

	foreach ($files as $file) {
	}

	wp_send_json($return);
} else if ($act == 'get_upload_log') {
	$page    = max(1, intval($_REQUEST['_page'] ?? 1));
	$perpage = intval($_REQUEST['perpage']) ? intval($_REQUEST['perpage']) : 20;
	$search  = isset($_REQUEST['search']) ? trim($_REQUEST['search']) : '';
	$offset  = ($page - 1) * $perpage;

	$where  = "WHERE rf_btype = 'gene'";

	if (!empty($search)) {
		$like = '%' . $wpdb->esc_like($search) . '%';
		$where .= $wpdb->prepare(
			" AND (rf_file LIKE %s OR rf_assen_list LIKE %s)",
			$like,
			$like
		);
	}

	$all_rows = $wpdb->get_results(
		"SELECT rf_ID, rf_file, rf_assen_list, rf_upload_date
     FROM ci_reports_files $where
     ORDER BY rf_upload_date DESC, rf_ID DESC",
		ARRAY_A
	);

	$log_dir_url = str_replace(WP_CONTENT_DIR, WP_CONTENT_URL, $log_dir);

	$assen_map = [];
	foreach ($all_rows as $r) {
		$assens = array_filter(array_map('trim', explode(',', $r['rf_assen_list'] ?? '')));
		if (empty($assens)) continue;

		$sent_date = '';
		$order = $wpdb->get_row($wpdb->prepare(
			"SELECT other_info FROM ci_sync_api_orders 
         WHERE mo_btype = 'gene' AND FIND_IN_SET(%d, mo_test_ID) LIMIT 1",
			$r['rf_ID']
		));
		if ($order && !empty($order->other_info)) {
			$info = json_decode($order->other_info, true);
			if (!empty($info['sent']) && is_array($info['sent'])) {
				foreach ($info['sent'] as $sent) {
					if (!empty($sent['file_ids']) && in_array((string)$r['rf_ID'], array_map('strval', $sent['file_ids']))) {
						$sent_date = $sent['date'];
					}
				}
			}
		}

		foreach ($assens as $assen) {
			if (!isset($assen_map[$assen])) {
				$assen_map[$assen] = [];
			}
			$assen_map[$assen][] = [
				'rf_file'        => $r['rf_file'],
				'rf_url'         => $log_dir_url . $r['rf_file'],
				'rf_upload_date' => $r['rf_upload_date'],
				'sent_date'      => $sent_date,
			];
		}
	}

	$all_assens = array_keys($assen_map);
	$total      = count($all_assens);
	$paged      = array_slice($all_assens, $offset, $perpage);

	$expanded = [];
	foreach ($paged as $assen) {
		$expanded[] = [
			'assen' => $assen,
			'files' => $assen_map[$assen],
		];
	}

	ob_clean();
	wp_send_json([
		'status'      => 'ok',
		'rows'        => $expanded,
		'total'       => $total,
		'page'        => $page,
		'perpage'     => $perpage,
		'total_pages' => max(1, ceil($total / $perpage)),
	]);
	exit;
} else if ($act == 'demo_file') {

	header("Content-type: text/csv");
	header("Content-Disposition: attachment; filename=demo_import.csv");
	header("Pragma: no-cache");
	ob_clean();
	$f = fopen('php://output', 'w');
	$headers = array('Sample', 'Target', 'Result');
	fputcsv($f, $headers);
	$data = array('Test1222333', 'Antigen', 'Positive');
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

	foreach ($_SESSION['files_gene_import'] as $filename => $meta) {

		if (count($meta['duplicates']) > 0) {
			foreach ($meta['duplicates'] as $barcode => $dups) {
				foreach ($dups as $drug => $arr) {
					if (in_array($arr['id'], $ids)) {
						$update = array('conc' => $arr['conc']);
						if (isset($arr['pos-neg']) &&  in_array($arr['pos-neg'], array('POS', 'NEG'))) {
							$update['pos-neg'] = $arr['pos-neg'];
						}
						$rs = $wpdb->update('ci_results_gene', $update, array('id' => $arr['id']));
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

	foreach ($_SESSION['files_gene_import'] as $filename => $meta) {

		if (count($meta['duplicates']) > 0) {
			foreach ($meta['duplicates'] as $barcode => $dups) {
				foreach ($dups as $drug => $arr) {
					if (in_array($arr['id'], $ids)) {
						$update = array('conc' => $arr['conc']);
						if (isset($arr['pos-neg']) &&  in_array($arr['pos-neg'], array('POS', 'NEG'))) {
							$update['pos-neg'] = $arr['pos-neg'];
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


	$xyassens = isset($_POST['xyassens']) && is_array($_POST['xyassens']) ? $_POST['xyassens'] : array();


	if (!empty($xyassens)) {

		if (isset($_POST['plate_location']) && is_array($_POST['plate_location'])) {
			foreach ($_POST['plate_location'] as $ass => $vl) {
				if (in_array($ass, $xyassens)) {
					$mid = $wpdb->get_var("select mid from ci_batches_gene where assen = '{$ass}' ");
					trpdd_update_batch_meta($mid, 'gene', '_plate_location', $vl);
				}
			}
		}
	}

	foreach ($files as $file) {
		$filename 	= $file['name'];
		$filetmp  	= $file['tmp_name'];
		$info 		= pathinfo($filename);
		$ext 		= strtolower($info['extension']);
		$acceptExts = array_map(function ($ext) {
			return str_replace('.', '', trim($ext));
		}, explode(',', $options['gene_extend_file']));
		$defaultExts = ['csv', 'xls', 'xlsx', 'txt', 'rpt', 'vcf'];

		$allExts = array_merge($acceptExts, $defaultExts);
		if (in_array($ext, $allExts)) {
			$filepath = $log_dir . $filename;
			if (file_exists($filepath)) {
				rename($filepath, str_replace('.' . $ext, '_' . time() . '.' . $ext, $filepath));
			}

			if (isset($_GET['debug'])) {
				var_dump($filepath);
			}

			if (move_uploaded_file($filetmp, $filepath)) {
				$_SESSION['files_gene_import'][$filename] = array(
					'imported' => 0,
					'done' => false
				);
				$uploaded++;

				preg_match("/([0-9]{8})/", $filename, $matches);
				$batch_id = isset($matches[1]) ? $matches[1] : '';
				if (empty($batch_id)) {
					preg_match("/Batch ([0-9]+)/", $filename, $matches_lab);
					$batch_id = isset($matches_lab[1]) ? $matches_lab[1] : '';
				}
				if (empty($batch_id)) {
					$batch_id = date_i18n('Ymd');
				}
				if (!empty($batch_id)) {
					$lab_id = CI_Base_Lab::get_current_lab_id();
					$old = $wpdb->get_row("select rf_ID,rf_count,rf_assen_list 
											from ci_reports_files 
		                                    WHERE rf_batch_id ='{$batch_id}' 
		                                    AND rf_file='{$filename}' 
		                                    AND rf_lab_site_id='{$lab_id}'  
		                                    and rf_btype='gene' ");

					if ($old) {
						$file_id = $old->rf_ID;
						$xyassens_filearr = !empty($old->rf_assen_list) ? explode(',', $old->rf_assen_list) : array();
						if (!empty($xyassens)) {
							$xyassens_filearr = array_merge($xyassens_filearr, $xyassens);
							$xyassens_filearr = array_unique($xyassens_filearr);
						}

						$wpdb->update('ci_reports_files', array(
							'rf_count' => $old->rf_count + 1,
							'rf_assen_list' => implode(',', $xyassens_filearr),
							'rf_upload_date' => date_i18n('Y-m-d')
						), array('rf_ID' => $old->rf_ID));
					} else {

						$wpdb->insert('ci_reports_files', array(
							'rf_batch_id' => $batch_id,
							'rf_lab_site_id' => CI_Base_Lab::get_current_lab_id(),
							'rf_file' => $filename,
							'rf_count' => 1,
							'rf_btype' => 'gene',
							'rf_covid_type' => 'xy',
							'rf_assen_list' => implode(',', $xyassens),
							'rf_upload_date' => date_i18n('Y-m-d'),
							'rf_created' => date_i18n('Y-m-d H:i:s')
						));
						$file_id = $wpdb->insert_id;
					}

					$_SESSION['files_gene_import'][$filename]['file_id'] = $file_id;
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
		$linkimport = Tr_Base_Class_V6::link(array('act' => 'doimport'), false);
		if (isset($_REQUEST['only_insert'])) {
			$linkimport .= '&only_insert';
		}
?>
		<div class="wrap">
			<div style="width:900px;margin:30px auto;padding:30px">
				<p>Uploaded File Successfully!</p>
				<?php if (!empty($xyassens)) {
					echo '<div class="success_import"><br><a href="' . Tr_Base_Class_V6::link(array('act' => '', 'trajax' => ''), false) . '">Click here to back</a></div>';
				} else {
					echo '<p id="importstatus">...</p>';
				}
				?>

				<div id="displaydone"></div>
			</div>
		</div>
		<?php if (!empty($xyassens)) {

			//update
			$xyassens_str = implode("','", $xyassens);
			$mids = $wpdb->get_col("select mid from ci_batches_gene where assen in ('{$xyassens_str}') ");
			$mids_str = implode(',', $mids);

			$wpdb->query("update ci_sync_api_orders set mo_sent = 2 
			where mo_mid in ({$mids_str}) 
			and mo_sent = 1 and mo_file_id in (-59,-62) and mo_btype = 'gene'");

			if (empty($settings['gene_import_genotyping'])) {
				return;
			}
		}
		?>

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
		Tr_Session_Class::add_error('No file to import');
	}
} else if ($act == 'ci_gene_doctor_process') {

	$_SESSION['files_gene_import'] = array();
	foreach ($_POST['files'] as $filename) {

		$old = $wpdb->get_row("select *
				from ci_reports_files 
				where rf_file='{$filename}' 
				and rf_btype='gene' ");

		$_SESSION['files_gene_import'][$filename] = array(
			'imported' => 0,
			'done' => false,
			'file_id' => $old ? $old->rf_ID : 0
		);
	}
	$linkimport = Tr_Base_Class_V6::link(array('act' => 'doimport'), false);
	if (isset($_REQUEST['only_insert'])) {
		$linkimport .= '&only_insert';
	}

	?>
	<div class="wrap">
		<div style="width:900px;margin:30px auto;padding:30px">
			<p>Uploaded File Successfully!</p>
			<?php if (!empty($xyassens)) {
				echo '<div class="success_import"><br><a href="' . Tr_Base_Class_V6::link(array('act' => '', 'trajax' => ''), false) . '">Click here to back</a></div>';
			} else {
				echo '<p id="importstatus">...</p>';
			}
			?>

			<div id="displaydone"></div>
		</div>
	</div>
	<script>
		jQuery(function($) {
			function osp_call_import($params) {
				$('#importstatus').html('<b>Importing data. Don\'t turn off page...</b><span class="loading"></span>');
				$.ajax({
					url: '<?php echo $linkimport ?>&trajax=1&' + $params,
					success: function(rs) {
						if (rs.indexOf('success_import') > -1) {
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

		})
	</script>
	<?php
	exit;
} else if ($act == 'doimport') {

	$only_insert = isset($_REQUEST['only_insert']) ? true : false;


	$files = $_SESSION['files_gene_import'];

	$limit = 1500;
	$match_header = array(
		'barcode'       => 'barcode',
		'sample name'   => 'barcode',
		'sample'        => 'barcode',
		'target name'   => 'drug',
		'fluor'         => 'drug',
		'target'        => 'drug',
		'detector'      => 'drug',
		'dye'      		=> 'drug',
		'snp assay' 	=> 'drug',
		'cnv assay' 	=> 'drug',
		'ct'            => 'conc',
		'cq'            => 'conc',
		'cq mean'       => 'conc',
		'crt'           => 'conc',
		'tm'            => 'conc',
		'call'      => 'conc',
		'copy number calculated'      => 'conc',
		'melt temperature' => 'mtemp',
		'mtemp'         => 'mtemp',
		'result'        => 'result',
		'well'        => 'well',
	);

	if ((isset($settings['enable_rs_ct_amp_cq']) && $settings['enable_rs_ct_amp_cq']) || (isset($settings['rs_hml_amp_cq']) && $settings['rs_hml_amp_cq'])) {
		$match_header['cq'] = 'cq';
		$match_header['cq conf'] = 'cq';
		$match_header['amp score'] = 'amp_score';
	}

	if ($gene_importuti) {
		$match_header['name'] = 'barcode';
		$match_header['well'] = 'drugwell';
		$match_header['c(t)'] = 'result';
	}

	if (!tr_get_option('ci_pdf_data', 'gene_importfluor')) {
		unset($match_header['fluor']);
	}

	$reports = array();
	$batches = array();
	$logged_barcode = array();

	$settings_warn = get_option('tr_warn_popup_gene', array());

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


	$tests = $wpdb->get_results("select te_abrev,te_abrev2,te_name from ci_test_panels where te_type='gene' ");
	$test_abrev = array();
	$test_abrev2 = array();
	$all_test_names = array();
	$covid_drug = '';
	foreach ($tests as $t) {
		$t->te_name = strtoupper($t->te_name);
		$t->te_name = trim($t->te_name);
		$t->te_abrev = trim($t->te_abrev);

		if (!empty($t->te_abrev)) {
			$t->te_abrev = strtoupper($t->te_abrev);
			$test_abrev[$t->te_abrev] = $t->te_name;
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

		list($bidtm, $filepanel) = explode('_', $fname, 2);
		$filepanel = substr($filepanel, 0, 4);

		if (empty($instruments[$filename])) {

			foreach ($all_instruments as $mc) {
				if (stripos($fname, $mc) !== false) {
					$instruments[$filename] = substr(strtoupper($mc), 0, 15);
					break;
				}
			}
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
		$headers_abrevs_ct  = array();
		$is_uti_new_tmp     = 0;
		$count_import       = 0;

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

			if ($row == 1 || count($headers) < 2) {
				$headers = array();
				$is_uti_new_tmp = 0;
				foreach ($rowdt as $k => $t) {
					$t = trim(strtolower($t));
					if (isset($match_header[$t]) && !isset($headers[$match_header[$t]])) {
						$headers[$match_header[$t]] = $k;
					}
					if ($gene_importuti && in_array($t, array('sample no', 'assay id', 'c(t)'))) {
						$is_uti_new_tmp++;
					}
				}
				if (
					!isset($headers['barcode'])
					|| (!isset($headers['drug']) && !isset($headers['drugwell']) && !isset($headers['well']))
					|| (!isset($headers['conc']) && !isset($headers['result']))
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

				if (tr_get_option('ci_result_settings_gene', 'result_ins_filec')) {
					if ((stripos($rowdt[0], 'Instrument Name') !== false
							|| stripos($rowdt[0], 'Instrument Serial Number') !== false)
						&& strlen($rowdt[1]) > 3
					) {
						$instruments[$filename] = $rowdt[1];
					}
				}
			} else {
				//do import here

				if ($row < $meta['imported']) {
					continue;
				}


				$barcode    = trim($rowdt[$headers['barcode']]);

				$conc       = trim(isset($rowdt[$headers['conc']]) ? $rowdt[$headers['conc']] : '');
				$batch_id   = '';
				$instrument = isset($instruments[$filename]) ? $instruments[$filename] : '';
				$pass_override = false;
				$drug = '';
				if (isset($headers['mtemp'])) {
					$drug = $IC_covid_tests[0];
				} else if (isset($headers['drug'])) {
					$drug = strtoupper(trim($rowdt[$headers['drug']]));
				}


				if ($is_uti_new_tmp) {
					$drug = 'abrev';
				}
				if (empty($drug) && isset($headers['well'])) {
					$drug = 'well';
				}


				if (strlen($barcode) < 5) {
					continue;
				}

				if (
					empty($drug)
					|| empty($barcode)
					|| in_array($barcode, $excludebc)
					|| stripos($barcode, 'neg') !== false
					|| stripos($barcode, 'pos') !== false
					|| stripos($barcode, 'patient ') === 0
					|| strlen($barcode) < 7
				) {
					//echo 'empty';
					continue;
				}

				if (!isset($meta['assen_list']) || !is_array($meta['assen_list'])) {
					$meta['assen_list'] = array();
				}
				if (!in_array($barcode, $meta['assen_list'])) {
					$meta['assen_list'][] = $barcode;
				}

				$report_id = isset($reports[$barcode]) ? $reports[$barcode] : '';

				if (!isset($reports[$barcode]) || $report_id == 0) {
					$report = $wpdb->get_row("select id,upload_files from {$table_report} where patient_id = '{$barcode}' ", ARRAY_A);
					if ($report && $report['id'] > 0) {
						$report_id = $report['id'];
					}
				}


				if (!empty($check_pass_type)) {

					if ($check_pass_type == 'user') {
						if (isset($_REQUEST['pass_override_all'])) {
							$_SESSION['gene_passoverride_all'] = intval($_REQUEST['pass_override_all']);
						}

						$pass_override = $_SESSION['gene_passoverride_all'];
					} else {
						if (isset($_REQUEST['pass_override']) && !empty($_REQUEST['barcode'])) {
							$_SESSION['gene_passoverride'][$_REQUEST['barcode']] = $_REQUEST['pass_override'];
						}
						$pass_override = isset($_SESSION['gene_passoverride'][$barcode]) ? $_SESSION['gene_passoverride'][$barcode] : false;
					}


					if ($check_pass_type == 'user' && ($pass_override === 1 || $pass_override === 0)) {
						//dont check again
					} else if (!isset($reports[$barcode])) {

						if ($report_id > 0) {
							//show confirm
							if (!isset($_SESSION['gene_passoverride'][$barcode]) && !empty($report['upload_files'])) {
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
									$_SESSION['files_gene_import'] = $files;
									echo $row . '/' . $highestRow . ' rows';
									if ($check_pass_type == 'user') {
										echo '<div class="check_pass_user"></div>';
									} else {
										echo '<div class="check_pass" data-barcode="' . $barcode . '"></div>';
									}

									//var_dump($_SESSION['gene_passoverride']);
									exit;
								} else {
									$_SESSION['gene_passoverride'][$barcode] = false;
								}
							}
						}
					}

					if ($pass_override === '0' || $pass_override === 0) {
						$files[$filename]['skipped'][$barcode] = 1;
						$_SESSION['files_gene_import'] = $files;
						//echo $barcode.'continue';
						continue;
					}
				}


				$batch = isset($batches[$barcode]) ? $batches[$barcode] : array();

				if (empty($batch)) {
					$batch = $wpdb->get_row("select mid,
					            batch_id,
					            doc,
					            batch_status,
					            batch_created
					 			from {$table} where assen = '{$barcode}' ", ARRAY_A);
					$batches[$barcode] = $batch;
				}

				$batch_id = isset($batch['batch_id']) ? $batch['batch_id'] : '';

				if (empty($batch_id) && isset($batch['mid']) && $batch['mid'] > 0) {
					//search in receiving first
					$re_today = $wpdb->get_var("select ri.re_today 
                                        from ci_receiving ri
                                        join ci_receiving_batches rb ON ri.re_ID = rb.re_ID
                                        where rb.btype='gene' and rb.mid = '{$batch['mid']}' ");
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

					$old_upload_files = $wpdb->get_var("select upload_files from {$table_report} where id = '{$report_id}' ");
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
					$wpdb->update($table_report, $data, array('id' => $report_id));
				}

				if ($report_id > 0) {

					$reports[$barcode] = $report_id;

					$group_id = isset($group_ids[$filename]) ? $group_ids[$filename] : 0;

					if ($drug == 'COVID-19' && tr_get_option('ci_pdf_data', 'genecovid_covidnot19')) {
						$drug = 'COVID';
					} else if ($drug == 'COVID' && !tr_get_option('ci_pdf_data', 'genecovid_covidnot19')) {
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
					} else if ($genecovid_in1crpic) {
						if ($drug == 'N1') {
							$drug = 'COVID-19';
						} else if ($drug == 'RP') {
							$drug = 'IC';
						}
					}



					if ($drug == 'well') {
						$drug = trppd_get_drug_by_well($barcode, strtoupper(trim($rowdt[$headers['well']])), 'gene');
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
						if ($settings['enable_rs_ct_amp_cq']) {
							$drug_rs[$drug]['cq'] = substr($rowdt[$headers['cq']], 0, 8);
							$drug_rs[$drug]['amp_score'] = substr($rowdt[$headers['amp_score']], 0, 8);
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

							if ($gene_cv_file && !empty($covid_drug) && isset($headers['result'])) {
								$pnr = $rowdt[$headers['result']];

								if (stripos($pnr, 'NEG') !== false) {
									$drug_rs[$covid_drug] = array('conc' => '', 'pos-neg' => 'NEG');
								} else if (stripos($pnr, 'POS') !== false) {
									$drug_rs[$covid_drug] = array('conc' => '', 'pos-neg' => 'POS');
								}
							}
						}
					}

					foreach ($drug_rs as $drug => $darr) {
						$conc = $darr['conc'];
						$drug = trim($drug);
						$drug = strtoupper($drug);
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
						}



						if (empty($instrument) || stripos($instrument, 'studio1') !== false) {
							if ($drug == 'PRC') {
								$drug = 'IC';
							} else if ($drug == 'SARS-COV-2' && !$drug_by_abrev) {
								$drug = 'COVID-19';
							}

							if ($conc == 'Undetermined') {
								$conc = 'NaN';
							}
						}



						$data_result = array(
							'report_id' => $report_id,
							'drug' => $drug,
							'conc' => substr($conc, 0, 15),
							'instrument' => $instrument
						);
						if ($settings['enable_rs_ct_amp_cq']) {
							$data_result['cq'] = substr($darr['cq'], 0, 15);
							$data_result['amp_score'] = substr($darr['amp_score'], 0, 15);
						}

						if (isset($darr['pos-neg'])) {
							$data_result['pos-neg'] = $darr['pos-neg'];
						}

						$exists = $wpdb->get_row("select id,conc,`pos-neg` from {$table_results} 
						where report_id = {$report_id} and drug='{$drug}'");



						if (isset($result2times[$report_id][$drug]) && $exists) {
							//check second
							$drug2 = trim($drug . '-2');

							$data_result2 = $data_result;
							$data_result2['drug'] = $drug2;

							$exists2 = $wpdb->get_row("select id,conc,`pos-neg` from {$table_results} 
							where report_id = {$report_id} and drug='{$drug2}'");

							if ($exists2 && $exists2->id > 0) {
								$rsq = $wpdb->update($table_results, $data_result2, array('id' => $exists2->id));
							} else {
								$rsq = $wpdb->insert($table_results, $data_result2);
							}
							$limit = 0;
						} else if ($exists && $exists->id > 0) {
							//log to duplicate
							$data_result['id'] = $exists->id;
							$data_result['old'] = $exists->conc;
							$data_result['old-pos-neg'] = $exists->{'pos-neg'};
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
							} else {
								$files[$filename]['inserted'][$barcode] = 1;
							}
						}
						if (!isset($result2times[$report_id])) {
							$result2times[$report_id] = array();
						}
						$result2times[$report_id][$drug] = $data_result['conc'];
					} //end for drugs


					//log user
					if (isset($batch['mid']) && $batch['mid'] > 0 && !in_array($barcode, $logged_barcode)) {
						trpdd_log_user($user_id, 'uploaded_rs', array('assen' => $barcode, 'file' => basename($filename)), $batch['mid'], 'gene', '', '', $barcode);

						$logged_barcode[] = $barcode;
					}
				}



				$count_import++;
				$files[$filename]['imported'] = $row;
				$files[$filename]['assen_list'] = $meta['assen_list'];
				if ($limit > 0 && $count_import > $limit) {
					$_SESSION['files_gene_import'] = $files;
					echo $row . '/' . $highestRow . ' rows';
					exit;
				}
			}
		}

		if (
			!isset($headers['barcode'])
			|| (!isset($headers['drug'])  && !isset($headers['mtemp']))
			|| (!isset($headers['conc']) && !isset($headers['result']))
		) {
			echo $filename . ': Format not correct';
		}

		$files[$filename]['done'] = true;
		$_SESSION['files_gene_import'] = $files;


		//update file
		$assen_list = implode(',', $meta['assen_list']);
		$updatedata = array(
			'rf_assen_list' => $assen_list,
			'rf_imported' => 1
		);
		if (!empty($covid_file_type)) {
			$updatedata['rf_covid_type'] = substr($covid_file_type, 0, 7);
		}
		$wpdb->update('ci_reports_files', $updatedata, array('rf_ID' => $meta['file_id']));
	}



	$duplicates = '';

	foreach ($_SESSION['files_gene_import'] as $filename => $meta) {
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

					$oldvl = $arr['old'];
					$newvl = $arr['conc'];
					if (empty($newvl) && !empty($arr['pos-neg'])) {
						$newvl = $arr['pos-neg'];
						$oldvl = $arr['old-pos-neg'];
					}

					$duplicates .= '<tr class="tbrow ' . $class . '" data-id="' . $arr['id'] . '" data-report_id="' . $arr['report_id'] . '">    
                    <td class="barcode">' . $arr['barcode'] . '</td>
                    <td class="drug">' . $arr['drug'] . '</td>
                    <td class="old">' . $oldvl . '</td>
                    <td class="new">' . $newvl . '</td>
                    <td class="cb"><input type="checkbox" value="' . $arr['id'] . '"/></td>
                    <td><a class="imp_approve button">Approve</a>  <a class="imp_deny button">Deny</a></td>
                    </tr>';
					$count_dup++;
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

					$duplicates .= '<tr class="tbrow" data-id="' . $covidids . '">    
                    <td class="barcode">' . $arr['barcode'] . '</td>
                    <td class="drug">' . $drugs . '</td>
                    <td class="old">' . $olds . '</td>
                    <td class="new">' . $news . '</td>
                    <td class="cb"><input type="checkbox" value="' . $covidids . '"/></td>
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

	if (isset($settings['gene_rs_folder']) && !empty($settings['gene_rs_folder'])) {
		$nextcron = wp_next_scheduled('cron_all_api', array('CI_Gene_Move_RS'));
		if (empty($nextcron)) {
			wp_schedule_single_event(time() + 20, 'cron_all_api', array('CI_Gene_Move_RS'));
		}
	}
	if (isset($_REQUEST['trajax'])) {

		Tr_Session_Class::show();
		if ($error_no == 0) {


			echo '<div class="success_import">Imported Successfully! <br><a href="' . Tr_Base_Class_V6::link(array('act' => '', 'trajax' => ''), false) . '">Click here to back</a></div>';
		} else {
			echo '<div class="success_import"><a href="' . Tr_Base_Class_V6::link(array('act' => '', 'trajax' => ''), false) . '">Click here to back</a></div>';
		}



		exit;
	}
}

$_SESSION['gene_passoverride'] = array();
$_SESSION['gene_passoverride_all'] = false;
$_SESSION['files_gene_import'] = array();
$extra_exts = isset($options['gene_extend_file']) ? trim($options['gene_extend_file']) : '';

$extra_exts_clean = array_filter(array_map(function ($ext) {
	return '.' . ltrim(trim($ext), '.');
}, explode(',', $extra_exts)));

$accepts = [];

if (!empty($extra_exts_clean)) {
	$accepts = array_merge($accepts, $extra_exts_clean);
	$accepts = array_unique($accepts);
}

$accept_attr = implode(',', $accepts);

$message_exts = strtoupper(implode(' ', array_map(function ($ext) {
	return ltrim($ext, '.');
}, $accepts)));

?>
<div class="wrap">
	<h2 class="center">Upload Raw Data</h2>

	<?php Tr_Session_Class::show() ?>
	<div class="form-wrap center">
		<form enctype="multipart/form-data" method="post">
			<input type="hidden" name="act" value="ci_doctor_process">

			<p class="alert message warning">Please only upload CSV <?php echo $message_exts ?> files!</p>


			<div class="frow card_file">
				<a class="button selectfile" href="#">Choose Files</a>
				<span class="view"></span>
				<input type="file" id="upload-files" name="files[]" accept=".csv<?php if (!empty($accept_attr)) echo ',' . esc_attr($accept_attr) ?>" multiple required />
			</div>

			<div id="listassens" style="margin-bottom:10px"></div>


			<div class="frow">
				<input type="submit" id="uploadbtn" value="Upload" class="button-primary" />
				&nbsp;
				<?php if (tr_get_option('ci_pdf_data', 'api_genetica_json')) { ?>
					<button type="button" id="btn-upload-log" class="button">
						Logs
					</button>
				<?php } ?>
			</div>

			<br>


			<label>
				<input type="checkbox" name="only_insert" value="1" /> Only Insert New</label>
			<p><em>To upload multiple files, hold down SHIFT while clicking to select a group or CTRL to
					select multiple files individually.</em></p>

			<?php if (isset($_GET['debug'])) { ?>
				<a href="<?php Tr_Base_Class_V6::link(array('act' => 'demo_file', 'trajax' => 1, 'debug' => '')) ?>">Demo file</a>
			<?php } ?>
		</form>
	</div>
</div>
<div id="upload-log-modal" style="display:none;">
	<div id="upload-log-overlay"></div>
	<div id="upload-log-dialog">
		<div id="upload-log-header">
			<h3>📋 Upload Log</h3>
			<button type="button" id="upload-log-close">&times;</button>
		</div>
		<div id="upload-log-search-wrap">
			<input type="text" id="upload-log-search" placeholder="🔍 Search filename, batch, accession..." />
		</div>
		<div id="upload-log-body">
			<div class="ullog-loading">Loading...</div>
		</div>
	</div>
</div>

<style>
	#upload-log-overlay {
		position: fixed;
		inset: 0;
		background: rgba(0, 0, 0, .55);
		z-index: 99998;
	}

	#upload-log-dialog {
		position: fixed;
		top: 50%;
		left: 50%;
		transform: translate(-50%, -50%);
		z-index: 99999;
		background: #fff;
		border-radius: 8px;
		box-shadow: 0 8px 40px rgba(0, 0, 0, .28);
		width: 92%;
		max-width: 860px;
		max-height: 82vh;
		display: flex;
		flex-direction: column;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
	}

	#upload-log-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 14px 20px 10px;
		border-bottom: 1px solid #e5e7eb;
	}

	#upload-log-header h3 {
		margin: 0;
		font-size: 16px;
		font-weight: 600;
		color: #1e293b;
	}

	#upload-log-close {
		background: none;
		border: none;
		font-size: 22px;
		line-height: 1;
		cursor: pointer;
		color: #64748b;
		padding: 0 4px;
	}

	#upload-log-close:hover {
		color: #0f172a;
	}

	#upload-log-search-wrap {
		padding: 10px 20px 8px;
		border-bottom: 1px solid #f1f5f9;
	}

	#upload-log-search {
		width: 100%;
		box-sizing: border-box;
		padding: 7px 12px;
		border: 1px solid #cbd5e1;
		border-radius: 6px;
		font-size: 13px;
		outline: none;
	}

	#upload-log-search:focus {
		border-color: #3b82f6;
		box-shadow: 0 0 0 3px rgba(59, 130, 246, .15);
	}

	#upload-log-body {
		overflow-y: auto;
		padding: 0 20px 16px;
		flex: 1;
	}

	.ullog-loading {
		text-align: center;
		padding: 30px;
		color: #94a3b8;
		font-size: 14px;
	}

	table.ullog-tbl {
		width: 100%;
		border-collapse: collapse;
		font-size: 13px;
		margin-top: 12px;
	}

	table.ullog-tbl thead th {
		position: sticky;
		top: 0;
		background: #f8fafc;
		color: #475569;
		font-weight: 600;
		text-align: left;
		padding: 8px 10px;
		border-bottom: 2px solid #e2e8f0;
		white-space: nowrap;
	}

	table.ullog-tbl tbody tr:hover {
		background: #f0f9ff;
	}

	table.ullog-tbl td {
		padding: 7px 10px;
		border-bottom: 1px solid #f1f5f9;
		vertical-align: top;
		color: #334155;
	}

	table.ullog-tbl td.ullog-file a {
		color: #2563eb;
		text-decoration: none;
		font-weight: 500;
		word-break: break-all;
	}

	table.ullog-tbl td.ullog-file a:hover {
		text-decoration: underline;
	}

	table.ullog-tbl td.ullog-file span.nolink {
		color: #334155;
		word-break: break-all;
	}

	td.ullog-date {
		white-space: nowrap;
		color: #64748b;
	}

	td.ullog-batch {
		font-family: monospace;
		color: #0f766e;
	}

	td.ullog-count {
		text-align: center;
		color: #7c3aed;
		font-weight: 600;
	}

	.ullog-assen {
		font-size: 11px;
		margin-top: 3px;
		line-height: 1.5;
	}

	.ullog-empty {
		text-align: center;
		padding: 28px;
		color: #94a3b8;
		font-size: 13px;
	}

	.ullog-pager {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 12px 4px 4px;
		flex-wrap: wrap;
		gap: 8px;
	}

	.ullog-pager-info {
		font-size: 12px;
		color: #94a3b8;
	}

	.ullog-pager-btns {
		display: flex;
		align-items: center;
		gap: 3px;
		flex-wrap: wrap;
	}

	.ullog-pg-btn {
		min-width: 30px;
		height: 30px;
		padding: 0 6px;
		border: 1px solid #e2e8f0;
		background: #fff;
		border-radius: 5px;
		font-size: 13px;
		cursor: pointer;
		color: #475569;
		transition: background .15s, color .15s, border-color .15s;
		line-height: 28px;
	}

	.ullog-pg-btn:hover:not(:disabled):not(.active) {
		background: #f0f9ff;
		border-color: #93c5fd;
		color: #1d4ed8;
	}

	.ullog-pg-btn.active {
		background: #2563eb;
		border-color: #2563eb;
		color: #fff;
		font-weight: 600;
		cursor: default;
	}

	.ullog-pg-btn:disabled {
		opacity: .35;
		cursor: not-allowed;
	}

	.ullog-pg-ellipsis {
		font-size: 13px;
		color: #94a3b8;
		padding: 0 2px;
		line-height: 30px;
	}

	.ullog-perpage {
		height: 30px;
		padding: 0 6px;
		border: 1px solid #e2e8f0;
		border-radius: 5px;
		font-size: 12px;
		color: #475569;
		background: #fff;
		cursor: pointer;
		margin-left: 6px;
	}

	.ullog-rownum {
		width: 36px;
		text-align: center;
		color: #cbd5e1;
		font-size: 11px;
	}

	.ullog-file-row {
		padding: 2px 0;
		border-bottom: 1px dashed #f1f5f9;
		line-height: 1.6;
	}

	.ullog-file-row:last-child {
		border-bottom: none;
	}

	.ullog-file a,
	.ullog-file .nolink {
		display: block;
		max-width: 200px;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		cursor: pointer;
	}
</style>

<script>
	jQuery(function($) {
		var state = {
			page: 1,
			perpage: 50,
			search: '',
			loading: false
		};

		function openLogModal() {
			$('#upload-log-modal').show();
			state.page = 1;
			state.search = '';
			$('#upload-log-search').val('');
			loadLog();
		}

		function closeLogModal() {
			$('#upload-log-modal').hide();
		}

		function loadLog() {
			if (state.loading) return;
			state.loading = true;
			$('#upload-log-body').html('<div class="ullog-loading">Loading...</div>');

			$.ajax({
				data: {
					act: 'get_upload_log',
					trajax: 1,
					_page: state.page,
					perpage: state.perpage,
					search: state.search
				},
				type: 'GET',
				dataType: 'json',
				success: function(rs) {
					state.loading = false;
					if (rs.status === 'ok') {
						renderLog(rs);
					} else {
						$('#upload-log-body').html('<div class="ullog-empty">Could not load log.</div>');
					}
				},
				error: function() {
					state.loading = false;
					$('#upload-log-body').html('<div class="ullog-empty">Error loading. Please try again.</div>');
				}
			});
		}

		function renderLog(rs) {
			var rows = rs.rows;
			var total = rs.total;
			var page = rs.page;
			var perpage = rs.perpage;
			var totalPages = rs.total_pages;
			var from = (page - 1) * perpage + 1;
			var to = Math.min(from + perpage, total);

			if (rows.length === 0) {
				$('#upload-log-body').html('<div class="ullog-empty">No files found.</div>');
				return;
			}

			var html = '<table class="ullog-tbl"><thead><tr>' +
				'<th>#</th>' +
				'<th>Accession</th>' +
				'<th>Filename</th>' +
				'<th>Upload Date</th>' +
				'<th>Sent Date/Time</th>' +
				'</tr></thead><tbody>';

			rows.forEach(function(r, i) {
				var fileLinks = r.files.map(function(f) {
					var link = f.rf_url ?
						'<a href="' + f.rf_url + '" target="_blank" title="' + escHtml(f.rf_file) + '">' + escHtml(f.rf_file) + '</a>' :
						'<span class="nolink" title="' + escHtml(f.rf_file) + '">' + escHtml(f.rf_file) + '</span>';
					return '<div class="ullog-file-row">' + link + '</div>';
				}).join('');

				var uploadDates = r.files.map(function(f) {
					return '<div class="ullog-file-row">' + escHtml(f.rf_upload_date || '—') + '</div>';
				}).join('');

				var sentDates = r.files.map(function(f) {
					return '<div class="ullog-file-row">' + escHtml(f.sent_date || '—') + '</div>';
				}).join('');

				html += '<tr>' +
					'<td class="ullog-rownum">' + (from + i) + '</td>' +
					'<td class="ullog-assen-cell">' + escHtml(r.assen) + '</td>' +
					'<td class="ullog-file">' + fileLinks + '</td>' +
					'<td class="ullog-date">' + uploadDates + '</td>' +
					'<td class="ullog-date">' + sentDates + '</td>' +
					'</tr>';
			});

			html += '</tbody></table>';
			html += buildPager(page, totalPages, total, from, to, perpage);

			$('#upload-log-body').html(html);
		}

		function buildPager(page, totalPages, total, from, to, perpage) {
			var html = '<div class="ullog-pager">';
			html += '<span class="ullog-pager-info">Showing ' + from + '–' + to + ' of ' + total + ' files</span>';
			html += '<div class="ullog-pager-btns">';

			html += '<button class="ullog-pg-btn" data-page="1" ' + (page <= 1 ? 'disabled' : '') + '>«</button>';
			html += '<button class="ullog-pg-btn" data-page="' + (page - 1) + '" ' + (page <= 1 ? 'disabled' : '') + '>‹</button>';

			var wSize = 5,
				half = Math.floor(wSize / 2);
			var pStart = Math.max(1, page - half);
			var pEnd = Math.min(totalPages, pStart + wSize - 1);
			if (pEnd - pStart < wSize - 1) pStart = Math.max(1, pEnd - wSize + 1);

			if (pStart > 1) html += '<span class="ullog-pg-ellipsis">…</span>';
			for (var p = pStart; p <= pEnd; p++) {
				html += '<button class="ullog-pg-btn' + (p === page ? ' active' : '') + '" data-page="' + p + '">' + p + '</button>';
			}
			if (pEnd < totalPages) html += '<span class="ullog-pg-ellipsis">…</span>';

			html += '<button class="ullog-pg-btn" data-page="' + (page + 1) + '" ' + (page >= totalPages ? 'disabled' : '') + '>›</button>';
			html += '<button class="ullog-pg-btn" data-page="' + totalPages + '" ' + (page >= totalPages ? 'disabled' : '') + '>»</button>';
			html += '</div></div>';
			return html;
		}

		function escHtml(str) {
			return String(str)
				.replace(/&/g, '&amp;').replace(/</g, '&lt;')
				.replace(/>/g, '&gt;').replace(/"/g, '&quot;');
		}

		$('#btn-upload-log').on('click', function(e) {
			e.preventDefault();
			openLogModal();
		});

		$('#upload-log-close, #upload-log-overlay').on('click', closeLogModal);

		$(document).on('keydown', function(e) {
			if (e.key === 'Escape') closeLogModal();
		});

		var searchTimer;
		$('#upload-log-search').on('input', function() {
			var val = $(this).val();
			clearTimeout(searchTimer);
			searchTimer = setTimeout(function() {
				state.search = val;
				state.page = 1;
				loadLog();
			}, 400);
		});

		$(document).on('click', '.ullog-pg-btn', function() {
			var pg = parseInt($(this).data('page'));
			if (!isNaN(pg) && pg !== state.page) {
				state.page = pg;
				loadLog();
				$('#upload-log-body').scrollTop(0);
			}
		});

		$(document).on('change', '.ullog-perpage', function() {
			state.perpage = parseInt($(this).val());
			state.page = 1;
			loadLog();
			$('#upload-log-body').scrollTop(0);
		});
	});
</script>
<?php if (
	tr_get_option('ci_pdf_data', 'api_xygene')
	|| tr_get_option('ci_pdf_data', 'api_genxys')
	|| tr_get_option('ci_pdf_data', 'api_genetica_json')
) { ?>
	<style>
		.xysent {
			opacity: 0.4;
		}

		table.moredata th,
		table.moredata td {
			text-align: left;
		}

		table.moredata td input[type="text"] {
			max-width: 200px;
		}
	</style>
	<script>
		jQuery(function($) {
			const gene_extend_files = <?php echo json_encode($options['gene_extend_file'] ?? ''); ?>;
			const accept_extends = (gene_extend_files ?? '').split(',');
			const has_plate_location = <?php echo tr_get_option('ci_pdf_data', 'api_xygene') || tr_get_option('ci_pdf_data', 'api_genxys') ? 'true' : 'false'; ?>;
			$('body').on('submit', 'form:has(#uploadbtn)', function() {
				$('#uploadbtn').prop('disabled', true).val('Uploading...');
			});
			$('body').on('change', '#upload-files', function() {
				const files = this.files; // Get the FileList object
				let hasxygene = false;
				$newfiles = [];
				if (files.length > 0) {

					for (let i = 0; i < files.length; i++) {
						$newfiles.push({
							name: files[i].name,
							size: files[i].size,
							type: files[i].type
						});
						if (files[i].name.indexOf('PGX') > 0 || files[i].name.indexOf('.vcf') > 0) {
							hasxygene = true;
						}
						if (accept_extends.some(ext => files[i].name.includes(ext.trim()))) {
							hasxygene = true;
						}
					}
				}
				if (hasxygene) {
					$('#uploadbtn').hide();
					$('#listassens').html('<div class="loading"></div>');
					$.ajax({
						type: 'post',
						dataType: 'json',
						data: 'files=' + JSON.stringify($newfiles) + '&act=xygene_get_assens&trajax=1',
						success: function(rs) {
							let tbl = '<table class="moredata" style="width:600px;margin:0 auto"><tr><th style="width:130px">Accession</th><th style="width:200px">Patient Name</th>';
							if (has_plate_location) {
								tbl += '<th class="plate_location">Plate Location</th>';
							}
							tbl += '</tr>';
							$('#listassens').html('');
							for (i in rs.assens) {
								let sentclass = rs.assens[i].sent ? 'xysent' : '';
								tbl += '<tr class="' + sentclass + '"><td><label><input type="checkbox" class="xyassens" name="xyassens[]" value="' + i + '"/> ' + i + '</label></td>';
								tbl += '<td>' + rs.assens[i].patient_name + '</td>';
								if (has_plate_location) {
									tbl += '<td class="plate_location"><input type="text" name="plate_location[' + i + ']"/></td>';
								}
								tbl += '</tr>';
							}
							tbl += '</table>';
							tbl += '<div id="xyassen-search-wrap" style="margin-top:10px;text-align:center">' +
								'<div style="display:none"><input type="text" id="xyassen-search-input" placeholder="Typing accession..." style="width:220px"/> ' +
								'<button type="button" id="xyassen-search-btn" class="button">Search &amp; Add</button></div>' +
								'</div>';
							$('#listassens').html(tbl);
							for (i in rs.files) {
								if (rs.files[i]) {
									//alert(i + ' : you uploaded before!');
								}
							}

						},
						error: function() {
							setTimeout(function() {
								$('#upload-files').change();
							}, 1000);
						},
						complete: function() {
							$('#uploadbtn').prop('disabled', false).val('Send Samples');
						}

					});

				} else {
					$('#listassens').html('');
				}
			}).on('change', '.xyassens', function() {
				if ($('.xyassens:checked').length > 0) {
					$('#uploadbtn').show();
				} else {
					$('#uploadbtn').hide();
				}
			}).on('click', '#xyassen-search-btn', function() {
				doXyassenSearch();
			}).on('keypress', '#xyassen-search-input', function(e) {
				if (e.which === 13) {
					e.preventDefault();
					doXyassenSearch();
				}
			});

			function doXyassenSearch() {
				var keyword = $.trim($('#xyassen-search-input').val());
				if (keyword.length < 3) {
					alert('Please enter at least 3 characters to search!');
					return;
				}
				var $btn = $('#xyassen-search-btn');
				$btn.prop('disabled', true).text('Searching...');
				$.ajax({
					type: 'post',
					dataType: 'json',
					data: {
						act: 'search_xygene_assen',
						trajax: 1,
						keyword: keyword
					},
					success: function(rs) {
						$btn.prop('disabled', false).text('Search & Add');
						if (rs.status !== 'ok') {
							alert('An error occurred, please try again!');
							return;
						}
						var found = 0;
						for (var assen in rs.assens) {
							if (!rs.assens.hasOwnProperty(assen)) continue;
							found++;
							addAssenRow(assen, rs.assens[assen]);
						}
						if (found === 0) {
							alert('No matching accession found!');
						}
						$('#xyassen-search-input').val('');
					},
					error: function() {
						$btn.prop('disabled', false).text('Search & Add');
						alert('An error occurred, please try again!');
					}
				});
			}

			function addAssenRow(assen, info) {
				var $table = $('#listassens table.moredata');
				if ($table.length === 0) return;

				var $existing = $table.find('input.xyassens[value="' + assen + '"]');
				if ($existing.length > 0) {
					$existing.prop('checked', true).trigger('change');
					$existing.closest('tr').css('background', '#fffbcc');
					return;
				}

				var sentclass = info.sent ? 'xysent' : '';
				var row = '<tr class="' + sentclass + '"><td><label><input type="checkbox" class="xyassens" name="xyassens[]" value="' + assen + '" /> ' + assen + '</label></td>';
				row += '<td>' + (info.patient_name || '') + '</td>';
				if (has_plate_location) {
					row += '<td class="plate_location"><input type="text" name="plate_location[' + assen + ']"/></td>';
				}
				row += '</tr>';
				$table.append(row);
			}
		})
	</script>
<?php } ?>