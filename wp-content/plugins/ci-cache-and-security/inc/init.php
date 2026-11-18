<?php
if (! defined('ABSPATH')) exit; // Exit if accessed directly

global $blog_id, $tr_cache_blog_options, $tr_security;

include(TRSCSC_PATH . 'inc/tr_security_class.php');
$tr_security = new Tr_Security_Class();


//include(TRSCSC_PATH . 'inc/two_factor_auth.php');

if (isset($tr_cache_blog_options[$blog_id]) && !function_exists('tr_cache_exit')) {
    if (!@$tr_cache_blog_options[$blog_id]['in_init']) {
        include_once(TRSCSC_PATH . 'cache.php');
    } else {
        add_action('plugins_loaded', 'tr_cache_load_plugins_loaded', 2);
        function tr_cache_load_plugins_loaded()
        {
            global $blog_id, $tr_cache_blog_options;
            include_once(TRSCSC_PATH . 'cache.php');
        }
    }
}

add_action('login_head', 'trcache_data_login_head', 999);
add_filter('pre_http_request', 'tr_pre_http_request2', 99, 3);
add_action('init', 'tr_cache_plugin_init', 9);
add_action('cache_security', 'tr_cache_security_clean');
add_action('wp_insert_comment', 'tr_wp_insert_comment', 10, 2);
add_action('validate_password_reset', 'tr_validate_password_reset', 10, 2);
add_action('save_post', 'tr_wp_save_post_cache', 10, 1);
add_action('delete_cache', 'tr_wp_insert_comment', 10, 1);
add_action('wp_head', 'trcs_wp_head');
add_action('wp_footer', 'trcs_wp_footer');
add_filter('wp_mail_from',  'trcs_filter_get_from_address');
add_filter('wp_mail_from_name', 'trcs_filter_get_from_name');
add_action('upgrader_process_complete', 'trcs_flush_opcache_after_wp_update');


add_action('save_post', 'tr_cache_invalidate_post');
//add_action('save_post', 'tr_cache_sync_post_images', 99,2);
add_action('delete_post', 'tr_cache_sync_delete_post_images');
add_action('cron_sync_post_images', 'tr_cache_cron_sync_post_images');

add_action('publish_post', 'tr_cache_invalidate_post');
add_action('delete_post', 'tr_cache_invalidate_post');

if (defined('TRCPDD_FILE')) {
    add_action('template_redirect', function () {
        // Redirect ?author=N query string
        if (isset($_GET['author'])) {
            wp_redirect(home_url('/'), 301);
            exit;
        }

        // Redirect author archive pages (e.g. /author/username/)
        if (is_author()) {
            wp_redirect(home_url('/'), 301);
            exit;
        }
    });
}



register_activation_hook(TRSCSC_FILE, 'tr_cache_security_activate');
register_deactivation_hook(TRSCSC_FILE, 'tr_cache_security_deactivate');

