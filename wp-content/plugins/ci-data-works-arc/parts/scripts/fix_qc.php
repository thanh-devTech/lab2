<?php
global $wpdb;

$sql = "select * from ci_reports 
where patient_name like '%QC-1' 
or patient_name like '%QC-2' 
or patient_name like '%QC-3'";
$rs = $wpdb->get_results($sql);

foreach ($rs as $r) {
	list($qck, $qc_num) = explode('-', $r->patient_name);
	$exid = $wpdb->get_var("select id from ci_reports where batch_id = '{$r->batch_id}' 
	and patient_name = '{$qck}' 
	and instrument = '{$r->instrument}' ");
	var_dump($r);
	var_dump($qck);
	var_dump($exid);

	if (empty($exid)) {
		$wpdb->update('ci_reports', array(
			'patient_name' => $qck,
		), array('id' => $r->id));
		$exid = $r->id;
	}else{
		$wpdb->delete('ci_reports',array('id'=>$r->id));
	}

	$wpdb->query("update ci_results set drug = concat(drug,'-','{$qc_num}'), report_id = {$exid} where report_id = {$r->id}");
//	exit;
}
