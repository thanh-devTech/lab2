<?php
global $wpdb, $ci_all_btypes;
$trajax		= isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : '';

if ($trajax == 'approve') {
	$id = $_REQUEST['id'];
	$row = $wpdb->get_row("select * from {$wpdb->signups} where signup_id = {$id} ");
	if ($row) {
		$rs = wpmu_activate_signup($row->activation_key);

		if (!is_wp_error($rs)) {
			$message = "Password: " . $rs['password'];
			wp_mail($row->user_email, 'Your password', $message);
			wp_send_json_success("New user Password: " . $rs['password']);
		} else {
			$url = Tr_Base_Class_V6::link(array('act' => '', 'id' => ''), false);
			//wp_safe_redirect($url);
			wp_send_json_error(['data'=>$rs->get_error_message(),'url'=>$url]);
		}
		//$wpdb->delete($wpdb->signups,array('signup_id'=>$id));
	} else {
		Tr_Session_Class::add_error("User Not found");
		$url = Tr_Base_Class_V6::link(array('act' => '', 'id' => ''), false);
		//wp_safe_redirect($url);
		wp_send_json_error(['data'=>'Delete Fail!','url'=>$url]);
	}
}

if ($trajax == 'reject') {
	$id = $_REQUEST['idd'];
	$res_del = $wpdb->delete($wpdb->signups, array('signup_id' => $id));
	if ($res_del)
		wp_send_json_success('Deleted User #' . $id . ' success!');
	else
		wp_send_json_error('Delete Fail!');
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
				 "
);

include_once(TRCPDD_PATH . 'pages/formdata.php');
?>
<style>
    .selected {
        border-radius: calc(var(--radius) - 2px) !important;
        background-color: oklch(0.98 0 0) !important;
        border: none;
    }
    .swal2-container{z-index: 9999;}
	.pagination-links a {
		border-radius: 5px !important;
		background-color: white !important;
		border: solid 1px #e0e0e0 !important;
		color:#314158 !important;
	}

	.pagination-links a:hover{
		background-color: #ffc9c9 !important;
	}

	.pagination-links a.disabled{
	background-color: #f9fafb !important;
	color:#d1d5dc !important;
	}
	.swal2-container {
		z-index: 9999;
	}
</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<div class="wrap">
	<div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
		<div class="space-y-6">
			<div class="flex items-center justify-between">
				<div>
					<h1 class="text-2xl font-semibold">Pending Users</h1>
					<p class="text-muted-foreground mt-1">Review and approve user registration requests</p>
				</div>
			</div>
			<?php
			if(empty($users)){
			?>
			<div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-6 bg-gray-50 border border-gray-200 rounded-lg flex items-center justify-center">
                <span class="text-gray-400 font-medium">The list is empty.</span>
            </div>
			<?php }else{ ?>
			<div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border overflow-hidden">
				<div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-0">

				<div class="flex items-center justify-end gap-4 px-6 py-3 border-b bg-white">
				<?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?>
				</div>

					<div class="overflow-x-auto">
						<table class="w-full">
							<thead class="bg-gray-50 border-b">
								<tr>
									<th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Username</th>
									<th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Name</th>
									<th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Email</th>
									<th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Requested Role</th>
									<th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Requested Date</th>
									<th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Actions</th>
								</tr>
							</thead>
							<tbody class="divide-y divide-gray-200">
								<?php
								foreach ($users as $i => $user) {
									$meta = maybe_unserialize($user->meta);
									$display_name = $meta['display_name'] ?? '';
									$first_name   = $meta['first_name'] ?? '';
									$last_name    = $meta['last_name'] ?? '';
								?>
									<tr class="hover:bg-gray-50">
										<td class="px-6 py-4">
											<span class="text-sm font-medium" style="color: rgb(216, 61, 0);"><?php echo $user->user_login; ?></span>
										</td>
										<td class="px-6 py-4 text-sm text-gray-700"><?php echo $display_name ?: trim("$first_name $last_name"); ?></td>
										<td class="px-6 py-4">
											<a href="mailto:<?php echo $user->user_email; ?>" class="text-sm hover:underline" style="color: rgb(216, 61, 0);"><?php echo $user->user_email; ?></a>
										</td>

										<td class="px-6 py-4">
											<span data-slot="badge" class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden text-foreground [a&amp;]:hover:bg-accent [a&amp;]:hover:text-accent-foreground">
												<?php echo $meta['new_role'] ?? ''; ?>
											</span>
										</td>

										<td class="px-6 py-4 text-sm text-gray-700">
											<?php echo $user->registered ?>
										</td>

										<td class="px-6 py-4">
											<div class="flex items-center gap-2">
												<button data-id="<?php echo $user->signup_id ?>" data-slot="button" class="btn_approve cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground hover:bg-primary/90 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5" style="background-color: rgb(216, 61, 0);">
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check h-3 w-3 mr-1" aria-hidden="true">
														<path d="M20 6 9 17l-5-5"></path>
													</svg>Approve
												</button>

												<button data-id="<?php echo $user->signup_id ?>" data-slot="button" class="btn_reject cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5">
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x h-3 w-3 mr-1" aria-hidden="true">
														<path d="M18 6 6 18"></path>
														<path d="m6 6 12 12"></path>
													</svg>Reject
												</button>
											</div>
										</td>
									</tr>
								<?php } ?>
							</tbody>
						</table>
					</div>

				</div>

				<div class="flex items-center justify-end gap-4 px-6 py-3 border-b bg-white">
				<?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?>
				</div>
			</div>
			<?php }?>
		</div>
	</div>
</div>

<script>
	var url_main_page = 'ci_user_pending';

	jQuery(document).ready(function($) {

		$(".btn_approve").click(function() {
			var id_u = $(this).data("id");
			Swal.fire({
				title: 'Approve...',
				allowOutsideClick: false,
				didOpen: () => Swal.showLoading()
			});

			jQuery.post("admin.php?page=" + url_main_page, {
				trajax: 'approve',
				id: id_u
			}, function(response) {
				Swal.close();

				if (response.success) {
					Swal.fire('Success', response.data, 'success').then(() => {
						if(response.url!=null) location.href=response.url;
						else location.reload();
					});
				} else {
					Swal.fire('Error', response.data, 'error').then(()=>{
						if(response.url!=null) location.href=response.url;
					});
				}
			});
		});

		$(".btn_reject").click(function() {
			var id_u = $(this).data("id");
			Swal.fire({
				title: 'Reject User',
				text: 'Are you sure you want to reject user?',
				icon: 'question',
				showCancelButton: true,
				confirmButtonText: 'Yes, reject',
			}).then((result) => {

				if (!result.isConfirmed) return;

				Swal.fire({
					title: 'Reject...',
					allowOutsideClick: false,
					didOpen: () => Swal.showLoading()
				});

				jQuery.post("admin.php?page=" + url_main_page, {
					trajax: 'reject',
					idd: id_u
				}, function(response) {
					Swal.close();

					if (response.success) {
						Swal.fire('Success', response.data, 'success')
							.then(() => location.reload());
					} else {
						Swal.fire('Error', response.data, 'error');
					}
				});

			});
		});
	});
</script>