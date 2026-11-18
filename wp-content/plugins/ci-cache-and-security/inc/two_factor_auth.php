<?php

if (!defined('ABSPATH')) exit;

class WP_Simple_2FA
{
    const OPTION_KEY = 'tr_security';
    const USER_META_SECRET = '_wp2fa_secret';
    const USER_META_ENABLED = '_wp2fa_enabled';
    const USER_META_BACKUPS = '_wp2fa_backups';
    const TRANSIENT_PREFIX = 'wp2fa_pending_';

    public function __construct()
    {

        add_action('show_user_profile', [$this, 'profile_fields']);
        add_action('edit_user_profile', [$this, 'profile_fields']);
        add_action('edit_user_profile_factor_auth', [$this, 'profile_fields']);
        add_action('personal_options_update', [$this, 'save_profile']);
        add_action('edit_user_profile_update', [$this, 'save_profile']);

        add_filter('authenticate', [$this, 'check_2fa_required'], 110, 3);
        add_action('login_form_validate_2fa', [$this, 'show_2fa_form']);
        add_action('login_enqueue_scripts', [$this, 'login_styles']);

        add_action('init', [$this, 'block_unverified_access']);
    }

    public function get_options()
    {
        $defaults = [
            '2fa_enabled' => 0,
            '2fa_enforce_roles' => [],
            '2fa_issuer' => get_bloginfo('name'),
        ];
        return wp_parse_args(get_option(self::OPTION_KEY, []), $defaults);
    }


    public function profile_fields($user)
    {
        if (!current_user_can('edit_user', $user->ID)) return;

        $options = $this->get_options();
        if (!$options['2fa_enabled']) {
            return;
        }

        $secret = get_user_meta($user->ID, self::USER_META_SECRET, true);
        $enabled = get_user_meta($user->ID, self::USER_META_ENABLED, true);
        $backups = get_user_meta($user->ID, self::USER_META_BACKUPS, true) ?: [];

        if (!$secret) {
            $secret = $this->generate_secret();
            update_user_meta($user->ID, self::USER_META_SECRET, $secret);
        }

        $issuer = $this->get_options()['2fa_issuer'];
        $account_name = $user->user_login;
        $otpauth = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            rawurlencode($issuer),
            rawurlencode($account_name),
            $secret,
            rawurlencode($issuer)
        );
        $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($otpauth);

        $enforce = (array)($options['2fa_enforce_roles'] ?? []);
        $roles = (array)$user->roles;
        $is_enforced = count(array_intersect($roles, $enforce)) > 0;

        $messages = get_transient('wp2fa_message_' . $user->ID);
        if ($messages) {
            foreach ($messages as $message) {
                $class = $message['type'] === 'error' ? 'error' : 'updated';
                echo '<div class="' . $class . '"><p>' . esc_html($message['message']) . '</p></div>';
            }
            delete_transient('wp2fa_message_' . $user->ID);
        }
?>
        <h2>Two-Factor Authentication</h2>
        <table class="form-table">
            <tr>
                <th><label>Status</label></th>
                <td>
                    <?php if ($enabled): ?>
                        <span style="color: #46b450; font-weight: bold;">✓ Enabled</span>
                    <?php else: ?>
                        <span style="color: #dc3232; font-weight: bold;">✗ Disabled</span>
                    <?php endif; ?>
                </td>
            </tr>

            <?php if (!$enabled): ?>
                <tr>
                    <th><label>Setup Instructions</label></th>
                    <td>
                        <ol style="margin-left: 16px;">
                            <li>Install an authenticator app on your phone (Google Authenticator, Authy, Microsoft Authenticator, etc.)</li>
                            <li>Scan the QR code below with your authenticator app</li>
                            <li>Enter the 6-digit code from the app</li>
                            <li>Click "Verify & Enable"</li>
                            <li>Save your backup codes</li>
                        </ol>
                    </td>
                </tr>

                <tr>
                    <th><label>QR Code</label></th>
                    <td>
                        <img src="<?php echo esc_url($qr_url); ?>" alt="QR code" style="border: 1px solid #ddd; padding: 10px; background: #fff;"><br>
                        <p><strong>Manual entry:</strong> <code style="background: #f0f0f1; padding: 3px 6px;"><?php echo esc_html($secret); ?></code></p>
                    </td>
                </tr>

