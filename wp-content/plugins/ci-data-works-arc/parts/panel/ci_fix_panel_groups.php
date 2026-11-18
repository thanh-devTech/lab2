<?php

/**
 * CI API Fix Panel Groups
 * Cron job to fix panels in ci_batches_pcr
 */

class CI_Fix_Panel_Groups
{

	var $options = array();

	public function __construct()
	{
		$this->options['enable'] = true;
	}


	public function cron($params = array())
	{
		$this->fix_panel_group(intval($params[0]));
	}

	/**
	 * Fix panel groups in ci_batches_pcr table
	 */
	public function fix_panel_group($gid)
	{
		global $wpdb;

		$gid = intval($gid);
		if ($gid == 0) {
			return;
		}
		echo "<br>Fixing group ID: {$gid}\n<br>";
		$fixed_count = 0;
		$group = $wpdb->get_row("SELECT * FROM ci_test_groups WHERE tg_ID = {$gid} ", ARRAY_A);
		if ($group['tg_notestcheckbox']) {
			return;
		}

		$test_ids = $wpdb->get_col("SELECT te.te_ID FROM ci_test_panels te
		JOIN ci_test_groups tg ON tg.tg_ID = te.te_group_id
		WHERE (tg.tg_ID = {$gid} or tg.tg_parent = {$gid}) 
		AND te.te_in_req = 1
		");
		$where_tests = [];
		foreach ($test_ids as $tid) {
			$where_tests[] = "find_in_set('{$tid}', panel_tests_requested) = 0";
		}
		$where_tests_str = implode(' AND ', $where_tests);

		$btype = $group['tg_type'];
		$batch_tbl = tr_pdd_get_table_by_btype($btype);

		$rows = $wpdb->get_results("SELECT 
		mid,
		panel_tests_requested FROM {$batch_tbl} 
		WHERE find_in_set('GROUP_{$gid}', panel_tests_requested) > 0
		AND ({$where_tests_str})
		");
		foreach ($rows as $row) {
			$tests = explode(',', $row->panel_tests_requested);
			foreach ($test_ids as $tid) {
				if (!in_array($tid, $tests)) {
					$tests[] = $tid;
				}
			}
			$new_panel_tests = implode(',', $tests);
			$wpdb->update(
				$batch_tbl,
				['panel_tests_requested' => $new_panel_tests],
				['mid' => $row->mid],
				['%s'],
				['%d']
			);
		}
		echo $wpdb->last_error;
		echo $wpdb->last_query;
		var_dump($rows);

		return $fixed_count;
	}
}
