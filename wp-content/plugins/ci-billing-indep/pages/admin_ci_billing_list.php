<?php
global $wpdb, $current_role, $current_lab_id, $blog_id, $ci_active_menu, $show_menus_options;


$btype_colors = array(
    'tox' => '#7692c4',
    'blood' => '#e4666e',
    'dna' => 'blue',
    'ngs' => 'green',
    'pcr' => '#e36ce3'
);

$settings       = tr_billing_get_settings();
$user_id        = get_current_user_id();
$where_ba       = "";
$where          = $where_status = '';
$orderby        = ' order by result_date desc';
$order          = isset($_REQUEST['order']) && $_REQUEST['order'] == 'desc' ? 'desc' : 'asc';
$bill_status    = isset($_REQUEST['bill_status']) ? $_REQUEST['bill_status'] : '';
$rep            = isset($_REQUEST['rep']) ? $_REQUEST['rep'] : '';
$act            = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';
$cli_ID         = isset($_REQUEST['cli_ID']) ? $_REQUEST['cli_ID'] : 0;
$btype_selected = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : '';
$paytype        = isset($_REQUEST['paytype']) ? $_REQUEST['paytype'] : '';
$show_cares_act = defined('TRPDD_INS_CARES_ACT') && TRPDD_INS_CARES_ACT;

if (!tr_get_option('ci_pdf_data', 'req_cares_act') && !tr_get_option('ci_pdf_data', 'req_blood_cares_act')) {
    $show_cares_act = false;
}
$joins          = '';

if ($current_role == 'BillbyClinic') {
    $clinics = get_user_meta($user_id, '_bill_cli_IDs', true);
    if (!is_array($clinics)) {
        $clinics = array(0);
    }
    $clinics = implode(',', $clinics);
    $where_ba .= " and ba.cli_ID in ({$clinics}) ";
} else if ($current_role == 'BillbyRefLab') {
    $reflabid = get_user_meta($user_id, '_ref_lab_id', true);
    if (empty($reflabid)) {
        $reflabid = -1;
    }
    $where_ba .= " and ba.ref_lab_ID in ({$reflabid}) ";
} else if ($current_role == 'office_manager') {
    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where_ba .= " and ba.cli_ID = {$current_clinic} ";
    } else {

        if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true) || $cid > 0) {
            $cid     = get_user_meta($user_id, '_clinic_id', true);
            $cli_ids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
            $cli_ids = implode(',', $cli_ids);


            $where_ba .= " and ba.cli_ID in ( {$cli_ids} )";
        }
    }
} else if ($current_role == 'Billing' && !$settings['disable_billbyclinic']) {
    $excludecids = get_option('ci_bill_cli_IDs', array());

    if (is_array($excludecids) && count($excludecids) > 0) {
        $excludecids = implode(',', $excludecids);
        $where_ba .= " and ba.cli_ID not in ({$excludecids}) ";
    }
}

if (defined('TRPDD_UPSAVED') && TRPDD_UPSAVED) {
    $where_ba .= " and ba.is_saved = 0 ";
}


$where_ba .= " and " . tr_pdd_where_batch_rejected(false, true, '', array('cancelled')) . ' ';

if ($current_lab_id > 0 && $blog_id > 1) {
    $where_ba .= " and ba.lab_id = {$current_lab_id} ";
}

if (isset($settings['exclude_ref_lab']) && $settings['exclude_ref_lab']) {
    $where_ba .= " and (ba.ref_lab_id = 0 or ba.ref_lab_id = '{$current_lab_id}' ) ";
} else {
    if (isset($settings['include_ref_labs']) && !empty($settings['include_ref_labs'])) {
        $where_ba .= " and (ba.ref_lab_id = 0 or ba.ref_lab_id in ( {$settings['include_ref_labs']} ) ) ";
    }

    if (isset($settings['exclude_ref_labs']) && !empty($settings['exclude_ref_labs']) && empty($_REQUEST['lab_reflab'])) {
        $where_child = array();
        foreach (explode(',', $settings['exclude_ref_labs']) as $rlkey) {
            $where_ex = array();
            list($lab_id, $ref_lab_id) = explode('_', $rlkey);
            if ($lab_id > 0) {
                $where_ex[] = " ba.lab_ID = {$lab_id} ";
            }

            if ($ref_lab_id > 0) {
                $where_ex[] = " ba.ref_lab_ID = {$ref_lab_id} ";
            } else {
                $where_ex[] = " ba.ref_lab_ID = 0 ";
            }

            if (count($where_ex) > 0) {
                $where_child[] = ' ( ' . implode(' and ', $where_ex) . ' ) ';
            }
        }
        if (count($where_child) > 0) {
            $where_ba .= " and NOT (" . implode(' or ', $where_child) . ") ";
        }
    }
}


