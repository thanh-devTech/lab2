<?php
global $wpdb, $blog_id, $current_role;


$user_id    = get_current_user_id();
$batch_id   = $_REQUEST['batch_id'];
$lab_id     = $_REQUEST['lab_id'];
$time_show_dl = strtotime('2016-05-16');
$today      = Tr_Base_Class_V4::date('Y-m-d H:i:s');
$miss       = isset($_GET['drug']) ? $_GET['drug'] : 'miss';
$prio           = isset($_REQUEST['prio']) ? $_REQUEST['prio'] : '';

$where = " and ba.batch_id = '{$batch_id}' and lab.lab_ID = {$lab_id}";
$where  .= " and ba.batch_status !='archived' ";
$orderby = ' order by has_met_pos desc, ba.position asc';
$order = @$_REQUEST['order'] == 'desc' ? 'desc' : 'asc';
$joins = '';
$current_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;

if (tr_current_role_in(array('office_manager')) || $current_role == 'clinic') {

    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where .= " and ba.cli_ID = {$current_clinic} ";
    } else {
        if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
            $cid     = get_user_meta($user_id, '_clinic_id', true);
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

if (!empty($_REQUEST['s']) || !empty($_REQUEST['drug'])) {
    if (@$_REQUEST['drug'] == 'Alcohol') {
        $where .= $wpdb->prepare(" and ba.panel_tests_requested like %s and re.sample_type !='oral' ", '%ethanol*%');
    } else if (@$_REQUEST['drug'] == 'Methamphetamine DL') {
        $where .= " and ba.type_of_specimen not like 'oral%' and rs.id > 0 "; //and ba.panel_tests_requested like '%Methamphetamine d/l%'
    }


    if (stripos($_REQUEST['s'], 'medication:') !== false) {
        $pn = str_ireplace('medication:', '', $_REQUEST['s']);
        $where .= $wpdb->prepare(" and re.medications like %s ", '%' . $pn . '%');
    } else if (stripos($_REQUEST['s'], 'pos:') !== false) {
        $pn = trim(str_ireplace('pos:', '', $_REQUEST['s']));
        if (strpos($pn, ' ') > 0) {
            list($number, $pn) = explode(' ', $pn, 2);
        }
        $joins .= " join ci_results rspos ON rspos.report_id = re.id and rspos.drug='{$pn}' and rspos.`pos-neg`='POS' ";
        if ($number > 0) {
            $joins .= " and rspos.conc < {$number} ";
        }
    } else if (stripos($_REQUEST['s'], 'panel:') !== false) {
        $pn = str_replace('panel:', '', $_REQUEST['s']);
        $where .= $wpdb->prepare(" and ba.panel_tests_requested like %s ", '%' . $pn . '%');
    } else if ($_REQUEST['s'] == 'ethanol*') {

        $where .= $wpdb->prepare(" and ba.panel_tests_requested like %s ", '%' . $_REQUEST['s'] . '%');
    } else if ($_REQUEST['s'] == 'rejected') {
        $where .= " and (ba.batch_status like 're\_%' or ba.batch_status='rejected')";
    } else {

        if (strpos($_REQUEST['s'], ',') > 0) {
            list($firstname, $lastname) = explode(',', $_REQUEST['s']);
            $firstname = trim($firstname);
            $lastname = trim($lastname);
            $explodeString = "%{$firstname}%{$lastname}%";
            $where .= $wpdb->prepare(" and (ba.patient_name like %s)", $explodeString);
        } else {
            $where .= $wpdb->prepare(" and (ba.patient_name like %s or ba.assen = %s)", $_REQUEST['s'] . '%', $_REQUEST['s']);
        }
    }
}



if (tr_current_role_in(array('office_manager'))) {

    if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
        $cid     = get_user_meta($user_id, '_clinic_id', true);
        $cli_ids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
        $cli_ids = implode(',', $cli_ids);

        $where .= " and ba.cli_ID in ( {$cli_ids} )";
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
} else if ($user_id > 0 && !tr_current_role_in(array('administrator', 'AdminCIDW', 'administrator_p', 'scientist', 'labmanager', 'lab_tech'))) {
    $where .= " and re.author_id = {$user_id} ";
}


if ($prio > 0) {
    $limidatet = date('Y-m-d H:i:s', strtotime(date_i18n('Y-m-d H:i:s')) - $prio * 3600);
    $where .= " and ba.spec_received < '{$limidatet}' ";
}

$where .= " and (re.estatus !='C' or re.estatus is null ) ";


$joins .= " ";

$sql = "select re.*,
        ba.mid,
        ba.patient_name,
        ba.requesting_practice as clinic,
        ba.requesting_physician as doctor,
        ba.panel_tests_requested,
        ba.type_of_specimen,
        ba.current_medication,
        ba.position,
        ba.assen,
        ba.doc,
        ba.batch_created,
        lab.lab_ID,
        lab.lab_dba_init,
        rs.id as has_met_pos,
        rsp.id as has_pending,
        group_concat(rsp.drug) AS tests

    from  ci_batches ba 
    JOIN ci_labs lab ON lab.lab_ID = ba.lab_id 
    left JOIN ci_reports re ON ba.assen = re.patient_id
    LEFT JOIN ci_results rs ON rs.report_id = re.id 
        and rs.drug  = 'Methamphetamine' and rs.`pos-neg`= 'POS'

    JOIN ci_results rsp ON rsp.report_id = re.id 
        and rsp.`pos-neg`= 'PEN'

    where 1 {$where} group by ba.mid {$orderby}";



$time_start  = microtime(true);
$results    = $wpdb->get_results($sql);
$count_rows = count($results);

if (isset($_REQUEST['debug'])) {
    echo microtime(true) - $time_start;
    echo $sql;
}

echo '<table class="report_tbl child_list">';
$oral_content = '';
foreach ($results as $i => $row) {

    $mstatus = '';

    $is_oral = (stripos($row->type_of_specimen, 'oral') !== false);
    if ($row->has_met_pos && !$is_oral) {
        $mstatus .= '<ogr>DL</ogr>';
    }

    if (stripos($row->panel_tests_requested, 'Ethanol*') !== false) {
        $mstatus .= '<red>AL</red>';
    }

    $row->doc = date('m/d/Y H:i', strtotime($row->doc));

    ob_start();
?>
    <tr class="childp" data-id="<?php echo $row->id ?>">
        <td style="width: 6%;" class="cid" title="<?php echo $row->mid ?>"><?php echo $row->id ?></td>
        <td style="width: 6%;"><?php echo $row->assen ?></td>
        <td style="width: 5%;"><?php echo $row->position ?></td>
        <td style="width: 10%;"><?php echo tr_pdd_capitalized($row->patient_name) ?></td>
        <td style="width: 10%;"><?php echo $row->clinic ?></td>
        <td style="width: 9%"><?php echo $row->doctor ?></td>
        <td style="width: 16%;"><?php echo str_replace(',', "<br>", $row->tests) ?></td>
        <td style="width: 10%;"><?php echo $row->doc ?></td>
        <td style="width: 2%"><?php echo $mstatus ?></td>
        <td style="width: 5%;" class="result_<?php echo $row->estatus ?>">
            <?php
            echo $row->estatus;

            ?>
        </td>
        <td style="width: 15%;" class="actions">
            <?php if ($row->estatus == 'C'): ?>
                <a class="reverse_link" href="#">Reverse</a> -
                <a class="pdf_link" href="#">PDF</a> -
            <?php else: ?>
                <a class="edit_link" href="#">Edit</a> -
                <a class="approve_link" href="#">Approve</a> -
            <?php endif; ?>
            <a class="delete_link" href="#">Delete</a>
        </td>
    </tr>
    <?php
    $content = ob_get_clean();

    if (stripos($row->type_of_specimen, 'oral') !== false || stripos($row->sample_type, 'oral') !== false) {
        $oral_content .= $content;
    } else {
        echo $content;
    }
}
if (!empty($oral_content)) {
    ?>
    <tr class="childp">
        <td colspan="12" align="center">-------- Orals -------</td>
    </tr>
<?php
    echo $oral_content;
}


echo '</table>';
