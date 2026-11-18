<?php
global $wpdb;

$gid        = isset($_REQUEST['gid']) ? $_REQUEST['gid'] : 0;
$btype      = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : $_REQUEST['type'];
$labid      = isset($_REQUEST['labid']) ? $_REQUEST['labid'] : 0;
$btypename  = tr_pdd_get_typename($btype);
$user       = wp_get_current_user();
$can_delete = isset($_GET['debug'])  || $user->user_login == 'Administrat0r';

if (empty($labid)) {
	$labid = CI_Base_Lab::get_current_lab_id();
}
if ($labid > 0) {
	$lab = $wpdb->get_row("select * from ci_labs where lab_ID = {$labid}", ARRAY_A);
}


if ($act == 'deletef') {

	$ID         = $_REQUEST['id'];
	$group_ID   = $_REQUEST['group_ID'];
	$labid      = $_REQUEST['labid'];

	if ($can_delete) {
		$where_delete = array('group_ID' => $group_ID, 'lab_ID' => $labid);
		if ($ID > 0) {
			$where_delete = array('ID' => $ID);
		}
		$wpdb->delete('ci_pdf_footer', $where_delete);
		Tr_Session_Class::add('Deleted Footer group #' . $group_ID);
	} else {
		Tr_Session_Class::add_error('You cant delete group #' . $group_ID);
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
		$wpdb->delete('ci_pdf_footer', $where_delete);
		Tr_Session_Class::add('Deleted Footer #' . $ID);
	} else {
		Tr_Session_Class::add_error('You cant delete footer');
	}

	$url = Tr_Base_Class_V6::link(array('act' => 'editf', 'id' => '', 'gid' => $gID), false);
	echo '<meta http-equiv="refresh" content="0; url=' . $url . '">';
	exit;
}



if (isset($_POST['footer'])) {

	$today = date_i18n('Y-m-d');

	$footerdata = tr_pdd_stripslashes_deep($_POST['footer']);
	$footerdata['btype'] = $btype;
	$footerdata['lab_ID'] = $labid;

	$update_old = array();

	$need_create_new = isset($_REQUEST['newversion']) && $_REQUEST['newversion'];

	if ($footerdata['ID'] > 0 && $need_create_new) {
		$old = $wpdb->get_row("select * from ci_pdf_footer where ID = {$footerdata['ID']}");
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
			$footerdata['start_date'] = $today;
		}
	} else if ($need_create_new) {
		$footerdata['ID'] = 0;
	}

	if ($footerdata['ID'] > 0) {
		$rs = $wpdb->update('ci_pdf_footer', $footerdata, array('ID' => $footerdata['ID']));
		if ($rs === false) {
			Tr_Session_Class::add_error('Error. update Footer wrong to db!');
			echo $wpdb->last_error;
		} else {
			Tr_Session_Class::add('Updated Footer Successfully!');
			if (isset($footerdata['start_date']) && !empty($footerdata['start_date'])) {
				$oldid = $wpdb->get_var("select ID from ci_pdf_footer
							where group_ID = {$gid}
							and lab_ID = {$labid}
							and btype = '{$btype}'
							 and ID < {$footerdata['ID']}
							order by ID desc limit 1");
				if ($oldid > 0) {
					$wpdb->update('ci_pdf_footer', array('end_date' => $footerdata['start_date']), array('ID' => $oldid));
				}
			}
		}
	} else {
		$same_start_id = 0;
		if (!empty($footerdata['start_date'])) {
			$same_start_id = intval($wpdb->get_var($wpdb->prepare(
				"select ID from ci_pdf_footer
				where group_ID = %d
				and lab_ID = %d
				and btype = %s
				and start_date = %s
				order by ID desc
				limit 1",
				$footerdata['group_ID'],
				$labid,
				$btype,
				$footerdata['start_date']
			)));
		}

		if ($same_start_id > 0) {
			$footerdata['created'] = date_i18n('Y-m-d H:i:s');
			$footerdata['created_by'] = get_current_user_id();
			$rs = $wpdb->update('ci_pdf_footer', $footerdata, array('ID' => $same_start_id));
			if ($rs === false) {
				Tr_Session_Class::add_error('Error. update Footer wrong to db!');
				echo $wpdb->last_error;
			} else {
				if ($update_old) {
					$wpdb->update('ci_pdf_footer', $update_old, array('ID' => $update_old['ID']));
				}
				Tr_Session_Class::add('Updated Footer Successfully!');
			}
			$_POST = array();
		} else {
			$footerdata['created'] = date_i18n('Y-m-d H:i:s');
			$footerdata['created_by'] = get_current_user_id();

			$wpdb->insert('ci_pdf_footer', $footerdata);
			$footerid = $wpdb->insert_id;
			if ($footerid > 0) {
				if ($update_old) {
					$wpdb->update('ci_pdf_footer', $update_old, array('ID' => $update_old['ID']));
					Tr_Session_Class::add('Updated Footer Successfully!');
				} else {
					Tr_Session_Class::add('Created New Footer Successfully!');
				}
			} else {
				Tr_Session_Class::add_error('Error. create Footer wrong to db!');
			}
			$_POST = array();
		}
	}
}

