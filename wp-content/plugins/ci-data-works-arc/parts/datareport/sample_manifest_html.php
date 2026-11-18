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
<h2 class="center">Sample Manifest<?php echo !empty($is_no_results_tab) ? ' - No Results' : '' ?></h2>

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
	echo '<p class="center">No records found.</p>';
	return;
}
$icr = 0;
foreach ($clinicsrp as $arr) {
	$icr++;
?>
	<h4 class="center"><?php echo $arr['name'] ?></h4>
	<table class="tdborder">
		<thead>
			<tr>
				<?php if ($show_ref_assen) {
					echo '<th style="width:10%">' . $ref_assen_title . '</th>';
				} ?>
				<th style="width:10%">Accession</th>
				<th style="width:10%">Practice</th>
				<th style="width:15%">Ordering Physician</th>
				<th style="width:15%">Patient</th>
				<?php if ($show_athena_id) {
					echo '<th style="width:10%">Athena ID</th>';
				} ?>
				<?php if ($show_panel) {
					echo '<th style="width:15%">Panel</th>';
				} ?>
				<?php if ($show_pa_mrn) {
					echo '<th style="width:10%">PatientID</th>';
				} ?>
				<?php if ($is_no_results_tab) {
					echo '<th style="width:12%">Sent At</th>';
					echo '<th style="width:8%">Hours Since Sent</th>';
				} ?>
				<th style="width:15%">Date</th>
				<th style="width:12%">Sample Present?</th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ($arr['rows'] as $r) {
				$row_class = $is_no_results_tab && isset($r->sent_hours) && $r->sent_hours > $no_results_hours ? ' class="no-result-warning"' : '';
			?>
				<tr<?php echo $row_class ?>>
					<?php if ($show_ref_assen) echo '<td>' . $r->oldreq . '</td>' ?>
					<td><?php echo $r->assen ?></td>
					<td><?php echo $r->requesting_practice ?></td>
					<td><?php echo $r->requesting_physician ?></td>
					<td><?php echo $r->patient_name ?></td>
					<?php if ($show_athena_id) echo '<td>' . $r->cli_athena_departmentid . '</td>' ?>
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

						echo '<td>' .  implode(', ', array_unique($panels)) . '</td>';
					}
					?>
					<?php if ($show_pa_mrn) echo '<td>' . $r->pa_mrn . '</td>' ?>
					<?php if ($is_no_results_tab) {
						echo '<td>' . $r->sent_at . '</td>';
						echo '<td>' . (int) $r->sent_hours . '</td>';
					} ?>
					<td><?php echo $r->doc ?></td>
					<td class="center middle">
						<div class="checkbox"></div>
					</td>
				</tr>
			<?php
			}
			?>
		</tbody>
	</table>
<?php
	if ($icr < $counttbl) echo '<br>';
}
?>