if (!empty($_REQUEST['lab_reflab'])) {
    list($lab_id, $ref_lab_id) = explode('_', $_REQUEST['lab_reflab']);
    if ($lab_id > 0) {
        $where_ba .= " and ba.lab_ID = {$lab_id} ";
    }

    if ($ref_lab_id > 0) {
        $where_ba .= " and ba.ref_lab_ID = {$ref_lab_id} ";
    } else {
        $where_ba .= " and ba.ref_lab_ID = 0 ";
    }
}

$user_ref_lab_id = get_user_meta($user_id, '_ref_lab_id', true);
if ($user_ref_lab_id > 0) {
    $where_ba .= " and ba.ref_lab_id = {$user_ref_lab_id}  ";
}


if (!empty($bill_status)) {
    $where_status .= " and bill_status = '{$bill_status}' ";
}

if (!empty($paytype)) {
    if ($paytype == 'no') {
        $where_ba .= " and ba.insurance = 'No Insurance - Cash' ";
    } else if ($paytype == 'clientb') {
        $where_ba .= " and (ba.cli_ID in (
            select cID from ci_clinic  where cli_pay_type = '{$paytype}' 
            ) or ba.insurance = 'Client bill')";
    } else {
        $where_ba .= " and ba.cli_ID in (
            select cID from ci_clinic  where cli_pay_type = '{$paytype}' 
            )";
    }
}


if ($rep > 0) {
    $cls_id = array();
    $clis   = $wpdb->get_results("select * from ci_clinic where `cli_rep-ID`={$rep}", ARRAY_A);
    foreach ($clis as $row) {
        $cls_id[] = $row['cID'];
    }
    $cls_id   = implode(', ', $cls_id);
    $where_ba .= " and ba.cli_ID in ({$cls_id}) ";
}

if ($cli_ID > 0) {
    $where_ba .= " and ( ba.cli_ID = {$cli_ID} or ba.ref_cli_ID = {$cli_ID}) ";
}


if (!empty($_REQUEST['s'])) {
    $search   = stripslashes($_REQUEST['s']);
    $where_ba .= $wpdb->prepare(" and (ba.patient_name like %s or ba.assen=%s)", '%' . $search . '%', $search);
}

if (!empty($_REQUEST['insurance'])) {
    $ins      = $_REQUEST['insurance'];
    $where_ba .= $wpdb->prepare(" and ba.insurance like %s ", '%' . $ins . '%');
}

if (!empty($_REQUEST['claim_number'])) {
    $claim_number = $_REQUEST['claim_number'];
    $where_ba     .= $wpdb->prepare(" and ba.claim_id like %s ", '%' . $claim_number . '%');
}


$where .= " and ((id is null and ba.result_pdf!='' and ba.result_pdf is not null)  or (id > 0 and estatus in ('C','F'))) ";


