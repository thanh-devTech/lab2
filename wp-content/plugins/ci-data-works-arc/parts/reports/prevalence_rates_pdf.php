<html>

<head>
    <title><?php echo CI_Base_Lab::get_current_lab_name(); ?> - Prevalence Rates Report</title>
    <link rel="stylesheet" type="text/css" href="<?php echo TRCPDD_URL . 'css/pdf.css'; ?>">
    <style>
        .panel-subtitle {
            color: #666;
            font-size: 12px;
            margin: 0 0 15px 0;
        }

        th {
            font-size: 10px;
        }

        td {
            font-size: 11px;
        }

        .header h1.title {
            font-size: 24px;
        }

        .filters h3 {
            margin-bottom: 16px;
        }

        .section {
            margin-bottom: 20px;
            page-break-inside: unset;
        }
    </style>
</head>

<?php

?>

<body>
    <div class="header">
        <h1 class="title">Prevalence Rates Report</h1>
        <p class="subtitle">Generated: <?php echo $generated_on; ?></p>
        <span class="panel-label"><?php echo $lab_name; ?></span>
    </div>

    <div class="filters">
        <h3>Report Parameters</h3>
        <div class="filter-grid">
            <table class="filters-table">
                <tbody>
                    <tr class="filter-row">
                        <td style="width:20%"><span class="filter-label">Panels:</span></td>
                        <td style="width:80%">
                            <div class="filter-value"><?php echo $cur_name_filter_panel; ?></div>
                        </td>
                    </tr>
                    <tr class="filter-row">
                        <td><span class="filter-label">Location:</span></td>
                        <td>
                            <div class="filter-value"><?php echo $cur_name_clinic; ?></div>
                        </td>
                    </tr>

                    <tr class="filter-row">
                        <td><span class="filter-label">Time Period:</span></td>
                        <td>
                            <div class="filter-value"><?php echo $label_date_range; ?> </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <?php foreach ($group_reports as $g) {
        $group_pos_neg = $group_pos_negs[$g['id']];
        if ($sel_panel == 'all' && (empty($g['tests']) && empty($g['subs']))) continue;
    ?>
        <div class="section">
            <h3 style="color: #333; font-size: 18px; font-weight: bold; margin: 0 0 5px 0;"><?php echo $g['name']; ?></h3>
            <p class="panel-subtitle"><?php echo $group_pos_neg['total'] ?? 0; ?> specimens • <?php echo $group_pos_neg['POS'] ?? 0; ?> positive • <?php echo $label_date_range; ?></p>
            <table>
                <thead>
                    <tr>
                        <th>TARGET</th>
                        <th style="text-align: right;">POSITIVES</th>
                        <th style="text-align: right;">PREVALENCE</th>
                        <th style="text-align: right;">V.LOW</th>
                        <th style="text-align: right;">LOW</th>
                        <th style="text-align: right;">MED</th>
                        <th style="text-align: right;">HIGH</th>
                        <th style="text-align: right;">V.HIGH</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($g['subs'])) {
                        foreach ($g['subs'] as $sub) {
                            $testCount = is_array($sub['tests']) ? count($sub['tests']) : 0;

                            if ($testCount === 0) {
                                continue;
                            }
                            $totalH  = 0;
                            $totalM  = 0;
                            $totalVH = 0;
                            $totalVL = 0;
                            $totalL  = 0;
                            $totalP  = 0;
                            $totalN  = 0;

                            $totalPositives = 0;
                            $totalTests     = 0;

                            foreach ($sub['tests'] as $test) {
                                $totalH  += $test['H']  ?? 0;
                                $totalM  += $test['M']  ?? 0;
                                $totalVH += $test['VH'] ?? 0;
                                $totalVL += $test['VL'] ?? 0;
                                $totalL  += $test['L']  ?? 0;
                                $totalP  += $test['P']  ?? 0;
                                $totalN  += $test['N']  ?? 0;
                            }

                            $totalPositives = $totalH + $totalM + $totalL + $totalVL + $totalVH + $totalP;
                            $totalTests     = $totalPositives + $totalN;

                            $percentH  = $totalPositives > 0 ? calculatePercentage($totalH,  $totalPositives) : '0';
                            $percentM  = $totalPositives > 0 ? calculatePercentage($totalM,  $totalPositives) : '0';
                            $percentL  = $totalPositives > 0 ? calculatePercentage($totalL,  $totalPositives) : '0';
                            $percentVL = $totalPositives > 0 ? calculatePercentage($totalVL, $totalPositives) : '0';
                            $percentVH = $totalPositives > 0 ? calculatePercentage($totalVH, $totalPositives) : '0';

                    ?>
                            <tr style="background: #e9ecef; font-weight: bold;">
                                <td><?php echo $sub['name'] ?></td>
                                <td style="text-align: right;"><?php echo $totalPositives ?></td>
                                <td style="text-align: right;">-</td>
                                <td style="text-align: right;"><?php echo renderCell($totalVL, $percentVL); ?></td>
                                <td style="text-align: right;"><?php echo renderCell($totalL,  $percentL);  ?></td>
                                <td style="text-align: right;"><?php echo renderCell($totalM,  $percentM);  ?></td>
                                <td style="text-align: right;"><?php echo renderCell($totalH,  $percentH);  ?></td>
                                <td style="text-align: right;"><?php echo renderCell($totalVH, $percentVH); ?></td>
                            </tr>
                            <?php foreach ($sub['tests'] as $name => $test) {
                                $totalPositives = 0;
                                $totalTests     = 0;

                                $totalPositives = $test['H'] + $test['M'] + $test['L'] + $test['VL'] + $test['VH'] + $test['P'];
                                $totalTests     = $totalPositives + $test['N'];

                                $positiveRate = calculatePercentage($totalPositives, $totalTests);

                                $percentH  = $totalPositives > 0 ? calculatePercentage($test['H'],  $totalPositives) : '0';
                                $percentM  = $totalPositives > 0 ? calculatePercentage($test['M'],  $totalPositives) : '0';
                                $percentL  = $totalPositives > 0 ? calculatePercentage($test['L'],  $totalPositives) : '0';
                                $percentVL = $totalPositives > 0 ? calculatePercentage($test['H'], $totalPositives) : '0';
                                $percentVH = $totalPositives > 0 ? calculatePercentage($test['VH'], $totalPositives) : '0';

                            ?>
                                <tr>
                                    <td>&nbsp;&nbsp;<?php echo $name ?></td>
                                    <td style="text-align: right;"><?php echo $totalPositives ?></td>
                                    <td style="text-align: right;"><?php echo $positiveRate ?>%</td>
                                    <td style="text-align: right;"><?php echo renderCell($test['VL'] ?? 0, $percentVL); ?></td>
                                    <td style="text-align: right;"><?php echo renderCell($test['L']  ?? 0, $percentL);  ?></td>
                                    <td style="text-align: right;"><?php echo renderCell($test['M']  ?? 0, $percentM);  ?></td>
                                    <td style="text-align: right;"><?php echo renderCell($test['H']  ?? 0, $percentH);  ?></td>
                                    <td style="text-align: right;"><?php echo renderCell($test['VH'] ?? 0, $percentVH); ?></td>
                                </tr>
                            <?php }
                        }
                    } else {
                        foreach ($g['tests'] as $name => $test) {
                            $totalPositives = 0;
                            $totalTests     = 0;

                            $totalPositives = $test['H'] + $test['M'] + $test['L'] + $test['VL'] + $test['VH'] + $test['P'];
                            $totalTests = $totalPositives + $test['N'];

                            $positiveRate = calculatePercentage($totalPositives, $totalTests);

                            $percentH  = $totalPositives > 0 ? calculatePercentage($test['H'],  $totalPositives) : '0';
                            $percentM  = $totalPositives > 0 ? calculatePercentage($test['M'],  $totalPositives) : '0';
                            $percentL  = $totalPositives > 0 ? calculatePercentage($test['L'],  $totalPositives) : '0';
                            $percentVL = $totalPositives > 0 ? calculatePercentage($test['H'], $totalPositives) : '0';
                            $percentVH = $totalPositives > 0 ? calculatePercentage($test['VH'], $totalPositives) : '0';
                            ?>
                            <tr>
                                <td>&nbsp;&nbsp;<?php echo $name ?></td>
                                <td style="text-align: right;"><?php echo $totalPositives ?></td>
                                <td style="text-align: right;"><?php echo $positiveRate ?>%</td>
                                <td style="text-align: right;"><?php echo renderCell($test['VL'] ?? 0, $percentVL); ?></td>
                                <td style="text-align: right;"><?php echo renderCell($test['L']  ?? 0, $percentL);  ?></td>
                                <td style="text-align: right;"><?php echo renderCell($test['M']  ?? 0, $percentM);  ?></td>
                                <td style="text-align: right;"><?php echo renderCell($test['H']  ?? 0, $percentH);  ?></td>
                                <td style="text-align: right;"><?php echo renderCell($test['VH'] ?? 0, $percentVH); ?></td>
                            </tr>
                    <?php }
                    } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>

    <div class="footer">
        <p style="margin-bottom: 12px;"><strong>Ignite Laboratory Information System</strong></p>
        <p>This report is intended for internal use by authorized laboratory personnel and administrators. The data contained herein supports operational monitoring, quality assurance, workflow optimization, and compliance reporting as applicable.</p>
    </div>

</body>

</html>