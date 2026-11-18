<?php
global $current_role;
global $is_ci_admin;
global $wpdb;
global $current_user;

$trajax  = isset($_REQUEST['trajax']) ? sanitize_text_field($_REQUEST['trajax']) : '';
$mode    = isset($_REQUEST['mode']) ? sanitize_text_field($_REQUEST['mode']) : '';
$user_id = isset($_REQUEST['user_id']) ? intval($_REQUEST['user_id']) : 0;

$allowed_edit = false;

$user_id = $user_id ?: $current_user->ID;

$profile_user = get_user_to_edit($user_id);

if (!$profile_user) {
    wp_die(__('User not found.'));
}

if ($current_user->ID === $profile_user->ID) {
    define('IS_PROFILE_PAGE', true);
    $allowed_edit = true;
} else {
    define('IS_PROFILE_PAGE', false);
    $allowed_roles = ['administrator', 'clms', 'AdminCIDW'];
    if (!empty(array_intersect($profile_user->roles, $allowed_roles))) {
        $allowed_edit = true;
    }

    if (current_user_can('list_users')) {
        $allowed_edit = true;
    }

    if (!$allowed_edit) {
        if ($trajax == '') {
            wp_die(__('Sorry, you are not allowed to access this page.'));
        } else {
            wp_send_json_error('Sorry, you are not allowed to access this page.');
        }
    }
}

$user_id = $profile_user->ID;

$sign_dir = tr_get_signature_dir();
$dir_sign_img  = $sign_dir['dir'];
$url_sign_img  = $sign_dir['url'] . '/' . $profile_user->ID . '_user.png';
$path_sing_img = $sign_dir['path'] . '/' . $profile_user->ID . '_user.png';

if ($trajax == 'save_profile') {
    global $trpdd_old_user_cache, $trpdd_custom_profile_saving;
    $trpdd_custom_profile_saving = true;

    $current_user_data = get_userdata($user_id);
    $trpdd_old_user_cache[$user_id] = [
        'first_name'   => $current_user_data->first_name,
        'last_name'    => $current_user_data->last_name,
        'display_name' => $current_user_data->display_name,
        'user_email'   => $current_user_data->user_email,
        'role'         => $current_user_data->roles,
    ];
    $firstName = isset($_POST['firstName']) ? $_POST['firstName'] : '';
    $lastName = isset($_POST['lastName']) ? $_POST['lastName'] : '';
    $nickname = isset($_POST['nickname']) ? $_POST['nickname'] : '';
    $display_name = isset($_POST['display_name']) ? $_POST['display_name'] : '';
    $password     = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $email        = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

    if (empty($firstName)) wp_send_json_error('First name cannot be left blank.');
    if (empty($lastName)) wp_send_json_error('The last name cannot be left blank.');
    if (empty($nickname)) wp_send_json_error('The nickname field cannot be left blank.');
    if (empty($display_name)) wp_send_json_error('The display name is invalid.');
    if ($email === '' || !is_email($email)) wp_send_json_error('Invalid email address.');

    $userdata = [
        'ID'           => $user_id,
        'first_name'   => $firstName,
        'last_name'    => $lastName,
        'display_name' => $display_name,
        'user_email'   => $email,
    ];
    if ($password !== '') {
        $userdata['user_pass'] = $password;
    }
    if (isset($_POST['role']) && current_user_can('edit_users')) {
        $new_role = sanitize_text_field($_POST['role']);
        if ($new_role === 'data_export' && !is_super_admin()) {
            wp_send_json_error('Only Super Admin can assign the Data Export role.');
        }
        $user = new WP_User($user_id);

        if ($new_role === 'no_role') {
            $user->set_role('');
        } else {
            $all_roles = wp_roles()->get_names();

            if (array_key_exists($new_role, $all_roles)) {
                $user->set_role($new_role);
            }
        }
    }

    if (isset($_FILES['user_signature'])) {
        $logoname = $user_id . '_user.png';
        $imgfile = $this->uploadfile($_FILES['user_signature'], $logoname, $dir_sign_img);
        if (!empty($imgfile)) {
            update_user_meta($user_id, '_signature', $imgfile);
        }
    }

    $result = wp_update_user($userdata);

    if (is_wp_error($result)) wp_send_json_error($result->get_error_message());

    do_action('edit_user_profile_update', $user_id);

    update_user_meta($user_id, 'nickname', $nickname);

    if (class_exists('WC_Admin_Profile')) {
        $profile = new WC_Admin_Profile();
        $groups  = $profile->get_customer_meta_fields();

        $address_groups = ['billing', 'shipping'];

        //Woo
        foreach ($address_groups as $group_key) {
            if (empty($groups[$group_key]['fields'])) continue;

            foreach ($groups[$group_key]['fields'] as $meta_key => $field) {
                if (isset($_POST[$meta_key])) {
                    $value = wc_clean($_POST[$meta_key]);
                    update_user_meta($user_id, $meta_key, $value);
                }
            }
        }
    }

    wp_send_json_success('Save Profile success!');
    exit;
}

