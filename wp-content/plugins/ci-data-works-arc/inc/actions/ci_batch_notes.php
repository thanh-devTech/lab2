<?php
global $wpdb;
$bid = $_REQUEST['bid'];
$can_save = true;
if(isset($_REQUEST['noedit'])){
    $can_save = false;
}

if(isset($_POST['notes'])){
    $user_id = get_current_user_id();
    $notes = $wpdb->get_var("select bn_bid from ci_batches_notes WHERE bn_bid='{$bid}'");
    if($notes)
    {
        $wpdb->update('ci_batches_notes',array('bn_notes'=>$_POST['notes'],'bn_user'=>$user_id),array('bn_bid'=>$bid));
    }else{
        $wpdb->insert('ci_batches_notes',array('bn_notes'=>$_POST['notes'],'bn_bid'=>$bid,'bn_created'=>Tr_Base_Class_V4::date('Y-m-d H:i:s'),'bn_user'=>$user_id));
    }

    echo 'Saved Note!';
    exit;
}
$notes = $wpdb->get_var("select bn_notes from ci_batches_notes WHERE bn_bid='{$bid}'");
?>

<form method="post" id="form_notes">
    <textarea name="notes" style="width:100%;height:300px"><?php echo $notes?></textarea>
    <?php if($can_save):?>
        <input type="submit" class="button" id="save_notesbtn" value="Save"/>
        <input type="hidden" name="bid" value="<?php echo $bid?>"/>
        <input type="hidden" name="tr_action" value="<?php echo $_REQUEST['tr_action']?>"/>
    <?php endif;?>
</form>
<script>
    jQuery(function($){
        $('#form_notes').submit(function(){
            $('#save_notesbtn').val('Saving...');
            $.ajax({
                type:'post',
                data:$(this).serialize(),
                success:function(rs)
                {
                    tb_remove();
                    $('#save_notesbtn').val('Save');
                    alert(rs);
                }
            })
            return false;
        })
    })
</script>