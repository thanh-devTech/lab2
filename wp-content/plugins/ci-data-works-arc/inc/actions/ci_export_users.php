<?php
global $wpdb;

$download = true;
$filename = 'users.csv';

header("Pragma: public");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Content-Disposition: attachment; filename=\"$filename\";" );


$role_key = $wpdb->prefix.'capabilities';

$where = '';

if(!empty($_REQUEST['role'])){
	$role = str_replace('_','\_',$_REQUEST['role']);
	$where.= "  and m.meta_value like '%{$role}%' ";
}

$users = $wpdb->get_results("select 
					u.*,
					fn.meta_value as first_name,
					ln.meta_value as last_name,
					 doc.doc_ID,
					 doc.doc_specialty,
					 doc.doc_npi,
					 doc.doc_address,
					 doc.doc_city,
					 doc.doc_state,
					 doc.doc_zip,
					 doc.doc_phone,
					 m.meta_value as role
				from {$wpdb->users} u
				join {$wpdb->usermeta} m ON m.user_id = u.ID and m.meta_key='{$role_key}'
				left join {$wpdb->usermeta} fn ON fn.user_id = u.ID and fn.meta_key='first_name'
				left join {$wpdb->usermeta} ln ON ln.user_id = u.ID and ln.meta_key='last_name'
				left join {$wpdb->usermeta} mdoc ON mdoc.user_id = u.ID and mdoc.meta_key='_doc_id'
				left join {$wpdb->usermeta} mcol ON mcol.user_id = u.ID and mcol.meta_key='_collector_id'
				left join ci_doc doc ON doc.doc_ID = mdoc.meta_value
				left join ci_collector col ON col.col_ID = mcol.meta_value
                where 1 {$where}
                group by u.ID
                order by u.display_name asc");

$header = array(
	'First Name',
	'Last Name',
	'Email',
	'Role',
	'Office',
	'Address',
	'City',
	'Sate',
	'Zip',
	'Phone',
	'Specialty',
	'NPI',
);
$f = fopen('php://output','w');
fputcsv($f,$header);

foreach($users as $u)
{
	$first_name = trim($u->first_name);
	if(empty($first_name))
	{
		$first_name = $u->display_name;
	}
	$roles = unserialize($u->role);

	$role = '';
	foreach($roles as $role => $cap)
	{
		if($cap)break;
	}
	$offices = '';
	$address = $city = $state = $zip = $phone = '';
	if($role=='doctor'){
		$address = $u->doc_address;
		$city = $u->doc_city;
		$state = $u->doc_state;
		$zip = $u->doc_zip;
		$phone = $u->doc_phone;

		$sql_clinic = "select cli.cli_clinic from ci_clinic cli
                    JOIn ci_clinic_doc cm ON cm.cli_ID = cli.cID
                    where cm.doc_ID={$u->doc_ID} 
                    group by cli.cID order by cli.cli_clinic";

		
		$clinics = $wpdb->get_col($sql_clinic);
		$offices = implode(', ', $clinics);

	}else if($role =='collector')
	{
		$address = $u->col_location;
		$phone = $u->col_phone;
	}else if ($role == 'office_manager') {

		$sql_clinic = "select cli.cli_clinic from ci_clinic cli
                    JOIn ci_clinic_manager cm ON cm.ma_cli_ID = cli.cID
                    where cm.ma_user_ID={$u->ID} 
                    group by cli.cID order by cli.cli_clinic";

		
		$clinics = $wpdb->get_col($sql_clinic);
		$offices = implode(', ', $clinics);
	}
	$row = array(
		$first_name,
		$u->last_name,
		$u->user_email,
		$role,
		$offices,
		$address,
		$city,
		$state,
		$zip,
		$phone,
		$u->doc_specialty,
		$u->doc_npi

	);
	fputcsv($f,$row);
}
fclose($f);



exit;