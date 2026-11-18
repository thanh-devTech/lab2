<?php
$labname  = CI_Base_Lab::get_current_lab_name();
$generated_on = date("l, F d, Y \\a\\t h:i A");


$inbound_percentages = isset($inbound_percentages) && is_array($inbound_percentages) ? $inbound_percentages : [];
$received_percentages = isset($received_percentages) && is_array($received_percentages) ? $received_percentages : [];
$in_process_percentages = isset($in_process_percentages) && is_array($in_process_percentages) ? $in_process_percentages : [];
$completed_percentages = isset($completed_percentages) && is_array($completed_percentages) ? $completed_percentages : [];

?>

<html>

<head>
  <title><?php echo $labname; ?> - Queue Composition Report</title>
  <link rel="stylesheet" type="text/css" href="<?php echo TRCPDD_URL . 'css/pdf.css'; ?>">
  <style>
    @page {
      margin: 0.8cm 0.6cm 0.8cm 0.6cm;
    }

    .report-title {
      font-size: 18px;
      margin: 0;
      font-weight: bold;
      color: black !important;
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

    .queue-details-table th {
      white-space: nowrap;
    }

    .center {
      text-align: center;
    }
  </style>
</head>

<body>
  <div class="header">
    <div class="logo"><?php echo strtoupper($labname); ?></div>
    <h1 class="report-title">Current Queue by Panel </h1>
    <div class="report-date">Generated on <?php echo $generated_on; ?></div>
  </div>

  <div class="section">
    <div class="section-title">Report Filters</div>
    <div class="filters">
      <div class="filter-row">
        <span class="filter-label">Report Type:</span>
        Real-time Queue Status
      </div>
      <div class="filter-row">
        <span class="filter-label">Test Type:</span>
        <?php echo !empty($selected_btype) ? strtoupper($selected_btype) : 'ALL'; ?>
      </div>
      <div class="filter-row">
        <span class="filter-label">Clinic:</span>
        All Clinics
      </div>
    </div>
  </div>

  <div class="section">
    <div class="section-title">Queue Summary</div>
    <div class="metrics-grid">
      <table>
        <tr>
          <td style="border-bottom: none" ;>
            <div class="metric-card">
              <div class="metric-label">Total Inbound</div>
              <div class="metric-value"><?php echo $system_totals['inbound']; ?></div>
            </div>
          </td>

          <td style="border-bottom: none" ;>
            <div class="metric-card">
              <div class="metric-label">Total Received</div>
              <div class="metric-value"><?php echo $system_totals['received_total']; ?></div>
            </div>
          </td>

          <td style="border-bottom: none" ;>
            <div class="metric-card">
              <div class="metric-label">In Process</div>
              <div class="metric-value"><?php echo $system_totals['in_processing']; ?></div>
            </div>
          </td>

          <td style="border-bottom: none" ;>
            <div class="metric-card">
              <div class="metric-label">Completed</div>
              <div class="metric-value"><?php echo $system_totals['completed']; ?></div>
            </div>
          </td>

        </tr>
      </table>
    </div>
  </div>

  <div class="section">
    <div class="section-title">Queue Details by Panel</div>
    <table class="queue-details-table">
      <thead>
        <tr>
          <th style="text-align: left; padding-left: 8px;">Panel</th>
          <th class="center">Inbound</th>
          <th class="center">%</th>
          <th class="center">Received</th>
          <th class="center">%</th>
          <th class="center">In Process</th>
          <th class="center">%</th>
          <th class="center">Completed</th>
          <th class="center">%</th>
        </tr>
      </thead>
      <tbody>
        <?php
        foreach ($panel_stats as $p) {
          $panel_id = $p['panel_id'] ?? '';
          $inbound_pct = (int) ($inbound_percentages[$panel_id] ?? 0);
          $received_pct = (int) ($received_percentages[$panel_id] ?? 0);
          $in_process_pct = (int) ($in_process_percentages[$panel_id] ?? 0);
          $completed_pct = (int) ($completed_percentages[$panel_id] ?? 0);
          $panel_export_name = !empty($p['panel_display_name']) ? $p['panel_display_name'] : $p['panel_name'];
        ?>
          <tr>
            <td style="text-align: left; padding-left: 8px;"><strong><?php echo $panel_export_name; ?></strong></td>
            <td class="center"><?php echo $p['inbound']; ?></td>
            <td class="center"><?php echo $inbound_pct; ?>%</td>
            <td class="center"><?php echo $p['received_total']; ?></td>
            <td class="center"><?php echo $received_pct; ?>%</td>
            <td class="center"><?php echo $p['in_processing']; ?></td>
            <td class="center"><?php echo $in_process_pct; ?>%</td>
            <td class="center"><?php echo $p['completed']; ?></td>
            <td class="center"><?php echo $completed_pct; ?>%</td>
          </tr>
        <?php
        }
        ?>
        <tr style="background-color: #f1f1f1; font-weight: bold;">
          <?php
          $system_inbound_pct = ((int) $system_totals['inbound'] > 0) ? 100 : 0;
          $system_received_pct = ((int) $system_totals['received_total'] > 0) ? 100 : 0;
          $system_processing_pct = ((int) $system_totals['in_processing'] > 0) ? 100 : 0;
          $system_completed_pct = ((int) $system_totals['completed'] > 0) ? 100 : 0;
          ?>
          <td style="text-align: left; padding-left: 8px;">Total</td>
          <td class="center"><?php echo $system_totals['inbound']; ?></td>
          <td class="center"><?php echo $system_inbound_pct; ?>%</td>
          <td class="center"><?php echo $system_totals['received_total']; ?></td>
          <td class="center"><?php echo $system_received_pct; ?>%</td>
          <td class="center"><?php echo $system_totals['in_processing']; ?></td>
          <td class="center"><?php echo $system_processing_pct; ?>%</td>
          <td class="center"><?php echo $system_totals['completed']; ?></td>
          <td class="center"><?php echo $system_completed_pct; ?>%</td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="footer">
    <p>This report was generated by the Ignite Laboratory Information System</p>
    <p style="font-size: 9px; margin-top: 5px;">File: <?php echo $outfile; ?></p>
  </div>

</body>

</html>