<?php
global $wpdb;
$act    = isset($_REQUEST['act'])? $_REQUEST['act']:'';
$lab_id = isset($_REQUEST['lid'])? $_REQUEST['lid'] : 0;
$id     = isset($_REQUEST['id'])? $_REQUEST['id']:0;
$value  = isset($_REQUEST['value'])? $_REQUEST['value']:'';
$btype  = isset($_REQUEST['btype'])? $_REQUEST['btype']:'tox';
$check_key = 'cli_faxres';
if($btype!='tox'){
    $check_key = 'cli_fax_'.$btype;
}

if($act=='save_fax_number'){
    $return = array('status'=>'ok','changes'=>array());
    $wpdb->update('ci_clinic',array('cli_om-fax'=>$value),array('cID'=>$id));

    wp_send_json($return);
}
else if($act=='save_fax_result'){
    $return = array('status'=>'ok','changes'=>array());
    $key_exists = $wpdb->get_var("select {$check_key}  from ci_clinic limit 1");
    if(!empty($key_exists))
    {
        $wpdb->query("alter table ci_clinic add column {$check_key} tinyint(1) default 0");
    }
    $wpdb->update('ci_clinic',array($check_key=>$value),array('cID'=>$id));

    wp_send_json($return);
}

$where_clinic = '';

$where_clinic.=" and `cli_lab-ID`={$lab_id}";

$sql = "SELECT * FROM `ci_clinic` cli
            where 1 {$where_clinic} ORDER BY `cli_clinic` asc ";

if(isset($_REQUEST['debug'])){
    echo $sql;
}
$results = $wpdb->get_results($sql, ARRAY_A);
?>

<table class="report_tbl">
    <?php foreach($results as $row):?>
        <tr class="childp" data-id="<?php echo $row['cID']?>">
            <td style="width:50px" class="cid"><?php echo $row['cID']?></td>
            <td style="width:10%"></td>
            <td style="width:40%"><a href="?page=ci_data_forms&tab=new_account_setup&clinic_id=<?php echo $row['cID']?>" target="_blank"><?php echo $row['cli_clinic']?></a></td>
            <td style="width:20%" class="editable"><?php echo $row['cli_om-fax']?></td>
            <td class="checkboxable" data-name="<?php echo $check_key?>"><?php echo $row[$check_key]?></td>
        </tr>
    <?php endforeach?>
</table>