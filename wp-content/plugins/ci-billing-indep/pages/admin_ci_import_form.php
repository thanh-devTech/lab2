<?php
$step = isset($_REQUEST['step']) ? $_REQUEST['step'] : 1;

if ($act == 'download_status_file') {
    $filename = 'demo_billing_status.csv';
    ob_clean();
    header("Content-disposition: attachment; filename={$filename}");
    header("Content-Type: text/csv");
    $headers = array('Specimen Number', 'Status');
    $row = array('DEMO01234567', 'Paid');

    $output = fopen("php://output", 'w');
    fputcsv($output, $headers);
    fputcsv($output, $row);
    exit;
} else if (isset($_POST['import_billing_status'])) {
    $save_folder = WP_CONTENT_DIR . '/log/billing_status';
    if (!is_dir($save_folder)) {
        wp_mkdir_p($save_folder);
    }

    if (!empty($_FILES['import_file']['name'])) {
        $filename   = $_FILES['import_file']['name'];
        $filetmp    = $_FILES['import_file']['tmp_name'];
        $info       = pathinfo($filename);
        $ext        = $info['extension'];
        $required_columns = array('specimen number', 'status');

        if (in_array($ext, array('csv', 'xls', 'xlsx'))) {
            $filepath = $save_folder.'/'. $filename;
            if(move_uploaded_file($filetmp, $filepath)) {
                
            }

            $data = trpdd_get_data($filetmp, $ext, $required_columns, true, false, false, false);
            if (empty($data['header'])) {
                if (isset($data['missing'])) {
                    $missing = array_map('ucwords', $data['missing']);
                    $missing = implode(', ', $missing);
                    Tr_Session_Class::add_error("Required Columns: " . $missing);
                } else {
                    Tr_Session_Class::add_error("Required Columns: Specimen Number, Status");
                }
            } else {

                foreach ($data['data'] as $row) {


                    $assen = trpdd_get_string_exist($row, array(
                        'specimen number',
                        'accession number',
                        'accession',
                        'ascension'
                    ));
                    $status = trpdd_get_string_exist($row, array(
                        'billed status (lims)',
                        'status',
                    ));

                    if (stripos($status, 'Need Billing') !== false || stripos($status, ' Not to bill') > 0) {
                        $status = 'Need Billing';
                    } else if (
                        stripos($status, 'DOS not Matched') !== false
                        || stripos($status, 'Query-Pending') !== false
                    ) {
                        $status = 'Need Info';
                    }

                    $exist_id = $wpdb->get_var("select id from ci_batch_billings where assen = '{$assen}'");
                    $update = array(
                        'assen' => $assen,
                        'bill_status' => $status,
                    );

                    if ($exist_id) {
                        $wpdb->update('ci_batch_billings', $update, array('id' => $exist_id));
                    } else {
                        $wpdb->insert('ci_batch_billings', $update);
                    }
                }
                Tr_Session_Class::add('Imported Successfully!');
            }
        }
    }
} else if (isset($_POST['import_billing'])  || isset($_GET['logfile']) || $step > 1) {
    include "admin_ci_import_data.php";
    return;
}

?>
<div class="wrap">
    <h2 class="center">Import Billing</h2>
    <?php Tr_Session_Class::show() ?>
    <div class="frow">
        <div class="col2">
            <div class="form-wrap center">
                <form enctype="multipart/form-data" method="post">
                    <input type="hidden" name="import_billing" value="1">

                    <h3>Upload Raw Data</h3>
                    <input type="file" id="upload-files" name="files[]"
                        accept="text/csv,text/txt, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" multiple />
                    <br><br>
                    <input type="submit" value="Upload" class="button-primary" />
                    <br><br><br>
                </form>
            </div>
        </div>
        <div class="col2">
            <div class="form-wrap center">
                <form enctype="multipart/form-data" method="post">
                    <input type="hidden" name="import_billing_status" value="1">

                    <h3>Upload Billing Status Data</h3>
                    <input type="file" id="upload-files" name="import_file"
                        accept="text/csv,text/txt, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" />

                    <br><br>
                    <input type="submit" value="Upload" class="button-primary" />
                    <br><br>
                    <a class="button" href="<?php Tr_Base_Class_V6::link(array('act' => 'download_status_file', 'trajax' => 1)) ?>">Download Demo file</a>
                </form>
            </div>
        </div>
    </div>




</div>