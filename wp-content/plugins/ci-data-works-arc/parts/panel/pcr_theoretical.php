<?php
global $wpdb;

include_once(TRCPDD_PATH . 'pages/formdata.php');

$mcs = get_option('trinstruments_pcr', array());

$act = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';
if ($act == 'import') {

    if (isset($_FILES['importfile']) && !empty($_FILES['importfile']['name'])) {
        $filename = $_FILES['importfile']['name'];
        $data = trpdd_get_data($_FILES['importfile']['tmp_name']);

        if (stripos($data['header'][0], 'name') !== false && stripos($data['header'][1], 'cutoff') !== false) {
            foreach ($data['data'] as $row) {
                $drug = trim(strtoupper($row[0]));
                $qc_type = '';
                $wpdb->query("insert into ci_theoreticals(qc_type,qc_drug,qc_value,qc_value2,qc_specimen)
                values('{$qc_type}','{$drug}','{$row[1]}','{$row[2]}','pcr') 
                ON DUPLICATE KEY UPDATE qc_value ='{$row[1]}', qc_value2 ='{$row[2]}' ");
            }
            echo '<p class="center">Imported successfully!</p>';
        } else if (stripos($data['data'][0][0], 'qc') > 0) {

            foreach ($data['data'] as $row) {
                $qc_type = strtolower($row[0]);
                $drug = trim(strtoupper($row[1]));
                $value = trim($row[2]);

                $qc_type = stripos($qc_type, 'high') !== false ? 'high' : 'low';
                $fieldname = $qc_type == 'high' ? 'qc_value2' : 'qc_value';
                $qc_type = '';
                $drug = preg_replace("/\s[0-9]+/", '', $drug);
                $drug = trim($drug);


                $wpdb->query("insert into ci_theoreticals(qc_type,qc_drug,{$fieldname},qc_specimen)
                                    values('{$qc_type}','{$drug}','{$value}','pcr') 
                                    ON DUPLICATE KEY UPDATE {$fieldname} ='{$value}' ");
            }
            echo '<p class="center">Imported successfully!</p>';
        } else {
            echo '<p class="center error red">Wrong format</p>';
        }
    }
?>
    <div class="wrap2">
        <div style="width:380px;margin:20px auto;border:1px dashed #aaa;padding:20px;background:#fefefe">
            <form method="post" enctype="multipart/form-data">

                <div class="frow">
                    <label>Import File: </label>
                    <input type="file" name="importfile" required />
                </div>
                <input type="submit" class="button-primary" value="Import" />
            </form>
        </div>

        <br>
        Columns Format: low/high | Drug name | Value
    </div>
<?php

} else if ($act == 'add' || $act == 'edit') {

    if (isset($_POST['data'])) {
        $data = $_POST['data'];
        $id = $data['id'];
        $data['qc_specimen'] = 'pcr';

        if ($id == 0) {
            $rs = $wpdb->insert('ci_theoreticals', $data);
            $data = array();
            $id = $wpdb->insert_id;
        } else {
            $rs = $wpdb->update('ci_theoreticals', $data, array('id' => $id));
        }

        if ($rs === false) {
            Tr_Session_Class::add_error('Error: ' . $wpdb->last_error);
        } else {
            Tr_Session_Class::add('Saved Successfully!');

            $wpdb->delete('ci_theoretical_instruments', array('theo_ID' => $id));
            foreach ($_POST['instruments'] as $d) {
                $d['theo_ID'] = $id;
                if (!empty($d['qc_value'])) {
                    $rs = $wpdb->insert('ci_theoretical_instruments', $d);
                    if ($rs === false) {
                        echo $wpdb->last_error;
                    }
                }
            }
        }
    }


    if ($_REQUEST['id'] > 0 && $act == 'edit') {
        $id = $_REQUEST['id'];
        $data = $wpdb->get_row("select * from ci_theoreticals where id= {$id}", ARRAY_A);
        $instruments = $wpdb->get_results("select * from ci_theoretical_instruments where theo_ID = {$id}", ARRAY_A);
    } else {
        $data = array();
        $instruments = array();
    }



?>
    <div class="wrap2">
        <h2>Edit
            <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">Back</a>
        </h2>

        <?php Tr_Session_Class::show() ?>
        <form method="post">
            <div class="frow">
                <div class="col2">
                    <label>Component</label>
                    <input type="text" name="data[qc_drug]" value="<?php echo $data['qc_drug'] ?>" required maxlength="45" />
                </div>

            </div>

            <div class="frow">

                <div class="col2">
                    <label>Low Value</label>
                    <input type="text" name="data[qc_value]" value="<?php echo $data['qc_value'] ?>" required maxlength="10" />
                </div>
                <div class="col2">
                    <label>High Value</label>
                    <input type="text" name="data[qc_value2]" value="<?php echo $data['qc_value2'] ?>" required maxlength="10" />
                </div>
            </div>



            <div class="frow blabel">
                <div class="col4">
                    <h4>Instrument</h4>
                </div>
                <div class="col6">
                    <h4>Low</h4>
                </div>
                <div class="col6">
                    <h4>High</h4>
                </div>
                <div class="col6">
                    <h4>Start Date</h4>
                </div>
                <div class="col6">
                    <h4>End Date</h4>
                </div>
            </div>
            <ol id="doctors_dv" class="more">
                <?php
                $max_j = count($instruments) > 1 ? count($instruments) : 1;
                for ($j = 0; $j < $max_j; $j++): ?>
                    <li class="frow blabel pdoctorrow <?php if ($j == 0) echo 'default' ?>">
                        <div class="frow">
                            <div class="col4">
                                <select name="instruments[<?php echo $j ?>][instrument]">
                                    <option value="">Select Instrument</option>
                                    <?php
                                    foreach ($mcs as $mc) {
                                        $selected = $mc == $instruments[$j]['instrument'] ? 'selected' : '';
                                        echo '<option value="' . $mc . '" ' . $selected . '>' . $mc . '</option>';
                                    }
                                    ?>
                                </select>

                            </div>
                            <div class="col6">
                                <input type="number" step="any" name="instruments[<?php echo $j ?>][qc_value]" value="<?php echo $instruments[$j]['qc_value'] ?>" maxlength="20" />
                            </div>
                            <div class="col6">
                                <input type="number" step="any" name="instruments[<?php echo $j ?>][qc_value2]" value="<?php echo $instruments[$j]['qc_value2'] ?>" maxlength="20" />
                            </div>
                            <div class="col6">
                                <input type="search" class="hasdate" data-write="true" name="instruments[<?php echo $j ?>][start_date]" value="<?php echo $instruments[$j]['start_date'] ?>" />
                            </div>
                            <div class="col6">
                                <input type="search" class="hasdate" data-write="true" name="instruments[<?php echo $j ?>][end_date]" value="<?php echo $instruments[$j]['end_date'] ?>" />
                                <a class="rm_doctor_row button">-</a>
                            </div>
                        </div>
                    </li>
                <?php endfor; ?>
            </ol>
            <a class="button add_doctor_row" data-id="<?php echo $j ?>">+</a>
            <br><br>

            <div class="frow">
                <input type="submit" class="button-primary" value="Save" />
                <input type="hidden" name="data[id]" value="<?php echo $id ?>" />
            </div>
        </form>
    </div>
<?php
} else if ($act == 'instruments') {
    if (isset($_POST['instruments'])) {
        $instruments = array();
        foreach (explode("\r", $_POST['instruments']) as $ms) {
            $ms = trim($ms);
            if (!empty($ms)) {
                $instruments[] = $ms;
            }
        }
        update_option('trinstruments_pcr', $instruments);
        Tr_Session_Class::add('Updated Successfully!');
    }
    $instruments = get_option('trinstruments_pcr', array());
?>
    <div class="wrap2">
        <h2>Instruments
            <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">Back</a>
        </h2>

        <?php Tr_Session_Class::show() ?>
        <form method="post">
            <textarea name="instruments" style="width:500px;height:500px"><?php echo implode("\r", $instruments) ?></textarea>
            <br>
            <input type="submit" class="button-primary" value="Update" />
        </form>
    </div>
<?php
} else {

    if ($act == 'pdelete' && $_REQUEST['idd'] > 0) {
        $rs = $wpdb->delete('ci_theoreticals', array('ID' => $_REQUEST['idd']));
        Tr_Session_Class::add("Deleted Successfully");
    }
    $where = '';

    $rows = $wpdb->get_results("select * from ci_theoreticals where qc_specimen='pcr' {$where} order by ID asc");

?>
    <div class="wrap2">

        <h2>Theoretical
            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2">Add New</a>
            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'import', 'id' => '')) ?>" class="add-new-h2">Import</a>
            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'instruments', 'id' => '')) ?>" class="add-new-h2">Instruments</a>
        </h2>

        <form method="get" style="float:right">
            <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
            <input type="hidden" name="pagetab" value="<?php echo isset($_GET['pagetab']) ? $_GET['pagetab'] : '' ?>" />
            <input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />

            <input type="submit" class="button" value="Filter" />
        </form>

        <div class="wraptbl">
            <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Component</th>
                        <th style="width:100px">Low</th>
                        <th style="width:100px">High</th>
                        <th style="width:100px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($rows as $i => $row) {
                        if(stripos($row->qc_value,',')>0 && stripos($row->qc_value,'.')===false)
                        {
                            $wpdb->update('ci_theoreticals',array(
                                'qc_value' => str_replace(',','.',$row->qc_value),
                                'qc_value2' => str_replace(',','.',$row->qc_value2),
                            ),array('ID'=>$row->ID));
                        }
                    ?>
                        <tr>
                            <td><?php echo $i + 1 ?></td>
                            <td>
                                <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->ID)) ?>"><?php echo $row->qc_drug ?></a>
                            </td>
                            <td><?php echo $row->qc_value ?></td>
                            <td><?php echo $row->qc_value2 ?></td>
                            <td>
                                <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->ID)) ?>">Edit</a> -
                                <a class="pdelete" data-id="<?php echo $row->ID ?>" href="#">Delete</a>
                            </td>
                        </tr>
                    <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

<?php
}
