<?php
$descs = $wpdb->get_results("select * from ci_abx_texts where ctype = 'bvg' order by ID asc");
foreach ($descs as $d) {
	$bacs = explode(',', $d->condition1);
	$bacteiranames = array();
	foreach ($bacs as $bid) {
		if (isset($abx->selectedBacterias[$bid])) {
			$bat = $abx->selectedBacterias[$bid];
			if ($bat->bv_type == 'opportunistic') {
				continue;
			}
			$bacteiranames[] = $bat->name;
		}
	}
	if (empty($bacteiranames) || empty($d->desc_1)) {
		continue;
	}
?>
	<div class="bvdesctbl">
		<div class="bvdtitle"><span class="bvdicon bvd_<?php echo $d->condition2 ?>"></span> <?php echo implode(', ', $bacteiranames) ?></div>
		<div class="bvdcontent"><?php echo $d->desc_1 ?></div>
	</div>
<?php
}
