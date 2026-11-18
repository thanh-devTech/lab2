<?php
class CI_Api_Genetica_Json
{

	public $log = '';
	public $api_id = -64;
	public $log_file_slug = 'genetica_json';
	public $options = array();
	public $all_btypes = array('gene');

	private $baseUrl = 'https://www.geneticaconsulting.com/api/import';
	private $orgid;
	private $apikey;

	public $log_type = 'json';
	public $log_dir = 'genetica_json';


	public function __construct()
	{
		$this->options = get_option('tr_api_genetica_json_st', array());

		$this->orgid = $this->options['orgid'] ?? '';
		$this->apikey = $this->options['apikey'] ?? '';
	}


	public function cron()
	{
		//cron job code here
		$this->send_orders(10);
	}

	public function sync_reqs()
	{
		return $this->send_orders(1);
	}

	public function clean_files()
	{
		global $wpdb;
		$limittime = time() - 86400 * 7;
		$limitdate = date('Y-m-d', $limittime);
		$rs = $wpdb->get_results("select rf_file from ci_reports_files where rf_btype = 'gene'
		and rf_covid_type = 'xy' and rf_upload_date <='{$limitdate}' ");
		foreach ($rs as $r) {
			$filepath = WP_CONTENT_DIR . '/log/results_gene/' . $r->rf_file;
			if (file_exists($filepath) && filemtime($filepath) < $limittime) {
				unlink($filepath);
			}
		}
	}

	public function get_files($assen, $exclude_ids = array())
	{
		global $wpdb;

		$files = array();
		$file_types = array('cnv' => 'cnv',  'Genotyping' => 'snp', 'genotype' => 'snp');

		$exclude_ids_str = !empty($exclude_ids) ? implode(',', $exclude_ids) : '0';

		foreach ($file_types as $stext => $ft) {
			$filerow = $wpdb->get_row("select rf_ID, rf_file from ci_reports_files where rf_btype='gene' 
			and find_in_set('{$assen}', rf_assen_list) and rf_file like '%{$stext}%' 
			and rf_ID not in ({$exclude_ids_str})
			order by rf_created asc limit 1", ARRAY_A);

			if ($filerow && !isset($files[$ft])) {
				$filerow['path'] = WP_CONTENT_DIR . '/log/results_gene/' . $filerow['rf_file'];
				$files[$ft] = array(
					'id' => $filerow['rf_ID'],
					'path' => $filerow['path'],
				);
			}
		}



		echo $assen;
		var_dump($files);
		echo '<br>';

		if (count($files) < 2) {
			return false;
		}
		return $files;
	}

	public function send_orders($limit = 10)
	{
		global $wpdb;

		if (empty($this->orgid) || empty($this->apikey)) {
			echo 'Cant get token!';
			return;
		}



		foreach ($this->all_btypes as $btype) {

			$batch_tbl = tr_pdd_get_table_by_btype($btype);
			$where = '';

			//$where .= " and (api.mo_ID is null or api.mo_sent = 0) ";

			if (!empty($this->options['start_date'])) {
				$where .= " and ba.doc >= '{$this->options['start_date']}' ";
			}

			//$where .= " and ba.batch_id > 0 ";
			//$where .= " and ba.assen = 'HOR1234862' ";

			$sql = "select ba.*,
				cli.*,
				doc.*,
				pa.*,
				ins.*,
				api.mo_ID,
				api.mo_test_ID,
				api.other_info,
				'{$btype}' as btype
                    
            from {$batch_tbl} ba
            left JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
            left JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
            left JOIN ci_doc doc ON doc.doc_ID = ba.doc_ID
            left JOIN ci_clinic cli ON cli.cID = ba.cli_ID
            left JOIN ci_insurances ins ON ins.ins_ID = ba.insurance1
            left join ci_sync_api_orders api ON api.mo_mid = ba.mid
                                        and api.mo_file_id = '{$this->api_id}'
                                        and api.mo_btype = '{$btype}'
             where 1
             {$where}
			 and exists (
			 	select 1
			 	from ci_reports_files rf
			 	where rf.rf_btype='{$btype}'
				and rf_covid_type = 'xy'
			 	and find_in_set(ba.assen, rf.rf_assen_list)
				and (api.mo_test_ID is null or find_in_set(rf.rf_ID, api.mo_test_ID) = 0)
			 )
             order by mid asc
			 limit {$limit}";



			$rows = $wpdb->get_results($sql);
			echo $wpdb->last_error;
			echo $wpdb->last_query;
			echo 'count: ' . count($rows) . '<br>';
			//echo $wpdb->last_query;

			foreach ($rows as $r) {
				$resp = $this->put_req($r);

				$this->putlog($resp);
				if (is_array($resp) && isset($resp['http_code']) && $resp['http_code'] == 201) {

					$other_info = !empty($r->other_info) ? json_decode($r->other_info, true) : array();
					unset($other_info['http_code']);
					unset($other_info['file_ids']);

					$file_ids = !empty($r->mo_test_ID) ? explode(',', $r->mo_test_ID) : array();
					if (isset($resp['file_ids']) && is_array($resp['file_ids'])) {
						$file_ids = array_merge($file_ids, $resp['file_ids']);
					}
					$file_ids = array_unique($file_ids);
					$file_ids = implode(',', $file_ids);

					if (!isset($other_info['sent'])) {
						$other_info['sent'] = array();
					}
					$other_info['sent'][] = array(
						'date' => date_i18n('Y-m-d H:i:s'),
						'http_code' => 	$resp['http_code'],
						'file_ids' => isset($resp['file_ids']) ? $resp['file_ids'] : array(),
					);

					$update = array(
						'mo_file_id'    => $this->api_id,
						'mo_mid'        => $r->mid,
						'mo_btype'		=> $btype,
						'mo_test_ID'    => $file_ids,
						'mo_sent' 		=> 1,
						'mo_sent_dt' 	=> date_i18n('Y-m-d H:i:s'),
						'other_info' 	=> json_encode($other_info),
					);


					if ($r->mo_ID > 0) {
						$rr = $wpdb->update('ci_sync_api_orders', $update, array('mo_ID' => $r->mo_ID));
					} else {
						$rr = $wpdb->insert('ci_sync_api_orders', $update);
					}
				}
			}
		}
	}

	public function put_req($data)
	{
		global $wpdb;

		if (!is_object($data)) {
			$data = (object) $data;
		}
		$gender = $data->pa_gender == 'F' ? 'Female' : 'Male';

		$medications = array();
		$meds = explode(',', $data->current_medication);

		$meds_other = explode(',', $data->current_medication_other);
		if (!empty($meds_other)) {
			$meds = array_merge($meds, $meds_other);
		}
		$meds = array_unique($meds);
		foreach ($meds as $med) {
			$med = trim($med);
			if (!empty($med)) {
				$med_code = $wpdb->get_var($wpdb->prepare("select me_code from ci_medications where me_name = %s", $med));
				$medications[] = [
					'Name' => $med,
					'Code' => $med_code,
				];
			}
		}



		$services = array();


		$files = $this->get_files($data->assen, !empty($data->mo_test_ID) ? explode(',', $data->mo_test_ID) : array());
		if (empty($files)) {
			return false;
		}
		$orderData = [
			"sampleIdentifier" => $data->assen,
			'results' => [
				'PatientName' => [
					'First' => $data->pa_first,
					'Last' => $data->pa_last,
					'Middle' => ''
				],
				'DOB' => $data->pa_dob,
				'SexAtBirth' => $gender,
				"PatientAddress" 	=> [
					"address1" 	=> $data->pa_address,
					"city" 		=> $data->pa_city,
					"state" 	=> $data->pa_state,
					"zip"		 => $data->pa_zip
				],
				'OrderingPhysician' => [
					'First' => $data->doc_first,
					'Last' => $data->doc_last,
					'NPI' => $data->doc_npi
				],
				'Medication' => $medications,
				'PatientResults' => $services
			]
		];


		$file_ids = array();

		foreach ($files as $fk => $fv) {
			if (file_exists($fv['path'])) {
				$orderData[$fk . 'File'] = base64_encode(file_get_contents($fv['path']));
				$file_ids[] = $fv['id'];
			}
		}
		echo '<pre>';
		var_dump($orderData);
		echo '</pre>';

		$log_dir = WP_CONTENT_DIR . '/log/' . $this->log_dir;
		if (!file_exists($log_dir)) {
			mkdir($log_dir, 0755, true);
		}

		file_put_contents($log_dir . '/' . $data->assen . '.json', json_encode($orderData, JSON_PRETTY_PRINT));


		$rs =  $this->request($this->baseUrl, $orderData);
		$rs['file_ids'] = $file_ids;

		return $rs;
	}

	public function settingFields()
	{
		return array(
			'orgid' => array('type' => 'text', 'label' => 'Org ID'),
			'apikey' => array('type' => 'text', 'label' => 'API Key'),
			'start_date' => array('type' => 'date', 'label' => 'Start Date'),
			'cron' => array('type' => 'select', 'options' => array(30 => 'each 30mins', 10 => 'each 10mins', 5 => 'each 5mins', 2 => 'each 2mins'), 'label' => 'Schedule'),
		);
	}
	public function settingActions()
	{
		return array(
			'test_sync_orders' => array('label' => 'Test Send Orders'),
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

	public function setting_count()
	{
		global $wpdb;
		echo '<table style="min-width:300px">';
		echo '<tr><th>Type</th><th>Reqs</th><th>Sent</th><th></th></tr>';
		foreach ($this->all_btypes as $btype) {
			$batch_tbl = tr_pdd_get_table_by_btype($btype);
			$report_tbl = tr_pdd_get_table_report_by_btype($btype);

			$joins = '';

			$sql_count = " select count(*) as count_reqs,
                            SUM(IF(o.mo_sent=1,1,0)) as count_sent
                            from {$batch_tbl} ba
							{$joins}
                            left join {$report_tbl} re ON re.patient_id = ba.assen and re.estatus='C'
                            left join ci_sync_api_orders o ON o.mo_mid = ba.mid and o.mo_btype='{$btype}' and o.mo_file_id = {$this->api_id}
                            where 1
							and exists (
								select 1
								from ci_reports_files rf
								where rf.rf_btype='gene'
								and find_in_set(ba.assen, rf.rf_assen_list)
							) ";
			if (!empty($this->options['start_date'])) {
				$sql_count .= " and ba.doc >= '{$this->options['start_date']}' ";
			}



			if (isset($_GET['debug'])) {
				echo $sql_count;
			}
			$count = $wpdb->get_row($sql_count);

			echo '<tr><th>' . $btype . '</th><td class="center">' . $count->count_reqs . '</td>';
			echo '<td class="center">' . $count->count_sent . '</td>';
			echo '<td><a title="' . strtoupper($btype) . '" class="thickbox" href="' . Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'log', 'btype' => $btype, 'debug' => isset($_GET['debug']) ? 1 : '', 'width' => 1000), false) . '">Log</a>';
			echo '</tr>';
		}
		echo '</table>';
	}

	private function request($url, $data = null, $method = 'POST')
	{

		$ch = curl_init($url);
		if ($method === 'POST') {
			curl_setopt($ch, CURLOPT_POST, true);
		}
		if ($data !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}
		$headers = ['Content-Type: application/json'];
		$headers[] = "orgid: {$this->orgid}";
		$headers[] = "apikey: {$this->apikey}";

		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		$headerSize = $response !== false ? curl_getinfo($ch, CURLINFO_HEADER_SIZE) : 0;
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$responseHeaders = '';
		$responseBody = '';

		echo '<br>';

		if ($response !== false) {
			$responseHeaders = substr($response, 0, $headerSize);
			$responseBody = substr($response, $headerSize);
			var_dump($responseHeaders);
			var_dump($responseBody);
		} else {
			var_dump($response);
		}

		if ($response === false) {
			return ['error' => curl_error($ch)];
		}


		var_dump($url);
		var_dump($headers);
		var_dump($httpCode);
		var_dump($responseHeaders);

		$decoded = json_decode($responseBody, true);
		if (is_array($decoded)) {
			$decoded['http_code'] = $httpCode;
			return $decoded;
		}

		return [
			'http_code' => $httpCode,
			'response_body' => $responseBody,
		];
	}

	public function putlog($msg)
	{
		if (is_array($msg)) {
			$msg = json_encode($msg);
		}
		$filepath = WP_CONTENT_DIR . '/log/ci_api_genetica.txt';
		$f = fopen($filepath, 'a+');
		if ($f) {
			fputs($f, '--------' . date_i18n('Y-m-d H:i:s') . '------' . "\n");
			fputs($f, $msg . "\n\n");
			fclose($f);
		}
	}
}
