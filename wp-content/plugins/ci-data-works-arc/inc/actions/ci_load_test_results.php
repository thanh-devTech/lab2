<?php
global $wpdb, $current_role;
global $ci_only_results;


$user_id    = get_current_user_id();
$result_dir = tr_get_result_upload_dir();
$doc        = $_REQUEST['doc'];
$fromdate           = isset($_GET['fromdate']) ? $_GET['fromdate'] : '';
$todate             = isset($_GET['todate']) ? $_GET['todate'] : '';
$last_login = get_user_meta($user_id, '_last_login', true);
$show_filter_approve_date = tr_get_option('ci_pdf_data', 'teresult_approvefilter');

if (empty($last_login)) {
    $last_login = date('Y-m-d H:i:s', strtotime('-2days'));
}
$last_login_time = strtotime($last_login);
$where          = ' and ba.is_saved = 0 ';
$show_clinic    = $show_doctor = false;

$all_status = tr_pdd_get_batch_statuses(false, false, false, 'all');

if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();

    $where .= " and (ba.lab_id in ( {$lab_str} ) or ba.ref_lab_ID in ( {$lab_str} ) )";
} else if (!defined('TRPDD_SAME_LAB') || !TRPDD_SAME_LAB) {
    $lab_id = CI_Base_Lab::get_current_lab_id();
    $where .= " and (ba.lab_id in ( {$lab_id} ) or ba.ref_lab_ID in ( {$lab_id} ) )";
}

if (!empty($_REQUEST['s'])) {

    if (strpos($_REQUEST['s'], ',') > 0) {
        list($firstname, $lastname) = explode(',', $_REQUEST['s']);
        $firstname = trim($firstname);
        $lastname = trim($lastname);
        $explodeString = "%{$firstname}%{$lastname}%";
        $where .= $wpdb->prepare(" and (ba.patient_name like %s)", $explodeString);
    } else {
        $where .= $wpdb->prepare(" and (ba.assen like %s or ba.patient_name like %s)", $_REQUEST['s'] . '%', '%' . $_REQUEST['s'] . '%');
    }
}
if (!empty($_REQUEST['clinic'])) {
    $where .= $wpdb->prepare(" and ba.requesting_practice like %s ", $_REQUEST['clinic'] . '%');
}

if (!empty($_REQUEST['doctor'])) {
    $where .= $wpdb->prepare(" and ba.requesting_physician like %s ", $_REQUEST['doctor'] . '%');
}
$show_status = true;
if (isset($_REQUEST['new_results'])) {

    $where .= " and re.approve_date >= '{$last_login}' and re.estatus= 'C' ";
    $show_status = false;
}

if (isset($_REQUEST['revised_results'])) {
    $where .= " and re.internal_notes!='' and re.estatus= 'C' ";
    $show_status = false;
}

if (isset($_REQUEST['pos_results'])) {
    $where .= " and re.estatus= 'C' and re.has_pos = 1 ";
    $show_status = false;
}


if ($show_filter_approve_date) {
    if (!empty($fromdate)) {
        $where .= " and re.approve_date >= '{$fromdate}' ";
    }

    if (!empty($todate)) {
        $where .= " and re.approve_date <= '{$todate} 23:59:59' ";
    }
}


$user = wp_get_current_user();

