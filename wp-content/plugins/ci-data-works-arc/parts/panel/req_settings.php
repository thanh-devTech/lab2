<?php

if (!function_exists('trpdd_arr_options2text')) {
    function trpdd_arr_options2text($arr)
    {
        $lines = array();
        if (is_string($arr)) {
            if (!empty($arr)) {
                $arrnew = array();
                foreach (explode('|', $arr) as $line) {
                    $line = trim($line);
                    if (strpos($line, ',') > 0) {
                        $arr2 = explode(',', $line, 2);
                        $arrnew[trim($arr2[0])] = trim($arr2[1]);
                    } else {
                        $arrnew[] = $line;
                    }
                }
                $arr = $arrnew;
            } else {
                $arr = array();
            }
        }
        foreach ($arr as $k => $vl) {
            $lines[] = $k . ' , ' . $vl;
        }
        return implode("\n", $lines);
    }
}

if (!function_exists('trpdd_text_options2arr')) {
    function trpdd_text_options2arr($string)
    {
        $arr = array();
        foreach (explode("\n", $string) as $line) {
            $line = trim($line);
            if (!empty($line)) {
                if (strpos($line, ',') > 0) {
                    $arr2 = explode(',', $line, 2);
                    $arr[trim($arr2[0])] = trim($arr2[1]);
                } else {
                    $arr[] = $line;
                }
            }
        }
        return $arr;
    }
}
$key =  'ci_req_settings_' . $tes_type;

$options = get_option($key);

if (isset($_POST['import_settings'])) {
    if (isset($_FILES['settings_import_file']) && !empty($_FILES['settings_import_file']['tmp_name'])) {
        $import = json_decode(file_get_contents($_FILES['settings_import_file']['tmp_name']), true);
        $import_options = array();

        if (is_array($import) && isset($import['option_key']) && $import['option_key'] == $key && isset($import['options']) && is_array($import['options'])) {
            $import_options = $import['options'];
        }

        if (!empty($import_options)) {
            $logdata = $import_options;
            $logdata['old'] = $options;
            update_option($key, $import_options);
            trpdd_log_user(0, 'import_req_st_' . $tes_type, $logdata);
            Tr_Session_Class::add('Imported Successfully!');
            $options = get_option($key);
        } else {
            Tr_Session_Class::add('Invalid import file.');
        }
    } else {
        Tr_Session_Class::add('Please select an import file.');
    }
}

if (isset($_POST['options'])) {
    $_POST['options'] = tr_pdd_stripslashes_deep($_POST['options']);
    $logdata          = $_POST['options'];
    $logdata['old']   = $options;

    $options = wp_parse_args($_POST['options'], $options);

    if (isset($options['pa_genotypes'])) {
        $options['pa_genotypes'] = trpdd_text_options2arr($options['pa_genotypes']);
    }

    if (isset($options['enabel_test_control_by']) && $options['enabel_test_control_by']) {
        $wpdb->query("alter table ci_test_panels add column te_control_bys text");
    }



    update_option($key, $options);

    trpdd_log_user(0, 'req_st_' . $tes_type, $logdata);

    Tr_Session_Class::add('Updated Successfully!');


    $options = get_option($key);
}



