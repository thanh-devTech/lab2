<?php
global $wpdb,$current_role,$blog_id;

$can_edit = true;
$can_delete = true;
$can_change_status = false;

include_once(TRCPDD_PATH.'pages/formdata.php');

$user_id = get_current_user_id();
if(isset($_GET['debug'])){
    ob_start();
}
$id = isset($_REQUEST['id'])? $_REQUEST['id']:0;
$rep_selected = array();
$cli = array();
$labs = array(''=>'Select Lab');
$clinics = array();
$reps = array();
$docs = array(''=>'Please Select');
$locations = array('online via web-ex');
$lab_id = 0;
$where_list = '';
$where_cli = '';

if($current_role=='lab_rep'  || $current_role=='lab_rep_w2'){
    $rep_id = get_user_meta(get_current_user_id(),'_rep_id',true);
    $col_ids = $wpdb->get_col("select col_ID from ci_rep_collector where rep_ID = {$rep_id} ");
    $col_ids = implode(',',$col_ids);
    $where_list.= " and col.rep_ID in ($col_ids) ";
    $clis = $wpdb->get_results("select * from ci_clinic where `cli_rep-ID`={$rep_id} AND cli_status=1 order by cli_clinic asc", ARRAY_A);
    $lab_id = $wpdb->get_var("select lab_ID from ci_lab_reps where rep_ID = {$rep_id}");
    $can_change_status = true;

	$rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);

	if($rep['rep_inactive']==1)
	{
		$can_edit = false;
		$can_delete = false;
		$can_change_status = false;
	}
}
else if($current_role == 'clinic'){
    $cli_id = get_user_meta(get_current_user_id(),'_clinic_id',true);
    $where_list.= " and col.cli_ID = {$cli_id} ";
}
else if(tr_current_role_in(array('office_manager','multi_site_manager','clinic'))){

	$current_clinic = get_user_meta($user_id,'_use_current_clinic_id',true);
	if($current_clinic>0)
	{
		$where .= " and cID = {$current_clinic} ";
	}else{
		if(!tr_get_option('ci_pdf_data','off_see_all') || get_user_meta(get_current_user_id(),'_only_one_clinic',true)) {
			$cid = get_user_meta($user_id,'_clinic_id',true);
			$cli_ids = $wpdb->get_col( "select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' " );
			$cli_ids = implode( ',', $cli_ids );

			$where .= " and cID in ( {$cli_ids} )";
		}
	}

    $can_change_status = true;

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
}else if($current_role=='lab'){
    $lab_id = CI_Base_Lab::get_current_lab_id();
}
else if(tr_current_role_in( array('administrator', 'administrator_p' , 'clms' ,'AdminCIDW'))){
    $lab_id = @$_POST['lab_id']> 0? @$_POST['lab_id']: $lab_id;
    if(empty($lab_id))
    {
        $lab_id = CI_Base_Lab::get_current_lab_id();
    }

    $las = CI_Base_Lab::get_user_labs();

    foreach($las as $l)
    {
        $labs[$l['lab_ID']] = $l['lab_dba'];
    }
    $can_change_status = true;
}

