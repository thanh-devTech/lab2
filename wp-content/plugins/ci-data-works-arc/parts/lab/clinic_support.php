<?php
$user           = wp_get_current_user();
$current_lab    = CI_Base_Lab::get_current_lab();
$default_phone  = $current_lab['lab_phone'] ?? '1-800-555-0123';
$default_email  = $current_lab['lab_email'] ?? $user->user_email;

$cs_settings = get_option('ci_clinic_support', [
    'enabled'  => 0,
    'phone'    => $default_phone,
    'email'    => $default_email,
    'hours'    => "Mon-Fri: 7:00 AM - 7:00 PM ET\nSat-Sun: 9:00 AM - 5:00 PM ET",
    'response' => 'We typically respond within 24 business hours. Urgent sample or result-related questions are prioritized.',
    'include'  => 'For specimen-related or results questions, please include the accession number. Do not submit other protected health information through this form',
    'supplies' => "When enabled, collection supplies can be requested directly through this form by selecting Order Collection Supplies and entering the needed quantities.",
]);
if (!get_option('ci_clinic_support_text_migrated_v1')) {
    $cs_settings['response'] = 'We typically respond within 24 business hours. Urgent sample or result-related questions are prioritized.';
    $cs_settings['include']  = 'For specimen-related or results questions, please include the accession number. Do not submit other protected health information through this form.';
    $cs_settings['supplies'] = 'When enabled, collection supplies can be requested directly through this form by selecting Order Collection Supplies and entering the needed quantities.';

    update_option('ci_clinic_support', $cs_settings);
    update_option('ci_clinic_support_text_migrated_v1', 1);
}

