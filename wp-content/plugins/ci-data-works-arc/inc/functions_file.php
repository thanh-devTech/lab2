<?php

function tr_get_signature($data)
{
	$data_pieces = @explode(",", $data, 2);
	if (isset($data_pieces[1])) {
		$encoded_image = $data_pieces[1];
		$data = base64_decode($encoded_image);
	}

	return $data;
}

function tr_get_image_from_base64($data)
{
	$data_pieces = explode(",", $data, 2);
	if (stripos($data_pieces[0], ';base64') !== false) {
		$encoded_image = $data_pieces[1];
	} else {
		$encoded_image = $data;
	}
	$decoded_image = base64_decode($encoded_image);
	return $decoded_image;
}

function tr_get_image_data($file)
{
	$value = file_get_contents($file);
	if (!empty($value)) {
		$value = 'data:' . mime_content_type($file) . ';base64,' . base64_encode($value);
	}

	return $value;
}


function tr_get_signature_by($assen, $name)
{
	$sign_dir = tr_get_signature_dir();
	$file = $assen . '_' . $name . '.png';
	$file_path = $sign_dir['path'] . '/' . $file;
	if (file_exists($file_path) && filesize($file_path) > 0) {
		return tr_get_image_data($file_path);
	}
	return '';
}

function tr_get_card_by($assen, $name, $filename = '')
{
	$card_dir = tr_get_ins_card_dir();
	$file = !empty($filename) ? $filename : $assen . '_' . $name . '.jpg';
	$file_path = $card_dir['path'] . '/' . $file;
	if (file_exists($file_path) && filesize($file_path) > 0) {
		return tr_get_image_data($file_path);
	}
	return '';
}

function tr_save_signature($filename, $data, $file = '')
{
	if (empty($data) && is_array($file) && !empty($file['tmp_name'])) {
		$data = file_get_contents($file['tmp_name']);
	} else {
		$data = tr_get_signature($data);
	}

	if (empty($data)) return false;
	$dir = tr_get_signature_dir();
	$filename .= '.png';
	$filepath = $dir['path'] . '/' . $filename;

	if (file_put_contents($filepath, $data)) {
		return $filename;
	}
	return false;
}

function trpdd_get_user_signature()
{
	$user_id = get_current_user_id();
	$signature = get_user_meta($user_id, '_signature', true);
	return $signature;
}

function tr_wp_upload_dir()
{
	$upload_dir = wp_upload_dir();
	$upload_dir['basedir'] = preg_replace("/\/sites\/[0-9]+/", '', $upload_dir['basedir']);
	$upload_dir['baseurl'] = preg_replace("/\/sites\/[0-9]+/", '', $upload_dir['baseurl']);
	return $upload_dir;
}

function trpdd_get_install_folders()
{
	$upload_dir = tr_wp_upload_dir();

	return array(
		array('label' => 'Signatures', 'path' => $upload_dir['basedir'] . '/signatures'),
		array('label' => 'Insurance Cards', 'path' => $upload_dir['basedir'] . '/ins_cards'),
		array('label' => 'Logos', 'path' => $upload_dir['basedir'] . '/logos'),
		array('label' => 'Batch Imports', 'path' => $upload_dir['basedir'] . '/batch_imports'),
		array('label' => 'Bill Imports', 'path' => $upload_dir['basedir'] . '/bill_imports'),
		array('label' => 'Insurance Mix', 'path' => $upload_dir['basedir'] . '/ins_mix'),
		array('label' => 'Issue Photos', 'path' => $upload_dir['basedir'] . '/issue_photo'),
		array('label' => 'Temp', 'path' => WP_CONTENT_DIR . '/tmp'),
		array('label' => 'Cache', 'path' => WP_CONTENT_DIR . '/cache'),
		array('label' => 'Req Cache', 'path' => WP_CONTENT_DIR . '/cache/reqs'),
		array('label' => 'Results', 'path' => WP_CONTENT_DIR . '/results'),
		array('label' => 'HTML Results Cache', 'path' => WP_CONTENT_DIR . '/results/html'),
		array('label' => 'Result Uploads', 'path' => WP_CONTENT_DIR . '/results/uploads'),
		array('label' => 'Reference Results', 'path' => WP_CONTENT_DIR . '/results/ref'),
		array('label' => 'Error Logs', 'path' => WP_CONTENT_DIR . '/log/errors'),
		array('label' => 'Result Logs', 'path' => WP_CONTENT_DIR . '/log/results'),
		array('label' => 'Prefill Covid Logs', 'path' => WP_CONTENT_DIR . '/log/prefillcovid'),
		array('label' => 'Tecan Uploads', 'path' => $upload_dir['basedir'] . '/tecan'),
		array('label' => 'Logs', 'path' => WP_CONTENT_DIR . '/log'),
		array('label' => 'Log Import Tox', 'path' => WP_CONTENT_DIR . '/log/results'),
		array('label' => 'Log Import PCR', 'path' => WP_CONTENT_DIR . '/log/results_pcr'),
		array('label' => 'Log Import Blood', 'path' => WP_CONTENT_DIR . '/log/results_blood'),
		array('label' => 'Log Import Req', 'path' => WP_CONTENT_DIR . '/log/results_req'),
		array('label' => 'Log Import Gene', 'path' => WP_CONTENT_DIR . '/log/results_gene'),
		array('label' => 'Paperwork', 'path' => WP_CONTENT_DIR . '/paperwork'),
		array('label' => 'QR Codes', 'path' => WP_CONTENT_DIR . '/qrs'),
		array('label' => 'Test folder', 'path' => WP_CONTENT_DIR . '/test'),
	);
}

function tr_get_signature_dir()
{
	$dir = "/signatures";
	$upload_dir = tr_wp_upload_dir();
	$return = array();
	$return['path'] = $upload_dir['basedir'] . $dir;
	$return['dir'] = $upload_dir['basedir'] . $dir;
	$return['url'] = $upload_dir['baseurl'] . $dir;
	return $return;
}

function tr_get_ins_card_dir()
{
	$dir = "/ins_cards";
	$upload_dir = tr_wp_upload_dir();
	$return = array();
	$return['path'] = $upload_dir['basedir'] . $dir;
	$return['url'] = $upload_dir['baseurl'] . $dir;
	return $return;
}

function tr_get_issue_photo_dir()
{
	$dir = "/issue_photo";
	$upload_dir = tr_wp_upload_dir();
	$return = array();
	$return['path'] = $upload_dir['basedir'] . $dir;
	$return['url'] = $upload_dir['baseurl'] . $dir;
	return $return;
}

