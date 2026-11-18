<?php

global $wpdb;

if (!defined('DEMO_SITE') && DEMO_SITE !== true) {
	echo '<h3>This feature is only available on demo site.</h3>';
	return;
}


if (isset($_POST['trajax']) && $_POST['trajax'] === 'update_all_doctor') {
	$doctors = isset($_POST['doctors']) ? json_decode(stripslashes($_POST['doctors']), true) : [];

	if (empty($doctors)) {
		wp_send_json([
			'success' => false,
			'error' => 'Empty doctors'
		]);
		exit;
	}

	try {
		$doc_ids = $wpdb->get_col("SELECT doc_ID FROM ci_doc ORDER BY doc_ID ASC");

		if (empty($doc_ids)) {
			wp_send_json([
				'success' => false,
				'error' => 'No doctors found'
			]);
		}

		$total_data = count($doctors);
		$i = 0;

		foreach ($doc_ids as $doc_id) {

			$d = $doctors[$i % $total_data];

			$wpdb->update(
				"ci_doc",
				[
					'doc_first' => $d['doc_first'],
					'doc_last'  => $d['doc_last'],
					'doc_npi'   => $d['doc_npi'],
				],
				[
					'doc_ID' => $doc_id
				],
				['%s', '%s', '%s'],
				['%d']
			);

			$i++;
		}
		wp_send_json([
			'success' => true,
			'message' => 'Updated all doctors successful'
		]);
	} catch (Exception $e) {
		wp_send_json([
			'success' => false,
			'error' => $e->getMessage()
		]);
	}
	exit;
}

if (isset($_POST['trajax']) && $_POST['trajax'] === 'generate_patients') {
	$patients = isset($_POST['patients']) ? json_decode(stripslashes($_POST['patients']), true) : [];
	$update_db = isset($_POST['update_db']) ? (int) $_POST['update_db'] : 0;
	$only_update_batch = isset($_POST['only_update_batch']) ? (int) $_POST['only_update_batch'] : 0;

	if (empty($patients) && !$only_update_batch) {
		wp_send_json([
			'success' => false,
			'error' => 'Empty patients'
		]);
		exit;
	}

	try {
		if ($only_update_batch) {
			$all_patients = $wpdb->get_results(
				"SELECT pa_ID, pa_first, pa_last, pa_dob FROM ci_patients",
				ARRAY_A
			);

			if (empty($all_patients)) {
				wp_send_json([
					'success' => false,
					'error' => 'No patients found'
				]);
			}
			$total_patients = count($all_patients);
			$patient_index  = 0;

			$btypes = tr_pdd_get_batch_types();

			foreach ($btypes as $btype) {
				$batch_tbl = tr_pdd_get_table_by_btype($btype);
				$batch_ids = $wpdb->get_col("
					SELECT mid
					FROM {$batch_tbl}
					ORDER BY doc DESC
				");
				foreach ($batch_ids as $mid) {

					$p = $all_patients[$patient_index % $total_patients];

					$patient_name = $p['pa_last'] . ',' . $p['pa_first'];

					$wpdb->update(
						$batch_tbl,
						[
							'patient_name' => $patient_name,
							'patient_id'   => $p['pa_ID'],
							'dob'          => $p['pa_dob'],
						],
						[
							'mid' => $mid
						],
						['%s', '%d', '%s'],
						['%d']
					);

					$patient_index++;
				}
			}
		} else {
			if ($update_db) {
				$cli_ids = $wpdb->get_col('select cID from ci_clinic');

				foreach ($patients as $pa) {
					$pa['pa_clinic_ID'] = $cli_ids[array_rand($cli_ids)];
					$wpdb->insert('ci_patients', $pa);
				}
			}
		}

		wp_send_json([
			'success' => true,
			'message' => 'Generated patients successfully'
		]);
	} catch (Exception $e) {
		wp_send_json([
			'success' => false,
			'error' => $e->getMessage()
		]);
	}
	exit;
}

if (isset($_POST['trajax']) && $_POST['trajax'] === 'remove_unsue_patients') {

	try {
		$delete_sql = "DELETE FROM ci_patients";

		$deleted_count = $wpdb->query($delete_sql);

		wp_send_json([
			'success' => true,
			'deleted_count' => $deleted_count,
			'message' => "Deleted {$deleted_count} unuse patients"
		]);
	} catch (Exception $e) {
		wp_send_json([
			'success' => false,
			'error' => $e->getMessage()
		]);
	}
	exit;
}



$count_doctors = $wpdb->get_var('select count(*) from ci_doc');
?>
<style>
	.form-group {
		margin-bottom: 15px;
	}

	.form-group input[type="checkbox"] {
		margin-right: 5px;
	}

	.btn-primary {
		background: #0073aa;
		color: white;
		padding: 10px 20px;
		border: none;
		border-radius: 4px;
		cursor: pointer;
		font-size: 14px;
	}

	.btn-primary:hover {
		background: #005a87;
	}

	.btn-primary:disabled {
		background: #ccc;
		cursor: not-allowed;
	}

	#result {
		margin-top: 20px;
	}
