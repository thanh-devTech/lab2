<?php

/**
 * Template: Daily Export PDF
 * - $obj_tat (summary metrics)
 * - $tat_chart_data (trend 14 Days)
 * - $panels (panel list)
 * - $from, $to, $btype
 * - $where_global, $wpdb
 * - function tr_calc_panel_tat(...)
 */

$labname  = CI_Base_Lab::get_current_lab_name();
$generated_on = date_i18n("l, F d, Y \\a\\t h:i A");
?>

<html>

<head>
  <title>Ignite Laboratory - Daily Sample Volume Report</title>
  <style>
    @page {
      margin: 0.8cm 0.6cm 0.8cm 0.6cm;
    }

    body {
      font-family: Arial, sans-serif;
      margin: 0px;
      color: #333;
      font-size: 13px;
      line-height: 1.3;
    }

    .header {
      text-align: center;
      border-bottom: 1.5px solid #d83d00;
      padding-bottom: 12px;
      margin-bottom: 18px;
    }

    .logo {
      color: #d83d00;
      font-size: 20px;
      font-weight: bold;
      margin-bottom: 6px;
    }

    .report-title {
      font-size: 18px;
      margin: 0;
      font-weight: bold;
    }

    .report-date {
      color: #666;
      font-size: 12px;
      margin-top: 4px;
    }

    .section {
      margin-bottom: 18px;
    }

    .section-title {
      color: #d83d00;
      font-size: 14px;
      font-weight: bold;
      margin-bottom: 8px;
      border-bottom: 1px solid #ddd;
      padding-bottom: 3px;
    }

    .filters {
      background: #f8f9fa;
      padding: 10px;
      border-radius: 4px;
      margin-bottom: 12px;
    }

    .filter-row {
      margin-bottom: 4px;
      font-size: 12px;
    }

    .filter-label {
      font-weight: bold;
      display: inline-block;
      width: 90px;
    }

    .metrics-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
      gap: 8px;
      margin-bottom: 15px;
    }

    .metric-card {
      background: #f8f9fa;
      padding: 8px;
      border-radius: 4px;
      text-align: center;
    }

    .metric-value {
      font-size: 18px;
      font-weight: bold;
      color: #d83d00;
      line-height: 1.1;
    }

    .metric-label {
      font-size: 11px;
      color: #666;
      margin-bottom: 3px;
    }

    .metric-sub {
      font-size: 10px;
      color: #666;
      margin-top: 2px;
    }

    .table-title {
      font-size: 13px;
      font-weight: bold;
      margin-bottom: 6px;
      color: #333;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 6px;
      font-size: 11px;
    }

    th,
    td {
      padding: 4px 3px;
      text-align: center;
      border-bottom: 1px solid #ddd;
    }

    th {
      background: #f8f9fa;
      font-weight: bold;
      color: #333;
      font-size: 10px;
    }

    .status-good {
      color: #10b981;
    }

    .status-warning {
      color: #f59e0b;
    }

    .status-poor {
      color: #dc2626;
    }

    table th.selectedpanel,
    table td.selectedpanel {
      background: #fff7ee;
    }

    .footer {
      margin-top: 20px;
      padding-top: 10px;
      border-top: 1px solid #ddd;
      text-align: center;
      font-size: 11px;
      color: #666;
    }

    @media print {
      body {
        margin: 0px;
        font-size: 12px;
      }

      .no-print {
        display: none;
      }
    }
  </style>
</head>

