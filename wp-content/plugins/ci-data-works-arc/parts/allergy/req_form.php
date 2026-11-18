<?php
global $wpdb;
global $current_role;
global $blog_id;


if (isset($_GET['debug'])) {
    ob_start();
}

$act = isset($_REQUEST['act']) ? $_REQUEST['act'] : @$act;
$table_batch = 'ci_batches_allergy';
$current_medications = tr_pdd_get_current_medications();
$tabs = array(
    '' => 'Allergy Form',
    //'temp_batches' => 'Temp Batches',
    'current_batches' => 'Completed Req Forms',
    'saved_form' => 'Saved'
);
$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);


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
            $link = Tr_Base_Class_V6::link(array('tab' => $k, 'act' => '', 'id' => '', 'mid' => ''), false);
            echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
        }
        ?>
    </h2>

<?php

}



$dagnostic_codes = array(
    '' => 'Please Select',
    'Z79.891' => 'Z79.891 - Long term (current) use of opiate analgesic',
    'Z79.899' => 'Z79.899 - Other long term (current) drug therapy',
    'F11.20' => 'F11.20 -  OPIOID DEPENDENCE, Uncomplicated',
    'F19.20' => 'F19.20 - Other psychoactive substance dependence, uncomplicated',
    'F18.10' => 'F18.10 - Inhalant abuse, uncomplicated',
    'F18120' => 'F18120 - Inhalant abuse with intoxication, uncomplicated',
    'F1890' => 'F1890 - Inhalant use, unspecified, uncomplicated',
    'F550' => 'F550 - Abuse of antacids',
    'F551' => 'F551 - Abuse of herbal or folk remedies',
    'F552' => 'F552 - Abuse of laxatives',
    'F553' => 'F553 - Abuse of steroids or hormones',
    'F554' => 'F554 - Abuse of vitamins',
    'F558' => 'F558 - Abuse of other non-psychoactive substances',
    'M545' => 'M545 -Low Back Pain',
    'M546' => 'M546 - Pain in Thoracic Spine',
    'G894' => 'G894 - Chronic Pain Syndrome',
);

$type_of_specimens = array(
    //'Urine' => 'Urine',
    'Blood' => 'Blood',
    //'Oral'  => 'Oral',
    'Serum' => 'Serum',
    'Other' => 'Other'
);



$cli = array();
$collector = array();
$current_lab = array();
$clinics = array('' => 'Please Select');
$docs = array('' => 'Please Select');
$where_clis = " and cli.cli_clinic !='' and cli.cli_clinic !='0' ";
$cli_id = 0;

if (isset($_POST['doc_id'])) {
    $_POST = tr_pdd_stripslashes_deep($_POST);

    $doc_id = $_POST['doc_id'];
    $doc = $wpdb->get_row("select * from ci_doc where doc_ID = {$doc_id}");
    $cli_id = $doc->cli_ID;
    $bat['physician_phone'] = $doc->doc_phone;
    $bat['doc_ID'] = $doc_id;
} else if (isset($_POST['clinic_id'])) {
    $cli_id = $_POST['clinic_id'];
    $cli = $wpdb->get_row("select * from ci_clinic where `cID`={$cli_id}", ARRAY_A);
}

if ($current_role == 'lab_rep'  || $current_role == 'lab_rep_w2') {
    $rep_id = get_user_meta(get_current_user_id(), '_rep_id', true);
    $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);
    $reps = array($rep_id);
    $members = tr_dpp_get_users_in_group(get_current_user_id());
    foreach ($members as $uid) {
        $reps[] = get_user_meta($uid, '_rep_id', true);
    }
    $reps = implode(',', $reps);
    $where_clis .= " and cli.`cli_rep-ID` in ($reps) ";
} else if ($current_role == 'collector') {
    $col_id = get_user_meta(get_current_user_id(), '_collector_id', true);
    $collector = $wpdb->get_row("select * from ci_collector where col_ID ='{$col_id}' ", ARRAY_A);
    $rep_id = $collector['rep_ID'];
    $reps = array();
    $members = tr_dpp_get_users_in_group(get_current_user_id());
    foreach ($members as $uid) {
        $reps[] = get_user_meta($uid, '_rep_id', true);
    }
    if (count($reps) > 0) {
        $reps = implode(',', $reps);
        $where_clis .= " and (cli.`cli_rep-ID` in ($reps) or cc.col_ID = {$col_id})";
    } else {
        $where_clis .= " and cc.col_ID = {$col_id} ";
    }
}

