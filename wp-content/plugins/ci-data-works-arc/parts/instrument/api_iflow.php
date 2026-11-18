<?php
include(TRCPDD_PATH . 'api/ci_api_iflow.php');

if (isset($_POST['options'])) {
    $options = $_POST['options'];
    if (empty($options['cron'])) {
        $options['cron'] = 2;
    }
    $old_options = get_option('ins_iflow_api', array());
    $bkdata = array(
        'old' => $old_options,
        'new' => $options
    );
    //log to user changes
    trpdd_log_user(get_current_user_id(), 'api_ins_iflow_api', $bkdata);
    
    update_option('ins_iflow_api', $options);
    Tr_Session_Class::add('Saved Settings Successfully');
    $options = get_option('ins_iflow_api', array());
    wp_clear_scheduled_hook('cron_all_api', array('CI_Api_Iflow'));

    if ($options['enable']) {
        wp_schedule_event(time() + 1, '10min', 'cron_all_api', array('CI_Api_Iflow'));
    }
}
$options = get_option('ins_iflow_api', array());

function iflow_process_test_upload()
{
    if (empty($_FILES['test_file'])) {
        echo json_encode(array('success' => false, 'message' => 'No file uploaded'));
        exit;
    }
    $pipeline_id = isset($_POST['pipeline_id']) ? sanitize_text_field($_POST['pipeline_id']) : '';
    $file = $_FILES['test_file'];

    // Move uploaded file to temp location
    $upload_dir = wp_upload_dir();
    $temp_file = $upload_dir['path'] . '/' . basename($file['name']);

    if (!move_uploaded_file($file['tmp_name'], $temp_file)) {
        echo json_encode(array('success' => false, 'message' => 'Failed to save uploaded file'));
        exit;
    }

    $iflow = new CI_Api_Iflow();
    $result = $iflow->process_genetics_file($temp_file, $pipeline_id);
    @unlink($temp_file);

    if (isset($result['error'])) {
        echo json_encode(array('success' => false, 'data' => $result));
    } else {
        echo json_encode(array('success' => true, 'data' => $result));
    }
    exit;
}

function iflow_test_connection()
{
    $iflow = new CI_Api_Iflow();
    $result = $iflow->test_connection();
    wp_send_json($result);
    exit;
}

function iflow_list_pipelines()
{
    $iflow = new CI_Api_Iflow();
    $result = $iflow->list_pipelines();
    wp_send_json($result);
    exit;
}

function iflow_list_runs()
{
    $iflow = new CI_Api_Iflow();
    $result = $iflow->get_list_run();
    wp_send_json($result);
    exit;
}

function iflow_download_run_output()
{
    $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
    $iflow = new CI_Api_Iflow();
    $result = $iflow->download_run_output($job_id);
    wp_send_json($result);
    exit;
}

function iflow_get_job_status()
{
    $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
    if (empty($job_id)) {
        echo json_encode(array('success' => false, 'message' => 'Job ID required'));
        exit;
    }

    $iflow = new CI_Api_Iflow();
    $result = $iflow->check_run_status($job_id);
    echo json_encode($result);
    exit;
}
function iflow_get_job_output()
{
    $job_id = isset($_POST['job_id']) ? sanitize_text_field($_POST['job_id']) : '';
    if (empty($job_id)) {
        echo json_encode(array('success' => false, 'message' => 'Job ID required'));
        exit;
    }

    $iflow = new CI_Api_Iflow();
    $result = $iflow->get_run_outputs($job_id);
    echo json_encode($result);
    exit;
}

function iflow_clear_logs()
{
    global $wpdb;
    $wpdb->query("TRUNCATE TABLE ci_iflow_logs");

    echo json_encode(array('success' => true));
    exit;
}

