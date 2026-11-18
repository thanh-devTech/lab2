<?php
global $wpdb;
include_once(TRCPDD_PATH.'pages/formdata.php');

$btype = !empty($btype)? $btype : 'tox';

$where = '';
if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $where .= " and lab_ID in ( {$lab_str} ) ";
}
$labs = $wpdb->get_results("select * from ci_labs where (lab_is_ref = 0 or lab_is_ref = 2) {$where} order by lab_dba_init asc");


$btype = 'tox';
?>

<div class="wrap">

    <div class="wraptbl">
    <table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl" >
        <thead>
        <tr>
            <th style="width:50px">#</th>
            <th style="width: 10%;">Lab</th>
            <th style="width: 40%;">Clinic</th>
            <th style="width:20%">Fax Number</th>
            <th>Fax Results</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach($labs as $lab):?>
            <tr class="parent" data-p="<?php echo $lab->lab_ID?>">
                <td><i></i></td>
                <td><?php echo $lab->lab_dba_init?></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <tr class="child p_tab_<?php echo $lab->lab_ID?>">
                <td colspan="5" class="nopadding"></td>
            </tr>

        <?php endforeach?>
        </tbody>
    </table>
    </div>

</div>
<style>
    .editable input{width:99%}
</style>
<script>
    jQuery(function($){


        $('body').on('click','td.editable',function(){
            if($(this).hasClass('editing')){
                return false;
            }

            $(this).data('vl',$(this).html()).addClass('editing');
            $(this).html('<input type="text" value="'+$(this).html()+'" title="Press Enter to save"/>');
            return false;
        });

        function trpdd_save_fax_number($pr,$id,$value)
        {
            $.ajax({
                type:'POST',
                dataType:'json',
                data:{tr_action:'ci_load_efax_setups','act':'save_fax_number',id:$id,value:$value,btype:'<?php echo $btype?>'},
                success:function(rs)
                {
                    if(rs.status=='ok'){
                        $pr.html($value);
                        $pr.removeClass('editing');
                    }else{
                        alert('Error Please try again');
                    }

                },
                error:function(){
                    setTimeout(function(){trpdd_save_fax_number($pr,$id,$value);},1000);
                }
            })
        }
        $('body').on('keypress','td.editable input',function(e){
            var code = e.keyCode || e.which;
            if (code == 13) {
                //save data
                var $pr = $(this).parents('td.editable');
                $value = $(this).val();
                $id = $pr.parent().data('id');
                $pr.append('<div class="sloading"></div>');
                trpdd_save_fax_number($pr,$id,$value);
            }
        });

        function trpdd_save_fax_result($pr,$id,$value)
        {
            $.ajax({
                type:'POST',
                dataType:'json',
                data:{tr_action:'ci_load_efax_setups','act':'save_fax_result',id:$id,value:$value,btype:'<?php echo $btype?>'},
                success:function(rs)
                {
                    if(rs.status=='ok'){

                    }else{
                        alert('Error Please try again');
                    }
                    $('.sloading',$pr).remove();

                },
                error:function(){
                    setTimeout(function(){trpdd_save_fax_result($pr,$id,$value);},1000);
                }
            })
        }

        $('body').on('click','td.checkboxable input',function(){
            var $pr = $(this).parents('td.checkboxable');
            $value = $(this).is(':checked')? 1 : 0;
            $id = $pr.parent().data('id');
            if($pr.find('.sloading').length>0)return false;

            $pr.append('<div class="sloading"></div>');
            trpdd_save_fax_result($pr,$id,$value);
        })


        function trcpp_load_results($lab_id)
        {
            var $obj = $('tr.p_tab_'+$lab_id+' td');
            if($obj.html()!='')
            {
                return;
            }else{
                $obj.html('<div class="loading center"></div>');
            }
            $.ajax({
                data:{tr_action:'ci_load_efax_setups',lid:$lab_id,btype:'<?php echo $btype?>'},
                type:'post',
                success:function(rs)
                {
                    var $html = $(rs);
                    $obj.html($html);
                    $('.checkboxable',$html).each(function(){
                        var $cid = $(this).parent().find('.cid').html();
                        var $checked = $(this).html()=='1'? 'checked':'';
                        $(this).html('<input type="checkbox" value="'+$cid+'" '+$checked+'/>');
                    })
                }
            })
        }

        $('body').on('click','tr.parent',function(e){
            if(e.target.tagName!='TD' && e.target.tagName!='TR' && e.target.tagName!='I')return;
            $('tr.child').hide();
            if($(this).data('showed')!='1')
            {
                var $obj = $('tr.p_tab_'+$(this).data('p'));
                $obj.show();
                $('tr.parent').data('showed','0');
                $(this).data('showed','1');
                $(this).addClass('active');
                setCookie('active_efaxs',$(this).data('p'));
                $('body,html').scrollTop($obj.offset().top-80);
                trcpp_load_results($(this).data('p'));
            }else{
                $('tr.p_'+$(this).data('p')).hide();
                $(this).data('showed','0');
                $(this).removeClass('active');
            }
            return false;
        });


        $old_active = getCookie('active_efaxs');
        if($old_active && $old_active!=null)
        {
            var $obj = $('tr.p_tab_'+$old_active);
            try
            {
                $obj.show();
                $('[data-p="'+$old_active+'"]').data('showed','1');
                if($old_active.indexOf('lab_')>-1)
                {
                    $obj.parents('.child').show();
                }
                $('body,html').scrollTop($obj.offset().top-150);
                trcpp_load_results($old_active);
            }catch(e){}

        }else if($('table.report_tbl tr.parent').length==1)
        {
            console.log('aaa');
            $('table.report_tbl tr.parent').click();
        }



    })


</script>