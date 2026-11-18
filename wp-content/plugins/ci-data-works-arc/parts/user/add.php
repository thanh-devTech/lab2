<?php
global $wp_roles;


$trajax     = isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : '';
$roles = get_editable_roles();

if ($trajax === 'add_user') {

    $user_name = sanitize_user($_REQUEST['user_name'] ?? '');
    $user_mail = sanitize_email($_REQUEST['user_mail'] ?? '');
    $user_role = sanitize_text_field($_REQUEST['user_role'] ?? '');
    $user_password = sanitize_text_field($_REQUEST['new_password'] ?? '');
    $skipConfirmation = (isset($_REQUEST['skipConfirmation']) ? $_REQUEST['skipConfirmation'] : '1') === '1';
    $current_blog_id  = get_current_blog_id();

    if ($user_name === '') {
        wp_send_json_error('Username is required');
    }

    if (!is_email($user_mail)) {
        wp_send_json_error('Invalid email address');
    }

    global $wp_roles;
    if (!$wp_roles->is_role($user_role)) {
        wp_send_json_error('Invalid role');
    }

    $existing_user = null;

    if (username_exists($user_name)) {
        $existing_user = get_user_by('login', $user_name);
    }

    if (!$existing_user && email_exists($user_mail)) {
        $existing_user = get_user_by('email', $user_mail);
    }

    if ($existing_user) {
        if (is_multisite()) {

            if (is_user_member_of_blog($existing_user->ID, $current_blog_id)) {
                wp_send_json_error([
                    'message' => 'User already exists and is already a member of this site.',
                    'type'    => 'already_member',
                ]);
            }

            $result = add_user_to_blog(
                $current_blog_id,
                $existing_user->ID,
                $user_role
            );

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }

            wp_send_json_success([
                'message'      => 'User already existed and has been added to this site.',
                'type'         => 'assigned',
                'user_id'      => $existing_user->ID,
                'redirect_url' => admin_url('admin.php?page=ci_user_edit&user_id=' . $existing_user->ID),
            ]);
        }

        wp_send_json_error([
            'message' => 'User already exists.',
            'type'    => 'already_exists',
        ]);
    }

    $password = ($user_password !== '') ? $user_password : wp_generate_password(12, true);

    $user_id = wp_insert_user([
        'user_login' => $user_name,
        'user_pass'  => $password,
        'user_email' => $user_mail,
        'role'       => $user_role,
    ]);

    if (is_wp_error($user_id)) {
        wp_send_json_error($user_id->get_error_message());
    }

    $user = get_user_by('id', $user_id);

    if (!$skipConfirmation) {


        $message = __(
            'Hi,

You\'ve been invited to join \'%1$s\' at
%2$s with the email: %3$s.
Your password: %4$s

Please click the following link to Login:
%5$s'
        );

        wp_mail(
            $user->user_email,
            sprintf(__('[%s] Joining to'), wp_specialchars_decode(get_option('blogname'))),
            sprintf(
                $message,
                get_option('blogname'),
                home_url(),
                $user_mail,
                $password,
                home_url('/wp-login.php')
            ),
            ''
        );
    }

    wp_send_json_success([
        'message'      => 'Add user success!',
        'type'         => 'created',
        'user_id'      => $user_id,
        'redirect_url' => admin_url('admin.php?page=ci_user_edit&user_id=' . $user_id),
    ]);
}

