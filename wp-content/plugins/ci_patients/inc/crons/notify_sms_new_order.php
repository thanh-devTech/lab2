<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb,$tr_sms_gateway,$current_lab_id;

$sending = get_option('tr_sms_new_order_sending',0);

if($sending > time() - 3600 && !isset($_GET['trcppr_cron_notify_sms_new_order']))
{
	echo 'out';
	return false;
}
update_option('tr_sms_new_order_sending', time());

if($current_lab_id>0)
{
	$lab_name = $wpdb->get_var("select lab_name from ci_labs where lab_ID = {$current_lab_id}");
}else{
	$lab_name = $wpdb->get_var("select lab_name from ci_labs order by lab_ID asc limit 1");
}

$options = get_option('trpatient_settings',array());
$options['enabel_notify_mi'] = intval($options['enabel_notify_mi']);

if(!$options['sms_new_order_notify']){
	echo 'no sms_new_order_notify';
	return false;
}

if(!$options['enabel_notify_mi'])
{
	echo 'not enabel_notify_mi';
	return false;
}

if(!$tr_sms_gateway || !is_object($tr_sms_gateway))
{
	echo 'no api';
	return false;
}
$loaded = $tr_sms_gateway->load_library();

if ($loaded) {
	$init = $tr_sms_gateway->init_api();
}

if (empty($options['sms_new_order_msg'])) {
	$options['sms_new_order_msg'] = 'Thank you and Welcome to {lab name} - Your Lab order is {accession}';
}
$options['sms_new_order_msg'] = str_replace('{lab name}', $lab_name, $options['sms_new_order_msg']);
$options['sms_new_order_msg'] = str_replace('{lab}', $lab_name, $options['sms_new_order_msg']);


$limit_date = date_i18n('Y-m-d H:i:s');
$limit_date = date('Y-m-d H:i:s' , strtotime($limit_date) - 3600*4);

$joins = '';
$more_fields = '';
$where = " and ba.batch_created >= '{$limit_date}' ";
$where.= " and (ba.req_form_time is not null or paid<0)";//paid<0 by api
$where.= " and ba.paid !=-12 ";//not prefill covid
$where.= " and ba.batch_status !='archived' ";

$where.= " and (ba.ref_lab_ID = 0 or ba.ref_lab_ID is null)";
$where.= " and sn.ID is null ";
$where.= " and pa.pa_phone !='' and pa.pa_phone !='000-000-0000' ";

if(!$options['enabel_notify_mi'])
{
	$where.= " and (ba.patient_portal_type !='' and ba.patient_portal_type is not null) ";
}

if($options['enabel_notify_mi']===2)
{
	//No Welcome SMS: cli_nowc_sms=1

	$where.= " and ba.cli_ID in (select cID from ci_clinic where cli_send_result_sms = 1 and cli_nowc_sms = 0) ";


	$cids_covid = $wpdb->get_col("select cID from ci_clinic where cli_send_ocovid_sms = 1");
	if(count($cids_covid)>0)
	{
		$cids_covid_str = implode(',',$cids_covid);

		$group_id = $wpdb->get_var("select tg_ID from ci_test_groups where tg_type='pcr' 
                                and tg_is_covid = 1");
		$where .= " and (
							(
								FIND_IN_SET('GROUP_{$group_id}',ba.panel_tests_requested)
						        and ba.cli_ID in ({$cids_covid_str})
						    )
						    OR ba.cli_ID not in ({$cids_covid_str})
						)";
	}
}

if(stripos($options['sms_new_order_msg'],'location')>0)
{
	$joins.= " join ci_clinic cli ON cli.cID = ba.cli_ID ";
	$more_fields.= ",cli.cli_clinic, cli.cli_street, cli.cli_city, cli.cli_state, cli.cli_zip ";
}


