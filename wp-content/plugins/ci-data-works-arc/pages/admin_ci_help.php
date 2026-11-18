<?php
global $wpdb;

$lab_id = CI_Base_Lab::get_current_lab_id();


include_once(TRCPDD_PATH . 'inc/functions_fix.php');
include_once(TRCPDD_PATH . 'pages/formdata.php');
$install_folders = trpdd_get_install_folders();
$load_folder_info = isset($_GET['loadfolders']);
//check db version
$db_version = get_option('TRCPDD_VS');
if ($db_version != TRCPDD_VS) {
	trpdd_plugin_activate();
	include(TRCPDD_PATH . 'inc/install_roles.php');
}

if (isset($_REQUEST['trajax']) && !isset($_REQUEST['act'])) {
	$_REQUEST['act'] = $_REQUEST['trajax'];
}
if (isset($_REQUEST['act']) && !empty($_REQUEST['act'])) {
	$scfile = trpdd_get_file_path('scripts', $_REQUEST['act']);
	if (file_exists($scfile)) {
		include($scfile);
		return;
	}
}

if (isset($_POST['create_install_folders'])) {
	check_admin_referer('ci_create_install_folders');
	$created_folders = array();
	$failed_folders = array();

	foreach ($install_folders as $folder) {
		$dir = $folder['path'];
		if (is_dir($dir)) {
			continue;
		}

		if (file_exists($dir) && !is_dir($dir)) {
			$failed_folders[] = $dir;
		} else if (wp_mkdir_p($dir)) {
			$created_folders[] = $dir;
		} else {
			$failed_folders[] = $dir;
		}
	}

	if (count($created_folders) > 0) {
		Tr_Session_Class::add('Created folders: ' . implode(', ', $created_folders));
	}
	if (count($failed_folders) > 0) {
		Tr_Session_Class::add_error('Can not create folders: ' . implode(', ', $failed_folders));
		Tr_Session_Class::add_error('chmod -R 00775 ' . WP_CONTENT_DIR . '  may help to fix the issue.');
		exec('chmod -R 00775 ' . WP_CONTENT_DIR);
	}
	if (count($created_folders) == 0 && count($failed_folders) == 0) {
		Tr_Session_Class::add('All install folders already exist.');
	}
}

if (isset($_REQUEST['clear_abx_cache'])) {
	$cachedir 		= WP_CONTENT_DIR . '/results/html/';
	$files = scandir($cachedir);
	echo 'clear abx cache:<br>';

	foreach ($files as $file) {
		if (stripos($file, 'pcr_3.htm') !== false) {
			$filepath = $cachedir . $file;
			unlink($filepath);
			echo $file . '<br>';
		}
	}

	exit;
}

if (isset($_GET['fix_qc'])) {
	include(trpdd_get_file_path('scripts', 'fix_qc'));
	echo 'ok';
	exit;
}

if (isset($_GET['fix_empty_assen'])) {
	foreach (tr_pdd_get_batch_types() as $btype) {
		$batch_tbl = tr_pdd_get_table_by_btype($btype);
		$rs = $wpdb->get_results("select * from {$batch_tbl} where (assen = '' or assen is null) and is_saved = 0");
		foreach ($rs as $r) {
			$sql = "select data from ci_log_forms where 
				form = 'save_{$btype}' 
				and data like '%assen\":\"%'
				and data like '%mid\":{$r->mid}%' ";
			echo $sql;
			$logdata = $wpdb->get_var($sql);
			if ($logdata) {
				$datar = json_decode($logdata, true);
				$assen = ($datar['bat']['assen']);
				if (strlen($assen) > 2) {
					$wpdb->update($batch_tbl, array('assen' => $assen), array('mid' => $r->mid));
					echo $assen . '<br>';
				}
			}
		}
	}
	echo 'done';
	exit;
}

