<?php
global $wpdb, $blog_id, $current_role;
$user_id = get_current_user_id();
$batch_id = $_REQUEST['batch_id'];
$lab_id = $_REQUEST['lab_id'];
$cnum = str_replace('C', '', $_REQUEST['cnum']);

$where = " and ba.batch_id = '{$batch_id}' and lab.lab_ID = {$lab_id}";
$orderby = ' order by ba.position asc';
$order = @$_REQUEST['order'] == 'desc' ? 'desc' : 'asc';
$joins = '';

if (!empty($_REQUEST['s'])) {

    if (strpos($_REQUEST['s'], ',') > 0) {
        list($firstname, $lastname) = explode(',', $_REQUEST['s']);
        $firstname = trim($firstname);
        $lastname = trim($lastname);
        $explodeString = "%{$firstname}%{$lastname}%";
        $where .= $wpdb->prepare(" and (ba.patient_name like %s ) ", $explodeString);
    } else {
        $where .= $wpdb->prepare(" and (ba.patient_name like %s or ba.assen = %s) ", '%' . $_REQUEST['s'] . '%', $_REQUEST['s']);
    }
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
}


$joins .= " JOIN ci_batches ba ON ba.assen = te.te_assen
    JOIN ci_labs lab ON lab.lab_ID = ba.lab_id 
    LEFT JOIN ci_reports re ON  ba.assen = re.patient_id ";

$where .= " and ((re.estatus != 'c' and ba.batch_status='completed') or (ba.batch_status='cold_storage'))";
$sql = "select te.*,
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
        ba.batch_status
    from ci_tecan te
    {$joins}

    where 1 {$where} group by ba.mid {$orderby}";



$time_start  = microtime(true);
$results = $wpdb->get_results($sql);
$count_rows = count($results);

if (isset($_REQUEST['debug'])) {
    echo microtime(true) - $time_start;
    echo $sql;
}


$oral_content = '';

$current_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;
$time_now = time() + $current_offset;

echo '<table class="report_tbl" data-now="' . date('Y-m-d H:i:s', $time_now) . '">';
foreach ($results as $i => $row) {

    $mstatus = '';
    $color = '';

    $is_oral = (stripos($row->type_of_specimen, 'oral') !== false);

    if (stripos($row->panel_tests_requested, 'Ethanol*') !== false) {
        $mstatus .= '<red>AL</red>';
    }

    $row->doc = date('m/d/Y H:i', strtotime($row->doc));

    //check color by days
    if ($row->batch_status == 'cold_storage') {
        $tecan_time = strtotime($row->te_created);
        if ($time_now - $tecan_time < 86400) { //24h
            $color = 'background:green';
        } else if ($time_now - $tecan_time < 86400 * 2) {
            $color = 'background:blue';
        } else if ($time_now - $tecan_time < 86400 * 3) {
            $color = 'background:red';
        } else {
            $row->batch_status = 'Discard';
        }
    }

    if (!empty($cnum)) {
        $wpdb->update('ci_tecan', array('te_number' => $cnum), array('te_ID' => $row->te_ID));
    }


    ob_start();
?>
    <tr class="childp" data-id="<?php echo $row->id ?>">
        <td style="width: 25px;" class="cid" title="<?php echo $row->mid ?>"><?php echo $row->id ?></td>
        <td style="width: 9%;"><?php echo $row->assen ?></td>
        <td style="width: 5%;"><?php echo $row->position ?></td>
        <td style="width: 6%;" title="<?php echo $row->sublab ?>"><?php echo $row->lab_dba_init ?></td>
        <td style="width: 11%;"><?php echo tr_pdd_capitalized($row->patient_name) ?></td>
        <td style="width: 14%;"><?php echo $row->clinic ?></td>
        <td style="width: 11%"><?php echo $row->doctor ?></td>
        <td style="width: 10%;"><?php echo $row->doc ?></td>
        <td style="width: 2%;"><?php echo $mstatus ?></td>
        <td style="width: 12%;<?php echo $color ?>" title="<?php echo $row->te_created ?>" data-rest="<?php echo $time_now - $tecan_time ?>"><?php echo ($row->batch_status == 'Discard') ? 'Discard' : tr_pdd_get_batch_statuses($row->batch_status, false, false, $row->btype) ?></td>
        <td class="actions"></td>
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
