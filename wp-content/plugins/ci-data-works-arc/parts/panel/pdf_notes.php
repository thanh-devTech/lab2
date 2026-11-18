<?php
global $wpdb;

$upload_dir = tr_wp_upload_dir();
$logo_dir = $upload_dir['basedir'] . '/logos';
$logo_url = $upload_dir['baseurl'] . '/logos';

$result_dir     = tr_get_result_upload_dir();

$tes_typename   = tr_pdd_get_typename($tes_type);

$user           = wp_get_current_user();
$can_delete     = isset($_GET['debug'])  || $user->user_login == 'Administrat0r';

if ($act == 'deletef') {

	$ID         = $_REQUEST['id'] ?? 0;
	$pid        = $_REQUEST['pid'] ?? 0;
	$group_ID   = $_REQUEST['group_ID'] ?? 0;

	if ($can_delete && $pid > 0) {

		$where_delete = array('parent_ID' => $pid);

		if ($ID > 0) {
			$where_delete = array('ID' => $ID);
		}


		$wpdb->delete('ci_pdf_notes', $where_delete);
		Tr_Session_Class::add('Deleted Notes group #' . $group_ID);
	} else {
		Tr_Session_Class::add_error('You cant delete Note #' . $group_ID);
	}


	$url = Tr_Base_Class_V6::link(array('act' => '', 'id' => '', 'group_ID' => '', 'gid' => ''), false);
	echo '<meta http-equiv="refresh" content="0; url=' . $url . '">';
	exit;
} else if ($act == 'deletef_by_id') {
	$ID         = $_REQUEST['id'];
	$gID        = $_REQUEST['gid'];
	$labid      = $_REQUEST['labid'];

	if ($can_delete) {
		$where_delete = array('ID' => $ID);
		$wpdb->delete('ci_pdf_notes', $where_delete);
		Tr_Session_Class::add('Deleted Note #' . $ID);
	} else {
		Tr_Session_Class::add_error('You cant delete Note');
	}

	$url = Tr_Base_Class_V6::link(array('act' => 'editf', 'id' => '', 'gid' => $gID), false);
	echo '<meta http-equiv="refresh" content="0; url=' . $url . '">';
	exit;
}




