<div class="frow">
    <?php
    $patientcol = 'col2';
    $providercol = 'col2';
    if ($settings['abovep_32']) {
        $patientcol = 'col23';
        $providercol = 'col13';
    }
    $provider_s_title = !empty($settings['abovepro_title']) ? $settings['abovepro_title'] : '';
    $provider_s_text = !empty($settings['abovepro_text']) ? $settings['abovepro_text'] : '';

    $patient_s_title = !empty($settings['abovep_title']) ? $settings['abovep_title'] : '';
    $patient_s_text = !empty($settings['abovep_text']) ? $settings['abovep_text'] : 'I hereby authorize the release of medical information related to the service described herein and authorize payment directly to <span id="lab_name_change_div">' . $current_lab['lab_dba'] . ' </span>, Inc.
            I agree to assume responsibility for payment of charges for laboratory services that are not covered by my healthcare insurer.';

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
    <div class="col2">
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
            echo $patient_signature_img;
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
        ?>
    </div>
    <div class="col2" id="provider_signdiv">
        <label><b>Authorized Healthcare</b></label>
        <label>Provider Signature:</label>
        <?php
        $fname = !empty($bat['provider_signature']) ? $bat['provider_signature'] : '';
        if (empty($fname) && !empty($current_doctor_sign)) {
            $fname = $current_doctor_sign;
        } else if (empty($fname) && $mid == 0 && $current_role == 'doctor') {
            $fname = trpdd_get_user_signature();
        }
        $provider_signature_img = '';
        if (!empty($fname) && file_exists($sign_dir['path'] . '/' . $fname) && filesize($sign_dir['path'] . '/' . $fname) > 0) {
            $provider_signature_img = '<img class="sign_img" src="' . $sign_dir['url'] . '/' . $fname . '?' . time() . '" style="width:300px"/>';
        }
        if ($act == 'view' || $act == 'print' || (isset($billing_page) && $billing_page)) {
            echo $provider_signature_img;
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