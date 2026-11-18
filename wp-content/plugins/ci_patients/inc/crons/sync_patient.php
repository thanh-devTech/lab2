<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb;
$log_file = WP_CONTENT_DIR . '/log/log_sync_patient.txt';
$filetime = filemtime($log_file);


if ($filetime > time() - 60  && !isset($_GET['cron_sync_patient'])) {
	return;
}


$db_version = get_option('TRCPPR_V');
if (TRCPPR_V != $db_version) {
	trcpp_active_plugin();
}

if (defined('OTPA_V')) {
	$db_version = get_option('OTPA_V');
	if (version_compare(number_format(OTPA_V, 3), number_format($db_version, 3), '>')) {
		$result = Otpa_Logger::install();
	}
}




$main_start_time = microtime(true);
$sync_options = get_option('tr_sync_patient_opt', array());

$filelog = fopen($log_file, 'a+');

$domain = str_replace(array('http://', 'https://', '/'), '', get_bloginfo('url'));

//check schedule
$pa_options = get_option('trpatient_settings', array());
if ($pa_options['sms_notify']) {
	$smscron = wp_get_schedule('cron_notify_sms');
	if (!$smscron) {
		wp_clear_scheduled_hook('cron_notify_sms');
		wp_schedule_event(time() + 1, '4min', 'cron_notify_sms');
	}

	$smscron = wp_get_schedule('cron_notify_sms_new_order');
	if (!$smscron) {
		wp_clear_scheduled_hook('cron_notify_sms_new_order');
		wp_schedule_event(time() + 100, '4min', 'cron_notify_sms_new_order');
	}
}

if (isset($pa_options['email_by_cron']) && $pa_options['email_by_cron']) {
	$limitemail = date('Y-m-d', time() - 86400 * 30);
	$wpdb->query("delete from ci_emails where created<'{$limitemail}'");
}
/*
//update pa_phone and pa_email
$start_time = microtime(true);
$lastid = intval($sync_options['last_pa_id']);
$sql = "select pa.pa_phone,
			pa.pa_ID,
			pa.pa_email,
			u.user_email,
			u.ID as user_id
			from ci_patients pa
			join {$wpdb->users} u ON u.patient_id = pa.pa_ID
			where 
			pa_ID > {$lastid}
			and (pa_phone = '' or pa_phone='000-000-0000' or pa_phone is null 
				or pa_email = '' or pa_email = '0@0' or pa_email is null)
			order by pa_ID asc
			limit 100";

$patients = $wpdb->get_results($sql, ARRAY_A);
foreach($patients as $patient)
{
	$update = array();
	if(empty($patient['pa_phone']) || $patient['pa_phone']=='000-000-0000'){
		$update['pa_phone'] = get_user_meta($patient['user_id'],'phone',true);
		if(empty($update['pa_phone']))
		{
			unset($update['pa_phone']);
		}
	}
	if(empty($patient['pa_email']) || $patient['pa_email']=='0@0'){
		$update['pa_email'] = $patient['user_email'];
		if(stripos($update['pa_email'],$domain)!==false)
		{
			unset($update['pa_email']);
		}
	}

	if(!empty($update))
	{
		$wpdb->update('ci_patients',$update,array('pa_ID'=>$patient['pa_ID']));
	}
	$sync_options['last_pa_id'] = $patient['pa_ID'];
}

if($sync_options['lasttimerpl'] < time() - 999600) {
	$wpdb->query( " update {$wpdb->usermeta} 
			set meta_value = REPLACE(
					REPLACE(
						REPLACE(
							REPLACE(
								REPLACE(meta_value,'-','')
								,'.','')
							,'(','')
						,')','')
					,' ','')
                    where meta_key='phone' 
                    and (meta_value like '%-%' 
                            or meta_value like '%\.%'
                            or meta_value like '%(%'
                            or meta_value like '% %'
                            ) " );


	fwrite( $filelog, date_i18n( "Y-m-d H:i:s" ) . ': fixed phone&email: ' . ( microtime( true ) - $start_time ) . "\n\n" );
	$sync_options['lasttimerpl'] = time();
}*/

