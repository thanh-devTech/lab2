<?php
global $wpdb;

if (isset($_POST['main_id'])  && $_POST['main_id'] > 0) {

	$m_id = $_POST['main_id'];
	$patient = $wpdb->get_row("select * from ci_patients where pa_ID = {$m_id} ");
	if ($patient) {
		foreach ($_POST['second_id'] as $s_id) {
			if ($m_id == $s_id) {
				continue;
			}

			foreach (tr_pdd_get_batch_types() as $btype) {
				$table = tr_pdd_get_table_by_btype($btype);
				$wpdb->update($table, array('patient_id' => $m_id), array('patient_id' => $s_id));
			}

			$wpdb->update('ci_saved' . '_batches', array('patient_id' => $m_id), array('patient_id' => $s_id));
			$wpdb->update('ci_insurances', array('pa_ID' => $m_id), array('pa_ID' => $s_id));
			$wpdb->update('ci_patients_history', array('pa_ID' => $m_id), array('pa_ID' => $s_id));
			$wpdb->update($wpdb->usermeta, array('meta_value' => $m_id), array('meta_value' => $s_id, 'meta_key' => '_patient_id'));
			$wpdb->update($wpdb->users, array('patient_id' => $m_id), array('patient_id' => $s_id));

			$import_ID = $wpdb->get_var("select pa_import_ID from ci_patients where pa_ID = {$s_id} ");
			if ($import_ID > 0) {
				$wpdb->insert('ci_fix_duplicate', array('ID' => $m_id, 'import_ID' => $import_ID, 'ctype' => 'patient', 'created' => date('Y-m-d H:i:s')));
			}
			$wpdb->insert('ci_fix_duplicate', array('ID' => $m_id, 'import_ID' => $s_id, 'ctype' => 'dpatient', 'created' => date('Y-m-d H:i:s')));
			$wpdb->delete('ci_patients', array('pa_ID' => $s_id));
		}
		Tr_Session_Class::add("moved patient from: " . implode(', ', $_POST['second_id']) . ' to #' . $m_id);
	} else {
		Tr_Session_Class::add_error('Not found Main ID: #' . $m_id);
	}
}
?>
<h2>Duplicate Patient</h2>
<?php Tr_Session_Class::show() ?>

<form method="get" style="margin-bottom:10px;">
	<input type="hidden" name="page" value="<?php echo isset($_GET['page']) ? $_GET['page'] : '' ?>" />
	<input type="hidden" name="pagetab" value="<?php echo isset($_GET['pagetab']) ? $_GET['pagetab'] : '' ?>" />
	<input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />
	<label>Name: </label>
	<input type="text" name="s" value="<?php echo isset($_REQUEST['s']) ? $_REQUEST['s'] : '' ?>" />
	<label>DOB:</label>
	<input type="text" class="hasdate" data-write="true" name="dob" value="<?php echo isset($_REQUEST['dob']) ? $_REQUEST['dob'] : '' ?>" />
	<label>SSN</label>
	<input type="text" name="ssn" value="<?php echo isset($_REQUEST['ssn']) ? $_REQUEST['ssn'] : '' ?>" />
	<input type="submit" class="button-primary bsty6" value="Search" />
	<a class="button" href="<?php Tr_Base_Class_V6::link(array('act' => 'autosearch', 's' => '', 'autodob' => '1', 'dob' => '', 'ssn' => '')) ?>">Auto List</a>
</form>

