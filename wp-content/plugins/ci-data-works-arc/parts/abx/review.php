<?php
global $wpdb;

if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'search_report') {
	$search  = trim($_REQUEST['term']);
	$sql = $wpdb->prepare("select assen, CONCAT(assen,' - ', patient_name)  as label
	 from ci_batches_pcr where assen = %s or patient_name like %s", $search, '%' . $search . '%');
	$rs = $wpdb->get_results($sql, ARRAY_A);

	wp_send_json($rs);
	exit;
} else if (isset($_POST['assen'])) {
	$assen 		= stripslashes($_POST['assen']);
	$comment 	= stripslashes($_POST['comment']);
	$addmailing = isset($_POST['addmailing']);
	$request_review = isset($_POST['request_review']);

	$user = wp_get_current_user();
	$rs = false;
	if (!empty($comment)) {
		$rs = tr_client_send_hub(array(
			'action' 	=> 'review',
			'assen' 	=> $assen,
			'comment' 	=> $comment,
			'addmailing' => $addmailing ? 1 : 0,
			'request_review' => $request_review ? 1 : 0,
			'email' 	=> $user->user_email,
			'fullname'	=> $user->display_name,
		));
	}

	if (is_array($rs) && $rs['status'] == 'ok') {
		$sent = true;
	} else {
		Tr_Session_Class::add_error("Error. Please try again!!");
	}
}

wp_enqueue_script('jquery-ui-autocomplete');
?>
<div class="wrap">
	<h2 class="center">Request ABX review</h2>
	<br>
	<?php
	Tr_Session_Class::show();
	if ($sent) {
		echo '<h2 class="center">Sent Successfully!</h2>';
		echo '<h2 class="center">Thank you!</h2>';
	} else { ?>
		<form method="post">
			<div class="frow">
				<label>Select Report(Accession/Patient name):</label>
				<input type="text" id="select_report" required />
				<input type="hidden" name="assen" value="" required />
			</div>
			<div class="frow" id="review"></div>
			<div class="frow">
				<label>Ask a question/leave a comment</label>
				<textarea name="comment" required></textarea>
			</div>
			<div class="frow">
				<label><input type="checkbox" name="request_review" value="1" /> request a review (if something doesn't seem right)</label>
			</div>
			<div class="frow">
				<input type="submit" class="button-primary" value="Submit" />
			</div>
		</form>
	<?php } ?>
</div>

<script>
	var $ajaxurl = '<?php echo Tr_Base_Class_V6::link(array('trajax' => 'search_report')) ?>';
	jQuery(function($) {
		$('#select_report').autocomplete({
			source: $ajaxurl,
			minLength: 3,
			select: function(event, ui) {
				if (ui.item) {
					$('[name="assen"]').val(ui.item.assen);
				}
			}
		});
	})
</script>