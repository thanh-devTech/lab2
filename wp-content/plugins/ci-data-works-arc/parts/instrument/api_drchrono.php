<?php

if (!class_exists('DrChrono_Lab')) {
    include(TRCPDD_PATH . 'api/ci_api_drchrono.php');
}

if (isset($_POST['action_run_cron'])) {
    if (!current_user_can('manage_options')) {
        Tr_Session_Class::add_error('Unauthorized.');
    } else {
        $lab = DrChrono_Lab::get_instance();

        ob_start();
        $lab->run_cron();
        ob_end_clean();

        Tr_Session_Class::add('Cron drchrono_send_lab_results ran successfully.');
    }
}
if (isset($_POST['action_run_get_results'])) {
    if (!current_user_can('manage_options')) {
        Tr_Session_Class::add_error('Unauthorized.');
    } else {
        $lab = DrChrono_Lab::get_instance();

        ob_start();
        $lab->run_drchrono_results();
        ob_end_clean();

        Tr_Session_Class::add('Cron get_drchrono_results ran successfully.');
    }
}

// ── Save Settings ──
if (isset($_POST['options'])) {
    $posted = $_POST['options'];
    $options = get_option('tr_dchrono_st', array());

    if (empty($options['cron'])) {
        $options['cron'] = 2;
    }
    if (isset($posted['client_id']))        $options['client_id']        = sanitize_text_field($posted['client_id']);
    if (isset($posted['client_secret']))    $options['client_secret']    = sanitize_text_field($posted['client_secret']);
    if (isset($posted['redirect_uri']))     $options['redirect_uri']     = esc_url_raw($posted['redirect_uri']);
    if (isset($posted['facility_code']))    $options['facility_code']    = sanitize_text_field($posted['facility_code']);
    if (isset($posted['lab_name']))         $options['lab_name']         = sanitize_text_field($posted['lab_name']);
    if (isset($posted['sublab_id']))        $options['sublab_id']        = absint($posted['sublab_id']);
    if (isset($posted['lis_endpoint_url'])) $options['lis_endpoint_url'] = esc_url_raw($posted['lis_endpoint_url']);
    if (isset($posted['lis_secret']))       $options['lis_secret']       = sanitize_text_field($posted['lis_secret']);
    if (isset($posted['cron']))             $options['cron']             = absint($posted['cron']);
    if (isset($posted['code']))             $options['code']             = sanitize_text_field($posted['code']);

    update_option('tr_dchrono_st', $options);

    if (isset($posted['client_id']) || isset($posted['client_secret'])) {
        delete_transient('drchrono_access_token');
    }

    // Tr_Session_Class::add('Saved Settings Successfully');
    $options = get_option('tr_dchrono_st', array());
}

// ── Handle: Auth – exchange code → access_token ──
if (isset($_POST['action_auth'])) {
    $options       = get_option('tr_dchrono_st', array());

    $client_id     = sanitize_text_field($_POST['auth_client_id']     ?? $options['client_id']     ?? '');
    $client_secret = sanitize_text_field($_POST['auth_client_secret'] ?? $options['client_secret'] ?? '');
    $redirect_uri  = esc_url_raw($_POST['auth_redirect_uri']          ?? $options['redirect_uri']  ?? '');
    $code          = sanitize_text_field($_POST['auth_code']          ?? '');

    if (!$client_id || !$client_secret || !$redirect_uri || !$code) {
        Tr_Session_Class::add('Please fill in Client ID, Client Secret, Redirect URI and Code first.', 'error');
    } else {
        $response = wp_remote_post('https://app.drchrono.com/o/token/', [
            'body' => [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => $redirect_uri,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
            ],
        ]);

        $body      = json_decode(wp_remote_retrieve_body($response), true);
        $http_code = wp_remote_retrieve_response_code($response);

        if ($http_code === 200 && !empty($body['access_token'])) {
            $expires_in = (int)($body['expires_in'] ?? 172800);

            $options['access_token']  = $body['access_token'];
            $options['refresh_token'] = $body['refresh_token'] ?? '';
            $options['token_expires'] = time() + $expires_in;
            $options['code']          = '';

            update_option('tr_dchrono_st', $options);
            set_transient('drchrono_access_token', $body['access_token'], $expires_in);

            Tr_Session_Class::add('Connected successfully! Access token saved.');
        } else {
            $detail = $body['error_description'] ?? ($body['error'] ?? 'Unknown error');
            Tr_Session_Class::add_error('Failed to get token: ' . $detail);
        }
    }
}

