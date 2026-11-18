<?php
if (! defined('ABSPATH')) exit; // Exit if accessed directly

include_once(TRSCSC_PATH . 'inc/actions.php');

function tradmin_action_need_fix_plugin()
{
    include_once(TRSCSC_PATH . 'inc/need_fix_plugin.php');
    exit;
}

function tradmin_action_removeban()
{
    global $tr_security, $wpdb;
    $ip = $_POST['ip'];
    $tr_security->updatebantime(0, $ip);
    $wpdb->delete('wp_tr_lock_ip', array('ip' => $ip));
    trfront_action_change_htaccess();
    echo 'ok';
    exit;
}

function tradmin_action_clear_cache($path = '', $type = '')
{
    if (empty($path)) {
        $path = TRSCSC_CACHE_PATH;
    }
    if (empty($type)) {
        $type = isset($_POST['type']) ? $_POST['type'] : '';
    }
    if (stripos($path, 'cache') === false) {
        return;
    }
    if ($type == 'js') {

        tr_cache_clear_by_dir(WP_CONTENT_DIR . TRSCSC_CACHE_JS, 0, time() + 999999);
    } else if ($type == 'css') {

        tr_cache_clear_by_dir(WP_CONTENT_DIR . TRSCSC_CACHE_CSS, 0, time() + 999999);
    } else {
        tr_cache_clear_by_dir($path, 0, time() + 999999);
        tr_cache_clear_by_dir(TRSCSC_CACHE_QUERIES, 0, time() + 999999);
    }

    echo 'ok';
    exit();
}

function tradmin_action_generate_config($uninstall = false, $change_ad_c = true)
{
    return trfront_action_generate_config($uninstall, $change_ad_c);
}

function tradmin_action_change_config_file($onlycontent = false, $uninstall = false)
{
    return trfront_action_change_config_file($onlycontent, $uninstall);
}

function tradmin_action_change_htaccess($onlycontent = false, $uninstall = false)
{
    return trfront_action_change_htaccess($onlycontent, $uninstall);
}

function tradmin_action_change_advanced_cache($onlycontent = false, $uninstall = false)
{
    return trfront_action_change_advanced_cache($onlycontent, $uninstall);
}
function tradmin_action_scan_quick_sc()
{
    include_once(TRSCSC_PATH . 'pages/admin_scan_page.php');
}

function tradmin_action_do_scan_quick_sc()
{
    if (!session_id()) session_start();
    $return = array('status' => 'done');
    @set_time_limit(60 * 5);
    include_once(TRSCSC_PATH . 'inc/tr_scan_dir.php');

    $dir = dirname(WP_CONTENT_DIR);
    $options = array(
        'skip_ext' => tr_get_option('tr_security', 'skip')
    );
    $scan = new Tr_Scan_Dir();
    $scan->scan($dir, $options);

    $log = '';
    $threats = $scan->get_log();
    foreach ($threats as $file => $name) {
        $log .= '<li>' . $file . ': ' . $name . '</li>';
    }
    if (count($threats) == 0) {
        $log .= '<h4 class="center">No found files</h4>';
    }
    $return['log'] = $log;
    wp_send_json($return);
}

function tradmin_action_test_scan_dir()
{
    echo 'start...';
    include_once(TRSCSC_PATH . 'inc/tr_scan_dir.php');
    $start_time = microtime(true);
    $scan = new Tr_Scan_Dir();
    $scan->scan_log_time();
    echo microtime(true) - $start_time;
    echo '<br>';
    echo 'count: ' . count($scan->files);
    echo 'ok';
    exit;
}

function tradmin_action_test_sync_log_file_time()
{
    $scan = new Tr_Scan_Dir();
    $rs = $scan->sync_server();
    var_dump($rs);
    echo 'ok';
    exit;
}

