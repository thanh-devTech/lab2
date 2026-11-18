<?php
class CI_API_XYGene
{
	public $log_file_slug 	= 'xygene';
	public $api_id 			= -59;
	public $debug 			= false;
	public $options 		= array();
	public $all_btypes 		= array('gene');
	public $log				= '';
	public $last_message	= '';

	//private string $receiveEndpoint = 'https://xygene-stg-api.nextgeninvent.io/xygene/receive-files/ignite';
	//private string $receiveEndpoint = 'https://xygene-dev-api.nextgeninvent.io/xygene/lab/receive-files/ignite';
	private string $receiveEndpoint = 'https://xygene-stg-api.nextgeninvent.io/xygene/lab/receive-files/ignite';

	public function __construct()
	{
		$this->options = get_option('tr_xygene_st', array());
		if (isset($this->options['endpoint']) && !empty($this->options['endpoint'])) {
			$this->receiveEndpoint = $this->options['endpoint'];
		}
	}

	public function settingFields()
	{
		return array(
			'endpoint' => array('type' => 'text', 'label' => 'API Endpoint'),
			'apikey' => array('type' => 'text', 'label' => 'API Key'),
			'dropbox' => array('type' => 'text', 'label' => 'Dropbox Folder'),
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
		$this->clean_files();
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

	public function putlog($msg)
	{
		if (is_array($msg)) {
			$msg = json_encode($msg);
		}
		$filepath = WP_CONTENT_DIR . '/log/ci_api_xygene.txt';
		$f = fopen($filepath, 'a+');
		if ($f) {
			fputs($f, '--------' . date_i18n('Y-m-d H:i:s') . '------' . "\n");
			fputs($f, $msg . "\n\n");
			fclose($f);

			if (filesize($filepath) > 1024 * 1024 * 5) {
				rename($filepath, WP_CONTENT_DIR . '/log/ci_api_xygene_' . date_i18n('Ymd_His') . '.txt');
			}
		}
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
		$headers[] = "api-key: {$this->options['apikey']}";
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT, 300); //timeout in seconds
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);

		$error = curl_error($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);


		return [
			'status_code' => $httpcode,
			'response' => json_decode($response, true),
			'error' => $error,
		];
	}


	public function sendOrder(array $data): array
	{
		return $this->request($this->receiveEndpoint,  $data);
	}

