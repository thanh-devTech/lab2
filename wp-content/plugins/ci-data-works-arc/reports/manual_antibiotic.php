<?php

$repdb = $wpdb->get_row("select show3pathogen_note,shownogrowth_note from ci_reports_pcr where id = {$report['id']}");
$show3pathogen_note = false;
$shownogrowth_note = false;
if ($repdb) {
	$show3pathogen_note = $repdb->show3pathogen_note;
	$shownogrowth_note = $repdb->shownogrowth_note;
}

if ($show3pathogen_note || $shownogrowth_note) {
	echo '<br><table class="tdborder small_table antibiotic_tbl">';
	echo '<tr class=" header_tbl autoh"><th >Culture and Antibiotic Susceptibility Results</th></tr>';
	if ($shownogrowth_note) {
		echo '<tr><td style="padding:10px">No Pathogen Growth Observed.</td></tr>';
	}
	if ($show3pathogen_note) {
		echo '<tr><td style="padding:10px">Mixed Culture - 3 or more organisms were detected. Probable contamination of urine specimen.</td></tr>';
	}

	echo '</table>';

	$need_break_summary_page = true;
	$has_report = true;
	echo '<div style="padding-top:8px;">' . nl2br($notes['utinewtable']) . '</div>';
} else {

	$anti_data = array();
	$anti_all = array();

	$anti_rs = $wpdb->get_results("select ra.*,
	                            ta.ta_name,
	                            te.te_name
	                    from ci_results_antibiotics ra
	                     join ci_test_panels te ON te.te_ID = ra.te_ID
	                     join ci_test_antibiotics ta ON ta.ID = ra.ta_ID
	                    where ra.report_id = {$id} 
	                        and ra.btype = 'pcr'
	                        order by ta_name asc , te_name asc");

	$labresults = $wpdb->get_results("select ra.*,te.te_name
                                            from ci_results_labresults ra
                                            join ci_test_panels te ON te.te_ID = ra.te_ID
                                            where report_id = {$id}
                                                order by te_name asc ");


	$mic_rs = $wpdb->get_results("select ra.*,
			                                    ta.ta_name
                                                from ci_results_antibiotics ra
                                                join ci_test_antibiotics ta ON ta.ID = ra.ta_ID
                                                where report_id = {$id} 
                                                    and btype = 'pcr'
                                                    and ra.te_ID = -1
                                                order by ta_name asc");




	foreach ($anti_rs as $r) {
		if (!isset($anti_data[$r->te_name])) {
			$anti_data[$r->te_name] = array(
				'labresult' => '',
				'rs' => array(),
			);
		}
		$anti_data[$r->te_name]['rs'][$r->ta_name] = $r->result;

		$anti_all[$r->ta_name] = $r->ta_name;
	}

	$mic_data = array();
	foreach ($mic_rs as $r) {
		/*
				if(!isset($anti_data['MIC'])){
					$anti_data['MIC'] = array(
						'labresult' => '',
						'rs' => array(),
					);
				}
				$anti_data['MIC']['rs'][$r->ta_name] = $r->result;
				$anti_all[$r->ta_name] = $r->ta_name;
			    */
		$mic_data[$r->ta_name] = $r->result;
	}

	if (isset($_GET['abc'])) {
		var_dump($anti_data);
	}


	foreach ($labresults as $r) {
		$anti_data[$r->te_name]['labresult'] = $r->result;
	}

	if ($anti_data) {
		$count_anti    = count($anti_all);
		$firstcolwidth = 35;
		if ($count_anti > 22) {
			$firstcolwidth = 20;
		} else if ($count_anti > 12) {
			$firstcolwidth = 25;
		}
		echo '<br><table style="border:0"><tr><td style="border:0;padding:0;page-break-inside:avoid">';
		echo '<table class="tdborder small_table antibiotic_tbl">';
		echo '<tr class="header_tbl autoh">';
		echo '<th colspan="' . ($count_anti + 2) . '" class="upper">Culture and Antibiotic Susceptibility Results</th></tr>';
		echo '<tr>';
		echo '<th class="center title_tbl" style="width:' . $firstcolwidth . '%;border-bottom:0">Detected</th>';
		echo '<th class="center title_tbl " style="width:90px;border-bottom:0" title="Lab Result"><span>Lab Result</span></th>';
		if ($count_anti > 0) {
			echo '<th colspan="' . count($anti_all) . '" class="center title_tbl" style="height:30px">Antibiotics</th></tr>';
		}

		echo '<tr>';
		echo '<th style="border-top:0"> </th><th style="border-top:0"> </th>';
		foreach ($anti_all as $tname) {
			echo '<th class="rotate " title="' . $tname . '"><span>' . $tname . '</span></th>';
		}
		echo '</tr>';
		foreach ($anti_data as $tename => $arr) {
			echo '<tr>';
			if ($tename == 'MIC') {
				echo '<td colspan=2 class="right">' . $tename . '</td>';
			} else {
				echo '<td ' . (count($mic_data) > 0 ? 'rowspan="2"' : '') . '>' . $tename . '</td>';
				echo '<td class="center">' . htmlentities($arr['labresult']) . '</td>';
			}

			foreach ($anti_all as $tname) {
				$vl = isset($arr['rs'][$tname]) ? $arr['rs'][$tname] : ' ';
				echo '<td class="center vl_' . $vl . '">' . htmlentities($vl) . '</td>';
			}
			echo '</tr>';

			if (count($mic_data) > 0) {
				echo '<tr>';
				echo '<td class="center">MIC</td>';
				foreach ($anti_all as $tname) {
					$vl = isset($mic_data[$tname]) ? $mic_data[$tname] : ' ';
					echo '<td class="center">' . htmlentities(trpdd_show_symbol($vl)) . '</td>';
				}
				echo '</tr>';
			}
		}

		$anti_all_str = implode("','", $anti_all);
		$abxmixs_rs = $wpdb->get_results("select * from ci_test_abxmix where drug in ('{$anti_all_str}')", ARRAY_A);
		$abxmixs = array();
		foreach ($abxmixs_rs as $ar) {
			$ar['drug'] = strtoupper($ar['drug']);
			foreach ($ar as $abxname => $vl) {
				$abxmixs[strtoupper($abxname)][$ar['drug']] = $vl;
			}
		}

		$markers = array();
		foreach ($drug_groups as $groupid => $groupdata) {
			if (stripos($groupdata['name'], ' marker') > 0) {
				foreach ($groupdata['tests'] as $arr) {
					if (in_array($arr['pos-neg'], array('POS', 'POL', 'POH', 'POM', 'PRP','PVH','PVL'))) {

						if (preg_match("/\(([^\)]+)\)/", $arr['te_name'], $matches) > 0) {
							$arr['te_name'] = $matches[1];
							$markers[] = $arr;
						}
					}
				}
			}
		}

		if (count($markers) > 0) {
			echo '<tr class="header_tbl">';
			echo '<th colspan="' . ($count_anti + 2) . '" class="center upper">INTEGRATED ANTIBIOTIC RESISTANCE MECHANISM</th>';
			echo '</tr>';
			foreach ($markers as $marker) {
				echo '<tr>';
				echo '<td>' . $marker['te_name'] . '</td>';
				echo '<td class="center">PCR Detected</td>';
				foreach ($anti_all as $tname) {
					$vl = $abxmixs[strtoupper($marker['te_name'])][strtoupper($tname)];
					echo '<td class="center vlmix_' . $vl . '">' . $vl . '</td>';
				}
				echo '</tr>';
			}
		}

		echo '</table>';
		echo '</td></tr></table>';

		$need_break_summary_page = true;
		$has_report = true;

		echo '<div style="padding-top:8px;">' . nl2br($notes['utinewtable']) . '</div>';
	}
}
