<?php
global $wpdb;

$comment = '';
$addmailing = false;
$sent = false;
if (!empty($_POST['comment'])) {

	$comment 	= stripslashes($_POST['comment']);
	$addmailing = isset($_POST['addmailing']);

	$user = wp_get_current_user();
	$rs = false;
	if (!empty($comment)) {
		$rs = tr_client_send_hub(array(
			'action' => 'contact',
			'comment' => $comment,
			'addmailing' => $addmailing ? 1 : 0,
			'email' => $user->user_email,
			'fullname' => $user->display_name,
		));
	}




	if (is_array($rs) && $rs['status'] == 'ok') {
		$sent = true;
	} else {
		Tr_Session_Class::add_error("Error. Please try again!!");
	}
}
?>

<div class="wrap">
	<h2 class="center">Contact Us</h2>
	<?php Tr_Session_Class::show() ?>
	<br>
	<?php
	if ($sent) {
		echo '<h2 class="center">Sent Successfully!</h2>';
		echo '<h2 class="center">Thank you!</h2>';
	} else { ?>
		<form method="post">

			<div class="frow">
				<label>Ask a question/leave a comment</label>
				<textarea name="comment" required><?php echo $comment ?></textarea>
			</div>
			<div class="frow">
				<label><input type="checkbox" name="addmailing" value="1" <?php checked($addmailing) ?> /> Add me to the mailing list</label>
			</div>
			<div class="frow">
				<input type="submit" class="button-primary" value="Submit" />
			</div>
		</form>
	<?php } ?>
</div>
