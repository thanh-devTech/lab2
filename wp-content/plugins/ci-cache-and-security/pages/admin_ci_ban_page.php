<?php
global $tr_security;

$msg_success = '';

if (isset($_POST['secu_settings'])) {
    $options = $_POST['secu_settings'];
    $secu_settings = get_option('tr_security');
    $old_settings = is_array($secu_settings) ? $secu_settings : array();
    $secu_settings['session_timeout'] = !empty($options['session_timeout']) ? $options['session_timeout'] : 20;
    $secu_settings['white_ips'] = $options['white_ips'];
    $secu_settings['white_ips_array'] = trcsc_validate_list_ip($options['white_ips']);
    sort($secu_settings['white_ips_array']);
    $secu_settings['white_ips'] = implode("\n", $secu_settings['white_ips_array']);

    $ips = implode("','", $secu_settings['white_ips_array']);
    $wpdb->query("delete from wp_tr_lock_ip where ip in ('{$ips}')");


    if (function_exists('trpdd_log_user')) {
        trpdd_log_user(get_current_user_id(), 'update_login_security', array(
            'session_timeout' => $secu_settings['session_timeout'],
            'white_ips' => $secu_settings['white_ips'],
            'old' => array(
                'session_timeout' => isset($old_settings['session_timeout']) ? $old_settings['session_timeout'] : '',
                'white_ips' => isset($old_settings['white_ips']) ? $old_settings['white_ips'] : '',
            ),
        ));
    }
    $msg_success = 'Settings saved successfully!';
    update_option('tr_security', $secu_settings);
}

$secu_settings = get_option('tr_security');
if (empty($secu_settings['session_timeout'])) {
    $secu_settings['session_timeout'] = 20;
}
$mypip = $tr_security->get_ip();

$sessions_timeout = [
    15 => '15 minutes',
    20 => '20 minutes (Recommended)',
    30 => '30 minutes',
    45 => '45 minutes',
    60 => '60 minutes (Extended)',
];

?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    h4 {
        margin: unset !important;
    }

    p {
        margin: unset;
    }

    #colbans>div {
        max-height: 500px !important
    }

    .min-h-180px {
        min-height: 180px;
    }

    .bg-input-background {
        background-color: var(--input-background);
    }

    .w-64 {
        width: calc(var(--spacing) * 64);
    }

    @media (width >=40rem) {
        .sm\:w-64 {
            width: calc(var(--spacing) * 64);
        }
    }

    .border-gray-300 {
        border-color: var(--color-gray-300);
    }

    #tbl_logs {
        border: none;
    }

    #tbl_logs thead {
        background-color: color-mix(in oklab, var(--color-gray-50) 80%, transparent);
    }

    #tbl_logs thead tr th {
        color: var(--color-gray-500);
        --tw-font-weight: var(--font-weight-bold);
        font-weight: var(--font-weight-bold);
        border: none;
    }

    #tbl_logs tbody tr td,
    #tbl_logs thead tr th {
        padding-left: calc(var(--spacing) * 8) !important;
        text-align: left;
    }

    #tbl_logs tbody tr td:last-child,
    #tbl_logs thead tr th:last-child {
        text-align: right;
        padding-right: calc(var(--spacing) * 8) !important;
    }

    #tbl_logs tbody tr {
        border-bottom: solid 1px oklch(.967 .003 264.542);
    }

    .removeban {
        color: rgb(220, 38, 38);
        --tw-font-weight: var(--font-weight-semibold);
        font-weight: var(--font-weight-semibold);
    }

    .btn_success {
        background-color: rgb(56 161 91) !important;
    }

    .select2-container--default .select2-selection--single {
        height: 2.25rem !important;
        padding: 0.5rem 0.75rem !important;
        font-size: 0.875rem !important;
        line-height: 1.25rem !important;
        color: #4b5563 !important;
        background-color: #f8f9fb !important;
        border: 1px solid #d1d5db !important;
        border-radius: 8px !important;
        outline: none !important;
        transition: all 0.15s ease-in-out !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #4b5563 !important;
        line-height: 1.25rem !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 2.25rem !important;
        right: 0.75rem !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow b {
        border-color: #6b7280 transparent transparent transparent !important;
        opacity: 0.5 !important;
    }

    .select2-container--default .select2-selection--single:hover {
        background-color: #f9fafb !important;
    }

    .select2-container--default .select2-results__option {
        padding: 0.5rem 0.75rem !important;
        font-size: 0.875rem !important;
    }

    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #f3f4f6 !important;
        color: #374151 !important;
    }

    .select2-container--default .select2-results__option[aria-selected="true"] {
        background-color: #fef2f2 !important;
        color: #D83D00 !important;
        font-weight: 500 !important;
    }

    .select2-container--default .select2-results__option[aria-selected="true"]::after {
        content: '✓';
        float: right;
        font-weight: bold;
        margin-left: 8px;
    }

    .select2-dropdown {
        border: 1px solid #d1d5db !important;
        border-radius: 0.375rem !important;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
    }

    .select2-container--default.select2-container--disabled .select2-selection--single {
        opacity: 0.5 !important;
        cursor: not-allowed !important;
    }

    #risk-acknowledgment[data-state="checked"] {
        background-color: #d97706 !important;
        border-color: #d97706 !important;
    }

    #risk-acknowledgment {
        border-color: #f59e0b !important;
    }

    #risk-acknowledgment:focus {
        border-color: #f59e0b;
        box-shadow: none;
    }

    #risk-acknowledgment {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        outline: none;
    }

    #risk-acknowledgment[data-state="checked"]::before {
        content: '✓';
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 12px;
        height: 14px;
        font-weight: bold;
    }
