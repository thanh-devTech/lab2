<div class="wrap">
    <?php
    global $wpdb,$current_role,$blog_id;

    include_once(TRCPDD_PATH.'pages/formdata.php');

    $max_item_plate = 96;
    $batch_id = tr_pdd_get_current_batch_id();
    $specimens = $wpdb->get_results("select * from ci_test_specimens");
    $batch_ids = array();
    for($i = -1;$i<=5;$i++)
    {
        $batch_ids[] = $batch_id - $i;
    }
    $instruments = $wpdb->get_col("select instrument from `ci_instruments` order by instrument asc");

    $today = date_i18n('Y-m-d');
    if(isset($_REQUEST['today'])){
        $today = $_REQUEST['today'];
    }


    if(isset($_POST['rec']))
    {
        $log = $_POST;

        $recs = tr_pdd_stripslashes_deep($_POST['rec']);
        $mid_exists = array();
        $track_exists = array();
        foreach($recs as $i => $rec)
        {

            if($rec['pl_ID']==0)
            {
                $rec['pl_ID'] = $wpdb->get_var("select pl_ID from ci_plates where pl_number = '{$rec['pl_number']}'
                                                    and pl_today = '{$rec['pl_today']}'");
            }

            if($rec['pl_ID']>0)
            {
                $ID = $rec['pl_ID'];
                $rs = $wpdb->update('ci_plates',$rec,array('pl_ID'=>$rec['pl_ID']));
                if($rs===false)
                {
                    Tr_Session_Class::add_error("Error: ".$wpdb->last_error);
                }
            }else{
                $rec['pl_author_id'] = get_current_user_id();
                $rec['pl_type'] = 0;
                if(empty($rec['pl_today']))
                {
                    $rec['pl_today'] = $today;
                }
                $rs = $wpdb->insert('ci_plates',$rec);
                if($rs===false)
                {
                    Tr_Session_Class::add_error("Error: ".$wpdb->last_error);
                }
                $ID = $wpdb->insert_id;
            }
            //var_dump($ID);
            if($ID>0)
            {
                $mids = is_array($_POST['re_mid'][$i])? $_POST['re_mid'][$i] : array();
                $mids_str = count($mids)>0? implode("','",$mids): '0';
                foreach($mids as $mid)
                {
                    $table = 'ci_batches';
                    $btype = 'tox';
                    $position = $_POST['re_mid_position'][$mid];

                    if(in_array($mid,$mid_exists))continue;
                    $mid_exists[] = $mid;

                    if(stripos($mid,'_')!==false)
                    {
                        list($btype,$mid) = explode('_',$mid);
                        $table = 'ci_batches_'.$btype;
                    }


                    $update = array('position'=>$position,'batch_id'=>$rec['pl_batch_ID']);

                    $exist = $wpdb->get_var("select mid from ci_plates_batches where pl_ID = {$ID} and mid = {$mid} and btype='{$btype}' ");
                    if($exist==0)
                    {
                        $rs = $wpdb->insert('ci_plates_batches',array('pl_ID'=>$ID,'mid'=>$mid,'btype'=>$btype));
                        if($rs===false)
                        {
                            Tr_Session_Class::add_error("Error: ".$wpdb->last_error);
                        }

                        $old_status = $wpdb->get_var("select batch_status from {$table} where mid = {$mid} ");
                        if($old_status!='received'){
                            $spec_received = date("Y-m-d H:i:s",$current_time);
                            $update['batch_status'] = 'received';
                            $update['spec_received'] = $spec_received;
                        }

                    }

                    $wpdb->update($table,$update,array('mid'=>$mid));
                }


            }

        }
        Tr_Session_Class::add('Saved data Successfully');



        $log['batch_id'] = $batch_id;
        trpdd_log_form('plate',$log);


    }

    $sql_labs = "select * from ci_labs where (lab_is_ref=0 or lab_is_ref=2) ";
    $sql_plate = "select * from ci_plates where pl_today>='{$today}' ";
    if(CI_Base_Lab::has_user_labs())
    {
        $lab_str = CI_Base_Lab::get_user_lab_ids_str();
        $sql_labs.= "and lab_ID in ( {$lab_str} ) ";
        $sql_plate.= " and pl_lab_ID in ( {$lab_str} ) ";
    }
    $labs = $wpdb->get_results($sql_labs, ARRAY_A);

    $sql_plate.= " order by pl_ID asc";
    $results = $wpdb->get_results($sql_plate, ARRAY_A);
    if(count($results)==0)
    {
        $results[] = array(
            'pl_number' => '1',
            'pl_today' => $today,
            'pl_lab_ID' => CI_Base_Lab::get_current_lab_id(),
            'pl_qty'=> '0',
            'pl_batch_ID' =>$batch_id
        );
    }

    if(isset($_GET['debug'])){
        var_dump($sql_plate);
    }

    ?>
    <?php Tr_Session_Class::show()?>

    <form method="post" class="need_validate">
        <div class="wraptbl">
        <table class="wp-list-table widefat striped fixed posts blue_tbl hover_tbl report_tbl" id="rec_tbl_add">
            <thead>
            <tr>
                <th style="width:20px"></th>
                <th style="width:180px">Plate number</th>
                <th style="width:80px">Lab</th>
                <th>Instrument</th>
                <th style="width: 150px;">Today</th>
                <th style="width:150px">Batch</th>
                <th class="tright">Qty</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $i = 0;
            $max_position = array();
            $specimen_types_all = array();
            foreach($specimens as $sp)
            {
                if($sp->ts_position>0)
                {
                    $sp->ts_position -=1;
                }
                $specimen_types_all[$sp->ts_type][$sp->ts_name] = $sp->ts_name;

                if($sp->ts_type=='tox'){
                    if($sp->ts_name=='Urine'){
                        $max_position['k0'] = $sp->ts_position;
                    }
                    $max_position['k'.$sp->ts_name] = $sp->ts_position;
                }
                else{
                    $max_position[$sp->ts_type] = $sp->ts_position;
                }
            }

            if(isset($_GET['debug'])){
                var_dump($max_position);
            }
            foreach($results as $row)
            {
                $current_plate = $row['pl_number'];

                $sql_childs = array();

                foreach(trpdd_get_current_btype() as $btype)
                {
                    if(in_array($btype, array('study')))continue;

                    $batch_tbl = tr_pdd_get_table_by_btype($btype);

                    $sql_childs[] = "
                    (select ba.mid,ba.type_of_specimen,ba.initials1,ba.ref_from_lab_ID,ba.position,ba.assen,ba.doc,ba.patient_name,
                    ba.requesting_practice,ba.requesting_physician,panel_tests_requested,
                    rb.btype 
                    from {$batch_tbl} ba
                    JOIN ci_plates_batches rb ON rb.mid=ba.mid and rb.btype='{$btype}'
                    where rb.pl_ID = '{$row['pl_ID']}' ) 
                    ";
                }

                $sql_childs = implode(' UNION ALL ', $sql_childs);

                $sql = "select * from ({$sql_childs}) as ball
                order by cast(ball.position as unsigned) desc";
               

                if(isset($_GET['debug'])){
                    echo $sql;echo '<br><br><br>';
                }
                $batches = $wpdb->get_results($sql);

                ?>
                <tr class="parent_c <?php if($i==0)echo 'first'?>" data-p="batch_<?php echo $i?>">
                    <td><i></i><input type="hidden" name="rec[<?php echo $i?>][pl_ID]" value="<?php echo @$row['pl_ID']?>"/></td>

                    <td><input type="text" class="pl_number readonly" name="rec[<?php echo $i?>][pl_number]" value="<?php echo @$row['pl_number']?>" readonly required/> </td>
                    <td>
                        <select name="rec[<?php echo $i?>][pl_lab_ID]" required >
                            <option value="">Select Lab</option>
                            <?php foreach($labs as $lab){
                                $selected = $lab['lab_ID']==@$row['pl_lab_ID']? 'selected':'';
                                echo '<option value="'.$lab['lab_ID'].'" '.$selected.'>'.$lab['lab_dba_init'].'</option>';
                            }?>
                        </select>
                    </td>

                    <td>
                        <select name="rec[<?php echo $i?>][pl_instrument]" required >
                            <option value="">Select Instrument</option>
                            <?php foreach($instruments as $instrument){
                                $selected = $instrument==@$row['pl_instrument']? 'selected':'';
                                echo '<option value="'.$instrument.'" '.$selected.'>'.$instrument.'</option>';
                            }?>
                        </select>
                    </td>
                    <td>
                        <input type="text" class="date readonly" name="rec[<?php echo $i?>][pl_today]" value="<?php echo @$row['pl_today']?>" readonly/>
                    </td>
                    <td>
                        <select name="rec[<?php echo $i?>][pl_batch_ID]" required>
                            <option value="">Select Batch</option>
                            <?php foreach($batch_ids as $bid){
                                $selected = $bid==@$row['pl_batch_ID']? 'selected':'';
                                echo '<option value="'.$bid.'" '.$selected.'>'.$bid.'</option>';
                            }?>
                        </select>
                    </td>

                    <td class="qty tright"><?php echo count($batches)?></td>
                </tr>


                <tr class="child <?php if($i==0)echo ' first_child ';if(count($batches)>=$max_item_plate)echo ' is_max_item ';echo ' p_batch_'.$i?>" data-id="<?php echo $i?>">
                    <td colspan="7">
                        <span class="barcodeauto_div">
                            <select class="btype" style="width:80px">
                            <?php
                            $types = array(
                                'tox' => 'Tox',
                                'blood'  => 'Blood',
                                'dna'  => 'PGx',
                                // 'ngs' => 'NGS',
                                'pcr' => 'PCR'
                            );
                            foreach($types as $k => $vl)
                            {
                                echo '<option value="'.$k.'">'.$vl.'</option>';
                            }
                            ?>
                        </select>
                        <input type="text" placeholder="Barcode" class="barcode_auto" style="width:300px">

                        <small>Press Enter to add</small>
                        </span>

                        <div class="warning_duplicate"></div>
                        <table class="posts report_tbl batch_tbl" data-pl_id="<?php echo $row['pl_ID']?>">
                            <thead>
                            <tr>
                                <th style="width:50px">#</th>
                                <th style="width:50px">Position</th>
                                <th>Barcode</th>
                                <th style="width:80px">DOC</th>
                                <th>Patient Name</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            $total_b = count($batches);
                            foreach($batches as $ii=> $batch)
                            {
                                $specimen_types = $specimen_types_all['tox'];
                                if(empty($batch->type_of_specimen))
                                {
                                    $batch->type_of_specimen = 'Urine';
                                }
                                if($batch->initials1=='JVHL'){
                                    $batch->type_of_specimen = 'JVHL';
                                }else if($batch->initials1=='HOS'){
                                    $batch->type_of_specimen = 'HOS';
                                }

                                $key_pos = 'k'.intval($batch->ref_from_lab_ID);

                                if($batch->btype=='tox'){
                                    if($batch->type_of_specimen!='Urine'){
                                        $key_pos = 'k'.$batch->type_of_specimen;
                                    }
                                }
                                else {
                                    $key_pos = $batch->btype;
                                    $specimen_types = $specimen_types_all[$batch->btype];
                                    $batch->mid = $batch->btype.'_'.$batch->mid;
                                }


                                $post_number = intval(preg_replace("/[^0-9]/",'',$batch->position));

                                if($post_number>=$max_position[$key_pos] && $post_number< $max_position[$key_pos]+1000)
                                {
                                    $max_position[$key_pos] = $post_number;
                                }

                                $batch->alcohol = stripos($batch->panel_tests_requested,'etg,')!==false && stripos($batch->panel_tests_requested,',ets')!==false;

                                ?>
                                <tr class="childp assen_<?php echo $batch->assen?>" data-spec_type="<?php echo $batch->type_of_specimen?>"  data-btype="<?php echo $batch->btype?>" data-pos="<?php echo $batch->position?>">
                                    <td><?php echo $total_b - $ii?><input type="hidden" name="re_mid[<?php echo $i?>][]" value="<?php echo $batch->mid?>"/></td>
                                    <td class="col-position"><input type="text" data-key="<?php echo $key_pos?>" name="re_mid_position[<?php echo $batch->mid?>]" value="<?php echo $batch->position?>"/></td>
                                    <td><?php echo $batch->assen?></td>
                                    <td><?php echo tr_pdd_display_date($batch->doc,'Y-m-d')?></td>
                                    <td><?php echo $batch->patient_name?></td>

                                    <td><a class="button remove_item">-</a> </td>
                                </tr>
                                <?php
                            }
                            ?>
                            </tbody>
                        </table>
                    </td>
                </tr>
                <?php
                $i++;
            }
            ?>
            </tbody>
        </table>
        </div>

        <br>
        <a class="button add_new_row">+</a>
        <input type="submit" class="button-primary" value="Submit"/>
        <a class="button show_hide_all right">Show All</a>
        <input type="hidden" name="print_barcode_mids" value="">
        <input type="hidden" name="print_reqs_mids" value="">
        <input type="hidden" name="today_batch_id" value="<?php echo $batch_id?>"/>
    </form>

    <audio src="<?php echo TRCPDD_URL?>audio/beep.mp3" id="beep_player" loop style="display:none"></audio>
    <iframe id="reprint_frame" src="about:blank" style="display: none;"></iframe>
    <style>
        .col-position input{width:60px;}
        .show_all tr.child{display:table-row !important;}
        .spec_type label{
            white-space: nowrap;
            margin-right:10px;
        }
        .spec_type label input[type=radio]{
            margin-right:0;
        }
        input.readonly{
            border:none !important;
            background:transparent !important;
            color:#fff !important;
            box-shadow: none !important;
            width:auto !important;
        }
        .is_max_item .barcodeauto_div{
            opacity: 0;
        }
        .pl_number.readonly{
            width:40px !important;
        }
        table .tright{
            text-align: right !important;
        }
    </style>
    <script>
        jQuery(function($){
            var $new_id = <?php echo $i?>;
            var $max_position = <?php echo json_encode($max_position)?>;
            var $barcode_doing = false;
            var $current_plate = <?php echo $current_plate?>;
            var $max_item_plate = <?php echo $max_item_plate?>;

            function tr_exists_position($pos) {
                if(!$pos)return false;
                var $exists = false;
                $('.col-position input').each(function () {
                    if($(this).val()==$pos){
                        $exists = true;
                    }
                })
                return $exists;
            }

            function tr_can_add_new_row()
            {
                var $count = $('#rec_tbl_add tr.child:last-child table.batch_tbl tr.childp').length;
                if($count>=$max_item_plate)
                {
                    return true;
                }
                return false;
            }

            function tr_add_new_row(){
                $current_plate++;
                var $newp = '<tr class="parent_c" data-p="batch_'+$new_id+'">'+$('#rec_tbl_add > tbody > tr.first').html()+'</tr>';
                $newp = $newp.replace(/\[\d\]/g,'['+$new_id+']');
                $newp = $($newp);
                $('input:not(.date),select',$newp).val('');
                $('input.pl_number',$newp).val($current_plate);
                $('td.qty',$newp).html('0');

                var $newc = '<tr class="child p_batch_'+$new_id+'" data-id="'+$new_id+'">'+$('#rec_tbl_add > tbody > tr.first_child').html()+'</tr>';
                $newc = $($newc);
                $('tbody',$newc).html('');
                $new_id++;
                $('#rec_tbl_add > tbody').append($newp);
                $('#rec_tbl_add > tbody').append($newc);
                //$newp.trigger('click');
                $('.barcode_auto',$newc).focus();

                //check time
                var today = new Date();
                var $hournow = today.getHours();

                if($hournow >= 19)
                {
                    var tomorrow = new Date();
                    tomorrow.setDate(today.getDate()+1);
                    var $nextdate = tomorrow.getFullYear()+'-'+ (tomorrow.getMonth()+1)+'-'+tomorrow.getDate();

                    $newp.find('input.date').val($nextdate);
                }
            }

            $('body').on('click','.add_new_row',function(){
                tr_add_new_row();
            })

                .on('click','.warning_duplicate',function () {
                    $(this).html('').removeClass('error');
                })
                .on('click','.remove_item',function () {
                    var $pr = $(this).parents('tr.childp');
                    var $mid = $('[name*="re_mid"]',$pr).val();

                    if(confirm('Do you want delete?')==false)return false;
                    $pr.addClass('bg_remove');

                    if(!$pr.hasClass('childp_new')){
                        var $ID = $pr.parents('.batch_tbl').data('pl_id');
                        $.ajax({
                            data:{tr_action:'ci_remove_plate',mid:$mid,pl_ID:$ID},
                            dataType:'json',
                            type:'post',
                            success:function(rs)
                            {
                                $pr.remove();
                            },
                            error:function () {
                                alert('Remove Failed, Please try again!');
                                $pr.removeClass('bg_remove');
                            }
                        });
                    }
                    return false;

                })
                .on('click','.reprint_btn',function () {
                    var $print_mids = '';
                    var $trp = $(this).parents('tr.child');
                    $('table.report_tbl tr.childp',$trp).each(function () {
                        $print_mids+= $('[name*="re_mid"]',$(this)).val()+',';
                    })
                    var $url = '?tr_action=ci_print_barcodes&mids='+$print_mids+'&reprint=1&print=1';
                    $('#reprint_frame').attr('src',$url);
                    return false;
                })
                .on('click','.print_barcode',function () {
                    var $pr = $(this).parents('tr');
                    var $print_mids = $('[name*="re_mid"]',$pr).val();
                    var $url = '?tr_action=ci_print_barcodes&mids='+$print_mids+'&print=1';
                    $('#reprint_frame').attr('src',$url);
                    return false;
                })
                .on('keypress','.barcode_auto',function(e){
                    var $this = $(this);
                    var $pr = $(this).parents('tr.child');
                    var code = e.keyCode || e.which;
                    var $vl = $(this).val().trim();
                    var $btype = $('.btype',$pr).val();
                    var $tbl = $pr.find('table tbody');
                    var $id = $(this).parents('tr').data('id');
                    var $warning = $('.warning_duplicate',$pr);
                    if(code ==13)
                    {
                        if($vl=='' || $barcode_doing)return false;
                        $this.val('');
                        //check exists
                        $warning.html('').removeClass('error');
                        if($('#rec_tbl_add .assen_'+$vl).length>0)
                        {
                            $warning.html('Duplicate barcode').addClass('error');
                            return false;
                        }else{
                            //double check
                            var $has_duplicate = false;
                            $('#rec_tbl_add .childp').each(function () {
                                if($(this).hasClass('assen_'+$vl)){
                                    $has_duplicate = true;
                                }
                            })
                            if($has_duplicate)
                            {
                                $warning.html('Duplicate barcode').addClass('error');
                                return false;
                            }
                        }

                        var $ii = $('.childp',$pr).length+1;
                        var $positions = '';
                        $('td.col-position input').each(function () {
                            $positions+= $(this).val()+',';
                        })


                        $barcode_doing = true;
                        $.ajax({
                            data:{tr_action:'ci_search_barcode_receiving',code:$vl,id: $id,ii:$ii,position:$max_position,positions:$positions,btype:$btype,ftype:'plate'},
                            type:'post',
                            dataType:'json',
                            success:function(rs)
                            {
                                $barcode_doing = false;
                                if(rs.status!='ok'){
                                    document.getElementById('beep_player').play();
                                    alert("Not found");
                                    document.getElementById('beep_player').pause();
                                }else{
                                    var $new = $(rs.html);
                                    $tbl.prepend($new);

                                    $max_position[rs.key_pos] = rs.position;

                                    if ($('.check_barcode',$pr).is(':checked')) {
                                        var $mid = $('[name*="re_mid"]',$new).val();
                                        $('[name="print_barcode_mids"]').val($('[name="print_barcode_mids"]').val()+','+$mid);
                                    }
                                    var $count = $('tr.childp',$tbl).length;
                                    $('tr[data-p="batch_'+$id+'"] .qty').html($count);

                                    if($count>=$max_item_plate)
                                    {
                                        $('tr.batch_'+$id).addClass('is_max_item');
                                    }

                                }

                            },
                            error:function () {
                                $this.val($vl);
                                alert('Error, Please try again');
                            }
                        });
                        return false;
                    }

                })
                .on('change','.col-position input',function () {
                    var $exists = false;
                    var $this = $(this);
                    var $pos = $this.val();
                    $('.col-position input').each(function () {
                        if($(this).val()==$pos && $this.attr('name') != $(this).attr('name')){
                            $exists = true;
                        }
                    })
                    if($exists)
                    {
                        $this.val('').focus();
                        alert('Duplicate Position');
                    }
                })
                .on('click','td.spec_type input',function () {
                    var $pr = $(this).parents('.childp');
                    var $posf = $('.col-position input',$pr);
                    var $newpos = false;

                    if($pr.data('btype')=='dna' || $pr.data('btype')=='ngs') {

                    }else if($pr.data('btype')=='blood'){

                    }
                    else if($(this).val()=='Oral')
                    {
                        $newpos = $posf.data('Oral');
                        if(tr_exists_position($newpos))
                        {
                            $newpos = false;
                        }
                        if(!$newpos)
                        {
                            $newpos = ++$max_position['kOral'];
                            $posf.data('Oral',$newpos);
                        }else if($max_position['Oral']==$newpos){
                            $max_position['kOral']++;
                        }
                        if($posf.data('JVHL') && $max_position['kJVHL']== $posf.data('JVHL')+1)
                        {
                            $max_position['kJVHL']--;
                        }
                    }
                    else if($(this).val()=='JVHL'){
                        $newpos = $posf.data('JVHL');
                        if(tr_exists_position($newpos))
                        {
                            $newpos = false;
                        }
                        if(!$newpos)
                        {
                            $newpos = ++$max_position['kJVHL'];
                            $posf.data('JVHL',$newpos);
                        }else if($max_position['kJVHL']==$newpos){
                            $max_position['kJVHL']++;
                        }
                        if($posf.data('Oral') && $max_position['kOral']== $posf.data('Oral')+1)
                        {
                            $max_position['kOral']--;
                        }
                    }
                    else if($(this).val()=='HOS'){
                        $newpos = $posf.data('HOS');
                        if(tr_exists_position($newpos))
                        {
                            $newpos = false;
                        }
                        if(!$newpos)
                        {
                            $newpos = ++$max_position['kHOS'];
                            $posf.data('HOS',$newpos);
                        }else if($max_position['kHOS']==$newpos){
                            $max_position['kHOS']++;
                        }
                        if($posf.data('Oral') && $max_position['kOral']== $posf.data('Oral')+1)
                        {
                            $max_position['kOral']--;
                        }
                    }else if($pr.data('spec_type')=='Urine' || $pr.data('spec_type')==''){
                        $newpos = $pr.data('pos');
                    }else if($pr.data('btype')=='tox'){
                        $newpos = $posf.data('k0');
                        if(tr_exists_position($newpos))
                        {
                            $newpos = false;
                        }
                        if(!$newpos)
                        {
                            $newpos = ++$max_position['k0'];
                            $posf.data('k0',$newpos);
                        }else if($max_position['k0']==$newpos){
                            $max_position['k0']++;
                        }
                    }
                    console.log($max_position);
                    if($newpos)
                    {
                        $posf.val($newpos);
                    }

                })
                .on('click','tr.parent_c',function(e){
                    if(e.target.tagName!='TD' && e.target.tagName!='TR' && e.target.tagName!='I')return;
                    $('tr.child').hide();
                    $('#rec_tbl_add').removeClass('show_all');
                    if($(this).data('showed')!='1')
                    {
                        $('tr.p_'+$(this).data('p')).show();
                        $('tr.p_'+$(this).data('p')).find('.barcode_auto').focus();
                        $('tr.parent_c').data('showed','0');
                        $(this).data('showed','1');
                        $(this).addClass('active');
                    }else{
                        $('tr.p_'+$(this).data('p')).hide();
                        $(this).data('showed','0');
                        $(this).removeClass('active');
                    }
                    return false;
                })
                .click(function(){
                    document.getElementById('beep_player').pause();
                })


            $('.show_hide_all').click(function(){
                var $tbl =  $('#rec_tbl_add');
                var $this = $(this);
                if($tbl.hasClass('show_all')){
                    $tbl.removeClass('show_all');
                    $this.html('Show All');
                }else{
                    $tbl.addClass('show_all');
                    $this.html('Hide All');
                }
            })

        })
    </script>

</div>

