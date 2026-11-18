<?php
global $wpdb,$current_role,$blog_id;
$user_id = get_current_user_id();

wp_enqueue_script('data-admin',TRCPDD_URL. 'js/admin.min.js',array(),TRCPDD_VS );

$where = 'WHERE ba.is_dna=1';
if(!empty($_REQUEST['s'])){
    $_REQUEST['s'] = trim($_REQUEST['s']);
    list($doc_first,$doc_last) = explode(' ',$_REQUEST['s']);
    if(empty($doc_last))$doc_last='askdfjasdf';
    $where.= $wpdb->prepare(" 
            and (ba.file_name like %s 
            or doc.doc_first like %s  or doc.doc_last like %s
            or doc.doc_last like %s or doc.doc_first like %s 
            or cli.cli_clinic like %s)",
        '%'.$_REQUEST['s'].'%',
        $doc_first.'%',$doc_last.'%',
        $doc_first.'%',$doc_last.'%',
        $_REQUEST['s'].'%');
}



$where_clis = '';

if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $where_clis.= " and cli.`cli_lab-ID` in ( {$lab_str} ) ";
}

if($current_role=='lab_rep'  || $current_role=='lab_rep_w2')
{
    $rep_id = get_user_meta(get_current_user_id(),'_rep_id',true);
    $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);

    $where_clis .= " and cli.`cli_rep-ID`={$rep_id} ";

}else if($current_role=='collector')
{
    $col_id = get_user_meta(get_current_user_id(),'_collector_id',true);
    $cli_ids = array();
    $cli_ids = $wpdb->get_col("select cli.cID from ci_clinic cli 
                    Left JOIn ci_clinic_collector cc ON cc.cli_ID = cli.cID
                    left join ci_lab_reps lr on cli.`cli_rep-ID` = lr.rep_ID
                    left join ci_rep_collector rc ON cli.`cli_rep-ID` = rc.rep_ID
                    where (cc.col_ID = {$col_id} or rc.col_ID = {$col_id})");
    $cli_ids = implode(',',$cli_ids);
    $where_clis.= " and cli.cID in ($cli_ids) ";

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

    $where_clis.=" and (cli_distributor_ID={$dis_id} or (`rep_marketing_group_ID` IN ({$g_ids}) or rep_distributor_ID={$dis_id})) ";
    $where_group.= " and gr_distributor_ID = {$dis_id} ";
}
else if($current_role == 'office_manager' || $current_role == 'clinic'){
    $cid = get_user_meta($user_id,'_clinic_id',true);
    $clinics = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
    $clinics = implode(',',$clinics);
    $where_clis.= " and cli.cID in ({$clinics}) ";
}
$where .= $where_clis;

$id = $_REQUEST['id'];
$act = $_REQUEST['act'];
if($act =='delete' && $id >0){
    $wpdb->delete('ci_igniting_qss',array('ig_ID'=>$id));
}

$sql_total = "select count(distinct(date_format(ba.created,'%Y%m%d'))) from ci_igniting_qss ba  
JOIN ci_clinic cli ON cli.cID = ba.cli_ID
              JOIN ci_doc doc ON doc.doc_ID = ba.doc_ID
              {$where}";

$row_per_page = 30;
$pagination = new Tr_Pagination_class2($row_per_page);
$offset = $pagination->offset();
$pagination->setTotal($wpdb->get_var($sql_total));


$sql_limit = "select distinct(date_format(ba.created,'%Y-%m-%d')) from ci_igniting_qss ba
JOIN ci_clinic cli ON cli.cID = ba.cli_ID
              JOIN ci_doc doc ON doc.doc_ID = ba.doc_ID
        {$where}
        ORDER BY ba.created desc  limit {$offset},{$row_per_page}";
$batch_ids = $wpdb->get_col($sql_limit);

$date_from = end($batch_ids);
$date_to = $batch_ids[0].' 23:59:59';
$where.= " and ba.created >='{$date_from}' and ba.created <= '{$date_to}' ";


$results = $wpdb->get_results("select ba.*,cli.cli_clinic,doc.doc_first,doc.doc_last 
            from ci_igniting_qss ba 
            JOIN ci_clinic cli ON cli.cID = ba.cli_ID
            JOIN ci_doc doc ON doc.doc_ID = ba.doc_ID
             {$where} ORDER BY ba.created desc");
?>
<h2>PGx Results</h2>
<?php Tr_Session_Class::show()?>
<form method="get">
    <input type="hidden" name="page" value="<?php echo $_GET['page']?>"/>
    <input type="hidden" name="tab" value="<?php echo $_GET['tab']?>"/>
    <label>Patient/Clinic/Doctor:</label>
    <input type="text" name="s" value="<?php echo $_REQUEST['s']?>"/>
    <input type="submit" class="button bsty6" value="Search"/>

</form>
<div class="tablenav"><?php if(isset($pagination))echo $pagination->pagination(array('m')) ?></div>

<div class="wraptbl">
<table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl" data-count="<?php echo @$count_rows?>">
    <thead>
    <tr>
        <th style="width:3%">#</th>
        <th  style="width: 25%;">Patient Name</th>
        <th  style="width: 25%;">Clinic</th>
        <th  style="width: 20%;">Doctor</th>
        <th  style="width: 80px;">Date</th>
        <th></th>
        <th style="width:50px"></th>
    </tr>
    </thead>

    <tbody>
    <?php
    $batches = array();
    if(count($results)>0)
    {
        foreach($results as $i => $row) {
            $created_time = strtotime($row->created);
            $row->batch_id = date('Ymd',$created_time);
            if(!is_array($batches[$row->batch_id]))
            {
                $batches[$row->batch_id] = array();
            }
            //$batches[$row->batch_id]['date'] = $row->collection_date;
            $batches[$row->batch_id]['date'] = date('Y-m-d',$created_time);
            $batches[$row->batch_id]['count_uploaded'] += ($row->box_uploaded>0)? 1 : 0;
            $batches[$row->batch_id]['rows'][] = $row;

        }
        ?>

        <?php
        foreach($batches as $batch_id => $d)
        {
            ?>
            <tr class="parent" data-p="batch_<?php echo $batch_id?>">
                <td><i></i></td>
                <td></td>
                <td></td>
                <td></td>
                <td><?php echo $d['date']?></td>
                <td align="right">
                    <a class="uploadpdf" data-date="<?php echo $d['date']?>">Upload PDF</a>
                    <?php if($d['count_uploaded']>= count($d['rows'])){?>
                        <span class="checkmark checked"></span>
                    <?php }else if($d['count_uploaded']>0){?>
                        <a class="uploadpdf2 dashicons dashicons-external" data-date="<?php echo $d['date']?>" title="Only Not Uploaded"></a>
                    <?php }?>
                </td>
                <td style="width:50px;"></td>
            </tr>
            <tr class="child p_batch_<?php echo $batch_id?>">
                <td colspan="7" class="nopadding">
                    <table class="report_tbl">
                        <?php foreach($d['rows'] as $row)
                        {
                            $result_pdf = WP_CONTENT_URL.'/results/'.$row->file_path.'/'.$row->file_name;
                            ?>
                            <tr class="childp">
                                <td style="width: 3%"></td>
                                <td style="width:25%"><?php echo $row->file_name?></td>
                                <td style="width:25%"><?php echo $row->cli_clinic?></td>
                                <td style="width:20%"><?php echo $row->doc_last.' '.$row->doc_first ?></td>
                                <td><?php echo date('Y-m-d',strtotime($row->doc))?></td>
                                <td class="actions" align="right">
                                    <a href="<?php echo $result_pdf?>" target="_blank">PDF</a> -
                                    <a class="delete" href="#" data-id="<?php echo $row->ig_ID?>">Delete</a>
                                </td>
                                <td style="width:50px;">
                                    <?php if($row->box_uploaded>0):?>
                                        <span class="dashicons dashicons-yes"></span>
                                    <?php endif;?>
                                </td>
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
            <td colspan="9">No Results Found.</td>
        </tr>
        <?php
    }



    ?>
    </tbody>
</table>
</div>

<script>
    jQuery(function($){

        $('a.delete').click(function () {
            if(confirm('Do you want delete?')==false)return false;
            $pr = $(this).parents('tr.childp');
            $.ajax({
                data:{act:'delete','id':$(this).data('id')},
                type:'post',
                success:function(rs)
                {
                    $pr.remove();
                }
            })
            return false;
        })
        $('a.btn_sell_all').click(function(){
            var sleat=$(this).attr("sel");
            $pid = $(this).parents('.parent_c').data('p');

            if(sleat=="true"){
                $(".p_"+$pid+" input:checkbox").prop('checked', true);
                $(this).attr("sel",false);
                $(this).html("Unselect All")
            }else{
                $(".p_"+$pid+" input:checkbox").prop('checked', false);
                $(this).attr("sel",true);
                $(this).html("Select All")
            }
            return false;
        })

        $('a.uploadpdf').click(function(){
            var $date = $(this).data('date');
            $ids = '';

            var $url = '?tr_action=ci_upload_qss_to_box&date='+$date+'&type=dna&kt=<?php echo time()?>&ids='+$ids;
            <?php if(isset($_GET['debug'])):?>
            $url+='&rsdb=1';
            <?php endif;?>
            tb_show('Upload to Box',$url+'&TB_iframe=true&width=400&height=300');
            return false;
        });

        $('a.uploadpdf2').click(function(){
            var $date = $(this).data('date');
            $ids = '';

            var $url = '?tr_action=ci_upload_qss_to_box&upload2&date='+$date+'&type=dna&kt=<?php echo time()?>&ids='+$ids;
            <?php if(isset($_GET['debug'])):?>
            $url+='&rsdb=1';
            <?php endif;?>
            tb_show('Upload to Box',$url+'&TB_iframe=true&width=400&height=300');
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
                var $obj = $(this);
                $('body,html').scrollTop($obj.offset().top-50);
            }else{
                $('tr.p_'+$(this).data('p')).hide();
                $(this).data('showed','0');
                $(this).removeClass('active');
            }
            return false;
        });

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
            }catch(e){}

        }

    })
</script>