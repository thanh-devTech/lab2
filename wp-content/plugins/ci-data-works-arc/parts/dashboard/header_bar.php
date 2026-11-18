<?php
global $pagenow;
global $wpdb;
global $is_ci_admin;

$user_id = get_current_user_id();
$user_name_display = "Guest";
if (is_user_logged_in()) {
    $current_user = wp_get_current_user();
    $user_name_display = esc_html($current_user->display_name);
}

$notification_last_changed_at = (int) get_option('trpdd_notification_cache_last_changed_at', time());
$user_notification_loaded_at = (int) get_user_meta($user_id, 'trpdd_notification_cache_loaded_at', true);

?>
<div id="wpadminbar" style="display: none;height: 0 !important;"></div>
<div id="header_bar" n_last_changed_at="<?php echo $notification_last_changed_at; ?>" user_n_loaded_at="<?php echo  $user_notification_loaded_at ?>" <?php if ($pagenow === 'post-new.php' || $pagenow === 'post.php') {
                                                                                                                                                            echo 'style="display:none"';
                                                                                                                                                        } ?>>
    <header class="on_sidebar  bg-white/90 backdrop-blur-sm px-6 py-4 shadow-card">
        <div class="flex items-center justify-between">
            <div id="header_bar_area_icon_slider" class="flex items-center gap-4">
                <button id="btn_toggle_sidebar" data-slot="sidebar-trigger"
                    class="inline-flex items-center justify-center gap-2 whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:bg-accent hover:text-accent-foreground dark:hover:bg-accent/50 rounded-md size-7"
                    data-sidebar="trigger">

                    <svg id="btn_toggle_sidebar_icon_open" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" class="lucide lucide-panel-left">
                        <rect width="18" height="18" x="3" y="3" rx="2"></rect>
                        <path d="M9 3v18"></path>
                    </svg>
                    <span class="sr-only">Toggle Sidebar</span>
                </button>
                <div id="system_online"
                    class="items-center gap-2 px-3 py-1.5 bg-green-50 rounded-full border border-green-200">
                    <div class="h-2 w-2 rounded-full bg-green-500 animate-pulse"></div><span
                        class="text-sm font-medium text-green-700">System Online</span>
                </div>

                <?php

                if (trpdd_can_view_header_clinic_dropdown()) {
                    $user_id = get_current_user_id();

                    $where = ' cli.cli_status = 1 ';
                    $join = '';
                    if (tr_current_role_in(array('office_manager'))) {
                        $cid = get_user_meta($user_id, '_clinic_id', true);
                        $join = ' JOIN ci_clinic_manager cm ON cm.ma_cli_ID = cli.cID ';

                        if (!tr_get_option('ci_pdf_data', 'off_o_cli') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {

                            $where .= " and (cm.ma_user_ID={$user_id} or cm.ma_cli_ID = '{$cid}')";
                        }
                    } else if (tr_current_role_in(array('doctor'))) {
                        $doc_id = get_user_meta($user_id, '_doc_id', true);
                        if (!tr_get_option('ci_pdf_data', 'doctor_see_all')) {
                            $where .= " and cID in  (select cli_ID from ci_clinic_doc where doc_ID = {$doc_id})";
                        }
                    } else if (tr_current_role_in(array('collector'))) {
                        $col_id = get_user_meta($user_id, '_collector_id', true);
                        if ($col_id == 0) {
                            $col_id = -1;
                        }
                        if (tr_get_option('ci_pdf_data', 'coloshisreqs')) {
                            $cli_ids = $wpdb->get_col("select cli_ID from ci_clinic_collector where col_ID = {$col_id}");
                            $cli_ids = implode(',', $cli_ids);
                            if (empty($cli_ids))
                                $cli_ids = '-1';
                            $where .= " and cID in ({$cli_ids})  ";
                        } else if (!tr_get_option('ci_pdf_data', 'collector_see_all')) {

                            $cli_ids = $wpdb->get_col("select cli_ID from ci_clinic_collector where col_ID = {$col_id}");
                            $cli_ids = implode(',', $cli_ids);
                            if (empty($cli_ids))
                                $cli_ids = '-1';
                            $where .= " and cID in ({$cli_ids})  ";
                        }
                    }

                    $sql_clinic = "select cli.* from ci_clinic cli
                        {$join}
                        where {$where}
                        group by cli.cID order by cli.cli_clinic";

                    $clinics = $wpdb->get_results($sql_clinic);

                    global $ci_showdbar_clinic_dropdown;
                    $ci_showdbar_clinic_dropdown = true;

                    echo '<select id="use_current_clinic_id" class="headerbarselect shadow-card rounded-md" onchange="location = location.href + (location.href.indexOf(\'?\')>-1? \'&\':\'?\')+ \'tr_action=use_current_clinic_id&cid=\'+jQuery(this).val();"><option value="0">All Clinics</option>';
                    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
                    $has_selected = false;
                    foreach ($clinics as $clinic) {
                        $selected = $clinic->cID == $current_clinic ? 'selected' : '';
                        if ($clinic->cID == $current_clinic) {
                            $has_selected = true;
                        }
                        echo '<option value="' . $clinic->cID . '" ' . $selected . '>' . $clinic->cli_clinic . '</option>';
                    }
                    if (!$has_selected) {
                        update_user_meta($user_id, '_use_current_clinic_id', '');
                    }
                    echo '</select>';
                }
                ?>

                <button id="btn_header_notification" data-slot="button"
                    class="inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 shadow-card hover:shadow-card-hover transition-shadow-smooth">
                    <svg id="bar_notification_icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" class="lucide lucide-triangle-alert h-4 w-4 mr-2 text-amber-500"
                        aria-hidden="true">
                        <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path>
                        <path d="M12 9v4"></path>
                        <path d="M12 17h.01"></path>
                    </svg>
                    <txtcountnotification id="txtCountNotification"><span id="txtNcount">0</span> Alerts</txtcountnotification>
                </button>

                <button id="btn_list_whats_new" data-slot="button"
                    class="items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 shadow-card hover:shadow-card-hover transition-shadow-smooth"><svg
                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-sparkles h-4 w-4 mr-2 text-blue-500" aria-hidden="true">
                        <path
                            d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z">
                        </path>
                        <path d="M20 2v4"></path>
                        <path d="M22 4h-4"></path>
                        <circle cx="4" cy="20" r="2"></circle>
                    </svg>
                    <txtShow>What's New</txtShow>
                </button>
            </div>
            <div id="header_bar_info_login" class=" items-center gap-4">
                <div id="header_bar_date" class="flex  bar_date items-center gap-2 text-sm text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-calendar h-4 w-4" aria-hidden="true">
                        <path d="M8 2v4"></path>
                        <path d="M16 2v4"></path>
                        <rect width="18" height="18" x="3" y="4" rx="2"></rect>
                        <path d="M3 10h18"></path>
                    </svg>
                    <span>
                        <?php
                        echo date_i18n('D | M d, Y, h:i A');
                        ?>
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-user h-4 w-4 text-gray-500" aria-hidden="true">
                        <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <span class="text-gray-700 font-medium">
                        <?php
                        echo $user_name_display;
                        ?>
                    </span>
                </div>

                <div id="header_bar_setting_wrapper" style="position:relative;display:inline-block;">
                    <button id="btn_header_bar_setting" data-slot="button"
                        class="inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:bg-accent hover:text-accent-foreground dark:hover:bg-accent/50 h-8 rounded-md gap-1.5 has-[&gt;svg]:px-2.5 p-2"><svg
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-settings h-4 w-4 text-gray-500" aria-hidden="true">
                            <path
                                d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915">
                            </path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>

                    <div id="menu_header_bar_setting" data-radix-popper-content-wrapper="" dir="ltr"
                        style="position:absolute;left:-83px;top:100%;display:none;min-width: max-content; will-change: transform; z-index: 50; --radix-popper-available-width: 1424px; --radix-popper-available-height: 568.25px; --radix-popper-anchor-width: 115.609375px; --radix-popper-anchor-height: 31.5px; --radix-popper-transform-origin: 100% 0px;">
                        <div data-side="bottom" data-align="end" role="menu" aria-orientation="vertical"
                            data-state="open" data-radix-menu-content="" dir="ltr" id="radix-:rm:"
                            aria-labelledby="radix-:rl:" data-slot="dropdown-menu-content"
                            class="bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 max-h-(--radix-dropdown-menu-content-available-height) min-w-[8rem] origin-(--radix-dropdown-menu-content-transform-origin) overflow-x-hidden overflow-y-auto rounded-md border p-1 shadow-md"
                            tabindex="-1" data-orientation="vertical"
                            style="outline: none; --radix-dropdown-menu-content-transform-origin: var(--radix-popper-transform-origin); --radix-dropdown-menu-content-available-width: var(--radix-popper-available-width); --radix-dropdown-menu-content-available-height: var(--radix-popper-available-height); --radix-dropdown-menu-trigger-width: var(--radix-popper-anchor-width); --radix-dropdown-menu-trigger-height: var(--radix-popper-anchor-height); pointer-events: auto;">
                            <a href="<?php echo tr_get_option('ci_pdf_data', 'new_user_manager') ? '?page=ci_user_edit' : 'profile.php'; ?>" role="menuitem"
                                data-slot="dropdown-menu-item" data-variant="default"
                                class="focus:bg-accent cursor-pointer focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
                                tabindex="-1" data-orientation="vertical" data-radix-collection-item="">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="#6b7280" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <circle cx="12" cy="7" r="4" />
                                    <path d="M5.5 21c0-3.5 3.5-6 6.5-6s6.5 2.5 6.5 6" />
                                    <path d="M18 13l3 3-5 5H13v-3l5-5z" />
                                </svg>
                                Edit Profile
                            </a>
                            <a href="<?php echo wp_logout_url(home_url()); ?>" role="menuitem"
                                data-slot="dropdown-menu-item" data-variant="default"
                                class="focus:bg-accent cursor-pointer focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
                                tabindex="-1" data-orientation="vertical" data-radix-collection-item="">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="#6b7280" stroke-width="1.5" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path d="M15 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4" />
                                    <path d="M10 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5" />
                                    <path d="M15 12H3m12 0l-3-3m3 3l-3 3" />
                                </svg>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <button id="header_bar_menu_mobile" data-slot="dropdown-menu-trigger"
                class="items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:bg-accent hover:text-accent-foreground dark:hover:bg-accent/50 h-8 rounded-md gap-1.5 has-[&gt;svg]:px-2.5 p-2 sm:hidden"
                type="button" id="radix-:ra:" aria-haspopup="menu" aria-expanded="false" data-state="closed"><svg
                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="lucide lucide-ellipsis-vertical h-4 w-4 text-gray-500" aria-hidden="true">
                    <circle cx="12" cy="12" r="1"></circle>
                    <circle cx="12" cy="5" r="1"></circle>
                    <circle cx="12" cy="19" r="1"></circle>
                </svg></button>
        </div>
    </header>
    <div id="header_bar_content" style="display:none"></div>
</div>
<div id="toast-container"></div>

<div id="menu_header_bar_mobile"
    style="display:none;position: fixed;top: 45px;right: 4px;width: 168px;min-width: max-content;will-change: transform;z-index: 777;">
    <div data-side="bottom" data-align="end" role="menu" aria-orientation="vertical" data-state="open"
        data-radix-menu-content="" dir="ltr" id="radix-:rb:" aria-labelledby="radix-:ra:"
        data-slot="dropdown-menu-content"
        class="bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 max-h-(--radix-dropdown-menu-content-available-height) min-w-[8rem] origin-(--radix-dropdown-menu-content-transform-origin) overflow-x-hidden overflow-y-auto rounded-md border p-1 shadow-md w-56"
        tabindex="-1" data-orientation="vertical"
        style="outline: none; --radix-dropdown-menu-content-transform-origin: var(--radix-popper-transform-origin); --radix-dropdown-menu-content-available-width: var(--radix-popper-available-width); --radix-dropdown-menu-content-available-height: var(--radix-popper-available-height); --radix-dropdown-menu-trigger-width: var(--radix-popper-anchor-width); --radix-dropdown-menu-trigger-height: var(--radix-popper-anchor-height); pointer-events: auto;">
        <div id="btn_what_new_mobile" role="menuitem" data-slot="dropdown-menu-item" data-variant="default"
            class="focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
            tabindex="-1" data-orientation="vertical" data-radix-collection-item="">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                class="lucide lucide-sparkles h-4 w-4 mr-2 text-blue-500" aria-hidden="true">
                <path
                    d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z">
                </path>
                <path d="M20 2v4"></path>
                <path d="M22 4h-4"></path>
                <circle cx="4" cy="20" r="2"></circle>
            </svg>
            What's New
        </div>

        <div role="separator" aria-orientation="horizontal" data-slot="dropdown-menu-separator"
            class="bg-border -mx-1 my-1 h-px"></div>

        <a href="<?php echo get_edit_profile_url(get_current_user_id()); ?>" role="menuitem"
            data-slot="dropdown-menu-item" data-variant="default"
            class="focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
            tabindex="-1" data-orientation="vertical" data-radix-collection-item="">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                stroke="#6b7280" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="7" r="4" />
                <path d="M5.5 21c0-3.5 3.5-6 6.5-6s6.5 2.5 6.5 6" />
                <path d="M18 13l3 3-5 5H13v-3l5-5z" />
            </svg>
            <?php echo $user_name_display; ?>
        </a>

        <a href="<?php echo wp_logout_url(home_url()); ?>" role="menuitem" data-slot="dropdown-menu-item"
            data-variant="default"
            class="focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
            tabindex="-1" data-orientation="vertical" data-radix-collection-item="">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                stroke="#6b7280" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4" />
                <path d="M10 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5" />
                <path d="M15 12H3m12 0l-3-3m3 3l-3 3" />
            </svg>
            Logout
        </a>

        <div role="separator" aria-orientation="horizontal" data-slot="dropdown-menu-separator"
            class="bg-border -mx-1 my-1 h-px"></div>
        <div role="menuitem" data-slot="dropdown-menu-item" data-variant="default"
            class="focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4 text-green-700"
            tabindex="-1" data-orientation="vertical" data-radix-collection-item="">
            <div class="h-2 w-2 rounded-full bg-green-500 mr-2">

            </div>
            System Online
        </div>
    </div>
</div>

<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_header_bar.js?' . filemtime(TRCPDD_PATH . 'js/admin_header_bar.js'); ?>"></script>