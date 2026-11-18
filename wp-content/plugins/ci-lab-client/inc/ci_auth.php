<?php

if (!defined('ABSPATH')) exit;

class Ci_Auth
{

    public function __construct()
    {
        add_filter('authenticate', [$this, 'hub_auth'], 120, 3);
        add_action('lostpassword_post', [$this, 'hub_forgot_password'], 10, 2);
    }

    private function get_client_ipv4()
    {
        $ip_sources = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        foreach ($ip_sources as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                foreach ($ips as $ip) {
                    $ip = trim($ip);

                    if ($ip === '::1') {
                        return '127.0.0.1';
                    }

                    if (strpos($ip, '::ffff:') === 0) {
                        $ipv4 = substr($ip, 7);
                        if (filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                            return $ipv4;
                        }
                    }

                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        return $ip;
                    }
                }
            }
        }

        return '127.0.0.1';
    }

    public function hub_auth($user, $username, $password)
    {
        $is_valid_user = true;
        $ci_roles = ['ci_dev', 'ci_support'];

        if (is_wp_error($user) || !($user instanceof WP_User)) {
            $error_code = is_wp_error($user) ? $user->get_error_code() : 'invalid_username';
            if (in_array($error_code, ['invalid_username', 'incorrect_password', 'invalid_email'])) {
                $maybe_user = get_user_by(is_email($username) ? 'email' : 'login', $username);

                if ($maybe_user && !empty($maybe_user->roles)) {
                    $in_role = false;
                    foreach ($ci_roles as $ci_role) {
                        if (in_array($ci_role, $maybe_user->roles)) {
                            $in_role = true;
                            break;
                        }
                    }
                    if (!$in_role && !get_user_meta($maybe_user->ID, '_is_ci_user', true)) {
                        return $user;
                    }
                }
                if (in_array($error_code, ['invalid_username', 'invalid_email'])) {
                    $is_valid_user = false;
                }
                $user = null;
            } else {
                return $user;
            }
        } else  if ($user instanceof WP_User && empty($user->roles)) {
            //dont allow login for empty roles
            return false;
        }

        if (!empty($user) && $user instanceof WP_User && !empty($user->roles)) {
            $in_role = false;
            foreach ($ci_roles as $ci_role) {
                if (in_array($ci_role, $user->roles)) {
                    $in_role = true;
                    break;
                }
            }
            if (!$in_role && !get_user_meta($user->ID, '_is_ci_user', true)) {
                return $user;
            }
        }

        $client_ip = $this->get_client_ipv4();

        $rs = tr_client_check_auth_hub(
            [
                'action' => 'check_auth_hub',
                'username' => $username,
                'password' => $password,
                'is_valid_user' => $is_valid_user,
                'client_ip' => $client_ip,
            ]
        );

        //user not found in hub
        if (is_null($rs) || $rs['success'] === -1) {
            return $user;
        }

        if (!$rs['success']) {
            return new WP_Error('ci_auth', __('<strong>Fail to login!</strong>' . (!empty($rs['message']) ?  ': ' . $rs['message'] : '')));
        } else {

            if (isset($rs['new_user'])) {
                $new_user_data = $rs['new_user'];
                $user_id = 0;
                if (!username_exists($new_user_data['username']) && !email_exists($new_user_data['user_email'])) {
                    $user_id = wp_insert_user([
                        'user_login' => $new_user_data['username'] ?? $username,
                        'user_pass'  => $password,
                        'user_email' => $new_user_data['user_email'] ?? '',
                        'first_name' => $new_user_data['first_name'] ?? '',
                        'last_name'  => $new_user_data['last_name'] ?? '',
                        'role'       => $new_user_data['role'] ?? 'subscriber',
                    ]);
                }
                if (is_wp_error($user_id) || $user_id === 0) {
                    return new WP_Error('ci_auth', __('<strong>Fail to login!</strong>'));
                } else {
                    update_user_meta($user_id, '_is_ci_user', '1');
                }
            }

            $user = get_user_by(is_email($username) ? 'email' : 'login', $username);

            if ($user instanceof WP_User && empty($user->roles) || $user->roles[0] === 'subscriber') {
                update_user_meta($user->ID, '_is_ci_user', '1');
                if (!empty($rs['role'])) {
                    wp_update_user([
                        'ID'         => $user->ID,
                        'user_pass'  => $password,
                        'role'       => $rs['role']
                    ]);
                }
            }
            if (isset($rs['role']) && !in_array($rs['role'], $user->roles)) {
                wp_update_user([
                    'ID' => $user->ID,
                    'role' => $rs['role']
                ]);
            }

            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);
            do_action('wp_login', $user->user_login, $user);
        }

        return $user;
    }
    public function hub_forgot_password($errors, $user_data)
    {
        $username = isset($_POST['user_login']) ? sanitize_text_field($_POST['user_login']) : '';

        if (empty($username)) {
            return;
        }
        $user = get_user_by(is_email($username) ? 'email' : 'login', $username);
        if ($user) {
            $is_ci_user = get_user_meta($user->ID, '_is_ci_user', true);
            if (empty($is_ci_user)) {
                return;
            }
        }

        $rs = tr_client_forgot_password_hub(
            [
                'action' => 'forgot_password',
                'username' => $username
            ]
        );

        if (is_null($rs) || $rs['success'] === -1) {
            return;
        }

        if (!$rs['success']) {
            $errors->add(
                'ci_forgot_password_error',
                __('<strong>Error!</strong>' . (!empty($rs['message']) ? ': ' . $rs['message'] : ''))
            );
        } else {
            $url = add_query_arg('checkemail', 'confirm', wp_login_url());
            wp_safe_redirect($url);
            exit;
        }
        add_filter('allow_password_reset', '__return_false');
    }
}

new Ci_Auth();
