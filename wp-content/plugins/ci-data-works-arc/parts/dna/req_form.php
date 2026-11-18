<?php
global $wpdb;
global $current_role, $form_error;
global $ci_all_btypes;


$btype          = 'dna';
$table_batch    = 'ci_batches_dna';
$user_id        = get_current_user_id();
$current_doctor_sign     = '';
if (isset($_GET['debug'])) {
    ob_start();
}

$act = isset($_REQUEST['act']) ? $_REQUEST['act'] : @$act;

$tabs = array(
    '' => 'PGx Req Form',
    //'temp_batches' => 'Temp Batches',
    'current_batches' => 'Completed Req Forms',
    'saved_form' => 'Saved'
);


$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);
if (!isset($hide_tabs) || $hide_tabs === false) {
    if ($act == 'print') {
?>
        <link href="<?php echo TRCPDD_URL ?>css/print.min.css?<?php echo TRCPDD_VS ?>" media="all" type="text/css" rel='stylesheet' />
    <?php
    } else {
        include_once(TRCPDD_PATH . 'pages/formdata.php');

    ?>
        <h2 class="nav-tab-wrapper">
            <?php

            foreach ($tabs as $k => $txt) {
                $class = $tab == $k ? 'nav-tab-active' : '';
                $link = Tr_Base_Class_V6::link(array('tab' => $k, 'act' => '', 'id' => '', 'mid' => '', 's' => ''), false);
                echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
            }
            ?>
        </h2>

<?php

    }
}

$dagnostic_codes = array(
    '' => 'Please Select',
    'CYP2C19' => 'CYP2C19',
    'CYP2CD6' => 'CYP2CD6',
    'Cardiovascular' => array(
        'E78.0' => 'E78.0 Pure hypercholesterolemia',
        'E78.2' => 'E78.2 Mixed hyperlipidemia',
        'E78.5' => 'E78.5 Hyperlipidemia, unspecified',
        '114.91' => '114.91 Unspecified Atrial Fibrillation',
        '120.0' => '120.0 Unstable angina',
        '120.1' => '120.1 Angina pector iswith docum ented spasm',
        '120.8' => '120.8 Other forms of angina pectoris',
        '120.9' => '120.9 Angina pecto ris, unspecified',
        '121.29' => '121.29 5T elevation (STEMI) MI involving oth er sites',
        '121.3' => '121.3 ST elevation (STEMI) MI of unspecified sites',
        '121.4' => '121.4 Non -ST elevation (NSTEMI)MI',
        '124.0' => '124.0 Acute coronary thrombosis not resulti ng in MI',
        '124.1' => '124.1 Dressler\'s syndrome',
        '124.8' => '124.8 Other form s of acute ischemic heart disease'
    ),
    'Mental Health Major Depressive Affective Disorder Recurrent Episode' => array(
        'F33.9' => 'F33.9 Unspecified',
        'F33.0' => 'F33.0 Mild',
        'F33.1' => 'F33.1 Moderate',
        'F33.2' => 'F33.2 Severe wlo psychotic features',
        'F33.3' => 'F33.3 Severe wipsychotic features',
        'F33.41' => 'F33.41 In partial remission',
        'F33.42' => 'F33.42 In full remission',
    ),
    'Bipolar 1Disorder, Most Recent Episode (or Current) DEPRESSED' => array(
        'F31.30' => 'F31.30 Unspecified',
        'F31.31' => 'F31.31 Mild',
        'F31.32' => 'F31.32 Moderate',
        'F31.4' => 'F31.4 Severe wlo psychotic features',
        'F31.5' => 'F31.5 Severe, w/psvchotic features',
        'F31.75' => 'F31.75 In parti al remission',
        'F31.76' => 'F31.76 In full remission',
    ),
    'Bipolar I Disorder, Most Recent Episode (or Current) MIXED' => array(
        'F31.60' => 'F31.60 Unspecified',
        'F31.61' => 'F31.61 Mild',
        'F31.62' => 'F31.62 Moderate',
        'F31.63' => 'F31.63 Severe, wlo psychotic features',
        'F31.64' => 'F31.64 Severe, w/p svchonc features',
        'F31.77' => 'F31.77 In partial remission',
        'F31.78' => 'F31.78 In full remission',
        'G10' => 'G10 Huntington\'s disease',
    ),
    'DIAGNOSIS ICD-10 CODES (For all assays except CYP2D6 and CYP219) Cardiovascular' => array(
        '068.2' => '068.2 Hereditary deficiency of other clotting factors',
        '110' => '110 Essential (primary) hypertension',
        '125.9' => '125.9 Chronic ischemic heart disease, unspecified',
        '148.91' => '148.91 Unspecified at rial fibrillation',
        '150.9' => '150.9 Heart failure, unspecified',
        '182.91' => '182.91 Chronic embolism and thrombosis, unspecified vein',
        'R03.0' => 'R03.0 Elevated blood-pressure reading, wlo diagnosis of hypertension',
    ),
    'Digestive' => array(
        'K21.9' => 'K21.9 Gastro -esophageal reflux disease wlo esophagitis'
    ),
    'Endocrine' => array(
        'E03.9' => 'E03.9 Hypothyroidism, unspecified',
        'ElO.9' => 'ElO.9 Type 1 diabetes mellitus without complicaions',
        'Ell.9' => 'Ell.9 Type 2 diabetes mellitus without complications',
    ),
    'Mental Disorders' => array(
        'F32.9' => 'F32.9 Major depressive disorder, single episode, unspecified',
        'F41.9' => 'F41.9 Anxiety disorder, unspecified',
        'F90.9' => 'F90.9 Attention deficit hyperactivity disorder, unspecified Pain',
        'G89.18' => 'G89.18 Other acute post procedural pain',
        'G89.4' => 'G89.4 Chronic pain syndrome',
        'M12.9' => 'M12.9 Arthropathy, unspecified',
        'M15.9' => 'M15.9 Polyosteoarthritis, unspecified',
        'M19.90' => 'M19.90 Unspecified osteoarthritis, unspecified site',
        'M25.50' => 'M25.50 Pain in unspecified joint',
        'M25.569' => 'M25.569 Pain in unspecified knee',
        'M53.82' => 'M53.82 Other specified dorsopathies, cervical region',
        'M54.5' => 'M54.5 Low back pain',
        'M60.9' => 'M60.9 Myosit is, unspecified',
        'M79.1' => 'M79.1 Myalgia',
        'M79.609' => 'M79.609 Pain in unspecified limb',
        'M79.7' => 'M79.7 Fibromyalgia',
    ),
    'Other intervertebral disc degeneration' => array(
        'M51.34' => 'M51.34 Thoracic region',
        'M51.35' => 'M51.35 Thoracolumbar region',
        'M51.36' => 'M51.36 Lumbar region',
        'M51.37' => 'M51.37 Lumbosacral region',
    ),
    'Radiculopathy Signs & Symptoms' => array(
        'G93.3' => 'G93.3 Postviral fatigue syndrome',
        'ROO.2' => 'ROO.2 Palpitations',
        'R06.00' => 'R06.00 Dyspnea, unspecified',
        'R06.09' => 'R06.09 Other forms of dyspnea',
        'R11.2' => 'R11.2 Nausea with vomiting, unspecified',
        'R35.0' => 'R35.0 Frequency of micturition',
        'R51' => 'R51 Headache',
        'R53.1' => 'R53.1 Weakness',
        'R53.81' => 'R53.81 Other malaise',
        'R53.83' => 'R53.83 Other fatigue',
        'R60.0' => 'R60.0 Localized edema',
        'R60.1' => 'R60.1 Generalized edema',
    ),
    'Other' => array(
        'Z79.891' => 'Z79.891 Long term (current) use of opiate analgesic',
        'Z79.899' => 'Z79.899 Other long term (current ) drug therapy',
    )

);