if (isset($_POST['action_create_sublab'])) {
    $options      = get_option('tr_dchrono_st', array());
    $access_token = get_transient('drchrono_access_token');

    if (!$access_token) {
        Tr_Session_Class::add('Not connected to DrChrono. Please connect first.', 'error');
    } else {
        $response = wp_remote_post('https://app.drchrono.com/api/sublabs', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode([
                'name'          => $options['lab_name']      ?? '',
                'facility_code' => $options['facility_code'] ?? '',
            ]),
        ]);

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);
        error_log(print_r($body, true));

        if ($code === 201 && !empty($body['id'])) {
            $options['sublab_id'] = $body['id'];
            update_option('tr_dchrono_st', $options);
            Tr_Session_Class::add('Sublab created successfully! ID: ' . $body['id']);
        } elseif ($code === 403) {
            Tr_Session_Class::add_error('Lab Vendor permission not enabled. Please email api@drchrono.com');
        } else {
            Tr_Session_Class::add_error('Failed to create sublab: ' . ($body['detail'] ?? 'Unknown error'));
        }
    }
}

// ── Handle: Revoke / Disconnect ──
if (isset($_POST['action_disconnect'])) {
    delete_transient('drchrono_access_token');
    $options = get_option('tr_dchrono_st', array());
    $options['access_token']  = '';
    $options['refresh_token'] = '';
    $options['token_expires'] = 0;
    update_option('tr_dchrono_st', $options);
    Tr_Session_Class::add('Disconnected from DrChrono.');
}

$options      = get_option('tr_dchrono_st', array());
$access_token = get_transient('drchrono_access_token') ?: ($options['access_token'] ?? '');
$is_connected = !empty($access_token);
$sublab_id    = $options['sublab_id'] ?? '';

// ── Check token còn hạn không ──
$token_expires = (int)($options['token_expires'] ?? 0);
$token_valid   = $is_connected && ($token_expires === 0 || $token_expires > time());

$callback_url  = home_url('/drchrono-callback/');
$lis_callback  = rest_url('drchrono-lab/v1/result');

?>

