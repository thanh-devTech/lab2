<!DOCTYPE html>
<html>

<head>
  <title>igniteABX All Panels Report - All Clinics</title>
  <link rel="stylesheet" type="text/css" href="<?php echo TRCPDD_URL . 'css/pdf.css'; ?>">
  <style>
    .findings-table th {
      background: #d83d00;
      color: white;
      font-weight: bold;
      text-align: center;
      border: none
    }

    .findings-table td {
      text-align: center;
      border-left: none;
      border-right: none;
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
<?php
$totals = $critical_findings_summary['total_summary'];
$total_clinic = 0;
foreach ($critical_findings_summary['table'] as $item) {
  if (intval($item['data']['Total']) > 0)
    $total_clinic += 1;
}
?>

<body>
  <div class="header">
    <h1 class="title">igniteABX <?php echo ($view_by == 'type' ? 'All Types' : 'All Panels'); ?> Comprehensive Report</h1>
    <p class="subtitle">Generated:<?php echo $generated_on; ?></p>
    <span class="panel-label"><?php echo $current_lab_name; ?></span>
  </div>

  <div class="filters">
    <h3>Report Parameters</h3>
    <table class="filters-table">
      <tbody>
        <tr class="filter-row">
          <td style="width:20%"><span class="filter-label">Time Period:</span></td>
          <td style="width:80%">
            <div class="filter-value"><?php echo $period_export; ?></div>
          </td>
        </tr>
        <tr class="filter-row">
          <td><span class="filter-label">Clinic:</span></td>
          <td>
            <div class="filter-value"><?php echo $cur_name_clinic; ?></div>
          </td>
        </tr>
        <tr class="filter-row">
          <td><span class="filter-label">Report Type:</span></td>
          <td>
            <div class="filter-value">
              <?php
              if ($view_by == 'type') {
                echo 'All Types';
              } else {
                echo 'All Panels';
              }
              ?>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="summary">
    <h2>Executive Summary</h2>
    <table class="stats-grid summary-table" style="width:100%">
      <tbody>
        <tr>
          <td style="border-bottom:none">
            <div class="stat-card">
              <div class="stat-value"><?php echo $total_samples; ?></div>
              <div class="stat-label">Total Samples</div>
            </div>
          </td>
          <td style="border-bottom:none">
            <div class="stat-card">
              <div class="stat-value"><?php echo $total_positive_rate; ?>%</div>
              <div class="stat-label">Overall Positivity Rate</div>
            </div>
          </td>
          <td style="border-bottom:none">
            <div class="stat-card">
              <div class="stat-value"><?php echo $total_positive; ?></div>
              <div class="stat-label">Positive Results</div>
            </div>
          </td>
          <td style="border-bottom:none">
            <div class="stat-card">
              <div class="stat-value"><?php echo $total_negative; ?></div>
              <div class="stat-label">Negative Results</div>
            </div>
          </td>
        </tr>
        <tr>
          <td style="border-bottom:none">
            <div class="stat-card">
              <div class="stat-value"><?php echo $total_critical; ?></div>
              <div class="stat-label">Critical Findings</div>
            </div>
          </td>
          <td style="border-bottom:none">
            <div class="stat-card">
              <div class="stat-value"><?php echo $total_clinics; ?></div>
              <div class="stat-label">Active Clinics</div>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="section">
    <h2><?php echo $view_by == 'panel' ? 'Panel Type' : 'Test Type'; ?> Distribution & Positivity Rates</h2>
    <table>
      <thead>
        <tr>
          <th><?php echo $view_by == 'panel' ? 'Panel' : 'Test Type'; ?></th>
          <th>Total Samples</th>
          <th>Percentage</th>
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
  </div>

  <div class="section">
    <h2>Daily Volume Trends (14-Day)</h2>
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Negative</th>
          <th>Positive</th>
          <th>Total</th>
          <th>Positivity Rate</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $sum_negative = 0;
        $sum_positive = 0;
        $sum_total = 0;
        $sum_pr = 0;
        $count = 0;

        foreach ($days_daily as $row) {
          $n = $row['negative'] ?? 0;
          $p = $row['positive'] ?? 0;
          $t = intval($n) + intval($p);
          $pr = $p > 0 ? round($p / $t * 100, 1) : 0;

          $sum_negative += $n;
          $sum_positive += $p;
          $sum_total += $t;
          $sum_pr += $pr;
          $count++;
        ?>
          <tr>
            <td><strong><?php echo $row['label']; ?></strong></td>
            <td class="negative"><?php echo $n; ?></td>
            <td class="positive"><?php echo $p; ?></td>
            <td><?php echo $t; ?></td>
            <td><?php echo $pr; ?>%</td>
          </tr>
        <?php }

        $avg_negative = $count > 0 ? round($sum_negative / $count, 1) : 0;
        $avg_positive = $count > 0 ? round($sum_positive / $count, 1) : 0;
        $avg_total = $count > 0 ? round($sum_total / $count, 1) : 0;
        $avg_pr = $count > 0 ? round($sum_pr / $count, 1) : 0;
        ?>
        <tr style="background: #f3f4f6; font-weight: bold; border-top: 2px solid #d1d5db;">
          <td><strong>14-Day Avg:</strong></td>
          <td class="negative"><?php echo $avg_negative; ?></td>
          <td class="positive"><?php echo $avg_positive; ?></td>
          <td><?php echo $avg_total; ?></td>
          <td><?php echo $avg_pr; ?>%</td>
        </tr>
      </tbody>
    </table>
  </div>
  <div class="section">
    <h2>Positivity Rate Trends (6-Month)</h2>
    <table>
      <thead>
        <tr>
          <th>Month</th>
          <?php
          foreach ($tabs as $tabkey => $tabdata) {
            if (in_array($tabkey, ['all', 'uyr', 'review', 'contact']))
              continue;
            if ($view_by == 'type') {
          ?>
              <th><?php echo $tabdata['title']; ?></th>
              <?php
            } else {
              $gp_ids = $tabdata['groupids'];
              if (empty($gp_ids)) continue;
              foreach ($gp_ids as $gid) {
                $group_name = $groups_mapping[$gid]['short_name'] ?? $gid;
              ?>
                <th><?php echo $group_name; ?></th>
          <?php
              }
            }
          }
          ?>
        </tr>
      </thead>
      <tbody>

        <?php foreach ($chartDataLine['labels'] as $index => $month): ?>
          <tr style="border-top:1px solid #eee;">
            <td style="padding:8px; font-weight:bold;"><?php echo htmlspecialchars($month); ?></td>
            <?php foreach ($chartDataLine['datasets'] as $dataset): ?>
              <?php
              $value = $dataset['data'][$index];
              $display = $value > 0 ? number_format($value, 1) . '%' : '-';
              ?>
              <td style="padding:8px;"><?php echo $display; ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>

      </tbody>
    </table>
  </div>

  <div class="section">
    <h2>Clinic Activity Overview</h2>
    <table>
      <thead>
        <tr>
          <th>Clinic</th>
          <th style="width: 40px" class="center">Total Volume</th>
          <th style="width: 60px" class="center">Positivity Rate</th>
          <th style="width: 40px" class="center">Top Panel</th>
          <?php
          foreach ($tabs as $tabkey => $tabdata) {
            if (in_array($tabkey, ['all', 'uyr', 'review', 'contact']))
              continue;
            if ($view_by == 'type') {
          ?>
              <th class="center"><?php echo $tabdata['title']; ?></th>
              <?php
            } else {
              $gp_ids = $tabdata['groupids'];
              if (empty($gp_ids)) continue;
              foreach ($gp_ids as $gid) {
                $group_name = $groups_mapping[$gid]['short_name'] ?? $gid;
              ?>
                <th><?php echo $group_name; ?></th>
          <?php
              }
            }
          } ?>
        </tr>
      </thead>
      <tbody>
        <?php
        foreach ($clinic_summary as $c) {
          $panels = $c['panels'];
        ?>
          <tr>
            <td><strong><?php echo $c['clinic']; ?></strong></td>
            <td><?php echo $c['total_volume']; ?></td>
            <td><?php echo $c['positivity_rate']; ?>%</td>
            <td><?php echo $c['top_panel']; ?></td>
            <?php
            foreach ($panels as $key => $value) { ?>
              <td><?php echo $value; ?></td>
            <?php } ?>
          </tr>
        <?php } ?>

      </tbody>
    </table>
  </div>

  <div class="section">
    <?php
    $totals = $critical_findings_summary['total_summary'];
    ?>

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
              <span
                class="<?php echo $row['ESBL'] > 0 ? "high-badge" : "zero-badge"; ?>">
                <?php echo $row['ESBL']; ?>
              </span>
              <span class="trend-info">(prev: <?php echo $data_cp['ESBL']['diff']; ?>)</span>
            </td>

            <td>
              <span
                class="<?php echo $row['CRE'] > 0 ? "critical-badge" : "zero-badge"; ?>">
                <?php echo $row['CRE']; ?>
              </span>
              <span class="trend-info">(prev: <?php echo $data_cp['CRE']['diff']; ?>)</span>
            </td>

            <td>
              <span
                class="<?php echo $row['MDR_AB'] > 0 ? "critical-badge" : "zero-badge"; ?>">
                <?php echo $row['MDR_AB']; ?></span>
              <span class="trend-info">(prev: <?php echo $data_cp['MDR_AB']['diff']; ?>)</span>
            </td>

            <td>
              <span
                class="<?php echo $row['MDR_PA'] > 0 ? "high-badge" : "zero-badge"; ?>">
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

    <!-- Legend -->
    <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e5e7eb;">
      <table>
        <tbody>
          <tr>
            <td style="border-bottom:none">
              <h5 style="font-weight: bold; color: #111827; margin: 0 0 8px 0; font-size: 13px;">Resistance Markers</h5>
              <ul style="margin: 0; padding-left: 0; list-style: none; color: #4b5563; line-height: 1.6;">
                <li><strong>MRSA:</strong> Methicillin-Resistant Staphylococcus aureus</li>
                <li><strong>VRE:</strong> Vancomycin-Resistant Enterococci</li>
              </ul>
            </td>
            <td style="border-bottom:none">
              <h5 style="font-weight: bold; color: #111827; margin: 0 0 8px 0; font-size: 13px;">Extended Spectrum</h5>
              <ul style="margin: 0; padding-left: 0; list-style: none; color: #4b5563; line-height: 1.6;">
                <li><strong>ESBL:</strong> Extended-Spectrum Beta-Lactamase</li>
                <li><strong>CRE:</strong> Carbapenem-resistant Enterobacterales</li>
              </ul>
            </td>
            <td style="border-bottom:none">
              <h5 style="font-weight: bold; color: #111827; margin: 0 0 8px 0; font-size: 13px;">MDR Organisms</h5>
              <ul style="margin: 0; padding-left: 0; list-style: none; color: #4b5563; line-height: 1.6;">
                <li><strong>MDR AB:</strong> Multi-Drug Resistant Acinetobacter baumannii</li>
                <li><strong>MDR PA:</strong> Multi-Drug Resistant Pseudomonas aeruginosa</li>
              </ul>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

  </div>
  </div>

  <div class="footer">
    <p><strong>Ignite Laboratory Information System - igniteABX Trends Reports</strong></p>
    <p>Aggregated testing data for authorized use in compliance monitoring, operational insight, and clinical trend
      analysis.</p>
    <p style="margin-top: 10px;">Report File: <?php echo $outfile; ?></p>
  </div>
</body>

</html>