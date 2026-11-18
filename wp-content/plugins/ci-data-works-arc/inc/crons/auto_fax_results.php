<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb;


include(TRCPDD_PATH . 'api/ci_main_fax.php');

$fax        = new CI_Main_Fax();
$result_dir = WP_CONTENT_DIR . '/results/';
$uptime     = time() - 1000;

$autotime 	= tr_get_option('ci_pdf_data', 'fax_time');

$needfax_status = array(4 => 4, 5 => 5);
if ($autotime > 0) {
	$hournow = date_i18n('H');
	if ($hournow < $autotime) {
		unset($needfax_status[5]);
	}
} else {
	//return;
}

$log_file = WP_CONTENT_DIR . '/log/auto_fax_results.txt';
$f 		= fopen($log_file, 'a+');
if ($f) {
	fwrite($f, date_i18n('Y-m-d H:i:s') . ":\n");
}

$needfax_status_str = implode(',', $needfax_status);


$db_version 	= get_option('TRCPDD_VS');

if ($db_version != TRCPDD_VS) {
	trpdd_plugin_activate();
}

$can_fax    = true;

if ($fax->is_expired()) {
	$rsrr = $fax->refreshToken();
	$need_break = true;
	if (is_array($rsrr) && !empty($rsrr['access_token'])) {
		$need_break =  false;
	} else if ($rsrr === true) {
		$need_break = false;
	}

	if ($need_break) {
		$can_fax = false;
	}
}


if (!$can_fax) {
	echo 'Cant fax';
	return;
}

$datetimenow = date_i18n('Y-m-d H:i:s');
$limit_datetime = date('Y-m-d', strtotime($datetimenow) - 86400);
$outtimebyhand = strtotime($datetimenow) - 1000; //out 1000s then update status to 5
echo 'date: ' . $limit_datetime . '<br>';

$wpdb->query("SET SESSION group_concat_max_len = 1000000;");

foreach (tr_pdd_get_batch_types() as $btype) {
	$auto_fax   = tr_get_option('ci_pdf_data', 'auto_fax_approve_' . $btype);
	if (!$auto_fax) {
		echo $btype . '=> not enable<br>';
		continue;
	}

	$type_result = 'rs_efax';
	if (!empty($btype) && $btype != 'tox') {
		$type_result .= $btype;
	}

	$fax_ids = array();

	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);



	$sql = "select
     re.id,
     re.upload_files,
     ba.mid,
     ba.assen,
     ba.cli_ID,
     ba.result_pdf,
     ba.lab_id,
     ba.batch_id,
     cli.`cli_om-fax` as fax,
     cli.cli_clinic,
     re.modified,
     bu.id as box_id,
     bu.status as box_status,
     bu.uptime,
     bu.path,
     bu.sender,
     bu.etag,
     bu.created,
     bu.status
     
    from {$batch_tbl} ba
    left JOIN {$report_tbl} re ON ba.assen = re.patient_id
    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
    JOIN ci_clinic cli ON ba.cli_ID = cli.cID 
    Left JOIn ci_box_upload bu ON bu.mid = ba.mid 
            and bu.type='{$type_result}' 
            
    where re.estatus='C' and re.faxed=0
        and re.approve_date >= '{$limit_datetime}'
        and cli.cli_faxres = '1'
        and cli.`cli_om-fax` != ''
        and bu.id is null
    group by ba.mid 
    order by ba.doc asc";


	$results 	= $wpdb->get_results($sql, ARRAY_A);
	$sent_before = '';

	foreach ($results as $re) {
		if ($re['status'] === '1') continue;

		if ($re['box_id'] == 0) {

			$report = tr_pdd_get_report_by_id(0, $btype, $re['mid']);
			if (!$report) continue;

			list($path, $file) = trpdd_get_path_result_pdf($report, $btype);
			$path_db = $path . '/' . $file;

			if (!file_exists($result_dir . $path)) {
				wp_mkdir_p($result_dir . $path);
			}

			$wpdb->insert(
				'ci_box_upload',
				array(
					'path' => $path_db,
					'type' => $type_result,
					'status'     => '5',
					'mid' => $re['mid'],
					'account_id' => $re['cli_ID'],
					'etag' => trim($re['fax']),
					'lab_ID' => $re['lab_id'],
					'batch_id' => $re['batch_id'],
					'btype' => $btype,
					'sender' => $re['cli_clinic'],
					'created' => date_i18n('Y-m-d H:i:s'),
					'created_by' => '-1'
				)
			);
			$re['box_id'] = $wpdb->insert_id;

			if ($f) {
				fwrite($f,  "added to list: {$re['assen']} {$file}\n");
			}
		} else if ($re['status'] == 0 && strtotime($re['created']) < $outtimebyhand) {
			$wpdb->query("update ci_box_upload set status = 5 where id = {$re['box_id']} and status = 0");
			if ($f) {
				fwrite($f,  "added to list:  {$re['assen']}:\n");
			}
		}
	}
}