<body>
  <div class="header">
    <div class="logo"><?php echo strtoupper($labname); ?></div>
    <h1 class="report-title">Daily Sample Volume Analysis Report</h1>
    <div class="report-date">Generated on <?php echo $generated_on; ?></div>
  </div>

  <div class="section">
    <div class="section-title">Report Filters</div>
    <div class="filters">
      <div class="filter-row">
        <span class="filter-label">Time Period:</span>
        <?php echo $report_time_label ?>
      </div>
      <div class="filter-row">
        <span class="filter-label">Test Type:</span>
        <?php echo strtoupper($btype); ?>
      </div>
      <div class="filter-row">
        <span class="filter-label">Test Panels:</span>
        <?php echo $panel_label; ?>
      </div>
      <div class="filter-row">
        <span class="filter-label">Clinic:</span>
        <?php echo $cid_label; ?>
      </div>
    </div>
  </div>

  <div class="section">
    <div class="section-title">Volume Summary</div>
    <div class="metrics-grid">
      <table>
        <tr>
          <td>
            <div class="metric-card">
              <div class="metric-label">Total Samples</div>
              <div class="metric-value">
                <?php echo number_format($summary['total_samples']); ?>
              </div>
            </div>
          </td>

          <td>
            <div class="metric-card">
              <div class="metric-label">Average Daily</div>
              <div class="metric-value">
                <?php echo number_format($summary['average_daily'], 1); ?>
              </div>
            </div>
          </td>

          <td>
            <div class="metric-card">
              <div class="metric-label">Success Rate</div>
              <div class="metric-value">
                <?php echo number_format($summary['success_rate'], 1); ?>%
              </div>
            </div>
          </td>

          <td>
            <div class="metric-card">
              <div class="metric-label">Rejection Rate</div>
              <div class="metric-value">
                <?php echo number_format($summary['rejection_rate'], 1); ?>%
              </div>
            </div>
          </td>
        </tr>
      </table>
    </div>
  </div>


  <div class="section">
    <div class="section-title">Daily Volume by Panel Type</div>

    <table>
      <thead>
        <tr>
          <th>Date</th>
          <?php foreach ($panel_distribution as $pd): ?>
            <th class="<?php echo $pd['selected'] ? 'selectedpanel' : '' ?>"><?php echo esc_html($pd['short_name']); ?></th>
          <?php endforeach; ?>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>
        <!-- dòng Total -->
        <tr style="font-weight:bold; background:#f8f9fa;">
          <td>Total</td>
          <?php foreach ($panel_distribution as $pd): ?>
            <td class="<?php echo $pd['selected'] ? 'selectedpanel' : '' ?>"><?php echo number_format($pd['count']); ?></td>
          <?php endforeach; ?>
          <td><?php echo number_format($summary['total_all_panels']); ?></td>
        </tr>

        <!-- dòng % -->
        <tr style="font-style:italic; color:#555;">
          <td>%</td>
          <?php foreach ($panel_distribution as $pd): ?>
            <td class="<?php echo $pd['selected'] ? 'selectedpanel' : '' ?>"><?php echo $pd['percent']; ?>%</td>
          <?php endforeach; ?>
          <td>100%</td>
        </tr>

        <!-- dòng Daily Avg -->
        <tr style="color:#0d6efd;">
          <td>Daily Avg</td>
          <?php foreach ($panel_distribution as $pd): ?>
            <td class="<?php echo $pd['selected'] ? 'selectedpanel' : '' ?>"><?php echo $pd['daily_avg']; ?></td>
          <?php endforeach; ?>
          <td><?php echo $summary['average_daily_all_panel']; ?></td>
        </tr>

        <!-- dữ liệu chi tiết từng ngày -->
        <?php foreach ($daily_panel_rows as $row): ?>
          <tr>
            <td><?php echo $row['date']; ?></td>
            <?php foreach ($panel_distribution as $pd): ?>
              <td class="<?php echo $pd['selected']  ? 'selectedpanel' : '' ?>">
                <?php echo $row['panels'][$pd['short_name']] ?? 0; ?>
              </td>
            <?php endforeach; ?>
            <td><strong><?php echo $row['total']; ?></strong></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>


  <div class="section">
    <div class="section-title">Daily Volume Breakdown</div>
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Processed</th>
          <th>Rejected</th>
          <th>In Process</th>
          <th>Total</th>
        </tr>
      </thead>

      <tbody>
        <?php
        foreach ($daily_sample_volumes['labels'] as $i => $label) {
          $processed = $daily_sample_volumes['datasets'][0]['data'][$i] ?? 0;
          $rejected = $daily_sample_volumes['datasets'][1]['data'][$i] ?? 0;
          $in_process = $daily_sample_volumes['datasets'][2]['data'][$i] ?? 0;
          $total = $processed + $rejected + $in_process;

          echo "<tr>";
          echo "<td>{$label}</td>";
          echo "<td class='status-good'>{$processed}</td>";
          echo "<td class='status-poor'>{$rejected}</td>";
          echo "<td>{$in_process}</td>";
          echo "<td><strong>{$total}</strong></td>";
          echo "</tr>";
        }
        ?>
      </tbody>
    </table>
  </div>

  <div class="footer">
    <p>This report was generated by the Ignite Laboratory Information System</p>
  </div>

</body>

</html>