if (tr_current_role_in(array('administrator', 'administrator_p', 'AdminCIDW'))) {
    $show_clinic = true;
    $show_doctor = true;
} else if ($current_role == 'labmanager') {
    $show_clinic = true;
    $show_doctor = true;
} else if ($current_role == 'lab_director') {
    $show_clinic = true;
    $show_doctor = true;
} else if (tr_current_role_in(array('office_manager')) || $current_role == 'clinic') {

    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where .= " and ba.cli_ID = {$current_clinic} ";
    } else {
        if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
            $cid = get_user_meta($user_id, '_clinic_id', true);
            $cli_ids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
            $cli_ids = implode(',', $cli_ids);

            $where .= " and ba.cli_ID in ( {$cli_ids} )";
        }
    }
} else if (tr_current_role_in(array('multi_site_manager'))) {

    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where .= " and ba.cli_ID = {$current_clinic} ";
    } else  if (tr_get_option('ci_pdf_data', 'msm_can_show_all')) {
        //can see all clinics
    } else if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
        $ms_cli_ids = get_user_meta($user_id, '_ms_clinic_ids', true);
        if (!is_array($ms_cli_ids) || empty($ms_cli_ids)) {
            $ms_cli_ids = $wpdb->get_col("select cID from ci_clinic where cli_status = 1 ");
            update_user_meta($user_id, '_ms_clinic_ids', $ms_cli_ids);
        }
        $ms_cli_ids_str = implode(',', $ms_cli_ids);
        $where .= " and ba.cli_ID in ( {$ms_cli_ids_str} )";
    }
} else if ($current_role == 'doctor') {
    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where .= " and ba.cli_ID = {$current_clinic} ";
    }
    $doctor_id = get_user_meta($user_id, '_doc_id', true);
    $where .= " and ba.doc_ID = {$doctor_id} ";
} else if ($current_role == 'lab_rep' || $current_role == 'lab_rep_w2') {
    $show_doctor = true;
    $rep_id = get_user_meta($user_id, '_rep_id', true);
    $clinics = $wpdb->get_col("select cli.cID from ci_clinic cli where cli.`cli_rep-ID` = {$rep_id}");
    $clinics = implode(',', $clinics);
    $where .= " and ba.cli_ID in  ({$clinics}) ";
} else if ($current_role == 'distributor') {
    $dis_ID = $wpdb->get_var("SELECT dis_ID FROM `ci_distributors` WHERE `dis_user_ID`=$user_id ");
    $reps = $wpdb->get_col("SELECT rep.rep_ID FROM ci_lab_reps rep
                            left JOIN `ci_marketing_groups` mg ON mg.gr_ID=rep.rep_marketing_group_ID
                             where (mg.`gr_distributor_ID`= {$dis_ID})
                             or (rep.`rep_distributor_ID`={$dis_ID} and rep.`rep_marketing_group_ID`='0') ");


    $reps = implode(',', $reps);
    $clinics = $wpdb->get_col("select cli.cID from ci_clinic cli where cli.`cli_rep-ID` in ($reps)");
    $clinics = implode(',', $clinics);
    $where .= " and ba.cli_ID in ({$clinics}) ";
} else if ($current_role == 'collector') {
    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where .= " and ba.cli_ID = {$current_clinic} ";
    }
    $col_id = get_user_meta($user_id, '_collector_id', true);
    $clinics = $wpdb->get_col("select cli_ID from ci_clinic_collector where col_ID = {$col_id}");
    $clinics = implode(',', $clinics);
    $where .= " and ba.cli_ID in ({$clinics}) ";
}



$where .= " and (ba.batch_status='completed' or ba.batch_type='E' or ba.batch_type='M' or re.estatus= 'C' ) ";
$where .= " and ba.batch_status != 'not_received' ";
$where .= " and (ba.doc>='{$doc}' and ba.doc<= '{$doc} 23:59:59') ";

$sql = "select re.*,
    ba.mid,
    ba.doc,
    ba.batch_type,
    ba.assen,
    ba.patient_name as batch_patient_name,
    ba.dob,
    ba.requesting_physician,
    ba.requesting_practice,
    ba.batch_status,
    ba.spec_received,
    ba.result_pdf
    from ci_batches ba
    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
    left JOIN ci_reports re ON ba.assen = re.patient_id
    where 1 {$where}
    group by ba.mid
    order by re.id desc, ba.doc desc";

if (isset($_REQUEST['debug'])) {
    echo '<div>' . $sql . '</div>';
}
$results = $wpdb->get_results($sql);

