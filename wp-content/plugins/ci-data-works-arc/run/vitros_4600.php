<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

//open port 8322:  iptables -A INPUT -m state --state NEW -p tcp --dport 8322 -j ACCEPT
//service iptables save
// cd /mnt/data/vhosts/default/wp-content/plugins/ci-data-works-arc/run/
// nohup zts-php vitros_4600.php port 8322 > outvitros_4600.txt 2> errorvitros_4600.txt < /dev/null &


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
const API_NAME = 'vitros_4600';
const API_ID = -92;
const DEBUG = true;



$port       = $argv[2] > 80 ? $argv[2] : 8322;

$config_dir = dirname(dirname(dirname(dirname(__FILE__)))) . '/config';
$config_file = $config_dir . '/vitros_4600_' . $port . '.php';
if (!file_exists($config_file)) {
	$config_file = $config_dir . '/vitros_4600.php';
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
	public $pid_file = 'sysmex_pid.txt';
	public $stop_file = 'sysmex_stop.txt';
	public $debug = false;
	public $CHARS = array();


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
	const FS = 0x1C;

	public function __construct($port, $address, $dbconfig)
	{
		$this->CHARS = array(
			'SOH' => chr(IntrumentServer::SOH),
			'STX' => chr(IntrumentServer::STX),
			'ETX' => chr(IntrumentServer::ETX),
			'ETB' => chr(IntrumentServer::ETB),
			'EOT' => chr(IntrumentServer::EOT),
			'ENQ' => chr(IntrumentServer::ENQ),
			'ACK' => chr(IntrumentServer::ACK),
			'NAK' => chr(IntrumentServer::NAK),
			'LF' => chr(IntrumentServer::LF),
			'VT' => chr(IntrumentServer::VT),
			'CR' => chr(IntrumentServer::CR),
			'FS' => chr(IntrumentServer::FS),
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


class Framemsg extends Volatile
{

	public $can_send = false;
	public $sent_enq = false;

	public $max_bytes_per_frame = 240;

	public $data = array(
		'count' => 0,
		'nak_count' => 0,
		'position' => 0,
		'messages' => array()
	);

	public function __construct($max_bytes_per_frame)
	{
		if ($max_bytes_per_frame > 240) {
			$this->max_bytes_per_frame = $max_bytes_per_frame;
		}
	}

	public function reset()
	{
		$this->can_send = false;
		$this->sent_enq = false;
		$this->data = array(
			'count' => 0,
			'nak_count' => 0,
			'position' => -1,
			'messages' => array()
		);
	}

	public function currentPosition()
	{
		$pos = $this->data['position'];
		if ($pos < 0) {
			$pos = 0;
		}
		return $pos;
	}

	public function hasNext()
	{
		return $this->currentPosition() + 1 < $this->data['count'];
	}

	public function hasMessage()
	{
		return isset($this->data['messages']['p_' . $this->currentPosition()]);
	}

	public function next()
	{
		if (!$this->hasNext()) {
			return false;
		}
		$this->data['position'] = $this->data['position'] + 1;

		return isset($this->data['messages']['p_' . $this->data['position']]);
	}

	public function canSend()
	{
		if ($this->sent_enq) {
			$this->sent_enq = false;
			$this->can_send = true;
		}
		return $this->can_send;
	}

	public function nak()
	{
		$this->data['nak_count'] = $this->data['nak_count'] + 1;

		if ($this->data['nak_count'] > 3) {
			$this->reset();
		}
	}

	public function isEnd()
	{
		return $this->currentPosition() + 1 >= $this->data['count'];
	}

	public function getCount()
	{
		return $this->data['count'];
	}

	public function getFrameNum()
	{
		$framenum = $this->currentPosition() + 1;
		if ($framenum > 7) {
			$framenum = $framenum % 8;
		}
		return $framenum;
	}

	public function getMsg()
	{
		return $this->data['messages']['p_' . $this->currentPosition()];
	}

	public function addMessage($msg)
	{
		$maxchar = $this->max_bytes_per_frame;
		$count = ceil(strlen($msg) / $maxchar);

		for ($i = 0; $i < $count; $i++) {
			$num = $i + $this->data['count'];
			$this->data['messages']['p_' . $num] = substr($msg, $maxchar * $i, $maxchar);
		}
		$this->data['count'] += $count;
	}

	public function setMessage($msg)
	{
		$maxchar = $this->max_bytes_per_frame;
		$this->reset();

		$this->data['count'] = ceil(strlen($msg) / $maxchar);

		for ($i = 0; $i < $this->data['count']; $i++) {
			$this->data['messages']['p_' . $i] = substr($msg, $maxchar * $i, $maxchar);
		}
	}
};

class Client_Thread extends \Thread
{

	private $msgsock = null;
	private $server;
	private $address;
	private $need_stop = false;
	private $client_id = null;
	private $is_test = false;
	private $framemsg;


	public function __construct($msgsock, $server, $clientid)
	{
		$this->address = stream_socket_get_name($msgsock, true);
		if (strpos($this->address, ':') > 0) {
			list($this->address, $rmport) = explode(':', $this->address);
		}
		$this->msgsock = $msgsock;
		$this->server = $server;
		$this->client_id = $clientid;

		$max_num = isset($this->server->dbconfig['max_bytes_per_frame']) ? $this->server->dbconfig['max_bytes_per_frame'] : 0;

		$this->framemsg = new Framemsg($max_num);

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
					echo 'parent' . "\n";
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
			$filelog = $this->server->path . '/error_' . $type . '_' . $this->server->port . '_' . $date . '.txt';
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
	}

	function cal_checksum($string)
	{
		$crc = 0;
		for ($i = 0; $i < strlen($string); $i++) {
			$crc += ord(substr($string, $i, 1));
		}
		return strtoupper(substr(dechex($crc), -2));
	}

	function write_msg()
	{
		$sent = 0;
		if ($this->framemsg->hasMessage()) {

			if (!$this->framemsg->can_send) {
				$this->sendraw($this->server->CHARS['ENQ']);
				$this->framemsg->can_send = true;
				sleep(2);
			}

			$msg = $this->framemsg->getMsg();

			$framenum = $this->framemsg->getFrameNum();
			$endchar  = $this->server->CHARS['ETX'];

			$checksum = $this->cal_checksum($framenum . $msg . $endchar);

			$talkback = $this->server->CHARS['STX'] . $framenum . $msg . $endchar;
			$talkback .= $checksum;
			$talkback .= $this->server->CHARS['CR'] . $this->server->CHARS['LF'];



			$sent = $this->sendraw($talkback);
		} else {
			$this->sendraw($this->server->CHARS['EOT']);
		}

		return $sent;
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


	function parse_data($msg)
	{

		$multis = array('R', 'C', 'O');
		$out = array();

		$segs = explode($this->server->CHARS['CR'], $msg);
		foreach ($segs as $fseg) {
			$fseg = trim($fseg);
			if (empty($fseg)) {
				continue;
			}
			$segments = explode('|', $fseg);
			$segname = $segments[0];
			$i = 1;
			$ic = isset($out[$segname]) ? count($out[$segname]) : 0;

			foreach ($segments as $k => $seg) {

				$j = 1;
				$sf = explode('^', $seg);
				foreach ($sf as $f) {
					if (in_array($segname, $multis)) {
						$out[$segname][$ic][$segname . '.' . $i][$segname . '.' . $i . '.' . $j] = $f;
					} else {
						$out[$segname][$segname . '.' . $i][$segname . '.' . $i . '.' . $j] = $f;
					}

					$j++;
				}
				$i++;
			}
		}

		return $out;
	}

	function build_data($arr)
	{
		$arr['L'] = array(
			'L.1' => array(
				'L.1.1' => 'L'
			),
			'L.2' => array(
				'L.2.1' => '1'
			),
			'L.3' => array(
				'L.3.1' => 'F'
			)
		);

		foreach ($arr as $segname => $segments) {
			$keys = array_keys($arr[$segname]);
			$max = end($keys);
			list($kt, $max) = explode('.', $max);
			$max = intval($max);
			$temp = array();
			for ($i = 1; $i <= $max; $i++) {
				$key = $segname . '.' . $i;
				$temp[$key] = array(
					$key . '.1' => ($i == 1 ? $segname : '')
				);
			}

			foreach ($arr[$segname] as $k => $vl) {
				$temp[$k] = $vl;
			}
			$arr[$segname] = $temp;
		}



		$FLD_SEP = '^';
		$msg = array();
		foreach ($arr as $segname => $segments) {
			if (isset($segments[0])) {
				foreach ($segments as $seg) {
					foreach ($seg as $i => $item) {
						if (is_array($item)) {
							$item = implode($FLD_SEP, $item);
						}

						$seg[$i] = $item;
					}
					$msg[] = implode('|', $seg);
				}
			} else {
				foreach ($segments as $i => $item) {
					if (is_array($item)) {
						$item = implode($FLD_SEP, $item);
					}

					$segments[$i] = $item;
				}
				$msg[] = implode('|', $segments);
			}
		}

		$this->framemsg->reset();
		foreach ($msg as $m) {
			$this->framemsg->addMessage($m . $this->server->CHARS['CR']);
		}
	}

	function send_request_sample($arr_request, $sample, $tests = array())
	{

		$fluid_num = '4';
		if (stripos($sample['type_of_specimen'], 'urine') !== false) {
			$fluid_num = '3';
		} else if (stripos($sample['type_of_specimen'], 'Serum') !== false) {
			$fluid_num = '5';
		} else if (stripos($sample['type_of_specimen'], 'Plasma') !== false) {
			$fluid_num = '6';
		} else if (stripos($sample['type_of_specimen'], 'Amniotic') !== false) {
			$fluid_num = '7';
		} else if (stripos($sample['type_of_specimen'], 'Reserved') !== false) {
			$fluid_num = '8';
		} else if (stripos($sample['type_of_specimen'], 'SWAB') !== false) {
			$fluid_num = '10';
		}

		$sample['pa_dob'] = str_replace('-', '', $sample['pa_dob']);
		if (!isset($sample['doc']) || empty($sample['doc'])) {
			$sample['doc'] = date('Y-m-d H:i:s');
		}

		$arr = array();

		$arr['H'] = array(
			'H.1' => array(
				'H.1.1' => 'H',
			),
			'H.2' => array(
				'H.2.1' => '\^&',
			),
			'H.5' => array(
				'H.5.1' => 'LIS',
			),
			'H.13' => array(
				'H.13.1' => 'LIS2-A',
			),
			'H.14' => array(
				'H.14.1' => date('YmdHis'),
			),
		);

		$arr['P'] = array(
			'P.1' => array(
				'P.1.1' => 'P'
			),
			'P.2' => array(
				'P.2.1' => '1'
			),
			'P.3' => array(
				'P.3.1' => $sample['patient_id']
			),
			'P.6' => array(
				'P.6.1' => $sample['pa_last'],
				'P.6.2' => $sample['pa_first'],
				'P.6.3' => '',
			),
			'P.8' => array(
				'P.8.1' => $sample['pa_dob']
			),
			'P.9' => array(
				'P.9.1' => $sample['pa_gender']
			),
			'P.14' => array(
				'P.14.1' => isset($sample['doc_last']) ? $sample['doc_last'] : '',
				'P.14.2' => isset($sample['doc_first']) ? $sample['doc_first'] : '',
				'P.14.3' => '',
			),
			'P.15' => array(
				'P.15.1' => '' //1 | Range Attribute
			),
			'P.26' => array(
				'P.26.1' => '' //10 | patient room number
			),
		);


		$testsargs = array();
		foreach ($tests as $test) {
			$testsargs[] =  $test;
		}
		$testsargs = '1.0+' . implode('+1.0\\', $testsargs) . '+1.0';
		$arr['O'] = array(
			'O.1' => array(
				'O.1.1' => 'O'
			),
			'O.2' => array(
				'O.2.1' => '1'
			),
			'O.3' => array(
				'O.3.1' => $sample['assen'],
				'O.3.2' => '',
				'O.3.3' => '',
			),
			'O.5' => array(
				'O.5.1' => '',
				'O.5.2' => '',
				'O.5.3' => '',
				'O.5.4' => $testsargs,
			),
			'O.6' => array(
				'O.6.1' => 'R'
			),
			'O.8' => array(
				'O.8.1' => date('YmdHis', strtotime($sample['doc']))
			),
			'O.12' => array(
				'O.12.1' => 'N'
			),
			'O.16' => array(
				'O.16.1' => $fluid_num
			),
			'O.26' => array(
				'O.26.1' => 'O'
			)
		);

		/* O.3.4:
		 * M: Manual input
		  A: Automatic assignment by analyzer B: Barcode reader
		  C: Assignment by host computer
		 */

		/*
		 O.26= O, P, F
		O for downloading
		P for uploading when some tests are
		still in progress
		F for uploading when all tests in
		sample are complete
		*/


		$this->build_data($arr);


		return $this->send_enq();
	}

	public function send_enq()
	{
		$this->framemsg->sent_enq = true;
		return $this->sendraw($this->server->CHARS['ENQ']);
	}

	public function run()
	{
		$db = new DB($this->server->dbconfig);


		$frame_num = 0;
		$last_buf = '';
		$frame_messages = array();
		$frame_started = false;
		$message_ended = false;


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

		do {
			try {

				if ($this->need_stop) {
					break;
				}

				if (false === ($buf = fread($this->msgsock, 1))) {
					if ($this->address != '127.0.0.1') {
						echo date('Y-m-d H:i:s') . " - {$this->address} #{$this->client_id} - error: Read false\n";
					}
					break;
				}

				if (strlen($buf) == 0) {
					if (feof($this->msgsock)) {
						if ($this->address != '127.0.0.1') {
							echo date('Y-m-d H:i:s') . " - {$this->address} #{$this->client_id} - end msg\n";
						}
						break;
					}/*else if(!$this->sendraw($this->server->CHARS['ACK']))
					{
						if($this->address!='127.0.0.1') {
							echo date( 'Y-m-d H:i:s' ) . " - {$this->address} #{$this->client_id} - empty buf\n";
						}
						break;
					}*/

					continue;
				}



				if ($this->server->debug) {
					$nl2 = "";
					if ($buf == $this->server->CHARS['LF'] && $last_buf == $this->server->CHARS['CR']) {
						$nl2 = '<NL><NL>';
					} else if ($buf == $this->server->CHARS['ENQ'] || $buf == $this->server->CHARS['ACK']) {
						$nl2 = '<NL><NL>';
					}
					$this->log($buf . $nl2, 'raw');
				}


				if ($buf == $this->server->CHARS['NAK']) {
					if ($this->server->debug) {
						$this->log("<NL><NL>" . date('H:i:s') . " - Rec:<NL>");
						$this->log($buf . "<NL>");
					}

					if ($this->framemsg->canSend() && $this->framemsg->hasNext()) {
						$this->framemsg->nak();
						$this->write_msg();
						$last_buf = $buf;
						continue;
					} else {
						$this->sendraw($this->server->CHARS['EOT']);
						$this->framemsg->reset();
					}
				} else if ($buf == $this->server->CHARS['ACK']) {

					if ($this->server->debug) {
						$this->log("<NL><NL>" . date('H:i:s') . " - Rec:<NL>");
						$this->log($buf . "<NL>");
					}

					//check sending message
					if ($this->framemsg->canSend() && $this->framemsg->hasNext() && $this->framemsg->next()) {
						$this->write_msg();
						$last_buf = $buf;
						continue;
					} else if ($this->framemsg->canSend()) {
						$this->sendraw($this->server->CHARS['EOT']);
						$this->framemsg->reset();
						continue;
					}
				} else if ($buf == $this->server->CHARS['EOT']) {

					//end of message
					$message_ended = true;
					if ($this->server->debug) {
						$this->log("<NL><NL>" . date('H:i:s') . " - Rec:<NL>");
						$this->log($buf . ".<NL>");
					}
					//send back ack
					//$this->sendraw($this->server->CHARS['ACK']);
					//$this->framemsg->can_send = false;
				} else if ($buf == $this->server->CHARS['ENQ']) {
					if ($this->server->debug) {
						$this->log("<NL><NL>" . date('H:i:s') . " -Rec:<NL>");
						$this->log($buf . "<NL>");
					}
					//send back ack
					$this->sendraw($this->server->CHARS['ACK']);

					$last_buf = $buf;
					continue;
				} else if ($buf == $this->server->CHARS['STX']) {
					$frame_started = true;
					$message_ended = false;
					$frame_num++;
					$frame_messages[$frame_num] = '';
				} else if ($buf == $this->server->CHARS['ETX']) {
					//end frame
					//$message_ended = true;
					$frame_started = false;
					if ($this->server->debug) {
						//$this->log("<NL><NL>".date('H:i:s')." -Rec:<NL>");
						//$this->log($buf. "<NL>");
					}
				} else if ($buf == $this->server->CHARS['ETB']) {
					//end frame
					$frame_started = false;
				} else if ($buf == $this->server->CHARS['LF'] && $last_buf == $this->server->CHARS['CR']) {
					//end frame msg
					if (!$frame_started) {
						//log frame msg
						$this->log("<NL><NL>" . date('H:i:s') . " -Rec:<NL>");
						$this->log($frame_messages[$frame_num] . "<NL>");

						//send back ack
						$this->sendraw($this->server->CHARS['ACK']);
					}
				} else if ($frame_started) {
					if ($last_buf == $this->server->CHARS['STX']) {
						//num here
						$num = $buf;
						if ($frame_num >= 8) {
							$num += (8 * (floor($frame_num / 8)));
						}
						if ($num != $frame_num) {
							//error reset all
							$this->log("<NL><NL>" . date('H:i:s') . " - Error: received num {$buf} ({$num}), current: {$frame_num} <NL>");

							$this->sendraw($this->server->CHARS['NAK']);

							$frame_num = 0;
							$frame_started = false;
							$frame_messages = array();

							$last_buf = $buf;
							continue;
						}
					} else if ($frame_started && $frame_num > 0) {
						$frame_messages[$frame_num] .= $buf;
					}
				}

				if ($message_ended && $frame_num > 0) {
					//end all frame
					$all_message_str = implode("", $frame_messages);
					//separe by L|1|N
					$all_messages = explode('L|1|N', $all_message_str);

					foreach ($all_messages as $message) {
						if (strlen($message) < 8) continue;

						$arr = $this->parse_data($message);
						if (empty($arr)) continue;

						if ($this->server->debug) {

							$this->log("<NL><NL>" . date('H:i:s') . " - Parse Message:<NL>");
							$this->log($message . "<NL>");
							$this->log("<NL>" . json_encode($arr) . "<NL>");
						}

						//$sender_name    = $arr['H']['H.5']['H.5.1'];
						//$receiver_name  = $arr['H']['H.10']['H.10.1'];

						$assen          = '';
						if (isset($arr['O'])) {
							foreach ($arr['O'] as $arrO) {
								$assen          = trim($arrO['O.3']['O.3.1']);
								if (!empty($assen)) {
									break;
								}
							}
						}



						if (isset($arr['R'])) {

							$list_assenids = array();
							$list_reportids = array();
							$logarr = array();
							$last_assen = '';
							$btype 		= 'blood';
							$mid 		= 0;
							$count_rs = 0;

							foreach ($arr['R'] as $i => $arrR) {

								if (count($arr['R']) == count($arr['O'])) {
									$assentmp          = trim($arr['O'][$i]['O.3']['O.3.1']);
									if (strlen($assentmp) > 6) {
										$assen = $assentmp;
									}
								}
								if (isset($list_assenids[$assen])) {
									$mid = $list_assenids[$assen];
									if ($last_assen != $assen) {
										$this->log("<NL>Assen: {$assen} ");
										$this->log("ID: {$mid} ");
										$this->log('<NL>');
									}
									$last_assen = $assen;
								} else {
									$mid = $db->get_mid($assen, $btype);
									$list_assenids[$assen] = $mid;
									$last_assen = $assen;
									$this->log("<NL>Assen: {$assen} ");

									if ($mid == 0) {
										//not found assen
										$this->log("Not found ");
									} else {
										$this->log("ID: {$mid} ");
									}
									$this->log('<NL>');
								}


								if ($mid > 0) {

									if (isset($list_reportids[$assen])) {
										$report_id = $list_reportids[$assen];
									} else {
										$batdata = $db->get_batch_data($assen, $btype);
										if (empty($batdata['batch_id'])) {
											$db->update_batch_id($assen, $btype, str_replace('-', '', $batdata['doc']));
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

										$list_reportids[$assen] = $report_id;
										$this->log("reportID: {$report_id} <NL>");
									}


									if ($report_id > 0) {

										$drug = $arrR['R.3']['R.3.4'];
										$conc = $arrR['R.4']['R.4.1'];

										$arrdrug = explode('+', $drug);
										if (isset($arrdrug[1])) {
											$drug = $arrdrug[1];
										}


										$rs = $db->insert_result($report_id, array(
											'drug' => $drug,
											'conc' => $conc,
											'key' => 'te_vitros_4600_code',
											'instrument' => API_NAME,
											'api' => API_ID,
										), 'blood');

										$this->log("{$drug}: {$conc} => {$db->last_drug} =>testid:{$db->last_testid} <NL>");

										if ($rs === false) {
											$this->log("<NL>insert data error for mid :{$mid} => {$db->last_query}<NL>");
											$logarr[$drug] = array($conc, $db->old_conc, 0);
										} else {
											$logarr[$drug] = array($conc, $db->old_conc, 1);
											$count_rs++;
										}
									}
								}
							}

							if ($mid > 0) {
								$db->set_received_result('blood', $mid, API_ID, $logarr);

								if (!$this->is_test && $count_rs > 0) {
									$db->reverse_report($assen, $btype);
								}
							}
						} else if (isset($arr['Q'])) {
							//request order

							$assen = trim($arr['Q']['Q.3']['Q.3.2']);
							if (!empty($assen)) {
								$sample = $db->get_batch_data($assen, 'blood');
								if ($sample) {
									$this->log("Query: {$assen} => mid {$sample['mid']} <NL>");
									$tests = $db->get_abreviations_sample('blood', $sample['panel_tests_requested'], '', 'te_vitros_4600_code');


									if (count($tests) > 0) {
										if ($this->send_request_sample(
											$arr,
											$sample,
											$tests
										)) {
											$db->set_sent_sample('blood', $sample['mid'], API_ID);
										}
									} else {
										$this->log("Query: {$assen} => empty Tests <NL>");
									}
								} else {
									$this->log("Query: {$assen} Not found <NL>");
								}
							}
							sleep(2);
						}
					}



					$frame_started = false;
					$message_ended = false;
					$frame_num = 0;
					$frame_messages = array();
				} else if (!$this->is_test && $buf == $this->server->CHARS['LF'] && $last_buf == $this->server->CHARS['SOH']) {
					$this->is_test = true;
					$this->sendraw("hello test: " . $this->address);
				}

				$last_buf = $buf;
			} catch (Exception $e) {
				var_dump($e);
			}
		} while (true);


		if ($this->address != '127.0.0.1') {
			echo date('Y-m-d H:i:s') . " - Client dropped - {$this->address} #{$this->client_id}\n";
		}
		$db->disconnect();

		$this->stop();
	}
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
