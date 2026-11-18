<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb;
$log_file = WP_CONTENT_DIR . '/log/cron_check_pdf_card.txt';
$limit_delete_pdf = time() - 86400 * 50;
if (time() - filemtime($log_file) < 500 && !isset($_GET['testcron'])) {
	return;
}


$f_log = fopen($log_file, 'a+');
fwrite($f_log, date_i18n('Y-m-d H:i:s') . " => start\n");

//wp_clear_scheduled_hook('ci_check_pdf_card_single_cron');

$btypes = tr_pdd_get_batch_types();
$keys = array(
	'ins_card_front',
	'ins_card_back',
	'patient_id_front',
	'patient_id_back',
);
$last_ids = get_option('ci_check_pdf_cards', array());


$ci_pdf_image = new ci_pdf_image();
$dir = tr_get_ins_card_dir();
$limit_cvpdf = 50;
foreach ($btypes as $btype) {
	$table = tr_pdd_get_table_by_btype($btype);
	$last_ids[$btype] = intval($last_ids[$btype]);

	$results = $wpdb->get_results("select * from {$table}
    where (ins_card_front like '%.pdf'
                or ins_card_back like '%.pdf'
                or patient_id_front like '%.pdf'
                or patient_id_back like '%.pdf'
          )
        and mid > {$last_ids[$btype]}
    order by mid asc
    limit {$limit_cvpdf}", ARRAY_A);
	$count = 0;

	foreach ($results as $row) {
		$update = array();
		fwrite($f_log, '=>' . $row['mid']);
		foreach ($keys as $k) {
			if (stripos($row[$k], '.pdf') > 0) {

				$oldname = $row[$k];
				$filepath = $dir['path'] . '/' . $oldname;
				if (!file_exists($filepath)) {
					$newname = str_replace('.pdf', '.jpg', $row[$k]);
					$imagepath = $dir['path'] . '/' . $newname;
					if (file_exists($imagepath)) {
						echo $row['mid'] . ' replace pdf to image: ' . $newname . '<br><br>';
						$update[$k] = $newname;
					} else {
						echo $row['mid'] . ' file not exist: ' . $row[$k] . '<br><br>';
						$update[$k] = '';
					}
				} else {
					$info       = pathinfo($oldname);
					$newname    = str_replace('.' . $info['extension'], '.jpg', $oldname);
					$imagepath  = $dir['path'] . '/' . $newname;

					if (trpdd_convert_pdf_to_image($filepath, $imagepath, true, '900x1200', 100)) {
						$ci_pdf_image->image_trim_white($imagepath, 13521906);

						$fsize = getimagesize($imagepath);
						if ($fsize === false) {
							unlink($imagepath);
						} else {
							$update[$k] = $newname;
							unlink($filepath);
							fwrite($f_log, '=>' . $row[$k] . ' => ' . $newname . "\n");
						}
					} else {

						$filesize = filesize($filepath);
						echo 'convert fail: ' . $row[$k] . ' => ' . $filesize;
						if ($filesize == 1339650 || $filesize == 0) {
							$update[$k] = '';
							unlink($filepath);
						}
						echo '<br><br>';
					}
				}
			}
		}

		if (count($update) > 0) {
			$wpdb->update($table, $update, array('mid' => $row['mid']));
		}
		$last_ids[$btype] = $row['mid'];
	}

	if (count($results) == $limit_cvpdf) {
		$nexttime = wp_next_scheduled('ci_check_pdf_card_single_cron');
		if (empty($nexttime)) {
			wp_schedule_single_event(time() + 60, 'ci_check_pdf_card_single_cron');
		}
	}
}

update_option('ci_check_pdf_cards', $last_ids);



//fix card size
$dir = tr_get_ins_card_dir();
$files = scandir($dir['path']);
$count = 0;
$limit = 100;
$timeout = time() - 86400 * 50;
echo '<br>scan folder:' . $dir['path'] . '<br>';

$total = 0;
$count_heic = 0;
$count_png = 0;
$skipbysize = 0;
$count_timeout = 0;
$count_cantgetsize = 0;
$count_invalid_image = 0;

foreach ($files as $file) {
	$path = $dir['path'] . '/' . $file;
	$size = filesize($path);
	$filetime = filemtime($path);

	$last4t = substr($filetime, strlen($filetime) - 4, 4);
	$last5t = substr($filetime, strlen($filetime) - 5, 5);

	if ($file == '..' || $file == '.') continue;
	if ( $last5t == '00000') continue;

	$total++;

	if (stripos($file, '.HEIC') > 0) {
		$count_heic++;
		//continue;
	}


	if (stripos($file, '.pdf') > 0) {
		if ($filetime < 1000) {
			continue;
		}
		if ($filetime < $limit_delete_pdf) {
			fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' =>  deleted: ' . $file . ' => size: ' . $size);
			unlink($path);
			continue;
		}

		if ($filetime < time() - 86400 * 7) {

			$imagefile = str_replace('.pdf', '.jpg', $path);
			if (file_exists($imagefile)) {
				unlink($path);
				continue;
			}

			//check exist in db

			$col_key = '';
			if (stripos($file, 'patient_id_back') !== false) {
				$col_key = 'patient_id_back';
			} else if (stripos($file, 'patient_id_front') !== false) {
				$col_key = 'patient_id_front';
			} else if (stripos($file, 'ins_card_front') !== false) {
				$col_key = 'ins_card_front';
			} else if (stripos($file, 'ins_card_back') !== false) {
				$col_key = 'ins_card_back';
			}
			if (!empty($col_key)) {
				$existdb = false;
				foreach ($btypes as $btype) {
					$table = tr_pdd_get_table_by_btype($btype);

					$exist = $wpdb->get_var("select mid from {$table} where {$col_key} = '{$file}' ");
					if ($exist > 0) {
						$existdb = true;
						break;
					}
				}

				if (!$existdb) {
					echo '<br>file not exist in db: ' . $file . '<br>';

					unlink($path);
					continue;
				}
			}
		}
		continue;
	}

	//check lower case
	$path_lower = strtolower($path);
	$path_lower2 = strtolower(str_ireplace(array('.PNG', '.HEIC', '.JPEG'), '.jpg', $path));
	if ($path_lower != $path && file_exists($path_lower) && $size != filesize($path_lower)) {
		unlink($path_lower);
	} else if ($path_lower2 != $path && file_exists($path_lower2) && $size != filesize($path_lower2)) {
		unlink($path_lower2);
	}

	if ($filetime < $timeout) {
		if ($size > 900000 && (stripos($file, '.jpg') > 0 || stripos($file, '.jpeg') > 0)) {
			$fsize = getimagesize($path);

			if ($fsize === false) {
				unlink($path);
				echo 'file timeout1<br>';
				continue;
			}
		}

		//echo $path;
		$count_timeout++;
		//echo 'file timeout<br>';
		continue;
	}

	$max_size = 690000;
	$max_width = 700;
	$max_height = 700;

	if (count($files) > 100000) {
		$max_size = 500000;
		$max_width = 600;
		$max_height = 600;
	}


	if (stripos($file, '.png') > 0) {
		$max_size = 900000;
		$max_width = 580;
		$max_height = 580;

		if (count($files) > 100000) {
			$max_size = 500000;
			$max_width = 500;
			$max_height = 500;
		}
	}

	if ($size < $max_size) {
		$skipbysize++;
		continue;
	}

	$fsize = getimagesize($path);
	if ($fsize === false) {
		//echo 'cant get size';
		$count_cantgetsize++;
		$stlen = strlen($filetime);
		$filetime = substr($filetime, 0, $stlen - 5) . '00000';
		touch($path, $filetime);
		continue;
	} else if ($fsize[0] <= $max_width && $fsize[1] <= $max_height) {
		var_dump($path);
		var_dump($fsize);
		var_dump($size);
		echo '<br><br>';
		continue;
	}


	echo $path;



	$image = wp_get_image_editor($path);
	if (! is_wp_error($image)) {
		$image->set_quality(80);
		$rss  = $image->resize($max_width, $max_height, false);

		if ($rss === true) {
			$newpathname = $path . '.tmp';
			rename($path, $newpathname);
			$resized = $image->save($path);

			if ($resized) {

				if ($resized['path'] != $path) {
					echo '=>' . $resized['path'];
					@unlink($path);
					rename($resized['path'], $path);
				}

				echo ' => ok';
				if (filesize($path) < 100) {
					@unlink($path);
					rename($newpathname, $path);
					echo ' => fail2';
				} else {
					touch($path, $filetime);
				}
			} else {
				echo ' => fail';
				@unlink($path);
				rename($newpathname, $path);
			}
			@unlink($newpathname);
		} else {

			var_dump($fsize);
			var_dump($rss);
			echo 'Resize fail: ' . $file . '<br><br>';
		}
	} else if (isset($image->errors['invalid_image'])) {
		$count_invalid_image++;
		//touch($path, $filetime - 86400);
		continue;
	} else {
		var_dump($image);
		if ($size < 1000000) {
			$stlen = strlen($filetime);
			$filetime = substr($filetime, 0, $stlen - 5) . '00000';
			touch($path, $filetime);
			continue;
		}
	}



	$newsize = filesize($path);

	fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' =>  ' . $file . ' => size: ' . $size);
	fwrite($f_log, '=> new size:' . $newsize . "\n");


	echo ' =>size:' . $size;
	echo ' =>new size:' . $newsize;
	echo '=> time: ' . $filetime;

	if ($size == $newsize || $newsize > $max_size) {
		$stlen = strlen($filetime);
		$filetime = substr($filetime, 0, $stlen - 4) . '0000';
		touch($path, $filetime);
	}


	echo '=> newtime: ' . $filetime;
	echo '<br>';

	$count++;

	if ($count > $limit) {
		$nexttime = wp_next_scheduled('ci_check_pdf_card_single_cron');
		if (empty($nexttime)) {
			wp_schedule_single_event(time() + 60, 'ci_check_pdf_card_single_cron');
		}
		echo '<meta http-equiv="refresh" content="2">';
		touch($log_file, time() - 10000);
		break;
	}
}
echo '$total: ' . $total . '<br>';
echo '$count_png: ' . $count_png . '<br>';
echo '$count_heic: ' . $count_heic . '<br>';
echo '$count: ' . $count . '<br>';
echo '$skipbysize: ' . $skipbysize . '<br>';
echo '$count_cantgetsize: ' . $count_cantgetsize . '<br>';
echo '$count_invalid_image: ' . $count_invalid_image . '<br>';
echo '$count_timeout: ' . $count_timeout . '<br>';


