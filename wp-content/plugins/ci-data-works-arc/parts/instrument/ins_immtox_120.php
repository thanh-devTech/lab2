<?php
global $logpath, $wpdb;

include(TRCPDD_PATH . 'inc/define_instruments.php');
include(TRCPDD_PATH . 'inc/functions_instrument.php');

$conf 		= $define_instruments['ins_immtox_120'];
$port_types = array();
$port_types['order_port'] = 'Send Orders';
$port_types['result_port'] = 'Receive Results';
$port_types['patient_port'] = 'Send Patient information';
$port_types['result_inq_port'] = 'Send Test result inquiry';
$port_types['order_inq_port'] = 'ReceiveTest order inquiry';

$admin_view = isset($_REQUEST['view']) || isset($_COOKIE['trquery_monitor']);
$options    = get_option($conf['opt'], array());

if (isset($_POST['options'])) {
	$options = $_POST['options'];
	$old_options = get_option('tr_ins_immtox_120', array());
	$bkdata = array(
		'old' => $old_options,
		'new' => $options
	);
	//log to user changes
	trpdd_log_user(get_current_user_id(), 'api_tr_ins_immtox_120', $bkdata);
	update_option($conf['opt'], $options);
	Tr_Session_Class::add("Settings Saved");
}
$need_start = false;

if (empty($options['order_port'])) {
	$options['order_port'] = 55000;
}
if (empty($options['result_port'])) {
	$options['result_port'] = 55001;
}
if (empty($options['patient_port'])) {
	$options['patient_port'] = 55002;
}
if (empty($options['result_inq_port'])) {
	$options['result_inq_port'] = 55003;
}
if (empty($options['order_inq_port'])) {
	$options['order_inq_port'] = 55004;
}
$phpcmd     = isset($options['phpcmd']) ? $options['phpcmd'] : 'zts-php';

$offtime    = get_option('gmt_offset') * HOUR_IN_SECONDS;
$ip         = trpdd_get_ip();
$path       = TRCPDD_PATH . 'run';
$path       = str_replace('//', '/', $path);

$logpath    = WP_CONTENT_DIR . '/log/immtox_120';
$ins_type   = 'MSH';

$logurl     = Tr_Base_Class_V6::link(array('trajax' => 'viewlog'), false);
$reimporturl = Tr_Base_Class_V6::link(array('act' => 'reimport', 'trajax' => 1), false);
if (!file_exists($logpath)) {
	wp_mkdir_p($logpath);
}

$act        = isset($_REQUEST['trajax']) && empty($act) ? $_REQUEST['trajax'] : $act;
if (!empty($act)) {
	include(trpdd_get_file_path('instrument', 'instrument_actions'));
}




