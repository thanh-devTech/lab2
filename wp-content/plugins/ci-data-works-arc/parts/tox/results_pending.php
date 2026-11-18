<?php
global $wpdb,$current_role;
$user_id = get_current_user_id();

include_once(TRCPDD_PATH.'pages/formdata.php');

$where = '';
$orderby = ' order by ba.batch_id desc,ba.lab asc';
$order = @$_REQUEST['order']=='desc'?'desc':'asc';

$joins = '';
//page
$row_per_page = 10;
$pagination = new Tr_Pagination_class($row_per_page);
$offset = $pagination->offset();


if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $sql .= " and ba.lab_id in ( {$lab_str} ) ";
}


if(tr_current_role_in(array('office_manager','multi_site_manager','clinic'))){

    $cid = get_user_meta($user_id,'_clinic_id',true);
    $clinics = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
    $clinics = implode(',',$clinics);
    $where.= " and ba.cli_ID in ({$clinics}) ";

}
else if($user_id>0 && !tr_current_role_in( array('administrator','AdminCIDW','administrator_p','scientist','labmanager','lab_tech')) )
{
    $where.= " and re.author_id = {$user_id} ";
}


$_REQUEST['s'] = trim(@$_REQUEST['s']);

if(!empty($_REQUEST['s']) || !empty($_REQUEST['drug'])){

    if(@$_REQUEST['drug']=='Alcohol')
    {
        $where .= $wpdb->prepare(" and ba.panel_tests_requested like %s and re.sample_type !='oral' ", '%ethanol*%');
    }
    else if(@$_REQUEST['drug']=='Methamphetamine DL')
    {
        $where .= $wpdb->prepare(" and ba.panel_tests_requested like %s and re.sample_type !='oral'  ", '%Methamphetamine d/l Isomers%');
        $joins.= " join ci_results rsm ON rsm.report_id = re.id and rsm.drug='METHAMPHETAMINE' and rsm.`pos-neg`='POS' ";
    }

    if(stripos($_REQUEST['s'],'medication:')!==false)
    {
        $pn = str_ireplace('medication:','',$_REQUEST['s']);
        $where .= $wpdb->prepare(" and re.medications like %s ", '%' . $pn . '%');
    }
    else if(stripos($_REQUEST['s'],'pos:')!==false)
    {
        $pn = trim(str_ireplace('pos:','',$_REQUEST['s']));
        if(strpos($pn,' ')>0)
        {
            list($number,$pn) = explode(' ',$pn,2);
        }
        $joins.= " join ci_results rspos ON rspos.report_id = re.id and rspos.drug='{$pn}' and rspos.`pos-neg`='POS' ";
        if($number>0)
        {
            $joins.= " and rspos.conc < {$number} ";
        }
    }
    else if(stripos($_REQUEST['s'],'panel:')!==false)
    {
        $pn = str_replace('panel:','',$_REQUEST['s']);
        $where .= $wpdb->prepare(" and ba.panel_tests_requested like %s ", '%' . $pn . '%');
    }
    else if($_REQUEST['s']=='ethanol*') {

        $where .= $wpdb->prepare(" and ba.panel_tests_requested like %s ", '%' . $_REQUEST['s'] . '%');
    }
    else if($_REQUEST['s']=='rejected'){
        $where .= " and (ba.batch_status like 're\_%' or ba.batch_status='rejected')";
    }
    else{

        if(strpos($_REQUEST['s'], ',')>0)
        {
            list($firstname, $lastname) = explode(',',$_REQUEST['s']);
            $firstname = trim($firstname);
            $lastname = trim($lastname);
            $explodeString = "%{$firstname}%{$lastname}%";
            $where.= $wpdb->prepare(" and (ba.patient_name like %s)",$explodeString);
        }
        else{
            $where.= $wpdb->prepare(" and (ba.patient_name like %s or ba.assen = %s)",$_REQUEST['s'].'%',$_REQUEST['s']);
        }
    }

}
$where.= " and ( re.estatus !='C' or re.estatus is null ) ";



if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $where .= " and ba.lab_id in ( {$lab_str} ) ";
}

$joins .= " JOIN ci_batches ba ON ba.assen = re.patient_id
    JOIN ci_labs lab ON lab.lab_ID = ba.lab_id 
    JOIN ci_results rs ON re.id = rs.report_id and rs.`pos-neg`='PEN'  ";

$sql_total = "select count(distinct(ba.batch_id)) as c from ci_reports re
    {$joins}
        where 1 {$where}";

