<?php
global $wpdb;

//check table wp_tr_image_link_posts
$has_table = $wpdb->get_var("SHOW TABLES LIKE 'wp_tr_image_link_posts'");
if($has_table===null)
{
    tr_cache_security_activate(true);
}

if(isset($_GET['reset'])){
    update_option('trsync_images_last_postid',0);
}


if(!wp_next_scheduled('cron_sync_post_images'))
{
    wp_clear_scheduled_hook('cron_sync_post_images');
    wp_schedule_single_event(time()+30,'cron_sync_post_images');
}



$search = isset($_GET['s'])? $_GET['s'] :'';
$by = isset($_GET['by'])? $_GET['by'] :'';
$hasp = isset($_GET['hasp'])? 1 :0;

$where = " p.post_type='attachment' and p.post_mime_type in ('image/jpeg','image/png','image/gif','image/jpg') ";
$join = '';

if(!empty($search))
{
    if($by=='post'){
        $where.= " and (p2.post_title like '%{$search}%'  )";
        $join .= " left join {$wpdb->posts} p2 ON p2.ID = lp.post_id ";
    }else{
        $where.= " and (p.post_title like '%{$search}%' or m.meta_value like '%{$search}%' )";
    }

}

if($hasp)
{
    $where.= " and lp.post_id > 0 ";
}

$row_per_page = 50;
$pagination = new Tr_Pagination_class2($row_per_page);
$offset = $pagination->offset();
$pagination->setTotal($wpdb->get_var("select count(distinct(p.ID)) from {$wpdb->posts} p 
                                        left join {$wpdb->postmeta} m On m.post_id = p.ID and m.meta_key='_wp_attachment_image_alt'
                                        left join wp_tr_image_link_posts lp ON lp.image_id = p.ID
                                        {$join}
                                        where {$where}"));

$sql = "select p.ID,p.post_title,p.post_excerpt,
        m.meta_value as alt_text,
        group_concat(lp.post_id) as postids
        from {$wpdb->posts} p
        left join {$wpdb->postmeta} m On m.post_id = p.ID and m.meta_key='_wp_attachment_image_alt'
        left join wp_tr_image_link_posts lp ON lp.image_id = p.ID
        {$join}
        where {$where} 
        group by p.ID
        order by p.ID desc 
        limit {$offset},{$row_per_page}";


$rows = $wpdb->get_results($sql);

if(isset($_GET['debug2'])){
    var_dump($sql);
}

?>

<div class="wrap">
    <div class="tablenav mtop10">
        <form method="get" class="search-box">
            <input type="hidden" name="page" value="<?php echo $_GET['page']?>"/>
            <input type="hidden" name="pagetab" value="<?php echo $_GET['pagetab']?>"/>
            <input type="hidden" name="tab" value="<?php echo $_GET['tab']?>"/>

            <input type="search" name="s" value="<?php echo $search?>" placeholder="Search"/>
            <select name="by">
                <?php
                foreach(array(''=>'By Image Name','post'=>'By Post Title') as $k => $vl)
                {
                    $selected = $_GET['by']==$k? 'selected':'';
                    echo '<option value="'.$k.'" '.$selected.'>'.$vl.'</option>';
                }
                ?>
            </select>
            <label><input type="checkbox" name="hasp" value="1" <?php checked($_GET['hasp'])?>/> Linked to Posts </label>
            <input type="submit" class="button" value="Filter"/>
        </form>
        <?php if(isset($pagination))echo $pagination->pagination() ?></div>
    <table class="wp-list-table widefat fixed striped posts" id="alttbl">
        <thead>
        <tr>
            <th style="width:150px">Image</th>
            <th>Alt</th>
            <th>Posts</th>
        </tr>
        </thead>
        <tbody>
        <?php
        $images_sizes = get_intermediate_image_sizes();
        foreach($rows as $row)
        {
            $image = wp_get_attachment_image_src($row->ID);
            $image_full = wp_get_attachment_image_src($row->ID,'full');
            $posts = array();
            if(!empty($row->postids)) {
                $query_posts = "select * from {$wpdb->posts} where ID in ({$row->postids})";
                $posts = $wpdb->get_results($query_posts);
            }
            /*
            else{
                $where_post_content = '';
                foreach(array('large') as $sizename)
                {
                    $image = wp_get_attachment_image_src($row->ID,$sizename);
                    if(!isset($image[0]))continue;
                    list($imaged,$imageurl) = explode('uploads/',$image[0]);
                    if(empty($imageurl))continue;
                    $where_post_content.= " or (p.post_content like '%{$imageurl}%') ";
                }

                $query_posts = "select p.* from {$wpdb->posts} p
                                        left join {$wpdb->postmeta} m ON m.post_id = p.ID and m.meta_key='_thumbnail_id'
                                        where 
                                            p.post_status ='publish'
                                            and p.post_type !='attachment'
                                            and p.post_type!='customize_changeset'
                                            and p.post_type!='revision'
                                            and (m.meta_value= {$row->ID} 
                                                {$where_post_content}
                                                )
                                        ";
            }


            */

            ?>
            <tr data-id="<?php echo $row->ID?>">
                <td title="<?php echo $row->post_title?>"><img src="<?php echo $image[0]?>"/></td>
                <td><input type="text" placeholder="Alt Text" class="alt_text" title="Press Enter(Return) to save" value="<?php echo $row->alt_text?>"/>
                    <div>Caption: </div>
                    <textarea class="post_excerpt"><?php echo esc_html($row->post_excerpt)?></textarea>
                    <div><a class="savebtn button">Save</a></div>
                    <div><?php echo basename($image_full[0])?></div>
                </td>
                <td>
                    <?php
                    echo '<ol>';
                    foreach($posts as $p)
                    {
                        echo '<li><a target=_blank href="'.get_permalink($p).'">'.$p->post_title.'</a></li>';
                    }
                    echo '</ol>';
                    ?>
                </td>
            </tr>
        <?php
        }
        ?>
        </tbody>
    </table>
</div>

<style>
    #alttbl td img{
        max-width: 140px;
    }
    #alttbl td input.alt_text, #alttbl td textarea{
        width:100%;
    }
</style>
<script>
    jQuery(function ($) {
        $('body').on('click','#alttbl td a.savebtn',function (e) {
            var $btn = $(this);
            var $tr = $(this).parents('tr');
            var $id = $tr.data('id');
            var $alt = $('.alt_text',$tr).val();
            var $post_excerpt = $('.post_excerpt',$tr).val();

            $btn.html('Saving...');
            $.ajax({
                type:'post',
                dataType:'json',
                data:{'tr_action':'ci_save_alt_text','id':$id,'alt':$alt,'post_excerpt':$post_excerpt},
                success:function (rs) {
                    $btn.html('Saved');
                    setTimeout(function () {
                        $btn.html('Save');
                    },6000);
                }
            });
            return false;
        })
    })
</script>