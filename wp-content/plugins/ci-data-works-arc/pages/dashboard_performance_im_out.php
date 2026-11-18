<?php
global $wpdb,$current_role;
$today_time         = strtotime( Tr_Base_Class_V4::date());
$current_month      = date('Y-m',$today_time);
$current_month_end  = date('Y-m-t',$today_time);
$current_prescribed = isset($current_prescribed)? $current_prescribed : 0;
$clinic_groups      = array();
$user_id            = get_current_user_id();
$is_group_manager   = false;
if(empty($current_clinic))
{
    $current_clinic = get_user_meta($user_id,'_use_current_clinic_id',true);
}


switch ($current_role)
{
    case 'lab_rep':
    case 'lab_rep_w2':
        $rep_id = get_user_meta($user_id, '_rep_id', true);
        $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);


        $clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli where cli_status = 1 and `cli_rep-ID`={$rep_id} order by cli_clinic asc");
        break;
    case 'office_manager':
    case 'multi_site_manager':

        $cid = get_user_meta($user_id,'_clinic_id',true);
        $cids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
        $cids = implode(',',$cids);
        $where_clinic = " cli.cID in ({$cids}) ";


        $clinic_group_id = $wpdb->get_var("select meta_value from {$wpdb->usermeta} WHERE meta_key='_clinic_group' AND user_id = {$user_id}");
        if($clinic_group_id>0)
        {
            $clinic_groups = $wpdb->get_results("select * from ci_clinician_groups where clig_ID = {$clinic_group_id} and clig_inactive=0");
            $where_clinic = " or cli_group_ID = {$clinic_group_id} ";
            $is_group_manager = true;
        }
        $sql_clinic = "select * from ci_clinic cli WHERE cli_status = 1 and ( {$where_clinic} ) order by cli_clinic asc";

        $clinics = $wpdb->get_results($sql_clinic);

        if(count($clinics)>0)
        {
            $is_group_manager = true;
        }

        break;
    case 'lab':
        $lab_str = CI_Base_Lab::get_user_lab_ids_str();
        $clinic_groups = $wpdb->get_results("select * from ci_clinician_groups where clig_inactive=0");
        $clinics = $wpdb->get_results("select * from ci_clinic 
                    where cli_status = 1 
                    and `cli_lab-ID` in ({$lab_str})
                    order by cli_clinic asc");
        break;
    case 'distributor':
        $sql = "select * from ci_clinician_groups cg
                                JOIN `ci_distributors` dis
                                JOIN ci_lab_reps lr ON lr.rep_ID = cg.clig_rep_ID
                                left join {$wpdb->usermeta} um ON um.meta_key='_rep_id' and um.meta_value = lr.rep_ID

                                JOIN ci_marketing_groups mg ON mg.gr_distributor_ID = dis.dis_ID
                                            and (lr.rep_marketing_group_ID = mg.gr_ID or mg.gr_admins = um.user_id)
                                 WHERE dis.`dis_user_ID`=$user_id and cg.clig_inactive=0";
        if(isset($_REQUEST['debug3'])){
            echo $sql;
        }
        $clinic_groups = $wpdb->get_results($sql);

        $sql = "select * from ci_clinic cli
                                JOIN `ci_distributors` dis
                                JOIN ci_lab_reps lr ON  lr.rep_ID = cli.`cli_rep-ID`
                                JOIN ci_marketing_groups mg ON mg.gr_distributor_ID = dis.dis_ID
                                                            and lr.rep_marketing_group_ID = mg.gr_ID
                                 WHERE dis.`dis_user_ID`=$user_id 
                                 order by cli_clinic asc";
        if(isset($_REQUEST['debug4'])){
            echo $sql;
        }
        $clinics = $wpdb->get_results($sql);
        break;
    default:
        $clinic_groups = $wpdb->get_results("select * from ci_clinician_groups where clig_inactive=0");
        $clinics = $wpdb->get_results("select * from ci_clinic where cli_status = 1 order by cli_clinic asc");
        break;
}

$prescribed = array(
    '' => 'Prescribed:Vivitrol',
    'w_o' => 'w/o Vivitrol',
    'just_vivitrol' => 'just Vivitrol'
);
$months = $wpdb->get_results("select distinct(DATE_FORMAT(doc, '%Y-%m') ) as doc from ci_batches where doc > '2015-01-01' and doc<= '{$current_month_end} 23:59:59' order by doc desc");
?>
<style>#dashboard_performance_im_out{width:100% !important;clear:both;float:none !important}</style>
<select id="change_month_perfor" style="width:20%"  class="njs_select2">
    <option value="">Select Month</option>
    <?php
    foreach($months as $m)
    {
        $selected = $current_month == $m->doc? 'selected':'';
        echo '<option value="'.$m->doc.'" '.$selected.'>'.$m->doc.'</option>';
    }
    ?>
