<?php
global $wpdb;

if(!user_can(get_current_user_id(),'administrator')){
	exit;
}

$filename = 'users.csv';

header("Pragma: public");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Content-Disposition: attachment; filename=\"$filename\";" );
$f = fopen('php://output','w');
$sql = "select pa.*, u.user_email from ci_patients pa
	join {$wpdb->users} u ON u.patient_id = pa.pa_ID
	where pa_dob <'1994-11-21'
	group by pa.pa_ID";
$rs = $wpdb->get_results($sql);
$headers = array(
	'First Name','Last Name','DOB','Address','City','State','Zip','Phone','Email'
);
fputcsv($f,$headers);
foreach($rs as $r)
{
	if(empty($r->pa_email))
	{
		$r->pa_email = $r->user_email;
	}
	$row = array(
		$r->pa_first,
		$r->pa_last,
		$r->pa_dob,
		$r->pa_address,
		$r->pa_city,
		$r->pa_state,
		$r->pa_zip,
		$r->pa_phone,
		$r->pa_email,
	);
	fputcsv($f,$row);
}
exit;