<?php
global $wpdb, $current_role;
$date_from = isset($_REQUEST['date_from']) ? $_REQUEST['date_from'] : '';
$date_to   = isset($_REQUEST['date_to']) ? $_REQUEST['date_to'] : '';
$lab_id = isset($_REQUEST['lab_id']) ? $_REQUEST['lab_id'] : 0;

$by         = isset($_REQUEST['by']) ? $_REQUEST['by'] : 'ins';
if (empty($date_from)) $date_from = date_i18n('Y-m-d');
if (empty($date_to)) $date_to = date_i18n('Y-m-d');


if (!tr_current_role_in(array('lab', 'AdminCIDW', 'administrator', 'administrator_p'))) {
    //find rep in group
    $users_reps = tr_dpp_get_users_in_group(get_current_user_id());
    foreach ($users_reps as $uid) {
        $rid = get_user_meta($uid, '_rep_id', true);
        if ($rid > 0) {
            $rep_ids[] = $rid;
        }
    }
    if ($current_role == 'distributor') {
        $rep_ids2 = $wpdb->get_col("select rep.rep_ID from ci_lab_reps AS rep
                                    JOIN ci_distributors dis ON dis.dis_ID = rep.rep_distributor_ID
                                    WHERE dis.dis_user_ID = {$user_id}");
        if (count($rep_ids2) > 0) {
            $rep_ids = array_merge($rep_ids, $rep_ids2);
        }
    }
}

$where = '';
if ($lab_id > 0) {
    //$where .= " and LB.lab_ID ='{$lab_id}' ";
    $where .= " and (LB.lab_ID ='{$lab_id}' or ba.lab_id = {$lab_id} or ba.ref_lab_ID = {$lab_id} )";
}
//$where .= " and ba.doc >= '{$date_from}' and ba.doc <= '{$date_to}' ";


$where .= " and ba.spec_received >= '{$date_from}' and ba.spec_received <= '{$date_to}' ";



//where by user role
if (count($rep_ids) > 0) {
    $rep_ids = implode(',', $rep_ids);
    $where .= " and CLI.`cli_rep-ID` in ({$rep_ids}) ";
} else {
    switch ($current_role) {
        case 'lab_rep':
        case 'lab_rep_w2':
            $rep_id = get_user_meta(get_current_user_id(), '_rep_id', true);
            $where .= " and CLI.`cli_rep-ID` = {$rep_id} ";
            break;
    }
}

if (isset($_REQUEST['debug'])) {
    var_dump($rep_ids);
    echo '<br>';
    echo $where;
}

$select_fields = "ba.mid,
                    ba.doc,
                    ba.assen,
                    ba.insurance as pinsname,
                    ba.requesting_practice,
                    ba.requesting_physician,
                    bb.received_amount AS collected,
                    bb.claim_date AS billed_date,
                    bb.date AS collected_date,
                    LR.rep_comission,
                    CLI.cli_clinic,
                    ba.patient_name,
                    ba.batch_status,
                    docs.doc_first,
                    docs.doc_last,
                    ba.dob,
                    LR.rep_first,
                    LR.rep_last,
                    if(pl.payer_commercial='1','C',
                    if(pl.payer_medicaid='1','A',
                    if(pl.payer_medicare='1','M',
                    if(pl.payer_workmen='1','W',
                    if(pl.payer_other='1','O',''))))) as ins_mix
                    ";

$joins = "  left JOIN ci_batch_billings bb ON (bb.claim_no!='' and bb.claim_no is not null and bb.claim_no = ba.claim_id)
                                                        or (bb.assen = ba.assen)
                left JOIN `ci_labs` LB ON ba.lab =  LB.lab_dba_init or ba.lab_id = LB.lab_ID or ba.ref_lab_ID = LB.lab_ID
                left join `ci_clinic` as CLI ON CLI.cID = ba.cli_ID
                left join `ci_doc` as docs ON docs.cli_ID = CLI.cID
                left JOIN `ci_lab_reps` as LR ON LR.rep_ID = CLI.`cli_rep-ID`
                left JOIN ci_payer_list pl ON pl.payer_name = ba.insurance
                ";


$select_fields .= ",concat(LR.rep_last,', ',LR.rep_first) as rep,ba.billing_notes";
$where .= " and (bill_status='Need Info')";

$order_by = "rep,cli_clinic ASC ";

$headers = array(
    'Rep',
    'Date',
    'Accession',
    'Patient Name',
    'DOB',
    'Clinic',
    'Doctor',
    'Notes',
);



$query = " select {$select_fields} from ci_batches ba {$joins}
                where 1 {$where} group by ba.mid order by {$order_by}";
$file = tempnam("/tmp", "export");
$f = fopen($file, 'w');
$count = 0;
$rows = $wpdb->get_results($query);



fputcsv($f, $headers);

foreach ($rows as $r) {


    if (empty($r->collected)) {
        $r->collected_date = '';
    }


    $row = array(
        $r->rep,
        date('Y-m-d', strtotime($r->doc)),
        $r->assen,
        $r->patient_name,
        $r->dob,
        $r->cli_clinic,
        $r->doc_first . ' ' . $r->doc_last,
        $r->billing_notes,
    );


    fputcsv($f, $row);
    $count++;
}


if ($count == 0) {
    Tr_Session_Class::add_error('Not found!');
    return false;
}

$filename = 'sale_Report_' . $date_from . '_' . $date_to . '.csv';


header("Pragma: public");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: private", false);
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=\"$filename\";");
header("Content-Transfer-Encoding: binary");

readfile($file);
@unlink($file);
exit;
