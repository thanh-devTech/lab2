<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

//open port 8270:  iptables -A INPUT -m state --state NEW -p tcp --dport 8270 -j ACCEPT
//service iptables save
// cd /mnt/data/vhosts/default/wp-content/plugins/ci-data-works-arc/run/
// nohup zts-php immtox_120.php port 8270 > out.txt 2> error.txt < /dev/null &


if (!isset($argv[1])) {
	if ($argv[1] == 'phpinfo') {
		phpinfo();
		exit;
	} else if ($argv[1] != 'port') {
		echo 'exit';
		exit;
	}
}

const API_VERSION = '1.1.3';
const API_NAME = 'immtox_120';
const API_ID = -81;
const DEBUG = true;



$port       = $argv[2] > 80 ? $argv[2] : 8270;

$config_dir = dirname(dirname(dirname(dirname(__FILE__)))) . '/config/';
$config_file = $config_dir . 'immtox_120_' . $port . '.php';
if (!file_exists($config_file)) {
	$config_file = $config_dir . 'immtox_120.php';
}

require($config_file);
require("db.php");




if (!isset($dbconfig) || !is_array($dbconfig)) {
	$dbconfig = array();
	echo 'Not found config file';
	exit;
}
if (!isset($dbconfig['time_zone']) || empty($dbconfig['time_zone'])) {
	$dbconfig['time_zone'] = 'America/Detroit';
}
date_default_timezone_set($dbconfig['time_zone']);


class IntrumentServer
{

	public $dbconfig = array();
	public $port;
	public $log_path;
	public $pid_file;
	public $stop_file;
	public $log_file;
	public $CHARS;

	private $sockServer;

	public $debug = false;

	public function __construct($port, $log_path, $dbconfig)
	{
		$this->log_path = $log_path;
		$this->dbconfig = $dbconfig;
		$this->port = $port;
		$this->CHARS = array(
			'SOH' => chr(0x01),
			'STX' => chr(0x02),
			'ETX' => chr(0x03),
			'EOT' => chr(0x04),
			'ENQ' => chr(0x05),
			'ACK' => chr(0x06),
			'NAK' => chr(0x15),
			'ETB' => chr(0x17),
			'LF' => chr(0x0A),
			'VT' => chr(0x0B),
			'FS' => chr(0x1C),
			'CR' => chr(0x0D),
		);

		$this->pid_file = $log_path . '/pid_' . $this->port . '.txt';
		$this->stop_file = $log_path . '/stop_' . $this->port . '.txt';
		$this->log_file = $log_path . '/log_' . $this->port . '.txt';

		if (file_exists($this->stop_file)) {
			unlink($this->stop_file);
		}
		file_put_contents($this->pid_file, '');
	}

	public function create()
	{
		printf(" - creating socket...");

		$this->sockServer = stream_socket_server("tcp://0.0.0.0:{$this->port}", $errno, $errstr);
		if (!$this->sockServer) {
			return false;
		}
		return true;
	}

	public function setDebug($debug)
	{
		$this->debug = $debug;
	}

	public function add_pid($pid)
	{
		try {
			$f = fopen($this->pid_file, 'a+');
			fwrite($f, $pid . "\n");
			fclose($f);
		} catch (Exception $e) {
		}
	}

	public function testdb()
	{
		$db = new DB($this->dbconfig);
		$rs = $db->connect();
		$db->disconnect();
		return $rs;
	}

	public function on_ssa_error($errno, $errstr)
	{
		if ($errno === 2 && stripos($errstr, 'accept failed: Connection timed out') !== false) return;
		echo date('Y-m-d H:i:s') . " -  ERROR [$errno]: $errstr\n";
	}

	public function listen()
	{
		if (!$this->sockServer) return false;

		$clients = array();

		$this->add_pid(posix_getpid());
		set_error_handler(array($this, 'on_ssa_error'));
		$count_client = 1;
		while (true) {
			try {

				$address = '';
				//echo 'waiting...' . $count_client . "\n";
				if (($msgsock = stream_socket_accept($this->sockServer, 9999, $address)) === false) {
					restore_error_handler();
					//echo 'address:' . $address . "\n";
					continue;
				}

				if (strpos($address, ':') > 0) {
					list($address, $rmport) = explode(':', $address);
				}

				//check if kill file exit
				if (file_exists($this->stop_file)) {
					foreach ($clients as $client) {
						$client->stop();
					}

					stream_socket_shutdown($msgsock, STREAM_SHUT_WR);
					break;
				}

				if (!$this->checkwhiteip($address)) {
					stream_socket_shutdown($msgsock, STREAM_SHUT_WR);
					echo date('Y-m-d H:i:s') . " deny:{$address}\n";
					continue;
				}


				$clients[] = new Client_Thread($msgsock, $this, $count_client);

				$count_client++;
			} catch (Exception $e) {
				echo 'error:';
				var_dump($e);
				echo "\n";
				foreach ($clients as $client) {
					$client->stop();
				}
			}
		}

		stream_socket_shutdown($this->sockServer, STREAM_SHUT_WR);
		echo date('Y-m-d H:i:s') . ' - Closed server' . "\n";
	}



