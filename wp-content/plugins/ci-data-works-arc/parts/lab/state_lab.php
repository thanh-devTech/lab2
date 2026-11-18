<?php
global $wpdb, $is_ci_admin;

$state_lab_old_labs = isset($slug_main) && $slug_main === 'ci_manage_labs';

if (!function_exists('icon_sort')) {
	function icon_sort()
	{
		$icon_down_up = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon_sort lucide lucide-arrow-up-down inline h-3 w-3 ml-1 opacity-40" aria-hidden="true"><path d="m21 16-4 4-4-4"></path><path d="M17 20V4"></path><path d="m3 8 4-4 4 4"></path><path d="M7 4v16"></path></svg>';
		$icon_up = '<svg style="display:none" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon_sort lucide lucide-arrow-up h-3 w-3 ml-1 text-primary" aria-hidden="true"><path d="m5 12 7-7 7 7"></path><path d="M12 19V5"></path></svg>';
		$icon_down = '<svg style="display:none" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon_sort lucide lucide-arrow-down h-3 w-3 ml-1 text-primary" aria-hidden="true"><path d="M12 5v14"></path><path d="m19 12-7 7-7-7"></path></svg>';
		return $icon_down_up . $icon_up . $icon_down;
	}
}

$btypes = array();
foreach (tr_pdd_get_batch_types() as $bt) {
	$btypes[$bt] = $bt;
}
$btype_keys = array_keys($btypes);
$single_btype_mode = count($btype_keys) === 1;
$single_btype_key = $single_btype_mode ? (string) $btype_keys[0] : '';
$single_btype_label = $single_btype_mode ? (string) $btypes[$single_btype_key] : '';
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
<style>
	table select {
		border-radius: 20px !important;
		background-color: oklch(0.98 0 0) !important;
		border: none !important;
		font-size: 13px !important;
		color: var(--color-gray-600) !important;
	}

	.select2-container--default .select2-selection--multiple {
		border: 0px none;
		max-width: 200px;
	}

	.select2-container--default .select2-selection--multiple .select2-selection__choice {
		background-color: white;
		color: #6b7280;
		border-color: #0000001f;
	}

	.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
		color: #d83d00;
	}

	body .select2-container .select2-selection--single,
	body .select2-container--default .select2-selection--single .select2-selection__arrow {
		border: none;
	}

	body .select2-container--default .select2-selection--single .select2-selection__rendered {
		background-color: #f9f9f9;
		border-radius: 10px;
		color: #4a5565;
		padding-right: 30px
	}

	.select2-container--default .select2-results__option[role=group] {
		font-size: 12px;
	}

	.select2-dropdown {
		border-top: solid 1px #3838384a !important;
		border-top-right-radius: 4px !important;
		border-top-left-radius: 4px !important;
		padding: 0;
		overflow: hidden;
		box-sizing: border-box;
		box-shadow: 0px 1px 7px 0px #7878784a;
		min-width: 100px !important;
	}

	.select2-results {
		padding: 3px;
		box-sizing: border-box;
	}

	.select2-results__options {
		max-height: 260px !important;
		overflow-x: hidden !important;
		overflow-y: auto !important;
		box-sizing: border-box;
	}

	.select2-results__options--nested {
		max-height: none !important;
		overflow: visible !important;
	}

	.select2-results__option,
	.select2-results__group {
		white-space: nowrap !important;
		overflow: visible !important;
		text-overflow: clip !important;
	}

	body .select2-container--default .select2-results__option--highlighted[aria-selected] {
		background: #f7c0c0ff;
		color: #d83d00;
	}

	.select2-results__options::-webkit-scrollbar {
		width: 6px;
	}

	.select2-results__options::-webkit-scrollbar-thumb {
		background: #cbd5e1;
		border-radius: 8px;
	}

	.select2-results__options::-webkit-scrollbar-track {
		background: transparent;
	}

	.select2-results__option--highlighted:not(.select2-results__option--selected) {
		background-color: #f7c0c0ff !important;
		color: #d83d00 !important;
	}

	.select2-container--default .select2-selection--single {
		background-color: transparent !important;
	}

	.select2-container--default .select2-selection--single .select2-selection__arrow b {
		background-image: url("<?php echo TRCPDD_URL; ?>/images/down_arrow.png") !important;
		background-repeat: no-repeat;
		background-size: 100% 100%;
		height: 16px;
		left: 50%;
		margin-left: -14px;
		margin-top: -8px;
		position: absolute;
		top: 50%;
		width: 16px;
		border: none;
	}

	.btn_extension {
		padding: 0px;
		margin: 0px;
	}

	.lazy-select2-wrap {
		position: relative;
		width: 100%;
		max-width: 100%;
		min-width: 0;
		box-sizing: border-box;
	}

	.lazy-select2-wrap.is-multi {
		min-width: 0;
	}

	.lazy-select2-trigger {
		width: 100%;
		max-width: 100%;
		border: none;
		padding: 0;
		background: transparent;
		position: relative;
		cursor: pointer;
		text-align: left;
		z-index: 2;
		box-sizing: border-box;
	}

	.lazy-select2-trigger.is-disabled {
		cursor: not-allowed;
		opacity: 0.65;
	}

	.lazy-select2-trigger-value {
		display: block;
		width: 100%;
		max-width: 100%;
		min-height: 30px;
		padding: 0 34px 0 12px;
		background-color: #f9f9f9;
		border-radius: 8px;
		color: #4a5565;
		font-size: 13px;
		line-height: 30px;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		box-sizing: border-box;
	}

	.lazy-select2-wrap.is-multi .lazy-select2-trigger-value {
		display: block;
		min-height: 30px;
		padding: 0 30px 0 12px;
		line-height: 30px;
		background-color: oklch(0.98 0 0);
		border-radius: 10px;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	#state_lab_frm td.state-lab-col-ct .lazy-select2-trigger-value {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 4px;
		min-height: 36px;
		padding: 4px 24px 4px 4px;
		line-height: normal;
		white-space: normal;
		overflow: visible;
		text-overflow: clip;
	}

	.lazy-select2-trigger::after {
		content: "";
		position: absolute;
		top: 50%;
		right: 10px;
		width: 16px;
		height: 16px;
		transform: translateY(-50%);
		background-image: url("<?php echo TRCPDD_URL; ?>/images/down_arrow.png");
		background-repeat: no-repeat;
		background-size: 100% 100%;
		pointer-events: none;
	}

	.lazy-select2-trigger-value.is-placeholder {
		color: #6b7280;
	}

	.lazy-select2-tag {
		display: inline-flex;
		align-items: center;
		padding: 3px 8px;
		border: 1px solid #0000001f;
		border-radius: 8px;
		background-color: white;
		color: #6b7280;
		font-size: 12px;
		line-height: 1.2;
		max-width: 100%;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.lazy-select2-source {
		position: absolute;
		top: 0;
		left: 0;
		width: 100% !important;
		max-width: 100% !important;
		height: 100%;
		opacity: 0;
		pointer-events: none;
	}

	.lazy-select2-wrap .select2-container {
		width: 100% !important;
		max-width: 100% !important;
	}

	.state-lab-table-wrap {
		overflow-x: auto !important;
		overflow-y: hidden !important;
		-webkit-overflow-scrolling: touch;
	}

	#state_lab_frm [data-slot="table-container"] {
		overflow: visible !important;
		min-width: 100%;
	}

	#state_lab_frm table[data-slot="table"] {
		width: 100%;
		min-width: 0;
		table-layout: fixed;
	}

	.state-lab-col-type {
		width: 80px;
	}

	.state-lab-col-test {
		width: 190px;
	}

	.state-lab-col-panel {
		width: 96px;
	}

	.state-lab-col-condition,
	.state-lab-col-ct-condition {
		width: 58px;
	}

	.state-lab-col-ct {
		width: 180px;
	}

	.state-lab-col-send-ct {
		width: 46px;
		text-align: center;
	}

	.state-lab-col-state {
		width: 70px;
	}

	.state-lab-col-status {
		width: 152px;
	}

	#state_lab_frm td.state-lab-col-panel span[data-k="panel_name"] {
		display: block;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	#state_lab_frm td.state-lab-col-send-ct,
	#state_lab_frm th.state-lab-col-send-ct {
		text-align: center;
	}

	#state_lab_frm td.state-lab-col-condition .lazy-select2-trigger-value,
	#state_lab_frm td.state-lab-col-ct-condition .lazy-select2-trigger-value,
	#state_lab_frm td.state-lab-col-state .lazy-select2-trigger-value {
		padding: 0 20px 0 6px;
		font-size: 11px;
	}

	#state_lab_frm td.state-lab-col-condition .lazy-select2-trigger::after,
	#state_lab_frm td.state-lab-col-ct-condition .lazy-select2-trigger::after,
	#state_lab_frm td.state-lab-col-state .lazy-select2-trigger::after {
		right: 4px;
		width: 12px;
		height: 12px;
	}

	#state_lab_frm th.state-lab-col-condition,
	#state_lab_frm th.state-lab-col-ct-condition,
	#state_lab_frm th.state-lab-col-send-ct {
		white-space: normal;
		line-height: 1.15;
	}

	.state-lab-status-actions {
		display: grid;
		grid-template-columns: minmax(0, 1fr) 32px 32px;
		align-items: center;
		column-gap: 8px;
	}

	.state-lab-status-badge {
		width: 80%;
		min-width: 0;
	}

	.state-lab-status-badge [data-slot="badge"] {
		width: 100%;
		justify-content: center;
	}

	.state-lab-filters {
		display: grid;
		grid-template-columns: repeat(3, minmax(180px, 1fr)) auto;
		gap: 12px;
		align-items: end;
	}

	.state-lab-filter-field label {
		display: block;
		margin-bottom: 6px;
		font-size: 12px;
		font-weight: 500;
		color: #6b7280;
	}

	.state-lab-filter-actions {
		display: flex;
		align-items: end;
	}

	.state-lab-history-list {
		max-height: 420px;
		overflow-y: auto;
		text-align: left;
		padding-right: 8px;
	}

	.state-lab-history-item {
		display: grid;
		grid-template-columns: minmax(0, 1fr) auto auto;
		gap: 12px;
		align-items: center;
		padding: 12px 8px 12px 0;
		border-bottom: 1px solid #e5e7eb;
	}

	.state-lab-history-item:last-child {
		border-bottom: none;
	}

	.state-lab-history-name {
		font-size: 13px;
		font-weight: 600;
		color: #111827;
		word-break: break-all;
	}

	.state-lab-history-meta {
		margin-top: 4px;
		font-size: 12px;
		font-weight: 500;
		color: #4b5563;
	}

	.state-lab-history-user {
		color: #dc2626;
	}

	.state-lab-import-hint {
		text-align: left;
		font-size: 13px;
		line-height: 1.6;
		color: #4b5563;
	}

	body .swal2-title {
		line-height: 1.35 !important;
		white-space: normal !important;
		word-break: break-word;
		padding: 0 1rem !important;
	}

	@media (max-width: 1100px) {
		.state-lab-filters {
			grid-template-columns: repeat(2, minmax(180px, 1fr));
		}
	}

	@media (max-width: 700px) {
		.state-lab-filters {
			grid-template-columns: 1fr;
		}
	}

	<?php if ($state_lab_old_labs) { ?>.state-lab-old-labs {
		padding-right: 16px;
	}

	<?php } ?>
