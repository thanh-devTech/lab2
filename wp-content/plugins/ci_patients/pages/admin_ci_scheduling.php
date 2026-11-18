<?php
global $wpdb;

$tabs = array(
	'main' => 'Main',
	'concierge'=> 'Concierge',
	'canada'=> 'Canada',
);

$pa_options = get_option('trpatient_settings',array());
if(isset($pa_options['formtypes']) && is_array($pa_options['formtypes'])){
	foreach($pa_options['formtypes'] as $arr)
	{
	    $key = 'cform_'.$arr['value'];
		$tabs[$key] = $arr['title'];

	}
}


if(!isset($pa_options['enable_concierge']) || !$pa_options['enable_concierge'] ){
	unset($tabs['concierge']);
}

if(!isset($pa_options['enable_canada']) || !$pa_options['enable_canada'] ){
	unset($tabs['canada']);
}


$tab = isset($_REQUEST['tab'])? $_REQUEST['tab']: key($tabs);
if(!isset($tabs[$tab])){
	$tab = key($tabs);
}
if(count($tabs)>1 && !isset($_REQUEST['trajax'])){
	?>
    <div class="wrap">
        <h2 class="nav-tab-wrapper ">
			<?php
			foreach($tabs as $k => $txt){
				$class = $tab == $k ? 'nav-tab-active':'';
				$link = Tr_Base_Class_V4::link(array('tab'=>$k,'act'=>'','id'=>'','batch_id'=>'','view'=>'','s'=>'','search'=>'','paged'=>'','from'=>'','to'=>'','summary'=>'','orderby'=>'','order'=>'','assen'=>'','status'=>''),false);
				echo '<a class="nav-tab '.$class.'" href="'.$link.'">'.$txt.'</a>';
			}
			?>
        </h2>
    </div>
	<?php
}

$custom_form = '';
$form_key = 'trpatient_product_settings';
$scheduling_tbl = 'ci_scheduling';

if(!empty($tab) && $tab!='main'){
	$custom_form = $tab;
	$form_key = 'trpatient_'.$custom_form.'_settings';
	$scheduling_tbl = 'ci_scheduling_'.$custom_form;
}

//check scheduling table
$tbl_exist = $wpdb->get_var("select count(*) from {$scheduling_tbl}");
if(!$tbl_exist)
{
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	$sql_tbl = "CREATE TABLE `{$scheduling_tbl}` (
    `ID` int(11) NOT NULL AUTO_INCREMENT,
    edate date,
    etime varchar(6),
    max_number int(5) default 0,
    count_req int(5) default 0,
    created datetime,
  PRIMARY KEY (`ID`),
  KEY `edate` (`edate`),
  KEY `max_number` (`max_number`),
  KEY `count_req` (`count_req`)
) ENGINE=InnoDB";
	$rs = dbDelta($sql_tbl);
}


$form_options = get_option($form_key, array());

$locations = array();
if($form_options['enable_location'])
{
	$locations = is_array($form_options['locations']) ? $form_options['locations']:array();
}


wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_style( 'jquery-ui', TRCPDD_URL. 'admin/css/jquery-ui.css',array(),'1.1.9' );
// FullCalendar v6: single global bundle; CSS is injected by the JS (no separate stylesheet).
wp_enqueue_script('fooevents-calendar-full-callendar',TRCPPR_URL. 'fullcalendar/index.global.min.js',array(),'6.1.21'  );
add_thickbox();
?>
<div class="wrap">
    <h2 style="text-align: center">Scheduling <?php echo $tabs[$tab]?></h2>
    <br>
    <form method="post" id="formeventdaterange" >
        <div class="wapformadd" style="background:#fff;padding:10px;border-radius:6px;margin:10px auto;text-align: center">

            <input type="text" name="date" autocomplete="off" class="hasdate date_start" placeholder="yyyy-mm-dd" style="width:100px" required/>
            <span class="edate">to:</span>
            <input type="text" name="enddate" autocomplete="off" class="hasdate date_end"  placeholder="yyyy-mm-dd" style="width:100px" />

            <select name="weekday">
                <option value="">Week day</option>
                <?php
                for($i=1;$i<=7;$i++)
                {
                    echo '<option value="'.$i.'">'.jddayofweek($i-1,1).'</option>';
                }
                ?>
            </select>
            <span class="stime">
            <select class="time_start" name="time_start" style="float:none" required>
                <?php
                for($i=5;$i<=23;$i++)
                {
	                for($j=0;$j<60;$j+=15){

		                $key = tr_get_key_hourmin($i,$j);
		                $text = tr_display_hourmin($key);
		                echo '<option value="'.$key.'">'.$text.'</option>';
		                if($i==24)break;
	                }
                }
                ?>
                <option value="auto">Auto</option>
            </select>
           </span>

            <span class="stime">
            <label>to</label>
            <select class="time_end" name="time_end" style="float:none" required>
                <?php
                for($i=6;$i<=24;$i++)
                {
	                for($j=0;$j<60;$j+=15){
		                $apm = ($i<12 || $i==24)? 'AM':'PM';
		                $hour = ($i>12)? $i - 12 : $i;
		                $hour = ($hour<10)? '0'.$hour : $hour;
		                $hour24 = ($i<10)? '0'.$i:$i;
		                $min = ($j<10)? '0'.$j:$j;
		                $key = $hour24.':'.$min;
		                $text = $hour.':'.$min.' '.$apm;
		                echo '<option value="'.$key.'">'.$text.'</option>';
		                if($i==24)break;
	                }
                }
                ?>
                <option value="auto">Auto</option>
            </select>
            </span>
            <select class="eachtime" name="eachtime" style="float:none">
                <option value="15">Step 15 mins</option>
                <option value="30">Step 30 mins</option>
                <option value="45">Step 45 mins</option>
                <option value="60">Step 1 hr</option>
                <option value="120">Step 2 hrs</option>
            </select>
            <input type="number" class="max_number" name="max_number" required placeholder="Max Patients" style="width:120px"/>
			<?php
			if(count($locations)>0)
			{
				?>
                <select name="location" class="location" style="width:100px">
                    <option value="">All Location</option>
					<?php
					foreach($locations as $location)
					{
						echo '<option value="'.$location['key'].'">'.$location['key'].'. '.$location['name'].'</option>';
					}
					?>
                </select>
				<?php
			}
			?>
            <input type="submit" class="button-primary" value="Add"/>
            <input type="button" class="button removebtn" value="Delete"/>

        </div>

        <input type="hidden" name="custom_form" value="<?php echo $custom_form?>"/>
    </form>

    <div id='calendar'></div>


