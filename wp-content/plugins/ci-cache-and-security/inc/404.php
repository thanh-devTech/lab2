<?php
global $tr_cache_options;
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
//check security hide backend
$secu = get_option('tr_security');

if($secu['hide_backend'])
{
    
    $key = get_option('tr_security_admin_key');
    if(stripos($_SERVER['REQUEST_URI'],  '/'.$secu['login_slug'])!==false)
    {
        $loginurl = get_bloginfo('url').'/wp-login.php?'.$key;
        wp_redirect($loginurl);
        exit;
    }
}

//rebuild css

if(stripos($_SERVER['REQUEST_URI'],'wp-content/cache/css/')!==false)
{
    $cache_obj = Tr_Cache_Class::instance();
    
    $file = basename($_SERVER['REQUEST_URI']);
    $file = preg_replace('/\?.*/', '', $file);
   
    $file_md5    = TRSCSC_CACHE_CSS.'/'. $file;
    $file_path   = WP_CONTENT_DIR.'/'.$file_md5;
    $file_info   = str_replace('/css','/cssinfo',$file_path).'.info';

    if(!file_exists($file_info))
    {
        return;
    }
    $styles = json_decode(file_get_contents($file_info),true);

    $data     = '';
    $maindomain = get_bloginfo('url');
    foreach($styles as $handle => $css)
    {
        $css[1] = str_replace($maindomain,'',$css[1]);
        $csscode = $cache_obj->_fix_css(@file_get_contents($css[0]),$css[0],$css[1]);

        if(isset($_GET['debug'])){
            var_dump($css);
            echo "\n";
            echo strlen($csscode);
            echo "\n\n";
        }

        $data.= $csscode."\n";
    }
    if(isset($_GET['debug'])) {
        exit;
    }
    // return $data;
    if(!empty($data))
    {
        // Remove comments
        $data = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $data);
        // Remove space after colons
        $data = str_replace(': ', ':', $data);
        // Remove whitespace

        $data = str_replace(array("\r","\n","\t"),'',$data);
        if($tr_cache_options['optimize_ws_css'])
        {
            $data = $cache_obj->stripWhitespace($data);
            $data = $cache_obj->shortenHex($data);
            $data = $cache_obj->stripEmptyTags($data);
        }
        Tr_Cache_Class::$has_run=true;
        @file_put_contents($file_path,$data);
        header('Content-Type: text/css');
        echo $data;
        exit;
    }
}


if(stripos($_SERVER['REQUEST_URI'],'wp-content/cache/js/')!==false)
{
    $cache_obj = Tr_Cache_Class::instance();

    $file = basename($_SERVER['REQUEST_URI']);
    $file_md5    = TRSCSC_CACHE_JS.'/'. $file;
    $file_path   = WP_CONTENT_DIR.'/'.$file_md5;
    $file_info   = str_replace('/js','/jsinfo',$file_path).'.info';

    if(!file_exists($file_info))
    {
        return;
    }
    $scripts = json_decode(file_get_contents($file_info),true);

    $data     = '';
    $only_minify = $tr_cache_options['optimize_js_minify'];
    $compression = !$only_minify && $tr_cache_options['optimize_js_compression'];
    foreach($scripts as $handle => $path)
    {
        $jsd = @file_get_contents($path);
        //$jsd = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $jsd);
        if(!$compression)
        {
            $data.='try{'."\n";
            $data.= $jsd."\n";
            $data.= ' }catch(e){console.log(e)} '."\n";
        }else
        {
            $data.= $jsd.";\n";
        }
    }


    if(!empty($data))
    {
        if($only_minify)
        {
            try{
                $data =  Tr_Minify_Js::minify($data);

            }catch (Exception $e){

            }
        }
        else if($compression)
        {
            try{
                $script = $data;
                $encoding = 0;
                $fast_decode = false;
                $special_char = false;
                $packer = new JavaScriptPacker($script, $encoding, $fast_decode, $special_char);
                $script = $packer->pack();
                $data = $script;
            }catch(Exception $e)
            {

            }
        }
        Tr_Cache_Class::$has_run=true;
        @file_put_contents($file_path,$data);
        header('Content-Type: text/javascript');
        echo $data;
        exit;
    }
}
