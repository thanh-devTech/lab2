<?php
global $wpdb, $current_lab_id, $blog_id, $current_role, $ci_active_menu, $show_menus_options;


$result_dir = '';
if (function_exists('tr_get_result_upload_dir')) {
    $result_dir = tr_get_result_upload_dir();
}


$settings   = tr_billing_get_settings();
$result_dir = false;

$user_id    = get_current_user_id();
$date       = isset($_REQUEST['batch_id']) ? $_REQUEST['batch_id'] : '';
$bill_status = isset($_REQUEST['bill_status']) ? $_REQUEST['bill_status'] : '';
$paytype    = isset($_REQUEST['paytype']) ? $_REQUEST['paytype'] : '';
$cli_ID     = isset($_REQUEST['cli_ID']) ? $_REQUEST['cli_ID'] : 0;
$btype_selected         = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : '';

$options    = get_option('tr_billing_ind_st', array());
$show_cares_act = defined('TRPDD_INS_CARES_ACT') && TRPDD_INS_CARES_ACT;

if (!tr_get_option('ci_pdf_data', 'req_cares_act') && !tr_get_option('ci_pdf_data', 'req_blood_cares_act')) {
    $show_cares_act = false;
}

$where = "";


if (defined('TRPDD_UPSAVED') && TRPDD_UPSAVED) {
    $where .= " and ba.is_saved = 0 ";
}


$where .= " and " . tr_pdd_where_batch_rejected(false, true, '', array('cancelled')) . ' ';

if (!empty($_REQUEST['s'])) {
    $search = stripslashes($_REQUEST['s']);
    $where .= $wpdb->prepare(" and (ba.patient_name like %s or ba.assen=%s)", '%' . $search . '%', $search);
}
if (!empty($_REQUEST['insurance'])) {
    $ins = $_REQUEST['insurance'];
    $where .= $wpdb->prepare(" and ba.insurance like %s ", $ins . '%');
}

$ormorepaytype = '';
if (!empty($paytype)) {
    if (isset($settings['paytypebynoinscash']) && $settings['paytypebynoinscash']) {
        if ($paytype == 'cash') {
            $ormorepaytype .= ' or ba.paid > 0 ';
        } else if ($paytype == 'insu') {
            $ormorepaytype .= ' or ba.paid = 0 ';
        }
    }

    if ($paytype !== 'no' && $paytype !== 'clientb') {
        $where_cli .= " and (cli.cli_pay_type = '{$paytype}' {$ormorepaytype} ) ";
    }

    if ($paytype == 'clientb') {
        $where .= " AND (cli.cli_pay_type = '{$paytype}' OR ba.insurance = 'Client Bill') ";
    } else if ($paytype == 'no') {
        $where .= " AND (
			cli.cli_pay_type = '{$paytype}' 
			OR (ba.insurance = 'No Insurance - Cash' OR ba.insurance = '')
		) ";
    } else if ($paytype == 'cash') {
        $where .= " AND (
			cli.cli_pay_type = '{$paytype}'
			OR ba.insurance = 'No Insurance - Cash'
		) ";
    } else if ($paytype == 'insu') {
        $where .= " AND (
			cli.cli_pay_type = '{$paytype}' 
			OR (ba.insurance != 'No Insurance - Cash' OR ba.insurance != '')
		) ";
    }
}

if (!empty($_REQUEST['claim_number'])) {
    $claim_number = $_REQUEST['claim_number'];
    $where .= $wpdb->prepare(" and ba.claim_id like %s ", '%' . $claim_number . '%');
}

$orderby = ' order by re.approve_date desc, ba.mid desc';



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

