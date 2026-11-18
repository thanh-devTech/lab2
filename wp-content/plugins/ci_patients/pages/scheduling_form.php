<?php

if(!empty($form_options['text_btime'])){
    echo '<div>'.stripslashes($form_options['text_btime']).'</div>';
}
?>
<div class="frow">
    <?php
    if(count($locations)>0 && !$showed_locations)
    {
        ?>
        <div class="frow" id="location_div">
            <label>Location: </label>
            <select name="location" style="width:434px" required>
                <option value="">Select Location</option>
                <?php
                if(!isset($location['pids']) || !is_array($location['pids']))
                {
	                $location['pids'] = array();
                }
                foreach($locations as $location)
                {
	                $moreopt = '';
                    if($form_options['enable_pbyloc']){
	                    $moreopt = implode(',',$location['pids']);
                    }
                    echo '<option value="'.$location['key'].'" data-pids="'.$moreopt.'">'.$location['name'].'</option>';
                }
                ?>
            </select>
        </div>
    <?php
    }
    ?>

	<div id="time_div">
        <div class="col2">
            <div class="calendardiv" id='calendar'></div>

        </div>
        <div class="col2">
            <div class="schedulingsdiv" id="schedulings"></div>
        </div>
    </div>
</div>

<input type="hidden" class="docinput" name="bat[doc]" value="" required/>

<?php

if(!empty($form_options['text_bltime'])){
	echo '<div>'.stripslashes($form_options['text_bltime']).'</div>';
}
