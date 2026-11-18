<?php
global $blog_id, $wpdb;

include_once(TRCPDD_PATH . 'pages/formdata.php');


$current_medications    = tr_pdd_get_current_medications();
$groups_results         = array();

$mid                    = isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;


if ($act == 'edit' && $mid > 0) {
    $bat        = $wpdb->get_row("select * from ci_batches where mid='{$mid}' ", ARRAY_A);
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
    $ins1 = $wpdb->get_row("select * from ci_insurances where ins_ID = '{$bat['insurance1']}' ", ARRAY_A);

    $bat['current_medication'] = explode(',', @$bat['current_medication']);
    $bat['current_medication_other'] = explode(',', @$bat['current_medication_other']);
    if (count($bat['current_medication_other']) == 1 && trim($bat['current_medication_other'][0]) == '') {
        $bat['current_medication_other'] = array();
    }
    foreach ($bat['current_medication'] as $m) {
        if ($m == 'Fioricet' || $m == 'Lortab') continue;
        if (!in_array($m, $current_medications) && !empty($m) && $m != 'None Listed') {
            $bat['current_medication_other'][] = $m;
        }
    }


    $bat['panel_tests_requested_str'] = $bat['panel_tests_requested'];
    $bat['panel_tests_requested'] = explode(',', @$bat['panel_tests_requested']);
    $bat['medication'] = maybe_unserialize(@$bat['medication']);

    $bat['current_medication'] = array_map('trim', $bat['current_medication']);
    $bat['panel_tests_requested'] = array_map('trim', $bat['panel_tests_requested']);
    $bat['current_medication_other'] = array_map('trim', $bat['current_medication_other']);

    if (empty($bat['custom_profile'])) {
        $bat['custom_profile'] = 'Do Not Use Custom Profile';
    }

    if ($bat['custom_profile'] == 'Highrisk') {
        $bat['custom_profile'] = 'Custom High Risk';
    } else if ($bat['custom_profile'] == 'Standard') {
        $bat['custom_profile'] = 'Custom Mid Risk';
    } else if ($bat['custom_profile'] == 'Basic') {
        $bat['custom_profile'] = 'Custom Low Risk';
    }

    $cus = $wpdb->get_row("select cp.cus_individuals,
                                    cp.cus_med_risk,
                                    cp.cus_high_risk 
                        from ci_custom_providers cp 
                        where cp.doc_ID = '{$bat['doc_ID']}' 
                        and cp.cli_ID = {$bat['cli_ID']}  
                        and cp.cus_type='tox' ", ARRAY_A);
} else {
    $bat['type_of_specimen'] = 'Urine';
    $cli['lab_ID']      = CI_Base_Lab::get_current_lab_id();
    $bat['batch_id']    = tr_pdd_get_current_batch_id();
    $bat['current_medication'] = array();
    $bat['panel_tests_requested'] = array();
    if (tr_get_option('ci_pdf_data', 'current_doc')) {
        $bat['doc'] = date_i18n('Y-m-d H:i:s');
    }
}

?>
<div class="wrap">
    <h2>Short Form Tox</h2>
    <?php Tr_Session_Class::show() ?>

    <form method="post" enctype="multipart/form-data" class="need_validate" id="requisition_form">

        <div class="frow blabel">
            <div class="col2">
                <div class="frow">
                    <label>Date Received</label>
                    <input type="text" class="hasdatetime today" name="bat[spec_received]" value="<?php echo @$bat['spec_received'] ?>" required="" />
                </div>
                <div class="frow">
                    <label>Lab</label>
                    <?php if ($bat['lab_id'] == 0 || $blog_id == 1) {
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
                <?php if ($bat['lab_id'] == 0 || $blog_id == 1) :
                    if (@$bat['ref_lab_ID'] > 0) {
                        $lab_ref = $wpdb->get_row("select * from ci_labs where lab_ID = {$bat['ref_lab_ID']}", ARRAY_A);
                    }
                ?>
                    <div class="frow">
                        <label>Reference Lab</label>
                        <input type="text" class="ref_lab_auto" name="ref_lab[lab_dba_init]" value="<?php echo @$lab_ref['lab_dba_init'] ?>" />
                        <input type="hidden" name="ref_lab[lab_ID]" value="<?php echo @$lab_ref['lab_ID'] ?>">
                    </div>
                <?php endif; ?>

                <div class="frow">
                    <label>PRACTICE INFORMATION</label>
                    <input type="text" class="cfname requesting_practice_auto" name="bat[requesting_practice]" value="<?php echo trim(@$bat['requesting_practice']) ?>" required />
                    <input type="hidden" name="bat[cli_ID]" value="<?php echo @$bat['cli_ID'] ?>">
                </div>
                <div class="frow">
                    <label>ORDERING PHYSICIAN</label>
                    <input type="text" class="requesting_physician_auto cfname" name="bat[requesting_physician]" value="<?php echo trim(@$bat['requesting_physician']) ?>" />
                    <input type="hidden" name="bat[doc_ID]" data-low="<?php echo esc_attr(strtoupper($cus['cus_individuals'])) ?>" data-med="<?php echo esc_attr(strtoupper($cus['cus_med_risk'])) ?>" data-high="<?php echo esc_attr(strtoupper($cus['cus_high_risk'])) ?>" value="<?php echo @$bat['doc_ID'] ?>">
                </div>

                <div class="frow">
                    <label>Date of Collection</label>
                    <input type="text" class="hasdatetime <?php if (tr_get_option('ci_pdf_data', 'limit_doc')) echo 'limitfuture' ?>" data-write="true" name="bat[doc]" value="<?php echo empty($bat['doc']) ? 'auto' : $bat['doc'] ?>" autocomplete="off" required="" />
                </div>

                <div class="frow">
                    <label>Type of Specimen:</label>
                    <div class="ilabel label2"><?php
                                                $types = tr_pdd_get_type_of_specimens();
                                                if (!empty($bat['type_of_specimen']) && !isset($types[$bat['type_of_specimen']])) {
                                                    $types[$bat['type_of_specimen']] = $bat['type_of_specimen'];
                                                }
                                                tr_radio('bat[type_of_specimen]', $types, @$bat['type_of_specimen'], array('attr' => 'required')) ?></div>
                </div>
            </div>


            <div class="col2">
                <?php if ($blog_id == 1) : ?>
                    <div class="frow">
                        <label>Batch Number</label>
                        <input type="text" name="bat[batch_id]" value="<?php echo @$bat['batch_id'] ?>" required />
                    </div>
                <?php endif; ?>
                <div class="frow">
                    <label>Position</label>
                    <input type="number" name="bat[position]" value="<?php echo @$bat['position'] ?>" required />
                </div>
                <div class="frow">
                    <label>Accession Number</label>
                    <input type="text" name="bat[assen]" id="number_assen" value="<?php echo @$bat['assen'] ?>" required="" style="width:75%" />
                    <?php if (empty($bat['assen'])) { ?>
                        <label id="show_auto_gennerated" class="inline" style="margin-left:10px">
                            <input type="checkbox" name="auto_generated" value="1" checked />
                            <span> Auto Generated</span>
                        </label>
                    <?php } ?>
                </div>
                <div class="frow">
                    <label>PID#</label>
                    <input type="text" name="bat[oldreq]" value="<?php echo @$bat['oldreq'] ?>" />
                </div>
                <div class="frow">
                    <label>Patient Last Name</label>
                    <input type="text" class="cfname patient_last_auto" name="pa[pa_last]" value="<?php echo @$pa['pa_last'] ?>" required="" />
                    <input type="hidden" name="pa[pa_ID]" value="<?php echo @$pa['pa_ID'] ?>">
                </div>
                <div class="frow">
                    <label>Patient First Name</label>
                    <input type="text" class="cfname patient_first_auto" name="pa[pa_first]" value="<?php echo @$pa['pa_first'] ?>" required="" />
                </div>

                <div class="frow">
                    <label>DOB</label>
                    <input type="text" class="hasdate limitfuture" data-write="true" name="pa[pa_dob]" value="<?php echo @$pa['pa_dob'] ?>" autocomplete="off" required />
                </div>

            </div>
        </div>

        <div class="frow ">
            <div class="col2 blabel printw100">
                <?php
                if (count($current_medications) > 0) {
                ?>
                    <div class="medication_area frow insidebreak">
                        <h4>PATIENT CURRENT MEDICATIONS</h4>
                        <span class="small">I order the lab to test for/confirm the prescribed medications listed below</span>
                        <input type="hidden" name="has_edit_medication" value="1" />
                        <div class="frow blabel">
                            <?php
                            $fourn = round(count($current_medications) / 4) + 1;
                            foreach ($current_medications as $i => $m) :
                                if ($i % $fourn == 0) {
                                    if ($i > 1) echo '</div>';
                                    echo '<div class="col4">';
                                }
                                $k = $m;
                                if ($k == 'Fioricet/Fiorinal') $k = 'Fioricet';
                                else if ($k == 'Lorcet/Lortab') $k = 'Lortab';
                            ?>
                                <label>
                                    <input type="checkbox" name="bat[current_medication][]" value="<?php echo $k ?>" <?php checked(in_array($k, @$bat['current_medication'])) ?> />
                                    <?php echo $m ?>
                                </label>
                            <?php endforeach;
                            echo '</div>' ?>
                        </div>

                        <div class="frow" style="padding-top:10px">
                            <input type="hidden" name="current_medication_none" value="" />
                            <input type="checkbox" name="current_medication_none" value="1" <?php checked(in_array('None Listed', @$bat['current_medication'])) ?> /> No Prescribed Medication
                        </div>

                        <div class="frow" style="padding-top:20px" id="more_current_medication_div">
                            <?php
                            if (!is_array(@$bat['current_medication_other'])) $bat['current_medication_other'] = array();
                            $count = count($bat['current_medication_other']) >= 3 ? count($bat['current_medication_other']) : 3;
                            for ($i = 0; $i < $count; $i++) : ?>
                                <div class="col3 <?php if ($i == 0) echo 'default' ?>">
                                    <label>Other:</label>
                                    <input type="text" name="bat[current_medication_other][]" value="<?php echo @$bat['current_medication_other'][$i] ?>" />
                                </div>
                            <?php endfor; ?>
                        </div>
                        <?php if ($act != 'print') { ?>
                            <a class="add_more_current_medication button">+</a>
                        <?php } ?>
                        <div class="frow">
                            Not all drugs LISTED under "other" will be available.
                        </div>
                    </div>
                <?php
                }
                ?>

            </div>
            <div class="col2">
                <div class="frow">
                    <h4>TESTS ORDERED/CUSTOM PROFILE OPTIONS</h4>
                </div>
                <div class="frow">
                    <?php tr_select_option('bat[custom_profile]', tr_pdd_get_custom_profiles(), @$bat['custom_profile'], array('attr' => 'required')) ?>
                    <!--<div class="small">Specimen Validity Testing performed on all submitted samples</div>!-->
                </div>
                <div class="frow">
                    <h4>TESTING REQUESTED</h4>
                </div>

                <?php
                $screenings = tr_pdd_get_result_validity(1, $bat['batch_created'], $mid, $saved_id > 0);

                if (count($screenings) > 0) {
                    $use_screening = false;
                    foreach ($screenings as $arr) {
                        if ($arr['checked']) {
                            $use_screening = true;
                        }
                    }
                ?>
                    <div class="frow">
                        <label class="bold inline"><input name="screening" value="1" type="radio" required <?php checked($use_screening) ?> /> Drug Screening </label> &nbsp;&nbsp;&nbsp;&nbsp;
                        <label class="bold inline"><input name="screening" value="0" type="radio" required <?php checked(!$use_screening && $mid > 0) ?> /> No Screening </label>

                        <span style="padding-left: 20px;">
                            <input type="hidden" name="bat[only_screening]" value="0" />
                            <label class="bold"><input name="bat[only_screening]" value="1" type="checkbox" <?php checked($bat['only_screening']) ?> /> Only Screening </label>
                        </span>

                        <div class="frow" id="screening_div" style="margin-top:10px">
                            <?php
                            foreach ($screenings as $arr) {
                                $key = $arr['u_ID'];
                                $arr['conc'] = preg_replace("/[^0-9-.,]/", '', $arr['conc']);

                                if (isset($arr['readonly']) && $arr['readonly'] && !empty($arr['outcome'])) {
                                    //dont change pos/neg
                                } else if ($arr['outcome'] != 'CAN') {
                                    if (!empty($arr['conc']) && ($arr['conc'] < $arr['u_low'] || ($arr['conc'] > $arr['u_high'] && $arr['u_high'] > 0))) {
                                        $arr['outcome'] = $arr['u_neg_pos'] ? 'POS' : 'ABNORMAL';
                                    } else {
                                        $arr['outcome'] = $arr['u_neg_pos'] ? 'NEG' : 'NORMAL';
                                    }
                                }
                            ?>
                                <input type="hidden" name="data_specimen_screening[<?php echo $key ?>][checked]" value="0" />

                                <div class="col3">
                                    <label><input type="checkbox" class="screening_cb" name="data_specimen_screening[<?php echo $key ?>][checked]" value="1" <?php checked($arr['checked']) ?> data-id="<?php echo $key ?>" data-u_reflex="<?php echo $arr['u_reflex'] ?>" data-outcome="<?php echo $arr['outcome'] == 'ABNORMAL' ? 'POS' : $arr['outcome'] ?>" data-u_reflex_tests="<?php echo $arr['u_reflex_tests'] ?>" data-u_reflex_clinics="<?php echo $arr['u_reflex_clinics'] ?>" data-u_reflex_pos_not_medication="<?php echo isset($arr['u_reflex_pos_not_medication']) ? $arr['u_reflex_pos_not_medication'] : '' ?>" data-related_medications="<?php echo esc_attr(trim((isset($arr['u_related_medications']) ? $arr['u_related_medications'] : '') . ',' . $arr['u_name'] . ',' . $arr['u_abreviation'], ',')) ?>" />
                                        <?php echo htmlspecialchars($arr['u_name']) ?></label>
                                </div>
                            <?php
                            }
                            ?>
                        </div>

                    </div>
                <?php
                }

                $lcms_tests = tr_pdd_get_profiles_tests_requesteds('tox', true, false, $bat['doc']);
                if (count($lcms_tests) > 0) {
                ?>
                    <div class="frow ">
                        <label class="bold">LCMS Confirmation</label>
                        <div id="default_requests" class="frow blabel panel_requests masonry" style="font-size:12px;column-count:<?php echo $settings['panel_column'] >= 2 ? $settings['panel_column'] : 2 ?>">
                            <input type="hidden" name="has_panel_requests" value="1" />
                            <?php
                            $tl = 0;

                            foreach ($lcms_tests as $group_id => $vl) {
                                $group = tr_pdd_get_panel_groups($group_id);

                                if ($group['edit_results']) {
                                    $group['tests'] = $vl;
                                    $groups_results[] = $group;
                                }

                                $group_class = '';
                                if (empty($group['bgcolor'])) {
                                    $group['bgcolor'] = '#dddddd';
                                }
                                $group['color'] = trpdd_getContrastColor($group['bgcolor']);
                                $group['spec_bgcolor'] = strlen($group['bgcolor']) == 7 ? $group['bgcolor'] . '88' : $group['bgcolor'];
                                $group['box_bgcolor'] = strlen($group['bgcolor']) == 7 ? $group['bgcolor'] . '66' : $group['bgcolor'];
                                if ($act == 'print') {
                                    $group['box_bgcolor'] = '#f8f8f8';
                                    $group['spec_bgcolor'] = '#eeeeee';
                                }
                            ?>
                                <div class="groupparent item <?php echo $group_class ?> " data-id="<?php echo $group['id'] ?>" style="background:<?php echo $group['box_bgcolor'] ?>">
                                    <label class="block bold group_label" style="background:<?php echo $group['bgcolor'] ?>;color: <?php echo $group['color'] ?>">
                                        <?php echo $group['label'] ?>
                                    </label>
                                    <div class="frow box_tests">
                                        <?php
                                        foreach ($vl as $k => $r) {
                                            $txt = $r->te_name;
                                            $key = strtoupper($r->key);
                                            $checked = @in_array($key, $bat['panel_tests_requested']);
                                            if (strpos($key, ',') > 0 && !$checked) {
                                                $keys = explode(',', $key);
                                                foreach ($keys as $kc) {
                                                    $checked = @in_array(strtoupper($kc), $bat['panel_tests_requested']);
                                                    if ($checked) {
                                                        break;
                                                    }
                                                }
                                            }
                                        ?>

                                            <label class="c lcb">
                                                <input type="checkbox" class="<?php echo $r->class ?>" name="bat[panel_tests_requested][]" data-id="<?php echo $r->te_ID ?>" value="<?php echo $key ?>" <?php checked($checked) ?> />
                                                <?php echo $txt ?></label>

                                        <?php
                                        }

                                        ?>
                                    </div>
                                </div>
                            <?php
                            }
                            ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>

        <?php
        $validities     = tr_pdd_get_result_validity(0, $bat['batch_created'], $mid);
        $urine_settings = get_option('ci_urine_validity_settings', array());
        if (count($validities) > 0) {
        ?>
            <div class="specimen_test">
                <div class="frow">
                    <h4>SPECIMEN VALIDITY TEST</h4>
                </div>
                <div class="frow">
                    <div class="col5">
                        <strong>Test</strong>
                    </div>
                    <?php if (!$urine_settings['paper_strip']) : ?>
                        <div class="col5">
                            <strong>Value</strong>
                        </div>
                    <?php endif; ?>
                    <div class="col5">
                        <strong>Test Outcome</strong>
                    </div>
                    <div class="col5">
                        <strong>Reference Range</strong>
                    </div>
                    <div class="col5">
                        <strong>Comments</strong>
                    </div>
                </div>
                <div class="frow">

                    <?php
                    foreach ($validities as $arr) {
                        $key = $arr['u_ID'];

                        if (isset($arr['readonly']) && $arr['readonly'] && !empty($arr['outcome'])) {
                            //dont change pos/neg
                        } else if ($arr['outcome'] != 'CAN') {
                            if (!empty($arr['conc']) && ($arr['conc'] < $arr['u_low'] || ($arr['conc'] > $arr['u_high'] && $arr['u_high'] > 0))) {
                                $arr['outcome'] = 'POS';
                            } else if (!empty($arr['conc']) || empty($arr['outcome'])) {
                                $arr['outcome'] = 'NEG';
                            }
                            if (strtolower($arr['comments']) == 'description') {
                                $arr['comments'] = '';
                            }
                        }
                        if (($arr['outcome'] == 'POS' || $arr['outcome'] == 'ABNORMAL')) {
                            $k = $arr['conc'] < $arr['u_low'] ? 'low' : 'high';
                            $cm = $arr['u_comments'];
                            if (stripos($cm, '||') > 0) {
                                $cms = explode('||', $cm);
                                $cm = '';
                                if (stripos($arr['comments'], ($k == 'low' ? 'high' : 'low')) === 0) {
                                    $arr['comments'] = '';
                                }
                                if (empty($arr['comments'])) {
                                    $has_cm = false;
                                    foreach ($cms as $c) {
                                        $c = trim($c);
                                        if (stripos($c, $k) === 0) {
                                            $cm = $c;
                                            $has_cm = true;
                                            break;
                                        }
                                    }
                                    if (!$has_cm) {
                                        $cm = $arr['conc'] < $arr['u_low'] ? $cms[0] : $cms[1];
                                    } else {
                                        $cm = $arr['conc'] < $arr['u_low'] ? $cms[0] : $cms[1];
                                    }
                                }
                                $arr['comments'] = $cm;
                            } else if (empty($arr['comments'])) {
                                $arr['comments'] = $arr['u_comments'];
                            }
                        }
                    ?>
                        <div class="frow specimen_row" data-min="<?php echo $arr['u_low'] ?>" data-pos="<?php echo $arr['u_neg_pos'] ?>" data-max="<?php echo $arr['u_high'] ?>" data-comments="<?php echo $arr['u_comments'] ?>">
                            <div class="col5">
                                <?php echo htmlspecialchars($arr['u_name']) ?>
                            </div>
                            <?php if (!$urine_settings['paper_strip']) : ?>
                                <div class="col5">
                                    <input type="text" class="speciment_value" name="data_specimen[<?php echo $key ?>][conc]" value="<?php echo $arr['conc'] ?>" />
                                </div>
                            <?php endif ?>
                            <div class="col5">
                                <select name="data_specimen[<?php echo $key ?>][outcome]" class="specimen_outcome njs_select2">
                                    <?php
                                    $outcomes = array(
                                        '' => 'Select',
                                        'NEG' => 'NORMAL',
                                        'POS' => 'ABNORMAL',
                                        'POL' => 'ABNORMAL-LOW',
                                        'POH' => 'ABNORMAL-HIGH',
                                        'CAN' => 'Canceled',
                                    );
                                    if ($arr['u_neg_pos']) {
                                        $outcomes = array(
                                            '' => 'Select',
                                            'NEG' => 'NEG',
                                            'POS' => 'POS',
                                            'CAN' => 'Canceled',
                                        );
                                    }
                                    foreach ($outcomes as $k => $vl) {
                                        $selected = $k == $arr['outcome'] ? 'selected' : '';
                                        echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col5">
                                <?php echo trpdd_show_symbol($arr['u_range']) ?>&nbsp;
                            </div>
                            <div class="col5">
                                <textarea class="specimen_comments" name="data_specimen[<?php echo $key ?>][comments]" style="height:50px;"><?php echo $arr['comments'] ?></textarea>
                            </div>
                        </div>
                    <?php
                    }
                    ?>
                </div>
            </div>

        <?php
        }
        $use_vdx = trpdd_use_screening_vdx($mid, $bat['batch_created']);
        if ($use_vdx) {
            $vdxs = tr_pdd_get_result_validity(2, $bat['batch_created'], $mid);
        ?>
            <div class="frow">
                <h4>VDX Synthetic Urine</h4>
            </div>
            <div class="frow">
                <div class="col5">
                    <strong>Test</strong>
                </div>
                <?php if (!$urine_settings['paper_strip']) : ?>
                    <div class="col5">
                        <strong>Value</strong>
                    </div>
                <?php endif; ?>
                <div class="col5">
                    <strong>Test Outcome</strong>
                </div>
                <div class="col5">
                    <strong>Reference Range</strong>
                </div>
                <div class="col5">
                    <strong>Comments</strong>
                </div>
            </div>
            <?php
            $CreVDX = 0;
            $SGVDX = 0;

            foreach ($vdxs as $arr) {
                $key = $arr['u_ID'];
                $arr['conc'] = preg_replace("/[^0-9-.,]/", '', $arr['conc']);

                if (isset($arr['readonly']) && $arr['readonly'] && !empty($arr['outcome'])) {
                    //dont change pos/neg
                } elseif ($arr['outcome'] != 'CAN') {
                    if (!empty($arr['conc']) && ($arr['conc'] < $arr['u_low'] || ($arr['conc'] > $arr['u_high'] && $arr['u_high'] > 0))) {
                        $arr['outcome'] = 'POS';
                    } else {
                        $arr['outcome'] =  'NEG';
                    }
                }
                if (stripos($arr['u_name'], 'creavdx') !== false) {
                    $CreVDX = $arr['conc'];
                } else if (stripos($arr['u_name'], 'sgvdx') !== false) {
                    $SGVDX = $arr['conc'];
                }

                if ($arr['u_name'] == 'C/SGR') {
                    $ratio =  number_format($CreVDX / (($SGVDX - 1) * 6667), 2);
            ?>
                    <div class="frow specimen_row vdx_row csgr_row" data-name="<?php echo strtolower($arr['u_name']) ?>">
                        <div class="col5">
                            <?php echo htmlspecialchars($arr['u_name']) ?>
                        </div>
                        <?php if (!$urine_settings['paper_strip']) : ?>
                            <div class="col5">
                                <input type="text" readonly value="<?php echo $ratio ?>" />
                            </div>
                        <?php endif ?>
                        <div class="col5">

                        </div>
                        <div class="col5">
                        </div>
                        <div class="col5">
                        </div>
                    </div>
                <?php
                } else {
                ?>
                    <div class="frow specimen_row vdx_row" data-name="<?php echo strtolower($arr['u_name']) ?>">
                        <div class="col5">
                            <?php echo htmlspecialchars($arr['u_name']) ?>
                        </div>
                        <?php if (!$urine_settings['paper_strip']) : ?>
                            <div class="col5">
                                <input type="text" data-min="<?php echo $arr['u_low'] ?>" data-max="<?php echo $arr['u_high'] ?>" class="speciment_value" name="data_specimen[<?php echo $key ?>][conc]" value="<?php echo $arr['conc'] ?>" />
                            </div>
                        <?php endif ?>
                        <div class="col5">
                            <select name="data_specimen[<?php echo $key ?>][outcome]" class="specimen_outcome njs_select2" data-min="<?php echo $arr['u_low'] ?>" data-max="<?php echo $arr['u_high'] ?>" data-comments="<?php echo $arr['u_comments'] ?>" data-u_reflex="<?php echo $arr['u_reflex'] ?>" data-u_reflex_tests="<?php echo $arr['u_reflex_tests'] ?>" data-u_reflex_clinics="<?php echo $arr['u_reflex_clinics'] ?>">
                                <?php
                                $outcomes = array(
                                    ''         => 'Select',
                                    'NEG'   => 'NORMAL',
                                    'POS' => 'ABNORMAL',
                                    'CAN' => 'Canceled',
                                );
                                if ($arr['u_neg_pos']) {
                                    $outcomes = array(
                                        ''    => 'Select',
                                        'NEG' => 'NEG',
                                        'POS' => 'POS',
                                        'CAN' => 'Canceled',
                                    );
                                }
                                foreach ($outcomes as $k => $vl) {
                                    $selected = $k == $arr['outcome'] ? 'selected' : '';
                                    echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col5">
                            <?php echo trpdd_show_symbol($arr['u_range']) ?>&nbsp;
                        </div>
                        <div class="col5">
                            <textarea class="specimen_comments" name="data_specimen[<?php echo $key ?>][comments]" style="height:50px;"><?php echo $arr['comments'] ?></textarea>
                        </div>
                    </div>
            <?php
                }
            }
        }

        $screenings = tr_pdd_get_result_validity(1, $bat['batch_created'], $mid);
        if (count($screenings) > 0) {
            ?>
            <div class="specimen_test" id="screening_results">
                <div class="frow">
                    <h4>Drug Screening</h4>
                </div>
                <div class="frow">
                    <div class="col5">
                        <strong>Test</strong>
                    </div>
                    <?php if (!$urine_settings['paper_strip']) : ?>
                        <div class="col5">
                            <strong>Value</strong>
                        </div>
                    <?php endif; ?>
                    <div class="col5">
                        <strong>Test Outcome</strong>
                    </div>
                    <div class="col5">
                        <strong>Reference Range</strong>
                    </div>
                    <div class="col5">
                        <strong>Comments</strong>
                    </div>
                </div>
                <div class="frow">
                    <?php
                    foreach ($screenings as $arr) {
                        $key = $arr['u_ID'];

                        if ($arr['outcome'] != 'CAN') {
                            if (!empty($arr['conc']) && ($arr['conc'] < $arr['u_low'] || ($arr['conc'] > $arr['u_high'] && $arr['u_high'] > 0))) {
                                $arr['outcome'] = 'POS';
                            } else if (!empty($arr['conc']) || empty($arr['outcome'])) {
                                $arr['outcome'] = 'NEG';
                            }
                        }
                        if (!$arr['checked']) {
                            $arr['outcome'] = '';
                        }

                        if (strtolower($arr['comments']) == 'description') {
                            $arr['comments'] = '';
                        }
                        if (($arr['outcome'] == 'POS' || $arr['outcome'] == 'ABNORMAL')) {
                            $k = $arr['conc'] < $arr['u_low'] ? 'low' : 'high';
                            $cm = $arr['u_comments'];
                            if (stripos($cm, '||') > 0) {
                                if (stripos($arr['comments'], ($k == 'low' ? 'high' : 'low')) === 0) {
                                    $arr['comments'] = '';
                                }
                                if (empty($arr['comments'])) {
                                    $cms = explode('||', $cm);
                                    $has_cm = false;
                                    foreach ($cms as $c) {
                                        $c = trim($c);
                                        if (stripos($c, $k) === 0) {
                                            $cm = $c;
                                            $has_cm = true;
                                            break;
                                        }
                                    }
                                    if (!$has_cm) {
                                        $cm = $arr['conc'] < $arr['u_low'] ? $cms[0] : $cms[1];
                                    }
                                }
                                $arr['comments'] = $cm;
                            } else if (empty($arr['comments'])) {
                                $arr['comments'] = $arr['u_comments'];
                            }

                            if ($arr['u_reflex'] && !empty($arr['u_reflex_tests'])) {
                                $clinic_ref = !empty($arr['u_reflex_clinics']) ? explode(',', $arr['u_reflex_clinics']) : array();

                                if (empty($arr['u_reflex_clinics']) || in_array($bat['cli_ID'], $clinic_ref)) {
                                    $reflex_tests = explode(',', $arr['u_reflex_tests']);
                                    $has_change_tests = false;
                                    foreach ($reflex_tests as $ref_test) {
                                        $ref_test = trim($ref_test);
                                        if (empty($ref_test)) continue;

                                        if (!in_array($ref_test, $bat['panel_tests_requested'])) {
                                            $bat['panel_tests_requested_str'] .= ',' . $ref_test;
                                            $has_change_tests = true;
                                        }
                                    }

                                    if ($has_change_tests && !empty($bat['panel_tests_requested_str'])) {
                                        $wpdb->update(
                                            'ci_batches',
                                            array('panel_tests_requested' => $bat['panel_tests_requested_str']),
                                            array('mid' => $bat['mid'])
                                        );
                                    }
                                }
                            }
                        }
                    ?>
                        <div class="frow specimen_row" data-min="<?php echo $arr['u_low'] ?>" data-pos="<?php echo $arr['u_neg_pos'] ?>" data-max="<?php echo $arr['u_high'] ?>" data-comments="<?php echo $arr['u_comments'] ?>" data-u_reflex="<?php echo $arr['u_reflex'] ?>" data-u_reflex_clinics="<?php echo $arr['u_reflex_clinics'] ?>" data-u_reflex_tests="<?php echo $arr['u_reflex_tests'] ?>">
                            <div class="col5">
                                <?php echo htmlspecialchars($arr['u_name']) ?>
                            </div>
                            <?php if (!$urine_settings['paper_strip']) : ?>
                                <div class="col5">
                                    <input type="text" class="speciment_value" name="data_specimen_screening[<?php echo $key ?>][conc]" value="<?php echo $arr['conc'] ?>" />
                                </div>
                            <?php endif ?>
                            <div class="col5">
                                <select name="data_specimen_screening[<?php echo $key ?>][outcome]" class="specimen_outcome njs_select2">
                                    <?php
                                    $outcomes = array(
                                        '' => 'Select',
                                        'NEG' => 'NORMAL',
                                        'POS' => 'ABNORMAL',
                                        'CAN' => 'Canceled',
                                    );
                                    if ($arr['u_neg_pos']) {
                                        $outcomes = array(
                                            '' => 'Select',
                                            'NEG' => 'NEG',
                                            'POS' => 'POS',
                                            'CAN' => 'Canceled',
                                        );
                                    }
                                    foreach ($outcomes as $k => $vl) {
                                        $selected = $k == $arr['outcome'] ? 'selected' : '';
                                        echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col5">
                                <?php echo trpdd_show_symbol($arr['u_range']) ?>&nbsp;
                            </div>
                            <div class="col5">
                                <textarea class="specimen_comments" name="data_specimen_screening[<?php echo $key ?>][comments]" style="height:50px;"><?php echo $arr['comments'] ?></textarea>
                            </div>
                        </div>
                    <?php
                    }
                    ?>
                </div>
            </div>
            <?php
        }

        if (tr_get_option('ci_pdf_data', 'tox_emrs_shortf') && $groups_results) {

            global $ci_cutoffs;

            $report = $wpdb->get_row("select * from ci_reports where patient_id = '{$bat['assen']}'", ARRAY_A);

            $drug_pairs = tr_pdd_get_drug_pairs();
            $medications = tr_pdd_parse_medications($report['medications'], $drug_pairs);

            $pos_neg = array(
                ''    => 'Select',
                'NEG' => 'Negative',
                'POS' => 'Positive',
                'PEN' => 'Pending',
                'INV' => 'Invalid',
            );

            foreach ($groups_results as $group) {
                $hide_value = false;
            ?>
                <div id="rs_GROUP_<?php echo $group['id'] ?>" class="group_results rs_by_test<?php if ($group['notestcheckbox']) echo ' notestcheckbox ' ?>" data-auto_batchid="<?php echo $group['auto_batchid'] ?>" data-auto_position="<?php echo $group['auto_position'] ?>">
                    <div class="group_rs_tt">
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
                    </div>
                    <?php
                    foreach ($group['tests'] as $test) {
                        if (empty($test->te_ID)) continue;
                        $drug = strtoupper($test->te_name);
                        $arr = array();
                        if ($report['id'] > 0) {
                            $arr = $wpdb->get_row("select * 
                                from ci_results rs
                                join ci_test_panels te ON te.te_name = rs.drug and te_type='tox'
                                where rs.report_id = {$report['id']} 
                                    and rs.group_id = {$test->tg_ID}
                                    and te_ID = {$test->te_ID} ", ARRAY_A);

                            if (
                                !$arr
                                && (in_array($test->te_ID, $bat['panel_tests_requested'])
                                    || in_array('GROUP_' . $test->tg_ID, $bat['panel_tests_requested']))
                            ) {
                                $arr = $wpdb->get_row("select * 
                                from ci_results rs
                                join ci_test_panels te ON te.te_name = rs.drug and te_type='tox'
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
                                $wpdb->update('ci_reports', array('instrument' => $report['instrument']), array('id' => $report['id']));
                            }
                        }


                        if ($arr['id'] > 0) {
                            $arr = tr_pdd_get_drug_class_comments($arr, $medications, $report['matrix'], $in_panel_tests_requested, $ci_cutoffs);
                        }

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
                $status_options = tr_pdd_get_batch_statuses_on_hold(tr_pdd_get_batch_statuses(false, false, false, 'tox'), $bat['batch_status'], $bat['mid'], $table_batch, 'tox');
                $disabled_keys = tr_pdd_get_batch_statuses_disabled_keys('tox', 'show_in_req_form', $bat['batch_status']);
                tr_select_option('bat[batch_status]', $status_options, @$bat['batch_status'], ['disabled_options' => $disabled_keys]);
                ?>
            </div>
            <div class="col3">
                <label>Photo</label><br>
                <input type="file" name="issue_photo" />
            </div>
        </div>

        <div class="frow">
            <h4>Results Upload</h4>
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
            <br><br>
        </div>
        <div class="frow">
            <h4>&nbsp;</h4>
        </div>
        <div class="frow">
            <input type="submit" class="button-primary" value="Submit">
            <input type="hidden" name="tr_action" value="ci_save_form_requisition" />
            <input type="hidden" name="bat[batch_short]" value="1" />
            <input type="hidden" name="bat[batch_type]" value="M" />
            <?php if (@$mid > 0) : ?>
                <input type="hidden" name="bat[mid]" value="<?php echo $mid ?>" />
            <?php endif; ?>
        </div>
        <?php trpdd_form_unique_id() ?>

    </form>
</div>
<div id="popup_selecter" style="display: none;"></div>

<style>
    .group_results .group_rs_tt,
    .group_results .rs_test,
    .group_results .rs_subgroup {
        display: none;
    }

    .group_result_show .group_rs_tt,
    .group_results.has_test .group_rs_tt,
    .group_result_show.notestcheckbox .rs_test,
    .group_results .rs_test.show_test,
    .group_results .rs_subgroup.has_test {
        display: block !important;
    }
</style>


<script>
    var $t_assen = false;
    jQuery(function($) {
        function tr_call_csgr() {
            var $crevdx = $('.vdx_row[data-name="crevdx"] .speciment_value').val();
            var $sgvdx = $('.vdx_row[data-name="sgvdx"] .speciment_value').val();
            var $vl = Math.round(($crevdx / (($sgvdx - 1) * 6667)) * 100) / 100;
            $('.csgr_row input').val($vl);
        }

        function get_current_medications_for_reflex() {
            var $medications = [];
            $('[name="bat[current_medication][]"]:checked').each(function() {
                $medications.push($(this).val());
            });
            $('[name="bat[current_medication_other][]"]').each(function() {
                if ($(this).val() != '') {
                    $medications.push($(this).val());
                }
            });

            return $medications.map(function($medication) {
                return $.trim($medication + '').replace(/\s+/g, ' ').toUpperCase();
            });
        }

        function has_related_screening_medication($screening) {
            if (($screening.data('u_reflex_pos_not_medication') + '') != '1') {
                return false;
            }

            var $related = ($screening.attr('data-related_medications') + '').split(',');
            var $medications = get_current_medications_for_reflex();
            for (var $i in $related) {
                var $medication = $.trim($related[$i] + '').replace(/\s+/g, ' ').toUpperCase();
                if ($medication != '' && $medications.indexOf($medication) > -1) {
                    return true;
                }
            }

            return false;
        }

        function remove_screening_reflex_tests($screening) {
            var $ts = $screening.data('added_tests');
            if ($ts) {
                $screening.data('added_tests', false);
                for (var $i in $ts) {
                    $('[name="bat[panel_tests_requested][]"][value="' + $ts[$i] + '"]').removeAttr('checked');
                }
            }
        }

        $('body').on('change', '.specimen_outcome', function() {
                var $this = $(this);
                var $pr = $(this).parents('.specimen_row');
                var $cm = $('.specimen_comments', $pr);
                var $min = parseFloat($pr.data('min'));
                var $max = parseFloat($pr.data('max'));
                var $vl = parseFloat($('.speciment_value', $pr).val());
                if ($this.val() == 'POS') {
                    var $comments = $pr.data('comments');
                    if ($comments.indexOf('||') > -1) {
                        var $html = '<select id="select_comments"  class="njs_select2" style="width:100%"><option value="">Choose a Comment</option>';
                        var $comments = $comments.split('||');
                        if ($comments.length == 2) {
                            if ($min > 0 && $vl < $min) {
                                $cm.val($comments[0]);
                            } else if ($vl > $max && $max > 0) {
                                $cm.val($comments[1]);
                            }
                        } else {
                            for (i in $comments) {
                                $html += '<option value="' + $comments[i] + '">' + $comments[i] + '</option>';
                            }
                            $html += '</select>';
                            $('#popup_selecter').html($html);
                            tb_remove();
                            tb_show("Choose a Comment", "#TB_inline?height=50&amp;width=400&amp;inlineId=popup_selecter");
                            $('#select_comments').change(function() {
                                if ($(this).val() != '') {
                                    $cm.val($(this).val());
                                    tb_remove();
                                }
                            })
                        }

                    } else {
                        $cm.val($comments);
                    }
                } else {
                    $cm.val('');
                }
                $('.screening_cb').change();

            })
            .on('change', '.speciment_value', function() {
                var $pr = $(this).parents('.specimen_row');
                var $min = parseFloat($pr.data('min'));
                var $max = parseFloat($pr.data('max'));
                var $vl = parseFloat($(this).val());
                var $pos = $pr.data('pos');
                var $status = 'NEG';

                if (isNaN($vl)) {
                    $status = '';
                } else if ($(this).data('min') == 'neg') {
                    $status = ($vl >= $max) ? 'POS' : 'NEG';
                } else if ($(this).val() == '') {
                    $status = '';
                } else if (($vl < $min && $min != '') || $vl > $max) {
                    $status = 'POS';
                }
                var $pr = $(this).parents('.specimen_row');
                $('.specimen_outcome', $pr).val($status).change();

            })
            .on('change keyup', '#number_assen', function() {
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
                            btype: 'tox'
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
            .on('change', '#batcustom_profile', function() {
                var $tpl = $(this).val();
                $tpl = $tpl.toLowerCase();
                $('#default_requests input').removeAttr('checked');
                try {

                    var $key = '';
                    if ($tpl.indexOf('mid ') > -1) $key = 'med';
                    else if ($tpl.indexOf('low ') > -1) $key = 'low';
                    else if ($tpl.indexOf('high ') > -1) $key = 'high';
                    else if ($tpl.indexOf('comprehensive') > -1) $key = 'comprehensive';
                    var $test_str = '';
                    if ($key) {
                        $test_str = $('[name="bat[doc_ID]"] option:selected').data($key);
                    }

                    if ($key && $test_str && $test_str.length > 0) {
                        var $tests = $test_str.split(',');
                        for (i in $tests) {
                            $('#default_requests input[value="' + $tests[i] + '"]').prop('checked', true);
                        }
                        $('#default_requests input').each(function() {
                            var $vl = $(this).val();
                            if ($vl.indexOf(',') > 0 && $test_str.indexOf($vl) > -1) {
                                $(this).prop('checked', true);
                            }
                        })
                    } else if ($tpl == 'previous testing') {
                        var $test_str = $('[name="pa[pa_ID]"]').data('last_tests');
                        var $tests = $test_str.split(',');
                        for (i in $tests) {
                            $('#default_requests input[value="' + $tests[i] + '"]').prop('checked', true);
                        }
                        $('#default_requests input').each(function() {
                            var $vl = $(this).val();
                            if ($vl.indexOf(',') > 0 && $test_str.indexOf($vl) > -1) {
                                $(this).prop('checked', true);
                            }
                        })
                    } else {
                        if ($tpl.indexOf('mid ') > -1) $tpl = 'standard';
                        else if ($tpl.indexOf('low ') > -1) $tpl = 'basic';
                        else if ($tpl.indexOf('high ') > -1) $tpl = 'highrisk';

                        $('#default_requests input.te_' + $tpl).prop('checked', true);
                    }
                } catch (e) {
                    console.log(e);
                }
                $('#default_requests input[disabled]').removeAttr('checked');
                $('.screening_cb').change();

            })
            .on('change', 'input[name="screening"]', function() {
                if ($(this).is(':checked') && $(this).val() == 1) {
                    $('#screening_div input').prop('checked', true).change();
                    $('#screening_results').show();
                } else {
                    $('#screening_div input').removeAttr('checked');
                    $('input[name="bat[only_screening]"]').prop('checked', false).change();
                    $('#screening_results').hide();
                }
            })
            .on('change', '#screening_div input', function() {
                var use_screening = false;
                $('#screening_div input').each(function() {
                    if ($(this).is(":checked")) {
                        use_screening = true;
                    }
                })
                if (use_screening) {
                    $('input[name="screening"][value="1"]').prop('checked', true);
                    $('#screening_results').show();
                } else {
                    $('input[name="screening"][value="0"]').prop('checked', true);
                    $('input[name="bat[only_screening]"][type="checkbox"]').prop('checked', false).change();
                    $('#screening_results').hide();
                }
            })
            .on('change', '[name="bat[only_screening]"][type="checkbox"]', function() {
                if ($(this).is(':checked')) {
                    $('#batcustom_profile').prop('required', false);
                } else {
                    $('#batcustom_profile').prop('required', true);
                }
                if ($(this).is(':checked') && $('input[name="screening"]:checked').val() == '0') {
                    $(this).prop('checked', false);
                    return false;
                }
            })
            .on('change', '[name="bat[type_of_specimen]"][value="Oral"]', function() {
                if ($('#requisition_form').data('testoral')) {

                    if ($(this).is(':checked')) {
                        $('[name="screening"][value="0"]').click();
                        $('.has_oral_0').addClass('disabledbyo');
                        $('.has_oral_0 input').prop('disabled', true).prop('checked', false);
                    } else {
                        $('.has_oral_0').removeClass('disabledbyo');
                        $('.has_oral_0 input').prop('disabled', false);
                    }
                }
            })
            .on('click change', '.screening_cb', function() {
                var $u_ID = $(this).data('id');
                var $ispos = $('select[name="data_specimen_screening[' + $u_ID + '][outcome]"]').val();
                $ispos = $ispos == 'POS' || $ispos == 'ABNORMAL';

                if ($(this).data('u_reflex') == '1') {

                    if ($(this).is(':checked') && $ispos) {
                        if (has_related_screening_medication($(this))) {
                            remove_screening_reflex_tests($(this));
                            return;
                        }
                        var $cli_id = $('[name="bat[cli_ID]"]').val();
                        var $ts = ($(this).data('u_reflex_tests') + '').split(',');
                        var $clinics = $(this).data('u_reflex_clinics') + '';

                        var $inClinic = true;
                        if ($clinics && $clinics != '') {
                            $clinics = $clinics.split(',');
                            $inClinic = false;
                            for (var j in $clinics) {
                                if ($cli_id == $clinics[j]) {
                                    $inClinic = true;
                                    break;
                                }
                            }
                        }

                        if ($inClinic) {
                            for (var i in $ts) {
                                $('[name="bat[panel_tests_requested][]"][value="' + $ts[i] + '"]').prop('checked', true);
                            }
                            $(this).data('added_tests', $ts);
                        }
                    } else {
                        remove_screening_reflex_tests($(this));
                    }
                }
            })
            .on('change keyup', '[name="bat[current_medication][]"], [name="bat[current_medication_other][]"]', function() {
                $('.screening_cb').change();
            })
            .on('click', '#box_create_rand', function() {
                var $ptient = encodeURI($('[name="pa[pa_last]"]').val() + '|' + $('[name="pa[pa_first]"]').val());
                var $dob = encodeURI($('[name="pa[pa_dob]"]').val())
                tb_show('', '<?php echo get_home_url(); ?>/wp-admin/admin.php?&tr_action=qrcode&editable&type=short&width=400&height=400&text=' + $(this).data('a') + '&pa=' + $ptient + '&dob=' + $dob);
                return false;
            })
            .on('click', '.group_label', function() {
                var $pl = $(this).parents('.groupparent');
                if (!$(this).data('checked')) {
                    $('.lcb input', $pl).prop('checked', true).change();
                    $(this).data('checked', true);
                } else {
                    $('.lcb input', $pl).prop('checked', false).change();
                    $(this).data('checked', false);
                }
            })
            .on('click change', '.groupparent .c input', function() {
                var $pr = $(this).parents('.groupparent');
                var $groupid = $pr.data('id');
                var $tid = $(this).data('id');
                var $groupdiv = $('#rs_GROUP_' + $groupid + '.rs_by_test');
                if ($groupdiv) {
                    if ($(this).is(':checked')) {
                        $('.rs_TEST_' + $tid, $groupdiv).addClass('show_test');
                    } else {
                        $('.rs_TEST_' + $tid, $groupdiv).removeClass('show_test');
                    }

                    if ($('.show_test', $groupdiv).length > 0) {
                        $groupdiv.addClass('has_test');
                    } else {
                        $groupdiv.removeClass('has_test');
                    }

                    if ($('.rs_subgroup', $groupdiv).length > 0) {
                        var $maingid = $(this).data('groupid');

                        if ($('.rs_GROUP_' + $maingid + '.show_test', $groupdiv).length > 0) {
                            $('.rs_sub_group_' + $maingid, $groupdiv).addClass('has_test');
                        } else {
                            $('.rs_sub_group_' + $maingid, $groupdiv).removeClass('has_test');
                        }
                    }

                }

            })
            .on('click', '[name="auto_generated"]', function() {
                if ($(this).is(':checked')) {
                    $('#number_assen,#confirm_assen').removeAttr('required').val('').attr('readonly', '1');
                } else {
                    $('#number_assen,#confirm_assen').attr('required', 'required').removeAttr('readonly');
                }
            })


        if ($('[name="bat[mid]"]').val() > 0) {
            $('#screening_div input').change();
        } else {
            $('input[name="screening"][value="1"]').prop('checked', true).change();
        }


        $('.csgr_row').each(function() {
            $('.vdx_row[data-name="crevdx"] .speciment_value,.vdx_row[data-name="sgvdx"] .speciment_value').change(function() {
                tr_call_csgr();
            }).change();
        })
        $('.groupparent .c input:checked').change();
        $('[name="bat[only_screening]"][type="checkbox"]').change();
    })
</script>
