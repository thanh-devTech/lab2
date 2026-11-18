<?php
$showed_locations = true;
$location_store_number = '';
?>
<div id="location_form">
	<div class="frow blabel">

		<div class="col4">
			<label>Store Location Number </label>
			<input type="number" name="location_store_number" value="<?php echo @$location_store_number ?>"
			       required maxlength="3"/>
		</div>
	</div>
</div>