wp_enqueue_script('password-strength-meter');
?>
<style>
    input[type=email] {
        border-radius: calc(var(--radius) - 2px) !important;
        background-color: oklch(0.98 0 0) !important;
        border: none;
    }

    .swal2-container {
        z-index: 9999;
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
</style>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?v=' . TRCPDD_VS ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<div class="wrap">
    <div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]" style="min-height: 80vh;">
        <div class="space-y-6 max-w-2xl">
            <div>
                <h1 class="text-2xl font-semibold">Add New User</h1>
                <p class="text-muted-foreground mt-1">Create a brand new user and add them to this portal.</p>
            </div>
            <form id="frm_profile" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                        <label data-slot="label" class="flex items-center gap-2 text-sm leading-none text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-left md:text-right font-semibold" for="username">Username <span class="text-muted-foreground font-normal">(required)</span></label>
                        <div class="md:col-span-2">
                            <input id="user_name" name="user_name" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" placeholder="Enter username" value="">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                        <label data-slot="label" class="flex items-center gap-2 text-sm leading-none text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-left md:text-right font-semibold" for="email">Email <span class="text-muted-foreground font-normal">(required)</span></label>
                        <div class="md:col-span-2">
                            <input id="user_mail" name="user_mail" type="email" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive" placeholder="Enter email address" value="">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center"><label data-slot="label" class="flex items-center gap-2 text-sm leading-none text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-left md:text-right font-semibold" for="role">Role</label>
                        <div class="md:col-span-2">
                            <select name="user_role" id="sel_role">
                                <?php foreach ($roles as $key => $r) {
                                ?>
                                    <option value="<?php echo $key; ?>" <?php if (isset($r['disabled']) && !empty($r['disabled'])) echo 'disabled'; ?>><?php echo $r['name']; ?></option>
                                <?php
                                }
                                ?>
                            </select>
                        </div>
                    </div>

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

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                        <label data-slot="label" class="flex items-center gap-2 text-sm leading-none text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-left md:text-right font-semibold pt-2">Skip Confirmation Email</label>
                        <div class="md:col-span-2">
                            <div class="flex items-center gap-2">
                                <button type="button" role="checkbox" aria-checked="true" data-state="checked" value="1" data-slot="checkbox"
                                    class="cursor-pointer peer bg-input-background dark:bg-input/30 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground dark:data-[state=checked]:bg-primary data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50"
                                    id="skipConfirmation">
                                    <span data-state="checked" data-slot="checkbox-indicator" class="flex items-center justify-center text-current transition-none" style="pointer-events: none;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check size-3.5">
                                            <path d="M20 6 9 17l-5-5"></path>
                                        </svg>
                                    </span>
                                </button>
                                <label data-slot="label" class="cursor-pointer flex items-center gap-2 text-sm leading-none text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 font-normal cursor-pointer" for="skipConfirmation">Add user without sending confirmation email</label>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <div>
                <button
                    id="btn_add_user"
                    data-slot="button"
                    class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground hover:bg-primary/90 h-9 py-2 has-[&gt;svg]:px-3 px-8" style="background-color: rgb(216, 61, 0);">
                    Add New User
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    var url_main_page = 'ci_user_add';
    let strengthResult = null;
    let passwordField = null;

    function updateCheckboxUI($el) {
        const isChecked = $el.attr("data-state") === "checked";

        $el.attr({
            "aria-checked": isChecked ? "true" : "false",
            "value": isChecked ? "1" : "0"
        });

        $el.find("span")
            .toggleClass("flex", isChecked)
            .toggleClass("hidden", !isChecked);
    }

    jQuery(document).ready(function($) {
        strengthResult = $('#password-strength');
        passwordField = $('#new_password');
        initCustomSelect("select");

        $(document).on('click', 'button[role="checkbox"]', function() {
            const $btn = $(this);
            const name = $btn.attr("name");
            const newState = $btn.attr("data-state") === "unchecked" ? "checked" : "unchecked";

            $btn.attr("data-state", newState);
            updateCheckboxUI($btn);
        });

        $("#btn_add_user").click(function() {
            var frm = document.getElementById('frm_profile');
            var formData = new FormData(frm);
            var skipConfirmation_val = $("#skipConfirmation").attr("value");
            formData.append('trajax', 'add_user');
            formData.append('skipConfirmation', skipConfirmation_val);

            jQuery.ajax({
                url: "admin.php?page=" + url_main_page,
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    Swal.close();
                    if (response.success) {
                        var data = response.data;
                        var isAssigned = data.type === 'assigned';

                        Swal.fire({
                            title: 'Success',
                            text: isAssigned ?
                                'User already existed and has been added to this site.' : 'User created successfully!',
                            icon: isAssigned ? 'warning' : 'success',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(function() {
                            window.location.href = data.redirect_url;
                        });
                    } else {
                        var errMsg = typeof response.data === 'object' ?
                            response.data.message :
                            response.data;

                        Swal.fire({
                            title: 'Error',
                            text: errMsg,
                            icon: 'error'
                        });
                    }
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

    });
</script>