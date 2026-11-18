<?php
$box = new CI_Box_Works(false,true);

if(isset($_POST['options'])){

    if(isset($_POST['logout'])){
        $_POST['options']['access_token'] = '';
        $_POST['options']['refresh_token'] = '';

    }
    $box->update_options($_POST['options']);


    Tr_Session_Class::add('Saved Settings Successfully');
}

$blogurl = str_replace('http:','https:',get_bloginfo('url'));
$redirect_url = $blogurl;

if(isset($_GET['code']) && !empty($_GET['code'])){
    try{
        $rs = $box->authorize($_GET['code']);
        if($rs===-1)
        {
            //done
        }
        else if($rs->headers->code==200)
        {
            Tr_Session_Class::add('Authentication Successfully');
        }else{
            Tr_Session_Class::add_error('Authentication Error: '.$rs->response['error_description']);
        }
    }catch (Exception $e)
    {
        Tr_Session_Class::add_error('Error: '.$rs['error_description']);
    }
}

$options = $box->options;

if(!isset($options['use_results_folder'])){
    $options['use_results_folder'] = 1;
}

$user = false;
if(!empty($options['access_token'])){
    try{
        $user = $box->get_user('me');
        $user_id = intval($user->response['id']);
        if($user_id>0)
        {
            $box->options['account_id'] = $user->response['id'];
            $box->update_options();

        }else{
            $user = $box->get_user('me');
            $user_id = intval($user->response['id']);
            if($user_id>0)
            {
                $box->options['account_id'] = $user->response['id'];
                $box->update_options();

            }
        }

    }catch (Exception $e)
    {
        Tr_Session_Class::add_error('Error: '.$e->getMessage());
    }
}


try{
    if(isset($_REQUEST['test_create'])) {
        $rs = $box->create_folder($_REQUEST['name']);
        var_dump($rs);
        exit;
    }else if(isset($_REQUEST['get_folder_items'])) {
        $rs = $box->get_folder_items($_REQUEST['id']);
        var_dump($rs);
        exit;
    }else if(isset($_REQUEST['test_get_folder'])) {
        $rs = $box->get_folder_info($_REQUEST['id']);
        var_dump($rs);
        exit;
    }else if(isset($_REQUEST['copy_file'])){
        try{
            $rs = $box->copy_file($_REQUEST['f'],$_REQUEST['d']);
        }catch (Exception $e)
        {
            var_dump($e);
        }
        var_dump($rs);
        exit;
    } else if(isset($_REQUEST['copy_folder'])){
        try{
            $rs = $box->copy_folder($_REQUEST['s'],$_REQUEST['d']);
        }catch (Exception $e)
        {
            var_dump($e);
        }
        var_dump($rs);
        exit;
    }else if(isset($_REQUEST['testup'])){
        $rs = $box->upload_file(TRCPDD_PATH.'js/admin.js','test.txt');
        var_dump($rs);
    }
    else if(isset($_REQUEST['testfolder'])){
        $rs = $box->get_folder_info($_REQUEST['testfolder']);
        var_dump($rs);
    }
    else if(isset($_REQUEST['reset_token'])){
        $options['access_token'] = '';
        $options['refresh_token'] = '';
        update_option('ci_box_settings',$options);
    }
}catch(Exception $e)
{
    var_dump($e);
}
?>

    <div class="wrap">
        <h2>Box Settings</h2>
        <?php Tr_Session_Class::show()?>

        <form method="post">
            <?php if($user):?>
                <div class="row">
                    <img src="<?php echo $user->response['avatar_url']?>" style="max-width: 50px;max-height: 50px;"/>
                    <b><?php echo $user->response['name']?></b>
                    <input type="submit" name="logout" value="Logout" class="button"/>
                </div>
            <?php endif;?>
            <div class="row">
                <label>Client ID</label>
                <input type="text" name="options[client_id]" value="<?php echo $options['client_id']?>"/>
                <?php if(!empty($options['client_id']) ):?>
                    <a href="<?php echo $box->get_authorize_url($redirect_url)?>" class="button">Authorize</a>
                <?php endif;?>
            </div>
            <div class="row">
                <label>Client Secret</label>
                <input type="text" name="options[client_secret]" value="<?php echo $options['client_secret']?>"/>
            </div>
            <div class="row">
                <label>Redirect Url:</label>
                <input type="text" readonly value="<?php echo $redirect_url?>"/>
                <a href="https://app.box.com/developers/services">Create App</a>
            </div>
            <div class="row">
                <label>Use Results folder</label>
                <input type="hidden" name="options[use_results_folder]" value="0"/>
                <input type="checkbox" name="options[use_results_folder]" value="1" <?php checked($options['use_results_folder'])?>"/> (default: 1)
            </div>
            <div class="row">
                <input type="submit" value="Save" class="button-primary"/>
<?php if($user){?>

    <?php }?>
            </div>
        </form>
    </div>

<?php
if(isset($_REQUEST['debug'])){
    var_dump($options);
}
?>