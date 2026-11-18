<?php
global $wpdb;

wp_enqueue_script('jquery-ui', '', array('jquery'));
wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');

$date       = isset($_REQUEST['date']) ? trim($_REQUEST['date']) : '';
$assen      = isset($_REQUEST['assen']) ? trim($_REQUEST['assen']) : '';
$carr       = isset($_REQUEST['carr']) ? trim($_REQUEST['carr']) : '';
$results    = array();

if ($act == 'change_employee') {
    $return = array('status' => 'ok');
    $mid = $_REQUEST['mid'];
    $employee = $_REQUEST['employee'] ? 1 : 0;
    list($batch_tbl, $mid, $btype) = tr_pdd_get_table_mid_by_id($mid);
    $wpdb->update($batch_tbl, array('is_employee' => $employee), array('mid' => $mid));

    wp_send_json($return);
} else if (!empty($date)) {

    $sql = "select re.*,lab.lab_dba_init as lab_name
	from ci_receiving re
	JOIN ci_labs as lab On lab.lab_ID = re.re_lab_ID
	where re.re_type = 0 ";

    $sql .= " and re.re_today='{$date}'  ";

    if($carr == 'fedex')
    {
        $sql.= " and re_tracking_number_barcode like '96%' ";
    }
    else if($carr == 'ups'){
        $sql.= " and (
            LENGTH(re_tracking_number_barcode)= 12
            OR LENGTH(re_tracking_number_barcode)= 9
            OR (LENGTH(re_tracking_number_barcode)= 16 AND re_tracking_number_barcode like '1Z%')
            OR (LENGTH(re_tracking_number_barcode)= 10 AND re_tracking_number_barcode like 'T%')
        ) ";
    }else if($carr == 'usps'){
        $sql.= " and (
            (re_tracking_number_barcode like '420%')
            OR (re_tracking_number_barcode like '91%')
            OR (re_tracking_number_barcode like '71%')
            OR (re_tracking_number_barcode like '92%')
        ) ";
    }
    else if($carr == 'courier'){
        $sql.= " and re_tracking_number_barcode not like '96%' ";
        $sql.= " and NOT (
            LENGTH(re_tracking_number_barcode)= 12
            OR LENGTH(re_tracking_number_barcode)= 9
            OR (LENGTH(re_tracking_number_barcode)= 16 AND re_tracking_number_barcode like '1Z%')
            OR (LENGTH(re_tracking_number_barcode)= 10 AND re_tracking_number_barcode like 'T%')
        ) ";
        $sql.= " and NOT (
            (re_tracking_number_barcode like '420%')
            OR (re_tracking_number_barcode like '91%')
            OR (re_tracking_number_barcode like '71%')
            OR (re_tracking_number_barcode like '92%')
        ) ";
    }


    if (CI_Base_Lab::has_user_labs()) {
        $lab_str = CI_Base_Lab::get_user_lab_ids_str();
        $sql .= " and re_lab_ID in ({$lab_str}) ";
    }

    if (isset($_GET['debug'])) {
        echo $sql;
    }
    $results = $wpdb->get_results($sql, ARRAY_A);
}

?>
<br>
<form method="get">
    <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
    <input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
    <label>Date:</label>
    <input type="search" class="hasdate" autocomplete="off" data-write="1" name="date" required value="<?php echo $date ?>" />
    <select name="carr" class="njs_select2">
        <?php
        $arrs = array(
            '' => 'All',
            'fedex' => 'FedEx',
            'ups' => 'UPS',
            'usps' => 'USPS',
            'courier' => 'Courier'
        );
        foreach ($arrs as $k => $vl) {
            $selected = isset($_REQUEST['carr']) && $_REQUEST['carr'] == $k ? 'selected' : '';
            echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
        }
        ?>
    </select>
    <input type="submit" class="button bsty6" value="Search" />
</form>

