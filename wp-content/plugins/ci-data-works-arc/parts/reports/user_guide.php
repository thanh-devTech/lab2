<?php
global $wpdb;

$slug_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
$tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
$act = isset($_GET['act']) ? sanitize_key(wp_unslash($_GET['act'])) : '';

$slug_page = str_replace('ci_', '', $slug_page);
$slug_page = str_replace('datareport_', 'reports_', $slug_page);
$category = '';

$slug_mapping = [
    "reports_pcr_summary" => "reports_prevalence_rates",
    "reports_state_report" => "covid_reporting"
];
if (isset($slug_mapping[$slug_page])) {
    $slug_page = $slug_mapping[$slug_page];
}

if (strpos($slug_page, 'reports_') === 0) {
    $category = 'report';
}
if (strpos($slug_page, 'reportabx_') === 0) {
    $category = 'ignite_abx';
}
if (in_array($slug_page, ['dashboard_main', 'clinic_statistics', 'tox_report', 'covid_reporting', 'clinic_support'])) {
    $category = 'dashboard';
}
if ($slug_page === 'lab_main') {
    $category = 'labs';
}

$ignite_slugs_has_tab = ['reportabx_main'];
$ignite_slugs_has_widget = ['dashboard_main'];
$ignite_slugs_has_act = ['lab_main'];

$slug_pages = [];
$order_by_widget = false;

if (in_array($slug_page, $ignite_slugs_has_tab)) {
    $tab_page = $tab ?: 'all';
    if ($slug_page === 'reportabx_main' && $tab_page !== 'all') {
        $tab_page = 'uti';
    }
    $slug_pages[] = $slug_page . '|' . $tab_page;
}
if (in_array($slug_page, $ignite_slugs_has_act)) {
    $act_page = $act ?: 'list';
    $slug_pages[] = $slug_page . '|' . $act_page;
}
if (in_array($slug_page, $ignite_slugs_has_widget)) {
    $enable_widgets = isset($dashboard_widgets) ? $dashboard_widgets : [];
    if (!empty($enable_widgets)) {
        $order_by_widget = true;
        foreach ($enable_widgets as $widget) {
            $slug_pages[] = $slug_page . '|' . $widget;
        }
    }
}

if (empty($slug_pages)) {
    $slug_pages[] = $slug_page;
}

$like_conditions = [];
$query_params = ['_pages', '_category', 'userguides'];

foreach ($slug_pages as $sp) {
    $like_conditions[] = "pm_page.meta_value LIKE %s";
    $query_params[] = '%"' . $sp . '"%';
}

$query_params[] = $category;

$like_sql = implode(' OR ', $like_conditions);

$sql = $wpdb->prepare(
    "
    SELECT DISTINCT p.post_title, p.post_content, pm_page.meta_value as page_slugs, p.post_date
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm_page
        ON p.ID = pm_page.post_id AND pm_page.meta_key = %s
    JOIN {$wpdb->postmeta} pm_category
        ON p.ID = pm_category.post_id AND pm_category.meta_key = %s
    WHERE p.post_type = %s
      AND (
          ({$like_sql})
          OR (pm_category.meta_value = %s AND (pm_page.meta_value = '' OR pm_page.meta_value IS NULL))
      )
    ORDER BY p.post_date DESC
    ",
    ...$query_params
);
$user_guides = $wpdb->get_results($sql);
if ($order_by_widget && !empty($user_guides)) {
    $slug_order = array_flip($slug_pages);

    usort($user_guides, function ($a, $b) use ($slug_order) {
        $pos_a = 9999;
        $pos_b = 9999;
        $page_slugs_a = (string) ($a->page_slugs ?? '');
        $page_slugs_b = (string) ($b->page_slugs ?? '');

        foreach ($slug_order as $slug => $index) {
            if (str_contains($page_slugs_a, '"' . $slug . '"')) {
                $pos_a = $index;
                break;
            }
        }

        foreach ($slug_order as $slug => $index) {
            if (str_contains($page_slugs_b, '"' . $slug . '"')) {
                $pos_b = $index;
                break;
            }
        }

        return $pos_a - $pos_b;
    });
}

$post_content = '';
if (!empty($user_guides)) {
    foreach ($user_guides as $user_guide) {
        $post_content .= apply_filters('the_content', $user_guide->post_content ?? '');
    }
}
if (!empty($post_content)) {
?>
    <div id="trpddUserGuideRoot">
        <button id="openHelpBtn" style="top:82px;right:20px; z-index:667;" class="fixed z-40 bg-primary hover:bg-primary/90 cursor-pointer text-white rounded-full w-8 h-8 shadow-md hover:shadow-lg transition-all duration-200 flex items-center justify-center" aria-label="Open help sidebar" title="Help Guide">
            <span class="text-lg leading-none">i</span>
        </button>

        <div id="helpSidebar" style="z-index: 99999; width:336px; right:100%;" class="fixed top-0 h-full bg-white border-l border-gray-200 shadow-2xl transition-all translate-x-full duration-300 overflow-hidden">
            <div class="h-full flex flex-col">
                <div class="help_head flex items-center justify-between p-4 border-b border-gray-200 bg-[#3C3D59]">
                    <div class="help_head_title flex items-center gap-2 text-white"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-question-mark w-5 h-5" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                            <path d="M12 17h.01"></path>
                        </svg>
                        <h2 class="font-semibold">Help Guide</h2>
                    </div>
                    <button id="closeHelpBtn" data-slot="button" class="cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:text-accent-foreground dark:hover:bg-accent/50 rounded-md gap-1.5 has-[&gt;svg]:px-2.5 text-white hover:bg-white/10 h-8 w-8 p-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x w-4 h-4" aria-hidden="true">
                            <path d="M18 6 6 18"></path>
                            <path d="m6 6 12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto p-6 post_content">
                    <?php echo $post_content; ?>
                </div>
                <div class="border-t border-gray-200 p-4 bg-gray-50">
                    <p class="text-xs text-gray-600 text-center">Need more help? Contact the Ignite support team.</p>
                </div>
            </div>
        </div>
    </div>
    <?php if (empty($trpdd_skip_user_guide_script) && !(defined('DOING_AJAX') && DOING_AJAX)) { ?>
        <script src="<?php echo esc_url(TRCPDD_URL . 'js/user_guide.js?ver=' . filemtime(TRCPDD_PATH . 'js/user_guide.js')); ?>"></script>
    <?php } ?>
<?php
}
?>