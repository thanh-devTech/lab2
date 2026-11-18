<?php
global $wpdb, $current_role, $is_ci_admin;

//check db version
$db_version = get_option('TRCPDD_VS');
if ($db_version != TRCPDD_VS) {
	trpdd_plugin_activate();
}

$disable_rename     = false;
$can_delete         = false;
$user               = wp_get_current_user();

if (in_array($user->user_login, array('Administrat0r', 'cornel', 'trinh'))) {
	$can_delete = true;
}



include_once(trpdd_get_file_path('panel', 'actions'));

include_once(TRCPDD_PATH . 'pages/formdata.php');

$btypes     = tr_pdd_get_batch_types();

$tabs = array();
if (in_array('req', $btypes)) {
	$btypes[2] = 'blood';
	$btypes[16] = 'pcr';
}
foreach ($btypes as $k => $btype) {
	$tabs[$btype] = tr_pdd_get_typename($btype);
}

if (in_array('tox', $btypes)) {
	$tabs['dru'] = 'Drugs';
	$tabs['urine_validity'] = 'Urine Chemistry';
	$tabs['metabolites'] = 'Metabolites';
} else if (in_array('gene', $btypes)) {
	//$tabs['dru'] = 'Drugs';
}

if (in_array('study', $btypes)) {
	$tabs['study'] = 'Study';
}

if ($can_delete) {
	$tabs['receiving'] = 'Receiving';
}

if ($is_ci_admin) {
	$tabs['otherst'] = 'Other';
}



$act        = isset($act) ? $act : '';
$tabtype    = isset($_REQUEST['tabtype']) ? $_REQUEST['tabtype'] : '';
$tab        = isset($_REQUEST['tab']) && !empty($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);

if (!isset($tabs[$tab])) {
	$tab = key($tabs);
}

if (!function_exists('trpdd_panel_tabtype_label')) {
	function trpdd_panel_tabtype_label($tabtype)
	{
		$labels = array(
			'groups' => 'Groups',
			'medications' => 'Medications',
			'specimens' => 'Specimens',
			'cutoffs' => 'Cutoffs',
			'antibiotics' => 'Antibiotics',
			'pathogens' => 'Pathogens',
			'result_settings' => 'Result Settings',
			'req_settings' => 'Req Settings',
			'questions' => 'Questions',
			'inclusion' => 'Inclusion',
			'exclusion' => 'Exclusion',
			'sitenames' => 'Site Names',
			'diagnostic_codes' => 'Diagnostic Codes',
			'logs' => 'Logs',
			'batch_status' => 'Statuses',
			'irbs' => 'IRBs',
			'otherst' => 'Other',
			'metabolites' => 'Metabolites',
			'urine_validity' => 'Urine Chemistry',
		);

		if (isset($labels[$tabtype])) {
			return $labels[$tabtype];
		}

		return ucwords(str_replace('_', ' ', $tabtype));
	}
}

if (!function_exists('trpdd_panel_admin_url')) {
	function trpdd_panel_admin_url($args = array())
	{
		$base_args = array(
			'page' => 'ci_pdf_data',
			'pagetab' => 'ci_test_panels',
		);
		$final_args = array();
		foreach (array_merge($base_args, $args) as $k => $v) {
			if ($v === '' || $v === null) {
				continue;
			}
			$final_args[$k] = $v;
		}
		return add_query_arg($final_args, admin_url('admin.php'));
	}
}

