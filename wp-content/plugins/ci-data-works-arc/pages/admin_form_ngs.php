<?php
global $wpdb;
global $current_role;
global $blog_id;


$user_id = get_current_user_id();
if (isset($_GET['debug'])) {
    ob_start();
}

$act = isset($_REQUEST['act']) ? $_REQUEST['act'] : @$act;
$table_batch = 'ci_batches_ngs';
$tabs = array(
    '' => 'NGS Req Form',
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
    'Z13.0' => 'Z13.0 - Encounter for screening for diseases of the blood and blood-forming organs and certain disorders involving the immune mechanism',
    'Z13.228' => 'Z13.228 - Encounter for screening for other metabolic disorders',
    'Z31.440' => 'Z31.440 - Encounter of male for testing for genetic disease carrier status for procreative management',
    'Z31.430' => 'Z31.430 - Encounter of female for testing for genetic disease carrier status for procreative management',
    'Z13.79' => 'Z13.79 - Encounter for other screening for genetic and chromosomal anomalies',
    'Z15.89' => 'Z15.89 - Genetic susceptibility to other disease',

    'Z81.0' => 'Z81.0 - Family history of intellectual disabilities the immune mechanism',
    'Z34.01' => 'Z34.01 -  Encounter for supervision of normal first pregnancy, first trimester',
    'Z34.02' => 'Z34.02 -  Encounter for supervision of normal first pregnancy, second trimester',
    'Z34.03' => 'Z34.03 -  Encounter for supervision of normal first pregnancy, third trimester',
    'Z34.80' => 'Z34.80 -  Encounter for supervision of other normal pregnancy, unspecified trimester',
    'Z84.81' => 'Z84.81 -  Family history of carrier of genetic disease',
    'Z81.8 ADHD' => 'Z81.8 -  Family history of ADHD',
    'Z81.8 Autism' => 'Z81.8 -  Family history of Autism',
    'Z81.8 Anxiety' => 'Z81.8 -  Family history of Anxiety',
    'Z81.8 Speech Impediments' => 'Z81.8 -  Family history of Speech Impediments',
    'Z81.8 Hyperactive Behavior' => 'Z81.8 -  Family history of Hyperactive Behavior',
    'Z83.2' => 'Z83.2 -  Family history of Sickle Cell Anemia',

    'Z82.0 Seizures' => 'Z82.0 -  Family history of Seizures',
    'Z82.0 Spinal Muscular Atrophy' => 'Z82.0 -  Family history of Spinal Muscular Atrophy',
    'Z83.49 Cystic Fibrosis' => 'Z83.49 -  Family history of Cystic Fibrosis',
    'Z83.49 Tay Sachs' => 'Z83.49 -  Family history of Tay Sachs',
    'Z82.69' => 'Z82.69 -  Family history of other diseases of the musculoskeletal system and connective tissue',


);


$type_of_specimens = tr_pdd_get_type_of_specimens('ngs');

$current_medications = tr_pdd_get_current_medications();

$cli = array();
$collector = array();
$current_lab = array();
$clinics = array('' => 'Please Select');
$docs = array('' => 'Please Select');
$where_clis = " and cli.cli_clinic !='' and cli.cli_clinic !='0' ";
$cli_id = 0;



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
    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where .= " and ba.cli_ID = {$current_clinic} ";
    }

    $col_id = get_user_meta(get_current_user_id(), '_collector_id', true);
    $collector = $wpdb->get_row("select * from ci_collector where col_ID ={$col_id}", ARRAY_A);
    $rep_id = $collector['rep_ID'];
    //$where_clis = " and cli.`cli_rep-ID`={$rep_id} ";
    $where_clis .= " and cc.col_ID = {$col_id} ";
} else if ($current_role == 'distributor') {
    $dis_id = $wpdb->get_var("select dis_ID from ci_distributors where dis_user_ID = {$user_id}");

    $arr_marketing_groups = $wpdb->get_results("SELECT * FROM `ci_marketing_groups` where `gr_distributor_ID`= {$dis_id} and `gr_inactive`='0'");
    $g_ids = array(-1);
    foreach ($arr_marketing_groups as $g) {
        $g_ids[] = $g->gr_ID;
    }
    $g_ids = implode(',', $g_ids);

    $where_rep .= " and (`rep_marketing_group_ID` IN ({$g_ids}) or rep_distributor_ID={$dis_id}) ";

    $where_clis .= " and (cli_distributor_ID={$dis_id} or (`rep_marketing_group_ID` IN ({$g_ids}) or rep_distributor_ID={$dis_id})) ";
    $where_group .= " and gr_distributor_ID = {$dis_id} ";
} else if ($current_role == 'office_manager' || $current_role == 'clinic') {
    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where_clis .= " and cli.cID = {$current_clinic} ";
    } else {
        if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
            $cid = get_user_meta($user_id, '_clinic_id', true);
            $cli_ids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
            $cli_ids = implode(',', $cli_ids);

            $where_clis .= " and cli.cID in ( {$cli_ids} )";
        }
    }
} else if ($current_role == 'doctor') {
    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where .= " and ba.cli_ID = {$current_clinic} ";
    }

    $doc_id = get_user_meta($user_id, '_doc_id', true);
    $clinics = $wpdb->get_col("select cli_ID from ci_clinic_doc where doc_ID={$doc_id} ");
    $clinics = implode(',', $clinics);
    $where_clis .= " and cli.cID in ({$clinics}) ";
    $where_doctor .= " and doc.doc_ID = {$doc_id} ";
}
$required_sign = true;
if (tr_current_role_in(array('administrator', 'administrator_p', 'AdminCIDW', 'labsecman', 'leadlabsecman', 'labmanager'))) {
    $required_sign = false;
}


