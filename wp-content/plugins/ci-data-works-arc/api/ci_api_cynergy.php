<?php
class CI_API_Cynergy
{
	public $log_file_slug 	= 'cynergy';
	public $api_id 			= -58;
	public $debug 			= false;
	public $options 		= array();
	public $all_btypes 		= array('req');
	public $log				= '';

	private $baseUrl;
	private $tokenUrl;
	private $token;

	public function __construct()
	{
		$this->options = get_option('tr_cynergy_st', array());

		$this->baseUrl = 'https://api-uat.cynergywellness.com';
		$this->tokenUrl = $this->baseUrl . '/token';
	}

	public function settingFields()
	{
		return array(
			'username' => array('type' => 'text', 'label' => 'Username'),
			'password' => array('type' => 'text', 'label' => 'Password'),
			'requestingEntity' => array('type' => 'text', 'label' => 'requestingEntity'),
			'accountNumber' => array('type' => 'text', 'label' => 'Acount Number'),
			'labId' => array('type' => 'text', 'label' => 'LabID'),
			'start_date' => array('type' => 'date', 'label' => 'Start Date'),
			'cron' => array('type' => 'select', 'options' => array(30 => 'each 30mins', 10 => 'each 10mins', 5 => 'each 5mins', 2 => 'each 2mins'), 'label' => 'Schedule'),
		);
	}
	public function showSettingsFields($options)
	{
		foreach ($this->settingFields() as $fk => $arr) {
?>
			<div class="row">
				<label><?php echo $arr['label'] ?></label>
				<?php
				if ($arr['type'] == 'select') {
					echo '<select name="options[' . $fk . ']">';
					foreach ($arr['options'] as $mk => $mvl) {
						$selected = isset($options['cron']) && $mk == $options['cron'] ? 'selected' : '';
						echo '<option value="' . $mk . '" ' . $selected . '>' . $mvl . '</option>';
					}
					echo '</select>';
				} else if ($arr['type'] == 'checkbox') { ?>
					<input type="checkbox" name="options[<?php echo $fk ?>]" value="1" <?php checked(isset($options[$fk]) ? $options[$fk] : 0) ?> />
				<?php } else { ?>
					<input type="<?php echo $arr['type'] ?>" name="options[<?php echo $fk ?>]" value="<?php echo $options[$fk] ?>" />
				<?php } ?>
			</div>
<?php
		}
	}

	public function cron()
	{
		$this->send_orders();
	}

	public function callback() {}

	public function putlog($msg)
	{
		if (is_array($msg)) {
			$msg = json_encode($msg);
		}
		$filepath = WP_CONTENT_DIR . '/log/ci_api_cynergy.txt';
		$f = fopen($filepath, 'a+');
		if ($f) {
			fputs($f, '--------' . date_i18n('Y-m-d H:i:s') . '------' . "\n");
			fputs($f, $msg . "\n\n");
			fclose($f);
		}
	}

