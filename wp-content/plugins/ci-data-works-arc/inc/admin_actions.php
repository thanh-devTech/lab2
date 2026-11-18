<?php

@set_time_limit(900);

function tradmin_action_ci_export_sale_report()
{
	include "actions/ci_export_sale_report.php";
}

function tradmin_action_ci_export_sale_report_date()
{
	include "actions/ci_export_sale_report_date.php";
}


function tradmin_action_ci_export_sale_report_info()
{
	include "actions/ci_export_sale_info.php";
}

function tradmin_action_ci_pdf_export_report()
{
	include "actions/ci_pdf_export_report.php";
}

function tradmin_action_ci_export_audit_report()
{
	include "actions/ci_export_audit_report.php";
}

function tradmin_action_ci_customer_show_orders()
{
	include "actions/ci_customer_show_orders.php";
}


function tradmin_action_ci_import_batch_data()
{
	include "actions/ci_import_batch_data.php";
}

function tradmin_action_run_import_batch_data()
{
	global $inserted_batch_import;
	ob_start();
	$return = array('status' => 'done', 'html' => '');

	$upload_dir = tr_wp_upload_dir();
	$import_dir = $upload_dir['basedir'] . '/batch_imports';
	$import_file = $_REQUEST['file'];
	if ($import_file == 'zip') {
		$f = opendir($import_dir);
		while ($file = readdir($f)) {
			if (is_file($import_dir . '/' . $file) && stripos($file, '.csv') !== false) {
				if ($import_file == 'zip') {
					$import_file = $file;
				} else {
					$return['status'] = 'ok';
				}
			}
		}
	}
	$file_path = $import_dir . '/' . $import_file;
	$imported = trimport_batch_by_file($file_path, $import_file);
	unlink($file_path);
	if ($_REQUEST['file'] == 'zip') {
		$return['html'] .= 'File: ' . $import_file;
	}
	$return['html'] .= '- rows: ' . $imported . ' | new: ' . $inserted_batch_import;
	$return['log'] = ob_get_clean();
	ob_start();
	Tr_Session_Class::show();
	$return['html'] .= ob_get_clean();
	wp_send_json($return);
	exit;
}

