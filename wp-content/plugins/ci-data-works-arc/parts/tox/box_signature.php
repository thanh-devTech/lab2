<div class="frow insidebreak">
    <?php
    $patientcol = 'col2';
    $providercol = 'col2';
    if (isset($settings['abovep_32']) && $settings['abovep_32']) {
        $patientcol = 'col23';
        $providercol = 'col13';
    }
    $provider_s_title = !empty($settings['abovepro_title']) ? $settings['abovepro_title'] : 'Practitioner Acknowledgement';
    $provider_s_text = !empty($settings['abovepro_text']) ? $settings['abovepro_text'] : 'In my professional judgement, the tests I order for this patient
    are medically necessary. I understant that each test that I order is a billable event. I also
    understand that my order and a requisition are required for each specimen sent to <span id="lab_name_change_div">' . $current_lab['lab_dba_init'] . '</span>. I
    understand that the patient\'s medical record must clearly reflect my order for testing.';

    $patient_s_title = !empty($settings['abovep_title']) ? $settings['abovep_title'] : 'Authorization';
    $patient_s_text = !empty($settings['abovep_text']) ? $settings['abovep_text'] : 'I certify that I have voluntarily provided a fresh and unadulterated urine specimen for analytical testing. The information provided on this form and on the label affixed to the
    specimen cup is accurate. I authorize <span id="lab_name_change_div">' . $current_lab['lab_dba'] . ' (' . $current_lab['lab_dba_init'] . ')</span> to release the results of this testing to the treating authorized healthcare provider or facility. I hereby authorize my
    insurance plan to be billed and benefits to be paid directly to <span id="lab_name_change_div">' . $current_lab['lab_dba_init'] . '</span> for services I received. I acknowledge that <span id="lab_name_change_div">' . $current_lab['lab_dba_init'] . '</span> may be an out-of network provider with my insurer. I am also aware that in
    some cases, my insurer will send the payment directly to me. I agree to endorse the insurance check and forward it to <span id="lab_name_change_div">' . $current_lab['lab_dba_init'] . '</span> within 30 days of receipt. Failure to do so may result in my account
    being forwarded to collection and reported to a credit bureau. I understand that <span id="lab_name_change_div">' . $current_lab['lab_dba_init'] . '</span> may use my specimen and any testing performed on that specimen for research, development and
    potential publication purposes, as long as the information has been properly de-identified pursuant to law.';

    if (empty($provider_s_title) && empty($provider_s_text)) {
        echo '<h4>' . $patient_s_title . '</h4>';
        echo $patient_s_text;
    } else {
    ?>
        <div class="<?php echo $patientcol ?>">
            <h4><?php echo $patient_s_title ?></h4>
            <div style="margin-right: 15px">
                <?php echo $patient_s_text ?>
            </div>
        </div>
        <div class="<?php echo $providercol ?>">
            <h4><?php echo $provider_s_title ?></h4>
            <?php echo $provider_s_text ?>
        </div>
    <?php } ?>
</div>
<div class="frow blabel">
    <div class="<?php echo $patientcol ?>">
        <br>
        <label>Patient Signature:</label>
        <?php
        $fname = !empty($bat['patient_signature']) ? $bat['patient_signature'] : '';
        $patient_signature_img = '';
        if ($fname == 'onfile') {
            $patient_signature_img = TRCPDD_URL . 'images/OnFile.png';
            $patient_signature_img = '<img class="sign_img" src="' . $patient_signature_img . '" style="width:300px"/>';
        } else if (!empty($fname) && file_exists($sign_dir['path'] . '/' . $fname) && filesize($sign_dir['path'] . '/' . $fname) > 0) {
            $patient_signature_img = '<img class="sign_img" src="' . $sign_dir['url'] . '/' . $fname . '?' . time() . '" style="width:300px"/>';
        }

        if ($act == 'view' || $act == 'print' || (isset($billing_page) && $billing_page)) {
            echo '<div class="signatureimgbox">';
            echo $patient_signature_img;
            echo '</div>';
        } else {
        ?>
            <div class="card_file">
                <?php
                echo '<div class="signaturepad" style="' . (empty($patient_signature_img) ? '' : 'display:none') . '">';
                echo tr_signature(array('name' => 'patient_signature', 'required' => false, 'clear' => false));
                echo '</div>';
                ?>
                <a class="button selectfile" href="#">Choose Signature Image</a>
                <a class="button-primary remove" rel="patient_signature">Clear</a>
                <br>
                <span class="view"><?php echo $patient_signature_img ?></span>
                <input type="file" name="patient_signature" accept="image/*" />
                <input type="hidden" name="img_patient_signature" value="<?php echo $fname ?>" />
                <?php if (!isset($bat['patient_signature']) || $bat['patient_signature'] != 'onfile') { ?>
                    <label class="inline">
                        <input type="checkbox" name="onfile" value="1" <?php checked($settings['auto_on_patient_signature_file']) ?> /> On-File
                    </label>
                <?php } ?>
            </div>
        <?php
        }
        if (tr_get_option('ci_pdf_data', 'show_submitdate') && !empty($patient_signature_img)) {
            echo date('m/d/Y H:i:s', strtotime($bat['batch_modified']));
        }
        ?>
    </div>
    <div class="<?php echo $providercol ?>" id="provider_signdiv">
        <label><b>Authorized Healthcare</b></label>
        <label>Provider Signature:</label>
        <?php
        $fname = !empty($bat['provider_signature']) ? $bat['provider_signature'] : '';
        if ((empty($fname) || stripos($fname, 'doc_') === 0) && !empty($current_doctor_sign)) {
            $fname = $current_doctor_sign;
        } else if (empty($fname) && $mid == 0 && $current_role == 'doctor') {
            $fname = trpdd_get_user_signature();
        }
        $provider_signature_img = '';
        if (!empty($fname) && file_exists($sign_dir['path'] . '/' . $fname) && filesize($sign_dir['path'] . '/' . $fname) > 0) {
            $provider_signature_img = '<img class="sign_img" src="' . $sign_dir['url'] . '/' . $fname . '?' . time() . '" style="width:300px"/>';
        }
        if ($act == 'view' || $act == 'print' || (isset($billing_page) && $billing_page)) {
            echo '<div class="signatureimgbox">';
            echo $provider_signature_img;
            echo '</div>';
        } else {
        ?>
            <div class="card_file">
                <?php
                echo '<div class="signaturepad" style="' . (empty($provider_signature_img) ? '' : 'display:none') . '">';
                echo tr_signature(array('name' => 'provider_signature', 'required' => false, 'clear' => false));
                echo '</div>';
                ?>
                <a class="button selectfile" href="#">Choose Signature Image</a>
                <a class="button-primary remove" rel="provider_signature">Clear</a>
                <br>
                <span class="view signimg"><?php echo $provider_signature_img ?></span>
                <input type="file" name="provider_signature" accept="image/*" />
                <input type="hidden" name="img_provider_signature" value="<?php echo $fname ?>" />
            </div>
        <?php
        }
        ?>
    </div>
</div>