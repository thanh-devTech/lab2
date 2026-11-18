<?php
global $wpdb, $current_role;

$nextcron = wp_next_scheduled('ci_cron_clean_results');
if ($nextcron == 0 || $nextcron > time() + 360) {
    wp_schedule_single_event(time() + 180, 'ci_cron_clean_results', array(0));
}

wp_enqueue_script('jquery-ui-datepicker');

wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');
wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);


$date_from = isset($_REQUEST['date_from']) ? $_REQUEST['date_from'] : '';
$date_to = isset($_REQUEST['date_to']) ? $_REQUEST['date_to'] : '';
$onlyvalue = isset($_REQUEST['onlyvalue']);
$summary = isset($_REQUEST['summary']) || isset($_REQUEST['trajax']);
$search = isset($_REQUEST['s']) ? $_REQUEST['s'] : '';

$types = tr_pdd_get_batch_types();
if (!isset($_REQUEST['s'])) {
    $onlyvalue = true;
}
$where_ba = "";
$where_study = "";
$where_cli = "";

if (!empty($date_from)) {
    $where_ba .= " and ba.doc >= '{$date_from}' ";
    $where_study .= " and ba.doc >= '{$date_from}' ";
}

if (!empty($date_to)) {
    $where_ba .= " and ba.doc <= '{$date_to} 23:59:59' ";
    $where_study .= " and ba.doc <= '{$date_to} 23:59:59' ";
}

$conditions = trpdd_get_batch_conditions();

$where_ba .= $conditions['where'];

if ($conditions['check_author']) {
    $where_ba .= $return['where_author'];
}

