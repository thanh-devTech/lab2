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
	$enddate = $date;
}
$weekday = isset($_REQUEST['weekday']) && $_REQUEST['weekday']>0? intval($_REQUEST['weekday']): 0;
$times = $_REQUEST['evtime'];
$time_start = $_REQUEST['time_start'];
$time_end = $_REQUEST['time_end'];
$eachtime = $_REQUEST['eachtime'];

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

	$startmin = tr_hourmin2min($time_start);
	$endmin = tr_hourmin2min($time_end);
	for( $i = $startmin;$i<=$endmin;$i+=$eachtime)
	{
		$time = tr_min2hourmin($i);
		$times[] = $time;
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
}else if(!empty($enddate) && $enddate>$date)
{
	$new_date = date('Y-m-d', strtotime($date));
	$dates = array();
	for($i=1;$i<=365;$i++)
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

foreach( $dates as $date)
{
	foreach($times as $i => $time)
	{
		$id = $wpdb->get_var("select ID from {$scheduling_tbl} where edate='{$date}' and etime='{$time}' ");

		$arr = array();

		if(count($locations)>0)
		{
			foreach($locations as $location)
			{
				if(!empty($location_selected) && $location['key']!=$location_selected)
				{
					continue;
				}
				$arr['location_'.$location['key']] = 0;
			}

			if(count($locations)==count($arr))
			{
				$wpdb->delete($scheduling_tbl,array('ID'=>$id));
			}else{
				$wpdb->update($scheduling_tbl,$arr,array('ID'=>$id));
			}

		}else{
			$wpdb->delete($scheduling_tbl,array('ID'=>$id));
		}

	}

}

wp_send_json($return);
