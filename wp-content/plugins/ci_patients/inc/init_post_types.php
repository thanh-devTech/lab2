<?php
function trhub_register_post_type($type, $name, $names, $menu_name, $supports = null, $rewrite = true, $others = null)
{
    $labels = array(
        'name' => $name,
        'singular_name' => $name,
        'add_new' => _x('Add New', 'entity'),
        'add_new_item' => __('Add New ' . $name),
        'edit_item' => __('Edit ' . $name),
        'new_item' => __('New ' . $name),
        'all_items' => __('All ' . $names),
        'view_item' => __('View ' . $name),
        'search_items' => __('Search ' . $names),
        'not_found' =>  __('No ' . $names . ' found'),
        'not_found_in_trash' => __('No ' . $names . ' found in Trash'),
        'parent_item_colon' => '',
        'menu_name' => $menu_name

    );

    if ($supports == null) {
        $supports = array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments');
    }

    $args = array(
        'labels' => $labels,
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'query_var' => true,
        'rewrite' => $rewrite,
        'has_archive' => true,
        'hierarchical' => false,
        'menu_position' => null,
        //'capability_type' => array($type, $type . 's'),
        'supports' => $supports
    );
    if (is_array($others)) {
        $args = array_merge($args, $others);
    }
    register_post_type($type, $args);
}

function sync_help_support_posts($source_blog_id = 1)
{
    global $wpdb;

    $blog_prefix = $wpdb->get_blog_prefix($source_blog_id);
    $source_posts_table = $blog_prefix . 'posts';
    $source_postmeta_table = $blog_prefix . 'postmeta';

    // Check if source and destination tables are different
    if ($source_posts_table == $wpdb->posts) {
        error_log('Source and destination tables are the same, no sync needed');
        return array('success' => false, 'message' => 'Same table');
    }

    // Get posts from source table
    $sql_source = $wpdb->prepare(
        "
        SELECT *
        FROM {$source_posts_table}
        WHERE post_type = %s AND post_status = 'publish'
        ORDER BY post_date DESC
        ",
        'help_support'
    );
    $source_posts = $wpdb->get_results($sql_source, ARRAY_A);

    if (empty($source_posts)) {
        error_log('No posts found in source table to sync');
        return array('success' => false, 'message' => 'No posts found');
    }

    $synced_posts = 0;
    $synced_meta = 0;

    foreach ($source_posts as $post) {
        $old_post_id = $post['ID'];
        unset($post['ID']);

        $inserted = $wpdb->insert($wpdb->posts, $post);

        if ($inserted) {
            $synced_posts++;

            $new_post_id = $wpdb->insert_id;

            $meta_query = $wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$source_postmeta_table} WHERE post_id = %d",
                $old_post_id
            );
            $post_metas = $wpdb->get_results($meta_query, ARRAY_A);

            if (!empty($post_metas)) {
                foreach ($post_metas as $meta) {
                    $meta_inserted = $wpdb->insert(
                        $wpdb->postmeta,
                        array(
                            'post_id' => $new_post_id,
                            'meta_key' => $meta['meta_key'],
                            'meta_value' => $meta['meta_value']
                        ),
                        array('%d', '%s', '%s')
                    );

                    if ($meta_inserted) {
                        $synced_meta++;
                    }
                }
            }
        }
    }

    return array(
        'success' => true,
        'synced_posts' => $synced_posts,
        'synced_meta' => $synced_meta
    );
}
// Function to sync/update posts from source to destination
function update_help_support_posts($source_blog_id = 1)
{
    global $wpdb;

    $blog_prefix = $wpdb->get_blog_prefix($source_blog_id);
    $source_posts_table = $blog_prefix . 'posts';
    $source_postmeta_table = $blog_prefix . 'postmeta';

    if ($source_posts_table == $wpdb->posts) {
        return array('success' => false, 'message' => 'Same table');
    }

    $sql_source = $wpdb->prepare(
        "
        SELECT *
        FROM {$source_posts_table}
        WHERE post_type = %s AND post_status = 'publish'
        ORDER BY post_date DESC
        ",
        'help_support'
    );
    $source_posts = $wpdb->get_results($sql_source, ARRAY_A);

    if (empty($source_posts)) {
        return array('success' => false, 'message' => 'No source posts found');
    }

    $inserted_posts = 0;
    $updated_posts = 0;
    $skipped_posts = 0;
    $synced_meta = 0;

    foreach ($source_posts as $source_post) {
        $source_post_id = $source_post['ID'];
        $menu_order = $source_post['menu_order'];

        $dest_post = $wpdb->get_row($wpdb->prepare(
            "
            SELECT p.ID, pm.meta_value as is_edited
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_is_edit_help_support'
            WHERE p.post_type = %s AND post_status = 'publish' AND p.menu_order = %d
            LIMIT 1
            ",
            'help_support',
            $menu_order
        ), ARRAY_A);

        if ($dest_post) {
            $dest_post_id = $dest_post['ID'];

            if (!empty($dest_post['is_edited'])) {
                $skipped_posts++;
                continue;
            }

            // Post exists but not edited, UPDATE it
            $post_data = array(
                'post_content' => $source_post['post_content'],
                'post_excerpt' => $source_post['post_excerpt'],
                'post_title' => $source_post['post_title'],
                'post_name' => $source_post['post_name'],
                'post_modified' => current_time('mysql'),
                'post_modified_gmt' => current_time('mysql', 1),
            );

            $updated = $wpdb->update(
                $wpdb->posts,
                $post_data,
                array('ID' => $dest_post_id),
                array('%s', '%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );

            if ($updated !== false) {
                $updated_posts++;
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->postmeta} 
                    WHERE post_id = %d 
                    AND meta_key != '_is_edit_help_support'",
                    $dest_post_id
                ));

                $meta_query = $wpdb->prepare(
                    "SELECT meta_key, meta_value FROM {$source_postmeta_table} WHERE post_id = %d",
                    $source_post_id
                );
                $post_metas = $wpdb->get_results($meta_query, ARRAY_A);

                if (!empty($post_metas)) {
                    foreach ($post_metas as $meta) {

                        $meta_inserted = $wpdb->insert(
                            $wpdb->postmeta,
                            array(
                                'post_id' => $dest_post_id,
                                'meta_key' => $meta['meta_key'],
                                'meta_value' => $meta['meta_value']
                            ),
                            array('%d', '%s', '%s')
                        );

                        if ($meta_inserted) {
                            $synced_meta++;
                        }
                    }
                }

                clean_post_cache($dest_post_id);
            }
        } else {

            unset($source_post['ID']);

            $inserted = $wpdb->insert($wpdb->posts, $source_post);

            if ($inserted) {
                $inserted_posts++;

                $new_post_id = $wpdb->insert_id;

                $meta_query = $wpdb->prepare(
                    "SELECT meta_key, meta_value FROM {$source_postmeta_table} WHERE post_id = %d",
                    $source_post_id
                );
                $post_metas = $wpdb->get_results($meta_query, ARRAY_A);

                if (!empty($post_metas)) {
                    foreach ($post_metas as $meta) {

                        $meta_inserted = $wpdb->insert(
                            $wpdb->postmeta,
                            array(
                                'post_id' => $new_post_id,
                                'meta_key' => $meta['meta_key'],
                                'meta_value' => $meta['meta_value']
                            ),
                            array('%d', '%s', '%s')
                        );

                        if ($meta_inserted) {
                            $synced_meta++;
                        }
                    }
                }
            }
        }
    }

    return array(
        'success' => true,
        'inserted_posts' => $inserted_posts,
        'updated_posts' => $updated_posts,
        'skipped_posts' => $skipped_posts,
        'synced_posts' => $inserted_posts + $updated_posts,
        'synced_meta' => $synced_meta
    );
}

