<?php
global $wpdb;

$custom_form = isset($_REQUEST['custom_form']) && !empty($_REQUEST['custom_form'])? $_REQUEST['custom_form'] : '';
$escheduling = isset($_REQUEST['escheduling']) && !empty($_REQUEST['escheduling'])? $_REQUEST['escheduling'] : '';
$doc1 = isset($_REQUEST['doc1']) && !empty($_REQUEST['doc1'])? $_REQUEST['doc1'] : '';
$doc2 = isset($_REQUEST['doc2']) && !empty($_REQUEST['doc2'])? $_REQUEST['doc2'] : '';
if(empty($custom_form))
{
	if(isset($_REQUEST['is_concierge']) && $_REQUEST['is_concierge'])
	{
		$custom_form = 'concierge';
	}
	else if(isset($_REQUEST['is_canada']) && $_REQUEST['is_canada'])
	{
		$custom_form = 'canada';
	}
	else if(isset($_REQUEST['is_meijer']) && $_REQUEST['is_meijer'])
	{
		$custom_form = 'meijer';
	}
}



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


$datetimenow = date_i18n('Y-m-d H:i');
$datelimit_stamp = strtotime($datetimenow) +5*60;
if($custom_form == 'concierge')
{
	$datelimit_stamp = strtotime(date_i18n('Y-m-d'))+86400;
}
$startdate = date('Y-m-d',strtotime($_REQUEST['start']));
$enddate = date('Y-m-d',strtotime($_REQUEST['end']));
if(isset($_REQUEST['frontshow']) && $startdate < date_i18n('Y-m-d')){
	$startdate = date_i18n('Y-m-d');
}

$timefrom = isset($_REQUEST['timefrom'])? trim($_REQUEST['timefrom']) : '';
$timeto   = isset($_REQUEST['timeto'])? trim($_REQUEST['timeto']) : '';
if(!empty($timefrom))
{
	$timefrom = strtotime($timefrom);
}
if(!empty($timeto))
{
	$timeto = strtotime($timeto);
}

$location_selected = '';
if(isset($_REQUEST['location']) && !empty($_REQUEST['location'])){
	$location_selected = $_REQUEST['location'];
}
if($location_selected=='undefined'){
	$location_selected = '';
}
$max_col = 'max_number';
$count_col = 'count_req';




$limit_weekdates = array();
$date_adding_product = '';
$dateweek_adding_product = array();
$dateweek_key1 = array();
if(!empty($escheduling))
{
	foreach($form_options['limitscheduling'] as $lmarr)
	{
		if($lmarr['key']=='1')
		{
			$dateweek_key1 = $lmarr['dweeks'];
		}else if($lmarr['key']=='2' && empty($doc1) && !empty($doc2))
		{
			$daywdoc2 = date('N', strtotime($doc2));
			$arrlm2 = explode('-',$lmarr['lm1']);
			foreach($arrlm2 as $lm2)
			{
				$daywdoc1 = $daywdoc2 - $lm2;
				if($daywdoc1<0)
				{
					$daywdoc1 = 7 + $daywdoc1;
				}
				if(in_array($daywdoc1, $dateweek_key1)){
					$doc1 = date('Y-m-d', strtotime($doc2)-$lm2*86400);
					break;
				}
			}

		}

		if($escheduling===$lmarr['key']){
			$limit_weekdates = is_array($lmarr['dweeks'])? $lmarr['dweeks']:'';
			if(!empty($lmarr['lm1']))
			{
				if(!empty($doc1)){
					$doc1time = strtotime($doc1);
					list($lmin,$lmax) = explode('-',$lmarr['lm1']);
					if(empty($lmax))
					{
						$lmax = $lmin;
					}
					$startdate = date('Y-m-d', $doc1time + $lmin*86400);
					$enddate = date('Y-m-d', $doc1time + $lmax*86400);

					if(isset($lmarr['prad']) && $lmarr['prad']>0)
					{
						$date_adding_product = date('Y-m-d', $doc1time + $lmarr['pradlm']*86400);
					}

				}
				else if($lmarr['pradlm']>0 && $escheduling>1 && !empty($dateweek_key1))
				{
					foreach($dateweek_key1 as $wd)
					{
						$wad = $wd+$lmarr['pradlm'];
						if($wad>7)$wad = $wad-7;
						$dateweek_adding_product[] = $wad;
					}
				}

			}

			break;
		}
	}
}

$where = " edate >= '{$startdate}' and edate <= '{$enddate}'  ";

if(isset($_REQUEST['frontshow'])){
	if(!empty($location_selected))
	{
		$max_col = 'location_'.$location_selected;
		$count_col = 'location_count_'.$location_selected;
	}

	$where.= " and ({$max_col} > {$count_col} or {$count_col} IS NULL) ";

}
$sql = "select * from {$scheduling_tbl}
				where {$where}
				order by edate asc, etime asc";

$results = $wpdb->get_results($sql);



if(!empty($wpdb->last_error))
{
	include(TRCPPR_PATH . 'inc/install.php');
}
if(isset($_GET['debug'])){
	var_dump($wpdb->last_error);
	var_dump($results);echo '<br><br>';
	var_dump($startdate);echo '<br>';
	var_dump($enddate);echo '<br>';
	var_dump($sql);
	echo '<br><br>';
}
$return = array();
$data_dates = array();
foreach($results as $r)
{
	$etime = strtotime($r->etime);
	if($timefrom > $etime)
	{
		continue;
	}
	if($timeto > 0 && $timeto < $etime)
	{
		continue;
	}

	$title = tr_display_hourmin($r->etime);
	$datetime = $r->edate.' '.$r->etime;
	$datetime_stamp = strtotime($datetime);
	$weekday = date('N',$datetime_stamp);

	if(!isset($_REQUEST['frontshow'])){
		if(count($locations)>0)
		{
			foreach($locations as $location)
			{
				$col_key = 'location_'.$location['key'];
				$title.= '|'.$r->{$col_key};
			}
		}else{
			$title .= ' | '.$r->max_number.' | '.intval($r->count_req);
		}

	}else if($datetime_stamp < $datelimit_stamp){
		continue;
	}

	if(!empty($limit_weekdates))
	{

		if(!in_array($weekday,$limit_weekdates))
		{
			continue;
		}
	}

	$row = array(
		'title' => $title,
		//'timestr' => $r->etime,
		//'tfrom' => $timefrom,
		//'tt' => $timeto,
		'start' => $datetime,
	);
	if(!empty($date_adding_product)){
		if($date_adding_product==$r->edate)
		{
			$row['classNames']= array('aop');
		}
	}else if(!empty($dateweek_adding_product))
	{
		if(in_array($weekday,$dateweek_adding_product))
		{
			$row['classNames']= array('aop');
		}
	}
	$return[] = $row;

}

wp_send_json($return);