?>
<div class="wrap">

    <h2>Panel Options for <?php echo strtoupper($tes_type) ?>
        <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '', 'tabtype' => '')) ?>" class="add-new-h2">All Tests</a>
        <a href="#" class="add-new-h2 trpdd-settings-import-export">Export/Import</a>

    </h2>
    <?php Tr_Session_Class::show() ?>

    <br><br>

    <div id="trpdd_settings_import_export_modal" style="display:none;position:fixed;z-index:100000;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.35);">
        <div style="background:#fff;width:420px;max-width:90%;margin:120px auto 0;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,.2);">
            <h3 style="margin-top:0;">Export/Import Settings</h3>
            <p>
                <a href="#" class="button button-primary trpdd-export-settings">Export</a>
            </p>
            <form method="post" enctype="multipart/form-data">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <input type="file" name="settings_import_file" accept="application/json,.json" />
                    <input type="submit" name="import_settings" class="button" value="Import" />
                    <a href="#" class="button trpdd-close-settings-import-export">Close</a>
                </div>
            </form>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data">

        <?php
        if ($tes_type != 'study') {
            //not study
        ?>
            <div class="frow">
                <div class="col3">

                    <div class="frow">
                        <input type="hidden" name="options[show_pa_genotypes]" value="0" />
                        <label><input type="checkbox" name="options[show_pa_genotypes]" value="1" <?php checked(isset($options['show_pa_genotypes']) ? $options['show_pa_genotypes'] : '') ?> />
                            Show Genotypes in Patient box </label>
                    </div>
                    <div class="frow">
                        <input type="hidden" name="options[auto_on_patient_signature_file]" value="0" />
                        <label><input type="checkbox" name="options[auto_on_patient_signature_file]" value="1" <?php checked(isset($options['auto_on_patient_signature_file']) ? $options['auto_on_patient_signature_file'] : '') ?> />
                            Auto On Patient Signature On-File </label>
                    </div>
                    <div class="frow">
                        <input type="hidden" name="options[show_panel_list]" value="0" />
                        <label><input type="checkbox" name="options[show_panel_list]" value="1" <?php checked(isset($options['show_panel_list']) ? $options['show_panel_list'] : '') ?> />
                            Show Panel in Completed reqs </label>
                    </div>
                    <div class="frow">
                        <input type="hidden" name="options[enable_filter]" value="0" />
                        <label><input type="checkbox" name="options[enable_filter]" value="1" <?php checked(isset($options['enable_filter']) ? $options['enable_filter'] : '') ?> />
                            Enable filter </label>
                    </div>
                    <div class="frow">
                        <input type="hidden" name="options[enable_client_bill]" value="0" />
                        <label><input type="checkbox" name="options[enable_client_bill]" value="1" <?php checked(isset($options['enable_client_bill']) ? $options['enable_client_bill'] : '') ?> />
                            Enable client bill </label>
                    </div>
                    <div class="frow">
                        <input type="hidden" name="options[req_inc_pathogogy]" value="0" />
                        <label><input type="checkbox" name="options[req_inc_pathogogy]" value="1" <?php checked(isset($options['req_inc_pathogogy']) ? $options['req_inc_pathogogy'] : '') ?> />
                            Enable Request Aegis Pathology Consult. </label>
                    </div>
                    <div class="frow">
                        <input type="hidden" name="options[add_standing_order]" value="0" />
                        <label><input type="checkbox" data-target="block_add_standing_order" name="options[add_standing_order]" value="1" <?php checked(isset($options['add_standing_order']) ? $options['add_standing_order'] : '') ?> />
                            Enable Add Standing Order(add multi orders) </label>
                    </div>
                    <div class="frow">
                        <input type="hidden" name="options[hide_pre_authorization]" value="0" />
                        <label><input type="checkbox" name="options[hide_pre_authorization]" value="1" <?php checked(isset($options['hide_pre_authorization']) ? $options['hide_pre_authorization'] : '') ?> />
                            Hide Pre-authorization</label>
                    </div>

                    <div class="frow">
                        <input type="hidden" name="options[dagnostic_code_required]" value="0" />
                        <label><input type="checkbox" name="options[dagnostic_code_required]" value="1" <?php checked(isset($options['dagnostic_code_required']) ? $options['dagnostic_code_required'] : '') ?> />
                            Enable DIAGNOSTIC CODES Required </label>
                    </div>
                    <div class="frow">
                        <input type="hidden" name="options[disable_auto_doc]" value="0" />
                        <label><input type="checkbox" name="options[disable_auto_doc]" value="1" <?php checked(isset($options['disable_auto_doc']) ? $options['disable_auto_doc'] : '') ?> />
                            Turn Off Auto DOC </label>
                    </div>
                    <div class="frow">
                        <input type="hidden" name="options[disable_workercomp]" value="0" />
                        <label><input type="checkbox" name="options[disable_workercomp]" value="1" <?php checked(isset($options['disable_workercomp']) ? $options['disable_workercomp'] : '') ?> />
                            Turn Off Worker's Comp and Auto Information </label>
                    </div>

                    <div class="frow">
                        <input type="hidden" name="options[disable_auto_ptests]" value="0" />
                        <label><input type="checkbox" name="options[disable_auto_ptests]" value="1" <?php checked(isset($options['disable_auto_ptests']) ? $options['disable_auto_ptests'] : '') ?> />
                            Turn Off Auto Patient previous Tests </label>
                    </div>
                    <div class="frow">
                        <input type="hidden" name="options[auto_approve_rs]" value="0" />
                        <label><input type="checkbox" name="options[auto_approve_rs]" value="1" <?php checked(isset($options['auto_approve_rs']) ? $options['auto_approve_rs'] : '') ?> />
                            Enable Auto Approve result when uploaded pdf </label>
                    </div>


                    <div class="frow">
                        <input type="hidden" name="options[onlyonepanel]" value="0" />
                        <label><input type="checkbox" name="options[onlyonepanel]" value="1" <?php checked(isset($options['onlyonepanel']) ? $options['onlyonepanel'] : '') ?> />
                            One panel per req form to be selected.</label>
                    </div>
                </div>



                <div class="col3">


                    <?php

                    if ($tes_type == 'blood') {
                    ?>
                        <div class="frow">
                            <input type="hidden" name="options[add_blood_pcr]" value="0" />
                            <label><input type="checkbox" name="options[add_blood_pcr]" value="1" <?php checked(isset($options['add_blood_pcr']) ? $options['add_blood_pcr'] : '') ?> />
                                Enable Add new PCR after submit</label>
                        </div>
                        <div class="frow">
                            <input type="hidden" name="options[show_paf_bsign]" value="0" />
                            <label><input type="checkbox" name="options[show_paf_bsign]" value="1" <?php checked(isset($options['show_paf_bsign']) ? $options['show_paf_bsign'] : '') ?> />
                                Show Patient Information (some fields) below Signature </label>
                        </div>
                        <div class="frow">
                            <input type="hidden" name="options[show_pa_bmi]" value="0" />
                            <label><input type="checkbox" name="options[show_pa_bmi]" value="1" <?php checked(isset($options['show_pa_bmi']) ? $options['show_pa_bmi'] : '') ?> />
                                Show Patient BMI </label>
                        </div>
                        <div class="frow">
                            <input type="hidden" name="options[show_questions]" value="0" />
                            <label><input type="checkbox" name="options[show_questions]" value="1" <?php checked(isset($options['show_questions']) ? $options['show_questions'] : '') ?> />
                                Show Questions </label>
                        </div>
                        <div class="frow">
                            <input type="hidden" name="options[show_pa_hbgenotypes]" value="0" />
                            <label><input type="checkbox" name="options[show_pa_hbgenotypes]" value="1" <?php checked(isset($options['show_pa_hbgenotypes']) ? $options['show_pa_hbgenotypes'] : '') ?> />
                                Show HP Genotypes in Patient box </label>
                        </div>


                        <div class="frow">
                            <input type="hidden" name="options[medication_date]" value="0" />
                            <label><input type="checkbox" name="options[medication_date]" value="1" <?php checked(isset($options['medication_date']) ? $options['medication_date'] : '') ?> />
                                Enable Date for Medication</label>
                        </div>

                        <div class="frow">
                            <input type="hidden" name="options[e_orderable_test]" value="0" />
                            <label><input type="checkbox" name="options[e_orderable_test]" value="1" <?php checked(isset($options['e_orderable_test']) ? $options['e_orderable_test'] : '') ?> />
                                Enable "Orderable Test"</label>
                        </div>

                        <div class="frow block_add_standing_order">
                            <input type="hidden" name="options[show_all_standing_order]" value="0" />
                            <label><input type="checkbox" name="options[show_all_standing_order]" value="1" <?php checked(isset($options['show_all_standing_order']) ? $options['show_all_standing_order'] : '') ?> />
                                Show all Order with Phlebotomist Code in Clinic </label>
                        </div>

                        <div class="frow block_add_standing_order">
                            <input type="hidden" name="options[Phlebotomist_showallblood]" value="0" />
                            <label><input type="checkbox" name="options[Phlebotomist_showallblood]" value="1" <?php checked(isset($options['Phlebotomist_showallblood']) ? $options['Phlebotomist_showallblood'] : '') ?> />
                                Show all Orders</label>
                        </div>
                        <div class="frow">
                            <input type="hidden" name="options[Phlebotomist]" value="0" />
                            <label><input type="checkbox" data-target="block_phlebotomist" name="options[Phlebotomist]" value="1" <?php checked(isset($options['Phlebotomist']) ? $options['Phlebotomist'] : '') ?> />
                                Enable Phlebotomist </label>
                        </div>

                        <div class="frow block_phlebotomist">

                            <div class="frow">
                                <input type="hidden" name="options[show_phlebotomist_status]" value="0" />
                                <label><input type="checkbox" name="options[show_phlebotomist_status]" value="1" <?php checked(isset($options['show_phlebotomist_status']) ? $options['show_phlebotomist_status'] : '') ?> />
                                    show Phlebotomist Status In export</label>
                            </div>


                            <div class="frow">
                                <input type="hidden" name="options[phle_d_medicare]" value="0" />
                                <label><input type="checkbox" name="options[phle_d_medicare]" value="1" <?php checked(isset($options['phle_d_medicare']) ? $options['phle_d_medicare'] : '') ?> />
                                    Disable Phlebotomist "Medicare Patients" </label>
                            </div>
                        </div>
                        <div class="frow">
                            <input type="hidden" name="options[Phlebotomist_reced_comed]" value="0" />
                            <label><input type="checkbox" name="options[Phlebotomist_reced_comed]" value="1" <?php checked(isset($options['Phlebotomist_reced_comed']) ? $options['Phlebotomist_reced_comed'] : '') ?> />
                                Enable Phlebotomist Received ->completed</label>
                        </div>
                        <div class="frow">
                            <input type="hidden" name="options[highlight_selected_tests_arc_byte]" value="0" />
                            <label><input type="checkbox" name="options[highlight_selected_tests_arc_byte]" value="1" <?php checked(isset($options['highlight_selected_tests_arc_byte']) ? $options['highlight_selected_tests_arc_byte'] : '') ?> />
                                Highlight selected tests in yellow</label>
                        </div>
                        <div class="frow">
                            <input type="hidden" name="options[show_ordered_tests_box]" value="0" />
                            <label><input type="checkbox" name="options[show_ordered_tests_box]" value="1" <?php checked(isset($options['show_ordered_tests_box']) ? $options['show_ordered_tests_box'] : '') ?> />
                                Show Ordered Tests</label>
                        </div>


                    <?php
                    } else if ($tes_type == 'tox') {
                    ?>
                        <div class="frow">
                            <input type="hidden" name="options[hide_only_screening]" value="0" />
                            <label><input type="checkbox" name="options[hide_only_screening]" value="1" <?php checked(isset($options['hide_only_screening']) ? $options['hide_only_screening'] : '') ?> />
                                Disable Only Screening </label>
                        </div>
                        <div class="frow">
                            <input type="hidden" name="options[alert_check_screening]" value="0" />
                            <label><input type="checkbox" name="options[alert_check_screening]" value="1" <?php checked(isset($options['alert_check_screening']) ? $options['alert_check_screening'] : '') ?> />
                                Enable Check Screening by clinic when submit</label>
                        </div>
                        <div class="frow">
                            <input type="hidden" name="options[hide_poc]" value="0" />
                            <label><input type="checkbox" name="options[hide_poc]" value="1" <?php checked(isset($options['hide_poc']) ? $options['hide_poc'] : '') ?> />
                                Disable POC </label>
                        </div>
                        <div class="frow">
                            <input type="hidden" name="options[e_phase]" value="0" />
                            <label><input type="checkbox" name="options[e_phase]" value="1" <?php checked(isset($options['e_phase']) ? $options['e_phase'] : '') ?> />
                                Enable Phase </label>
                        </div>
                        <div class="frow">
                            <input type="hidden" name="options[patient_phase_page]" value="0" />
                            <label><input type="checkbox" name="options[patient_phase_page]" value="1" <?php checked(isset($options['patient_phase_page']) ? $options['patient_phase_page'] : '') ?> />
                                Enable Patient Phase page </label>
                        </div>
                        <div class="frow">
                            <input type="hidden" name="options[temperature_required]" value="0" />
                            <label><input type="checkbox" name="options[temperature_required]" value="1" <?php checked(isset($options['temperature_required']) ? $options['temperature_required'] : '') ?> />
                                Temperature Required </label>
                        </div>

                    <?php
                    } else if ($tes_type == 'pcr') {
                    ?>

                        <div class="frow">
                            <input type="hidden" name="options[add_blood_pcr]" value="0" />
                            <label><input type="checkbox" name="options[add_blood_pcr]" value="1" <?php checked(isset($options['add_blood_pcr']) ? $options['add_blood_pcr'] : '') ?> />
                                Enable Add new Blood after submit</label>
                        </div>
                        <div class="frow">
                            <input type="hidden" name="options[pcrplus]" value="0" />
                            <label><input type="checkbox" name="options[pcrplus]" value="1" <?php checked(isset($options['pcrplus']) ? $options['pcrplus'] : '') ?> />
                                Use main req form (PCR+)</label>
                        </div>
                        <div class="frow">
                            <input type="hidden" name="options[dagnostic_code_bpanel]" value="0" />
                            <label><input type="checkbox" name="options[dagnostic_code_bpanel]" value="1" <?php checked(isset($options['dagnostic_code_bpanel']) ? $options['dagnostic_code_bpanel'] : '') ?> />
                                DIAGNOSTIC CODES by panel (PCR+)</label>
                        </div>
                        <div class="frow">
                            <input type="hidden" name="options[enabel_cultureid]" value="0" />
                            <label><input type="checkbox" name="options[enabel_cultureid]" value="1" <?php checked(isset($options['enabel_cultureid']) ? $options['enabel_cultureid'] : '') ?> />
                                Enable "Culture ID with Reflexive Antibiotic Sensitivity testing"</label>
                        </div>
                        <div class="frow">
                            <input type="hidden" name="options[enabel_test_control_by]" value="0" />
                            <label><input type="checkbox" name="options[enabel_test_control_by]" value="1" <?php checked(isset($options['enabel_test_control_by']) ? $options['enabel_test_control_by'] : '') ?> />
                                Enable Test Control By(any control POS then POS)</label>
                        </div>

                        <div class="frow">
                            <input type="hidden" name="options[enable_control_by_specimen]" value="0" />
                            <label><input type="checkbox" name="options[enable_control_by_specimen]" value="1" <?php checked(isset($options['enable_control_by_specimen']) ? $options['enable_control_by_specimen'] : '') ?> />
                                Enable Test Control by Specimen</label>
                        </div>

                        <div class="frow">
                            <input type="hidden" name="options[hide_control_tests]" value="0" />
                            <label><input type="checkbox" name="options[hide_control_tests]" value="1" <?php checked(isset($options['hide_control_tests']) ? $options['hide_control_tests'] : '') ?> />
                                Hide control tests</label>
                        </div>
                        <div class="frow">
                            <input type="hidden" name="options[e_orderable_test]" value="0" />
                            <label><input type="checkbox" name="options[e_orderable_test]" value="1" <?php checked(isset($options['e_orderable_test']) ? $options['e_orderable_test'] : '') ?> />
                                Enable "Orderable Test"</label>
                        </div>

                        <div class="frow">
                            <input type="hidden" name="options[pcr_orderable_ua]" value="0" />
                            <label><input type="checkbox" name="options[pcr_orderable_ua]" value="1" <?php checked(isset($options['pcr_orderable_ua']) ? $options['pcr_orderable_ua'] : '') ?> />
                                orderable UA</label>
                        </div>
                        <div class="frow">
                            <input type="hidden" name="options[e_wound_sources]" value="0" />
                            <label><input type="checkbox" name="options[e_wound_sources]" value="1" <?php checked(isset($options['e_wound_sources']) ? $options['e_wound_sources'] : '') ?> />
                                Enable "Specimen Location/Source"</label>
                        </div>
                        <div class="frow">
                            <input type="hidden" name="options[enable_panel_specimen_source]" value="0" />
                            <label><input type="checkbox" name="options[enable_panel_specimen_source]" value="1" <?php checked(isset($options['enable_panel_specimen_source']) ? $options['enable_panel_specimen_source'] : '') ?> />
                                Enable Panel "Specimen Source"</label>
                        </div>
                    <?php
                    }
                    ?>

                </div>
                <div class="col3">


                    <?php
                    if ($tes_type == 'blood') {
                    ?>









                        <div class="frow block_phlebotomist">
                            <label>Phlebotomist Indentifier</label>
                            <input type="text" name="options[phle_indentifier]" value="<?php isset($options['phle_indentifier']) ? $options['phle_indentifier'] : '' ?>" />
                        </div>
                        <div class="frow block_phlebotomist">
                            <label>Phlebotomist Company</label>
                            <input type="text" name="options[phle_company]" value="<?php isset($options['phle_company']) ? $options['phle_company'] : '' ?>" />
                        </div>


                        <div class="frow">
                            <input type="hidden" name="options[quick_intake]" value="0" />
                            <label><input type="checkbox" name="options[quick_intake]" value="1" <?php checked(isset($options['quick_intake']) ? $options['quick_intake'] : '') ?> />
                                Enable Quick Intake </label>
                        </div>

                        <div class="frow">
                            <input type="hidden" name="options[print_assen_specimen]" value="0" />
                            <label><input type="checkbox" name="options[print_assen_specimen]" value="1" <?php checked(isset($options['print_assen_specimen']) ? $options['print_assen_specimen'] : '') ?> />
                                Print barcode by Specimen(Urine: U, Blood: W)</label>
                        </div>


                        <div class="frow">
                            <label>Print second (W) label for</label>
                            <select name="options[print_secondlb]">
                                <option value="">Select Type</option>
                                <?php
                                $sptypes = $wpdb->get_results("select * from ci_test_specimens where ts_type='{$tes_type}'");
                                foreach ($sptypes as $sptype) {
                                    if ($sptype->ts_name == 'Blood') {
                                        $sptype->ts_name = 'Whole Blood';
                                    }
                                    $selected = $sptype->ts_ID == $options['print_secondlb'] ? 'selected="selected"' : '';
                                    echo '<option value="' . $sptype->ts_ID . '" ' . $selected . '>' . $sptype->ts_name . '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="frow">
                            <label>Print second (U) label for</label>
                            <select name="options[print_secondlb_u]">
                                <option value="">Select Type</option>
                                <?php
                                $sptypes = $wpdb->get_results("select * from ci_test_specimens where ts_type='{$tes_type}'");
                                foreach ($sptypes as $sptype) {
                                    if ($sptype->ts_name == 'Blood') {
                                        $sptype->ts_name = 'Whole Blood';
                                    }
                                    $selected = $sptype->ts_ID == $options['print_secondlb_u'] ? 'selected="selected"' : '';
                                    echo '<option value="' . $sptype->ts_ID . '" ' . $selected . '>' . $sptype->ts_name . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <?php

                        if (tr_get_option('ci_pdf_data', 'plate_coating')) {
                            $coating_types = array(
                                'VP' => 'VP',
                                'VPV' => 'VP Validation',
                                'P' => 'PSel', //487
                                'V' => 'VCam', //48
                                'M' => 'Mixed',
                                'PT' => 'PT',
                                'VT' => 'VT',
                            );
                        ?>
                            <div class="frow">
                                <label>Plate Coating Types</label>
                                <select class="js_select2" name="options[plate_coating_types][]" multiple>
                                    <?php
                                    foreach ($coating_types as $k => $txt) {
                                        $selected = in_array($k, $options['plate_coating_types']) ? 'selected' : '';
                                        echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        <?php
                        }
                    } else if ($tes_type == 'pcr') {
                        ?>
                        <div class="frow ">
                            <input type="hidden" name="options[show_temperature]" value="0" />
                            <label><input type="checkbox" name="options[show_temperature]" value="1" <?php checked(isset($options['show_temperature']) ? $options['show_temperature'] : '') ?> />
                                Show Temperature</label>
                        </div>
                        <div class="frow ">
                            <input type="hidden" name="options[show_refasseninlist]" value="0" />
                            <label><input type="checkbox" name="options[show_refasseninlist]" value="1" <?php checked(isset($options['show_refasseninlist']) ? $options['show_refasseninlist'] : '') ?> />
                                Show Ref column in req list</label>
                        </div>
                        <div class="frow ">
                            <label>Text below Diagnostic Codes</label>
                            <textarea name="options[textafter_diagnostic_codes]"><?php echo isset($options['textafter_diagnostic_codes']) ? $options['textafter_diagnostic_codes'] : '' ?></textarea>
                        </div>
                    <?php
                    }
                    ?>

                    <div class="frow">
                        <label>Panel Column Count: </label>
                        <input type="number" name="options[panel_column]" value="<?php echo isset($options['panel_column']) ? $options['panel_column'] : '' ?>" max="5" />

                    </div>
                    <div class="frow">
                        <label>Genotypes: </label>
                        <textarea name="options[pa_genotypes]" placeholder="<?php echo "key , value\nkey2 , value2" ?>"><?php echo trpdd_arr_options2text($options['pa_genotypes']) ?></textarea>
                    </div>
                    <div class="frow">
                        <label>Print Barcode Times: </label>
                        <input type="number" name="options[printbarcod2times]" value="<?php echo isset($options['printbarcod2times']) ? $options['printbarcod2times'] : '1' ?>" min="1" max="5" />

                    </div>
                </div>
            </div>

            <hr>

            <div class="frow">
                <div class="col2">
                    <h3>Above Panels</h3>
                    <label>Title</label>
                    <input type="text" name="options[abovepanels_title]" value="<?php echo isset($options['abovepanels_title']) ? $options['abovepanels_title'] : '' ?>" />
                    <br>
                    <h3>Below Panels area</h3>
                    <textarea name="options[below_panels]"><?php echo isset($options['below_panels']) ? $options['below_panels'] : '' ?></textarea>
                </div>
            </div>


            <div class="frow">
                <div class="col2">
                    <h3>Above Patient Signature</h3>
                    <label>Title</label>
                    <input type="text" name="options[abovep_title]" value="<?php echo isset($options['abovep_title']) ? $options['abovep_title'] : '' ?>" />
                    <br>
                    <label>Text</label>
                    <textarea name="options[abovep_text]"><?php echo isset($options['abovep_text']) ? $options['abovep_text'] : '' ?></textarea>
                </div>
                <div class="col2">
                    <h3>Above Provider Signature</h3>
                    <label>Title</label>
                    <input type="text" name="options[abovepro_title]" value="<?php echo isset($options['abovepro_title']) ? $options['abovepro_title'] : '' ?>" />
                    <br>
                    <label>Text</label>
                    <textarea name="options[abovepro_text]"><?php echo isset($options['abovepro_text']) ? $options['abovepro_text'] : '' ?></textarea>

                </div>
            </div>

            <div class="frow">
                <input type="hidden" name="options[abovep_32]" value="0" />
                <label><input type="checkbox" name="options[abovep_32]" value="1" <?php checked(isset($options['abovep_32']) ? $options['abovep_32'] : '') ?> /> Show Patient 3/2 column </label>
            </div>

        <?php
        }
        ?>

        <div class="frow">
            <input type="submit" class="button-primary" value="Save" />
        </div>
    </form>
</div>

<script>
    jQuery(function($) {
        var trpddSettingsExport = <?php echo wp_json_encode(array(
                                        'option_key' => $key,
                                        'type' => $tes_type,
                                        'tabtype' => 'req_settings',
                                        'exported_at' => current_time('mysql'),
                                        'options' => is_array($options) ? $options : array(),
                                    ), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        var trpddSettingsExportFilename = <?php echo wp_json_encode($tes_type . '-req-settings-' . date('Ymd-His') . '.json') ?>;

        $('body').on('click', '.trpdd-settings-import-export', function(e) {
            e.preventDefault();
            $('#trpdd_settings_import_export_modal').show();
        }).on('click', '.trpdd-close-settings-import-export', function(e) {
            e.preventDefault();
            $('#trpdd_settings_import_export_modal').hide();
        }).on('click', '#trpdd_settings_import_export_modal', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        }).on('click', '.trpdd-export-settings', function(e) {
            e.preventDefault();
            var blob = new Blob([JSON.stringify(trpddSettingsExport, null, 2)], {
                type: 'application/json'
            });
            var url = URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.href = url;
            link.download = trpddSettingsExportFilename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        });

        $('body').on('change', 'input[type="checkbox"][data-target]', function() {
            console.log($(this).data('target'));
            if ($(this).is(':checked')) {
                $('.' + $(this).data('target')).show();
            } else {
                $('.' + $(this).data('target')).hide();
            }
        })

        $('input[type="checkbox"][data-target]').change();
    })
</script>