<?php
if (!empty($_REQUEST['s']) || !empty($_REQUEST['dob']) || !empty($_REQUEST['ssn'])) {
?>
	<form method="post" id="move_items_form">
		<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl small_padding">
			<thead>
				<tr>
					<th style="width:50px">#</th>
					<th style="width:30px">Main</th>
					<th style="width:50px">Second</th>
					<th>Last Name</th>
					<th>First Name</th>
					<th>DOB</th>
					<th>SSN</th>
					<th>MRN</th>
					<th>Address</th>
					<th>City</th>
					<th style="width:40px">State</th>
					<th style="width:50px">Zip</th>
					<th>Phone</th>
					<th>Gender</th>
					<th>Clinic#</th>
					<th style="width:80px">Last Test</th>
					<th>User</th>
				</tr>
			</thead>
			<tbody>
				<?php

				$where = " 1 ";

				if (!empty($_REQUEST['s'])) {
					list($pa_last, $pa_first) = explode(',', $_REQUEST['s']);
					$pa_last = trim($pa_last);
					$pa_first = trim($pa_first);

					$where .= $wpdb->prepare(
						" 
				and (pa.pa_last like %s or pa.pa_first like %s)
				and (pa.pa_first like %s or pa.pa_last like %s)
				",
						$pa_last . '%',
						$pa_last . '%',
						$pa_first . '%',
						$pa_first . '%'
					);
				}

				if (!empty($_REQUEST['dob'])) {
					$where .= " and pa.pa_dob = '{$_REQUEST['dob']}' ";
				}

				if (!empty($_REQUEST['ssn'])) {
					$where .= " and pa.pa_ssn = '{$_REQUEST['ssn']}' ";
				}

				$sql = "select pa.* 
						from ci_patients pa
						where {$where} 
						group by pa.pa_ID 
						order by pa.pa_dob asc, pa.pa_ssn asc, pa.pa_first asc 
						";

				$patients = $wpdb->get_results($sql);

				if (isset($_GET['debug'])) {
					var_dump($sql);
					var_dump($wpdb->last_error);
				}


				$patient_dob = array();
				$patient_names = array();

				foreach ($patients as $i => $pa) {
					$patients[$i]->key_name = strtolower($pa->pa_last . $pa->pa_first);
					if (!is_array($patient_dob[$pa->pa_dob])) {
						$patient_dob[$pa->pa_dob] = array();
					}
					$patient_dob[$pa->pa_dob]['count']++;
					if ($patient_dob[$pa->pa_dob]['count'] > 1) {
						$patient_dob[$pa->pa_dob]['color'] = trrandom_color();
					}

					if (!is_array($patient_names[$pa->key_name])) {
						$patient_names[$pa->key_name] = array();
					}

					$patient_names[$pa->key_name]['count']++;
					if ($patient_names[$pa->key_name]['count'] > 1) {
						$patient_names[$pa->key_name]['color'] = trrandom_color();
					}

					$patients[$i]->batch_created = '';
					$patients[$i]->assen = '';
					$patients[$i]->btype = '';
					foreach (tr_pdd_get_batch_types() as $btype) {
						$batch_tbl = tr_pdd_get_table_by_btype($btype);
						$bat1 = $wpdb->get_row("select assen,batch_created from {$batch_tbl} 
											where patient_id = {$pa->pa_ID} 
											order by batch_created desc limit 1");

						if ($bat1 && $bat1->batch_created > $patients[$i]->batch_created) {
							$patients[$i]->batch_created = $bat1->batch_created;
							$patients[$i]->assen = $bat1->assen;
							$patients[$i]->btype = $btype;
						}
					}
				}

				usort($patients, function ($a, $b) {
					return $a->batch_created < $b->batch_created;
				});

				foreach ($patients as $pa) {
					$pa->pa_address = str_ireplace('.', '', $pa->pa_address);
					$pa->pa_address = str_ireplace('vining', 'vinning', $pa->pa_address);
					$pa->pa_address = str_ireplace('sout st', 'south st', $pa->pa_address);
					$pa->pa_address = str_ireplace('street', 'st', $pa->pa_address);
					$pa->pa_address = str_ireplace('W South', 'West South', $pa->pa_address);


					$user_email = $wpdb->get_var("select u.user_email from {$wpdb->users} u
										join {$wpdb->usermeta} m ON u.ID = m.user_id 
										where m.meta_key = '_patient_id' and m.meta_value = '{$pa->pa_ID}'");


				?>
					<tr class="item_<?php echo $pa->pa_ID ?>" data-id="<?php echo $pa->pa_ID ?>">
						<td><?php echo $pa->pa_ID ?></td>
						<td><input type="radio" class="main_id" name="main_id" value="<?php echo $pa->pa_ID ?>" /> </td>
						<td><input type="checkbox" class="second_id" name="second_id[]" value="<?php echo $pa->pa_ID ?>" /> </td>
						<td class="editable" data-key="pa_last" style="background:<?php echo @$patient_last[$pa->pa_last]['color'] ?>"><?php echo $pa->pa_last ?></td>
						<td class="editable" data-key="pa_first" style="background:<?php echo @$patient_names[$pa->key_name]['color'] ?>"><?php echo $pa->pa_first ?></td>
						<td class="editable needcalendar" data-key="pa_dob" style="background:<?php echo @$patient_dob[$pa->pa_dob]['color'] ?>"><?php echo $pa->pa_dob ?></td>
						<td class="editable" data-key="pa_ssn"><?php echo $pa->pa_ssn ?></td>
						<td class="editable" data-key="pa_mrn"><?php echo $pa->pa_mrn ?></td>
						<td class="editable" data-key="pa_address"><?php echo tr_pdd_capitalized($pa->pa_address) ?></td>
						<td class="editable" data-key="pa_city"><?php echo tr_pdd_capitalized($pa->pa_city) ?></td>
						<td class="editable" data-key="pa_state"><?php echo $pa->pa_state ?></td>
						<td class="editable" data-key="pa_zip"><?php echo $pa->pa_zip ?></td>
						<td class="editable" data-key="pa_phone" style="background:<?php echo @$patient_phones[$pa->pa_phone]['color'] ?>"><?php echo $pa->pa_phone ?></td>
						<td class="editable" data-key="pa_gender"><?php echo $pa->pa_gender ?></td>
						<td><?php echo $pa->pa_clinic_ID ?></td>
						<td title="<?php echo $pa->btype . ': ' . $pa->assen ?>"><?php echo $pa->batch_created ?></td>
						<td><?php echo $user_email ?></td>
					</tr>
				<?php
				}

				?>
			</tbody>
		</table>
		<div style="margin-top:10px;">
			<input type="hidden" name="act" value="move_duplicate" />
			<input type="hidden" name="s" value="<?php echo isset($_REQUEST['s']) ? $_REQUEST['s'] : '' ?>" />
			<input type="hidden" name="dob" value="<?php echo isset($_REQUEST['dob']) ? $_REQUEST['dob'] : '' ?>" />

			<input type="submit" class="button-primary" value="Move" />
			<input type="button" class="button checkallsecond" value="Check all Second" />

			<span> <?php echo count($patients) ?> items</span>
		</div>
	</form>
	<style>
		table.small_padding th,
		table.small_padding td {
			padding-left: 2px;
			padding-right: 2px;
		}
	</style>
	<script>
		jQuery(function($) {
			function tr_save_patient($obj) {
				var $pr = $obj.parents('td.editable');
				var $key = $pr.data('key');
				$value = $obj.val();
				$id = $pr.parent().data('id');
				$pr.append('<div class="sloading"></div>');
				$.ajax({
					type: 'POST',
					data: {
						tr_action: 'ci_save_patient_ajax',
						id: $id,
						value: $value,
						key: $key
					},
					dataType: 'json',
					success: function(rs) {
						if (rs.status == 'ok') {
							$pr.html($value);
							$pr.removeClass('editing');
						} else {
							alert('Error Please try again');
						}
						$pr.remove('.sloading');
					},
					error: function() {
						alert('Error Please try again');
						$pr.remove('.sloading');
					}
				})
			}
			$('.editable').click(function() {
				if ($(this).hasClass('editing')) return;
				$(this).addClass('editing');
				var $vl = $(this).html();
				if ($(this).data('key') == 'pa_gender') {
					$html = $('<select class="njs_select2"><option value="F">Female</option><option value="M">Male</option></select>');

				} else {
					$html = $('<input type="text" />');
				}
				$html.val($vl);

				$(this).html($html);
				if ($(this).hasClass('needcalendar')) {
					$('input', $(this)).datepicker({
						dateFormat: 'yy-mm-dd'
					}).focus();
				}
			}).on('keypress', 'input', function(e) {
				var code = e.keyCode || e.which;
				if (code == 13) {
					tr_save_patient($(this));
					return false;
				}
			}).on('change', 'select', function() {
				tr_save_patient($(this));
			})

		})
	</script>

<?php
} else if ($act == 'autosearch') {

	$groupby = " group by pa_last,pa_first ";
	if (isset($_REQUEST['autodob'])) {
		$groupby .= ',pa_dob ';
	}

	$total = $wpdb->get_var("select count(*) from (select count(*) as c
	from ci_patients pa
	where pa_first !='' or pa_last!=''
	{$groupby} ) as rs
	where rs.c > 1 ");

	$row_per_page = 100;
	$pagination = new Tr_Pagination_class($row_per_page);
	$pagination->setTotal($total);
	$offset = $pagination->offset();


	
	$sql = "select * from (
		select pa.pa_dob,
			pa.pa_last,
			pa.pa_first,
			pa.pa_ssn,
			pa.pa_mrn,
			pa.pa_address,
			pa.pa_city,
			pa.pa_state,
			pa.pa_zip,
			pa.pa_phone,
			count(*) as c
		from ci_patients pa
		where pa_first !='' or pa_last!=''
		{$groupby} 
		order by c desc,pa_dob) as rs
		 where rs.c > 1
		 
		  limit {$offset},{$row_per_page}";
	$patients = $wpdb->get_results($sql);

	if (isset($_GET['debug'])) {
		var_dump($sql);
	}
?>
	<?php if ($pagination) echo $pagination->pagination() ?>
	<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
		<thead>
			<tr>
				<th style="width:50px">Duplicate</th>
				<th>Last Name</th>
				<th>First Name</th>
				<th>DOB</th>
				<th>SSN</th>
				<th>MRN</th>
				<th>Address</th>
				<th>Phone</th>
				<th style="width:50px;">Actions</th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ($patients as $pa) {
				if ($pa->c <= 1) continue;
				$dob = isset($_REQUEST['autodob']) ? $pa->pa_dob : '';
			?>
				<tr>
					<td><?php echo $pa->c ?></td>
					<td><?php echo $pa->pa_last ?></td>
					<td><?php echo $pa->pa_first ?></td>
					<td><?php echo $pa->pa_dob ?></td>
					<td><?php echo $pa->pa_ssn ?></td>
					<td><?php echo $pa->pa_mrn ?></td>
					<td><?php echo $pa->pa_address . ' ' . $pa->pa_city . ' ' . $pa->pa_state . ' ' . $pa->pa_zip ?></td>
					<td><?php echo $pa->pa_phone ?></td>
					<td>
						<a href="<?php Tr_Base_Class_V6::link(array('act' => '', 's' => $pa->pa_last . ',' . $pa->pa_first, 'dob' => $dob)) ?>">List</a>
					</td>
				</tr>
			<?php
			}
			?>
		</tbody>
	</table>
	<?php if ($pagination) echo $pagination->pagination() ?>
<?php
}
