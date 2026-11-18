<?php
global $wpdb, $current_role;


include_once(TRCPDD_PATH . 'pages/formdata.php');

$user_id    = get_current_user_id();
//check files
$result_dir = tr_get_result_upload_dir();
$path       = $result_dir['path'];

if (isset($_POST['insert_files']) && $_POST['insert_files']) {
	$files = $_FILES['Results'];
	foreach ($files['name'] as $i => $outfile) {
		if (empty($outfile)) continue;

		$filepath = $path . $outfile;
		$tmp = $files['tmp_name'][$i];
		if (move_uploaded_file($tmp, $filepath)) {



			//check in db
			$assen  = strtolower($outfile);
			$assen = str_replace('.pdf', '', $assen);
			$mid = $wpdb->get_var("select mid from ci_batches_blood where assen = '{$assen}'");


			if ($mid > 0) {
				$wpdb->update('ci_batches_blood', array('result_pdf' => $outfile), array('mid' => $mid));


				trpdd_log_user($user_id, 'uploadedpdf_rs', 'Uploaded blood result pdf for #' . $assen, $mid, 'blood', '', '', $assen);
			} else {
				//not found
				Tr_Session_Class::add_error("This result \"{$assen}\" does not match an existing Requisition.");
			}
		} else {
			Tr_Session_Class::add_error('Can\'t upload file: ' . $outfile);
			continue;
		}
	}

	Tr_Session_Class::add("Uploaded successfully");
}

?>

<div class="wrap">
	<h2>Blood Results Upload</h2>
	<?php Tr_Session_Class::show() ?>
	<br><br>
	<div class="form-wrap center">
		<form method="post" enctype="multipart/form-data">
			<div class="frow center">
				<label>Result PDFs</label>


			</div>

			<div class="frow card_file">
				<a class="button selectfile" href="#">Choose File</a>
				<span class="view"></span>
				<input type="file" name="Results[]" multiple />
			</div>

			<div class="frow">
				<br><br>
				<input type="submit" class="button-primary" value="Submit" />
				<input type="hidden" name="insert_files" value="upload" />
			</div>
		</form>
	</div>
</div>