<?php


if ((isset($pa_options['enable_parental_agree']) && $pa_options['enable_parental_agree']) || isset($_GET['enable_parental_agree'])) {
	wp_enqueue_script('signature-pad', TRCPDD_URL . 'js/signature_pad.min.js', array(), TRCPDD_VS, false);
}
wp_enqueue_script('datedropdown', TRCPPR_URL . 'js/jquery.date-dropdowns.min.js', array(), TRCPPR_V);
wp_enqueue_script('parentfront', TRCPPR_URL . 'js/front_style3.js', array(), TRCPPR_V);
wp_enqueue_style('patientsfront', TRCPPR_URL . 'css/front_style3.css', array(), TRCPPR_V);



$step_count = 1;

$checkouturl = '';
if (function_exists('wc_get_checkout_url')) {
	$checkouturl = wc_get_checkout_url();
}

if ($disable_payment) {
	$checkouturl = '';
}

if (isset($_GET['debug'])) {
	var_dump($products_ids);
}


$steps = [
	['number' => 1, 'title' => 'Test Selection', 'icon' => 'computer'],
	['number' => 2, 'title' => 'Patient Info', 'icon' => 'user'],
	['number' => 3, 'title' => 'Agreements', 'icon' => 'document'],
	['number' => 4, 'title' => 'Payment', 'icon' => 'card'],
	['number' => 5, 'title' => 'Your Order', 'icon' => 'check']
];

$icons = [
	1 => '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>',
	2 => '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>',
	3 => '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 2a1 1 0 000 2h6a1 1 0 100-2H9z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4a2 2 0 012-2h12a2 2 0 012 2v16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 8h8M8 12h8M8 16h4" />
          </svg>',
	4 => '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
          </svg>',
	5 => '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>',
];


?>