function tr_cache_plugin_init()
{
    global $wpdb;
    global $blog_id;
    global $settings_imagealts;
    $user_id         = get_current_user_id();

    if (defined('DOING_CRON')) {
        ob_start(function ($rs) {
            return '1';
        });
    }
    if (!is_admin()) {
        if (!is_user_logged_in())
            add_filter('show_admin_bar', '__return_false', 99);

        global $tr_cache_options;
        if (!is_array($tr_cache_options))
            $tr_cache_options = get_option('trcs_cache', array());

        if ((isset($tr_cache_options['optimize_js']) && $tr_cache_options['optimize_js'])
            || (isset($tr_cache_options['optimize_css']) && $tr_cache_options['optimize_css'])
        ) {
            $cache_obj = Tr_Cache_Class::instance();
            if (!Tr_Cache_Class::$has_run)
                add_action('template_redirect', array(&$cache_obj, 'template_redirect'), 1);
            if ($tr_cache_options['add_script_sync']) {
                add_filter('script_loader_tag', array(&$cache_obj, 'script_loader_tag'), 11, 3);
            }
        }

        if ((isset($tr_cache_options['add_style_bottom']) && $tr_cache_options['add_style_bottom']) || (isset($tr_cache_options['on']) && $tr_cache_options['on'] && tr_cache_is_mobile())) {
            remove_action('wp_head', 'wp_print_styles', 8);
        }
        if (isset($tr_cache_options['optimize_js_footer']) && $tr_cache_options['optimize_js_footer']) {
            //remove_action( 'wp_head','wp_print_head_scripts',9 );
        }
        if (isset($tr_cache_options['on']) && $tr_cache_options['on']) {
            add_action('admin_bar_menu', 'tr_cache_admin_bar_menu', 99);
        }

        $settings_imagealts = get_option('trfix_imagealts', array());
        if (isset($settings_imagealts['enable']) && $settings_imagealts['enable']) {
            add_filter('the_content', 'tr_cs_filter_the_content', 99);
        }
        add_action('template_redirect', 'tr_template_redirect_check_404', 1);
    }

    $session_timeout = tr_get_option('tr_security', 'session_timeout', 0);
    $session_timeout = $session_timeout > 0 ? $session_timeout * 60 : 20 * 60;

    if ($user_id > 0) {
        $user_token     = hash('sha256', wp_get_session_token());

        $lastact = $wpdb->get_row("select * from ci_user_last_activity where user_ID={$user_id} and token='{$user_token}'");
        $last_active = 0;
        if ($lastact) {
            $last_active = (int)$lastact->last_activity;
        }

        if (isset($_GET['debug_last_activity'])) {
            echo 'user#' . $user_id . ' / token: ' . wp_get_session_token() . ' / ';
            var_dump($user_token);
            echo '<br>';
            echo 'last active: ';
            var_dump($last_active);
            echo '<br>last active: ';
            echo date('Y-m-d H:i:s', $last_active);
            echo 'Left time: ';
            var_dump(($last_active + $session_timeout) - time());
            if (function_exists('trpdd_display_time_duration')) {
                echo trpdd_display_time_duration(($last_active + $session_timeout) - time(), false);
            }

            do_action('trpp_auto_logout_event');
            exit;
        }
        $is_outtime = $last_active > 0 && $last_active < time() - $session_timeout;
        $need_auto_logout = false;
        $logout_redirect_url = '';
        if ($is_outtime && empty($_POST) && !isset($_REQUEST['trajax']) && !isset($_REQUEST['tr_action'])) {
            $need_auto_logout = true;
            if (is_admin()) {
                $logout_redirect_url = wp_login_url($_SERVER['REQUEST_URI']);
            } else {
                $logout_redirect_url = $_SERVER['REQUEST_URI'];
            }
        }

        $doingajax = defined('DOING_AJAX') && !isset($_COOKIE['trquery_monitor']);
        if ($doingajax && $last_active > 0) {
            $need_auto_logout = $is_outtime;
        } else {

            if ($lastact) {
                $wpdb->update(
                    'ci_user_last_activity',
                    array(
                        'last_activity' => time()
                    ),
                    array(
                        'user_ID' => $user_id,
                        'token' => $user_token
                    )
                );
            } else {
                $wpdb->insert(
                    'ci_user_last_activity',
                    array(
                        'user_ID' => $user_id,
                        'blog_id' => $blog_id,
                        'token' => $user_token,
                        'last_activity' => $last_active > 0 ? $last_active : time()
                    )
                );
            }
        }

        if ($need_auto_logout) {
            $outtime = time() - $last_active;
            if (function_exists('trpdd_log_user')) {
                if ($outtime > $session_timeout) {
                    $outtime = $session_timeout;
                }
                $outtime = function_exists('trpdd_display_time_duration') ? trpdd_display_time_duration($outtime, false) : $outtime;
                trpdd_log_user($user_id, 'logout', 'auto-logout: ' . $outtime);
            }

            wp_logout();
            $wpdb->delete('ci_user_last_activity', array(
                'user_ID' => $user_id,
                'token' => $user_token
            ));
            if (!empty($logout_redirect_url) && empty($_POST)) {
                wp_safe_redirect($logout_redirect_url);
                exit;
            }
        }
    }
}

function tr_cache_admin_bar_menu($wp_admin_bar)
{
    $wp_admin_bar->add_menu(array(
        'id'     => 'ci_clear_cache',
        'title'  => __('Clear Cache'),
        'href'   => '#',
    ));
    $wp_admin_bar->add_menu(array(
        'parent'     => 'ci_clear_cache',
        'id'         => 'ci_clear_cache_s',
        'title'  => __('Clear This Page'),
        'href'   => '?tr_action=ci_clear_cache_page',
    ));
    $wp_admin_bar->add_menu(array(
        'parent'     => 'ci_clear_cache',
        'id'         => 'ci_clear_cache_all',
        'title'  => __('Clear All'),
        'href'   => '?tr_action=ci_clear_cache',
    ));
}

