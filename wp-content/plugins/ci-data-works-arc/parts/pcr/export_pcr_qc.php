<?php
global $wpdb;
$batch_id= $_REQUEST['batch_id'];
if(empty($batch_id))
{
	exit;
}

$all_status = tr_pdd_get_batch_statuses(false,false,false,'all');

$all_tests = array();
$rs = $wpdb->get_results("select te_ID, te_name,te_group_id from ci_test_panels where te_type='pcr'");
foreach($rs as $r)
{
	$all_tests[$r->te_ID] = array(
		'name' => $r->te_name,
		'group_id' => $r->te_group_id,
	);
}


$groups = $wpdb->get_results("select * from ci_test_groups where tg_type = 'pcr' order by tg_name", ARRAY_A);
$all_groups = array();
foreach($groups as $group)
{
	$all_groups[$group['tg_ID']] = $group['tg_name'];
}



$where = " and ba.batch_id = '{$batch_id}' ";

$sql = "select ba.position,
        ba.assen,
        ba.patient_name,
        ba.dob,
        ba.doc,
        ba.requesting_practice,
        ba.requesting_physician,
        ba.batch_status,
        ba.panel_tests_requested as tests,
        ba.notes,
        ba.type_of_specimen,
        ba.culture
        
        from ci_batches_pcr ba 
        where 1 {$where}
        order by ba.position asc";
$samples = $wpdb->get_results($sql);

$filename = 'pcr_qc_batch_'.$batch_id.'.csv';

header("Pragma: public");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: private",false);
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=\"$filename\";" );
header("Content-Transfer-Encoding: binary");

$f = fopen('php://output','w');

$keys = array(
	'Position',
	'Patient Name',
	'Accession',
	'DOB',
	'Clinic',
	'Doctor Name',
	'Collection Date',
	'Req Panels',
	'Notes',
	'Diagnostic codes',
	'Specimen source',
	'Status of the form'
);

fputcsv($f, $keys);

foreach($samples as $row)
{
	$tests = array();
	$row->tests = strtoupper($row->tests);
	$rs_tests = explode(',',$row->tests);
	foreach($rs_tests as $t)
	{
		if(stripos($t,'group')===false)
		{
			if($t>0 && isset($all_tests[$t]))
			{
				$t = $all_tests[$t];
				if(!in_array('GROUP_'.$t['group_id'], $rs_tests))
				{
					$tests[] = $t['name'];
				}
			}

		}else{
			$group_id = str_ireplace('group_','',$t);
			if(isset($all_groups[$group_id])){
				$tests[] = $all_groups[$group_id];
			}
		}
	}

	if($row->culture )
	{
		$tests[] = 'Culture';
	}
	$tests = implode(', ',$tests);

	$row_data = array(
		$row->position,
		$row->patient_name,
		$row->assen,
		$row->dob,
		$row->requesting_practice,
		$row->requesting_physician,
		$row->doc,
		$tests,
		$row->notes,
		$row->dagnostic_codes1,
		$row->type_of_specimen,
		$all_status[$row->batch_status]
	);

	fputcsv($f, $row_data);
}
fclose($f);


exit;