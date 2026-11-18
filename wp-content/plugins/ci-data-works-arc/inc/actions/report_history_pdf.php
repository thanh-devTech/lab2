<?php
global $wpdb;

$d = $_REQUEST['d'];
$f = $_REQUEST['f'];
$reid = isset($_GET['reid']) && $_GET['reid'] > 0 ? $_GET['reid'] : 0;
$btype = isset($_GET['btype']) ? $_GET['btype'] : '';
$isdebug = isset($_GET['debug']);

$dir 		= WP_CONTENT_DIR . '/results/html/' . $d;

$filepath = $dir . '/' . $f . '.html';


$report     = tr_pdd_get_report_by_id($reid, $btype);

if (file_exists($filepath)) {
	$outfile = $d . '_' . $f . '.pdf';

	$content = file_get_contents($filepath);

	if (isset($_GET['rebuild']) && $reid > 0 && !empty($btype)) {

		$content = trpdd_export_results_pdf($reid, 'html', false, $btype);
		if ($isdebug) {
			var_dump($content);
			exit;
		}
		if (!empty($content)) {
			trpdd_put_content_pdf_html($filepath, $content);
		}
	}



	if (isset($_GET['html'])) {
		var_dump($content);
		exit;
	} else if (isset($_GET['delete'])) {
		unlink($filepath);
		echo 'deleted file';
		exit;
	}
	if (!isset($_GET['view'])) {
		header('Content-Disposition: ; filename="' . basename($outfile) . '"');
	} else {
		header('Content-Disposition: ; filename="' . basename($outfile) . '"');
	}
	if (!isset($_GET['debug'])) {
		header("Content-type:application/pdf");
	}

	try {

		$arr1 = explode('<!--split_page-->', $content);
		$page1content = $arr1[0];
		$page2content = isset($arr1[1]) ? $arr1[1] : '';

		$pdf_files = array();
		$temp_pdf1 = tempnam(WP_CONTENT_DIR . '/tmp', "exportpdf1");

		trpdd_build_pdf($page1content, array('filepath' => $temp_pdf1));
		$pdf_files[] = $temp_pdf1;
		if (!empty($page2content)) {

			if (isset($_GET['clearcacheabx']) && stripos($page2content, 'abx') !== false) {
				$id = $reid;
				ob_start();
				require(TRCPDD_PATH . 'reports/abx.php');
				$page2content_tmp = ob_get_clean();
				if ($has_report) {
					$page2content = $page2content_tmp;

					$content = $page1content . '<!--split_page-->' . $page2content;

					file_put_contents($filepath, $content);
				}
			}
			$temp_pdf2 = tempnam(WP_CONTENT_DIR . '/tmp', "exportpdf2");
			trpdd_build_pdf($page2content, array('filepath' => $temp_pdf2));
			$pdf_files[] = $temp_pdf2;
		}

		if (!empty($report['result_pdf'])) {
			$rs_pdfs = explode(',', $report['result_pdf']);
			$pdf_upload_files = array();
			foreach ($rs_pdfs as $fpdf) {
				if (empty($fpdf)) continue;

				$pdf_path = tr_get_result_upload_dir('path') . '/' . $fpdf;

				if (file_exists($pdf_path)) {
					if (stripos($fpdf, '_mako.') > 0) {
						$only_pdf = true;
					}

					$pdf_upload_files[] =  $pdf_path;
				}
			}
			if (count($pdf_upload_files) > 0) {
				if (strpos($page1content, 'No Custom Profile/No Tests Requested Otherwise') !== false) {
					$only_pdf = true;
				}
				if ($only_pdf) {
					$pdf_files = $pdf_upload_files;
					unlink($temp_pdf1);
					if (isset($temp_pdf2)) {
						unlink($temp_pdf2);
					}
				} else {
					foreach ($pdf_upload_files as $pdf_upload_file) {
						$pdf_files[] = $pdf_upload_file;
					}
				}
			}
		}



		if (count($pdf_files) > 1) {
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
				$pageCount = $pdf->setSourceFile($pdf_file);

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


			$pdf->Output('F', $temp_pdf1);
			readfile($temp_pdf1);
			unlink($temp_pdf1);
			if (isset($temp_pdf2)) {
				unlink($temp_pdf2);
			}
		} else {
			readfile($pdf_files[0]);
		}
		foreach ($pdf_files as $file) {
			if (stripos($file, 'exportpdf') !== false) {
				unlink($file);
			}
		}
	} catch (Exception $e) {
		Tr_Session_Class::add_error("Error while export PDF file2. " . $e->getMessage());
		return false;
	}
}