                <tr>
                    <th><label for="wp2fa_code">Verification Code</label></th>
                    <td>
                        <input type="text" id="wp2fa_code" name="wp2fa_code" value="" maxlength="6" pattern="\d{6}" placeholder="000000" style="font-size: 18px; width: 150px; text-align: center; letter-spacing: 3px;">
                        <p class="description">Enter the 6-digit code from your authenticator app</p>
                        <p>
                            <button type="submit" class="button button-primary" name="wp2fa_action" value="verify">Verify & Enable 2FA</button>
                        </p>
                    </td>
                </tr>
            <?php else: ?>
                <tr>
                    <th><label>Backup Codes</label></th>
                    <td>
                        <p><strong>Store these codes in a safe place!</strong> Each code can be used once if you lose access to your authenticator app.</p>
                        <div style="background: #f0f0f1; padding: 15px; border: 1px solid #ddd;">
                            <?php foreach ($backups as $code): ?>
                                <code style="display: block; font-size: 14px; margin: 3px 0; letter-spacing: 2px;"><?php echo esc_html($code); ?></code>
                            <?php endforeach; ?>
                        </div>
                        <p>
                            <button type="submit" class="button" name="wp2fa_action" value="regen">Regenerate Backup Codes</button>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th><label>Disable 2FA</label></th>
                    <td>
                        <p class="description">Remove two-factor authentication from your account</p>
                        <button type="submit" class="button button-secondary" name="wp2fa_action" value="disable" onclick="return confirm('Are you sure you want to disable 2FA? This will make your account less secure.');">Disable 2FA</button>
                    </td>
                </tr>
            <?php endif; ?>
        </table>
        <?php wp_nonce_field('wp2fa_profile_nonce', 'wp2fa_profile_nonce'); ?>
    <?php
    }

    public function save_profile($user_id)
    {
        if (!current_user_can('edit_user', $user_id)) return;
        if (!isset($_POST['wp2fa_profile_nonce']) || !wp_verify_nonce($_POST['wp2fa_profile_nonce'], 'wp2fa_profile_nonce')) return;

        $secret = get_user_meta($user_id, self::USER_META_SECRET, true);
        if (!$secret) {
            $secret = $this->generate_secret();
            update_user_meta($user_id, self::USER_META_SECRET, $secret);
        }

        $action = $_POST['wp2fa_action'] ?? '';

        if ($action === 'verify') {
            $code = sanitize_text_field($_POST['wp2fa_code'] ?? '');
            if ($this->verify_totp($secret, $code)) {
                update_user_meta($user_id, self::USER_META_ENABLED, 1);
                $backups = $this->generate_backup_codes();
                update_user_meta($user_id, self::USER_META_BACKUPS, $backups);

                $messages[] = [
                    'type' => 'success',
                    'message' => '2FA has been enabled successfully! Please save your backup codes below.'
                ];
            } else {
                $messages[] = [
                    'type' => 'error',
                    'message' => 'Invalid verification code. Please try again.'
                ];
            }
        } elseif ($action === 'regen') {
            $backups = $this->generate_backup_codes();
            update_user_meta($user_id, self::USER_META_BACKUPS, $backups);

            $messages[] = [
                'type' => 'success',
                'message' => 'New backup codes have been generated. Please save them below.'
            ];
        } elseif ($action === 'disable') {
            delete_user_meta($user_id, self::USER_META_ENABLED);
            delete_user_meta($user_id, self::USER_META_BACKUPS);

            $messages[] = [
                'type' => 'success',
                'message' => '2FA has been disabled.'
            ];
        }

        if (!empty($messages)) {
            set_transient('wp2fa_message_' . $user_id, $messages, 30);
        }
    }


    public function check_2fa_required($user, $username, $password)
    {
        if (is_wp_error($user) || !($user instanceof WP_User)) {
            return $user;
        }

        $options = $this->get_options();
        if (!$options['2fa_enabled']) {
            return $user;
        }

        $enforce = (array)($options['2fa_enforce_roles'] ?? []);
        $roles = (array)$user->roles;
        $enforced = count(array_intersect($roles, $enforce)) > 0;

        if (!$enforced) {
            return $user;
        }

        $enabled = get_user_meta($user->ID, self::USER_META_ENABLED, true);
        if (!$enabled) {
            return $user;
        }

        $token = wp_generate_password(32, false);
        set_transient(self::TRANSIENT_PREFIX . $token, [
            'user_id' => $user->ID,
            'username' => $username,
        ], 600);

        $redirect_url = add_query_arg([
            'action' => 'validate_2fa',
            'token' => $token,
        ], wp_login_url());

        wp_safe_redirect($redirect_url);
        exit;
    }

    public function show_2fa_form()
    {
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : $token;

        $pending = get_transient(self::TRANSIENT_PREFIX . $token);

        if (!$pending || !isset($pending['user_id'])) {
            wp_redirect(wp_login_url());
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wp2fa_verify_nonce'])) {
            if (!wp_verify_nonce($_POST['wp2fa_verify_nonce'], 'wp2fa_verify_action')) {
                $error = 'Invalid security token.';
            } else {
                $code = sanitize_text_field($_POST['wp_2fa_code'] ?? '');
                $user_id = $pending['user_id'];

                if ($this->verify_2fa_code($user_id, $code)) {
                    delete_transient(self::TRANSIENT_PREFIX . $token);

                    wp_set_current_user($user_id);
                    wp_set_auth_cookie($user_id);
                    do_action('wp_login', $pending['username'], get_user_by('id', $user_id));
                    setcookie('wp2fa_verified', '1', time() + 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);

                    wp_safe_redirect(admin_url());
                    exit;
                } else {
                    $error = 'Invalid authentication code. Please try again.';
                }
            }
        }

        login_header(__('Two-Factor Authentication', 'wp-simple-2fa'));

        $form_action = add_query_arg([
            'action' => 'validate_2fa',
            'token' => $token
        ], wp_login_url());
        if (!empty($error)) {
            echo '<div id="login_error" class="notice notice-error">' . esc_html($error) . '</div>';
        }
    ?>
        <form name="validate_2fa_form" id="validate_2fa_form" action="<?php echo esc_url($form_action); ?>" method="post">
            <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
            <p>
                <label for="wp_2fa_code">Authentication Code</label>
                <input type="text" name="wp_2fa_code" id="wp_2fa_code" class="input" size="20" autocomplete="off" placeholder="000000" maxlength="6" style="letter-spacing: 3px; text-align: center; font-size: 18px;" autofocus required>
            </p>
            <p class="description" style="margin-top: -10px; margin-bottom: 20px;">
                Enter the 6-digit code from your authenticator app or use a backup code.
            </p>

            <?php wp_nonce_field('wp2fa_verify_action', 'wp2fa_verify_nonce'); ?>

            <p class="submit">
                <input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="Verify">
            </p>
        </form>

        <p id="backtoblog">
            <a href="<?php echo esc_url(home_url('/')); ?>">&larr; Go to <?php echo esc_html(get_bloginfo('name')); ?></a>
        </p>
    <?php
        exit;
    }

    private function verify_2fa_code($user_id, $code)
    {
        if (empty($code)) {
            return false;
        }

        $backups = get_user_meta($user_id, self::USER_META_BACKUPS, true) ?: [];
        if (in_array($code, $backups, true)) {
            $backups = array_diff($backups, [$code]);
            update_user_meta($user_id, self::USER_META_BACKUPS, array_values($backups));
            return true;
        }

        $secret = get_user_meta($user_id, self::USER_META_SECRET, true);
        if ($secret && $this->verify_totp($secret, $code)) {
            return true;
        }

        return false;
    }

    public function block_unverified_access()
    {
        if (!is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        if (in_array($GLOBALS['pagenow'], ['wp-login.php', 'wp-register.php'])) {
            return;
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }
        if (isset($_COOKIE['wp2fa_verified']) && $_COOKIE['wp2fa_verified'] === '1') {
            return;
        }

        global $wpdb;
        $pattern = self::TRANSIENT_PREFIX . '%';
        $transients = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE %s",
            '_transient_' . $pattern
        ));

        foreach ($transients as $transient) {
            $data = maybe_unserialize($transient->option_value);
            if (isset($data['user_id']) && $data['user_id'] == $user_id) {
                wp_logout();
                wp_redirect(wp_login_url());
                exit;
            }
        }
    }

    public function login_styles()
    {
    ?>
        <style>
            #wp_2fa_code {
                width: 100%;
                letter-spacing: 3px;
                text-align: center;
                font-size: 16px;
            }
        </style>
