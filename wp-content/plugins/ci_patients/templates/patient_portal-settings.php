<?php
$pa_options = get_option('trpatient_settings', array());
$logo_id = isset($pa_options['logo_login']) ? absint($pa_options['logo_login']) : 0;
$logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
$dob_verification = !empty($pa_options['enable_acheck_dob']) ? 'every-login' : 'first-login-only';
$name_verification = !empty($pa_options['disable_vfn']) ? 'last-only' : 'first-and-last';
?>
<style>
    #support-phone,#support-email,#support-hours{
        padding-left: 30px;
    }
</style>
<main class="bg-[#f8f9fa] overflow-auto flex-1">
    <form class="tr-portal-settings-form">
        <div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
            <div class="space-y-6 pb-16">
                <div>
                    <h1 class="text-2xl font-semibold text-[#3C3D59]">Portal Settings</h1>
                </div>
                <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header"
                        class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                        <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-image w-5 h-5 text-[#D83D00]">
                                <rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect>
                                <circle cx="9" cy="9" r="2"></circle>
                                <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path>
                            </svg>Portal Logo</h4>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                        <div class="space-y-3 max-w-sm">
                            <div class="space-y-1.5"><label data-slot="label"
                                    class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600"
                                    for="portal-logo">Logo</label>
                                <div class="flex gap-2"><input type="hidden" id="portal-logo-id" value="<?php echo esc_attr($logo_id); ?>"><input type="file" data-slot="input"
                                        class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive flex-1"
                                        id="portal-logo" accept="image/*" disabled><button type="button" id="portal-logo-upload" data-slot="button" disabled
                                        class="inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5"><svg
                                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" class="lucide lucide-upload h-4 w-4">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                            <polyline points="17 8 12 3 7 8"></polyline>
                                            <line x1="12" x2="12" y1="3" y2="15"></line>
                                        </svg></button></div>
                            </div>
                            <div id="portal-logo-preview"
                                class="flex justify-center items-center border-2 border-dashed rounded-lg p-8 bg-gray-50 text-gray-400 text-sm">
                                <?php if (!empty($logo_url)) { ?><img src="<?php echo esc_url($logo_url); ?>" alt="Portal Logo" style="max-width:100%;max-height:100px;"><?php } else { ?>No logo uploaded<?php } ?></div>
                        </div>
                    </div>
                </div>
                <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header"
                        class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                        <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-shield w-5 h-5 text-[#D83D00]">
                                <path
                                    d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z">
                                </path>
                            </svg>Identity Verification</h4>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-6">
                        <div class="flex items-start gap-3 bg-[#fff5f2] border border-[#f5c6b8] rounded-lg px-4 py-3"><svg
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-circle-alert w-5 h-5 text-[#D83D00] flex-shrink-0 mt-0.5">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" x2="12" y1="8" y2="12"></line>
                                <line x1="12" x2="12.01" y1="16" y2="16"></line>
                            </svg>
                            <p class="text-sm font-semibold text-[#D83D00]">Phone number and Date of Birth are always required
                                for patient login.</p>
                        </div>
                        <p class="text-sm text-gray-600">For additional login security, the following options can also be
                            enabled:</p>
                        <div>
                            <h4 class="text-sm font-semibold text-[#3C3D59] mb-3">Date of Birth Verification</h4>
                            <div class="space-y-2.5"><label
                                    class="flex items-start gap-3 p-4 rounded-lg border border-gray-200 cursor-pointer hover:border-[#D83D00] transition-colors"><input
                                        type="radio"
                                        class="mt-0.5 w-4 h-4 text-[#D83D00] border-gray-300 focus:ring-[#D83D00]"
                                        value="every-login" <?php checked($dob_verification, 'every-login'); ?> disabled>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">Verify Date of Birth at Every Login</p>
                                        <p class="text-sm text-gray-500 mt-0.5">Patient must confirm their date of birth each
                                            time they log in</p>
                                    </div>
                                </label><label
                                    class="flex items-start gap-3 p-4 rounded-lg border border-gray-200 cursor-pointer hover:border-[#D83D00] transition-colors"><input
                                        type="radio"
                                        class="mt-0.5 w-4 h-4 text-[#D83D00] border-gray-300 focus:ring-[#D83D00]"
                                        value="first-login-only" <?php checked($dob_verification, 'first-login-only'); ?> disabled>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">Verify Date of Birth on First Login Only
                                        </p>
                                        <p class="text-sm text-gray-500 mt-0.5">Date of birth is checked once when the patient
                                            first logs in</p>
                                    </div>
                                </label></div>
                        </div>
                        <div class="pt-2 border-t border-gray-100">
                            <h4 class="text-sm font-semibold text-[#3C3D59] mb-3 mt-4">Name Verification</h4>
                            <div class="space-y-2.5"><label
                                    class="flex items-start gap-3 p-4 rounded-lg border border-gray-200 cursor-pointer hover:border-[#D83D00] transition-colors"><input
                                        type="radio"
                                        class="mt-0.5 w-4 h-4 text-[#D83D00] border-gray-300 focus:ring-[#D83D00]"
                                        value="last-only" <?php checked($name_verification, 'last-only'); ?> disabled>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">Verify Patient's Last Name Only</p>
                                        <p class="text-sm text-gray-500 mt-0.5">Patient must enter their last name to verify
                                            identity</p>
                                    </div>
                                </label><label
                                    class="flex items-start gap-3 p-4 rounded-lg border border-gray-200 cursor-pointer hover:border-[#D83D00] transition-colors"><input
                                        type="radio"
                                        class="mt-0.5 w-4 h-4 text-[#D83D00] border-gray-300 focus:ring-[#D83D00]"
                                        value="first-and-last" <?php checked($name_verification, 'first-and-last'); ?> disabled>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">Verify Patient's First and Last Name</p>
                                        <p class="text-sm text-gray-500 mt-0.5">Patient must enter both first and last name to
                                            verify identity</p>
                                    </div>
                                </label></div>
                        </div><button type="button" data-slot="button" disabled
                            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive h-9 px-4 py-2 has-[&gt;svg]:px-3 bg-[#D83D00] hover:bg-[#c03600] text-white">Save
                            Verification Settings</button>
                    </div>
                </div>
                <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header"
                        class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                        <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-phone w-5 h-5 text-[#D83D00]">
                                <path
                                    d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z">
                                </path>
                            </svg>Support Settings</h4>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-4">
                        <p class="text-sm text-gray-600">This information will be available on the patient portal Help &amp;
                            Support page.</p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <div class="space-y-1.5"><label data-slot="label"
                                    class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600"
                                    for="support-phone">Phone</label>
                                <div class="relative"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round"
                                        class="lucide lucide-phone absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400">
                                        <path
                                            d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z">
                                        </path>
                                    </svg><input type="tel" data-slot="input"
                                        class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive pl-9"
                                        id="support-phone" placeholder="(555) 123-4567" value="<?php echo esc_attr($pa_options['help_support_phone'] ?? ''); ?>" readonly></div>
                            </div>
                            <div class="space-y-1.5"><label data-slot="label"
                                    class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600"
                                    for="support-email">Email</label>
                                <div class="relative"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round"
                                        class="lucide lucide-mail absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400">
                                        <rect width="20" height="16" x="2" y="4" rx="2"></rect>
                                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                                    </svg><input type="email" data-slot="input"
                                        class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive pl-9"
                                        id="support-email" placeholder="support@ignitelis.com" value="<?php echo esc_attr($pa_options['help_support_email'] ?? ''); ?>" readonly>
                                </div>
                            </div>
                            <div class="space-y-1.5"><label data-slot="label"
                                    class="flex items-center gap-2 font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-xs text-gray-600"
                                    for="support-hours">Hours Of Operation</label>
                                <div class="relative"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round"
                                        class="lucide lucide-clock absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <polyline points="12 6 12 12 16 14"></polyline>
                                    </svg><input type="text" data-slot="input"
                                        class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive pl-9"
                                        id="support-hours" placeholder="Mon-Fri, 8 AM - 6 PM EST"
                                        value="<?php echo esc_attr($pa_options['help_support_time'] ?? ''); ?>" readonly></div>
                            </div>
                        </div><button type="button" data-slot="button" disabled
                            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive h-9 px-4 py-2 has-[&gt;svg]:px-3 bg-[#D83D00] hover:bg-[#c03600] text-white">Save
                            Support Settings</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</main>
