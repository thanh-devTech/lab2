<html>

<head>
    <title><?php echo CI_Base_Lab::get_current_lab_name(); ?> - Sample Delay Trends Report</title>
    <link rel="stylesheet" type="text/css" href="<?php echo TRCPDD_URL . 'css/pdf.css'; ?>">
    <style>
        .tiles-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-top: 15px;
        }

        .tile {
            background: white;
            padding: 18px 12px;
            border-radius: 6px;
            text-align: center;
        }

        .tile-value {
            font-size: 24px;
            font-weight: bold;
            color: #d83d00;
            margin-bottom: 8px;
        }

        .tile-label {
            font-size: 20px;
            color: #666;
        }

        .tile-type {
            font-size: 20px;
            color: #000;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .tile-comparison {
            font-size: 20px;
            color: #999;
            margin-top: 4px;
            font-weight: normal;
        }

        .tile-comparison .arrow {
            font-weight: 600;
        }

        .tile-comparison .arrow.down {
            color: #16a34a;
        }

        .tile-comparison .arrow.up {
            color: #dc2626;
        }

        .tile-month {
            font-size: 20px;
            color: #999;
            margin-left: 8px;
            font-weight: normal;
        }

        .current-month-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .avg-table th,
        .avg-table td {
            width: 33.33%;
            text-align: left;
        }

        .avg-table td.shaded-col {
            background: #f8f9fa;
        }

        .avg-table .count {
            font-size: 11px;
            color: #999;
            font-weight: normal;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1 class="title">Specimen Delay Trends Report</h1>
        <p class="subtitle">Generated: <?php echo $generated_on; ?></p>
        <span class="panel-label"><?php echo CI_Base_Lab::get_current_lab_name(); ?></span>
    </div>

    <div class="filters">
        <h3>Report Parameters</h3>
        <div class="filter-grid">
            <table class="filters-table">
                <tbody>
                    <tr class="filter-row">
                        <td style="width:20%"><span class="filter-label">Time Period:</span></td>
                        <td style="width:80%">
                            <div class="filter-value">Historical 12 Months (<?php echo $first_month ? $first_month : 'N/A' ?> - <?php echo $last_month ? $last_month : 'N/A' ?>)</div>
                        </td>
                    </tr>
                    <tr class="filter-row">
                        <td><span class="filter-label">Type:</span></td>
                        <td>
                            <div class="filter-value"><?php echo strtoupper($btype_selected); ?></div>
                        </td>
                    </tr>
                    <tr class="filter-row">
                        <td><span class="filter-label">Panels:</span></td>
                        <td>
                            <div class="filter-value"><?php echo $cur_name_filter_panel; ?></div>
                        </td>
                    </tr>
                    <tr class="filter-row">
                        <td><span class="filter-label">Clinic:</span></td>
                        <td>
                            <div class="filter-value"><?php echo $cur_name_clinic; ?></div>
                        </td>
                    </tr>
                    <tr class="filter-row">
                        <td><span class="filter-label">Status:</span></td>
                        <td>
                            <div class="filter-value"><?php echo $cur_name_status; ?></div>
                        </td>
                    </tr>
                    <tr class="filter-row">
                        <td><span class="filter-label">Bucket:</span></td>
                        <td>
                            <div class="filter-value"><?php echo $cur_name_bucket; ?></div>
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>
    </div>


    <div class="section">
        <div class="section-title">Current Month vs 12-Month Average Delay Rates</div>
        <div class="averages-grid" style="margin-top: 15px;">
            <?php if ($count_month > 0): ?>
                <table style="width:100%;border:0">
                    <tbody>
                        <tr>
                            <?php if($sel_status=='all'||$sel_status=='not_received'):?>
                            <td style="border-bottom:0px;padding-left:0">
                                <h3 style="color: #333; font-size: 14px; font-weight: bold; margin-bottom: 6px;">Not Received (Pre-Lab)</h3>
                                <table class="avg-table">
                                    <thead>
                                        <tr>
                                            <th style="width:18%">Time Bracket</th>
                                            <th style="width:42%">Current Month(<?php echo $last_month ?? 'N/A'; ?>)</th>
                                            <th style="width:40%">12-Month Average</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bucket_render as $key => $item) {
                                            $month_last = $month_last_grouped['not_received'];
                                            $avg_last = $month_last_grouped['pct_not_received'];
                                            $avg_12m = $avg['not_received_' . $key] ?? 0;
                                        ?>
                                            <tr>
                                                <?php if($key=='under_12'){ ?>
                                                <td><strong>&lt; 12 hours</strong></td>
                                                <?php }else{?>
                                                <td><strong><?php echo $item['title']; ?></strong></td>
                                                <?php } ?>
                                                <td class="shaded-col center"><?php echo $avg_last[$key] ? $avg_last[$key] : 0; ?>% <span class="count">(<?php echo $month_last[$key] ? $month_last[$key] : 0; ?>)</span></td>
                                                <td class="center"><?php echo $avg12['not_received'][$key]; ?>% <span class="count">(<?php echo $avg_12m; ?>)</span></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </td>
                            <?php endif;?>

                            <?php if($sel_status=='all'||$sel_status=='pending'):?>
                            <td style="border-bottom:0px;padding-right:0">
                                <h3 style="color: #333; font-size: 14px; font-weight: bold; margin-bottom: 6px;">Pending (In-Lab)</h3>

                                <table class="avg-table">
                                    <thead>
                                        <tr>
                                            <th style="width:18%">Time Bracket</th>
                                            <th style="width:42%">Current Month (<?php echo $last_month ?? 'N/A'; ?>)</th>
                                            <th style="width:40%">12-Month Average</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bucket_render as $key => $item) {
                                            $month_last = $month_last_grouped['pending'];
                                            $avg_last = $month_last_grouped['pct_pending'];
                                            $avg_12m = $avg['pending_' . $key] ?? 0;
                                        ?>
                                            <tr>
                                                <?php if($key=='under_12'){ ?>
                                                <td><strong>&lt; 12 hours</strong></td>
                                                <?php }else{?>
                                                <td><strong><?php echo $item['title']; ?></strong></td>
                                                <?php } ?>
                                                <td class="shaded-col center"><?php echo $avg_last[$key] ? $avg_last[$key] : 0; ?>% <span class="count">(<?php echo $month_last[$key] ? $month_last[$key] : 0; ?>)</span></td>
                                                <td class="center"><?php echo $avg12['pending'][$key]; ?>% <span class="count">(<?php echo $avg_12m; ?>)</span></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </td>
                            <?php endif;?>
                        </tr>
                    </tbody>
                </table>
            <?php else: ?>
                <table style="width:100%;border:0">
                    <tr>
                        <td style="text-align:center;width:100%;border:none;color: #666;">No Data Available</td>
                    </tr>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Specimens by Status &amp; Time Bracket (12 Months)</div>
        <div class="averages-grid" style="margin-top: 15px;">
            <?php if ($count_month > 0): ?>
                <table style="width:100%;border:0">
                    <tbody>
                        <tr>
                            <?php if($sel_status=='all'||$sel_status=='not_received'):?>
                            <td style="border-bottom:0px;padding-left:0">
                                <h3 style="color: #333; font-size: 14px; font-weight: bold; margin-bottom: 6px;">Not Received (Pre-Lab)</h3>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th class="total-col">Total</th>
                                            <th>&gt;72h</th>
                                            <th>48-72h</th>
                                            <th>24-48h</th>
                                            <th>12-24h</th>
                                            <?php if($is_include_12==1){?><th>&lt;12h</th><?php }?>
                                        </tr>
                                    </thead>
                                    <tbody>

                                        <tr style="background: #f8f9fa; border-bottom: 2px solid #d83d00;">
                                            <td><strong>Average</strong></td>
                                            <td class="total-col"><strong><?php echo $avg['total_recv']; ?></strong></td>
                                            <td><strong><?php echo $avg['not_received_over_72']; ?></strong></td>
                                            <td><strong><?php echo $avg['not_received_48_72']; ?></strong></td>
                                            <td><strong><?php echo $avg['not_received_24_48']; ?></strong></td>
                                            <td><strong><?php echo $avg['not_received_12_24']; ?></strong></td>
                                            <?php if($is_include_12==1){?><td><strong><?php echo $avg['not_received_under_12']; ?></strong></td><?php }?>
                                        </tr>

                                        <?php
                                        foreach ($count_by_months as $key => $item) {
                                            if($is_include_12==1){
                                                $total_recv = $item['not_received_over_72'] + $item['not_received_48_72'] + $item['not_received_24_48'] + $item['not_received_12_24'] + $item['not_received_under_12'];
                                            }else{
                                                $total_recv = $item['not_received_over_72'] + $item['not_received_48_72'] + $item['not_received_24_48'] + $item['not_received_12_24'];
                                            }
                                        ?>
                                            <tr>
                                                <td><strong><?php echo $item['month_label']; ?></strong>
                                                </td>
                                                <td class="total-col"><strong><?php echo $total_recv; ?></strong></td>
                                                <td><?php echo $item['not_received_over_72']; ?></td>
                                                <td><?php echo $item['not_received_48_72']; ?></td>
                                                <td><?php echo $item['not_received_24_48']; ?></td>
                                                <td><?php echo $item['not_received_12_24']; ?></td>
                                                <?php if($is_include_12==1){?><td><?php echo $item['not_received_under_12']; ?></td><?php }?>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </td>
                            <?php endif;?>

                            <?php if($sel_status=='all'||$sel_status=='pending'):?>
                            <td style="border-bottom:0px;padding-right:0;">
                                <h3 style="color: #333; font-size: 14px; font-weight: bold; margin-bottom: 6px;">Pending (In-Lab)</h3>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th class="total-col">Total</th>
                                            <th>&gt;72h</th>
                                            <th>48-72h</th>
                                            <th>24-48h</th>
                                            <th>12-24h</th>
                                            <?php if($is_include_12==1){?><th>&lt;12h</th><?php }?>
                                        </tr>
                                    </thead>
                                    <tbody>

                                        <tr style="background: #f8f9fa; border-bottom: 2px solid #208B84;">
                                            <td><strong>Average</strong></td>
                                            <td class="total-col"><strong><?php echo $avg['total_pending']; ?></strong></td>
                                            <td><strong><?php echo $avg['pending_over_72']; ?></strong></td>
                                            <td><strong><?php echo $avg['pending_48_72']; ?></strong></td>
                                            <td><strong><?php echo $avg['pending_24_48']; ?></strong></td>
                                            <td><strong><?php echo $avg['pending_12_24']; ?></strong></td>
                                            <?php if($is_include_12==1){?><td><strong><?php echo $avg['pending_under_12']; ?></strong></td><?php }?>
                                        </tr>


                                        <?php
                                        foreach ($count_by_months as $key => $item) {
                                            if($is_include_12==1){
                                                $total_pending = $item['pending_over_72'] + $item['pending_48_72'] + $item['pending_24_48'] + $item['pending_12_24'] + $item['pending_under_12'];
                                            }else{
                                                $total_pending = $item['pending_over_72'] + $item['pending_48_72'] + $item['pending_24_48'] + $item['pending_12_24'];
                                            }
                                        ?>
                                            <tr>
                                                <td><strong><?php echo $item['month_label']; ?></strong>
                                                </td>
                                                <td class="total-col"><strong><?php echo $total_pending; ?></strong></td>
                                                <td><?php echo $item['pending_over_72']; ?></td>
                                                <td><?php echo $item['pending_48_72']; ?></td>
                                                <td><?php echo $item['pending_24_48']; ?></td>
                                                <td><?php echo $item['pending_12_24']; ?></td>
                                                <?php if($is_include_12==1){?><td><?php echo $item['pending_under_12']; ?></td><?php }?>
                                            </tr>
                                        <?php } ?>

                                    </tbody>
                                </table>
                            </td>
                            <?php endif;?>
                        <tr>
                    </tbody>
                </table>
            <?php else: ?>
                <table>
                    <tr>
                        <td style="text-align:center;width:100%;border:none;color: #666;">No Data Available</td>
                    </tr>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Delay Rates by Status &amp; Time Bracket (12 Months)</div>
        <div class="averages-grid" style="margin-top: 15px;">
            <?php if ($count_month > 0): ?>
                <table>
                    <tbody>
                        <tr>
                            <?php if($sel_status=='all'||$sel_status=='not_received'):?>
                            <td style="border-bottom:0px;padding-left:0">
                                <h3 style="color: #333; font-size: 14px; font-weight: bold; margin-bottom: 6px;">Not Received (Pre-Lab)</h3>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>&gt;72h</th>
                                            <th>48-72h</th>
                                            <th>24-48h</th>
                                            <th>12-24h</th>
                                            <?php if($is_include_12==1){?><th>&lt;12h</th><?php }?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php

                                        $count = count($count_by_months);

                                        // Tính tổng và trung bình trước
                                        $total_over_72 = $total_48_72 = $total_24_48 = $total_12_24 = $total_under_12 = 0;

                                        foreach ($count_by_months as $item) {
                                            $total_over_72   += $item['pct_not_received_over_72'];
                                            $total_48_72     += $item['pct_not_received_48_72'];
                                            $total_24_48     += $item['pct_not_received_24_48'];
                                            $total_12_24     += $item['pct_not_received_12_24'];
                                            $total_under_12  += $item['pct_not_received_under_12'];
                                        }

                                        ?>
                                        <tr style="background: #f8f9fa; border-bottom: 2px solid #d83d00;">
                                            <td><strong>Average</strong></td>
                                            <td><strong><?php echo round($total_over_72 / $count, 2); ?>%</strong></td>
                                            <td><strong><?php echo round($total_48_72 / $count, 2); ?>%</strong></td>
                                            <td><strong><?php echo round($total_24_48 / $count, 2); ?>%</strong></td>
                                            <td><strong><?php echo round($total_12_24 / $count, 2); ?>%</strong></td>
                                           <?php if($is_include_12==1){?><td><strong><?php echo round($total_under_12 / $count, 2); ?>%</strong></td><?php } ?>
                                        </tr>
                                        <?php
                                        foreach ($count_by_months as $key => $item) {
                                        ?>
                                            <tr>
                                                <td><strong><?php echo $item['month_label']; ?></strong></td>
                                                <td><?php echo $item['pct_not_received_over_72']; ?>%</td>
                                                <td><?php echo $item['pct_not_received_48_72']; ?>%</td>
                                                <td><?php echo $item['pct_not_received_24_48']; ?>%</td>
                                                <td><?php echo $item['pct_not_received_12_24']; ?>%</td>
                                               <?php if($is_include_12==1){?> <td><?php echo $item['pct_not_received_under_12']; ?>%</td><?php }?>
                                            </tr>
                                        <?php } ?>

                                    </tbody>
                                </table>
                            </td>
                            <?php endif;?>

                            <?php if($sel_status=='all'||$sel_status=='pending'):?>
                            <td style="border-bottom:0px;padding-right:0;">
                                <h3 style="color: #333; font-size: 14px; font-weight: bold; margin-bottom: 6px;">Pending (In-Lab)</h3>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>&gt;72h</th>
                                            <th>48-72h</th>
                                            <th>24-48h</th>
                                            <th>12-24h</th>
                                            <?php if($is_include_12==1){?><th>&lt;12h</th><?php }?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php

                                        $count = count($count_by_months);

                                        // Tính tổng và trung bình trước
                                        $total_over_72 = $total_48_72 = $total_24_48 = $total_12_24 = $total_under_12 = 0;

                                        foreach ($count_by_months as $item) {
                                            $total_over_72   += $item['pct_pending_over_72'];
                                            $total_48_72     += $item['pct_pending_48_72'];
                                            $total_24_48     += $item['pct_pending_24_48'];
                                            $total_12_24     += $item['pct_pending_12_24'];
                                            $total_under_12  += $item['pct_pending_under_12'];
                                        }

                                        ?>
                                        <tr style="background: #f8f9fa; border-bottom: 2px solid #208B84;">
                                            <td><strong>Average</strong></td>
                                            <td><strong><?php echo round($total_over_72 / $count, 2); ?>%</strong></td>
                                            <td><strong><?php echo round($total_48_72 / $count, 2); ?>%</strong></td>
                                            <td><strong><?php echo round($total_24_48 / $count, 2); ?>%</strong></td>
                                            <td><strong><?php echo round($total_12_24 / $count, 2); ?>%</strong></td>
                                            <?php if($is_include_12==1){?><td><strong><?php echo round($total_under_12 / $count, 2); ?>%</strong></td><?php }?>
                                        </tr>

                                        <?php
                                        foreach ($count_by_months as $key => $item) {
                                        ?>
                                            <tr>
                                                <td><strong><?php echo $item['month_label']; ?></strong></td>
                                                <td><?php echo $item['pct_pending_over_72']; ?>%</td>
                                                <td><?php echo $item['pct_pending_48_72']; ?>%</td>
                                                <td><?php echo $item['pct_pending_24_48']; ?>%</td>
                                                <td><?php echo $item['pct_pending_12_24']; ?>%</td>
                                                <?php if($is_include_12==1){?><td><?php echo $item['pct_pending_under_12']; ?>%</td><?php }?>
                                            </tr>
                                        <?php } ?>

                                    </tbody>
                                </table>
                            </td>
                            <?php endif;?>
                        </tr>
                    </tbody>
                </table>
            <?php else: ?>
                <table>
                    <tr>
                        <td style="text-align:center;width:100%;border:none;color: #666;">No Data Available</td>
                    </tr>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Clinic Pre-Lab Performance by Time Bracket</div>
        <h3 style="color: #333; font-size: 14px; font-weight: bold; margin: 15px 0 6px 0;">Not Received (Pre-Lab)</h3>
        <table>
            <thead>
                <tr>
                    <th>Clinic</th>
                    <th>&gt;72h</th>
                    <th>48-72h</th>
                    <th>24-48h</th>
                    <th>12-24h</th>
                    <?php if($is_include_12==1){?><th>&lt;12h</th><?php }?>
                    <th>Under 24h Target (<?php echo $selected_clinic_month_label; ?>)</th>
                    <th>12-Month Avg</th>
                </tr>
            </thead>
            <tbody>

                <tr style="background: #f8f9fa; font-weight: bold; border-bottom: 2px solid #d83d00;">
                    <td>Average (All Specimens)</td>
                    <td style="text-align: center;"><?php echo $averages_clinic['pct_over_72']; ?>%</td>
                    <td style="text-align: center;"><?php echo $averages_clinic['pct_48_72']; ?>%</td>
                    <td style="text-align: center;"><?php echo $averages_clinic['pct_24_48']; ?>%</td>
                    <td style="text-align: center;"><?php echo $averages_clinic['pct_12_24']; ?>%</td>
                    <?php if($is_include_12==1){?><td style="text-align: center;"><?php echo $averages_clinic['pct_under_12']; ?>%</td><?php } ?>
                    <td style="text-align: center;"><?php echo $averages_clinic['pct_under_24']; ?>%</td>
                    <td style="text-align: center;"><?php echo $averages_clinic['avg_month_total']; ?>%</td>
                </tr>
                <?php
                foreach ($report_clinic as $item) {
                    $compare_under_24 = $item['compare_under_24'];
                    $compare_under_24_12m = $item['compare_under_24_12m'];
                ?>
                    <tr>
                        <td><strong><?php echo $item['name']; ?></strong></td>
                        <td style="text-align: center;"><?php echo $item['pct_over_72'] === null ? 'N/A' : $item['pct_over_72'].'%'; ?></td>
                        <td style="text-align: center;"><?php echo $item['pct_48_72'] === null ? 'N/A' : $item['pct_48_72'].'%'; ?></td>
                        <td style="text-align: center;"><?php echo $item['pct_24_48'] === null ? 'N/A' : $item['pct_24_48'].'%'; ?></td>
                        <td style="text-align: center;"><?php echo $item['pct_12_24'] === null ? 'N/A' : $item['pct_12_24'].'%'; ?></td>
                        <?php if($is_include_12==1){?><td style="text-align: center;"><?php echo $item['pct_under_12'] === null ? 'N/A' : $item['pct_under_12'].'%'; ?></td><?php }?>
                        <td style="text-align: center;"><?php echo $item['pct_under_24'] === null ? 'N/A' : $item['pct_under_24'].'%'; ?></td>
                        <td style="text-align: center;"><?php echo $item['avg_month_total'] === null ? 'N/A' : $item['avg_month_total'].'%'; ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <div class="footer">
        <p style="margin-bottom: 12px;"><strong>Ignite Laboratory Information System</strong></p>
        <p>igniteLIS analytics reports are intended for authorized laboratory and administrative users. These reports provide aggregated operational data to support workflow monitoring, performance oversight, and applicable compliance or public-health reporting.</p>
    </div>

</body>

</html>
