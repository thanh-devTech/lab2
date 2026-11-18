<?php
$sign_dir    = tr_get_signature_dir();
$current_lab = isset($current_lab) && !empty($current_lab)
    ? $current_lab : CI_Base_Lab::get_current_lab();
$lab_init    = $current_lab['lab_dba_init'] ?? '';

$req_inc_pathogogy = !empty($settings['req_inc_pathogogy']);

$patient_s_title = !empty($settings['abovep_title'])
    ? $settings['abovep_title']
    : 'CONSENT FOR TESTING';

$patient_s_text = !empty($settings['abovep_text'])
    ? $settings['abovep_text']
    : "The information I have provided on this form is accurate.
I authorize {$lab_init} Diagnostics Laboratories to release the results of this test to my treating physician or facility.
I hereby authorize my insurance or other payment to {$lab_init} Diagnostics Laboratories for services I receive.
I am aware that {$lab_init} Diagnostics Laboratories may be an out of network provider with my insurer.
I am aware that I am responsible for all co-pays and deductibles not covered by insurance or other payers.";

$provider_s_title = !empty($settings['abovepro_title'])
    ? $settings['abovepro_title']
    : 'MEDICAL NECESSITY';

$provider_s_text = !empty($settings['abovepro_text'])
    ? $settings['abovepro_text']
    : 'As part of my antibiotic stewardship policy, I find it medically necessary to rapidly determine and differentiate a viral and/or bacterial infection in order to treat with or without appropriate antibiotics.
Having the most accurate and timely data available to me directly guides my treatment and patient management.
Empiric treatment and management leads to inappropriate and unnecessary antibiotic use (50% according to the CDC) and delayed diagnosis which can lead to severe consequences.';

