<?php
global $wpdb, $locations, $locations_types, $showed_locations;
$_SESSION['upload_insurance_photos'] = array();
$_SESSION['current_order_id'] = false;

if (isset($_GET['post']) && $_GET['post'] > 0) {
	return;
}
if (isset($_GET['wc-ajax']) || defined('DOING_AJAX')) {
	return;
}

$user = wp_get_current_user();
if (is_user_logged_in() && $user->roles[0] != 'administrator') {
	wp_logout();
}

$has_company_section    = false;
$disable_payment        = false;
$location_input         = false;
$onlypcr                = false;
$hidepaid               = false;
$custom_form            = '';


$only_use_sckey = isset($atts['key']) && !empty($atts['key']) ? $atts['key'] : '';
$only_pr_key = isset($_REQUEST['pr']) && !empty($_REQUEST['pr']) ? $_REQUEST['pr'] : '';

if (!empty($only_pr_key)) {
	$only_pr_key = str_replace('_', '|', $only_pr_key);
	$only_pr_key = str_replace('-G|', '-G_', $only_pr_key);
}

if (isset($atts)) {
	if ((isset($atts['meijer']) && $atts['meijer'])
		|| (isset($atts['form']) && $atts['form'] == 'meijer')
	) {
		$custom_form = 'meijer';
	} else if ((isset($atts['canada']) && $atts['canada'])
		|| (isset($atts['form']) && $atts['form'] == 'canada')
	) {
		$custom_form = 'canada';
	} else if ((isset($atts['concierge']) && $atts['concierge'])
		|| (isset($atts['form']) && $atts['form'] == 'concierge')
	) {
		$custom_form = 'concierge';
	} else if (isset($atts) && isset($atts['form']) && !empty($atts['form'])) {
		$custom_form = 'cform_' . $atts['form'];
	}
}

$form_key   = 'trpatient_product_settings';
if (isset($custom_form) && !empty($custom_form)) {
	$form_key = 'trpatient_' . $custom_form . '_settings';
}
$form_options   = get_option($form_key, array());
$pa_options     = get_option('trpatient_settings', array());


if (isset($atts)) {
	if (isset($atts['onlypaid']) && $atts['onlypaid']) {
		$form_options['enable_insurance'] = false;
	}

	if (isset($atts['hidepaid']) && $atts['hidepaid']) {
		$form_options['products'] = false;
		$hidepaid = true;
	}

	if (isset($atts['onlypcr']) && $atts['onlypcr']) {
		$onlypcr = true;
	}

	if (isset($atts['location']) && $atts['location'] == 'input') {
		$location_input = true;
	}

	if (isset($atts['appointment']) && !$atts['appointment']) {
		$form_options['enable_scheduling'] = false;
	}
}

if ($custom_form == 'meijer') {
	$form_options['enable_scheduling'] = false;
	$disable_payment = true;
} else if (isset($form_options['disable_payment']) && $form_options['disable_payment']) {
	$disable_payment = true;
}


if (isset($_REQUEST['loc_first'])) {
	$form_options['location_first'] = true;
} else if (isset($_REQUEST['pro_first'])) {
	$form_options['location_first'] = false;
}
if (isset($form_options['location_afpa']) && $form_options['location_afpa']) {
	$form_options['location_first'] = false;
}


$form_options['invertinsucash'] = isset($form_options['invertinsucash']) && $form_options['invertinsucash'];
$form_options['enable_priority'] = isset($form_options['enable_priority']) && $form_options['enable_priority'];
$locations          = array();
$locations_types    = array();
$showed_locations   = false;
$current_loc_key    = '';
$current_loc_key_char = '';
$current_loc_id     = '';
$hide_location_tab  = false;
$only_ptypes        = array();
$adding_tabs 		= array();

if (isset($form_options['test_tabs']) && count($form_options['test_tabs']) > 0) {
	foreach ($form_options['test_tabs'] as $tabarr) {
		$adding_tabs[strtolower($tabarr['value'])] = $tabarr['title'];
	}
}

