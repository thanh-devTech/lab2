<?php
global $wpdb;

if (isset($_POST['message'])) {
	Tr_Session_Class::reset();

	$user_ids = isset($_POST['user_ids']) ? $_POST['user_ids'] : array();

	if (empty($_POST['subject'])) {
		Tr_Session_Class::add_error('Please enter subject');
	}

	if (empty($_POST['message'])) {
		Tr_Session_Class::add_error('Please enter message');
	}




	if (Tr_Session_Class::count_error() == 0) {
		$sender = get_current_user_id();
		$data = array(
			'subject' 	=> trim(stripslashes($_POST['subject'])),
			'msg' 		=> stripslashes($_POST['message']),
			'from_date' => $_POST['from_date'],
			'to_date' 	=> $_POST['to_date'],
			'background' => $_POST['background'],
			'author' 	=> $sender,
			'created_date' => date_i18n('Y-m-d H:i:s'),
			'msg_type' 	=> $msg_type,
			'read_roles' => isset($_POST['read_roles']) ? implode(',', $_POST['read_roles']) : '',
		);
		$msg_id = $_POST['ID'];
		if ($msg_id > 0) {
			unset($data['created_date']);
			$wpdb->update('ci_messages', $data, array('ID' => $msg_id));
		} else {
			$wpdb->insert('ci_messages', $data);
			$msg_id = $wpdb->insert_id;
		}

		if ($msg_id > 0) {

			Tr_Session_Class::add('Sent message');
			update_option('trpdd_notification_cache_last_changed_at', time(), false);

			if (count($user_ids) > 0) {
				$sql = "insert into ci_message_users (msg_ID, user_id) VALUES ";
				$sqls = array();

				foreach ($user_ids as $uid) {
					$sqls[] = $wpdb->prepare("(%d,%d)", $msg_id, $uid);
				}
				$sql .= implode(', ', $sqls);

				$wpdb->query($sql);
			}
		} else {
			Tr_Session_Class::add_error('Error, while create new message');
		}
	}
	wp_redirect(Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $msg_id, 'trajax' => ''), false));
	exit;
}

if ($act == 'edit') {
	$data = $wpdb->get_row("select * from ci_messages where ID = {$id}", ARRAY_A);

	$data['read_roles'] = explode(',', $data['read_roles']);
} else {
	$data = array();
	$data['read_roles'] = array();
}

if ($act === 'list') {
	include(trpdd_get_file_path('message', 'list'));
	return;
}

$roles_opts = array(
	'' => 'All',
	'acli' => 'ALL Clinics',
	'alab' => 'ALL Labs',
);

$all_roles = get_editable_roles();
foreach ($all_roles as $rkey => $rval) {
	if (in_array($rkey, array('subscriber', 'editor', 'contributor', 'clms', 'Billing', 'customer', 'author', 'patient'))) continue;
	$roles_opts[$rkey] = $rval['name'];
}

?>
<div class="wrap">
	<h2><?php echo ($act == 'edit' ? 'Edit' : 'Add') . ' ' . $msg_type_label ?> <a class="add-new-h2" href="<?php Tr_Base_Class_V6::link(array('act' => 'list', 'id' => '')) ?>"><?php echo 'All ' . $msg_type_label . 's' ?></a></h2>
	<?php Tr_Session_Class::show() ?>
	<br><br>
	<form method="post">

		<div class="frow">
			<label>Subject:</label>
			<input type="text" require name="subject" value="<?php echo $data['subject'] ?>" required />
		</div>
		<div class="frow">
			<label>Send To:</label>
			<select name="read_roles[]" id="read_roles" multiple>
				<?php foreach ($roles_opts as $rkey => $rval) {
					$selected =  in_array($rkey, $data['read_roles']) ? 'selected' : '';
					echo '<option value="' . $rkey . '" ' . $selected . '>' . $rval . '</option>';
				}
				?>
			</select>
		</div>
		<div class="frow blabel">
			<div class="">
				<label>Message</label>
				<?php wp_editor($data['msg'], 'message') ?>
			</div>
		</div>
		<div class="frow">
			<div class="col4">
				<label>From:</label>
				<input type="search" class="hasdate" name="from_date" value="<?php echo $data['from_date'] ?>" />
			</div>
			<div class="col4">
				<label>To:</label>
				<input type="search" class="hasdate" name="to_date" value="<?php echo $data['to_date'] ?>" />
			</div>

			<div class="col4">
				<label>Priority:</label>
				<select name="background" style="width:200px">
					<?php
					foreach ($alertbgcolors as $k => $carr) {
					?>
						<option value="<?php echo $k ?>" <?php if ($data['background'] == $k) echo 'selected' ?>><?php echo $carr['label'] ?></option>
					<?php } ?>
				</select>
			</div>

		</div>

		<div class="frow">
			<input type="submit" class="button-primary" value="Save" />
			<input type="hidden" name="trajax" value="1" />
			<input type="hidden" name="ID" value="<?php echo $id ?>" />
		</div>
	</form>
</div>

<script>
	jQuery(function($) {
		$('.colorpicker').wpColorPicker();

		$('#read_roles').select2({
			allowClear: true,
		}).on('select2:select', function(e) {
			var data = e.params.data;
			if (data.id == '') {
				$(this).val(data.id).trigger('change');
			} else if ($('#read_roles').val().includes('')) {
				$('option[value=""]', $(this)).prop('selected', false);
				$(this).trigger('change');
			}
		});
	})
</script>