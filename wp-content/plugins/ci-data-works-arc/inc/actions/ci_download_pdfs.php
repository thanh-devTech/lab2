<?php
global $wpdb;
$export_type = 'zip'; //pdf

$ids = explode(',', trim($_REQUEST['ids']));
$btype = !empty($_REQUEST['btype']) ? $_REQUEST['btype'] : 'tox';
$batch_id = isset($_REQUEST['bid']) ? $_REQUEST['bid'] : '';
$is_downloaded = isset($_REQUEST['downloaded']) ? $_REQUEST['downloaded'] : '';
$pdfs = array();

if (!class_exists('ZipArchive')) {
	$export_type = 'pdf';
}
@ob_get_clean();

if ($export_type == 'pdf') {
	require_once(TRCPDD_PATH . 'inc/fpdf/fpdf.php');
	require_once(TRCPDD_PATH . 'inc/FPDI-2.6.0/src/autoload.php');


	$temp_pdf = tempnam(WP_CONTENT_DIR . '/tmp', "exportpdf");
	$pdf = new \setasign\Fpdi\Fpdi();
	$pdf->SetFont('Helvetica');
	$pdf->SetFontSize(6);
	$pdf->SetTextColor(0, 0, 0);

	$width = 0;
	$height = 0;
	$pagenow = 1;

	$count_files = 0;
	$error_files = array();



	foreach ($ids as $id) {
		$report = tr_pdd_get_report_by_id($id, $btype);

		if (empty($batch_id)) {
			$batch_id = $report['batch_id'];
		}

		if ($report['estatus'] != 'C') continue;

		$outfile = trpdd_export_results_pdf($id, '', true, $btype);
		if (!$outfile) {
			continue;
		}

		$count_files++;
		try {

			//add pdf file
			$pageCount = $pdf->setSourceFile($outfile);
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
		} catch (Exception $e) {

			$error_files[] = $outfile;
		}
	}

	$pdf->Output('F', $temp_pdf);

	if (count($error_files) > 0) {
		foreach ($error_files as $f) {
			$exc = "gs -dNOPAUSE -sDEVICE=pdfwrite -sOUTPUTFILE={$temp_pdf} -dBATCH {$temp_pdf} {$f}";

			try {
				exec($exc, $out, $result);
			} catch (Exception $e) {
				//var_dump($e);
			}
		}
	}

	$downloadfile = 'batch_' . $batch_id . '.pdf';
	header('Content-Type: application/pdf');
	if (!isset($_GET['view'])) {
		header('Content-Disposition: attachment; filename="' . basename($downloadfile) . '"');
	}
	readfile($temp_pdf);
	unlink($temp_pdf);
} else {

	$temp_zip = tempnam(WP_CONTENT_DIR . '/tmp', "exportzip");
	//check file writeable $temp_zip
	if (!is_writable($temp_zip)) {
		$temp_zip = tempnam(sys_get_temp_dir(), "exportzip");
	}


	$zip = new ZipArchive;
	if ($zip->open($temp_zip, ZipArchive::CREATE) === TRUE) {

		$has_file = false;
		foreach ($ids as $id) {
			if (empty($id)) continue;
			if ($is_downloaded) {
				$table_report = tr_pdd_get_table_report_by_btype($btype);
				$user_id = get_current_user_id();
				$users = $wpdb->get_var("select users_printed from {$table_report} where id = {$id} ");
				$users = explode(',', $users);
				if (!in_array($user_id, $users)) {
					$users[] = $user_id;
				}
				$users = implode(',', $users);
				$wpdb->update($table_report, array('users_printed' => $users), array('id' => $id));
			}
			$report = tr_pdd_get_report_by_id($id, $btype);

			if (empty($batch_id)) {
				$batch_id = $report['batch_id'];
			}

			if ($report['estatus'] != 'C') {
				continue;
			}

			$pdffile = trpdd_export_results_pdf($id, '', true, $btype);
			if (! $pdffile || !file_exists($pdffile)) {
				continue;
			}
			$foldername = 'batch_' . $batch_id;

			$zip->addFile($pdffile, $foldername . '/' . basename($pdffile));
			//break;
			$has_file = true;
		}

		$zip->close();

		if (!$has_file) {
			header('Content-Type: text/plain');
			echo 'No Results Approved for this collection date.';
			exit;
		}


		$outfile = 'batch_' . $batch_id . '.zip';

		header('Content-Length: ' . filesize($temp_zip));
		header('Content-Type: application/zip');
		header('Content-Disposition: attachment; filename="' . basename($outfile) . '"');
		readfile($temp_zip);

		unlink($temp_zip);
	}
}
$data_log = [
	'type' => $btype . ' Test Results',
	'file_name' => 'batch_' . $batch_id . '.' . ($export_type == 'pdf' ? 'pdf' : 'zip'),
	'total_records' => count($ids),
	'from_date' => $batch_id,
	'to_date' => $batch_id,
];
trpdd_log_user(get_current_user_id(), 'download_report', $data_log, '', '', '', '', 'Report_' . date_i18n('mdY'));

exit;