if ($act == 'addf' || $act == 'editf') {

	$data = array();

	$histories = array();
	if ($act == 'editf') {
		$whereedit = '';
		if ($_REQUEST['id'] > 0) {
			$whereedit .= " and ID = {$_REQUEST['id']} ";
		}
		$data = $wpdb->get_row("select * from ci_pdf_footer 
							where group_ID = '{$gid}'
							and lab_ID = {$labid}
							and btype = '{$btype}'
							{$whereedit}
							 order by start_date desc
							limit 1", ARRAY_A);

		$histories = $wpdb->get_results("select * from ci_pdf_footer
							where group_ID = {$gid}
							and lab_ID = {$labid}
							and btype = '{$btype}'
							order by ID desc");

		$data['text'] = stripslashes($data['text']);
	}
	$groups = array('-1' => 'Default');
	$rsg = $wpdb->get_results("select tg_ID,tg_name from ci_test_groups 
							where tg_type='{$btype}'
							and tg_ID not in (select group_ID from ci_pdf_footer where btype = '{$btype}')");
	foreach ($rsg as $rg) {
		$groups[$rg->tg_ID] = $rg->tg_name;
	}


?>
	<div class="wrap">
		<h2><?php echo ($act == 'add') ? 'Add New' : 'Edit' ?> <?php echo $btypename ?> Footer
			<?php if (isset($_GET['pagetab']) && $_GET['pagetab'] == 'ci_test_panels' || $_GET['tabtype'] == 'pdf_footer') { ?>
				<a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '', 'labid' => '', 'gid' => '')) ?>" class="add-new-h2">All Footers</a>
			<?php } else { ?>
				<a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '', 'gid' => '')) ?>" class="add-new-h2">All Footers</a>
			<?php } ?>
		</h2>
		<?php Tr_Session_Class::show() ?>

		<form method="post" enctype="multipart/form-data">

			<?php if ($act == 'addf') { ?>
				<div class="frow blabel">
					<div class="col2">
						<label>Group</label>
						<select name="footer[group_ID]">
							<?php
							foreach ($groups as $groupk => $grouptxt) {
								$selected = isset($data['group_ID']) && $data['group_ID'] == $groupk ? 'selected' : '';
								echo '<option value="' . $groupk . '" ' . $selected . '>' . $grouptxt . '</option>';
							}
							?>
						</select>
					</div>
					<div class="col2">
						<?php
						//$this->input('end_date',array('label'=>'<b>End Date</b>','attrs'=>'class="hasdate" data-write="true"'));
						//$this->input('start_date',array('label'=>'<b>Start Date</b>','attrs'=>'class="hasdate" data-write="true"'));
						?>
					</div>
				</div>
			<?php } else {
				$group_name = 'Global (Default)';
				if ($gid > 0) {
					$group_name = $wpdb->get_var("select tg_name from ci_test_groups where tg_ID = {$gid}");
				}


			?>
				<input type="hidden" name="footer[group_ID]" value="<?php echo $data['group_ID'] ?>" />
				<div class="frow">
					<b><label>Group:</label> <?php echo $group_name ?></b>
				</div>
				<div class="frow">
					<b>Start Date: </b>
					<?php
					if (isset($_GET['debug'])) {
					?>
						<input type="text" class="hasdate" name="footer[start_date]" value="<?php echo $data['start_date'] ?>" style="width:130px" />
					<?php
					} else {
						echo empty($data['start_date']) ? '0000-00-00' : $data['start_date'];
					}
					?>
				</div>
				<div class="frow">
					<b>End Date: </b> <?php echo empty($data['end_date']) ? 'now' : $data['end_date'] ?>
				</div>

			<?php
			} ?>

			<div class="frow">
				<h4>Header</h4>
				<?php
				wp_editor($data['header'], 'header_text', array('media_buttons' => false, 'editor_height' => 200, 'textarea_name' => 'footer[header]'));
				?>
			</div>

			<div class="frow">
				<h4>Footer</h4>
				<?php
				wp_editor(isset($data['text']) ? $data['text'] : '', 'footer_text', array('media_buttons' => false, 'editor_height' => 400, 'textarea_name' => 'footer[text]'));
				?>
			</div>
			<div class="frow shortcodes">
				Shortcode:
				<?php
				$searchs = array(
					'[lab]',
					'[lab_name]',
					'[assen]',
					'[patient_name]',
					'[lab_address]',
					'[lab_director]',
					'[lab_phone]',
					'[lab_clia]',
					'[lab_cap]',
					'[lab_cola]',
					'[cap]',
					'[cola]',
				);
				foreach ($searchs as $sk) {
					echo '<span>' . $sk . '</span>';
				}
				?>
			</div>

			<?php if ($act == 'editf') { ?>
				<div class="frow">
					<label><input type="radio" name="newversion" value="0" checked required /> Replace Old </label>
					<label><input type="radio" name="newversion" value="1" required /> New version </label>
				</div>
			<?php } ?>

			<?php
			if ($histories) {
			?>
				<div class="frow">
					<h5>History</h5>
					<?php
					foreach ($histories as $i => $history) {
						if (empty($history->start_date)) {
							$history->start_date = '0000-00-00';
						}
						if (empty($history->end_date)) {
							$history->end_date = 'now';
							if (!empty($data['start_date']) && $history->ID != $data['ID'] && empty($_REQUEST['id'])) {
								$history->end_date = $data['start_date'];
								$wpdb->update('ci_pdf_footer', array('end_date' => $history->end_date), array('ID' => $history->ID));
							}
						} else if ($i == 0) {
							$wpdb->update('ci_pdf_footer', array('end_date' => ''), array('ID' => $history->ID));
						} else if ($i == count($histories) - 1) {
							$wpdb->update('ci_pdf_footer', array('start_date' => ''), array('ID' => $history->ID));
						}
					?>
						<div class="frow">
							<a href="<?php Tr_Base_Class_V6::link(array('act' => 'editf', 'gid' => $history->group_ID, 'id' => $history->ID)) ?>"><?php echo $history->start_date . ' to ' . $history->end_date ?> </a>
							<?php if (count($histories) > 1) { ?>
								-
								<a class="delete" href="<?php Tr_Base_Class_V6::link(array(
															'act' => 'deletef_by_id',
															'gid' => $history->group_ID,
															'lid' => '',
															'id' => $history->ID,
														)) ?>">Delete</a>
							<?php } ?>
						</div>
					<?php
					}
					?>
				</div>
			<?php
			}

			if (count($histories) <= 1 && isset($data['ID']) && $data['ID'] > 0) {
				$wpdb->update('ci_pdf_footer', array('end_date' => '', 'start_date' => ''), array('ID' => $data['ID']));
			}

			?>
			<input type="hidden" name="footer[ID]" value="<?php echo isset($data['ID']) ? $data['ID'] : '' ?>" />
			<input type="submit" class="button-primary" value="Submit" />
		</form>
	</div>
	<style>
		.shortcodes span {
			display: inline-block;
			padding: 2px 10px;
			cursor: pointer;
		}
	</style>
	<script>
		jQuery(function($) {
			$('body').on('click', '.shortcodes span', function() {


				if (document.body.createTextRange) {
					const range = document.body.createTextRange();
					range.moveToElementText(this);
					range.select();
				} else if (window.getSelection) {
					const selection = window.getSelection();
					const range = document.createRange();
					range.selectNodeContents(this);
					selection.removeAllRanges();
					selection.addRange(range);
				} else {
					console.warn("Could not select text in node: Unsupported browser.");
				}

				navigator.clipboard.writeText($(this).html());
			})
		})
	</script>
