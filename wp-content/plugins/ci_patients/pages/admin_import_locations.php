<?php
global $wpdb;
global $adding_locations_import;
$adding_locations_import = array();

if (isset($_FILES['importfile'])) {

	$data = trpdd_get_data($_FILES['importfile']['tmp_name'], 'csv', array(), true);

	foreach ($data['data'] as $row) {
		$name = trpdd_get_string_exist($row, array('account name'));
		$loc_no = trpdd_get_string_exist($row, array('location no.'));
		$street = trpdd_get_string_exist($row, array('street address'));
		$city = trpdd_get_string_exist($row, array('city'));
		$state = trpdd_get_string_exist($row, array('state'));
		$zipcode = trpdd_get_string_exist($row, array('zip code'));
		$phone = trpdd_get_string_exist($row, array('phone','site phone'));

		$address = trim($street . ', ' . $city . ', ' . $state . ' ' . $zipcode);
		$adding_locations_import[] = array(
			'key' => $loc_no,
			'name' => $name,
			'address' => $address,
			'phone' => $phone,
			'cli_ID' => $_POST['cli_ID']
		);
	}
	echo 'Done';
	//exit;
}

if (empty($adding_locations_import)) {

	$clis = $wpdb->get_results("select * from ci_clinic where cli_status=1");
?>
	<form method="post" style="margin:70px" enctype="multipart/form-data">
		<label>Csv File:</label>
		<input type="file" name="importfile" required />
		<br>
		<label>Clinic:</label>
		<select name="cli_ID">
			<?php
			echo '<option value=""></option>';
			foreach ($clis as $cli) {
				echo '<option value="' . $cli->cID . '">' . $cli->cli_clinic . '</option>';
			}
			?>
		</select>
		<br>

		<input type="submit" class="button" value="Import" />
		<input type="hidden" name="act" value="import_locations" />
	</form>
<?php
	exit;
}
