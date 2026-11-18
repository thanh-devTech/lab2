<?php

$rejected = tr_pdd_get_batch_statuses($report['batch_status'], false, false, $btype);


if (strpos($rejected, '-') === false) {
	$rejected = 'Rejected - ' . $rejected;
}
if (isset($_GET['html'])) {
	var_dump($report['batch_status']);
	var_dump($rejected);
}
$arr = explode('-', $rejected, 2);
$rejected_title = trim($arr[0]);
$rejected_reason = isset($arr[1]) ? trim($arr[1]) : '';


if (tr_get_option('ci_pdf_data', 'pdf_amended_results') && intval($report['final_results']) === 3) {
	echo '<h1 class="center amended_results red" style="font-size:14px">Amended Results</h1>';
}


?>
<br>
<br><br><br><br>
<h1 style="color:red;font-size:20px;text-align: center;text-transform: uppercase"><?php echo $rejected_title ?></h1><br>
<style>
	body .notes_report {
		text-align: center;
		font-size: 16px !important;
		font-weight: bold;
	}
</style>
<?php


if (!empty($rejected_reason) && $rejected_reason != 'Rejected') {
	echo '<p style="line-height:20px;" class="notes_report">' . $rejected_reason . '</p>';
}
if (strtoupper($rejected_title) == 'INCONCLUSIVE') {
	echo '<br><p style="color:red;text-align:center;font-size:16px;text-transform: uppercase;line-height:20px;" class="notes_report">Specimen Deemed Inconclusive After Two Consecutive Runs</p>';

	echo '<br><p style="color:red;text-align:center;font-size:16px;font-weight:bold;line-height:20px;" class="notes_report">RECOLLECTION IS HIGHLY RECOMMENDED.</p>';
}
if ($report['batch_status'] == 're_yscbp' && isset($notes['rejectin']) && !empty($notes['rejectin'])) {
	echo '<p style="color:red;text-align:center;font-size:16px;line-height:20px;" class="notes_report">' . nl2br($notes['rejectin']) . '</p>';
}
?>
<p style="text-align: center;" class="notes_report"><?php echo trpdd_filter_comments_in_pdf($report['billing_notes']) ?></p>
<?php
$has_report = true;