if($act=='list'){
    //$where_list .= $where_rep;//." and (col.col_first!='' or col.col_last !='' ) and col.col_first!='unknown' ";

	$where_list.= " and (col.col_first !='' or col.col_last!='' or col.col_phone!='')";
    $sql = "select  col.*, lab.lab_dba_init as lab_name
                            from ci_collector col
                            left JOIN ci_lab_reps lr ON lr.rep_ID = col.rep_ID
                            Left JOIN ci_labs lab ON lab.lab_ID = lr.lab_ID
                            Where 1 {$where_list} 
                            
                            order by col.col_status desc, col.col_first asc";
    if(isset($_REQUEST['debug']))
    {
        echo $sql;
    }
    $cols = $wpdb->get_results($sql);

    $show_lab = tr_current_role_in(array('administrator','AdminCIDW','administrator_p','clms'));
    ?>
    <div class="wrap">
        <h2>COLLECTORS <a class="add-new-h2" href="<?php echo Tr_Base_Class_V6::link(array('act'=>'','id'=>''))?>">Add New</a> </h2>
        <?php Tr_Session_Class::show()?>

        <div class="wraptbl">
        <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
            <thead class="sticky">
            <tr>
                <th style="width:50px">#</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
                <?php if($show_lab):?>
                    <th style="width:70px">Lab</th>
                <?php endif;?>
                <?php if($can_change_status):?>
                    <th style="width: 100px;">Status</th>
                <?php endif;?>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach($cols as $row):?>
                <tr  data-id="<?php echo $row->col_ID;?>">
                    <td><?php echo $row->col_ID?></td>
                    <td><?php echo $row->col_first.' '.$row->col_last?></td>
                    <td><?php echo $row->col_phone?></td>
                    <td><?php echo $row->col_email?></td>
                    <?php if($show_lab):?>
                        <td><?php echo $row->lab_name?></td>
                    <?php endif;?>
                    <?php if($can_change_status):?>
                        <td class="col_status" data-status="<?php echo $row->col_status;?>"></td>
                    <?php endif;?>
                    <td>
                        <a href="<?php echo Tr_Base_Class_V6::link(array('act'=>'edit','id'=>$row->col_ID))?>">Edit</a>
                        <?php if($can_delete){?>
                        - <a class="delete" href="<?php echo Tr_Base_Class_V6::link(array('tr_action'=>'ci_delete_collector','id'=>$row->col_ID))?>">Delete</a>
                        <?php }?>
                    </td>
                </tr>
            <?php endforeach;?>
            </tbody>
        </table>
        </div>

        <div>Total: <?php echo count($cols)?></div>

    </div>
    <script>
        jQuery(function($){
            $('a.delete').click(function(){
                if(confirm('Do you want delete?')==false)return false;
            })
            var $statuses = {1:'Active',0:'Inactive'};
            $('.col_status').each(function(){
                var $html = '<select>';
                for(k in $statuses)
                {
                    $html+='<option value="'+k+'" '+(k==$(this).data('status')?'selected':'')+'>'+$statuses[k]+'</option>';
                }
                $html+='</select>';
                $(this).html($html);
                if($(this).data('status')=='0'){
                    $(this).parents('tr').addClass('row_inactive');
                }

            });
            $('.col_status select').change(function(){
                var $this = $(this);
                var $pr = $(this).parent();
                $pr.find('.sloading').remove();
                $pr.append('<div class="sloading"></div>');
                var $id = $pr.parents('tr').data('id');
                var $vl = $(this).val();
                $.ajax({
                    type:'post',
                    dataType:'json',
                    data:{tr_action:'ci_change_col_status',id:$id,status:$vl},
                    success: function(rs){
                        $pr.find('.sloading').remove();
                        if(rs.status=='ok'){
                            if($vl=='0'){
                                $pr.parents('tr').addClass('row_inactive');
                            }
                            else{
                                $pr.parents('tr').removeClass('row_inactive');
                            }
                        }else{
                            $this.change();
                        }
                    },
                    error:function(){
                        $this.change();
                    }
                })
            });
        })
    </script>
    <?php
    return;
}
else if($act=='edit' && $id>0)
{
    $col = $wpdb->get_row("select col.* from ci_collector col where col.col_ID = {$id}", ARRAY_A);
    if($col['lab_ID']==0)
    {
        $col['lab_ID'] = $wpdb->get_var("select lab_ID from ci_lab_reps where rep_ID = {$col['rep_ID']}");
    }

    $lab_id = $col['lab_ID'];
    $rep_id = $col['rep_ID'];
    $cli_id = $col['cli_ID'];

    $clinics = $wpdb->get_col("select cli_ID from ci_clinic_collector where col_ID = {$id} ", 0);
    $rep_selected = $wpdb->get_col("select rep_ID from ci_rep_collector where col_ID = {$id} ", 0);

}else{
    $col = array();
    $col['col_tox'] = 1;
}

if($_POST['clinic_id']>0)
{
    $cli_id = $_POST['clinic_id'];
}


if($cli_id > 0)
{
    $cli = $wpdb->get_row("select * from ci_clinic where `cID`={$cli_id}", ARRAY_A);
    if($lab_id==0)
    {
        $lab_id = $cli['cli_lab-ID'];
    }
}



if($lab_id==0)
{
    $lab_id = CI_Base_Lab::get_current_lab_id();
}

