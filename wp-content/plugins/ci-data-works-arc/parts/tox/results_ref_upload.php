<?php
global $wpdb,$current_role;

include_once(TRCPDD_PATH.'pages/formdata.php');
$dir = WP_CONTENT_DIR.'/results/';
if( ! file_exists( $dir ) ){
    wp_mkdir_p( $dir );
}

$user_id = get_current_user_id();

$tabs = array(
    '' => 'Ref Tox Results',
    'list' => 'Ref Tox Results'
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
	include( trpdd_get_file_path('tox','results_ref_list') );
    return;
}

if($_POST['bat'])
{
    $bat = $_POST['bat'];
    $files = $_FILES['result_pdf'];
    $clinic = $wpdb->get_var("select cli_clinic from ci_clinic WHERE cID = {$bat['cli_ID']}");
    $doctor = $wpdb->get_row("select doc_last,doc_first from ci_doc WHERE doc_ID = {$bat['doc_ID']}");
    $doctor = $doctor->doc_last.', '.$doctor->doc_first;
    $clinic = sanitize_title($clinic);
    $doctor = sanitize_title($doctor);
    $doc = date('Y-m-d',strtotime($bat['doc']));

    $folder  = 'B3D'.'/'.$clinic.'/'.$doctor.'/'.$doc;

    if( ! file_exists( $dir.$folder ) ){
        wp_mkdir_p( $dir.$folder );
    }
    $bat['user_id'] = get_current_user_id();
    $bat['file_path'] = $folder;
    $bat['created'] = Tr_Base_Class_V6::date('Y-m-d H:i:s');
    $cdate = date('Ymd', strtotime($bat['doc'])).'_udt_';

    //add request pdf
    if($_FILES['request_pdf']['name']){
        $outfile = $cdate.'_bltreq_'.$_FILES['request_pdf']['name'];
        $filepath = $dir.$folder.'/'.$outfile;
        if(move_uploaded_file($_FILES['request_pdf']['tmp_name'],$filepath)){

            $bat['request_pdf'] = $outfile;
        }else{
            Tr_Session_Class::add_error('Can\'t upload file: '.$outfile);
        }
    }

    $ids = array();
    foreach($files['name'] as $i => $outfile)
    {
        $outfile = $cdate .$outfile;
        $tmp = $files['tmp_name'][$i];
        $filepath = $dir.$folder.'/'.$outfile;

        $bat['file_name'] = $outfile;
        if(move_uploaded_file($tmp,$filepath)){
            $rs = $wpdb->insert('ci_igniting_qss',$bat);
            $ids[] = $wpdb->insert_id;
        }else{
            Tr_Session_Class::add_error('Can\'t upload file: '.$outfile);
        }
    }


    if(count($ids)>0){
        $ids = implode(',',$ids);
        $today = Tr_Base_Class_V6::date('Y-m-d');
        Tr_Session_Class::add("Added successfully");
        ?>
        <script>
            jQuery(function($){
                setTimeout(function(){
                    tb_show('Upload','?tr_action=ci_upload_qss_to_box&date=<?php echo $today?>&ids=<?php echo $ids?>&type=tox&TB_iframe=true&width=400&height=300');
                },500);
            })
        </script>
        <?php
        $bat = array();
    }
    else{
        Tr_Session_Class::add_error('Can\'t upload file');
    }

    $_POST['clinic_id'] = $bat['cli_ID'];

}





$clinics    = array(''=>'Please Select');
$docs       = array(''=>'Please Select');
$cli_id     = $_POST['clinic_id'];



$conditions     = trpdd_get_batch_conditions(0);
$where_clis     = $conditions['where_clinic'];


$sql_clinic = "select cli.* from ci_clinic cli
                    Left JOIn ci_clinic_collector cc ON cc.cli_ID = cli.cID
                    left join ci_lab_reps lr on cli.`cli_rep-ID` = lr.rep_ID
                    left join ci_rep_collector rc ON cli.`cli_rep-ID` = rc.rep_ID
                    where 1 {$where_clis} group by cli.cID order by cli.cli_clinic";
$clinics = $wpdb->get_results($sql_clinic, ARRAY_A);

if($cli_id > 0) {
    $ds = $wpdb->get_results("select doc.* from ci_doc doc
                        JOIn ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
                        LEFT JOIN ci_custom_providers cp ON doc.doc_ID = cp.doc_ID
                        where cd.cli_ID = {$cli_id}");

    foreach ($ds as $doc) {
        $docs[$doc->doc_ID] = $doc->doc_first . ' ' . $doc->doc_last;
    }
}
?>

<div class="wrap">
    <h2>Upload Ref Tox Results</h2>
    <?php Tr_Session_Class::show()?>
    <br><br>
    <form method="post" enctype="multipart/form-data">
        <div class="frow">
            <div class="col2">
                <label>Clinic</label>
                <select name="bat[cli_ID]" required id="cli_change" class="select2">
                    <option value="">Select Clinic</option>
                    <?php
                    foreach($clinics as $clinic)
                    {
                        $selected = $bat['cli_ID'] == $clinic['cID']? 'selected':'';
                        echo '<option value="'.$clinic['cID'].'" '.$selected.'>'.$clinic['cli_clinic'].'</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="col2" id="cli_change_div">
                <label>ORDERING PHYSICIAN</label>
                <?php
                tr_select_option('bat[doc_ID]',$docs,@$bat['doc_ID'],array('attr'=>'required'));
                ?>
            </div>
        </div>

        <div class="frow">
            <div class="col2">
                <label>Date of Collection</label>
                <input type="text"  class="hasdatetime  <?php if(tr_get_option('ci_pdf_data','limit_doc'))echo 'limitfuture'?> <?php if($current_role!='lab_tech')echo 'today'?>" <?php if(tr_current_role_in(array('administrator','AdminCIDW','administrator_p','lab','labsecman','leadlabsecman','lab_manager','lab_sec','lab_tech')))echo 'data-write="true"'?> name="bat[doc]" value="<?php echo @$bat['doc']?>"  required=""/>
            </div>
        </div>
        <div class="frow">
            <div class="col2">
                <label>Result PDFs</label>
                <input type="file" name="result_pdf[]" multiple required/>
            </div>
            
            <div class="col2">
                <label>Request PDF</label>
                <input type="file" name="request_pdf" />
            </div>
        </div>
        <div class="frow">
            <input type="submit" class="button-primary" value="Submit"/>
            <input type="hidden" name="bat[is_dna]" value="4"/>
        </div>
    </form>
</div>
<script>
    jQuery(function($){
        $('.select2').select2();
    })
</script>