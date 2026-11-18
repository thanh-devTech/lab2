<?php
global $wpdb, $current_role;
include(TRCPDD_PATH . 'api/ci_api_telcor_billing_sftp.php');

$log_dir    = WP_CONTENT_DIR . '/log/billing_sftp/';
$log_url    = get_bloginfo('url') . '/wp-content/log/billing_sftp/';
$user       = wp_get_current_user();

if (!file_exists($log_dir)) {
	wp_mkdir_p($log_dir);
}




if (isset($_POST['options'])) {
	$options = $_POST['options'];
	if (empty($options['cron'])) {
		$options['cron'] = 10;
	}

    $old_options = get_option('tr_billing_sftp_st', array());
    $bkdata = array(
        'old' => $old_options,
        'new' => $options
    );
    //log to user changes
    trpdd_log_user(get_current_user_id(), 'api_tr_billing_sftp_st', $bkdata);
	update_option('tr_billing_sftp_st', $options);


	Tr_Session_Class::add('Saved Settings Successfully');
	$options = get_option('tr_billing_sftp_st', array());
	//setup cron


	if ($options['enable']) {

		$offset = get_option('gmt_offset') * HOUR_IN_SECONDS;
		$time_mid_night = strtotime(date('Y-m-d', time() + $offset)) -  $offset + 1000;
		if ($time_mid_night < time()) {
			$time_mid_night += 86400;
		}

		$next_cron = wp_next_scheduled('cron_api_billing_sftp');
		if (!$next_cron || $next_cron > $time_mid_night) {
			wp_clear_scheduled_hook('cron_api_billing_sftp');
			wp_schedule_event($time_mid_night, 'daily', 'cron_api_billing_sftp');
			Tr_Session_Class::add('Added Schedule');
		}
	} else {
		wp_clear_scheduled_hook('cron_api_billing_sftp');
	}
}
$options = get_option('tr_billing_sftp_st', array());


try {
	$client = new CI_Api_Telcor_Billing_sftp($options);
} catch (Exception $e) {
	Tr_Session_Class::add_error($e->getMessage());
}


