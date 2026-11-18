<?php
global $wpdb;


$datetime_now = date_i18n('Y-m-d H:i:s');

$user_id = get_current_user_id();
$hournow = date_i18n('H');
$today = date_i18n('Y-m-d');


if (isset($_REQUEST['today'])) {
    $today = $_REQUEST['today'];
}

$receiving_keys         = tr_get_option('ci_pdf_data', 'receiving_keys');
$print_zebra            = tr_get_option('ci_pdf_data', 'print_zebra');
$print_zebra_size       = tr_get_option('ci_pdf_data', 'print_zebra_size');
$print_2_labels         = tr_get_option('ci_pdf_data', 'receiving_print2');
$receiving_show_panel   = true;
$receiving_time_bg      = tr_get_option('ci_pdf_data', 'receiving_time_bg');
$show_stat              = tr_get_option('ci_pdf_data', 'enable_stat');



$sql_labs = "select * from ci_labs where 1 ";

$query_date = date('Y-m-d', strtotime($today) - 72 * 3600);
$sql_receiving = "select * from {$receiving_tbl} where re_type={$re_type} and re_today>='{$query_date}' ";
if (isset($_REQUEST['today'])) {
    $sql_receiving = "select * from {$receiving_tbl} where re_type={$re_type} and re_today='{$today}'";
}

if (isset($_GET['debug'])) {
    var_dump($sql_receiving);
}
if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();

    $sql_labs .= "and lab_ID in ( {$lab_str} ) ";
    $sql_receiving .= " and re_lab_ID in ( {$lab_str} ) ";
} else {
    $sql_labs .= " and lab_status = 1 ";
}

$sql_labs .= " order by lab_is_ref asc ";
$sql_receiving .= " order by re_ID asc";

$results = $wpdb->get_results($sql_receiving, ARRAY_A);


$labs = $wpdb->get_results($sql_labs, ARRAY_A);

if (count($results) == 0) {
    $results[] = array(
        're_today' => $today,
        're_lab_ID' => CI_Base_Lab::get_current_lab_id(),
        're_timing' => '72',
        're_priorty' => 'low',
        're_collector' => 'N/A',
    );
}

if (isset($_GET['debug'])) {
    var_dump($sql_receiving);
}

?>
<?php Tr_Session_Class::show() ?>


<div id="loading_form">
    <div class="loading"></div>
</div>



