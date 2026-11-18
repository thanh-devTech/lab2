<?php
global $wpdb;

if(!user_can(get_current_user_id(),'administrator'))exit;

$custom_form = isset($_REQUEST['custom_form']) && !empty($_REQUEST['custom_form'])? $_REQUEST['custom_form'] : '';
$form_key = 'trpatient_product_settings';
$scheduling_tbl = 'ci_scheduling';
if(!empty($custom_form))
{
	$form_key = 'trpatient_'.$custom_form.'_settings';
	$scheduling_tbl = 'ci_scheduling_'.$custom_form;
}

$form_options = get_option($form_key, array());


$locations = array();
if($form_options['enable_location'])
{
	$locations = is_array($form_options['locations']) ? $form_options['locations']:array();
}

if(count($locations)>0)
{
	$rs = $wpdb->get_results("show COLUMNS from  {$scheduling_tbl}");
	$columns = array();
	foreach ($rs as $col)
	{
		if(stripos($col->Field,'location_')!==false)
		{
			$columns[$col->Field] = $col->Field;
		}

	}
	foreach($locations as $location)
	{
		$col_key = 'location_'.$location['key'];
		$col_count_key = 'location_count_'.$location['key'];
		if(!in_array($col_key,$columns))
		{
			$wpdb->query("alter table {$scheduling_tbl} add column {$col_key} int(5) default 0");
			$wpdb->query("alter table {$scheduling_tbl} add column {$col_count_key} int(5) default 0");
		}
		unset($columns[$col_key]);
		unset($columns[$col_count_key]);
	}

	if(count($columns)>0)
	{
		foreach($columns as $column)
		{
			$wpdb->query("alter table {$scheduling_tbl} drop column {$column}");
		}
	}
}

$return = array('status'=>'ok');
$date = $_REQUEST['date'];
$enddate = $_REQUEST['enddate'];
if(empty($enddate))
{
	$enddate = $data;
}

$weekday = isset($_REQUEST['weekday']) && $_REQUEST['weekday']>0? intval($_REQUEST['weekday']): 0;
$times = $_REQUEST['evtime'];
$time_start = $_REQUEST['time_start'];
$time_end = $_REQUEST['time_end'];
$eachtime = $_REQUEST['eachtime'];
$max_numbers = $_REQUEST['max_number'];
$location_selected = $_REQUEST['location'];
$locations_numbers = array();

if(count($locations)>0)
{
	foreach($locations as $location)
	{
		$locations_numbers[$location['key']] = $_REQUEST['location_'.$location['key']];
	}
}

$removelist = trim($_REQUEST['removelist'],',');
$removelist = explode(',',$removelist);


$is_month = strlen($date)<=7;

if(empty($times) && !empty($time_start) && !empty($time_end) && !empty($eachtime) ){

	$times = array();
	$max_numbers = array();
	$locations_numbers = array();

	if($time_start!='auto'){
		$startmin = tr_hourmin2min($time_start);
		$endmin = tr_hourmin2min($time_end);
		for( $i = $startmin;$i<=$endmin;$i+=$eachtime)
		{
			$time = tr_min2hourmin($i);
			if($time=='00:00'){
				continue;
			}
			$times[] = $time;
			$max_numbers[] = $_REQUEST['max_number'];

			if(count($locations)>0)
			{
				foreach($locations as $location)
				{
					$locations_numbers[$location['key']][] = $_REQUEST['max_number'];
				}
			}
		}
	}
}

$dates = array($date);
if($is_month)
{
	$month = date('m',strtotime($date));
	$new_date = date('Y-m-01', strtotime($date));
	$dates = array();
	for($i=1;$i<=31;$i++)
	{
		$passdate = true;
		if($weekday>0 && date('N', strtotime($new_date))!=$weekday)
		{
			$passdate = false;
		}

		if($passdate)
		{
			$dates[] = $new_date;
		}
		$new_date = date('Y-m-d', strtotime('+1day', strtotime($new_date)));
		$new_month = date('m',strtotime($new_date));
		if($new_month != $month)
		{
			break;
		}
	}
}else if(!empty($enddate) && $enddate>=$date)
{
	$new_date = date('Y-m-d', strtotime($date));
	$dates = array();
	for($i=1;$i<=360;$i++)
	{
		$passdate = true;
		if($weekday>0 && date('N', strtotime($new_date))!=$weekday)
		{
			$passdate = false;
		}

		if($passdate)
		{
			$dates[] = $new_date;
		}

		if($new_date>=$enddate)
		{
			break;
		}
		$new_date = date('Y-m-d', strtotime('+1day', strtotime($new_date)));

	}
}


