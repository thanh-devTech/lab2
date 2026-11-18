<?php

/**
 * Template: TAT Export PDF
 * - $obj_tat (summary metrics)
 * - $tat_chart_data (trend 14 Days)
 * - $panels (panel list)
 * - $from, $to, $btype
 * - $where_global, $wpdb
 * - function tr_calc_panel_tat(...)
 */

$lab_id   = CI_Base_Lab::get_current_lab_id();
$labname  = $wpdb->get_var("SELECT lab_name FROM ci_labs WHERE lab_ID = {$lab_id} ");
$generated_on = date_i18n("l, F d, Y \\a\\t h:i A");
?>
<html>

<head>
  <title>Ignite Laboratory - TAT Report</title>
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

    .font_utf8 {
      font-family: "DejaVu Sans";
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
      width: 120px;
    }

    .metrics-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
      margin-bottom: 15px;
    }

    .metric-card {
      background: #f8f9fa;
      padding: 8px;
      border-radius: 4px;
      text-align: center;
    }

    .metric-value {
      font-size: 20px;
      font-weight: bold;
      color: #d83d00;
      line-height: 1.1;
    }

    .metric-label {
      font-size: 11px;
      color: #666;
      margin-bottom: 3px;
    }

    .metric-trend {
      font-size: 11px;
      margin-top: 3px;
    }

    .green {
      color: #10b981;
    }

    .red {
      color: #dc2626;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 6px;
      font-size: 12px;
    }

    th,
    td {
      padding: 6px 4px;
      text-align: left;
      border-bottom: 1px solid #ddd;
    }

    th {
      background: #f8f9fa;
      font-weight: bold;
      color: #333;
      font-size: 11px;
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
        margin: 8px;
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
    <h1 class="report-title">Turnaround Time Analysis Report</h1>
    <div class="report-date">Generated on <?php echo $generated_on; ?></div>
  </div>

  <div class="section">
    <div class="section-title">Report Filters</div>
    <div class="filters">
      <div class="filter-row">
        <span class="filter-label">Date Range:</span>
        <?php echo $from; ?> to <?php echo $to; ?>
      </div>
      <div class="filter-row">
        <span class="filter-label">TAT Measurement:</span>
        <?php echo strtoupper($tat_type); ?>
      </div>
      <div class="filter-row">
        <span class="filter-label">Test Type:</span>
        <?php echo strtoupper($btype); ?>
      </div>
      <div class="filter-row">
        <span class="filter-label">Test Panels:</span>
        <?php echo $panel_name; ?>
      </div>
      <div class="filter-row">
        <span class="filter-label">Clinic:</span>
        <?php echo $cid_label; ?>
      </div>
    </div>
  </div>

  <div class="section">
    <div class="section-title">Performance Summary <span style="font-size:11px; color:#333;">(Selected filters apply)</span></div>
    <div class="metrics-grid">
      <table>
        <tr>
          <td>
            <div class="metric-card">
              <div class="metric-label">Selected Period Average</div>
              <div class="metric-value"><?php echo $obj_tat['current']['value']; ?>h</div>
              <div class="metric-trend trend-down font_utf8 <?php echo $obj_tat['current']['color']; ?>"><?php echo $obj_tat['current']['compare']; ?></div>
            </div>
          </td>
          <td>
            <div class="metric-card">
              <div class="metric-label">14 Day Average</div>
              <div class="metric-value"><?php echo $obj_tat['average_14days']['value']; ?>h</div>
              <div class="metric-trend trend-up font_utf8 <?php echo $obj_tat['average_14days']['color']; ?>"><?php echo $obj_tat['average_14days']['compare']; ?></div>
            </div>
          </td>
          <td>
            <div class="metric-card">
              <div class="metric-label">7 Day Average</div>
              <div class="metric-value"><?php echo $obj_tat['average_7days']['value']; ?>h</div>
              <div class="metric-trend trend-down font_utf8 <?php echo $obj_tat['average_7days']['color']; ?>"><?php echo $obj_tat['average_7days']['compare']; ?></div>
            </div>
          </td>
        </tr>
      </table>
    </div>
  </div>

  <div class="section">
    <div class="section-title">Panel Performance Analysis</div>
    <table>
      <thead>
        <tr>
          <th>Panel Name</th>
          <th>TAT</th>
          <th>C2R</th>
          <th>R2R</th>
          <th>Target</th>
          <th>Performance</th>
          <th>Sample Count</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($results_report as $row): ?>
          <tr>
            <td><?php echo esc_html($row['panel']) ?></td>
            <td><?php echo $row['data']['tat'] ?>h</td>
            <td><?php echo $row['data']['c2r'] ?>h</td>
            <td><?php echo $row['data']['r2r'] ?>h</td>
            <td><?php echo $row['data']['target'] ?>h</td>
            <td class="<?php echo $row['data']['compare'] ?>"><?php echo $row['data']['performance'] ?></td>
            <td><?php echo $row['data']['samples'] ?></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>

  <div class="section">
    <div class="section-title"><?php echo $diff_days ?>-Day Trend Analysis <span style="font-size:11px; color:#333;">(Selected filters apply)</span></div>
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Collection to Received</th>
          <th>Received to Results</th>
          <th>Total TAT</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $trend_rows = [];
        foreach ($tat_chart_data['labels'] as $idx => $label) {
          $c2r = $tat_chart_data['datasets'][0]['data'][$idx];
          $r2r = $tat_chart_data['datasets'][1]['data'][$idx];
          $trend_rows[] = [
            'label' => $label,
            'c2r' => $c2r,
            'r2r' => $r2r,
            'tat' => round($c2r + $r2r, 1),
            'ts' => strtotime($label),
          ];
        }

        usort($trend_rows, function ($a, $b) {
          $a_ts = $a['ts'];
          $b_ts = $b['ts'];
          if ($a_ts && $b_ts) {
            if ($a_ts === $b_ts) {
              return strcmp((string)$a['label'], (string)$b['label']);
            }
            return $a_ts <=> $b_ts;
          }
          if ($a_ts && !$b_ts) {
            return -1;
          }
          if (!$a_ts && $b_ts) {
            return 1;
          }
          return strcmp((string)$a['label'], (string)$b['label']);
        });
        ?>
        <?php foreach ($trend_rows as $row): ?>
          <tr>
            <td><?php echo $row['label']; ?></td>
            <td><?php echo $row['c2r']; ?>h</td>
            <td><?php echo $row['r2r']; ?>h</td>
            <td><?php echo $row['tat']; ?>h</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="footer">
    <p>This report was generated by the Ignite Laboratory Information System</p>
  </div>
</body>

</html>