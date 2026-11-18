<?php

$row->current_medication = str_replace('|', ',', $row->current_medication);

if ($row->btype == 'tox') {
	if ($row->only_screening) {
		$panel = '';
	} else if ($row->custom_profile == 'Use Custom Profile' && !empty($row->panel_tests_requested)) {
		$panel = $row->panel_tests_requested;
	} else if (strpos($row->panel_tests_requested, '+') === false) {
		$panel = trim(ucfirst($row->custom_profile) . ' +' . $row->panel_tests_requested, '+');
	} else {
		$panel = $row->panel_tests_requested;
	}
} else {
	$testids = explode(',', $row->panel_tests_requested);
	$panel = array();
	$groupids = array();
	foreach ($testids as $testid) {
		$testid = trim($testid);
		$testid = str_replace('+', '', $testid);
		if (stripos($testid, 'group_') !== false) {
			$group_id = str_ireplace('group_', '', $testid);
			if (!in_array($group_id, $groupids) && isset($groups[$group_id])) {
				$panel[] = '<b>' . $groups[$group_id]->tg_name . '</b>';

				if (isset($groups_notestcb[$group_id])) {

					foreach ($groups_notestcb[$group_id] as $tid) {
						$testobj = isset($all_tests[$tid]) ? $all_tests[$tid] : null;
						if ($testobj) {
							if ($testobj->has_reflex && !$row->has_pos) {
								continue;
							}
							$drugpars = array(
								strtoupper($testobj->te_name),
								strtoupper($testobj->te_abrev),
								strtoupper($testobj->te_abrev2),
							);
							$testdrug = '';
							$testname = $testobj->te_name;
							foreach ($drugpars as $tdr) {
								if (isset($result_values[$tdr])) {
									$testdrug = $tdr;
									break;
								}
							}
							if (!empty($testdrug)) {
								list($pos_neg, $conc) = explode(';', $result_values[$testdrug]);
								if ($settings['redtest_ins_report'] && in_array($pos_neg, array('POS', 'POL', 'POH', 'POM', 'PVL', 'PVH'))) {
									$digi = $testdrug == 'ONCOSURE' ? 'G' : 'D';
									$panel[] = '<span class="fillred">' . $testname . ' (' . $digi . ')</span>';
								} else {
									$panel[] =  $testname;
								}
							}
						}
					}
				}
				$groupids[] = $group_id;
				if (stripos($groups[$group_id]->tg_name, 'cov-') !== false) {
					$has_covid = true;
				}
			}
		} else if (isset($all_tests[$testid])) {
			$testobj = $all_tests[$testid];
			if ($testobj->has_reflex && !$row->has_pos) {
				continue;
			}
			$drugpars = array(
				strtoupper($testobj->te_name),
				strtoupper($testobj->te_abrev),
				strtoupper($testobj->te_abrev2),
			);
			$testdrug = '';
			$testname = $testobj->te_name;
			foreach ($drugpars as $tdr) {
				if (isset($result_values[$tdr])) {
					$testdrug = $tdr;
					break;
				}
			}

			if (!empty($testdrug)) {
				$group_id = $testobj->te_group_id;
				if ($group_id > 0 && !in_array($group_id, $groupids) && isset($groups[$group_id])) {
					$panel[] = '<b>' . $groups[$group_id]->tg_name . '</b>';
					$groupids[] = $group_id;
					if (stripos($groups[$group_id]->tg_name, 'cov-') !== false) {
						$has_covid = true;
					}
				}


				list($pos_neg, $conc) = explode(';', $result_values[$testdrug]);

				if ($settings['redtest_ins_report'] && in_array($pos_neg, array('POS', 'POL', 'POH', 'POM', 'PVL', 'PVH'))) {
					$digi = $testdrug == 'ONCOSURE' ? 'G' : 'D';
					$panel[] = '<span class="fillred">' . $testname . ' (' . $digi . ')</span>';
				} else {
					$panel[] =  $testname;
				}


				if (stripos($testobj->te_name, 'cov-') !== false) {
					$has_covid = true;
				}
			}
		}
	}
	$panel = implode(', ', $panel);
}


