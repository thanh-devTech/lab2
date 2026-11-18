<?php

$id = $_REQUEST['id'];

if (isset($_POST['clinics'])) {
    foreach ($_POST['clinics'] as $cid) {
        $wpdb->update('ci_clinic', array('cli_ref_lab_ID' => $id), array('cID' => $cid));
    }
    Tr_Session_Class::add('Saved Successfully!');
}

include_once(TRCPDD_PATH . 'pages/formdata.php');

$lab = $wpdb->get_row("select * from ci_labs where lab_ID = {$id}");
$clinics = $wpdb->get_results("select * from ci_clinic where cli_status = 1 order by cli_clinic");
?>
<div class="wrap">
    <h2>Clinics for <?php echo $lab->lab_dba_init ?>
        <a class="add-new-h2" href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $id)) ?>">Edit Ref Lab</a>
        <a class="add-new-h2" href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>">All Ref Lab</a>
    </h2>
    <?php Tr_Session_Class::show() ?>
    <form method="post" style="margin-top:15px;">
        <label>Clinics</label>
        <select name="clinics[]" multiple class="js_select2" style="width:80%">
            <?php
            foreach ($clinics as $clinic) {
                $selected = $clinic->cli_ref_lab_ID == $id ? 'selected' : '';
                echo '<option value="' . $clinic->cID . '" ' . $selected . '>' . $clinic->cli_clinic . '</option>';
            }
            ?>
        </select>
        <div class="frow" style="margin-left:15px;margin-top:10px;">
            <input type="submit" class="button-primary" value="Save" />
        </div>
    </form>
</div>