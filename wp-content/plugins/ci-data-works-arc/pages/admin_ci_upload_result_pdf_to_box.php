<?php
global $wpdb;


$result_dir     = WP_CONTENT_DIR.'/results/';
$type_result    = 'result';


$batch_id       = intval($_REQUEST['batch_id']);
$lab_id         = intval(@$_REQUEST['lab_id']);
$ref_lab_id     = @$_REQUEST['ref_lab_id'];
$type           = $_REQUEST['type']? $_REQUEST['type']:'tox';
$table          = tr_pdd_get_table_by_btype($type);
$report_tbl     = tr_pdd_get_table_report_by_btype($type);

if(!empty($type) && $type!='tox')
{
    $type_result.= $type;
}

$is_debug       = isset($_REQUEST['rsdb']);
$ids            = isset($_REQUEST['ids'])? $_REQUEST['ids']:'';
$mids           = isset($_REQUEST['mids'])? $_REQUEST['mids']:'';

if($is_debug)
{
    $type_result='testrs';
}

$where_lab = '';
$where_lab2= '';
if($lab_id>0)
{
    $lab = $wpdb->get_row("select lab_dba_init,lab_dba from ci_labs where lab_ID = {$lab_id} ");
    $where_lab = " and lab_ID = {$lab_id}  ";
    $where_lab2 = " and (re.sublab = '{$lab->lab_dba_init}' or re.sublab = '{$lab->lab_dba}' or lab.lab_ID = {$lab_id}) ";



    if($ref_lab_id>0)
    {
        $where_lab2.= " and ba.ref_lab_ID = {$ref_lab_id} ";
    }
    else
    {
        $where.= " and lab.lab_ID = {$lab_id} and re.report_ref = 0 ";
    }
}

if(!empty($mids))
{
    $mids = explode(',',$mids);
    $mids = array_filter($mids);
    $mids = implode(',',$mids);
    $where_lab2.= " and ba.mid in ({$mids})";
}
else if(!empty($ids))
{
    $ids = explode(',',$ids);
    $ids = array_filter($ids);
    $ids = implode(',',$ids);
    $where_lab2.= " and re.id in ({$ids})";
}


$box = new CI_Box_Works();



$pos_up = $wpdb->get_var("select count(*) as count from ci_box_upload where type='{$type_result}' and status = 1  {$where_lab}");

