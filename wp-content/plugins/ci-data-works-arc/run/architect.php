<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

//open port 8689:  iptables -A INPUT -m state --state NEW -p tcp --dport 8689 -j ACCEPT
//service iptables save
// cd /mnt/data/vhosts/default/wp-content/plugins/ci-data-works-ark/run/
// nohup zts-php architect.php port 8689 > out.txt 2> error.txt < /dev/null &

date_default_timezone_set('America/Detroit');

require("db.php");


class IntrumentServer
{

	public static $port = '';
	public static $filelog = '';
	public static $path = '';
	public static $config = array();

	public $db = null;
	public $dbconfig = array();
	private $sockServer;
	private $address;
	public static $pid_file = 'architectpid.txt';
	public static $stop_file = 'architectstop.txt';
	public static $debug = false;

	const API_TYPE = 5;

	const VT = 0x0B;
	const FS = 0x1C;
	const CR = 0x0D;

	public function __construct($port, $address, $dbconfig)
	{
		$this->address = $address;
		$this->dbconfig = $dbconfig;
		IntrumentServer::$port = $port;
		if (empty(IntrumentServer::$path)) {
			IntrumentServer::$path = dirname(__FILE__) . '/architect';
		}
		IntrumentServer::$filelog = IntrumentServer::$path . '/log_' . IntrumentServer::$port . '.txt';
		IntrumentServer::$pid_file = IntrumentServer::$path . '/pid_' . IntrumentServer::$port . '.txt';
		IntrumentServer::$stop_file = IntrumentServer::$path . '/stop_' . IntrumentServer::$port . '.txt';

		if (file_exists(IntrumentServer::$stop_file)) {
			unlink(IntrumentServer::$stop_file);
		}

		file_put_contents(IntrumentServer::$pid_file, '');
		IntrumentServer::$config = array();

		$this->db = new DB($dbconfig);
	}

	public function create()
	{
		printf(" - creating socket...");
		$port = IntrumentServer::$port;

		$this->sockServer = stream_socket_server("tcp://{$this->address}:{$port}", $errno, $errstr);
		if (!$this->sockServer) {
			return false;
		}
		return true;
	}

	public function setDebug($debug)
	{
		IntrumentServer::$debug = $debug;
	}

