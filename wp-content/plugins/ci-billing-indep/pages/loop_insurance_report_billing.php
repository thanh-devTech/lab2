<?php
$total_amount = 0;
$panel_tbl = '<table class="testcpttbl">';
$showed_amount = array();
$row->bytest = true;
$row->withobj = true;

if (isset($_GET['debugassen']) && $_GET['debugassen'] === $row->assen) {
	var_dump($row);
	echo '<br><br>';
	$_GET['debug'] = $row->assen;
}
$billing_codes = tr_billing_get_cpt_code($row);

if (isset($_GET['debugassen']) && $_GET['debugassen'] === $row->assen) {
	var_dump($billing_codes);
	echo '<br><br>';
	unset($_GET['debug']);
}

$groupids = array();
foreach (explode(',', $row->panel_tests_requested) as $testid) {
	$testid = trim($testid);
	$testid = str_replace('+', '', $testid);
	if (stripos($testid, 'group_') !== false) {
		$group_id = str_ireplace('group_', '', $testid);
		if (!in_array($group_id, $groupids) && isset($groups[$group_id])) {
			$panel_code = $groups[$group_id]->tg_cpt_code;
			$panel_amount = 0;
			if (isset($billing_codes['G' . $group_id])) {
				$panel_code = $billing_codes['G' . $group_id]['code'];
				$panel_amount = $billing_codes['G' . $group_id]['amount'];
			}
			if ($is_summary && $panel_amount == 0) {
				continue;
			}
			$panel_tbl .= '<tr>';
			$panel_tbl .= '<td class="cptcode">' . $panel_code . '</td>';
			$panel_tbl .= '<td class="pname"><b>' . $groups[$group_id]->tg_name . '*</b></td>';
			$panel_tbl .= '<td class="amount">' . $panel_amount . '</td>';
			$panel_tbl .= '</tr>';

			if (isset($groups_notestcb[$group_id])) {

				foreach ($groups_notestcb[$group_id] as $tid) {
					if (isset($all_tests[$tid])) {

						if (in_array($billing_codes[$tid]['code'], $showed_amount)) {
							$billing_codes[$tid]['amount'] = '';
						} else if ($billing_codes[$tid]['amount'] > 0) {
							$total_amount += $billing_codes[$tid]['amount'];
						}
						$showed_amount[] = $billing_codes[$tid]['code'];

						if (!$is_summary) {
							$panel_tbl .= '<tr>';
							$panel_tbl .= '<td class="cptcode">' . $billing_codes[$tid]['code'] . '</td>';
							$panel_tbl .= '<td class="pname">' . $all_tests[$tid]->te_name . '</td>';
							$panel_tbl .= '<td class="amount">' . $billing_codes[$tid]['amount'] . '</td>';
							$panel_tbl .= '</tr>';
						}
					}
				}
			}

			$groupids[] = $group_id;
		}
	} else if (isset($all_tests[$testid])) {
		$testdb = $all_tests[$testid];
		//$testid = $testdb->te_ID;
		$drugpars = array(
			strtoupper($testdb->te_name),
			strtoupper($testdb->te_abrev),
			strtoupper($testdb->te_abrev2),
		);
		$testdrug = '';
		foreach ($drugpars as $tdr) {
			if (isset($result_values[$tdr])) {
				$testdrug = $tdr;
				break;
			}
		}
		if (!empty($testdrug)) {

			list($pos_neg, $conc) = explode(';', $result_values[$testdrug]);

			$tr_class = '';
			if ($settings['redtest_ins_report'] && in_array($pos_neg, array('POS', 'POL', 'POH', 'POM', 'PVL', 'PVH'))) {
				$tr_class = 'fillred';
			}
			$group_id = $testdb->te_group_id;
			if ($group_id > 0 && !in_array($group_id, $groupids) && isset($groups[$group_id])) {

				if ($is_summary && !isset($billing_codes['G' . $group_id])) {
					continue;
				}
				$panel_code = isset($billing_codes['G' . $group_id]) ? $billing_codes['G' . $group_id]['code'] : $groups[$group_id]->tg_cpt_code;
				$panel_tbl .= '<tr>';
				$panel_tbl .= '<td class="cptcode">' . $panel_code . '</td>';
				$panel_tbl .= '<td class="pname"><b>' . $groups[$group_id]->tg_name . '*</b></td>';
				$panel_tbl .= '<td class="amount"></td>';
				$panel_tbl .= '</tr>';

				$groupids[] = $group_id;
			}

			if (in_array($billing_codes[$testid]['code'], $showed_amount)) {
				$billing_codes[$testid]['amount'] = '';
			} else if ($billing_codes[$testid]['amount'] > 0) {
				$total_amount += $billing_codes[$testid]['amount'];
			}
			if (!$is_summary) {
				$test_code = $billing_codes[$testid]['code'];
				$test_amount = $billing_codes[$testid]['amount'];
				if (isset($billing_codes['G' . $group_id])) {
					$test_code = '';
					$test_amount = '';
				}
				$panel_tbl .= '<tr class="' . $tr_class . '">';
				$panel_tbl .= '<td class="cptcode">' . $test_code . '</td>';
				$panel_tbl .= '<td class="pname">' . $all_tests[$testid]->te_name . '</td>';
				$panel_tbl .= '<td class="amount">' . $test_amount . '</td>';
				$panel_tbl .= '</tr>';
			}
		}
	}
}


if (isset($billing_codes['classes']) && $total_amount == 0) {

	foreach ($billing_codes['classes'] as $class_name => $class_info) {
		$panel_tbl .= '<tr>';
		$panel_tbl .= '<td class="cptcode">' . $class_info['code'] . '</td>';
		$panel_tbl .= '<td class="pname">' . $class_info['class'] . '</td>';
		$panel_tbl .= '<td class="amount">' . $class_info['amount'] . '</td>';
		$panel_tbl .= '</tr>';
		if ($class_info['amount'] > 0) {
			$total_amount += $class_info['amount'];
		}
	}
	if ($is_summary && count($billing_codes['classes']) > 1 && $total_amount > 0) {
		$panel_tbl .= '<tr>';
		$panel_tbl .= '<td class="cptcode"></td>';
		$panel_tbl .= '<td class="pname"><b>Total Amount</b></td>';
		$panel_tbl .= '<td class="amount">' . number_format($total_amount, 2) . '</td>';
		$panel_tbl .= '</tr>';
	}
}

$panel_tbl .= '</table>';
?>
<tr class="childp">
	<td><?php echo tr_billing_display_date($row->doc, 'Y-m-d') ?></td>
	<td><?php echo $row->assen ?></td>
	<td><?php echo $row->oldreq ?></td>
	<td><?php echo $row->pa_last ?></td>
	<td><?php echo $row->pa_first ?></td>
	<td><?php echo tr_billing_display_date($row->pa_dob, 'Y-m-d') ?></td>
	<?php if ($paytype == 'insu') {
		echo '<td>' . $row->insurance . '</td>';
	}
	?>
	<td colspan="3"><?php echo $panel_tbl ?></td>
</tr>