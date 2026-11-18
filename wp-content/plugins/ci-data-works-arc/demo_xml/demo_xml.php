<?php
global $wpdb;

$filename = $_GET['demo'];
header('content-type: text/xml');
$content =  file_get_contents(TRCPDD_PATH . '/demo_xml/' . $filename);

$btype = str_replace('.xml', '', $filename);
if ($btype == 'requisition') {
	$btype = 'req';
}
$labname = $wpdb->get_var("select lab_dba_init from ci_labs order by lab_ID asc limit 1");
if (!empty($labname)) {
	$content = str_replace('<lab>DEM</lab>', '<lab>' . $labname . '</lab>', $content);
}
if (stripos($content, '<aoe_answers></aoe_answers>') > 0) {

	$headers  = $wpdb->get_results("select * from ci_test_aoe_headers 
			                                    where tag_btype='{$btype}' 
			                                    and tag_status = 1
			                                    order by tag_order asc");

	$newquestions = '<aoe_answers>';

	foreach ($headers as $i => $header) {

		$questions = $wpdb->get_results("select * from ci_test_aoe 
				                            where ta_btype= '{$btype}' 
				                                and ta_header_id = {$header->tag_ID}
				                                order by ta_order asc, ta_ID asc");
		foreach ($questions as $row) {
			$fieldname = strtolower($row->ta_slug);
			$newquestions .= '<' . $fieldname . '>' . $row->ta_question . '</' . $fieldname . '>';
		}
	}

	$newquestions .= '</aoe_answers>';
	$content = str_ireplace('<aoe_answers></aoe_answers>', $newquestions, $content);
}

echo $content;