if ($current_lab_id > 0 && $blog_id > 1) {
    $where .= " and ba.lab_id = {$current_lab_id} ";
} else if ($current_lab_id > 0 && isset($settings['exclude_ref_lab']) && $settings['exclude_ref_lab']) {
    $where .= " and (ba.ref_lab_id = 0 or ba.ref_lab_id = {$current_lab_id} ) ";
} else {

    if (isset($settings['include_ref_labs']) && !empty($settings['include_ref_labs'])) {
        $where .= " and (ba.ref_lab_id = 0 or ba.ref_lab_id in ( {$settings['include_ref_labs']} ) ) ";
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

$user_ref_lab_id = get_user_meta($user_id, '_ref_lab_id', true);
if ($user_ref_lab_id > 0) {
    $where .= " and ba.ref_lab_id = {$user_ref_lab_id}  ";
}


if (!empty($bill_status)) {
    $where .= " and bb.bill_status = '{$bill_status}' ";
}



if ($cli_ID > 0) {
    $where .= " and ( ba.cli_ID = {$cli_ID} or ba.ref_cli_ID = {$cli_ID}) ";
}


if (!empty($_REQUEST['lab_reflab'])) {
    list($lab_id, $ref_lab_id) = explode('_', $_REQUEST['lab_reflab']);
    if ($lab_id > 0) {
        $where .= " and ba.lab_ID = {$lab_id} ";
    }

    if ($ref_lab_id > 0) {
        $where .= " and ba.ref_lab_ID = {$ref_lab_id} ";
    } else {
        $where .= " and ba.ref_lab_ID = 0 ";
    }
}


if (isset($settings['servicedate_doc']) && $settings['servicedate_doc']) {
    $where .= " and ( ba.doc >= '{$date}' and ba.doc <= '{$date} 23:59:59') ";
} else {
    $where .= " and ((re.id > 0  and re.approve_date >= '{$date}' and re.approve_date <= '{$date} 23:59:59') 
                or (re.id is null and ba.result_pdf!='' and ba.result_pdf is not null and  ba.batch_created >= '{$date}' and ba.batch_created <= '{$date} 23:59:59' ))";
}


$where .= " and re.estatus in ('C','F') ";


$more_fields = '';
$more_joins = '';
if ($show_cares_act) {
    $more_fields .= ',ins.ins_cares_act ';
    $more_joins .= ' left join ci_insurances ins ON ba.insurance1 = ins.ins_ID ';
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

        if ($t == 'study') continue;

        if (!empty($btype_selected) && $t != $btype_selected) {
            continue;
        }

        if (
            (isset($show_menus_options['all']['form_' . $t]) && $show_menus_options['all']['form_' . $t])
            || (isset($ci_active_menu['form_' . $t]) && $ci_active_menu['form_' . $t])
            || in_array($current_role, array('Billing', 'BillbyClinic', 'AdminCIDW'))
        ) {
            $batch_tbl = tr_pdd_get_table_by_btype($t);
            $report_tbl = tr_pdd_get_table_report_by_btype($t);
            $has_more_type = true;

            $sql_child[] = "(select 
                        ba.mid,
                        ba.lab,
                        ba.assen,
                        ba.patient_name,
                        ba.requesting_practice,
                        ba.requesting_physician,
                        ba.pverify_status,
                        ba.claim_id,
                        ba.result_pdf,
                        ba.insurance,
                        ba.paid,
                        ba.billing_notes,
                        ba.priority_hours,
                        cli.cli_pay_type,
                        re.id,
                        'C' as estatus,
                        
                        bb.id as bill_id,
                        bb.claim_no,
                        bb.claim_date,
                        bb.allowed_amount,
                        bb.bill_status,
                        bb.received_amount,
                        bb.billed_amount,
                        
                        doc.doc_ID,
                        doc.doc_npi,
                        doc.doc_pecos,
                        rep.rep_last,
                        rep.rep_first,
                        '{$t}' as btype
                        {$more_fields}
                
                    from {$batch_tbl} ba
                    left join {$report_tbl} re ON  ba.assen = re.patient_id  
                    left join ci_batch_billings bb ON {$where_join}
                    left join ci_doc doc ON doc.doc_ID = ba.doc_ID
                    left join ci_clinic cli ON cli.cID = ba.cli_ID
                    left join ci_lab_reps rep ON rep.rep_ID = cli.`cli_rep-ID`
                    {$more_joins}
                    where 1=1 {$where} group by ba.mid order by ba.mid desc)";
        }
    }
}


$sql_child = implode("\n UNION ALL \n", $sql_child);

$sql = "select * from ( {$sql_child} ) as rs  ";

