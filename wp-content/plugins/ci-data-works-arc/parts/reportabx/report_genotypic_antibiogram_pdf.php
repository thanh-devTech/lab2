<!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
  <title>igniteABX Molecular Resistance Summary</title>
  <link rel="stylesheet" type="text/css" href="<?php echo TRCPDD_URL . 'css/pdf.css'; ?>">
  <style>
    .subtitle {
      color: #6b7280;
      margin: 5px 0 10px 0;
    }

    .timestamp {
      color: #666;
      font-size: 14px;
      margin: 0 0 15px 0;
    }

    .system-badge {
      display: inline-block;
      background: #d83d00;
      color: white;
      padding: 8px 30px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: bold;
    }

    .filters {
      padding: 10px 20px;
      margin: 12px 0;
    }

    .filters h3 {
      color: #3c3d59;
      margin: 0 0 12px 0;
      font-size: 14px;
    }

    .filter-row {
      margin-bottom: 8px;
      font-size: 13px;
    }

    .filter-label {
      color: #666;
      margin-right: 8px;
    }

    table {
      margin: 12px 0;
      table-layout: fixed;
      font-weight: bold;
    }

    th {
      background-color: #f3f4f6;
      padding: 5px 2px;
      text-align: left;
      border-bottom: 2px solid #e5e7eb;
      color: #374151;
      word-wrap: break-word;
      font-size: 10px;
    }

    th.pathogen,
    td.pathogen {
      width: 18%;
    }

    th.count,
    td.samples {
      width: 4%;
    }

    td {
      padding: 4px 2px;
      border-bottom: 1px solid #e5e7eb;
      font-size: 10px;
    }

    td.pathogen {
      font-weight: bold;
      color: #1f2937;
    }

    td.value {
      text-align: center;
      font-weight: bold;
      color: #1f2937;
    }

    td.samples {
      text-align: center;
      background-color: #f9fafb;
      font-weight: bold;
      color: #1f2937;
    }

    .rate-low {
      background-color: #dcfce7;
    }

    .rate-medium-low {
      background-color: #f7fee7;
    }

    .rate-medium {
      background-color: #fff7ed;
    }

    .rate-high {
      background-color: #fee2e2;
    }

    .no-data {
      background-color: #fff;
      color: #9ca3af !important;
    }

    .legend {
      margin-top: 18px;
      padding: 12px 20px;
      background-color: #f9fafb;
      border-radius: 8px;
      font-size: 10px;
      page-break-inside: avoid;
    }

    .legend h3 {
      margin: 0 0 10px 0;
      font-size: 12px;
      color: #1f2937;
    }

    .legend-items {
      margin-bottom: 15px;
    }

    .legend-item {
      display: inline-block;
      margin-right: 20px;
      white-space: nowrap;
    }

    .legend-box {
      display: inline-block;
      width: 20px;
      height: 12px;
      border-radius: 2px;
      border: 1px solid #d1d5db;
      vertical-align: middle;
      margin-right: 5px;
    }

    .legend-note {
      font-size: 12px;
    }

    .disclaimer {
      margin-top: 10px;
      color: #6b7280;
      font-size: 12px;
      line-height: 1.5;
    }

    .footer {
      margin-top: 14px;
      padding-top: 10px;
      border-top: 1px solid #e5e7eb;
    }
  </style>
</head>

<body>
  <?php $molecular_rows = $genotypic_antibiogram['rows'] ?? array(); ?>
  <?php $molecular_columns = $genotypic_antibiogram['columns'] ?? array(); ?>
  <?php $labname = CI_Base_Lab::get_current_lab_name() ?: 'Laboratory'; ?>
  <div class="header">
    <h1 class="title">igniteABX Molecular Resistance Summary</h1>
    <p class="subtitle">Pathogen&ndash;Resistance Marker Co-Detection Rates</p>
    <p class="timestamp">Generated: <?php echo esc_html($generated_on); ?></p>
    <div class="system-badge"><?php echo esc_html($labname); ?></div>
  </div>

  <div class="filters">
    <h3>Report Parameters</h3>
    <div class="filter-row">
      <span class="filter-label">Time Period:</span>
      <span><?php echo esc_html($period_export); ?></span>
    </div>
    <div class="filter-row">
      <span class="filter-label">Clinic Filter:</span>
      <span><?php echo esc_html($cur_name_clinic); ?></span>
    </div>
  </div>

  <?php if (!empty($molecular_rows)) { ?>
    <table>
      <thead>
        <tr>
          <th class="pathogen">Pathogen</th>
          <th class="center count">n</th>
          <?php foreach ($molecular_columns as $column) { ?>
            <th class="center"><?php echo esc_html($column); ?></th>
          <?php } ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($molecular_rows as $row) { ?>
          <tr>
            <td class="pathogen"><?php echo esc_html($row['full_name'] ?? $row['name']); ?></td>
            <td class="samples"><?php echo intval($row['samples']); ?></td>
            <?php foreach ($molecular_columns as $column) { ?>
              <?php
              $cell = $row['values'][$column] ?? null;
              $rate = $cell && !empty($cell['applicable']) ? floatval($cell['resistant']) : null;
              $rate_class = 'no-data';
              if ($rate !== null) {
                $rate_class = $rate < 10 ? 'rate-low' : ($rate < 20 ? 'rate-medium-low' : ($rate < 40 ? 'rate-medium' : 'rate-high'));
              }
              ?>
              <td class="value <?php echo $rate_class; ?>"><?php echo $rate === null ? '-' : esc_html($rate . '%'); ?></td>
            <?php } ?>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  <?php } else { ?>
    <p>No molecular resistance summary data found for the selected filters.</p>
  <?php } ?>

  <div class="legend">
    <h3>Resistance Legend</h3>
    <div class="legend-items">
      <span class="legend-item"><span class="legend-box rate-low"></span>&lt;10%</span>
      <span class="legend-item"><span class="legend-box rate-medium-low"></span>10-19%</span>
      <span class="legend-item"><span class="legend-box rate-medium"></span>20-39%</span>
      <span class="legend-item"><span class="legend-box rate-high"></span>&ge;40%</span>
      <span class="legend-item"><span class="legend-box no-data"></span>No data</span>
    </div>
    <div class="legend-note">
      <strong>Note:</strong> BL Inhibitors = Beta-Lactamase Inhibitors
    </div>
    <div class="disclaimer">
      This table provides a molecular, marker-based view of resistance and is not equivalent to a culture-based susceptibility assessment. It summarizes how often antibiotic resistance markers were detected alongside each pathogen, providing an estimate of potential resistance to a given antibiotic class. Values reflect overall trends and do not predict susceptibility for an individual case. Incomplete marker coverage may underestimate resistance rates, and resistance markers cannot always be assigned to a specific organism in polymicrobial infections. The presence of a marker does not always result in phenotypic resistance, and absence of a detected marker does not guarantee susceptibility.
    </div>
  </div>


  <div class="footer">
    <p><strong>Ignite Laboratory Information System - igniteABX Molecular Resistance Summary</strong></p>
    <p>Aggregated testing data for authorized use in compliance monitoring, operational insight, and clinical trend
      analysis.</p>
  </div>

</body>

</html>