<div id="form_receiving" data-print2="<?php echo $print_2_labels ? 1 : 0 ?>" style="display: none;">

    <div class="wraptbl">
        <table class="wp-list-table widefat striped fixed posts blue_tbl hover_tbl report_tbl" id="rec_tbl_add">
            <thead class="sticky">
                <tr>
                    <th style="width:18px"></th>
                    <th style="width: 90px;">Today</th>
                    <th class="smaller">Tracking Number Barcode</th>
                    <th style="width:55px">Lab</th>
                    <th style="width:60px">Timing</th>
                    <th>Facility</th>
                    <th>Collector</th>
                    <th>Qty</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $i = 0;

                foreach ($results as $row) {
                    $re_ID = isset($row['re_ID']) ? $row['re_ID'] : 0;
                    $re_start_pos_str = '';

                    $sql = "select *
                    from {$re_batch_tbl} rb 
                    where rb.re_ID = '{$re_ID}'
                    order by position desc";

                    if (isset($_GET['debug'])) {
                        echo $sql;
                        echo '<br><br><br>';
                    }
                    $batches = $wpdb->get_results($sql);
                    $total_b = count($batches);
                    $row['re_practice'] = str_replace('\\', '', isset($row['re_practice']) ? $row['re_practice'] : '');
                ?>
                    <tr class="parent_c smaller <?php if ($i == 0) echo 'first' ?>" data-p="batch_<?php echo $i ?>">
                        <td><i></i></td>
                        <td><input type="text" data-fname="re_today" class="date" value="<?php echo $row['re_today'] ?>" readonly />
                            <input type="hidden" data-fname="re_ID" value="<?php echo isset($row['re_ID']) ? $row['re_ID'] : '' ?>" />
                        </td>
                        <td><input type="text" data-fname="re_tracking_number_barcode" value="<?php echo isset($row['re_tracking_number_barcode']) ? $row['re_tracking_number_barcode'] : '' ?>" required /> </td>
                        <td>
                            <select data-fname="re_lab_ID" required style="width:60px">
                                <option value="">Select Lab</option>
                                <?php foreach ($labs as $lab) {
                                    $selected = $lab['lab_ID'] == @$row['re_lab_ID'] || count($labs) == 1 ? 'selected' : '';
                                    echo '<option value="' . $lab['lab_ID'] . '" ' . $selected . '>' . $lab['lab_dba_init'] . '</option>';
                                } ?>
                            </select>
                        </td>
                        <td>
                            <?php tr_select_option('rec[' . $i . '][re_timing]', $timings, isset($row['re_timing']) ? $row['re_timing'] : '', array('attr' => 'data-fname="re_timing" ')); ?>
                        </td>
                        <td><input type="text" data-fname="re_practice" value="<?php echo stripslashes($row['re_practice']) ?>" required /> </td>
                        <td><input type="text" data-fname="re_collector" value="<?php echo stripslashes($row['re_collector']) ?>" required /> </td>
                        <td><?php echo $total_b?></td>

                    </tr>
                    <tr class="child <?php if ($i == 0) echo 'first_child' ?> p_batch_<?php echo $i ?>" data-id="<?php echo $i ?>">
                        <td colspan="<?php echo 8 ?>">

                            <input type="text" placeholder="Barcode" class="barcode_auto" style="width:300px">

                            <small>Press Enter to add</small>
                            &nbsp;&nbsp;&nbsp;
                            <label><input type="checkbox" class="autocodecb" value="1" /> Recruited</label>
                            &nbsp;&nbsp;
                            <label><input type="checkbox" class="autocodecb" value="2" /> Transfusion</label>
                            &nbsp;&nbsp;
                            <label><input type="checkbox" class="autocodecb" value="3" /> Bovine</label>
                            &nbsp;&nbsp;
                            <label><input type="checkbox" class="autocodecb" value="4" /> Sickle-Chex</label>
                            
                            <a class="button printall_btn bsty1" title="Exclude JVHL" style="margin-left:15px">Print All</a>
                            <div class="warning_duplicate"></div>

                            <table class="posts report_tbl batch_tbl subretbl" <?php echo $re_start_pos_str ?>>
                                <thead>
                                    <tr>
                                        <th style="width:20px"></th>
                                        <th style="width:20px">#</th>
                                        <th style="width:70px">Position</th>
                                        <th style="width:100px">Barcode</th>
                                        <th style="width:140px">DOC</th>
                                        <th></th>
                                        <th style="width:190px">Assigned to</th>
                                        <th style="width:140px" class="center">Qty Citrate</th>
                                        <th style="width:140px" class="center">Qty EDTA</th>
                                        <th style="width:140px" class="center">Qty SST tube</th>
                                        <th style="width:140px" class="center">Qty Slides</th>
                                        <th style="width:60px">Print Barcode</th>
                                        <th style="width:30px"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                   
                                    foreach ($batches as $ii => $batch) {
                                        $batch->number = $ii;
                                        tr_study_receiving_row($batch);
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                <?php
                    $i++;
                }
                ?>
            </tbody>
        </table>
    </div>

    <br>
    <a class="button add_new_row">+</a>
    <?php /*
    <input type="submit" class="button-primary" id="submit_btn" value="Submit"/>
        */ ?>
    <a class="button show_hide_all right">Show All</a>
</div>


<audio src="<?php echo TRCPDD_URL ?>audio/beep.mp3" id="beep_player" loop style="display:none"></audio>
<iframe id="reprint_frame" src="about:blank" style="display: none;"></iframe>

<?php if ($receiving_keys) { ?>
    <div id="dialog_warning" style="display: none;">
        The scanned in sample number is not starting with <?php echo implode(' or ', $receiving_keys) ?>
    </div>
<?php } ?>
<div id="dialog_warning_empty" style="display: none;"></div>
<style>
    .col-position input {
        width: 60px;
    }

    .show_all tr.child {
        display: table-row !important;
    }

    .spec_type label {
        white-space: nowrap;
        margin-right: 10px;
    }

    .spec_type label input[type=radio] {
        margin-right: 0;
    }

    th.smaller {
        font-size: 12px;
    }

    .smaller input[type="text"],
    .smaller input[type="number"],
    .smaller select {
        font-size: 12px;
    }

    .blue_tbl input[type="text"] {
        width: 100%;
    }

    .study_assigned {
        background: #fafafa;
        padding-bottom: 10px;
    }

    .study_assigned .frow:first-child {
        background: #666;
        color: #fff !important;
        line-height: 30px;
    }

    .study_assigned .item {
        padding-left: 10px;
        padding-right: 10px;
    }

    .addslide {
        position: absolute;
        margin-top: -40px;
        margin-left: -20px;
        cursor: pointer;
    }

    .addslide:hover {
        color: red;
    }

    #listslides .item {
        margin-bottom: 5px;
    }

    #listslides .item:after {
        content: '';
        clear: both;
        display: block;
    }
</style>

<script>
    var $hour_next_batch = <?php echo intval(TRPDD_HOUR_CHANGE_BATCH) ?>;
    var $new_id = <?php echo intval($i) ?>;
    var $use_zebra = <?php echo $print_zebra ? 'true' : 'false' ?>;
    var $use_zebra_size = '<?php echo !empty($print_zebra_size) ? $print_zebra_size : '2x1' ?>';
    var $add_doc_to_label = <?php echo tr_get_option('ci_pdf_data', 'barcode_doc') ? 'true' : 'false' ?>;
    var $disable_successful_print_popup = <?php echo tr_get_option('ci_pdf_data', 'disable_successful_print_popup') ? 'true' : 'false' ?>;
</script>
<?php if ($print_zebra) { ?>
    <script src="<?php echo TRCPDD_URL . 'js/BrowserPrint-3.0.216.min.js?v=' . TRCPDD_VS ?>" type="text/javascript"></script>
    <script src="<?php echo TRCPDD_URL . 'js/BrowserPrint-Zebra-1.0.216.min.js?v=' . TRCPDD_VS ?>" type="text/javascript"></script>
<?php } ?>

<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.css">
<script src="//cdnjs.cloudflare.com/ajax/libs/timepicker/1.3.5/jquery.timepicker.min.js"></script>

<script>
    var $today = '<?php echo $today ?>';
</script>

<script src="<?php echo TRCPDD_URL . 'js/printer.js?v=' . TRCPDD_VS ?>" type="text/javascript"></script>
<script src="<?php echo TRCPDD_URL . 'js/admin_receiving_study.js?v=' . TRCPDD_VS ?>" type="text/javascript"></script>
