<?php
global $wpdb;
global $wp_roles;
$roles = $wp_roles->roles;
if (!is_super_admin()) {
	unset($roles['data_export']);
}
$trajax     = isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : '';

if ($trajax == 'add_multi_user') {
	$send_mail = isset($_REQUEST['send_mail']) ? $_REQUEST['send_mail'] : '0';
	$role      = $_REQUEST['role'];
	$emails    = trim($_REQUEST['emails']);

	$out = trpdd_process_multi_user_add($emails, $role, $send_mail);

	if (isset($out['error'])) {
		wp_send_json_error($out['error']);
	}
	wp_send_json_success($out['results']);
	exit;
}

add_filter('editable_roles', function ($roles) {
	unset($roles['clinic']);
	unset($roles['lab_rep']);
	unset($roles['lab_rep_w2']);
	unset($roles['collector']);
	unset($roles['office_manager']);
	unset($roles['multi_site_manager']);
	unset($roles['doctor']);
	unset($roles['patient']);
	return $roles;
});
?>
<style>
	input[type=email],
	input[type=button],
	input[type=email],
	input[type=search],
	input[type=date],
	input[type=submit],
	input[type=tel],
	input[type=text],
	input[type=number],
	input[type=color],
	input[type=password],
	select,
	textarea,
	.selected {
		border-radius: calc(var(--radius) - 2px) !important;
		background-color: oklch(0.98 0 0) !important;
		border: none;
	}

	.user-result-item {
		display: flex;
		align-items: center;
		justify-content: space-between;
		border-left: 3px solid #22c55e;
		padding: 6px 10px;
		margin-bottom: 6px;
		text-align: left;
		background: #f8f9fa;
		border-radius: 4px;
	}

	.user-result-item .info {
		font-family: monospace;
		font-size: 13px;
	}

	.user-result-item .copy-btn {
		cursor: pointer;
		opacity: .6;
		margin-left: 12px;
		flex-shrink: 0;
	}

	.user-result-item .copy-btn:hover {
		opacity: 1;
	}

	.user-result-item.error .info {
		color: #dc2626;
	}

	.swal2-container {
		z-index: 9999;
	}
</style>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?v=' . TRCPDD_VS ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="wrap">
	<div class="flex-1 p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
		<div class="space-y-6 max-w-3xl">
			<div>
				<h1 class="text-2xl font-semibold">Add New Multiple Users</h1>
				<p class="text-muted-foreground mt-1">Add multiple users at once by entering their email addresses.</p>
			</div>

			<form id="frm_profile" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border">
				<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6 space-y-6">
					<div class="space-y-2">
						<label data-slot="label" class="flex items-center gap-2 text-sm leading-none text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 font-semibold" for="userEmails">
							User Email Addresses
							<span class="text-muted-foreground font-normal">(required)</span>
						</label>

						<p class="text-sm text-muted-foreground">Enter one email address per line. These users will be added to the portal.</p>
						<textarea
							name="emails"
							data-slot="textarea"
							class="resize-none border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 flex min-h-16 w-full rounded-md border bg-input-background px-3 py-2 transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm font-mono text-sm"
							id="userEmails"
							placeholder="user1@example.com
user2@example.com
user3@example.com" rows="8"></textarea>
					</div>
					<div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
						<label data-slot="label" class="flex items-center gap-2 text-sm leading-none text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-left md:text-right font-semibold" for="role">Role</label>
						<div class="md:col-span-2">
							<select name="role" id="role">
								<?php foreach ($roles as $key => $r) {
								?>
									<option value="<?php echo $key; ?>"><?php echo $r['name']; ?></option>
								<?php
								}
								?>
							</select>
						</div>
					</div>
					<div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center"><label data-slot="label" class="flex items-center gap-2 text-sm leading-none text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 text-left md:text-right font-semibold">Send Confirmation Email</label>
						<div class="md:col-span-2">
							<div class="flex items-center gap-2">
								<button id="send_email" type="button" role="checkbox" aria-checked="false" data-state="unchecked" value="0" data-slot="checkbox" class="cursor-pointer peer bg-input-background dark:bg-input/30 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground dark:data-[state=checked]:bg-primary data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50" id="sendEmail">
									<span data-state="checked" data-slot="checkbox-indicator" class="hidden items-center justify-center text-current transition-none" style="pointer-events: none;">
										<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-check size-3.5">
											<path d="M20 6 9 17l-5-5"></path>
										</svg>
									</span>
								</button>
								<label data-slot="label" class="cursor-pointer flex items-center gap-2 text-sm leading-none text-gray-700 select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 font-normal cursor-pointer" for="sendEmail">Send Confirmation Email</label>
							</div>
						</div>
					</div>
				</div>
			</form>
			<div>
				<button id="btn_add_multi_user" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-primary text-primary-foreground hover:bg-primary/90 h-9 py-2 has-[&gt;svg]:px-3 px-8" style="background-color: rgb(216, 61, 0);">Add New Users</button>
			</div>
		</div>
	</div>
