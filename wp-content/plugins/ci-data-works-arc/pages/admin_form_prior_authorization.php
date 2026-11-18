<?php
global $current_role;
global $lab_id;

$lab = array();
$doc = array();

$cpts = array('G6042','G6044','83992','G6031','81002','G6040','83789','G6052','G6043','82542','G6053','G6032','82570','G6045','81003','G6046','84311');
$diagnosis = array('Z78.891','Z79.899','F11.20','F19.20');

if(!isset($pri))
{
    $mid = $_REQUEST['mid'];
    $batch = $wpdb->get_row("select * from ci_batches where mid='{$mid}'", ARRAY_A);
    if(!$batch)
    {
        return;
    }
    $pri = array();
    $pri['pri_assen'] = $batch['assen'];
    $pri['pri_mid'] = $batch['mid'];
    $doc_id = $batch['doc_ID'];
    $lab_id = $batch['lab_id'];
    $pa_id =  $batch['patient_id'];

    $doc = $wpdb->get_row("select * from ci_doc where doc_ID = {$doc_id}", ARRAY_A);
    $lab = $wpdb->get_row("select * from ci_labs where lab_ID = {$lab_id}", ARRAY_A);
    $patient = $wpdb->get_row("select * from ci_patients where pa_ID = {$pa_id}", ARRAY_A);


    $lab['lab_url'] = trpdd_get_lab_url($lab['lab_site']);


}
?>
<div class="wrap_form wrap">
    <h2>Prior Authorization Request Form</h2>
    <?php Tr_Session_Class::show()?>

    <form method="post" enctype="multipart/form-data" class="need_validate" id="requisition_form">

        <div class="frow blabel">
            <div class="col4">
                <label>Health Plan Name</label>
                <input type="text" name="pri[pri_plan_name]" value="<?php echo @$pri['pri_plan_name']?>" required="">
            </div>
            <div class="col4">
                <label>Health Plan Fax #</label>
                <input type="text" name="pri[pri_plan_fax]" value="<?php echo @$pri['pri_plan_fax']?>">
            </div>
            <div class="col4">
                <label>Health Plan Email</label>
                <input type="email" name="pri[pri_plan_email]" value="<?php echo @$pri['pri_plan_email']?>" required="">
            </div>
            <div class="col4">
                <label>Date of Service Requested</label>
                <input type="text" class="hasdate today" name="pri[pri_date]" value="<?php echo @$pri['pri_date']?>" required="">
            </div>

        </div>

        <div class="frow">
            <h4>Service Type Requiring Authorization</h4>
        </div>
        <div class="frow">

            Laboratory:  <?php echo $lab['lab_dba'].' ('.$lab['lab_dba_init'].')';?> – Urine Drug Toxicology Testing


        </div>

        <div class="frow">
            <h4>SECTION I:  Patient Information	</h4>
        </div>

        <div class="frow">
            <div class="col2">
                <label>Last Name, First Name</label>
                <input type="text"  value="<?php echo @$patient['pa_last'] .' ' . @$patient['pa_first']?>">
            </div>
            <div class="col2">
                <label>Date of Birth</label>
                <input type="text" value="<?php echo @$patient['pa_dob']?>">
            </div>
        </div>
        <div class="frow blabel">
            <div class="col2">
                <label>Street Address</label>
                <input type="text" value="<?php echo @$patient['pa_address']?>" />
            </div>
            <div class="col2">
                <label>Primary Phone #</label>
                <input type="text" class="has_phone" value="<?php echo @$patient['pa_phone']?>" />
            </div>
        </div>
        <div class="frow blabel">
            <div class="col2">
                <label>City</label>
                <input type="text" value="<?php echo @$patient['pa_city']?>" />
            </div>
            <div class="col2">
                <label>State</label>
                <input type="text" value="<?php echo @$patient['pa_state']?>" />
            </div>
        </div>
        <div class="frow blabel">
            <div class="col2">
                <label>Health Insurance ID #</label>
                <input type="text" name="pri[pri_ins_id]" value="<?php echo @$pri['pri_ins_id']?>" />
            </div>
            <div class="col2">
                <label>ZIP Code</label>
                <input type="text"  value="<?php echo @$patient['pa_zip']?>" />
            </div>
        </div>

        <div class="frow">
            <h4>SECTION II:  Laboratory Information	</h4>
        </div>


        <div class="frow">
            <div class="col2">
                <label>Laboratory Name</label>
                <input type="text"  value="<?php echo $lab['lab_dba']?>"/>
            </div>
            <div class="col2">
                <label>Laboratory Address</label>
                <input type="text"  value="<?php echo @$lab['lab_address'].', '.@$lab['lab_city'].', '.@$lab['lab_state'].', '.@$lab['lab_zip']?>"/>
            </div>
        </div>
        <div class="frow blabel">
            <div class="col5">
                <label>Lab NPI</label>
                <input type="text"  value="<?php echo @$lab['lab_npi']?>"/>
            </div>
            <div class="col5">
                <label>Lab Tax ID</label>
                <input type="text"  value="<?php echo @$lab['lab_tax_id']?>"/>
            </div>
            <div class="col5">
                <label>Lab CLIA</label>
                <input type="text"  value="<?php echo @$lab['lab_clia']?>"/>
            </div>
            <div class="col5">
                <label>Lab URL</label>
                <input type="text"  value="<?php echo @$lab['lab_url']?>"/>
            </div>
            <div class="col5">
                <label>Lab Support Email</label>
                <input type="email"  value="<?php echo @$lab['lab_email']?>"/>
            </div>
        </div>
        <div class="frow ilabel">
            <div class="col2">
                <label>Phone: </label>
                <input type="text" class="has_phone"  value="<?php echo @$lab['lab_phone']?>"/>
            </div>
            <div class="col2">
                <label>Fax: </label>
                <input type="text"  value="<?php echo @$lab['lab_fax']?>"/>
            </div>
        </div>


        <div class="frow">
            <h4>SECTION III:  Requesting Provider	</h4>
        </div>
        <div class="frow">
            MEDICAL NECESSITY: Our facility has selected <?php echo @$lab['lab_dba']?> (<?php echo @$lab['lab_dba_init']?>) to perform all of its quantification, and if necessary, qualitative urine drug testing.  Our choice over an in-network facility is based on <?php echo @$lab['lab_dba_init']?>'s ability to offer an extensive drug panel analysis with emphasis on Schedule II narcotics, illicit drugs, and other potentially harmful drugs.  Our choice takes into serious consideration the importance of properly and URGENTLY handling these types of specimens with care and precision to avoid mishandling or potentially mislabeling patients while on controlled substances.  <?php echo @$lab['lab_dba_init']?> is one of few laboratories that can provide this level of testing with a quick turnaround, which does not compromise the final reporting product.  Furthermore, the reports include summary remarks that are found to be clinically applicable.

        </div>

        <div class="frow">
            <div class="col2">
                <label>Requestor's Name</label>
                <input type="text" value="<?php echo @$doc['doc_first'].' '. @$doc['doc_last']?>"/>
            </div>
            <div class="col2">
                <label>NPI</label>
                <input type="text"  value="<?php echo @$doc['doc_npi']?>" >
            </div>
        </div>

        <div class="frow">
            <div class="col2">
                <label>Requestor's Address</label>
                <input type="text" value="<?php echo @$doc['doc_address']?>" />
            </div>
            <div class="col2">
                <label>City</label>
                <input type="text" value="<?php echo @$doc['doc_city']?>" />
            </div>
        </div>
        <div class="frow">
            <div class="col2">
                <label>State</label>
                <input type="text" value="<?php echo @$doc['doc_state']?>" />
            </div>
            <div class="col2">
                <label>Zip</label>
                <input type="text" value="<?php echo @$doc['doc_zip']?>" />
            </div>
        </div>


        <div class="frow">
            <h4>SECTION IV:  Test Requested	</h4>
        </div>

        <div class="frow" id="pri_cpt_list">
            <?php foreach($cpts as $cpt):?>
            <div class="col3">
                <label> CPT:
                    <input type="checkbox" name="pri[pri_cpt][]" value="<?php echo $cpt?>"/>
                    <?php echo $cpt?>
                </label>
            </div>
            <?php endforeach;?>
        </div>
        <div class="row">
            <a class="selectall" href="#" data-target="pri_cpt_list">select all</a>
            <a class="unselectall" href="#" data-target="pri_cpt_list">unselect all</a>
        </div>

        <div class="frow">
            <div class="col2">
                <div class="frow">
                    <h4>SECTION V: Diagnosis	</h4>
                </div>

                <div class="frow">
                    <?php foreach($diagnosis as $i =>$di):?>
                        <?php if($i%2==0):?>
                            <div class="col3">
                                Dx
                            </div>
                        <?php endif;?>
                        <div class="col3">
                            <label>
                                <input type="checkbox" name="pri[pri_diagnosis][]" value="<?php echo $di?>"/>
                                <?php echo $di?>
                            </label>
                        </div>
                    <?php endforeach;?>
                    <div class="col3">Dx</div>
                    <div class="col32">
                        <label>
                            <input type="checkbox" name="pri[pri_diagnosis][]" value="Z03.89"/>
                            Z03.89 Primary plus
                        </label>
                        <br>
                        <input type="text" name="pri[pri_diagnosis][]" value="" style="width: 35%"/>(Specific Drug Dependence Diagnosis)
                    </div>
                </div>
            </div>
            <div class="col2">
                <div class="frow">
                    <h4>SECTION VI:  Additional Comments	</h4>
                </div>
                <div class="frow">
                    <textarea name="pri[pri_comments]"><?php echo @$pri['pri_comments']?></textarea>
                </div>
            </div>
        </div>

        <div class="frow center">
            <p>Please fax/email to insurance company on DAY OF COLLECTION!!</p>
        </div>




        <div>
            <input type="submit" class="button-primary" value="Submit">
            <input type="hidden" name="tr_action" value="ci_save_prior_authorization"/>
            <input type="hidden" name="pri[pri_mid]" value="<?php echo @$pri['pri_mid']?>"/>
            <input type="hidden" name="pri[pri_assen]" value="<?php echo @$pri['pri_assen']?>"/>
        </div>

    </form>
</div>