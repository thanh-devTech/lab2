<?php
global $wpdb,$blog_id;

$mid = $_REQUEST['mid']> 0? $_REQUEST['mid'] : $mid;
$logid = $_REQUEST['logid']> 0? $_REQUEST['logid'] : $logid;
$need_cron_pverify = false;
$need_update = isset($_REQUEST['need_update']);



$bat = $wpdb->get_row("select * from ci_batches where mid = {$mid}");
$batch = (array)$bat;
$patient_id = $bat->patient_id;
$ins1 = $wpdb->get_row("select * from ci_insurances WHERE ins_ID ={$bat->insurance1}", ARRAY_A);
$pa = $wpdb->get_row("select * from ci_patients WHERE pa_ID ={$patient_id}", ARRAY_A);
$update_batch = array();
$log_pverify = '';
try{
    if( $bat && !empty($batch['patient_name']) && !empty($batch['insurance']) && $batch['insurance']!='No Insurance - Cash')
    {
        $log_pverify = array('assen'=>$batch['assen'],'mid'=>$mid);

        $payer_id = $wpdb->get_var("select payer_pverify_ID from ci_payer_list where payer_name = '{$batch['insurance']}' ");
        if(!empty($payer_id))
        {
            $depen_last = $ins1['ins_last'];
            $depen_first = $ins1['ins_first'];

            $args = array(
                'PayerID' => $payer_id,
                'ProviderNPI' => '1508115262',
                'ProviderLastName' => 'CTL',
                'ProviderPIN' => '7112',
                'SubLastName' => trim($pa['pa_last']),
                'SubFirstName' => trim($pa['pa_first']),
                'SubMemberID' => $ins1['ins_member_id'],
                'DependentLastName' => $depen_last,
                'DependentFirstName' => $depen_first,
                'DependentRelationship' => $ins1['ins_relation'],
                'PatientGender' => $pa['pa_gender'],
                'PatientDOB' => $pa['pa_dob'],
                'DOSFrom' => $batch['doc'],
                'DOSTo' => $batch['doc'],
            );
            $log_pverify['args']=$args;



            $client = new CI_PVerify_Client(false,$cron_blog_id);
            $p_rs = $client->verify_patient($args);

            $log_pverify['rsuid'] = $client->rsuid;
            $log_pverify['result'] = $client->rs;

            if(isset($_REQUEST['debug2'])){
                var_dump($p_rs);
                echo '<br><br>';
                var_dump($log_pverify);
                echo '<br><br>';
                var_dump($cron_blog_id);
                echo '<br><br>';
                var_dump($client->settings);
            }

            if($p_rs && is_object($p_rs))
            {
                $pa_update = array();
                if(!empty($p_rs->LastName)){
                    $pa_update['pa_last'] = tr_pdd_capitalized($p_rs->LastName);
                }
                if(!empty($p_rs->FirstName)){
                    $pa_update['pa_first'] = tr_pdd_capitalized($p_rs->FirstName);
                }
                if(!empty($p_rs->MiddleName)){
                    $pa_update['pa_middle'] = tr_pdd_capitalized($p_rs->MiddleName);
                }

                if(!empty($p_rs->Address1)){
                    $pa_update['pa_address'] = tr_pdd_capitalized($p_rs->Address1);
                }
                if(!empty($p_rs->City)){
                    $pa_update['pa_city'] = tr_pdd_capitalized($p_rs->City);
                }
                if(!empty($p_rs->State)){
                    $pa_update['pa_state'] = tr_pdd_capitalized($p_rs->State);
                }
                if(!empty($p_rs->Zip)){
                    $pa_update['pa_zip'] = $p_rs->Zip;
                }

                if(count($pa_update)>0 && $need_update)
                {
                    $wpdb->update('ci_patients',$pa_update,array('pa_ID'=>$patient_id));
                    $batch['batch_status'] = $update_batch['batch_status'] = 'pre-verify';
                    $log_pverify['patient'] = $pa_update;
                }

                $ins1_update= array();
                if(!empty($p_rs->MemberID)){
                    $ins1_update['ins_member_id'] = $p_rs->MemberID;
                }
                if(!empty($p_rs->GroupNumber)){
                    $ins1_update['ins_group_id'] = $p_rs->GroupNumber;
                }

                if(count($ins1_update)>0 && $need_update)
                {
                    $wpdb->update('ci_insurances',$ins1_update,array('ins_ID'=>$batch['insurance1']));

                    //check ref lab by insurance
                    if(empty($batch['ref_lab_ID']))
                    {
                        $ref_id = trpdd_get_ref_lab_by_insurance($ins1['ins_company'],$ins1['ins_state'],$batch['requesting_practice'],$batch['lab_id']);
                        if($ref_id>0)
                        {
                            $log_pverify['ref_lab_ID'] = $update_batch['ref_lab_ID'] = $ref_id;
                        }
                    }
                    $log_pverify['insurance'] = $ins1_update;
                }
            }else if(!$log_pverify['result']){
                $need_cron_pverify = true;
            }

            if(isset($_REQUEST['debug'])){
                var_dump($pa_update);
                var_dump($patient_id);
                var_dump($p_rs);exit;
            }
        }else{
            $msg =  'No payer_id for insurance: '.$batch['insurance'];
            $log_pverify['result'] = $msg;
            if(isset($_REQUEST['debug2'])){
                echo $msg;
            }
        }


    }
}catch (Exception $e)
{
    $log_pverify['result'] = $e;
    if(isset($_REQUEST['debug2'])){
        var_dump($e);
    }
}

if(!empty($log_pverify))
{
    $log_pverify['status'] = $update_batch['batch_status'];
    if(isset($is_cron))
    {
        $log_pverify['cron'] = 1;
        $log_pverify['blog_id'] = $blog_id;
    }
    //trpdd_log_form('pverify',$log_pverify);
    if($logid>0 && $need_update)
    {

        trpdd_log_form('pverify',$log_pverify,$logid,$mid);
    }
}

if($need_update && count($update_batch)>0)
{
    $wpdb->update('ci_batches',$update_batch,array('mid'=>$mid));
}

if(isset($need_return_rs))
{
    return $rs;
}else{

    header ("Content-Type:text/xml");
    echo '<?xml version="1.0" encoding="UTF-8" standalone="no" ?>';
    echo "\n";
    $xml = new CI_Data_Work_Xml();
    $rs = new stdClass();
    $rs->Request = array((object)$log_pverify['args']);
    $rs->VerifyEligibility = array($log_pverify['rsuid']);
    $rs->GetEligibilitySummaryFULL = array($log_pverify['result']);

    $x = new stdClass();
    $x->Result = $rs;

    echo $xml->objToXML($x);

    exit;
}
