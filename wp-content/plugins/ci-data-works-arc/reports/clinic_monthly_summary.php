<?php
/*
 * For Report PCR Summary
 */
global $wpdb, $current_role;
global $count_row_in_page, $max_row_per_page, $group_pos_negs;


function trpcr_sum_get_tests_by_groups($gid, $results)
{
	global $wpdb;
	$has_hml = null;
	$tests = $wpdb->get_results("select te.te_name,
			te.te_abrev,
			te.te_abrev2
			from ci_test_panels te
			where te.te_group_id = {$gid} 
			and te_is_control = 0
			order by te.te_position asc");

	$test_rs 		= array();

	foreach ($tests as $test) {

		$drug 	= strtoupper($test->te_name) . '_' . $gid;
		$drug1 	= strtoupper($test->te_abrev) . '_' . $gid;
		$drug2 	= strtoupper($test->te_abrev2) . '_' . $gid;
		if (!empty($drug1) && isset($results[$drug1])) {
			$drug = $drug1;
		}
		if (!empty($drug2) && isset($results[$drug2])) {
			$drug = $drug2;
		}

		if (isset($results[$drug])) {
			$arr = $results[$drug];

			if (!isset($test_rs[$test->te_name])) {
				$test_rs[$test->te_name] = array(
					'H' => 0,
					'M' => 0,
					'L' => 0,
					'P' => 0,
					'N' => 0,
				);
			}

			$test_rs[$test->te_name]['H'] += $arr['H'];
			$test_rs[$test->te_name]['M'] += $arr['M'];
			$test_rs[$test->te_name]['L'] += $arr['L'];
			$test_rs[$test->te_name]['P'] += $arr['P'];
			$test_rs[$test->te_name]['N'] += $arr['N'];

			if ($arr['H'] + $arr['M'] + $arr['L'] > 0) {
				$has_hml = true;
			} else if (is_null($has_hml) && $arr['P']) {
				$has_hml = false;
			}
		}
	}

	return array(
		'tests' => $test_rs,
		'has_hml' => $has_hml,
	);
}

function trpcr_show_results_tests($grouparr)
{
	global $count_row_in_page, $max_row_per_page, $group_pos_negs;
	$has_group = false;
	$need_break_page = false;
	$start_row_count = $count_row_in_page;

	$count_row_in_page++;

	$html = '<table class="border ' . ($need_break_page ? 'break' : '') . '">';
	$html .= '<thead>';
	$html .= '<tr>';
	$html .= '<th>' . ($grouparr['parent_id'] > 0 ? $grouparr['name'] : '') . '</th>';
	if (!$grouparr['is_high_med_low']) {

		$html .= '<th style="width:80px;">Detected</th>';
		$html .= '<th style="width:100px;">Not Detected</th>';
		$html .= '<th style="width:10px;"></th>';
		$html .= '<th style="width:110px">% Detected</th>';
		$html .= '<th style="width:110px">% Not Detected</th>';
	} else {
		$html .= '<th style="width:50px">High</th>';
		$html .= '<th style="width:60px">Medium</th>';
		$html .= '<th style="width:50px">Low</th>';
		$html .= '<th style="width:10px"></th>';
		$html .= '<th style="width:70px">% of High</th>';
		$html .= '<th style="width:86px">% of Medium</th>';
		$html .= '<th style="width:56px">% of Low</th>';
	}

	$html .= '</tr>';
	$html .= '</thead>';

	foreach ($grouparr['tests'] as $drug => $arr) {
		$arr['HP'] = '';
		$arr['MP'] = '';
		$arr['LP'] = '';
		$arr['total'] = $arr['H'] + $arr['M'] + $arr['L'];

		if (!$grouparr['is_high_med_low']) {
			$arr['total'] = $arr['P'] + $arr['N'];
		}

		if ($arr['total'] > 0) {

			$has_group = true;
			if (!$grouparr['is_high_med_low']) {
				$arr['M'] = $arr['P'];
				$arr['L'] = $arr['N'];

				$arr['MP'] = number_format($arr['M'] / $arr['total'] * 100, 2) . '%';
				$arr['LP'] = number_format($arr['L'] / $arr['total'] * 100, 2) . '%';
			} else {
				$arr['HP'] = number_format($arr['H'] / $arr['total'] * 100, 2) . '%';
				$arr['MP'] = number_format($arr['M'] / $arr['total'] * 100, 2) . '%';
				$arr['LP'] = number_format($arr['L'] / $arr['total'] * 100, 2) . '%';
			}
		}

		$html .= '<tr>';
		$html .= '<th class="aleft">' . $drug . '</th>';
		if ($grouparr['is_high_med_low']) {
			$html .= '<td>' . $arr['H'] . '</td>';
		}
		$html .= '<td>' . $arr['M'] . '</td>';
		$html .= '<td>' . $arr['L'] . '</td>';
		$html .= '<td class="bgblack"></td>';
		if ($grouparr['is_high_med_low']) {
			$html .= '<td>' . $arr['HP'] . '</td>';
		}
		$html .= '<td>' . $arr['MP'] . '</td>';
		$html .= '<td>' . $arr['LP'] . '</td>';
		$html .= '</tr>';

		$count_row_in_page++;
		if ($count_row_in_page >= $max_row_per_page) {
			$count_row_in_page = 0;
			$max_row_per_page = 36;
		}
	}
	$html .= '</table>';

	if ($has_group) {
		echo $html;
	} else {
		$count_row_in_page = $start_row_count;
	}
	return $has_group;
}




$lab_id			= CI_Base_Lab::get_current_lab_id();
$has_report     = false;
$from_date      = $month . '-01';
$to_date        = date('Y-m-t', strtotime($from_date));
$lab            = $wpdb->get_row("select * from ci_labs where lab_ID = {$lab_id} ");

$where          = "  ba.doc >='{$from_date}' and ba.doc <='{$to_date}'";

if ($cli_ID != 'all') {
	$clinic = $wpdb->get_row("select * from ci_clinic where cID = {$cli_ID} ");
	$where .= " and ba.cli_ID = {$cli_ID}";
}



$covid_group    = $wpdb->get_row("select * from ci_test_groups 
					where tg_name ='nCoV-2019' or tg_is_covid = 1");
$covid_group_id = $covid_group->tg_ID;



$sql = "select rs.drug, 
        rs.`pos-neg` as pos_neg,
        rs.group_id,
        count(*) as count
		from ci_results_pcr rs
		JOIN ci_reports_pcr re On re.id = rs.report_id
		JOIN ci_batches_pcr ba ON re.patient_id = ba.assen
		where {$where}
		group by rs.drug, rs.group_id, pos_neg
		";

$rs    = $wpdb->get_results($sql);



$results  = array();

foreach ($rs as $r) {
	$key = strtoupper($r->drug) . '_' . $r->group_id;

	$r->high_med_low = '';
	if (in_array($r->pos_neg, array('POH', 'PVH'))) {
		$r->high_med_low = 'H';
	} else if (in_array($r->pos_neg, array('POL', 'PVL'))) {
		$r->high_med_low = 'L';
	} else if (in_array($r->pos_neg, array('POM'))) {
		$r->high_med_low = 'M';
	} else if (in_array($r->pos_neg, array('POS'))) {
		$r->high_med_low = 'P';
	} else if (in_array($r->pos_neg, array('NEG'))) {
		$r->high_med_low = 'N';
	}
	if (!isset($results[$key])) {
		$results[$key] = array(
			'H' => 0,
			'M' => 0,
			'L' => 0,
			'P' => 0,
			'N' => 0,
		);
	}
	$results[$key][$r->high_med_low] += $r->count;
}
unset($rs);



$group_ids 		= array();
$group_reports 	= array();
$rsgroups 		= $wpdb->get_results("select tg_ID, 
				tg_name, 
				tg_parent 
				from ci_test_groups
				 where tg_type='pcr' 
				 and tg_parent = 0
				 order by tg_position asc");

foreach ($rsgroups as $r) {
	$r->tg_name = trim(str_replace('(new)', '', $r->tg_name));
	if (stripos($r->tg_name, 'Control') !== false) {
		continue;
	}

	$group_ids[$r->tg_ID] = $r->tg_ID;
	$tests 	= array();
	$subs 	= array();
	$has_hml = true;
	$subgroups = $wpdb->get_results("select * from ci_test_groups where tg_parent = {$r->tg_ID} order by tg_position asc");
	if (count($subgroups) > 0) {
		foreach ($subgroups as $sub) {
			$testarr = trpcr_sum_get_tests_by_groups($sub->tg_ID, $results);

			$subs[$sub->tg_ID] = array(
				'id' 	=> $sub->tg_ID,
				'parent_id' => $sub->tg_parent,
				'name' 	=> $sub->tg_name,
				'is_high_med_low' => $testarr['has_hml'],
				'tests' => $testarr['tests']
			);
		}
	} else {

		$testarr = trpcr_sum_get_tests_by_groups($r->tg_ID, $results);
		$tests = $testarr['tests'];
		$has_hml = $testarr['has_hml'];
	}

	$group_reports[$r->tg_ID] = array(
		'id' 	=> $r->tg_ID,
		'name' 	=> $r->tg_name,
		'is_high_med_low' => $has_hml,
		'subs' 	=> $subs,
		'tests' => $tests,
	);
}


//count pos/neg
$group_pos_negs = array();
foreach ($group_ids as $gid) {
	$sql = "select 
		sum(if(re.has_pos>0, 1, 0)) as countpos,
        count(*) as count
		from ci_reports_pcr re 
		JOIN ci_batches_pcr ba ON re.patient_id = ba.assen
		where {$where}
		and FIND_IN_SET('GROUP_{$gid}',ba.panel_tests_requested)
		";

	$rs    = $wpdb->get_row($sql);
	$group_pos_negs[$gid] = array(
		'POS' => $rs->countpos,
		'NEG' => $rs->count - $rs->countpos
	);
}


//check covid
if ($covid_group_id > 0) {
	$sql = "select 
		re.covid_pos,
        count(*) as count
		FROM ci_reports_pcr re
		JOIN ci_batches_pcr ba ON re.patient_id = ba.assen
		where {$where} 
		and FIND_IN_SET('GROUP_{$covid_group_id}',ba.panel_tests_requested)
		group by re.covid_pos";
	$rs = $wpdb->get_results($sql);
	$covids = array();
	foreach ($rs as $r) {
		if (!in_array($r->covid_pos, array('0', '1'))) {
			continue;
		}
		$covids[$r->covid_pos] = $r->count;
	}
}


if (isset($_GET['debug2'])) {
	var_dump($group_reports);
	exit;
}
//end covid

$upload_dir = tr_wp_upload_dir();
$logo_url = $upload_dir['baseurl'] . '/logos/' . $lab->lab_logo;
?>
<html>

<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<style>
		@page {
			margin: 0.7cm 0.6cm 0.6cm 0.6cm;
		}

		table {
			width: 100%;
		}

		table.border {
			border-collapse: collapse;
			border: none;
		}

		table.border th {
			font-weight: normal;
			border-top: 1px solid #ddd;
			border-bottom: 1px solid #ddd;
		}

		table.border thead th {
			text-align: center;
		}

		table.border td {
			border-top: 1px solid #ddd;
			border-bottom: 1px solid #ddd;
			text-align: center;
		}

		.center {
			text-align: center;
		}

		.aleft {
			text-align: left;
			padding-left: 5px;
		}

		.bgblack {
			background: #888;
		}

		.break {
			page-break-after: always;
		}
	</style>
</head>

<body>
	<div class="center">
		<img src="<?php echo $logo_url ?>" style="height:90px;" />
	</div>
	<h1 class="center">PCR Summary - Prevalence Rates</h1>
	<div class="center" style="font-size:12px;"><?php echo ($clinic) ? $clinic->cli_clinic : 'All Clients ' ?> - <?php echo date('M Y', strtotime($month)) ?></div>
	<br><br>
	<?php
	if (count($covids) > 0) {

		$has_report = true;
		$covid_percent = array();
		$covid_total = $covids[1] + $covids[0];
		$covid_percent[1] = number_format($covids[1] / $covid_total * 100, 2) . '%';
		$covid_percent[0] = number_format($covids[0] / $covid_total * 100, 2) . '%';
	?>
		<table class="border">
			<thead>
				<tr>
					<th></th>
					<th></th>
					<th style="width:80px;">Detected</th>
					<th style="width:100px;">Not Detected</th>
					<th style="width:10px;"></th>
					<th style="width:106px">% Detected</th>
					<th style="width:110px">% Not Detected</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<th class="bold aleft"><?php echo $covid_group->tg_name ?></th>
					<th class="aleft"><?php echo $covid_group->tg_name ?></th>
					<td><?php echo intval($covids[1]) ?></td>
					<td><?php echo intval($covids[0]) ?></td>
					<td class="bgblack"></td>
					<td><?php echo $covid_percent[1] ?></td>
					<td><?php echo $covid_percent[0] ?></td>
				</tr>
			</tbody>
		</table>
		<br>
	<?php
	}

	$hml_body_html 		= '';
	$pn_body_html 		= '';
	$showed_groups 		= array();
	$count_row_in_page 	= 0;
	$max_row_per_page 	= 28;
	

	foreach ($group_reports as $grouparr) {

		$start_row_count = $count_row_in_page;


		ob_start();

		$has_group 			= false;

		$posnegarr 			= $group_pos_negs[$grouparr['parent_id'] > 0 ? $grouparr['parent_id'] : $grouparr['id']];
		$number_pos_neg 	= ' (' . $posnegarr['POS'] . '/' . $posnegarr['NEG'] . ')';
		$count_row_in_page++;

		if ($count_row_in_page >= $max_row_per_page - 3) {
			$count_row_in_page = 0;
			$max_row_per_page = 36;
			echo '<div class="break"></div>';
		}

		echo '<table class="border">';
		echo '<tr>';
		echo  '<th colspan="' . ($grouparr['is_high_med_low'] ? 8 : 6) . '" class=" center" style="font-size:13px;font-weight:bold">' . $grouparr['name'] . $number_pos_neg . '</th>';
		echo  '</tr>';
		echo '</table>';

		if (count($grouparr['subs']) > 0) {
			foreach ($grouparr['subs'] as $sub) {

				if (trpcr_show_results_tests($sub)) {
					$has_group = true;
				}
			}
		} else {
			$has_group = trpcr_show_results_tests($grouparr);
		}



		$content = ob_get_clean();
		if ($has_group) {
			$has_report = true;
			echo $content;
		}else{
			$count_row_in_page = $start_row_count;
		}
	}


	?>
</body>

</html>