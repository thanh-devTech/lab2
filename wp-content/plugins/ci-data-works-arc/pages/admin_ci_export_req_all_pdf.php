<?php
global $wpdb;
$dna_folder = WP_CONTENT_DIR . '/DNA/';
$user_id = get_current_user_id();
$dir = WP_CONTENT_DIR.'/cache/';

$batch_tbl = 'ci_batches_dna';


//check config site
$cs = get_option('ci_box_settings',array());
$use_current_blog = (!empty($cs['access_token']));
$box = new CI_Box_Works(true,$use_current_blog);

$where_account = '';
if(!empty($box->options['account_id'])){
    $where_account .= " and account_id = {$box->options['account_id']}";
}


$file_path = $dir.'export_pdf_dna.txt';
$upload_data = $data = array();

$config = get_option('ci_export_pdf_dna',array());
$pos = intval($config['pos']);

$total_up = $wpdb->get_var("select count(*) from ci_box_upload where type='dna' {$where_account}");
$pos_up = $wpdb->get_var("select count(*) as count from ci_box_upload where type='dna' and status = 1 {$where_account}");

if(file_exists($file_path))
{
    $results = @json_decode(file_get_contents($file_path),true);
}
else{
    $results = $wpdb->get_col("select mid from {$batch_tbl} where is_dna=1 order by doc asc");
    @file_put_contents($file_path,json_encode($results));
    $config['pos'] = 0;
    $config['break']=0;
    $pos = 0;
    update_option('ci_export_pdf_dna',$config);
}
$count_results = count($results);






if(isset($_REQUEST['pos_up'])){

    $uploads = $wpdb->get_results("select * from ci_box_upload where type='dna' and status !=1 {$where_account} limit 5");
    $response = array('status'=>'done','total'=>$total_up,'pos_up'=>$pos_up);

    if($total_up==0 || $total_up == $pos_up)
    {
        @unlink($file_path);
        wp_send_json($response);
    }




    $dna_folder_id = 0;
    try{
        $dna_folder_id = $box->create_folder('DNA');
        $response['dna_folder_id'] = $dna_folder_id;
    }catch (Exception $e)
    {
        $response = array('status'=>'error','message'=>'Error while create DNA folder');
        wp_send_json($response);
    }
    if(empty($dna_folder_id))
    {
        $response = array('status'=>'error','message'=>'Error while create DNA folder');
        wp_send_json($response);
    }
    $parent_ids = array();


    foreach($uploads as $upload)
    {
        $file = $upload->path;
        $sub_folder = dirname($file);
        if(isset($parent_ids[$sub_folder])) {
            $parent_id = $parent_ids[$sub_folder];
        }else{
            try{
                $parent_id = $box->create_folder($sub_folder,$dna_folder_id);
                $parent_ids[$sub_folder] = $parent_id;
            }catch (Exception $e)
            {
                //$response = array('status'=>'error','message'=>'Error while create '.$sub_folder.' folder');
                //wp_send_json($response);
                sleep(2);
            }
        }

        try{
            $rs = $box->upload_file($dna_folder.$file,'',$parent_id);

            //update status
            $wpdb->update('ci_box_upload',array('status'=>1,'uptime'=>time()),array('path'=>$upload->path));
        }catch (Exception $e)
        {
            //$response = array('status'=>'error','message'=>'Error while upload file: '.$file);
            //wp_send_json($response);
            sleep(2);
        }

        if($rs)
        {
            $response['status'] = 'ok';
        }
    }

    if($response['status']=='done'){
        //@unlink($file_path);
    }
    $sql = "select count(*) as count from ci_box_upload where type='dna' and status = 1 {$where_account}";
    $pos_up = $wpdb->get_var($sql);

    $response['pos_up']  = $pos_up;

    wp_send_json($response);
    exit;
}