//fix duplicate users
/*
$start_time = microtime(true);
$rs = $wpdb->get_results(
	"select u.ID,
	        p.*,
			count(*) as c 
			from {$wpdb->users} u
			 JOIN ci_patients p ON u.patient_id = p.pa_ID
			where patient_id>0 
			group by patient_id 
			order by c desc,u.ID desc
			 limit 1000");

foreach($rs as $r)
{
	if($r->c<2)continue;

	$patient_id = $r->pa_ID;
	$phone = str_replace(array('-',' ','.','(',')','+1'),'',$r->pa_phone);


	$main_user_id = 0;
	$email = '';
	if(is_email($r->pa_email)){
		$email = $r->pa_email;
	}
	fwrite($filelog, date_i18n("Y-m-d H:i:s").': fix patient #'.$patient_id."\n");
	//find main user
	$count_users = 0;

	$users = $wpdb->get_results("select * from {$wpdb->users} where patient_id = {$patient_id}");

	foreach($users as $i => $user)
	{
		$phonen = get_user_meta($user->ID,'phone',true);
		$phonen = str_replace(array('-',' ','.','(',')','+1'),'',$phonen);
		$user->phone = $phonen;

		$u = get_user_by('id',$user->ID);
		if($u->roles[0]!='patient'){
			unset($users[$i]);
			continue;
		}
		$count_users++;

		if($phone == $phonen)
		{
			$main_user_id = $user->ID;

		}
	}


	if($main_user_id>0 && $count_users>=2)
	{
		fwrite($filelog, date_i18n("Y-m-d H:i:s").': #'.$patient_id.' =>main user:'.$main_user_id.'=>phone:'.$phone."\n");
		foreach($users as $user)
		{
			if($user->ID != $main_user_id )
			{
				$wpdb->delete($wpdb->users,array('ID'=>$user->ID));
				$wpdb->delete($wpdb->usermeta,array('user_id'=>$user->ID));

				$wpdb->update($wpdb->postmeta,array('meta_value'=>$main_user_id),array('meta_value'=>$user->ID,'meta_key'=>'_customer_user'));

				fwrite($filelog, date_i18n("Y-m-d H:i:s").': #'.$patient_id.' =>delete user:'.$user->ID.' =>phone: '.$user->phone."\n");
			}
		}

		if(!empty($email))
		{
			$wpdb->update($wpdb->users,array('user_email'=>$email),array('ID'=>$main_user_id));
		}
	}

}
fwrite($filelog, date_i18n("Y-m-d H:i:s").': fixed duplicated: '.(microtime(true) - $start_time)."\n\n");

*/