function tr_get_order_req_photo_dir()
{
	$dir = "/order_photo";
	$upload_dir = tr_wp_upload_dir();
	$return = array();
	$return['path'] = $upload_dir['basedir'] . $dir;
	if (!file_exists($return['path'])) {
		wp_mkdir_p($return['path']);
	}
	$return['url'] = $upload_dir['baseurl'] . $dir;
	return $return;
}

function tr_save_ins_card($filename, $file)
{
	if (!empty($file['tmp_name'])) {
		$dir = tr_get_ins_card_dir();
		$ext =  pathinfo($file['name'], PATHINFO_EXTENSION);
		$filename .= '.' . $ext;
		$filename = strtolower($filename);
		$filepath = $dir['path'] . '/' . $filename;
		if (file_exists($filepath)) {
			unlink($filepath);
		}

		if (move_uploaded_file($file['tmp_name'], $filepath)) {
			if (stripos($filename, '.pdf') !== false) {
				wp_clear_scheduled_hook('ci_check_pdf_card_single_cron');
				wp_schedule_single_event(time() + 200, 'ci_check_pdf_card_single_cron');
			}
			return $filename;
		} else {
			$err = 'error when upload file: ' . $filepath;
			Tr_Session_Class::add_error($err);
		}
	}
	return false;
}

function tr_save_card_file($filename, $data)
{
	if (empty($data)) return false;
	$data = tr_get_signature($data);
	if (empty($data)) return false;
	$dir = tr_get_ins_card_dir();
	$filename .= '.png';
	$filename = strtolower($filename);
	$filepath = $dir['path'] . '/' . $filename;
	if (file_put_contents($filepath, $data)) {
		return $filename;
	}
	return false;
}

function tr_save_issue_photo($filename, $file)
{
	if (!empty($file['tmp_name'])) {
		$dir = tr_get_issue_photo_dir();
		$ext =  pathinfo($file['name'], PATHINFO_EXTENSION);
		$filename .= '.' . $ext;
		$filename = strtolower($filename);
		$filepath = $dir['path'] . '/' . $filename;
		if (file_exists($filepath)) {
			unlink($filepath);
		}

		if (move_uploaded_file($file['tmp_name'], $filepath)) {
			return $filename;
		} else {
			$err = 'error when upload file: ' . $filepath;
			Tr_Session_Class::add_error($err);
		}
	}
	return false;
}

function tr_save_order_req_upload($filename, $file)
{
	if (!empty($file['tmp_name'])) {
		$dir = tr_get_order_req_photo_dir();
		$ext =  pathinfo($file['name'], PATHINFO_EXTENSION);
		$filename .= '.' . $ext;
		$filename = strtolower($filename);
		$filepath = $dir['path'] . '/' . $filename;
		if (file_exists($filepath)) {
			unlink($filepath);
		}

		if (move_uploaded_file($file['tmp_name'], $filepath)) {
			return $filename;
		} else {
			$err = 'error when upload file: ' . $filepath;
			Tr_Session_Class::add_error($err);
		}
	}
	return false;
}


function tr_pdd_convert_signature_pdf_to_img($filename, $again = false, $resize = '1200x1200', $quality = 60)
{
	if (stripos($filename, '.pdf') === false) {
		return false;
	}
	$dir        = tr_get_signature_dir();
	$filepath   = $dir['path'] . '/' . $filename;
	$size       = getimagesize($filepath);
	$info       =  pathinfo($filename);

	if (!empty($resize)) {
		$resize = "-resize {$resize}";
	}

	if (!$size && file_exists($filepath)) {
		$newname = str_replace('.' . $info['extension'], '.jpg', $filename);
		$newpath = $dir['path'] . '/' . $newname;

		if (!file_exists($newpath) || $again == true) {
			$pdf_file = escapeshellarg($filepath);
			$jpg_file = escapeshellarg($newpath);

			$result = 0;
			$exc = "convert -quality {$quality} -density 300 {$resize} -append {$pdf_file} {$jpg_file}";

			try {
				exec($exc, $out, $result);

				if (file_exists($newpath)) {
					return $newname;
				}
			} catch (Exception $e) {
				var_dump($e);
			}
		}
	}
	return false;
}

function trpdd_convert_pdf_to_image($pdf_path, $image_path, $again = false, $resize = '1200x1200', $quality = 60)
{
	if (stripos($pdf_path, '.pdf') === false) {
		return false;
	}
	$size = getimagesize($pdf_path);

	if (!empty($resize)) {
		$resize = "-resize {$resize}";
	}

	if (!$size && file_exists($pdf_path)) {
		if (!file_exists($image_path) || $again == true) {
			$pdf_file = escapeshellarg($pdf_path);
			$jpg_file = escapeshellarg($image_path);

			$result = 0;
			$exc = "convert -quality {$quality} -density 300 {$resize} -append {$pdf_file} {$jpg_file}";

			try {
				exec($exc, $out, $result);

				if (file_exists($image_path)) {
					return true;
				}
			} catch (Exception $e) {
				var_dump($e);
			}
		}
	}
	return false;
}


function tr_get_result_upload_dir($keytype = '')
{
	$dir = '/results/uploads/';
	$return = array();
	$return['path'] = WP_CONTENT_DIR . $dir;
	$return['url'] = WP_CONTENT_URL . $dir;
	if (!file_exists($return['path'])) {
		wp_mkdir_p($return['path']);
	}
	if (!empty($keytype)) {
		return $return[$keytype];
	}
	return $return;
}


function tr_save_result_upload($filename, $file)
{
	$dir = tr_get_result_upload_dir();
	if (!file_exists($dir['path'])) {
		wp_mkdir_p($dir['path']);
	}

	if (is_array($file) && !empty($file['tmp_name'])) {
		if (is_array($file['tmp_name'])) {
			$files_name = array();
			foreach ($file['name'] as $i => $name) {
				if (empty($file['tmp_name'][$i])) {
					continue;
				}

				$ext =  pathinfo($name, PATHINFO_EXTENSION);
				if (empty($ext)) $ext = 'pdf';

				$fname = $filename . '.' . $ext;
				$filepath = $dir['path'] . '/' . $fname;
				$num = 1;
				while (file_exists($filepath)) {
					$fname = $filename . '.' . $num . '.' . $ext;
					$filepath = $dir['path'] . '/' . $fname;
					$num++;
				}
				if (move_uploaded_file($file['tmp_name'][$i], $filepath)) {
					$files_name[] = $fname;
				} else {
					$err = 'error when upload file: ' . $filepath;
					Tr_Session_Class::add_error($err);
				}
			}
			return implode(',', $files_name);
		} else if (!empty($file['name'])) {
			$ext =  pathinfo($file['name'], PATHINFO_EXTENSION);
			$filename .= '.' . $ext;
			$filepath = $dir['path'] . '/' . $filename;
			if (file_exists($filepath)) {
				unlink($filepath);
			}


			if (move_uploaded_file($file['tmp_name'], $filepath)) {
				return $filename;
			} else {
				$err = 'error when upload file: ' . $filepath;
				Tr_Session_Class::add_error($err);
			}
		}
	} else if (is_string($file) && !empty($file)) {
		$data = tr_get_signature($file);
		if (empty($data)) return false;
		$filename .= '.pdf';
		$filepath = $dir['path'] . '/' . $filename;
		if (file_put_contents($filepath, $data)) {
			return $filename;
		}
	}
	return false;
}

