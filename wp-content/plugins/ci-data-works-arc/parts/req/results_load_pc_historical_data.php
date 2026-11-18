<?php
$return = array('status' => 'ok', 'rows' => array());

$date = $_REQUEST['id'];

$plates = $wpdb->get_results("select * from ci_plates pl 
JOIN ci_racks ra ON ra.re_ID = pl.pl_rack_ID
where re_is_uti_panel = 82
and pl_today = '{$date}'
order by pl_ID desc", ARRAY_A);

$keys = array(
	'MSRE+|1',
	'MSRE-|1',
	'MSRE+|2',
	'MSRE-|2',
);


foreach ($plates as $plate) {

	if (empty($plate['re_subtitle'])) {
		$plate['re_subtitle'] = 'Plate #' . $plate['pl_number'];
	}
	$otherdata = json_decode($plate['other_data'], true);
	ob_start();
?>
	<tr class="child">
		<td colspan="2">
			<div class="center bold"><?php echo $plate['re_subtitle'] ?></div>
			<table class="wp-list-table  widefat striped posts blue_tbl hover_tbl">
				<thead>
					<tr>
						<th>Well</th>
						<th>Sample description 1</th>
						<th>Sample description 2</th>
						<th>Target</th>
						<th>Conc(copies/µL)</th>
						<th>DyeName(s)</th>
						<th>Accepted Droplets</th>
						<th>Positives</th>
						<th>Negatives</th>
						<th>Status</th>
					</tr>
				</thead>
				<tbody>
					<?php

					foreach ($keys as $key) {
						list($desc2, $target) = explode('|', $key);
						$rowd = $wpdb->get_row("select * from ci_req_qc_historical 
						where plate_ID = {$plate['pl_ID']} 
						and desc1 = 'PC'
						and desc2 = '{$desc2}' 
						and target = {$target} ", ARRAY_A);

					?>
						<tr>
							<td><?php echo $rowd['well'] ?></td>
							<td>PC</td>
							<td><?php echo $desc2 ?></td>
							<td><?php echo $target ?></td>
							<td><?php echo $rowd['conc'] ?></td>
							<td><?php echo $target == '1' ? 'FAM' : 'HEX' ?></td>
							<td><?php echo $rowd['accepted_droplets'] ?></td>
							<td><?php echo $rowd['positives'] ?></td>
							<td><?php echo $rowd['negatives'] ?></td>
							<td><?php echo $rowd['status'] == 1 ? 'PASS' : '' ?></td>
						</tr>
					<?php
					}
					?>
				</tbody>
			</table>
		</td>
	</tr>
<?php

	$return['rows'][] = ob_get_clean();
}

wp_send_json($return);