if ($form_options['enable_location']) {
	$locations = is_array($form_options['locations']) ? $form_options['locations'] : array();

	if ($form_options['enable_s_location']) {
		foreach ($locations as $k => $location) {

			$location['key'] = trim($location['key']);
			if (isset($_REQUEST['location'])) {
				$use_this_loc = false;
				if (strtolower($_REQUEST['location']) == strtolower($location['url'])) {
					$current_loc_id = $k;
					$current_loc_key = $location['key'];
					$current_loc_key_char = strtoupper(substr($current_loc_key, 0, 1));
					$use_this_loc = true;
					if ($location['hidemap']) {
						$hide_location_tab = true;
					}
					$locations_types[] = $current_loc_key_char;
				} else if (stripos($location['key'], 'h') === 0 && !$form_options['only_show_one_in_loc']) { //not H
					$use_this_loc = true;
				}
				if (!$use_this_loc) {
					unset($locations[$k]);
				} else if ((isset($form_options['enable_pbyloc']) && $form_options['enable_pbyloc'])
					&& isset($location['pids']) && is_array($location['pids'])
				) {
					$only_ptypes = array_merge($only_ptypes, $location['pids']);
				}
			} else if (stripos($location['key'], 's') === 0) { //is S
				unset($locations[$k]);
			} else if (stripos($location['key'], 'w') === 0) { //is W
				unset($locations[$k]);
			} else if ($location['hidemain']) {
				unset($locations[$k]);
			} else {
				$locations_types[] = strtoupper(substr($location['key'], 0, 1));
			}
		}

		if (!empty($current_loc_id)) {
			$location = $locations[$current_loc_id];
			unset($locations[$current_loc_id]);
			array_unshift($locations, $location);
		}
	}
}

if (isset($_GET['debug'])) {
	echo '$only_ptypes: ';
	var_dump($only_ptypes);
	echo '<br><br>';
	echo '$locations_types: ';
	var_dump($locations_types);
	echo '<br><br>';
}
if (!isset($_REQUEST['location']) && !$pa_options['parental_agree_hidesi']) {
	$pa_options['enable_parental_agree'] = false;
}

$user_id    = get_current_user_id();
$patient_id = tr_patient_get_id_patient($user_id);

if (is_object($patient_id)) {
	$patient_id = $patient_id->pa_ID;
	$wpdb->update($wpdb->users, array('patient_id' => $patient_id), array('ID' => $user_id));
}
$patient = array();
$ins1 	= array();

