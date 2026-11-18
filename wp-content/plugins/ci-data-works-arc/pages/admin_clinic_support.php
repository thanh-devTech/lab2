<?php
global $wpdb;

$trajax = isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : '';
$user = wp_get_current_user();
$comment = '';
$sent = false;
$results = [];

$cs_settings = get_option('ci_clinic_support', [
    'enabled'  => 0,
    'phone'    => '1-800-555-0123',
    'email'    => 'clinicsupport@ignitelis.com',
    'hours'    => "Mon-Fri: 7:00 AM - 7:00 PM ET\nSat-Sun: 9:00 AM - 5:00 PM ET",
    'response' => 'We typically respond within 24 business hours. Urgent sample or result-related questions are prioritized.',
    'include'  => 'For specimen-related or results questions, please include the accession number. Do not submit other protected health information through this form',
    'supplies' => "When enabled, collection supplies can be requested directly through this form by selecting Order Collection Supplies and entering the needed quantities.",
    'supply_items'     => [],
    'supplies_enabled' => 0,
]);

if ($trajax == 'send_clinic_support') {

    global $wpdb;

    $name          = isset($_REQUEST['name']) ? sanitize_text_field($_REQUEST['name']) : ($user->display_name ?? "Guest");
    $email         = isset($_REQUEST['email']) ? sanitize_email($_REQUEST['email']) : ($user->user_email ?? '');
    $clinic_name   = isset($_REQUEST['clinic_name']) ? sanitize_text_field($_REQUEST['clinic_name']) : '';
    $phone         = isset($_REQUEST['phone']) ? sanitize_text_field($_REQUEST['phone']) : '';
    $inquiry_type  = isset($_REQUEST['inquiry_type']) ? sanitize_text_field($_REQUEST['inquiry_type']) : '';
    $priority_type = isset($_REQUEST['priority_type']) ? sanitize_text_field($_REQUEST['priority_type']) : '';
    $comment       = isset($_POST['comment']) ? sanitize_textarea_field(stripslashes($_POST['comment'])) : '';
    $supply_lines = [];
    $supply_items_clean = [];
    if (!empty($_POST['supply_items'])) {
        $supply_items_raw = json_decode(wp_unslash($_POST['supply_items']), true);
        if (is_array($supply_items_raw)) {
            $max_lookup = [];
            foreach ($cs_settings['supply_items'] as $si) {
                $max_lookup[$si['name']] = intval($si['max_qty']);
            }
            foreach ($supply_items_raw as $item) {
                $iname = sanitize_text_field($item['name'] ?? '');
                $iqty  = isset($item['qty']) ? intval($item['qty']) : 0;
                if ($iname === '' || $iqty <= 0) {
                    continue;
                }
                if (isset($max_lookup[$iname])) {
                    $iqty = min($iqty, $max_lookup[$iname]);
                }
                $supply_lines[]       = "- {$iname}: {$iqty}";
                $supply_items_clean[] = ['name' => $iname, 'qty' => $iqty];
            }
        }
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

    if (empty($clinic_name)) {
        $results['status'] = 'error';
        $results['message'] = 'Clinic Name cannot be blank!';
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

    $rs            = false;
    $mail_sent     = false;
    $error_message = '';

    $results = [
        'status'  => 'ok',
        'message' => 'Thank you for contacting us. We will respond within 24 business hours.'
    ];

    try {

        if (!empty($cs_settings['enabled']) && !empty($cs_settings['email'])) {
            $support_email = $cs_settings['email'];

            $subject = sprintf('[Clinic Support] %s - %s (%s Priority)', $clinic_name, $inquiry_type, $priority_type);

            $body  = "New clinic support request:\n\n";
            $body .= "Name: {$name}\n";
            $body .= "Email: {$email}\n";
            $body .= "Clinic: {$clinic_name}\n";
            $body .= "Phone: {$phone}\n";
            $body .= "Inquiry Type: {$inquiry_type}\n";
            if (!empty($supply_lines)) {
                $body .= "Collection Supplies Requested:\n" . implode("\n", $supply_lines) . "\n";
            }
            $body .= "Priority: {$priority_type}\n\n";
            $body .= "Message:\n{$comment}\n";

            $headers = [
                'Content-Type: text/plain; charset=UTF-8',
                'Reply-To: ' . $name . ' <' . $email . '>',
            ];

            $mail_sent = wp_mail($support_email, $subject, $body, $headers);

            if (!$mail_sent) {
                if (!empty($error_message)) {
                    $error_message .= ' | ';
                }
                $error_message .= 'wp_mail failed to send to clinic support.';
            }
        }
    } catch (Exception $e) {
        $results['status']  = 'error';
        $results['message'] = 'An unexpected error occurred: ' . $e->getMessage();
        $error_message = $e->getMessage();
    }

    $wpdb->insert('ci_clinic_support', [
        'user_id'        => get_current_user_id(),
        'account_name'   => $name,
        'account_email'  => $email,
        'clinic_name'    => $clinic_name,
        'phone'          => $phone,
        'inquiry_type'   => $inquiry_type,
        'priority_type'  => $priority_type,
        'comment'        => $comment,
        'mail_sent'      => $mail_sent ? 1 : 0,
        'error_message'  => $error_message,
        'created_at'     => date_i18n('Y-m-d H:i:s'),
        'supply_items' => json_encode($supply_items_clean),
    ]);

    echo json_encode($results);
    exit;
}

?>
<style>
    .cs-supply-qty::-webkit-outer-spin-button,
    .cs-supply-qty::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .cs-supply-qty {
        -moz-appearance: textfield;
    }

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

    input[type=number] {
        border-radius: calc(var(--radius) - 2px) !important;
        background-color: var(--input-background);
        border: 1px solid transparent;
    }

    input[readonly] {
        background-color: #f3f4f6 !important;
        color: #374151;
        cursor: not-allowed;
    }

    p {
        margin-bottom: 10px;
    }
</style>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?' . TRCPDD_VS ?>"></script>
<div class="wrap">
    <div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8">
        <div class="space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
                <div>
                    <h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">Clinic Support</h1>
                    <p class="text-muted-foreground mt-1">Get assistance with specimen submissions, test results, supplies, account questions, or other clinic operational needs.</p>
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
                                    </svg>Submit a Clinic Support Request</h4>
                            </div>
                            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                                <div class="space-y-6">
                                    <div class="space-y-2"><label data-slot="label"
                                            class="flex items-center gap-2 text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
                                            for="name">Name *</label>
                                        <div><input data-slot="input"
                                                class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive"
                                                id="name" readonly value="<?php echo esc_attr($user->display_name ?? ''); ?>">
                                        </div>
                                        <p class="text-xs text-gray-500">Using your account name</p>
                                    </div>
                                    <div class="space-y-2"><label data-slot="label"
                                            class="flex items-center gap-2 text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
                                            for="email">Email Address *</label>
                                        <div><input type="email" data-slot="input"
                                                class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive"
                                                id="email" readonly value="<?php echo esc_attr($user->user_email ?? ''); ?>">
                                        </div>
                                        <p class="text-xs text-gray-500">Using your account email</p>
                                    </div>
                                    <div class="space-y-2"><label data-slot="label"
                                            class="flex items-center gap-2 text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
                                            for="clinic-name">Clinic Name *</label>
                                        <input data-slot="input"
                                            class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-yellow-50 border-gray-300 focus:bg-yellow-50"
                                            id="clinic_name" placeholder="Enter your clinic name" value="">
                                    </div>
                                    <div class="space-y-2"><label data-slot="label"
                                            class="flex items-center gap-2 text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
                                            for="phone">Phone Number</label>
                                        <input type="tel" data-slot="input"
                                            class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-yellow-50 border-gray-300 focus:bg-yellow-50"
                                            id="phone" placeholder="(555) 123-4567" value="">
                                    </div>
                                    <div class="space-y-2">
                                        <label data-slot="label"
                                            class="flex items-center gap-2 text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
                                            for="inquiry-type">Inquiry Type *</label>
                                        <select id="inquiry_type">
                                            <option value="">Select inquiry type</option>
                                            <option value="supplies">Order Collection Supplies</option>
                                            <option value="specimen-requirements">Specimen Requirements</option>
                                            <option value="pickup-courier">Pickup or Courier Issue</option>
                                            <option value="results">Test Results Question</option>
                                            <option value="technical">Technical or Portal Support</option>
                                            <option value="training">Training Request</option>
                                            <option value="general">General Question</option>
                                        </select>
                                    </div>
                                    <?php if (!empty($cs_settings['supplies_enabled']) && !empty($cs_settings['supply_items'])) : ?>
                                        <div id="supply-order-section" class="rounded-lg border border-gray-200 overflow-hidden" style="display:none;">
                                            <div class="px-4 py-3 bg-gray-50 text-sm font-semibold text-[#3C3D59] flex items-center gap-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-package w-4 h-4 text-[#D83D00]">
                                                    <path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"></path>
                                                    <path d="M12 22V12"></path>
                                                    <polyline points="3.29 7 12 12 20.71 7"></polyline>
                                                    <path d="m7.5 4.27 9 5.15"></path>
                                                </svg>
                                                Order Collection Supplies
                                            </div>
                                            <div class="px-4 pb-4 pt-3 space-y-3">
                                                <p class="text-xs text-gray-500">Enter quantities for the supplies you need. Leave blank to skip.</p>
                                                <div class="space-y-2">
                                                    <div style="grid-template-columns: 1fr 120px; column-gap: 1rem;" class="grid">
                                                        <span class="text-xs font-medium text-gray-400 uppercase tracking-wide">Item</span>
                                                        <span class="text-xs font-medium text-gray-400 uppercase tracking-wide">Qty (max)</span>
                                                    </div>
                                                    <?php foreach ($cs_settings['supply_items'] as $i => $item) : ?>
                                                        <div style="grid-template-columns: 1fr 120px; column-gap: 1rem;" class="grid">
                                                            <label for="supply-<?php echo $i; ?>" class="text-sm text-gray-700 flex items-center"><?php echo esc_html($item['name']); ?></label>
                                                            <div class="relative">
                                                                <input type="number" data-slot="input" class="border-input flex h-9 w-full rounded-md border px-3 py-1 bg-input-background text-sm pr-10 cs-supply-qty"
                                                                    id="supply-<?php echo $i; ?>" data-name="<?php echo esc_attr($item['name']); ?>"
                                                                    min="0" max="<?php echo esc_attr($item['max_qty']); ?>" placeholder="0" value="">
                                                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400 pointer-events-none">/<?php echo esc_html($item['max_qty']); ?></span>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <div class="space-y-2">
                                        <label data-slot="label"
                                            class="flex items-center gap-2 text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
                                            for="priority">Priority *
                                        </label>

                                        <select id="priority_type">
                                            <option value="">Select priority level</option>
                                            <option value="low">Low - General question or feedback</option>
                                            <option value="normal">Normal - Standard inquiry</option>
                                            <option value="high">High - Urgent sample or results inquiry</option>
                                            <option value="urgent">Urgent - Critical patient care issue</option>
                                        </select>
                                    </div>
                                    <div class="space-y-2">
                                        <label data-slot="label"
                                            class="flex items-center gap-2 text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
                                            for="message">Message
                                        </label>

                                        <textarea data-slot="textarea"
                                            class="placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 flex field-sizing-content min-h-16 w-full rounded-md border px-3 py-2 text-base transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm bg-yellow-50 border-gray-300 focus:bg-yellow-50 resize-none"
                                            id="message" placeholder="Tell us how we can help you..." rows="6"></textarea>
                                    </div>
                                    <div class="flex">
                                        <button id="btn-send" data-slot="button"
                                            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive h-9 py-2 has-[&gt;svg]:px-3 bg-primary hover:bg-primary/90 text-white px-6"
                                            type="submit">Send Message</button>
                                        <div style="padding:5px">
                                            <svg id="loading_form_clinic" style="display:none" xmlns="http://www.w3.org/2000/svg" width="64"
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
                            <p><strong style="color:unset">Note:</strong> This form is for general clinic inquiries, sample questions, and support only. Do not submit patient-specific information or protected health information (PHI) through this form. All patient data is protected under HIPAA regulations.</p>
                        </div>
                    </div>

                    <div class="col-span-1 space-y-4">
                        <div data-slot="card" class="text-card-foreground flex flex-col gap-6 rounded-xl shadow-card bg-gradient-to-br from-red-50 to-orange-50 border-2 border-[#D83D00]">
                            <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                                <h4 data-slot="card-title" class="text-black" style="margin-bottom: 0; margin-top: 0;"><strong class="text-lg font-bold">Need Immediate Help?</strong></h4>
                            </div>
                            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-6">
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2 text-gray-700"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-phone h-4 w-4 text-primary">
                                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                        </svg><span class="text-sm font-semibold">Phone Support</span></div><a href="tel:<?php echo $cs_settings['phone']; ?>" class="block text-2xl font-bold hover:text-[#b83400] transition-colors"><span class="text-primary"><?php echo $cs_settings['phone']; ?></span></a>
                                </div>
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2 text-gray-700"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-mail h-4 w-4 text-primary">
                                            <rect width="20" height="16" x="2" y="4" rx="2"></rect>
                                            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                                        </svg><span class="text-sm font-semibold">Email Support</span></div><a href="mailto:<?php echo $cs_settings['email']; ?>" class="block text-lg font-semibold text-primary hover:text-[#b83400] transition-colors break-words"><span class="text-primary"><?php echo $cs_settings['email']; ?></span></a>
                                </div>
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2 text-gray-700"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clock h-4 w-4 text-primary">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <polyline points="12 6 12 12 16 14"></polyline>
                                        </svg><span class="text-sm font-semibold">Support Hours</span></div>
                                    <div class="text-sm text-gray-700 space-y-1">
                                        <?php
                                        $hours = esc_html($cs_settings['hours']);
                                        $hours = preg_replace('/^(.*?:)/m', '<strong>$1</strong>', $hours);
                                        echo nl2br($hours);
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                            <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                                <h4 data-slot="card-title" class="leading-none">Support Information</h4>
                            </div>
                            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-3 text-sm text-gray-600">
                                <p><strong>Response time:</strong> <?php echo $cs_settings['response']; ?></p>
                                <p><strong>What to include:</strong> <?php echo $cs_settings['include']; ?></p>
                                <p><strong>Collection supplies:</strong> <?php echo $cs_settings['supplies']; ?></p>
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
            $("#loading_form_clinic").show();
            var name = $("#name").val();
            var email = $("#email").val();
            var clinic_name = $("#clinic_name").val();
            var phone = $("#phone").val();
            var inquiry_type = $("#inquiry_type").val();
            var priority_type = $("#priority_type").val();
            var message = $("#message").val();
            var supplyItems = [];
            $('.cs-supply-qty').each(function() {
                var qty = $(this).val();
                if (qty && parseInt(qty) > 0) {
                    supplyItems.push({
                        name: $(this).data('name'),
                        qty: parseInt(qty)
                    });
                }
            });
            jQuery.post("index.php?page=clinic_support", {
                trajax: 'send_clinic_support',
                name: name,
                email: email,
                clinic_name: clinic_name,
                phone: phone,
                comment: message,
                inquiry_type: inquiry_type,
                priority_type: priority_type,
                supply_items: JSON.stringify(supplyItems)
            }, function(response) {
                $("#loading_form_clinic").hide();
                var res = JSON.parse(response);
                if (res.status == "ok") {
                    showToast('success', 'Clinic Support', res.message, 4000, 'top_center');
                    $("#clinic_name").val('');
                    $("#phone").val('');
                    $("#message").val('');
                } else {
                    showToast('error', 'Clinic Support', res.message, 4000, 'top_center');
                }
            });
            return false;
        });
        $("#inquiry_type").on('change', function() {
            if ($(this).val() === 'supplies') {
                $("#supply-order-section").slideDown(150);
            } else {
                $("#supply-order-section").slideUp(150);
            }
        });
        $('.cs-supply-qty').on('input blur', function() {
            var $input = $(this);
            var max = parseInt($input.attr('max'), 10);
            var min = parseInt($input.attr('min'), 10) || 0;
            var val = parseInt($input.val(), 10);

            if (isNaN(val)) return;

            if (!isNaN(max) && val > max) {
                $input.val(max);
            } else if (val < min) {
                $input.val(min);
            }
        });

        initCustomSelect("select");
    });
</script>