if ($trajax == '2_factor_auth') {
    do_action('edit_user_profile_update', $user_id);
    $messages = get_transient('wp2fa_message_' . $user_id);
    delete_transient('wp2fa_message_' . $user_id);
    trpdd_log_user(get_current_user_id(), '2fa_user', $messages, $user_id, '', '', '', $user_id);
    wp_send_json_success($messages ?: []);
}

if ($trajax === 'logout_everywhere_else') {

    if (!is_user_logged_in()) wp_send_json_error('You are not logged in.');

    if (
        !isset($_POST['_wpnonce']) ||
        !wp_verify_nonce($_POST['_wpnonce'], 'logout_everywhere_nonce')
    ) {
        wp_send_json_error('Invalid nonce.');
    }
    wp_destroy_other_sessions();
    wp_send_json_success('Logged out from all other devices');
}

if ($trajax === 'remove_sign') {
    if (!is_user_logged_in()) wp_send_json_error('You are not logged in.');
    $sign_file_name  = $user_id . '_user.png';
    $sign_file_path  = trailingslashit($sign_dir['path']) . $sign_file_name;
    if (file_exists($sign_file_path)) unlink($sign_file_path);
    delete_user_meta($user_id, '_signature');
    wp_send_json_success('Signature deleted successfully!');
}

$username   = $profile_user->user_login;
$email      = $profile_user->user_email;
$first_name = $profile_user->first_name;
$last_name  = $profile_user->last_name;
$display    = $profile_user->display_name;
$nickname   = $profile_user->nickname;
$mate_signature = get_user_meta($user_id, '_signature', true);
$public_display = [];

$public_display['display_nickname'] = $profile_user->nickname;
$public_display['display_username'] = $profile_user->user_login;

if (!empty($profile_user->first_name)) {
    $public_display['display_firstname'] = $profile_user->first_name;
}

if (!empty($profile_user->last_name)) {
    $public_display['display_lastname'] = $profile_user->last_name;
}

if (!empty($profile_user->first_name) && !empty($profile_user->last_name)) {
    $public_display['display_firstlast'] = $profile_user->first_name . ' ' . $profile_user->last_name;
    $public_display['display_lastfirst'] = $profile_user->last_name . ' ' . $profile_user->first_name;
}

$public_display = array_unique($public_display);
wp_enqueue_script('password-strength-meter');

$back_url = wp_get_referer();

