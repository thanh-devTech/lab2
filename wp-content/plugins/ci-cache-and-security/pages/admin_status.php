<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

$tabs = array(
    'mail' => 'Test Mail',
);
$tab = isset($_REQUEST['tab'])? $_REQUEST['tab']: key($tabs);
if(!isset($tabs[$tab]))
{
    $tab = key($tabs);
}
?>
    <h2 class="nav-tab-wrapper">
        <?php
        foreach($tabs as $k => $txt){
            $class = $tab == $k ? 'nav-tab-active':'';
            $link = Tr_Base_Class_Vs::link(array('tab'=>$k,'act'=>'','id'=>''),false);
            echo '<a class="nav-tab '.$class.'" href="'.$link.'">'.$txt.'</a>';
        }
        ?>
    </h2>
<?php
function trcs_status_wp_mail_failed($rs)
{
    var_dump($rs);
}

function trcs_status_get_from_address($email)
{
    global $from_email;
    return $from_email;
}

function trcs_status_get_from_name($name)
{
    global $from_name;
    return $from_name;
}


if($tab=='mail')
{
    if(isset($_POST['fields'])){
        global $from_email,$from_name;
        extract($_POST['fields']);

        add_action('wp_mail_failed','trcs_status_wp_mail_failed');

        if(!empty($from_email))
        {
            add_filter( 'wp_mail_from',  'trcs_status_get_from_address'  );
        }
        if(!empty($from_name))
        {
            add_filter( 'wp_mail_from_name', 'trcs_status_get_from_name'  );
        }



        $rs = wp_mail($to,$subject,$message);
        var_dump($rs);
    }
    ?>
    <form method="post">
        <div class="row">
            <label>Subject</label>
            <input type="text" name="fields[subject]" value="<?php echo $fields['subject']?>"/>
        </div>
        <div class="row">
            <label>To</label>
            <input type="text" name="fields[to]" value="<?php echo $fields['to']?>"/>
        </div>
        <div class="row">
            <label>Message</label>
            <textarea name="fields[message]"><?php echo $fields['message']?></textarea>
        </div>
        <div class="row">
            <label>From Name</label>
            <input type="text" name="fields[from_name]" value="<?php echo $fields['from_name']?>"/>
        </div>
        <div class="row">
            <label>From Email</label>
            <input type="text" name="fields[from_email]" value="<?php echo $fields['from_email']?>"/>
        </div>
        <div class="row">
            <input type="submit" class="button button-primary"value="Send"/>
        </div>
    </form>
<?php
}