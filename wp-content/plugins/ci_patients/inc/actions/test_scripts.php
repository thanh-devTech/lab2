<?php
global $wpdb;

if(isset($_GET['testtimeout'])){

	sleep(180);
	echo 'ok';
	exit;
}
else if(isset($_GET['qrr'])){
	$id = isset($_GET['id'])? $_GET['id'] : 3024;
	$QR = tr_patient_qr_by_report($id,'pcr');

	header("Content-type: image/png");
	if(isset($_GET['download'])){
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename=qr.png');
		header('Content-Transfer-Encoding: binary');
	}
	imagepng($QR);
	imagedestroy($QR);
}
else if(isset($_GET['qr'])){
	$id = isset($_GET['id'])? $_GET['id'] : 3024;
	$url = tr_patient_qr_url_report($id,'pcr');
	echo '<a href="'.$url.'" target=_blank>'.$url.'</a>';
}else if(isset($_GET['mms'])){
	$id = isset($_GET['id'])? $_GET['id'] : 3024;
	$url = tr_patient_qr_url_report($id,'pcr');

	global $tr_sms_gateway;

	$loaded = $tr_sms_gateway->load_library();

	if ( $loaded ) {
		$init = $tr_sms_gateway->init_api();
	}

	//$to =  '+13176230565'; //test
	$to = '+13133996201';//corel

	//$url = '';



	$sent_sms = $tr_sms_gateway->send_sms($to, 'Hey, check this out!', 'test', 'test', 'nt',0,array($url) );

	var_dump( $sent_sms );
	var_dump( $tr_sms_gateway->last_result );
	var_dump( $tr_sms_gateway->last_error );
}else if(isset($_GET['listmsg'])){
	global $tr_sms_gateway;

	$loaded = $tr_sms_gateway->load_library();

	if ( $loaded ) {
		$init = $tr_sms_gateway->init_api();
	}

	try{
		$list = $tr_sms_gateway->listmsg(null,null,'+13133996201',null,null,'2022-01-30T23:59:59.000Z');

		foreach($list->getResult()->messages as $msg)
		{
			var_dump($msg);
			echo '<br><br>';
		}
	}catch (Exception $e)
	{
		var_dump($e);
	}
}else if(isset($_GET['sendwelcome'])){
	global $tr_sms_gateway;


	$assen = $_REQUEST['sendwelcome'];
	if(empty($assen))
	{
		return;
	}
	$options = get_option('trpatient_settings',array());

	$loaded = $tr_sms_gateway->load_library();

	if ( $loaded ) {
		$init = $tr_sms_gateway->init_api();
	}


	$sql = array();
	foreach(tr_pdd_get_batch_types() as $btype)
	{

		$table_batch = tr_pdd_get_table_by_btype($btype);
		$sql[] = "(select 
                ba.mid,
                ba.patient_name,
                ba.assen,
                ba.doc,
                pa.pa_ID,
                pa.pa_phone as phone,
                '{$btype}' as btype
                from {$table_batch} ba
                join ci_patients pa ON pa.pa_ID = ba.patient_id
                where ba.assen='{$assen}'
                )";
	}
	$sql = implode(' UNION ALL ', $sql);

	$sql = "select * from ({$sql}) tt 
	    order by tt.mid asc";



	$results = $wpdb->get_results($sql);
	foreach($results as $r)
	{

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

		//var_dump($phone_num, $sms_message,$patient_name,$assen,'n',$patient_id);exit;

		if ( strlen($phone_num) > 8) {
			try {
				$rs = $tr_sms_gateway->send_sms($phone_num, $sms_message,$patient_name,$assen,'n',$patient_id);
				var_dump($rs);
				if($rs)
				{
					$wpdb->insert('ci_sent_notify',array('mid'=>$mid,
					                                     'btype'=>$btype,
					                                     'send_type' => 'new',
					                                     'created'=>date_i18n('Y-m-d H:i:s')
					));
				}

			} catch (Exception $e) {

			}

		}

		exit;

	}

}