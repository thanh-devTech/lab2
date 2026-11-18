<?php

if (!empty($_FILES['importfile']['name'])) {
	$fileinfo = pathinfo($_FILES['importfile']['name']);
	$current_lab_id = CI_Base_Lab::get_current_lab_id();
	if (empty($current_lab_id)) {
		$current_lab_id = $wpdb->get_var("select lab_ID from ci_labs where lab_is_ref = 2 order by lab_ID asc limit 1");
	}
	if (empty($current_lab_id)) {
		$current_lab_id = $wpdb->get_var("select lab_ID from ci_labs order by lab_ID asc limit 1");
	}
	if ($fileinfo['extension'] == 'json') {
		$json = json_decode(file_get_contents($_FILES['importfile']['tmp_name']), true);

		$cols       = $wpdb->get_col("show columns from ci_clinic");
		$excludes   = array('cID', 'cli_lab-ID');

		$count_inserted = 0;
		$count_updated = 0;
		$count_error = 0;

		foreach ($json['clinics'] as $row) {
			$rowdata = array();
			foreach ($cols as $col) {
				if (isset($row[$col]) && !in_array($col, $excludes)) {
					$rowdata[$col] = $row[$col];
				}
			}

			$cid = $wpdb->get_var("select cID from ci_clinic
							where cli_clinic = '{$rowdata['cli_clinic']}' ");

			if ($cid > 0) {
				$exist_lab_id = $wpdb->get_var($wpdb->prepare("select `cli_lab-ID` from ci_clinic where cID = %d", $cid));
				if (empty($exist_lab_id)) {
					$rowdata['cli_lab-ID'] = $current_lab_id;
				}
				$wpdb->update('ci_clinic', $rowdata, array('cID' => $cid));
				$count_updated++;
			} else {
				$rowdata['cli_lab-ID'] = $current_lab_id;
				$wpdb->insert('ci_clinic', $rowdata);
				$cid = $wpdb->insert_id;
				$count_inserted++;
			}

			if ($cid > 0) {
				$wpdb->query("delete from ci_clinic_doc where cli_ID = {$cid}");
				foreach ($row['doctors'] as $j => $doc) {
					unset($doc['doc_ID']);
					$doc['cli_ID'] = $cid;
					if (empty($doc['doc_first'])) continue;

					tr_data_update_doc($doc);
				}

				$ma_ids = array(0);

				foreach ($row['managers'] as $ma) {
					unset($ma['ma_ID']);

					$ma['ma_cli_ID'] = $cid;
					if (empty($ma['ma_manager'])) continue;

					$ma_ID = 0;
					if (!empty($ma['ma_email'])) {
						$ma_ID = $wpdb->get_var($wpdb->prepare("select ma_ID from ci_clinic_manager where ma_email = %s", $ma['ma_email']));
					}

					if ($ma_ID > 0) {
						$rs = $wpdb->update('ci_clinic_manager', $ma, array('ma_ID' => $ma_ID));
					} else {
						$rs = $wpdb->insert('ci_clinic_manager', $ma);
						$ma_ID = $wpdb->insert_id;
					}

					if ($rs === false) {
						Tr_Session_Class::add_error('Error while update Office Manager: ' . $wpdb->last_error);
					}
					if ($ma_ID > 0) {
						$user_id = tr_check_and_create_office_manager_user($cid, $ma);
						$ma_ids[] = $ma_ID;

						if (tr_get_option('ci_pdf_data', 'off_see_all')) {
							update_user_meta($user_id, '_only_one_clinic', isset($rowdata['cli_only_one']) ? $rowdata['cli_only_one'] : 0);
						}
					}
				}
				$ma_ids = implode(',', $ma_ids);
				$wpdb->query("delete from ci_clinic_manager where ma_cli_ID = {$cid} and ma_ID not in ({$ma_ids})");

				foreach ($row['collectors'] as $col) {
					if (empty(implode('', $col))) {
						continue;
					}
					unset($col['col_ID']);
					$col_ID = 0;
					if (!empty($col['col_email'])) {
						$col_ID = $wpdb->get_var("select col_ID from ci_collector where col_email = '{$col['col_email']}'");
					}
					if ($col_ID == 0) {
						$col_ID = $wpdb->get_var("select col.col_ID from ci_collector col
								JOIN ci_clinic_collector cc ON cc.col_ID = col.col_ID
								where cli_ID = {$cid}
								and col.col_first = '{$col['col_first']}'
								and col.col_last = '{$col['col_last']}' ");
					}

					if ($col_ID > 0) {
						$wpdb->update('ci_collector', $col, array('col_ID' => $col_ID));
						tr_pdd_create_user_collector($col, false);
					} else {
						$wpdb->insert('ci_collector', $col);

						$col_ID = $wpdb->insert_id;
						tr_pdd_create_user_collector($col, true);
					}

					$wpdb->insert('ci_clinic_collector', array('col_ID' => $col_ID, 'cli_ID' => $cid));
				}
			}
		}

		Tr_Session_Class::add('Inserted: ' . $count_inserted);
		Tr_Session_Class::add('Updated: ' . $count_updated);
		Tr_Session_Class::add('Error: ' . $count_error);
	} else {
		$info = pathinfo($_FILES['importfile']['name']);
		$ext = isset($info['extension']) ? strtolower($info['extension']) : 'csv';
		$data = trpdd_get_data($_FILES['importfile']['tmp_name'], $ext, array(), true);
		tr_ensure_clinic_api_columns(array('cli_sftp_emr_code'));
		$clinic_cols = $wpdb->get_col("show columns from ci_clinic");
		$count_imported = 0;
		$count_managers = 0;
		$count_skipped = 0;
		$skipped_rows = array();
		$default_manager = array(
			'ma_manager' => '',
			'ma_email' => '',
		);
		foreach ($data['data'] as $row) {
			$clean_row = array();
			foreach ($row as $rk => $rv) {
				$clean_key = strtolower(trim(str_replace("\xEF\xBB\xBF", '', $rk)));
				$clean_row[$clean_key] = $rv;
			}
			$row = $clean_row;

			$client_type = trim(trpdd_get_string_exist($row, array('client type', 'clinic type', 'facility type', 'type')));
			$npi = trim(trpdd_get_string_exist($row, array('npi', 'clinic npi', 'facility npi')));
			$datarow = array(
				'cli_clinic' => trpdd_get_string_exist($row, array('actual location name', 'client name', 'site', 'site name', 'clinic name', 'name', 'facility', 'facility name', 'location', 'location name', 'practice', 'practice name', 'client')),
				'cli_street' => trpdd_get_string_exist($row, array('address', 'street address', 'clinic address', 'address 1', 'address1', 'location address', 'facility address')),
				'cli_city' => trpdd_get_string_exist($row, array('city')),
				'cli_state' => trpdd_get_string_exist($row, array('state', 'st')),
				'cli_zip' => trpdd_get_string_exist($row, array('zip', 'zip code', 'postal code', 'zipcode')),
				'cli_om-phone' => trpdd_get_string_exist($row, array('phone', 'phone number', 'phone #', 'main phone', 'office phone')),
				'cli_om-fax' => trpdd_get_string_exist($row, array('fax', 'fax number', 'fax #', 'main fax', 'office fax')),
				'cli_sftp_emr_code' => trpdd_get_string_exist($row, array('client id', 'clientid', 'client_id', 'client code', 'clinic id', 'clinic code', 'facility code', 'location code', 'code')),
			);
			$notes = array();
			if (!empty($client_type)) {
				$notes[] = 'Client Type: ' . $client_type;
			}
			if (!empty($npi)) {
				$notes[] = 'NPI: ' . $npi;
			}
			if (!empty($notes) && in_array('cli_notes', $clinic_cols)) {
				$datarow['cli_notes'] = implode("\n", $notes);
			}
			$manager = array(
				'ma_manager' => trim(trpdd_get_string_exist($row, array('manager', 'manager name', 'office manager', 'office manager name', 'medical director', 'contact', 'contact name'))),
				'ma_email' => trim(trpdd_get_string_exist($row, array('email', 'email address', 'manager email', 'manager email address', 'office manager email', 'contact email'))),
			);
			if (!empty($manager['ma_manager']) || !empty($manager['ma_email'])) {
				foreach ($manager as $mk => $mv) {
					if (!empty($mv)) {
						$default_manager[$mk] = $mv;
					}
				}
			} else if (!empty($default_manager['ma_manager']) || !empty($default_manager['ma_email'])) {
				$manager = $default_manager;
			}
			if (!empty($datarow['cli_sftp_emr_code'])) {
				$datarow['cli_pcr'] = 1;
			}

			if (!empty($datarow['cli_street']) && empty($datarow['cli_city']) && empty($datarow['cli_state'])) {
				$cliaddr = tr_parse_address($datarow['cli_street']);
				$datarow['cli_street'] = $cliaddr['address'];
				$datarow['cli_city'] = $cliaddr['city'];
				$datarow['cli_state'] = $cliaddr['state'];
				$datarow['cli_zip'] = $cliaddr['zip'];
			}
			foreach ($datarow as $k => $vl) {
				if (empty($vl)) {
					unset($datarow[$k]);
				}
			}
			if (empty($datarow['cli_clinic'])) {
				$count_skipped++;
				if (count($skipped_rows) < 10) {
					$skipped_rows[] = trpdd_get_string_exist($row, array('client id', 'clientid', 'client_id', 'client code', 'clinic id', 'clinic code', 'facility code', 'location code', 'code'));
				}
				continue;
			}
			$exist = 0;
			if (!empty($datarow['cli_sftp_emr_code'])) {
				$exist = $wpdb->get_var($wpdb->prepare("select cID from ci_clinic where cli_sftp_emr_code = %s", $datarow['cli_sftp_emr_code']));
			}
			if (!$exist) {
				$exist = $wpdb->get_var($wpdb->prepare("select cID from ci_clinic where cli_clinic = %s", $datarow['cli_clinic']));
			}
			if (!$exist) {
				$datarow['cli_sdate'] = date_i18n('Y-m-d');
				$datarow['cli_lab-ID'] = $current_lab_id;
				$wpdb->insert('ci_clinic', $datarow);
				$exist = $wpdb->insert_id;
			} else {
				$exist_lab_id = $wpdb->get_var($wpdb->prepare("select `cli_lab-ID` from ci_clinic where cID = %d", $exist));
				if (empty($exist_lab_id)) {
					$datarow['cli_lab-ID'] = $current_lab_id;
				}
				$wpdb->update('ci_clinic', $datarow, array('cID' => $exist));
			}
			if ($exist > 0 && (!empty($manager['ma_manager']) || !empty($manager['ma_email']))) {
				$manager['ma_cli_ID'] = $exist;
				if (empty($manager['ma_manager']) && !empty($manager['ma_email'])) {
					$manager['ma_manager'] = $manager['ma_email'];
				}
				$ma_id = 0;
				if (!empty($manager['ma_email'])) {
					$ma_id = $wpdb->get_var($wpdb->prepare("select ma_ID from ci_clinic_manager where ma_cli_ID = %d and ma_email = %s", $exist, $manager['ma_email']));
				}
				if ($ma_id > 0) {
					$rs = $wpdb->update('ci_clinic_manager', $manager, array('ma_ID' => $ma_id));
				} else {
					$rs = $wpdb->insert('ci_clinic_manager', $manager);
					$ma_id = $wpdb->insert_id;
				}
				if ($rs === false) {
					Tr_Session_Class::add_error('Error while update Office Manager: ' . $wpdb->last_error);
				} else {
					$user_id = tr_check_and_create_office_manager_user($exist, $manager);
					if (tr_get_option('ci_pdf_data', 'off_see_all')) {
						update_user_meta($user_id, '_only_one_clinic', 0);
					}
					$count_managers++;
				}
			}
			$count_imported++;
		}
		Tr_Session_Class::add("Rows found: " . count($data['data']));
		Tr_Session_Class::add("Imported: " . $count_imported);
		Tr_Session_Class::add("Office Managers: " . $count_managers);
		if ($count_skipped > 0) {
			Tr_Session_Class::add_error("Skipped rows without clinic/site name: " . $count_skipped . (!empty($skipped_rows) ? ' (' . implode(', ', array_filter($skipped_rows)) . ')' : ''));
		}
	}
}
?>
<div class="wrap">
	<h2>Import</h2>
	<?php Tr_Session_Class::show() ?>
	<div style="margin:20px">
		<form method="post" enctype="multipart/form-data">
			<input type="file" name="importfile" />
			<input type="submit" class="button" />
			<input type="hidden" name="act" value="import" />
		</form>
	</div>
</div>