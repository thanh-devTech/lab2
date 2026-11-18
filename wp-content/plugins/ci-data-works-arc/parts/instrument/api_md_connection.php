<?php
global $wpdb, $current_role;

$log_dir = WP_CONTENT_DIR . '/log/md_connection/';
$log_run_file = WP_CONTENT_DIR . '/log/log_api_md_connection.txt';

if (!file_exists($log_dir)) {
	wp_mkdir_p($log_dir);
}

include(TRCPDD_PATH . 'api/ci_api_md_connection.php');


if (isset($_POST['options'])) {

    $options=$_POST['options'];
    $old_options = get_option('tr_md_connection_st', array());
    $bkdata = array(
        'old' => $old_options,
        'new' => $options
    );
    //log to user changes
    trpdd_log_user(get_current_user_id(), 'api_tr_md_connection_st', $bkdata);
	update_option('tr_md_connection_st', $_POST['options']);


	Tr_Session_Class::add('Saved Settings Successfully');
	$options = get_option('tr_md_connection_st', array());
	//setup cron
	wp_clear_scheduled_hook('cron_api_md_connection');

	if ($options['enable']) {
		wp_schedule_event(time() + 1, '10min', 'cron_api_md_connection');
	}
}
$options = get_option('tr_md_connection_st', array());




try {
	$client = new CI_Api_MD_Connection($options);
} catch (Exception $e) {
	Tr_Session_Class::add_error($e->getMessage());
}


