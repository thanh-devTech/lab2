
<div>
    <form method="get" class="form_sale_report">
        <input type="hidden" name="page" value="<?php echo $_GET['page']?>"/>
        <input type="hidden" name="tab" value="<?php echo isset($tab)? $tab : '';?>"/>
        <label>From: </label>
        <input type="text" name="date_from" id="from" value="<?php echo isset($date_from)? $date_from: ''?>"/>
        <label>To: </label>
        <input type="text" name="date_to" id="to" value="<?php echo isset($date_to)? $date_to : ''?>"/>
        <input type="submit" name="submit" id="export" onclick="form_sale_run_action();return false;" value="Run Report" class="button bsty6"/>
        <input type="submit" id="submit_form" style="width:1px;height: 1px;padding: 0px;position: absolute;top:-1000px;">
        <input type="submit" name="export" value="Export" onclick="form_sale_report_action();return false;" class="button"/>
    </form>
    <script>
        function form_sale_report_action(){
            jQuery('#export').append('<input type="hidden" name="tr_action" class="tr_action" value="ci_export_sale_report_info">');
            jQuery('#submit_form').click();
        }

        function form_sale_run_action(){
            jQuery('.tr_action').remove();
            jQuery('#export').click();
        }
    </script>
</div>
<br><br>

<table class="report_tbl" data-count="<?php echo isset($count_rows)? $count_rows: 0?>">
    <?php if(isset($_REQUEST['submit'])):?>
        <thead>
        <tr>
            <th style="width:20px"></th>
            <th style="width:12%">Rep</th>
            <th style="width:10%">Date</th>
            <th style="width:10%">Accession</th>
            <th style="width:150px">Patient Name</th>
            <th style="width:10%">DOB</th>
            <th style="width:150px;">Clinic</th>
            <th style="width:12%">Doctor</th>
            <th>Notes</th>
        </tr>
        </thead>
    <?php endif;?>
    <tbody>
    <?php
    $parents = array();
    $html = '';
    $current_code = null;
    $collected = 0;
    $commission = 0;
    $count_child = 0;
    $count_paid = 0;
    $count_bill = 0;
    $parent_id = 1;
    // var_dump($rows);exit;


    if(!isset($rows) || !is_array($rows))$rows = array();

    foreach($rows as $i => $row)
    {
        if(!in_array($row->rep,$parents))
        {
            $parents[] = $row->rep;
            ob_start();
        }
        if(empty($row->collected))$row->collected = 0;


        $row->commission = trpdd_cal_commission($row->collected,$row->rep_comission,$row->ins_mix,$row->btype);
        ?>
        <tr class="childp">
            <td <?php if($count_child==0)echo 'style="width:20px"'?>></td>
            <td <?php if($count_child==0)echo 'style="width:12%"'?>></td>
            <td <?php if($count_child==0)echo 'style="width:10%"'?>><?php echo date('Y-m-d',strtotime($row->doc));?></td>
            <td <?php if($count_child==0)echo 'style="width:10%"'?>><?php echo $row->assen?></td>
            <td <?php if($count_child==0)echo 'style="width:150px"'?>><?php echo $row->patient_name;?></td>
            <td <?php if($count_child==0)echo 'style="width:10%"'?>><?php echo $row->dob?></td>
            <td <?php if($count_child==0)echo 'style="width:150px"'?>><?php echo $row->cli_clinic;?></td>
            <td <?php if($count_child==0)echo 'style="width:12%"'?>><?php echo $row->doc_last.', '.$row->doc_first;?></td>
            <td><?php echo $row->billing_notes;?></td>
        </tr>

        <?php
        $collected +=  $row->collected;
        $commission+= $row->commission;
        $count_child++;
        $count_paid += ($row->collected>0)? 1 : 0;
        $count_bill+= !empty($row->patient_name)? 1 : 0;
        $has_result = true;
        $next_row = @$rows[$i+1];


        if(($next_row && !in_array($next_row->rep,$parents) || $count_rows==$i+1) && count($parents)>0)
        {
            $html = ob_get_clean();

            if(empty($row->rep))
            {
                $row->rep = 'Unknown';
            }
            ?>
            <tr class="parent" data-p="<?php echo $parent_id?>">
                <td><i></i></td>
                <td><?php echo $row->rep;?></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <tr class="child p_<?php echo $parent_id?>">
                <td colspan="9" class="nopadding">
                    <table style="width: 100%;" class="report_tbl">
                        <?php echo $html?>
                    </table>
                </td>
            </tr>
            <?php
            $parent_id++;
            $collected = 0;
            $commission = 0;
            $count_child = 0;
            $count_paid = 0;
            $count_bill = 0;
        }
    }


    ?>

    </tbody>
</table>
<div>Total: <?php echo isset($count_rows)? $count_rows:0?></div>

<?php if(isset($_REQUEST['submit']) && !$has_result):?>
    <div class="updated"><p>No Result Found</p></div>
<?php endif;?>