if ($act == 'list') {

	$btype      = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'tox';
	$joins      = '';
	$fields     = '';
	$where      = " ";
	if (!empty($_REQUEST['s'])) {
		$where .= " and ba.assen = '{$_REQUEST['s']}' ";
	}
	$batch_table = tr_pdd_get_table_by_btype($btype);
	$report_table = tr_pdd_get_table_report_by_btype($btype);

	$row_per_page = 200;
	$pagination = new Tr_Pagination_class2($row_per_page);
	$pagination->setTotal($wpdb->get_var("select count(distinct(ba.mid)) as c 
	                                from {$batch_table} ba
	                                join ci_sync_api_orders  o ON o.mo_mid = ba.mid
	                                        and o.mo_file_id in({$conf['id']}) and o.mo_btype='{$btype}'
	                                where 1 {$where}"));
	$offset = $pagination->offset();

	$rows = $wpdb->get_results("select ba.mid,
                                ba.assen,
                                ba.doc,
                                ba.oldreq,
                                re.id,
                                re.estatus,
                                re.instrument,
                                re.created_date,
                                pa.pa_first,
                                pa.pa_last,
                                o.mo_control_ID as sent_order,
                                o.mo_set_ID as received_rs
                            from {$batch_table} ba
                            left join {$report_table} re ON re.patient_id = ba.assen
                            left join ci_patients pa ON pa.pa_ID = ba.patient_id
                            join ci_sync_api_orders  o ON o.mo_mid = ba.mid
	                                        and o.mo_file_id in({$conf['id']}) and o.mo_btype='{$btype}'
                            where 1 {$where}
                            group by ba.mid
                            order by received_rs desc, o.mo_control_ID desc
                            limit {$offset},{$row_per_page}");
	echo ($wpdb->last_error);
?>
	<div>
		<input type="search" id="assen_search">
		<a class="button seach_assen" href="<?php Tr_Base_Class_V6::link(array('s' => '')) ?>">Search</a>
	</div>
	<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
	<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
		<thead>
			<tr>
				<th style="width:20px"></th>
				<th>Accession</th>
				<th>Patient</th>
				<th style="width:90px">Status</th>
				<th style="width:140px;">Requested Order</th>
				<th>Received Result</th>
			</tr>
		</thead>
		<tbody>
			<?php

			foreach ($rows as $i => $row) {
			?>
				<tr class="childp" data-id="<?php echo $row->id ?>" data-mid="<?php echo $row->mid ?>">
					<td><?php echo $i + 1 ?></td>
					<td><a target="_blank" href="?page=ci_batches_view&act=edit&id=<?php echo $btype . '_' . $row->mid ?>&short=1"><?php echo $row->assen ?></a></td>
					<td><?php echo $row->pa_last . ', ' . $row->pa_first ?></td>
					<td><a href="?page=<?php echo $btype ?>_results&act=edit&id=<?php echo $row->id; ?>" target="_blank">
							<?php echo !empty($row->estatus) ? $row->estatus : '.' ?>
						</a>
					</td>
					<td><?php echo $row->sent_order; ?></td>
					<td><?php echo $row->received_rs ?></td>

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
}



if (isset($_POST['start_stop'])  || isset($_POST['restart'])) {
	trpdd_update_config_file('immtox_120', $options);
	//stop that port first 
	foreach ($conf['file'] as $pty => $runfile) {
		$port = $options[$pty];

		$outfile    = $logpath . '/out_' . $port . '.txt';
		$error_file = $logpath . '/error_' . $port . '.txt';
		$pid_file   = $logpath . '/pid_' . $port . '.txt';
		$stop_file  = $logpath . '/stop_' . $port . '.txt';

		trpdd_stop_port($port, $pid_file, $stop_file);
		sleep(2);
		$started = trpdd_port_opened($port);

		if (!$started && stripos($_POST['start_stop'], 'start') !== false  || isset($_POST['restart'])) {
			if (file_exists($pid_file)) {
				unlink($pid_file);
			}
			$command = "nohup {$phpcmd} " . escapeshellarg("{$path}/{$runfile}")
				. " port {$port} " . escapeshellarg($logpath)
				. " > " . escapeshellarg($outfile)
				. " 2> " . escapeshellarg($error_file)
				. " < /dev/null &";

			$rs = exec($command, $output);

			if (isset($_GET['debug2'])) {
				var_dump($command);
				echo '<br><br>';
				var_dump($output);
				var_dump($rs);
			}

			$need_start = true;
			sleep(2);
			$started = trpdd_port_opened($port);
			if ($started) {
				$options['started'] = true;
				update_option($conf['opt'], $options);
			}
		}
	}


	//if stop
	if (stripos($_POST['start_stop'], 'stop') !== false) {
		$options['auto_start'] = false;
		$options['started'] = false;
		update_option($conf['opt'], $options);
	}
}
$started_count = 0;
$started_ports = array();
foreach ($conf['file'] as $pty => $runfile) {
	$port = $options[$pty];
	$started = trpdd_port_opened($port);
	if ($started) {
		$started_count++;
		$started_ports[] = $port;
	}
}
$started = $started_count == count($conf['file']);
if ($started) {
	$options['started'] = true;
	update_option($conf['opt'], $options);
}

if ($need_start && $started) {
	$options['auto_start'] = true;
	update_option($conf['opt'], $options);
	foreach ($conf['file'] as $pty => $runfile) {
		$port = $options[$pty];
		Tr_Session_Class::add("Started at port {$port} ");
	}
}

if (!$options['auto_start']) $options['auto_start'] = true;



wp_enqueue_script('instrument', TRCPDD_URL . 'js/instrument.js', array(), time());
wp_add_inline_script('instrument', 'var $ws_host = "' . $ip . '",$ws_port=' . $port . ';', 'before');

$df_configs = array(
	'max_bytes_per_frame' => array(63993)
);
?>
<div class="wrap">
	<?php Tr_Session_Class::show() ?>

	<div style="border:1px dashed #ddd;width:740px;margin:30px auto;padding:30px;">
		<form method="post">
			<?php if (!$started) { ?>
				<div class="frow">
					<div class="col2">
						<div class="row">
							<label>Orders Port: </label>
							<input type="number" min="100" max="99999" name="options[order_port]" value="<?php echo $options['order_port'] ?>" <?php if ($started) echo 'readonly' ?> style="width:90px">
						</div>
						<div class="row">
							<label>Result Port: </label>
							<input type="number" min="100" max="99999" name="options[result_port]" value="<?php echo $options['result_port'] ?>" <?php if ($started) echo 'readonly' ?> style="width:90px">
						</div>
						<div class="row">
							<label>Patient information Port: </label>
							<input type="number" min="100" max="99999" name="options[patient_port]" value="<?php echo $options['patient_port'] ?>" <?php if ($started) echo 'readonly' ?> style="width:90px">
						</div>
						<div class="row">
							<label>Test result inquiry Port: </label>
							<input type="number" min="100" max="99999" name="options[result_inq_port]" value="<?php echo $options['result_inq_port'] ?>" <?php if ($started) echo 'readonly' ?> style="width:90px">
						</div>
						<div class="row">
							<label>Test order inquiry Port: </label>
							<input type="number" min="100" max="99999" name="options[order_inq_port]" value="<?php echo $options['order_inq_port'] ?>" <?php if ($started) echo 'readonly' ?> style="width:90px">
						</div>

						<div class="row">
							<label>Runner</label>
							<select name="options[phpcmd]" style="width:120px">
								<?php
								foreach (array('zts-php' => 'zts-php', 'php' => 'php') as $k => $txt) {
									$selected = $k == $options['phpcmd'] ? 'selected' : '';
									echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
								}
								?>
							</select>
						</div>

						<?php
						$showd_col2 = false;
						$countrowonecol = floor(count($df_configs) / 2);
						$numrownow = 0;
						foreach ($df_configs as $cfk => $cfv) {
							$secondvl = '';
							if (is_array($cfv)) {
								$secondvl = isset($cfv[1]) ? $cfv[1] : '';
								$cfv = isset($cfv[0]) ? $cfv[0] : '';
							}
							if (!isset($options[$cfk])) {
								$options[$cfk] = $cfv;
							}
							$numrownow++;
							if (!$showd_col2 && $numrownow >= $countrowonecol) {
								$showd_col2 = true;
								echo '</div><div class="col2">';
							}
						?>
							<div class="row">
								<label><?php echo $cfk ?>: </label>
								<?php if (is_bool($cfv)) {
								?>
									<input type="checkbox" name="options[<?php echo $cfk ?>]" <?php checked($options[$cfk]) ?> value="1" />
									<small>default: <?php echo $cfv ? 1 : 0 ?></small>
								<?php
								} else { ?>
									<input type="number" name="options[<?php echo $cfk ?>]" value="<?php echo $options[$cfk] ?>" style="width:90px" />
									<small>default: <?php echo $cfv . (!empty($secondvl) ? '. or ' . $secondvl : '') ?></small>
								<?php } ?>
							</div>
						<?php
						}
						?>

						<div class="row">
							<label>Auto Start: </label>
							<input type="checkbox" name="options[auto_start]" value="1" <?php checked($options['auto_start']) ?> />
						</div>

					</div>
				</div>
				<div class="frow">
					<input type="submit" class="button-primary" value="Save" />
				</div>
			<?php
			}
			?>



			<div style="padding:15px 0">
				Server IP: <?php echo $ip ?><br>
				Status: <?php echo $started ? 'Started at port ' . implode(', ', $started_ports) : 'stopped' ?>
			</div>


			<input type="submit" class="button" name="start_stop" value="<?php echo $started ? 'Stop' : 'Start' ?>" />

			<?php if ($started) { ?>
				<input type="submit" class="button" name="restart" value="Restart" />
			<?php

			}
			if (isset($_GET['debug']) || $admin_view) {
			?>
				<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'install'));
													if (isset($_GET['debug'])) echo '&debug=1' ?>&width=1200">Install</a> &nbsp;&nbsp;
			<?php
			} ?>

			<?php foreach ($conf['file'] as $pty => $runfile) {
				$port = $options[$pty];
				$log        = trpdd_get_logfiles($logpath, $port);
			?>
				<div class="listlogblock" style="margin-top:25px">
					<?php echo $port_types[$pty] ?>. Port <?php echo $port ?>

					<?php echo '<div>connected: ' . trpdd_count_connected('immtox_120.php', $port) . '</div>'; ?>
					<select class="listlog" style="width:240px">
						<?php
						foreach ($log['files'] as $f) {
							$filesize = filesize($logpath . '/' . $f);

							if ($filesize == 0 || isset($_REQUEST['clear_log'])) {
								unlink($logpath . '/' . $f);
								continue;
							} else if (($filesize < 10 || stripos($f, 'test') > 0) && !isset($_GET['debug'])) {
								//continue;
							}
							echo '<option value="' . $f . '">' . $f . '</option>';
						}
						?>
					</select>

					<a class="button viewlog">View Log</a>
					<a class="button viewlograw">View raw</a>
					<?php if (isset($_GET['debug']) || $admin_view) { ?>
						<a class="button reimport">Re-import</a>
						<a class="button reimport onlyqc">Re-import Only QC</a>
					<?php } ?>

					<a class="button" href="<?php echo $logurl . '&file=out_' . $port . '.txt' ?>" target="logc">Log connected</a>
					<a class="button" href="<?php echo $logurl . '&file=error_' . $port . '.txt' ?>" target="logc">Log Errors</a>



					<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'list', 'btype' => 'tox'));
														if (isset($_GET['debug'])) echo '&debug=1' ?>&width=1000" title="tox List"> Log</a> &nbsp;&nbsp;

					<?php
					if (isset($_GET['debug']) || $admin_view) {
					?>
						<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test', 'port' => $port));
															if (isset($_GET['debug'])) echo '&debug=1' ?>&width=1200">Test</a> &nbsp;&nbsp;
					<?php
					}
					?>
				</div>
			<?php
			}
			?>

		</form>
	</div>
