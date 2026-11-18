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
        ?>
    </div>
    <div class="<?php echo $providercol ?>" id="provider_signdiv">
        <label><b>Authorized Healthcare</b></label>
        <label>Provider Signature:</label>
        <?php
        $mid = isset($mid) ? $mid : 0;
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

<?php
if (isset($settings['show_paf_bsign']) && $settings['show_paf_bsign']) {
    if (isset($pa['pa_height']) && !empty($pa['pa_height'])) {

        echo '<div>Height:&nbsp;&nbsp;&nbsp; ' . $pa['pa_height'] . '</div>';
        echo '<div>Weight: &nbsp; ' . $pa['pa_weight'] . ' LBs</div>';
        $pa['bmi'] = trpdd_get_bmi($pa['pa_height'], $pa['pa_weight']);
        echo '<div>BMI: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . number_format($pa['bmi'], 1) . '</div>';
    }
    $Pulse = trpdd_get_batch_meta($mid, 'blood', 'Pulse');

    $Pressure1 = trpdd_get_batch_meta($mid, 'blood', 'Pressure1');
    $Pressure1_2 = trpdd_get_batch_meta($mid, 'blood', 'Pressure1_2');
    $Arm = trpdd_get_batch_meta($mid, 'blood', 'Arm');

    $Pressure2 = trpdd_get_batch_meta($mid, 'blood', 'Pressure2');
    $Pressure2_2 = trpdd_get_batch_meta($mid, 'blood', 'Pressure2_2');
    $Arm2 = trpdd_get_batch_meta($mid, 'blood', 'Arm2');

?>


    <?php include('questions.php') ?>

    <div class="frow">
        <h4>Patient Vitals: </h4>
    </div>
    <div class="frow">
        <div class="col4">
            <label>Pulse: </label>
            <input type="text" name="batmeta[Pulse]" value="<?php echo $Pulse ?>" style="width:50px" /> BPM
        </div>
        <div class="col3">
            <label>#1 Blood Pressure:</label>
            <input type="text" name="batmeta[Pressure1]" value="<?php echo $Pressure1 ?>" style="width:50px" /> /
            <input type="text" name="batmeta[Pressure1_2]" value="<?php echo $Pressure1_2 ?>" style="width:50px" />
            <select name="batmeta[Arm]" style="width:150px">
                <?php
                foreach (array('left' => 'Left Arm Sitting', 'right' => 'Right Arm Sitting') as $k => $txt) {
                    $selected = $k == $Arm ? 'selected' : '';
                    echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
                }
                ?>
            </select>
        </div>

        <div class="col3">
            <label>#2 Blood Pressure:</label>
            <input type="text" name="batmeta[Pressure2]" value="<?php echo $Pressure2 ?>" style="width:50px" /> /
            <input type="text" name="batmeta[Pressure2_2]" value="<?php echo $Pressure2_2 ?>" style="width:50px" />
            <select name="batmeta[Arm2]" style="width:150px">
                <?php
                foreach (array('left' => 'Left Arm Sitting', 'right' => 'Right Arm Sitting') as $k => $txt) {
                    $selected = $k == $Arm2 ? 'selected' : '';
                    echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
                }
                ?>
            </select>
        </div>

    </div>
    </div>



<?php

}
