<?php
global $current_role;

if(isset($_REQUEST['trajax'])){
    $json = array();

    $datefrom = date('Y-m-d',strtotime($_REQUEST['start']));
    $dateto = date('Y-m-d',strtotime($_REQUEST['end']));

    $btypes = tr_pdd_get_batch_types();
    $tmp_array = array();
    $where_cli = '';
    $where = '';
    if(count($rep_ids)>0)
    {
        $rep_ids = implode(',',$rep_ids);
        $where_cli.= " and CLI.`cli_rep-ID` in ({$rep_ids}) ";
    }
    else{
        switch($current_role)
        {
            case 'lab_rep':
            case 'lab_rep_w2':
                $rep_id = get_user_meta(get_current_user_id(),'_rep_id',true);
            $where_cli.= " and CLI.`cli_rep-ID` = {$rep_id} ";
                break;
        }
    }

    if(!empty($where_cli))
    {
        $cIds = $wpdb->get_col("select cID from ci_clinic CLI where 1 {$where_cli} ");
        $cIds = implode(',',$cIds);
        $where.= " and ba.cli_id in ({$cIds}) ";
    }


    foreach($btypes as $btype)
    {
        $table = tr_pdd_get_table_by_btype($btype);
        $typename = tr_pdd_get_typename($btype);
        $sql = "select DATE_FORMAT(ba.spec_received,'%Y-%m-%d') AS ddoc,
                ba.requesting_practice,
                count(*) as count
                from {$table} ba
                where ba.spec_received >= '{$datefrom}' and ba.spec_received <='{$dateto} 23:59:59'
                    {$where}
                 group by ddoc, requesting_practice
                 ";
        $rows = $wpdb->get_results($sql);

        foreach($rows as $row)
        {
            if(!is_array($tmp_array[$row->ddoc]))
            {
                $tmp_array[$row->ddoc] = array('counts'=>array(),'desc'=>array());
            }
            if(!isset($tmp_array[$row->ddoc]['counts'][$typename]))
            {
                $tmp_array[$row->ddoc]['counts'][$typename] = 0;
            }
            if(!isset($tmp_array[$row->ddoc]['desc'][$typename]))
            {
                $tmp_array[$row->ddoc]['desc'][$typename] = '';
            }
            $tmp_array[$row->ddoc]['counts'][$typename] += intval($row->count);

            if(empty($row->requesting_practice))
            {
                $row->requesting_practice = 'Unknow';
            }
            $tmp_array[$row->ddoc]['desc'][$typename].= '<div class="tt_row"><span class="tt">'.$row->requesting_practice.'</span><span class="count">'.$row->count.'</span></div>';
        }
    }

    foreach($tmp_array as $date => $arr)
    {
        $json[] = array(
            'start' => $date,
            'counts' => $arr['counts'],
            'desc' => $arr['desc'],
        );
    }

    wp_send_json($json);
}





wp_enqueue_style( 'jquery-ui', TRCPDD_URL. 'css/jquery-ui.css',array(),'1.1.9' );
wp_enqueue_style( 'data-fullcalendar', TRCPDD_URL.'fullcalendar/core/main.min.css',array(),TRCPDD_VS );
wp_enqueue_style( 'data-daygrid', TRCPDD_URL.'fullcalendar/daygrid/main.min.css',array(),TRCPDD_VS );

wp_enqueue_script('jquery-ui-tooltip');
wp_enqueue_script('data-fullcalendar',TRCPDD_URL. 'fullcalendar/core/main.min.js',array(),TRCPDD_VS );
wp_enqueue_script('data-interaction',TRCPDD_URL. 'fullcalendar/interaction/main.min.js',array(),TRCPDD_VS );
wp_enqueue_script('data-daygrid',TRCPDD_URL. 'fullcalendar/daygrid/main.min.js',array(),TRCPDD_VS );
//wp_enqueue_script('data-moment',TRCPDD_URL. 'fullcalendar/moment/main.min.js',array(),TRCPDD_VS );
//wp_enqueue_script('data-luxon',TRCPDD_URL. 'fullcalendar/luxon/main.min.js',array(),TRCPDD_VS );
?>

<div class="wrap wrap_cal_report">
    <div style="background:#fff;padding:20px;">

    <div id='calendar'></div>
    </div>
</div>


<script>
    var $ = jQuery;
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');

        var calendar = new FullCalendar.Calendar(calendarEl, {
            plugins: [ 'dayGrid'],
            //timeZone: 'UTC',
            defaultView: 'dayGridMonth',
            events: '<?php echo '?page='.$_GET['page'].'&tab='.$_GET['tab'].'&trajax=1'?>',
            eventRender: function (info) {
                var $wrap = $('<div class="cal_wrap"></div>');
                var $total = 0;
                for(var i in info.event.extendedProps.counts)
                {
                    var $count = info.event.extendedProps.counts[i];
                    var $desc = '<div class="wrap_toltip">'+info.event.extendedProps.desc[i]+'</div>';
                    $total+= $count;
                    var $drow = $("<div class='cal_row' title=' '><span class='tt'>"+i+"</span><span class='count'>"+$count+"</span></div>");
                    $drow.data('desc',$desc);
                    $wrap.append($drow);

                    $drow.tooltip({
                        content: function () {
                            return $(this).data('desc');
                        }
                    });
                }
                $wrap.append("<div class='cal_total'>"+$total+"</div>");

                return $wrap;
            },
            dayRender: function (dayRenderInfo) {
                buildMonthList();
            }
        });
        calendar.render();
        $('<select id="month"></select>').prependTo('#calendar .fc-right');

        $('#month').on('change', function() {
            var myDate = new Date($(this).val());
            calendar.gotoDate( myDate );
            buildMonthList();
        });

        buildMonthList();

        function buildMonthList() {
            $('#month').empty();
            var month = calendar.getDate();
            var initial = month.getFullYear()+'-'+( month.getMonth() + 1 );
            //month.add(-6, 'month');
            month.setMonth(month.getMonth()-20);

            for (var i = 0; i < 30; i++) {
                var opt = document.createElement('option');
                opt.value = month.getFullYear()+'-'+( month.getMonth() + 1 )+'-01';
                opt.text = ( month.getMonth() + 1 ) +'/'+ month.getFullYear();
                opt.selected = initial === month.getFullYear()+'-'+( month.getMonth() + 1 );
                $('#month').append(opt);
                month.setMonth(month.getMonth()+1);

            }
        }
    });

</script>