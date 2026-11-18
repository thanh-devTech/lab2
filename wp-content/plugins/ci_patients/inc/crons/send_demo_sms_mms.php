<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb,$tr_sms_gateway;

if($userid==0 || !defined('DEMO_PATIENT_ID'))
{
	return;
}
$phone_num = get_user_meta($userid,'phone',true);

if(empty($phone_num))
{
	return;
}


$loaded = $tr_sms_gateway->load_library();

if ( $loaded ) {
	$init = $tr_sms_gateway->init_api();
}

$report_id = 3497;
$patient = $wpdb->get_row("select * from ci_patients where pa_ID= ".DEMO_PATIENT_ID);
$report = $wpdb->get_row("select ba.mid,ba.assen from ci_batches_pcr ba
						join ci_reports_pcr re ON ba.assen = re.patient_id
						where re.id = {$report_id}");

$options = get_option('trpatient_settings',array());
$has_passport = strlen($patient->pa_passport)>=8;
if(str_replace('0','',$patient->pa_passport)===''){
	$has_passport = false;
}

if(empty($options['sms_msg']))
{
	$options['sms_msg'] = 'Your Test Results are now available please visit: '.get_bloginfo('url');
}

if(empty($options['mms_msg']))
{
	$options['mms_msg'] = 'Travel Testing Validation QR
Covid-19 RT-PCR Result for {first name} {current date}';
}


$patient_id = DEMO_PATIENT_ID;
$test_name = 'nCoV-2019';
$patient_name = 'Patient, Test';
$assen = $report->assen;


$sms_message = stripslashes($options['sms_msg']);
$sms_message = str_replace('{test name}',$test_name ,$sms_message);
$sms_message = str_replace(array('{patient name}','{patient_name}'), $patient_name, $sms_message);
$sms_message = str_replace(array('{accessions}','(accession)','{accession}'), $assen, $sms_message);

$msg_type = 'nt';
if($has_passport){

	$url = tr_patient_qr_url_report($report_id,'pcr');

	$mms_message = $sms_message;

	$sms_message = stripslashes($options['mms_msg']);
	$sms_message = str_replace('{test name}',$test_name ,$sms_message);
	$sms_message = str_replace(array('{patient name}','{patient_name}'), $patient_name, $sms_message);
	$sms_message = str_replace(array('{accessions}','(accession)','{accession}'), $assen, $sms_message);
	$sms_message = str_replace('{first name}', $first_name, $sms_message);
	$sms_message = str_replace('{current date}', date_i18n('m/d/Y'), $sms_message);

	$sent_mms = $tr_sms_gateway->send_sms($phone_num,$mms_message,$patient_name,$assen,'nt',$patient_id,array($url));
	$msg_type = 'ms';

}

$sent_sms = $tr_sms_gateway->send_sms($phone_num,$sms_message,$patient_name,$assen,$msg_type,$patient_id);