//fix signatures size
$dir = tr_get_signature_dir();
$files = scandir($dir['path']);
$count = 0;
$limit = 10;
$timeout = time() - 86400;
foreach ($files as $file) {
	if ($file == '..' || $file == '.' || stripos($file, '.pdf') > 0) continue;
	$path = $dir['path'] . '/' . $file;
	$size = filesize($path);
	$filetime = filemtime($path);

	if ($filetime < $timeout) continue;

	$last4t = substr($filetime, strlen($filetime) - 4, 4);
	$max_size = 600000;
	if (stripos($file, '.png') > 0) {
		$max_size = 1200000;
	}


	if ($size < $max_size || $last4t === '0000') {

		continue;
	}



	$image = wp_get_image_editor($path);
	if (! is_wp_error($image)) {
		$image->set_quality(70);
		if ($image->resize(500, 500, false)) {
			$resized = $image->save($path);

			if ($resized && isset($resized['path'])) {

				if ($resized['path'] != $path) {
					@unlink($path);
					rename($resized['path'], $path);
				}
			}
			touch($path, $filetime);
		}
	}

	$newsize = filesize($path);
	fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' =>  ' . $file . ' => size: ' . $size);
	fwrite($f_log, '=> new size:' . $newsize . "\n");


	if ($size == $newsize) {
		fwrite($f_log, date_i18n('Y-m-d H:i:s') . '=> time: ' . $filetime . "\n");

		$stlen = strlen($filetime);
		$filetime = substr($filetime, 0, $stlen - 4) . '0000';
		touch($path, $filetime);
		fwrite($f_log, date_i18n('Y-m-d H:i:s') . '=> new time: ' . $filetime . "\n");
	}


	$count++;

	if ($count > $limit) {
		$nexttime = wp_next_scheduled('ci_check_pdf_card_single_cron');
		if (empty($nexttime)) {
			wp_schedule_single_event(time() + 60, 'ci_check_pdf_card_single_cron');
		}
		echo '<meta http-equiv="refresh" content="1">';
		touch($log_file, time() - 10000);
		return;
	}
}




