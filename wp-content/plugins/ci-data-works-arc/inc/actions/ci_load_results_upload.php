<?php
global $wpdb, $blog_id, $current_role;

$result_dir     = tr_get_result_upload_dir();
$user_id        = get_current_user_id();
$batch_id       = $_REQUEST['batch_id'];
$lab_id         = $_REQUEST['lab_id'];
$type           = $_REQUEST['type'] ? $_REQUEST['type'] : 'dna';
$pending        = isset($_REQUEST['pending']);
$is_test_results    = isset($_GET['page']) && $_GET['page'] == 'ci_test_results';

$btype          = tr_pdd_get_numtype($type);

$table          = 'ci_batches';

if ($btype > 0) {
    //$where.= " and ba.is_dna = {$btype} ";
    $table = tr_pdd_get_table_by_btype($type);
}


$where = " and ba.batch_id = '{$batch_id}' ";
$where .= " and ba.batch_status != 'archived' ";
if (!empty($lab_id)) {
    $where .= " and lab.lab_ID = {$lab_id} ";
} else if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $where .= " and lab.lab_ID in ( {$lab_str} )";
}

if ($pending) {
    $where .= " and (ba.result_pdf = '' or ba.result_pdf is null) and re.id is null and ba.batch_status not like 're\_%' and ba.batch_status !='rejected'  ";
} else {
    $where .= " and (ba.result_pdf != '' or re.id > 0 or " . tr_pdd_where_batch_rejected() . " )";
}


if (tr_current_role_in(array('office_manager')) || $current_role == 'clinic') {

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
    $doc_id = get_user_meta($user_id, '_doc_id', true);
    $where .= " and ba.doc_ID = {$doc_id} ";
} else if ($current_role == 'lab_rep') {
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
    $col_id     = get_user_meta($user_id, '_collector_id', true);
    $clinics    = $wpdb->get_col("select cli_ID from ci_clinic_collector where col_ID = {$col_id}");
    $clinics    = implode(',', $clinics);
    $where      .= " and ba.cli_ID in ({$clinics}) ";
}

if (!defined('TRPDD_SAME_LAB') || !TRPDD_SAME_LAB) {
    if (CI_Base_Lab::has_user_labs()) {
        $lab_str = CI_Base_Lab::get_user_lab_ids_str();

        $where .= " and (ba.lab_id in( {$lab_str} ) or ba.ref_lab_ID in ( {$lab_str} ) ) ";
    }
}

if (!empty($_REQUEST['s'])) {

    if (strpos($_REQUEST['s'], ',') > 0) {
        list($firstname, $lastname) = explode(',', $_REQUEST['s']);
        $firstname = trim($firstname);
        $lastname = trim($lastname);
        $explodeString = "%{$firstname}%{$lastname}%";
        $where .= $wpdb->prepare(
            " 
            and (ba.patient_name like %s 
            or ba.requesting_practice like %s
            or ba.requesting_physician like %s)",
            $explodeString,
            $_REQUEST['s'] . '%',
            $_REQUEST['s'] . '%'
        );
    } else {
        $where .= $wpdb->prepare(
            " 
            and (ba.patient_name like %s 
            or ba.assen like %s 
            or ba.requesting_practice like %s
            or ba.requesting_physician like %s)",
            $_REQUEST['s'] . '%',
            $_REQUEST['s'] . '%',
            $_REQUEST['s'] . '%',
            $_REQUEST['s'] . '%'
        );
    }
}
$sql = "select ba.*,
                lab.*, 
                re.id as reid, 
                re.estatus,
                re.upload_files
                from {$table} ba 
                  JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
                  left join ci_reports re ON re.patient_id = ba.assen
                  WHERE 1 {$where}";
$results = $wpdb->get_results($sql);

if (isset($_GET['debug'])) {
    echo '<br><br>' . "\n\n";
    echo $sql;
    echo '<br><br>' . "\n\n";
    var_dump($wpdb->last_query);
}

if (count($results) == 0) {
    echo '<p class="center">Not found</p>';
    return;
}