$sql = array();
foreach(tr_pdd_get_batch_types() as $btype)
{
	$where_covid = '';
	if($options['only_covid']){
		if($btype=='pcr')
		{
			$group_id = $wpdb->get_var("select tg_ID from ci_test_groups where tg_type='pcr' 
                                and tg_is_covid = 1");
			$where_covid.= " and FIND_IN_SET('GROUP_{$group_id}',ba.panel_tests_requested) ";
		}else if($btype=='blood'){
			$group_id = $wpdb->get_var("select tg_ID from ci_test_groups where tg_type='blood' 
                                and tg_is_covid = 1");
			$where_covid.= " and FIND_IN_SET('GROUP_{$group_id}',ba.panel_tests_requested) ";
		}else{
			continue;
		}
	}
	
	$table_batch = tr_pdd_get_table_by_btype($btype);
	$sql[] = "(select 
                ba.mid,
                ba.patient_name,
                ba.assen,
                ba.doc,
                pa.pa_ID,
                pa.pa_phone as phone,
                '{$btype}' as btype
                {$more_fields}
                from {$table_batch} ba
                join ci_patients pa ON pa.pa_ID = ba.patient_id
                {$joins}
                left join ci_sent_notify sn ON sn.mid = ba.mid and sn.btype='{$btype}' and sn.send_type = 'new'
                where 1 {$where} {$where_covid}
                )";
}

if(empty($sql))
{
	return;
}
$sql = implode(' UNION ALL ', $sql);

$sql = "select * from ({$sql}) tt 
	    order by tt.mid asc";



$results = $wpdb->get_results($sql);



if(!empty($wpdb->last_error))
{
	wp_mail('pntrinh29@gmail.com',$lab_name.' - sql error',$wpdb->last_error."\n".$wpdb->last_query);
}
if(isset($_GET['debug'])){
	var_dump($sql);
	echo '<br><br><br>';
	var_dump($wpdb->last_error);
	echo '<br><br><br>';
	var_dump($results);
	echo '<br><br><br>';
	exit;
}


foreach($results as $r)
{
	//check sent again
	$sent = $wpdb->get_var("select ID from ci_sent_notify 
							where mid = {$r->mid} 
							and btype='{$r->btype}' and send_type = 'new' ");
	if($sent)
	{
		continue;
	}


	$btype = $r->btype;
	$mid   = $r->mid;
	$assen = $r->assen;
	$datetime = '';
	$datetime2 = '';
	if(!empty($r->doc))
	{
		$datetime = date('M d @ h:iA', strtotime($r->doc));
		$datetime2 = date('m/d/Y', strtotime($r->doc)).' at '.date('H:i', strtotime($r->doc));
	}

	$location_name = '';
	$location_address = '';

	if(stripos($options['sms_new_order_msg'],'location')>0)
	{
		$location_name = $r->cli_clinic;
		if(!empty($r->cli_street) && !empty($r->cli_city))
		{
			$location_address = $r->cli_street.', '.$r->cli_city.', '.$r->cli_state.' '.$r->cli_zip;
		}

	}

	$phone_num = $r->phone;
	$phone_num = str_replace('-','',$phone_num);
	$patient_name = $r->patient_name;
	$patient_id = $r->pa_ID;
	if($options['sms_fl_name'] && strpos($patient_name,',')>0)
	{
		list($last_name, $first_name) = explode(',',$patient_name);
		$patient_name = trim($first_name).' '.trim($last_name);
	}

	$sms_message = stripslashes($options['sms_new_order_msg']);

	$sms_message = str_replace(array('{lab}','{lab name}'), $lab_name, $sms_message);

	$sms_message = str_replace(array('{patient name}','{patient_name}'), $patient_name, $sms_message);

	$sms_message = str_replace(array('{accessions}','(accession)','{accession}'), $assen, $sms_message);

	$sms_message = str_replace('{datetime}', $datetime, $sms_message);
	$sms_message = str_replace('{datetime2}', $datetime2, $sms_message);

	$sms_message = str_replace('{location}', $location_name, $sms_message);
	$sms_message = str_replace(array('{location_address}','{location address}'), $location_address, $sms_message);

	$sms_message = str_replace(array('@,','@ ,','@  ,',', ,',',,'),'',$sms_message);

	if ( strlen($phone_num) > 8) {
		try {
			$rs = $tr_sms_gateway->send_sms($phone_num, $sms_message,$patient_name,$assen,'n',$patient_id);
			if($rs)
			{
				$wpdb->insert('ci_sent_notify',array('mid'=>$mid,
				                                     'btype'=>$btype,
				                                     'send_type' => 'new',
				                                     'created'=>date_i18n('Y-m-d H:i:s')
				));
			}

		} catch (Exception $e) {
			sleep(10);
		}

	}

}

update_option('tr_sms_new_order_sending', 0);