function iflow_render_test_upload()
{
    $options = get_option('ins_CI_Api_Iflow', array());
    $default_pipeline = isset($options['default_pipeline_slug']) ? $options['default_pipeline_slug'] : '';
?>
    <!DOCTYPE html>
    <html>

    <body>
        <div class="container">
            <h2>Test iFlow File Upload & Run</h2>

            <div class="info-box">
                <strong>How it works:</strong>
                <ol style="margin: 10px 0 0 0; padding-left: 20px;">
                    <li>Upload file to iFlow file service</li>
                    <li>Submit run with selected pipeline</li>
                    <li>Receive run ID to track progress</li>
                </ol>
            </div>

            <div class="upload-form">
                <form id="uploadForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Select File:</label>
                        <input type="file" name="test_file" id="test_file" required />
                        <small>Select a genetics file to upload (VCF, FASTQ, BAM, etc.)</small>
                    </div>

                    <div class="form-group">
                        <label>Pipeline :</label>
                        <input type="text" name="pipeline_id" id="pipeline_id" value="<?php echo esc_attr($default_pipeline); ?>" required />
                        <small>Enter pipeline slug (e.g., hereditary-mock). <a href="#" id="loadPipelinesBtn">Load available pipelines</a></small>
                    </div>

                    <div class="form-group" id="pipelinesListGroup" style="display:none;">
                        <label>Available Pipelines:</label>
                        <select id="pipelinesSelect" size="5" style="max-width: 100%;">
                            <option value="">Loading...</option>
                        </select>
                        <small>Click to select a pipeline</small>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="button button-primary">Upload & Run</button>
                        <button type="button" class="button button-secondary" id="testConnectionBtn">Test Connection</button>
                    </div>

                    <div class="loading" id="loading">
                        <span class="loading-spinner"></span> Processing... Please wait...
                    </div>
                </form>
            </div>

            <div id="resultBox" class="result-box" style="display:none;">
                <h3>Results:</h3>
                <div id="resultContent"></div>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                // Load pipelines
                $('#loadPipelinesBtn').on('click', function(e) {
                    e.preventDefault();
                    $('#pipelinesListGroup').show();
                    $('#pipelinesSelect').html('<option>Loading pipelines...</option>');

                    $.ajax({
                        type: 'POST',
                        data: {
                            trajax: 'list_pipelines'
                        },
                        dataType: 'json',
                        success: function(data) {
                            if (data && Array.isArray(data)) {
                                var options = '';
                                data.forEach(function(pipeline) {
                                    options += `<option value="${pipeline.id}">${pipeline.description}</option>`;
                                });
                                $('#pipelinesSelect').html(options);
                            } else {
                                $('#pipelinesSelect').html('<option>No pipelines found</option>');
                            }
                        },
                        error: function() {
                            $('#pipelinesSelect').html('<option>Error loading pipelines</option>');
                        }
                    });
                });

                $('#pipelinesSelect').on('change', function() {
                    var selected = $(this).val();
                    if (selected) {
                        $('#pipeline_id').val(selected);
                    }
                });

                $('#uploadForm').on('submit', function(e) {
                    e.preventDefault();

                    var formData = new FormData();
                    var fileInput = $('#test_file')[0];
                    var pipelineID = $('#pipeline_id').val();

                    if (fileInput.files.length === 0) {
                        alert('Please select a file');
                        return;
                    }

                    if (!pipelineID) {
                        alert('Please enter a pipeline');
                        return;
                    }

                    formData.append('trajax', 'process_test_upload');
                    formData.append('test_file', fileInput.files[0]);
                    formData.append('pipeline_id', pipelineID);

                    $('#loading').addClass('active');
                    $('#resultBox').hide();

                    $.ajax({
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        success: function(data) {
                            $('#loading').removeClass('active');
                            $('#resultBox').show();

                            var resultHtml = '';
                            if (data.success) {
                                resultHtml = '<p class="success">✅ Upload and run submission successful!</p>';

                                if (data.data && data.data.id) {
                                    resultHtml += '<div style="background: #e7f3ff; padding: 15px; border-radius: 4px; margin: 15px 0;">';
                                    resultHtml += '<p><strong>🎯 Run ID:</strong> <code style="background: #fff; padding: 5px 10px; border-radius: 3px;">' + data.data.id + '</code></p>';
                                    resultHtml += '<p style="margin: 10px 0 0 0;">Use the "Check Job Status" button to monitor this run.</p>';
                                    resultHtml += '</div>';
                                }

                                resultHtml += '<details><summary>View Full Response</summary>';
                                resultHtml += '<pre>' + JSON.stringify(data.data, null, 2) + '</pre>';
                                resultHtml += '</details>';
                            } else {
                                resultHtml = '<p class="error">Upload/Run failed!</p>';
                                resultHtml += '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                            }

                            $('#resultContent').html(resultHtml);
                        },
                        error: function(xhr, status, error) {
                            $('#loading').removeClass('active');
                            $('#resultBox').show();
                            $('#resultContent').html('<p class="error">Error: ' + error + '</p>');
                        }
                    });
                });

                // Handle test connection button
                $('#testConnectionBtn').on('click', function() {
                    $('#loading').addClass('active');
                    $('#resultBox').hide();

                    $.ajax({
                        type: 'POST',
                        data: {
                            trajax: 'test_connection'
                        },
                        dataType: 'json',
                        success: function(data) {
                            $('#loading').removeClass('active');
                            $('#resultBox').show();

                            var resultHtml = '';
                            if (data.success) {
                                resultHtml = '<p class="success">✅ All services connected successfully!</p>';

                                if (data.services) {
                                    resultHtml += '<h4>Service Status:</h4>';
                                    resultHtml += '<ul style="list-style: none; padding: 0;">';

                                    $.each(data.services, function(service, info) {
                                        var icon = info.success ? '✅' : '❌';
                                        var color = info.success ? 'green' : 'red';
                                        resultHtml += '<li style="margin: 10px 0;">';
                                        resultHtml += '<strong>' + icon + ' ' + service.toUpperCase() + ':</strong> ';
                                        resultHtml += '<span style="color: ' + color + ';">' + info.message + '</span>';
                                        resultHtml += '<br><small style="color: #666;">' + info.url + '</small>';
                                        resultHtml += '</li>';
                                    });

                                    resultHtml += '</ul>';
                                }
                            } else {
                                resultHtml = '<p class="error">❌ Some services failed to connect!</p>';
                                resultHtml += '<p>' + data.message + '</p>';

                                if (data.services) {
                                    resultHtml += '<h4>Service Status:</h4>';
                                    resultHtml += '<ul style="list-style: none; padding: 0;">';

                                    $.each(data.services, function(service, info) {
                                        var icon = info.success ? '✅' : '❌';
                                        var color = info.success ? 'green' : 'red';
                                        resultHtml += '<li style="margin: 10px 0;">';
                                        resultHtml += '<strong>' + icon + ' ' + service.toUpperCase() + ':</strong> ';
                                        resultHtml += '<span style="color: ' + color + ';">' + info.message + '</span>';
                                        resultHtml += '<br><small style="color: #666;">' + info.url + '</small>';
                                        resultHtml += '</li>';
                                    });

                                    resultHtml += '</ul>';
                                }
                            }

                            resultHtml += '<details style="margin-top: 20px;"><summary>View Full Response</summary>';
                            resultHtml += '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                            resultHtml += '</details>';

                            $('#resultContent').html(resultHtml);
                        },
                        error: function(xhr, status, error) {
                            $('#loading').removeClass('active');
                            $('#resultBox').show();
                            $('#resultContent').html('<p class="error">❌ Error: ' + error + '</p>');
                        }
                    });
                });
            });
        </script>
    </body>

    </html>
