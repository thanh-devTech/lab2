<?php
global $wpdb, $current_role;
include_once(TRCPDD_PATH . 'pages/formdata.php');

$user_id = get_current_user_id();

//check files
$result_dir = tr_get_result_upload_dir();
$path = $result_dir['path'];

if ($_POST['insert_files']) {
    $files = $_FILES['Results'];
    foreach ($files['name'] as $i => $outfile) {
        if (empty($outfile)) continue;

        $filepath = $path . $outfile;
        $tmp = $files['tmp_name'][$i];
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        if (move_uploaded_file($tmp, $filepath)) {
            //var_dump($filepath);exit;

            //check in db
            $added_logo = 0;
            $assen  = strtolower($outfile);
            $assen = str_replace('.pdf', '', $assen);
            $batch = $wpdb->get_row("select * from ci_batches where assen = '{$assen}'", ARRAY_A);

            if ($batch && $batch['mid'] > 0) {
                $update = array('result_pdf' => $outfile);

                if (empty($batch['batch_id'])) {
                    $update['batch_id'] = date_i18n('Ymd');
                    if (strtotime($batch['doc']) > 1000) {
                        $update['batch_id'] = date_i18n('Ymd', strtotime($batch['doc']));
                    }
                }

                $wpdb->update('ci_batches', $update, array('mid' => $batch['mid']));

                //update approve_date
                $re = $wpdb->get_row("select id,final_results from ci_reports where patient_id = '{$batch['assen']}'");
                $update_report = array(
                    'approve_date' => date_i18n('Y-m-d H:i:s'),
                    'final_results' => intval($_POST['final_results']),
                    'modified' => time(),
                );
                if ($re && $re->id > 0) {
                    if ($re->final_results) {
                        unset($update_report['final_results']);
                    }
                    $wpdb->update('ci_reports', $update_report, array('id' => $re->id));
                } else {
                    $update_report['patient_id'] = $batch['assen'];
                    $update_report['created'] = date_i18n('Y-m-d H:i:s');
                    $update_report['created_date'] = date_i18n('Y-m-d');
                    $update_report['author_id'] = get_current_user_id();

                    $wpdb->insert('ci_reports', $update_report);
                }

                trpdd_log_user($user_id, 'uploadedpdf_rs', 'Uploaded Tox Ref result pdf ' . ($_POST['final_results'] == 2 ? ', Preliminary' : ''), $batch['mid'], 'tox', '', '', $assen);
            } else {
                //not found
                Tr_Session_Class::add_error("This result \"{$assen}\" does not match an existing Requisition.");
            }

            //update approve_date
            $wpdb->update('ci_reports', array('approve_date' => date_i18n('Y-m-d H:i:s')), array('patient_id' => $batch['assen']));

            //ccheck pdf version to convert to 1.4
            //var_dump(trpdd_pdfVersion($filepath));

        } else {
            Tr_Session_Class::add_error('Can\'t upload file: ' . $outfile);
            continue;
        }
    }

    Tr_Session_Class::add("Uploaded successfully");
}
?>

<div class="wrap">
    <h2 class="center">Tox Results Upload</h2>
    <?php Tr_Session_Class::show() ?>
    <br><br>
    <div class="form-wrap center">
        <form method="post" enctype="multipart/form-data">
            <div class="col3">
                <h3>Result PDFs</h3>
                <input type="file" name="Results[]" multiple />
            </div>
            <div class="frow">
                <input type="hidden" name="final_results" value="1" />
                <label><input type="checkbox" name="final_results" value="2" /> Preliminary</label>
            </div>
            <div class="frow">
                <br><br>
                <input type="submit" class="button-primary" value="Upload" />
                <input type="hidden" name="insert_files" value="upload" />
            </div>
        </form>
    </div>
</div>