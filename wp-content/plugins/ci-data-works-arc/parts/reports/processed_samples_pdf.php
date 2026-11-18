<html>

<head>
    <title><?php echo CI_Base_Lab::get_current_lab_name(); ?> - Processed Samples Report</title>
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
        <h1 class="title">Processed Samples Report</h1>
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
                            <div class="filter-value"><?php echo $from_date . " to " .  $to_date; ?></div>
                        </td>
                    </tr>
                    <tr class="filter-row">
                        <td><span class="filter-label">Type:</span></td>
                        <td>
                            <div class="filter-value"><?php echo tr_pdd_get_typename($btype_selected); ?></div>
                        </td>
                    </tr>
                    <tr class="filter-row">
                        <td><span class="filter-label">Count Type:</span></td>
                        <td>
                            <div class="filter-value"><?php echo isset($date_types[$date_type]) ? $date_types[$date_type] : 'Received' ?></div>
                        </td>
                    </tr>
                    <tr class="filter-row">
                        <td><span class="filter-label">Clinic:</span></td>
                        <td>
                            <div class="filter-value"><?php echo $clinic_filter; ?></div>
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>
    </div>
    <div class="summary-grid">
        <table>
            <tbody>
                <tr>
                    <td style="border-bottom: none;">
                        <div class="summary-card">
                            <div class="summary-value"><?php echo $total_top['processed']; ?></div>
                            <div class="summary-label">Total Processed</div>
                        </div>
                    </td>
                    <td style="border-bottom: none;">
                        <div class="summary-card">
                            <div class="summary-value"><?php echo $total_top['main']; ?></div>
                            <div class="summary-label">In-House</div>
                        </div>
                    </td>
                    <td style="border-bottom: none;">
                        <div class="summary-card">
                            <div class="summary-value"><?php echo $total_top['rejected']; ?></div>
                            <div class="summary-label">Rejected</div>
                        </div>
                    </td>
                    <td style="border-bottom: none;">
                        <div class="summary-card">
                            <div class="summary-value"><?php echo $total_top['rejected_percent']; ?></div>
                            <div class="summary-label">Rejection Rate</div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Specimen Processing by Panel</div>
        <table>
            <thead>
                <tr>
                    <th>Panel</th>
                    <th>Total</th>
                    <th>% of Total</th>
                    <th>In-House</th>
                    <th>Referenced</th>
                    <th>Rejected</th>
                    <th>Rejected %</th>
                    <th>Rerun Rate</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>All Panels</strong></td>
                    <td><?php echo $summary_totals['processed']; ?></td>
                    <td></td>
                    <td><?php echo $summary_totals['main']; ?></td>
                    <td><?php echo $summary_totals['ref']; ?></td>
                    <td><?php echo $summary_totals['rejected']; ?></td>
                    <td><?php echo $summary_totals['rejected_percent']; ?></td>
                    <td><?php echo $summary_totals['rerun']; ?></td>
                </tr>

                <?php
                if ($cli_ID == -1) {
                    // Group by clinic
                    foreach ($report_clids as $cid) {
                        $cli = isset($clinics[$cid]) ? $clinics[$cid] : $inactive_clinics[$cid];

                        // Compute clinic-level totals
                        $totals = ['processed' => 0, 'main' => 0, 'ref' => 0, 'rejected' => 0, 'rerun' => 0];
                        $detail_rows = [];

                        foreach ($reports as $gid => $row) {
                            $parts = explode('_', $gid);
                            $rcliid = $parts[1] ?? null;
                            if ($rcliid != $cid) continue;

                            $rejected_percent  = $row['processed'] > 0 ? number_format(($row['rejected'] / $row['processed']) * 100, 2) . '%' : '0.00%';
                            $percent_of_total  = $total_processed  > 0 ? number_format(($row['processed'] / $total_processed)  * 100, 1) . '%' : '0.0%';

                            $totals['processed'] += $row['processed'];
                            $totals['main']      += $row['main'];
                            $totals['ref']       += $row['ref'];
                            $totals['rejected']  += $row['rejected'];
                            $totals['rerun']     += $row['rerun'];

                            $ref_by_labs = [];
                            foreach ($row['refs'] as $lid => $qty) {
                                if (!empty($all_labs[$lid])) {
                                    $ref_by_labs[] = $qty . $all_labs[$lid]->lab_dba_init;
                                }
                            }

                            $detail_rows[] = [
                                'row'              => $row,
                                'rejected_percent' => $rejected_percent,
                                'percent_of_total' => $percent_of_total,
                                'ref_by_labs'      => $ref_by_labs,
                            ];
                        }

                        $tot_rej_pct   = $totals['processed'] > 0 ? number_format(($totals['rejected'] / $totals['processed']) * 100, 2) . '%' : '0.00%';
                        $status_class  = $cli->cli_status == 0 ? 'clinic_status_0' : '';
                ?>
                        <!-- Clinic summary row -->
                        <tr class="<?php echo $status_class; ?>">
                            <td><strong><?php echo htmlspecialchars($cli->cli_clinic); ?></strong></td>
                            <td><?php echo $totals['processed']; ?></td>
                            <td></td>
                            <td><?php echo $totals['main']; ?></td>
                            <td><?php echo $totals['ref']; ?></td>
                            <td><?php echo $totals['rejected']; ?></td>
                            <td><?php echo $tot_rej_pct; ?></td>
                            <td><?php echo $totals['rerun']; ?></td>
                        </tr>

                        <!-- Panel detail rows for this clinic -->
                        <?php foreach ($detail_rows as $d): ?>
                            <tr class="processed-detail-row">
                                <td><?php echo htmlspecialchars($d['row']['panel']); ?></td>
                                <td><?php echo $d['row']['processed']; ?></td>
                                <td><?php echo $d['percent_of_total']; ?></td>
                                <td><?php echo $d['row']['main']; ?></td>
                                <td>
                                    <?php echo $d['row']['ref']; ?>
                                    <?php if (!empty($d['ref_by_labs'])): ?>
                                        &nbsp;&nbsp;<?php echo implode('&nbsp;&nbsp;', array_map('htmlspecialchars', $d['ref_by_labs'])); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $d['row']['rejected']; ?></td>
                                <td><?php echo $d['rejected_percent']; ?></td>
                                <td><?php echo $d['row']['rerun']; ?></td>
                            </tr>
                        <?php endforeach; ?>

                    <?php } // end foreach clinic
                } else {
                    foreach ($reports as $row) {
                        $row['percent']           = $total_processed > 0 ? number_format(($row['processed'] / $total_processed) * 100, 1) . '%' : '0%';
                        $row['rejected_percent']  = $row['processed']    > 0 ? number_format(($row['rejected']  / $row['processed'])  * 100, 2) . '%' : '0.00%';
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['panel']); ?></strong></td>
                            <td><?php echo $row['processed']; ?></td>
                            <td><?php echo $row['percent']; ?></td>
                            <td><?php echo $row['main']; ?></td>
                            <td><?php echo $row['ref']; ?></td>
                            <td><?php echo $row['rejected']; ?></td>
                            <td><?php echo $row['rejected_percent']; ?></td>
                            <td><?php echo $row['rerun']; ?></td>
                        </tr>
                <?php }
                } ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">12-Month Volume Trend</div>
        <table>
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Processed In-House</th>
                    <th>Referenced</th>
                    <th>Rejected</th>
                    <th>Rejected %</th>
                </tr>
            </thead>

            <tbody>
                <?php
                foreach ($chartVolumeData['labels'] as $i => $label) {
                    $processed  = $chartVolumeData['datasets'][0]['data'][$i] ?? 0;
                    $referenced = $chartVolumeData['datasets'][1]['data'][$i] ?? 0;
                    $rejected   = $chartVolumeData['datasets'][2]['data'][$i] ?? 0;
                    $vol_total  = $processed + $referenced + $rejected;
                    $rej_pct    = $vol_total > 0 ? number_format($rejected / $vol_total * 100, 2) . '%' : '0.00%';
                    echo "<tr>";
                    echo "<td>{$label}</td>";
                    echo "<td style='text-align: center;'>{$processed}</td>";
                    echo "<td style='text-align: center;'>{$referenced}</td>";
                    echo "<td style='text-align: center;'>{$rejected}</td>";
                    echo "<td style='text-align: center;'>{$rej_pct}</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
    <div class="section">
        <div class="section-title">Panel Volume Trends</div>
        <table>
            <thead>
                <tr>
                    <th>Month</th>
                    <?php
                    foreach ($chartDataLine['datasets'] as $row) {
                        echo "<th>{$row['label']}</th>";
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($chartDataLine['labels'] as $i => $label) {
                    echo "<tr>";
                    echo "<td>{$label}</td>";
                    foreach ($chartDataLine['datasets'] as $row) {
                        $panel_data = $row['data'][$i] ?? 0;
                        echo "<td style='text-align: center;'>{$panel_data}</td>";
                    }
                    echo "</tr>";
                }
                ?>
            </tbody>

        </table>
    </div>

    <div class="footer">
        <p style="margin-bottom: 12px;"><strong>Ignite Laboratory Information System</strong></p>
        <p>igniteLIS analytics reports are intended for authorized laboratory and administrative users. These reports provide aggregated operational data to support workflow monitoring, performance oversight, and applicable compliance or public-health reporting.</p>
    </div>

</body>

</html>