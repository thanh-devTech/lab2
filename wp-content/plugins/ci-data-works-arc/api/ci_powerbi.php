<?php
require_once 'restful_api.php';

add_filter('https_ssl_verify', '__return_false');

class CI_PowerBI_Api extends restful_api
{

	public $key_data = null;

	function __construct($pre_endpoint)
	{
		parent::__construct($pre_endpoint);
	}

	protected function check_key()
	{
		global $wpdb;

		if ($this->endpoint == 'request_info' && $this->request_api_key == 'viewinfo') {
			return true;
		}

		$this->key_data = $wpdb->get_row("select * from ci_wsdl_keys
			where wsdl_key = '{$this->request_api_key}'
			and wsdl_type='powerbi' ");

		if (! $this->key_data) {
			$this->response(405, array('status' => 'error', 'message' => 'wrong key'));
			exit;
		}

		return true;
	}

	protected function get_ip()
	{
		if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
			$ip = trim($ips[0]);
			if (!empty($ip)) {
				return $ip;
			}
		}

		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			return $_SERVER['HTTP_CLIENT_IP'];
		}

		return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
	}

	protected function log($type, $key_id, $assen, $status, $data)
	{
		global $wpdb;

		if (is_array($data) || is_object($data)) {
			$data = json_encode($data);
		}

		return $wpdb->insert(
			'ci_wsdl_log',
			array(
				'wl_ID' => $key_id,
				'wl_type' => $type,
				'wl_key' => $assen,
				'wl_count' => 1,
				'wl_status' => $status,
				'wl_data' => substr((string) $data, 0, 1000),
				'wl_at' => date_i18n('Y-m-d H:i:s'),
				'wl_ip' => $this->get_ip(),
			)
		);
	}

	protected function get_requested_panels()
	{
		global $wpdb;

		$tests = array(
			'groups' => array(),
			'tests' => array(),
		);

		$groups = $wpdb->get_results("select tg_ID, tg_name from ci_test_groups where tg_type = 'pcr'");
		foreach ($groups as $group) {
			$tests['groups']['GROUP_' . $group->tg_ID] = $group->tg_name;
		}

		$panels = $wpdb->get_results("select te_ID, te_name from ci_test_panels where te_type = 'pcr'");
		foreach ($panels as $panel) {
			$tests['tests'][$panel->te_ID] = $panel->te_name;
		}

		return $tests;
	}

	protected function format_test_panel($panel_tests_requested, $all_tests)
	{
		$groups = array();
		$tests = array();

		foreach (explode(',', (string) $panel_tests_requested) as $test_id) {
			$test_id = trim($test_id);
			if (empty($test_id)) {
				continue;
			}

			if (isset($all_tests['groups'][$test_id])) {
				$groups[] = $all_tests['groups'][$test_id];
			} else if (isset($all_tests['tests'][$test_id])) {
				$tests[] = $all_tests['tests'][$test_id];
			}
		}

		$groups = array_values(array_unique($groups));
		$tests = array_values(array_unique($tests));

		if (!empty($groups)) {
			return implode(', ', $groups);
		}

		return implode(', ', $tests);
	}

	protected function normalise_name($first_name, $last_name)
	{
		$name = trim($last_name . ', ' . $first_name, ', ');
		return preg_replace('/\s+/', ' ', $name);
	}

	protected function get_test_status($batch_status, $report_status = '')
	{
		$batch_status = trim((string) $batch_status);
		if (!empty($batch_status)) {
			$status = tr_pdd_get_batch_statuses($batch_status, false, true, 'pcr');
			if (!empty($status)) {
				return $status;
			}

			return ucwords(str_replace(array('_', '-'), ' ', $batch_status));
		}

		if ($report_status === 'C') {
			return 'Completed';
		}

		return '';
	}

	protected function get_insurance_group($row)
	{
		if ($row->payer_medicare === '1') {
			return 'Medicare';
		}
		if ($row->payer_medicaid === '1') {
			return 'Medicaid';
		}
		if ($row->payer_workmen === '1') {
			return "Worker's Comp";
		}
		if ($row->payer_other === '1') {
			return 'Other';
		}
		if ($row->payer_commercial === '1') {
			return 'Commercial';
		}

		$insurance = strtolower(trim($row->insurance_name));
		if (empty($insurance)) {
			return '';
		}

		if (
			strpos($insurance, 'self pay') !== false
			|| strpos($insurance, 'selfpay') !== false
			|| strpos($insurance, 'cash') !== false
			|| strpos($insurance, 'client bill') !== false
			|| strpos($insurance, 'no insurance') !== false
		) {
			return 'Self Pay';
		}

		if (strpos($insurance, 'medicare') !== false) {
			return 'Medicare';
		}

		if (strpos($insurance, 'medicaid') !== false || strpos($insurance, 'medi-cal') !== false) {
			return 'Medicaid';
		}

		if (strpos($insurance, 'work') !== false || strpos($insurance, 'comp') !== false) {
			return "Worker's Comp";
		}

		return 'Commercial';
	}

	protected function get_datetime_param($key, $end_of_day = false)
	{
		if (empty($this->params[$key])) {
			return '';
		}

		$datetime = trim($this->params[$key]);
		$time = strtotime($datetime);
		if (! $time) {
			return '';
		}

		if (strpos($datetime, ':') === false) {
			return date('Y-m-d', $time) . ($end_of_day ? ' 23:59:59' : ' 00:00:00');
		}

		return date('Y-m-d H:i:s', $time);
	}

	/**
	 * GET completed/approved PCR results for PowerBI
	 *
	 * - Requests:
	 *      + limit: default 100, max 1000
	 *      + page: default 1
	 *      + datetime: filter CompletedDateTime from this datetime
	 *      + datetime_to: filter CompletedDateTime to this datetime
	 *
	 * - Response:
	 * {
	 *   "status": "ok",
	 *   "number": 1,
	 *   "total": 1,
	 *   "page": 1,
	 *   "total_page": 1,
	 *   "results": [{
	 *     "Accession": "MPA01235928",
	 *     "PatientName": "Last, First",
	 *     "ServiceDateTime": "2026-03-12 09:00:00",
	 *     "ReceivedDateTime": "2026-03-12 10:00:00",
	 *     "CompletedDateTime": "2026-03-12 12:00:00",
	 *     "AccessionType": "PCR",
	 *     "TestStatus": "Completed",
	 *     "Clinic": "Clinic Name",
	 *     "OrderingPhysician": "Doe, John",
	 *     "CollectorName": "Collector Name",
	 *     "Insurance": "Insurance Name",
	 *     "InsuranceGroup": "Commercial",
	 *     "TestPanel": "Respiratory Panel",
	 *     "SalesRep": "Rep Name",
	 *     "Lab": "ADG"
	 *   }]
	 * }
	 */
	public function request_get_result()
	{
		global $wpdb;

		$return = array(
			'status' => 'ok',
			'number' => 0,
			'total' => 0,
			'page' => 1,
			'total_page' => 0,
			'results' => array(),
		);

		$limit = isset($this->params['limit']) && intval($this->params['limit']) > 0 ? intval($this->params['limit']) : 100;
		$page = isset($this->params['page']) && intval($this->params['page']) > 0 ? intval($this->params['page']) : 1;
		if ($limit > 1000) {
			$limit = 1000;
		}

		$return['page'] = $page;

		$offset = ($page - 1) * $limit;
		$start_date = $this->key_data->wsdl_start;
		$datetime = $this->get_datetime_param('datetime');
		$datetime_to = $this->get_datetime_param('datetime_to', true);
		$cli_IDs = $wpdb->get_col("select cli_ID from ci_wsdl_key_clinics where wsdl_ID = {$this->key_data->wsdl_ID}");
		$all_tests = $this->get_requested_panels();

		$where = " where re.estatus = 'C' and re.approve_date is not null and re.approve_date != '0000-00-00 00:00:00' ";

		if (!empty($datetime)) {
			$where .= " and re.approve_date >= '{$datetime}' ";
		} else if (!empty($start_date)) {
			$where .= " and re.approve_date >= '{$start_date}' ";
		}

		if (!empty($datetime_to)) {
			$where .= " and re.approve_date <= '{$datetime_to}' ";
		}

		if (count($cli_IDs) == 1 && intval($cli_IDs[0]) === -1) {
			$cli_IDs = array();
		}

		if (count($cli_IDs) > 0) {
			$where .= ' and ba.cli_ID in (' . implode(',', array_map('intval', $cli_IDs)) . ') ';
		}

		if ($this->key_data->wsdl_ref_lab_ID > 0) {
			$where .= " and ba.ref_lab_ID = {$this->key_data->wsdl_ref_lab_ID} ";
		} else if ($this->key_data->wsdl_ref_lab_ID == -1) {
			$where .= " and ba.ref_lab_ID > 0 ";
		}

		$sql_total = "select count(*)
			from ci_batches_pcr ba
			join ci_reports_pcr re on re.patient_id = ba.assen
			left join ci_clinic cli on cli.cID = ba.cli_ID
			{$where}";

		$return['total'] = intval($wpdb->get_var($sql_total));
		$return['total_page'] = $limit > 0 ? intval(ceil($return['total'] / $limit)) : 1;

		$sql = "select
				ba.mid,
				ba.assen,
				ba.patient_name,
				ba.doc,
				ba.spec_received,
				ba.requesting_practice,
				ba.requesting_physician,
				ba.batch_status,
				ba.panel_tests_requested,
				ba.insurance,
				ba.subscriber_insurance,
				re.estatus,
				re.approve_date,
				cli.cli_clinic,
				lab.lab_dba,
				lab.lab_dba_init,
				pa.pa_first,
				pa.pa_last,
				col.col_first,
				col.col_last,
				lr.rep_first,
				lr.rep_last,
				pl.payer_commercial,
				pl.payer_medicaid,
				pl.payer_medicare,
				pl.payer_workmen,
				pl.payer_other,
				trim(case
					when ba.insurance != '' then ba.insurance
					when ba.subscriber_insurance != '' then ba.subscriber_insurance
					else ''
				end) as insurance_name
			from ci_batches_pcr ba
			join ci_reports_pcr re on re.patient_id = ba.assen
			left join ci_clinic cli on cli.cID = ba.cli_ID
			left join ci_labs lab on lab.lab_ID = ba.lab_id
			left join ci_patients pa on pa.pa_ID = ba.patient_id
			left join ci_collector col on col.col_ID = ba.col_ID
			left join ci_lab_reps lr on lr.rep_ID = cli.`cli_rep-ID`
			left join ci_payer_list pl on pl.payer_name = (case
				when ba.insurance != '' then ba.insurance
				when ba.subscriber_insurance != '' then ba.subscriber_insurance
				else ''
			end)
			{$where}
			order by re.approve_date desc, ba.mid desc
			limit {$offset}, {$limit}";

		$rows = $wpdb->get_results($sql);

		foreach ($rows as $row) {
			$patient_name = $this->normalise_name($row->pa_first, $row->pa_last);
			if (empty($patient_name)) {
				$patient_name = trim($row->patient_name);
			}

			$collector_name = trim($row->col_first . ' ' . $row->col_last);
			$sales_rep = trim($row->rep_first . ' ' . $row->rep_last);
			$clinic_name = !empty($row->cli_clinic) ? $row->cli_clinic : $row->requesting_practice;
			$lab_name = !empty($row->lab_dba_init) ? $row->lab_dba_init : $row->lab_dba;

			$return['results'][] = array(
				'Accession' => $row->assen,
				'PatientName' => $patient_name,
				'ServiceDateTime' => $row->doc,
				'ReceivedDateTime' => $row->spec_received,
				'CompletedDateTime' => $row->approve_date,
				'AccessionType' => 'PCR',
				'TestStatus' => $this->get_test_status($row->batch_status, $row->estatus),
				'Clinic' => $clinic_name,
				'OrderingPhysician' => trim($row->requesting_physician),
				'CollectorName' => $collector_name,
				'Insurance' => $row->insurance_name,
				'InsuranceGroup' => $this->get_insurance_group($row),
				'TestPanel' => $this->format_test_panel($row->panel_tests_requested, $all_tests),
				'SalesRep' => $sales_rep,
				'Lab' => $lab_name,
			);
		}

		$return['number'] = count($return['results']);

		$log_data = array(
			'request' => $this->params,
			'return' => array(
				'status' => $return['status'],
				'number' => $return['number'],
				'total' => $return['total'],
				'page' => $return['page'],
				'total_page' => $return['total_page'],
			),
		);

		$this->log('get_result', $this->key_data->wsdl_ID, date_i18n('Y-m-d H:i:s'), $return['status'], $log_data);
		$this->response(200, $return);
	}
}