<?php
}

function iflow_render_view_logs()
{
    global $wpdb;
    $table_name = 'ci_iflow_logs';

    $logs = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY id DESC LIMIT 200");
?>
    <!DOCTYPE html>
    <html>

    <body>
        <div class="container">
            <h2>iFlow API Logs</h2>

            <div class="stats">
                <div class="stat-box">
                    <div class="stat-number"><?php echo count($logs); ?></div>
                    <div class="stat-label">Total Logs</div>
                </div>
                <div class="stat-box error">
                    <div class="stat-number"><?php echo count(array_filter($logs, function ($l) {
                                                    return $l->type === 'error';
                                                })); ?></div>
                    <div class="stat-label">Errors</div>
                </div>
                <div class="stat-box success">
                    <div class="stat-number"><?php echo count(array_filter($logs, function ($l) {
                                                    return $l->type === 'success';
                                                })); ?></div>
                    <div class="stat-label">Successful</div>
                </div>
            </div>

            <div class="controls">
                <button id="clearLogsBtn" class="button button-danger" style="margin-bottom: 10px;">Clear All Logs</button>
            </div>

            <table>
                <thead>
                    <tr>
                        <th width="5%">ID</th>
                        <th width="10%">Time</th>
                        <th width="8%">Type</th>
                        <th width="15%">Message</th>
                        <th width="47%">Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: #666;">
                                No logs found. Start testing the API to see logs here.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo $log->ID; ?></td>
                                <td><?php echo $log->created_at; ?></td>
                                <td class="type-<?php echo $log->type; ?>"><?php echo strtoupper($log->type); ?></td>
                                <td><?php echo esc_html($log->message); ?></td>
                                <td>
                                    <pre style="overflow-x:auto; white-space:pre;"><?php echo esc_html($log->data); ?></pre>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <script>
            $(document).ready(function() {
                $('#refreshBtn').on('click', function() {
                    location.reload();
                });

                $('#clearLogsBtn').on('click', function() {
                    if (!confirm('⚠️ Are you sure you want to clear all logs? This cannot be undone.')) {
                        return;
                    }

                    $.ajax({
                        type: 'POST',
                        data: {
                            trajax: 'clear_logs'
                        },
                        dataType: 'json',
                        success: function(data) {
                            if (data.success) {
                                location.reload();
                            } else {
                                alert('Failed to clear logs');
                            }
                        },
                        error: function() {
                            alert('Error clearing logs');
                        }
                    });
                });
            });
        </script>
    </body>

    </html>
<?php
}

