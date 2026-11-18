<html>

<head>
  <title>Sample Volume Report</title>
  <link rel="stylesheet" type="text/css" href="<?php echo TRCPDD_URL . 'css/pdf.css'; ?>">
  <style>
    .header {text-align: left;}
    h2{color: #d83d00;font-size: 14px;margin: 20px 0 10px 0;}
  </style>
</head>

<body>
  <div class="header">
    <h1>Sample Volume Report</h1>
    <p><strong>Period:</strong> <?php echo $text_sel_date_rep; ?> | <strong>Generated:</strong>
      <?php echo $period_export; ?></p>
  </div>

  <div class="summary-grid">
    <table>
      <tbody>
        <tr>
          <td style="border-bottom: none;">
            <div class="summary-card">
              <div class="summary-value"><?php echo $totals['main']; ?></div>
              <div class="summary-label">Total Samples</div>
            </div>
          </td>
          <td style="border-bottom: none;">
            <div class="summary-card">
              <div class="summary-value"><?php echo $totals['processed']; ?></div>
              <div class="summary-label">Processed</div>
            </div>
          </td>
          <td style="border-bottom: none;">
            <div class="summary-card">
              <div class="summary-value"><?php echo $totals['rejected']; ?></div>
              <div class="summary-label">Rejected</div>
            </div>
          </td>
          <td style="border-bottom: none;">
            <div class="summary-card">
              <div class="summary-value"><?php echo $totals['rejected_percent']; ?></div>
              <div class="summary-label">Rejection Rate</div>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <h2>Volume by Test Panel</h2>
  <table>
    <thead>
      <tr>
        <th>Panel</th>
        <th>All Clinics</th>
        <th>Processed</th>
        <th>Referenced</th>
        <th>Rejected</th>
        <th>Rejected %</th>
      </tr>
    </thead>
    <tbody>

      <tr>
        <td><strong>All Clinics</strong></td>
        <td><?php echo $totals['clinics']; ?></td>
        <td><?php echo $totals['main']; ?></td>
        <td><?php echo $totals['processed']; ?></td>
        <td><?php echo $totals['rejected']; ?></td>
        <td><?php echo $totals['rejected_percent']; ?></td>
      </tr>
      <?php
      foreach ($all_panels as $panel_id => $panel_name) {
        $r = $reports[$panel_id];
        if ($r == null)
          continue;
        $rejected_percent = $r['processed'] > 0 ? $r['rejected'] / $r['processed'] * 100 : 0;
        $clinic_count = isset($r['clinics']) && is_array($r['clinics']) ? count($r['clinics']) : 0;
        ?>
        <tr>
          <td><strong><?php echo $r['panel']; ?></strong></td>
          <td><?php echo $clinic_count; ?></td>
          <td><?php echo $r['processed']; ?></td>
          <td><?php echo $r['ref']; ?></td>
          <td><?php echo $r['rejected']; ?></td>
          <td><?php echo number_format($rejected_percent, 2); ?>%</td>
        </tr>
      <?php } ?>
    </tbody>
  </table>

  <div class="footer">
    <p><strong>Ignite Laboratory Information System - Volume Reports</strong></p>
    <p>Generated on <?php echo $period_export; ?> for authorized laboratory use.</p>
  </div>

</body>

</html>