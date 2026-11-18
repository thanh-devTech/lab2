<?php
global $wpdb;

$trajax = isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : '';
$user = wp_get_current_user();
$comment = '';
$addmailing = false;
$sent = false;
$results = [];

if ($trajax == 'send_contact') {

    $name = isset($_REQUEST['name']) ? $_REQUEST['name'] : ($user->display_name ?? "Guest");
    $email = isset($_REQUEST['email']) ? $_REQUEST['email'] : '';
    $inquiry_type = isset($_REQUEST['inquiry_type']) ? $_REQUEST['inquiry_type'] : '';
    $priority_type = isset($_REQUEST['priority_type']) ? $_REQUEST['priority_type'] : '';
    $comment = isset($_POST['comment']) ? stripslashes($_POST['comment']) : '';
    $addmailing = isset($_POST['addmailing']) ? $_POST['addmailing'] : 0;

    if ($comment == '') {
        $results['status'] = 'error';
        $results['message'] = 'Comment cannot be blank!';
        echo json_encode($results);
        exit;
    }

    if ($name == '') {
        $results['status'] = 'error';
        $results['message'] = 'Name cannot be blank!';
        echo json_encode($results);
        exit;
    }

    if (empty($email)) {
        $results['status'] = 'error';
        $results['message'] = 'Email cannot be blank!';
        echo json_encode($results);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $results['status'] = 'error';
        $results['message'] = 'Invalid email format!';
        echo json_encode($results);
        exit;
    }

    if (empty($inquiry_type)) {
        $results['status'] = 'error';
        $results['message'] = 'Inquiry Type cannot be blank!';
        echo json_encode($results);
        exit;
    }

    if (empty($priority_type)) {
        $results['status'] = 'error';
        $results['message'] = 'Priority cannot be blank!';
        echo json_encode($results);
        exit;
    }

    $rs = false;
    $send = true;

    $results = [
        'status' => 'ok',
        'message' => 'Thank you for contacting us. We will respond within 1-2 business days.'
    ];

    try {
        if (!empty($comment)) {
            $rs = tr_client_send_hub([
                'action' => 'contact',
                'comment' => $comment,
                'addmailing' => $addmailing,
                'email' => $email,
                'user_email' => $user->user_email,
                'fullname' => $name,
                'inquiry_type' => $inquiry_type,
                'priority_type' => $priority_type
            ]);

            if (!$rs) {
                $results['status'] = 'error';
                $results['message'] = 'Failed to send data. Please try again later.';
                throw new Exception('Failed to send data to hub.');
            }
        } else {
            $results['status'] = 'error';
            $results['message'] = 'Comment cannot be empty!';
        }
    } catch (Exception $e) {
        $results['status'] = 'error';
        $results['message'] = 'An unexpected error occurred: ' . $e->getMessage();
    }

    echo json_encode($results);
    exit;
}
?>
<style>
    h1,
    h4 {
        margin: unset;
    }

    h1 {
        margin-bottom: 5px !important;
    }

    h4 {
        margin-bottom: 5px;
    }

    h5 {
        font-weight: var(--font-weight-medium);
        margin: 0px;
        --tw-font-weight: var(--font-weight-medium);
        font-size: 13px;
        margin-bottom: 10.5px;
    }

    h1,
    h2,
    h3,
    h4,
    h5,
    h6 {
        font-weight: unset !important;
        font-size: inherit;
    }

    h4 {
        font-size: 14.5px;
    }

    input[type=button],
    input[type=email],
    input[type=search],
    input[type=submit],
    input[type=tel],
    input[type=text],
    select,
    textarea,
    .selected {
        border-radius: calc(var(--radius) - 2px) !important;
        background-color: var(--color-yellow-50) !important;
        border: 1px solid #e2e2e2ff;
    }

    p {
        margin-bottom: 10px;
    }

    p strong {
        color: var(--color-gray-700);
    }
