<?php
global $wpdb, $current_role;

$func = $_REQUEST['func'] ? $_REQUEST['func'] : '';
$user_id = get_current_user_id();

if ($func == 'toggle_sidebar') {
    $is_show_sidebar = $_REQUEST['is_show_sidebar'];
    update_option('show_sidebar_' . $user_id, $is_show_sidebar);
    echo $is_show_sidebar;
}

$roles_in = array($current_role);
if (in_array($current_role, array('office_manager', 'doctor', 'clinic', 'collector', 'lab_director', 'lab_rep', 'sales_accounting'))) {
    $roles_in[] = 'acli';
} else if (in_array($current_role, array('lab', 'labb', 'labmanager', 'AdminCIDW', 'administrator_p', 'wp_admin'))) {
    $roles_in[] = 'alab';
}

$where_roles = array('me.read_roles is null', ' me.read_roles = \'\'');
foreach ($roles_in as $ri) {
    $where_roles[] = " FIND_IN_SET('{$ri}', me.read_roles)  ";
}

if ($func == 'count_notification') {
    $notification_last_changed_at = (int) get_option('trpdd_notification_cache_last_changed_at',time());

    update_user_meta($user_id, 'trpdd_notification_cache_loaded_at',time());
    $user_notification_loaded_at = time();

    //Count Alert
    $where = " me.msg_type = 'a'";
    $where .= " and (mu.user_id > 0 OR " . implode(' OR ', $where_roles) . ") ";

    $where .= " AND (me.from_date IS NULL OR me.from_date ='0000-00-00' OR me.from_date <= CURDATE()) ";
    $where .= " AND (me.to_date IS NULL OR me.to_date = '0000-00-00' OR me.to_date >= CURDATE()) ";

    $sql_count = "
        SELECT COUNT(*) 
        FROM ci_messages AS me
        LEFT JOIN ci_message_users mu ON mu.msg_ID = me.ID and mu.user_id = {$user_id}
        WHERE {$where}
        AND (mu.readed IS NULL OR mu.readed = 0)
    ";
    $total_alerts = (int) $wpdb->get_var($sql_count);


    //All Alert

    $sql = "select * from ci_messages as me
            left JOIN ci_message_users mu ON mu.msg_ID = me.ID and mu.user_id = {$user_id}
            WHERE {$where}
            AND (mu.readed IS NULL OR mu.readed = 0)
            GROUP BY me.ID
            ORDER BY me.created_date DESC
            limit 10";
    $messages = $wpdb->get_results($sql);
    ob_start();
?>
    <div id="all_notification" class="border-b bg-white shadow-md">
        <div class="max-w-full">
            <div class="w-full max-h-[600px] bg-white">
                <div class="border-b bg-gray-50">
                    <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-amber-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3" />
                                <path d="M12 9v4" />
                                <path d="M12 17h.01" />
                            </svg>
                            <h3 class="font-semibold text-gray-900">System Alerts</h3>
                            <span class="ml-2 inline-flex items-center rounded-md bg-secondary px-2 py-0.5 text-xs">
                                <?php echo count($messages); ?> Active
                            </span>
                        </div>
                        <button class="btn_close_alert h-8 px-3 rounded-md text-gray-500 hover:text-gray-700">✕</button>
                    </div>
                </div>

                <div class="max-h-[480px] overflow-y-auto">
                    <div id="alert_body" class="max-w-7xl mx-auto p-6 space-y-4">
                        <?php if ($messages): ?>
                            <?php foreach ($messages as $msg):
                                $bg = $msg->background ?: '#f9fafb';
                                $level = 'INFO';
                                $text_color = 'text-red-800';
                                $level_class = 'text-gray-800 bg-gray-50 border-gray-200';
                                if ($msg->background == 'critical') {
                                    $level = 'CRITICAL';
                                    $level_class = 'text-red-800 bg-red-50 border-red-200';
                                    $text_color = 'text-red-800';
                                } elseif ($msg->background == 'high') {
                                    $level = 'HIGH';
                                    $level_class = 'text-orange-800 bg-orange-50 border-orange-200';
                                    $text_color = 'text-orange-800';
                                } elseif ($msg->background == 'medium') {
                                    $level = 'MEDIUM';
                                    $level_class = 'text-blue-800 bg-blue-50 border-blue-200';
                                    $text_color = 'text-blue-800';
                                } elseif ($msg->background == 'low') {
                                    $level = 'LOW';
                                    $level_class = 'text-gray-800 bg-gray-50 border-gray-200';
                                    $text_color = 'text-gray-800';
                                }
                            ?>
                                <div id="alert_<?php echo $msg->ID; ?>"
                                    class="item_alert_header flex items-start gap-3 p-4 rounded-lg border shadow-card relative group <?php echo $level_class; ?>">

                                    <div class="mt-0.5 <?php echo $text_color; ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-triangle-alert h-4 w-4" aria-hidden="true">
                                            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path>
                                            <path d="M12 9v4"></path>
                                            <path d="M12 17h.01"></path>
                                        </svg>
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <div class="session_alert flex items-center justify-between mb-1">
                                            <h4 class="font-medium text-sm"><?php echo esc_html($msg->subject) ?: 'No Subject'; ?>
                                            </h4>
                                            <div class="flex items-center gap-2">
                                                <span data-slot="badge"
                                                    class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent [a&amp;]:hover:bg-secondary/90 text-xs <?php echo $level_class; ?>"><?php echo $level; ?></span>
                                                <button
                                                    id-db="<?php echo $msg->ID; ?>"
                                                    id-alert="alert_<?php echo $msg->ID; ?>" data-slot="button"
                                                    class="btn_del_alert inline-flex items-center justify-center whitespace-nowrap text-sm font-medium disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:text-accent-foreground dark:hover:bg-accent/50 rounded-md gap-1.5 has-[&gt;svg]:px-2.5 h-6 w-6 p-0 opacity-0 group-hover:opacity-100 transition-opacity text-red-800 hover:bg-white/20">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                        stroke-linecap="round" stroke-linejoin="round"
                                                        class="lucide lucide-x h-3 w-3" aria-hidden="true">
                                                        <path d="M18 6 6 18"></path>
                                                        <path d="m6 6 12 12"></path>
                                                    </svg>
                                                </button>
                                            </div>

                                        </div>
                                        <p class="text-sm opacity-90 mb-1">
                                            <?php echo $msg->msg; ?>
                                        </p>
                                        <p class="text-xs text-gray-500 flex items-center gap-1">
                                            <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor">
                                                <path d="M12 6v6l4 2" />
                                                <circle cx="12" cy="12" r="10" />
                                            </svg>
                                            <?php echo human_time_diff(strtotime($msg->created_date), current_time('timestamp')); ?>
                                            ago
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="mt-6 pt-4 border-t border-gray-200 flex justify-between items-center">
                                <div class="text-sm text-gray-600"><?php echo count($messages); ?> alerts requiring attention
                                </div>
                                <button class="btn_clear_all_alert h-8 px-3 rounded-md border bg-background text-sm">Clear All
                                    Alerts</button>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-12">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-triangle-alert h-12 w-12 mx-auto mb-4 text-gray-300" aria-hidden="true">
                                    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"></path>
                                    <path d="M12 9v4"></path>
                                    <path d="M12 17h.01"></path>
                                </svg>
                                <h4 class="font-medium text-gray-900 mb-2">No Active Alerts</h4>
                                <p class="text-gray-600">All alerts have been cleared. Great job!</p>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>

            </div>
        </div>
    </div>
    <?php
    $html_alert = ob_get_clean();

    //What's New
    $args = array(
        'post_type'      => 'whatnew',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_key'       => '_version'
    );

    $posts = get_posts($args);

    if (count($posts) == 0) {
        $nextcron = wp_next_scheduled('cron_client_sync_whatnew');
        if ($nextcron == 0 || $nextcron > time() + 300) {
            wp_schedule_single_event(time() + 10, 'cron_client_sync_whatnew');
        }
    }
    $posts = array_filter($posts, function ($post) {
        $version = get_post_meta($post->ID, '_version', true);

        $version = ltrim($version, 'v');

        return version_compare($version, TRCPDD_V, '<=');
    });
    $count_post = 0;
    ob_start();
    ?>
    <div class="border-b bg-white shadow-md">
        <div class="max-w-full">
            <div class="w-full max-h-[600px] bg-white">
                <div class="border-b bg-gray-50">
                    <div class="max-w-7xl mx-auto px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                    height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round"
                                    class="lucide lucide-sparkles h-5 w-5 text-blue-500" aria-hidden="true">
                                    <path
                                        d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z">
                                    </path>
                                    <path d="M20 2v4"></path>
                                    <path d="M22 4h-4"></path>
                                    <circle cx="4" cy="20" r="2"></circle>
                                </svg>
                                <h3 class="font-semibold text-gray-900">What's New in Ignite LIS</h3>
                            </div>
                            <button data-slot="button"
                                class="btn_close_alert inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:bg-accent dark:hover:bg-accent/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 text-gray-500 hover:text-gray-700">✕</button>
                        </div>
                    </div>
                </div>
                <div class="max-h-[520px] overflow-y-auto">
                    <div class="max-w-7xl mx-auto">
                        <div class="p-6">
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                                <div class="lg:col-span-2 flex flex-col h-[500px]" style="height: 500px;">
                                    <div class="flex-1 flex flex-col overflow-hidden">
                                        <div class="flex-1 overflow-y-auto">
                                            <?php
                                            foreach ($posts as $post) {
                                                $news_title = preg_replace('/^[^\:]*:/i', ' ', trim($post->post_title));
                                                $guide_field = get_post_meta($post->ID, '_guide', true);
                                                $version_field = get_post_meta($post->ID, '_version', true);
                                                setup_postdata($post);
                                            ?>
                                                <div id="pr_<?php echo $post->ID; ?>" <?php if ($count_post != 0) {
                                                                                            echo 'style="display:none"';
                                                                                        } ?> class="p_release space-y-6">

                                                    <div class="border-b pb-4">
                                                        <div>
                                                            <h2 class="font-semibold text-lg text-gray-900">Release Notes: <?php echo $news_title; ?></h2>
                                                            <p class="text-xs text-gray-600">Version <?php echo $version_field; ?> • <?php echo get_the_date('F j, Y', $post->ID); ?>
                                                            </p>
                                                        </div>
                                                    </div>

                                                    <div class="prose prose-sm max-w-none">
                                                        <div
                                                            class=" text-sm text-gray-700 leading-relaxed [&amp;&gt;*]:text-sm">
                                                            <?php echo apply_filters('the_content', do_blocks($post->post_content)); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php
                                                if ($guide_field != '') {
                                                ?>
                                                    <div id="pr_<?php echo $post->ID; ?>_guide" style="display:none" class="p_release space-y-6">

                                                        <div class="border-b pb-4">
                                                            <div>
                                                                <h2 class="font-semibold text-lg text-gray-900">User Guide: <?php echo $news_title; ?></h2>
                                                                <p class="text-xs text-gray-600">Version <?php echo $version_field; ?> • <?php echo get_the_date('F j, Y', $post->ID); ?>
                                                                </p>
                                                            </div>
                                                        </div>

                                                        <div class="prose prose-sm max-w-none">
                                                            <div
                                                                class=" text-sm text-gray-700 leading-relaxed [&amp;&gt;*]:text-sm">
                                                                <?php echo apply_filters('the_content', do_blocks($guide_field)); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                            <?php
                                                }
                                                $count_post++;
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="h-[500px] flex flex-col" style="height: 500px;">
                                    <div class="mb-4">
                                        <h4 class="font-semibold text-lg text-gray-900 mb-2">Recent Versions</h4>
                                        <p class="text-xs text-gray-600">Click on any version to view release notes</p>
                                    </div>
                                    <div class="flex-1 space-y-3 overflow-y-auto">
                                        <?php
                                        $count_post = 0;
                                        foreach ($posts as $post) {
                                            setup_postdata($post);
                                            $time_ago = human_time_diff(get_the_time('U', $post), current_time('timestamp'));
                                            $version_field = get_post_meta($post->ID, '_version', true);
                                            $guide_field = get_post_meta($post->ID, '_guide', true);
                                        ?>
                                            <div
                                                post_id="<?php echo $post->ID; ?>"
                                                id="sel_pr_<?php echo $post->ID; ?>" id-post-release="pr_<?php echo $post->ID; ?>" class="sel_p_release border rounded-lg bg-white shadow-sm hover:shadow-md transition-all group border-primary bg-primary/5 shadow-md">
                                                <div class="p-4">
                                                    <div class="mb-3">
                                                        <div class="flex items-center gap-2 mb-2 flex-wrap">
                                                            <?php
                                                            if ($version_field != "") {
                                                            ?>
                                                                <span
                                                                    data-slot="badge"
                                                                    class="pr_v inline-flex items-center justify-center rounded-md border px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden [a&amp;]:hover:bg-accent [a&amp;]:hover:text-accent-foreground text-xs font-mono border-primary text-primary">
                                                                    <?php echo $version_field; ?>
                                                                </span>
                                                            <?php } ?>

                                                            <?php if ($count_post == 0) { ?>
                                                                <span
                                                                    data-slot="badge"
                                                                    class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden [a&amp;]:hover:bg-primary/90 text-xs bg-blue-100 text-blue-700 border-blue-200">
                                                                    Latest
                                                                </span>
                                                            <?php } ?>
                                                        </div>
                                                        <h5 class="font-medium text-sm mb-1 transition-colors text-primary">
                                                            <?php echo get_the_title($post); ?>
                                                        </h5>
                                                        <p class="text-xs text-gray-600 mb-2 line-clamp-2">
                                                            <?php
                                                            $excerpt = get_the_excerpt($post);
                                                            if (!empty($excerpt)) {
                                                                echo $excerpt;
                                                            } else {
                                                                echo wp_trim_words(strip_shortcodes(strip_tags($post->post_content)), 30, '...');
                                                            }
                                                            ?>
                                                        </p><span class="text-xs text-gray-500"><?php echo $time_ago; ?> ago</span>
                                                    </div>
                                                    <div class="flex gap-2">
                                                        <button
                                                            post_id="<?php echo $post->ID; ?>"
                                                            id="btn_note_<?php echo $post->ID; ?>"
                                                            class="btn_note flex items-center gap-2 px-3 py-2 text-xs rounded-md border transition-colors flex-1 bg-primary text-white border-primary"><svg
                                                                xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                                viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                                class="lucide lucide-file-text h-3 w-3" aria-hidden="true">
                                                                <path
                                                                    d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z">
                                                                </path>
                                                                <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                                                                <path d="M10 9H8"></path>
                                                                <path d="M16 13H8"></path>
                                                                <path d="M16 17H8"></path>
                                                            </svg><span>Notes</span>
                                                        </button>
                                                        <?php
                                                        if ($guide_field != "") {
                                                        ?>
                                                            <button
                                                                post_id="<?php echo $post->ID; ?>"
                                                                id="btn_guide_<?php echo $post->ID; ?>"
                                                                id-post-release="pr_<?php echo $post->ID; ?>_guide"
                                                                class="btn_guide flex items-center gap-2 px-3 py-2 text-xs rounded-md border transition-colors flex-1 bg-gray-50 text-gray-700 border-gray-200 hover:bg-gray-100"><svg
                                                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                                    class="lucide lucide-book-open h-3 w-3" aria-hidden="true">
                                                                    <path d="M12 7v14"></path>
                                                                    <path
                                                                        d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z">
                                                                    </path>
                                                                </svg><span>Guide</span>
                                                            </button>
                                                        <?php } ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php
                                            $count_post++;
                                        }
                                        wp_reset_postdata();
                                        ?>
                                    </div>
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
            function reset_emp_pr() {
                $(".sel_p_release, .sel_p_release *").removeClass("border-primary");
                $(".sel_p_release").find(".btn_note").removeClass("bg-primary");
                $(".sel_p_release").find("h5").removeClass("text-primary");
                $(".sel_p_release").find(".pr_v").removeClass("text-primary");
                $(".sel_p_release").find(".btn_note").addClass("text-gray-700");
                $(".sel_p_release").find(".btn_note").addClass("bg-gray-50");
                $(".sel_p_release").find(".btn_note").removeClass("text-white");
                $(".sel_p_release").find(".btn_guide").removeClass("bg-primary");
                $(".sel_p_release").find(".btn_guide").removeClass("text-white");
            }

            function select_ui_emp(id_emp) {
                $("#" + id_emp).addClass("border-primary");
                $("#" + id_emp).find(".btn_note").removeClass("bg-gray-50");
                $("#" + id_emp).find(".btn_note").addClass("bg-primary");
                $("#" + id_emp).find(".btn_note").addClass("text-white");
                $("#" + id_emp).find("h5").addClass("text-primary");
                $("#" + id_emp).find(".pr_v").addClass("text-primary");
            }

            $(".sel_p_release").click(function() {
                $(".p_release").hide();
                var p_id_emp = $(this).attr("id-post-release");
                var p_id = $(this).attr("post_id");
                $("#" + p_id_emp).show();
                reset_emp_pr();
                select_ui_emp("sel_pr_" + p_id);
            });

            $(".sel_p_release").first().click();

            $(".btn_guide").click(function() {
                $(".p_release").hide();
                var p_id = $(this).attr("post_id");
                var p_id_emp = $(this).attr("id-post-release");
                $("#" + p_id_emp).show();
                reset_emp_pr();
                select_ui_emp("sel_pr_" + p_id);
                $("#btn_guide_" + p_id).addClass("bg-primary");
                $("#btn_guide_" + p_id).addClass("text-white");
                $("#btn_guide_" + p_id).removeClass("hover:bg-gray-100");
                $("#btn_note_" + p_id).removeClass("bg-primary");
                $("#btn_note_" + p_id).removeClass("text-white");
                return false;
            });
        });
    </script>
