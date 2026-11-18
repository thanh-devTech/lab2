<?php

function tr_auto_fix_duplicate_patients()
{
    global $wpdb;

    $groupby = " group by pa_last,pa_first,pa_dob ,pa_gender, pa_phone";

    $sql = "select * from (select *,count(*) as c 
                        from ci_patients 
                        where pa_first !='' and pa_last!=''
                        {$groupby} order by c desc,pa_dob) rs 
                         where c > 1 and pa_dob > '1000-01-01' ";
    $groups = $wpdb->get_results($sql, ARRAY_A);

    if(isset($_GET['debug1'])){
        var_dump($sql);echo '<br><br>';
        var_dump($groups);
        exit;
    }


    $type = 'dob';
    $ssnnotin = array('00','-','0','0.','000-00-00','000-000-0000','000-00-000','000-00-0000','000-00-00000','000-00-0000`','na','n/a');
    if(count($groups)==0)
    {
        $groupby = " group by pa_last,pa_first,pa_ssn ";

        $sql = "select * from (select *,count(*) as c 
                        from ci_patients 
                        where pa_first !='' and pa_last!=''
                        {$groupby} order by c desc,pa_ssn) rs 
                         where c > 1 and pa_ssn !=''  limit 150";
        $groups = $wpdb->get_results($sql, ARRAY_A);
        $type = 'ssn';

        if(isset($_GET['debug11'])){
            var_dump($sql);echo '<br><br>';
            var_dump($groups);
            exit;
        }
    }

    $count = 0;
    $count_success = 0;
    foreach($groups as $group)
    {
        if($group['c']< 2)continue;
        $count++;

        $group['pa_ssn'] = trim(strtolower($group['pa_ssn']));

        if($type=='ssn' && !empty($group['pa_ssn']) && !in_array($group['pa_ssn'],$ssnnotin) ){
            $where = $wpdb->prepare(" pa.pa_last = %s 
                                        and pa.pa_first = %s 
                                        and pa.pa_ssn=%s",
                                        $group['pa_last'],
                                        $group['pa_first'],
                                        $group['pa_ssn']);
        }else{
            if(empty($group['pa_dob']))continue;

            $where = $wpdb->prepare(" 
                                        (
                                            (pa.pa_last = %s and pa.pa_first = %s) 
                                            or 
                                            (pa.pa_first = %s and pa.pa_last = %s)
                                        )
                                        and pa.pa_dob=%s ",
                                        $group['pa_last'],
                                        $group['pa_first'],
							            $group['pa_last'],
							            $group['pa_first'],
                                        $group['pa_dob']);

            $where.= $wpdb->prepare(" and pa.pa_gender = %s",$group['pa_gender']);
        }

        $patients = $wpdb->get_results("select pa.*,
                replace( replace(pa_ssn, 'N/A',''),'NA','') as ssn,
                (select batch_created from ci_batches ba where ba.patient_id = pa.pa_ID order by ba.batch_created desc limit 1) as batch_created 
                from ci_patients pa
                where {$where} 
                group by pa.pa_ID 
                order by pa.pa_dob desc, ssn desc, pa.pa_ssn desc,pa.pa_mrn desc, pa.pa_address desc,batch_created desc
                limit 200", ARRAY_A);

        if(isset($_GET['debug2'])){
            var_dump($patients);exit;
        }

        $main_patient = false;
        $remove_ids = array();
        foreach($patients as $patient)
        {

            $patient['pa_ssn'] = trim(strtolower($patient['pa_ssn']));
            $patient['pa_phone'] = trim(strtolower($patient['pa_phone']));
            $patient['pa_phone'] = str_replace(array(' ',')','(','-','+1',','),'',$patient['pa_phone']);

            if($patient['pa_phone']=='000-000-0000'){
                continue;
            }

            if(strlen($patient['pa_phone'])<=8)
            {
	            $patient['pa_phone'] = '';
            }

            if(in_array($patient['pa_ssn'],$ssnnotin))
            {
                $patient['pa_ssn'] = '';
            }

            if(in_array($patient['pa_phone'],$ssnnotin))
            {
                $patient['pa_phone'] = '';
            }



            $patient['pa_address'] = str_ireplace('  ',' ',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('.','',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('vining','vinning',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('sout st','south st',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('street','st',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('W South','West South',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('oak dr','oak',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('s d st','south d st',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('river rd','river',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('ROAD','rd',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('rd apt 202','rd apt 2',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('apt #','rd apt ',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('dyar st #','dyar st apt ',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('st apt','st',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('mandalay cit','mardalay circle',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('brentwood apt','brentwood st',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('old higway','old highway',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('canterbury dr','canterbury',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('rd apt a','rd',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('n washington','north washington',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('quail ridge rd','quail ridge',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('apt#','apt ',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('ln ave','lane ave',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('e jefferson','east jefferson',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('e townline','townline',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('16 rd','16',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('prevost st','prevost',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('state st k','state st',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('state st129','state st',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('hillhurst drive','hillhurst dr',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('indianna st','indiana st',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('elkland ln po box 502','elkland lane',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('s isabell rd','s isabella rd',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('baltimore st','biltmore st',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('#814','814',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('blvd apt','blvd',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('pallister apt 904','pallister st 904',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('shawno st','shawano st',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('n deer run trl','n deer run trail',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('northlawn st','northlawn',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace(' ontrose',' montrose',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('e groce','e grove',$patient['pa_address']);

            $patient['pa_address'] = str_ireplace('apt18','apt 18',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('vanburen st','vanburen',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('trl rd','trail dr',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('w cerand','west cerand',$patient['pa_address']);

            $patient['pa_address'] = str_ireplace('garnger st','granger st',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('37 south','37 s',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('apt 2','apt 1',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('grayson drive','grayson dr',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('knaoo rd','knapp rd',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('ockert ln','ockert lane',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('nautical ln','nautical lane',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('harrison woods lane','harrison wood lane',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('s horst ct','south horst',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('e patterson','e paterson',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('petersham ln','petersham lane',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('littlefield st','petersham lane',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('shady lane drive','shady lane dr',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('benett dr apt 6','benett drive',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace(',','',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('blvd apt','blvd',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('blvd 4','blvd',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('west st 2','w st 2',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('saratogas st','saratoga st',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('geddes rd','geddes',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('west main','w main',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('n crown stret','n crown st',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('meyers rd apt','meyers rd',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('woodcreek rd apt','woodcreek rd',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('merry lane','merry ln',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('mile rd','mile',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('vantroba drive','vantroba dr',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('hazelwood st','hazelwood',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('cambridge apt 402','cambridge',$patient['pa_address']);

            $patient['pa_address'] = str_ireplace('malburg way dr','malbury way dr',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('e riverside dr','e riverside dr',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('onagon trail','onagon trl',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('marquette dr','marquette',$patient['pa_address']);


            $patient['pa_address'] = str_ireplace('  ',' ',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('south ellsworth rd','s ellsworth rd',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('ct apt 19','ct apt',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('lake apt q-320','lake',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('s broas st','s brods',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('n main st 22','main st',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('timberridge circle','timberridge cir',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('e tuttle rd lot','e tuttle lot',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('n jones rd','n jones',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('wildwood apt ','wildwood ',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('wildwood #','wildwood ',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('w main rd','w main',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('meyers rd 106','meyers rd',$patient['pa_address']);


            $patient['pa_address'] = str_ireplace('montcalm','montclair',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('avenue','ave',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('liesle rd russell wood','liesle rd',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('harding st','harding',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('p556','556',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('hartwell st','hartwell',$patient['pa_address']);

            $patient['pa_address'] = str_ireplace('fwy apt','apt',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('ave apt 124','ave apt 12',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('w willis','willis',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('rd unite 4a','unit 4 a',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('river dr','river',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('kingsville st 101','kingsville',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('fieldling','fielding',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('lane','ln',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace(' st','',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('linwood elmhurst home','elmhurst',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace(' ave','',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('drive','dr',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace(' e ',' ',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace(' ln','',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace(' dr','',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace(' dt','',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace(' creek trail','creek trl',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('Marriner','Manor',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('south','s',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace(' unit 416','',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('d 2576','d',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('g 523','g',$patient['pa_address']);
            $patient['pa_address'] = str_ireplace('connelly unit 347','connelly',$patient['pa_address']);

            $patient['pa_address'] = trim(strtolower($patient['pa_address']));


            
            if($main_patient==false)
            {
                $main_patient = $patient;


            }else{

                $can_remove = true;

				echo $patient['pa_last'].', '.$patient['pa_first'].' ('.$patient['pa_dob'].')=>';
				echo 'phone:'.$patient['pa_phone'].'=>';

                if(!empty($patient['pa_ssn']) && $patient['pa_ssn']!=$main_patient['pa_ssn']){
                    $can_remove = false;
	                echo 'by ssn => ';
                    var_dump($patient['pa_ssn']);
                    var_dump($main_patient['pa_ssn']);
                }

                if(!empty($patient['pa_phone']) && $patient['pa_phone']!=$main_patient['pa_phone']){
                    $can_remove = false;
	                echo 'by phone => ';
                    var_dump($patient['pa_phone']);
                    var_dump($main_patient['pa_phone']);
                }



                if(!empty($patient['pa_address']) && $patient['pa_address']!=$main_patient['pa_address']){
                    $can_remove = false;
	                echo 'by address => ';
                    var_dump($patient['pa_address']);
                    var_dump($main_patient['pa_address']);

                }
                if(!empty($patient['pa_ssn']) && $patient['pa_ssn']==$main_patient['pa_ssn']){
                    $can_remove = true;
                }

                if($can_remove)
                {
                    $remove_ids[] = $patient['pa_ID'];


                    $rs = tr_move_patient_and_remove($main_patient['pa_ID'],$patient['pa_ID']);
                    if($rs)
                    {
                        $count_success++;
                    }
                }
	            echo '<br><br>';

            }
        }

    }

    if(isset($_GET['debug'])){
        echo '$count_success:';var_dump($count_success);
        echo "<br><br>";
    }

    if($count_success > 5)
    {
        wp_clear_scheduled_hook('ci_today_batch_cron',array('remove_duplicate_patient'));
        wp_schedule_single_event(time()+100,'ci_today_batch_cron',array('remove_duplicate_patient'));
    }
}

function tr_valid_ssn($ssn)
{
    if(strlen($ssn)<5){
        return false;
    }
    if(stripos($ssn,'xx')!==false){
        return false;
    }

    if(stripos($ssn,'000-')!==false)
    {
        return false;
    }


    return true;
}

function tr_move_patient_and_remove($m_id,$s_id)
{
    global $wpdb;

    if($m_id == $s_id || empty($m_id))
    {
        if(isset($_GET['debug'])){
            echo 'no m_id';exit;
        }
        return;
    }

    $main_patient = $wpdb->get_row("select * from ci_patients where pa_ID = {$m_id}", ARRAY_A);
    $remove_patient = $wpdb->get_row("select * from ci_patients where pa_ID = {$s_id}", ARRAY_A);

    if(!$main_patient)
    {
        return;
    }

    $update = array();
    if(!tr_valid_ssn($main_patient['pa_ssn']) && tr_valid_ssn($remove_patient['pa_ssn']))
    {
        $update['pa_ssn'] = $remove_patient['pa_ssn'];
    }

    if(empty($main_patient['pa_phone']) && !empty($remove_patient['pa_phone']))
    {
        $update['pa_phone'] = $remove_patient['pa_phone'];
    }

    if((empty($main_patient['pa_dob']) || $main_patient['pa_dob']=='0000-00-00') && !empty($remove_patient['pa_dob']))
    {
        $update['pa_dob'] = $remove_patient['pa_dob'];
    }

    if(!empty($update))
    {
        $wpdb->update('ci_patients',$update,array('pa_ID' => $main_patient['pa_ID']));
    }

    $can_remove = true;
    $both_ssn_valid = tr_valid_ssn($main_patient['pa_ssn']) && tr_valid_ssn($remove_patient['pa_ssn']);

    if($both_ssn_valid && $main_patient['pa_ssn']== $remove_patient['pa_ssn']){
        $can_remove = true;
    }else {
        if($both_ssn_valid && $main_patient['pa_ssn']!= $remove_patient['pa_ssn']){
            $can_remove = false;
        }
    }

    if(!$can_remove && ((!empty($main_patient['pa_phone']) && $main_patient['pa_phone']==$remove_patient['pa_phone'])
            || ($main_patient['pa_dob']==$remove_patient['pa_dob'] && $main_patient['pa_dob']> '1000-01-01' ) ) )
    {
        $can_remove = true;
    }

    if(!$can_remove){
        if(isset($_GET['debug'])){
            var_dump($main_patient);
            echo '<br><br>';
            var_dump($remove_patient);
            echo 'cant_remove';exit;
        }
        return false;
    }


    if(function_exists('tr_pdd_get_batch_types')){
        foreach(tr_pdd_get_batch_types() as $type)
        {
            $table = tr_pdd_get_table_by_btype($type);
            $wpdb->update($table,array('patient_id'=>$m_id),array('patient_id'=>$s_id));
        }
    }else{
        $wpdb->update('ci_batches',array('patient_id'=>$m_id),array('patient_id'=>$s_id));
        $wpdb->update('ci_batches_blood',array('patient_id'=>$m_id),array('patient_id'=>$s_id));
        $wpdb->update('ci_batches_dna',array('patient_id'=>$m_id),array('patient_id'=>$s_id));
        $wpdb->update('ci_batches_allergy',array('patient_id'=>$m_id),array('patient_id'=>$s_id));
    }

    
    $wpdb->update('ci_insurances',array('pa_ID'=>$m_id),array('pa_ID'=>$s_id));
    $wpdb->update('ci_patients_history',array('pa_ID'=>$m_id),array('pa_ID'=>$s_id));
	$wpdb->update($wpdb->usermeta, array('meta_value'=>$m_id),array('meta_value'=>$s_id,'meta_key'=>'_patient_id'));
	$wpdb->update($wpdb->users, array('patient_id'=>$m_id),array('patient_id'=>$s_id));

	$wpdb->update('wp_2_postmeta', array('meta_value'=>$m_id),array('meta_value'=>$s_id,'meta_key'=>'_patient_id'));


    $import_ID = $wpdb->get_var("select pa_import_ID from ci_patients where pa_ID = {$s_id} ");
    if($import_ID>0)
    {
        $wpdb->insert('ci_fix_duplicate',array('ID'=>$m_id,'import_ID'=>$import_ID,'ctype'=>'patient','created'=>date('Y-m-d H:i:s')));
    }

    $wpdb->insert('ci_fix_duplicate',array('ID'=>$m_id,'import_ID'=>$s_id,'ctype'=>'dpatient','created'=>date('Y-m-d H:i:s')));
    $wpdb->delete('ci_patients',array('pa_ID'=>$s_id));

    if(isset($_GET['debug3'])){
        echo 'removed';exit;
    }
    return true;
}