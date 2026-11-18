<?php
global $wpdb;

$child = array();
$listids = array();



foreach (tr_pdd_get_batch_types() as $btype) {
	$batch_tbl = tr_pdd_get_table_by_btype($btype);

	$child[] = "( 
			select ba.assen,
			ba.mid,
			ba.patient_name,
			ba.ref_lab_ID,
			ba.panel_tests_requested,
			wb.btype as btype
			from {$batch_tbl} ba 
			JOIN ci_wsdl_batch wb ON wb.b_mid = ba.mid and wb.btype = '{$btype}' and b_status =1 and b_exported=0
			JOIN ci_wsdl_keys wk ON wk.wsdl_ID=wb.b_wsdl_ID and wk.wsdl_type = 'req_{$btype}' 
			group by ba.mid order by ba.ref_lab_ID asc, ba.position asc, ba.mid desc 
			)";
}
$child = implode(' UNION ALL ', $child);
$sql = "select * from ({$child}) as r";
$rs = $wpdb->get_results($sql);
if(!empty($wpdb->last_error))
{
	echo $wpdb->last_error;exit;
}
$all_panels = array();
$rsps = $wpdb->get_results("select tg_ID, tg_name from ci_test_groups");
foreach ($rsps as $r) {
	$all_panels[$r->tg_ID] = $r->tg_name;
}

$reflabs = array();
$rls = $wpdb->get_results("select * from ci_labs");
foreach ($rls as $r) {
	$reflabs[$r->lab_ID] = $r->lab_name;
}

$datenow = date_i18n('Ymd');
$filename = 'RefLab_Transfer_Export_' . $datenow . '.csv';
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: private", false);
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=\"$filename\";");
header("Content-Transfer-Encoding: binary");
$header = array('Accession', 'Patient Name', 'Panels');
$f = fopen('php://output', 'w');


$in_labs = array();
foreach ($rs as $r) {
	if(!isset($listids[$r->btype]))
	{
		$listids[$r->btype] = array();
	}
	$listids[$r->btype][] = $r->mid;


	if (!in_array($r->ref_lab_ID, $in_labs)) {
		$labname = $reflabs[$r->ref_lab_ID];
		fputcsv($f, array($labname));
		fputcsv($f, $header);
		$in_labs[] = $r->ref_lab_ID;
	}
	$panels = array();
	foreach (explode(',', $r->panel_tests_requested) as $t) {
		if (stripos($t, 'group_') !== false) {
			$groupid = str_ireplace('group_', '', $t);
			$panelname = $all_panels[$groupid];
			if (!empty($panelname)) {
				$panels[] = $panelname;
			}
		}
	}
	$rowdata = array(
		$r->assen,
		$r->patient_name,
		implode(';', $panels)
	);
	fputcsv($f, $rowdata);
}


//update
foreach($listids as $btype => $listid)
{
	$listid_str = implode(',', $listid);
	$wpdb->query("update ci_wsdl_batch set b_exported = 1 where btype = '{$btype}' 
	and b_mid in ({$listid_str})");
}
//$wpdb->query("update ci_wsdl_batch set b_exported = 0");