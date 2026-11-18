 <?php
	uasort($this->selectedBacterias, function ($a, $b) {
		return $a->bvtypenum < $b->bvtypenum ? -1 : 1;
	});
	?>
 <table class="reportabx_tbl  reportabx_tbl_debug tblborder">
 	<thead>
 		<tr>
 			<th>Name</th>
 			<th>CT</th>
 			<th>Risk</th>
 			<th>RA</th>
 			<th title="ra_percent">%</th>
 			<th title="load">Load</th>
 			<th title="percent_panel">% Pathogen_Panel</th>
 			<th>Score</th>
 			<th>LB Score</th>
 			<th>BVM Score</th>
 		</tr>
 	</thead>
 	<tbody>
 		<?php
			$sumra = 0;
			$sumload = 0;
			foreach ($this->selectedBacterias as $bid => $bat) {
				$sumra += $bat->ra;
				$sumload += $bat->load;
			?>
 			<tr>
 				<td class="center"><?php echo $bat->name . '(' . $bat->bv_type . ')' ?></td>
 				<td class="center"><?php echo $bat->conc ?></td>
 				<td class="center"><?php echo $bat->risk ?></td>
 				<td class="center"><?php echo number_format($bat->ra, 8) ?></td>
 				<td class="center"><?php echo $bat->ra_percent ?></td>
 				<td class="center"><?php echo number_format($bat->load, 2) ?></td>
 				<td class="center"><?php echo number_format(isset($bat->percent_panel) ? $bat->percent_panel : 0, 2) . '%' ?></td>
 				<td class="center" title="<?php echo isset($bat->t_min) ? $bat->t_min . '|' . $bat->t_max : '' ?>"><?php echo isset($bat->score) ? $bat->score : 0 ?></td>
 				<td class="center"><?php echo isset($bat->lb_score) ? $bat->lb_score : 0 ?></td>
 				<td class="center"><?php echo $this->vm_scores['bv'] ?></td>
 			</tr>
 		<?php
			}
			?>
 	</tbody>
 	<tfoot>
 		<tr>
 			<th>Total</th>
 			<th></th>
 			<th></th>
 			<th><?php echo number_format($sumra, 8) ?></td>
 			<th></th>
 			<th><?php echo number_format($sumload, 1) ?></th>
 			<th><?php echo '' ?></th>
 			<th><?php echo number_format($this->vm_scores['all'], 2) ?></th>
 			<th><?php echo number_format($this->vm_scores['lb'], 2) ?></th>
 			<th><?php echo number_format($this->vm_scores['bv'], 2) ?></th>
 		</tr>
 	</tfoot>
 </table>
 <br><br>
 <?php
	$allabxs = array();
	foreach ($this->bv_abx_scores as $bid => $abxs) {
		if ($bid > 0) {
			foreach ($abxs as $abxkey => $arr) {
				$allabxs[$abxkey] = $arr['name'];
			}
		}
	}

	?>
 <table class="reportabx_tbl  reportabx_tbl_debug tblborder">
 	<thead>
 		<tr>
 			<th></th>
 			<th>Name</th>
 			<th>%</th>
 			<?php foreach ($allabxs as $abx) {
					echo '<th>' . $abx . '</th>';
					echo '<th>X</th>';
				}
				?>
 		</tr>
 	</thead>
 	<tbody>
 		<?php
			$typekeys = array('av', 'ur', 'bv', 'yeast');
			foreach ($typekeys as $k) {
				$bvkey = str_replace('bv', 'bvo', $k);
				if (count($this->bv_bac_Ids[$bvkey]) > 0) {
					$typett = strtoupper($k);
			?>
 				<tr class="bvrow">
 					<td></td>
 					<td class="center"><?php echo 'Sum ' . $typett  ?></td>
 					<td class="center">100</td>
 					<?php
						foreach ($allabxs as $abxkey => $abx) {
							echo '<td class="center">' . number_format($this->bv_abx_scores[$k][$abxkey]['x'], 1) . '</td>';
							echo '<td class="center">' . number_format($this->bv_abx_scores[$k][$abxkey]['x'], 1) . '</td>';
						}
						?>
 				</tr>
 				<?php
					foreach ($this->bv_bac_Ids[$bvkey] as $bid) {
						$bat = $this->selectedBacterias[$bid];
					?>
 					<tr>
 						<td><?php echo $typett ?></td>
 						<td class="center"><?php echo $bat->name  ?></td>
 						<td class="center"><?php echo $bat->percent_panel > 0 ? number_format($bat->percent_panel, 1) : 0 ?></td>
 						<?php
							foreach ($allabxs as $abxkey => $abx) {
								$arrk 	= isset($this->bv_abx_scores[$bid][$abxkey]) ? $this->bv_abx_scores[$bid][$abxkey] : array();
								$score 	= isset($arrk['score']) ? $arrk['score'] : 0;
								$x 		= isset($arrk['x']) ? $arrk['x'] : 0;

								echo '<td class="center">' . number_format($score, 1) . '</td>';
								echo '<td class="center">' . number_format($x, 1) . '</td>';
							}
							?>
 					</tr>
 		<?php
					}
				}
			}

			?>
 	</tbody>

 </table>
 <br><br>
 <?php

	foreach (array('av', 'ur', 'bv', 'yeast') as $t) {
		if (count($this->bv_abxs[$t . '_gram']) > 0) {
			echo '<div>';
			echo strtoupper($t) . ': ';
			echo implode(', ', $this->bv_abxs[$t . '_gram']);
			echo '</div>';
		}
	}

	?>

 <style>
 	.dompdf_new #footer {
 		display: none !important;
 	}

 	.reportabx_tbl tr th {
 		border: 1px solid #ddd;
 		text-align: center;
 	}

 	tr.bvrow td {
 		font-weight: bold;
 	}
 </style>