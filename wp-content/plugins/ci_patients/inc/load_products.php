<?php

$products_ids           = array();
$products_insurance_ids = array();
$products_no_insurance_ids = array();
$adding_scheduling      = array();

try {
	//test add to cart all
	//WC()->cart->empty_cart();

	if (is_array($form_options['products']) && count($form_options['products']) > 0) {
		foreach ($form_options['products'] as $arr) {
			if ($onlypcr && stripos($arr['type'], 'pcr') === false) {
				continue;
			}
			$product = wc_get_product($arr['ID']);
			if ($product) {
				$originalPrice = $product->get_regular_price();
				$salePrice = $product->get_sale_price();
				if (empty($salePrice)) {
					$salePrice = $originalPrice;
				}
				$price_txt = $product->get_price_html();
				if ($product->get_price() == 0) {
					$price_txt = '';
				} else if ($hidepaid) {
					continue;
				}

				if (!isset($arr['locs']) || !is_array($arr['locs'])) {
					$arr['locs'] = array();
				}

				$pkey = $arr['type'] . '|' . $arr['ID'] . '|pay';

				$description = $product->get_description();
				if (empty($description)) {
					$description = nl2br($product->get_short_description());
				}

				$products_ids[$arr['ID']] = array(
					'key' => $pkey,
					'type' => $arr['type'],
					'locs' => implode(',', $arr['locs']),
					'header' => trim($arr['header']),
					'description' => $description,
					'tbelow' => isset($arr['tbelow']) ? trim($arr['tbelow']) : '',
					'company' => isset($arr['company']) ? trim($arr['company']) : '',
					'timefrom' => isset($arr['timefrom']) ? trim($arr['timefrom']) : '',
					'timeto' => isset($arr['timeto']) ? trim($arr['timeto']) : '',
					'textlimit' => isset($arr['textlimit']) ? trim($arr['textlimit']) : '',
					'escheduling' => isset($arr['escheduling']) ? trim($arr['escheduling']) : '',
					'radiogroup' => isset($arr['radiogroup']) ? trim($arr['radiogroup']) : '',
					'duration' => isset($arr['duration']) ? trim($arr['duration']) : '',
					'badge' => isset($arr['badge']) ? trim($arr['badge']) : '',
					'tabkey' => isset($arr['tabkey']) ? trim($arr['tabkey']) : '',
					'title' => $product->get_name(),
					'originalPrice' => $originalPrice,
					'salePrice' => $salePrice,
					'price' => $price_txt,
					'status' => $product->get_status()
				);

				if (isset($arr['company']) && $arr['company']) {
					$has_company_section = true;
				}

				if (!empty($only_use_sckey)) {
					if (!isset($arr['sckey']) || empty($arr['sckey']) || $only_use_sckey != $arr['sckey']) {
						unset($products_ids[$arr['ID']]);
						continue;
					}
				} else {
					if (isset($arr['sckey']) && !empty($arr['sckey'])) {
						unset($products_ids[$arr['ID']]);
						continue;
					}
				}


				if (!empty($only_pr_key) && $only_pr_key != $pkey) {
					unset($products_ids[$arr['ID']]);
					continue;
				}

				if (isset($only_ptypes) && count($only_ptypes) > 0 && !in_array($pkey, $only_ptypes)) {
					unset($products_ids[$arr['ID']]);
					continue;
				} else if (is_array($arr['locs']) && count($arr['locs']) > 0) {
					$in_list_locs = false;
					if (!empty($current_loc_key) && in_array($current_loc_key, $arr['locs'])) {
						$in_list_locs = true;
					} else {
						foreach ($locations as $location) {
							if (in_array($location['key'], $arr['locs'])) {
								$in_list_locs = true;
							}
						}

						if (!$in_list_locs) {
							foreach ($arr['locs'] as $l1) {
								if (stripos($l1, 'loc_') === 0) {
									$clid = str_ireplace('loc_', '', $l1);
									if (in_array($clid, $locations_types)) {
										$in_list_locs = true;
										break;
									}
								}
							}
						}
					}


					if (!$in_list_locs) {
						unset($products_ids[$arr['ID']]);
						continue;
					}
				}

				if (isset($pre_add_products) && $pre_add_products && function_exists('WC') && WC()->cart->is_empty()) {
					$rs = WC()->cart->add_to_cart($arr['ID']);
					if (!$rs) {
						wc_print_notices();
						echo 'Error while setup page. Please try again later!';
						return;
					}
				}

				if (isset($arr['escheduling']) && $arr['escheduling'] > 0) {
					if (!is_array($adding_scheduling[$arr['escheduling']])) {
						$adding_scheduling[$arr['escheduling']] = array();
					}
					$adding_scheduling[$arr['escheduling']][] = $pkey;
				}
			}
		}
	}

	if ($form_options['enable_insurance'] && count($form_options['products_insurance']) > 0) {
		foreach ($form_options['products_insurance'] as $arr) {
			if ($onlypcr && stripos($arr['type'], 'pcr') === false) {
				continue;
			}
			$product = wc_get_product($arr['ID']);
			if ($product) {
				$originalPrice = $product->get_regular_price();
				$salePrice = $product->get_sale_price();
				if (empty($salePrice)) {
					$salePrice = $originalPrice;
				}

				$price_txt = $product->get_price_html();
				if ($product->get_price() == 0) {
					$price_txt = '';
				} else if ($hidepaid) {
					continue;
				} else if (isset($_REQUEST['location'])) {
					continue;
				}
				if (!isset($arr['locs']) || !is_array($arr['locs'])) {
					$arr['locs'] = array();
				}

				$pkey = $arr['type'] . '|' . $arr['ID'] . '|ins';

				$products_insurance_ids[$arr['ID']] = array(
					'key' => $pkey,
					'type' => $arr['type'],
					'locs' => implode(',', $arr['locs']),
					'header' => trim($arr['header']),
					'tbelow' => isset($arr['tbelow']) ? trim($arr['tbelow']) : '',
					'company' => isset($arr['company']) ? trim($arr['company']) : '',
					'timefrom' => isset($arr['timefrom']) ? trim($arr['timefrom']) : '',
					'timeto' => isset($arr['timeto']) ? trim($arr['timeto']) : '',
					'textlimit' => isset($arr['textlimit']) ? trim($arr['textlimit']) : '',
					'escheduling' => isset($arr['escheduling']) ? trim($arr['escheduling']) : '',
					'radiogroup' => isset($arr['radiogroup']) ? trim($arr['radiogroup']) : '',
					'tabkey' => isset($arr['tabkey']) ? trim($arr['tabkey']) : '',
					'duration' => isset($arr['duration']) ? trim($arr['duration']) : '',
					'badge' => isset($arr['badge']) ? trim($arr['badge']) : '',
					'title' => $product->get_name(),
					'originalPrice' => $originalPrice,
					'salePrice' => $salePrice,
					'price' => $price_txt,
					'status' => $product->get_status()
				);

				if (isset($arr['company']) && $arr['company']) {
					$has_company_section = true;
				}


				if (!empty($only_use_sckey)) {
					if (!isset($arr['sckey']) || empty($arr['sckey']) || $only_use_sckey != $arr['sckey']) {
						unset($products_insurance_ids[$arr['ID']]);
						continue;
					}
				} else {
					if (isset($arr['sckey']) && !empty($arr['sckey'])) {
						unset($products_insurance_ids[$arr['ID']]);
						continue;
					}
				}

				if (!empty($only_pr_key) && $only_pr_key != $pkey) {
					if (isset($_GET['debug'])) {
						echo 'continue insu for: $only_pr_key<br>';
						var_dump($products_insurance_ids[$arr['ID']]);
						echo '<br><br>';
					}
					unset($products_insurance_ids[$arr['ID']]);
					continue;
				}

				if (isset($only_ptypes) && count($only_ptypes) > 0 && !in_array($pkey, $only_ptypes)) {
					unset($products_insurance_ids[$arr['ID']]);
					continue;
				} else if (is_array($arr['locs']) && count($arr['locs']) > 0) {
					$in_list_locs = false;
					if (!empty($current_loc_key) && in_array($current_loc_key, $arr['locs'])) {
						$in_list_locs = true;
					} else {
						foreach ($locations as $location) {
							if (in_array($location['key'], $arr['locs'])) {
								$in_list_locs = true;
							}
						}

						if (!$in_list_locs) {
							foreach ($arr['locs'] as $l1) {
								if (stripos($l1, 'loc_') === 0) {
									$clid = str_ireplace('loc_', '', $l1);

									if (in_array($clid, $locations_types)) {
										$in_list_locs = true;
										break;
									}
								}
							}
						}
					}

					if (!$in_list_locs) {
						unset($products_insurance_ids[$arr['ID']]);
						continue;
					}
				}

				if (isset($pre_add_products) && $pre_add_products && WC()->cart->is_empty()) {
					$rs = WC()->cart->add_to_cart($arr['ID']);
					if (!$rs) {
						wc_print_notices();
						echo 'Error while setup page. Please try again later!';
						return;
					}
				}

				if (isset($arr['escheduling']) && $arr['escheduling'] > 0) {
					if (!is_array($adding_scheduling[$arr['escheduling']])) {
						$adding_scheduling[$arr['escheduling']] = array();
					}
					$adding_scheduling[$arr['escheduling']][] = $pkey;
				}
			}
		}
	}



	if (isset($form_options['enable_no_insurance']) && $form_options['enable_no_insurance'] && count($form_options['products_noinsurance']) > 0) {
		foreach ($form_options['products_noinsurance'] as $arr) {
			if ($onlypcr && stripos($arr['type'], 'pcr') === false) {
				continue;
			}
			$product = wc_get_product($arr['ID']);
			if ($product) {

				$originalPrice = $product->get_regular_price();
				$salePrice = $product->get_sale_price();
				if (empty($salePrice)) {
					$salePrice = $originalPrice;
				}

				$price_txt = $product->get_price_html();
				if ($product->get_price() == 0) {
					$price_txt = '';
				} else if ($hidepaid) {
					continue;
				}
				if (!isset($arr['locs']) || !is_array($arr['locs'])) {
					$arr['locs'] = array();
				}

				$pkey = $arr['type'] . '|' . $arr['ID'] . '|noins';

				$products_no_insurance_ids[$arr['ID']] = array(
					'key' => $pkey,
					'type' => $arr['type'],
					'locs' => implode(',', $arr['locs']),
					'header' => trim($arr['header']),
					'tbelow' => isset($arr['tbelow']) ? trim($arr['tbelow']) : '',
					'company' => isset($arr['company']) ? trim($arr['company']) : '',
					'timefrom' => isset($arr['timefrom']) ? trim($arr['timefrom']) : '',
					'timeto' => isset($arr['timeto']) ? trim($arr['timeto']) : '',
					'textlimit' => isset($arr['textlimit']) ? trim($arr['textlimit']) : '',
					'escheduling' => isset($arr['escheduling']) ? trim($arr['escheduling']) : '',
					'radiogroup' => isset($arr['radiogroup']) ? trim($arr['radiogroup']) : '',
					'tabkey' => isset($arr['tabkey']) ? trim($arr['tabkey']) : '',
					'duration' => isset($arr['duration']) ? trim($arr['duration']) : '',
					'badge' => isset($arr['badge']) ? trim($arr['badge']) : '',
					'title' => $product->get_name(),
					'originalPrice' => $originalPrice,
					'salePrice' => $salePrice,
					'price' => $price_txt,
					'status' => $product->get_status(),
					'inloc'  => false,
				);

				if (isset($arr['company']) && $arr['company']) {
					$has_company_section = true;
				}

				if (!empty($only_use_sckey)) {
					if (!isset($arr['sckey']) || empty($arr['sckey']) || $only_use_sckey != $arr['sckey']) {
						unset($products_no_insurance_ids[$arr['ID']]);
						continue;
					}
				} else {
					if (isset($arr['sckey']) && !empty($arr['sckey'])) {
						unset($products_no_insurance_ids[$arr['ID']]);
						continue;
					}
				}

				if (!empty($only_pr_key) && $only_pr_key != $pkey) {
					if (isset($_GET['debug'])) {
						echo 'continue noinsu for: only_pr_key<br>';
						var_dump($only_pr_key);
						echo '<br>';
						var_dump($pkey);
						echo '<br><br>';
					}
					unset($products_no_insurance_ids[$arr['ID']]);
					continue;
				}

				if (count($only_ptypes) > 0 && !in_array($pkey, $only_ptypes)) {
					unset($products_no_insurance_ids[$arr['ID']]);
					continue;
				} else if (
					!empty($current_loc_key)
					&& is_array($arr['locs'])
					&& count($arr['locs']) > 0
				) {
					$in_list_locs = false;
					if (in_array($current_loc_key, $arr['locs'])) {
						$in_list_locs = true;
					} else {
						foreach ($arr['locs'] as $l1) {
							if (stripos($l1, 'loc_') === 0) {
								$clid = str_ireplace('loc_', '', $l1);
								if ($current_loc_key_char === strtoupper($clid)) {
									$in_list_locs = true;
								}
								break;
							}
						}
					}

					if (!$in_list_locs) {

						unset($products_no_insurance_ids[$arr['ID']]);
						continue;
					} else {
						$products_no_insurance_ids[$arr['ID']]['inloc'] = true;
					}
				} else if (count($only_ptypes) > 0 && in_array($pkey, $only_ptypes)) {
					$products_no_insurance_ids[$arr['ID']]['inloc'] = true;
				}

				if (isset($pre_add_products) && $pre_add_products && WC()->cart->is_empty()) {
					$rs = WC()->cart->add_to_cart($arr['ID']);
					if (!$rs) {
						wc_print_notices();
						echo 'Error while setup page. Please try again later!';
						return;
					}
				}
			}
		}

		if (isset($_GET['debug'])) {
			echo '<br><br>';
			echo 'noinsu $products_no_insurance_ids: ';
			var_dump($products_no_insurance_ids);
			echo '<br><br>';
		}
	}
} catch (Error $e) {
	var_dump($e);
}
