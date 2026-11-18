<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

//open port 8637:  iptables -A INPUT -m state --state NEW -p tcp --dport 8637 -j ACCEPT
//service iptables save

// cd /mnt/data/vhosts/default/wp-content/plugins/ci-data-works-arc/run/
// nohup zts-php au680.php port 8637 > outau680.txt 2> errorau680.txt < /dev/null &



if (!isset($argv[1])) {
	if ($argv[1] == 'phpinfo') {
		phpinfo();
		exit;
	} else if ($argv[1] != 'port') {
		echo 'exit';
		exit;
	}
}

const API_VERSION = '1.1.1';
const API_NAME = 'Au680B';
const API_ID = -20;
const DEBUG = true;

$port       = $argv[2] > 80 ? $argv[2] : 8637;

$config_dir = dirname(dirname(dirname(dirname(__FILE__)))) . '/config';
$config_file = $config_dir . '/au680_blood_' . $port . '.php';
if (!file_exists($config_file)) {
	$config_file = $config_dir . '/au680_blood.php';
}
if (!file_exists($config_file)) {
	echo 'no config file';
	exit;
}
require($config_file);
require("db.php");




if (!isset($dbconfig) || !is_array($dbconfig)) {
	$dbconfig = array();
	echo 'Not found config file';
}
if (!isset($dbconfig['time_zone']) || empty($dbconfig['time_zone'])) {
	$dbconfig['time_zone'] = 'America/Detroit';
}
date_default_timezone_set($dbconfig['time_zone']);

class IntrumentServer
{

	public $port = '';
	public $filelog = '';
	public $path = '';
	public $default_config = array();


	public $db = null;
	public $dbconfig = array();
	private $sockServer;
	private $address;
	public $pid_file = 'pid.txt';
	public $stop_file = 'stop.txt';
	public $debug = false;

	const SOH = 0x01;
	const STX = 0x02;
	const ETX = 0x03;
	const ETB = 0x17;
	const EOT = 0x04;
	const ENQ = 0x05;
	const ACK = 0x06;
	const NAK = 0x15;
	const LF = 0x0A;
	const VT = 0x0B;
	const CR = 0x0D;

	public function __construct($port, $address, $dbconfig)
	{
		$this->address = $address;
		$this->dbconfig = $dbconfig;
		$this->port = $port;

		if (!empty($dbconfig['path'])) {
			$this->path = $dbconfig['path'];
		}

		if (empty($this->path)) {
			$this->path = dirname(__FILE__) . '/log';
		}

		$this->filelog = $this->path . '/log_' . $this->port . '.txt';
		$this->pid_file = $this->path . '/pid_' . $this->port . '.txt';
		$this->stop_file = $this->path . '/stop_' . $this->port . '.txt';

		if (file_exists($this->stop_file)) {
			unlink($this->stop_file);
		}

		file_put_contents($this->pid_file, '');

		$this->default_config = array(
			'use_device_no' => false,
			'use_rack' => false,
			'rack_size' => 4,
			'test_no_size' => 3,
			'result_size' => 6,
			'result_unknow_size' => 0,
			'data_mark_size' => 2,
			'control_size' => 3,
			'sample_id_size' => 11,
			'use_sex' => false,
			'use_age' => false,
			'use_patient_1' => false,
			'use_patient_2' => false,
			'use_patient_3' => false,
			'use_patient_4' => false,
			'use_patient_5' => false,
			'use_patient_6' => false,
			'use_diluent_inf' => false,
		);

		$this->db = new DB($dbconfig);
	}

