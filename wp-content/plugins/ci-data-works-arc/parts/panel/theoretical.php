<?php
global $wpdb;

include_once(TRCPDD_PATH . 'pages/formdata.php');

$mrs = $wpdb->get_col("select distinct(cu_ms) as cu_ms from ci_test_panels_cutoffs where cu_ms !='' and cu_btype='tox'");
$mcs = array();
foreach ($mrs as $mc) {
    $ms = explode(',', $mc);
    $mcs = array_merge($mcs, $ms);
}
$mcs = array_map('strtoupper', $mcs);
$mcs = array_unique($mcs);
sort($mcs);

$act = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';
if ($act == 'export') {
    $rows = $wpdb->get_results("select * from ci_theoreticals order by ID asc", ARRAY_A);
    $data = array();
    $header = array('low/medium/high', 'Drug Name', 'Value', 'Specimen');
    $data[] = $header;
    foreach ($rows as $row) {
        $data[] = array($row['qc_type'], $row['qc_drug'], $row['qc_value'], $row['qc_specimen']);
    }
    trpdd_export_csv($data, 'ci_theoretical_values.csv');
    exit;
} else if ($act == 'import') {

    if (isset($_FILES['importfile']) && !empty($_FILES['importfile']['name'])) {
        $filename = $_FILES['importfile']['name'];
        $data = trpdd_get_data($_FILES['importfile']['tmp_name']);

        if (
            stripos($data['data'][0][0], 'qc') > 0 ||
            in_array(strtolower($data['header'][0]), array('low/high', 'low/medium/high'))
        ) {
            $df_specimen = $_POST['qc_specimen'];
            foreach ($data['data'] as $row) {
                $qc_type = strtolower($row[0]);
                $drug = trim(strtoupper($row[1]));
                $value = trim($row[2]);
                if (stripos($qc_type, 'medium') !== false || stripos($qc_type, 'med') !== false) {
                    $qc_type = 'medium';
                } else if (stripos($qc_type, 'high') !== false) {
                    $qc_type = 'high';
                } else {
                    $qc_type = 'low';
                }
                $drug = preg_replace("/\s[0-9]+/", '', $drug);
                $drug = trim($drug);
                $specimen = isset($row[3]) && !empty($row[3]) ? strtolower(trim($row[3])) : $df_specimen;

                $wpdb->query("insert into ci_theoreticals(qc_type,qc_drug,qc_value,qc_specimen)
                                    values('{$qc_type}','{$drug}','{$value}','{$specimen}') 
                                    ON DUPLICATE KEY UPDATE qc_value ='{$value}'");
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
                    <label>Urine or Oral</label>
                    <select name="qc_specimen" required>
                        <option value="">Select</option>
                        <?php
                        foreach (array('urine', 'oral') as $type) {
                            echo '<option value="' . $type . '" >' . $type . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="frow">
                    <label>Import File: </label>
                    <input type="file" name="importfile" required />
                </div>
                <input type="submit" class="button-primary" value="Import" />
            </form>
        </div>

        <br>
        Columns Format: low/medium/high | Drug name | Value
    </div>
<?php

} else if ($act == 'add' || $act == 'edit') {

    if (isset($_POST['data'])) {
        $data = $_POST['data'];
        $id = $data['id'];

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
                <div class="col2">
                    <label>Low, Medium or High QC</label>
                    <select name="data[qc_type]" required>
                        <?php
                        foreach (array('low', 'medium', 'high') as $type) {
                            $selected = $type == $data['qc_type'] ? 'selected' : '';
                            echo '<option value="' . $type . '" ' . $selected . '>' . $type . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="frow">
                <div class="col2">
                    <label>Urine or Oral</label>
                    <select name="data[qc_specimen]" required>
                        <?php
                        foreach (array('urine', 'oral') as $type) {
                            $selected = $type == $data['qc_specimen'] ? 'selected' : '';
                            echo '<option value="' . $type . '" ' . $selected . '>' . $type . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col2">
                    <label>Default Value</label>
                    <input type="text" name="data[qc_value]" value="<?php echo $data['qc_value'] ?>" required maxlength="10" />
                </div>
            </div>



            <div class="frow blabel">
                <div class="col4">
                    <h4>Instrument</h4>
                </div>
                <div class="col4">
                    <h4>Value</h4>
                </div>
                <div class="col4">
                    <h4>Start Date</h4>
                </div>
                <div class="col4">
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
                            <div class="col4">
                                <input type="number" step="any" name="instruments[<?php echo $j ?>][qc_value]" value="<?php echo $instruments[$j]['qc_value'] ?>" maxlength="20" />
                            </div>
                            <div class="col4">
                                <input type="search" class="hasdate" data-write="true" name="instruments[<?php echo $j ?>][start_date]" value="<?php echo $instruments[$j]['start_date'] ?>" />
                            </div>
                            <div class="col4">
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
    if (isset($_POST['options'])) {
        update_option('trinstruments', $_POST['options']);
        Tr_Session_Class::add('Updated Successfully!');
    }
    $options = get_option('trinstruments', array());
?>
    <div class="wrap2">
        <h2>Instruments
            <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">Back</a>
        </h2>

        <?php Tr_Session_Class::show() ?>
        <form method="post">
            <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
                <thead>
                    <tr>
                        <th style="width:200px">Instrument</th>
                        <th>Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($mcs as $mc) {
                    ?>
                        <tr>
                            <td><?php echo $mc ?></td>
                            <td>
                                <select name="options[<?php echo $mc ?>]">
                                    <?php
                                    foreach (array('urine', 'oral') as $k) {
                                        $selected = $options[$mc] == $k ? 'selected' : '';
                                        echo '<option value="' . $k . '" ' . $selected . '>' . $k . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                    <?php
                    }
                    ?>
                </tbody>
            </table>
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
    if (!empty($_GET['lorh'])) {
        $lorh = strtolower(trim($_GET['lorh']));
        if (in_array($lorh, array('mid', 'med'))) {
            $_GET['lorh'] = 'medium';
        }
    }
    $where = '';
    if (empty($_GET['spc'])) $_GET['spc'] = 'urine';
    if (!empty($_GET['spc'])) {
        $where .= " and qc_specimen = '{$_GET['spc']}' ";
    } else {
        $where .= " and qc_specimen in ('urine','oral') ";
    }
    if (!empty($_GET['lorh'])) {
        $where .= " and qc_type = '{$_GET['lorh']}' ";
    }
    $rows = $wpdb->get_results("select * from ci_theoreticals where 1 {$where} order by ID asc");

    if (isset($_POST['qct'])) {
        update_option('tr_lj_lcms_qct', $_POST['qct']);
    }
    $qct = get_option('tr_lj_lcms_qct', 'standard');
?>
    <div class="wrap2">
        <div style="border:1px dashed #ddd;width:600px;margin:10px auto;padding:10px">
            <form method="post">
                <label>
                    <input type="radio" name="qct" value="standard" <?php checked($qct, 'standard') ?> /> Standard Deviation
                </label>
                <label>
                    <input type="radio" name="qct" value="theoretical" <?php checked($qct, 'theoretical') ?> /> Percent Deviation from Theoretical Values
                </label>
                <label>
                    <input type="radio" name="qct" value="both" <?php checked($qct, 'both') ?> /> Both
                </label>
                <input type="submit" class="button-primary" style="margin-left:10px" value="Save" />
            </form>
        </div>

        <h2>Theoretical
            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2">Add New</a>
            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'import', 'id' => '')) ?>" class="add-new-h2">Import</a>
            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'instruments', 'id' => '')) ?>" class="add-new-h2">Instruments</a>
        </h2>

        <form method="get" style="float:right">
            <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
            <input type="hidden" name="pagetab" value="<?php echo isset($_GET['pagetab']) ? $_GET['pagetab'] : '' ?>" />
            <input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />
            <label>Type</label>
            <select name="spc">
                <?php
                foreach (array('urine', 'oral') as $type) {
                    $selected = isset($_GET['spc']) && $type == $_GET['spc'] ? 'selected' : '';
                    echo '<option value="' . $type . '" ' . $selected . '>' . $type . '</option>';
                }
                ?>
            </select>
            <label>Low, Medium or High QC</label>
            <select name="lorh">
                <option value="">Select</option>
                <?php
                foreach (array('low', 'medium', 'high') as $type) {
                    $selected = isset($_GET['lorh']) && $type == $_GET['lorh'] ? 'selected' : '';
                    echo '<option value="' . $type . '" ' . $selected . '>' . $type . '</option>';
                }
                ?>
            </select>
            <input type="submit" class="button" value="Filter" />
        </form>

        <div class="wraptbl">
            <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Component</th>
                        <th>QC Type</th>
                        <th>Theoretical Value</th>
                        <th>Specimen</th>
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
                                <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->ID)) ?>"><?php echo $row->qc_drug ?></a>
                            </td>
                            <td><?php echo $row->qc_type ?></td>
                            <td><?php echo $row->qc_value ?></td>
                            <td><?php echo $row->qc_specimen ?></td>
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