function iflow_render_check_status()
{
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
?>
    <!DOCTYPE html>
    <html>

    <body>
        <div class="container">
            <h2>iFlow Runs Management</h2>

            <div class="loading" id="loading">
                <span class="loading-spinner"></span> Loading runs...
            </div>

            <div id="runsTableContainer" style="display: none;">
                <table id="runsTable">
                    <thead>
                        <tr>
                            <th width="25%">Run Name</th>
                            <th width="15%">Status</th>
                            <th width="15%">Created At</th>
                            <th width="15%">Duration</th>
                            <th width="10%">Created By</th>
                            <th width="20%">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="runsTableBody">
                    </tbody>
                </table>
            </div>

            <div id="noData" class="no-data" style="display: none;">
                <p>No runs found. Upload a file to create your first run.</p>
            </div>
        </div>

        <!-- Modal for displaying output -->
        <div id="outputModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="modalTitle">Run Output</h3>
                    <span class="close">&times;</span>
                </div>
                <div id="modalBody"></div>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                let runsData = [];

                function calculateDuration(startedAt, finishedAt) {
                    if (!startedAt) return 'N/A';

                    const start = new Date(startedAt);
                    const end = finishedAt ? new Date(finishedAt) : new Date();
                    const diff = Math.floor((end - start) / 1000); // seconds

                    if (diff < 60) return diff + 's';
                    if (diff < 3600) return Math.floor(diff / 60) + 'm ' + (diff % 60) + 's';
                    const hours = Math.floor(diff / 3600);
                    const minutes = Math.floor((diff % 3600) / 60);
                    return hours + 'h ' + minutes + 'm';
                }

                function getStatusClass(status) {
                    const statusMap = {
                        'succeeded': 'status-succeeded',
                        'failed': 'status-failed',
                        'running': 'status-running',
                        'pending': 'status-pending',
                        'cancelled': 'status-cancelled'
                    };
                    return statusMap[status.toLowerCase()] || 'status-pending';
                }

                function renderRunsTable(runs) {
                    if (!runs || runs.length === 0) {
                        $('#runsTableContainer').hide();
                        $('#noData').show();
                        $('#statsContainer').hide();
                        return;
                    }

                    runsData = runs;

                    let html = '';
                    runs.forEach(function(run) {
                        const statusClass = getStatusClass(run.status);
                        const duration = calculateDuration(run.started_at, run.finished_at);

                        html += '<tr>';
                        html += '<td><strong>' + run.name + '</strong><br><small style="color: #666;">ID: ' + run.id + '</small></td>';
                        html += '<td><span class="status-badge ' + statusClass + '">' + run.status + '</span></td>';
                        html += '<td>' + run.created_at + '</td>';
                        html += '<td>' + duration + '</td>';
                        html += '<td>' + (run.created_by || 'N/A') + '</td>';
                        html += '<td class="actions">';
                        html += '<button class="button button-secondary button-small view-output" data-run-id="' + run.id + '" data-run-name="' + run.name + '">View Output</button> ';
                        if (run.status === 'succeeded') {
                            html += '<button class="button button-primary button-small download-output" data-run-id="' + run.id + '" data-run-name="' + run.name + '">Download</button>';
                        }
                        html += '</td>';
                        html += '</tr>';
                    });

                    $('#runsTableBody').html(html);
                    $('#runsTableContainer').show();
                    $('#noData').hide();
                }

                // Load runs
                function loadRuns() {
                    $('#loading').addClass('active');
                    $('#loadRunsBtn').prop('disabled', true);

                    $.ajax({
                        type: 'POST',
                        data: {
                            trajax: 'list_runs'
                        },
                        dataType: 'json',
                        success: function(data) {
                            $('#loading').removeClass('active');
                            $('#loadRunsBtn').hide();
                            $('#refreshBtn').show();

                            if (Array.isArray(data)) {
                                renderRunsTable(data);
                            } else if (data.error) {
                                alert('Error: ' + data.message);
                            } else {
                                renderRunsTable([]);
                            }
                        },
                        error: function(xhr, status, error) {
                            $('#loading').removeClass('active');
                            $('#loadRunsBtn').prop('disabled', false);
                            alert('Error loading runs: ' + error);
                        }
                    });
                }

                // View output
                $(document).on('click', '.view-output', function() {
                    const runId = $(this).data('run-id');
                    const runName = $(this).data('run-name');

                    $('#modalTitle').text('Output: ' + runName);
                    $('#modalBody').html('<div style="text-align: center; padding: 20px;"><span class="loading-spinner"></span> Loading output...</div>');
                    $('#outputModal').show();

                    $.ajax({
                        type: 'POST',
                        data: {
                            trajax: 'get_job_output',
                            job_id: runId
                        },
                        dataType: 'json',
                        success: function(data) {
                            let html = '';

                            if (data.outputs && data.outputs.length > 0) {
                                html += '<h4>Output Files:</h4>';
                                html += '<ul style="list-style: none; padding: 0;">';
                                data.outputs.forEach(function(output) {
                                    html += '<li style="margin: 10px 0; padding: 10px; background: #f9f9f9; border-radius: 4px;">';
                                    html += '<strong>' + (output.name || 'Output') + '</strong><br>';
                                    if (output.url) {
                                        html += '<a href="' + output.url + '" target="_blank" class="download-link">📥 Download</a>';
                                    }
                                    html += '</li>';
                                });
                                html += '</ul>';
                            } else if (data.output_url) {
                                html += '<p><a href="' + data.output_url + '" target="_blank" class="download-link">📥 Download Output</a></p>';
                            } else {
                                html += '<p style="color: #666;">No output files available yet.</p>';
                            }

                            html += '<details style="margin-top: 20px;"><summary><strong>Full Response</strong></summary>';
                            html += '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                            html += '</details>';

                            $('#modalBody').html(html);
                        },
                        error: function(xhr, status, error) {
                            $('#modalBody').html('<p style="color: #d63638;">❌ Error loading output: ' + error + '</p>');
                        }
                    });
                });

                $(document).on('click', '.download-output', function() {
                    const runId = $(this).data('run-id');
                    const button = $(this);

                    button.prop('disabled', true).text('Loading...');

                    $.ajax({
                        type: 'POST',
                        data: {
                            trajax: 'download_job_output',
                            job_id: runId
                        },
                        dataType: 'json',
                        success: function(data) {
                            if (data.files && data.files.length > 0) {
                                let html = '<h4>Available Output Files:</h4>';
                                html += '<ul style="list-style: none; padding: 0;">';

                                data.files.forEach(function(file, index) {
                                    html += '<li style="margin: 10px 0; padding: 10px; background: #f9f9f9; border-radius: 4px;">';
                                    html += '<strong>' + (file.file_name || 'File ' + (index + 1)) + '</strong><br>';
                                    html += '<a href="' + file.url + '" target="_blank" class="download-link" download>📥 Download</a>';
                                    html += '</li>';
                                });

                                html += '</ul>';

                                $('#modalTitle').text('Download Files');
                                $('#modalBody').html(html);
                                $('#outputModal').show();
                            }
                            button.prop('disabled', false).text('Download');
                        },
                        error: function(xhr, status, error) {
                            button.prop('disabled', false).text('Download');
                            alert('Error: ' + error);
                        }
                    });
                });

                $('.close').on('click', function() {
                    $('#outputModal').hide();
                });

                $(window).on('click', function(event) {
                    if (event.target.id === 'outputModal') {
                        $('#outputModal').hide();
                    }
                });

                loadRuns();
            });
        </script>
    </body>

    </html>
