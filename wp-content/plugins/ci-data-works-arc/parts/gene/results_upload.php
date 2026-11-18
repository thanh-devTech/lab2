<?php
global $wpdb, $current_role;


include_once(TRCPDD_PATH . 'pages/formdata.php');

$user_id    = get_current_user_id();

//check files
$result_dir = tr_get_result_upload_dir();
$path       = $result_dir['path'];

if (isset($_GET['fix_miss'])) {
    $rs = $wpdb->get_results("select * from ci_batches_gene where result_pdf ='' or result_pdf is null", ARRAY_A);
    foreach ($rs as $batch) {
        $outfile = $batch['assen'] . '.pdf';

        $file_path = $path . $outfile;
        var_dump($file_path);
        if (file_exists($file_path)) {
            $update = array('result_pdf' => $outfile);
            if (empty($batch['batch_id'])) {
                $update['batch_id'] = date_i18n('Ymd');
                if (strtotime($batch['doc']) > 1000) {
                    $update['batch_id'] = date_i18n('Ymd', strtotime($batch['doc']));
                }
            }
            $rs = $wpdb->update('ci_batches_gene', $update, array('mid' => $batch['mid']));
            echo '=>exist';
        }
        echo '<br>';
    }

    echo 'ok';
    exit;
}

if (isset($_POST['insert_files']) && $_POST['insert_files']) {
    $files = $_FILES['Results'];
    $count_file = 0;
    foreach ($files['name'] as $i => $outfile) {
        if (empty($outfile)) continue;

        $filepath = $path . $outfile;
        $tmp = $files['tmp_name'][$i];

        if (file_exists($filepath)) {
            unlink($filepath);
        }
        if (move_uploaded_file($tmp, $filepath)) {

            //check in db
            $added_logo = 0;
            $assen  = strtolower($outfile);
            $assen = str_replace('.pdf', '', $assen);
            $batch = $wpdb->get_row("select * from ci_batches_gene where assen = '{$assen}'", ARRAY_A);

            if ($batch && $batch['mid'] > 0) {
                $update = array('result_pdf' => $outfile);
                if (empty($batch['batch_id'])) {
                    $update['batch_id'] = date_i18n('Ymd');
                    if (strtotime($batch['doc']) > 1000) {
                        $update['batch_id'] = date_i18n('Ymd', strtotime($batch['doc']));
                    }
                }

                $rs = $wpdb->update('ci_batches_gene', $update, array('mid' => $batch['mid']));
                if ($rs !== false) {
                    $count_file++;

                    trpdd_log_user($user_id, 'uploadedpdf_rs', 'Uploaded gene result pdf ' . ($_POST['final_results'] == 2 ? ', Preliminary' : ''), $batch['mid'], 'gene', '', '', $assen);
                } else {
                    Tr_Session_Class::add_error("error: " . $wpdb->last_error);
                }

                //update approve_date
                $re = $wpdb->get_row("select id,final_results from ci_reports_gene where patient_id = '{$batch['assen']}'");
                $update_report = array(
                    'approve_date' => date_i18n('Y-m-d H:i:s'),
                    'final_results' => intval($_POST['final_results']),
                    'modified' => time(),
                );
                if ($re && $re->id > 0) {
                    $wpdb->update('ci_reports_gene', $update_report, array('id' => $re->id));
                } else {
                    $update_report['patient_id'] = $batch['assen'];
                    $update_report['created'] = date_i18n('Y-m-d H:i:s');
                    $update_report['created_date'] = date_i18n('Y-m-d');
                    $update_report['author_id'] = get_current_user_id();


                    $wpdb->insert('ci_reports_gene', $update_report);
                }
            } else {
                //not found
                Tr_Session_Class::add_error("This result \"{$assen}\" does not match an existing Requisition.");
            }
        } else {
            Tr_Session_Class::add_error('Can\'t upload file: ' . $outfile);
            continue;
        }
    }

    Tr_Session_Class::add("Uploaded {$count_file} files");
}
?>

<div class="wrap">
    <h2 style="text-align: center">gene Results Upload</h2>
    <?php Tr_Session_Class::show() ?>
    <br><br>
    <div class="form-wrap center">
        <form method="post" enctype="multipart/form-data">
            <div class="frow">
                <h3>Result PDFs</h3>
                <div class="frow card_file">
                    <a class="button selectfile" href="#">Choose Files</a>
                    <span class="view"></span>
                    <input type="file" name="Results[]" multiple />
                </div>

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