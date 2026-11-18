<?php
global $wpdb;
global $ci_pdf_filename;
$ci_pdf_filename = '';

$key 		= isset($_GET['key']) ? $_GET['key'] : '';
$btype 		= isset($_GET['btype']) ? $_GET['btype'] : '';
$type 		= isset($_GET['type']) ? strtoupper($_GET['type']) : '';
$timing 	= isset($_GET['timing']) ? strtoupper($_GET['timing']) : '';
$id 		= isset($_GET['id']) ? strtoupper($_GET['id']) : '';

if (empty($btype)) {
	$btype = reset(tr_pdd_get_batch_types());
}


$exist_key = $wpdb->get_var("select count(*) from ci_wsdl_keys where wsdl_key ='{$key}'");
if (!$exist_key) {
	echo 'wrong key';
	exit;
}

if (isset($_GET['tr_action']) && $_GET['tr_action'] == 'ci_results_pdf') {

	$outfile = trpdd_export_results_pdf($id, '', false, $btype);
	if (empty($ci_pdf_filename)) {
		$ci_pdf_filename = $outfile;
	}
	
	if (!isset($_GET['view'])) {
		header('Content-Disposition: attachment; filename="' . basename($ci_pdf_filename) . '"');
	} else {
		ob_start("ob_gzhandler");
		header('Content-Disposition: ; filename="' . basename($ci_pdf_filename) . '"');
	}
	@readfile($outfile);
}

echo '<html>';
echo '<meta name="robots" content="noindex">';
echo '<body>';
include(trpdd_get_file_path($btype, 'results_for_robots'));
echo '</body>';
echo '</html>';
exit;
