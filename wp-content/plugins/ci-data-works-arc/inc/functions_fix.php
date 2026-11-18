<?php
function trpdd_sync_batch_ids($limitdate = null)
{
	global $wpdb;

	foreach (tr_pdd_get_batch_types() as $btype) {
		$batch_tbl = tr_pdd_get_table_by_btype($btype);
		$sql = "select distinct(ba.batch_id) from {$batch_tbl} ba
			where batch_id !='' and batch_id is not null
			and batch_id not in (select batch_id from ci_batch_ids)
			";
		if ($limitdate) {
			$sql .= " and doc >= '{$limitdate}' ";
		}
		$batch_ids = $wpdb->get_col($sql);
		foreach ($batch_ids as $batch_id) {
			if ($batch_id === '0') continue;

			$wpdb->query("insert IGNORE into ci_batch_ids set batch_id = '{$batch_id}' ");
		}
	}
}

function trpdd_check_ssl()
{
	$urlcheck = 'https://www.howsmyssl.com/a/check';
	if (empty(file_get_contents($urlcheck))) {
		return false;
	}
	return true;
}

function trpdd_check_plugin_requireds()
{

	$install_ssh2 = 'sudo yum install php-pecl-ssh2 -y | or | sudo apt-get install php-ssh2 -y';
	if (stripos(ABSPATH, 'bitnami') > 0) {
		$install_ssh2 = 'cd /tmp
		wget http://www.libssh2.org/download/libssh2-1.11.0.tar.gz
		tar vxzf libssh2-1.11.0.tar.gz
		cd libssh2-1.11.0
		./configure
		make
		sudo make install
		cd /tmp
		wget http://pecl.php.net/get/ssh2-1.4.tgz
		tar xzf ssh2-1.4.tgz
		cd ssh2-1.4
		phpize
		./configure --with-ssh2
		make
		sudo make install
		then add "extension = ssh2" to php.ini';
	}
	$list = array();
	$list[] = array(
		'name' => 'Dompdf',
		'desc' => 'Dompdf plugin',
		'type' => 'defined',
		'value' => 'TRCDPDF_FILE',
		'status' => false
	);
	$list[] = array(
		'name' => 'Lab Client',
		'desc' => 'Lab Client plugin',
		'type' => 'defined',
		'value' => 'TRLCN_FILE',
		'status' => false
	);
	$list[] = array(
		'name' => 'Convert PDF',
		'desc' => 'Convert Pdf to image',
		'type' => 'exec',
		'value' => 'convert -v|ImageMagick',
		'status' => false,
		'install' => 'sudo apt install imagemagick -y'
	);
	$list[] = array('name' => 'GS Lib', 'desc' => 'Convert Pdf to 1.4 version', 'type' => 'exec', 'value' => 'gs -v|Ghostscript', 'install' => 'sudo apt-get install ghostscript libgs-dev -y', 'status' => false);
	$list[] = array('name' => 'mbstring Lib', 'desc' => 'mbstring Lib', 'type' => 'function_exists', 'value' => 'mb_convert_encoding', 'status' => false);
	$list[] = array('name' => 'bcmath Lib', 'desc' => 'bcmath Lib', 'type' => 'function_exists', 'value' => 'bcadd', 'status' => false);
	$list[] = array('name' => 'XML Lib', 'desc' => 'XML Lib', 'type' => 'function_exists', 'value' => 'simplexml_load_string', 'status' => false);
	$list[] = array('name' => 'soap Lib', 'desc' => 'soap Lib', 'type' => 'class_exists', 'value' => 'SoapClient', 'status' => false);
	$list[] = array('name' => 'ZipArchive Lib', 'desc' => 'ZipArchive Lib', 'type' => 'class_exists', 'value' => 'ZipArchive', 'status' => false);
	$list[] = array('name' => 'SSL Client', 'desc' => 'SSL Client', 'type' => 'function', 'value' => 'trpdd_check_ssl', 'status' => false);
	$list[] = array('name' => 'Opcache', 'desc' => 'Opcache', 'type' => 'function_exists', 'value' => 'opcache_get_status', 'status' => false);
	$list[] = array('name' => 'max_file_uploads', 'desc' => '', 'type' => 'ini_get', 'value' => '5', 'status' => false);
	$list[] = array('name' => 'memory_limit', 'desc' => '', 'type' => 'ini_get', 'value' => '256M', 'status' => false);
	$list[] = array('name' => 'upload_max_filesize', 'desc' => '', 'type' => 'ini_get', 'value' => '50M', 'status' => false);
	$list[] = array('name' => 'max_execution_time', 'desc' => '', 'type' => 'ini_get', 'value' => '200', 'status' => false);
	$list[] = array(
		'name' => 'max_input_vars',
		'desc' => '',
		'type' => 'ini_get',
		'value' => '50000',
		'status' => false,
		'install' => "sudo sed -i 's/^[;]*max_input_vars.*/max_input_vars = 50000/' /opt/bitnami/php/etc/php.ini",
	);
	$list[] = array(
		'name' => 'ssh2_connect',
		'desc' => 'Sftp',
		'type' => 'function_exists',
		'value' => 'ssh2_connect',
		'status' => false,
		'install' => $install_ssh2
	);

	foreach ($list as &$arr) {
		switch ($arr['type']) {
			case 'defined':
				if (defined($arr['value'])) {
					$arr['status'] = true;
				}
				break;
			case 'function_exists':
				if (function_exists($arr['value'])) {
					$arr['status'] = true;
				}
				break;
			case 'class_exists':
				if (class_exists($arr['value'])) {
					$arr['status'] = true;
				}
				break;
			case 'ini_get':
				$rs = ini_get($arr['name']);
				$arr['desc'] = $rs . ' (' . $arr['value'] . ')';
				$arr['value'] = str_replace(array('K', 'M', 'G'), '', $arr['value']);
				$rs = str_replace(array('K', 'M', 'G'), '', $rs);
				if ($rs >= $arr['value']) {
					$arr['status'] = true;
				}
				break;
			case 'exec':
				$arr2 = explode('|', $arr['value']);
				$rs = exec($arr2[0], $output, $rscode);

				if (is_array($output)) {
					foreach ($output as $txt) {
						if (stripos($txt, $arr2[1]) !== false) {
							$arr['status'] = true;
						}
					}
				}
				if (stripos($rs, $arr2[1]) !== false) {
					$arr['status'] = true;
				}

				break;

			case 'function':
				if (call_user_func($arr['value'])) {
					$arr['status'] = true;
				}
				break;
		}

		if ($arr['status'] == false && isset($arr['install'])) {
			$arr['desc'] .= '. Install: <div style="border:1px solid #ddd;padding:5px">' . str_replace("\n", '<br>', $arr['install']) . '</div>';

			if (stripos(ABSPATH, 'bitnami') !== false && !empty($arr['install']) && $arr['type'] == 'ini_get') {
				$config_file = '/opt/bitnami/php/etc/php.ini';
				$oldconfig = file_get_contents($config_file);
				$pattern = '/' . preg_quote($arr['name']) . '\s*=\s*.*$/m';
				preg_match($pattern, $oldconfig, $matches);
				if (!empty($matches)) {
					$arr['desc'] .= ' Current config line: <div style="border:1px solid #ddd;padding:5px">' . $matches[0] . '</div>';
					if (is_writeable($config_file)) {
						$arr['desc'] .= ' You can run the above command to update the php.ini file directly.';
					} else {
						$arr['desc'] .= ' Please update the php.ini file manually.';
						$arr['desc'] .= '<div style="border:1px solid #ddd;padding:5px">sudo /opt/bitnami/ctlscript.sh restart php-fpm</div>';
					}
				} else {
					$arr['desc'] .= ' You can add this line to php.ini file.';
				}
			}
		}
	}


	$list = apply_filters('ci_plugin_required', $list);
	return $list;
}