if ($act == 'test_connect') {
	try {
		$client->connect();
		$client->login();
		echo 'logged in<br>';

		$path = isset($_REQUEST['path']) ? $_REQUEST['path'] : '/';
		if (!empty($_GET['pathdelete'])) {

			$client->delete($_GET['pathdelete']);
			$path = dirname($_GET['pathdelete']);
		}


		$files = $client->scan_dir($path);
		if ($path != '/') {
			$path .= '/';
		}
		$path = str_replace('//', '/', $path);

		echo ' <a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'path' => dirname($path)), false) . '" class="show_popup button">Up</a> ';
		echo ' <a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'path' => ($path)), false) . '" class="show_popup button">Reload</a>';
		echo '<br>';
		echo '<br>List files: ' . $path . '<br>';
		foreach ($files as $file) {
			if (strpos($file, '.') > 0) {
				if (isset($_REQUEST['delete'])) {
					$client->delete($path . $file);
				}
				if (stripos($file, '.pdf') > 0) {
					echo '<li><a target=_blank href="' . Tr_Base_Class_V6::link(array('act' => 'readfile', 'trajax' => 1, 'path' => $path . $file), false) . '">' . $file . '</a></li>';
				} else {
					echo '<li><a  href="' . Tr_Base_Class_V6::link(array('act' => 'readfile', 'path' => $path . $file), false) . '" class="show_popup">' . $file . '</a></li>';
				}
			} else {
				echo '<li><a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'path' => $path . $file), false) . '" class="show_popup">' . $file . '</a></li>';
			}
		}
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	echo 'count: ' . count($files);
	Tr_Session_Class::show();
	exit;
} else if ($act == 'readfile') {
	try {
		$log = '';
		$client->connect();
		$client->login();
		$log .= 'logged in<br>';

		$tmp_path = tempnam('/tmp', 'test');
		$client->receiveFile($_REQUEST['path'], $tmp_path);
		if (stripos($_REQUEST['path'], '.pdf') > 0) {
			header('Content-Type: application/pdf');
			readfile($tmp_path);
			unlink($tmp_path);
			exit;
		}

		$log .= '<a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'path' => dirname($_REQUEST['path'])), false) . '" class="show_popup">Up</a>';
		$log .= '<br>';
		$log .= 'reading file ' . $_REQUEST['path'] . '<br><br>';
		$log .= '<a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'pathdelete' => $_REQUEST['path']), false) . '" class="show_popup">Delete</a>';
		$log .= '<textarea style="width:100%;height:400px">';
		$log .= file_get_contents($tmp_path);
		$log .= '</textarea>';
		echo $log;
		unlink($tmp_path);
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
} else if ($act == 'test_sync') {
	try {
		$client->debug = true;
		$client->connect();
		$client->login();
		echo 'logged in<br>';

		$hour = date_i18n('H');
		if ($hour <= 22 && $hour > 5 && !isset($_REQUEST['date'])) {
			$_REQUEST['date'] = date('Y-m-d', strtotime(date_i18n('Y-m-d')) - 86400);
		}

		$filename = $client->sync();
		echo str_replace("\n", '<br>', $client->log);

		echo "<br><br>done";
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
} else if ($act == 'test_sync_pdf') {
	try {
		$client->debug = true;
		$client->connect();
		$client->login();
		echo 'logged in<br>';

		$client->sync_pdfs(30);
		echo str_replace("\n", '<br>', $client->log);

		echo "<br><br>done";

		touch(WP_CONTENT_DIR . '/log/log_api_billing_sftp.txt', time() - 3600);

		wp_clear_scheduled_hook('cron_api_billing_sftp', array('pdfs'));
		wp_schedule_single_event(time() + 5, 'cron_api_billing_sftp', array('pdfs'));
		echo 'Added Cron';
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
} else if ($act == 'test_upload') {
	try {
		$file_path = $client->folder . '/test.txt';
		$client->debug = true;
		$client->connect();
		$client->login();
		echo 'logged in<br>';
		echo 'Uploading file : test.txt<br>';
		$tmp_path = tempnam('/tmp', 'test');
		file_put_contents($tmp_path, 'test content');

		$client->uploadFile($tmp_path, $file_path);
		echo 'Uploaded<br>';
		$files = $client->scan_dir($client->folder);
		echo '<br>List files in folder:<br>';
		foreach ($files as $file) {
			echo '<li>' . $file . '</li>';
		}
		echo 'Reading file:<br><br>';
		$client->receiveFile($file_path, $tmp_path);
		echo file_get_contents($tmp_path);
		echo '<br>';
		echo '<br>Deleting file:' . $file_path;
		$client->delete($file_path);
		echo '=>deleted';


		echo str_replace("\n", '<br>', $client->log);
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
} else if ($act == 'viewlog') {
	$logfile = WP_CONTENT_DIR . '/log/log_api_billing_sftp.txt';

	echo '<html><body>';
	echo '<pre>';
	echo htmlentities(file_get_contents($logfile));
	echo '</pre>';
	echo '<div id="endofile"></div>';
?>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
	<script>
		jQuery(function($) {
			$("html, body").animate({
				scrollTop: $(document).height()
			}, 300);
		})
	</script>
	</body>

	</html>
<?php
	exit;
} else if ($act == 'log') {
	$joins = '';
	$fields = '';
	$where = " o.mo_file_id='{$client->api_id}' ";

	if (!empty($_REQUEST['s'])) {
		$wherechild = array();
		foreach (tr_pdd_get_batch_types() as $btype) {
			$batch_tbl = tr_pdd_get_table_by_btype($btype);
			$sql_c = $wpdb->prepare(
				" select mid from {$batch_tbl} where assen like %s or patient_name like %s ",
				$_REQUEST['s'] . '%',
				'%' . $_REQUEST['s'] . '%'
			);
			$mids = $wpdb->get_col($sql_c);
			if (count($mids) > 0) {
				$mids = implode(',', $mids);
				$wherechild[] = " ( mo_btype='{$btype}' and mo_mid in ({$mids}) ) ";
			}
		}

		if (count($wherechild) > 0) {
			$wherechild = implode(' or ', $wherechild);
			$where .= " and " . $wherechild;
		} else {
			$where .= " and mo_ID = -1 ";
		}
	}

	$row_per_page = 200;

	$pagination = new Tr_Pagination_class2($row_per_page);
	$pagination->setTotal($wpdb->get_var("select count(*) as c 
	                                from ci_sync_api_orders o
	                                where {$where}"));
	$offset = $pagination->offset();

	$rows = $wpdb->get_results("select o.*
                            from ci_sync_api_orders o
                            where {$where} 
                            order by mo_ID desc
                            limit {$offset},{$row_per_page}");

	ob_start();

?>
	<div>
		<input type="search" id="assen_search" value="<?php echo $_REQUEST['s'] ?>" placeholder="Accession or patient name">
		<a class="button seach_assen" href="<?php Tr_Base_Class_V6::link(array('s' => '')) ?>">Search</a>
	</div>
	<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
	<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
		<thead>
			<tr>
				<th style="width:60px">#</th>
				<th style="width:50px">type</th>
				<th>Accession</th>
				<th>Patient</th>
				<th>Clinic</th>
				<th>Doctor</th>
				<th>Sent PDF</th>
				<th>File</th>
			</tr>
		</thead>
		<tbody>
			<?php
			$codes = array();
			foreach ($rows as $row) {
				$batch_tbl = tr_pdd_get_table_by_btype($row->mo_btype);
				$data = $wpdb->get_row("select * from {$batch_tbl} ba
		                    where ba.mid = {$row->mo_mid}");
			?>
				<tr class="childp" data-id="<?php echo $row->id ?>">
					<td><?php echo $data->mid ?></td>
					<td><?php echo $row->mo_btype ?></td>
					<td><a target="_blank" href="?page=ci_batches_view&act=edit&id=<?php echo $row->mo_btype . '_' . $data->mid ?>&"><?php echo $data->assen ?></a></td>
					<td><?php echo $data->patient_name ?></td>
					<td><?php echo $data->requesting_practice ?></td>
					<td><?php echo $data->requesting_physician ?></td>
					<td><?php echo $row->mo_set_ID ?></td>
					<td title="<?php echo $row->mo_test_ID ?>">
						<a target="_blank" href="<?php echo '?page=welcome&trajax=1&fot=billing_sftp&viewfile=' . $row->mo_control_ID ?>"><?php echo $row->mo_control_ID ?></a>
					</td>
				</tr>
			<?php
			}
			?>
		</tbody>
	</table>
	<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
	<?php

	if (isset($_GET['get_codes'])) {
		echo implode(', ', $codes);
	}
	$content = ob_get_clean();
	if (isset($_GET['trajax'])) {
		$content = str_replace('class="', 'class="show_popup ', $content);
		$content = str_replace('class=\'', 'class=\'show_popup ', $content);
	}
	echo $content;
	exit;
} else if ($act == 'download_csv') {
	$btype = $_REQUEST['btype'];
	$date = date_i18n('Y-m-d');
	$hour = date_i18n('H');
	if (isset($_REQUEST['date'])) {
		$date = date('Y-m-d', strtotime($_REQUEST['date']));
	} else if ($hour <= 22 && $hour > 1) {
		$date = date('Y-m-d', strtotime($date) - 86400);
	}
	$file = $client->build_csv($date, $btype);
	$_REQUEST['viewfile'] = $file;
	?>
	<form method="get">
		<?php
		foreach ($_GET as $k => $vl) {
			if (!in_array($k, array('date'))) {
				echo '<input type="hidden" name="' . $k . '" value="' . $vl . '">';
			}
		}
		?>
		<label>Date: </label>
		<input type="date" name="date" value="<?php echo $date ?>" />
		<input type="submit" class="button" value="Change Date" />
	</form>
<?php
	include(TRCPDD_PATH . '/pages/admin_welcome.php');

	exit;
} else if ($act == 'phleblog_csv') {
	$date = date_i18n('Y-m-d');
	$hour = date_i18n('H');
	if (isset($_REQUEST['date'])) {
		$date = date('Y-m-d', strtotime($_REQUEST['date']));
	} else if ($hour <= 22 && $hour > 1 && !isset($_REQUEST['date'])) {
		$date = date('Y-m-d', strtotime($date) - 86400);
	}
	$file = $client->build_PhleboLog($date);
	$_REQUEST['viewfile'] = $file;
?>
	<form method="get">
		<?php
		foreach ($_GET as $k => $vl) {
			if (!in_array($k, array('date'))) {
				echo '<input type="hidden" name="' . $k . '" value="' . $vl . '">';
			}
		}
		?>
		<label>Date: </label>
		<input type="date" name="date" value="<?php echo $date ?>" />
		<input type="submit" class="button" value="Change Date" />
	</form>
<?php
	include(TRCPDD_PATH . '/pages/admin_welcome.php');

	exit;
} else if ($act == 'list_ids') {

	$list = $wpdb->get_results("select * from ci_clinic where cli_status = 1 order by cast(cli_Telcor_ID as unsigned) asc");
?>
	<table id="idstbl" class="posts wp-list-table widefat fixed hover_tbl">
		<thead>
			<tr>
				<th style="width:30px">#</th>
				<th>Clinic</th>
				<th style="width:100px">ID</th>
			</tr>
		</thead>
		<?php
		foreach ($list as $row) {
		?>
			<tr class="datarow" data-id="<?php echo $row->cID ?>">
				<td><?php echo $row->cID ?></td>
				<td><?php echo $row->cli_clinic ?></td>
				<td><input type="text" class="editfield duplicate_ids" data-id="<?php echo $row->cID ?>" data-k="cli_Telcor_ID" value="<?php echo $row->cli_Telcor_ID ?>" /></td>
			</tr>
		<?php
		}
		?>
	</table>
	<?php
	exit;
} else if ($act == 'save_ids') {
	$return = array('success' => 1, 'msg' => '');
	ob_clean();
	$id = $_REQUEST['id'];
	$col = trim($_REQUEST['col']);
	$vl = trim($_REQUEST['val']);

	$rs = $wpdb->update('ci_clinic', array($col => $vl), array('cID' => $id));

	if ($rs === false) {
		$return['success'] = 0;
		$return['msg'] = 'Error. Please try again';
	}
	wp_send_json($return);
} else if ($act == 'check') {
	if (isset($_POST['accessions'])) {
		$return = array('status' => 'ok', 'html' => '');
		$assens_str = trim($_POST['accessions']);
		$assens_str = str_replace("\r", "\n", $assens_str);
		$assens_str = str_replace("\n\n", "\n", $assens_str);
		$assens = explode("\n", $assens_str);
		$assens_str = implode("','", $assens);


		$sql_childs = array();
		foreach (tr_pdd_get_batch_types() as $btype) {
			$batch_tbl = tr_pdd_get_table_by_btype($btype);
			$report_tbl = tr_pdd_get_table_report_by_btype($btype);

			$sql_childs[] = "(select
		                    ba.mid,
		                    ba.assen,
		                    ba.doc,
		                    ba.batch_id,
		                    re.id,
		                    re.estatus,
		                    re.approve_date,
		                    '{$btype}' as btype
		                    FROM {$batch_tbl} ba
		                    left JOIN {$report_tbl} re ON re.patient_id = ba.assen
                            WHERE ba.assen in ('{$assens_str}')
                            ) ";
		}
		$sql = implode(' UNION ALL ', $sql_childs);
		$sql = "SELECT * FROM ({$sql}) AS ba1
	                LEFT JOIN ci_sync_api_orders mo
                        ON mo.mo_mid = ba1.mid 
                        and mo.mo_btype = ba1.btype
                        and mo.mo_file_id in (-18)";

		$rows = $wpdb->get_results($sql);


		if (!empty($wpdb->last_error)) {
			$return['html'] = $wpdb->last_error;
		}

		ob_start();
		if (isset($_POST['noaprove'])) {
			foreach ($rows as $row) {
				if ($row->estatus != 'C') {
					echo $row->assen . '<br>';
				}
			}
		} else {

			if (isset($_POST['resetall'])) {
				foreach ($rows as $i => $row) {

					if ($row->mo_ID > 0) {
						$wpdb->delete('ci_sync_api_orders', array('mo_ID' => $row->mo_ID));
					}
					$rows[$i]->mo_test_ID = '';
				}
			}

			if (isset($_POST['send'])) {
				try {
					$client->connect();
					$client->login();
					echo 'loggedin';
					$dates = array();
					foreach ($rows as $row) {
						if (empty($row->mo_test_ID) && $row->estatus == 'C') {
							$date = $row->approve_date;
							if (empty($date) || $date == '0000-00-00 00:00:00') {
								$date = $row->doc;
							}
							$date = date('Y-m-d', strtotime($date));
							if (!isset($dates[$date])) {
								$dates[$date] = array();
							}
							$dates[$date][] = $row->assen;
						}
					}

					if (count($dates) > 0) {
						foreach ($dates as $date => $assens_sync) {
							$_REQUEST['date'] = $date;
							$_GET['only_missing'] = true;
							$rs = $client->sync(array('assens' => $assens_sync));
							var_dump($date);
							var_dump($assens_sync);
							var_dump($rs);
						}
					}
				} catch (Exception $e) {
					Tr_Session_Class::add_error($e->getMessage());
				}
			}

	?>
			<table style="width:100%" class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
				<thead>
					<tr>
						<th style="width: 50px;">type</th>
						<th style="width:100px">Accession</th>
						<th style="width:100px">Batch#</th>
						<th style="width:70px;">Approved</th>
						<th>Approve Date</th>
						<th>Sent</th>
					</tr>
				</thead>
				<?php
				foreach ($rows as $row) {
					$report_tbl = tr_pdd_get_table_report_by_btype($row->btype);

					if ($row->estatus == 'C') {
						if (empty($row->approve_date) || $row->approve_date == '0000-00-00 00:00:00') {
							$row->approve_date = $row->doc;
							$wpdb->update($report_tbl, array('approve_date' => $row->doc), array('id' => $row->id));
						}
					}

					if (isset($_POST['approve'])) {



						//  $wpdb->update($report_tbl,array('estatus'=>'C'),array('id'=>$row->id));


					}

				?>
					<tr>
						<td><?php echo $row->btype ?></td>
						<td><a target="_blank" href="?page=ci_custody_chain&assen=<?php echo $row->assen ?>"><?php echo $row->assen ?></a></td>
						<td><?php echo $row->batch_id ?></td>
						<td><a target="_blank" href="?page=<?php echo $row->btype . '_results&act=edit&id=' . $row->id ?>"><?php echo ($row->estatus == 'C' ? 'Yes' : '<red>No</red>') ?></a></td>
						<td><?php echo $row->approve_date != '0000-00-00 00:00:00' ? $row->approve_date : '' ?></td>
						<td><?php echo $row->mo_test_ID ?></td>
					</tr>
				<?php
				}
				?>
			</table>
	<?php
		}
		$return['html'] = ob_get_clean();
		wp_send_json($return);
	}
	?>
	<div id="display_rs"></div>
	<form method="post" id="checkform">
		<label>Accessions: </label><br>
		<textarea id="accessions" name="accessions" style="width:100%;height:200px;"></textarea>
		<?php if ($user->user_login == 'Administrat0r') { ?>
			<div class="frow checkboxes">
				<label><input type="checkbox" name="send" value="1" /> Send Missing </label>
				<label><input type="checkbox" name="resetall" value="1" /> Reset </label>
				<label><input type="checkbox" name="approve2" value="1" /> Aprrove </label>
				<label><input type="checkbox" name="noaprove" value="1" /> List Only NO Aprrove </label>
			</div>
		<?php } ?>
		<div class="frow">

			<input type="submit" class="button" value="Submit" />
			<input type="hidden" name="trajax" value="1" />
			<input type="hidden" name="act" value="check" />

		</div>
	</form>
	<script>
		jQuery(function($) {
			$('form#checkform').on('submit', function() {
				var $btn = $('[type="submit"]', $(this));
				$btn.val('Checking...');
				$.ajax({
					type: 'POST',
					dataType: 'json',
					data: $(this).serialize(),
					success: function(json) {
						$btn.val('Check');
						$('#display_rs').html(json.html);
					}
				})
				return false;
			})
		})
	</script>
<?php
	exit;
}



?>

<div class="wrap">
	<h2>Telcor sFtp Settings</h2>
	<?php Tr_Session_Class::show() ?>

	<form method="post">
		<div class="frow">
			<div class="col2">
				<div class="row">
					<label for="for_enable">Enable </label>
					<input type="checkbox" id="for_enable" value="1" name="options[enable]" <?php checked(isset($options['enable']) ? $options['enable'] : '') ?> />
				</div>
				<div class="row">
					<label for="enable_blood">Today Veni </label>
					<input type="checkbox" id="enable_blood" value="1" name="options[enable_blood]" <?php checked(isset($options['enable_blood']) ? $options['enable_blood'] : '') ?> />
				</div>
				<div class="row">
					<label for="PhleboLog">PhleboLog</label>
					<input type="checkbox" id="PhleboLog" value="1" name="options[PhleboLog]" <?php checked(isset($options['PhleboLog']) ? $options['PhleboLog'] : '') ?> />
				</div>

				<div class="row">
					<label>Host</label>
					<input type="text" name="options[host]" value="<?php echo isset($options['host']) ? $options['host'] : '' ?>" required />
				</div>
				<div class="row">
					<label>Username</label>
					<input type="text" name="options[username]" value="<?php echo isset($options['username']) ? $options['username'] : '' ?>" required />
				</div>
				<div class="row">
					<label>Password</label>
					<input type="password" name="options[password]" value="<?php echo isset($options['password']) ? $options['password'] : '' ?>" required />
				</div>
				<div class="row">
					<label>Main Folder</label>
					<input type="text" name="options[folder]" value="<?php echo isset($options['folder']) ? $options['folder'] : '' ?>" required />
				</div>
				<div class="row">
					<label>Client Bill Folder</label>
					<input type="text" name="options[clientbill_folder]" value="<?php echo isset($options['clientbill_folder']) ? $options['clientbill_folder'] : '' ?>" />
				</div>
				<div class="row">
					<label>PhleboLog Folder</label>
					<input type="text" name="options[phlebolog_folder]" value="<?php echo isset($options['phlebolog_folder']) ? $options['phlebolog_folder'] : '' ?>" />
				</div>
				<div class="row">
					<label>Req Pdf Folder</label>
					<input type="text" name="options[req_folder]" value="<?php echo isset($options['req_folder']) ? $options['req_folder'] : '' ?>" />
				</div>
				<div class="row">
					<label>Result Pdf Folder</label>
					<input type="text" name="options[result_folder]" value="<?php echo isset($options['result_folder']) ? $options['result_folder'] : '' ?>" />
				</div>

			</div>

			<div class="col2">
				<label>Notes</label>
				<textarea name="options[notes]" style="height:300px"><?php echo isset($options['notes']) ? $options['notes'] : '' ?></textarea>
			</div>
		</div>


		<div class="row">
			<input type="submit" value="Save" class="button-primary" /> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_connect')) ?>">Test Connect</a> &nbsp;&nbsp;
			<?php if (isset($_GET['debug'])) { ?>
				<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_upload')) ?>">Test Upload</a>
				<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_sync')) ?>">Test Sync Billing</a> &nbsp;&nbsp;
				<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_sync_pdf')) ?>">Test Sync PDF</a> &nbsp;&nbsp;
			<?php } ?>
			<?php
			foreach (tr_pdd_get_batch_types() as $btype) {
			?>
				<a class="button" target="_blank" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'download_csv', 'btype' => $btype)) ?>"><?php echo $btype . ' csv' ?></a> &nbsp;&nbsp;
			<?php
			}
			?>
			<a class="button" target="_blank" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'phleblog_csv')) ?>">PhleboLog csv</a> &nbsp;&nbsp;

			<a class="thickbox button" href="<?php Tr_Base_Class_V6::link(array('act' => 'list_ids', 'trajax' => 1)) ?>">List IDs</a>

			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'log'));
												if (isset($_GET['debug'])) echo '&debug=1' ?>&width=1200">Log</a> &nbsp;&nbsp;
			<a class="button" target="_blank" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'viewlog')); ?>">Log File</a>
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'check', 'width' => '1000')); ?>">Multi Check</a>
		</div>

		<?php

		echo 'Now: ' . date_i18n('Y-m-d H:i:s') . '<br>';

		$lastfile = $wpdb->get_var("select mo_control_ID from ci_sync_api_orders
		                                where mo_file_id = {$client->api_id}
		                                order by mo_ID desc limit 1");
		echo 'Last File: <a target=_blank href="' . $log_url . $lastfile . '">' . $lastfile . '</a> - 
		            <a target=_blank href="?page=welcome&trajax=1&fot=billing_sftp&viewfile=' . $lastfile . '">view</a><br>';

		$leftcount = $wpdb->get_var("select count(*) from ci_sync_api_orders 
		                        where mo_file_id = {$client->api_id}
		                        and mo_status = 0 or mo_status is null");
		if ($leftcount > 0) {
			echo 'Left pdf: ' . $leftcount . '<br>';
		}

		$next_cron = wp_next_scheduled('cron_api_billing_sftp');
		$current_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;


		$last_cron = file_exists(WP_CONTENT_DIR . '/log/log_api_billing_sftp.txt') ? filemtime(WP_CONTENT_DIR . '/log/log_api_billing_sftp.txt') : 0;

		if ($last_cron) {
			echo 'Last Cron: ' . date('Y-m-d H:i:s', $last_cron + $current_offset) . '<br>';
		}

		if ($next_cron) {
			echo 'Next Cron: ' . date('Y-m-d H:i:s', $next_cron + $current_offset) . '<br>';
		}
		?>
	</form>
</div>
<style>
	.editfield {
		max-width: 95%;
	}

	.checkboxes label {
		margin-right: 15px;
	}
</style>

<script>
	jQuery(function($) {
		function save_ids_data($this) {
			var $pr = $this.parent();
			var $tr = $pr.parent();
			var $vl = $this.val();
			var $col = $this.data('k');
			var $id = $tr.data('id');

			$pr.append('<div class="sloading"></div>');
			$.ajax({
				data: {
					act: 'save_ids',
					trajax: 1,
					col: $col,
					id: $id,
					val: $vl
				},
				type: 'post',
				dataType: 'json',
				success: function(rs) {
					if (rs.success) {

					} else {
						alert(rs.msg);
					}
					$('.sloading', $pr).remove();
				}
			});
		}


		$('body').on('click', 'a.show_popup', function() {
			if ($(this).hasClass('seach_assen')) return;
			var $url = $(this).attr('href');
			//tb_remove();
			setTimeout(function() {
				tb_show('', $url);
			}, 300);
			return false;
		}).on('click', 'a.resend', function() {
			if (!confirm('Do you want send?')) return false;
			if ($(this).hasClass('sending')) return false;
			$(this).addClass('sending');
			var $this = $(this);
			var $pr = $(this).parents('tr');
			var $id = $pr.data('id');
			$.ajax({
				data: {
					trajax: 1,
					act: 'send_result',
					id: $id
				},
				type: 'post',
				dataType: 'json',
				success: function($rs) {
					$this.removeClass('sending');
					if ($rs.status == 'ok') {

						alert($rs.msg);
					} else {
						alert($rs.msg);
					}
				}
			});
			return false;
		}).on('click', 'a.seach_assen', function() {
			var $s = $('#assen_search').val();
			var $url = $(this).attr('href');
			setTimeout(function() {
				tb_show('', $url + '&s=' + $s);
			}, 500);
			return false;
		}).on('change', '#ma_ids_cb', function() {
			if ($(this).is(':checked')) {
				$('#clinic_div').hide();
				$('#doctor_div').hide();
			} else {
				$('#clinic_div').show();
				$('#doctor_div').show();
			}
		}).on('keypress', '#idstbl input.editfield', function(e) {
			var code = e.keyCode || e.which;
			if (code == 13) {
				save_ids_data($(this));
				return false;
			}
		}).on('change', '#idstbl input.editfield', function() {
			save_ids_data($(this));
		}).on('change', '.duplicate_ids', function() {
			var $has_duplicate = false;
			var $ice_value = $(this).val();
			var $cid = $(this).data('id');
			$('.duplicate_ids').each(function() {
				var $cid2 = $(this).data('id');
				if ($cid != $cid2 && $(this).val() == $ice_value) {
					$has_duplicate = true;
				}
			})
			if ($has_duplicate) {
				alert('Please input a unique code!');
				$(this).val('');
				$(this).focus();
			}
		})

		$('#ma_ids_cb').change();
	})
</script>