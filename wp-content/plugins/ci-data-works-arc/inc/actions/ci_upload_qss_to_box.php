<?php
global $wpdb;

$dir = WP_CONTENT_DIR.'/results/';

$type_result = 'rs_upload_qss';
$is_debug = isset($_REQUEST['rsdb']);
$ids = isset($_REQUEST['ids'])? $_REQUEST['ids']:'';
$date = $_REQUEST['date'];
$batch_id = str_replace('-','',$date);
$type= isset($_REQUEST['type'])? $_REQUEST['type']:'';

if($is_debug)
{
    $type_result='testrqss';
}

$where = " and batch_id = '{$batch_id}' ";
$where2 = " and ba.created >= '{$date}' and ba.created <= '{$date} 23:59:59'";
if($type == 'blood')
{
    $where2.= " and is_dna = 2 ";
}
else if($type == 'dna')
{
    $where2.= " and is_dna = 1 ";
}
else if($type == 'allergy')
{
    $where2.= " and is_dna = 3 ";
}
else if($type == 'tox')
{
    $where2.= " and is_dna = 4 ";
}
else{
    $where2.= " and is_dna = 0 ";
}

if(isset($_REQUEST['upload2'])){
    $where2.= " and box_uploaded = 0 ";
}

if(!empty($ids))
{
    $ids = explode(',',$ids);
    $ids = array_filter($ids);
    $ids = implode(',',$ids);
    $where2.= " and ba.ig_ID in ({$ids})";
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
        $file = $dir.$path;

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
                    $box->update_file_rs($upload->path, $rs,$type_result);
                    $wpdb->update('ci_igniting_qss',array('box_uploaded'=>1),array('ig_ID'=>$upload->mid));

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
     bu.id as box_id,
     bu.status as box_status,
     bu.uptime,
     bu.path
    from ci_igniting_qss ba 
    Left JOIn ci_box_upload bu ON bu.mid = ba.ig_ID and type='{$type_result}'
    where 1 {$where2} group by ba.ig_ID order by ba.doc asc ";

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
$total = count($results);
foreach($results as $re)
{
    if($report['box_id']==0)
    {
        $re['id'] = $re['ig_ID'];
        $re['batch_id'] = date('Ymd',strtotime($re['created']));
        $path = $re['file_path'];
        $file = $re['file_name'];
        $path_db = $path.'/'.$file;
        $box->add_file($path_db,$re['ig_ID'],$type_result,$re['batch_id'],10,$re['file_name']);

        if(!empty($re['request_pdf'])){
            $file = $re['request_pdf'];
            $path_db = $path.'/'.$file;
            $box->add_file($path_db,$re['ig_ID'],$type_result,$re['batch_id'],10,$re['request_pdf']);
            $total+=1;
        }

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
Total: <?php echo $total?>
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
                data:{action:'ajax',tr_action:'<?php echo $_REQUEST['tr_action']?>',type:'<?php echo $type?>',upload:true,date:'<?php echo $date?>',ids:'<?php echo $_REQUEST['ids']?>'},
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