if ($act == 'export') {
    $file_name = 'billing_export_' . date_i18n('Y_m_d') . '.csv';
    header("Content-disposition: attachment; filename={$file_name}");
    header("Content-Type: text/csv");

    $f          = fopen('php://output', 'w');
    $headers    = array('Service Date', 'Accession', 'Name', 'Clinic', 'Doctor', 'Status');

    fputcsv($f, $headers);


    $orderby    = ' order by re.approve_date desc, ba.mid desc';

    $where      = " and re.estatus in ('C','F') ";

    if (!empty($bill_status)) {
        $where .= " and bb.bill_status = '{$bill_status}' ";
    }


    if (!empty($_REQUEST['s'])) {
        $search = stripslashes($_REQUEST['s']);
        $where .= $wpdb->prepare(" and (ba.patient_name like %s or ba.assen=%s)", '%' . $search . '%', $search);
    }
    if (!empty($_REQUEST['insurance'])) {
        $ins = $_REQUEST['insurance'];
        $where .= $wpdb->prepare(" and ba.insurance like %s ", $ins . '%');
    }


    if (!empty($paytype)) {
        if ($paytype == 'no') {
            $where .= " and ba.insurance = 'No Insurance - Cash' ";
        } else {
            $where .= " and cli_pay_type = '{$paytype}' ";
        }
    }



    if ($current_role == 'BillbyClinic') {
        $clinics = get_user_meta($user_id, '_bill_cli_IDs', true);
        if (!is_array($clinics)) {
            $clinics = array(0);
        }
        $clinics = implode(',', $clinics);
        $where .= " and ba.cli_ID in ({$clinics}) ";
    } else if ($current_role == 'office_manager') {

        $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
        if ($current_clinic > 0) {
            $where .= " and ba.cli_ID = {$current_clinic} ";
        } else {

            if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true) || $cid > 0) {
                $cid     = get_user_meta($user_id, '_clinic_id', true);
                $cli_ids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
                $cli_ids = implode(',', $cli_ids);


                $where .= " and ba.cli_ID in ( {$cli_ids} )";
            }
        }
    } else if ($current_role == 'Billing' && !$settings['disable_billbyclinic']) {
        $excludecids = get_option('ci_bill_cli_IDs', array());

        if (is_array($excludecids) && count($excludecids) > 0) {
            $excludecids = implode(',', $excludecids);
            $where .= " and ba.cli_ID not in ({$excludecids}) ";
        }
    }


    $sql_child = array();

    $has_more_type = false;
    $rpp_gi = false;
    if (function_exists('tr_pdd_get_batch_types')) {
        $where_join = " bb.assen = ba.assen ";
        if (tr_billing_get_settings('enable_claim_no')) {
            $where_join = " (bb.claim_no!='' and bb.claim_no is not null and bb.claim_no = ba.claim_id)
                                                        or (bb.assen = ba.assen) ";
        }

        foreach (trbilling_req_types() as $t) {

            if ((!empty($btype_selected) && $t != $btype_selected) || $t == 'study') {
                continue;
            }

            if (
                $show_menus_options['all']['form_' . $t]
                || $ci_active_menu['form_' . $t]
                || in_array($current_role, array('Billing', 'BillbyClinic', 'AdminCIDW'))
            ) {
                $batch_tbl = tr_pdd_get_table_by_btype($t);
                $report_tbl = tr_pdd_get_table_report_by_btype($t);
                $has_more_type = true;

                $sql_child[] = "(select
                        ba.assen,
                        ba.patient_name,
                        ba.requesting_practice,
                        ba.requesting_physician,
                        ba.doc,
                        bb.bill_status,
                        '{$t}' as btype,
                        
                        re.approve_date
                
                    from {$batch_tbl} ba
                    left join {$report_tbl} re ON  ba.assen = re.patient_id  
                    left join ci_clinic cli ON cli.cID = ba.cli_ID
                    left join ci_batch_billings bb ON {$where_join}
                    where 1=1 {$where} {$where_ba}
                    group by ba.mid 
                    order by ba.mid desc)";
            }
        }
    }
    $sql_child = implode("\n UNION ALL \n", $sql_child);

    $sql = "select * from ( {$sql_child} ) as rs  ";
    $results = $wpdb->get_results($sql);


    foreach ($results as $row) {
        if (empty($row->approve_date)) {
            $row->approve_date = $row->doc;
        }
        $row->approve_date = date('Y-m-d', strtotime($row->approve_date));
        $data_row = array(
            $row->approve_date,
            $row->assen,
            $row->patient_name,
            $row->requesting_practice,
            $row->requesting_physician,
            $row->bill_status
        );
        fputcsv($f, $data_row);
    }
    if (function_exists('trpdd_log_user')) {
        $data_log = [
            'type' => 'Specimen Billing',
            'file_name' => $file_name,
            'total_records' => count($results),
        ];
        trpdd_log_user(get_current_user_id(), 'download_billing', $data_log, '', '', '', '', 'Billing_' . date_i18n('mdY'));
    }

    exit;
}

$sql_child = array();