	public function add_pid($pid)
	{
		try {
			$f = fopen(IntrumentServer::$pid_file, 'a+');
			fwrite($f, $pid . "\n");
			fclose($f);
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
				if (($msgsock = stream_socket_accept($this->sockServer, 90, $address)) === false) {
					restore_error_handler();
					echo $address;
					continue;
				}

				if (strpos($address, ':') > 0) {
					list($address, $rmport) = explode(':', $address);
				}

				//check if kill file exit
				if (file_exists(IntrumentServer::$stop_file)) {
					foreach ($clients as $client) {
						$client->stop();
					}

					stream_socket_shutdown($msgsock, STREAM_SHUT_WR);
					break;
				}

				if (!in_array($address, array('96.64.0.94', '96.64.0.93', '127.0.0.1', '192.64.78.66'))) {
					//stream_socket_shutdown($msgsock,STREAM_SHUT_WR);
					echo date('Y-m-d H:i:s') . " - Client not accepted by server -{$address}\n";
					//continue;
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
}


class Client_Thread extends \Thread
{

	private $msgsock = null;
	private $server;
	private $address;
	private $need_stop = false;
	private $client_id = null;

	public function __construct($msgsock, $server, $clientid)
	{
		$this->address = stream_socket_get_name($msgsock, true);
		if (strpos($this->address, ':') > 0) {
			list($this->address, $rmport) = explode(':', $this->address);
		}

		$this->msgsock = $msgsock;
		$this->server = $server;
		$this->client_id = $clientid;

		$this->start();
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

	function log($log)
	{
		$filelog = IntrumentServer::$path . '/log_' . IntrumentServer::$port . '_' . date('Y-m-d') . '.txt';
		$log = str_replace(chr(IntrumentServer::VT), '<VT>', $log);
		$log = str_replace(chr(IntrumentServer::FS), '<FS>', $log);
		$log = str_replace(chr(IntrumentServer::CR), '<CR>' . "\n", $log);
		$log = str_replace('<NL>', "\n", $log);

		$f = fopen($filelog, "a+");
		if ($f) {
			fwrite($f, $log);
			fclose($f);
		}
	}

	function write_msg($msgsock, $talkback)
	{
		$talkback = chr(IntrumentServer::VT) . $talkback . chr(IntrumentServer::FS) . chr(IntrumentServer::CR);
		if (IntrumentServer::$debug) {
			$date = date('Y-m-d H:i:s');
			$log = "<NL>{$date} - {$this->address} #{$this->client_id} => sendout: <NL>";
			$log .= $talkback;
			$log .= "<NL>==========<NL><NL><NL>";
			$this->log($log);
		}

		return fputs($msgsock, $talkback, strlen($talkback));
	}


	function architect_to_array($string)
	{

		$multis = array('OBX');

		$string = str_replace("\r\n", "\n", $string);
		$string = str_replace("\n", "\r", $string);
		$segs = explode("\r", $string);

		$out = array();

		//get delimiting characters

		if (substr($segs[0], 0, 3) != 'MSH') {

			$out['ERROR'][0] = 'Invalid architect Message.';
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

	function array_to_architect($arr)
	{
		$FLD_SEP = isset($arr['FLD_SEP']) && !empty($arr['FLD_SEP']) ? $arr['FLD_SEP'] : '^';
		$architect = array();
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
					$architect[] = $name1 . '|' . implode('|', $seg);
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
					$architect[] = $segname . '|' . implode('|', $seg);
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
				$architect[] = $segname . '|' . implode('|', $segments);
			}
		}
		$architect = implode("\r", $architect);
		return $architect;
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

		$msg = $this->array_to_architect($OUT);
		return $this->write_msg($this->msgsock, $msg);
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

				//TQ1|1||||||||S^Stat^architect0485
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
				$OUT[$i]['OBR']['OBR.4']['OBR.4.3']   = 'ARCHITECT';
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




		$msg = $this->array_to_architect($OUT);
		return $this->write_msg($this->msgsock, $msg);
	}

	/* Accept
	MSH|^~\&|LISApp|MainFacility|architectApp|LabFacility|20160801103758||OML^O33^OML_O33|7e68205e- a431-464c-afd7-0115b5baf653|P|2.5.1|||NE|AL||UNICODE UTF-8|||LAB-28^IHE
	PID|||100||Doe^John^Lee^^^^L||19500214|M
	PV1||N|^ER 2
	SPM|1|||""|||||||P^Patient^architect0369
	SAC|||S000501 ORC|NW||||||||20160801103758 TQ1|1||||||||S^Stat^architect0485
	ORC|NW||||||||20160801103758
	TQ1|1||||||||S^Stat^architect0485
	OBR||ORDER#1005||249^TSH^99ABT|||||||A
	NTE|0||Order comment
	 *
	 */

	/* REjected
	 * MSH|^~\&|LISApp|MainFacility|architectApp|LabFacility|20160801103902||OML^O33^OML_O33|b13bff13- ba63-4a50-9cb5-28d3d4df7c97|P|2.5.1|||NE|AL||UNICODE UTF-8|||LAB-28^IHE
	 * PID|||100||Doe^John^Lee^^^^L||19500214|M
	 * PV1||N|^ER 2
	 * SPM|1|||""|||||||P^Patient^architect0369
	 * SAC|||S000501
	 * ORC|NW||||||||20160801103902
	 * TQ1|1||||||||S^Stat^architect0485
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
		$OUT['MSH']['MSH.5']['MSH.5.1'] = 'architectApp';
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

		//TQ1|1||||||||S^Stat^architect0485
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
		$OUT['TQ1']['TQ1.9']['TQ1.9.3'] = 'architect0485';

		$i = 0;
		$OUT['OBR'] = array();
		foreach ($data['tests'] as $test) {

			$OUT['OBR']                     = array();
			$OUT['OBR']['OBR.1']['OBR.1.1']   = $i;
			$OUT['OBR']['OBR.2']['OBR.2.1']   = $data['mid'];
			$OUT['OBR']['OBR.3']['OBR.3.1']   = '';
			$OUT['OBR']['OBR.4']['OBR.4.1']   = $test['num'];
			$OUT['OBR']['OBR.4']['OBR.4.2']   = $test['drug'];
			$OUT['OBR']['OBR.4']['OBR.4.3']   = 'ARCHITECT';
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


		$msg = $this->array_to_architect($OUT);
		return $this->write_msg($this->msgsock, $msg);
	}

	public function run()
	{
		$db = new DB($this->server->dbconfig);
		$db->connect();

		$buf = "";
		$data = '';
		$msg_started = false;
		$user_id = IntrumentServer::API_TYPE * -1;


		date_default_timezone_set('America/Detroit');
		echo date('Y-m-d H:i:s') . " - Client connected - {$this->address} #{$this->client_id}\n";

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
						break;
					}
					//echo date('Y-m-d H:i:s')." - {$this->address} #{$this->client_id} - buf empty\n";
					continue;
				}



				if (chr(IntrumentServer::VT) == $buf) {
					$msg_started = true;
					$data = '';
					continue;
				}


				if ($msg_started && (chr(IntrumentServer::FS) == $buf)) {
					$msg_started = false;
					$created = date('Y-m-d H:i:s');


					//parse data
					$arr = $this->architect_to_array($data);

					if (IntrumentServer::$debug) {
						$this->log("<NL><NL><NL>{$created} - {$this->address} #{$this->client_id} =>Rev:<NL>");
						$this->log(chr(IntrumentServer::VT) . $data . $buf . "<NL>");
						//$this->log("<NL>".json_encode($arr)."<NL>");
					} else {
						touch(IntrumentServer::$filelog);
					}

					$stype = $arr['MSH']['MSH.9']['MSH.9.1'];

					switch ($stype) {
						case 'NMD': //connection test
							$this->send_ack($arr, 'AA');
							break;

						case 'QBP':

							//send request
							$assen = $arr['QPD']['QPD.3']['QPD.3.1'];
							if (!empty($assen)) {
								$batch = $db->get_batch_data($assen, 'tox');
								if ($batch) {
									$drugs = $db->get_tests_specimens($assen);
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
											'dob' => $batch['dob'],
											'sex' => $batch['pa_gender'],
											'tests' => $tests
										);
										$this->send_rsp($arr, $data, 'AA');
									}
								} else {
									$this->send_rsp($arr, null, 'AA');
								}
							} else {
								$this->send_rsp($arr, null, 'AA');
							}


							break;

						case 'SSU':
							$this->send_ack($arr, 'AA');
							break;

						case 'TCU':
							$this->send_ack($arr, 'AA');
							break;

						case 'OUL':

							$assen = trim($arr['SAC']['SAC.3']['SAC.3.1']);
							if (empty($assen)) {
								$assen = trim($arr['SPM']['SPM.2']['SPM.2.1']);
							}
							$mid = $db->get_mid($assen, 'tox');
							if (IntrumentServer::$debug) {
								$this->log("{$assen}: {$mid}<NL>");
							}
							$ack_status = 'AA';
							$logdata = '';
							if ($mid > 0) {
								$testids = $db->get_all_tests_ids_architect();

								foreach ($arr['OBX'] as $j => $obx) {
									$test_num = $obx["OBX.3"]["OBX.3.1"];
									if (stripos($test_num, '&') > 0) {
										list($test_num) = explode('&', $test_num);
									}
									$conc = $obx["OBX.5"]["OBX.5.1"];
									$obxnum = $obx["OBX.1"]["OBX.1.1"];
									if ($obxnum == '1') {
										$testid = isset($testids[$test_num]) ? $testids[$test_num] : 0;

										if (IntrumentServer::$debug) {
											$this->log("{$test_num}: {$testid} : {$conc}<NL>");
										}

										if ($testid > 0) {
											$sql = "INSERT INTO ci_result_test_speciments (mid,testid,conc,api_type)
                                        values ('{$mid}','{$testid}','{$conc}','" . IntrumentServer::API_TYPE . "')
                                        ON DUPLICATE KEY UPDATE conc = '{$conc}', api_type = " . IntrumentServer::API_TYPE;
											$rs = $db->query($sql);

											if ($rs === false) {
												$this->log("<NL>insert data error for mid :{$mid} => {$sql}<NL>");
											}
										}
									}
								}
							} else {
								$logdata = 'Not found: ' . $assen;
								$mid = 0;
								$ack_status = 'AE';
							}
							$this->send_ack($arr, $ack_status);
							//log user

							$rs = $db->query("INSERT INTO ci_log_users (user_id,user_action,ip,mid,btype,data,created)
											values({$user_id},'architect_rs','{$this->address}',{$mid},'tox','{$logdata}','{$created}')");
							if ($rs === false) {
								$this->log("insert log error for mid :{$mid} tox<NL>");
							}


							break;

						default:
							$this->send_ack($arr, 'AA');
							break;
					}


					$data = '';
				}

				if ($msg_started) {
					$data .= $buf;
				}
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


$address = '0.0.0.0';
$port = $argv[2] > 80 ? $argv[2] : 8689;
$dbconfig = array(
	'host' => 'db',
	'user' => 'ARK_default',
	'pass' => 'B7uyNDstzH7G',
	'name' => 'ARK_Laboratory',
	'port' => '3306'
);

echo date('Y-m-d H:i:s') . ' - Starting port: ' . $port . "...";


if (!empty($argv[3]) && stripos($argv[3], 'log/') > 0) {
	IntrumentServer::$path = $argv[3];
}
$server = new IntrumentServer($port, $address, $dbconfig);

$server->setDebug(true);

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
