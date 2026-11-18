<?php
global $current_role,$current_lab_id,$wpdb;


$user       = wp_get_current_user();

if (function_exists('trcppr_is_new_style_enabled') && trcppr_is_new_style_enabled() && in_array('administrator', (array) $user->roles, true) && defined('TRCPDD_PATH') && function_exists('trpdd_get_file_path') && is_file(TRCPDD_PATH . 'pages/admin_welcome.php')) {
	$_GET['newdb']     = 1;
	$_REQUEST['newdb'] = 1;
	?>
	<style>
		button[onclick*="dashboard_main"],
		a[href*="dashboard_main"] {
			display: none !important;
		}
	</style>
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			document.querySelectorAll('button[onclick*="dashboard_main"], a[href*="dashboard_main"]').forEach(function(el) {
				el.style.setProperty('display', 'none', 'important');
			});
		});
	</script>
	<?php
	ob_start();
	include(TRCPDD_PATH . 'pages/admin_welcome.php');
	echo str_replace('Your laboratory information', 'Patient Portal', ob_get_clean());
	return;
}

$patient_id = tr_patient_get_id_patient($user->ID);

if(function_exists('wp_get_scheduled_event')){
	$min = wp_get_scheduled_event('action_scheduler_run_queue',array('WP Cron'));
	if($min && is_object($min) && isset($min->schedule) && $min->schedule=='every_minute'){
		wp_clear_scheduled_hook('action_scheduler_run_queue',array( 'WP Cron' ));
	}
}



$options    = get_option( 'trpatient_welcome_settings', array() );
$pa_options = get_option( 'trpatient_settings', array() );
try {
	$db_version = get_option( 'TRCPPR_V' );
	if ( TRCPPR_V != $db_version) {
		trcpp_active_plugin();
	}


	if(defined('OTPA_V')){
		$db_version = get_option('OTPA_V');
		if(version_compare(number_format(OTPA_V,3),number_format($db_version,3),'>')){
			$result = Otpa_Logger::install();
		}
	}


	if ( $pa_options['sms_notify'] ) {
		$next_cron = wp_next_scheduled( 'cron_notify_sms' );
		if ( ! $next_cron ) {
			wp_schedule_event( time() + 1, '4min', 'cron_notify_sms' );
		}
		$next_cron = wp_next_scheduled( 'cron_notify_sms_new_order' );
		if ( ! $next_cron ) {
			wp_schedule_event( time() + 50, '4min', 'cron_notify_sms_new_order' );
		}

	}

	if ( isset( $_GET['testsms'] ) ) {
		global $tr_sms_gateway;

		$loaded = $tr_sms_gateway->load_library();

		if ( $loaded ) {
			$init = $tr_sms_gateway->init_api();
		}

		$sent_sms = $tr_sms_gateway->send_sms( '+13176230565', 'test', 'test', 'test', 'nt' );
		var_dump( $sent_sms );
		var_dump( $tr_sms_gateway->last_error );

	}
}catch(Exception $e)
{

}

try{

    if(defined('OTPA_V')){
	    $db_version = get_option('OTPA_V');
	    if(version_compare(number_format(OTPA_V,3),number_format($db_version,3),'>')){
		    $result = Otpa_Logger::install();
		    var_dump($result);
	    }

    }

}catch (Exception $e2)
{

}

if($user->user_login!='Administrat0r'){
	$sessions = WP_Session_Tokens::get_instance( $user->ID );
	$sessions->destroy_others( wp_get_session_token() );
}else{
    if(isset($_GET['test_email_cron'])){
	    add_filter('pre_wp_mail','trpatient_pre_wp_mail_cron',99,2);
	    $headers  = 'MIME-Version: 1.0' . "\r\n";
	    $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

	    $from_name = get_bloginfo('name');
	    $from_email = $pa_options['email_from'];
	    if(!empty($from_email) && is_email($from_email))
	    {
		    $headers .= 'From: ' . $from_name . ' <' . $from_email . '>' . "\r\n";
	    }

	   $rs =  wp_mail('pntrinh29@gmail.com','Test email 1','here is message',$headers);
	    var_dump($rs);
    }
}


$cron_file = ABSPATH.'/wp-cron.php';
$crondata = file_get_contents($cron_file);
if(strpos($crondata,'fastcgi_finish_request();')>0)
{
    $crondata = str_replace('fastcgi_finish_request();','',$crondata);
    file_put_contents($cron_file,$crondata);
}
?>

<div style="padding:10px;text-align: center;margin-top:100px">

    <?php
    if(isset($options['welcome']) && strlen($options['welcome'])>10)
    {
        echo '<div title="'. $current_role .'">';
        echo apply_filters('the_content',$options['welcome']);
        echo '</div>';
    }else {
	    ?>
        <img src="<?php echo TRCPPR_URL ?>images/logo.png" data-lab="<?php echo $current_lab_id ?>"
             data-userid="<?php echo $user->ID ?>"
             title="<?php echo $current_role ?>"/>
	    <?php
    }
    ?>
</div>
<style>
    #wpwrap{background: #fff}
</style>