	public function create()
	{
		printf(" - creating socket...");
		$port = $this->port;

		$this->sockServer = stream_socket_server("tcp://{$this->address}:{$port}", $errno, $errstr);
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
			if ($f) {
				fwrite($f, $pid . "\n");
				fclose($f);
			}
		} catch (Exception $e) {
		}
	}



	public function testdb()
	{
		$rs = $this->db->connect();
		$this->db->disconnect();
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
		$count_client = 0;
		while (true) {
			try {

				$address = '';
				if (($msgsock = @stream_socket_accept($this->sockServer, 90, $address)) === false) {
					restore_error_handler();
					echo $address;
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


				$count_client++;

				$clients[] = new Client_Thread($msgsock, $this, $count_client);
			} catch (Exception $e) {
				var_dump($e);
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
		} else {
			echo 'no config file for white ip';
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

	public function __construct($msgsock, $server, $clientid)
	{
		$this->address = stream_socket_get_name($msgsock, true);
		if (strpos($this->address, ':') > 0) {
			list($this->address, $rmport) = explode(':', $this->address);
		}

		$this->msgsock = $msgsock;
		$this->server = $server;
		$this->client_id = $clientid;

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

					$this->run();
					exit;
				} else {

					// Parent process: continue listening for new connections
				}
			} else {
				$this->run();
			}
		} else {
			$this->start();
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
		$filelog = $this->server->path . '/log_' . $type . '_' . $this->address . '-' . $this->server->port . '_' . $date . '.txt';

		if ($type == 'error') {
			$filelog = $this->server->path . '/error_' . $type . $this->server->port . '_' . $date . '_' . $this->address . '.txt';
		} else if ($this->address == '127.0.0.1' || $type == 'test' || $this->is_test) {
			$filelog = $this->server->path . '/log_' . $type . $this->server->port . '_test.txt';
		}


		$log = str_replace(chr(IntrumentServer::SOH), '<SOH>', $log);
		$log = str_replace(chr(IntrumentServer::STX), '<STX>', $log);
		$log = str_replace(chr(IntrumentServer::ETX), '<ETX>', $log);
		$log = str_replace(chr(IntrumentServer::ETB), '<ETB>', $log);
		$log = str_replace(chr(IntrumentServer::EOT), '<EOT>', $log);
		$log = str_replace(chr(IntrumentServer::ENQ), '<ENQ>', $log);
		$log = str_replace(chr(IntrumentServer::ACK), '<ACK>', $log);
		$log = str_replace(chr(IntrumentServer::LF), '<LF>' . "\n", $log);
		$log = str_replace(chr(IntrumentServer::VT), '<VT>', $log);
		$log = str_replace(chr(IntrumentServer::CR), '<CR>', $log);
		$log = str_replace('<NL>', "\n", $log);

		$f = fopen($filelog, "a+");
		if ($f) {
			fwrite($f, $log);
			fclose($f);
		}
	}

	function write_msg($msgsock, $talkback)
	{
		$talkback = chr(IntrumentServer::STX) . $talkback . chr(IntrumentServer::ETX);
		if (DEBUG) {
			$date = date('Y-m-d H:i:s');
			$log = "<NL>{$date} - {$this->address} #{$this->client_id} => sendout: <NL>";
			$log .= $talkback;
			$log .= "<NL>==========<NL><NL>";
			$this->log($log);
		}

		return fputs($msgsock, $talkback, strlen($talkback));
	}


	function parse_data($data)
	{

		$arr = array();
		$arr['type'] = substr($data, 0, 2);
		$format = array('type' => 2);
		switch ($arr['type']) {
			case 'R ':
				$format['system_no']    = isset($this->server->dbconfig['use_device_no']) && $this->server->dbconfig['use_device_no'] ? 2 : 0;
				$format['rack_no']      = isset($this->server->dbconfig['use_rack']) && $this->server->dbconfig['use_rack'] ? $this->server->dbconfig['rack_size'] : 0;
				$format['cup_pos']      = isset($this->server->dbconfig['use_rack']) && $this->server->dbconfig['use_rack'] ? 2 : 0;
				$format['sample_type']  = 1;
				$format['sample_no']    = 4;
				$format['sample_id']    = $this->server->dbconfig['sample_id_size'];

				break;

			case 'D ':
			case 'd ':
				$format['system_no']    = isset($this->server->dbconfig['use_device_no']) && $this->server->dbconfig['use_device_no'] ? 2 : 0;
				$format['rack_no']      = isset($this->server->dbconfig['use_rack']) && $this->server->dbconfig['use_rack'] ? $this->server->dbconfig['rack_size'] : 0;
				$format['cup_pos']      = isset($this->server->dbconfig['use_rack']) && $this->server->dbconfig['use_rack'] ? 2 : 0;
				$format['sample_type']  = 1;
				$format['sample_no']    = 4;
				$format['sample_id']    = isset($this->server->dbconfig['sample_id_size']) ? $this->server->dbconfig['sample_id_size'] : '';
				$format['dummy']        = 4;
				$format['class_no']     = 1;
				$format['sex']          = isset($this->server->dbconfig['use_sex']) && $this->server->dbconfig['use_sex'] ? 1 : 0;
				$format['year_age']     = isset($this->server->dbconfig['use_age']) && $this->server->dbconfig['use_age'] ? 3 : 0;
				$format['month_age']    = isset($this->server->dbconfig['use_age']) && $this->server->dbconfig['use_age'] ? 2 : 0;
				for ($i = 1; $i <= 6; $i++) {
					$format['patient_' . $i] = isset($this->server->dbconfig['use_patient_' . $i]) && $this->server->dbconfig['use_patient_' . $i] ? 20 : 0;
				}
				if (isset($this->server->dbconfig['result_unknow_size']) && $this->server->dbconfig['result_unknow_size'] > 0) {
					$format['unknow'] = $this->server->dbconfig['result_unknow_size'];
				}
				break;
		}
		$current_pos = 0;
		foreach ($format as $k => $size) {
			$arr[$k] = substr($data, $current_pos, $size);
			$current_pos += $size;
		}
		$arr['current_pos'] = $current_pos;
		return $arr;
	}

	function build_data($type, $arr)
	{
		$msg = $type;
		$format = array();
		switch ($type) {

			case 'S ':
				$format['rack_no']      = isset($this->server->dbconfig['use_rack']) && $this->server->dbconfig['use_rack'] ? $this->server->dbconfig['rack_size'] : 0;
				$format['cup_pos']      = isset($this->server->dbconfig['use_rack']) && $this->server->dbconfig['use_rack'] ? 2 : 0;
				$format['sample_type']  = 1;
				$format['sample_no']    = 4;
				$format['sample_id']    = $this->server->dbconfig['sample_id_size'];
				$format['dummy']        = 4;
				$format['class_no']     = 1;
				$format['sex']          = isset($this->server->dbconfig['use_sex']) && $this->server->dbconfig['use_sex'] ? 1 : 0;
				$format['year_age']     = isset($this->server->dbconfig['use_age']) && $this->server->dbconfig['use_age'] ? 3 : 0;
				$format['month_age']    = isset($this->server->dbconfig['use_age']) && $this->server->dbconfig['use_age'] ? 2 : 0;
				for ($i = 1; $i <= 6; $i++) {
					$format['patient_' . $i] = @$this->server->dbconfig['use_patient_' . $i] ? 20 : 0;
				}
				if (isset($this->server->dbconfig['result_unknow_size']) && $this->server->dbconfig['result_unknow_size'] > 0) {
					$format['unknow'] = $this->server->dbconfig['result_unknow_size'];
				}
				break;

			case 'SH':
				$format['rack_no']      = isset($this->server->dbconfig['use_rack']) && $this->server->dbconfig['use_rack'] ? $this->server->dbconfig['rack_size'] : 0;
				$format['cup_pos']      = isset($this->server->dbconfig['use_rack']) && $this->server->dbconfig['use_rack'] ? 2 : 0;
				$format['sample_type']  = 1;
				$format['sample_no']    = 4;
				$format['sample_id']    = $this->server->dbconfig['sample_id_size'];
				$format['sample_no']    = 4;
				break;
		}

		if (!isset($arr['sex']) || $arr['sex'] === '') {
			$arr['sex'] = '0';
		}

		if (!isset($arr['year_age']) || empty($arr['year_age'])) {
			$arr['year_age'] = '000';
		}

		foreach ($format as $k => $size) {
			$vl = isset($arr[$k]) ? $arr[$k] : '';
			$vl = substr($vl, 0, $size);
			$adding = $size - strlen($vl);
			if ($adding > 0) {
				$vl = str_repeat(' ', $adding) . $vl;
			}
			$msg .= $vl;
		}

		return $msg;
	}


	public function run()
	{

		$db = new DB($this->server->dbconfig);
		$db->connect();

		$buf = "";
		$last_buf = "";
		$data = '';
		$msg_started = false;

		$ack_response = chr(IntrumentServer::ACK);

		try {
			date_default_timezone_set($this->server->dbconfig['time_zone']);

			echo date('Y-m-d H:i:s') . " - Client connected - {$this->address} #{$this->client_id}\n";
		} catch (Exception $e) {
		}


		//$buf = chr(IntrumentServer::EOT);
		$rs = fputs($this->msgsock, $buf, strlen($buf));
		if ($rs) {
			//echo date('Y-m-d H:i:s')." - {$this->address} #{$this->client_id} - sent first byte\n";
		}

		do {
			try {

				if ($this->need_stop) {
					break;
				}

				if (false === ($buf = fread($this->msgsock, 1))) {
					echo date('Y-m-d H:i:s') . " - {$this->address} #{$this->client_id} - error: Read false\n";
					break;
				}


				if (strlen($buf) == 0) {
					if (feof($this->msgsock)) {
						//sleep(10);
						echo date('Y-m-d H:i:s') . " - {$this->address} #{$this->client_id} - error: Read feof\n";
						break;
					}
					continue;
				}
				if ($buf == chr(IntrumentServer::EOT)) {
					echo date('Y-m-d H:i:s') . " - {$this->address} #{$this->client_id} - error: Read EOT\n";
					break;
				}

				if (DEBUG) {
					$nl2 = "";
					if ($buf == chr(IntrumentServer::ETX)) {
						$nl2 = '<NL><NL>';
					}
					$this->log($buf . $nl2, 'raw');
				}

				if (chr(IntrumentServer::STX) == $buf) {
					$msg_started = true;
					$data = '';
					$last_buf       = $buf;
					continue;
				}


				if ($msg_started && (chr(IntrumentServer::ETX) == $buf || chr(IntrumentServer::ETB) == $buf)) {
					$msg_started = false;


					$arr = $this->parse_data($data);
					$created = date('Y-m-d H:i:s');

					if (DEBUG) {
						$this->log("<NL><NL>{$created} - {$this->address} #{$this->client_id} =>Rev:<NL>");
						$this->log(chr(IntrumentServer::STX) . $data . $buf . "<NL>");
						$this->log("<NL>" . json_encode($arr) . "<NL>");
					} else {
						touch($this->server->filelog);
					}

					//send back 06
					if (DEBUG) {
						$created = date('Y-m-d H:i:s');
						$this->log("<NL><NL>{$created} - {$this->address} #{$this->client_id} =>sendout:<NL>");
						$this->log(chr(IntrumentServer::ACK) . "<NL>");
					}
					fputs($this->msgsock, $ack_response, strlen($ack_response));
					sleep(1);

					switch ($arr['type']) {
						case 'R ';
							$assen = trim($arr['sample_id']);

							$btype = 'blood';
							$mid = $db->get_mid($assen, $btype);

							if ($mid > 0) {
								$tests = $db->get_tests($assen, $btype);

								$arr['sample_type'] = ' '; //$db->get_sample_type($assen,$btype);
								if ($arr['sample_type'] == 'W') {
									$arr['sample_type'] = ' ';
								}



								$arr['class_no'] = 'E';
								$msg = $this->build_data('S ', $arr);
								foreach ($tests as $test) {
									$msg .= $test;
									if (isset($this->server->dbconfig['use_diluent_inf']) && $this->server->dbconfig['use_diluent_inf']) {
										$msg .= '0';
									}
								}

								$this->write_msg($this->msgsock, $msg);

								$rs = $db->query("INSERT INTO ci_log_users (user_id,user_action,ip,mid,btype,data,created)
											values(" . API_ID . ",'au680_o','{$this->address}',{$mid},'{$btype}','','{$created}')");
								if ($rs === false) {
									$this->log("insert log error for mid :{$mid} {$btype}<NL>");
								}
							} else {
								$this->log("not found assen :{$assen}<NL>");
							}




							break;

						case 'RE':
						case 'DE':
							//$this->write_msg($this->msgsock,'SE');
							break;



						case 'D ':
							//case 'd ':

							$logarr = array();
							$logstr = '';

							$assen = trim($arr['sample_id']);
							$btype = 'blood';
							$mid = $db->get_mid($assen, $btype);
							$count_rs = 0;


							if (DEBUG) {
								$this->log("{$btype}:{$assen}: {$mid}<NL>");
							}

							if ($mid > 0) {
								$report_id = 0;
								$report = $db->get_report($assen, $btype);
								if (!$report) {
									$report_id = $db->create_report($assen, $btype, API_NAME);
								} else if ($report['estatus'] == 'C' && in_array($report['final_results'], array(1, 3))) {
									$approvedtype = $report['final_results'] == 3 ? 'Amended' : 'Final';
									$this->log("<NL>Report is approved as {$approvedtype}<NL>");
									$report_id = 0;
									break;
								} else {
									$report_id = $report['id'];
									$db->update_report($assen, $btype, API_NAME);
								}

								$testids_ordered = $db->testids_ordered($btype, $assen);

								$current_pos = $arr['current_pos'];
								$maxlength = strlen($data);



								while ($current_pos < $maxlength) {
									$db->last_drug = '';
									$db->last_conc = '';

									$test_num   = substr($data, $current_pos, $this->server->dbconfig['test_no_size']);
									$current_pos += $this->server->dbconfig['test_no_size']; //+$this->server->dbconfig['data_mark_size'];
									$conc       = substr($data, $current_pos, $this->server->dbconfig['result_size'] + $this->server->dbconfig['data_mark_size']);
									$current_pos += $this->server->dbconfig['result_size'] + $this->server->dbconfig['data_mark_size'];

									$test_num = trim($test_num);

									if (empty($test_num) || $test_num === '0') continue;

									$conc = str_replace(' ', '', $conc);
									$conc = preg_replace('/[a-zA-Z\*]*/', '', $conc);


									if ($conc == '%?') {
										continue;
									}
									if ($report_id > 0) {
										$rs = $db->insert_result($report_id, array(
											'key' => 'te_au680',
											'drug' => $test_num,
											'conc' => $conc,
											'testids' => $testids_ordered,
											'instrument' => API_NAME,
										), $btype);
										if ($rs === false) {
											$this->log("<NL>insert data error for mid :{$mid} => {$db->last_query}<NL>");
										} else {
											$count_rs++;
										}
									}

									$this->log("{$test_num} => drug :{$db->last_drug} => conc: {$conc} => {$db->last_conc}<NL>");
								}
								$logdata = '';
							} else {
								$logdata = 'Not found: ' . $assen;
								$mid = 0;
							}

							//log user

							$rs = $db->query("INSERT INTO ci_log_users (user_id,user_action,ip,mid,btype,data,created)
											values(" . API_ID . ",'au680_rs','{$this->address}',{$mid},'{$btype}','{$logdata}','{$created}')");
							if ($rs === false) {
								$this->log("insert log error for mid :{$mid} {$btype}<NL>");
							}

							$db->set_received_result($btype, $mid, API_ID, $logarr);

							if (!$this->is_test && $count_rs > 0) {
								$db->reverse_report($assen, $btype);
							}

							break;

						default:

							break;
					}



					$data = '';
				}

				if ($msg_started) {
					$data .= $buf;
				} else if (!$this->is_test && $buf == chr(IntrumentServer::LF) && $last_buf == chr(IntrumentServer::SOH)) {
					$this->is_test = true;
					$this->write_msg($this->msgsock, "hello test");
				}
				$last_buf       = $buf;
			} catch (Exception $e) {
				var_dump($e);
			}
		} while (true);



		echo date('Y-m-d H:i:s') . " - Client dropped - {$this->address} #{$this->client_id}\n";
		$db->disconnect();

		$this->stop();
	}
}

if (!isset($argv[1]) || $argv[1] != 'port') {
	echo 'exit';
	exit;
}


$address    = '0.0.0.0';


echo 'version: ' . API_VERSION . "\n";
echo 'Timezone: ' . $dbconfig['time_zone'] . "\n";
echo 'load config file: ' . basename($config_file) . "\n";
echo date('Y-m-d H:i:s') . ' - Starting port: ' . $port . "...";


if (!empty($argv[3]) && stripos($argv[3], 'log/') > 0) {
	$dbconfig['path'] =  $argv[3];
}

$server = new IntrumentServer($port, $address, $dbconfig);

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