	function get_result_zip($assen)
	{
		global $wpdb;

		//get 2 files


		$txtfile = $wpdb->get_var("select rf_file from ci_reports_files where rf_btype='gene' 
		and find_in_set('{$assen}', rf_assen_list) and rf_file like '%vcf' 
		order by rf_created desc limit 1");

		$rptfile = $wpdb->get_var("select rf_file from ci_reports_files where rf_btype='gene' 
		and find_in_set('{$assen}', rf_assen_list) and (rf_file like '%pgx%'  or rf_file like '%rpt' )
		order by rf_created desc limit 1");

		$files = array();
		if (!empty($txtfile)) {
			$files['txt'] = WP_CONTENT_DIR . '/log/results_gene/' . $txtfile;
		}
		if (!empty($rptfile)) {
			$files['rpt'] = WP_CONTENT_DIR . '/log/results_gene/' . $rptfile;
		}
		echo $assen;
		var_dump($files);
		echo '<br>';

		if (count($files) < 1) {
			return '';
		}
		//exit;
		if (!file_exists(WP_CONTENT_DIR . '/log/genezip/')) {
			wp_mkdir_p(WP_CONTENT_DIR . '/log/genezip/');
		}

		$zipfile = WP_CONTENT_DIR . '/log/genezip/' . $assen . '.zip';

		if (file_exists($zipfile)) unlink($zipfile);

		if (!file_exists($zipfile)) {
			// Create new zip object
			$zip = new ZipArchive();

			if ($zip->open($zipfile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
				// Add files to zip
				foreach ($files as $file) {
					$rs1 = $zip->addFile($file, basename($file));
					echo 'added file: ' . $file . '<br>';
					var_dump($rs1);
					echo '<br>';
				}

				// Close zip archive
				$zip->close();
				echo "Zip created successfully: $zipfile<br>";
			} else {
				echo "Failed to create zip file<br>";
			}
		}


		if (filesize($zipfile) < 100) {
			echo 'empty zip size';
			unlink($zipfile);
			$zipfile = '';
		} else {
			//upload to dropbox
			include_once(TRCPDD_PATH . '/api/ci_dropbox.php');
			if (substr($this->options['dropbox'], 0, 1) != '/') {
				$this->options['dropbox']  = '/' . $this->options['dropbox'];
			}
			$dropboxfile = $this->options['dropbox'] . '/' . basename($zipfile);
			$dropbox = new CI_Dropbox();

			$upload_override = true;
			$dropboxlink = '';
			try {
				$dropboxlink = $dropbox->GetLink($dropboxfile);
				var_dump($dropboxlink);
				echo '<br>';

				if (!empty($dropboxlink) && $upload_override) {
					//$dropbox->Delete($dropboxfile);
					$dropboxlink = '';
					//echo 'removed zip dropbox<br>';
				}
			} catch (Exception $e2) {
			}

			if (empty($dropboxlink)) {
				$rs = $dropbox->UploadFile($zipfile, $dropboxfile);
			} else {
				$rs = true;
			}

			if ($rs) {
				echo 'Uploaded zip file<br>';
				unlink($zipfile);
				echo 'removed zip local<br>';
				$zipfile = $dropbox->GetLink($dropboxfile);
				$zipfile = str_replace('&dl=0', '&dl=1', $zipfile);
			} else {
				$zipfile = '';
				echo 'upload zip file fail<br>';
			}
		}

		return $zipfile;
	}


	public function put_req($data)
	{
		global $wpdb;

		if (!is_object($data)) {
			$data = (object) $data;
		}

		$gender = $data->pa_gender == 'F' ? 'Female' : 'Male';

		$zipfile = $this->get_result_zip($data->assen);

		if (empty($zipfile)) {
			echo 'empty zip';
			return false;
		}

		$group_ids = array();
		foreach (explode(',', $data->panel_tests_requested) as $tid) {
			if (stripos($tid, 'GROUP_') !== false) {
				$group_id = str_ireplace('GROUP_', '', $tid);
				$group_ids[] = $group_id;
			}
		}
		$group_ids_str = implode(',', $group_ids);
		$panels = array();
		$rspanels  = $wpdb->get_results("select tg_name, tg_cpt_code from ci_test_groups where tg_ID in ({$group_ids_str})");
		foreach ($rspanels as $rp) {
			$pname = !empty($rp->tg_cpt_code) ? $rp->tg_cpt_code : $rp->tg_name;
			$panels[] = $pname;
		}

		if (empty($data->lab_name)) {
			$data->lab_name = 'Oncosure';
		}

		$plate_location = $wpdb->get_var("select meta_value from ci_batches_gene_meta where meta_key = '_plate_location' and mid = {$data->mid} ");

		$data->spec_received = str_replace(' ', 'T', $data->spec_received);
		$data->doc = str_replace(' ', 'T', $data->doc);
		$orderData = [
			"FirstName" => $data->pa_first,
			"LastName" => $data->pa_last,
			"DateOfBirth" => $data->pa_dob,
			"Suffix" => $data->pa_suffix,
			"AddressLine1" => $data->pa_address,
			"AddressLine2" => "",
			"City" => $data->pa_city,
			"Country" => "USA",
			"MiddleName" => $data->pa_middle,
			"PostalCode" => $data->pa_zip,
			"SexAtBirth" => $gender,
			"SourceSystemOfRecordIdentifier" => "SYS123",
			"SourceSystemOfRecordName" => $data->lab_name,
			"State" => $data->pa_state,
			"DateReceived" => $data->spec_received,
			//"MedicalFacilityIdentifier" => $data->requesting_practice_code,
			"MedicalFacilityName" => $data->requesting_practice,
			"OrderingPhysician" => $data->requesting_physician,
			"PlateLocation" => $plate_location,
			"SampleIdentifier" => $data->mid,
			"SourceAccessionNumber" => $data->assen,
			"SourceLabName" => "Ignite",
			"SpecimenCollectionDate" => $data->doc,
			"SpecimenReceivedDate" => $data->spec_received,
			"SpecimenType" => "Blood",
			"ReportTypes" => $panels,
			"file_url" => $zipfile
		];
		echo '<pre>';
		var_dump($data->assen);
		var_dump($orderData);
		echo '</pre>';
		//exit;

		$this->putlog($orderData);
		$this->last_message = json_encode($orderData);

		$orderResult = $this->sendOrder($orderData);

		var_dump($orderResult);
		//exit;

		return $orderResult;
	}

	public function send_orders()
	{
		global $wpdb;


		$limit = 100;


		foreach ($this->all_btypes as $btype) {

			$batch_tbl = tr_pdd_get_table_by_btype($btype);
			$report_tbl = tr_pdd_get_table_report_by_btype($btype);


			$where = " and (api.mo_ID is null or api.mo_sent = 0 or api.mo_sent = 2) ";
			$where .= " and (api.mo_runcount < 150 or api.mo_runcount is null)"; //retry 150 times

			if (!empty($this->options['start_date'])) {
				$where .= " and ba.doc >= '{$this->options['start_date']}' ";
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
				api.mo_runcount,
				api.mo_sent,
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
             where 1
             {$where}
             order by mid desc
			 limit {$limit}";



			$rows = $wpdb->get_results($sql);
			echo $wpdb->last_query;
			echo $wpdb->last_error;
			echo 'count: ' . count($rows) . '<br>';
			//echo $wpdb->last_query;

			$count_sent = 0;

			foreach ($rows as $r) {

				//check status again
				$mo_sent = $wpdb->get_var("select mo_sent from ci_sync_api_orders
				 	where mo_file_id = '{$this->api_id}'
					and mo_mid = '{$r->mid}' and mo_btype = '{$btype}' ");
				if (intval($mo_sent) === 1) {
					continue;
				}

				$resp = $this->put_req($r);
				if ($resp === false) {
					continue;
				}

				$this->putlog($resp);

				$update = array(
					'mo_file_id'    => $this->api_id,
					'mo_mid'        => $r->mid,
					'mo_btype'		=> $btype,
					'other_info' 	=> $this->last_message,
					'mo_peg_ID'		=> json_encode($resp['response'])
				);

				$succcess = false;
				if (is_array($resp['response'])) {
					if (isset($resp['response']['StatusMessageCode']) && $resp['response']['StatusMessageCode'] == '200') {
						$succcess = true;
					}
					if (isset($resp['response']['status_code']) && $resp['response']['status_code'] == '200') {
						$succcess = true;
					}
					if (isset($resp['response']['status_message_code']) && $resp['response']['status_message_code'] == '200') {
						$succcess = true;
					}
				}

				if ($succcess) {
					$count_sent++;
					$update['mo_sent'] = 1;
					$update['mo_sent_dt'] = date_i18n('Y-m-d H:i:s');
				} else {
					$update['mo_runcount']  = min(300, $r->mo_runcount + 1);
					$this->putlog("Request failed for mid {$r->mid} with retried: " . ($r->mo_runcount + 1) . " times");
				}


				if ($r->mo_ID > 0) {
					$rso = $wpdb->update('ci_sync_api_orders', $update, array('mo_ID' => $r->mo_ID));
				} else {
					$rso = $wpdb->insert('ci_sync_api_orders', $update);
				}

				if ($count_sent >= 10) {
					break;
				}
			}
		}
	}
}