if ($tab == 'current_batches') {
    include('admin_req_list_ngs.php');
    return;
} else if ($tab == 'saved_form') {
    if (!empty($act) && $act != 'delete') {
        $saved_id = $_REQUEST['id'];
    } else {
        include('admin_req_list_ngs_saved.php');
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
    $cli = $wpdb->get_row("select * from ci_clinic where `cID`={$cli_id}", ARRAY_A);
    $pa = $wpdb->get_row("select pa.* from ci_patients pa
                           where pa.pa_ID = '{$bat['patient_id']}' ", ARRAY_A);
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
        $action_form = 'ci_save_ngs';
        $mid = $id;
    }
} else {
    $bat['panel_tests_requested'] = array();
    $bat['custom_profile'] = '';
    $bat['medication'] = array();
    $bat['current_medication'] = array();
    $bat['poc_perform'] = '0';


    $action_form = 'ci_save_ngs';

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
                    where cli.cli_status = 1  {$where_clis} group by cli.cID order by cli.cli_clinic";
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

    <?php if ($act == 'print') : ?>
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
        <h2>NGS Requisition Form</h2>
        <?php Tr_Session_Class::show() ?>

        <div class="right foutrow">
            <label>Accession Number</label>
            <input type="text" name="bat[assen]" value="<?php echo @$bat['assen'] ?>" required="" />
        </div>

        <div class="frow blabel hlabel">
            <div class="col5">
                <?php
                $las = $wpdb->get_results("select * from ci_labs  where (lab_is_ref=0 or lab_is_ref=2) order by lab_dba");
                $labs = array('' => 'Select Lab');
                foreach ($las as $l) {
                    $labs[$l->lab_ID] = $l->lab_dba;
                }
                ?>
                <label>Lab</label>
                <?php tr_select_option('bat[lab_id]', $labs, $bat['lab_id'], array('attr' => 'required')) ?>
                <?php
                ?>
                <label>PRACTICE INFORMATION</label>
                <?php
                if (tr_current_role_in(array('lab_tech', 'lab_sec'))) {
                ?>
                    <input type="text" class="cfname requesting_practice_auto" name="bat[requesting_practice]" value="<?php echo @$bat['requesting_practice'] ?>" required />
                    <input type="hidden" name="bat[cli_ID]" value="<?php echo @$bat['cli_ID'] ?>">
                <?php
                } else { ?>
                    <select name="bat[cli_ID]" required id="cli_change" class="njs_select2">
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
                <?php if (count($collectors) > 0 && $current_role != 'collector') : ?>
                    <select name="col[col_ID]" id="col_id_sel" required="required" class="njs_select2">
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
                <input type="text" class="has_phone" name="col[col_phone]" value="<?php echo @$collector['col_phone'] ?>" />
            </div>
            <div class="col5">
                <label>DIAGNOSTIC CODES</label>
                <div class="dagnostic_code" data-name="bat[dagnostic_codes][]">
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
            <div class="col25">

                <label>SPECIMEN INFORMATION</label>
                <div class="col2 ilabel label2">
                    <label>Type of Specimen:</label>
                    <div><?php tr_radio('bat[type_of_specimen]', $type_of_specimens, @$bat['type_of_specimen'], array('attr' => 'required')) ?></div>
                </div>
                <div class="col2 small">
                    <label>Date of Collection</label>
                    <input type="text" class="hasdatetime <?php if (tr_get_option('ci_pdf_data', 'limit_doc')) echo 'limitfuture' ?>" name="bat[doc]" value="<?php echo empty($bat['doc']) ? 'auto' : $bat['doc'] ?>" <?php if (!empty($bat['doc'])) echo 'readonly' ?> required="" />
                    <label>Date Received</label>
                    <input type="text" class="hasdatetime today" name="bat[spec_received]" value="<?php echo @$bat['spec_received'] ?>" required="" />

                </div>
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

        <div class="frow">
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

                <?php
                $keys = array(
                    'Comprehensive' => '<b>Gene (#SNPS)</b> CYP1A2 (4), CYP2B6 (8), CYP2C9 (8), CYP2C19 (8), CYP2D6 (15), CYP3A4 (6), CYP3A5 (5), ANKK1/DRD2 (1), APOE (2),COMT (1), FACTOR II [F2] (1), FACTOR V [F5] (1), MTHFR (2),OPRM1 (1), SLCO1B1 (2), VKORC1 (1)',
                    'Pain Management' => '<b>Gene (#SNPS)</b> CYP1A2( 4), CYP2B6 (8), CYP2C9 (8), CYP2C19 (8), CYP2D6 (15), CYP3A4 (6), CYP3A5 (5), OPRM1(1)',
                    'Psychiatry' => '<b>Gene (#SNPS)</b> CYP1A2(4), CYP2B6(8), CYP2C9 (8), CYP2C19(8),CYP2D6(15), CYP3A4 (6), CYP3A5(5), ANKK1/DRD2 (1),COMT (1), MTHFR (2)',
                    'Cardiology' => '<b>Gene (#SNPS)</b> CYP2C9 (8), CYP2C19 (8), CYP2D6 (15), CYP3A4 (6),CYP3A5 (5), APOE (2), FACTOR II [F2] (1), FACTOR V [F5] (1),MTHFR (2), SLCO1B1 (2), VKORC1 (1)',
                    'Urology' => '<b>Gene (#SNPS)</b> CYP2D6 (15), CYP3A4 (6),CYP3A5 (5)'
                );
                $i = 0;
                foreach ($keys as $k => $txt) {
                ?>
                    <div class="frow blabel testing_ordered_box">
                        <label>
                            <input type="checkbox" name="testing_ordered[<?php echo $i ?>]" value="<?php echo $k ?>">
                            <strong class="tooltip" title="<?php echo ($txt) ?>"><?php echo $k ?> Panel: <i class="itip"></i></strong>
                        </label>
                        <table class="testing_ordered_list">
                            <tr>
                                <td><label>- <input type="radio" name="testing_ordered_add[<?php echo $i ?>][0]" value="-" /></label> </td>
                                <td><label>+ <input type="radio" name="testing_ordered_add[<?php echo $i ?>][0]" value="+" /></label> </td>
                                <td><input type="text" name="testing_ordered_name[<?php echo $i ?>][0]" /></td>
                            </tr>
                        </table>
                        <div class="alignright"><a class="button add_new_testing" data-id="1">+</a></div>
                    </div>
                <?php
                    $i++;
                }
                ?>
                <script>
                    jQuery(function($) {
                        $('.add_new_testing').click(function() {
                            var $nid = parseInt($(this).data('id'));
                            var $tb = $(this).parents('.testing_ordered_box').find('.testing_ordered_list');
                            $new = $tb.find('tr:first-child').html();
                            $new = $new.replace(/\[0\]\"/g, '[' + $nid + ']"');
                            $new = $('<tr>' + $new + '</tr>');
                            $new.find('input').val('');
                            $tb.append($new);
                            $(this).data('id', $nid + 1);
                        });
                        $('.tooltip').tooltip({
                            content: function() {
                                return $(this).attr('title');
                            }
                        });
                    })
                </script>
            </div>
        </div>
        <div class="frow">
            STATEMENT OF CONSENT AND RELEASE I hereby certify that the information provided is true and accurate. I consent to the collection of specimens from myself and any listed
            child for the purpose of NGS testing. <?php echo @$current_lab['lab_dba_init'] ?> reserves the right to re-collect samples for retesting and to store samples for future additional tests, if necessary. I understand that while
            NGS testing is highly accurate and widely accepted, as in all testing there is a possibility of delay or error. I understand that samples and the NGS they contain become the
            exclusive property of <?php echo @$current_lab['lab_dba_init'] ?>. I also understand that this testing is for informational purposes and prescription drug regimes should never be altered without consulting a physician.
            <?php echo @$current_lab['lab_dba_init'] ?> will not release any personal, identifiable information of any kind to a third party without my express written instructions. I also understand that <?php echo @$current_lab['lab_dba_init'] ?> reserves the right to
            provide deidentified information of a statistical nature to accrediting agencies and reserves the right to use such anonymous information
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
                        <img class="sign_img" src="<?php echo $sign_dir['url'] . '/' . $fname . '?' . time(); ?>" style="width:300px" />
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
            $col_num = ($action_form != 'ci_save_ngs') ? 'col2' : 'col4';
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

        <?php if (!empty($action_form) && $act != 'print') : ?>


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
                    echo tr_select_option('bat[batch_status]', tr_pdd_get_batch_statuses(false, false, false, 'ngs', 'show_in_req_form'), @$bat['batch_status']) ?>
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

                <?php if (@$mid == 0 || $saved_id > 0) : ?>
                    <input type="submit" class="button only_save_form right" name="only_save_form" value="Save">
                    <input type="hidden" name="saved_id" value="<?php echo $saved_id ?>" />
                <?php endif; ?>

                <input type="hidden" name="tr_action" value="<?php echo $action_form ?>" />
                <input type="hidden" name="bat[is_dna]" value="14" />
                <?php if (@$mid > 0) : ?>
                    <input type="hidden" name="bat[mid]" value="<?php echo $mid ?>" />
                <?php endif; ?>

            </div>
        <?php endif; ?>
        <?php trpdd_form_unique_id() ?>

    </form>
</div>
<?php if ($act == 'print') : ?>
    <script>
        window.print();
    </script>
<?php endif; ?>


<?php
if (isset($_GET['debug'])) {
    $content = ob_get_clean();
    $content = str_replace('required', 'datarequired', $content);
    echo $content;
}