<div class="wrap drc-settings-wrap">

    <div class="drc-settings-header">
        <div class="drc-settings-header__icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18" />
            </svg>
        </div>
        <div>
            <h1 class="drc-settings-header__title">DrChrono Settings</h1>
            <p class="drc-settings-header__sub">Lab Integration Configuration</p>
        </div>
        <div class="drc-settings-header__status">
            <?php if ($token_valid): ?>
                <span class="drc-status drc-status--ok">
                    <span class="drc-status__dot"></span>
                    Connected
                </span>
            <?php else: ?>
                <span class="drc-status drc-status--off">
                    <span class="drc-status__dot"></span>
                    Not Connected
                </span>
            <?php endif; ?>
        </div>
    </div>

    <?php Tr_Session_Class::show(); ?>

    <?php if (!$token_valid): ?>
        <div class="drc-notice drc-notice--info">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="8" />
                <line x1="12" y1="12" x2="12" y2="16" />
            </svg>
            <div>
                <strong>Before connecting:</strong> Authorize via DrChrono and paste the <code>code</code> from the redirect URL into the <em>Code</em> field above, then click <strong>Get Access Token</strong>.
                <a href="https://app.drchrono.com/api-management/" target="_blank" class="drc-link">
                    Open DrChrono API Settings ↗
                </a>
            </div>
        </div>
    <?php endif; ?>

    <div class="drc-settings-grid">

        <div class="drc-card">
            <div class="drc-card__header">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
                <h2 class="drc-card__title">OAuth Credentials</h2>
            </div>

            <form method="post" class="drc-form">

                <div class="drc-field">
                    <label class="drc-label" for="client_id">
                        Client ID
                        <span class="drc-required">*</span>
                    </label>
                    <input type="text"
                        id="client_id"
                        name="options[client_id]"
                        class="drc-input"
                        value="<?php echo esc_attr($options['client_id'] ?? ''); ?>"
                        placeholder="lyVsybKRiVwo6aukN2...">
                </div>

                <div class="drc-field">
                    <label class="drc-label" for="client_secret">
                        Client Secret
                        <span class="drc-required">*</span>
                    </label>
                    <div class="drc-input-group">
                        <input type="password"
                            id="client_secret"
                            name="options[client_secret]"
                            class="drc-input"
                            value="<?php echo esc_attr($options['client_secret'] ?? ''); ?>"
                            placeholder="••••••••••••••••">
                        <button type="button" class="drc-btn-icon drc-toggle-secret" title="Show/Hide">
                            <svg class="icon-eye" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                            <svg class="icon-eye-off" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" />
                                <line x1="1" y1="1" x2="23" y2="23" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="drc-field">
                    <label class="drc-label" for="redirect_uri">
                        Redirect URI
                        <span class="drc-required">*</span>
                    </label>
                    <input type="url"
                        id="redirect_uri"
                        name="options[redirect_uri]"
                        class="drc-input"
                        value="<?php echo esc_attr($options['redirect_uri'] ?? $callback_url); ?>"
                        placeholder="<?php echo esc_attr($callback_url); ?>">
                    <span class="drc-hint">Must match exactly what's registered in DrChrono API App</span>
                </div>

                <div class="drc-field">
                    <label class="drc-label" for="code">
                        Authorization Code
                        <span class="drc-required">*</span>
                    </label>
                    <input type="text"
                        id="code"
                        name="options[code]"
                        class="drc-input"
                        value="<?php echo esc_attr($options['code'] ?? ''); ?>"
                        placeholder="Paste code from DrChrono redirect URL">
                    <span class="drc-hint">
                        Get this by visiting:
                        <?php
                        $auth_url = 'https://app.drchrono.com/o/authorize/?' . http_build_query([
                            'response_type' => 'code',
                            'client_id'     => $options['client_id'] ?? '',
                            'redirect_uri'  => $options['redirect_uri'] ?? $callback_url,
                        ]);
                        ?>
                        <a href="<?php echo esc_url($auth_url); ?>" target="_blank" class="drc-link">
                            DrChrono Authorization ↗
                        </a>
                        — copy the <code>code=…</code> value from the redirect URL and paste here
                    </span>
                </div>

                <div class="drc-card__footer">
                    <button type="submit" name="options[save]" class="drc-btn drc-btn--primary">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                            <polyline points="17 21 17 13 7 13 7 21" />
                            <polyline points="7 3 7 8 15 8" />
                        </svg>
                        Save Settings
                    </button>
                </div>

            </form>
        </div>

        <div class="drc-card">
            <div class="drc-card__header">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
                    <polyline points="15 3 21 3 21 9" />
                    <line x1="10" y1="14" x2="21" y2="3" />
                </svg>
                <h2 class="drc-card__title">DrChrono Connection <a href="<?php echo esc_url(admin_url('admin.php?page=drchrono-lab')); ?>" target="_blank" class="drc-link">Test iframe</a></h2>
            </div>

            <?php if ($token_valid): ?>
                <div class="drc-connected-info">
                    <div class="drc-connected-info__row">
                        <span class="drc-connected-info__label">Status</span>
                        <span class="drc-connected-info__value drc-text--green">✓ Authorized</span>
                    </div>
                    <?php if ($token_expires > 0): ?>
                        <div class="drc-connected-info__row">
                            <span class="drc-connected-info__label">Token expires</span>
                            <span class="drc-connected-info__value">
                                <?php echo human_time_diff(time(), $token_expires); ?> from now
                            </span>
                        </div>
                    <?php endif; ?>
                    <div class="drc-connected-info__row">
                        <span class="drc-connected-info__label">Token</span>
                        <span class="drc-connected-info__value drc-mono">
                            <?php echo esc_html(substr($access_token, 0, 20) . '...'); ?>
                        </span>
                    </div>
                </div>

                <form method="post" class="drc-form">
                    <div class="drc-card__footer">
                        <button type="submit" name="action_disconnect" class="drc-btn drc-btn--danger"
                            onclick="return confirm('Disconnect from DrChrono?')">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18.36 6.64a9 9 0 1 1-12.73 0" />
                                <line x1="12" y1="2" x2="12" y2="12" />
                            </svg>
                            Disconnect
                        </button>
                    </div>
                </form>

            <?php else: ?>
                <div class="drc-connect-prompt">
                    <div class="drc-connect-prompt__icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" />
                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" />
                        </svg>
                    </div>
                    <p class="drc-connect-prompt__text">
                        Exchange your authorization code to get an access token.
                    </p>
                    <p class="drc-connect-prompt__step">
                        Step 1: Save Client ID, Secret &amp; Redirect URI (Card 1)<br>
                        Step 2: Click
                        <?php
                        $auth_url2 = 'https://app.drchrono.com/o/authorize/?' . http_build_query([
                            'response_type' => 'code',
                            'client_id'     => $options['client_id'] ?? '',
                            'redirect_uri'  => $options['redirect_uri'] ?? $callback_url,
                        ]);
                        ?>
                        <a href="<?php echo esc_url($auth_url2); ?>" target="_blank" class="drc-link">DrChrono Authorization ↗</a><br>
                        Step 3: Copy <code>code=...</code> tu redirect URL → paste ben duoi<br>
                        Step 4: Click <strong>Get Access Token</strong> in approximately 30 seconds
                    </p>
                </div>

                <?php /* Form auth: gui code truc tiep, KHONG can Save truoc */ ?>
                <form method="post" class="drc-form">
                    <input type="hidden" name="auth_client_id" value="<?php echo esc_attr($options['client_id']     ?? ''); ?>">
                    <input type="hidden" name="auth_client_secret" value="<?php echo esc_attr($options['client_secret'] ?? ''); ?>">
                    <input type="hidden" name="auth_redirect_uri" value="<?php echo esc_attr($options['redirect_uri']  ?? $callback_url); ?>">

                    <div class="drc-field">
                        <label class="drc-label" for="auth_code_input">
                            Authorization Code
                            <span class="drc-required">*</span>
                        </label>
                        <input type="text"
                            id="auth_code_input"
                            name="auth_code"
                            class="drc-input"
                            value=""
                            autocomplete="off"
                            placeholder="Paste code tu DrChrono redirect URL tai day">
                        <span class="drc-hint" style="color:#ea580c">&#9888; The code expires in approximately 30 seconds — paste it and click immediately.!</span>
                    </div>

                    <div class="drc-card__footer">
                        <button type="submit" name="action_auth"
                            class="drc-btn drc-btn--primary"
                            <?php echo empty($options['client_id']) ? 'disabled title="Save Client ID & Secret first"' : ''; ?>>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                                <polyline points="10 17 15 12 10 7" />
                                <line x1="15" y1="12" x2="3" y2="12" />
                            </svg>
                            Get Access Token
                        </button>
                    </div>
                </form>
            <?php endif; ?>

        </div>

        <div class="drc-card">
            <div class="drc-card__header">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18" />
                </svg>
                <h2 class="drc-card__title">Lab Configuration</h2>
            </div>

            <form method="post" class="drc-form">

                <div class="drc-field">
                    <label class="drc-label" for="lab_name">
                        Lab Name
                        <span class="drc-required">*</span>
                    </label>
                    <input type="text"
                        id="lab_name"
                        name="options[lab_name]"
                        class="drc-input"
                        value="<?php echo esc_attr($options['lab_name'] ?? ''); ?>"
                        placeholder="My Lab Inc.">
                </div>

                <div class="drc-field">
                    <label class="drc-label" for="facility_code">
                        Facility Code
                        <span class="drc-required">*</span>
                    </label>
                    <input type="text"
                        id="facility_code"
                        name="options[facility_code]"
                        class="drc-input"
                        value="<?php echo esc_attr($options['facility_code'] ?? ''); ?>"
                        placeholder="LAB001"
                        style="text-transform:uppercase">
                    <span class="drc-hint">Unique identifier for your lab</span>
                </div>

                <div class="drc-field">
                    <label class="drc-label" for="sublab_id">
                        Sublab ID
                    </label>
                    <div class="drc-input-group">
                        <input type="number"
                            id="sublab_id"
                            name="options[sublab_id]"
                            class="drc-input"
                            value="<?php echo esc_attr($sublab_id); ?>"
                            placeholder="Auto-filled after creation"
                            readonly>
                        <?php if ($sublab_id): ?>
                            <span class="drc-input-badge drc-input-badge--ok">✓ Set</span>
                        <?php else: ?>
                            <span class="drc-input-badge drc-input-badge--warn">Not set</span>
                        <?php endif; ?>
                    </div>
                    <span class="drc-hint">Auto-generated when you click "Create Sublab"</span>
                </div>

                <div class="drc-card__footer drc-card__footer--gap">
                    <button type="submit" name="options[save]" class="drc-btn drc-btn--secondary">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                            <polyline points="17 21 17 13 7 13 7 21" />
                            <polyline points="7 3 7 8 15 8" />
                        </svg>
                        Save
                    </button>
                    <button type="submit" name="action_create_sublab"
                        class="drc-btn drc-btn--primary"
                        <?php echo (!$token_valid || empty($options['lab_name'])) ? 'disabled title="Connect DrChrono and fill Lab Name first"' : ''; ?>>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="12" y1="8" x2="12" y2="16" />
                            <line x1="8" y1="12" x2="16" y2="12" />
                        </svg>
                        Create Sublab
                    </button>
                </div>

            </form>
        </div>

        <div class="drc-card">
            <div class="drc-card__header">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                </svg>
                <h2 class="drc-card__title">LIS Integration</h2>
            </div>

            <form method="post" class="drc-form">

                <div class="drc-field">
                    <label class="drc-label" for="lis_endpoint_url">
                        LIS Endpoint URL
                    </label>
                    <input type="url"
                        id="lis_endpoint_url"
                        name="options[lis_endpoint_url]"
                        class="drc-input"
                        value="<?php echo esc_attr($options['lis_endpoint_url'] ?? ''); ?>"
                        placeholder="https://your-lis-system.com/api/orders">
                    <span class="drc-hint">WordPress sẽ forward lab orders đến URL này</span>
                </div>

                <div class="drc-field">
                    <label class="drc-label" for="lis_secret">
                        LIS Shared Secret
                    </label>
                    <div class="drc-input-group">
                        <input type="password"
                            id="lis_secret"
                            name="options[lis_secret]"
                            class="drc-input"
                            value="<?php echo esc_attr($options['lis_secret'] ?? ''); ?>"
                            placeholder="••••••••••••••••">
                        <button type="button" class="drc-btn-icon drc-toggle-secret" title="Show/Hide">
                            <svg class="icon-eye" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                            <svg class="icon-eye-off" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" />
                                <line x1="1" y1="1" x2="23" y2="23" />
                            </svg>
                        </button>
                    </div>
                    <span class="drc-hint">LIS dùng header <code>X-LIS-Auth</code> để verify callback</span>
                </div>

                <!-- Callback URL readonly -->
                <div class="drc-field">
                    <label class="drc-label">LIS Callback URL</label>
                    <div class="drc-input-group">
                        <input type="text"
                            class="drc-input drc-input--readonly"
                            value="<?php echo esc_attr($lis_callback); ?>"
                            readonly
                            id="lis-callback-url">
                        <button type="button" class="drc-btn-icon drc-copy-btn" data-target="lis-callback-url" title="Copy">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                            </svg>
                        </button>
                    </div>
                    <span class="drc-hint">Provide this URL to your LIS system for result callbacks</span>
                </div>

                <div class="drc-card__footer">
                    <button type="submit" name="options[save]" class="drc-btn drc-btn--primary">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                            <polyline points="17 21 17 13 7 13 7 21" />
                            <polyline points="7 3 7 8 15 8" />
                        </svg>
                        Save Settings
                    </button>
                </div>

            </form>
        </div>
        <div class="drc-card">
            <div class="drc-card__header">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 4 23 10 17 10" />
                    <polyline points="1 20 1 14 7 14" />
                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
                </svg>
                <h2 class="drc-card__title">Manual Cron</h2>
            </div>

            <div class="drc-form">
                <p style="color:#6b7280;font-size:13px;margin:0 0 16px">
                    Manually trigger cron jobs for testing. Check <code>wp-content/log/ci_drchrono.log</code> for output.
                </p>

                <div style="display:flex;flex-direction:column;gap:12px">

                    <form method="post">
                        <?php wp_nonce_field('drchrono_manual_cron'); ?>
                        <button type="submit" name="action_run_cron"
                            class="drc-btn drc-btn--secondary"
                            style="width:100%"
                            <?php echo !$token_valid ? 'disabled title="Connect DrChrono first"' : ''; ?>
                            onclick="return confirm('Run send_lab_results cron now?')">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="5 3 19 12 5 21 5 3" />
                            </svg>
                            Run: Send Lab Results → DrChrono
                        </button>
                    </form>

                    <form method="post">
                        <?php wp_nonce_field('drchrono_manual_cron'); ?>
                        <button type="submit" name="action_run_get_results"
                            class="drc-btn drc-btn--secondary"
                            style="width:100%"
                            <?php echo !$token_valid ? 'disabled title="Connect DrChrono first"' : ''; ?>
                            onclick="return confirm('Run get_drchrono_results cron now?')">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="23 4 23 10 17 10" />
                                <polyline points="1 20 1 14 7 14" />
                                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
                            </svg>
                            Run: Get Results ← DrChrono
                        </button>
                    </form>

                </div>

            </div>
        </div>

    </div>

    <div class="drc-checklist">
        <h3 class="drc-checklist__title">Setup Checklist</h3>
        <ul class="drc-checklist__list">
            <li class="drc-checklist__item <?php echo !empty($options['client_id']) ? 'is-done' : ''; ?>">
                <span class="drc-checklist__icon"></span>
                Save Client ID & Client Secret
            </li>
            <li class="drc-checklist__item <?php echo $token_valid ? 'is-done' : ''; ?>">
                <span class="drc-checklist__icon"></span>
                Get Access Token (OAuth)
            </li>
            <li class="drc-checklist__item <?php echo !empty($options['lab_name']) && !empty($options['facility_code']) ? 'is-done' : ''; ?>">
                <span class="drc-checklist__icon"></span>
                Fill Lab Name & Facility Code
            </li>
            <li class="drc-checklist__item <?php echo !empty($sublab_id) ? 'is-done' : ''; ?>">
                <span class="drc-checklist__icon"></span>
                Create Sublab on DrChrono
            </li>
            <li class="drc-checklist__item <?php echo !empty($options['lis_endpoint_url']) ? 'is-done' : ''; ?>">
                <span class="drc-checklist__icon"></span>
                Configure LIS Integration
            </li>
        </ul>
    </div>

