<?php

class CI_Api_Insu_Billing_sftp
{
	protected $conn;
	protected $sftp;
	private  $host = '';
	private  $port = 22;
	public $folder = '/';
	public $clientbill_folder = '';
	public $dir = WP_CONTENT_DIR . '/log/insu_billing_sftp/';
	public $log_file = WP_CONTENT_DIR . '/log/log_api_insu_billing_sftp.txt';
	protected $options;
	public $debug = false;
	public $log = '';
	public $api_id = -18;
	public $log_file_slug = 'insu_billing_sftp';
	public $current_date = '';
	public $mids = array();

	public function __construct($options)
	{

		$this->options = $options;
		$this->host = isset($options['host']) ? $options['host'] : '';
		if (isset($options['port']) && $options['port'] > 0) {
			$this->port = $options['port'];
		}

		$this->folder           = isset($options['folder']) ? $options['folder'] : '';
		$this->clientbill_folder       = isset($options['clientbill_folder']) ? $options['clientbill_folder'] : '';
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
		$result = ssh2_auth_password($this->conn, $this->options['username'], $this->options['password']);

		if ($result === false) {
			throw new Exception('Login incorrect');
		}

		$this->sftp = @ssh2_sftp($this->conn);

		return $result;
	}

	public function getpath($path)
	{
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
		if (! $stream)
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
		$remote_file = str_replace('//', '/', $remote_file);
		$path = $this->getpath($remote_file);
		$stream = @fopen($path, 'w');
		if (! $stream)
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
		if (! $rs)
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

	public function log($message)
	{
		$f_log = fopen($this->log_file, 'a+');
		fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => ' . $message . "\n");
		fclose($f_log);

		$this->log .= $message . "\n";
	}

	public function cron()
	{
		$this->connect();
		$this->login();
		$this->sync();
	}

	public function sync($args = array())
	{
		global $wpdb;

		$this->current_date = date_i18n('Y-m-d');
		$hour = date_i18n('H');
		if ($hour <= 22) {
			$this->current_date = date('Y-m-d', strtotime($this->current_date) - 86400);
		}
		if (isset($_REQUEST['date'])) {
			$this->current_date = $_REQUEST['date'];
		}

		foreach (array('insu', 'clientb') as $type) {
			$this->log("Building CSV for type: {$type} for date: {$this->current_date} \n");
			$local_file = $this->build_csv($this->current_date, $type, $args);

			if ($local_file) {
				$files[] = $local_file;

				$filename = basename($local_file);

				$folder = $this->folder;
				if ($type == 'clientb') {
					$folder = $this->clientbill_folder;
				}

				$remote_file = $folder . '/' . $filename;

				try {
					if (isset($_REQUEST['testcron'])) {
						echo 'uploading file: ' . $remote_file . '<br>';
					}

					$this->uploadFile($local_file, $remote_file);
					$this->log("uploaded: {$remote_file} \n");

					//insert log db
					foreach ($this->mids as $btype => $mids) {
						foreach ($mids as $mid) {
							$logarr = array(
								'mo_file_id' => $this->api_id,
								'mo_mid' => $mid,
								'mo_btype' => $btype,
								'mo_test_ID' => date_i18n('Y-m-d H:i:s'),
								'mo_control_ID' => $filename,
							);
							$mo_ID = $wpdb->get_var("select mo_ID from ci_sync_api_orders 
									where mo_btype = '{$btype}' 
									and mo_file_id = {$this->api_id}
									and mo_mid = {$mid}");
							if ($mo_ID > 0) {
								$logarr = array(
									'mo_control_ID' => $filename,
								);
								$wpdb->update('ci_sync_api_orders', $logarr, array('mo_ID' => $mo_ID));
							} else {
								$wpdb->insert('ci_sync_api_orders', $logarr);
							}
						}
					}
				} catch (Exception $e) {
					$this->log("sync error: " . $e->getMessage());
					return false;
				}
			}
		}
	}

	public function build_csv($date, $type, $args = array())
	{
		global $wpdb;

		$filename   = $date  . '.csv';
		$local_file = $this->dir . '/' . $filename;
		file_put_contents($local_file, '');

		$this->mids = tr_billing_export_insurance_reports($date, $type, $local_file);


		return $local_file;
	}
}