function tr_save_result_pdfs_upload($signature_id, $current_result_pdf = '', $multi_file = null, $single_file = null)
{
	$multi_file = tr_filter_pdf_upload_file($multi_file);
	$single_file = tr_filter_pdf_upload_file($single_file);

	return tr_save_multi_result_upload($signature_id, $current_result_pdf, $multi_file, $single_file);
}

function tr_save_multi_result_upload($signature_id, $current_files = '', $multi_file = null, $single_file = null)
{
	if (is_array($multi_file) && !empty($multi_file['tmp_name'])) {
		$new_pdfs = tr_save_result_upload($signature_id, $multi_file);

		$files = array();
		if (!empty($current_files)) {
			$files = array_filter(explode(',', $current_files));
		}

		if (!empty($new_pdfs)) {
			$arr = explode(',', $new_pdfs);
			foreach ($arr as $npdf) {
				if (!in_array($npdf, $files)) {
					$files[] = $npdf;
				}
			}
		}

		return implode(',', $files);
	}

	return tr_save_result_upload($signature_id, $single_file);
}

function tr_filter_pdf_upload_file($file)
{
	if (!is_array($file) || empty($file['name'])) {
		return $file;
	}

	if (is_array($file['name'])) {
		foreach ($file['name'] as $i => $name) {
			if (empty($name) || empty($file['tmp_name'][$i])) {
				continue;
			}
			if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) != 'pdf') {
				Tr_Session_Class::add_error('Only PDF files are allowed: ' . $name);
				unset($file['name'][$i], $file['type'][$i], $file['tmp_name'][$i], $file['error'][$i], $file['size'][$i]);
			}
		}
		return $file;
	}

	if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) != 'pdf') {
		Tr_Session_Class::add_error('Only PDF files are allowed: ' . $file['name']);
		return null;
	}

	return $file;
}

function trpdd_render_req_upload_files($field, $value = '', $button_text = 'Upload Paper Req', $file_input_name = 'original_reqs', $show_button = true)
{
	$result_dir = tr_get_result_upload_dir();
	$value = !empty($value) ? $value : '';
?>
	<div class="req_result_pdf">
		<?php if ($show_button) { ?>
			<a class="button addfile" data-fname="<?php echo esc_attr($file_input_name) ?>" href="#"><?php echo esc_html($button_text) ?></a>
		<?php } ?>
		<input type="hidden" class="result_value" name="bat[<?php echo esc_attr($field) ?>]" value="<?php echo esc_attr($value) ?>" />
		<ul class="listresultfiles">
			<?php
			if (!empty($value)) {
				$files = explode(',', $value);
				foreach ($files as $f) {
					$f = trim($f);
					if ($f == '') {
						continue;
					}
					$file_url = $result_dir['url'] . '/' . $f . '?t=' . time();
			?>
					<li class="result_pdf_view" data-f="<?php echo esc_attr($f) ?>">
						<a href="<?php echo esc_url($file_url) ?>" target="_blank"><?php echo esc_html($f) ?></a> -
						<a class=" remove_result_pdf" href="#">Remove</a>
					</li>
			<?php
				}
			}
			?>
		</ul>
	</div>
<?php
}

function tr_get_paper_pdf_upload_dir()
{
	$dir = '/paper_pdf/';
	$return = array();
	$return['path'] = WP_CONTENT_DIR . $dir;
	$return['url'] = WP_CONTENT_URL . $dir;
	if (!file_exists($return['path'])) {
		wp_mkdir_p($return['path']);
	}
	return $return;
}

function tr_save_paper_pdf_upload($filename, $file)
{
	$dir = tr_get_paper_pdf_upload_dir();

	if (!empty($file['tmp_name'])) {
		$ext =  pathinfo($file['name'], PATHINFO_EXTENSION);
		$filename .= '.' . $ext;
		$filepath = $dir['path'] . '/' . $filename;
		if (file_exists($filepath)) {
			unlink($filepath);
			unlink(str_replace('.pdf', '.jpg', $filepath));
		}


		if (move_uploaded_file($file['tmp_name'], $filepath)) {
			return $filename;
		} else {
			$err = 'error when upload file: ' . $filepath;
			Tr_Session_Class::add_error($err);
		}
	}
	return false;
}

function trpdd_merge_pdf($pdf_paths)
{
	require_once(TRCPDD_PATH . 'inc/fpdf/fpdf.php');
	require_once(TRCPDD_PATH . 'inc/FPDI-2.6.0/src/autoload.php');

	$pdf_dir = '';

	$pdfstr = implode('', $pdf_paths);
	foreach ($pdf_paths as $pdf_path) {
		$pdfstr .= filemtime($pdf_path);
		if (empty($pdf_dir)) {
			$pdf_dir = dirname($pdf_path);
		}
	}
	$filename = 'tmp_' . md5($pdfstr) . '.pdf';
	$outfile = $pdf_dir . '/' . $filename;

	if (file_exists($outfile)) {
		return $outfile;
	}

	$temp_pdf = tempnam(WP_CONTENT_DIR . '/tmp', "exportpdf");
	$first_file = false;
	foreach ($pdf_paths as $pdf_path) {
		if (!$first_file) {
			copy($pdf_path, $temp_pdf);
			$first_file = true;
			continue;
		}

		try {
			$pdf = new \setasign\Fpdi\Fpdi();
			$pdf->SetFont('Helvetica');
			$pdf->SetFontSize(6);
			$pdf->SetTextColor(0, 0, 0);

			$pageCount = $pdf->setSourceFile($temp_pdf);
			// iterate through all pages
			for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
				// import a page
				$templateId = $pdf->importPage($pageNo, setasign\Fpdi\PdfReader\PageBoundaries::CROP_BOX, true, true);
				// get the size of the imported page
				$s = $pdf->getTemplateSize($templateId);

				$pdf->AddPage($s['orientation'], $s);
				// use the imported page
				$pdf->useTemplate($templateId);
			}

			//add pdf file
			$pageCount = $pdf->setSourceFile($pdf_path);
			// iterate through all pages
			for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
				// import a page
				$templateId = $pdf->importPage($pageNo, setasign\Fpdi\PdfReader\PageBoundaries::CROP_BOX, true, true);
				// get the size of the imported page
				$s = $pdf->getTemplateSize($templateId);

				$pdf->AddPage($s['orientation'], $s);

				// use the imported page
				$pdf->useTemplate($templateId);
			}

			$pdf->Output('F', $outfile);
		} catch (Exception $e) {
			//Tr_Session_Class::add_error('Error while merge PDF files: ' . $e->getMessage());
			$exc = "gs -dNOPAUSE -sDEVICE=pdfwrite -sOUTPUTFILE={$outfile} -dBATCH {$temp_pdf} {$pdf_path}";

			try {
				exec($exc, $out, $result);
			} catch (Exception $e) {
				var_dump($e);
			}
		}

		copy($outfile, $temp_pdf);
	}

	@unlink($temp_pdf);


	return $outfile;
}