add_action('init', 'trhub_new_post_types', 0);
function trhub_new_post_types()
{
    global $wpdb;
    trhub_register_post_type('help_support', 'Patient FAQ', 'Patient FAQ', 'Patient FAQ', array('title', 'editor'), false, array('menu_position' => 108, 'menu_icon' => 'dashicons-editor-help'));

    $sql = $wpdb->prepare(
        "
        SELECT COUNT(*) 
        FROM {$wpdb->posts}
        WHERE post_type = %s AND post_status = 'publish'
        ",
        'help_support'
    );
    $count = $wpdb->get_var($sql);

    if ($count == 0) {
        sync_help_support_posts(1);
    }
}

add_action('save_post_help_support', 'save_help_support_edit_flag', 10, 3);
function save_help_support_edit_flag($post_id, $post, $update)
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if ($post->post_type !== 'help_support') {
        return;
    }

    update_post_meta($post_id, '_is_edit_help_support', 1);
}

// add_action('manage_posts_extra_tablenav', 'add_sync_button_to_list_page');
function add_sync_button_to_list_page($which)
{
    global $typenow;

    if ($typenow !== 'help_support' || $which !== 'top') {
        return;
    }
?>
    <div class="alignleft actions">
        <button type="button" id="sync-help-support-list-btn" class="button button-primary">
            <span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
            Sync Help & Support
        </button>
        <span id="sync-help-support-list-status" style="margin-left: 10px; line-height: 28px;"></span>
    </div>
<?php
}

// add_action('admin_footer', 'add_sync_button_list_page_script');
function add_sync_button_list_page_script()
{
    global $typenow;

    if ($typenow !== 'help_support') {
        return;
    }
?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#sync-help-support-list-btn').on('click', function(e) {
                e.preventDefault();

                if (confirm('Do you want to sync help support?')) {
                    var $btn = $(this);
                    var $status = $('#sync-help-support-list-status');

                    $btn.prop('disabled', true);
                    $status.html('<span style="color: #0073aa;"><span class="dashicons dashicons-update spin" style="animation: rotation 2s infinite linear;"></span> Syncing...</span>');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sync_help_support_ajax',
                            nonce: '<?php echo wp_create_nonce('sync_help_support_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                var msg = 'Inserted: ' + response.data.inserted_posts +
                                    ', Updated: ' + response.data.updated_posts +
                                    ', Skipped: ' + response.data.skipped_posts +
                                    ', Meta: ' + response.data.synced_meta;

                                $status.html('<span style="color: #46b450;"><span class="dashicons dashicons-yes"></span> ' + msg + '</span>');

                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            } else {
                                $status.html('<span style="color: #dc3232;"><span class="dashicons dashicons-no"></span> Error: ' +
                                    response.data.message + '</span>');
                                $btn.prop('disabled', false);
                            }
                        },
                        error: function() {
                            $status.html('<span style="color: #dc3232;"><span class="dashicons dashicons-no"></span> AJAX Error</span>');
                            $btn.prop('disabled', false);
                        }
                    });
                }
            });
        });
    </script>
    <style>
        @keyframes rotation {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(359deg);
            }
        }

        .spin {
            display: inline-block;
        }
    </style>
    <?php
}

add_action('wp_ajax_sync_help_support_ajax', 'handle_sync_help_support_ajax');
function handle_sync_help_support_ajax()
{

    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sync_help_support_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
        return;
    }

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => 'Permission denied'));
        return;
    }

    $result = update_help_support_posts(1);

    if ($result['success']) {
        wp_send_json_success(array(
            'synced_posts' => $result['synced_posts'],
            'synced_meta' => $result['synced_meta'],
            'inserted_posts' => $result['inserted_posts'],
            'updated_posts' => $result['updated_posts'],
            'skipped_posts' => $result['skipped_posts']
        ));
    } else {
        wp_send_json_error(array('message' => $result['message']));
    }
}

// sort help support
add_action('add_meta_boxes', function () {
    add_meta_box(
        'help_support_order',
        __('Order'),
        'help_support_order_meta_box_cb',
        'help_support',
        'side',
        'high'
    );
});

function help_support_order_meta_box_cb($post)
{
    wp_nonce_field('help_support_order_nonce', 'help_support_order_nonce');
    $order = get_post_meta($post->ID, '_order', true);
    echo '<input type="number" name="help_support_order" min="0" value="' . esc_attr($order !== '' ? $order : 0) . '" style="width:100%" />';
}

add_action('save_post_help_support', function ($post_id) {
    if (!isset($_POST['help_support_order_nonce']) || !wp_verify_nonce($_POST['help_support_order_nonce'], 'help_support_order_nonce')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['help_support_order'])) {
        update_post_meta($post_id, '_order', intval($_POST['help_support_order']));
    }
});

add_filter('manage_help_support_posts_columns', function ($columns) {
    $new_columns = array();
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['help_support_order'] = __('Order');
        }
    }
    return $new_columns;
});

add_action('manage_help_support_posts_custom_column', function ($column, $post_id) {
    if ($column === 'help_support_order') {
        $order = get_post_meta($post_id, '_order', true);
        echo '<span class="editable-order" data-post-id="' . esc_attr($post_id) . '" data-value="' . esc_attr($order !== '' ? $order : 0) . '" style="cursor:pointer; border-bottom:1px dashed #999;">' . esc_html($order !== '' ? $order : 0) . '</span>';
    }
}, 10, 2);

add_filter('manage_edit-help_support_sortable_columns', function ($columns) {
    $columns['help_support_order'] = '_order';
    return $columns;
});
add_action('init', function () {
    if (get_option('help_support_order_migrated')) return;

    $posts = get_posts([
        'post_type'      => 'help_support',
        'posts_per_page' => -1,
        'meta_query'     => [
            [
                'key'     => '_order',
                'compare' => 'NOT EXISTS',
            ],
        ],
    ]);

    foreach ($posts as $post) {
        update_post_meta($post->ID, '_order', 0);
    }

    update_option('help_support_order_migrated', true);
});

add_action('pre_get_posts', function ($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    if ($query->get('post_type') !== 'help_support') return;

    if ($query->get('orderby') === '_order') {
        $query->set('meta_key', '_order');
        $query->set('orderby', 'meta_value_num');
    }

    if (!$query->get('orderby')) {
        $query->set('meta_key', '_order');
        $query->set('orderby', 'meta_value_num');
        $query->set('order', 'ASC');
    }
});

add_action('admin_head', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'help_support') return;

    if (!isset($_GET['orderby'])) {
    ?>
        <script>
            jQuery(function($) {
                $('.manage-column.column-date').removeClass('sorted asc desc');
                $('.manage-column.column-help_support_order').removeClass('desc');

                $('.manage-column.column-help_support_order').addClass('sorted asc');
            });
        </script>
    <?php
    }
});

