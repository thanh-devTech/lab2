<?php

global $wpdb;
global $row;

//$_GET['onlyview'] = true;

$studyid = $_REQUEST['id'];

$all_bat_status = tr_pdd_get_batch_statuses(false,false,true,'study');

$study = $wpdb->get_row("select * from ci_study  where ID = {$studyid} ");
$study->template = json_decode($study->template, true);

$where = " and re.estatus = 'C' and ba.project_ID = '{$studyid}' ";

$orderby = ' order by ba.position asc';
$sql = "select
        re.id,
        re.estatus,
        re.created,
        re.approve_date,
        re.final_results,
        ba.doc,
        ba.mid,
        ba.assen,
        ba.patient_name,
        ba.panel_tests_requested,
        ba.position,
        ba.batch_status,
		ba.phase,
		ba.timeframe,
		ba.conditions,
		ba.notes_report,
		ba.project_ID,
		reb.patientid,
		reb.code as sampleid,
		reb.study_assigned,
		reb.freezer_code,
		reb.timepoint
        
    from ci_batches_study ba
	left join ci_receiving_study_batches reb ON reb.ID = ba.receivingID
    left JOIN ci_reports_study re ON ba.assen = re.patient_id
    where 1 {$where} group by ba.mid {$orderby}";

$reports = $wpdb->get_results($sql, ARRAY_A);

if (!empty($wpdb->last_error)) {
	echo $wpdb->last_error;
	exit;
}

$header = array();
foreach ($study->template as $arr) {
	$header[] = $arr[0];
}


$filename = $study->project_name . '.csv';

$f = fopen('php://output', 'w');
if (!isset($_GET['onlyview'])) {
	tr_pdd_header('csv', $filename);
}


fputcsv($f, $header);

$tests = array();
$reportables = array();
$tests_rs = $wpdb->get_results("select * from ci_study_tests");
foreach ($tests_rs as $test) {
	$reported_values = json_decode($test->reported_values, true);

	$tests[$test->ID] = array();
	foreach ($reported_values as $ar1) {
		$k1 = strtolower(trim($ar1['v']));
		$reportables[$k1] = $ar1;
	}
}

if (isset($_GET['onlyview'])) {
	echo '<br><br>';
	echo '$reportables:<br>';
	var_dump($reportables);
	echo '<br><br>';
	echo '<br><br>';
}

foreach ($reports as $row) {


	$row['note'] = $row['notes_report'];
	$row['notes'] = $row['notes_report'];
	$row['condition'] = $row['conditions'];

	$conditions = array();
	foreach (explode(';', $row['conditions']) as $ck) {
		$ck = strtolower($ck);
		$ckey = strtolower(substr(str_replace(array(' ','[',']'), '', $ck), 0, 35));
		$conditions[$ckey] = $ck;
	}

	$results = $wpdb->get_results("select * from ci_results_study where report_id = {$row['id']}", ARRAY_A);

	foreach ($results as $i4 => $r) {
		if (stripos($r['drug'],'_mfdata')!==false) continue;

		$rskey = strtolower(trim($r['drug']));

		$concs = json_decode($r['conc'], true);
		if (is_array($concs) && count($concs) > 0) {
			$r['conc'] = '';
			foreach ($concs as $conc) {
				$r['conc'] = $conc;
				break;
			}

			if (count($conditions) > 1 && count($concs) > 1) {
				$cons = array();
				foreach ($conditions as $ckey => $ck) {
					$cons[$ckey] = $concs[$ckey];
					$rsckey = $ck . '_' . $rskey;
					$row[$rsckey] = $concs[$ckey];

					$rsckey = $rskey . '_' . $ck;
					$row[$rsckey] = $concs[$ckey];
				}
				$r['conc'] = implode('; ', $cons);
				if($study->csvbycondition)
				{
					$row[$rskey.'_arr']= $cons;
				}
			}
		}



		$row[$rskey] = $r['conc'];
		$row[$rskey . '-unit'] = $reportables[$rskey]['u'];

		if (!empty($r['group_id'])) {

			$row[$r['group_id'] . '_' . $rskey] = $r['conc'];
			$row[$r['group_id'] . '_' . $rskey . '-unit'] = $reportables[$rskey]['u'];
		}
	}


	if (isset($_GET['onlyview'])) {
		echo '<br><br>';
		var_dump($results);
		echo '<br><br>';
		var_dump($row);
		echo '<br><br>';

		foreach ($study->template as $arr) {
			$k1 = strtolower(trim(str_replace(array('[', ']'), '', $arr[1])));
			echo $k1 . ' , ';
		}
		echo '<br><br>';
	}


	//$row['timeframe'] = '';
	$row['doc-time'] = date('H:i', strtotime($row['doc']));
	$row['doc-date'] = date('Y-m-d', strtotime($row['doc']));
	$row['final_status']	= tr_pdd_get_result_status($row['final_results']);
	$row['study_status']	= isset($all_bat_status[$row['batch_status']])? $all_bat_status[$row['batch_status']] : '';

	$conditionsrows = array(0=>'');
	if($study->csvbycondition)
	{
		$conditionsrows = $conditions;
	}
	
	foreach ($conditionsrows as $sconditionkey=> $scondition) {

		if($study->csvbycondition)
		{
			$row['condition'] = $scondition;
			$row['conditions'] = $scondition;
		}

		$datarow = array();
		foreach ($study->template as $arr) {

			$k1 = strtolower(trim(str_replace(array('[', ']'), '', $arr[1])));
			$vl = '';

			if($study->csvbycondition && isset($row[$k1.'_arr']) && is_array($row[$k1.'_arr']))
			{
				$row[$k1] = $row[$k1.'_arr'][$sconditionkey];
			}

			//check calculate
			if (isset($reportables[$k1]) && !empty($reportables[$k1]['c'])) {
				$calc = $reportables[$k1]['c'];

				try {
					$calc = preg_replace_callback('/\[([A-Za-z0-9\-\s\=]+)\]/', function ($matches) {
						global $row;
						$rskey = strtolower($matches[1]);
						return $row[$rskey];
					}, $calc);

					$rscalc = 0;
					eval('$rscalc = ' . $calc . ';');
					$row[$k1] = $rscalc;

					$row[$k1 . '-unit'] = $reportables[$k1]['u'];
				} catch (Error $e) {
				}
			}


			if (isset($row[$k1])) {
				$vl = $row[$k1];
			} else {
				$vl = preg_replace_callback("/\[([^\]]+)\]/", function ($matches) {
					global $row;
					$k3 = strtolower(trim(str_replace(array('[', ']'), '', $matches[1])));
					$vl = '';

					if (isset($row[$k3])) {
						$vl = $row[$k3];
					} else {
						if (isset($_GET['onlyview'])) {
							echo '<br>-->';
							echo $k3;
							echo '<br>';
						}
					}
					return $vl;
				}, $arr[1]);

				preg_match_all("/\"(.*)\"/", $vl, $matches2);
				$text = '';

				if (count($matches2[0]) > 0) {
					foreach ($matches2[0] as $i2 => $vl2) {
						$text = $matches2[1][$i2];
						$vl = str_replace($vl2, '', $vl);
					}
				}

				$vlold = $vl;
				if (strlen($vl) > 2) {
					try {
						eval('$vl=' . $vl . ';');
					} catch (Error $e) {
						$vl = $vlold;
					}
				}
				$vl .= $text;
				if (empty($vl) && isset($_GET['onlyview'])) {
					echo '<br>';
					echo $k1;
					echo '<br>';
				}
			}


			$datarow[] = $vl;
		}

		fputcsv($f, $datarow);
	}
}

exit;
