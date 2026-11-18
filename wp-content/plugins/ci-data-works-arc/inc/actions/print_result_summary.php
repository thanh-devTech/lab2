<?php
global $wpdb,$report;

$batch_id   = intval($_REQUEST['batch_id']);
$lab_id     = intval(@$_REQUEST['lab_id']);
$ids = isset($_REQUEST['ids'])? $_REQUEST['ids']:'';

if($lab_id ==0)
{
    $lab_id = CI_Base_Lab::get_current_lab_id();
}

$where = '';
//$where .= " and re.estatus = 'C' ";
$where.= " and ba.batch_id = '{$batch_id}' and ba.lab_id = '{$lab_id}' ";
if(!empty($ids))
{
    $ids = explode(',',$ids);
    $ids = array_filter($ids);
    $ids = implode(',',$ids);
    $where .= " and re.id in ({$ids})";
}


$sql = "select re.id 
    from ci_reports re
    JOIN ci_batches ba ON ba.assen = re.patient_id
    where 1 {$where}
    group by ba.mid 
    order by ba.position asc";

$reports = $wpdb->get_col($sql);


ob_start();
include(TRCPDD_PATH.'/reports/results_tox_summary.php');
$html = ob_get_clean();

echo $html;
?>
<script>
    window.print();
</script>
<?php
exit;




$filename = 'print.pdf';
header("Pragma: public");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: private",false);
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=\"$filename\";" );
header("Content-Transfer-Encoding: binary");

trpdd_build_pdf($html,array('filename'=>$filename));

exit;