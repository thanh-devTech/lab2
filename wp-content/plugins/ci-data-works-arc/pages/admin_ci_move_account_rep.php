<?php
include_once(TRCPDD_PATH.'pages/formdata.php');
if(!empty($_POST['clinic']))
{
    $clinic_id = $_POST['cID'];
    $rep_id = $_POST['rep_ID'];
    if($clinic_id>0 && $rep_id>0)
    {
        $wpdb->update('ci_clinic',array('cli_rep-ID'=>$rep_id),array('cID'=>$clinic_id));

        //find collector
        $cols = $wpdb->get_results("select * from ci_clinic_collector where cli_ID = {$clinic_id}");
        foreach($cols as $col)
        {
            $wpdb->update('ci_clinic',array('rep_ID'=>$rep_id),array('col_ID'=>$col->col_ID));
        }
        Tr_Session_Class::add('Changed Rep Successfully');
    }

}
?>
<h2>Move Account to New Rep</h2>
<?php Tr_Session_Class::show()?>

<form method="post">
    <div class="frow">
        <div class="col3">
            <label>Clinic</label>
            <input type="text" class="clinic_rep_auto" name="clinic" value=""/>
            <input type="hidden" name="cID" value=""/>
        </div>
    </div>
    <div class="frow">
        <div class="col3">
            <label>Rep</label>
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
        $('.clinic_rep_auto').autocomplete({
            source: ajaxurl+'?action=ajax&autocomplete=clinic_rep_auto',
            minLength: 1,
            select: function( event, ui ) {
                if(ui.item)
                {
                    $('[name="cID"]').val(ui.item.id);
                    $(this).data('name',ui.item.value);

                    $('[name="rep"]').val(ui.item.rep_Name);
                    $('[name="rep_ID"]').val(ui.item.rep_ID);
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
        $('body').on('change','.clinic_rep_auto',function(){
            $vl = $(this).data('name');
            if($vl!=$(this).val())
            {
                $('[name="cID"]').val('');
                $('[name="clinic"]').val('')
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