<?php
global $wpdb;
global $current_role,$blog_id,$ci_all_btypes;
$can_edit = true;
$user_id = get_current_user_id();

$can_change_status = false;
if(isset($_GET['debug'])){
    ob_start();
}
$act = isset($_REQUEST['act'])? $_REQUEST['act']: @$act;
$save_action = '';
if($act=='print'){
    ?>
    <link href="<?php echo TRCPDD_URL ?>css/print.min.css?<?php echo TRCPDD_VS?>" media="all" type="text/css" rel='stylesheet'/>
    <?php
}else{
    include_once(TRCPDD_PATH.'pages/formdata.php');
    $save_action = 'ci_save_form_new_account_setup';
}

$courier_types = array(
	'fedex' => 'FedEx',
	'ups' => 'UPS',
    'courier' => 'Courier',
);
$courier_types2 = array(
	'willcall' => 'Will Call',
	'dailypickup' => 'Daily Pickup',
);

$cli = array();
$col = array();
$clinics = array(''=>'Please select');
$reps = array(''=>'Select Rep');
$labs = array(''=>'Select Lab');
$where_clinic = '';
$where_rep = '';


if(isset($_GET['fix_all_pcr'])){
    $type = 'pcr';
    $rs = $wpdb->get_results('select * from ci_clinic');
    foreach($rs as $r)
    {
        $cid = $r->cID;
        $wpdb->update('ci_clinic',array('cli_'.$type=>'1'),array('cID'=>$cid));

        $mids = $wpdb->get_col("select ma_user_ID from ci_clinic_manager where ma_cli_ID = {$cid}");
        
        foreach($mids as $user_id)
        {
            update_user_meta($user_id, '_'.$type, '1');
        }
    }
    echo 'ok';

}




if($current_role == 'clinic'){
    $cli_id = get_user_meta(get_current_user_id(),'_clinic_id',true);
    $cli = $wpdb->get_row("select * from ci_clinic where `cID`={$cli_id}", ARRAY_A);
}
else if(isset($_REQUEST['clinic_id']))
{
    $cli_id = $_REQUEST['clinic_id'];
    $cli = $wpdb->get_row("select * from ci_clinic where `cID`={$cli_id}", ARRAY_A);
}




if(tr_current_role_in(array('office_manager','multi_site_manager','clinic'))){

	$current_clinic = get_user_meta($user_id,'_use_current_clinic_id',true);
	if($current_clinic>0)
	{
		$where .= " and cID = {$current_clinic} ";
	}else{
		if(!tr_get_option('ci_pdf_data','off_see_all') || get_user_meta(get_current_user_id(),'_only_one_clinic',true)) {
			$cid = get_user_meta($user_id,'_clinic_id',true);
			$cli_ids = $wpdb->get_col( "select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' " );
			$cli_ids = implode( ',', $cli_ids );
            if(empty($cli_ids))
            {
	            $cli_ids = '0';
            }
			$where .= " and cID in ( {$cli_ids} )";
		}
	}

}
else if($current_role=='lab_rep'  || $current_role=='lab_rep_w2')
{
    $rep_id = get_user_meta(get_current_user_id(),'_rep_id',true);
    $where_clinic.=" and `cli_rep-ID`={$rep_id}";
    $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);

    if($rep['rep_inactive']==1)
    {
	    $can_edit = false;
    }
}
else if($current_role == 'distributor'){
    $dis_id = $wpdb->get_var("select dis_ID from ci_distributors where dis_user_ID = {$user_id}");

    $arr_marketing_groups=$wpdb->get_results("SELECT * FROM `ci_marketing_groups` where `gr_distributor_ID`= {$dis_id} and `gr_inactive`='0'");
    $g_ids = array(-1);
    foreach($arr_marketing_groups as $g)
    {
        $g_ids[] = $g->gr_ID;
    }
    $g_ids = implode(',',$g_ids);

    $where_rep.= " and (`rep_marketing_group_ID` IN ({$g_ids}) or rep_distributor_ID={$dis_id}) ";

    $where_clinic.=" and (cli_distributor_ID={$dis_id} or (`rep_marketing_group_ID` IN ({$g_ids}) or rep_distributor_ID={$dis_id})) ";
}

else if($current_role=='collector'){
    $col_id = get_user_meta($user_id,'_collector_id',true);
    $cids = $wpdb->get_col("select cli_ID from ci_clinic_collector where col_ID = {$col_id} ");
    $cids = implode(',',$cids);
    if(empty($cids))
    {
	    $cids = '0';
    }
    $where_rep .= '';
    $where_clinic.= " and cID in ({$cids})";

    $rep_rs = $wpdb->get_results("select * from ci_lab_reps where rep_inactive=0  {$where_rep} order by rep_first ASC ");
}