function trpdd_add_result_pdf($report, $pdf_path, $btype = 'tox', $outfile = '')
{
	global $has_error_build_pdf;

	if (empty($report['content'])) {

		ob_start();
		$id = $report['id'];
		$has_report = false;

		$file_html = 'results_combine_ref.php';
		if ($btype != 'tox' && !empty($btype)) {
			$file_html = 'results_' . $btype . '.php';
		}

		if (!file_exists(TRCPDD_PATH . 'reports/' . $file_html)) {
			return $pdf_path;
		}

		include(TRCPDD_PATH . 'reports/' . $file_html);

		$content = ob_get_clean();
	} else {
		$content = $report['content'];
		$have_urine = true;
		$has_report = true;
	}
	if (isset($_GET['html2'])) {
		echo $content;
		exit;
	}


	if (!$have_urine && $btype == 'tox') {
		return $pdf_path;
	}

	if (isset($_GET['debugf2'])) {

		return $pdf_path;
	}

	$temp_pdf = '';

	if ($has_report && !empty($content)) {
		$temp_pdf = tempnam(WP_CONTENT_DIR . '/tmp', "exportpdf");

		try {

			trpdd_build_pdf($content, array('filepath' => $temp_pdf));
		} catch (Exception $e) {
			echo "Error while export PDF file.. " . $e->getMessage();
			exit;
		}
	}


	$addingpos = '';
	if (isset($_REQUEST['pos_results'])) {
		$addingpos = 'pos_';
	}

	if (empty($outfile)) {
		$outfile = dirname($pdf_path) . '/build_' . $addingpos . basename($pdf_path);
	}

	$pdf_files = array();
	if (!empty($temp_pdf)) {
		$pdf_files[] = $temp_pdf;
	}


	if ($report['add_style']) {


		if (isset($report['patient_guide']) && !empty($report['patient_guide'])) {
			$result_dir     = tr_get_result_upload_dir();
			$guide_pdf = $result_dir['path'] . '/' . $report['patient_guide'];

			if (file_exists($guide_pdf)) {
				$pdf_files[] = $guide_pdf;
			}
		}

		$has_style 		= false;
		$count_errors 	= 0;
		$file_html = 'results_' . $btype . '_' . $report['add_style'] . '.php';
		if ((isset($report['content2']) && !empty($report['content2']))) {
			$has_style = true;
			$content = $report['content2'];
			if (isset($_GET['dbtime'])) {
				echo 'ABX from history<br>';
			}
		} else if (file_exists(TRCPDD_PATH . 'reports/' . $file_html)) {

			$use_cache = true;
			$cache = trpdd_get_html_result_cache($report, $btype . '_' . $report['add_style']);

			ob_start();
			include(TRCPDD_PATH . 'reports/' . $file_html);
			$content = ob_get_clean();
			if (isset($_GET['dbtime'])) {
				echo 'ABX version: ' . $abx_version . '<br>';
			}

			if (!$cache) {
				if ($has_style && $count_errors == 0) {
					trpdd_put_html_result_cache($report,  $btype . '_' . $report['add_style'], $content);
				} else if ($count_errors > 0) {
					//echo 'Error. Please try again (' . $count_errors . ')!';
					//exit;
				}
			} else if ($use_cache) {
				if (isset($_GET['onlyabxcache'])) {
					echo $cache;
					exit;
				}
				if (isset($_GET['dbtime'])) {
					global $ci_file_cache_path;
					$offset_seconds = get_option('gmt_offset') * HOUR_IN_SECONDS;
					echo 'Abx cache datetime: ' . date('Y-m-d H:i:s', filemtime($ci_file_cache_path) + $offset_seconds) . '<br>';
				}
				//check cache correct
				$passcache = true;

				if (strpos($cache, 'riskbg') === false) {
					$passcache = false;
				}

				if (stripos($cache, 'pathogencity') !== false && stripos($cache, 'rowbg_0') === false) {
					$passcache = false;
				}


				if ($passcache) {
					$content = $cache;
					$hide_patient_info  = isset($_REQUEST['hidepatient']);
					if ($hide_patient_info) {
						$passcache = false;
					}
				}

				$has_style = true;
			}
		}

		if ($has_style) {
			if (defined('TRCDPDF_NEW') && TRCDPDF_NEW) {
				if (strpos($content, 'class="dompdf_new"') === false) {
					$content = str_replace('0.5cm 0.5cm 2.1cm 0.5cm', '0.5cm 0.5cm 0.3cm 0.5cm', $content);
					$content = str_replace('0.5cm 0.5cm 3.5cm 0.5cm', '0.5cm 0.5cm 0.3cm 0.5cm', $content);
					$content = str_replace('margin-bottom: 0cm', 'margin-bottom: 3.5cm', $content);
					$content = str_replace('<html>', '<html class="dompdf_new">', $content);
				}
			}

			$temp_pdf2 = tempnam(WP_CONTENT_DIR . '/tmp', "exportpdfstyle");
			try {
				trpdd_build_pdf($content, array('filepath' => $temp_pdf2));
				$pdf_files[] = $temp_pdf2;
			} catch (Exception $e) {
				echo "Error while export PDF file3. " . $e->getMessage();
				exit;
			}
		}
	}


	if (!empty($pdf_path)) {
		$pdf_files[] = $pdf_path;
	}

	$has_error_build_pdf = false;
	try {

		global $ci_pdf_added_pagination;

		require_once(TRCPDD_PATH . 'inc/fpdf/fpdf.php');
		require_once(TRCPDD_PATH . 'inc/FPDI-2.6.0/src/autoload.php');


		$pdf = new \setasign\Fpdi\Fpdi();
		$pdf->SetFont('Helvetica');
		$pdf->SetFontSize(6);
		$pdf->SetTextColor(0, 0, 0);
		if (!$ci_pdf_added_pagination) {
			$pdf->add_page_footer = true;
		}



		foreach ($pdf_files as $pdf_file) {
			if (isset($_GET['debugf'])) {
				echo '<br>' . $pdf_file . '<br>';
			}
			$pageCount = $pdf->setSourceFile($pdf_file);
			if (isset($_GET['debugf'])) {
				echo '<br>' . $pageCount . '<br>';
			}
			// iterate through all pages
			for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
				// import a page

				$templateId = $pdf->importPage($pageNo, setasign\Fpdi\PdfReader\PageBoundaries::CROP_BOX, true, true);
				// get the size of the imported page
				$s = $pdf->getTemplateSize($templateId);

				$pdf->AddPage($s['orientation'], $s);
				// use the imported page
				$pdf->useTemplate($templateId);
			}
		}

		$pdf->Output('F', $outfile);


		@unlink($temp_pdf);
		if (isset($temp_pdf2) && !empty($temp_pdf2)) {
			@unlink($temp_pdf2);
		}
	} catch (Exception $e) {

		$has_error_build_pdf = true;

		if (file_exists($outfile)) {
			unlink($outfile);
		}
		if (!isset($temp_pdf2)) {
			$temp_pdf2 = '';
		}
		$exc = "gs -dNOPAUSE -sDEVICE=pdfwrite -sOUTPUTFILE={$outfile} -dBATCH {$temp_pdf} {$temp_pdf2} {$pdf_path}";

		try {
			exec($exc, $out, $result);

			if (file_exists($outfile)) {
				unlink($temp_pdf);
				if (isset($temp_pdf2) && !empty($temp_pdf2)) {
					@unlink($temp_pdf2);
				}
				return $outfile;
			}
		} catch (Exception $e) {
			var_dump($e);
			exit;
		}
		copy($temp_pdf, $outfile);
		@unlink($temp_pdf);
		if (isset($temp_pdf2) && !empty($temp_pdf2)) {
			@unlink($temp_pdf2);
		}
	}



	return $outfile;
}


