<?php
global $wpdb;
$results = array();



if(isset($_REQUEST['assen'])){

    $assen = $_REQUEST['assen'];
    $sql = "select ba.*,
                ref_lab.lab_dba_init as lab_ref
                from ci_batches ba
                left JOIN ci_labs ref_lab ON ref_lab.lab_ID = ba.ref_lab_ID
                where assen like '{$assen}%'";
    $results = $wpdb->get_results($sql);
}

?>
<div class="wrap">
    <h2>Check Reference</h2>
    <?php Tr_Session_Class::show() ?>
    <div class="row">
        <form method="get" >
            <div class="row">
                <label>Accession Number</label>
                <input type="hidden" name="page" value="<?php echo $_GET['page']?>"/>
                <input type="hidden" name="tab" value="<?php echo isset($_GET['tab'])? $_GET['tab'] : ''?>"/>
                <input type="text" name="assen" value="<?php echo isset($assen)? $assen : ''?>" autofocus id="batch_assen" required="" style="width:200px"/>
                <input type="submit" class="button bsty6" value="Submit"/>
            </div>
        </form>

    </div>

    <?php
    if(count($results)>0){
        ?>
    <div class="wraptbl">
        <table class="wp-list-table widefat striped pages blue_tbl hover_tbl">
            <thead>
            <tr>
                <th>#</th>
                <th>Insurance</th>
                <th>Lab</th>
                <th>Dr Name</th>
                <th>Office</th>
                <th>Referenced</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $current_batch_id = tr_pdd_get_current_batch_id();
            foreach($results as $row):
                if($row->batch_id==0)
                {
                    $row->batch_id = $current_batch_id;
                }
                ?>
                <tr class="row_<?php echo $row->mid?>" data-id="<?php echo $row->mid?>" data-ref_id="<?php echo $row->ref_lab_ID?>" data-batch_id="<?php echo $row->batch_id?>">
                    <td class="pos"><?php echo $row->position?> </td>
                    <td><?php echo @$row->insurance?></td>
                    <td><?php echo @$row->lab?></td>
                    <td><?php echo @$row->requesting_physician?></td>
                    <td><?php echo @$row->requesting_practice?></td>
                    <td class="ref"><?php echo $row->lab_ref?></td>
                    <td>
                        <a class="add_to_current_batch"  href="#">Add to Current Batch</a>
                         -
                        <a class="thickbox" href="<?php Tr_Base_Class_V6::link(array('tr_action'=>'ci_print_req','id'=>$row->mid,'tab'=>''))?>&TB_iframe=true&width=800&height=550">Print Req Form</a>
                    </td>
                </tr>
            <?php endforeach;?>
            </tbody>
        </table>
    </div>

        <?php
        if($row->batch_type=='E'){
            $_REQUEST['id'] = $row->mid;
            $_REQUEST['not_show_tab'] = true;
            $_REQUEST['act'] = 'view';
            include( tr_pdd_get_req_file('tox') );
        }

        ?>
        <?php
    }else if(isset($_REQUEST['assen'])){
        ?>
        <div class="updated error"><p>Not found</p></div>
    <?php
    }
    ?>

</div>
<div id="add_to_current_batch" style="display: none;">
    <form method="post" id="form_add_to_current_batch">
        <div class="frow">
            <label>Position</label>
            <input type="text" id="batch_position" name="position" value=""/>
        </div>
        <?php if($blog_id == 1):?>
        <div class="frow">
            <label>Batch Number</label>
            <input type="text" id="batch_id" name="batch_id" value=""/>
        </div>
        <?php endif;?>
        <div class="frow">
            <label>Reference Lab</label>
            <input type="text" class="ref_lab_auto" id="batch_ref" name="ref_lab[lab_dba_init]" value=""/>
            <input type="hidden" id="batch_ref_ID" name="ref_lab[lab_ID]" value=""/>
        </div>
        <div class="frow">
            <input type="submit" id="submit_atc_batch" value="Submit" class="button-primary"/>
            <input type="hidden" id="batch_mid" name="mid" value=""/>
            <input type="hidden" name="tr_action" value="ci_add_to_current_batch"/>
        </div>
    </form>
</div>
<style>
    .ui-autocomplete{
        z-index: 9999999 !important;}
</style>
<script>
    jQuery(function($){
        var $tout;
        $('.add_to_current_batch').click(function(){
            var $tr = $(this).parents('tr');
            var $id = $tr.data('id');
            $('#batch_mid').val($id);
            $('#batch_position').val($('.pos',$tr).html());
            $('#batch_ref').val($('.ref',$tr).html());
            $('#batch_ref_ID').val($($tr).data('ref_id'));
            $('#batch_id').val($($tr).data('batch_id'));
            $('#submit_atc_batch').val('Submit');
            try{clearTimeout($tout);}catch(e){}
            $('#form_add_to_current_batch').show();
            $('#add_to_current_batch .updated').remove();
            tb_remove();
            tb_show("Add to Current Batch", "#TB_inline?height=300&amp;width=480&amp;inlineId=add_to_current_batch");
            return false;
        });
        $('#form_add_to_current_batch').submit(function(){
            $('#submit_atc_batch').val('Saving...');
            $.ajax({
                type:'post',
                data:$(this).serialize(),
                dataType:'json',
                success:function(rs){
                    var $tr = $('tr.row_'+rs.mid);
                    $('.pos',$tr).html(rs.position);
                    $('.ref',$tr).html(rs.ref);
                    $($tr).data('ref_ID',rs.ref_lab_ID);
                    $($tr).data('batch_id',rs.batch_id);
                    $('#TB_ajaxContent').append(rs.alert);
                    $('#form_add_to_current_batch').hide();
                    $tout = setTimeout(function(){tb_remove();},5000);
                }
            });
            return false;
        })
    })
</script>