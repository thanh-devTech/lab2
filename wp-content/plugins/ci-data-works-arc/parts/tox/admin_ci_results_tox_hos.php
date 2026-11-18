<?php
global $wpdb,$current_role;


include_once(TRCPDD_PATH.'pages/formdata.php');

//check files
$dir_root = ABSPATH .'/CentralTox/';
$dirs['Req'] = $dir_root.'Requests/';
$dirs['Notes'] = $dir_root.'Notes/';
$dirs['Results'] = $dir_root.'Results/';




$tabs = array(
    '' => 'Tox Hos',
    'list' => 'Tox Hos Results'
);
$tab = isset($_REQUEST['tab'])? $_REQUEST['tab']: key($tabs);

?>
<h2 class="nav-tab-wrapper">
    <?php

    foreach($tabs as $k => $txt){
        $class = $tab == $k ? 'nav-tab-active':'';
        $link = Tr_Base_Class_V6::link(array('tab'=>$k,'act'=>'','id'=>'','mid'=>'','paged'=>'','s'=>''),false);
        echo '<a class="nav-tab '.$class.'" href="'.$link.'">'.$txt.'</a>';
    }
    ?>
</h2>

<?php

if($tab=='list'){
    include( 'admin_ci_results_tox_hos_list.php' );
    return;
}

if($_POST['insert_files'])
{

    foreach($dirs as $sub => $path)
    {
        $files = $_FILES[$sub];
        foreach($files['name'] as $i => $outfile)
        {
            if(empty($outfile))continue;

            $filepath = $path.$outfile;
            $tmp = $files['tmp_name'][$i];
            if(move_uploaded_file($tmp,$filepath)){

            }else{
                Tr_Session_Class::add_error('Can\'t upload file: '.$outfile);
                continue;
            }

            $insert = array(
                'created' => Tr_Base_Class_V4::date('Y-m-d H:i:s'),
                'user_id' => get_current_user_id()
            );

            if($sub=='Req'){
                list($doc,$clinic,$assen,$patient) = explode('-',str_ireplace('.pdf','',$outfile),4);
                if($doc>0)
                {
                    preg_match("/([0-9]{4})([0-9]{2})([0-9]{2})/",$doc,$doc_str);
                    if($doc_str)
                    {
                        $cdatefile = $doc_str[1].'-'.$doc_str[2].'-'.$doc_str[3];
                        $insert['doc'] = $cdatefile;
                    }
                }
            }else if($sub=='Notes'){
                list($assen,$patient) = explode('-',str_ireplace('.pdf','',$outfile),2);
            }else{
                list($doctor,$assen,$patient) = explode('-',str_ireplace('.pdf','',$outfile),3);
            }

            $exists = $wpdb->get_var("select th_ID from ci_tox_hos where subfolder='{$sub}' and file_name='{$outfile}' ");
            if(!$exists)
            {
                $insert['assen'] = $assen;
                $insert['clinic'] = $clinic;
                $insert['doctor'] = $doctor;
                $insert['patient_name'] = $patient;
                $insert['subfolder'] = $sub;
                $insert['file_name'] = $outfile;
                $wpdb->insert('ci_tox_hos',$insert);
            }
        }
    }

    Tr_Session_Class::add("Added successfully");

}
else{

    foreach($dirs as $sub => $path)
    {
        $d = opendir($path);
        while($outfile = readdir($d))
        {
            $insert = array(
                'created' => Tr_Base_Class_V4::date('Y-m-d H:i:s'),
                'user_id' => get_current_user_id()
            );
            if($outfile=='.' || $outfile=='..')continue;
            $filepath = $path.$outfile;

            if($sub=='Req'){
                list($doc,$clinic,$assen,$patient) = explode('-',str_ireplace('.pdf','',$outfile),4);
                if($doc>0)
                {
                    preg_match("/([0-9]{4})([0-9]{2})([0-9]{2})/",$doc,$doc_str);
                    if($doc_str)
                    {
                        $cdatefile = $doc_str[1].'-'.$doc_str[2].'-'.$doc_str[3];
                        $insert['doc'] = $cdatefile;
                    }
                }
            }else if($sub=='Notes'){
                list($assen,$patient) = explode('-',str_ireplace('.pdf','',$outfile),2);
            }else{
                list($doctor,$assen,$patient) = explode('-',str_ireplace('.pdf','',$outfile),3);
            }

            $exists = $wpdb->get_var("select th_ID from ci_tox_hos where subfolder='{$sub}' and file_name='{$outfile}' ");
            if(!$exists)
            {
                $insert['assen'] = $assen;
                $insert['clinic'] = $clinic;
                $insert['doctor'] = $doctor;
                $insert['patient_name'] = $patient;
                $insert['subfolder'] = $sub;
                $insert['file_name'] = $outfile;
                $wpdb->insert('ci_tox_hos',$insert);
            }
        }
    }

//update collect date
    $wpdb->query("update `ci_tox_hos` h1 join ci_tox_hos h2 ON h1.assen = h2.assen and h2.doc>'0000-00-00' and h1.doc='0000-00-00' set h1.doc=h2.doc");


}


?>

<div class="wrap">
    <h2>Tox Hos Upload</h2>
    <?php Tr_Session_Class::show()?>
    <br><br>
    <form method="post" enctype="multipart/form-data">
        <div class="frow">
            <div class="col3">
                <label>Request PDFs</label>
                <input type="file" name="Req[]" multiple/>
            </div>
            <div class="col3">
                <label>Result PDFs</label>
                <input type="file" name="Results[]" multiple/>
            </div>
            <div class="col3">
                <label>Notes PDFs</label>
                <input type="file" name="Notes[]" multiple/>
            </div>
        </div>
        <div class="frow">
            <input type="submit" class="button-primary" value="Submit"/>
            <input type="hidden" name="insert_files" value="upload"/>
        </div>
    </form>
</div>