<?php
global $wpdb,$current_role,$blog_id;
$user_id = get_current_user_id();

wp_enqueue_script('data-admin',TRCPDD_URL. 'js/admin.min.js',array(),TRCPDD_VS );
$dir_root = get_bloginfo('home') .'/LabPro/';
$dirs['Req'] = $dir_root.'Requests/';
$dirs['Notes'] = $dir_root.'Notes/';
$dirs['Results'] = $dir_root.'Results/';


$where = 'WHERE 1 ';

if(!empty($_REQUEST['s'])){
    $_REQUEST['s'] = trim($_REQUEST['s']);
    $where.= $wpdb->prepare(" 
            and (ba.file_name like %s 
            or ba.doctor like %s
            or ba.clinic like %s)",
        '%'.$_REQUEST['s'].'%',
        '%'.$_REQUEST['s'].'%',
        '%'.$_REQUEST['s'].'%');
}



$id = $_REQUEST['id'];
$act = $_REQUEST['act'];
if($act =='delete' && $id >0){
    $wpdb->delete('ci_allergy_hos',array('th_ID'=>$id));
}


$sql_total = "select count(distinct(date_format(ba.doc,'%Y%m%d'))) from ci_allergy_hos ba 
              {$where}";
$row_per_page = 30;
$pagination = new Tr_Pagination_class2($row_per_page);
$offset = $pagination->offset();
$pagination->setTotal($wpdb->get_var($sql_total));


$sql_limit = "select distinct(date_format(ba.doc,'%Y-%m-%d')) from ci_allergy_hos ba
        {$where}
        ORDER BY ba.doc desc  limit {$offset},{$row_per_page}";
$batch_ids = $wpdb->get_col($sql_limit);

$date_from = end($batch_ids);
$date_to = $batch_ids[0].' 23:59:59';
$where.= " and ba.doc >='{$date_from}' and ba.doc <= '{$date_to}' ";