function trimport_batch_by_file($file, $filename = '')
{
	global $wpdb, $inserted_batch_import;
	$updated_batch_import  = 0;

	$f = fopen($file, 'r');
	$r = 0;
	$batch_id = 0;

	$imported = 0;
	$fields_list = array();

	$table_keys = $wpdb->get_results("SHOW FULL COLUMNS FROM ci_batches");
	$exist_keys = array();
	// var_dump($table_keys);exit;
	foreach ($table_keys as $i => $k) {
		$exist_keys[$k->Field] = $k->Type;
	}



	while ($row = fgetcsv($f)) {
		$r++;
		if ($r == 1) {

			if (stripos($row[0], 'batch') !== false) {
				$batch_id = intval(preg_replace("/[\D]/", '', $row[0]));
			} else if (stripos($row[1], 'batch') !== false) {
				$batch_id = intval(preg_replace("/[\D]/", '', $row[1]));
			}
			//var_dump($row[1]);
			//var_dump($batch_id);exit;
			continue;
		} else if ($r == 2) {

			if (isset($_REQUEST['setup'])) {
				tr_setup_batch_table($row, fgetcsv($f));
				break;
			} else {
				//setup fields
				$fields_list = tr_setup_field_new($row, $exist_keys, 'ci_batches');
			}

			continue;
		}
		// var_dump(count($row));exit;
		//$batch_id = 0;
		if ($batch_id == 0) {
			//find batch_id from filename
			preg_match("/batch ([0-9]{1,4})/i", $filename, $matches);

			$batch_id = intval(preg_replace("/[\D]/", '', $matches[1]));
			if ($batch_id == 0) {
				Tr_Session_Class::add_error('File: ' . $filename . '. Don\'t see Batch ID');
				if ($r <= 2) continue;
				break;
			}
		}

		if (empty($row[0]) && empty($row[1])) continue;


		$data = array(
			'batch_id' => $batch_id,
		);


		foreach ($fields_list as $fk => $kp) {
			$vl = trim($row[$kp]);
			if ($exist_keys[$fk] == 'date') {
				$vl = trformatdate($vl);
			}
			$data[$fk] = $vl;
		}

		if (empty($data['doc'])) continue;

		//var_dump($data);exit;
		//insert
		$batch = $wpdb->get_row("select mid,batch_type from ci_batches
        where batch_id={$batch_id} and doc='{{$data['doc']}}' and assen = '{$data['assen']}' ");
		if ($batch) {
			if ($batch->batch_type == 'M') {
				$wpdb->update('ci_batches', $data, array('mid' => $batch->mid));
			}
		} else {
			$wpdb->insert('ci_batches', $data);
			$inserted_batch_import++;
		}

		$imported++;
	}

	//update lab
	$labs = $wpdb->get_results("select * from ci_labs");
	foreach ($labs as $lab) {
		$wpdb->query("update ci_batches set lab_id = {$lab->lab_ID} where lab_id=0
        and (lab='{$lab->lab_dba_init}' or lab='{$lab->lab_name}' or lab='{$lab->lab_dba}') ");
	}

	//end update lab
	return $imported;
}

function tradmin_action_ci_import_billing_all()
{
	global $errors;

	$dir = TRCPDD_PATH . 'files2';
	$d = opendir($dir);
	$i = 1;
	while ($f = readdir($d)) {
		$path = $dir . '/' . $f;
		if (!is_file($path)) continue;
		//echo $path;
		$imported = tradmin_action_ci_import_billing_file($path, $f);
		echo $i++;
		echo '=>';
		echo $f;
		echo '=>';
		echo $imported;
		echo '=>e: ' . $errors;
		echo '<br>';
		// exit;
	}
	exit;
}

function tradmin_action_ci_import_billing_data()
{
	global $import_file;
	global $import_act;


	$file = $_FILES['billing']['tmp_name'];
	$filename = $_FILES['billing']['name'];
	if (empty($file) || (stripos($filename, '.csv') === false && stripos($filename, '.zip') === false)) {
		Tr_Session_Class::add_error('Please select CSV file to upload');
		return false;
	}

	//save file
	$upload_dir = tr_wp_upload_dir();
	$import_dir = $upload_dir['basedir'] . '/bill_imports';
	$import_file = Tr_Form_V7::uploadfile($_FILES['billing'], $filename, $import_dir, array('csv', 'zip'));
	if (!$import_file) {
		Tr_Session_Class::add_error('Error, upload file ');
	} else {
		$import_act = 'bill';
		if (stripos($filename, '.zip') !== false) {
			$file = $import_dir . '/' . $import_file;
			WP_Filesystem();
			$unzipfile = unzip_file($file, $import_dir);
			if (!$unzipfile) {
				Tr_Session_Class::add_error('Error, can\'t extract zip file');
				$import_act = '';
			} else {
				$import_act = 'zip_bill';
				$import_file = 'zip';
			}
			unlink($file);
		}
	}
}

function tradmin_action_run_ci_import_billing_data()
{
	ob_start();
	$return = array('status' => 'done', 'html' => '');

	$upload_dir = tr_wp_upload_dir();
	$import_dir = $upload_dir['basedir'] . '/bill_imports';
	$import_file = $_REQUEST['file'];
	if ($import_file == 'zip') {
		$f = opendir($import_dir);
		while ($file = readdir($f)) {
			if (is_file($import_dir . '/' . $file) && stripos($file, '.csv') !== false) {
				if ($import_file == 'zip') {
					$import_file = $file;
				} else {
					$return['status'] = 'ok';
					break;
				}
			}
		}
	}
	$file_path = $import_dir . '/' . $import_file;
	$imported = tradmin_action_ci_import_billing_file($file_path, $import_file);
	unlink($file_path);
	if ($_REQUEST['file'] == 'zip') {
		$return['html'] .= 'File: ' . $import_file;
	}
	$return['html'] .= '- Imported: ' . $imported;
	$return['log'] = ob_get_clean();
	ob_start();
	Tr_Session_Class::show();
	$return['html'] .= ob_get_clean();
	wp_send_json($return);
	exit;
}

function tradmin_action_run_ci_import_billing_data_repair()
{
	global $wpdb;
	ob_start();
	$return = array('status' => 'done', 'html' => '');

	$upload_dir = tr_wp_upload_dir();
	$import_dir = $upload_dir['basedir'] . '/ins_mix';

	$f = opendir($import_dir);
	$files = array();
	while ($file = readdir($f)) {
		if (is_file($import_dir . '/' . $file) && stripos($file, '.txt') !== false) {
			$files[] = $file;
		}
	}
	$count = count($files);

	if ($count > 0) {
		$file = $files[0];
		$data = file_get_contents($import_dir . '/' . $file);
		$data = @json_decode($data, true);
		foreach ($data as $row) {
			$exists = $wpdb->get_var("select ins_ID from ci_insurancemix where lab_ID={$row['lab_ID']}
                                and ins_name='{$row['ins_name']}' and ins_code='{$row['ins_code']}'");
			if (!$exists) {
				$wpdb->insert('ci_insurancemix', $row);
			}
		}

		unlink($import_dir . '/' . $file);

		if ($count > 1) {
			$return['status'] = 'ok';
			echo 'Repair Ins Mix (left: ' . ($count - 1) . ')';
		}
	}

	if ($return['status'] == 'done') {
		$wpdb->query("repair table `ci_batch_billings`");
		echo 'Repair done';
	}


	$return['html'] .= ob_get_clean();
	wp_send_json($return);
	exit;
}

function tradmin_action_ci_import_billing_file($file, $filename = '')
{
	global $wpdb, $errors;

	$lab_id = CI_Base_Lab::get_current_lab_id();


	$f 			= fopen($file, 'r');
	$r			= 0;
	$imported 	= 0;
	$errors 	= 0;

	$table_keys = $wpdb->get_results("SHOW FULL COLUMNS FROM ci_batch_billings");
	$exist_keys = array();
	$fields_list = array();

	$labs 		= $wpdb->get_results("select lab_ID,lab_name from ci_labs");
	$labs_arr 	= array();

	foreach ($labs as $lab) {
		$labs_arr[$lab->lab_name] = $lab->lab_ID;
	}

	// var_dump($table_keys);exit;
	foreach ($table_keys as $i => $k) {
		$exist_keys[$k->Field] = $k->Type;
	}

	$ins_mix = array();

	while ($row = fgetcsv($f)) {
		$r++;
		if ($r == 1) {
			if (stripos($row[0], 'key') === false) {
				Tr_Session_Class::add_error('Dont see keyid field');
				return;
			}
			if (isset($_REQUEST['setup'])) {
				tr_setup_batch_table($row, fgetcsv($f));
				break;
			} else {
				$fields_list = tr_setup_field_new($row, $exist_keys, 'ci_batch_billings');
			}
			continue;
		}

		if (count($fields_list) < 10) break;

		$data = array(
			'keyid' => $row[0],
			'lab_id' => $lab_id,
		);

		foreach ($fields_list as $fk => $kp) {
			$vl = $row[$kp];
			if ($exist_keys[$fk] == 'date') {
				$vl = trformatdate($vl);
			}
			$data[$fk] = $vl;
		}
		// var_dump($data);exit;
		//insert
		$rs = $wpdb->insert('ci_batch_billings', $data);
		if ($rs) {

			$imported++;
		} else {
			$errors++;
		}
		//import to insurancemix
		$lab_id = $labs_arr[$data['dname']];
		if (!$lab_id) {
			$wpdb->insert('ci_labs', array('lab_name' => $data['dname']));
			$lab_id = $wpdb->insert_id;
			$labs_arr[$data['dname']] = $lab_id;
		}
		$ins_mix[] = array('lab_ID' => $lab_id, 'ins_name' => $data['pinsname'], 'ins_code' => $data['pinscode']);
	}

	if (count($ins_mix) > 0) {
		//save mix file
		$upload_dir = tr_wp_upload_dir();
		$fmix = $upload_dir['basedir'] . '/ins_mix/' . time() . '.txt';
		file_put_contents($fmix, json_encode($ins_mix));
	}

	return $imported;
}

function tr_setup_field_new($headers, $exists, $table = '')
{
	$fields = array();
	$fs = array();
	// var_dump($exists);
	// echo '<br><br>';
	foreach ($headers as $i => $k) {
		if ($i == 0) continue;

		$kname = strim_title($k);
		$k = strim_key($kname);
		/**
		if($k=='custom_profile' && $table=='ci_batches'){
		$k = 'panel_tests_requested';
		}
		 */
		if (empty($k)) continue;
		$k = tr_check_key_in_array($fs, $k);
		$fs[] = $k;

		if (!isset($exists[$k])) {
			//create new column table
			global $wpdb;
			$vl = tr_get_column_type($kname);
			$wpdb->query("alter table {$table} add column {$k} {$vl}");
			echo "alter table {$table} add column {$k} {$vl}";
			echo '<br>';
		}
		$fields[$k] = $i;
	}
	return $fields;
}

function trformatdate($date)
{
	preg_match("/([0-9]{1,2})\/([0-9]{1,2})\/([0-9]{2,4})/", $date, $matches);
	if (count($matches) > 0) {
		$year = $matches[3];
		if (strlen($year) == 2) {
			$year = $year < 20 ? '20' . $year : '19' . $year;
		}
		$month = $matches[1];
		$day = $matches[2];
		$date = $year . '-' . $month . '-' . $day;
	}
	return $date;
}

function tr_check_key_in_array($fields, $k, $number = 0)
{
	$kf = $number > 0 ? $k . $number : $k;
	if (in_array($kf, $fields)) {
		return tr_check_key_in_array($fields, $k, $number + 1);
	}
	return $kf;
}

function strim_title($k)
{
	$k = preg_replace("/\([^\)]*\)/", '', $k);
	$k = preg_replace("/[^a-zA-Z0-9\/\s_]/", '', $k);
	$k = str_replace('RESQUESTING', 'REQUESTING', $k);
	$k = str_replace('PRCATICE', 'PRACTICE', $k);
	$k = str_replace('COLLEDCTED', 'COLLECTED', $k);
	$k = str_replace('COLLEDCTED', 'COLLECTED', $k);
	$k = trim($k);
	return $k;
}

function strim_key($k)
{
	$k = sanitize_title($k);
	$k = str_replace('-', '_', $k);
	return $k;
}

function tr_get_column_type($kname, $value = '')
{
	$type = 'varchar';
	$length = '(50)';
	$default = ' default null';
	$comment = " COMMENT '{$kname}'";

	preg_match("/^([0-9]{1,2})\/([0-9]{1,2})\/([0-9]{2,4})$/", $value, $matches);
	if (count($matches) > 0) {
		$type = 'date';
		$length = '';
		$default = '';
	}
	return " {$type}{$length}{$default}{$comment}";
}

function tr_setup_batch_table($headers, $row)
{
	echo '<pre>';
	$fields = array();
	foreach ($headers as $i => $k) {
		$kname = strim_title($k);
		$value = trim($row[$i]);

		$k = strim_key($kname);
		$k = tr_check_key_in_array($fields, $k);
		$fields[] = $k;


		echo $k;
		echo tr_get_column_type($kname, $value);
		echo "\n";
	}
	foreach ($fields as $i =>    $k) {
		echo "'{$k}'=>\$row[{$i}],\n";
	}
	var_export($fields);
	echo '</pre>';
	exit;
}

function tradmin_action_save_ins_mix()
{
	global $wpdb;

	$id = $_REQUEST['id'];
	$k  = $_REQUEST['k'];
	$vl = $_REQUEST['vl'] == 'yes' ? '0' : '1';
	$oldvl = $vl == '1' ? 'no' : 'yes';

	$data = array($k => $vl);

	$wpdb->update('ci_insurancemix', $data, array('ins_ID' => $id));
	$vl = $vl == '1' ? 'yes' : 'no';
	wp_send_json(array('html' => $vl, 'classold' => 'vl_' . $oldvl, 'classnew' => 'vl_' . $vl));
}

function tradmin_action_save_payer_mix()
{
	global $wpdb;

	$id = $_REQUEST['id'];
	$k  = $_REQUEST['k'];
	$vl = $_REQUEST['vl'] == 'yes' ? '0' : '1';
	$oldvl = $vl == '1' ? 'no' : 'yes';

	$data = array($k => $vl);

	$wpdb->update('ci_payer_list', $data, array('payer_ID' => $id));
	$vl = $vl == '1' ? 'yes' : 'no';
	trpdd_log_user(get_current_user_id(), 'update_payer', $data, '', '', '', '', $id);
	wp_send_json(array('html' => $vl, 'classold' => 'vl_' . $oldvl, 'classnew' => 'vl_' . $vl));
}

function tradmin_action_show_basic_info()
{
	$id = $_REQUEST['id'];
	$file = isset($_REQUEST['file']) ? $_REQUEST['file'] : 'info.jpg';
?>
	<div>
		<img src="<?php echo TRCPDD_URL ?>images/<?php echo $file ?>" style="width: 100%" />
	</div>
	<div>
		<a class="button" onclick="tb_remove();">OK</a>
		<a class="button" onclick="cancel_info('<?php echo $id ?>')">CANCEL</a>
	</div>
<?php
	exit;
}

function tradmin_action_form_collector_request()
{
	include "actions/form_collector_request.php";
}

function tradmin_action_restore_req_form()
{
	global $wpdb;
	$created = $_REQUEST['id'];
	$btype = $_REQUEST['btype'];
	$action = 'save_' . $btype;
	$row = $wpdb->get_row("select * from `ci_log_forms` where id='{$created}' and form='{$action}' ", ARRAY_A);
	if ($row) {
		$_POST = $_REQUEST = json_decode($row['data'], true);


		$_POST['bat']['mid'] = $_POST['mid'];

		$_SESSION['form_unique_id'] = $_POST['form_unique_id'];
		if (!empty($_REQUEST['assen']) && empty($_POST['bat']['assen'])) {
			$_POST['bat']['assen'] = $_REQUEST['assen'];
		}
		if (empty($_POST['bat']['assen']) && !empty($_POST['bat']['assenauto'])) {
			$_POST['bat']['assen'] = $_POST['bat']['assenauto'];
		}

		unset($_POST['bat']['assenauto']);
		unset($_REQUEST['auto_generated']);
		switch ($btype) {
			case 'tox':
				tradmin_action_ci_save_form_requisition();
				break;
			case 'pcr':
				tradmin_action_ci_save_form_pcr();
				break;
		}
		var_dump($_POST);
		exit;
	}
}

function tradmin_action_ci_save_form_requisition()
{
	include "actions/ci_save_form_requisition.php";
}

function tradmin_action_ci_save_form_custom_provider()
{
	global $wpdb;
	$_POST = tr_pdd_stripslashes_deep($_POST);

	$cus = $_POST['cus'];

	$in_inds = array();
	if (is_array($cus['cus_individuals'])) {
		$in_inds = $cus['cus_individuals'];
		$cus['cus_individuals'] = implode(',', $cus['cus_individuals']);
	} else {
		$cus['cus_individuals'] = @$cus['cus_individuals'];
	}


	//update cus_injections
	if (count($in_inds) > 0) {
		$in_inds = implode("','", $in_inds);
		$cus_injections = array();
		$injs = $wpdb->get_results("select * from ci_injections where test in ('{$in_inds}') group by panel_U
            order by FIELD(panel_U,'PP','THC','TBS','DL','Alcohol') ");
		foreach ($injs as $row) {
			$row->panel_U = trim($row->panel_U);
			if (empty($row->panel_U)) continue;
			$cus_injections[] = $row->panel_U;
		}
		$cus['cus_injections'] = implode('/', $cus_injections);
	}


	$signature = $_POST['signature'];
	$cus_id = @$cus['cus_ID'];

	if ($cus_id > 0) {
		$rs = $wpdb->update('ci_custom_providers', $cus, array('cus_ID' => $cus_id));
	} else {
		$rs = $wpdb->insert('ci_custom_providers', $cus);
		$cus_id = $wpdb->insert_id;
	}
	//check and update tests for that doctor in batches
	$doc_id = $cus['doc_ID'];
	/**
	if($doc_id > 0)
	{
	$batches = $wpdb->get_results("select * from ci_batches where doc_ID = {$doc_id} and custom_profile = 'Use Custom Profile' ", ARRAY_A);
	foreach($batches as $batch)
	{
	$panel_tests_requested = trpdd_panel_tests_requested($batch,$batch['doc_ID'],$batch['cli_ID']);
	if(!empty($panel_tests_requested) && $panel_tests_requested != $batch['panel_tests_requested']){
	$wpdb->update('ci_batches',array('panel_tests_requested'=> $panel_tests_requested), array('mid'=>$batch['mid']));
	}
	}

	//update user

	}
	 */
	$sign_dir = tr_get_signature_dir();
	$png_file = $sign_dir['path'] . '/provider_' . $cus_id . '.png';
	if (isset($_POST['remove_signature']) && $_POST['remove_signature'] == 1) {
		@unlink($png_file);
	}


	if (isset($_FILES['image_signature']) && file_exists($_FILES['image_signature']['tmp_name'])) {

		$file_tmp = $_FILES['image_signature']['tmp_name'];
		$file_ext = strtolower(end(explode('.', $_FILES['image_signature']['name'])));

		$expensions = array("jpeg", "jpg", "png", "gif", "pdf");

		if (in_array($file_ext, $expensions) === false) {
			$error = 1;
			Tr_Session_Class::add_error('extension not allowed, please choose a JPEG,PDF,PNG,JPG or PNG file.');
		} else {
			//echo 33;exit;
		}




		if (empty($error) == true) {
			if ($file_ext == 'pdf') {
				$pdf_file = $sign_dir['path'] . '/provider_' . $cus_id . '.pdf';
				if (file_exists($pdf_file)) {
					unlink($pdf_file);
				}
				$rs_move = move_uploaded_file($file_tmp, $pdf_file);

				if ($rs_move) {
					$resize = '1200x1200';
					$out = false;
					if (!empty($resize)) {
						$resize = "-resize {$resize}";
					}

					$quality = 20;

					$pdf_file = escapeshellarg($pdf_file);
					$png_file = escapeshellarg($png_file);

					$result = 0;
					$exc = "convert -quality {$quality} -density 300 {$resize} -append {$pdf_file} {$png_file}";

					try {
						exec($exc, $out, $result);
						$signature = '';
						$sin_s = false;
					} catch (Exception $e) {
						var_dump($e);
					}
				}
			} else {

				if (file_exists($png_file)) {
					unlink($png_file);
				}
				$upload_file_move = move_uploaded_file($file_tmp, $png_file);
				if ($upload_file_move) {
					$signature = '';
					$sin_s = false;
				}
			}
		} else {
			Tr_Session_Class::add_error('Upload Fail!');
		}
	} else {
		//save signatrue
		tr_save_signature('provider_' . $cus_id, $signature);
	}


	if ($rs === false) {
		Tr_Session_Class::add_error('Error: ' . $wpdb->last_error);
	} else {
		Tr_Session_Class::add('Saved CUSTOM PROVIDER FORM');
		$redirect = Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $cus_id), false);
		wp_redirect($redirect);
		exit;
	}
}

function tradmin_action_ci_delete_custom_provider()
{
	$id = $_REQUEST['id'];
	$redirect = Tr_Base_Class_V6::link(array('act' => 'list', 'id' => ''), false);
	global $wpdb;
	$wpdb->query("delete from ci_custom_providers where cus_ID = {$id}");
	Tr_Session_Class::add("Deleted Custom Provider #" . $id);
	wp_redirect($redirect);
	exit;
}

function tradmin_action_ci_save_form_new_account_due()
{
	include('actions/ci_save_form_new_account_due.php');
}

function tradmin_action_ci_save_form_new_account_setup()
{

	include "actions/ci_save_form_new_account_setup.php";
}

function tradmin_action_ci_export_today_batches($lab_id = 0, $date = false, $filepath = false)
{
	return include "actions/ci_export_today_batches.php";
}

function tradmin_action_ci_export_batches($lab_id = 0, $filepath = false)
{
	include "actions/ci_export_batches.php";
}

function tradmin_action_ci_view_req()
{
	global $wpdb;
	global $hide_tabs;
	$hide_tabs = true;
	$_REQUEST['act'] = 'view';
	$_REQUEST['not_show_tab'] = true;
	$type = isset($_REQUEST['type']) ? $_REQUEST['type'] : $_REQUEST['btype'];

?>
	<link href="<?php echo TRCPDD_URL ?>css/print.min.css?<?php echo TRCPDD_VS ?>" media="print" type="text/css" rel='stylesheet' />
<?php
	include(tr_pdd_get_req_file($type));
	exit;
}

function tradmin_action_ci_print_req()
{
	global $wpdb;
	$_REQUEST['act'] = 'print';
	if (stripos($_REQUEST['id'], '_') !== false) {
		list($btype, $id) = explode('_', $_REQUEST['id']);
		$_REQUEST['id'] = $_GET['id'] = $id;
		$_REQUEST['type'] = $btype;
	}
	ob_start();
	include(tr_pdd_get_req_file($_REQUEST['type']));
	$content = ob_get_clean();
	$content = preg_replace("#\<option[^\>]*selected[^\>]*\>([^\<]*)\<\/option\>#is", '$1', $content);
	$content = preg_replace("#\<option([^\>]*)\>([^\<]*)\<\/option\>#is", '', $content);
	$content = preg_replace("#\<input[^\>]*type=\"hidden\"[^\>]*>#is", '', $content);
	$content = preg_replace("#\<select[^\>]*\>([^\<]*)\<\/select\>#is", '<span class="text">$1</span>', $content);

	$content = str_ireplace(array('<span class="text">Select State</span>', '<span class="text">Select</span>'), '<span class="text"> </span>', $content);
	$content = str_ireplace(array('type="email"', 'type="search"'), 'type="text"', $content);
	$content = str_replace('<textarea', '<div class="textarea text" ', $content);
	$content = str_replace('</textarea>', '</div>', $content);

	echo '<html>';
	echo '<head>';
	echo '<meta name="viewport" content="width=device-width,initial-scale=1.0">';
	echo '<style></style>';
	echo '</head>';
	echo '<body class="print">';
	echo $content;
	echo '</body>';
	echo '</html>';
	exit;
}

function tradmin_action_ci_update_assen_batch_id()
{
	global $wpdb;

	$_POST = tr_pdd_stripslashes_deep($_POST);

	$user_id = get_current_user_id();
	$batch_id = $_POST['batch_id'];
	$assen = $_POST['assen'];
	$to_msh = @$_POST['to_msh'];

	$today = date('Y-m-d', time() + (get_option('gmt_offset') * HOUR_IN_SECONDS));


	if ($to_msh == '1') {
	} else if (empty($batch_id) || $batch_id == 0) {
		Tr_Session_Class::add_error('Please enter batch number');
		return;
	} else {
		update_option('tr_new_batch_id', $batch_id);
		update_option('tr_new_batch_date', $today);
	}

	if (empty($assen)) {
		Tr_Session_Class::add_error('Please enter assenssion number');
		return;
	}

	$btype = 'tox';
	$table = 'ci_batches';
	$batch = false;

	foreach (tr_pdd_get_batch_types() as $btype) {
		$table = tr_pdd_get_table_by_btype($btype);

		$batch = $wpdb->get_row("select * from {$table} where assen='{$assen}' and batch_id = 0", ARRAY_A);

		if ($batch) {
			break;
		}
	}


	if (!$batch) {
		Tr_Session_Class::add_error('Not found this Accession number ' . $assen . ' in Batch 0');
	} else {

		$data = array(
			'batch_id' => $batch_id
		);

		if (empty($batch['spec_received']) || strtotime($batch['spec_received']) < 10000) {
			//$data['spec_received'] = Tr_Base_Class_V6::date('Y-m-d H:i:s');
		}

		if ($batch['lab_id'] > 0 && $batch['lab_id'] != CI_Base_Lab::get_current_lab_id()) {
			$data['ref_lab_ID'] = CI_Base_Lab::get_current_lab_id();
		}

		//check doc profile
		if ($btype == 'tox' && $batch['custom_profile'] == 'Use Custom Profile') {
			$data['panel_tests_requested'] = trpdd_panel_tests_requested($batch, $batch['doc_ID'], $batch['cli_ID']);
		}

		$wpdb->update($table, $data, array('assen' => $assen, 'batch_id' => 0));

		if ($batch['batch_id'] > 0) {
			Tr_Session_Class::add("Changed Accession #{$assen} from Batch #{$batch['batch_id']} to Batch #{$batch_id}");
		} else {

			Tr_Session_Class::add("Added Accession #{$assen} to Batch #{$batch_id}");
		}

		trpdd_log_user($user_id, 'move_batch', 'Move Accession', $batch['mid'], $btype, '', '', $assen);
	}
	trpdd_log_form('ci_update_assen_batch_id');
}

function tradmin_action_ci_move_assen_batch_id()
{
	global $wpdb;
	$_POST = tr_pdd_stripslashes_deep($_POST);

	$old_batch_id = isset($_POST['old_batch_id']) ? $_POST['old_batch_id'] : '';
	$new_batch_id = $_POST['new_batch_id'];
	$assen = trim($_POST['assen']);


	if ($new_batch_id == '') {
		Tr_Session_Class::add_error('Please enter new batch number');
		return;
	}

	if ($new_batch_id == $old_batch_id) {
		Tr_Session_Class::add_error('Please enter new batch number not same old batch number');
		return;
	}

	if (empty($assen)) {
		Tr_Session_Class::add_error('Please enter assenssion number');
		return;
	}

	$table = '';
	$btype = 'tox';
	$batch = new stdClass();
	foreach (tr_pdd_get_batch_types() as $btype) {
		$table = tr_pdd_get_table_by_btype($btype);
		$batch = $wpdb->get_row("select * from {$table} where assen='{$assen}' ");
		if ($batch) {
			break;
		}
	}



	if (!$batch) {
		Tr_Session_Class::add_error("Accession number: {$assen} not found ");
	} else {
		$old_batch_id = $batch->batch_id;
		$data = array(
			'batch_id' => $new_batch_id,
		);

		if ($batch->batch_type == 'E') {

			if (tr_get_option('ci_pdf_data', 'receiving_offtime')) {
				$data['spec_received'] = Tr_Base_Class_V6::date('Y-m-d');
			} else {
				$data['spec_received'] = Tr_Base_Class_V6::date('Y-m-d H:i:s');
			}
		}

		$wpdb->update($table, $data, array('assen' => $assen));

		Tr_Session_Class::add("Moved Accession #{$assen} from Batch #{$old_batch_id} to Batch #{$new_batch_id}");

		trpdd_log_user(get_current_user_id(), 'move_batch', "Moved Accession #{$assen} from Batch #{$old_batch_id} to Batch #{$new_batch_id}", $batch->mid, $btype, '', '', $assen);
	}
	trpdd_log_form('ci_move_assen_batch_id');
}

function tradmin_action_ci_add_to_current_batch()
{
	global $wpdb;

	$mid 		= $_REQUEST['mid'];
	$return 	= array('status' => 'ok', 'mid' => $mid);
	$ref_lab 	= @$_REQUEST['ref_lab'];

	list($table, $mid, $btype) = tr_pdd_get_table_mid_by_id($mid);

	$batch = $wpdb->get_row("select * from {$table} where mid = {$mid}");

	$today 			= date('Y-m-d', time() + (get_option('gmt_offset') * HOUR_IN_SECONDS));

	$new_batch_id 	= date("Ymd", strtotime($today));

	if (isset($_REQUEST['batch_id'])) {
		$new_batch_id = $_REQUEST['batch_id'];
	}
	$data = array(
		'batch_id' => $new_batch_id
	);
	if (isset($_REQUEST['position'])) {
		$data['position'] = $_REQUEST['position'];
	}

	if (isset($ref_lab['lab_ID'])) {
		$data['ref_lab_ID'] = $ref_lab['lab_ID'];
	}
	if ($batch->batch_type == 'E') {
		$data['spec_received'] = Tr_Base_Class_V6::date('Y-m-d H:i:s');
	}
	$wpdb->update($table, $data, array('mid' => $mid));

	$alert = "Moved Accession #{$batch->assen} from Batch #{$batch->batch_id} to Batch #{$new_batch_id}";
	//Tr_Session_Class::add($alert);

	$batch = $wpdb->get_row("select ba.ref_lab_ID,ba.position,ref.lab_dba_init from {$table} ba
                            Left JOIN ci_labs ref ON ref.lab_ID = ba.ref_lab_ID
                            where mid = {$mid}");

	$return['ref_lab_ID'] = $batch->ref_lab_ID;
	$return['ref'] 		= $batch->lab_dba_init;
	$return['position'] = $batch->position;
	$return['batch_id'] = $new_batch_id;
	$return['alert'] = '<div class="updated success"><p>' . $alert . '</p></div>';
	wp_send_json($return);
	exit;
}

function tradmin_action_ci_save_prior_authorization()
{
	global $wpdb;
	$_POST = tr_pdd_stripslashes_deep($_POST);

	$pri = $_POST['pri'];
	$lab = array();


	if (!is_array($pri['pri_cpt'])) $pri['pri_cpt'] = array();
	$pri['pri_cpt'] = implode('|', $pri['pri_cpt']);

	if (!is_array($pri['pri_diagnosis'])) $pri['pri_diagnosis'] = array();
	$pri['pri_diagnosis'] = implode('|', $pri['pri_diagnosis']);

	$wpdb->insert('ci_priors', $pri);
	$pri_id = $wpdb->insert_id;

	trpdd_log_form('ci_save_prior_authorization');

	if ($pri_id > 0) {
		//send email
		ob_start();
		include(TRCPDD_PATH . 'pages/email_prior_authoriztion.php');
		$content = ob_get_clean();
		$to = $pri['pri_plan_email'] . ',cornel@urbanverve.com';
		$subject = 'Prior Authorization';

		$blogname = $lab['lab_dba'];
		if (empty($blogname)) {
			$blogname = get_option('blogname');
		}
		$blogname = wp_specialchars_decode($blogname, ENT_QUOTES);

		$headers = '';
		if (is_email($lab['lab_email'])) {
			$headers = 'From: ' . $blogname . ' <' . $lab['lab_email'] . '>' . "\r\n";
			$headers .= 'Reply-To: ' . $lab['lab_email'] . "\r\n";
		}

		$headers .= "MIME-Version: 1.0" . "\r\n";
		$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

		$rs = wp_mail($to, $subject, $content, $headers);
		if (!$rs) {
			Tr_Session_Class::add_error('Error while send email');
		}


		Tr_Session_Class::add("Added Prior #{$pri_id} Accession " . $pri['pri_assen']);
		$redirect = Tr_Base_Class_V6::link(array('tab' => '', 'mid' => ''), false);
		wp_redirect($redirect);
		exit;
	} else {
		Tr_Session_Class::add_error("Error while insert Prior");
	}
}

function tradmin_action_ci_results_approve()
{
	global $wpdb;
	$id = $_REQUEST['id'];
	$report = $wpdb->get_row("select re.*,
	                            ba.mid, ba.batch_status, ba.assen
	                            from ci_reports re 
	                            join ci_batches ba ON ba.assen = re.patient_id
	                            where re.id={$id}", ARRAY_A);

	$status = 'C';
	$now = date_i18n('Y-m-d H:i:s');
	if ($report['estatus'] != 'C') {
		$wpdb->update('ci_reports', array(
			'estatus' => $status,
			'approve_date' => $now,
			'modified' => time()
		), array('id' => $id));
	}



	$rejected_statuses = tr_pdd_get_batch_statuses(false, true, true, 'tox');
	if (!isset($rejected_statuses[$report['batch_status']])) {
		$wpdb->update('ci_batches', array('batch_status' => 'completed'), array('mid' => $report['mid']));
	}


	//export pdf file
	if ($status == 'C') {
		trpdd_log_user(get_current_user_id(), 'approve_rs', $report['assen'], $report['mid'], 'tox', '', '', $report['assen']);

		trpdd_add_cron_after_approve('tox');

		trpdd_trigger_approve_report($report, 'tox');
	}


	Tr_Session_Class::add("Approved Result #" . $id);
	$url = Tr_Base_Class_V6::link(array('tr_action' => '', 'id' => ''), false);
	wp_redirect($url);
	exit;
}
function tradmin_action_ci_results_reverse()
{
	global $wpdb;
	$id = $_REQUEST['id'];
	$report = $wpdb->get_row("select re.*, 
	                            ba.mid, ba.batch_status
	                        from ci_reports re
	                        join ci_batches ba On ba.assen = re.patient_id
	                        where re.id={$id}", ARRAY_A);

	$wpdb->update('ci_reports', array('estatus' => 'M'), array('id' => $id));

	if ($report['batch_status'] == 'completed') {
		$wpdb->update('ci_batches', array('batch_status' => 'resulted'), array('mid' => $report['mid']));
	}

	trpdd_log_user(get_current_user_id(), 'reverse_rs', '', $report['mid'], 'tox', '', '', $report['patient_id']);

	Tr_Session_Class::add("Reverse Result #" . $id);
	$url = Tr_Base_Class_V6::link(array('tr_action' => '', 'id' => ''), false);
	wp_redirect($url);
	exit;
}


function tradmin_action_ci_results_delete()
{
	global $wpdb;
	$id = $_REQUEST['id'];
	$btype = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'tox';
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);
	$result_tbl = tr_pdd_get_table_result_by_btype($btype);
	$wpdb->delete($report_tbl, array('id' => $id));
	$wpdb->delete($result_tbl, array('report_id' => $id));

	Tr_Session_Class::add("Deteled Result #" . $id);
	$url = Tr_Base_Class_V6::link(array('tr_action' => '', 'id' => ''), false);
	wp_redirect($url);
	exit;
}

function tradmin_action_ci_results_pdf()
{
	global $wpdb, $pdf_has_report;
	global $ci_pdf_filename;
	$pdf_has_report = true;
	$_GET['viewpdf'] = true;
	$ci_pdf_filename = '';

	$type 	= isset($_REQUEST['sum']) ? 'sum' : (isset($_REQUEST['style2']) ? 'style2' : '');
	$btype 	= isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'tox';
	$print 	= isset($_REQUEST['print']) ? 1 : 0;
	$test_result = $print || (isset($_REQUEST['pdf']) && $_REQUEST['pdf'] == 'test_results') ? true : false;
	$id 	= isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;
	$mid 	= isset($_REQUEST['mid']) ? $_REQUEST['mid'] : 0;
	$start_time = microtime(true);

	if ($id == 0 && $mid == 0 && isset($_REQUEST['file'])) {
		list($btype, $id) = explode('_', $_REQUEST['file']);
		$id = str_replace('.pdf', '', $id);
		$id = trpdd_patient_decode($id);
	}
	$report = array();
	$table_report = tr_pdd_get_table_report_by_btype($btype);

	if ($id == 0 && $mid == 0 && !empty($_REQUEST['assen'])) {
		$id = $wpdb->get_var("select id from {$table_report} where patient_id = '{$_REQUEST['assen']}' ");
	}


	if (!empty($mid)) {
		$table = tr_pdd_get_table_by_btype($btype);
		$report = $wpdb->get_row("select ba.*, re.id from {$table} ba
                            left JOIN {$table_report} re  ON ba.assen = re.patient_id
                            where ba.mid = {$mid} ", ARRAY_A);

		if ($report['id'] == 0 && ($report['batch_status'] == 'rejected' || stripos($report['batch_status'], 're_') === 0) && !empty($report['assen'])) {
			$wpdb->insert(
				$table_report,
				array(
					'patient_id' => $report['assen'],
					'batch_id' => $report['batch_id'],
					'created' => date_i18n('Y-m-d H:i:s'),
					'created_date' => date_i18n('Y-m-d'),
					'lab_id' => $report['lab_id']
				)
			);
			$report['id'] = $wpdb->insert_id;
		}

		$id = $report['id'];
	}
	$assen_log = !empty($report['assen']) ? $report['assen'] : $wpdb->get_var("select patient_id from {$table_report} where id = {$id} ");
	trpdd_log_user(get_current_user_id(), 'download_rs', strtoupper($btype) . ' Results PDF', $mid, $btype, '', '',  $assen_log);
	$outfile = trpdd_export_results_pdf($id, $type, $print, $btype);


	if (!$outfile && $report['estatus'] != 'C') {
		echo 'Error. Please try again later!';
		exit;
	}

	if (isset($_GET['dbtime'])) {
		global $ci_time_build_pdf, $ci_time_query_pdf, $db_time_log;
		var_dump($db_time_log);
		echo 'query time: ' . $ci_time_query_pdf . '<br>';
		echo 'build pdf time: ' . $ci_time_build_pdf . '<br>';
		echo 'pdf version: ' . TRCDPDF_V . '<br>';
		echo 'pdf #2?: ' . (TRCDPDF_NEW ? 1 : 0) . '<br>';
		echo 'PDF size: ' . filesize($outfile) . '<br>';
		echo 'total: ';
		echo microtime(true) - $start_time;
		exit;
	}
	//var_dump($outfile);exit;
	if (!isset($_GET['debuga'])) {
		header('Content-Type: application/pdf');
	}

	if (!$print) {
		if (empty($ci_pdf_filename)) {
			$ci_pdf_filename = $outfile;
		}
		if (!isset($_GET['view'])) {
			header('Content-Disposition: attachment; filename="' . basename($ci_pdf_filename) . '"');
		} else {
			ob_start("ob_gzhandler");
			header('Content-Disposition: ; filename="' . basename($ci_pdf_filename) . '"');
		}
	} else {
		header('Content-Disposition: ; filename="' . basename($ci_pdf_filename) . '"');
	}

	if ($test_result) {
		$user_id = get_current_user_id();
		$users = $wpdb->get_var("select users_printed from {$table_report} where id = {$id} ");
		$users = explode(',', $users);
		if (!in_array($user_id, $users)) {
			$users[] = $user_id;
		}
		$users = implode(',', $users);
		$wpdb->update($table_report, array('users_printed' => $users), array('id' => $id));
	}

	@readfile($outfile);
	if (!$pdf_has_report && stripos($outfile, '/uploads/') !== false) {
		unlink($outfile);
	}

	exit;
}



function tradmin_action_test_email_user()
{
	$user_id = $_REQUEST['user_id'];
	$pass = 'abc';
	$rs = trpdd_send_mail_create_user($user_id, $pass);
	var_dump($rs);
	exit;
}

function tradmin_action_ci_change_batch_status()
{
	global $wpdb;
	$id = $_REQUEST['id'];
	$btype = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'tox';
	$status = $_REQUEST['status'];
	if (strpos($id, '_') > 0) {
		list($btype, $id) = explode('_', $id);
	}

	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$return = array('status' => 'ok', 'msg' => 'Changed Status Successfully');

	$data = $wpdb->get_row("select assen,batch_status 
	from {$batch_tbl} where mid = '{$id}'", ARRAY_A);
	$assen = $data['assen'];
	$can_change = true;
	if ($status == 'completed') {
		//check result
		$report_tbl = tr_pdd_get_table_report_by_btype($btype);
		$estatus = $wpdb->get_var("select estatus from {$report_tbl} where patient_id = '{$assen}' ");
		if ($estatus != 'C') {
			$can_change = false;
		}
	}
	if ($can_change) {
		if ($status == 'hold' && $data['batch_status'] != $status) {
			trpdd_update_batch_meta($id, $btype, 'hold_batch_status', $data['batch_status']);
		}
		$data['old'] = $data;
		$data['batch_status'] = $status;
		$wpdb->update($batch_tbl, array('batch_status' => $status), array('mid' => $id));


		trpdd_log_user(get_current_user_id(), 'change_status', $data, $id, $btype, '', '', $assen);
	} else {
		$return['status'] = 'error';
		$return['msg'] = 'You cant change status to completed because result is not approved yet.';
		$return['old_status'] = $data['batch_status'];
	}


	wp_send_json($return);
	exit;
}

function tradmin_action_ci_change_group_batch_status()
{
	global $wpdb;
	$batch_id = $_REQUEST['batch_id'];
	$status = $_REQUEST['status'];
	$user_id = get_current_user_id();

	$return = array('status' => 'ok', 'msg' => 'Changed Status Successfully');

	$bats = $wpdb->get_results("select ba.mid,ba.batch_status from ci_tecan te 
                    JOIN ci_batches ba ON ba.assen = te.te_assen
                    WHERE ba.batch_id = '{$batch_id}'  ");
	if ($bats && !empty($batch_id)) {
		$notes = $wpdb->get_var("select bn_bid from ci_batches_notes WHERE bn_bid='{$batch_id}'");
		if ($notes) {
			$wpdb->update('ci_batches_notes', array(
				'bn_user' => $user_id,
				'bn_status_changed' => Tr_Base_Class_V6::date('Y-m-d H:i:s')
			), array('bn_bid' => $batch_id));
		} else {
			$wpdb->insert(
				'ci_batches_notes',
				array(
					'bn_bid' => $batch_id,
					'bn_status_changed' => Tr_Base_Class_V6::date('Y-m-d H:i:s'),
					'bn_created' => Tr_Base_Class_V6::date('Y-m-d H:i:s'),
					'bn_user' => $user_id
				)
			);
		}

		foreach ($bats as $ba) {
			$wpdb->update('ci_batches', array('batch_status' => $status), array('mid' => $ba->mid));
		}
	}
	wp_send_json($return);
	exit;
}

function tradmin_action_ci_delete_collector()
{
	$id = $_REQUEST['id'];
	$redirect = Tr_Base_Class_V6::link(array('act' => 'list', 'id' => ''), false);
	global $wpdb;
	$wpdb->query("delete from ci_collector where col_ID = {$id}");
	Tr_Session_Class::add("Deleted Collector #" . $id);
	wp_redirect($redirect);
	exit;
}

function tradmin_action_ci_delete_cbatch()
{
	global $wpdb;
	$id = $_REQUEST['id'];
	list($table, $id, $btype) = tr_pdd_get_table_mid_by_id($id);
	$bat = $wpdb->get_row("select ba.*,lab.lab_email 
	                        from {$table} ba 
	                        left JOIN ci_labs lab On lab.lab_ID = ba.lab_id
	                         where ba.mid = {$id}");
	if (!$bat) return;
	trpdd_delete_batch($id, $btype);
	Tr_Session_Class::add("Deleted #" . $id . '". Accession: ' . $bat->assen);
	$redirect = Tr_Base_Class_V6::link(array('act' => '', 'id' => ''), false);

	wp_redirect($redirect);
	exit;
}

function tradmin_action_payer_list_install()
{
	global $wpdb;
	$list = array();
	include(TRCPDD_PATH . 'inc/payer_list.php');
	foreach ($list as $vl) {
		$vl = html_entity_decode($vl);
		$data = array(
			'payer_name' => $vl,
		);
		$wpdb->insert("ci_payer_list", $data);
	}
	echo 'ok';
	exit;
}

function tradmin_action_script_import()
{
	include(TRCPDD_PATH . 'inc/import.php');
	exit;
}

function tradmin_action_save_position_batch()
{
	global $wpdb;
	$_POST = tr_pdd_stripslashes_deep($_POST);

	$mid = $_POST['mid'];
	$position = $_POST['position'];
	if ($mid > 0 && isset($position)) {
		$wpdb->update('ci_batches', array('position' => $position), array('mid' => $mid));
		echo 'ok';
		exit;
	}
	exit;
}

function tradmin_action_save_quick_edit_batch()
{
	global $wpdb;
	$return = array('status' => 'error');
	$_POST = tr_pdd_stripslashes_deep($_POST);

	$mid = $_REQUEST['mid'];
	$table = 'ci_batches';

	foreach (tr_pdd_get_batch_types() as $btype) {
		$typekey = $btype . '_';
		if (stripos($mid, $typekey) !== false) {
			$table = tr_pdd_get_table_by_btype($btype);
			$mid = str_replace($typekey, '', $mid);
			break;
		}
	}


	if ($mid > 0) {
		$bat = $wpdb->get_row("select * from {$table} where mid={$mid}");
		$update = array();
		$pa = array();

		$update['position'] = $_POST['position'];
		if (isset($_POST['dob'])) {
			$pa['pa_dob'] = tr_format_date_default($_POST['dob'], 'Y-m-d');
			$update['dob'] = $pa['pa_dob'];
		}

		if (isset($_POST['pa_first'])) {
			$pa['pa_first'] = tr_trim_name($_POST['pa_first']);
		}
		if (isset($_POST['pa_last'])) {
			$pa['pa_last'] = tr_trim_name($_POST['pa_last']);
			$update['patient_name'] = tr_trim_name($pa['pa_last'] . ', ' . $pa['pa_first']);
		}

		if (isset($_POST['current_medication'])) {
			$update['current_medication'] = ucwords($_POST['current_medication']);
			$update['current_medication'] = tr_trim_name($update['current_medication']);
		}

		if (isset($_POST['ref_lab']) && isset($_POST['ref_lab']['lab_ID'])) {
			$update['ref_lab_ID'] = $_POST['ref_lab']['lab_ID'];
		}


		if (count($pa) > 0) {
			if ($bat->patient_id > 0) {
				$sql = "select * from ci_patients where pa_ID = {$bat->patient_id} ";
				if ($mid == 0 && !empty($pa['pa_dob'])) {
					//insert if dob defferent
					$sql .= " and pa_dob='{$pa['pa_dob']}' ";
				}
				$patient = $wpdb->get_row($sql);


				$pa['pa_first'] = trim($pa['pa_first']);
				$pa['pa_last'] = trim($pa['pa_last']);

				//check patient exist
				if ($patient && !empty($pa['pa_last'])) {
					$patient->pa_first = trim($patient->pa_first);
					$patient->pa_last = trim($patient->pa_last);

					if (strtolower($patient->pa_first) != strtolower($pa['pa_first']) && strtolower($patient->pa_last) != strtolower($pa['pa_last'])) {
						$count_b_patient = $wpdb->get_var("select count(mid) from {$table} where patient_id = {$patient->pa_ID} and mid != '{$mid}'");
						if ($count_b_patient > 0) {
							$bat->patient_id = 0;
						}
					}
				}
			}
			if ($bat->patient_id > 0) {
				trpdd_update_patient($pa, $bat->patient_id);
			} else {
				$pa['pa_clinic_ID'] = $bat->cli_ID;
				$pa['pa_created'] = date_i18n('Y-m-d H:i:s');
				$pa['pa_created_by'] = get_current_user_id();

				$wpdb->insert('ci_patients', $pa);
				$pa_id = $wpdb->insert_id;
				$update['patient_id'] = $pa_id;
			}
		}

		$wpdb->update($table, $update, array('mid' => $mid));

		$return['status'] = 'ok';
		$return['cols'] = $update;
		$return['values'] = $_POST;


		trpdd_log_form('quick_edit_batch');

		wp_send_json($return);
	}
	exit;
}

function tradmin_action_ci_print_clinic()
{
	$id = $_REQUEST['id'];
	$_REQUEST['clinic_id'] = $id;
	$_REQUEST['act'] = 'print';
	echo '<div style="padding:10px">';
	include(trpdd_get_file_path('accountforms', 'new_account_form'));
	echo '</div>';
	exit;
}

function tradmin_action_ci_print_doctor_custom()
{
	$doc_id = $_REQUEST['doc_id'] = $_REQUEST['id'];
	$_REQUEST['act'] = 'print';
	include(trpdd_get_file_path('accountforms', 'custom_provider'));
	exit;
}

function tradmin_action_test_today_batch()
{
	$log = tr_pdd_ci_today_batch_cron(true);
	echo str_replace("\n", "<br>", $log);
	exit;
}

function tradmin_action_ci_save_result_edit()
{
	include(TRCPDD_PATH . 'inc/actions/ci_save_result_edit.php');
}

function tradmin_action_ci_export_req_pdf()
{
	ob_start();
	$outfile = '';
	include(TRCPDD_PATH . 'pages/admin_ci_export_req_html.php');
	$content = ob_get_clean();
	$content = str_replace('></td>', '>&nbsp;</td>', $content);
	if (isset($_REQUEST['html'])) {
		echo $content;
		exit;
	}
	$filetmp = tempnam("/tmp", "export" . time()) . '.html';
	file_put_contents($filetmp, $content);
	trpdd_html_to_pdf($filetmp, null, $outfile);

	unlink($filetmp);
	exit;
}

function tradmin_action_ci_export_req_all_pdf()
{
	$type = $_REQUEST['type'];
	include(TRCPDD_PATH . 'pages/admin_ci_export_req_all_pdf.php');
	exit;
}

function tradmin_action_ci_upload_result_pdf_to_box()
{
	include(TRCPDD_PATH . 'pages/admin_ci_upload_result_pdf_to_box.php');
	exit;
}

function tradmin_action_ci_result_show_note()
{
	include(TRCPDD_PATH . 'pages/admin_ci_result_show_note.php');
	exit;
}

function tradmin_action_ci_upload_specimen_issue_photo()
{
	include(TRCPDD_PATH . 'inc/actions/ci_upload_specimen_issue_photo.php');
	exit;
}

function tradmin_action_ci_show_doctor_new_form()
{
	include(TRCPDD_PATH . 'inc/actions/ci_show_doctor_new_form.php');
	exit;
}
function tradmin_action_ci_save_doc_mnemonic()
{
	global $wpdb;
	$doc_id = $_REQUEST['doc_id'];
	$value  = $_REQUEST['value'];
	$wpdb->update('ci_doc', array('doc_mnemonic' => $value), array('doc_ID' => $doc_id));
	//update hl7sent for batch
	$wpdb->query("update ci_batches set hl7sent = 0 where doc_ID = {$doc_id} and batch_id BETWEEN 20151111 AND curdate()");

	$return = array('status' => 'ok', 'value' => $value);
	wp_send_json($return);
	exit;
}

function tradmin_action_ci_load_results()
{
	include(TRCPDD_PATH . 'inc/actions/ci_load_results.php');
	exit;
}

function tradmin_action_ci_load_results_pending()
{
	include(TRCPDD_PATH . 'inc/actions/ci_load_results_pending.php');
	exit;
}

function tradmin_action_ci_menu_tree()
{
	include(TRCPDD_PATH . 'inc/actions/ci_menu_tree.php');
	exit;
}

function tradmin_action_ci_save_qc_value()
{
	include('actions/ci_save_qc_value.php');
}

function tradmin_action_ci_save_qc_lot_value()
{
	global $wpdb;

	$_POST = tr_pdd_stripslashes_deep($_POST);

	$id = $_POST['id'];
	$value = $_POST['value'];
	$instrument = isset($_REQUEST['instrument']) ? $_REQUEST['instrument'] : '';
	$where = array(
		'rs_instrument' => $instrument
	);
	$wpdb->update('ci_results_start', array('rs_lot' => $value), $where);
	wp_send_json(array('status' => 'ok'));
	exit;
}

function tradmin_action_ci_save_qc_expiration_date()
{
	global $wpdb;

	$_POST = tr_pdd_stripslashes_deep($_POST);

	$bid 	= $_POST['bid'];
	$value 	= $_POST['value'];
	$instrument = isset($_REQUEST['instrument']) ? $_REQUEST['instrument'] : '';

	if (stripos($bid, 'start') !== false) {
		$where = array(
			'rs_instrument' => $instrument
		);
		$wpdb->update('ci_results_start', array('rs_expiration_date' => $value), $where);
	} else {
		$where = array(
			'batch_id' => $bid,
		);
		if (CI_Base_Lab::has_user_labs()) {
			$where['lab_id'] = CI_Base_Lab::get_user_lab_ids();
		}

		$wpdb->update('ci_reports', array('expiration_date' => $value), $where);
	}

	wp_send_json(array('status' => 'ok'));
	exit;
}

function tradmin_action_qrcode()
{
	include('actions/qrcode.php');
}


function tradmin_action_show_qrcode()
{
	include('actions/show_qrcode.php');
}

function tradmin_action_write_img_chart_pdf()
{
	$count = intval($_REQUEST['count']);
	for ($i = 0; $i < $count; $i++) {

		$txtid = 'imgData' . $i;
		$imageData = $_REQUEST[$txtid];
		$filteredData = substr($imageData, strpos($imageData, ",") + 1);
		$unencodedData = base64_decode($filteredData);
		$file_name = $i . '.png';
		$file_exp = WP_CONTENT_DIR . '/results/' . $file_name;
		$fp = fopen($file_exp, 'wb');
		fwrite($fp, $unencodedData);
		fclose($fp);
	}


	$html = '<html>
            <head>
            <style>
                img{
                    width:50%;
                 
                    /*-ms-transform: rotate(270deg);
                    -webkit-transform: rotate(270deg);
                    transform: rotate(270deg); */
                }
            </style>
            </head>
            <body>';
	$count_row = 0;
	for ($i = 0; $i < $count; $i++) {
		$count_row++;
		$html .= '<img src="' . get_home_url() . '/wp-content/results/' . $i . '.png"/>';
		if ($count_row >= 2) {
			$html .= '<span style="break-after: always;">';
			$count_row = 0;
		}
	}

	$html .= '</body></html>';


	$file_location = WP_CONTENT_DIR . '/results/export_result.pdf';
	trpdd_build_pdf($html, array('filepath' => $file_location));
	echo 'Export sucess!';
	exit;
}

function tradmin_action_ci_show_batch_result_miss()
{
	include(TRCPDD_PATH . 'inc/actions/ci_show_batch_result_miss.php');
	exit;
}

function tradmin_action_ci_show_batch_result_files()
{
	include(TRCPDD_PATH . 'inc/actions/ci_show_batch_result_file.php');
	exit;
}

function tradmin_action_ci_show_batch_result_tecans()
{
	include(TRCPDD_PATH . 'inc/actions/ci_show_batch_result_tecans.php');
	exit;
}

function tradmin_action_ci_results_bulk_edit()
{
	include(TRCPDD_PATH . 'inc/actions/ci_results_bulk_edit.php');
	exit;
}
function tradmin_action_ci_results_reverse_all()
{
	global $wpdb;
	$ids = trim($_REQUEST['ids'], ",");
	$ids = explode(',', $ids);
?>
	<script>
		jQuery(function($) {
			<?php
			foreach ($ids as $id) {
				$report = $wpdb->get_row("select re.*,ba.mid,ba.batch_status 
	                                    join ci_batches ba ON ba.assen = re.patient_id
	                                    from ci_reports re where re.id={$id}", ARRAY_A);

				trpdd_log_user(get_current_user_id(), 'reverse_rs', '', $report['mid'], 'tox', '', '', $report['patient_id']);

				$wpdb->update('ci_reports', array('estatus' => 'M'), array('id' => $id));

				if ($report['batch_status'] == 'completed') {
					$wpdb->update('ci_batches', array('batch_status' => 'resulted'), array('mid' => $report['mid']));
				}
			?>
				var $row = $('[data-id="<?php echo $id ?>"]');
				$('.reverse_link', $row).after('<a href="#" class="edit_link">Edit</a>');
				$('.reverse_link', $row).remove()
				$('.pdf_link', $row).after('<a href="#" class="approve_link">Approve</a>');
				$('.pdf_link', $row).remove();
				$('.result_C', $row).html('M').addClass('result_M').removeClass('result_C');
			<?php
			}
			?>
		});
	</script>
	<h3>Reversed Successfully</h3>
	<div class="center">
		<a class="button" onclick="tb_remove();">Close</a>
	</div>
<?php
	exit;
}

function tradmin_action_ci_results_approve_all()
{
	global $wpdb;
	$lab_id = $_REQUEST['lab_id'];
	$batch_id = $_REQUEST['batch_id'];
	$ids = trim($_REQUEST['ids'], ",");
	$lab = $wpdb->get_row("select * from ci_labs where lab_ID = {$lab_id}");

	$sql = " select re.id,ba.mid,ba.batch_status, ba.assen
        from ci_reports re
        JOIN ci_batches ba ON ba.assen = re.patient_id
        JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
        where lab.lab_ID={$lab_id} and (re.batch_id = '{$batch_id}' or ba.batch_id = '{$batch_id}')";
	if (!empty($ids)) {
		$sql .= " and re.id in ({$ids})";
	}
	$reports = $wpdb->get_results($sql, ARRAY_A);

	$now = Tr_Base_Class_V6::date('Y-m-d H:i:s');
	foreach ($reports as $report) {
		trpdd_log_user(get_current_user_id(), 'approve_rs', $report['assen'], $report['mid'], 'tox', '', '', $report['assen']);

		$wpdb->update('ci_reports', array('estatus' => 'C', 'approve_date' => $now, 'modified' => time()), array('id' => $report['id']));


		$rejected_statuses = tr_pdd_get_batch_statuses(false, true, true, 'tox');
		if (!isset($rejected_statuses[$report['batch_status']])) {
			$wpdb->update('ci_batches', array('batch_status' => 'completed'), array('mid' => $report['mid']));
		}
		//trpdd_export_results_pdf($re->id);
	}

	Tr_Session_Class::add("Approved Result on lab: " . $lab->lab_dba_init . ' - batch: ' . $batch_id);

	trpdd_add_cron_after_approve('tox');

	$url = Tr_Base_Class_V6::link(array('tr_action' => '', 'id' => '', 'batch_id' => '', 'lab_id' => '', 'm' => '1', 'ids' => ''), false);
	wp_redirect($url);
	exit;
}

function tradmin_action_ci_results_delete_all()
{
	global $wpdb;
	$lab_id = $_REQUEST['lab_id'];
	$batch_id = $_REQUEST['batch_id'];
	$ids = trim($_REQUEST['ids'], ",");

	$lab = $wpdb->get_row("select * from ci_labs where lab_ID = {$lab_id}");
	$sql = "
        select re.id, ba.mid, ba.assen from ci_reports re
        JOIN ci_batches ba ON ba.assen = re.patient_id
        JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
        where re.estatus='C' ";
	if (!empty($ids)) {
		$sql .= " and re.id in ({$ids})";
	} else {
		$sql .= " lab.lab_ID={$lab_id} and ba.batch_id = '{$batch_id}' ";
	}
	$reports = $wpdb->get_results($sql);

	foreach ($reports as $re) {
		$id = $re->id;

		$wpdb->delete('ci_reports', array('estatus' => 'C', 'id' => $id));

		trpdd_log_user(get_current_user_id(), 'delete_rs', '', $re->mid, 'tox', '', '', $re->assen);
	}
	Tr_Session_Class::add("Deleted Results on lab: " . $lab->lab_dba_init . ' - batch: ' . $batch_id);
	$url = Tr_Base_Class_V6::link(array('tr_action' => '', 'id' => '', 'batch_id' => '', 'lab_id' => '', 'm' => '1', 'ids' => ''), false);
	wp_redirect($url);
	exit;
}

function tradmin_action_ci_search_barcode_receiving()
{
	include(TRCPDD_PATH . 'inc/actions/ci_search_barcode_receiving.php');
	exit;
}

function tradmin_action_ci_print_barcode_number()
{
	include(TRCPDD_PATH . 'inc/actions/ci_print_barcode_number.php');
	exit;
}

function tradmin_action_ci_load_tecans()
{
	include(TRCPDD_PATH . 'inc/actions/ci_load_tecans.php');
	exit;
}
function tradmin_action_ci_show_batch_tecan_files()
{
	include(TRCPDD_PATH . 'inc/actions/ci_show_batch_tecan_files.php');
	exit;
}

function tradmin_action_ci_get_report_perform_month()
{
	include(TRCPDD_PATH . 'pages/dashboard_get_report_perform_month.php');
	exit;
}

function tradmin_action_ci_get_report_perform_year()
{
	include(TRCPDD_PATH . 'pages/dashboard_get_report_perform_year.php');
	exit;
}

function tradmin_action_ci_load_test_results()
{
	include(TRCPDD_PATH . 'inc/actions/ci_load_test_results.php');
	exit;
}
function tradmin_action_ci_load_tecan_processing()
{
	include(TRCPDD_PATH . 'inc/actions/ci_load_tecan_processing.php');
	exit;
}
function tradmin_action_ci_batch_notes()
{
	include(TRCPDD_PATH . 'inc/actions/ci_batch_notes.php');
	exit;
}

function tradmin_action_print_jchar()
{
	require_once('jpgraph/jpgraph.php');
	require_once('jpgraph/jpgraph_line.php');
	require_once('jpgraph/jpgraph_date.php');
	include(TRCPDD_PATH . 'inc/actions/ci_print_jchar.php');
	exit;
}

function tradmin_action_ci_change_cli_status()
{
	global $wpdb;
	$id = $_REQUEST['id'];
	$status = $_REQUEST['status'];
	$return = array('status' => 'ok');

	$rs = $wpdb->update('ci_clinic', array('cli_status' => $status), array('cID' => $id));
	if ($rs === false) {
		$return['status'] = 'error';
		$return['msg'] = $wpdb->last_error;
	}

	wp_send_json($return);
	exit;
}

function tradmin_action_ci_change_col_status()
{
	global $wpdb;
	$id = $_REQUEST['id'];
	$status = $_REQUEST['status'];
	$return = array('status' => 'ok');

	$rs = $wpdb->update('ci_collector', array('col_status' => $status), array('col_ID' => $id));
	if ($rs === false) {
		$return['status'] = 'error';
		$return['msg'] = $wpdb->last_error;
	}

	wp_send_json($return);
	exit;
}

function tradmin_action_ci_load_customer_by_lab()
{
	include('actions/ci_load_customer_by_lab.php');
	exit;
}

function tradmin_action_ci_load_customer_orders()
{
	include('actions/ci_load_customer_orders.php');
	exit;
}

function tradmin_action_ci_save_customer_orders()
{
	include('actions/ci_save_customer_orders.php');
}

function tradmin_action_ci_save_blood()
{
	include_once(TRCPDD_PATH . 'inc/actions/ci_save_blood.php');
}
function tradmin_action_ci_save_req()
{
	include_once(TRCPDD_PATH . 'inc/actions/ci_save_req.php');
}

function tradmin_action_ci_save_dna()
{
	include_once(TRCPDD_PATH . 'inc/actions/ci_save_dna.php');
}

function tradmin_action_ci_save_allergy()
{
	include_once(TRCPDD_PATH . 'inc/actions/ci_save_allergy.php');
}

function tradmin_action_ci_load_results_upload()
{
	include(TRCPDD_PATH . 'inc/actions/ci_load_results_upload.php');
	exit;
}

function tradmin_action_ci_save_qc_undo_value()
{
	include('actions/ci_save_qc_undo_value.php');
}

function tradmin_action_ci_save_qc_delete_value()
{
	include('actions/ci_save_qc_delete_value.php');
}

function tradmin_action_ci_save_qc_comments()
{
	include('actions/ci_save_qc_comments.php');
}

function tradmin_action_ci_print_qc_pdf()
{
	include('actions/ci_print_qc_pdf.php');
}
function tradmin_action_ci_search_result_files()
{
	include('actions/ci_search_result_files.php');
}

function tradmin_action_ci_upload_qss_to_box()
{
	include('actions/ci_upload_qss_to_box.php');
	exit;
}

function tradmin_action_ci_upload_result_pdf_to_box_upload()
{
	include(TRCPDD_PATH . 'inc/actions/ci_upload_result_pdf_to_box_upload.php');
	exit;
}
function tradmin_action_ci_remove_pa_bill_notes()
{
	global $wpdb;
	$wpdb->update('ci_patients', array('pa_bill_notes' => ''), array('pa_ID' => $_REQUEST['id']));
	echo 'ok';
	exit;
}

function tradmin_action_ci_check_number_assen()
{
	include('actions/ci_check_number_assen.php');
}

function tradmin_action_ci_download_hos_result()
{
	include('actions/ci_download_hos_result.php');
}

function tradmin_action_ci_downloaded_hos_result()
{
	include('actions/ci_downloaded_hos_result.php');
}

function tradmin_action_ci_get_report_turnaround()
{
	include('actions/ci_get_report_turnaround.php');
	exit;
}

function tradmin_action_ci_get_ship_rates()
{
	include('actions/ci_get_ship_rates.php');
	exit;
}

function tradmin_action_ci_get_ship_label()
{
	include('actions/ci_get_ship_label.php');
	exit;
}

function tradmin_action_ci_generate_ups_ship()
{
	include('actions/ci_generate_ups_ship.php');
	exit;
}

function tradmin_action_ci_confirm_shipment_ups()
{
	include('actions/ci_confirm_shipment_ups.php');
	exit;
}

function tradmin_action_ci_req_send_email_pdfs()
{
	include(TRCPDD_PATH . 'inc/actions/ci_req_send_email_pdfs.php');
	exit;
}

function tradmin_action_ci_load_efax_setups()
{
	include(TRCPDD_PATH . 'inc/actions/ci_load_efax_setups.php');
	exit;
}

function tradmin_action_ci_print_barcodes()
{
	include(TRCPDD_PATH . 'inc/actions/ci_print_barcodes.php');
	exit;
}

function tradmin_action_ci_upload_result_pdf_to_efax()
{
	include(TRCPDD_PATH . 'inc/actions/ci_upload_result_pdf_to_efax.php');
	exit;
}

function tradmin_action_ci_upload_result_pdf_to_efax_upload()
{
	include(TRCPDD_PATH . 'inc/actions/ci_upload_result_pdf_to_efax_upload.php');
	exit;
}

function tradmin_action_ci_print_custom_provider()
{
	global $wpdb;
	$_REQUEST['act'] = 'print';
	include(TRCPDD_PATH . 'pages/admin_form_custom_provider.php');
	exit;
}
function tradmin_action_ci_check_clinic_exist()
{
	global $wpdb;
	$return = array('status' => 'ok');
	$sql = $wpdb->prepare("select cID from ci_clinic where cli_clinic =%s and cID != %s", $_REQUEST['clinic'], intval($_REQUEST['cID']));
	//  $return['sql'] = $sql;
	$exists = $wpdb->get_var($sql);
	if ($exists > 0) {
		$return['status'] = 'exist';
		$return['cID'] = $exists;
	}
	wp_send_json($return);
}

function tradmin_action_ci_print_reqs()
{
	include(TRCPDD_PATH . 'inc/actions/ci_print_reqs.php');
	exit;
}

function tradmin_action_ci_add_drugs_to_test()
{
	$drugs = $_REQUEST['drugs'];
	$drugs = explode(',', $drugs);
	$drugs = array_map('trim', $drugs);
	$olds = array_map('trim', $_REQUEST['olds']);

	foreach ($drugs as $drug) {
		if (empty($drug) || in_array($drug, $olds)) continue;
		$logic = array(
			'medication' => $drug
		);
		include(trpdd_get_file_path('panel', 'row_test_logic'));
	}
	exit;
}


function tradmin_action_get_all_bet()
{
	global $wpdb;

	$sql = "select DISTINCT(ba.batch_id) from ci_reports re
            JOIN ci_batches ba ON re.patient_id = ba.assen
            JOIN ci_results rs ON rs.report_id = re.id and rs.drug='METHAMPHETAMINE' and rs.`pos-neg`='POS'
            WHERE   ba.batch_id>0 and ba.type_of_specimen != 'oral'";


	if (CI_Base_Lab::has_user_labs()) {
		$lab_str = CI_Base_Lab::get_user_lab_ids_str();
		$sql .= " and re.lab_id in ( {$lab_str} ) ";
	}


	$sql .= " ORDER BY ba.batch_id DESC LIMIT 10";
	$batches = $wpdb->get_results($sql);
?>
	<form action="?tr_action=ci_export_batches&type=current&m=export_meth<?php if (isset($_GET['ms'])) echo '&ms' ?>" method="post">

		<div>
			<table class="hover_tbl" style="width:100%">
				<tr>
					<th>#</th>
					<th title="<?php echo $sql ?>">Batch Number</th>
				</tr>
				<?php
				foreach ($batches as $b) {
				?>
					<tr>
						<td><input checked="true" type="checkbox" name="idm[]" value="<?php echo $b->batch_id; ?>" /></td>
						<td><?php echo $b->batch_id; ?></td>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
		<input type="submit" class="button" value="Export" />
	</form>
<?php
	exit;
}

function tradmin_action_ci_test_opi()
{
	include('actions/test_opi.php');
	exit;
}

function tradmin_action_test_auto_upload_results()
{
	tr_pdd_cron_ci_auto_upload_results();
	exit;
}


function tradmin_action_ci_save_patient_ajax()
{
	include('actions/save_patient_ajax.php');
	exit;
}

function tradmin_action_ci_save_doctor_ajax()
{
	include('actions/save_doctor_ajax.php');
}

function tradmin_action_ci_print_result_summary()
{
	include('actions/print_result_summary.php');
	exit;
}

function tradmin_action_ci_change_urine_validity_status()
{
	global $wpdb;
	$id = $_REQUEST['id'];
	$status = $_REQUEST['status'];
	$return = array('status' => 'ok');
	$update = array('u_status' => $status);
	if ($status == '0') {
		$update['u_end'] = date_i18n('Y-m-d');
	} else {
		$update['u_end'] = '';
	}
	$rs = $wpdb->update('ci_urine_validity', $update, array('u_ID' => $id));
	if ($rs === false) {
		$return['status'] = 'error';
		$return['msg'] = $wpdb->last_error;
	}

	wp_send_json($return);
	exit;
}

function tradmin_action_ci_remove_receiving()
{
	global $wpdb;
	$return = array('status' => 'ok');
	$mid = $_REQUEST['mid'];
	$re_ID = $_REQUEST['re_ID'];

	list($table, $mid, $btype) = tr_pdd_get_table_mid_by_id($mid);
	$return['tbl'] = $table;
	$return['mid'] = $mid;

	$wpdb->query("delete from ci_receiving_batches where re_ID = {$re_ID} and mid = {$mid} and btype='{$btype}' ");
	$wpdb->query("update {$table} set batch_id=0,batch_status='',spec_received='' where mid = {$mid} and cli_ID = 0 ");
	wp_send_json($return);
}

function tradmin_action_ci_remove_plate()
{
	global $wpdb;
	$return = array('status' => 'ok');
	$mid = $_REQUEST['mid'];
	$pl_ID = $_REQUEST['pl_ID'];

	list($table, $mid, $btype) = tr_pdd_get_table_mid_by_id($mid);
	$return['tbl'] = $table;
	$return['mid'] = $mid;

	$wpdb->query("delete from ci_plates_batches where pl_ID = {$pl_ID} and mid = $mid ");
	$wpdb->query("update {$table} set batch_id=0,batch_status='',spec_received='' where mid = $mid ");
	wp_send_json($return);
}

function tradmin_action_ci_load_clinic_report_tests()
{
	include('actions/ci_load_clinic_report_tests.php');
	exit;
}

function tradmin_action_ci_update_has_ordered()
{
	global $wpdb;
	$last_id = get_option('ci_last_id_update_has_ordered', 0);


	$orders = $wpdb->get_results("select * from ci_reports where estatus='C' and id > {$last_id} limit 1000");
	foreach ($orders as $o) {
		$rs = tr_pdd_update_result_ordered($o->id);
		$last_id = $o->id;
		var_dump($last_id);
		var_dump($rs);
		echo '<br>';
	}
	update_option('ci_last_id_update_has_ordered', $last_id);

	$nextcron = wp_next_scheduled('ci_cron_clean_results');
	if ($nextcron == 0 || $nextcron > time() + 360) {
		wp_schedule_single_event(time() + 180, 'ci_cron_clean_results', array(0));
	}

	echo '<meta http-equiv="refresh" content="5">';
	exit;
}

function tradmin_action_ci_save_form_rpp_gi()
{
	include "actions/ci_save_form_rpp_gi.php";
}

function tradmin_action_ci_save_form_women()
{
	include "actions/ci_save_form_women.php";
}

function tradmin_action_ci_save_notes_batch()
{
	global $wpdb;
	$return = array('status' => 'ok');
	$mid = $_REQUEST['mid'];
	$notes = stripslashes($_REQUEST['notes']);
	$table = 'ci_batches';
	if (stripos($mid, 'blood_') !== false) {
		$table = 'ci_batches_blood';
		$mid = str_replace('blood_', '', $mid);
	} else if (stripos($mid, 'dna_') !== false) {
		$table = 'ci_batches_dna';
		$mid = str_replace('dna_', '', $mid);
	} else if (stripos($mid, 'rpp_gi_') !== false) {
		$table = 'ci_batches_rpp_gi';
		$mid = str_replace('rpp_gi_', '', $mid);
	}
	$wpdb->update($table, array('notes' => $notes), array('mid' => $mid));

	wp_send_json($return);
}

function tradmin_action_ci_load_rejected()
{
	include(TRCPDD_PATH . 'inc/actions/ci_load_rejected.php');
	exit;
}

function tradmin_action_ci_req_pdf()
{
	include(TRCPDD_PATH . 'inc/actions/ci_req_pdf.php');
}

function tradmin_action_ci_get_req_pdf()
{
	include(TRCPDD_PATH . 'inc/actions/ci_get_req_pdf.php');
}

function tradmin_action_ci_check_pdf_card_cron()
{
	include(TRCPDD_PATH . 'inc/crons/check_pdf_card.php');
	exit;
}

function tradmin_action_ci_upload_result_pdf()
{
	include(TRCPDD_PATH . 'inc/actions/ci_upload_result_pdf.php');
}
function tradmin_action_sync_wsdl_client()
{
	include(TRCPDD_PATH . 'inc/actions/ci_cron_wsdl_put_req.php');
}

function tradmin_action_ci_batch_pdf()
{
	include(TRCPDD_PATH . 'reports/batch_pdf_html.php');
}

function tradmin_action_ci_cron_clean_results()
{
	echo tr_pdd_ci_cron_clean_results();
	exit;
}

function tradmin_action_ci_changeto_finalcopy()
{
	global $wpdb;
	$ids = explode(',', trim($_POST['ids']));
	foreach ($ids as $id) {
		$wpdb->update('ci_reports', array('final_results' => '1', 'modified' => time()), array('id' => $id));
	}
	echo 'Changed to Final Copy';
	exit;
}

function tradmin_action_ci_save_otr()
{
	include(TRCPDD_PATH . '/inc/actions/ci_save_otr.php');
}

function tradmin_action_ci_save_ngs()
{
	include "actions/ci_save_ngs.php";
}

function tradmin_action_ci_save_form_pcr()
{
	include_once(TRCPDD_PATH . 'inc/actions/ci_save_pcr.php');
}
function tradmin_action_ci_load_results_pcr()
{
	include(TRCPDD_PATH . 'inc/actions/ci_load_results_pcr.php');
	exit;
}

function tradmin_action_ci_save_form_gene()
{
	include_once(TRCPDD_PATH . 'inc/actions/ci_save_gene.php');
}
function tradmin_action_ci_load_results_gene()
{
	include(TRCPDD_PATH . 'inc/actions/ci_load_results_gene.php');
	exit;
}

function tradmin_action_use_current_clinic_id()
{
	$cid = $_GET['cid'];
	$user_id = get_current_user_id();
	update_user_meta($user_id, '_use_current_clinic_id', $cid);
	$url = Tr_Base_Class_V6::link(array('cid' => '', 'act' => '', 'id' => ''), false);
	wp_redirect($url);
}

function tradmin_action_ci_report_pdf()
{
	global $wpdb, $ci_need_build_pdf;
	$ci_need_build_pdf = true;
	$report = trpdd_get_html_report($_REQUEST);
	$outfile = $report['filename'];
	if (isset($_GET['html'])) {
		echo $report['html'];
		exit;
	}
	try {
		$html = trpdd_pdf_html($report['html']);
		trpdd_build_pdf($html, array('size' => 'letter', 'orientation' => $report['orientation'], 'filename' => $outfile));
	} catch (Error $e) {
		echo ("Error while export PDF file. " . $e->getMessage());
	}

	exit;
}

function tradmin_action_ci_view_user_logs()
{
	include(TRCPDD_PATH . 'inc/actions/ci_view_user_logs.php');
}

function tradmin_action_ci_load_results_blood()
{
	include(TRCPDD_PATH . 'inc/actions/ci_load_results_blood.php');
	exit;
}

function tradmin_action_ci_export_users()
{
	include(TRCPDD_PATH . 'inc/actions/ci_export_users.php');
	exit;
}


function tradmin_action_ci_test_dymo()
{
	include(TRCPDD_PATH . 'pages/testdymo.php');
	exit;
}


function tradmin_action_ci_download_pdfs()
{
	include(TRCPDD_PATH . 'inc/actions/ci_download_pdfs.php');
	exit;
}

function tradmin_action_ci_qr_generator()
{
	include(TRCPDD_PATH . 'inc/actions/qr_generator.php');
	exit;
}

function tradmin_action_report_history_pdf()
{
	include(TRCPDD_PATH . 'inc/actions/report_history_pdf.php');
	exit;
}

function tradmin_action_ci_results_history()
{
	include(TRCPDD_PATH . 'inc/actions/results_history.php');
	exit;
}

function tradmin_action_ci_results_quick_view()
{
	include(TRCPDD_PATH . 'inc/actions/results_quick_view.php');
	exit;
}


function tradmin_action_ci_duplicate_req()
{
	include(TRCPDD_PATH . 'inc/actions/duplicate_req.php');
	exit;
}

function tradmin_action_dashboard_ajax()
{
	include(TRCPDD_PATH . 'parts/dashboard/dashboard_ajax.php');
	exit;
}
function tradmin_action_ci_save_form_default_profile()
{
	include(TRCPDD_PATH . 'inc/actions/save_form_default_profile.php');
}
function tradmin_action_export_pcr_qc()
{
	include(TRCPDD_PATH . 'parts/pcr/export_pcr_qc.php');
	exit;
}


//end file