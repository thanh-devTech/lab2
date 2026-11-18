<?php
global $wpdb;
include_once('functions_req_qc.php');

if ($act == 'load_table_child') {
	include('results_load_pc_historical_data.php');
	exit;
}


$avgdata = array();
$keys = array(
	'PC|MSRE+|1',
	'PC|MSRE-|1',
	'PC|MSRE+|2',
	'PC|MSRE-|2',
);

$avg_stdev = tr_pdd_get_pc_historcal();

foreach ($keys as $key) {
	list($pttype, $desc2, $target) = explode('|', $key);

	if ($desc2 == 'MSRE-' && $target == 2) {
		$avg = '';
		$stdev = '';
	} else {
		$target_name = $target == 1 ? 'Insulin' : 'TTC5';
		$akey = $target==2? 'TTC5': $desc2.'_'.$target_name;
		$avg = $avg_stdev['AVG_' . $akey];
		$stdev = $avg_stdev['STD_' . $akey];
	}


	$avgdata[] = array(
		'desc2' 	=> $desc2,
		'target' 	=> $target,
		'average' 	=> $avg,
		'stdev' 	=> $stdev,
	);
}

$dates = $wpdb->get_results("select * from ci_plates pl 
JOIN ci_racks ra ON ra.re_ID = pl.pl_rack_ID
where re_is_uti_panel = 82
group by pl_today order by pl_ID desc");
include_once(TRCPDD_PATH . 'pages/formdata.php');
?>
<div class="wrap">
	<h2>PC Historical Data</h2>
	<?php Tr_Session_Class::show() ?>
	<br><br>
	<table class="wp-list-table  widefat striped posts blue_tbl hover_tbl " style="width:500px">
		<thead>
			<th>Description 2</th>
			<th>Target</th>
			<th>Historical AVERAGE</th>
			<th>Historical STDEV</th>
		</thead>
		<tbody>
			<?php
			foreach ($avgdata as $row) {
			?>
				<tr>
					<td><?php echo $row['desc2'] ?></td>
					<td><?php echo $row['target'] ?></td>
					<td><?php echo !empty($row['average']) ? number_format($row['average'], 2) : '' ?></td>
					<td><?php echo !empty($row['stdev']) ? number_format($row['stdev'], 2) : '' ?></td>
				</tr>
			<?php
			}
			?>
		</tbody>
	</table>
	<br><br>

	<div class="wraptbl">
		<table id="main_tbl" class="accordion_tbl wp-list-table widefat fixed striped posts blue_tbl hover_tbl report_tbl">
			<thead>
				<tr>
					<th style="width:20px"></th>
					<th>Date</th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ($dates as $row) {
				?>
					<tr class="parent" data-id="<?php echo $row->pl_today ?>">
						<td><i></i></td>
						<td><?php echo $row->pl_today ?></td>
					</tr>
				<?php
				}
				?>
			</tbody>
		</table>
	</div>
</div>

<script src="<?php echo TRCPDD_URL . 'js/admin_accordion.js?v=' . time() ?>"></script>
<script>
	jQuery(function($) {
		$('#main_tbl').admin_accordion();
	})
</script>