if (!$back_url) $back_url = admin_url();
?>
<style>
    h4 {
        margin-bottom: 0px !important;
    }

    input[type=button],
    input[type=email],
    input[type=search],
    input[type=date],
    input[type=submit],
    input[type=tel],
    input[type=text],
    input[type=number],
    input[type=color],
    input[type=password],
    select,
    textarea,
    .selected {
        border-radius: calc(var(--radius) - 2px) !important;
        background-color: oklch(0.98 0 0) !important;
        border: none;
    }

    .swal2-container {
        z-index: 9999;
    }

    .signature_canvas canvas {
        border: 1px solid #ffffff;
    }

    #password-strength.short {
        background-color: #f47272;
        border: solid 1.2px #dc3232;
        padding: 2px;
    }

    #password-strength.bad {
        background-color: #ffdb79;
        border: solid 1.2px #ffb900;
        padding: 2px;
    }

    #password-strength.good {
        background-color: #71d2ef;
        border: solid 1.2px #00a0d2;
        padding: 2px;
    }

    #password-strength.strong {
        background-color: #6ae675;
        border: solid 1.2px #46b450;
        padding: 2px;
    }

    .b_short {
        border: solid 2px #dc3232 !important;
    }

    .b_bad {
        border: solid 2px #ffb900 !important;
    }

    .b_good {
        border: solid 2px #00a0d2 !important;
    }

    .b_strong {
        border: solid 2px #46b450 !important;
    }

    #box_auth h2 {
        font-size: 1em;
        margin: 1.33em 0;
    }

    #box_auth div table tbody tr th label,
    #box_auth div table tbody tr th {
        font-size: 12px !important;
        font-weight: 500;
        color: #364153;
    }

    #box_auth div table tbody tr th {
        width: 33%;
    }

    p.description {
        width: 100%;
        margin-top: 3px !important;
        margin-bottom: 3px !important;
    }

    #box_auth .button-primary {
        border-radius: 5px;
    }

    .wc-col {
        margin-bottom: 10px;
    }

    .wc-col input {
        width: 100%;
    }

    .wc-col label {
        width: 100%;
        display: block;
        margin-bottom: 5px;
        color: #626262;
    }

    .wc-col label .optional {
        color: red;
    }

    .description {
        width: 100% !important;
        font-size: 10px !important;
        margin-top: 6px !important;
    }
