<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb;
$limit = 30;
$last_id = get_option('trsync_images_last_postid',0);

$posts = $wpdb->get_results("select * from {$wpdb->posts} 
                                    where ID > {$last_id} 
                                    and post_content != ''
                                    and post_type !='attachment'
                                    and post_type!='customize_changeset'
                                    and post_type!='revision'
                                    limit {$limit}
                                    ");


if(count($posts) > 0 && !wp_next_scheduled('cron_sync_post_images'))
{
    wp_clear_scheduled_hook('cron_sync_post_images');
    wp_schedule_single_event(time()+($limit * 10),'cron_sync_post_images');
}

foreach($posts as $p)
{
    tr_cache_sync_post_images($p->ID,$p);
    $last_id = $p->ID;
}

update_option('trsync_images_last_postid',$last_id);