else if(isset($_REQUEST['pos'])){
    $response = array('status'=>'done');
    $filetmp = tempnam("/tmp", "export" . time()).'.html';
    $max_run = 10;
    for($iab=1;$iab<=$max_run;$iab++)
    {
        $_GET['id'] = $_REQUEST['id'] = @$results[$pos];
        $pos++;
        if($pos >= $count_results)
        {
            $pos = $count_results;
        }

        if($_GET['id']>0) {

            $response['status'] = 'ok';

            ob_start();
            include(TRCPDD_PATH . 'pages/admin_ci_export_req_html.php');
            $content = ob_get_clean();
            $content = str_replace('></td>', '>&nbsp;</td>', $content);

            if (!file_exists($dna_folder . $doc_date)) {
                wp_mkdir_p($dna_folder . $doc_date);
            }

            $file_subpath = $doc_date .'/' .$outfile;
            unset($outfile);

            //check box upload db
            $sql = "select * from ci_box_upload where path='{$file_subpath}' and type='dna' {$where_account}";
            $ups = $wpdb->get_results($sql);
            $found_up = false;
            if($ups)
            {

                foreach($ups as $up)
                {
                    if($up->mid==$mid){
                        $file_subpath = $up->path;
                        $found_up = true;
                        break;
                    }
                }
                $count_up = count($ups);
                if($count_up > 0 && $found_up==false)
                {
                    $file_subpath = str_replace('.pdf','-'.$count_up.'.pdf',$file_subpath);
                }
            }

            $response['outfile'.$iab] = $file_subpath;

            if(!empty($batch_modified) && file_exists($dna_folder.$file_subpath))
            {
                $file_time = filectime($dna_folder.$file_subpath);
                $modified_time = strtotime($batch_modified);
                if($file_time > $modified_time)
                {
                    $config['break']++;
                    $response['break'] = $config['break'];
                    if($max_run<100)
                    {
                        $max_run++;
                    }
                    if(!$found_up)
                    {
                        $wpdb->insert('ci_box_upload',array('path'=>$file_subpath,'mid'=>$mid,'type'=>'dna','account_id'=>$box->options['account_id']));
                    }

                    continue;
                }else if($found_up){
                    //need update
                    $wpdb->update('ci_box_upload',array('status'=>2),array('mid'=>$mid,'path'=>$file_subpath));
                }else if(!$found_up){
                    $wpdb->insert('ci_box_upload',array('path'=>$file_subpath,'mid'=>$mid,'type'=>'dna','account_id'=>$box->options['account_id']));
                }
            }else if(file_exists($dna_folder.$file_subpath) && !$found_up)
            {
                $wpdb->insert('ci_box_upload',array('path'=>$file_subpath,'mid'=>$mid,'type'=>'dna','account_id'=>$box->options['account_id']));
            }

            if(!file_put_contents($filetmp, $content))
            {
                $response['status']='error';
                $response['message'] = "Error while export PDF file. " . $e->getMessage().'=>'.$mid.'=>'.$file_subpath;
                $response['errorid'] = $mid;
                $response['file_subpath'] = $file_subpath;
                break;
            }


            if(trpdd_html_to_pdf($filetmp,$dna_folder.$file_subpath))
            {
                $response['status']='ok';
                //add file need upload
                if(!$found_up)
                {
                    $wpdb->insert('ci_box_upload',array('path'=>$file_subpath,'mid'=>$mid,'type'=>'dna','account_id'=>$box->options['account_id']));
                }
            }
            else
            {
                $response['status']='error';
                $response['message'] = "Error while export PDF file. " . $e->getMessage().'=>'.$mid.'=>'.$file_subpath;
                $response['errorid'] = $mid;
                $response['file_subpath'] = $file_subpath;
                break;
            }
        }else{
            break;
        }

    }

    if(!isset($_REQUEST['debug']))
    {
        $response['pos']  = $pos;
        //update pos
        $config['pos'] = $pos;
        update_option('ci_export_pdf_dna',$config);
    }

    @unlink($filetmp);
    wp_send_json($response);
    exit;
}

?>
<h4>1. Export to PDF</h4>
Total: <?php echo count($results)?>
<br>
Current: <span class="current_pdf"><?php echo $pos?></span>
<div id="export_status">Waiting...</div>
<br>
<h4>2. Upload to Box</h4>
Total: <span id="upload_total"><?php echo $total_up?></span>
<br>
Current: <span class="current_upload_pdf"><?php echo $pos_up?></span>
<div id="upload_status">Waiting...</div>

<script>
    jQuery(function($){
        var $pos = <?php echo intval($pos)?>;
        var $pos_up = <?php echo intval($pos_up)?>;
        function tr_do_export_pdf()
        {
            $('#export_status').html('Exporting...');
            $.ajax({
                url:ajaxurl,
                data:{action:'ajax',tr_action:'ci_export_req_all_pdf',pos:$pos},
                type:'post',
                dataType:'json',
                success:function(rs)
                {
                    if(rs.status=='error'){
                        alert(rs.message);
                    }
                    else if(rs.status=='done'){
                        $('#export_status').html('Done');
                        tr_upload_to_box();
                    }else{
                        $pos = rs.pos;
                        $('.current_pdf').html($pos);
                        setTimeout(function(){
                            tr_do_export_pdf();
                        },600);
                    }

                }
            });
        }
        function tr_upload_to_box()
        {
            $('#upload_status').html('Uploading...');
            $.ajax({
                url:ajaxurl,
                data:{action:'ajax',tr_action:'ci_export_req_all_pdf',pos_up:$pos_up},
                type:'post',
                dataType:'json',
                success:function(rs)
                {
                    if(rs.status=='error'){
                        alert(rs.message);
                    }
                    else if(rs.status=='done'){
                        $('#upload_status').html('Done');
                        $('#upload_total').html(rs.total);
                    }else{
                        $pos_up = rs.pos_up;
                        $('#upload_total').html(rs.total);
                        $('.current_upload_pdf').html($pos_up);
                        setTimeout(function(){
                            tr_upload_to_box();
                        },600);
                    }

                }
            });
        }
        tr_do_export_pdf();
    })
</script>
