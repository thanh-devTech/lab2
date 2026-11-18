<!DOCTYPE html>
<html>

<head>
  <title>Volume Trend Report</title>
  <link rel="stylesheet" type="text/css" href="<?php echo TRCPDD_URL . 'css/pdf.css'; ?>">
</head>
<?php
$rows = $table_marker['rows'];
if ($cur_name_filter_panel == "All Panels") {
  $cur_name_filter_panel = "All " . $cur_name_panel . " Panels";
}
?>

<body>
  <div class="header">
    <h1 class="title">Resistance Markers <?php echo $view_mode_rm; ?> Trends Report</h1>
    <p class="subtitle">Generated: <?php echo $generated_on; ?></p>
  </div>

  <div class="filters">
    <h3>Report Parameters</h3>
    <div class="filter-row">
      <span class="filter-label">Time Period:</span>
      <?php echo $period_export; ?>
    </div>
    <div class="filter-row">
      <span class="filter-label">Clinic Filter:</span>
      <?php echo $cur_name_clinic; ?>
    </div>
    <div class="filter-row">
      <span class="filter-label">Panel Type:</span>
      <?php echo $cur_name_filter_panel; ?>
    </div>
    <div class="filter-row">
      <span class="filter-label">View mode:</span>
      <?php echo $view_mode_rm . ': ' . $sub_title_rm; ?>
    </div>
  </div>

  <div class="section">
    <h2>Resistance Markers</h2>
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
              $months = $table_marker['columns'];

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
  <div class="footer">
    <p><strong>Ignite Laboratory Information System - igniteABX Trends Reports</strong></p>
    <p>Aggregated testing data for authorized use in compliance monitoring, operational insight, and clinical trend
      analysis.</p>
    <p style="margin-top: 10px;">Report File: <?php echo $outfile; ?></p>
  </div>
</body>

</html>