	private function request($url, $data = null, $method = 'POST', $auth = true)
	{
		$ch = curl_init($url);
		if ($method === 'POST') {
			curl_setopt($ch, CURLOPT_POST, true);
		}
		if ($data !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}
		$headers = ['Content-Type: application/json'];
		if ($auth && $this->token) {
			$headers[] = "Authorization: Bearer {$this->token}";
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		if ($response === false) {
			return ['error' => curl_error($ch)];
		}
		curl_close($ch);

		var_dump($headers);
		var_dump($response);

		return json_decode($response, true);
	}

	private function getToken()
	{
		$response = $this->request($this->tokenUrl, [
			'username' => $this->options['username'],
			'password' => $this->options['password']
		], 'POST', false);

		return $response['token'] ?? null;
	}

	public function searchClinics($params)
	{
		$params['requestingEntity'] = $this->options['requestingEntity'];
		$url = $this->baseUrl . '/toxicology-order/search-clinics';
		//$url = 'https://api.webhue.net/raw.php';
		return $this->request($url, $params);
	}

	public function createOrder($orderData)
	{
		$url = $this->baseUrl . '/order/create-order';
		//$url = 'https://api.webhue.net/raw.php';
		return $this->request($url, $orderData);
	}

	public function put_req($data)
	{
		global $wpdb;

		if (!is_object($data)) {
			$data = (object) $data;
		}
		$gender = $data->pa_gender == 'F' ? 'Female' : 'Male';

		$services = array();

		foreach (explode(',', $data->panel_tests_requested) as $tid) {
			if (stripos($tid, 'GROUP_') !== false) {
				$group_id = str_ireplace('GROUP_', '', $tid);

				$sql_tests = "select te.te_cynergy_code, 
				te.te_name,
				g.tg_name
				from ci_test_panels te
				JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
				where te_group_id = {$group_id} ";
				$tests = $wpdb->get_results($sql_tests);


				$expirationDate = date('Y-m-d', time() + 86400 * 300);
				foreach ($tests as $test) {
					$services[] = [
						"serviceCode" => $test->te_cynergy_code,
						"labId" => $this->options['labId'],
						"accountNumber" => $this->options['accountNumber'],
						"regulation" => "DOT",
						"mode" => "FMCSA",
						"category" => $test->tg_name, //"Driver"
						"reasonForTest" => "Pre-Employment",
						"requestObservation" => "Y",
						"requestSplitSample" => "Y",
						"expirationDate" => $expirationDate
					];
				}
			}
		}
		$orderData = [
			"extOrderId" => $data->assen,
			"requestingEntity" => $this->options['requestingEntity'],
			"costCode" => "COST987",
			"servicesRequested" => $services,
			"collSite" => [
				"siteType" 		=> "PSC",
				"psc"		 	=> "0",
				"clinicId" 		=> 10293,
				"clinicCode" 	=> null,
				"collComments" 	=> ""
			],
			"patient" => [
				"donorIdentifiers" => [
					["identifierType" => "SSN", "identifierValue" => $data->pa_ssn]
				],
				"firstName" => $data->pa_first,
				"lastName" 	=> $data->pa_last,
				"dob" 		=> $data->pa_dob,
				"gender" 	=> $gender,
				"address" 	=> [
					"address1" 	=> $data->pa_address,
					"city" 		=> $data->pa_city,
					"state" 	=> $data->pa_state,
					"zip"		 => $data->pa_zip
				]
			]
		];
		echo '<pre>';
		var_dump($orderData);
		echo '</pre>';

		$this->putlog($orderData);
		$orderResult = $this->createOrder($orderData);

		return $orderResult;
	}

	public function send_orders()
	{
		global $wpdb;

		$this->token = $this->getToken();

		if (empty($this->token)) {
			echo 'Cant get token!';
			return;
		} else {
			//	echo $this->token;
		}

		$limit = 10;


		foreach ($this->all_btypes as $btype) {

			$batch_tbl = tr_pdd_get_table_by_btype($btype);


			$where = " and (api.mo_ID is null or api.mo_sent = 0) ";
			$where .= " and ba.paid = -57 ";
			if (!empty($this->options['start_date'])) {
				$where .= " and ba.doc >= '{$this->options['start_date']}' ";
			}

			$where .= " and ba.batch_id > 0 and ba.patient_id > 0 ";

			$sql = "select ba.*,
				cli.*,
				doc.*,
				pa.*,
				ins.*,
				api.mo_ID,
				api.other_info,
				'{$btype}' as btype
                    
            from {$batch_tbl} ba
            JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
            JOIn ci_patients pa ON pa.pa_ID = ba.patient_id
            JOIn ci_doc doc ON doc.doc_ID = ba.doc_ID
            JOIn ci_clinic cli ON cli.cID = ba.cli_ID
            left JOIN ci_insurances ins ON ins.ins_ID = ba.insurance1
            left join ci_sync_api_orders api ON api.mo_mid = ba.mid
                                        and api.mo_file_id = '{$this->api_id}'
                                        and api.mo_btype = '{$btype}'
             where 1
             {$where}
             order by mid asc
			 limit {$limit}";



			$rows = $wpdb->get_results($sql);
			echo $wpdb->last_error;
			//echo $wpdb->last_query;

			foreach ($rows as $r) {
				$resp = $this->put_req($r);
				$this->putlog($resp);
				if (is_array($resp) && isset($resp['status'])) {
					$update = array(
						'mo_file_id'    => $this->api_id,
						'mo_mid'        => $r->mid,
						'mo_btype'		=> $btype,
						'mo_sent' => 1,
						'mo_sent_dt' => date_i18n('Y-m-d H:i:s')
					);


					if ($r->mo_ID > 0) {
						$rr = $wpdb->update('ci_sync_api_orders', $update, array('mo_ID' => $r->mo_ID));
					} else {
						$rr = $wpdb->insert('ci_sync_api_orders', $update);
					}

					//add meta
					trpdd_update_batch_meta($r->mid, $btype, '_cynergy_status', 1);
				}
			}
		}
	}
}
