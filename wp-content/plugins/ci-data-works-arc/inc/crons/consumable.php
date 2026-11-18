<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb;


$log_file = WP_CONTENT_DIR.'/log/cron_consumable.txt';

$ftime = filemtime($log_file);
if($ftime - (3600*2) > time())
{
	return;
}


$f_log = fopen( $log_file, 'a+' );
fwrite( $f_log, date_i18n( 'Y-m-d H:i:s' ) . " => start\n" );

$today  = date_i18n('Y-m-d');
$config = get_option("consumable_inv_settings",array());
$emails = explode("\n",isset($config['emails'])? $config['emails'] : '');
$admin_emails = array();

foreach($emails as $email)
{
    $email = trim($email);
    if(is_email($email))
    {
        $admin_emails[] = $email;
    }
}

if(in_array('cornel@ciwebstudio.com',$admin_emails))
{
    $admin_emails[] = 'pntrinh29@gmail.com';
}

$admin_emails = implode(',',$admin_emails);

$rows = $wpdb->get_col("select id from ci_consumable_inv 
                            where co_sent_notify = 0 
                            and ( co_qty <= co_reorder_at or ADDDATE(co_order_date, co_duration) < '{$today}' ) ");

foreach($rows as $id)
{
    $data = $wpdb->get_row("select inv.*,
                    ven.ve_name,
                    ven.ve_salesperson,
                    ven.ve_site,
                    ven.ve_email,
                    ven.ve_phone,
                    ven.ve_notes
                from ci_consumable_inv inv
                left join ci_vendors ven ON ven.id = inv.co_vendor_id
                where inv.id = {$id} ");

    $subject = 'It\'s time to reorder: '.$data->co_name.'';

    if(empty($data->co_name)){

	    $wpdb->update('ci_consumable_inv', array('co_sent_notify'=>-1),array('id'=>$id));
	    return;
    }
    $message = 'It\'s time to reorder:'."\n";
    $message .= "Name: {$data->co_name} \n";
    $message .= "Size: {$data->co_size} \n";
    $message .= "Qty: {$data->co_qty} \n";
	$message .= "ReOrder Qty: {$data->co_reorder_qty} \n";
    $message .= "Order Date: {$data->co_order_date} \n";

    $message .= "\nVendor:\n";
    $message .= "Vendor Name: {$data->ve_name} \n";
    $message .= "Sales Person: {$data->ve_salesperson} \n";
    $message .= "Vendor Site: {$data->ve_site} \n";
    $message .= "Vendor Email: {$data->ve_email} \n";
    $message .= "Vendor Phone: {$data->ve_phone} \n";
    $message .= "Vendor Notes: {$data->ve_notes} \n";

    $email = $admin_emails;
    if(is_email($data->co_email))
    {
        $email.= ','.$data->co_email;
    }


    $rs = wp_mail($email, $subject, $message);

    if($rs)
    {
        $wpdb->update('ci_consumable_inv', array('co_sent_notify'=>1),array('id'=>$id));
    }
}



fwrite( $f_log, date_i18n( 'Y-m-d H:i:s' ) . ' => end ' . "\n\n\n" );
fclose( $f_log );

if(filesize($log_file)>500000)
{
	$olddata = substr(file_get_contents($log_file),390000);
	file_put_contents($log_file,$olddata);
}