<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb,$report,$log;
//$is_debug = true;
$limit = 5;

$where = " re.estatus='C' ";

$where.= " and ( ba.custom_profile!='Use Custom Profile' or (ba.custom_profile='Use Custom Profile' and cp.cus_ID>0) )";
$where.= " and (re.opi_id = '') ";

$sql = "select re.id,opi.* from ci_reports re
        JOIN ci_batches ba ON ba.assen = re.patient_id
        left Join ci_custom_providers cp ON cp.doc_ID = ba.doc_ID
        join ci_opisafe_key_relationships kc on (ba.cli_ID = kc.target_ID and kc.cType='C') or (ba.doc_ID = kc.target_ID and kc.cType='D')
        join ci_opisafe_keys opi ON opi.opi_ID = kc.opi_ID
        where {$where} 
        group by ba.mid
        order by re.id asc limit {$limit}";

$reports = $wpdb->get_results($sql,ARRAY_A);
if(isset($_GET['debug'])){
    echo $sql;
    echo '<br><br>';
    $debug = true;
}
else if(!isset($_GET['test'])){
    //return;
}
if(count($reports)==0)
{
    echo 'notfound';
    return;
}



$log = array();
$log['reports'] = $reports;
$log['start'] = date('Y-m-d H:i:s');
$time_start = microtime(true);

if(count($reports)>0)
{
    wp_clear_scheduled_hook('ci_auto_upload_results',array(0));
    wp_schedule_single_event(time()+60*2, 'ci_auto_upload_results',array(0));
    $log['next'] =1;
}

foreach ($reports as $row) {
    $re_id = $row['id'];

    $opi = new CI_Data_OpiSafe($row['opi_key'],$row['opi_secret'],$row['opi_labkey']);
    //$opi->debug = 'all';

    $rs = $opi->send_report($re_id);

    $log['rs'][] = $rs;

    if($debug)
    {
        echo '<br>';
        echo $re_id;
        var_dump($rs);echo '<br>';echo '<br>';
    }

    if($rs['status']==='no_file')
    {
        if($debug)
        {
            echo 'no file report';
            continue;
        }
    }
    else if($rs===null || $rs['status'] =='')
    {
        if($debug)
        {
            echo 'fail';
        }
        $wpdb->update('ci_reports',array('opi_id'=>'fail'),array('id'=>$re_id));
        continue;
    }
    else if($rs['status']==='no_npi')
    {
        if($debug)
        {
            echo 'no_npi';
        }
        $wpdb->update('ci_reports',array('opi_id'=>'no_npi'),array('id'=>$re_id));
        continue;
    }
    else if($rs['status']==='no_patients'){
        exit;
    }else if($rs['status'] =='ok'){
        if($debug)
        {
            echo 'ok';echo '<br>';echo '<br>';
        }
    }

}


$log['runtime'] = microtime(true) - $time_start;

trpdd_log_form('auto_opisafe',$log);

return $log;