<?php

$gid        = isset($_REQUEST['gid']) ? $_REQUEST['gid'] : 0;
$btype      = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : $_REQUEST['type'];
$labid      = isset($_REQUEST['labid']) ? $_REQUEST['labid'] : 0;
$subact     = isset($_REQUEST['subact']) ? $_REQUEST['subact'] : '';
$btypename  = tr_pdd_get_typename($btype);
$user       = wp_get_current_user();

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

	if ($subact == 'addf') {
		$url = Tr_Base_Class_V6::link(array('act' => 'footers', 'subact' => '', 'id' => '', 'group_ID' => '', 'gid' => ''), false);
		echo '<meta http-equiv="refresh" content="0; url=' . $url . '">';
		exit;
	}
}


$data 		= array();
$histories 	= array();


if ($subact == 'editf') {
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
	<div class="flex items-center justify-between mb-4">
		<h3 class="text-lg font-semibold"> <?php echo ($subact == 'addf') ? 'Add New' : 'Edit' ?> <?php echo $btypename ?> Footer</h3>
		<div class="flex gap-3">
			<?php if (isset($_GET['pagetab']) && $_GET['pagetab'] == 'ci_test_panels' || $_GET['tabtype'] == 'pdf_footer') { ?>

				<a href="<?php Tr_Base_Class_V6::link(array('act' => 'footers', 'id' => '', 'gid' => '')) ?>" class="add-new-h2">All Footers</a>
			<?php } else { ?>
				<button onclick="window.location='<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'subact' => '', 'id' => $labid, 'lab_id' => $labid)) ?>'" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive text-primary-foreground h-9 px-4 py-2 has-[&gt;svg]:px-3 bg-primary hover:bg-primary/90">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-pen h-4 w-4 mr-2" aria-hidden="true">
						<path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
						<path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path>
					</svg>
					Edit Lab
				</button>
				<button onclick="window.location='<?php Tr_Base_Class_V6::link(array('act' => 'footers', 'subact' => '', 'id' => '', 'labid' => '', 'gid' => '')) ?>'" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3 border-[#3C3D59] text-[#3C3D59] hover:bg-[#3C3D59] hover:text-white">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-pen h-4 w-4 mr-2" aria-hidden="true">
						<path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
						<path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path>
					</svg>
					</svg>All Footers
				</button>

			<?php } ?>
		</div>
	</div>

	<?php Tr_Session_Class::show() ?>

	<form method="post" enctype="multipart/form-data">

		<?php if ($subact == 'addf') { ?>
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

		<?php if ($subact == 'editf') { ?>
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
						<a href="<?php Tr_Base_Class_V6::link(array('act' => 'footer_edit', 'subact' => 'editf', 'gid' => $history->group_ID, 'id' => $history->ID)) ?>"><?php echo $history->start_date . ' to ' . $history->end_date ?> </a>
						<?php if (count($histories) > 1) { ?>
							-
							<a class="delete" href="<?php Tr_Base_Class_V6::link(array(
														'act' => 'footers',
														'subact' => 'deletef_by_id',
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

		<button id="reg_lab_submit" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive text-primary-foreground h-9 px-4 py-2 has-[&gt;svg]:px-3 shadow-brand hover:shadow-brand-lg transition-shadow-smooth bg-primary hover:bg-primary/90">
			<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save h-4 w-4 mr-2" aria-hidden="true">
				<path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
				<path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
				<path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
			</svg>
			Save Footer
		</button>
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