function trpdd_pdfVersion($filename)
{
	$fp = @fopen($filename, 'rb');

	if (!$fp) {
		return 0;
	}

	/* Reset file pointer to the start */
	fseek($fp, 0);

	/* Read 20 bytes from the start of the PDF */
	preg_match('/\d\.\d/', fread($fp, 20), $match);

	fclose($fp);

	if (isset($match[0])) {
		return $match[0];
	} else {
		return 0;
	}
}


function trpdd_get_data($filepath, $ext = null, $required_columns = null, $map2column = false, $header_num = false, $trim_header = false, $calcu = true)
{
	//trpdd_get_string_exist()
	if ($header_num) {
		$map2column = false;
	}
	$header = array();
	$data = array();
	$missing = array();
	$found_columns = array();
	if ($ext == null) {
		$info = pathinfo($filepath);
		$ext = isset($info['extension']) ? $info['extension'] : 'csv';
	}
	$ftype = 'csv';
	try {
		if ($ext == 'csv') {


			$tab = "\t";

			$f = fopen($filepath, 'r');
			$datafile           = fgets($f);
			fclose($f);

			$count_semicolon    = substr_count($datafile, ';');
			$count_comma        = substr_count($datafile, ',');
			$datafile           = '';
			if ($count_semicolon > $count_comma) {
				$tab = ';';
			}

			$f = fopen($filepath, 'r');
			$rownodata = 0;
			while ($rowdt = fgetcsv($f, null, $tab)) {
				if (count($rowdt) <= 1 && $tab == "\t") {
					$tab = ',';
					fseek($f, 0);
					continue;
				}

				if ($header_num) {
					$data[] = $rowdt;
				} else if (count($header) == 0) {
					$header = $rowdt;

					foreach ($header as $hi => $cl) {
						$cl = trim($cl);
						$cl = strtolower($cl);
						if ($trim_header) {
							$cl = str_replace(array(' ', '(', ')', '.'), '', $cl);
						}
						$header[$hi] = $cl;
					}


					if (trim(implode('', $header)) == '') {
						$header = array();
					} else if (!empty($required_columns)) {
						$missing = array();
						foreach ($required_columns as $clk => $rf) {
							if (is_array($rf)) {
								$rf_key = implode('', $rf);
								foreach ($rf as $rf2) {
									$rf2 = strtolower($rf2);

									$hpos = array_search($rf2, $header);

									if ($hpos === false) {
										$missing[$rf_key] = $rf[0];
									} else {
										$found_columns[$rf_key] = $rf2;
										unset($missing[$rf_key]);

										if (is_string($clk) && $map2column) {
											$header[$hpos] = $clk;
										}
										break;
									}
								}
							} else {
								$rf = strtolower($rf);
								if (!in_array($rf, $header)) {
									$missing[$rf] = $rf;
								} else {
									$found_columns[$rf] = $rf;
								}
							}
						}

						foreach ($found_columns as $fckey => $fc) {
							unset($missing[$fckey]);
						}

						if (!empty($missing)) {
							$header = array();
						}
					}
				} else if ($map2column) {
					$new_row = array();
					$has_data = false;
					foreach ($header as $pos => $h) {
						if (!empty($rowdt[$pos])) {
							$has_data = true;
						}
						if (!isset($new_row[$h])) {
							$new_row[$h] = $rowdt[$pos];
						} else {
							$new_row[$h . '_2'] = $rowdt[$pos];
						}
					}
					if (!$has_data) {
						$rownodata++;
						if ($rownodata > 5) {
							break;
						} else {
							continue;
						}
					}
					$data[] = $new_row;
				} else {
					if (empty($rowdt[0]) && empty($rowdt[1]) && empty($rowdt[2]) && empty($rowdt[3])) {

						$rownodata++;
						if ($rownodata > 5) {
							break;
						} else {
							continue;
						}
					}
					$data[] = $rowdt;
				}
			}
			fclose($f);
		} else {
			require_once TRCPDD_PATH . 'inc/PHPExcel/IOFactory.php';

			$ftype = 'excel';
			$filetype = PHPExcel_IOFactory::identify($filepath);

			$objReader = PHPExcel_IOFactory::createReader($filetype);

			if (stripos($filepath, '.csv') > 0) {
				//check semicolon
				$datafile           = file_get_contents($filepath);
				$count_semicolon    = substr_count($datafile, ';');
				$count_comma        = substr_count($datafile, ',');
				$datafile           = '';

				if ($count_semicolon > $count_comma) {
					$objReader->setDelimiter(';');
				}
			}

			$objPHPExcel = $objReader->load($filepath);

			$sheet 			= $objPHPExcel->getSheet(0);
			$highestRow 	= $sheet->getHighestRow();
			$highestColumn 	= $sheet->getHighestColumn();
			$highestcolnum = PHPExcel_Cell::columnIndexFromString($highestColumn) - 1;
			$has_error_get 	= false;

			for ($row = 1; $row <= $highestRow; $row++) {
				$rowdt = array();

				if ($has_error_get) {
					$rowdt = array();
					for ($col1 = 0; $col1 <= $highestcolnum; $col1++) {
						$vl = '';
						try {
							$colkey = PHPExcel_Cell::stringFromColumnIndex($col1);
							$vl = $sheet->rangeToArray($colkey . $row . ':' . $colkey . $row, NULL, TRUE, FALSE);
							$vl = $vl[0][0];
						} catch (Exception $e2) {
						}
						$rowdt[] = $vl;
					}
				} else {
					try {
						$rowdt = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, NULL, $calcu, FALSE);
						$rowdt = $rowdt[0];
					} catch (Exception $e) {
						$has_error_get = true;
						$row--;
						continue;
					}
				}



				if (count($rowdt) == 1 && is_array($rowdt[0]) && count($rowdt[0]) > 1) {
					$rowdt = $rowdt[0];
				}


				if (count($header) == 0 && !$header_num) {
					$header = $rowdt;
					foreach ($header as $hi => $cl) {
						$cl = trim($cl);
						$cl = strtolower($cl);
						if ($trim_header) {
							$cl = str_replace(array(' ', '(', ')', '.'), '', $cl);
						}
						$header[$hi] = $cl;
					}

					if (trim(implode('', $header)) == '') {
						$header = array();
					} else if (!empty($required_columns)) {
						$missing = array();
						foreach ($required_columns as $rf) {
							if (is_array($rf)) {
								$rf_key = implode('', $rf);
								foreach ($rf as $rf2) {
									$rf2 = strtolower($rf2);
									$hpos = array_search($rf2, $header);

									if ($hpos === false) {
										$missing[$rf_key] = $rf[0];
									} else {
										$found_columns[$rf_key] = $rf2;
										unset($missing[$rf_key]);

										if (is_string($clk) && $map2column) {
											$header[$hpos] = $clk;
										}
										break;
									}
								}
							} else {
								$rf = strtolower($rf);
								if (!in_array($rf, $header)) {
									$missing[$rf] = $rf;
								} else {
									$found_columns[$rf] = $rf;
								}
							}
						}
						foreach ($found_columns as $fckey => $fc) {
							unset($missing[$fckey]);
						}

						if (!empty($missing)) {
							$header = array();
						}
					}
				} else {

					if ($row > 5 && empty($rowdt[0]) && empty($rowdt[1])) {
						continue;
					} else if ($map2column) {
						$new_row = array();
						foreach ($header as $pos => $h) {
							$new_row[$h] = isset($rowdt[$pos]) ? $rowdt[$pos] : '';
						}
						$data[] = $new_row;
					} else {
						$data[] = $rowdt;
					}
				}
			}
		}
	} catch (Error $e) {
	}
	return array('header' => $header, 'data' => $data, 'ftype' => $ftype, 'missing' => $missing, 'found' => $found_columns);
}


