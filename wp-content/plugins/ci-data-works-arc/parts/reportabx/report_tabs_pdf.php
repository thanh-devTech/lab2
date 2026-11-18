<!DOCTYPE html>
<html>

<head>
  <title>igniteABX Report - <?php echo $cur_name_clinic; ?></title>
  <link rel="stylesheet" type="text/css" href="<?php echo TRCPDD_URL . 'css/pdf.css'; ?>">
  <style>
    .subtitle-row h3 {
      color: #3c3d59;
    }

    th {
      background: #f8f9fa;
      font-weight: bold;
      color: #333;
      font-size: 11px;
    }

    table tbody tr td strong {
      font-size: 12px;
    }

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

    .molecular-summary-table {
      table-layout: fixed;
    }

    .molecular-summary-table th,
    .molecular-summary-table td {
      padding: 4px 2px;
      font-size: 9px;
      font-weight: bold;
      word-wrap: break-word;
    }

    .molecular-summary-table .pathogen {
      width: 18%;
      color: #1f2937;
    }

    .molecular-summary-table .samples {
      width: 4%;
      text-align: center;
      background-color: #f9fafb;
      color: #1f2937;
    }

    .molecular-summary-table .value {
      text-align: center;
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
  </style>
</head>
<?php
$tab_cur = $tabs[$tab];
if ($cur_name_filter_panel == "All Panels") {
  $tab_title = $cur_name_panel;
} else {
  $tab_title = $cur_name_filter_panel;
}
?>

<body>
  <div class="header">
    <?php
    $lab = CI_Base_Lab::get_current_lab_name();
    $labname = $lab ?? 'Laboratory';
    ?>
    <h1 class="title">igniteABX - <?php echo $tab_title; ?> Report</h1>
    <p class="subtitle">Generated: <?php echo $generated_on; ?></p>
    <span class="panel-label"><?php echo $labname; ?></span>
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
    <div class="filter-row">
      <span class="filter-label">Panel Filter:</span>
      <span><?php echo $cur_name_filter_panel; ?></span>
    </div>

  </div>

  <div class="summary">
    <h2>Executive Summary</h2>
    <div class="stats-grid">
      <table style="border:none">
        <tbody>
          <tr>
            <td style="border-bottom:none">
              <div class="stat-card">
                <div class="stat-value"><?php echo $numtestsofm; ?></div>
                <div class="stat-label">Total Samples</div>
              </div>
            </td>

            <td style="border-bottom:none">
              <div class="stat-card">
                <div class="stat-value"><?php echo $pcpos; ?>%</div>
                <div class="stat-label">Overall Positivity Rate</div>
              </div>
            </td>

            <td style="border-bottom:none">
              <div class="stat-card">
                <div class="stat-value"><?php echo $total_p; ?></div>
                <div class="stat-label">Positive Results</div>
              </div>
            </td>

          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="section">
    <h2>Test Results Overview</h2>
    <table class="subtitle-row">
      <tbody>
        <tr>
          <td style="width:32%; text-align: center;;border-bottom:none;padding-left:0">
            <h3>Current Period</h3>
          </td>
          <td style="width:68%;border-bottom:none;padding-right:0">
            <h3>6-Month Trend</h3>
          </td>
        </tr>
      </tbody>
    </table>

    <table>
      <tbody>
        <tr>
          <td style="width:32%;text-align:center;vertical-align: top;border-bottom:none;padding-left:0;">
            <img style="width: 73%;margin-top:30px" src="<?php echo crop_center_base64($image_test_results, 520, 520); ?>" />
          </td>
          <td style="width:68%;border-bottom:none;vertical-align: top;padding-right:0">
            <table>
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
                $count_months = !empty($chart2->monthdata) && is_array($chart2->monthdata) ? count($chart2->monthdata) : 0;

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
                $avg_rate = $avg_samples > 0
                  ? round(($avg_pos / $avg_samples) * 100, 1)
                  : 0;
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
          </td>
        </tr>
      </tbody>
    </table>

  </div>

  <?php
  if ($filepath_save_pdf != '') {
    $rows = $table_pathogens->rows;
  } else {
    $rows = $table_pathogens['rows'] ?? [];
  }
  ?>
  <div class="section">
    <h2>Pathogens<strong style="color:#111827; font-size: 14px;"> | <?php echo $view_mode_path . ' View : ' . $sub_title_path; ?></strong></h2>
    <?php if (count($rows) > 0) { ?>
      <table class="subtitle-row">
        <tbody>
          <tr>
            <td style="width:32%; text-align: center;;border-bottom:none;padding-left:0">
              <h3>Current Period</h3>
            </td>
            <td style="width:68%;border-bottom:none;padding-right:0">
              <h3>6-Month Trend</h3>
            </td>
          </tr>
        </tbody>
      </table>
      <table>
        <tbody>
          <tr>
            <td style="width:32%;text-align:center;vertical-align: top;border-bottom:none;padding-left:0">
              <img style="width: 73%;margin-top:30px" src="<?php echo crop_center_base64($image_pathogens, 520, 520); ?>" />
            </td>

            <td style="width:68%;border-bottom:none;padding-right:0;vertical-align: top">
              <?php
              if ($filepath_save_pdf != '') {
                $months = $table_pathogens->columns;
              } else {
                $months = $table_pathogens['columns'];
              }

              $headerHTML = '';
              foreach ($months as $m) {
                $headerHTML .= '<th class="center">' . $m . ' %</th>';
              }
              $headerHTML .= '<th class="center">Avg %</th>';
              ?>
              <table>
                <thead>
                  <tr style="height:13px;">
                    <th>Pathogen</th>
                    <?php echo $headerHTML; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  foreach ($rows as $index => $row) {
                  ?>
                    <tr>
                      <td><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background-color:<?php echo $row['color']; ?>;"></span> <?php echo $row['name']; ?></td>
                      <?php
                      $total_denominator = 0;
                      $total_numerator = 0;
                      foreach ($months as $key => $m) {
                        $cell = isset($row[$key]) ? (array)$row[$key] : [];
                        $key_value = $prevalence_pathogen == '0' ? 'value' : ($prevalence_pathogen == 'all' ? 'value_all' : 'value_pos');
                        $cellValue = isset($cell[$key_value]) ? $cell[$key_value] : 0;
                        $key_denominator = $prevalence_pathogen == '0' ? 'total' : ($prevalence_pathogen == 'all' ? 'total_sample' : 'total_positive');
                        $total_denominator += isset($cell[$key_denominator]) ? $cell[$key_denominator] : 0;
                        $total_numerator += isset($cell['count']) ? $cell['count'] : 0;
                        echo '<td>' . $cellValue . '</td>';
                      }
                      $avg = $total_denominator ? round($total_numerator / $total_denominator * 100, 1) : 0;
                      ?>
                      <td><?php echo $avg; ?></td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </td>
          </tr>
        </tbody>
      </table>
    <?php } else { ?>
      <table>
        <tr>
          <td style="text-align:center;width:100%;border:none;color: #666;">No Data Available</td>
        </tr>
      </table>
    <?php } ?>

  </div>


  <?php
  if ($filepath_save_pdf != '') {
    $rows = $table_marker->rows ?? [];;
  } else {
    $rows = $table_marker['rows'] ?? [];
  }
  ?>

  <div class="section">
    <h2>Resistance Markers<strong style="color:#111827; font-size: 14px;"> | <?php echo $view_mode_rm . ' View : ' . $sub_title_rm; ?></strong></h2>
    <?php if (count($rows) > 0) { ?>
      <table class="subtitle-row">
        <tbody>
          <tr>
            <td style="width:32%; text-align: center;;border-bottom:none;padding-left:0">
              <h3>Current Period</h3>
            </td>
            <td style="width:68%;border-bottom:none;padding-right:0">
              <h3>6-Month Trend</h3>
            </td>
          </tr>
        </tbody>
      </table>
      <table>
        <tbody>
          <tr>
            <td style="width:32%;text-align:center;vertical-align: top;border-bottom:none;padding-left:0">
              <img style="width: 73%;margin-top:30px" src="<?php echo crop_center_base64($image_marker, 520, 520); ?>" />
            </td>

            <td style="width:68%;border-bottom:none;vertical-align: top;padding-right:0">
              <?php
              if ($filepath_save_pdf != '') {
                $months = $table_marker->columns;
              } else {
                $months = $table_marker['columns'];
              }

              $headerHTML = '';
              foreach ($months as $m) {
                $headerHTML .= '<th class="center">' . $m . ' %</th>';
              }
              $headerHTML .= '<th class="center">Avg %</th>';
              ?>
              <table>
                <thead>
                  <tr>
                    <th>Resistance Marker</th>
                    <?php echo $headerHTML; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  foreach ($rows as $index => $row) {
                  ?>
                    <tr>
                      <td><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background-color:<?php echo $row['color']; ?>;"></span> <?php echo $row['name']; ?></td>
                      <?php
                      $total_denominator = 0;
                      $total_numerator = 0;
                      foreach ($months as $key => $m) {
                        $cell = isset($row[$key]) ? (array)$row[$key] : [];
                        $key_value = $prevalence_rmk == '0' ? 'value' : ($prevalence_rmk == 'all' ? 'value_all' : 'value_pos');
                        $cellValue = isset($cell[$key_value]) ? $cell[$key_value] : 0;
                        $key_denominator = $prevalence_rmk == '0' ? 'total' : ($prevalence_rmk == 'all' ? 'total_sample' : 'total_positive');
                        $total_denominator += isset($cell[$key_denominator]) ? $cell[$key_denominator] : 0;
                        $total_numerator += isset($cell['count']) ? $cell['count'] : 0;
                        echo '<td>' . $cellValue . '</td>';
                      }
                      $avg = $total_denominator ? round($total_numerator / $total_denominator * 100, 1) : 0;
                      ?>
                      <td><?php echo $avg; ?></td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </td>
          </tr>
        </tbody>
      </table>
    <?php } else { ?>
      <table>
        <tr>
          <td style="text-align:center;width:100%;border:none;color: #666;">No Data Available</td>
        </tr>
      </table>
    <?php } ?>
  </div>

  <div class="section">
    <h2>Infection Complexity Analysis</h2>
    <table>
      <tbody>
        <tr>
          <td style="width:50%;vertical-align: top;border-bottom:none;" class="info-box">
            <h4>Polymicrobial Detections</h4>
            <p style="margin-bottom: 12px; font-size: 11px; color: #666;">Pathogen count per specimen.</p>
            <?php
            $pi_data = $chart5->data;
            $total_rate = $chart5->data_rate;
            $pi_total = 0;
            foreach ($pi_data as $index => $row) {
              if ($index == 0)
                continue;
              $pi_total += $row[1];
            ?>
              <p><strong><?php echo $row[0]; ?>:</strong> <?php echo $row[1]; ?> (<?php echo $total_rate[$index - 1] ? $total_rate[$index - 1] : 0; ?>%)
              </p>
            <?php } ?>
            <p style="margin-top: 10px; font-size: 12px; color: #666;;">
              Total: <?php echo $pi_total; ?> infections analyzed
            </p>
          </td>

          <td style="width:50%;vertical-align: top;border-bottom:none;border-left:solid 8px #fff" class="info-box">
            <h4>Resistance Marker Distribution</h4>
            <p style="margin-bottom: 12px; font-size: 11px; color: #666;">Resistance marker count per specimen.</p>
            <?php
            $mdr_data = $chart6->data;
            $total_rate = $chart6->total_rate;
            $mdr_total = 0;
            foreach ($mdr_data as $index => $row) {
              if ($index == 0)
                continue;
              $mdr_total += $row[1];
            ?>
              <p><strong><?php echo $row[0]; ?>:</strong> <?php echo $row[1]; ?>
                (<?php echo $total_rate[$index - 1] ? $total_rate[$index - 1] : 0; ?>%)</p>
            <?php } ?>
            <p style="margin-top: 10px; font-size: 12px; color: #666;">
              Total: <?php echo $mdr_total; ?> infections analyzed
            </p>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <?php
  $molecular_rows = array();
  $molecular_columns = array();
  if (isset($genotypic_antibiogram)) {
    if (is_object($genotypic_antibiogram)) {
      $molecular_rows = $genotypic_antibiogram->rows ?? array();
      $molecular_columns = $genotypic_antibiogram->columns ?? array();
    } else {
      $molecular_rows = $genotypic_antibiogram['rows'] ?? array();
      $molecular_columns = $genotypic_antibiogram['columns'] ?? array();
    }
  }
  ?>
  <?php if (!empty($molecular_rows) && !empty($molecular_columns)) { ?>
    <div class="section">
      <h2>Molecular Resistance Summary<strong style="color:#111827; font-size: 14px;"> | Pathogen-Resistance Marker Co-Detection Rates</strong></h2>
      <table class="molecular-summary-table">
        <thead>
          <tr>
            <th class="pathogen">Pathogen</th>
            <th class="samples">n</th>
            <?php foreach ($molecular_columns as $column) { ?>
              <th class="center"><?php echo esc_html($column); ?></th>
            <?php } ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($molecular_rows as $row) { ?>
            <?php $row = (array) $row; ?>
            <tr>
              <td class="pathogen"><?php echo esc_html($row['full_name'] ?? $row['name']); ?></td>
              <td class="samples"><?php echo intval($row['samples']); ?></td>
              <?php foreach ($molecular_columns as $column) { ?>
                <?php
                $values = isset($row['values']) ? (array) $row['values'] : array();
                $cell = isset($values[$column]) ? (array) $values[$column] : null;
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
      <div style="margin-top: 8px; padding: 10px 14px; background-color: #f9fafb; border-radius: 8px; font-size: 11px; line-height: 1.5; color: #6b7280;">
        <strong style="color:#1f2937;">Note:</strong> BL Inhibitors = Beta-Lactamase Inhibitors<br>
        This table provides a molecular, marker-based view of resistance and is not equivalent to a culture-based susceptibility assessment. It summarizes how often antibiotic resistance markers were detected alongside each pathogen, providing an estimate of potential resistance to a given antibiotic class. Values reflect overall trends and do not predict susceptibility for an individual case. Incomplete marker coverage may underestimate resistance rates, and resistance markers cannot always be assigned to a specific organism in polymicrobial infections. The presence of a marker does not always result in phenotypic resistance, and absence of a detected marker does not guarantee susceptibility.
      </div>
    </div>
  <?php } ?>

  <div class="section">
    <h2>Critical Findings</h2>
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
            <td style="border-bottom:none;vertical-align: top;">
              <h5 style="font-weight: bold; color: #111827; margin: 0 0 8px 0; font-size: 13px;">Resistance Markers</h5>
              <ul style="margin: 0; padding-left: 0; list-style: none; color: #4b5563; line-height: 1.6;">
                <li><strong>MRSA:</strong> Methicillin-Resistant Staphylococcus aureus</li>
                <li><strong>VRE:</strong> Vancomycin-Resistant Enterococci</li>
              </ul>
            </td>
            <td style="border-bottom:none;vertical-align: top;">
              <h5 style="font-weight: bold; color: #111827; margin: 0 0 8px 0; font-size: 13px;">Extended Spectrum</h5>
              <ul style="margin: 0; padding-left: 0; list-style: none; color: #4b5563; line-height: 1.6;">
                <li><strong>ESBL:</strong> Extended-Spectrum Beta-Lactamase</li>
                <li><strong>CRE:</strong> Carbapenem-resistant Enterobacterales</li>
              </ul>
            </td>
            <td style="border-bottom:none;vertical-align: top;">
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

  <div class="footer">
    <p><strong>Ignite Laboratory Information System - igniteABX Trends Reports</strong></p>
    <p>Aggregated testing data for authorized use in compliance monitoring, operational insight, and clinical trend
      analysis.</p>
    <p style="margin-top: 10px; font-size: 11px;">Report File: <?php echo $outfile; ?></p>
  </div>

</body>

</html>
