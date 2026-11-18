<?php
global $wpdb;


$batch_from = $_REQUEST['batch_from'];
$batch_to   = $_REQUEST['batch_to'];
$report_type = $_REQUEST['report_type'];
if(empty($batch_to) || $batch_to < $batch_from)
{
    $batch_to = $batch_from;
}
$file = tempnam("/tmp", "export");
$f = fopen($file,'w');
$count = 0;
$where = '';

if ($report_type == 'audit')
{
    $filename = 'Audit_Report_batch_'.$batch_from.'_'.$batch_to.'.csv';

    $headers =array(
        '#',
        'Batch#',
        'D.O.C',
        'ID#',
        'Patient ID',
        'Patient Name',
        'dname',
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

    fputcsv($f,$headers);

    $dname = $_REQUEST['dname'];
    $lab = CI_Base_Lab::get_current_lab();
    $dname = $lab->lab_name;

    if(!empty($dname)){

        if(isset($_REQUEST['nolab']))
        {
            $where.= " and ( bi.dname='{$dname}' or bi.dname ='')";
        }else
        {
            $where.= " and bi.dname='{$dname}'";
        }
    }
    for($i = $batch_from;$i<=$batch_to;$i++)
    {
        //echo $batch_from,'-'.$batch_to;
        //fputcsv($f,array('Batch '.$i));
        //fputcsv($f,$headers);
        $sql = " select
                rs.batch_id,
                rs.doc,
                rs.id,
                rs.patient_id,
                rs.patient_name,
                rs.dname,
                rs.ref_idno,
                rs.refname,
                rs.pinsname,
                rs.sinsname,
                sum(rs.approved),
                sum(rs.collected),
                sum(rs.adjusted),
                sum(rs.patpay),
                sum(rs.patadj),
                sum(rs.insadj),
                rs.patpayd,
                sum(rs.insbal),
                sum(rs.cashbal)
                from
                ( SELECT
                    ba.mid,
                    ba.batch_id,
                    ba.doc,
                    bi.patient_id,
                    ba.patient_name,
                    bi.dname,
                    bi.ref_idno,
                    bi.refname,
                    bi.pinsname,
                    bi.sinsname,
                    bi.approved,
                    bi.collected,
                    bi.adjusted,
                    bi.patpay,
                    bi.patadj,
                    bi.insadj,
                    bi.patpayd,
                    bi.insbal,
                    bi.cashbal

                from ci_batches ba
                left JOIN ci_batch_billings bi ON  ba.doc = bi.servdate
                and ( ba.patient_name = bi.patient
                or (
                    SUBSTRING_INDEX(SUBSTRING_INDEX(ba.patient_name, ' ', 2), ' ', -1) = SUBSTRING_INDEX(SUBSTRING_INDEX(bi.patient, ' ', 2), ' ', -1)
                    AND
                    SUBSTRING_INDEX(ba.patient_name, ' ', 1) = SUBSTRING_INDEX(bi.patient, ' ', 1)
                    )
                )
                where ba.batch_id ='{$i}' 
                {$where} 
                order by ba.mid) as rs group by rs.mid";
        //echo $sql;exit;
        $rs = $wpdb->get_results($sql);

        foreach($rs as $r)
        {
            $r = array_values( get_object_vars( $r ) );
            fputcsv($f,$r);
            $count++;
        }

    }
    fclose($f);
}
else if($report_type=='dreamaudit') {

    $filename = 'Dream_Audit_Report_batch_'.$batch_from.'_'.$batch_to.'.csv';

    $headers = array(
        '#',
        'Batch#',
        'Lab' ,
        'Patient Name',
        'D.O.B.',
        'Accession',
        'Requesting_Physician',
        'D.O.C.',
        'Ins(P)',
        'Ins(S)',
        'LL Insurance',
        'PFInCode',
        'Ref',
        'Billed',
        'Billed Amount',
        'Collected',
        'Adjusted',
        'Insurance Balance',
        'Cash Balance'
    );

    fputcsv($f, $headers);

    $dname2 = $_REQUEST['dname2'];
  
    $lab = CI_Base_Lab::get_current_lab();
    $dname2 = $lab->lab_name;

    if (!empty($dname2)) {
        if(isset($_REQUEST['nolab2']))
        {
            $where.= " and ( bi.dname='{$dname2}' or bi.dname ='')";
        }else
        {
            $where.= " and bi.dname='{$dname2}'";
        }
    }
    for ($i = $batch_from; $i <= $batch_to; $i++) {
        //echo $batch_from,'-'.$batch_to;
        //fputcsv($f,array('Batch '.$i));
        //fputcsv($f,$headers);
        $sql = " select
                rs.batch_id,
				IF(ISNULL(rs.lab),rs.dname,rs.lab),
				rs.patient_name,
				rs.dob,
                rs.assen,
                rs.requesting_physician,
                rs.doc, 
				rs.pinsname,
                rs.sinsname,
	            rs.insurance,
	            rs.pfincode,
	            IF(rs.ins_referenced='1','Y','N'),
                IF(sum(rs.billed)>0,'YES','NO'),
                sum(rs.billed),
                sum(rs.collected),
                sum(rs.adjusted),
                
                sum(rs.insbal),
                sum(rs.cashbal)
                from
                ( SELECT
                    ba.mid,
                    ba.batch_id,
					ba.lab,
					ba.patient_name,
					bb.patient_id,
					ba.dob,
                    ba.assen,
					ba.requesting_physician,
                    ba.doc,
                    bb.pinsname,
                    bb.sinsname,
                    ba.insurance,
                    bb.pfincode,
                    inx.ins_referenced,
                    bb.billed,
                    bb.approved,
                    bb.collected,
                    bb.adjusted,
                    
                    bb.insbal,
                    bb.cashbal,
                    bb.dname

                from ci_batches ba
                left JOIN ci_batch_billings bb ON  ba.doc = bb.servdate
                and ( ba.patient_name = bb.patient
                or (
                    SUBSTRING_INDEX(SUBSTRING_INDEX(ba.patient_name, ' ', 2), ' ', -1) = SUBSTRING_INDEX(SUBSTRING_INDEX(bb.patient, ' ', 2), ' ', -1)
                    AND
                    SUBSTRING_INDEX(ba.patient_name, ' ', 1) = SUBSTRING_INDEX(bb.patient, ' ', 1)
                    )
                )
                left join ci_labs lab ON lab.lab_name = bb.dname
                left JOIN ci_insurancemix inx ON inx.ins_name = bb.pinsname and inx.ins_code = bb.pinscode and inx.lab_ID = lab.lab_ID
                where ba.batch_id = '{$i}' 
                {$where} ) as rs group by rs.mid order by rs.mid";
        $rs = $wpdb->get_results($sql);
        if(isset($_REQUEST['debug'])){
            echo $sql;exit;
        }

        foreach ($rs as $r) {
            $r = array_values(get_object_vars($r));
            fputcsv($f, $r);
            $count++;
        }

    }
    fclose($f);
}

if($count==0)
{
    Tr_Session_Class::add_error('Data match not found!');
    return false;
}




header("Pragma: public");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: private",false);
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=\"$filename\";" );
header("Content-Transfer-Encoding: binary");

readfile($file);
@unlink($file);


// echo microtime(true) - $start_time;

exit;