//fix issue photos
$dir = tr_get_issue_photo_dir();
$files = scandir($dir['path']);
$count = 0;
$limit = 10;
$timeout = time() - 86400;
echo '<br>scan folder:' . $dir['path'] . '<br>';

foreach ($files as $file) {
	$oldfile = $file;
	$file = strtolower($file);
	if ($oldfile !== $file) {
		rename($dir['path'] . '/' . $oldfile, $dir['path'] . '/' . $file);

		foreach ($btypes as $btype) {
			$table = tr_pdd_get_table_by_btype($btype);
			$wpdb->update($table, array('issue_photo' => $file), array('issue_photo' => $oldfile));
		}
	}

	$path = $dir['path'] . '/' . $file;
	$size = filesize($path);
	$filetime = filemtime($path);

	if ($file == '..' || $file == '.') continue;

	if ($filetime > 1000 && $filetime < 86400 * 465) {
		unlink($path);
		continue;
	}

	if (stripos($file, '.pdf') > 0 && $filetime > 1000) {
		if ($filetime < $limit_delete_pdf) {
			fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' =>  deleted: ' . $file . ' => size: ' . $size);
			unlink($path);
			continue;
		}

		if ($filetime < time() - 86400 * 7) {
			$imagefile = str_replace('.pdf', '.jpg', $path);
			if (file_exists($imagefile)) {
				unlink($path);
				continue;
			}
		}

		continue;
	}



	$last4t = substr($filetime, strlen($filetime) - 4, 4);


	if ($filetime < $timeout) {

		if ((stripos($file, '.jpg') > 0 || stripos($file, '.jpeg') > 0) && $size > 1000000) {
			$fsize = getimagesize($path);

			if ($fsize === false) {
				unlink($path);
			}
		}
		continue;
	}
	$max_size = 600000;
	if (stripos($file, '.png') > 0) {
		$max_size = 1200000;
	}


	if ($size < $max_size || $last4t === '0000') {
		continue;
	}


	$image = wp_get_image_editor($path);
	if (! is_wp_error($image)) {
		$image->set_quality(80);
		if ($image->resize(1000, 1000, false) === true) {

			$resized = $image->save($path);

			if ($resized && isset($resized['path'])) {

				if ($resized['path'] != $path) {
					@unlink($path);
					rename($resized['path'], $path);
				}
				touch($path, $filetime);
				if ($oldfile !== $file) {
					unlink($dir['path'] . '/' . $oldfile);
				}
			} else {

				echo 'save file err';
			}
		} else {
			var_dump($path);
		}
	} else {
		echo 'image load err';
	}

	$newsize = filesize($path);


	fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' =>  ' . $file . ' => size: ' . $size);
	fwrite($f_log, '=> new size:' . $newsize . "\n");


	$count++;

	if ($count > $limit) {
		$nexttime = wp_next_scheduled('ci_check_pdf_card_single_cron');
		if (empty($nexttime)) {
			wp_schedule_single_event(time() + 60, 'ci_check_pdf_card_single_cron');
		}
		touch($log_file, time() - 10000);
		echo '<meta http-equiv="refresh" content="1">';
		return;
	}
}


touch($log_file, time() - 10000);