	function checkwhiteip($ip)
	{
		global $config_dir;
		if (empty($config_dir)) {
			echo 'no config white ip';
			return true;
		}
		if ($ip == '107.172.218.216') { //test ip
			return true;
		}
		$wip_file = $config_dir . '/whiteips.txt';
		if (file_exists($wip_file)) {
			$list = file_get_contents($wip_file);
			if (!empty($list)) {
				$list = explode("\r", $list);
				foreach ($list as $wip) {
					$wip = trim($wip);
					if ($wip == $ip) {
						return true;
					}
				}
				return false;
			}
		}
		return true;
	}
}
if (!class_exists('Volatile')) {
	class Volatile {}
}
if (!class_exists('Thread')) {
	class Thread
	{

		public $no_thread = true;
	}
}

class Client_Thread extends \Thread
{

	private $msgsock = null;
	private $server;
	private $address;
	private $need_stop = false;
	private $client_id = null;
	private $is_test = false;
	private $is_order_ins = false;
	private $db;
	private $last_sent_order_mid = 0;

	public function __construct($msgsock, $server, $clientid)
	{
		$this->address = stream_socket_get_name($msgsock, true);
		if (strpos($this->address, ':') > 0) {
			list($this->address, $rmport) = explode(':', $this->address);
		}
		$this->msgsock 	= $msgsock;
		$this->server 	= $server;
		$this->client_id = $clientid;
		$this->is_order_ins = $this->server->port == $this->server->dbconfig['order_port'];

		$max_num = isset($this->server->dbconfig['max_bytes_per_frame']) ? $this->server->dbconfig['max_bytes_per_frame'] : 0;



		if (isset($this->no_thread) && $this->no_thread) {
			if (function_exists('pcntl_fork')) {
				$pid = pcntl_fork();
				$server->add_pid(posix_getpid());
				$server->add_pid($pid);
				if ($pid == -1) {
					// Fork failed
					echo "pcntl_fork() failed\n";
					socket_close($msgsock); // Close the client socket in the parent process
				} elseif ($pid == 0) {
					// Child process: handle the client
					//echo 'Forked' . "\n";
					$this->run();
					//echo 'exit' . "\n";
					die();
				} else {
					sleep(1);
					//echo 'parent' . "\n";
					// Parent process: continue listening for new connections
				}
			} else {
				$this->run();
			}
		} else {
			$this->start();
		}
	}

