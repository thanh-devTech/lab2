<?php
//notify
$file_t = WP_CONTENT_DIR.'/cache/loss_db.txt';
$ftime = @filemtime($file_t);
$config = @json_decode(@file_get_contents(WP_CONTENT_DIR.'/cache/trcs_otherst.json'),true);

if($config['notify_db_loss'] && !empty($config['notify_db_email']) && $ftime < time() - 86400){

    $msg = $_SERVER['HTTP_HOST'];
    if(function_exists('wp_mail')){
        $rs = wp_mail($config['notify_db_email'],'Error establishing a database connection',$msg);
    }else{
        $rs = mail($config['notify_db_email'],'Error establishing a database connection',$msg);
    }
	@touch($file_t);

    if($config['notify_db_loss_api'])
    {
        $body = array();
        $body['tr_action'] = 'ci_notify_loss_db';
        $for = str_replace(array('http://','https://'),'',$_SERVER['HTTP_HOST']);
        $body['for']= base64_encode($for);

        $body = file_get_contents('https://ciwebstudio.com/wp-load.php?'.http_build_query($body));

        if (!empty($body)) {
            $data = @json_decode($body, true);
            $rs = $data['status']=='ok';
        }else{
            $rs = false;
        }
    }



}

$message = '<h1>' . __( 'Error establishing a database connection' ) . "</h1>\n";
wp_die($message);
