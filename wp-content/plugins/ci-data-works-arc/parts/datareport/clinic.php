<?php
global $wpdb, $current_role;

$nextcron = wp_next_scheduled('ci_cron_clean_results');
if ($nextcron == 0 || $nextcron > time() + 360) {
    wp_schedule_single_event(time() + 180, 'ci_cron_clean_results', array(0));
}

wp_enqueue_script('jquery-ui-datepicker');

wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');
wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);


$date_from  = isset($_REQUEST['date_from']) ? $_REQUEST['date_from'] : '';
$date_to    = isset($_REQUEST['date_to']) ? $_REQUEST['date_to'] : '';
$onlyvalue  = isset($_REQUEST['onlyvalue']);
$summary    = isset($_REQUEST['summary']) || isset($_REQUEST['trajax']);
$search     = isset($_REQUEST['s']) ? $_REQUEST['s'] : '';

$types = tr_pdd_get_batch_types();
if (!isset($_REQUEST['s'])) {
    $onlyvalue = true;
}
$where_ba   = "";
$where_study   = "";
$where_cli  = "";

if (!empty($date_from)) {
    $where_ba .= " and ba.doc >= '{$date_from}' ";
    $where_study .= " and ba.doc >= '{$date_from}' ";
}

if (!empty($date_to)) {
    $where_ba .= " and ba.doc <= '{$date_to} 23:59:59' ";
    $where_study .= " and ba.doc <= '{$date_to} 23:59:59' ";
}

$conditions = trpdd_get_batch_conditions();

$where_ba      .=  $conditions['where'];

if ($conditions['check_author']) {
    $where_ba      .= $return['where_author'];
}

if (!empty($search)) {
    $where_cli .= " and cli.cli_clinic like '%{$search}%' ";
}
$clinics    = array();
$tabs       = array();
if ($summary) {
    $child_query = array();
    foreach ($types as $btype) {
        $btable = tr_pdd_get_table_by_btype($btype);

        if($btype=='study'){
            $childq = "(select cli.ID as cID, 
            cli.project_name as cli_clinic,
            '' as cli_city, 
            '' as rep_first,
            '' as rep_last,
            count(ba.mid) as num_requests,
            '{$btype}' as btype
            from ci_study cli
            left join {$btable} ba ON ba.project_ID = cli.ID
            where cli.istatus = 1 {$where_cli} {$where_study} group by cli.ID
            ) ";
        }
        else{
            $childq = "(select cli.cID, 
            cli.cli_clinic,
            cli.cli_city, 
            rep.rep_first,
            rep.rep_last,
            count(ba.mid) as num_requests,
            '{$btype}' as btype
            from ci_clinic cli
            left join {$btable} ba ON ba.cli_ID = cli.cID
            left join ci_lab_reps rep ON cli.`cli_rep-ID` = rep.rep_ID
            where cli.cli_status = 1 {$where_cli} {$where_ba} group by cli.cID
            ) ";
        }
        $child_query[] = $childq;

        if(isset($_GET['debug'])){
            echo '<br><br>';
            echo $childq;
            echo '<br><br>';
        }
    }

    $child_query = implode(' UNION ALL ', $child_query);
    $sql = "select * from ({$child_query}) as ball order by cli_clinic asc";
    $rs = $wpdb->get_results($sql);
    $clinics = array();
    foreach ($rs as $r) {
        if ($onlyvalue && $r->num_requests == 0) {
            continue;
        }
        if (!isset($clinics[$r->cID])) {
            $clinics[$r->cID] = $r;
        }
        $clinics[$r->cID]->{$r->btype} = $r->num_requests;
    }

    if (isset($_REQUEST['trajax'])) {

        header("Content-disposition: attachment; filename=export.csv");
        header("Content-Type: text/csv");
        $output = fopen("php://output", 'w');
        $headers = array('Clinic', 'City');
        foreach ($types as $btype) {
            $headers[] = tr_pdd_get_typename($btype);
        }
        $headers[] = 'Total';
        $headers[] = 'Rep Name';
        fputcsv($output, $headers);
        foreach ($clinics as $clinic) {
            $row = array(
                $clinic->cli_clinic,
                $clinic->cli_city
            );
            $row_total = 0;
            foreach ($types as $btype) {
                $tcount = intval($clinic->{$btype});
                $row_total += $tcount;
                $row[] = $tcount;
            }
            $row[] = $row_total;
            $row[] = $clinic->rep_first . ' ' . $clinic->rep_last;
            fputcsv($output, $row);
        }
        @fclose($output);
        exit;
    }
} else {

    foreach ($types as $btype) {
        $tabs[$btype] = tr_pdd_get_typename($btype);
    }
    $tabbype = !empty($_REQUEST['rtab']) ? $_REQUEST['rtab'] : key($tabs);

    $batch_tbl = tr_pdd_get_table_by_btype($tabbype);
    $report_tbl = tr_pdd_get_table_report_by_btype($tabbype);

    if($tabbype=='study'){
        $sql = "select cli.ID as cID, 
        cli.project_name as cli_clinic,
        count(ba.mid) as num_requests,
        sum(if(re.id>0,1,0)) as num_results,
        sum(re.count_drugs) as num_tests
        from ci_study cli
        left join {$batch_tbl} ba on ba.project_ID = cli.ID
        left join {$report_tbl} re On ba.assen = re.patient_id
        where cli.istatus = 1 {$where_cli} {$where_study}
        group by cli.ID 
        order by project_name asc";
    }
    else{
        $sql = "select cli.cID, cli.cli_clinic,
        count(ba.mid) as num_requests,
        sum(if(re.id>0,1,0)) as num_results,
        sum(billed_amount) as money_billed,
        sum(bb.received_amount) as money_received,
        sum(re.count_drugs) as num_tests
        from ci_clinic cli
        left join {$batch_tbl} ba on ba.cli_ID = cli.cID
        left join {$report_tbl} re On ba.assen = re.patient_id
        left join ci_batch_billings bb ON (bb.claim_no!='' and bb.claim_no is not null and bb.claim_no = ba.claim_id)
                                                        or (bb.assen = ba.assen)
        where cli.cli_status = 1 {$where_cli} {$where_ba}
        group by cli.cID 
        order by cli_clinic asc";
    }

    if (isset($_REQUEST['s'])) {
        $clinics = $wpdb->get_results($sql);
    }
}