	public function run()
	{
		$this->db = new DB($this->server->dbconfig);

		$buf            = '';
		$data           = '';
		$last_buf       = '';
		$msg_started    = false;
		$count_read_fail = 0;


		try {
			date_default_timezone_set('America/Detroit');
			echo date('Y-m-d H:i:s') . " - Client connected - {$this->address} #{$this->client_id}\n";
			//	$this->log("<NL><NL>".date('H:i:s')." - Client connected - {$this->address} #{$this->client_id}:<NL>");
		} catch (Exception $e) {
		}

		if ($this->is_order_ins) {
			$this->check_send_orders();
		}


		do {
			try {

				if ($this->need_stop) {
					break;
				}

				$read = [$this->msgsock];
				$write = null;
				$except = null;

				if (stream_select($read, $write, $except, 300)) {
					if (false === ($buf = fread($this->msgsock, 1))) {
						$count_read_fail++;
						echo date('Y-m-d H:i:s') . " - {$this->address} #{$this->client_id} - error: Read false\n";
						if ($count_read_fail > 10) {
							break;
						} else {
							continue;
						}
					}
				} else {
					//run each 5mins
					//echo date('Y-m-d H:i:s') . " - {$this->address} #{$this->client_id} - error: stream_select timeout\n";
					if ($this->is_order_ins) {
						$this->check_send_orders();
					}
					continue;
				}

				if (strlen($buf) == 0) {
					if (feof($this->msgsock)) {
						break;
					}
					//echo date('Y-m-d H:i:s')." - {$this->address} #{$this->client_id} - buf empty\n";
					continue;
				}



				if ($this->server->CHARS['VT'] == $buf) {
					$msg_started = true;
					$data = '';
					continue;
				}


				if ($msg_started && ($this->server->CHARS['FS'] == $last_buf) && $this->server->CHARS['CR'] == $buf) {
					$msg_started    = false;
					$created        = date('Y-m-d H:i:s');


					//parse data
					$arr = $this->hl7_to_array($data);

					if ($this->server->debug) {
						$this->log("<NL><NL><NL>{$created} - {$this->address} #{$this->client_id} =>Rev:<NL>");
						$this->log($this->server->CHARS['VT'] . $data . $buf . "<NL>");
						//$this->log("<NL>".json_encode($arr)."<NL>");
					} else {
						touch($this->server->log_file);
					}

					$stype = isset($arr['MSH']) ? $arr['MSH']['MSH.9']['MSH.9.1'] : '';

					$datetime = isset($arr['MSH']['MSH.7']['MSH.7.1']) ? $arr['MSH']['MSH.7']['MSH.7.1'] : '';
					if (!empty($datetime)) {
						$datetime = date('Y-m-d', strtotime($datetime));
					}

					switch ($stype) {

						case 'OUL':

							$assen = isset($arr['SAC']) ? trim($arr['SAC']['SAC.2']['SAC.2.1']) : '';
							if (empty($assen)) {
								$assen = trim($arr['PID']['PID.3']['PID.3.1']);
							}

							$qc_component1 = isset($arr['SAC']) ? trim($arr['SAC']['SAC.11']['SAC.11.1']) : '';
							$qc_component2 = isset($arr['SAC']) ? trim($arr['SAC']['SAC.11']['SAC.11.2']) : '';

							$is_qc = false;
							if (empty($assen) && !empty($qc_component1) && !empty($qc_component2)) {
								$is_qc = true;
							}

							$ack_status = 'AA';

							if (!empty($assen)) {
								$btype = 'tox';
								$mid = $this->db->get_mid($assen, $btype);
								$count_rs = 0;
								if ($this->server->debug) {
									$this->log("{$assen}: {$mid}<NL>");
								}

								$report_id = 0;
								$report = $this->db->get_report($assen, $btype);
								if (!$report) {
									$report_id = $this->db->create_report($assen, $btype, API_NAME);
								} else if ($report['estatus'] == 'C' && in_array($report['final_results'], array(1, 3))) {
									$approvedtype = $report['final_results'] == 3 ? 'Amended' : 'Final';
									$this->log("<NL>Report is approved as {$approvedtype}<NL>");
									$report_id = 0;
								} else {
									$report_id = $report['id'];
									$this->db->update_report($assen, $btype, API_NAME);
								}

								if ($report_id > 0) {
									$this->log("reportID: {$report_id} <NL>");

									$logarr = array();

									$testids = $this->db->get_all_tests_ids($btype, 'u_immtox_120_code');

									foreach ($arr['OBX'] as $j => $obx) {
										$test_num = $obx["OBX.3"]["OBX.3.1"];

										$conc = $obx["OBX.5"]["OBX.5.1"];
										$flag = $obx["OBX.8"]["OBX.8.1"];


										$testarr = isset($testids[$test_num]) ? $testids[$test_num] : array();
										if (DEBUG) {
											$this->log("{$test_num}=> {$testarr['drug']} ({$testarr['ID']})=> conc: {$conc}<NL>");
										}


										if ($testarr) {
											$sql = "INSERT INTO ci_result_test_speciments (mid,testid,conc,api_type)
												values ('{$mid}','{$testarr['ID']}','{$conc}','" . API_ID . "')
												ON DUPLICATE KEY UPDATE conc = '{$conc}', api_type = " . API_ID;
											$rs = $this->db->query($sql);

											if ($rs === false) {
												$logarr[$testarr['ID']] = array($conc, 0);
												$this->log("<NL>insert data error for mid :{$mid} => {$sql}<NL>");
											} else {
												$logarr[$testarr['ID']] = array($conc, 1);
												$count_rs++;
											}
										}
									}

									$this->db->set_received_result($btype, $mid, API_ID, $logarr);

									if (!$this->is_test && $count_rs > 0) {
										$this->db->reverse_report($assen, $btype);
									}
								}
							} else if ($is_qc) {
								//check QC
								$this->log("<NL>QC result: {$qc_component1} => {$qc_component2}<NL>");
								if ($qc_component1 == 81) {
									$testids = $this->db->get_all_tests_ids('tox', 'u_immtox_120_code');
									foreach ($arr['OBX'] as $j => $obx) {
										$test_num = $obx["OBX.3"]["OBX.3.1"];
										$conc = $obx["OBX.5"]["OBX.5.1"];
										$testarr = isset($testids[$test_num]) ? $testids[$test_num] : array();

										$patientid 		= $qc_component2 % 2 == 0 ? 'HQC' : 'LQC';
										if ($testarr) {
											//check pos/neg


											$patient_name 	= $patientid;
											$batch_id 		=  date('Ymd', strtotime($datetime));
											$patientid 		.= 'Immtox120';

											$report = $this->db->get_qc($patientid, $batch_id, 'tox');
											if (!$report) {
												$report_id = $this->db->create_qc($patientid, array(
													'patient_name' => $patient_name,
													'instrument' => 'Immtox 120',
													'batch_id' => $batch_id,
													'acquisition_date' => $datetime,
												), 'tox');
											} else {
												$report_id = $report['id'];
											}

											if ($report_id > 0) {
												$this->db->insert_result($report_id, array(
													'drug' => $testarr['drug'],
													'conc' => $conc,
												), 'tox');
											}
										}

										if (DEBUG) {
											$this->log("Qc: bid: {$batch_id} =>reportid{$report_id}=> {$test_num}=> {$testarr['drug']} ({$testarr['ID']})=> conc: {$conc} => {$patientid}<NL>");
										}
									}
								}
							} else {

								$mid = 0;
								$ack_status = 'NF';
								$this->log("<NL>Assession empty<NL>");
							}
							$this->send_ack($arr, $ack_status);

							break;

						case 'QBP':
							$assen = trim($arr['QPD']['QPD.4']['QPD.4.1']);
							if (!empty($assen)) {
								$this->log("<NL>Query for assen: {$assen}<NL>");

								$batch = $this->db->get_batch_data($assen, 'tox');
								$drugs = $this->db->get_tests_specimens($assen, 'u_immtox_120_code');
								$data = array();
								if (!empty($drugs)) {
									$tests = array();

									foreach ($drugs as $num => $drug) {
										$tests[] = array(
											'num' => $num,
											'drug' => $drug
										);
									}
									$data = array(
										'assen' => $assen,
										'patient_name' => $batch['patient_name'],
										'mid' => $batch['mid'],
										'pa_dob' => $batch['pa_dob'],
										'pa_gender' => $batch['pa_gender'],
										'tests' => $tests
									);
								}
								$this->send_rsp($arr, $data, 'AA');
								$this->db->set_sent_sample('tox', $batch['mid'], API_ID);
							} else {
								$this->log("<NL>Query with empty assen<NL>");
								$this->send_rsp($arr, null, 'NF');
							}

							break;

						case 'ORL':
							if ($this->is_order_ins) {
								if ($this->last_sent_order_mid > 0 && !isset($arr['ERR'])) {

									$this->db->set_sent_sample('tox', $this->last_sent_order_mid, API_ID);
								}


								$this->check_send_orders();
							}

							break;

						default:
							$this->send_ack($arr, 'AA');
							break;
					}


					$data = '';
				} else if (!$this->is_test && $buf == $this->server->CHARS['LF'] && $last_buf == $this->server->CHARS['SOH']) {
					$this->is_test = true;
					$this->sendraw("hello test: " . $this->address);
				}

				if ($msg_started && $buf != $this->server->CHARS['FS']) {
					$data .= $buf;
				}
				$last_buf = $buf;
			} catch (Exception $e) {
				var_dump($e);
			}
		} while (true);



		echo date('Y-m-d H:i:s') . " - Client dropped - {$this->address} #{$this->client_id}\n";
		$this->db->disconnect();

		$this->stop();
	}

