<div class="wrap">
    <?php
    global $wpdb;
    $where = '';
    if(!empty($_REQUEST['search'])){
        $search = $_REQUEST['search'];
        $where = " where doc_first like '{$search}%' or doc_last like '{$search}%'";


    }
    $row_per_page = 100;
    $pagination = new Tr_Pagination_class($row_per_page);
    $pagination->setTotal($wpdb->get_var("select count(*) as c from ci_doc {$where}"));
    $offset = $pagination->offset();

    $docs = $wpdb->get_results("select * from ci_doc {$where} limit {$offset},{$row_per_page}");
    ?>

    <h2>Doctors</h2>
    <form method="get">
        <input type="hidden" name="page" value="<?php echo @$_REQUEST['page']?>">
        <?php if(isset($_REQUEST['tab'])):?>
            <input type="hidden" name="tab" value="<?php echo @$_REQUEST['tab']?>">
        <?php endif;?>
        Search: <input type="text" name="search" value="<?php echo $_REQUEST['search']?>">
        <input type="submit" class="button bsty6" value="Search"/>
    </form>

    <div class="wraptbl">
    <table class="wp-list-table widefat striped pages blue_tbl hover_tbl" style="table-layout: auto">
        <thead>
        <tr>
            <th style="width:50px">#</th>
            <th>First Name</th>
            <th>Last Name</th>
            <th>NPI</th>
            <th style="width:40%">Mnemonic <small>(click to edit)</small></th>
        </tr>
        </thead>
        <tbody>
        <?php
        if(isset($docs) && count($docs)>0)
        {
            foreach($docs as $i => $row){
                ?>
                <tr data-id="<?php echo $row->doc_ID?>">
                    <td><?php echo $i+1?></td>
                    <td><?php echo $row->doc_first?></td>
                    <td><?php echo $row->doc_last?></td>
                    <td><?php echo $row->doc_npi?></td>
                    <td class="mnemonic" data-vl="<?php echo $row->doc_mnemonic?>"></td>
                </tr>
                <?php
            }
        }else if(isset($docs)){
            ?>
            <tr>
                <td colspan="3">Not found</td>
            </tr>
            <?php
        }
        ?>
        </tbody>
    </table>
    </div>

    <div class="tablenav"><?php if($pagination)echo $pagination->pagination() ?></div>
</div>
<script>
    jQuery(function($){
        $('td.mnemonic').each(function(){
            var $this = $(this);
            $this.html($this.data('vl'));
            $this.click(function(){
                if(!$(this).hasClass('editing'))
                {
                    $new = '<input type="text" class="mnemonic_vl" value="'+$(this).html()+'"><input class="button-primary submit" type="button" value="Submit"/><input type="button" class="button cancel" value="Cancel"/>';
                    $(this).html($new).addClass('editing');
                }
            })

        })
        $('body').on('click','td.mnemonic .cancel',function(){
            $td = $(this).parents('td');
            $td.html($td.data('vl'));
            $td.removeClass('editing');
            return false;
        })
        $('body').on('click','td.mnemonic .submit',function(){
            $td = $(this).parents('td');
            $doc_id = $(this).parents('tr').data('id');
            $vl = $('.mnemonic_vl',$td).val();
            $.ajax({
                url:location.href,
                type:"post",
                data:{tr_action:'ci_save_doc_mnemonic',doc_id:$doc_id,value:$vl},
                dataType:'json',
                success:function(rs){
                    $td.html($vl).data('vl',$vl);
                    $td.removeClass('editing');
                }
            })
            return false;
        })
    })
</script>