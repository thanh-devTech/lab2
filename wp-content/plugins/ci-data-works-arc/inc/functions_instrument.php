<?php

function trpdd_count_connected_old($slug, $port)
{
	$command = "ps aux | grep {$slug}";
	exec($command, $output, $return);
	$count = 0;
	foreach ($output as $out) {
		if (strpos($out, $slug . ' port ' . $port) > 0) {
			$count++;
		}
	}
	if ($count > 0) {
		$count = $count - 1;
	}
	return $count;
}


function trpdd_count_connected($slug, $port)
{
	global $logpath;
	$file = $logpath . '/out_' . $port . '.txt';
	if (!file_exists($file)) return 0;
	if (filesize($file) > 10000000) {
		tr_pdd_keepLastLines($file, 500);
		return 0;
	}
	$data = file_get_contents($file);
	$count_connected = substr_count($data, 'Client connected');
	$count_closed = substr_count($data, 'Client dropped');

	$count = $count_connected -  $count_closed;
	if ($count < 0) $count = 0;

	return $count;
}

function trpdd_get_logfiles($path, $port)
{
	$files = array();
	$lasttime = 0;
	$dir = opendir($path);
	if ($dir) {
		while ($f = readdir($dir)) {
			$fpath = $path . '/' . $f;
			if (stripos($f, 'test') > 0 || stripos($f, 'raw') > 0) {
				continue;
			}

			if (is_file($fpath) && filesize($fpath) > 10 && strpos($f, 'log_') === 0 && stripos($f, $port) !== false) {

				$ftime = filemtime($fpath);
				if (isset($files[$ftime])) {
					$ftime = $ftime + 10;
				}
				$files[$ftime] = $f;
				if ($ftime > $lasttime) {
					$lasttime = $ftime;
				}
			}
		}
	}


	if (count($files) > 0) {
		krsort($files);
	}
	if (count($files) > 50) {
		$oldfiles = array_slice($files, 50);
		foreach ($oldfiles as $f) {
			@unlink($path . '/' . $f);
			$rawfile = str_replace('log', 'log_raw', $f);
			@unlink($path . '/' . $rawfile);
		}
		$files = array_slice($files, 0, 50);
	}
	return array(
		'files' => $files,
		'lasttime' => $lasttime,
	);
}

function trpdd_has_http($fpath)
{
	$has_http = false;
	$fp = fopen($fpath, 'r');
	if ($fp) {
		while (($buffer = fgets($fp, 4096)) !== false) {
			if (stripos($buffer, 'http/') !== false) {
				$has_http = true;
				break;
			}
		}
		fclose($fp);
	}
	return $has_http;
}

function trpdd_stop_port($port, $pid_file, $stop_file)
{
	touch($stop_file);
	trpdd_try_connect('localhost', $port);

	$pass_stop = false;
	if (file_exists($pid_file)) {
		$handle = @fopen($pid_file, 'r');
		if ($handle) {
			while (($line = fgets($handle)) !== false) {
				$pid = trim($line);
				if ($pid > 0) {
					$rs = posix_kill($pid, 9);
					if ($rs) {
						$pass_stop = true;
					}
				}
			}
			fclose($handle);
		}
	}
	if (!$pass_stop) {
		$command = "/sbin/fuser {$port}/tcp";
		$pid = exec($command, $output);
		$pid = intval(trim($pid));
		if ($pid > 0) {
			posix_kill($pid, 9);
		}
	}
}

function trpdd_update_config_file($api_slug, $more_config = array())
{
	$configdir = ABSPATH . '/wp-content/config';
	if (!file_exists($configdir)) {
		wp_mkdir_p($configdir);
	}
	$timezone = 'America/Detroit';
	if (function_exists('wp_timezone_string')) {
		$timezone = wp_timezone_string();
	}

	$dbconfig = array(
		'host' => DB_HOST,
		'user' => DB_USER,
		'pass' => DB_PASSWORD,
		'name' => DB_NAME,
		'port' => 3306,
		'time_zone' => $timezone,
		'main_slug' => tr_get_option('ci_pdf_data', 'barcode_key')
	);

	if (is_array($more_config)) {
		$dbconfig = array_merge($dbconfig, $more_config);
	}

	ob_start();
	var_export($dbconfig);
	$configstr = ob_get_clean();

	$port = isset($more_config['port']) ? $more_config['port'] : 0;


	$config_file = $configdir . '/' . $api_slug . '.php';


	$content = '<?php' . "\n";
	$content .= 'if(!defined("API_NAME"))exit;' . "\n";

	$content .= '$dbconfig = ' . $configstr . ';' . "\n";

	if ($port > 0) {
		$config_file2 = $configdir . '/' . $api_slug . '_' . $port . '.php';
		file_put_contents($config_file2, $content);
	}
	return file_put_contents($config_file, $content);
}

function format_date($date, $format = 'dmY')
{
	return date($format, strtotime($date));
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

function trpdd_get_ip()
{
	$ip = $_SERVER['SERVER_ADDR'];
	if (
		stripos(ABSPATH, '/deployments/') !== false
		|| stripos(ABSPATH, '/bitnami/') !== false
		|| stripos(ABSPATH, '/var/www/') !== false
	) {
		$json = wp_remote_get('https://imt.ignitelabconnect.com/labs_api/get_ip');
		if (!is_wp_error($json)) {
			$json = json_decode($json['body'], true);
			$ip = isset($json['ip']) ? $json['ip'] : $ip;
		}
	}

	return $ip;
}

function tr_pdd_keepLastLines($filename, $num_lines = 100)
{
	$file = fopen($filename, "r+");
	if (!$file) {
		return false;
	}

	// Seek to the end of the file
	fseek($file, -1, SEEK_END);
	$pos = ftell($file);
	$lines = [];
	$line_count = 0;
	$buffer = '';

	while ($pos >= 0) {
		// Read character by character backwards
		fseek($file, $pos);
		$char = fgetc($file);

		if ($char === "\n") {
			// Newline found, add current buffer as a line
			$lines[] = strrev($buffer); // Reverse buffer to get the line in correct order
			$buffer = '';
			$line_count++;
			if ($line_count >= $num_lines) {
				break; // Stop if we have enough lines
			}
		} elseif ($char !== false) {
			$buffer .= $char;
		}

		$pos--;
	}

	// Add the first line if it wasn't ended by a newline
	if ($buffer !== '' && $line_count < $num_lines) {
		$lines[] = strrev($buffer);
	}

	// Reverse the array to get lines in correct chronological order
	$lines = array_reverse($lines);

	// Truncate the file and write back the last 100 lines
	ftruncate($file, 0);
	rewind($file);
	fwrite($file, implode("", $lines));
	fclose($file);

	return true;
}
//end file