<?php
if (!function_exists('trcppr_is_new_style_enabled') || !trcppr_is_new_style_enabled()) {
	wp_die(__('This page is only available when New Style is enabled.'));
}

$pa_options = get_option('trpatient_settings', array());
$portal_settings_saved = false;

if (isset($_POST['pa_options'])) {
	$options = tr_pdd_stripslashes_deep($_POST['pa_options']);

	$pa_options['logo_login'] = isset($options['logo_login']) ? absint($options['logo_login']) : 0;
	$pa_options['help_support_phone'] = isset($options['help_support_phone']) ? sanitize_text_field($options['help_support_phone']) : '';
	$pa_options['help_support_email'] = isset($options['help_support_email']) ? sanitize_email($options['help_support_email']) : '';
	$pa_options['help_support_time'] = isset($options['help_support_time']) ? sanitize_text_field($options['help_support_time']) : '';

	$dob_verification = isset($options['dob_verification']) ? sanitize_key($options['dob_verification']) : 'every-login';
	$pa_options['enable_acheck_dob'] = $dob_verification == 'every-login' ? 1 : 0;
	$pa_options['enable_check_dob'] = $dob_verification == 'first-login-only' ? 1 : 0;

	$name_verification = isset($options['name_verification']) ? sanitize_key($options['name_verification']) : 'last-only';
	$pa_options['enable_acheck_name'] = 1;
	$pa_options['disable_vfn'] = $name_verification == 'last-only' ? 1 : 0;

	update_option('trpatient_settings', $pa_options);
	$portal_settings_saved = true;
}