<?php
}

// Handle AJAX actions
if (!empty($_GET['trajax']) && $_GET['trajax'] == '1' && !empty($_GET['act'])) {
    $act = sanitize_text_field($_GET['act']);

    switch ($act) {
        case 'test_upload':
            iflow_render_test_upload();
            exit;

        case 'view_logs':
            iflow_render_view_logs();
            exit;

        case 'check_status':
            iflow_render_check_status();
            exit;
    }
}

// Handle AJAX POST requests
if (!empty($_POST['trajax'])) {
    switch ($_POST['trajax']) {
        case 'process_test_upload':
            iflow_process_test_upload();
            exit;

        case 'test_connection':
            iflow_test_connection();
            exit;

        case 'list_pipelines':
            iflow_list_pipelines();
            exit;
        case 'list_runs':
            iflow_list_runs();
            exit;
        case 'download_job_output':
            iflow_download_run_output();
            exit;

        case 'get_job_status':
            iflow_get_job_status();
            exit;
        case 'get_job_output':
            iflow_get_job_output();
            exit;
        case 'clear_logs':
            iflow_clear_logs();
            exit;
    }
}

?>

<div class="wrap">
    <h2>iFlow API Settings</h2>
    <?php Tr_Session_Class::show() ?>

    <form method="post">
        <div class="row">
            <div>
                <div class="row">
                    <label for="for_enable">Enable Integration</label>
                    <input type="checkbox" id="for_enable" value="1" name="options[enable]" <?php checked(isset($options['enable']) ? $options['enable'] : 0) ?> />
                </div>

                <h3 style="margin-top: 30px; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">Authentication</h3>

                <div class="row">
                    <label>API Token</label>
                    <input type="text" name="options[token]" value="<?php echo isset($options['token']) ? esc_attr($options['token']) : '' ?>" required style="font-family: monospace;" />
                    <small>Your iFlow API Bearer token</small>
                </div>

                <div class="row">
                    <label>Project ID</label>
                    <input type="text" name="options[project_id]" value="<?php echo isset($options['project_id']) ? esc_attr($options['project_id']) : '' ?>" required style="font-family: monospace;" />
                    <small>Your iFlow Project ID (required for X-Project-ID header)</small>
                </div>

                <h3 style="margin-top: 30px; border-bottom: 2px solid #0073aa; padding-bottom: 10px;">Service URLs</h3>

                <div class="row">
                    <label>File Service URL</label>
                    <input type="text" name="options[file_url]" value="<?php echo isset($options['file_url']) ? esc_attr($options['file_url']) : 'https://files.stg.iflow.intelliseq.com' ?>" required />
                    <small>Default: https://files.stg.iflow.intelliseq.com</small>
                </div>

                <div class="row">
                    <label>Compute Service URL</label>
                    <input type="text" name="options[compute_url]" value="<?php echo isset($options['compute_url']) ? esc_attr($options['compute_url']) : 'https://compute.stg.iflow.intelliseq.com' ?>" required />
                    <small>Default: https://compute.stg.iflow.intelliseq.com</small>
                </div>

                <div class="row">
                    <label>Admin Service URL</label>
                    <input type="text" name="options[admin_url]" value="<?php echo isset($options['admin_url']) ? esc_attr($options['admin_url']) : 'https://admin.stg.iflow.intelliseq.com' ?>" required />
                    <small>Default: https://admin.stg.iflow.intelliseq.com</small>
                </div>

            </div>
        </div>

        <div class="frow row">
            <input type="submit" value="Save Settings" class="button-primary" /> &nbsp;&nbsp;
            <a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'test_upload'));
                                                if (isset($_GET['debug'])) echo '&debug=1' ?>&width=1000&height=800">Test Upload</a>
            &nbsp;&nbsp;
            <a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'check_status'));
                                                if (isset($_GET['debug'])) echo '&debug=1' ?>&width=1200&height=600">Check Run Status</a>
            &nbsp;&nbsp;
            <a class="button thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'view_logs'));
                                                if (isset($_GET['debug'])) echo '&debug=1' ?>&width=1400&height=800">View Logs</a>
        </div>
    </form>

    <div style="margin-top: 30px; padding: 15px; background: #f0f6fc; border-left: 4px solid #0073aa; border-radius: 4px;">
        <h4 style="margin-top: 0;">API Documentation</h4>
        <ul style="margin: 0; padding-left: 20px;">
            <li><strong>File Service:</strong> Handles file upload/download operations</li>
            <li><strong>Compute Service:</strong> Manages pipeline runs and workflow execution</li>
            <li><strong>Admin Service:</strong> Provides project and user management</li>
            <li><strong>Miner Service:</strong> Handles orders and sample data analysis</li>
        </ul>
        <p style="margin: 10px 0 0 0;">
            <small>
                <a href="https://docs.stg.iflow.intelliseq.com/api/reference/" target="_blank">View Full API Documentation</a><br>
                Make sure to configure your API token and Project ID before testing
            </small>
        </p>
    </div>