if (!isset($cs_settings['supplies_enabled'])) {
    $cs_settings['supplies_enabled'] = 0;
}
if (!isset($cs_settings['supply_items'])) {
    $cs_settings['supply_items'] = [
        [
            'name' => 'Urine Cups',
            'max_qty' => 50,
        ],
        [
            'name' => 'Urine Vials',
            'max_qty' => 100,
        ],
        [
            'name' => 'Resp Swab',
            'max_qty' => 50,
        ],
        [
            'name' => 'Wound Swab',
            'max_qty' => 50,
        ],
        [
            'name' => 'Specimen/biohazard bags',
            'max_qty' => 100,
        ],
        [
            'name' => 'Yellow mailer bags',
            'max_qty' => 50,
        ],
    ];
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
    input[type=number],
    input[type=text],
    select,
    textarea,
    .selected {
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

    @media (width>=48rem) {
        .md\:text-sm {
            font-size: var(--text-sm) !important;
            line-height: var(--tw-leading, var(--text-sm--line-height))
        }
    }
</style>
<div class="space-y-6">
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 py-4">
            <label class="flex items-center gap-3 cursor-pointer">
                <button type="button" role="checkbox" aria-checked="<?php echo $cs_settings['enabled'] ? 'true' : 'false'; ?>" data-state="<?php echo $cs_settings['enabled'] ? 'checked' : 'unchecked'; ?>" value="on" data-slot="checkbox" class="peer bg-input-background dark:bg-input/30 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground dark:data-[state=checked]:bg-primary data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 w-5 h-5" id="cs-enabled">
                    <span data-state="<?php echo $cs_settings['enabled'] ? 'checked' : 'unchecked'; ?>" data-slot="checkbox-indicator" class="text-current transition-none" style="pointer-events: none; display: flex;<?php echo $cs_settings['enabled'] ? '' : ' display:none;'; ?>">
                        <svg style="margin-left: 2px;" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check size-3.5">
                            <path d="M20 6 9 17l-5-5"></path>
                        </svg>
                    </span>
                </button>
                <div>
                    <p class="text-sm font-semibold text-[#3C3D59]" style="margin-bottom: 2px;">Enable Clinic Support</p>
                    <p class="text-xs text-gray-500" style="margin-bottom: 0;">When enabled, the Clinic Support tab will appear in the Dashboard menu.</p>
                </div>
            </label>
        </div>
    </div>
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
        <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
            <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-phone w-5 h-5 text-[#D83D00]">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                </svg>Contact Details</h4>
            <p class="text-sm text-gray-500 mt-0.5">Displayed prominently on the Clinic Support page for clinics to reach your team.</p>
        </div>
        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div class="space-y-1"><label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="cs-phone">Phone Number</label>
                    <div class="relative">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-phone absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                        </svg>
                        <input data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive pl-9" id="cs-phone" placeholder="1-800-555-0123" value="<?php echo esc_attr($cs_settings['phone']); ?>">
                    </div>
                </div>
                <div class="space-y-1"><label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="cs-email">Email Address</label>
                    <div class="relative">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-mail absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400">
                            <rect width="20" height="16" x="2" y="4" rx="2"></rect>
                            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                        </svg>
                        <input type="email" data-slot="input" style="padding-left: 32px;" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive pl-9" id="cs-email" placeholder="clinicsupport@ignitelis.com" value="<?php echo esc_attr($cs_settings['email']); ?>">
                    </div>
                </div>
                <div class="space-y-1"><label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="cs-hours">Support Hours</label>
                    <div class="relative">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clock absolute left-3 top-3 w-4 h-4 text-gray-400">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        <textarea data-slot="textarea" style="padding: 8px;padding-left: 32px;" class="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 flex field-sizing-content min-h-16 w-full rounded-md border bg-input-background px-3 py-2 transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm pl-9 resize-none text-sm" id="cs-hours" placeholder="Mon-Fri: 7:00 AM – 7:00 PM ET" rows="2"><?php echo esc_textarea($cs_settings['hours']); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
        <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
            <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-mail w-5 h-5 text-[#D83D00]">
                    <rect width="20" height="16" x="2" y="4" rx="2"></rect>
                    <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                </svg>Support Information Text</h4>
            <p class="text-sm text-gray-500 mt-0.5">These three blocks appear in the Support Information section of the Clinic Support page.</p>
        </div>
        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-5">
            <div class="space-y-1">
                <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="cs-response">Response Time</label>
                <textarea data-slot="textarea" style="padding: 8px" class="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 flex field-sizing-content min-h-16 w-full rounded-md border bg-input-background px-3 py-2 transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm resize-none text-sm" id="cs-response" placeholder="Describe your typical response time…" rows="3"><?php echo esc_textarea($cs_settings['response']); ?></textarea>
            </div>
            <div class="space-y-1">
                <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="cs-include">What to Include</label>
                <textarea data-slot="textarea" style="padding: 8px" class="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 flex field-sizing-content min-h-16 w-full rounded-md border bg-input-background px-3 py-2 transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm resize-none text-sm" id="cs-include" placeholder="What information should clinics include in their request…" rows="3"><?php echo esc_textarea($cs_settings['include']); ?></textarea>
            </div>
            <div class="space-y-1">
                <label data-slot="label" class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600" for="cs-supplies">Collection Supplies</label>
                <textarea data-slot="textarea" style="padding: 8px" class="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 flex field-sizing-content min-h-16 w-full rounded-md border bg-input-background px-3 py-2 transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm resize-none text-sm" id="cs-supplies" placeholder="Information about ordering or requesting collection supplies…" rows="3"><?php echo esc_textarea($cs_settings['supplies']); ?></textarea>
            </div>
        </div>
    </div>
    <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
        <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 [.border-b]:pb-6">
            <div class="flex items-center justify-between">
                <h4 data-slot="card-title" class="leading-none flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-package w-5 h-5 text-[#D83D00]">
                        <path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"></path>
                        <path d="M12 22V12"></path>
                        <polyline points="3.29 7 12 12 20.71 7"></polyline>
                        <path d="m7.5 4.27 9 5.15"></path>
                    </svg>Collection Supplies
                </h4>
                <label class="flex items-center gap-2 cursor-pointer">
                    <button type="button" role="checkbox"
                        aria-checked="<?php echo $cs_settings['supplies_enabled'] ? 'true' : 'false'; ?>"
                        data-state="<?php echo $cs_settings['supplies_enabled'] ? 'checked' : 'unchecked'; ?>"
                        value="on" data-slot="checkbox"
                        class="peer bg-input-background dark:bg-input/30 data-[state=checked]:bg-primary data-[state=checked]:border-primary size-4 shrink-0 rounded-[4px] border shadow-xs outline-none w-4 h-4"
                        id="cs-supplies-enabled">
                        <span data-slot="checkbox-indicator" style="pointer-events:none;<?php echo $cs_settings['supplies_enabled'] ? '' : ' display:none;'; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check size-3.5">
                                <path d="M20 6 9 17l-5-5"></path>
                            </svg>
                        </span>
                    </button>
                    <span class="text-sm font-medium text-gray-700">Enable supply ordering</span>
                </label>
            </div>
            <p class="text-sm text-gray-500 mt-0.5">Enable this if your lab ships specimen collection supplies to clinics. It allows clinics to select supply types and quantities directly from the Clinic Support page. Define each supply item and the maximum quantity a clinic can request per order.</p>
        </div>
        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-3">
            <div style="grid-template-columns: 1fr 140px 40px;" class="grid gap-2 mb-1">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Supply Name</span>
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Max Qty / Order</span>
                <span></span>
            </div>
            <div id="cs-supply-rows" class="space-y-3">
                <?php if (!empty($cs_settings['supply_items'])) : foreach ($cs_settings['supply_items'] as $item) : ?>
                        <div style="grid-template-columns: 1fr 140px 40px;" class="grid gap-2 items-center cs-supply-row">
                            <input type="text" data-slot="input" class="border-input flex h-9 w-full rounded-md border px-3 py-1 bg-input-background text-sm cs-supply-name" placeholder="e.g. Urine Cups" value="<?php echo esc_attr($item['name']); ?>">
                            <input type="number" data-slot="input" class="border-input flex h-9 w-full rounded-md border px-3 py-1 bg-input-background text-sm cs-supply-max" min="1" value="<?php echo esc_attr($item['max_qty']); ?>">
                            <button type="button" data-slot="button" class="cs-remove-supply-row inline-flex items-center justify-center h-9 w-9 p-0 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-md">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash-2 w-4 h-4">
                                    <path d="M3 6h18"></path>
                                    <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                                    <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                                    <line x1="10" x2="10" y1="11" y2="17"></line>
                                    <line x1="14" x2="14" y1="11" y2="17"></line>
                                </svg>
                            </button>
                        </div>
                <?php endforeach;
                endif; ?>
            </div>
            <button type="button" id="btn-add-supply" data-slot="button" class="inline-flex items-center justify-center text-sm font-medium border bg-background h-8 rounded-md gap-1.5 px-3 mt-1 text-[#3C3D59] border-[#3C3D59] hover:bg-[#3C3D59] hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus w-4 h-4 mr-1.5">
                    <path d="M5 12h14"></path>
                    <path d="M12 5v14"></path>
                </svg>Add Supply
            </button>
        </div>
    </div>
    <div>
        <button type="button" id="btn-save-clinic-support" data-slot="button" class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive h-9 px-4 py-2 has-[&gt;svg]:px-3 bg-[#D83D00] hover:bg-[#c03600] text-white">Save Clinic Support Settings</button>
        <span id="cs-save-success" style="display:none; margin-left:10px; color:#16a34a; font-size:13px;">Saved successfully!</span>
        <span id="cs-save-error" style="display:none; margin-left:10px; color:#dc2626; font-size:13px;"></span>
    </div>
</div>
<script>
    jQuery(function($) {
        var $csEnabled = $("#cs-enabled");

        $csEnabled.click(function() {
            var $btn = $(this);
            var state = $btn.attr("data-state");
            if (state === "unchecked") {
                $btn.attr("data-state", "checked").attr("aria-checked", "true");
                $btn.find("[data-slot='checkbox-indicator']").show();
            } else {
                $btn.attr("data-state", "unchecked").attr("aria-checked", "false");
                $btn.find("[data-slot='checkbox-indicator']").hide();
            }
        });

        var $csSuppliesEnabled = $("#cs-supplies-enabled");
        $csSuppliesEnabled.click(function() {
            var $btn = $(this);
            var state = $btn.attr("data-state");
            if (state === "unchecked") {
                $btn.attr("data-state", "checked").attr("aria-checked", "true");
                $btn.find("[data-slot='checkbox-indicator']").show();
            } else {
                $btn.attr("data-state", "unchecked").attr("aria-checked", "false");
                $btn.find("[data-slot='checkbox-indicator']").hide();
            }
        });

        $("#btn-save-clinic-support").click(function() {
            var $btn = $(this);
            var $success = $("#cs-save-success");
            var $error = $("#cs-save-error");
            var originalText = $btn.text();

            $success.hide();
            $error.hide();
            $btn.prop('disabled', true).text('Saving...');

            var enabled = $csEnabled.attr('data-state') === 'checked' ? 1 : 0;
            var supplyItems = [];
            $('.cs-supply-row').each(function() {
                var name = $(this).find('.cs-supply-name').val();
                var max = $(this).find('.cs-supply-max').val();
                if (name) {
                    supplyItems.push({
                        name: name,
                        max_qty: parseInt(max) || 1
                    });
                }
            });

            $.ajax({
                type: 'POST',
                data: {
                    trajax: 'save_clinic_support_settings',
                    enabled: enabled,
                    phone: $("#cs-phone").val(),
                    email: $("#cs-email").val(),
                    hours: $("#cs-hours").val(),
                    response: $("#cs-response").val(),
                    include: $("#cs-include").val(),
                    supplies: $("#cs-supplies").val(),
                    supplies_enabled: $csSuppliesEnabled.attr('data-state') === 'checked' ? 1 : 0,
                    supply_items: JSON.stringify(supplyItems)

                },
                success: function(res) {
                    if (res.success) {
                        $success.text('Saved successfully!').show();
                    } else {
                        $error.text('Error: ' + (res.message || 'Failed to save')).show();
                    }
                },
                error: function(xhr, status, err) {
                    $error.text('Error saving: ' + err).show();
                },
                complete: function() {
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        });

        function csSupplyRowTemplate() {
            return '<div style="grid-template-columns: 1fr 140px 40px;" class="grid gap-2 items-center cs-supply-row">' +
                '<input type="text" data-slot="input" class="border-input flex h-9 w-full rounded-md border px-3 py-1 bg-input-background text-sm cs-supply-name" placeholder="e.g. Urine Cups" value="">' +
                '<input type="number" data-slot="input" class="border-input flex h-9 w-full rounded-md border px-3 py-1 bg-input-background text-sm cs-supply-max" min="1" value="50">' +
                '<button type="button" data-slot="button" class="cs-remove-supply-row inline-flex items-center justify-center h-9 w-9 p-0 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-md">' +
                '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash-2 w-4 h-4"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path><line x1="10" x2="10" y1="11" y2="17"></line><line x1="14" x2="14" y1="11" y2="17"></line></svg>' +
                '</button></div>';
        }

        $("#btn-add-supply").click(function() {
            $("#cs-supply-rows").append(csSupplyRowTemplate());
        });

        $(document).on('click', '.cs-remove-supply-row', function() {
            $(this).closest('.cs-supply-row').remove();
        });
    });
</script>