$logo_id = isset($pa_options['logo_login']) ? absint($pa_options['logo_login']) : 0;
$logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
$dob_verification = !empty($pa_options['enable_acheck_dob']) ? 'every-login' : 'first-login-only';
$name_verification = !empty($pa_options['disable_vfn']) ? 'last-only' : 'first-and-last';
?>
<style>
    .tr-portal-settings-form {
        --background: #f7f8fa;
        --foreground: #1a1a1a;
        --card: #fff;
        --card-foreground: #1a1a1a;
        --primary: #d83d00;
        --primary-foreground: #fff;
        --muted-foreground: #6b7280;
        --accent: #fef3f0;
        --accent-foreground: #d83d00;
        --destructive: #dc2626;
        --border: #0000001f;
        --input-background: #f8f9fb;
        --input-border: #00000026;
        --ring: #d83d00;
        --radius: .75rem;
        --spacing: .25rem;
        --text-xs: .75rem;
        --text-xs--line-height: calc(1 / .75);
        --text-sm: .875rem;
        --text-sm--line-height: calc(1.25 / .875);
        --text-base: 1rem;
        --text-2xl: 1.5rem;
        --text-2xl--line-height: calc(2 / 1.5);
        --font-weight-medium: 500;
        --font-weight-semibold: 600;
        --shadow: 0 1px 2px 0 #0000000a;
        box-sizing: border-box;
        color: var(--foreground);
        font-size: 14px;
    }

    .tr-portal-settings-form *,
    .tr-portal-settings-form *::before,
    .tr-portal-settings-form *::after {
        box-sizing: border-box;
        border-color: var(--border);
        outline-color: var(--ring);
    }

    .tr-portal-settings-form h1,
    .tr-portal-settings-form h2,
    .tr-portal-settings-form h3,
    .tr-portal-settings-form h4,
    .tr-portal-settings-form h5,
    .tr-portal-settings-form h6,
    .tr-portal-settings-form p {
        margin: 0;
    }

    .tr-portal-settings-form button,
    .tr-portal-settings-form input {
        font: inherit;
        letter-spacing: inherit;
    }

    .tr-portal-settings-form svg {
        display: block;
        vertical-align: middle;
    }

    .tr-portal-settings-form .relative { position: relative; }
    .tr-portal-settings-form .absolute { position: absolute; }
    .tr-portal-settings-form .\@container\/card-header { container: card-header / inline-size; }
    .tr-portal-settings-form .left-3 { left: calc(var(--spacing) * 3); }
    .tr-portal-settings-form .top-1\/2 { top: 50%; }
    .tr-portal-settings-form .-translate-y-1\/2 { transform: translateY(-50%); }
    .tr-portal-settings-form .flex { display: flex; }
    .tr-portal-settings-form .inline-flex { display: inline-flex; }
    .tr-portal-settings-form .grid { display: grid; }
    .tr-portal-settings-form .grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
    .tr-portal-settings-form .grid-rows-\[auto_auto\] { grid-template-rows: auto auto; }
    .tr-portal-settings-form .auto-rows-min { grid-auto-rows: min-content; }
    .tr-portal-settings-form .flex-1 { flex: 1 1 0%; }
    .tr-portal-settings-form .flex-col { flex-direction: column; }
    .tr-portal-settings-form .flex-shrink-0,
    .tr-portal-settings-form .shrink-0 { flex-shrink: 0; }
    .tr-portal-settings-form .items-center { align-items: center; }
    .tr-portal-settings-form .items-start { align-items: flex-start; }
    .tr-portal-settings-form .justify-center { justify-content: center; }
    .tr-portal-settings-form .gap-1\.5 { gap: calc(var(--spacing) * 1.5); }
    .tr-portal-settings-form .gap-2 { gap: calc(var(--spacing) * 2); }
    .tr-portal-settings-form .gap-3 { gap: calc(var(--spacing) * 3); }
    .tr-portal-settings-form .gap-5 { gap: calc(var(--spacing) * 5); }
    .tr-portal-settings-form .gap-6 { gap: calc(var(--spacing) * 6); }
    .tr-portal-settings-form .space-y-1\.5 > :not(:last-child) { margin-bottom: calc(var(--spacing) * 1.5); }
    .tr-portal-settings-form .space-y-2\.5 > :not(:last-child) { margin-bottom: calc(var(--spacing) * 2.5); }
    .tr-portal-settings-form .space-y-3 > :not(:last-child) { margin-bottom: calc(var(--spacing) * 3); }
    .tr-portal-settings-form .space-y-4 > :not(:last-child) { margin-bottom: calc(var(--spacing) * 4); }
    .tr-portal-settings-form .space-y-6 > :not(:last-child) { margin-bottom: calc(var(--spacing) * 6); }
    .tr-portal-settings-form .w-4 { width: calc(var(--spacing) * 4); }
    .tr-portal-settings-form .w-5 { width: calc(var(--spacing) * 5); }
    .tr-portal-settings-form .w-full { width: 100%; }
    .tr-portal-settings-form .h-4 { height: calc(var(--spacing) * 4); }
    .tr-portal-settings-form .h-5 { height: calc(var(--spacing) * 5); }
    .tr-portal-settings-form .h-8 { height: calc(var(--spacing) * 8); }
    .tr-portal-settings-form .h-9 { height: calc(var(--spacing) * 9); }
    .tr-portal-settings-form .min-w-0 { min-width: 0; }
    .tr-portal-settings-form .max-w-sm { max-width: 24rem; }
    .tr-portal-settings-form .overflow-auto { overflow: auto; }
    .tr-portal-settings-form .whitespace-nowrap { white-space: nowrap; }
    .tr-portal-settings-form .cursor-pointer { cursor: pointer; }
    .tr-portal-settings-form .select-none { user-select: none; }
    .tr-portal-settings-form .outline-none { outline: 2px solid transparent; outline-offset: 2px; }
    .tr-portal-settings-form .p-4 { padding: calc(var(--spacing) * 4); }
    .tr-portal-settings-form .p-8 { padding: calc(var(--spacing) * 8); }
    .tr-portal-settings-form .px-3 { padding-left: calc(var(--spacing) * 3); padding-right: calc(var(--spacing) * 3); }
    .tr-portal-settings-form .px-4 { padding-left: calc(var(--spacing) * 4); padding-right: calc(var(--spacing) * 4); }
    .tr-portal-settings-form .px-6 { padding-left: calc(var(--spacing) * 6); padding-right: calc(var(--spacing) * 6); }
    .tr-portal-settings-form .py-1 { padding-top: calc(var(--spacing) * 1); padding-bottom: calc(var(--spacing) * 1); }
    .tr-portal-settings-form .py-2 { padding-top: calc(var(--spacing) * 2); padding-bottom: calc(var(--spacing) * 2); }
    .tr-portal-settings-form .py-3 { padding-top: calc(var(--spacing) * 3); padding-bottom: calc(var(--spacing) * 3); }
    .tr-portal-settings-form .pt-2 { padding-top: calc(var(--spacing) * 2); }
    .tr-portal-settings-form .pt-6 { padding-top: calc(var(--spacing) * 6); }
    .tr-portal-settings-form .pb-16 { padding-bottom: calc(var(--spacing) * 16); }
    .tr-portal-settings-form .pl-9 { padding-left: calc(var(--spacing) * 9); }
    .tr-portal-settings-form .mt-0\.5 { margin-top: calc(var(--spacing) * .5); }
    .tr-portal-settings-form .mt-4 { margin-top: calc(var(--spacing) * 4); }
    .tr-portal-settings-form .mb-3 { margin-bottom: calc(var(--spacing) * 3); }
    .tr-portal-settings-form .rounded-md { border-radius: calc(var(--radius) - 2px); }
    .tr-portal-settings-form .rounded-lg { border-radius: var(--radius); }
    .tr-portal-settings-form .rounded-xl { border-radius: calc(var(--radius) + 4px); }
    .tr-portal-settings-form .border { border-width: 1px; border-style: solid; }
    .tr-portal-settings-form .border-2 { border-width: 2px; border-style: solid; }
    .tr-portal-settings-form .border-t { border-top-width: 1px; border-top-style: solid; }
    .tr-portal-settings-form .border-dashed { border-style: dashed; }
    .tr-portal-settings-form .border-input { border-color: var(--input-border); }
    .tr-portal-settings-form .border-gray-100 { border-color: #f3f4f6; }
    .tr-portal-settings-form .border-gray-200 { border-color: #e5e7eb; }
    .tr-portal-settings-form .border-gray-300 { border-color: #d1d5db; }
    .tr-portal-settings-form .border-\[\#f5c6b8\] { border-color: #f5c6b8; }
    .tr-portal-settings-form .bg-card { background-color: var(--card); }
    .tr-portal-settings-form .bg-background { background-color: var(--background); }
    .tr-portal-settings-form .bg-input-background { background-color: var(--input-background); }
    .tr-portal-settings-form .bg-gray-50 { background-color: #f9fafb; }
    .tr-portal-settings-form .bg-\[\#f8f9fa\] { background-color: #f8f9fa; }
    .tr-portal-settings-form .bg-\[\#fff5f2\] { background-color: #fff5f2; }
    .tr-portal-settings-form .bg-\[\#D83D00\] { background-color: #D83D00; }
    .tr-portal-settings-form .text-card-foreground { color: var(--card-foreground); }
    .tr-portal-settings-form .text-foreground { color: var(--foreground); }
    .tr-portal-settings-form .text-gray-400 { color: #9ca3af; }
    .tr-portal-settings-form .text-gray-500 { color: #6b7280; }
    .tr-portal-settings-form .text-gray-600 { color: #4b5563; }
    .tr-portal-settings-form .text-gray-900 { color: #111827; }
    .tr-portal-settings-form .text-white { color: #fff; }
    .tr-portal-settings-form .text-\[\#3C3D59\] { color: #3C3D59; }
    .tr-portal-settings-form .text-\[\#D83D00\] { color: #D83D00; }
    .tr-portal-settings-form .text-xs { font-size: var(--text-xs); line-height: var(--text-xs--line-height); }
    .tr-portal-settings-form .text-sm { font-size: var(--text-sm); line-height: var(--text-sm--line-height); }
    .tr-portal-settings-form .text-base { font-size: var(--text-base); line-height: 1.5; }
    .tr-portal-settings-form .text-2xl { font-size: var(--text-2xl); line-height: var(--text-2xl--line-height); }
    .tr-portal-settings-form .font-medium { font-weight: var(--font-weight-medium); }
    .tr-portal-settings-form .font-semibold { font-weight: var(--font-weight-semibold); }
    .tr-portal-settings-form .leading-none { line-height: 1; }
    .tr-portal-settings-form .shadow-card { box-shadow: var(--shadow); }
    .tr-portal-settings-form .transition-all,
    .tr-portal-settings-form .transition-colors,
    .tr-portal-settings-form .transition-\[color\,box-shadow\] { transition-duration: .15s; transition-timing-function: cubic-bezier(.4, 0, .2, 1); }
    .tr-portal-settings-form .transition-all { transition-property: all; }
    .tr-portal-settings-form .transition-colors { transition-property: color, background-color, border-color, text-decoration-color, fill, stroke; }
    .tr-portal-settings-form .transition-\[color\,box-shadow\] { transition-property: color, box-shadow; }
    .tr-portal-settings-form .\[\&\:last-child\]\:pb-6:last-child { padding-bottom: calc(var(--spacing) * 6); }
    .tr-portal-settings-form .\[\.border-b\]\:pb-6.border-b { padding-bottom: calc(var(--spacing) * 6); }
    .tr-portal-settings-form .\[\&_svg\]\:pointer-events-none svg { pointer-events: none; }
    .tr-portal-settings-form .\[\&_svg\]\:shrink-0 svg { flex-shrink: 0; }
    .tr-portal-settings-form .\[\&_svg\:not\(\[class\*\=\'size-\'\]\)\]\:size-4 svg:not([class*='size-']) { width: calc(var(--spacing) * 4); height: calc(var(--spacing) * 4); }
    .tr-portal-settings-form .has-\[\>svg\]\:px-2\.5:has(>svg) { padding-left: calc(var(--spacing) * 2.5); padding-right: calc(var(--spacing) * 2.5); }
    .tr-portal-settings-form .has-\[\>svg\]\:px-3:has(>svg) { padding-left: calc(var(--spacing) * 3); padding-right: calc(var(--spacing) * 3); }
    .tr-portal-settings-form .has-data-\[slot\=card-action\]\:grid-cols-\[1fr_auto\]:has([data-slot="card-action"]) { grid-template-columns: 1fr auto; }
    .tr-portal-settings-form input[type="text"],
    .tr-portal-settings-form input[type="email"],
    .tr-portal-settings-form input[type="tel"],
    .tr-portal-settings-form input[type="file"] {
        border-width: 1px;
        border-style: solid;
    }

    .tr-portal-settings-form input::placeholder { color: var(--muted-foreground); }
    .tr-portal-settings-form .placeholder\:text-muted-foreground::placeholder { color: var(--muted-foreground); }
    .tr-portal-settings-form .selection\:bg-primary::selection { background-color: var(--primary); }
    .tr-portal-settings-form .selection\:text-primary-foreground::selection { color: var(--primary-foreground); }
    .tr-portal-settings-form .file\:inline-flex::file-selector-button { display: inline-flex; }
    .tr-portal-settings-form .file\:h-7::file-selector-button { height: calc(var(--spacing) * 7); }
    .tr-portal-settings-form .file\:border-0::file-selector-button { border-width: 0; }
    .tr-portal-settings-form .file\:bg-transparent::file-selector-button { background-color: transparent; }
    .tr-portal-settings-form .file\:text-sm::file-selector-button { font-size: var(--text-sm); line-height: var(--text-sm--line-height); }
    .tr-portal-settings-form .file\:font-medium::file-selector-button { font-weight: var(--font-weight-medium); }
    .tr-portal-settings-form .file\:text-foreground::file-selector-button { color: var(--foreground); }
    .tr-portal-settings-form .disabled\:pointer-events-none:disabled,
    .tr-portal-settings-form .peer-disabled\:cursor-not-allowed:disabled,
    .tr-portal-settings-form .group-data-\[disabled\=true\]\:pointer-events-none[data-disabled="true"] { pointer-events: none; }
    .tr-portal-settings-form .disabled\:cursor-not-allowed:disabled,
    .tr-portal-settings-form .peer-disabled\:cursor-not-allowed:disabled { cursor: not-allowed; }
    .tr-portal-settings-form .disabled\:opacity-50:disabled,
    .tr-portal-settings-form .peer-disabled\:opacity-50:disabled,
    .tr-portal-settings-form .group-data-\[disabled\=true\]\:opacity-50[data-disabled="true"] { opacity: .5; }
    .tr-portal-settings-form .focus-visible\:border-ring:focus-visible { border-color: var(--ring); }
    .tr-portal-settings-form .focus-visible\:ring-ring\/50:focus-visible { box-shadow: 0 0 0 3px rgba(216, 61, 0, .25); }
    .tr-portal-settings-form .focus-visible\:ring-\[3px\]:focus-visible { outline-width: 3px; }
    .tr-portal-settings-form .focus\:ring-\[\#D83D00\]:focus { box-shadow: 0 0 0 3px rgba(216, 61, 0, .25); }
    .tr-portal-settings-form .aria-invalid\:border-destructive[aria-invalid="true"] { border-color: var(--destructive); }
    .tr-portal-settings-form .aria-invalid\:ring-destructive\/20[aria-invalid="true"] { box-shadow: 0 0 0 3px rgba(220, 38, 38, .2); }
    .tr-portal-settings-form .hover\:border-\[\#D83D00\]:hover { border-color: #D83D00; }
    .tr-portal-settings-form .hover\:bg-\[\#c03600\]:hover { background-color: #c03600; }
    .tr-portal-settings-form .hover\:bg-accent:hover { background-color: var(--accent); }
    .tr-portal-settings-form .hover\:text-accent-foreground:hover { color: var(--accent-foreground); }
    .dark .tr-portal-settings-form .dark\:bg-input\/30 { background-color: rgba(255, 255, 255, .12); }
    .dark .tr-portal-settings-form .dark\:border-input { border-color: rgba(255, 255, 255, .2); }
    .dark .tr-portal-settings-form .dark\:hover\:bg-input\/50:hover { background-color: rgba(255, 255, 255, .2); }
    .dark .tr-portal-settings-form .dark\:aria-invalid\:ring-destructive\/40[aria-invalid="true"] { box-shadow: 0 0 0 3px rgba(239, 68, 68, .4); }
    .pl-9 {
        padding-left: calc(var(--spacing) * 9);
    }
    .lucide-phone,.lucide-mail,.lucide-clock{
        top: 23px !important;
    }
    .border-gray-300 {
        border-color: var(--color-gray-300);
    }
    .rounded-lg {
        border-radius: var(--radius) !important;
    }

    input[type="text"],
    input[type="tel"],
    input[type="search"],
    input[type="email"],
    input[type="submit"],
    input[type="button"],
    select:not(.headerbarselect):not(.none_border) {
        border-radius: unset !important;
    }
    @media (min-width: 640px) {
        .tr-portal-settings-form .sm\:p-6 { padding: calc(var(--spacing) * 6); }
    }

    @media (min-width: 768px) {
        .tr-portal-settings-form .md\:grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .tr-portal-settings-form .md\:text-sm { font-size: var(--text-sm); line-height: var(--text-sm--line-height); }
    }

    @media (min-width: 1024px) {
        .tr-portal-settings-form .lg\:p-8 { padding: calc(var(--spacing) * 8); }
    }
</style>
<form method="post" class="tr-portal-settings-form">
    <div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
        <div class="space-y-6 pb-16">
            <div>
                <h1 class="text-2xl font-semibold text-[#3C3D59]">Portal Settings</h1>
            </div>
            <div data-slot="card" class="tr-support-settings-card bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
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
                            <div class="flex gap-2"><input type="hidden" id="portal-logo-id" name="pa_options[logo_login]" value="<?php echo esc_attr($logo_id); ?>"><input type="file" data-slot="input"
                                    class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive flex-1"
                                    id="portal-logo" accept="image/*"><button type="button" id="portal-logo-upload" data-slot="button"
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
                                    type="radio" name="pa_options[dob_verification]"
                                    class="mt-0.5 w-4 h-4 text-[#D83D00] border-gray-300 focus:ring-[#D83D00]"
                                    value="every-login" <?php checked($dob_verification, 'every-login'); ?>>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Verify Date of Birth at Every Login</p>
                                    <p class="text-sm text-gray-500 mt-0.5">Patient must confirm their date of birth each
                                        time they log in</p>
                                </div>
                            </label><label
                                class="flex items-start gap-3 p-4 rounded-lg border border-gray-200 cursor-pointer hover:border-[#D83D00] transition-colors"><input
                                    type="radio" name="pa_options[dob_verification]"
                                    class="mt-0.5 w-4 h-4 text-[#D83D00] border-gray-300 focus:ring-[#D83D00]"
                                    value="first-login-only" <?php checked($dob_verification, 'first-login-only'); ?>>
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
                                    type="radio" name="pa_options[name_verification]"
                                    class="mt-0.5 w-4 h-4 text-[#D83D00] border-gray-300 focus:ring-[#D83D00]"
                                    value="last-only" <?php checked($name_verification, 'last-only'); ?>>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Verify Patient's Last Name Only</p>
                                    <p class="text-sm text-gray-500 mt-0.5">Patient must enter their last name to verify
                                        identity</p>
                                </div>
                            </label><label
                                class="flex items-start gap-3 p-4 rounded-lg border border-gray-200 cursor-pointer hover:border-[#D83D00] transition-colors"><input
                                    type="radio" name="pa_options[name_verification]"
                                    class="mt-0.5 w-4 h-4 text-[#D83D00] border-gray-300 focus:ring-[#D83D00]"
                                    value="first-and-last" <?php checked($name_verification, 'first-and-last'); ?>>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Verify Patient's First and Last Name</p>
                                    <p class="text-sm text-gray-500 mt-0.5">Patient must enter both first and last name to
                                        verify identity</p>
                                </div>
                            </label></div>
                    </div><button type="submit" data-slot="button"
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
                                    id="support-phone" name="pa_options[help_support_phone]" placeholder="(555) 123-4567" value="<?php echo esc_attr($pa_options['help_support_phone'] ?? ''); ?>"></div>
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
                                    id="support-email" name="pa_options[help_support_email]" placeholder="support@ignitelis.com" value="<?php echo esc_attr($pa_options['help_support_email'] ?? ''); ?>">
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
                                    id="support-hours" name="pa_options[help_support_time]" placeholder="Mon-Fri, 8 AM - 6 PM EST"
                                    value="<?php echo esc_attr($pa_options['help_support_time'] ?? ''); ?>"></div>
                        </div>
                    </div><button type="submit" data-slot="button"
                        class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive h-9 px-4 py-2 has-[&gt;svg]:px-3 bg-[#D83D00] hover:bg-[#c03600] text-white">Save
                        Support Settings</button>
                </div>
            </div>
        </div>
    </div>
</form>
<script>
	jQuery(function($) {
		<?php if ($portal_settings_saved) : ?>
		if (typeof Swal !== 'undefined') {
			Swal.fire({
				icon: 'success',
				title: 'Portal Settings saved.',
				confirmButtonColor: '#D83D00'
			});
		} else {
			alert('Portal Settings saved.');
		}
		<?php endif; ?>
		var frame;
		$('#portal-logo').on('click', function(e) {
			e.preventDefault();
			$('#portal-logo-upload').trigger('click');
		});
		$('#portal-logo-upload').on('click', function(e) {
			e.preventDefault();
			if (frame) {
				frame.open();
				return;
			}
			frame = wp.media({
				title: 'Select Portal Logo',
				button: {
					text: 'Use this logo'
				},
				library: {
					type: 'image'
				},
				multiple: false
			});
			frame.on('select', function() {
				var attachment = frame.state().get('selection').first().toJSON();
				$('#portal-logo-id').val(attachment.id);
				$('#portal-logo-preview').html('<img src="' + attachment.url + '" alt="Portal Logo" style="max-width:100%;max-height:100px;">');
			});
			frame.open();
		});
	});
</script>
