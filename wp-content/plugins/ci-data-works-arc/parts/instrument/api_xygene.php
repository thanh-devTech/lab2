<?php
global $wpdb, $current_role;

$log_dir 	= WP_CONTENT_DIR . '/log/xygene/';
$resultlog 	= WP_CONTENT_DIR . '/log/xygene_results/';


$dir = dirname(ABSPATH) . '/xygene/';
$order_dir = $dir . 'orders/';
$order_log_dir = $dir . 'archive/';
$result_dir = $dir . 'results/';

include(TRCPDD_PATH . 'inc/define_apis.php');
include(TRCPDD_PATH . 'api/ci_api_xygene.php');

$api_key = 'api_xygene';
$log_file = WP_CONTENT_DIR . '/log/ci_' . $api_key . '.txt';



if (isset($_POST['options'])) {

	$key_api = $define_apis[$api_key]['st'];
	$options = $_POST['options'];
	$old_options = get_option($key_api, array());
	$bkdata = array(
		'old' => $old_options,
		'new' => $options
	);
	//log to user changes
	trpdd_log_user(get_current_user_id(), 'api_' . $key_api, $bkdata);

	update_option($define_apis[$api_key]['st'], $_POST['options']);


	Tr_Session_Class::add('Saved Settings Successfully');
	$options = get_option($define_apis[$api_key]['st'], array());
	//setup cron
	wp_clear_scheduled_hook($define_apis[$api_key]['cron'][0], array($define_apis[$api_key]['cron'][1]));

	if ($options['enable']) {
		//2min 5min 10min
		if (empty($options['cron'])) {
			$options['cron'] = 5;
		}
		wp_schedule_event(time() + 100, $options['cron'] . 'min', $define_apis[$api_key]['cron'][0], array($define_apis[$api_key]['cron'][1]));
	}
}
$options = get_option($define_apis[$api_key]['st'], array());