</style>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?' . TRCPDD_VS ?>"></script>
<div class="wrap">
    <div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8">
        <div class="space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
                <div>
                    <h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">Contact Us</h1>
                    <p class="text-muted-foreground mt-1">Reach out for support, questions, or general inquiries.</p>
                </div>
            </div>
            <div>
                <div class="grid grid-cols-3 gap-6">
                    <div class="col-span-2 space-y-6">
                        <div data-slot="card"
                            class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                            <div data-slot="card-header"
                                class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                                <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg
                                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" class="lucide lucide-mail h-5 w-5 text-primary"
                                        aria-hidden="true">
                                        <path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"></path>
                                        <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                                    </svg>Send Us a Message</h4>
                            </div>
                            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                                <div class="space-y-6">
                                    <div class="space-y-2"><label data-slot="label"
                                            class="flex items-center gap-2 text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
                                            for="name">Name *</label><input data-slot="input"
                                            class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-yellow-50 border-gray-300 focus:bg-yellow-50"
                                            id="name" placeholder="Enter your full name" value="<?php echo $user->display_name ?? ''; ?>"></div>
                                    <div class="space-y-2"><label data-slot="label"
                                            class="flex items-center gap-2 text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
                                            for="email">Email Address *</label>
                                        <input type="email" data-slot="input"
                                            class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-yellow-50 border-gray-300 focus:bg-yellow-50"
                                            id="email" placeholder="your.email@example.com" value="<?php echo $user->user_email ?? ''; ?>">
                                    </div>
                                    <div class="space-y-2">
                                        <label data-slot="label"
                                            class="flex items-center gap-2 text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
                                            for="inquiry-type">Inquiry Type *</label>
                                        <select id="inquiry_type" aria-hidden="true" tabindex="-1"
                                            style="position: absolute; border: 0px; width: 1px; height: 1px; padding: 0px; margin: -1px; overflow: hidden; clip: rect(0px, 0px, 0px, 0px); white-space: nowrap; overflow-wrap: normal;">
                                            <option value="">Select inquiry type</option>
                                            <option value="general">General Inquiry</option>
                                            <option value="technical">Technical Support</option>
                                            <option value="feature">Feature Request</option>
                                            <option value="suggestion">Suggestion/Feedback</option>
                                            <option value="training">Training Request</option>
                                            <option value="billing">Billing Question</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div class="space-y-2">
                                        <label data-slot="label"
                                            class="flex items-center gap-2 text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
                                            for="priority">Priority *
                                        </label>


                                        <select id="priority_type">
                                            <option value="">Select priority level</option>
                                            <option value="low">Low - General question or feedback</option>
                                            <option value="normal">Normal - Standard inquiry</option>
                                            <option value="high">High - Issue affecting workflow</option>
                                            <option value="urgent">Urgent - Critical system issue</option>
                                        </select>
                                    </div>
                                    <div class="space-y-2">
                                        <label data-slot="label"
                                            class="flex items-center gap-2 text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
                                            for="message">Message *
                                        </label>

                                        <textarea data-slot="textarea"
                                            class="placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 flex field-sizing-content min-h-16 w-full rounded-md border px-3 py-2 text-base transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm bg-yellow-50 border-gray-300 focus:bg-yellow-50 resize-none"
                                            id="message" placeholder="Tell us how we can help you..." rows="6"></textarea>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <button id="btn_check_contact" type="button" role="checkbox" aria-checked="false"
                                            data-state="unchecked" value="on" data-slot="checkbox"
                                            class="peer bg-input-background dark:bg-input/30 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground dark:data-[state=checked]:bg-primary data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50">
                                            <span data-slot="checkbox-indicator"
                                                class="items-center justify-center text-current transition-none"
                                                style="pointer-events: none; display: none;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-check size-3.5">
                                                    <path d="M20 6 9 17l-5-5"></path>
                                                </svg>
                                            </span>
                                        </button>

                                        <input type="checkbox" aria-hidden="true" tabindex="-1" value="on"
                                            style="transform: translateX(-100%); position: absolute; pointer-events: none; opacity: 0; margin: 0px; width: 14px; height: 14px;">

                                        <label id="label_check_contact" data-slot="label"
                                            class="flex items-center gap-2 text-sm leading-none select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 font-normal cursor-pointer"
                                            for="mailing-list">Add me to the mailing list for product updates and
                                            announcements</label>
                                    </div>
                                    <div class="flex">
                                        <button id="btn-send" data-slot="button"
                                            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive h-9 py-2 has-[&gt;svg]:px-3 bg-primary hover:bg-primary/90 text-white px-6"
                                            type="submit">Send Message</button>
                                        <div style="padding:5px">
                                            <svg id="loading_form_contact" style="display:none" xmlns="http://www.w3.org/2000/svg" width="64"
                                                height="18" viewBox="0 0 64 18" aria-hidden="true" role="img"
                                                style="/* display: none; */">
                                                <circle cx="9" cy="9" r="6" fill="#6B7280">
                                                    <animate attributeName="opacity" values="0.2;1;0.2" dur="1s"
                                                        repeatCount="indefinite" begin="0s"></animate>
                                                </circle>
                                                <circle cx="32" cy="9" r="6" fill="#6B7280">
                                                    <animate attributeName="opacity" values="0.2;1;0.2" dur="1s"
                                                        repeatCount="indefinite" begin="0.15s"></animate>
                                                </circle>
                                                <circle cx="55" cy="9" r="6" fill="#6B7280">
                                                    <animate attributeName="opacity" values="0.2;1;0.2" dur="1s"
                                                        repeatCount="indefinite" begin="0.3s"></animate>
                                                </circle>
                                            </svg>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-4 pb-2 text-xs text-muted-foreground border-t">
                            <p><strong style="color:unset">Note:</strong> This form is for general inquiries, technical support, and feedback only. Do not submit patient-specific information or protected health information (PHI) through this form. All patient data is protected under HIPAA regulations.</p>
                        </div>

                    </div>

                    <div class="col-span-1">
                        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                            <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                                <h4 data-slot="card-title" class="leading-none">Support Information</h4>
                            </div>
                            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-3 text-sm text-gray-600">
                                <p><strong>Response time:</strong> We typically respond within 24–48 business hours. Urgent technical issues may receive priority attention.</p>
                                <p><strong>What to include:</strong> For technical issues, please include relevant details such as error messages, screenshots, or specific steps to reproduce the problem. This helps us assist you more quickly.</p>
                                <p><strong>ABX interpretation questions:</strong> If your question relates to a specific igniteABX report interpretation, please use the "Request ABX Review" form so it reaches the correct review team.</p>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </div>
