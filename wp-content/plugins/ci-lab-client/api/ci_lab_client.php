<?php

require_once  'restful_api.php';

add_filter('https_ssl_verify', '__return_false');

set_time_limit(999999);

//ini_set("log_errors", 1);
//ini_set("error_log", WP_CONTENT_DIR."/log/php-error.log");

class CI_Lab_Client_Api extends restful_api
{

	var $api_id = -10;
	var $public_key = 'tinptciq349vbc12xpmaecfj8';
	var $lab_key = '';
	var $options = array();

	var $logcount = 0;

	function __construct($is_call = false)
	{
		global $blog_id;
		$this->hide_info = true;
		if ($blog_id > 1) {
			$this->options = get_blog_option(1, 'ci_lab_client_st', array());
		} else {
			$this->options = get_option('ci_lab_client_st', array());
		}

		if (isset($this->options['key'])) {
			$this->lab_key = $this->options['key'];
		}
		if (!$is_call) {
			parent::__construct('lab_api');
		}
	}

	public function getservertype()
	{
		$servertype = stripos(ABSPATH, 'deployments') > 0 ? 'docker' : 'base';
		if (stripos(ABSPATH, 'bitnami') > 0) {
			$servertype = 'bitnami';
		} else if (stripos(ABSPATH, 'var/www') > 0) {
			$servertype = 'www';
		}
		return $servertype;
	}

	protected function request($action, $args = array())
	{
		global $wpdb, $blog_id;

		$url = 'https://imt.ignitelabconnect.com/labs_api/' . $action;

		if (defined('HUB_API_URL')) {
			$url = HUB_API_URL . $action;
		}

		if (isset($args['body']['old_server'])) {
			if ($args['body']['old_server']) {
				$url = 'https://ignitelabconnect.com/labs_api/' . $action;
			}
			unset($args['body']['old_server']);
		}

		if (!isset($args['timeout'])) {
			$args['timeout'] = 100;
		}

		if (!isset($args['headers'])) {
			$args['headers'] = array();
		}
		if (!isset($args['body']) || !is_array($args['body'])) {
			$args['body'] = array();
		} else {
			$args['method'] = 'POST';
		}

		$args['headers']['api-key'] = $this->lab_key;
		$args['headers']['pub-key'] = $this->public_key;

		$lab = $wpdb->get_row("select * from ci_labs where lab_site = {$blog_id}");
		$domain = '';
		if (defined('DOMAIN_CURRENT_SITE') && !empty(DOMAIN_CURRENT_SITE)) {
			$domain = DOMAIN_CURRENT_SITE;
		}
		if (empty($domain)) {
			$domain = str_replace(array('https://', 'http://', '/'), '', get_option('home'));
		}
		$args['body']['domain'] = $domain;
		$args['body']['name'] = $lab->lab_name;
		$args['body']['init'] = $lab->lab_dba_init;

		if ($action != 'get_ip') {
			$args['body']['ip'] = $this->getIP();
		}

		$args['body']['servertype'] = $this->getservertype();

		if ($action == 'lab_key') {
			$args['body']['address'] = $lab->lab_address;
			$args['body']['city'] = $lab->lab_city;
			$args['body']['state'] = $lab->lab_state;
			$args['body']['zip'] = $lab->lab_zip;
			$args['body']['clia'] = $lab->lab_clia;
		}

		$rs = wp_remote_request($url, $args);


		if (is_wp_error($rs)) {
			$return = array(
				'status' => 'error',
				'success' => false,
				'message' => 'request error',
				'errors' => $rs->errors
			);
		} else {
			$return = json_decode($rs['body'], true);
			if (!is_array($return)) {
				$return = [
					'status' => 'error',
					'success' => false,
					'message' => 'Request error'
				];
			} else if (isset($return['errorCode']) && $return['errorCode'] == 101) {
				$this->get_key();
			} else if (isset($return['status']) && !isset($return['success'])) {
				$return['success'] = $return['status'] == 'ok' ? 1 : 0;
			}
		}
		return $return;
	}

	protected function check_key($use_tmp_key = false)
	{
		if ($this->request_public_key !== $this->public_key) {
			return false;
		}
		if ($use_tmp_key && !empty($this->params['tmp_key']) && $this->params['tmp_key'] == $this->options['tmp_key']) {
			return true;
		}
		return !empty($this->lab_key) && $this->lab_key === $this->request_api_key;
	}

	protected function set_option($okey, $ovl)
	{
		$st = get_option('ci_lab_client_st', array());
		$st[$okey] = $ovl;
		update_option('ci_lab_client_st', $st, false);
	}

	protected function fix_user_ini_file($arrs)
	{
		$filepath = ABSPATH . '/.user.ini';
		$contentadd = '';
		foreach ($arrs as $k => $vl) {
			$contentadd .= $k . '=' . $vl . "\n";
		}
		if (!file_exists($filepath)) {
			file_put_contents($filepath, $contentadd);
		}
	}

	protected function get_error_dir()
	{
		$dir = '/var/log/nginx';
		if (stripos(ABSPATH, '/deployments') > 0) {
			$dir = WP_CONTENT_DIR . '/log/errors';
			if (!file_exists($dir)) {
				wp_mkdir_p($dir);
			}
		}
		return $dir;
	}

	public function get_key()
	{

		if (empty($this->lab_key)) {
			$this->lab_key =  md5(microtime());
		}
		$this->set_option('tmp_key', $this->lab_key);

		$return = $this->request('lab_key');
		if (isset($_GET['resetkey'])) {
			var_dump($return);
		}
		if ($return['success']) {

			return true;
		}
		return false;
	}

	public function getIP()
	{
		if ($this->getservertype() == 'base') {
			return isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '';
		}
		$return = $this->request('get_ip');

		return isset($return['ip']) ? $return['ip'] : '';
	}

	public function send_error($code, $msg)
	{
		$return = $this->request('put_errors', array(
			'body' => array(
				'code' => $code,
				'msg' => $msg,
			)
		));
		return $return;
	}

