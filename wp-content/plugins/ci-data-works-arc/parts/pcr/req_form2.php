<?php
global $wpdb;
global $current_role, $form_error;
global $ci_all_btypes;

$assen_readonly = false;

$by_type_id = 0;
$by_btype = '';
if ($id == 'by_btype' && isset($_SESSION['add_new_by_btype'][0]) && !isset($is_prefill_covid)) {
    $by_type_id  = $_SESSION['add_new_by_btype'][0];
    $by_btype    = $_SESSION['add_new_by_btype'][3];
}

if ($act == 'view' || $act == 'edit' || $act == 'print' || $by_type_id > 0) {

    $action_form = '';

    if ($by_type_id > 0) {
        $batch_other_tbl = tr_pdd_get_table_by_btype($by_btype);
        $bat        = $wpdb->get_row("select * from {$batch_other_tbl} where mid='{$by_type_id}' ", ARRAY_A);
        $can_edit = true;
        $bat['assen'] = '';
        $bat['panel_tests_requested'] = '';
    } else {
        $bat = $wpdb->get_row("select * from {$table_batch} where mid='{$id}' ", ARRAY_A);
    }
    $cli['cID'] = $bat['cli_ID'];
    $cli_id     = $cli['cID'];
    $cli        = $wpdb->get_row("select * from ci_clinic where `cID`={$cli_id}", ARRAY_A);

    $pa         = $wpdb->get_row("select pa.* from ci_patients pa
                           where pa.pa_ID = '{$bat['patient_id']}' ", ARRAY_A);

    if ($cli_id > 0) {
        $where_cli .= " or cID = {$cli_id} ";
    }
    if (!$pa) {
        $pa = array();
        list($pa['pa_last'], $pa['pa_first']) = explode(',', @$bat['patient_name']);
        $pa['pa_dob'] = $bat['dob'];

        $pa['pa_first'] = trim($pa['pa_first']);
        $pa['pa_last'] = trim($pa['pa_last']);
    }
    $ins1 = $wpdb->get_row("select * from ci_insurances where ins_ID = '{$bat['insurance1']}' ", ARRAY_A);
    $ins2 = $wpdb->get_row("select * from ci_insurances where ins_ID = '{$bat['insurance2']}' ", ARRAY_A);


    if ((!$ins1 || empty($ins1['ins_firs'])) && in_array($bat['insurance'], ['No Insurance - Cash', 'Client bill'])) {
        $ins1['ins_first'] = $pa['pa_first'];
        $ins1['ins_last'] = $pa['pa_last'];
        $ins1['ins_relation'] = 'Self';
    }
    if ($ins1['ins_cares_act'] && in_array($bat['insurance'], ['No Insurance - Cash', 'Client bill'])) {
        $bat['insurance'] = '';
    }

    $collector = $wpdb->get_row("select * from ci_collector where col_ID ={$bat['col_ID']}", ARRAY_A);

    $bat['btype'] = 'pcr';

    $bat['panel_tests_requested_str'] = $bat['panel_tests_requested'];
    $bat['panel_tests_requested'] = explode(',', @$bat['panel_tests_requested']);
    $bat['panel_tests_requested'] = array_map('trim', $bat['panel_tests_requested']);
    $bat['panel_tests_requested'] = array_map('strtoupper', $bat['panel_tests_requested']);


    $bat['dagnostic_codes'] = explode(',', isset($bat['dagnostic_codes1']) ? $bat['dagnostic_codes1'] : '');
    if (empty($bat['mid'])) {
        $bat['dagnostic_codes'] = array();
    }



    if (substr($bat['doc'], 0, 4) == '1970') {
        $bat['doc'] = date_i18n('Y-m-d H:i:s');
    }



    $batch_key = $bat['assen'];

    if ($billing_page) {

        $sql = "SELECT
            lab.*,
            ba.*,
            ba.patient_id as pa_ID,
            rl.lab_additional as rl_lab_additional,
            re.*
        FROM ci_reports_pcr re
    
        JOIN {$table_batch} ba ON  ba.assen = re.patient_id
    
        JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
        LEFT JOIN ci_labs rl ON rl.lab_ID = ba.ref_lab_ID
        WHERE ba.mid='" . $id . "' group by ba.mid limit 1";

        $report = $wpdb->get_row($sql, ARRAY_A);
    }


    if ($act == 'edit') {
        $can_edit = true;
        $mid = $id;
    }

    $barcode_key = tr_get_option('ci_pdf_data', 'barcode_key');
    if (!empty($bat['assen']) && !empty($barcode_key) && stripos($bat['assen'], $barcode_key) === 0) {
        $assen_readonly = true;
    }

    $notallowchangepatient = tr_get_option('ci_pdf_data', 'disable_c_patient');
    $notallowchangepatientdob = tr_get_option('ci_pdf_data', 'disable_c_patientdob');


    $is_hold = $bat['batch_status'] == 'hold';
} else {
    $notallowchangepatient      = false;
    $notallowchangepatientdob   = false;
    $bat['panel_tests_requested'] = array();
    $bat['custom_profile']      = '';
    $bat['medication']          = array();
    $bat['current_medication']  = array();
    $bat['poc_perform']         = '0';
    $bat['doc']                 = '';
    $bat['type_of_specimen']    = '';
    $bat['patient_signature']   = '';
    $bat['batch_status']   = '';
    $bat['p_condition']   = '';
    $bat['dagnostic_codes'] = array();

    $pa                         = array();

    $can_edit = true;

    if ($form_error) {
        $bat = $_POST['bat'];
    }
    $bat['lab_id'] = 1;

    if ($cli_id == 0 && $current_clinic_id > 0) {
        $cli_id = $bat['cli_ID'] = $cli['cID'] = $current_clinic_id;
    }

    $ins1 = array(
        'ins_relation' => 'Self',
        //'ins_company' => 'N/A',
        //'ins_email' => 'N/A',
        // 'ins_address' => 'N/A',
        // 'ins_city' => 'N/A',
        //  'ins_state' => 'N/A',
        // 'ins_zip' => 'N/A',
        // 'ins_member_id' => 'N/A',
    );

    if (tr_get_option('ci_pdf_data', 'current_doc')) {
        $bat['doc'] = date_i18n('Y-m-d H:i:s');
    }
}

if ($current_clinic_id > 0) {
    $where_cli .= " and (cli.cID = {$current_clinic_id} or cli.cID = '{$bat['cli_ID']}')";
}

if (isset($_POST['clinic_id'])) {
    $cli_id = $_POST['clinic_id'];
    $cli = $wpdb->get_row("select * from ci_clinic where `cID`={$cli_id}", ARRAY_A);
}


if (empty($bat['ins_comp'])) {
    $bat['ins_comp'] = 'ins';
}
$card_dir = tr_get_ins_card_dir();
$sign_dir = tr_get_signature_dir();

$sql_clinic = "select cli.* from ci_clinic cli
                    Left JOIn ci_clinic_collector cc ON cc.cli_ID = cli.cID
                    left join ci_lab_reps lr on cli.`cli_rep-ID` = lr.rep_ID
                    where {$where_cli} group by cli.cID order by cli.cli_clinic";

if (isset($_GET['debug'])) {
    echo '<br>' . $sql_clinic . '<br>';
}
$clinics = $wpdb->get_results($sql_clinic, ARRAY_A);
if (count($clinics) == 1) {
    $cli = $clinics[0];
    $cli_id = $bat['cli_ID'] = $cli['cID'];
}

if ($cli_id > 0) {
    $bat['col_ID'] = isset($bat['col_ID']) ? $bat['col_ID'] : 0;

    $doctors = $wpdb->get_results("select doc.* 
                    from ci_doc doc
                        JOIn ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
                        where cd.cli_ID = {$cli_id}  {$where_doctor}
                        order by doc.doc_last,doc.doc_first asc", ARRAY_A);


    //checkdoctor
    if ($bat['doc_ID'] > 0) {
        $doc_ids = array();
        foreach ($doctors as $doctor) {
            $doc_ids[] = $doctor['doc_ID'];
        }
        if (!in_array($bat['doc_ID'], $doc_ids)) {
            //find doctorid
            foreach ($doctors as $doctor) {
                if ($bat['requesting_physician'] == $doctor['doc_last'] . ' ' . $doctor['doc_first']) {
                    $bat['doc_ID'] = $doctor['doc_ID'];
                    $wpdb->update($table_batch, array('doc_ID' => $bat['doc_ID']), array('mid' => $bat['mid']));
                    break;
                }
            }
        }
    }

    if ($current_role != 'collector') {
        $collectors = $wpdb->get_results("select col.* from ci_collector col
                                JOIN ci_clinic_collector cc ON cc.col_ID = col.col_ID
                                where cc.cli_ID ={$cli_id} and (col.col_status=1 or col.col_ID = '{$bat['col_ID']}')", ARRAY_A);
    }
}

if (isset($bat['lab_id']) && $bat['lab_id'] > 0) {
    $current_lab = $wpdb->get_row("select * from ci_labs Where lab_ID = {$bat['lab_id']}", ARRAY_A);
} else {
    $current_lab = CI_Base_Lab::get_current_lab();
}

$labs = CI_Base_Lab::get_user_labs();

$ref_labs = $wpdb->get_results("select * from ci_labs  
                where lab_ID != '{$current_lab['lab_ID']}' {$where_ref_lab} 
                order by lab_dba");


if (isset($_REQUEST['debug'])) {
    var_dump($sql_clinic);
    echo '<br><br>$current_lab:<br>';
    var_dump($current_lab);
    echo '<br><br>reflabs:<br>';
    var_dump($ref_labs);
    echo '<br><br>$cli:<br>';
    var_dump($cli);
    var_dump($bat['lab_id']);
    echo '<br><br>';
    var_dump($labs);
}
?>
<div class="wrap_form wrap">

    <form method="post" enctype="multipart/form-data" class="need_validate main_form <?php if (!$bat || !isset($bat['mid']) || !$bat['mid']) echo 'emptyform' ?>" id="requisition_form">
        <div style="<?php if (isset($print_canvas)) echo 'min-height:1056px' ?>">

            <?php if ($act == 'print') include(trpdd_get_file_path('global', 'print_req_header')); ?>

            <h2 class="center">PCR REQUISITION FORM</h2>
            <?php
            if ($act != 'print') {
                Tr_Session_Class::show();
            }
            ?>

            <?php
            if ($act == 'view' || $billing_page) { ?>
                <div class="right foutrow">
                    <label>Accession Number:</label>
                    <span class="text bold binline" style="width:200px"><?php echo isset($bat['assen']) ? $bat['assen'] : '' ?>&nbsp;&nbsp;</span>
                </div>
            <?php } ?>

            <div class="frow blabel hlabel">
                <div class="col4">
                    <?php
                    if (count($labs) > 1) {
                        $labs_options   = array('' => 'Select Lab');
                        $only_labids    = array();

                        if (count($labs) > 1 && tr_current_role_in(array('office_manager', 'doctor'))) {
                            foreach ($clinics as $clinic) {
                                $only_labids[] = $clinic['cli_lab-ID'];
                            }
                        }
                        if (isset($mid) && $mid > 0) {
                            $only_labids[] = $bat['lab_id'];
                        }
                        $one_lab_id = 0;
                        foreach ($labs as $l) {
                            if (count($only_labids) > 0 && !in_array($l['lab_ID'], $only_labids)) {
                                continue;
                            }
                            $labs_options[$l['lab_ID']] = $l['lab_dba'];
                            $one_lab_id = $l['lab_ID'];
                        }
                        if (!isset($mid) || empty($mid)) {
                            if (count($labs_options) == 2) {
                                $bat['lab_id'] = $one_lab_id;
                            } else if (empty($bat['lab_id'])) {
                                $bat['lab_id'] = CI_Base_Lab::get_current_lab_id();
                            }
                        }
                    ?>
                        <label>Lab</label>
                        <?php tr_select_option('bat[lab_id]', $labs_options, $bat['lab_id'], array('attr' => 'required')) ?>
                        <?php
                    }

                    if (tr_get_option('ci_pdf_data', 'show_reflab_comreqs') && count($ref_labs) > 0) {
                        $ref_labs_options = array('' => 'Select Ref Lab');
                        if ($current_role == 'ref_lab_tech') {
                            $ref_labs_options = array();
                        }
                        foreach ($ref_labs as $l) {
                            $ref_labs_options[$l->lab_ID] = $l->lab_name;
                        }

                        if (!tr_get_option('ci_pdf_data', 'disable_rlbcl')) {
                            if (is_array($cli) && $cli['cli_ref_lab_ID'] > 0) {
                                $ref_labs_options = array();
                            } else if (tr_current_role_in(array('office_manager'))) {
                                $has_cli_ref_lab = false;
                                foreach ($clinics as $clinic) {
                                    if ($clinic['cli_ref_lab_ID'] > 0) {
                                        $ref_labs_options = array();
                                        break;
                                    }
                                }
                            }
                        }

                        if (count($ref_labs_options) > 0) {
                        ?>
                            <label>Ref Lab</label>
                            <div id="ref_lab_area">
                                <?php tr_select_option('bat[ref_lab_ID]', $ref_labs_options, $bat['ref_lab_ID'], array()) ?>
                            </div>
                    <?php
                        }
                    }

                    ?>
                    <label>Practice Information</label>
                    <?php
                    if (tr_current_role_in(array('lab_sec'))) {

                        $oattr = ' data-address="' . $cli['cli_street'] . '" ';
                        $oattr .= ' data-city="' . $cli['cli_city'] . '" ';
                        $oattr .= ' data-state="' . $cli['cli_state'] . '" ';
                        $oattr .= ' data-zip="' . $cli['cli_zip'] . '" ';
                        $oattr .= ' data-phone="' . $cli['cli_om-phone'] . '" ';
                        $oattr .= ' data-print2="' . $cli['cli_print_2labels'] . '" ';
                        $oattr .= ' data-pbtn="' . $cli['cli_show_submit_print_bc'] . '" ';
                        $oattr .= ' data-autoa="' . $cli['cli_auto_assen'] . '" ';
                        $oattr .= ' data-reason="' . $cli['cli_show_reason_testing'] . '" ';
                        $oattr .= ' data-reflab="' . $cli['cli_ref_lab_ID'] . '" ';

                    ?>
                        <input type="text" class="cfname requesting_practice_auto" name="bat[requesting_practice]" value="<?php echo isset($bat['requesting_practice']) ? $bat['requesting_practice'] : '' ?>" required />
                        <input type="hidden" name="bat[cli_ID]" value="<?php echo isset($bat['cli_ID']) ? $bat['cli_ID'] : '' ?>" <?php echo $oattr ?> />
                    <?php
                    } else { ?>
                        <select name="bat[cli_ID]" required id="cli_change" class="js_select2">
                            <option value="">Select Clinic</option>
                            <?php
                            foreach ($clinics as $clinic) {
                                $selected = $cli && $cli['cID'] == $clinic['cID'] ? 'selected' : '';
                                $oattr = ' data-address="' . $clinic['cli_street'] . '" ';
                                $oattr .= ' data-city="' . $clinic['cli_city'] . '" ';
                                $oattr .= ' data-state="' . $clinic['cli_state'] . '" ';
                                $oattr .= ' data-zip="' . $clinic['cli_zip'] . '" ';
                                $oattr .= ' data-phone="' . $clinic['cli_om-phone'] . '" ';
                                $oattr .= ' data-print2="' . $clinic['cli_print_2labels'] . '" ';
                                $oattr .= ' data-pbtn="' . $clinic['cli_show_submit_print_bc'] . '" ';
                                $oattr .= ' data-autoa="' . $clinic['cli_auto_assen'] . '" ';
                                $oattr .= ' data-reason="' . $clinic['cli_show_reason_testing'] . '" ';
                                $oattr .= ' data-reflab="' . $clinic['cli_ref_lab_ID'] . '" ';

                                echo '<option value="' . $clinic['cID'] . '" ' . $selected . ' ' . $oattr . '>' . $clinic['cli_clinic'] . '</option>';
                            }
                            ?>
                        </select>
                    <?php } ?>

                    <?php if (tr_get_option('ci_pdf_data', 'show_priority_comreqs')) { ?>
                        <div class="frow">
                            <label>Priority</label>
                            <?php
                            tr_select_option('bat[priority_hours]', array(
                                '0' => 'None',
                                '12' => '12 hrs',
                                '24' => '24 hrs',
                                '48' => '48 hrs',
                                '72' => '72 hrs',
                                '120' => '5 days',
                                '168' => '7 days',
                                '-1' => 'Regular',
                                '-2' => 'STAT',
                            ), @$bat['priority_hours']) ?>
                        </div>
                    <?php } ?>
                </div>
                <div class="col4" id="cli_change_div">
                    <label>Ordering Physician</label>
                    <?php if (tr_current_role_in(array('lab_sec'))) {
                    ?>
                        <input type="text" class="requesting_physician_auto cfname" name="bat[requesting_physician]" value="<?php echo isset($bat['requesting_physician']) ? $bat['requesting_physician'] : '' ?>" required />
                        <input type="hidden" name="bat[doc_ID]" value="<?php echo isset($bat['doc_ID']) ? $bat['doc_ID'] : '' ?>">
                    <?php
                    } else {
                    ?>
                        <select name="bat[doc_ID]" required class="js_select2">
                            <option value="">Select Doctor</option>
                            <?php
                            if (empty($bat['doc_ID']) && isset($cli['cli_default_doc_ID']) && $cli['cli_default_doc_ID'] > 0) {
                                $bat['doc_ID'] = $cli['cli_default_doc_ID'];
                            }
                            foreach ((array)$doctors as $d) {
                                $selected = '';
                                if ((isset($bat['doc_ID']) && $d['doc_ID'] == $bat['doc_ID']) || count($doctors) == 1) {
                                    $selected = 'selected';
                                    $current_doctor_sign = $d['doc_signature'];
                                }
                                $doctor_display = tr_trim_name($d['doc_last'] . ' ' . $d['doc_first']);
                                if ($show_npi && !empty($d['doc_npi'])) {
                                    $doctor_display .= ' - ' . $d['doc_npi'];
                                }

                                echo '<option value="' . $d['doc_ID'] . '" ' . $selected . ' data-signature="' . $d['doc_signature'] . '" >' . $doctor_display . '</option>';
                            }
                            ?>
                        </select>
                    <?php
                    }
                    ?>


                </div>
                <div class="col4">
                    <label>Collector Name</label>
                    <?php if (($collectors && count($collectors) > 0 && $current_role != 'collector')
                        && !tr_get_option('ci_pdf_data', 'allow_type_col', false)
                        && !tr_get_option('ci_pdf_data', 'discreatedcol')
                    ) { ?>
                        <select name="col[col_ID]" id="col_id_sel" class="js_select2">
                            <?php
                            foreach ($collectors as $collector) {
                                $selected = $collector['col_ID'] == $bat['col_ID'] ? 'selected' : '';
                                echo '<option value="' . $collector['col_ID'] . '" ' . $selected . ' data-phone="' . $collector['col_phone'] . '">' . tr_trim_name(@$collector['col_first'] . ' ' . @$collector['col_last']) . '</option>';
                            }
                            $collector['col_phone'] = $collectors[0]['col_phone'];
                            ?>
                        </select>
                    <?php } else {
                        if (!$collector) {
                            $collector = isset($collectors[0]) ? $collectors[0] : array('col_first' => '', 'col_last' => '');
                        }
                        $collector_name = tr_trim_name($collector['col_first'] . ' ' . @$collector['col_last']);
                        if (empty($collector_name) && tr_get_option('ci_pdf_data', 'discreatedcol')) {
                            $author_id = $bat['author_ID'] > 0 || $bat['mid'] > 0 ? $bat['author_ID'] : get_current_user_id();

                            if ($author_id > 0) {
                                $collector_name = get_user_meta($author_id, 'first_name', true) . ' ' .
                                    get_user_meta($author_id, 'last_name', true);
                                $collector['col_ID'] = -1;
                            }
                        }
                    ?>
                        <input type="text" class="<?php if ($current_role != 'collector') echo 'col_auto' ?>" name="col[col_name]" value="<?php echo $collector_name ?>" />
                        <input type="hidden" name="col[col_ID]" value="<?php echo isset($collector['col_ID']) ? $collector['col_ID'] : '' ?>" />
                    <?php }; ?>
                    <label>Collector Phone Number</label>
                    <input type="text" class="has_phone" name="col[col_phone]" value="<?php echo isset($collector['col_phone']) ? $collector['col_phone'] : '' ?>" <?php if (!empty($collector['col_phone'])) echo 'readonly' ?> />
                </div>
                <div class="col4">
                    <label>Date of Collection</label>
                    <input type="text" class="hasdatetime <?php if (tr_get_option('ci_pdf_data', 'limit_doc')) echo 'limitfuture' ?>" <?php if (tr_current_role_in(array('administrator', 'AdminCIDW', 'administrator_p', 'lab', 'labsecman', 'leadlabsecman', 'lab_manager', 'lab_sec', 'lab_tech'))) echo 'data-write="true"' ?> name="bat[doc]" value="<?php echo empty($bat['doc']) && $act != 'print' && !$settings['disable_auto_doc'] ? 'auto' : $bat['doc'] ?>" <?php if (!empty($bat['doc'])) echo 'readonly' ?> required="" />

                    <?php
                    if ($settings['show_temperature']) {
                        echo '<label class="inlineblock">Temperature <input type="text" name="bat[temperature]" value="' . $bat['temperature'] . '" style="width:40px;height:26px" class="center" maxlength="10" /> <sup>o</sup>C</labe>';
                    }

                    if (tr_get_option('ci_pdf_data', 'show_basic_demographics')) { ?>
                        <div class="frow" style="margin-bottom:5px;margin-top:15px">
                            <h4 style="margin:0">BASIC DEMOGRAPHICS</h4>
                        </div>
                        <div>
                            <span style="display: inline-block;width:50px">Name: </span> <span id="patient_name" style="font-size:15px"><?php echo (isset($pa['pa_last']) ? $pa['pa_last'] : '') . ', ' . (isset($pa['pa_first']) ? $pa['pa_first'] : '') ?></span>
                        </div>
                        <div style="margin-top:5px">
                            <span style="display: inline-block;width:50px">DOB:</span> <span id="patient_dob" style="font-size:15px"><?php echo isset($pa['pa_dob']) ? $pa['pa_dob'] : '' ?></span>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="frow">
                <?php if (tr_get_option('ci_pdf_data', 'show_pregnant')) { ?>
                    <div class="col4">
                        <label>Pregnant</label>
                        <?php tr_select_option('bat[p_pregnant]', array('' => 'Select', 'Y' => 'Yes', 'N' => 'No'), $bat['p_pregnant']) ?>
                    </div>
                <?php } ?>
                <?php if (tr_get_option('ci_pdf_data', 'show_fin')) { ?>
                    <div class="col4">
                        <label>FIN</label>
                        <input type="text" maxlength="50" name="bat[p_fin]" value="<?php echo isset($bat['p_fin']) ? $bat['p_fin'] : '' ?>" />
                    </div>
                <?php } ?>
                <?php if (tr_get_option('ci_pdf_data', 'show_ehr')) { ?>
                    <div class="col4">
                        <label>EHR</label>
                        <input type="text" maxlength="50" name="bat[p_ehr]" value="<?php echo isset($bat['p_ehr']) ? $bat['p_ehr'] : '' ?>" />
                    </div>
                <?php } ?>
            </div>

            <?php
            if (tr_get_option('ci_pdf_data', 'show_bnt_file_pa_ins')) {
            ?>
                <div class="frow">
                    <h4>Attach Face Sheet and Attach Demographics</h4>
                </div>
                <div class="frow">
                    <div class="col2">
                        <div class="card_file">
                            <a class="button selectfile" href="#">Choose Attach Face Sheet</a>
                            <input type="file" name="attach_face_sheet" />
                            <input type="hidden" id="attach_face_sheet" name="attach_face_sheet" value="<?php echo isset($bat['attach_face_sheet']) ? $bat['attach_face_sheet'] : '' ?>" />
                            <?php if (!empty($bat['attach_face_sheet'])) {
                                $original_req = $card_dir['url'] . '/' . $bat['attach_face_sheet'] . '?' . time();
                            ?>
                                <span><a href="<?php echo $original_req ?>" class="button view_card_file" target="_blank">View Attach Face Sheet</a></span>
                                <a class="button remove">Remove</a>
                            <?php
                            } else {
                                echo '<span></span>';
                            } ?>
                        </div>
                    </div>
                    <div class="col2">
                        <div class="card_file">
                            <a class="button selectfile" href="#">Choose Attach Demographics</a>
                            <input type="file" name="attach_demographics" />
                            <input type="hidden" id="attach_demographics" name="attach_demographics" value="<?php echo isset($bat['attach_demographics']) ? $bat['attach_demographics'] : '' ?>" />
                            <?php if (!empty($bat['attach_demographics'])) {
                                $original_req = $card_dir['url'] . '/' . $bat['attach_demographics'] . '?' . time();
                            ?>
                                <span><a href="<?php echo $original_req ?>" class="button view_card_file" target="_blank">View Attach Demographics</a></span>
                                <a class="button remove">Remove</a>
                            <?php
                            } else {
                                echo '<span></span>';
                            } ?>
                        </div>
                    </div>
                </div>
            <?php
            }
            ?>

            <div class="frow">
                <h4>Patient Information</h4>
            </div>

            <input type="hidden" name="pa[pa_ID]" value="<?php echo isset($pa['pa_ID']) ? $pa['pa_ID'] : '' ?>" />
            <div class="patient_block">
                <div class="frow blabel">
                    <?php if (!tr_get_option('ci_pdf_data', 'disable_ssn')) { ?>
                        <div class="col4">

                            <label>Social Security Number </label>
                            <input type="text" name="pa[pa_ssn]" id="pa_ssn" maxlength="11" value="<?php echo isset($pa['pa_ssn']) ? ($act == 'print' ? $pa['pa_ssn'] . ' ' : $pa['pa_ssn']) : '' ?>" <?php if (tr_get_option('ci_pdf_data', 'required_ssn')) echo 'required' ?> <?php if ($act != 'print') echo 'style="width:76%"' ?> />
                            <?php if ($act != 'print' && $act != 'view') : ?>
                                <a class="button find_patient samefheight" href="#" data-type="ssn">find</a>
                            <?php endif; ?>
                        </div>
                    <?php } else if (tr_get_option('ci_pdf_data', 'show_symtomatic')) {
                    ?>
                        <div class="col4">
                            <label>Is patient Symptomatic?</label>
                            <?php tr_select_option('pa[pa_symptomatic]', array('' => 'Select', 'Y' => 'Yes', 'N' => 'No'), isset($pa['pa_symptomatic']) ? $pa['pa_symptomatic'] : '', array('attr' => ' ')) ?>
                        </div>
                    <?php
                    } ?>
                    <div class="col4">
                        <input type="hidden" name="bat[is_employee]" value="0" />
                        <label>Last Name <span class="right">Employee <input type="checkbox" name="bat[is_employee]" value="1" <?php checked(isset($bat['is_employee']) ? $bat['is_employee'] : 0) ?> /> </span></label>
                        <input type="text" class="cfname requesting_patients_last_auto" name="pa[pa_last]" autocomplete="none" value="<?php echo isset($pa['pa_last']) ? $pa['pa_last'] : '' ?>" <?php if ($notallowchangepatient && isset($pa['pa_last']) && !empty($pa['pa_last']) && $saved_id > 0) echo 'readonly' ?> required="" />
                    </div>
                    <div class="col4">
                        <label>First Name</label>
                        <input type="text" class="cfname" name="pa[pa_first]" value="<?php echo isset($pa['pa_first']) ? $pa['pa_first'] : '' ?>" <?php if ($notallowchangepatient && !empty($pa['pa_first']) && $saved_id > 0) echo 'readonly' ?> required="" />
                    </div>
                    <div class="col4">
                        <label>Middle Initial</label>
                        <input type="text" class="cfname" name="pa[pa_middle]" value="<?php echo isset($pa['pa_middle']) ? $pa['pa_middle'] : '' ?>" <?php if (tr_get_option('ci_pdf_data', 'pa_mid_required')) echo 'required' ?> />
                    </div>
                </div>

                <div class="frow blabel">
                    <div class="col4">
                        <label>Medical Record Number</label>
                        <input type="text" id="med_number" name="pa[pa_mrn]" value="<?php echo isset($pa['pa_mrn']) ? $pa['pa_mrn'] : '' ?>" maxlength="15" <?php
                                                                                                                                                            if ($act != 'print') echo ' style="width:76%" ';
                                                                                                                                                            if (tr_get_option('ci_pdf_data', 'mrn_required')) echo ' required ';
                                                                                                                                                            ?> />
                        <?php if ($act != 'print' && $act != 'view') : ?>
                            <a class="button find_patient samefheight" href="#" data-type="med_number">find</a>
                        <?php endif; ?>
                    </div>
                    <div class="col4">
                        <label>Street Address <span class="right">NH <input type="checkbox" class="nh_address" /> </span> </label>
                        <input type="text" name="pa[pa_address]" id="pa_address" value="<?php echo isset($pa['pa_address']) ? $pa['pa_address'] : '' ?>" <?php if (!tr_get_option('ci_pdf_data', 'pa_address_notrequired')) echo 'required' ?> maxlength="150" />
                    </div>
                    <?php if (tr_get_option('ci_pdf_data', 'enable_patient_room') && !tr_get_option('ci_pdf_data', 'enable_patient_unit_room_bed')) { ?>
                        <div class="col12">
                            <label>Room</label>
                            <input type="text" name="pa[pa_room]" value="<?php echo isset($pa['pa_room']) ? $pa['pa_room'] : '' ?>" />
                        </div>
                    <?php } ?>
                    <div class="<?php echo tr_get_option('ci_pdf_data', 'enable_patient_room') && !tr_get_option('ci_pdf_data', 'enable_patient_unit_room_bed') ? 'col6' : 'col4' ?>">
                        <label>City</label>
                        <input type="text" name="pa[pa_city]" id="pa_city" value="<?php echo isset($pa['pa_city']) ? $pa['pa_city'] : '' ?>" <?php if (!tr_get_option('ci_pdf_data', 'pa_address_notrequired')) echo 'required' ?> maxlength="100" />
                    </div>
                    <div class="col4">
                        <div class=" blabel frow">
                            <div class="col2">
                                <label>State</label>
                                <?php tr_select_option('pa[pa_state]', tr_get_states(), isset($pa['pa_state']) ? $pa['pa_state'] : '', array('attr' => !tr_get_option('ci_pdf_data', 'pa_address_notrequired') ? 'required' : '')) ?>
                            </div>
                            <div class="col2">
                                <label>Zip</label>
                                <input type="text" name="pa[pa_zip]" id="pa_zip" value="<?php echo isset($pa['pa_zip']) ? $pa['pa_zip'] : '' ?>" <?php if (!tr_get_option('ci_pdf_data', 'pa_address_notrequired')) echo 'required' ?> maxlength="12" />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="frow">
                    <div class="col4">
                        <label>Gender</label>
                        <?php tr_select_option('pa[pa_gender]', trpdd_genders(), isset($pa['pa_gender']) ? $pa['pa_gender'] : '', array('attr' => 'required="" ')) ?>
                    </div>
                    <div class="col4">
                        <label>DOB</label>
                        <input type="text" class="<?php if ((!$notallowchangepatientdob || empty($pa['pa_dob'])) && $saved_id == 0) echo 'hasdate' ?> birthday limitfuture" name="pa[pa_dob]" value="<?php echo isset($pa['pa_dob']) ? $pa['pa_dob'] : '' ?>" <?php if ($notallowchangepatientdob && !empty($pa['pa_dob'])) echo 'readonly' ?> required="" />
                    </div>
                    <div class="col4">
                        <label>Phone Number</label>
                        <input type="text" class="has_phone" name="pa[pa_phone]" value="<?php echo isset($pa['pa_phone']) ? $pa['pa_phone'] : '' ?>" <?php if (!tr_get_option('ci_pdf_data', 'pa_phone_notrequired')) echo 'required' ?> maxlength="18" />
                    </div>
                    <div class="col4">
                        <label>Email</label>
                        <input type="email" name="pa[pa_email]" value="<?php echo isset($pa['pa_email']) ? $pa['pa_email'] : '' ?>" <?php if (tr_get_option('ci_pdf_data', 'pa_email_required')) echo 'required' ?> />
                    </div>
                </div>
                <div class="frow">
                    <?php
                    $count_colinrow = 0;
                    if (tr_get_option('ci_pdf_data', 'show_symtomatic') && !tr_get_option('ci_pdf_data', 'disable_ssn')) {
                        $count_colinrow++;
                    ?>
                        <div class="col4">
                            <label>Is patient Symptomatic?</label>
                            <?php tr_select_option('pa[pa_symptomatic]', array('' => 'Select', 'Y' => 'Yes', 'N' => 'No'), isset($pa['pa_symptomatic']) ? $pa['pa_symptomatic'] : '', array('attr' => ' ')) ?>
                        </div>
                    <?php } ?>

                    <?php if (tr_get_option('ci_pdf_data', 'show_patient_passport')) {
                        $count_colinrow++;
                    ?>
                        <div class="col4">
                            <label>Passport Number</label>
                            <input type="text" name="pa[pa_passport]" value="<?php echo isset($pa['pa_passport']) ? $pa['pa_passport'] : '' ?>" maxlength="100" />
                        </div>
                    <?php } ?>

                    <?php
                    $count_colinrow++;
                    if ($count_colinrow > 1 && $count_colinrow % 4 == 1) {
                        $count_colinrow = 0;
                        echo '</div><div class="frow">';
                    }
                    ?>
                    <div class="col4">
                        <label>Race</label>
                        <?php
                        $races = array(
                            '' => 'Select',
                            '1002-5' => 'American Indian or Alaska  Native',
                            '2028-9' => 'Asian',
                            '2054-5' => 'Black / African American',
                            '2076-8' => 'Native Hawaiian or Other  Pacific Islander',
                            '2106-3' => 'White',
                            '2131-1' => 'Other'
                        );
                        tr_select_option('pa[pa_race]', $races, isset($pa['pa_race']) ? $pa['pa_race'] : '');
                        ?>
                    </div>
                    <?php
                    $count_colinrow++;
                    if ($count_colinrow > 1 && $count_colinrow % 4 == 1) {
                        $count_colinrow = 0;
                        echo '</div><div class="frow">';
                    }
                    ?>
                    <div class="col4">
                        <label>Ethnicity</label>
                        <?php
                        $Ethnicitis = array(
                            '' => 'Select',
                            'H' => 'Hispanic or Latino',
                            'N' => 'Not Hispanic or Latino',
                            'U' => 'Unknown'
                        );
                        tr_select_option('pa[pa_ethnicity]', $Ethnicitis, isset($pa['pa_ethnicity']) ? $pa['pa_ethnicity'] : '');
                        ?>
                    </div>

                    <?php
                    if (tr_get_option('ci_pdf_data', 'show_patient_sogi')) {
                        $count_colinrow++;
                        if ($count_colinrow > 1 && $count_colinrow % 4 == 1) {
                            $count_colinrow = 0;
                            echo '</div><div class="frow">';
                        }

                    ?>
                        <div class="col4">
                            <label>Sexual Orientation</label>
                            <input type="text" name="pa[pa_sexual_oriention]" value="<?php echo isset($pa['pa_sexual_oriention']) ? $pa['pa_sexual_oriention'] : '' ?>" maxlength="100" />
                        </div>
                        <?php
                        $count_colinrow++;
                        if ($count_colinrow > 1 && $count_colinrow % 4 == 1) {
                            $count_colinrow = 0;
                            echo '</div><div class="frow">';
                        }
                        ?>
                        <div class="col4">
                            <label>Gender Identity</label>
                            <input type="text" name="pa[pa_gender_identity]" value="<?php echo isset($pa['pa_gender_identity']) ? $pa['pa_gender_identity'] : '' ?>" maxlength="100" />
                        </div>
                    <?php

                    }
                    if (isset($settings['show_pa_genotypes']) && $settings['show_pa_genotypes'] && !empty($settings['pa_genotypes'])) {
                        $count_colinrow++;
                        if ($count_colinrow > 1 && $count_colinrow % 4 == 1) {
                            $count_colinrow = 0;
                            echo '</div><div class="frow">';
                        }
                    ?>
                        <div class="col4">
                            <label>Genotype</label>
                            <?php
                            tr_select_option('pa[pa_genotypes]', $settings['pa_genotypes'], @$pa['pa_genotypes']);
                            ?>
                        </div>

                    <?php
                    }
                    ?>
                    <?php
                    if (tr_get_option('ci_pdf_data', 'pcr_patient_fvaccine')) {
                        if ($count_colinrow >= 4) {
                            $count_colinrow = 0;
                            echo '</div><div class="frow">';
                        }
                        $count_colinrow++;
                        echo '<div class="col4">';
                        echo '<label>Are you fully Vaccinated?</label>';
                        tr_select_option('pa[pa_fully_vaccinated]', array('' => 'Select', 'yes' => 'Yes', 'no' => 'No'), $pa['pa_fully_vaccinated']);
                        echo '</div>';
                    }
                    ?>
                </div>
                <?php if (tr_get_option('ci_pdf_data', 'show_patient_r3snh')) { ?>
                    <div class="frow">
                        <?php
                        $patient_types = array(
                            '' => 'None',
                            'SC' => 'Symptomatic',
                            'NH' => 'Nursing Home',
                            'HW' => 'Healthcare Worker'
                        );
                        tr_radio('pa[pa_patient_type]', $patient_types, $pa['pa_patient_type']);
                        ?>
                    </div>
                <?php } ?>
                <?php if (tr_get_option('ci_pdf_data', 'enable_patient_unit_room_bed')) { ?>
                <div class="frow">
                    <div class="col4">
                        <label>Unit</label>
                        <input type="text" name="pa[pa_unit]" value="<?php echo isset($pa['pa_unit']) ? $pa['pa_unit'] : '' ?>" />
                    </div>
                    <div class="col4">
                        <label>Room</label>
                        <input type="text" name="pa[pa_room]" value="<?php echo isset($pa['pa_room']) ? $pa['pa_room'] : '' ?>" />
                    </div>
                    <div class="col4">
                        <label>Bed</label>
                        <input type="text" name="pa[pa_bed]" value="<?php echo isset($pa['pa_bed']) ? $pa['pa_bed'] : '' ?>" />
                    </div>
                </div>
                <?php } ?>
            </div>

            <?php if (tr_get_option('ci_pdf_data', 'show_patient_county_pregnant')) { ?>
                <div class="frow">
                    <div class="col4">
                        <label>Pt County</label>
                        <input type="text" name="pa[pa_pt_county]" value="<?php echo isset($pa['pa_pt_county']) ? $pa['pa_pt_county'] : '' ?>" />
                    </div>
                    <div class="col4">
                        <label>Employed in Healthcare?</label>
                        <?php tr_select_option('pa[pa_employed_in_healthcar]', array(
                            ''  => 'Select',
                            'Y' => 'Yes',
                            'N' => 'No'
                        ), @$pa['pa_employed_in_healthcar'], array()) ?>

                    </div>
                    <div class="col4">
                        <label>Symptomatic per cdc?</label>
                        <?php tr_select_option('pa[pa_symptomatic]', array(
                            ''  => 'Select',
                            'Y' => 'Yes',
                            'N' => 'No'
                        ), @$pa['pa_symptomatic'], array()) ?>

                    </div>
                    <div class="col4">
                        <label>Date of Symptom Onset?</label>
                        <input type="text" class="hasdate" name="pa[pa_date_of_symptom]" placeholder="yyyy-mm-dd" value="<?php echo $pa['pa_date_of_symptom'] != '0000-00-00' ? $pa['pa_date_of_symptom'] : '' ?>" />
                    </div>
                </div>
                <div class="frow">
                    <div class="col4">
                        <label>Hospitalized in ICU?</label>
                        <?php tr_select_option('pa[pa_hospitalized_in_icu]', array(
                            ''  => 'Select',
                            'Y' => 'Yes',
                            'N' => 'No'
                        ), @$pa['pa_hospitalized_in_icu'], array()) ?>

                    </div>
                    <div class="col4">
                        <label>Resident in Congregate Care</label>
                        <?php tr_select_option('pa[pa_resident_in_congregate_care]', array(
                            ''  => 'Select',
                            'Y' => 'Yes',
                            'N' => 'No'
                        ), @$pa['pa_resident_in_congregate_care'], array()) ?>

                    </div>
                    <div class="col4">
                        <label>Pregnant?</label>
                        <?php tr_select_option('pa[pa_pregnant]', array(
                            ''  => 'Select',
                            'Y' => 'Yes',
                            'N' => 'No'
                        ), @$pa['pa_pregnant']) ?>

                    </div>
                </div>
            <?php } ?>

            <?php if (!tr_get_option('ci_pdf_data', 'hide_insurance')) { ?>
                <div class="frow">
                    <div class="col23">
                        <h4><label><input type="radio" name="bat[ins_comp]" value="ins" <?php checked('ins', @$bat['ins_comp']) ?> /> Primary Insurance Billing <span>(include copy of both sides of insurance card)</span></label></h4>
                    </div>
                    <div class="col13">
                        <?php if (tr_get_option('ci_req_settings_pcr', 'disable_workercomp')) {
                            echo '<h4>Secondary Insurance Billing</h4>';
                        } else {
                            echo ' <h4><label><input type="radio" name="bat[ins_comp]" value="comp" ' . checked('comp', @$bat['ins_comp'], false) . ' /> Worker\'s Comp and Auto Information</label></h4>';
                        } ?>

                    </div>
                </div>

                <div class="frow blabel">
                    <div class="col23 insurance_block1" id="use_required_ins">

                        <div class="frow">
                            <div class="col3">
                                <input type="hidden" name="no_insurance" value="" />
                                <label class="inline"><input type="checkbox" name="no_insurance" value="No Insurance - Cash" <?php checked(isset($bat['insurance']) ? $bat['insurance'] : '', 'No Insurance - Cash') ?> /> No Insurance - Cash</label>
                            </div>
                            <?php if (tr_get_option('ci_pdf_data', 'req_cares_act')) { ?>
                                <div class="col3">
                                    <input type="hidden" name="ins1[ins_cares_act]" value="" />
                                    <label class="inline"><input type="checkbox" name="ins1[ins_cares_act]" value="1" <?php checked(isset($ins1['ins_cares_act']) ? $ins1['ins_cares_act'] : '', '1') ?> /> No Insurance - CARES Act</label>
                                </div>
                            <?php } ?>
                            <?php if (tr_get_option('ci_req_settings_pcr', 'enable_client_bill')) { ?>
                                <div class="col3">
                                    <input type="hidden" name="client_bill" value="" />
                                    <label class="inline"><input type="checkbox" name="client_bill" value="Client bill" <?php checked(isset($bat['insurance']) ? $bat['insurance'] : '', "Client bill") ?> /> Client bill</label>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="frow">
                            <div class="col3">
                                <label>Last Name</label>
                                <input type="text" class="cfname has_required" name="ins1[ins_last]" value="<?php echo isset($ins1['ins_last']) ? $ins1['ins_last'] : '' ?>" <?php echo $required_insurance ?> />
                                <input type="hidden" name="ins1[ins_ID]" value="<?php echo isset($ins1['ins_ID']) ? $ins1['ins_ID'] : '' ?>" />
                            </div>
                            <div class="col3">
                                <label>First Name</label>
                                <input type="text" class="cfname has_required" name="ins1[ins_first]" value="<?php echo isset($ins1['ins_first']) ? $ins1['ins_first'] : '' ?>" <?php echo $required_insurance ?> />
                            </div>
                            <div class="col3 ilabel">
                                <label>Relation to Patient?</label>
                                <div>
                                    <?php if ($act == 'print') {
                                        echo '<span class="text">' . $ins1['ins_relation'] . '</span>';
                                    } else {
                                        tr_radio('ins1[ins_relation]', array('Self' => 'Self', 'Parent' => 'Parent', 'Spouse' => 'Spouse'), @$ins1['ins_relation'], array('attr' => $required_insurance));
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="col3" id="dob_ins1" <?php if (@$ins1['ins_relation'] == 'Self') echo 'style="display: none"' ?>>
                                <label><span>Parent</span> DOB</label>
                                <input type="text" class="hasdate birthday limitfuture <?php if ($required_insurance == '') echo 'notrequired' ?>" name="ins1[ins_dob]" value="<?php echo isset($ins1['ins_dob']) ? $ins1['ins_dob'] : '' ?>" />
                            </div>
                        </div>
                        <div class="frow">
                            <div class="col3">
                                <label>Insurance Company Name</label>
                                <?php if (tr_get_option('ci_pdf_data', 'dropdown_insurance')) { ?>
                                    <select name="ins1[ins_company]" class="js_select2" <?php echo $required_insurance ?>>
                                        <option value="">Select</option>
                                        <?php
                                        $insurances = $wpdb->get_results("select * from ci_payer_list order by payer_name asc");
                                        $has_ins_selected = false;
                                        foreach ($insurances as $ins) {
                                            $selected = $ins->payer_name == $ins1['ins_company'] ? 'selected' : '';
                                            if ($selected) $has_ins_selected = true;
                                            echo '<option value="' . $ins->payer_name . '" ' . $selected . '>' . $ins->payer_name . '</option>';
                                            if (!empty($ins->payer_name_search)) {
                                                echo '<option value="' . $ins->payer_name . '" ' . $selected . '>' . $ins->payer_name_search . ' (' . $ins->payer_name . ')</option>';
                                            }
                                        }
                                        if (!$has_ins_selected && !empty($ins1['ins_company'])) {
                                            echo '<option value="' . $ins1['ins_company'] . '" selected>' . $ins1['ins_company'] . '</option>';
                                        }
                                        ?>
                                    </select>
                                <?php } else { ?>
                                    <input type="text" class="cfname ins_company_auto" name="ins1[ins_company]" value="<?php echo isset($ins1['ins_company']) ? $ins1['ins_company'] : '' ?>" <?php echo $required_insurance ?> />
                                <?php } ?>
                            </div>
                            <div class="col3">
                                <label>Mnemonic</label>
                                <input type="text" name="ins1[ins_mnemonic]" value="<?php echo isset($ins1['ins_mnemonic']) ? $ins1['ins_mnemonic'] : '' ?>" readonly maxlength="30" />
                            </div>
                            <div class="col3">
                                <label>Email</label>
                                <input type="email" name="ins1[ins_email]" value="<?php echo isset($ins1['ins_email']) ? $ins1['ins_email'] : '' ?>" />
                            </div>
                        </div>
                        <?php
                        if (tr_get_option('ci_pdf_data', 'insurance_partab')) {
                        ?>
                            <div class="ilabel">
                                <input type="hidden" name="ins1[ins_part_A]" value="0" />
                                <label><input type="checkbox" name="ins1[ins_part_A]" value="1" <?php checked(isset($ins1['ins_part_A']) ? $ins1['ins_part_A'] : '') ?> /> Part A</label>
                                &nbsp;&nbsp;
                                <input type="hidden" name="ins1[ins_part_B]" value="0" />
                                <label><input type="checkbox" name="ins1[ins_part_B]" value="1" <?php checked(isset($ins1['ins_part_B']) ? $ins1['ins_part_B'] : '') ?> /> Part B</label>
                            </div>
                        <?php
                        }
                        ?>
                        <div class="frow">
                            <div class="col3">
                                <label>Street Address</label>
                                <input type="text" name="ins1[ins_address]" value="<?php echo isset($ins1['ins_address']) ? $ins1['ins_address'] : '' ?>" <?php echo $required_insurance_address ?> />
                            </div>
                            <div class="col3">
                                <label>City</label>
                                <input type="text" name="ins1[ins_city]" value="<?php echo isset($ins1['ins_city']) ? $ins1['ins_city'] : '' ?>" <?php echo $required_insurance_address ?> />
                            </div>
                            <div class="col3">
                                <div class="frow">
                                    <div class="col2">
                                        <label>State</label>
                                        <?php tr_select_option('ins1[ins_state]', tr_get_states(), @$ins1['ins_state'], array('attr' => $required_insurance_address)) ?>
                                    </div>
                                    <div class="col2">
                                        <label>Zip</label>
                                        <input type="text" name="ins1[ins_zip]" value="<?php echo isset($ins1['ins_zip']) ? $ins1['ins_zip'] : '' ?>" <?php echo $required_insurance_address ?> />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="frow">
                            <div class="col3">
                                <label>Insurance Phone</label>
                                <input type="text" class="has_phone" name="ins1[ins_phone]" value="<?php echo isset($ins1['ins_phone']) ? $ins1['ins_phone'] : '' ?>" />
                            </div>
                            <div class="col3">
                                <label>Member ID</label>
                                <input type="text" name="ins1[ins_member_id]" maxlength="24" value="<?php echo isset($ins1['ins_member_id']) ? $ins1['ins_member_id'] : '' ?>" <?php echo $required_insurance ?> />
                            </div>
                            <div class="col3">
                                <label>Group #</label>
                                <input type="text" name="ins1[ins_group_id]" maxlength="20" value="<?php echo isset($ins1['ins_group_id']) ? $ins1['ins_group_id'] : '' ?>" />
                            </div>
                        </div>
                        <div class="frow">
                            <?php if (!tr_get_option('ci_req_settings_pcr', 'hide_pre_authorization')) { ?>
                                <div class="col3">
                                    <label>Pre-authorization</label>
                                    <?php tr_yes_no('ins1[ins_authorization]', isset($ins1['ins_authorization']) ? $ins1['ins_authorization'] : '', array('attr' => $required_insurance)) ?>
                                </div>
                            <?php } ?>
                            <div class="col3">
                                <label>Authorization #</label>
                                <input type="text" name="ins1[ins_authorization_id]" value="<?php echo isset($ins1['ins_authorization_id']) ? $ins1['ins_authorization_id'] : '' ?>" />
                            </div>
                            <div class="col3">
                                <label>Authorization Date</label>
                                <input type="text" class="hasdate" name="ins1[ins_authorization_date]" value="<?php echo isset($ins1['ins_authorization_date']) ? $ins1['ins_authorization_date'] : '' ?>" />
                            </div>
                        </div>

                        <?php if (!tr_get_option('ci_req_settings_pcr', 'disable_workercomp')) {
                            echo '<h4>Secondary Insurance Billing <span>(include copy of both sides of insurance card)</span></h4>';
                        } else {
                            echo '</div><div class="col13 insurance_block2" >';
                        }
                        ?>
                        <div class="frow <?php echo tr_get_option('ci_req_settings_pcr', 'disable_workercomp') ? 'wrap2cols' : 'wrap3cols' ?>">
                            <div class="col3">
                                <label>Last Name</label>
                                <input type="text" class="cfname" name="ins2[ins_last]" value="<?php echo isset($ins2['ins_last']) ? $ins2['ins_last'] : '' ?>" />
                                <input type="hidden" name="ins2[ins_ID]" value="<?php echo isset($ins2['ins_ID']) ? $ins2['ins_ID'] : '' ?>" />
                            </div>
                            <div class="col3">
                                <label>First Name</label>
                                <input type="text" class="cfname" name="ins2[ins_first]" value="<?php echo isset($ins2['ins_first']) ? $ins2['ins_first'] : '' ?>" />
                            </div>
                            <div class="col3 ilabel">
                                <label>Relation to Patient?</label>
                                <div>
                                    <?php if ($act == 'print') {
                                        echo '<span class="text">' . (isset($ins2['ins_relation']) ? $ins2['ins_relation'] : '') . '</span>';
                                    } else {
                                        tr_radio('ins2[ins_relation]', array('Self' => 'Self', 'Parent' => 'Parent', 'Spouse' => 'Spouse'), isset($ins2['ins_relation']) ? $ins2['ins_relation'] : '');
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="col3">
                                <label>Insurance Company Name</label>
                                <input type="text" class="cfname ins_company_auto2" name="ins2[ins_company]" value="<?php echo isset($ins2['ins_company']) ? $ins2['ins_company'] : '' ?>" />
                            </div>
                            <div class="col3">
                                <label>Email</label>
                                <input type="email" name="ins2[ins_email]" value="<?php echo isset($ins2['ins_email']) ? $ins2['ins_email'] : '' ?>" />
                            </div>
                            <div class="col3" id="dob_ins2" <?php if (isset($ins2['ins_relation']) && $ins2['ins_relation'] == 'Self') echo 'style="display: none"' ?>>
                                <label><span>Parent</span> DOB</label>
                                <input type="text" class="hasdate birthday limitfuture" name="ins2[ins_dob]" value="<?php echo isset($ins2['ins_dob']) ? $ins2['ins_dob'] : '' ?>" />
                            </div>
                            <div class="col3">
                                <label>Insurance Phone</label>
                                <input type="text" class="has_phone" name="ins2[ins_phone]" value="<?php echo isset($ins2['ins_phone']) ? $ins2['ins_phone'] : '' ?>" />
                            </div>
                            <div class="col3">
                                <label>Member ID</label>
                                <input type="text" name="ins2[ins_member_id]" maxlength="24" value="<?php echo isset($ins2['ins_member_id']) ? $ins2['ins_member_id'] : '' ?>" />
                            </div>
                            <div class="col3">
                                <label>Group #</label>
                                <input type="text" name="ins2[ins_group_id]" value="<?php echo isset($ins2['ins_group_id']) ? $ins2['ins_group_id'] : '' ?>" />
                            </div>
                        </div>

                    </div>
                    <?php if (!tr_get_option('ci_req_settings_pcr', 'disable_workercomp')) { ?>
                        <div class="col13" id="use_required_com">
                            <div class="frow">
                                <div class="col2">
                                    <label>Subscriber Last Name</label>
                                    <input type="text" class="cfname" name="bat[subscriber_last]" value="<?php echo isset($bat['subscriber_last']) ? $bat['subscriber_last'] : '' ?>" data-required="true" />
                                </div>
                                <div class="col2">
                                    <label>Subscriber First Name</label>
                                    <input type="text" class="cfname" name="bat[subscriber_first]" value="<?php echo isset($bat['subscriber_first']) ? $bat['subscriber_first'] : '' ?>" data-required="true" />
                                </div>
                            </div>
                            <div class="frow">
                                <label>Date of Injury</label>
                                <input type="text" class="hasdate" name="bat[date_of_injury]" value="<?php echo isset($bat['date_of_injury']) ? $bat['date_of_injury'] : '' ?>" data-required="true" />
                            </div>
                            <div class="frow">
                                <div class="col23">
                                    <label>Address of Claim</label>
                                    <input type="text" name="bat[address_of_claim]" value="<?php echo isset($bat['address_of_claim']) ? $bat['address_of_claim'] : '' ?>" />
                                </div>
                                <div class="col13">
                                    <label>Zip Code</label>
                                    <input type="text" name="bat[subscriber_zip]" value="<?php echo isset($bat['subscriber_zip']) ? $bat['subscriber_zip'] : '' ?>" />
                                </div>
                            </div>
                            <div class="frow">
                                <div class="col23">
                                    <label>Insurance</label>
                                    <input type="text" class="cfname" name="bat[subscriber_insurance]" value="<?php echo isset($bat['subscriber_insurance']) ? $bat['subscriber_insurance'] : '' ?>" data-required="true" />
                                </div>
                                <div class="col13">
                                    <label>WC#</label>
                                    <input type="text" name="bat[subscriber_wc]" value="<?php echo isset($bat['subscriber_wc']) ? $bat['subscriber_wc'] : '' ?>" />
                                </div>
                            </div>
                            <div class="frow">
                                <div class="col23">
                                    <label>Attorney</label>
                                    <input type="text" name="bat[attorney_name]" value="<?php echo isset($bat['attorney_name']) ? $bat['attorney_name'] : '' ?>" />
                                </div>
                                <div class="col13">
                                    <label>Attorney Phone</label>
                                    <input type="text" class="has_phone" name="bat[attorney_phone]" value="<?php echo isset($bat['attorney_phone']) ? $bat['attorney_phone'] : '' ?>" />
                                </div>
                            </div>
                            <div class="frow">
                                <label>Adjuster Name</label>
                                <input type="text" class="cfname" name="bat[adjuster_name]" value="<?php echo isset($bat['adjuster_name']) ? $bat['adjuster_name'] : '' ?>" />
                            </div>
                            <div class="frow">
                                <label>Adjuster Phone Number</label>
                                <input type="text" class="has_phone" name="bat[adjuster_phone]" value="<?php echo isset($bat['adjuster_phone']) ? $bat['adjuster_phone'] : '' ?>" />
                            </div>
                            <div class="frow">
                                <label>Claim Number</label>
                                <input type="text" name="bat[claim_number]" value="<?php echo isset($bat['claim_number']) ? $bat['claim_number'] : '' ?>" data-required="true" />
                            </div>
                            <div class="frow">
                                <label>Claim under litigation?</label>
                                <?php tr_yes_no('bat[claim_under_litigation]', isset($bat['claim_under_litigation']) ? $bat['claim_under_litigation'] : ''); ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>

        </div>

        <?php if (tr_get_option('ci_pdf_data', 'pcr_card_images')) { ?>
            <div class="insidebreak">
                <div class="frow">
                    <h4>INSERT INSURANCE CARD AND PHOTO ID PICTURES</h4>
                </div>
                <?php if (!$billing_page) { ?>
                    <div class="frow">
                        By clicking the icon below, you will be prompted to insert a photo of both the patient current insurance card and photo ID. The photo
                        can be obtained directly from the computer (saved as a image) or a scanner.
                    </div>
                <?php } ?>
                <div class="frow blabel">
                    <?php
                    $col_num = (!$can_edit) ? 'col2' : 'col4';
                    ?>
                    <div class="<?php echo $col_num ?>">
                        <?php
                        $rq1        = false;
                        $card_link  = '';
                        if (!empty($bat['ins_card_front']) && file_exists($card_dir['path'] . '/' . $bat['ins_card_front']) && filesize($card_dir['path'] . '/' . $bat['ins_card_front']) > 0) {
                            $imageurl = $card_dir['url'] . '/' . $bat['ins_card_front'] . '?r=' . time();
                            if (stripos($bat['ins_card_front'], '.pdf') > 0) {
                                $imageurl = '?tr_control=ci_pdf_image&action=view_card_image&mid=' . $bat['mid'] . '&btype=pcr&field=ins_card_front&saved_id=' . $saved_id;
                            }
                            $card_link = '<a href="' . $imageurl . '" target=_blank><img src="' . $imageurl . '" style="width:100%"></a>';
                        }
                        if ($can_edit && (!isset($billing_page) || !$billing_page)) {
                        ?>
                            <div class="card_file">
                                <a class="button selectfile <?php if ($rq1) echo 'required' ?>">Choose Ins Card Front File</a>
                                <span class="block"><?php echo $card_link; ?></span>
                                <input type="file" <?php if ($rq1) echo 'data-required="1" required ' ?> name="ins_card_front" accept="image/*,application/pdf" />
                            </div>
                        <?php
                        } else {
                            echo '<label>Ins Card Front</label>';
                            echo $card_link;
                        }
                        ?>
                        <input type="hidden" name="img_ins_card_front" value="<?php echo isset($bat['ins_card_front']) ? $bat['ins_card_front'] : '' ?>" />
                    </div>
                    <div class="<?php echo $col_num ?>">
                        <?php
                        $card_link  = '';
                        if (!empty($bat['ins_card_back']) && file_exists($card_dir['path'] . '/' . $bat['ins_card_back']) && filesize($card_dir['path'] . '/' . $bat['ins_card_back']) > 0) {
                            $imageurl = $card_dir['url'] . '/' . $bat['ins_card_back'] . '?r=' . time();
                            if (stripos($bat['ins_card_back'], '.pdf') > 0) {
                                $imageurl = '?tr_control=ci_pdf_image&action=view_card_image&mid=' . $bat['mid'] . '&btype=pcr&field=ins_card_back&saved_id=' . $saved_id;
                            }
                            $card_link = '<a href="' . $imageurl . '" target=_blank><img src="' . $imageurl . '" style="width:100%"></a>';
                        }
                        if ($can_edit  && (!isset($billing_page) || !$billing_page)) {
                        ?>
                            <div class="card_file">
                                <a class="button selectfile" href="#">Choose Ins Card Back File</a>
                                <span><?php echo $card_link ?></span>
                                <input type="file" name="ins_card_back" accept="image/*,application/pdf" />
                            </div>
                        <?php
                        } else {
                            echo '<label>Ins Card Back</label>';
                            echo $card_link;
                        }
                        ?>
                        <input type="hidden" name="img_ins_card_back" value="<?php echo isset($bat['ins_card_back']) ? $bat['ins_card_back'] : '' ?>" />
                    </div>

                    <?php
                    if ($col_num == 'col2') {
                        echo '</div><div class="frow">';
                    }
                    ?>

                    <div class="<?php echo $col_num ?>">
                        <?php
                        $card_link  = '';
                        if (!empty($bat['patient_id_front']) && file_exists($card_dir['path'] . '/' . $bat['patient_id_front']) && filesize($card_dir['path'] . '/' . $bat['patient_id_front']) > 0) {
                            $imageurl = $card_dir['url'] . '/' . $bat['patient_id_front'] . '?r=' . time();
                            if (stripos($bat['patient_id_front'], '.pdf') > 0) {
                                $imageurl = '?tr_control=ci_pdf_image&action=view_card_image&mid=' . $bat['mid'] . '&btype=pcr&field=patient_id_front&saved_id=' . $saved_id;
                            }
                            $card_link = '<a href="' . $imageurl . '" target=_blank><img src="' . $imageurl . '" style="width:100%"></a>';
                        }
                        if ($can_edit && (!isset($billing_page) || !$billing_page)) {
                        ?>
                            <div class="card_file">
                                <a class="button selectfile" href="#">Choose Patient ID Front File</a>
                                <span><?php echo $card_link ?></span>
                                <input type="file" name="patient_id_front" />
                            </div>
                        <?php
                        } else {
                            echo '<label>Patient ID Front</label>';
                            echo $card_link;
                        }
                        ?>
                        <input type="hidden" name="img_patient_id_front" value="<?php echo isset($bat['patient_id_front']) ? $bat['patient_id_front'] : '' ?>" />
                    </div>
                    <div class="<?php echo $col_num ?>">
                        <?php
                        $card_link  = '';
                        if (!empty($bat['patient_id_back']) && file_exists($card_dir['path'] . '/' . $bat['patient_id_back']) && filesize($card_dir['path'] . '/' . $bat['patient_id_back']) > 0) {
                            $imageurl = $card_dir['url'] . '/' . $bat['patient_id_back'] . '?r=' . time();
                            if (stripos($bat['patient_id_back'], '.pdf') > 0) {
                                $imageurl = '?tr_control=ci_pdf_image&action=view_card_image&mid=' . $bat['mid'] . '&btype=pcr&field=patient_id_back&saved_id=' . $saved_id;
                            }
                            $card_link = '<a href="' . $imageurl . '" target=_blank><img src="' . $imageurl . '" style="width:100%"></a>';
                        }
                        if ($can_edit && (!isset($billing_page) || !$billing_page)) {
                        ?>
                            <div class="card_file">
                                <a class="button selectfile" href="#">Choose Patient ID Back File</a>
                                <span><?php echo $card_link ?></span>
                                <input type="file" name="patient_id_back" />
                            </div>
                        <?php
                        } else {
                            echo '<label>Patient ID Back</label>';
                            echo $card_link;
                        }
                        ?>
                        <input type="hidden" name="img_patient_id_back" value="<?php echo isset($bat['patient_id_back']) ? $bat['patient_id_back'] : '' ?>" />
                    </div>
                </div>
            </div>
        <?php } ?>

        <?php include('req_section_panels.php') ?>

        <?php if (!$billing_page || isset($_REQUEST['show_tests'])) { ?>

            <div class="frow">
                <div class="col2">
                    <h4>Diagnostic Codes</h4>
                    <div class="dagnostic_code " data-name="bat[dagnostic_codes][]" <?php if (tr_get_option('ci_pdf_data', 'pcr_diagnostic_required')) echo 'data-required="true"' ?>>
                        <ul id="list_dagnostic_codes">
                            <?php
                            if (is_array(@$bat['dagnostic_codes']) && count($bat['dagnostic_codes']) > 0) {
                                foreach ($bat['dagnostic_codes'] as $vl) {
                                    if (empty($vl)) continue;
                            ?>
                                    <li><input type="hidden" name="bat[dagnostic_codes][]" value="<?php echo $vl ?>" /> <?php echo $vl ?> <span></span></li>
                            <?php
                                }
                            }
                            ?>
                        </ul>
                        <?php if ($act != 'print' && $act != 'view') {
                            $codes = $wpdb->get_results("select `key`, `desc`, panelIDs
                                    from ci_dagnostic_codes where `type` = 'pcr' order by iorder desc");

                        ?>
                            <div class="frow">
                                <div class=" dagnostic_edit">
                                    <select data-codes="<?php echo esc_attr(json_encode($codes)) ?>" name="dagnostic_code_select" id="dagnostic_code_select"></select>

                                </div>
                                <div class="">
                                    <input type="text" id="dagnostic_code_text" placeholder="Enter Additional Codes" />
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                    <?php
                    echo tr_get_option('ci_req_settings_pcr', 'textafter_diagnostic_codes');
                    ?>
                </div>
                <?php if (tr_get_option('ci_req_settings_pcr', 'e_wound_sources')) {
                    $wound_sources = $wpdb->get_col("select te_name from ci_test_panels where te_type='wsso' order by te_position asc");
                    $selected_wound_sources = trpdd_get_batch_meta($bat['mid'], 'pcr', 'wound_sample_source');
                    if (!is_array($selected_wound_sources)) {
                        $selected_wound_sources = array();
                    }
                    $wound_panels_required = get_option('ci_wound_required_panels', array());
                ?>
                    <div class="col2">
                        <h4>Specimen Location/Source</h4>
                        <div class="wound_sample_source" data-name="batmeta[wound_sample_source][]">
                            <ul id="wound_sample_source_list" class="list_custom_codes">
                                <?php
                                if (is_array($selected_wound_sources) && count($selected_wound_sources) > 0) {
                                    foreach ($selected_wound_sources as $vl) {
                                        if (empty($vl)) continue;
                                ?>
                                        <li><input type="hidden" name="batmeta[wound_sample_source][]" value="<?php echo $vl ?>" /> <?php echo $vl ?> <span></span></li>
                                <?php
                                    }
                                }
                                ?>
                            </ul>
                            <?php if ($act != 'print' && $act != 'view') {
                            ?>
                                <div class="frow">
                                    <div class=" list_custom_edit">
                                        <select class="js_select2" id="wound_sample_source_select" data-required_panels="<?php echo implode(',', $wound_panels_required) ?>">
                                            <?php
                                            echo '<option value="">Select Source</option>';
                                            foreach ($wound_sources as $ws) {
                                                echo '<option value="' . $ws . '" >' . $ws . '</option>';
                                            }
                                            ?>
                                        </select>

                                    </div>
                                    <div class="">
                                        <input type="text" id="wound_sample_source_text" placeholder="Enter Additional Source" />
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>

            </div>

            <?php include('questions.php') ?>

        <?php } ?>

        <?php include('box_signature.php'); ?>



        <?php if (!tr_get_option('ci_pdf_data', 'hide_physicalnotes')) { ?>
            <div class="frow">
                <h4>NOTES</h4>
            </div>
            <div class="frow">
                <textarea name="bat[notes]" <?php if ($act != 'print') echo 'placeholder="Please enter any ' . (tr_get_option('ci_pdf_data', 'cht_physicalnotes') ? 'Chief Complaint' : 'Physician Notes') . '"' ?>><?php if (isset($bat['notes'])) echo @$bat['notes'] ?></textarea>
            </div>
        <?php } ?>

        <?php if (tr_get_option('ci_pdf_data', 'enable_patient_notes')) { ?>
            <div class="frow">
                <h4>PATIENT NOTES</h4>
            </div>
            <div class="frow">
                <textarea name="pa[pa_notes]" <?php if ($act != 'print') echo 'placeholder="Please enter Patient Notes"' ?>><?php if (isset($pa['pa_notes'])) echo @$pa['pa_notes'] ?></textarea>
            </div>
        <?php } ?>

        <?php if ($act != 'print') {
        ?>
            <div class="frow">
                <h4>NOTES (visible on reports)</h4>
            </div>
            <div class="frow">
                <textarea name="bat[notes_report]"><?php if (isset($bat['notes_report'])) echo @$bat['notes_report'] ?></textarea>
            </div>
        <?php
        } ?>


        <?php if ($billing_page) {
        ?>
            <div class="frow">
                <h4>Billing notes</h4>
            </div>
            <div class="frow">
                <textarea name="bat[billing_notes]" maxlength="200" <?php if ($act != 'print') echo 'placeholder="Please enter any Billing Notes"' ?>><?php if (isset($bat['billing_notes'])) echo @$bat['billing_notes'] ?></textarea>
            </div>
        <?php
        } else {
        ?>

            <div class="frow insidebreak">
                <div class="col2">
                    <label>Status</label>
                    <?php
                    $status_options = tr_pdd_get_batch_statuses_on_hold(tr_pdd_get_batch_statuses(false, false, false, 'pcr'), $bat['batch_status'], $bat['mid'], $table_batch, 'pcr');
                    $disabled_keys = tr_pdd_get_batch_statuses_disabled_keys('pcr', 'show_in_req_form', $bat['batch_status']);
                    tr_select_option('bat[batch_status]', $status_options, @$bat['batch_status'], ['disabled_options' => $disabled_keys])
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
        }

        ?>



        <?php if ($can_edit && $act != 'print') { ?>

            <?php if (!$billing_page) { ?>
                <div class="frow">
                    <h4>Upload</h4>
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
                                        $result_pdf = $result_dir['url'] . '/' . $f . '?t=' . time();
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
                        <?php trpdd_render_req_upload_files('original_req', isset($bat['original_req']) ? $bat['original_req'] : '', 'Upload Paper Req'); ?>
                    </div>
                    <div class="col3 notprint">
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


                <div class="frow">
                    <h4>&nbsp;</h4>
                </div>
                <?php if (tr_get_option('ci_pdf_data', 'show_pid_field')) { ?>
                    <div class="frow">
                        <span class="ilabel">
                            <label style="display:inline-block;width:120px"><?php echo tr_get_option('ci_pdf_data', 'title_pid_field', 'PID') ?></label>
                            <input type="text" style="width:300px" name="bat[oldreq]" value="<?php echo isset($bat['oldreq']) ? $bat['oldreq'] : '' ?>" <?php if (tr_get_option('ci_pdf_data', 'oldreq_required')) echo 'required' ?> />
                        </span>
                    </div>
                <?php } ?>
                <div class="frow row" style="margin-bottom:15px;">

                    <div class=" foutrow ilabel">
                        <?php if ($act != 'print' && $act != 'view' && !$billing_page) { ?>
                            <span id="show_assen">
                                <label>Accession Number</label>
                                <input type="text" id="number_assen" name="bat[assen]" value="<?php echo isset($bat['assen']) ? $bat['assen'] : '' ?>" <?php if ($assen_readonly) echo 'readonly' ?> required />
                                <label>Confirm</label>
                                <input type="text" id="confirm_assen" value="<?php echo isset($bat['assen']) ? $bat['assen'] : '' ?>" required />
                                <span class="confirm_status dashicons dashicons-yes"></span>
                                <a class="button print_assen_btn" style="<?php if (empty($bat['assen']) || $saved_id > 0) echo 'display:none' ?>" href="#">Print Barcode</a>
                            </span>
                            <?php if (empty($bat['assen'])) { ?>
                                <label id="show_auto_gennerated" style="margin-left:10px">
                                    <input type="checkbox" name="auto_generated" value="1" checked />
                                    <span> Auto Generated</span>
                                </label>
                            <?php } ?>

                        <?php } ?>

                    </div>
                    <div class="clear"></div>
                </div>
            <?php
            } else {

                $where_exist = " assen = '{$bat['assen']}' ";
                if (!empty($bat['claim_id'])) {
                    $where_exist .= " or claim_no = '{$bat['claim_id']}' ";
                }

                $bb = $wpdb->get_row("select * from ci_batch_billings where {$where_exist} ", ARRAY_A);

            ?>



                <div class="frow">
                    <div class="col3">
                        <label>Claim ID</label>
                        <input type="text" name="bb[claim_no]" value="<?php echo !empty($bb['claim_no']) ? $bb['claim_no'] : $bat['claim_id'] ?>" />
                    </div>
                    <div class="col3">
                        <label>Claim Date</label>
                        <input type="text" class="hasdate today" data-write="1" name="bb[claim_date]" value="<?php echo $bb['claim_date'] ?>" />
                    </div>

                </div>
                <div class="frow">
                    <div class="col3">
                        <label>Status</label>
                        <?php
                        tr_select_option('bb[bill_status]', $bill_statuses, @$bb['bill_status']) ?>
                    </div>
                    <div class="col3">
                        <label>Billed Amount</label>
                        <input type="text" name="bb[billed_amount]" value="<?php echo $bb['billed_amount'] ?>" />
                    </div>
                </div>
                <div id="billing_codes_div">
                    <div class="frow show_classes">
                        <h4>Billing Codes</h4>
                    </div>
                    <?php


                    if (isset($_REQUEST['debug'])) {
                        var_dump($bat['insurance']);
                        var_dump($ins1['ins_company']);
                    }

                    tr_bill_get_billing_code($ins1['ins_company'], $report['id'], $bat);
                    ?>
                </div>


                <?php
                if (!empty($bat['original_req'])) {
                    $result_dir = tr_get_result_upload_dir();
                    $original_req = $result_dir['url'] . '/' . $bat['original_req'];
                ?>
                    <div class="frow">
                        <h4>Paper Req</h4>
                        <div class="col3">
                            <span><a href="<?php echo $original_req ?>" class="original_req_view" target="_blank">Paper Req</a></span>
                        </div>
                    </div>
            <?php
                }
            }
            ?>


            <?php
            if (isset($settings['add_standing_order']) && $settings['add_standing_order'] && current_user_can('ci_standing_orders_pcr') && empty($mid)) {
            ?>
                <div class="checkbox_box">
                    <div class="frow">
                        <label><input type="checkbox" name="add_standing_order" value="1" /> Add Standing Order</label>
                    </div>
                    <div class="frow show_by_checkbox">
                        <div class="col4">
                            <label>Date Range:</label>
                            <input type="text" class="hasdate" data-rq="true" name="standing_start" />
                        </div>
                        <div class="col4">
                            <label>to: </label>
                            <input type="text" class="hasdate" data-rq="true" name="standing_end" />
                        </div>
                        <div class="col3">
                            <label>Frequency</label><br>
                            <input type="number" data-rq="true" name="standing_frequency" style="width:50%" /> in days
                        </div>
                    </div>
                </div>
            <?php
            }

            if (isset($settings['add_blood_pcr']) && $settings['add_blood_pcr'] && (!isset($by_type_id) || $by_type_id == 0)) {
            ?>
                <div class="frow">
                    <label><input type="checkbox" name="add_new_type" value="blood" /> Add Blood Tests</label>
                    <br><br>
                </div>
            <?php
            }

            include(trpdd_get_file_path('global', 'end_req_form'));
            ?>

            <?php do_action('end_form_pcr', $bat) ?>

            <div class="frow submitdiv">
                <input type="submit" class="button-primary" style="margin-right: 150px;" id="save_pcr_form" value="Submit">
                <input type="submit" class="button-primary" id="submit_print_btn" name="save_print" value="Print Barcode / Submit">
                <a class="iprint button bsty2" href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_print_req', 'id' => isset($bat['mid']) ? $bat['mid'] : '', 'type' => 'pcr', 'tab' => '')) ?>">Print Form</a>
                <?php if (isset($_REQUEST['page']) && $_REQUEST['page'] == 'ci_short_list') {
                ?>
                    <input type="hidden" name="bat[short_completed]" value="0">
                    <label><input type="checkbox" name="bat[short_completed]" value="1" <?php checked(@$bat['short_completed'], 1) ?>> Completed</label>
                <?php
                } ?>

                <?php if (!isset($mid) || $mid == 0 || $saved_id > 0) { ?>
                    <input type="submit" class="button only_save_form right" name="only_save_form" value="Save temporary">
                    <input type="hidden" name="saved_id" value="<?php echo $saved_id ?>" />
                <?php } ?>



                <input type="hidden" name="tr_action" value="<?php echo $can_edit ? 'ci_save_form_pcr' : '' ?>" />
                <input type="hidden" name="bat[is_dna]" value="16" />
                <?php if (isset($mid) && $mid > 0) { ?>
                    <input type="hidden" name="bat[mid]" value="<?php echo $mid ?>" />
                <?php } else {
                ?>
                    <input type="hidden" name="bat[batch_type]" value="P" />
                <?php
                } ?>
            </div>

        <?php
        } else if ($act == 'view') {
        ?>
            <br><br>
            <input type="hidden" id="number_assen" value="<?php echo isset($bat['assen']) ? $bat['assen'] : '' ?>" />
            <a class="button print_assen_btn" style="<?php if (empty($bat['assen'])) echo 'display:none' ?>" href="#">Print Barcode</a>
        <?php
        } ?>

        <input type="hidden" name="batch_tbl" value="<?php echo $table_batch ?>" />

        <?php trpdd_form_unique_id() ?>

    </form>
    <input type="hidden" id="position" value="<?php echo isset($bat['position']) ? $bat['position'] : '' ?>" />
</div>

<?php
if ($act == 'print') {
    if (!isset($print_canvas)) echo '<script>window.print();</script>';
} else {
?>

    <script>
        var $mid = <?php echo intval(isset($bat['mid']) ? $bat['mid'] : 0) ?>;
        var $alwayshowprintlb = <?php echo !tr_current_role_in(array('office_manager', 'doctor')) ? 'true' : 'false'; ?>;
        var $pa_required = false;
        var $sign_dir = '<?php echo $sign_dir['url'] ?>';
        var $t_assen = false;
        var $changebychild = false;
        var $onlyonepanel = <?php echo isset($settings['onlyonepanel']) ? intval($settings['onlyonepanel']) : 0 ?>;
        var $duplicate_req_checked = {};
        var $duplicate_req_checking = {};
        jQuery(function($) {

            function change_clinic_printbtn() {

                var $show_pbtn = $('select[name="bat[cli_ID]"]').length > 0 ? $('select[name="bat[cli_ID]"] option:selected') : $('[name="bat[cli_ID]"]');

                if ($alwayshowprintlb || $show_pbtn.data('pbtn') == '1') {
                    $('#submit_print_btn').show();
                } else {
                    $('#submit_print_btn').hide();
                }
            }

            function change_patient_insu_required($rq) {

                if ($rq) {
                    $('.patient_block .has_required').attr('required', true);
                    $('.insurance_block1 .has_required').attr('required', true);
                } else {
                    $('.patient_block :required, .insurance_block1 :required').each(function() {
                        $(this).addClass('has_required');
                        $(this).removeAttr('required');
                        $(this).removeAttr('aria-required');
                    })
                }
            }

            function tr_parse_related_group_ids($raw_ids) {
                if ($raw_ids === undefined || $raw_ids === null || $raw_ids === '') {
                    return [];
                }
                return String($raw_ids)
                    .split(',')
                    .map(function($id) {
                        return $.trim(String($id));
                    })
                    .filter(function($id) {
                        return $id !== '' && $id !== '0';
                    });
            }

            function tr_related_ids_intersect($ids1, $ids2) {
                for (var i = 0; i < $ids1.length; i++) {
                    if ($ids2.includes($ids1[i])) {
                        return true;
                    }
                }
                return false;
            }

            function tr_get_panel_related_ids($panel) {
                var $panel_group = $panel.parents('.groupparent');
                var $ids = tr_parse_related_group_ids($panel.data('relate_to'));
                var $classes = String($panel_group.attr('class') || '').split(/\s+/);
                for (var i = 0; i < $classes.length; i++) {
                    if ($classes[i].indexOf('relate_to_group_') === 0) {
                        var $class_ids = tr_parse_related_group_ids($classes[i].replace('relate_to_group_', ''));
                        for (var j = 0; j < $class_ids.length; j++) {
                            $ids.push($class_ids[j]);
                        }
                    } else if ($classes[i].indexOf('parent_group_') === 0) {
                        var $parent_ids = tr_parse_related_group_ids($classes[i].replace('parent_group_', ''));
                        for (var k = 0; k < $parent_ids.length; k++) {
                            $ids.push($parent_ids[k]);
                        }
                    }
                }
                return $ids.filter(function($id, $index, $all_ids) {
                    return $id !== '' && $all_ids.indexOf($id) === $index;
                });
            }

            function tr_get_panel_relation_keys($panel) {
                var $ids = tr_get_panel_related_ids($panel);
                $ids.push($panel.val().replace('GROUP_', ''));
                return $ids.filter(function($id, $index, $all_ids) {
                    return $id !== '' && $all_ids.indexOf($id) === $index;
                });
            }

            function tr_get_related_component_ids($panel) {
                var $component_ids = tr_get_panel_relation_keys($panel);
                var $changed = true;

                while ($changed) {
                    $changed = false;
                    $('.batcustom_profile').each(function() {
                        var $panel_ids = tr_get_panel_relation_keys($(this));
                        if (!tr_related_ids_intersect($component_ids, $panel_ids)) {
                            return;
                        }
                        for (var i = 0; i < $panel_ids.length; i++) {
                            if (!$component_ids.includes($panel_ids[i])) {
                                $component_ids.push($panel_ids[i]);
                                $changed = true;
                            }
                        }
                    });
                }

                return $component_ids;
            }

            function tr_panels_in_same_related_group($panel1, $panel2) {
                return tr_related_ids_intersect(tr_get_related_component_ids($panel1), tr_get_panel_relation_keys($panel2));
            }

            function tr_set_direct_related_panels_checked($current, $checked) {
                var $related_ids = tr_get_panel_related_ids($current);
                $('.batcustom_profile').each(function() {
                    var $related_panel = $(this);
                    var $related_id = $related_panel.val().replace('GROUP_', '');
                    if ($related_panel.is($current) || !$related_ids.includes($related_id)) {
                        return;
                    }
                    if ($related_panel.is(':checked') == $checked) {
                        return;
                    }
                    $related_panel.data('byrelated', true);
                    $related_panel.prop('checked', $checked).change();
                });
            }

            function tr_has_other_active_panel($current) {
                var $has_other_panel = false;
                $('.batcustom_profile:checked').each(function() {
                    var $panel = $(this);
                    var $panel_group = $panel.parents('.groupparent');
                    if (tr_panels_in_same_related_group($current, $panel) ||
                        $panel_group.hasClass('notcactive') ||
                        $panel_group.hasClass('filter_hidden') ||
                        $panel_group.hasClass('disabled') ||
                        ($panel_group.hasClass('by_clinic') && !$panel_group.hasClass('cactive') && !$panel_group.hasClass('in_group'))) {
                        return;
                    }
                    if ($panel_group.is(':visible')) {
                        $has_other_panel = true;
                    }
                });
                return $has_other_panel;
            }

            function tr_check_duplicate_req_patient() {
                var $fname = $.trim($('[name="pa[pa_first]"]').val());
                var $lname = $.trim($('[name="pa[pa_last]"]').val());
                var $dob = $.trim($('[name="pa[pa_dob]"]').val());
                var $mid = $('[name="bat[mid]"]').val();
                var $check_key = [$fname.toLowerCase(), $lname.toLowerCase(), $dob].join('|');

                if ($fname == '' || $lname == '' || $dob == '' || ($mid && $mid != '') || $duplicate_req_checked[$check_key] || $duplicate_req_checking[$check_key]) {
                    return;
                }

                $duplicate_req_checking[$check_key] = true;
                $.ajax({
                    data: {
                        tr_action: "ci_check_number_assen",
                        btype: 'pcr',
                        fname: $fname,
                        lname: $lname,
                        dob: $dob,
                        mid: $mid
                    },
                    type: 'post',
                    dataType: 'json',
                    success: function(json) {
                        $duplicate_req_checked[$check_key] = true;
                        if (json.status == 'dublicate') {
                            if (!confirm("A requisition for this patient already exists. Do you want to create an additional one?")) {
                                $('[name*="pa[pa"]').each(function() {
                                    $(this).val('');
                                })
                            }
                        }
                    },
                    complete: function() {
                        delete $duplicate_req_checking[$check_key];
                    }
                });
            }

            function tr_build_diagnostic_codes() {
                //build diagnotic code
                var $panels = [];
                $('input.batcustom_profile:checked').each(function() {
                    $panels.push($(this).val().replace('GROUP_', ''));
                });

                var $dcobj = $('#dagnostic_code_select');
                try {
                    $codes = $dcobj.data('codes');
                    $html = '';
                    for (i in $codes) {
                        var $inpanel = false;
                        try {
                            if ($codes[i].panelIDs && $codes[i].panelIDs != '') {

                                var $pns = $codes[i].panelIDs.split(',');

                                for (i2 in $pns) {

                                    if ($panels.includes($pns[i2])) {
                                        $inpanel = true;
                                        break;
                                    }
                                }

                            }
                        } catch (e) {}

                        if ($inpanel) {
                            $html += '<option value="' + $codes[i].key + '">' + $codes[i].desc + '</option>';

                        }

                    }
                    if ($html != '') {
                        $html = '<option value="">Select</option>' + $html;
                    }
                    $dcobj.html($html).select2();
                } catch (e) {
                    console.log(e)
                }


            }

            function tr_wound_check_required() {
                $wound_sample_source_select = $('#wound_sample_source_select');
                if ($('#wound_sample_source_list li').length > 0) {
                    $wound_sample_source_select.removeAttr('required').change();
                    return;
                }
                var $panels = [];
                $('input.batcustom_profile:checked').each(function() {
                    $panels.push($(this).val().replace('GROUP_', ''));
                });
                try {

                    $requiredpanels = $wound_sample_source_select.data('required_panels');
                    if ($requiredpanels && $requiredpanels != '') {
                        $requiredpanels = $requiredpanels.split(',');
                        var $inpanel = false;
                        for (i in $requiredpanels) {
                            if ($panels.includes($requiredpanels[i])) {
                                $inpanel = true;
                                break;
                            }
                        }
                        if ($inpanel) {
                            $wound_sample_source_select.attr('required', true).change();
                        } else {
                            $wound_sample_source_select.removeAttr('required').change();
                        }
                    }

                } catch (e2) {}
            }


            $('body').on('change', '#number_assen', function() {
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
                                    $('#confirm_assen').val('');
                                    alert(rs.msg);
                                }

                                $('[type="submit"]').removeAttr('disabled');
                            },
                            error: function() {
                                setTimeout(function() {
                                    $t_assen = false;
                                    $this.change();
                                }, 1000);
                            }
                        })
                    }, 500);
                })
                .on('change', '[name="bat[cli_ID]"]', function() {
                    var $cli_id = $(this).val();
                    var $cli_opt = $('[name="bat[cli_ID]"] option:selected');
                    $('.requesting_patients_last_auto').autocomplete('option', 'source', ajaxurl + '?action=ajax&autocomplete=requesting_patients_last_auto&cli_id=' + $cli_id);
                    $('.requesting_patients_last_auto').attr('autocomplete', 'new-password');
                    if ($('.nh_address').is(':checked') && $(this).val() != '') {

                        $('[name="pa[pa_address]"]').val($cli_opt.data('address'));
                        $('[name="pa[pa_city]"]').val($cli_opt.data('city'));
                        $('[name="pa[pa_state]"]').val($cli_opt.data('state')).change();
                        $('[name="pa[pa_zip]"]').val($cli_opt.data('zip'));

                        if ($('[name="pa[pa_phone]"]').val() == '' && $cli_opt.data('phone')) {
                            $('[name="pa[pa_phone]"]').val($cli_opt.data('phone'));
                        }
                    }


                    change_clinic_printbtn();

                    <?php if (tr_get_option('ci_pdf_data', 'show_cbaa_clinic')) { ?>
                        if ($('#number_assen').val() == '') {
                            if ($('option:selected', $(this)).data('autoa')) {
                                $('[name="auto_generated"]').prop('checked', true);
                                $('#number_assen,#confirm_assen').removeAttr('required').attr('readonly', '1');
                            } else {
                                $('[name="auto_generated"]').prop('checked', false);
                                $('#number_assen,#confirm_assen').attr('required', 'required').removeAttr('readonly');
                            }
                        }
                    <?php } ?>

                    $('.groupparent.by_clinic:not(.in_group)').removeClass('cactive');
                    $('.groupparent.by_clinic.cli_id_' + $cli_id).addClass('cactive');
                    $('.groupparent.by_clinic:not(.cactive):not(.in_group) input[type="checkbox"]').prop('checked', false);

                    $('.groupparent.not_by_clinic').removeClass('notcactive');
                    $('.groupparent.not_by_clinic.not_cli_id_' + $cli_id + ':not(.in_group)').addClass('notcactive');
                    $('.groupparent.notcactive input[type="checkbox"]').prop('checked', false);
                    $('.filter_btn.active').trigger('apply_filter');

                    if ($('#p_condition').length > 0 && $('#p_condition').hasClass('showallcl') == false) {
                        if ($('option:selected', $(this)).data('reason')) {
                            $('#p_condition').show();
                        } else {
                            $('#p_condition').hide();
                        }
                    }


                })
                .on('change', '#attach_face_sheet, #attach_demographics', function() {
                    var $has_vl = $('#attach_face_sheet').val() != '' || $('#attach_demographics').val() != '';
                    if ($has_vl) {
                        change_patient_insu_required(false);
                    } else {
                        change_patient_insu_required(true);
                    }
                })
                .on('click change', '.groupparent .c input.cb_test', function() {
                    if ($(this).hasClass('readonly')) {
                        return false;
                    }
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
                .on('change', '.batcustom_profile', function() {
                    var $vl = $(this).val();
                    var $pr = $(this).parents('.groupparent');
                    if ($(this).hasClass('readonly')) {
                        return false;
                    }
                    if ($onlyonepanel && $(this).is(":checked")) {
                        var $has_other_panel = tr_has_other_active_panel($(this));
                        if ($(this).data('byrelated')) {
                            $has_other_panel = false;
                            $(this).data('byrelated', false);
                        }
                        if ($has_other_panel) {
                            alert('Only one panel can be selected');
                            $(this).prop('checked', false);
                            $('input.cb_test', $pr).prop('checked', false);
                            return false;
                        }
                    }
                    if ($(this).is(":checked")) {
                        if (!$changebychild) {
                            $('.c input', $pr).prop('checked', true);
                        }
                        $('.specimens', $pr).addClass('grequired');
                        $('.panel_specimen_source input', $pr).attr('required', 'required');
                        if ($('.specimens input[type="checkbox"]', $pr).length == 1) {
                            $('.specimens input[type="checkbox"]', $pr).prop('checked', true);
                        }

                    } else {
                        if (!$changebychild) {
                            $('.c input', $pr).removeAttr('checked');
                        }
                        $('.specimens', $pr).removeClass('grequired');
                        $('.specimens', $pr).removeClass('gerror');
                        $('.specimens input[type="checkbox"]', $pr).prop('checked', false);
                        $('.panel_specimen_source input', $pr).removeAttr('required');
                    }

                    var $pl = $('.groupparent.parent_' + $(this).val().toLowerCase() + ' .group_label input');

                    if ($(this).is(':checked')) {
                        if ($pl.length && !$pl.is(':checked')) {
                            $pl.data('byrelated', true);
                            $pl.prop('checked', true).change();
                        }
                        tr_set_direct_related_panels_checked($(this), true);

                    } else {
                        if ($pl.length && $pl.is(':checked')) {
                            $pl.prop('checked', false).change();
                        }
                        tr_set_direct_related_panels_checked($(this), false);
                    }
                    $changebychild = false;

                    tr_build_diagnostic_codes();
                    tr_wound_check_required();
                })
                .on('click', '.specimens input', function() {
                    if ($(this).is(':checked')) {
                        var $pr = $(this).parents('.groupparent');
                        if ($('input.batcustom_profile', $pr).is(':checked') == false) {
                            return false;
                        }
                    }
                })
                .on('submit', '#requisition_form', function() {
                    if ($('#confirm_assen').val() != $('[name="bat[assen]"]').val()) {
                        alert('Please Confirm Accession Number');
                        $('#confirm_assen').focus();
                        return false;
                    }
                    if ($('.panel_requests').length == 0 || $('#requisition_form').hasClass('only_save_form')) return;

                    var $spec_required = false;
                    $('.specimens.grequired').each(function() {
                        if ($('input:checked', $(this)).length == 0) {
                            $spec_required = true;
                            $('input', $(this)).focus();
                            $(this).addClass('gerror');
                        }
                    })

                    if ($('input.cb_test:checked').length == 0) {
                        alert('You must select least 1 test');
                        return false;
                    } else if ($spec_required) {
                        alert('Please select least one Specimen Type');
                        return false;
                    }

                })
                .on('keyup', '#confirm_assen,[name="bat[assen]"]', function() {
                    if ($('#confirm_assen').val() != '' && $('#confirm_assen').val() == $('[name="bat[assen]"]').val()) {
                        $('.confirm_status').addClass('confirmed');
                        $('.confirm_status').removeClass('error');
                        $('#print_assen_btn').show();
                    } else if ($('[name="bat[assen]"]').val() != '') {
                        $('.confirm_status').removeClass('confirmed');
                        $('.confirm_status').addClass('error');
                        $('#print_assen_btn').hide();
                    }
                })
                .on('click', '.only_save_form', function() {
                    $('form [required]').removeAttr('required');
                })
                .on('change', 'select#col_id_sel', function() {
                    var $phone = $('option[value="' + $(this).val() + '"]', $(this)).data('phone');
                    $('[name="col[col_phone]"]').val($phone);
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
                .on('change', '[name="bat[doc_ID]"]', function() {
                    var $pr = $('#provider_signdiv');
                    var $sign = $('option:selected', $(this));
                    $('.signimg img.bydoc', $pr).remove();
                    if ($('img', $pr).length == 0 && $sign && $sign.data('signature')) {
                        var $url = $sign_dir + '/' + $sign.data('signature');
                        $('.signimg', $pr).html('<img class="sign_img bydoc" src="' + $url + '"/>');
                        $('[name="img_provider_signature"]', $pr).val($sign.data('signature'));
                        $('.signature_canvas', $pr).hide();
                    } else {
                        $('.signature_canvas', $pr).show();
                    }
                })
                .on('click', '.nh_address', function() {
                    if ($(this).is(':checked') && $('[name="bat[cli_ID]"]').val() != '') {
                        var $cli_opt = $('[name="bat[cli_ID]"] option:selected');
                        $('[name="pa[pa_address]"]').val($cli_opt.data('address'));
                        $('[name="pa[pa_city]"]').val($cli_opt.data('city'));
                        $('[name="pa[pa_state]"]').val($cli_opt.data('state')).change();
                        $('[name="pa[pa_zip]"]').val($cli_opt.data('zip'));

                        if ($('[name="pa[pa_phone]"]').val() == '' && $cli_opt.data('phone')) {
                            $('[name="pa[pa_phone]"]').val($cli_opt.data('phone'));
                        }
                    }
                })
                .on('change', '[name="pa[pa_last]"]', function() {
                    if ($('[name="ins1[ins_relation]"][value="Self"]').is(":checked")) {
                        $('[name="ins1[ins_last]"]').val($('[name="pa[pa_last]"]').val());
                    }
                })
                .on('change', '[name="pa[pa_first]"]', function() {
                    if ($('[name="ins1[ins_relation]"][value="Self"]').is(":checked")) {
                        $('[name="ins1[ins_first]"]').val($('[name="pa[pa_first]"]').val());
                    }
                })
                .on('click', '[name="ins1[ins_relation]"]', function() {
                    $('[name="pa[pa_last]"]').change();
                    $('[name="pa[pa_first]"]').change();
                })
                .on('change', '[name="pa[pa_first]"], [name="pa[pa_last]"], [name="pa[pa_dob]"], [name="pa[pa_ID]"]', function() {
                    tr_check_duplicate_req_patient();
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
                            $('input.gua').prop('checked', true).change();
                            $('input.guti').prop('checked', true).change();
                            $('.boxguti .specimens input[name="bat[type_of_specimen][]"]').prop('checked', true);
                            $('input.gutiabx').prop('checked', true).change();
                        } else if ($vl == '-2') {
                            $('input.gua').prop('checked', true).change();
                            $('input.guti').prop('checked', true).change();
                            $('.boxguti .specimens input[name="bat[type_of_specimen][]"]').prop('checked', true);
                            $('input.gutiabx').prop('checked', false).change();
                        } else if ($vl == '-3') {
                            $('input.gua').prop('checked', true).change();
                            $('input.guti').prop('checked', false).change();
                            $('input.gutiabx').prop('checked', false).change();
                        }
                    }
                })
                .on('change', '.wound_sample_source select', function() {
                    if ($(this).val() == '') return;
                    var $pr = $(this).parents('.wound_sample_source');
                    var $vl = $(this).val();
                    var $html = '<li><input type="hidden" name="' + $pr.data('name') + '" value="' + $vl + '"/> ' + $vl + ' <span></span></li>';
                    $('ul', $pr).append($html);
                    $(this).val('').change();
                    tr_wound_check_required();
                })
                .on('keypress', '.wound_sample_source input', function(e) {
                    if (e.keyCode == 13) {
                        var $vl = $(this).val();
                        if ($vl == '') return false;
                        var $pr = $(this).parents('.wound_sample_source');
                        var $html = '<li><input type="hidden" name="' + $pr.data('name') + '" value="' + $vl + '"/> ' + $vl + ' <span></span></li>';
                        $('ul', $pr).append($html);
                        $(this).val('');
                        tr_wound_check_required();
                        return false;
                    }
                })
                .on('change', '.wound_sample_source input', function() {
                    var $vl = $(this).val();
                    if ($vl == '') return false;
                    var $pr = $(this).parents('.wound_sample_source');
                    var $html = '<li><input type="hidden" name="' + $pr.data('name') + '" value="' + $vl + '"/> ' + $vl + ' <span></span></li>';
                    $('ul', $pr).append($html);
                    $(this).val('');
                    tr_wound_check_required();
                }).on('click', '.wound_sample_source li span', function() {
                    var $pr = $(this).parents('.wound_sample_source');
                    $(this).parent().remove();
                    tr_wound_check_required();
                })

            $('#attach_face_sheet').change();

            $('.requesting_patients_last_auto').autocomplete({
                source: ajaxurl + '?action=ajax&autocomplete=requesting_patients_last_auto',
                minLength: 1,
                search: function(event, ui) {
                    var $cli_id = $('[name="bat[cli_ID]"]').val();

                    if ($cli_id == 0) {
                        return false;
                    } else {
                        $('.requesting_patients_last_auto').autocomplete('option', 'source', ajaxurl + '?action=ajax&autocomplete=requesting_patients_last_auto&cli_id=' + $cli_id);
                        $('.requesting_patients_last_auto').attr('autocomplete', 'new-password');
                    }
                },
                select: function(event, ui) {
                    if (ui.item) {
                        $('[name="pa[pa_ID]"]').val(ui.item.id);
                        for (k in ui.item.pa) {
                            $.tr_fill_value('[name="pa[' + k + ']"]', ui.item.pa[k]);
                        }
                        if (ui.item.ins1) {
                            for (k in ui.item.ins1) {
                                $.tr_fill_value('[name="ins1[' + k + ']"]', ui.item.ins1[k]);
                            }
                        }
                        if (ui.item.ins2) {
                            for (k in ui.item.ins2) {
                                $.tr_fill_value('[name="ins2[' + k + ']"]', ui.item.ins2[k]);
                            }
                        }
                        if (ui.item.images) {
                            for (k in ui.item.images) {
                                var vl = ui.item.images[k];
                                if (vl !== '') {
                                    var $obj = $('[name="img_' + k + '"]');
                                    var $pr = $obj.parents('.card_file');
                                    $obj.val(vl);
                                    $('span', $pr).html('<div><img src="/"><' + ui.item.card_url + '/' + vl + 'div>');
                                }
                            }
                        }
                        try {
                            $('a.find_patient').trigger("found_patient", [ui.item]);
                        } catch (e) {
                            console.log(e);
                        }
                    } else {
                        $('[name="pa[pa_ID]').val('');
                    }
                }
            });
            $('.requesting_patients_last_auto').attr('autocomplete', 'new-password');

            $('[name="bat[doc_ID]"]').change();
            $('[name="bat[assen]"]').change();
            if ($('[name="auto_generated"]').is(":checked")) {
                $('#number_assen,#confirm_assen').removeAttr('required').val('').attr('readonly', '1');

            }

            change_clinic_printbtn();
            tr_build_diagnostic_codes();
            if ($('[name="bat[cli_ID]"]').val() !== '') {
                $('[name="bat[cli_ID]"]').change();
            }

            $('.required_new').each(function() {
                if ($mid == 0) {
                    $(this).attr('required', 'required');
                } else {
                    $(this).prop('checked', true);
                }
            })

        })
    </script>
<?php
}
?>