// 7. Inline editable-cell (AJAX)
add_action('admin_footer-edit.php', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'help_support') return;
    ?>
    <script>
        jQuery(function($) {
            $(document).on('click', '.editable-order', function() {
                var $el = $(this);
                if ($el.find('input').length) return;

                var val = $el.data('value');
                var postId = $el.data('post-id');
                var $input = $('<input type="number" style="width:60px" />').val(val);

                $el.empty().append($input);
                $input.focus();

                function save() {
                    var newVal = parseInt($input.val(), 10);
                    $el.html(newVal).data('value', newVal);

                    $.post(ajaxurl, {
                        action: 'update_help_support_order',
                        post_id: postId,
                        order: newVal,
                        nonce: '<?php echo wp_create_nonce("update_order_nonce"); ?>'
                    });
                }

                $input.on('blur', save).on('keydown', function(e) {
                    if (e.key === 'Enter') save();
                    if (e.key === 'Escape') $el.html(val);
                });
            });
        });
    </script>
<?php
});

add_action('wp_ajax_update_help_support_order', function () {
    check_ajax_referer('update_order_nonce', 'nonce');
    if (!current_user_can('edit_posts')) wp_send_json_error();

    $post_id = intval($_POST['post_id']);
    $order = intval($_POST['order']);
    update_post_meta($post_id, '_order', $order);

    wp_send_json_success();
});

add_action('admin_menu', 'trcppr_register_patient_faq_admin_pages', 90);
function trcppr_register_patient_faq_admin_pages()
{
    if (!function_exists('trcppr_is_new_style_enabled') || !trcppr_is_new_style_enabled()) {
        return;
    }

    remove_menu_page('edit.php?post_type=help_support');
    add_menu_page('Patient FAQ', 'Patient FAQ', 'edit_posts', 'ci_patient_faq', 'trcppr_render_patient_faq_page', 'dashicons-editor-help', 108);
    add_submenu_page('ci_patient_faq', 'Patient FAQ', 'All FAQs', 'edit_posts', 'ci_patient_faq', 'trcppr_render_patient_faq_page');
    add_submenu_page('ci_patient_faq', 'Add New FAQ', 'Add New FAQ', 'edit_posts', 'ci_patient_faq_add', 'trcppr_render_patient_faq_form_page');
}

add_action('admin_init', 'trcppr_redirect_help_support_default_pages');
function trcppr_redirect_help_support_default_pages()
{
    if (!function_exists('trcppr_is_new_style_enabled') || !trcppr_is_new_style_enabled()) {
        return;
    }

    global $pagenow;
    if ($pagenow == 'edit.php' && ($_GET['post_type'] ?? '') == 'help_support') {
        wp_redirect(admin_url('admin.php?page=ci_patient_faq'));
        exit;
    }
    if ($pagenow == 'post-new.php' && ($_GET['post_type'] ?? '') == 'help_support') {
        wp_redirect(admin_url('admin.php?page=ci_patient_faq_add'));
        exit;
    }
    if ($pagenow == 'post.php' && isset($_GET['post'])) {
        $post = get_post(absint($_GET['post']));
        if ($post && $post->post_type == 'help_support') {
            wp_redirect(admin_url('admin.php?page=ci_patient_faq_add&faq_id=' . absint($post->ID)));
            exit;
        }
    }
}