if (!empty($search)) {
    $where_cli .= " and cli.cli_clinic like '%{$search}%' ";
}
$clinics = array();
$tabs = array();
if ($summary) {
    $child_query = array();
    foreach ($types as $btype) {
        $btable = tr_pdd_get_table_by_btype($btype);

        if ($btype == 'study') {
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
        } else {
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

        if (isset($_GET['debug'])) {
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

    if ($tabbype == 'study') {
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
    } else {
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


$time_start = microtime(true);


$q_count = microtime(true) - $time_start;

if (isset($_REQUEST['debug'])) {
    echo '$q_count: ' . $q_count, '<br><br><br>';
    echo '$sql: ' . $sql, '<br><br><br>';
}
?>
<style>

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


<style>
    h1,
    h2,
    h3,
    h4,
    h5,
    h6 {
        font-weight: unset !important;
        font-size: inherit;
        margin-bottom: 6px !important;
    }

    h1,
    h4 {
        margin: unset;
    }

    h1 {
        margin-bottom: 2px;
    }

    h4 {
        margin-bottom: 5px;
    }

    h5 {
        font-weight: var(--font-weight-medium);
        margin: 0px;
        --tw-font-weight: var(--font-weight-medium);
        font-size: 13px;
        margin-bottom: 10px;
    }

    .text-primary {
        color: var(--primary);
    }

    ol {
        list-style-type: none;
        margin: 0px;
        margin-top: px;
        padding-top: 5px;
        padding-left: 5px;
    }

    ol li a {
        color: unset;
    }


    .text-2xl {
        font-size: var(--text-2xl);
        line-height: var(--tw-leading, var(--text-2xl--line-height));
    }

    table .aright {
        text-align: right !important;
    }
</style>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?' . TRCPDD_VS ?>"></script>
<div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
    <div class="space-y-6">
        <div class="bg-white border rounded-lg px-3 sm:px-6 py-2 sm:py-3 shadow-sm">
            <nav aria-label="breadcrumb" data-slot="breadcrumb">
                <ol data-slot="breadcrumb-list"
                    class="text-muted-foreground flex items-center gap-1.5 text-sm break-words sm:gap-2.5 flex-wrap">

                    <li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5">
                        <a data-slot="breadcrumb-link"
                            class="hover:text-foreground transition-colors flex items-center gap-1 text-xs sm:text-sm"
                            href="?page=dashboard_main">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-house h-3 w-3" aria-hidden="true">
                                <path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"></path>
                                <path
                                    d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z">
                                </path>
                            </svg>
                            <span class="truncate max-w-[120px] sm:max-w-none">Dashboard</span>
                        </a>
                    </li>

                    <li data-slot="breadcrumb-separator" role="presentation" aria-hidden="true"
                        class="[&amp;&gt;svg]:size-3.5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-chevron-right">
                            <path d="m9 18 6-6-6-6"></path>
                        </svg>
                    </li>

                    <li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5">
                        <a data-slot="breadcrumb-link"
                            class="hover:text-foreground transition-colors flex items-center gap-1 text-xs sm:text-sm"
                            href="?page=ci_reports_main">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-file-text h-3 w-3" aria-hidden="true">
                                <path
                                    d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z">
                                </path>
                                <path d="M14 2v5a1 1 0 0 0 1 1h5"></path>
                                <path d="M10 9H8"></path>
                                <path d="M16 13H8"></path>
                                <path d="M16 17H8"></path>
                            </svg>
                            <span class="truncate max-w-[120px] sm:max-w-none">Reports</span>
                        </a>
                    </li>

                    <li data-slot="breadcrumb-separator" role="presentation" aria-hidden="true"
                        class="[&amp;&gt;svg]:size-3.5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-chevron-right">
                            <path d="m9 18 6-6-6-6"></path>
                        </svg>
                    </li>

                    <li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5"><span
                            data-slot="breadcrumb-page" role="link" aria-disabled="true" aria-current="page"
                            class="text-foreground font-normal flex items-center gap-1 text-xs sm:text-sm"><svg
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-chart-column h-3 w-3" aria-hidden="true">
                                <path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
                                <path d="M18 17V9"></path>
                                <path d="M13 17V5"></path>
                                <path d="M8 17v-3"></path>
                            </svg><span class="truncate max-w-[120px] sm:max-w-none">Volume Report</span></span></li>
                </ol>
            </nav>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">
                    Volume Report</h1>
                <p class="text-muted-foreground mt-1">Sample volume analysis by test panel and
                    clinic</p>
            </div>
        </div>
        <div>
            <div class="space-y-5">

                <div data-slot="card"
                    class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header"
                        class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 pb-3">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <h4 data-slot="card-title" class="leading-none flex items-center gap-2">
                                    <div class="p-1.5 bg-blue-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
                                            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-chart-column h-4 w-4 text-blue-600" aria-hidden="true">
                                            <path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
                                            <path d="M18 17V9"></path>
                                            <path d="M13 17V5"></path>
                                            <path d="M8 17v-3"></path>
                                        </svg></div>Volume by Test Panel
                                </h4>
                            </div>

                        </div>
                        <div class="flex flex-wrap items-center gap-3 mt-4">

                            <form method="get" style="margin-bottom:20px">
                                <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
                                <input type="hidden" name="rtab"
                                    value="<?php echo isset($_GET['rtab']) ? $_GET['rtab'] : '' ?>" />
                                <input type="hidden" name="tab"
                                    value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />
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

                                <label><input type="checkbox" name="onlyvalue" <?php checked($onlyvalue) ?> value="1" />
                                    Only with
                                    Volume</label>
                                <label><input type="checkbox" name="summary" <?php checked($summary) ?> value="1" />
                                    Summary</label>
                                <span class="onelinemobile">
                                    <input type="submit" class="button bsty6" value="Submit" />
                                    <?php if (isset($_REQUEST['s'])) { ?>
                                        <input type="submit" name="trajax" class="button" value="Export" />
                                    <?php } ?>
                                </span>
                            </form>
                        </div>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-6">
                        <div class="border rounded-lg overflow-hidden">
                            <div class="overflow-x-auto">
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
                                                foreach ((array) $clinics as $clinic) {

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
                                                        <td class="aright"><?php echo number_format($clinic->money_billed, 2) ?>
                                                        </td>
                                                        <td class="aright">
                                                            <?php echo number_format($clinic->money_received, 2) ?>
                                                        </td>
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
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-lg py-4 px-4">
                    <p class="text-xs text-gray-600 text-center">
                        <strong style="display: block;padding-bottom:3px;">Ignite Laboratory Information System</strong>
                        igniteLIS analytics reports are intended for authorized laboratory and administrative users. These reports provide aggregated operational data to support workflow monitoring, performance oversight, and applicable compliance or public-health reporting.
                    </p>
                </div>

            </div>
        </div>
    </div>
</div>