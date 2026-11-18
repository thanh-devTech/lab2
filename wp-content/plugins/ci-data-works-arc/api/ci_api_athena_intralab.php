<?php
include_once('ci_hl7.php');

class CI_Api_Athena_Intralab extends CI_Hl7
{
	private  $host = 'sftp.athenahealth.com';
	private  $port = 22;

	public $order_folder = '/outboundfromathena';
	public $result_folder = '/inboundintoathena';
	protected $conn;
	protected $sftp;
	protected $lab_id;
	protected $clinic_id;
	protected $doc_id;
	protected $msh6;
	protected $sendpdf;

	public $debug = false;
	public $log = '';
	public $api_id = 'intralab';
	public $api_num = -66;
	public $options = array();

	public function __construct()
	{
		$this->options = get_option('tr_api_athena_intralab_st', array());
		$this->lab_id = isset($this->options['lab_id']) ? $this->options['lab_id'] : 0;
		$this->clinic_id = isset($this->options['cli_id']) ? $this->options['cli_id'] : 0;
		$this->doc_id = isset($this->options['doc_id']) ? $this->options['doc_id'] : 0;
		$this->msh6 = isset($this->options['msh6']) ? $this->options['msh6'] : '';
		$this->sendpdf = isset($this->options['sendpdf']) ? $this->options['sendpdf'] : '';

		if (!empty($this->options['host'])) {
			$this->host = $this->options['host'];
		}
		if (!empty($this->options['port'])) {
			$this->port = $this->options['port'];
		}


		if (!function_exists('ssh2_connect')) {
			throw new Exception('function ssh2_connect not exist! 
			to install: yum install php-pecl-ssh2 then restart php');
		}
	}

	public function cron()
	{
		try {
			$this->connect();
			$this->login();
			try {
				$this->sync_reqs();
			} catch (Exception $e) {
				Tr_Session_Class::add_error('Sync Reqs: ' . $e->getMessage());
			}

			try {
				$this->sync_reports();
			} catch (Exception $e) {
				Tr_Session_Class::add_error('Sync Results: ' . $e->getMessage());
			}
		} catch (Exception $e) {
			Tr_Session_Class::add_error($e->getMessage());
		}
	}

	public function connect()
	{

		$this->conn = @ssh2_connect($this->host, $this->port);

		if (!$this->conn) {
			throw new Exception('Unable to connect');
		}
	}

	public function login()
	{
		$username = isset($this->options['username']) ? $this->options['username'] : '';
		$password = isset($this->options['password']) ? $this->options['password'] : '';

		$result = ssh2_auth_password($this->conn, $username, $password);

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
		}

		closedir($handle);
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



		if (count($files) == 0) {
			$this->log .= "No files in Orders folder!";

			try {
				ssh2_sftp_rmdir($this->sftp, $old_archive_dir);
				//ssh2_sftp_mkdir($this->sftp, $archive_dir);
			} catch (Exception $e) {
			}
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

	function get_tests_by_group($groupid)
	{
		global $wpdb;
		$groupsids = $wpdb->get_col("select tg_ID from ci_test_groups
		where tg_parent = {$groupid}");
		$groupsids[] = $groupid;
		$groupsids_str = implode(',', $groupsids);
		$testids = $wpdb->get_col("select te_ID from ci_test_panels
		where te_group_id in ({$groupsids_str}) 
		and te_in_req = 1");

		return $testids;
	}

	function get_type_by_group($groupid)
	{
		global $wpdb;
		$source = 'Nasal Swab';
		$rs = $wpdb->get_var("select ts_name from ci_test_specimens 
			where ts_group_id = {$groupid}
			order by ts_ID asc
			limit 1");

		if (!empty($rs)) {
			$source = $rs;
		}

		return $source;
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
			                and tg_type='pcr' and tg_show_req = 1 ");
		$batch_tbl = 'ci_batches_pcr';

		$groups = array();
		$rs = $wpdb->get_results("select * from ci_test_groups where tg_type = 'pcr' ");
		foreach ($rs as $r) {
			$other = !empty($r->tg_other_options) ? json_decode($r->tg_other_options, true) : array();
			$code = isset($other['athena_intralab']) ? strtoupper(trim($other['athena_intralab'])) : '';
			if (isset($this->options['use_cpt_code']) && $this->options['use_cpt_code'] && empty($code)) {
				$code = $r->tg_cpt_code;
			}

			if (!empty($code)) {
				$groups[$code] = $r->tg_ID;

				//get tests
				if ($code == 'CTNGTV') {
					$tests = $wpdb->get_results("select te_ID, te_loinc_code from ci_test_panels te
					join ci_test_groups g On g.tg_ID = te.te_group_id
					where tg_ID = {$r->tg_ID} or tg_parent = {$r->tg_ID} 
					and te_loinc_code!='' ");
					foreach ($tests as $t) {
						if (!empty($t->te_loinc_code) && !isset($groups['TEST_' . $t->te_loinc_code])) {
							$groups['TEST_' . $t->te_loinc_code] = $t->te_ID;
							$groups['TESTGROUP_' . $t->te_ID] = $r->tg_ID;
						}
					}
				}
			}
			$code2 = $r->tg_cpt_code;
			if (!empty($code2)) {
				$groups[$code2] = $r->tg_ID;
			}
		}


		$path = $this->getpath($file_path);
		$stream = @fopen($path, 'r');
		$contents = '';
		if (!$stream) return;

		while (($buffer = fgets($stream, 4096)) !== false) {
			$contents .= $buffer;
		}

		$hl7_dir = WP_CONTENT_DIR . '/log/athena_intralab_orders/';
		if (!file_exists($hl7_dir)) {
			mkdir($hl7_dir);
		}

		file_put_contents($hl7_dir . basename($file_path), $contents);

		$packages = explode('MSH|', $contents);


		$clinic_name = '';
		$clinic_id 		= $this->clinic_id;
		if ($clinic_id > 0) {
			$clinic_name = $wpdb->get_var("select cli_clinic from ci_clinic where cID = '{$clinic_id}' ");
		}

		$default_doctor_name = '';
		if ($this->doc_id > 0) {
			$doctor = $wpdb->get_row("select doc_first,doc_last from ci_doc where doc_ID = {$this->doc_id}");
			$default_doctor_name = $doctor->doc_last . ', ' . $doctor->doc_first;
		}

		foreach ($packages as $package) {
			$other_info = array();
			$package = trim($package);
			if (empty($package)) continue;
			$package = 'MSH|' . $package;
			$priority_hours  = 0;

			$arr = $this->hl7_to_array($package, array('OBR'));


			$control_id   = $arr['MSH']['MSH.10']['MSH.10.1'] ?? '';
			$hl7_set_ID   = $arr['PID']['PID.1']['PID.1.1'] ?? '';
			$hl7_patient_id = $arr['PID']['PID.3']['PID.3.1'] ?? '';
			$pa_last      = trim($arr['PID']['PID.5']['PID.5.1']) ?? '';
			$pa_first     = trim($arr['PID']['PID.5']['PID.5.2']) ?? '';
			$dob          = $arr['PID']['PID.7']['PID.7.1'] ?? '';
			$gender       = $arr['PID']['PID.8']['PID.8.1'] ?? '';
			$address      = trim($arr['PID']['PID.11']['PID.11.1'] . ' ' . $arr['PID']['PID.11']['PID.11.2']) ?? '';
			$city         = $arr['PID']['PID.11']['PID.11.3'] ?? '';
			$state        = $arr['PID']['PID.11']['PID.11.4'] ?? '';
			$zip          = $arr['PID']['PID.11']['PID.11.5'] ?? '';
			$phone        = $arr['PID']['PID.13']['PID.13.1'] ?? '';
			$email        = $arr['PID']['PID.13']['PID.13.4'] ?? '';

			$insurance      = $arr['IN1']['IN1.4']['IN1.4.1'] ?? '';
			$ins_address    = $arr['IN1']['IN1.5']['IN1.5.1'] ?? '';
			$ins_city       = $arr['IN1']['IN1.5']['IN1.5.3'] ?? '';
			$ins_state      = $arr['IN1']['IN1.5']['IN1.5.4'] ?? '';
			$ins_zip        = $arr['IN1']['IN1.5']['IN1.5.5'] ?? '';
			$ins_phone      = $arr['IN1']['IN1.7']['IN1.7.1'] ?? '';
			$ins_group_id   = $arr['IN1']['IN1.8']['IN1.8.1'] ?? '';
			$ins_group_name   = $arr['IN1']['IN1.9']['IN1.9.1'] ?? '';
			$ins_first      = $arr['IN1']['IN1.16']['IN1.16.1'] ?? '';
			$ins_last       = $arr['IN1']['IN1.16']['IN1.16.2'] ?? '';
			$ins_dob        = $arr['IN1']['IN1.18']['IN1.18.1'] ?? '';
			$ins_authorization_id = $arr['IN1']['IN1.36']['IN1.36.1'] ?? '';

			$order_status   = $arr['ORC']['ORC.1']['ORC.1.1'] ?? '';
			$order_id       = $arr['ORC']['ORC.2']['ORC.2.1'] ?? '';
			$doc_orc9       = $arr['ORC']['ORC.9']['ORC.9.1'] ?? '';

			$doc_npi        = $arr['ORC']['ORC.12']['ORC.12.1'] ?? '';
			$doc_first      = $arr['ORC']['ORC.12']['ORC.12.2'] ?? '';
			$doc_last       = $arr['ORC']['ORC.12']['ORC.12.3'] ?? '';

			$other_info['ORC12.1'] = $arr['ORC']['ORC.12']['ORC.12.1'] ?? '';
			$other_info['ORC12.2'] = $arr['ORC']['ORC.12']['ORC.12.2'] ?? '';
			$other_info['ORC12.3'] = $arr['ORC']['ORC.12']['ORC.12.3'];

			$doc_spm17	   = $arr['SPM']['SPM.17']['SPM.17.1'] ?? '';


			$tests_requested 	= array();
			$specimens 			= array();
			$obr13 				= '';
			$peg_id 			= '';
			$doc_obr 			= '';


			foreach ($arr['OBR'] as $obr) {
				if (empty($doc_obr)) {
					$doc_obr = $obr['OBR.6']['OBR.6.1'] ?? '';
				}

				$other_info['OBR16.1'] = $obr['OBR.16']['OBR.16.1'];
				$other_info['OBR16.2'] = $obr['OBR.16']['OBR.16.2'];
				$other_info['OBR16.3'] = $obr['OBR.16']['OBR.16.3'];


				$other_info['OBR41'] = trim($obr['OBR.4']['OBR.4.1']);
				$other_info['OBR42'] = trim($obr['OBR.4']['OBR.4.2']);
				$other_info['OBR7'] = trim($obr['OBR.7']['OBR.7.1']);

				$obr13 = $peg_id  	= trim($obr['OBR.13']['OBR.13.1']);
				$obr_code        	= $obr['OBR.2']['OBR.2.1'];
				$obr3 				= '';
				if (isset($obr['OBR.3']['OBR.3.1']) && !empty($obr['OBR.3']['OBR.3.1'])) {
					$obr3            = $obr['OBR.3']['OBR.3.1'];
				}

				if (stripos($obr['OBR.15']['OBR.15.1'], '12h') !== false) {
					$priority_hours = 12;
				} else if (stripos($obr['OBR.15']['OBR.15.1'], '24h') !== false) {
					$priority_hours = 24;
				}

				//get tests
				$group_code = isset($obr['OBR.4']['OBR.4.1']) ? strtoupper(trim($obr['OBR.4']['OBR.4.1'])) : '';

				echo '$group_code: ' . $group_code . '<br>';
				if (!empty($group_code)) {
					$group_specimen = '';
					//get specimen from OBR

					$obr13 = str_replace(' ', '', $obr13);

					if (isset($obr['OBR.15']['OBR.15.2']) && !empty($obr['OBR.15']['OBR.15.2']) && stripos($obr['OBR.15']['OBR.15.1'], 'LS') !== false) {
						$specimenindb = trim($obr['OBR.15']['OBR.15.2']);
						$specimenindb = $wpdb->get_var("select ts_name from ci_test_specimens 
										where ts_name = '" . esc_sql($specimenindb) . "' 
										and ts_type='pcr' ");
						if (!empty($specimenindb)) {
							$group_specimen = $specimenindb;
						}
					}

					if (isset($groups[$group_code])) {
						$gid = $groups[$group_code];
						$gkid = 'GROUP_' . $gid;
						if (!in_array($gkid, $tests_requested)) {
							$tests_requested[] = $gkid;
						}

						$tests_ids = $this->get_tests_by_group($gid);
						foreach ($tests_ids as $tid) {
							if (!in_array($tid, $tests_requested)) {
								$tests_requested[] = $tid;
							}
						}

						$other_info['panel_id'] = $groups[$group_code];
						if (empty($group_specimen)) {
							$group_specimen = $this->get_type_by_group($groups[$group_code]);
						}
					} else if (isset($groups['TEST_' . $group_code])) {
						$tid = $groups['TEST_' . $group_code];

						$grid = $groups['TESTGROUP_' . $tid];

						if ($grid > 0 && !in_array('GROUP_' . $grid, $tests_requested)) {
							$tests_requested[] = 'GROUP_' . $grid;
						}
						$tests_requested[] = $tid;
					}

					if (!empty($group_specimen)) {
						$specimens[] = $group_specimen;
					}
				}
			}




			$nte            = isset($arr['NTE']) ? $arr['NTE']['NTE.3']['NTE.3.1'] : '';

			if (empty($peg_id)) {
				$peg_id = $obr_code;
			}
			if (stripos($peg_id, ':') > 0) {
				list($t, $peg_id) = explode(':', $peg_id);
				$peg_id = trim($peg_id);
			}
			$peg_id = substr($peg_id, 0, 30);

			$speciments_in_obx = array();
			$obx_source = '';

			if ($arr['OBX']) {
				foreach ($arr['OBX'] as $obx) {
					if ($obx['OBX.5']['OBX.5.1'] == 12 || $obx['OBX.5']['OBX.5.1'] == 24) {
						$priority_hours = $obx['OBX.5']['OBX.5.1'];
					}

					if (isset($obx['OBX.3']['OBX.3.2'])) {
						if (stripos($obx['OBX.3']['OBX.3.2'], 'Specimen Type') !== false && !empty($obx['OBX.5']['OBX.5.1'])) {
							$spec = $obx['OBX.5']['OBX.5.1'];
							if ($spec == 'Dirty-catch Urine') {
								$spec = 'Dirty catch Urine';
							}
							$speciments_in_obx[] = $spec;
						}

						if (stripos($obx['OBX.3']['OBX.3.2'], 'Source') !== false && !empty($obx['OBX.5']['OBX.5.1'])) {
							$obx_source = $obx['OBX.5']['OBX.5.1'];
						}
					}
				}
			}

			if (count($speciments_in_obx) > 0) {
				$specimens = $speciments_in_obx;
			}



			$other_info['OBR_CODE'] = $obr_code;
			$other_info['OBR_3'] = $obr3;
			$other_info['NTE'] = trim($arr['NTE']['NTE.3']['NTE.3.1']);
			$other_info['MSH4'] = trim($arr['MSH']['MSH.4']['MSH.4.1'] . '^' . $arr['MSH']['MSH.4']['MSH.4.2']);
			$other_info['MSH5'] = trim($arr['MSH']['MSH.5']['MSH.5.1']);
			$other_info['MSH6'] = trim($arr['MSH']['MSH.6']['MSH.6.1'] . '^' . $arr['MSH']['MSH.6']['MSH.6.2']);
			$other_info['PID3'] = trim($arr['PID']['PID.3']['PID.3.1']);
			$other_info['PID10'] = trim($arr['PID']['PID.10']['PID.10.1']);
			$other_info['PV17'] = trim($arr['PV1']['PV1.7']['PV1.7.1']);

			$cliniccode 	= trim($arr['MSH']['MSH.4']['MSH.4.1']);
			$clinic_code2 	= isset($arr['MSH']['MSH.13']) ? trim($arr['MSH']['MSH.13']['MSH.13.1']) : '';

			if ($this->options['clinicpv1']) {
				$cliniccode = !empty($arr['PV1']['PV1.3']['PV1.3.1']) ? trim($arr['PV1']['PV1.3']['PV1.3.1']) : $cliniccode;
				$cliniccode = substr($cliniccode, 0, 2);
			}
			echo 'clinic code: ';
			var_dump($cliniccode);
			echo '<br>';
			//find clinic by code
			if (!empty($cliniccode)) {

				if (!empty($clinic_code2)) {
					$clinic = $wpdb->get_row("select cID, cli_clinic from ci_clinic 
					where cli_athena_intralab_departmentid ='{$cliniccode}'
						and cli_athena_intralab_departmentid2 ='{$clinic_code2}' ");
				}

				if (empty($clinic)) {
					$clinic = $wpdb->get_row("select cID, cli_clinic from ci_clinic where cli_athena_intralab_departmentid ='{$cliniccode}' ");
				}
				var_dump($clinic);

				if ($clinic) {
					$clinic_id = $clinic->cID;
					$clinic_name = $clinic->cli_clinic;
				}
			}
			//var_dump($clinic);

			$ref_assen =  !empty($obr_code) ? $obr_code : $peg_id;
			if (isset($this->options['mergeobr3']) && $this->options['mergeobr3']) {
				$ref_assen = $obr3;
			}

			preg_match("/^([0-9]+)/", $ref_assen, $ref_matches);
			if (isset($ref_matches[1]) && !empty($ref_matches[1])) {
				$ref_assen = $ref_matches[1];
			}

			if (empty($order_id)) {
				$this->log .= " empty $order_id";
				return false;
			}

			//fix date

			$dob    = date('Y-m-d', strtotime($dob));

			if (strtotime($doc_spm17) < 1000) {
				$doc_spm17 = '';
			} else {
				$doc_spm17 = preg_replace("/-[0-9]+/", "", $doc_spm17);
			}
			if (strtotime($doc_obr) < 1000) {
				$doc_obr = '';
			} else {
				$doc_obr = preg_replace("/-[0-9]+/", "", $doc_obr);
			}
			if (strtotime($doc_orc9) < 1000) {
				$doc_orc9 = '';
			} else {
				$doc_orc9 = preg_replace("/-[0-9]+/", "", $doc_orc9);
			}


			$doc = date_i18n('Y-m-d H:i:s');
			if (!empty($doc_spm17)) {
				$doc = date('Y-m-d H:i:s', strtotime($doc_spm17));
				$this->log .= " Using SPM.17 date: {$doc_spm17} => {$doc} \n";
			} else if (!empty($doc_obr)) {
				$doc = date('Y-m-d H:i:s', strtotime($doc_obr));
				$this->log .= " Using OBR.9 date: {$doc_obr} => {$doc} \n";
			} else if (!empty($doc_orc9)) {
				$doc = date('Y-m-d H:i:s', strtotime($doc_orc9));
				$this->log .= " Using ORC.9 : {$doc_orc9} => {$doc} \n";
			}


			$gender = stripos($gender, 'u') !== false ? 'U' : $gender;
			$gender = stripos($gender, 'f') !== false ? 'F' : 'M';

			$orderdb = $wpdb->get_row("select * from ci_sync_api_orders 
							where mo_test_ID='{$order_id}'
							      and mo_file_id = '{$dbfile->m_ID}' ");
			$mid = $orderdb && $orderdb->mo_mid > 0 ? $orderdb->mo_mid : 0;
			$bat = null;


			if ($mid > 0) {
				$bat = $wpdb->get_row("select * from ci_batches_pcr where mid = {$mid} ");
			}

			if (isset($_REQUEST['act']) && $_REQUEST['act'] == 'reimport' && $bat && isset($this->options['mergeobr2']) && $this->options['mergeobr2']) {
				$obr3_ref_assen = $obr3;
				preg_match("/^([0-9]+)/", $obr3_ref_assen, $ref_matches);
				if (isset($ref_matches[1]) && !empty($ref_matches[1])) {
					$obr3_ref_assen = $ref_matches[1];
				}

				if ($ref_assen == $bat->oldreq) {
					//no change
				} else if ($obr3_ref_assen != $bat->oldreq) {
					$bat = null;
				} else {
					$bat->panel_tests_requested = '';
					$bat->type_of_specimen = '';
				}
			}

			if (!$bat && !empty($ref_assen) && strlen($ref_assen) > 6) {
				if (isset($this->options['mergeobr3']) && $this->options['mergeobr3']) {
					$bat = $wpdb->get_row("select * from ci_batches_pcr where oldreq = '{$ref_assen}' ");
				} else if (isset($this->options['mergeobr2']) && $this->options['mergeobr2']) {
					$bat = $wpdb->get_row("select * from ci_batches_pcr where oldreq = '{$ref_assen}' ");
				}
			}

			if (!$bat) {
				$assen = '';
				if (stripos($other_info['NTE'], 'rpb') === 0) {
					$assen = $other_info['NTE'];
				} else if (isset($arr['NTE'])) {
					$assen = $this->findinarr($arr['NTE'], 'rpb');
				}
				if (isset($arr['IN1']) && empty($assen)) {
					$assen = $this->findinarr($arr['IN1'], 'rpb');
				}

				if (empty($assen)) {
					$assen = tr_get_new_assen();
				} else {
					//check assen exist
					$bat = $wpdb->get_row("select * from ci_batches_pcr where assen = '{$assen}'");
				}

				if (empty($assen)) {
					continue;
				}
			} else {
				$assen = $bat->assen;
				//merge $tests_requested
				if (!empty($bat->panel_tests_requested)) {
					$old_tests = explode(',', $bat->panel_tests_requested);
					foreach ($old_tests as $ot) {
						if (!in_array($ot, $tests_requested)) {
							$tests_requested[] = $ot;
						}
					}
				}
				if (!empty($bat->type_of_specimen)) {
					$old_types = explode(',', $bat->type_of_specimen);
					foreach ($old_types as $ot) {
						if (!in_array($ot, $specimens)) {
							$specimens[] = $ot;
						}
					}
				}
			}


			$tests_requested_str = implode(',', $tests_requested);
			$specimens_str = implode(',', array_unique($specimens));

			if (empty($tests_requested_str) && $group_covid_id > 0) {
				//$tests_requested_str =  'GROUP_' . $group_covid_id;
				if (empty($specimens)) {
					$specimens[] = 'Nasal Swab';
				}
			}

			$this->log .= "assen: {$assen} \n";

			$patientid = $bat && $bat->patient_id > 0 ? $bat->patient_id : 0;
			if ($patientid == 0) {

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

			$ins_id = $bat && $bat->insurance1 > 0 ? $bat->insurance1 : 0;
			if ($ins_id == 0) {
				if (empty($ins_first)) {
					$ins_first = $pa_first;
					$ins_last = $pa_last;
					$insurance = 'No Insurance - Cash';
				}
				$ins_id = $wpdb->get_var("select ins_ID from ci_insurances 
								where ins_first='{$ins_first}' and ins_last = '{$ins_last}' 
								and ins_company='{$insurance}' and pa_ID = {$patientid} ");
				$ins_arr = array(
					'ins_first' => $ins_first,
					'ins_last' => $ins_last,
					'ins_relation' => 'Self',
					'ins_company' => $insurance . '',
					'ins_address' => $ins_address . '',
					'ins_city' => $ins_city . '',
					'ins_state' => $ins_state . '',
					'ins_zip' => $ins_zip . '',
					'ins_phone' => $ins_phone . '',
					'ins_group_id' => $ins_group_id . '',
					'ins_authorization_id' => $ins_authorization_id . '',
					'ins_dob' => $ins_dob . '',
					'pa_ID' => $patientid . '',

				);
				if ($ins_id == 0) {
					$wpdb->insert('ci_insurances', $ins_arr);
					$ins_id = $wpdb->insert_id;
					if ($this->debug) {
						$this->log .= "Added new Insurance: {$insurance} => {$ins_id}\n";
					}
				}
			}

			$doc_id = $this->doc_id;
			$doctor_name = $default_doctor_name;
			if (!empty($doc_first) && !empty($doc_last)) {
				$doc_first = ucwords(strtolower($doc_first));
				$doc_last = ucwords(strtolower($doc_last));

				$sql_doctor = $wpdb->prepare("select doc_ID from ci_doc where  
										 doc_first=%s  
										    and doc_last=%s 
										  and doc_npi = %s ", $doc_first, $doc_last, $doc_npi);
				$doc_id = $wpdb->get_var($sql_doctor);

				if ($doc_id == 0) {
					$doc_id = $wpdb->get_var("select doc_ID from ci_doc where doc_npi = '{$doc_npi}'");
				}
				if ($doc_id == 0) {
					$wpdb->insert('ci_doc', array('doc_first' => $doc_first, 'doc_last' => $doc_last, 'doc_npi' => $doc_npi));
					$doc_id = $wpdb->insert_id;
					if ($this->debug) {
						$this->log .= "Added new doctor: {$doc_first} {$doc_last}\n";
					}
				}
				$doctor_name = $doc_last . ', ' . $doc_first;
			}



			if ($doc_id > 0 && $clinic_id > 0) {
				$wpdb->insert('ci_clinic_doc', array('doc_ID' => $doc_id, 'cli_ID' => $clinic_id));
			}




			$dagnostic_codes = array();
			if (isset($arr['DG1'])) {
				foreach ($arr['DG1'] as $dg1) {
					if (!empty($dg1['DG1.3']['DG1.3.1'])) {
						$dagnostic_codes[] = $dg1['DG1.3']['DG1.3.1'];
					}
				}
			}

			$dagnostic_codes = implode(',', $dagnostic_codes);




			$patient_name = $pa_last . ', ' . $pa_first;
			$data         = array(
				'assen'                => $assen,
				'patient_id'           => $patientid,
				'patient_name'         => $patient_name,
				'lab_ID'               => $this->lab_id,
				'doc_ID'               => intval($doc_id),
				'cli_ID'               => intval($clinic_id),
				'doc'                  => $doc,
				'dob'                  => $dob,
				'requesting_physician' => $doctor_name . '',
				'requesting_practice'  => $clinic_name . '',
				'type_of_specimen'     => $specimens_str,
				'dagnostic_codes1'     => $dagnostic_codes,
				'panel_tests_requested' => $tests_requested_str,
				'insurance'             => $insurance . '',
				'insurance1'             => intval($ins_id),
				'notes'                 => $nte . '',
				'oldreq'                 => $ref_assen . '',
				'patient_signature'      => 'onfile',
				'paid'                   => $this->api_num,
			);
			if ($priority_hours > 0) {
				$data['priority_hours'] = $priority_hours;
			}

			if ($order_status === 'CA') {
				$data['batch_status'] = 'archived';
			}

			//$data['batch_id'] = date('Ymd',strtotime($data['doc'])).'-1';

			if ($bat) {
				$mid = $bat->mid;
				//$_GET['updateclinic'] = $_GET['updatedoctor'] = true;
				if ($bat->cli_ID > 0 && !isset($_GET['updateclinic'])) {
					//unset($data['cli_ID']);
					//	unset($data['requesting_practice']);
				}
				if ($bat->doc_ID > 0 && !isset($_GET['updatedoctor'])) {
					//unset($data['doc_ID']);
					//unset($data['requesting_physician']);
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

			//update meta source
			trpdd_update_batch_meta($mid, 'pcr', '_obx_source', $obx_source);

			$update = array(
				'mo_file_id'    => $dbfile->m_ID,
				'mo_mid'        => $mid,
				'mo_btype'        => 'pcr',
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

	public function sync_reports($over_id = 0)
	{
		global $wpdb;
		$btype = 'pcr';
		$batch_tbl = 'ci_batches_pcr';
		$report_tbl = 'ci_reports_pcr';

		$hl7_dir = WP_CONTENT_DIR . '/log/athena_intralab/';
		if (!file_exists($hl7_dir)) {
			mkdir($hl7_dir);
		}
		$pos_neg = array(
			'' => '',
			'NEG' => 'Not Detected',
			'POS' => 'Detected',
			'POL' => 'Detected-Low',
			'POH' => 'Detected-High',
			'POM' => 'Detected-Medium',
			'PVL' => 'Detected-Very Low',
			'PVH' => 'Detected-Very High',
			'PEN' => 'Pending',
			'INC' => 'Inconclusive',
			'PRP' => 'Positive',
			'INV' => 'Invalid',
			'NGE' => 'Negative',
			'REJ' => 'Rejected'
		);

		$where = '';
		if ($over_id > 0) {
			$where .= " and re.id = {$over_id} ";
		} else {
			$where .= " and (mo.mo_status = 0 or mo.mo_sent = 2) ";
		}

		$sql = "select re.id,
					ba.mid,
					ba.batch_id,
					ba.assen,
					ba.patient_name,
					ba.doc,
					ba.spec_received,
					ba.panel_tests_requested,
					ba.batch_status,
					
					pa.pa_first,
					pa.pa_last,
					pa.pa_address,
					pa.pa_city,
					pa.pa_state,
					pa.pa_zip,
					pa.pa_dob,
					pa.pa_ssn,
					pa.pa_mrn,
					pa.pa_gender,
					pa.pa_phone,
					pa.pa_email,
					
					re.id,
					re.approve_date,
					re.created_date,
					re.instrument,
					re.has_uti,
					re.final_results,
					
					mo.mo_ID,
					mo.mo_set_ID,
					mo.mo_test_ID,
					mo.mo_control_ID,
					mo.mo_patient_ID,
					mo.mo_peg_ID,
					mo.other_info,
					mo.mo_sent,
					mo.mo_sent_dt,
					
					mf.m_ID,
					mf.m_file,
					lab.lab_name,
					lab.lab_clia,
					
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
				JOIN ci_sync_api_orders mo ON ba.mid = mo.mo_mid 
				JOIN ci_sync_api_files mf ON mf.m_ID = mo.mo_file_id  and mf.m_type='{$this->api_id}'
				JOIN {$report_tbl} re ON re.patient_id = ba.assen
				
				JOIN ci_labs lab ON lab.lab_ID = ba.lab_id or ((ba.lab_id =0 or ba.lab_id is null) and lab.lab_ID = 1)
				jOIN ci_patients pa ON pa.pa_ID = ba.patient_id
				jOIN ci_clinic cli ON cli.cID = ba.cli_ID
				JOIN ci_doc doc ON doc.doc_ID = ba.doc_ID
				where re.estatus = 'C' {$where}
				group by ba.mid
				limit 20
				";


		$rs = $wpdb->get_results($sql);

		$report_files = array();

		if ($this->debug && count($rs) == 0) {
			if (!empty($wpdb->last_error)) {
				$this->log .= "error: " . $wpdb->last_error;
			}
			$this->log .= "Not found results!";
		}

		$group_covid_id = $wpdb->get_var("select tg_ID from ci_test_groups 
			                where (tg_is_covid=1 or tg_name ='nCoV-2019') 
			                and tg_type='pcr' and tg_show_req = 1 ");




		foreach ($rs as $r) {

			$mo_orders = array($r->mo_ID => $r);
			$orders = $wpdb->get_results("select mo.* from ci_sync_api_orders mo
				JOIN ci_sync_api_files mf ON mf.m_ID = mo.mo_file_id  
											and mf.m_type='{$this->api_id}'
				where mo.mo_mid = {$r->mid} 
				order by mo.mo_ID asc");

			$order0 = $orders[0];


			if (count($orders) > 1) {

				$other_info = json_decode($order0->other_info, true);
			} else {
				$other_info = json_decode($r->other_info, true);
			}


			$assen 		= !empty($r->oldreq) ? $r->oldreq : $r->assen;
			$nte_code 	= !empty($other_info['NTE']) ? $other_info['NTE'] : $assen;



			$r->result_status = 'F';
			if ($r->batch_status == 'rejected' || strpos($r->batch_status, 're_') === 0) {
				$r->result_status = 'X';
			}

			if ($r->final_results == 2) {
				$r->result_status = 'P';
			} else if ($r->final_results == 3) {
				$r->result_status = 'A';
			} else if (!empty($r->mo_sent_dt) && $r->mo_sent_dt != '0000-00-00 00:00:00' && $r->final_results == 1) {
				$r->result_status = 'C';
			}




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
			$msh6 = !empty($this->msh6) ? $this->msh6 : $other_info['MSH4'];
			list($other_info['MSH6']) = explode('^', $other_info['MSH6']);
			$msh4 = trim($other_info['MSH6'], '^');
			$msh6 = trim($msh6, '^');


			$hl7_arr = array();
			$hl7_arr['MSH'] = $this->getMSH(
				$r->mo_control_ID,
				$msh4,
				'LAB',
				'ORU',
				'R01',
				'ORU_R01',
				12,
				'ATHENANET',
				$msh6,
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

			if (count($orders) > 1) {
				foreach ($orders as $cio => $corder) {
					if ($cio > 0) {
						$mo_orders[$corder->mo_ID] = $corder;

						$c_other_info = json_decode($corder->other_info, true);
						$obr_code = $assen;
						$ctest_id = !empty($c_other_info['OBR41']) ? $c_other_info['OBR41'] : '';
						$ctest_name = !empty($c_other_info['OBR42']) ? $c_other_info['OBR42'] : '';

						if (empty($ctest_name)) continue;

						$hl7_arr[] = $this->getORC(
							$corder->mo_test_ID,
							$assen,
							$r->doc,
							$r->lab_name,
							$c_other_info['ORC12.1'],
							$c_other_info['ORC12.2'],
							$c_other_info['ORC12.3']
						);

						$hl7_arr[] = $this->getOBR(
							$corder->mo_test_ID,
							$obr_code,
							$r->lab_name,
							$other_info['OBR16.1'],
							$other_info['OBR16.2'],
							$other_info['OBR16.3'],
							$ctest_id,
							$ctest_name,
							$r->approve_date,
							$r->result_status,
							$corder->mo_peg_ID,
							$r->doc
						);
					}
				}
			}


			$test_id = !empty($other_info['OBR41']) ? $other_info['OBR41'] : '94500-6';
			$test_name = !empty($other_info['OBR42']) ? $other_info['OBR42'] : 'nCoV-2019';

			$hl7_arr[] = $this->getORC(
				$order0->mo_test_ID,
				$assen,
				$r->doc,
				$r->lab_name,
				$other_info['ORC12.1'],
				$other_info['ORC12.2'],
				$other_info['ORC12.3']
			);

			if ($r->has_uti && !empty($this->options['nte_message'])) {
				$nte_code = $this->options['nte_message'];
			}

			$hl7_arr[] = $this->getNTE($nte_code);

			$obr_code = !empty($other_info['OBR_3']) ? $other_info['OBR_3'] : $other_info['OBR_CODE'];
			$obr_code = $assen;


			$hl7_arr[] = $this->getOBR(
				$order0->mo_test_ID,
				$obr_code,
				$r->lab_name,
				$other_info['OBR16.1'],
				$other_info['OBR16.2'],
				$other_info['OBR16.3'],
				$test_id,
				$test_name,
				$r->approve_date,
				$r->result_status,
				$order0->mo_peg_ID,
				$r->doc
			);

			$pdf_file = '';
			if ($this->sendpdf) {
				$pdf_file  = trpdd_export_results_pdf($r->id, '', false, $btype);
			}

			$hl7_arr['OBX'] = array();


			if ($r->batch_status == 'rejected' || strpos($r->batch_status, 're_') === 0) {

				$otherarr = array();

				if ($r->has_uti && !empty($this->options['disableobx8'])) {
					$otherarr['obx8'] = '';
				}

				$reason_rejected = tr_pdd_get_batch_statuses($r->batch_status, true, false, $btype);
				$reason_rejected = str_ireplace('Rejected - ', '', $reason_rejected);
				$hl7_arr['OBX'][] = $this->getOBX(
					$r->lab_clia,
					$r->lab_name,
					'REJECTED',
					$reason_rejected,
					$pos_neg['REJ'],
					$r->result_status,
					'',
					'',
					'',
					'',
					'',
					'',
					$otherarr
				);
			} else {

				$has_covid = $group_covid_id > 0 ? stripos($r->panel_tests_requested, 'GROUP_' . $group_covid_id) !== false : false;

				if ($has_covid && $other_info['panel_id'] == 0) {
					$arr = trpdd_cal_ncovid19(array(), $r->id, $r->instrument, $r->created_date);
					$hl7_arr['OBX'][] = $this->getOBX(
						$r->lab_clia,
						$r->lab_name,
						$test_id,
						$test_name,
						$pos_neg[$arr['pos-neg']],
						$r->result_status,
						'',
						'',
						'',
						'',
						'',
					);
				}

				if ($other_info['panel_id'] > 0 || !$has_covid) {

					$report = tr_pdd_get_results_by_report($r->id, 'pcr');

					foreach ($report['all_groups'] as $groupid => $groupdata) {

						foreach ($groupdata['tests'] as $arr) {

							if ($arr['te_hide_pdf'] || $arr['te_is_control'] || empty($arr['pos-neg'])) continue;

							$otherarr = array();

							if ($r->has_uti && !empty($this->options['disableobx8'])) {
								$otherarr['obx8'] = '';
							}

							$hl7_arr['OBX'][] = $this->getOBX(
								$r->lab_clia,
								$r->lab_name,
								$arr['te_abrev'],
								$arr['te_name'],
								$pos_neg[$arr['pos-neg']],
								$r->result_status,
								'',
								'',
								'',
								'',
								'',
								'',
								$otherarr
							);
						}
					}
				}
			}

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

			$report_files[] = $hl7_file;

			file_put_contents($hl7_file, $hl7_data);

			if (isset($_GET['act2']) && $_GET['act2'] == 'rebuild') {
				continue;
			}


			try {
				$datetimenow = date_i18n('Y-m-d H:i:s');
				$remote_file = $this->result_folder . '/' . basename($hl7_file);
				$this->uploadFile($hl7_file, $remote_file);
				if ($this->debug) {
					$this->log .= "uploaded: {$remote_file} \n";
				}


				foreach ($mo_orders as $mo_ID => $order) {
					$other_info = !empty($order->other_info) ? json_decode($order->other_info, true) : array();
					if (!isset($other_info['sent'])) {
						$other_info['sent'] = array();
					}
					$other_info['sent'][] = $datetimenow;
					$wpdb->update('ci_sync_api_orders', array(
						'mo_status' => 1,
						'mo_sent' => 1,
						'mo_sent_dt' => $datetimenow,
						'other_info' => json_encode($other_info)
					), array('mo_ID' => $mo_ID));
				}
			} catch (Exception $e) {
				var_dump($e);
			}
		}


		return count($report_files);
	}
}
