<?php
if (! defined('ABSPATH')) exit; // Exit if accessed directly

class Tr_Cache_Query
{
    public $last_key;
    public $hascache = false;
    public $group = '';

    public function __construct($group = '')
    {
        $this->group = $group;
    }

    function _get_cache($key, $limit = 200, $json = true, $output = OBJECT)
    {

        $this->last_key = $key;
        $this->hascache = false;

        $data = false;
        if (isset($_REQUEST['recachequery']) || defined('RECACHEQUERIES')) {
            return false;
        }
        $dir = TRSCSC_CACHE_QUERIES;
        if (!empty($this->group)) {
            $dir .= '/' . $this->group;
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
        }
        $filepath = $dir . '/' . $key;
        $timeout = time() - $limit;
        if (file_exists($filepath) && filemtime($filepath) > $timeout) {
            $data = file_get_contents($filepath);
            if ($json) {
                $data = json_decode($data, $output != OBJECT);
            }
        }
        if ($data) {
            $this->hascache = true;
        }
        return $data;
    }

    function _set_cache($key, $data, $json = true)
    {
        global $wpdb;
        if (!file_exists(TRSCSC_CACHE_QUERIES)) {
            wp_mkdir_p(TRSCSC_CACHE_QUERIES);
        }
        $dir = TRSCSC_CACHE_QUERIES;
        if (!empty($this->group)) {
            $dir .= '/' . $this->group;
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
        }
        $filepath = $dir . '/' . $key;
        if ($data === false || is_null($data)) {
            @unlink($filepath);
            return false;
        }
        if ($json) {
            $data = json_encode($data);
        }

        if (isset($_GET['debugcache'])) {
            echo $key . "\n";
            echo $wpdb->last_query . "\n";
            echo '<br><br>';
        }
        //  file_put_contents(WP_CONTENT_DIR . '/debug_cache_query.txt', $key . "\n" . $wpdb->last_query . "\n\n", FILE_APPEND);
        return file_put_contents($filepath, $data);
    }

    public function get_results($sql, $output = OBJECT, $limit = 200)
    {
        global $wpdb;
        $query_key = md5($sql . $output) . '.get_results';

        //get cache
        $results = $this->_get_cache($query_key, $limit, true, $output);

        if (!is_array($results)) {
            $results = $wpdb->get_results($sql, $output);

            //cache
            $this->_set_cache($query_key, $results);
        }

        return $results;
    }

    public function get_row($sql, $output = OBJECT, $y = 0, $limit = 200)
    {
        global $wpdb;
        $query_key = md5($sql . $output) . '.get_row';

        //get cache
        $results = $this->_get_cache($query_key, $limit, true, $output);

        if (!$results) {
            $results = $wpdb->get_row($sql, $output, $y);

            //cache
            $this->_set_cache($query_key, $results);
        }

        return $results;
    }

    public function get_var($sql, $x = 0, $y = 0, $limit = 200)
    {
        global $wpdb;
        $query_key = md5($sql . $limit) . '.get_var' . $x . $y;

        //get cache
        $results = $this->_get_cache($query_key, $limit, false);

        if ($results === false) {
            $results = $wpdb->get_var($sql, $x, $y);

            //cache
            $this->_set_cache($query_key, $results, false);
        }

        return $results;
    }

    public function get_col($sql, $x = 0, $limit = 200)
    {
        global $wpdb;
        $query_key = md5($sql . $limit) . '.get_col' . $x;

        //get cache
        $results = $this->_get_cache($query_key, $limit);

        if (!$results) {
            $results = $wpdb->get_col($sql, $x);

            //cache
            $this->_set_cache($query_key, $results);
        }

        return $results;
    }

    public function clear_results($sql, $output = OBJECT)
    {
        $query_key = md5($sql . $output) . '.get_results';
        $this->_set_cache($query_key, false);
    }

    public function clear_row($sql, $output = OBJECT, $y = 0)
    {
        $query_key = md5($sql . $output) . '.get_row';
        $this->_set_cache($query_key, false);
    }

    public function clear_var($sql, $x = 0, $y = 0, $limit = 200)
    {
        $query_key = md5($sql . $limit) . '.get_var' . $x . $y;
        $this->_set_cache($query_key, false);
    }

    public function clear_col($sql, $x = 0, $limit = 200)
    {
        $query_key = md5($sql . $limit) . '.get_col' . $x;
        $this->_set_cache($query_key, false);
    }
}