</div>


<style>
    table .fc-event-time{
        display:none;
    }
    #TB_ajaxContent{
        min-height:94%;
    }
    table td.fc-day, table td.fc-day span{
        cursor: pointer;
    }
    table td.fc-day:hover{
        background: rgba(0, 168, 232, 0.2);
    }
    table td input{
        max-width:94%;
    }
    table .evtrow input.qty{
        width:60px;
    }
    .fc-toolbar-title{
        cursor: pointer;
    }
    .red{
        color: red;
    }
    #ui-datepicker-div.ui-widget-content{
        z-index:999 !important;
    }
    table#eventtbl th{
        padding-left:1px;
        padding-right:1px;
        min-width:38px;
    }
    table#eventtbl td{
        padding:1px 0;
        white-space:nowrap;
    }
    table#eventtbl td input[type="number"]{
        width:70px;
    }
    table#eventtbl td input[type="text"]{
        padding:0;
        max-width:80%;
    }
    .fc-daygrid-dot-event .fc-event-title{
        font-size:10px;
    }
    .fc-daygrid-event-dot{
        margin:0 2px;
    }
</style>
<script>

    var $locations = <?php echo json_encode($locations)?>;
    function min2hourmin(num) {
        num = parseInt(num);
        var hours = (num / 60);
        var rhours = Math.floor(hours);
        var minutes = (hours - rhours) * 60;
        var rminutes = Math.round(minutes);
        return (rhours<10?'0'+rhours:rhours) + ":" + (rminutes<10?'0'+rminutes:rminutes) ;
    }

    function hourmin2min($str)
    {
        var $arr = $str.split(':');
        return parseInt($arr[0])*60 + parseInt($arr[1]);
    }

    jQuery(function ($) {
        jQuery.fn.tagName = function() {
            return this.prop("tagName");
        };
        var $custom_form = $('[name="custom_form"]').val();

        var calendarEl = document.getElementById('calendar');

        var calendar = new FullCalendar.Calendar(calendarEl, {
            //timeZone: 'UTC',
            initialView: 'dayGridMonth', // FullCalendar v6 (was v4/v5 'defaultView')
            events: '?tr_act=scheduling_load_events&custom_form='+$custom_form,
            height:'auto',
            selectable:true,
            select:function (selectionInfo) {
                console.log(selectionInfo);

                selectionInfo.endStr = selectionInfo.end.toString().slice(0, 10);

                var $range = selectionInfo.startStr;
                if(selectionInfo.startStr!=selectionInfo.endStr)
                {
                    // $range+=' - '+selectionInfo.endStr;
                }
                var $url = '?tr_act=scheduling_edit_event&date='+selectionInfo.startStr+'&width=1200&custom_form='+$custom_form;
                tb_show('Date: '+$range,$url);
            }
        });

        calendar.render();

        $('body').on('click','.fc-view td.fc-day',function () {
            var $date = $(this).data('date');
            var $url = '?tr_act=scheduling_edit_event&date='+$date+'&custom_form='+$custom_form;
            //tb_show('Date '+$date,$url);
        })
            .on('click','a.addevent',function () {
            var $f = $('#formevent');
            var time_start = $('.time_start',$f).val();
            var time_end = $('.time_end',$f).val();
            var $eachm = parseInt($('.eachtime',$f).val());
            var $max_number = $('.max_number',$f).val()!==''? parseInt($('.max_number',$f).val()):0;

            if(time_start=='' || time_end=='' || time_start==time_end){
                return false;
            }

            var $startmin = hourmin2min(time_start);
            var $endmin = hourmin2min(time_end);
            for(var i = $startmin;i<=$endmin;i+=$eachm)
            {
                var $time = min2hourmin(i);
                var $exist = false;

                $('#eventtbl input[name*="evtime"]').each(function () {
                    if($time == $(this).val())
                    {
                        $exist = true;
                    }
                })
                if(!$exist){
                    var $opt = {etime:$time,max_number:$max_number};
                    if($locations)
                    {
                        for(var ilo in $locations)
                        {
                            $opt['location_'+$locations[ilo].key] = $max_number;
                        }
                    }

                    $.traddrow($opt);
                }

            }
            $('.time_start').val('00:00');
            $('.time_end').val('00:00');
            return false;
        })
            .on('click','a.removet',function () {
            var $pr = $(this).parents('tr.evtrow');
            var $id = $pr.data('id');
            if($id)
            {
                var $list = $('[name="removelist"]').val();
                $list+= ','+ $id;
                $('[name="removelist"]').val($list);
            }
            $pr.remove();

        })
            .on('submit','form#formevent',function () {
            var $btn = $('[type="submit"]',$(this));
            $btn.val('Saving...');
            $.ajax({
                data:$(this).serialize(),
                type:'post',
                dataType:'json',
                success:function (json) {
                    $btn.val('Save');
                    if(json.status!='ok'){
                        alert('Error. Please Try again');
                    }else{
                        calendar.refetchEvents()
                    }
                },
                error:function () {
                    $btn.val('Save');
                    alert('Error. Please Try again');
                }
            })
            return false;
        })
            .on('submit','form#formeventdaterange',function () {
            var $btn = $('[type="submit"]',$(this));
            if($btn.val()=='Saving...')return false;
            $btn.data('oldtext',$btn.val());
            $btn.val('Saving...');
            $.ajax({
                data:$(this).serialize()+'&tr_act=scheduling_save_event',
                type:'post',
                dataType:'json',
                success:function (json) {
                    $btn.val($btn.data('oldtext'));
                    if(json.status!='ok'){
                        alert('Error. Please Try again');
                    }else{
                        calendar.refetchEvents()
                    }
                },
                error:function () {
                    $btn.val($btn.data('oldtext'));
                    alert('Error. Please Try again');
                }
            })
            return false;
        })
            .on('click','.fc-toolbar-title',function () {
            var $date = calendar.getDate();
            var $y = $date.getFullYear();
            var $m = ($date.getMonth()+1);
            var $my = $y+'-'+($m<10? '0'+$m:$m);
            var $url = '?tr_act=scheduling_edit_event&date='+$my+'&custom_form='+$custom_form;
            tb_show($(this).html(),$url);
        })
            .on('click','.removebtn',function () {
            if(confirm("Do you delete by date range?")==false)return false;
            var $form = $(this).parents('form');
            var $btn = $(this);
            if($btn.val()=='Deleting...')return false;
            $btn.data('oldtext',$btn.val());
            $btn.val('Deleting...');
            $.ajax({
                data:$form.serialize()+'&tr_act=scheduling_delete',
                type:'post',
                dataType:'json',
                success:function (json) {
                    $btn.val($btn.data('oldtext'));
                    if(json.status!='ok'){
                        alert('Error. Please Try again');
                    }else{
                        calendar.refetchEvents()
                    }
                },
                error:function () {
                    $btn.val($btn.data('oldtext'));
                    alert('Error. Please Try again');
                }
            })
            return false;
        })
            .on('click','.fc-event-title',function () {
                var $td = $(this).parents('td');
                var $date = $td.data('date');
                var $url = '?tr_act=scheduling_edit_event&date='+$date+'&width=1200&custom_form='+$custom_form;
                tb_show('Date: '+$date,$url);
            })

        $('.hasdate').datepicker({dateFormat:'yy-mm-dd'});

        $.tr_display_hourmin = function ($time) {

            var $arr = $time.split(':');
            var hours = parseInt($arr[0]);
            var minutes = parseInt($arr[1]);
            var ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12; // the hour '0' should be '12'
            minutes = minutes < 10 ? '0'+minutes : minutes;
            var strTime = hours + ':' + minutes + ' ' + ampm;
            return strTime;
        }

        $.traddrow = function($data)
        {
            var $time = $data.etime ? $data.etime: '';
            var $id = $data.ID ? $data.ID : '';

            var $row = $($('table#rawtable tbody').html());
            $('[name="evtime[]"]',$row).val($time);
            $('.timec',$row).html($.tr_display_hourmin($time));

            for(var i in $data)
            {
                var $obj = $('.'+i,$row);
                if($obj.length>0)
                {
                    if($obj.tagName()=='SPAN'){
                        $('.'+i,$row).html($data[i]);
                    }else{
                        $('.'+i,$row).val($data[i]);
                    }
                }

            }
            $row.data('id',$id);

            $('#eventtbl tbody').append($row);
        }

    })

</script>

