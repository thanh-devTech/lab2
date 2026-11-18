<?php
global $wpdb;

$trajax = isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : '';

if ($trajax == 'search_report') {
	$search = trim($_REQUEST['term']);
	$sql = $wpdb->prepare("select assen, CONCAT(assen,' - ', patient_name)  as label
	 from ci_batches_pcr where assen = %s or patient_name like %s", $search, '%' . $search . '%');
	$rs = $wpdb->get_results($sql, ARRAY_A);
	wp_send_json($rs);
	exit;
} else if ($trajax == "send_review") {
	$assen = isset($_POST['assen']) ? stripslashes($_POST['assen']) : '';
	$comment = isset($_POST['comment']) ? stripslashes($_POST['comment']) : '';
	$addmailing = isset($_POST['addmailing']) ? $_POST['addmailing'] : 0;

	$results = [];

	if ($assen == '') {
		$results['status'] = 'error';
		$results['message'] = 'Select Report not selected';
		echo json_encode($results);
		exit;
	}

	if ($comment == '') {
		$results['status'] = 'error';
		$results['message'] = 'Comment cannot be blank!';
		echo json_encode($results);
		exit;
	}

	$user = wp_get_current_user();
	$rs = false;
	if (!empty($comment)) {
		$rs = tr_client_send_hub(array(
			'action' => 'review',
			'assen' => $assen,
			'comment' => $comment,
			'addmailing' => $addmailing,
			'email' => $user->user_email,
			'fullname' => $user->display_name,
		));
	}

	$results['status'] = 'ok';
	$results['message'] = 'Your request has been sent to the antimicrobial stewardship team.';
	echo json_encode($results);
	exit;
}

wp_enqueue_script('jquery-ui-autocomplete');
?>
<style>
	h1,
	h4 {
		margin: unset;
	}

	h1 {
		margin-bottom: 5px !important;
	}

	h4 {
		margin-bottom: 5px;
	}

	h5 {
		font-weight: var(--font-weight-medium);
		margin: 0px;
		--tw-font-weight: var(--font-weight-medium);
		font-size: 13px;
		margin-bottom: 10.5px;
	}

	h1,
	h2,
	h3,
	h4,
	h5,
	h6 {
		font-weight: unset !important;
		font-size: inherit;
	}

	h4 {
		font-size: 14.5px;
	}

	input[type=button],
	input[type=email],
	input[type=search],
	input[type=submit],
	input[type=tel],
	input[type=text],
	select,
	textarea,
	.selected {
		border-radius: calc(var(--radius) - 2px) !important;
		background-color: var(--color-yellow-50) !important;
		border: 1px solid #e2e2e2ff;
	}

	p {
		margin-bottom: 10px;
	}

	strong {
		color: var(--color-gray-700);
	}
</style>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?' . TRCPDD_VS ?>"></script>
<script>
	var $ajaxurl_search = '<?php echo Tr_Base_Class_V6::link(array('trajax' => 'search_report')) ?>';
	var $ajaxurl_send = '<?php echo Tr_Base_Class_V6::link(array('trajax' => 'send_review')) ?>';
	jQuery(function($) {
		$("#request_review").prop('checked', true);
		$('#report-select').autocomplete({
			source: $ajaxurl_search,
			minLength: 3,
			select: function(event, ui) {
				if (ui.item) {
					//$('[name="assen"]').val(ui.item.assen);
					$("#assend").val(ui.item.assen);
				}
			}
		});

		$("#btn_done_review").click(function() {
			var assend = $("#assend").val();
			$("#btn_done_review").data("disabled", true).addClass("opacity-50");
			$("#loading_form_review").show();
			jQuery.post("admin.php?page=abx_review", {
				trajax: 'send_review',
				assen: $("#assend").val(),
				comment: $("#comment").val(),
				addmailing: 0
			}, function(response) {
				var res = JSON.parse(response);
				$("#loading_form_review").hide();
				if (res.status == "ok") {
					showToast('success', 'ABX Review Request Submitted', 'Your request has been sent to the antimicrobial stewardship team.', 4000, 'top_center');
					$("#assend").val('');
					$("#comment").val('');
					$("#report-select").val('');
				} else {
					showToast('error', 'ABX Review Request', res.message, 4000, 'top_center');
				}
				$("#btn_done_review").data("disabled", false).removeClass("opacity-50");
			});
			return false;
		});
	})
</script>
<div class="wrap">
	<div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8">
		<div class="space-y-6">
			<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
				<div>
					<h1 class="text-xl sm:text-2xl font-semibold">Request ABX Review</h1>
					<p class="text-muted-foreground mt-1">Submit questions about igniteABX report interpretation.</p>
				</div>
			</div>
			<div>
				<div class="grid grid-cols-3 gap-6">
					<div class="col-span-2 space-y-6">
						<div data-slot="card"
							class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
							<div data-slot="card-header"
								class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
								<h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg
										xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
										fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
										stroke-linejoin="round" class="lucide lucide-file-text h-5 w-5 text-primary"
										aria-hidden="true">
										<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
										<path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
										<path d="M10 9H8"></path>
										<path d="M16 13H8"></path>
										<path d="M16 17H8"></path>
									</svg>Request ABX Review</h4>
							</div>
							<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
								<form class="space-y-6">
									<div class="space-y-2">
										<label data-slot="label"
											class="flex items-center gap-2 text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
											for="report-select">Select Report (Accession/Patient name):
										</label>

										<input data-slot="input"
											class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive bg-yellow-50 border-gray-300 focus:bg-yellow-50"
											id="report-select" placeholder="Enter accession number or patient name" value="" />
									</div>
									<div class="space-y-2">
										<label data-slot="label"
											class="flex items-center gap-2 text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50"
											for="comment">Ask a question/leave a comment
										</label>
										<textarea data-slot="textarea" name="comment"
											class="placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 flex field-sizing-content min-h-16 w-full rounded-md border px-3 py-2 text-base transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm bg-yellow-50 border-gray-300 focus:bg-yellow-50 resize-none"
											id="comment" placeholder="Enter your question or comment here..."
											rows="6"></textarea>
									</div>

									<div class="flex">
										<button id="btn_done_review" data-slot="button"
											class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive h-9 py-2 has-[&gt;svg]:px-3 bg-primary hover:bg-primary/90 text-white px-6"
											type="submit">Submit</button>
										<svg id="loading_form_review" style="display:none" xmlns="http://www.w3.org/2000/svg"
											width="64" height="18" viewBox="0 0 64 18" aria-hidden="true" role="img"
											style="/* display: none; */">
											<circle cx="9" cy="9" r="6" fill="#6B7280">
												<animate attributeName="opacity" values="0.2;1;0.2" dur="1s"
													repeatCount="indefinite" begin="0s"></animate>
											</circle>
											<circle cx="32" cy="9" r="6" fill="#6B7280">
												<animate attributeName="opacity" values="0.2;1;0.2" dur="1s"
													repeatCount="indefinite" begin="0.15s"></animate>
											</circle>
											<circle cx="55" cy="9" r="6" fill="#6B7280">
												<animate attributeName="opacity" values="0.2;1;0.2" dur="1s"
													repeatCount="indefinite" begin="0.3s"></animate>
											</circle>
										</svg>
									</div>
									<input type="hidden" id="assend" name="assend" />
								</form>
							</div>
						</div>

						<div class="pt-4 pb-2 text-xs text-muted-foreground border-t">
							<p><strong style="color:unset">Note:</strong> This review request is intended for healthcare professionals and authorized laboratory personnel only. Submissions are reviewed to ensure accuracy of igniteABX report interpretation. We do not provide medical advice. All clinical decisions remain the responsibility of the treating clinician. Patient information is protected under HIPAA regulations.</p>
						</div>

					</div>

					<div class="col-span-1">
						<div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
							<div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
								<h4 data-slot="card-title" class="leading-none">About ABX Review Requests</h4>
							</div>
							<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-3 text-sm text-gray-600">
								<p><strong>When to request a review:</strong> Use this form if you have questions about antimicrobial susceptibility results, notice unexpected resistance patterns, or need clarification on report interpretation.</p>
								<p><strong>Response time:</strong> We typically respond within 24–48 business hours.</p>
								<p><strong>Questions about lab results:</strong> Please contact the laboratory directly. This form is only for questions about igniteABX report interpretation and antibiotic selection logic.</p>
							</div>
						</div>
					</div>

				</div>

			</div>
		</div>
	</div>
</div>