$sig_layout_wide = !empty($settings['abovep_32']);
?>
<div class="req-section">
    <div class="req-section__head">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 20h9" />
            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z" />
        </svg>
        Signatures &amp; Consent
    </div>
    <div class="req-section__body">

        <?php if ($req_inc_pathogogy): ?>
            <div style="margin-bottom:.85rem;">
                <label class="req-chklbl">
                    <input type="checkbox" name="batmeta[_inc_pathogogy]" value="1">
                    <span style="font-weight:700;color:var(--req-danger);">Request Aegis Pathology Consult</span>
                </label>
                <p style="font-size:12px;color:var(--req-muted);margin:.35rem 0 0;line-height:1.55;">
                    If selected, I acknowledge that I will receive a pathology consult and interpretive report
                    from Aegis Health Services on the lab results for the patient's treatment and care, due to
                    the complexity of the patient's medical condition. I certify that I have discussed these
                    services with the patient, informed them that they will be provided by a consultative
                    provider, and noted in the medical record.
                </p>
            </div>
        <?php endif; ?>

        <div class="<?php echo $sig_layout_wide ? 'req-g3' : 'req-g2' ?>" style="margin-bottom:.85rem;">

            <div <?php echo $sig_layout_wide ? 'style="grid-column:span 2"' : '' ?>>
                <p style="font-weight:700;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;margin:0 0 .3rem;">
                    <?php echo esc_html($patient_s_title) ?>
                </p>
                <p style="font-size:12px;color:var(--req-muted);margin:0;line-height:1.55;white-space:pre-line;">
                    <?php echo esc_html($patient_s_text) ?>
                </p>
            </div>

            <div>
                <p style="font-weight:700;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;margin:0 0 .3rem;">
                    <?php echo esc_html($provider_s_title) ?>
                </p>
                <p style="font-size:12px;color:var(--req-muted);margin:0;line-height:1.55;white-space:pre-line;">
                    <?php echo esc_html($provider_s_text) ?>
                </p>
            </div>

        </div>

        <div class="<?php echo $sig_layout_wide ? 'req-g3' : 'req-g2' ?>">

            <!-- Patient signature -->
            <div <?php echo $sig_layout_wide ? 'style="grid-column:span 2"' : '' ?>>
                <p class="req-lbl" style="margin-bottom:.4rem;">Patient Signature</p>
                <?php
                $p_fname   = !empty($bat['patient_signature']) ? $bat['patient_signature'] : '';
                $p_sig_img = '';
                if ($p_fname === 'onfile') {
                    $p_sig_img = '<img class="sign_img" src="' . esc_url(TRCPDD_URL . 'images/OnFile.png') . '" style="max-width:260px;">';
                } elseif (
                    !empty($p_fname)
                    && file_exists($sign_dir['path'] . '/' . $p_fname)
                    && filesize($sign_dir['path'] . '/' . $p_fname) > 0
                ) {
                    $p_sig_img = '<img class="sign_img" src="' . esc_url($sign_dir['url'] . '/' . $p_fname . '?' . time()) . '" style="max-width:260px;">';
                }
                ?>
                <div class="req-sig-wrap" id="req-patient-sig-wrap">
                    <div class="req-sig-canvas" id="req-patient-canvas"
                        style="<?php echo $p_sig_img ? 'display:none;' : '' ?>border:1.5px solid var(--req-border);border-radius:.375rem;background:#fff;max-width:302px;">
                        <?php echo tr_signature(['name' => 'patient_signature', 'required' => false, 'clear' => true]); ?>
                    </div>
                    <div class="req-sig-imgbox" id="req-patient-img"><?php echo $p_sig_img ?></div>
                    <div style="display:flex;gap:.5rem;align-items:center;margin-top:.4rem;flex-wrap:wrap;">
                        <label class="req-btn req-btn--secondary" style="padding:.35rem .7rem;font-size:12px;cursor:pointer;">
                            Upload Image
                            <input type="file" name="patient_signature" accept="image/*" style="display:none;"
                                onchange="pcrSigPreview(this,'req-patient-img','req-patient-canvas','img_patient_signature')">
                        </label>
                        <?php if ($p_fname !== 'onfile'): ?>
                            <label class="req-chklbl" style="font-size:12px;">
                                <input type="checkbox" name="onfile" value="1" <?php checked($settings['auto_on_patient_signature_file']) ?>> On-File
                            </label>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="img_patient_signature" id="img_patient_signature"
                        value="<?php echo esc_attr($p_fname) ?>">
                </div>
            </div>

            <!-- Provider signature -->
            <div id="provider_signdiv">
                <p class="req-lbl" style="margin-bottom:.4rem;">Provider Signature</p>
                <?php
                $v_fname = !empty($bat['provider_signature']) ? $bat['provider_signature'] : '';
                // Fall back to doctor's on-file signature (mirrors box_signature.php)
                if ((empty($v_fname) || stripos($v_fname, 'doc_') === 0) && !empty($current_doctor_sign ?? '')) {
                    $v_fname = $current_doctor_sign;
                }
                $v_sig_img = '';
                if (
                    !empty($v_fname)
                    && file_exists($sign_dir['path'] . '/' . $v_fname)
                    && filesize($sign_dir['path'] . '/' . $v_fname) > 0
                ) {
                    $v_sig_img = '<img class="sign_img" src="' . esc_url($sign_dir['url'] . '/' . $v_fname . '?' . time()) . '" style="max-width:260px;">';
                }
                ?>
                <div class="req-sig-wrap" id="req-provider-sig-wrap">
                    <div class="req-sig-canvas signaturepad" id="req-provider-canvas"
                        style="<?php echo $v_sig_img ? 'display:none;' : '' ?>border:1.5px solid var(--req-border);border-radius:.375rem;background:#fff;max-width:302px;">
                        <?php echo tr_signature(['name' => 'provider_signature', 'required' => false, 'clear' => true]); ?>
                    </div>
                    <div class="req-sig-imgbox signimg" id="req-provider-img"><?php echo $v_sig_img ?></div>
                    <div style="display:flex;gap:.5rem;align-items:center;margin-top:.4rem;flex-wrap:wrap;">
                        <label class="req-btn req-btn--secondary" style="padding:.35rem .7rem;font-size:12px;cursor:pointer;">
                            Upload Image
                            <input type="file" name="provider_signature" accept="image/*" style="display:none;"
                                onchange="pcrSigPreview(this,'req-provider-img','req-provider-canvas','img_provider_signature')">
                        </label>
                    </div>
                    <input type="hidden" name="img_provider_signature" id="img_provider_signature"
                        value="<?php echo esc_attr($v_fname) ?>">
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    function pcrSigPreview(input, imgBoxId, canvasId, hiddenId) {
        var file = input.files && input.files[0];
        if (!file) return;

        var reader = new FileReader();
        reader.onload = function(e) {
            var imgBox = document.getElementById(imgBoxId);
            var canvas = document.getElementById(canvasId);
            var hid = document.getElementById(hiddenId);

            if (imgBox) {
                imgBox.innerHTML = '<img class="sign_img" src="' + e.target.result + '" style="max-width:260px;">';
            }
            if (canvas) canvas.style.display = 'none';
            // Clear the stored filename so the new file upload takes priority on save
            if (hid) hid.value = '';
        };
        reader.readAsDataURL(file);
    }
</script>