function tr_cache_is_mobile()
{
    $useragent = $_SERVER['HTTP_USER_AGENT'];
    return preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $useragent) || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($useragent, 0, 4));
}

function tr_cache_security_activate($only_setup_table = false)
{
    include_once(TRSCSC_PATH . 'inc/admin_actions.php');
    tradmin_action_flush_opcache_reset();
    include(TRSCSC_PATH . 'inc/install.php');
}

function tr_cache_security_deactivate()
{
    wp_clear_scheduled_hook('cache_security');

    // burn the file without delete it so one can rewrite it
    include_once(TRSCSC_PATH . 'inc/admin_actions.php');
    tradmin_action_generate_config(true);
}

function tr_template_redirect_check_404()
{
    if (is_404()) {
        include(TRSCSC_PATH . 'inc/404.php');
    }
}

function tr_cache_security_clean()
{
    include(TRSCSC_PATH . 'inc/cache_security_clean.php');
}

function tr_wp_insert_comment($id, $comment = '')
{
    global $blog_id;
    if ($comment) {
        $post_id = $comment->comment_post_ID;
    } else {
        $post_id = $id;
    }

    $link = get_permalink($post_id) . $blog_id;
    $link = preg_replace('~^.*?://~', '', $link);
    $file = md5($link);
    $filename = $file . '.dat';
    $mobilefile = $file . '_mobile.dat';
    if (file_exists(TRSCSC_CACHE_PATH . '/' . $filename))
        @unlink(TRSCSC_CACHE_PATH . '/' . $filename);
    if (file_exists(TRSCSC_CACHE_PATH . '/' . $mobilefile))
        @unlink(TRSCSC_CACHE_PATH . '/' . $mobilefile);
    if (file_exists(TRSCSC_CACHE_PATH . '/' . $filename . 's'))
        @unlink(TRSCSC_CACHE_PATH . '/' . $filename . 's');
    if (file_exists(TRSCSC_CACHE_PATH . '/' . $mobilefile . 's'))
        @unlink(TRSCSC_CACHE_PATH . '/' . $mobilefile . 's');

    @touch(TRSCSC_CACHE_PATH . '/_archives.dat');
}
function tr_wp_save_post_cache($post_id)
{
    @touch(TRSCSC_CACHE_PATH . '/_archives.dat');
    @touch(TRSCSC_CACHE_PATH . '/_category.dat');
}

function tr_validate_password_reset($errors, $user)
{
    global $tr_security;
    if ($errors->get_error_code() == '') {
        $tr_security->allow_user_login($user);
    }
}

if (!function_exists('wp_is_writable')) {
    function wp_is_writable($path)
    {
        if ('WIN' === strtoupper(substr(PHP_OS, 0, 3)))
            return win_is_writable($path);
        else
            return @is_writable($path);
    }
}


function tr_pre_http_request2($return, $r, $url)
{
    if (tr_get_option('tr_security', 'disable_call_wp_api') && stripos($_SERVER['REQUEST_URI'], 'plugins.php') === false) {
        if (strpos($url, 'version.wpbakery.com') > 0) {
            return true;
        } else if (strpos($url, 'themes/update-check') > 0) {
            return true;
        } else if (strpos($url, 'adminmenueditor') > 0) {
            return true;
        } else if (strpos($url, 'yithemes.com') > 0) {
            return true;
        }
    }
    return $return;
}


function trcs_wp_head()
{
    $code = tr_get_option('trcs_otherst', 'header_code');
    echo stripslashes($code);
}
function trcs_wp_footer()
{
    $code = tr_get_option('trcs_otherst', 'footer_code');
    echo stripslashes($code);
}

function trcs_get_cache_class()
{
    include_once(TRSCSC_PATH . 'inc/tr_cache_query.php');
    return new Tr_Cache_Query();
}


function trcache_data_login_head()
{
    include(TRSCSC_PATH . 'pages/login_head.php');
}

