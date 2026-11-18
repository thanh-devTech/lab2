<?php
include_once('ci_hl7.php');

class CI_Api_Sftp_EMR extends CI_Hl7
{

	public $order_folder = '/';
	public $result_folder = '/';
	protected $conn;
	protected $sftp;
	protected $options;

	public $debug = false;
	public $log = '';
	public $api_id = 'sftp_emr';
	public $api_num = -32;
	public $covid_loinc_code = '94306-8';

	public function __construct($options)
	{

		$this->options = $options;

		$this->order_folder = $options['order_folder'];
		$this->result_folder = $options['result_folder'];

		if (!function_exists('ssh2_connect')) {
			throw new Exception('function ssh2_connect not exist! 
			to install: yum install php-pecl-ssh2 or apt-get install php-ssh2. 
			sudo apt install php-pear php-dev libssh2-1-dev
			then restart php');
		}
	}

	public function connect()
	{
		//echo 'Connecting to: ' . $this->options['host'] . ':' . $this->options['port'];
		$opts = array();
		if (!isset($this->options['disable_ssh_rsa']) || !$this->options['disable_ssh_rsa']) {
			$opts['hostkey'] = 'ssh-rsa';
		}
		$this->conn = @ssh2_connect($this->options['host'], $this->options['port'], $opts);

		if (!$this->conn) {
			throw new Exception('Unable to connect');
		}
	}

	public function login()
	{
		if (empty($this->options['password']) || !empty($this->options['ppk'])) {
			$pubfile = WP_CONTENT_DIR . '/uploads/' . $this->options['pub'];
			$rsafile = WP_CONTENT_DIR . '/uploads/' . $this->options['ppk'];
			$result = ssh2_auth_pubkey_file($this->conn, $this->options['username'], $pubfile, $rsafile);
		} else {
			$result = ssh2_auth_password($this->conn, $this->options['username'], $this->options['password']);
		}



		if ($result === false) {
			throw new Exception('Login incorrect');
		}

		$this->sftp = @ssh2_sftp($this->conn);

		return $result;
	}

	public function getpath($path)
	{
		if (stripos($path, 'wp-content') !== false) {
			return $path;
		}
		return "ssh2.sftp://{$this->sftp}{$path}";
	}

	public function scan_dir($path = '')
	{
		$dir = $this->getpath($path);
		$tempArray = array();
		$handle = opendir($dir);
		if ($handle) {
			while (false !== ($file = readdir($handle))) {
				if (substr("$file", 0, 1) != ".") {
					$tempArray[] = $file;
				}
			}
			closedir($handle);
		}


		return $tempArray;
	}

	public function receiveFile($remote_file, $local_path)
	{
		$path = $this->getpath($remote_file);
		$stream = @fopen($path, 'r');
		if (!$stream)
			throw new Exception("Could not open file: $remote_file");
		$f = fopen($local_path, 'w');
		if ($f) {
			while (($buffer = fgets($stream, 4096)) !== false) {
				fwrite($f, $buffer);
			}
		} else {
			throw new Exception("Could not open local file: $local_path");
		}
		@fclose($stream);
	}

	public function uploadFile($local_file, $remote_file)
	{
		$path = $this->getpath($remote_file);
		$stream = @fopen($path, 'w');
		if (!$stream)
			throw new Exception("Could not open file: $remote_file");
		$data_to_send = @file_get_contents($local_file);
		if ($data_to_send === false)
			throw new Exception("Could not open local file: $local_file.");
		if (@fwrite($stream, $data_to_send) === false)
			throw new Exception("Could not send data from file: $local_file.");
		@fclose($stream);
	}

	public function delete($remote_path)
	{
		$path = $this->getpath($remote_path);
		$rs  = unlink($path);
		if (!$rs)
			throw new Exception("Could not delete file: $remote_path");
	}

	public function removeDir($path)
	{
		try {
			return ssh2_sftp_rmdir($this->sftp, $path);
		} catch (Exception $e) {
		}
		return false;
	}

	public function movefile($file_path, $move_path)
	{
		try {
			return ssh2_sftp_rename($this->sftp, $file_path, $move_path);
		} catch (Exception $e) {
		}
		return false;
	}

	public function getFileSize($remote_file)
	{
		$path = $this->getpath($remote_file);
		return filesize($path);
	}

	public function getFileTime($remote_file)
	{
		$path = $this->getpath($remote_file);
		return filemtime($path);
	}


	public function sync_reqs()
	{
		global $wpdb;

		$old_archive_dir = $this->order_folder . '/archive/';
		$archive_dir = '/archive/';

		$files = $this->scan_dir($this->order_folder);
		$count_files = 0;



		ssh2_sftp_rmdir($this->sftp, $old_archive_dir);
		//ssh2_sftp_mkdir($this->sftp, $archive_dir);

		if (count($files) == 0) {
			$this->log .= "No files in Orders folder!";
		}

		if ($this->debug) {
			var_dump($files);
		}
		foreach ($files as $file) {
			$file_path = $this->order_folder . '/' . $file;
			if (stripos($file, '.') > 0) {
				$filetime = $this->getFileTime($file_path);
				$arr = array(
					'm_file' => $file_path,
					'm_time' => $filetime,
					'm_type' => $this->api_id
				);
				$dbfile = $wpdb->get_row("select * from ci_sync_api_files where m_file='{$file_path}' and m_type='{$this->api_id}'");
				if ($dbfile) {
					if ($filetime <= $dbfile->m_time) {
						//$this->movefile($file_path, $archive_dir.$file);
						//continue;
					}
				} else {
					$wpdb->insert('ci_sync_api_files', $arr);
					$dbfile = $wpdb->get_row("select * from ci_sync_api_files where m_file='{$file_path}'  and m_type='{$this->api_id}'");
				}

				$done = $this->import_req_file($dbfile, $file_path);

				//move file to archive
				if ($done) {
					//$this->movefile($file_path, $archive_dir . $file);
					$this->delete($file_path);
				}

				$count_files++;
			}
		}

		if ($this->debug && $count_files = 0) {
			$this->log .= " No file need sync\n";
		}
		return $count_files;
	}

	public function findinarr($arr, $need)
	{
		$found = false;
		if (is_array($arr)) {
			foreach ($arr as $vl) {
				if ($found = $this->findinarr($vl, $need)) {
					return $found;
				}
			}
		} else if (is_string($arr) && stripos($arr, $need) === 0) {
			$found = $arr;
		}

		return $found;
	}

	public function import_req_file($dbfile, $file_path)
	{
		global $wpdb;
		$mid = 0;
		if ($this->debug) {
			$this->log .= "file: " . $file_path . "\n";
		}
		$group_covid_id = $wpdb->get_var("select tg_ID from ci_test_groups 
			                where (tg_is_covid=1 or tg_name ='nCoV-2019') 
			                and tg_type='pcr'");

		$btype = 'pcr';
		$batch_tbl = 'ci_batches_pcr';

		$path = $this->getpath($file_path);
		$stream = @fopen($path, 'r');
		$contents = '';

		while (($buffer = fgets($stream, 4096)) !== false) {
			$contents .= $buffer;
		}

		$hl7_dir = WP_CONTENT_DIR . '/log/sftp_emr_orders/';
		if (!file_exists($hl7_dir)) {
			mkdir($hl7_dir);
		}

		if (!isset($_GET['act']) || $_GET['act'] != 'reimport') {
			file_put_contents($hl7_dir . basename($file_path), $contents);
		}


		$packages = explode('MSH|', $contents);

		$clinics = array();
		if (isset($this->options['check_nte_clinic']) && $this->options['check_nte_clinic']) {
			$clinics = $wpdb->get_results("select cID, cli_clinic, cli_street, cli_city
			from ci_clinic where cli_status = 1", ARRAY_A);
		}

		$default_clinic_name = '';
		$clinic_id = $this->options['cli_id'];
		if ($this->options['cli_id'] > 0) {
			$default_clinic_name = $wpdb->get_var("select cli_clinic from ci_clinic where cID = '{$this->options['cli_id']}' ");
		}

		$default_doctor_name = '';
		if ($this->options['doc_id'] > 0) {
			$doctor = $wpdb->get_row("select doc_first,doc_last from ci_doc where doc_ID = {$this->options['doc_id']}");
			$default_doctor_name = $doctor->doc_last . ', ' . $doctor->doc_first;
		}

		foreach ($packages as $package) {
			$other_info = array();
			$package = trim($package);
			if (empty($package)) continue;
			$package = 'MSH|' . $package;
			$priority_hours  = 0;
			$report_notes = '';

			$arr = $this->hl7_to_array($package, array('OBR', 'IN1', 'OBX'));

			$clinic_id = $this->options['cli_id'];
			$clinic_name = $default_clinic_name;


			$control_id   = $arr['MSH']['MSH.10']['MSH.10.1'];
			$location_code = isset($arr['MSH']['MSH.4']['MSH.4.1']) ? $arr['MSH']['MSH.4']['MSH.4.1'] : '';
			$hl7_set_ID   = $arr['PID']['PID.1']['PID.1.1'];
			$hl7_patient_id = $arr['PID']['PID.3']['PID.3.1'];
			$pa_last      = trim($arr['PID']['PID.5']['PID.5.1']);
			$pa_first     = trim($arr['PID']['PID.5']['PID.5.2']);
			$dob          = $arr['PID']['PID.7']['PID.7.1'];
			$gender       = $arr['PID']['PID.8']['PID.8.1'];
			$race_code       = $arr['PID']['PID.10']['PID.10.1'];
			$address      = trim($arr['PID']['PID.11']['PID.11.1'] . ' ' . $arr['PID']['PID.11']['PID.11.2']);
			$city         = $arr['PID']['PID.11']['PID.11.3'];
			$state        = $arr['PID']['PID.11']['PID.11.4'];
			$zip          = $arr['PID']['PID.11']['PID.11.5'];
			$phone        = $arr['PID']['PID.13']['PID.13.1'];
			$email        = $arr['PID']['PID.13']['PID.13.4'];
			$ethnicity    = $arr['PID']['PID.22']['PID.22.1'];


			$pa_first = str_replace('??', '`', $pa_first);
			$pa_last = str_replace('??', '`', $pa_last);

			$order_id       = $arr['ORC']['ORC.2']['ORC.2.1'];
			$doc			= '';
			$doc_npi 		= $arr['ORC']['ORC.12']['ORC.12.1'];
			$doc_last       = $arr['ORC']['ORC.12']['ORC.12.2'];
			$doc_first      = $arr['ORC']['ORC.12']['ORC.12.3'];



			$peg_id          = isset($arr['OBR']['OBR.13']['OBR.13.1']) ? $arr['OBR']['OBR.13']['OBR.13.1'] : '';
			$obr_code        = isset($arr['OBR']['OBR.2']['OBR.2.1']) ? $arr['OBR']['OBR.2']['OBR.2.1'] : '';

			$other_info['OBR_code'] = array();
			$other_info['OBR_source'] = array();
			$found_btype = false;
			foreach ($arr['OBR'] as $obr) {
				$peg_id          = $obr['OBR.13']['OBR.13.1'];
				$obr_code        = $obr['OBR.2']['OBR.2.1'];

				$other_info['OBR16.1'] = $obr['OBR.16']['OBR.16.1'];
				$other_info['OBR16.2'] = $obr['OBR.16']['OBR.16.2'];
				$other_info['OBR16.3'] = $obr['OBR.16']['OBR.16.3'];
				$other_info['OBR_3'] = $obr['OBR.3']['OBR.3.1'];
				$obr3 = $obr['OBR.2']['OBR.2.1'];

				$other_info['OBR41'] = trim($obr['OBR.4']['OBR.4.1']);
				$other_info['OBR42'] = trim($obr['OBR.4']['OBR.4.2']);
				$other_info['OBR7'] = trim($obr['OBR.7']['OBR.7.1']);

				$other_info['OBR_code'][] = $other_info['OBR41'];
				$other_info['OBR_source'][$other_info['OBR41']] = array($obr['OBR.9']['OBR.9.1'], $obr['OBR.9']['OBR.9.2']);

				$bcode = $other_info['OBR41'];
				if ($this->options['alltypes'] && !$found_btype && !empty($bcode)) {
					//check type

					$group_btype = $wpdb->get_var("select tg_type
					from ci_test_groups
					where tg_short_name = '{$bcode}'
					and tg_show_req = 1");
					if (!empty($group_btype)) {
						$btype = $group_btype;
						$batch_tbl = tr_pdd_get_table_by_btype($btype);
						$found_btype = true;
					} else {
						//check test
						$test_btype = $wpdb->get_var("select te_type 
						from ci_test_panels
						where te_loinc_code = '{$bcode}' ");
						if (!empty($test_btype)) {
							$btype = $test_btype;
							$batch_tbl = tr_pdd_get_table_by_btype($btype);
							$found_btype = true;
						}
					}
				}



				if (stripos($obr['OBR.15']['OBR.15.1'], '12h') !== false) {
					$priority_hours = 12;
				} else if (stripos($obr['OBR.15']['OBR.15.1'], '24h') !== false) {
					$priority_hours = 24;
				}

				if (empty($doc)) {
					$doc = $obr['OBR.7']['OBR.7.1'];
				}
			}

			if (empty($doc)) {

				$doc            = $arr['ORC']['ORC.9']['ORC.9.1'];
			}


			if (empty($peg_id)) {
				$peg_id = $obr_code;
			}
			if (stripos($peg_id, ':') > 0) {
				list($t, $peg_id) = explode(':', $peg_id);
				$peg_id = trim($peg_id);
			}
			$peg_id = substr($peg_id, 0, 30);


			$other_info['ORC12.1'] = $arr['ORC']['ORC.12']['ORC.12.1'];
			$other_info['ORC12.2'] = $arr['ORC']['ORC.12']['ORC.12.2'];
			$other_info['ORC12.3'] = $arr['ORC']['ORC.12']['ORC.12.3'];


			$other_info['NTE'] = trim($arr['NTE']['NTE.3']['NTE.3.1']);
			$other_info['MSH4'] = trim($arr['MSH']['MSH.4']['MSH.4.1'] . '^' . $arr['MSH']['MSH.4']['MSH.4.2']);
			$other_info['MSH5'] = trim($arr['MSH']['MSH.5']['MSH.5.1']);
			$other_info['MSH6'] = trim($arr['MSH']['MSH.6']['MSH.6.1'] . '^' . $arr['MSH']['MSH.6']['MSH.6.2']);
			$other_info['PID3'] = trim($arr['PID']['PID.3']['PID.3.1']);
			$other_info['PID10'] = trim($arr['PID']['PID.10']['PID.10.1']);


			$other_info['PV17'] = trim($arr['PV1']['PV1.7']['PV1.7.1']);

			$custom_fields = array();
			foreach ($arr['OBX'] as $obx) {
				if (stripos($obx['OBX.3']['OBX.3.2'], 'location:') !== false) {
					$custom_fields['pdf_location'] = $obx['OBX.5']['OBX.5.1'];
				}
			}


			$ref_assen = !empty($obr3) ? $obr3 : $peg_id;
			preg_match("/^([0-9]+)/", $ref_assen, $ref_matches);
			if (isset($ref_matches[1]) && !empty($ref_matches[1])) {
				//$ref_assen = $ref_matches[1];
			}

			if (empty($order_id)) {
				$this->log .= " empty $order_id";
				return false;
			}


			$dob    = date('Y-m-d', strtotime($dob));

			if (empty($doc)) {
				$doc = date_i18n('Y-m-d H:i:s');
			}
			$doc    = date('Y-m-d H:i:s', strtotime($doc));
			$gender = stripos($gender, 'f') !== false ? 'F' : 'M';

			$orderdb = $wpdb->get_row("select * from ci_sync_api_orders 
							where mo_test_ID='{$order_id}'
							      and mo_file_id = '{$dbfile->m_ID}' ");
			$mid = $orderdb && $orderdb->mo_mid > 0 ? $orderdb->mo_mid : 0;
			$bat = null;
			if ($mid > 0) {
				$bat = $wpdb->get_row("select * from {$batch_tbl} where mid = {$mid} ");
			} else if (!empty($peg_id) && strlen($peg_id) > 6) {
				//$bat = $wpdb->get_row( "select * from {$batch_tbl} where oldreq = '{$peg_id}' " );
			}

			if (!$bat) {
				$assen = tr_get_new_assen();

				if (empty($assen)) {
					continue;
				}
			} else {
				$assen = $bat->assen;
			}

			$this->log .= "assen: {$assen} \n";

			$patientid = $bat && $bat->patient_id > 0 ? $bat->patient_id : 0;
			if ($patientid == 0) {

				$phone = str_replace(array(')', '(', '-', ' '), '', $phone);
				$state = strtoupper($state);
				$state = substr($state, 0, 2);

				$patientid = $wpdb->get_var("select pa_ID from ci_patients where pa_first='{$pa_first}'
				                and pa_last='{$pa_last}' and pa_dob='{$dob}' and pa_city = '{$city}' ");
				$patient = array(
					'pa_first'  => $pa_first,
					'pa_last'   => $pa_last,
					'pa_dob'    => $dob,
					'pa_gender' => $gender,
					'pa_phone'  => $phone,
					'pa_address'    => $address,
					'pa_city'    => $city,
					'pa_state'    => $state,
					'pa_zip'    => $zip,
					'pa_email'  => $email,
				);
				if (!empty($hl7_patient_id)) {
					$patient['pa_mrn'] = $hl7_patient_id;
				}

				//fix phone
				if (isset($patient['pa_phone'])) {
					$patient['pa_phone'] = str_replace(array(' ', '(', ')', '-', '.'), '', $patient['pa_phone']);
				}
				if ($patientid == 0) {

					$wpdb->insert('ci_patients', $patient);
					$patientid = $wpdb->insert_id;
				} else {
					foreach ($patient as $k => $vl) {
						if (empty($vl)) {
							unset($patient[$k]);
						}
					}
					$wpdb->update('ci_patients', $patient, array('pa_ID' => $patientid));
				}
			}


			//check insurance
			$ins_ids = array();
			$ins_ids[1] = $bat && $bat->insurance1 > 0 ? $bat->insurance1 : 0;
			$ins_ids[2] = $bat && $bat->insurance2 > 0 ? $bat->insurance2 : 0;

			$insurance = '';
			foreach ($arr['IN1'] as $ins) {
				$ins_num = intval($ins['IN1.1']['IN1.1.1']);

				$ins_company      = isset($ins['IN1.4']['IN1.4.1']) ? $ins['IN1.4']['IN1.4.1'] : '';
				$ins_address      = isset($ins['IN1.5']['IN1.5.1']) ? $ins['IN1.5']['IN1.5.1'] : '';
				$ins_city         = isset($ins['IN1.5']['IN1.5.3']) ? $ins['IN1.5']['IN1.5.3'] : '';
				$ins_state        = isset($ins['IN1.5']['IN1.5.4']) ? $ins['IN1.5']['IN1.5.4'] : '';
				$ins_zip          = isset($ins['IN1.5']['IN1.5.5']) ? $ins['IN1.5']['IN1.5.5'] : '';
				$ins_phone        = isset($ins['IN1.7']['IN1.7.1']) ? $ins['IN1.7']['IN1.7.1'] : '';
				$ins_group_id     = isset($ins['IN1.8']['IN1.8.1']) ? $ins['IN1.8']['IN1.8.1'] : '';
				$ins_group_name   = isset($ins['IN1.9']['IN1.9.1']) ? $ins['IN1.9']['IN1.9.1'] : '';
				$ins_last         = isset($ins['IN1.16']['IN1.16.1']) ? $ins['IN1.16']['IN1.16.1'] : '';
				$ins_first        = isset($ins['IN1.16']['IN1.16.2']) ? $ins['IN1.16']['IN1.16.2'] : '';
				$ins_relation     = isset($ins['IN1.17']['IN1.17.1']) ? $ins['IN1.17']['IN1.17.1'] : '';
				$ins_dob          = isset($ins['IN1.18']['IN1.18.1']) ? $ins['IN1.18']['IN1.18.1'] : '';
				$ins_authorization_id = isset($ins['IN1.14']['IN1.14.1']) ? $ins['IN1.14']['IN1.14.1'] : '';
				$ins_member_id	  = isset($ins['IN1.36']['IN1.36.1']) ? $ins['IN1.36']['IN1.36.1'] : '';

				if (empty($ins_relation)) {
					$ins_relation = 'Self';
				}
				if ($ins_num == 1) {
					$insurance = $ins_company;
				}


				if ($ins_ids[$ins_num] == 0) {
					if (empty($ins_first) && $ins_num == 1) {
						$ins_first = $pa_first;
						$ins_last = $pa_last;
					}

					if (empty($ins_first) && empty($ins_last)) {
						continue;
					}
					$ins_ids[$ins_num] = $wpdb->get_var("select ins_ID from ci_insurances 
									where ins_first='{$ins_first}' and ins_last = '{$ins_last}' 
									and ins_company='{$ins_company}' and pa_ID = {$patientid} ");
				}

				$ins_arr = array(
					'ins_first' 		=> $ins_first,
					'ins_last'			=> $ins_last,
					'ins_relation' 		=> $ins_relation,
					'ins_company'		=> $ins_company . '',
					'ins_address' 		=> $ins_address . '',
					'ins_city' 			=> $ins_city . '',
					'ins_state' 		=> $ins_state . '',
					'ins_zip' 			=> $ins_zip . '',
					'ins_phone' 		=> $ins_phone . '',
					'ins_group_id' 		=> $ins_group_id . '',
					'ins_member_id' 	=> $ins_member_id . '',
					'ins_authorization_id' => $ins_authorization_id . '',
					'ins_dob' 			=> $ins_dob . '',
					'pa_ID' 			=> $patientid . '',

				);

				if ($ins_ids[$ins_num] == 0) {
					$wpdb->insert('ci_insurances', $ins_arr);
					$ins_ids[$ins_num] = $wpdb->insert_id;
					if ($this->debug) {
						$this->log .= "Added new Insurance: {$ins_company} => {$ins_ids[$ins_num]}\n";
					}
				} else {
					$wpdb->update('ci_insurances', $ins_arr, array('ins_ID' => $ins_ids[$ins_num]));
					//echo $wpdb->last_error;
					//echo $wpdb->last_query;
				}
			}


			if (empty($insurance)) {
				$insurance = 'No Insurance - Cash';
			}


			//check clinic
			if ($this->options['check_nte_clinic']) {

				$s_addr = array(' Rd', ' Dr');
				$r_addr = array(' Road', ' Drive');

				$nte3_1 = $arr['NTE']['NTE.3']['NTE.3.1'];
				$nte3_1 = str_ireplace($s_addr, $r_addr, $nte3_1);
				$nte3_1 = preg_replace("/[^a-zA-Z1-9]/", '', $nte3_1);
				if (!empty($nte3_1)) {
					foreach ($clinics as $clinic) {

						$pcli = false;
						$cliname1 = preg_replace("/[^a-zA-Z1-9]/", '', $clinic['cli_clinic']);
						if (!empty($cliname1) && stripos($nte3_1, $cliname1) !== false) {
							$pcli = true;
						} else if (!empty($clinic['cli_street'])) {

							$clinic['cli_street'] = str_replace($s_addr, $r_addr, $clinic['cli_street']);
							$clinic['cli_city'] = str_replace($s_addr, $r_addr, $clinic['cli_city']);

							$clinic['cli_street'] = preg_replace("/[^a-zA-Z1-9]/", '', $clinic['cli_street']);
							$clinic['cli_city'] = preg_replace("/[^a-zA-Z1-9]/", '', $clinic['cli_city']);


							if (
								stripos($nte3_1, $clinic['cli_street']) !== false
								&& stripos($nte3_1, $clinic['cli_city']) !== false
							) {
								$pcli = true;
							}
						}


						if ($pcli) {
							$clinic_id = $clinic['cID'];
							$clinic_name = $clinic['cli_clinic'];
							echo ' clinic: ' . $clinic_name . ' <br> ';
							break;
						}
					}
				}
			} else if (!empty($location_code)) {

				//check clinic by location code
				$pcli = $wpdb->get_row("select cID, cli_clinic from ci_clinic where cli_sftp_emr_code ='{$location_code}' ", ARRAY_A);
				if ($pcli) {
					$clinic_id = $pcli['cID'];
					$clinic_name = $pcli['cli_clinic'];
					echo ' clinic: ' . $clinic_name . ' <br> ';
				}

				$report_notes = isset($arr['NTE']['NTE.3']['NTE.3.1']) ? $arr['NTE']['NTE.3']['NTE.3.1'] : '';
			}

			//check doctor
			$doc_id = $this->options['doc_id'];
			$doctor_name = $default_doctor_name;
			if (!empty($doc_first) && !empty($doc_last)) {

				$doc_id = $wpdb->get_var("select doc_ID from ci_doc where  
									( (doc_first='{$doc_first}' and doc_last='{$doc_last}') or
									 (doc_first='{$doc_first}' and doc_last='{$doc_last}') )
									 and doc_npi = '{$doc_npi}' ");
				if (empty($doc_id)) {
					$doc_id = $wpdb->get_var("select doc_ID from ci_doc where  
									 (doc_first='{$doc_first}' and doc_last='{$doc_last}') or
									 (doc_first='{$doc_first}' and doc_last='{$doc_last}') 
									 ");
				}


				if ($doc_id == 0) {
					$doc_arr = array(
						'doc_first' => $doc_first,
						'doc_last' => $doc_last
					);
					if (!empty($doc_npi)) {
						$doc_arr['doc_npi'] = $doc_npi;
					}
					$wpdb->insert('ci_doc', $doc_arr);
					$doc_id = $wpdb->insert_id;
					if ($this->debug) {
						$this->log .= "Added new doctor: {$doc_npi} {$doc_first} {$doc_last}\n";
					}
				} else {
					echo ' doctor: ' . $doc_last . ', ' . $doc_first . ' <br> ';
				}
				$doctor_name = $doc_last . ', ' . $doc_first;
			}


			if ($doc_id > 0 && $clinic_id > 0) {
				$wpdb->insert('ci_clinic_doc', array('doc_ID' => $doc_id, 'cli_ID' => $clinic_id));
			}


			$tests_requested = 'GROUP_' . $group_covid_id;
			$type_of_specimen = '';
			$dagnostic_codes = array();
			if (isset($arr['DG1'])) {
				foreach ($arr['DG1'] as $dg1) {
					if (!empty($dg1['DG1.3']['DG1.3.1'])) {
						$dagnostic_codes[] = $dg1['DG1.3']['DG1.3.1'];
					}
				}
			}

			$dagnostic_codes = implode(',', $dagnostic_codes);
			if (empty($dagnostic_codes) || strpos($dagnostic_codes, '.') === false) {
				$dagnostic_codes = 'Z20.828';
			}
			$tests_requested_arr = array();
			$source_ids = array();

			foreach ($other_info['OBR_code'] as $bcode) {

				$group_id = $wpdb->get_var("select tg_ID 
				from ci_test_groups
				where ( tg_cpt_code='{$bcode}' or tg_short_name = '{$bcode}' or tg_name = '{$bcode}')
				and tg_type='{$btype}' ");


				$soucecodeobr = $other_info['OBR_source'][$bcode];

				if (isset($soucecodeobr[0])) {
					$source_id = 0;
					if (!empty($soucecodeobr[0])) {
						$source_c = $soucecodeobr[0];
						//get source id by code
						$source_id = $wpdb->get_var("select ts_ID from ci_test_specimens where ts_type = '{$btype}' and (ts_code='{$source_c}' or ts_name='{$source_c}')");
					}

					if ($source_id == 0) {
						$source_id = $wpdb->get_var("select ts_ID from ci_test_specimens where ts_type = '{$btype}' and ts_group_id='{$group_id}' order by ts_ID asc limit 1");
					}
					if ($source_id > 0) {
						$source_ids[$source_id] = $source_id;
					}
					echo ' source: ' . $source_id . ' <br> ';
				}

				$where = " and  te_loinc_code='{$bcode}' ";
				if ($group_id > 0) {
					$tests_requested_arr[] = 'GROUP_' . $group_id;
					$where = " and ( 
									(tg_parent = '{$group_id}' and tg_short_name!='ABR' ) 
									or tg_ID = {$group_id} 
								) ";
				}

				$sqlt = "select te.te_ID, 
				te.te_type_id,
				g.tg_ID, 
				g.tg_only_group,
				g.tg_parent,
				g.tg_show_req
				from ci_test_panels te
				JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
				where te_type = '{$btype}' and te_in_req = 1
				{$where}
				group by te.te_ID
				";

				$testarr = $wpdb->get_results($sqlt, ARRAY_A);


				if (count($testarr) > 0) {

					foreach ($testarr as $t) {
						if (!in_array('GROUP_' . $t['tg_ID'], $tests_requested_arr) && $t['tg_show_req']) {
							$tests_requested_arr[] = 'GROUP_' . $t['tg_ID'];
						}
						$tests_requested_arr[] = $t['te_ID'];

						if ($t['te_type_id'] > 0) {
							$source_ids[$t['te_type_id']] = $t['te_type_id'];
						}
					}
				}
			}

			if (count($tests_requested_arr) > 0) {
				$tests_requested = implode(',', $tests_requested_arr);


				if (count($source_ids) > 0) {
					$source_ids_str = implode(',', $source_ids);

					$sources = $wpdb->get_col("select ts_name 
							from ci_test_specimens 
							where ts_ID in ({$source_ids_str})");

					$type_of_specimen = implode(',', $sources);
				}

				if (empty($type_of_specimen)) {
					if ($btype == 'blood') {
						$type_of_specimen = 'Blood';
					} else if ($btype == 'pcr') {
						$type_of_specimen = 'Nasopharyngeal Swab';
					}
				}
			} else if ($btype == 'pcr') {
				$type_of_specimen = 'Nasopharyngeal Swab';
			}


			$patient_name = $pa_last . ', ' . $pa_first;
			$data         = array(
				'assen'                => $assen,
				'patient_id'           => $patientid,
				'patient_name'         => $patient_name,
				'lab_ID'               => $this->options['lab_id'],
				'doc_ID'               => intval($doc_id),
				'cli_ID'               => intval($clinic_id),
				'doc'                  => $doc,
				'dob'                  => $dob,
				'requesting_physician' => $doctor_name . '',
				'requesting_practice'  => $clinic_name . '',
				'type_of_specimen'     => $type_of_specimen,
				'dagnostic_codes1'     => $dagnostic_codes,
				'panel_tests_requested' => $tests_requested,
				'insurance'             => $insurance . '',
				'insurance1'             => intval($ins_ids[1]),
				'insurance2'             => intval($ins_ids[2]),
				//'notes'                 => $obr3 . '',
				'oldreq'                 => $ref_assen . '',
				'patient_signature'      => 'onfile',
				'paid'      => $this->api_num,
			);
			if ($priority_hours > 0) {
				$data['priority_hours'] = $priority_hours;
			}

			if (!empty($report_notes)) {
				$data['notes_report'] = $report_notes;
			}
			$question_id = $bat && $bat->covid_question_id ? $bat->covid_question_id : 0;
			$covidq = array();

			if ($race_code == '1002-5') {
				$race_code = 'I';
			} else if ($race_code == '2076-8') {
				$race_code = 'P';
			} else if ($race_code == '2028-9') {
				$race_code = 'A';
			} else if ($race_code == '2054-5') {
				$race_code = 'B';
			} else if ($race_code == '2106-3') {
				$race_code = 'W';
			} else if ($race_code == '2131-1') {
				$race_code = 'O';
			}

			if ($ethnicity == '2135-2') {
				$ethnicity = 'HL';
			} else if ($ethnicity == '2186-5') {
				$ethnicity = 'NHL';
			} else {
				$ethnicity = 'U';
			}

			$covidq['race'] = substr($race_code, 0, 1);
			$covidq['ethnicity'] = substr($ethnicity, 0, 3);
			foreach ($arr['NTE'] as $nte) {
				if (strpos($nte['NTE.3']['NTE.3.1'], '?') > 0) {
					list($question, $anser_text) = explode('?', $nte['NTE.3']['NTE.3.1']);
					$anser = '';
					if (stripos($anser_text, 'Y') !== false) {
						$anser = 'Y';
					} else if (stripos($anser_text, 'U') !== false) {
						$anser = 'U';
					} else if (stripos($anser_text, 'N') !== false) {
						$anser = 'N';
					}

					if (empty($anser)) continue;

					if (stripos($question, 'first test') !== false) {
						$covidq['first_covid_test'] = $anser; //1
					} else if (stripos($question, 'employed in healthcare') !== false) {
						$covidq['employed_in_heal'] = $anser; //2
					} else if (stripos($question, 'symptomatic') !== false) {
						$covidq['cdc'] = $anser; //3
					} else if (stripos($question, 'ICU') !== false) {
						$covidq['icu'] = $anser; //4
					} else if (stripos($question, 'Congregate') !== false) {
						$covidq['resident'] = $anser; //5
					} else if (stripos($question, 'Pregnant') !== false) {
						$covidq['pregnant'] = $anser; //6
					}
				}
			}

			if (count($covidq) > 0) {
				if ($question_id > 0) {
					$wpdb->update('ci_covid_questions', $covidq, array('ID' => $question_id));
				} else {
					$covidq['created'] = date_i18n('Y-m-d H:i:s');
					$wpdb->insert('ci_covid_questions', $covidq);
					$question_id  = $wpdb->insert_id;
				}
				$data['covid_question_id'] = $question_id;
			}



			if ($bat) {
				$mid = $bat->mid;

				if ($bat->cli_ID > 0) {
					unset($data['cli_ID']);
					unset($data['requesting_practice']);
				}
				if ($bat->doc_ID > 0) {
					unset($data['doc_ID']);
					unset($data['requesting_physician']);
				}
				foreach ($data as $k => $vl) {
					if (empty($vl)) {
						unset($data[$k]);
					}
				}

				$wpdb->update($batch_tbl, $data, array('mid' => $bat->mid));
			} else {
				$data['batch_created'] = date_i18n('Y-m-d H:i:s');

				$wpdb->insert($batch_tbl, $data);
				$mid = $wpdb->insert_id;

				if ($this->debug) {
					$this->log .= "Added req {$assen} => id: {$mid} \n";
					if ($mid == 0) {
						$this->log .= "err: " . $wpdb->last_error . "\n";
					}
				}
			}
			if ($mid == 0) return false;

			foreach ($custom_fields as $cfk => $cfvl) {
				trpdd_update_batch_meta($mid, $btype, $cfk, $cfvl);
			}



			$update = array(
				'mo_file_id'    => $dbfile->m_ID,
				'mo_mid'        => $mid,
				'mo_btype'        => $btype,
				'mo_control_ID' => $control_id . '',
				'mo_test_ID'    => $order_id . '',
				'mo_set_ID'     => substr($hl7_set_ID, 0, 30),
				'mo_patient_ID' => substr($hl7_patient_id, 0, 30),
				'mo_peg_ID' => substr($peg_id, 0, 30),
				'other_info' => json_encode($other_info) . '',
			);
			//var_dump($update);

			if ($orderdb && $orderdb->mo_ID > 0) {
				$r = $wpdb->update('ci_sync_api_orders', $update, array('mo_ID' => $orderdb->mo_ID));
			} else {
				$r = $wpdb->insert('ci_sync_api_orders', $update);
			}

			if ($r === false) {
				$this->log .= $wpdb->last_error;
				return false;
			}
		}

		return $mid > 0;
	}

	public function sync_reports($over_id = null, $limit = 10)
	{
		global $wpdb;
		global $has_error_build_pdf;

		$btypes = array('pcr');
		if (isset($this->options['alltypes']) && $this->options['alltypes']) {
			$btypes = tr_pdd_get_batch_types();
		}

		if (!empty($over_id)) {
			list($btype, $over_id) = explode('_', $over_id);
			$btypes = array($btype);
		}

		foreach ($btypes as $btype) {

			$batch_tbl = tr_pdd_get_table_by_btype($btype);
			$report_tbl = tr_pdd_get_table_report_by_btype($btype);
			$result_tbl = tr_pdd_get_table_result_by_btype($btype);

			$drug_pairs = array();
			if ($btype == 'tox') {
				$drug_pairs = tr_pdd_get_drug_pairs();
			}

			$hl7_dir = WP_CONTENT_DIR . '/log/sftp_emr_results/';
			if (!file_exists($hl7_dir)) {
				mkdir($hl7_dir);
			}
			$covid_tests = array(
				'NCOV-2019_N1' => array(),
				'NCOV-2019_N2' => array(),
				'NCOV-2019_N3' => array(),
				'RNASEP' => array(),
			);
			$pos_neg = array(
				'' => '',
				'NEG' => 'Not Detected',
				'POS' => 'Detected',
				'PEN' => 'Pending',
				'INC' => 'Inconclusive',
				'PRP' => 'Detected',
				'INV' => 'Invalid',
				'NGE' => 'Not Detected',
				'N/A' => 'N/A',
				'POH' => 'Detected-High',
				'POL' => 'Detected-Low',
				'POM' => 'Detected-Medium',
				'PVH' => 'Detected-Very High',
				'PVL' => 'Detected-Very Low',
			);

			$drug_arr = array();
			foreach ($covid_tests as $t => $v) {
				$drug_arr[] = $t;
			}

			$limit_approve_date = date('Y-m-d H:i:s', strtotime(date_i18n('Y-m-d H:i:s')) - 100);


			$sql = "select re.id,
					ba.mid,
					ba.batch_id,
					ba.assen,
					ba.patient_name,
					ba.doc,
					ba.spec_received,
					ba.panel_tests_requested,
					ba.type_of_specimen,
					ba.notes,
					ba.batch_status,
					
					pa.pa_first,
					pa.pa_last,
					pa.pa_address,
					pa.pa_city,
					pa.pa_state,
					pa.pa_zip,
					pa.pa_dob,
					pa.pa_ssn,
					pa.pa_gender,
					pa.pa_phone,
					pa.pa_email,
					
					re.approve_date,
					re.instrument,
					mo.mo_ID,
					mo.mo_set_ID,
					mo.mo_test_ID,
					mo.mo_control_ID,
					mo.mo_patient_ID,
					mo.mo_peg_ID,
					mo.other_info,
					mo.mo_btype,
					
					mf.m_ID,
					mf.m_file,
					lab.lab_name,
					lab.lab_clia,
					lab.lab_address,
					lab.lab_city,
					lab.lab_state,
					lab.lab_zip,
					
					cli.cli_clinic,
					cli.cli_street,
					cli.cli_city,
					cli.cli_state,
					cli.cli_zip,
					cli.`cli_om-phone` as cli_phone,
					
					doc.doc_first,
					doc.doc_last,
					doc.doc_npi
					
				FROM {$batch_tbl} ba
				JOIN {$report_tbl} re ON re.patient_id = ba.assen
				JOIN ci_sync_api_orders mo ON ba.mid = mo.mo_mid and mo.mo_btype='{$btype}'
				JOIN ci_sync_api_files mf ON mf.m_ID = mo.mo_file_id  and mf.m_type='{$this->api_id}'
				
				JOIN ci_labs lab ON lab.lab_ID = ba.lab_id or ((ba.lab_id =0 or ba.lab_id is null) and lab.lab_ID = 1)
				jOIN ci_patients pa ON pa.pa_ID = ba.patient_id
				jOIN ci_clinic cli ON cli.cID = ba.cli_ID
				JOIN ci_doc doc ON doc.doc_ID = ba.doc_ID
				where re.estatus = 'C'
				and re.approve_date < '{$limit_approve_date}'
				";

			if ($over_id > 0) {
				$sql .= " and re.id = {$over_id} ";
			} else {
				$sql .= " and mo.mo_status = 0 ";
			}
			$sql .= " limit {$limit}";
			$rs = $wpdb->get_results($sql);
			//echo $wpdb->last_query . '<br>';

			$report_files = array();

			if ($this->debug && count($rs) == 0) {
				if (!empty($wpdb->last_error)) {
					$this->log .= "error: " . $wpdb->last_error;
				}
				$this->log .= "Not found results!";
			}

			$mo_ids = array();

			foreach ($rs as $r) {
				try {


					$other_info = json_decode($r->other_info, true);

					$assen = !empty($r->oldreq) ? $r->oldreq : $r->assen;


					$batch_status = $r->batch_status;

					$groupdata = $r->id;

					if (empty($r->created_date)) {
						$r->created_date = date('Y-m-d');
					}


					$r->result_status = 'F';

					if ($batch_status == 'rejected' || strpos($batch_status, 're_') === 0) {
						$r->result_status = 'X';
					}

					$test_id = !empty($other_info['OBR41']) ? $other_info['OBR41'] : $this->covid_loinc_code;
					$test_name = !empty($other_info['OBR42']) ? $other_info['OBR42'] : 'SARS-CoV-2 rtPCR';

					if (empty($other_info['ORC12.1'])) {
						$other_info['ORC12.1'] = $r->doc_npi;
						$other_info['ORC12.2'] = $r->doc_first;
						$other_info['ORC12.3'] = $r->doc_last;
					}

					if (empty($other_info['OBR16.1'])) {
						$other_info['OBR16.1'] = $r->doc_npi;
						$other_info['OBR16.2'] = $r->doc_first;
						$other_info['OBR16.3'] = $r->doc_last;
					}

					$test_groups		= array();

					$obrtid = !empty($other_info['OBR41']) ? $other_info['OBR41'] : $test_id;
					$obrtname = !empty($other_info['OBR42']) ? $other_info['OBR42'] : $test_name;

					$bcodes = isset($other_info['OBR_code']) && count($other_info['OBR_code']) > 0 ? $other_info['OBR_code'] : array();
					if (empty($bcodes) && !empty($other_info['OBR41'])) {
						$bcodes = array($other_info['OBR41']);
					}
					$group_ids 	= array();
					$test_ids 	= array();

					foreach (explode(',', $r->panel_tests_requested) as $tid) {
						if (stripos($tid, 'group_') !== false) {
							$group_id = str_ireplace('GROUP_', '', $tid);
							if ($group_id > 0 && !in_array($group_id, $group_ids)) {
								$group_ids[] = $group_id;
							}
						} else if ($tid > 0 && !in_array($tid, $test_ids)) {
							$test_ids[] = $tid;
						}
					}
					$test_ids_str = implode(',', $test_ids);
					if (empty($test_ids_str)) {
						$test_ids_str = '-1';
					}

					if (count($bcodes) > 0) {

						$exclude_parents = array(-1);
						foreach ($bcodes as $bcode) {

							$groupdata = $wpdb->get_row("select tg_ID ,tg_name, tg_parent
							from ci_test_groups
							where ( tg_cpt_code='{$bcode}' or tg_short_name = '{$bcode}' or tg_name = '{$bcode}')
							and tg_type='{$btype}' ");
							if ($groupdata) {
								if ($groupdata->tg_parent > 0) {
									$exclude_parents[] = $groupdata->tg_ID;
								}


								if (!isset($test_groups[$bcode])) {
									$test_groups[$bcode] = array(
										'name' => $groupdata->tg_name,
										'tests' => array(),
										'exclude' => array(),
										'id' => $groupdata->tg_ID
									);
								}
							}
						}
						$exclude_parents = implode(',', $exclude_parents);

						foreach ($bcodes as $bcode) {

							$where = " and te_loinc_code = '{$bcode}' ";
							$group_id = '-1';

							if (isset($test_groups[$bcode])) {
								$group_id = $test_groups[$bcode]['id'];


								$where = " and ( 
									(tg_parent = '{$group_id}'  and tg_ID not in ({$exclude_parents}) ) 
									or tg_ID = {$group_id} 
								)
								and ( te.te_ID in ({$test_ids_str}) or g.tg_only_group=1 or g.tg_notestcheckbox=1) ";
							}

							//before query
							$wpdb->query("delete from {$result_tbl}
							where report_id = '{$r->id}'
							and conc is null 
							and `pos-neg` is null 
							and instrument is null
							and (comments is null or comments = '') ");


							$tests_rs = $wpdb->get_results("select 
							te.*,
							GROUP_CONCAT( rs.conc SEPARATOR '|') as concs,
							GROUP_CONCAT( rs.`pos-neg` SEPARATOR '|') as `pos-negs`,
							g.tg_ID, 
							g.tg_only_group,
							g.tg_short_name,
							g.tg_is_covid,
							g.tg_name,
							rs.*
							from ci_test_panels te
							JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
							JOIN {$result_tbl} rs ON (rs.drug = te.te_name or rs.drug=te.te_abrev or rs.drug=te.te_abrev2) 
													and rs.report_id = {$r->id}
							where te_type = '{$btype}' and te_in_req = 1
							{$where}
							group by te.te_ID ", ARRAY_A);
							echo $wpdb->last_error;

							if (count($tests_rs) > 0) {
								$in_panel_tests_requested = array();
								if ($btype == 'tox') {

									$ci_cutoffs = tr_pdd_get_cutoffs($btype, $r->created_date);
									$in_panel_tests_requested = tr_pdd_parse_panel_tests_requested($r->panel_tests_requested, $drug_pairs);
									$medications = tr_pdd_parse_medications($r->medications, $drug_pairs);
								}
								echo count($tests_rs) . " tests for bcode: {$bcode} \n";
								foreach ($tests_rs as $ti => $t) {

									$oldt = $t;
									$concs = explode('|', $t['concs']);
									$pos_negs = explode('|', $t['pos-negs']);
									if (count($concs) > 1 || empty($t['conc'])) {
										foreach ($concs as $k => $v) {
											$poneg = isset($pos_negs[$k]) ? $pos_negs[$k] : '';
											if ($v != '' && $poneg != '') {
												$t['conc'] = $v;
												$t['pos-neg'] = $poneg;
												break;
											}
										}
									}

									$t['test_name'] = $t['te_name'];

									if ($btype == 'blood') {
										$t = tr_pdd_parse_blood_test($t, $r->instrument, $t['tg_ID'], (array) $r);
									} else if ($btype == 'tox') {
										$t = tr_pdd_get_drug_class_comments($t, $medications, $r->matrix, $in_panel_tests_requested, $ci_cutoffs);
									} else if ($t['tg_is_covid']) {
										$arr = trpdd_cal_ncovid19(array('te_name' => $t['te_name']), $r->id, $r->instrument, $r->created_date);
										$t['pos-neg'] = $arr['pos-neg'];
									} else if ($btype == 'pcr') {
										$t['drug'] = $t['te_name'];
										$t['type_of_specimen'] = $r->type_of_specimen;
										$t = tr_pdd_parse_pcr_test($t, $r->instrument, $t['tg_ID'], $r->created_date);
									}

									if (empty($t['pos-neg']) || $t['pos-neg'] == 'PEN' || $t['pos-neg'] == 'CAN') {
										var_dump($t['drug']);
										var_dump($t['conc']);
										var_dump($oldt['conc']);
										var_dump($concs);
										var_dump($t['pos-neg']);
										echo '<br><br>';
										continue;
									}

									$bcode_group = $bcode;
									if ($t['tg_short_name'] == 'ABR' && $bcode != 'ABR') {
										$bcode_group = 'ABR';
									}

									if (!isset($test_groups[$bcode_group])) {
										$test_groups[$bcode_group] = array(
											'name' => $t['tg_name'],
											'tests' => array()
										);
									}

									$test_groups[$bcode_group]['tests'][] = array(
										'test_id' => $t['te_loinc_code'],
										'test_name' => $t['test_name'],
										'pos-neg' => $t['pos-neg'],
										'conc' => $t['conc'],
										'range' => isset($t['range']) ? $t['range'] : '',
										'unit' => $t['te_units'],
									);
								}
							}
						}
					}

					if (empty($test_groups) && $btype == 'pcr') {

						$arr = trpdd_cal_ncovid19(array('te_name' => $t['te_name']), $r->id, $r->instrument, $r->created_date);

						$test_groups[$obrtid] = array(
							'name' => $obrtname,
							'tests' => array(array(
								'test_id' => $test_id,
								'test_name' => $test_name,
								'pos-neg' => $arr['pos-neg']
							))
						);
					}


					$hl7_arr = array();
					$hl7_arr['MSH'] = $this->getMSH(
						$r->mo_control_ID,
						$r->lab_name,
						'LAB',
						'ORU',
						'R01',
						'ORU_R01',
						12,
						'igniteLIS',
						'19711',
						'AL'
					);

					$hl7_arr['PID'] = $this->getPID(
						$r->mo_set_ID,
						$r->mo_patient_ID,
						$r->pa_first,
						$r->pa_last,
						$r->pa_ssn,
						$r->pa_gender,
						$r->pa_dob,
						$r->pa_address,
						$r->pa_city,
						$r->pa_state,
						$r->pa_zip,
						$r->pa_phone,
						$r->pa_email,
						$r->pa_mrn,
						$other_info['PID10']
					);

					$hl7_arr['ORC'] = $this->getORC(
						$r->mo_test_ID,
						$assen,
						$r->doc,
						$r->lab_name,
						$other_info['ORC12.1'],
						$other_info['ORC12.2'],
						$other_info['ORC12.3']
					);

					if (empty($r->notes)) {
						//$r->notes = 'This is a Clinical Pharmacist Note';
					}
					if (!empty($r->notes)) {
						$hl7_arr['NTE'] = $this->getNTE($r->notes);
					}



					$obr_code = !empty($other_info['OBR_3']) ? $other_info['OBR_3'] : $other_info['OBR_CODE'];
					if (empty($obr_code)) {
						$obr_code = $assen;
					}

					$showed_nt1 = false;

					foreach ($test_groups as $groupcode => $garr) {

						if (empty($garr['tests'])) continue;

						$hl7_arr[] = $this->getOBR(
							$r->mo_test_ID,
							$assen,
							$r->lab_name,
							$r->doc_npi,
							$r->doc_first,
							$r->doc_last,
							$groupcode,
							$garr['name'],
							$r->approve_date,
							$r->result_status,
							$r->mo_peg_ID,
							$r->doc,
							$r->spec_received
						);

						if (!$showed_nt1) {
							//$hl7_arr['NT1'] = $this->getNT1($r->notes);
							$showed_nt1 = true;
						}

						if ($batch_status == 'rejected' || strpos($batch_status, 're_') === 0) {
							$garr['tests'] = array();
							$garr['tests'][] = array(
								'test_id' => $groupcode,
								'test_name' => $garr['name'],
								'pos-neg' => 'REJ'
							);
						}

						foreach ($garr['tests'] as $tarr) {

							$otherarr = array();

							if (in_array($btype, array('blood', 'tox'))) {
								$otherarr['obx5'] = $tarr['conc'];
								$otherarr['obx6'] = $tarr['unit'];
								$otherarr['obx7'] = $tarr['range'];
							}

							$hl7_arr[] = $this->getOBX(
								$r->lab_clia,
								$r->lab_name,
								$tarr['test_id'],
								$tarr['test_name'],
								$pos_neg[$tarr['pos-neg']],
								$r->result_status,
								$r->approve_date,
								$r->lab_address,
								$r->lab_city,
								$r->lab_state,
								$r->lab_zip,
								'',
								$otherarr
							);
						}
					}

					if ($batch_status == 'rejected' || strpos($batch_status, 're_') === 0) {
						$nte_msg = 'Rejected';
						$batch_statuses = tr_pdd_get_batch_statuses(false, true, true, $btype);
						if (isset($batch_statuses[$batch_status])) {
							$nte_msg = $batch_statuses[$batch_status];
						}
						$hl7_arr['NTE'] = $this->getNTE($nte_msg);
					}

					$has_error_build_pdf = false;
					global $ci_abx_reports;
					$ci_abx_reports = array();

					$pdf_file  = trpdd_export_results_pdf($r->id, '', false, $btype);
					if (isset($_GET['dbtime'])) {
						global $ci_time_build_pdf, $ci_time_query_pdf, $db_time_log;
						var_dump($db_time_log);
						echo 'query time: ' . $ci_time_query_pdf . '<br>';
						echo 'build pdf time: ' . $ci_time_build_pdf . '<br>';
						echo 'pdf version: ' . TRCDPDF_V . '<br>';
						echo 'pdf #2?: ' . (TRCDPDF_NEW ? 1 : 0) . '<br>';
						echo 'PDF size: ' . filesize($pdf_file) . '<br>';
						exit;
					}
					if ($has_error_build_pdf) {
						$this->log .= "Error generating PDF for assen: {$r->assen} \n";
						continue;
					}


					$hl7_arr[] = $this->getOBR(
						$r->mo_test_ID,
						$assen,
						$r->lab_name,
						$r->doc_npi,
						$r->doc_first,
						$r->doc_last,
						'CumulativePDF',
						'PDF Report',
						$r->approve_date,
						$r->result_status,
						$r->mo_peg_ID,
						$r->doc,
						$r->spec_received,
						'L'
					);

					$hl7_arr['OBX'][] = $this->getOBX(
						$r->lab_clia,
						$r->lab_name,
						'pdf',
						'PDF',
						'',
						$r->result_status,
						'',
						'',
						'',
						'',
						'',
						$pdf_file
					);

					$hl7_data = $this->array_to_hl7($hl7_arr);

					$filename = $r->assen . '.hl7';
					$hl7_file = $hl7_dir . $filename;

					if (!isset($report_files[$hl7_file])) {
						$report_files[$hl7_file] = fopen($hl7_file, 'a+');
						if (!isset($mo_ids[$hl7_file])) {
							$mo_ids[$hl7_file] = array();
						}
					}

					$old_data = file_get_contents($hl7_file);
					if (!empty($old_data)) {
						ftruncate($report_files[$hl7_file], 0);
						if (stripos($old_data, $assen) > 0 || stripos($old_data, $r->assen) > 0) {
							ftruncate($report_files[$hl7_file], 0);
						}
					}



					fputs($report_files[$hl7_file], $hl7_data . "\r\n");


					$mo_ids[$hl7_file][] = $r->mo_ID;

					$this->log .= 'report assen:' . $r->assen . "\n";
				} catch (Exception $e2) {
					var_dump($e2);
				}
			}

			if (isset($_GET['act']) && $_GET['act'] == 'rebuild') {
				return 1;
			}

			foreach ($report_files as $hl7_file => $f) {

				fclose($f);
				//echo file_get_contents($hl7_file);exit;

				$local_file = $hl7_file;
				$remote_file = $this->result_folder . '/' . basename($hl7_file);
				try {
					$this->uploadFile($local_file, $remote_file);
					if ($this->debug) {
						$this->log .= "uploaded: {$remote_file} \n";
					}

					$mo_id_str = implode(',', $mo_ids[$hl7_file]);
					$datenow = date_i18n('Y-m-d H:i:s');
					$wpdb->query("update ci_sync_api_orders set mo_status = 1, mo_sent_dt= '{$datenow}' where mo_ID in ({$mo_id_str})");
				} catch (Exception $e) {
					var_dump($e);
				}
			}
		}
		return count($report_files);
	}
}