if ($act == 'addf' || $act == 'editf') {

	$gid        = isset($_REQUEST['gid']) ? $_REQUEST['gid'] : 0;

	if (isset($_POST['data'])) {

		$today = date_i18n('Y-m-d');

		$footerdata = tr_pdd_stripslashes_deep($_POST['data']);
		$footerdata['btype'] = $tes_type;
		foreach (array('start_date', 'end_date') as $date_key) {
			if (isset($footerdata[$date_key])) {
				$footerdata[$date_key] = trim($footerdata[$date_key]);
				if ($footerdata[$date_key] == 'now' || $footerdata[$date_key] == '0000-00' || $footerdata[$date_key] == '0000-00-00') {
					$footerdata[$date_key] = '';
				}
			}
		}
		if (isset($_POST['data']['exclude_panels_raw'])) {
			$footerdata['exclude_panels'] = implode(',', array_map('intval', $_POST['data']['exclude_panels_raw']));
		} else {
			$footerdata['exclude_panels'] = '';
		}
		unset($footerdata['exclude_panels_raw']);

		if (!isset($footerdata['is_abx'])) {
			$footerdata['is_abx'] = 0;
		}

		$update_old = array();

		$need_create_new = isset($_REQUEST['newversion']) && $_REQUEST['newversion'];

		if ($footerdata['ID'] > 0 && $need_create_new) {
			$old = $wpdb->get_row("select * from ci_pdf_notes where ID = {$footerdata['ID']}");
			$old->text = stripslashes($old->text);
			if ($old->text == $footerdata['text'] || $old->start_date == $today) {
				$need_create_new = false;
			}
			if ($need_create_new) {

				$update_old = array(
					'end_date' => $today,
					'ID' => $footerdata['ID'],
				);


				$footerdata['ID'] = 0;
				$footerdata['group_ID'] = $old->group_ID;
				$footerdata['parent_ID'] = $old->ID;
				$footerdata['start_date'] = $today;
				$footerdata['end_date'] = '';
			}
		}

		if ($footerdata['ID'] > 0) {
			$footerid = $footerdata['ID'];
			$old_start_date = $wpdb->get_var("select start_date from ci_pdf_notes where ID = {$footerid}");
			if ($old_start_date == '0000-00' || $old_start_date == '0000-00-00') {
				$old_start_date = '';
			}
			$rs = $wpdb->update('ci_pdf_notes', $footerdata, array('ID' => $footerdata['ID']));
			if ($rs === false) {
				Tr_Session_Class::add_error('Error. update Note wrong to db!');
				echo $wpdb->last_error;
			} else {
				Tr_Session_Class::add('Updated Note Successfully!');

				if (isset($footerdata['start_date']) && !empty($footerdata['start_date']) && $old_start_date != $footerdata['start_date']) {
					$lab_ID = isset($_REQUEST['lid']) ? intval($_REQUEST['lid']) : (isset($footerdata['lab_ID']) ? intval($footerdata['lab_ID']) : 0);
					$oldid = 0;
					if (!empty($old_start_date)) {
						$oldid = $wpdb->get_var("select ID from ci_pdf_notes
								where group_ID = {$gid}
								and btype = '{$tes_type}'
								 and lab_ID = '{$lab_ID}'
								 and ID != {$footerid}
								 and end_date = '{$old_start_date}'
								order by start_date desc, ID desc limit 1");
					}
					if (empty($oldid)) {
						$oldid = $wpdb->get_var("select ID from ci_pdf_notes
								where group_ID = {$gid}
								and btype = '{$tes_type}'
								 and lab_ID = '{$lab_ID}'
								 and ID < {$footerid}
								order by ID desc limit 1");
					}
					if ($oldid > 0) {
						$wpdb->update('ci_pdf_notes', array('end_date' => $footerdata['start_date']), array('ID' => $oldid));
					}
				}
			}
		} else {
			$footerdata['created'] = date_i18n('Y-m-d H:i:s');
			$footerdata['created_by'] = get_current_user_id();


			$wpdb->insert('ci_pdf_notes', $footerdata);
			$footerid = $wpdb->insert_id;

			if ($footerid > 0) {

				if (!isset($footerdata['parent_ID']) || empty($footerdata['parent_ID'])) {
					$wpdb->update('ci_pdf_notes', array('parent_ID' => $footerid), array('ID' => $footerid));
				}
				if ($update_old) {
					$wpdb->update('ci_pdf_notes', $update_old, array('ID' => $update_old['ID']));
					Tr_Session_Class::add('Updated Note Successfully!');
					wp_redirect(Tr_Base_Class_V6::link(array('act' => 'editf', 'gid' => $gid, 'id' => $footerid), false));
				} else {
					Tr_Session_Class::add('Created New Note Successfully!');
				}
			} else {
				Tr_Session_Class::add_error('Error. create Note wrong to db!');
			}
			$_POST = array();
		}

		//upload file
		if (isset($_FILES['pdf_file']) && !empty($_FILES['pdf_file']['name'])) {
			$filename = $footerid . '.pdf';

			if ($_FILES['pdf_file']['type'] == 'application/pdf') {
				if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $result_dir['path'] . '/' . $filename)) {
					$wpdb->update('ci_pdf_notes', array('pdf_file' => $filename), array('ID' => $footerid));
				}
			}
		}
	}


	$data = array();

	$histories = array();
	if ($act == 'editf') {
		$lid = isset($_REQUEST['lid']) ? $_REQUEST['lid'] : 0;
		$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;
		$pid = isset($_REQUEST['pid']) ? $_REQUEST['pid'] : 0;

		$whereedit = '';
		if (isset($_REQUEST['id']) && $_REQUEST['id'] > 0) {
			$whereedit .= " and ID = {$_REQUEST['id']} ";
		} else if (isset($_REQUEST['lid']) && $_REQUEST['lid'] > 0) {
			$whereedit .= " and lab_ID = {$_REQUEST['lid']} ";
		}
		$data = $wpdb->get_row("select * from ci_pdf_notes 
							where parent_ID = '{$pid}'
							and btype = '{$tes_type}'
							{$whereedit}
							 order by start_date desc
							limit 1", ARRAY_A);

		$histories = $wpdb->get_results("select * from ci_pdf_notes
							where parent_ID = {$pid}
							and btype = '{$tes_type}'
							 and lab_ID = '{$lid}' 
							order by start_date desc ,ID desc");

		if ($data['group_ID'] == 0) $data['group_ID'] = -1;

		if (empty($data['parent_ID'])) {
			$data['parent_ID'] = $data['ID'];
			$wpdb->update('ci_pdf_notes', array('parent_ID' => $data['parent_ID']), array('ID' => $data['ID']));
		}
	}
	$groups = array('-1' => 'All');
	$rsg = $wpdb->get_results("select tg_ID,tg_name from ci_test_groups 
							where tg_type='{$tes_type}' and tg_parent = 0 and tg_show_req = 1 order by tg_position asc
							");
	foreach ($rsg as $rg) {
		$groups[$rg->tg_ID] = $rg->tg_name;
	}
	$labs = array('0' => 'All lab');
	$rsl = $wpdb->get_results("select lab_ID, lab_name from ci_labs where 1 order by lab_ID asc");
	foreach ($rsl as $rl) {
		$labs[$rl->lab_ID] = $rl->lab_name;
	}

?>
	<div class="wrap">
		<h2><?php echo ($act == 'add') ? 'Add New' : 'Edit' ?> <?php echo $tes_typename ?> Note
			<a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">All Notes</a>
		</h2>
		<?php Tr_Session_Class::show() ?>

		<form method="post" enctype="multipart/form-data">

			<?php if ($act == 'addf') { ?>
				<div class="frow blabel">
					<div class="col2">
						<label>Panel:</label>
						<?php
						tr_select_option('data[group_ID]', $groups, $data['group_ID']);
						?>
					</div>
				</div>
			<?php } else {
				$group_name = 'All';
				if ($gid > 0) {
					$group_name = $wpdb->get_var("select tg_name from ci_test_groups where tg_ID = {$gid}");
				}


			?>
				<input type="hidden" name="data[group_ID]" value="<?php echo $data['group_ID'] ?>" />
				<div class="frow">
					<b><label>Panel:</label> <?php echo $group_name ?></b>
				</div>
				<div class="frow">
					<b>Start Date: </b>
					<input type="text" class="hasdate" name="data[start_date]" value="<?php echo empty($data['start_date']) || $data['start_date'] == '0000-00' || $data['start_date'] == '0000-00-00' ? '' : $data['start_date'] ?>" style="width:140px" placeholder="0000-00-00" />
				</div>
				<div class="frow">
					<b>End Date: </b>
					<input type="text" class="hasdate" name="data[end_date]" value="<?php echo empty($data['end_date']) || $data['end_date'] == '0000-00' || $data['end_date'] == '0000-00-00' ? '' : $data['end_date'] ?>" style="width:140px" placeholder="now" />
				</div>

			<?php
			} ?>

			<?php if (count($labs) > 2) { ?>
				<div class="frow blabel">
					<div class="col2">
						<label class="bold">Lab</label>
						<?php
						tr_select_option('data[lab_ID]', $labs, $data['lab_ID']);
						?>
					</div>
				</div>
			<?php } ?>

			<?php
			$is_global = (isset($data['group_ID']) && $data['group_ID'] <= 0) || ($act == 'addf');

			$exclude_panels_saved = !empty($data['exclude_panels']) ? array_map('intval', explode(',', $data['exclude_panels'])) : array();
			$rsp = $wpdb->get_results("select tg_ID, tg_name from ci_test_groups where tg_type='{$tes_type}' and tg_parent = 0 and tg_show_req = 1");
			if ($is_global && $rsp) {
			?>
				<div class="global-only-fields">
					<div class="frow blabel">
						<div class="col2">
							<label class="bold">Exclude Panels</label>
							<select name="data[exclude_panels_raw][]" class="js_select2" multiple style="height:120px;width:100%">
								<?php foreach ($rsp as $rp) { ?>
									<option value="<?php echo $rp->tg_ID ?>"
										<?php if (in_array($rp->tg_ID, $exclude_panels_saved)) echo 'selected' ?>>
										<?php echo $rp->tg_name ?>
									</option>
								<?php } ?>
							</select>
						</div>
					</div>
					<?php if ($tes_type == 'pcr') { ?>
						<div class="frow">
							<label>
								<input type="checkbox" name="data[is_abx]" value="1"
									<?php checked(!empty($data['is_abx'])) ?> />
								<b>igniteABX</b>
							</label>
						</div>
					<?php } ?>
				</div>
			<?php } ?>

			<div class="frow">
				<label class="bold">Note:</label>
				<div class="postarea">
					<?php wp_editor($data['text'], 'data_text', array('textarea_name' => 'data[text]', 'editor_height' => 500)) ?>
				</div>
				<div>
					<label>Shortcodes: [director_name]</label>
				</div>
			</div>
			<?php if ($act == 'editf') { ?>
				<div class="frow" style="padding:10px">
					<label><input type="radio" name="newversion" value="0" checked required /> Replace Old </label>
					<label><input type="radio" name="newversion" value="1" required /> New version </label>
				</div>
			<?php } ?>

			<div class="frow" style="padding:10px;margin-top:15px;margin-bottom:15px;">
				<label class="bold">Adding PDF</label>
				<input type="hidden" name="data[pdf_file]" value="<?php echo $data['pdf_file'] ?>" />
				<?php if (!empty($data['pdf_file'])) {
					echo '<div class="viewpdfdiv"><a target=_blank href="' . $result_dir['url'] . '/' . $data['pdf_file'] . '">View</a> <a class="removepdf button">Remove</a></div>';
				}
				?>
				<input type="file" name="pdf_file" accept="application/pdf" />
			</div>


			<?php
			if ($histories) {
			?>
				<div class="frow">
					<h5>Versions</h5>
					<?php
					foreach ($histories as $history) {
						if (empty($history->start_date) || $history->start_date == '0000-00' || $history->start_date == '0000-00-00') {
							$history->start_date = '0000-00-00';
						}
						if (empty($history->end_date) || $history->end_date == '0000-00' || $history->end_date == '0000-00-00') {
							$history->end_date = 'now';
							if (!empty($data['start_date']) && empty($_REQUEST['id']) && $data['ID'] != $history->ID) {
								$history->end_date = $data['start_date'];
								$wpdb->update('ci_pdf_notes', array('end_date' => $history->end_date), array('ID' => $history->ID));
							}
						}

						if ($history->start_date == $history->end_date) {
							$history->end_date = '';
							$wpdb->update('ci_pdf_notes', array('end_date' => ''), array('ID' => $history->ID));
						}
					?>
						<div class="frow">
							<a <?php if ($history->ID == $data['ID']) echo 'class="bold"' ?> href="<?php Tr_Base_Class_V6::link(array('act' => 'editf', 'gid' => $history->group_ID, 'id' => $history->ID)) ?>"><?php echo $history->start_date . ' to ' . $history->end_date ?> </a>
							-
							<a class="delete" href="<?php Tr_Base_Class_V6::link(array(
														'act' => 'deletef_by_id',
														'gid' => $history->group_ID,
														'lid' => '',
														'id' => $history->ID,
													)) ?>">Delete</a>
						</div>
					<?php
					}
					?>
				</div>
			<?php
			}

			?>

			<input type="hidden" name="data[ID]" value="<?php echo $data['ID'] ?>">
			<input type="submit" class="button-primary" value="Submit" />
		</form>
	</div>
	<script>
		jQuery(function($) {
			$('a.removepdf').click(function() {
				$('[name="data[pdf_file]"]').val('');
				$('.viewpdfdiv').remove();
			})

			function toggleGlobalFields() {
				var gid = $('[name="data[group_ID]"]').val();
				if (gid == '-1') {
					$('.global-only-fields').show();
				} else {
					$('.global-only-fields').hide();
				}
			}

			$('[name="data[group_ID]"]').on('change', toggleGlobalFields);
			toggleGlobalFields();
		})
	</script>
<?php
} else {

	$key = $tes_type == 'tox' ? 'ci_notes_result' : 'ci_notes_result_' . $tes_type;

	if (isset($_POST['notes'])) {
		$options = get_option($key, array());
		foreach ($_POST['notes'] as $k => $vl) {
			$options[$k] = $vl;
		}


		//checkupload file

		foreach ($_FILES as $k => $file) {
			$filename = $k . $file['name'];
			$filepath = $logo_dir . '/' . $filename;
			$info     = pathinfo($filename);
			$ext      = $info['extension'];

			if (in_array($ext, array('jpg', 'jpeg', 'png'))) {
				if (file_exists($filepath)) {
					unlink($filepath);
				}
				if (move_uploaded_file($file['tmp_name'], $filepath)) {
					$options[$k] = $filename;
				}
			}
		}
		update_option($key, $options);
		Tr_Session_Class::add("Saved");
	}

	$notes  = get_option($key);

?>
	<div class="wrap">

		<?php Tr_Session_Class::show() ?>

		<br><br>

		<?php
		//fix parent first
		$rows = $wpdb->get_results("select * from ci_pdf_notes where btype='{$tes_type}' and parent_ID = 0 order by ID asc");
		$parent_ids = array();
		foreach ($rows as $row) {
			if ($row->group_ID == 0) {
				$row->group_ID = -1;
			}
			if ($row->start_date == '0000-00-00') {
				$row->start_date = '';
			}
			if ($row->end_date == '0000-00-00') {
				$row->end_date = '';
			}
			if (!isset($parent_ids[$row->group_ID])) {
				$parent_ids[$row->group_ID] = $row->ID;
			}

			$parentid = $parent_ids[$row->group_ID];
			if (empty($row->start_date) && empty($row->end_date)) {
				$parentid = $row->ID;
			}
			$wpdb->update('ci_pdf_notes', array('parent_ID' => $parentid), array('ID' => $row->ID));
		}


		$note_rows = $wpdb->get_results("select ft.*,
                                    g.tg_name as group_name,
                                    lab.lab_name,
                                    count(*) as count
                                    from ci_pdf_notes ft
                                    left join ci_test_groups g ON g.tg_ID = ft.group_ID
                                    left join ci_labs lab ON lab.lab_ID = ft.lab_ID
                                    where ft.btype='{$tes_type}'
                                    group by parent_ID
                                    order by lab_ID, start_date desc");

		if (empty($note_rows)) {
			$wpdb->insert('ci_pdf_notes', array(
				'btype' => $tes_type,
				'text' => $notes[1],
				'group_ID' => -1,
				'created' => date_i18n('Y-m-d H:i:s')
			));
		}
		?>

		<div class="frow">
			<h4>Notes <a href="<?php Tr_Base_Class_V6::link(array('act' => 'addf', 'id' => '')) ?>"
					class="add-new-h2">Add New</a></h4>
		</div>

		<div class="wraptbl">
			<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
				<thead>
					<tr>
						<th style="width:50px">#</th>
						<th>Group</th>
						<th>Lab</th>
						<th>Last Edit</th>
						<th style="width:70px">Versions</th>
						<th style="width:70px">PDF</th>
						<?php if ($tes_type == 'pcr') { ?>
							<th style="width:70px">ABX</th>
						<?php } ?>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($note_rows as $i => $row) {
						if (empty($row->group_ID) || $row->group_ID == -1) {
							$row->group_name = 'All';
						} else if (empty($row->group_name)) {
							$row->group_name = '#' . $row->group_ID;
						}
						if (empty($row->start_date)) {
							$row->start_date = $row->created;
						}
						if (empty($row->lab_name)) {
							$row->lab_name = 'All';
						}
					?>
						<tr>
							<td><?php echo $row->ID ?></td>
							<td><?php echo $row->group_name ?></td>
							<td><?php echo $row->lab_name ?></td>
							<td><?php echo $row->start_date ?></td>
							<td><?php echo $row->count ?></td>
							<td><?php echo $row->pdf_file ? 'yes' : 'no' ?></td>
							<?php if ($tes_type == 'pcr') { ?>
								<td><?php echo $row->is_abx ? 'yes' : 'no' ?></td>
							<?php } ?>
							<td>
								<a href="<?php Tr_Base_Class_V6::link(array(
												'act' => 'editf',
												'gid' => $row->group_ID,
												'pid' => $row->parent_ID,
												'lid' => $row->lab_ID
											)) ?>">Edit</a>
								<?php if ($can_delete) { ?>
									-
									<a class="delete" href="<?php Tr_Base_Class_V6::link(array(
																'act' => 'deletef',
																'group_ID' => $row->group_ID,
																'pid' => $row->parent_ID,
																'lid' => $row->lab_ID
															)) ?>">Delete</a>
								<?php } ?>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>

		<div class="frow">
			<h4>Other</h4>
		</div>

		<form method="post" enctype="multipart/form-data">
			<?php
			if (defined('TRPDD_ARK') && TRPDD_ARK) {

				if (in_array($tes_type, array('pcr', 'blood', 'dna'))) {

					if (!empty($notes['1'])) {
			?>

						<div class="frow">
							<label>Notes #1</label>
							<textarea name="notes[1]" style="height: 200px;"><?php echo stripslashes($notes['1']) ?></textarea>
						</div>
					<?php }

					if (!empty($notes['2'])) {
					?>

						<div class="frow">
							<label>Show Note #2 from date</label>
							<input type="search" class="hasdate" autocomplete="off" name="notes[fromdate2]" value="<?php echo $notes['fromdate2'] ?>" />
						</div>
						<div class="frow">
							<label>Notes #2</label>
							<textarea name="notes[2]" style="height: 200px;"><?php echo stripslashes($notes['2']) ?></textarea>
						</div>
					<?php }

					if (!empty($notes['notcovid'])) {
					?>

						<div class="frow">
							<label>Notes For Not Covid</label>
							<textarea name="notes[notcovid]" style="height: 200px;"><?php echo stripslashes($notes['notcovid']) ?></textarea>
						</div>
					<?php } ?>

					<?php if ($tes_type == 'pcr') {
						if (!empty($notes['uti_abx'])) { ?>
							<div class="frow">
								<label>Notes For UTI w/ABX</label>
								<textarea name="notes[uti_abx]" style="height: 200px;"><?php echo stripslashes($notes['uti_abx']) ?></textarea>
							</div>
						<?php } ?>
						<?php if (!empty($notes['wound_abx'])) { ?>
							<div class="frow">
								<label>Notes For Wound w/ABX</label>
								<textarea name="notes[wound_abx]" style="height: 200px;"><?php echo stripslashes($notes['wound_abx']) ?></textarea>
							</div>
						<?php } ?>
						<?php if (!empty($notes['new_abx'])) { ?>

							<div class="frow">
								<label>Notes for New ABX(Notes for Clinical Decision Support)</label>
								<textarea name="notes[new_abx]" style="height: 200px;"><?php echo stripslashes($notes['new_abx']) ?></textarea>
							</div>
						<?php } ?>
				<?php
					}
				}
			}
			if ($tes_type == 'pcr') {
				?>

				<div class="frow">
					<label style="width:300px;display:inline-block">PDF Logo</label>
					<input type="file" name="pdf_logo" />
					<input type="hidden" name="notes[pdf_logo]" value="<?php echo $notes['pdf_logo'] ?>" />
					<?php if (! empty($notes['pdf_logo'])) {
						echo '<span class="ct_logo_image">';
						echo '<img src="' . $logo_url . '/' . $notes['pdf_logo'] . '?" style="width:50px;height:auto">';
						echo '<a class="button remove_logo_image">Remove</a>';
						echo '</span>';
					}
					?>
				</div>

				<div class="frow">
					<label style="width:300px;display:inline-block">Adding Image to Result PDF after Notes</label>
					<input type="file" name="result_imagenote" />
					<input type="hidden" name="notes[result_imagenote]" value="<?php echo $notes['result_imagenote'] ?>" />
					<?php if (! empty($notes['result_imagenote'])) {
						echo '<span class="ct_result_imagenote">';
						echo '<img src="' . $logo_url . '/' . $notes['result_imagenote'] . '?" style="width:50px;height:auto">';
						echo '<a class="button remove_result_imagenote">Remove</a>';
						echo '</span>';
					}
					?>
				</div>
				<div class="frow">
					<label style="width:300px;display:inline-block">Adding Image to Result PDF in New Page</label>
					<input type="file" name="result_image" />
					<input type="hidden" name="notes[result_image]" value="<?php echo $notes['result_image'] ?>" />
					<?php if (! empty($notes['result_image'])) {
						echo '<span class="ct_result_image">';
						echo '<img src="' . $logo_url . '/' . $notes['result_image'] . '?" style="width:50px;height:auto">';
						echo '<a class="button remove_result_image">Remove</a>';
						echo '</span>';
					}
					?>
					<label>
						<input type="checkbox" name="notes[onlyscovid]"
							value="1" <?php checked($notes['onlyscovid']) ?> /> Only show for Covid
					</label>
				</div>

				<div class="frow">
					<label>Notes for reject "INSUFFICIENT SAMPLE"</label>
					<textarea name="notes[rejectin]"
						style="height: 150px;"><?php echo stripslashes($notes['rejectin']) ?></textarea>
				</div>




				<script>
					jQuery(function($) {
						$('.remove_logo_image').click(function() {
							$('[name="notes[pdf_logo]"]').val('');
							$('.ct_logo_image').remove();
						})
						$('.remove_result_image').click(function() {
							$('[name="notes[result_image]"]').val('');
							$('.ct_result_image').remove();
						})
						$('.remove_result_imagenote').click(function() {
							$('[name="notes[result_imagenote]"]').val('');
							$('.ct_result_imagenote').remove();
						})
						$('body').on('click', '#TB_ajaxContent a', function() {
							var $url = $(this).attr('href');
							tb_remove();
							setTimeout(function() {
								tb_show('', $url);
							}, 500);
							return false;
						})
					})
				</script>

			<?php
			} else if ($tes_type == 'blood') {
			?>

				<div class="frow">
					<label>Style #2 Notes above Other Notes</label>
					<textarea name="notes[style2_notes]"
						style="height: 200px;"><?php echo stripslashes($notes['style2_notes']) ?></textarea>
				</div>
				<div class="frow">
					<label>Style #2 Notes at bottom</label>
					<textarea name="notes[style2_notes_bottom]"
						style="height: 200px;"><?php echo stripslashes($notes['style2_notes_bottom']) ?></textarea>
				</div>
			<?php
			} else if (in_array($tes_type, array('tox'))) {
			?>

				<div class="frow">
					<label>Last Page Text</label>
					<textarea name="notes[last_page_text]"
						style="height: 400px;"><?php echo stripslashes($notes['last_page_text']) ?></textarea>
				</div>
			<?php
			}
			?>
			<div class="frow">
				<input type="submit" class="button-primary" value="Save" />
			</div>
		</form>

	</div>
<?php
}
