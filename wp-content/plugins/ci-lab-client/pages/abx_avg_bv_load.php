<?php
global $wpdb, $tableconfig, $ci_allways_cache_tests;

$ci_allways_cache_tests = true;

$allrptypes = array(
	'0' => 'All by hand',
	'1' => 'Healthy by hand',
	'2' => 'Not Healthy by hand',
	'3' => 'Healthy auto',
	'4' => 'Not Healthy Auto',
	'5' => 'All Auto',
);

$act 			= isset($_REQUEST['act']) ? $_REQUEST['act'] : '';
$report_type 	= isset($_REQUEST['rptype']) ? $_REQUEST['rptype'] : '0';

if ($act == 'savelink') {
	$links = get_option('ci_abx_links', array());
	if (isset($_POST['removeall']) && $_POST['removeall']) {
		$links = array();
	}
	$_POST['link'] = str_replace("\n", "\r", $_POST['link']);
	$linkarr = explode("\r", $_POST['link']);
	foreach ($linkarr as $l) {
		$l = trim($l);
		if (strlen($l) > 5 && !in_array($l, $links)) {
			$links[] = $l;
		}
	}



	update_option('ci_abx_links', $links);
	Tr_Session_Class::add("Added successfully!");
}


if ($act == 'add') {
?>
	<form method="post">
		<div class="frow">
			<label><?php echo defined('TRCPDD_PATH') ? 'Accessions' : 'Links' ?></label>
			<textarea name="link"></textarea>
		</div>
		<div class="frow">
			<label><input type="checkbox" name="removeall" value="1" /> Remove all before add</label>
		</div>
		<div class="frow">
			<input type="submit" class="button" value="Add" />
			<input type="hidden" name="act" value="savelink" />
		</div>
	</form>
<?php
	exit;
}
add_thickbox();

//parse link

function trget_abx_scores($link)
{
	global $wpdb;

	$only_get_score = true; //important
	$assen = '';
	if (defined('TRCPDD_PATH')) {
		$assen = $link;
		$report = array();
		$id = $_REQUEST['id']  = $wpdb->get_var("select id from ci_reports_pcr where patient_id = '{$assen}' ");
		$link = '?tr_action=ci_results_pdf&debugabx&shownewabx=1&btype=pcr&id=' . $id;
		if (empty($id)) return array();
		try {
			ob_start();
			$_REQUEST['debugabx'] = $_GET['debugabx'] = 1;
			$_REQUEST['shownewabx'] = $_GET['shownewabx'] = 1;
			include(TRCPDD_PATH . 'reports/abx.php');
			ob_get_clean();
		} catch (Exception $e) {
			var_dump($e);
			return array();
		}

		if ($abx_type != 'v') return array();
	} else {

		parse_str($link, $arr);
		foreach ($arr as $k => $vl) {
			$_GET[$k] = $vl;
			$_REQUEST[$k] = $vl;
		}
		ob_start();
		include('abx_test2.php');
		ob_get_clean();
	}


	$total_load			= $abx->get_BV_total_load();
	$risktexts = array();
	if ($total_load > 0) {
		if ($total_load < 1000) {

			$risktexts[] = 'VL';
		} else if ($total_load < 10000) {
			$risktexts[] = 'L';
		} else if ($total_load < 100000) {
			$risktexts[] = 'M';
		} else if ($total_load < 1000000) {
			$risktexts[] = 'H';
		} else {
			$risktexts[] = 'VH';
		}
	}

	$yeast_load 		= $abx->yeast_total_load;
	if ($yeast_load > 0) {
		if ($total_load < 1000) {

			$risktexts[] = 'C-VL';
		} else if ($total_load < 10000) {
			$risktexts[] = 'C-L';
		} else if ($total_load < 100000) {
			$risktexts[] = 'C-M';
		} else if ($total_load < 1000000) {
			$risktexts[] = 'C-H';
		} else {
			$risktexts[] = 'C-VH';
		}
	}
	$risktext = implode(' + ', $risktexts);

	$bacs = array();
	//var_dump($abx->selectedBacterias);
	foreach ($abx->selectedBacterias as $bid => $bat) {


		if ($bat->conc == 40) continue;

		$lbsum = $abx->getBV_Lactobacillus_Percent();
		$lbm	= $abx->vm_scores['lbm'];
		$bvr	= $abx->vm_scores['bvr'];
		$bvm	= $abx->vm_scores['bv'];
		$lbr	= $abx->vm_scores['lbr'];
		$bvrisk2	= $abx->panel_risks['bv2'];

		$healthy = '';
		if ($lbsum > 90) {
			$healthy = 'yes';
		} else if ($lbsum < 10) {
			$healthy = 'no';
		}

		$bacs[$bid] = array(
			'name' 		=> $bat->name . '(' . $bat->bv_type . ')',
			'link' 		=> $link,
			'assen' 	=> $assen,
			'risktext' 	=> $risktext,
			'conc' 		=> $bat->conc,
			'ra' 		=> number_format($bat->ra, 10),
			'ra_percent' => number_format($bat->ra_percent, 2),
			'load' 		=> number_format($bat->load, 2),
			'percent_panel' => number_format($bat->percent_panel, 2),
			'score' 	=> number_format($bat->score, 2),
			'lb_score' 	=> number_format($bat->lb_score, 2),
			'lb_sum' 	=> number_format($lbsum, 2),
			'lbm' 	=> number_format($lbm, 2),
			'bvr' 	=> number_format($bvr, 2),
			'bvm' 	=> number_format($bvm, 2),
			'lbr' 	=> number_format($lbr, 2),
			'bvrisk2' 	=> number_format($bvrisk2, 2),
			'healthy'	=> $healthy,
		);
	}

	return array('bacs' => $bacs);
}

