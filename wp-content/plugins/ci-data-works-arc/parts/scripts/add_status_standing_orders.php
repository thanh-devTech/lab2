<?php

if (tr_get_option('ci_req_settings_blood', 'Phlebotomist') && in_array('blood', tr_pdd_get_batch_types())) {

	$defaultarr = array(
		'assigned' => 'Assigned',
		'collected' => 'Collected',
		'not_collected' => 'Not Collected',
		'refused' => 'Refused',
		'stopped' => 'Stopped'
	);
	if (tr_get_option('ci_req_settings_blood', 'phle_d_medicare')) {
		$defaultarr = array(
			'assigned' => 'Checked-In',
			'collected' => 'Collected',
		);
	}
	$old_statusmap = array(
		'assigned' => 'so_assigned',
		'Collected' => 'so_collected',
		'Not Collected' => 'so_not_collected',
		'refused' => 'so_refused',
		'stopped' => 'so_stopped'
	);

	foreach (tr_pdd_get_batch_types() as $btype) {
		$exist = $wpdb->get_var("select count(*) from ci_batches_status 
				where btype = '$btype' and s_type = 'so'");
		if (!$exist) {
			foreach ($defaultarr as $key => $value) {
				//check exist first
				$status_id = $wpdb->get_var("select ID from ci_batches_status 
						where s_text = '$value' 
						and btype = '$btype' ");
				if ($status_id) {
					$wpdb->update('ci_batches_status', array(
						's_type' => 'so',
						'iorder' => 11
					), array(
						'ID' => $status_id
					));
				} else {
					$wpdb->insert('ci_batches_status', array(
						's_key' => $key,
						's_text' => $value,
						's_type' => 'so',
						'btype' => $btype,
						'iorder' => 11,
					));
				}
			}

			//update meta
			$meta_tbl = tr_pdd_get_table_by_btype($btype) . '_meta';
			foreach ($old_statusmap as $old => $new) {
				$wpdb->query("update {$meta_tbl} set meta_value='$new' where meta_key = 'status' and meta_value = '$old' ");
			}
		}
	}
} else {
	$wpdb->query("delete from ci_batches_status where s_type = 'so' ");
}
