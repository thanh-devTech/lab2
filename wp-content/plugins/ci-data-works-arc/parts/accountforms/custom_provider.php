<?php
global $wpdb, $current_role, $current_lab_id;
$act = isset($_REQUEST['act']) ? $_REQUEST['act'] : @$act;
$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;
$user_id = get_current_user_id();
$use_select2 = true;
$can_edit = true;
$can_delete = true;

if ($act == 'print') {
?>
    <link href="<?php echo TRCPDD_URL ?>css/print.min.css?<?php echo TRCPDD_VS ?>" media="all" type="text/css" rel='stylesheet' />
<?php
} else {
    include_once(TRCPDD_PATH . 'pages/formdata.php');
    $save_action = 'ci_save_form_custom_provider';
}

$cli        = array();
$cli_id     = isset($_POST['clinic_id']) ? $_POST['clinic_id'] : 0;
$sign_dir   = tr_get_signature_dir();
$where_cli  = ' and cli_status = 1 ';




if ($current_role == 'office_manager' || $current_role == 'clinic') {

    $cid = get_user_meta($user_id, '_clinic_id', true);
    $clinics = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
    $clinics = implode(',', $clinics);
    $where_cli .= " and cID in ({$clinics}) ";

    if (empty($act)) {
        $act = 'list';
    }
} else if (tr_current_role_in(array('multi_site_manager'))) {

    if (tr_get_option('ci_pdf_data', 'msm_can_show_all')) {
        //can see all clinics
    } else if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
        $cli_ids = get_user_meta($user_id, '_ms_clinic_ids', true);
        if (!is_array($cli_ids) || empty($cli_ids)) {
            $cli_ids = $wpdb->get_col("select cID from ci_clinic where cli_status = 1 ");
            update_user_meta($user_id, '_ms_clinic_ids', $cli_ids);
        }
        $cli_ids_str = implode(',', $cli_ids);
        $where_cli .= " and cID in ({$cli_ids_str}) ";
    }
} else if (tr_current_role_in(array('reflab_manager'))) {
    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where_cli .= " and cID = {$current_clinic} ";
    } else {
        $ref_lab_id = get_user_meta($user_id, '_ref_lab_id', true);
        $where_cli .= " and (`cli_ref_lab_ID` = {$ref_lab_id} or (cli_ref_lab_ID=0 and `cli_lab-ID` = {$ref_lab_id})) ";
    }
} else if ($current_role == 'lab_rep'  || $current_role == 'lab_rep_w2') {
    $rep_id = get_user_meta(get_current_user_id(), '_rep_id', true);
    $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);
    $where_cli .= " and `cli_rep-ID`={$rep_id} ";

    if ($rep['rep_inactive'] == 1) {
        $can_edit = false;
        $can_delete = false;
        $save_action = '';
    }
} else if ($current_role == 'distributor') {
    $dis_id = $wpdb->get_var("select dis_ID from ci_distributors where dis_user_ID = {$user_id}");

    $arr_marketing_groups = $wpdb->get_results("SELECT * FROM `ci_marketing_groups` where `gr_distributor_ID`= {$dis_id} and `gr_inactive`='0'");
    $g_ids = array(-1);
    foreach ($arr_marketing_groups as $g) {
        $g_ids[] = $g->gr_ID;
    }
    $g_ids = implode(',', $g_ids);

    $where_rep .= " and (`rep_marketing_group_ID` IN ({$g_ids}) or rep_distributor_ID={$dis_id}) ";

    $where_cli .= " and (cli_distributor_ID={$dis_id} or (`rep_marketing_group_ID` IN ({$g_ids}) or rep_distributor_ID={$dis_id})) ";
    $where_group .= " and gr_distributor_ID = {$dis_id} ";
}

if ($current_lab_id > 0 && !defined('TRPDD_SAME_LAB')) {
    $where_cli .= " and `cli_lab-ID`={$current_lab_id} ";
}

