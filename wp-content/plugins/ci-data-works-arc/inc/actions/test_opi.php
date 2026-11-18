<?php

global $wpdb;


$key = $_REQUEST['key'];
$secret = $_REQUEST['secret'];
$labid = $_REQUEST['labid'];
$type = $_REQUEST['type'];


if($type=='sync'){

    $id = $_REQUEST['id'];
    $where = " where re.estatus='C' and kc.opi_ID = {$id}";
    $where.= " and ( ba.custom_profile!='Use Custom Profile' or (ba.custom_profile='Use Custom Profile' and cp.cus_ID>0) )";

    $sql = "select count(re.id) from ci_reports re
        JOIN ci_batches ba ON ba.assen = re.patient_id
        left Join ci_custom_providers cp ON cp.doc_ID = ba.doc_ID
        join ci_opisafe_key_relationships kc on (ba.cli_ID = kc.target_ID and kc.cType='C') or (ba.doc_ID = kc.target_ID and kc.cType='D')  ";

    $total = $wpdb->get_var($sql.$where);

    $where.= " and (re.opi_id = '' or re.opi_id is null) ";

    $left = $wpdb->get_var($sql.$where);

    if(isset($_GET['debug'])){
        echo $sql.$where;
    }
    if($left>0)
    {
        wp_clear_scheduled_hook('ci_auto_upload_results',array(0));
        wp_schedule_single_event(time()+60*2, 'ci_auto_upload_results',array(0));
    }
    ?>
    <div>
        <ul>
            <li>
                Total: <?php echo intval($total)?>
            </li>
            <li>
                Left: <?php echo intval($left)?>
            </li>
        </ul>
    </div>
<?php
    exit;
    
}


$return = array('status'=>'error','html'=>'<br>');
try {
    $opi = new CI_Opisafe($key, $secret, $labid);
    //$opi->debug = true;
    if($type=='patients'){
        $rs = $opi->patients();
    }else if($type=='providers'){
        $rs = $opi->providers();
        if($rs)
        {
            foreach($rs['providers'] as &$provider)
            {
                $provider['doc_ID'] = $wpdb->get_var("select doc_ID from ci_doc where doc_npi = '{$provider['npi']}'");
                $provider['avalable_in_db'] = $wpdb->get_var("
                                        select count(re.id) from ci_reports re
                                        JOIN ci_batches ba ON ba.assen = re.patient_id
                                        join ci_doc doc On doc.doc_ID = ba.doc_ID
                                        left Join ci_custom_providers cp ON cp.doc_ID = ba.doc_ID
                                        where doc.doc_npi = '{$provider['npi']}' 
                                        and re.estatus = 'C'
                                        and ( ba.custom_profile!='Use Custom Profile' or (ba.custom_profile='Use Custom Profile' and cp.cus_ID>0) )");
            }
        }
    }
    else{
        $rs = $opi->ping();
    }
    if(!isset($rs['error'])){
        $return['status'] = 'ok';
        ob_start();

        if($rs)
        {
            print_r($rs);
        }else{
            echo 'Response Message: ';
            print_r($opi->last_body);
        }

        $return['html'].= '<pre>'.ob_get_clean().'</pre>';
    }else{
        $return['html'].= $rs['error'];
    }
    $return['rs'] = $rs;

}catch (Exception $e)
{
    $return['html'] = $e;
}

wp_send_json($return);