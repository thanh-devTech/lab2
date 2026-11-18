<!DOCTYPE html>
<html>

<head>
  <title>Positivity Rate Trends Report</title>
  <link rel="stylesheet" type="text/css" href="<?php echo TRCPDD_URL . 'css/pdf.css'; ?>">
</head>
<body>
  <div class="header">
    <h1 class="title">igniteABX - Positivity Rate Trends Report</h1>
    <p class="subtitle">Generated: <?php echo $generated_on; ?></p>
    <p class="subtitle">6-Month Trend Analysis</p>
    <span class="panel-label"><?php echo $current_lab_name; ?></span>
  </div>
  <table class="table">
    <thead>
      <tr>
        <th style="width: 10%">Month</th>
        <?php
        foreach ($tabs as $tabkey => $tabdata) {
          if (in_array($tabkey, ['all', 'uyr', 'review', 'contact'])) continue;
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
        } ?>
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
            <td style="text-align:center;"><?php echo $display; ?></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="footer">
    <p><strong>Ignite Laboratory Information System - igniteABX Trends Reports</strong></p>
    <p>Aggregated testing data for authorized use in compliance monitoring, operational insight, and clinical trend analysis.</p>
  </div>
</body>

</html>