<div class="oncosure-form-container" id="oncosure-form">
	<div class="oncosure-main-wrapper">


		<div class="oncosure-form-section">
			<div class="oncosure-header">
				<h1>Order Your Test Kit</h1>
				<p>Complete the steps below to order your cancer screening test</p>
			</div>

			<!-- Step Indicators -->
			<div class="oncosure-step-indicators">
				<div class="oncosure-steps-wrapper">
					<?php
					foreach ($steps as $steparr) {
					?>
						<div class="oncosure-step <?php if ($steparr['number'] == 1) echo 'active' ?>">
							<div class="oncosure-step-content">
								<div class="oncosure-step-number-row">
									<div class="oncosure-step-number"><?php echo $steparr['number'] ?></div>
									<div class="oncosure-step-icon"><?php echo $icons[$steparr['number']] ?></div>
								</div>
								<span class="oncosure-step-title"><?php echo $steparr['title'] ?></span>
							</div>
							<div class="oncosure-tooltip" id="tooltip-<?php echo $steparr['number'] ?>" style="display: none;"></div>
						</div>
					<?php } ?>
				</div>
			</div>

			<form method="post" name="checkout" action="<?php echo esc_url($checkouturl); ?>" enctype="multipart/form-data" class="need_validate <?php echo ($disable_payment) ? 'notpaymentform' : 'checkout woocommerce-checkout' ?>" id="requisition_form">
				<input type="hidden" name="patient_checkout" value="1" />
				<input type="hidden" name="custom_form" value="<?php echo $custom_form ?>" />
				<?php
				if (isset($atts['clinic_id']) && $atts['clinic_id'] > 0) {
					echo '<input type="hidden" name="cli_ID" value="' . $atts['clinic_id'] . '"/>';
				}
				if (isset($atts['doc_id']) && $atts['doc_id'] > 0) {
					echo '<input type="hidden" name="doc_ID" value="' . $atts['doc_id'] . '"/>';
				}
				if (isset($atts['specimen']) && !empty($atts['specimen'])) {
					echo '<input type="hidden" name="specimen" value="' . $atts['specimen'] . '"/>';
				}
				?>
				<div class="accordions <?php if (isset($form_options['show_patient_multi']) && $form_options['show_patient_multi']) echo 'patient_multi' ?>" data-locfirst="<?php echo $form_options['location_first'] ? 1 : 0 ?>" data-disablepayment="<?php echo $disable_payment ? 1 : 0 ?>">

					<?php
					$header_id = 0;

					if ($hide_location_tab) {
						$showed_locations = true;
					?>
						<input type="hidden" name="location" value="<?php echo $current_loc_key ?>" />
						<?php
					} else if (isset($form_options['location_first']) && $form_options['location_first']) {
						if ($location_input) {
						?>
							<div data-step="<?php echo $step_count++ ?>" class="accordion-section locationt location_input">
								<div class="accordion-section-content">
									<?php include('location_store_id.php'); ?>
									<div class="nextarea" style="margin-top:20px;text-align: right">

										<a class="gotostep button qbutton small" data-step="<?php echo $step_count ?>" href="#">Next</a>
									</div>
								</div>
							</div>
						<?php
						} else if (($form_options['enable_location_map'] && count($locations) > 0)) {
						?>
							<div data-step="<?php echo $step_count++ ?>" class="accordion-section locationt location_map">
								<div class="accordion-section-content">
									<?php include('location_map.php'); ?>
									<div class="nextarea" style="margin-top:20px;text-align: right">
										<?php if ($form_options['enable_skip_location']) {
										?>
											<a class="gotostep button qbutton small skip_location" data-step="<?php echo $step_count ?>" href="#">Skip - I will go to my own provider.</a>
										<?php
										}
										?>
										<a class="gotostep button qbutton small" data-step="<?php echo $step_count ?>" href="#">Next</a>
									</div>
								</div>
							</div>
						<?php
						} else if ($form_options['enable_location_fields']) {
						?>
							<div data-step="<?php echo $step_count++ ?>" class="accordion-section locationf">
								<div class="accordion-section-content">
									<?php include('location_fields.php'); ?>
									<div class="nextarea" style="margin-top:20px;text-align: right">
										<a class="gotostep button qbutton small" data-step="<?php echo $step_count ?>" href="#">Next</a>
									</div>
								</div>
							</div>
						<?php
						}
					}

					if (isset($form_options['enable_groups']) && $form_options['enable_groups']) {
						?>
						<div data-step="<?php echo $step_count++ ?>" class="accordion-section group_list">
							<div class="accordion-section-content">
								<?php include('group_list.php'); ?>
								<div class="nextarea" style="margin-top:20px;text-align: right">
									<a class="gotostep button qbutton small" data-step="<?php echo $step_count ?>" href="#">Next</a>
								</div>
							</div>
						</div>
					<?php
					}

					$select_tests_text = !empty($form_options['selectteststab'])  && empty($custom_form) ? $form_options['selectteststab'] : 'Select Tests';


					$second_tab_products = array();
					$second_tab_products_insurance = array();
					$second_tab_products_no_insurance = array();

					if (!isset($form_options['disable_select_tests']) || !$form_options['disable_select_tests']) {
					?>

						<div data-step="<?php echo $step_count++ ?>" class="accordion-section selecttests selecttests_main <?php if (count($adding_tabs) > 0) echo 'hasselectsecond' ?>" style="display: none;">
							<div class="accordion-section-content" id="test_types" data-allow_select_pay_ins="<?php echo $form_options['allow_select_pay_ins'] ? 1 : 0 ?>" data-only_one_per_group="<?php echo $form_options['only_one_per_group'] ? 1 : 0 ?>" style="text-align: center">
								<?php
								echo '<div class="text_above_tests"></div>';
								if (!empty($form_options['text_st'])) {
									echo '<div>' . stripslashes($form_options['text_st']) . '</div>';
								}
								if ($form_options['enable_priority']) {
								?>
									<table style="border:none;margin:0 auto">
										<td>
											<label>Priority: </label>
											<label>
												<input type="radio" name="bat[priority_hours]" value="12" /> 12
											</label>
											<label>
												<input type="radio" name="bat[priority_hours]" value="24" /> 24
											</label>
										</td>
									</table>
									<?php
								}

								$product_adding_html = '';
								if (
									isset($form_options['products_adding'])
									&& is_array($form_options['products_adding'])
									&& count($form_options['products_adding']) > 0
								) {
									ob_start();

									foreach ($form_options['products_adding'] as $pr) {
										$product = wc_get_product($pr['ID']);
										if (!$product) continue;
										if (!empty($pr['header'])) {
											if (
												in_array($current_loc_key_char, array('S', 'W'))
												&& stripos($pr['header'], 'Serology ') !== false
											) {
												continue;
											}
											echo '<tr class="headertr"><td><div class="headerproduct">' . $pr['header'] . '</div></td></tr>';
										}
									?>
										<tr>
											<td style="text-align: left">
												<label>
													<input type="checkbox" name="products_adding[]" value="<?php echo $pr['ID'] ?>" />
													<?php echo $product->get_name() ?> &nbsp;&nbsp;
													<?php echo $product->get_price() ?>
												</label>
											</td>
										</tr>

								<?php
									}

									$product_adding_html = ob_get_clean();
								}

								ob_start();
								?>
								<table style="border:none;margin:0 auto" class="btypetbl outinsurance">
									<?php
									if (
										isset($form_options['enable_discounttest']) && $form_options['enable_discounttest']
										&& is_array($form_options['discount'])
									) {

										foreach ($form_options['discount'] as $discount) {
											$amount = $discount['amount'];
											if ($amount > 0 && count($discount['products']) > 0 && !empty($discount['title'])) {
									?>
												<tr class="cbrow discountrow">
													<td style="text-align: left">
														<label>
															<input type="checkbox" class="groupcheckbox" data-ids="<?php echo implode(',', $discount['products']) ?>" data-group="pay" checked />
															<?php echo $discount['title'] ?>
															<?php if (!empty($discount['desc'])) {
																echo '<div class="descpp">' . $discount['desc'] . '</div>';
															}
															?>
														</label>

														<?php
														foreach ($products_ids as $pid => $arr) {
															if (in_array($pid, $discount['products'])) { ?>
																<label style="display:none !important;">
																	<input type="checkbox" name="tests[]" class="childgroupcb <?php echo str_replace('|', '_', $arr['key']) . '|discount' ?>" data-group="pay" data-locs="<?php echo $arr['locs'] ?>" data-timefrom="<?php echo $arr['timefrom'] ?>" data-timeto="<?php echo $arr['timeto'] ?>" data-escheduling="<?php echo $arr['escheduling'] ?>" data-radiogroup="<?php echo $arr['radiogroup'] ?>" data-type="<?php echo $arr['type'] ?>" data-company="<?php echo $arr['company'] ?>" value="<?php echo $arr['key'] ?>" checked />
																	<?php echo $arr['title'] ?>
																</label>
														<?php
															}
														}
														?>
														<script>
															jQuery(function($) {
																$('body').on('click change', 'input.groupcheckbox', function() {
																	var $pr = $(this).parents('tr.discountrow');
																	if ($(this).is(':checked')) {
																		$('input.childgroupcb', $pr).prop('checked', true);
																		$('tr.mainpay input').prop('checked', false);
																	} else {
																		$('input.childgroupcb', $pr).prop('checked', false);
																	}
																}).on('click', 'tr.mainpay input', function() {
																	$('input.groupcheckbox').prop('checked', false).change();
																})
															})
														</script>
													</td>
												</tr>
											<?php
											}
										}
									}
									foreach ($products_ids as $arr) {
										if (isset($arr['tabkey']) && !empty($arr['tabkey'])) {
											if (!isset($second_tab_products[$arr['tabkey']])) {
												$second_tab_products[$arr['tabkey']] = array();
											}
											$second_tab_products[$arr['tabkey']][] = $arr;
											continue;
										}
										if (!empty($arr['header'])) {
											if (
												in_array($current_loc_key_char, array('S', 'W'))
												&& stripos($arr['header'], 'Serology ') !== false
											) {
												continue;
											}

											$header_id++;
											?>
											<tr class="headertr" data-hid="<?php echo $header_id ?>">
												<td>
													<div class="headerproduct"><?php echo $arr['header'] ?></div>
												</td>
											</tr>
										<?php
										}
										?>
										<tr class="cbrow mainpay" data-hid="<?php echo $header_id ?>">
											<td style="text-align: left">
												<label>
													<?php if (!in_array($arr['status'], array('draft', 'pending'))) { ?>
														<input type="checkbox" name="tests[]" class="<?php echo str_replace('|', '_', $arr['key']) ?>" data-group="pay" data-locs="<?php echo $arr['locs'] ?>" data-timefrom="<?php echo $arr['timefrom'] ?>" data-timeto="<?php echo $arr['timeto'] ?>" data-escheduling="<?php echo $arr['escheduling'] ?>" data-radiogroup="<?php echo $arr['radiogroup'] ?>" data-type="<?php echo $arr['type'] ?>" data-company="<?php echo $arr['company'] ?>" value="<?php echo $arr['key'] ?>" required />
													<?php } ?>
													<?php echo $arr['title'] ?> &nbsp;&nbsp;
													<?php echo $arr['price'] ?>
													<?php if (in_array($arr['status'], array('draft', 'pending'))) {
														echo ' (Coming soon) ';
													} ?>
												</label>
												<br>
												<?php
												if (!empty($arr['tbelow'])) {
													echo '<div class="tbelow">' . $arr['tbelow'] . '</div>';
												}
												?>
												<span class="textlimit" style="display: none !important;"><?php echo $arr['textlimit'] ?></span>
											</td>
										</tr>
									<?php
									}
									?>
								</table>

								<?php
								$cash_table_html = ob_get_clean();
								$no_insu_table_html = '';
								if (isset($form_options['enable_no_insurance']) && $form_options['enable_no_insurance'] && count($products_no_insurance_ids) > 0) {

									ob_start();
								?>
									<table style="border:none;" class="btypetbl withnoinsurance" data-rssn="<?php echo $form_options['enable_no_insurance_ssn'] ? 1 : 0 ?>">
										<?php
										foreach ($products_no_insurance_ids as $arr) {

											if (isset($arr['tabkey']) && !empty($arr['tabkey'])) {
												if (!isset($second_tab_products_no_insurance[$arr['tabkey']])) {
													$second_tab_products_no_insurance[$arr['tabkey']] = array();
												}
												$second_tab_products_no_insurance[$arr['tabkey']][] = $arr;
												continue;
											}
											if (!empty($arr['header'])) {
												if (in_array($current_loc_key_char, array('W')) && !$arr['inloc']) {
													continue;
												}
												$header_id++;
										?>
												<tr class="headertr" data-hid="<?php echo $header_id ?>">
													<td>
														<div class="headerproduct"><?php echo $arr['header'] ?></div>
													</td>
												</tr>
											<?php
											}
											?>
											<tr class="cbrow" data-hid="<?php echo $header_id ?>">
												<td style="text-align: left">
													<label><input type="checkbox" name="tests[]" class="<?php echo str_replace('|', '_', $arr['key']) ?>" data-group="noinsu" data-locs="<?php echo $arr['locs'] ?>" data-timefrom="<?php echo $arr['timefrom'] ?>" data-timeto="<?php echo $arr['timeto'] ?>" data-escheduling="<?php echo $arr['escheduling'] ?>" data-radiogroup="<?php echo $arr['radiogroup'] ?>" data-type="<?php echo $arr['type'] ?>" data-company="<?php echo $arr['company'] ?>" value="<?php echo $arr['key'] ?>" required />
														<?php echo $arr['title'] ?><?php echo !empty($arr['price']) ? ' ' . $arr['price'] : '' ?>
													</label>
													<br>
													<?php
													if (!empty($arr['tbelow'])) {
														echo '<div class="tbelow">' . $arr['tbelow'] . '</div>';
													}
													?>
													<span class="textlimit" style="display: none !important;"><?php echo $arr['textlimit'] ?></span>
												</td>
											</tr>
										<?php
										}
										?>
									</table>
									<?php
									$no_insu_table_html = ob_get_clean();
									if (!empty($no_insu_table_html)) {
										$no_insu_table_html .= '<div>' . $form_options['noinsu_text'] . '</div>';
									}
								}

								if ($form_options['enable_insurance'] && count($products_insurance_ids) > 0) {

									ob_start();
									?>
									<table style="border:none;" class="btypetbl withinsurance" data-rssn="<?php echo $form_options['enable_insurance_ssn'] ? 1 : 0 ?>">
										<?php

										foreach ($products_insurance_ids as $arr) {
											if (isset($arr['tabkey']) && !empty($arr['tabkey'])) {
												if (!isset($second_tab_products_insurance[$arr['tabkey']])) {
													$second_tab_products_insurance[$arr['tabkey']] = array();
												}
												$second_tab_products_insurance[$arr['tabkey']][] = $arr;
												continue;
											}
											if (!empty($arr['header'])) {
												if (
													in_array($current_loc_key_char, array('S', 'W'))
													&& stripos($arr['header'], 'Serology ') !== false
												) {
													continue;
												}
												$header_id++;
										?>
												<tr class="headertr" data-hid="<?php echo $header_id ?>">
													<td>
														<div class="headerproduct"><?php echo $arr['header'] ?></div>
													</td>
												</tr>
											<?php
											}
											?>
											<tr class="cbrow" data-hid="<?php echo $header_id ?>">
												<td style="text-align: left">
													<label>
														<input type="checkbox" name="tests[]" class="<?php echo str_replace('|', '_', $arr['key']) ?>" data-group="insu" data-locs="<?php echo $arr['locs'] ?>" data-timefrom="<?php echo $arr['timefrom'] ?>" data-timeto="<?php echo $arr['timeto'] ?>" data-escheduling="<?php echo $arr['escheduling'] ?>" data-radiogroup="<?php echo $arr['radiogroup'] ?>" data-type="<?php echo $arr['type'] ?>" data-company="<?php echo $arr['company'] ?>" value="<?php echo $arr['key'] ?>" required />
														<?php echo $arr['title'] ?><?php echo !empty($arr['price']) ? ' ' . $arr['price'] : '' ?></label>
													<br>
													<?php
													if (!empty($arr['tbelow'])) {
														echo '<div class="tbelow">' . $arr['tbelow'] . '</div>';
													}
													?>
													<span class="textlimit" style="display: none !important;"><?php echo $arr['textlimit'] ?></span>
												</td>
											</tr>
										<?php
										}
										?>
									</table>
								<?php
									$insu_table_html = ob_get_clean();
									if (!isset($form_options['no_insurance_below']) || !$form_options['no_insurance_below']) {
										if ($form_options['invertinsunoinsu']) {
											$insu_table_html = $no_insu_table_html . $insu_table_html;
										} else {
											$insu_table_html .= $no_insu_table_html;
										}
									}


									echo '<table style="margin:0 auto;width:auto;border:none"><tr>';
									$tblcols = 1;
									if (count($products_ids) > 0) {
										echo '<td style="padding-right:20px;border-right:2px solid #ddd;width:50%">';
										echo !$form_options['invertinsucash'] ? $cash_table_html : $insu_table_html;
										echo '</td><td style="padding-left:20px;padding-right:0;">';
										if ($form_options['add_or_cc_ins']) {
											echo '<div class="onlymobile orccins" style="display:none">Or</div>';
										}
										echo $form_options['invertinsucash'] ? $cash_table_html : $insu_table_html;
										echo '</td>';
										$tblcols = 2;
									} else {
										echo '<td>';
										echo $insu_table_html;
										echo '</td>';
									}

									echo '</tr>';
									if (isset($form_options['no_insurance_below']) && $form_options['no_insurance_below']) {
										echo '<tr><td colspan="' . $tblcols . '" class="center">';
										echo $no_insu_table_html;
										echo '</td></tr>';
									}
									echo $product_adding_html;
									echo '</table>';
								} else {
									echo '<table style="margin:0 auto;width:auto;border:none"><tr>';
									if (isset($no_insu_table_html) && !empty($no_insu_table_html)) {
										echo '<td style="width:50%">';
										echo $no_insu_table_html;
										echo '</td>';
									}
									echo '<td>';
									echo $cash_table_html;
									echo '</td>';
									echo '<tr>';
									echo $product_adding_html;
									echo '</table>';
								}
								?>

								<div class="nextarea" style="margin-top:20px;text-align: right">
									<a class="gotostep button qbutton small nextbyproduct" data-step="<?php echo $step_count ?>" href="#">Next</a>
								</div>
							</div>
						</div>

						<?php
						if (count($adding_tabs) > 0) {

							foreach ($adding_tabs as $tabkey => $tabtext) {
								if (!isset($second_tab_products[$tabkey])) {
									continue;
								}
						?>
								<div data-step="<?php echo $step_count++ ?>" class="accordion-section selecttests selecttests_<?php echo $tabkey ?> selecttestssecond" style="display: none;">
									<div class="accordion-section-content" id="test_types" data-allow_select_pay_ins="<?php echo $form_options['allow_select_pay_ins'] ? 1 : 0 ?>" data-only_one_per_group="<?php echo $form_options['only_one_per_group'] ? 1 : 0 ?>" style="text-align: center">
										<?php
										ob_start();
										?>
										<table style="border:none;margin:0 auto" class="btypetbl outinsurance">
											<?php

											foreach ($second_tab_products[$tabkey] as $arr) {
												if (!empty($arr['header'])) {
													if (
														in_array($current_loc_key_char, array('S', 'W'))
														&& stripos($arr['header'], 'Serology ') !== false
													) {
														continue;
													}

													$header_id++;
											?>
													<tr class="headertr" data-hid="<?php echo $header_id ?>">
														<td>
															<div class="headerproduct"><?php echo $arr['header'] ?></div>
														</td>
													</tr>
												<?php
												}
												?>
												<tr class="cbrow mainpay" data-hid="<?php echo $header_id ?>">
													<td style="text-align: left">
														<label>
															<?php if (!in_array($arr['status'], array('draft', 'pending'))) { ?>
																<input type="checkbox" name="tests[]" class="<?php echo str_replace('|', '_', $arr['key']) ?>" data-group="pay" data-locs="<?php echo $arr['locs'] ?>" data-timefrom="<?php echo $arr['timefrom'] ?>" data-timeto="<?php echo $arr['timeto'] ?>" data-escheduling="<?php echo $arr['escheduling'] ?>" data-radiogroup="<?php echo $arr['radiogroup'] ?>" data-type="<?php echo $arr['type'] ?>" data-company="<?php echo $arr['company'] ?>" value="<?php echo $arr['key'] ?>" required />
															<?php } ?>
															<?php echo $arr['title'] ?> &nbsp;&nbsp;
															<?php echo $arr['price'] ?>
															<?php if (in_array($arr['status'], array('draft', 'pending'))) {
																echo ' (Coming soon) ';
															} ?>
														</label>
														<br>
														<?php
														if (!empty($arr['tbelow'])) {
															echo '<div class="tbelow">' . $arr['tbelow'] . '</div>';
														}
														?>
														<span class="textlimit" style="display: none !important;"><?php echo $arr['textlimit'] ?></span>
													</td>
												</tr>
											<?php
											}
											?>
										</table>

										<?php
										$cash_table_html = ob_get_clean();
										$no_insu_table_html = '';
										if (
											isset($form_options['enable_no_insurance']) && $form_options['enable_no_insurance']
											&& isset($second_tab_products_no_insurance[$tabkey]) && count($second_tab_products_no_insurance[$tabkey]) > 0
										) {

											ob_start();
										?>
											<table style="border:none;" class="btypetbl withnoinsurance" data-rssn="<?php echo $form_options['enable_no_insurance_ssn'] ? 1 : 0 ?>">
												<?php
												foreach ($second_tab_products_no_insurance[$tabkey] as $arr) {

													if (!empty($arr['header'])) {
														if (in_array($current_loc_key_char, array('W')) && !$arr['inloc']) {
															continue;
														}
														$header_id++;
												?>
														<tr class="headertr" data-hid="<?php echo $header_id ?>">
															<td>
																<div class="headerproduct"><?php echo $arr['header'] ?></div>
															</td>
														</tr>
													<?php
													}
													?>
													<tr class="cbrow" data-hid="<?php echo $header_id ?>">
														<td style="text-align: left">
															<label><input type="checkbox" name="tests[]" class="<?php echo str_replace('|', '_', $arr['key']) ?>" data-group="noinsu" data-locs="<?php echo $arr['locs'] ?>" data-timefrom="<?php echo $arr['timefrom'] ?>" data-timeto="<?php echo $arr['timeto'] ?>" data-escheduling="<?php echo $arr['escheduling'] ?>" data-radiogroup="<?php echo $arr['radiogroup'] ?>" data-type="<?php echo $arr['type'] ?>" data-company="<?php echo $arr['company'] ?>" value="<?php echo $arr['key'] ?>" required />
																<?php echo $arr['title'] ?><?php echo !empty($arr['price']) ? ' ' . $arr['price'] : '' ?>
															</label>
															<br>
															<?php
															if (!empty($arr['tbelow'])) {
																echo '<div class="tbelow">' . $arr['tbelow'] . '</div>';
															}
															?>
															<span class="textlimit" style="display: none !important;"><?php echo $arr['textlimit'] ?></span>
														</td>
													</tr>
												<?php
												}
												?>
											</table>
											<?php
											$no_insu_table_html = ob_get_clean();
											if (!empty($no_insu_table_html)) {
												$no_insu_table_html .= '<div>' . $form_options['noinsu_text'] . '</div>';
											}
										}

										if ($form_options['enable_insurance'] && isset($second_tab_products_insurance[$tabkey]) && count($second_tab_products_insurance[$tabkey]) > 0) {

											ob_start();
											?>
											<table style="border:none;" class="btypetbl withinsurance" data-rssn="<?php echo $form_options['enable_insurance_ssn'] ? 1 : 0 ?>">
												<?php

												foreach ($second_tab_products_insurance[$tabkey] as $arr) {

													if (!empty($arr['header'])) {
														if (
															in_array($current_loc_key_char, array('S', 'W'))
															&& stripos($arr['header'], 'Serology ') !== false
														) {
															continue;
														}
														$header_id++;
												?>
														<tr class="headertr" data-hid="<?php echo $header_id ?>">
															<td>
																<div class="headerproduct"><?php echo $arr['header'] ?></div>
															</td>
														</tr>
													<?php
													}
													?>
													<tr class="cbrow" data-hid="<?php echo $header_id ?>">
														<td style="text-align: left">
															<label>
																<input type="checkbox" name="tests[]" class="<?php echo str_replace('|', '_', $arr['key']) ?>" data-group="insu" data-locs="<?php echo $arr['locs'] ?>" data-timefrom="<?php echo $arr['timefrom'] ?>" data-timeto="<?php echo $arr['timeto'] ?>" data-escheduling="<?php echo $arr['escheduling'] ?>" data-radiogroup="<?php echo $arr['radiogroup'] ?>" data-type="<?php echo $arr['type'] ?>" data-company="<?php echo $arr['company'] ?>" value="<?php echo $arr['key'] ?>" required />
																<?php echo $arr['title'] ?><?php echo !empty($arr['price']) ? ' ' . $arr['price'] : '' ?></label>
															<br>
															<?php
															if (!empty($arr['tbelow'])) {
																echo '<div class="tbelow">' . $arr['tbelow'] . '</div>';
															}
															?>
															<span class="textlimit" style="display: none !important;"><?php echo $arr['textlimit'] ?></span>
														</td>
													</tr>
												<?php
												}
												?>
											</table>
										<?php
											$insu_table_html = ob_get_clean();
											if (!isset($form_options['no_insurance_below']) || !$form_options['no_insurance_below']) {
												if ($form_options['invertinsunoinsu']) {
													$insu_table_html = $no_insu_table_html . $insu_table_html;
												} else {
													$insu_table_html .= $no_insu_table_html;
												}
											}


											echo '<table style="margin:0 auto;width:auto;border:none"><tr>';
											$tblcols = 1;
											if (count($second_tab_products[$tabkey]) > 0) {
												echo '<td style="padding-right:20px;border-right:2px solid #ddd;width:50%">';
												echo !$form_options['invertinsucash'] ? $cash_table_html : $insu_table_html;
												echo '</td><td style="padding-left:20px;padding-right:0;">';
												if ($form_options['add_or_cc_ins']) {
													echo '<div class="onlymobile orccins" style="display:none">Or</div>';
												}
												echo $form_options['invertinsucash'] ? $cash_table_html : $insu_table_html;
												echo '</td>';
												$tblcols = 2;
											} else {
												echo '<td>';
												echo $insu_table_html;
												echo '</td>';
											}

											echo '</tr>';
											if (isset($form_options['no_insurance_below']) && $form_options['no_insurance_below']) {
												echo '<tr><td colspan="' . $tblcols . '" class="center">';
												echo $no_insu_table_html;
												echo '</td></tr>';
											}
											echo '</table>';
										} else {
											echo '<table style="margin:0 auto;width:auto;border:none"><tr>';
											if (isset($no_insu_table_html) && !empty($no_insu_table_html)) {
												echo '<td style="width:50%">';
												echo $no_insu_table_html;
												echo '</td>';
											}
											echo '<td>';
											echo $cash_table_html;
											echo '</td>';
											echo '<tr>';
											echo '</table>';
										}
										?>
										<div class="nextarea" style="margin-top:20px;text-align: right">
											<a class="gotostep button qbutton small nextbyproduct" data-step="<?php echo $step_count ?>" href="#">Next</a>
										</div>
									</div>
								</div>
						<?php
							}
						} //end adding tabs

					} else {
						echo '<div style="display: none;" class="btypetbl">';
						foreach ($products_ids as $arr) {
							echo '<input type="checkbox" name="tests[]" class="' . str_replace('|', '_', $arr['key']) . '" data-group="pay" data-type="' . $arr['type'] . '" data-company="true" value="' . $arr['key'] . '" checked />';
						}
						echo '</div>';
					}

					if ((isset($form_options['en_symptoms']) && $form_options['en_symptoms']) || isset($_GET['test_syumptoms'])) {
						?>
						<div data-step="<?php echo $step_count++ ?>" class="accordion-section symptoms" data-addingall="<?php echo $form_options['symptoms_allc'] ?>" style="display: none;">

							<div class="accordion-section-content">
								<div id="symptomslist">
									<?php
									foreach ($form_options['symptomslist'] as $symptomsitem) {
									?>
										<div class="row">
											<label>
												<input type="checkbox" class="dagnostic_codes_cb" name="bat[dagnostic_codes][]" value="<?php echo $symptomsitem['code'] ?>" />
												<?php echo $symptomsitem['title'] ?>
											</label>
										</div>
									<?php
									}
									?>
								</div>
								<div class="nextarea" style="margin-top:20px;text-align: right">
									<a class="gotostep button qbutton small nextbysymptoms" data-step="<?php echo $step_count ?>" href="#">Next</a>
								</div>
							</div>
						</div>
						<?php
					}

					if (!$form_options['location_first'] && !$hide_location_tab && !$form_options['location_afpa']) {

						if ($location_input) {
						?>
							<div data-step="<?php echo $step_count++ ?>" class="accordion-section locationt location_input">

								<div class="accordion-section-content">
									<?php include('location_store_id.php'); ?>
									<div class="nextarea" style="margin-top:20px;text-align: right">
										<a class="gotostep button qbutton small" data-step="<?php echo $step_count ?>" href="#">Next</a>
									</div>
								</div>
							</div>
						<?php
						} else if (($form_options['enable_location_map'] && count($locations) > 0) || isset($_GET['enable_location_map'])) {
						?>
							<div data-step="<?php echo $step_count++ ?>" class="accordion-section locationt location_map" style="display: none;">

								<div class="accordion-section-content">
									<?php include('location_map.php'); ?>
									<div class="nextarea" style="margin-top:20px;text-align: right">
										<?php if ($form_options['enable_skip_location']) {
										?>
											<a class="gotostep button qbutton small skip_location" data-step="<?php echo $step_count ?>" href="#">Skip - I will go to my own provider.</a>
										<?php
										}
										?>
										<a class="gotostep button qbutton small" data-step="<?php echo $step_count ?>" href="#">Next</a>
									</div>
								</div>
							</div>
						<?php
						} else if ($form_options['enable_location_fields']) {
						?>
							<div data-step="<?php echo $step_count++ ?>" class="accordion-section locationf" style="display: none;">

								<div class="accordion-section-content">
									<?php include('location_fields.php'); ?>
									<div class="nextarea" style="margin-top:20px;text-align: right">
										<a class="gotostep button qbutton small" data-step="<?php echo $step_count ?>" href="#">Next</a>
									</div>
								</div>
							</div>
						<?php
						}
					}


					if ($form_options['enable_scheduling']) {
						// FullCalendar v6: single global bundle; CSS injected by the JS (no separate stylesheet).
						wp_enqueue_script('fooevents-calendar-full-callendar', TRCPPR_URL . 'fullcalendar/index.global.min.js', array(), '6.1.21');

						if (count($adding_scheduling) == 0 || (!isset($form_options['enable_groups']) && !isset($form_options['enable_sctest']))) {
						?>
							<div data-step="<?php echo $step_count++ ?>" data-key="" class="accordion-section scheduling" style="display: none;">

								<div class="accordion-section-content">
									<?php include('scheduling_form.php'); ?>
									<div class="textlimitshow"></div>
									<div class="nextarea" style="margin-top:20px;text-align: right">
										<a class="gotostep button qbutton small" data-step="<?php echo $step_count ?>" href="#">Next</a>
									</div>
								</div>
							</div>
							<?php
						}
						if (((isset($form_options['enable_groups']) && $form_options['enable_groups'])
								|| (isset($form_options['enable_sctest']) && $form_options['enable_sctest']))
							&& count($adding_scheduling) > 0
						) {
							foreach ($adding_scheduling as $sckey => $tkeys) {
								$limitdays = 0;
								$adding_productid = 0;
								$adding_product_days = 0;
								foreach ($form_options['limitscheduling'] as $lmarr) {
									if ($sckey == $lmarr['key']) {
										$limitdays = $lmarr['lm1'];
										if (strpos($limitdays, '-') > 0) {
											list($notmp, $limitdays) = explode('-', $limitdays);
										}
										$limitdays = trim($limitdays);
										if (isset($lmarr['prad']) && $lmarr['prad'] > 0) {
											$adding_productid = $lmarr['prad'];
											$adding_product_days = $lmarr['pradlm'];
										}
										break;
									}
								}
							?>
								<div data-step="<?php echo $step_count++ ?>" data-key="<?php echo $sckey ?>" data-limitd="<?php echo $limitdays ?>" data-adding_product_days="<?php echo $adding_product_days ?>" class="accordion-section adding_scheduling scheduling_<?php echo $sckey ?> disabled" style="display: none;">

									<div class="accordion-section-content">
										<div class="before_calendardiv"></div>
										<div class="frow">
											<div id="time_div">
												<div class="col2">
													<div class="calendardiv" id='calendar_<?php echo $sckey ?>'></div>
												</div>
												<div class="col2">
													<div class="schedulingsdiv" id="schedulings_<?php echo $sckey ?>"></div>
												</div>
											</div>
										</div>

										<?php
										if ($adding_productid > 0) {
											$productadm = wc_get_product($adding_productid);
											if ($productadm) {
										?>
												<div class="addingproductlm" style="color:red;display:none">
													<?php echo $productadm->get_name() ?>
													<span style="display:none !important;"><input type="checkbox" name="products_adding[]" value="<?php echo $adding_productid ?>" /></span>
												</div>
										<?php
											}
										}
										?>

										<div class="textlimitshow"></div>

										<input type="hidden" class="docinput" name="bat[adoc][<?php echo $sckey ?>]" value="" />
										<input type="hidden" class="adockeys" name="bat[adockeys][<?php echo $sckey ?>]" value="<?php echo implode('*', $tkeys) ?>" />

										<div class="nextarea" style="margin-top:20px;text-align: right">
											<a class="gotostep button qbutton small submit_adoc" data-step="<?php echo $step_count ?>" href="#">Next</a>
										</div>
									</div>
								</div>

						<?php
							}
						}
					} else if (isset($form_options['enable_doc_picker']) && $form_options['enable_doc_picker']) {
						wp_enqueue_script('jquery-datepicker');
						wp_enqueue_style('jquery-ui-datepicker', TRCPPR_URL . 'css/jquery-ui-datepicker.css', array(), '1.8');
						?>
						<div data-step="<?php echo $step_count++ ?>" class="accordion-section docdatepicker " style="display: none;">

							<div class="accordion-section-content">
								<div style="text-align:center">
									<label>Date</label>
									<input type="text" class="docinput hasdate" name="bat[doc]" value="" required />
								</div>
								<div class="nextarea" style="margin-top:20px;text-align: right">
									<a class="gotostep button qbutton small submit_adoc" data-step="<?php echo $step_count ?>" href="#">Next</a>
								</div>
							</div>
						</div>
						<script>
							jQuery(function($) {
								$('.hasdate').datepicker({
									dateFormat: 'yy-mm-dd',
									minDate: new Date('<?php echo date_i18n('Y/m/d') ?>')
								});
							})
						</script>

					<?php
					}



					?>

					<div data-step="<?php echo $step_count++ ?>" class="accordion-section patient_section" style="display: none;">
						<div class="accordion-section-title">Patient Information</div>
						<div class="accordion-section-content">
							<?php include('patient_form.php') ?>
							<div id="multi_patient"></div>
							<div class="nextarea" style="margin-top:20px;text-align: right">
								<?php if (isset($form_options['show_patient_multi']) && $form_options['show_patient_multi']) {
								?>
									<a style="float:left" class=" button qbutton small add_family_member" href="#">Add Family Member</a>
								<?php
								} ?>

								<?php if ($disable_payment && !$pa_options['enable_parental_agree']) { ?>
									<input type="submit" class="button qbutton small" value="Submit" />
								<?php } else { ?>
									<a class="gotostep button qbutton small" data-step="<?php echo $step_count ?>" href="#">Next</a>
								<?php } ?>
							</div>
						</div>
					</div>




					<?php

					if ($pa_options['enable_parental_agree'] || isset($_GET['enable_parental_agree'])) {
						$agree_title = !empty($pa_options['agree_title']) ? $pa_options['agree_title'] : 'Parental AGREEMENTS';
					?>
						<div data-step="<?php echo $step_count++ ?>" class="accordion-section parental_agree_section" style="display: none;">
							<div class="accordion-section-content">
								<?php

								echo '<div class="parental_agree">';
								echo $pa_options['parental_agree'];
								echo '</div>';

								?>
								<div class="frow">
									<label><input type="checkbox" required /> I agree</label>
								</div>

								<?php if (!$pa_options['parental_agree_hidesi']) { ?>
									<div class="frow blabel">
										<div class="col4">
											<label>Last Name</label>
											<input type="text" class="cfname" name="parent[parent_last]" value="<?php echo @$patient['parent_last'] ?>" required />
										</div>
										<div class="col4">
											<label>First Name</label>
											<input type="text" class="cfname" name="parent[parent_first]" value="<?php echo @$patient['parent_first'] ?>" required />
										</div>
										<div class="col2">
											<label>Sign Below Please</label>
											<span id="signature_wrap">
												<canvas style="background:#ffffc3;border:1px solid #ddd" id="signature_canvas" width="400" height="173"></canvas>
												<a class="button clear_btn">Clear</a>
												<input type="hidden" class="signature" name="signature" />
											</span>
										</div>
									</div>
								<?php } ?>

								<div class="nextarea" style="margin-top:20px;text-align: right">
									<?php if ($disable_payment) { ?>
										<input type="submit" class="button qbutton small" value="Submit" />
									<?php } else { ?>
										<a class="gotostep button qbutton small" data-step="<?php echo $step_count ?>" href="#">Next</a>
									<?php } ?>
								</div>
							</div>
						</div>
					<?php
					}
					?>


					<?php if (!$disable_payment) { ?>
						<div data-step="<?php echo $step_count++ ?>" class="accordion-section checkout" style="display: none;">
							<div class="accordion-section-title">Payment </div>
							<div class="accordion-section-content">
								<?php
								if (!empty($form_options['text_paym'])) {
									echo '<div>' . stripslashes($form_options['text_paym']) . '</div>';
								}
								?>
								<div id="checkout_section"></div>
							</div>
						</div>
					<?php } ?>

					<div data-step="10" class="accordion-section" style="display: none;">
						<div class="accordion-section-title">Order Detail </div>
						<div class="accordion-section-content">
							<div id="order_section"></div>
						</div>
					</div>

				</div>

			</form>


		</div>


		<!-- Order Summary Sidebar -->
		<div class="oncosure-sidebar">
			<div class="oncosure-order-summary">
				<div class="oncosure-summary-header">
					<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
					</svg>
					<h3>Order Summary</h3>
				</div>
				<div class="oncosure-summary-content"></div>
			</div>
		</div>
	</div>
</div>


<?php

if (is_admin()) {
	$wc_url = plugins_url('/', WC_PLUGIN_FILE);
	wp_enqueue_script('wc-checkout', $wc_url . 'assets/js/frontend/checkout.min.js', array('jquery', 'woocommerce', 'wc-country-select', 'wc-address-i18n'));
	do_action('wp_enqueue_scripts');
	do_action('wp_print_scripts');
	do_action('wp_print_footer_scripts');
}
