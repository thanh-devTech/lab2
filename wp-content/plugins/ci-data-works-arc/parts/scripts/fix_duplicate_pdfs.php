<?php
global $wpdb;
$dir = tr_get_result_upload_dir();
$btype = isset($_GET['btype']) ? sanitize_text_field($_GET['btype']) : 'gene';
$batch_tbl = tr_pdd_get_table_by_btype($btype);
$where = '';
//$where .= ' and mid = 320';
$sql = "select * from {$batch_tbl} where result_pdf !='' {$where} order by mid desc ";
$rs = $wpdb->get_results($sql);
echo count($rs) . " batches found.<br/>";
foreach ($rs as $r) {
	$pdfs = explode(',', $r->result_pdf);
	if (count($pdfs) <= 1) {
		continue;
	}

	$new_files = array();
	$file_sizes = array();
	$md5_map = array();
	$has_change = false;
	foreach ($pdfs as $k => $v) {
		$md5 = md5_file($dir['path'] . '/' . $v);
		$filesize = filesize($dir['path'] . '/' . $v);
		if (in_array($md5, $md5_map)) {
			//duplicate found
			$has_change = true;
			continue;
		}
		//check file sizes
		$has_same_size = false;
		foreach ($file_sizes as $fsizeold) {
			if ($fsizeold > $filesize - 418687 && $fsizeold < $filesize + 418687) {
				//duplicate found
				$has_change = true;
				$has_same_size = true;
				break;
			}
		}

		if($has_same_size) {
			continue;
		}



		$md5_map[$v] = $md5;
		$file_sizes[$v] = $filesize;
		$new_files[] = $v;
	}
	echo '<pre>';
	var_export($md5_map);
	var_export($file_sizes);
	echo '</pre>';
	if ($has_change) {
		var_dump($new_files);
		$new_pdf_str = implode(',', $new_files);
		$wpdb->update($batch_tbl, array('result_pdf' => $new_pdf_str), array('mid' => $r->mid));
		echo "Fixed duplicate PDFs for batch mid: {$r->mid}<br/>";
	}
}
