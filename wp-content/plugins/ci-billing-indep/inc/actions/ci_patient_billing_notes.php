<?php
global $wpdb;
$mid = $_REQUEST['mid'];
$btype = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'tox';

$batch_tbl = tr_pdd_get_table_by_btype($btype);
$patient_id = $wpdb->get_var("select patient_id from {$batch_tbl} where mid = {$mid}");

if (isset($_POST['bill_notes'])) {
    /*
    $data = array('pa_bill_notes' => $_POST['bill_notes']);
    $rs = $wpdb->update('ci_patients', $data, array('pa_ID' => $patient_id));
    if ($rs === false) {
        $wpdb->query("alter table ci_patients add column pa_bill_notes text");
        $rs = $wpdb->update('ci_patients', $data, array('pa_ID' => $patient_id));
    }*/

    $wpdb->update($batch_tbl, array('billing_notes' => $_POST['bill_notes']), array('mid' => $mid));
    echo 'Updated notes';
    exit;
}

$billing_notes = $wpdb->get_var("select billing_notes from {$batch_tbl} where mid = {$mid}");
?>

<form method="post" id="billing_notes_form">
    <div>Billing Notes</div>
    <textarea name="bill_notes" style="width:100%;height:150px;"><?php echo $billing_notes ?></textarea>
    <div>
        <input type="submit" class="button-primary submit" value="Save" />
        <input type="hidden" name="mid" value="<?php echo $mid ?>" />
        <input type="hidden" name="patient_id" value="<?php echo $patient_id ?>" />
        <input type="hidden" name="tr_action" value="ci_patient_billing_notes" />
    </div>
</form>
<script>
    jQuery(function($) {
        $('#billing_notes_form').submit(function() {
            var $btn = $(this).find('.submit');
            $.ajax({
                type: 'post',
                data: $(this).serialize(),
                success: function(rs) {
                    alert(rs);
                    tb_remove();
                }
            })
            return false;
        })
    })
</script>