//fix duplicate users
$start_time = microtime(true);
$need_fix_dup_users = false;
if ($need_fix_dup_users) {
	$start_time = microtime(true);
	$wpdb->query("update {$wpdb->users} set user_email = '" . time() . '@' . "' where user_email = '' limit 1");

	$rs = $wpdb->get_results("select *,count(*) as c from `wp_users` group by user_email order by c desc limit 1000");
	foreach ($rs as $r) {
		if ($r->c < 2) {
			continue;
		}

		$users      = $wpdb->get_results("select u.*,
					m.meta_value as phone
	                from wp_users u 
					join wp_usermeta m ON u.ID = m.user_id 
								and m.meta_key='wp_2_capabilities' and  m.meta_value like '%patient%'
					left join wp_usermeta m ON u.ID = m.user_id and m.meta_key='phone' 
					where user_email = '{$r->user_email}' order by ID desc");
		$main_user  = false;
		$main_phone = '';
		if (count($users) < 2) {
			continue;
		}

		foreach ($users as $user) {
			$user->phone = str_replace('+1', '', $user->phone);

			if ($main_user == false) {
				$main_user  = $user;
				$main_phone = $user->phone;
			}
		}
		if (! $main_user) {
			continue;
		}
		var_dump($main_user);
		echo '<br><br>';

		foreach ($users as $user) {
			$user_phone = str_replace('+1', '', $user->phone);
			if ($user->ID == $main_user->ID) {
				continue;
			}
			if ($main_phone != $user_phone) {
				$wpdb->update('wp_users', array(
					'user_login' => microtime(true),
					'user_email' => microtime(true) . '@',
				), array('ID' => $user->ID));

				echo 'updated:userid: ' . $user->ID;
				var_dump($user);
				echo '<br><br>';
				continue;
			}
			echo 'delete:userid: ' . $user->ID;
			var_dump($user);
			echo '<br><br>';
			$wpdb->delete('wp_users', array('ID' => $user->ID));
			$wpdb->delete('wp_usermeta', array('user_id' => $user->ID));

			$wpdb->update($wpdb->postmeta, array('meta_value' => $main_user->ID), array(
				'meta_value' => $user->ID,
				'meta_key'   => '_customer_user'
			));
		}
	}
}

//end fix duplicate users


fwrite($filelog, date_i18n("Y-m-d H:i:s") . ': fix duplicate users: ' . (microtime(true) - $start_time) . "\n\n");



$start_time = microtime(true);

//update order userid
$rs = $wpdb->get_results("select * from {$wpdb->postmeta} 
				where meta_key='_customer_user' 
				and meta_value='0' 
				order by post_id desc
				limit 1000");
foreach ($rs as $r) {
	$billing_email = get_post_meta($r->post_id, '_billing_email', true);
	if (!empty($billing_email)) {
		$user = get_user_by('email', $billing_email);
		if ($user && $user->ID > 0) {
			update_post_meta($r->post_id, '_customer_user', $user->ID);
		}
	}
}


fwrite($filelog, date_i18n("Y-m-d H:i:s") . ': updated order id: ' . (microtime(true) - $start_time) . "\n\n");

$start_time = microtime(true);

//update clinic id for patient
$pids = $wpdb->get_col("select pa_ID from ci_patients pa
					where pa.pa_clinic_ID is null 
						or pa.pa_clinic_ID =0
						limit 1000");
foreach ($pids as $pid) {
	//find clinic id
	foreach (tr_pdd_get_batch_types() as $btype) {
		$tbl = tr_pdd_get_table_by_btype($btype);
		$cid = $wpdb->get_var("select cli_ID from {$tbl} where patient_id = {$pid}");
		if ($cid > 0) {
			$wpdb->update('ci_patients', array('pa_clinic_ID' => $cid), array('pa_ID' => $pid));
			break;
		}
	}
}


fwrite($filelog, date_i18n("Y-m-d H:i:s") . ': fixed clinic id for patient: ' . (microtime(true) - $start_time) . "\n\n");

$start_time = microtime(true);

//update patient_id for log_sms
$cache_phone_pid = array();
$rs = $wpdb->get_results("select assen,
                    phone,
                    created,
                    sendtype
                    from tr_log_sms 
					where patient_id is null
					order by created desc
					limit 1000");
foreach ($rs as $r) {
	//find $pid

	$pid = 0;
	if (isset($cache_phone_pid[$r->phone])) {
		continue;
	}

	if (!empty($r->assen)) {
		foreach (tr_pdd_get_batch_types() as $btype) {
			$tbl = tr_pdd_get_table_by_btype($btype);
			$pid = $wpdb->get_var("select patient_id from {$tbl} where assen = '{$r->assen}'");
			if ($pid > 0) {
				break;
			}
		}
	}



	if ($pid == 0) {
		$phones = implode("','", tr_patient_get_phone_arr($r->phone));
		if (!empty($phones)) {
			$pid = $wpdb->get_var("select pa_ID from ci_patients where pa_phone in ('{$phones}')");

			if ($pid == 0) {
				$uid = $wpdb->get_var("select user_id from {$wpdb->usermeta} where meta_key='phone' and meta_value in ('{$phones}') ");
				if ($uid > 0) {
					$pid = $wpdb->get_var("select patient_id from {$wpdb->users} where ID = {$uid} ");
				}
			}
		}
	}


	$pid = intval($pid);
	$cache_phone_pid[$r->phone] = $pid;
	$wpdb->query("update tr_log_sms set patient_id = {$pid} where phone = '{$r->phone}' and patient_id is null");
	/*
	$wpdb->update('tr_log_sms',array('patient_id'=>$pid),
		array(
			'phone'=>$r->phone,
			'created'=>$r->created,
			'sendtype'=>$r->sendtype,
		));*/
}

fwrite($filelog, date_i18n("Y-m-d H:i:s") . ': fixed patientid for sms: ' . (microtime(true) - $start_time) . "\n\n");
$start_time = microtime(true);

$wpdb->query("update tr_log_sms set sendtype='v' where sendtype='' and message like '%Verification%'");
$wpdb->query("update tr_log_sms set sendtype='n' where sendtype='' and message like '%Welcome%'");

fwrite($filelog, date_i18n("Y-m-d H:i:s") . ': update verify & welcome: ' . (microtime(true) - $start_time) . "\n\n");



//update patient level =0
$rs = $wpdb->get_col("select u.ID from {$wpdb->users} u
			join {$wpdb->usermeta} m1 ON m1.user_id = u.ID and m1.meta_key='wp_2_user_level'
			join {$wpdb->usermeta} mr ON mr.user_id = u.ID and mr.meta_key='wp_2_capabilities'
			where m1.meta_value = '1' 
			and mr.meta_value like '%patient%'
			limit 1000");
foreach ($rs as $uid) {
	$wpdb->query("update {$wpdb->usermeta} set meta_value='0' where user_id = {$uid} and meta_key='wp_2_user_level'");
}



fwrite($filelog, date_i18n("Y-m-d H:i:s") . ': end : ' . (microtime(true) - $main_start_time) . "\n\n\n\n\n\n");
fclose($filelog);

update_option('tr_sync_patient_opt', $sync_options);

if (filesize($log_file) > 100000) {
	$olddata = substr(file_get_contents($log_file), 90000);
	file_put_contents($log_file, $olddata);
}


if (isset($_GET['refresh'])) {
	echo '<meta http-equiv="refresh" content="1"/>';
}