<?php
} else {
	$where  = " ft.btype='{$btype}' ";
	if ($labid > 0) {
		$where .= " and ft.lab_ID = {$labid} ";
	}

	$wpdb->update('ci_pdf_footer', array('group_ID' => -1), array('group_ID' => 0));

	$sql_footers = "select ft.*,
								g.tg_name as group_name,
								lab.lab_name,
								count(*) as count
                                from ci_pdf_footer ft
                                left join ci_test_groups g ON g.tg_ID = ft.group_ID
                                left join ci_labs lab ON lab.lab_ID = ft.lab_ID
                                where {$where}
                                group by group_ID, lab_ID
                                order by start_date desc";

	$footers = $wpdb->get_results($sql_footers);


?>
	<div class="wrap">
		<h2><?php echo $btypename . ' Result Footer ' . ($labid > 0 ? 'for lab ' . $lab['lab_dba_init'] : '') ?>
			<?php if ($labid > 0) { ?>
				<a href="<?php Tr_Base_Class_V6::link(array('subpage' => '', 'act' => 'edit', 'id' => $labid, 'labid' => '')) ?>" class="add-new-h2">Edit Lab</a>
			<?php } ?>
			<a href="<?php Tr_Base_Class_V6::link(array('act' => 'addf', 'id' => '')) ?>" class="add-new-h2">Add New</a>
		</h2>

		<?php Tr_Session_Class::show() ?>

		<div class="wraptbl">
			<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
				<thead>
					<tr>
						<th style="width:50px">#</th>
						<th>Lab</th>
						<th>Group</th>
						<th>Last Edit</th>
						<th>Versions</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($footers as $i => $footer) {
						if (empty($footer->group_name)) {
							$footer->group_name = 'Global (default)';
						}
					?>
						<tr>
							<td><?php echo $footer->ID ?></td>
							<td><?php echo $footer->lab_name ?></td>
							<td><?php echo $footer->group_name ?></td>
							<td><?php echo $footer->start_date ?></td>
							<td><?php echo $footer->count ?></td>

							<td>
								<a href="<?php Tr_Base_Class_V6::link(array('act' => 'editf', 'gid' => $footer->group_ID, 'labid'  => $footer->lab_ID)) ?>">Edit</a>
								<?php if ($can_delete) { ?>
									-
									<a class="delete" href="<?php Tr_Base_Class_V6::link(array(
																'act' => 'deletef',
																'id'  => $footer->ID,
																'group_ID'  => $footer->group_ID,
																'labid'  => $footer->lab_ID,
															)) ?>">Delete</a>
								<?php } ?>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>


		<script>
			jQuery(function($) {
				$('a.delete').click(function() {
					if (confirm('Do you want delete?') == false) return false;
				})
			})
		</script>
	</div>
<?php
}
