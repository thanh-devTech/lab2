<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
add_action('cron_api_noah', 'tr_pdd_cron_api_noah');
function tr_pdd_cron_api_noah()
{
	include(TRCPDD_PATH . 'inc/crons/api_noah.php');
}


add_action('cron_api_cteh', 'tr_pdd_cron_api_cteh');
function tr_pdd_cron_api_cteh()
{
	include(TRCPDD_PATH . 'inc/crons/api_cteh.php');
}


add_action('cron_api_elink', 'tr_pdd_cron_api_elink');
function tr_pdd_cron_api_elink()
{
	include(TRCPDD_PATH . 'inc/crons/api_elink.php');
}


add_action('cron_api_hl7_ellkay', 'tr_pdd_cron_api_hl7_ellkay');
function tr_pdd_cron_api_hl7_ellkay()
{
	include(TRCPDD_PATH . 'inc/crons/api_hl7_ellkay.php');
}

add_action('cron_api_covid_csv_sftp', 'tr_pdd_cron_api_covid_csv_sftp');
function tr_pdd_cron_api_covid_csv_sftp()
{
	include(TRCPDD_PATH . 'inc/crons/covid_csv_sftp.php');
}

add_action('cron_add_manual_reports', 'tr_pdd_cron_add_manual_reports');
function tr_pdd_cron_add_manual_reports($batch_id = '')
{
	include(TRCPDD_PATH . 'inc/crons/add_manual_reports.php');
}


add_action('cron_demo_auto', 'tr_pdd_cron_demo_auto_update');
function tr_pdd_cron_demo_auto_update()
{
	$log = '';
	include(TRCPDD_PATH . 'inc/crons/demo_auto_update.php');
	return $log;
}


add_action('cron_api_hl7_athena_api', 'tr_pdd_cron_api_hl7_athena_api');
function tr_pdd_cron_api_hl7_athena_api()
{
	include(TRCPDD_PATH . 'inc/crons/api_athena_api.php');
}


add_action('cron_api_hl7_athena', 'tr_pdd_cron_api_hl7_athena');
function tr_pdd_cron_api_hl7_athena()
{
	include(TRCPDD_PATH . 'inc/crons/api_athena.php');
}


add_action('cron_api_solv', 'tr_pdd_cron_api_solv');
function tr_pdd_cron_api_solv()
{
	include(TRCPDD_PATH . 'inc/crons/api_solv.php');
}

add_action('cron_create_result_pdf', 'tr_pdd_cron_create_result_pdf');
function tr_pdd_cron_create_result_pdf()
{
	include(TRCPDD_PATH . 'inc/crons/create_result_pdf.php');
}

add_action('cron_sms_notify', 'tr_pdd_cron_sms_notify');
function tr_pdd_cron_sms_notify()
{
	include(TRCPDD_PATH . 'inc/crons/cron_sms_notify.php');
}


add_action('cron_api_billing_sftp', 'tr_pdd_cron_api_billing_sftp');
function tr_pdd_cron_api_billing_sftp($args1 = '')
{
	include(TRCPDD_PATH . 'inc/crons/telcor_billing_sftp.php');
}


add_action('cron_api_lifetime', 'tr_pdd_cron_api_lifetime');
function tr_pdd_cron_api_lifetime()
{
	include(TRCPDD_PATH . 'inc/crons/lifetime.php');
}


add_action('cron_api_provista', 'tr_pdd_cron_api_provista');
function tr_pdd_cron_api_provista()
{
	include(TRCPDD_PATH . 'inc/crons/api_provista.php');
}


//hourly
add_action('ci_cron_sync_wsdl_client_req', 'ci_cron_sync_wsdl_client_req');
function ci_cron_sync_wsdl_client_req()
{
	include(TRCPDD_PATH . 'inc/crons/wsdl_put_req.php');
}

add_action('ci_cron_sync_wsdl_get_ref_lab_reqs', 'ci_cron_sync_wsdl_get_ref_lab_reqs');
function ci_cron_sync_wsdl_get_ref_lab_reqs()
{
	include(TRCPDD_PATH . 'inc/crons/wsdl_get_ref_lab_reqs.php');
}


add_action('ci_cron_sync_wsdl_client_result', 'ci_cron_sync_wsdl_client_result');
function ci_cron_sync_wsdl_client_result()
{
	include(TRCPDD_PATH . 'inc/crons/wsdl_put_result.php');
}

