<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

//open port 8681:  iptables -A INPUT -m state --state NEW -p tcp --dport 8681 -j ACCEPT
//service iptables save
// cd /mnt/data/vhosts/default/wp-content/plugins/ci-data-works-arc/run/
// nohup zts-php phoresis.php port 8681 > out.txt 2> error.txt < /dev/null &


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
const API_NAME = 'Phoresis';
const API_ID = -16;
const PROGRAM_CODE = 6;
const DEBUG = true;



$port       = $argv[2] > 80 ? $argv[2] : 8681;

$config_dir = dirname(dirname(dirname(dirname(__FILE__)))) . '/config';
$config_file = $config_dir . '/phoresis_' . $port . '.php';
if (!file_exists($config_file)) {
	$config_file = $config_dir . '/phoresis.php';
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

		$count_client = 0;
		while (true) {
			try {

				$need_continue = false;
				$address = '';
				if (($msgsock = @stream_socket_accept($this->sockServer, 90, $address)) === false) {
					restore_error_handler();
					echo $address;
					$need_continue = true;
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

				if ($need_continue) {
					continue;
				}

				if (!$this->checkwhiteip($address)) {
					stream_socket_shutdown($msgsock, STREAM_SHUT_WR);
					echo date('Y-m-d H:i:s') . " deny:{$address}\n";
					continue;
				}

				$count_client++;
				$client = new Client_Thread($msgsock, $this, $count_client, $address);
				$client->start();
				$clients[] = $client;
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
			echo 'no config file white ip';
		}
		return true;
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

	public function __construct($msgsock, $server, $clientid, $address)
	{
		$this->msgsock      = $msgsock;
		$this->server       = $server;
		$this->client_id    = $clientid;
		$this->address      = $address;
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

	function fillzero($num, $maxlength)
	{
		$length = strlen($num);
		if ($length < $maxlength) {
			$num = str_repeat('0', $maxlength - $length) . $num;
		}

		return $num;
	}

	function fill_space($text, $maxlength)
	{
		$length = strlen($text);
		if ($length < $maxlength) {
			$text = $text . str_repeat(' ', $maxlength - $length);
		} else if ($length > $maxlength) {
			$text = substr($text, 0, $maxlength);
		}

		return $text;
	}

	function format_date($date, $format = 'dmY')
	{
		return date($format, strtotime($date));
	}

	function get_age($dob, $today = '')
	{
		if (empty($today)) {
			$today = date("Y-m-d");
		} else {
			$today = date('Y-m-d', strtotime($today));
		}
		$diff = date_diff(date_create($dob), date_create($today));
		$years = (int)$diff->format('%y');

		return $years;
	}

	function write_msg($msg)
	{
		if (DEBUG) {
			$date = date('H:i:s');
			$log = "<NL>{$date} - Send: <NL>";
			$log .= $msg;
			$log .= "<NL><NL><NL>";
			$this->log($log);
		}

		return fputs($this->msgsock, $msg);
	}


	function send_message1($startnum = 1, $endnum = 9999, $arr)
	{
		//11bytes
		$msg = $this->server->CHARS['STX'];
		$msg .= isset($arr['program_code']) ? $arr['program_code'] : PROGRAM_CODE;
		$msg .= $this->fillzero($startnum, 4);
		$msg .= $this->fillzero($endnum, 4);
		$msg .= $this->server->CHARS['ETX'];

		return $this->write_msg($msg);
	}

	//ACK
	function send_message2()
	{
		//3bytes
		$msg = $this->server->CHARS['STX'];
		$msg .= $this->server->CHARS['ACK'];
		$msg .= $this->server->CHARS['ETX'];

		return $this->write_msg($msg);
	}

	function send_message3($number, $req_data = array(), $arr, $department = '', $Concentration = '', $frees = array(), $is_end = false)
	{
		//247bytes
		$msg = $this->server->CHARS['STX'];

		$msg .= isset($arr['program_code']) ? $arr['program_code'] : PROGRAM_CODE;
		$msg .= $this->fillzero($number, 4);
		$msg .= $this->fill_space($req_data['assen'], 15);
		$msg .= $this->fill_space($req_data['pa_first'] . ' ' . $req_data['pa_last'], 30);
		$msg .= $this->format_date($req_data['pa_dob'], 'dmY');
		$msg .= $req_data['pa_gender'] == 'M' ? 'M' : 'F';

		$age = $this->get_age($req_data['pa_dob']);
		$msg .= $this->fillzero($age, 3);
		$msg .= $this->fill_space($department, 20);
		$msg .= $this->format_date($req_data['doc'], 'dmY');
		$msg .= $this->fill_space($Concentration, 5);
		$msg .= $this->fill_space($frees[0], 30);
		$msg .= $this->fill_space($frees[1], 30);
		$msg .= $this->fill_space($frees[2], 30);
		$msg .= $this->fill_space($frees[3], 30);
		$msg .= $this->fill_space($frees[4], 30);
		$msg .= $is_end ? $this->server->CHARS['EOT'] : $this->server->CHARS['ETX'];

		return $this->write_msg($msg);
	}

	//NACK
	function send_message4()
	{
		//3bytes
		$msg = $this->server->CHARS['STX'];
		$msg .= $this->server->CHARS['NAK'];
		$msg .= $this->server->CHARS['ETX'];

		return $this->write_msg($msg);
	}


	function send_message5(
		$number,
		$req_data,
		$arr,
		$department,
		$doc,
		$Concentration,
		$unit_concentration,
		$frees = array(),
		$Operator_ID,
		$Date_of_Analysis,
		$Fractions = array(),
		$Peaks = array(),
		$Pathological_Flag = 0,
		$Ratio_1,
		$Ratio_2,
		$Comment,
		$Reference_pattern_flag = 0,
		$QC_sample_flag = 0,
		$is_end = false
	) {
		$num_Fractions = count($Fractions);
		if ($num_Fractions > 10) $num_Fractions = 10;

		$age = trdd_dob2ages($req_data['pa_dob']);

		//247bytes
		$msg = $this->server->CHARS['STX'];
		$msg .= isset($arr['program_code']) ? $arr['program_code'] : PROGRAM_CODE;
		$msg .= $this->fillzero($number, 4);
		$msg .= $this->fill_space($req_data['pa_ID'], 15);
		$msg .= $this->fill_space($req_data['pa_first'] . ' ' . $req_data['pa_last'], 30);
		$msg .= $this->format_date($req_data['pa_dob'], 'dmY');
		$msg .= $req_data['pa_gender'] == 'M' ? 'M' : 'F';
		$msg .= $this->fillzero($age, 3);
		$msg .= $this->fill_space($department, 20);
		$msg .= $this->format_date($doc, 'dmY');
		$msg .= $this->fill_space($Concentration, 5);
		$msg .= $this->fill_space($unit_concentration, 8);
		$msg .= $this->fill_space($frees[0], 30);
		$msg .= $this->fill_space($frees[1], 30);
		$msg .= $this->fill_space($frees[2], 30);
		$msg .= $this->fill_space($frees[3], 30);
		$msg .= $this->fill_space($frees[4], 30);
		$msg .= $this->fill_space($Operator_ID, 3);
		$msg .= $this->format_date($Date_of_Analysis, 'dmY');
		$msg .= $this->fillzero($num_Fractions, 2);
		$name_msg = '';
		$percent_msg = '';
		$conc_msg = '';
		for ($i = 0; $i < 10; $i++) {
			$name_msg .= $this->fill_space(isset($Fractions[$i]) ? $Fractions[$i]['name'] : '', 10);
			$percent_msg .= $this->fill_space(isset($Fractions[$i]) ? $Fractions[$i]['percent'] : '', 5);
			$conc_msg .= $this->fill_space(isset($Fractions[$i]) ? $Fractions[$i]['conc'] : '', 5);
		}
		$msg .= $name_msg;
		$msg .= $percent_msg;
		$msg .= $conc_msg;

		$name_msg = '';
		$percent_msg = '';
		$conc_msg = '';
		for ($i = 0; $i < 4; $i++) {
			$name_msg .= $this->fill_space(isset($Peaks[$i]) ? $Peaks[$i]['name'] : '', 10);
			$percent_msg .= $this->fill_space(isset($Peaks[$i]) ? $Peaks[$i]['percent'] : '', 5);
			$conc_msg .= $this->fill_space(isset($Peaks[$i]) ? $Peaks[$i]['conc'] : '', 5);
		}
		$msg .= $name_msg;
		$msg .= $percent_msg;
		$msg .= $conc_msg;

		$msg .= $Pathological_Flag ? 1 : 0;
		$msg .= $this->fill_space($Ratio_1, 5);
		$msg .= $this->fill_space($Ratio_2, 5);
		$msg .= $this->fill_space($Comment, 230);
		$msg .= $Reference_pattern_flag ? 1 : 0;
		$msg .= $QC_sample_flag ? 1 : 0;

		$msg .= $is_end ? $this->server->CHARS['EOT'] : $this->server->CHARS['ETX'];

		return $this->write_msg($msg);
	}


	function send_message6($patientid, $arr)
	{
		//18bytes
		$msg = $this->server->CHARS['STX'];
		$msg .= isset($arr['program_code']) ? $arr['program_code'] : PROGRAM_CODE;
		$msg .= $this->fill_space($patientid, 15);
		$msg .= $this->server->CHARS['ETX'];

		return $this->write_msg($msg);
	}


	//EOT
	function send_message7()
	{
		//3bytes
		$msg = $this->server->CHARS['STX'];
		$msg .= $this->server->CHARS['EOT'];
		$msg .= $this->server->CHARS['ETX'];

		return $this->write_msg($msg);
	}


	function parse_data($msg)
	{
		$data 	= array();
		$length = strlen($msg);

		$data['msg_num'] = 0;
		$data['msg_length'] = $length;

		if ($length >= 791) {
			$data['msg_num'] = 5;
		} else if ($length == 11) {
			$data['msg_num'] = 1;
		} else if ($length == 3) {
			$data['msg_num'] = 2;
			if ($msg[1] == $this->server->CHARS['EOT']) {
				$data['msg_num'] = 7;
			} else if ($msg[1] == $this->server->CHARS['NAK']) {
				$data['msg_num'] = 4;
			}
		} else if ($length == 247) {
			$data['msg_num'] = 3;
		} else if ($length == 18) {
			$data['msg_num'] = 6;
		}
		$map = array();
		switch ($data['msg_num']) {
			case 1:

				$map['start_num'] = array(2, 4);
				$map['end_num'] = array(6, 4);
				break;

			case 2:
				$map['ack'] = array(2, 1);
				break;

			case 3:

				$map['sample_num'] = array(2, 4);
				$map['patient_id'] = array(8, 15);
				$map['patient_name'] = array(21, 30);
				$map['patient_dob'] = array(51, 8);
				$map['patient_gender'] = array(59, 1);
				$map['patient_age'] = array(60, 3);
				$map['department'] = array(63, 20);
				$map['doc'] = array(83, 8);
				$map['concentration'] = array(91, 5);
				$map['free1'] = array(96, 30);
				$map['free2'] = array(126, 30);
				$map['free3'] = array(156, 30);
				$map['free4'] = array(186, 30);
				$map['free5'] = array(216, 30);
				break;

			case 4:
				$map['nack'] = array(2, 1);
				break;

			case 5:
				$map['program_code'] = array(2, 1);
				$map['sample_number'] = array(3, 4);
				$map['patient_id'] = array(7, 15);
				$map['patient_name'] = array(22, 30);
				$map['dob'] = array(52, 8);
				$map['sex'] = array(60, 1);
				$map['age'] = array(61, 3);
				$map['department'] = array(64, 20);
				$map['doc'] = array(84, 8);
				$map['consentration'] = array(92, 5);
				$map['measurement'] = array(97, 8);
				$map['free1'] = array(105, 30);
				$map['free2'] = array(135, 30);
				$map['free3'] = array(165, 30);
				$map['free4'] = array(195, 30);
				$map['free5'] = array(225, 30);
				$map['operation_id'] = array(255, 3);
				$map['date_of_analysis'] = array(258, 8);
				$map['number_fractions'] = array(266, 2);

				for ($in = 1; $in <= 10; $in++) {
					$map['fraction_name_' . $in] = array(268 + ($in - 1) * 10, 10);
					$map['fraction_percent_' . $in] = array(368 + ($in - 1) * 5, 5);
					$map['fraction_conc_' . $in] = array(418 + ($in - 1) * 5, 5);
				}

				for ($in = 1; $in <= 4; $in++) {
					$map['peak_name_' . $in] = array(468 + ($in - 1) * 10, 10);
					$map['peak_percent_' . $in] = array(508 + ($in - 1) * 5, 5);
					$map['peak_conc_' . $in] = array(528 + ($in - 1) * 5, 5);
				}

				$map['patholotical_flag'] = array(548, 1);
				$map['ratio1'] = array(549, 5);
				$map['ratio2'] = array(554, 5);
				$map['comment'] = array(559, 230);
				$map['reference_flag'] = array(789, 1);
				$map['qc_flag'] = array(790, 1);

				break;

			case 6:
				$map['program_code'] = array(2, 1);
				$map['patient_id'] = array(3, 15);
				break;
			case 7:
				$map['eot'] = array(2, 1);
				break;
		}

		foreach ($map as $k => $marr) {

			$data[$k] = trim(substr($msg, $marr[0] - 1, $marr[1]));
		}

		return $data;
	}

	public function run()
	{
		$db = new DB($this->server->dbconfig);
		$db->connect();

		$data           = '';
		$last_buf           = '';
		$msg_started    = false;
		$last_mid_requested = 0;


		$doing_type = 0;
		$current_number_msg3 = 0;



		try {
			date_default_timezone_set('America/Detroit');
			if (!in_array($this->address, array('127.0.0.1', '107.172.218.216'))) {
				echo date('Y-m-d H:i:s') . " - Client connected - {$this->address} #{$this->client_id}\n";
			}
		} catch (Exception $e) {
		}


		do {
			try {

				if ($this->need_stop) {
					break;
				}

				if (false === ($buf = fread($this->msgsock, 1))) {
					if (!in_array($this->address, array('127.0.0.1', '107.172.218.216'))) {
						echo date('Y-m-d H:i:s') . " - {$this->address} #{$this->client_id} - error: Read false\n";
					}
					break;
				}

				if (strlen($buf) == 0) {
					if (feof($this->msgsock)) {
						if (!in_array($this->address, array('127.0.0.1', '107.172.218.216'))) {
							echo date('Y-m-d H:i:s') . " - {$this->address} #{$this->client_id} - end msg\n";
						}
						break;
					}
					continue;
				}

				if (DEBUG) {
					$nl2 = "";
					if ($buf == $this->server->CHARS['ETX']) {
						$nl2 = '<NL><NL>';
					}
					$this->log($buf . $nl2, 'raw');
				}

				if ($buf == $this->server->CHARS['STX']) {
					$msg_started    = true;
					$data           = $buf;
					$last_buf       = $buf;

					continue;
				} else if (
					$msg_started
					&& ($buf == $this->server->CHARS['ETX']
						|| ($buf == $this->server->CHARS['EOT'] && $last_buf != $this->server->CHARS['STX'])
					)
				) {
					$msg_started = false;
					$data .= $buf;

					$arr = $this->parse_data($data);

					if (DEBUG) {
						$this->log("<NL>" . date('H:i:s') . " - Rev:<NL>");
						$this->log($data . "<NL>");
						$this->log("<NL>" . json_encode($arr) . "<NL>");
					} else {
						touch($this->server->log_file);
					}
					$btype = 'blood';
					if ($arr['msg_num'] == 1) {
						//request sample

						$current_number_msg3 = 1;
						$doing_type = 1;
						$this->send_message2();
						$sample = $db->get_next_sample($btype, API_ID);
						if (!$sample) {
							$btype = 'study';
							$sample = $db->get_next_sample($btype, API_ID);
						}
						if ($sample) {

							$this->send_message3($current_number_msg3, $sample, $arr);
							$db->set_sent_sample($btype, $sample['mid'], API_ID);
						}
					} else if ($arr['msg_num'] == 2) {
						//get next sample
						if ($doing_type == 1) {
							$sample = $db->get_next_sample($btype, API_ID);
							if (!$sample) {
								$btype = 'study';
								$sample = $db->get_next_sample($btype, API_ID);
							}
							if ($sample) {
								$current_number_msg3++;

								$this->send_message3($current_number_msg3, $sample, $arr);
								$db->set_sent_sample($btype, $sample['mid'], API_ID);
							}
						}
					} else if ($arr['msg_num'] == 6) {
						//request one sample
						$assen = preg_replace("/[^a-zA-Z0-9\-]/", '', $arr['patient_id']);
						$current_number_msg3 = 1;

						$this->send_message2();

						$sample = $db->get_batch_data($assen, $btype);
						if (!$sample) {
							$btype = 'study';
							$sample = $db->get_batch_data($assen, $btype);
						}
						if ($sample) {

							$this->send_message3($current_number_msg3, $sample, $arr, '', '', array(), true);
							$db->set_sent_sample($btype, $sample['mid'], API_ID);
						} else {
							$this->send_message7();
							$this->log("Not found assen: {$assen}<NL>");
						}
					} else if ($arr['msg_num'] == 5) {
						//receive results
						$assen = preg_replace("/[^a-zA-Z0-9\-]/", '', $arr['patient_id']);


						$mid = $db->get_mid($assen, $btype);
						$count_rs = 0;

						if (!$mid) {
							$btype = 'study';
							$mid = $db->get_mid($assen, $btype);
						}

						$this->log("<NL>Assen: {$assen} ({$btype}): {$mid} <NL>");

						if ($mid > 0) {

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
								for ($i = 1; $i <= $arr['number_fractions']; $i++) {
									$drug 		= $arr['fraction_name_' . $i];
									$percent 	= floatval($arr['fraction_percent_' . $i]);
									$conc 		= floatval($arr['fraction_conc_' . $i]);
									$percent 	= floatval($arr['fraction_percent_' . $i]);

									if ($percent != 0 && $conc == 0) {
										$conc = $percent;
									}


									$this->log("{$drug}: {$conc} <NL>");

									$rs = $db->insert_result($report_id, array(
										'key' => 'te_phoresis',
										'drug' => $drug,
										'conc' => $conc,
										'instrument' => API_NAME,
									), $btype);

									if ($rs === false) {
										$this->log("<NL>insert data error for mid :{$mid} => {$db->last_query}<NL>");
										$logarr[$drug] = array($conc, $db->old_conc, 0);
									} else {
										$logarr[$drug] = array($conc, $db->old_conc, 1);
										$count_rs++;
									}
								}

								$db->set_received_result($btype, $mid, API_ID, $logarr);

								if (!$this->is_test && $count_rs > 0) {
									$db->reverse_report($assen, $btype);
								}
							}
						} else {
							//not found assen
							$this->log("Not found <NL>");
						}

						$this->send_message2();
					}
				} else if ($msg_started) {
					$data .= $buf;
				} else if (!$this->is_test && $buf == $this->server->CHARS['LF'] && $last_buf == $this->server->CHARS['SOH']) {
					$this->is_test = true;
					$this->write_msg("hello test");
				}


				$last_buf = $buf;
			} catch (Exception $e) {
				var_dump($e);
			}
		} while (true);




		if (!in_array($this->address, array('127.0.0.1', '107.172.218.216'))) {
			echo date('Y-m-d H:i:s') . " - Client dropped - {$this->address} #{$this->client_id}\n";
		}
		$db->disconnect();

		$this->stop();
	}
}


$log_path = '';
if (!empty($argv[3]) && stripos($argv[3], 'log/') > 0) {
	$log_path = $argv[3];
} else {
	$log_path = dirname(__FILE__) . '/phoresis';
}

echo 'version: ' . API_VERSION . "\n";
echo 'load config file: ' . basename($config_file) . "\n";
echo date('Y-m-d H:i:s') . ' - Starting port: ' . $port . "...";

$server = new IntrumentServer($port, $log_path, $dbconfig);

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