if (function_exists('tr_pdd_get_batch_types')) {
    $where_join = " bb.assen = ba.assen ";
    if (tr_billing_get_settings('enable_claim_no')) {
        $where_join = " (bb.claim_no!='' and bb.claim_no is not null and bb.claim_no = ba.claim_id)
                                                        or (bb.assen = ba.assen) ";
    }

    foreach (trbilling_req_types() as $t) {

        if ($t == 'study') continue;

        if (!empty($btype_selected) && $t != $btype_selected) {
            continue;
        }

        if ((isset($show_menus_options['all']['form_' . $t]) && $show_menus_options['all']['form_' . $t])
            || (isset($ci_active_menu['form_' . $t]) && $ci_active_menu['form_' . $t])
            || in_array($current_role, array('Billing', 'BillbyClinic', 'AdminCIDW'))
        ) {
            $batch_tbl  = tr_pdd_get_table_by_btype($t);
            $report_tbl = tr_pdd_get_table_report_by_btype($t);

            $cjoin      = '';
            $cfields    = '';

            if (!empty($bill_status)) {
                $cfields .= ',bb.bill_status ';
                $cjoin .= " join ci_batch_billings bb ON {$where_join} ";
            }

            $childq = " select ba.mid, 
                        ba.assen, 
                        ba.lab_id,
                        ba.result_pdf,
                        DATE_FORMAT(ba.doc,'%Y-%m-%d') as doc,
                        re.id,
                        DATE_FORMAT(approve_date,'%Y-%m-%d') as approve_date,
                        re.estatus
                        {$cfields}
                 from {$batch_tbl} ba
                 left join {$report_tbl} re on  ba.assen = re.patient_id
                 ";

            $childq .= $cjoin;

            $childq .= " where 1=1  {$where_ba} ";

            $sql_child[] = $childq;
        }
    }
}


$sql_child = implode(" UNION ALL ", $sql_child);


$sql_total   = "select count(distinct(DATE_FORMAT(approve_date,'%Y-%m-%d'))) as c 
    from ({$sql_child}) ba
    where 1=1 {$where} {$where_status}";

if (class_exists('Tr_Cache_Query')) {
    $cache_time = 86400;
    $cache = new Tr_Cache_Query();
    $total = $cache->get_var($sql_total, 0, 0, $cache_time);
} else {
    $total = $wpdb->get_var($sql_total);
}


//page
$row_per_page = 5;
$pagination   = new Tr_Pagination_class2($row_per_page);
$offset       = $pagination->offset();
$time_start   = microtime(true);
$pagination->setTotal($total);
$pagination->item_title = 'day';
$q_total = microtime(true) - $time_start;

$dateby = 'IF(approve_date is not null,approve_date, ba.doc) AS result_date ';
if (isset($settings['servicedate_doc']) && $settings['servicedate_doc']) {
    $dateby = ' ba.doc as result_date ';
}
$sql = "select  {$dateby}
        
    from ({$sql_child}) ba
    where 1=1 {$where} {$where_status} 
    group by result_date  {$orderby}  
    limit {$offset},{$row_per_page} ";

if (isset($_GET['debug'])) {
    var_dump($sql);
}
$time_start = microtime(true);
$results    = $wpdb->get_results($sql);
$count_rows = count($results);
$q_results  = microtime(true) - $time_start;

$clinics = array();
if ($settings['enable_filter_clinic']) {
    $clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic 
		                                    from ci_clinic cli
                                            where cli.cli_status = 1
                                            group by cli.cID
                                            order by cli_clinic asc");
}

$labs = $wpdb->get_results("select lab_ID, lab_name, lab_dba_init, lab_is_ref from ci_labs");



$total_columns = 11;

$filter_btypes = array();
foreach (trbilling_req_types() as $bty) {

    if ($bty == 'study') continue;

    if (
        (isset($show_menus_options['all']['form_' . $bty]) && $show_menus_options['all']['form_' . $bty])
        || (isset($ci_active_menu['form_' . $bty]) && $ci_active_menu['form_' . $bty])
        || in_array($current_role, array('Billing', 'BillbyClinic', 'AdminCIDW'))
    ) {
        $filter_btypes[$bty] = tr_pdd_get_typename($bty);
    }
}
/*
$sql_count = "select 
        count(*) AS cc ,
        sum(if(bill_status='Billed',1,0)) as count_billed,
        sum(if(bill_status='Need Info',1,0)) as count_need_info,
        sum(if(bill_status='Need Billing' or bill_status='',1,0)) as count_need_bill
    from ({$sql_child}) ba
    where 1=1 {$where}   ";


$time_start = microtime( true );
$count      = $wpdb->get_row( $sql_count );
$q_count    = microtime( true ) - $time_start;
*/
if (isset($_REQUEST['debug'])) {
    echo 'qtotal: ' . $q_total, '<br>';
    echo '$sql_total: ' . $sql_total, '<br><br><br>';
    echo '$q_results: ' . $q_results, "<br>";
    echo '$sql: ' . $sql, "<br><br>";
    echo '$q_count: ' . $q_count, '<br><br><br><br>';
    echo '$sql_count: ' . $sql_count, '<br><br>';
}