</style>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?v=' . TRCPDD_VS ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<div class="wrap">
    <div class="flex-1 p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
        <form id="frm_profile" method="post" class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Profile</h1>
                </div>

                <button onclick="window.location='<?php echo $back_url; ?>';return false;" data-slot="button" class="inline-flex cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x h-4 w-4 mr-2" aria-hidden="true">
                        <path d="M18 6 6 18"></path>
                        <path d="m6 6 12 12"></path>
                    </svg>Close
                </button>
            </div>

            <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                    <h4 data-slot="card-title" class="leading-none">Personal Information</h4>
                    <input type="hidden" id="logout_nonce" name="logout_nonce" value="<?php echo wp_create_nonce('logout_everywhere_nonce'); ?>">
                    <input type="hidden" id="user_id" name="user_id" value="<?php echo $user_id; ?>">
                </div>
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                        <label data-slot="label" class="flex items-center gap-2 text-sm leading-none font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-left md:text-right pt-2" for="username">Username</label>
                        <div class="md:col-span-2 space-y-2">
                            <input
                                data-slot="input"
                                class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-gray-50" id="username" disabled=""
                                value="<?php echo $username ?>">
                            <p class="text-sm text-muted-foreground">Usernames cannot be changed.</p>
                        </div>
                    </div>
                    <?php if (!IS_PROFILE_PAGE) { ?>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                            <label data-slot="label" class="flex items-center gap-2 text-sm leading-none font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-left md:text-right" for="role">
                                Role
                            </label>
                            <div class="md:col-span-2">
                                <select name="role" id="role" <?php echo (!current_user_can('edit_users')) ? 'disabled' : ''; ?>>
                                    <?php
                                    $editable_roles = get_editable_roles();
                                    $user_roles = $profile_user->roles;
                                    $current_role = !empty($user_roles) ? $user_roles[0] : '';

                                    foreach ($editable_roles as $role_slug => $role_info) {
                                        echo '<option value="' . esc_attr($role_slug) . '" ' . (isset($role_info['disabled']) && $role_info['disabled'] ? 'disabled ' : '') . selected($current_role, $role_slug, false) . '>';
                                        echo esc_html($role_info['name']);
                                        echo '</option>';
                                    }
                                    ?>
                                    <option value="no_role" <?php selected($current_role, ''); ?>>— No role for this site —</option>
                                </select>
                                <p class="text-sm text-muted-foreground mt-2">User role determines permissions and capabilities</p>
                            </div>
                        </div>
                    <?php } ?>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                        <label data-slot="label" class="flex items-center gap-2 text-sm leading-none font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-left md:text-right" for="firstName">First Name</label>
                        <div class="md:col-span-2">
                            <input data-slot="input"
                                class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive"
                                id="firstName"
                                name="firstName"
                                value="<?php echo $first_name; ?>">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                        <label data-slot="label" class="flex items-center gap-2 text-sm leading-none font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-left md:text-right" for="lastName">Last Name</label>
                        <div class="md:col-span-2">
                            <input
                                data-slot="input"
                                class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive"
                                id="lastName"
                                name="lastName"
                                value="<?php echo $last_name; ?>">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center"><label data-slot="label" class="flex items-center gap-2 text-sm leading-none font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-left md:text-right" for="nickname">Nickname <span class="text-muted-foreground">(required)</span></label>
                        <div class="md:col-span-2">
                            <input
                                data-slot="input"
                                class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive"
                                id="nickname"
                                name="nickname"
                                value="<?php echo $nickname; ?>">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                        <label data-slot="label" class="flex items-center gap-2 text-sm leading-none font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-left md:text-right" for="displayAs">Display name publicly as</label>
                        <div class="md:col-span-2">
                            <select name="display_name">
                                <?php foreach ($public_display as $display): ?>
                                    <option value="<?php echo $display; ?>"
                                        <?php selected($profile_user->display_name, $display); ?>>
                                        <?php echo $display; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                        <label data-slot="label" class="flex items-center gap-2 text-sm leading-none font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-left md:text-right pt-2" for="email">
                            Email <span class="text-muted-foreground">(required)</span>
                        </label>
                        <div class="md:col-span-2 space-y-2">
                            <input
                                name="email"
                                type="email"
                                data-slot="input"
                                class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive"
                                id="email"
                                value="<?php echo $email; ?>">
                            <p class="text-sm text-muted-foreground">If you change your email, we'll send a confirmation message to the new address. It won't become active until confirmed.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                    <h4 data-slot="card-title" class="leading-none">Signature</h4>
                </div>

                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                        <div class="text-left md:text-right"></div>
                        <div class="md:col-span-2">

                            <div class="flex gap-2 mb-2">
                                <div class="text-left md:text-right"></div>
                                <input id="field_sign_user_file" name="sign_user_file" type="file" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive flex-1" accept="image/*">
                                <button onclick="$('#field_sign_user_file').click();return false;" data-slot="button" class="cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-upload h-4 w-4" aria-hidden="true">
                                        <path d="M12 3v12"></path>
                                        <path d="m17 8-5-5-5 5"></path>
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    </svg>
                                </button>
                            </div>

                            <div class="cursor-pointer relative flex justify-center items-center border rounded-md p-6 bg-gray-50">
                                <div>
                                    <?php
                                    if (file_exists($path_sing_img)) {
                                        echo '<img id="user_img_sign" src="' . $url_sign_img . '?' . time() . '" style="max-height:100px;background:#fff" alt="Director Signature" class="h-32 w-auto object-contain">';
                                    }

                                    if ($mode == '') {

                                        echo '<div class="signaturepad" style="' . (empty($mate_signature) ? '' : 'display:none') . '">';
                                        echo tr_signature(array('name' => 'lab_director_signature', 'required' => false, 'clear' => false));
                                        echo '</div>';
                                    ?>
                                        <button id="btn_remove_sign" data-slot="button" class="cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 gap-1.5 has-[&gt;svg]:px-2.5 absolute top-2 right-2 h-8 w-8 rounded bg-white/90 hover:bg-white text-red-600 hover:text-red-700 shadow-sm p-0">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash2 lucide-trash-2 h-4 w-4" aria-hidden="true">
                                                <path d="M10 11v6"></path>
                                                <path d="M14 11v6"></path>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                                                <path d="M3 6h18"></path>
                                                <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            </svg>
                                        </button>
                                    <?php
                                    }
                                    ?>
                                </div>
                            </div>
                            <p class="text-sm text-muted-foreground mt-2">Digital signature for approving laboratory results</p>
                        </div>
                    </div>
                </div>
            </div>

            <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                    <h4 data-slot="card-title" class="leading-none">Account Management</h4>
                </div>
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                        <label data-slot="label" class="flex items-center gap-2 text-sm leading-none font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-left md:text-right" for="newPassword">New Password</label>
                        <div id="arean_all_item_set_new_password" class="hidden md:col-span-2 gap-2">
                            <div class="w-full">
                                <input
                                    name="new_password"
                                    type="text"
                                    data-slot="input"
                                    class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive"
                                    id="new_password"
                                    placeholder="Leave blank to keep current password"
                                    value="">
                                <div id="password-strength"></div>
                            </div>

                            <button id="btn_show_hide_password" data-slot="button" class="cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5">
                                <span class="dashicons dashicons-hidden" aria-hidden="true"></span>
                                <span style="display: none;" class="dashicons dashicons-visibility" aria-hidden="true"></span>
                                Hide
                            </button>

                            <button id="btn_cancel_set_password" data-slot="button" class="cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5">
                                <svg fill="#000000" width="64px" height="64px" viewBox="0 0 32 32" version="1.1" xmlns="http://www.w3.org/2000/svg">
                                    <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                                    <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                                    <g id="SVGRepo_iconCarrier">
                                        <title>cancel1</title>
                                        <path d="M16 29c-7.18 0-13-5.82-13-13s5.82-13 13-13 13 5.82 13 13-5.82 13-13 13zM16 6c-5.522 0-10 4.478-10 10s4.478 10 10 10c5.523 0 10-4.478 10-10s-4.477-10-10-10zM20.537 19.535l-1.014 1.014c-0.186 0.186-0.488 0.186-0.675 0l-2.87-2.87-2.87 2.87c-0.187 0.186-0.488 0.186-0.675 0l-1.014-1.014c-0.186-0.186-0.186-0.488 0-0.675l2.871-2.869-2.871-2.87c-0.186-0.187-0.186-0.489 0-0.676l1.014-1.013c0.187-0.187 0.488-0.187 0.675 0l2.87 2.87 2.87-2.87c0.187-0.187 0.489-0.187 0.675 0l1.014 1.013c0.186 0.187 0.186 0.489 0 0.676l-2.871 2.87 2.871 2.869c0.186 0.187 0.186 0.49 0 0.675z"></path>
                                    </g>
                                </svg>
                                Cancel
                            </button>
                        </div>

                        <div id="arean_btn_set_new_password" class="md:col-span-2 flex gap-2">
                            <button id="btn_set_new_password" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3">
                                Set New Password
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                        <label data-slot="label" class="flex items-center gap-2 text-sm leading-none font-medium text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-left md:text-right">Sessions</label>
                        <div class="md:col-span-2 space-y-2">
                            <button id="btn_log_out_everywhere_else" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3">
                                Log Out Everywhere Else
                            </button>
                            <p class="text-sm text-muted-foreground">Left yourself logged in somewhere? Log out everywhere else and stay signed in here.</p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (IS_PROFILE_PAGE) {
                if (has_action('edit_user_profile_factor_auth') && get_option('tr_security') && get_option('tr_security')['2fa_enabled']) {
            ?>
                    <div id="box_auth" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                            <?php
                            do_action('edit_user_profile_factor_auth', $profile_user);
                            ?>
                        </div>
                    </div>
                <?php }
            } else {
                if (has_action('edit_user_profile_factor_auth') && get_option('tr_security') && get_option('tr_security')['2fa_enabled']) {
                ?>
                    <div id="box_auth" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                            <?php
                            do_action('edit_user_profile_factor_auth', $profile_user, 1);
                            ?>
                        </div>
                    </div>
                <?php } ?>
                <div id="box_info_other" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                    <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                        <h4 data-slot="card-title" class="leading-none">Other settings</h4>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                        <?php
                        do_action('edit_user_profile_other_info', $profile_user, 1);
                        ?>
                    </div>
                </div>
            <?php } ?>

            <?php if (class_exists('WC_Admin_Profile')) { ?>
                <div id="box_info_wc_billng" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                    <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                        <h4 data-slot="card-title" class="leading-none">Billing address</h4>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                        <?php
                        $profile_user = get_user_by('id', $user_id);
                        $profile = new WC_Admin_Profile();
                        $groups  = $profile->get_customer_meta_fields();
                        $billing = $groups['billing']['fields'];

                        foreach ($billing as $key => $field) {

                            $value = get_user_meta($profile_user->ID, $key, true);

                            echo '<div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">';
                            echo '<label>' . $field['label'] . '</label>';
                            echo '<div class="md:col-span-2 wc-col">';
                            woocommerce_form_field(
                                $key,
                                $field,
                                $value
                            );
                            echo '</div>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>

                <div id="box_info_wc_shipping" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                    <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                        <h4 data-slot="card-title" class="leading-none">Shipping address</h4>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                            <label>Copy from billing address</label>
                            <div class="md:col-span-2 wc-col">
                                <button id="btn_copy_wc"
                                    onclick="
                    document.getElementById('shipping_first_name').value =
                    document.getElementById('billing_first_name').value;

                    document.getElementById('shipping_last_name').value =
                    document.getElementById('billing_last_name').value;
                    return false;
                    "
                                    data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground hover:bg-primary/90 h-9 py-2 has-[&gt;svg]:px-3 px-8">
                                    <svg fill="#ffffff" width="64px" height="64px" viewBox="0 0 1920 1920" xmlns="http://www.w3.org/2000/svg" stroke="#ffffff">
                                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                                        <g id="SVGRepo_iconCarrier">
                                            <path d="M1468.183 451.76v1468.184H0V451.76h1468.183ZM1920 0v1468.296h-338.812V338.812H451.704V0H1920ZM338.812 1016.446h790.56V903.509h-790.56v112.937Zm0 225.874h564.686v-112.937H338.812v112.937Zm0 225.988h790.56v-113.05h-790.56v113.05Z" fill-rule="evenodd"></path>
                                        </g>
                                    </svg>
                                    Copy
                                </button>
                            </div>
                        </div>

                        <?php
                        $profile_user = get_user_by('id', $user_id);
                        $profile = new WC_Admin_Profile();
                        $groups  = $profile->get_customer_meta_fields();
                        $shipping = $groups['shipping']['fields'];

                        foreach ($shipping as $key => $field) {
                            if ($key == 'copy_billing') continue;
                            $value = get_user_meta($profile_user->ID, $key, true);

                            echo '<div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">';
                            echo '<label>' . $field['label'] . '</label>';
                            echo '<div class="md:col-span-2 wc-col">';
                            woocommerce_form_field(
                                $key,
                                $field,
                                $value
                            );
                            echo '</div>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
            <?php } ?>

            <div class="flex justify-end pb-8">
                <?php if ($mode == '') { ?>
                    <button id="btn_save" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground hover:bg-primary/90 h-9 py-2 has-[&gt;svg]:px-3 px-8">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save h-4 w-4 mr-2" aria-hidden="true">
                            <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                            <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                            <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                        </svg>
                        Update Profile
                    </button>
                <?php } ?>
            </div>
        </form>
    </div>
</div>
<script>
    var url_main_page = 'ci_user_edit';
    let strengthResult = null;
    let passwordField = null;

    function dataURLtoBlob(dataurl) {
        let arr = dataurl.split(',');
        let mime = arr[0].match(/:(.*?);/)[1];
        let bstr = atob(arr[1]);
        let n = bstr.length;
        let u8arr = new Uint8Array(n);
        while (n--) {
            u8arr[n] = bstr.charCodeAt(n);
        }
        return new Blob([u8arr], {
            type: mime
        });
    }

    function isCanvasBlank(canvas) {
        const blank = document.createElement('canvas');
        blank.width = canvas.width;
        blank.height = canvas.height;
        return canvas.toDataURL() === blank.toDataURL();
    }

    jQuery(document).ready(function($) {

        strengthResult = $('#password-strength');
        passwordField = $('#new_password');

        $('select[name="_lab_ids[]"]').attr("data-multi", "1").attr("id", "sel_labs");
        initCustomSelect("select");

        const iconInfo = `
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
            viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
            class="lucide lucide-info h-5 w-5 text-blue-600 mt-0.5 flex-shrink-0"
            aria-hidden="true">
        <circle cx="12" cy="12" r="10"></circle>
        <path d="M12 16v-4"></path>
        <path d="M12 8h.01"></path>
        </svg>
        `;
        $('p:contains("Two-factor authentication is currently disabled")').addClass('text-blue-800 flex items-start gap-2 p-4 rounded-lg bg-blue-50 border border-blue-200').prepend(iconInfo);
        $("#btn_save").click(function() {
            Swal.fire({
                title: 'Profile',
                text: 'Saving...Please wait...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => Swal.showLoading()
            });

            var frm = document.getElementById('frm_profile');
            var formData = new FormData(frm);
            formData.append('trajax', 'save_profile');
            formData.set('new_password', $("#new_password").val());

            let fileSig = frm.querySelector('input[type=file][name=sign_user_file]');
            if (fileSig.files.length) {
                formData.set('user_signature', fileSig.files[0]);
            } else {
                let canvas = document.querySelector('.signaturepad canvas');
                if (canvas && !isCanvasBlank(canvas)) {
                    let sigDataUrl = canvas.toDataURL('image/png');
                    let sigBlob = dataURLtoBlob(sigDataUrl);
                    formData.set('user_signature', sigBlob, 'signature.png');
                }
            }

            jQuery.ajax({
                url: "admin.php?page=" + url_main_page,
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    Swal.close();
                    if (response.success) {
                        Swal.fire({
                            title: 'Success',
                            text: response.data,
                            icon: 'success'
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.data,
                            icon: 'error'
                        });
                    }
                    return false;
                },
                error: function(err) {
                    Swal.close();
                    Swal.fire({
                        title: 'Error',
                        text: 'Network or server error.',
                        icon: 'error'
                    });
                }
            });
            return false;
        });


        $("#btn_log_out_everywhere_else").click(function() {
            Swal.fire({
                title: 'Log Out Everywhere Else',
                text: 'Please wait...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            var data_frm = [];
            data_frm.push({
                name: 'trajax',
                value: 'logout_everywhere_else'
            });
            data_frm.push({
                name: '_wpnonce',
                value: $("#logout_nonce").val()
            });
            jQuery.post("admin.php?page=" + url_main_page, data_frm, function(response) {
                Swal.close();
                if (response.success) {
                    Swal.fire({
                        title: 'success',
                        text: response.data,
                        icon: 'success'
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: response.data,
                        icon: 'error'
                    });
                }
            });

            return false;
        });

        $("#btn_remove_sign").click(function() {

            let canvas = document.querySelector('.signaturepad canvas');
            if (canvas) {
                let ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                if ($("#user_img_sign").length == 0)
                    return false;
            }

            Swal.fire({
                title: 'Confirm signature deletion?',
                text: 'The data, once deleted, cannot be recovered!',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Delete signature image',
                        text: 'Please wait...',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    $(".signaturepad").show();
                    $("#user_img_sign").remove();

                    var data_frm = [];
                    data_frm.push({
                        name: 'trajax',
                        value: 'remove_sign'
                    });
                    data_frm.push({
                        name: 'user_id',
                        value: $("#user_id").val()
                    });
                    jQuery.post("admin.php?page=" + url_main_page, data_frm, function(response) {
                        Swal.close();
                        if (response.success) {
                            Swal.fire({
                                title: 'success',
                                text: response.data,
                                icon: 'success'
                            });
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: response.data,
                                icon: 'error'
                            });
                        }
                    });
                }
            });
            return false;
        });

        $("#btn_set_new_password").click(function() {
            $("#new_password").val("<?php echo wp_generate_password(24, true, true); ?>");
            $("#arean_all_item_set_new_password").removeClass("hidden").addClass("flex");
            $("#arean_btn_set_new_password").removeClass("flex").addClass("hidden");
            checkPassStreng();
            return false;
        });

        $("#btn_cancel_set_password").click(function() {
            passwordField.removeClass('b_short').removeClass('b_bad').removeClass('b_good').removeClass('b_strong');
            $("#arean_all_item_set_new_password").removeClass("flex").addClass("hidden");
            $("#arean_btn_set_new_password").removeClass("hidden").addClass("flex");
            $("#new_password").val("");
            return false;
        });

        $("#btn_show_hide_password").click(function() {
            var icon_show = $(this).find(".dashicons-hidden");
            var icon_hide = $(this).find(".dashicons-visibility");
            if ($("#new_password").attr("type") == "text") {
                $("#new_password").attr("type", "password");
                icon_show.hide();
                icon_hide.show();
            } else {
                $("#new_password").attr("type", "text");
                icon_show.show();
                icon_hide.hide();
            }
            return false;
        });

        function checkPassStreng() {
            let strength = wp.passwordStrength.meter(
                passwordField.val(),
                [],
                passwordField.val()
            );

            let strengthText = '';
            strengthResult.removeClass().addClass('strength');
            passwordField.removeClass('b_short').removeClass('b_bad').removeClass('b_good').removeClass('b_strong');
            switch (strength) {
                case 0:
                case 1:
                    strengthText = 'Very weak';
                    strengthResult.addClass('short');
                    passwordField.addClass('b_short');
                    break;
                case 2:
                    strengthText = 'Weak';
                    strengthResult.addClass('bad');
                    passwordField.addClass('b_bad');
                    break;
                case 3:
                    strengthText = 'Medium';
                    strengthResult.addClass('good');
                    passwordField.addClass('b_good');
                    break;
                case 4:
                    strengthText = 'Strong';
                    strengthResult.addClass('strong');
                    passwordField.addClass('b_strong');
                    break;
            }

            strengthResult.text(strengthText);
        }

        passwordField.on('keyup', function() {
            checkPassStreng();
        });

        jQuery(document).on('click', 'button[name="wp2fa_action"]', function(e) {

            var act = $(this).val();
            Swal.fire({
                title: 'Authenticating...',
                text: 'Please wait...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            var data_frm = [];
            data_frm.push({
                name: 'trajax',
                value: '2_factor_auth'
            });
            data_frm.push({
                name: 'user_id',
                value: $("#user_id").val()
            });
            data_frm.push({
                name: 'wp2fa_code',
                value: $("#wp2fa_code").val()
            });
            data_frm.push({
                name: 'wp2fa_action',
                value: act
            });

            data_frm.push({
                name: 'wp2fa_profile_nonce',
                value: $("#wp2fa_profile_nonce").val()
            });
            jQuery.post("admin.php?page=" + url_main_page, data_frm, function(response) {
                Swal.close();

                const hasError = response.data.some(item => item.type === 'error');

                let htmlMessages = response.data
                    .map(item => `<p>${item.message}</p>`)
                    .join('');

                Swal.fire({
                    title: hasError ? 'Error' : 'Success',
                    html: htmlMessages,
                    icon: hasError ? 'error' : 'success'
                });

                if (hasSuccess) {
                    setTimeout(function() {
                        window.location.reload();
                    }, 5000);
                }
            });
            return false;
        });
    });
</script>