if (count($results) == 0) {
    echo '<p class="center">Not found</p>';
}
?>
<table class="report_tbl">
    <?php
    foreach ($results as $i => $row) {
        $row_class = '';
        $approve_time = strtotime($row->approve_date);
        $row->batch_status = $all_status[$row->batch_status];
        if ($row->estatus == 'C') {
            $row->batch_status = 'Approved';
        } else if (!empty($row->result_pdf)) {
            $row->batch_status = 'Approved';
            $fpath = $result_dir['path'] . '/' . $row->result_pdf;
            if (file_exists($fpath)) {
                $approve_time = filemtime($fpath);
                $row->approve_date = date('Y-m-d H:i:s', $approve_time);
            }
        }
        $info = '';
        $status_class = '';

        if (strtotime($row->spec_received) > 0) {
            $info .= 'Received: ' . $row->spec_received . "\n";
        }

        if (strtotime($row->spec_received) > 0) {
            $info .= 'Processing: ' . $row->spec_received . "\n";
        }

        if ($approve_time > 0) {
            $info .= 'Approved: ' . $row->approve_date;
            if ($last_login_time < $approve_time) {
                $status_class .= ' new_results ';
            }
        }
        if (!empty($row->internal_notes)) {
            $status_class .= ' revised_results ';
        }
        $users_printed = explode(',', $row->users_printed);

        $row_class .= ' bs_' . $row->batch_status;

        if ($row->has_pos > 0) {
            $row_class .= ' has_pos ';
            $row_class .= ' has_pos_' . $row->has_pos;
        }

    ?>
        <tr class="childp <?php echo $row_class ?>" data-id="<?php echo $row->id ?>" data-estatus="<?php echo $row->estatus ?>">
            <td style="width:2%"></td>
            <td style="width:8%" title="<?php echo $row->mid . ' | ' . $row->batch_id ?>"><?php echo date('Y-m-d', strtotime($row->doc)) ?></td>
            <?php if ($show_clinic) : ?>
                <td style="width:15%"><?php echo $row->requesting_practice ?></td>
            <?php endif; ?>
            <?php if ($show_doctor) : ?>
                <td style="width:12%"><?php echo $row->requesting_physician ?></td>
            <?php endif; ?>
            <td style="width:8%"><?php echo $row->batch_type ?></td>
            <td style="width: 110px;"><?php echo $row->assen ?></td>
            <td style="width: 12%;"><?php echo $row->batch_patient_name ?></td>
            <td style="width: 8%;"><?php echo $row->dob ?></td>
            <td style="width:8%" title="<?php echo $info ?>"><?php echo $row->batch_status ?></td>
            <?php if ($show_status) : ?>
                <td style="width:8%" class="statuscol2">
                    <span title="<?php echo $row->approve_date ?>" class="<?php echo $status_class ?>"></span>
                    <span class="linepos"></span>
                </td>
            <?php endif; ?>
            <td class="cactions">
                <?php if (!$ci_only_results) { ?>
                    <a class="thickbox" href="?tr_action=ci_view_req&id=<?php echo $row->mid ?>&width=1000&height=600">View</a>
                    <a class="iprint" data-href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_print_req', 'id' => $row->mid, 'type' => 'tox', 'tab' => '')) ?>">Print Req</a>
                <?php } ?>
                <?php
                if ($row->estatus == 'C') {
                ?>
                    <a class="download_pdf" target="_blank" href="?tr_action=ci_results_pdf&pdf=test_results&id=<?php echo $row->id;
                                                                                                                if ($_REQUEST['view']) echo '&view=1'; ?>">PDF</a>
                    <a class="print_pdf" href="?tr_action=ci_results_pdf&id=<?php echo $row->id ?>&print=test_results">Print</a>
                    <?php if (in_array($user_id, $users_printed)) : ?>
                        <span class="dashicons dashicons-yes"></span>
                    <?php endif; ?>
                <?php
                } else if (!empty($row->result_pdf)) {
                ?>
                    <a href="<?php echo $result_dir['url'] . '/' . $row->result_pdf ?>" target="_blank">PDF</a>
                    <a class="print_pdf" href="?tr_action=ci_results_pdf&mid=<?php echo $row->mid ?>&print=test_results">Print</a>
                    <?php if (in_array($user_id, $users_printed)) : ?>
                        <span class="dashicons dashicons-yes"></span>
                    <?php endif; ?>
                <?php
                }
                ?>
            </td>
        </tr>

    <?php
    }
    ?>
</table>