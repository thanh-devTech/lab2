<?php
if(isset($_POST['do_import'])){
    $data = file_get_contents($_FILES['fileimport']['tmp_name']);
    $data = json_decode($data,true);
    $count_row = 0;
    if (!is_array($data) || count($data) == 0) {
        Tr_Session_Class::add_error('Import failed. No Medicare code records were found in the uploaded file.');
    } else {
    foreach($data as $row)
    {
        if (empty($row['cm_code'])) {
            continue;
        }
	    $tests = isset($row['tests']) ? $row['tests'] : array();
        unset($row['cm_ID']);
        unset($row['tests']);

	    $row_id = $wpdb->get_var("select cm_ID from ci_bill_codes_medicare where cm_code = '{$row['cm_code']}' ");
        if($row_id>0)
        {
            $rs = $wpdb->update('ci_bill_codes_medicare',$row,array('cm_ID'=>$row_id));
            if ($rs !== false) {
                $count_row++;
            }
        }else{
	        $rs = $wpdb->insert('ci_bill_codes_medicare',$row);
	        $row_id = $wpdb->insert_id;
            if ($rs !== false && $row_id > 0) {
                $count_row++;
            }
        }

    }
    if ($count_row > 0) {
        Tr_Session_Class::add('Imported successfully!');
    } else {
        Tr_Session_Class::add_error('Import failed. No Medicare code records were imported. Please use a Medicare code JSON file for this tab.');
    }
    }
}

if($act=='export'){

	$rows = $wpdb->get_results("select * from ci_bill_codes_medicare",ARRAY_A);
	
	$filename = 'bill_codes_medicare.json';
	header("Content-disposition: attachment; filename={$filename}");
	header("Content-Type: text/json");
    echo json_encode($rows);
    exit;
}
else if($act=='import'){
	?>
    <form method="post" enctype="multipart/form-data">
        <div class="frow">
            <label>File:</label>
            <input type="file" name="fileimport"/>
            <input type="submit" class="button" name="do_import" value="Upload"/>
        </div>
    </form>
	<?php
	exit;
}
?>
<div class="wrap">
    <?php
    global $wpdb;

    if(isset($_POST['save_action'])){
        $_POST =  tr_pdd_stripslashes_deep($_POST);
        $row = $_POST['drug_class'];

        if($row['cm_ID'] > 0)
        {
            $rs = $wpdb->update('ci_bill_codes_medicare',$row,array('cm_ID' => $row['cm_ID']));
            if($rs===false)
            {
                Tr_Session_Class::add_error('Error. '.$wpdb->last_error);
            }
            else{
                Tr_Session_Class::add('Updated Successfully!');
            }
        }else{
            $row['cm_created'] = Tr_Base_Class_V4::date("Y-m-d H:i:s");
            $row['cm_created_by'] = get_current_user_id();
            $wpdb->insert('ci_bill_codes_medicare',$row);
            $row_id = $wpdb->insert_id;
            if($row_id>0)
            {
                Tr_Session_Class::add('Created New  Successfully!');
            }
            else{
                Tr_Session_Class::add_error('Error.'.$wpdb->last_error);
            }
            $row = array();
        }

    }


    if($act=='edit' || $act=='add'){

        if($_REQUEST['id']>0)
        {
            $id = $_REQUEST['id'];
            $row = $wpdb->get_row("select * from ci_bill_codes_medicare where cm_ID = {$id}",ARRAY_A);
        }
        ?>
        <h2><?php echo ($act=='add')? 'Add New': 'Edit'?>  <a href="<?php Tr_Base_Class_V4::link(array('act'=>'','id'=>''))?>" class="add-new-h2">All</a> </h2>

        <?php Tr_Session_Class::show()?>

        <form method="post" class="need_validate">
            <div class="frow">
                <h4>Info</h4>
            </div>

            <div class="frow">
                <div class="col2">
                    <label>Code</label>
                    <input type="text" name="drug_class[cm_code]" value="<?php echo @$row['cm_code']?>" maxlength="255"  required/>
                </div>
                <div class="col2">
                    <label>Name</label>
                    <input type="text" name="drug_class[cm_name]" value="<?php echo @$row['cm_name']?>" maxlength="255"  required/>
                </div>
            </div>

            <div class="frow">
                <div class="col2">
                    <label>Range Bot</label>
                    <input type="text" name="drug_class[cm_range_bot]" value="<?php echo @$row['cm_range_bot']?>" maxlength="11" />
                </div>
                <div class="col2">
                    <label>Range Top</label>
                    <input type="text" name="drug_class[cm_range_top]" value="<?php echo @$row['cm_range_top']?>" maxlength="11"  />
                </div>
            </div>
            <div class="frow">
                <div class="col2">
                    <label>Amount</label>
                    <input type="text" name="drug_class[cm_amount]" value="<?php echo @$row['cm_amount']?>" maxlength="255"  />
                </div>
            </div>





            <div class="frow">
                <input type="submit" class="button-primary" value="Submit">
                <input type="hidden" name="save_action" value="1"/>
                <input type="hidden" name="drug_class[cm_ID]" value="<?php echo @$row['cm_ID']?>"/>
            </div>
        </form>

    <?php
    }
    else {
    if($act=='delete'){
        $wpdb->query("delete from `ci_bill_codes_medicare` WHERE  `cm_ID`='".$_REQUEST['id']."'");
        Tr_Session_Class::add('Deleted Successfully');
    }

    $order = @$_REQUEST['order']=='desc'?'desc':'asc';
    $orderby = "order by cm_ID $order";

    $sql = "select * from ci_bill_codes_medicare {$orderby} ";


    $rows = $wpdb->get_results($sql);

    if(isset($_REQUEST['debug'])){
        var_dump($sql);
    }
    ?>
        <h2>Billing Codes - Medicare 
            <a href="<?php Tr_Base_Class_V4::link(array('act'=>'add','id'=>''))?>" class="add-new-h2">Add New</a>
            <a href="<?php Tr_Base_Class_V4::link(array('act'=>'export','id'=>'','trajax'=>'1'))?>" class="add-new-h2">Export</a>
            <a href="<?php Tr_Base_Class_V4::link(array('act'=>'import','id'=>'','trajax'=>'1'))?>" class="add-new-h2 thickbox">Import</a>
        </h2>
    <?php Tr_Session_Class::show()?>

        <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
            <thead>
            <tr>
                <th style="width:4%">ID</th>
                <th>Code</th>
                <th>Range Bot</th>
                <th>Range Top</th>
                <th>Name</th>
                <th>Amount</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row):
                ?>
                <tr>
                    <td><?php echo $row->cm_ID ?></td>
                    <td><?php echo $row->cm_code?></td>
                    <td><?php echo $row->cm_range_bot ?></td>
                    <td><?php echo $row->cm_range_top ?></td>
                    <td><?php echo $row->cm_name ?></td>
                    <td><?php echo $row->cm_amount?></td>
                    <td>
                        <a href="<?php Tr_Base_Class_V4::link(array('act' => 'edit', 'id' => $row->cm_ID)) ?>">Edit</a> -
                        <a class="delete" href="<?php Tr_Base_Class_V4::link(array('act' => 'delete', 'id' => $row->cm_ID)) ?>">Delete</a>
                    </td>
                </tr>
            <?php endforeach;?>
            </tbody>
        </table>
        <script>
            jQuery(function($){
                $('a.delete').click(function(){
                    if (confirm('Do you want Delete?') == false)return false;
                })
            })
        </script>
        <?php
    }
    ?>
</div>
