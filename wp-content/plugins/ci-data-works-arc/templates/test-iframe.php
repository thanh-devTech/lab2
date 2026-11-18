<?php
if (!class_exists('DrChrono_Lab')) {
    require_once(TRCPDD_PATH . 'api/ci_api_drchrono.php');
}
$lab = DrChrono_Lab::get_instance();

// Check if plugin is configured
$client_secret = $lab->get_option('client_secret');
if (!$client_secret) {
?>
    <!DOCTYPE html>
    <html>

    <head>
        <meta charset="UTF-8">
        <title>Setup Required</title>
        <style>
            .notice {
                background: #fff3cd;
                border: 1px solid #ffc107;
                padding: 20px;
                border-radius: 8px;
            }

            .notice h1 {
                margin-top: 0;
                color: #856404;
            }

            .notice p {
                color: #856404;
                line-height: 1.6;
            }

            .btn {
                display: inline-block;
                padding: 12px 24px;
                background: #2563eb;
                color: white;
                text-decoration: none;
                border-radius: 6px;
                margin-top: 16px;
            }

            .btn:hover {
                background: #1d4ed8;
            }
        </style>
    </head>

    <body>
        <div class="notice">
            <h1>&#9888;&#65039; DrChrono Not Configured</h1>
            <p>You need to configure DrChrono credentials first before testing the iFrame.</p>
            <p><strong>Next steps:</strong></p>
            <ol>
                <li>Go to Settings &gt; DrChrono</li>
                <li>Enter your Client ID and Client Secret</li>
                <li>Get Access Token</li>
                <li>Create Sublab</li>
                <li>Come back to this page</li>
            </ol>
            <a href="<?php echo admin_url('admin.php?page=ci_pdf_data&pagetab=ci_wsdl_keys&tab=other&subtab=api_drchrono'); ?>" class="btn">Go to Settings</a>
        </div>
    </body>

    </html>
<?php
    exit;
}

$test_doctor_id   = isset($_GET['doctor_id'])   ? intval($_GET['doctor_id'])   : 524547;
$test_patient_id  = isset($_GET['patient_id'])  ? intval($_GET['patient_id'])  : 132994059;
$test_practice_id = isset($_GET['practice_id']) ? intval($_GET['practice_id']) : 514875;
$test_iat         = time();

// Generate test JWT
function drchrono_test_base64url_encode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

$header = ['alg' => 'HS256', 'typ' => 'JWT'];
$payload = [
    'doctor_id'   => $test_doctor_id,
    'patient_id'  => $test_patient_id,
    'practice_id' => $test_practice_id,
    'iat'         => $test_iat,
    'exp'         => $test_iat + (15 * 60),
];

$header_encoded  = drchrono_test_base64url_encode(json_encode($header));
$payload_encoded = drchrono_test_base64url_encode(json_encode($payload));
$signature       = drchrono_test_base64url_encode(hash_hmac('sha256', "$header_encoded.$payload_encoded", $client_secret, true));
$test_jwt        = "$header_encoded.$payload_encoded.$signature";

// Get iframe page URL
$page = get_page_by_path('lab-requisition');
$iframe_url = $page ? get_permalink($page->ID) : home_url('/lab-requisition/');

// Build full URL with params
$full_url = add_query_arg([
    'doctor_id'   => $test_doctor_id,
    'patient_id'  => $test_patient_id,
    'practice_id' => $test_practice_id,
    'iat'         => $test_iat,
    'jwt'         => $test_jwt,
], $iframe_url);

$current_page_url = admin_url('admin.php?page=drchrono-lab');

$token       = $lab->get_token();
$is_connected = !empty($token);
$sublab_id   = $lab->get_option('sublab_id');

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DrChrono iFrame Test</title>
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            margin: 0;
            background: #f1f5f9;
        }

        .wrap {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 20px 28px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.35);
        }

        .header h1 {
            font-size: 20px;
            margin: 0 0 4px;
        }

        .header p {
            font-size: 13px;
            opacity: 0.85;
            margin: 0;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 14px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.success {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }

        .status-badge.error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        /* Grid */
        .grid {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 18px;
        }

        @media (max-width: 1024px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }

        /* Panel */
        .panel {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
        }

        .panel h2 {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #64748b;
            margin: 0 0 14px;
        }

        /* Form fields */
        .field {
            margin-bottom: 12px;
        }

        .field label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .field input[type="number"],
        .field input[type="text"] {
            width: 100%;
            padding: 8px 10px;
            font-size: 13px;
            font-family: 'Courier New', monospace;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #f8fafc;
            color: #1e293b;
            transition: border-color 0.15s;
        }

        .field input:focus {
            outline: none;
            border-color: #667eea;
            background: #fff;
        }

        /* Info boxes (read-only display) */
        .info-value {
            font-size: 11px;
            padding: 7px 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            word-break: break-all;
            color: #475569;
            line-height: 1.5;
        }

        /* Buttons */
        .btn-row {
            display: flex;
            gap: 8px;
            margin-top: 16px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.15s;
        }

        .btn-primary {
            background: #667eea;
            color: #fff;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-reload {
            background: #f59e0b;
            color: #fff;
        }

        .btn-reload:hover {
            background: #d97706;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .btn-danger {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .btn-danger:hover {
            background: #fecaca;
        }

        /* Divider */
        .divider {
            height: 1px;
            background: #e2e8f0;
            margin: 14px 0;
        }

        /* iFrame container */
        .iframe-container {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
            display: flex;
            flex-direction: column;
            height: calc(100vh - 160px);
            min-height: 600px;
        }

        .iframe-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            color: #64748b;
            gap: 10px;
        }

        .iframe-header .url-text {
            font-family: 'Courier New', monospace;
            font-size: 10px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex: 1;
        }

        iframe {
            width: 100%;
            flex: 1;
            border: none;
        }

        /* Spinner */
        .spinner {
            display: none;
            width: 14px;
            height: 14px;
            border: 2px solid #e2e8f0;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .loading .spinner {
            display: inline-block;
        }
    </style>