function tradmin_action_ci_run_optimize_images()
{
    $return = array('status' => 'ok', 'msg' => 'ok', 'status_msg' => '', 'position' => 1);
    $op = new Tr_Optimize_Image();
    $op->set_size($_REQUEST['max_width'], $_REQUEST['max_height'], $_REQUEST['max_size']);
    $key = 'uploads_images';
    if ($_REQUEST['position'] == 0) {
        $op->make_list(WP_CONTENT_DIR . '/uploads/', $key);
    } else {
        $op->loaddata($key);
    }

    if ($op->total > 0 && $op->total - $_REQUEST['position'] > 0) {
        $rs = $op->optimize($_REQUEST['position']);
        $return['msg'] = '<a href="' . $rs['url'] . '" target="_blank">' . $rs['file'] . '</a>' . ' => ' . (($rs['status']) ? 'ok' : 'error');
    } else {
        $return['status'] = 'done';
    }
    $return['status_msg'] = 'Optimized: ' . $_REQUEST['position'] . ' / total: ' . $op->total;
    $return['position'] = $_REQUEST['position'] + 1;

    wp_send_json($return);
    exit;
}

function tradmin_action_flush_opcache_reset()
{
    if (function_exists('opcache_reset')) {

        // Check if file cache is enabled and delete it if enabled
        if (ini_get('opcache.file_cache') && is_writable(ini_get('opcache.file_cache'))  && class_exists('RecursiveIteratorIterator')) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ini_get('opcache.file_cache'), RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                $todo($fileinfo->getRealPath());
            }
        }

        // Flush OPcache
        opcache_reset();

        // If prewarm option is active
        if (get_option('flush-opcache-preload') == 1 && function_exists('flush_opcache_preload')) {
            flush_opcache_preload();
        }
    }
}

function tradmin_action_ci_save_alt_text()
{
    global $wpdb;

    $return = array('status' => 'ok');
    $id = $_POST['id'];
    $alt = trim($_POST['alt']);
    $post_excerpt = trim($_POST['post_excerpt']);
    if (empty($alt)) {
        $alt = $post_excerpt;
    }
    update_post_meta($id, '_wp_attachment_image_alt', $alt);
    if (isset($_POST['post_excerpt'])) {
        $wpdb->update($wpdb->posts, array('post_excerpt' => $post_excerpt), array("ID" => $id));
    }


    //replace alt for post_content
    $images_sizes = get_intermediate_image_sizes();
    $where_post_content = '';
    $list_image_urls = array();
    foreach ($images_sizes as $sizename) {
        $image = wp_get_attachment_image_src($id, $sizename);
        if (!isset($image[0])) continue;
        list($imaged, $imageurl) = explode('uploads/', $image[0]);
        if (empty($imageurl)) continue;
        $list_image_urls[] = $imageurl;
        $where_post_content .= " or (p.post_content like '%{$imageurl}%') ";
    }

    $query_posts = "select p.* from {$wpdb->posts} p
        where 
            p.post_content != ''
            and p.post_type !='attachment'
            and p.post_type!='customize_changeset'
            and p.post_type!='revision'
            and (1 {$where_post_content})
                
        ";
    $posts = $wpdb->get_results($query_posts);

    foreach ($posts as $p) {
        $post_content = $p->post_content;
        if (empty($post_content)) continue;
        preg_match_all("/\<img[^>]*src=[\'\"]+([^\'\"]+)[\'\"]+[^>]*\>/", $post_content, $matches);
        if (count($matches[1]) > 0) {
            $has_change = false;
            foreach ($matches[1] as $i => $imageurl) {
                list($imaged, $imageurl) = explode('uploads/', $imageurl);
                if (in_array($imageurl, $list_image_urls)) {
                    //need replace alt
                    $has_change = true;
                    $img_tag = $matches[0][$i];
                    if (stripos($img_tag, 'alt=') > 0) {
                        $new_img_tag = preg_replace("/alt=[\'\"]+([^\'\"]+)[\'\"]+/", 'alt="' . $alt . '"', $img_tag);
                    } else {
                        $new_img_tag = str_replace('<img ', '<img alt="' . $alt . '"', $img_tag);
                    }


                    $post_content = str_replace($img_tag, $new_img_tag, $post_content);
                }
            }

            if ($has_change && !empty($post_content)) {
                $wpdb->update($wpdb->posts, array('post_content' => $post_content), array('ID' => $p->ID));
            }
        }
    }

    wp_send_json($return);
}
