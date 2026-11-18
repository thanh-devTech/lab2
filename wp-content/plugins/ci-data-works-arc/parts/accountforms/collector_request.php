<?php include_once(TRCPDD_PATH.'pages/formdata.php');?>
<?php
global $current_role;
$cli = array();
$clinics = array(''=>'Please Select');
$docs = array(''=>'Please Select');

if(isset($_POST['clinic_id']))
{
    $cli_id = $_POST['clinic_id'];
    $cli = $wpdb->get_row("select * from ci_clinic where `cID`={$cli_id}", ARRAY_A);
}

if($current_role=='lab_rep'  || $current_role=='lab_rep_w2')
{
    $rep_id = get_user_meta(get_current_user_id(),'_rep_id',true);
    $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);


    $clis = $wpdb->get_results("select * from ci_clinic where `cli_rep-ID`={$rep_id}", ARRAY_A);

    foreach($clis as $row)
    {
        $clinics[$row['cID']] = $row['cli_clinic'];
    }
}

if($cli_id > 0)
{
    $cli['physicians'] = array();
    $docs = $wpdb->get_results("select doc.* from ci_doc doc
                        JOIn ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
                        where cd.cli_ID = {$cli_id}");

    foreach($docs as $doc)
    {
        $cli['physicians'][] = $doc->doc_first.' '.$doc->doc_last;
    }
    $cli['physicians'] = implode(', ',$cli['physicians']);

}
?>
<h2>COLLECTOR REQUEST FORM</h2>
<?php Tr_Session_Class::show()?>
<form method="post" class="need_validate">

    <div class="frow">
        <h4>Sales Representative</h4>
    </div>

    <div class="frow blabel">
        <div class="col4">
            <label>Name</label>
            <input type="text" name="col[col_first]" value="<?php echo $col['col_first']?>"/>
        </div>
        <div class="col4">
            <label>Phone</label>
            <input type="text" class="has_phone" name="col[col_phone]" value="<?php echo $col['col_phone']?>"/>
        </div>
        <div class="col4">
            <label>Date of Request</label>
            <input type="text" class="hasdate today" name="col[col_rdate]" value="<?php echo $col['col_rdate']?>"/>
        </div>
        <div class="col4">
            <label>Collector Start Date</label>
            <input type="text" class="hasdate today"  name="col[col_sdate]" value="<?php echo $col['col_sdate']?>"/>
        </div>
    </div>
    <div id="cli_change_div">
        <div class="frow blabel">
            <div class="col4">
                <label>Practice Name</label>
                <?php tr_select_option('col[cli_ID]',$clinics,$cli['cID'],array('id'=>'cli_change'))?>
            </div>
            <div class="col4">
                <label>Physicians</label>
                <input type="text" name="col[col_physicians]" value="<?php echo $cli['physicians']?>"/>
            </div>
            <div class="col2">
                <label>Address</label>
                <input type="text" name="col[col_street]" value="<?php echo $cli['cli_street']?>"/>
            </div>

        </div>

        <div class="frow blabel">
            <div class="col4">
                <label>Telephone Number</label>
                <input type="text" class="has_phone"  name="col[col_phone]" value="<?php echo $cli['cli_om-phone']?>"/>
            </div>
            <div class="col4">
                <label>Fax Number</label>
                <input type="text" name="col[col_fax]" value="<?php echo $cli['cli_om-fax']?>"/>
            </div>

        </div>
    </div>
    <div class="frow">
        <h4>Office Contact</h4>
    </div>

    <div class="frow blabel">
        <div class="col4">
            <label>Phone</label>
            <input type="text" class="has_phone" name="col[col_office_phone]" value="<?php echo $col['col_office_phone']?>"/>
        </div>
        <div class="col3">
            <label>Email</label>
            <input type="email" name="col[col_email]" value="<?php echo $col['col_email']?>"/>
        </div>
    </div>

    <div class="frow">
        <h4>Hours Requested for Collector</h4>
    </div>

    <div class="frow ilabel">
        <div class="col6">
            <label>Mon</label>
            <input type="text" name="col[col_mon]" value="<?php echo $col['col_mon']?>"/>
        </div>
        <div class="col6">
            <label>Tue</label>
            <input type="text" name="col[col_tue]" value="<?php echo $col['col_tue']?>"/>
        </div>
        <div class="col6">
            <label>Wed</label>
            <input type="text" name="col[col_wed]" value="<?php echo $col['col_wed']?>"/>
        </div>
        <div class="col6">
            <label>Thu</label>
            <input type="text" name="col[col_thu]" value="<?php echo $col['col_thu']?>"/>
        </div>
        <div class="col6">
            <label>Fri</label>
            <input type="text" name="col[col_fri]" value="<?php echo $col['col_fri']?>"/>
        </div>
        <div class="col6">
            <label>Sat</label>
            <input type="text" name="col[col_sat]" value="<?php echo $col['col_sat']?>"/>
        </div>
    </div>

    <div class="frow">
        <h4>Please check all that apply</h4>
    </div>

    <div class="frow iblabel">
        <label>
            <input type="hidden" name="col[col_poc-cups]" value="0"/>
            <input type="checkbox" name="col[col_poc-cups]" value="1" <?php checked($col['col_poc-cups'])?>/>
            POC Cups
        </label>
        <label>
            <input type="hidden" name="col[col_fax-result]" value="0"/>
            <input type="checkbox" name="col[col_fax-result]" value="1" <?php checked($col['col_fax-result'])?>/>
            FAX Results
        </label>
        <label>
            <input type="hidden" name="col[col_analyser]" value="0"/>
            <input type="checkbox" name="col[col_analyser]" value="1" <?php checked($col['col_analyser'])?>/>
            Analyzer
        </label>
        <label>
            <input type="hidden" name="col[col_portal]" value="0"/>
            <input type="checkbox" name="col[col_portal]" value="1" <?php checked($col['col_portal'])?>/>
            Online Portal results
        </label>
    </div>

    <div class="frow">
        <h4>NOTES:</h4>
    </div>

    <div class="frow">
        <textarea  name="col[col_notes]"><?php echo $col['col_notes']?></textarea>
    </div>

    <div class="frow">
        <input type="submit" class="button-primary" value="Submit">
        <input type="hidden" name="tr_action" value="form_collector_request">
    </div>

</form>