</style>

<div class="wrap">
    <div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
        <form method="post" class="space-y-4 pb-10" id="security-form">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">Login Security</h1>
                    <p class="text-muted-foreground mt-1 text-sm">Manage trusted IPs and automatic login protections.</p>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        id="btn_back"
                        data-slot="button"
                        class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3">
                        Cancel
                    </button>

                    <button
                        type="submit"
                        data-slot="button"
                        class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground hover:bg-primary/90 h-9 px-4 py-2 has-[&gt;svg]:px-3 shadow-brand hover:shadow-brand-lg transition-all"
                        style="background-color: rgb(216, 61, 0);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24"
                            height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save h-4 w-4 mr-2"
                            aria-hidden="true">
                            <path
                                d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z">
                            </path>
                            <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                            <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                        </svg>Save Changes
                    </button>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4">
                <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 border-b border-gray-100 bg-gray-50/30 py-3">
                        <div class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clock h-5 w-5 text-[#3C3D59]" aria-hidden="true">
                                <path d="M12 6v6l4 2"></path>
                                <circle cx="12" cy="12" r="10"></circle>
                            </svg>
                            <h4 data-slot="card-title" class="text-base font-semibold">Session Timeout</h4>
                        </div>
                        <p data-slot="card-description" class="text-muted-foreground text-sm">Automatically log users out after inactivity.</p>
                    </div>
                    <div data-slot="card-content" class="[&amp;:last-child]:pb-6 px-6 py-5" style="padding-top: 1.25rem;">
                        <div style="max-width: 28rem;">
                            <select name="secu_settings[session_timeout]" id="session_timeout" required>
                                <?php
                                foreach ($sessions_timeout as $k => $s) {
                                    $selected = $secu_settings['session_timeout'] == $k ? 'selected' : '';
                                    echo '<option value="' . $k . '" ' . $selected . '>' . $s . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div id="current-timeout-warning" class="mt-4" style="max-width: 28rem; display: none;">
                            <div class="flex flex-col gap-3 p-4 bg-amber-50 rounded-lg border" style="border-color:#fee685;">
                                <div class="flex items-start gap-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-triangle-alert h-5 w-5 text-amber-600 shrink-0 mt-0.5">
                                        <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path>
                                        <path d="M12 9v4"></path>
                                        <path d="M12 17h.01"></path>
                                    </svg>
                                    <div class="space-y-1">
                                        <p class="text-sm text-imp font-bold uppercase tracking-tight" style="color: #7b3306;">Security Risk Increased</p>
                                        <p class="text-sm text-imp leading-snug" style="color: #973c00;">Recommended timeout is 20 minutes. Current setting increases the risk of unauthorized access on shared or unattended devices.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="new-timeout-warning" class="mt-4" style="max-width: 28rem; display: none;">
                            <div class="flex flex-col gap-3 p-4 bg-amber-50 rounded-lg border border-amber-200 animate-in fade-in slide-in-from-top-2 duration-300" style="border-color:#fee685;">
                                <div class="flex items-start gap-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-triangle-alert h-5 w-5 text-amber-600 shrink-0 mt-0.5">
                                        <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path>
                                        <path d="M12 9v4"></path>
                                        <path d="M12 17h.01"></path>
                                    </svg>
                                    <div class="space-y-1">
                                        <p class="text-sm  text-imp font-bold uppercase tracking-tight" style="color: #7b3306;">Security Risk Increased</p>
                                        <p class="text-sm  text-imp leading-snug" style="color: #973c00;">Recommended timeout is 20 minutes. Current setting increases the risk of unauthorized access on shared or unattended devices.</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-2 mt-2 border-t" style="padding-top: 0.5rem; border-color: #fee68580;">
                                    <button
                                        type="button"
                                        role="checkbox"
                                        aria-checked="false"
                                        data-state="unchecked"
                                        value="on"
                                        data-slot="checkbox"
                                        class="peer bg-input-background dark:bg-input/30 data-[state=checked]:text-primary-foreground dark:data-[state=checked]:bg-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 mt-1 border-amber-400 data-[state=checked]:bg-amber-600 data-[state=checked]:border-amber-600"
                                        id="risk-acknowledgment">
                                    </button>
                                    <label for="risk-acknowledgment" class="text-sm leading-snug cursor-pointer select-none font-medium" style="color: #7b3306;">
                                        I understand and accept the risk of extending the session timeout.
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div data-slot="card"
                    class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card overflow-hidden">
                    <div data-slot="card-header"
                        class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 border-b border-gray-100 bg-gray-50/30 py-3">
                        <div class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-shield-check h-5 w-5 text-green-600" aria-hidden="true">
                                <path
                                    d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z">
                                </path>
                                <path d="m9 12 2 2 4-4"></path>
                            </svg>
                            <h4 data-slot="card-title" class="text-base font-semibold">Trusted IP Addresses</h4>
                        </div>
                        <p data-slot="card-description" class="text-muted-foreground text-sm">Enter one IP address per line.
                            These addresses will always be allowed.</p>
                    </div>

                    <div data-slot="card-content" class="[&amp;:last-child]:pb-6 px-6 pb-6 pt-3">
                        <div class="space-y-3 max-w-2xl">
                            <div class="relative">
                                <textarea
                                    id="white_ips"
                                    name="secu_settings[white_ips]"
                                    data-slot="textarea"
                                    class="placeholder:text-muted-foreground focus-visible:border-ring aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 flex field-sizing-content rounded-md border bg-input-background px-3 py-2 transition-[color,box-shadow] outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm min-h-180px max-h-[250px] w-full font-mono text-sm leading-relaxed resize-none focus-visible:ring-1 focus-visible:ring-[#D83D00] border-gray-300"
                                    placeholder="68.49.2.249..." style="border-left: none;"><?php echo $secu_settings['white_ips'] ?></textarea>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-gray-500">Add My Current IP:</span>
                                    <button
                                        onclick="jQuery('#white_ips').val(jQuery('#white_ips').val()+'\n<?php echo $mypip; ?>')"
                                        class="text-sm font-medium hover:underline flex items-center gap-1 transition-colors cursor-pointer"
                                        style="color: rgb(216, 61, 0);"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                            height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-mouse-pointer2 lucide-mouse-pointer-2 h-3.5 w-3.5"
                                            aria-hidden="true">
                                            <path
                                                d="M4.037 4.688a.495.495 0 0 1 .651-.651l16 6.5a.5.5 0 0 1-.063.947l-6.124 1.58a2 2 0 0 0-1.438 1.435l-1.579 6.126a.5.5 0 0 1-.947.063z">
                                            </path>
                                        </svg>
                                        <?php echo $mypip; ?>
                                    </button>
                                </div>
                            </div>
                            <div class="bg-amber-50 border border-amber-100 rounded-lg p-2 flex items-start gap-2.5 mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="lucide lucide-info h-4 w-4 text-amber-500 mt-0.5 flex-shrink-0"
                                    aria-hidden="true">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <path d="M12 16v-4"></path>
                                    <path d="M12 8h.01"></path>
                                </svg>
                                <p class="text-sm text-amber-800 leading-normal">IPs added here are always allowed and skip
                                    automatic protections. Only add trusted clinic or office networks.</p>
                            </div>

                        </div>

                        <div class="flex justify-end pt-2 mt-4">
                            <button
                                type="submit"
                                data-slot="button"
                                class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground hover:bg-primary/90 h-9 px-4 py-2 has-[&gt;svg]:px-3 shadow-brand hover:shadow-brand-lg transition-all"
                                style="background-color: rgb(216, 61, 0);">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24"
                                    height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save h-4 w-4 mr-2"
                                    aria-hidden="true">
                                    <path
                                        d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z">
                                    </path>
                                    <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                                    <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                                </svg>
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>
                <div data-slot="card"
                    class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card overflow-hidden">
                    <div data-slot="card-header"
                        class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 border-b border-gray-100 bg-gray-50/30 py-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                    height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round"
                                    class="lucide lucide-shield-alert h-5 w-5" aria-hidden="true"
                                    style="color: rgb(216, 61, 0);">
                                    <path
                                        d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z">
                                    </path>
                                    <path d="M12 8v4"></path>
                                    <path d="M12 16h.01"></path>
                                </svg>
                                <h4 data-slot="card-title" class="text-base font-semibold">Automatically Blocked IP
                                    Addresses</h4>
                            </div>
                            <div class="relative w-full sm:w-64"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                    height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round"
                                    class="lucide lucide-search absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400"
                                    aria-hidden="true">
                                    <path d="m21 21-4.34-4.34"></path>
                                    <circle cx="11" cy="11" r="8"></circle>
                                </svg>
                                <input id="search_ip" data-slot="input"
                                    class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input flex w-full min-w-0 rounded-md border px-3 py-1 bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive pl-9 h-9 text-sm focus-visible:ring-1 focus-visible:ring-[#D83D00]"
                                    placeholder="Search Blocked IPs..." value="">
                            </div>
                        </div>
                        <p data-slot="card-description" class="text-muted-foreground text-sm mt-0.5">These IPs were
                            temporarily blocked after repeated failed or suspicious login attempts.</p>
                    </div>
                    <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-0">
                        <div class="max-h-[350px] overflow-auto scrollbar-hide">
                            <div data-slot="table-container" class="relative w-full overflow-x-auto text-center">
                                <?php include(TRSCSC_PATH . 'inc/tab-custom-autoban.php'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <input type="hidden" name="page" value="ci_ban_page">
        </form>
    </div>
</div>

<script>
    jQuery(function($) {

        <?php
        if ($msg_success != '') {
        ?>
            Swal.fire({
                title: 'Success',
                text: '<?php echo $msg_success; ?>',
                icon: 'success',
                customClass: {
                    confirmButton: 'btn_success'
                }
            });
        <?php
        }
        ?>

        $('#session_timeout').select2({
            minimumResultsForSearch: Infinity,
            width: '100%'
        });

        var initialTimeout = parseInt($('#session_timeout').val());
        var isCheckboxChecked = false;

        $('#risk-acknowledgment').on('click', function() {
            var currentState = $(this).attr('data-state');
            if (currentState === 'checked') {
                $(this).attr('data-state', 'unchecked');
                $(this).attr('aria-checked', 'false');
                isCheckboxChecked = false;
            } else {
                $(this).attr('data-state', 'checked');
                $(this).attr('aria-checked', 'true');
                isCheckboxChecked = true;
            }
        });

        function checkTimeoutValue() {
            var selectedTimeout = parseInt($('#session_timeout').val());
            var currentWarning = $('#current-timeout-warning');
            var newWarning = $('#new-timeout-warning');
            var checkbox = $('#risk-acknowledgment');

            currentWarning.hide();
            newWarning.hide();

            if (selectedTimeout > 20) {
                if (selectedTimeout === initialTimeout) {
                    currentWarning.show();
                } else {
                    newWarning.show();
                }
            } else {
                checkbox.attr('data-state', 'unchecked');
                checkbox.attr('aria-checked', 'false');
                isCheckboxChecked = false;
            }
        }
        checkTimeoutValue();

        $('#session_timeout').on('change', function() {
            checkTimeoutValue();
        });

        $('#security-form').on('submit', function(e) {
            var selectedTimeout = parseInt($('#session_timeout').val());

            if (selectedTimeout > 20 && selectedTimeout !== initialTimeout && !isCheckboxChecked) {
                e.preventDefault();
                Swal.fire({
                    title: 'Confirmation Required',
                    text: 'Please confirm that you understand the risks of extending the session timeout.',
                    icon: 'warning',
                    confirmButtonText: 'OK',
                    customClass: {
                        confirmButton: 'btn_success'
                    }
                });
                return false;
            }
        });

        $('#search_ip').on('keyup', function() {
            var value = $(this).val().toLowerCase();

            $('#tbl_logs tbody tr').filter(function() {
                var ip = $(this).find('td:first').text().toLowerCase();
                $(this).toggle(ip.indexOf(value) > -1);
            });

        });

        $('#btn_back').on('click', function(e) {
            e.preventDefault();
            if (document.referrer) {
                window.history.back();
            } else {
                window.location.href = ajaxurl.replace('admin-ajax.php', '');
            }
            return false;
        });

    });
</script>