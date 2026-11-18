<?php
global $wpdb;
if ($act == 'pdelete' && $_POST['idd'] > 0) {
	$idd = $_POST['idd'];
	$clinic = $wpdb->get_row("select * from ci_clinic where cID = {$idd} ", ARRAY_A);

	$wpdb->delete('ci_clinic', array('cID' => $idd));
	Tr_Session_Class::add('Deleted Req record');

	$message = json_encode($clinic);

	trpdd_log_form('delete_clinic', $message);
	trpdd_log_user(get_current_user_id(), 'delete_cli', 'Delete Clinic', $idd, '', '', '', '');
}

if (isset($_REQUEST['s']) && !empty($_REQUEST['s'])) {
	$where_clinic .= $wpdb->prepare(" and cli_clinic like %s", '%' . $_REQUEST['s'] . '%');
}

if (isset($_REQUEST['rep']) && $_REQUEST['rep'] > 0) {
	$where_clinic .= " and `cli_rep-ID` = {$_REQUEST['rep']} ";
}

if (isset($_REQUEST['rlid']) && $_REQUEST['rlid'] > 0) {
	$where_clinic .= " and (`cli_ref_lab_ID` = {$_REQUEST['rlid']} or (cli_ref_lab_ID=0 and `cli_lab-ID` = {$_REQUEST['rlid']})) ";
}