//hourly
add_action('ci_check_upload_apis', 'tr_pdd_cron_fresh_apis', 11);
function tr_pdd_cron_fresh_apis()
{
	include(TRCPDD_PATH . 'inc/crons/fresh_apis.php');
	include(TRCPDD_PATH . 'inc/crons/check_upload_apis.php');
}

//night daily
add_action('ci_auto_upload_results', 'tr_pdd_cron_ci_auto_upload_results');
function tr_pdd_cron_ci_auto_upload_results()
{
	include(TRCPDD_PATH . 'inc/crons/auto_upload_results.php');
	include(TRCPDD_PATH . 'inc/crons/auto_upload_results_opisafe.php');
}

//hourly
add_action('ci_auto_fax_results', 'tr_pdd_cron_ci_auto_fax_results');
function tr_pdd_cron_ci_auto_fax_results()
{
	include(TRCPDD_PATH . 'inc/crons/auto_fax_results.php');
}

//single
add_action('ci_cron_clean_results', 'tr_pdd_ci_cron_clean_results');
function tr_pdd_ci_cron_clean_results($btype = '')
{
	$log = '';
	include(TRCPDD_PATH . 'inc/crons/clean_results.php');
	return $log;
}


//3:30AM
add_action('cron_receiving_batch_id', 'tr_pdd_cron_receiving_batch_id');
function tr_pdd_cron_receiving_batch_id()
{
	$log = '';
	include(TRCPDD_PATH . 'inc/crons/receiving_batch_id.php');
	return $log;
}

add_action('cron_api_meenta', 'tr_pdd_cron_api_meenta');
function tr_pdd_cron_api_meenta()
{
	include(TRCPDD_PATH . 'inc/crons/api_meenta.php');
}



add_action('cron_api_sftp_experity', 'tr_pdd_cron_api_sftp_experity');
function tr_pdd_cron_api_sftp_experity()
{
	include(TRCPDD_PATH . 'inc/crons/api_sftp_experity.php');
}

add_action('ci_today_batch_cron', 'tr_pdd_ci_today_batch_cron');
function tr_pdd_ci_today_batch_cron()
{
	$log = '';
	//include(TRCPDD_PATH.'inc/today_batch_cron.php');
	include(TRCPDD_PATH . 'inc/fix_duplicate_functions.php');
	tr_auto_fix_duplicate_patients();
	return $log;
}


add_action('ci_fix_db', 'tr_pdd_ci_fix_db');
function tr_pdd_ci_fix_db($arg1 = 0)
{

	$log = '';
	include(TRCPDD_PATH . 'parts/scripts/fix_db.php');
	return $log;
}

add_action('ci_check_pdf_card_cron', 'tr_pdd_ci_check_pdf_card_cron');
add_action('ci_check_pdf_card_single_cron', 'tr_pdd_ci_check_pdf_card_cron');
function tr_pdd_ci_check_pdf_card_cron()
{
	$log = '';
	include(TRCPDD_PATH . 'inc/crons/consumable.php');
	include(TRCPDD_PATH . 'inc/crons/check_pdf_card.php');
	return $log;
}



add_action('cron_api_evident', 'trpdd_cron_api_evident');
function trpdd_cron_api_evident()
{
	include(TRCPDD_PATH . 'inc/crons/cron_api_evident.php');
}


add_action('cron_api_md_connection', 'trpdd_cron_api_md_connection');
function trpdd_cron_api_md_connection()
{
	include(TRCPDD_PATH . 'inc/crons/api_md_connection.php');
}


add_action('ci_cron_2mins', 'trpdd_ci_cron_2mins');
function trpdd_ci_cron_2mins()
{
	include(TRCPDD_PATH . 'inc/crons/cron_2mins.php');
}


add_action('cron_api_sftp_emr', 'trpdd_ci_cron_api_sftp_emr');
function trpdd_ci_cron_api_sftp_emr()
{
	include(TRCPDD_PATH . 'inc/crons/api_sftp_emr.php');
}

add_action('cron_all_api', 'trpdd_ci_cron_all_api', 10, 3);
function trpdd_ci_cron_all_api($class, $module = '', $parms = array())
{
	include(TRCPDD_PATH . 'inc/crons/cron_all_api.php');
}
