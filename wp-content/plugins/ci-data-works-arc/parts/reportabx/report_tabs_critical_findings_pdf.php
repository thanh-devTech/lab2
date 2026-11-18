<!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
  <title>Critical Findings Report</title>
  <link rel="stylesheet" type="text/css" href="<?php echo TRCPDD_URL . 'css/pdf.css'; ?>">
  <style>
   .findings-table th {
      background: #d83d00;
      color: white;
      font-weight: bold;
      text-align: center;
    }

    .findings-table td {
      text-align: center;
    }

    .critical-badge {
      background: #dc2626;
      color: white;
      padding: 3px 8px;
      border-radius: 4px;
      font-weight: bold;
      font-size: 11px;
      display: inline-block;
    }

    .high-badge {
      background: #f59e0b;
      color: white;
      padding: 3px 8px;
      border-radius: 4px;
      font-weight: bold;
      font-size: 11px;
      display: inline-block;
    }

    .zero-badge {
      background: #e5e7eb;
      color: #6b7280;
      padding: 3px 8px;
      border-radius: 4px;
      font-size: 11px;
      display: inline-block;
    }

    .trend-info {
      color: #6b7280;
      font-size: 10px;
      margin-left: 6px;
      font-weight: normal;
    }

    .total-cell {
      background: #fef3c7;
      font-weight: bold;
      color: #d83d00;
      font-size: 13px;
    }
  </style>
</head>

<body>
  <div class="header">
    <h1 class="title">igniteABX - Critical Findings Report</h1>
    <p class="subtitle">Generated:<?php echo $generated_on; ?></p>
  </div>

  <div class="filters">
    <h3>Report Parameters</h3>
    <div class="filter-row">
      <span class="filter-label">Time Period:</span>
      <span><?php echo $period_export; ?></span>
    </div>
    <div class="filter-row">
      <span class="filter-label">Clinic Filter:</span>
      <span><?php echo $cur_name_clinic; ?></span>
    </div>
  </div>

  <?php
  $totals = $critical_findings_summary['total_summary'];
  ?>

  <div class="table-container">
    <h3 class="table-title">Critical Findings by Clinic</h3>
    <table class="findings-table">
      <thead>
        <tr>
          <th style="text-align: left;">Clinic</th>
          <th>MRSA</th>
          <th>VRE</th>
          <th>ESBL</th>
          <th>CRE</th>
          <th>MDR AB</th>
          <th>MDR PA</th>
          <th style="width: 20px;">Total</th>
        </tr>
      </thead>

      <tbody>
        <?php
        foreach ($critical_findings_summary['table'] as $item) {
          $row = $item['data'];
          $data_cp = $item['trend'];
          if (intval($row['Total']) == 0)
            continue;
        ?>

          <tr>
            <td><strong><?php echo $item['clinic']; ?></strong></td>

            <td>
              <span class="<?php echo $row['MRSA'] > 0 ? "critical-badge" : "zero-badge"; ?>">
                <?php echo $row['MRSA']; ?>
              </span>
              <span class="trend-info">(prev: <?php echo $data_cp['MRSA']['diff']; ?>)</span>
            </td>

            <td>
              <span class="<?php echo $row['VRE'] > 0 ? "critical-badge" : "zero-badge"; ?>">
                <?php echo $row['VRE']; ?>
              </span>
              <span class="trend-info">(prev: <?php echo $data_cp['VRE']['diff']; ?>)</span>
            </td>
            <td>
              <span class="<?php echo $row['ESBL'] > 0 ? "high-badge" : "zero-badge"; ?>">
                <?php echo $row['ESBL']; ?>
              </span>
              <span class="trend-info">(prev: <?php echo $data_cp['ESBL']['diff']; ?>)</span>
            </td>

            <td>
              <span class="<?php echo $row['CRE'] > 0 ? "critical-badge" : "zero-badge"; ?>">
                <?php echo $row['CRE']; ?>
              </span>
              <span class="trend-info">(prev: <?php echo $data_cp['CRE']['diff']; ?>)</span>
            </td>

            <td>
              <span class="<?php echo $row['MDR_AB'] > 0 ? "critical-badge" : "zero-badge"; ?>">
                <?php echo $row['MDR_AB']; ?></span>
              <span class="trend-info">(prev: <?php echo $data_cp['MDR_AB']['diff']; ?>)</span>
            </td>

            <td>
              <span class="<?php echo $row['MDR_PA'] > 0 ? "high-badge" : "zero-badge"; ?>">
                <?php echo $row['MDR_PA']; ?></span>
              <span class="trend-info">(prev: <?php echo $data_cp['MDR_PA']['diff']; ?>)</span>
            </td>

            <td class="total-cell">
              <?php echo $row['Total']; ?>
            </td>
          </tr>

        <?php } ?>
        <tr style="background: #f3f4f6; font-weight: bold;">
          <td>TOTALS</td>
          <td><?php echo $totals['MRSA']; ?></td>
          <td><?php echo $totals['VRE']; ?></td>
          <td><?php echo $totals['ESBL']; ?></td>
          <td><?php echo $totals['CRE']; ?></td>
          <td><?php echo $totals['MDR_AB']; ?></td>
          <td><?php echo $totals['MDR_PA']; ?></td>
          <td class="total-cell"><?php echo $critical_findings_summary['total_all']; ?></td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="footer">
    <p><strong>Ignite Laboratory Information System - igniteABX Trends Reports</strong></p>
    <p>Aggregated testing data for authorized use in compliance monitoring, operational insight, and clinical trend
      analysis.</p>
    <p style="margin-top: 10px;">Report File: <?php echo $outfile; ?></p>
  </div>
</body>

</html>