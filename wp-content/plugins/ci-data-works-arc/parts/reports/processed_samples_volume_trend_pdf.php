<html>

<head>
    <title>12-Month Volume Trend</title>
    <link rel="stylesheet" type="text/css" href="<?php echo TRCPDD_URL . 'css/pdf.css'; ?>">
</head>

<body>
    <div class="header">
        <h1 class="title">12-Month Volume Trend</h1>
        <p class="subtitle">Generated: <?php echo $generated_on; ?> &nbsp;|&nbsp; <?php echo $months_12_text; ?></p>
        <span class="panel-label"><?php echo CI_Base_Lab::get_current_lab_name(); ?></span>
    </div>

    <div class="section">
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
                <?php foreach ($chartVolumeData['labels'] as $i => $label): ?>
                    <?php
                    $_inhouse  = $chartVolumeData['datasets'][0]['data'][$i] ?? 0;
                    $_ref      = $chartVolumeData['datasets'][1]['data'][$i] ?? 0;
                    $_rejected = $chartVolumeData['datasets'][2]['data'][$i] ?? 0;
                    $_total    = $_inhouse + $_ref + $_rejected;
                    $_rej_pct  = $_total > 0 ? number_format($_rejected / $_total * 100, 2) . '%' : '0.00%';
                    ?>
                    <tr>
                        <td><?php echo $label; ?></td>
                        <td style="text-align:center"><?php echo $_inhouse; ?></td>
                        <td style="text-align:center"><?php echo $_ref; ?></td>
                        <td style="text-align:center"><?php echo $_rejected; ?></td>
                        <td style="text-align:center"><?php echo $_rej_pct; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="footer">
        <p><strong>Ignite Laboratory Information System</strong></p>
        <p>igniteLIS analytics reports are intended for authorized laboratory and administrative users.</p>
    </div>
</body>

</html>