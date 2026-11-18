<?php
global $wpdb,$ci_all_btypes;


$act = isset($_REQUEST['act'])? $_REQUEST['act'] :'';
if($act=='approve')
{
	$id = $_REQUEST['id'];
	$row = $wpdb->get_row("select * from {$wpdb->signups} where signup_id = {$id} ");
	if($row)
	{
		$rs = wpmu_activate_signup($row->activation_key);

		if(!is_wp_error($rs))
		{
			Tr_Session_Class::add("New user Password: ".$rs['password']);

			$message = "Password: ".$rs['password'];
			wp_mail($row->user_email,'Your password',$message);

		}else{
			Tr_Session_Class::add_error($rs->get_error_message());
			$url = Tr_Base_Class_V6::link(array('act'=>'','id'=>''),false);
			wp_safe_redirect($url);
		}
		//$wpdb->delete($wpdb->signups,array('signup_id'=>$id));
	}else{
		Tr_Session_Class::add_error("User Not found");
		$url = Tr_Base_Class_V6::link(array('act'=>'','id'=>''),false);
		wp_safe_redirect($url);
	}

}
else if($act=='pdelete'){
	$id = $_REQUEST['idd'];
	$wpdb->delete($wpdb->signups,array('signup_id'=>$id));
	Tr_Session_Class::add('Deleted User #'.$id);

}

$total = $wpdb->get_var("select count(*) from {$wpdb->signups} where active = 0");
$row_per_page = 50;
$pagination = new Tr_Pagination_class2($row_per_page);
$offset = $pagination->offset();
$pagination->setTotal($total);

$users = $wpdb->get_results(
	"select * from {$wpdb->signups} where active = 0
				order by registered desc
				 limit {$offset},{$row_per_page}
				 ");


include_once(TRCPDD_PATH.'pages/formdata.php');
?>
<div class="wrap">
	<h2>Pending Users</h2>
	<?php Tr_Session_Class::show()?>

	<div class="wraptbl">
		<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
			<thead>
			<tr>
				<th style="width:50px">#</th>
				<th>Username</th>
				<th>Email</th>
				<th>registered</th>
				<th>Role</th>
				<th>Actions</th>
			</tr>
			</thead>
			<tbody>
			<?php
			if(count($users)==0)
			{
				echo '<tr><td colspan=5>Not found</td></tr>';
			}
			foreach ($users as $i=> $user):
				$meta = maybe_unserialize($user->meta);
				?>
				<tr>
					<td><?php echo $user->signup_id ?></td>
					<td><?php echo $user->user_login ?></td>
					<td><?php echo $user->user_email ?></td>
					<td><?php echo $user->registered ?></td>
					<td><?php echo $meta['new_role'] ?></td>
					<td>
						<a href="<?php Tr_Base_Class_V6::link(array('act'=>'approve','id'=>$user->signup_id))?>">Approve</a> -
						<a class="pdelete" href="#" data-id="<?php echo $user->signup_id ?>">Delete</a>
					</td>
				</tr>
			<?php endforeach;?>
			</tbody>
		</table>
	</div>
</div>
