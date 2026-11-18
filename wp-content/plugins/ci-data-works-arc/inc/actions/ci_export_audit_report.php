<?php
global $wpdb, $current_role, $blog_id;

if (!isset($_REQUEST['export'])) return;

$date_from  = isset($_REQUEST['date_from']) ? $_REQUEST['date_from'] : '';
$date_to    = isset($_REQUEST['date_to']) ? $_REQUEST['date_to'] : '';
$lab_id     = isset($_REQUEST['lab_id']) ? $_REQUEST['lab_id'] : 0;

$sort       = isset($_REQUEST['sort']) && $_REQUEST['sort'] == 'desc' ? 'desc' : 'asc';
$sortby     = isset($_REQUEST['sortby']) ? $_REQUEST['sortby'] : 'doc';

if (empty($date_from)) $date_from = date_i18n('Y-m-d');
if (empty($date_to)) $date_to = date_i18n('Y-m-d');
$count = 0;


$where = " and ba.doc >= '{$date_from}' and ba.doc <= '{$date_to}' ";

if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $where .= " and ba.lab_id in ( {$lab_str} ) ";
}


$file = tempnam("/tmp", "export");
$f = fopen($file, 'w');

$headers = array(
    'Batch#',
    'Accession',
    'D.O.C',
    'ID#',
    'Patient ID',
    'Patient Name',
    'dname',
    'Distributor',
    'Rep',
    'ref_idno',
    'refname',
    'pinsname',
    'sinsname',
    'approved',
    'collected',
    'adjusted',
    'patpay',
    'patadj',
    'insadj',
    'patpayd',
    'insbal',
    'cashbal'
);

fputcsv($f, $headers);

$child_q = array();
foreach (tr_pdd_get_batch_types() as $btype) {
    $batch_tbl = tr_pdd_get_table_by_btype($btype);
    $child_q[] = "(
            SELECT
                ba.batch_id,
                ba.assen,
                ba.doc,
                ba.patient_id,
                ba.patient_name,
                ba.requesting_physician AS dname,
                DIS.dis_name AS distributor,
                concat(LR.rep_first,' ',LR.rep_last) as rep,
                0 as ref_idno,
                '' as refname,
                ba.insurance AS pinsname,
                '' as sinsname,
                0 as approved,
                bb.received_amount as collected,
                0 as adjusted,
                0 as patpay,
                0 as patadj,
                0 as insadj,
                '' as patpayd,
                0 as insbal,
                0 as cashbal

                from {$batch_tbl} ba
                left JOIN ci_batch_billings bb ON (bb.claim_no!='' and bb.claim_no is not null and bb.claim_no = ba.claim_id)
                                                        or (bb.assen = ba.assen)
                left join `ci_clinic` as CLI ON CLI.cID = ba.cli_ID
                left JOIN `ci_lab_reps` as LR ON LR.rep_ID = CLI.`cli_rep-ID` 
                left JOIN ci_distributors DIS On DIS.dis_ID = CLI.cli_distributor_ID
                where  ba.batch_id !='' and ba.batch_id!='0' 
                {$where} 
                group by ba.mid 
                )";
}

$child_q = implode(' UNION ALL ', $child_q);
$sql = " select * from ({$child_q}) ba
                order by {$sortby} {$sort}";

$rows = $wpdb->get_results($sql);

if (isset($_GET['debug'])) {
    echo $sql;
    echo $wpdb->last_error;
}

foreach ($rows as $r) {
    $r = array_values(get_object_vars($r));
    fputcsv($f, $r);
    $count++;
}


if ($count == 0) {
    Tr_Session_Class::add_error('Not found!');
    return false;
}

$filename = 'Audit_Report_' . $date_from . '_' . $date_to . '.csv';


header("Pragma: public");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: private", false);
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=\"$filename\";");
header("Content-Transfer-Encoding: binary");

readfile($file);
@unlink($file);
$data_log = [
    'type' => 'Audit Report',
    'file_name' => $filename,
    'total_records' => $count,
    'from_date' => $date_from,
    'to_date' => $date_to,
];
trpdd_log_user(get_current_user_id(), 'download_report', $data_log, '', '', '', '', 'Report_' . date_i18n('mdY'));


exit;
