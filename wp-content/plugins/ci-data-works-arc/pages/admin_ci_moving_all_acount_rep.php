<?php
include_once(TRCPDD_PATH.'pages/formdata.php');
if(!empty($_POST['old_rep_ID']))
{
    $old_rep_ID = $_POST['old_rep_ID'];
    $rep_id = $_POST['rep_ID'];
    if($old_rep_ID>0 && $rep_id>0)
    {
        $wpdb->update('ci_clinic',array('cli_rep-ID'=>$rep_id),array('cli_rep-ID'=>$old_rep_ID));

        //find collector
        $wpdb->update('ci_collector',array('rep_ID'=>$rep_id),array('rep_ID'=>$old_rep_ID));
        $wpdb->update('ci_rep_collector',array('rep_ID'=>$rep_id),array('rep_ID'=>$old_rep_ID));

        $wpdb->update('ci_clinician_groups',array('clig_rep_ID'=>$rep_id),array('clig_rep_ID'=>$old_rep_ID));

        Tr_Session_Class::add('Changed Rep Successfully');
    }

}
?>
<h2>Move Account to New Rep</h2>
<?php Tr_Session_Class::show()?>

<form method="post">
    <div class="frow">
        <div class="col3">
            <label>Old Rep</label>
            <input type="text" class="old_rep_auto" name="old_rep" value=""/>
            <input type="hidden" name="old_rep_ID" value=""/>
        </div>
    </div>
    <div class="frow">
        <div class="col3">
            <label>New Rep</label>
            <input type="text" class="rep_auto" name="rep" value=""/>
            <input type="hidden" name="rep_ID" value=""/>
        </div>
    </div>
    <div class="frow">
        <div class="col3">
            <input type="submit" value="Save Changes" class="button-primary"/>
        </div>
    </div>

</form>
<script>
    jQuery(function($){
        $('.old_rep_auto').autocomplete({
            source: ajaxurl+'?action=ajax&autocomplete=rep_auto',
            minLength: 1,
            select: function( event, ui ) {
                if(ui.item)
                {
                    $('[name="old_rep_ID"]').val(ui.item.id);
                    $(this).data('name',ui.item.value);
                }
            }
        });
        $('.rep_auto').autocomplete({
            source: ajaxurl+'?action=ajax&autocomplete=rep_auto',
            minLength: 1,
            select: function( event, ui ) {
                if(ui.item)
                {
                    $('[name="rep_ID"]').val(ui.item.id);
                    $(this).data('name',ui.item.value);
                }
            }
        });
        $('body').on('change','.old_rep_auto',function(){
            $vl = $(this).data('name');
            if($vl!=$(this).val())
            {
                $('[name="old_rep_ID"]').val('');
                $('[name="old_rep"]').val('')
            }
        });
        $('body').on('change','.rep_auto',function(){
            $vl = $(this).data('name');
            if($vl!=$(this).val())
            {
                $('[name="rep_ID"]').val('');
                $('[name="rep"]').val('')
            }
        });
    })
</script>