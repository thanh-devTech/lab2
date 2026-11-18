<?php
wp_enqueue_script('jquery');
add_action('login_enqueue_scripts', function () {

    global $ci_patient_options;
    if (empty($ci_patient_options)) {
        $ci_patient_options     = get_option('trpatient_settings', array());
    }
    $new_login_style = true; //from 2026/01/01
    if ($new_login_style) {
        wp_enqueue_script('patients-js', TRCPPR_URL . 'js/login_customer.js', [], TRCPPR_V, true);
        wp_enqueue_style('patients', TRCPPR_URL . 'css/otpa.css', array(), TRCPPR_V);

        $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '127.0.0.1';

        wp_localize_script('patients-js', 'MY_IP_DATA', array(
            'ip' => $ip
        ));
    } else {
        wp_enqueue_style('patients', TRCPPR_URL . 'css/login.css', [], TRCPPR_V);
    }
});

ob_start('trcppr_login_ob_filter');

add_filter('validate_username', 'trcppr_validate_username');
function trcppr_validate_username($valid)
{
    return true;
}

function trcppr_login_ob_filter($output)
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

        $hide_btn = $pa_options['hide_pwd_register'];
        if (isset($_GET['type']) && $_GET['type'] == 'phone') {
            $hide_btn  = false;
        }
        if (!$hide_btn) {
            $output = str_replace('method="post">', 'method="post"><p class=""><a class="button bluebtn" href="' . wp_registration_url() . '">Register With My Email</a></p>', $output);
        }
    }

    return $output;
}

