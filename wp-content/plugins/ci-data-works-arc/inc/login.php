<?php
wp_enqueue_script('jquery');
add_action('login_enqueue_scripts', function () {
    wp_enqueue_script('patients-js', TRCPDD_URL . 'js/login_customer.js', [], TRCPDD_V, true);
    wp_enqueue_style('patients', TRCPDD_URL . 'css/otpa.css', array(), TRCPDD_V);

    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ip = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';

    wp_localize_script('patients-js', 'MY_IP_DATA', array(
        'ip' => $ip,
        'current_year' => date_i18n('Y'),
    ));
});

ob_start('trpdd_login_ob_filter');

add_filter('validate_username', 'trpdd_Validate_username');
function trpdd_Validate_username($valid)
{
    return true;
}

add_filter('login_message', 'trpdd_disabled_role_login_message');
function trpdd_disabled_role_login_message($message)
{
    if (!empty($_GET['disabled_role'])) {
        $message .= '<div id="login_error">Your role is disabled for this site.</div>';
    }

    return $message;
}

function trpdd_login_ob_filter($output)
{
    $pa_options = get_option('trpatient_settings', array());

    $output = str_replace('Username<br />', '', $output);
    $output = str_replace('Password<br />', '', $output);
    $output = str_replace('Email<br />', '', $output);
    $output = str_replace('Username or Email Address<br />', '', $output);
    $output = str_replace('Password<br />', '', $output);
    $output = str_replace('CAPTCHA Code<br />', '', $output);
    if (@$_REQUEST['action'] == 'register') {
        $output = str_replace('id="user_login"', 'id="user_login" placeholder="Username" title="Username"', $output);
    } else {
        $output = str_replace('id="user_login"', 'id="user_login" placeholder="Email" title="Email"', $output);
    }

    $output = str_replace('id="user_email"', 'id="user_email" placeholder="Email" title="Email"', $output);
    $output = str_replace('id="user_pass"', 'id="user_pass" placeholder="Password" title="Password"', $output);
    $output = str_replace('id="captcha_code"', 'id="captcha_code" placeholder="CAPTCHA Code" title="CAPTCHA Code"', $output);
    $output = preg_replace('/<a[^\>]*>Register\<\/a\> \|/', '', $output);
    if (!isset($_REQUEST['checkemail']) || $_REQUEST['checkemail'] != 'registered') {

        $hide_btn = !empty($pa_options['hide_pwd_register']);
        if (isset($_GET['type']) && $_GET['type'] == 'phone') {
            $hide_btn  = false;
        }
        if (!$hide_btn) {
            $output = str_replace('method="post">', 'method="post"><p class=""><a class="button bluebtn" href="' . wp_registration_url() . '">Register With My Email</a></p>', $output);
        }
    }

    return $output;
}

add_action('login_head', 'trpdd_data_login_head', 998);

function trpdd_data_login_head()
{
    $url_logo_header = '';
    $current_lab = CI_Base_Lab::get_current_lab();
    if ($current_lab) {
        $upload_dir = tr_wp_upload_dir();
        $logo       = $current_lab['lab_logo'];
        $url_logo_header   = $upload_dir['baseurl'] . '/logos/' . $logo;
    } else {
        $url_logo_header = TRCPDD_URL . 'images/logo.png';
    }
    $favicon_32_url = TRCPDD_URL . 'images/favicon32.png';
    $apple_touch_icon_url = TRCPDD_URL . 'images/apple-touch-icon.png';
?>
    <link rel="icon" href="<?php echo esc_url($favicon_32_url); ?>" sizes="32x32" />
    <link rel="apple-touch-icon" href="<?php echo esc_url($apple_touch_icon_url); ?>" />
    <style>
        html body.login h1 a,
        body.login h1 a {
            background-image: url(<?php echo $url_logo_header ?>) !important;
            background-size: contain !important;
            background-position: center !important;
            height: 100px !important;
            width: auto !important;
            pointer-events: none;
            cursor: default;
        }

        body.login.interim-login h1 a {
            max-height: 40px !important;
        }


        .forgetmenot {
            display: none;
        }
    </style>
<?php
}


add_filter('login_url', 'trpdd_login_url');
function trpdd_login_url($url)
{
    if (defined('OTPA_V') && isset($_GET['type']) && $_GET['type'] == 'phone') {
        $url =  home_url('otpa/passwordless-login');
    }
    return $url;
}



add_filter('lostpassword_errors', 'trpdd_lostpassword_errors', 99, 2);
function trpdd_lostpassword_errors($errors, $user_data)
{
    global $wpdb;

    $user_id = $user_data->ID;
    $phone = get_user_meta($user_id, 'phone', true);
    $phone = str_replace(array('+1', ' ', '(', ')'), '', $phone);
    $domain = $_SERVER['HTTP_HOST'];
    list($nameemail, $domain_email) = explode('@', $user_data->user_email);
    $nameemail = preg_replace("/[0-9]*/", '', $nameemail);


    if (
        strpos($user_data->user_email, $phone . '@') === 0
        || $user_data->user_email == '0@0'
        || stripos($user_data->user_email, '@patients.') > 0
        || (empty($nameemail) && $domain_email == $domain)
    ) {
        //this is email is auto create
        $errors->add('empty_email', '<b>Error</b>: Empty Email for this user. Please use your phone to login!');
    }

    return $errors;
}

add_filter('lostpassword_user_data', 'trpdd_ostpassword_user_data', 99, 2);
function trpdd_ostpassword_user_data($user_data, $errors)
{
    global $wpdb;

    if (!$errors->has_errors() && is_object($user_data)) {
        $user_id = $user_data->ID;
        $phone = get_user_meta($user_id, 'phone', true);
        $phone = str_replace(array('+1', ' ', '(', ')'), '', $phone);
        if (strpos($user_data->user_email, $phone . '@') === 0) {
            //this is email is auto create
            //search email from patient table
            $patientemail = $wpdb->get_var("select pa.pa_email from ci_patients pa
                        join {$wpdb->users} u ON u.patient_id = pa.pa_ID
                        where u.ID = {$user_id}");
            if (!empty($patientemail)) {
                $user_data->user_email = $patientemail;
                $wpdb->update($wpdb->users, array('user_email' => $patientemail), array('ID' => $user_id));
            }
        }
    }
    return $user_data;
}
//end file

?>