function trpdd_clear_by_dir($path, $timeout, $current_time, $remove_dir = false, $exts = '')
{
	if (
		stripos($path, '/cache/') === false
		&& stripos($path, '/log/') === false
		&& stripos($path, '/qrs/') === false
		&& stripos($path, '/pce/') === false
		&& stripos($path, '/glml/') === false
		&& stripos($path, '/msu/') === false
		&& stripos($path, '/nwlabs/') === false
		&& stripos($path, '/paperwork/') === false
		&& stripos($path, '/doctor/') === false
		&& stripos($path, '/tmp/') === false
		&& stripos($path, '/results/') === false
		&& stripos($path, '/uploads/signatures/') === false
		&& stripos($path, '/uploads/ins_cards/') === false
		&& stripos($path, '/uploads/issue_photo/') === false
	) {
		return false;
	}


	if ($remove_dir) {
		//no thing
	} else if (basename($path) == 'uploads' || stripos($path, 'uploads') !== false) {
		return false;
	}

	//dont clear in html dir
	if (basename($path) == 'html' || stripos($path, 'results/html') !== false) {
		return false;
	}

	$handle = @opendir($path);
	if ($handle) {
		while ($file = readdir($handle)) {
			if ($file == '.' || $file == '..' || $file[0] == '_') continue;

			if (is_dir($path . '/' . $file)) {
				trpdd_clear_by_dir($path . '/' . $file, $timeout, $current_time, $remove_dir);
				if ($remove_dir && !in_array($file, array('uploads', 'cache', 'log', 'html', 'ref', 'tmp', 'results'))) {
					rmdir($path . '/' . $file);
				}
			} else {
				if (!empty($exts) && stripos($file, $exts) === false) {
					continue;
				}
				$t = @filemtime($path . '/' . $file);
				if ($current_time - $t > $timeout) {
					if (stripos($path, 'uploads') !== false && !$remove_dir) continue;
					else if (stripos($file, '.php') !== false || stripos($file, '.html') !== false) continue;

					@unlink($path . '/' . $file);
				}
			}
		}
	}
	if ($handle) {
		@closedir($handle);
	}
}