</div>

<style>
    .row input[type="text"] {
        width: 500px;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .row small {
        display: block;
        margin-left: 150px;
        color: #666;
        font-size: 12px;
        margin-top: 5px;
    }

    .frow {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 2px solid #ddd;
    }

    .button {
        margin-right: 5px;
    }

    .upload-form {
        background: #f9f9f9;
        padding: 20px;
        border-radius: 5px;
        margin-bottom: 20px;
        border: 1px solid #ddd;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        font-weight: bold;
        margin-bottom: 5px;
    }

    .form-group input[type="text"],
    .form-group input[type="file"],
    .form-group select {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .form-group small {
        color: #666;
        display: block;
        margin-top: 5px;
    }

    .result-box {
        background: #fff;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 5px;
        margin-top: 20px;
    }

    .error {
        color: #d63638;
    }

    .success {
        color: #00a32a;
    }



    .info-box {
        background: #e7f3ff;
        padding: 15px;
        border-left: 4px solid #0073aa;
        margin-bottom: 20px;
        border-radius: 4px;
    }

    .button {
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        margin-right: 10px;
    }

    .button-primary {
        background: #0073aa;
        color: white;
    }

    .button-primary:hover {
        background: #005177;
    }

    .button-secondary {
        background: #f0f0f0;
        color: #333;
    }

    .button-secondary:hover {
        background: #e0e0e0;
    }

    .button-small {
        padding: 4px 10px;
        font-size: 12px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 5px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        table-layout: fixed;
    }

    th,
    td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }

    th {
        background: #f5f5f5;
        font-weight: bold;
        position: sticky;
        top: 0;
    }

    tr:hover {
        background: #f9f9f9;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-weight: bold;
        font-size: 12px;
        text-transform: uppercase;
    }

    .status-pending {
        background: #fff3cd;
        color: #856404;
    }

    .status-running {
        background: #cce5ff;
        color: #004085;
    }

    .status-succeeded {
        background: #d4edda;
        color: #155724;
    }

    .status-failed {
        background: #f8d7da;
        color: #721c24;
    }

    .status-cancelled {
        background: #e2e3e5;
        color: #383d41;
    }

    .loading {
        display: none;
        padding: 15px;
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 4px;
        margin: 15px 0;
        text-align: center;
    }

    .loading.active {
        display: block;
    }

    .loading-spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 3px solid #f3f3f3;
        border-top: 3px solid #0073aa;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-right: 8px;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    .no-data {
        text-align: center;
        padding: 40px;
        color: #666;
    }

    .actions {
        white-space: nowrap;
    }

    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
        background-color: #fefefe;
        margin: 5% auto;
        padding: 20px;
        border: 1px solid #888;
        border-radius: 5px;
        width: 80%;
        max-width: 800px;
        max-height: 80vh;
        overflow-y: auto;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #0073aa;
    }

    .close {
        color: #aaa;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }

    .close:hover {
        color: #000;
    }

    pre {
        background: #f9f9f9;
        padding: 15px;
        overflow-x: auto;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 12px;
    }

    .stats {
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
    }

    .stat-box {
        flex: 1;
        padding: 10px;
        background: white;
        border-radius: 5px;
        border-left: 4px solid #0073aa;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .stat-box.success {
        border-left-color: #00a32a;
    }

    .stat-box.error {
        padding: 10px;
        margin: 0;
        border: 0;
        border-left: 4px solid #d63638;
    }

    .stat-box.warning {
        border-left-color: #ffc107;
    }

    .stat-number {
        font-size: 24px;
        font-weight: bold;
    }

    .stat-label {
        color: #666;
        font-size: 14px;
        margin-top: 4px;
    }

    .type-info {
        color: #0073aa;
    }

    .type-success {
        color: #00a32a;
    }

    .type-error {
        color: #d63638;
    }

    .download-link {
        color: #0073aa;
        text-decoration: none;
        font-weight: 500;
    }

    .download-link:hover {
        text-decoration: underline;
    }
</style>