$clinic = null;
$auto_location = false;
if($time_start=='auto' && !empty($location_selected)){

	foreach($locations as $location)
	{
		if($location['key']==$location_selected)
		{
			$clinic = $wpdb->get_row("select * from ci_clinic where cID = {$location['cli_ID']}", ARRAY_A);
			break;
		}
	}
	$auto_location = true;
}else if($time_start=='auto' && !$form_options['enable_location']){
	$cli_ID = $form_options['cli_ID'];

	if($cli_ID>0)
	{
		$clinic = $wpdb->get_row("select * from ci_clinic where cID = {$cli_ID}", ARRAY_A);
		if($clinic)
		{
			$auto_location = true;
		}
	}

}

foreach( $dates as $date)
{
	if($auto_location){

		$times = array();
		$max_numbers = array();
		$locations_numbers = array();

		if($clinic!==null)
		{
			$dateofw = strtolower(date('D',strtotime($date)));
			$clidatekey = 'cli_'.$dateofw;
			if(empty($clinic[$clidatekey])){
				continue;
			}
			$timestr = $clinic[$clidatekey];
			$timestartends = array();
			if(strpos($timestr,',')>0)
			{
				$timestr2 = explode(',',$timestr);
				$timestartends[] = explode('-',$timestr2[0]);
				$timestartends[] = explode('-',$timestr2[1]);
			}else{
				$timestartends[] = explode('-',$timestr);
			}

			foreach($timestartends as $timestartend)
			{
				$time_start = $timestartend[0];
				$time_end = $timestartend[1];

				if(empty($time_start) || empty($time_end))
				{
					continue;
				}
				$time_start = tr_get_key_hourmin($time_start,'auto');
				$time_end = tr_get_key_hourmin($time_end,'auto');


				$startmin = tr_hourmin2min($time_start);
				$endmin = tr_hourmin2min($time_end);
				for( $i = $startmin;$i<=$endmin;$i+=$eachtime)
				{
					$time = tr_min2hourmin($i);
					if($time=='00:00'){
						continue;
					}
					$times[] = $time;
					$max_numbers[] = $_REQUEST['max_number'];

					if(count($locations)>0)
					{
						foreach($locations as $location)
						{
							$locations_numbers[$location['key']][] = $_REQUEST['max_number'];
						}
					}
				}
			}

		}else{
			continue;
		}
	}

	foreach($times as $i => $time)
	{
		$id = $wpdb->get_var("select ID from {$scheduling_tbl} where edate='{$date}' and etime='{$time}' ");

		$arr = array(
			'edate' => $date,
			'etime' => $time,
			'max_number' => intval($max_numbers[$i])
		);

		if(count($locations)>0)
		{
			foreach($locations as $location)
			{
				if(!empty($location_selected) && $location['key']!=$location_selected)
				{
					continue;
				}
				$arr['location_'.$location['key']] = intval($locations_numbers[$location['key']][$i]);
			}
			unset($arr['max_number']);
		}

		if($id>0)
		{
			if(in_array($id,$removelist))
			{
				if (($idx= array_search($id, $removelist)) !== false) {
					unset($removelist[$idx]);
				}
			}
			$wpdb->update($scheduling_tbl,$arr,array('ID'=>$id));
		}else{
			$arr['created'] = date_i18n('Y-m-d H:i:s');
			$wpdb->insert($scheduling_tbl,$arr);
		}
	}

}

if(!$is_month && count($removelist)>0)
{
	$removelist = implode(',',$removelist);
	if(!empty($removelist))
	{
		$wpdb->query("delete from {$scheduling_tbl} where ID in ({$removelist})");
	}

}

wp_send_json($return);