function tr_pdd_get_req_file($btype, $is_short = false)
{
	if (empty($btype)) {
		$btype = 'tox';
	}

	//search file in parts folder first
	$path = TRCPDD_PATH . 'parts/' . $btype . '/req_form.php';
	if ($is_short) {
		$pathshort = TRCPDD_PATH . 'parts/' . $btype . '/short_form.php';
		if (file_exists($pathshort)) {
			$path = $pathshort;
		} else {
			$formshort_file = 'admin_form_short_' . $btype . '.php';
			if ($btype == 'tox') {
				$formshort_file = 'admin_form_short.php';
			}
			if (file_exists(TRCPDD_PATH . 'pages/' . $formshort_file)) {
				$path = TRCPDD_PATH . 'pages/' . $formshort_file;
			}
		}
	}
	if (!file_exists($path)) {
		$path = TRCPDD_PATH . 'pages/admin_form_' . $btype . '.php';
	}

	if (isset($_GET['debug_path'])) {
		echo ($path);
		echo '<br><br>';
	}
	return $path;
}

function trpdd_list_report_styles($btype)
{
	$dir = TRCPDD_PATH . 'reports';
	$arr = array();
	for ($i = 1; $i <= 10; $i++) {
		$filepath = $dir . '/results_' . $btype . '_' . $i . '.php';
		if ($i == 1) {
			$filepath = $dir . '/results_' . $btype . '.php';
		}

		if ($i > 1 && !file_exists($filepath)) {
			break;
		}
		$name = 'Style #' . $i;

		preg_match("/Name: ([^\n]+)\n/", file_get_contents($filepath), $matches);
		if (isset($matches[1])) {
			$mname =  trim($matches[1]);
			if (!empty($mname)) {
				$name .= ': ' . $mname;
			}
		}

		$arr[$i] = $name;
	}
	return $arr;
}


function trpdd_list_plate_styles($btype)
{
	$user 		= wp_get_current_user();
	$isadmin 	= $user->user_login == 'Administrat0r';

	$dir = TRCPDD_PATH . 'plate_maps';
	$files = scandir($dir);
	$arr = array();
	if ($btype == 'pcr') {
		$arr[1] =  'UTI/WOUND';
		$arr[2] =  'Covid-19';
	}

	foreach ($files as $file) {
		if (strpos($file, '.txt') === false && strpos($file, '.json') === false) {
			continue;
		}
		$num = str_replace(array('.txt', '.json'), '', $file);
		if (stripos($file, '.json') > 0) {
			$arr2 = json_decode(file_get_contents($dir . '/' . $file), true);
			$name = $arr2['name'];
			if ($isadmin) {
				$name .= ' ' . $arr2['lab'];
			}
		} else {
			$name = file_get_contents($dir . '/' . $file);
		}

		if ($btype == 'pcr' && stripos($name, 'blood') !== false) {
			continue;
		} else if ($btype == 'blood' && stripos($name, 'blood') === false) {
			continue;
		}

		$arr[$num] = $name . ' #' . $num;
	}
	ksort($arr);

	return $arr;
}

