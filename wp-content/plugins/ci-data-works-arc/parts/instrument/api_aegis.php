<?php
global $wpdb, $current_role;

$log_dir 	= WP_CONTENT_DIR . '/log/aegis/';
$result_in_log 	= WP_CONTENT_DIR . '/log/aegis/';
$resultlog 	= WP_CONTENT_DIR . '/log/aegis_results/';


include(TRCPDD_PATH . 'inc/define_apis.php');
include(TRCPDD_PATH . 'api/ci_api_aegis.php');

$api_key 	= 'api_aegis';
$log_file 	= WP_CONTENT_DIR . '/log/ci_' . $api_key . '.txt';


if (isset($_POST['options'])) {
	$options = $_POST['options'];
	$key_api = $define_apis[$api_key]['st'];
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
		if (empty($options['cron'])) {
			$options['cron'] = 5;
		}
		wp_schedule_event(time() + 100, $options['cron'] . 'min', $define_apis[$api_key]['cron'][0], array($define_apis[$api_key]['cron'][1]));
	}
}


$options = get_option($define_apis[$api_key]['st'], array());
try {
	$client = new CI_Api_aegis($options);
	if (!function_exists('ssh2_connect')) {
		throw new Exception('function ssh2_connect not exist! 
			to install: yum install php-pecl-ssh2 then restart php');
	}
} catch (Exception $e) {
	Tr_Session_Class::add_error($e->getMessage());
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
				<textarea name="options[notes]" style="height:300px"><?php echo esc_html($options['notes']) ?></textarea>
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