<br>
<div class="wraptbl">
    <table class="wp-list-table widefat striped fixed posts blue_tbl hover_tbl report_tbl" id="rec_tbl_add">
        <thead>
            <tr>
                <th>Tracking Number Barcode</th>
                <th style="width:40px">Lab</th>
                <th style="width:50px">Priority</th>
                <th style="width:50px">Timing</th>
                <th>Practice</th>
                <th style="width:100px">Collector</th>
                <th class="smaller" style="width:45px">Qty<br>Urine</th>
                <th class="smaller" style="width:45px">Qty<br>Oral</th>
                <th class="smaller" style="width:45px">Qty<br>JVHL</th>
                <th class="smaller" style="width:45px">Qty<br>JVHL-O</th>
                <th class="smaller" style="width:45px">Qty<br>Blood</th>
                <th class="smaller" style="width:45px">Qty<br>PGx</th>
                <th class="smaller" style="width:45px">Qty<br>rPCR</th>
                <th class="smaller" style="width:45px">Qty<br>NF-PCR</th>
                <th class="smaller" style="width:45px">Qty<br>PCR</th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($results as $i => $row) {
                $re_ID = $row['re_ID'];
                $child_sql = array();
                foreach (tr_pdd_get_batch_types() as $btype) {
                    $batch_tbl = tr_pdd_get_table_by_btype($btype);

                    $child_sql[] = "(select ba.mid,ba.type_of_specimen,ba.initials1,ba.ref_from_lab_ID,ba.position,ba.assen,ba.doc,ba.patient_name,
                                    ba.requesting_practice,ba.requesting_physician,ba.panel_tests_requested,ba.culture,ba.dob,ba.is_employee,
                                    rb.btype,rb.created
                                    from {$batch_tbl} ba
                                        JOIN ci_receiving_batches rb ON rb.mid=ba.mid and rb.btype='{$btype}'
                                        where rb.re_ID = '{$re_ID}' ) ";
                }

                $child_sql = implode(' UNION ALL ', $child_sql);

                $sql = "select * from ({$child_sql}) as ball
                                order by ball.position desc";

                $batches = $wpdb->get_results($sql);
                if (empty($batches)) continue;
                $has_content = false;
                ob_start();
            ?>
                <tr class="parent_c" data-p="batch_<?php echo $i ?>">
                    <td><?php echo $row['re_tracking_number_barcode'] ?></td>
                    <td><?php echo $row['lab_name'] ?></td>
                    <td><?php echo $priorities[$row['re_priority']] ?></td>
                    <td><?php echo $timings[$row['re_timing']] ?></td>
                    <td><?php echo $row['re_practice'] ?></td>
                    <td><?php echo $row['re_collector'] ?></td>
                    <td><?php echo $row['re_qty_urine'] ?></td>
                    <td><?php echo $row['re_qty_oral'] ?></td>
                    <td><?php echo $row['re_qty_jvhl'] ?></td>
                    <td><?php echo $row['re_qty_jvhlo'] ?></td>
                    <td><?php echo $row['re_qty_blood'] ?></td>
                    <td><?php echo $row['re_qty_dna'] ?></td>
                    <td><?php echo $row['re_qty_rpcr'] ?></td>
                    <td><?php echo $row['re_qty_nfpcr'] ?></td>
                    <td><?php echo $row['re_qty_pcr'] ?></td>
                </tr>
                <tr class="child  p_batch_<?php echo $i ?>" data-id="<?php echo $i ?>">
                    <td class="nopadding" colspan="15">
                        <table class="posts report_tbl">
                            <thead>
                                <tr>
                                    <th style="width:20px">#</th>
                                    <th style="width:70px">Position</th>
                                    <th style="width:100px">Barcode</th>
                                    <th style="width:80px">DOC</th>
                                    <th>Patient Name</th>
                                    <th>Office</th>
                                    <th>Doctor</th>
                                    <th>Employee</th>
                                </tr>
                            </thead>
                            <?php
                            foreach ($batches as $batch) {
                                $class = '';

                                /*
                                $assen_num = preg_replace("/[^\d]/", '', $batch->assen);
                                $lengthnum = strlen($batch->assen);
                                $type = 'courier';
                                if (strpos($assen_num, '96') === 0) {
                                    $type = 'fedex';
                                } else if ($lengthnum == 12 || $lengthnum == 9) {
                                    $type = 'ups';
                                } else if ($lengthnum == 16 && stripos($batch->assen, '1Z') === 0) {
                                    $type = 'ups';
                                } else if ($lengthnum == 10 && stripos($batch->assen, 'T') === 0) {
                                    $type = 'ups';
                                }

                                if (!empty($carr) && $type != $carr) {
                                    continue;
                                }
                                */
                                $batch->mid = $batch->btype . '_' . $batch->mid;
                            ?>
                                <tr data-mid="<?php echo $batch->mid ?>" class="childp assen_<?php echo $batch->assen . ' ' . $class ?>">
                                    <td><?php echo $batch->assen ?></td>
                                    <td><?php echo $batch->position ?></td>
                                    <td><?php echo $batch->assen ?></td>
                                    <td><?php echo $batch->doc ?></td>
                                    <td><?php echo $batch->patient_name ?></td>
                                    <td><?php echo $batch->requesting_practice ?></td>
                                    <td><?php echo $batch->requesting_physician ?></td>
                                    <td>
                                        <input type="checkbox" class="employee" <?php checked($batch->is_employee) ?> />
                                    </td>
                                </tr>
                            <?php
                                $has_content = true;
                            }
                            ?>
                        </table>
                    </td>
                </tr>
            <?php
                $content_tbl = ob_get_clean();
                if ($has_content) {
                    echo $content_tbl;
                }
            }
            ?>
        </tbody>
    </table>
</div>

<style>
    .report_tbl tbody tr.found_assen.childp td {
        background: #ffffe0 !important;
    }
</style>
<script>
    jQuery(function($) {
        $('body').on('click', 'tr.parent_c', function(e) {
            if (e.target.tagName != 'TD' && e.target.tagName != 'TR' && e.target.tagName != 'I') return;
            $('tr.child').hide();
            if ($(this).data('showed') != '1') {
                $('tr.p_' + $(this).data('p')).show();
                $('tr.parent_c').data('showed', '0');
                $(this).data('showed', '1');
                $(this).addClass('active');
            } else {
                $('tr.p_' + $(this).data('p')).hide();
                $(this).data('showed', '0');
                $(this).removeClass('active');
            }
            return false;
        }).on('click', 'input.employee', function() {
            var $checked = $(this).is(':checked');
            var $tr = $(this).parents('tr.childp');
            var $mid = $tr.data('mid');
            var $this = $(this);
            $this.attr('disabled', true);
            $.ajax({
                type: 'post',
                data: {
                    trajax: 1,
                    act: 'change_employee',
                    mid: $mid,
                    employee: $checked
                },
                dataType: 'json',
                success: function(json) {
                    if (json.status == 'ok') {
                        $this.removeAttr('disabled');
                    }
                },
                error: function() {

                }
            })
        })
    })
</script>