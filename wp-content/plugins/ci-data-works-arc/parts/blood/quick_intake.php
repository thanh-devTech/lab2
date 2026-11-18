<?php
global $wpdb;

$batch_tbl 		= 'ci_batches_blood';

$print_times 	= tr_get_option('ci_pdf_data', 'quintake_print_times', 1);

if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'addnew') {

	$return = array('status' => 'error', 'html' => '');

	$data 		= $_POST['formdata'];

	$batchdata 			= array();
	$batchdata['dob'] 	= $data['dob'];
	$batchdata['patient_name'] = $data['last_name'] . ', ' . $data['first_name'];
	$batchdata['assen'] = tr_get_new_assen('blood', false, '');
	$batchdata['batch_created'] = date_i18n('Y-m-d H:i:s');
	$batchdata['doc'] 	= date_i18n('Y-m-d H:i:s');
	$batchdata['batch_type'] = 'Q';
	$batchdata['author_ID'] = get_current_user_id();

	if (isset($data['cli_ID']) && !empty($data['cli_ID'])) {
		$batchdata['cli_ID'] = $data['cli_ID'];
		update_user_meta( get_current_user_id(), '_quick_intake_cliid', $data['cli_ID'] );
	}

	if (isset($data['pa_location']) && !empty($data['pa_location'])) {
		//find patient
		$patient = $wpdb->get_row($wpdb->prepare("select * from ci_patients 
		where pa_first = %s and pa_last = %s and pa_dob = %s", $data['first_name'], $data['last_name'], $data['dob']));
		if ($patient) {
			$batchdata['patient_id'] = $patient->pa_ID;
			$wpdb->update('ci_patients', array(
				'pa_location' => $data['pa_location'],
			), array('pa_ID' => $patient->pa_ID));
			$return['error2'] = $wpdb->last_error;

			$return['patientext'] = $patient;
		} else {
			$patientdata = array(
				'pa_first' => $data['first_name'],
				'pa_last' => $data['last_name'],
				'pa_dob' => $data['dob'],
				'pa_location' => $data['pa_location'],
				'pa_created' => date_i18n('Y-m-d H:i:s'),
				'pa_created_by' => get_current_user_id(),
			);
			$return['patient'] = $patientdata;

			$wpdb->insert('ci_patients', $patientdata);
			$return['error2'] = $wpdb->last_error;
			$batchdata['patient_id'] = $wpdb->insert_id;
		}
	}


	$wpdb->insert($batch_tbl, $batchdata);
	$return['error3'] = $wpdb->last_error;
	$mid = $wpdb->insert_id;
	if ($mid > 0) {

		$return['status'] = 'ok';
		$data['assen'] = $batchdata['assen'];
		$row = (object) $data;

		ob_start();
?>
		<tr data-id="<?php echo $mid ?>" <?php echo isset($print_times) && $print_times > 0 ? 'data-print_times="' . $print_times . '"' : '' ?>>
			<td class="col-pa_first"><?php echo $row->first_name ?></td>
			<td class="col-pa_last"><?php echo $row->last_name ?></td>
			<td class="col-dob"><?php echo $row->dob ?></td>
			<?php if (tr_get_option('ci_pdf_data', 'req_patient_location')) { ?>
				<td class="col-pa_location"><?php echo $row->pa_location ?></td>
			<?php } ?>
			<td class="assen"><?php echo $row->assen ?></td>
			<td><a class="button print_assen_btn">Print Barcode</a></td>
		</tr>
<?php
		$return['html'] = ob_get_clean();
	} else {
		$return['error'] = $wpdb->last_error;
	}
	wp_send_json($return);
}
include_once(TRCPDD_PATH . 'pages/formdata.php');



$where = " batch_type = 'Q' ";
$row_per_page = 100;
$pagination = new Tr_Pagination_class($row_per_page);
$pagination->setTotal($wpdb->get_var("select count(*) as c 
		from {$batch_tbl} ba
         where  {$where}"));
$offset = $pagination->offset();


$sql = "select ba.mid, 
ba.assen, 
ba.patient_name, 
ba.dob,
pa.*
from {$batch_tbl} ba
LEFT JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
where {$where}
order by mid desc
limit {$offset},{$row_per_page}";


$rows = $wpdb->get_results($sql);

$clinics = $wpdb->get_results("select cID, cli_clinic from ci_clinic where cli_status = 1 order by cli_clinic");

?>
<div class="wrap">
	<h2>Quick Intake</h2>

	<form method="post" id="addform">
		<div class="frow">
			<div class="col5">
				<label>First Name</label>
				<input type="text" name="formdata[first_name]" value="" required="true" />
			</div>
			<div class="col5">
				<label>Last Name</label>
				<input type="text" name="formdata[last_name]" value="" required="true" />
			</div>
			<div class="col8">
				<label>DOB</label>
				<input type="text" class="hasdate birthday limitfuture" name="formdata[dob]" value="" required="true" />
			</div>
			<?php if (tr_get_option('ci_pdf_data', 'req_patient_location')) { ?>
				<div class="col6">
					<label>Location</label>
					<input type="text" name="formdata[pa_location]" value="" />
				</div>
				<div class="col5">
					<label>Clinic</label>
					<select name="formdata[cli_ID]">
						<option value="">Select</option>
						<?php
						$cliID = get_user_meta(get_current_user_id(), '_quick_intake_cliid', true);
						foreach ($clinics as $cli) {
							$selected = $cli->cID == $cliID ? 'selected' : '';
							echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
						}
						?>
					</select>
				</div>
			<?php } ?>
			<div class="col11">
				<input type="button" style="margin-top:22px;" class="button-primary submitform" value="Submit" />
			</div>
		</div>
		<input type="hidden" name="trajax" value="addnew" />
	</form>

	<div class="wraptbl">
		<div class="tablenav">
			<?php if ($pagination) echo $pagination->pagination() ?>
		</div>
		<table id="maintbl" class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl visitrow_tbl">
			<thead>
				<tr>
					<th>First Name</th>
					<th>Last Name</th>
					<th>DOB</th>
					<?php if (tr_get_option('ci_pdf_data', 'req_patient_location')) { ?>
						<th>Location</th>
					<?php } ?>
					<th>Barcode</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ($rows as $row) {
					list($row->last_name, $row->first_name) = explode(',', $row->patient_name);
				?>
					<tr data-id="<?php echo $row->mid ?>" <?php echo isset($print_times) && $print_times > 0 ? 'data-print_times="' . $print_times . '"' : '' ?>>
						<td class="col-pa_first"><?php echo $row->first_name ?></td>
						<td class="col-pa_last"><?php echo $row->last_name ?></td>
						<td class="col-dob"><?php echo $row->dob ?></td>
						<?php if (tr_get_option('ci_pdf_data', 'req_patient_location')) { ?>
							<td class="col-pa_location"><?php echo $row->pa_location ?></td>
						<?php } ?>
						<td class="assen"><?php echo $row->assen ?></td>
						<td><a class="button print_assen_btn">Print Barcode</a></td>
					</tr>
				<?php
				}
				?>
			</tbody>
		</table>
		<div class="tablenav">
			<?php if ($pagination) echo $pagination->pagination() ?>
		</div>
	</div>
</div>

<script>
	jQuery(function($) {
		$('body').on('click', '.submitform', function() {
			$(this).parents('form#addform').submit();
		}).on('submit', 'form#addform', function() {
			var $form = $(this);
			var $btn = $('input.submitform', $form);
			if ($btn.val() == 'Sending...') return false;
			$btn.val('Sending...');
			$.ajax({
				type: 'post',
				dataType: 'json',
				data: $form.serialize(),
				success: function(rs) {
					if (rs.status == 'ok') {
						$btn.val('Submit');
						$('input[type="text"]', $form).val('');
						var $tr = $(rs.html);
						$('table#maintbl tbody').prepend($tr);
					} else {
						$btn.val('Submit');
						alert('Error. Please try again!');
					}

				},
				error: function() {
					$btn.val('Submit');
					alert('Error. Please try again!');
				}
			})
			return false;
		})
	})
</script>