</div>
<script>
    jQuery(function($) {
        window.$ = $;
        $("#btn-send").click(function() {
            $("#loading_form_contact").show();
            var name = $("#name").val();
            var email = $("#email").val();
            var inquiry_type = $("#inquiry_type").val();
            var priority_type = $("#priority_type").val();
            var message = $("#message").val();
            var state_addmailing = $("#btn_check_contact").attr("data-state") === "checked" ? 1 : 0;
            jQuery.post("admin.php?page=ci_reportabx_contact", {
                trajax: 'send_contact',
                name: name,
                email: email,
                comment: message,
                inquiry_type: inquiry_type,
                priority_type: priority_type,
                addmailing: state_addmailing
            }, function(response) {
                $("#loading_form_contact").hide();
                var res = JSON.parse(response);
                if (res.status == "ok") {
                    showToast('success', 'Contact Us', 'Thank you for contacting us. We will respond within 1-2 business days.', 4000, 'top_center');
                    $("#name").val();
                    $("#email").val();
                    $("#type").val();
                    $("#message").val();
                } else {
                    showToast('error', 'Contact Us', res.message, 4000, 'top_center');
                }
                $("#btn_done_review").data("disabled", false).removeClass("opacity-50");
            });
            return false;
        });

        initCustomSelect("select");

        $("#btn_check_contact,#label_check_contact").click(function() {
            var $btn = $("#btn_check_contact");
            var state = $btn.attr("data-state");
            if (state === "unchecked") {
                $btn.attr("data-state", "checked").attr("aria-checked", "true");
                $btn.find("[data-slot='checkbox-indicator']").show();
            } else {
                $btn.attr("data-state", "unchecked").attr("aria-checked", "false");
                $btn.find("[data-slot='checkbox-indicator']").hide();
            }
        });
    });
</script>
