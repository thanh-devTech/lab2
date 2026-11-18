<?php
global $blog_id, $wpdb;

include_once(TRCPDD_PATH . 'pages/formdata.php');

$groups_results     = array();
$settings           = get_option('ci_req_settings_blood', array());

$mid                = isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;
$act                = isset($_REQUEST['act']) ? $_REQUEST['act'] : $act;


if (!isset($settings['disable_auto_doc'])) {
    $settings['disable_auto_doc'] = false;
}


if (in_array($act, array('view', 'print', 'edit')) && $mid > 0) {
    $bat = $wpdb->get_row("select * from ci_batches_blood where mid='{$mid}' ", ARRAY_A);
    $cli['cID'] = $bat['cli_ID'];
    $cli_id = $cli['cID'];
    $pa = $wpdb->get_row("select pa.* from ci_patients pa
                           where pa.pa_ID = '{$bat['patient_id']}' ", ARRAY_A);
    if (!$pa) {
        $pa = array();
        list($pa['pa_first'], $pa['pa_last']) = explode(',', @$bat['patient_name']);
        $pa['pa_dob'] = $bat['dob'];
        $pa['pa_first'] = trim($pa['pa_first']);
        $pa['pa_last'] = trim($pa['pa_last']);
    }

    $bat['panel_tests_requested'] = explode(',', @$bat['panel_tests_requested']);
    $bat['medication'] = maybe_unserialize(@$bat['medication']);

    $bat['panel_tests_requested'] = array_map('trim', $bat['panel_tests_requested']);
} else {
    $cli['lab_ID'] = CI_Base_Lab::get_current_lab_id();

    $bat['mid']         = '';
    $bat['assen']         = '';
    $bat['doc']         = '';
    $bat['spec_received'] = '';
    $bat['lab_ID']      = '';
    $bat['medication']      = '';
    $bat['ref_lab_ID']  = '';
    $bat['requesting_practice']  = '';
    $bat['cli_ID']  = '';
    $bat['requesting_physician']  = '';
    $bat['type_of_specimen']  = '';
    $bat['batch_status']  = '';
    $bat['p_condition']  = '';
    $bat['batch_id'] = tr_pdd_get_current_batch_id();
    if (tr_get_option('ci_pdf_data', 'current_doc')) {
        $bat['doc'] = date_i18n('Y-m-d H:i:s');
    }
    $bat['panel_tests_requested'] = array();
}

?>
<div class="wrap">
    <h2>Short Form Blood</h2>
    <?php Tr_Session_Class::show() ?>

    <form method="post" enctype="multipart/form-data" data-btype="blood" class="need_validate main_form" id="requisition_form">

        <div class="frow blabel">
            <div class="col2">
                <div class="frow">
                    <label>Date Received</label>
                    <input type="text" class="hasdatetime today" name="bat[spec_received]" value="<?php echo isset($bat['spec_received']) ? $bat['spec_received'] : '' ?>" required="" />
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
                    <input type="text" class="cfname requesting_practice_auto" name="bat[requesting_practice]" value="<?php echo trim(@$bat['requesting_practice']) ?>" required />
                    <input type="hidden" name="bat[cli_ID]" value="<?php echo isset($bat['cli_ID']) ? $bat['cli_ID'] : '' ?>">
                </div>
                <div class="frow">
                    <label>ORDERING PHYSICIAN</label>
                    <input type="text" class="requesting_physician_auto cfname" name="bat[requesting_physician]" value="<?php echo trim(@$bat['requesting_physician']) ?>" required />
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
                    <input type="number" name="bat[position]" value="<?php echo isset($bat['position']) ? $bat['position'] : '' ?>" required />
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
        <?php if (tr_get_option('ci_pdf_data', 'enable_patient_score')) { ?>
            <div class="frow">
                <div class="col4">
                    <label>Pain Score</label>
                    <select name="bat[p_pain_score]" class="njs_select2">
                        <option value="">Select</option>
                        <?php
                        for ($i = 0; $i <= 10; $i++) {
                            $selected = isset($bat['p_pain_score']) && !in_array($bat['p_pain_score'], array(null, '')) && $bat['p_pain_score'] == $i ? 'selected' : '';
                            echo '<option value="' . $i . '" ' . $selected . '>' . $i . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col4">
                    <label>Fatigue Score</label>
                    <select name="bat[p_fatigue_score]" class="njs_select2">
                        <option value="">Select</option>
                        <?php
                        for ($i = 0; $i <= 10; $i++) {
                            $selected = isset($bat['p_fatigue_score']) && !in_array($bat['p_fatigue_score'], array(null, '')) && $bat['p_fatigue_score'] == $i ? 'selected' : '';
                            echo '<option value="' . $i . '" ' . $selected . '>' . $i . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col4">
                    <label>Reason for Testing</label>
                    <select name="bat[p_condition]" class="njs_select2">
                        <option value="">Select</option>
                        <?php
                        $conditions = array('Baseline', 'Therapy Start', 'Monitor Therapy', 'Acute Crisis');
                        foreach ($conditions as $condition) {
                            $selected = $condition == $bat['p_condition'] ? 'selected' : '';
                            echo '<option value="' . $condition . '" ' . $selected . '>' . $condition . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col4">
                    <label>Hemoglobin</label>
                    <input type="text" name="bat[p_hemoglobin]" value="<?php echo isset($bat['p_hemoglobin']) ? $bat['p_hemoglobin'] : '' ?>" maxlength="40" />
                </div>
            </div>
        <?php } ?>

        <div class="frow printbreak">
            <div>
                <div class="frow">
                    <h4>TEST LIST: <small>Please check appropriate panels that address your patients needs. Tests can be ordered individually</small></h4>
                </div>

                <input type="hidden" name="has_panel_requests" value="1" />

                <div id="default_requests" class="frow blabel panel_requests masonry" style="font-size:12px;column-count:<?php echo $settings['panel_column'] >= 2 ? $settings['panel_column'] : 5 ?>">
                    <?php
                    $tl = 0;

                    foreach (tr_pdd_get_profiles_tests_requesteds('blood', true, false, $bat['doc']) as $group_id => $vl) {
                        $has_select_group   = false;
                        $has_checked        = false;
                        $group_class        = '';
                        $group_key          = 'GROUP_' . $group_id;
                        $in_group           = @in_array($group_key, $bat['panel_tests_requested']);
                        $group              = tr_pdd_get_panel_groups($group_id, 'blood');
                        if ($mid == 0 && isset($group['other_options']['auto_checked']) && $group['other_options']['auto_checked']) {
                            $in_group = true;
                        }
                        if (isset($_GET['debug1'])) {
                            var_dump($group);
                        }
                        if (!$group['show'] && !$in_group) continue;
                        if ($group['parent'] > 0) {
                            $group_class .= ' parent_group_' . $group['parent'] . ' ';
                        }
                        if (!empty($group['relate_to'])) {
                            $group_class .= ' relate_to_group_' . $group['relate_to'] . ' ';
                        }
                        if ($group['only_group']) {
                            $vl = array();
                            $group_class .= ' only_group ';
                        }

                        $group_key = 'GROUP_' . $group_id;
                        $in_group = @in_array($group_key, $bat['panel_tests_requested']);
                        if ($in_group) {
                            $has_checked = true;
                        }
                        $group['color'] = trpdd_getContrastColor($group['bgcolor']);
                        $group['spec_bgcolor'] = strlen($group['bgcolor']) == 7 ? $group['bgcolor'] . '88' : $group['bgcolor'];
                        $group['box_bgcolor'] = strlen($group['bgcolor']) == 7 ? $group['bgcolor'] . '66' : $group['bgcolor'];

                        if (!empty($group['cli_IDs'])) {
                            $group_class .= ' by_clinic ';
                            foreach (explode(',', $group['cli_IDs']) as $pcid) {
                                $group_class .= ' cli_id_' . $pcid . ' ';

                                if ($bat['cli_ID'] == $pcid) {
                                    $group_class .= ' cactive ';
                                }
                            }
                        }

                        if (!empty($group['not_cli_IDs'])) {
                            $group_class .= ' not_by_clinic ';
                            foreach (explode(',', $group['not_cli_IDs']) as $pcid) {
                                $group_class .= ' not_cli_id_' . $pcid . ' ';

                                if ($bat['cli_ID'] == $pcid) {
                                    $group_class .= ' notcactive ';
                                }
                            }
                        }
                    ?>
                        <div class="groupparent item <?php echo $group_class ?>" style="background:<?php echo $group['box_bgcolor'] ?>">
                            <label class="block bold group_label" style="background:<?php echo $group['bgcolor'] ?>;color: <?php echo $group['color'] ?>">
                                <input type="checkbox" class="batcustom_profile cb_test group_print_labels" name="bat[panel_tests_requested][]" value="<?php echo $group_key ?>" data-relate_to="<?php echo $group['relate_to'] ?>" <?php checked($in_group) ?> />
                                <?php echo $group['label'] ?>
                            </label>
                            <div class="frow">
                                <?php
                                $list_nocheckbox = array();
                                $subgroups = array();
                                foreach ($vl as $k => $r) {
                                    $txt = $r->te_name;
                                    $key = $r->te_ID;
                                    if (empty($key)) continue;

                                    $checked = @in_array($key, $bat['panel_tests_requested']);
                                    if ($checked) {
                                        $has_checked = true;
                                    }

                                    if ($r->tg_parent > 0 && !in_array($r->tg_ID, $subgroups) && $group_id != $r->tg_ID) {
                                        $subgroups[] = $r->tg_ID;
                                        $subgrouup_key = 'GROUP_' . $r->tg_ID;
                                        $in_subgroup =  @in_array($subgrouup_key, $bat['panel_tests_requested']);
                                        $cb_subgroup_class = '';
                                ?>
                                        <div style="font-weight: bold;padding-left:20px">
                                            <label>
                                                <?php
                                                $subgroup_cb_class = '';
                                                if ($r->tg_only_group && false) {
                                                    $subgroup_key          = 'GROUP_' . $r->tg_ID;
                                                    $in_subgroup           = @in_array($subgroup_key, $bat['panel_tests_requested']);
                                                ?>
                                                    <input type="checkbox" class="<?php echo $subgroup_cb_class ?>" name="bat[panel_tests_requested][]" value="<?php echo $subgroup_key ?>" <?php checked($in_subgroup) ?> />
                                                <?php
                                                } ?>
                                                <?php echo $r->main_group ?>
                                            </label>
                                        </div>
                                    <?php
                                    }
                                    if ($r->tg_only_group) {
                                        continue;
                                    }
                                    ?>
                                    <label class="c lcb">
                                        <?php if (!$group['notestcheckbox']) { ?>
                                            <input type="checkbox" class="<?php echo $r->class ?> cb_test" name="bat[panel_tests_requested][]" data-relations="<?php echo $r->te_relations ?>" value="<?php echo $key ?>" <?php checked($checked) ?> />
                                        <?php } ?>
                                        <span><?php echo $txt ?></span>
                                    </label>
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
        </div>

        <?php if (isset($settings['e_orderable_test']) && $settings['e_orderable_test']) { ?>
            <div class="col3 tests_group ">
                <label>
                    <input type="hidden" name="bat[orderable_test]" value="0" />
                    <input type="checkbox" id="orderable_test_cb" class="cb_test" name="bat[orderable_test]" <?php checked($bat['orderable_test']) ?> value="1" />
                    Orderable Test
                </label>
            </div>
        <?php } ?>

        <?php
        if (tr_get_option('ci_pdf_data', 'blood_medication')) {

            $bat['medication'] = explode('|', $bat['medication']);

            $mgroups = $wpdb->get_results("select mg_ID,mg_name
                                                from ci_medication_groups 
                                                where mg_btype='blood' 
                                                order by mg_name asc");

            ob_start();
            $has_medication = false;
            foreach ($mgroups as $mgroup) {
                $medications = $wpdb->get_results("select * 
                                        from ci_medications 
                                        where me_group_id = {$mgroup->mg_ID}
                                        order by me_ID asc");
                if (empty($medications)) {
                    continue;
                }
                $has_medication = true;
        ?>
                <div class="col4 groupparent group_medication item ">
                    <label class="block bold group_label"><?php echo $mgroup->mg_name ?></label>
                    <div class="frow">
                        <?php
                        foreach ($medications as $medication) {
                            $low_name = strtolower($medication->me_name);
                            $medication->me_name = str_replace('Other.', 'Other', $medication->me_name);
                        ?>
                            <div class="meitem">
                                <?php
                                if ($low_name == 'other') {
                                    $other_vl = '';
                                    foreach ($bat['medication'] as $vl) {
                                        if (stripos($vl, 'other:') === 0) {
                                            $other_vl = str_ireplace('other:', '', $vl);
                                            break;
                                        }
                                    }
                                    echo '<label><input type="text" name="medication_other" placeholder="Other" value="' . $other_vl . '" ></label>';
                                } else if ($low_name == 'other.') {
                                    $other_vl = '';
                                    foreach ($bat['medication'] as $vl) {
                                        if (stripos($vl, 'other2:') === 0) {
                                            $other_vl = str_ireplace('other2:', '', $vl);
                                            break;
                                        }
                                    }
                                    echo '<label><input type="text" name="medication_other2" placeholder="Other" value="' . $other_vl . '" ></label>';
                                } else {
                                    $checked    = false;
                                    $me_value   = $medication->me_name;
                                    $me_start   = '';
                                    $me_end     = '';
                                    foreach ($bat['medication'] as $vl) {
                                        $arr = explode(';', $vl);
                                        $me_name = $arr[0];
                                        if ($me_name == $medication->me_name) {
                                            $checked = true;
                                            $me_start = isset($arr[1]) ? $arr[1] : '';
                                            $me_end = isset($arr[2]) ? $arr[2] : '';
                                            $me_value = $me_name . ';' . $me_start . ';' . $me_end;
                                            break;
                                        }
                                    }
                                ?>
                                    <label>
                                        <input type="checkbox" name="bat[medication][]" data-key="<?php echo $medication->me_name ?>" value="<?php echo $me_value ?>" <?php checked($checked) ?> />
                                        <?php echo $medication->me_name ?>
                                    </label>
                                <?php
                                    if ($settings['medication_date']  && stripos($mgroup->mg_name, 'Other Therapy') === false) {
                                        echo '<div class="daterange">';
                                        echo '<input type="text" class="hasdate mstart_date" value="' . $me_start . '" placeholder="Start Date"/>';
                                        echo '<input type="text" class="hasdate mend_date" value="' . $me_end . '" placeholder="End Date"/>';
                                        echo '</div>';
                                    }
                                }
                                ?>

                            </div>
                        <?php
                        }
                        ?>
                    </div>
                </div>
            <?php
            }
            $medication_html = ob_get_clean();

            if ($has_medication) {
            ?>
                <div class="frow">
                    <h4>Medications</h4>
                </div>
                <div class="frow blabel">
                    <?php echo $medication_html ?>
                </div>
                <input type="hidden" name="has_edit_medication" value="1" />
        <?php
            }
        }
        ?>

        <?php
        if ($groups_results) {
            $report = $wpdb->get_row("select * 
			                from ci_reports_blood 
			                where patient_id = '{$bat['assen']}'", ARRAY_A);

            $pos_neg = array(
                ''    => 'Select',
                'NEG' => 'Negative',
                'POS' => 'Positive',
                'PEN' => 'Pending',
                'INV' => 'Invalid',
            );

            foreach ($groups_results as $group) {
                $hide_value = false;
                $hide_outcome = false;
                if (stripos($group['label'], 'Rapid Antibody') !== false) {
                    $hide_value = true;
                } else if (stripos($group['label'], 'Hemoglobin Evaluation') !== false) {
                    $hide_outcome = true;
                }
        ?>
                <div id="rs_GROUP_<?php echo $group['id'] ?>" class="group_results <?php if (isset($group['edit_results_test']) && $group['edit_results_test']) echo ' rs_by_test ';
                                                                                    if ($group['notestcheckbox']) echo ' notestcheckbox ' ?>" data-auto_batchid="<?php echo $group['auto_batchid'] ?>" data-auto_position="<?php echo $group['auto_position'] ?>">
                    <div class="frow">
                        <h4>Group <?php echo $group['label'] ?></h4>
                    </div>
                    <div class="frow specimen_row vdx_row csgr_row">
                        <div class="col5">
                            <strong>Test</strong>
                        </div>

                        <div class="col5">
                            <?php if (!$hide_value) { ?>
                                <strong>Value</strong>
                            <?php } else {
                                echo '&nbsp;';
                            } ?>
                        </div>

                        <div class="col5">
                            <?php if (!$hide_outcome) { ?>
                                <strong>Test Outcome</strong>
                            <?php } else {
                                echo '&nbsp;';
                            } ?>
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
                        if (isset($report['id']) && $report['id'] > 0) {
                            $arr = $wpdb->get_row("select * 
                                from ci_results_blood rs
                                join ci_test_panels te ON te.te_name = rs.drug and te_type='blood'
                                where rs.report_id = {$report['id']} 
                                    and rs.group_id = {$test->tg_ID}
                                    and te_ID = {$test->te_ID} ", ARRAY_A);

                            if (
                                !$arr
                                && (in_array($test->te_ID, $bat['panel_tests_requested'])
                                    || in_array('GROUP_' . $test->tg_ID, $bat['panel_tests_requested']))
                            ) {
                                $sql2 = "select * 
                                from ci_results_blood rs
                                join ci_test_panels te ON te.te_name = rs.drug and te_type='blood'
                                                and te_group_id = {$test->tg_ID}
                                where rs.report_id = {$report['id']} 
                                    and (rs.group_id is null or rs.group_id = 0)
                                    and te_ID = {$test->te_ID} ";

                                $arr = $wpdb->get_row($sql2, ARRAY_A);
                                if (isset($_GET['debug'])) {
                                    var_dump($sql2);
                                }
                            }
                        }

                        if (empty($arr)) {
                            $arr = array(
                                'drug' => $drug,
                                'te_ID' => $test->te_ID,
                            );
                        }

                        $rskey = $test->te_ID . '_' . $drug . '_' . $test->tg_ID . '_' . (isset($arr['id']) ? $arr['id'] : '');

                        //search instrument
                        if (!empty($arr['conc']) && $report && empty($report['instrument'])) {

                            $report['instrument'] = $wpdb->get_var(
                                "select cu_ms from ci_test_panels_cutoffs 
	                                where cu_te_ID = {$test->te_ID} and cu_ms is not null and cu_ms!=''"
                            );

                            if (!empty($report['instrument'])) {
                                $report['instrument'] = str_ireplace(array('_male', '_female'), '', $report['instrument']);
                                $wpdb->update('ci_reports_blood', array('instrument' => $report['instrument']), array('id' => $report['id']));
                            }
                        }


                        $arr = tr_pdd_parse_blood_test($arr, isset($report['instrument']) ? $report['instrument'] : '', $test->tg_ID, $bat);
                        if ($test->tg_parent > 0 && !in_array($test->tg_ID, $subgroups)) {
                            $subgroups[] = $test->tg_ID;
                            $subclass = '';
                    ?>
                            <div class="frow rs_subgroup rs_sub_group_<?php echo $test->tg_ID ?>">
                                <h4 class="center" style="line-height: 22px"><?php echo $test->main_group ?></h4>
                            </div>
                        <?php
                        }
                        $pos_neg_vls = $pos_neg;

                        if ($test->te_is_detected) {
                            $pos_neg_vls['POS'] = 'Detected';
                            $pos_neg_vls['NEG'] = 'Not Detected';
                        }


                        $arr['conc'] = trim($arr['conc']);
                        $selected_ot = substr($arr['conc'], 0, 1);
                        $selected_ot = in_array($selected_ot, array('<', '>')) ? $selected_ot : '';
                        $arr['conc'] = trim(str_replace(array('>', '<'), '', $arr['conc']));
                        ?>
                        <div class="frow trow">
                            <div class="col5 tname">
                                <span><?php echo $test->te_name ?></span>
                            </div>
                            <div class="col5">
                                <?php if (!$hide_value) { ?>
                                    <input type="text" name="results[<?php echo $rskey ?>][conc]" value="<?php echo $arr['conc'] ?>" />
                                <?php } else {
                                    echo '&nbsp;';
                                } ?>
                            </div>
                            <div class="col5">
                                <?php if (!$hide_outcome) { ?>
                                    <select name="results[<?php echo $rskey ?>][pos-neg]">
                                        <?php
                                        foreach ($pos_neg_vls as $k => $vl) {
                                            $selected = $k == $arr['pos-neg'] ? 'selected' : '';
                                            echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
                                        }
                                        ?>
                                    </select>
                                <?php } else {
                                    echo '&nbsp;';
                                } ?>
                            </div>
                            <div class="col5">
                                <?php echo isset($arr['range']) && !empty($arr['range']) ? $arr['range'] : (isset($arr['te_desc']) ? $arr['te_desc'] : '') ?>&nbsp;
                            </div>
                            <div class="col5">
                                <textarea class="specimen_comments" name="results[<?php echo $rskey ?>][comments]" style="height:50px;"><?php echo isset($arr['comments']) ? $arr['comments'] : '' ?></textarea>
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
            <div class="col3">
                <label>Status</label>
                <?php
                $status_options = tr_pdd_get_batch_statuses_on_hold(tr_pdd_get_batch_statuses(false, false, false, 'blood'), $bat['batch_status'], $bat['mid'], $table_batch, 'blood');
                $disabled_keys = tr_pdd_get_batch_statuses_disabled_keys('blood', 'show_in_req_form', $bat['batch_status']);
                tr_select_option('bat[batch_status]', $status_options, @$bat['batch_status'], ['disabled_options' => $disabled_keys]);
                ?>
            </div>
            <div class="col3">
                <div class="card_file">
                    <label>Issue Photo</label><br>
                    <a class="button selectfile" href="#">Choose File</a>
                    <span class="view">
                        <?php
                        $dir = tr_get_issue_photo_dir();
                        $fname = isset($bat['issue_photo']) ? $bat['issue_photo'] : '';
                        if (!empty($fname) && file_exists($dir['path'] . '/' . $fname) && filesize($dir['path'] . '/' . $fname) > 0) {
                            echo '<a href="' . $dir['url'] . '/' . $fname . '">View File</a>';
                        }
                        ?>
                    </span>
                    <input type="file" name="issue_photo" accept="image/*" />
                </div>
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
        <?php if (!in_array($act, array('view', 'print'))) { ?>
            <div class="frow">
                <h4>&nbsp;</h4>
            </div>

            <div class="frow">
                <input type="submit" class="button-primary" value="Submit">
                <input type="hidden" name="tr_action" value="ci_save_blood" />
                <input type="hidden" name="bat[batch_short]" value="1" />
                <input type="hidden" name="bat[batch_type]" value="M" />
                <?php if (@$mid > 0) : ?>
                    <input type="hidden" name="bat[mid]" value="<?php echo $mid ?>" />
                <?php endif; ?>
            </div>
        <?php } ?>
        <?php trpdd_form_unique_id() ?>

    </form>
</div>
<div id="popup_selecter" style="display: none;"></div>
<script>
    var $t_assen = false;
    var $changebychild = false;
    var $currentdate = '<?php echo date_i18n('Y-m-d') ?>';
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
                            btype: 'blood'
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
                //$('#batcustom_profile').change();
            })
            .on('click', '[name="auto_generated"]', function() {
                if ($(this).is(':checked')) {
                    $('#number_assen,#confirm_assen').removeAttr('required').val('').attr('readonly', '1');
                } else {
                    $('#number_assen,#confirm_assen').attr('required', 'required').removeAttr('readonly');
                }
            })
            .on('change', '[name="bat[assen]"]', function() {
                if ($(this).val() != '') {
                    $('#show_auto_gennerated').hide();
                } else {
                    $('#show_auto_gennerated').show();
                }
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
            .on('click change', '.groupparent .c input.cb_test', function() {
                var $pr = $(this).parents('.groupparent');
                var $prcb = $('.group_label input', $pr);
                var $relations = $(this).data('relations');
                if ($(this).is(':checked')) {
                    $changebychild = true;
                    $prcb.prop('checked', true).change();

                    if ($relations) {
                        $relations = $relations.split(',');
                        for (i in $relations) {
                            var $rlid = $relations[i];
                            $('.c input.cb_test[value="' + $rlid + '"]', $pr).prop('checked', true);
                        }
                    }
                } else {
                    if ($('.c input.cb_test:checked', $pr).length == 0) {
                        $prcb.prop('checked', false).change();
                    }
                }
            })
            .on('change', '.batcustom_profile', function() {
                var $pr = $(this).parents('.groupparent');
                var $rl_ids = $(this).data('relate_to');
                var $rlids = ($rl_ids === undefined || $rl_ids === null || $rl_ids === '') ? [] : String($rl_ids).split(',').map(function($id) {
                    return $.trim(String($id));
                }).filter(function($id) {
                    return $id !== '';
                });
                if ($(this).is(":checked")) {
                    if (!$changebychild) {
                        $('.c input', $pr).prop('checked', true);
                    }
                    $('.specimens', $pr).addClass('grequired');

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
                    if (!$changebychild) {
                        $('.c input', $pr).removeAttr('checked');
                    }
                    $('.specimens', $pr).removeClass('grequired');
                    $('.specimens', $pr).removeClass('gerror');
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

                var $groupid = $(this).val();
                if ($(this).is(':checked')) {
                    $('#rs_' + $groupid).show();
                } else {
                    $('#rs_' + $groupid).hide();
                }
            })
            .on('change', '.meitem input[type="checkbox"]', function() {
                var $pr = $(this).parents('.meitem');
                if ($(this).is(':checked')) {
                    $pr.addClass('active');
                    var $startf = $('input.mstart_date', $pr);
                    if ($startf.val() == '') {
                        //$startf.val($currentdate).change();
                    }

                } else {
                    $pr.removeClass('active');
                }
            })
            .on('change', '.meitem .daterange input', function() {
                var $pr = $(this).parents('.meitem');
                var $cb = $('input[type="checkbox"]', $pr);
                var $startd = $('input.mstart_date', $pr).val();
                var $endd = $('input.mend_date', $pr).val();
                var $arr = $cb.val().split(';');
                var $newvl = $arr[0] + ';' + $startd + ';' + $endd;
                $cb.val($newvl);
            })
            .on('found_patient', 'a.find_patient', function(e, data) {
                if (data['last_tests']) {
                    var $tests = data['last_tests'].split(',');
                    for (var i in $tests) {
                        var $t = $tests[i];
                        $('input.cb_test[value="' + $t + '"]').prop('checked', true);
                    }
                }
                if (data['medication']) {
                    var $medications = data['medication'].split('|');
                    for (var i in $medications) {
                        var $marr = $medications[i].split(';');
                        var $t = $marr[0];
                        var $cb = $('input[name="bat[medication][]"][data-key="' + $t + '"]');
                        if ($cb.length > 0) {
                            var $startd = $marr[1] ? $marr[1] : '';
                            var $endd = $marr[2] ? $marr[2] : '';
                            if ($endd != '' && $endd < $currentdate) {
                                continue;
                            }
                            $cb.prop('checked', true).change();
                            var $pr = $cb.parents('.meitem');
                            $('input.mstart_date', $pr).val($startd).change();
                            $('input.mend_date', $pr).val($endd).change();

                        } else if ($t.indexOf('other2:') > -1) {
                            var $newt = $t.replace("other2:", '');
                            $('[name="medication_other2"]').val($newt);
                        } else if ($t.indexOf('other:') > -1) {
                            var $newt = $t.replace("other:", '');
                            $('[name="medication_other"]').val($newt);
                        }

                    }
                }
            }).on('change', '[name="bat[cli_ID]"]', function() {
                var $cli_id = $(this).val();
                $('.groupparent.by_clinic').removeClass('cactive');
                $('.groupparent.by_clinic.cli_id_' + $cli_id).addClass('cactive');

                $('.groupparent.not_by_clinic').removeClass('notcactive');
                $('.groupparent.not_by_clinic.not_cli_id_' + $cli_id).addClass('notcactive');
            })

        $('[name="bat[assen]"]').change();
        $('.meitem input[type="checkbox"]').change();
        $('.batcustom_profile').each(function() {
            var $groupid = $(this).val();
            if ($(this).is(':checked')) {
                $('#rs_' + $groupid).show();
            } else {
                $('#rs_' + $groupid).hide();
            }
        })

        if ($('[name="auto_generated"]').is(":checked")) {
            $('#number_assen,#confirm_assen').removeAttr('required').val('').attr('readonly', '1');

        }


    })
</script>