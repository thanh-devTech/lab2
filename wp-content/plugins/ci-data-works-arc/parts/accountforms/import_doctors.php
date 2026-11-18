<?php
global $last_password;

unlink(WP_CONTENT_DIR . '/log/doc_users.csv');

$users = array();


if (!empty($_FILES['file']['name'])) {
	$data = trpdd_get_data($_FILES['file']['tmp_name'], '', array('npi', 'email'), true);

	$cli_id = isset($_REQUEST['cID']) ? $_REQUEST['cID'] : 0;
	$clinic_ids = $wpdb->get_col("select cID from ci_clinic");

	foreach ($data['data'] as $row) {
		$created = false;

		$email = trpdd_get_string_exist($row, array('email', 'email address'));
		$email = trim($email);
		$email = str_replace(' ', '', $email);
		if (stripos($email, 'N/A') !== false || stripos($email, 'na') !== false) {
			$email = '';
		}
		$data = array(
			'doc_first' => trpdd_get_string_exist($row, array('first', 'first name')),
			'doc_last' => trpdd_get_string_exist($row, array('last', 'last name')),
			'doc_specialty' => trpdd_get_string_exist($row, array('degree', 'ft/pt/ic', 'specialty')),
			'doc_npi' => trpdd_get_string_exist($row, array('npi', 'npi number')),
			'doc_email' => $email,
			'doc_phone' => trpdd_get_string_exist($row, array('phone', 'phone no', 'phone no.'))
		);

		$doc_id = $wpdb->get_var("select doc_ID from ci_doc
			            where doc_first = '{$data['doc_first']}'
			            and doc_last = '{$data['doc_last']}'
			            and doc_npi = '{$data['doc_npi']}' ");
		if (!$doc_id) {
			$created = true;
			$wpdb->insert('ci_doc', $data);
			$doc_id = $wpdb->insert_id;
		} else {
			$created = false;
			$wpdb->update('ci_doc', $data, array('doc_ID' => $doc_id));
		}

		if ($doc_id > 0) {
			if ($cli_id === 'all') {

				foreach ($clinic_ids as $cid) {
					$wpdb->insert('ci_clinic_doc', array('doc_ID' => $doc_id, 'cli_ID' => $cid));
				}
			}
			else if ($cli_id > 0) {
				$rsi = $wpdb->insert('ci_clinic_doc', array('doc_ID' => $doc_id, 'cli_ID' => $cli_id));
				if ($rsi === false) {
					Tr_Session_Class::add_error('Error: ' . $wpdb->last_error);
				}
			}
		}

		if ($doc_id > 0 && !empty($email)) {
			$user_id = tr_check_and_create_doc_user($doc_id, $cli_id, $created);
			$users[] = array($user_id, $email, $last_password);
		}
	}

	Tr_Session_Class::add('Imported : ' . $count);


	$f = fopen(WP_CONTENT_DIR . '/log/doc_users.csv', 'w');
	fputcsv($f, array('UserID', 'Email', 'Password'));
	foreach ($users as $u) {
		fputcsv($f, $u);
	}
	Tr_Session_Class::add('<a href="/wp-content/log/doc_users.csv">Download users.csv</a>');
}

$clinics = $wpdb->get_results("select * from ci_clinic where cli_clinic !=''  order by cli_clinic asc");
?>
<div class="wrap center">
	<h2>Import Physicians</h2>
	<?php Tr_Session_Class::show(); ?>
	<div style="width:800px;margin:30px auto">
		<form method="post" enctype="multipart/form-data">
			<?php if (!tr_get_option('ci_pdf_data', 'doc_o_cli')) { ?>
				<div class="frow">
					<label>Clinic</label>
					<select name="cID" class="js_select2" style="width:400px" required>
						<option value="">Select Clinic</option>
						<option value="all">All Clinic</option>
						<?php
						foreach ($clinics as $clinic) {
							echo '<option value="' . $clinic->cID . '">' . $clinic->cli_clinic . '</option>';
						}
						?>
					</select>
				</div>
			<?php } ?>
			<div class="frow">
				<label>Csv file:</label>
				<input type="file" name="file" accept="text/csv" required />
			</div>

			<div class="frow">
				<input type="submit" class="button" />
			</div>

		</form>
		<br>
		<a href="<?php Tr_Base_Class_V6::link(array('trajax' => 'demo_doctor_csv', 'bytab' => 1)) ?>">Download Template Csv</a>
	</div>
</div>