$sql_limit = "select distinct(ba.batch_id) from ci_reports re
    {$joins}
        where 1 {$where}
        order by ba.batch_id desc limit {$offset},{$row_per_page}";

$time_start  = microtime(true);
$pagination->setTotal($wpdb->get_var($sql_total));
$q_total = microtime(true) - $time_start;

$time_start  = microtime(true);
$batch_ids = $wpdb->get_col($sql_limit);
$q_limit = microtime(true) - $time_start;

$batch_ids = implode("','",$batch_ids);
$where.= " and ba.batch_id in('{$batch_ids}') ";


$sql = "select ba.batch_id,re.created_date,re.collection_date,
        lab.lab_ID,
        lab.lab_dba_init
    from ci_reports re
    {$joins}
    where 1 {$where} group by ba.mid {$orderby}";


$time_start  = microtime(true);
$results = $wpdb->get_results($sql);
$count_rows = count($results);


if(isset($_REQUEST['debug'])){
    echo microtime(true) - $time_start,'<br>';
    echo 'qtotal: '.$q_total,'<br>';
    echo '$sql_limit: '.$sql_limit,"<br><br>";
    echo 'qlimit: '.$q_limit,'<br><br>';
    echo $sql,'<br><br>';
    var_dump($results);
}
?>
<h2>Results</h2>
<?php Tr_Session_Class::show()?>
<form method="get">
    <input type="hidden" name="page" value="<?php echo $_GET['page']?>"/>
    <input type="hidden" name="tab" value="<?php echo $_GET['tab']?>"/>
    <label>Patient</label>
    <input type="text" name="s" value="<?php echo $_REQUEST['s']?>"/>
    <input type="submit" class="button bsty6" value="Search"/>
    <label><input type="radio" class="drugs" name="drug" value="Methamphetamine DL"/> Methamphetamine DL</label>
    <label><input type="radio" class="drugs" name="drug" value="Alcohol"/> Alcohol</label>


</form>

<div class="tablenav"><?php if($pagination)echo $pagination->pagination() ?></div>

<div class="wraptbl">
<table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl" data-count="<?php echo @$count_rows?>">
    <thead>
    <tr>
        <th style="width:6%">#</th>
        <th style="width: 6%;">Batch</th>
        <th style="width: 5%;">Position</th>
        <th  style="width: 10%;">Patient Name</th>
        <th  style="width: 10%;">Clinic</th>
        <th  style="width: 9%;">Doctor</th>
        <th style="width: 16%;">Missing Tests</th>
        <th  style="width: 10%;">Date</th>
        <th style="width:2%"></th>
        <th style="width:5%">Status</th>
        <th style="width:15%">Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php
    $batches = array();
    $type = 1;
    if(count($results)>0)
    {
        foreach($results as $i => $row) {
            if(!is_array($batches[$row->batch_id]))
            {
                $batches[$row->batch_id] = array();
            }
            //$batches[$row->batch_id]['date'] = $row->collection_date;
            $batches[$row->batch_id]['date'] = $row->created_date;

            if($type==1)
            {
                $batches[$row->batch_id]['labs'][$row->lab_ID] = $row->lab_dba_init;
            }else{
                $batches[$row->batch_id]['labs'][$row->lab_ID] = $row->lab_dba_init;
            }
            $batches[$row->batch_id]['count']++;

        }
        ?>

        <?php
        foreach($batches as $batch_id => $d)
        {
            ?>
            <tr class="parent" data-p="batch_<?php echo $batch_id?>">
                <td><i></i></td>
                <td><a class="show_batch_result_miss" title="<?php echo $d['count']?>" data-batch_id="<?php echo $batch_id?>"><?php echo $batch_id?></a></td>

                <td><a class="show_batch_result_files dashicons dashicons-archive" data-batch_id="<?php echo $batch_id?>"></a></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td><?php echo $d['date']?></td>
                <td></td>
                <td></td>
                <td>
                    <a class="uploadpdf" data-batch_id="<?php echo $batch_id?>">Upload PDF</a>
                </td>
            </tr>
            <tr class="child p_batch_<?php echo $batch_id?>">
                <td colspan="12" class="nopadding">
                    <table class="report_tbl">
                        <?php
                        if($type==1) {
                            foreach ($d['labs'] as $lab_id => $lab_name) {
                                ?>
                                <tr class="parent_c" data-p="lab_<?php echo $lab_id.$batch_id ?>"  data-batch_id="<?php echo $batch_id?>" data-lab_id="<?php echo $lab_id?>">
                                    <td style="width: 6%;"><i></i></td>
                                    <td style="width:6%"><?php echo $lab_name ?></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td align="right">
                                        <a class="btn_sell_all" sel="true" >Select all</a> -
                                        <a class="bulk_edit_button">Bulk Edit</a> -
                                        <a class="delete_all_button" href="<?php Tr_Base_Class_V6::link(array('tr_action'=>'ci_results_delete_all','lab_id'=>$lab_id,'batch_id'=>$batch_id))?>">Delete All</a> -
                                        <a class="approve_all" href="<?php Tr_Base_Class_V6::link(array('tr_action'=>'ci_results_approve_all','lab_id'=>$lab_id,'batch_id'=>$batch_id))?>">Approve All</a> -
                                    </td>
                                    <td style="width:15%">
                                        <a class="uploadpdf">Upload PDF</a>
                                    </td>
                                </tr>
                                <tr class="child_c p_lab_<?php echo $lab_id.$batch_id ?>" data-batch_id="<?php echo $batch_id?>" data-lab_id="<?php echo $lab_id?>">
                                    <td colspan="11" class="nopadding"></td>
                                </tr>
                                <?php
                            }
                        }else{
                            ?>
                            <tr class="child_c p_lab_<?php echo $lab_id.$batch_id ?>" data-batch_id="<?php echo $batch_id?>" data-lab_id="<?php echo $lab_id?>">
                                <td colspan="11" class="nopadding"></td>
                            </tr>
                            <?php
                        }
                        ?>
                    </table>
                </td>
            </tr>
            <?php
        }
    }else{
        ?>
        <tr>
            <td colspan="11">No Results Found.</td>
        </tr>
        <?php
    }



    ?>
    </tbody>
