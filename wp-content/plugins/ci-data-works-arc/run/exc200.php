<?php
if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit('Forbidden');
}

//open port 8654:  iptables -A INPUT -m state --state NEW -p tcp --dport 8654 -j ACCEPT
//service iptables save
// cd /mnt/data/vhosts/default/wp-content/plugins/ci-data-works-arc/run/
// nohup zts-php exc200.php port 8654 > out.txt 2> error.txt < /dev/null &

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
const API_NAME = 'exc200';
const API_ID = -66;
const DEBUG = true;



$port       = $argv[2] > 80 ? $argv[2] : 8654;

$config_dir = dirname(dirname(dirname(dirname(__FILE__)))) . '/config/';
$config_file = $config_dir . 'exc200_' . $port . '.php';
if (!file_exists($config_file)) {
	$config_file = $config_dir . 'exc200.php';
}

if (!file_exists($config_file)) {
	echo 'no config file' . "\n";
	exit;
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

	public $port 	= '';
	public $filelog = '';
	public $path 	= '';
	public $default_config = array();


	public $db = null;
	public $dbconfig = array();
	private $sockServer;
	private $address;
	public $pid_file = 'exc200_pid.txt';
	public $stop_file = 'exc200_stop.txt';
	public $debug = false;
	public $CHARS = array();

	public function __construct($port, $address, $dbconfig)
	{
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
		//if ($errno === 2 && stripos($errstr, 'accept failed: Connection timed out') !== false) return;
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
					echo 'address:' . $address . "\n";
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
					echo 'Forked' . "\n";
					$this->run();
					echo 'exit' . "\n";
					die();
					echo 'die not works' . "\n";
					exit;
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
		try {
			$date = date('Y-m-d');
			$filelog = $this->server->path . '/log_' . $type . '_' . $this->address . '-' . $this->server->port . '_' . $date . '.txt';

			if ($type == 'error') {
				$filelog = $this->server->path . '/error_' . $type . $this->server->port . '_' . $date  . '.txt';
			} else if ($this->address == '127.0.0.1' || $type == 'test' || $this->is_test) {
				$filelog = $this->server->path . '/log_' . $type . $this->server->port . '_test.txt';
			}

			foreach ($this->server->CHARS as $k => $CHAR) {
				$nl = "";
				if ($k == 'CR') {
					$nl = "\n";
				}
				$log = str_replace($CHAR, '<' . $k . '>' . $nl, $log);
			}

			$log = str_replace(array("<CR>\n<LF>", "<CR><NL><LF>"), '<CR><LF>', $log);

			$log = str_replace('<NL>', "\n", $log);

			$f = fopen($filelog, "a+");
			if ($f) {
				fwrite($f, $log);
				fclose($f);
			}
		} catch (Exception $e) {
		}
	}

	function sendraw($data, $needlog = true)
	{
		$status = fputs($this->msgsock, $data, strlen($data));

		if ($needlog && $this->server->debug) {
			$log = "<NL>" . date('H:i:s') . " - " . ($status ? 'sent' : 'send fail') . ": <NL>";
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


	function hl7_to_array($string)
	{

		$multis = array('OBX');

		$string = str_replace("\r\n", "\n", $string);
		$string = str_replace("\n", "\r", $string);
		$segs = explode("\r", $string);

		$out = array();

		//get delimiting characters

		if (substr($segs[0], 0, 3) != 'MSH') {

			$out['ERROR'][0] = 'Invalid exc200 Message.';
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
		$exc200 = array();
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
					$exc200[] = $name1 . '|' . implode('|', $seg);
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
					$exc200[] = $segname . '|' . implode('|', $seg);
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
				$exc200[] = $segname . '|' . implode('|', $segments);
			}
		}
		$exc200 = implode("\r", $exc200);
		return $exc200;
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

		$msg = $this->array_to_hl7($OUT);
		return $this->write_msg($msg);
	}

	function send_qck($arr, $status = 'OK')
	{
		$OUT = array();
		$OUT['MSH'] = $arr['MSH'];
		$OUT['MSH']['MSH.9']['MSH.9.1'] = 'QCK';
		$OUT['MSH']['MSH.9']['MSH.9.3'] = 'QCK';

		$OUT['MSA'] = array();
		$OUT['MSA']['MSA.1'] = 'AA';
		$OUT['MSA']['MSA.2'] = $arr['MSH']['MSH.10']['MSH.10.1'];

		$OUT['QAK'] = array();
		$OUT['QAK']['QAK.1'] = 'SR';
		$OUT['QAK']['QAK.2'] = $status;

		$msg = $this->array_to_hl7($OUT);
		return $this->write_msg($msg);
	}

	//QBP^Q11 => RSP^K11
	function send_rsp($arr, $data, $status = 'AA')
	{
		$OUT = array();
		$OUT['MSH'] = $arr['MSH'];
		$OUT['MSH']['MSH.9']['MSH.9.1'] = 'RSP';
		$OUT['MSH']['MSH.9']['MSH.9.2'] = 'ZOS';
		$OUT['MSH']['MSH.9']['MSH.9.3'] = 'RSP_K11';

		$OUT['MSA'] = array();
		$OUT['MSA']['MSA.1'] = $status;
		$OUT['MSA']['MSA.2'] = $arr['MSH']['MSH.10']['MSH.10.1'];

		$OUT['QAK']['ORC.1']['ORC.1.1'] = $arr['QPD']['QPD.2']['QPD.2.1'];
		$OUT['QAK']['ORC.2']['ORC.2.1'] = $data ? 'OK' : 'NF';
		$OUT['QAK']['ORC.3'] = $arr['QPD']['QPD.1'];

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
			$OUT['PID']['PID.7']['PID.7.1'] = str_replace('-', '', $data['dob']);
			$OUT['PID']['PID.8']['PID.8.1'] = $data['sex'];

			$OUT['PV1'] = array();
			$OUT['PV1']['PV1.1']['PV1.1.1'] = '1';
			$OUT['PV1']['PV1.2']['PV1.2.1'] = 'N';
			$OUT['PV1']['PV1.3']['PV1.3.1'] = '';
			$OUT['PV1']['PV1.4']['PV1.4.1'] = '';
			$OUT['PV1']['PV1.5']['PV1.5.1'] = '';
			$OUT['PV1']['PV1.6']['PV1.6.1'] = '';
			$OUT['PV1']['PV1.7']['PV1.7.1'] = '';




			$i = 1;
			foreach ($data['tests'] as $test) {

				//ORC|NW||||||||20160801103902
				$OUT[$i]['ORC'] = array();
				$OUT[$i]['ORC']['ORC.1']['ORC.1.1'] = 'NW';

				//TQ1|1||||||||S^Stat^exc2000485
				$OUT[$i]['TQ1'] = array();
				$OUT[$i]['TQ1']['TQ1.1']['TQ1.1.1'] = $i;
				$OUT[$i]['TQ1']['TQ1.2']['TQ1.2.1'] = '';
				$OUT[$i]['TQ1']['TQ1.3']['TQ1.3.1'] = '';
				$OUT[$i]['TQ1']['TQ1.4']['TQ1.4.1'] = '';
				$OUT[$i]['TQ1']['TQ1.5']['TQ1.5.1'] = '';
				$OUT[$i]['TQ1']['TQ1.6']['TQ1.6.1'] = '';
				$OUT[$i]['TQ1']['TQ1.7']['TQ1.7.1'] = '';
				$OUT[$i]['TQ1']['TQ1.8']['TQ1.8.1'] = '';
				$OUT[$i]['TQ1']['TQ1.9']['TQ1.9.1'] = 'R';

				$OUT[$i]['OBR']                     = array();
				$OUT[$i]['OBR']['OBR.1']['OBR.1.1']   = $i;
				$OUT[$i]['OBR']['OBR.2']['OBR.2.1']   = '';
				$OUT[$i]['OBR']['OBR.3']['OBR.3.1']   = '';
				$OUT[$i]['OBR']['OBR.4']['OBR.4.1']   = $test['num'];
				$OUT[$i]['OBR']['OBR.4']['OBR.4.2']   = ''; //$test['drug'];
				$OUT[$i]['OBR']['OBR.4']['OBR.4.3']   = 'exc200';
				$OUT[$i]['OBR']['OBR.5']['OBR.5.1']   = '';
				$OUT[$i]['OBR']['OBR.6']['OBR.6.1']   = '';
				$OUT[$i]['OBR']['OBR.7']['OBR.7.1']   = '';
				$OUT[$i]['OBR']['OBR.8']['OBR.8.1']   = '';
				$OUT[$i]['OBR']['OBR.9']['OBR.9.1']   = '';
				$OUT[$i]['OBR']['OBR.10']['OBR.10.1'] = '';
				$OUT[$i]['OBR']['OBR.11']['OBR.11.1'] = 'A';


				$OUT[$i]['NTE'] = array();
				$OUT[$i]['NTE']['NTE.1']['NTE.1.1'] = $i;
				$OUT[$i]['NTE']['NTE.2']['NTE.2.1'] = '';
				$OUT[$i]['NTE']['NTE.3']['NTE.3.1'] = 'Order comment';

				$i++;
			}
		}




		$msg = $this->array_to_hl7($OUT);
		return $this->write_msg($msg);
	}

	/* Accept
	MSH|^~\&|LISApp|MainFacility|exc200App|LabFacility|20160801103758||OML^O33^OML_O33|7e68205e- a431-464c-afd7-0115b5baf653|P|2.5.1|||NE|AL||UNICODE UTF-8|||LAB-28^IHE
	PID|||100||Doe^John^Lee^^^^L||19500214|M
	PV1||N|^ER 2
	SPM|1|||""|||||||P^Patient^exc2000369
	SAC|||S000501 ORC|NW||||||||20160801103758 TQ1|1||||||||S^Stat^exc2000485
	ORC|NW||||||||20160801103758
	TQ1|1||||||||S^Stat^exc2000485
	OBR||ORDER#1005||249^TSH^99ABT|||||||A
	NTE|0||Order comment
	 *
	 */

	/* REjected
	 * MSH|^~\&|LISApp|MainFacility|exc200App|LabFacility|20160801103902||OML^O33^OML_O33|b13bff13- ba63-4a50-9cb5-28d3d4df7c97|P|2.5.1|||NE|AL||UNICODE UTF-8|||LAB-28^IHE
	 * PID|||100||Doe^John^Lee^^^^L||19500214|M
	 * PV1||N|^ER 2
	 * SPM|1|||""|||||||P^Patient^exc2000369
	 * SAC|||S000501
	 * ORC|NW||||||||20160801103902
	 * TQ1|1||||||||S^Stat^exc2000485
	 * OBR||ORDER#1005||249^TSH^99ABT|||||||A
	 * NTE|0||Order comment
	 */
	function send_oml($arr, $data, $rejected = false)
	{
		$data['dob'] = str_replace('-', '', $data['dob']);

		$OUT = array();
		$OUT['MSH'] = $arr['MSH'];
		$OUT['MSH']['MSH.3']['MSH.3.1'] = 'LISApp';
		$OUT['MSH']['MSH.4']['MSH.4.1'] = 'MainFacility';
		$OUT['MSH']['MSH.5']['MSH.5.1'] = 'exc200App';
		$OUT['MSH']['MSH.6']['MSH.6.1'] = 'LabFacility';
		$OUT['MSH']['MSH.7']['MSH.7.1'] = '20160801103902';
		$OUT['MSH']['MSH.8']['MSH.8.1'] = '';
		$OUT['MSH']['MSH.9']['MSH.9.1'] = 'OML';
		$OUT['MSH']['MSH.9']['MSH.9.2'] = 'O33';
		$OUT['MSH']['MSH.9']['MSH.9.3'] = 'OML_O33';

		$OUT['PID'] = array();
		$OUT['PID']['PID.1']['PID.1.1'] = '';
		$OUT['PID']['PID.2']['PID.2.1'] = '';
		$OUT['PID']['PID.3']['PID.3.1'] = '100';
		$OUT['PID']['PID.4']['PID.4.1'] = '';
		$OUT['PID']['PID.5']['PID.5.1'] = $data['patient_name'];
		$OUT['PID']['PID.6']['PID.6.1'] = '';
		$OUT['PID']['PID.7']['PID.7.1'] = $data['dob'];
		$OUT['PID']['PID.8']['PID.8.1'] = $data['sex'];

		$OUT['PV1'] = array();
		$OUT['PV1']['PV1.1']['PV1.1.1'] = '';
		$OUT['PV1']['PV1.2']['PV1.2.1'] = 'N';
		$OUT['PV1']['PV1.3']['PV1.3.1'] = '';
		$OUT['PV1']['PV1.3']['PV1.3.2'] = 'ER 2';

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


		//ORC|NW||||||||20160801103902
		$OUT['ORC'] = array();
		$OUT['ORC']['ORC.1']['ORC.1.1'] = 'NW';
		$OUT['ORC']['ORC.2']['ORC.2.1'] = '';
		$OUT['ORC']['ORC.3']['ORC.3.1'] = '';
		$OUT['ORC']['ORC.4']['ORC.4.1'] = '';
		$OUT['ORC']['ORC.5']['ORC.5.1'] = '';
		$OUT['ORC']['ORC.6']['ORC.6.1'] = '';
		$OUT['ORC']['ORC.7']['ORC.7.1'] = '';
		$OUT['ORC']['ORC.8']['ORC.8.1'] = '';
		$OUT['ORC']['ORC.9']['ORC.9.1'] = date('YmdHis');

		//TQ1|1||||||||S^Stat^exc2000485
		$OUT['TQ1'] = array();
		$OUT['TQ1']['TQ1.1']['TQ1.1.1'] = '1';
		$OUT['TQ1']['TQ1.2']['TQ1.2.1'] = '';
		$OUT['TQ1']['TQ1.3']['TQ1.3.1'] = '';
		$OUT['TQ1']['TQ1.4']['TQ1.4.1'] = '';
		$OUT['TQ1']['TQ1.5']['TQ1.5.1'] = '';
		$OUT['TQ1']['TQ1.6']['TQ1.6.1'] = '';
		$OUT['TQ1']['TQ1.7']['TQ1.7.1'] = '';
		$OUT['TQ1']['TQ1.8']['TQ1.8.1'] = '';
		$OUT['TQ1']['TQ1.9']['TQ1.9.1'] = 'S';
		$OUT['TQ1']['TQ1.9']['TQ1.9.2'] = 'Stat';
		$OUT['TQ1']['TQ1.9']['TQ1.9.3'] = 'exc2000485';

		$i = 0;
		$OUT['OBR'] = array();
		foreach ($data['tests'] as $test) {

			$OUT['OBR']                     = array();
			$OUT['OBR']['OBR.1']['OBR.1.1']   = $i;
			$OUT['OBR']['OBR.2']['OBR.2.1']   = $data['mid'];
			$OUT['OBR']['OBR.3']['OBR.3.1']   = '';
			$OUT['OBR']['OBR.4']['OBR.4.1']   = $test['num'];
			$OUT['OBR']['OBR.4']['OBR.4.2']   = $test['drug'];
			$OUT['OBR']['OBR.4']['OBR.4.3']   = 'exc200';
			$OUT['OBR']['OBR.5']['OBR.5.1']   = '';
			$OUT['OBR']['OBR.6']['OBR.6.1']   = '';
			$OUT['OBR']['OBR.7']['OBR.7.1']   = '';
			$OUT['OBR']['OBR.8']['OBR.8.1']   = '';
			$OUT['OBR']['OBR.9']['OBR.9.1']   = '';
			$OUT['OBR']['OBR.10']['OBR.10.1'] = '';
			$OUT['OBR']['OBR.11']['OBR.11.1'] = 'A';
			$i++;
		}

		$OUT['NTE'] = array();
		$OUT['NTE']['NTE.1']['NTE.1.1'] = '0';
		$OUT['NTE']['NTE.2']['NTE.2.1'] = '';
		$OUT['NTE']['NTE.3']['NTE.3.1'] = 'Order comment';


		$msg = $this->array_to_hl7($OUT);
		return $this->write_msg($msg);
	}

	public function run()
	{
		$db = new DB($this->server->dbconfig, $this->client_id);

		$buf            = '';
		$data           = '';
		$last_buf       = '';
		$msg_started    = false;


		try {
			date_default_timezone_set($this->server->dbconfig['time_zone']);
			if ($this->address != '127.0.0.1') {
				echo date('Y-m-d H:i:s') . " - Client connected - {$this->address} #{$this->client_id}\n";
			}


			if ($this->server->debug) {
				//$this->log("<NL><NL>".date('H:i:s')." - connected<NL>");
			}
		} catch (Exception $e) {
		}

		//$this->sendraw($this->server->CHARS['EOT']);
		$count_read_fail = 0;
		do {
			try {

				if ($this->need_stop) {
					break;
				}

				if (false === ($buf = fread($this->msgsock, 1))) {
					if ($this->address != '127.0.0.1') {
						//	echo date('Y-m-d H:i:s') . " - {$this->address} #{$this->client_id} - error: Read false\n";
					}
					$count_read_fail++;
					if ($count_read_fail < 3000) continue;
					break;
				}

				if (strlen($buf) == 0) {
					if (feof($this->msgsock)) {
						if ($this->address != '127.0.0.1') {
							echo date('Y-m-d H:i:s') . " - {$this->address} #{$this->client_id} - end msg\n";
						}
						break;
					}

					continue;
				}



				if ($this->server->CHARS['VT'] == $buf) {
					$msg_started = true;
					$data = '';
					continue;
				}


				if ($msg_started && ($this->server->CHARS['FS'] == $last_buf || $last_buf == $this->server->CHARS['CR']) && $this->server->CHARS['CR'] == $buf) {
					$msg_started    = false;
					$created        = date('Y-m-d H:i:s');


					//parse data
					$arr = $this->hl7_to_array($data);

					if ($this->server->debug) {
						$this->log("<NL><NL><NL>{$created} - {$this->address} #{$this->client_id} =>Rev:<NL>");
						$this->log($this->server->CHARS['VT'] . $data . $buf . "<NL>");
						$this->log("<NL>" . json_encode($arr) . "<NL>");
					} else {
						touch($this->server->log_file);
					}

					$stype = $arr['MSH']['MSH.9']['MSH.9.1'];

					switch ($stype) {

						case 'ORU':

							$assen = trim($arr['PID']['PID.3']['PID.3.1']);
							if (empty($assen)) {
								$assen = trim($arr['OBR']['OBR.2']['OBR.2.1']);
							}

							$ack_status = 'AA';

							if ($this->server->debug) {
								$this->log("{$assen}=>");
							}
							if (!empty($assen)) {
								$btype = 'blood';
								$mid = $db->get_mid($assen, 'blood');
								if ($this->server->debug) {
									$this->log("{$mid}<NL>");
								}

								$report_id = 0;
								$report = $db->get_report($assen, $btype);
								if (!$report) {
									$report_id = $db->create_report($assen, $btype, API_NAME);
								} else if ($report['estatus'] == 'C' && in_array($report['final_results'], array(1, 3))) {
									$approvedtype = $report['final_results'] == 3 ? 'Amended' : 'Final';
									$this->log("<NL>Report is approved as {$approvedtype}<NL>");
									$report_id = 0;
								} else {
									$report_id = $report['id'];
									$db->update_report($assen, $btype, API_NAME);
								}

								if ($report_id > 0) {
									$this->log("reportID: {$report_id} <NL>");

									$logarr = array();
									$logstr = '';
									$count_rs = 0;
									foreach ($arr['OBX'] as $j => $obx) {
										$drug = $obx["OBX.3"]["OBX.3.1"];
										if (empty($drug)) {
											$drug = $obx["OBX.3"]["OBX.3.2"];
										}
										$conc = $obx["OBX.5"]["OBX.5.1"];



										$rs = $db->insert_result($report_id, array(
											'drug' => $drug,
											'conc' => $conc,
											'key' => 'te_exc200',
											'instrument' => API_NAME,
										), 'blood');

										$this->log("{$drug}: {$db->last_drug} : {$conc} <NL>");

										if ($rs === false) {
											$this->log("<NL>insert data error for mid :{$mid} => {$db->last_query}<NL>");
											$logarr[$drug] = array($conc, $db->old_conc, 0);
										} else {
											$logarr[$drug] = array($conc, $db->old_conc, 1);
											$count_rs++;
										}
									}

									$db->set_received_result('blood', $mid, API_ID, $logarr);

									if (!$this->is_test && $count_rs > 0) {
										$db->reverse_report($assen, $btype);
									}
								}
							} else {
								$logdata = 'Not found: ' . $assen;
								$mid = 0;
								$ack_status = 'CA';
								if (empty($assen)) {
									$ack_status = 'AA'; //for test
								}
							}
							$this->send_ack($arr, $ack_status);

							break;

						case 'QRY':
							$assen = trim($arr['QRD']['QRD.8']['QRD.8.1']);
							$mid = 0;
							if (!empty($assen)) {
								$mid = $db->get_mid($assen, 'blood');
							}

							$this->send_qck($arr, $mid > 0 ? 'OK' : 'NF');

							break;

						default:
							$this->send_ack($arr, 'AA');
							break;
					}


					$data = '';
					$db->disconnect();
				} else if (!$this->is_test && $buf == $this->server->CHARS['LF'] && $last_buf == $this->server->CHARS['SOH']) {
					$this->is_test = true;
					$this->sendraw("hello test: " . $this->address);
				}

				if ($msg_started && $buf != $this->server->CHARS['FS']) {
					$data .= $buf;
				}
				$last_buf = $buf;
			} catch (Error $e) {
				$this->log("<NL>" . date('H:i:s') . " -  error: " . $e->getMessage() . '<NL>');
			}
		} while (true);

		$db->disconnect();

		$this->stop();

		if ($this->address != '127.0.0.1') {
			echo date('Y-m-d H:i:s') . " - Client dropped - {$this->address} #{$this->client_id}\n";
		}
	}
}


$address    = '0.0.0.0';


echo 'version: ' . API_VERSION . "\n";
echo 'Timezone: ' . $dbconfig['time_zone'] . "\n";
echo 'load config file: ' . basename($config_file) . "\n";

$wip_file = $config_dir . '/whiteips.txt';
if (!file_exists($wip_file)) {
	echo 'No config white ips' . "\n";
}
echo date('Y-m-d H:i:s') . ' - Starting port: ' . $port . "...";


if (!empty($argv[3]) && stripos($argv[3], 'log/') > 0) {
	$dbconfig['path'] =  $argv[3];
}

$server = new IntrumentServer($port, $address, $dbconfig);
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
