<?php
global $wpdb, $current_role;

$batch_id   = $_REQUEST['batch_id'];
$btype      = !empty($_REQUEST['type']) ? $_REQUEST['type'] : 'tox';
$log_url    = WP_CONTENT_URL . '/log/results/';
$log_dir    = WP_CONTENT_DIR . '/log/results/';

if ($btype != 'tox') {
    $log_url = WP_CONTENT_URL . '/log/results_' . $btype . '/';
    $log_dir = WP_CONTENT_DIR . '/log/results_' . $btype . '/';
}

if (strpos($batch_id, '-') > 0) {
    list($batch_id, $k) = explode('-', $batch_id);
}
$lab_id = CI_Base_Lab::get_current_lab_id();

$files = $wpdb->get_results("select * from ci_reports_files 
                                where rf_batch_id='{$batch_id}' 
                                and rf_lab_site_id='{$lab_id}'
                                and rf_btype = '{$btype}' ");

if (count($files) == 0) {
    $batch_id = $batch_id . '-' . $k;
    $files = $wpdb->get_results("select * from ci_reports_files 
                                where rf_batch_id='{$batch_id}' 
                                and rf_lab_site_id='{$lab_id}'
                                and rf_btype = '{$btype}' ");
}
if (count($files) == 0) {
    $batch_tbl = tr_pdd_get_table_by_btype($btype);
    $report_tbl = tr_pdd_get_table_report_by_btype($btype);

    $reports = $wpdb->get_results("select upload_files 
                            from {$report_tbl} re 
                            JOIN {$batch_tbl} ba ON ba.assen = re.patient_id 
                            where ba.batch_id = '{$batch_id}' and re.lab_id='{$lab_id}'");
    $files = array();
    foreach ($reports as $r) {
        $fs = explode(',', $r->upload_files);
        $files = array_merge($files, (array)$fs);
    }
    $files = array_unique($files);
    foreach ($files as $f) {
        if (empty($f) || !file_exists($log_dir . $file->rf_file)) continue;

        $insert = array(
            'rf_batch_id' => $batch_id,
            'rf_lab_site_id' => $lab_id,
            'rf_file' => $f,
            'rf_count' => 1,
            'rf_btype' => $btype,
        );
        $wpdb->insert('ci_reports_files', $insert);
    }

    $files = $wpdb->get_results("select * from ci_reports_files 
                                where rf_batch_id='{$batch_id}' 
                                and rf_lab_site_id='{$lab_id}'
                                and rf_btype = '{$btype}' ");
}


foreach ($files as $i => $file) {
    if (!file_exists($log_dir . $file->rf_file)) {
        unset($files[$i]);
    }
}


if (count($files) == 0) {
    echo 'Not found';
    return;
}


?>
<form target="_blank" method="post" action="?page=<?php echo $btype ?>_results&tab=generator&type=qc">
    <div>Total: <?php echo count($files) ?></div>

    <div class="wraptbl">
        <table class="wp-list-table widefat striped posts blue_tbl hover_tbl">
            <thead>
                <tr>
                    <th style="width:50px"><input type="checkbox" class="checklall" /></th>
                    <th>File Name</th>
                    <th>Upload Datetime</th>
                    <th>Count</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($files as $file) {
                    $datetime = filemtime($log_dir . $file->rf_file);
                    $datetime = date('Y-m-d H:i:s', $datetime);
                ?>
                    <tr>
                        <td><input type="checkbox" class="llifiles" name="files[]" value="<?php echo $file->rf_file ?>" /></td>
                        <td><a target="_blank" href="<?php echo $log_url . $file->rf_file ?>"><?php echo $file->rf_file ?></a> </td>
                        <td><?php echo tr_pdd_display_date($datetime, 'm/d/Y H:i:s', true) ?></td>
                        <td><?php echo $file->rf_count ?></td>
                    </tr>
                <?php
                }
                ?>
            </tbody>
        </table>
    </div>

    <?php if ($btype == 'tox') { ?>

        <input type="hidden" name="act" value="ci_doctor_process" />
        <input type="submit" class="button" value="ReImport QC" />
    <?php } else if ($btype == 'gene') { ?>
        <input type="hidden" name="act" value="ci_gene_doctor_process" />
        <input type="submit" class="button" value="ReImport " />
    <?php  } ?>

</form>
<script>
    jQuery(function($) {
        $('.checklall').click(function() {
            if ($(this).is(":checked")) {
                $('.llifiles').prop('checked', true);
            } else {
                $('.llifiles').removeAttr('checked');
            }
        })
    })
</script>