if ($patient_id > 0) {

	$patient = $wpdb->get_row("select * from ci_patients where pa_ID = '{$patient_id}' ", ARRAY_A);

	$ins1 = $wpdb->get_row("select * from ci_insurances where pa_ID = '{$patient_id}' 
                        and ins_first !='' and ins_first is not null
                        order by ins_ID desc limit 1", ARRAY_A);
}

if (!$patient) {
	$patient = isset($_SESSION['restration_covid_testing']['pa']) ? $_SESSION['restration_covid_testing']['pa'] : array();
}

if (!$ins1) {
	$ins1 = isset($_SESSION['restration_covid_testing']['ins1']) ? $_SESSION['restration_covid_testing']['ins1'] : array();
}

$pre_add_products = true;
include(TRCPPR_PATH . '/inc/load_products.php');

if (isset($_GET['formstyle']) && $_GET['formstyle'] == 3) {
	include(TRCPPR_PATH . 'pages/registration_tests_style3.php');
	return;
} else if (isset($pa_options['formstyle']) && $pa_options['formstyle'] == 2) {
	include(TRCPPR_PATH . 'pages/registration_tests_style2.php');
	return;
}


if ((isset($pa_options['enable_parental_agree']) && $pa_options['enable_parental_agree']) || isset($_GET['enable_parental_agree'])) {
	wp_enqueue_script('signature-pad', TRCPDD_URL . 'js/signature_pad.min.js', array(), TRCPDD_VS, false);
}
wp_enqueue_script('datedropdown', TRCPPR_URL . 'js/jquery.date-dropdowns.min.js', array(), TRCPPR_V);
wp_enqueue_script('parentfront', TRCPPR_URL . 'js/front.min.js', array(), TRCPPR_V);
wp_enqueue_style('patientsfront', TRCPPR_URL . 'css/front.css', array(), TRCPPR_V);



$step_count = 1;

global $ultraadmin;
if (!empty($form_options['acc_bg_color']) || !empty($form_options['acc_bg_hover_color']) || !empty($ultraadmin['accordion-bg'])) {

	if (empty($form_options['acc_bg_color'])) {
		$form_options['acc_bg_color'] = isset($ultraadmin['accordion-bg']) ? $ultraadmin['accordion-bg'] : '';
	}
	if (empty($form_options['acc_text_color'])) {
		$form_options['acc_text_color'] = isset($ultraadmin['accordion-text']) ? $ultraadmin['accordion-text'] : '';
	}
	if (empty($form_options['acc_content_color'])) {
		$form_options['acc_content_color'] = isset($ultraadmin['accordion-content']) ? $ultraadmin['accordion-content'] : '';
	}
	if (empty($form_options['acc_bg_hover_color'])) {
		$form_options['acc_bg_hover_color'] = isset($form_options['acc_bg_color']) ? $form_options['acc_bg_color'] : '';
	}
	if (empty($form_options['acc_text_hover_color'])) {
		$form_options['acc_text_hover_color'] = isset($form_options['acc_text_color']) ? $form_options['acc_text_color'] : '';
	}
?>
	<style>
		body .accordions .accordion-section-title,
		body .accordions .active .accordion-section-title,
		body .accordions .button:hover,
		body .accordions .button.samebar,
		body .accordions .pass .accordion-section-title:hover {
			background: <?php echo $form_options['acc_bg_color']; ?>;
			color: <?php echo $form_options['acc_text_color']; ?>;
			border-color: <?php echo $form_options['acc_bg_color']; ?>;
		}

		body .accordion-section-content {
			color: <?php echo $form_options['acc_content_color']; ?>;
		}

		body .accordions .pass .accordion-section-title:hover,
		body .accordions .active .accordion-section-title,
		body .accordions .button {
			background: <?php echo $form_options['acc_bg_hover_color']; ?>;
			color: <?php echo $form_options['acc_text_hover_color']; ?>;
			border-color: <?php echo $form_options['acc_bg_hover_color']; ?>;
		}

		<?php
		if (count($adding_tabs) > 0) {
			echo 'body .accordions .selecttests_main .accordion-section-title{background:' . $form_options['acc_bg_color_main'] . '}';
			foreach ($adding_tabs as $tkey => $ttext) {
				echo 'body .accordions .selecttests_' . $tkey . ' .accordion-section-title{background:' . $form_options['acc_bg_color_' . $tkey] . '}';
			}
		}
		if (isset($_GET['debug'])) {
			echo '.accordions .accordion-section-content{display:block !important}';
		}
		?>
	</style>
<?php
}

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
?>

<div class="wrap_form wrap">

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
						<div class="accordion-section-title">Location</div>
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
						<div class="accordion-section-title">Location</div>
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
						<div class="accordion-section-title">Testing Location</div>
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
					<div class="accordion-section-title">Group</div>
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

				<div data-step="<?php echo $step_count++ ?>" class="accordion-section selecttests selecttests_main <?php if (count($adding_tabs) > 0) echo 'hasselectsecond' ?>">
					<div class="accordion-section-title"><?php echo $select_tests_text ?> </div>
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
						<div data-step="<?php echo $step_count++ ?>" class="accordion-section selecttests selecttests_<?php echo $tabkey ?> selecttestssecond">
							<div class="accordion-section-title"><?php echo $tabtext ?> </div>
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
				<div data-step="<?php echo $step_count++ ?>" class="accordion-section symptoms" data-addingall="<?php echo $form_options['symptoms_allc'] ?>">
					<div class="accordion-section-title">Symptoms</div>
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
						<div class="accordion-section-title">Location</div>
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
					<div data-step="<?php echo $step_count++ ?>" class="accordion-section locationt location_map">
						<div class="accordion-section-title">Location</div>
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
						<div class="accordion-section-title">Testing Location</div>
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
					<div data-step="<?php echo $step_count++ ?>" data-key="" class="accordion-section scheduling">
						<div class="accordion-section-title">Appointment Time</div>
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
						<div data-step="<?php echo $step_count++ ?>" data-key="<?php echo $sckey ?>" data-limitd="<?php echo $limitdays ?>" data-adding_product_days="<?php echo $adding_product_days ?>" class="accordion-section adding_scheduling scheduling_<?php echo $sckey ?> disabled">
							<div class="accordion-section-title">Appointment Time #<span class="schenum"></span></div>
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
				<div data-step="<?php echo $step_count++ ?>" class="accordion-section docdatepicker ">
					<div class="accordion-section-title">Select Date</div>
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



			if ($form_options['show_medical_history']) {
			?>
				<div data-step="<?php echo $step_count++ ?>" class="accordion-section reason_for_testing">
					<div class="accordion-section-title">Medical History</div>
					<div class="accordion-section-content">
						<div class="frow">
							<label>Previously Diagnosed</label>
							<textarea name="batch_meta[prev_diagnosed]"></textarea>
						</div>
						<div class="frow">
							<label>Initial Screening</label>
							<textarea name="batch_meta[init_screening]"></textarea>
						</div>
						<div class="nextarea" style="margin-top:20px;text-align: right">
							<a class="gotostep button qbutton small" data-step="<?php echo $step_count ?>" href="#">Next</a>
						</div>
					</div>
				</div>
			<?php
			}

			if ($form_options['show_reason_testing']) {
				$reasons = array('Having Symptoms', 'Clearance for Travel', 'General Screening');
				if (isset($form_options['reason_testing']) && !empty($form_options['reason_testing'])) {
					$reasons = explode(',', $form_options['reason_testing']);
				}
			?>
				<div data-step="<?php echo $step_count++ ?>" class="accordion-section reason_for_testing">
					<div class="accordion-section-title">Reason for Testing</div>
					<div class="accordion-section-content">
						<div class="frow groupradios">
							<?php
							foreach ($reasons as $reason) {
								$reason = trim($reason);
								echo '<label><input type="radio" name="bat[p_condition]" value="' . $reason . '" /> ' . $reason . ' </label>';
							}
							?>
						</div>
						<div class="nextarea" style="margin-top:20px;text-align: right">
							<a class="gotostep button qbutton small" data-step="<?php echo $step_count ?>" href="#">Next</a>
						</div>
					</div>
				</div>
			<?php
			}

			?>

			<div data-step="<?php echo $step_count++ ?>" class="accordion-section patient_section">
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

			if (!$form_options['location_first'] && !$hide_location_tab && $form_options['location_afpa']) {

				if ($location_input) {
			?>
					<div data-step="<?php echo $step_count++ ?>" class="accordion-section locationt location_input">
						<div class="accordion-section-title">Location</div>
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
					<div data-step="<?php echo $step_count++ ?>" class="accordion-section locationt location_map locationbystate">
						<div class="accordion-section-title">Location</div>
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
						<div class="accordion-section-title">Testing Location</div>
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



			if (isset($form_options['enable_questions']) && !empty($form_options['enable_questions'])) {
				?>
				<div data-step="<?php echo $step_count++ ?>" class="accordion-section questions_section">
					<div class="accordion-section-title">Questions</div>
					<div class="accordion-section-content">
						<?php include('questions.php') ?>
						<div class="nextarea" style="margin-top:20px;text-align: right">
							<?php if ($disable_payment && !$pa_options['enable_parental_agree']) { ?>
								<input type="submit" class="button qbutton small" value="Submit" />
							<?php } else { ?>
								<a class="gotostep button qbutton small" data-step="<?php echo $step_count ?>" href="#">Next</a>
							<?php } ?>
						</div>
					</div>
				</div>
			<?php
			}
			if ($pa_options['enable_parental_agree'] || isset($_GET['enable_parental_agree'])) {
				$agree_title = !empty($pa_options['agree_title']) ? $pa_options['agree_title'] : 'Parental AGREEMENTS';
			?>
				<div data-step="<?php echo $step_count++ ?>" class="accordion-section parental_agree_section">
					<div class="accordion-section-title"><?php echo $agree_title ?></div>
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

			<?php
			if ($form_options['enable_insurance'] || isset($_GET['show_insurance'])) {
			?>
				<div data-step="<?php echo $step_count++ ?>" class="accordion-section insurancearia disabled">
					<div class="accordion-section-title">PRIMARY INSURANCE BILLING</div>
					<div class="accordion-section-content">
						<?php include('insurance_form.php'); ?>
						<div class="nextarea" style="margin-top:20px;text-align: right">
							<a class="gotostep button qbutton small" data-step="<?php echo $step_count ?>" href="#">Next</a>
						</div>
					</div>
				</div>
			<?php
			}
			?>

			<?php
			if (isset($form_options['group_passportimg']) && $form_options['group_passportimg']) {
			?>
				<div data-step="<?php echo $step_count++ ?>" class="accordion-section passportimg">
					<div class="accordion-section-title">Passport Pictures</div>
					<div class="accordion-section-content">
						<div class="<?php if ($form_options['insu_cbtsyellow']) echo 'bgyellow' ?>" style="margin-top:20px;">
							<div class="frow">
								<h5>INSERT Passport Pictures</h5>
							</div>

							<div class="frow blabel">

								<div class="col4">

									<div class="card_file" data-id="patient_id_front">
										<a class="button samebar" href="#">Passport Front Photo</a>
									</div>
								</div>
								<div class="col4">

									<div class="card_file" data-id="patient_id_back">
										<a class="button samebar" href="#"> Passport Back Photo</a>
									</div>
								</div>
							</div>
						</div>
						<div class="nextarea" style="margin-top:20px;text-align: right">
							<a class="gotostep button qbutton small" data-step="<?php echo $step_count ?>" href="#">Next</a>
						</div>
					</div>
				</div>
			<?php
			} else if ((isset($form_options['enable_no_insurance']) && $form_options['enable_no_insurance'] && $form_options['enable_no_ins_upid'])
				|| (isset($form_options['enable_pay_ins_photo']) && $form_options['enable_pay_ins_photo'])
			) {
			?>
				<div data-step="<?php echo $step_count++ ?>" class="accordion-section nonins_cardsid <?php echo ($form_options['enable_pay_ins_photo']) ? 'pay_cardsids' : 'disabled' ?>">
					<div class="accordion-section-title">ID Card Pictures</div>
					<div class="accordion-section-content">
						<div class="<?php if ($form_options['insu_cbtsyellow']) echo 'bgyellow' ?>" style="margin-top:20px;">
							<div class="frow">
								<h5>INSERT ID Card Pictures</h5>
							</div>

							<div class="frow blabel">

								<div class="col4">

									<div class="card_file" data-id="patient_id_front">
										<a class="button samebar" href="#">ID Card Front Photo</a>
									</div>
								</div>
								<div class="col4">

									<div class="card_file" data-id="patient_id_back">
										<a class="button samebar" href="#"> ID Card Back Photo</a>
									</div>
								</div>
							</div>
						</div>
						<div class="nextarea" style="margin-top:20px;text-align: right">
							<a class="gotostep button qbutton small" data-step="<?php echo $step_count ?>" href="#">Next</a>
						</div>
					</div>
				</div>
			<?php
			}
			?>

			<?php if (!$disable_payment) { ?>
				<div data-step="<?php echo $step_count++ ?>" class="accordion-section checkout">
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

			<div data-step="10" class="accordion-section">
				<div class="accordion-section-title">Order Detail </div>
				<div class="accordion-section-content">
					<div id="order_section"></div>
				</div>
			</div>

		</div>

	</form>


</div>

<?php

if (is_admin()) {
	$wc_url = plugins_url('/', WC_PLUGIN_FILE);
	wp_enqueue_script('wc-checkout', $wc_url . 'assets/js/frontend/checkout.min.js', array('jquery', 'woocommerce', 'wc-country-select', 'wc-address-i18n'));
	do_action('wp_enqueue_scripts');
	do_action('wp_print_scripts');
	do_action('wp_print_footer_scripts');
}
