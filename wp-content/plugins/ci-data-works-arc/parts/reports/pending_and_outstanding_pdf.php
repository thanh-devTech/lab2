<html>

<head>
  <meta charset="UTF-8">
  <title><?php echo $outfile ?></title>
  <link rel="stylesheet" type="text/css" href="<?php echo TRCPDD_URL . 'css/pdf.css'; ?>">
  <style>
    .report-title {
      color: #000;
    }

    .summary-grid {
      margin-bottom: 15px;
    }

    .summary-value {
      color: #000;
    }

    .value-red {
      color: #dc2626;
    }

    .value-orange {
      color: #ea580c;
    }

    .value-amber {
      color: #f59e0b;
    }

    .value-green {
      color: #16a34a;
    }

    .top-boxes-tbl td {
      border-bottom: none;
    }

    .status-badge {
      padding: 3px 8px;
      border-radius: 3px;
      font-size: 9px;
      text-transform: uppercase;
      letter-spacing: 0.3px;
      display: inline-block;
      font-weight: bold;
      white-space: nowrap;
    }

    .status-not-received {
      background: #fef3c7;
      color: #92400e;
    }

    .status-pending {
      background: #dbeafe;
      color: #1e40af;
    }

    .bucket-badge {
      padding: 2px 6px;
      border-radius: 3px;
      font-size: 9px;
      font-weight: bold;
    }

    .bucket-critical {
      background: #fee2e2;
      color: #991b1b;
    }

    .bucket-high {
      background: #fed7aa;
      color: #9a3412;
    }

    .bucket-moderate {
      background: #fef3c7;
      color: #92400e;
    }

    .bucket-normal {
      background: #d1fae5;
      color: #065f46;
    }
  </style>
</head>

<body>

  <div class="header">
    <h1 class="title">Current Delays</h1>
    <p class="subtitle">Generated: <?php echo $generated_on; ?></p>
    <span class="panel-label"><?php echo CI_Base_Lab::get_current_lab_name(); ?></span>
  </div>

  <div class="section">
    <div class="filters">
      <h3>Report Parameters</h3>
      <div class="filter-row">
        <span class="filter-label">Panels:</span>
       <?php echo $cur_name_filter_panel; ?>
      </div>
      <div class="filter-row">
        <span class="filter-label">Clinic:</span>
        <?php echo $cur_name_clinic; ?>
      </div>
      <div class="filter-row">
        <span class="filter-label">Status:</span>
        <?php echo $cur_name_status; ?>
      </div>
      <div class="filter-row">
        <span class="filter-label">Bucket:</span>
        <?php echo $cur_name_bucket; ?>
      </div>
    </div>
  </div>

  <div class="section">
    <div class="section-title">Not Received Samples Summary</div>
    <div class="summary-grid">
      <table class="top-boxes-tbl" style="margin-top:0">
        <tbody>
          <tr>
            <td style="width:25%">
              <div class="summary-card">
                <div class="summary-label">Not Received &gt;72h</div>
                <div class="summary-value"><?php echo $count_batch->not_received_over_72; ?></div>
                <div class="summary-sublabel">Critical Delay</div>
              </div>
            </td>

            <td style="width:25%">
              <div class="summary-card">
                <div class="summary-label">Not Received 48-72h</div>
                <div class="summary-value"><?php echo $count_batch->not_received_48_72; ?></div>
                <div class="summary-sublabel">High Delay</div>
              </div>
            </td>

            <td style="width:25%">
              <div class="summary-card">
                <div class="summary-label">Not Received 24-48h</div>
                <div class="summary-value"><?php echo $count_batch->not_received_24_48; ?></div>
                <div class="summary-sublabel">Moderate Delay</div>
              </div>
            </td>

            <td style="width:25%">
              <div class="summary-card">
                <div class="summary-label">Not Received &lt;24h</div>
                <div class="summary-value"><?php echo $count_batch->not_received_under_24; ?></div>
                <div class="summary-sublabel">Normal range</div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="section">
    <div class="section-title">Pending Result Samples Summary</div>
    <div class="summary-grid">
      <table class="top-boxes-tbl" style="margin-top:0">
        <tbody>
          <tr>
            <td style="width:25%">
              <div class="summary-card">
                <div class="summary-label">Pending &gt;72h</div>
                <div class="summary-value"><?php echo $count_batch->pending_over_72; ?></div>
                <div class="summary-sublabel">Critical Delay</div>
              </div>
            </td>

            <td style="width:25%">
              <div class="summary-card">
                <div class="summary-label">Pending 48-72h</div>
                <div class="summary-value"><?php echo $count_batch->pending_48_72; ?></div>
                <div class="summary-sublabel">High Delay</div>
              </div>
            </td>

            <td style="width:25%">
              <div class="summary-card">
                <div class="summary-label">Pending 24-48h</div>
                <div class="summary-value"><?php echo $count_batch->pending_24_48; ?></div>
                <div class="summary-sublabel">Moderate Delay</div>
              </div>
            </td>

            <td style="width:25%">
              <div class="summary-card">
                <div class="summary-label">Pending &lt;24h</div>
                <div class="summary-value"><?php echo $count_batch->pending_under_24; ?></div>
                <div class="summary-sublabel">Normal range</div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="section">
    <div class="section-title">Detailed Sample List (<?php echo $total_count ?> Total)</div>
    <table>
      <thead>
        <tr>
          <th class="center" style="width:100px">Status</th>
          <th>Accession</th>
          <th>Panel</th>
          <th>Clinic</th>
          <th style="width:70px">Collection</th>
          <th style="width:70px">Received</th>
          <th style="width:50px">Age</th>
          <th style="width:50px">Bucket</th>
          <th style="width:50px">Plated</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $mem_limit = ini_get('memory_limit');
        $limit_rows = 1000;
        if ($mem_limit < 512) {
          $limit_rows = 600;
        } else if ($mem_limit < 265) {
          $limit_rows = 400;
        }
        if (isset($_GET['debugstyle'])) {
          $limit_rows = 100;
        }
        foreach ($reports as $index => $row) {
          if ($index >= $limit_rows) {
            break;
          }
        ?>
          <tr>
            <td>
              <?php if ($row['status'] === 'pending'){ ?>
                <span class="status-badge status-pending">PENDING</span>
              <?php } else { ?>
                <span class="status-badge status-not-received">NOT RECEIVED</span>
              <?php } ?>
            </td>
            <td><?php echo $row['assen']; ?></td>
            <td><?php echo !empty($row['panels']) ? $row['panels'] : '-'; ?></td>
            <td><?php echo !empty($row['cli_clinic']) ? ucwords(strtolower($row['cli_clinic'])) : '-'; ?></td>
            <td><?php echo $row['doc']; ?></td>
            <td style="color: #999;"><?php if($row['status'] === 'pending'){ echo $row['spec_received']; }else { echo '-';} ?></td>
            <td><?php echo $row['age_in_hours']; ?>h</td>
            <td><span class="bucket-badge <?php echo $row['bucket_class_pdf']; ?>"><?php echo htmlspecialchars($row['bucket_txt'], ENT_QUOTES, 'UTF-8'); ?></span></td>
            <td class="center"><?php echo $row['plated'] ?></td>
          </tr>
        <?php
        }
        ?>

      </tbody>
    </table>
  </div>

    <div class="footer">
        <p style="margin-bottom: 12px;"><strong>Ignite Laboratory Information System</strong></p>
        <p>igniteLIS analytics reports are intended for authorized laboratory and administrative users. These reports provide aggregated operational data to support workflow monitoring, performance oversight, and applicable compliance or public-health reporting.</p>
    </div>


</body>

</html>