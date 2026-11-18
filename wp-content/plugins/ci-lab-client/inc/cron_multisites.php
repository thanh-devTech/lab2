<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $ci_blogs;
// Get Blogs
$args  = array( 'archived' => 0, 'deleted' => 0 );
$ci_blogs = get_sites( $args );

function ci_do_cron_multisite()
{
	global $ci_blogs,$wp_version;

	$current_blog = array_pop($ci_blogs);

	if(!$current_blog || count($ci_blogs)==0)
	{
		return;
	}
	if(count($ci_blogs)>1)
	{
		register_shutdown_function('ci_do_cron_multisite');
	}



	//$f = fopen(WP_CONTENT_DIR.'/log/log_cron_m.txt','a+');
	//fwrite($f,date('Y-m-d H:i:s ').$current_blog->blog_id."\n");
	//fclose($f);

	$time = time();
	$domain = $current_blog->domain;
	$path = $current_blog->path;
	$agent = 'WordPress/' . $wp_version . '; ' . home_url();
	$command = "https://" . $domain . ( $path ? $path : '/' ) . 'wp-cron.php?doing_wp_cron2=' . $time . '&ver=' . $wp_version;

	$ch = curl_init( $command );
	$rc = curl_setopt( $ch, CURLOPT_RETURNTRANSFER, false );
	$rc = curl_exec( $ch );
	curl_close( $ch );
	//var_dump($command);

}

register_shutdown_function('ci_do_cron_multisite');
