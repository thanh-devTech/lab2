<link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__) . '../css/admin_show_db.css'; ?>">
<div class="wrap">
    <h1>WP API Tester</h1>
    <form method="post" action="">
        <?php wp_nonce_field('wp_api_tester_action', 'wp_api_tester_nonce'); ?>

        <div class="form-row">
            <!-- Endpoint input -->
            <label for="endpoint">Endpoint URL:</label>
            <input type="text" id="endpoint" name="endpoint" 
                value="<?php echo esc_attr($_POST['endpoint'] ?? ''); ?>">

            <!-- HTTP method -->
            <label for="method">Method:</label>
            <select id="method" name="method">
                <option value="GET" <?php selected($_POST['method'] ?? '', 'GET'); ?>>GET</option>
                <option value="POST" <?php selected($_POST['method'] ?? '', 'POST'); ?>>POST</option>
                <option value="PUT" <?php selected($_POST['method'] ?? '', 'PUT'); ?>>PUT</option>
                <option value="DELETE" <?php selected($_POST['method'] ?? '', 'DELETE'); ?>>DELETE</option>
            </select>
        </div>

        <!-- Custom headers -->
        <label>Headers (Key : Value)</label><br>
        <textarea name="headers" rows="4" cols="60" placeholder="Authorization: Bearer token123"><?php 
            echo esc_textarea($_POST['headers'] ?? ''); 
        ?></textarea><br><br>

        <!-- Request body -->
        <label>Body (JSON):</label><br>
        <textarea name="body" rows="5" cols="60"><?php echo esc_textarea($_POST['body'] ?? ''); ?></textarea><br><br>

        <input type="submit" name="send_request" class="button button-primary" value="Send Request">
    </form>

    <?php
    // Handle request
    if ( isset($_POST['send_request']) && check_admin_referer('wp_api_tester_action','wp_api_tester_nonce') ) {

        // Parse headers from textarea
        $headers_input = array_filter(array_map('trim', explode("\n", $_POST['headers'] ?? '')));
        $headers = [];
        foreach ($headers_input as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }

        // Prepare request args
        $args = [
            'method'  => sanitize_text_field($_POST['method']),
            'headers' => !empty($headers) ? $headers : ['Content-Type' => 'application/json'],
        ];
        if (!empty($_POST['body'])) {
            $args['body'] = stripslashes($_POST['body']);
        }

        // Send API request
        $response = wp_remote_request( esc_url_raw($_POST['endpoint']), $args );

        echo '<h2>Response:</h2>';

        if (is_wp_error($response)) {
            echo '<pre>'. esc_html($response->get_error_message()) .'</pre>';
        } else {
            $body = wp_remote_retrieve_body($response);
            $decoded = json_decode($body, true);
            if ($decoded) {
                echo '<pre><code>' . esc_html(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</code></pre>';
            } else {
                echo '<pre><code>' . esc_html($body) . '</code></pre>';
            }
        }

        // Save history (max 10 items)
        $history = get_option('wp_api_tester_history', []);
        array_unshift($history, [
            'endpoint' => sanitize_text_field($_POST['endpoint']),
            'method'   => sanitize_text_field($_POST['method']),
            'headers'  => $_POST['headers'],
            'body'     => $_POST['body'],
            'time'     => current_time('mysql')
        ]);
        $history = array_slice($history, 0, 10); // keep only last 10
        update_option('wp_api_tester_history', $history);
    }

    // Display history
    $history = get_option('wp_api_tester_history', []);
    if (!empty($history)) {
        echo '<h2>History (last 10 requests)</h2>';
        echo '<table class="wp-db-table-list"><thead><tr><th>Time</th><th>Method</th><th>Endpoint</th><th>Action</th></tr></thead><tbody>';
        foreach ($history as $index => $item) {
            echo '<tr>';
            echo '<td>' . esc_html($item['time']) . '</td>';
            echo '<td>' . esc_html($item['method']) . '</td>';
            echo '<td>' . esc_html($item['endpoint']) . '</td>';
            echo '<td>
                <form method="post" style="display:inline;">
                    ' . wp_nonce_field('wp_api_tester_action', 'wp_api_tester_nonce', true, false) . '
                    <input type="hidden" name="endpoint" value="' . esc_attr($item['endpoint']) . '">
                    <input type="hidden" name="method" value="' . esc_attr($item['method']) . '">
                    <input type="hidden" name="headers" value="' . esc_attr($item['headers']) . '">
                    <input type="hidden" name="body" value="' . esc_attr($item['body']) . '">
                    <input type="submit" name="send_request" class="button" value="Run Again">
                </form>
            </td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
    ?>
</div>