$type_of_specimens = array(
    //'Urine' => 'Urine',
    'Blood' => 'Blood',
    'Oral'  => 'Oral',
    // 'Serum' => 'Serum',
    //'Other' => 'Other'
);

$current_medications = tr_pdd_get_current_medications();


$conditions = trpdd_get_batch_conditions(0, 'dna');
$edit_time  = $conditions['edit_time'];
$can_edit   = $conditions['can_edit'];
$can_delete = $conditions['can_delete'];
$can_hold   = $conditions['can_hold_req'];
$where          = '1 ' . $conditions['where'];
$where_cli  = $conditions['where_clinic'];
$where_doctor  = $conditions['where_doctor'];
if ($conditions['check_author']) {
    $where      .= $return['where_author'];
}

$cli        = array();
$collector  = array();
$current_lab = array();
$clinics    = array('' => 'Please Select');
$docs       = array('' => 'Please Select');

$cli_id     = 0;



$required_sign = true;
if (tr_current_role_in(array('administrator', 'AdminCIDW', 'labsecman', 'leadlabsecman', 'labmanager'))) {
    $required_sign = false;
}


if ($tab == 'current_batches') {
    include(trpdd_get_file_path('dna', 'req_list'));
    return;
} else if ($tab == 'saved_form') {
    if (!empty($act) && $act != 'delete') {
        $saved_id = $_REQUEST['id'];
    } else {
        include(trpdd_get_file_path('dna', 'req_saved'));
        return;
    }
} else if ($tab == 'prior_authorization') {
    include(trpdd_get_file_path('global', 'prior_authorization'));
    return;
} else if (!empty($tab)) {
    $tabfile = trpdd_get_file_path($btype, $tab);
    if (file_exists($tabfile)) {
        include($tabfile);
        return;
    }
}