<?php
    $html_whats_new = ob_get_clean();

    $result = [];
    $result['total_alert'] = $total_alerts;
    $result['data_alert'] = $html_alert;
    $result['data_whats_new'] = $html_whats_new;
    $result['last_changed_at'] = $notification_last_changed_at;
    $result['user_loaded_at'] = $user_notification_loaded_at;

    wp_send_json($result);
}

if ($func == 'test') {
    $result = [];
    $updated = $wpdb->query($wpdb->prepare("
        UPDATE ci_message_users mu
        INNER JOIN ci_messages me ON me.ID = mu.msg_ID
        SET mu.readed = 0
        WHERE mu.user_id = %d AND mu.readed = 1
    ", $user_id));

    if ($updated !== false) {
        $result = [
            'status' => 'ok',
            'msg'    => 'All notifications have been marked as read.',
            'count_update'  => (int) $updated
        ];
    } else {
        $result = [
            'status' => 'error',
            'msg'    => 'Failed to clear notifications.' . $user_id
        ];
    }
    wp_send_json($result);
    exit;
}

if ($func == 'clear_notification') {
    $type_notifi = $_REQUEST['type'];

    $result = [];
    $unread = $wpdb->get_results($wpdb->prepare("
        SELECT m.ID 
        FROM ci_messages m
        LEFT JOIN ci_message_users mu 
            ON mu.msg_ID = m.ID AND mu.user_id = %d
        WHERE m.msg_type = 'a' AND (mu.readed IS NULL OR mu.readed = 0)
    ", $user_id));

    if (!empty($unread)) {
        foreach ($unread as $msg) {
            $wpdb->query($wpdb->prepare("
                INSERT INTO ci_message_users (msg_ID, user_id, readed)
                VALUES (%d, %d, 1)
                ON DUPLICATE KEY UPDATE readed = 1
            ", $msg->ID, $user_id));
        }

        $result = [
            'status' => 'ok',
            'msg'    => 'All notifications have been marked as read.'
        ];
    } else {
        $result = [
            'status' => 'ok',
            'msg'    => 'No unread notifications found.'
        ];
    }
    wp_send_json($result);
    exit;
}

if ($func == 'read_more_notification') {
    $where = " me.msg_type = 'n'";
    $where .= " and (mu.user_id > 0 OR " . implode(' OR ', $where_roles) . ") ";

    $page_show = $_REQUEST['page_show'];
    $noti_per_page = $_REQUEST['noti_per_page'];
    $offset        = ($page_show - 1) * $noti_per_page;
    $sql = "SELECT 
            me.*, 
		    um.meta_value AS user_roles
        FROM ci_messages AS me
        LEFT JOIN ci_message_users mu 
            ON mu.msg_ID = me.ID and mu.user_id = {$user_id}
        LEFT JOIN {$wpdb->usermeta} um 
            ON um.user_id = me.author 
            AND um.meta_key = '{$wpdb->prefix}capabilities'
        WHERE {$where}
        GROUP BY me.ID
        ORDER BY me.created_date DESC
        LIMIT {$offset}, {$noti_per_page}
        ";
    $messages = $wpdb->get_results($sql);

    foreach ($messages as $msg) {
        include TRCPDD_PATH . 'parts/dashboard/noti_item.php';
    }
    exit;
}

if ($func == 'remove_notification') {
    $id_notification = $_REQUEST['id_notification'];
    $type = $_REQUEST['type'];
    $message = $wpdb->get_row($wpdb->prepare("
        SELECT * 
        FROM ci_messages 
        WHERE ID = %d AND msg_type = %s
    ", $id_notification, $type));

    $result = [];

    if ($message) {
        $wpdb->query($wpdb->prepare("
            INSERT INTO ci_message_users (msg_ID, user_id, readed)
            VALUES (%d, %d, 1)
            ON DUPLICATE KEY UPDATE readed = 1
        ", $id_notification, $user_id));

        $result = [
            'status' => 'ok',
            'msg'    => 'Notification has been marked as read successfully.'
        ];
    } else {
        $wpdb->query($wpdb->prepare("
            INSERT INTO ci_message_users (msg_ID, user_id, readed)
            VALUES (%d, %d, 1)
            ON DUPLICATE KEY UPDATE readed = 1
        ", $id_notification, $user_id));
        $result = [
            'status' => 'error',
            'msg'    => 'Notification not found or type mismatch. ID:' . $id_notification
        ];
    }
    wp_send_json($result);
    exit;
}