?>
<div class="wrap">
    <h2>Specimen Billing
        <?php if ($user->user_login == 'administrat0r') { ?>
            <a href="?tr_action=billing_export_patients" class="add-new-h2">Export</a>
        <?php } ?>
    </h2>
    <?php Tr_Session_Class::show() ?>
    <form method="get" id="search_form">
        <input type="hidden" name="page" value="<?php echo isset($_GET['page']) ? $_GET['page'] : '' ?>" />
        <input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />

        <select name="bill_status" class="fixsline">
            <?php
            foreach ($bill_statuses as $k => $vl) {
                $selected = $k == $bill_status ? 'selected' : '';
                echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
            }
            ?>
        </select>
        <select name="rep" class="fixsline">
            <option value="">Select Rep</option>
            <?php
            $reps = $wpdb->get_results("select * from ci_lab_reps order BY rep_first ASC ");
            foreach ($reps as $r) {
                $selected = $r->rep_ID == $rep ? 'selected' : '';
                echo '<option value="' . $r->rep_ID . '" ' . $selected . '>' . $r->rep_first . ' ' . $r->rep_last . '</option>';
            }
            ?>
        </select>
        <?php if (count($labs) > 1 && $user_ref_lab_id == 0) { ?>
            <select name="lab_reflab" class="fixsline">
                <option value="">Select Lab</option>
                <?php
                $include_ref_arr = !empty($settings['include_ref_labs']) ? explode(',', $settings['include_ref_labs']) : array();
                $exclude_ref_arr = !empty($settings['exclude_ref_labs']) ? explode(',', $settings['exclude_ref_labs']) : array();
                foreach ($labs as $lab) {
                    if ($lab->lab_is_ref == 1) continue;

                    $selected = isset($_REQUEST['lab_reflab']) && $_REQUEST['lab_reflab'] == $lab->lab_ID ? 'selected' : '';
                    if (count($exclude_ref_arr) > 0 && in_array($lab->lab_ID, $exclude_ref_arr)) {
                        //no thing now
                    } else {
                        echo '<option value="' . $lab->lab_ID . '" ' . $selected . '>' . $lab->lab_name . '</option>';
                    }

                    if (!$settings['exclude_ref_lab']) {
                        foreach ($labs as $ref) {
                            $labkey = $lab->lab_ID . '_' . $ref->lab_ID;

                            if (!$ref->lab_is_ref || $ref->lab_ID == $lab->lab_ID) continue;
                            if (count($exclude_ref_arr) > 0 && in_array($labkey, $exclude_ref_arr)) {
                                continue;
                            }
                            if (count($include_ref_arr) > 0 && !in_array($ref->lab_ID, $include_ref_arr)) {
                                continue;
                            }
                            $labkey = $lab->lab_ID . '_' . $ref->lab_ID;
                            $selected = isset($_REQUEST['lab_reflab']) && $_REQUEST['lab_reflab'] == $labkey ? 'selected' : '';
                            echo '<option value="' . $labkey . '" ' . $selected . '>' . $lab->lab_name . ' + Ref: ' . $ref->lab_dba_init . '</option>';
                        }
                    }
                }
                ?>
            </select>
        <?php } ?>
        <?php
        if ($settings['enable_filter_clinic'] && isset($clinics)) {
        ?>
            <select name="cli_ID" class="select2" data-placeholder="Select Clinic">
                <option value="">All Clinic</option>
                <?php
                foreach ($clinics as $cli) {
                    $selected = $cli->cID == $cli_ID ? 'selected' : '';
                    echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
                }
                ?>
            </select>
        <?php
        }
        ?>
        <?php
        if (count($filter_btypes) > 1) {
        ?>
            <select name="btype" class="fixsline">
                <?php
                echo '<option value="">Req Type</option>';
                foreach ($filter_btypes as $bty => $txt) {

                    $selected = $bty == $btype_selected ? 'selected' : '';
                    echo '<option value="' . $bty . '" ' . $selected . '>' . $txt . '</option>';
                }
                ?>
            </select>
        <?php
        }
        if ($settings['enable_pay_type']) {
        ?>
            <select name="paytype" class="fixsline">
                <?php
                echo '<option value="">Pay Type</option>';
                foreach ($settings['paytypes'] as $k => $txt) {
                    $selected = $k == $paytype ? 'selected' : '';
                    echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
                }
                echo '<option value="no">No Insurance</option>';
                ?>
            </select>
        <?php
        }
        ?>




        <br>
        <div style="padding-top:10px">
            <input type="text" name="s" value="<?php echo stripslashes(isset($_REQUEST['s']) ? $_REQUEST['s'] : '') ?>" placeholder="Patient Name/Accession" />

            <input type="text" name="insurance" value="<?php echo stripslashes(isset($_REQUEST['insurance']) ? $_REQUEST['insurance'] : '') ?>" placeholder="Insurance" />
            <input type="text" name="claim_number" value="<?php echo stripslashes(isset($_REQUEST['claim_number']) ? $_REQUEST['claim_number'] : '') ?>" placeholder="Claim number" />

            <input type="submit" class="button-primary" value="Search" />
            <input type="button" class="button exportbtn" value="Export" />
        </div>

    </form>


    <div class="tablenav top"><?php if (isset($pagination))
                                    echo $pagination->pagination() ?></div>


    <table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl" data-count="<?php echo @$count_rows ?>">
        <thead class="sticky">
            <tr>
                <th style="width:20px">#</th>
                <th style="width: 9%;">Service Date</th>
                <th style="width: 8%;">Claim ID</th>
                <th style="width: 9%;">Claim Date</th>
                <th style="width: 4%" class="center">Billed</th>
                <?php
                if ($show_cares_act) {
                    $total_columns++;
                    echo '<th style="width:4%" class="center">Cares Act</th>';
                }
                ?>
                <th style="width: 9%;">Billed Amount</th>
                <th style="width: 9%;">Adjustments</th>
                <th style="width: 6%;">Received</th>
                <th style="width: 10%;">Received Amount</th>
                <th style="width: 9%;">Balance</th>
                <th class="tls"><?php /*
                    <span style="color:<?php echo ( $count->count_need_bill == 0 ) ? 'lightgreen' : 'red' ?>"
                          title="Need Bill"><?php echo intval( $count->count_need_bill ) ?></span>
                <span style="color:white" title="Billed"><?php echo intval( $count->count_billed ) ?></span>
                <span style="color:yellow" title="Need Info"><?php echo intval( $count->count_need_info ) ?></span>*/ ?>
                    Status
                </th>
            </tr>
        </thead>
        <tbody>
            <?php
            $batches = array();
            $type    = 1;
            if (count($results) > 0) {
                foreach ($results as $i => $row) {
            ?>
                    <tr class="parent" data-p="batch_<?php echo $i ?>">
                        <td><i></i></td>
                        <td><?php echo $row->result_date ?></td>
                        <td colspan="<?php echo $total_columns - 3 ?>"></td>
                        <td class="tls"><?php /*
                        <span style="color:<?php echo ( $row->count_need_bill == 0 ) ? 'lightgreen' : 'red' ?>" title="Need Bill"><?php echo $row->count_need_bill ?></span>
                        <span style="color:white" title="Billed"><?php echo $row->count_billed ?></span>
                        <span style="color:yellow" title="Need Info"><?php echo $row->count_need_info ?></span>*/ ?>
                        </td>
                    </tr>
                    <tr class="child p_batch_<?php echo $i ?>" data-batch_id="<?php echo $row->result_date ?>">
                        <td colspan="<?php echo $total_columns ?>" class="nopadding"></td>
                    </tr>
                <?php
                }
            } else {
                ?>
                <tr>
                    <td colspan="<?php echo $total_columns ?>">Results not found.</td>
                </tr>
            <?php
            }


            ?>
        </tbody>
    </table>
    <div class="tablenav bottom"><?php if (isset($pagination))
                                        echo $pagination->pagination() ?></div>
