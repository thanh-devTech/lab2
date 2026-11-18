<?php
global $wpdb;
$type = $_REQUEST['type'];
$subfolder = $_REQUEST['folder'];
$id = $_REQUEST['id'];
$dl_type = $type.'-'.$subfolder;

$table = 'ci_tox_hos';
$dir_root = '/CentralTox/';
$dirs['Req'] = $dir_root.'Requests/';
$dirs['Notes'] = $dir_root.'Notes/';
$dirs['Results'] = $dir_root.'Results/';

if($type=='blood'){
    $table = 'ci_blood_hos';
    $dir_root = '/LabPro/';
}
else if($type=='dna'){
    $table = 'ci_dna_hos';
    $dir_root = '/LabPro/';
}
else if($type=='allergy'){
    $table = 'ci_allergy_hos';
    $dir_root = '/LabPro/';
}
$limit = " limit 30 ";

if($id>0)
{
    $where = " where dl_ID = {$id} ";
    $limit = '';
}else{
    $where = " where (dl_ID = 0 or dl_ID is null) and subfolder='{$subfolder}'";
}


$sql = "select *
        from {$table} {$where}
        order by doc asc {$limit}";

if(isset($_REQUEST['debug'])){
    echo $sql;exit;
}
$results = $wpdb->get_results($sql);

$count = count($results);
if($count==0){
    Tr_Session_Class::add_error('No '.$type.' found for download');
    $redirect = $_SERVER['HTTP_REFERER'];
    wp_safe_redirect($redirect);
    exit;
}

/*
$zip = new ZipArchive();
$tmp_file = tempnam('/tmp','export');

// open archive
if ($zip->open($tmp_file, ZIPARCHIVE::CREATE) !== TRUE) {
    die ("Could not open archive");
}
*/
require_once(TRCPDD_PATH.'inc/fpdf/fpdf.php');
require_once(TRCPDD_PATH . 'inc/FPDI-2.6.0/src/autoload.php');
$pdf = new \setasign\Fpdi\Fpdi();

$IDs = array();
foreach($results as $row)
{
    $IDs[] = $row->th_ID;

    $file = ABSPATH . $dirs[$row->subfolder].$row->file_name;

    //$zip->addFile($file, $file_names[$ii]) or die ("ERROR: Could not add file: $file_names[$ii]");

    $pageCount = $pdf->setSourceFile($file);
    // iterate through all pages
    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
        // import a page
        $templateId = $pdf->importPage($pageNo,setasign\Fpdi\PdfReader\PageBoundaries::CROP_BOX,true,true);
        // get the size of the imported page
        $s = $pdf->getTemplateSize($templateId);
        
        $pdf->AddPage($s['orientation'], $s);

        // use the imported page
        $pdf->useTemplate($templateId);

    }

}
// close and save archive
//$zip->close();

if($id==0)
{

    $id_from = $wpdb->get_var("select dl_from + dl_count from ci_hos_download where dl_type='{$dl_type}' order by dl_ID desc limit 1") ;
    if($id_from==0)
    {
        $id_from = 1;
    }
//insert new group
    $wpdb->insert('ci_hos_download',
        array(
            'dl_created'=>Tr_Base_Class_V4::date('Y-m-d H:i:s'),
            'dl_type' => $dl_type,
            'dl_count' => $count,
            'dl_from' => $id_from,
            'dl_user_ids' => get_current_user_id(),
        ));

    $dl_ID = $wpdb->insert_id;
    $IDs = implode(',',$IDs);
    $wpdb->query("update {$table} set dl_ID = {$dl_ID} where th_ID in ($IDs) ");
}else{
    $download = $wpdb->get_row("select * from ci_hos_download where dl_ID = {$id} ") ;
    $id_from = $download->dl_from;
    $user_ids = explode(',',$download->dl_user_ids);
    $user_ids[] = get_current_user_id();
    $user_ids= array_unique($user_ids);
    $user_ids = implode(',',$user_ids);
    $wpdb->update('ci_hos_download',array('dl_user_ids'=>$user_ids),array('dl_ID'=>$id));

}


$filename = $subfolder.' '.$id_from.'-'.($id_from+$count-1).'.pdf';
/**
$zip_name = 'result_'.$id_from.'-'.($id_from+$count-1).'.zip';
///Then download the zipped file.
header('Content-Type: application/zip');
header('Content-disposition: attachment; filename='.$zip_name);
header('Content-Length: ' . filesize($tmp_file));
readfile($tmp_file);
 */
$pdf->Output($filename,'D');

exit;