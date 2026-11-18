<?php
if(empty($group_manual_antibiotic_name))
{
	$group_manual_antibiotic_name = 'Culture and Antibiotic Susceptibility';
}

$repdb = $wpdb->get_row("select * from ci_reports_blood where id = {$report['id']}");
$show3pathogen_note = false;
$shownogrowth_note = false;
if ($repdb) {
	$show3pathogen_note = $repdb->show3pathogen_note;
	$shownogrowth_note = $repdb->shownogrowth_note;
}

if ($show3pathogen_note || $shownogrowth_note) {
	echo '<br><table class="tdborder small_table antibiotic_tbl">';
	echo '<tr class=" header_tbl autoh"><th >'.$group_manual_antibiotic_name.' Results</th></tr>';
	if ($shownogrowth_note) {
		echo '<tr><td style="padding:10px">No Pathogen Growth Observed.</td></tr>';
	}
	if ($show3pathogen_note) {
		echo '<tr><td style="padding:10px">Mixed Culture - 3 or more organisms were detected. Probable contamination of urine specimen.</td></tr>';
	}

	echo '</table>';

	$need_break_summary_page = true;
	echo '<div style="padding-top:8px;">' . nl2br($notes['utinewtable']) . '</div>';
} else {

	$anti_data = array();
	$anti_all = array();

	$anti_rs = $wpdb->get_results("select ra.*,
	                            ta.te_name as ta_name,
	                            te.te_name
	                    from ci_results_antibiotics ra
	                     join ci_test_panels te ON te.te_ID = ra.te_ID
	                     join ci_test_panels ta ON ta.te_ID = ra.ta_ID and ta.te_type='cuant'
	                    where ra.report_id = {$id} 
	                        and ra.btype = 'blood'
	                    order by ta_name asc , te_name asc");

	$labresults = $wpdb->get_results("select ra.*,te.te_name
                                            from ci_results_labresults ra
                                            join ci_test_panels te ON te.te_ID = ra.te_ID
                                            where report_id = {$id}
                                                order by te_name asc ");


	foreach ($anti_rs as $r) {
		if (!isset($anti_data[$r->te_name])) {
			$anti_data[$r->te_name] = array(
				'labresult' => '',
				'rs' => array(),
				'mic' => array(),
			);
		}
		$anti_data[$r->te_name]['rs'][$r->ta_name] = $r->result;
		if (!empty($r->mic_value)) {
			$anti_data[$r->te_name]['mic'][$r->ta_name] = $r->mic_value;
		}

		$anti_all[$r->ta_name] = $r->ta_name;
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
		$secondcolwidth = 90;


		if ($count_anti > 25) {
			$firstcolwidth = 16;
			$secondcolwidth = 18;
			echo '<style>body table td.valignmid{font-size:6pt} body table th.rotate{min-width:16px !important}table tr.microw td{font-size:5pt;padding:2px 0;}table tr.microw td.symbol{letter-spacing:-1px}</style>';
		}
		else if ($count_anti > 22) {
			$firstcolwidth = 20;
			$secondcolwidth = 50;
			echo '<style>body table th.rotate{min-width:22px !important}table tr.microw td{font-size:7pt;padding:2px 0;}table tr.microw td.symbol{letter-spacing:-1px}</style>';
		}
		else if ($count_anti > 12) {
			$firstcolwidth = 25;
			$secondcolwidth = 70;
			echo '<style>body table th.rotate{min-width:24px !important}table tr.microw td{font-size:7pt;padding:2px 0}</style>';
		}

		


		echo '<br><table style="border:0"><tr><td style="border:0;padding:0;page-break-inside:avoid">';
		echo '<table class="tdborder small_table antibiotic_tbl">';
		echo '<tr class="header_tbl autoh">';
		echo '<th colspan="' . ($count_anti + 2) . '" class="upper">'.$group_manual_antibiotic_name.' Results</th></tr>';
		echo '<tr>';
		echo '<th class="center title_tbl" style="width:' . $firstcolwidth . '%;border-bottom:0">Detected</th>';
		echo '<th class="center title_tbl " style="width:'.$secondcolwidth.'px;border-bottom:0" title="Lab Result"><span>Lab Result</span></th>';
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
			echo '<tr class="antirow">';
			echo '<td ' . (is_array($arr['mic']) && count($arr['mic']) > 0 ? 'rowspan="2"' : '') . ' class="valignmid ">' . $tename . '</td>';

			echo '<td class="center" ' . (strlen($arr['labresult']) > 12 ? 'style="font-size:8px"' : '') . '>' . htmlentities($arr['labresult']) . '</td>';

			foreach ($anti_all as $tname) {
				$vl = isset($arr['rs'][$tname]) ? $arr['rs'][$tname] : ' ';
				echo '<td class="center vl_' . $vl . '">' . htmlentities($vl) . '</td>';
			}
			echo '</tr>';

			if (is_array($arr['mic']) && count($arr['mic']) > 0) {
				echo '<tr class="microw">';
				echo '<td class="center">MIC</td>';
				foreach ($anti_all as $tname) {
					$vl = isset($arr['mic'][$tname]) ? $arr['mic'][$tname] : ' ';
					$vl = htmlentities(trpdd_show_symbol($vl));
					if($count_anti>22 && strpos($vl,'0.')!==false)
					{
						//$vl = str_replace('0.','.',$vl);
					}
					echo '<td class="center symbol">' . $vl . '</td>';
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
		foreach ($drug_groups as $group => $groupdata) {
			if (stripos($group, ' marker') > 0) {
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
				echo '<td class="center">blood Detected</td>';
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


		echo '<div style="padding-top:8px;">' . nl2br($notes['utinewtable']) . '</div>';
	}
}
if ($need_break_summary_page) {
	echo '<div class="break"></div>';
}
