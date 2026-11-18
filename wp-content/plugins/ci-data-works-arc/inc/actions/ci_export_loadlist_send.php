<?php
global $wpdb;
$order_by = 'ba.spec_received desc, lab.lab_dba_init asc, lr.lab_dba_init asc';
$cIDs_str = explode(',',$cIDs);

$where = " and cli_ID in ({$cIDs_str}) ";


$sql = "select assen,
                doc,
                dob,
                current_medication
                
            from ci_batches
            where ba.batch_id!=0 {$where} order by {$order_by} ";


$batches = $wpdb->get_results($sql);

if($filepath)
{
    if(count($batches)==0)
    {
        return false;
    }
    $f = fopen($filepath,'w');
}else{
    $file = tempnam("/tmp", "export");
    $f = fopen($file,'w');
}

$i = 1;
$keys = array(
    'Position',
    'Accession',
    'Old Req Number',
    'Patient Name',
    'Medication',
    'DOB',
    'Facility',
    'Clinic City',
    'Provider',
    'Dr Phone',
    'Collection',
    'Received',
    'Lab',
    'Ref',
    'Req Panel Tests',
    'Insurance',
    'Notes',
    'Status'
);
fputcsv($f,$keys);

foreach($batches as $row)
{
    $i++;
    $row_data = array(
        $row->assen,
        $row->doc,
        $row->dob,
        $row->current_medication
    );


    fputcsv($f,$row_data);
}
@fclose($f);