	function check_send_orders()
	{
		$batch_id = date('Ymd');
		$orders = $this->db->get_unsent_orders('tox', API_ID, $batch_id, 10);
		echo date('Y-m-d H:i:s') . " - bid: {$batch_id} - check_send_orders: " . count($orders) . "\n";
		if ($orders) {
			foreach ($orders as $order) {

				$drugs = $this->db->get_tests_specimens($order['assen'], 'u_immtox_120_code');

				if (!empty($drugs)) {
					$tests = array();

					foreach ($drugs as $num => $drug) {
						$tests[] = array(
							'num' => $num,
							'drug' => $drug
						);
					}
					$order['tests'] = $tests;
				}

				if (empty($order['tests'])) {
					echo date('Y-m-d H:i:s') . " - no tests for order: " . $order['assen'] . "\n";
					$this->db->set_sent_sample('tox', $order['mid'], API_ID);
					continue;
				}

				$this->send_oml($order);
				$this->last_sent_order_mid = $order['mid'];

				break;
			}
		}
	}


	function stop()
	{
		try {
			$this->need_stop = true;
			if ($this->msgsock != null) {
				stream_socket_shutdown($this->msgsock, STREAM_SHUT_WR);
			}

			$this->msgsock = null;
		} catch (Exception $e) {
		}
	}