</head>

<body>
    <div class="wrap">

        <!-- Header -->
        <div class="header">
            <div>
                <h1>&#128300; DrChrono iFrame Test Environment</h1>
                <p>Testing lab requisition form — IDs</p>
            </div>
            <?php if ($is_connected): ?>
                <span class="status-badge success"><span class="status-dot"></span> Connected</span>
            <?php else: ?>
                <span class="status-badge error"><span class="status-dot"></span> Not Connected</span>
            <?php endif; ?>
        </div>

        <div class="grid">

            <div>
                <div class="panel">

                    <!-- Editable IDs form -->
                    <h2>&#9999;&#65039; Test IDs (can be edited)</h2>
                    <form method="GET" action="" id="ids-form">
                        <div class="field">
                            <label for="doctor_id">Doctor ID</label>
                            <input type="number" id="doctor_id" name="doctor_id" value="<?php echo esc_attr($test_doctor_id); ?>">
                        </div>
                        <div class="field">
                            <label for="patient_id">Patient ID</label>
                            <input type="number" id="patient_id" name="patient_id" value="<?php echo esc_attr($test_patient_id); ?>">
                        </div>
                        <div class="field">
                            <label for="practice_id">Practice ID</label>
                            <input type="number" id="practice_id" name="practice_id" value="<?php echo esc_attr($test_practice_id); ?>">
                        </div>

                        <div class="btn-row">
                            <button type="button" class="btn btn-primary" onclick="reloadIframe()">&#8635; Reload iFrame</button>
                        </div>
                    </form>

                    <div class="divider"></div>

                    <!-- Status info -->
                    <h2>&#128202; Test Information</h2>

                    <?php if ($sublab_id): ?>
                        <div class="field">
                            <label>Sublab ID</label>
                            <div class="info-value"><?php echo esc_html($sublab_id); ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="field">
                        <label>IAT (issued at)</label>
                        <div class="info-value"><?php echo $test_iat; ?> (<?php echo date('H:i:s', $test_iat); ?>)</div>
                    </div>

                    <div class="field">
                        <label>JWT Token (preview)</label>
                        <div class="info-value" style="font-size: 10px;">
                            <?php echo esc_html(substr($test_jwt, 0, 60)); ?>...
                        </div>
                    </div>

                    <div class="field">
                        <label>iFrame URL</label>
                        <div class="info-value"><?php echo esc_html($iframe_url); ?></div>
                    </div>

                    <div class="divider"></div>

                    <!-- Extra actions -->
                    <div class="btn-row">
                        <a href="<?php echo esc_url($full_url); ?>" target="_blank" class="btn btn-secondary">
                            &#128279; Open iFrame in New Tab
                        </a>
                        <a href="<?php echo admin_url('options-general.php?page=drchrono-lab'); ?>" class="btn btn-secondary">
                            &#9881;&#65039; Settings
                        </a>
                    </div>

                </div>
            </div>

            <!-- ===== RIGHT PANEL: iFrame ===== -->
            <div class="iframe-container" id="iframe-wrap">
                <div class="iframe-header" id="iframe-header">
                    <span>&#128065; Live Preview</span>
                    <span class="url-text" id="url-display"><?php echo esc_html(parse_url($full_url, PHP_URL_PATH)); ?></span>
                    <span class="spinner" id="spinner"></span>
                    <button class="btn btn-reload" style="padding:4px 10px;font-size:11px;" onclick="reloadIframe()">&#8635; Reload</button>
                </div>
                <iframe id="test_frame" src="<?php echo esc_url($full_url); ?>"></iframe>
            </div>

        </div><!-- .grid -->

    </div><!-- .wrap -->

    <script>
        var iframeBaseUrl = '<?php echo esc_js($iframe_url); ?>';

        function reloadIframe() {
            var doctor_id = document.getElementById('doctor_id').value || '524547';
            var patient_id = document.getElementById('patient_id').value || '132994059';
            var practice_id = document.getElementById('practice_id').value || '514875';
            const url = new URL(window.location.href);
            url.searchParams.set('doctor_id', doctor_id);
            url.searchParams.set('patient_id', patient_id);
            url.searchParams.set('practice_id', practice_id);
            window.location.href = url.toString();
        }
    </script>

</body>

</html>