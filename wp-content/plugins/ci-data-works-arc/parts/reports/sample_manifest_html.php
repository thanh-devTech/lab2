<style>
	table th {
		text-align: left;
	}

	.checkbox {
		display: inline-block;
		width: 12px;
		height: 12px;
		border: 1px solid #ccc;
	}

	table th.clinic {
		border: none;
	}

	h4 {
		margin: 4px;
		font-size: 14px;
	}

	tr.no-result-warning td {
		background: #f8d7da;
		color: #721c24;
	}
</style>
<?php
$ref_assen_title = tr_get_option('ci_pdf_data', 'title_pid_field', 'PID');
$show_ref_assen = tr_get_option('ci_pdf_data', 'show_pid_field');
$is_no_results_tab = !empty($is_no_results_tab);
$no_results_hours = isset($no_results_hours) ? (int) $no_results_hours : 100;
$clinicsrp = array();
foreach ($results as $r) {
	if (!isset($clinicsrp[$r->cli_ID])) {
		$clinicsrp[$r->cli_ID] = array(
			'name' => $r->requesting_practice,
			'rows' => array()
		);
	}
	$clinicsrp[$r->cli_ID]['rows'][] = $r;
}
unset($results);
$counttbl = count($clinicsrp);
if ($counttbl == 0) {
	echo '<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-6 mb-6"><div class="border rounded-lg px-4 py-6 text-sm text-gray-500">No Results Found.</div></div>';
	return;
}
$icr = 0;
foreach ($clinicsrp as $arr) {
	$icr++;
?>
	<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-6 mb-6">
		<div class="border rounded-lg overflow-hidden">
			<div class="overflow-x-auto"></div>
			<?php if ($arr['name'] != '') { ?>
				<h4 class="center"><?php echo $arr['name'] ?></h4><?php } ?>
			<table class="w-full text-sm">
				<thead class="bg-gray-100 border-b">
					<tr>
						<?php if ($show_ref_assen) {
							echo '<th class="px-4 py-3 text-left font-medium text-gray-800">' . $ref_assen_title . '</th>';
						} ?>
						<th class="px-4 py-3 text-left font-medium text-gray-800">Accession</th>
						<th class="px-4 py-3 text-center font-medium text-gray-800">Practice</th>
						<th class="px-4 py-3 text-center font-medium text-gray-800">Ordering Physician</th>
						<th class="px-4 py-3 text-center font-medium text-gray-800">Patient</th>
						<?php if ($show_athena_id) {
							echo '<th style="width:10%" class="px-4 py-3 text-center font-medium text-gray-800">Athena ID</th>';
						} ?>
						<?php if ($show_panel) {
							echo '<th style="width:15%" class="px-4 py-3 text-center font-medium text-gray-800">Panel</th>';
						} ?>
						<?php if ($show_pa_mrn) {
							echo '<th class="px-4 py-3 text-center font-medium text-gray-800">PatientID</th>';
						} ?>
						<?php if ($is_no_results_tab) {
							echo '<th class="px-4 py-3 text-center font-medium text-gray-800">Sent At</th>';
							echo '<th class="px-4 py-3 text-center font-medium text-gray-800">Hours Since Sent</th>';
						} ?>
						<th class="px-4 py-3 text-center font-medium text-gray-800">Date</th>
						<th class="px-4 py-3 text-center font-medium text-gray-800">Sample Present?</th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ($arr['rows'] as $r) {
						$row_class = $is_no_results_tab && isset($r->sent_hours) && $r->sent_hours > $no_results_hours ? ' class="no-result-warning"' : '';
					?>
						<tr<?php echo $row_class ?>>
							<?php if ($show_ref_assen)
								echo '<td class="px-4 py-3 font-medium text-gray-900">' . $r->oldreq . '</td>' ?>
							<td class="px-4 py-3 font-medium text-gray-900"><?php echo $r->assen ?></td>
							<td class="px-4 py-3 font-medium text-gray-900"><?php echo $r->requesting_practice ?></td>
							<td class="px-4 py-3 font-medium text-gray-900"><?php echo $r->requesting_physician ?></td>
							<td class="px-4 py-3 font-medium text-gray-900"><?php echo $r->patient_name ?></td>
							<?php if ($show_athena_id) echo '<td class="px-4 py-3 font-medium text-gray-900">' . $r->cli_athena_departmentid . '</td>' ?>
							<?php if ($show_panel) {
								$panels = [];
								foreach (explode(',', $r->panel_tests_requested) as $t) {
									if (stripos($t, 'group') !== false) {
										$group_id = str_ireplace('group_', '', $t);
										if (isset($all_groups[$group_id])) {
											$panels[] = $all_groups[$group_id]->tg_name;
										}
									} else {
										if (isset($all_tests[$t]) && isset($all_groups[$all_tests[$t]])) {
											$panels[] = $all_groups[$all_tests[$t]]->tg_name;
										}
									}
								}
								echo '<td class="px-4 py-3 font-medium text-gray-900">' .  implode(', ', array_unique($panels)) . '</td>';
							}
							?>
							<?php if ($show_pa_mrn)
								echo '<td class="px-4 py-3 font-medium text-gray-900">' . $r->pa_mrn . '</td>' ?>
							<?php if ($is_no_results_tab) {
								echo '<td class="px-4 py-3 font-medium text-gray-900">' . $r->sent_at . '</td>';
								echo '<td class="px-4 py-3 font-medium text-gray-900">' . (int) $r->sent_hours . '</td>';
							} ?>
							<td class="px-4 py-3 font-medium text-gray-900"><?php echo $r->doc ?></td>
							<td class="center middle">
								<div class="checkbox"></div>
							</td>
						</tr>
					<?php
					}
					?>
				</tbody>
			</table>

		</div>
	</div>
<?php
}
?>