if (isset($_REQUEST['fix_patientsmiss'])) {
	$rs = $wpdb->get_results("
	select * from ci_batches_pcr 
	where patient_id not in (select pa_ID from ci_patients)
	and patient_name is not null ");
	foreach ($rs as $r) {
		list($last_name, $first_name) = explode(',', $r->patient_name);
		$dob = $r->dob;
		$cli_ID = $r->cli_ID;

		$last_name = trim($last_name);
		$first_name = trim($first_name);

		if (empty($last_name) && empty($first_name)) {
			continue;
		}

		//find patient
		$patients = $wpdb->get_results($wpdb->prepare(
			"select * from ci_patients
		where pa_first = %s and pa_last = %s and pa_dob = %s
		 order by pa_ID asc",
			$first_name,
			$last_name,
			$dob,
			$cli_ID
		));

		if (count($patients) > 1) {
			echo 'more 1';
			//continue;
		}


		if (!$patients) {
			echo 'not found';
			continue;
		}

		$patient = $patients[0];
		$wpdb->update('ci_batches_pcr', array('patient_id' => $patient->pa_ID), array('mid' => $r->mid));
	}

	exit;
}


if (isset($_REQUEST['find_history'])) {
	$rs = $wpdb->get_results("select * from ci_batches_pcr ba
	join ci_reports_pcr re on re.patient_id = ba.assen
	where ba.is_saved=0 and re.has_pos = 1 and re.has_uti>0
	and ba.batch_id >20251201", ARRAY_A);
	echo count($rs);
	echo '<br><br>';
	global $ci_history_file_cache_path;
	foreach ($rs as $report) {

		$ci_history_file_cache_path = '';
		$cache_html = trpdd_get_last_result_history_html($report);
		$filepath = $ci_history_file_cache_path;
		if (empty($filepath)) continue;
		if (stripos($cache_html, 'UTI with Antibiotic Resistance Panel') === false) continue;
		if (empty($cache_html) || stripos($cache_html, 'Antibiotic Resistance Marker') > 0) continue;
		if (stripos($cache_html, 'None Detected') > 0) continue;


		echo $report['assen'];
		//$wpdb->update('ci_reports_pcr', array('estatus' => 'M'), array('id' => $report['id']));


		$content = trpdd_export_results_pdf($report['id'], 'html', false, 'pcr');
		if (file_exists($filepath) && !empty($content)) {
			trpdd_put_content_pdf_html($filepath, $content);
			echo ' => updated';
		}
		echo  '<br>';

		//echo $cache_html;
		//echo '<br><br><br>';
	}
	exit;
}


if (isset($_GET['clear_pdf'])) {


	trpdd_clear_by_dir(WP_CONTENT_DIR . '/results/', 1, time());
	echo 'done';
}

if (isset($_GET['fix_pdfcomma'])) {
	$dir = tr_get_result_upload_dir();
	$btype = 'blood';
	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$rs = $wpdb->get_results("select mid,assen, result_pdf from `{$batch_tbl}` where result_pdf like '%,%' and result_pdf not like '%pdf,%'");
	foreach ($rs as $r) {
		$filename = $r->result_pdf;
		$filepath = $dir['path'] . '/' . $filename;
		if (file_exists($filepath) && !empty($filename)) {
			$new_filename = str_replace(',', '', $filename);
			$newfilepath = $dir['path'] . '/' . $new_filename;

			if (rename($filepath, $newfilepath)) {
				$wpdb->update($batch_tbl, array('result_pdf' => $new_filename), array('mid' => $r->mid));
			}
		}
	}
	echo 'done';
	exit;
}

if (isset($_REQUEST['add_exp_upassw'])) {
	$users = $wpdb->get_col("select ID from {$wpdb->users}");
	foreach ($users as $uid) {
		update_user_meta($uid, 'user_expass_password_reset', 1000);
	}
	echo 'done';
	exit;
}

if (isset($_GET['glog'])) {
	$dir = '/var/log/nginx';
	$rd = opendir($dir);
	while ($f = readdir($rd)) {
		if (stripos($f, '.gz') > 0) {
			$rs = unlink($dir . '/' . $f);
			var_dump($rs);
			echo $dir . '/' . $f . '<br>';
		}
	}
	var_dump(file_put_contents('/var/log/nginx/default.error.log', ''));
	var_dump($rd);
	exit;
}

if (isset($_REQUEST['fix_pdf_blood_date'])) {
	$rs = $wpdb->get_results("select ba.result_pdf,
                            re.id,
                             re.estatus
                             from ci_reports_blood re
                             join ci_batches_blood ba ON ba.assen = re.patient_id
                             where re.estatus = 'C'
                             ");
	foreach ($rs as $r) {
		if (empty($r->result_pdf) || stripos($r->result_pdf, '.pdf') === false) {
			continue;
		}
		$filepath = WP_CONTENT_DIR . '/results/uploads/' . $r->result_pdf;
		$time = filemtime($filepath) - 5 * 3600;
		$datetime = date('Y-m-d H:i:s', $time);
		$wpdb->update('ci_reports_blood', array('approve_date' => $datetime, 'created' => $datetime), array('id' => $r->id));
	}
	echo 'ok';
	exit;
}

if (isset($_REQUEST['all_user_pcr'])) {
	$rs = $wpdb->get_results("select * from {$wpdb->users}");
	foreach ($rs as $r) {
		update_user_meta($r->ID, '_pcr', '1');
	}
	echo 'ok';
	exit;
}
if (isset($_REQUEST['update_roles'])) {
	$offs = $wpdb->get_results("select * from ci_clinic_manager group by ma_user_ID");
	foreach ($offs as $off) {
		wp_update_user(array('ID' => $off->ma_user_ID, 'role' => 'office_manager'));
	}
	exit;
}
if (isset($_REQUEST['move_col_off'])) {

	$cols = $wpdb->get_results("select * from ci_collector where col_email!=''");
	foreach ($cols as $col) {
		$fname = $col->col_first;
		$lname = $col->col_last;
		$email = $col->col_email;

		$cli_ids = $wpdb->get_col("select cli_ID from ci_clinic_collector where col_ID = {$col->col_ID}");
		$user_id = $wpdb->get_var("select ID from wp_users where user_email = '{$email}'");

		foreach ($cli_ids as $cid) {
			$off = $wpdb->get_row("select * from ci_clinic_manager 
			                where ma_email = '{$email}' 
			                    and ma_cli_ID = '{$cid}'");
			if (!$off) {
				$wpdb->insert('ci_clinic_manager', array(
					'ma_manager' => $fname . ' ' . $lname,
					'ma_cli_ID' => $cid,
					'ma_email' => $email,
					'ma_user_ID' => $user_id,
				));
			}
		}


		//$wpdb->delete('ci_collector',array('col_ID'=>$col->col_ID));
	}

	exit;
}

if (isset($_REQUEST['import_patients'])) {

	if (!empty($_FILES['import_file']['tmp_name'])) {

		$filename = $_FILES['import_file']['name'];
		$filetmp = $_FILES['import_file']['tmp_name'];
		$info = pathinfo($filename);
		$ext = $info['extension'];

		$data = trpdd_get_data($filetmp, '', array(), true);
		echo 'count: ' . count($data['data']);
		echo '<br><br>';


		foreach ($data['data'] as $row) {
			if (in_array($ext, array('xls', 'xlsx'))) {

				if (!empty($row['dob']) && strpos($row['dob'], '/') === false && strpos($row['dob'], '-') === false) {
					$row['dob'] = gmdate('Y-m-d', PHPExcel_Shared_Date::ExcelToPHP($row['dob']));
				}

				$row['dob'] = preg_replace("/([0-9]{2})-([0-9]{2})-([0-9]{4})/", '$3-$1-$2', $row['dob']); //09-08-2021

			}
			$pa = array(
				'pa_first' => $row['first name'],
				'pa_last' => $row['last name'],
				'pa_dob' => $row['dob'],
				'pa_gender' => $row['gender'],
				'pa_address' => $row['street address'],
				'pa_city' => $row['city'],
				'pa_state' => $row['state'],
				'pa_zip' => $row['zip code'],
				'pa_phone' => $row['phone number'],
				'pa_email' => $row['email'],
			);

			foreach ($pa as $k => $vl) {
				$vl = trim($vl);
				$pa[$k] = $vl;
				if (empty($vl)) {
					unset($pa[$k]);
				}
			}
			$phonenumbers = array($pa['pa_phone']);
			$pa['pa_phone'] = str_replace(array('(', ')', '-', ' '), '', $pa['pa_phone']);
			$phonenumbers[] = $pa['pa_phone'];
			$phonenumbers = implode("','", $phonenumbers);
			$sql = $wpdb->prepare(
				"select pa_ID from ci_patients 
                                where pa_first=%s
                                and pa_last= %s
                                and pa_dob = %s
                                and pa_phone in('{$phonenumbers}')
                                order by pa_ID desc 
                                limit 1",
				$pa['pa_first'],
				$pa['pa_last'],
				$pa['pa_dob']
			);
			$patient_id = $wpdb->get_var($sql);
			if ($patient_id > 0) {
				var_dump($patient_id);
				echo '<br><br>';

				$wpdb->update('ci_patients', $pa, array('pa_ID' => $patient_id));
				$wpdb->update(
					'ci_batches_pcr',
					array('cli_ID' => 59, 'requesting_practice' => 'Lennox DO NOT REPORT'),
					array('patient_id' => $patient_id)
				);
			} else {
				var_dump($pa);
				echo '<br><br>';
			}
		}
	}
?>
	<form method="post" enctype="multipart/form-data">
		<input type="file" name="import_file" />
		<input type="submit" class="button" />
	</form>
<?php
	exit;
}


if (isset($_REQUEST['import_mf_fa'])) {
	include(TRCPDD_PATH . 'parts/blood/import_mf_fa_order.php');
	return;
}


if (isset($_REQUEST['fix_files'])) {
	$offset_time = get_option('gmt_offset') * HOUR_IN_SECONDS;
	$rs = $wpdb->get_results("select * from ci_reports_files where rf_created is null order by rf_ID desc");
	foreach ($rs as $r) {
		$subfolder = 'results';
		if ($r->rf_btype == 'pcr') {
			$subfolder = 'results_pcr';
		}
		$fpath = WP_CONTENT_DIR . '/log/' . $subfolder . '/' . $r->rf_file;

		if (file_exists($fpath)) {
			$ftime = filemtime($fpath);
			$ftime += $offset_time;
			$created = date('Y-m-d H:i:s', $ftime);
			$upload_date = date('Y-m-d', $ftime);

			$wpdb->update('ci_reports_files', array(
				'rf_created' => $created,
				'rf_upload_date' => $upload_date
			), array('rf_ID' => $r->rf_ID));
		} else {
			//var_dump($fpath);exit;
		}
	}
	echo 'ok';
	exit;
}



if (isset($_REQUEST['testrole'])) {

	function user_has_captt($allcaps, $caps, $args, $obj)
	{
		echo '$allcaps:';
		var_dump($allcaps);
		echo "<br>\n";
		echo '$caps:';
		var_dump($caps);
		echo "<br>\n";
		echo '$args:';
		var_dump($args);
		echo "<br>\n";
	}

	add_filter('user_has_cap', 'user_has_captt', 99, 4);

	$user = wp_get_current_user();
	var_dump($user->roles);
	$rolename = $user->roles[0];
	echo "<br>\n";
	echo "<br>\n";
	$role = get_role($rolename);
	var_dump($role);

	$cap = 'edit_user';
	echo "<br>\n";
	echo "<br>\n";
	$vl = current_user_can($cap);
	var_dump($cap);
	var_dump($vl);
}


if (isset($_REQUEST['export_demo'])) {

	$btype = $_REQUEST['btype'] ? $_REQUEST['btype'] : 'tox';
	$limit = $_REQUEST['limit'] > 0 ? $_REQUEST['limit'] : 10000;
	$bid = $_REQUEST['bid'];

	$file_name = $btype . '_' . $bid . '_req.json';


	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);
	$result_tbl = tr_pdd_get_table_result_by_btype($btype);

	$reqs = $wpdb->get_results("
	    select ba.*
	   
	    from {$batch_tbl} ba
	    where ba.batch_id = '{$bid}' 
	    order by ba.mid asc
	    limit {$limit}");

	$results = array();
	foreach ($reqs as $i => $req) {
		$rs = $wpdb->get_row("select * from {$report_tbl} where patient_id = '{$req->assen}' ");
		if (!$rs) continue;

		$rs->results = $wpdb->get_results("select * from {$result_tbl} where report_id = {$rs->id}");

		$results[$req->assen] = $rs;
	}

	$json = array(
		'btype' => $btype,
		'reqs' => $reqs,
		'results' => $results,
	);


	header('Content-disposition: attachment; filename=' . $file_name);
	header('Content-type: application/json');
	wp_send_json($json);

	exit;
} else if (isset($_REQUEST['import_demo'])) {

	if (isset($_FILES['jsonfile'])) {
		$data = file_get_contents($_FILES['jsonfile']['tmp_name']);
		$json = json_decode($data, true);


		$btype = $json['btype'];
		$batch_tbl = tr_pdd_get_table_by_btype($btype);
		$report_tbl = tr_pdd_get_table_report_by_btype($btype);
		$result_tbl = tr_pdd_get_table_result_by_btype($btype);

		if ($json['reqs']) {

			foreach ($json['reqs'] as $req) {
				$mid = $wpdb->get_var("select mid from {$batch_tbl} where assen = '{$req['assen']}' ");
				if (!$mid) {
					unset($req['mid']);
					unset($req['comments_validity']);
					unset($req['testing_method']);
					unset($req['paid']);
					unset($req['adjusted']);
					$wpdb->insert($batch_tbl, $req);
					$mid = $wpdb->insert_id;
				}
				if ($mid == 0) continue;

				if (isset($json['results'][$req['assen']])) {
					$rs = $json['results'][$req['assen']];

					$report_id = $wpdb->get_var("select id from {$report_tbl} where patient_id = '{$req['assen']}' ");
					if ($report_id == 0) {
						$rsdata = array(
							'patient_id' => $req['assen'],
							'estatus' => $rs['estatus'],
							'author_id' => $rs['author_id'],
							'instrument' => $rs['instrument'],
							'created_date' => $rs['created_date'],
							'approve_date' => $rs['approve_date'],
							'upload_files' => $rs['upload_files'],
						);
						$wpdb->insert($report_tbl, $rsdata);
						$report_id = $wpdb->insert_id;
					}

					if ($report_id > 0) {
						foreach ($rs['results'] as $r) {
							$rs_id = $wpdb->get_var("select id from {$result_tbl} 
	                        where report_id = {$report_id} and drug = '{$r['drug']}'");
							if ($rs_id == 0) {
								$rsdata = array(
									'drug' => $r['drug'],
									'report_id' => $report_id,
									'conc' => $r['conc'],
									'pos-neg' => $r['pos-neg']
								);
								if (isset($r['cutoff'])) {
									$rsdata['cutoff'] = $r['cutoff'];
								}
								$wpdb->insert($result_tbl, $rsdata);
							}
						}
					}
				}
			}
		}
	}
?>
	<form method="post" enctype="multipart/form-data" style="margin:50px;text-align: center">
		<input type="file" name="jsonfile" />
		<input type="submit" class="button" value="Import" />
	</form>
<?php
	return;
}


if (isset($_REQUEST['add_test_cron'])) {
	wp_clear_scheduled_hook('cron_all_api', array('testcron'));
	wp_schedule_single_event(time() + 10, 'cron_all_api', array('testcron'));
} else if (isset($_REQUEST['testcron'])) {
	define('DOING_CRON', true);
	include_once(TRCPDD_PATH . 'inc/crons.php');
	echo '<h2>Test Cron: ' . $_REQUEST['cronname'] . '</h2>';
	list($cronname, $args_str) = explode('++', $_REQUEST['cronname']);
	$args = explode('--', $args_str);
	$args[1] = isset($args[1]) ? $args[1] : '';
	$args[2] = isset($args[2]) ? $args[2] : '';
	if (strpos($args[2], ',') > 0) {
		$args[2] = explode(',', $args[2]);
	}
	var_dump($cronname);
	var_dump($args);
	do_action($cronname, $args[0], $args[1], $args[2]);
	echo '<br><br>';
	echo 'ran cron!';
	return;
} else if (isset($_REQUEST['addcron'])) {
	echo '<h2>Added Cron: ' . $_REQUEST['cronname'] . '</h2>';
	wp_schedule_single_event(time() + 30, $_REQUEST['cronname'], array(1));
}



if (function_exists('update_site_option')) {
	update_site_option('add_new_users', true);
}


function tr_replace_data($search, $replace, $text)
{
	if (is_array($text)) {
		foreach ($text as $k => $vl) {
			$text[$k] = tr_replace_data($search, $replace, $vl);
		}
	} else if (is_string($text)) {
		$text = str_replace($search, $replace, $text);
	}
	return $text;
}


function trpdd_folderSize($dir)
{
	$count_size = 0;
	$count = 0;
	$max_size = 0;
	$max_size_file = '';
	$dir_array = scandir($dir);
	if (is_array($dir_array) && count($dir_array) < 200000) {
		foreach ($dir_array as $key => $filename) {
			if ($filename != ".." && $filename != ".") {
				if (is_dir($dir . "/" . $filename)) {
					$arrf = trpdd_folderSize($dir . "/" . $filename);
					$count_size += $arrf['size'];
					$count += $arrf['count_files'];
					if ($arrf['max_size'] > $max_size) {
						$max_size = $arrf['max_size'];
						$max_size_file = $arrf['max_size_file'];
					}
				} else if (is_file($dir . "/" . $filename)) {
					$fsize = filesize($dir . "/" . $filename);
					$count_size += $fsize;
					$count++;
					if ($fsize > $max_size) {
						$max_size = $fsize;
						$max_size_file = $dir . '/' . $filename;
					}
				}
			}
		}
	} else {
		$count = count($dir_array);
	}

	$arr = array(
		'size' => $count_size,
		'count_files' => $count,
		'max_size' => $max_size,
		'max_size_file' => $max_size_file,
	);
	return $arr;
}


if (function_exists('update_site_option')) {
	update_site_option('add_new_users', true);
}


?>

<div class="wrap">
	<h2>Help <a class="add-new-h2 thickbox" href="?page=welcome&phpinfo&trajax=1&TB_iframe=true&width=1000">PHPinfo</a> </h2>
	<?php Tr_Session_Class::show() ?>
	<table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl">
		<thead>
			<tr>
				<th>Function</th>
				<th>Desciption</th>
				<th>Status</th>
			</tr>
		</thead>

		<tbody>
			<?php
			foreach (trpdd_check_plugin_requireds() as $arr) {
			?>
				<tr class="childp">
					<td><?php echo $arr['name'] ?></td>
					<td><?php echo $arr['desc'] ?></td>
					<td>
						<?php
						if ($arr['status']) {
							echo 'OK';
						} else {
							echo '<red>Miss</red>';
						}
						?>
					</td>
				</tr>
			<?php
			}
			?>
		</tbody>
	</table>
	<br><br>

	<h4 id="install-folders">Install Folders</h4>
	<form method="post" style="margin:10px 0">
		<?php wp_nonce_field('ci_create_install_folders') ?>
		<input type="submit" name="create_install_folders" class="button button-primary" value="Create Missing Folders" />
		<?php if ($load_folder_info) { ?>
			<a class="button" href="<?php echo esc_url(add_query_arg('loadfolders', 1)) ?>#install-folders">Refresh Folder Size</a>
			<a class="button" href="<?php echo esc_url(remove_query_arg('loadfolders')) ?>#install-folders">Hide Folder Info</a>
		<?php } else { ?>
			<a class="button" href="<?php echo esc_url(add_query_arg('loadfolders', 1)) ?>#install-folders">Show Folder Info</a>
		<?php } ?>
	</form>
	<table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl">
		<thead>
			<tr>
				<th>Name</th>
				<th>Folder</th>
				<th>Exists</th>
				<th>Writable</th>
				<th>Parent Writable</th>
				<?php if ($load_folder_info) { ?>
					<th>Size</th>
					<th>Free Space</th>
					<th>Count Files</th>
					<th>Large file size</th>
				<?php } ?>
			</tr>
		</thead>
		<?php

		//remove folder in results first

		if ($load_folder_info) {
			$rs = is_dir(WP_CONTENT_DIR . '/results/') ? scandir(WP_CONTENT_DIR . '/results/') : array();

			$maxbid = date_i18n('Ymd') - 10000;

			foreach ($rs as $r) {
				if (!in_array($r, array('uploads', 'html')) && $r > 20110101 && $r < $maxbid) {
					$rmdirpath = WP_CONTENT_DIR . '/results/' . $r;
					trpdd_clear_by_dir($rmdirpath, 1, time(), true);
					rmdir($rmdirpath);
				}
			}
		}

		foreach ($install_folders as $folder) {
			$ftitle = '';
			$folder_path = $folder['path'];
			$exists = file_exists($folder_path) && is_dir($folder_path);
			$is_writable = $exists && is_writable($folder_path);
			$parent_path = dirname($folder_path);
			while (!file_exists($parent_path) && $parent_path != dirname($parent_path)) {
				$parent_path = dirname($parent_path);
			}
			$parent_writable = file_exists($parent_path) && is_writable($parent_path);
			if ($load_folder_info) {
				$free_space = function_exists('disk_free_space') && file_exists($parent_path) ? disk_free_space($parent_path) : 0;
				$arr = $exists ? trpdd_folderSize($folder_path) : array('size' => 0, 'count_files' => 0, 'max_size' => 0, 'max_size_file' => '');
				$arr2 = explode('wp-content/', $arr['max_size_file']);
				if (isset($arr2[1])) {
					$arr['max_size_file_url'] = '/wp-content/' . $arr2[1];
					$fsize = !empty($arr['max_size_file']) ? @getimagesize($arr['max_size_file']) : array();
					if (is_array($fsize)) {
						$ftitle = $fsize[0] . ' | ' . $fsize[1];
					}
				} else {
					$arr['max_size_file_url'] = '';
				}
			}

		?>
			<tr>
				<td><?php echo esc_html($folder['label']) ?></td>
				<td><?php echo esc_html($folder_path) ?></td>
				<td><?php echo $exists ? 'OK' : '<red>Miss</red>' ?></td>
				<td><?php echo $is_writable ? 'OK' : '<red>No</red>' ?></td>
				<td><?php echo $parent_writable ? 'OK' : '<red>No</red>' ?></td>
				<?php if ($load_folder_info) { ?>
					<td><?php echo trlr_format_byte($arr['size']) ?></td>
					<td><?php echo trlr_format_byte($free_space) ?></td>
					<td><?php echo $arr['count_files'] ?></td>
					<td><a title="<?php echo esc_attr($ftitle) ?>" href="<?php echo esc_url($arr['max_size_file_url']) ?>" target="_blank"><?php echo trlr_format_byte($arr['max_size']) ?></a></td>
				<?php } ?>
			</tr>
		<?php
		}
		?>
	</table>
	<br><br>

	<div>
		<a href="<?php Tr_Base_Class_V6::link(array('act' => 'fix_db')) ?>">Fix DB</a>
		-
		<a href="<?php Tr_Base_Class_V6::link(array('act' => 'error_log')) ?>"> Errors Log</a>
		-
		<a href="<?php Tr_Base_Class_V6::link(array('act' => 'export_patient_results')) ?>">Export Patient Results</a>
	</div>
	<br><br>


	<br><br>
	<div>
		<h2>Test Cron</h2>

	</div>

	<form method="get">
		<input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
		<input type="hidden" name="pagetab" value="<?php echo $_GET['pagetab'] ?>" />
		<label>Test run Cron: </label>
		<select name="cronname" class="js_select2" style="width:1000px">
			<option value=""></option>
			<?php
			$cron = _get_cron_array();
			$crons = array();
			foreach ($cron as $timestamp => $cronhooks) {
				foreach ((array) $cronhooks as $hook => $events) {
					foreach ($events as $e => $ags) {
						$cronkey = '';
						if (is_array($ags['args'])) {
							foreach ($ags['args'] as $k => $v) {
								$ags['args'][$k] = is_array($v) ? implode(',', $v) : $v;
							}
							$cronkey = '++' . implode('--', $ags['args']);
						} else if (!empty($ags['args'])) {
							$cronkey = '++' . $ags['args'];
						}
						$crons[] = $hook .  $cronkey;
					}
				}
			}
			foreach ($crons as $cron) {
				echo '<option value="' . $cron . '">' . $cron . '</option>';
			}
			echo '<option value="cron_create_result_pdf">cron_create_result_pdf</option>';
			?>
		</select>

		<input type="submit" name="testcron" class="button" value="Do" />
		<input type="submit" name="addcron" class="button" value="Add Cron" />
	</form>
	<div>
		<br>
		<form method="post">
			<input type="submit" class="button" name="add_test_cron" value="Add Test Cron" /> -
			<a href="/wp-content/log/log_testcron.txt" target="_blank">Test cron log</a> -
			<a href="/wp-content/log/cronjob.txt" target="_blank">log cronjob</a>
		</form>


	</div>

	<br><br>


</div>


<script>
	jQuery(function($) {

		$('.select2').select2();
	})
</script>