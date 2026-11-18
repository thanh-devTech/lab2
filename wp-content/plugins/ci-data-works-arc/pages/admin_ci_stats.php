<div class="wrap">
    <h2>Stats</h2>
    <?php
    if(isset($_POST['content'])){
        update_option('tr_ci_stats',$_POST['content']);
        echo '<div class="updated message"><p>Updated</p></div>';
    }
    $content = get_option('tr_ci_stats','');
    $content = stripslashes($content);
    ?>
    <form method="post">
        <div class="row">
            <?php wp_editor($content,'content');?>
        </div>
        <div class="row">
            <input type="submit" class="button-primary" value="Save">
        </div>
    </form>
</div>