</div>

<?php include('form_white_ips.php') ?>

<style>
	form .row label {
		width: 170px
	}
</style>
<script>
	jQuery(function($) {

		$('body').on('click', '[name="start_stop"]', function() {
				var act = $(this).val();
				if (act == 'Stop' && confirm("Do you want " + act + '?') == false) {
					return false;
				}
			})
			.on('click', 'a.show_popup', function() {
				if ($(this).hasClass('seach_assen')) return;
				var $url = $(this).attr('href');
				//tb_remove();
				setTimeout(function() {
					tb_show('', $url);
				}, 300);
				return false;
			})
			.on('click', 'a.seach_assen', function() {
				var $s = $('#assen_search').val();
				var $url = $(this).attr('href');
				setTimeout(function() {
					tb_show('', $url + '&s=' + $s);
				}, 300);
				return false;
			})
		$('a.viewlog').click(function() {
			var $pr = $(this).parents('.listlogblock');
			var url = '<?php echo $logurl . '&file=' ?>' + $('.listlog', $pr).val();
			window.open(url, 'viewlog');
		});
		$('a.viewlograw').click(function() {
			var $pr = $(this).parents('.listlogblock');
			var url = '<?php echo $logurl . '&raw=1&file=' ?>' + $('.listlog', $pr).val();
			window.open(url, 'viewlograw');
		});
		$('a.reimport').click(function() {
			var $pr = $(this).parents('.listlogblock');
			var url = '<?php echo $reimporturl . '&file=' ?>' + $('.listlog', $pr).val() + '&width=1200&heigth=500';
			if ($(this).hasClass('onlyqc')) {
				url += '&onlyqc=1';
			}
			tb_show('reimport', url);
		})
	})
</script>