function trpdd_get_file_path($module, $slug)
{
	$filepaths = array(
		TRCPDD_PATH . 'parts/' . $module . '/' . $slug . '.php',
		TRCPDD_PATH . 'pages/admin_ci_' . $slug . '.php',
		TRCPDD_PATH . 'pages/admin_' . $slug . '.php',
		TRCPDD_PATH . 'pages/admin_form_' . $slug . '.php',
		TRCPDD_PATH . 'pages/admin_' . $slug . '_' . $module . '.php',
	);

	$path = '';
	foreach ($filepaths as $path) {
		if (file_exists($path)) {
			break;
		}
	}
	if (isset($_GET['debug_path'])) {
		echo ($path);
		echo '<br><br>';
	}

	return $path;
}

function trpdd_include_module_file($module, $slug)
{
	$filepath = trpdd_get_file_path($module, $slug);
	if (file_exists($filepath)) {
		include($filepath);
	}
}

function trpdd_build_pdf($content, $args = array())
{
	global $is_ci_admin;
	$admin_view         	= isset($_REQUEST['view']) || isset($_COOKIE['trquery_monitor']);
	if (!isset($args['size']) || empty($args['size'])) {
		$args['size'] = 'letter';
	}
	if (!isset($args['orientation']) || empty($args['orientation'])) {
		$args['orientation'] = 'portrait'; //landscape
	}
	if (!isset($args['download'])) {
		$args['download'] = 1;
	}

	$content = str_replace('[ABSPATH]', ABSPATH, $content);

	if (isset($_GET['html'])) {
		echo $content;
		exit;
	}
	if ($admin_view && !isset($args['alwaysdownload'])) {
		$args['download'] = 0;
	}

	if (!function_exists('trdomepdf_build_pdf')) {
		$plugin = 'ci-dompdf/plug.php';
		if (file_exists(WP_CONTENT_DIR . '/plugins/' . $plugin)) {
			if (!function_exists('activate_plugin') || !activate_plugin($plugin)) {
				include_once(WP_CONTENT_DIR . '/plugins/' . $plugin);
			}
		} else {
			function trdomepdf_build_pdf() {}
			echo 'Plugin Dompdf not exist!';
			exit;
		}
	}

	return trdomepdf_build_pdf($content, $args);
}

function tr_pdd_header($content_type, $filename = '')
{
	if (defined('DO_EMAIL_SCHEDULE_REPORTS')) {
		return;
	}
	if (isset($_GET['view']) && !isset($_SERVER['HTTP_REFERER'])) {
		ob_start(function ($buffer) {
			$buffer = '<table><tr><td>' . $buffer;
			$buffer = str_replace(',', '</td><td>', $buffer);
			$buffer = str_replace("\n", '</td></tr><tr><td>', $buffer);
			$buffer .= '</td></tr></table>';
			return $buffer;
		});
		return;
	}
	if ($content_type == 'csv') $content_type = 'application/csv';
	header('content-type:' . $content_type);
	header('Content-disposition: attachment; filename=' . $filename);
}


function trpdd_export_csv($data, $filename = 'export.csv', $headers = array())
{
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename=' . $filename);
	$output = fopen('php://output', 'w');
	if (!empty($headers)) {
		fputcsv($output, $headers);
	}
	foreach ($data as $row) {
		fputcsv($output, $row);
	}
	fclose($output);
	exit;
}

function trpdd_get_resized_image($imagepath, $width, $height)
{
	if (empty($imagepath) || empty($width) || empty($height)) {
		return $imagepath;
	}
	$folder = dirname($imagepath);
	$filename = basename($imagepath);
	$newfilename = $width . '_' . $height . '_' . $filename;
	$image_cache = $folder . '/' . $newfilename;
	if (file_exists($image_cache) && filemtime($image_cache) > filemtime($imagepath)) {
		return $image_cache;
	}

	$image = wp_get_image_editor($imagepath);
	if (!is_wp_error($image)) {
		$image->resize($width, $height, false);
		$image->save($image_cache);
	}
	return $imagepath;
}

function trpdd_get_html_result_cache($report, $pdf_type)
{

	$dir 		= WP_CONTENT_DIR . '/results/html/';
	$assenlower = strtolower($report['assen']);
	$pdf_type 	= strtolower($pdf_type);
	$filename 	= $assenlower . '_' . $pdf_type . '.htm';
	$cachefp 	= $dir . $filename;
	$cachetime 	= file_exists($cachefp) ? filemtime($cachefp) : 0;


	$offset  = ((float) get_option('gmt_offset')) * 3600;
	$approve_time = strtotime($report['approve_date']) - $offset; //to UTC
	$modified_time = $report['modified']; //is UTC
	$has_cache = $cachetime > 0 && $cachetime > $approve_time && $cachetime > $modified_time;

	if (isset($_GET['nocacheabx']) || isset($_GET['shownewabx'])) {
		return '';
	}

	if (isset($_GET['clearcacheabx'])) {
		@unlink($cachefp);
		return '';
	}

	if (isset($report['estatus']) && $report['estatus'] != 'C') {
		return '';
	}

	if (tr_get_option('ci_pdf_data', 'new_abx_dcache')) {
		return '';
	}

	if (isset($_GET['dbtime'])) {

		echo '<div>HTML cache file: ' . $filename . ' => ' . ($has_cache ? ' has cache' : '') . '</div>';
		echo '<div>HTML cache time: ' . date('Y-m-d H:i:s', $cachetime + $offset) . '</div>';
		echo '<div>HTML cache size: ' . filesize($cachefp) . '</div>';
		echo '<div>HTML approve time: ' . date('Y-m-d H:i:s', strtotime($report['approve_date'])) . '</div>';
	}
	if ($has_cache) {
		global $ci_file_cache_path;
		$ci_file_cache_path = $cachefp;
		return file_get_contents($cachefp);
	}

	return '';
}

function trpdd_put_html_result_cache($report, $pdf_type, $content)
{
	$dir 		= WP_CONTENT_DIR . '/results/html/';
	if (!file_exists($dir)) {
		wp_mkdir_p($dir);
	}
	$filename 	= strtolower($report['assen'] . '_' . $pdf_type) . '.htm';
	$cachefp 	= $dir . $filename;

	if (isset($_GET['nocacheabx']) || isset($_GET['shownewabx'])) {
		return '';
	}
	if (tr_get_option('ci_pdf_data', 'new_abx_dcache')) {
		return '';
	}

	$content = str_replace(ABSPATH, '[ABSPATH]', $content);

	$rs = file_put_contents($cachefp, $content);
	if (isset($_GET['dbtime'])) {
		echo '<div>saved cache file: ' . $cachefp . ' => ' . ($rs ? 'ok' : 'fail') . '</div>';
	}
	return $rs;
}