if ($cli_id > 0) {
    $cli = $wpdb->get_row("select * from ci_clinic where `cID`={$cli_id}", ARRAY_A);
} else {
    $clinics = $wpdb->get_results("select cli.cID,cli.cli_clinic,
                    trim(concat(lr.rep_first,' ',lr.rep_last)) as rep_name
                from ci_clinic cli
                Left JOIN ci_lab_reps lr ON lr.rep_ID = cli.`cli_rep-ID`
                where 1 {$where_cli} order by cli_clinic ", ARRAY_A);
}



if ($act == 'list') {
    $order = @$_REQUEST['order'] == 'desc' ? 'desc' : 'asc';
    if (isset($_GET['orderby'])) {

        $short = $_GET['orderby'];
    } else {
        $short = 'cus.cus_manager';
    }

    if (!empty($_REQUEST['s'])) {
        $search = stripslashes($_REQUEST['s']);
        $where_cli .= $wpdb->prepare(" and (cus.cus_manager like %s or cus.cus_first like %s)", '%' . $search . '%', '%' . $search . '%');
    }

    $orderby = "order by $short $order";

    $sql = "select  cus.*,cli.cli_clinic,cli.`cli_lab-ID` as lab_ID, lab.lab_dba_init as lab_name
                            from ci_custom_providers cus
                            JOIN ci_clinic cli ON cli.cID = cus.cli_ID
                            left join ci_lab_reps lr on cli.`cli_rep-ID` = lr.rep_ID
                            Left JOIN ci_labs lab ON lab.lab_ID = cli.`cli_lab-ID`

                            Where 1 {$where_cli} {$orderby}";

    $customs = $wpdb->get_results($sql);

    $show_lab = in_array($current_role, array('administrator', 'AdminCIDW', 'administrator_p', 'clms'));

?>

    <form method="get" style="float:right;margin-right: 10px;">
        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
        <input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
        <input type="hidden" name="act" value="<?php echo $_GET['act'] ?>" />
        <label>Name/Provider:</label>
        <input type="text" name="s" value="<?php echo stripslashes(@$_REQUEST['s']) ?>" />
        <input type="submit" class="button bsty6" value="Search" />
    </form>

    <div class="wrap">
        <h2>All <a class="add-new-h2" href="<?php echo Tr_Base_Class_V5::link(array('act' => '', 'id' => '')) ?>">Add New</a> </h2>
        <?php Tr_Session_Class::show() ?>

        <div class="wraptbl">
            <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
                <thead class="sticky">
                    <tr>
                        <th style="width:50px">#</th>
                        <?php echo tr_tbl_sort('cus.cus_manager', 'ACCOUNT MANAGER') ?>
                        <th>Office Manager</th>
                        <th>Email</th>
                        <?php echo tr_tbl_sort('cus.cus_first', 'Provider') ?>
                        <th>FACILITY/CLIENT NAME</th>
                        <?php if ($show_lab) : ?>
                            <th>Lab</th>
                        <?php endif; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customs as $row) : ?>
                        <tr>
                            <td><?php echo $row->cus_ID ?></td>
                            <td><?php echo $row->cus_manager ?></td>
                            <td><?php echo $row->cus_office_man ?></td>
                            <td><?php echo $row->cus_email ?></td>
                            <td><?php echo $row->cus_first . ' ' . $row->cus_last; ?></td>
                            <td><?php echo $row->cli_clinic ?></td>
                            <?php if ($show_lab) : ?>
                                <td><?php echo $row->lab_name ?></td>
                            <?php endif; ?>
                            <td>
                                <a href="<?php echo Tr_Base_Class_V5::link(array('act' => 'edit', 'id' => $row->cus_ID)) ?>">Edit</a>
                                - <a class="thickbox" href="<?php echo Tr_Base_Class_V5::link(array('tr_action' => 'ci_print_custom_provider', 'id' => $row->cus_ID)) ?>&TB_iframe=true&width=800&height=550">Print</a>
                                <?php if ($can_delete) { ?>
                                    - <a class="delete" href="<?php echo Tr_Base_Class_V5::link(array('tr_action' => 'ci_delete_custom_provider', 'id' => $row->cus_ID)) ?>">Delete</a>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div>Total: <?php echo count($customs) ?></div>

    </div>
    <script>
        jQuery(function($) {
            $('a.delete').click(function() {
                if (confirm('Do you want delete?') == false) return false;
            })
        })
    </script>
<?php
    return;
} else if (($act == 'edit' || $act == 'print') && $id > 0) {
    $where = "cus.cus_ID = {$id}";
    if (isset($_REQUEST['doc_id'])) {
        $where = "cus.doc_ID = {$_REQUEST['doc_id']}";
    }
    //var_dump($where);
    $cus = $wpdb->get_row("select cus.*,
                            doc.doc_first,
                            doc.doc_last,
                            doc.doc_specialty,
                            cli.`cli_lab-ID` as lab_ID
                            from ci_custom_providers cus
                            JOIN ci_doc doc ON cus.doc_ID = doc.doc_ID
                            LEFT JOIN ci_clinic cli ON cli.cID = cus.cli_ID
                            where {$where} group by cus.cus_ID 
                            order by cus.cus_ID desc", ARRAY_A);
    $cli_id = $cus['cli_ID'];
    if ($cli_id == 0) {
        $cli_id = $wpdb->get_var("select cli_ID from ci_clinic_doc where doc_ID = '{$cus['doc_ID']}' ");
    }
    $cus['cus_individuals'] = strtoupper($cus['cus_individuals']);
    $cus['cus_med_risk'] = strtoupper($cus['cus_med_risk']);
    $cus['cus_high_risk'] = strtoupper($cus['cus_high_risk']);
    $cus['cus_comprehensive'] = ''; //strtoupper($cus['cus_comprehensive']);


    if (empty($cus['cus_last'])) {
        $cus['cus_last'] = $cus['doc_last'];
    }
    if (empty($cus['cus_first'])) {
        $cus['cus_first'] = $cus['doc_first'];
    }
    $cus['cus_testing_profile'] = strtolower($cus['cus_testing_profile']);
}

?>
<div class="wrap">
    <?php if ($act != 'print') : ?>
        <h2>CUSTOM PROVIDER ACKNOWLEDGEMENT FORM <a class="add-new-h2" href="<?php echo Tr_Base_Class_V5::link(array('act' => 'list', 'id' => '')) ?>">Saved form</a></h2>
        <?php Tr_Session_Class::show() ?>
    <?php endif; ?>
    <form method="post" class="need_validate" enctype="multipart/form-data">
        <input type="hidden" name="cus[cus_ID]" value="<?php echo @$cus['cus_ID'] ?>">

        <div class="frow blabel">
            <div class="col3">
                <label>FACILITY/CLIENT NAME</label>
                <select name="cus[cli_ID]" required="required">
                    <option value="">Please select</option>
                    <?php
                    foreach ($clinics as $cli) {
                        $cli['cli_clinic'] = trim($cli['cli_clinic']);
                        if (empty($cli['cli_clinic'])) continue;
                        $selected = $cli['cID'] == $cli_id ? 'selected' : '';
                        echo '<option value="' . $cli['cID'] . '" ' . $selected . ' data-rep="' . $cli['rep_name'] . '">' . stripslashes($cli['cli_clinic']) . '</option>';
                    }
                    ?>
                </select>
            </div>

        </div>
        <div class="frow">
            <div class="col3">
                <label>Provider Last Name:</label>
                <input type="text" class="doc_last requesting_physician_last_auto" name="cus[cus_last]" value="<?php echo @$cus['cus_last'] ?>" required />
                <input type="hidden" name="cus[doc_ID]" value="<?php echo @$cus['doc_ID'] ?>">
            </div>
            <div class="col3">
                <label>Provider First Name:</label>
                <input type="text" class="doc_first requesting_physician_first_auto" name="cus[cus_first]" value="<?php echo @$cus['cus_first'] ?>" required />

            </div>
            <div class="col3">
                <label>Specialty:</label>
                <input type="text" name="cus[cus_specialty]" value="<?php echo $cus['cus_specialty'] ?>" />
            </div>
        </div>

        <div class="frow blabel">

            <div class="col3">
                <label>ACCOUNT MANAGER</label>
                <input type="text" name="cus[cus_manager]" value="<?php echo $cus['cus_manager'] ?>" required="" />
            </div>
            <div class="col3">
                <label>PRIMARY CONTACT</label>
                <input type="text" name="cus[cus_office_man]" value="<?php echo $cus['cus_office_man'] ?>" required="" />
            </div>
            <div class="col3">
                <label>EMAIL</label>
                <input type="email" name="cus[cus_email]" value="<?php echo $cus['cus_email'] ?>" required="" />
            </div>
        </div>
        <div class="frow">
            <h4>IS TESTING CURRENTLY BEING PERFORMED AT YOUR FACILITY?</h4>
        </div>

        <div class="frow iblabel">
            <div class="col2">
                <label>Enzyme Immunoassay (EMIT, ELISA)</label>
                <?php tr_yes_no_radio('cus[cus_enzyme]', $cus['cus_enzyme']) ?>
            </div>
            <div class="col2">
                <label>Point of Care Testing (dipstick, drug screening cups)</label>
                <?php tr_yes_no_radio('cus[cus_point_of_care]', $cus['cus_point_of_care']) ?>
            </div>
        </div>

        <div class="frow">
            <h4>SELECT A TESTING PROFILE</h4>
        </div>
        <div class="frow">
            Based on risk stratification (e.g. low, moderate, or high risk patients, new patients, red flags), the practitioner may customize their testing requirements. The
            laboratory offers the client the opportunity to select or create their own custom profile, along with the ability of creating the name of the panel that best
            suits their practice.
            <br>
        </div>


        <div class="frow i_blabel">
            <div class="col5">
                <label><input type="radio" id="profile_basic" name="cus[cus_testing_profile]" value="basic" <?php checked($cus['cus_testing_profile'], 'basic');
                                                                                                            if ($act == 'print') echo 'disabled'; ?> />
                    Low Risk</label> <i class="itip tooltip testing_profile" title="" data-id="profile_basic"></i>
            </div>
            <div class="col5">
                <label><input type="radio" id="profile_standard" name="cus[cus_testing_profile]" value="standard" <?php checked($cus['cus_testing_profile'], 'standard');
                                                                                                                    if ($act == 'print') echo 'disabled'; ?> />
                    Moderate Risk</label><i class="itip tooltip testing_profile" title="" data-id="profile_standard"></i>
            </div>
            <div class="col5">
                <label><input type="radio" id="profile_highrisk" name="cus[cus_testing_profile]" value="highrisk" <?php checked($cus['cus_testing_profile'], 'highrisk');
                                                                                                                    if ($act == 'print') echo 'disabled'; ?> />
                    High Risk</label><i class="itip tooltip testing_profile" title="" data-id="profile_highrisk"></i>
            </div>
            <div class="col5">
                <label><input type="radio" id="profile_comprehensive" name="cus[cus_testing_profile]" value="Comprehensive" <?php checked($cus['cus_testing_profile'], 'comprehensive');
                                                                                                                            if ($act == 'print') echo 'disabled'; ?> />
                    COMPREHENSIVE</label><i class="itip tooltip testing_profile" title="" data-id="profile_comprehensive"></i>
            </div>

            <div class="col5">
                <label>Default Profile</label>
                <?php
                tr_select_option('cus[cus_df_profile]', tr_pdd_get_custom_profiles(), @$cus['cus_df_profile']);
                ?>
            </div>
        </div>

        <div class="frow">
            <h4>CONFIRM POSITIVE DRUG SCREEN RESULTS (applies ONLY if performing EIA or POC testing in office)</h4>
        </div>

        <div class="frow iblabel">
            <input type="hidden" name="cus[cus_confirm_positive]" value="0" />
            <label><input type="checkbox" name="cus[cus_confirm_positive]" value="1" <?php checked($cus['cus_confirm_positive'], '1') ?> />
                Please confirm all <b>POSITIVE</b> point -of-care testing or EIA screening results.</label>

        </div>

        <div class="frow">
            <h4>Please Select Individual Therapeutic Drugs or Therapeutic Drug Class Section.</h4>
        </div>
        <div class="frow">
            Based on risk stratification (e.g. low, moderate, or high risk patients, new patients, red flags), the practitioner may customize their testing requirements.
            <br>
            In the laboratory's commitment to medical necessity, the provider may customize the testing choices based on specific clinical need. Listed
            below are all the therapetutic drug / drug classes available. Please review each therapeutic drug/ drug class and select the individual or therapeutic drug
            class that suits your testing requirements. By checking the class you agree to test for all the therapeutic drugs available at the laboratory in that class.
            <br>
        </div>

        <div class="frow blabel default_requests panel_requests masonry" id="profile_custom_div" style="font-size:12px;">
            <?php

            $default_tests = array();
            if ($cus['cus_testing_profile'] == 'basic') {
                $default_tests = explode(",", $cus['cus_individuals']);
            } else if ($cus['cus_testing_profile'] == 'standard') {
                $default_tests = explode(",", $cus['cus_med_risk']);
            } else if ($cus['cus_testing_profile'] == 'highrisk') {
                $default_tests = explode(",", $cus['cus_high_risk']);
            } else if ($cus['cus_testing_profile'] == 'comprehensive') {
                $default_tests = explode(",", $cus['cus_comprehensive']);
            }
            $default_tests = array_map('strtoupper', $default_tests);

            foreach (tr_pdd_get_profiles_tests_requesteds('tox', true) as $group_id => $vl) {
                $group = tr_pdd_get_panel_groups($group_id);
                echo '<div class="groupparent item">';
                echo '<label class="block"><b>' . $group['label'] . '</b></label>';
                echo '<div class="frow">';
                foreach ($vl as $k => $r) {
                    $key = strtoupper($r->key);
                    $txt = $r->te_name;
                    $checked = @in_array($key, $default_tests);
            ?>
                    <label>
                        <input type="checkbox" class="<?php echo $r->class ?>" value="<?php echo $key ?>" <?php checked($checked) ?> />
                        <?php echo $txt ?></label>
            <?php

                }

                echo '</div>';
                echo '</div>';
            }
            ?>
        </div>

        <input type="hidden" id="all_basic" name="cus[cus_individuals]" value="<?php echo esc_attr($cus['cus_individuals']) ?>" />
        <input type="hidden" id="all_standard" name="cus[cus_med_risk]" value="<?php echo esc_attr($cus['cus_med_risk']) ?>" />
        <input type="hidden" id="all_highrisk" name="cus[cus_high_risk]" value="<?php echo esc_attr($cus['cus_high_risk']) ?>" />

        <input type="hidden" id="all_comprehensive" name="cus[cus_comprehensive]" value="<?php echo esc_attr($cus['cus_comprehensive']) ?>" />

        <div class="frow">
            <h4>Please check all that apply</h4>
        </div>
        <div class="frow iblabel">
            <?php
            foreach (tr_pdd_get_batch_types() as $btype) {
                $fk = 'cus_' . $btype;
                if (!in_array($btype, array('tox'))) continue;
            ?>
                <label>
                    <input type="hidden" name="cus[<?php echo $fk ?>]" value="0" />
                    <input type="checkbox" name="cus[<?php echo $fk ?>]" value="1" <?php checked($cus[$fk]) ?> />
                    <?php echo tr_pdd_get_typename($btype) ?>
                </label>
            <?php
            }
            ?>


            <label>
                <input type="hidden" name="cus[cus_ref_tox]" value="0" />
                <input type="checkbox" name="cus[cus_ref_tox]" value="1" <?php checked($cus['cus_ref_tox']) ?> />
                Ref Tox
            </label>
        </div>

        <div class="frow">
            <h4>Result Style</h4>
        </div>
        <div class="frow iblabel">
            <div class="col5">
                <select name="cus[cus_result_style]">
                    <?php
                    for ($i = 1; $i <= 7; $i++) {
                        $selected = $cus['cus_result_style'] == $i ? 'selected' : '';
                        echo '<option value="' . $i . '" ' . $selected . '>Style #' . $i . '</option>';
                    }
                    ?>
                </select>
            </div>

        </div>

        <div class="frow">
            <h4>PROVIDER AUTHRORIZATION</h4>
        </div>

        <div class="frow">
            <b>I understand and hereby acknowledge:</b><br>
            1. I have requested the creation of this custom profile of tests listed on this form for the use in our clinic when merited.<br>
            2. I understand that when ordering tests for my patients, reimbursement from the insurance carrier will be sought. I should only order
            those tests which I believe are medically necessary.<br>
            3. I understand that by using a customized testing profile, Medicare and other federally-funded health care plans may deny payment.<br>
            4. I will order individual test or a less inclusive profile when not all the tests in the cusomized profile are medically necessary.<br>
            5. I understand that this preferred order will remain in effect until an updated form is completed.<br>
        </div>






        <div class="frow iblabel">
            <div class="col2">
                <label>Provider Signature:</label>
                <?php
                if (@$cus['cus_ID'] > 0 || $act == 'print') {
                    $fname =  'provider_' . $cus['cus_ID'] . '.png';
                    $file_exists = file_exists($sign_dir['path'] . '/' . $fname) && (filesize($sign_dir['path'] . '/' . $fname) > 0);
                    if ($file_exists) {
                        list($width, $height, $type, $attr) = getimagesize($sign_dir['path'] . '/' . $fname);
                        if ($height > 500) {
                            $cus['cus_signature_upload'] = 1;
                        }
                    }
                    if ($file_exists && $cus['cus_signature_upload']) {
                ?>
                        <a class="thickbox" href="<?php echo $sign_dir['url'] . '/' . $fname . '?' . time() ?>">
                            <img src="<?php echo $sign_dir['url'] . '/' . $fname . '?' . time() ?>" style="max-width:300px" /></a>
                        <input type="hidden" name="cus[cus_signature_upload]" value="1" />
                    <?php
                    } else if ($file_exists) {
                        echo tr_signature(array('name' => 'signature', 'url' => $sign_dir['url'] . '/' . $fname . '?' . time()));
                    } else {
                        echo tr_signature(array('name' => 'signature'));
                    }
                    ?>
                <?php
                } else {
                    echo tr_signature(array('name' => 'signature'));
                } ?>
                <?php if ($act != 'print') : ?>
                    <input type="file" name="image_signature" value="Upload file Signature" />
                <?php endif; ?>
            </div>
            <div class="col2">
                <div class="frow">
                    <label>Date:</label>
                    <input type="text" class="hasdate today" name="cus[cus_provider_date]" value="<?php echo $cus['cus_provider_date'] ?>" />
                </div>
                <div class="frow">
                    <label><input type="checkbox" name="cus[cus_include_signature]" value="1" <?php checked($cus['cus_include_signature']) ?> /> I will not choose a custom profile and will select tests as medically necessary each time and include my signature on the Testing Request Form. </label>
                </div>
            </div>
        </div>

        <?php if (!empty($save_action) && $can_edit) : ?>
            <div>
                <input type="submit" class="button-primary" value="Submit">
                <input type="hidden" name="tr_action" value="<?php echo $save_action ?>" />
            </div>
        <?php endif; ?>

    </form>
</div>
<?php if ($act == 'print') { ?>
    <script>
        window.print();
    </script>
<?php } else { ?>
    <script>
        jQuery(function($) {
            var $bychangeall = false;
            $('[name="cus[cus_testing_profile]"]').change(function() {
                if ($(this).is(':checked')) {
                    var $tmp = $(this).val().toLowerCase();

                    $('#profile_custom_div input').removeAttr('checked');
                    var $test_str = $('#all_' + $tmp).val();
                    if ($test_str) {

                        var $tests = $test_str.split(',');
                        for (i in $tests) {

                            $('#profile_custom_div input[value="' + $tests[i] + '"]').prop('checked', true);
                        }
                        $('#profile_custom_div input').each(function() {
                            var $vl = $(this).val();
                            if ($vl.indexOf(',') > 0 && $test_str.indexOf($vl) > -1) {
                                $(this).prop('checked', true);
                            }
                        })
                    } else {
                        $('#profile_custom_div input.te_' + $tmp).prop('checked', true);
                        $bychangeall = true;
                        $('#profile_custom_div input.te_' + $tmp + ':first-child').change();
                        $bychangeall = false;

                    }
                }
            })


            $('#profile_custom_div input').change(function(e) {
                var $tmp = $('[name="cus[cus_testing_profile]"]:checked').val().toLowerCase();
                var $tests = [];

                if ($tmp == 'comprehensive' && !$bychangeall) {
                    console.log(e);
                    alert('You cant change Comprehensive');
                    return false;
                }

                $('#profile_custom_div input:checked').each(function() {
                    $tests.push($(this).val());
                })
                $('#all_' + $tmp).val($tests.toString());
            })

            $('[name="cus[cus_testing_profile]"]:checked').change();

            $('.testing_profile').click(function() {
                tb_show('', '?tr_action=show_basic_info&id=' + $(this).data('id') + '&width=1000&height=600');
            });
            $('.psych_panel').click(function() {
                tb_show('', '?tr_action=show_basic_info&file=psych_1.jpg&id=' + $(this).data('id') + '&width=800&height=600');
            });

            function tr_setup_physician_auto(cli_id) {
                $('.requesting_physician_last_auto').autocomplete({
                    source: ajaxurl + '?action=ajax&autocomplete=requesting_physician_last_auto&provider=1&cli_id=' + cli_id,
                    minLength: 1,
                    select: function(event, ui) {
                        if (ui.item) {
                            $('[name="cus[doc_ID]"]').val(ui.item.id);
                            $('[name="cus[doc_ID]"]').data('name', ui.item.value + ui.item.doc_first);
                            $('[name="cus[cus_first]"]').val(ui.item.doc_first);
                            $('[name="cus[cus_specialty]"]').val(ui.item.doc_specialty);
                            if (ui.item.cus_ID > 0) {
                                location.href = "<?php echo Tr_Base_Class_V5::link(array('act' => 'edit', 'id' => '')) ?>&id=" + ui.item.cus_ID;
                            }
                        } else {
                            $('[name="cus[doc_ID]"]').val('');
                        }
                    }
                });
                $('.requesting_physician_first_auto').autocomplete({
                    source: ajaxurl + '?action=ajax&autocomplete=requesting_physician_first_auto&provider=1&cli_id=' + cli_id,
                    minLength: 1,
                    select: function(event, ui) {
                        if (ui.item) {
                            $('[name="cus[doc_ID]"]').val(ui.item.id);
                            $('[name="cus[doc_ID]"]').data('name', ui.item.doc_last + ui.item.value);
                            $('[name="cus[cus_last]"]').val(ui.item.doc_last);
                            $('[name="cus[cus_specialty]"]').val(ui.item.doc_specialty);
                            if (ui.item.cus_ID > 0) {
                                location.href = "<?php echo Tr_Base_Class_V5::link(array('act' => 'edit', 'id' => '')) ?>&id=" + ui.item.cus_ID;
                            }
                        } else {
                            $('[name="cus[doc_ID]"]').val('');
                        }
                    }
                });
            };
            tr_setup_physician_auto($('[name="cus[cli_ID]"]').val());
            $('[name="cus[cli_ID]"]').change(function() {
                tr_setup_physician_auto($('[name="cus[cli_ID]"]').val());
                $('[name="cus[cus_manager]"]').val($(this).find(':selected').data('rep'));
            });
            $('.doc_first,.doc_last').change(function() {
                $vl = $('[name="cus[doc_ID]"]').data('name');
                if ($vl != $('.doc_last').val() + $('.doc_first').val()) {
                    $('.doc_last').val('')
                    $('.doc_first').val('');
                    $('[name="cus[doc_ID]"]').val('');
                }
            });
        })

        function cancel_info(id) {
            jQuery('#' + id).removeAttr('checked');
            tb_remove();
        }
    </script>
    <style>
        div.ui-tooltip {
            max-width: 800px !important;
        }
    </style>
<?php } ?>