add_action('login_form_register', 'trcppr_register_form', 1);
function trcppr_register_form()
{
    $errors = null;
    $pa_options = get_option('trpatient_settings', array());
    wp_enqueue_style('jquery-ui', TRCPDD_URL . 'admin/css/jquery-ui.css', array(), '1.1.9');
    add_thickbox();
    $user_login = '';
    $user_email = '';
    $http_post = ('POST' == $_SERVER['REQUEST_METHOD']);
    if ($http_post) {
        $_POST['user_login'] = $_POST['user_email'];
        $_POST['phone'] = trim($_POST['phone']);
        $_POST['phone'] = str_replace(array('-', '.', ' ', '(', ')'), '', $_POST['phone']);
        $_POST['phone'] = trim($_POST['phone']);

        if (isset($_POST['user_login']) && is_string($_POST['user_login'])) {
            $user_login = $_POST['user_login'];
        }

        if (isset($_POST['user_email']) && is_string($_POST['user_email'])) {
            $user_email = wp_unslash($_POST['user_email']);
        }

        if ($user_login == '' && (!empty($_POST['phone']))) {
            $user_login = $_POST['phone'];
            //$user_email = $_POST['phone'] . '@' . $_SERVER['HTTP_HOST'];
        }

        $errors = register_new_user($user_login, $user_email);

        if (empty($user_login) && empty($user_email) && ($_GET['type'] == 'phone')) {
            $errors->remove('empty_email');
        }

        if (!is_wp_error($errors)) {
            $redirect_to = !empty($_POST['redirect_to']) ? $_POST['redirect_to'] : 'wp-login.php?checkemail=registered';
            wp_safe_redirect($redirect_to);
            exit();
        } else {
            $errors->remove('empty_username');
        }
    }

    $registration_redirect = !empty($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : '';

    /**
     * Filters the registration redirect URL.
     *
     * @param string $registration_redirect The redirect destination URL.
     * @since 3.0.0
     *
     */
    $redirect_to = apply_filters('registration_redirect', $registration_redirect);
    login_header(__('Registration Form'), '<p class="message register">' . __('Register For This Site') . '</p>', $errors);

    $states = array();
    $st = tr_get_states();
    foreach ($st as $k => $t) {
        $t = $k;
        if ($k == '') $t = 'State';
        $states[$k] = $t;
    }

    if (isset($_GET['type']) && $_GET['type'] == 'phone') {
        $formAction = site_url('wp-login.php?action=register&type=phone', 'login_post');
    } else {
        $formAction = site_url('wp-login.php?action=register', 'login_post');
    }
?>
    <form name="registerform" id="registerform"
        action="<?php echo esc_url($formAction); ?>" method="post"
        novalidate="novalidate">

        <div class="frow">
            <label for="user_email">
                <input type="email" name="user_email" id="user_email" class="input"
                    value="<?php echo esc_attr(wp_unslash($user_email)); ?>" size="25" /></label>
        </div>


        <div class="frow">
            <input type="text" id="phoneNumber" name="phone" value="<?php echo @$_POST['phone'] ?>" placeholder="Phone Number"
                title="Phone Number" required />
        </div>
        <div class="frow">
            <input type="text" name="patient_code" value="<?php echo @$_POST['patient_code'] ?>"
                <?php if ($pa_options['limit_patient_code'] > 5) echo 'maxlength="' . $pa_options['limit_patient_code'] . '"' ?>
                placeholder="Patient Code" title="Patient Code" required />
            <a class="itip thickbox" href="#TB_inline?width=600&height=200&inlineId=patientcodeinfo"></a>
        </div>
        <div class="frow">
            <input type="text" name="first_name" value="<?php echo @$_POST['first_name'] ?>" placeholder="First Name"
                title="First Name" style="width:<?php echo $pa_options['hide_mi_register'] ? 49 : 40 ?>%" required />
            <?php if (!$pa_options['hide_mi_register']) { ?>
                <input type="text" name="middle_name" value="<?php echo @$_POST['middle_name'] ?>" placeholder="M.I"
                    title="M.I" style="width:17.3%" />
            <?php } ?>
            <input type="text" name="last_name" value="<?php echo @$_POST['last_name'] ?>" placeholder="Last Name"
                title="Last Name" style="width:<?php echo $pa_options['hide_mi_register'] ? 49 : 40 ?>%" required />
        </div>

        <div class="frow birthdaydrop">
            <label>Birthday:</label>
            <input type="text" name="birthday" class="has_date" autocomplete="off"
                value="<?php echo @$_POST['birthday'] ?>" placeholder="Birthdate" title="Birthdate" required />
        </div>

        <?php if (!$pa_options['hide_req_address']) { ?>
            <div class="frow">
                <input type="text" name="address" value="<?php echo @$_POST['address'] ?>" placeholder="Address"
                    title="Address" style="width:59%" />

                <input type="text" name="city" value="<?php echo @$_POST['city'] ?>" placeholder="City" title="City"
                    style="width:39%" />
            </div>
            <div class="frow">
                <select name="state" required style="width:30%;">
                    <?php
                    $states = array(
                        ''  => 'Select State',
                        "AL" => "Alabama",
                        "AK" => "Alaska",
                        "AS" => "American Samoa",
                        "AZ" => "Arizona",
                        "AR" => "Arkansas",
                        "CA" => "California",
                        "CO" => "Colorado",
                        "CT" => "Connecticut",
                        "DE" => "Delaware",
                        "DC" => "District Of Columbia",
                        "FM" => "Federated States Of Micronesia",
                        "FL" => "Florida",
                        "GA" => "Georgia",
                        "GU" => "Guam",
                        "HI" => "Hawaii",
                        "ID" => "Idaho",
                        "IL" => "Illinois",
                        "IN" => "Indiana",
                        "IA" => "Iowa",
                        "KS" => "Kansas",
                        "KY" => "Kentucky",
                        "LA" => "Louisiana",
                        "ME" => "Maine",
                        "MH" => "Marshall Islands",
                        "MD" => "Maryland",
                        "MA" => "Massachusetts",
                        "MI" => "Michigan",
                        "MN" => "Minnesota",
                        "MS" => "Mississippi",
                        "MO" => "Missouri",
                        "MT" => "Montana",
                        "NE" => "Nebraska",
                        "NV" => "Nevada",
                        "NH" => "New Hampshire",
                        "NJ" => "New Jersey",
                        "NM" => "New Mexico",
                        "NY" => "New York",
                        "NC" => "North Carolina",
                        "ND" => "North Dakota",
                        "MP" => "Northern Mariana Islands",
                        "OH" => "Ohio",
                        "OK" => "Oklahoma",
                        "OR" => "Oregon",
                        "PW" => "Palau",
                        "PA" => "Pennsylvania",
                        "PR" => "Puerto Rico",
                        "RI" => "Rhode Island",
                        "SC" => "South Carolina",
                        "SD" => "South Dakota",
                        "TN" => "Tennessee",
                        "TX" => "Texas",
                        "UT" => "Utah",
                        "VT" => "Vermont",
                        "VI" => "Virgin Islands",
                        "VA" => "Virginia",
                        "WA" => "Washington",
                        "WV" => "West Virginia",
                        "WI" => "Wisconsin",
                        "WY" => "Wyoming",
                        '00' => 'N/A'
                    );
                    $canada = array(
                        'AB' => 'Canada - Alberta',
                        'BC' => 'Canada - British Columbia',
                        'MB' => 'Canada - Manitoba',
                        'NB' => 'Canada - New Brunswick',
                        'NL' => 'Canada - Newfoundland and Labrador',
                        'NT' => 'Canada - Northwest Territories',
                        'NS' => 'Canada - Nova Scotia',
                        'NU' => 'Canada - Nunavut',
                        'ON' => 'Canada - Ontario',
                        'PE' => 'Canada - Prince Edward Island',
                        'QC' => 'Canada - Quebec',
                        'SK' => 'Canada - Saskatchewan',
                        'YT' => 'Canada - Yukon Territory'
                    );


                    if (function_exists('get_blog_option')) {
                        $settings = get_blog_option(defined('BLOG_ID_CURRENT_SITE') ? BLOG_ID_CURRENT_SITE : 1, 'ci_pdf_data');
                    } else {
                        $settings = get_option('ci_pdf_data');
                    }

                    if (isset($settings['req_canadian_provices']) && $settings['req_canadian_provices']) {
                        $states = array_merge($states, $canada);
                    }
                    foreach ($states as $statecode => $statetxt) {
                        $selected = isset($_POST['state']) && $_POST['state'] == $statecode ? 'selected' : '';
                        echo '<option value="' . $statecode . '" ' . $selected . '>' . $statetxt . '</option>';
                    }
                    ?>
                </select>


                <input type="text" name="zip" value="<?php echo @$_POST['zip'] ?>" placeholder="Zip" title="Zip"
                    style="width:68%" />
            </div>
        <?php } ?>

        <div id="patientcodeinfo" style="display: none;">
            <p style="color:#000;text-align: center;padding:10px"><?php
                                                                    $text = $pa_options['textblueicon'];
                                                                    if (empty($text)) {
                                                                        $text = 'Please ask your doctor for the Patient Code from one of your test results demographic block.';
                                                                    }
                                                                    echo $text;
                                                                    ?></p>
        </div>

        <?php
        do_action('register_form');
        ?>
        <p id="reg_passmail"><?php _e('Registration confirmation will be emailed to you.'); ?></p>
        <br class="clear" />
        <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>" />
        <p class="submit"><input type="submit" name="wp-submit" id="wp-submit"
                class="button button-primary button-large" value="<?php esc_attr_e('Register'); ?>" />
        </p>
    </form>

    <p id="nav">
        <a href="<?php echo esc_url(wp_login_url()); ?>"><?php _e('Log in'); ?></a>
        |
        <a href="<?php echo esc_url(wp_lostpassword_url()); ?>"><?php _e('Lost your password?'); ?></a>
    </p>
    <script src="<?php echo TRCPPR_URL . 'js/jquery.date-dropdowns.min.js' ?>"></script>
    <script>
        jQuery(function($) {
            $('.has_date').dateDropdowns({
                displayFormat: 'mdy',
                minAge: 0,
                daySuffixValues: ['', '', '', '']
            });
            <?php if ($pa_options['format_phone']) { ?>
                const isNumericInput = (event) => {
                    const key = event.keyCode;
                    return ((key >= 48 && key <= 57) || // Allow number line
                        (key >= 96 && key <= 105) // Allow number pad
                    );
                };

                const isModifierKey = (event) => {
                    const key = event.keyCode;
                    return (event.shiftKey === true || key === 35 || key === 36) || // Allow Shift, Home, End
                        (key === 8 || key === 9 || key === 13 || key === 46) || // Allow Backspace, Tab, Enter, Delete
                        (key > 36 && key < 41) || // Allow left, up, right, down
                        (
                            // Allow Ctrl/Command + A,C,V,X,Z
                            (event.ctrlKey === true || event.metaKey === true) &&
                            (key === 65 || key === 67 || key === 86 || key === 88 || key === 90)
                        )
                };

                const enforceFormat = (event) => {
                    // Input must be of a valid number format or a modifier key, and not longer than ten digits
                    if (!isNumericInput(event) && !isModifierKey(event)) {
                        event.preventDefault();
                    }
                };

                const formatToPhone = (event) => {
                    if (isModifierKey(event)) {
                        return;
                    }

                    // I am lazy and don't like to type things more than once
                    var target = event.target;
                    var input = event.target.value.replace(/\D/g, '').substring(0, 10); // First ten digits of input only
                    var zip = input.substring(0, 3);
                    var middle = input.substring(3, 6);
                    var last = input.substring(6, 10);

                    if (input.length > 6) {
                        target.value = '(' + zip + ') ' + middle + ' - ' + last;
                    } else if (input.length > 3) {
                        target.value = '(' + zip + ') ' + middle;
                    } else if (input.length > 0) {
                        target.value = '(' + zip + ')';
                    }
                };

                const inputElement = document.getElementById('phoneNumber');
                inputElement.addEventListener('keydown', enforceFormat);
                inputElement.addEventListener('keyup', formatToPhone);
            <?php } ?>
        })
    </script>
    <?php
    login_footer('user_login');
    ?>
    <?php
    exit;
}

add_filter('registration_errors', 'trcppr_registration_errors', 1, 3);
function trcppr_registration_errors($errors, $sanitized_user_login, $user_email)
{
    global $wpdb;
    $arr = array(
        'patient_code' => 'Patient Code',
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'birthday' => 'Birthdate',
        //        'phone'=> 'Phone Number',
    );

    if (isset($_GET['type']) && $_GET['type'] == 'phone') {
        $arr['phone'] =  'Phone Number';
    }

    foreach ($arr as $k => $tt) {
        if (isset($_POST[$k]) && empty($_POST[$k])) {
            $errors->add($k, '<b>Error:</b> Please enter a ' . $tt);
        }
    }


    unset($errors->errors['username_exists']);

    if (isset($errors->errors['email_exists'])) {
        foreach ($errors->errors['email_exists'] as $i => $txt) {
            if (stripos($txt, 'already') > 0) {
                $errors->errors['email_exists'][$i] = "<b>ERROR: </b>This account already exists. 
                If you have used the Patient Portal for previous testing results, please log in using the previous password. 
                If you forgot your password <a href='?action=lostpassword'>click here</a>. ";
            }
        }
    }

    if (!isset($errors->errors['email_exists']) && !empty($_POST['patient_code'])) {
        $patient_id = trpa_patient_id_from_code($_POST['patient_code'], $_POST['first_name'], $_POST['last_name'], $_POST['birthday']);
        if ($patient_id == 0) {
            //find patient
            $patient = false;
            if (function_exists('tr_pdd_get_batch_types')) {
                //search assen
                foreach (tr_pdd_get_batch_types() as $btype) {
                    $batch_tbl = tr_pdd_get_table_by_btype($btype);
                    $sql_2 = "select pa.pa_first, 
			                        pa.pa_last,
			                        pa.pa_dob,
			                        pa.pa_ID
			                        
			                    from {$batch_tbl} ba
			                    JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
								where ba.assen = '{$_POST['patient_code']}' 
								";
                    $patient = $wpdb->get_row($sql_2);

                    if ($patient) {
                        break;
                    }
                }
            }

            if (!$patient) {
                $errors->add('patient_code', '<b>Error</b>: Please correct Patient Code!');
            } else {
                $user = $wpdb->get_row("select * from {$wpdb->users} where patient_id = {$patient->pa_ID}");
                if ($user) {
                    $errors->add('user_exist', '<b>Error</b>: Email or Phone exist please go to <a href="?action=lostpassword">forgot password</a>!');
                } else if (strtolower($_POST['first_name']) != strtolower($patient->pa_first)) {
                    $errors->add('patient_firstname', '<b>Error</b>: Please correct First Name!');
                } else if (strtolower($_POST['last_name']) != strtolower($patient->pa_last)) {
                    $errors->add('patient_lastname', '<b>Error</b>: Please correct Last Name!');
                } else {
                    $errors->add('patient_dob', '<b>Error</b>: Please correct Birtday!');
                }
            }
        } else {
            //check users by patientid
            $user = $wpdb->get_row("select * from {$wpdb->users} where patient_id = {$patient_id}");
            if ($user) {
                $errors->add('user_exist', '<b>Error</b>: Email or Phone exist please use forgot password!');
            }
        }
    }

    return $errors;
}

add_action('register_new_user', 'trcppr_register_new_user');
function trcppr_register_new_user($user_id)
{
    global $wpdb;

    if (isset($_POST['patient_code'])) {
        trpa_set_patient_code($user_id, $_POST['patient_code'], $_POST['first_name'], $_POST['last_name'], $_POST['birthday']);

        wp_update_user(array('role' => 'patient', 'ID' => $user_id));
    }
    if (isset($_POST['first_name'])) {
        update_user_meta($user_id, 'first_name', $_POST['first_name']);
    }
    if (isset($_POST['last_name'])) {
        update_user_meta($user_id, 'last_name', $_POST['last_name']);
    }
    if (isset($_POST['middle_name'])) {
        update_user_meta($user_id, 'middle_name', $_POST['middle_name']);
    }

    if (isset($_POST['birthday'])) {
        update_user_meta($user_id, 'birthday', $_POST['birthday']);
    }
    if (isset($_POST['phone'])) {
        $_POST['phone'] = trim($_POST['phone']);
        $_POST['phone'] = str_replace(array('-', '.', ' ', '(', ')'), '', $_POST['phone']);
        $_POST['phone'] = trim($_POST['phone']);
        update_user_meta($user_id, 'phone', $_POST['phone']);
    }
    if (isset($_POST['address'])) {
        update_user_meta($user_id, 'address', $_POST['address']);
    }
    if (isset($_POST['city'])) {
        update_user_meta($user_id, 'city', $_POST['city']);
    }
    if (isset($_POST['state'])) {
        update_user_meta($user_id, 'state', $_POST['state']);
    }
    if (isset($_POST['zip'])) {
        update_user_meta($user_id, 'zip', $_POST['zip']);
    }
    $patient_id = tr_patient_get_id_patient($user_id);

    if ($patient_id > 0) {
        $patient = $wpdb->get_row("select * from ci_patients where pa_ID = '{$patient_id}' ", ARRAY_A);
        $update = array();
        if (empty($patient['pa_phone']) || $patient['pa_phone'] == '000-000-0000') {
            $update['pa_phone'] = $_POST['phone'];
            if (empty($update['pa_phone'])) {
                unset($update['pa_phone']);
            }
        }
        if (empty($patient['pa_email']) || $patient['pa_email'] == '0@0') {
            $update['pa_email'] = $_POST['user_email'];
        }

        if (!empty($update)) {
            $wpdb->update('ci_patients', $update, array('pa_ID' => $patient_id));
        }
    }
}

add_action('login_head', 'trcppr_data_login_head', 998);

function trcppr_data_login_head()
{
    $pa_options = get_option('trpatient_settings', array());
    if ($pa_options['hide_login_bg']) {
    ?>
        <style>
            html body,
            html #wp-auth-check-wrap #wp-auth-check {
                background: none !important
            }

            body.login form .input,
            body.login form select,
            body.login input[type=text],
            body.login input[type=email],
            body.login input[type=password] {
                background: #ececec !important;
            }

            body.login #backtoblog a,
            body.login #nav a,
            body.login a,
            .login,
            .login form label,
            .login form,
            .login .message {
                color: #333;
            }
        </style>
    <?php
    }


    $url_logo_header = '';

    if (!empty($pa_options['logo_login'])) {
        $url_logo_header = wp_get_attachment_image_url($pa_options['logo_login'], 'large');
        $img_logo_path = get_attached_file($pa_options['logo_login']);
        if (!file_exists($img_logo_path)) {
            $url_logo_header = '';
        }
    }

    if (empty($url_logo_header)) {
        $current_lab = CI_Base_Lab::get_current_lab();
        if ($current_lab) {
            $upload_dir = tr_wp_upload_dir();
            $logo       = $current_lab['lab_logo'];
            $url_logo_header   = $upload_dir['baseurl'] . '/logos/' . $logo;
        } else {
            $url_logo_header = TRCPPR_URL . 'images/logo.png';
        }
    }


    ?>
    <style>
        html body.login h1 a,
        body.login h1 a {
            background-image: url(<?php echo $url_logo_header ?>) !important;
            background-size: contain !important;
            background-position: center !important;
            height: 100px !important;
            width: auto !important;
        }

        .forgetmenot {
            display: none;
        }
    </style>
<?php
}
add_filter('login_url', 'trcppr_login_url');
function trcppr_login_url($url)
{
    if (defined('OTPA_V') && isset($_GET['type']) && $_GET['type'] == 'phone') {
        $url =  home_url('otpa/passwordless-login');
    }
    return $url;
}



add_filter('lostpassword_errors', 'trpl_lostpassword_errors', 99, 2);
function trpl_lostpassword_errors($errors, $user_data)
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

add_filter('lostpassword_user_data', 'trpl_lostpassword_user_data', 99, 2);
function trpl_lostpassword_user_data($user_data, $errors)
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