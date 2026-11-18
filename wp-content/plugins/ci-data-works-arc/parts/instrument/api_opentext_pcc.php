<?php
global $wpdb, $current_role;

$log_dir 	= WP_CONTENT_DIR . '/log/opentextpcc/';
$resultlog 	= WP_CONTENT_DIR . '/log/opentextpcc_results/';
$log_file 	= WP_CONTENT_DIR . '/log/ci_api_opentext_pcc.txt';


$dir 		= dirname(ABSPATH) . '/opentextpcc/';
$order_dir 	= $dir . 'orders/';
$order_log_dir = $dir . 'archive/';
$result_dir = $dir . 'results/';

include(TRCPDD_PATH . 'inc/define_apis.php');
include(TRCPDD_PATH . 'api/ci_api_opentext_pcc.php');

$covid_status = array(
	'0' => 'Negative',
	'1' => 'Positive',
	'2' => 'ReRun',
	'4' => 'Inconclusive'
);

if (isset($_POST['options'])) {

	$key_api = $define_apis['api_opentext_pcc']['st'];
	$options = $_POST['options'];
	$old_options = get_option($key_api, array());
	$bkdata = array(
		'old' => $old_options,
		'new' => $options
	);
	//log to user changes
	trpdd_log_user(get_current_user_id(), 'api_' . $key_api, $bkdata);

	update_option($define_apis['api_opentext_pcc']['st'], $_POST['options']);


	Tr_Session_Class::add('Saved Settings Successfully');
	$options = get_option($define_apis['api_opentext_pcc']['st'], array());
	//setup cron
	wp_clear_scheduled_hook($define_apis['api_opentext_pcc']['cron'][0], array($define_apis['api_opentext_pcc']['cron'][1]));

	if ($options['enable']) {
		wp_schedule_event(time() + 1, '10min', $define_apis['api_opentext_pcc']['cron'][0], array($define_apis['api_opentext_pcc']['cron'][1]));
	}
}
$options = get_option($define_apis['api_opentext_pcc']['st'], array());




try {
	$client = new CI_Api_OpenText_PCC($options);
} catch (Exception $e) {
	Tr_Session_Class::add_error($e->getMessage());
}

if (!empty($act)) {
	include(trpdd_get_file_path('instrument', 'sftp_actions'));
}

$labs 		= $wpdb->get_results("select * from ci_labs where lab_is_ref=0 or lab_is_ref = 2 order by lab_dba_init asc");
$clinics 	= $wpdb->get_results("select * from ci_clinic where cli_status=1 order by cli_clinic asc");
$docs 		= $wpdb->get_results("select * from ci_doc where doc_first!='' order by doc_first asc");
?>