if (!function_exists('trpdd_panel_build_breadcrumbs')) {
	function trpdd_panel_build_breadcrumbs($tab, $tab_label, $tabtype, $act)
	{
		global $wpdb;

		$breadcrumbs = array();
		$list_tests_url = trpdd_panel_admin_url(array(
			'tab' => $tab,
		));
		$groups_url = trpdd_panel_admin_url(array(
			'tab' => $tab,
			'tabtype' => 'groups',
			'type' => $tab,
		));
		$group_tests_url = function ($group_id) use ($tab) {
			return trpdd_panel_admin_url(array(
				'tab' => $tab,
				'gr' => $group_id,
			));
		};
		$group_settings_url = function ($group_id, $group_act = 'edit') use ($tab) {
			return trpdd_panel_admin_url(array(
				'tab' => $tab,
				'tabtype' => 'groups',
				'act' => $group_act,
				'id' => $group_id,
				'type' => $tab,
			));
		};

		$breadcrumbs[] = array('label' => 'Panels', 'url' => $list_tests_url);
		$breadcrumbs[] = array('label' => $tab_label, 'url' => $list_tests_url);

		$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
		$gr = isset($_REQUEST['gr']) ? (int)$_REQUEST['gr'] : 0;

		if (!empty($tabtype)) {
			$section_label = trpdd_panel_tabtype_label($tabtype);
			$section_url = trpdd_panel_admin_url(array(
				'tab' => $tab,
				'tabtype' => $tabtype,
				'type' => in_array($tabtype, array('groups', 'questions')) ? $tab : '',
			));
			$breadcrumbs[] = array('label' => $section_label, 'url' => $section_url);

			if ($tabtype == 'groups') {
				if ($act == 'edit' && $id > 0) {
					$group = $wpdb->get_row("select tg_ID, tg_name, tg_parent from ci_test_groups where tg_ID = {$id}", ARRAY_A);
					if (!empty($group)) {
						if (!empty($group['tg_parent'])) {
							$parent_group = $wpdb->get_row("select tg_ID, tg_name from ci_test_groups where tg_ID = {$group['tg_parent']}", ARRAY_A);
							if (!empty($parent_group)) {
								$breadcrumbs[] = array(
									'label' => $parent_group['tg_name'],
									'list_url' => $group_tests_url($parent_group['tg_ID']),
									'settings_url' => $group_settings_url($parent_group['tg_ID']),
									'is_group_node' => true,
								);
							}
						}
						$breadcrumbs[] = array(
							'label' => $group['tg_name'],
							'list_url' => $group_tests_url($group['tg_ID']),
							'settings_url' => $group_settings_url($group['tg_ID']),
							'is_group_node' => true,
						);
					}
				} else if (($act == 'bulk_edit_tests' || $act == 'copytest' || $act == 'group_export' || $act == 'duplicate') && $id > 0) {
					$group = $wpdb->get_row("select tg_ID, tg_name, tg_parent from ci_test_groups where tg_ID = {$id}", ARRAY_A);
					if (!empty($group)) {
						if (!empty($group['tg_parent'])) {
							$parent_group = $wpdb->get_row("select tg_ID, tg_name from ci_test_groups where tg_ID = {$group['tg_parent']}", ARRAY_A);
							if (!empty($parent_group)) {
								$breadcrumbs[] = array(
									'label' => $parent_group['tg_name'],
									'list_url' => $group_tests_url($parent_group['tg_ID']),
									'settings_url' => $group_settings_url($parent_group['tg_ID']),
									'is_group_node' => true,
								);
							}
						}
						$breadcrumbs[] = array(
							'label' => $group['tg_name'],
							'list_url' => $group_tests_url($group['tg_ID']),
							'settings_url' => $group_settings_url($group['tg_ID']),
							'is_group_node' => true,
						);
					}
					$breadcrumbs[] = array('label' => trpdd_panel_tabtype_label($act), 'url' => '');
				} else if ($act == 'add') {
					$breadcrumbs[] = array('label' => 'Add Group', 'url' => '');
				} else if ($act == 'plate_layout') {
					$breadcrumbs[] = array('label' => 'Plate Layout', 'url' => '');
				}
			} else if ($tabtype == 'questions') {
				$questions_url = trpdd_panel_admin_url(array(
					'tab' => $tab,
					'tabtype' => 'questions',
					'type' => $tab,
				));

				if ($act == 'groups') {
					$breadcrumbs[] = array('label' => 'Sections', 'url' => '');
				} else if ($act == 'edit' && $id > 0) {
					$question = $wpdb->get_row("select ta.ta_ID, ta.ta_question, ta.ta_header_id, tag.tag_header
						from ci_test_aoe ta
						left join ci_test_aoe_headers tag ON tag.tag_ID = ta.ta_header_id
						where ta.ta_ID = {$id}", ARRAY_A);
					if (!empty($question) && !empty($question['ta_header_id']) && !empty($question['tag_header'])) {
						$breadcrumbs[] = array(
							'label' => $question['tag_header'],
							'url' => add_query_arg(array('gr' => $question['ta_header_id']), $questions_url),
						);
					}
					if (!empty($question) && !empty($question['ta_question'])) {
						$breadcrumbs[] = array('label' => $question['ta_question'], 'url' => '');
					} else {
						$breadcrumbs[] = array('label' => 'Edit Question', 'url' => '');
					}
				} else if ($act == 'add') {
					if ($gr > 0) {
						$question_section = $wpdb->get_row("select tag_ID, tag_header from ci_test_aoe_headers where tag_ID = {$gr}", ARRAY_A);
						if (!empty($question_section)) {
							$breadcrumbs[] = array(
								'label' => $question_section['tag_header'],
								'url' => add_query_arg(array('gr' => $question_section['tag_ID']), $questions_url),
							);
						}
					}
					$breadcrumbs[] = array('label' => 'Add Question', 'url' => '');
				} else if ($gr > 0) {
					$question_section = $wpdb->get_row("select tag_ID, tag_header from ci_test_aoe_headers where tag_ID = {$gr}", ARRAY_A);
					if (!empty($question_section)) {
						$breadcrumbs[] = array(
							'label' => $question_section['tag_header'],
							'url' => add_query_arg(array('gr' => $question_section['tag_ID']), $questions_url),
						);
					}
				}
			} else if (!empty($act) && in_array($act, array('edit', 'add', 'duplicate'))) {
				$breadcrumbs[] = array('label' => ucfirst($act), 'url' => '');
			}
		} else {
			$breadcrumbs[] = array('label' => 'Tests', 'url' => $list_tests_url);

			if (in_array($act, array('edit', 'duplicate')) && $id > 0) {
				if ($tab == 'urine_validity') {
					$test = $wpdb->get_row("select u_ID, u_name, u_showinreq from ci_urine_validity where u_ID = {$id}", ARRAY_A);
					if (!empty($test)) {
						$urine_panel_type_labels = array(
							0 => 'Urine Validity',
							1 => 'Screening',
							2 => 'VDX Synthetic Urine',
						);
						$panel_type_key = (int)$test['u_showinreq'];
						$panel_type_label = isset($urine_panel_type_labels[$panel_type_key]) ? $urine_panel_type_labels[$panel_type_key] : '';
						if (!empty($panel_type_label)) {
							$breadcrumbs[] = array('label' => $panel_type_label, 'url' => '');
						}
						$breadcrumbs[] = array('label' => $test['u_name'], 'url' => '');
					}
				} else {
					$test = $wpdb->get_row("select te.te_name, g.tg_ID, g.tg_name, g.tg_parent from ci_test_panels te
						join ci_test_groups g on g.tg_ID = te.te_group_id
						where te.te_ID = {$id}", ARRAY_A);
					if (!empty($test)) {
						$breadcrumbs[] = array('label' => 'Groups', 'url' => $groups_url);
						if (!empty($test['tg_parent'])) {
							$parent_group = $wpdb->get_row("select tg_ID, tg_name from ci_test_groups where tg_ID = {$test['tg_parent']}", ARRAY_A);
							if (!empty($parent_group)) {
								$breadcrumbs[] = array(
									'label' => $parent_group['tg_name'],
									'list_url' => $group_tests_url($parent_group['tg_ID']),
									'settings_url' => $group_settings_url($parent_group['tg_ID']),
									'is_group_node' => true,
								);
							}
						}
						$breadcrumbs[] = array(
							'label' => $test['tg_name'],
							'list_url' => $group_tests_url($test['tg_ID']),
							'settings_url' => $group_settings_url($test['tg_ID']),
							'is_group_node' => true,
						);
						$breadcrumbs[] = array('label' => $test['te_name'], 'url' => '');
					}
				}
			} else if ($act == 'add') {
				if ($gr > 0) {
					$group = $wpdb->get_row("select tg_ID, tg_name, tg_parent from ci_test_groups where tg_ID = {$gr}", ARRAY_A);
					if (!empty($group)) {
						$breadcrumbs[] = array('label' => 'Groups', 'url' => $groups_url);
						if (!empty($group['tg_parent'])) {
							$parent_group = $wpdb->get_row("select tg_ID, tg_name from ci_test_groups where tg_ID = {$group['tg_parent']}", ARRAY_A);
							if (!empty($parent_group)) {
								$breadcrumbs[] = array(
									'label' => $parent_group['tg_name'],
									'list_url' => $group_tests_url($parent_group['tg_ID']),
									'settings_url' => $group_settings_url($parent_group['tg_ID']),
									'is_group_node' => true,
								);
							}
						}
						$breadcrumbs[] = array(
							'label' => $group['tg_name'],
							'list_url' => $group_tests_url($group['tg_ID']),
							'settings_url' => $group_settings_url($group['tg_ID']),
							'is_group_node' => true,
						);
					}
				}
				$breadcrumbs[] = array('label' => 'Add Test', 'url' => '');
			} else if ($gr > 0) {
				$group = $wpdb->get_row("select tg_ID, tg_name, tg_parent from ci_test_groups where tg_ID = {$gr}", ARRAY_A);
				if (!empty($group)) {
					$breadcrumbs[] = array('label' => 'Groups', 'url' => $groups_url);
					if (!empty($group['tg_parent'])) {
						$parent_group = $wpdb->get_row("select tg_ID, tg_name from ci_test_groups where tg_ID = {$group['tg_parent']}", ARRAY_A);
						if (!empty($parent_group)) {
							$breadcrumbs[] = array(
								'label' => $parent_group['tg_name'],
								'list_url' => $group_tests_url($parent_group['tg_ID']),
								'settings_url' => $group_settings_url($parent_group['tg_ID']),
								'is_group_node' => true,
							);
						}
					}
					$breadcrumbs[] = array(
						'label' => $group['tg_name'],
						'list_url' => $group_tests_url($group['tg_ID']),
						'settings_url' => $group_settings_url($group['tg_ID']),
						'is_group_node' => true,
					);
				}
			}
		}

		return $breadcrumbs;
	}
}

$panel_breadcrumbs = trpdd_panel_build_breadcrumbs($tab, $tabs[$tab], $tabtype, $act);
if (!isset($_REQUEST['trajax'])) {
?>
	<div class="wrap clear">
		<h2 class="nav-tab-wrapper">
			<?php

			foreach ($tabs as $k => $txt) {
				$class = $tab == $k ? 'nav-tab-active' : '';
				$link  = Tr_Base_Class_V6::link(array(
					'tab'     => $k,
					'act'     => '',
					'id'      => '',
					's'       => '',
					'search'  => '',
					'paged'   => '',
					'orderby' => '',
					'order'   => '',
					'tabtype' => '',
					'gr'      => '',
					'type'    => '',
					'labid'    => '',
					'step'    => '',
				), false);
				echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
			}
			?>
		</h2>
	</div>
	<?php if (!empty($panel_breadcrumbs)) { ?>
		<div id="ci-panel-global-breadcrumb" style="margin:8px 0;padding:5px 15px;border-radius:5px;;color:#646970;font-size:13px">
			<?php
			$group_icon = '<img src="' . esc_url(TRCPDD_URL . 'images/cogwheel.png') . '" alt="" width="13" height="13" style="display:inline-block;vertical-align:middle;flex:0 0 auto;" />';
			$last_breadcrumb_index = count($panel_breadcrumbs) - 1;
			foreach ($panel_breadcrumbs as $breadcrumb_index => $breadcrumb_item) {
				$item_html = esc_html($breadcrumb_item['label']);
				if (!empty($breadcrumb_item['is_group_node'])) {
					$text_html = '<span style="white-space:nowrap;">' . esc_html($breadcrumb_item['label']) . '</span>';
					if (!empty($breadcrumb_item['list_url'])) {
						$text_html = '<a href="' . esc_url($breadcrumb_item['list_url']) . '" style="white-space:nowrap;text-decoration:none;">' . esc_html($breadcrumb_item['label']) . '</a>';
					}
					$icon_html = $group_icon;
					if (!empty($breadcrumb_item['settings_url'])) {
						$icon_html = '<a href="' . esc_url($breadcrumb_item['settings_url']) . '" style="display:inline-flex;align-items:center;line-height:1;" title="Edit settings">' . $group_icon . '</a>';
					}
					$item_html = '<span style="display:inline-flex;align-items:center;gap:4px;white-space:nowrap;">' . $text_html . $icon_html . '</span>';
					echo $item_html;
				} else {
					if (!empty($breadcrumb_item['url']) && $breadcrumb_index < $last_breadcrumb_index) {
						echo '<a href="' . esc_url($breadcrumb_item['url']) . '">' . $item_html . '</a>';
					} else {
						echo '<span>' . $item_html . '</span>';
					}
				}
				if ($breadcrumb_index < $last_breadcrumb_index) {
					echo ' &rsaquo; ';
				}
			}
			?>
		</div>
	<?php } ?>
<?php
}

$tes_type  = !empty($tab) ? $tab : 'tox';

$rs_settings    = get_option('ci_result_settings_' . $tes_type, array());


if ($tab == 'otherst') {
	include(trpdd_get_file_path('panel', 'otherst'));
} else if ($tab == 'receiving') {
	include(trpdd_get_file_path('receiving', 'settings'));
} else if ($tab == 'urine_validity') {
	include(trpdd_get_file_path('panel', 'urine_validity'));
} else if ($tab == 'dru' || $tabtype == 'medications') {

	include(trpdd_get_file_path('panel', 'medications'));
} else if ($tab == 'metabolites') {
	include(trpdd_get_file_path('panel', 'metabolites'));
} else if ($tab == 'theoretical') {
	include(trpdd_get_file_path('panel', 'theoretical'));
} else if ($tabtype == 'local_resistance') {
	include(trpdd_get_file_path('abx', 'resistance_data'));
} else if ($tabtype == 'pdf_footer') {
	include(trpdd_get_file_path('lab', 'footer'));
} else if ($tabtype == 'logs') {
	include(trpdd_get_file_path('panel', 'logs'));
} else if ($tabtype == 'cu_pathogens' || $tabtype == 'cu_antibiotics') {
	include(trpdd_get_file_path('panel', 'pathogens')); //for blood
} else if (isset($_REQUEST['tabtype']) && !empty($_REQUEST['tabtype'])) {
	include(trpdd_get_file_path('panel', $_REQUEST['tabtype']));
} else if ($tes_type == 'study') {
	include(trpdd_get_file_path('panel', 'study_list'));
} else if ($act == 'edit' || $act == 'add' || $act == 'duplicate') {
	include(trpdd_get_file_path('panel', 'edit'));
} else {
	include(trpdd_get_file_path('panel', 'list'));
}