	function log($log, $type = '')
	{
		$date = date('Y-m-d');
		$filelog = $this->server->log_path . '/log_' . $type . $this->server->port . '_' . $date . '_' . $this->address . '.txt';
		if ($type == 'error') {
			$filelog = $this->server->log_path . '/error_' . $type . $this->server->port . '_' . $date . '.txt';
		} else if ($this->address == '127.0.0.1' || $type == 'test' || $this->is_test) {
			$filelog = $this->server->log_path . '/log_' . $type . $this->server->port . '_test.txt';
		}

		foreach ($this->server->CHARS as $k => $CHAR) {
			$nl = "";
			if ($k == 'CR') {
				$nl = "\n";
			}
			$log = str_replace($CHAR, '<' . $k . '>' . $nl, $log);
		}
		$log = str_replace("<CR>\n<LF>", '<CR><LF>', $log);

		$log = str_replace('<NL>', "\n", $log);

		$f = fopen($filelog, "a+");
		if ($f) {
			fwrite($f, $log);
			fclose($f);
		}
	}

	function sendraw($data, $needlog = true)
	{
		$status = fputs($this->msgsock, $data, strlen($data));

		if ($needlog && $this->server->debug) {
			$log = "<NL>" . date('Y-m-d H:i:s') . " - " . ($status ? 'sent' : 'send fail') . ": <NL>";
			$log .= $data;
			$log .= "<NL><NL>";
			$this->log($log);
		}
		return $status;
	}

	function write_msg($talkback)
	{
		$talkback = $this->server->CHARS['VT'] . $talkback . $this->server->CHARS['FS'] . $this->server->CHARS['CR'];

		return $this->sendraw($talkback);
	}


	function hl7_to_array(string $string)
	{

		$multis = array('OBX');

		$string = str_replace("\r\n", "\n", $string);
		$string = str_replace("\n", "\r", $string);
		$segs = explode("\r", $string);

		$out = array();

		//get delimiting characters

		if (substr($segs[0], 0, 3) != 'MSH') {

			$out['ERROR'][0] = 'Invalid immtox_120 Message.';
			$out['ERROR'][1] = 'Must start with MSH';

			return $out;
		}

		$delbarpos = strpos($segs[0], '|', 4);

		$delchar = substr($segs[0], 4, ($delbarpos - 4));
		$out['FLD_SEP'] = substr($delchar, 0, 1);
		$out['SFLD_SEP'] = substr($delchar, 1, 1);
		$out['REP_SEP'] = substr($delchar, 2, 1);
		$out['ESC_CHAR'] = substr($delchar, 3, 1);

		foreach ($segs as $fseg) {

			$fseg = trim($fseg);
			if (empty($fseg)) {
				continue;
			}
			$segments = explode('|', $fseg);

			$segname = $segments[0];
			$i = 0;
			$ic = isset($out[$segname]) ? count($out[$segname]) : 0;
			foreach ($segments as $k => $seg) {

				$j = 1;
				$sf = explode($out['FLD_SEP'], $seg);
				foreach ($sf as $f) {
					if (in_array($segname, $multis)) {
						$out[$segname][$ic][$segname . '.' . $i][$segname . '.' . $i . '.' . $j] = $f;
					} else {
						$out[$segname][$segname . '.' . $i][$segname . '.' . $i . '.' . $j] = $f;
					}

					$j++;
				}
				$i++;

				if ($k == 0 && $segname == 'MSH') {
					$out[$segname][$segname . '.' . $i][$segname . '.' . $i . '.1'] = '|';
					$i++;
				}
			}
		}


		return $out;
	}

