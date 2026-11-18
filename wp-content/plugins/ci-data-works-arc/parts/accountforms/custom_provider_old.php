<?php
global $current_role;

include_once(TRCPDD_PATH.'pages/formdata.php');

$cli        = array();
$clinics    = array(''=>'Please select');
$where_cli  = '';

if($current_role == 'clinic'){
    $cli_id = get_user_meta(get_current_user_id(),'_clinic_id',true);
    $cli = $wpdb->get_row("select * from ci_clinic where `cID`={$cli_id}", ARRAY_A);
}
else if(isset($_POST['clinic_id']))
{
    $cli_id = $_POST['clinic_id'];
    $cli = $wpdb->get_row("select * from ci_clinic where `cID`={$cli_id}", ARRAY_A);
}

if($current_role=='lab_rep'  || $current_role=='lab_rep_w2')
{
    $rep_id = get_user_meta(get_current_user_id(),'_rep_id',true);
    $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);
    $where_cli .=" `cli_rep-ID`={$rep_id} ";
}

if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $where_cli .= " and `cli_lab-ID` in ( {$lab_str} ) ";
}

$clis = $wpdb->get_results("select * from ci_clinic where 1 {$where_cli} ", ARRAY_A);

if(isset($clis) && count($clis)>0)
{
    foreach($clis as $row)
    {
        $clinics[$row['cID']] = $row['cli_clinic'];
    }
}
?>
<h2>CUSTOM PROVIDER ACKNOWLEDGEMENT FORM</h2>
<?php Tr_Session_Class::show()?>
<form method="post" class="need_validate">


    <div id="cli_change_div">
        <div class="frow blabel">
            <div class="col4">
                <label>FACILITY/CLIENT NAME</label>
                <?php tr_select_option('cli[cID]',$clinics,$cli['cID'],array('id'=>'cli_change','attr'=>' required=""'))?>
            </div>
            
            <div class="col4">
                <label>PRIMARY CONTACT</label>
                <input type="text" name="cli[cli_om-phone]" value="<?php echo $cli['cli_om-phone']?>" required=""/>
            </div>
            <div class="col4">
                <label>EMAIL</label>
                <input type="email"  name="cli[cli_om-email]" value="<?php echo $cli['cli_om-email']?>" required=""/>
            </div>
        </div>
    </div>
    <div class="frow">
        <h4>IS TESTING CURRENTLY BEING PERFORMED AT YOUR FACILITY?</h4>
    </div>

   

    <div class="frow">
        <h4>SELECT A TESTING PROFILE</h4>
    </div>
    <div class="frow">
        Based on risk stratification (e.g. low, moderate, or high risk patients, new patients, red flags), the practitioner may customize their testing requirements. The
        laboratory offers the client the opportunity to select or create their own custom profile, along with the ability of creating the name of the panel that best
        suits their practice.
        <br>
    </div>

    

    <div class="frow">
        <h4>CONFIRM POSITIVE DRUG SCREEN RESULTS (applies ONLY if performing EIA or POC testing in office)</h4>
    </div>


    <div class="frow">
        <h4>Please Select Individual Therapeutic Drugs or Therapeutic Drug Class Section. <b>ONLY IF CUSTOM PANEL IS SELECTED.</b></h4>
    </div>
    <div class="frow">
        In the laboratory's commitment to medical necessity, the provider may further customize the testing choices based on specific clinical need. Listed
        below are all the therapetutic drug / drug classes available. Please review each therapeutic drug/ drug class and select the individual or therapeutic drug
        class that suits your testing requirements. By checking the class you agree to test for all the therapeutic drugs available at the laboratory in that class.
        <br>
    </div>

   

    <div class="frow">
        <h4>PROVIDER AUTHRORIZATION</h4>
    </div>

    <div class="frow">
        <b>I understand and hereby acknowledge:</b><br>
        1. I have requested the creation of this custom profile of tests listed on this form for the use in our clinic when merited.<br>
        2. I understand that when ordering tests for my patients, reimbursement from the insurance carrier will be sought. I should only order
        those tests which I believe are medically necessary.<br>
        3. I understand that by using a customized testing profile, Medicare and other federally-funded health care plans may deny payment.<br>
        4. I will order individual test or a less inclusive profile when not all the tests in the cusomized profile are medically necessary.<br>
        5. I understand that this preferred order will remain in effect until an updated form is completed.<br>
    </div>

    


    <div>
        <input type="submit" class="button-primary" value="Submit">
        <input type="hidden" name="tr_action" value="ci_save_form_custom_provider"/>
    </div>

</form>
<script>
    jQuery(function($){

        $('input[name="fvalue[testing_profile]"]').change(function(){
            if($(this).is(':checked') && $(this).val()=='custom')
            {
                $('#profile_custom_div').show();
            }else
            {
                $('#profile_custom_div').hide();
            }
        }).change();
        $('.testing_profile').click(function(){
            tb_show('', '?tr_action=show_basic_info&id='+$(this).data('id')+'&width=1000&height=600');
        });
        $('.psych_panel').click(function(){
            tb_show('', '?tr_action=show_basic_info&file=psych_1.jpg&id='+$(this).data('id')+'&width=800&height=600');
        });
    })
    function cancel_info(id){
        jQuery('#'+id).removeAttr('checked');
        tb_remove();
    }
</script>
<style>
    div.ui-tooltip {
        max-width: 800px !important;
    }
</style>