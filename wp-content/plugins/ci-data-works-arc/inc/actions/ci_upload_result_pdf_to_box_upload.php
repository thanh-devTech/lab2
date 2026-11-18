<?php
global $wpdb;

$result_dir     = tr_get_result_upload_dir();

$batch_id       = intval($_REQUEST['batch_id']);
$lab_id         = intval(@$_REQUEST['lab_id']);
$type           = trpdd_get_current_btype();


$type_result    = 'rs_upload'.$type;
$is_debug       = isset($_REQUEST['rsdb']);
$ids            = isset($_REQUEST['ids'])? $_REQUEST['ids']:'';

$root_folder = "";
if($is_debug)
{
    $type_result='testrsup'.$type;
}
$table = 'ci_batches';
$where = " and batch_id = '{$batch_id}' ";
$where2= " and ba.batch_id = '{$batch_id}' and ba.result_pdf != '' ";
if($type=='dna'){
    $where2.= " and ba.is_dna=1 ";
    $table = 'ci_batches_dna';
    $root_folder = "dna_results";
}else if($type=='blood'){
    $where2.= " and ba.is_dna=2 ";
    $table = 'ci_batches_blood';
    $root_folder = "blood_results";
}
else if($type=='allergy'){
    $where2.= " and ba.is_bna = 3 ";
    $table = 'ci_batches_allergy';
    $root_folder = "allergy_results";
}
else{
    $where2.= " and ba.is_bna = 0 ";
}
if($lab_id>0)
{
    $lab = $wpdb->get_row("select lab_dba_init,lab_dba from ci_labs where lab_ID = {$lab_id} ");
    $where = " and lab_ID = {$lab_id} ";
    $where2 .= " and ba.lab_id = {$lab_id} ";

    if(!empty($ids))
    {
        $ids = explode(',',$ids);
        $ids = array_filter($ids);
        $ids = implode(',',$ids);
        $where2.= " and ba.mid in ({$ids})";
    }
}


$box = new CI_Box_Works();


$pos_up = $wpdb->get_var("select count(*) as count from ci_box_upload where type='{$type_result}' and status = 1  {$where}");

if(isset($_REQUEST['upload'])){
    $total_up = $wpdb->get_var("select count(*) from ci_box_upload where type='{$type_result}' {$where}");
    $uploads = $wpdb->get_results("select * from ci_box_upload where type='{$type_result}' and status !=1 and status!=4  {$where} limit 1");
    $response = array('status'=>'done','total'=>$total_up,'pos_up'=>$pos_up);
    if($total_up==0 || $total_up <= $pos_up)
    {
        wp_send_json($response);
    }

    foreach($uploads as $upload)
    {
        $rs = false;
        $try_upload = 0;
        $error_type=0;
        $path = $upload->path;
        $file = $result_dir['path'].$upload->sender;
        $filename = basename($path);
        $box_path = 'results/'.dirname($path);
        $box_path = str_replace('/results/other/','/results/',$box_path);
        $box_path = preg_replace("/results\/[0-9]+\//",'results/',$box_path);

        if($is_debug){
            $box_path = str_replace('results/','test/',$box_path);
        }
        else{
            $box_path = str_replace('results/',$root_folder.'/',$box_path);
        }

        while($try_upload<=3) {
            $try_upload++;

            try {
                //create if file not exists
                $filetime = filectime($file);
                if (!file_exists($file)) {
                    $response['status'] = 'error';
                    $response['message'] = 'File not exists: ' . $path;
                    break;
                }

                if ($error_type == 3) {
                    $box->check_folder($box_path);

                }
                $folder_id = $box->path_folder($box_path);

                if(!$folder_id)
                {
                    $error_type = 3;
                    continue;
                }


                //try upload
                if ($upload->status == 2 && !empty($upload->file_id)) {
                    $rs = $box->update_file($file, $upload->file_id, $upload->etag);
                    if ($rs->response['code'] == 'not_found') {
                        $rs = $box->upload_file($file,$filename, $folder_id);
                    }
                } else {
                    $rs = $box->upload_file($file, $filename, $folder_id);
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
                    $box->update_file_rs($upload->path, $rs,$type_result);
                    $wpdb->update($table,array('box_uploaded'=>1),array('mid'=>$upload->mid));

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
    $pos_up = $wpdb->get_var("select count(*) as count from ci_box_upload where type='{$type_result}' and status = 1  {$where}");
    $response['pos_up']  = $pos_up;

    wp_send_json($response);
    exit;

}


if(!$box->ready())
{

    echo 'Please <a href="admin.php?page=ci_pdf_data&pagetab=ci_wsdl_keys&tab=box" target=_blank>ReAuthorize</a> to Upload';
    exit;
}



if(!isset($_SESSION['last_time_id'])){
    $_SESSION['last_time_id']= array('k'=>'','keys'=>array());
}


$sql = "select
     ba.*,
     lab.lab_dba_init AS sublab,
     bu.id as box_id,
     bu.status as box_status,
     bu.uptime,
     bu.path
    from {$table} ba 
    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
    Left JOIn ci_box_upload bu ON bu.mid = ba.mid and type='{$type_result}'
    where 1 {$where2} group by ba.mid order by ba.doc asc limit 100";

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




if($is_new)
{
    //try remove from cache
    $wpdb->query("delete from ci_box_upload where type='{$type_result}'  {$where}");

}

$results = $wpdb->get_results($sql, ARRAY_A);
foreach($results as $re)
{
    if($report['box_id']==0)
    {
        //$re['id'] = $re['mid'];
        $re['batch_id'] = date('Ymd',strtotime($re['doc']));
        list($path,$file) = trpdd_get_path_result_pdf($re,$type);
        $path_db = $path.'/'.$file;
        //var_dump($path_db);exit;
        $box->add_file($path_db,$re['mid'],$type_result,$re['batch_id'],$re['lab_id'],$re['result_pdf']);

    }else{
        if($re['box_status']>0){
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
Total: <?php echo count($results)?>
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
                data:{action:'ajax',tr_action:'<?php echo $_REQUEST['tr_action']?>',type:'<?php echo $type?>',upload:true,batch_id:<?php echo $batch_id?>,lab_id:<?php echo $lab_id?>},
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