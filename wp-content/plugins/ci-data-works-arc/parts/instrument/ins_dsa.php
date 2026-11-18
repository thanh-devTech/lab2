<?php
global $wpdb;

include(trpdd_get_file_path('study', 'ins_dsa'));


$act = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';


if (isset($_POST['options'])) {
	$options = $_POST['options'];
	if (empty($options['cron'])) {
		$options['cron'] = 2;
	}
    $old_options = get_option('tr_ins_dsa_st', array());
    $bkdata = array(
        'old' => $old_options,
        'new' => $options
    );
    //log to user changes
    trpdd_log_user(get_current_user_id(), 'api_tr_ins_dsa_st', $bkdata);
	update_option('tr_ins_dsa_st', $options);


	Tr_Session_Class::add('Saved Settings Successfully');

	//setup cron
	wp_clear_scheduled_hook('cron_all_api',array('Ins_Dsa','study'));

	if ($options['enable']) {
		wp_schedule_event(time() + 1, $options['cron'] . 'min', 'cron_all_api', array('Ins_Dsa','study'));
		Tr_Session_Class::add('Added Schedule');
	}
}
$options = get_option('tr_ins_dsa_st', array());


$api = new Ins_Dsa($options);

if ($act == 'log_results') {

	$joins = '';
	$fields = '';
	$where = " mo_file_id='{$api->api_id}' and o.mo_peg_ID > '2022-01-01' ";

	if (!empty($_REQUEST['s'])) {
		$where .= " and ba.assen like '{$_REQUEST['s']}%' ";
	}

	$row_per_page = 200;
	$pagination = new Tr_Pagination_class2($row_per_page);
	$pagination->setTotal($wpdb->get_var("select count(*) as c from ci_batches_study ba
	                                join ci_sync_api_orders  o ON o.mo_mid = ba.mid and o.mo_btype='study' 
	                                where {$where}"));
	$offset = $pagination->offset();

	$rows = $wpdb->get_results("select ba.mid,
                                ba.assen,
                                ba.doc,
                                re.id,
                                re.estatus,
                                o.*
                            from ci_batches_study ba
                            left join ci_reports_study re ON re.patient_id = ba.assen
                            join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype='study'
                            where {$where}
                            order by mo_ID desc
                            limit {$offset},{$row_per_page}");
	//var_dump($wpdb->last_error);
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
				<th style="width:60px">#</th>
				<th>Accession</th>
				<th>Date</th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ($rows as $row) {
			?>
				<tr class="childp" data-id="<?php echo $row->id ?>">
					<td><?php echo $row->mid ?></td>
					<td><a target="_blank" href="?page=ci_batches_view&act=edit&id=study_<?php echo $row->mid ?>&short=1"><?php echo $row->assen ?></a></td>
					
					<td><?php echo $row->mo_peg_ID ?></td>
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


?>

<div class="wrap">
	<h2>DSA(Study)</h2>
	<?php Tr_Session_Class::show() ?>

	<form method="post">
		<div class="frow">
			<div class="col2">
				<div class="row">
					<label for="for_enable">Enable </label>
					<input type="checkbox" id="for_enable" value="1" name="options[enable]" <?php checked($options['enable']) ?> />
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
					<label>Folder</label>
					<input type="text" name="options[folder]" value="<?php echo $options['folder'] ?>" required />
				</div>

				<div class="row">
					<label>Schedule</label>
					<select name="options[cron]">
						<?php
						foreach (array(1, 2, 4, 6, 8, 10, 15, 30) as $m) {
							$selected = $m == $options['cron'] ? 'selected' : '';
							echo '<option value="' . $m . '" ' . $selected . '>each ' . $m . ' min(s)</option>';
						}
						?>
					</select>
				</div>


			</div>
			<div class="col2">
				<div class="frow">
					<label>Notes</label>
					<textarea name="options[notes]" style="height:300px"><?php echo esc_html($options['notes']) ?></textarea>
				</div>
			</div>
		</div>

		<div class="row">
			<input type="submit" value="Save" class="button-primary" /> &nbsp;&nbsp;
			<a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'log_results'));
												if (isset($_GET['debug'])) echo '&debug=1' ?>&width=1000">Log</a> &nbsp;&nbsp;
			<a class="button" target="_blank" href="<?php echo '/wp-content/log/log_ins_dsa.txt' ?>">Log Cron</a>
		</div>


		<div class="row">
			<?php
			

			echo 'Now: ' . date_i18n('Y-m-d H:i:s') . '<br>';

			$next_cron 		= wp_next_scheduled('cron_ins_dsa');
			$current_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;

			$last_cron = @filemtime(WP_CONTENT_DIR . '/log/log_ins_dsa_lastcron.txt');
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
	})
</script>