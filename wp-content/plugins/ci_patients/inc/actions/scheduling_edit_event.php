<?php
global $wpdb;

if(!user_can(get_current_user_id(),'administrator'))exit;

$custom_form = isset($_REQUEST['custom_form']) && !empty($_REQUEST['custom_form'])? $_REQUEST['custom_form'] : '';

$form_key = 'trpatient_product_settings';
$scheduling_tbl = 'ci_scheduling';
if(!empty($custom_form))
{
	$form_key = 'trpatient_'.$custom_form.'_settings';
	$scheduling_tbl = 'ci_scheduling_'.$custom_form;
}

$form_options = get_option($form_key, array());

$locations = array();
if($form_options['enable_location'])
{
	$locations = is_array($form_options['locations']) ? $form_options['locations']:array();
}

$date = $_REQUEST['date'];
$enddate = $_REQUEST['enddate'];

$where = " edate='{$date}' ";
if($enddate>$date)
{
	$where.= " 1=2 ";
}


$schedulings = $wpdb->get_results(
        "select * from {$scheduling_tbl} 
                where {$where}
                order by etime asc");
?>

    <form method="post" id="formevent" style="position:relative;">
        <div class="wapformadd">
		<span class="stime">
            <label>Start</label>
        <select class="time_start" style="float:none">
			<?php
			for($i=6;$i<=23;$i++)
			{
				for($j=0;$j<60;$j+=15){

					$key = tr_get_key_hourmin($i,$j);
					$text = tr_display_hourmin($key);
					echo '<option value="'.$key.'">'.$text.'</option>';
					if($i==24)break;
				}
			}
			?>
        </select>
       </span>

            <span class="stime">
            <label>End</label>
        <select class="time_end" style="float:none">
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
        </select>
        </span>
            <select class="eachtime" style="float:none">
                <option value="15">Step 15 mins</option>
                <option value="30">Step 30 mins</option>
                <option value="45">Step 45 mins</option>
                <option value="60">Step 1 hr</option>
                <option value="120">Step 2 hrs</option>
            </select>
            <input type="number" class="max_number" placeholder="Max Patients" style="width:120px"/>
            <a class="button addevent">Add</a>

            <input type="submit" class="button-primary" value="Save" style="float:right;"/>

        </div>

        <div style="<?php if(count($locations)>20)echo 'width:200%'?>">
            <div class="options_group" style="margin:10px 0">
                <table id="eventtbl" class="wp-list-table widefat  posts">
                    <thead>
                    <tr>
                        <th style="<?php if(count($locations)>6)echo 'width:40px !important;'?>" class="column-primary">Time</th>
						<?php
						if(count($locations)>0)
						{
							foreach($locations as $location)
							{
								echo '<th title="'.$location['name'].'">'.$location['key'].'</th>';
							}
						}
						else{
							?>
                            <th>Max Patients</th>
                            <th>Registered</th>
							<?php
						}
						?>

                        <th style="width:38px !important;"></th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>

            </div>
        </div>
        <input type="hidden" name="tr_act" value="scheduling_save_event"/>
        <input type="hidden" name="date" value="<?php echo $date?>"/>
        <input type="hidden" name="enddate" value="<?php echo $enddate?>"/>
        <input type="hidden" name="removelist" value=""/>
        <input type="hidden" name="custom_form" value="<?php echo $custom_form?>"/>
    </form>

    <table id="rawtable" style="display: none;">
        <tbody>
        <tr class="evtrow is-expanded">
            <td class="column-primary"><input type="hidden" name="evtime[]" readonly/><span class="timec"></span></td>
			<?php
			if(count($locations)>0)
			{
				foreach($locations as $location)
				{
					?>
                    <td class="location_<?php echo $location['key']?>">
                        <input class="location_<?php echo $location['key']?>" type="text" name="location_<?php echo $location['key']?>[]" value=""/>
                        <span class="red location_count_<?php echo $location['key']?>"></span>
                    </td>
					<?php
				}
			}else{
				?>
                <td><input class="max_number" type="number" min=1 name="max_number[]" value=""/></td>
                <td><span class="red count_req"></span></td>
				<?php
			}
			?>

            <td><a class="removet button">-</a></td>
        </tr>
        </tbody>
    </table>


    <script>
        var $ = jQuery;
        var $schedulings = <?php echo json_encode($schedulings)?>;
        for(i in $schedulings)
        {
            $.traddrow($schedulings[i]);
        }
    </script>
    </div>
<?php
exit;