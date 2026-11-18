<div class="wrap">
    <?php
    global $wpdb;

    if($act=='add' || $act=='edit')
    {

        if(isset($_POST['data'])){
            $data = $_POST['data'];
            $id = $data['id'];

            if($id==0)
            {
                $rs = $wpdb->insert('ci_drug_analyte_pairs',$data);
            }else{
                $rs = $wpdb->update('ci_drug_analyte_pairs',$data,array('id'=>$id));
            }

            if($rs===false)
            {
                Tr_Session_Class::add_error('Error: '.$wpdb->last_error);
            }else{
                Tr_Session_Class::add('Saved Successfully!');
            }
        }


        $tests = $wpdb->get_col("select te_name from ci_test_panels where te_type='tox' order by te_name");
        $tests = array_map('strtoupper',$tests);
        if($_REQUEST['id']>0 && $act=='edit')
        {
            $id = $_REQUEST['id'];
            $data = $wpdb->get_row("select * from ci_drug_analyte_pairs where id= {$id}", ARRAY_A);
            $data['drug_1'] = strtoupper($data['drug_1']);
            $data['drug_2'] = strtoupper($data['drug_2']);
            $data['drug_3'] = strtoupper($data['drug_3']);
            $data['drug_4'] = strtoupper($data['drug_4']);

            if(!empty($data['drug_1']) && !in_array($data['drug_1'],$tests))
            {
                $tests[] = $data['drug_1'];
            }
            if(!empty($data['drug_2']) && !in_array($data['drug_2'],$tests))
            {
                $tests[] = $data['drug_2'];
            }
            if(!empty($data['drug_3']) && !in_array($data['drug_3'],$tests))
            {
                $tests[] = $data['drug_3'];
            }
            if(!empty($data['drug_4']) && !in_array($data['drug_4'],$tests))
            {
                $tests[] = $data['drug_4'];
            }
        }
        ?>
        <h2>Edit</h2>

        <?php Tr_Session_Class::show()?>
        <form method="post">
            <div class="frow">
                <div class="col2">
                    <div class="frow">
                        <label>Main Test</label>
                        <select name="data[drug_1]" required class="js_select2">
                            <option value="">Select Test</option>
                            <?php
                            foreach($tests as $test)
                            {
                                $selected = $test == $data['drug_1']? 'selected':'';
                                echo '<option value="'.$test.'" '.$selected.'>'.$test.'</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="frow">
                        <label>Ref Test #1</label>
                        <select name="data[drug_2]" required class="js_select2">
                            <option value="">Select Test</option>
                            <?php
                            foreach($tests as $test)
                            {
                                $selected = $test == $data['drug_2']? 'selected':'';
                                echo '<option value="'.$test.'" '.$selected.'>'.$test.'</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="frow">
                        <label>Ref Test #2</label>
                        <select name="data[drug_3]" class="js_select2">
                            <option value="">Select Test</option>
                            <?php
                            foreach($tests as $test)
                            {
                                $selected = $test == $data['drug_3']? 'selected':'';
                                echo '<option value="'.$test.'" '.$selected.'>'.$test.'</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="frow">
                        <label>Ref Test #3</label>
                        <select name="data[drug_4]" class="js_select2">
                            <option value="">Select Test</option>
                            <?php
                            foreach($tests as $test)
                            {
                                $selected = $test == $data['drug_4']? 'selected':'';
                                echo '<option value="'.$test.'" '.$selected.'>'.$test.'</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="frow">
                <input type="submit" class="button-primary" value="Save"/>
                <input type="hidden" name="data[id]" value="<?php echo $id?>"/>
            </div>
        </form>
        <?php
    }
    else
    {

        if($act=='pdelete' && $_REQUEST['idd']>0)
        {
            $rs= $wpdb->delete('ci_drug_analyte_pairs',array('id'=>$_REQUEST['idd']));
            Tr_Session_Class::add("Deleted Successfully");
        }
        $rows = $wpdb->get_results("select * from ci_drug_analyte_pairs order by drug_1 asc");
        ?>
        <h2>Metabolites
            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2">Add New</a>
        </h2>
        <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
            <thead>
            <tr>
                <th style="width:50px">#</th>
                <th>Main Test</th>
                <th>Ref Test #1</th>
                <th>Ref Test #2</th>
                <th>Ref Test #3</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach ($rows as $i => $row) {
                ?>
                <tr>
                    <td><?php echo $i + 1 ?></td>
                    <td>
                        <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->id)) ?>"><?php echo $row->drug_1 ?></a>
                    </td>
                    <td><?php echo $row->drug_2 ?></td>
                    <td><?php echo $row->drug_3 ?></td>
                    <td><?php echo $row->drug_4 ?></td>
                    <td>
                        <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->id)) ?>">Edit</a> -
                        <a class="pdelete" data-id="<?php echo $row->id ?>" href="#">Delete</a>
                    </td>
                </tr>
                <?php
            }
            ?>
            </tbody>
        </table>

        <?php
    }
    ?>
</div>
