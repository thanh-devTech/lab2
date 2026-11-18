<?php

$id = $_REQUEST['id'];

if (isset($_POST['fees'])) {
    foreach ($_POST['fees'] as $lab_id => $fee) {
        $exists = $wpdb->get_var("select lab_ID from ci_lab_ref_fees where lab_ID = {$lab_id} and ref_lab_ID = {$id}");
        if ($exists > 0) {
            $data = array('lab_specimen_fee' => $fee);
            $where = array('lab_ID' => $lab_id, 'ref_lab_ID' => $id);
            $wpdb->update('ci_lab_ref_fees', $data, $where);
        } else {
            $data = array('lab_specimen_fee' => $fee, 'lab_ID' => $lab_id, 'ref_lab_ID' => $id);
            $wpdb->insert('ci_lab_ref_fees', $data);
        }
    }
    Tr_Session_Class::add('Saved Fees');
}

include_once(TRCPDD_PATH . 'pages/formdata.php');
$lab_fee = $wpdb->get_row("select * from ci_labs where lab_ID = {$id}");
$labs = $wpdb->get_results("select lab.lab_ID,lab.lab_dba,lab.lab_dba_init,lrf.lab_specimen_fee
                            from ci_labs lab
                            left JOIN ci_lab_ref_fees lrf ON lrf.lab_ID = lab.lab_ID and lrf.ref_lab_ID = {$id}
                            where (lab.lab_is_ref=0 or lab.lab_is_ref=2)
                             order by lab_dba_init");
?>
<div class="wrap">
    <h2>Fees for <?php echo $lab_fee->lab_dba_init ?></h2>
    <?php Tr_Session_Class::show() ?>
    <form method="post">
        <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th>Lab</th>
                    <th>INIT</th>
                    <th>Fee</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($labs as $i => $lab) {
                ?>
                    <tr>
                        <td><?php echo $i + 1 ?></td>
                        <td><?php echo $lab->lab_dba ?></td>
                        <td><?php echo $lab->lab_dba_init ?></td>
                        <td><input type="text" name="fees[<?php echo $lab->lab_ID ?>]" value="<?php echo $lab->lab_specimen_fee ?>" /></td>
                    </tr>
                <?php
                }
                ?>
            </tbody>
        </table>
        <br><br>
        <input type="submit" class="button-primary" name="form_save" value="Save" />
    </form>
</div>