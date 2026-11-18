<?php

if ($export_es_ls) {
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename=abx_debug_info.csv');
	$output = fopen('php://output', 'w');
	$headers = array('name' => 'Antibiotic');
	$antibiotics = array();
	foreach ($abx->getSelectedBacterias() as $bat) {
		$headers[$bat->ID . '_ES'] = 'ES (' . $bat->name . ')';
		$headers[$bat->ID . '_LS'] = 'LS (' . $bat->name . ')';

		foreach ($abx->get_scores($bat->ID, 'common') as $antiID => $scores) {
			if (!isset($antibiotics[$antiID])) {
				$antibiotics[$antiID] = array();
			}
			$antibiotics[$antiID][$bat->ID] = array(
				'ES' => isset($scores['ES']) ? $scores['ES'] : '',
				'LS' => isset($scores['LS']) ? $scores['LS'] : '',
			);
		}
	}
	fputcsv($output, $headers);

	foreach ($antibiotics as $name => $arr) {
		$row = array();
		foreach ($headers as $key => $header) {
			if ($key == 'name') {
				$row[] = $abx->abx_name($name);
			} else {
				list($batID, $type) = explode('_', $key);
				$row[] = isset($arr[$batID]) && isset($arr[$batID][$type]) ? $arr[$batID][$type] : '';
			}
		}
		fputcsv($output, $row);
	}

	fputcsv($output, array());
	fputcsv($output, array('Common Score Details'));
	fputcsv($output, array('Bacteria', 'Antibiotic', 'ES', 'RS', 'RM', 'LS', 'NTR', 'F', 'A', 'R', 'C', 'S', 'LT'));
	foreach ($abx->getSelectedBacterias() as $bat) {
		foreach ($abx->get_scores($bat->ID, 'common') as $antiID => $scores) {
			$row = array(
				$bat->name,
				isset($scores['name']) ? $scores['name'] : $abx->abx_name($antiID),
			);
			foreach (array('ES', 'RS', 'RM', 'LS', 'NTR', 'F', 'A', 'R', 'C', 'S', 'LT') as $key) {
				$row[] = isset($scores[$key]) ? $scores[$key] : '';
			}
			fputcsv($output, $row);
		}
	}


	exit;
}
add_thickbox();

if (isset($_GET['debugabx'])) {
	wp_print_scripts();
	wp_print_styles();
}

if (count($abx->getSelectedBacterias()) == 0) {
	return;
}

