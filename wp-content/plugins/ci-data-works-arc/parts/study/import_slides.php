<?php


if (isset($_FILES['files']['tmp_name'])) {

	$log_dir = WP_CONTENT_DIR . '/log/study_slides/';
	if (!is_dir($log_dir)) {
		wp_mkdir_p($log_dir);
	}
	$files = array();
	if (!is_array($_FILES['files']['tmp_name'])) {
		$files[] = $_FILES['files'];
	} else {
		foreach ($_FILES['files']['tmp_name'] as $i => $t) {
			$new_file = array();
			foreach ($_FILES['files'] as $key => $f) {
				$new_file[$key] = $f[$i];
			}
			$files[] = $new_file;
		}
	}
	$uploaded = 0;

	foreach ($files as $file) {
		$filename 	= $file['name'];
		$filetmp  	= $file['tmp_name'];
		$info 		= pathinfo($filename);
		$ext 		= strtolower($info['extension']);
		$filepath 	= $log_dir . $filename;
		if (file_exists($filepath)) {
			unlink($filepath);
		}
		if (in_array($ext, array('csv', 'xls', 'xlsx'))) {
			if (move_uploaded_file($filetmp, $filepath)) {

				$import_slide = $_POST['import_type'] == 'slide';

				$required_cols = array(
					'subjectid',
					'dateofcollection' => array('dateofcollection', 'collectiondate'),
					'timeofcollection' => array('timeofcollection', 'collectiontime'),
				);

				if ($import_slide) {
					$required_cols[] = '13-digitaccessionnumber';
					$required_cols['accessionnumber'] = array('accessionnumber', 'accessionnumber (7 alpha/numeric)');
				}

				$data = trpdd_get_data($filepath, $ext, $required_cols, true, false, true);

				if (count($data['data']) == 0) {
					Tr_Session_Class::add_error("file {$filename}: no data");
					continue;
				}

				foreach ($data['data'] as $row) {
					$patientid 	= trpdd_get_string_exist($row, array('subjectid', 'Subject ID'));
					$assnum 	= trpdd_get_string_exist($row, array('13-digitaccessionnumber', ''));
					$doc 		= $row['dateofcollection'];
					$toc 		= $row['timeofcollection'];
					$slideid 	= $row['barcode'];
					$code		= $row['accessionnumber'];


					$doc = date('Y-m-d H:i:s', strtotime($doc . ' ' . $toc));


					if ( empty($assnum) || empty($code)) {
						continue;
					}

					$rb = $wpdb->get_row("select rb.ID,
								rb.slides
								 from {$re_batch_tbl} rb
								JOIN {$receiving_tbl} re ON re.re_ID = rb.re_ID
									where re_type = 1 and code = '{$code}' ");
					if ($import_slide) {
						if ($rb) {
							$slides = json_decode($rb->slides, true);
							$found_assen = false;
							foreach ($slides as $i => $arr) {
								if ($arr['assen'] == $assnum) {
									$found_assen = true;
									break;
								}
							}

							if (!$found_assen) {
								$slides[] = array(
									'assen' => $assnum,
									'doc' => $doc,
									'slideid' => $slideid,
								);

								$rb->slides = json_encode($slides);

								$wpdb->update($re_batch_tbl, array('slides' => $rb->slides), array('ID' => $rb->ID));
							}
						}
					}else if(!$rb){
						$rowdata = array(
							'doc' => $doc,
						);
						//$wpdb->insert($re_batch_tbl, $rowdata);
					}
				}

				Tr_Session_Class::add('Imported file: ' . $filename);
			}
		}
	}
}

?>

<div class="wrap">
	<?php Tr_Session_Class::show() ?>
	<div class="form-wrap center">
		<form enctype="multipart/form-data" method="post">

			<div class="inline card_file">
				<a class="button selectfile" href="#">Choose File</a>
				<span class="view"></span>
				<input type="file" id="upload-files" require="true" name="files[]" accept="text/csv" multiple />
			</div>

			<div class="inline">
				<label class="inline"><input type="radio" name="import_type" value="frozen" /> Frozen Blood</label>
				&nbsp;&nbsp;
				<label class="inline"><input type="radio" name="import_type" value="slide" checked /> Slides</label>
			</div>


			<div class="inline">
				<input type="submit" value="Upload" class="button-primary" />
			</div>

		</form>
	</div>
</div>