	function array_to_hl7($arr)
	{
		$FLD_SEP = isset($arr['FLD_SEP']) && !empty($arr['FLD_SEP']) ? $arr['FLD_SEP'] : '^';
		$immtox_120 = array();
		foreach ($arr as $segname => $segments) {
			if (is_numeric($segname)) {
				foreach ($segments as $name1 => $seg) {
					foreach ($seg as $i => $item) {
						if (is_array($item)) {
							$item = implode($FLD_SEP, $item);
						}
						if (($name1 == 'MSH' && $item == '|') || $i == $name1 . '.0') {
							unset($seg[$i]);
							continue;
						}
						$seg[$i] = $item;
					}
					$immtox_120[] = $name1 . '|' . implode('|', $seg);
				}
			} else if (isset($segments[0])) {
				foreach ($segments as $seg) {
					foreach ($seg as $i => $item) {
						if (is_array($item)) {
							$item = implode($FLD_SEP, $item);
						}
						if ($i == $segname . '.0') {
							unset($seg[$i]);
							continue;
						}
						$seg[$i] = $item;
					}
					$immtox_120[] = $segname . '|' . implode('|', $seg);
				}
			} else {
				foreach ($segments as $i => $item) {
					if (is_array($item)) {
						$item = implode($FLD_SEP, $item);
					}
					if (($segname == 'MSH' && $item == '|') || $i == $segname . '.0') {
						unset($segments[$i]);
						continue;
					}
					$segments[$i] = $item;
				}
				$immtox_120[] = $segname . '|' . implode('|', $segments);
			}
		}
		$immtox_120 = implode("\r", $immtox_120);
		return $immtox_120;
	}

	function send_ack($arr, $status = 'AA')
	{
		$OUT = array();
		$OUT['MSH'] = $arr['MSH'];
		$OUT['MSH']['MSH.9']['MSH.9.1'] = 'ACK';
		$OUT['MSH']['MSH.9']['MSH.9.3'] = 'ACK';

		$OUT['MSA'] = array();
		$OUT['MSA']['MSA.1'] = $status;
		$OUT['MSA']['MSA.2'] = $arr['MSH']['MSH.10']['MSH.10.1'];

		if ($status == 'AE') {
			$OUT['ERR'] = array();
			$OUT['ERR']['ERR.1'] = 'SAC^2^1';
			$OUT['ERR']['ERR.2'] = '207';
		}

		$msg = $this->array_to_hl7($OUT);
		return $this->write_msg($msg);
	}
	//QBP^Q11 => RSP^K11
	function send_rsp($arr, $data, $status = 'AA')
	{
		$OUT = array();
		$OUT['MSH'] = $arr['MSH'];
		$OUT['MSH']['MSH.9']['MSH.9.1'] = 'RSP';
		$OUT['MSH']['MSH.9']['MSH.9.2'] = 'WOS';
		$OUT['MSH']['MSH.9']['MSH.9.3'] = 'RSP_K11';

		$OUT['MSA'] = array();
		$OUT['MSA']['MSA.1'] = $status;
		$OUT['MSA']['MSA.2'] = $arr['MSH']['MSH.10']['MSH.10.1'];

		if ($status == 'AE') {
			$OUT['ERR'] = array();
			$OUT['ERR']['ERR.1'] = 'QPD^2^1';
			$OUT['ERR']['ERR.2'] = '207';
		}

		$OUT['QAK']['QAK.1']['QAK.1.1'] = $arr['QPD']['QPD.2']['QPD.2.1'];
		$OUT['QAK']['QAK.2']['QAK.2.1'] = $data ? 'OK' : 'NF';
		$OUT['QAK']['QAK.3'] = $arr['QPD']['QPD.1'];

		$OUT['QPD'] = $arr['QPD'];

		$OUT['SPM'] = array();
		$OUT['SPM']['SPM.1']['SPM.1.1'] = '1';
		$OUT['SPM']['SPM.2']['SPM.2.1'] = $data['assen'];
		$OUT['SPM']['SPM.3']['SPM.3.1'] = '';
		$OUT['SPM']['SPM.4']['SPM.4.1'] = '';
		$OUT['SPM']['SPM.5']['SPM.5.1'] = '';
		$OUT['SPM']['SPM.6']['SPM.6.1'] = '';
		$OUT['SPM']['SPM.7']['SPM.7.1'] = '';
		$OUT['SPM']['SPM.8']['SPM.8.1'] = '';
		$OUT['SPM']['SPM.9']['SPM.9.1'] = '';
		$OUT['SPM']['SPM.10']['SPM.10.1'] = '';
		$OUT['SPM']['SPM.11']['SPM.11.1'] = 'P';

		if ($data) {

			$OUT['PID'] = array();
			$OUT['PID']['PID.1']['PID.1.1'] = '1';
			$OUT['PID']['PID.2']['PID.2.1'] = '';
			$OUT['PID']['PID.3']['PID.3.1'] = $data['assen'];
			$OUT['PID']['PID.4']['PID.4.1'] = '';
			$OUT['PID']['PID.5']['PID.5.1'] = $data['patient_name'];
			$OUT['PID']['PID.6']['PID.6.1'] = '';
			$OUT['PID']['PID.7']['PID.7.1'] = str_replace('-', '', $data['pa_dob']);
			$OUT['PID']['PID.8']['PID.8.1'] = $data['pa_gender'];


			$OUT['OBR']                     = array();
			$OUT['OBR']['OBR.1']['OBR.1.1']   = 1;
			$OUT['OBR']['OBR.2']['OBR.2.1']   = '';
			$OUT['OBR']['OBR.3']['OBR.3.1']   = '';
			$OUT['OBR']['OBR.4']['OBR.4.1']   = '';
			$OUT['OBR']['OBR.4']['OBR.4.2']   = '';
			$OUT['OBR']['OBR.4']['OBR.4.3']   = '';
			$OUT['OBR']['OBR.5']['OBR.5.1']   = '';
			$OUT['OBR']['OBR.6']['OBR.6.1']   = '';
			$OUT['OBR']['OBR.7']['OBR.7.1']   = '';
			$OUT['OBR']['OBR.8']['OBR.8.1']   = '';
			$OUT['OBR']['OBR.9']['OBR.9.1']   = '';
			$OUT['OBR']['OBR.10']['OBR.10.1'] = '';
			$OUT['OBR']['OBR.11']['OBR.11.1'] = '';
			$i = 1;
			foreach ($data['tests'] as $test) {

				$OUT[$i]['OBX'] = array();
				$OUT[$i]['OBX']['OBX.1']['OBX.1.1'] = $i;
				$OUT[$i]['OBX']['OBX.2']['OBX.2.1'] = '';
				$OUT[$i]['OBX']['OBX.3']['OBX.3.1'] = $test['num'];
				$OUT[$i]['OBX']['OBX.3']['OBX.3.2'] = $test['drug'];
				$OUT[$i]['OBX']['OBX.4']['OBX.4.1'] = '';
				$OUT[$i]['OBX']['OBX.5']['OBX.5.1'] = '';
				$OUT[$i]['OBX']['OBX.6']['OBX.6.1'] = '';
				$OUT[$i]['OBX']['OBX.7']['OBX.7.1'] = '';
				$OUT[$i]['OBX']['OBX.8']['OBX.8.1'] = '';
				$OUT[$i]['OBX']['OBX.9']['OBX.9.1'] = 'R';


				$i++;
			}
		}




		$msg = $this->array_to_hl7($OUT);
		return $this->write_msg($msg);
	}


