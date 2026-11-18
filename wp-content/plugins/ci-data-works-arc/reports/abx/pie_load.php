<div class="wrap_infection">
	<div class="wrap_load_composition">
		<div class="load_composition very_high">
			<div class="text">VERY HIGH</div>
		</div>
		<div class="load_composition high">
			<div class="text">HIGH</div>
		</div>
		<div class="load_composition medium">
			<div class="text">MEDIUM</div>
		</div>
		<div class="load_composition low">
			<div class="text">LOW</div>
		</div>
		<div class="load_composition very_low">
			<div class="text">V.LOW</div>
		</div>
	</div>
	<div class="overimage" style="min-height: 180px;">
		<?php if (!empty($healthchartimage)) { ?>
			<img style="width:180px" src="data:image/png;base64,<?php echo (base64_encode($healthchartimage)); ?>" />
		<?php } ?>
	</div>
</div>