</div><!-- /.wrap -->

<style>
    .drc-settings-header {
        display: flex;
        align-items: center;
        gap: 14px;
        margin: 20px 0 16px;
        padding-bottom: 16px;
        border-bottom: 1px solid #e2e8f0;
    }

    .drc-settings-header__icon {
        width: 48px;
        height: 48px;
        background: #eff6ff;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #2563eb;
        flex-shrink: 0;
    }

    .drc-settings-header__title {
        font-size: 22px;
        font-weight: 700;
        margin: 0;
        color: #1e293b;
    }

    .drc-settings-header__sub {
        font-size: 13px;
        color: #64748b;
        margin: 2px 0 0;
    }

    .drc-settings-header__status {
        margin-left: auto;
    }

    .drc-status {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 6px 14px;
        border-radius: 100px;
        font-size: 13px;
        font-weight: 600;
    }

    .drc-status--ok {
        background: #f0fdf4;
        color: #16a34a;
        border: 1px solid #bbf7d0;
    }

    .drc-status--off {
        background: #f8fafc;
        color: #94a3b8;
        border: 1px solid #e2e8f0;
    }

    .drc-status__dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: currentColor;
    }

    .drc-status--ok .drc-status__dot {
        animation: drc-pulse 2s infinite;
    }

    @keyframes drc-pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: .4;
        }
    }

    /* Notice */
    .drc-notice {
        display: flex;
        gap: 10px;
        align-items: flex-start;
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 16px;
        font-size: 13px;
        line-height: 1.6;
    }

    .drc-notice--info {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        color: #1e40af;
    }

    .drc-notice code {
        background: #dbeafe;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 12px;
        margin: 0 4px;
    }

    .drc-link {
        color: #2563eb;
        text-decoration: none;
        margin-left: 8px;
    }

    .drc-link:hover {
        text-decoration: underline;
    }

    /* Grid */
    .drc-settings-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
        margin-bottom: 20px;
    }

    @media (max-width: 900px) {
        .drc-settings-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Card */
    .drc-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
    }

    .drc-card__header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 18px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        color: #475569;
    }

    .drc-card__title {
        font-size: 14px;
        font-weight: 600;
        margin: 0;
        color: #1e293b;
    }

    .drc-card__footer {
        padding: 14px 18px;
        border-top: 1px solid #f1f5f9;
        background: #f8fafc;
    }

    .drc-card__footer--gap {
        display: flex;
        gap: 10px;
    }

    /* Form */
    .drc-form {
        padding: 16px 18px;
    }

    .drc-field {
        margin-bottom: 16px;
    }

    .drc-field:last-child {
        margin-bottom: 0;
    }

    .drc-label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #64748b;
        margin-bottom: 6px;
    }

    .drc-required {
        color: #ef4444;
        margin-left: 2px;
    }

    .drc-hint {
        display: block;
        font-size: 11.5px;
        color: #94a3b8;
        margin-top: 4px;
    }

    .drc-hint code {
        background: #f1f5f9;
        padding: 1px 5px;
        border-radius: 3px;
        font-size: 11px;
    }

    .drc-input,
    .drc-select {
        width: 100%;
        padding: 8px 12px;
        border: 1.5px solid #e2e8f0;
        border-radius: 7px;
        font-size: 13.5px;
        color: #1e293b;
        background: #fff;
        transition: border-color .15s, box-shadow .15s;
        outline: none;
        font-family: inherit;
    }

    .drc-input:focus,
    .drc-select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
    }

    .drc-input--readonly {
        background: #f8fafc;
        color: #64748b;
        cursor: default;
        font-size: 12px;
    }

    .drc-input-group {
        display: flex;
        gap: 6px;
        align-items: center;
    }

    .drc-input-group .drc-input {
        flex: 1;
    }

    .drc-input-badge {
        font-size: 11px;
        font-weight: 600;
        padding: 3px 8px;
        border-radius: 4px;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .drc-input-badge--ok {
        background: #f0fdf4;
        color: #16a34a;
        border: 1px solid #bbf7d0;
    }

    .drc-input-badge--warn {
        background: #fff7ed;
        color: #ea580c;
        border: 1px solid #fed7aa;
    }

    /* Buttons */
    .drc-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 16px;
        border: none;
        border-radius: 7px;
        font-size: 13.5px;
        font-weight: 600;
        cursor: pointer;
        transition: all .15s;
        white-space: nowrap;
        font-family: inherit;
    }

    .drc-btn:disabled {
        opacity: .5;
        cursor: not-allowed;
    }

    .drc-btn--primary {
        background: #2563eb;
        color: #fff;
    }

    .drc-btn--primary:hover:not(:disabled) {
        background: #1d4ed8;
    }

    .drc-btn--secondary {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
    }

    .drc-btn--secondary:hover:not(:disabled) {
        background: #e2e8f0;
    }

    .drc-btn--danger {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
    }

    .drc-btn--danger:hover:not(:disabled) {
        background: #fee2e2;
    }

    .drc-btn-icon {
        padding: 7px 9px;
        background: #f1f5f9;
        border: 1.5px solid #e2e8f0;
        border-radius: 7px;
        cursor: pointer;
        color: #64748b;
        transition: all .15s;
        display: flex;
        align-items: center;
        flex-shrink: 0;
    }

    .drc-btn-icon:hover {
        background: #e2e8f0;
        color: #1e293b;
    }

    /* Connected Info */
    .drc-connected-info {
        padding: 16px 18px;
    }

    .drc-connected-info__row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
    }

    .drc-connected-info__row:last-child {
        border-bottom: none;
    }

    .drc-connected-info__label {
        color: #64748b;
        font-weight: 500;
    }

    .drc-connected-info__value {
        color: #1e293b;
        font-weight: 600;
    }

    .drc-text--green {
        color: #16a34a !important;
    }

    .drc-mono {
        font-family: 'Courier New', monospace;
        font-size: 12px;
    }

    /* Connect Prompt */
    .drc-connect-prompt {
        padding: 24px 18px;
        text-align: center;
    }

    .drc-connect-prompt__icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: #eff6ff;
        color: #2563eb;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 14px;
    }

    .drc-connect-prompt__text {
        font-size: 14px;
        color: #475569;
        margin-bottom: 10px;
    }

    .drc-connect-prompt__step {
        font-size: 12px;
        color: #94a3b8;
        line-height: 1.8;
    }

    /* Checklist */
    .drc-checklist {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 18px 20px;
    }

    .drc-checklist__title {
        font-size: 13px;
        font-weight: 600;
        color: #64748b;
        margin: 0 0 14px;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .drc-checklist__list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .drc-checklist__item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 7px 14px;
        border-radius: 100px;
        font-size: 13px;
        font-weight: 500;
        background: #f8fafc;
        color: #94a3b8;
        border: 1.5px solid #e2e8f0;
        transition: all .2s;
    }

    .drc-checklist__item.is-done {
        background: #f0fdf4;
        color: #16a34a;
        border-color: #bbf7d0;
    }

    .drc-checklist__icon {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: #e2e8f0;
        display: inline-block;
        flex-shrink: 0;
    }

    .drc-checklist__item.is-done .drc-checklist__icon {
        background: #16a34a;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3'%3E%3Cpolyline points='20 6 9 17 4 12'/%3E%3C/svg%3E");
        background-size: 10px;
        background-repeat: no-repeat;
        background-position: center;
    }
</style>

<script>
    jQuery(function($) {
        // Toggle show/hide password
        $('.drc-toggle-secret').on('click', function() {
            const $btn = $(this);
            const $input = $btn.closest('.drc-input-group').find('input');
            const isPass = $input.attr('type') === 'password';
            $input.attr('type', isPass ? 'text' : 'password');
            $btn.find('.icon-eye').toggle(!isPass);
            $btn.find('.icon-eye-off').toggle(isPass);
        });

        // Copy to clipboard
        $('.drc-copy-btn').on('click', function() {
            const targetId = $(this).data('target');
            const val = $('#' + targetId).val();
            navigator.clipboard.writeText(val).then(() => {
                const $btn = $(this);
                $btn.css('color', '#16a34a');
                setTimeout(() => $btn.css('color', ''), 2000);
            });
        });

        // Facility code uppercase
        $('#facility_code').on('input', function() {
            this.value = this.value.toUpperCase();
        });
    });
</script>