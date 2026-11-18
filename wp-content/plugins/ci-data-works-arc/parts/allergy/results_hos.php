<?php
global $wpdb,$current_role;
include_once(TRCPDD_PATH.'pages/formdata.php');

//check files
$dir_root = ABSPATH .'/LabPro/';
$dirs['Req'] = $dir_root.'Requests/';
$dirs['Notes'] = $dir_root.'Notes/';
$dirs['Results'] = $dir_root.'Results/';
$type = 'alt';




$tabs = array(
    '' => 'Allergy Hos',
    'list' => 'Allergy Hos Results'
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
    include( 'results_hos_list.php' );
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

            tr_pdd_insert_hos('ci_allergy_hos',$outfile,$sub);
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
            if($outfile=='.' || $outfile=='..'  || stripos($outfile,'-'.$type.'-')===false)continue;

            tr_pdd_insert_hos('ci_allergy_hos',$outfile,$sub);
        }
    }


}

//update collect date
$wpdb->query("update `ci_allergy_hos` h1 join ci_allergy_hos h2 ON h1.assen = h2.assen and h2.doc>'0000-00-00' and h1.doc='0000-00-00' set h1.doc=h2.doc");


?>

<div class="wrap">
    <h2>Allergy Hos Upload</h2>
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