$time_start  = microtime(true);


$q_count = microtime(true) - $time_start;

if (isset($_REQUEST['debug'])) {

    echo '$q_count: ' . $q_count, '<br><br><br>';
    echo '$sql: ' . $sql, '<br><br><br>';
}
?>
<div class="wrap">
    <h2>Test Volumes Report</h2>
    <form method="get" style="margin-bottom:20px">
        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
        <input type="hidden" name="rtab" value="<?php echo isset($_GET['rtab']) ? $_GET['rtab'] : '' ?>" />
        <input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />
        <span class="onelinemobile">
            <label>From: </label>
            <input type="text" name="date_from" id="from" value="<?php echo $date_from ?>" />
        </span>
        <span class="onelinemobile">
            <label>To: </label>
            <input type="text" name="date_to" id="to" value="<?php echo $date_to ?>" />
        </span>
        <span class="onelinemobile">
            <label>Clinic</label>
            <input type="text" name="s" id="s" value="<?php echo $search ?>" />
        </span>

        <label><input type="checkbox" name="onlyvalue" <?php checked($onlyvalue) ?> value="1" /> Only with Volume</label>
        <label><input type="checkbox" name="summary" <?php checked($summary) ?> value="1" /> Summary</label>
        <span class="onelinemobile">
            <input type="submit" class="button bsty6" value="Submit" />
            <?php if (isset($_REQUEST['s'])) { ?>
                <input type="submit" name="trajax" class="button" value="Export" />
            <?php } ?>
        </span>
    </form>

    <?php
    if ($summary) {
    ?>
        <div class="wraptbl">
            <table class="report_tbl">
                <thead>
                    <tr>
                        <th>Clinic</th>
                        <th>City</th>
                        <?php
                        foreach ($types as $btype) {
                            echo '<th>' . tr_pdd_get_typename($btype) . ' Volume</th>';
                        }
                        ?>
                        <th>Total</th>
                        <th>Rep Name</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($clinics as $clinic) {
                    ?>
                        <tr class="childp">
                            <td><?php echo $clinic->cli_clinic ?></td>
                            <td><?php echo $clinic->cli_city ?></td>
                            <?php
                            $row_total = 0;
                            foreach ($types as $btype) {
                                $tcount = intval($clinic->{$btype});
                                $row_total += $tcount;
                                echo '<td>' . $tcount . '</td>';
                            }
                            ?>
                            <td><?php echo $row_total ?></td>
                            <td><?php echo $clinic->rep_first . ' ' . $clinic->rep_last ?></td>
                        </tr>
                    <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
    <?php
    } else if (isset($_REQUEST['s'])) {

        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $k => $txt) {
            $class = $tabbype == $k ? 'nav-tab-active' : '';
            $link = Tr_Base_Class_V6::link(array('rtab' => $k), false);
            echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
        }
        echo '</h2>';
    ?>
        <div class="wraptbl">
            <table class="report_tbl">
                <thead>
                    <tr>
                        <th style="width:3%"></th>
                        <th style="width:25%">Clinic</th>
                        <th class="aright">Number Of Requests</th>
                        <th class="aright">Number Of Results</th>
                        <th class="aright">Tests</th>
                        <th class="aright">Amount Billed</th>
                        <th class="aright">Amount Received</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $count_report = 0;
                    foreach ((array)$clinics as $clinic) {

                        if ($clinic->num_tests == 0) {
                            $clinic->num_results = 0;
                            $clinic->money_billed = 0;
                            $clinic->money_received = 0;
                        }

                        if ($onlyvalue && $clinic->num_results == 0) {
                            continue;
                        }
                        $count_report++;
                    ?>
                        <tr class="parent" data-p="<?php echo $clinic->cID ?>">
                            <td><i></i></td>
                            <td><?php echo $clinic->cli_clinic ?></td>
                            <td class="aright"><?php echo intval($clinic->num_requests) ?></td>
                            <td class="aright"><?php echo intval($clinic->num_results) ?></td>
                            <td class="aright"><?php echo intval($clinic->num_tests) ?></td>
                            <td class="aright"><?php echo number_format($clinic->money_billed, 2) ?></td>
                            <td class="aright"><?php echo number_format($clinic->money_received, 2) ?></td>
                        </tr>
                        <tr class="child p_<?php echo $clinic->cID ?>">
                            <td colspan="7" class="nopadding"></td>
                        </tr>
                    <?php
                    }

                    if ($count_report == 0) {
                    ?>
                        <tr class="childp">
                            <td colspan="8">No Results Found.</td>
                        </tr>
                    <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>

    <?php
    }
    ?>
</div>
<style>
    table .aright {
        text-align: right !important;
    }
</style>
<script>
    var $rtab = '<?php echo $tabbype ?>';
    jQuery(function($) {
        function trcpp_load_report($cli_ID) {
            var $obj = $('tr.p_' + $cli_ID + ' td');
            if ($obj.html() != '') {
                return;
            } else {
                $obj.html('<div class="loading center"></div>');
            }
            $.ajax({
                data: {
                    tr_action: 'ci_load_clinic_report_tests',
                    cli_ID: $cli_ID,
                    rtab: $rtab
                },
                type: 'post',
                success: function(rs) {
                    var $html = $(rs);
                    $obj.html($html);
                },
                error: function(c) {
                    trcpp_load_report($cli_ID);
                }
            })
        }
        $('tr.parent').click(function() {
            $('tr.child').hide();
            if ($(this).data('showed') != '1') {
                $('tr.p_' + $(this).data('p')).show();
                $('tr.parent').data('showed', '0');
                $(this).data('showed', '1');
                $(this).addClass('active');
                $('body,html').scrollTop($(this).offset().top - 50);
                trcpp_load_report($(this).data('p'));
            } else {
                $('tr.p_' + $(this).data('p')).hide();
                $(this).data('showed', '0');
                $(this).removeClass('active');
            }

        });
        $("#from").datepicker({
            changeMonth: true,
            changeYear: true,
            numberOfMonths: 1,
            dateFormat: 'yy-mm-dd',
            onClose: function(selectedDate) {
                $("#to").datepicker("option", "minDate", selectedDate);
            }
        });
        $("#to").datepicker({
            changeMonth: true,
            changeYear: true,
            numberOfMonths: 1,
            dateFormat: 'yy-mm-dd',
            onClose: function(selectedDate) {
                $("#from").datepicker("option", "maxDate", selectedDate);
            }
        });

    })
</script>

<?php
if (isset($_REQUEST['debug'])) {

    echo microtime(true) - $time_start;
}
