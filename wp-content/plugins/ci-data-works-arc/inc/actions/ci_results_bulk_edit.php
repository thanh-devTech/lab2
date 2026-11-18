<?php
global $wpdb;
$ids = trim($_REQUEST['ids'], ",");
$assen = trim($_REQUEST['assen'], ",");
$assen = !empty($assen) ? explode(',', $assen) : array(); //to array


if (isset($_POST['status'])) {
    $tests = $_POST['status'];
    foreach ($tests as $test => $status) {
        if ($status != 'same') {
            $up_comment = '';
            if ($status == 'PEN') {
                $up_comment = ", comments = 'Pending.' ";
            }
            $rs = $wpdb->query("update ci_results set conc='', `pos-neg`='{$status}' {$up_comment}
                        where drug='{$test}' and report_id in ({$ids}) ");
            if ($rs === false) {
                echo $wpdb->last_error;
            }
        }
    }
    if (isset($_POST['comment1']) && $_POST['comment1'] != 'same') {
        $wpdb->query("update ci_reports set show_comment1={$_POST['comment1']} where id in ({$ids})");
    }
    if (isset($_POST['comment2']) && $_POST['comment2'] != 'same') {
        $wpdb->query("update ci_reports set show_comment2={$_POST['comment2']} where id in ({$ids})");
    }
    if (@$_POST['type'] == 'save_approve') {
        foreach (explode(',', $ids) as $id) {
            $wpdb->update('ci_reports', array('estatus' => 'C'), array('id' => $id));

            $report = $wpdb->get_row("select ba.mid,ba.batch_status, ba.assen,
                                    re.id, re.estatus
                                from ci_batches ba 
                                join ci_reports re ON re.patient_id = ba.assen
                                where re.id = {$id} ", ARRAY_A);


            $rejected_statuses = tr_pdd_get_batch_statuses(false, true, true, 'tox');
            if (!isset($rejected_statuses[$report['batch_status']])) {
                $wpdb->update('ci_batches', array('batch_status' => 'completed'), array('mid' => $report['mid']));
            }

            trpdd_log_user(get_current_user_id(), 'approve_rs', $report['assen'], $report['mid'], 'tox', '', '', $report['assen']);

            trpdd_export_results_pdf($id);
        }
    }
    echo 'ok';
    exit;
}


if (empty($ids) && count($assen) > 0) {
    $assen_list = implode("','", $assen);
    $ids = $wpdb->get_col("select id from ci_reports where patient_id in ('{$assen_list}') and ( estatus !='C' or estatus is null)");
    $ids = implode(',', $ids);
    if (empty($ids)) {
        echo " No Test editable<br>";
    }
} else {

    $ids = $wpdb->get_col("select id from ci_reports where id in ({$ids}) and ( estatus !='C' or estatus is null)");

    $ids = implode(',', $ids);
    if (empty($ids)) {
        echo " No Test editable<br>";
    }
}



if (empty($ids)) {
?>

    <label>Accession:</label>
    <input type="text" id="assen_import_list" style="width:100%" />
    <small>Split by comma</small>
    <div>
        <input type="button" class="button" id="assen_import_button" value="Edit" />
    </div>

    <script>
        jQuery(function($) {
            $('#assen_import_button').click(function() {
                var $assen = $('#assen_import_list').val();
                if ($assen == '') {
                    return false;
                }
                var $url = '?tr_action=ci_results_bulk_edit&assen=' + $assen;
                tb_show('Bulk Edit', $url + '&width=800');
                return false;
            })
        })
    </script>
<?php
    return;
}

$sql = "select distinct(drug) as drug from ci_results
        where report_id in ({$ids}) ";

$results = $wpdb->get_results($sql);

if (count($assen) > 0) {
?>
    Edit: <?php echo implode(', ', $assen); ?>
<?php
}
?>
<form method="post" id="bulk_edit_form">

    <div class="wraptbl">
        <table class="wp-list-table widefat striped posts blue_tbl hover_tbl">
            <thead>
                <tr>
                    <th>Test</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($results as $row) {
                ?>
                    <tr>
                        <td><?php echo $row->drug ?></td>
                        <td>
                            <label><input type="radio" name="status[<?php echo $row->drug ?>]" value="" /> Blank</label>
                            <label><input type="radio" name="status[<?php echo $row->drug ?>]" value="NEG" /> NEG</label>
                            <label><input type="radio" name="status[<?php echo $row->drug ?>]" value="PEN" /> Pending</label>
                            <label><input type="radio" name="status[<?php echo $row->drug ?>]" value="same" checked /> Same</label>
                        </td>
                    </tr>
                <?php
                }
                ?>
            </tbody>
        </table>
    </div>

    <div class="frow">
        <label>Comment 1</label>
        <label><input type="radio" name="comment1" value="1" /> Yes </label>&nbsp;&nbsp;&nbsp;
        <label><input type="radio" name="comment1" value="0" /> No </label>&nbsp;&nbsp;&nbsp;
        <label><input type="radio" name="comment1" value="same" checked /> Same</label>
    </div>
    <div class="frow">
        <label>Comment 2</label>
        <label><input type="radio" name="comment2" value="1" /> Yes </label>&nbsp;&nbsp;&nbsp;
        <label><input type="radio" name="comment2" value="0" /> No </label>&nbsp;&nbsp;&nbsp;
        <label><input type="radio" name="comment2" value="same" checked /> Same</label>
    </div>
    <input type="submit" class="button fsave_button" name="save" value="Save" />
    <input type="submit" class="button-primary button fsave_button" name="save_approve" value="Save & Approve" />
    <input type="hidden" name="ids" value="<?php echo $ids ?>" />
    <input type="hidden" name="tr_action" value="ci_results_bulk_edit" />
    <br><br>
</form>
<script>
    jQuery(function($) {
        $('.fsave_button').click(function() {
            $this = $(this);
            $this.val('Saving...');
            $.ajax({
                data: $(this).parents('form').serialize() + '&type=' + $this.attr('name'),
                type: 'post',
                success: function(rs) {
                    if ($this.attr('name') == 'save_approve') {
                        $this.val('Saved & Approved');
                        <?php
                        foreach (explode(',', $ids) as $id) {
                        ?>
                            var $row = $('[data-id="<?php echo $id ?>"]');
                            console.log($row);
                            $('.approve_link', $row).after('<a href="#" class="pdf_link">PDF</a>');
                            $('.approve_link', $row).remove()
                            $('.edit_link', $row).after('<a href="#" class="reverse_link">Reverse</a>');
                            $('.edit_link', $row).remove();
                            $('.result_M', $row).html('C').addClass('result_C').removeClass('result_M');
                            $('.result_', $row).html('C').addClass('result_C').removeClass('result_');
                        <?php
                        }
                        ?>
                    } else {
                        $this.val('Saved');
                    }

                }
            })
            return false;
        })
    })
</script>