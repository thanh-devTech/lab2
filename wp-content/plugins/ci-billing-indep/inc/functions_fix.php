<?php

function trbill_find_id_by_name($tobj, $row, $tests)
{
	global $wpdb, $groupids_str;

	if (in_array($row['dc_type'], array('urine', 'screen'))) {
		$tobj->ct_test_ID = $wpdb->get_var("select u_ID from ci_urine_validity
		where u_name = '{$tobj->ct_test}' ");
	} else {
		if ($tobj->ct_test_ID == 0 || in_array($tobj->ct_test_ID, $tests)) {
			if (empty($groupids_str)) {
				$groupids = $wpdb->get_col("select tg_ID from 
				ci_test_groups where tg_show_req = 1 
				or tg_parent in (select tg_ID from ci_test_groups where tg_show_req = 1)");
				$groupids_str = implode(',', $groupids);
			}

			$tobj->ct_test_ID = $wpdb->get_var("select te_ID from ci_test_panels
			where te_type = '{$row['dc_type']}' and te_name = '{$tobj->ct_test}' and te_group_id in ({$groupids_str}) ");

			if ($tobj->ct_test_ID == 0) {
				$tobj->ct_test_ID = $wpdb->get_var("select te_ID from ci_test_panels
				where te_type = '{$row['dc_type']}' and te_name = '{$tobj->ct_test}'  ");
			}
		}


		if (isset($_GET['fix_test_group'])) {
			//get group by name first
			$gidbns = $wpdb->get_col("select tg_ID from ci_test_groups 
			where tg_type = '{$row['dc_type']}'
			and (
			(
						(tg_name = '{$row['dc_class']}' or tg_short_name = '{$row['dc_class']}' )
					and tg_show_req = 1
				)
				or 
				tg_parent in (select tg_ID from ci_test_groups where tg_show_req = 1 and (tg_name = '{$row['dc_class']}' or tg_short_name = '{$row['dc_class']}' ))
				)");

			$groupids_str_sg = $groupids_str;
			if (count($gidbns) > 0) {
				$groupids_str_sg = implode(',', $gidbns);

				$tid1 = $wpdb->get_var("select te_ID from ci_test_panels 
			where te_type = '{$row['dc_type']}' 
			and te_name = '{$tobj->ct_test}' 
			and te_group_id in ({$groupids_str_sg})
			");
				if ($tid1 > 0) {
					$tobj->ct_test_ID  = $tid1;
				}
			}
		}
	}

	return $tobj->ct_test_ID;
}