</div>



<iframe id="frame_print" src="about:blank" style="width:900px;height:10px;position: absolute;left:-9999px"></iframe>
<style>
    .tls span {
        width: 27px
    }

    table tr td.editable {
        position: relative;
    }

    table tr td.editable:after {
        border-bottom: 2px solid #ddd;
        content: "";
        position: absolute;
        width: 90%;
        bottom: 5px;
        left: 0;
    }

    .editable input {
        width: 100px;
    }

    .tls span {
        width: auto;
        min-width: 40px;
        white-space: nowrap;
    }

    body table.report_tbl tr td.bg_green {
        background: green !important;
    }

    body table.report_tbl tr td.bg_blue {
        background: #2b2be8 !important;
    }

    body table.report_tbl tr td.bg_lightblue {
        background: lightblue !important;
    }

    body table.report_tbl tr td.bg_48d {
        background: #48d1cc;
    }

    body table.report_tbl tr td.bg_red {
        background: red !important;
    }

    .report_tbl tr.child span.notchecked {
        display: block;
        width: 26px;
        height: 26px;
        padding: 0;
        margin: 2px auto;
        border: 1px solid #999;
    }

    <?php
    foreach ($btype_colors as $bt => $bg_color) {
        $color = function_exists('trpdd_getContrastColor') ? trpdd_getContrastColor($bg_color) : '#fff';
        echo '.report_tbl tr.btype_' . $bt . '{background:' . $bg_color . ' !important}' . "\n";
        echo '.report_tbl tr.btype_' . $bt . ' td, .report_tbl tr.btype_' . $bt . ' th{color:' . $color . '}' . "\n";
    }
    ?>