<div class="wrap">
	<h2>OpenText PCC Settings</h2>
	<?php Tr_Session_Class::show() ?>

	<form method="post">
		<div style="width:50%;float:left">
			<div class="row">
				<label for="for_enable">Enable </label>
				<input type="checkbox" id="for_enable" value="1" name="options[enable]" <?php checked($options['enable']) ?> />
			</div>
			<div class="row">
				<label>Lab</label>
				<select name="options[lab_id]">
					<?php
					foreach ($labs as $lab) {
						$selected = $lab->lab_ID == $options['lab_id'] ? 'selected' : '';
						echo '<option value="' . $lab->lab_ID . '" ' . $selected . '>' . $lab->lab_dba_init . '</option>';
					}
					?>
				</select>
			</div>


			<div class="row">
				<label>Clinic ID range</label>
				<input type="text" name="options[clinicid_range]" value="<?php echo isset($options['clinicid_range']) ? $options['clinicid_range'] : '' ?>" />
			</div>

			<div class="row">
				<label>Host</label>
				<input type="text" name="options[host]" value="<?php echo $options['host'] ?>" required />
			</div>
			<div class="row">
				<label>Port</label>
				<input type="number" name="options[port]" value="<?php echo $options['port'] ?>" required />
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
				<label>Order Folder</label>
				<input type="text" name="options[orders_folder]" value="<?php echo isset($options['orders_folder']) ? $options['orders_folder']  : '/orders' ?>" />
			</div>
			<div class="row">
				<label>Results Folder</label>
				<input type="text" name="options[results_folder]" value="<?php echo isset($options['results_folder']) ? $options['results_folder'] : '/results' ?>" />
			</div>
			<div class="row">
				<label>Start Date</label>
				<input type="text" class="hasdate" name="options[start_date]" value="<?php echo $options['start_date'] ?>" required />
			</div>
		</div>
		<div style="width:50%;float:right">
			<div class="frow">
				<label>Notes</label>
				<textarea name="options[notes]" style="height:300px"><?php echo esc_html($options['notes']) ?></textarea>
			</div>
		</div>

		<div class="row">
			<input type="submit" value="Save" class="button-primary" /> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_connect')) ?>">Test Connect</a> &nbsp;&nbsp;


			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_sync_orders')) ?>&width=1000">Test Sync Orders</a> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_sync_report')) ?>&width=1000">Test Sync Reports</a> &nbsp;&nbsp;

			<a class="thickbox button" href="<?php Tr_Base_Class_V6::link(array('act' => 'list_ids', 'trajax' => 1)) ?>&width=1000">Clinic IDs</a> &nbsp;&nbsp;
			<a class="button" target="_blank" href="/wp-content/log/ci_api_opentext_pcc.txt">Log File</a> &nbsp;&nbsp;

			<?php if (isset($_GET['debug'])) { ?>
				<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_upload')) ?>">Test Upload Result</a>
				<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'install')); ?>&width=1000">Install</a> &nbsp;&nbsp;
				<a class="button" target="_blank" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'demofile')); ?>">Demo file</a> &nbsp;&nbsp;
			<?php } ?>

			<br><br>
			<?php

			echo '<table style="width:400px">';
			echo '<tr><th>Type</th><th>Reqs</th><th>Results</th><th>Sent</th><th>Sent 2</th><th>resend</th><th></th></tr>';
			foreach ($client->all_btypes as $btype) {
				$batch_tbl 	= tr_pdd_get_table_by_btype($btype);
				$report_tbl = tr_pdd_get_table_report_by_btype($btype);

				$sql_count = " select count(*) as count_reqs,
                            SUM(IF(re.id is not null,1,0)) as count_results,
                            SUM(IF(o.mo_status in (1,2),1,0)) as count_sent,
                            SUM(IF(o.mo_status in (2),1,0)) as count_resend
                            from {$batch_tbl} ba
                            left join {$report_tbl} re ON re.patient_id = ba.assen and re.estatus='C'
                            left join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype='{$btype}'
                            join ci_sync_api_files fi ON o.mo_file_id = fi.m_ID and fi.m_type='{$client->log_file_slug}'
                            where ba.doc >= '{$options['start_date']}' ";

				$count = $wpdb->get_row($sql_count);

				if ($count->count_sent > $count->count_results) {
					$count->count_results = $count->count_sent;
				}

				$sent2 = $wpdb->get_row("select count(*) as count, SUM(IF(mo_status in (2),1,0)) as count_resend
				 from ci_sync_api_orders 
				where mo_status >0 and mo_file_id = {$client->api_id}  and mo_btype = '{$btype}' ");

				$count->count_resend +=  $sent2->count_resend;

				echo '<tr><th>' . $btype . '</th><td class="center">' . $count->count_reqs . '</td>';
				echo '<td class="center">' . $count->count_results . '</td>';
				echo '<td class="center">' . $count->count_sent . '</td>';
				echo '<td class="center">' . $sent2->count . '</td>';
				echo '<td class="center">' . $count->count_resend . '</td>';
				echo '<td><a class="thickbox" href="' . Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'log', 'btype' => $btype, 'debug' => isset($_GET['debug']) ? 1 : '', 'width' => 1200), false) . '">Log</a>';
				echo '</tr>';
			}
			echo '</table>';



			echo '<br>';
			echo 'Now: ' . date_i18n('Y-m-d H:i:s') . '<br>';

			$next_cron = wp_next_scheduled($define_apis['api_opentext_pcc']['cron'][0], array($define_apis['api_opentext_pcc']['cron'][1]));
			$current_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;

			$last_cron = filemtime($log_file);
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
		function save_ids_data($this) {
			var $pr = $this.parent();
			var $tr = $pr.parent();
			var $vl = $this.attr('type') == 'checkbox' ? ($this.is(':checked') ? 1 : 0) : $this.val();
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
			tb_remove();
			setTimeout(function() {
				tb_show('', $url);
			}, 500);
			return false;
		}).on('click', 'a.seach_assen', function() {
			var $s = $('#assen_search').val();
			var $url = $(this).attr('href');
			setTimeout(function() {
				tb_show('', $url + '&s=' + $s);
			}, 500);
			return false;
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

	})
</script>