</style>



<?php if ($act != 'generate_patients_result'): ?>
	<div class="patients-form-container">
		<h3>Fake Patients Generator</h3>
		<form id="patients-form">
			<div class="form-group">
				<label>
					<input type="checkbox" id="update_db" name="update_db" value="1">
					Update patients
				</label>
			</div>
			<div class="form-group">
				<label>
					<input type="checkbox" id="update_batch" name="update_batch" value="1">
					Only update batch table
				</label>
			</div>

			<button type="button" id="generate-btn" class="btn-primary">Generate Patients</button>
			<button type="button" id="remove-unuse-patients" class="btn-primary">Remove unuse patients</button>
			<button type="button" id="update_all_doctor" class="btn-primary">Update all doctor</button>
		</form>

		<div id="result"></div>
	</div>
<?php endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Faker/3.1.0/faker.min.js"></script>
<script>
	const numDoctors = '<?php echo $count_doctors; ?>';
	jQuery(function($) {
		const {
			faker
		} = window;
		faker.locale = 'en_US';

		function createFakePatients() {
			const patients = [];

			for (let i = 0; i < 500; i++) {

				const gender = faker.random.arrayElement(['M', 'F']);

				const patientData = {
					pa_first: gender === 'M' ? faker.name.firstName(0) : faker.name.firstName(1),
					pa_last: faker.name.lastName(),
					pa_dob: faker.date.past(100).toISOString().split('T')[0],
					pa_gender: gender,
					pa_address: faker.address.streetAddress(),
					pa_city: faker.address.city(),
					pa_state: faker.address.stateAbbr(),
					pa_zip: faker.address.zipCode('#####'),
					pa_phone: faker.phone.phoneNumber('(###) ###-####'),
					pa_email: faker.internet.email()
				};

				patients.push(patientData);
			}

			return patients;
		}

		function createFakeDoctorData() {
			const doctors = [];
			const num = parseInt(numDoctors);

			for (let i = 0; i < num; i++) {
				const docData = {
					doc_first: faker.name.firstName(),
					doc_last: 'Doctor',
					doc_npi: faker.helpers.replaceSymbols('#########')
				};

				doctors.push(docData);
			}

			return doctors;
		}

		$('#generate-btn').on('click', function() {
			const $form = $('#patients-form');
			const $update_db = $('#update_db');
			const $update_batch = $('#update_batch');

			const onlyUpdateBatchTb = $update_batch.is(':checked');

			const $btn = $(this);
			let patients = [];
			if (!onlyUpdateBatchTb) {
				patients = createFakePatients();
			}
			$btn.prop('disabled', true).text('Generating...');
			$.ajax({
				type: 'POST',
				data: {
					trajax: 'generate_patients',
					patients: JSON.stringify(patients),
					update_db: $update_db.is(':checked') ? 1 : 0,
					only_update_batch: onlyUpdateBatchTb ? 1 : 0
				},
				success: function(res) {
					if (res.success) {
						alert(res.message || '')
					} else {
						alert(res.error || '')
					}
				},
				error: function(e) {
					alert('Error generate patients');
					console.log(e);
				},
				complete: function() {
					$btn.prop('disabled', false).text('Generate Patients');
				}
			});

		});

		$('#remove-unuse-patients').on('click', function() {
			const $btn = $(this);
			$btn.prop('disabled', true).text('Removing...');
			$.ajax({
				type: 'POST',
				data: {
					trajax: 'remove_unsue_patients',
				},
				success: function(res) {
					if (res.success) {
						alert(res.message || '')
					} else {
						alert(res.error || '')
					}
				},
				error: function(e) {
					alert('Error removing patients');
					console.log(e);
				},
				complete: function() {
					$btn.prop('disabled', false).text('Remove unuse patients');
				}
			});

		});

		$('#update_all_doctor').on('click', function() {
			const $btn = $(this);
			$btn.prop('disabled', true).text('Updating...');
			const doctors = createFakeDoctorData();
			$.ajax({
				type: 'POST',
				data: {
					trajax: 'update_all_doctor',
					doctors: JSON.stringify(doctors)
				},
				success: function(res) {
					if (res.success) {
						alert(res.message || '')
					} else {
						alert(res.error || '')
					}
				},
				error: function(e) {
					alert('Error updating doctors');
					console.log(e);
				},
				complete: function() {
					$btn.prop('disabled', false).text('Update all doctor');
				}
			});

		});

	});
</script>