function trcs_filter_get_from_address($from_address)
{
    if (stripos($from_address, 'wordpress') !== false) {
        $email = tr_get_option('trcs_otherst', 'from_email');
        if (is_email($email)) {
            $from_address = $email;
        } else {
            $from_address = get_bloginfo('admin_email');
        }
    }
    return $from_address;
}

function trcs_filter_get_from_name($from_name)
{
    if (stripos($from_name, 'wordpress') !== false) {
        $name = tr_get_option('trcs_otherst', 'from_name');
        if (!empty($name)) {
            $from_name = $name;
        } else {
            $from_name = get_bloginfo('blogname');
        }
    }
    return $from_name;
}


function tr_cs_filter_the_content($content, $alt_text = '', $title = '')
{
    global $post, $settings_imagealts;

    $count = preg_match_all('/<img[^>]+>/i', $content, $images);

    if ($count > 0) {
        if (!$settings_imagealts) {
            $settings_imagealts = get_option('trfix_imagealts', array());
        }
        $alt_text = $settings_imagealts['alt_value'];
        foreach ($images[0] as $img) {
            if (stripos($img, 'alt') > 0) {
                continue;
            }
            $output = preg_match_all('/<img[^>]+src=[\'"]([^\'"]+)[\'"].*>/i', $img, $matches);
            $get_file_name = pathinfo($matches[1][0]);
            $image_file_name = $get_file_name['filename'];

            $postcategories = get_the_category();
            $post_category = '';
            if ($postcategories) {
                foreach ($postcategories as $category) {
                    $post_category .= $category->name . ' ';
                }
            }

            $alt_text = str_replace('%title', $post->post_title, $alt_text);
            $alt_text = str_replace('%name', $image_file_name, $alt_text);
            $alt_text = str_replace('%category', $post_category, $alt_text);

            $alt_text = trim($alt_text);
            $alt_text = trim($alt_text, '\s\-');
            $alt_text = trim($alt_text);

            $t = ' alt="' . esc_attr($alt_text) . '" ';
            $new_img = str_replace('<img ', '<img ' . $t, $img);
            $content = str_replace($img, $new_img, $content);
        }
    }
    return $content;
}

function trcs_flush_opcache_after_wp_update()
{
    include_once(TRSCSC_PATH . 'inc/admin_actions.php');
    tradmin_action_flush_opcache_reset();
}


function tr_cache_invalidate_post($post_id)
{
    global $tr_invalidated_post_id, $blog_id;

    if ($tr_invalidated_post_id == $post_id) {
        return;
    }
    if (false !== (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id))) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $post_id;
    }

    $link = get_permalink($post_id);
    $link = preg_replace('~^.*?://~', '', $link) . $blog_id;
    $file = md5($link);
    $filename = $file . '.dat';
    $mobilefile = $file . '_mobile.dat';
    if (file_exists(TRSCSC_CACHE_PATH . '/' . $filename))
        @unlink(TRSCSC_CACHE_PATH . '/' . $filename);
    if (file_exists(TRSCSC_CACHE_PATH . '/' . $mobilefile))
        @unlink(TRSCSC_CACHE_PATH . '/' . $mobilefile);
    if (file_exists(TRSCSC_CACHE_PATH . '/' . $filename . 's'))
        @unlink(TRSCSC_CACHE_PATH . '/' . $filename . 's');
    if (file_exists(TRSCSC_CACHE_PATH . '/' . $mobilefile . 's'))
        @unlink(TRSCSC_CACHE_PATH . '/' . $mobilefile . 's');
    $tr_invalidated_post_id = $post_id;

    @touch(TRSCSC_CACHE_PATH . '/_archives.dat');

    $home_url = $_SERVER['HTTP_HOST'] . '/' . $blog_id;
    $home_name = md5($home_url);
    @unlink(TRSCSC_CACHE_PATH . '/' . $home_name . '.dat');
    @unlink(TRSCSC_CACHE_PATH . '/' . $home_name . '.dats');
    @unlink(TRSCSC_CACHE_PATH . '/' . $home_name . '_mobile.dat');
    @unlink(TRSCSC_CACHE_PATH . '/' . $home_name . '_mobile.dats');
}

