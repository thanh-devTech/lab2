<!DOCTYPE html>
<html>

<head>
  <title>
    <?php if ($view_by == 'type') { ?>
      Test Type Distribution & Positivity Rates
    <?php } else { ?>
      Panel Type Distribution & Positivity Rates
    <?php } ?>
  </title>
  <link rel="stylesheet" type="text/css" href="<?php echo TRCPDD_URL . 'css/pdf.css'; ?>">
</head>

<body>
  <div class="header">
    <h1 class="title">
      igniteABX -
      <?php if ($view_by == 'type') { ?>
        Test Type & Positivity Report
      <?php } else { ?>
        Test Panel & Positivity Report
      <?php } ?>
    </h1>
    <p class="subtitle">Generated: <?php echo $generated_on; ?></p>
    <span class="panel-label"><?php echo $current_lab_name; ?></span>
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
    <div class="filter-row">
      <span class="filter-label">View Mode:</span>
      <?php if ($view_by == 'type') { echo 'Type'; } else { echo 'Panel';} ?>
    </div>
  </div>
  <div class="summary">
    <h3>Executive Summary</h3>
    <p><strong>Total Samples Analyzed:</strong> <?php echo $total_samples; ?></p>
    <p><strong>Overall Positivity Rate:</strong> <span class="positive"><?php echo $total_positive; ?> (<?php echo $total_positive_rate; ?>%)</span></p>
    <p><strong>Total Positive Results:</strong> <span class="negative"><?php echo $total_positive; ?></span></p>
  </div>
  <table class="table">
    <thead>
      <tr>
        <th>Test Type</th>
        <th>Sample Count</th>
        <th>Distribution %</th>
        <th>Positive</th>
        <th>Negative</th>
        <th>Positivity Rate</th>
      </tr>
    </thead>
    <tbody>
      <?php
      foreach ($summary_data as $row) {
        $percentage = $row['total'] > 0 ? round($row['total'] / $total_samples * 100, 1) : 0;
      ?>
        <tr>
          <td><strong><?php echo $row['title']; ?></strong></td>
          <td><?php echo $row['total']; ?></td>
          <td><?php echo $percentage; ?>%</td>
          <td><?php echo $row['positive']; ?></td>
          <td><?php echo $row['negative']; ?></td>
          <td><?php echo $row['positive_pc']; ?>%</td>
        </tr>
      <?php
      }
      ?>
    </tbody>
  </table>
  <div class="footer">
    <p><strong>Ignite Laboratory Information System - igniteABX Test Type & Positivity Report</strong></p>
    <p>Aggregated testing data for authorized use in compliance monitoring, operational insight, and clinical trend analysis.</p>
  </div>
</body>

</html>