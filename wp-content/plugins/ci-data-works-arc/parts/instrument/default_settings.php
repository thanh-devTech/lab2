<?php
global $wpdb, $current_role;


include_once(TRCPDD_PATH . 'inc/define_apis.php');



$api_key = $subtab;
$api_arr = $define_apis[$api_key];

$log_file 	= WP_CONTENT_DIR . '/log/ci_' . $api_key . '.txt';

$api_file = strtolower(str_replace('api_', 'ci_api_', $api_key)) . '.php';
$api_class = isset($api_arr['class']) ? $api_arr['class'] : ucfirst(str_replace('api_', 'CI_Api_', $api_key));
$api_class_path = TRCPDD_PATH . 'api/' . $api_file;
if (!file_exists($api_class_path)) {
	//$rs = file_put_contents($api_class_path, "<?php\ninclude_once('ci_api_base_sftphl7.php');\nclass $api_class extends CI_Api_Base_sFtpHl7 {\n\n}");
	//var_dump($api_class_path);
	//var_dump($rs);
}
include($api_class_path);




if (isset($_POST['options'])) {

	update_option($api_arr['st'], $_POST['options']);


	Tr_Session_Class::add('Saved Settings Successfully');
	$options = get_option($api_arr['st'], array());
	//setup cron
	wp_clear_scheduled_hook($api_arr['cron'][0], array($api_arr['cron'][1]));

	if ($options['enable']) {
		if (empty($options['cron'])) {
			$options['cron'] = 5;
		}
		wp_schedule_event(time() + 100, $options['cron'] . 'min', $api_arr['cron'][0], array($api_arr['cron'][1]));
	}
}


$options = get_option($api_arr['st'], array());

try {
	$client = new $api_class($options);
} catch (Exception $e) {
	Tr_Session_Class::add_error($e->getMessage());
}

$dir 		= ABSPATH . $api_key;

$order_dir 	= $dir . '/orders/';
$order_log_dir = $dir . '/archive/';
$result_dir = $dir . '/results/';


$log_dir 	= $client->order_log_dir;
if (!file_exists($log_dir)) {
	mkdir($log_dir, 0777, true);
}
$resultlog = $client->results_log_dir;
if (!file_exists($resultlog)) {
	mkdir($resultlog, 0777, true);
}


if (!empty($act)) {
	include(trpdd_get_file_path('instrument', 'sftp_actions'));
}

?>
<div class="wrap">
	<h2>Settings</h2>
	<?php Tr_Session_Class::show() ?>

	<form method="post">
		<div style="width:50%;float:left">
			<div class="row">
				<label for="for_enable">Enable </label>
				<input type="checkbox" id="for_enable" value="1" name="options[enable]" <?php checked(isset($options['enable']) ? $options['enable'] : 0) ?> />
			</div>

			<?php $client->showSettingsFields($options); ?>


		</div>
		<div style="width:50%;float:right">
			<div class="frow">
				<label>Notes</label>
				<textarea name="options[notes]" style="height:300px"><?php echo esc_html($options['notes'] ?? '') ?></textarea>
			</div>
		</div>

		<div class="row">
			<input type="submit" value="Save" class="button-primary" /> &nbsp;&nbsp;

			<?php
			foreach ($client->settingActions() as $fk => $arr) {
			?>
				<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => $fk)) ?>&width=1000"><?php echo $arr['label'] ?></a> &nbsp;&nbsp;
			<?php
			}
			?>
			<a class="button" target="_blank" href="/wp-content/log/<?php echo 'ci_' . $api_key ?>.txt">Log File</a> &nbsp;&nbsp;


			<br><br>
			<?php

			$client->setting_count();

			echo '<br>';
			echo 'Now: ' . date_i18n('Y-m-d H:i:s') . '<br>';

			$next_cron = wp_next_scheduled($api_arr['cron'][0], array($api_arr['cron'][1]));
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