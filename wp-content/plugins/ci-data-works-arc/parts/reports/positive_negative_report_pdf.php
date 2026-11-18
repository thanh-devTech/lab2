<html><head>
  <meta charset="UTF-8">
  <title><?php echo $outfile ?></title>
  <link rel="stylesheet" type="text/css" href="<?php echo TRCPDD_URL . 'css/pdf.css'; ?>">
  
</head>
<body>
  <div class="header">
    <h1 class="title">Positive / Negative Report</h1>
    <p class="subtitle">Generated:  <?php echo $generated_on; ?></p>
    <span class="panel-label"><?php echo CI_Base_Lab::get_current_lab_name(); ?></span>
  </div>

    <div class="filters">
        <h3>Report Parameters</h3>
        <div class="filter-grid">
            <table class="filters-table">
                <tbody>
                    <tr class="filter-row">
                        <td style="width:30%"><span class="filter-label">Date Range:</span></td>
                        <td style="width:70%">
                            <div class="filter-value"><?php echo date_i18n('M d', strtotime($from_date));?> - <?php echo date_i18n('M d', strtotime($to_date));?></div>
                        </td>
                    </tr>
                    <tr class="filter-row">
                        <td><span class="filter-label">Test Type:</span></td>
                        <td>
                            <div class="filter-value"><?php echo $cur_name_btype; ?></div>
                        </td>
                    </tr>
                    <tr class="filter-row">
                        <td><span class="filter-label">Clinic:</span></td>
                        <td>
                            <div class="filter-value"><?php echo $cur_name_clinic;?></div>
                        </td>
                    </tr>
                   <tr class="filter-row">
                        <td><span class="filter-label">Panels:</span></td>
                        <td>
                            <div class="filter-value"><?php echo $cur_name_filter_panel;?></div>
                        </td>
                    </tr>
                    <tr class="filter-row">
                        <td><span class="filter-label">Targets:</span></td>
                        <td>
                            <div class="filter-value"><?php echo $cur_name_test; ?></div>
                        </td>
                    </tr>
                    <tr class="filter-row">
                        <td><span class="filter-label" style="white-space: nowrap;">Show Detected Only:</span></td>
                        <td>
                            <div class="filter-value"><?php if($detected_only==1) echo 'YES'; else echo 'NO';?></div>
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>
    </div>

  <div class="summary-grid">
    <table>
      <tr>
        <td>
          <div class="summary-card">
            <div class="summary-value"><?php echo $total_reports;?></div>
            <div class="summary-label">Total Specimens</div>
          </div>
        </td>
        <td>
          <div class="summary-card">
            <div class="summary-value"><?php echo $positive_reports;?></div>
            <div class="summary-label">Positive Specimens</div>
          </div>
        </td>
        <td>
          <div class="summary-card">
            <div class="summary-value"><?php echo $positivity_rate;?>%</div>
            <div class="summary-label">Positivity Rate</div>
          </div>
        </td>
      </tr>
    </table>
  </div>
  <h3 style="color: #d83d00; margin-top: 30px; margin-bottom: 10px;">Target Detection Summary</h3>
  <table>
    <thead>
      <tr>
        <th>Target Name</th>
        <th style="text-align: center;">Specimens Tested</th>
        <th style="text-align: center;">Detected</th>
        <th style="text-align: center;">Detection Rate</th>
      </tr>
    </thead>
    <tbody>
        <?php
        foreach ($test_stats as $stat) {
          $rate = ($stat['total'] > 0) ? number_format(($stat['detected'] / $stat['total']) * 100, 2) : 0;
          ?>
          <tr>
            <td style="font-weight:blod;"><?php echo $stat['name']; ?></td>
            <td class="number"><?php echo $stat['total']; ?></td>
            <td class="number"><?php echo $stat['detected']; ?></td>
            <td class="number"><?php echo $rate; ?>%</td>
          </tr>
          <?php
        }
        ?>
    </tbody>
  </table>
  
  <div class="footer">
    <p><strong>Ignite Laboratory Information System</strong></p>
    <p>igniteLIS analytics reports are intended for authorized laboratory and administrative users. These reports provide aggregated operational data to support workflow monitoring, performance oversight, and applicable compliance or public-health reporting.</p>
  </div>


</body></html>