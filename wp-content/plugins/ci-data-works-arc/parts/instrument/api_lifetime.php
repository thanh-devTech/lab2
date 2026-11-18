<?php
global $wpdb, $current_role;

include(TRCPDD_PATH . 'api/ci_api_lifetime.php');

$dir            = dirname(ABSPATH) . '/lifetime/';
$order_dir      = $dir . 'orders/';
$order_log_dir  = $dir . 'archive/';
$result_dir     = $dir . 'results/';

$log_run_file   = WP_CONTENT_DIR . '/log/log_api_lifetime_run.txt';


if (isset($_POST['options'])) {

	$options = get_option('tr_lifetime_st', array());
	$new_options = $_POST['options'];
	$new_options['last_date'] = $options['last_date'];
	$old_options = get_option('tr_lifetime_st', array());
	$bkdata = array(
		'old' => $old_options,
		'new' => $new_options
	);
	//log to user changes
	trpdd_log_user(get_current_user_id(), 'api_tr_lifetime_st', $bkdata);
	update_option('tr_lifetime_st', $new_options);


	Tr_Session_Class::add('Saved Settings Successfully');
	$options = get_option('tr_lifetime_st', array());
	//setup cron
	wp_clear_scheduled_hook('cron_api_lifetime');

	if ($options['enable']) {
		if (empty($options['cron'])) {
			$options['cron'] = 5;
		}

		wp_schedule_event(time() + 1, $options['cron'] . 'min', 'cron_api_lifetime');


		wp_mkdir_p($dir);
		wp_mkdir_p($order_dir);
		wp_mkdir_p($order_log_dir);
		wp_mkdir_p($result_dir);
	}
}


$options = get_option('tr_lifetime_st', array());
if (!isset($options['cron'])) {
	$options['cron'] = 2;
}


$client = new CI_Api_Lifetime($options);

