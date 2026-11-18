<?php
global $wpdb;
global $report;

$is_demo_group = true;
$request_id = $_GET['id'] ?? null;
$groupid = intval($request_id);
$group = $wpdb->get_row($wpdb->prepare("SELECT * FROM ci_test_groups WHERE tg_ID = %d", $groupid));
if (!$group) {
	echo "Group not found.";
	exit;
}
$btype = $group->tg_type;
if ($btype == 'tox' || empty($btype)) {
	$html_file = TRCPDD_PATH . 'reports/results_tox.php';
} else {
	$html_file = TRCPDD_PATH . 'reports/results_' . $btype . '.php';
}
$pre_all_res = array();
$report = array();
$report['mid'] = '-1';
$report['assen'] = 'DeMo0001';
$report['id'] = '-1';
$report['doc'] = date_i18n('Y-m-d');
$report['created'] = date_i18n('Y-m-d');
$report['approve_date'] = date_i18n('Y-m-d');
$report['type_of_specimen'] = 'Nasopharyngeal Swab';
$report['sample_type'] = 'Nasopharyngeal Swab';
$report['physician'] = 'Dr. Demo';
$report['requesting_practice'] = 'Dr. Demo';
$report['patient_name'] = 'Patient Demo';
$report['pa_gender'] = 'F';
$report['pa_mrn'] = 'mrn0001';
$report['pa_ssn'] = 'ssn0001';
$report['pa_code'] = 'code0001';
$report['dob'] = '2020-01-01';
$report['pa_phone'] = '20200101';
$report['pa_address'] = '123 Demo St, Demo City, DC 12345';
$report['panel_tests_requested'] = 'GROUP_' . $groupid;
$id = '-1';

$tests = $wpdb->get_results("SELECT te.* FROM ci_test_panels te
	JOIN ci_test_groups tg ON te.te_group_id = tg.tg_ID
	 WHERE (tg_ID = {$groupid} or tg.tg_parent = {$groupid} )
	 AND te_in_req = 1", ARRAY_A);
foreach ($tests as $test) {
	$report['panel_tests_requested'] .= ',' . $test['te_ID'];

	$pre_all_res[$test['te_ID']] = $test;
	$pre_all_res[$test['te_ID']]['drug'] = strtoupper($test['te_name']);
	$pre_all_res[$test['te_ID']]['pos-neg'] = 'POS';
	$pre_all_res[$test['te_ID']]['conc'] = '22';
	$pre_all_res[$test['te_ID']]['cq'] = '22';
	$pre_all_res[$test['te_ID']]['amp_score'] = '22';
	$pre_all_res[$test['te_ID']]['id'] = -99;
	$pre_all_res[$test['te_ID']]['report_id'] = -1;
}

$lab = $wpdb->get_row("select * from ci_labs  order by lab_ID asc limit 1", ARRAY_A);
$clinic = $wpdb->get_row("select * from ci_clinic  order by cID asc limit 1", ARRAY_A);
$report = array_merge($report, $lab);
$report = array_merge($report, $clinic);

$report = CI_Base_Lab::apply_lab_field_history($report);

if (!empty($report['lab_results_logo'])) {
	$report['lab_logo'] = $report['lab_results_logo'];
}

$report['header_lab_address'] = trpdd_format_address($report['lab_address'], $report['lab_city'], $report['lab_state'], $report['lab_zip'], true);
$report['header_lab_name'] = $report['lab_name'];
$report['header_lab_phone'] = $report['lab_phone'];
$report['header_lab_clia'] = $report['lab_clia'];
$report['header_lab_director'] = $report['lab_director'];
$report['header_lab_second_line_hpdf'] = $report['lab_second_line_hpdf'];
$report['header_lab_fax'] = $report['lab_fax'];
$report['color'] = !empty($report['lab_color']) ? $report['lab_color'] : tr_get_option('ci_pdf_data', 'pdf_main_color');
if (empty($report['color']) || strlen($report['color']) < 3) {
	$report['color'] = '#059dab';
}

$report['requesting_practice'] = $report['cli_clinic'];
$report['cli_ID'] = isset($report['cID']) ? $report['cID'] : 0;

$original_request_id = isset($_REQUEST['id']) ? $_REQUEST['id'] : null;
$original_get_id = isset($_GET['id']) ? $_GET['id'] : null;
$_REQUEST['id'] = '-1';
$_GET['id'] = '-1';
ob_start();
if (file_exists($html_file)) {
	include($html_file);
} else {
	$has_report = false;
	if (isset($_GET['debug'])) {
		echo 'No file : ' . $html_file . '<br>';
	}
}
$content = ob_get_clean();

if (isset($_GET['debugrp'])) {
	var_dump($report);
	exit;
}

if (isset($report['add_style'])) {
	$file_style2 = 'results_' . $btype . '_' . $report['add_style'] . '.php';
	if ($html_file != $file_style2 && file_exists(TRCPDD_PATH . 'reports/' . $file_style2)) {

		ob_start();
		require(TRCPDD_PATH . 'reports/' . $file_style2);
		$contentabx = ob_get_clean();
		$has_style = isset($has_style) ? $has_style : false;
		if ($has_style && strlen($contentabx) > 300) {
			$content .= '<!--split_page-->' . $contentabx;
		}
	}
}
if ($original_request_id === null) {
	unset($_REQUEST['id']);
} else {
	$_REQUEST['id'] = $original_request_id;
}
if ($original_get_id === null) {
	unset($_GET['id']);
} else {
	$_GET['id'] = $original_get_id;
}

if (isset($_GET['debugabx'])) {
	echo $content;
	exit;
}
if (isset($_GET['html'])) {
	echo $content;
	exit;
}

$outfile = 'demo.pdf';

if (!isset($_GET['view'])) {
	header('Content-Disposition: ; filename="' . basename($outfile) . '"');
} else {
	header('Content-Disposition: ; filename="' . basename($outfile) . '"');
}
header("Content-type:application/pdf");

try {

	$arr1 = explode('<!--split_page-->', $content);
	$content = $arr1[0];
	$page2content = isset($arr1[1]) ? $arr1[1] : '';

	$pdf_files = array();
	$temp_pdf1 = tempnam(WP_CONTENT_DIR . '/tmp', "exportpdf1");

	trpdd_build_pdf($content, array('filepath' => $temp_pdf1));
	$pdf_files[] = $temp_pdf1;
	if (!empty($page2content)) {
		$temp_pdf2 = tempnam(WP_CONTENT_DIR . '/tmp', "exportpdf2");
		trpdd_build_pdf($page2content, array('filepath' => $temp_pdf2));
		$pdf_files[] = $temp_pdf2;
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
		unlink($file);
	}
} catch (Exception $e) {
	Tr_Session_Class::add_error("Error while export PDF file2. " . $e->getMessage());
	return false;
}