$fromdate = isset($_GET['fdate']) ? $_GET['fdate'] : '2024-12-20';
$todate = isset($_GET['tdate']) ? $_GET['tdate'] : '';
$links = get_option('ci_abx_links', array());
if (in_array($report_type, array(3, 4, 5))) {



	$panelids = $wpdb->get_col("select tg_ID from ci_test_groups 
	where tg_type = 'pcr'
	and tg_show_req = 1
	and tg_other_options like '%new_abx\":\"1%'
	and tg_other_options like '%abx_type\":\"v%'
	");

	$where_panels = array();
	foreach ($panelids as $pid) {
		$where_panels[] = " FIND_IN_SET('GROUP_{$pid}',panel_tests_requested)";
	}
	$where_panels = ' and (' . implode(' or ', $where_panels) . ')';

	$sql = "select ba.assen 
	from ci_batches_pcr ba
	join ci_reports_pcr re ON re.patient_id = ba.assen
	where re.estatus = 'C'
	and doc >='{$fromdate}'
	 {$where_panels}  
	 order by ba.batch_id desc
	";
	// and re.has_uti > 0
	$links = $wpdb->get_col($sql);

	if (isset($_GET['debug'])) {
		echo $sql;
	}
}
$bacterias = array();
foreach ($links as $link) {
	if (empty($link)) continue;
	$arr = trget_abx_scores($link);
	if (!isset($arr['bacs'])) continue;

	foreach ($arr['bacs'] as $bid => $arr) {
		if (!isset($bacterias[$bid])) {
			$bacterias[$bid] = array(
				'name' => $arr['name'],
				'concs' => array(),
				'loads' => array(),
				'hlbs' => array(),
				'nhlbs' => array(),
				'hlloads' => array(),
				'nhlloads' => array(),
				'arrs' => array(),
			);
		}

		$rk = md5(implode('_', $arr));

		if (!isset($bacterias[$bid]['arrs'][$rk])) {
			$load = str_replace(',', '', $arr['load']);
			$conc = str_replace(',', '', $arr['conc']);


			if ($arr['healthy'] === 'yes') {
				if (in_array($report_type, array(2, 4))) {
					continue;
				}
				$bacterias[$bid]['hlbs'][] = $conc;
				$bacterias[$bid]['hlloads'][] = $load;
			} else if ($arr['healthy'] === 'no') {
				if (in_array($report_type, array(1, 3))) {
					continue;
				}

				$bacterias[$bid]['nhlbs'][] = $conc;
				$bacterias[$bid]['nhlloads'][] = $load;
			} else if (in_array($report_type, array(3, 4))) {
				continue;
			}

			$bacterias[$bid]['concs'][] = $conc;
			$bacterias[$bid]['loads'][] = $load;

			$bacterias[$bid]['arrs'][$rk] = $arr;
		}
	}
}

foreach ($bacterias as $bid => $bat) {
	$bacterias[$bid]['avg_conc'] = number_format(array_sum($bat['concs']) / count($bat['concs']), 2);
	$bacterias[$bid]['avg_load'] = number_format(array_sum($bat['loads']) / count($bat['loads']), 2);
	$bacterias[$bid]['avg_conc_healthy'] = count($bat['hlbs']) > 0 ? number_format(array_sum($bat['hlbs']) / count($bat['hlbs']), 2) : '';
	$bacterias[$bid]['avg_conc_not_healthy'] = count($bat['nhlbs']) > 0 ? number_format(array_sum($bat['nhlbs']) / count($bat['nhlbs']), 2) : '';
	$bacterias[$bid]['avg_load_healthy'] = count($bat['hlloads']) > 0 ? number_format(array_sum($bat['hlloads']) / count($bat['hlloads']), 2) : '';
	$bacterias[$bid]['avg_load_not_healthy'] = count($bat['nhlloads']) > 0 ? number_format(array_sum($bat['nhlloads']) / count($bat['nhlloads']), 2) : '';
}

if ($act == 'export') {

	$filename = 'avg_bv_load.csv';
	header('Content-Type: application/csv');
	header('Content-Disposition: attachment; filename=' . $filename);
	header('Pragma: no-cache');
	$f = fopen('php://output', 'w');
	$header = array('Link', ' ', 'Name', 'CT', 'RA', '%', 'Load', '% Pathogen_Panel', 'Score', 'LB Score', 'LB%', 'LBM', 'BVR', 'BVM');
	fputcsv($f, $header);
	foreach ($bacterias as $bat) {
		if (count($bat['arrs']) == 0) continue;
		fputcsv($f, array(' '));
		$rowdata = array(
			$bat['name'],
			' ',
			'Avg Healthy CT: ' . $bat['avg_conc_healthy'] . ' | Avg Not-Healthy CT: ' . $bat['avg_conc_not_healthy'],
			'Avg CT: ' . $bat['avg_conc'],
			' ',
			' ',
			'Avg Load: ' . $bat['avg_load'],
			'Avg Healthy Load: ' . $bat['avg_load_healthy'] . ' | Avg Not-Healthy Load: ' . $bat['avg_load_not_healthy'],

		);
		fputcsv($f, $rowdata);

		foreach ($bat['arrs'] as $row) {
			if (isset($row['assen']) && !empty($row['assen'])) {
				$row['link'] = $row['assen']; //. ' ' . $row['link'];
			}
			$rowdata = array(
				$row['link'],
				$row['risktext'],
				$row['name'],
				$row['conc'],
				$row['ra'],
				$row['ra_percent'],
				$row['load'],
				$row['percent_panel'],
				$row['score'],
				$row['lb_score'],
				$row['lb_sum'],
				$row['lbm'],
				$row['bvr'],
				$row['bvm'],
			);
			fputcsv($f, $rowdata);
		}
	}
	exit;
}
?>
<div class="wrap">
	<h2></h2>
	<form method="get">
		<a class="thickbox button" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'add')) ?>">Add</a>
		<a class=" button" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'export')) ?>">Export</a>
		<?php if (defined('TRCPDD_PATH')) {

			wp_enqueue_script('jquery-ui', '', array('jquery'));
			wp_enqueue_script('jquery-ui-datepicker');
			wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');
		?>

			<input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
			<input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
			<select name="rptype" onchange="this.form.submit()">
				<?php
				foreach ($allrptypes as $k => $txt) {
					$selected = $k == $report_type ? 'selected' : '';
					echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
				}
				?>
			</select>
			<label>From</label>
			<input type="search" name="fdate" id="from" class="hasdate" value="<?php echo $fromdate ?>" onchange="this.form.submit()" />
			<script>
				jQuery(function($) {
					$("#from").datepicker({
						changeMonth: true,
						changeYear: true,
						numberOfMonths: 1,
						dateFormat: 'yy-mm-dd'
					});
				})
			</script>
		<?php } ?>
	</form>
	<?php Tr_Session_Class::show() ?>
	<?php echo 'count: ' . count($links); ?>
	<table class="agvtbl blue_tbl wp-list-table widefat striped posts hover_tbl read_only">
		<thead class="sticky">
			<tr>
				<th>Link</th>
				<th></th>
				<th>CT</th>
				<th>RA</th>
				<th>%</th>
				<th>Load</th>
				<th>% Pathogen_Panel</th>
				<th>Score</th>
				<th>LB Score</th>
				<th>LB %</th>
				<th>LBM</th>
				<th>LBR</th>
				<th>BVR</th>
				<th>BV risk2</th>
				<th>BVM</th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ($bacterias as $bat) {
				if (count($bat['arrs']) == 0) continue;
			?>
				<tr class="sticky batcol">
					<td colspan="2"><?php echo $bat['name'] ?></td>

					<td class="center">
						<div>Avg CT:</div>
						<div><?php echo $bat['avg_conc'] ?></div>
					</td>
					<td colspan="2">
						<div>Avg Healthy CT: <?php echo $bat['avg_conc_healthy'] ?></div>
						<div>Avg Not Healthy CT: <?php echo $bat['avg_conc_not_healthy'] ?></div>
					</td>
					<td class="center">
						<div>Avg Load:</div>
						<div><?php echo $bat['avg_load'] ?></div>
					</td>
					<td colspan="9">
						<div>Avg Healthy Load: <?php echo $bat['avg_load_healthy'] ?></div>
						<div>Avg Not Healthy Load: <?php echo $bat['avg_load_not_healthy'] ?></div>
					</td>
				</tr>
				<?php
				foreach ($bat['arrs'] as $row) {
					$row_class = '';
					if ($row['healthy'] === 'yes') {
						$row_class .= ' healthy';
					} else if ($row['healthy'] === 'no') {
						$row_class .= ' not_healthy';
					}
				?>
					<tr class="<?php echo $row_class ?>">
						<td>
							<?php
							if (isset($row['assen']) && !empty($row['assen'])) {
								echo '<span>' . $row['assen'] . ' </span> ';
							}
							?>
							<a href="<?php echo $row['link'] ?>" target="_blank">link</a>

						</td>
						<td><?php echo $row['risktext'] ?></td>
						<td class="center"><?php echo $row['conc'] ?></td>
						<td class="center"><?php echo $row['ra'] ?></td>
						<td class="center"><?php echo $row['ra_percent'] ?></td>
						<td class="center"><?php echo $row['load'] ?></td>
						<td class="center"><?php echo $row['percent_panel'] ?></td>
						<td class="center"><?php echo $row['score'] ?></td>
						<td class="center"><?php echo $row['lb_score'] ?></td>
						<td class="center"><?php echo $row['lb_sum'] ?></td>
						<td class="center"><?php echo $row['lbm'] ?></td>
						<td class="center"><?php echo $row['lbr'] ?></td>
						<td class="center"><?php echo $row['bvr'] ?></td>
						<td class="center"><?php echo $row['bvrisk2'] ?></td>
						<td class="center"><?php echo $row['bvm'] ?></td>
					</tr>
			<?php
				}
			}
			?>
		</tbody>
	</table>
</div>
<style>
	.agvtbl {
		border-collapse: collapse;
	}

	.agvtbl th {
		text-align: center;
	}

	.batcol td {
		font-weight: bold;
	}

	.agvtbl td {
		border: 1px solid #ddd !important;
	}

	.agvtbl tr.healthy td {
		background: #d5ebd5;
	}

	.agvtbl tr.not_healthy td {
		background: #ebd5dd;
	}
</style>