if ($act == 'install') {
?>
	<h2>Install</h2>
	<li>Create sftp to folder: <?php echo $dir ?></li>
	<li>Create folders: <?php echo $order_dir ?> and <?php echo $order_log_dir ?></li>

	<textarea style="width:100%;height:500px">
mkdir <?php echo $dir ?>

mkdir <?php echo $order_dir ?>

mkdir <?php echo $order_log_dir ?>

chmod 0777 <?php echo $order_dir ?>

chmod 0777 <?php echo $order_log_dir ?>

groupadd lifetime
useradd -g lifetime -d <?php echo $dir ?> -s /sbin/nologin lifetime
passwd lifetime
chmod 0755 <?php echo $dir ?>


chown -R root:root <?php echo $dir ?>

change file: /etc/ssh/sshd_config:

comment this line: Subsystem	sftp	/usr/libexec/openssh/sftp-server

and add this to end:

Match Group lifetime
ChrootDirectory <?php echo $dir ?>

ForceCommand internal-sftp
PasswordAuthentication yes
X11Forwarding no
AllowTcpForwarding no

then restart ssh: service sshd restart

fix issue:
chown root:nginx /mnt/data/vhosts
chmod 0755 /mnt/data/vhosts

</textarea>
<?php
	exit;
} else if ($act == 'test_sync') {
	try {
		$client->debug = true;
		$client->sync_reqs();
		echo str_replace("\n", '<br>', $client->log);
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	exit;
} else if ($act == 'test_sync_reports') {
	try {
		$client->debug = true;
		$client->sync_reports();
		echo str_replace("\n", '<br>', $client->log);
	} catch (Exception $e) {
		Tr_Session_Class::add_error($e->getMessage());
	}
	Tr_Session_Class::show();
	echo 'ok';
	exit;
} else if ($act == 'log') {

	$where = " o.mo_file_id='{$client->api_id}'  ";

	if (!empty($_REQUEST['s'])) {
		$where .= " and (ba.assen = '{$_REQUEST['s']}' or oldreq = '{$_REQUEST['s']}' )";
	}
	$row_per_page = 200;
	$pagination = new Tr_Pagination_class2($row_per_page);
	$pagination->setTotal($wpdb->get_var("select count(*) as c from ci_batches_blood ba
	                                join ci_sync_api_orders o ON o.mo_mid = ba.mid
	                                where {$where}"));

	$offset = $pagination->offset();

	$rows = $wpdb->get_results("select 
	                            ba.mid,
                                ba.assen,
                                ba.doc,
                                ba.oldreq,
                                ba.requesting_physician,
                                ba.requesting_practice,
                                ba.notes,
                                
                                pa.pa_ID,
                                pa.pa_first,
                                pa.pa_last,
                                pa.pa_dob,
                                pa.pa_gender,
                                pa.pa_address,
                                
                                re.covid_pos,
                                re.id,
                                re.estatus,
                                o.mo_control_ID,
                                o.mo_test_ID,
                                o.mo_status
                            from ci_batches_blood ba
                            join ci_patients pa ON pa.pa_ID = ba.patient_id
                            left join ci_reports_blood re ON re.patient_id = ba.assen
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid
                            where {$where}
                            order by mo_ID desc
                            limit {$offset},{$row_per_page}");



	ob_start();
?>
	<div>
		<input type="search" id="assen_search">
		<a class="button seach_assen" href="<?php Tr_Base_Class_V6::link(array('s' => '')) ?>">Search</a>
	</div>
	<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
	<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
		<thead>
			<tr>
				<th style="width:40px">#</th>
				<th style="width:80px">Accession</th>
				<th>Ref ID</th>
				<th>Patient</th>
				<th>DOB</th>
				<th>Gender</th>
				<th>Clinic</th>
				<th>Doctor</th>
				<th>DOC</th>
				<th style="width:60px">Resulted</th>
				<th>Date/time</th>
				<th style="width:30px;"></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ($rows as $row) {
				$file_path = WP_CONTENT_DIR . '/results/uploads/' . $row->mo_control_ID;
				$file_url = '/wp-content/results/uploads/' . $row->mo_control_ID;

			?>
				<tr class="childp" data-id="<?php echo $row->id ?>">
					<td><?php echo $row->mid ?></td>
					<td><a target="_blank" href="?page=ci_batches_view&act=edit&id=blood_<?php echo $row->mid ?>&short=1"><?php echo $row->assen ?></a></td>
					<td><?php echo $row->oldreq ?></td>
					<td><?php echo $row->pa_last . ', ' . $row->pa_first ?></td>
					<td><?php echo $row->pa_dob ?></td>
					<td class="center"><?php echo $row->pa_gender ?></td>
					<td><?php echo $row->requesting_practice ?></td>
					<td><?php echo $row->requesting_physician ?></td>
					<td><?php echo $row->doc ?></td>
					<td><a href="?page=blood_results&act=edit&id=<?php echo $row->id;
																	if (isset($_GET['debug'])) echo '&createnew=' . $row->mid ?>" target="_blank">
							<?php echo $row->estatus == 'C' ? 'Yes' : (isset($_GET['debug']) ? '.' : '') ?>
						</a>
					</td>
					<td><?php echo $row->mo_test_ID ?></td>
					<td><?php
						echo '<a target=_blank href="' . $file_url . '">file</a>';
						?></td>
				</tr>
			<?php
			}
			?>
		</tbody>
	</table>
	<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>

	<style>
		.wp-list-table td {
			font-size: 11px;
		}
	</style>
	<?php
	$content = ob_get_clean();
	if (isset($_GET['trajax'])) {
		$content = str_replace('class="', 'class="show_popup ', $content);
		$content = str_replace('class=\'', 'class=\'show_popup ', $content);
	}
	echo $content;
	exit;
} else if ($act == 'download_csv') {
	$btype = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'blood';
	$date = date_i18n('Y-m-d');
	$hour = date_i18n('H');
	if (isset($_REQUEST['date'])) {
		$date = date('Y-m-d', strtotime($_REQUEST['date']));
	} else if ($hour <= 22 && $hour > 1) {
		$date = date('Y-m-d', strtotime($date) - 86400);
	}
	$file = $client->build_csv_req($date, $btype);
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
}


$labs = $wpdb->get_results("select * from ci_labs where (lab_is_ref=0 or lab_is_ref=2) order by lab_dba_init asc");
?>

<div class="wrap">
	<h2>lifetime Settings</h2>
	<?php Tr_Session_Class::show() ?>

	<form method="post">
		<div class="row">
			<div style="width:45%;float:left">
				<div class="row">
					<label for="for_enable">Enable </label>
					<input type="checkbox" id="for_enable" value="1" name="options[enable]" <?php checked(isset($options['enable']) ? $options['enable'] : 0) ?> />
				</div>
				<div class="row">
					<label>Lab</label>
					<select name="options[lab_id]" required>
						<?php
						foreach ($labs as $lab) {
							$selected = isset($options['lab_id']) && $lab->lab_ID == $options['lab_id'] ? 'selected' : '';
							echo '<option value="' . $lab->lab_ID . '" ' . $selected . '>' . $lab->lab_dba_init . '</option>';
						}
						?>
					</select>
				</div>


				<div class="row">
					<label>Schedule</label>
					<select name="options[cron]">
						<?php
						foreach (array(2, 5, 10, 30) as $m) {
							$selected = isset($options['cron']) && $m == $options['cron'] ? 'selected' : '';
							$sctxt = $m > 0 ? 'each ' . $m . ' min(s)' : 'at night';
							echo '<option value="' . $m . '" ' . $selected . '>' . $sctxt . '</option>';
						}
						?>
					</select>
				</div>
			</div>
			<div style="width:49%;float:right">
				<div class="frow">
					<label>Notes</label>
					<textarea name="options[notes]" style="height:300px;width:100%"><?php echo isset($options['notes']) ? esc_html($options['notes']) : '' ?></textarea>
				</div>
			</div>
		</div>



		<div class="row">
			<input type="submit" value="Save" class="button-primary" />
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_sync_reports')) ?>&width=1000">Test Sync Reports</a> &nbsp;&nbsp;
			<a class="button" target="_blank" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'download_csv')) ?>">csv</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'log'));
												if (isset($_GET['debug'])) echo '&debug=1' ?>&width=1000">Log</a> &nbsp;&nbsp;

			<a class="button" href="/wp-content/log/log_api_lifetime.txt" target="_blank">Cron Log</a> &nbsp;&nbsp;
			<?php if (isset($_GET['debug'])) { ?>
				<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'install')); ?>&width=1000">Install</a> &nbsp;&nbsp;
			<?php } ?>
			<?php

			echo '<br><br>';
			echo 'Now: ' . date_i18n('Y-m-d H:i:s') . '<br>';

			$next_cron = wp_next_scheduled('cron_api_lifetime');

			$last_cron = file_exists($log_run_file) ? file_get_contents($log_run_file) : 0;
			if ($last_cron >  $next_cron - 60 * $options['cron']) {
				$last_cron = $next_cron - 60 * $options['cron'];
			}
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
	var $doctors = <?php echo json_encode(isset($doctors) ? $doctors : array()) ?>;
	var $selected_doc = <?php echo intval(isset($options['doc_id']) ? $options['doc_id'] : 0) ?>;
	jQuery(function($) {
		function save_hashcode_data($this) {
			var $pr = $this.parent();
			var $tr = $pr.parent();
			var $vl = $this.val();
			var $col = $this.data('k');
			var $id = $tr.data('id');

			$pr.append('<div class="sloading"></div>');
			$.ajax({
				data: {
					act: 'save_hashcode',
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

		$('[name="options[cli_id]"]').change(function() {
			var $cli_id = $(this).val();
			var $html = '<option value="">Select Doctor</option>';
			for (i in $doctors) {
				var $doctor = $doctors[i];
				if ($doctor.cli_ID == $cli_id) {
					var $selected = $doctor.doc_ID == $selected_doc ? 'selected' : '';
					$html += '<option value="' + $doctor.doc_ID + '" ' + $selected + '>' + $doctor.doc_first + ' ' + $doctor.doc_last + '</option>';
				}
			}
			$('[name="options[doc_id]"]').html($html);


		}).change();
		$('body').on('click', 'a.seach_assen', function() {
			var $s = $('#assen_search').val();
			var $url = $(this).attr('href');
			setTimeout(function() {
				tb_show('', $url + '&s=' + $s);
			}, 300);
			return false;
		}).on('click', 'a.show_popup', function() {
			if ($(this).hasClass('seach_assen')) return;
			var $url = $(this).attr('href');
			tb_remove();
			setTimeout(function() {
				tb_show('', $url);
			}, 500);
			return false;
		}).on('keypress', '#hashcodetbl input.editfield', function(e) {
			var code = e.keyCode || e.which;
			if (code == 13) {
				save_hashcode_data($(this));
				return false;
			}
		}).on('change', '#hashcodetbl input.editfield', function() {
			save_hashcode_data($(this));
		})
	})
</script>