if(($current_role=='lab_rep' || $current_role=='lab_rep_w2'))
{
    $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);
}else if($lab_id>0 && !defined('TRPDD_SAME_LAB')){
    $reps = $wpdb->get_results("select * from ci_lab_reps where lab_ID = '{$lab_id}' {$where_rep} ORDER BY rep_first ASC ", ARRAY_A);
}else{
    $reps = $wpdb->get_results("select * from ci_lab_reps where 1 {$where_rep} ORDER BY rep_first ASC ", ARRAY_A);
}

if($lab_id>0)
{
    $lab = $wpdb->get_row("select * from ci_labs where lab_ID = {$lab_id}", ARRAY_A);
    $clis = $wpdb->get_results("select * from ci_clinic where  cli_status=1", ARRAY_A);

}else{
    $clis = $wpdb->get_results("select * from ci_clinic where cli_status=1", ARRAY_A);
}



?>
<div class="wrap">
    <h2>COLLECTOR TRAINING FORM <a class="add-new-h2" href="<?php echo Tr_Base_Class_V6::link(array('act'=>'list','id'=>''))?>">List Collectors</a></h2>
    <?php Tr_Session_Class::show()?>
    <form method="post" id="lab_change_div" class="need_validate">
        <div class="frow">
            <div class="col2 ilabel">
                <label>Laboratory</label>
                <?php if(tr_current_role_in(array('administrator','AdminCIDW','administrator_p','clms')) && ($blog_id==1)):?>
                    <?php tr_select_option('col[lab_ID]',$labs,$lab_id,array('attr'=>'required','id'=>'lab_change'))?>
                <?php else:?>
                    <input type="text" name="lab[lab_name]" value="<?php echo $lab['lab_name']?>" readonly required=""/>
                    <input type="hidden" name="col[lab_ID]" value="<?php echo $lab_id?>">
                <?php endif;?>
            </div>
        </div>
        <div class="frow">
            <h4>Sales Representative</h4>
        </div>

        <div class="frow blabel">
            <?php if($current_role!='lab_rep' && $current_role!='lab_rep_w2'):?>
                <div class="col3">
                    <label>Rep Name</label>
                    <select name="col[rep_ID][]" class="reps js_select2" multiple required="">
                        <?php
                        foreach($reps as $rep){
                            $selected = ($rep['rep_ID']==@$col['rep_ID'] || in_array($rep['rep_ID'],$rep_selected))? 'selected':'';
                            if(!$selected && $rep['rep_inactive'])continue;

                            if(empty($rep['rep_phone']))$rep['rep_phone'] = $rep['rep_cell'];
                            echo '<option '.$selected.' value="'.$rep['rep_ID'].'" data-phone="'.$rep['rep_phone'].'" data-email="'.$rep['rep_email'].'">'.stripslashes($rep['rep_first'].' '.$rep['rep_last']).'</option>';
                        }
                        ?>
                    </select>
                </div>
            <?php else:?>
                <div class="col3">
                    <label>Name</label>
                    <input type="text" name="rep[rep_name]" value="<?php echo $rep['rep_first'].' '.$rep['rep_last']?>" readonly required=""/>
                    <input type="hidden" name="col[rep_ID]" value="<?php echo $rep['rep_ID']?>"/>
                </div>
            <?php endif;?>
            <div class="col3">
                <label>Phone</label>
                <?php if(empty($rep['rep_phone']))$rep['rep_phone'] = $rep['rep_cell'];?>
                <input type="text" class="has_phone" name="rep[rep_phone]" value="<?php echo $rep['rep_phone']?>" <?php if(!empty($rep['rep_phone']))echo'readonly'?>  />
            </div>
            <div class="col3">
                <label>Email</label>
                <input type="email" name="rep[rep_email]" value="<?php echo $rep['rep_email']?>" <?php if(!empty($rep['rep_email']))echo'readonly'?>  />
            </div>
        </div>
        <div class="frow blabel">

            <div class="col3">
                <label>Date of Request</label>
                <input type="text" class="hasdate today" name="col[col_rdate]" value="<?php echo @$col['col_rdate']?>" required=""/>
            </div>
            <div class="col3">
                <label>Date training needs to be completed by</label>
                <input type="text" class="hasdate"  name="col[col_cdate]" value="<?php echo @$col['col_cdate']?>" required=""/>
            </div>
            <div class="col3">
                <label>Collector start date in the office</label>
                <input type="text" class="hasdate"  name="col[col_sdate]" value="<?php echo @$col['col_sdate']?>" required=""/>
            </div>
        </div>

        <div class="frow">
            <h4>Collector</h4>
            <input type="hidden" name="col[col_ID]" value="<?php echo @$col['col_ID']?>"/>
        </div>


        <?php if($act=="edit"){?>
            <div class="frow blabel">
                <div class="col3">
                    <label>First Name</label>
                    <input type="text" class="cfname" name="col[col_first]" value="<?php echo @$col['col_first']?>" required=""/>

                </div>
                <div class="col3">
                    <label>Last Name</label>
                    <input type="text" class="cfname" name="col[col_last]" value="<?php echo @$col['col_last']?>" required=""/>
                </div>
                <div class="col3">
                    <label>Phone</label>
                    <input type="text" class="has_phone" name="col[col_phone]" value="<?php echo @$col['col_phone']?>" required=""/>
                </div>

            </div>

            <div class="frow blabel">
                <div class="col3">
                    <label>Email</label>
                    <input type="email" name="col[col_email]" value="<?php echo @$col['col_email']?>" required=""/>
                </div>
                <div class="col3">
                    <label>Training Location</label>
                    <?php tr_select_option('col[col_location]',$locations,@$col['col_location'],array('valuekey'=>true))?>
                </div>
                <div class="col3"></div>
                <div class="col3"></div>
            </div>
        <?php }else{?>
            <div class="frow blabel">
                <div class="col6"><label>First Name</label></div>
                <div class="col6"><label>Last Name</label></div>
                <div class="col6"><label>Phone</label></div>
                <div class="col6"><label>Email</label></div>
                <div class="col6"><label>Training Location</label></div>
            </div>
            <ol id="doctors_dv">

                <?php
                $max_j = count($col)>0? count($col) : 1;
                for($j = 0;$j<$max_j;$j++):?>
                    <li class="frow blabel pdoctorrow <?php if($j==0)echo 'default'?>">
                        <div class="col6">
                            <input type="text" class="auto_collector_first" name="cols[<?php echo $j?>][col_first]" value="" required=""/>

                            <input type="hidden" class="col_id" name="cols[<?php echo $j?>][col_ID]" value="" />
                        </div>
                        <div class="col6">
                            <input type="text" class="auto_collector_last" name="cols[<?php echo $j?>][col_last]" value="" required=""/>
                        </div>
                        <div class="col6">
                            <input type="text" class="col_phone" name="cols[<?php echo $j?>][col_phone]" value="" required=""/>
                        </div>

                        <div class="col6">
                            <input type="text" class="col_email" name="cols[<?php echo $j?>][col_email]" value="" required=""/>
                        </div>
                        <div class="col6">
                            <?php tr_select_option('cols['.$j.'][col_location]',$locations,@$col['col_location'],array('valuekey'=>true))?>
                        </div>
                        <div class="col6">
                            <?php if($act!='print'):?>
                                <a class="button rm_doctor_row">-</a>
                            <?php endif;?>
                        </div>
                    </li>
                <?php endfor;?>
            </ol>
            <?php if($act!='print'):?>
                <a class="button add_doctor_row" data-id="<?php echo $j?>">+</a>
                <script>
                    jQuery(function ($) {
                        $('.auto_collector_first').each(function() {
                            var $this = $(this);
                            var $pr = $this.parents('.pdoctorrow');
                            $(this).autocomplete({
                                source: ajaxurl + '?action=ajax&autocomplete=auto_collector_first',
                                minLength: 1,
                                select: function (event, ui) {
                                    if (ui.item) {
                                        $('.auto_collector_last',$pr).val(ui.item.col_last);
                                        $('.col_phone',$pr).val(ui.item.col_phone);
                                        $('.col_email',$pr).val(ui.item.col_email);
                                        $('.col_id',$pr).val(ui.item.col_ID);
                                        location.href = "<?php echo Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => ''))?>&id=" + ui.item.col_ID;
                                    }
                                }
                            });
                        });
                        $('.auto_collector_last').each(function() {
                            var $this = $(this);
                            var $pr = $this.parents('.pdoctorrow');
                            $(this).autocomplete({
                                source: ajaxurl + '?action=ajax&autocomplete=auto_collector_last',
                                minLength: 1,
                                select: function (event, ui) {
                                    if (ui.item) {
                                        $('.auto_collector_first',$pr).val(ui.item.col_first);
                                        $('.col_phone',$pr).val(ui.item.col_phone);
                                        $('.col_email',$pr).val(ui.item.col_email);
                                        $('.col_id',$pr).val(ui.item.col_ID);
                                        location.href = "<?php echo Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => ''))?>&id=" + ui.item.col_ID;
                                    }
                                }
                            });
                        });
                    })
                    </script>
            <?php endif;?>
        <?php }?>

        <div class="frow">
            <h4>Available Training Sessions</h4>
        </div>

        <div class="frow ilabel">
            <?php
            $sessions = array(
                '9AM EST Tuesday' => '9AM EST Tuesday',
                '9AM EST Thursday' => '9AM EST Thursday',
                '2PM EST Tuesday'=>'2PM EST Tuesday',
                '2PM EST Thursday'=> '2PM EST Thursday'
            );
            tr_radio('col[col_session]',$sessions,$col['col_session']);
            ?>

        </div>


        <div class="frow">
            <h4>Office Information</h4>
        </div>

        <div class="frow blabel">
            <div class="col3">
                <label>Practice Name</label>

                <select name="col[cli_ID][]" required="" class="clinic_select js_select2" multiple="multiple" style="height: 100px;">
                    <?php
                    foreach($clis as $cli){
                        $docs = $wpdb->get_results("select doc.* from ci_doc doc
                                            JOIn ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
                                            where cd.cli_ID = {$cli['cID']}");
                        $cli['physicians'] = array();
                        foreach($docs as $doc)
                        {
                            $cli['physicians'][] = $doc->doc_first.' '.$doc->doc_last;
                        }
                        $cli['physicians'] = implode(', ',$cli['physicians']);
                        $in_clinic = $cli_id==$cli['cID'] || in_array($cli['cID'],$clinics);

                        echo '<option value="'.$cli['cID'].'"
                        data-rep="'.$cli['cli_rep-ID'].'"
                        data-physicians="'.$cli['physicians'].'"
                        data-street="'.$cli['cli_street'].'"
                        data-phone="'.$cli['cli_om-phone'].'"
                        data-fax="'.$cli['cli_om-fax'].'" '.selected($in_clinic,true,false).'>'.stripslashes($cli['cli_clinic']).'</option>';
                    }
                    ?>
                </select>


            </div>
            <div class="col3">
                <label>Physicians</label>
                <input type="text" name="col[col_physicians]" value="<?php echo $cli['physicians']?>"/>
                <label>Telephone Number</label>
                <input type="text" class="has_phone"  name="cli[cli_om-phone]" readonly value="<?php echo $cli['cli_om-phone']?>"/>
            </div>
            <div class="col3">
                <label>Address</label>
                <input type="text" name="col[col_street]" value="<?php echo $cli['cli_street']?>"/>
                <label>Fax Number</label>
                <input type="text" name="cli[cli_om-fax]" readonly value="<?php echo $cli['cli_om-fax']?>"/>
            </div>

        </div>



        <div class="frow">
            <h4>Please check all that apply</h4>
        </div>

        <div class="frow iblabel">
            <label>
                <input type="hidden" name="col[col_poc-cups]" value="0"/>
                <input type="checkbox" name="col[col_poc-cups]" value="1" <?php checked($col['col_poc-cups'])?>/>
                POC Cups
            </label>
            <label>
                <input type="hidden" name="col[col_fax-result]" value="0"/>
                <input type="checkbox" name="col[col_fax-result]" value="1" <?php checked($col['col_fax-result'])?>/>
                Results Faxed
            </label>
            <label>
                <input type="hidden" name="col[col_analyser]" value="0"/>
                <input type="checkbox" name="col[col_analyser]" value="1" <?php checked($col['col_analyser'])?>/>
                Office Analyzer
            </label>
            <label>
                <input type="hidden" name="col[col_portal]" value="0"/>
                <input type="checkbox" name="col[col_portal]" value="1" <?php checked($col['col_portal'])?>/>
                Results via online portal
            </label>
            <label>
                <input type="hidden" name="col[col_tox]" value="0"/>
                <input type="checkbox" name="col[col_tox]" value="1" <?php checked($col['col_tox'])?>/>
                Tox
            </label>
            <label>
                <input type="hidden" name="col[col_dna]" value="0"/>
                <input type="checkbox" name="col[col_dna]" value="1" <?php checked($col['col_dna'])?>/>
                DNA
            </label>
            <label>
                <input type="hidden" name="col[col_blood]" value="0"/>
                <input type="checkbox" name="col[col_blood]" value="1" <?php checked($col['col_blood'])?>/>
                Blood
            </label>
            <label>
                <input type="hidden" name="col[col_allergy]" value="0"/>
                <input type="checkbox" name="col[col_allergy]" value="1" <?php checked($col['col_allergy'])?>/>
                Allergy
            </label>
            <label>
                <input type="hidden" name="col[col_ref_tox]" value="0"/>
                <input type="checkbox" name="col[col_ref_tox]" value="1" <?php checked($col['col_ref_tox'])?>/>
                Ref Tox
            </label>
            <label>
                <input type="hidden" name="col[col_test_results]" value="0"/>
                <input type="checkbox" name="col[col_test_results]" value="1" <?php checked($col['col_test_results'])?>/>
                Test Results
            </label>
        </div>

        <div class="frow">
            <h4>NOTES:</h4>
        </div>

        <div class="frow">
            <textarea  name="col[col_notes]"><?php echo @$col['col_notes']?></textarea>
        </div>
        <?php if($can_edit){?>
        <div class="frow">
            <input type="submit" class="button-primary" value="Submit">
            <input type="hidden" name="tr_action" value="form_collector_request">
        </div>
        <?php }?>

    </form>
</div>
<script>
    jQuery(function($){
        $('body').on('change','[name="col[cli_ID][]"]',function(){
            $('[name="col[col_physicians]"]').val($(this).find(':selected').data('physicians'));
            $('[name="col[col_street]"]').val($(this).find(':selected').data('street'));
            $('[name="cli[cli_om-phone]"]').val($(this).find(':selected').data('phone'));
            $('[name="cli[cli_om-fax]"]').val($(this).find(':selected').data('fax'));
        })
        $('body').on('change','[name="col[rep_ID]"]',function(){
            $('.clinic_select option').hide();
            $('.clinic_select [data-rep="'+$(this).val()+'"]').show();
            $('.clinic_select option').each(function(){
                if($(this).css('display')=='none'){
                    $(this).removeAttr('selected');
                }
            })
            var $em = $('[name="rep[rep_email]"]');
            var $ep = $('[name="rep[rep_phone]"]');
            $ep.val($(this).find(':selected').data('phone'));
            $em.val($(this).find(':selected').data('email'));
        });
        $('body').on('change','[name="col[rep_ID][]"]',function(){
            $('.clinic_select option').hide();
            $('option:selected',$(this)).each(function(){
                var $vl = $(this).attr('value');
                $('.clinic_select [data-rep="'+$vl+'"]').show();
            })

            $('.clinic_select option').each(function(){
                if($(this).css('display')=='none'){
                    $(this).removeAttr('selected');
                }
            })
            var $em = $('[name="rep[rep_email]"]');
            var $ep = $('[name="rep[rep_phone]"]');
            $ep.val($(this).find(':selected').data('phone'));
            $em.val($(this).find(':selected').data('email'));
        });
        $('[name="col[rep_ID]"]').change();
        $('[name="col[rep_ID][]"]').change();
        $('[name="col[cli_ID][]"]').change();
        function format_clinics (state) {
            if (!state.id) { return state.text; }
            if($('#col_cliid option[value="'+state.id+'"]').css('display')=='none')return null;
            var $state = $(
                '<span data-id="'+state.id+'">' + state.text + '</span>'
            );
            return $state;
        };

        $(".js_select2_custom").select2({
            templateResult: format_clinics
        }).next('.select2').addClass('required');
    })
</script>
<?php
if(isset($_GET['debug'])){
    $content = ob_get_clean();
    $content = str_replace('required','datarequired',$content);
    echo $content;
}
?>
