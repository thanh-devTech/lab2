<?php
global $wpdb;
$act = isset($_REQUEST['act'])? $_REQUEST['act']:'';
$assen = $_REQUEST['assen'];
$mid = isset($_REQUEST['mid']) && $_REQUEST['mid']>0? $_REQUEST['mid'] : $_REQUEST['id'];
$current_btype = !empty($_REQUEST['btype'])? $_REQUEST['btype'] : 'tox';
$fname = !empty($_REQUEST['fname'])? $_REQUEST['fname'] : '';
$lname = !empty($_REQUEST['lname'])? $_REQUEST['lname'] : '';
$dob = !empty($_REQUEST['dob'])? $_REQUEST['dob'] : '';


$return = array('status'=>'ok');
$exist = false;

if($act=='check_limit_days'){
	$groups = $_REQUEST['group_limits'];
	$pa_ID = $_REQUEST['pa_id'];
	$table = tr_pdd_get_table_by_btype($current_btype);
	$return['groups'] = array();
	$return['mids'] = array();
	$group_name = '';
	$days = 0;
	$current_time = strtotime(date_i18n('Y-m-d H:i:s'));

	foreach($groups as $groupid)
	{
		$groupid = str_ireplace('GROUP_','',$groupid);

		$group = $wpdb->get_row("select tg_name, tg_limit_days 
						from ci_test_groups 
						where tg_ID = {$groupid} and tg_limit_days > 0");
		if(!$group)
		{
			continue;
		}

		$limit_date = date('Y-m-d', $current_time - $group->tg_limit_days * 86400);
		//$return['limit_date'] = $limit_date;

		$sql_limit = "select mid,doc from {$table}
					where FIND_IN_SET('GROUP_{$groupid}',panel_tests_requested) 
					and doc > '{$limit_date}'
					and patient_id = {$pa_ID}";

		//$return['sql'] = $sql_limit;
		$existlimit = $wpdb->get_row($sql_limit);
		if($existlimit)
		{
			$days 		= $group->tg_limit_days;
			$group_name = $group->tg_name;
			$lastdays 	= floor( (time() - strtotime($existlimit->doc)) / 86400 );

			$return['groups'][] = $groupid;
			$return['mids'][] = $existlimit->mid;
			
		}

	}

	if(!empty($group_name) && $days>0)
	{
		$return['status'] = 'limit';
		$return['msg'] = 'Too soon for '.$group_name.' - you can only test once every '.$days.' days!  ';
		if(!tr_current_role_in(array('doctor','collector','office_manager','distributor'))){
			$return['msg'] = 'Are you sure you want to add '.$group_name.'? the last test was done '.$lastdays.' day ago. ';
			$return['status'] = 'limit_ask';
		}
		
	}

}
else if(!empty($assen))
{
	foreach(tr_pdd_get_batch_types() as $btype)
	{
		$table = tr_pdd_get_table_by_btype($btype);
		$sql = "select mid from {$table}  where assen='{$assen}' ";
		if($current_btype==$btype)
		{
			$sql.= " and mid != '{$mid}' and (cli_ID > 0 or doc_ID > 0)";
		}
		$exist = $wpdb->get_var($sql);

		if($exist)
		{
			$return['sql'] = $sql;
			break;
		}
	}
}
else if(!empty($fname) && !empty($lname) && !empty($dob) && empty($mid))
{
	$today = date_i18n('Y-m-d');
	$table = tr_pdd_get_table_by_btype($current_btype);
	$exist = $wpdb->get_var("select mid from {$table} ba
						join ci_patients pa ON pa.pa_ID = ba.patient_id
						where pa.pa_first='{$fname}'
						and pa.pa_last = '{$lname}'
						and pa.pa_dob = '{$dob}' 
						and ba.doc >= '{$today}'
						and ba.doc <= '{$today} 23:59:59'");
}


if($exist)
{
    $return['status'] = 'dublicate';
    $return['msg'] = 'This Accession number is already in use!';
}

wp_send_json($return);
exit;