$time_start  = microtime(true);
$results = $wpdb->get_results($sql);
$count_rows = count($results);

if (isset($_REQUEST['debug'])) {
    echo "\n\n<br><br>\n";
    echo microtime(true) - $time_start;
    echo "\n\n<br><br>\n";
    echo $sql;
    echo "\n\n<br><br>\n";
    var_dump($wpdb->last_error);
    echo "\n\n<br><br>\n";
}

echo '<table class="report_tbl" data-rows="' . $count_rows . '">';
$oral_content = '';
$all_type = array();
foreach ($results as $i => $row) {

    $update = array();
    $row->received_amount = (float) preg_replace("/[^0-9\.\,]/", '', $row->received_amount);
    $row->billed_amount   = (float) preg_replace("/[^0-9\.\,]/", '', $row->billed_amount);

    $row->billed_amount = floatval($row->billed_amount);
    $row->received_amount = floatval($row->received_amount);
    $row->allowed_amount = floatval($row->allowed_amount);

    $row->balance = $row->billed_amount - $row->received_amount - $row->allowed_amount;


    if ($options['need_bill_status_paid'] && empty($row->bill_status)) {
        $row->bill_status = 'Need Billing';
        $update['bill_status'] = $row->bill_status;
    }

    $class_block = $npi = '';
    if (! empty($row->doc_npi)) {
        $npi .= ' (' . $row->doc_npi . ')';
    }



    $is_no_insurance = stripos($row->insurance, 'No Insurance - Cash') !== false;
    $has_insurance = $row->paid == -1 || (stripos($row->billing_notes, 'Billed by Order #') !== false && !$is_no_insurance);
    $class_block .= ' paid_' . $row->paid . ' ';
    if (! $settings['enable_pay_type']) {
        if ($is_no_insurance) {
            $class_block .= ' bg_lightblue';
        }
    } else if ($row->cli_pay_type == 'insu' && $is_no_insurance) {
        $class_block .= ' bg_48d';
    } else if ($row->cli_pay_type == 'insu' || $has_insurance) {
        $class_block .= ' bg_blue';
    } else if ($row->cli_pay_type == 'cash') {
        $class_block .= ' bg_green';
    } else if ($row->cli_pay_type == 'inscash') {
        $class_block .= ' bg_lightblue';
    }

    if (!empty($update)) {
        if (empty($row->bill_id)) {
            $update['assen'] = $row->assen;
            $wpdb->insert('ci_batch_billings', $update);
        } else {
            $wpdb->update('ci_batch_billings', $update, array('id' => $row->bill_id));
        }
    }

    if (!in_array($row->btype, $all_type) && $has_more_type) {
        $all_type[] = $row->btype;
        $type_title = 'Tox';
        if (function_exists('tr_pdd_get_typename')) {
            $type_title = tr_pdd_get_typename($row->btype);
        }

?>
        <tr class="btype_<?php echo $row->btype ?>">
            <th colspan="3"><strong style="font-size:15px">Priority - <?php echo $type_title ?></strong></th>
            <th colspan="2">Clinic</th>
            <th colspan="<?php echo $show_cares_act ? 4 : 3 ?>" style="text-align: center">Doctor</th>
            <th style="text-align: center">Pecos</th>
            <th colspan="2">Rep</th>
        </tr>
    <?php
    }


    ?>
    <tr class="parent_c" data-id="<?php echo $row->mid ?>" data-btype="<?php echo $row->btype ?>">
        <td style="width: 20px;" class="<?php echo $class_block ?>" title="<?php echo $row->mid ?>"><i></i></td>
        <td style="width: 9%"><?php echo $row->assen ?></td>
        <td style="width: 8%;" class="editable" data-r="claim_no"><?php echo $row->claim_no ?></td>
        <td style="width: 9%;" class="editable date" data-r="claim_date"><?php echo $row->claim_date ?></td>
        <td style="width: 4%" class="billed center"><?php echo ($row->billed_amount > 0) ? '<span class="checkmark checked"></span>' : '<span class="notchecked"></span>' ?></td>
        <?php
        if ($show_cares_act) {
            echo '<td style="width:4%" class="center">' . ($row->ins_cares_act > 0 ? '<span class="checkmark checked"></span>' : '<span class="notchecked"></span>') . '</td>';
        }
        ?>
        <td style="width: 9%;" class="editable" data-r="billed_amount"><?php if ($row->billed_amount > 0) echo number_format($row->billed_amount, 2) ?></td>
        <td style="width: 9%;" class="editable" data-r="allowed_amount"><?php if ($row->allowed_amount > 0) echo number_format($row->allowed_amount, 2) ?></td>
        <td style="width: 6%" class="received center"><?php echo ($row->received_amount > 0) ? '<span class="checkmark checked"></span>' : '<span class="notchecked"></span>' ?></td>
        <td style="width: 10%;" class="editable" data-r="received_amount"><?php if ($row->received_amount > 0) echo number_format($row->received_amount, 2) ?></td>
        <td style="width: 9%;" class="balance"><?php if ($row->balance != 0) echo number_format($row->balance, 2) ?></td>
        <td class="bill_status" data-status="<?php echo $row->bill_status ?>"></td>

    </tr>
    <tr class="child_c p_childc_<?php echo $row->mid ?>" data-id="<?php echo $row->mid ?>">
        <td class="nopadding" colspan="<?php echo ($show_cares_act) ? 12 : 11 ?>">
            <table class="report_tbl">
                <tr class="childp">
                    <td style="width:20px"><?php
                                            if ($row->priority_hours > 0) {
                                                if (defined('TRPDD_SHOW_PRIORITY_NAME') && TRPDD_SHOW_PRIORITY_NAME) {
                                                    $arrpriorities = $row->btype == 'pcr' ? TRPDD_PRIORITIES_PCR : TRPDD_PRIORITIES;
                                                    $priority_name = isset($arrpriorities[$row->priority_hours]) ? $arrpriorities[$row->priority_hours] : '';

                                                    echo $priority_name;
                                                } else {
                                                    echo $row->priority_hours . 'hr(s)';
                                                }
                                            }
                                            ?></td>
                    <td style="width: 17%;"><?php echo ucwords(strtolower($row->patient_name)) ?></td>
                    <td style="width: <?php echo $show_cares_act ? 23 : 21 ?>%;"><?php echo $row->requesting_practice ?></td>
                    <td style="width: 21%"><?php echo $row->requesting_physician . $npi ?></td>
                    <td style="width:3%">
                        <input type="checkbox" class="pecos_checkbox" value="<?php echo $row->doc_ID ?>" <?php checked($row->doc_pecos) ?> title="PECOS" />
                    </td>
                    <td style="width:15%"><a class="send_billing" data-id="<?php echo $row->mid ?>"><?php echo $row->rep_first . ' ' . $row->rep_last ?></a></td>
                    <td colspan="2">
                        <a href="admin.php?page=ci_billings&act=edit&id=<?php echo $row->mid ?>&btype=<?php echo $row->btype ?>&date=<?php echo $date ?>">REQ</a> -
                        <a target="_blank" href="?tr_action=ci_get_req_pdf&id=<?php echo $row->mid ?>&btype=<?php echo $row->btype ?>">REQ PDF</a> -
                        <?php if ($row->estatus != 'C' && $row->estatus != 'F'): ?>
                            <a target="_blank" href="admin.php?page=ci_results&tab=list&act=edit&id=<?php echo $row->id ?>">Edit Result</a> -
                        <?php endif; ?>

                        <?php
                        if ($row->id > 0) {
                        ?>
                            <a href="<?php echo '?tr_action=ci_results_pdf&id=' . $row->id ?>&btype=<?php echo $row->btype ?>">Result PDF</a>
                        <?php
                        } else if ($result_dir && !empty($row->result_pdf)) { ?>
                            <a href="<?php echo $result_dir['url'] . '/' . $row->result_pdf ?>" target="_blank">Result PDF</a>
                        <?php } ?>
                        - <a class="thickbox" href="?tr_action=ci_patient_billing_notes&mid=<?php echo $row->mid ?>&btype=<?php echo $row->btype ?>">Notes</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
<?php
}

echo '</table>';
exit;
