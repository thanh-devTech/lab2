<div>
    <form method="get" class="form_sale_report">
        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
        <input type="hidden" name="tab" value="<?php echo $tab; ?>" />
        <label>From: </label>
        <input type="text" name="date_from" id="from" value="<?php echo $date_from ?>" />
        <label>To: </label>
        <input type="text" name="date_to" id="to" value="<?php echo $date_to ?>" />

        <?php
        global $current_role, $blog_id;
        if (tr_current_role_in(array('administrator', 'AdminCIDW', 'administrator_p', 'clms')) && ($blog_id == 1)) {
            $labs = $wpdb->get_results("select lab_ID,lab_name from ci_labs where (lab_is_ref=0 or lab_is_ref=2) ");

            echo '<label>Lab: </label><select name="lab_id" style="margin-top:-3px">';
            foreach ($labs as $lab) {
                $selected = ($lab->lab_ID == $lab_id) ? 'selected' : '';
                echo '<option value="' . $lab->lab_ID . '" ' . $selected . '>' . $lab->lab_name . '</option>';
            }
            echo '</select>';
        } else {
        ?>
            <input type="hidden" name="lab_id" value="<?php echo CI_Base_Lab::get_current_lab_id() ?>">
        <?php
        }
        ?>
        <input type="submit" name="submit" id="export" onclick="form_sale_run_action();return false;" value="Run Report" class="button bsty6" />
        <input type="submit" id="submit_form" style="width:1px;height: 1px;padding: 0px;position: absolute;top:-1000px;">
        <input type="submit" name="export" value="Export" onclick="form_sale_report_action();return false;" class="button" />

        <?php if (!$not_use_search_by): ?>
            <div class="row">
                <?php
                $bys = array(
                    'date'  => 'Date',
                    'cli' => 'Clinic',
                    'doc' => 'Doctor'
                );
                if ($current_role == 'lab' || $current_role == 'administrator' || $current_role == 'administrator_p' || count($rep_ids) > 0) {
                    $bys['rep'] = 'Rep';
                }
                if ($current_role == 'lab' || $current_role == 'administrator' || $current_role == 'distributor') {
                    $bys['distributor'] = 'Distributor';
                }
                foreach ($bys as $vl => $txt) {
                ?>
                    <label><input type="radio" class="report_by" name="by" value="<?php echo $vl ?>" <?php checked($vl, $by) ?>> by <?php echo $txt ?></label>
                <?php
                }
                ?>
            </div>

            <div class="row search_by">
                <div id="search_by_cli" style="display: none;">
                    <label>Search by Clinic</label>
                    <input type="text" name="clinic" value="<?php echo isset($_REQUEST['clinic']) ? $_REQUEST['clinic'] : '' ?>" />
                </div>
                <div id="search_by_doc" style="display: none;">
                    <label>Search by Doctor</label>
                    <input type="text" name="doctor" value="<?php echo isset($_REQUEST['doctor']) ? $_REQUEST['doctor'] : '' ?>" />
                </div>
                <div id="search_by_rep" style="display: none;">
                    <label>Search by Rep</label>
                    <input type="text" name="rep" value="<?php echo isset($_REQUEST['rep']) ? $_REQUEST['rep'] : '' ?>" />
                </div>
                <div id="search_by_distributor" style="display: none;">
                    <label>Search by Distributor</label>
                    <input type="text" name="dis" value="<?php echo isset($_REQUEST['dis']) ? $_REQUEST['dis'] : '' ?>" />
                </div>
            </div>
        <?php endif; ?>
    </form>
    <?php Tr_Session_Class::show() ?>
    <script>
        function form_sale_report_action() {
            jQuery('#export').append('<input type="hidden" name="tr_action" class="tr_action" value="ci_export_sale_report_date">');
            jQuery('#submit_form').click();
        }

        function form_sale_run_action() {
            jQuery('.tr_action').remove();
            jQuery('#export').click();
        }
    </script>
