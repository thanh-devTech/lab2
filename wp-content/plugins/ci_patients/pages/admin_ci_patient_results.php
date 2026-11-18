<?php
global $wpdb;
global $is_ci_admin;

$btypes_str = '';
$user_id = get_current_user_id();
$user = wp_get_current_user();
$today_time = strtotime(date_i18n('Y-m-d H:i:s'));
$check_duplicatep = true;
$byassen = isset($_GET['byassen']) ? sanitize_text_field(wp_unslash($_GET['byassen'])) : '';
$byphone = isset($_GET['byphone']) ? sanitize_text_field(wp_unslash($_GET['byphone'])) : '';

if (isset($_REQUEST['reset_patient_id'])) {
    update_user_meta($user_id, '_patient_code', '');
    $wpdb->update($wpdb->users, array('patient_id' => ''), array('ID' => $user_id));
    $wpdb->update('ci_patients', array('pa_email' => ''), array('pa_email' => $user->user_email));
}
wp_dequeue_style('Admin_Page_Class');


include_once(TRCPDD_PATH . 'pages/formdata.php');
wp_enqueue_script('patients', TRCPPR_URL . 'js/admin.js', array(), TRCPPR_V);
wp_enqueue_script('datedropdown', TRCPPR_URL . 'js/jquery.date-dropdowns.min.js', array(), TRCPPR_V);



$options = get_option('trpatient_settings', array());
$patient_id = tr_patient_get_id_patient($user_id);


if ($is_ci_admin) {
    if (!empty($byassen)) {
        foreach (tr_pdd_get_batch_types() as $btt) {
            $btable = tr_pdd_get_table_by_btype($btt);
            $pid = $wpdb->get_var($wpdb->prepare("select patient_id from {$btable} where assen = %s ", $byassen));
            if ($pid > 0) {
                $patient_id = (int) $pid;
                break;
            }
        }
    } else if (isset($_GET['patient_id']) && $_GET['patient_id'] > 0) {
        $patient_id = (int) $_GET['patient_id'];
    }

    if (!empty($byphone)) {
        $patient_id = (int) $wpdb->get_var($wpdb->prepare("select pa_ID from ci_patients where pa_phone like %s", '%' . $wpdb->esc_like($byphone) . '%'));
    }
}

if (isset($_GET['debugphone'])) {
    $phone = sanitize_text_field(wp_unslash($_GET['debugphone']));
    $patient_id = (int) $wpdb->get_var($wpdb->prepare("select pa_ID from ci_patients where pa_phone = %s", $phone));
}

if (isset($_GET['dd2'])) {
    var_dump($user_id);
    var_dump($patient_id);
    exit;
}

$dob = get_user_meta($user_id, '_dob', true);

//fix old error
if (is_object($patient_id)) {
    $patient_id = $patient_id->pa_ID;
    $wpdb->update($wpdb->users, array('patient_id' => $patient_id), array('ID' => $user_id));
}

$patient_id = (int) $patient_id;

if ($patient_id <= 0 && !$is_ci_admin) {
    include('admin_ci_setup_patient.php');
    return;
}


if ($options['enable_check_dob'] && empty($dob)) {

    //include('admin_ci_update_dob.php');
    //return;
}

$kids = $patient_id > 0 ? tr_patient_get_kids($patient_id, $user_id) : array();

if (isset($_GET['update_patient'])) {
    trcppr_cron_sync_patient();
}
$lab_url = '';

if (defined('TRPDD_ALLOW_FRONT_PDF') && TRPDD_ALLOW_FRONT_PDF) {
    $lab_url = get_site_url(1);
    if (empty($lab_url) && defined('DOMAIN_CURRENT_SITE')) {
        $lab_url = 'https://' . DOMAIN_CURRENT_SITE;
    }
}


if ($act == 'reset') {
    delete_user_meta($user_id, '_patient_id');
    $wpdb->update($wpdb->users, array('patient_id' => 0), array('ID' => $user_id));
    update_user_meta($user_id, '_patient_kids', array());
?>
    <h4>Reloading....</h4>
    <script>
        location.reload();
    </script>
<?php
    exit;
} else if ($act == 'addkid') {
    include('admin_ci_add_kid.php');
} else if ($act == 'removekid') {
    $kidid = intval($_REQUEST['id']);
    unset($kids[$kidid]);
    update_user_meta($user_id, '_patient_kids', $kids);

    wp_redirect(Tr_Base_Class_V6::link(array('act' => '', 'trajax' => '', 'id' => '', 'tab' => ''), false));
}
$tabs = array(
    'main' => 'Results',
);