	function send_oml($data, $rejected = false)
	{
		$data['pa_dob'] = str_replace('-', '', $data['pa_dob']);

		$OUT = array();
		$OUT['MSH'] = array();
		$OUT['MSH']['MSH.1']['MSH.1.1'] = '';
		$OUT['MSH']['MSH.2']['MSH.2.1'] = '';
		$OUT['MSH']['MSH.3']['MSH.3.1'] = 'LISApp';
		$OUT['MSH']['MSH.4']['MSH.4.1'] = 'MainFacility';
		$OUT['MSH']['MSH.5']['MSH.5.1'] = 'immtox_120App';
		$OUT['MSH']['MSH.6']['MSH.6.1'] = 'LabFacility';
		$OUT['MSH']['MSH.7']['MSH.7.1'] = '20160801103902';
		$OUT['MSH']['MSH.8']['MSH.8.1'] = '';
		$OUT['MSH']['MSH.9']['MSH.9.1'] = 'OML';
		$OUT['MSH']['MSH.9']['MSH.9.2'] = 'O35';
		$OUT['MSH']['MSH.9']['MSH.9.3'] = 'OML_O35';

		$OUT['PID'] = array();
		$OUT['PID']['PID.1']['PID.1.1'] = '1';
		$OUT['PID']['PID.2']['PID.2.1'] = '';
		$OUT['PID']['PID.3']['PID.3.1'] = $data['assen'];
		$OUT['PID']['PID.4']['PID.4.1'] = '';
		$OUT['PID']['PID.5']['PID.5.1'] = $data['patient_name'];
		$OUT['PID']['PID.6']['PID.6.1'] = '';
		$OUT['PID']['PID.7']['PID.7.1'] = $data['pa_dob'];
		$OUT['PID']['PID.8']['PID.8.1'] = $data['pa_gender'];


		$OUT['SPM'] = array();
		$OUT['SPM']['SPM.1']['SPM.1.1'] = '1';
		$OUT['SPM']['SPM.2']['SPM.2.1'] = '';
		$OUT['SPM']['SPM.3']['SPM.3.1'] = '';
		$OUT['SPM']['SPM.4']['SPM.4.1'] = '';
		$OUT['SPM']['SPM.5']['SPM.5.1'] = '';
		$OUT['SPM']['SPM.6']['SPM.6.1'] = '';
		$OUT['SPM']['SPM.7']['SPM.7.1'] = '';
		$OUT['SPM']['SPM.8']['SPM.8.1'] = '';
		$OUT['SPM']['SPM.9']['SPM.9.1'] = '';
		$OUT['SPM']['SPM.10']['SPM.10.1'] = '';
		$OUT['SPM']['SPM.11']['SPM.11.1'] = '';

		$OUT['SAC'] = array();
		$OUT['SAC']['SAC.1']['SAC.1.1'] = '';
		$OUT['SAC']['SAC.2']['SAC.2.1'] = '';
		$OUT['SAC']['SAC.3']['SAC.3.1'] = $data['assen'];
		if (!$rejected) {
			$OUT['SAC']['SAC.4']['SAC.4.1'] = 'NW';
			$OUT['SAC']['SAC.5']['SAC.5.1'] = '';
			$OUT['SAC']['SAC.6']['SAC.6.1'] = '';
			$OUT['SAC']['SAC.7']['SAC.7.1'] = '';
			$OUT['SAC']['SAC.8']['SAC.8.1'] = '';
			$OUT['SAC']['SAC.9']['SAC.9.1'] = '';
			$OUT['SAC']['SAC.10']['SAC.10.1'] = '';
			$OUT['SAC']['SAC.11']['SAC.11.1'] = '';
			$OUT['SAC']['SAC.12']['SAC.12.1'] = '';
		}



		$OUT['OBR']                     = array();
		$OUT['OBR']['OBR.1']['OBR.1.1']   = 1;
		$OUT['OBR']['OBR.2']['OBR.2.1']   = '';
		$OUT['OBR']['OBR.3']['OBR.3.1']   = '';
		$OUT['OBR']['OBR.4']['OBR.4.1']   = '';
		$OUT['OBR']['OBR.4']['OBR.4.2']   = '';
		$OUT['OBR']['OBR.4']['OBR.4.3']   = '';
		$OUT['OBR']['OBR.5']['OBR.5.1']   = '';
		$OUT['OBR']['OBR.6']['OBR.6.1']   = '';
		$OUT['OBR']['OBR.7']['OBR.7.1']   = '';
		$OUT['OBR']['OBR.8']['OBR.8.1']   = '';
		$OUT['OBR']['OBR.9']['OBR.9.1']   = '';
		$OUT['OBR']['OBR.10']['OBR.10.1'] = '';
		$OUT['OBR']['OBR.11']['OBR.11.1'] = '';
		$i = 1;
		foreach ($data['tests'] as $test) {

			$OUT[$i]['OBX'] = array();
			$OUT[$i]['OBX']['OBX.1']['OBX.1.1'] = $i;
			$OUT[$i]['OBX']['OBX.2']['OBX.2.1'] = '';
			$OUT[$i]['OBX']['OBX.3']['OBX.3.1'] = $test['num'];
			$OUT[$i]['OBX']['OBX.3']['OBX.3.2'] = $test['drug'];
			$OUT[$i]['OBX']['OBX.4']['OBX.4.1'] = '';
			$OUT[$i]['OBX']['OBX.5']['OBX.5.1'] = '';
			$OUT[$i]['OBX']['OBX.6']['OBX.6.1'] = '';
			$OUT[$i]['OBX']['OBX.7']['OBX.7.1'] = '';
			$OUT[$i]['OBX']['OBX.8']['OBX.8.1'] = '';
			$OUT[$i]['OBX']['OBX.9']['OBX.9.1'] = 'R';


			$i++;
		}


		$msg = $this->array_to_hl7($OUT);
		return $this->write_msg($msg);
	}
}


$log_path = '';
if (!empty($argv[3]) && stripos($argv[3], 'log/') > 0) {
	$log_path = $argv[3];
} else {
	$log_path = dirname(__FILE__) . '/immtox_120';
}

echo 'version: ' . API_VERSION . "\n";
echo 'Timezone: ' . $dbconfig['time_zone'] . "\n";
echo 'load config file: ' . basename($config_file) . "\n";
echo date('Y-m-d H:i:s') . ' - Starting port: ' . $port . "...";

$server = new IntrumentServer($port, $log_path, $dbconfig);
$server->setDebug(DEBUG);

if ($server->create()) {
	if ($server->testdb()) {
		echo "Started\n";
		$server->listen();
	} else {
		echo 'Cant connect DB';
	}
	echo date('Y-m-d H:i:s') . ' - Stopped Server!  ';
} else {
	echo 'Cant create Socket' . "\n";
}