$download_url = Tr_Base_Class_V6::link(array('trajax' => 'export_es_ls'), false);
if (isset($_GET['tr_action'])) {
	$download_url = Tr_Base_Class_V6::link(array('export_es_ls' => '1', 'tr_action' => $_GET['tr_action']), false);
}
?>
<a href="<?php echo $download_url ?>" target="_blank">Export Debug Info</a>
<table class="reportabx_tbl  reportabx_tbl_debug tblborder">
	<thead>
		<tr>
			<th style="width:160px"></th>
			<th class="aleft vtop">NR</th>
			<?php
			foreach ($abx->getMarkers() as $marker) {
				echo '<th class="aleft vtop">';
				echo $marker->name;
				echo ' <span clss="small">(risk=' . $marker->risk . ')</span>';
				echo '</th>';
			}
			?>
			<th class="aleft vtop">Common</th>
		</tr>
	</thead>
	<tbody>
		<?php
		foreach ($abx->getSelectedBacterias() as $bat) {
			// var_dump($abx->scores[$bat->ID]);
			//  echo '<br><br><br>';
		?>
			<tr class="batrow" data-bacid="<?php echo $bat->ID ?>">
				<td class="vtop bold">
					<?php echo $bat->name ?>
					<br><br>
					<div><?php echo strtoupper(isset($bat->lmh) ? $bat->lmh : '') ?></div>
					<div>RISK=<?php echo $bat->risk ?></div>
					<div>NC=<?php echo $bat->nc ?></div>
				</td>
				<?php
				echo '<td class="marker" data-markerid="0" class="aleft vtop" style="font-size:9px;line-height:12px">';
				foreach ($abx->get_scores($bat->ID, 'NR') as $antiID => $scores) {
					echo '<li data-antiid="' . $antiID . '">';
					echo '<span class="abxname">';
					echo $abx->abx_name($antiID);
					echo '</span>';

					$scoresarr = array();
					foreach ($scores as $k => $vl) {
						if (in_array($k, array('AB', 'FS', 'TS'))) continue;
						$scoresarr[] = '<span class="col_' . $k . '">' . $k . '=' . $vl . '</span>';
					}
					echo '(' . implode(',', $scoresarr) . ')';
					echo '</li>';
				}
				echo '</td>';

				foreach ($abx->getMarkers() as $mkid => $marker) {
					echo '<td class="marker" data-markerid="' . $marker->ID . '" class="aleft vtop" style="font-size:9px;line-height:12px">';
					$has_abx = false;
					foreach ($abx->get_scores($bat->ID, $mkid) as $antiID => $scores) {
						echo '<li data-antiid="' . $antiID . '">';
						echo '<span class="abxname">';
						if (isset($scores['name'])) {
							echo $scores['name'];
						} else {
							echo $abx->abx_name($antiID);
						}
						echo '</span>';
						$scoresarr = array();
						foreach ($scores as $k => $vl) {
							if (in_array($k, array('AB', 'FS', 'TS', 'name'))) continue;
							$scoresarr[] = '<span class="col_' . $k . '">' . $k . '=' . $vl . '</span>';
						}
						echo '(' . implode(',', $scoresarr) . ')';

						echo '</li>';
						$has_abx = true;
					}
					if (!$has_abx) {
						echo 'N/A';
					}
					echo '</td>';
				}

				echo '<td class="aleft vtop marker" data-markerid="common" style="font-size:9px;line-height:12px">';
				foreach ($abx->get_scores($bat->ID, 'common') as $antiID => $scores) {
					echo '<li data-antiid="' . $antiID . '">';
					echo '<span class="abxname">';
					if (isset($scores['name'])) {
						echo $scores['name'];
					} else {
						echo $abx->abx_name($antiID);
					}
					echo '</span>';
					$scoresarr = array();
					foreach ($scores as $k => $vl) {
						if (in_array($k, array('AB', 'FS', 'TS', 'name', 'icon', 'abx_score'))) continue;
						$scoresarr[] =  '<span class="col_' . $k . '">' . $k . '=' . $vl . '</span>';
					}
					echo '(' . implode(',', $scoresarr) . ')';
					echo '</li>';
				}
				echo '</td>';
				?>
			</tr>
		<?php
		}
		?>
	</tbody>

</table>

<?php


$gram_types = array(
	'1' => 'G-',
	'2' => 'G- ( Aerobic Bacilli E )',
	'3' => 'G- ( Aerobic Bacilli NE )',
	'4' => 'G- ( Aerobic Bacilli NF )',
	'5' => 'G- ( Anaerobic Bacilli )',

	'7' => 'G+',
	'8' => 'G+ ( Aerobic Cocci )',
	'9' => 'G+ ( Aerobic Bacilli )',
	'10' => 'G+ ( Anaerobic )',

	'12' => 'NG',
	'13' => 'NG ( Aerobic WD )',

	'14' => 'Fungi',
	'15' => 'Viruses',
	'16' => 'Protozoa',
	'17' => 'Other'
);