</style>
<?php if ((!isset($slug_main) || $slug_main !== 'ci_lab_main') && !defined('TR_STATE_LAB_SWEETALERT_LOADED')) {
	define('TR_STATE_LAB_SWEETALERT_LOADED', true); ?>
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php } ?>
<div class="wrap_table<?php echo $state_lab_old_labs ? ' state-lab-old-labs' : ''; ?>">
	<form id="state_lab_frm" method="post" class="space-y-6 pb-8">
		<div class="flex items-center justify-between mb-4">
			<h3 title="<?php echo count($options) ?>" class="text-lg font-semibold">Report to State</h3>
			<div class="flex items-center gap-2">
				<?php if (intval($is_ci_admin) === 1) { ?>
					<input id="state_lab_import_file" type="file" accept=".json,.bk,.csv,application/json,text/csv" style="display:none;" />
					<button id="btn_history" type="button" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] border bg-background text-foreground hover:bg-accent hover:text-accent-foreground h-9 px-4 py-2 has-[&gt;svg]:px-3">
						<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-history h-4 w-4" aria-hidden="true">
							<path d="M12 8v4l3 3"></path>
							<path d="M3.05 11a9 9 0 1 1 .5 4"></path>
							<path d="M3 4v5h5"></path>
						</svg>
						History
					</button>
					<button id="btn_import" type="button" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] border bg-background text-foreground hover:bg-accent hover:text-accent-foreground h-9 px-4 py-2 has-[&gt;svg]:px-3">
						<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-upload h-4 w-4" aria-hidden="true">
							<path d="M12 3v12"></path>
							<path d="m17 8-5-5-5 5"></path>
							<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
						</svg>
						Import
					</button>
					<?php if (isset($_GET['debug'])) { ?>
						<button id="btn_export_json" type="button" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] border bg-background text-foreground hover:bg-accent hover:text-accent-foreground h-9 px-4 py-2 has-[&gt;svg]:px-3">
							<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-json h-4 w-4" aria-hidden="true">
								<path d="M10 2v8l3-3 3 3V2"></path>
								<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
								<path d="M20 8h-6"></path>
								<path d="M8 13h0"></path>
								<path d="M8 17h0"></path>
								<path d="M12 13h4"></path>
								<path d="M12 17h4"></path>
							</svg>
							Export JSON
						</button>
				<?php }
				} ?>
				<button id="btn_export" type="button" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-9 px-4 py-2 has-[&gt;svg]:px-3">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-download h-4 w-4 mr-2">
						<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
						<polyline points="7 10 12 15 17 10"></polyline>
						<line x1="12" x2="12" y1="15" y2="3"></line>
					</svg>
					Export CSV
				</button>
			</div>
		</div>
		<?php Tr_Session_Class::show() ?>
		<div class="state-lab-filters bg-white rounded-lg border shadow-sm p-4">
			<div class="state-lab-filter-field">
				<label for="state_lab_filter_state">State</label>
				<div class="lazy-select2-wrap is-single">
					<button type="button" class="lazy-select2-trigger" title="All States">
						<span class="lazy-select2-trigger-value is-placeholder">All States</span>
					</button>
					<select id="state_lab_filter_state" class="s_select3 lazy-select2-source state-lab-filter" data-filter-key="state" data-placeholder="All States" style="width:100%;">
						<option value="__all_state__">All States</option>
					</select>
				</div>
			</div>
			<div class="state-lab-filter-field">
				<label for="state_lab_filter_panel">Panel</label>
				<div class="lazy-select2-wrap is-single">
					<button type="button" class="lazy-select2-trigger" title="All Panels">
						<span class="lazy-select2-trigger-value is-placeholder">All Panels</span>
					</button>
					<select id="state_lab_filter_panel" class="s_select3 lazy-select2-source state-lab-filter" data-filter-key="panel" data-placeholder="All Panels" style="width:100%;">
						<option value="__all_panel__">All Panels</option>
					</select>
				</div>
			</div>
			<div class="state-lab-filter-field">
				<label for="state_lab_filter_test">Test</label>
				<div class="lazy-select2-wrap is-single">
					<button type="button" class="lazy-select2-trigger" title="All Tests">
						<span class="lazy-select2-trigger-value is-placeholder">All Tests</span>
					</button>
					<select id="state_lab_filter_test" class="s_select3 lazy-select2-source state-lab-filter" data-filter-key="test" data-placeholder="All Tests" style="width:100%;">
						<option value="__all_test__">All Tests</option>
					</select>
				</div>
			</div>
			<div class="state-lab-filter-actions">
				<button id="state_lab_filter_reset" type="button" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all border bg-background text-foreground hover:bg-accent hover:text-accent-foreground h-9 px-4 py-2">Clear Filters</button>
			</div>
		</div>
		<div class="state-lab-table-wrap bg-white rounded-lg border shadow-sm">
			<div data-slot="table-container" class="relative w-full">
				<table data-slot="table" class="w-full caption-bottom text-sm">
					<thead data-slot="table-header" class="[&amp;_tr]:border-b">
						<tr data-slot="table-row" class="hover:bg-muted/50 data-[state=selected]:bg-muted border-b transition-colors bg-gray-100">
							<?php if (!$single_btype_mode) { ?>
								<th data-slot="table-head" class="state-lab-col-type text-foreground h-10 px-2 text-left align-middle font-medium whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px]">Type</th>
							<?php } ?>
							<th data-asc="asc" data-slot="table-head" class="state-lab-col-test cursor-pointer text-foreground h-10 px-2 text-left align-middle font-medium whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px]">
								Test <?php echo icon_sort(); ?>
							</th>
							<th data-slot="table-head" class="state-lab-col-panel text-foreground h-10 px-2 text-left align-middle font-medium whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px]">Panel</th>
							<th data-slot="table-head" class="state-lab-col-condition text-foreground h-10 px-2 text-left align-middle font-medium whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px]">Condition</th>
							<th data-slot="table-head" class="state-lab-col-ct text-foreground h-10 px-2 text-left align-middle font-medium whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] bg-red-50/50">Conditional Tests (CT)</th>
							<th data-slot="table-head" class="state-lab-col-ct-condition text-foreground h-10 px-2 text-left align-middle font-medium whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] bg-red-50/50">Condition</th>
							<th data-slot="table-head" class="state-lab-col-send-ct text-foreground h-10 px-2 align-middle font-medium whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] bg-red-50/50">Send CT</th>
							<th data-slot="table-head" class="state-lab-col-state cursor-pointer text-foreground h-10 px-2 text-left align-middle font-medium whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px]">
								<?php echo icon_sort(); ?>
							</th>
							<th data-slot="table-head" class="state-lab-col-status text-foreground h-10 px-2 text-left align-middle font-medium whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px]">Status</th>
						</tr>
					</thead>
					<tbody id="listtests" data-slot="table-body" class="[&amp;_tr:last-child]:border-0">
					</tbody>
				</table>
			</div>
		</div>

		<div class="loading"></div>

		<div id="actions_btn" class="flex gap-3 hidden">
			<button id="btn_save" type="submit" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive text-primary-foreground h-9 px-4 py-2 has-[&gt;svg]:px-3 shadow-brand hover:shadow-brand-lg transition-shadow-smooth bg-primary hover:bg-primary/90">Save</button>
			<button type="button" data-slot="button" class="addrow cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 hover:bg-primary/5">
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus h-4 w-4" aria-hidden="true">
					<path d="M5 12h14"></path>
					<path d="M12 5v14"></path>
				</svg>
			</button>
			<span id="count_items"></span>
		</div>
	</form>