</select>
<?php if(!tr_current_role_in( array('office_manager')) || $is_group_manager){

    if(!tr_current_role_in(array( 'lab_rep','lab_rep_w2')) && count($clinic_groups)>0){
  ?>
    <select id="change_clinic_group" class="njs_select2">
        <option value="">Select Clinic Group</option>
        <?php
        foreach($clinic_groups as $c)
        {
            if(empty($current_cl_group)){
                $current_cl_group = $c->clig_ID;
            }
            $selected = $current_cl_group == $c->clig_ID? 'selected':'';
            echo '<option value="'.$c->clig_ID.'" '.$selected.'>'.$c->clig_name.'</option>';
        }
        ?>
    </select>
        <?php
    }else{
        if(empty($current_clinic))
        {
            $current_clinic = $clinics[0]->cID;
        }
    }
    ?>

    <select id="change_clinic" class="njs_select2">
        <option value="">Select Clinic</option>
        <?php
        if(tr_current_role_in(array('administrator','administrator_p','AdminCIDW'))){
            $selected = $current_clinic == 'all'? 'selected':'';
            echo '<option value="all" '.$selected.'>All Clinics</option>';
        }
        foreach($clinics as $c)
        {
            $selected = $current_clinic == $c->cID? 'selected':'';
            echo '<option value="'.$c->cID.'" '.$selected.'>'.$c->cli_clinic.'</option>';
        }
        ?>
    </select>
<?php
}
?>
<select id="change_prescribed" class="njs_select2">
    <?php
    foreach($prescribed as $k => $vl)
    {
        $selected = $current_prescribed == $k? 'selected':'';
        echo '<option value="'.$k.'" '.$selected.'>'.$vl.'</option>';
    }
    ?>
</select>
<div id="display_report_perform" style="margin-top:10px">
    <?php //include(TRCPDD_PATH.'pages/dashboard_get_report_perform_month.php');?>
</div>


<h3 style="line-height: 30px;padding-left: 20px;">Report 12 months &nbsp;&nbsp;
    <select id="change_medi_report_year" class="njs_select2">
        <?php
        $medis = tr_pdd_get_report_medis();
        foreach($medis as $txt =>$code)
        {
            echo '<option value="'.$code.'">'.$txt.'</option>';
        }
        ?>
    </select>
    <?php if($current_role!='office_manager' || $is_group_manager){

    if(!tr_current_role_in(array( 'lab_rep','lab_rep_w2')) && count($clinic_groups)>0){
        ?>
        <select id="change_clinic_group2" class="njs_select2">
            <option value="">Select Clinic Group</option>
            <?php
            foreach($clinic_groups as $c)
            {
                if(empty($current_cl_group)){
                    $current_cl_group = $c->clig_ID;
                }
                $selected = $current_cl_group == $c->clig_ID? 'selected':'';
                echo '<option value="'.$c->clig_ID.'" '.$selected.'>'.$c->clig_name.'</option>';
            }
            ?>
        </select>
        <?php }?>
        <select id="change_clinic2" class="njs_select2">
            <option value="">Select Clinic</option>
            <?php
            if(tr_current_role_in(array('administrator','administrator_p','AdminCIDW'))){
                $selected = $current_clinic == 'all'? 'selected':'';
                echo '<option value="all" '.$selected.'>All Clinics</option>';
            }
            foreach($clinics as $c)
            {
                $selected = $current_clinic == $c->cID? 'selected':'';
                echo '<option value="'.$c->cID.'" '.$selected.'>'.$c->cli_clinic.'</option>';
            }
            ?>
        </select>
    <?php }?>
    <select id="change_prescribed2" class="njs_select2">
        <?php

        foreach($prescribed as $k => $vl)
        {
            $selected = $current_prescribed == $k? 'selected':'';
            echo '<option value="'.$k.'" '.$selected.'>'.$vl.'</option>';
        }
        ?>
    </select>
</h3>
<div id="display_report_year">
    <?php //include(TRCPDD_PATH.'pages/dashboard_get_report_perform_year.php');?>
</div>

<script>
    jQuery(function($){
        $('body').on('change','#change_month_perfor,#change_clinic,#change_clinic_group,#change_prescribed',function(){
            var $mo = $('#change_month_perfor').val();
            var $cg = $('#change_clinic_group').val();
            var $cid = $('#change_clinic').val();
            var $pre = $('#change_prescribed').val();
            if($cg=='' && $cid=='')return;
            $('#display_report_perform').html('<div class="loading" style="min-height:200px">Loading...</div>');
            $.ajax({
                data:{tr_action:'ci_get_report_perform_month',m:$mo,cg:$cg,cid:$cid,pre:$pre},
                success:function(rs)
                {
                    $('#display_report_perform').html(rs);
                }
            })
        })
        $('#change_clinic_group').change(function(){
            if($(this).val()!==''){
                $('#change_clinic').val('');
            }
        })
        $('#change_clinic').change(function(){
            if($(this).val()!==''){
                $('#change_clinic_group').val('');
            }
        });

        $('body').on('change','#change_medi_report_year,#change_clinic2,#change_clinic_group2,#change_prescribed2',function(){
            var $med = $('#change_medi_report_year').val();
            var $cg = $('#change_clinic_group2').val();
            var $cid = $('#change_clinic2').val();
            var $pre = $('#change_prescribed2').val();
            if($cg=='' && $cid=='')return;
            $('#display_report_year').html('<div class="loading" style="min-height:200px">Loading...</div>');
            $.ajax({
                data:{tr_action:'ci_get_report_perform_year',med:$med,cg:$cg,cid:$cid,pre:$pre},
                success:function(rs)
                {
                    $('#display_report_year').html(rs);
                }
            })
        })
        $('#change_clinic_group2').change(function(){
            if($(this).val()!==''){
                $('#change_clinic2').val('');
            }
        })
        $('#change_clinic2').change(function(){
            if($(this).val()!==''){
                $('#change_clinic_group2').val('');
            }
        });
        setTimeout(function () {
            $('#change_month_perfor').change();
        },200);
        setTimeout(function () {
            $('#change_medi_report_year').change();
        },1000);


    })
</script>