if(isset($_REQUEST['upload'])){

    $total_up = $wpdb->get_var("select count(*) from ci_box_upload where type='{$type_result}'  {$where_lab}");
    $sql_up = "select * from ci_box_upload where type='{$type_result}' and status !=1 and status!=4 {$where_lab} limit 1";

    $uploads = $wpdb->get_results($sql_up);


    $response = array('status'=>'done','total'=>$total_up,'pos_up'=>$pos_up);
    if($total_up==0 || $total_up <= $pos_up)
    {
        //update uploaded to batch
        if($total_up>0 && !$is_debug)
        {
            $lab_id = CI_Base_Lab::get_lab_id_by_blog_id();
            $bu_id = $wpdb->get_var("select bu_ID from ci_batches_uploaded 
                        where bu_batch_id={$batch_id} 
                        and bu_lab_id=0 
                        and bu_lab_site_id='{$lab_id}' 
                        ");
            if($bu_id==0)
            {
                $wpdb->insert('ci_batches_uploaded',array(
                    'bu_batch_id'=>$batch_id,
                    'bu_lab_id'=>0,
                    'bu_lab_site_id'=> CI_Base_Lab::get_current_lab_id()
                ));
            }

        }
        wp_send_json($response);
    }
	$time_edit_html = filectime(TRCPDD_PATH.'reports/results_tox.php');

    foreach($uploads as $upload)
    {
        $rs = false;
        $try_upload = 0;
        $error_type=0;
        $path = $upload->path;
        $file = $result_dir.$path;
        $box_path = 'results/'.dirname($path);
        $box_path = preg_replace("/results\/[0-9]+\//",'results/',$box_path);

        if($is_debug){
            $box_path = str_replace('results/','test/',$box_path);
        }
        else if($box->options['use_results_folder']==='0'){
            $box_path = str_replace('results/','',$box_path);
        }

        while($try_upload<=3) {
            $try_upload++;

            try {
                //create if file not exists
                $filetime = @filectime($file);
                if(stripos($file,'uploads/')!==false)
                {

                }
                else if (!file_exists($file) || $filetime < $time_edit_html)
                {
                    $id =  $upload->mid;
                    if($upload->sender=='mid'){
                        $id = 0;
                        $_REQUEST['mid'] = $upload->mid;
                    }

                    $file = trpdd_export_results_pdf($id,'',false,$upload->btype);
                    if(! $file){
                        $response['status'] = 'error';
                        $response['message'] = 'File not exists: ' . $path;
                        break;
                    }
                }



                if ($error_type == 3) {
                    $box->check_folder($box_path);

                }
                $folder_id = $box->path_folder($box_path);

                $response['folder_id'] = $folder_id;

                if(!$folder_id)
                {
                    $error_type = 3;
                    continue;
                }

                if (!file_exists($file))
                {
                    $response['fienot'] = $file;
                }

                //try upload
                if ($upload->status == 2 && !empty($upload->file_id)) {
                    $rs = $box->update_file($file, $upload->file_id, $upload->etag);
                    if ($rs->response['code'] == 'not_found') {
                        $rs = $box->upload_file($file, '', $folder_id);
                    }
                } else {
                    $rs = $box->upload_file($file, '', $folder_id);
                    if ($rs->response['code'] == 'item_name_in_use') {
                        $file_id = $rs->response['context_info']['conflicts']['id'];
                        $file_etag = $rs->response['context_info']['conflicts']['etag'];
                        $rs = $box->update_file($file, $file_id, $file_etag);
                    }
                }

                if($rs && $is_debug)
                {
                    $response['rs'][] = $rs;
                    $box->update_file_rs($upload->path, $rs,$type_result);
                    break;
                }
                else if ($rs) {
                    $box->update_file_rs($upload->path, $rs);

                    if($upload->sender=='mid'){
                        $wpdb->update($table,array('box_uploaded'=>1),array('mid'=>$upload->mid));
                    }else{
                        $wpdb->update($report_tbl,array('box_uploaded'=>1),array('id'=>$upload->mid));
                    }


                    //copy file to new Archiver folder
                    try {
                        $file_id = $rs->response['entries'][0]['id'];
                        $archive_path = str_replace('results/', 'archive/', $box_path);
                        if(stripos($archive_path,'archive/')===false)
                        {
                            $archive_path = 'archive/' . $box_path;
                        }

                        if($archive_path!=$box_path)
                        {
                            $f_id = $box->path_folder($archive_path);

                            $rsc = $box->copy_file($file_id, $f_id);
                            if ($rsc->response['code'] == 'item_name_in_use') {
                                //need delete that file
                                $fc_id = $rsc->response['context_info']['conflicts']['id'];
                                $fc_etag = $rsc->response['context_info']['conflicts']['etag'];
                                $rsd = $box->delete_file($fc_id, $fc_etag);
                                if ($rsd) {
                                    $rsc = $box->copy_file($file_id, $f_id);
                                }
                            }
                        }

                    } catch (Exception $e) {

                    }
                    break;

                } else {
                    $error_type = 2;//upload fail
                }
            } catch (Exception $e) {
                if($e->getCode()=='upload_file_404'){
                    $error_type = 3;
                }
                $response['status'] = 'error';
                $response['message'] = 'Error: ' . $e->getMessage();
                $response['file'] = $file;

            }
        }

        if($rs)
        {
            $response['status'] = 'ok';
        }else if($response['status']=='done'){
            $response['status'] = 'ok';
        }

    }
    $pos_up = $wpdb->get_var("select count(*) as count from ci_box_upload 
                    where type='{$type_result}' 
                    and status = 1 
                    and batch_id = '{$batch_id}' 
                    {$where_lab}");
    $response['pos_up']  = $pos_up;

    wp_send_json($response);
    exit;

}
if(!isset($_SESSION['last_time_id'])){
    $_SESSION['last_time_id']= array('k'=>'','keys'=>array());
}


if(!$box->ready())
{

    echo 'Please <a href="admin.php?page=ci_pdf_data&pagetab=ci_wsdl_keys&tab=box" target=_blank>ReAuthorize</a> to Upload';
    exit;
}


$sql = "select
    ba.mid,
    ba.result_pdf,
     re.id,
     re.modified,
     re.upload_files,
     bu.id as box_id,
     bu.status as box_status,
     bu.uptime,
     bu.path
    from {$table} ba
    left JOIN {$report_tbl} re ON ba.assen = re.patient_id
    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
    Left JOIn ci_box_upload bu ON bu.mid = re.id and type='{$type_result}'
    where (ba.batch_id = '{$batch_id}') {$where_lab2} 
    and (re.estatus='C' or (ba.result_pdf!='' and ba.result_pdf is not null ))
    group by ba.mid 
    order by ba.doc asc";


$query_key = md5($sql);
$is_new = true;
if( $_SESSION['last_time_id']['k'] == $_REQUEST['kt']){
    if(in_array($query_key,$_SESSION['last_time_id']['keys'])){
        $is_new = false;
    }else{
        $_SESSION['last_time_id']['keys'][] = $query_key;
    }
}else{
    $_SESSION['last_time_id']['k'] = $_REQUEST['k'];
    $_SESSION['last_time_id']['keys'] = array($query_key);
}


//echo $sql;

if($is_new)
{
    //try remove from cache
    $wpdb->query("delete from ci_box_upload where type='{$type_result}' 
                and batch_id = '{$batch_id}' 
                {$where_lab}");

}




$results = $wpdb->get_results($sql, ARRAY_A);
$added_file = 0;
foreach($results as $re)
{
    if($report['box_id']==0)
    {
        $mid = $re['id']==0? $re['mid'] : 0;
        $is_mid = $re['id']==0 ? 'mid' : '';

        if(!empty($re['result_pdf']) && empty($re['upload_files']))
        {
            $file = $re['result_pdf'];
            $path = 'uploads';
            $path_db = $path.'/'.$file;
        }else{
            $report = tr_pdd_get_report_by_id($re['id'],$type,$mid);
            list($path,$file) = trpdd_get_path_result_pdf($report,$type);
            $path_db = $path.'/'.$file;

            if(!file_exists($result_dir.$path))
            {
                wp_mkdir_p($result_dir.$path);
            }

        }


        $file_path = $result_dir.$path_db;
        $box->add_file($path_db,$re['id'],$type_result,$batch_id,$lab_id,$is_mid,$type);
        $added_file++;
        if(stripos($file,'other')===0)
        {
            $file_other = $file.$re['id'];
        }

    }else{
        $file = $result_dir.$re['path'];
        if($re['modified']>=filectime($file)){
            //trpdd_export_results_pdf($re['id']);
        }

        if((filectime($file)>= $re['uptime'] || $re['box_status']==4) && $re['box_status']>0){
            $wpdb->update('ci_box_upload',array('status'=>'2'),array('id'=>$re['box_id']));
        }

    }
}
?>

<html>
<?php
do_action( 'admin_print_scripts' );
?>
<script type="text/javascript">
    addLoadEvent = function(func){if(typeof jQuery!="undefined")jQuery(document).ready(func);else if(typeof wpOnload!='function'){wpOnload=func;}else{var oldonload=wpOnload;wpOnload=function(){oldonload();func();}}};
    var ajaxurl = '<?php echo admin_url( 'admin-ajax.php', 'relative' ); ?>',
        pagenow = '<?php echo $current_screen->id; ?>',
        typenow = '<?php echo $current_screen->post_type; ?>',
        adminpage = '<?php echo $admin_body_class; ?>',
        thousandsSeparator = '<?php echo addslashes( $wp_locale->number_format['thousands_sep'] ); ?>',
        decimalPoint = '<?php echo addslashes( $wp_locale->number_format['decimal_point'] ); ?>',
        isRtl = <?php echo (int) is_rtl(); ?>;
</script>
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<body>
<div title="<?php echo $added_file?>">Total: <?php echo count($results)?></div>
<br>
Current: <span class="current_upload_pdf">0</span>
<div id="upload_status" title="<?php echo $file_other?>">Waiting...</div>


<script>
    jQuery(function($){
        function tr_upload_to_box()
        {
            $('#upload_status').html('Uploading...');
            $.ajax({
                url:ajaxurl+'?<?php if($is_debug)echo 'rsdb=1' ?>',
                data:{action:'ajax',tr_action:'<?php echo $_REQUEST['tr_action']?>',upload:true,batch_id:<?php echo $batch_id?>,lab_id:<?php echo $lab_id?>,type:'<?php echo $type?>'},
                type:'post',
                dataType:'json',
                success:function(rs)
                {
                    if(rs.status=='error'){
                        alert(rs.message);
                    }
                    else if(rs.status=='done'){
                        $('#upload_status').html('Done');
                    }else{
                        $('.current_upload_pdf').html(rs.pos_up);
                        setTimeout(function(){
                            tr_upload_to_box();
                        },600);
                    }
                },
                error:function()
                {
                    $('#upload_status').html('Please wait... Retrying upload.');
                    setTimeout(function(){
                        tr_upload_to_box();
                    },600);
                }
            });
        }
        tr_upload_to_box();
    })
</script>
</body>
</html>