if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $where_clis .= " and cli.`cli_lab-ID` in ( {$lab_str} ) ";
}




if ($tab == 'current_batches') {
    include('req_list.php');
    return;
} else if ($tab == 'saved_form') {
    if (!empty($act) && $act != 'delete') {
        $saved_id = $_REQUEST['id'];
    } else {
        include('req_saved.php');
        return;
    }
} else if ($tab == 'prior_authorization') {
    include('admin_form_prior_authorization.php');
    return;
}


if ($act == 'view' || $act == 'edit' || $act == 'print') {

    $action_form = '';
    $id = $_REQUEST['id'];
    $bat = $wpdb->get_row("select * from {$table_batch} where mid='{$id}' ", ARRAY_A);

    $cli['cID'] = $bat['cli_ID'];
    $cli_id = $cli['cID'];
    $pa = $wpdb->get_row("select pa.* from ci_patients pa
                           where pa.pa_ID = '{$bat['patient_id']}' ", ARRAY_A);
    if (!$pa) {
        $pa = array();
        list($pa['pa_last'], $pa['pa_first']) = explode(',', @$bat['patient_name']);
        $pa['pa_dob'] = $bat['dob'];
    }
    $ins1 = $wpdb->get_row("select * from ci_insurances where ins_ID = '{$bat['insurance1']}' ", ARRAY_A);
    $ins2 = $wpdb->get_row("select * from ci_insurances where ins_ID = '{$bat['insurance2']}' ", ARRAY_A);

    $collector = $wpdb->get_row("select * from ci_collector where col_ID = '{$bat['col_ID']}' ", ARRAY_A);

    $bat['current_medication_str'] = $bat['current_medication'];
    $bat['current_medication'] = explode(',', @$bat['current_medication']);
    $bat['current_medication_other'] = explode(',', @$bat['current_medication_other']);
    if (count($bat['current_medication_other']) == 1 && trim($bat['current_medication_other'][0]) == '') {
        $bat['current_medication_other'] = array();
    }
    foreach ($bat['current_medication'] as $m) {
        if (!in_array($m, $current_medications) && !empty($m) && $m != 'None Listed') {
            $bat['current_medication_other'][] = $m;
        }
    }

    if (strpos($bat['panel_tests_requested'], '+') > 0) {
        $bat['panel_tests_requested'] = explode('+', $bat['panel_tests_requested']);
        $bat['panel_tests_requested'] = end($bat['panel_tests_requested']);
    }
    $bat['panel_tests_requested'] = explode(',', @$bat['panel_tests_requested']);
    $bat['medication'] = maybe_unserialize(@$bat['medication']);

    $bat['current_medication'] = array_map('trim', $bat['current_medication']);
    $bat['panel_tests_requested'] = array_map('trim', $bat['panel_tests_requested']);
    $bat['current_medication_other'] = array_map('trim', $bat['current_medication_other']);
    //var_dump(@$bat);
    $batch_key = $bat['assen'];

    if ($bat['lab_id'] > 0) {
        $where_clis .= " and cli.`cli_lab-ID` = {$bat['lab_id']}";
    }

    if ($act == 'edit') {
        $action_form = 'ci_save_allergy';
        $mid = $id;
    }
} else {

    $bat['panel_tests_requested'] = array();
    $bat['custom_profile'] = '';
    $bat['medication'] = array();
    $bat['current_medication'] = array();
    $bat['poc_perform'] = '0';
    $bat['fasting'] = 0;


    $action_form = 'ci_save_allergy';
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
                    where cli.cli_status = 1 {$where_clis} group by cli.cID order by cli.cli_clinic";
$clinics = $wpdb->get_results($sql_clinic, ARRAY_A);
if (count($clinics) == 1) {
    $cli = $clinics[0];
    $cli_id = $bat['cli_ID'] = $cli['cID'];
}

if ($cli_id > 0) {
    $ds = $wpdb->get_results("select doc.* from ci_doc doc
                        JOIn ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
                        left JOIN ci_custom_providers cp ON doc.doc_ID = cp.doc_ID
                        where cd.cli_ID = {$cli_id}
                        order by doc.doc_last,doc.doc_first asc");

    foreach ($ds as $doc) {
        $docs[$doc->doc_ID] = $doc->doc_last . ', ' . $doc->doc_first;
    }
    $current_lab = $wpdb->get_row("select * from ci_labs Where lab_ID = {$lab_id}", ARRAY_A);

    $collectors = $wpdb->get_results("select col.* from ci_collector col
                            JOIN ci_clinic_collector cc ON cc.col_ID = col.col_ID
                            where cc.cli_ID ={$cli_id} and (col.col_status=1 or col.col_ID = '{$bat['col_ID']}')", ARRAY_A);
} else {
    $current_lab = CI_Base_Lab::get_current_lab();
}



if (isset($_REQUEST['debug'])) {
    var_dump($sql_clinic);
}
?>
<div class="wrap_form wrap">

    <?php if ($act == 'print'): ?>
        <input type="button" class="button print_button" onclick="window.print()" value="Print" />
        <?php
        $upload_dir = tr_wp_upload_dir();
        $logo = $current_lab['lab_logo'];
        $logo_path = $upload_dir['basedir'] . '/logos/' . $logo;
        $logo_url = $upload_dir['baseurl'] . '/logos/' . $logo;
        ?>
        <div style="text-align: center">
            <img src="<?php echo $logo_url ?>" align="center" style="max-width:400px" />
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="need_validate" id="requisition_form">
        <h2>Allergy Requisition Form</h2>
        <?php Tr_Session_Class::show() ?>

        <div class="right foutrow">
            <label>Accession Number</label>
            <input type="text" name="bat[assen]" value="<?php echo @$bat['assen'] ?>" required="" />
        </div>

        <div class="frow blabel hlabel">
            <div class="col5">
                <?php if ($blog_id == 1) {
                ?>
                    <label>Lab</label>
                    <select name="bat[lab_id]" required>
                        <option value="">Select Lab</option>
                        <?php
                        $las = $wpdb->get_results("select * from ci_labs where (lab_is_ref=0 or lab_is_ref=2) order by lab_dba");
                        foreach ($las as $l) {
                            $selected = $l->lab_ID == $bat['lab_id'] ? 'selected' : '';
                            echo '<option value="' . $l->lab_ID . '" data-short="' . $l->lab_dba_init . '">' . $l->lab_dba . '</option>';
                        }
                        ?>
                    </select>
                <?php
                }
                ?>
                <label>PRACTICE INFORMATION</label>
                <?php
                if (tr_current_role_in(array('lab_tech', 'lab_sec'))) {
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
                <?php if (tr_current_role_in(array('lab_tech', 'lab_sec'))) {
                ?>
                    <input type="text" class="requesting_physician_auto cfname" name="bat[requesting_physician]" value="<?php echo @$bat['requesting_physician'] ?>" required />
                    <input type="hidden" name="bat[doc_ID]" value="<?php echo @$bat['doc_ID'] ?>">
                <?php
                } else {
                    tr_select_option('bat[doc_ID]', $docs, @$bat['doc_ID'], array('attr' => 'required'));
                }
                ?>
                <label>Collector Name</label>
                <?php if (count($collectors) > 0 && $current_role != 'collector'): ?>
                    <select name="col[col_ID]" id="col_id_sel" required="required">
                        <?php
                        foreach ($collectors as $collector) {
                            $selected = $collector['col_ID'] == $bat['col_ID'] ? 'selected' : '';
                            echo '<option value="' . $collector['col_ID'] . '" ' . $selected . ' data-phone="' . $collector['col_phone'] . '">' . tr_trim_name(@$collector['col_first'] . ' ' . @$collector['col_last']) . '</option>';
                        }
                        $collector['col_phone'] = $collectors[0]['col_phone'];
                        ?>
                    </select>
                <?php else:
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

                <div class="dagnostic_code" data-name="bat[dagnostic_codes][]">
                    <ul id="list_dagnostic_codes">
                        <?php
                        $bat['dagnostic_codes'] = explode(',', @$bat['dagnostic_codes1']);
                        if (empty($bat['mid'])) {
                            $bat['dagnostic_codes'] = array();
                            /**
                            foreach($dagnostic_codes as $k =>$vl)
                            {
                            if(empty($k))continue;
                            $bat['dagnostic_codes'][] = $k;
                            }
                             */
                        }
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
                    <?php if ($act != 'print' && $act != 'view'): ?>
                        <div class="dagnostic_edit">
                            <?php tr_select_option('dagnostic_code_select', $dagnostic_codes, null) ?>
                            <a class="itip thickbox" href="<?php echo TRCPDD_URL ?>images/ICD10Crosswalk.png"></a>
                            <input type="text" id="dagnostic_code_text" placeholder="Enter Additional Codes" />
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col25">

                <label>SPECIMEN INFORMATION</label>
                <div class="col2 ilabel label2">
                    <label class="bold">Type of Specimen:</label>
                    <div><?php tr_radio('bat[type_of_specimen]', $type_of_specimens, @$bat['type_of_specimen'], array('attr' => 'required')) ?></div>

                </div>
                <div class="col2 ">
                    <label>Date of Collection</label>
                    <input type="text" class="hasdatetime <?php if (tr_get_option('ci_pdf_data', 'limit_doc')) echo 'limitfuture' ?>" name="bat[doc]" value="<?php echo empty($bat['doc']) ? 'auto' : $bat['doc'] ?>" <?php if (!empty($bat['doc'])) echo 'readonly' ?> required="" />
                    <div class="ilabel" style="margin-top:5px;">
                        <label class="bold">Fasting</label>
                        <?php tr_radio('bat[fasting]', array(0 => 'No', 1 => 'Yes'), @$bat['fasting']) ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="frow">
            <h4>PATIENT INFORMATION</h4>
        </div>

        <div class="frow blabel">
            <div class="col4">
                <label>Social Security Number</label>
                <input type="text" name="pa[pa_ssn]" id="pa_ssn" maxlength="12" value="<?php echo @$pa['pa_ssn'] ?>" <?php if ($act != 'print') echo 'style="width:60%"' ?> required="" />
                <input type="hidden" name="pa[pa_ID]" value="<?php echo @$pa['pa_ID'] ?>" />

                <?php if ($act != 'print'): ?>
                    <a class="button find_patient samefheight" href="#" data-type="ssn">find</a>
                <?php endif; ?>
            </div>
            <div class="col4">
                <label>Last Name</label>
                <input type="text" class="cfname" name="pa[pa_last]" value="<?php echo @$pa['pa_last'] ?>" required="" />
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
                <?php if ($act != 'print'): ?>
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
                <div class=" blabel">
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
                <input type="text" class="hasdate birthday limitfuture" name="pa[pa_dob]" value="<?php echo @$pa['pa_dob'] ?>" required="" />
            </div>
            <div class="col4">
                <label>Phone Number</label>
                <input type="text" class="has_phone" name="pa[pa_phone]" value="<?php echo @$pa['pa_phone'] ?>" required="" />
            </div>
            <div class="col4">
                <label>Email</label>
                <input type="email" name="pa[pa_email]" value="<?php echo @$pa['pa_email'] ?>" />
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
                        <input type="text" class="cfname" name="ins1[ins_company]" value="<?php echo @$ins1['ins_company'] ?>" required="" />
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
                    <div class="col3"></div>
                </div>
                <div class="frow">
                    <div class="col3">
                        <label>Insurance Company Name</label>
                        <input type="text" class="cfname" name="ins2[ins_company]" value="<?php echo @$ins2['ins_company'] ?>" />
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
        <div class="frow">
            <h4>Testing Requested</h4>
        </div>
        <div class="frow">
            <p>NOTE: Only tests that are medically necessary for the diagnosis or treatment of the patient may be submitted for Medicare and / or Medicaid reimbursement. Documentation supporting medical necessity must be present in the medical record.</p>
        </div>
        <div class="frow blabel">
            <?php
            $tests = array(
                'Food-General' => 0,
                'Mix(Egg white, Milk, Fish, Wheat, Soya Bean, Peanut)' => 'fx5',
                'Apple' => 'f49',
                'Avocado' => 'f96',
                'Banana' => 'f92',
                'Barley' => 'f6',
                'BlueBerry' => 'f288',
                'Buckwheat' => 'f11',
                'Cacao' => 'f93',
                'Carrot' => 'f31',
                'Celery' => 'f85',
                'Cherry' => 'f242',
                'Chick Pea' => 'f309',
                'Cucumber' => 'f244',
                'Garlic' => 'f47',
                'Gluten' => 'f79',
                'Grapen' => 'f259',
                'Green Bean' => 'f315',
                'Green Pepper' => 'f263',
                'Kiwi' => 'f84',
                'Lentil' => 'f235',
                'Maize (corn)' => 'f8',
                'Mango' => 'f91',
                'Mushroom' => 'f212',
                'Mustard' => 'f89',
                'Oat' => 'f7',
                'Onion' => 'f48',
                'Orange' => 'f33',
                'Pea' => 'f12',
                'Peach' => 'f95',
                'Pineapple' => 'f210',
                'Potato' => 'f35',
                'Raspberry' => 'f343',
                'Race' => 'f9',
                'Rye' => 'f5',
                'Soya Bean' => 'f14',
                'Strawberry' => 'f44',
                'Tomato' => 'f25',
                'Watermelon' => 'f329',
                'Wheat' => 'f4',
                'Yeast' => 'f45',

                'Foods - Eggs, Milk & Meat' => 0,
                'Alpha Lactalbumin' => 'f76',
                'Beef' => 'f27',
                'Beta lactoglobulin' => 'f77',
                'Casein' => 'f78',
                'Cheese, Cheddar style' => 'f81',
                'Chicken Meat' => 'f83',
                'Cow\'s Whey' => 'f236',
                'Egg' => 'f245',
                'Egg Evalbumin' => 'f232',
                'Egg Evomucoid' => 'f233',
                'Egg white' => 'f1',
                'Egg Yolk' => 'f75',
                'Milk' => 'f2',
                'Pork' => 'f26',
                'Turkey Meat' => 'f284',

                'Peanut Component' => 0,
                'Peanut Component panel(rAra h1, rAra h2, rAra h3, rAra h8, rAra h9)' => 'pc5',
                'Peanut rAra h1' => 'f422',
                'Peanut rAra h2' => 'f423',
                'Peanut rAra h3' => 'f424',
                'Peanut rAra h8' => 'f352',
                'Peanut rAra h9' => 'f427',

                'Foods - Nuts & Seeds' => 0,
                'Nut Mix(Peanut, Hazel Nut, Brazil Nut, Almond, Coconut)' => 'fx1',
                'Almond' => 'f20',
                'Brazil Nut' => 'f18',
                'Cashew Nut' => 'f202',
                'Coconut' => 'f36',
                'Hazel Nut' => 'f17',
                'Linseed' => 'f333',
                'Macadamia Nut' => 'f345',
                'Peanut' => 'f13',
                'Pecan Nut' => 'f201',
                'Pine Nut' => 'f253',
                'Pistachio' => 'f203',
                'Poppy Seed' => 'f224',
                'Sesame Seed' => 'f10',
                'Sunflower Seed' => 'k84',
                'Walnut' => 'f256',

                'Foods - Seafood & Mollusks' => 0,
                'Seafood Mix(fish, Shrimp, Blue Mussel, Tuna, Salmon)' => 'fx2',
                'Blue Mussel' => 'f37',
                'Clam' => 'f207',
                'Crab' => 'f23',
                'Fish(cod)' => 'f3',
                'Haddock' => 'f42',
                'Halibut' => 'f303',
                'Lobster' => 'f80',
                'Oyster' => 'f290',
                'Salmon' => 'f41',
                'Scallop' => 'f338',
                'Shrimp' => 'f24',
                'Sole' => 'f337',
                'Squid' => 'f258',
                'Trout' => 'f204',
                'Tuna' => 'f40',
                'Whitefish' => 'f384',

                'Dust & Mites' => 0,
                'House Dust Mix(House Dust,D.pteronyssinus, D.farinae, B.germanica' => 'hx2',
                'Dust Mite (D.pteronyssinus)' => 'd1',
                'Dust Mite (D.farinae)' => 'd2',
                'Dust Mite (D.microceras)' => 'd3',
                'Dust Mite (Hollister-Stier)' => 'h2',

                'Animals' => 0,
                'Cat Dander' => 'e1',
                'Chicken Feathers' => 'e85',
                'Dog Dander' => 'e5',
                'Duck Feathers' => 'e86',
                'Horse Dander' => 'e3',
                'Rabbit Epithelium' => 'e82',

                'Grass & Weed Pollens' => 0,
                'Grass Mix (Cocksfoot, Meadow, Rye, Timothy,Meadow Grass)' => 'gx1',
                'Weed Mix (Common Ragweed, Mugwort,Plaintain, Goosefoot, Saltwort)' => 'wxl',
                'Bermuda Grass' => 'g2',
                'Brame Grass' => 'g11',
                'Cocksfoot' => 'g3',
                'Johnson Grass' => 'g10',
                'Meadow Grass' => 'g8',
                'Sweet Vernal Grass' => 'g1',
                'Timothy Grass' => 'ge',
                'Common Ragweed' => 'w1',
                'Dandelion' => 'w8',
                'False Ragweed' => 'w4',
                'Firebush' => 'w17',
                'Giant Ragweed' => 'w3',
                'Goldenrod' => 'w12',
                'Mugwort' => 'w6',
                'Nettle' => 'w20',
                'Plaintain' => 'w9',
                'Saltwort' => 'w11',
                'Sunflower' => 'w20',

                'Tree Pollens' => 0,
                'Tree Mix 1 (Oak, Elm, Sycamore, Willow,Cottonwood)' => 'tx4',
                'Tree Mix 2 (Box-elder, Birch Beech, Oak, Walnut)' => 'tx6',
                'Oak' => 't7',
                'Common Silver Birch' => 't3',

                'Insects & Venoms' => 0,
                'Common Wasp (Yellow Jacket)' => 'i3',
                'Honeybee' => 'i1',
                'Mosquito' => 'i71',
                'Paper Wasp' => 'i4',
                'White-Faced Hornet' => 'i2',
                'Yellow Hornet' => 'i5',

                'Occupational' => 0,
                'IDI - Isocyanate' => 'k77',
                'MDI - Isocyanate' => 'k76',
                'TDI - Isocyanate' => 'k75',
                'Latex (Hevea brazill crisis)' => 'k82',

                'Moulds & Yeast' => 0,
                'Mould Mix (Cladosporium herbarum, Aspergillus fumigatus,Candida albicans, Alternaria alternate, Setomelanomma rostrata Pen icillium chrysogenum)' => 'mx2',
                'Alternaria alternate' => 'm6',
                'Aspergillus fumigatus' => 'm3',
                'Aspergillus niger' => 'm207',
                'Candida albicans' => 'm5',
                'Cladosporium herbarum' => 'm207',
                'Renicillium chrysogenum' => 'm1',

                'Drugs' => 0,
                'Amoxicilloyl' => 'c6',
                'Ampicilloyl' => 'c5',
                'Penicilloyl G' => 'c1',
                'Penicilloyl V' => 'c2',

                'Billing Codes' => 0,
                'Allergy Testing - lst Allergen' => '350-1',
                'Allergy Testing - 2nd Allergen' => '350-2',
                'Allergy Testing - 3rd Allergen' => '350-3',
                'Allergy Testing - 4th Allergen' => '350-4',
                'Allergy Testing - 5th Allergen' => '350-5',
                'Allergy Testing - 6th Allergen' => '350-6',
                'Allergy Testing - 7th Allergen' => '350-7',
                'Allergy Testing - 8th Allergen' => '350-8',
                'Allergy Testing - 9th Allergen' => '350-9',
                'Peanut Component - 1st' => '351-1',
                'Peanut Component - 2nd' => '351-2',
                'Peanut Component - 3rd' => '351-3',
                'Peanut Component - 4th' => '351-4',
                'Peanut Component Panel' => '352',
                'Allergy Testing - 1st Mix' => '353-1',
                'Allergy Testing - 2nd Mix' => '353-2',
                'Allergy Testing - 3rd Mix' => '353-3',
                'Allergy Testing - 4th Mix' => '353-4'


            );

            $count_col = round(count($tests) / 3);
            $i = 0;

            $panel_tests_requested_str = implode(',', @$bat['panel_tests_requested']);
            foreach ($tests as $test => $vl) {
                $checked = in_array($test, $bat['panel_tests_requested']);
                if (strpos($test, ',') > 0 && stripos($panel_tests_requested_str, $test) !== false) {
                    $checked = true;
                }
                if ($i % $count_col == 0) {
                    if ($i > 0) echo '</div>';
                    echo '<div class="col3">';
                }
                if ($vl === 0) {
            ?>
                    <label class="bold" style="margin-top:5px;"><?php echo $test ?></label>
                <?php
                } else {

                ?>
                    <label><input type="checkbox" name="bat[panel_tests_requested][]" <?php checked($checked) ?>
                            value="<?php echo $test ?>" /> <?php echo $test ?>, <?php echo $vl ?> </label>

            <?php
                }
                $i++;
            }
            echo '</div>';
            ?>
        </div>

        <div class="frow">
            I hereby authorize the release of medical information related to the service described herein and authorize payment directly to LabPro, Inc.
            I agree to assume responsibility for payment of charges for laboratory services that are not covered by my healthcare insurer.
        </div>
        <div class="frow blabel">
            <div class="col2">
                <br>
                <label>Patient Signature:</label>
                <?php
                $fname = '';
                if (@$bat['mid'] > 0) {

                    $fname = !empty($bat['patient_signature']) ? $bat['patient_signature'] : $batch_key . '_patient.png';
                    if (file_exists($sign_dir['path'] . '/' . $fname) && filesize($sign_dir['path'] . '/' . $fname) > 0) {
                ?>
                        <img class="sign_img" src="<?php echo $sign_dir['url'] . '/' . $fname . '?' . time(); ?>" style="width:300px" />

                    <?php
                    } elseif (@$_REQUEST['page'] == 'ci_short_list') {
                    ?>
                        <div class="card_file">
                            <a class="button selectfile" href="#">Choose Signature Image</a>
                            <span></span>
                            <input type="file" name="patient_signature" accept="image/*" />
                        </div>
                <?php
                    }
                } else {
                    echo tr_signature(array('name' => 'patient_signature'));
                } ?>

                <input type="hidden" name="img_patient_signature" value="<?php echo $fname ?>" />
            </div>
            <div class="col2">
                <label><b>Authorized Healthcare</b></label>
                <label>Provider Signature:</label>
                <?php
                $fname = '';
                if (@$bat['mid'] > 0) {
                    $fname = !empty($bat['provider_signature']) ? $bat['provider_signature'] : $batch_key . '_provider.png';
                    if (file_exists($sign_dir['path'] . '/' . $fname) && filesize($sign_dir['path'] . '/' . $fname) > 0) {
                ?>
                        <img class="sign_img" src="<?php echo $sign_dir['url'] . '/' . $fname . '?' . time(); ?>"
                            style="width:300px" />
                    <?php
                    } else {
                    ?>
                        <div class="card_file">
                            <a class="button selectfile" href="#">Choose Signature Image</a>
                            <span></span>
                            <input type="file" name="provider_signature" accept="image/*" />
                        </div>
                <?php
                    }
                } else {
                    echo tr_signature(array('name' => 'provider_signature'));
                } ?>
                <input type="hidden" name="img_provider_signature" value="<?php echo $fname ?>" />
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
            $col_num = ($action_form != 'ci_save_allergy') ? 'col2' : 'col4';
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
                                echo '<a href="' . $card_dir['url'] . '/' . $fname . '?r=' . time() . '" target=_blank><img src="' . $card_dir['url'] . '/' . $fname . '?r=' . time() . '" style="width:100%"></a>';
                            }
                            ?>
                        </span>
                        <input type="file" <?php if ($rq1) echo 'data-required="1" required ' ?> name="ins_card_front" accept="image/*" />
                    </div>
                <?php
                } else {
                    echo '<label>Ins Card Front</label>';

                    if (file_exists($card_dir['path'] . '/' . $fname) && filesize($card_dir['path'] . '/' . $fname) > 0) {
                        echo '<a href="' . $card_dir['url'] . '/' . $fname . '?r=' . time() . '" target=_blank><img src="' . $card_dir['url'] . '/' . $fname . '?r=' . time() . '" style="width:100%"></a>';
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
                                echo '<a href="' . $card_dir['url'] . '/' . $fname . '?r=' . time() . '" target=_blank><img src="' . $card_dir['url'] . '/' . $fname . '?r=' . time() . '" style="width:100%"></a>';
                            }
                            ?>
                        </span>
                        <input type="file" name="ins_card_back" />
                    </div>
                <?php
                } else {
                    echo '<label>Ins Card Back</label>';

                    if (file_exists($card_dir['path'] . '/' . $fname) && filesize($card_dir['path'] . '/' . $fname) > 0) {
                        echo '<a href="' . $card_dir['url'] . '/' . $fname . '?r=' . time() . '" target=_blank><img src="' . $card_dir['url'] . '/' . $fname . '?r=' . time() . '" style="width:100%"></a>';
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
                                echo '<a href="' . $card_dir['url'] . '/' . $fname . '?r=' . time() . '" target=_blank><img src="' . $card_dir['url'] . '/' . $fname . '?r=' . time() . '" style="width:100%"></a>';
                            }
                            ?>
                        </span>
                        <input type="file" name="patient_id_front" />
                    </div>
                <?php
                } else {
                    echo '<label>Patient ID Front</label>';

                    if (file_exists($card_dir['path'] . '/' . $fname) && filesize($card_dir['path'] . '/' . $fname) > 0) {
                        echo '<a href="' . $card_dir['url'] . '/' . $fname . '?r=' . time() . '" target=_blank><img src="' . $card_dir['url'] . '/' . $fname . '?r=' . time() . '" style="width:100%"></a>';
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
                                echo '<a href="' . $card_dir['url'] . '/' . $fname . '?r=' . time() . '" target=_blank><img src="' . $card_dir['url'] . '/' . $fname . '?r=' . time() . '" style="width:100%"></a>';
                            }
                            ?>
                        </span>
                        <input type="file" name="patient_id_back" />
                    </div>
                <?php
                } else {
                    echo '<label>Patient ID Back</label>';

                    if (file_exists($card_dir['path'] . '/' . $fname) && filesize($card_dir['path'] . '/' . $fname) > 0) {
                        echo '<a href="' . $card_dir['url'] . '/' . $fname . '?r=' . time() . '" target=_blank><img src="' . $card_dir['url'] . '/' . $fname . '?r=' . time() . '" style="width:100%"></a>';
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

        <?php if (!empty($action_form) && $act != 'print'): ?>


            <div class="frow">
                <div class="col3">
                    <label>Status</label>
                    <?php
                    $batch_statuses = array(
                        0 => 'None',
                        1 => 'Received',
                        2 => 'Completed',
                        3 => 'Rejected'
                    );
                    $status_options = tr_pdd_get_batch_statuses_on_hold(tr_pdd_get_batch_statuses(false, false, false, 'allergy'), $bat['batch_status'], $bat['mid'], $table_batch, 'allergy');
                    $disabled_keys = tr_pdd_get_batch_statuses_disabled_keys('allergy', 'show_in_req_form', $bat['batch_status']);
                    tr_select_option('bat[batch_status]', $status_options, @$bat['batch_status'], ['disabled_options' => $disabled_keys]);
                    ?>
                </div>
            </div>


            <div class="frow">
                <input type="submit" class="button-primary" value="Submit">
                <?php if (@$_REQUEST['page'] == 'ci_short_list') {
                ?>
                    <input type="hidden" name="bat[short_completed]" value="0">
                    <label><input type="checkbox" name="bat[short_completed]" value="1" <?php checked(@$bat['short_completed'], 1) ?>> Completed</label>
                <?php
                } ?>

                <?php if (@$mid == 0 || $saved_id > 0): ?>
                    <input type="submit" class="button only_save_form right" name="only_save_form" value="Save">
                    <input type="hidden" name="saved_id" value="<?php echo $saved_id ?>" />
                <?php endif; ?>

                <input type="hidden" name="tr_action" value="<?php echo $action_form ?>" />
                <input type="hidden" name="bat[is_dna]" value="3" />
                <?php if (@$mid > 0): ?>
                    <input type="hidden" name="bat[mid]" value="<?php echo $mid ?>" />
                <?php endif; ?>
            </div>
        <?php endif; ?>


        <?php trpdd_form_unique_id() ?>

    </form>
</div>
<?php if ($act == 'print'): ?>
    <script>
        window.print();
    </script>
<?php endif; ?>
<script>
    jQuery(function($) {
        $('[name="bat[lab_id]"]').change(function() {
            var $lab = $('option[value="' + $(this).val() + '"]', $(this)).data('short');
            $('#lab_name_change_div').html($lab);
        })
    })
</script>

<?php
if (isset($_GET['debug'])) {
    $content = ob_get_clean();
    $content = str_replace('required', 'datarequired', $content);
    echo $content;
}
?>
