<?php
global  $blog_id, $wpdb;

include_once(TRCPDD_PATH . 'pages/formdata.php');

$mid            = isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;
$act            = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';
$groups_results = array();
$settings       = get_option('ci_req_settings_pcr', array());
$receving_readonly = tr_get_option('ci_pdf_data', 'receiving_noedit');

if (!isset($settings['disable_auto_doc'])) {
    $settings['disable_auto_doc'] = false;
}


if ($act == 'edit' && $mid > 0) {
    $bat        = $wpdb->get_row("select * from ci_batches_pcr where mid='{$mid}' ", ARRAY_A);
    $cli['cID'] = $bat['cli_ID'];
    $cli_id     = $cli['cID'];
    $pa         = $wpdb->get_row("select pa.* from ci_patients pa
                           where pa.pa_ID = '{$bat['patient_id']}' ", ARRAY_A);
    if (!$pa) {
        $pa = array();
        list($pa['pa_first'], $pa['pa_last']) = explode(',', @$bat['patient_name']);
        $pa['pa_dob'] = $bat['dob'];
        $pa['pa_first'] = trim($pa['pa_first']);
        $pa['pa_last'] = trim($pa['pa_last']);
    }


    $bat['panel_tests_requested']   = explode(',', @$bat['panel_tests_requested']);
    $bat['panel_tests_requested']   = array_map('trim', $bat['panel_tests_requested']);



    if (empty($bat['spec_received'])) {
        $bat['spec_received'] = $bat['batch_created'];
    }

    if (tr_get_option('ci_pdf_data', 'receiving_offtime') && !empty($bat['spec_received'])) {
        $bat['spec_received'] = date('Y-m-d', strtotime($bat['spec_received']));
    }
} else {
    $cli['lab_ID'] = CI_Base_Lab::get_current_lab_id();
    $bat['doc']         = '';
    $bat['spec_received'] = '';
    $bat['lab_ID']      = '';
    $bat['ref_lab_ID']  = '';
    $bat['requesting_practice']  = '';
    $bat['cli_ID']  = '';
    $bat['requesting_physician']  = '';
    $bat['type_of_specimen']  = '';
    $bat['batch_status']  = '';
    $bat['p_condition']  = '';
    $bat['batch_id']    = tr_pdd_get_current_batch_id();
    if (tr_get_option('ci_pdf_data', 'current_doc')) {
        $bat['doc']     = date_i18n('Y-m-d H:i:s');
    }
    $bat['panel_tests_requested'] = array();
    $receving_readonly = false;
}


if (isset($bat['lab_ID']) && $bat['lab_ID'] > 0) {
    $current_lab = $wpdb->get_row("select * from ci_labs Where lab_ID = {$bat['lab_ID']}", ARRAY_A);
} else {
    $current_lab = CI_Base_Lab::get_current_lab();
}

?>
<div class="wrap">
    <h2>Short Form PCR</h2>
    <?php Tr_Session_Class::show() ?>

    <form method="post" enctype="multipart/form-data" class="need_validate" id="requisition_form">

        <div class="frow blabel">
            <div class="col2">
                <div class="frow">
                    <label>Date Received</label>
                    <input type="text" class="<?php if (!$receving_readonly) echo tr_get_option('ci_pdf_data', 'receiving_offtime') ? 'hasdate' : 'hasdatetime' ?> today" name="bat[spec_received]" value="<?php echo isset($bat['spec_received']) ? $bat['spec_received'] : '' ?>" <?php if ($receving_readonly) echo 'readonly' ?> required="" />
                </div>
                <div class="frow">
                    <label>Lab</label>
                    <?php if ($blog_id == 1) {
                        if (empty($bat['lab_id'])) $bat['lab_id'] = CI_Base_Lab::get_current_lab_id();
                        if (@$bat['lab_id'] > 0) {
                            $lab = $wpdb->get_row("select * from ci_labs where lab_ID = {$bat['lab_id']}", ARRAY_A);
                        }
                    ?>
                        <input type="text" class="lab_auto" name="lab[lab_dba_init]" value="<?php echo @$lab['lab_dba_init'] ?>" required />
                        <input type="hidden" id="lab_id" name="lab[lab_ID]" value="<?php echo @$lab['lab_ID'] ?>">
                    <?php } else {
                        if (empty($bat['lab_id'])) $bat['lab_id'] = CI_Base_Lab::get_current_lab_id();
                        $lab = $wpdb->get_row("select * from ci_labs where lab_ID = {$bat['lab_id']}", ARRAY_A);
                    ?>
                        <input type="text" name="lab[lab_dba_init]" value="<?php echo $lab['lab_dba_init'] ?>" readonly required="" />
                        <input type="hidden" name="lab[lab_ID]" value="<?php echo $lab['lab_ID'] ?>">
                    <?php } ?>
                </div>
                <?php if ($blog_id == 1) {
                    if (@$bat['ref_lab_ID'] > 0) {
                        $lab_ref = $wpdb->get_row("select * from ci_labs where lab_ID = {$bat['ref_lab_ID']}", ARRAY_A);
                    }
                ?>
                    <div class="frow">
                        <label>Reference Lab</label>
                        <input type="text" class="ref_lab_auto" name="ref_lab[lab_dba_init]" value="<?php echo isset($lab_ref['lab_dba_init']) ? $lab_ref['lab_dba_init'] : '' ?>" />
                        <input type="hidden" name="ref_lab[lab_ID]" value="<?php echo isset($lab_ref['lab_ID']) ? $lab_ref['lab_ID'] : '' ?>">
                    </div>
                <?php } ?>

                <div class="frow">
                    <label>PRACTICE INFORMATION</label>
                    <input type="text" class="cfname requesting_practice_auto" name="bat[requesting_practice]" value="<?php echo isset($bat['requesting_practice']) ? $bat['requesting_practice'] : '' ?>" required />
                    <input type="hidden" name="bat[cli_ID]" value="<?php echo isset($bat['cli_ID']) ? $bat['cli_ID'] : '' ?>">
                </div>
                <div class="frow">
                    <label>ORDERING PHYSICIAN</label>
                    <input type="text" class="requesting_physician_auto cfname" name="bat[requesting_physician]" value="<?php echo isset($bat['requesting_physician']) ? $bat['requesting_physician'] : '' ?>" required />
                    <input type="hidden" name="bat[doc_ID]" data-low="<?php echo isset($cus['cus_individuals']) ? esc_attr(strtoupper($cus['cus_individuals'])) : '' ?>" data-med="<?php echo isset($cus['cus_med_risk']) ? esc_attr(strtoupper($cus['cus_med_risk'])) : '' ?>" data-high="<?php echo isset($cus['cus_high_risk']) ? esc_attr(strtoupper($cus['cus_high_risk'])) : '' ?>" value="<?php echo isset($bat['doc_ID']) ? $bat['doc_ID'] : '' ?>">
                </div>

                <div class="frow">
                    <label>Date of Collection</label>
                    <input type="text" class="hasdatetime <?php if (tr_get_option('ci_pdf_data', 'limit_doc')) echo 'limitfuture' ?>" data-write="true" name="bat[doc]" value="<?php echo empty($bat['doc']) && !$settings['disable_auto_doc'] ? 'auto' : $bat['doc'] ?>" autocomplete="off" required="" />
                </div>

            </div>


            <div class="col2">
                <?php if ($blog_id == 1) : ?>
                    <div class="frow">
                        <label>Batch Number</label>
                        <input type="text" name="bat[batch_id]" value="<?php echo isset($bat['batch_id']) ? $bat['batch_id'] : '' ?>" maxlength="11" required />
                    </div>
                <?php endif; ?>
                <div class="frow">
                    <label>Position</label>
                    <input type="number" name="bat[position]" value="<?php echo isset($bat['position']) ? $bat['position'] : '' ?>" />
                </div>
                <div class="frow">
                    <label>Accession Number</label>
                    <input type="text" name="bat[assen]" id="number_assen" value="<?php echo isset($bat['assen']) ? $bat['assen'] : '' ?>" required="" style="width:75%" />
                    <?php if (empty($bat['assen'])) { ?>
                        <label id="show_auto_gennerated" class="inline" style="margin-left:10px">
                            <input type="checkbox" name="auto_generated" value="1" checked />
                            <span> Auto Generated</span>
                        </label>
                    <?php } ?>
                </div>

                <div class="frow">
                    <label>Patient Last Name</label>
                    <input type="text" class="cfname patient_last_auto" name="pa[pa_last]" value="<?php echo isset($pa['pa_last']) ? $pa['pa_last'] : '' ?>" required="" />
                    <input type="hidden" name="pa[pa_ID]" value="<?php echo isset($pa['pa_ID']) ? $pa['pa_ID'] : '' ?>">
                </div>
                <div class="frow">
                    <label>Patient First Name</label>
                    <input type="text" class="cfname patient_first_auto" name="pa[pa_first]" value="<?php echo isset($pa['pa_first']) ? $pa['pa_first'] : '' ?>" required="" />
                </div>

                <div class="frow">
                    <label>DOB</label>
                    <input type="text" class="hasdate limitfuture" data-write="true" name="pa[pa_dob]" value="<?php echo isset($pa['pa_dob']) ? $pa['pa_dob'] : '' ?>" autocomplete="off" required />
                </div>

            </div>
        </div>

        <?php include('req_section_panels.php') ?>

        <?php include('questions.php') ?>

        <?php
        if (tr_get_option('ci_pdf_data', 'pcr_crs_shortf')) {

            $pos_neg = array(
                'NEG' => 'Negative',
                'POS' => 'Positive',
                'PEN' => 'Pending',
                'INC' => 'Inconclusive',
            );

            $report = $wpdb->get_row("select * from ci_reports_pcr where patient_id = '{$bat['assen']}'");
            $group = $wpdb->get_row("select * from ci_test_groups where (tg_is_covid =1 or tg_name like '%ncov%' ) and tg_type='pcr'")
        ?>
            <div id="rs_GROUP_<?php echo $group->tg_ID ?>" style="display: none;">
                <div class="frow">
                    <h4><?php echo $group->tg_name ?> Result</h4>
                </div>
                <table style="width:100%" class="tbl_results">
                    <tr class="group_header">
                        <th><strong>Test</strong></th>
                        <th><strong>Result</strong></th>
                    </tr>
                    <?php
                    $report_id = $report ? $report->id : 0;
                    $arr = $wpdb->get_row("select * 
					        FROM ci_test_panels tp 
                            left join ci_results_pcr rs ON  tp.te_name = rs.drug and report_id='" . $report_id . "'
					        where tp.te_group_id = {$group->tg_ID}  ", ARRAY_A);
                    $arr['drug'] = $arr['te_name'];
                    $pos_neg_vls = $pos_neg;

                    if ($arr['is_covid']) {

                        $pos_neg_vls = array(
                            '' => 'Select',
                            'POS' => $pos_neg['POS'],
                            'NEG' => $pos_neg['NEG'],
                            'RER' => 'ReRun',
                            'INC' => 'Inconclusive'
                        );
                    }
                    if ($arr['te_is_detected']) {
                        $pos_neg_vls['POS'] = 'Detected';
                        $pos_neg_vls['NEG'] = 'Not Detected';
                    }

                    $key = $arr['drug'];

                    ?>
                    <tr>
                        <td><input type="text" <?php echo 'name="results[' . $key . '][drug]"' ?> readonly value="<?php echo $arr['drug'] ?>" /> </td>
                        <td>
                            <select name="results[<?php echo $key ?>][pos-neg]">
                                <option value="">Select</option>
                                <?php
                                foreach ($pos_neg_vls as $k => $vl) {
                                    $selected = $k == $arr['pos-neg'] ? 'selected="selected"' : '';
                                    echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
                                }
                                ?>
                            </select>
                        </td>

                    </tr>

                </table>
            </div>
            <script>
                jQuery(function($) {
                    $('.cb_test[value*="GROUP_"]').on('click change', function() {
                        var $vl = $(this).val();
                        if ($(this).is(':checked')) {
                            $('#rs_' + $vl).show();
                        } else {
                            $('#rs_' + $vl).hide();
                        }
                    })
                    $('.cb_test[value*="GROUP_"]:checked').change();
                })
            </script>
            <?php
        }

        if (tr_get_option('ci_pdf_data', 'pcr_emrs_shortf') && $groups_results) {

            $report = $wpdb->get_row("select * from ci_reports_pcr where patient_id = '{$bat['assen']}'", ARRAY_A);

            $pos_neg = array(
                ''    => 'Select',
                'NEG' => 'Negative',
                'POS' => 'Positive',
                'PEN' => 'Pending',
                'INV' => 'Invalid',
            );

            foreach ($groups_results as $group) {
                $hide_value = false;
                if (
                    stripos($group['label'], 'antigen') !== false
                    || stripos($group['label'], 'SARS-COV-2 PCR TEST EXPEDITED') !== false
                ) {
                    $hide_value = true;
                }
            ?>
                <div id="rs_GROUP_<?php echo $group['id'] ?>" class="group_results <?php if ($group['edit_results_test']) echo ' rs_by_test ';
                                                                                    if ($group['notestcheckbox']) echo ' notestcheckbox ' ?>" style="display: none" data-auto_batchid="<?php echo $group['auto_batchid'] ?>" data-auto_position="<?php echo $group['auto_position'] ?>">
                    <div class="frow">
                        <h4>Group <?php echo $group['label'] ?></h4>
                    </div>
                    <div class="frow specimen_row vdx_row csgr_row">
                        <div class="col5">
                            <strong>Test</strong>
                        </div>
                        <?php if (!$hide_value) { ?>
                            <div class="col5">
                                <strong>Value</strong>
                            </div>
                        <?php } ?>
                        <div class="col5">
                            <strong>Test Outcome</strong>
                        </div>
                        <div class="col5">
                            <strong>Reference Range</strong>
                        </div>
                        <div class="col5"><strong>Comments</strong></div>
                    </div>
                    <?php
                    foreach ($group['tests'] as $test) {
                        if (empty($test->te_ID)) continue;
                        $drug = strtoupper($test->te_name);
                        $arr = array();
                        if ($report['id'] > 0) {
                            $arr = $wpdb->get_row("select * 
                                from ci_results_pcr rs
                                join ci_test_panels te ON te.te_name = rs.drug and te_type='pcr'
                                where rs.report_id = {$report['id']} 
                                    and rs.group_id = {$test->tg_ID}
                                    and te_ID = {$test->te_ID} ", ARRAY_A);

                            if (
                                !$arr
                                && (in_array($test->te_ID, $bat['panel_tests_requested'])
                                    || in_array('GROUP_' . $test->tg_ID, $bat['panel_tests_requested']))
                            ) {
                                $arr = $wpdb->get_row("select * 
                                from ci_results_pcr rs
                                join ci_test_panels te ON te.te_name = rs.drug and te_type='pcr'
                                where rs.report_id = {$report['id']} 
                                    and (rs.group_id is null or rs.group_id = 0)
                                    and te_ID = {$test->te_ID} ", ARRAY_A);
                            }
                        }


                        if (empty($arr)) {
                            $arr = array(
                                'drug' => $drug,
                                'te_ID' => $test->te_ID,
                            );
                        }

                        $rskey = $test->te_ID . '_' . $drug . '_' . $test->tg_ID . '_' . $arr['id'];

                        //search instrument
                        if (!empty($arr['conc']) && $report && empty($report['instrument'])) {

                            $report['instrument'] = $wpdb->get_var(
                                "select cu_ms from ci_test_panels_cutoffs 
	                                where cu_te_ID = {$test->te_ID} and cu_ms is not null and cu_ms!=''"
                            );

                            if (!empty($report['instrument'])) {
                                $wpdb->update('ci_reports_pcr', array('instrument' => $report['instrument']), array('id' => $report['id']));
                            }
                        }

                        $arr['type_of_specimen'] = $report['type_of_specimen'];
                        $arr = tr_pdd_parse_pcr_test($arr, $report['instrument'], $test->tg_ID);
                        $outcomes = $pos_neg;
                        if ($test->te_is_detected) {
                            $outcomes['POS'] = 'Detected';
                            $outcomes['NEG'] = 'Not Detected';
                        }

                        if (stripos($test->te_name, 'MonkeyPox') !== false) {
                            $outcomes['EQU'] = 'Equivocal';
                            $outcomes['INC'] = 'Inconclusive';
                        }
                    ?>
                        <div class="frow rs_test rs_TEST_<?php echo $test->te_ID ?> rs_GROUP_<?php echo $test->tg_ID ?>">
                            <div class="col5">
                                <?php echo $test->te_name ?>
                            </div>
                            <?php if (!$hide_value) { ?>
                                <div class="col5">
                                    <input type="text" name="results[<?php echo $rskey ?>][conc]" value="<?php echo $arr['conc'] ?>" />
                                </div>
                            <?php } ?>
                            <div class="col5">
                                <select name="results[<?php echo $rskey ?>][pos-neg]">
                                    <?php
                                    foreach ($outcomes as $k => $vl) {
                                        $selected = $k == $arr['pos-neg'] ? 'selected' : '';
                                        echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col5">
                                <?php echo $arr['range'] ?>&nbsp;
                            </div>
                            <div class="col5">
                                <textarea class="specimen_comments" name="results[<?php echo $rskey ?>][comments]" style="height:50px;"><?php echo $arr['comments'] ?></textarea>
                            </div>
                        </div>
                    <?php
                    }
                    ?>
                </div>
        <?php
            }
        }
        ?>


        <div class="frow">
            <h4>NOTES</h4>
        </div>
        <div class="frow">
            <textarea name="bat[notes]" <?php if ($act != 'print') echo 'placeholder="Please enter any ' . (tr_get_option('ci_pdf_data', 'cht_physicalnotes') ? 'Chief Complaint' : 'Physician Notes') . '"' ?>><?php if (isset($bat['notes'])) echo @$bat['notes'] ?></textarea>
        </div>


        <div class="frow">
            <h4>BILLING NOTES</h4>
        </div>
        <div class="frow">
            <textarea name="bat[billing_notes]" maxlength="200" <?php if ($act != 'print') echo 'placeholder="Please enter any Billing Notes"' ?>><?php if (isset($bat['billing_notes'])) echo @$bat['billing_notes'] ?></textarea>
        </div>

        <div class="frow">
            <h4>NOTES (visible on reports)</h4>
        </div>
        <div class="frow">
            <textarea name="bat[notes_report]"><?php if (isset($bat['notes_report'])) echo @$bat['notes_report'] ?></textarea>
        </div>

        <div class="frow">
            <div class="col2">
                <label>Status</label>
                <?php
                $status_options = tr_pdd_get_batch_statuses_on_hold(tr_pdd_get_batch_statuses(false, false, false, 'pcr'), $bat['batch_status'], $bat['mid'], $table_batch, 'pcr');
                $disabled_keys = tr_pdd_get_batch_statuses_disabled_keys('pcr', 'show_in_req_form', $bat['batch_status']);
                tr_select_option('bat[batch_status]', $status_options, @$bat['batch_status'], ['disabled_options' => $disabled_keys]);
                ?>
            </div>
            <?php if (tr_get_option('ci_pdf_data', 'pcr_reasont')) { ?>
                <div class="col2 " id="p_condition">
                    <label>Reason for Testing</label>
                    <?php
                    tr_select_option('bat[p_condition]', array(
                        '' => 'Select',
                        'Weekly Testing' => 'Weekly Testing',
                        'Symptoms/Exposure' => 'Symptoms/Exposure',
                        'Positive Retest' => 'Positive Retest',
                        'Other' => 'Other'
                    ), @$bat['p_condition']) ?>
                </div>
            <?php } ?>
        </div>

        <?php
        if ($act != 'print') {
        ?>
            <div class="frow">
                <h4>Upload File</h4>
            </div>
            <div class="frow">
                <div class="col3">
                    <div class="req_result_pdf">
                        <a class="button addfile">Choose Result PDF</a>

                        <input type="hidden" class="result_value" name="bat[result_pdf]" value="<?php echo isset($bat['result_pdf']) ? $bat['result_pdf'] : '' ?>" />
                        <ul class="listresultfiles">
                            <?php
                            if (!empty($bat['result_pdf'])) {
                                $rs_files = explode(',', $bat['result_pdf']);
                                foreach ($rs_files as $f) {

                                    $result_dir = tr_get_result_upload_dir();
                                    $result_pdf = $result_dir['url'] . '/' . $f;
                            ?>
                                    <li class="result_pdf_view" data-f="<?php echo $f ?>">
                                        <a href="<?php echo $result_pdf ?>" target="_blank"><?php echo $f ?></a> -
                                        <a class=" remove_result_pdf" href="#">Remove</a>
                                    </li>
                            <?php
                                }
                            }
                            ?>
                        </ul>
                    </div>
                </div>
                <div class="col3">
                    <div class="card_file">
                        <a class="button selectfile" href="#">Upload Issue Photo</a>
                        <span class="view">
                            <?php
                            $dir = tr_get_issue_photo_dir();
                            $fname = isset($bat['issue_photo']) ? $bat['issue_photo'] : '';
                            if (!empty($fname) && file_exists($dir['path'] . '/' . $fname) && filesize($dir['path'] . '/' . $fname) > 0) {
                                echo '<a href="' . $dir['url'] . '/' . $fname . '" target="_blank">View File</a>';
                            }
                            ?>
                        </span>
                        <input type="file" name="issue_photo" accept="image/*" />
                    </div>
                </div>
            </div>
        <?php
        }
        ?>
        <br><br>
        <div class="frow">
            <h4>&nbsp;</h4>
        </div>
        <div class="frow">
            <input type="submit" class="button-primary" value="Submit">
            <input type="hidden" name="tr_action" value="ci_save_form_pcr" />

            <?php if (@$mid > 0) { ?>
                <input type="hidden" name="bat[mid]" value="<?php echo $mid ?>" />
            <?php } else {
            ?>
                <input type="hidden" name="bat[batch_short]" value="1" />
                <input type="hidden" name="bat[batch_type]" value="M" />
            <?php
            } ?>
        </div>
        <?php trpdd_form_unique_id() ?>

    </form>
</div>
<div id="popup_selecter" style="display: none;"></div>
<script>
    var $t_assen = false;
    var $changebychild = false;
    jQuery(function($) {
        $('body').on('change keyup', '#number_assen', function() {
                var $this = $(this);
                var $vl = $(this).val().trim();
                var $mid = $('[name="bat[mid]"]').val();
                $(this).val($vl);
                if ($vl.length < 4) return;
                $('[type="submit"]').attr('disabled', 'disabled');
                if ($t_assen) {
                    clearTimeout($t_assen);
                }
                $t_assen = setTimeout(function() {
                    $.ajax({
                        data: {
                            tr_action: 'ci_check_number_assen',
                            assen: $vl,
                            mid: $mid,
                            btype: 'pcr'
                        },
                        type: 'post',
                        dataType: 'json',
                        success: function(rs) {
                            $t_assen = false;
                            if (rs.status == 'dublicate') {
                                $this.val('');
                                alert(rs.msg);
                            }
                            $('[type="submit"]').removeAttr('disabled');
                        }
                    })
                }, 500);
            })
            .on('change', '[name="bat[doc_ID]"]', function() {
                $('#batcustom_profile').change();
            })
            .on('change', '.group_results select', function() {
                if ($(this).val() != '') {
                    var $gr = $(this).parents('.group_results');
                    if ($gr.data('auto_batchid') == '1') {
                        if (($('[name="bat[batch_id]"]').val() == '' || $('[name="bat[batch_id]"]').val() == '0')) {
                            $('[name="bat[batch_id]"]').val('auto');
                        }
                    }
                    if ($gr.data('auto_position') == '1') {
                        if ($('[name="bat[position]"]').val() == '') {
                            $('[name="bat[position]"]').val('-1');
                        }
                    }
                }
            })
            .on('change', '[name="bat[assen]"]', function() {
                if ($(this).val() != '') {
                    $('#show_auto_gennerated').hide();
                } else {
                    $('#show_auto_gennerated').show();
                }
            })
            .on('change', '[name="bat[cli_ID]"]', function() {
                var $cli_id = $(this).val();
                $('.groupparent.by_clinic').removeClass('cactive');
                $('.groupparent.by_clinic.cli_id_' + $cli_id).addClass('cactive');

                $('.groupparent.not_by_clinic').removeClass('notcactive');
                $('.groupparent.not_by_clinic.not_cli_id_' + $cli_id).addClass('notcactive');
            })
            .on('change', '.batcustom_profile', function() {
                var $pr = $(this).parents('.groupparent');
                if ($(this).is(":checked")) {
                    if (!$changebychild) {
                        $('.c input', $pr).prop('checked', true);
                    }
                    $('.specimens', $pr).addClass('grequired');
                } else {
                    if (!$changebychild) {
                        $('.c input', $pr).removeAttr('checked');
                    }
                    $('.specimens', $pr).removeClass('grequired');
                    $('.specimens', $pr).removeClass('gerror');
                }
                var $rl_ids = $(this).data('relate_to');
                var $rlids = ($rl_ids === undefined || $rl_ids === null || $rl_ids === '') ? [] : String($rl_ids).split(',').map(function($id) {
                    return $.trim(String($id));
                }).filter(function($id) {
                    return $id !== '';
                });
                var $pl = $('.groupparent.parent_' + $(this).val().toLowerCase() + ' .group_label input');

                if ($(this).is(':checked')) {
                    if ($pl.length && !$pl.is(':checked')) {
                        $pl.prop('checked', true).change();
                    }
                    if ($rlids.length > 0) {
                        for (var i = 0; i < $rlids.length; i++) {
                            var $rl = $('.groupparent .group_label input[value="GROUP_' + $rlids[i] + '"]');
                            if (!$rl.length || $rl.is(':checked')) {
                                continue;
                            }
                            $rl.data('byrelated', true);
                            $rl.prop('checked', true).change();
                        }
                    }

                } else {
                    if ($pl.length && $pl.is(':checked')) {
                        $pl.prop('checked', false).change();
                    }
                    if ($rlids.length > 0) {
                        for (var j = 0; j < $rlids.length; j++) {
                            var $rl_uncheck = $('.groupparent .group_label input[value="GROUP_' + $rlids[j] + '"]');
                            if (!$rl_uncheck.length || !$rl_uncheck.is(':checked')) {
                                continue;
                            }
                            $rl_uncheck.prop('checked', false).change();
                        }
                    }
                }
                $changebychild = false;
            })
            .on('change', '.group_label input[type="checkbox"]', function() {
                var $pr = $('#rs_' + $(this).val());
                if ($(this).is(':checked')) {
                    $pr.show();
                } else {
                    $pr.hide();
                }
            })
            .on('click', '[name="auto_generated"]', function() {
                if ($(this).is(':checked')) {
                    $('#number_assen,#confirm_assen').removeAttr('required').val('').attr('readonly', '1');
                } else {
                    $('#number_assen,#confirm_assen').attr('required', 'required').removeAttr('readonly');
                }
            })
            .on('click change', '.groupparent .c input.cb_test', function() {
                var $pr = $(this).parents('.groupparent');
                var $prcb = $('.group_label input', $pr);
                if ($(this).is(':checked')) {
                    $changebychild = true;
                    $prcb.prop('checked', true).change();
                } else {
                    if ($('.c input.cb_test:checked', $pr).length == 0) {
                        $prcb.prop('checked', false).change();
                    }
                }
            })
            .on('change', '.orderable_ua', function() {
                if ($(this).is(':checked')) {
                    var $vl = $(this).val();
                    $('.orderable_ua').each(function() {
                        if ($(this).val() != $vl) {
                            $(this).prop('checked', false);
                        }
                    })
                    if ($vl == '-1') {
                        $('input.guti').prop('checked', true).change();
                        $('input.gua').prop('checked', true).change();
                        $('.boxguti .specimens input[name="bat[type_of_specimen][]"]').prop('checked', true);
                        $('input.gutiabx').prop('checked', true).change();
                    } else if ($vl == '-2') {
                        $('input.guti').prop('checked', true).change();
                        $('input.gua').prop('checked', true).change();
                        $('.boxguti .specimens input[name="bat[type_of_specimen][]"]').prop('checked', true);
                        $('input.gutiabx').prop('checked', false).change();
                    } else if ($vl == '-3') {
                        $('input.guti').prop('checked', false).change();
                        $('input.gua').prop('checked', true).change();
                        $('input.gutiabx').prop('checked', false).change();
                    }
                }
            })

        $('.group_label input[type="checkbox"]').each(function() {
            var $pr = $('#rs_' + $(this).val());
            if ($(this).is(':checked')) {
                $pr.show();
            } else {
                $pr.hide();
            }
        })

        $('.batcustom_profile').each(function() {
            var $pr = $(this).parents('.groupparent');
            if ($(this).is(":checked")) {
                $('.specimens', $pr).addClass('grequired');
            } else {
                $('.specimens', $pr).removeClass('grequired');
            }
        })

        $('[name="bat[assen]"]').change();
        if ($('[name="auto_generated"]').is(":checked")) {
            $('#number_assen,#confirm_assen').removeAttr('required').val('').attr('readonly', '1');

        }


    })
</script>