$panel = trim(str_replace(',', "<br>", $panel));

$dagnostic_codes = explode(',', $row->dagnostic_codes1);
$row->dagnostic_codes1 = array();
$row->dagnostic_codes_desc = array();
foreach ($dagnostic_codes as $dcode) {
	$desc = '';
	if (isset($all_dagnostic_codes[$dcode])) {
		$desc = $all_dagnostic_codes[$dcode];
	} else {
		$arr2 = explode(' ', $dcode, 2);
		$dcode = isset($arr2[0]) ? $arr2[0] : '';
		$desc = isset($arr2[1]) ? $arr2[1] : '';
	}
	$desc = str_replace($dcode, '', $desc);
	$row->dagnostic_codes1[] = $dcode;
	$row->dagnostic_codes_desc[] = $desc;
}

$row->withtimes = 1;

$billing_codes = tr_billing_get_cpt_code($row);

$billing_codes = str_replace(',', ', ', $billing_codes);

if (!isset($row->payer_fin_mnemonic)) {
	$row->payer_fin_mnemonic = '';
}
?>
<tr class="childp" data-id="<?php echo $row->mid ?>" data-btype="<?php echo $row->btype ?>">
	<td title="<?php echo $row->batch_id ?>" class="col-position"><?php echo $row->position; ?></td>
	<td><?php if ($row->resulted) echo '<span class="dashicons dashicons-yes"></span>' ?></td>
	<td><?php echo $row->assen ?></td>
	<td><?php echo $row->requesting_practice ?></td>
	<td><?php echo $row->pa_mrn ?></td>
	<td><?php echo $row->pa_first ?></td>
	<td><?php echo $row->pa_last ?></td>
	<td><?php echo $row->pa_gender ?></td>
	<?php if (isset($settings['insurance_sresg']) && $settings['insurance_sresg']) {
	?>
		<td><?php echo $races[$row->pa_race] ?></td>
		<td><?php echo $Ethnicitis[$row->pa_ethnicity] ?></td>
		<td><?php echo $row->pa_sexual_oriention ?></td>
		<td><?php echo $row->pa_gender_identity ?></td>
	<?php
	}
	?>
	<td><?php echo $row->pa_address ?></td>
	<td><?php echo $row->pa_city ?></td>
	<td><?php echo $row->pa_state ?></td>
	<td><?php echo $row->pa_zip ?></td>
	<td class="r-date"><?php echo tr_billing_display_date($row->pa_dob) ?></td>
	<td><?php echo $row->pa_phone ?></td>
	<td><?php echo $row->pa_code ?></td>
	<td><?php echo $parent_name ?></td>
	<td><?php echo tr_billing_display_date($parent_dob) ?></td>
	<td><?php echo $row->cli_city ?></td>
	<td><?php echo $row->doc_first ?></td>
	<td><?php echo $row->doc_last ?></td>
	<td><?php echo $row->doc_npi ?></td>
	<td><?php echo $row->doc_sp ?></td>
	<td><?php echo $row->doc_sp_npi ?></td>
	<td><?php echo $row->doc_phone ?></td>
	<td class="r-date"><?php echo tr_billing_display_date($row->doc) ?></td>
	<td class="r-date"><?php echo tr_billing_display_date($row->spec_received) ?></td>
	<td class="r-date"><?php echo tr_billing_display_date($row->approve_date) ?></td>
	<td><?php echo $row->lab ?></td>
	<td><?php echo $row->lab_ref ?></td>
	<?php if ($enable_stat) echo '<td>' . ($row->stat ? '<span class="dashicons dashicons-yes"></span>' : '') . '</td>' ?>
	<td><?php if (!empty($panel)) echo '<div class="panels">' . $panel . '</div>' ?></td>
	<?php if (isset($settings['enable_insurance_codes']) && $settings['enable_insurance_codes']) { ?>
		<td><?php echo $row->ins_code ?></td>
	<?php } ?>
	<td><?php echo $row->insurance ?></td>
	<td><?php echo $row->ins_member_id ?></td>
	<td><?php echo $row->ins_group_id ?></td>
	<?php if (isset($settings['enable_insurance_codes']) && $settings['enable_insurance_codes']) { ?>
		<td><?php echo $row->ins_code2 ?></td>
	<?php } ?>
	<td><?php echo $row->ins_company2 ?></td>
	<td><?php echo $row->ins_member_id2 ?></td>
	<?php if ($enable_insurance_rp_mpi) { ?>
		<td><?php echo $row->payer_fin_mnemonic ?></td>
	<?php } ?>
	<td><?php echo $row->type_of_specimen ?></td>
	<td><?php
		foreach ($row->dagnostic_codes1 as $code) {
			echo '<div>' . $code . '</div>';
		}
		?></td>
	<td><?php
		foreach ($row->dagnostic_codes_desc as $cd) {
			echo '<div>' . $cd . '</div>';
		}
		?></td>
	<?php
	if ($show_rapid_collect) {
	?>
		<td><?php echo $row->collect_by_rapid ? 'yes' : 'no' ?></td>
		<td><?php echo $row->miles_rapid ?></td>
	<?php
	}
	?>
	<td><?php echo $all_status[$row->batch_status] ?></td>
	<?php
	if ($settings['cstatus_ins_report']) {
		echo '<td class="bill_status"  data-status="' . $row->bill_status . '"></td>';
	} else if ($row->priority_hours > 0) {
		echo '<td>';
		if (defined('TRPDD_SHOW_PRIORITY_NAME') && TRPDD_SHOW_PRIORITY_NAME) {
			$arrpriorities = $row->btype == 'pcr' ? TRPDD_PRIORITIES_PCR : TRPDD_PRIORITIES;
			$priority_name = isset($arrpriorities[$row->priority_hours]) ? $arrpriorities[$row->priority_hours] : '';

			echo $priority_name;
		} else {
			echo $row->priority_hours . 'hr(s)';
		}
		echo '</td>';
	} else {
		echo '<td></td>';
	}
	?>
	<td><?php echo $billing_codes ?></td>
	<?php if ($enable_result_insurance) {
		$resultpn = '';
		if ($has_covid) {
			if (is_null($row->covid_pos)) {
				if (function_exists('trpdd_cal_ncovid19')) {
					$arr = trpdd_get_pcr_covid_test();
					$arr['drug'] = $arr['te_name'];

					$is_covid2 = $groupd_covid2_id > 0 && stripos($row->panel_tests_requested, 'GROUP_' . $groupd_covid2_id) !== false;
					$arr = trpdd_cal_ncovid19($arr, $row->id, $row->instrument, array(), $row->doc, $is_covid2);
					$row->covid_pos = $arr['covid_pos'];
				}
			}

			if ($row->covid_pos === '1') {
				$resultpn = 'Positive';
			} else if ($row->covid_pos >= 2) {
				$resultpn = 'Inconclusive';
			} else {
				$resultpn = 'Negative';
			}
		}

	?>
		<td><?php echo $resultpn ?></td>
	<?php } ?>
	<td><?php echo nl2br($row->notes) ?></td>
	<td><?php echo nl2br($row->billing_notes) ?></td>
	<td><?php echo nl2br($row->internal_notes) ?></td>
	<?php
	if ($insurance_Salesperson) {
		$salesperson = $all_clinics[$row->cli_ID]->salesperson;
		$distributor = $all_clinics[$row->cli_ID]->distributor;
		$marketing_group = $all_clinics[$row->cli_ID]->marketing_group;
		echo '<td>' . $salesperson . '</td>';
		echo '<td>' . $distributor . '</td>';
		echo '<td>' . $marketing_group . '</td>';
	}
	if (isset($settings['show_collec_col']) && $settings['show_collec_col']) {
		echo '<td>' . $row->collector . '</td>';
	}
	?>
</tr>