<?php
global $import_file;
global $import_act;

$file = $_FILES['batch']['tmp_name'];
$filename = $_FILES['batch']['name'];
if(empty($file) || (stripos($filename,'.csv')===false && stripos($filename,'.zip')===false))
{
    Tr_Session_Class::add_error('Please select CSV file to upload');
    return false;
}

//save file
$upload_dir = tr_wp_upload_dir();
$import_dir = $upload_dir['basedir'].'/batch_imports';
$import_file = Tr_Form_V7::uploadfile($_FILES['batch'],$filename,$import_dir,array('csv','zip'));
if(!$import_file)
{
    Tr_Session_Class::add_error('Error, upload file ');
}else{
    $import_act = 'batch';
    if(stripos($filename,'.zip')!==false)
    {
        $file = $import_dir.'/'.$import_file;
        WP_Filesystem();
        $unzipfile = unzip_file( $file, $import_dir);
        if(!$unzipfile)
        {
            Tr_Session_Class::add_error('Error, can\'t extract zip file');
            $import_act = '';
        }else{
            $import_act = 'zip_batch';
            $import_file = 'zip';
        }
        unlink($file);
    }
}