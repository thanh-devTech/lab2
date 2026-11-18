<?php
if (isset($_GET['ci_setup'])) {
	echo '<h3>Updating statuses...' . $only_btype . '</h3>';
}
try {

	$btypes2 = tr_pdd_get_batch_types();
	if (isset($only_btype)) {
		$btypes2 = [$only_btype];
	}

	include_once(TRLCN_PATH . '/api/ci_lab_client.php');
	if (class_exists('CI_Lab_Client_Api')) {
		$client = new CI_Lab_Client_Api(true);
		$rs = $client->get_statuses([]);

		if (!empty($rs) && !empty($rs['statuses'])) {
			$all_status = $rs['statuses'];
			foreach ($btypes2 as $btype2) {
				$statuses = isset($all_status[$btype2]) ? $all_status[$btype2] : [];
				if (!empty($statuses)) {
					$wpdb->delete('ci_batches_status', ['btype' => $btype2]);
					foreach ($statuses as $row) {
						$wpdb->insert('ci_batches_status', [
							's_key'              => $row['s_key'],
							's_text'             => $row['s_text'],
							's_type'             => $row['s_type'],
							'btype'              => $btype2,
							'istatus'            => $row['istatus'],
							'iorder'             => $row['iorder'],
							'show_in_req_form'   => $row['show_in_req_form'],
							'show_in_inbound'    => $row['show_in_inbound'],
							'show_in_result_form' => $row['show_in_result_form'],
						]);
					}
				}
			}
		}
	}
} catch (Exception $e) {
	// Handle the exception or log it
}
