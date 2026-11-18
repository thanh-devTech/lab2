<?php
global $wpdb;

$type 		= isset($_GET['type']) ? strtoupper($_GET['type']) : '';
$timing 	= isset($_GET['timing']) ? strtoupper($_GET['timing']) : '';

if (!in_array($type, array('P', 'N'))) {
	$type = '';
}


$all_groups = array();
$rs_groups = $wpdb->get_results("select 
	tg_ID, 
	tg_name,
	tg_is_covid,
	tg_other_options,
	tg_parent,
	tg_auto_approve_neg,
	tg_auto_approve_pos_neg,
	tg_reflex_group_ID
	from ci_test_groups where tg_type = 'pcr' ");

foreach ($rs_groups as $g) {
	$all_groups[$g->tg_ID] = $g;
}
unset($rs_groups);

$inconclusive_drugs = array();
$rs = $wpdb->get_results("select te.* ,
g.tg_parent,
g.tg_antibiotic
from ci_test_panels te 
JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
where te_type='pcr'");
foreach ($rs as $r) {
	$r->drug = strtoupper($r->te_name);
	$all_tests[$r->te_ID] = $r;
	if ($r->te_inconclusive_ID == -1 || $r->te_is_control) {
		$inconclusive_drugs[] = $r->te_name;
		if (!empty($r->abrev)) {
			$inconclusive_drugs[] = $r->te_abrev;
		}
		if (!empty($r->abrev2)) {
			$inconclusive_drugs[] = $r->te_abrev2;
		}
	}
	$mgroupid = $r->tg_parent > 0 ? $r->tg_parent : $r->te_group_id;

	if (isset($group_all_negs[$mgroupid]) && !$r->te_is_control) {
		$group_all_negs[$mgroupid]++;
	}
}

$inconclusive_drugs = implode("','", $inconclusive_drugs);



$limit_4days = date('Y-m-d', strtotime(date_i18n('Y-m-d')) - 4 * 86400);
if ($timing > 0) {
	$limit_4days = date('Y-m-d H:i:s', strtotime(date_i18n('Y-m-d H:i:s')) - 3600 * $timing);
}
$conditions = trpdd_get_batch_conditions(0, 'pcr');
$where      .=  $conditions['where'];
$where .= " and re.approve_date >= '{$limit_4days}' ";
$where .= " and re.estatus = 'C' ";

$sql = "select
	ba.assen,
	ba.patient_name,
	ba.requesting_practice as clinic,
	ba.panel_tests_requested,
	re.id,
	re.approve_date,
	re.has_pos,
	pa.pa_mrn
    from ci_batches_pcr ba
	JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
	JOIN ci_reports_pcr re ON ba.assen = re.patient_id
    where 1 {$where} 
    group by ba.mid 
    order by re.approve_date asc";


$time_start  = microtime(true);
$results = $wpdb->get_results($sql);

if (isset($_GET['debug'])) {
	echo $sql;
	echo '<br>';
	echo $wpdb->last_error;
}

if (!empty($results)) {
	$num = 1;
	echo '<table border="1" style="border-spacing: 0;border-collapse: collapse;">';
	echo '<tr>';
	echo '<th>#</th>';
	echo '<th>Assen</th>';
	echo '<th>Panels</th>';
	echo '<th>Approved Date</th>';
	echo '<th>Status</th>';
	echo '<th>Patient ID</th>';
	echo '<th>PDF</th>';
	echo '</tr>';
	foreach ($results as $row) {

		$panels = array();
		foreach (explode(',', $row->panel_tests_requested) as $t) {
			if (stripos($t, 'group') !== false) {
				$group_id = str_ireplace('group_', '', $t);
				if (isset($all_groups[$group_id])) {
					$panels[] = $all_groups[$group_id]->tg_name;
				}
			} else if ($t > 0) {
				$tests_ids[] = $t;
			}
		}

		if (is_null($row->has_pos)) {
			if (empty($tests_ids)) $tests_ids = array('-1');
			$tests_ids_str = implode(',', $tests_ids);
			$sql_has_pos = "select count(*) from ci_results_pcr rs
										JOIN ci_test_panels te ON te.te_name = rs.drug or te.te_abrev = rs.drug or te.te_abrev2 = rs.drug
	                                    where report_id = {$row->id}
	                                    and `pos-neg` in ('POS','POM','POL','POH','PCH','PCL','PVH','PVL') 
	                                    and te.te_ID in ({$tests_ids_str})
										and te.te_is_control = 0
	                                    and drug not in ('{$inconclusive_drugs}')";
			if (!empty($tests_in_reflex)) {
				$sql_has_pos .= " and te.te_ID not in ({$tests_in_reflex}) ";
			}
			$row->has_pos = $wpdb->get_var($sql_has_pos);
		}

		$row->status = $row->has_pos ? 'P' : 'N';

		if (!empty($type) && $type != $row->status) {
			continue;
		}


		$row->panels = implode(',<br>', $panels);

		$row->pdf = '?tr_action=ci_results_pdf&btype=pcr&id=' . $row->id;
		if (isset($_GET['key'])) {
			$row->pdf .= '&key=' . $_GET['key'];
		}
		echo '<tr>';
		echo '<td>' . ($num++) . '</td>';
		echo '<td>' . htmlspecialchars($row->assen) . '</td>';
		echo '<td>' . ($row->panels) . '</td>';
		echo '<td>' . htmlspecialchars($row->approve_date) . '</td>';
		echo '<td>' . $row->status . '</td>';
		echo '<td>' . $row->pa_mrn . '</td>';
		echo '<td>' . (!empty($row->pdf) ? '<a href="' . htmlspecialchars($row->pdf) . '">Download</a>' : 'N/A') . '</td>';
		echo '</tr>';
	}
	echo '</table>';
} else {
	echo 'No results found.';
}
