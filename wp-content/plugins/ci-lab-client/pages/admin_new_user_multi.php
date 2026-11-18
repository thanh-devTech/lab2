<?php
global $wpdb;

if (isset($_POST['createnew'])) {
	$emails    = trim($_POST['emails']);
	$role      = $_REQUEST['role'];
	$send_mail = isset($_REQUEST['send_email']) && $_REQUEST['send_email'] ? '1' : '0';

	$out = trpdd_process_multi_user_add($emails, $role, $send_mail);

	if (isset($out['error'])) {
		Tr_Session_Class::add_error($out['error']);
	} else {
		$success_lines = array();
		foreach ($out['results'] as $r) {
			if ($r['status'] === 'success') {
				$name = !empty($r['first_name']) ? ($r['first_name'] . ' ' . $r['last_name'] . ' ') : '';
				$roleText = !empty($r['role_name']) ? (' - ' . $r['role_name']) : '';
				$success_lines[] = $name . $r['email'] . $roleText . ' | password: ' . $r['password'];
			} else {
				Tr_Session_Class::add_error($r['message']);
			}
		}
		if (!empty($success_lines)) {
			Tr_Session_Class::add(implode('<br>', $success_lines));
		}
	}
}

add_filter('editable_roles', function ($roles) {
	unset($roles['clinic']);
	unset($roles['lab_rep']);
	unset($roles['lab_rep_w2']);
	unset($roles['collector']);
	unset($roles['office_manager']);
	unset($roles['multi_site_manager']);
	unset($roles['doctor']);
	unset($roles['patient']);
	return $roles;
});
?>
<div class="wrap">
	<h2>Create Users</h2>
	<?php Tr_Session_Class::show() ?>
	<br>
	<form method="post">
		<div class="frow">
			<label>Emails</label>
			<textarea name="emails" required></textarea>
		</div>
		<div class="frow">
			<label>Role</label>
			<select name="role" id="role" required>
				<option value="">Select Role</option>
				<?php wp_dropdown_roles(); ?>
			</select>
		</div>
		<div class="frow">
			<label><input type="checkbox" name="send_email" value="1" /> Send Email</label>
		</div>
		<div class="frow">
			<input type="submit" class="button-primary" name="createnew" value="Create" />
		</div>
	</form>
</div>