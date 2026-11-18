<?php
global $ci_all_btypes;
$inbtypes = array('pcr', 'blood', 'dna', 'tox', 'gene');
if (tr_get_option('ci_pdf_data', 'req_multi_add') && count($ci_all_btypes) > 1 && in_array($btype, $inbtypes)) {
	//$rel_ids 	= trpdd_get_batch_meta($mid,$btype,'_rel_ids',false);
	//$rel_id 	= trpdd_get_batch_meta($mid,$btype,'_rel_id',false);

	if (empty($mid)) {
		echo '<div class="frow ilabel add_multi_btype">';
		echo 'Add to: ';
		echo '<span class="group_radio">';
		foreach ($ci_all_btypes as $bt) {
			if (in_array($bt, array($btype, 'study')) || !in_array($bt, $inbtypes)) continue;
			echo '<label>';
			echo '<input type="checkbox" name="add_multi_btype[]" value="' . $bt . '"/> ' . tr_pdd_get_typename($bt);
			echo '</label> ';
		}
		echo '</span>';
		echo '</div>';
	}
}

if (tr_get_option('ci_pdf_data', 'enable_ttmileage')) {
	$tt_mileage = trpdd_get_batch_meta($mid, $btype, 'tt_mileage');
	echo '<div class="frow">';
	echo '<h4>Total Mileage</h4>';
	echo '<div class="col2">';
	echo '<input type="text" name="batmeta[tt_mileage]" value="' . $tt_mileage . '"/>';
	echo '</div>';
	echo '</div>';
}