</div>

<script>
	var $lastid = <?php echo $lastid ?>;
	var $all_status = <?php echo json_encode($all_status) ?>;
	var $options = <?php echo json_encode($options) ?>;
	var $groups = <?php echo json_encode($groups); ?>;
	var $all_states = <?php echo json_encode($all_states); ?>;
	var $btypes = <?php echo json_encode($btypes); ?>;
	var showTypeColumn = <?php echo $single_btype_mode ? 'false' : 'true'; ?>;
	var singleBtypeKey = <?php echo json_encode($single_btype_key); ?>;
	var singleBtypeLabel = <?php echo json_encode($single_btype_label); ?>;
	var $pos_negs = {
		'0': 'Both',
		'1': 'POS',
		'2': 'NEG'
	};
	jQuery(function($) {
		var $ = jQuery;
		var testMetaById = {};
		var testsByNameKey = {};
		var conditionSelect2Options = {
			width: '100%',
			placeholder: 'Select tests',
			closeOnSelect: false,
			minimumResultsForSearch: 0
		};
		var singleSelect2Options = {
			width: '100%',
			minimumResultsForSearch: 1,
			dropdownAutoWidth: false
		};
		var singleSelect2SmallOptions = $.extend({}, singleSelect2Options);
		var singleSelect2LargeOptions = $.extend({}, singleSelect2Options);
		var singleSelect2StateOptions = $.extend({}, singleSelect2Options);
		var FILTER_ALL_STATE = '__all_state__';
		var FILTER_ALL_PANEL = '__all_panel__';
		var FILTER_ALL_TEST = '__all_test__';
		var stateLabAjaxUrl = <?php echo json_encode(isset($state_lab_ajax_url) ? $state_lab_ajax_url : 'admin.php?page=' . $slug_main); ?>;

		for (const groupId in $groups) {
			const group = $groups[groupId];
			for (const testIndex in group['tests']) {
				const test = group['tests'][testIndex];
				testMetaById[String(test['te_ID'])] = {
					name: test['te_name'],
					normalizedName: normalize_main_test_name(test['te_name']),
					groupId: String(groupId),
					groupName: group['tg_name'],
					btype: group['tg_type']
				};
				if (!testsByNameKey[testMetaById[String(test['te_ID'])].normalizedName]) {
					testsByNameKey[testMetaById[String(test['te_ID'])].normalizedName] = [];
				}
				testsByNameKey[testMetaById[String(test['te_ID'])].normalizedName].push({
					tid: String(test['te_ID']),
					groupId: String(groupId),
					groupName: group['tg_name'],
					btype: group['tg_type'],
					name: test['te_name']
				});
			}
		}

		if ($.fn.select2 && $.fn.select2.amd) {
			var select2Amd = $.fn.select2.amd;
			var select2Utils = select2Amd.require('select2/utils');
			var select2Dropdown = select2Amd.require('select2/dropdown');
			var select2DropdownSearch = select2Amd.require('select2/dropdown/search');
			var select2AttachBody = select2Amd.require('select2/dropdown/attachBody');
			var select2MultipleSelection = select2Amd.require('select2/selection/multiple');
			var select2Placeholder = select2Amd.require('select2/selection/placeholder');
			var select2EventRelay = select2Amd.require('select2/selection/eventRelay');
			var conditionDropdownAdapter = select2Utils.Decorate(
				select2Utils.Decorate(select2Dropdown, select2DropdownSearch),
				select2AttachBody
			);
			var conditionSelectionAdapter = select2Utils.Decorate(
				select2Utils.Decorate(select2MultipleSelection, select2Placeholder),
				select2EventRelay
			);
			var singleDropdownBaseAdapter = select2Utils.Decorate(
				select2Utils.Decorate(select2Dropdown, select2DropdownSearch),
				select2AttachBody
			);

			function makeFixedWidthDropdownAdapter(fixedWidth) {
				function FixedWidthDropdown() {
					singleDropdownBaseAdapter.apply(this, arguments);
				}

				FixedWidthDropdown.prototype = Object.create(singleDropdownBaseAdapter.prototype);
				FixedWidthDropdown.prototype.constructor = FixedWidthDropdown;
				FixedWidthDropdown.prototype._resizeDropdown = function() {
					singleDropdownBaseAdapter.prototype._resizeDropdown.apply(this, arguments);
					if (this.$dropdown && this.$dropdown.length) {
						this.$dropdown.css({
							width: fixedWidth + 'px',
							minWidth: fixedWidth + 'px'
						});
					}
				};

				return FixedWidthDropdown;
			}

			conditionSelect2Options.dropdownAdapter = conditionDropdownAdapter;
			conditionSelect2Options.selectionAdapter = conditionSelectionAdapter;
			singleSelect2Options.dropdownAdapter = singleDropdownBaseAdapter;
			singleSelect2SmallOptions.dropdownAdapter = makeFixedWidthDropdownAdapter(100);
			singleSelect2LargeOptions.dropdownAdapter = makeFixedWidthDropdownAdapter(250);
			singleSelect2StateOptions.dropdownAdapter = makeFixedWidthDropdownAdapter(260);
		}

		function handle_lazy_select($select, action, shouldOpen) {
			if (!$select.length) {
				return '';
			}

			var isActive = $select.hasClass('select2-hidden-accessible');
			var isMulti = $select.hasClass('s_select2');
			var isRowStateSelect = $select.is('select[name$="[state]"]') && !$select.hasClass('state-lab-filter');
			var $trigger = $select.siblings('.lazy-select2-trigger');
			var selectedTexts = $('option:selected', $select).map(function() {
				return $.trim($(this).text());
			}).get().filter(function(text) {
				return text !== '';
			});

			if (action === 'store') {
				if ($select.is('select[data-k="tid"]')) {
					$select.data('previous-tid', String($select.val() || ''));
				}
				return '';
			}

			if (action === 'text') {
				return isMulti ? selectedTexts.join('; ') : (selectedTexts[0] || '');
			}

			if (action === 'sync') {
				if (isActive) {
					$select.trigger('change.select2');
				}
				if ($trigger.length) {
					var placeholder = $select.data('placeholder') || 'Select';
					var $content = $trigger.find('.lazy-select2-trigger-value');
					var isCtColumn = $select.closest('td').hasClass('state-lab-col-ct');
					if (!$content.length) {
						$content = $trigger;
					}

					if (isMulti) {
						if (!selectedTexts.length) {
							$content.text(placeholder);
							$content.addClass('is-placeholder');
						} else {
							if (isCtColumn) {
								var tagsHtml = '';
								for (var i = 0; i < selectedTexts.length; i++) {
									tagsHtml += '<span class="lazy-select2-tag">' + $('<div>').text(selectedTexts[i]).html() + '</span>';
								}
								$content.html(tagsHtml);
							} else {
								var multiSummary = selectedTexts[0];
								if (selectedTexts.length > 1) {
									multiSummary += ' +' + (selectedTexts.length - 1);
								}
								$content.text(multiSummary);
							}
							$content.removeClass('is-placeholder');
						}
					} else {
						var singleText = isRowStateSelect ? String($select.val() || '') : (selectedTexts[0] || '');
						singleText = singleText || placeholder;
						$content.text(singleText);
						$content.toggleClass('is-placeholder', !selectedTexts.length);
					}

					$trigger.attr('title', selectedTexts.length ? selectedTexts.join(', ') : placeholder);
					$trigger.prop('disabled', $select.prop('disabled'));
					$trigger.toggleClass('is-disabled', $select.prop('disabled'));
				}
				return '';
			}

			if (action === 'destroy') {
				if (isActive) {
					$select.off('.lazyDestroy');
					try {
						$select.select2('destroy');
					} catch (e) {
						console.log(e);
					}
				}
				if ($trigger.length) {
					$trigger.show();
				}
				handle_lazy_select($select, 'sync');
				return '';
			}

			if (action === 'open') {
				if (isActive) {
					if (shouldOpen) {
						setTimeout(function() {
							try {
								$select.select2('open');
							} catch (e) {
								console.log(e);
							}
						}, 0);
					}
					return '';
				}

				if (isMulti) {
					var selectedMainTid = String($('select[data-k="tid"]', $select.closest('tr.item')).val() || '');
					if (!selectedMainTid) {
						handle_lazy_select($select, 'sync');
						return '';
					}
				}
				if ($trigger.length) {
					$trigger.hide();
				}

				try {
					var select2Options = isMulti ? conditionSelect2Options : get_single_select2_options($select);
					$select.select2(select2Options);
					$select.one('select2:close.lazyDestroy', function() {
						setTimeout(function() {
							handle_lazy_select($select, 'destroy');
						}, 0);
					});
				} catch (e) {
					console.log(e);
					if ($trigger.length) {
						$trigger.show();
					}
					return '';
				}

				if (shouldOpen) {
					setTimeout(function() {
						try {
							$select.select2('open');
						} catch (e) {
							console.log(e);
						}
					}, 0);
				}
			}

			return '';
		}

		function add_item_row($id, $item) {

			if (!$item['status']) $item['status'] = 0;
			if (!$item['btype']) $item['btype'] = singleBtypeKey || Object.keys($btypes)[0] || 'pcr';

			let col_status = '';
			if ($item['status'] == "connected") {
				col_status = `
					<span data-slot="badge" class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden [a&]:hover:bg-primary/90 bg-green-100 text-green-800 border-green-200">
						${$all_status[$item['status']]} 
					</span>
				`;
			} else {
				col_status = `
					<span data-slot="badge" class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent bg-secondary text-secondary-foreground [a&amp;]:hover:bg-secondary/90">
						${$all_status[$item['status']]} 
					</span>
				`;
			}

			let $html = `

						<tr data-slot="table-row" class="item hover:bg-muted/50 data-[state=selected]:bg-muted border-b transition-colors">
							${showTypeColumn ? `
								<td data-slot="table-cell" class="state-lab-col-type p-2 align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px]">
									<div class="lazy-select2-wrap is-single">
										<button type="button" class="lazy-select2-trigger" title="Select type">
											<span class="lazy-select2-trigger-value is-placeholder">Select type</span>
										</button>
										<select class="border-input text-gray-600 s_select3 lazy-select2-source" style="width:100%;" data-k="btype" data-placeholder="Select type" name="options[${$id}][btype]">${show_btype_options($item['btype'])}</select>
									</div>
								</td>
							` : ''}

							<td data-slot="table-cell" class="state-lab-col-test p-2 align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px]">
								${!showTypeColumn ? `<input type="hidden" data-k="btype" data-label="${$('<div>').text($btypes[$item['btype']] || singleBtypeLabel || $item['btype']).html()}" name="options[${$id}][btype]" value="${$('<div>').text($item['btype']).html()}" />` : ''}
								<div class="lazy-select2-wrap is-single">
									<button type="button" class="lazy-select2-trigger" title="Select">
										<span class="lazy-select2-trigger-value is-placeholder">Select</span>
									</button>
									<select data-k="tid" class="s_select3 lazy-select2-source" style="width:100%;" data-placeholder="Select" name="options[${$id}][tid]">${show_test_options($item['btype'], $item['tid'])}</select>
								</div>
							</td>

							<td data-slot="table-cell" class="state-lab-col-panel p-2 align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px]">
								<span data-k="panel_name" class="text-gray-400"></span>
							</td>

							<td data-slot="table-cell" class="state-lab-col-condition p-2 align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px]">
								<div class="lazy-select2-wrap is-single">
									<button type="button" class="lazy-select2-trigger" title="Select condition">
										<span class="lazy-select2-trigger-value is-placeholder">Select condition</span>
									</button>
									<select class="s_select3 lazy-select2-source" style="width:100%;" data-k="cond_pos_neg" data-placeholder="Select condition" name="options[${$id}][cond_pos_neg]">${show_cond_options($item['cond_pos_neg'])}</select>
								</div>
							</td>

							<td data-slot="table-cell" class="state-lab-col-ct p-2 align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] bg-red-50/30">
								<div class="lazy-select2-wrap is-multi">
									<button type="button" class="lazy-select2-trigger" title="Select tests">
										<span class="lazy-select2-trigger-value is-placeholder">Select tests</span>
									</button>
									<select class="s_select2 lazy-select2-source" multiple data-k="cond_only_tids" data-placeholder="Select tests" name="options[${$id}][cond_only_tids][]" >${show_test_options($item['btype'], $item['cond_only_tids'], true)}</select>
								</div>
							</td>

							<td data-slot="table-cell" class="state-lab-col-ct-condition p-2 align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] bg-red-50/30">
								<div class="lazy-select2-wrap is-single">
									<button type="button" class="lazy-select2-trigger" title="Select condition">
										<span class="lazy-select2-trigger-value is-placeholder">Select condition</span>
									</button>
									<select class="s_select3 lazy-select2-source" style="width:100%;" data-k="cond_only_pos_neg" data-placeholder="Select condition" name="options[${$id}][cond_only_pos_neg]">${show_cond_options($item['cond_only_pos_neg'])}</select>
								</div>
							</td>

							<td data-slot="table-cell" class="state-lab-col-send-ct p-2 align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] bg-red-50/30">
								<input type="checkbox" name="options[${$id}][send_cond_tests]" value="1" ${$item['send_cond_tests']?'checked':''} />
							</td>

							<td data-slot="table-cell" class="state-lab-col-state p-2 align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px]">
								<div class="lazy-select2-wrap is-single">
									<button type="button" class="lazy-select2-trigger" title="Select state">
										<span class="lazy-select2-trigger-value is-placeholder">Select state</span>
									</button>
									<select class="s_select3 lazy-select2-source" style="width:100%;" data-placeholder="Select state" name="options[${$id}][state]">${show_state_options($item['state'])}</select>
								</div>
							</td>

							<td data-slot="table-cell" class="state-lab-col-status p-2 align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px]">
								<div class="state-lab-status-actions">
									<div class="state-lab-status-badge">
										${col_status}
									</div>
	
									<span class="clone cursor-pointer btn_extension inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:text-accent-foreground dark:hover:bg-accent/50 rounded-md gap-1.5 has-[>svg]:px-2.5 h-8 w-8 p-0 hover:bg-gray-100">
										<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-copy h-4 w-4" aria-hidden="true"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"></rect><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"></path></svg>
									</span>

									<span class="removerow cursor-pointer btn_extension inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 rounded-md gap-1.5 has-[>svg]:px-2.5 h-8 w-8 p-0 hover:bg-red-50 hover:text-red-600">
										<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash2 lucide-trash-2 h-4 w-4" aria-hidden="true"><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path><path d="M3 6h18"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
									</span>  

								</div>
							</td>

						</tr>			
			
			`;

			var $html_object = $($html);
			$('.activet', $html_object).removeClass('activet');
			$('#listtests').append($html_object);
			update_condition_test_select($html_object);
			sync_row_meta($html_object);
			$('.lazy-select2-source', $html_object).each(function() {
				handle_lazy_select($(this), 'sync');
			});

		}

		function show_btype_options($vl) {
			let $opts = '';
			for (i in $btypes) {
				$selected = i == $vl ? 'selected' : '';
				$opts += '<option value="' + i + '" ' + $selected + '>' + $btypes[i] + '</option>';
			}
			return $opts;
		}

		function get_row_btype($row) {
			var $field = $('[data-k="btype"]', $row).first();
			if ($field.length) {
				return String($field.val() || '');
			}
			return String(singleBtypeKey || '');
		}

		function get_row_btype_label($row) {
			var $field = $('[data-k="btype"]', $row).first();
			if ($field.is('select')) {
				return $('option:selected', $field).text();
			}
			if ($field.length) {
				return String($field.data('label') || $field.val() || '');
			}
			return String(singleBtypeLabel || singleBtypeKey || '');
		}

		function get_select2_dropdown_group($select) {
			if (!$select || !$select.length) {
				return 'default';
			}

			var $cell = $select.closest('td');
			var isStateFilter = $select.is('#state_lab_filter_state') || String($select.data('filter-key') || '') === 'state';
			var isStateColumn = $cell.hasClass('state-lab-col-state') || $select.is('select[name$="[state]"]');
			if (isStateFilter || isStateColumn) {
				return 'state';
			}
			if ($cell.hasClass('state-lab-col-type') || $cell.hasClass('state-lab-col-condition') || $cell.hasClass('state-lab-col-ct-condition')) {
				return 'small';
			}
			if ($cell.hasClass('state-lab-col-test') || $cell.hasClass('state-lab-col-ct')) {
				return 'large';
			}
			return 'default';
		}

		function get_single_select2_options($select) {
			var group = get_select2_dropdown_group($select);
			if (group === 'small') return singleSelect2SmallOptions;
			if (group === 'large') return singleSelect2LargeOptions;
			if (group === 'state') return singleSelect2StateOptions;
			return singleSelect2Options;
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

		function update_condition_test_select($row) {
			const $mainTestSelect = $('select[data-k="tid"]', $row);
			const $conditionSelect = $('select[data-k="cond_only_tids"]', $row);
			const selectedMainTid = String($mainTestSelect.val() || '');
			const rawSelectedConditionValues = $conditionSelect.val();
			const selectedMainTestMeta = testMetaById[selectedMainTid];
			$('span[data-k="panel_name"]', $row).text(selectedMainTestMeta ? selectedMainTestMeta.groupName : '');
			const selectedConditionValues = Array.isArray(rawSelectedConditionValues) ?
				rawSelectedConditionValues.map(function(item) {
					return String(item);
				}) :
				(rawSelectedConditionValues ? [String(rawSelectedConditionValues)] : []);

			if (!selectedMainTid || !selectedMainTestMeta) {
				$conditionSelect.html('');
				$conditionSelect.val(null);
				$conditionSelect.prop('disabled', true);
				handle_lazy_select($conditionSelect, 'sync');
				return;
			}

			const matchedGroup = $groups[selectedMainTestMeta.groupId];
			if (!matchedGroup) {
				$conditionSelect.html('');
				$conditionSelect.val(null);
				$conditionSelect.prop('disabled', true);
				handle_lazy_select($conditionSelect, 'sync');
				return;
			}

			let conditionOptions = '';
			for (const testIndex in matchedGroup['tests']) {
				const test = matchedGroup['tests'][testIndex];
				const testId = String(test['te_ID']);

				if (testId === selectedMainTid) {
					continue;
				}

				const $selected = selectedConditionValues.includes(testId) ? 'selected' : '';
				conditionOptions += '<option value="' + test['te_ID'] + '" ' + $selected + '>' + test['te_name'] + ' (' + test['te_loinc_code'] + ')</option>';
			}

			$conditionSelect.html(conditionOptions);
			$conditionSelect.prop('disabled', false);
			$conditionSelect.val(selectedConditionValues);
			handle_lazy_select($conditionSelect, 'sync');
		}

		function normalize_main_test_name($name) {
			return $.trim(String($name || '')
					.replace(/\s*\([^)]*\)\s*$/, '')
					.replace(/\s+/g, ' '))
				.toLowerCase();
		}

		function get_main_test_name($row) {
			var selectedText = $('select[data-k="tid"] option:selected', $row).text() || '';
			selectedText = normalize_main_test_name(selectedText);
			if (selectedText !== '') {
				return selectedText;
			}

			var selectedTid = String($('select[data-k="tid"]', $row).val() || '');
			return testMetaById[selectedTid] ? normalize_main_test_name(testMetaById[selectedTid].name) : '';
		}

		function sync_row_meta($row) {
			var selectedTid = String($('select[data-k="tid"]', $row).val() || '');
			var selectedMeta = testMetaById[selectedTid];
			$row.attr('data-main-test-key', get_main_test_name($row));
			$row.attr('data-state-value', String($('select[name$="[state]"]', $row).val() || ''));
			$row.attr('data-panel-id', selectedMeta ? selectedMeta.groupId : '');
			$row.attr('data-panel-name', selectedMeta ? selectedMeta.groupName : '');
			$row.attr('data-test-id', selectedTid);
		}

		function populate_filter_dropdowns() {
			var currentState = String($('#state_lab_filter_state').val() || FILTER_ALL_STATE);
			var currentPanel = String($('#state_lab_filter_panel').val() || FILTER_ALL_PANEL);
			var currentTest = String($('#state_lab_filter_test').val() || FILTER_ALL_TEST);
			var stateOptions = ['<option value="' + FILTER_ALL_STATE + '">All States</option>'];
			var panelOptions = ['<option value="' + FILTER_ALL_PANEL + '">All Panels</option>'];
			var testOptions = ['<option value="' + FILTER_ALL_TEST + '">All Tests</option>'];
			var stateItems = [];
			var panelItems = [];
			var testItems = [];
			var seenStates = {};
			var seenPanels = {};
			var seenTests = {};

			$('#listtests tr.item').each(function() {
				var $row = $(this);
				sync_row_meta($row);

				var stateValue = String($row.attr('data-state-value') || '');
				var panelId = String($row.attr('data-panel-id') || '');
				var panelName = String($row.attr('data-panel-name') || '');
				var testId = String($row.attr('data-test-id') || '');
				var testLabel = $.trim($('select[data-k="tid"] option:selected', $row).text());

				if (stateValue && !seenStates[stateValue]) {
					seenStates[stateValue] = true;
					stateItems.push({
						value: stateValue,
						label: stateValue + ' - ' + ($all_states[stateValue] || stateValue)
					});
				}

				if (panelId && !seenPanels[panelId]) {
					seenPanels[panelId] = true;
					panelItems.push({
						value: panelId,
						label: panelName
					});
				}

				if (testId && !seenTests[testId]) {
					seenTests[testId] = true;
					testItems.push({
						value: testId,
						label: testLabel
					});
				}
			});

			stateItems.sort(function(a, b) {
				return String(a.value).localeCompare(String(b.value), undefined, {
					sensitivity: 'base'
				});
			});

			panelItems.sort(function(a, b) {
				return String(a.label).localeCompare(String(b.label), undefined, {
					sensitivity: 'base'
				});
			});

			testItems.sort(function(a, b) {
				return String(a.label).localeCompare(String(b.label), undefined, {
					sensitivity: 'base'
				});
			});

			stateItems.forEach(function(state) {
				stateOptions.push('<option value="' + state.value + '">' + state.label + '</option>');
			});

			panelItems.forEach(function(panel) {
				panelOptions.push('<option value="' + panel.value + '">' + panel.label + '</option>');
			});

			testItems.forEach(function(test) {
				testOptions.push('<option value="' + test.value + '">' + test.label + '</option>');
			});

			$('#state_lab_filter_state').html(stateOptions.join(''));
			$('#state_lab_filter_panel').html(panelOptions.join(''));
			$('#state_lab_filter_test').html(testOptions.join(''));

			var hasCurrentState = stateItems.some(function(item) {
				return item.value === currentState;
			});
			var hasCurrentPanel = panelItems.some(function(item) {
				return item.value === currentPanel;
			});
			var hasCurrentTest = testItems.some(function(item) {
				return item.value === currentTest;
			});
			var isCurrentStateAll = currentState === FILTER_ALL_STATE;

			if (!stateFilterAutoInitialized && stateItems.length >= 2) {
				$('#state_lab_filter_state').val(hasCurrentState && !isCurrentStateAll ? currentState : stateItems[0].value);
				stateFilterAutoInitialized = true;
			} else {
				$('#state_lab_filter_state').val(hasCurrentState ? currentState : FILTER_ALL_STATE);
			}

			$('#state_lab_filter_panel').val(hasCurrentPanel ? currentPanel : FILTER_ALL_PANEL);
			$('#state_lab_filter_test').val(hasCurrentTest ? currentTest : FILTER_ALL_TEST);

			$('.state-lab-filter').each(function() {
				handle_lazy_select($(this), 'sync');
			});

			$('#count_items').text($('#listtests tr.item:visible').length + ' item' + ($('#listtests tr.item:visible').length !== 1 ? 's' : ''));
		}

		function apply_table_filters() {
			var stateFilter = String($('#state_lab_filter_state').val() || FILTER_ALL_STATE);
			var panelFilter = String($('#state_lab_filter_panel').val() || FILTER_ALL_PANEL);
			var testFilter = String($('#state_lab_filter_test').val() || FILTER_ALL_TEST);

			if (stateFilter === FILTER_ALL_STATE) {
				stateFilter = '';
			}
			if (panelFilter === FILTER_ALL_PANEL) {
				panelFilter = '';
			}
			if (testFilter === FILTER_ALL_TEST) {
				testFilter = '';
			}

			$('#listtests tr.item').each(function() {
				var $row = $(this);
				sync_row_meta($row);
				var isVisible = (!stateFilter || String($row.attr('data-state-value') || '') === stateFilter) &&
					(!panelFilter || String($row.attr('data-panel-id') || '') === panelFilter) &&
					(!testFilter || String($row.attr('data-test-id') || '') === testFilter);
				$row.toggle(isVisible);
			});

			$('#count_items').text($('#listtests tr.item:visible').length + ' item' + ($('#listtests tr.item:visible').length !== 1 ? 's' : ''));
		}

		var $stateLabForm = $('#state_lab_frm');
		var stateFilterAutoInitialized = false;
		var stateLabHasUnsavedChanges = false;
		var stateLabAllowLeave = false;

		$stateLabForm.on('click', '.addrow', function() {
				$lastid++;
				add_item_row('k' + $lastid, {});
				populate_filter_dropdowns();
				stateLabHasUnsavedChanges = true;
				return false;
			}).on('mousedown touchstart', 'select.s_select3', function(e) {
				var $select = $(this);
				if (!$select.hasClass('select2-hidden-accessible')) {
					handle_lazy_select($select, 'store');
					e.preventDefault();
					handle_lazy_select($select, 'open', true);
				}
			}).on('focus', 'select.s_select3', function() {
				handle_lazy_select($(this), 'store');
			}).on('keydown', 'select.s_select3', function(e) {
				var $select = $(this);
				if ($select.hasClass('select2-hidden-accessible')) {
					return;
				}
				if (['Enter', ' ', 'ArrowDown', 'ArrowUp'].indexOf(e.key) > -1) {
					handle_lazy_select($select, 'store');
					e.preventDefault();
					handle_lazy_select($select, 'open', true);
				}
			}).on('click', '.lazy-select2-trigger', function(e) {
				e.preventDefault();
				var $select = $(this).siblings('select.lazy-select2-source');
				handle_lazy_select($select, 'store');
				handle_lazy_select($select, 'open', true);
			}).on('keydown', '.lazy-select2-trigger', function(e) {
				if (['Enter', ' ', 'ArrowDown', 'ArrowUp'].indexOf(e.key) === -1) {
					return;
				}
				e.preventDefault();
				var $select = $(this).siblings('select.lazy-select2-source');
				handle_lazy_select($select, 'store');
				handle_lazy_select($select, 'open', true);
			}).on('click', '.removerow', async function() {
				var $pr = $(this).parents('tr.item');
				var mainTid = String($('select[data-k="tid"]', $pr).val() || '');
				var state = String($('select[name$="[state]"]', $pr).val() || '');
				var mainTestMeta = testMetaById[mainTid];
				var mainTestName = $.trim($('select[data-k="tid"] option:selected', $pr).text().replace(/\s*\([^)]*\)\s*$/, '')) || (mainTestMeta ? mainTestMeta.name : 'this test');
				sync_row_meta($pr);
				var mainTestKey = $pr.attr('data-main-test-key') || '';
				var $sameTestRows = $('#listtests tr.item').filter(function() {
					var $row = $(this);
					sync_row_meta($row);
					return ($row.attr('data-state-value') || '') === state && ($row.attr('data-main-test-key') || '') === mainTestKey;
				});
				var canDeleteAll = $sameTestRows.length > 1;

				if (window.Swal && typeof Swal.fire === 'function') {
					var result = await Swal.fire({
						title: 'Delete "' + mainTestName + '"?',
						text: canDeleteAll ?
							'Delete removes only this row. Delete All removes all rows for this test in this state.' : 'This will delete just this row.',
						icon: 'question',
						showCancelButton: true,
						showDenyButton: canDeleteAll,
						confirmButtonText: 'Delete',
						denyButtonText: 'Delete All',
						cancelButtonText: 'Cancel'
					});

					if (result.isConfirmed) {
						$pr.remove();
						populate_filter_dropdowns();
						stateLabHasUnsavedChanges = true;
					} else if (result.isDenied) {
						if (canDeleteAll) {
							$sameTestRows.remove();
							populate_filter_dropdowns();
							stateLabHasUnsavedChanges = true;
						} else {
							await Swal.fire({
								title: 'No matching rows',
								text: 'There are no other rows with the same main test name in this State.',
								icon: 'info',
								confirmButtonText: 'OK'
							});
						}
					}
					return;
				}

				if (confirm('Delete just this row "' + mainTestName + '"?')) {
					$pr.remove();
					populate_filter_dropdowns();
					stateLabHasUnsavedChanges = true;
				} else if (canDeleteAll && confirm('Delete all rows with main test "' + mainTestName + '" in the same State?')) {
					if (canDeleteAll) {
						$sameTestRows.remove();
						populate_filter_dropdowns();
						stateLabHasUnsavedChanges = true;
					}
				}

			}).on('change', 'select[data-k="btype"]', function() {
				var $pr = $(this).parents('tr.item');
				var $testSelect = $('select[data-k="tid"]', $pr);
				let $opts = show_test_options($(this).val(), '');
				$testSelect.html($opts).val('');
				$testSelect.data('previous-tid', '');
				handle_lazy_select($testSelect, 'sync');
				update_condition_test_select($pr);
				sync_row_meta($pr);
				populate_filter_dropdowns();
			}).on('change', 'select[data-k="tid"]', async function() {
				var $select = $(this);
				var $pr = $select.parents('tr.item');
				var previousTid = String($select.data('previous-tid') || '');
				var selectedTid = String($select.val() || '');
				var selectedMeta = testMetaById[selectedTid];
				if (previousTid === '' && selectedMeta) {
					var sameNameTests = testsByNameKey[selectedMeta.normalizedName] || [];
					var selectedBtype = get_row_btype($pr);
					var seenGroups = {};
					var testsInOtherPanels = [];

					for (const idx in sameNameTests) {
						var sameNameTest = sameNameTests[idx];
						if (sameNameTest.btype !== selectedBtype || seenGroups[sameNameTest.groupId]) {
							continue;
						}
						seenGroups[sameNameTest.groupId] = true;
						testsInOtherPanels.push(sameNameTest);
					}

					if (testsInOtherPanels.length > 1) {
						if (window.Swal && typeof Swal.fire === 'function') {
							var addAllResult = await Swal.fire({
								title: 'Add "' + selectedMeta.name + '"?',
								text: 'Would you like to add "' + selectedMeta.name + '" from all panels it appears in?',
								icon: 'question',
								showCancelButton: true,
								confirmButtonText: 'Yes',
								cancelButtonText: 'No'
							});

							if (addAllResult.isConfirmed) {
								var baseItem = {
									btype: selectedBtype,
									cond_pos_neg: $('select[data-k="cond_pos_neg"]', $pr).val(),
									cond_only_tids: [],
									cond_only_pos_neg: $('select[data-k="cond_only_pos_neg"]', $pr).val(),
									send_cond_tests: $('input[name$="[send_cond_tests]"]', $pr).is(':checked') ? 1 : 0,
									state: $('select[name$="[state]"]', $pr).val(),
									status: 0
								};

								for (const idx in testsInOtherPanels) {
									var panelTest = testsInOtherPanels[idx];
									if (panelTest.tid === selectedTid) {
										continue;
									}
									$lastid++;
									add_item_row('k' + $lastid, Object.assign({}, baseItem, {
										tid: panelTest.tid
									}));
								}
							}
						} else if (confirm('Would you like to add "' + selectedMeta.name + '" from all panels it appears in?')) {
							var baseItem = {
								btype: selectedBtype,
								cond_pos_neg: $('select[data-k="cond_pos_neg"]', $pr).val(),
								cond_only_tids: [],
								cond_only_pos_neg: $('select[data-k="cond_only_pos_neg"]', $pr).val(),
								send_cond_tests: $('input[name$="[send_cond_tests]"]', $pr).is(':checked') ? 1 : 0,
								state: $('select[name$="[state]"]', $pr).val(),
								status: 0
							};

							for (const idx in testsInOtherPanels) {
								var panelTest = testsInOtherPanels[idx];
								if (panelTest.tid === selectedTid) {
									continue;
								}
								$lastid++;
								add_item_row('k' + $lastid, Object.assign({}, baseItem, {
									tid: panelTest.tid
								}));
							}
						}
					}
				}

				$select.data('previous-tid', selectedTid);
				update_condition_test_select($pr);
				sync_row_meta($pr);
				populate_filter_dropdowns();
			}).on('change', 'select[name$="[state]"]', function() {
				sync_row_meta($(this).parents('tr.item'));
				populate_filter_dropdowns();
			})
			.on('click', '.clone', function() {
				var $pr = $(this).parents('tr.item');
				var $opts = {};
				$('input,select', $pr).each(function() {
					var $k = $(this).data('k');
					var $vl = $(this).val();
					$opts[$k] = $vl;
				});
				$lastid++;
				add_item_row('k' + $lastid, $opts);
				populate_filter_dropdowns();
				stateLabHasUnsavedChanges = true;
			})

		for (i in $options) {
			add_item_row(i, $options[i]);
		}

		populate_filter_dropdowns();
		apply_table_filters();
		stateLabHasUnsavedChanges = false;

		$('.loading').hide();
		$('#actions_btn').show().removeClass('hidden');

		$stateLabForm.on('change input', '#listtests input, #listtests select', function() {
			stateLabHasUnsavedChanges = true;
		});

		$('.state-lab-filter').on('change', function() {
			if (this.id === 'state_lab_filter_test' && String($(this).val() || FILTER_ALL_TEST) !== FILTER_ALL_TEST) {
				$('#state_lab_filter_state').val(FILTER_ALL_STATE);
				$('#state_lab_filter_panel').val(FILTER_ALL_PANEL);
				handle_lazy_select($('#state_lab_filter_state'), 'sync');
				handle_lazy_select($('#state_lab_filter_panel'), 'sync');
			}
			handle_lazy_select($(this), 'sync');
			apply_table_filters();
		});

		$('#state_lab_filter_reset').on('click', function() {
			$('#state_lab_filter_state').val(FILTER_ALL_STATE);
			$('#state_lab_filter_panel').val(FILTER_ALL_PANEL);
			$('#state_lab_filter_test').val(FILTER_ALL_TEST);
			$('.state-lab-filter').each(function() {
				handle_lazy_select($(this), 'sync');
			});
			apply_table_filters();
		});

		$("#btn_export").on("click", function() {
			var csvHeader = ['Test', 'Panel', 'Condition', 'Conditional Tests (CT)', 'CT Condition', 'Send CT', 'State', 'Status'];
			if (showTypeColumn) {
				csvHeader.unshift('Type');
			}
			var csvRows = [csvHeader];

			$('#listtests tr.item').each(function() {
				var $row = $(this);
				var rowData = [
					$('select[data-k="tid"] option:selected', $row).text(),
					$('span[data-k="panel_name"]', $row).text(),
					$('select[data-k="cond_pos_neg"] option:selected', $row).text(),
					$('select[data-k="cond_only_tids"] option:selected', $row).map(function() {
						return $(this).text();
					}).get().join('; '),
					$('select[data-k="cond_only_pos_neg"] option:selected', $row).text(),
					$('input[name$="[send_cond_tests]"]', $row).is(':checked') ? 'Yes' : 'No',
					$('select[name$="[state]"] option:selected', $row).text(),
					$.trim($('span[data-slot="badge"]', $row).text())
				];
				if (showTypeColumn) {
					rowData.unshift(get_row_btype_label($row));
				}

				csvRows.push(rowData);
			});

			var csvContent = "\uFEFF" + csvRows.map(function(row) {
				return row.map(function(cell) {
					var value = String(cell || '').replace(/"/g, '""');
					return '"' + value + '"';
				}).join(',');
			}).join("\n");
			var blob = new Blob([csvContent], {
				type: 'text/csv;charset=utf-8;'
			});
			var link = document.createElement('a');
			var date = new Date();
			var fileName = 'report_to_state_' +
				date.getFullYear() +
				String(date.getMonth() + 1).padStart(2, '0') +
				String(date.getDate()).padStart(2, '0') + '_' +
				String(date.getHours()).padStart(2, '0') +
				String(date.getMinutes()).padStart(2, '0') +
				String(date.getSeconds()).padStart(2, '0') +
				'.csv';

			link.href = URL.createObjectURL(blob);
			link.download = fileName;
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
			URL.revokeObjectURL(link.href);
		});

		$("#btn_export_json").on("click", function() {
			var jsonRows = [];

			$('#listtests tr.item').each(function() {
				var $row = $(this);
				jsonRows.push({
					btype: get_row_btype($row),
					tid: String($('select[data-k="tid"]', $row).val() || ''),
					cond_pos_neg: String($('select[data-k="cond_pos_neg"]', $row).val() || ''),
					cond_only_tids: $('select[data-k="cond_only_tids"]', $row).val() || [],
					cond_only_pos_neg: String($('select[data-k="cond_only_pos_neg"]', $row).val() || ''),
					send_cond_tests: $('input[name$="[send_cond_tests]"]', $row).is(':checked') ? 1 : 0,
					state: String($('select[name$="[state]"]', $row).val() || ''),
					status: $.trim($('span[data-slot="badge"]', $row).text())
				});
			});

			var blob = new Blob([JSON.stringify(jsonRows, null, 2)], {
				type: 'application/json;charset=utf-8;'
			});
			var link = document.createElement('a');
			var date = new Date();
			var fileName = 'report_to_state_' +
				date.getFullYear() +
				String(date.getMonth() + 1).padStart(2, '0') +
				String(date.getDate()).padStart(2, '0') + '_' +
				String(date.getHours()).padStart(2, '0') +
				String(date.getMinutes()).padStart(2, '0') +
				String(date.getSeconds()).padStart(2, '0') +
				'.json';

			link.href = URL.createObjectURL(blob);
			link.download = fileName;
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
			URL.revokeObjectURL(link.href);
		});


		$('table th').click(function() {
			var table = $(this).parents('table').eq(0);
			var rows = table.find('tbody tr').toArray();
			var index = $(this).index();
			var asc = !$(this).data('asc');

			$('table th').find(".lucide-arrow-up-down").addClass("inline").show();
			$('table th').find(".lucide-arrow-up").removeClass("inline").hide();
			$('table th').find(".lucide-arrow-down").removeClass("inline").hide();
			$(this).find(".lucide-arrow-up-down").removeClass("inline").hide();


			rows.sort(function(a, b) {
				var cellA = $(a).children('td').eq(index);
				var cellB = $(b).children('td').eq(index);
				var $selectA = $('select', cellA).first();
				var $selectB = $('select', cellB).first();
				var A = $selectA.length ? handle_lazy_select($selectA, 'text') : $.trim(cellA.text());
				var B = $selectB.length ? handle_lazy_select($selectB, 'text') : $.trim(cellB.text());

				return asc ?
					B.localeCompare(A, undefined, {
						sensitivity: 'base'
					}) :
					A.localeCompare(B, undefined, {
						sensitivity: 'base'
					});
			});

			$.each(rows, function(i, row) {
				table.children('tbody').append(row);
			});

			if (asc) {
				$(this).find(".lucide-arrow-down").addClass('inline').show();
			} else {
				$(this).find(".lucide-arrow-up").addClass('inline').show();
			}

			$(this).data('asc', asc);
		});

		$("#btn_save").on("click", function(e) {
			e.preventDefault();

			const formData = new FormData(document.getElementById('state_lab_frm'));
			formData.append('trajax', 'save_state_lab');
			Swal.fire({
				title: 'Report to State',
				text: 'Saving...Please wait...',
				allowOutsideClick: false,
				allowEscapeKey: false,
				didOpen: () => {
					Swal.showLoading();
				}
			});

			$.ajax({
				url: stateLabAjaxUrl,
				type: "POST",
				data: formData,
				contentType: false, // BẮT BUỘC
				processData: false, // BẮT BUỘC
				success(res) {
					if (!res.success) {
						Swal.fire({
							title: "Report to State",
							html: res.data || 'Save failed',
							icon: "error"
						});
						return;
					}
					Swal.fire({
						title: "Report to State",
						html: res.data,
						icon: "success"
					});
					stateLabHasUnsavedChanges = false;
				},
				error() {
					Swal.fire({
						title: "Report to State",
						text: "Save failed. Please try again.",
						icon: "error"
					});
				}
			});
		});

		$('#btn_history').on('click', function() {
			Swal.fire({
				title: 'History',
				text: 'Loading backups...Please wait...',
				allowOutsideClick: false,
				allowEscapeKey: false,
				didOpen: () => {
					Swal.showLoading();
				}
			});

			$.ajax({
				url: stateLabAjaxUrl,
				type: 'POST',
				data: {
					trajax: 'list_state_lab_backups'
				},
				success(res) {
					if (!res.success) {
						Swal.fire({
							title: 'History',
							html: res.data || 'Unable to load backups.',
							icon: 'error'
						});
						return;
					}

					var backups = res.data && res.data.backups ? res.data.backups : [];
					var html = '<div class="state-lab-history-list">';

					if (!backups.length) {
						html += '<div class="state-lab-history-meta">No backups found.</div>';
					} else {
						backups.forEach(function(backup) {
							var fileName = $('<div>').text(String(backup.file || '')).html();
							var created = $('<div>').text(String(backup.created || '')).html();
							var userName = $('<div>').text(String(backup.user || 'Unknown')).html();
							var metaParts = [];
							if (userName !== '' && userName !== 'Unknown') {
								metaParts.push('<span class="state-lab-history-user">' + userName + '</span>');
							}
							html += '<div class="state-lab-history-item">' +
								'<div>' +
								'<div class="state-lab-history-name">' + created + '</div>' +
								'<div class="state-lab-history-meta">' + metaParts.join(' • ') + '</div>' +
								'</div>' +
								'<button type="button" class="state-lab-restore-backup cursor-pointer inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md text-xs font-medium transition-all border bg-background text-foreground hover:bg-accent hover:text-accent-foreground h-8 px-3 py-1.5" data-file="' + fileName + '" data-created="' + created + '">' +
								'<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-undo-2 h-3.5 w-3.5" aria-hidden="true"><path d="M9 14 4 9l5-5"></path><path d="M4 9h11a4 4 0 1 1 0 8h-1"></path></svg>' +
								'Restore</button>' +
								'<button type="button" class="state-lab-delete-backup cursor-pointer inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md text-xs font-medium transition-all border border-red-200 bg-white text-red-600 hover:bg-red-50 h-8 px-3 py-1.5" data-file="' + fileName + '" data-created="' + created + '">' +
								'<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash2 h-3.5 w-3.5" aria-hidden="true"><path d="M3 6h18"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path></svg>' +
								'Delete</button>' +
								'</div>';
						});
					}

					html += '</div>';

					Swal.fire({
						title: 'History',
						html: html,
						width: 860,
						showConfirmButton: false,
						showCloseButton: true
					});
				},
				error() {
					Swal.fire({
						title: 'History',
						text: 'Unable to load backups.',
						icon: 'error'
					});
				}
			});
		});

		$(document).on('click', '.state-lab-restore-backup', function() {
			var backupFile = String($(this).data('file') || '');
			var backupCreated = String($(this).data('created') || 'this backup');
			if (backupFile === '') {
				return;
			}

			Swal.fire({
				title: 'Restore backup?',
				html: '<div class="state-lab-import-hint">Backup to restore: <strong>' + $('<div>').text(backupCreated).html() + '</strong></div>',
				icon: 'warning',
				showCancelButton: true,
				confirmButtonText: 'Restore',
				cancelButtonText: 'Cancel'
			}).then(function(result) {
				if (!result.isConfirmed) {
					return;
				}

				Swal.fire({
					title: 'History',
					text: 'Restoring backup...Please wait...',
					allowOutsideClick: false,
					allowEscapeKey: false,
					didOpen: () => {
						Swal.showLoading();
					}
				});

				$.ajax({
					url: stateLabAjaxUrl,
					type: 'POST',
					data: {
						trajax: 'restore_state_lab_backup',
						backup_file: backupFile
					},
					success(res) {
						if (!res.success) {
							Swal.fire({
								title: 'History',
								html: res.data || 'Restore failed.',
								icon: 'error'
							});
							return;
						}

						Swal.fire({
							title: 'History',
							html: res.data,
							icon: 'success'
						}).then(function() {
							window.location.reload();
						});
					},
					error() {
						Swal.fire({
							title: 'History',
							text: 'Restore failed.',
							icon: 'error'
						});
					}
				});
			});
		});

		$(document).on('click', '.state-lab-delete-backup', function() {
			var backupFile = String($(this).data('file') || '');
			var backupCreated = String($(this).data('created') || 'this backup');
			if (backupFile === '') {
				return;
			}

			Swal.fire({
				title: 'Delete backup?',
				html: '<div class="state-lab-import-hint">This will permanently delete the backup from <strong>' + $('<div>').text(backupCreated).html() + '</strong> from History. This action cannot be undone.</div>',
				icon: 'warning',
				showCancelButton: true,
				confirmButtonText: 'Delete',
				cancelButtonText: 'Cancel',
				confirmButtonColor: '#dc2626'
			}).then(function(result) {
				if (!result.isConfirmed) {
					return;
				}

				$.ajax({
					url: stateLabAjaxUrl,
					type: 'POST',
					data: {
						trajax: 'delete_state_lab_backup',
						backup_file: backupFile
					},
					success(res) {
						if (!res.success) {
							Swal.fire({
								title: 'History',
								html: res.data || 'Delete failed.',
								icon: 'error'
							});
							return;
						}

						Swal.fire({
							title: 'History',
							html: res.data,
							icon: 'success'
						}).then(function() {
							$('#btn_history').trigger('click');
						});
					},
					error() {
						Swal.fire({
							title: 'History',
							text: 'Delete failed.',
							icon: 'error'
						});
					}
				});
			});
		});

		$('#btn_import').on('click', function() {
			$('#state_lab_import_file').val('');
			$('#state_lab_import_file').trigger('click');
		});

		$('#state_lab_import_file').on('change', function() {
			var input = this;
			var file = input.files && input.files[0] ? input.files[0] : null;
			if (!file) {
				return;
			}

			Swal.fire({
				title: 'Import CSV File?',
				html: '<div class="state-lab-import-hint">Importing this file will replace the current Report to State configuration. The current version will be backed up first and can be restored from History if needed.</div>',
				icon: 'warning',
				showCancelButton: true,
				confirmButtonText: 'Import',
				cancelButtonText: 'Cancel'
			}).then(function(result) {
				if (!result.isConfirmed) {
					$(input).val('');
					return;
				}

				var formData = new FormData();
				formData.append('trajax', 'import_state_lab_csv');
				formData.append('import_file', file);

				Swal.fire({
					title: 'Import Config',
					text: 'Importing...Please wait...',
					allowOutsideClick: false,
					allowEscapeKey: false,
					didOpen: () => {
						Swal.showLoading();
					}
				});

				$.ajax({
					url: stateLabAjaxUrl,
					type: 'POST',
					data: formData,
					contentType: false,
					processData: false,
					success(res) {
						if (!res.success) {
							Swal.fire({
								title: 'Import Failed',
								html: res.data || 'Import failed.',
								icon: 'error'
							});
							return;
						}

						Swal.fire({
							title: 'Import Config',
							html: res.data,
							icon: 'success'
						}).then(function() {
							window.location.reload();
						});
					},
					error() {
						Swal.fire({
							title: 'Import Failed',
							text: 'Import failed.',
							icon: 'error'
						});
					},
					complete() {
						$(input).val('');
					}
				});
			});
		});

		$(window).on('beforeunload', function(e) {
			if (!stateLabHasUnsavedChanges || stateLabAllowLeave) {
				return;
			}
			e.preventDefault();
			e.returnValue = '';
			return '';
		});

		$(document).on('click', 'a[href]', function(e) {
			var $link = $(this);
			var href = String($link.attr('href') || '');
			if (!stateLabHasUnsavedChanges || stateLabAllowLeave || !href || href === '#' || href.indexOf('javascript:') === 0 || $link.attr('target') === '_blank' || $link.attr('download')) {
				return;
			}
			if ($link.closest('.swal2-container').length) {
				return;
			}
			if (this.origin === window.location.origin && this.pathname === window.location.pathname && this.search === window.location.search && (!this.hash || this.hash === window.location.hash)) {
				return;
			}

			e.preventDefault();

			if (window.Swal && typeof Swal.fire === 'function') {
				Swal.fire({
					title: 'Unsaved changes',
					html: '<div class="state-lab-import-hint">You have unsaved changes on Report to State. Leave this page without saving?</div>',
					icon: 'warning',
					showCancelButton: true,
					confirmButtonText: 'Leave page',
					cancelButtonText: 'Stay here'
				}).then(function(result) {
					if (!result.isConfirmed) {
						return;
					}
					stateLabAllowLeave = true;
					window.location.href = href;
				});
				return;
			}

			if (confirm('You have unsaved changes on Report to State. Leave this page without saving?')) {
				stateLabAllowLeave = true;
				window.location.href = href;
			}
		});


	})
</script>