$results = $wpdb->get_results("select 
                ba.*,
                group_concat(subfolder SEPARATOR '|') as subfolders,
                group_concat(file_name SEPARATOR '|') as file_names,
                group_concat(doctor SEPARATOR '|') as doctors,
                group_concat(clinic SEPARATOR '|') as clinics
                from ci_allergy_hos ba 
               {$where} group by assen ORDER BY ba.doc desc");
?>
<h2>Tox Hos Results</h2>
<?php Tr_Session_Class::show()?>
<form method="get" style="float:left">
    <input type="hidden" name="page" value="<?php echo $_GET['page']?>"/>
    <input type="hidden" name="tab" value="<?php echo $_GET['tab']?>"/>
    <label>Patient/Clinic/Doctor:</label>
    <input type="text" name="s" value="<?php echo $_REQUEST['s']?>"/>
    <input type="submit" class="button bsty6" value="Search"/>


</form>
<div style="float:right">
    <form method="post">
        <select name="folder" id="folder_hos" class="njs_select2">
            <option value="Req">Requests</option>
            <option value="Results">Results</option>
            <option value="Notes">Notes</option>
        </select>
        <input type="submit" class="button-primary" value="Download"/>
        <a class="button downloaded_hos" data-type="allergy">Downloaded</a>
        <input type="hidden" name="tr_action" value="ci_download_hos_result"/>
        <input type="hidden" name="type" value="tox"/>
    </form>

</div>
<div class="tablenav"><?php if(isset($pagination))echo $pagination->pagination(array('m')) ?></div>

<div class="wraptbl">
<table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl" data-count="<?php echo @$count_rows?>">
    <thead>
    <tr>
        <th style="width:3%">#</th>
        <th  style="width: 20%;">Clinic</th>
        <th  style="width: 15%;">Doctor</th>
        <th style="width:10%">Accession#</th>
        <th  style="width: 15%;">Patient</th>
        <th  style="width: 80px;">Date</th>
        <th style="width:60px">Req</th>
        <th style="width:60px">Notes</th>
        <th style="width:60px">Result</th>
        <th style="width:50px"></th>
    </tr>
    </thead>

    <tbody>
    <?php
    $batches = array();
    if(count($results)>0)
    {
        foreach($results as $i => $row) {
            $created_time = strtotime($row->doc);
            $row->batch_id = date('Ymd',$created_time);
            if(!is_array($batches[$row->batch_id]))
            {
                $batches[$row->batch_id] = array();
            }
            //$batches[$row->batch_id]['date'] = $row->collection_date;
            $date = date('Y-m-d',$created_time);

            if($created_time<0)$date = '0000-00-00';
            $batches[$row->batch_id]['date'] = $date;
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
                <td></td>
                <td><?php echo $d['date']?></td>
                <td></td>
                <td></td>
                <td align="right">
                </td>
                <td style="width:50px;"></td>
            </tr>
            <tr class="child p_batch_<?php echo $batch_id?>">
                <td colspan="10" class="nopadding">
                    <table class="report_tbl">
                        <?php foreach($d['rows'] as $row)
                        {
                            $pdfs = array();
                            $subfolders = explode('|',$row->subfolders);
                            $file_names = explode('|',$row->file_names);
                            $doctors = explode('|',$row->doctors);
                            $clinics = explode('|',$row->clinics);
                            // var_dump($subfolders);
                            foreach($subfolders as $ii=> $sub)
                            {
                                if(!empty($doctors[$ii])){
                                    $row->doctor = $doctors[$ii];
                                }
                                if(!empty($clinics[$ii])){
                                    $row->clinic = $clinics[$ii];
                                }
                                if(empty($sub))continue;
                                $pdfs[$sub] = $dirs[$sub].$file_names[$ii];
                            }
                            ?>
                            <tr class="childp">
                                <td style="width: 3%"></td>
                                <td style="width:20%"><?php echo $row->clinic?></td>
                                <td style="width:15%"><?php echo $row->doctor ?></td>
                                <td style="width:10%"><?php echo $row->assen?></td>
                                <td style="width:15%"><?php echo $row->patient_name?></td>
                                <td style="width:80px"><?php if(strtotime($row->doc)>0)echo date('Y-m-d',strtotime($row->doc))?></td>
                                <td style="width:60px">
                                    <?php if($pdfs['Req']):?>
                                        <a href="<?php echo $pdfs['Req']?>" target="_blank">PDF</a>
                                    <?php endif?>
                                </td>
                                <td style="width:60px">
                                    <?php if($pdfs['Notes']):?>
                                        <a href="<?php echo $pdfs['Notes']?>" target="_blank">PDF</a>
                                    <?php endif?>
                                </td>
                                <td style="width:60px">
                                    <?php if($pdfs['Results']):?>
                                        <a href="<?php echo $pdfs['Results']?>" target="_blank">PDF</a>
                                    <?php endif?>
                                </td>
                                <td style="width:50px;">
                                    <?php if($current_role=='administrator'):?>
                                        <a class="delete" href="#" data-id="<?php echo $row->th_ID?>">Delete</a>
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
            <td colspan="10">No Results Found.</td>
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

            var $url = '?tr_action=ci_upload_qss_to_box&date='+$date+'&type=tox&kt=<?php echo time()?>&ids='+$ids;
            <?php if(isset($_GET['debug'])):?>
            $url+='&rsdb=1';
            <?php endif;?>
            tb_show('Upload to Box',$url+'&TB_iframe=true&width=400&height=300');
            return false;
        });
        $('a.downloaded_hos').click(function () {
            var $folder = $('#folder_hos').val();
            var $type = $(this).data('type');
            var $url = '?tr_action=ci_downloaded_hos_result&type='+$type+'&folder='+$folder;
            tb_show('Downloaded '+$folder,$url+'&width=600&height=400');
            return false;
        })
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