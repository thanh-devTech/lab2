jQuery.fn.admin_accordion = function () {
    var $ = jQuery;
    var $tbl = $(this);
    var $last_tr_parent_id = 0;
    var $column_count = 0;

    jQuery.fn.load_data = function () {
        var $tr = $(this);
        var $dataid = $tr.data('id');
        var $trid = $tr.data('trid');
        var $html = '<tr class="child active loading_row"><td colspan="' + $column_count + '"><div class="loading center"></div></td></tr>';
        $($html).insertAfter($tr);
        //remove child 
        $('tr.trparent_'+$trid).remove();
        $.ajax({
            type: 'post',
            dataType: 'json',
            data: { trajax: 1, act: 'load_table_child', id: $dataid },
            success: function (json) {
                $('tr.loading_row',$tbl).remove();

                if (json.status == 'ok') {
                    for(i in json.rows)
                    {
                        var $row = $(json.rows[i]);
                        $row.addClass('active child trparent_'+$trid);
                        $row.insertAfter($tr);
                    }
                } else {
                    setTimeout(function () { $tr.load_data() }, 2000);
                }
            },
            error: function () {
                $('tr.loading_row',$tbl).remove();
                setTimeout(function () { $tr.load_data() }, 2000);
            }
        });
        return true;
    }

    function count_columns() {
        var $tr = $('tr:nth-child(1)', $tbl);
        $('th', $tr).each(function () {
            var $colspan = $(this).attr('colspan') || 1;
            $column_count += $colspan;
        });
    }

    function trigger() {
        $tbl.on('click', 'tr.parent', function (e) {
            if (e.target.tagName != 'TD' && e.target.tagName != 'TR' && e.target.tagName != 'I') return;
            var $tr = $(this);
            var $trid = $tr.data('trid');
            if(!$trid)
            {
                $trid = $last_tr_parent_id++;
                $tr.data('trid',$trid);
            }
             
            if ($tr.hasClass('active')) {
                $tr.removeClass('active');
                $('tr.child.active', $tbl).removeClass('active');
                $('tr.trparent_'+$trid).remove();
                return false;
            }
            
            $('tr.parent.active', $tbl).removeClass('active');
            $('tr.child.active', $tbl).removeClass('active');
            $tr.addClass('active');
            $tr.load_data();
            return false;
        }).on('click','.baction',function(){
            var $tr = $(this).parents('tr.parent');
            
            if($tr.length==0)
            {
                $tr = $(this).parents('tr.child');
            }
            var $btn = $(this);
            var $act = $btn.data('action');
            var $type = $btn.data('type');
            var $id = $tr.data('id');
            var $stext = $btn.data('stext')||'';
            var $confirmtext = $btn.data('confirm')||'';
            if($stext && $btn.data('oldtext')==$stext)return false;
            if($confirmtext && !confirm($confirmtext))
            {
                return false;
            }
            $btn.data('oldtext',$btn.html());
            if($stext)
            {
                $btn.html($stext);
            }

            if($type=='iframe'){
                $frameid = 'frame_'+$act;
                $('iframe#'+$frameid).remove();
                $url = location.href+'&trajax='+$act+'&act='+$act+'&id='+$id;
                var $html='<iframe id="'+$frameid+'" style="width:900px;height:300px;position:absolute;left:-99999px" src="'+$url+'"></iframe>';
                $('body').append($html);
                setTimeout(function(){
                    $btn.html($btn.data('oldtext'));
                },2000);
            }else{
                $.ajax({
                    type:'post',
                    dataType:'json',
                    data:{trajax:$act,act:$act,id:$id},
                    success:function(json)
                    {
                        if(json.reload)
                        {
                            location.reload();
                        }else if(json.removeE)
                        {
                            for(i in json.removeE)
                            {
                                $(json.removeE[i]).remove();
                            }
                            
                        }
                        $btn.html($btn.data('oldtext'));
                    },
                    error:function(){

                    }
                })
            }
            return false;
        })
    }

    function setup()
    {
        $tbl.addClass('accordion_tbl');
        count_columns();
        trigger();
    }
    setup();
}

