<html>

<head>
    <title>Panel Volume Trends</title>
    <link rel="stylesheet" type="text/css" href="<?php echo TRCPDD_URL . 'css/pdf.css'; ?>">
    <style>
        table {
            font-size: 10px;
        }

        th,
        td {
            padding: 4px 6px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1 class="title">Panel Volume Trends</h1>
        <p class="subtitle">Generated: <?php echo $generated_on; ?></p>
        <span class="panel-label"><?php echo CI_Base_Lab::get_current_lab_name(); ?></span>
    </div>

    <div class="section">
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
        <p><strong>Ignite Laboratory Information System</strong></p>
        <p>igniteLIS analytics reports are intended for authorized laboratory and administrative users.</p>
    </div>
</body>

</html>