//check efax
if ($can_fax) {
	$groupby = 'group by id';
	if ($fax->api_slug == 'CI_Fax_RingCentral') {
		$groupby = 'group by etag,btype';
	}
	$uploads = $wpdb->get_results("select 
                                group_concat(id) as ids,
                                group_concat(mid) as mids,
                                group_concat(path) as paths,
                                etag,
                                sender,
                                btype
                                
                                from ci_box_upload 
                                where type in('rs_efax','rs_efaxpcr','rs_efaxblood')
                                AND status in({$needfax_status_str})
                                {$groupby}
                                order by id ASC 
                                limit 10");





	foreach ($uploads as $upload) {
		$btype = $upload->btype;
		$report_tbl = tr_pdd_get_table_report_by_btype($btype);
		$batch_tbl = tr_pdd_get_table_by_btype($btype);

		$rs = false;
		$files = array();
		$ids = explode(',', $upload->ids);
		$mids = explode(',', $upload->mids);
		$paths = explode(',', $upload->paths);

		foreach ($paths as $i => $path) {
			$mid = $mids[$i];
			$file = $result_dir . $path;
			$filetime = filectime($file);
			if (stripos($file, 'uploads/') === false) {
				$file = trpdd_export_results_pdf($mid, 'mid', false, $btype);
				if (! $file) {

					echo 'file not exist: ' . $file . '<br>';
					if ($f) {
						fwrite($f,  'file not exist: ' . $file . "\n");
					}
					continue;
				}
			}

			if (file_exists($file)) {
				$files[basename($file)] = $file;
			}
		}


		if (count($files) == 0) {
			echo 'empty files<br>';
			if ($f) {
				fwrite($f,  'empty files'  . "\n");
			}
			continue;
		}

		//var_dump($files);
		$number = trim($upload->etag);
		$number = str_replace(array('-', '(', ')', ' '), '', $number);


		$error 		= '';
		$error_log 	= '';
		$needbreak 	= false;

		if ($fax->is_expired()) {
			$fax->refreshToken();
			if ($fax->is_expired()) {
				$needbreak = true;
				$response['status'] = 'error';

				if ($f) {
					fwrite($f,  'efax is expired'  . "\n");
				}
				break;
			}
		}

		if ($is_debug) {
			$response['use'] = 'debug';
			$rs = $fax->send_debug($number, $files, $upload->sender);
		} else {
			$response['use'] = 'api';
			$rs = $fax->send($number, $files, $upload->sender);
		}


		if ($rs) {
			$wpdb->query("update ci_box_upload set status = 1,uptime='" . time() . "' where id in ({$upload->ids})");
			$wpdb->query("update {$report_tbl} set faxed = 1 
                                where patient_id in (select assen from {$batch_tbl} where mid in ({$upload->mids}))");

			//add log
			foreach ($mids as $mid) {
				trpdd_log_user(-1, 'fax', $upload->etag, $mid, $btype);
			}
			if ($f) {
				fwrite($f,  'faxed'  . "\n");
			}
		} else {
			$wpdb->query("update ci_box_upload set status = 6,log='" . $error_log . "' where id in ({$upload->ids})");
			if ($f) {
				fwrite($f,  'fax error:' . $error_log . "\n");
			}
		}

		if ($needbreak) {
			break;
		}
		sleep(2);
	}
}

$faxes = $wpdb->get_var("select count(*) from `ci_box_upload` 
								where type in('rs_efax','rs_efaxpcr','rs_efaxblood')
                                AND status in({$needfax_status_str}) 
                                AND uptime< {$uptime} ");
if ($faxes > 0) {
	$nextcron = wp_next_scheduled('ci_auto_fax_results');
	if ($nextcron == 0 || $nextcron > time() + 300) {
		wp_schedule_single_event(time() + 100, 'ci_auto_fax_results', array('single'));
	}
}


if ($f) {
	fwrite($f,  "end\n\n\n");
	fclose($f);
	if (filesize($log_file) > 500000) {
		$olddata = substr(file_get_contents($log_file), 390000);
		file_put_contents($log_file, $olddata);
	}
}