if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $where_clinic .= " and `cli_lab-ID` in ( {$lab_str} ) ";
}

if(tr_current_role_in(array('administrator','AdminCIDW','administrator_p','clms'))){

    $can_change_status = true;

    if(empty($lab_id))
    {
        $lab_id = CI_Base_Lab::get_current_lab_id();
    }
    $sql="select * from ci_lab_reps where rep_inactive = 0 ";

    if($lab_id> 0 && !defined('TRPDD_SAME_LAB')){
        $rep['lab_ID'] = $lab_id;
        //$sql.=" and lab_ID = {$lab_id}";
    }

    $rep_rs = $wpdb->get_results($sql.' order by rep_first ASC ');
    $las = $wpdb->get_results("select * from ci_labs  where (lab_is_ref=0 or lab_is_ref=2 ) ");
    foreach($las as $l)
    {
        $labs[$l->lab_ID] = $l->lab_dba_init;
    }

}
else{
    if(empty($lab_id))
    {
        $lab_id = CI_Base_Lab::get_current_lab_id();
    }
    if($lab_id>0 && !defined('TRPDD_SAME_LAB')){
        //$where_rep.= " and lab_ID = $lab_id";
    }
    $rep_rs = $wpdb->get_results("select * from ci_lab_reps where rep_inactive=0  {$where_rep} order by rep_first ASC ");
}



if(isset($rep_rs) && count($rep_rs)>0)
{
    foreach($rep_rs as $r)
    {
        $reps[$r->rep_ID] = $r->rep_first.' '.$r->rep_last.' ('.$r->rep_email.')';
    }
}
if($lab_id>0)
{
    $lab = $wpdb->get_row("select * from ci_labs where lab_ID = {$lab_id}", ARRAY_A);
}


if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $where_clinic.=" and `cli_lab-ID` in ( {$lab_str} ) ";
}

