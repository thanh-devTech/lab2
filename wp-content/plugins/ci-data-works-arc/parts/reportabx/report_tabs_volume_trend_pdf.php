<!DOCTYPE html>
<html>

<head>
  <title>Volume Trend Report</title>
  <link rel="stylesheet" type="text/css" href="<?php echo TRCPDD_URL . 'css/pdf.css'; ?>">
</head>

<body>
  <div class="header">
    <h1 class="title">igniteABX - Volume Trend Reportss</h1>
    <p class="subtitle">Generated: <?php echo $generated_on; ?></p>
  </div>

  <div class="filters">
    <h3>Filter Settings</h3>
    <div class="filter-row">
      <span class="filter-label">Period:</span>
      <?php echo $period_export; ?>
    </div>
    <div class="filter-row">
      <span class="filter-label">Clinic:</span>
      <?php echo $cur_name_clinic; ?>
    </div>
  </div>

  <?php
  $data = $chart2->monthdata;
  ?>

  <div class="summary">
    <h3>Executive Summary</h3>
    <p><strong>Total Samples:</strong> <?php echo ($total_p + $total_n); ?></p>
    <p><strong>Positive Results:</strong> <span class="positive"><?php echo $total_p; ?>
        (<?php echo $pcpos; ?>%)</span></p>
    <p><strong>Negative Results:</strong> <span class="negative"><?php echo $total_n; ?></span></p>
  </div>

  <table class="table">
    <thead>
      <tr>
        <th>Month</th>
        <th class="center">Total Samples</th>
        <th class="center">Negative</th>
        <th class="center">Positive</th>
        <th class="center">Positivity Rate</th>
      </tr>
    </thead>

    <tbody>

      <?php
      // Tính trung bình
      $sum_samples = 0;
      $sum_neg = 0;
      $sum_pos = 0;
      $count_months = count($chart2->monthdata);

      foreach ($chart2->monthdata as $index => $row) {
        $sum_samples += $row[5];
        $sum_neg     += $row[2];
        $sum_pos     += $row[1];
      ?>
        <tr>
          <td><strong><?php echo $row[0]; ?></strong></td>
          <td class="center"><?php echo $row[5]; ?></td>
          <td class="center negative"><?php echo $row[2]; ?></td>
          <td class="center positive"><?php echo $row[1]; ?></td>
          <td class="center"><?php echo $row[3]; ?>%</td>
        </tr>
      <?php
      }

      // Tính AVERAGE
      $avg_samples = $count_months > 0 ? round($sum_samples / $count_months) : 0;
      $avg_neg     = $count_months > 0 ? round($sum_neg / $count_months) : 0;
      $avg_pos     = $count_months > 0 ? round($sum_pos / $count_months) : 0;

      // Average positivity rate
      $avg_rate = $avg_samples > 0 ? round(($avg_pos / $avg_samples) * 100, 1) : 0;
      ?>

      <tr style="background:#f8f9fa; font-weight:bold;">
        <td><strong>Average</strong></td>
        <td class="center"><?php echo $avg_samples; ?></td>
        <td class="center negative"><?php echo $avg_neg; ?></td>
        <td class="center positive"><?php echo $avg_pos; ?></td>
        <td class="center"><?php echo $avg_rate; ?>%</td>
      </tr>

    </tbody>
  </table>
  <div class="footer">
    <p><strong>Ignite Laboratory Information System - igniteABX Trends Reports</strong></p>
    <p>Aggregated testing data for authorized use in compliance monitoring, operational insight, and clinical trend
      analysis.</p>
  </div>
</body>

</html>