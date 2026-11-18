<?php
$files = trpdd_list_report_html_files($report, $btype);

if (count($files) > 0 && !isset($_REQUEST['tr_action'])) {
	echo '<br><br>';
	echo '<h2>History</h2>';
}
echo '<div class="report_history">';

foreach ($files as $arr) {
	$dir 		= WP_CONTENT_DIR . '/results/html/' . $arr[0];
	$filepath 	= $dir . '/' . $arr[1] . '.html';
	$htmldata 	= file_get_contents($filepath);
	preg_match("/\<meta id=\"final\" content=\"([^\"]+)\"/", $htmldata, $matches);

	$report_type = isset($matches[1]) ? $matches[1] : '';
	$report_type = tr_pdd_get_result_status($report_type);
	echo '<li>';
	echo '<a target=_blank href="?tr_action=report_history_pdf&reid='.$report['id'].'&btype='.$btype.'&d=' . $arr[0] . '&f=' . $arr[1] . '">' . $arr[1] . '</a>';
	echo !empty($report_type) ? ' <span> - ' . $report_type . '</span>' : '';
	echo '</li>';
}
echo '<div>';