if (is_array($kids)) {
    foreach ($kids as $pid => $kid) {
        $tabkey = trpdd_patient_encode($pid);
        $tabs['patient_' . $tabkey] = $kid['last_name'] . ', ' . $kid['first_name'];
    }
}

$is_kid = false;
$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);
if (stripos($tab, 'patient_') !== false) {
    $main_patient_id = $patient_id;
    list($temp, $patient_id) = explode('_', $tab);
    $patient_id = trpdd_patient_decode($patient_id);
    if (isset($kids[$patient_id])) {
        $is_kid = true;
    } else {
        $patient_id = $main_patient_id;
        $tab = key($tabs);
    }
}


$all_groups = array();
$ts = $wpdb->get_results("select tg_ID, 
                                tg_name, 
                                tg_label_name,
                                tg_notestcheckbox 
                                from ci_test_groups");
foreach ($ts as $t) {
    $all_groups[$t->tg_ID] = $t;
}
?>
<div class="wrap">
    <h2 class="nav-tab-wrapper">
        <?php

        foreach ($tabs as $k => $txt) {
            $class = $tab == $k ? 'nav-tab-active' : '';
            $link = Tr_Base_Class_V4::link(array('tab' => $k, 'act' => '', 'id' => '', 'mid' => '', 'paged' => '', 'archive' => ''), false);
            echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
        }
        ?>
        <a title="Add Family Member" class="button add-new-h2 addnewkid thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => 1, 'act' => 'addkid')) ?>">Add Family Member</a>
        <?php if ($user->roles[0] == 'administrator') { ?>
            <a title="Reset" class="button add-new-h2 right resetbtn thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => 1, 'act' => 'reset')) ?>">Reset</a>
        <?php } ?>
    </h2>
    <br>
</div>
<?php
if ($is_ci_admin) {

    if ($patient_id > 0) {
        $patientname = $wpdb->get_var($wpdb->prepare("select concat(pa_first, ' ', pa_last, ' (', pa_dob,')') from ci_patients where pa_ID = %d ", $patient_id));
        echo 'You are viewing patient: ' . esc_html($patientname);
    }
?>
    <form method="get" id="searchform">
        <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']) ?>" />
        <label>Search Patient by Name:</label>
        <input type="text" class="patient_auto" value="" />
        <label>By Accession:</label>
        <input type="text" name="byassen" id="byassen" value="<?php echo esc_attr($byassen) ?>" />
        <label>By Phone:</label>
        <input type="text" name="byphone" id="byphone" value="<?php echo esc_attr($byphone) ?>" />
        <input type="hidden" name="patient_id" id="patient_id" />
        <input type="submit" class="button" value="Submit" style="display:none" />
    </form>
    <script>
        var $cur_year = '<?php echo date_i18n('Y') ?>';
        jQuery(function($) {
            $('.patient_auto').autocomplete({
                source: ajaxurl + '?action=ajax&autocomplete=patient_auto&btype=all',
                minLength: 1,
                select: function(event, ui) {
                    if (ui.item) {
                        $('input#patient_id').val(ui.item.id).data('name', ui.item.value);
                        $('#searchform').submit();
                    }
                }
            })
            $('body').on('change', '#byassen', function() {
                if ($(this).val() != '') {
                    $('#searchform').submit();
                }
            })
        });
    </script>
<?php
}
?>


<div class="wrap">

    <?php Tr_Session_Class::show() ?>
    <?php
    $options['below_tabs'] = isset($options['below_tabs']) ? $options['below_tabs'] : '';
    echo '<div class="text_below_tabs">';
    echo apply_filters('the_content', stripslashes($options['below_tabs']));
    echo '</div>';

    $patient_ids = array($patient_id);
    //find duplicate by phone
    $patient = $patient_id > 0 ? $wpdb->get_row($wpdb->prepare("select * from ci_patients where pa_ID = %d", $patient_id)) : false;

    if ($patient) {
        $phones = tr_patient_get_phone_arr($patient->pa_phone);
        $phones = implode("','", $phones);
    } else {
        $phones = '';
    }


    if ($check_duplicatep && $patient && !empty($patient->pa_phone) && strlen($patient->pa_phone) > 9 && !empty($phones)) {
        $dubpa = $wpdb->get_col($wpdb->prepare(
            "select pa_ID from ci_patients
                    where 
                    (
                        (pa_first= %s and pa_last = %s)
                        OR (pa_first= %s and pa_last = %s)
                    )
                     and pa_dob = %s
                     and pa_phone in ('{$phones}') 
                     and pa_ID != %d ",
            $patient->pa_first,
            $patient->pa_last,
            $patient->pa_last,
            $patient->pa_first,
            $patient->pa_dob,
            $patient_id
        ));


        if (count($dubpa) > 0) {
            $patient_ids = array_merge($patient_ids, $dubpa);
        }
    }
    $patient_ids = array_filter(array_map('intval', $patient_ids));
    if (empty($patient_ids)) {
        $patient_ids = array(0);
    }
    $patient_ids = implode(',', $patient_ids);

    $where_patient = " ba.patient_id in ({$patient_ids}) ";

    if (isset($options['patient_m5china']) && $options['patient_m5china'] && !empty($phones)) {
        $pachinas = $wpdb->get_col("select pa_ID from ci_patients
                    where pa_phone in ('{$phones}') 
                     and pa_ID not in ( {$patient_ids} ) ");

        if (count($pachinas) >= 4) {
            $pachinas = implode(',', array_map('intval', $pachinas));
            $where_patient .= " or (ba.patient_id in ({$pachinas}) and ba.patient_portal_type = 'China') ";
        }
    }

    $where = " ( {$where_patient} ) ";

    $where .= " and ba.batch_status !='archived' ";

    $sql_total = array();
    foreach (tr_pdd_get_batch_types() as $btype) {
        $table_batch = tr_pdd_get_table_by_btype($btype);
        $table_report = tr_pdd_get_table_report_by_btype($btype);

        $sql_total[] = "(select count(*) as count from {$table_batch} ba 
	                left join {$table_report} re ON re.patient_id = ba.assen
	                where {$where})";
    }
    $sql_total = implode(' UNION ALL ', $sql_total);
    $sql_total = "select sum(tt.count) from ({$sql_total}) tt";


    $row_per_page = 20;
    $pagination = new Tr_Pagination_class2($row_per_page);
    $offset = $pagination->offset();
    $total = $wpdb->get_var($sql_total);
    //var_dump($wpdb->last_error);
    $pagination->setTotal($total);


    $sql = array();
    $sql_no_result = array();
    $btypes = array();
    foreach (tr_pdd_get_batch_types() as $btype) {
        $where_covid = '';
        if ($options['only_covid']) {
            if ($btype == 'pcr') {
                $group_id = $wpdb->get_var("select tg_ID from ci_test_groups where tg_type='pcr' 
                                and tg_is_covid = 1");
                $where_covid .= " and FIND_IN_SET('GROUP_{$group_id}',ba.panel_tests_requested) ";
            } else if ($btype == 'blood') {
                $group_id = $wpdb->get_var("select tg_ID from ci_test_groups where tg_type='blood' 
                                and tg_is_covid = 1");
                $where_covid .= " and FIND_IN_SET('GROUP_{$group_id}',ba.panel_tests_requested) ";
            } else {
                continue;
            }
        }
        $table_batch = tr_pdd_get_table_by_btype($btype);
        $table_report = tr_pdd_get_table_report_by_btype($btype);

        $sql[] = "(select 
                    ba.mid, 
                    ba.patient_name, 
                    ba.batch_id,
                    ba.assen,
                    ba.doc,
                    ba.spec_received,
                    ba.requesting_practice as clinic,
                    ba.requesting_physician as doctor,
                    ba.panel_tests_requested,
                    
                    pa.pa_last,
                    pa.pa_first,
                    pa.pa_dob,
                    pa.pa_passport,
                    
                    re.id,
                    re.estatus,
                    re.approve_date,
                    IF(re.estatus='C',1,0) as has_result,
                    '{$btype}' as btype
                    
                    from {$table_batch} ba
                    left join {$table_report} re On re.patient_id = ba.assen
                    join ci_patients pa ON pa.pa_ID = ba.patient_id
                    where {$where} {$where_covid}
                 )";
    }
    $sql = implode(' UNION ALL ', $sql);


    $sql = "select * from ({$sql}) tt 
	    order by tt.doc desc
        limit {$offset},{$row_per_page}
        ";

    $rows = $wpdb->get_results($sql);

    //get last result date
    $last_result_date = '';
    $no_results = array();
    $has_result = false;
    foreach ($rows as $row) {
        if ($row->has_result) {
            if ($row->doc > $last_result_date) {
                $last_result_date = $row->doc;
            }
            $has_result = true;
        } else {
            $no_results[$row->assen] = $row;
        }
    }

    if ($has_result) {
        $no_results = false;
    }


    if (isset($_GET['debug'])) {
        var_dump($patient_ids);
        echo '<br><br>';

        var_dump($wpdb->last_error);
        echo '<br>';
        var_dump($wpdb->last_query);
    }

    $steps = array();
    $steps[1] = array('title' => 'Testing Order Received', 'desc' => 'Test Requisition has been received!');
    $steps[2] = array('title' => 'Sample Collected', 'desc' => 'Your sample it\'s on its way to the lab for processing...');
    $steps[3] = array('title' => 'Sample Received', 'desc' => 'Your sample has been received by the lab...');
    $steps[4] = array('title' => $btypes_str . ' Testing', 'desc' => 'Your sample is now being tested...');
    $steps[5] = array('title' => 'Testing Complete', 'desc' => 'Your Results are now available!');

    foreach ($steps as $i => $arr) {
        if (isset($options['bubble_t_' . $i]) && !empty($options['bubble_t_' . $i])) {
            $steps[$i]['title'] = $options['bubble_t_' . $i];
        }
        if (isset($options['bubble_sub_' . $i]) && !empty($options['bubble_sub_' . $i])) {
            $steps[$i]['desc'] = $options['bubble_sub_' . $i];
        }

        $steps[$i]['title'] = str_replace('#types#', $btypes_str, $steps[$i]['title']);
    }


    if ($no_results) {
        $btype_names = array();
        $received = false;
        $assens_no_results = array();
        foreach ($no_results as $r) {
            $btype_names[$r->btype] = tr_pdd_get_typename($r->btype);
            if (!$received) {
                $received = $wpdb->get_var("select ID from ci_receiving_batches 
	                    where mid = {$r->mid} and btype='{$r->btype}'");
            }
            $assens_no_results[] = $r->assen;
        }

        $step = 2;

        if ($received) {
            $step = 4;
        }
        $btypes_str = implode(', ', $btype_names);


        echo '<div class="text_above_bubbles">';
        echo apply_filters('the_content', stripslashes($options['above_bubbles']));
        echo '</div>';
    ?>
        <div class="bubbles">
            <?php
            for ($i = 1; $i <= 5; $i++) {
                $class = '';
                if ($step >= $i) {
                    $class .= 'active';
                }
                $title_one = '';
                if ($i == 1) {
                    $title_one = implode(', ', $assens_no_results);
                }
            ?>
                <div class="bubble <?php echo $class ?>">
                    <div class="bwrap">
                        <div class="circle" title="<?php echo $title_one ?>"><span class="step"><?php echo $i ?></span></div>
                        <div class="tt"><?php echo $steps[$i]['title'] ?></div>
                        <?php if ($options['hide_desc'] != '1'): ?>
                            <div class="desc"><?php echo $steps[$i]['desc'] ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php
            }
            ?>
        </div>
    <?php
        echo '<div class="text_below_bubbles">';
        echo apply_filters('the_content', stripslashes($options['below_bubbles']));
        echo '</div>';
    }


    if (isset($_GET['archive']) && $_GET['archive']) {
    ?>
        <div style="text-align: right;"><a class="button" href="<?php Tr_Base_Class_V6::link(array('archive' => '')) ?>">New Results</a></div>
    <?php
    } else if ($no_results && $options['archive_result'] && count($rows) > 0) {
        $rows = array();
    ?>
        <div style="text-align: right;margin:5px 0"><a class="button" href="<?php Tr_Base_Class_V6::link(array('archive' => 1)) ?>">Archive Results</a></div>
    <?php
    }

    ?>
    <div id="main_results" class="outtbl <?php if (count($rows) == 0) echo 'nodatars' ?>">
        <div class="tablenav topnav"><?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?></div>
        <div class="wraptbl mobileview">
            <table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl" data-count="<?php echo @$count_rows ?>">
                <thead>
                    <tr>
                        <th style="width:3%">#</th>
                        <th style="width: 10%;">Accession</th>
                        <th style="width: 12%;">Patient Name</th>
                        <th style="width: 80px;">DOB</th>
                        <th style="width: 14%;">Clinic</th>
                        <th style="width: 11%;">Doctor</th>
                        <th style="width: 15%;">Type</th>
                        <th style="width: 80px;font-size:13px">Collect Date</th>
                        <th style="width: 80px;">Result Date</th>
                        <th>Status/View</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (count($rows) > 0) {

                        foreach ($rows as $i => $row) {
                            $row->pa_passport = str_replace(array('-', '.', ' '), '', $row->pa_passport);
                            $has_passport = strlen($row->pa_passport) >= 8;
                            if (str_replace('0', '', $row->pa_passport) === '') {
                                $has_passport = false;
                            }

                            $pdflink = '';
                            if ($row->id > 0 && $row->estatus == 'C') {
                                $file = $row->btype . '_' . trpdd_patient_encode($row->id) . '_' . $user_id . '.pdf';
                                if (!empty($lab_url)) {
                                    $auth = base64_encode(time() . '_' . $user_id . '_' . $row->id);
                                    $pdflink = $lab_url . '?tr_action=ci_results_pdf&auth=' . $auth . '&file=' . $file;
                                } else {
                                    $pdflink = Tr_Base_Class_V4::link(array('tr_action' => 'patient_result_pdf', 'file' => $file), false);
                                }

                                if (isset($options['mms_notify']) && $options['mms_notify'] && $has_passport) {
                                    $pdflink .= '&hqr';
                                }
                            }




                            $panel = array();
                            foreach (explode(',', $row->panel_tests_requested) as $t) {
                                if (stripos($t, 'group') === false) {
                                } else {
                                    $group_id = str_ireplace('group_', '', $t);

                                    if (isset($all_groups[$group_id])) {
                                        $panel[] = $all_groups[$group_id]->tg_name;
                                    }
                                }
                            }

                            if (empty($panel) && $row->btype == 'tox') {
                                $panel[] = 'Tox';
                            }

                            $class = '';
                            $result_time = strtotime($row->approve_date);
                            if ($options['ew_1week'] && $today_time - $result_time > 86400 * 7) {
                                $class .= ' warning_1week ';
                            }

                    ?>
                            <tr class="childp <?php echo $class ?>">
                                <td class="number" data-colname="ID"><?php echo $total - ($offset) - $i ?></td>
                                <td class="assen" data-colname="Accession"><?php echo $row->assen ?></td>
                                <td class="patient" data-colname="Patient Name"><?php echo $row->pa_last . ', ' . $row->pa_first ?></td>
                                <td class="patientdob" data-colname="Patient DOB"><?php echo $row->pa_dob ?></td>
                                <td class="clinic" data-colname="Clinic"><?php echo $row->clinic ?></td>
                                <td class="doctor" data-colname="Doctor"><?php echo $row->doctor ?></td>
                                <td class="btype" data-colname="Type" style="font-size:11px"><?php echo implode(', ', $panel) ?></td>
                                <td class="doc" data-colname="Collect Date"><?php echo tr_pdd_display_date($row->doc, 'Y-m-d') ?></td>
                                <td class="doc" data-colname="Result Date"><?php echo tr_pdd_display_date($row->approve_date, 'Y-m-d') ?></td>
                                <td class="viewlink center">
                                    <?php if (!empty($pdflink)) { ?>
                                        <a target="_blank" href="<?php echo $pdflink ?>">View Result</a>
                                    <?php } else {
                                        $step = 2;
                                        if ($row->estatus == 'C') {
                                            $step = 5;
                                        } else if ($row->id > 0) {
                                            $step = 4;
                                        } else if ($row->spec_received >= $row->doc) {
                                            $step = 3;
                                        }
                                        $arr = $steps[$step];
                                        echo $arr['title'];
                                    } ?>
                                </td>
                            </tr>
                        <?php
                        }
                    } else {
                        ?>
                        <tr class="childp">
                            <td colspan="10" class="center notfound">No Results have been Released yet!</td>
                        </tr>
                    <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <div class="tablenav"><?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?></div>
    </div>

    <?php
    if ($is_kid) {
    ?>
        <a href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'removekid', 'id' => $patient_id)) ?>">Remove this Member</a>
    <?php
    }
    ?>
</div>

<?php
if ($options['ew_1week']) {

    wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');


    wp_enqueue_script('jquery-ui');
    wp_enqueue_script('jquery-ui-dialog');
    wp_enqueue_style('wp-jquery-ui-dialog');
?>
    <div id="dialog_warning_empty" style="display: none;">
        <div style="color:red">IMPORTANT: The results you are about to access are more than 1 week old,
            please ensure you accessing your most recent result as referenced by the Date column.</div>
    </div>

    <script>
        jQuery(function($) {
            $('body').on('click', 'tr.warning_1week .viewlink a', function() {
                var $this = $(this);
                $("#dialog_warning_empty").dialog({
                    modal: true,
                    title: 'Warning',
                    width: 400,
                    dialogClass: 'receivingwarning',
                    buttons: {
                        "I Understand!": function() {
                            location.href = $this.attr('href');
                            $(this).dialog("close");
                        }
                    }
                });
                return false;
            });
        })
    </script>
<?php
}