echo '<table class="report_tbl child_list" data-total="' . $count_rows . '">';
foreach ($results as $row) {
    $has_critical = false;
    if ($row->reid > 0 && $type == 'blood') {
        $has_critical = $wpdb->get_var("select count(*) from ci_results rs 
                                JOIN ci_test_panels p ON rs.drug = p.te_name 
                                and p.te_critical_low>0 and p.te_critical_high>0
                                and p.te_critical_low < rs.conc
                                and p.te_critical_high > rs.conc
                                where rs.report_id = {$row->reid}");
    }
?>
    <tr class="childp" data-id="<?php echo $row->reid ?>" data-mid="<?php echo $row->mid ?>" data-assen="<?php echo $row->assen ?>">
        <td style="width: 6%" class="cid" title="<?php echo $row->mid ?>"><?php echo $row->position ?></td>
        <td style="width: 8%;" colspan="2"><?php echo $row->assen ?></td>
        <td style="width: 12%;"><?php echo tr_pdd_capitalized($row->patient_name) ?></td>
        <td style="width: 80px;"><?php echo $row->dob ?></td>
        <td style="width: 12%"><?php echo $row->requesting_physician ?></td>
        <td style="width: 18%;"><?php echo stripslashes($row->requesting_practice) ?></td>
        <td style="width:11%"><?php echo $row->insurance ?></td>
        <td style="width: 80px;"><?php echo tr_pdd_display_date($row->doc, 'Y-m-d') ?></td>
        <?php if ($is_test_results) { ?>
            <td style="width: 10%"><?php echo $row->batch_status ?></td>
        <?php } ?>
        <td style="width:50px">
            <?php if ($type != 'tox') {
                $type_form = $type . '_req_form';
            ?>
                <a href="?page=<?php echo $type_form ?>&act=view&id=<?php echo $row->mid ?>" target="_blank">View</a>
            <?php } else {
            ?>
                <a href="?page=tox_req_form&act=view&id=<?php echo $row->mid ?>" target="_blank">View</a>
            <?php
            } ?>
        </td>
        <?php if ($type == 'blood') : ?>
            <td style="width:40px">
                <?php if (!empty($row->original_req)) : ?>
                    <a href="<?php echo $result_dir['url'] . '/' . $row->original_req ?>" target="_blank">PDF</a>
                <?php endif ?>
            </td>
            <td style="width:30px" class="red bold">
                <?php if ($has_critical) echo 'A' ?>
            </td>
        <?php endif; ?>
        <td class="actions aright">
            <?php
            if (stripos($row->batch_status, 're_') === 0 || $row->batch_status == 'rejected') {
            ?>
                <a href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_results_pdf', 'mid' => $row->mid, 'btype' => $type)) ?>">PDF</a>
                <?php
            } else  if (!empty($row->result_pdf)) {
                if ($type == 'tox') {
                ?>
                    <a href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_results_pdf', 'mid' => $row->mid, 'btype' => $type)) ?>">PDF</a>
                <?php
                } else {
                ?>
                    <a href="<?php echo $result_dir['url'] . '/' . $row->result_pdf ?>" target="_blank">PDF</a>
                    <?php
                    if ($row->reid > 0 && !empty($row->upload_files)) {
                    ?>
                        <br>
                        <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->reid, 'btype' => $type)) ?>">Edit</a>
                        <br>
                        <a class="delete" href="#">Delete</a>
                        <?php
                        if ($row->estatus == 'C') {
                        ?>
                            <br>
                            <a href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_results_pdf', 'mid' => $row->mid, 'btype' => $type)) ?>">PDF</a>
                <?php
                        }
                    }
                }
            } else if ($row->reid > 0) {
                ?>
                <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->reid, 'btype' => $type)) ?>">Edit</a>
                <br>
                <a class="delete" href="#">Delete</a>
            <?php
            } else {
            ?>
                <a class="upload_result_pdf" href="#">Upload</a>
            <?php
            }
            ?>
        </td>
    </tr>
<?php
}
echo '</table>';
