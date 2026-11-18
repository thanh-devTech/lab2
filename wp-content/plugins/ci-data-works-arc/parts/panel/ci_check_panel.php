<?php
class CI_Check_Panel
{
	var $options = array();

	public function __construct()
	{
		$this->options['enable'] = true;
	}


	public function cron($params = array())
	{
		if (in_array('pcr', tr_pdd_get_batch_types()) && tr_get_option('ci_pdf_data', 'new_abx')) {
			$this->check_abx_map();
		}
	}

	function auto_map_abx($group_id)
	{
		$_GET['gr'] = $group_id;
		$tab = 'pcr';
		$_REQUEST['type'] = $tab;
		include('list.php');
	}

	public function check_abx_map()
	{
		global $wpdb;
		if (!function_exists('tr_client_send_hub')) {
			return;
		}

		if (!in_array('pcr', tr_pdd_get_batch_types()) || !tr_get_option('ci_pdf_data', 'new_abx')) {
			return;
		}

		$sql_groups = "
		select tg_ID, tg_name, tg_other_options
		from ci_test_groups
		where tg_parent = 0
		and tg_type = 'pcr'
		and tg_other_options like '%new_abx\":\"1%'
		and tg_antibiotic = 0 
		";
		$groups = $wpdb->get_results($sql_groups, ARRAY_A);
		if (count($groups) == 0) {
			return;
		}

		$un_mapped = array();
		foreach ($groups as $group) {
			$gid = $group['tg_ID'];

			//run automap first
			$this->auto_map_abx($gid);

			$sql_panels = "
			select te.te_ID,
			 te.te_name,
			 tg.tg_ID,
			 tg.tg_name,
			 meta.meta_value as abx_map
			from ci_test_panels te
			left join ci_test_meta meta on meta.test_id = te.te_ID 
							and meta.meta_key in( 'mkr_tests','bac_tests')
							and meta.meta_value != '' and meta.meta_value is not null
			JOIN ci_test_groups tg ON tg.tg_ID = te.te_group_id
			where (tg.tg_ID = {$gid} or tg.tg_parent = {$gid}) 
			and te.te_in_req = 1	
			";
			$tests = $wpdb->get_results($sql_panels, ARRAY_A);
			foreach ($tests as $test) {
				$mapid = $test['abx_map'];
				if (empty($mapid)) {
					if (!isset($un_mapped[$gid])) {
						$un_mapped[$gid] = array(
							'group_id' => $gid,
							'group_name' => $group['tg_name'],
							'tests' => array(),
						);
					}
					$un_mapped[$gid]['tests'][] = array(
						'test_id' => $test['te_ID'],
						'test_name' => $test['te_name'],
						'group_id' => $test['tg_ID'],
						'group_name' => $test['tg_name'],
					);
				}
			}
		}
		echo '<pre>';
		print_r($un_mapped);
		echo '</pre>';
		if (count($un_mapped) > 0) {
			//send to hub
			$rs = tr_client_send_hub(array(
				'action'     => 'abx_un_mapped',
				'data' => json_encode($un_mapped),
			));
			if (isset($_GET['debug'])) {
				var_dump($rs);
			}
		}
	}
}
