<?php
global $wpdb, $current_role, $is_ci_admin;

$user_id = get_current_user_id();

if ($act == 'pdelete') {
	$id = $_REQUEST['id'] > 0 ? $_REQUEST['id'] : $_REQUEST['idd'];
	$wpdb->delete('ci_message_users', array('msg_ID' => $id));
	$wpdb->delete('ci_messages', array('ID' => $id));
	update_option('trpdd_notification_cache_last_changed_at', time(), false);
	Tr_Session_Class::add('Deleted Message Successfully');
}

$where = " me.ID > 0 and me.msg_type = '{$msg_type}'  ";
if (!$is_ci_admin) {
	$where = " and  me.author = {$user_id} ";
}
$row_per_page = 100;
$pagination = new Tr_Pagination_class($row_per_page);
$pagination->setTotal($wpdb->get_var("select count(*) as c from ci_messages me  WHERE {$where}"));
$offset = $pagination->offset();


$sql = "select me.*, u.display_name from ci_messages me
		left JOIN {$wpdb->users} u ON u.ID = me.author
        WHERE {$where}
        order by me.created_date desc 
		limit {$offset},{$row_per_page}";
$messages = $wpdb->get_results($sql);
?>
<div class="wrap">
	<h2><?php echo $msg_type_label . 's' ?> <a class="add-new-h2" href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>">Add a <?php echo $msg_type_label ?></a></h2>
	<?php Tr_Session_Class::show() ?>

	<div class="tablenav">
		<?php if ($pagination) echo $pagination->pagination() ?>
	</div>

	<table class="wp-list-table widefat fixed striped posts">
		<thead>
			<tr>
				<th style="width:50px">#</th>
				<th>Subject</th>
				<th>Msg</th>
				<th style="width:90px">From</th>
				<th style="width:90px">To</th>
				<th style="width:30px">Bg</th>
				<?php if ($is_ci_admin) { ?>
					<th style="width:120px">Created by</th>
				<?php } ?>
				<th style="width:120px">Created</th>
				<th style="width:100px">Actions</th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ($messages as $i => $row) {
				$msg = substr(strip_tags($row->msg), 0, 400);
				if ($msg_type == 'a') {
					$row->background = $alertbgcolors[$row->background]['color'];
				}

			?>
				<tr>
					<td><?php echo $i + 1 ?></td>
					<td><a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->ID)) ?>"><?php echo $row->subject ?></a></td>
					<td>
						<?php echo $msg ?>...
					</td>
					<td><?php echo $row->from_date ?></td>
					<td><?php echo $row->to_date ?></td>
					<td style="background:<?php echo $row->background ?>"></td>
					<?php if ($is_ci_admin) { ?>
						<td><?php echo $row->display_name ?></td>
					<?php } ?>
					<td><?php echo $row->created_date ?></td>
					<td>
						<a class="pdelete" href="#" data-id="<?php echo $row->ID ?>">Delete</a>

					</td>
				</tr>
			<?php
			}
			?>
		</tbody>
	</table>

	<div class="tablenav">
		<?php if ($pagination) echo $pagination->pagination() ?>
	</div>
</div>