<?php
global $current_role;

$lab = array();
$doc = array();

$cpts = array('G6042','G6044','83992','G6031','81002','G6040','83789','G6052','G6043','82542','G6053','G6032','82570','G6045','P81003','G6046','84311');
$diagnosis = array('Z78.891','Z79.899','F11.20','F19.20');

$mid = $pri['pri_mid'];
$batch = $wpdb->get_row("select * from ci_batches where mid='{$mid}'", ARRAY_A);
$doc_id = $batch['doc_ID'];
$lab_id = $batch['lab_id'];
$pa_id =  $batch['patient_id'];

$doc = $wpdb->get_row("select * from ci_doc where doc_ID = {$doc_id}", ARRAY_A);
$lab = $wpdb->get_row("select * from ci_labs where lab_ID = {$lab_id}", ARRAY_A);
$patient = $wpdb->get_row("select * from ci_patients where pa_ID = {$pa_id}", ARRAY_A);


$lab['lab_url'] = trpdd_get_lab_url($lab['lab_site']);

?>
<div class="wrap_form wrap">
    <h2>Prior Authorization Request Form</h2>


    <div class="frow blabel">
        <div style="float:left;width:23%">
            <label>Health Plan Name</label><br>
            <?php echo @$pri['pri_plan_name']?>
        </div>
        <div style="float:left;width:23%">
            <label>Health Plan Fax #</label><br>
            <?php echo @$pri['pri_plan_fax']?>
        </div>
        <div style="float:left;width:23%">
            <label>Health Plan Email</label><br>
            <?php echo @$pri['pri_plan_email']?>
        </div>
        <div style="float:left;width:23%">
            <label>Date of Service Requested</label><br>
            <?php echo @$pri['pri_date']?>
        </div>

    </div>

    <div style="clear:both">
        <h4>Service Type Requiring Authorization</h4>
    </div>
    <div style="clear:both">

        Laboratory:  <?php echo $lab['lab_dba'].' ('.$lab['lab_dba_init'].')';?>  – Urine Drug Toxicology Testing

    </div>

    <div style="clear:both">
        <h4>SECTION I:  Patient Information	</h4>
    </div>

    <div style="clear:both">
        <div style="float:left;width:48%">
            <label>Last Name, First Name: </label> <?php echo @$patient['pa_last'] .' ' . @$patient['pa_first']?>
        </div>
        <div style="float:left;width:48%">
            <label>Date of Birth: </label> <?php echo @$patient['pa_dob']?>
        </div>
    </div>
    <div class="frow blabel">
        <div style="float:left;width:48%">
            <label>Street Address: </label>
            <?php echo @$patient['pa_address']?>
        </div>
        <div style="float:left;width:48%">
            <label>Primary Phone #: </label>
            <?php echo @$patient['pa_phone']?>
        </div>
    </div>
    <div class="frow blabel">
        <div style="float:left;width:48%">
            <label>City: </label>
            <?php echo @$patient['pa_city']?>
        </div>
        <div style="float:left;width:48%">
            <label>State: </label>
            <?php echo @$patient['pa_state']?>
        </div>
    </div>
    <div class="frow blabel">
        <div style="float:left;width:48%">
            <label>Health Insurance ID #: </label>
            <?php echo @$pri['pri_ins_id']?>
        </div>
        <div style="float:left;width:48%">
            <label>ZIP Code: </label>
            <?php echo @$patient['pa_zip']?>
        </div>
    </div>

    <div style="clear:both;padding-top:10px">
        <h4>SECTION II:  Laboratory Information	</h4>
    </div>


    <div style="clear:both">
        <div style="float:left;width:48%">
            <label>Laboratory Name: </label>
            <?php echo $lab['lab_dba']?>
        </div>
        <div style="float:left;width:48%">
            <label>Laboratory Address: </label>
            <?php echo @$lab['lab_address'].', '.@$lab['lab_city'].', '.@$lab['lab_state'].', '.@$lab['lab_zip']?>
        </div>
    </div>
    <div class="frow blabel">
        <div style="float:left;width:48%">
            <label>Lab NPI: </label>
            <?php echo @$lab['lab_npi']?>
        </div>
        <div style="float:left;width:48%">
            <label>Lab Tax ID: </label>
            <?php echo @$lab['lab_tax_id']?>
        </div>
    </div>
    <div style="clear: both;">
        <div style="float:left;width:48%">
            <label>Lab CLIA: </label>
            <?php echo @$lab['lab_clia']?>
        </div>
        <div style="float:left;width:48%">
            <label>Lab URL: </label>
            <?php echo @$lab['lab_url']?>
        </div>
    </div>
    <div style="clear:both">

        <div style="float:left;width:48%">
            <label>Lab Support Email: </label>
            <?php echo @$lab['lab_email']?>
        </div>
        <div style="float:left;width:48%">
            <label>Phone: </label>
            <?php echo @$lab['lab_phone']?>
        </div>
    </div>
    <div style="clear:both">
        <div style="float:left;width:48%">
            <label>Fax: </label>
            <?php echo @$lab['lab_fax']?>
        </div>
    </div>


    <div style="clear:both;padding-top:10px;">
        <h4>SECTION III:  Requesting Provider	</h4>
    </div>
    <div style="clear:both">
        MEDICAL NECESSITY: Our facility has selected <?php echo @$lab['lab_dba']?> (<?php echo @$lab['lab_dba_init']?>) to perform all of its quantification, and if necessary, qualitative urine drug testing.  Our choice over an in-network facility is based on <?php echo @$lab['lab_dba_init']?>'s ability to offer an extensive drug panel analysis with emphasis on Schedule II narcotics, illicit drugs, and other potentially harmful drugs.  Our choice takes into serious consideration the importance of properly and URGENTLY handling these types of specimens with care and precision to avoid mishandling or potentially mislabeling patients while on controlled substances.  <?php echo @$lab['lab_dba_init']?> is one of few laboratories that can provide this level of testing with a quick turnaround, which does not compromise the final reporting product.  Furthermore, the reports include summary remarks that are found to be clinically applicable.

    </div>

    <div style="clear:both;padding-top:10px;">
        <div style="float:left;width:48%">
            <label>Requestor's Name: </label>
            <?php echo @$doc['doc_first'].' '. @$doc['doc_last']?>
        </div>
        <div style="float:left;width:48%">
            <label>NPI: </label>
            <?php echo @$doc['doc_npi']?>
        </div>
    </div>

    <div style="clear:both">
        <div style="float:left;width:48%">
            <label>Requestor's Address: </label>
            <?php echo @$doc['doc_address']?>
        </div>
        <div style="float:left;width:48%">
            <label>City: </label>
            <?php echo @$doc['doc_city']?>
        </div>
    </div>
    <div style="clear:both">
        <div style="float:left;width:48%">
            <label>State: </label>
            <?php echo @$doc['doc_state']?>
        </div>
        <div style="float:left;width:48%">
            <label>Zip: </label>
            <?php echo @$doc['doc_zip']?>
        </div>
    </div>


    <div style="clear:both;padding-top:10px;">
        <h4>SECTION IV:  Test Requested	</h4>
    </div>

    <div style="clear:both">
        <?php
        $pri_cpt_s = explode('|',$pri['pri_cpt']);
        foreach($cpts as $cpt):?>
            <div style="float:left;width:30%">
                <label> <?php echo (in_array($cpt,$pri_cpt_s))? '<b>x</b>':'-';?>
                    &nbsp;&nbsp;CPT:
                    <?php echo $cpt?>
                </label><br>
            </div>
        <?php endforeach;?>
    </div>

    <div style="clear:both;padding-top:10px;">
        <h4>SECTION V: Diagnosis</h4>
    </div>

    <div style="clear:both">
        <?php
        $diagnosis_s = explode('|',$pri['pri_diagnosis']);
        foreach($diagnosis as $i =>$di):?>
            <?php if($i%2==0):?>
                <div style="float:left;width:30%">
                    Dx
                </div>
            <?php endif;?>
            <div style="float:left;width:30%">
                <label>
                    <?php echo (in_array($di,$diagnosis_s))? '<b>x</b>':'-';?>
                    &nbsp;&nbsp;<?php echo $di?>
                </label><br>
            </div>
        <?php endforeach;?>
        <div style="float:left;width:30%">
            Dx
        </div>
        <div style="float:left;width:64%">
            <label>
                <?php echo (in_array('Z03.89',$diagnosis_s))? '<b>x</b>':'-';?>
                &nbsp;&nbsp;
                Z03.89 Primary plus
            </label>
            <br>
            <?php
            $full_diagnosis = array_merge($diagnosis,array('Z03.89'));
            $vl = end($diagnosis_s);
            if(!in_array($vl,$full_diagnosis)){
                echo $vl;
            }
            ?>(Specific Drug Dependence Diagnosis)
        </div>
    </div>

    <div style="clear:both;padding-top:10px;">
        <h4>SECTION VI:  Additional Comments	</h4>
    </div>
    <div style="clear:both">
        <?php echo @$pri['pri_comments']?>
    </div>

</div>