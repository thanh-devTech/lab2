<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb,$report;
$is_debug       = false;
$limit          = 10;
$time_edit_html = filectime(TRCPDD_PATH.'reports/results_tox.php');

$where = " re.estatus='C' 
    and ba.ref_from_lab_ID = 0  
    and (re.re_import_ID=0 or re.re_import_ID is null)";

$where.= " and ( ba.custom_profile!='Use Custom Profile' or (ba.custom_profile='Use Custom Profile' and cp.cus_ID>0) )";
if(!$is_debug)
{
    $where.= " and re.box_uploaded = 0 ";
}


$sql = "select re.id from ci_reports re
        JOIN ci_batches ba ON ba.assen = re.patient_id
        left Join ci_custom_providers cp ON cp.doc_ID = ba.doc_ID
        where {$where} order by re.id asc limit {$limit}";

$reports = $wpdb->get_col($sql);

if(isset($_GET['debug'])){
    echo $sql;
}
if(count($reports)==0)
{
    return;
}

$result_dir = WP_CONTENT_DIR.'/results/';

$box = new CI_Box_Works();

if(!$box->ready())
{
    return;
}

$log = array();
$log['reports'] = $reports;
$log['start'] = date('Y-m-d H:i:s');
$time_start = microtime(true);

if(count($reports)>0)
{
    wp_clear_scheduled_hook('ci_auto_upload_results',array(0));
    wp_schedule_single_event(time()+60*2, 'ci_auto_upload_results',array(0));
    $log['next'] =1;
}

foreach ($reports as $re_id)
{
    unset($report);
    $report = tr_pdd_get_report_by_id($re_id);
    list($path,$file) = trpdd_get_path_result_pdf($report);

    if(!file_exists($result_dir.$path))
    {
        wp_mkdir_p($result_dir.$path);
    }

    $path_db = $path.'/'.$file;
    $file_path = $result_dir.$path_db;

    if (!file_exists($file_path))
    {
        trpdd_export_results_pdf($re_id);
    }

    $filetime = filectime($file_path);
    if($report['modified']>=$filetime || strtotime($report['batch_modified'])>=$filetime || $time_edit_html> $filetime){
        trpdd_export_results_pdf($re_id);
    }

    if (!file_exists($file_path))
    {
        $log['nofile'][] = $file_path;
    }else{
        $box_path = 'results/'.$path;
        $box_path = preg_replace("/results\/[0-9]+\//",'results/',$box_path);

        if($is_debug){
            $box_path = str_replace('results/','test/',$box_path);
        }
        else if($box->options['use_results_folder']==='0'){
            $box_path = str_replace('results/','',$box_path);
        }



        try{
            $error_type = 0;

            $folder_id = $box->path_folder($box_path);

            if(!$folder_id)
            {
                $box->check_folder($box_path);
                $folder_id = $box->path_folder($box_path);
                if(!$folder_id)
                {
                    $error_type = 3;
                    echo 'error folder:'.$box_path;
                    continue;
                }
            }


            $rs = $box->upload_file($file_path, '', $folder_id);
            if ($rs->response['code'] == 'item_name_in_use') {

                $file_id = $rs->response['context_info']['conflicts']['id'];
                $file_etag = $rs->response['context_info']['conflicts']['etag'];
                $rsd = $box->delete_file($file_id, $file_etag);
                $rs = $box->upload_file($file_path, '', $folder_id);
                //$rs = $box->update_file($file_path, $file_id, $file_etag);
            }


            if($is_debug)
            {
                var_dump($rs);exit;
            }
            else if ($rs) {
                echo 'uploaded: '.$box_path.'/'.basename($file_path).'<br>';
                $info = array('autobox'=>1,'file'=>$box_path.'/'.basename($file_path),'date'=>Tr_Base_Class_V4::date('Y-m-d H:i:s'));
                $info = json_encode($info);
                $wpdb->update('ci_reports',array('box_uploaded'=>1,'info'=>$info),array('id'=>$re_id));

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

            }else{
                $log['fail'][] = $box_path.'/'.basename($file_path);
            }

        }catch (Exception $e)
        {
            var_dump($e);
            $log['fail'][] = $box_path.'/'.basename($file_path);
        }
        sleep(1);
    }


}

$log['runtime'] = microtime(true) - $time_start;

trpdd_log_form('autouploadbox',$log);