function tr_cache_sync_post_images($post_id, $post = null)
{
    global $wpdb;
    if (false !== (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id))) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $post_id;
    }

    try {
        $image_ids = array();
        $imageid = get_post_meta($post_id, '_thumbnail_id', true);
        if ($imageid > 0) {
            $image_ids[$imageid] = $imageid;
        }
        $post_content = ($post) ? $post->post_content : '';
        if (empty($post_content)) {
            $post_content = $wpdb->get_var("select post_content from {$wpdb->posts} where ID = {$post_id} ");
        }

        preg_match_all("/\<img[^>]*src=[\'\"]+([^\'\"]+)[\'\"]+[^>]*\>/", $post_content, $matches);

        if (count($matches[1]) > 0) {
            foreach ($matches[1] as $imageurl) {
                list($imaged, $imageurl) = explode('uploads/', $imageurl);


                //find image id
                $imageid = $wpdb->get_var("select ID from {$wpdb->posts} where post_type='attachment' and guid like '%{$imageurl}'");
                if ($imageid == 0) {
                    $imageid = $wpdb->get_var("select post_id from {$wpdb->postmeta} where meta_value ='{$imageurl}'");
                }
                if ($imageid == 0) {
                    $imageurl = preg_replace("/-[0-9]+x[0-9]+\./", '.', $imageurl);
                    $imageid = $wpdb->get_var("select post_id from {$wpdb->postmeta} where meta_value ='{$imageurl}'");
                }
                if ($imageid > 0) {
                    $image_ids[$imageid] = $imageid;
                }
            }
        }


        if (count($image_ids) > 0) {
            foreach ($image_ids as $imageid) {
                // $wpdb->insert('wp_tr_image_link_posts',array('image_id'=>$imageid,'post_id'=>$post_id));
                $rsc = $wpdb->query("INSERT INTO wp_tr_image_link_posts (image_id,post_id) 
									values ('{$imageid}','{$post_id}') 
									ON DUPLICATE KEY UPDATE image_id = '{$imageid}' ");
            }
            $image_ids_str = implode(',', $image_ids);
        } else {
            $image_ids_str = '-1';
        }
        $wpdb->query("delete from wp_tr_image_link_posts where post_id = {$post_id} and image_id not in ({$image_ids_str})");
    } catch (Exception $e) {
        //  var_dump($e);exit;
    }
}


function tr_cache_sync_delete_post_images($post_id)
{
    global $wpdb;
    $wpdb->query("delete from wp_tr_image_link_posts where post_id = {$post_id} ");
}

function tr_cache_cron_sync_post_images()
{
    include('cron_sync_post_images.php');
}

function trcsc_validate_list_ip($ips)
{
    if (!is_array($ips)) {
        $ips = explode("\n", $ips);
    }
    $return = array();

    foreach ($ips as $ip) {
        $ip = trim($ip);
        $count = count(explode('.', $ip));

        if (empty($ip) || $count != 4) continue;
        $return[] = $ip;
    }
    $return = array_unique($return);
    return $return;
}
add_filter('rest_request_before_callbacks', function ($response, $handler, $request) {

    $route = $request->get_route();

    if (strpos($route, '/wp/v2/users') === 0) {
        return new WP_Error(
            'rest_forbidden',
            'Users endpoint is disabled.',
            ['status' => 403]
        );
    }

    return $response;
}, 10, 3);
add_action('plugins_loaded', function () {


    if (defined('TRCPDD_PATH') || defined('TRHUB_PATH')) {

        add_filter('wp_sitemaps_add_provider', function ($provider, $name) {
            if ($name === 'users') {
                return false;
            }

            return $provider;
        }, 10, 2);


        add_action('template_redirect', function () {
            if (is_author()) {
                wp_die('Access denied', '', [
                    'response' => 403
                ]);
            }
        });


        add_filter('redirect_canonical', function ($redirect_url) {
            return is_author() ? false : $redirect_url;
        });
    }
});

add_action('init', 'trpp_schedule_auto_logout_cron');
function trpp_schedule_auto_logout_cron()
{
    if (!wp_next_scheduled('trpp_auto_logout_event')) {
        wp_schedule_event(time() + 300, '5min', 'trpp_auto_logout_event');
    }
}
add_action('trpp_auto_logout_event', 'trpp_run_auto_logout_cron');
function trpp_run_auto_logout_cron()
{
    include(TRSCSC_PATH . 'inc/tr_auto_logout.php');
    $auto_logout = new TR_Auto_Logout();
    $auto_logout->cron();
    echo nl2br($auto_logout->log);
}
