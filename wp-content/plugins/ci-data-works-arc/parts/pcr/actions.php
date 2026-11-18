<?php
function tr_pcr_create_report()
{
	global $wpdb;

	$mid = $_GET['createnew'];
	if (empty($mid)) return;

	$bat = $wpdb->get_row("select * from ci_batches_pcr where mid = {$mid} ");

	if ($bat) {
		$id = $wpdb->get_var("select id from ci_reports_pcr where patient_id='{$bat->assen}'");
		if ($id == 0) {
			if (empty($bat->batch_id)) {
				$bat->batch_id = tr_pdd_get_current_batch_id();
				$wpdb->update('ci_batches_pcr', array('batch_id' => $bat->batch_id), array('mid' => $mid));
			}
			$_GET['add_covid_demo'] = true;
			$data = array(
				'batch_id' => $bat->batch_id,
				'patient_id' => $bat->assen,
				'created_date' => date_i18n('Y-m-d'),
				'created' => date_i18n('Y-m-d H:i:s'),
			);
			$wpdb->insert('ci_reports_pcr', $data);
			$id = $wpdb->insert_id;
		}

		$report = tr_pdd_get_report_by_id($id, 'pcr');
		echo '<meta http-equiv="refresh" content="1;url=' .
			Tr_Base_Class_V6::link(array('id' => $id, 'add_covid_demo' => true, 'add_alltests' => true, 'createnew' => ''), false)
			. '" />';
		exit;
	}
}

function tr_pcr_result_add_all_results($all_res, $report, $test_ids, $group_ids)
{
	global $wpdb;

	$where_group = '';
	if (count($group_ids) > 0) {
		$group_ids_str = implode(', ', $group_ids);
		$groups_nochecks = $wpdb->get_col("select tg_ID from ci_test_groups where tg_ID in ({$group_ids_str}) 
				and (tg_notestcheckbox = 1 or tg_only_group = 1) ");
		$groups_nochecks_str = implode(',', $groups_nochecks);
		if (empty($groups_nochecks_str)) $groups_nochecks_str = '-1';
		$where_group = " or (
					te.te_group_id in 
						(
						select tg_ID from ci_test_groups 
						where (
								(
									tg_ID in ({$group_ids_str}) 
									and (tg_notestcheckbox = 1 or tg_only_group = 1)
								)
								or 
								(
									tg_parent in ({$groups_nochecks_str}) 
								)
							)
						) 
					) ";
	}
	$sql = "SELECT *
        FROM ci_test_panels te  
        where te.te_type='pcr'  and (te.te_ID in ({$test_ids}) {$where_group})
        ";

	$date_test = date('Y-m-d', strtotime($report['created']));

	$sql .= " and (te_start <= '{$date_test}' or te_start is null or te_start = '')";
	$sql .= " and (te_end > '{$date_test}' or te_end is null or te_end = '')";

	$all_results_q = $wpdb->get_results($sql, ARRAY_A);

	foreach ($all_results_q as $arr) {
		if (isset($all_res[$arr['te_ID']])) {
			//echo $arr['te_ID'].' -> exist<br>';
			//continue;
			$arr['id'] = $all_res[$arr['te_ID']]['id'];
		}
		$arr['dbdrug'] = isset($arr['drug']) ? $arr['drug'] : '';
		$arr['drug'] = strtoupper($arr['te_name']);
		$arr['conc'] = 40;
		$arr['pos-neg'] = "NEG";
		$arr['amp_score'] = 40;
		$arr['cq'] = 40;
		$all_res[$arr['te_ID']] = $arr;



		if (empty($report['instrument'])) {
			$report['instrument'] = $wpdb->get_var("select cu_ms 
			from ci_test_panels_cutoffs 
			where cu_ms!='' and cu_te_ID = {$arr['te_ID']}");

			if (!empty($instrument)) {
				$wpdb->update('ci_reports_pcr', array('instrument' => $report['instrument']), array('id' => $report['id']));
			}
		}
	}
	return $all_res;
}