function trcppr_patient_faq_admin_styles()
{
?>
    <style>
        .tr-faq-admin {
            --spacing: .25rem;
            --radius: .75rem;
            --shadow-card: 0 1px 2px rgba(0, 0, 0, .04);
            box-sizing: border-box;
            margin: -10px 0 0 -20px;
            min-height: calc(100vh - 32px);
            color: #111827;
            font-size: 14px;
        }
        .tr-faq-admin *,
        .tr-faq-admin *::before,
        .tr-faq-admin *::after {
            box-sizing: border-box;
        }
        .tr-faq-admin h1,
        .tr-faq-admin h4,
        .tr-faq-admin p {
            margin: 0;
        }
        .tr-faq-admin a {
            text-decoration: none;
        }
        .tr-faq-admin button,
        .tr-faq-admin input,
        .tr-faq-admin textarea,
        .tr-faq-admin select {
            font: inherit;
            letter-spacing: inherit;
        }
        .tr-faq-admin svg {
            display: block;
        }
        .tr-faq-admin .flex { display: flex; }
        .tr-faq-admin .inline-flex { display: inline-flex; }
        .tr-faq-admin .grid { display: grid; }
        .tr-faq-admin .relative { position: relative; }
        .tr-faq-admin .absolute { position: absolute; }
        .tr-faq-admin .flex-1 { flex: 1 1 0%; }
        .tr-faq-admin .flex-col { flex-direction: column; }
        .tr-faq-admin .flex-wrap { flex-wrap: wrap; }
        .tr-faq-admin .items-center { align-items: center; }
        .tr-faq-admin .justify-between { justify-content: space-between; }
        .tr-faq-admin .justify-center { justify-content: center; }
        .tr-faq-admin .gap-0\.5 { gap: 2px; }
        .tr-faq-admin .gap-1\.5 { gap: 6px; }
        .tr-faq-admin .gap-2 { gap: 8px; }
        .tr-faq-admin .gap-3 { gap: 12px; }
        .tr-faq-admin .gap-4 { gap: 16px; }
        .tr-faq-admin .gap-6 { gap: 24px; }
        .tr-faq-admin .space-y-1\.5 > :not(:last-child) { margin-bottom: 6px; }
        .tr-faq-admin .space-y-4 > :not(:last-child) { margin-bottom: 16px; }
        .tr-faq-admin .space-y-5 > :not(:last-child) { margin-bottom: 20px; }
        .tr-faq-admin .space-y-6 > :not(:last-child) { margin-bottom: 24px; }
        .tr-faq-admin .overflow-auto { overflow: auto; }
        .tr-faq-admin .overflow-hidden { overflow: hidden; }
        .tr-faq-admin .overflow-x-auto { overflow-x: auto; }
        .tr-faq-admin .w-3 { width: 12px; }
        .tr-faq-admin .w-4 { width: 16px; }
        .tr-faq-admin .w-10 { width: 40px; }
        .tr-faq-admin .w-12 { width: 48px; }
        .tr-faq-admin .w-24 { width: 96px; }
        .tr-faq-admin .w-28 { width: 112px; }
        .tr-faq-admin .w-36 { width: 144px; }
        .tr-faq-admin .w-56 { width: 224px; }
        .tr-faq-admin .w-full { width: 100%; }
        .tr-faq-admin .h-3 { height: 12px; }
        .tr-faq-admin .h-4 { height: 16px; }
        .tr-faq-admin .h-5 { height: 20px; }
        .tr-faq-admin .h-9 { height: 36px; }
        .tr-faq-admin .min-h-\[180px\] { min-height: 180px; }
        .tr-faq-admin .min-w-0 { min-width: 0; }
        .tr-faq-admin .p-1\.5 { padding: 6px; }
        .tr-faq-admin .p-4 { padding: 16px; }
        .tr-faq-admin .px-2 { padding-left: 8px; padding-right: 8px; }
        .tr-faq-admin .px-2\.5 { padding-left: 10px; padding-right: 10px; }
        .tr-faq-admin .px-3 { padding-left: 12px; padding-right: 12px; }
        .tr-faq-admin .px-4 { padding-left: 16px; padding-right: 16px; }
        .tr-faq-admin .px-5 { padding-left: 20px; padding-right: 20px; }
        .tr-faq-admin .px-6 { padding-left: 24px; padding-right: 24px; }
        .tr-faq-admin .py-0\.5 { padding-top: 2px; padding-bottom: 2px; }
        .tr-faq-admin .py-1 { padding-top: 4px; padding-bottom: 4px; }
        .tr-faq-admin .py-1\.5 { padding-top: 6px; padding-bottom: 6px; }
        .tr-faq-admin .py-2 { padding-top: 8px; padding-bottom: 8px; }
        .tr-faq-admin .py-3 { padding-top: 12px; padding-bottom: 12px; }
        .tr-faq-admin .pt-6 { padding-top: 24px; }
        .tr-faq-admin .pb-16 { padding-bottom: 64px; }
        .tr-faq-admin .pl-9 { padding-left: 36px; }
        .tr-faq-admin .mt-1 { margin-top: 4px; }
        .tr-faq-admin .mt-2 { margin-top: 8px; }
        .tr-faq-admin .ml-1 { margin-left: 4px; }
        .tr-faq-admin .mr-auto { margin-right: auto; }
        .tr-faq-admin .-mb-1 { margin-bottom: -4px; }
        .tr-faq-admin .left-3 { left: 12px; }
        .tr-faq-admin .top-1\/2 { top: 50%; }
        .tr-faq-admin .-translate-y-1\/2 { transform: translateY(-50%); }
        .tr-faq-admin .rounded { border-radius: 4px; }
        .tr-faq-admin .rounded-md { border-radius: 6px; }
        .tr-faq-admin .rounded-lg { border-radius: 8px; }
        .tr-faq-admin .rounded-xl { border-radius: 12px; }
        .tr-faq-admin .rounded-full { border-radius: 999px; }
        .tr-faq-admin .border { border: 1px solid #e5e7eb; }
        .tr-faq-admin .border-b { border-bottom: 1px solid #e5e7eb; }
        .tr-faq-admin .border-t { border-top: 1px solid #f3f4f6; }
        .tr-faq-admin .border-gray-100 { border-color: #f3f4f6; }
        .tr-faq-admin .border-gray-200 { border-color: #e5e7eb; }
        .tr-faq-admin .border-gray-300 { border-color: #d1d5db; }
        .tr-faq-admin .border-\[\#D83D00\] { border-color: #D83D00; }
        .tr-faq-admin .border-transparent { border-color: transparent; }
        .tr-faq-admin .bg-\[\#f8f9fa\] { background: #f8f9fa; }
        .tr-faq-admin .bg-\[\#D83D00\] { background: #D83D00; }
        .tr-faq-admin .bg-\[\#c03600\] { background: #c03600; }
        .tr-faq-admin .bg-card,
        .tr-faq-admin .bg-white { background: #fff; }
        .tr-faq-admin .bg-gray-50 { background: #f9fafb; }
        .tr-faq-admin .bg-gray-100 { background: #f3f4f6; }
        .tr-faq-admin .bg-green-100 { background: #dcfce7; }
        .tr-faq-admin .bg-input-background { background: #f8f9fb; }
        .tr-faq-admin .text-left { text-align: left; }
        .tr-faq-admin .text-xs { font-size: 12px; line-height: 16px; }
        .tr-faq-admin .text-sm { font-size: 14px; line-height: 20px; }
        .tr-faq-admin .text-2xl { font-size: 24px; line-height: 32px; }
        .tr-faq-admin .font-medium { font-weight: 500; }
        .tr-faq-admin .font-semibold { font-weight: 600; }
        .tr-faq-admin .leading-none { line-height: 1; }
        .tr-faq-admin .text-\[\#3C3D59\] { color: #3C3D59; }
        .tr-faq-admin .text-\[\#D83D00\] { color: #D83D00; }
        .tr-faq-admin .text-white { color: #fff; }
        .tr-faq-admin .text-gray-300 { color: #d1d5db; }
        .tr-faq-admin .text-gray-400 { color: #9ca3af; }
        .tr-faq-admin .text-gray-500 { color: #6b7280; }
        .tr-faq-admin .text-gray-600 { color: #4b5563; }
        .tr-faq-admin .text-gray-700 { color: #374151; }
        .tr-faq-admin .text-gray-800 { color: #1f2937; }
        .tr-faq-admin .text-gray-900 { color: #111827; }
        .tr-faq-admin .text-green-700 { color: #15803d; }
        .tr-faq-admin .text-red-500 { color: #ef4444; }
        .tr-faq-admin .whitespace-nowrap { white-space: nowrap; }
        .tr-faq-admin .select-none { user-select: none; }
        .tr-faq-admin .cursor-pointer { cursor: pointer; }
        .tr-faq-admin .shadow-card { box-shadow: var(--shadow-card); }
        .tr-faq-admin .transition-all,
        .tr-faq-admin .transition-colors,
        .tr-faq-admin .transition-opacity { transition-duration: .15s; transition-timing-function: cubic-bezier(.4, 0, .2, 1); }
        .tr-faq-admin .transition-colors { transition-property: color, background-color, border-color; }
        .tr-faq-admin .transition-opacity { transition-property: opacity; }
        .tr-faq-admin .opacity-0 { opacity: 0; }
        .tr-faq-admin .opacity-40 { opacity: .4; }
        .tr-faq-admin .opacity-100 { opacity: 1; }
        .tr-faq-admin .group:hover .group-hover\:opacity-100 { opacity: 1; }
        .tr-faq-admin .divide-y > :not(:last-child) { border-bottom: 1px solid #f3f4f6; }
        .tr-faq-admin .hover\:bg-\[\#c03600\]:hover { background: #c03600; }
        .tr-faq-admin .hover\:bg-gray-50:hover { background: #f9fafb; }
        .tr-faq-admin .hover\:bg-gray-100:hover { background: #f3f4f6; }
        .tr-faq-admin .hover\:bg-gray-200:hover { background: #e5e7eb; }
        .tr-faq-admin .hover\:border-\[\#D83D00\]:hover { border-color: #D83D00; }
        .tr-faq-admin .hover\:text-\[\#D83D00\]:hover { color: #D83D00; }
        .tr-faq-admin .hover\:text-gray-900:hover { color: #111827; }
        .tr-faq-admin .hover\:underline:hover { text-decoration: underline; }
        .tr-faq-admin .focus-visible\:ring-\[3px\]:focus-visible { box-shadow: 0 0 0 3px rgba(216, 61, 0, .25); outline: none; }
        .tr-faq-admin .focus\:ring-\[\#D83D00\]:focus { box-shadow: 0 0 0 3px rgba(216, 61, 0, .25); outline: none; }
        .tr-faq-admin .focus-within\:ring-2:focus-within { box-shadow: 0 0 0 2px rgba(216, 61, 0, .25); }
        .tr-faq-admin .focus-within\:border-transparent:focus-within { border-color: transparent; }
        .tr-faq-admin .outline-none { outline: none; }
        .tr-faq-admin table {
            border-collapse: collapse;
        }
        .tr-faq-admin input[type="text"],
        .tr-faq-admin input[type="search"],
        .tr-faq-admin input[type="number"] {
            border-width: 1px;
            box-shadow: none;
        }
        .tr-faq-admin .tr-faq-search {
            height: 36px;
        }
        .tr-faq-admin .tr-faq-search svg {
            top: 26px;
            transform: translateY(-50%);
            pointer-events: none;
            position: absolute;
        }
        .tr-faq-admin .tr-faq-search input {
            height: 36px;
            line-height: 20px;
            padding-top: 7px;
            padding-bottom: 7px;
            padding-left: 36px;
            margin: 0;
            vertical-align: middle;
        }
        .tr-faq-admin input::placeholder { color: #6b7280; }
        .tr-faq-admin .faq-editor .wp-editor-wrap {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            overflow: hidden;
        }
        .tr-faq-admin .faq-editor .wp-editor-container {
            border: 0;
            box-shadow: none;
        }
        .tr-faq-admin .faq-editor .quicktags-toolbar,
        .tr-faq-admin .faq-editor div.mce-toolbar-grp {
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }
        @media (min-width: 640px) {
            .tr-faq-admin .sm\:p-6 { padding: 24px; }
        }
        @media (min-width: 1024px) {
            .tr-faq-admin .lg\:p-8 { padding: 32px; }
            .tr-faq-admin .lg\:grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .tr-faq-admin .lg\:col-span-2 { grid-column: span 2 / span 2; }
        }
    </style>
<?php
}

function trcppr_patient_faq_icon($name, $classes = 'w-4 h-4')
{
    $icons = array(
        'plus' => '<path d="M5 12h14"></path><path d="M12 5v14"></path>',
        'search' => '<circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path>',
        'up' => '<path d="m18 15-6-6-6 6"></path>',
        'down' => '<path d="m6 9 6 6 6-6"></path>',
        'pencil' => '<path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"></path><path d="m15 5 4 4"></path>',
        'arrow-left' => '<path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path>',
        'bold' => '<path d="M6 12h9a4 4 0 0 1 0 8H7a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h7a4 4 0 0 1 0 8"></path>',
        'italic' => '<line x1="19" x2="10" y1="4" y2="4"></line><line x1="14" x2="5" y1="20" y2="20"></line><line x1="15" x2="9" y1="4" y2="20"></line>',
        'underline' => '<path d="M6 4v6a6 6 0 0 0 12 0V4"></path><line x1="4" x2="20" y1="20" y2="20"></line>',
        'list' => '<path d="M3 12h.01"></path><path d="M3 18h.01"></path><path d="M3 6h.01"></path><path d="M8 12h13"></path><path d="M8 18h13"></path><path d="M8 6h13"></path>',
        'link' => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>',
    );
    return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . esc_attr($classes) . '">' . ($icons[$name] ?? '') . '</svg>';
}

function trcppr_patient_faq_sort_link($key, $label, $current_sort, $current_order)
{
    $next_order = ($current_sort == $key && $current_order == 'asc') ? 'desc' : 'asc';
    $url = add_query_arg(array('orderby' => $key, 'order' => $next_order));
    $up_class = $current_sort == $key && $current_order == 'asc' ? 'w-3 h-3 -mb-1 opacity-100 text-[#D83D00]' : 'w-3 h-3 -mb-1';
    $down_class = $current_sort == $key && $current_order == 'desc' ? 'w-3 h-3 opacity-100 text-[#D83D00]' : 'w-3 h-3';
    return '<a class="inline-flex items-center text-gray-700" href="' . esc_url($url) . '">' . esc_html($label) . '<span class="inline-flex flex-col ml-1 opacity-40">' . trcppr_patient_faq_icon('up', $up_class) . trcppr_patient_faq_icon('down', $down_class) . '</span></a>';
}

function trcppr_patient_faq_status_link($key, $label, $count, $current_status)
{
    $url_args = array('page' => 'ci_patient_faq');
    if ($key != 'all') {
        $url_args['post_status'] = $key;
    }
    if (!empty($_GET['s'])) {
        $url_args['s'] = sanitize_text_field(wp_unslash($_GET['s']));
    }
    if (!empty($_GET['orderby'])) {
        $url_args['orderby'] = sanitize_key($_GET['orderby']);
    }
    if (!empty($_GET['order'])) {
        $url_args['order'] = sanitize_key($_GET['order']);
    }
    $class = $current_status == $key ? 'font-medium text-[#D83D00]' : 'text-gray-500 hover:text-[#D83D00]';
    $url = add_query_arg($url_args, admin_url('admin.php'));
    return '<a href="' . esc_url($url) . '" class="' . esc_attr($class) . '">' . esc_html($label) . ' <span class="font-medium text-gray-900">(' . esc_html($count) . ')</span></a>';
}

function trcppr_render_patient_faq_page()
{
    if (!current_user_can('edit_posts')) {
        wp_die(__('You do not have permission to access this page.'));
    }

    $trashed = false;
    $deleted = false;
    if (isset($_GET['faq_action'], $_GET['faq_id']) && $_GET['faq_action'] == 'trash') {
        $post_id = absint($_GET['faq_id']);
        if (wp_verify_nonce($_GET['_wpnonce'] ?? '', 'trash_patient_faq_' . $post_id) && current_user_can('delete_post', $post_id)) {
            wp_trash_post($post_id);
            $trashed = true;
        }
    } else if (isset($_GET['faq_action'], $_GET['faq_id']) && $_GET['faq_action'] == 'delete') {
        $post_id = absint($_GET['faq_id']);
        if (wp_verify_nonce($_GET['_wpnonce'] ?? '', 'delete_patient_faq_' . $post_id) && current_user_can('delete_post', $post_id)) {
            wp_delete_post($post_id, true);
            $deleted = true;
        }
    }

    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $status_filter = isset($_GET['post_status']) ? sanitize_key($_GET['post_status']) : 'all';
    if (!in_array($status_filter, array('all', 'publish', 'trash'), true)) {
        $status_filter = 'all';
    }
    $query_status = array('publish', 'draft');
    if ($status_filter == 'publish') {
        $query_status = 'publish';
    } else if ($status_filter == 'trash') {
        $query_status = 'trash';
    }
    $sort = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'order';
    $order_dir = isset($_GET['order']) && strtolower($_GET['order']) == 'desc' ? 'DESC' : 'ASC';
    $query_order_args = array('meta_key' => '_order', 'orderby' => 'meta_value_num', 'order' => $order_dir);
    if ($sort == 'title') {
        $query_order_args = array('orderby' => 'title', 'order' => $order_dir);
    } else if ($sort == 'status') {
        $query_order_args = array('orderby' => 'post_status', 'order' => $order_dir);
    } else if ($sort == 'date') {
        $query_order_args = array('orderby' => 'date', 'order' => $order_dir);
    } else {
        $sort = 'order';
    }
    $counts = wp_count_posts('help_support');
    $query = new WP_Query(array_merge(array(
        'post_type' => 'help_support',
        'post_status' => $query_status,
        'posts_per_page' => -1,
        's' => $search,
    ), $query_order_args));

    trcppr_patient_faq_admin_styles();
?>
    <div class="tr-faq-admin">
        <div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
            <div class="space-y-6 pb-16">
                <div class="flex items-center justify-between gap-4">
                    <h1 class="text-2xl font-semibold text-[#3C3D59]">Patient FAQ</h1>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ci_patient_faq_add')); ?>" class="justify-center whitespace-nowrap rounded-md text-sm font-medium transition-all h-9 px-4 py-2 bg-[#D83D00] hover:bg-[#c03600] text-white flex items-center gap-2">
                        <?php echo trcppr_patient_faq_icon('plus'); ?>Add New FAQ
                    </a>
                </div>
                <div class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card overflow-hidden">
                    <div class="border-b border-gray-200 px-5 py-3 flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-3 text-sm text-gray-500 mr-auto">
                            <?php echo trcppr_patient_faq_status_link('all', 'All', ($counts->publish ?? 0) + ($counts->draft ?? 0), $status_filter); ?>
                            <span class="text-gray-300">|</span>
                            <?php echo trcppr_patient_faq_status_link('publish', 'Published', $counts->publish ?? 0, $status_filter); ?>
                            <span class="text-gray-300">|</span>
                            <?php echo trcppr_patient_faq_status_link('trash', 'Trash', $counts->trash ?? 0, $status_filter); ?>
                        </div>
                        <form method="get" class="tr-faq-search relative">
                            <input type="hidden" name="page" value="ci_patient_faq">
                            <?php if ($status_filter != 'all') : ?>
                                <input type="hidden" name="post_status" value="<?php echo esc_attr($status_filter); ?>">
                            <?php endif; ?>
                            <input type="hidden" name="orderby" value="<?php echo esc_attr($sort); ?>">
                            <input type="hidden" name="order" value="<?php echo esc_attr(strtolower($order_dir)); ?>">
                            <?php echo trcppr_patient_faq_icon('search', 'absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400'); ?>
                            <input class="border flex min-w-0 rounded-md px-3 py-1 bg-input-background outline-none pl-9 w-56 text-sm h-9" name="s" placeholder="Search FAQs..." value="<?php echo esc_attr($search); ?>">
                        </form>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50">
                                    <th class="w-10 px-4 py-3"><input type="checkbox" class="w-4 h-4 rounded border-gray-300 text-[#D83D00] focus:ring-[#D83D00]"></th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700"><?php echo trcppr_patient_faq_sort_link('title', 'Title', $sort, strtolower($order_dir)); ?></th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700 cursor-pointer select-none whitespace-nowrap w-24"><?php echo trcppr_patient_faq_sort_link('order', 'Order', $sort, strtolower($order_dir)); ?></th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700 w-28"><?php echo trcppr_patient_faq_sort_link('status', 'Status', $sort, strtolower($order_dir)); ?></th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-700 cursor-pointer select-none whitespace-nowrap w-36"><?php echo trcppr_patient_faq_sort_link('date', 'Date', $sort, strtolower($order_dir)); ?></th>
                                    <th class="px-4 py-3 w-12"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if ($query->have_posts()) : ?>
                                    <?php while ($query->have_posts()) : $query->the_post();
                                        $post_id = get_the_ID();
                                        $order = get_post_meta($post_id, '_order', true);
                                        $edit_url = admin_url('admin.php?page=ci_patient_faq_add&faq_id=' . $post_id);
                                        $is_trash_view = $status_filter == 'trash';
                                        $row_action_url = $is_trash_view
                                            ? wp_nonce_url(admin_url('admin.php?page=ci_patient_faq&post_status=trash&faq_action=delete&faq_id=' . $post_id), 'delete_patient_faq_' . $post_id)
                                            : wp_nonce_url(admin_url('admin.php?page=ci_patient_faq&faq_action=trash&faq_id=' . $post_id), 'trash_patient_faq_' . $post_id);
                                        $is_published = get_post_status($post_id) == 'publish';
                                    ?>
                                        <tr class="group hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-3"><input type="checkbox" class="w-4 h-4 rounded border-gray-300 text-[#D83D00] focus:ring-[#D83D00]"></td>
                                            <td class="px-4 py-3">
                                                <a href="<?php echo esc_url($edit_url); ?>" class="font-medium text-[#3C3D59] hover:text-[#D83D00] transition-colors text-left"><?php echo esc_html(get_the_title()); ?></a>
                                                <div class="flex items-center gap-3 mt-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <a href="<?php echo esc_url($edit_url); ?>" class="text-xs text-[#D83D00] hover:underline">Edit</a>
                                                    <span class="text-gray-300">|</span>
                                                    <a href="<?php echo esc_url($row_action_url); ?>" class="tr-faq-trash-link text-xs text-red-500 hover:underline" data-action="<?php echo esc_attr($is_trash_view ? 'delete' : 'trash'); ?>"><?php echo esc_html($is_trash_view ? 'Delete' : 'Trash'); ?></a>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-gray-700"><?php echo esc_html($order !== '' ? $order : 0); ?></td>
                                            <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $is_published ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'; ?>"><?php echo esc_html($is_published ? 'Published' : 'Draft'); ?></span></td>
                                            <td class="px-4 py-3 text-gray-500 text-xs"><?php echo esc_html(get_the_date('Y/m/d')); ?></td>
                                            <td class="px-4 py-3"><a href="<?php echo esc_url($edit_url); ?>" class="p-1.5 rounded-lg text-gray-400 hover:text-[#D83D00] hover:bg-gray-100 transition-colors inline-flex" title="Edit"><?php echo trcppr_patient_faq_icon('pencil'); ?></a></td>
                                        </tr>
                                    <?php endwhile; wp_reset_postdata(); ?>
                                <?php else : ?>
                                    <tr><td colspan="6" class="px-4 py-3 text-gray-500 text-sm">No FAQs found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-gray-100 px-5 py-3 text-xs text-gray-400"><?php echo esc_html($query->post_count); ?> items</div>
                </div>
            </div>
        </div>
    </div>
    <script>
        jQuery(function($) {
            <?php if ($trashed) : ?>
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'FAQ moved to trash.',
                    confirmButtonColor: '#D83D00'
                });
            } else {
                alert('FAQ moved to trash.');
            }
            <?php endif; ?>
            <?php if ($deleted) : ?>
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'FAQ deleted permanently.',
                    confirmButtonColor: '#D83D00'
                });
            } else {
                alert('FAQ deleted permanently.');
            }
            <?php endif; ?>

            $('.tr-faq-admin').on('click', '.tr-faq-trash-link', function(e) {
                var url = $(this).attr('href');
                var action = $(this).data('action') || 'trash';
                var isDelete = action === 'delete';
                if (typeof Swal === 'undefined') {
                    if (!confirm(isDelete ? 'Delete this FAQ permanently?' : 'Move this FAQ to trash?')) {
                        e.preventDefault();
                    }
                    return;
                }

                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: isDelete ? 'Delete this FAQ permanently?' : 'Move this FAQ to trash?',
                    text: isDelete ? 'This action cannot be undone.' : 'You can still restore it from trash if needed.',
                    showCancelButton: true,
                    confirmButtonText: isDelete ? 'Delete' : 'Move to trash',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#D83D00',
                    cancelButtonColor: '#6b7280'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        window.location.href = url;
                    }
                });
            });
        });
    </script>
<?php
}

function trcppr_render_patient_faq_form_page()
{
    if (!current_user_can('edit_posts')) {
        wp_die(__('You do not have permission to access this page.'));
    }

    $post_id = isset($_GET['faq_id']) ? absint($_GET['faq_id']) : 0;
    $post = $post_id ? get_post($post_id) : null;
    if ($post && $post->post_type !== 'help_support') {
        wp_die(__('Invalid FAQ.'));
    }

    $updated = false;
    $save_error = '';
    if (isset($_POST['tr_patient_faq_nonce']) && wp_verify_nonce($_POST['tr_patient_faq_nonce'], 'save_patient_faq')) {
        $title = sanitize_text_field(wp_unslash($_POST['faq_question'] ?? ''));
        $content = wp_kses_post(wp_unslash($_POST['faq_answer'] ?? ''));
        $order = intval($_POST['faq_order'] ?? 0);
        $status = sanitize_key($_POST['faq_status'] ?? 'publish');
        $status = in_array($status, array('publish', 'draft'), true) ? $status : 'publish';

        $data = array(
            'post_type' => 'help_support',
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => $status,
        );
        if ($post_id) {
            $data['ID'] = $post_id;
            wp_update_post($data);
        } else {
            $post_id = wp_insert_post($data);
        }
        if ($post_id && !is_wp_error($post_id)) {
            update_post_meta($post_id, '_order', $order);
            update_post_meta($post_id, '_is_edit_help_support', 1);
            $updated = true;
        } else {
            $save_error = is_wp_error($post_id) ? $post_id->get_error_message() : 'Unable to save FAQ.';
        }
    }

    $post = $post_id ? get_post($post_id) : null;
    $title = $post ? $post->post_title : '';
    $content = $post ? $post->post_content : '';
    $status = $post ? $post->post_status : 'publish';
    $order = $post_id ? get_post_meta($post_id, '_order', true) : '';
    if ($order === '') {
        $max_order = new WP_Query(array('post_type' => 'help_support', 'post_status' => array('publish', 'draft'), 'posts_per_page' => 1, 'meta_key' => '_order', 'orderby' => 'meta_value_num', 'order' => 'DESC'));
        $order = $max_order->have_posts() ? intval(get_post_meta($max_order->posts[0]->ID, '_order', true)) + 1 : 1;
    }

    trcppr_patient_faq_admin_styles();
?>
    <div class="tr-faq-admin">
        <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=ci_patient_faq_add' . ($post_id ? '&faq_id=' . $post_id : ''))); ?>" class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
            <?php wp_nonce_field('save_patient_faq', 'tr_patient_faq_nonce'); ?>
            <div class="space-y-6 pb-16">
                <div class="flex items-center gap-3">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ci_patient_faq')); ?>" class="flex items-center gap-1.5 text-sm text-gray-500 hover:text-[#D83D00] transition-colors"><?php echo trcppr_patient_faq_icon('arrow-left'); ?>Back to list</a>
                    <span class="text-gray-300">/</span>
                    <h1 class="text-2xl font-semibold text-[#3C3D59]"><?php echo esc_html($post_id ? 'Edit FAQ' : 'Add New FAQ'); ?></h1>
                </div>
                <div class="grid lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2">
                        <div class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                            <div class="grid px-6 pt-6">
                                <h4 class="leading-none">Question &amp; Answer</h4>
                            </div>
                            <div class="px-6 space-y-5" style="padding-bottom:24px">
                                <div class="space-y-1.5">
                                    <label class="flex items-center gap-2 font-medium text-xs text-gray-600" for="faq-question">Question</label>
                                    <input class="border flex h-9 w-full min-w-0 rounded-md px-3 py-1 bg-input-background outline-none text-sm" id="faq-question" name="faq_question" placeholder="Enter the FAQ question" value="<?php echo esc_attr($title); ?>" required>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="flex items-center gap-2 font-medium text-xs text-gray-600">Answer</label>
                                    <div class="faq-editor">
                                        <?php
                                        wp_editor($content, 'faq_answer', array(
                                            'textarea_name' => 'faq_answer',
                                            'media_buttons' => false,
                                            'textarea_rows' => 10,
                                            'teeny' => false,
                                        ));
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-5">
                        <div class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                            <div class="grid px-6 pt-6"><h4 class="leading-none">Order</h4></div>
                            <div class="px-6" style="padding-bottom:24px">
                                <input type="number" class="border flex h-9 min-w-0 rounded-md px-3 py-1 bg-input-background outline-none w-full text-sm" name="faq_order" min="1" value="<?php echo esc_attr($order); ?>">
                                <p class="text-xs text-gray-500 mt-2">Controls the display order on the patient portal.</p>
                            </div>
                        </div>
                        <div class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                            <div class="grid px-6 pt-6"><h4 class="leading-none">Publish</h4></div>
                            <div class="px-6 space-y-4" style="padding-bottom:24px">
                                <div class="space-y-1.5">
                                    <label class="flex items-center gap-2 font-medium text-xs text-gray-600">Status</label>
                                    <div class="flex gap-2">
                                        <label class="flex-1"><input type="radio" name="faq_status" value="publish" <?php checked($status, 'publish'); ?> hidden><span class="inline-flex justify-center w-full py-2 text-sm font-medium rounded-full border transition-all <?php echo $status == 'publish' ? 'bg-[#D83D00] text-white border-[#D83D00]' : 'bg-white text-gray-600 border-gray-300 hover:border-[#D83D00]'; ?>">published</span></label>
                                        <label class="flex-1"><input type="radio" name="faq_status" value="draft" <?php checked($status, 'draft'); ?> hidden><span class="inline-flex justify-center w-full py-2 text-sm font-medium rounded-full border transition-all <?php echo $status == 'draft' ? 'bg-[#D83D00] text-white border-[#D83D00]' : 'bg-white text-gray-600 border-gray-300 hover:border-[#D83D00]'; ?>">draft</span></label>
                                    </div>
                                </div>
                                <button class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all h-9 px-4 py-2 w-full bg-[#D83D00] hover:bg-[#c03600] text-white" type="submit">Publish</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <script>
        jQuery(function($) {
            <?php if ($updated || isset($_GET['updated'])) : ?>
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'FAQ saved.',
                    confirmButtonColor: '#D83D00'
                });
            } else {
                alert('FAQ saved.');
            }
            <?php endif; ?>
            <?php if ($save_error) : ?>
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Unable to save FAQ',
                    text: <?php echo wp_json_encode($save_error); ?>,
                    confirmButtonColor: '#D83D00'
                });
            } else {
                alert(<?php echo wp_json_encode('Unable to save FAQ: ' . $save_error); ?>);
            }
            <?php endif; ?>
            $('.tr-faq-admin input[name="faq_status"]').on('change', function() {
                var selected = $(this).val();
                $('.tr-faq-admin input[name="faq_status"]').each(function() {
                    var $span = $(this).next('span');
                    if ($(this).val() === selected) {
                        $span.removeClass('bg-white text-gray-600 border-gray-300').addClass('bg-[#D83D00] text-white border-[#D83D00]');
                    } else {
                        $span.removeClass('bg-[#D83D00] text-white border-[#D83D00]').addClass('bg-white text-gray-600 border-gray-300');
                    }
                });
            });
        });
    </script>
<?php
}

add_action('admin_head', 'trcppr_render_help_support_admin_ui');
function trcppr_render_help_support_admin_ui()
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== 'help_support') {
        return;
    }

    $is_list = $screen->base === 'edit';
    $is_edit = in_array($screen->base, array('post', 'post-new'), true);
?>
    <style>
        body.post-type-help_support #wpcontent {
            background: #f8f9fa;
        }
        body.post-type-help_support #wpbody-content {
            padding: 24px 32px 80px;
            box-sizing: border-box;
        }
        body.post-type-help_support .wrap {
            margin: 0;
        }
        body.post-type-help_support .wrap h1.wp-heading-inline,
        body.post-type-help_support .wrap h1 {
            color: #3C3D59;
            font-size: 24px;
            font-weight: 600;
            line-height: 32px;
            letter-spacing: 0;
            margin: 0 0 24px;
        }
        body.post-type-help_support .page-title-action,
        body.post-type-help_support #publish,
        body.post-type-help_support .button-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 0 16px;
            border: 0;
            border-radius: 7px;
            background: #D83D00;
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            line-height: 1;
            box-shadow: none;
            text-shadow: none;
            transition: background-color .15s ease;
        }
        body.post-type-help_support .page-title-action:hover,
        body.post-type-help_support #publish:hover,
        body.post-type-help_support .button-primary:hover {
            background: #c03600;
            color: #fff;
        }
        body.post-type-help_support .subsubsub {
            margin: 0;
            color: #6b7280;
            font-size: 13px;
        }
        body.post-type-help_support .subsubsub a {
            color: #6b7280;
        }
        body.post-type-help_support .subsubsub .current,
        body.post-type-help_support .subsubsub .count {
            color: #111827;
            font-weight: 500;
        }
        body.post-type-help_support .search-box input[name="s"],
        body.post-type-help_support input[type="search"],
        body.post-type-help_support input[type="text"],
        body.post-type-help_support input[type="number"] {
            min-height: 36px;
            border: 1px solid #e5e7eb;
            border-radius: 7px;
            background: #f8f9fb;
            color: #111827;
            box-shadow: none;
            font-size: 13px;
        }
        body.post-type-help_support .wp-list-table {
            overflow: hidden;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
        }
        body.post-type-help_support .wp-list-table thead th,
        body.post-type-help_support .wp-list-table tfoot th {
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            color: #374151;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.4;
            padding: 12px 14px;
        }
        body.post-type-help_support .wp-list-table tbody td,
        body.post-type-help_support .wp-list-table tbody th {
            border-bottom: 1px solid #f3f4f6;
            color: #4b5563;
            font-size: 13px;
            padding: 12px 14px;
            vertical-align: middle;
        }
        body.post-type-help_support .wp-list-table tbody tr:hover td,
        body.post-type-help_support .wp-list-table tbody tr:hover th {
            background: #f9fafb;
        }
        body.post-type-help_support .wp-list-table .column-title strong a {
            color: #3C3D59;
            font-weight: 600;
        }
        body.post-type-help_support .wp-list-table .column-title strong a:hover,
        body.post-type-help_support .row-actions a {
            color: #D83D00;
        }
        body.post-type-help_support .column-help_support_order {
            width: 92px;
        }
        body.post-type-help_support .column-date {
            width: 150px;
            color: #6b7280;
        }
        body.post-type-help_support .tablenav {
            min-height: 0;
            margin: 0;
            padding: 12px 16px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-bottom: 0;
            border-radius: 12px 12px 0 0;
        }
        body.post-type-help_support .tablenav.bottom {
            border: 0;
            background: transparent;
        }
        body.post-type-help_support .search-box {
            margin: -48px 16px 12px 0;
        }
        body.post-type-help_support #titlediv #title {
            height: 40px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            background: #f8f9fb;
            box-shadow: none;
            color: #111827;
            font-size: 14px;
            padding: 8px 12px;
        }
        body.post-type-help_support #poststuff {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 320px;
            gap: 24px;
            padding-top: 0;
        }
        body.post-type-help_support #post-body.columns-2 {
            display: contents;
        }
        body.post-type-help_support #post-body-content,
        body.post-type-help_support #postbox-container-1 {
            float: none;
            width: auto;
            margin: 0;
        }
        body.post-type-help_support #post-body-content,
        body.post-type-help_support .postbox {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
        }
        body.post-type-help_support #post-body-content {
            padding: 24px;
        }
        body.post-type-help_support #post-body-content::before {
            content: "Question & Answer";
            display: block;
            margin-bottom: 20px;
            color: #111827;
            font-size: 16px;
            font-weight: 600;
            line-height: 1;
        }
        body.post-type-help_support #titlediv::before {
            content: "Question";
            display: block;
            margin-bottom: 6px;
            color: #4b5563;
            font-size: 12px;
            font-weight: 600;
        }
        body.post-type-help_support #wp-content-wrap::before {
            content: "Answer";
            display: block;
            margin: 18px 0 6px;
            color: #4b5563;
            font-size: 12px;
            font-weight: 600;
        }
        body.post-type-help_support .wp-editor-container {
            overflow: hidden;
            border: 1px solid #d1d5db;
            border-radius: 9px;
            box-shadow: none;
        }
        body.post-type-help_support .quicktags-toolbar,
        body.post-type-help_support div.mce-toolbar-grp {
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }
        body.post-type-help_support .postbox-header {
            border-bottom: 0;
            padding: 20px 20px 0;
        }
        body.post-type-help_support .postbox-header h2 {
            color: #111827;
            font-size: 16px;
            font-weight: 600;
        }
        body.post-type-help_support .postbox .inside {
            padding: 16px 20px 20px;
            margin: 0;
        }
        body.post-type-help_support #minor-publishing,
        body.post-type-help_support #misc-publishing-actions {
            display: none;
        }
        body.post-type-help_support #major-publishing-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
            border-top: 0;
            background: transparent;
            padding: 0;
        }
        body.post-type-help_support #delete-action,
        body.post-type-help_support #publishing-action {
            float: none;
            width: 100%;
            text-align: left;
        }
        body.post-type-help_support #publish {
            width: 100%;
        }
        body.post-type-help_support #help_support_order .inside input {
            width: 100% !important;
        }
        @media (max-width: 960px) {
            body.post-type-help_support #wpbody-content {
                padding: 18px;
            }
            body.post-type-help_support #poststuff {
                grid-template-columns: 1fr;
            }
            body.post-type-help_support .search-box {
                float: none;
                margin: 12px 0;
            }
        }
    </style>
    <script>
        jQuery(function($) {
            <?php if ($is_list) : ?>
            $('.wrap h1.wp-heading-inline').text('Patient FAQ');
            $('.page-title-action').html('<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> Add New FAQ');
            $('.search-box input[name="s"]').attr('placeholder', 'Search FAQs...');
            <?php endif; ?>

            <?php if ($is_edit) : ?>
            $('.wrap h1.wp-heading-inline, .wrap h1').first().text('Add New FAQ');
            $('#title-prompt-text').text('Enter the FAQ question');
            $('#publish').val('Publish');
            <?php endif; ?>
        });
    </script>
<?php
}