</style>


<script>
    jQuery(function($) {
        var $bill_statuses = <?php echo json_encode($bill_statuses) ?>;

        function trcpp_load_results($batch_id) {
            var $obj = $('tr[data-batch_id="' + $batch_id + '"] td');
            if ($obj.html() != '') {
                return;
            } else {
                $obj.html('<div class="loading center"></div>');
            }
            $.ajax({
                data: {
                    tr_action: 'ci_load_specimen_billings',
                    batch_id: $batch_id
                },
                type: 'post',
                success: function(rs) {
                    var $html = $(rs);
                    $obj.html($html);
                    $('.cid', $html).each(function() {
                        $(this).prepend('<input type="checkbox" value="' + $(this).html() + '"/>');
                    });
                    $('.bill_status').each(function() {
                        var $html = '<select data-id="' + $(this).parent().data('id') + '" data-btype="' + $(this).parent().data('btype') + '">';
                        for (k in $bill_statuses) {
                            $html += '<option value="' + k + '" ' + (k == $(this).data('status') ? 'selected' : '') + '>' + $bill_statuses[k] + '</option>';
                        }
                        $html += '</select>';
                        $(this).html($html);
                    });
                    $('.bill_status select').change(function() {
                        var $pr = $(this).parent();
                        var $vl = $(this).val();
                        var $id = $(this).data('id');
                        var $btype = $(this).data('btype');
                        $pr.append('<span class="sloading"></span>');
                        $.post(ajaxurl, {
                            tr_action: 'ci_change_batch_bill_status',
                            action: 'ajax',
                            id: $id,
                            btype: $btype,
                            status: $(this).val()
                        }, function(rs) {
                            if ($vl == 'Need Info') {
                                var $url = '?tr_action=ci_patient_billing_notes&mid=' + $id;
                                tb_show("Billing Notes", $url);
                            } else {
                                alert(rs);
                            }
                            $('.sloading', $pr).remove();
                        })
                    });
                    $('td.editable').attr('title', 'Click to edit');
                    $('tr.child_c').show();
                    $('body,html').scrollTop($obj.offset().top - 110);
                }
            })
        }

        $('body').on('click', 'td.editable', function() {
                if ($(this).hasClass('editing')) {
                    return false;
                }

                $(this).data('vl', $(this).html()).addClass('editing');
                $(this).html('<input type="text" value="' + $(this).html() + '" title="Press Enter to save"/>');

                if ($(this).hasClass('date')) {
                    $('input', $(this)).datepicker({
                        showButtonPanel: true,
                        dateFormat: 'yy-mm-dd',
                        changeMonth: true,
                        changeYear: true
                    });
                }
                $('input', $(this)).focus();
                return false;
            })
            .on('keypress', 'td.editable input', function(e) {
                var code = e.keyCode || e.which;
                if (code == 13) {
                    //save data
                    var $pr = $(this).parents('td.editable');
                    var $value = $(this).val();
                    var $field = $pr.data('r');
                    var $btype = $pr.parent().data('btype');
                    console.log($btype);
                    var $id = $pr.parent().data('id');
                    $pr.append('<div class="sloading"></div>');
                    $.ajax({
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            tr_action: 'ci_save_billing_data',
                            mid: $id,
                            value: $value,
                            field: $field,
                            btype: $btype
                        },
                        success: function(rs) {
                            if (rs.status == 'ok') {
                                $pr.html($value);
                                $pr.removeClass('editing');
                                for (i in rs.changes) {
                                    $('td.' + i, $pr.parent()).html(rs.changes[i]);
                                }
                            } else {
                                alert('Error Please try again');
                            }

                        }
                    })
                }
            })
            .on('click', 'a.send_billing', function() {
                $mid = $(this).data('id');
                tb_show('Send email', '?tr_action=ci_send_rep_billing&mid=' + $mid + '&width=400&height=150');
                return false;
            })
            .on('click', 'input.pecos_checkbox', function() {
                $.ajax({
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        tr_action: 'ci_save_billing_pecos',
                        doc_id: $(this).val(),
                        checked: $(this).is(':checked') ? 1 : 0
                    },
                    success: function(rs) {

                    }
                })
            })
            .on('click', 'tr.parent', function(e) {
                if (e.target.tagName != 'TD' && e.target.tagName != 'TR' && e.target.tagName != 'I') return;
                $('tr.child').hide();
                if ($(this).data('showed') != '1') {
                    var $obj = $('tr.p_' + $(this).data('p'));
                    $obj.show();
                    $('tr.parent').data('showed', '0');
                    $(this).data('showed', '1');
                    $(this).addClass('active');
                    setCookie('active_billing', $(this).data('p'));
                    $batch_id = $obj.data('batch_id');
                    trcpp_load_results($batch_id);
                } else {
                    $('tr.p_' + $(this).data('p')).hide();
                    $(this).data('showed', '0');
                    $(this).removeClass('active');
                }
                return false;
            })
            .on('click', 'tr.parent_c', function(e) {
                if (e.target.tagName != 'TD' && e.target.tagName != 'TR' && e.target.tagName != 'I') return;
                $('tr.child_c').hide();
                if ($(this).data('showed') != '1') {
                    var $obj = $('tr.p_childc_' + $(this).data('id'));
                    $obj.show();
                    $('tr.parent_c').data('showed', '0');
                    $(this).data('showed', '1');
                    $(this).addClass('active');
                    setCookie('active_billing', $(this).data('p'));
                    $batch_id = $obj.data('id');
                    //trcpp_load_results($batch_id);
                } else {
                    $('tr.p_childc_' + $(this).data('id')).hide();
                    $(this).data('showed', '0');
                    $(this).removeClass('active');
                }
                return false;
            })
            .on('hover', 'tr.parent_c', function(e) {
                if (e.target.tagName != 'TD' && e.target.tagName != 'TR' && e.target.tagName != 'I') return;
                if ($(this).data('showed') != '1' && !$(this).data('tthover')) {
                    var $this = $(this);
                    var $obj = $('tr.p_childc_' + $this.data('id'));
                    $this.data('tthover', setTimeout(function() {
                        $obj.show();
                        $this.data('showed', '1');
                        $this.addClass('active');
                    }, 1000));
                }
                return false;
            })
            .on('mouseout', 'tr.parent_c', function() {
                console.log($(this).data('tthover'));
                clearTimeout($(this).data('tthover'));
                $(this).data('tthover', false);
            })
            .on('click', '.exportbtn', function() {
                location.href = 'admin.php?' + $('#search_form').serialize() + '&trajax=1&act=export';
                return false;
            })
            .on('click', 'a.print_pdf', function() {

                var $this = $(this);
                var $url = '<?php echo admin_url('/') ?>' + $(this).data('href');
                $('#frame_print').attr('src', $url);

                $.show_popup("<div class='center printpdfinfo'><div class='loading'></div><div class='buildinginfo'>Building PDF<div></div>", '', 20000);

                //window.open($url,"print","menubar=0,width=100,height=100");
                return false;
            })
            .on('printed', function() {
                $.hide_popup();
            })

        var $old_active = getCookie('active_billing');
        if ($old_active && $old_active != null) {
            var $obj = $('tr.p_' + $old_active);
            try {
                $obj.show();
                $('[data-p="' + $old_active + '"]').data('showed', '1');
                if ($old_active.indexOf('lab_') > -1) {
                    $obj.parents('.child').show();
                }

                var $batch_id = $obj.data('batch_id');
                trcpp_load_results($batch_id);
            } catch (e) {}

        }

    })
</script>