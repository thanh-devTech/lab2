<?php
global $wpdb;

$user_id = get_current_user_id();
$state_sidebar = get_user_meta($user_id, 'state_sidebar', 1);
$patient_id = tr_patient_get_id_patient($user_id);

$user_name_display = "Guest";
if (is_user_logged_in()) {
    $current_user = wp_get_current_user();
    $user_name_display = esc_html($current_user->display_name);
}

//fix old error
if (is_object($patient_id)) {
    $patient_id = $patient_id->pa_ID;
    $wpdb->update($wpdb->users, array('patient_id' => $patient_id), array('ID' => $user_id));
}

$patient = $wpdb->get_row("select * from ci_patients where pa_ID = {$patient_id} ");


$pa_options = get_option('trpatient_settings', array());
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


$url_logo_footer = TRCPPR_URL . 'images/logo_ignite_lis.png';
?>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="This application provides interactive charts for visualizing delay rates, enhancing data analysis and decision-making for users in real-time.">
    <meta name="title" content="Molecular Lab Information System Dashboard">
    <meta name="robots" content="noindex">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Molecular Lab Information System Dashboard">
    <meta name="twitter:description" content="This application provides interactive charts for visualizing delay rates, enhancing data analysis and decision-making for users in real-time.">
    <meta property="og:title" content="Molecular Lab Information System Dashboard">
    <meta property="og:description" content="This application provides interactive charts for visualizing delay rates, enhancing data analysis and decision-making for users in real-time.">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Molecular Lab Information System Dashboard">
    <title><?php echo get_bloginfo('name'); ?></title>
    <link rel="stylesheet" href="<?php echo TRCPPR_URL; ?>/css/patient_frontend.css?<?php echo TRCPPR_V; ?>">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="<?php echo TRCPPR_URL; ?>/js/patient_frontend.js?<?php echo TRCPPR_V; ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="flex h-screen w-full overflow-hidden" id="rs_warp">

        <div id="menu_sidebar" data-slot="sidebar" data-state="<?php if ($state_sidebar == '' || $state_sidebar == 1) { ?>expanded<?php } else { ?>collapsed<?php } ?>" data-collapsible="" data-variant="sidebar" data-side="left" class="group peer hidden md:flex flex-col flex-shrink-0 transition-[width] duration-200 ease-linear text-sidebar-foreground <?php if ($state_sidebar == '' || $state_sidebar == '1') {
                                                                                                                                                                                                                                                                                                                                                                    echo 'w-[240px]';
                                                                                                                                                                                                                                                                                                                                                                } else {
                                                                                                                                                                                                                                                                                                                                                                    echo 'w-[60px]';
                                                                                                                                                                                                                                                                                                                                                                } ?> shadow-elevated">
            <div data-sidebar="sidebar" data-slot="sidebar-inner" class="bg-sidebar text-sidebar-foreground border-r border-gray-200 flex h-full w-full flex-col group-data-[variant=floating]:rounded-lg group-data-[variant=floating]:border group-data-[variant=floating]:border-sidebar-border">
                <div data-slot="sidebar-header" data-sidebar="header" class="flex flex-col gap-2 p-2 border-b border-gray-200 px-6 py-5 bg-gradient-to-b from-white to-gray-50">
                    <div class="flex items-center justify-center h-full min-h-[3rem]">
                        <img src="<?php echo $url_logo_header; ?>" alt="ignite Patient Portal" class="h-12 w-auto max-h-full max-w-full object-contain">
                    </div>
                </div>
                <div data-slot="sidebar-content" data-sidebar="content" class="flex min-h-0 flex-1 flex-col gap-2 overflow-auto group-data-[collapsible=icon]:overflow-hidden px-3 py-4 bg-white">
                    <ul data-slot="sidebar-menu" data-sidebar="menu" class="flex w-full min-w-0 flex-col gap-1 space-y-1">
                        <li data-slot="sidebar-menu-item" data-sidebar="menu-item" class="group/menu-item relative">
                            <button onclick="window.location='<?php echo home_url(); ?>/patient/results'" data-slot="sidebar-menu-button" data-sidebar="menu-button" data-size="default" <?php if ($this->current_page == 'patient/results') { ?>data-active="true" <?php } ?> class="cursor-pointer peer/menu-button flex items-center gap-2 overflow-hidden p-2 text-left outline-hidden ring-sidebar-ring focus-visible:ring-2 active:bg-sidebar-accent active:text-sidebar-accent-foreground disabled:pointer-events-none disabled:opacity-50 group-has-data-[sidebar=menu-action]/menu-item:pr-8 aria-disabled:pointer-events-none aria-disabled:opacity-50 data-[active=true]:font-medium data-[state=open]:hover:bg-sidebar-accent data-[state=open]:hover:text-sidebar-accent-foreground group-data-[collapsible=icon]:size-8! group-data-[collapsible=icon]:p-2! [&amp;&gt;span:last-child]:truncate [&amp;&gt;svg]:size-4 [&amp;&gt;svg]:shrink-0 h-8 text-base w-full justify-start <?php if ($state_sidebar == '' || $state_sidebar == 1) {
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    echo 'px-4';
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                } else {
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    echo 'px-3';
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                } ?> py-3 rounded-lg transition-all text-gray-700 data-[active=true]:bg-[#D83D00] data-[active=true]:text-white data-[active=true]:shadow-md hover:bg-gray-100 hover:text-gray-900">
                                <div class="flex items-center gap-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text h-5 w-5 flex-shrink-0" aria-hidden="true">
                                        <path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"></path>
                                        <path d="M14 2v5a1 1 0 0 0 1 1h5"></path>
                                        <path d="M10 9H8"></path>
                                        <path d="M16 13H8"></path>
                                        <path d="M16 17H8"></path>
                                    </svg>
                                    <span class="font-medium">Lab Results</span>
                                </div>
                            </button>
                        </li>
                        <li data-slot="sidebar-menu-item" data-sidebar="menu-item" class="group/menu-item relative">
                            <button onclick="window.location='<?php echo home_url(); ?>/patient/linked'" data-slot="sidebar-menu-button" data-sidebar="menu-button" data-size="default" <?php if ($this->current_page == 'patient/linked') { ?>data-active="true" <?php } ?> class="cursor-pointer peer/menu-button flex items-center gap-2 overflow-hidden p-2 text-left outline-hidden ring-sidebar-ring focus-visible:ring-2 active:bg-sidebar-accent active:text-sidebar-accent-foreground disabled:pointer-events-none disabled:opacity-50 group-has-data-[sidebar=menu-action]/menu-item:pr-8 aria-disabled:pointer-events-none aria-disabled:opacity-50 data-[active=true]:font-medium data-[state=open]:hover:bg-sidebar-accent data-[state=open]:hover:text-sidebar-accent-foreground group-data-[collapsible=icon]:size-8! group-data-[collapsible=icon]:p-2! [&amp;&gt;span:last-child]:truncate [&amp;&gt;svg]:size-4 [&amp;&gt;svg]:shrink-0 h-8 text-base w-full justify-start <?php if ($state_sidebar == '' || $state_sidebar == 1) {
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    echo 'px-4';
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                } else {
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    echo 'px-3';
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                } ?> py-3 rounded-lg transition-all text-gray-700 data-[active=true]:bg-[#D83D00] data-[active=true]:text-white data-[active=true]:shadow-md hover:bg-gray-100 hover:text-gray-900">
                                <div class="flex items-center gap-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users h-5 w-5 flex-shrink-0" aria-hidden="true">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                        <path d="M16 3.128a4 4 0 0 1 0 7.744"></path>
                                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                    </svg>
                                    <span class="font-medium">Add Family Member</span>
                                </div>
                            </button>
                        </li>
                        <li data-slot="sidebar-menu-item" data-sidebar="menu-item" class="group/menu-item relative">
                            <button onclick="window.location='<?php echo home_url(); ?>/patient/help'" data-slot="sidebar-menu-button" data-sidebar="menu-button" data-size="default" <?php if ($this->current_page == 'patient/help') { ?>data-active="true" <?php } ?> class="cursor-pointer peer/menu-button flex items-center gap-2 overflow-hidden p-2 text-left outline-hidden ring-sidebar-ring focus-visible:ring-2 active:bg-sidebar-accent active:text-sidebar-accent-foreground disabled:pointer-events-none disabled:opacity-50 group-has-data-[sidebar=menu-action]/menu-item:pr-8 aria-disabled:pointer-events-none aria-disabled:opacity-50 data-[active=true]:font-medium data-[state=open]:hover:bg-sidebar-accent data-[state=open]:hover:text-sidebar-accent-foreground group-data-[collapsible=icon]:size-8! group-data-[collapsible=icon]:p-2! [&amp;&gt;span:last-child]:truncate [&amp;&gt;svg]:size-4 [&amp;&gt;svg]:shrink-0 h-8 text-base w-full justify-start <?php if ($state_sidebar == '' || $state_sidebar == 1) {
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                echo 'px-4';
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            } else {
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                echo 'px-3';
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            } ?> py-3 rounded-lg transition-all text-gray-700 data-[active=true]:bg-[#D83D00] data-[active=true]:text-white data-[active=true]:shadow-md hover:bg-gray-100 hover:text-gray-900">
                                <div class="flex items-center gap-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-question-mark h-5 w-5 flex-shrink-0" aria-hidden="true">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                                        <path d="M12 17h.01"></path>
                                    </svg>
                                    <span class="font-medium">Help &amp; Support</span>
                                </div>
                            </button>
                        </li>
                    </ul>
                </div>
                <div data-slot="sidebar-footer" data-sidebar="footer" class="flex flex-col gap-2 border-t border-gray-200 bg-gray-50 p-4">
                    <div class="flex items-center justify-center"><img src="<?php echo $url_logo_footer; ?>" alt="ignite LIS" class="h-12 w-auto max-w-full object-contain"></div>
                </div>
            </div>
        </div>


        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

            <header class="flex-shrink-0 border-b border-gray-200 bg-white px-6 py-3 ">
                <div class="flex items-center justify-between gap-4">

                    <div class="flex items-center gap-4 flex-1 min-w-0">
                        <button id="btn_toggle_sidebar" data-slot="sidebar-trigger" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:bg-accent hover:text-accent-foreground dark:hover:bg-accent/50 rounded-md size-7" data-sidebar="trigger"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-panel-left">
                                <rect width="18" height="18" x="3" y="3" rx="2"></rect>
                                <path d="M9 3v18"></path>
                            </svg><span class="sr-only">Toggle Sidebar</span>
                        </button>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-2 text-gray-600">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-calendar w-4 h-4 flex-shrink-0" aria-hidden="true">
                                <path d="M8 2v4"></path>
                                <path d="M16 2v4"></path>
                                <rect width="18" height="18" x="3" y="4" rx="2"></rect>
                                <path d="M3 10h18"></path>
                            </svg>
                            <span class="text-sm hidden md:inline">
                                <?php echo date_i18n('D | M d, Y, h:i A T'); ?>
                            </span>
                            <span class="text-sm md:hidden"><?php echo date_i18n('h:i A'); ?></span>
                        </div>
                        <div class="flex items-center gap-2 text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user w-4 h-4 flex-shrink-0" aria-hidden="true">
                                <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <span class="text-sm font-medium hidden sm:inline"><?php echo $user_name_display; ?></span>
                            <input type="hidden" id="user_id" value="<?php echo $user_id; ?>">
                        </div>
                        <div class="relative cursor-pointer" id="menu_wrapper">
                            <button id="menu_user" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-settings w-4 h-4 text-gray-600" aria-hidden="true">
                                    <path d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>

                            <div id="menu_nav_user" style="display: none;" class="absolute right-0 mt-2 w-48 bg-white rounded-lg border border-gray-200 shadow-card py-1 z-20">
                                <button onclick="window.location='<?php echo home_url(); ?>/patient/profile'" class="cursor-pointer w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user w-4 h-4" aria-hidden="true">
                                        <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>Edit Profile
                                </button>

                                <button onclick="window.location='<?php echo wp_logout_url(); ?>'" class="cursor-pointer w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-log-out w-4 h-4" aria-hidden="true">
                                        <path d="m16 17 5-5-5-5"></path>
                                        <path d="M21 12H9"></path>
                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                    </svg>Logout
                                </button>
                            </div>

                        </div>
                    </div>
                </div>
            </header>