function trpdd_trigger_approve_report($report, $btype)
{
	global $wpdb;
	if ($report['estatus'] != 'C') {
		$report['estatus_old'] = $report['estatus'];
		$report['estatus'] = 'C';
	}


	$wpdb->insert('ci_approve_logs', array(
		'report_id' => $report['id'],
		'btype' => $btype,
		'final_status' => $report['final_results'],
		'created_at' => date_i18n('Y-m-d H:i:s'),
		'created_by' => get_current_user_id(),
	));

	trpdd_build_history_pdf($report, $btype);

	//check apis ids
	$api_ids = array(-2, -18, -50, -48);


	$api_ids_str = implode(',', $api_ids);

	//update apis to send again : -1 issue before, 1:sent, 2:resend
	$wpdb->query("update ci_sync_api_orders 
	set mo_status = 2
	where mo_status in (-1, 1)
	and mo_mid = {$report['mid']}
	and mo_btype = '{$btype}'
	and (mo_file_id in ({$api_ids_str}) or mo_file_id>0)");

	$wpdb->query("update ci_sync_api_orders 
	set mo_sent = 2
	where mo_sent in (-1, 1)
	and mo_mid = {$report['mid']}
	and mo_btype = '{$btype}'
	and ( mo_file_id in ({$api_ids_str}) or mo_file_id>0 )
	");

	//resend aegis api
	if (tr_get_option('ci_api', 'api_aegis') && intval($report['final_results']) !== 1) {
		$wpdb->query("update ci_sync_api_orders 
		set mo_sent = 2
		where mo_sent in (-1, 1)
		and mo_mid = {$report['mid']}
		and mo_btype = '{$btype}'
		and  mo_file_id in (-61)
		");
	}


	//remove null results
	$result_tbl = tr_pdd_get_table_result_by_btype($btype);
	if ($report['id'] > 0) {
		$wpdb->query("delete from {$result_tbl}
		where report_id = '{$report['id']}'
		and conc is null and `pos-neg` is null and instrument is null");
	}

	//sync result again for wsdl
	$wsdl_ids = $wpdb->get_col("select wsdl_ID from ci_wsdl_keys where (wsdl_url!='' and wsdl_url is not null) and wsdl_type = 'result_{$btype}' ");

	if (count($wsdl_ids) > 0) {
		$wsdl_ids_str = implode(',', $wsdl_ids);
		$wpdb->query("update ci_wsdl_batch 
		set b_status = 2
		where b_status = 1
		and b_mid = {$report['mid']}
		and btype = '{$btype}' 
		and b_wsdl_ID in ({$wsdl_ids_str})");
	}


	do_action('ci_approve_rs', $report, $btype);
}

function trpdd_build_history_pdf($report, $btype)
{
	global $wpdb;
	$batch_tbl 	= tr_pdd_get_table_by_btype($btype);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);
	if (!isset($report['assen']) || empty($report['assen'])) {
		$report['assen'] = $report['patient_id'];
	}
	if (!isset($report['final_results']) || !isset($report['id']) || empty($report['id'])) {
		$reportdb 			= $wpdb->get_row("select id, final_results from {$report_tbl} where patient_id = '{$report['assen']}' ");
		$report['final_results'] = $reportdb->final_results;
		$report['id'] 		= $reportdb->id;
	}
	if (isset($report['has_pos']) && is_null($report['has_pos'])) {
		ob_start();
		$_GET['id'] = $id = $report['id'];
		include(TRCPDD_PATH . "parts/{$btype}/results_edit.php");
		$content = ob_get_clean();
	}


	if ($report['final_results'] > 0) {

		$dir 		= WP_CONTENT_DIR . '/results/html/' . strtolower($report['assen']);
		if (!file_exists($dir)) {
			wp_mkdir_p($dir);
		}
		if (!isset($report['approve_date']) || empty($report['approve_date']) || $report['approve_date'] == '0000-00-00 00:00:00') {
			$report['approve_date'] = date_i18n('Y-m-d H:i:s');
		}
		$datetime = date('Y-m-d_H_i_s', strtotime($report['approve_date']));
		$filepath = $dir . '/' . $datetime . '.html';
		$content = trpdd_export_results_pdf($report['id'], 'html', false, $btype);
		if ($content) {
			return trpdd_put_content_pdf_html($filepath, $content);
		} else {
			//add build to cron
			if (!isset($report['mid']) || empty($report['mid'])) {
				$report['mid'] = $wpdb->get_var("select mid from {$batch_tbl} where assen = '{$report['assen']}' ");
			}
			trpdd_update_batch_meta($report['mid'], $btype, '_need_build_history',  date_i18n('Y-m-d H:i:s'));
		}
	}
	return false;
}


function trpdd_list_report_html_files($report)
{
	$return 	= array();
	if (!isset($report['assen']) || empty($report['assen'])) {
		$report['assen'] = $report['patient_id'];
	}
	$assen 		= strtolower($report['assen']);
	$dir 		= WP_CONTENT_DIR . '/results/html/' . strtolower($report['assen']);
	if (file_exists($dir)) {
		$files 		=  scandir($dir, SCANDIR_SORT_DESCENDING);

		foreach ($files as $f) {
			if (in_array($f, array('.', '..'))) continue;
			$filename = str_replace('.html', '', $f);
			$filetime = filemtime($dir . '/' . $f);
			$return[] = array($assen, $filename, $filetime);
		}
		if (count($return) > 0) {
			usort($return, function ($a, $b) {
				return $b[2] - $a[2];
			});
		}
	}

	return $return;
}
function trpdd_get_last_result_history_html($report)
{
	$files = trpdd_list_report_html_files($report);
	if (count($files) > 0) {
		$lastfile = $files[0];
		$dir 		= WP_CONTENT_DIR . '/results/html/' . strtolower($report['assen']);
		$filepath 	= $dir . '/' . $lastfile[1] . '.html';
		if (file_exists($filepath)) {
			global $ci_history_file_cache_path;
			$ci_history_file_cache_path = $filepath;
			return file_get_contents($filepath);
		}
	}
	return '';
}

function trpdd_put_content_pdf_html($filepath, $content)
{
	$content = str_replace(ABSPATH, '[ABSPATH]', $content);
	return file_put_contents($filepath, $content);
}
//end file