</div>
<br><br>
<table class="report_tbl" data-count="<?php echo isset($count_rows) ? $count_rows : 0 ?>">
    <?php if (isset($_REQUEST['submit'])): ?>
        <thead>
            <tr>
                <th style="width:20px"></th>
                <th style="width:100px">Date</th>
                <th style="width:10%">Accession</th>
                <th style="width:10%">Status</th>
                <th style="width:150px">Patient Name</th>
                <th style="width:20%;">Clinic</th>
                <th style="width:11%">Doctor</th>
                <th style="width:11%">Distributor</th>
                <th>Collector</th>
            </tr>
        </thead>
    <?php endif; ?>
    <tbody>
        <?php
        $parents    = array();
        $html       = '';
        $parent_id  = 1;
        $total_ig   = 0;
        $resulted_c = 0;
        $count_child = 0;
        // var_dump($rows);exit;
        if (!isset($rows) || !is_array($rows)) $rows = array();
        $all_status = tr_pdd_get_batch_statuses(false, false, false, 'all');

        $parentfield = 'doc';
        if ($by == 'cli') {
            $parentfield = 'cli_clinic';
        } else if ($by == 'doc') {
            $parentfield = 'requesting_physician';
        } else if ($by == 'rep') {
            $parentfield = 'rep';
        } else if ($by == 'distributor') {
            $parentfield = 'distributor';
        }
        foreach ($rows as $i => $row) {
            $row->doc = date('Y-m-d', strtotime($row->doc));
            if (!in_array($row->{$parentfield}, $parents)) {
                $parents[] = $row->{$parentfield};
                $total_ig = 0;
                $resulted_c = 0;
                ob_start();
            }
            $row->batch_status = isset($all_status[$row->batch_status]) ? $all_status[$row->batch_status] : '';
            if ($row->has_results) {
                $row->batch_status = 'Resulted';
                if ($row->has_pending) {
                    $row->batch_status = 'Pending';
                }
            }

        ?>
            <tr class="childp">
                <td <?php if ($count_child == 0) echo 'style="width:20px"' ?>></td>
                <td <?php if ($count_child == 0) echo 'style="width:100px"' ?>><?php echo $row->doc; ?></td>
                <td <?php if ($count_child == 0) echo 'style="width:10%"' ?>><?php echo $row->assen ?></td>
                <td <?php if ($count_child == 0) echo 'style="width:10%"' ?>><?php echo $row->batch_status; ?></td>
                <td <?php if ($count_child == 0) echo 'style="width:150px"' ?>><?php echo $row->patient_name; ?></td>
                <td <?php if ($count_child == 0) echo 'style="width:20%"' ?>><?php echo $row->cli_clinic; ?></td>
                <td <?php if ($count_child == 0) echo 'style="width:11%"' ?>><?php echo $row->doc_first . ' ' . $row->doc_last; ?></td>
                <td <?php if ($count_child == 0) echo 'style="width:11%"' ?>><?php echo $row->distributor; ?></td>
                <td><?php echo $row->collector; ?></td>
            </tr>

            <?php

            $has_result = true;
            $next_row = isset($rows[$i + 1]) ? $rows[$i + 1] : '';
            $total_ig++;
            if ($row->batch_status == 'Resulted') {
                $resulted_c++;
            }


            if (is_object($next_row) && isset($next_row->doc)) {
                $next_row->doc = date('Y-m-d', strtotime($next_row->doc));
            }

            if (($next_row && !in_array($next_row->{$parentfield}, $parents) || $count_rows == $i + 1) && count($parents) > 0) {
                $html = ob_get_clean();
                if (empty($row->{$parentfield})) {
                    $row->{$parentfield} = 'Unknown';
                }
            ?>
                <tr class="parent" data-p="<?php echo $parent_id ?>">
                    <td><i></i></td>
                    <td colspan="2"><?php echo $row->{$parentfield}; ?></td>
                    <td>
                        <span title="Total" style="color:white"><?php echo $total_ig ?></span> &nbsp;&nbsp;
                        <span title="Resulted" style="color:#18ad18"><?php echo $resulted_c ?></span>
                    </td>
                    <td colspan="5"></td>
                </tr>
                <tr class="child p_<?php echo $parent_id ?>">
                    <td colspan="9" class="nopadding">
                        <table style="width: 100%;" class="report_tbl">
                            <?php echo $html ?>
                        </table>
                    </td>
                </tr>
        <?php
                $parent_id++;
            }
        }


        ?>

    </tbody>
</table>

<?php if (isset($_REQUEST['submit']) && !$has_result): ?>
    <div class="updated">
        <p>No Result Found</p>
    </div>
<?php endif; ?>