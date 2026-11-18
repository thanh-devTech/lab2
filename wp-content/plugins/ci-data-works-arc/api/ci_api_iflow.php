<?php

/**
 * iFlow API Integration
 * Purpose: Send genetics files from instrument to iFlow and retrieve results
 */

class CI_Api_Iflow
{
    private $api_token = '';
    private $project_id;
    private $file_url = 'https://files.stg.iflow.intelliseq.com';
    private $compute_url = 'https://compute.stg.iflow.intelliseq.com';
    private $admin_url = 'https://admin.stg.iflow.intelliseq.com';
    private $log_enabled = true;

    public $log     = '';

    var $api_id     = -65;

    var $options = array();

    public function __construct()
    {
        $this->get_options();
        $options = $this->options;

        // Override with saved settings if available
        if (isset($options['token']) && !empty($options['token'])) {
            $this->api_token = $options['token'];
        }

        $this->project_id = isset($options['project_id']) ? $options['project_id'] : '';

        if (isset($options['file_url'])) {
            $this->file_url = $options['file_url'];
        }
        if (isset($options['compute_url'])) {
            $this->compute_url = $options['compute_url'];
        }
        if (isset($options['admin_url'])) {
            $this->admin_url = $options['admin_url'];
        }
    }

    public function get_options()
    {
        $this->options = get_option('ins_iflow_api');
    }

    public function cron()
    {
        $this->upload_gene_files();
        $this->get_result_file();
    }

    /**
     * Log message to database
     */
    private function log($message, $data = array(), $type = 'info')
    {
        if (!$this->log_enabled) return;

        global $wpdb;
        $table_name = 'ci_iflow_logs';

        $wpdb->insert(
            $table_name,
            array(
                'message' => $message,
                'data' => json_encode($data, JSON_PRETTY_PRINT),
                'type' => $type,
                'created_at' => date_i18n('Y-m-d H:i:s'),
            ),
            array('%s', '%s', '%s', '%s')
        );
    }

    /**
     * Get common headers for API requests
     */
    private function get_headers()
    {
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_token,
            'Content-Type' => 'application/json',
        );

        if (!empty($this->project_id)) {
            $headers['X-Project-ID'] = $this->project_id;
        }