foreach ($abx->debug_ls_by as $k => $arr) {
	list($batid, $marker_ID, $antiid) = explode('_', $k);
?>
	<div id="<?php echo 'div_' . $k ?>" style="display: none">
		<div class="table" style="border:1px solid #ddd">
			<?php

			$batname = $abx->selectedBacterias[$batid]->name;
			$classname = $marker_ID > 0 ? $abx->selectedMarkers[$marker_ID]->class : 'NR';

			echo '<h2>' . $batname . ' + ' . $classname . '</h2>';
			echo '<table class="lstable">';
			$count = 0;
			foreach ($arr as $ik => $a) {
				$count++;

				$aks = explode('-', $ik);
				$batid = $aks[0];
				$mk = isset($aks[1]) && $aks[1] > 0 ? $classname : '';
				$batname1 = $batname;
				if (stripos($batid, 'gram') !== false) {
					list($tpm, $gram_type) = explode('_', $batid);
					$batname1 = $gram_types[$gram_type];
				}

				$sourcenum = isset($aks[4]) && stripos($aks[4], '3') > 0 ? 'source 3' : 'source 2';
				if ($batid === '0') $sourcenum = 'Source 1';

				echo '<tr class="' . ($a[1] > 0 ? 'selected' : '') . '" title="' . $ik . '" keyselected="' . $a[2] . '">';
				echo '<td>' . $count . '</td>';
				echo '<td>' . $batname1 . '</td>';
				echo '<td>' . $mk . '</td>';
				echo '<td>' . strtoupper(isset($aks[3]) ? $aks[3] : '') . '</td>'; //state
				echo '<td>' . $sourcenum . '</td>'; //score2/3
				echo '<td>' . $a[0] . '</td>';
				echo '</tr>';
			}
			echo '</table>';
			?>
		</div>
	</div>
<?php
}
?>
<div id="div_common" style="display:none">
	<div class="content_common">...</div>
</div>

<script>
	jQuery(function($) {
		$('body').on('click', '.col_LS', function() {
			var $li = $(this).parents('li');
			var $tr = $(this).parents('tr.batrow');
			var $td = $(this).parents('td.marker');
			var $antiid = $li.data('antiid');
			var $batid = $tr.data('bacid');
			var $markerid = $td.data('markerid');
			var $abx = $('.abxname', $li).html();
			var $id = 'div_' + $batid + '_' + $markerid + '_' + $antiid;
			if ($markerid == 'common') {
				$id = 'div_common&width=1200';
				var $html = '<table class="lstblcommon"><tr>';
				$('td.marker', $tr).each(function() {
					let $mkid = $(this).data('markerid');
					if ($mkid == 'common') return;
					$html += '<td>' + $('#' + 'div_' + $batid + '_' + $mkid + '_' + $antiid).html() + '</td>';
				})
				$html += '</tr></table>';
				$('#div_common .content_common').html($html);
			}

			tb_show($abx, '#TB_inline?&width=600&height=550&inlineId=' + $id);
		})
	})
</script>

<style>
	.reportabx_tbl_debug td * {
		font-size: 10px;
		line-height: 12px;
	}

	.reportabx_tbl_debug td,
	.reportabx_tbl_debug td:last-child {
		border: 1px solid #ddd;

	}

	.reportabx_tbl .td {
		border-bottom: 1px solid #ddd !important;
	}

	.lstable,
	.lstblcommon {
		width: 100%;
	}

	.lstblcommon td {
		vertical-align: top;
	}

	.lstable tr td {
		padding: 5px;
	}

	.lstable tr.selected {
		background: #ff5858;
		color: #fff;
	}

	.col_LS {
		color: #ba2626;
		cursor: pointer;
	}

	.col_ES {
		color: #9c9c0a;
	}

	.col_RS {
		color: #0a9c0f;
	}

	.col_RM {
		color: #d631a1;
	}

	.col_NTR {
		color: #9731d6;
	}

	.col_A {
		color: #009194;
	}

	.col_R {
		color: #1419bc;
	}

	.col_C {
		color: #b57700;
	}

	.col_S {
		color: #ff5858;
	}

	.col_LT {
		color: #722791;
	}

	.logdebug {
		line-height: 12px;
		;
	}
</style>