</table>
</div>


<script>
    jQuery(function($){
        function trcpp_load_results($batch_id,$lab_id)
        {
            var $obj = $('tr.p_lab_'+$lab_id+$batch_id+' td');
            if($obj.html()!='')
            {
                return;
            }else{
                $obj.html('<div class="loading center"></div>');
            }
            $.ajax({
                data:{tr_action:'ci_load_results_pending',batch_id:$batch_id,lab_id:$lab_id},
                type:'post',
                success:function(rs)
                {
                    var $html = $(rs);
                    $obj.html($html);
                    $('.cid',$html).each(function(){
                        $(this).prepend('<input type="checkbox" value="'+$(this).html()+'"/>');
                    })
                }
            })
        }
        $('body').on('click','a.delete',function(){
            if(confirm('Do you want delete?')==false)
            {
                return false;
            }
        })
        $('a.approve_all').click(function(){
            if(confirm('Do you want Approve all?')==true)
            {
                var $pr = $(this).parents('tr');
                var $bid=$pr.data('batch_id');
                var $labid = $pr.data('lab_id');
                $ids = '';
                if($labid)
                {
                    //find checked
                    $('.p_lab_'+$labid+$bid+' input:checked').each(function(){
                        $ids+=$(this).val()+',';
                    })
                }
                location =$(this).attr('href')+'&ids='+$ids;
            }
            return false;
        })
        $('a.delete_all_button').click(function(){
            if(confirm('Do you want Delete all?')==true)
            {
                var $pr = $(this).parents('tr');
                var $bid=$pr.data('batch_id');
                var $labid = $pr.data('lab_id');
                if($labid)
                {
                    $('.p_lab_'+$labid+$bid+' input:checked').each(function(){
                        $ids+=$(this).val()+',';
                    })

                }
                if($ids==''){
                    alert('Please select Results');
                    return false;
                }
                location =$(this).attr('href')+'&ids='+$ids;
            }
            return false;
        })
        $('a.uploadpdf').click(function(){
            var $pr = $(this).parents('tr');
            var $bid=$pr.data('batch_id');
            var $labid = $pr.data('lab_id');
            tb_show('Upload to Box','?tr_action=ci_upload_result_pdf_to_box&batch_id='+$bid+'&lab_id='+$labid+'&TB_iframe=true&width=400&height=300');
            return false;
        })
        $('a.show_batch_result_miss').click(function(){
            var $bid = $(this).data('batch_id');
            tb_show('Batch '+$bid,'?tr_action=ci_show_batch_result_miss&batch_id='+$bid+'&width=800');
            return false;
        })
        $('a.show_batch_result_files').click(function(){
            var $bid = $(this).data('batch_id');
            tb_show('Uploaded Files in Batch '+$bid,'?tr_action=ci_show_batch_result_files&batch_id='+$bid+'&width=800');
            return false;
        })


        $('a.bulk_edit_button').click(function(){
            $ids = '';
            var $pr = $(this).parents('tr');
            var $bid=$pr.data('batch_id');
            var $labid = $pr.data('lab_id');
            if($labid)
            {
                $('.p_lab_'+$labid+$bid+' input:checked').each(function(){
                    $ids+=$(this).val()+',';
                })

            }
            if($ids==''){
                var $url = '?tr_action=ci_results_bulk_edit';
                tb_show('Bulk Edit',$url+'&width=800');
                return false;
            }
            var $url = '?tr_action=ci_results_bulk_edit&ids='+$ids;
            tb_show('Bulk Edit',$url+'&width=800');
            return false;
        });


        $('a.btn_sell_all').click(function(){
            var sleat=$(this).attr("sel");

            if(sleat=="true"){
                $("input:checkbox").prop('checked', true);
                $(this).attr("sel",false);
                $(this).html("Unselect All")
            }else{
                $("input:checkbox").prop('checked', false);
                $(this).attr("sel",true);
                $(this).html("Select All")
            }
            return false;
        })


        $('body').on('click','a.edit_link',function(){
            location = '<?php Tr_Base_Class_V6::link(array('act'=>'edit','id'=>''))?>&id='+$(this).parents('tr').data('id');
            return false;
        });
        $('body').on('click','a.reverse_link',function(){
            location = '<?php Tr_Base_Class_V6::link(array('tr_action'=>'ci_results_reverse','id'=>''))?>&id='+$(this).parents('tr').data('id');
            return false;
        });
        $('body').on('click','a.pdf_link',function(){
            location = '<?php Tr_Base_Class_V6::link(array('tr_action'=>'ci_results_pdf','id'=>''))?>&id='+$(this).parents('tr').data('id');
            return false;
        });
        $('body').on('click','a.approve_link',function(){
            location = '<?php Tr_Base_Class_V6::link(array('tr_action'=>'ci_results_approve','id'=>''))?>&id='+$(this).parents('tr').data('id');
            return false;
        });
        $('body').on('click','a.delete_link',function(){
            if(confirm('Do you want delete?')==true)
            {
                location = '<?php Tr_Base_Class_V6::link(array('tr_action'=>'ci_results_delete','id'=>''))?>&id='+$(this).parents('tr').data('id');
            }
            return false;
        });

        $('tr.parent').click(function(){
            $('tr.child').hide();
            if($(this).data('showed')!='1')
            {
                $('tr.p_'+$(this).data('p')).show();
                $('tr.parent').data('showed','0');
                $(this).data('showed','1');
                $(this).addClass('active');
                setCookie('active_rs',$(this).data('p'));
                var $obj = $('tr.p_'+$(this).data('p'));
                $('body,html').scrollTop($obj.offset().top-80);
                if($('tr.p_'+$(this).data('p')+' tr.parent_c').length==1)
                {
                    $('tr.p_'+$(this).data('p')+' tr.parent_c').click();
                }
            }else{
                $('tr.p_'+$(this).data('p')).hide();
                $(this).data('showed','0');
                $(this).removeClass('active');
            }
            return false;
        });
        $('tr.parent_c').click(function(){
            $('tr.child_c').hide();
            if($(this).data('showed')!='1')
            {
                $('tr.p_'+$(this).data('p')).show();
                $('tr.parent_c').data('showed','0');
                $(this).data('showed','1');
                $(this).addClass('active');
                setCookie('active_rs',$(this).data('p'));
                var $obj = $('tr.p_'+$(this).data('p'));
                $('body,html').scrollTop($obj.offset().top-150);
                $batch_id = $obj.data('batch_id');
                $lab_id = $obj.data('lab_id');
                trcpp_load_results($batch_id,$lab_id);
            }else{
                $('tr.p_'+$(this).data('p')).hide();
                $(this).data('showed','0');
                $(this).removeClass('active');
            }
            return false;
        });
        $('.drugs').click(function(){
            $(this).parents('form').submit();
        })
        $old_active = getCookie('active_rs');
        if($old_active && $old_active!=null)
        {
            var $obj = $('tr.p_'+$old_active);
            try
            {
                $obj.show();
                $('[data-p="'+$old_active+'"]').data('showed','1');
                if($old_active.indexOf('lab_')>-1)
                {
                    $obj.parents('.child').show();
                }
                $('body,html').scrollTop($obj.offset().top-150);
                $batch_id = $obj.data('batch_id');
                $lab_id = $obj.data('lab_id');
                trcpp_load_results($batch_id,$lab_id);
            }catch(e){}

        }



    })


</script>