</div>

<script>
	var url_main_page = 'ci_user_add_multi';

	function updateCheckboxUI($el) {
		const isChecked = $el.attr("data-state") === "checked";

		$el.attr({
			"aria-checked": isChecked ? "true" : "false",
			"value": isChecked ? "1" : "0"
		});

		$el.find("span").toggleClass("flex", isChecked).toggleClass("hidden", !isChecked);
	}

	jQuery(document).ready(function($) {

		initCustomSelect("select");

		$(document).on('click', 'button[role="checkbox"]', function() {
			const $btn = $(this);
			const name = $btn.attr("name");
			const newState = $btn.attr("data-state") === "unchecked" ? "checked" : "unchecked";

			$btn.attr("data-state", newState);
			updateCheckboxUI($btn);
		});

		function buildUserResultsHtml(results) {
			var html = '<div class="user-results-wrap">';
			results.forEach(function(r) {
				var text;
				if (r.status === 'success') {
					var name = r.first_name ? (r.first_name + ' ' + r.last_name + ' ') : '';
					var roleText = r.role_name ? (' - ' + r.role_name) : '';
					text = name + r.email + roleText + (r.password ? ' | password: ' + r.password : '');
				} else {
					text = r.message;
				}
				var errClass = (r.status === 'error') ? ' error' : '';
				var safeText = $('<div>').text(text).html();

				html += '<div class="user-result-item' + errClass + '">' +
					'<span class="info">' + safeText + '</span>' +
					'<span class="copy-btn" data-copy="' + safeText.replace(/"/g, '&quot;') + '" title="Copy">' +
					'<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
					'<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>' +
					'</span>' +
					'</div>';
			});
			html += '</div>';
			return html;
		}

		function copyToClipboard(text, el) {
			function done() {
				var old = el.getAttribute('title');
				el.setAttribute('title', 'Copied!');
				setTimeout(function() {
					el.setAttribute('title', old || 'Copy');
				}, 1500);
			}

			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(text).then(done).catch(function() {
					fallbackCopy(text, done);
				});
			} else {
				fallbackCopy(text, done);
			}
		}

		function fallbackCopy(text, cb) {
			var ta = document.createElement('textarea');
			ta.value = text;
			ta.style.position = 'fixed';
			ta.style.opacity = '0';
			document.body.appendChild(ta);
			ta.focus();
			ta.select();
			try {
				document.execCommand('copy');
				cb && cb();
			} catch (e) {
				console.error('Copy failed', e);
			}
			document.body.removeChild(ta);
		}

		function buildAllResultsText(results) {
			var lines = [];
			results.forEach(function(r) {
				if (r.status === 'success') {
					var name = r.first_name ? (r.first_name + ' ' + r.last_name + ' ') : '';
					var roleText = r.role_name ? (' - ' + r.role_name) : '';
					lines.push(name + r.email + roleText + (r.password ? ' | password: ' + r.password : ''));
				}
			});
			return lines.join('\n');
		}

		$("#btn_add_multi_user").click(function() {
			var frm = document.getElementById('frm_profile');
			var formData = new FormData(frm);
			var send_email_val = $("#send_email").attr("value");
			formData.append('trajax', 'add_multi_user');
			formData.append('send_email', send_email_val);

			jQuery.ajax({
				url: "admin.php?page=" + url_main_page,
				type: "POST",
				data: formData,
				processData: false,
				contentType: false,
				success: function(response) {
					Swal.close();
					if (response.success) {
						Swal.fire({
							title: 'Success',
							html: buildUserResultsHtml(response.data),
							icon: 'success',
							showDenyButton: true,
							denyButtonText: 'Copy All',
							denyButtonColor: '#6366f1',
							reverseButtons: false,
							preDeny: function() {
								var allText = buildAllResultsText(response.data);
								copyToClipboard(allText, Swal.getDenyButton());
								return false;
							},
							didOpen: function() {
								document.querySelectorAll('.copy-btn').forEach(function(btn) {
									btn.addEventListener('click', function() {
										var text = this.getAttribute('data-copy');
										copyToClipboard(text, this);
									});
								});
							}
						});
					} else {
						Swal.fire({
							title: 'Error',
							html: response.data,
							icon: 'error'
						});
					}
				},
				error: function(err) {
					Swal.close();
					Swal.fire({
						title: 'Error',
						text: 'Network or server error.',
						icon: 'error'
					});
				}
			});
		});
	});
</script>