if ($act == 'view' || $act == 'edit' || $act == 'print') {

    $action_form = '';
    $id = $_REQUEST['id'];
    $bat = $wpdb->get_row("select * from {$table_batch} where mid='{$id}' ", ARRAY_A);
    $cli['cID'] = $bat['cli_ID'];
    $cli_id = $cli['cID'];
    $cli = $wpdb->get_row("select * from ci_clinic where `cID`={$cli_id}", ARRAY_A);

    $pa = $wpdb->get_row("select pa.* from ci_patients pa
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

    $collector = $wpdb->get_row("select * from ci_collector where col_ID ={$bat['col_ID']}", ARRAY_A);

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

    $bat['str_panel_tests_requested'] = $bat['panel_tests_requested'];
    $bat['panel_tests_requested'] = json_decode($bat['panel_tests_requested'], true);
    if ($bat['panel_tests_requested'] == null) {
        $bat['panel_tests_requested'] = array();
        $arr = explode(',', $bat['str_panel_tests_requested']);

        foreach ($arr as $a) {
            $a = trim($a);
            if (!empty($a)) {
                $bat['panel_tests_requested'][$a] = array();
            }
        }
    }
    $bat['medication'] = maybe_unserialize(@$bat['medication']);


    $bat['current_medication'] = array_map('trim', $bat['current_medication']);
    $bat['current_medication_other'] = array_map('trim', $bat['current_medication_other']);
    //var_dump(@$bat);
    $batch_key = $bat['assen'];


    if ($act == 'edit') {
        $action_form = 'ci_save_dna';
        $mid = $id;
    }
} else {
    $bat['panel_tests_requested'] = array();
    $bat['custom_profile'] = '';
    $bat['medication'] = array();
    $bat['current_medication'] = array();
    $bat['poc_perform'] = '0';


    $action_form = 'ci_save_dna';

    if ($cli_id == 0) {
        $cli_id = $bat['cli_ID'] = $cli['cID'] = get_user_meta($user_id, '_use_current_clinic_id', true);
    }
}

if (isset($_POST['doc_id'])) {
    $doc_id = $_POST['doc_id'];
    $doc = $wpdb->get_row("select * from ci_doc where doc_ID = {$doc_id}");
    $cli_id = $doc->cli_ID;
    $bat['physician_phone'] = $doc->doc_phone;
    $bat['doc_ID'] = $doc_id;
} else if (isset($_POST['clinic_id'])) {
    $cli_id = $_POST['clinic_id'];
    $cli = $wpdb->get_row("select * from ci_clinic where `cID`={$cli_id}", ARRAY_A);
}

if (empty($bat['type_of_specimen'])) {
    $bat['type_of_specimen'] = 'Other';
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
$clinics = $wpdb->get_results($sql_clinic, ARRAY_A);
if (count($clinics) == 1) {
    $cli = $clinics[0];
    $cli_id = $bat['cli_ID'] = $cli['cID'];
}

if ($cli_id > 0) {
    $ds = $wpdb->get_results("select doc.* from ci_doc doc
                        JOIn ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
                        left JOIN ci_custom_providers cp ON doc.doc_ID = cp.doc_ID
                        where cd.cli_ID = {$cli_id}  {$where_doctor}
                        order by doc.doc_last,doc.doc_first asc");

    foreach ($ds as $doc) {
        $docs[$doc->doc_ID] = $doc->doc_last . ', ' . $doc->doc_first;
        if (count($ds) == 1) {
            $bat['doc_ID'] = $doc->doc_ID;
        }

        if (isset($bat['doc_ID']) && $doc->doc_ID == $bat['doc_ID']) {
            $current_doctor_sign = $doc->doc_signature;
        }
    }

    if ($current_role != 'collector') {
        $collectors = $wpdb->get_results("select col.* from ci_collector col
                                JOIN ci_clinic_collector cc ON cc.col_ID = col.col_ID
                                where cc.cli_ID ={$cli_id} and (col.col_status=1 or col.col_ID = '{$bat['col_ID']}')", ARRAY_A);
    }
}

if ($bat['lab_ID'] > 0) {
    $current_lab = $wpdb->get_row("select * from ci_labs Where lab_ID = {$bat['lab_ID']}", ARRAY_A);
} else {
    $current_lab = CI_Base_Lab::get_current_lab();
}



if (isset($_REQUEST['debug'])) {
    var_dump($sql_clinic);
    var_dump($current_lab);
}
?>
<div class="wrap_form wrap">

    <form method="post" enctype="multipart/form-data" class="need_validate" id="requisition_form">
        <div style="<?php if (isset($print_canvas)) echo 'min-height:1056px' ?>">
            <?php if ($act == 'print') : ?>
                <?php
                $upload_dir = tr_wp_upload_dir();
                $logo = $current_lab['lab_logo'];
                $logo_path = $upload_dir['basedir'] . '/logos/' . $logo;
                $logo_url = $upload_dir['baseurl'] . '/logos/' . $logo;
                ?>
                <div style="text-align: center">
                    <img src="<?php echo $logo_url ?>" align="center" style="max-width:400px;max-height:80px" />
                </div>
            <?php endif; ?>
            <h2 class="center">PGx Testing Requisition Form</h2>
            <?php Tr_Session_Class::show() ?>

            <?php if ($act == 'print' || $act == 'view') : ?>
                <div class="right foutrow">
                    <label>Accession Number</label>
                    <input type="text" id="number_assen" name="bat[assen]" value="<?php echo @$bat['assen'] ?>" required="" />
                </div>
            <?php endif; ?>

            <div class="frow blabel hlabel">
                <div class="col5">
                    <?php
                    $labs = CI_Base_Lab::get_user_labs();
                    if (count($labs) > 1) {
                        $labs_options = array('' => 'Select Lab');
                        foreach ($labs as $l) {
                            $labs_options[$l['lab_ID']] = $l['lab_dba'];
                        }
                    ?>
                        <label>Lab</label>
                        <?php tr_select_option('bat[lab_id]', $labs_options, $bat['lab_id'], array('attr' => 'required')) ?>
                    <?php
                    } ?>
                    <label>PRACTICE INFORMATION</label>
                    <?php
                    if (tr_current_role_in(array('lab_sec'))) {
                    ?>
                        <input type="text" class="cfname requesting_practice_auto" name="bat[requesting_practice]" value="<?php echo @$bat['requesting_practice'] ?>" required />
                        <input type="hidden" name="bat[cli_ID]" value="<?php echo @$bat['cli_ID'] ?>">
                    <?php
                    } else { ?>
                        <select name="bat[cli_ID]" required id="cli_change">
                            <option value="">Select Clinic</option>
                            <?php
                            foreach ($clinics as $clinic) {
                                $selected = $cli['cID'] == $clinic['cID'] ? 'selected' : '';
                                echo '<option value="' . $clinic['cID'] . '" ' . $selected . '>' . $clinic['cli_clinic'] . '</option>';
                            }
                            ?>
                        </select>
                    <?php } ?>
                </div>
                <div class="col5" id="cli_change_div">
                    <label>ORDERING PHYSICIAN</label>
                    <?php if (tr_current_role_in(array('lab_sec'))) {
                    ?>
                        <input type="text" class="requesting_physician_auto cfname" name="bat[requesting_physician]" value="<?php echo @$bat['requesting_physician'] ?>" required />
                        <input type="hidden" name="bat[doc_ID]" value="<?php echo @$bat['doc_ID'] ?>">
                    <?php
                    } else {
                        tr_select_option('bat[doc_ID]', $docs, @$bat['doc_ID'], array('attr' => 'required'));
                    }
                    ?>
                    <label>Collector Name</label>
                    <?php if (is_array($collectors) && count($collectors) > 0 && $current_role != 'collector') : ?>
                        <select name="col[col_ID]" id="col_id_sel">
                            <?php
                            foreach ($collectors as $collector) {
                                $selected = $collector['col_ID'] == $bat['col_ID'] ? 'selected' : '';
                                echo '<option value="' . $collector['col_ID'] . '" ' . $selected . ' data-phone="' . $collector['col_phone'] . '">' . tr_trim_name(@$collector['col_first'] . ' ' . @$collector['col_last']) . '</option>';
                            }
                            $collector['col_phone'] = $collectors[0]['col_phone'];
                            ?>
                        </select>
                    <?php else :
                        if (!$collector) {
                            $collector = $collectors[0];
                        }
                    ?>
                        <input type="text" class="col_auto" name="col[col_name]" value="<?php echo tr_trim_name(@$collector['col_first'] . ' ' . @$collector['col_last']) ?>" />
                        <input type="hidden" name="col[col_ID]" value="<?php echo @$collector['col_ID'] ?>" />
                    <?php endif; ?>
                    <label>Collector Phone Number</label>
                    <input type="text" class="has_phone" name="col[col_phone]" value="<?php echo @$collector['col_phone'] ?>" <?php if (!empty($collector['col_phone'])) echo 'readonly' ?> />
                </div>
                <div class="col5">
                    <label>DIAGNOSTIC CODES</label>
                    <div class="dagnostic_code" data-name="bat[dagnostic_codes][]" data-required="true">
                        <ul id="list_dagnostic_codes">
                            <?php
                            $bat['dagnostic_codes'] = explode(',', @$bat['dagnostic_codes1']);
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
                        <?php tr_select_option('dagnostic_code_select', $dagnostic_codes, null) ?>
                        <input type="text" id="dagnostic_code_text" placeholder="Enter Additional Codes" />
                    </div>
                </div>
                <div class="col5">
                    <label>Type of Specimen:</label>
                    <div class="ilabel">
                        <?php tr_radio('bat[type_of_specimen]', $type_of_specimens, @$bat['type_of_specimen'], array('attr' => 'required')) ?>
                    </div>
                </div>
                <div class="col5 small">
                    <label>Date of Collection</label>
                    <input type="text" class="hasdatetime <?php if (tr_get_option('ci_pdf_data', 'limit_doc')) echo 'limitfuture' ?>" name="bat[doc]" value="<?php echo empty($bat['doc']) ? 'auto' : $bat['doc'] ?>" <?php if (!empty($bat['doc'])) echo 'readonly' ?> required="" />
                    <label>Date Received</label>
                    <input type="text" class="hasdatetime today" name="bat[spec_received]" value="<?php echo @$bat['spec_received'] ?>" required="" />
                </div>
            </div>

            <div class="frow">
                <h4>PATIENT INFORMATION</h4>
            </div>

            <div class="frow blabel">
                <div class="col4">
                    <label>Social Security Number</label>
                    <input type="text" name="pa[pa_ssn]" id="pa_ssn" maxlength="12" value="<?php echo @$pa['pa_ssn'] ?>" <?php if ($act != 'print') echo 'style="width:60%"' ?> />
                    <input type="hidden" name="pa[pa_ID]" value="<?php echo @$pa['pa_ID'] ?>" />
                    <?php if ($act != 'print') : ?>
                        <a class="button find_patient samefheight" href="#" data-type="ssn">find</a>
                    <?php endif; ?>
                </div>
                <div class="col4">
                    <label>Last Name</label>
                    <input type="text" class="cfname requesting_physician_last_auto" name="pa[pa_last]" value="<?php echo @$pa['pa_last'] ?>" required="" />
                </div>
                <div class="col4">
                    <label>First Name</label>
                    <input type="text" class="cfname" name="pa[pa_first]" value="<?php echo @$pa['pa_first'] ?>" required="" />
                </div>
                <div class="col4">
                    <label>Middle Initial</label>
                    <input type="text" class="cfname" name="pa[pa_middle]" value="<?php echo @$pa['pa_middle'] ?>" />
                </div>
            </div>

            <div class="frow blabel">
                <div class="col4">
                    <label>Medical Record Number</label>
                    <input type="text" id="med_number" name="pa[pa_mrn]" value="<?php echo @$pa['pa_mrn'] ?>" maxlength="15" <?php if ($act != 'print') echo 'style="width:60%"' ?> />
                    <?php if ($act != 'print') : ?>
                        <a class="button find_patient samefheight" href="#" data-type="med_number">find</a>
                    <?php endif; ?>
                </div>
                <div class="col4">
                    <label>Street Address</label>
                    <input type="text" name="pa[pa_address]" id="pa_address" value="<?php echo @$pa['pa_address'] ?>" required="" />
                </div>
                <div class="col4">
                    <label>City</label>
                    <input type="text" name="pa[pa_city]" id="pa_city" value="<?php echo @$pa['pa_city'] ?>" required="" />
                </div>
                <div class="col4">
                    <div class="frow blabel">
                        <div class="col2">
                            <label>State</label>
                            <?php tr_select_option('pa[pa_state]', tr_get_states(), @$pa['pa_state'], array('attr' => 'required')) ?>
                        </div>
                        <div class="col2">
                            <label>Zip</label>
                            <input type="text" name="pa[pa_zip]" id="pa_zip" value="<?php echo @$pa['pa_zip'] ?>" required="" />
                        </div>
                    </div>
                </div>
            </div>
            <div class="frow">
                <div class="col4 label2d">
                    <label>Gender</label><br>
                    <?php tr_select_option('pa[pa_gender]', trpdd_genders(), @$pa['pa_gender'], array('attr' => 'required="" ')) ?>
                </div>
                <div class="col4">
                    <label>DOB</label>
                    <input type="text" class="hasdate birthday limitfuture" name="pa[pa_dob]" value="<?php echo @$pa['pa_dob'] ?>" required="" <?php if (tr_get_option('ci_pdf_data', 'e_hand_editdob')) echo 'data-write="1"' ?> />
                </div>
                <div class="col4">
                    <label>Phone Number</label>
                    <input type="text" class="has_phone" name="pa[pa_phone]" value="<?php echo @$pa['pa_phone'] ?>" />
                </div>
                <div class="col4">
                    <label>Email</label>
                    <input type="email" name="pa[pa_email]" value="<?php echo @$pa['pa_email'] ?>" <?php if (tr_get_option('ci_pdf_data', 'pa_email_required')) echo 'required' ?> />
                </div>
            </div>


            <div class="frow">
                <div class="col23">
                    <h4><label><input type="radio" name="bat[ins_comp]" value="ins" <?php checked('ins', @$bat['ins_comp']) ?> /> PRIMARY INSURANCE BILLING <span>(include copy of both sides of insurance card)</span></label></h4>
                </div>
                <div class="col13">
                    <h4><label><input type="radio" name="bat[ins_comp]" value="comp" <?php checked('comp', @$bat['ins_comp']) ?> /> WORKER'S COMP AND AUTO INFORMATION</label></h4>
                </div>
            </div>

            <div class="frow blabel">
                <div class="col23" id="use_required_ins">

                    <div class="frow">
                        <div class="col3">
                            <label>Last Name</label>
                            <input type="text" class="cfname" name="ins1[ins_last]" value="<?php echo @$ins1['ins_last'] ?>" required="" />
                            <input type="hidden" name="ins1[ins_ID]" value="<?php echo @$ins1['ins_ID'] ?>" />
                        </div>
                        <div class="col3">
                            <label>First Name</label>
                            <input type="text" class="cfname" name="ins1[ins_first]" value="<?php echo @$ins1['ins_first'] ?>" required="" />
                        </div>
                        <div class="col3 ilabel">
                            <label>Relation to Patient?</label><br>
                            <?php tr_radio('ins1[ins_relation]', array('Self' => 'Self', 'Parent' => 'Parent', 'Spouse' => 'Spouse'), @$ins1['ins_relation'], array('attr' => 'required')) ?>
                        </div>
                    </div>
                    <div class="frow">
                        <div class="col3">
                            <label>Insurance Company Name</label>
                            <input type="text" class="cfname ins_company_auto" name="ins1[ins_company]" value="<?php echo @$ins1['ins_company'] ?>" required="" />
                        </div>
                        <div class="col3">
                            <label>Email</label>
                            <input type="email" name="ins1[ins_email]" value="<?php echo @$ins1['ins_email'] ?>" />
                        </div>
                        <div class="col3" id="dob_ins1" <?php if (@$ins1['ins_relation'] == 'Self') echo 'style="display: none"' ?>>
                            <label><span>Parent</span> DOB</label>
                            <input type="text" class="hasdate birthday limitfuture" name="ins1[ins_dob]" value="<?php echo @$ins1['ins_dob'] ?>" />
                        </div>
                    </div>
                    <div class="frow">
                        <div class="col3">
                            <label>Street Address</label>
                            <input type="text" name="ins1[ins_address]" value="<?php echo @$ins1['ins_address'] ?>" required="" />
                        </div>
                        <div class="col3">
                            <label>City</label>
                            <input type="text" name="ins1[ins_city]" value="<?php echo @$ins1['ins_city'] ?>" required="" />
                        </div>
                        <div class="col3">
                            <div class="frow">
                                <div class="col2">
                                    <label>State</label>
                                    <?php tr_select_option('ins1[ins_state]', tr_get_states(), @$ins1['ins_state'], array('attr' => 'required')) ?>
                                </div>
                                <div class="col2">
                                    <label>Zip</label>
                                    <input type="text" name="ins1[ins_zip]" value="<?php echo @$ins1['ins_zip'] ?>" required="" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="frow">
                        <div class="col3">
                            <label>Insurance Phone</label>
                            <input type="text" class="has_phone" name="ins1[ins_phone]" value="<?php echo @$ins1['ins_phone'] ?>" />
                        </div>
                        <div class="col3">
                            <label>Member ID</label>
                            <input type="text" name="ins1[ins_member_id]" maxlength="24" value="<?php echo @$ins1['ins_member_id'] ?>" required="" />
                        </div>
                        <div class="col3">
                            <label>Group #</label>
                            <input type="text" name="ins1[ins_group_id]" value="<?php echo @$ins1['ins_group_id'] ?>" />
                        </div>
                    </div>
                    <div class="frow">
                        <div class="col3">
                            <label>Pre-authorization</label>
                            <?php tr_yes_no('ins1[ins_authorization]', @$ins1['ins_authorization'], array('attr' => 'required')) ?>
                        </div>
                        <div class="col3">
                            <label>Authorization #</label>
                            <input type="text" name="ins1[ins_authorization_id]" value="<?php echo @$ins1['ins_authorization_id'] ?>" />
                        </div>
                        <div class="col3">
                            <label>Authorization Date</label>
                            <input type="text" class="hasdate" name="ins1[ins_authorization_date]" value="<?php echo @$ins1['ins_authorization_date'] ?>" />
                        </div>
                    </div>
                    <h4>SECONDARY INSURANCE BILLING <span>(include copy of both sides of insurance card)</span></h4>
                    <div class="frow">
                        <div class="col3">
                            <label>Last Name</label>
                            <input type="text" class="cfname" name="ins2[ins_last]" value="<?php echo @$ins2['ins_last'] ?>" />
                            <input type="hidden" name="ins2[ins_ID]" value="<?php echo @$ins2['ins_ID'] ?>" />
                        </div>
                        <div class="col3">
                            <label>First Name</label>
                            <input type="text" class="cfname" name="ins2[ins_first]" value="<?php echo @$ins2['ins_first'] ?>" />
                        </div>
                        <div class="col3 ilabel">
                            <label>Relation to Patient?</label><br>
                            <?php tr_radio('ins2[ins_relation]', array('Self' => 'Self', 'Parent' => 'Parent', 'Spouse' => 'Spouse'), @$ins2['ins_relation']) ?>
                        </div>
                    </div>
                    <div class="frow">
                        <div class="col3">
                            <label>Insurance Company Name</label>
                            <input type="text" class="cfname ins_company_auto" name="ins2[ins_company]" value="<?php echo @$ins2['ins_company'] ?>" />
                        </div>
                        <div class="col3">
                            <label>Email</label>
                            <input type="email" name="ins2[ins_email]" value="<?php echo @$ins2['ins_email'] ?>" />
                        </div>
                        <div class="col3" id="dob_ins2" <?php if (@$ins2['ins_relation'] == 'Self') echo 'style="display: none"' ?>>
                            <label><span>Parent</span> DOB</label>
                            <input type="text" class="hasdate birthday limitfuture" name="ins2[ins_dob]" value="<?php echo @$ins2['ins_dob'] ?>" />
                        </div>
                    </div>
                    <div class="frow">
                        <div class="col3">
                            <label>Insurance Phone</label>
                            <input type="text" class="has_phone" name="ins2[ins_phone]" value="<?php echo @$ins2['ins_phone'] ?>" />
                        </div>
                        <div class="col3">
                            <label>Member ID</label>
                            <input type="text" name="ins2[ins_member_id]" maxlength="24" value="<?php echo @$ins2['ins_member_id'] ?>" />
                        </div>
                        <div class="col3">
                            <label>Group #</label>
                            <input type="text" name="ins2[ins_group_id]" value="<?php echo @$ins2['ins_group_id'] ?>" />
                        </div>
                    </div>

                </div>
                <div class="col13" id="use_required_com">
                    <div class="frow">
                        <div class="col2">
                            <label>Subscriber Last Name</label>
                            <input type="text" class="cfname" name="bat[subscriber_last]" value="<?php echo @$bat['subscriber_last'] ?>" required="" />
                        </div>
                        <div class="col2">
                            <label>Subscriber First Name</label>
                            <input type="text" class="cfname" name="bat[subscriber_first]" value="<?php echo @$bat['subscriber_first'] ?>" required="" />
                        </div>
                    </div>
                    <div class="frow">
                        <label>Date of Injury</label>
                        <input type="text" class="hasdate" name="bat[date_of_injury]" value="<?php echo @$bat['date_of_injury'] ?>" required="" />
                    </div>
                    <div class="frow">
                        <div class="col23">
                            <label>Address of Claim</label>
                            <input type="text" name="bat[address_of_claim]" value="<?php echo @$bat['address_of_claim'] ?>" />
                        </div>
                        <div class="col13">
                            <label>Zip Code</label>
                            <input type="text" name="bat[subscriber_zip]" value="<?php echo @$bat['subscriber_zip'] ?>" />
                        </div>
                    </div>
                    <div class="frow">
                        <div class="col23">
                            <label>Insurance</label>
                            <input type="text" class="cfname" name="bat[subscriber_insurance]" value="<?php echo @$bat['subscriber_insurance'] ?>" required="" />
                        </div>
                        <div class="col13">
                            <label>WC#</label>
                            <input type="text" name="bat[subscriber_wc]" value="<?php echo @$bat['subscriber_wc'] ?>" />
                        </div>
                    </div>
                    <div class="frow">
                        <div class="col23">
                            <label>Attorney</label>
                            <input type="text" name="bat[attorney_name]" value="<?php echo @$bat['attorney_name'] ?>" />
                        </div>
                        <div class="col13">
                            <label>Attorney Phone</label>
                            <input type="text" class="has_phone" name="bat[attorney_phone]" value="<?php echo @$bat['attorney_phone'] ?>" />
                        </div>
                    </div>
                    <div class="frow">
                        <label>Adjuster Name</label>
                        <input type="text" class="cfname" name="bat[adjuster_name]" value="<?php echo @$bat['adjuster_name'] ?>" />
                    </div>
                    <div class="frow">
                        <label>Adjuster Phone Number</label>
                        <input type="text" class="has_phone" name="bat[adjuster_phone]" value="<?php echo @$bat['adjuster_phone'] ?>" />
                    </div>
                    <div class="frow">
                        <label>Claim Number</label>
                        <input type="text" name="bat[claim_number]" value="<?php echo @$bat['claim_number'] ?>" required="" />
                    </div>
                    <div class="frow">
                        <label>Claim under litigation?</label>
                        <?php tr_yes_no('bat[claim_under_litigation]', @$bat['claim_under_litigation']); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="frow printbreak">
            <div class="col2">
                <h4>PATIENT’S CURRENT MEDICATIONS</h4>
            </div>
            <div class="col2">
                <h4>TESTING ORDERED</h4>
            </div>
        </div>

        <div class="frow">
            <div class="col2 lable4cl" style="padding-top:20px">
                <input type="hidden" name="has_edit_medication" value="1" />
                <?php foreach ($current_medications as $m) :
                    $k = $m;
                    if ($k == 'Fioricet/Fiorinal') $k = 'Fioricet';
                    else if ($k == 'Lorcet/Lortab') $k = 'Lortab';
                ?>
                    <label>
                        <input type="checkbox" name="bat[current_medication][]" value="<?php echo $k ?>" <?php checked(in_array($k, @$bat['current_medication'])) ?> />
                        <?php echo $m ?>
                    </label>
                <?php endforeach; ?>

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
                <?php if ($act != 'print') : ?>
                    <a class="add_more_current_medication button">+</a>
                <?php endif; ?>
                <div class="frow">
                    Not all drugs LISTED under "other" will be available.
                </div>
            </div>
            <div class="col2 ">
                <input type="hidden" name="has_panel_requests" value="1" />
                <?php
                $keys = array(
                    'Comprehensive' => '<b>Gene (#SNPS)</b> CYP1A2 (4), CYP2B6 (8), CYP2C9 (8), CYP2C19 (8), CYP2D6 (15), CYP3A4 (6), CYP3A5 (5), ANKK1/DRD2 (1), APOE (2),COMT (1), FACTOR II [F2] (1), FACTOR V [F5] (1), MTHFR (2),OPRM1 (1), SLCO1B1 (2), VKORC1 (1)',
                    'Pain Management' => '<b>Gene (#SNPS)</b> CYP1A2( 4), CYP2B6 (8), CYP2C9 (8), CYP2C19 (8), CYP2D6 (15), CYP3A4 (6), CYP3A5 (5), OPRM1(1)',
                    'Psychiatry' => '<b>Gene (#SNPS)</b> CYP1A2(4), CYP2B6(8), CYP2C9 (8), CYP2C19(8),CYP2D6(15), CYP3A4 (6), CYP3A5(5), ANKK1/DRD2 (1),COMT (1), MTHFR (2)',
                    'Cardiology' => '<b>Gene (#SNPS)</b> CYP2C9 (8), CYP2C19 (8), CYP2D6 (15), CYP3A4 (6),CYP3A5 (5), APOE (2), FACTOR II [F2] (1), FACTOR V [F5] (1),MTHFR (2), SLCO1B1 (2), VKORC1 (1)',
                    'Urology' => '<b>Gene (#SNPS)</b> CYP2D6 (15), CYP3A4 (6),CYP3A5 (5)'
                );
                $i = 0;
                foreach (tr_pdd_get_profiles_tests_requesteds('dna', true, false, $bat['doc']) as $group_id => $vl) {

                    $group_key      = 'GROUP_' . $group_id;
                    $group          = tr_pdd_get_panel_groups($group_id, 'dna');

                    $has_panel = isset($bat['panel_tests_requested'][$group_key]);

                    $txt            = '<b>Gene (#SNPS)</b>';
                    foreach ($vl as $r) {
                        $txt .= ', ' . $r->te_name;
                    }

                    $tests = $has_panel ? $bat['panel_tests_requested'][$group_key] : array();
                    if (count($tests) == 0) {
                        $tests = array(array());
                    }

                    if (empty($group['bgcolor'])) {
                        $group['bgcolor'] = '#f8f8f8';
                    }
                    $group['color'] = trpdd_getContrastColor($group['bgcolor']);
                    $group['spec_bgcolor'] = strlen($group['bgcolor']) == 7 ? $group['bgcolor'] . '88' : $group['bgcolor'];
                    $group['box_bgcolor'] = strlen($group['bgcolor']) == 7 ? $group['bgcolor'] . '66' : $group['bgcolor'];
                    if ($act == 'print') {
                        $group['box_bgcolor'] = '#f8f8f8';
                        $group['spec_bgcolor'] = '#eeeeee';
                    }
                ?>
                    <div class="frow blabel testing_ordered_box" style="padding:5px;background:<?php echo $group['box_bgcolor'] ?>">
                        <label>
                            <input type="checkbox" name="testing_ordered[]" <?php checked($has_panel) ?> value="<?php echo $group_key ?>">
                            <strong class="tooltip" title="<?php echo ($txt) ?>"><?php echo $group['label'] ?>: <i class="itip"></i></strong>
                        </label>
                        <div class="testingordered_wrap">
                            <table class="testing_ordered_list">
                                <?php foreach ($tests as $j => $arr) { ?>
                                    <tr>
                                        <td class="tdclpn"><label>- <input type="radio" name="testing_ordered_add[<?php echo $group_key ?>][<?php echo $j ?>]" value="-" <?php checked($arr['type'], '-') ?> /></label> </td>
                                        <td class="tdclpn"><label>+ <input type="radio" name="testing_ordered_add[<?php echo $group_key ?>][<?php echo $j ?>]" value="+" <?php checked($arr['type'], '+') ?> /></label> </td>
                                        <td><input type="text" name="testing_ordered_name[<?php echo $group_key ?>][<?php echo $j ?>]" value="<?php echo $arr['name'] ?>" /></td>
                                    </tr>
                                <?php } ?>
                            </table>
                            <div class="alignright"><a class="button add_new_testing" data-nextid="<?php echo $j + 1 ?>">+</a></div>

                            <div class="specimens">
                                <?php
                                $rs = $wpdb->get_results("select * from ci_test_specimens where (ts_group_id={$group_id} or ts_group_id = '') and ts_type='dna'");
                                if (count($rs) > 0) {

                                ?>
                                    <div class="specimens" style="padding:5px;margin-top:5px;">
                                        <label class="title">SPECIMEN SOURCE</label>
                                        <?php
                                        $specimens = explode(',', strtolower($bat['type_of_specimen']));
                                        foreach ($rs as $r) {
                                            $checked_spec = in_array(strtolower($r->ts_name), $specimens);
                                            if (!$in_group) {
                                                $checked_spec = false;
                                            }
                                        ?>
                                            <label><input type="checkbox" name="bat[type_of_specimen][]" <?php checked($checked_spec) ?> value="<?php echo $r->ts_name ?>"> <?php echo $r->ts_name ?>
                                            </label>
                                        <?php
                                        }
                                        ?>
                                    </div>
                                <?php
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                <?php
                    $i++;
                }
                ?>
            </div>
        </div>
        <div class="frow">
            STATEMENT OF CONSENT AND RELEASE I hereby certify that the information provided is true and accurate. I consent to the collection of specimens from myself and any listed
            child for the purpose of PGx testing. <?php echo @$current_lab['lab_dba_init'] ?> reserves the right to re-collect samples for retesting and to store samples for future additional tests, if necessary. I understand that while
            PGx testing is highly accurate and widely accepted, as in all testing there is a possibility of delay or error. I understand that samples and the PGx they contain become the
            exclusive property of <?php echo @$current_lab['lab_dba_init'] ?>. I also understand that this testing is for informational purposes and prescription drug regimes should never be altered without consulting a physician.
            <?php echo @$current_lab['lab_dba_init'] ?> will not release any personal, identifiable information of any kind to a third party without my express written instructions. I also understand that <?php echo @$current_lab['lab_dba_init'] ?> reserves the right to
            provide deidentified information of a statistical nature to accrediting agencies and reserves the right to use such anonymous information
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

                if ($act == 'view') {
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
                        <?php if ($bat['patient_signature'] != 'onfile') { ?>
                            <div>
                                <label class="inline">
                                    <input type="checkbox" name="onfile" value="1" <?php checked($settings['auto_on_patient_signature_file']) ?> /> On-File
                                </label>
                            </div>
                        <?php } ?>
                    </div>
                <?php
                }
                ?>
            </div>
            <div class="col2">
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
                if ($act == 'view') {
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
                        <span class="view"><?php echo $provider_signature_img ?></span>
                        <input type="file" name="provider_signature" accept="image/*" />
                        <input type="hidden" name="img_provider_signature" value="<?php echo $fname ?>" />
                    </div>
                <?php
                }
                ?>
            </div>
        </div>


        <div class="frow">
            <h4>INSERT INSURANCE CARD AND PHOTO ID PICTURES</h4>
        </div>
        <div class="frow">
            By clicking the icon below, you will be prompted to insert a photo of both the patient current insurance card and photo ID. The photo
            can be obtained directly from the computer (saved as a pdf) or a scanner.
        </div>
        <div class="frow blabel">
            <?php
            $col_num = ($action_form != 'ci_save_dna') ? 'col2' : 'col4';
            ?>
            <div class="<?php echo $col_num ?>">
                <?php
                $fname = !empty($bat['ins_card_front']) ? $bat['ins_card_front'] : $batch_key . '_ins.jpg';
                $rq1 = false;
                if (!empty($action_form)) {
                ?>
                    <div class="card_file">
                        <a class="button selectfile <?php if ($rq1) echo 'required' ?>">Choose Ins Card Front File</a>
                        <span>
                            <?php
                            if (file_exists($card_dir['path'] . '/' . $fname) && filesize($card_dir['path'] . '/' . $fname) > 0) {
                                echo '<img src="' . $card_dir['url'] . '/' . $fname . '?r=' . time() . '" style="width:100%">';
                            }
                            ?>
                        </span>
                        <input type="file" <?php if ($rq1) echo 'data-required="1" required ' ?> name="ins_card_front" accept="image/*" />
                    </div>
                <?php
                } else {
                    echo '<label>Ins Card Front</label>';

                    if (file_exists($card_dir['path'] . '/' . $fname) && filesize($card_dir['path'] . '/' . $fname) > 0) {
                        echo '<img src="' . $card_dir['url'] . '/' . $fname . '?r=' . time() . '" style="width:100%">';
                    }
                }
                ?>
                <input type="hidden" name="img_ins_card_front" value="<?php echo $fname ?>" />
            </div>
            <div class="<?php echo $col_num ?>">
                <?php
                $fname = !empty($bat['ins_card_back']) ? $bat['ins_card_back'] : $batch_key . '_ins_back.jpg';
                if (!empty($action_form)) {
                ?>
                    <div class="card_file">
                        <a class="button selectfile" href="#">Choose Ins Card Back File</a>
                        <span>
                            <?php
                            if (file_exists($card_dir['path'] . '/' . $fname) && filesize($card_dir['path'] . '/' . $fname) > 0) {
                                echo '<img src="' . $card_dir['url'] . '/' . $fname . '?r=' . time() . '" style="width:100%">';
                            }
                            ?>
                        </span>
                        <input type="file" name="ins_card_back" />
                    </div>
                <?php
                } else {
                    echo '<label>Ins Card Back</label>';

                    if (file_exists($card_dir['path'] . '/' . $fname) && filesize($card_dir['path'] . '/' . $fname) > 0) {
                        echo '<img src="' . $card_dir['url'] . '/' . $fname . '?r=' . time() . '" style="width:100%">';
                    }
                }
                ?>
                <input type="hidden" name="img_ins_card_back" value="<?php echo $fname ?>" />
            </div>

            <div class="<?php echo $col_num ?>">
                <?php
                $fname = !empty($bat['patient_id_front']) ? $bat['patient_id_front'] : $batch_key . '_id.jpg';
                if (!empty($action_form)) {
                ?>
                    <div class="card_file">
                        <a class="button selectfile" href="#">Choose Patient ID Front File</a>
                        <span>
                            <?php
                            if (file_exists($card_dir['path'] . '/' . $fname) && filesize($card_dir['path'] . '/' . $fname) > 0) {
                                echo '<img src="' . $card_dir['url'] . '/' . $fname . '?r=' . time() . '" style="width:100%">';
                            }
                            ?>
                        </span>
                        <input type="file" name="patient_id_front" />
                    </div>
                <?php
                } else {
                    echo '<label>Patient ID Front</label>';

                    if (file_exists($card_dir['path'] . '/' . $fname) && filesize($card_dir['path'] . '/' . $fname) > 0) {
                        echo '<img src="' . $card_dir['url'] . '/' . $fname . '?r=' . time() . '" style="width:100%">';
                    }
                }
                ?>
                <input type="hidden" name="img_patient_id_front" value="<?php echo $fname ?>" />
            </div>
            <div class="<?php echo $col_num ?>">
                <?php
                $fname = !empty($bat['patient_id_back']) ? $bat['patient_id_back'] : $batch_key . '_id_back.jpg';
                if (!empty($action_form)) {
                ?>
                    <div class="card_file">
                        <a class="button selectfile" href="#">Choose Patient ID Back File</a>
                        <span>
                            <?php
                            if (file_exists($card_dir['path'] . '/' . $fname) && filesize($card_dir['path'] . '/' . $fname) > 0) {
                                echo '<img src="' . $card_dir['url'] . '/' . $fname . '?r=' . time() . '" style="width:100%">';
                            }
                            ?>
                        </span>
                        <input type="file" name="patient_id_back" />
                    </div>
                <?php
                } else {
                    echo '<label>Patient ID Back</label>';

                    if (file_exists($card_dir['path'] . '/' . $fname) && filesize($card_dir['path'] . '/' . $fname) > 0) {
                        echo '<img src="' . $card_dir['url'] . '/' . $fname . '?r=' . time() . '" style="width:100%">';
                    }
                }
                ?>
                <input type="hidden" name="img_patient_id_back" value="<?php echo $fname ?>" />
            </div>
        </div>

        <div class="frow">
            <h4>NOTES</h4>
        </div>
        <div class="frow">
            <textarea name="bat[notes]" <?php if ($act != 'print') echo 'placeholder="Please enter any ' . (tr_get_option('ci_pdf_data', 'cht_physicalnotes') ? 'Chief Complaint' : 'Physician Notes') . '"' ?>><?php if (isset($bat['notes'])) echo @$bat['notes'] ?></textarea>
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

        <?php
        //if(isset($_REQUEST['short_form']))
        //{
        ?>
        <div class="frow">
            <div class="col3">
                <label>Status</label>
                <?php
                $status_options = tr_pdd_get_batch_statuses_on_hold(tr_pdd_get_batch_statuses(false, false, false, 'dna'), $bat['batch_status'], $bat['mid'], $table_batch, 'dna');
                $disabled_keys = tr_pdd_get_batch_statuses_disabled_keys('dna', 'show_in_req_form', $bat['batch_status']);
                tr_select_option('bat[batch_status]', $status_options, @$bat['batch_status'], ['disabled_options' => $disabled_keys]);
                ?>
            </div>
            <div class="col3 notprint">
                <label>Photo</label><br>
                <input type="file" name="issue_photo" />
            </div>
        </div>
        <?php
        // }
        ?>





        <?php if (!empty($action_form) && $act != 'print') : ?>

            <div class="frow">
                <h4>Original Req and Results Upload</h4>
            </div>
            <div class="frow">
                <div class="col2">
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
                <div class="col2">
                    <?php trpdd_render_req_upload_files('original_req', isset($bat['original_req']) ? $bat['original_req'] : '', 'Choose Original Req'); ?>
                </div>
            </div>

            <div class="frow">
                <h4>&nbsp;</h4>
            </div>
            <div class=" row" style="margin-bottom:15px;">

                <div class=" foutrow ilabel">
                    <?php if ($act != 'print' && $act != 'view') : ?>

                        <label>Accession Number</label>
                        <input type="text" id="number_assen" name="bat[assen]" value="<?php echo @$bat['assen'] ?>" />
                        <label>Confirm</label>
                        <input type="text" id="confirm_assen" value="<?php echo @$bat['assen'] ?>" />
                        <span class="confirm_status dashicons dashicons-yes"></span>
                        <?php /*
                        <a class="button" data-a="<?php echo @$bat['assen']?>" id="box_create_rand" href="#">Generate</a>
                        */ ?>
                        <a class="button" style="<?php if (empty($bat['assen'])) echo 'display:none' ?>" id="box_print_assen" href="#">Print</a>
                        <input type="hidden" name="auto_generated" value="1" />
                        <span class="small">Leave empty for Auto Generated</span>

                    <?php endif; ?>

                </div>
                <div class="clear"></div>
            </div>

            <?php
            include(trpdd_get_file_path('global', 'end_req_form'));
            ?>
            <div class="frow">
                <input type="submit" class="button-primary" value="Submit">
                <?php if (@$_REQUEST['page'] == 'ci_short_list') {
                ?>
                    <input type="hidden" name="bat[short_completed]" value="0">
                    <label><input type="checkbox" name="bat[short_completed]" value="1" <?php checked(@$bat['short_completed'], 1) ?>> Completed</label>
                <?php
                } ?>

                <?php if (@$mid == 0 || $saved_id > 0) : ?>
                    <input type="submit" class="button only_save_form right" name="only_save_form" value="Save">
                    <input type="hidden" name="saved_id" value="<?php echo $saved_id ?>" />
                <?php endif; ?>

                <input type="hidden" name="tr_action" value="<?php echo $action_form ?>" />
                <input type="hidden" name="bat[is_dna]" value="1" />
                <?php if (@$mid > 0) : ?>
                    <input type="hidden" name="bat[mid]" value="<?php echo $mid ?>" />
                <?php endif; ?>

            </div>
        <?php endif; ?>
        <?php trpdd_form_unique_id() ?>

    </form>
</div>

<?php
if ($act == 'print') {
    if (!isset($print_canvas)) echo '<script>window.print();</script>';
} else {
?>

    <script>
        var $t_assen = false;
        jQuery(function($) {
            $('body').on('change', '.warning_doc', function() {
                var $d = $(this).val();
                var currentTime = new Date()
                var month = currentTime.getMonth() + 1;
                var day = currentTime.getDate();
                var year = currentTime.getFullYear();
                if (month < 10) month = '0' + month;
                if (day < 10) day = '0' + day;
                var d1 = new Date(year + '-' + month + '-' + day);
                $d = $d.split(' ');
                $d = $d[0];
                console.log($d);
                var d2 = new Date($d);

                if (d1.getTime() < d2.getTime()) {
                    alert($(this).data('warning'));
                    $(this).val('');
                }
            }).on('change', '#number_assen', function() {
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
                            btype: 'dna'
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

            $('[name="bat[temperature]"]').change(function() {
                if ($('#tempe_1').is(":checked") || $('#tempe_2').is(":checked") || $('#tempe_3').is(":checked")) {
                    //$('[name="bat[actual_temperature]"]').removeAttr('required');
                    //$(this).attr('required','required');
                } else {
                    //$('[name="bat[actual_temperature]"]').attr('required','required');
                    //$(this).removeAttr('required');
                }
            }).change();
            $('[name="bat[actual_temperature]"]').change(function() {
                $('[name="bat[temperature]"]').removeAttr('required').removeAttr('checked');
            })

            $('body').on('change', '[name="bat[cli_ID]"]', function() {
                var $cli_id = $(this).val();
                $('.requesting_physician_last_auto').autocomplete('option', 'source', ajaxurl + '?action=ajax&autocomplete=requesting_patients_last_auto&cli_id=' + $cli_id);
            })

            $('.requesting_physician_last_auto').autocomplete({
                source: ajaxurl + '?action=ajax&autocomplete=requesting_patients_last_auto',
                minLength: 1,
                search: function(event, ui) {
                    var $cli_id = $('[name="bat[cli_ID]"]').val();
                    console.log($cli_id);
                    if ($cli_id == 0) {
                        return false;
                    } else {
                        $('.requesting_physician_last_auto').autocomplete('option', 'source', ajaxurl + '?action=ajax&autocomplete=requesting_patients_last_auto&cli_id=' + $cli_id);
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
                            if (ui.item.diagnostic) {
                                var $codes = ui.item.diagnostic.split(',');
                                var $list = $('#list_dagnostic_codes');
                                $list.empty();
                                for (var i in $codes) {
                                    var $code = $.trim($codes[i]);
                                    if ($code === '') continue;
                                    $list.append(
                                        '<li>' +
                                        '<input type="hidden" name="bat[dagnostic_codes][]" value="' + $code + '" />' +
                                        $code +
                                        ' <span></span>' +
                                        '</li>'
                                    );
                                }
                            }
                        } catch (e) {
                            console.log(e);
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

            $('#batcustom_profile').change(function() {
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
                        console.log($tpl);
                        $('#default_requests input.te_' + $tpl).prop('checked', true);
                    }
                } catch (e) {
                    console.log(e);
                }
                $('#default_requests input[disabled]').removeAttr('checked');
                $('.screening_cb').change();
                $('[name="bat[current_medication][]"]:checked').change();
            })
            $('#box_create_rand').click(function() {
                var $ptient = encodeURI($('[name="pa[pa_last]"]').val() + '|' + $('[name="pa[pa_first]"]').val());
                var $dob = encodeURI($('[name="pa[pa_dob]"]').val())
                tb_show('', '<?php echo get_home_url(); ?>/wp-admin/admin.php?&tr_action=qrcode&width=400&height=400&text=' + $(this).data('a') + '&pa=' + $ptient + '&dob=' + $dob);
                return false;
            });
            $('#requisition_form').submit(function() {
                if ($('#confirm_assen').val() != $('[name="bat[assen]"]').val()) {
                    alert('Please Confirm Accession Number');
                    $('#confirm_assen').focus();
                    return false;
                }
            });
            $('#box_print_assen').click(function() {
                var $ptient = encodeURI($('[name="pa[pa_last]"]').val() + '|' + $('[name="pa[pa_first]"]').val());
                var $dob = encodeURI($('[name="pa[pa_dob]"]').val())
                tb_show('', '<?php echo get_home_url(); ?>/wp-admin/admin.php?&tr_action=qrcode&width=400&height=400&text=' + $('#confirm_assen').val() + '&pa=' + $ptient + '&dob=' + $dob);
                return false;
            })
            $('#confirm_assen,[name="bat[assen]"]').keyup(function() {
                if ($('#confirm_assen').val() != '' && $('#confirm_assen').val() == $('[name="bat[assen]"]').val()) {
                    $('.confirm_status').addClass('confirmed');
                    $('.confirm_status').removeClass('error');
                    $('#box_print_assen').show();
                } else {
                    $('.confirm_status').removeClass('confirmed');
                    $('.confirm_status').addClass('error');
                    $('#box_print_assen').hide();
                }
            })
            $('body').on('click', '.only_save_form', function() {
                $('form [required]').removeAttr('required');
            })
            $('body').on('change', 'select#col_id_sel', function() {
                var $phone = $('option[value="' + $(this).val() + '"]', $(this)).data('phone');
                $('[name="col[col_phone]"]').val($phone);
            })
            $('.add_new_testing').click(function() {
                var $nid = parseInt($(this).data('nextid'));
                var $tb = $(this).parents('.testing_ordered_box').find('.testing_ordered_list');
                $new = $tb.find('tr:first-child').html();
                $new = $new.replace(/\[[0-9]+\]\"/g, '[' + $nid + ']"');
                $new = $('<tr>' + $new + '</tr>');
                $('input[type="text"]', $new).val('');
                $('input[type="radio"]', $new).removeAttr('checked');
                $tb.append($new);
                $(this).data('nextid', $nid + 1);
            });
            $('.tooltip').tooltip({
                content: function() {
                    return $(this).attr('title');
                }
            });

        })
    </script>
<?php
}
?>



<?php
if (isset($_GET['debug'])) {
    $content = ob_get_clean();
    $content = str_replace('required', 'datarequired', $content);
    echo $content;
}
?>