function return_bytes($val)
{
	$val = trim($val);
	$last = strtolower(substr($val, -1, 1));
	$val = intval($val);
	switch ($last) {
		// The 'G' modifier is available since PHP 5.1.0
		case 'g':
			$val *= 1024;
		case 'm':
			$val *= 1024;
		case 'k':
			$val *= 1024;
	}

	return $val;
}

function trlr_format_byte($bytes)
{
	if ($bytes == 0) return '0 B';
	$si_prefix = array('B', 'KB', 'MB', 'GB', 'TB', 'EB', 'ZB', 'YB');
	$base = 1024;
	$class = min((int)log($bytes, $base), count($si_prefix) - 1);

	return sprintf('%1.2f', $bytes / pow($base, $class)) . ' ' . $si_prefix[$class];
}

function trpdd_sync_abx_test_colors($override = false)
{
	global $wpdb;
	$updated_tests = array();
	$bacterias = $wpdb->get_results("select * from ci_abx_bacterias where color is not null and trim(color) != '' ");
	foreach ($bacterias as $b) {
		//find related bacteria and update color
		$related_ids = $wpdb->get_col("select test_id from ci_test_meta 
						where meta_key='bac_tests'
						AND FIND_IN_SET(" . intval($b->ID) . ", meta_value) ");
		foreach ($related_ids as $rid) {
			//check if not manual color
			if (!$override) {
				$manual_color = $wpdb->get_var("select meta_value from ci_test_meta 
						where test_id = " . intval($rid) . "
						AND meta_key='color_changed' ");
				if (!empty($manual_color) && trim($manual_color) != '') {
					continue;
				}
			}


			$wpdb->update(
				'ci_test_panels',
				array('te_color' => $b->color),
				array('te_ID' => intval($rid)),
				array('%s'),
				array('%d')
			);
		}
		$wpdb->query(
			$wpdb->prepare(
				"
				UPDATE ci_test_panels
				SET te_color = %s
				WHERE te_name = %s
				AND (te_color != %s OR te_color IS NULL)
				",
				$b->color,
				$b->bacteria_name,
				$b->color
			)
		);
		$updated_tests[] = $b->bacteria_name;
	}

	$markers = $wpdb->get_results("select * from ci_abx_resistance_markers where color is not null and trim(color) != '' ");
	foreach ($markers as $m) {
		//find related markers and update color
		$related_ids = $wpdb->get_col("select test_id from ci_test_meta 
						where meta_key='mkr_tests'
						AND FIND_IN_SET(" . intval($m->ID) . ", meta_value) ");
		foreach ($related_ids as $rid) {

			if (!$override) {
				$manual_color = $wpdb->get_var("select meta_value from ci_test_meta 
						where test_id = " . intval($rid) . "
						AND meta_key='color_changed' ");
				if (!empty($manual_color) && trim($manual_color) != '') {
					continue;
				}
			}
			$wpdb->update(
				'ci_test_panels',
				array('te_color' => $m->color),
				array('te_ID' => intval($rid)),
				array('%s'),
				array('%d')
			);
		}
		$wpdb->query(
			$wpdb->prepare(
				"
				UPDATE ci_test_panels
				SET te_color = %s
				WHERE te_name = %s
				AND (te_color != %s OR te_color IS NULL)
				",
				$m->color,
				$m->resistance_marker,
				$m->color
			)
		);
		$updated_tests[] = $m->resistance_marker;
	}
	return $updated_tests;
}

//end of file
