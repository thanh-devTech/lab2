<?php

class CI_Api_Genxys
{
	public $log_file_slug 	= 'genxys';
	public $log_dir 		= WP_CONTENT_DIR . '/log/ci_api_genxys/';
	var $auth_url = 'https://us.portal.genxys.com';
	var $api_url = 'https://apim-us.azure-api.net/genxys-portal';

	public $log 	= '';
	public $is_Test = false;
	public $debug 	= false;
	public $all_btypes 		= array('gene');

	var $api_id 	= -62;
	var $options = array();

	public function __construct()
	{
		$this->get_options();
		$this->is_Test = !isset($this->options['is_product']) || !$this->options['is_product'];
	}

	public function get_options()
	{
		$this->options = get_option('tr_hl7_api_genxys_st');
	}

	public function set_option($k, $vl)
	{
		$this->options[$k] = $vl;
		update_option('tr_hl7_api_genxys_st', $this->options);
	}

	public function cron()
	{
		$this->send_orders();

		$this->get_result_file();
	}
	public function callback()
	{
		global $wpdb;
		$request_api_key = $_SERVER['HTTP_API_KEY'] ?? '';
		if (empty($this->request_api_key)) {
			$request_api_key = $_REQUEST['key'] ?? '';
		}

		$key = $wpdb->get_row("select wsdl_key from ci_wsdl_keys
                            where wsdl_key = '{$request_api_key}' and wsdl_type like 'json%'");
		if (empty($key)) {
			wp_send_json([
				'status' => 'error',
				'message' => 'Invalid API key'
			], 401);
		}
		$body = file_get_contents('php://input');
		$data = json_decode($body, true);
		$rs = [];
		if (is_array($data) && isset($data['Id'], $data['Event'])) {
			$id = $data['Id'];
			$event = $data['Event'];
			switch ($event) {
				case ('AwaitingQASignoff'):
					$rs = $this->qa_signoff($id);
					break;
				case ('AwaitingLDSignoff'):
					$rs = $this->ld_signoff($id);
					break;
				case ('Completed'):
					$rs = $this->order_details($id);
					break;
				default:
					$rs = [
						'contentType' => null,
						'serializerSettings' => null,
						'statusCode' => 400,
						'value' => [
							'errorMessages' => ['Event not handled'],
							'success' => false
						]
					];
					break;
			}
		}
		$http_status = 200;
		if (isset($rs['value']['success']) && $rs['value']['success'] === false) {
			$http_status = 400;
		}

		wp_send_json([
			'status' => $rs['value']['success'] ? 'success' : 'error',
			'event' => $event,
			'data' => $rs
		], $http_status);
	}

	public function putlog($msg, $type, $assen)
	{

		if (!file_exists($this->log_dir)) {
			wp_mkdir_p($this->log_dir);
		}
		$filepath = $this->log_dir . sanitize_file_name($type . '_' . $assen) . '.json';
		$now = date_i18n('Y-m-d H:i:s');

		$logs = [];
		if (file_exists($filepath)) {
			$content = file_get_contents($filepath);
			$decoded = json_decode($content, true);
			if (is_array($decoded)) {
				$logs = $decoded;
			}
		}

		$logs[] = [
			'time' => $now,
			'message' => $msg,
		];

		file_put_contents(
			$filepath,
			wp_json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
			LOCK_EX
		);
	}


	protected function apiCall($urlpath, $args = array(), $is_auth = false)
	{
		$urlpath = str_replace(' ', '%20', $urlpath);

		$url = $is_auth ? $this->auth_url : $this->api_url;
		$url .= $urlpath;

		if (!isset($args['headers'])) {
			$args['headers'] = array();
		}
		if (!$is_auth) {
			if ($this->is_token_expired()) {
				$auth_rs = $this->auth();
				if (empty($auth_rs['value']['access_token'])) {
					error_log('Auth failed: cannot refresh token');
					return $auth_rs;
				}
			}

			$args['headers']['Authorization'] = 'Bearer ' . $this->options['access_token'];
		}
		$args['timeout'] = 60;

		$rs = wp_remote_request($url, $args);
		if (!is_wp_error($rs)) {
			$rs = json_decode($rs['body'], true);
		}
		return $rs;
	}

	protected function is_token_expired()
	{
		$expires = $this->options['expires_in'] ?? '';
		if (empty($expires)) {
			return true;
		}

		$expire_time = strtotime($expires);
		return $expire_time <= time();
	}

	public function auth()
	{
		$args = array(
			"username" => $this->options['user'],
			"password" => $this->options['password'],
		);
		$rs = $this->apiCall('/api/app/AccessToken', array(
			'method'    => 'POST',
			'headers'     => array('Content-Type' => 'application/x-www-form-urlencoded'),
			'body'    => http_build_query($args),
		), true);
		if (isset($rs['value'])) {
			$this->set_option('access_token', $rs['value']['access_token']);
			$expire_timestamp = time() + intval($rs['value']['expires_in']);
			$expires = date_i18n('Y-m-d H:i:s', $expire_timestamp);
			$this->set_option('expires_in', $expires);
		}
		return $rs;
	}

	function get_instrument_files($assens)
	{
		global $wpdb;

		if (empty($assens) || !is_array($assens)) {
			echo 'No assens provided';
			return [];
		}

		$assen_conditions = array_map(function ($a) use ($wpdb) {
			return $wpdb->prepare("FIND_IN_SET(%s, rf_assen_list)", $a);
		}, $assens);
		$assen_sql = '(' . implode(' OR ', $assen_conditions) . ')';

		$query = "
			SELECT rf_file 
			FROM ci_reports_files 
			WHERE rf_btype = 'gene'
			AND {$assen_sql}
			ORDER BY rf_created DESC
		";

		$result_files = $wpdb->get_col($query);
		echo $wpdb->last_error;

		if (empty($result_files)) {
			return [];
		}

		$base_dir = WP_CONTENT_DIR . '/log/results_gene/';
		$final_files = [
			'CNV' => "",
			'HLA' => "",
			'OA' => "",
			'SNP' => "",
			'STR' => "",
			'AUX' => "",
			'ULR' => "",
		];
		$opts = get_option('ci_result_settings_gene');
		$extra_exts = isset($opts['gene_extend_file']) ? trim($opts['gene_extend_file']) : '';
		if (empty($extra_exts)) {
			$extra_exts = 'oa,cnv,hla,snp,str,aux,ulr';
		}
		$allowed_exts = array_filter(array_map(function ($ext) {
			return ltrim(trim($ext), '.');
		}, explode(',', $extra_exts)));


		foreach ($result_files as $fname) {
			$full_path = $base_dir . $fname;
			if (file_exists($full_path)) {
				$content = file_get_contents($full_path);
				$ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
				if ($content !== false && in_array($ext, $allowed_exts)) {
					$final_files[strtoupper($ext)] = base64_encode($content);
				}
			}
		}
		$final_files = array_filter($final_files); // Remove empty entries

		return $final_files;
	}

	public function send_orders($assen = null)
	{
		global $wpdb;
		$limit = 10;
		$clinic_ID = $this->options['clinic_id'];

		foreach ($this->all_btypes as $btype) {

			$batch_tbl = tr_pdd_get_table_by_btype($btype);
			$report_tbl = tr_pdd_get_table_report_by_btype($btype);


			$where = '';

			if (!empty($assen)) {
				$where .= " and ba.assen = '{$assen}' ";
			} else {
				$where .= " and (api.mo_ID is null or api.mo_sent = 0 or api.mo_sent = 2) ";
			}

			$where .= " and ba.batch_id > 0 and ba.patient_id > 0 ";
			$where .= " and (re.estatus !='C' or re.estatus is null) ";

			$sql = "select ba.*,
				cli.*,
				doc.*,
				pa.*,
				ins.*,
				lab.lab_name,
				api.mo_ID,
				api.other_info,
				'{$btype}' as btype
                    
            from {$batch_tbl} ba
			LEFT JOIN {$report_tbl} re ON re.patient_id = ba.assen
            JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
            JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
            JOIN ci_doc doc ON doc.doc_ID = ba.doc_ID
            JOIN ci_clinic cli ON cli.cID = ba.cli_ID
            left JOIN ci_insurances ins ON ins.ins_ID = ba.insurance1
            left JOIN ci_sync_api_orders api ON api.mo_mid = ba.mid
                                        and api.mo_file_id = '{$this->api_id}'
                                        and api.mo_btype = '{$btype}'
             where 1 {$where}
             order by mid desc
			 limit {$limit}";


			$rows = $wpdb->get_results($sql);
			echo $wpdb->last_error;
			echo $wpdb->last_query;
			echo '<br><br>';
			$rs = null;
			echo 'count: ' . count($rows);

			if (count($rows) === 0) return null;

			foreach ($rows as $row) {
				$orders = [];
				$assens = [];
				$assens[] = $row->assen;
				$current_medications = $row->current_medication ?? '';
				if (!empty($row->current_medication_other)) {
					$current_medications .= "," . $row->current_medication_other;
				}
				$meds_array = array();
				if (!empty($current_medications)) {
					$meds = explode(',', $current_medications);

					foreach ($meds as $med) {
						$med = trim($med);
						if (!empty($med)) {
							$meds_array[] = array(
								"name" => $med,
								"dosage" => "",
								"coding" => array(
									"system" => "",
									"code" => ""
								),
								"indication" => array(
									"name" => "",
									"coding" => array(
										"system" => "",
										"code" => ""
									)
								)
							);
						}
					}
				}
				$orders[] = [
					"resourceType" => "patient",
					"orderedBy" => $row->doc_first . ' ' . $row->doc_last,
					"clinicIdentifier" => $clinic_ID,
					"clinicType" => "",
					"clinicInstance" => "",
					"barcode" => $row->assen,
					"panel" => "",
					"tag" => "",
					"payload" => array(
						"identifier" => 'PGx 1_B',
						"firstName" => $row->pa_first,
						"lastName" => $row->pa_last,
						"demographics" => array(
							"dob" => $row->pa_dob,
							"sexAtBirth" => $row->pa_gender,
							"email" => $row->pa_email,
							"address" => array(
								"addressLine1" => $row->pa_address,
								"addressLine2" => '',
								"city" => $row->pa_city,
								"region" => $row->state ?? '',
								"postalCode" => $row->zip ?? '0000',
								"country" => 'US',
							),
						),
						"specimen" => array(
							"sampleId" => $row->assen,
							"type" => $row->type_of_specimen,
							"date" => date_i18n('Y-m-d', strtotime($row->doc)),
							"received" => date_i18n('Y-m-d', strtotime($row->spec_received)),
						),
						"notes" => $row->notes,
						"medicalSummary" => array(
							"currentMedications" => $meds_array,
							"consideredMedications" => [],
						),
					)
				];
				$files = $this->get_instrument_files($assens);
				echo 'files: ' . count($files);
				if (empty($files)) {
					continue;
				}
				$data = [
					'orders' => $orders,
					'instrumentFiles' => $files
				];
				$this->putlog($data, "order", $row->assen);
				$rs = $this->apiCall('/api/ExternalLab/UploadLabOrder', array(
					'method'    => 'PUT',
					'headers' => array('Content-Type' => 'application/json'),
					'body' => json_encode($data),
				));
				$this->putlog($rs, "order", $row->assen);
				if (!is_wp_error($rs) && $rs['statusCode'] == 200) {
					$update = array(
						'mo_file_id' => $this->api_id,
						'mo_mid' => $row->mid,
						'mo_btype' => $btype,
					);

					$update['mo_sent'] = 1;
					$update['mo_sent_dt'] = date_i18n('Y-m-d H:i:s');

					if ($row->mo_ID > 0) {
						$wpdb->update('ci_sync_api_orders', $update, array('mo_ID' => $row->mo_ID));
					} else {
						$wpdb->insert('ci_sync_api_orders', $update);
					}
				}
			}

			return $rs;
		}
	}

	public function get_result_file($assen = '', $onlybtype = '')
	{
		global $wpdb;
		$limit = 1;
		$rs = null;
		foreach ($this->all_btypes as $btype) {

			if (!empty($onlybtype) && $onlybtype != $btype) {
				continue;
			}
			$batch_tbl = tr_pdd_get_table_by_btype($btype);

			$time_check = date_i18n('Y-m-d H:i:s', strtotime('-5 minutes', current_time('timestamp')));

			$where = '';
			if (!empty($assen)) {
				$where = $wpdb->prepare(" and ba.assen = %s ", $assen);
			} else {
				$where = $wpdb->prepare("
				AND api.mo_sent >= 1
				AND api.mo_status = 0
				AND api.mo_sent_dt < %s
			", $time_check);

				$where .= " and ba.batch_id > 0 and ba.patient_id > 0 ";
			}


			$sql = "select ba.*,
				api.mo_ID,
				'{$btype}' as btype
                from {$batch_tbl} ba
            	JOIN ci_sync_api_orders api ON api.mo_mid = ba.mid
                                        and api.mo_file_id = '{$this->api_id}'
                                        and api.mo_btype = '{$btype}'
				where 1 {$where}
				order by mid desc
				limit {$limit}";

			$rows = $wpdb->get_results($sql);
			foreach ($rows as $row) {

				$rs = $this->download_reports($row->assen);
				if (!is_wp_error($rs) && $rs['statusCode'] == 200 && !$rs['value']['hasError']) {
					$lab_reports = $rs['value']['labReports'] ?? [];
					foreach ($lab_reports as $rp) {
						$dir = tr_get_result_upload_dir();
						if (!file_exists($dir['path'])) {
							wp_mkdir_p($dir['path']);
						}
						$pdfcode = base64_decode($rp['pdf']);
						$filenamepdf = strtolower($row->assen) . '_' . $this->log_file_slug . '_' . $rp['reportName'];
						$pdf_path = $dir['path'] . '/' . $filenamepdf;
						if (strlen($pdfcode) > 1000) {
							file_put_contents($pdf_path, $pdfcode);
							$row->result_pdf = strtolower($row->result_pdf);
							$result_pdfs = !empty($row->result_pdf) ? explode(',', $row->result_pdf) : array();
							if (!in_array($filenamepdf, $result_pdfs)) {
								$result_pdfs[] = $filenamepdf;
							}
							$wpdb->update($batch_tbl, array('result_pdf' => implode(',', $result_pdfs)), array('mid' => $row->mid));
							$wpdb->update('ci_sync_api_orders', ["mo_status" => 1], array('mo_ID' => $row->mo_ID));
						}
					}
				}
			}
		}
		return $rs;
	}


	public function download_reports($identifier)
	{
		$body = [
			'identifier' => $identifier,
			'reportTypes' => ["long"]
		];
		$this->putlog($body, "download", $identifier);
		$rs = $this->apiCall('/api/ExternalLab/DownloadReports', array(
			'method'    => 'POST',
			'headers' => array('Content-Type' => 'application/json'),
			'body' => json_encode($body)
		));
		$this->putlog($rs, "download", $identifier);
		return $rs;
	}
	public function qa_signoff($identifier)
	{
		$body = [
			'identifier' => $identifier,
		];
		$rs = $this->apiCall('/api/ExternalLab/QaSignoff', array(
			'method'    => 'PUT',
			'headers' => array('Content-Type' => 'application/json'),
			'body' => json_encode($body)
		));
		return $rs;
	}
	public function ld_signoff($identifier)
	{
		$body = [
			'identifier' => $identifier,
		];
		$rs = $this->apiCall('/api/ExternalLab/LdSignoff', array(
			'method'    => 'PUT',
			'headers' => array('Content-Type' => 'application/json'),
			'body' => json_encode($body)
		));
		return $rs;
	}
	public function order_details($identifier)
	{
		$body = [
			'identifier' => $identifier,
		];
		$rs = $this->apiCall('/api/ExternalLab/LabOrderDetails', array(
			'method'    => 'POST',
			'headers' => array('Content-Type' => 'application/json'),
			'body' => json_encode($body)
		));
		return $rs;
	}
}
