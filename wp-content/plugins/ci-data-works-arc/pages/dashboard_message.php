<?php
add_thickbox();
global $wpdb;
$user_id = get_current_user_id();
$today = date_i18n('Y-m-d');
$sql = "select * from ci_messages as me
        left JOIN ci_message_users mu ON mu.msg_ID = me.ID
        WHERE (mu.user_id = {$user_id} or me.read_roles !='user')
        and from_date <='{$today}' 
        and to_date >='{$today}'
        order by me.created desc limit 10";
$messages = $wpdb->get_results($sql);

if (count($messages) == 0) {
    //echo '<p>Not found message</p>';

} else {
?>
    <div class="db_messages">
        <?php
        foreach ($messages as $row) {
            $msg = substr(strip_tags($row->msg), 0, 500);
            $row->color = trpdd_getContrastColor($row->background);
        ?>
            <div class="<?php if ($row->istatus == 0) echo 'unread' ?>" style="padding:5px;margin-bottom:5px;background:<?php echo $row->background ?>;color:<?php echo $row->color?>">
                <?php echo $msg ?>
            </div>
        <?php
        }
        ?>
    </div>

<?php
}
?>

<script>
    jQuery(document).ready(function($) {
        var $height = $('#dashboard_welcome_clis').height();
        //$('#dashboard_message').css('height',$height+1);
    })
</script>