if($act!='edit' && $act!='print'){
    $clis = $wpdb->get_results("SELECT cli.*,lr.rep_first,lr.rep_last FROM `ci_clinic` cli
            left join ci_lab_reps lr on cli.`cli_rep-ID` = lr.rep_ID
            left join ci_clinic_manager ma on ma.ma_cli_ID = cli.cID
            where  cli_clinic !='0' {$where_clinic} 
            group by cli.cID
            order by cli_clinic", ARRAY_A);

    if(isset($clis) && count($clis)>0)
    {
        foreach($clis as $row)
        {
            $clinics[$row['cID']] = stripslashes($row['cli_clinic']);
        }
    }
}


if(isset($cli['cID']) && $cli['cID']>0)
{
    $docs = $wpdb->get_results("select doc.* from ci_doc doc
                                JOIN ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
                                where cd.cli_ID = {$cli['cID']}", ARRAY_A);
    $col = $wpdb->get_results("select co.* from ci_collector co JOIN ci_clinic_collector cl ON cl.col_ID = co.col_ID where cl.cli_ID = {$cli['cID']} ", ARRAY_A);
    $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$cli['cli_rep-ID']}", ARRAY_A);
	if($rep && !isset($reps[$rep['rep_ID']])){
		$reps[$rep['rep_ID']] = $rep['rep_first'].' '.$rep['rep_last'].' ('.$rep['rep_email'].')';
	}
    $managers = $wpdb->get_results("select * from ci_clinic_manager where ma_cli_ID = {$cli['cID']}", ARRAY_A);
    if(!$managers)
    {
        $managers = array();
        $managers[] = array(
            'ma_manager'=> '',
            'ma_email'=>$cli['cli_om-email'],
        );
    }
    $lab_id  = $cli['cli_lab-ID'];

}else{
    $cli['cli_tox'] = 1;
    $cli['cli_status'] = 1;
}

if(empty($cli['cli_result_style']))
{
    $cli['cli_result_style'] = 1;
}
if(empty($cli['cli_result_style_pcr']))
{
    $cli['cli_result_style_pcr'] = 1;
}

if(isset($_GET['debug'])){
    echo '$where_rep:';
    var_dump($where_rep);
}

?>

<?php if($act=='print'):?>
    <input type="button" class="button print_button" onclick="window.print()" value="Print"/>

<?php endif;?>


<h2>NEW ACCOUNT SETUP FORM</h2>
<?php Tr_Session_Class::show()?>

<form method="post" id="cli_change_div" class="need_validate main_form">



    <div class="frow">
        <h4>PRIMARY FACILITY ACCOUNT INFORMATION</h4>
    </div>

    <div class="frow blabel">
        <div class="col2">
            <div class="frow">
                    <label>PRACTICE/CLINIC NAME</label>
                    <?php if($act=='edit' || $act =='print'):?>
                        <input type="text" name="cli[cli_clinic]" value="<?php echo $cli['cli_clinic']?>"/>
                        <input type="hidden" name="cli[cID]" value="<?php echo $cli['cID']?>">
                    <?php else:?>
                        <?php tr_select_option('cli[cID]',$clinics,$cli['cID'],array('id'=>'cli_change2','attr'=>' required class="js_select2"'))?>
                    <?php endif;?>

            </div>

            <div class="frow">
                <?php if($can_change_status){?>
                <div class="col2">
                    <label>Status</label>
                    <select name="cli[cli_status]" required>
                        <?php
                        foreach(array(1=>'Active',0=>'Inactive') as $k =>$vl)
                        {
                            $selected = $k==$cli['cli_status']? 'selected':'';
                            echo '<option value="'.$k.'" '.$selected.'>'.$vl.'</option>';
                        }
                        ?>
                    </select>
                </div>
                <?php }?>
                <div class="col2">
                    <label>START DATE</label>
                    <input type="text"  name="cli[cli_sdate]" <?php echo(@$cli['cID']>0)?'readonly': 'class="hasdate"'?> value="<?php echo $cli['cli_sdate']?>" required=""/>
                </div>
            </div>

        </div>
        <div class="col2">
            <label>HOURS OF OPERATION</label>
            <div class="frow ilabel">
                <?php $col6 = ($act=='print')? 'col3':'col7';?>
                <div class="<?php echo $col6?>">
                    <label>Mon</label>
                    <input type="text" name="cli[cli_mon]" value="<?php echo $cli['cli_mon']?>" />
                </div>
                <div class="<?php echo $col6?>">
                    <label>Tue</label>
                    <input type="text" name="cli[cli_tue]" value="<?php echo $cli['cli_tue']?>" />
                </div>
                <div class="<?php echo $col6?>">
                    <label>Wed</label>
                    <input type="text" name="cli[cli_wed]" value="<?php echo $cli['cli_wed']?>" />
                </div>
                <div class="<?php echo $col6?>">
                    <label>Thu</label>
                    <input type="text" name="cli[cli_thu]" value="<?php echo $cli['cli_thu']?>" />
                </div>
                <div class="<?php echo $col6?>">
                    <label>Fri</label>
                    <input type="text" name="cli[cli_fri]" value="<?php echo $cli['cli_fri']?>" />
                </div>
                <div class="<?php echo $col6?>">
                    <label>Sat</label>
                    <input type="text" name="cli[cli_sat]" value="<?php echo $cli['cli_sat']?>" />
                </div>
                <div class="<?php echo $col6?>">
                    <label>Sun</label>
                    <input type="text" name="cli[cli_sun]" value="<?php echo $cli['cli_sun']?>" />
                </div>
            </div>
            <div class="frow ilabel">
                <label style="width:80px">Courier Type</label>
                <select name="cli[cli_courier_type]" style="width:100px;">
		            <?php
		            foreach($courier_types as $tkey => $tname)
		            {
			            $selected = $tkey == $cli['cli_courier_type']? 'selected':'';
			            echo '<option value="'.$tkey.'" '.$selected.'>'.$tname.'</option>';
		            }
		            ?>
                </select>
                <select name="cli[cli_courier_type2]" style="width:100px;">
                    <option value="">Select</option>
		            <?php
		            foreach($courier_types2 as $tkey => $tname)
		            {
			            $selected = $tkey == $cli['cli_courier_type2']? 'selected':'';
			            echo '<option value="'.$tkey.'" '.$selected.'>'.$tname.'</option>';
		            }
		            ?>
                </select>
            </div>
        </div>
    </div>




    <div class="frow blabel">
        <div class="col4">
            <label>PRACTICE/CLINIC ADDRESS</label>
            <input type="text" name="cli[cli_street]" value="<?php echo $cli['cli_street']?>" required=""/>
        </div>
        <div class="col4">
            <label>CITY</label>
            <input type="text" name="cli[cli_city]" value="<?php echo $cli['cli_city']?>" required=""/>
        </div>
        <div class="col4">
            <label>STATE</label>
            <?php tr_select_option('cli[cli_state]',tr_get_states(),$cli['cli_state'],array('attr'=>' required'))?>
        </div>
        <div class="col4">
            <label>ZIP CODE</label>
            <input type="text" name="cli[cli_zip]" value="<?php echo $cli['cli_zip']?>" required=""/>
        </div>
    </div>


    <div class="frow blabel">
        <div class="col2">
            <label>PHONE NO.</label>
            <input type="text" class="has_phone" name="cli[cli_om-phone]" value="<?php echo $cli['cli_om-phone']?>" required=""/>
        </div>
        <div class="col2">
            <label>FAX NO.</label>
            <input type="text" class="has_phone" name="cli[cli_om-fax]" value="<?php echo $cli['cli_om-fax']?>" required=""/>
        </div>
    </div>

    <div class="frow">
            <h4>Office Manager</h4>
        </div>

        <div class="frow blabel">
            <div class="col2">
                <label>Name</label>
            </div>
            <div class="col2">
                <label>Email Address</label>
            </div>
        </div>

        <ol id="managers_dv" class="more multirows" style="overflow: auto;max-height:300px">
            <?php
            $offic_required = tr_get_option('ci_pdf_data', 'forms_droffic') ? '' : 'required';
            $max_j = isset($managers) &&  count($managers) > 0 ? count($managers) : ($act == 'print' && $id == 0 ? 5 : 1);
            for ($j = 0; $j < $max_j; $j++) : ?>
                <li class="frow blabel pmanagerrow <?php echo ($j == 0) ? 'default' : 'secondrow' ?> ">
                    <div class="col2">
                        <input type="text" name="manager[<?php echo $j ?>][ma_manager]" value="<?php echo isset($managers[$j]['ma_manager']) ? $managers[$j]['ma_manager'] : '' ?>" <?php echo $offic_required ?> />
                        <input type="hidden" name="manager[<?php echo $j ?>][ma_ID]" value="<?php echo isset($managers[$j]['ma_ID']) ? $managers[$j]['ma_ID'] : '' ?>" />
                    </div>
                    <div class="col2">
                        <input type="email" name="manager[<?php echo $j ?>][ma_email]" value="<?php echo isset($managers[$j]['ma_email']) ? $managers[$j]['ma_email'] : '' ?>" <?php echo $offic_required ?> />
                        <?php if ($act != 'print') : ?>
                            <a class="button remove_multi_row">-</a>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endfor; ?>
        </ol>
        <?php if ($act != 'print') : ?>
            <a class="button add_multi_row add_managerbtn" data-key="manager" data-id="<?php echo $j ?>">+</a>
        <?php endif; ?>



    <div class="frow">
        <h4>SALES REP INFORMATION (required)</h4>
    </div>
    <div class="frow blabel">
        <?php if(tr_current_role_in(array('administrator','AdminCIDW','administrator_p','clms'))):?>
            <div class="col4">
                <label>Lab</label>
                <?php if($blog_id==1):?>
                    <?php tr_select_option('lab[lab_ID]',$labs,$lab_id,array('attr'=>'required'))?>
                <?php else:?>
                    <input type="text" name="lab[lab_dba_init]" value="<?php echo $lab['lab_dba_init']?>" readonly required=""/>
                    <input type="hidden" name="lab[lab_ID]" value="<?php echo $lab['lab_ID']?>">
                <?php endif;?>
            </div>
            <div class="col4">
                <label>SALES REP NAME</label>
                <?php tr_select_option('cli[cli_rep-ID]',$reps,$cli['cli_rep-ID'],array('attr'=>'required'))?>
            </div>
            <div class="col4">
                <label>Account Manager</label>
                <input type="text" name="cli[cli_account_manager]" value="<?php echo $cli['cli_account_manager']?>" maxlength="50"/>
            </div>
        <?php elseif($current_role=='lab_rep'  || $current_role=='lab_rep_w2'):?>
            <div class="col5">
                <label>SALES REP LAST NAME</label>
                <input type="text" name="rep[rep_last]" value="<?php echo $rep['rep_last']?>" required=""/>
                <input type="hidden" name="cli[cli_rep-ID]" value="<?php echo $rep['rep_ID']?>"/>
            </div>
            <div class="col5">
                <label>SALES REP FIRST NAME</label>
                <input type="text" name="rep[rep_first]" value="<?php echo $rep['rep_first']?>" required=""/>
            </div>
            <div class="col5">
                <label>MIDDLE INITIAL</label>
                <input type="text" name="rep[rep_middle]" value="<?php echo $rep['rep_middle']?>" />
            </div>
            <div class="col5">
                <label>Account Manager</label>
                <input type="text" name="cli[cli_account_manager]" value="<?php echo $cli['cli_account_manager']?>" maxlength="50"/>
            </div>
        <?php else:?>
            <div class="col4">
                <label>SALES REP NAME</label>
                <?php tr_select_option('cli[cli_rep-ID]',$reps,$cli['cli_rep-ID'],array('attr'=>'required'));?>
            </div>
            <div class="col4">
                <label>Account Manager</label>
                <input type="text" name="cli[cli_account_manager]" value="<?php echo $cli['cli_account_manager']?>" maxlength="50"/>
            </div>
            <div class="col4"></div>


        <?php endif;?>
        <div class="col4">
            <label>DATE</label>
            <input type="text"  class="hasdate today" name="cli[cli_sdate]" value="<?php echo $rep['rep_sdate']?>" required=""/>
        </div>
    </div>
    <div class="frow">
	    <?php if(tr_get_option('ci_pdf_data','enable_clinic_group')){?>
        <div class="col4">
            <label>Clinician Group</label>
            <?php
            $groups = $wpdb->get_results("select * from ci_clinician_groups  where 1  {$where_cli_group}");

            if(isset($_GET['clig']))
            {
                $cli['cli_group_ID'] = $_GET['clig'];
            }
            ?>
            <select name="cli[cli_group_ID]">
                <option value="">Select Clinician Group</option>
                <?php
                foreach($groups as $g)
                {
                    $selected = $g->clig_ID == $cli['cli_group_ID']? 'selected':'';
                    echo '<option value="'.$g->clig_ID.'" '.$selected.' data-reps="|'.$g->clig_rep_ID.'|">'.$g->clig_name.'</option>';
                }
                ?>
            </select>
            <a class="add_new_group">Add New Clinician Group</a>
            <div class="new_group" style="display: none;">
                <label>New Group</label>
                <input type="text" name="cli_new_group" value=""/>
            </div>
        </div>
        <?php }?>
        <div class="col4">
            <label>Marketing Group</label>
            <?php
            if(tr_current_role_in(array('administrator','AdminCIDW','administrator_p','lab','distributor'))){
                $groups = $wpdb->get_results("select g.*,group_concat('|',rep.rep_ID,'|') as reps from ci_marketing_groups g
                    left JOIN ci_lab_reps rep ON rep.rep_marketing_group_ID = g.gr_ID
                    where 1  {$where_group}
                    Group by g.gr_ID ");

                ?>
                <select name="cli[cli_marketing_group_ID]">
                    <option value="">Select Marketing Group</option>
                    <?php
                    foreach($groups as $g)
                    {
                        $selected = $g->gr_ID == $cli['cli_marketing_group_ID']? 'selected':'';
                        echo '<option value="'.$g->gr_ID.'" '.$selected.' data-reps="'.$g->reps.'">'.$g->gr_name.'</option>';
                    }
                    ?>
                </select>
                <?php
            }else if($current_role=='lab_rep'  || $current_role=='lab_rep_w2'){
                $group_leader = tr_dpp_get_marketing_group_leader($user_id);
                if($group_leader)
                {
                    ?>
                    <input type="text" value="<?php echo $group_leader->gr_name?>" readonly/>
                    <input type="hidden" name="cli[cli_marketing_group_ID]" value="<?php echo $group_leader->gr_ID?>"/>
                    <?php
                }else{
                    $group = tr_dpp_get_marketing_group($user_id);
                    ?>
                    <input type="text" value="<?php echo $group->gr_name?>" readonly/>
                    <input type="hidden" name="cli[cli_marketing_group_ID]" value="<?php echo $group->gr_ID?>"/>
                    <?php
                }
            }

            ?>
        </div>
        <div class="col4">
            <label>Distributor</label>
            <?php
            if($current_role=='distributor'){
                $distributor = $wpdb->get_var("select dis_name from ci_distributors where dis_user_ID = {$user_id}");
                ?>
                <input type="text" value="<?php echo $distributor?>" readonly/>
                <?php
            }else{
                $groups = $wpdb->get_results("select dis.*,group_concat('|',rep.rep_ID,'|') as reps from ci_distributors dis
                    left JOIN ci_lab_reps rep ON rep.rep_distributor_ID = dis.dis_ID
                    Group by dis.dis_ID ");
                ?>
                <select name="cli[cli_distributor_ID]">
                    <option value="">Select Distributor</option>
                    <?php
                    foreach($groups as $dis)
                    {
                        $selected = $dis->dis_ID == $cli['cli_distributor_ID']? 'selected':'';
                        if(count($groups)==1)$selected = 'selected';
                        echo '<option value="'.$dis->dis_ID.'" '.$selected.' data-reps="'.$dis->reps.'">'.$dis->dis_name.' '.$dis->dis_last.'</option>';
                    }
                    ?>
                </select>
                <?php
            }
            ?>
        </div>
    </div>

    <div class="frow">
        <h4>PRIMARY FACILITY PHYSICIAN INFORMATION - Please fill out a separate forms for separate locations</h4>
    </div>

    <div class="frow blabel">
        <div class="col6"><label>PHYSICIAN FIRST NAME</label></div>
        <div class="col6"><label>LAST NAME</label></div>
        <div class="col6"><label>SPECIALTY/CREDENTIAL</label></div>
        <div class="col6"><label>NPI</label></div>
        <div class="col6"><label>PHONE NO.</label></div>
        <div class="col6"><label>EMAIL ADDRESS</label></div>
    </div>
    <ol id="doctors_dv" class="more">
        <?php
        $max_j = isset($docs) && count($docs)>1? count($docs) :1;
        for($j = 0;$j<$max_j;$j++):?>
            <li class="frow blabel pdoctorrow <?php if($j==0)echo 'default'?>">
                <div class="col6">
                    <input type="text" name="doc[<?php echo $j?>][doc_first]" value="<?php echo $docs[$j]['doc_first']?>" required=""/>
                    <input type="hidden" name="doc[<?php echo $j?>][doc_ID]" value="<?php echo $docs[$j]['doc_ID']?>" />
                </div>
                <div class="col6">
                    <input type="text" name="doc[<?php echo $j?>][doc_last]" value="<?php echo $docs[$j]['doc_last']?>" required=""/>
                </div>
                <div class="col6">
                    <input type="text" name="doc[<?php echo $j?>][doc_specialty]" value="<?php echo $docs[$j]['doc_specialty']?>" required=""/>
                </div>
                <div class="col6">
                    <input type="text" name="doc[<?php echo $j?>][doc_npi]" value="<?php echo $docs[$j]['doc_npi']?>" required=""/>
                </div>
                <div class="col6">
                    <input type="text"  class="has_phone" name="doc[<?php echo $j?>][doc_phone]" value="<?php echo $docs[$j]['doc_phone']?>" />
                </div>
                <div class="col6">
                    <input type="email" name="doc[<?php echo $j?>][doc_email]" value="<?php echo $docs[$j]['doc_email']?>"/>
                    <?php if($act!='print'):?>
                        <a class="button rm_doctor_row">-</a>
                    <?php endif;?>
                </div>
            </li>
        <?php endfor;?>
    </ol>
    <?php if($act!='print'):?>
        <a class="button add_doctor_row" data-id="<?php echo $j?>">+</a>
        <div class="note">Note: You can search PHYSICIAN by NPI</div>
    <?php endif;?>

    <div class="frow" style="<?php if($current_role=='collector')echo 'display:none'?>">
        <div class="frow">
            <h4>COLLECTOR INFORMATION</h4>
        </div>

        <div class="frow blabel">
            <div class="col7"><label>COLLECTOR LAST NAME</label></div>
            <div class="col7"><label>COLLECTOR FIRST NAME</label></div>
            <div class="col7"><label>MIDDLE INITIA</label></div>
            <div class="col7"><label>DATE</label></div>
            <div class="col7"><label>PHONE NO.</label></div>
            <div class="col7"><label>EMAIL ADDRESS</label></div>
            <div class="col7"><label>Lab Employee</label></div>
        </div>
        <ol id="coll_dv" class="more">
            <?php
            $max_j = count($col)>0? count($col) : 1;
            for($j = 0;$j<$max_j;$j++):?>
                <li class="frow blabel pcorow <?php if($j==0)echo 'default'?>">
                    <div class="col7">
                        <input type="text" name="cols[<?php echo $j?>][col_last]" value="<?php echo $col[$j]['col_last']?>" />

                        <input type="hidden" name="cols[<?php echo $j?>][col_ID]" value="<?php echo $col[$j]['col_ID']?>" />
                    </div>
                    <div class="col7">
                        <input type="text" name="cols[<?php echo $j?>][col_first]" value="<?php echo $col[$j]['col_first']?>"/>
                    </div>
                    <div class="col7">
                        <input type="text" name="cols[<?php echo $j?>][col_middle]" value="<?php echo $col[$j]['col_middle']?>"/>
                    </div>

                    <div class="col7">
                        <input type="text"  name="cols[<?php echo $j?>][col_rdate]" value="<?php echo $col[$j]['col_rdate']?>"/>
                    </div>

                    <div class="col7">
                        <input type="text" class="has_phone" name="cols[<?php echo $j?>][col_phone]" value="<?php echo $col[$j]['col_phone']?>"/>
                    </div>

                    <div class="col7">
                        <input type="text" name="cols[<?php echo $j?>][col_email]" value="<?php echo $col[$j]['col_email']?>"/>
                    </div>
                    <div class="col7">
                        <input type="hidden" name="cols[<?php echo $j?>][col_employee]" value="0" />
                        <input type="checkbox" name="cols[<?php echo $j?>][col_employee]" value="1" <?php checked($col[$j]['col_employee'])?>/>
	                    <?php if($act!='print'):?>
                            <a class="button rm_co_ll_row" style="padding: 0 5px;float:right;">-</a>
	                    <?php endif;?>
                    </div>
                </li>
            <?php endfor;?>
        </ol>
        <?php if($act!='print'):?>
            <a class="button add_coll_row" data-id="<?php echo $j?>">+</a>
        <?php endif;?>
    </div>

    <div class="frow">
        <h4>TEST RESULTS</h4>
    </div>

    <div class="frow iblabel">
        <label>
            FAX RESULTS
            <input type="hidden" name="cli[cli_faxres]" value="0"/>
            <input type="checkbox" id="cli_faxes_result" name="cli[cli_faxres]" value="1" <?php checked($cli['cli_faxres'])?>/>
        </label>
        <label>
            WEB PORTAL ACCESS
            <input type="hidden" name="cli[cli_portal]" value="0"/>
            <input type="checkbox" name="cli[cli_portal]" value="1" <?php checked($cli['cli_portal'])?>/>
        </label>
        <label>
            EMR/EHR ACCESS
            <input type="hidden" name="cli[cli_emr-ehr]" value="0"/>
            <input type="checkbox" name="cli[cli_emr-ehr]" value="1" <?php checked($cli['cli_emr-ehr'])?>/>
        </label>
    </div>

    <?php if(in_array('tox', $ci_all_btypes)){?>
    <div class="frow">
        <h4>RESULTS STYLE (Tox)</h4>
    </div>
    <div class="frow iblabel">
        <div class="col5">
            <select name="cli[cli_result_style]">
                <?php
                for($i=1;$i<=6;$i++)
                {
                    $selected = $cli['cli_result_style']==$i ? 'selected':'';
                    echo '<option value="'.$i.'" '.$selected.'>Style #'.$i.'</option>';
                }
                ?>
            </select>
        </div>

        <label>
            <input type="hidden" name="cli[cli_vurine_top]" value="0"/>
            <input type="checkbox" name="cli[cli_vurine_top]" value="1" <?php checked($cli['cli_vurine_top'])?>/>
            Urine validity Top in style #5
        </label>

        <label>
            <input type="hidden" name="cli[cli_no_history]" value="0"/>
            <input type="checkbox" name="cli[cli_no_history]" value="1" <?php checked($cli['cli_no_history'])?>/>
            No History Block
        </label>
    </div>
    <?php }?>


	<?php if(in_array('pcr', $ci_all_btypes)){?>
    <div class="frow">
        <h4>RESULTS STYLE (PCR)</h4>
    </div>
    <div class="frow iblabel">
        <div class="col5">
            <select name="cli[cli_result_style_pcr]">
                <?php
                for($i=1;$i<=2;$i++)
                {
                    $selected = $cli['cli_result_style_pcr']==$i ? 'selected':'';
                    echo '<option value="'.$i.'" '.$selected.'>Style #'.$i.'</option>';
                }
                ?>
            </select>
        </div>

    </div>
    <?php }?>
    
    <div class="frow">
        <h4>&nbsp;Access to</h4>
    </div>
    <div class="frow">
        <?php
        foreach($ci_all_btypes as $btype)
        {
            $typename = tr_pdd_get_typename($btype);
            $key = 'cli_'.$btype;
            $checked = $cli[$key];
	        if(count($ci_all_btypes)==1)
	        {
                $checked = true;
	        }
            ?>
            <div class="col5">
                <label><?php echo $typename?></label>
                <input type="hidden" name="cli[<?php echo $key?>]" value="0"/>
                <input type="checkbox" name="cli[<?php echo $key?>]" value="1" <?php checked($checked)?>"/>
            </div>
        <?php
        }
        ?>

    </div>

    <?php
    if(!empty($save_action) && $can_edit):?>
        <div class="frow">
            <input type="submit" class="button-primary" value="Submit">
            <input type="hidden" name="tr_action" value="<?php echo $save_action?>"/>
        </div>
    <?php endif;?>

    <script>
        jQuery(function($){

            function autocomment_coll_last_auto(){
                $('[name*="[col_last]"]').autocomplete({
                    source: ajaxurl+'?action=ajax&autocomplete=autocomment_coll_last_auto',
                    minLength: 1,
                    select: function( event, ui ) {
                        if(ui.item)
                        {
                            var $pr = $(this).parents('.pcorow');
                            $('[name*="[col_last]"]',$pr).val(ui.item.col_last);
                            $('[name*="[col_ID]"]',$pr).val(ui.item.col_ID);
                            $('[name*="[col_first]"]',$pr).val(ui.item.col_first);
                            $('[name*="[col_middle]"]',$pr).val(ui.item.col_middle);
                            $('[name*="[col_rdate]"]',$pr).val(ui.item.col_rdate);
                            $('[name*="[col_phone]"]',$pr).val(ui.item.col_phone);
                            $('[name*="[col_email]"]',$pr).val(ui.item.col_email);
                        }
                    }
                });
            }

            $('#coll_dv').on( "new_row",function(){
                autocomment_coll_last_auto();
            }).on('remove_row',function(e,doc_id){
                console.log(doc_id);
            });
            autocomment_coll_last_auto();

            function setup_physician_npi_auto()
            {
                $('[name*="[doc_npi]"]').autocomplete({
                    source: ajaxurl+'?action=ajax&autocomplete=requesting_physician_npi_auto',
                    minLength: 1,
                    select: function( event, ui ) {
                        if(ui.item)
                        {
                            var $pr = $(this).parents('.pdoctorrow');
                            $('[name*="[doc_ID]"]',$pr).val(ui.item.id);
                            $('[name*="[doc_first]"]',$pr).val(ui.item.doc_first);
                            $('[name*="[doc_last]"]',$pr).val(ui.item.doc_last);
                            $('[name*="[doc_specialty]"]',$pr).val(ui.item.doc_specialty);
                            $('[name*="[doc_phone]"]',$pr).val(ui.item.doc_phone);
                            $('[name*="[doc_email]"]',$pr).val(ui.item.doc_email);
                        }
                    }
                });
                
            };
            $( "#doctors_dv" ).on( "new_row",function(){
                setup_physician_npi_auto();
            }).on('remove_row',function(e,doc_id){
                console.log(doc_id);
            });

            setup_physician_npi_auto();


            $('body').on('click','a.add_new_group',function(){
                $(this).hide();
                $('.new_group').show();
                $('#clicli_group_id').val('');
            })
           // $('[name="cli[cli_marketing_group_ID]"] option').hide();
            //$('[name="cli[cli_distributor_ID]"] option').hide();
            $('[name="cli[cli_group_ID]"] option').hide();
            $('[name="cli[cli_ID]"]').change(function(){
                $vl = $(this).val();
                <?php /*
                var $mg = false;
                $('[name="cli[cli_marketing_group_ID]"] option').each(function(){
                    $('[name="cli[cli_marketing_group_ID]"]').val('');
                    var $reps = $(this).data('reps')+'';
                    if($(this).val()=='' || $reps.indexOf('|'+$vl+'|')>-1){
                        $(this).show();
                        $mg = $(this).val();
                    }else{
                        $(this).hide();
                    }
                })
                if($mg)
                {
                    $('[name="cli[cli_marketing_group_ID]"]').val($mg);
                }
                var $dis = false;
                $('[name="cli[cli_distributor_ID]"] option').each(function(){
                    $('[name="cli[cli_distributor_ID]"]').val('');
                    var $reps = $(this).data('reps')+'';
                    if($(this).val()=='' || $reps.indexOf('|'+$vl+'|')>-1){
                        $(this).show();
                        $dis = $(this).val();
                    }else{
                        $(this).hide();
                    }
                })
                if($dis)
                {
                    $('[name="cli[cli_distributor_ID]"]').val($dis);
                }
                */?>
                var $cg = false;
                $('[name="cli[cli_group_ID]"] option').each(function(){
                    $('[name="cli[cli_group_ID]"]').val('');
                    var $reps = $(this).data('reps')+'';
                    if($(this).val()=='' || $reps.indexOf('|'+$vl+'|')>-1){
                        $(this).show();
                        $cg = $(this).val();
                    }else{
                        $(this).hide();
                    }
                })
                if($cg)
                {
                    $('[name="cli[cli_group_ID]"]').val($cg);
                }


            }).change();
            $('#cli_change2').change(function(){
                location.href='<?php Tr_Base_Class_V6::link(array('clinic_id'=>''))?>&clinic_id='+$(this).val();
            })
        })
    </script>
</form>



<?php
if(isset($_GET['debug'])){
    $content = ob_get_clean();
    $content = str_replace('required','datarequired',$content);
    echo $content;
}
?>
