<?php
global $wpdb,$current_role;
$act = isset($_REQUEST['act'])? $_REQUEST['act']: @$act;
$id = isset($_REQUEST['id'])? $_REQUEST['id']:0;


if($act=='print'){
    ?>
    <link href="<?php echo TRCPDD_URL ?>css/print.min.css?<?php echo TRCPDD_VS?>" media="all" type="text/css" rel='stylesheet'/>
    <?php
}else{
    include_once(TRCPDD_PATH.'pages/formdata.php');
    $save_action = 'ci_save_form_custom_provider';
}

$cli = array();
$cli_id = isset($_POST['clinic_id'])? $_POST['clinic_id']:0;
$sign_dir = tr_get_signature_dir();
$where_cli = ' and cli_status = 1 ';




if($current_role == 'clinic'){
    $cli_id = get_user_meta(get_current_user_id(),'_clinic_id',true);
}
else if($current_role=='lab_rep'  || $current_role=='lab_rep_w2')
{
    $rep_id = get_user_meta(get_current_user_id(),'_rep_id',true);
    $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);
    $where_cli .= " and `cli_rep-ID`={$rep_id} ";
}

if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $where_cli .= " and `cli_lab-ID` in ( {$lab_str} ) ";
}

if($cli_id>0)
{
    $cli = $wpdb->get_row("select * from ci_clinic where `cID`={$cli_id}", ARRAY_A);
}else{
    $clinics = $wpdb->get_results("select cli.cID,cli.cli_clinic,
                    trim(concat(lr.rep_first,' ',lr.rep_last)) as rep_name
                from ci_clinic cli
                Left JOIN ci_lab_reps lr ON lr.rep_ID = cli.`cli_rep-ID`
                where 1 {$where_cli} order by cli_clinic ", ARRAY_A);
}



if(($act=='edit' || $act=='print') && $id>0)
{
    $where = "cus.cus_ID = {$id}";
    if(isset($_REQUEST['doc_id'])){
        $where = "cus.doc_ID = {$_REQUEST['doc_id']}";
    }
    $cus = $wpdb->get_row("select cus.*,
                            doc.doc_first,
                            doc.doc_last,
                            doc.doc_specialty,
                            cli.`cli_lab-ID` as lab_ID
                            from ci_custom_providers cus
                            JOIN ci_doc doc ON cus.doc_ID = doc.doc_ID
                            LEFT JOIN ci_clinic cli ON cli.cID = cus.cli_ID
                            where {$where} group by cus.cus_ID", ARRAY_A);
    $cli_id = $cus['cli_ID'];
    if($cli_id==0)
    {
        $cli_id = $wpdb->get_var("select cli_ID from ci_clinic_doc where doc_ID = {$cus['doc_ID']}");
    }
    $cus['individuals'] = explode(',',$cus['cus_individuals']);
    if(empty($cus['cus_last'])){
        $cus['cus_last'] = $cus['doc_last'];
    }
    if(empty($cus['cus_first'])){
        $cus['cus_first'] = $cus['doc_first'];
    }
    $cus['cus_testing_profile'] = strtolower($cus['cus_testing_profile']);

}
else if($act=='list')
{

    $where = '';
    if($current_role == 'clinic'){
        $cli_id = get_user_meta(get_current_user_id(),'_clinic_id',true);
        $where.= " and cus.cli_ID = {$cli_id} ";
    }

   
    if (CI_Base_Lab::has_user_labs()) {
        $lab_str = CI_Base_Lab::get_user_lab_ids_str();
        $where.= " and cli.`cli_lab-ID` in ( {$lab_str} ) ";
    }

    $sql = "select  cus.*,cli.cli_clinic,cli.`cli_lab-ID` as lab_ID, lab.lab_dba_init as lab_name
                            from ci_custom_providers cus
                            JOIN ci_clinic cli ON cli.cID = cus.cli_ID
                            Left JOIN ci_labs lab ON lab.lab_ID = cli.`cli_lab-ID`
                            Where 1 {$where} ";

    $customs = $wpdb->get_results($sql);

    $show_lab = tr_current_role_in(array('administrator','AdminCIDW','administrator_p','clms'));

    ?>
    <div class="wrap">
        <h2>All <a class="add-new-h2" href="<?php echo Tr_Base_Class_V6::link(array('act'=>'','id'=>''))?>">Add New</a> </h2>
        <?php Tr_Session_Class::show()?>

        <div class="wraptbl">
        <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
            <thead>
            <tr>
                <th style="width:50px">#</th>
                <th>ACCOUNT MANAGER</th>
                <th>Office Manager</th>
                <th>Email</th>
                <th>FACILITY/CLIENT NAME</th>
                <?php if($show_lab):?>
                    <th>Lab</th>
                <?php endif;?>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach($customs as $row):?>
                <tr>
                    <td><?php echo $row->cus_ID?></td>
                    <td><?php echo $row->cus_manager?></td>
                    <td><?php echo $row->cus_office_man?></td>
                    <td><?php echo $row->cus_email?></td>
                    <td><?php echo $row->cli_clinic?></td>
                    <?php if($show_lab):?>
                        <td><?php echo $row->lab_name?></td>
                    <?php endif;?>
                    <td>
                        <a href="<?php echo Tr_Base_Class_V6::link(array('act'=>'edit','id'=>$row->cus_ID))?>">Edit</a>
                        - <a class="delete" href="<?php echo Tr_Base_Class_V6::link(array('tr_action'=>'ci_delete_custom_provider','id'=>$row->cus_ID))?>">Delete</a>
                    </td>
                </tr>
            <?php endforeach;?>
            </tbody>
        </table>
        </div>


    </div>
    <script>
        jQuery(function($){
            $('a.delete').click(function(){
                if(confirm('Do you want delete?')==false)return false;
            })
        })
    </script>
    <?php
    return;
}

?>
    <div class="wrap">
        <?php if($act!='print'):?>
            <h2>CUSTOM PROFILE <a class="add-new-h2" href="<?php echo Tr_Base_Class_V6::link(array('act'=>'list','id'=>''))?>">Saved form</a></h2>
            <?php Tr_Session_Class::show()?>
        <?php endif;?>
        <form method="post" class="need_validate">
            <input type="hidden" name="cus[cus_ID]" value="<?php echo @$cus['cus_ID']?>">

            <div class="frow blabel">
                <div class="col3">
                    <label>FACILITY/CLIENT NAME</label>
                    <select name="cus[cli_ID]" required="required">
                        <option value="">Please select</option>
                        <?php
                        foreach($clinics as $cli){
                            $cli['cli_clinic'] = trim($cli['cli_clinic']);
                            if(empty($cli['cli_clinic']))continue;
                            $selected = $cli['cID'] ==$cli_id? 'selected':'';
                            echo '<option value="'.$cli['cID'].'" '.$selected.' data-rep="'.$cli['rep_name'].'">'.stripslashes($cli['cli_clinic']).'</option>';
                        }
                        ?>
                    </select>
                </div>

            </div>
            <div class="frow">
                <div class="col3">
                    <label>Provider Last Name:</label>
                    <input type="text" class="doc_last requesting_physician_last_auto" name="cus[cus_last]" value="<?php echo @$cus['cus_last']?>" required/>
                    <input type="hidden" name="cus[doc_ID]" value="<?php echo @$cus['doc_ID']?>">
                </div>
                <div class="col3">
                    <label>Provider First Name:</label>
                    <input type="text" class="doc_first requesting_physician_first_auto" name="cus[cus_first]" value="<?php echo @$cus['cus_first']?>" required/>

                </div>
                <div class="col3">
                    <label>Specialty:</label>
                    <input type="text" name="cus[cus_specialty]" value="<?php echo $cus['cus_specialty']?>"/>
                </div>
            </div>



            <div class="frow">
                <h4>SELECT A TESTING PROFILE</h4>
            </div>
            <div class="frow">
                Based on risk stratification (e.g. low, moderate, or high risk patients, new patients, red flags), the practitioner may customize their testing requirements. The
                laboratory offers the client the opportunity to select or create their own custom profile, along with the ability of creating the name of the panel that best
                suits their practice.
                <br>
            </div>


            <div class="frow i_blabel">
                <div class="col5">
                    <label><input type="radio" id="profile_basic" name="cus[cus_testing_profile]" value="Basic" <?php checked( $cus['cus_testing_profile'],'Basic')?>/>
                        Low Risk</label> <i class="itip tooltip testing_profile" title="" data-id="profile_basic"></i>
                </div>
                <div class="col5">
                    <label><input type="radio" id="profile_standard" name="cus[cus_testing_profile]" value="Standard" <?php checked( $cus['cus_testing_profile'],'Standard')?>/>
                        Moderate Risk</label><i class="itip tooltip testing_profile" title="" data-id="profile_standard"></i>
                </div>
                <div class="col5">
                    <label><input type="radio" id="profile_highrisk" name="cus[cus_testing_profile]" value="highrisk" <?php checked( $cus['cus_testing_profile'],'highrisk')?>/>
                        High Risk</label><i class="itip tooltip testing_profile" title="" data-id="profile_highrisk"></i>
                </div>
                <div class="col5">
                    <label><input type="radio" id="profile_comprehensive" name="cus[cus_testing_profile]" value="Comprehensive" <?php checked( $cus['cus_testing_profile'],'Comprehensive')?>/>
                        COMPREHENSIVE</label><i class="itip tooltip testing_profile" title="" data-id="profile_comprehensive"></i>
                </div>

            </div>


            <div class="frow">
                <h4>CONFIRM POSITIVE DRUG SCREEN RESULTS (applies ONLY if performing EIA or POC testing in office)</h4>
            </div>

            <div class="frow iblabel">
                <input type="hidden" name="cus[cus_confirm_positive]" value="0"/>
                <label><input type="checkbox" name="cus[cus_confirm_positive]" value="1" <?php checked( $cus['cus_confirm_positive'],'1')?>/>
                    Please confirm all <b>POSITIVE</b> point -of-care testing or EIA screening results.</label>

            </div>

            <div class="frow">
                <h4>Please Select Individual Therapeutic Drugs or Therapeutic Drug Class Section.</h4>
            </div>
            <div class="frow">
                Based on risk stratification (e.g. low, moderate, or high risk patients, new patients, red flags), the practitioner may customize their testing requirements.
                <br>
                In the laboratory's commitment to medical necessity, the provider may customize the testing choices based on specific clinical need. Listed
                below are all the therapetutic drug / drug classes available. Please review each therapeutic drug/ drug class and select the individual or therapeutic drug
                class that suits your testing requirements. By checking the class you agree to test for all the therapeutic drugs available at the laboratory in that class.
                <br>
            </div>

            <div class="frow blabel" id="profile_custom_div">
                <?php
                if(!is_array($cus['individuals']))$cus['individuals'] = array();
                foreach(tr_pdd_get_profiles_tests_requesteds('tox') as $group_id=> $vl):?>
                    <div class="col7">
                        <?php
                        $group = tr_pdd_get_panel_groups($group_id);
                        echo '<label class="block"><b>'.$group['label'].'</b></label>';
                        echo '<div class="frow">';
                        foreach($vl as $k => $r)
                        {
                            $txt = $r->te_name;
                            $key = $r->key;
                            $checked = @in_array($key,$cus['cus_individuals']);
                            if(strpos($key,',')>0 && stripos($cus['cus_individuals'],$key)!==false)
                            {
                                $checked = true;
                            }
                            ?>
                            <label class="">
                                <input type="checkbox" class="<?php echo $r->class?>" name="cus[cus_individuals][]" value="<?php echo $key?>" <?php checked( $checked )?> />
                                <?php echo $txt?></label>
                            <?php
                        }
                        ?>
                        <?php
                        echo '</div>';

                        ?>
                    </div>
                <?php endforeach;?>
            </div>

            <div class="frow">
                <h4>PROVIDER AUTHRORIZATION</h4>
            </div>

            <div class="frow">
                <b>I understand and hereby acknowledge:</b><br>
                1. I have requested the creation of this custom profile of tests listed on this form for the use in our clinic when merited.<br>
                2. I understand that when ordering tests for my patients, reimbursement from the insurance carrier will be sought. I should only order
                those tests which I believe are medically necessary.<br>
                3. I understand that by using a customized testing profile, Medicare and other federally-funded health care plans may deny payment.<br>
                4. I will order individual test or a less inclusive profile when not all the tests in the cusomized profile are medically necessary.<br>
                5. I understand that this preferred order will remain in effect until an updated form is completed.<br>
            </div>



            <div class="frow iblabel">
                <div class="col2">
                    <label>Provider Signature:</label>
                    <?php

                    if(@$cus['cus_ID']>0 || $act=='print') {

                        $fname =  'provider_'.$cus['cus_ID'].'.png';
                        if (file_exists($sign_dir['path'] . '/' . $fname) && filesize($sign_dir['path'] . '/' . $fname) > 0)
                        {
                            ?>
                            <img class="sign_img" src="<?php echo $sign_dir['url'] . '/' . $fname.'?'.time(); ?>" style="width:300px"/>
                            <?php
                        }else{
                            echo tr_signature(array('name'=>'signature'));
                        }
                        ?>
                        <?php
                    }else{
                        echo tr_signature(array('name'=>'signature'));
                    }?>
                </div>
                <div class="col2">
                    <label>Date:</label>
                    <input type="text" class="hasdate today" name="cus[cus_provider_date]" value="<?php echo $cus['cus_provider_date']?>"/>
                </div>
            </div>

            <?php if(!empty($save_action)):?>
                <div class="frow">
                    <input type="submit" class="button-primary" value="Submit">
                    <input type="hidden" name="tr_action" value="<?php echo $save_action?>"/>
                </div>
            <?php endif;?>

        </form>
    </div>
<?php if($act!='print'):?>
    <script>
        jQuery(function($){
            $('[name="cus[cus_testing_profile]"]').change(function(){
                if($(this).is(':checked')){
                    $vl = $(this).val();
                    $('#profile_custom_div input').removeAttr('checked');
                    $('#profile_custom_div input.te_'+$vl).prop('checked',true);

                }
            })
            $('.testing_profile').click(function(){
                tb_show('', '?tr_action=show_basic_info&id='+$(this).data('id')+'&width=1000&height=600');
            });
            $('.psych_panel').click(function(){
                tb_show('', '?tr_action=show_basic_info&file=psych_1.jpg&id='+$(this).data('id')+'&width=800&height=600');
            });
            function tr_setup_physician_auto(cli_id)
            {
                $('.requesting_physician_last_auto').autocomplete({
                    source: ajaxurl+'?action=ajax&autocomplete=requesting_physician_last_auto&provider=1&cli_id='+cli_id,
                    minLength: 1,
                    select: function( event, ui ) {
                        if(ui.item)
                        {
                            $('[name="cus[doc_ID]"]').val(ui.item.id);
                            $('[name="cus[doc_ID]"]').data('name',ui.item.value + ui.item.doc_first);
                            $('[name="cus[cus_first]"]').val(ui.item.doc_first);
                            $('[name="cus[cus_specialty]"]').val(ui.item.doc_specialty);
                            if(ui.item.cus_ID > 0)
                            {
                                location.href="<?php echo Tr_Base_Class_V6::link(array('act'=>'edit','id'=>''))?>&id="+ui.item.cus_ID;
                            }
                        }else
                        {
                            $('[name="cus[doc_ID]"]').val('');
                        }
                    }
                });
                $('.requesting_physician_first_auto').autocomplete({
                    source: ajaxurl+'?action=ajax&autocomplete=requesting_physician_first_auto&provider=1&cli_id='+cli_id,
                    minLength: 1,
                    select: function( event, ui ) {
                        if(ui.item)
                        {
                            $('[name="cus[doc_ID]"]').val(ui.item.id);
                            $('[name="cus[doc_ID]"]').data('name',ui.item.doc_last + ui.item.value );
                            $('[name="cus[cus_last]"]').val(ui.item.doc_last);
                            $('[name="cus[cus_specialty]"]').val(ui.item.doc_specialty);
                            if(ui.item.cus_ID > 0)
                            {
                                location.href="<?php echo Tr_Base_Class_V6::link(array('act'=>'edit','id'=>''))?>&id="+ui.item.cus_ID;
                            }
                        }else{
                            $('[name="cus[doc_ID]"]').val('');
                        }
                    }
                });
            };
            tr_setup_physician_auto($('[name="cus[cli_ID]"]').val());
            $('[name="cus[cli_ID]"]').change(function(){
                tr_setup_physician_auto($('[name="cus[cli_ID]"]').val());
                $('[name="cus[cus_manager]"]').val($(this).find(':selected').data('rep'));
            });
            $('.doc_first,.doc_last').change(function(){
                $vl = $('[name="cus[doc_ID]"]').data('name');
                if($vl!=$('.doc_last').val() +$('.doc_first').val())
                {
                    $('.doc_last').val('')
                    $('.doc_first').val('');
                    $('[name="cus[doc_ID]"]').val('');
                }
            });
        })
        function cancel_info(id){
            jQuery('#'+id).removeAttr('checked');
            tb_remove();
        }
    </script>
    <style>
        div.ui-tooltip {
            max-width: 800px !important;
        }
    </style>
<?php endif;?>