        return $headers;
    }

    public function process_genetics_file($local_file_path, $pipeline_id)
    {
        $this->log('Starting genetics file processing', array(
            'file' => basename($local_file_path),
            'pipeline_id' => $pipeline_id,
        ));

        // Step 1: Upload file to iFlow
        $remote_path = 'test-data/' . basename($local_file_path);
        $upload_result = $this->upload_file($local_file_path, $remote_path, $pipeline_id);

        if (isset($upload_result['error'])) {
            $this->log('File upload failed', $upload_result, 'error');
            return array('error' => 'Upload failed', 'details' => $upload_result);
        }

        $this->log('File uploaded successfully', array('remote_path' => $remote_path, 'result' => $upload_result), 'success');

        // Step 2: Submit pipeline run
        // Map the uploaded file to the pipeline input
        $run_params['snv_vcf_gz'] = $remote_path; // Path in bucket

        $run_result = $this->submit_run($pipeline_id, $run_params);

        if (isset($run_result['error'])) {
            $this->log('Run submission failed', $run_result, 'error');
            return array('error' => 'Run submission failed', 'details' => $run_result);
        }

        $run_id = isset($run_result['id']) ? $run_result['id'] : null;

        $this->log('Run submitted successfully', array(
            'run_id' => $run_id,
            'status' => isset($run_result['status']) ? $run_result['status'] : 'unknown'
        ), 'success');

        return array(
            'success' => true,
            'run_id' => $run_id,
            'status' => isset($run_result['status']) ? $run_result['status'] : 'queued',
            'name' => isset($run_result['name']) ? $run_result['name'] : '',
            'remote_file_path' => $remote_path
        );
    }

    /**
     * Upload file to iFlow file service
     * Step 1: Request presigned URL with JSON body {path, content_type}
     * Step 2: Upload file content to presigned URL
     */
    public function upload_file($file_path, $remote_path = null, $pipeline_id)
    {
        $this->log('Uploading file', array('file' => basename($file_path)));

        if (!file_exists($file_path)) {
            return array('error' => 'File not found: ' . $file_path);
        }

        if (!$remote_path) {
            $remote_path = 'test-data/' . basename($file_path);
        }

        // Determine content type
        $content_type = $this->get_content_type(basename($file_path));

        // Step 1: Request presigned URL
        $request_body = array(
            'path' => $remote_path,
            'pipeline_id' => $pipeline_id,
            'content_type' => $content_type
        );

        $url = $this->file_url . '/api/v1/files/upload-url?project_id=' . $this->project_id;

        $response = wp_remote_post($url, array(
            'headers' => $this->get_headers(),
            'body' => json_encode($request_body),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            $this->log('Presigned URL request failed', array('error' => $response->get_error_message()), 'error');
            return array('error' => $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_data = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code >= 400) {
            $this->log('Presigned URL request failed', array('status_code' => $response_code, 'response' => $response_data), 'error');
            return array('error' => 'Upload request failed', 'status_code' => $response_code, 'response' => $response_data);
        }

        // Step 2: Upload file content to presigned URL
        if (!isset($response_data['url'])) {
            $this->log('No presigned URL in response', array('response' => $response_data), 'error');
            return array('error' => 'No presigned URL returned', 'response' => $response_data);
        }

        $presigned_url = $response_data['url'];
        $file_content = file_get_contents($file_path);

        $this->log('Uploading to presigned URL', array('size' => strlen($file_content), 'presigned_url' => $response_data['url']));

        $upload_response = wp_remote_request($presigned_url, array(
            'method' => 'PUT',
            'headers' => array(
                'Content-Type' => $content_type,
                'Content-Length' => strlen($file_content),
            ),
            'body' => $file_content,
            'timeout' => 300,
        ));

        if (is_wp_error($upload_response)) {
            $this->log('File upload to presigned URL failed', array('error' => $upload_response->get_error_message()), 'error');
            return array('error' => $upload_response->get_error_message());
        }

        $upload_code = wp_remote_retrieve_response_code($upload_response);

        if ($upload_code >= 400) {
            $this->log('File upload failed', array('status_code' => $upload_code), 'error');
            return array('error' => 'File upload failed', 'status_code' => $upload_code);
        }

        $this->log('File uploaded successfully', array('remote_path' => $remote_path), 'success');
        return array('success' => true, 'remote_path' => $remote_path);
    }

    /**
     * Get content type based on file extension
     */
    private function get_content_type($filename)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $mime_types = array(
            'vcf' => 'text/x-vcard',
            'gz' => 'application/gzip',
            'fastq' => 'application/octet-stream',
            'fq' => 'application/octet-stream',
            'bam' => 'application/octet-stream',
            'sam' => 'text/plain',
            'bed' => 'text/plain',
            'zip' => 'application/zip',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'json' => 'application/json',
        );

        return isset($mime_types[$extension]) ? $mime_types[$extension] : 'application/octet-stream';
    }

    /**
     * Submit a pipeline run
     * POST /api/v1/runs
     */
    public function submit_run($pipeline_id, $run_params = [])
    {
        $this->log('Submitting run', array('pipeline' => $pipeline_id));

        $run_data = $run_params;
        $run_data['pipeline_id'] = $pipeline_id;

        $url = $this->compute_url . '/api/v1/runs';

        $response = wp_remote_post($url, array(
            'headers' => $this->get_headers(),
            'body' => json_encode($run_data),
            'timeout' => 60,
        ));

        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_data = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code >= 400) {
            return array('error' => 'Run submission failed', 'status_code' => $response_code, 'response' => $response_data);
        }

        return $response_data;
    }

    /**
     * Check run status
     * GET /api/v1/runs/{id}
     */
    public function check_run_status($run_id)
    {
        $this->log('Checking run status', array('run_id' => $run_id));

        $url = $this->compute_url . '/api/v1/runs/' . $run_id;

        $response = wp_remote_get($url, array(
            'headers' => $this->get_headers(),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_data = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code >= 400) {
            return array('error' => 'Status check failed', 'status_code' => $response_code);
        }

        return $response_data;
    }

    /**
     * Get run outputs (results)
     * GET /api/v1/runs/{id}/outputs
     */
    public function get_run_outputs($run_id)
    {
        $this->log('Getting run outputs', array('run_id' => $run_id));

        $url = $this->compute_url . '/api/v1/runs/' . $run_id . '/outputs';

        $response = wp_remote_get($url, array(
            'headers' => $this->get_headers(),
            'timeout' => 60
        ));

        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_data = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code >= 400) {
            return array('error' => 'Failed to get outputs', 'status_code' => $response_code);
        }

        return $response_data;
    }

    public function get_list_run()
    {
        $url = $this->compute_url . '/api/v1/runs';

        $response = wp_remote_get($url, array(
            'headers' => $this->get_headers(),
            'timeout' => 60
        ));

        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_data = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code >= 400) {
            return array('error' => 'Failed to get list run', 'status_code' => $response_code);
        }

        return $response_data;
    }

    public function download_run_output($run_id)
    {

        $outputs = $this->get_run_outputs($run_id);

        if (isset($outputs['error'])) {
            $this->log('Failed to get run outputs', $outputs, 'error');
            return array('error' => 'Failed to get run outputs', 'details' => $outputs);
        }

        if (empty($outputs['outputs']) || !is_array($outputs['outputs']) || $outputs['status'] != 'succeeded') {
            $this->log('No outputs found for run', array('run_id' => $run_id), 'error');
            return array('error' => 'No outputs found for this run');
        }

        $downloaded_files = array();
        $failed_files = array();

        foreach ($outputs['outputs'] as $index => $output) {
            $file_path = $output['path'];
            $file_name = $output['name'];

            if (!$file_path) {
                $this->log('No file path found in output', $output, 'error');
                $failed_files[] = array(
                    'index' => $index,
                    'error' => 'No file path in output',
                    'output' => $output
                );
                continue;
            }

            $result = $this->download_result($file_path);

            if (isset($result['error'])) {

                $failed_files[] = array(
                    'file_path' => $file_path,
                    'error' => $result['error'],
                    'file_name' => $file_name
                );
            } else {

                $downloaded_files[] = array(
                    'url' => $result['url'],
                    'file_path' => $file_path,
                    'file_name' => $file_name
                );
            }
        }

        $response = array(
            'success' => count($downloaded_files) > 0,
            'run_id' => $run_id,
            'total_outputs' => count($outputs),
            'downloaded' => count($downloaded_files),
            'failed' => count($failed_files),
            'files' => $downloaded_files
        );

        if (!empty($failed_files)) {
            $response['failed_files'] = $failed_files;
        }

        if (count($downloaded_files) === 0) {
            $response['error'] = 'No files were downloaded successfully';
            $this->log('No files downloaded', $response, 'error');
        } else {
            $this->log('Run outputs downloaded', array(
                'run_id' => $run_id,
                'downloaded' => count($downloaded_files),
                'failed' => count($failed_files),
                'files' => $downloaded_files
            ), 'success');
        }

        return $response;
    }

    /**
     * Download a result file
     * First get signed URL, then download
     */
    public function download_result($file_path)
    {
        $url = $this->file_url . '/api/v1/files/download-url?' . http_build_query(array('path' => $file_path, 'project_id' => $this->project_id));

        $response = wp_remote_get($url, array(
            'headers' => $this->get_headers(),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }
        $response_code = wp_remote_retrieve_response_code($response);
        $response_data = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code >= 400) {
            return array('error' => 'Failed to download file', 'status_code' => $response_code);
        }

        return $response_data;
    }

    /**
     * List available pipelines
     */
    public function list_pipelines()
    {
        $url = $this->compute_url . '/api/v1/pipelines';

        $response = wp_remote_get($url, array(
            'headers' => $this->get_headers(),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Test connection to all services
     */
    public function test_connection()
    {
        $results = array(
            'file' => $this->test_service($this->file_url),
            'compute' => $this->test_service($this->compute_url),
            'admin' => $this->test_service($this->admin_url),
        );

        $all_success = $results['file']['success'] && $results['compute']['success'] && $results['admin']['success'];

        return array(
            'success' => $all_success,
            'services' => $results,
            'message' => $all_success ? 'All services connected' : 'Some services failed'
        );
    }

    private function test_service($url)
    {
        $response = wp_remote_get($url . '/health', array(
            'headers' => array('Authorization' => 'Bearer ' . $this->api_token),
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message(), 'url' => $url);
        }

        $code = wp_remote_retrieve_response_code($response);
        return array(
            'success' => $code === 200,
            'status_code' => $code,
            'message' => $code === 200 ? 'Connected' : 'Failed',
            'url' => $url
        );
    }
    function get_instrument_files($assens)
    {
        global $wpdb;

        if (empty($assens) || !is_array($assens)) {
            return [];
        }

        $assen_conditions = array_map(function ($a) use ($wpdb) {
            return $wpdb->prepare("FIND_IN_SET(%s, rf_assen_list)", $a);
        }, $assens);
        $assen_sql = '(' . implode(' OR ', $assen_conditions) . ')';


        $query = "
			SELECT rf_file 
			FROM ci_reports_files 
			WHERE rf_btype = 'gene'
			AND {$assen_sql}
			ORDER BY rf_created DESC
		";

        $result_files = $wpdb->get_col($query);

        if (empty($result_files)) {
            return [];
        }

        $base_dir = WP_CONTENT_DIR . '/log/results_gene/';
        $final_files = [];

        foreach ($result_files as $fname) {
            $full_path = $base_dir . $fname;
            if (file_exists($full_path) && is_file($full_path)) {
                $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                if ($ext == 'vcf') {
                    $final_files[] = $full_path;
                }
            }
        }

        return $final_files;
    }
    public function upload_gene_files()
    {
        global $wpdb;
        $btype = 'gene';
        $batch_tbl = tr_pdd_get_table_by_btype($btype);
        $report_tbl = tr_pdd_get_table_report_by_btype($btype);

        $where = " and (api.mo_ID is null or api.mo_sent = 0 or api.mo_sent = 2) ";
        $where .= " and ba.batch_id > 0 and ba.patient_id > 0 ";
        $where .= " and (re.estatus !='C' or re.estatus is null) ";

        $sql = "select ba.assen, ba.mid, ba.patient_id as pa_ID, api.mo_ID, api.other_info, api.mo_sent
            from {$batch_tbl} ba
			LEFT JOIN {$report_tbl} re ON re.patient_id = ba.assen
            left JOIN ci_sync_api_orders api ON api.mo_mid = ba.mid
                                        and api.mo_file_id = '{$this->api_id}'
                                        and api.mo_btype = '{$btype}'
             where 1 {$where}
             order by mid desc";
        $rows = $wpdb->get_results($sql);
        if (count($rows) === 0) {
            $this->log('No records to process', array(), 'info');
            return null;
        }

        $pipelines = $this->list_pipelines();
        if (isset($pipelines['error']) || empty($pipelines)) {
            $this->log('Failed to get pipelines', $pipelines, 'error');
            return array('error' => 'Cannot get pipeline list');
        }

        $pipeline_id = isset($pipelines[0]['id']) ? $pipelines[0]['id'] : null;
        if (!$pipeline_id) {
            $this->log('No pipeline found', array(), 'error');
            return array('error' => 'No pipeline available');
        }

        $assens = array_map(function ($row) {
            return $row->assen;
        }, $rows);
        $files = $this->get_instrument_files($assens);

        if (empty($files)) {
            $this->log('No files found to upload', array(), 'info');
            return null;
        }
        $results = array();
        foreach ($files as $file_path) {
            $gzipped_file = $this->gzip_file($file_path);

            if (isset($gzipped_file['error'])) {
                $this->log('Failed to gzip file', array(
                    'file' => basename($file_path),
                    'error' => $gzipped_file['error']
                ), 'error');
                continue;
            }

            $result = $this->process_genetics_file($gzipped_file['path'], $pipeline_id);

            if (isset($result['error'])) {

                if (file_exists($gzipped_file['path'])) {
                    unlink($gzipped_file['path']);
                }
                continue;
            }

            // Find matching row for this file
            $matching_row = null;
            foreach ($rows as $row) {
                $row_files = $this->get_instrument_files(array($row->assen));
                if (in_array($file_path, $row_files)) {
                    $matching_row = $row;
                    break;
                }
            }

            if ($matching_row) {
                // Prepare data for database
                $data = array(
                    'file_path' => $file_path,
                    'gzipped_file' => basename($gzipped_file['path']),
                    'remote_path' => $result['remote_file_path'],
                    'uploaded_at' => date_i18n('Y-m-d H:i:s')
                );

                $update = array(
                    'mo_file_id' => $this->api_id,
                    'mo_mid' => $matching_row->mid,
                    'mo_btype' => $btype,
                    'mo_patient_ID' => $matching_row->pa_ID,
                    'other_info' => json_encode($data),
                    'mo_peg_ID' => $result['run_id'],
                    'mo_sent' => 1,
                    'mo_sent_dt' => date_i18n('Y-m-d H:i:s')
                );

                if ($matching_row->mo_ID > 0) {
                    // Update existing record
                    $update_result = $wpdb->update(
                        'ci_sync_api_orders',
                        $update,
                        array('mo_ID' => $matching_row->mo_ID)
                    );

                    if ($update_result === false) {
                        $this->log('Failed to update api_orders', array(
                            'mo_ID' => $matching_row->mo_ID,
                            'run_id' => $result['run_id'],
                            'error' => $wpdb->last_error,
                            'query' => $wpdb->last_query
                        ), 'error');
                    }
                } else {
                    $insert_result = $wpdb->insert('ci_sync_api_orders', $update);

                    if ($insert_result === false) {
                        $this->log('Failed to create api_orders', array(
                            'mid' => $matching_row->mid,
                            'run_id' => $result['run_id'],
                            'error' => $wpdb->last_error,
                            'query' => $wpdb->last_query,
                            'data' => $update
                        ), 'error');
                    }
                }

                $results[] = array(
                    'file' => basename($file_path),
                    'run_id' => $result['run_id'],
                    'status' => 'success'
                );
            }

            if (file_exists($gzipped_file['path'])) {
                unlink($gzipped_file['path']);
            }
        }

        return $results;
    }
    private function gzip_file($file_path)
    {
        if (!file_exists($file_path)) {
            return array('error' => 'File not found: ' . $file_path);
        }

        // Check if file is already gzipped
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        if ($extension === 'gz') {
            return array('path' => $file_path, 'already_gzipped' => true);
        }

        $gzipped_path = $file_path . '.gz';

        $file_content = file_get_contents($file_path);
        if ($file_content === false) {
            return array('error' => 'Failed to read file: ' . $file_path);
        }

        $gzipped_content = gzencode($file_content, 9);
        if ($gzipped_content === false) {
            return array('error' => 'Failed to gzip file: ' . $file_path);
        }

        $write_result = file_put_contents($gzipped_path, $gzipped_content);
        if ($write_result === false) {
            return array('error' => 'Failed to write gzipped file: ' . $gzipped_path);
        }

        return array('path' => $gzipped_path);
    }
    public function get_result_file()
    {
        global $wpdb;
        $limit = 1;
        $btype = 'gene';
        $batch_tbl = tr_pdd_get_table_by_btype($btype);

        $time_check = date_i18n('Y-m-d H:i:s', current_time('timestamp'));

        $where = $wpdb->prepare("
                AND api.mo_sent >= 1
                AND api.mo_status = 0
                AND api.mo_sent_dt < %s
            ", $time_check);
        $where .= " AND ba.batch_id > 0 AND ba.patient_id > 0 ";

        $sql = "SELECT ba.*,
            api.mo_ID,
            api.mo_peg_ID,
            api.other_info,
            '{$btype}' as btype
            FROM {$batch_tbl} ba
            JOIN ci_sync_api_orders api ON api.mo_mid = ba.mid
                AND api.mo_file_id = '{$this->api_id}'
                AND api.mo_btype = '{$btype}'
            WHERE 1 {$where}
            ORDER BY mid DESC
            LIMIT {$limit}";

        $rows = $wpdb->get_results($sql);

        if (empty($rows)) {
            return null;
        }

        $results = array();
        foreach ($rows as $row) {
            $run_id = $row->mo_peg_ID;
            if (!isset($run_id)) {
                continue;
            }

            $status_result = $this->check_run_status($run_id);

            if (isset($status_result['error'])) {
                $this->log('Failed to check run status', array(
                    'run_id' => $run_id,
                    'error' => $status_result
                ), 'error');
                continue;
            }

            $run_status = isset($status_result['status']) ? $status_result['status'] : '';

            $this->log('Run status checked', array(
                'run_id' => $run_id,
                'status' => $run_status,
                'assen' => $row->assen
            ), 'info');

            if ($run_status !== 'succeeded') {
                continue;
            }

            $download_result = $this->download_run_output($run_id);

            if (isset($download_result['error'])) {
                continue;
            }

            if (empty($download_result['files'])) {
                continue;
            }

            $saved_files = array();
            foreach ($download_result['files'] as $file_info) {
                $download_url = $file_info['url'];
                $file_name = $file_info['file_name'];
                $file_response = wp_remote_get($download_url, array(
                    'timeout' => 300
                ));

                if (is_wp_error($file_response)) {
                    continue;
                }

                $file_content = wp_remote_retrieve_body($file_response);

                if (strlen($file_content) < 100) {
                    continue;
                }

                $dir = tr_get_result_upload_dir();
                if (!file_exists($dir['path'])) {
                    wp_mkdir_p($dir['path']);
                }

                $local_filename = strtolower($row->assen) . '_iflow_' . $file_name;
                $local_path = $dir['path'] . '/' . $local_filename;

                $write_result = file_put_contents($local_path, $file_content);

                if ($write_result === false) {
                    continue;
                }

                $saved_files[] = $local_filename;
            }

            if (!empty($saved_files)) {

                $row->result_pdf = strtolower($row->result_pdf);
                $result_pdfs = !empty($row->result_pdf) ? explode(',', $row->result_pdf) : array();

                foreach ($saved_files as $saved_file) {
                    if (!in_array($saved_file, $result_pdfs)) {
                        $result_pdfs[] = $saved_file;
                    }
                }

                $wpdb->update(
                    $batch_tbl,
                    array('result_pdf' => implode(',', $result_pdfs)),
                    array('mid' => $row->mid)
                );


                $wpdb->update(
                    'ci_sync_api_orders',
                    array(
                        'mo_status' => 1,
                    ),
                    array('mo_ID' => $row->mo_ID)
                );

                $this->log('Results downloaded and saved', array(
                    'run_id' => $run_id,
                    'assen' => $row->assen,
                    'files' => $saved_files
                ), 'success');

                $results[] = array(
                    'assen' => $row->assen,
                    'run_id' => $run_id,
                    'files' => $saved_files,
                    'status' => 'success'
                );
            }
        }
        return $results;
    }
}