	public function send_statistics($date, $send_to_hub = true, $is_debug = false, $is_update_panel = false)
	{
		global $wpdb;
		$data = array();

		$batch_id = str_replace('-', '', $date);

		$where_date_default = " AND ba.batch_id = '{$batch_id}' ";
		if (tr_get_option('ci_pdf_data', 'multi_batch_id')) {
			$bs = array(" ba.batch_id= '{$batch_id}' ");
			for ($i = 1; $i <= 10; $i++) {
				$bs[] = " ba.batch_id = '{$batch_id}-{$i}' ";
			}

			$where_date_default = ' AND (' . implode(' or ', $bs) . ' ) ';
		}
		if (class_exists('CI_Base_Lab')) {
			$days_of_operation_lab = CI_Base_Lab::get_lab_operations();
		}

		foreach (tr_pdd_get_batch_types() as $btype) {
			$where_date  = $where_date_default;
			$where_batch = " AND ba.is_saved = 0 ";

			$batch_tbl = tr_pdd_get_table_by_btype($btype);
			$report_tbl = tr_pdd_get_table_report_by_btype($btype);

			if ($btype == 'study') {
				$where_date = " AND ba.batch_created >= '{$date} 00:00:00' AND ba.batch_created <= '{$date} 23:59:59' ";
				$where_batch = "";
			}

			$panels = $wpdb->get_results(
				"select * from ci_test_groups 
						where tg_type = '{$btype}' 
						and tg_show_req = 1
						and tg_parent = 0
						"
			); //and tg_parent = 0
			$uid_map = [];
			foreach ($panels as $panel) {
				$uk = $btype . '_' . sanitize_key($panel->tg_name);
				$uid = !empty($panel->tg_unique_id) ? $panel->tg_unique_id : ($panel->tg_is_covid ? $btype . '_covid' : $uk);
				$uid_map[$uid][] = $panel;
			}

			foreach ($uid_map as $uid => $group) {
				if (count($group) <= 1) continue;

				foreach ($group as $p) {
					$expected_uid = $btype . '_' . sanitize_key($p->tg_name);

					// unique_id không khớp với tên hiện tại → reset
					if ($p->tg_unique_id !== $expected_uid) {
						$wpdb->update(
							'ci_test_groups',
							['tg_unique_id' => $expected_uid],
							['tg_ID' => $p->tg_ID]
						);
						foreach ($panels as &$panel_ref) {
							if ($panel_ref->tg_ID == $p->tg_ID) {
								$panel_ref->tg_unique_id = $expected_uid;
								break;
							}
						}
						unset($panel_ref);
					}
				}
			}

			foreach ($panels as $panel) {
				$uk = $btype . '_' . sanitize_key($panel->tg_name);
				if (empty($panel->tg_unique_id)) {
					$panel->tg_unique_id = $panel->tg_is_covid ? $btype . '_covid' : $uk;
					$wpdb->update('ci_test_groups', array(
						'tg_unique_id' => $panel->tg_unique_id,
					), array('tg_ID' => $panel->tg_ID));
				}
				$count_abx = "";
				$panel_type = '';
				if ($btype == 'pcr') {
					$other_options = !empty($panel->tg_other_options) ? json_decode($panel->tg_other_options, true) : array();
					$panel->enabled_abx = false;
					if (isset($other_options['new_abx']) && $other_options['new_abx']) {

						$panel->enabled_abx  = true;
						if (!empty($other_options['new_abx_date']) && strtotime($other_options['new_abx_date']) > time()) {
							$panel->enabled_abx  = false;
						}
						$panel_type = $other_options['abx_type'];
					}

					if ($panel->enabled_abx) {
						$count_abx = ', SUM(IF((re.has_uti > 0 AND re.has_uti <9 ) and re.estatus=\'C\',1,0)) as count_result_abx ';
					}


					if (isset($panel->tg_panel_type) && !empty($panel->tg_panel_type)) {
						$panel_type = $panel->tg_panel_type;
					}
				}

				if (empty($panel_type)) {
					$panel_type = empty($panel->tg_short_name) ? sanitize_key($panel->tg_name) : sanitize_key(substr($panel->tg_short_name, 0, 3));
				}

				if ($btype == 'tox') {
					$tests = $wpdb->get_col(
						"select te_name from ci_test_panels 
						where te_type = '{$btype}'
						and te_group_id = {$panel->tg_ID}"
					);
					$where_tests = [];

					foreach ($tests as $test) {
						$test_name = strtoupper($test);
						$where_tests[] = "FIND_IN_SET('{$test_name}',panel_tests_requested)";
					}
					$where_group = implode(' OR ', $where_tests);
				} else {
					$where_group = "FIND_IN_SET('GROUP_{$panel->tg_ID}',panel_tests_requested)";
				}

				$sql = "select count(DISTINCT ba.assen) as count_received,
							  sum(IF(re.id is not null, 1, 0)) as count_result,
							  sum(IF(re.estatus='C', 1, 0)) as count_result_c,
							  sum(IF(ba.ref_lab_ID > 0, 1, 0)) as count_ref,
							  0 as countb
							  {$count_abx}
							from {$batch_tbl} ba
							left join {$report_tbl} re ON re.patient_id = ba.assen 
							where ({$where_group}) {$where_batch}
							{$where_date} ";
				$counts = $wpdb->get_row($sql);

				if ($counts && ($counts->countb > 0 || $counts->count_received > 0 || $counts->count_result > 0)) {
					$data[$uk] = array(
						'group' 		=> $panel->tg_name,
						'short_name' 	=> $panel->tg_short_name,
						'panel_type' => $panel_type,
						'panel_color' => $panel->tg_bgcolor,
						'unique_id' => $panel->tg_unique_id,
						'btype' => $btype,
						'is_covid' => intval($panel->tg_is_covid),
						'countb' => intval($counts->countb),
						'count_inbound' => intval($counts->countb),
						'count_received' => intval($counts->count_received),
						'count_result' => intval($counts->count_result),
						'count_result_c' => intval($counts->count_result_c),
						'count_ref' => intval($counts->count_ref),
						'count_result_abx' => 0,
						'date' => $date,
						'update_panel' => $is_update_panel ? 1 : 0,
					);

					if (isset($counts->count_result_abx)) {
						$data[$uk]['count_result_abx'] = intval($counts->count_result_abx);
					}

					if (isset($_GET['testcron'])) {
						echo '<br>';
						echo $panel->tg_name, ' => <br>';
						var_dump($data[$uk]);
						echo '<br><br><br>';
					}
				}
				if (isset($data[$uk]) && class_exists('CI_Base_Lab')) {
					$where_tat_panel = $where_date
						. ' and ' . tr_pdd_where_batch_rejected(false, true, $btype, array('deleted', 'archived', 'cancelled'))
						. ' and (' . $where_group . ')';
					$sql_tat_panel = "
						SELECT ba.doc,ba.lab_ID,ba.batch_status,ba.spec_received,re.approve_date
						FROM {$batch_tbl} ba
						LEFT JOIN {$report_tbl} re ON re.patient_id = ba.assen AND re.estatus = 'C'
						WHERE 1=1 {$where_tat_panel}
					";
					$tat_panel_rows = $wpdb->get_results($sql_tat_panel);
					$pc2r_total = 0;
					$pr2r_total = 0;
					$pc2r_count = 0;
					$pr2r_count = 0;

					$total_samples = 0;
					$samples_under_36 = 0;

					foreach ($tat_panel_rows as $row) {
						$c2r = CI_Base_Lab::calculate_business_hours(
							$row->doc,
							$row->spec_received,
							$row->lab_ID,
							$days_of_operation_lab
						);
						$r2r = CI_Base_Lab::calculate_business_hours(
							$row->spec_received,
							$row->approve_date,
							$row->lab_ID,
							$days_of_operation_lab
						);
						if ($c2r > 0) {
							$pc2r_total += $c2r;
							$pc2r_count++;
						}
						if ($r2r > 0) {
							$pr2r_total += $r2r;
							$pr2r_count++;
						}

						if ($c2r > 0 || $r2r > 0) {
							$total_tat = $c2r + $r2r;
							$total_samples++;
							if ($total_tat < 36) {
								$samples_under_36++;
							}
						}
					}

					$data[$uk]['c2r'] = $pc2r_count > 0 ? round($pc2r_total / $pc2r_count, 1) : 0;
					$data[$uk]['r2r'] = $pr2r_count > 0 ? round($pr2r_total / $pr2r_count, 1) : 0;
					$data[$uk]['tat'] = round($data[$uk]['c2r'] + $data[$uk]['r2r'], 1);

					$data[$uk]['performance'] = $total_samples > 0 ? round(($samples_under_36 / $total_samples) * 100, 1) : 0;
				}
			}

			$rejected_condition = "ba.batch_status = 'rejected'";
			if (function_exists('tr_pdd_where_batch_rejected')) {
				$rejected_condition = tr_pdd_where_batch_rejected(false, false, $btype);
			}
			if ($btype != 'study') {
				$where_batch .= " AND ba.panel_tests_requested IS NOT NULL AND ba.panel_tests_requested != '' ";
			}

			//send total
			$count_abx = "";
			if ($btype == 'pcr') {
				$count_abx = ', SUM(IF((re.has_uti > 0 AND re.has_uti <9 ) and re.estatus=\'C\',1,0)) as count_result_abx ';
			}
			$sql = "SELECT COUNT(ba.assen) AS count_received, 
						COUNT(re.patient_id) AS count_result, 
						COUNT(CASE WHEN re.estatus = 'C' THEN re.patient_id END) AS count_result_c,
						COUNT(CASE WHEN {$rejected_condition} THEN ba.assen END) AS count_rejected,
						COUNT(CASE WHEN ba.ref_lab_ID > 0 THEN ba.assen END) AS count_ref,
						0 AS countb
						{$count_abx}
					FROM {$batch_tbl} ba
					LEFT JOIN {$report_tbl} re ON re.patient_id = ba.assen
					WHERE 1=1 {$where_batch}
					{$where_date}";
			$counts = $wpdb->get_row($sql);

			if ($counts && ($counts->countb > 0 || $counts->count_received > 0 || $counts->count_result > 0)) {
				$data['total_' . $btype] = array(
					'group' => 'total_' . $btype,
					'short_name' => 'total_' . $btype,
					'unique_id' => 'total_' . $btype,
					'btype' => $btype,
					'is_covid' => 0,
					'countb' => intval($counts->countb),
					'count_inbound' => intval($counts->countb),
					'count_received' => intval($counts->count_received),
					'count_result' => intval($counts->count_result),
					'count_result_c' => intval($counts->count_result_c),
					'count_rejected' => intval($counts->count_rejected),
					'count_ref' => intval($counts->count_ref),
					'count_result_abx' => 0,
					'date' => $date,
				);
				if (isset($counts->count_result_abx)) {
					$data['total_' . $btype]['count_result_abx'] = intval($counts->count_result_abx);
				}
			}

			// TAT
			if (class_exists('CI_Base_Lab')) {
				$where_tat = $where_date . ' and ' . tr_pdd_where_batch_rejected(false, true, $btype, array('deleted', 'archived', 'cancelled'));

				$sql_tat = "
					SELECT ba.doc, ba.lab_ID, ba.batch_status, ba.spec_received, re.approve_date
					FROM {$batch_tbl} ba
					LEFT JOIN {$report_tbl} re ON re.patient_id = ba.assen AND re.estatus = 'C'
					WHERE 1=1
					{$where_tat}
				";
				$tat_rows = $wpdb->get_results($sql_tat);

				$c2r_total = $r2r_total = $c2r_count = $r2r_count = 0;

				foreach ($tat_rows as $row) {
					$c2r = CI_Base_Lab::calculate_business_hours($row->doc, $row->spec_received, $row->lab_ID, $days_of_operation_lab);
					$r2r = CI_Base_Lab::calculate_business_hours($row->spec_received, $row->approve_date, $row->lab_ID, $days_of_operation_lab);

					if ($c2r > 0) {
						$c2r_total += $c2r;
						$c2r_count++;
					}
					if ($r2r > 0) {
						$r2r_total += $r2r;
						$r2r_count++;
					}
				}

				$avg_c2r = $c2r_count > 0 ? round($c2r_total / $c2r_count, 1) : 0;
				$avg_r2r = $r2r_count > 0 ? round($r2r_total / $r2r_count, 1) : 0;

				if ($avg_c2r > 0 || $avg_r2r > 0) {
					$data['tat_' . $btype] = [
						'date'      => $date,
						'unique_id' => 'tat_' . $btype,
						'btype'     => $btype,
						'c2r'       => $avg_c2r,
						'r2r'       => $avg_r2r,
						'tat'       => round($avg_c2r + $avg_r2r, 1),
						'countb'    => 0,
					];
				}
			}
		}

		// SMS
		$count_sms = $wpdb->get_var("select count(*) from tr_log_sms 
			where created >= '{$date}' and created <= '{$date} 23:59:59' and sendtype != 'cl' ");
		if ($count_sms > 0) {
			$data['total_sms'] = [
				'date' => $date,
				'unique_id' => 'total_sms',
				'countb' => $count_sms
			];
		}

		// log hubs
		$log_month = date('m', strtotime($date));
		$log_year = date('Y', strtotime($date));
		$log_hubs = $wpdb->get_results("select log_count, log_state from ci_log_hubs where log_month='{$log_month}' and log_year='{$log_year}'");

		if (!empty($log_hubs)) {
			foreach ($log_hubs as $log) {
				if (!empty($log->log_count) && !empty($log->log_state)) {
					$key = 'total_state_' . $log->log_state;
					$data[$key] = [
						'date' => date('Y-m-01', strtotime($date)),
						'unique_id' => $log->log_state,
						'countb' => $log->log_count
					];
				}
			}
		}


		if (empty($data)) return 0;

		if (!$send_to_hub && $is_debug) {
			return $data;
		}

		$return = $this->request('update_statistics', array(
			'body' => array(
				'data' => $data,
				'date' => $date,
			)
		));
		if ($is_debug) {
			return $data;
		}

		if (isset($this->params['send_statistics'])) {
			return $return;
		}
		if ($return['success']) {
			return true;
		}
		return false;
	}
	function get_blog_id_by_domain_keyword($keyword = 'patients')
	{
		global $wpdb;

		$blog_id = $wpdb->get_var($wpdb->prepare(
			"SELECT blog_id 
        FROM {$wpdb->blogs} 
        WHERE domain LIKE %s 
        LIMIT 1",
			'%' . $wpdb->esc_like($keyword) . '%'
		));

		return $blog_id ? (int)$blog_id : null;
	}

	function _update_postdata($post_type, $posts, $is_single_post = false)
	{
		global $wpdb;
		$ids = array();
		$is_switch = false;
		if ($post_type == 'help_support') {
			$blog_id = $this->get_blog_id_by_domain_keyword('patients');
			if ($blog_id) {
				switch_to_blog($blog_id);
				$is_switch = true;
			}
		}
		foreach ($posts as $post) {

			if ($post_type == 'help_support') {
				$dest_post = $wpdb->get_row($wpdb->prepare(
					"
					SELECT p.ID, pm.meta_value as is_edited
					FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_is_edit_help_support'
					WHERE p.post_type = %s 
					AND (p.menu_order = %d OR p.post_name = %s)
					LIMIT 1
					",
					$post_type,
					$post['ID'],
					$post['post_name']
				), ARRAY_A);
			} else {
				$dest_post = $wpdb->get_row($wpdb->prepare(
					"
					SELECT p.ID
					FROM {$wpdb->posts} p
					WHERE p.post_type = %s 
					AND (p.menu_order = %d OR p.post_name = %s)
					LIMIT 1
					",
					$post_type,
					$post['ID'],
					$post['post_name']
				), ARRAY_A);
			}

			$pid = !empty($dest_post) ? $dest_post['ID'] : 0;
			$is_edited = ($post_type == 'help_support' && !empty($dest_post)) ? $dest_post['is_edited'] : '';

			if ($pid > 0) {
				if ($post_type != 'help_support' || $is_edited != '1') {
					$wpdb->update($wpdb->posts, array(
						'post_content' => $post['post_content'],
						'post_title' => $post['post_title']
					), array('ID' => $pid));
				}
			} else {
				$wpdb->insert($wpdb->posts, array(
					'post_type' => $post_type,
					'post_content' => $post['post_content'],
					'post_date' => $post['post_date'],
					'post_date_gmt' => $post['post_date_gmt'],
					'post_title' => $post['post_title'],
					'post_excerpt' => $post['post_excerpt'],
					'post_status' => $post['post_status'],
					'comment_status' => 'closed',
					'ping_status' => 'closed',
					'post_name' => $post['post_name'],
					'post_modified' => $post['post_modified'],
					'post_modified_gmt' => $post['post_modified_gmt'],
					'menu_order' => $post['ID'],
				));
				$pid = $wpdb->insert_id;
			}
			$mt_keys = [];
			foreach ($post['metas'] as $mk => $mvl) {
				update_post_meta($pid, $mk, $mvl);
				$mt_keys[] = $mk;
			}

			if (!in_array('_pages', $mt_keys)) {
				delete_post_meta($pid, '_pages');
			}
			$ids[] = $pid;
		}

		//remove old one
		if (!$is_single_post) {
			$ids_str = count($ids) > 0 ? implode(',', $ids) : '0';
			$rs = $wpdb->get_col("select ID from {$wpdb->posts} where post_type='{$post_type}' and ID not in ({$ids_str}) ");
			foreach ($rs as $rid) {
				$wpdb->query("delete from {$wpdb->posts} where ID = {$rid} ");
				$wpdb->query("delete from {$wpdb->postmeta} where post_id = {$rid} ");
			}
		}
		if ($post_type == 'help_support' && $is_switch) {
			restore_current_blog();
		}

		return $ids;
	}


	public function get_db_used()
	{
		global $wpdb;

		return $wpdb->get_var("select sum(data_length+index_length) from information_schema.tables");
	}

	public function get_db_queries()
	{
		global $wpdb;

		return $wpdb->get_results("show full processlist", ARRAY_A);
	}

	public function check_abx_version()
	{
		$cversion = get_option('ci_abx_last_version', 0);
		$opt = get_option('ci_pdf_data', array());
		$args = array(
			'version' => $cversion,
			'abx_enable' => $opt['new_abx'] ? 1 : 0,
		);
		$return 	= $this->request('check_abx_version', array('body' => $args));

		if ($return['status'] == 'ok') {
			$abx_version 	= get_option('ci_abx_table_versions', array());
			if (isset($return['version']) && $return['version'] > 0) {

				$abx_version['version'] = $return['version'];
			}

			if (isset($return['tables']) && is_array($return['tables'])) {
				$abx_version['lastversions'] = $return['tables'];
			}


			update_option('ci_abx_table_versions', $abx_version, false);
		}
	}

	public function get_available_versions()
	{
		$return = $this->request('get_abx_versions_list', array('body' => array()));
		if (!empty($return['status']) && $return['status'] === 'ok') {
			return $return['versions'];
		}
		return array();
	}
	public function sync_version_table($table, $version_name)
	{
		global $ci_update_log;
		$ci_update_log = '';
		$table = sanitize_text_field($table);
		$version_name = sanitize_text_field($version_name);

		$abx_version = get_option('ci_abx_table_versions', array());
		if (!isset($abx_version['lastversions'][$table]) && empty($version_name)) {
			return array('success' => false, 'msg' => "Table '{$table}' not found.");
		}

		$rs = $this->update_abx_table($table, $version_name);

		if ($rs === 0) {
			unset($abx_version['tables'][$table]);
			unset($abx_version['lastversions'][$table]);
			update_option('ci_abx_table_versions', $abx_version, false);
			return array('success' => false, 'msg' => "Table '{$table}' not exist.", 'log' => $ci_update_log);
		} else if ($rs) {
			return array('success' => true, 'msg' => "Table '{$table}' synced.", 'log' => $ci_update_log);
		}

		return array('success' => false, 'msg' => "Sync failed for '{$table}'.", 'log' => $ci_update_log);
	}

	public function finish_sync_version($version_name)
	{
		if ($version_name === 'latest') {
			$abx_version = get_option('ci_abx_table_versions', array());
			if (isset($abx_version['named_version'])) {
				unset($abx_version['named_version']);
				update_option('ci_abx_table_versions', $abx_version, false);
			}
			delete_option('ci_abx_current_named_version');
		} else {
			update_option('ci_abx_current_named_version', sanitize_text_field($version_name), false);
		}

		update_option('ci_abx_last_change', date_i18n('YmdHis'));
		update_option('_ci_last_update_abx', time(), false);

		$this->request('sync_abx_version_lab', array('body' => array('version_name' => $version_name)));
	}

	public function update_abx_table($table, $version_name = '')
	{
		global $wpdb, $ci_update_log;
		$ci_update_log = '';

		$abx_version 	= get_option('ci_abx_table_versions', array());
		if (!isset($abx_version['lastversions'])) {
			$abx_version = array('tables' => array(), 'lastversions' => array(), 'version' => 0);
		}
		$cversion 		= isset($abx_version['tables'][$table]) ? $abx_version['tables'][$table] : 0;
		$opt = get_option('ci_pdf_data', array());
		$args = array(
			'table' => $table,
			'version' => $cversion,
			'abx_enable' => $opt['new_abx'] ? 1 : 0,
		);
		if (!empty($version_name)) {
			$args['version_name'] = $version_name;
		}

		$start_time = microtime(true);
		$return 	= $this->request('get_abx_table', array('body' => $args));

		$ci_update_log .= $return['status'] . ' => Get Data time: ' . round(microtime(true) - $start_time, 2) . " seconds\n";

		if ($return['status'] == 'not_exist') {
			return 0;
		} else if ($return['status'] == 'last') {
			return true;
		} else if ($return['status'] == 'ok') {
			$create = $return['create'];
			$data	= $return['data'];
			$newversion = $return['version'];

			if (empty($create)) {
				//echo 'empty create';
				return false;
			} else if (empty($version_name) && $newversion == $cversion) {
				//dont update if same version
				return true;
			} else {
				$start_time = microtime(true);
				$oldtables = array('ci_abx_resistance_markers', 'ci_abx_bacterias');
				//store old type first
				$olddata = array();
				$keyupdates = array('uti', 'wound', 'gi');
				$countbykeys = array('uti' => 0, 'wound' => 0, 'gi' => 0);
				if (in_array($table, $oldtables)) {
					$rsdata = $wpdb->get_results("select ID,uti,wound,gi from {$table}", ARRAY_A);
					foreach ($rsdata as $row) {
						$olddata[$row['ID']] = $row;
						foreach ($keyupdates as $k) {
							if ($row[$k]) {
								$countbykeys[$k]++;
							}
						}
					}
				}
				$wpdb->query("drop table {$table}");
				$wpdb->query($create);

				$data_error = 0;
				$this->logcount = count($data);
				if ($this->logcount > 0) {
					$columns = array_keys($data[0]);
					$columns_list = implode('`,`', $columns);
					$sql = "INSERT INTO `$table` (`$columns_list`) VALUES ";
					$placeholders = [];

					foreach ($data as $row) {

						if (in_array($table, $oldtables)) {
							foreach ($keyupdates as $k) {
								if (isset($row[$k]) && $countbykeys[$k] > 0) {
									if (isset($olddata[$row['ID']][$k])) {
										$row[$k] = $olddata[$row['ID']][$k];
									} else {
										$row[$k] = 0;
									}
								}
							}
						}

						$placeholders[] = $wpdb->prepare(
							'(' . implode(',', array_fill(0, count($row), '%s')) . ')',
							array_values($row)
						);
					}

					$sql .= implode(', ', $placeholders);
					$rsq = $wpdb->query($sql);
					if ($rsq == false) {
						$data_error++;
					}
				}



				$ci_update_log .= 'count rows: ' . $this->logcount . ', success: ' . $return['success'] . ', error: ' . $return['error'] . "\n";
				$ci_update_log .= 'Update Data time: ' . round(microtime(true) - $start_time, 2) . " seconds\n";



				if ($data_error == 0) {
					if ($newversion > 0) {
						$abx_version['tables'][$table] = $newversion;
					}

					if (!empty($version_name)) {
						$abx_version['applied_version'] = $version_name;
					}
					update_option('ci_abx_table_versions', $abx_version, false);
				}
			}

			return true;
		}
		return false;
	}
	public function get_statuses($args)
	{
		$return 	= $this->request('get_batch_statuses', array('body' => $args));
		return $return;
	}

	public function force_send_report($args)
	{
		$return 	= $this->request('force_send_report', array('body' => $args));
		return $return;
	}
	public function sync_close_lab($args)
	{
		$return 	= $this->request('sync_close_lab', array('body' => $args));
		return $return;
	}

	public function send_to_hub($args)
	{
		$return 	= $this->request('send_data', array('body' => $args));
		return $return;
	}
	public function check_auth_hub($args)
	{
		$return 	= $this->request('check_auth_hub', array('body' => $args));
		return $return;
	}
	public function forgot_password_hub($args)
	{
		$return 	= $this->request('forgot_password_hub', array('body' => $args));
		return $return;
	}

	public function send_user_feedback($args)
	{
		$return = $this->request('send_user_feedback', array('body' => $args));

		return $return;
	}
	public function send_mail_to_hub($args)
	{
		//check exclude email
		$exclude_emails = array(
			'info@sozodx.com',
			'admin@communityhealthnetworklab.com',
			'admin@asaplabsandcare.com',
			'info@obgynmontgomery.com',
			'info@urosurgeryhouston.com',
			'info@mhp.healthcare',
			'james@ionmdx.com',
			'admin@catalystdiagnostic.com',
			'admin@thefootandankleclinicofwestmonroe.com'
		);
		if (
			isset($args['to'])
			&& (in_array($args['to'], $exclude_emails)
				|| stripos($args['to'], '@lab.') > 0
				|| stripos($args['to'], '@@patients.') > 0)
		) {
			return true;
		}
		$return = $this->request('send_mail_to_hub', array('body' => $args));

		if (isset($return['status']) && in_array($return['status'], array('ok', 'success'))) {
			return true;
		}
		return false;
	}

	public function sync_messages($messages = null)
	{
		global $wpdb;


		if (!is_array($messages)) {
			$return = $this->request('get_messages_sync_data');
			if (!isset($return['status']) || $return['status'] !== 'ok') {
				return false;
			}

			$messages = isset($return['messages']) && is_array($return['messages']) ? $return['messages'] : array();
		}

		$current_ids = array();
		$has_changes = false;

		foreach ($messages as $message) {
			$hub_id = isset($message['ID']) ? absint($message['ID']) : 0;
			if ($hub_id <= 0) {
				continue;
			}

			$local_id = 0 - $hub_id;
			$current_ids[] = $local_id;

			$data = array(
				'subject' => isset($message['subject']) ? $message['subject'] : '',
				'msg' => isset($message['msg']) ? $message['msg'] : '',
				'author' => 0,
				'created_date' => !empty($message['created_date']) ? $message['created_date'] : current_time('mysql'),
				'from_date' => (!empty($message['from_date']) && $message['from_date'] !== '0000-00-00') ? $message['from_date'] : null,
				'to_date' => (!empty($message['to_date']) && $message['to_date'] !== '0000-00-00') ? $message['to_date'] : null,
				'background' => isset($message['background']) ? $message['background'] : '',
				'msg_type' => (isset($message['msg_type']) && $message['msg_type'] === 'a') ? 'a' : 'n',
				'read_roles' => isset($message['read_roles']) ? $message['read_roles'] : '',
			);

			$exists = (int) $wpdb->get_var($wpdb->prepare("SELECT ID FROM ci_messages WHERE ID = %d", $local_id));
			if ($exists !== 0) {
				$wpdb->update('ci_messages', $data, array('ID' => $local_id));
			} else {
				$data['ID'] = $local_id;
				$wpdb->insert('ci_messages', $data);
			}

			if ($wpdb->last_error === '') {
				$has_changes = true;
			}
		}

		$keep_ids_sql = !empty($current_ids) ? implode(',', array_map('intval', $current_ids)) : '0';
		$stale_ids = $wpdb->get_col("SELECT ID FROM ci_messages WHERE ID < 0 AND ID NOT IN ({$keep_ids_sql})");

		if (!empty($stale_ids)) {
			$stale_ids_sql = implode(',', array_map('intval', $stale_ids));
			$msg_user_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', 'ci_message_users'));
			if ($msg_user_table === 'ci_message_users') {
				$wpdb->query("DELETE FROM ci_message_users WHERE msg_ID IN ({$stale_ids_sql})");
			}

			$wpdb->query("DELETE FROM ci_messages WHERE ID IN ({$stale_ids_sql})");
			$has_changes = true;
		}

		if ($has_changes && function_exists('trpdd_touch_notification_cache_last_changed_at')) {
			trpdd_touch_notification_cache_last_changed_at();
		}

		return true;
	}

	public function sync_post_type($post_type)
	{
		$args = array(
			'post_type' => $post_type
		);
		$return 	= $this->request('get_postdata', array('body' => $args));

		if ($return['status'] == 'ok') {
			return $this->_update_postdata($post_type, $return['posts']);
		}
		return false;
	}


	public function sync_hub_table($table)
	{
		global $wpdb;
		$args = array(
			'table' => $table
		);
		$return 	= $this->request('get_table_sync_data', array('body' => $args));
		if ($return['status'] == 'ok') {
			$create = $return['create'];
			$data	= $return['data'];
			if (!empty($create) && !empty($data)) {
				$wpdb->query("drop table {$table}");
				$wpdb->query($create);

				foreach ($data as $row) {

					$wpdb->insert($table, $row);
				}
			}
		}
		return false;
	}
	public function sync_panel_types()
	{
		$return = $this->request('get_panel_types');
		if ($return['status'] == 'ok') {
			update_option('panel_types', $return['panel_types']);
			return true;
		}
		return false;
	}

	public function send_unsent_state_reports($unsent_reports)
	{
		$return = $this->request('send_unsent_state_reports', array('body' => array('unsent_reports' => $unsent_reports)));
		if ($return['status'] == 'ok') {
			return true;
		}
		return false;
	}

	/*
	 * public requests
	 */


	public function request_get_ip()
	{
		$return = array('status' => 'error');
		$return['ip'] = $_SERVER['REMOTE_ADDR'];
		$this->response(200, $return);
		return $return;
	}

	public function request_sync_statistics()
	{
		$return = array('success' => 0, 'count_done' => 0, 'count_error' => 0, 'message' => '');
		if ($this->check_key() && isset($this->params['from']) && isset($this->params['to'])) {
			$from = $this->params['from'];
			$to = $this->params['to'];
			$update_panel = $this->params['update_panel'];
			$start = strtotime($from);
			$end   = strtotime($to);
			$failed = false;
			for ($date = $start; $date <= $end; $date = strtotime('+1 day', $date)) {
				$day = date_i18n('Y-m-d', $date);
				$rs = $this->send_statistics($day, true, false, $update_panel);
				if ($rs === false) {
					$failed = true;
					$return['count_error']++;
				} else {
					$return['count_done']++;
				}
			}

			$return['success'] = $failed ? 0 : 1;
		}
		$this->response(200, $return);
	}
	public function request_sync_panel_colors()
	{
		$return = array('success' => 0, 'message' => '');
		if ($this->check_key() && isset($this->params['panel_types'])) {
			$panel_types = $this->params['panel_types'] ?: [];
			update_option('panel_types', $panel_types);
			$return['success'] = 1;
		}
		$this->response(200, $return);
	}

	public function request_ping()
	{
		$return = array('success' => 0, 'message' => '');

		if ($this->params['key'] == 'abc123') {
			if (isset($this->params['send_statistics'])) {
				$date = date_i18n('Y-m-d');
				$return = $this->send_statistics($date);
			} else {
				$st = get_option('ci_lab_client_st', array());

				if ($st['dbv'] != TRLCN_V) {
					include_once(TRLCN_PATH . 'inc/install.php');
					$st['dbv'] = TRLCN_V;
					update_option('ci_lab_client_st', $st, false);
				}
				$return = $this->get_key();
			}
		}
		$this->response(200, $return);
	}

	public function request_set_key()
	{
		$return = array('success' => 0, 'message' => '');

		if ($this->check_key(true) && !empty($this->params['new_key'])) {
			$this->lab_key = $this->params['new_key'];
			$this->set_option('key', $this->lab_key);
			$this->set_option('keytime', time());
			$return['success'] = 1;
		}
		$this->response(200, $return);
	}

	public function request_set_settings()
	{
		$return = array('success' => 0, 'message' => '');

		if ($this->check_key()) {
			if (!empty($this->params['opts'])) {

				foreach ($this->params['opts'] as $k => $vl) {

					if ($k == 'hub_adding_tests') {
						$old_opts 	= get_option('ci_send_hub_tests', array());

						if (!is_array($vl)) {
							$vl = array();
						}
						$new_opts = array();
						foreach ($vl as $i => $arr) {
							$new_opts[$arr['tid'] . '_' . $arr['state']] = $arr;
						}
						$haschange = false;
						foreach ($old_opts as $tk => $arr) {
							$opt_key = $arr['tid'] . '_' . $arr['state'];
							if (isset($new_opts[$opt_key])) {
								$newopt = $new_opts[$opt_key];
								if ($arr['status'] != $newopt['status']) {
									$old_opts[$tk]['status'] = $newopt['status'];
									$haschange = true;
								}
							}
						}
						//$return['changed'] = $vl;
						if ($haschange) {
							update_option('ci_send_hub_tests', $old_opts);
						}
					} else {
						$this->set_option($k, $vl);
					}
				}

				$return['success'] = 1;

				$nextcr = wp_next_scheduled('cron_lab_client');

				if ($this->params['opt_name'] == 'currentdate') {
					wp_schedule_single_event(time() + 30, 'cron_lab_client');
				} else if ($nextcr > time() + 3600) {
					wp_schedule_single_event(time() + 30, 'cron_lab_client');
				}
			} else {
				$return['errorCode'] = 102;
			}
		} else {
			$return['errorCode'] = 101;
			$return['errorMsg'] = "Key is wrong";
		}
		$this->response(200, $return);
	}



	public function request_set_site_options()
	{
		$return = array('success' => 0, 'message' => '');

		if ($this->check_key()) {
			if (!empty($this->params['opt_name'])) {
				$is_patient_site = isset($this->params['site']) && $this->params['site'] == 'patient';

				$patient_blog_id = defined('CI_PATIENT_BLOG_ID') && CI_PATIENT_BLOG_ID > 0 ? CI_PATIENT_BLOG_ID : 2;

				if (isset($this->params['opts']) && is_array($this->params['opts'])) {
					if ($is_patient_site) {
						$opts = get_blog_option($patient_blog_id, $this->params['opt_name'], array());
					} else {
						$opts = get_option($this->params['opt_name'], array());
					}

					if (is_array($opts)) {
						foreach ($this->params['opts'] as $k => $vl) {
							$opts[$k] = $vl;
						}

						if ($is_patient_site) {
							update_blog_option($patient_blog_id, $this->params['opt_name'], $opts);
						} else {
							update_option($this->params['opt_name'], $opts);
						}


						$return['success'] = 1;
					}
				} else if (isset($this->params['opt_value'])) {
					update_blog_option($patient_blog_id, $this->params['opt_name'], $this->params['opt_value']);
					$return['success'] = 1;
				}
			} else {
				$return['errorCode'] = 102;
			}
		} else {
			$return['errorCode'] = 101;
		}
		$this->response(200, $return);
	}

	public function request_get_site_options()
	{
		$return = array('success' => 0, 'message' => '');

		if ($this->check_key()) {
			$is_patient_site = $this->params['site'] == 'patient';

			if (isset($this->params['define'])) {
				$return['defined'] = defined($this->params['define']) ? constant($this->params['define']) : false;
			}

			if (isset($this->params['function'])) {
				$return['function_exist'] = function_exists($this->params['function']);
			}

			if (!empty($this->params['opt_name'])) {
				$patient_blog_id = defined('CI_PATIENT_BLOG_ID') && CI_PATIENT_BLOG_ID > 0 ? CI_PATIENT_BLOG_ID : 2;

				if ($is_patient_site) {
					$opts = get_blog_option($patient_blog_id, $this->params['opt_name']);
				} else {
					$opts = get_option($this->params['opt_name']);
				}

				$return['success'] = 1;
				$return['opt_value'] = $opts;
			} else {
				$return['errorCode'] = 102;
			}
		} else {
			$return['errorCode'] = 101;
		}
		$this->response(200, $return);
	}

	public function request_get_wp_version()
	{
		global $wp_version, $wpdb;
		$return = array('success' => 0, 'message' => '');
		if ($this->check_key()) {
			$return['success'] = 1;
			$return['phpv'] = phpversion();
			$return['version'] = $wp_version;
			$return['p_version'] = TRCPDD_V;

			$return['servertype'] = $this->getservertype();

			$return['db_version'] = get_option('TRCPDD_VS');
			$return['free_space'] = disk_free_space(".");
			$return['free_space_root'] = disk_free_space("/");

			$return['loadavg'] = sys_getloadavg();
			$return['db_used'] = $this->get_db_used();
			$return['queries'] = $this->get_db_queries();
			$return['abx_version'] = get_option('ci_abx_last_version', 0);
			$return['table_versions'] = get_option('ci_abx_table_versions', array());

			$opt = get_option('ci_pdf_data', array());
			$return['abx_enable'] = $opt['new_abx'] ? 1 : 0;
			$return['abx_panels'] = array();

			if (!empty($return['abx_enable'])) {
				$groups = $wpdb->get_results(
					"SELECT tg_name, tg_other_options FROM ci_test_groups WHERE tg_parent = 0"
				);
				foreach ($groups as $g) {
					$opts = json_decode($g->tg_other_options, true);
					if (!empty($opts['new_abx'])) {
						$return['abx_panels'][] = $g->tg_name;
					}
				}
			}

			if (isset($opt['close_lab']) && $opt['close_lab']) {
				$return['closed'] = true;
			}
			$is_ark = is_dir(WP_PLUGIN_DIR . '/ci-data-works-ark');
			$return['is_ark'] = $is_ark;

			if (!function_exists('get_plugins')) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$all_plugins    = get_plugins();
			$active_plugins = get_option('active_plugins', array());
			$active_plugin_info = array();

			foreach ($active_plugins as $plugin_file) {
				$slug = explode('/', $plugin_file)[0];

				$active_plugin_info[] = array(
					'slug'    => $slug,
					'version' => isset($all_plugins[$plugin_file]['Version']) ? $all_plugins[$plugin_file]['Version'] : '',
				);
			}
			if ($is_ark) {
				$has_ark = false;
				foreach ($active_plugin_info as $p) {
					if ($p['slug'] === 'ci-data-works-ark') {
						$has_ark = true;
						break;
					}
				}

				if (!$has_ark) {
					$active_plugin_info[] = array(
						'slug'    => 'ci-data-works-ark',
						'version' => defined('TRCPDD_V') ? TRCPDD_V : '',
					);
				}
			}

			$return['active_plugins_detail'] = $active_plugin_info;
		} else {
			$return['errorCode'] = 101;
		}
		$this->response(200, $return);
	}

	public function request_get_ssl_client_version()
	{
		$return = array('success' => 0, 'message' => '');
		if ($this->check_key()) {
			$url = 'https://www.howsmyssl.com/a/check';
			if (isset($this->params['url']) && !empty($this->params['url'])) {
				$url = $this->params['url'];
			}
			$data = file_get_contents($url);
			$return['success'] = 1;
			$return['message'] = $data;
			$return['version'] = OPENSSL_VERSION_TEXT;
		} else {
			$return['errorCode'] = 101;
		}
		$this->response(200, $return);
	}

	public function request_fix_db()
	{
		$return = array('success' => 1, 'message' => '');
		if ($this->check_key()) {
			ob_start();

			$st = get_option('ci_lab_client_st', array());

			if ($st['dbv'] != TRLCN_V) {
				include_once(TRLCN_PATH . 'inc/install.php');
				$st['dbv'] = TRLCN_V;
				update_option('ci_lab_client_st', $st, false);
			}


			$db_version = get_option('TRCPDD_VS');
			if ($db_version != TRCPDD_VS) {
				trpdd_plugin_activate();
			}

			//fix sms auth
			if (function_exists('get_network_option')) {
				$f = WP_CONTENT_DIR . '/plugins/ci-otp-authenticator/inc/class-otpa-logger.php';
				if (file_exists($f)) {
					try {
						include($f);
						$result = Otpa_Logger::install();
					} catch (Exception $e) {
					}
				}
			}


			include(TRCPDD_PATH . 'inc/fix_db.php');
			global $wpdb;
			$wpdb->query("delete from {$wpdb->options} where option_name like 'ci_billing_list_cache%'");
			$return['message'] = ob_get_clean();
		}
		$this->response(200, $return);
	}
	public function request_remove_ban()
	{
		$return = array('success' => 1, 'message' => '');
		if ($this->check_key() && $this->params['k2'] == 'h95uy14mnt') {
			global $wpdb;
			$wpdb->query("delete from wp_tr_lock_ip");
			$wpdb->query("delete from {$wpdb->usermeta} where meta_key='_tr_security' ");
		}
		$this->response(200, $return);
	}


	public function request_sync_state_sent()
	{
		global $wpdb;
		$return = array('success' => 1, 'message' => '', 'list' => array());
		if ($this->check_key()) {
			$assens = $this->params['assens_covid'];

			foreach ($assens as $assen => $datetime) {
				if (!empty($assen) && !empty($datetime)) {
					$rsi = $wpdb->insert('ci_hub_statistics', array('assen' => $assen, 'date_report' => $datetime));
					if ($rsi !== false) {
						$return['list'][] = $assen;
					} else {
						if ($wpdb->get_var($wpdb->prepare("select assen from ci_hub_statistics where assen = %s", $assen))) {
							$return['list'][] = $assen;
						}
					}
				}
			}
		}
		$this->response(200, $return);
	}

	public function request_count_types()
	{
		global $wpdb;
		$return = array('success' => 0, 'message' => '', 'list' => array());
		if ($this->check_key()) {
			$return['success'] = 1;
			foreach (tr_pdd_get_batch_types() as $btype) {
				$table_batch = tr_pdd_get_table_by_btype($btype);
				$table_report = tr_pdd_get_table_report_by_btype($btype);

				$arr = array();
				$arr['count'] = $wpdb->get_var("select count(*) from {$table_batch} ");
				$arr['lastdate'] = $wpdb->get_var("select doc from {$table_batch} order by doc desc limit 1");

				$return['list'][$btype] = $arr;

				if ($btype == 'pcr') {
					$arr2 = array();
					$arr2['count'] = $wpdb->get_var("select count(*) from ci_results_antibiotics where btype = 'pcr'");
					$arr2['groups'] = $wpdb->get_col("select tg_name from ci_test_groups where tg_manual_antibiotic = 1 and tg_type = 'pcr' ");
					$return['list']['micpcr'] = $arr2;
				} else if ($btype == 'blood') {
					$arr2 = array();
					$arr2['count'] = $wpdb->get_var("select count(*) from ci_results_antibiotics where btype = 'blood'");
					$arr2['groups'] = $wpdb->get_col("select tg_name from ci_test_groups where tg_manual_antibiotic = 1 and tg_type = 'blood' ");
					$return['list']['micblood'] = $arr2;
				}
			}
		}
		$this->response(200, $return);
	}

	public function request_panel_types()
	{
		global $wpdb;
		$return = array('success' => 0, 'message' => '', 'list' => array());
		if ($this->check_key()) {
			$return['success'] = 1;
			foreach (tr_pdd_get_batch_types() as $btype) {
				$arr = array();
				$arr['panels'] = $wpdb->get_col("select tg_name from ci_test_groups where tg_type='{$btype}'
											and tg_show_req = 1 and tg_parent = 0
											order by tg_position asc");

				$return['list'][$btype] = $arr;
			}

			$return['list']['pats'] = $wpdb->get_var("select count(*) from ci_test_panels where te_type='pats' ");
		}
		$this->response(200, $return);
	}

	public function request_nginx_log_list()
	{
		$return = array('success' => 0, 'message' => '', 'list' => array());
		if ($this->check_key()) {
			$dir = $this->get_error_dir();

			$rd  = opendir($dir);
			while ($f = readdir($rd)) {
				if (!in_array($f, array('.', '..'))) {
					$filepath         = $dir . '/' . $f;
					$return['list'][] = array(
						'file' => $f,
						'size' => filesize($filepath),
						'time' => filemtime($filepath),
					);
				}
			}
			$return['success'] = 1;
		}
		$this->response(200, $return);
	}

	public function request_nginx_get_log()
	{
		$return = array('success' => 0, 'message' => '', 'list' => array());
		$dir = $this->get_error_dir();
		if ($this->check_key()) {
			$return['success'] = 1;
			$log_file 	= $this->params['file'];
			$filepath 	= $dir . '/' . $log_file;
			$nginxlogdir = WP_CONTENT_DIR . '/log/nginx';
			$nginxlogurl = 'https://' . $_SERVER['HTTP_HOST'] . '/wp-content/log/nginx/';
			$return['logdir'] = $dir;
			if (stripos($dir, '/log/errors') > 0) {
				$nginxlogdir = $dir;
				list($ttt, $fdname) = explode('wp-content', $dir);
				$nginxlogurl = 'https://' . $_SERVER['HTTP_HOST'] . '/wp-content/' . $fdname . '/';
			}
			if (!file_exists($nginxlogdir)) {
				wp_mkdir_p($nginxlogdir);
			}
			if (file_exists($filepath)) {
				if (isset($this->params['search']) && !empty($this->params['search'])) {
					$fh = fopen($log_file, 'r');
				} else {
					if ($nginxlogdir != $dir) {
						$rs = copy($filepath, $nginxlogdir . $log_file);
					} else {
						$rs = true;
					}

					if ($rs) {
						$return['filepath'] =  $nginxlogurl . $log_file;
					} else {
						$return['data'] = file_get_contents($filepath);
					}
				}
			}
		}
		$this->response(200, $return);
	}

	public function request_empty_nginx_log()
	{
		$return = array('success' => 0, 'message' => '', 'list' => array());
		$dir = $this->get_error_dir();
		if ($this->check_key()) {
			$return['success'] = 1;
			$log_file = $this->params['file'];
			if ($log_file == 'all') {
				$rd  = opendir($dir);
				while ($f = readdir($rd)) {
					if (!in_array($f, array('.', '..'))) {
						$filepath         = $dir . '/' . $f;
						$return['list'][] = array(
							'file' => $f,
							'size' => filesize($filepath),
							'time' => filemtime($filepath),
							'deleted' => file_put_contents($filepath, '')
						);
					}
				}
				$return['success'] = 1;
			} else {
				$filepath = $dir . '/' . $log_file;
				if (file_exists($filepath)) {
					$return['success'] = file_put_contents($filepath, '');
				}
			}
		}
		$this->response(200, $return);
	}

	public function request_sms_login_log()
	{
		global $wpdb;
		$return = array('success' => 0, 'message' => '');
		if ($this->check_key()) {
			$log_file = WP_CONTENT_DIR . '/log/sms_user_not_found.txt';

			if (file_exists($log_file)) {
				$return['success'] = 1;
				$return['filetime'] = filemtime($log_file);
				$return['filesize'] = filesize($log_file);


				$f = fopen($log_file, 'r');

				if ($return['filesize'] > 100000) {
					fseek($f, $return['filesize'] - 100000);
				}
				$return['lastdata'] = fread($f, 100000);
				fclose($f);

				if ($this->params['empty']) {
					file_put_contents($log_file, '');
				}
			} else {
				$return['success'] = 2;
			}
		}
		$this->response(200, $return);
	}

	public function request_find_phone()
	{
		$return = array('success' => 1, 'found' => false);
		if ($this->check_key() && $this->params['k2'] == 'asdfjlkj') {
			global $wpdb;
			$phone = $this->params['phone'];
			$options = isset($this->params['options']) && is_array($this->params['options']) ? $this->params['options'] : array();

			$where = "  ";
			if ($options['byname']) {
				if (strpos($phone, ',') > 0) {
					list($lastname, $firstname) = explode(',', $phone);
				} else {
					list($firstname, $lastname) = explode(' ', $phone);
				}
				$firstname = trim($firstname);
				$lastname = trim($lastname);
				if (!empty($firstname)) {
					$where .= " and pa_first = '{$firstname}' ";
				}

				if (!empty($lastname)) {
					$where .= " and pa_last = '{$lastname}' ";
				}
			} else {
				$where .= " and pa_phone like '%{$phone}%' ";
			}

			$patients = $wpdb->get_results(
				"select pa_ID,pa_first,pa_last,pa_phone
								from ci_patients 
								where 1 {$where}",
				ARRAY_A
			);

			if (count($patients) > 0) {
				if ($options['has_result']) {
					foreach ($patients as $i => $p) {
						$hasrs = $wpdb->get_var("
								select ba.assen
								from ci_batches_pcr ba
								join ci_reports_pcr re ON re.patient_id = ba.assen
								where ba.patient_ID = {$p->pa_ID}");
						$patients[$i]['result'] = $hasrs;
					}
				}
				$return['found'] = $patients;
			} else if ($this->params['findtype'] == 'phone') {
				$patientid = $wpdb->get_var("select user_id from {$wpdb->usermeta} 
									where meta_key='phone' and meta_value like '%{$phone}%' ");
				if ($patientid > 0) {
					$return['found'] = $patientid;
				}
			}
		}
		$this->response(200, $return);
	}

	public function request_table_info()
	{
		global $wpdb;
		$return = array('success' => 0, 'message' => '');
		if ($this->check_key()) {
			$return['success'] = 1;
			$return['info'] = $wpdb->get_results("explain {$this->params['table']}", ARRAY_A);

			$opt = get_option('ci_pdf_data', array());
			$return['abx_enable'] = $opt['new_abx'] ? 1 : 0;
			if (isset($opt['close_lab']) && $opt['close_lab']) {
				$return['closed'] = true;
			}
		}
		$this->response(200, $return);
	}

	public function request_check_required()
	{
		global $wpdb;
		$return = array('success' => 0, 'message' => '');
		if ($this->check_key()) {
			$return['success'] = 1;
			include_once(TRCPDD_PATH . 'inc/functions_fix.php');
			if (function_exists('trpdd_check_plugin_requireds')) {
				$return['list'] = trpdd_check_plugin_requireds();
			} else {
				include_once(TRLCN_PATH . 'inc/functions_fix.php');
				$return['list'] = trclient_check_plugin_requireds();
			}

			$add_user_ini = array();
			foreach ($return['list'] as $arr) {
				if ($arr['name'] == 'max_input_vars') {
					$add_user_ini['max_input_vars'] = '50000';
				} else if ($arr['name'] == 'memory_limit') {
					$add_user_ini['max_input_vars'] = '500M';
				}
			}

			if (count($add_user_ini) > 0) {
				$this->fix_user_ini_file($add_user_ini);
			}
		}
		$this->response(200, $return);
	}

	public function request_update_plugin()
	{
		global $wpdb;
		$return = array('success' => 0, 'message' => '');
		if ($this->check_key()) {
			$return['success'] = 1;
			include_once ABSPATH . 'wp-includes/pluggable.php';
			include_once ABSPATH . 'wp-admin/includes/file.php';
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
			include_once ABSPATH . 'wp-admin/includes/misc.php';
			include_once ABSPATH . 'wp-admin/includes/template.php';
			include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			include_once ABSPATH . 'wp-admin/includes/screen.php';

			$plugin_url = '';
			if (isset($this->params['plugin_url']) && $this->params['plugin_url']) {
				$plugin_url = $this->params['plugin_url'];
			}
			if (empty($plugin_url)) {
				$return['message'] = 'error';
			} else {
				ob_start();
				$upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
				$upgrader->skin->done_header = true;
				$upgrader->skin->done_footer = true;

				$plugins        = get_plugins('/' . $this->params['plugin']);
				$plugin_file    = '';
				foreach ($plugins as $file => $plugin) {
					$plugin_file = $file;
				}
				if (empty($plugin_file)) {
					$plugin_file = 'plug.php';
				}

				$plugin = $this->params['plugin'] . '/' . $plugin_file;

				$result   = $upgrader->run(array(
					'package' => $plugin_url,
					'destination' => WP_PLUGIN_DIR,
					'clear_destination' => true,
					'clear_working' => true,
					'is_multi' => true,
					'hook_extra' => array(
						'plugin' => $plugin
					)
				));

				if ($this->params['active']) {
					$rs_active = activate_plugin($plugin);
					if (!is_wp_error($rs_active)) {
						$return['active'] = 1;
					} else {
						$return['active'] = 0;
					}
				}
				ob_get_clean();

				$return['success'] = 1;
				$return['rs'] = $result;
				$plugin_file = WP_PLUGIN_DIR . '/' . $plugin;
				if (file_exists($plugin_file)) {
					$plugin_data = get_plugin_data($plugin_file, false, false);
					$return['version'] = $plugin_data['Version'];
				} else {
					$return['version'] = '';
				}
			}
		} else {
			$return['message'] = 'wk';
		}
		$this->response(200, $return);
	}

	public function request_move_admin_role()
	{
		global $wpdb;
		$return = array('success' => 0, 'message' => '', 'users' => array());
		if ($this->check_key()) {
			include_once(ABSPATH . '/wp-includes/pluggable.php');

			$return['success'] = 1;
			$users = get_users(array('role' => 'administrator', 'fields' => 'all'));

			foreach ($users as $user) {
				if (in_array($user->user_login, array('Administrat0r', 'cornel1', 'lis-admin'))) {
					continue;
				}

				update_user_meta($user->ID, $user->cap_key, array('AdminCIDW' => true));

				$return['users'][] = $user->user_login;
			}
		}
		$this->response(200, $return);
	}


	public function request_abx_check()
	{
		global $wpdb;
		$return = array('success' => 0, 'message' => '');
		if ($this->check_key()) {
			$return['success'] = 1;

			$where = '';
			if (!empty($this->params['q'])) {
				$where = ' where ' . $this->params['q'];
			}
			$return['count_bw'] = $wpdb->get_var("select count(*) from ci_bacteria_wound {$where}");
			$return['count_bu'] = $wpdb->get_var("select count(*) from ci_bacteria_uti {$where}");
			$return['count_bg'] = $wpdb->get_var("select count(*) from ci_bacteria_gi {$where}");
			$return['where'] = $where;

			$opt = get_option('ci_pdf_data', array());
			$return['abx_enable'] = $opt['new_abx'] ? 1 : 0;
			if (isset($opt['close_lab']) && $opt['close_lab']) {
				$return['closed'] = true;
			}
		}
		$this->response(200, $return);
	}

	//new version
	public function request_sync_abx_data()
	{
		global $wpdb;
		$return = array('success' => 0);
		if ($this->check_key()) {

			$opt = get_option('ci_pdf_data', array());

			$last_version = !empty($this->params['version']) ? $this->params['version'] : date_i18n('YmdHis');
			$version_name = isset($this->params['version_name']) ? sanitize_text_field($this->params['version_name']) : '';

			$this->params['tables'] = json_decode($this->params['tables'], true);

			$abx_version 	= get_option('ci_abx_table_versions', array());
			if (!isset($abx_version['tables'])) {
				$abx_version = array('tables' => array(), 'lastversions' => array(), 'version' => 0);
			}
			$abx_version['version'] = $last_version;
			$abx_version['lastversions'] = $this->params['tables'];

			if (!empty($version_name)) {
				$abx_version['named_version'] = $version_name;
			} else {
				unset($abx_version['named_version']);
			}

			update_option('ci_abx_table_versions', $abx_version, false);
			if ($opt['new_abx']) {

				//add cron cron_update_abx
				$nextcron = wp_next_scheduled('cron_update_abx');
				if ($nextcron == 0) {
					wp_schedule_single_event(time() + 30, 'cron_update_abx');
				}
			}


			$abx_file = TRCPDD_PATH . 'inc/ci_abx.php';

			$return['success'] = 1;
			$return['tables'] = $abx_version['tables'];
			$return['cversion'] = get_option('ci_abx_last_version', 0);
			$return['abxversion'] = filemtime($abx_file);
			$return['dompdfversion'] = defined('TRCDPDF_V') ? TRCDPDF_V : '';
			$return['TRCPDD_VS'] = defined('TRCPDD_VS') ? TRCPDD_VS : '';


			$return['abx_enable'] = $opt['new_abx'] ? 1 : 0;
			if (isset($opt['close_lab']) && $opt['close_lab']) {
				$return['closed'] = true;
			}
		}
		$this->response(200, $return);
	}

	//new version
	public function request_get_abx_version()
	{
		global $wpdb;
		$return = array('success' => 0, 'message' => '');

		if ($this->check_key()) {

			$abx_file = TRCPDD_PATH . 'inc/ci_abx.php';

			$all_btypes = tr_pdd_get_batch_types();

			$return['success'] 			= 1;
			$return['version'] 			= get_option('ci_abx_last_version', 0);
			$return['table_versions'] 	= get_option('ci_abx_table_versions', array());

			$return['cversion'] 		= get_option('ci_abx_last_version', 0);
			$return['Quantitative'] 	= get_option('ci_abx_Quantitative', 0);
			$return['panels'] 			= array();

			$return['Quantitative5Levels'] = tr_get_option('ci_result_settings_pcr', 'enable_verylh');
			$return['biofilm'] 			= tr_get_option('ci_pdf_data', 'new_abx_biofilm');
			$return['abxversion'] 		= filemtime($abx_file);
			$return['dompdfversion'] 	= defined('TRCDPDF_V') ? TRCDPDF_V : '';
			$return['TRCPDD_VS'] 		= defined('TRCPDD_VS') ? TRCPDD_VS : '';
			$return['hasupdateabx']		= wp_next_scheduled('cron_update_abx') > 0;
			$return['servertype'] 		= $this->getservertype();

			$opt = get_option('ci_pdf_data', array());
			$return['abx_enable'] = $opt['new_abx'] ? 1 : 0;
			if (isset($opt['close_lab']) && $opt['close_lab']) {
				$return['closed'] = true;
			} else if (!in_array('pcr', $all_btypes)) {
				$return['abx_enable'] = false;
			} else {
				$main_groups = $wpdb->get_results("
				select tg_ID, tg_name,tg_short_name, tg_other_options
				from ci_test_groups
				where tg_parent = 0
				and tg_type = 'pcr'
				and tg_show_req = 1
				and tg_other_options like '%new_abx\":\"1%'
				and tg_antibiotic = 0 
				order by tg_position asc
				");

				foreach ($main_groups as $g) {
					$markers = array();
					$other_options = json_decode($g->tg_other_options, true);

					$abx_type = 'uti';
					if (isset($other_options['abx_type']) && !empty($other_options['abx_type'])) {
						$abx_type = $other_options['abx_type'];
					} else if (stripos($g->tg_name, 'wound') !== false) {
						$abx_type = 'wound';
					} else if (stripos($g->tg_name, 'gi') !== false) {
						$abx_type = 'gi';
					} else if (stripos($g->tg_name, 'Respiratory') !== false || stripos($g->tg_name, 'rpp') !== false) {
						$abx_type = 'r';
					}

					$panel 					= [];
					$panel['quantitative'] 	= $other_options['quantitative'];
					$panel['id'] 			= $g->tg_ID;
					$panel['name'] 			= $g->tg_name;
					$panel['short'] 		= $g->tg_short_name;
					$panel['type'] 			= $abx_type;


					$return['panels'][] = $panel;



					//get markers
					$tests = $wpdb->get_results("
					select te_name, 
					te_abrev, 
					te_ID,
					m.meta_value as mkr_tests
					from ci_test_panels te
					left join ci_test_meta m ON m.test_id = te.te_ID and m.meta_key='mkr_tests'
					JOIN ci_test_groups tg ON tg.tg_ID = te.te_group_id
					where tg_parent = {$g->tg_ID} and te_in_req = 1
					and (tg.tg_antibiotic = 1 or tg_name like '%Antibiotic Resistance%' )");

					foreach ($tests as $t) {
						$mkids = !empty($t->mkr_tests) ? explode(',', $t->mkr_tests) : array();

						if (count($mkids) == 0) {
							$testnames = array($t->te_name);
							if (!empty($t->te_abrev)) {
								$testnames[] = $t->te_abrev;
							}
							$testnames_str = implode("','", $testnames);
							$mkids = $wpdb->get_col("select ID from ci_abx_resistance_markers where resistance_marker in ('{$testnames_str}')");
						}
						$markers = array_merge($markers, $mkids);
					}
					$markers = array_map('intval', $markers);

					sort($markers);


					$return[$abx_type . '_markers'] = $markers;
				}
			}
		}
		$this->response(200, $return);
	}

	public function request_get_abx_tests()
	{
		global $wpdb;
		$return = array('success' => 0);
		if ($this->check_key()) {
			$gid = !empty($this->params['gid']) ? $this->params['gid'] : 0;
			$return['gid'] = $gid;
			if ($gid > 0) {
				$return['success'] = 1;
				$return['ct_amp_cq'] = tr_get_option('ci_result_settings_pcr', 'enable_rs_ct_amp_cq', false);
				$return['verylh'] = tr_get_option('ci_result_settings_pcr', 'enable_verylh', false);

				$return['group'] = $wpdb->get_row("select * from ci_test_groups where tg_ID = {$gid} ", ARRAY_A);
				$return['group']['other_options'] = json_decode($return['group']['tg_other_options'], true);
				$return['subs'] = $wpdb->get_results("select 
				tg_ID as id, 
				tg_name as name,
				tg_antibiotic as antibiotic
				from ci_test_groups where tg_parent = {$gid} order by tg_position asc", ARRAY_A);
				foreach ($return['subs'] as &$sub) {

					$sub['tests'] = $wpdb->get_results("select 
					te_ID as id, 
					te_name as name, 
					te_abrev as abrev
					from ci_test_panels te 
					where te_group_id = {$sub['id']} 
					and te_in_req = 1
					order by te_position asc ", ARRAY_A);

					foreach ($sub['tests'] as &$test) {

						$test['ranges'] = $wpdb->get_results("select 
						cu_critical_low as vlow,
						cu_cutoff as low,
						cu_medium as med,
						cu_upper_limit as high,
						cu_critical_high as vhigh,
						cu_hqc as hqc,
						cu_ms as ins
						 from ci_test_panels_cutoffs 
						 where cu_te_ID = {$test['id']} ");
						if ($sub['antibiotic']) {
							$test['mkrids'] = $wpdb->get_var("select meta_value from ci_test_meta where test_id = {$test['id']} and meta_key='mkr_tests' ");
							if (!empty($test['mkrids'])) {
								$test['mkrids']  = explode(',', $test['mkrids']);
							} else {
								$test['mkrids'] = array();
								$test['mkrids'][] = $wpdb->get_var("select ID from ci_abx_resistance_markers 
								where resistance_marker = '{$test['name']}' 
								or resistance_marker = '{$test['abrev']}' ");
							}
						} else {
							$test['bacids'] = $wpdb->get_var("select meta_value from ci_test_meta where test_id = {$test['id']} and meta_key='bac_tests' ");
							if (!empty($test['bacids'])) {
								$test['bacids']  = explode(',', $test['bacids']);
							} else {
								$test['bacids'] = array();
								$test['bacids'][] = $wpdb->get_var("select ID from ci_abx_bacterias 
								where bacteria_name = '{$test['name']}' 
								or bacteria_name = '{$test['abrev']}' ");
							}
						}
					}
				}
			}
		}
		$this->response(200, $return);
	}

	public function request_add_run_query()
	{
		global $wpdb;
		$return = array('success' => 0);
		if ($this->check_key()) {
			$query_id = !empty($this->params['query_id']) ? $this->params['query_id'] : 0;

			if ($query_id > 0) {
				$nextcron = wp_next_scheduled('cron_run_query', array($query_id));
				if ($nextcron == 0) {
					wp_schedule_single_event(time() + 30, 'cron_run_query', array($query_id));
				}
			}
		}
		$this->response(200, $return);
	}

	public function request_get_all_tests()
	{
		global $wpdb;
		$return = array('success' => 0, 'types' => array());
		if ($this->check_key()) {
			$return['success'] = 1;
			foreach (tr_pdd_get_batch_types() as $btype) {
				$return[$btype] = $wpdb->get_results("select t.*, g.* from ci_test_panels t
				JOIN ci_test_groups g ON g.tg_ID = t.te_group_id
				where g.tg_type = '{$btype}' ", ARRAY_A);
				$return['types'][] = $btype;
			}
		}
		$this->response(200, $return);
	}

	public function request_get_lab_info()
	{
		global $wpdb, $blog_id;
		$return = array('success' => 0);
		if ($this->check_key()) {
			$return['success'] = 1;

			$lab = $wpdb->get_row("select * from ci_labs where lab_site = {$blog_id}");
			$domain = '';
			if (defined('DOMAIN_CURRENT_SITE') && !empty(DOMAIN_CURRENT_SITE)) {
				$domain = DOMAIN_CURRENT_SITE;
			}
			if (empty($domain)) {
				$domain = str_replace(array('https://', 'http://', '/'), '', get_option('url'));
			}

			$closed = false;
			$opt = get_option('ci_pdf_data', array());
			if (isset($opt['close_lab']) && $opt['close_lab']) {
				$closed = true;
			}
			$return['domain'] 	= $domain;
			$return['name'] 	= $lab->lab_name;
			$return['init'] 	= $lab->lab_dba_init;
			$return['ip'] 		= $this->getIP();
			$return['address'] 	= $lab->lab_address;
			$return['city'] 	= $lab->lab_city;
			$return['state'] 	= $lab->lab_state;
			$return['zip'] 		= $lab->lab_zip;
			$return['clia'] 	= $lab->lab_clia;
			$return['closed'] 	= $closed ? 1 : 0;
			$return['servertype'] = $this->getservertype();
		}
		$this->response(200, $return);
	}

	public function request_get_state_tests()
	{
		global $wpdb, $blog_id;
		$return = array('success' => 0, 'tests' => array(), 'specimens' => array());
		if ($this->check_key()) {
			$return['success'] = 1;
			$options = get_option('ci_send_hub_tests', array());

			include_once(TRCPDD_PATH . 'api/ci_api_loinc.php');
			$loincapi = new CI_Api_LOINC();

			foreach ($options as $tk => $arr) {
				if ($arr['tid'] > 0) {
					$test = $wpdb->get_row("select * from ci_test_panels where te_ID = {$arr['tid']} ");
					$arr['test_name'] = $test->te_name;
					$arr['loinc_code'] = $test->te_loinc_code;
					$arr['snomed_code'] = $test->te_snomed_code;
				}

				if (!empty($arr['loinc_code'])) {
					$arr['loinc_short'] = trpdd_get_test_meta($arr['tid'], '_loinc_short', '');

					if (empty($arr['loinc_short'])) {
						$arr['loinc_short'] = $loincapi->getShortName($arr['loinc_code']);
						if (!empty($arr['loinc_short'])) {
							$rs1 = trpdd_update_test_meta($arr['tid'], '_loinc_short', $arr['loinc_short']);
						}
					}
				}

				if (isset($arr['cond_only_tids']) && count($arr['cond_only_tids']) > 0) {
					$arr['cond_only_test_names'] = array();
					$arr['cond_only_loinc_codes'] = array();
					foreach ($arr['cond_only_tids'] as $onltid) {
						$test2 = $wpdb->get_row("select te_name, te_loinc_code from ci_test_panels
							where te_ID = {$onltid} ");
						$arr['cond_only_test_names'][$onltid] = $test2->te_name;
						$arr['cond_only_loinc_codes'][$onltid] = $test2->te_loinc_code;
					}
				} else if ($arr['cond_only_tid'] > 0) {
					$test2 = $wpdb->get_row("select te_name, te_loinc_code from ci_test_panels
					where te_ID = {$arr['cond_only_tid']} ");
					$arr['cond_only_test_name'] = $test2->te_name;
					$arr['cond_only_loinc_code'] = $test2->te_loinc_code;
				}

				$return['tests'][$tk] = $arr;
			}

			$specs = $wpdb->get_results("select ts.ts_name, ts.ts_code , g.tg_name
			from ci_test_specimens ts
			left JOIN ci_test_groups g ON ts.ts_group_id = g.tg_ID
			where ts.ts_code !='' ");
			foreach ($specs as $spec) {
				if (!empty($spec->ts_code)) {
					$return['specimens'][$spec->ts_name] = $spec->ts_code;
					$return['group_specs'][$spec->tg_name] = $spec->ts_name;
				}
			}
		}
		$this->response(200, $return);
	}


	public function request_sync_postdata()
	{
		$return = array('success' => 0);
		if ($this->check_key()) {
			$return['success'] = 1;
			try {
				$post_type 	= $this->params['post_type'];
				$posts 		= isset($this->params['posts']) ? $this->params['posts'] : array();
				if (isset($this->params['cron'])) {
					$next = wp_next_scheduled('cron_client_sync_whatnew');
					if (empty($next)) {
						wp_schedule_single_event(time() + 30, 'cron_client_sync_whatnew');
					}
				} else if (!empty($posts)) {
					$rs = $this->_update_postdata($post_type, $this->params['posts'], $this->params['is_single']);
					$return['ids'] = $rs;
				}
			} catch (Exception $e) {
				$return['success'] = 0;
				$return['error'] = $e->getMessage();
			}
		}
		$this->response(200, $return);
	}

	public function request_sync_table()
	{
		$return = array('success' => 0);
		if ($this->check_key()) {
			$return['success'] = 1;
			$table = $this->params['table'];
			try {
				if (!empty($table)) {
					$this->sync_hub_table($table);
				}
			} catch (Exception $e) {
				$return['success'] = 0;
				$return['error'] = $e->getMessage();
			}
		}
		$this->response(200, $return);
	}

	public function request_sync_item_message()
	{
		global $wpdb;

		$return = array('success' => 0);
		if ($this->check_key()) {
			$return['success'] = 1;
			try {
				$action = isset($this->params['action']) ? $this->params['action'] : null;
				$message = isset($this->params['data']) ? $this->params['data'] : null;

				$hub_id = isset($message['ID']) ? absint($message['ID']) : 0;
				if ($hub_id > 0) {
					$local_id = 0 - $hub_id;

					if ($action === 'delete') {
						$wpdb->delete('ci_messages', array('ID' => $local_id));
					} else {

						$data = array(
							'subject' => isset($message['subject']) ? $message['subject'] : '',
							'msg' => isset($message['msg']) ? $message['msg'] : '',
							'author' => 0,
							'created_date' => !empty($message['created_date']) ? $message['created_date'] : current_time('mysql'),
							'from_date' => (!empty($message['from_date']) && $message['from_date'] !== '0000-00-00') ? $message['from_date'] : null,
							'to_date' => (!empty($message['to_date']) && $message['to_date'] !== '0000-00-00') ? $message['to_date'] : null,
							'background' => isset($message['background']) ? $message['background'] : '',
							'msg_type' => (isset($message['msg_type']) && $message['msg_type'] === 'a') ? 'a' : 'n',
							'read_roles' => isset($message['read_roles']) ? $message['read_roles'] : '',
						);

						$exists = (int) $wpdb->get_var($wpdb->prepare("SELECT ID FROM ci_messages WHERE ID = %d", $local_id));
						if ($exists !== 0) {
							$wpdb->update('ci_messages', $data, array('ID' => $local_id));
						} else {
							$data['ID'] = $local_id;
							$wpdb->insert('ci_messages', $data);
						}
					}

					update_option('trpdd_notification_cache_last_changed_at', time(), false);
				}
			} catch (Exception $e) {
				$return['success'] = 0;
				$return['error'] = $e->getMessage();
			}
		}
		$this->response(200, $return);
	}

	public function request_add_cron()
	{
		$return = array('success' => 0);
		if ($this->check_key()) {

			$hook = $this->params['hook'];
			$args = isset($this->params['args']) ? $this->params['args'] : array();
			if (!empty($hook)) {
				$return['success'] = 1;
				$next = wp_next_scheduled($hook, $args);
				if (empty($next)) {
					wp_schedule_single_event(time() + 30, $hook, $args);
					$return['message'] = "Scheduled cron for hook: {$hook}";
				}
			}
		}
		$this->response(200, $return);
	}

	public function request_clean()
	{
		$return = array('success' => 0);
		if ($this->check_key()) {
			$return['success'] = 1;
			ob_start();
			$log = '';
			include_once(TRLCN_PATH . 'inc/crons/clean.php');
			$return['message'] = $log . ob_get_clean();
		}
		$this->response(200, $return);
	}


	private function ensure_color_column(string $table, string $afterColumn = 'ID')
	{
		global $wpdb;
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = %s
              AND COLUMN_NAME = 'color'
            ",
				$table
			)
		);

		if ((int)$exists === 0) {
			$wpdb->query(
				"ALTER TABLE {$table}
             ADD COLUMN color VARCHAR(20) NULL AFTER {$afterColumn}"
			);
		}
	}


	public function request_update_tests_color()
	{
		$return = array('success' => 0);
		if ($this->check_key()) {
			$return['success'] = 1;
			$bacterias = $this->params['bacterias'];
			$markers = $this->params['markers'];
			$is_override = $this->params['is_override'] ?? false;

			$return['is_override'] = $is_override;
			try {
				global $wpdb;
				$this->ensure_color_column('ci_abx_bacterias', 'ID');
				$this->ensure_color_column('ci_abx_resistance_markers', 'ID');
				foreach ($bacterias as $bid => $color) {
					if (empty($color)) {
						continue;
					}
					$wpdb->update(
						'ci_abx_bacterias',
						array('color' => $color),
						array('ID' => intval($bid)),
						array('%s'),
						array('%d')
					);
				}
				foreach ($markers as $mid => $color) {
					if (empty($color)) {
						continue;
					}
					$wpdb->update(
						'ci_abx_resistance_markers',
						array('color' => $color),
						array('ID' => intval($mid)),
						array('%s'),
						array('%d')
					);
				}

				if (!function_exists('trpdd_sync_abx_test_colors')) {
					include_once(TRCPDD_PATH . 'inc/functions_fix.php');
				}
				if (function_exists('trpdd_sync_abx_test_colors')) {

					$return['trpdd_sync_abx_test_colors'] = 'function exists';
					$return['updated_tests'] = trpdd_sync_abx_test_colors($is_override);
				} else {
					$return['trpdd_sync_abx_test_colors'] = 'function not exists';
				}
			} catch (Exception $e) {
				$return['success'] = 0;
				$return['error'] = $e->getMessage();
			}
		}
		$this->response(200, $return);
	}

	public function request_list_ins_apis()
	{
		$return = array(
			'success' => 0,
			'instruments' => array(),
			'apis' => array(),
		);

		if ($this->check_key()) {
			$return['success'] = 1;

			$options = get_option('ci_pdf_data', array());
			$trcpdd_path = defined('TRCPDD_PATH') ? TRCPDD_PATH : '';

			if (!empty($trcpdd_path)) {
				$define_instruments_file = $trcpdd_path . 'inc/define_instruments.php';
				if (file_exists($define_instruments_file)) {
					$define_instruments = array();
					include $define_instruments_file;

					if (!empty($define_instruments) && is_array($define_instruments)) {
						foreach ($define_instruments as $key => $arr) {
							if (empty($options[$key])) {
								continue;
							}

							$return['instruments'][$key] = $arr;
						}
					}
				}

				$define_apis_file = $trcpdd_path . 'inc/define_apis.php';
				if (file_exists($define_apis_file)) {
					$define_apis = array();
					include $define_apis_file;

					if (!empty($define_apis) && is_array($define_apis)) {
						foreach ($define_apis as $key => $arr) {
							if (empty($options[$key])) {
								continue;
							}

							$return['apis'][$key] = $arr;
						}
					}
				}
			}
		}

		$this->response(200, $return);
	}

	public function count_users_by_role($role_key)
	{
		$users = get_users(array(
			'role'    => $role_key,
			'fields'  => 'ID',
		));
		return count($users);
	}

	public function request_count_users_by_roles()
	{
		$return = array('success' => 0, 'counts' => array());
		if ($this->check_key()) {
			$return['success'] = 1;

			$roles = wp_roles()->roles;
			foreach ($roles as $role_key => $role_info) {
				$count = $this->count_users_by_role($role_key);
				$return['counts'][$role_key] = [
					'name' => $role_info['name'],
					'count' => $count
				];
			}
		}
		$this->response(200, $return);
	}
	public function request_delete_role()
	{
		$return = array('success' => 0, 'message' => '');
		if ($this->check_key()) {
			$role = isset($this->params['role']) ? sanitize_text_field($this->params['role']) : '';
			if (empty($role)) {
				$return['message'] = 'Role is required';
				$this->response(200, $return);
				return;
			}
			if (!get_role($role)) {
				$return['message'] = 'Role does not exist';
				$this->response(200, $return);
				return;
			}
			$users = get_users(array(
				'role'    => $role,
				'fields'  => 'ID',
				'number'  => 1
			));
			if (!empty($users)) {
				$return['message'] = 'Role has users, cannot delete';
				$this->response(200, $return);
				return;
			}
			remove_role($role);
			$return['success'] = 1;
			$return['message'] = 'Role deleted successfully';
		}
		$this->response(200, $return);
	}
	public function request_count_patients()
	{
		global $wpdb;

		$return = array('success' => 0, 'patients' => 0);

		if ($this->check_key()) {
			$return['success'] = 1;

			$union_queries = array();

			foreach (tr_pdd_get_batch_types() as $btype) {
				$batch_tbl = tr_pdd_get_table_by_btype($btype);
				$union_queries[] = "SELECT `patient_id` FROM `{$batch_tbl}` WHERE `patient_id` > 0";
			}

			if (!empty($union_queries)) {
				$sql = "SELECT COUNT(*) AS total FROM (
                        SELECT DISTINCT up.patient_id
                        FROM (" . implode(" UNION ", $union_queries) . ") AS up
                        INNER JOIN `ci_patients` p ON p.pa_ID = up.patient_id
                    ) AS existing_patients";

				$row = $wpdb->get_row($sql);

				$return['patients'] = $row ? (int) $row->total : 0;
			}
		}

		$this->response(200, $return);
	}
}