try {
	$client = new CI_Api_xygene($options);
} catch (Exception $e) {
	Tr_Session_Class::add_error($e->getMessage());
}
if ($act == 'search_clinic') {
	$client->debug = true;
	$sql_tests = "select te.te_xygene_code
				from ci_test_panels te
				where te_xygene_code !='' ";
	$tests = $wpdb->get_col($sql_tests);


	$rs = $client->searchClinics([
		"serviceCodes" => $tests,
		"address1" => "324 S. 6Th Ave",
		"city" => "Wauchula",
		"state" => "KS",
		"zip" => "66029",
		"searchRadius" => 100
	]);
	var_dump($rs);
	exit;
} else if ($act == 'test_sync') {
	try {

		$client->debug = true;
		$client->send_orders();
		echo str_replace("\n", '<br>', $client->log);
		echo "<br><br>done";
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
} else if ($act == 'log') {

	$btype =  isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'gene';
	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);

	$where = " o.mo_file_id='{$client->api_id}'  ";
	if (isset($_GET['debug'])) {
		$joins .= " join ci_patients pa ON pa.pa_ID = ba.patient_id ";
		$joins .= " join ci_insurances ins ON ins.ins_ID = ba.insurance1 ";
		$fields .= ", pa.pa_first, pa.pa_last, ins.ins_first, ins.ins_last, ins.ins_ID ";
	}
	if (!empty($_REQUEST['s'])) {
		$where .= $wpdb->prepare(
			" and (ba.assen like %s or ba.patient_name like %s or o.mo_test_ID = %s or o.mo_patient_ID= %s) ",
			$_REQUEST['s'] . '%',
			'%' . $_REQUEST['s'] . '%',
			$_REQUEST['s'],
			$_REQUEST['s']
		);
	}

	if ($client->options['ref_lab_id'] > 0) {
		$where .= " and ba.ref_lab_ID = {$client->options['ref_lab_id']} ";
	}



	$row_per_page = 200;
	if (isset($_GET['get_codes'])) {
		$row_per_page = 9999999999;
	}
	$pagination = new Tr_Pagination_class2($row_per_page);

	$rejected_status = tr_pdd_get_batch_statuses(false, true, true, 'gene');


	$pagination->setTotal($wpdb->get_var("select count(*) as c from {$batch_tbl} ba
									left join {$report_tbl} re ON re.patient_id = ba.assen
	                                join ci_sync_api_orders  o ON o.mo_mid = ba.mid and o.mo_btype='{$btype}' 
	                                where {$where} "));
	$offset = $pagination->offset();



	$rows = $wpdb->get_results("select ba.mid,
                                ba.assen,
                                ba.patient_name,
                                ba.requesting_practice,
                                ba.requesting_physician,
                                ba.doc,
                                ba.spec_received,
                                ba.batch_created,
                                re.id,
                                re.estatus,
                                o.mo_ID,
                                o.mo_status,
                                o.mo_patient_ID,
								o.mo_sent_dt
                            from {$batch_tbl} ba
                            left join {$report_tbl} re ON re.patient_id = ba.assen
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype = '{$btype}' 
                            where {$where}
                            order by mo_sent_dt desc
                            limit {$offset},{$row_per_page}");
	//echo $wpdb->last_query;
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
				<th style="width:100px">Accession</th>
				<th>PID</th>
				<th>Patient</th>
				<th>Clinic</th>
				<th>Doctor</th>
				<th style="width:60px">Resulted</th>
				<th style="width:70px">Status</th>
				<th style="width:50px;">Sent</th>
				<th>DOC</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ($rows as $row) {


			?>
				<tr class="childp" data-id="<?php echo $row->id ?>">
					<td><?php echo $row->mid ?></td>
					<td><a target="_blank" href="?page=ci_batches_view&act=edit&id=<?php echo $btype . '_' . $row->mid ?>"><?php echo $row->assen ?></a></td>
					<td><?php echo $row->mo_patient_ID ?></td>
					<td><?php echo $row->patient_name ?></td>
					<td><?php echo $row->requesting_practice ?></td>
					<td><?php echo $row->requesting_physician ?></td>
					<td><a href="?page=<?php echo 'ci_results_' . $btype ?>&act=edit&id=<?php echo $row->id;
																						if (isset($_GET['debug'])) echo '&createnew=' . $row->mid ?>" target="_blank">
							<?php echo $row->estatus == 'C' ? 'Yes' : (isset($_GET['debug']) ? '.' : '') ?>
						</a>
					</td>
					<td>

					</td>
					<td><?php echo $row->mo_sent_dt ?></td>
					<td><?php echo $row->doc ?></td>
					<td><?php
						echo '<a target=_blank href="' . Tr_Base_Class_V4::link(array('trajax' => 1, 'act' => 'viewrshl7', 'fid' => $row->mo_ID, 'btype' => $btype), false) . '">View data</a>';
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
} else if ($act == 'viewrshl7') {

	$mo_id = $_REQUEST['fid'];
	$data = $wpdb->get_row("select * from ci_sync_api_orders where mo_ID = {$mo_id} ");

	$json = json_decode(stripslashes($data->other_info), true);
	if (is_array($json)) {
		echo '<pre>';
		echo stripslashes(json_encode($json, JSON_PRETTY_PRINT));
		echo '</pre>';
	} else {
		echo stripslashes($data->other_info);
	}

	echo '<br><br><pre>';
	echo $data->mo_peg_ID;
	echo '</pre>';

	exit;
}
?>

<div class="wrap">
	<h2>Settings</h2>
	<?php Tr_Session_Class::show() ?>

	<form method="post">
		<div style="width:50%;float:left">
			<div class="row">
				<label for="for_enable">Enable </label>
				<input type="checkbox" id="for_enable" value="1" name="options[enable]" <?php checked($options['enable']) ?> />
			</div>

			<?php $client->showSettingsFields($options); ?>


		</div>
		<div style="width:50%;float:right">
			<div class="frow">
				<label>Notes</label>
				<textarea name="options[notes]" style="height:300px"><?php echo esc_html($options['notes']) ?></textarea>
			</div>
		</div>

		<div class="row">
			<input type="submit" value="Save" class="button-primary" /> &nbsp;&nbsp;

			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'search_clinic')) ?>&width=1000">Test Search clinics</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_sync')) ?>&width=1000">Test send Orders</a> &nbsp;&nbsp;

			<a class="button" target="_blank" href="/wp-content/log/<?php echo 'ci_' . $api_key ?>.txt">Log File</a> &nbsp;&nbsp;


			<br><br>
			<?php

			echo '<table style="min-width:300px">';
			echo '<tr><th>Type</th><th>Reqs</th><th>left</th><th>Error</th><th>Sent</th><th></th></tr>';
			foreach ($client->all_btypes as $btype) {
				$batch_tbl = tr_pdd_get_table_by_btype($btype);
				$report_tbl = tr_pdd_get_table_report_by_btype($btype);

				$sql_count = " select count(*) as count_reqs,
                            SUM(IF(re.id is null and (o.mo_sent = 0 or o.mo_sent is null) ,1,0)) as count_left,
                            SUM(IF(o.mo_runcount>0 and (o.mo_sent = 0 or o.mo_sent is null) ,1,0)) as count_error,
                            SUM(IF(o.mo_sent>0,1,0)) as count_sent
                            from {$batch_tbl} ba
                            left join {$report_tbl} re ON re.patient_id = ba.assen and re.estatus='C'
                            left join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype='{$btype}' and o.mo_file_id = {$client->api_id}
                            where ba.batch_id > 0 and ba.patient_id > 0  ";
				if (!empty($options['start_date'])) {
					$sql_count .= " and ba.doc >= '{$options['start_date']}' ";
				}

				if (isset($_GET['debug'])) {
					echo $sql_count;
				}
				$count = $wpdb->get_row($sql_count);


				echo '<tr><th>' . $btype . '</th><td class="center">' . $count->count_reqs . '</td>';
				echo '<td class="center">' . $count->count_left . '</td>';
				echo '<td class="center">' . $count->count_error . '</td>';
				echo '<td class="center">' . $count->count_sent . '</td>';
				echo '<td><a class="thickbox" href="' . Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'log', 'btype' => $btype, 'debug' => isset($_GET['debug']) ? 1 : '', 'width' => 1000), false) . '">Log</a>';
				echo '</tr>';
			}
			echo '</table>';



			echo '<br>';
			echo 'Now: ' . date_i18n('Y-m-d H:i:s') . '<br>';

			$next_cron = wp_next_scheduled($define_apis[$api_key]['cron'][0], array($define_apis[$api_key]['cron'][1]));
			$current_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;

			$last_cron = file_exists($log_file) ? filemtime($log_file) : 0;
			if ($last_cron) {
				echo 'Last Cron: ' . date('Y-m-d H:i:s', $last_cron + $current_offset) . '<br>';
			}
			if ($next_cron) {
				echo 'Next Cron: ' . date('Y-m-d H:i:s', $next_cron + $current_offset) . '<br>';
			}
			?>
		</div>
	</form>
</div>

<script>
	jQuery(function($) {
		$('body').on('click', 'a.show_popup', function() {
			if ($(this).hasClass('seach_assen')) return;
			var $url = $(this).attr('href');
			if ($(this).hasClass('resend')) return;
			//tb_remove();
			setTimeout(function() {
				tb_show('', $url);
			}, 300);
			return false;
		}).on('click', 'a.seach_assen', function() {
			var $s = $('#assen_search').val();
			var $url = $(this).attr('href');
			setTimeout(function() {
				tb_show('', $url + '&s=' + encodeURIComponent($s));
			}, 500);
			return false;
		})
	})
</script>