if ($act == 'test_connect') {
	try {

		$path = isset($_REQUEST['path']) ? $_REQUEST['path'] : '/';
		$client->connect();
		$client->login();
		echo 'logged in<br>';


		$files = $client->scan_dir($path);
		if ($path != '/') {
			$path .= '/';
		}

		echo '<br>List files:<br>';
		foreach ($files as $file) {
			if (strpos($file, '.') > 0) {
				if (stripos($path, 'archive') !== false) {
					//$client->delete($path.$file);
				}
				echo '<li><a href="' . Tr_Base_Class_V6::link(array('act' => 'readfile', 'path' => $path . $file), false) . '" class="show_popup">' . $file . '</a></li>';
			} else {
				echo '<li><a href="' . Tr_Base_Class_V6::link(array('act' => 'test_connect', 'path' => $path . $file), false) . '" class="show_popup">' . $file . '</a></li>';
			}
		}
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
} else if ($act == 'readfile') {
	try {
		$client->connect();
		$client->login();
		echo 'logged in<br>';

		$tmp_path = tempnam('/tmp', 'test');
		$client->receiveFile($_REQUEST['path'], $tmp_path);
		echo 'reading file ' . $_REQUEST['path'] . '<br><br>';
		echo '<textarea style="width:100%;height:400px">';
		echo file_get_contents($tmp_path);
		echo '</textarea>';
		unlink($tmp_path);
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
} else if ($act == 'test_sync_in') {
	try {

		$client->debug = true;
		$client->connect();
		$client->login();
		echo 'logged in<br>';

		$client->sync_in();

		echo str_replace("\n", '<br>', $client->log);
		echo "<br><br>done";
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
} else if ($act == 'test_sync_out') {
	try {

		$client->debug = true;
		$client->connect();
		$client->login();
		echo 'logged in<br>';


		$client->sync_out();
		echo str_replace("\n", '<br>', $client->log);
		echo "<br><br>done";
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
} else if ($act == 'test_upload') {
	try {
		$file_path = $options['out_folder'] . '/test.txt';
		$client->debug = true;
		$client->connect();
		$client->login();
		echo 'logged in<br>';
		echo 'Uploading file : test.txt<br>';
		$tmp_path = tempnam('/tmp', 'test');
		file_put_contents($tmp_path, 'test content');

		$client->uploadFile($tmp_path, $file_path);
		echo 'Uploaded<br>';
		$files = $client->scan_dir($options['out_folder']);
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
} else if ($act == 'log') {
	$joins = '';
	$fields = '';
	$where = " (o.mo_file_id='{$client->api_id}' or o.mo_peg_ID='{$client->api_id}') ";
	if (isset($_GET['debug'])) {
		$joins .= " join ci_patients pa ON pa.pa_ID = ba.patient_id ";
		$joins .= " join ci_insurances ins ON ins.ins_ID = ba.insurance1 ";
		$fields .= ", pa.pa_first, pa.pa_last, ins.ins_first, ins.ins_last, ins.ins_ID ";
	}
	if (!empty($_REQUEST['s'])) {
		$where .= $wpdb->prepare(
			" and (ba.assen like %s or ba.patient_name like %s or o.mo_test_ID = %s) ",
			$_REQUEST['s'] . '%',
			'%' . $_REQUEST['s'] . '%',
			$_REQUEST['s']
		);
	}

	$row_per_page = 200;
	if (isset($_GET['get_codes'])) {
		$row_per_page = 9999999999;
	}


	$childq_total = array();
	$childq_all = array();

	foreach ($options['btypes'] as $btype) {
		$batch_tbl = tr_pdd_get_table_by_btype($btype);
		$report_tbl = tr_pdd_get_table_report_by_btype($btype);

		$childq_total[] = " (select count(*) as c from {$batch_tbl} ba
		join ci_sync_api_orders  o ON o.mo_mid = ba.mid and o.mo_btype = '{$btype}'
		where {$where} ) ";

		$childq_all[] = "(select ba.mid,
		ba.assen,
		ba.patient_name,
		ba.requesting_practice,
		ba.requesting_physician,
		ba.doc,
		ba.dagnostic_codes1,
		re.covid_pos,
		re.id,
		re.estatus,
		cli.*,
		o.mo_status,
		o.mo_ID,
		o.other_info,
		o.mo_file_id,
		o.mo_peg_ID,
		o.mo_set_ID,
		o.mo_btype,
		o.mo_test_ID
		{$fields}
		from {$batch_tbl} ba
		left join {$report_tbl} re ON re.patient_id = ba.assen
		left join ci_clinic cli ON cli.cID = ba.cli_ID
		join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype = '{$btype}'
		{$joins}
		where {$where} 
		)";
	}

	$total_query = implode("\n UNION ALL \n", $childq_total);
	$sql_query = implode("\n UNION ALL \n", $childq_all);


	$pagination = new Tr_Pagination_class2($row_per_page);
	$pagination->setTotal($wpdb->get_var("select sum(c) from ({$total_query}) as rs"));
	echo $wpdb->last_error;
	$offset = $pagination->offset();

	$rows = $wpdb->get_results("select * from ({$sql_query}) rs
							order by mo_ID desc
                            limit {$offset},{$row_per_page}");


	ob_start();
	echo $wpdb->last_error;

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
				<th style="width:60px">type</th>
				<th>Accession</th>
				<th>Patient</th>
				<th>Clinic</th>
				<th>Doctor</th>
				<th style="width:120px;">Sent</th>
				<th style="width:120px">Received</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php
			$codes = array();
			foreach ($rows as $row) {

				if (empty($row->mo_peg_ID)) {
					$wpdb->update('ci_sync_api_orders', array('mo_peg_ID' => $client->api_id), array('mo_ID' => $row->mo_ID));
				}
				$other_info = json_decode($row->other_info, true);

			?>
				<tr class="childp" data-id="<?php echo $row->id ?>">
					<td><?php echo $row->mid ?></td>
					<td><?php echo $row->mo_btype ?></td>
					<td><a target="_blank" href="?page=ci_batches_view&act=edit&id=<?php echo $row->mo_btype.'_'. $row->mid ?>&"><?php echo $row->assen ?></a></td>
					<td><?php echo $row->patient_name ?></td>
					<td><?php echo $row->requesting_practice ?></td>
					<td><?php echo $row->requesting_physician ?></td>
					<td><?php echo !empty($row->mo_test_ID) ? date('Y-m-d H:i:s', strtotime($row->mo_test_ID)) : '' ?></td>
					<td><?php echo !empty($row->mo_set_ID) ? date('Y-m-d H:i:s', strtotime($row->mo_set_ID)) : '' ?></td>
					<td><?php
						echo '<a target=_blank href="' . Tr_Base_Class_V6::link(array('trajax' => 1, 'act' => 'viewfiles', 'oid' => $row->mo_ID), false) . '">View files</a>';
						?></td>
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
} else if ($act == 'log_csv') {

	$where = " (f.m_type='{$client->api_key}' ) ";

	if (!empty($_REQUEST['s'])) {
		$where .= $wpdb->prepare(
			" and (ba.assen like %s or ba.patient_name like %s or o.mo_test_ID = %s) ",
			$_REQUEST['s'] . '%',
			'%' . $_REQUEST['s'] . '%',
			$_REQUEST['s']
		);
	}

	$row_per_page = 200;


	$total_query = "select count(*) from ci_sync_api_files f where {$where} ";
	$pagination = new Tr_Pagination_class2($row_per_page);
	$pagination->setTotal($wpdb->get_var($total_query));
	echo $wpdb->last_error;
	$offset = $pagination->offset();

	$rows = $wpdb->get_results("
							select f.* ,
							(select count(*) from ci_sync_api_orders o where o.mo_file_id = f.m_ID) as countsamp
							from ci_sync_api_files f
							where {$where}
							order by m_time desc
                            limit {$offset},{$row_per_page}");


	ob_start();
	echo $wpdb->last_error;

	?>

	<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
	<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
		<thead>
			<tr>
				<th style="width:60px">#</th>
				<th>File</th>
				<th style="width:120px;">Date/Time</th>
				<th>Count</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ($rows as $row) {
			?>
				<tr class="childp" data-id="<?php echo $row->m_ID ?>">
					<td><?php echo $row->m_ID ?></td>
					<td><a target="_blank" href="<?php Tr_Base_Class_V6::link(array('trajax' => 1, 'act' => 'viewcsv', 'fid' => $row->m_ID)) ?>"><?php echo basename($row->m_file) ?></a></td>
					<td><?php echo !empty($row->m_time) ? date('Y-m-d H:i:s', $row->m_time) : '' ?></td>
					<td><?php echo $row->countsamp ?></td>
					<td></td>
				</tr>
			<?php
			}
			?>
		</tbody>
	</table>
	<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
<?php

	$content = ob_get_clean();
	if (isset($_GET['trajax'])) {
		$content = str_replace('class="', 'class="show_popup ', $content);
		$content = str_replace('class=\'', 'class=\'show_popup ', $content);
	}
	echo $content;
	exit;
} else if ($act == 'viewcsv') {
	try {
		$row = $wpdb->get_row("select f.*
		                     from  ci_sync_api_files f 
		                     where f.m_ID = {$_REQUEST['fid']}");

		if (!empty($row->m_file)) {
			$local_file = $log_dir . basename($row->m_file);
			if (file_exists($local_file)) {
				$reimportlink = Tr_Base_Class_V6::link(array('act' => 'reimport'), false);
				echo 'csv file.:' . $local_file;
				echo '   <a href="' . $reimportlink . '">reimport</a>';
				echo '<textarea style="width:2000px;height:300px">';
				echo file_get_contents($local_file);
				echo '</textarea>';
			} else {

				$file_path = $options['in_folder'] . '/archive/' . basename($row->m_file);

				$client->connect();
				if ($client->login()) {
					$tmp_path = tempnam('/tmp', 'test');
					try {
						$client->receiveFile($file_path, $tmp_path);
					} catch (Exception $e) {
						var_dump($e);
						var_dump($file_path);
					}
					echo 'csv file: ' . $row->m_file;
					echo '<textarea style="width:2000px;height:300px">';
					echo file_get_contents($tmp_path);
					echo '</textarea>';
					unlink($tmp_path);
				} else {
					echo 'login fail for csv';
				}
			}
		}
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();

	exit;
} else if ($act == 'viewfiles') {

	try {
		$row = $wpdb->get_row("select f.*,o.*
		                     from  ci_sync_api_orders o
		                      left join ci_sync_api_files f ON o.mo_file_id = f.m_ID
		                     where o.mo_ID = {$_REQUEST['oid']}");


		if (!empty($row->mo_control_ID)) {

			$outfile = basename($row->mo_control_ID);

			if (file_exists($log_dir . $outfile)) {
				echo 'output file:' . $outfile;
				echo ' - <a href="'.Tr_Base_Class_V6::link(array('act'=>'rebuildhl7','oid'=>$_REQUEST['oid']),false).'">rebuild</a>';
				echo ' - <a href="'.Tr_Base_Class_V6::link(array('act'=>'rebuildhl7','oid'=>$_REQUEST['oid'],'send'=>true),false).'">resend</a>';
				echo '<textarea style="width:2000px;height:300px">';
				echo file_get_contents($log_dir . $outfile);
				echo '</textarea>';
			}
		}

		if (!empty($row->m_file)) {
			$local_file = $log_dir . basename($row->m_file);
			if (file_exists($local_file)) {
				$reimportlink = Tr_Base_Class_V6::link(array('act' => 'reimport'), false);
				echo 'csv file.:' . $local_file;
				echo '   <a href="' . $reimportlink . '">reimport</a>';
				echo '<textarea style="width:2000px;height:300px">';
				echo file_get_contents($local_file);
				echo '</textarea>';
			} else {

				$file_path = $options['out_folder'] . '/archive/' . basename($row->m_file);
				$client->connect();
				if ($client->login()) {
					$tmp_path = tempnam('/tmp', 'test');
					try {
						$client->receiveFile($file_path, $tmp_path);
					} catch (Exception $e) {
					}
					echo 'csv file';
					echo '<textarea style="width:2000px;height:300px">';
					echo file_get_contents($tmp_path);
					echo '</textarea>';
					unlink($tmp_path);
				} else {
					echo 'login fail for csv';
				}
			}
		}
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
}
else if($act=='rebuildhl7'){

	$oid = $_REQUEST['oid'];
	$order = $wpdb->get_row("select * from ci_sync_api_orders where mo_ID = {$oid}");
	$batch_tbl = tr_pdd_get_table_by_btype($order->mo_btype);
	$assen = $wpdb->get_var("select assen from {$batch_tbl} where mid = {$order->mo_mid}");
	
	if(!empty($assen))
	{
		$local_files = $client->build_hl7('a', $order->mo_btype, array('assens' => array($assen)));
		if(isset($_GET['send']) && $_GET['send'])
		{
			$client->connect();
			$client->login();
			foreach ($local_files as $local_file) {
				try {
					$filename = basename($local_file);

					$remote_file = $options['out_folder'] . '/' . $filename;
					
					
					$client->uploadFile($local_file, $remote_file);
					echo("uploaded: {$remote_file} \n");

					
					
				} catch (Exception $e) {
					echo $e;exit;
				}
			}
			exit;
		}
	}
	
	wp_redirect(Tr_Base_Class_V6::link(array('act'=>'viewfiles'),false));
	exit;
}

$labs = $wpdb->get_results("select * from ci_labs where (lab_is_ref=0 or lab_is_ref=2) order by lab_dba_init asc");
$clinics = $wpdb->get_results("select * from ci_clinic where cli_status=1 order by cli_clinic asc");
$docs = $wpdb->get_results("select * from ci_doc where doc_first!='' order by doc_first asc");
?>

<div class="wrap">
	<h2>MD Connection Billing</h2>
	<?php Tr_Session_Class::show() ?>

	<form method="post">
		<div class="row">
			<label for="for_enable">Enable </label>
			<input type="checkbox" id="for_enable" value="1" name="options[enable]" <?php checked($options['enable']) ?> />
		</div>
		<div class="row">
			<label for="fileby">File by </label>
			<select name="options[fileby]">
				<?php
				foreach (array(0 => 'date', 1 => 'Sample') as $k => $vl) {
					$selected = $options['fileby'] == $k ? 'selected' : '';
					echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
				}
				?>
			</select>
		</div>
		<div class="row">
			<label for="btypes">Type:</label>
			<select name="options[btypes][]" multiple class="js_select2">
				<?php
				if (!is_array($options['btypes'])) $options['btypes'] = array();
				foreach (tr_pdd_get_batch_types() as $btype) {
					$selected = in_array($btype, $options['btypes']) ? 'selected' : '';
					echo '<option value="' . $btype . '" ' . $selected . '>' . tr_pdd_get_typename($btype) . '</option>';
				}
				?>
			</select>
		</div>


		<div class="row">
			<label>Host</label>
			<input type="text" name="options[host]" value="<?php echo $options['host'] ?>" required />
		</div>
		<div class="row">
			<label>Port</label>
			<input type="text" name="options[port]" value="<?php echo $options['port'] > 0 ? $options['port'] : 22 ?>" required />
		</div>
		<div class="row">
			<label>Username</label>
			<input type="text" name="options[username]" value="<?php echo $options['username'] ?>" required />
		</div>
		<div class="row">
			<label>Password</label>
			<input type="password" name="options[password]" value="<?php echo $options['password'] ?>" required />
		</div>
		<div class="row">
			<label>In Folder</label>
			<input type="text" name="options[in_folder]" value="<?php echo $options['in_folder'] ?>" required />
		</div>
		<div class="row">
			<label>Out Folder</label>
			<input type="text" name="options[out_folder]" value="<?php echo $options['out_folder'] ?>" required />
		</div>

		<div class="row">
			<input type="submit" value="Save" class="button-primary" /> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_connect')) ?>">Test Connect</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_upload')) ?>">Test Upload file</a>
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_sync_in')) ?>">Test get In</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_sync_out')) ?>">Test send Out</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'log', 'width' => '1200')) ?>">Log</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'log_csv', 'width' => '1200')) ?>">Log csv files</a> &nbsp;&nbsp;
			<a class="button" target="_blank" href="/wp-content/log/log_api_md_connection.txt">Log Cron</a> &nbsp;&nbsp;
		</div>
		<div class="row">

			<?php
			$count = $wpdb->get_row("select 
			SUM(IF(o.mo_file_id > 0,1,0)) as count_received,
			SUM(IF(o.mo_test_ID!=1,1,0)) as count_sent
			from ci_sync_api_orders o 
			left join ci_sync_api_files f ON o.mo_file_id = f.m_ID
			where o.mo_peg_ID='{$client->api_id}'");
			echo '<br>';
			echo 'Sent: ' . $count->count_sent . '<br>';
			echo 'Received: ' . $count->count_received . '<br>';
			echo '<br><br>';
			echo 'Now: ' . date_i18n('Y-m-d H:i:s') . '<br>';

			$next_cron = wp_next_scheduled('cron_api_md_connection');

			$last_cron = file_exists($log_run_file) ? filemtime($log_run_file) : 0;


			if ($last_cron > 0) {
				echo 'Last Cron: ' . get_date_from_gmt(date('Y-m-d H:i:s', $last_cron), 'Y-m-d H:i:s') . '<br>';
			}
			if ($next_cron) {
				echo 'Next Cron: ' . get_date_from_gmt(date('Y-m-d H:i:s', $next_cron), 'Y-m-d H:i:s') . '<br>';
			}

			?>

		</div>
	</form>
</div>

<script>
	jQuery(function($) {
		$('body').on('click','a.seach_assen',function () {
            var $s = $('#assen_search').val();
            var $url = $(this).attr('href');
            setTimeout(function () {
                tb_show('',$url+'&s='+$s);
            },300);
            return false;
        }).on('click', 'a.show_popup', function() {
			if($(this).hasClass('seach_assen'))return;
			var $url = $(this).attr('href');
			tb_remove();
			setTimeout(function() {
				tb_show('', $url);
			}, 500);
			return false;
		})
	})
</script>