<?php
    }

    private function generate_secret($length = 16)
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $s = '';
        for ($i = 0; $i < $length; $i++) {
            $s .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $s;
    }

    private function base32_to_binary($base32)
    {
        $base32 = strtoupper($base32);
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';

        for ($i = 0; $i < strlen($base32); $i++) {
            $val = strpos($alphabet, $base32[$i]);
            if ($val === false) continue;
            $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
            $bytes .= chr(bindec(substr($bits, $i, 8)));
        }

        return $bytes;
    }

    private function hotp($secret, $counter)
    {
        $key = $this->base32_to_binary($secret);
        $bin_counter = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $bin_counter, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncated = substr($hash, $offset, 4);
        $code = unpack('N', $truncated)[1] & 0x7FFFFFFF;
        return $code;
    }

    private function totp($secret, $timeSlice = null)
    {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }
        $hotp = $this->hotp($secret, $timeSlice);
        return str_pad($hotp % 1000000, 6, '0', STR_PAD_LEFT);
    }

    public function verify_totp($secret, $code, $window = 1)
    {
        $code = str_pad((string)$code, 6, '0', STR_PAD_LEFT);

        for ($i = -$window; $i <= $window; $i++) {
            $time_slice = floor(time() / 30) + $i;
            if (hash_equals($this->totp($secret, $time_slice), $code)) {
                return true;
            }
        }

        return false;
    }

    private function generate_backup_codes($n = 8)
    {
        $arr = [];
        for ($i = 0; $i < $n; $i++) {
            $arr[] = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        }
        return $arr;
    }
}

new WP_Simple_2FA();