$row_per_page = 100;
$pagination = new Tr_Pagination_class($row_per_page);
$pagination->setTotal($wpdb->get_var("SELECT count(*) as count FROM `ci_clinic` cli
left join ci_lab_reps lr on cli.`cli_rep-ID` = lr.rep_ID
{$join_clinic}
where 1 {$where_clinic}"));
$offset = $pagination->offset();


$sql = "SELECT cli.*,lr.rep_first,lr.rep_last, ma.ma_email
FROM `ci_clinic` cli
left join ci_lab_reps lr on cli.`cli_rep-ID` = lr.rep_ID
left join ci_clinic_manager ma on ma.ma_cli_ID = cli.cID
{$join_clinic}
where 1 {$where_clinic}
group by cli.cID
ORDER BY `cli_clinic` asc limit {$offset},{$row_per_page}";

if (isset($_REQUEST['debug'])) {
	echo $sql;
}
$results = $wpdb->get_results($sql);

$ref_labs = $wpdb->get_results("select * from ci_labs order by lab_ID asc");

?>
<div class="wrap">
	<h2>All ACCOUNT
		<?php if ($can_add_new) { ?>
			<a class="add-new-h2" href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '', 'paged' => '')) ?>">Add New</a>
		<?php } ?>
		<?php if ($is_ci_admin) { ?>
			<a class="add-new-h2" href="<?php Tr_Base_Class_V6::link(array('act' => 'export', 'trajax' => 1, 'id' => '', 'paged' => '')) ?>">Export</a>
			<a class="add-new-h2 thickbox" title="Import" href="<?php Tr_Base_Class_V6::link(array('act' => 'import', 'trajax' => 1, 'id' => '', 'paged' => '')) ?>">Import</a>
		<?php } ?>
	</h2>
	<?php Tr_Session_Class::show() ?>
	<div class="tablenav">
		<form method="get" style="float:left">
			<input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
			<input type="hidden" name="act" value="<?php echo isset($_GET['act']) ? $_GET['act'] : '' ?>" />
			<label>Name/Email</label>
			<input type="text" name="s" value="<?php echo isset($_REQUEST['s']) ? $_REQUEST['s'] : '' ?>" />
			<?php if ($current_role != 'lab_rep' && $can_add_new) : ?>
				<select name="rep" class="njs_select2">
					<option value="">Select Rep</option>
					<?php
					$reps = $wpdb->get_results("select * from ci_lab_reps where rep_inactive=0  {$where_rep} ORDER BY rep_first,rep_last ASC ");
					foreach ($reps as $rep) {
						$selected = isset($_REQUEST['rep']) && $rep->rep_ID == $_REQUEST['rep'] ? 'selected' : '';
						echo '<option value="' . $rep->rep_ID . '" ' . $selected . '>' . $rep->rep_first . ' ' . $rep->rep_last . '</option>';
					}
					?>
				</select>
			<?php endif; ?>

			<?php
			if (count($ref_labs) > 1) {
			?>
				<select name="rlid">
					<?php
					echo '<option value="">select Lab</option>';
					foreach ($ref_labs as $lab) {
						$selected = isset($_REQUEST['rlid']) && $lab->lab_ID == $_REQUEST['rlid'] ? 'selected' : '';
						echo '<option value="' . $lab->lab_ID . '" ' . $selected . '>' . $lab->lab_dba_init . ' - ' . $lab->lab_name . '</option>';
					}
					?>
				</select>
			<?php
			}
			?>
			<input type="submit" class="button bsty6" value="Search" />

		</form>
		<?php if ($pagination) echo $pagination->pagination() ?>
	</div>
	<div class="wraptbl">
		<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
			<thead class="sticky">
				<tr>
					<th style="width: 50px">#</th>
					<th>Clinic Name</th>
					<th style="width:90px">Phone</th>
					<th style="width: 90px;">Fax</th>
					<th>Email</th>
					<th>Rep</th>
					<th style="width:77px">Date</th>
					<?php if ($can_change_status) : ?>
						<th style="width:80px">Status</th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php
				$i = 1;
				$managers = array();
				if (tr_get_option('ci_pdf_data', 'off_o_cli')) {
					$managers = $wpdb->get_results("select * from ci_clinic_manager group by ma_email", ARRAY_A);
				}
				foreach ($results as $row) {

					if ($need_assign_doctors) {
						foreach ($doctors as $did) {
							$wpdb->query("INSERT IGNORE INTO ci_clinic_doc set doc_ID = {$did},
                                                    cli_ID = {$row->cID} ");
						}
					}
					if (tr_get_option('ci_pdf_data', 'off_o_cli')) {
						foreach ($managers as $marr) {
							$wpdb->query("INSERT IGNORE INTO ci_clinic_manager 
                                                    set ma_manager = '{$marr['ma_manager']}',
                                                    ma_email = '{$marr['ma_email']}',
                                                    ma_user_ID = {$marr['ma_user_ID']},
                                                    ma_cli_ID = {$row->cID}  ");
						}
					}
				?>
					<tr data-id="<?php echo $row->cID; ?>">
						<td><?php echo $row->cID ?></td>
						<td>
							<a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->cID)) ?>"><?php echo $row->cli_clinic; ?></a>
							<div class="row-actions">
								<?php
								do_action('lab_addon_admin_action', 'clinic_row', array('row' => $row));
								if ($enable_abx) {
								?>
									<spanc class="separate"><a class="thickbox" title="Local Resistance" href="<?php Tr_Base_Class_V6::link(array('trajax' => true, 'act' => 'abx_local_resistance', 'cid' => $row->cID, 'width' => '1000')) ?>">Local Resistance</a></span>
									<?php
								}
									?>
									<?php if ($can_delete) : ?>
										<span class="separate"><a class="pdelete separate" data-id="<?php echo $row->cID ?>">Delete</a></span>
									<?php endif; ?>
							</div>
						</td>
						<td><?php echo $row->{'cli_om-phone'} ?></td>
						<td><?php echo $row->{'cli_om-fax'} ?></td>
						<td><?php echo $row->ma_email ?></td>
						<td><?php echo $row->rep_first . ' ' . $row->rep_last ?></td>
						<td><?php echo $row->cli_sdate; ?></td>
						<?php if ($can_change_status) : ?>
							<td class="cli_status" data-status="<?php echo $row->cli_status; ?>"></td>
						<?php endif; ?>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	</div>

</div>
<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
<script>
	jQuery(function($) {

		var $statuses = {
			1: 'Active',
			0: 'Inactive'
		};
		$('.cli_status').each(function() {
			var $html = '<select class="njs_select2">';
			for (k in $statuses) {
				$html += '<option value="' + k + '" ' + (k == $(this).data('status') ? 'selected' : '') + '>' + $statuses[k] + '</option>';
			}
			$html += '</select>';
			$(this).html($html);
			if ($(this).data('status') == '0') {
				$(this).parents('tr').addClass('row_inactive');
			}

		});
		$('.cli_status select').change(function() {
			var $this = $(this);
			var $pr = $(this).parent();
			$pr.find('.sloading').remove();
			$pr.append('<div class="sloading"></div>');
			var $id = $pr.parents('tr').data('id');
			var $vl = $(this).val();
			$.ajax({
				type: 'post',
				dataType: 'json',
				data: {
					tr_action: 'ci_change_cli_status',
					id: $id,
					status: $vl
				},
				success: function(rs) {
					$pr.find('.sloading').remove();
					if (rs.status == 'ok') {
						if ($vl == '0') {
							$pr.parents('tr').addClass('row_inactive');
						} else {
							$pr.parents('tr').removeClass('row_inactive');
						}
					} else {
						$this.change();
					}
				},
				error: function() {
					$this.change();
				}
			})
		});

		$('a.delete').click(function() {
			if (confirm('Are you sure you want to delete #' + $(this).data('assen') + '?') == false) return false;
		})
	})
</script>