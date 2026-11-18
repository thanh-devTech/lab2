<!DOCTYPE html>
<html>

<head>
  <title>Clinic Activity Report</title>
  <link rel="stylesheet" type="text/css" href="<?php echo TRCPDD_URL . 'css/pdf.css'; ?>">
</head>

<body>
  <div class="header">
    <h1 class="title">igniteABX - Clinic Activity Report</h1>
    <p class="subtitle">Generated: <?php echo $generated_on; ?></p>
    <span class="panel-label"><?php echo $current_lab_name; ?></span>
  </div>

  <div class="filters">
    <h3>Report Details</h3>
    <div class="filter-row">
      <span class="filter-label">Period:</span>
      <span><?php echo $period_export; ?></span>
    </div>
    <div class="filter-row">
      <span class="filter-label">View Mode:</span>
      <span><?php if ($view_by == 'type') { echo 'Type'; } else { echo 'Panel';} ?></span>
    </div>
    <div class="filter-row">
      <span class="filter-label">Total Clinics:</span>
      <span><?php echo $clinic_activity_overview['total_clinics']; ?></span>
    </div>
  </div>

  <table class="table">
    <thead>
      <tr>
        <th>Clinic</th>
        <th style="width:46px;">Total Volume</th>
        <th style="width:60px;">Positivity Rate</th>
        <th style="width:40px;">Top Panel</th>
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
        }
        ?>
      </tr>
    </thead>
    <tbody>
      <?php
      foreach ($clinic_summary as $row) {
        $panels = $row['panels'];
      ?>
        <tr>
          <td><strong><?php echo $row['clinic']; ?></strong></td>
          <td><?php echo $row['total_volume']; ?></td>
          <td><?php echo $row['positivity_rate']; ?>%</td>
          <td><?php echo $row['top_panel']; ?></td>
          <?php
          foreach ($panels as $key => $value) {
          ?>
            <td><?php echo $value; ?></td>
          <?php
          }
          ?>
        </tr>
      <?php
      }
      ?>
    </tbody>
  </table>

  <div class="footer">
    <p><strong>Ignite Laboratory Information System - igniteABX Trends Reports</strong></p>
    <p>Aggregated testing data for authorized use in compliance monitoring, operational insight, and clinical trend analysis.</p>
  </div>
</body>

</html>