<?php
global $wpdb;

$btypes = array();
foreach (tr_pdd_get_batch_types() as $bt) {
	$btypes[$bt] = $bt;
}
$groupbytestids 	= array();
$groupcodebytestids 	= array();
$covid_testsids 	= array();
$all_testids 		= array();
$btypes_str 		= implode("','", $btypes);

$rsgroups 			= $wpdb->get_results("
select tg_ID, 
tg_name,
tg_cpt_code,
tg_is_covid,
tg_type
from ci_test_groups 
where tg_show_req = 1 
and tg_parent = 0 
and tg_type in ('{$btypes_str}')
order by tg_position asc
", ARRAY_A);
$groups = array();

foreach ($rsgroups as $group) {

	$group['tests'] = $wpdb->get_results("select te_ID, te_name, te_loinc_code 
	from ci_test_panels te
	JOIN ci_test_groups tg ON te.te_group_id = tg.tg_ID
	where tg.tg_ID = {$group['tg_ID']} or tg.tg_parent = {$group['tg_ID']}", ARRAY_A);

	foreach ($group['tests'] as $te) {
		$groupbytestids[$te['te_ID']] = $group['tg_name'];
		$groupcodebytestids[$te['te_ID']] = $group['tg_cpt_code'];
		$all_testids[] = $te['te_ID'];
		if ($group['tg_is_covid']) {
			$covid_testsids[] = $te['te_ID'];
		}
	}
	$groups[$group['tg_ID']] = $group;
}

$all_status = array(
	0 => 'Not Setup',
	1 => 'Pending',
	2 => 'Connected',
);
$all_states = tr_get_states();


$send_to_hub = isset($_GET['send_to_hub']);
if (isset($_POST['options'])) {
	$options 	= array();
	$backup_dir = WP_CONTENT_DIR . '/backups/report_to_state/';
	if (!file_exists($backup_dir)) {
		wp_mkdir_p($backup_dir);
	}

	$old_opts 	= get_option('ci_send_hub_tests', array());
	$old_optswkeys = array();
	foreach ($old_opts as $i => $arr) {
		$old_optswkeys[$arr['tid'] . '_' . $arr['state']] = $arr;
	}
	//backup
	$backupfile = date_i18n('Y-m-dH_i_s') . '_report_to_state.bk';
	file_put_contents($backup_dir . $backupfile, json_encode($old_opts));
	foreach ($_POST['options'] as $i => $arr) {
		if ($arr['tid'] > 0) {

			$opt_key = $arr['tid'] . '_' . $arr['state'];
			if (isset($old_optswkeys[$opt_key])) {
				$oldopt = $old_optswkeys[$opt_key];
				$arr['status'] = $oldopt['status'];
			}

			if (!isset($arr['cond_only_tids']) || !is_array($arr['cond_only_tids'])) {
				$arr['cond_only_tids'] = array();
			}


			if (!isset($arr['status'])) {
				$arr['status'] = 0;
			}

			$arr['group'] 	= $groupbytestids[$arr['tid']];
			$arr['group_code'] 	= $groupcodebytestids[$arr['tid']];
			$arr['iscovid'] = in_array($arr['tid'], $covid_testsids) ? 1 : 0;


			$options[$i] = $arr;
		}
	}

	update_option('ci_send_hub_tests', $options);
	$send_to_hub = true;



	Tr_Session_Class::add("Saved");
}

if ($send_to_hub && function_exists('tr_client_send_hub')) {

	//check wsdl
	$url = get_bloginfo('url') . '/wsdl?wsdl';
	$key = '';
	$wsdl = $wpdb->get_row("select * from ci_wsdl_keys where wsdl_desc like '%Hub%'");
	if (!$wsdl) {
		$lab_id = CI_Base_Lab::get_lab_id_by_blog_id();
		$key	= wp_generate_password(15);
		$wpdb->insert('ci_wsdl_keys', array(
			'wsdl_key' => $key,
			'wsdl_lab_ID' => $lab_id,
			'wsdl_type' => '',
			'wsdl_desc' => 'Reporting Hub',
		));
	} else {
		$key = $wsdl->wsdl_key;
	}

	include_once(TRCPDD_PATH . 'api/ci_api_loinc.php');
	$loincapi = new CI_Api_LOINC();

	$options 	= get_option('ci_send_hub_tests', array());
	foreach ($options as $tk => $arr) {


		if ($arr['tid'] > 0) {
			$test = $wpdb->get_row("select * from ci_test_panels where te_ID = {$arr['tid']} ");

			$arr['test_name'] = $test->te_name;
			$arr['loinc_code'] = $test->te_loinc_code;
			$arr['snomed_code'] = $test->te_snomed_code;

			if (!empty($arr['loinc_code'])) {
				$arr['loinc_short'] = trpdd_get_test_meta($arr['tid'], '_loinc_short', '');

				if (empty($arr['loinc_short'])) {
					$arr['loinc_short'] = $loincapi->getShortName($arr['loinc_code']);
					if (!empty($arr['loinc_short'])) {
						$rs1 = trpdd_update_test_meta($arr['tid'], '_loinc_short', $arr['loinc_short']);
					}
				}

				if (empty($arr['loinc_short'])) {
					Tr_Session_Class::add_error("Cant get Short Description of Loinc code: {$arr['loinc_code']} ");
				}
			}
		}

		if (isset($arr['cond_only_tids']) && count($arr['cond_only_tids']) > 0) {
			$arr['cond_only_test_names'] = array();
			$arr['cond_only_loinc_codes'] = array();
			foreach ($arr['cond_only_tids'] as $onltid) {
				$test2 = $wpdb->get_row("select te_name, te_loinc_code from ci_test_panels
							where te_ID = {$onltid} ");
				$arr['cond_only_test_names'][$onltid] = $test2->te_name;
				$arr['cond_only_loinc_codes'][$onltid] = $test2->te_loinc_code;
			}
		}

		$options[$tk] = $arr;
	}
	$specimens = array();
	$group_specimens = array();
	$specs = $wpdb->get_results("select ts.ts_name, ts.ts_code , g.tg_name
		from ci_test_specimens ts
		left JOIN ci_test_groups g ON ts.ts_group_id = g.tg_ID
		 where ts.ts_code !='' ");
	foreach ($specs as $spec) {
		if (!empty($spec->ts_code)) {
			$specimens[$spec->ts_name] = $spec->ts_code;
		}
		if (!empty($spec->tg_name)) {
			$group_specimens[$spec->tg_name] = $spec->ts_name;
		}
	}

	$rs = tr_client_send_hub(array(
		'action' 	=> 'report_tests',
		'tests' 	=> $options,
		'specimens' => $specimens,
		'group_specs' => $group_specimens,
		'url' 		=> $url,
		'key'		=> $key,
		//'both_server' => true,
	));
	if (empty($rs) || !isset($rs['status']) || $rs['status'] != 'ok') {
		Tr_Session_Class::add_error("Error when send config to Hub");
	}

	if (isset($_GET['debug'])) {
		var_dump($options);
		var_dump($rs);
	}
}


$options 	= get_option('ci_send_hub_tests', array());
$lastid 	= 0;
$existids 	= array();
$exist_keys = array();

usort($options, function ($a, $b) {
	if ($a['state'] == $b['state']) {
		return strcmp($a['group'], $b['group']);
	}
	return strcmp($a['state'], $b['state']);
});

foreach ($options as $k => $arr) {

	$existids[] = $arr['tid'];

	if (isset($arr['cond_only_tid']) && !isset($arr['cond_only_tids'])) {
		$options[$k]['cond_only_tids'] = array($arr['cond_only_tid']);
	}


	$kid = intval(str_replace('k', '', $k));
	if ($kid > $lastid) {
		$lastid = $kid;
	}

	$key = $arr['tid'] . $arr['cond_pos_neg'] . $arr['state'];
	if (in_array($key, $exist_keys)) {
		//unset($options[$k]);
		//echo 'unset '.$key.'<br>';
	} else {
		$exist_keys[] = $key;
	}
}

if (isset($_GET['duplicatestate'])) {
	$newstate = $_GET['duplicatestate'];
	foreach ($options as $k => $arr) {
		$lastid++;

		$arr['state'] = $newstate;
		$options['k' . $lastid] = $arr;
	}
}

if (isset($_GET['addold'])) {
	foreach ($options as $k => $arr) {
		$groupname = $groupbytestids[$arr['tid']];
		if (stripos($groupname, '(new)') !== false) {
			$groupname = str_replace('(new)', '', $groupname);
			$groupname2 = str_ireplace(' Panel', '', $groupname);

			$test = $wpdb->get_row("select te_name, te_abrev from ci_test_panels
				where te_ID = {$arr['tid']} ");
			var_dump($test);
			$abrev = !empty($test->te_abrev) ? $test->te_abrev : 'no_abrev';
			$oldtests = $wpdb->get_results("select te_ID, te_name, te_loinc_code from ci_test_panels
				where  (te_name = '{$test->te_name}' or te_abrev = '{$abrev}')
				and te_ID != {$arr['tid']} ", ARRAY_A);

			foreach ($oldtests as $oldtest) {
				var_dump($oldtest);
				if ($oldtest && !in_array($oldtest['te_ID'], $existids)) {
					$k = 'k' . $lastid;
					$lastid++;
					$options[$k] = $arr;
					$options[$k]['tid'] = $oldtest['te_ID'];
					$options[$k]['group'] = $groupbytestids[$oldtest['te_ID']];
					$options[$k]['iscovid'] = in_array($oldtest['te_ID'], $covid_testsids) ? 1 : 0;
					$options[$k]['status'] = 0;
				} else {
					echo '=> exists';
				}
			}


			echo '<br><br>';
		}
	}
}

foreach ($options as $k => $arr) {

	if (!in_array($arr['tid'], $all_testids)) {

		if (!isset($_REQUEST['removeold'])) {
			$test = $wpdb->get_row("select te_name, te_loinc_code, te_group_id 
			from ci_test_panels
			where te_ID = {$arr['tid']} ");
			if ($test) {
				$group = $wpdb->get_row("
				select tg_ID, 
				tg_name,
				tg_is_covid,
				tg_type
				from ci_test_groups 
				where tg_ID = {$test->te_group_id} ", ARRAY_A);
				if ($group) {
					if (!isset($groups[$group['tg_ID']])) {
						$group['tests'] = $wpdb->get_results("select te_ID, te_name, te_loinc_code 
						from ci_test_panels te
						JOIN ci_test_groups tg ON te.te_group_id = tg.tg_ID
						where tg.tg_ID = {$group['tg_ID']} or tg.tg_parent = {$group['tg_ID']}", ARRAY_A);
						foreach ($group['tests'] as $te) {
							$groupbytestids[$te['te_ID']] = $group['tg_name'];
							$all_testids[] = $te['te_ID'];
							if ($group['tg_is_covid']) {
								$covid_testsids[] = $te['te_ID'];
							}
						}
						$groups[$group['tg_ID']] = $group;
					}
				}
			}
		} else {
			unset($options[$k]);
		}
	}
}



?>
<div class="wrap">
	<h2 title="<?php echo count($options) ?>">Report to State</h2>
	<?php Tr_Session_Class::show() ?>

	<form method="post">
		<div class="frow">
			<div class="col12">
				<h4>Type</h4>
			</div>
			<div class="col3">
				<h4>Test</h4>
			</div>
			<div class="col12">
				<h4>Condition</h4>
			</div>
			<div class="col6">
				<h4>Condition Tests</h4>
			</div>
			<div class="col12">&nbsp;</div>
			<div class="col9">
				<h4>State</h4>
			</div>
			<div class="col7">
				<h4>Status</h4>
			</div>
		</div>
		<div id="listtests"></div>
		<div class="loading"></div>

		<div id="actions_btn" style="display: none;">
			<input type="submit" class="button-primary" value="Save" />
			<a class="button addrow">+</a>
		</div>
	</form>
</div>
<style>
	.statuscol {
		line-height: 32px;
	}

	.statuscol .button {
		margin-top: 6px;
	}

	.only_pos_neg:not(.activet) select,
	.only_pos_neg:not(.activet) label {
		visibility: hidden;
	}
</style>
<script>
	var $lastid = <?php echo $lastid ?>;
	var $all_status = <?php echo json_encode($all_status) ?>;
	var $options = <?php echo json_encode($options) ?>;
	var $groups = <?php echo json_encode($groups); ?>;
	var $all_states = <?php echo json_encode($all_states); ?>;
	var $btypes = <?php echo json_encode($btypes); ?>;
	var $pos_negs = {
		'0': 'Both',
		'1': 'POS',
		'2': 'NEG'
	};
	jQuery(function($) {
		function add_item_row($id, $item) {

			if (!$item['status']) $item['status'] = 0;
			if (!$item['btype']) $item['btype'] = 'pcr';

			let $html = `<div class="item frow">
			<div class="col12 btype"><select data-k="btype"  name="options[${$id}][btype]">${show_btype_options($item['btype'])}</select></div>
			<div class="col3 main_tid"><select data-k="tid" name="options[${$id}][tid]">${show_test_options($item['btype'], $item['tid'])}</select></div>
			<div class="col12"><select data-k="cond_pos_neg" name="options[${$id}][cond_pos_neg]">${show_cond_options($item['cond_pos_neg'])}</select></div>
			<div class="col6 only_tid"><select class="s_select2" multiple data-k="cond_only_tids" name="options[${$id}][cond_only_tids][]" >${show_test_options($item['btype'], $item['cond_only_tids'], true)}</select></div>
			<div class="col12 only_pos_neg">
			<select data-k="cond_only_pos_neg" name="options[${$id}][cond_only_pos_neg]">${show_cond_options($item['cond_only_pos_neg'])}</select>
			<label><input type="checkbox" name="options[${$id}][send_cond_tests]" value="1" ${$item['send_cond_tests']?'checked':''} /> Send Tests</label>
			</div>
			<div class="col9">
				<select name="options[${$id}][state]">${show_state_options($item['state'])}</select>
				
			</div>
			<div class="col7 statuscol">${$all_status[$item['status']]} <a class="button bsmall removerow">-</a>  
			<a class="button bsmall bduplicate">D</a></div>
			</div>`;

			var $html_object = $($html);
			$('.activet', $html_object).removeClass('activet');
			$('#listtests').append($html_object);
			$('select[name="options[' + $id + '][cond_only_tids][]"]').change();
			$('.s_select2', $html_object).select2();

		}

		function show_btype_options($vl) {
			let $opts = '';
			for (i in $btypes) {
				$selected = i == $vl ? 'selected' : '';
				$opts += '<option value="' + i + '" ' + $selected + '>' + $btypes[i] + '</option>';
			}
			return $opts;
		}

		function show_cond_options($vl) {
			let $opts = '';
			for (i in $pos_negs) {
				$selected = i == $vl ? 'selected' : '';
				$opts += '<option value="' + i + '" ' + $selected + '>' + $pos_negs[i] + '</option>';
			}
			return $opts;
		}

		function show_state_options($vl) {
			let $opts = '';
			for (i in $all_states) {
				$selected = i == $vl ? 'selected' : '';
				$opts += '<option value="' + i + '" ' + $selected + '>' + i + ' - ' + $all_states[i] + '</option>';
			}
			return $opts;
		}

		function show_test_options($bt, $vl, $multi) {
			let $opts = '<option value="">Select</option>';
			for (i in $groups) {
				if ($bt != $groups[i]['tg_type']) continue;
				$opts += '<optgroup label="' + $groups[i]['tg_name'] + '">';
				for (t in $groups[i]['tests']) {
					var $test = $groups[i]['tests'][t];

					if ($multi && typeof $vl == 'object') {
						$selected = $vl.includes($test['te_ID']) ? 'selected' : '';
					} else {
						$selected = $test['te_ID'] == $vl ? 'selected' : '';
					}

					$opts += '<option value="' + $test['te_ID'] + '" ' + $selected + '>' + $test['te_name'] + ' (' + $test['te_loinc_code'] + ')</option>';
				}
				$opts += '</optgroup>';

			}
			return $opts;
		}


		$('body').on('click', '.addrow', function() {
				$lastid++;
				add_item_row('k' + $lastid, {});
			}).on('click', 'a.removerow', function() {
				if (confirm('Are you sure you want to delete this test?')) {
					var $pr = $(this).parents('div.item');
					$pr.remove();
				}

			}).on('change', '.only_tid select', function() {
				var $pr = $(this).parents('div.item');

				if ($('option:selected', $(this)).length > 0) {
					$('.only_pos_neg', $pr).addClass('activet');
				} else {
					$('.only_pos_neg', $pr).removeClass('activet');
				}
			}).on('change', '.btype select', function() {
				var $pr = $(this).parents('div.item');
				if ($(this).val() !== '') {
					let $opts = show_test_options($(this).val(), '');
					$('.main_tid select', $pr).html($opts);
					$('.only_tid select', $pr).html($opts);
				}
			})
			.on('click', '.bduplicate', function() {
				var $pr = $(this).parents('div.item');
				var $opts = {};
				$('input,select', $pr).each(function() {
					var $k = $(this).data('k');
					var $vl = $(this).val();
					$opts[$k] = $vl;
				});
				$lastid++;
				add_item_row('k' + $lastid, $opts);
			})

		for (i in $options) {
			add_item_row(i, $options[i]);
		}

		$('#actions_btn').show();
		$('.loading').hide();

	})
</script>