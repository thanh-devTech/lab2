<?php


$act = isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : '';
$lab = $wpdb->get_row("select * from ci_labs where lab_ID = {$current_lab_id} ");
if ($act == 'export_doctors') {

	$filename = str_replace(' ', '-', $lab->lab_name) . '_doctors.csv';
	header('Content-Type: application/csv');
	header('Content-Disposition: attachment; filename=' . $filename);
	header('Pragma: no-cache');

	$f = fopen('php://output', 'w');
	fputcsv($f, ['First Name', 'Last Name', 'NPI']);
	$doctors = $wpdb->get_results("
		SELECT doc_first, doc_last, doc_npi FROM ci_doc doc
		JOIN ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
		JOIN ci_clinic cli ON cli.cID = cd.cli_ID
		GROUP BY doc.doc_ID
	");
	foreach ($doctors as $d) fputcsv($f, [$d->doc_first, $d->doc_last, $d->doc_npi]);
	exit;
}

if ($act == 'export_clinic_doctors') {
	$filename = str_replace(' ', '-', $lab->lab_name) . '_clinics.csv';
	header('Content-Type: application/csv');
	header('Content-Disposition: attachment; filename=' . $filename);
	header('Pragma: no-cache');

	$f = fopen('php://output', 'w');
	fputcsv($f, ['Clinic', 'Doctor First Name', 'Doctor Last Name', 'Doctor NPI']);
	$clinics = $wpdb->get_results("SELECT * FROM ci_clinic ORDER BY cli_clinic ASC");
	foreach ($clinics as $cli) {
		fputcsv($f, [$cli->cli_clinic]);
		$doctors = $wpdb->get_results("
			SELECT doc_first, doc_last, doc_npi FROM ci_doc doc
			JOIN ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
			WHERE cd.cli_ID = {$cli->cID}
		");
		foreach ($doctors as $d) fputcsv($f, ['', $d->doc_first, $d->doc_last, $d->doc_npi]);
	}
	exit;
}

$from_date = !empty($_REQUEST['from'])
	? date('Y-m-d', strtotime($_REQUEST['from']))
	: date('Y-m-d', strtotime('-11 months', current_time('timestamp')));

$to_date = !empty($_REQUEST['to'])
	? date('Y-m-d', strtotime($_REQUEST['to']))
	: date('Y-m-d', current_time('timestamp'));

$POS_CODES = ['POS', 'POH', 'POM', 'POL', 'PVL', 'PVH'];

$pos_neg_vls = [
	''    => '',
	'POS' => 'Detected',
	'NEG' => 'Not-Detected',
	'POL' => 'Detected-Low',
	'POM' => 'Detected-Med',
	'POH' => 'Detected-High',
	'PVL' => 'Detected-Low',
	'PVH' => 'Detected-High',
];

function csv_safe($value)
{
	if ($value === null) return '';
	$value = (string) $value;
	$value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
	$value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
	if (preg_match('/^[=+\-@]/', $value)) $value = "'" . $value;
	return trim($value);
}
if (!function_exists('_pn_extract_testids')) {
	function _pn_extract_testids($row, $all_panels, &$panels_out = null): array
	{
		$testids = [];
		foreach (explode(',', (string) $row->panel_tests_requested) as $t) {
			if (stripos($t, 'GROUP_') !== false) {
				$gid   = str_ireplace('GROUP_', '', $t);
				$group = $all_panels[$gid] ?? null;
				if ($group) {
					if ($group->tg_only_group)
						$testids = array_merge($testids, $group->testids);
					if ($panels_out !== null) $panels_out[] = $group->tg_name;
				}
			} else {
				$testids[] = strtolower($t);
			}
		}
		return $testids;
	}
}

if ($act === 'export_pos_neg') {
	$log_dir  = WP_CONTENT_DIR . '/log/pos_neg';
	$log_url  = WP_CONTENT_URL . '/log/pos_neg';
	if (!file_exists($log_dir)) wp_mkdir_p($log_dir);

	$all_batch_types_str = implode(',', array_map(function ($t) {
		return "'$t'";
	}, tr_pdd_get_batch_types()));

	$all_panels = [];
	$all_tests  = [];
	$panels_rs = $wpdb->get_results("
		SELECT tg_ID, REPLACE(tg_name,'(new)','') AS tg_name, tg_only_group
		FROM ci_test_groups
		WHERE tg_parent = 0 AND tg_show_req = 1 AND tg_type IN ({$all_batch_types_str})
		ORDER BY tg_name ASC
	");

	foreach ($panels_rs as $panel) {
		$tests_rs = $wpdb->get_results("
			SELECT te.te_ID, te.te_name, te.te_abrev, te.te_abrev2
			FROM ci_test_panels te
			JOIN ci_test_groups g ON te.te_group_id = g.tg_ID
			WHERE (te_group_id = {$panel->tg_ID} OR g.tg_parent = {$panel->tg_ID})
			  AND te.te_in_req = 1 AND te.te_is_control = 0
			ORDER BY te.te_name ASC
		");
		foreach ($tests_rs as $t) {
			$all_tests[$t->te_ID]               = $t;
			$all_tests[strtolower($t->te_name)] = $t;
		}
		if ($panel->tg_only_group) {
			$panel->testids = $wpdb->get_col("SELECT te_ID FROM ci_test_panels WHERE te_group_id = {$panel->tg_ID} AND te_in_req = 1 AND te_is_control = 0");
		}
		$all_panels[$panel->tg_ID] = $panel;
	}

	// reflex test IDs
	$reflex_test_ids    = [];
	$groups_with_reflex = [];
	$rgs_rs = $wpdb->get_col("
		SELECT DISTINCT tg_reflex_group_ID FROM ci_test_groups
		WHERE tg_reflex_group_ID IS NOT NULL AND tg_reflex_group_ID != '' AND tg_reflex_group_ID != '0'
	");
	foreach ($rgs_rs as $rg) {
		foreach (array_filter(array_map('trim', explode(',', $rg))) as $gid) {
			$groups_with_reflex[$gid] = true;
		}
	}
	if (!empty($groups_with_reflex)) {
		$ids_str         = implode(',', array_keys($groups_with_reflex));
		$reflex_test_ids = $wpdb->get_col("
			SELECT DISTINCT te_ID FROM ci_test_panels te
			JOIN ci_test_groups g ON te.te_group_id = g.tg_ID
			WHERE (te.te_group_id IN ({$ids_str}) OR g.tg_parent IN ({$ids_str}))
			  AND te.te_in_req = 1 AND te.te_is_control = 0
		");
	}
	$testp_rs = $wpdb->get_results("SELECT * FROM ci_test_panels WHERE te_type IN ({$all_batch_types_str}) AND te_in_req = 1 AND te_is_control = 0");
	foreach ($testp_rs as $r) {
		$r->is_reflex_test              = in_array($r->te_ID, $reflex_test_ids);
		$all_tests[$r->te_ID]           = $r;
		$all_tests[strtolower($r->te_name)] = $r;
	}

	// query rows
	$child_sqls = [];
	foreach (tr_pdd_get_batch_types() as $bt) {
		$batch_tbl   = tr_pdd_get_table_by_btype($bt);
		$report_tbl  = tr_pdd_get_table_report_by_btype($bt);
		$result_tbl  = tr_pdd_get_table_result_by_btype($bt);
		$has_uti_col = $bt === 'pcr' ? 're.has_uti' : 'NULL AS has_uti';
		$where       = trpdd_abx_query_date_range($from_date, $to_date) . " AND re.estatus = 'C'";

		$child_sqls[] = "
			SELECT ba.doc, ba.assen, ba.mid, ba.panel_tests_requested,
			       re.id, re.has_pos, {$has_uti_col},
			       (SELECT GROUP_CONCAT(drug,'|',conc,'|',`pos-neg`)
			        FROM {$result_tbl} WHERE report_id = re.id GROUP BY report_id) AS results,
			       '{$bt}' AS btype
			FROM {$batch_tbl} ba
			JOIN {$report_tbl} re ON re.patient_id = ba.assen AND re.estatus = 'C'
			JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
			WHERE {$where}
			GROUP BY ba.mid
		";
	}

	$union_sql = 'SELECT * FROM (' . implode(' UNION ALL ', $child_sqls) . ') AS ba ORDER BY doc DESC';

	$all_test_names = [];
	$pass1 = $wpdb->get_results($union_sql);

	foreach ($pass1 as $row) {
		$pnv = $pos_neg_vls;
		$testids = _pn_extract_testids($row, $all_panels);
		foreach ($testids as $t) {
			if (!isset($all_tests[$t])) continue;
			$testobj = $all_tests[$t];
			if ($testobj->is_reflex_test && empty($row->has_pos)) {
				if ($row->btype === 'pcr' && empty($row->has_uti)) continue;
				elseif ($row->btype !== 'pcr') continue;
			}
			$name = $testobj->te_name;
			$all_test_names[$name] = true;
		}
	}
	$test_names = array_keys($all_test_names);

	$outfile  = generate_report_filename('RPT', 'PositiveNegative', $to_date, '', 'csv');
	$filepath = $log_dir . '/' . $outfile;
	$f        = fopen($filepath, 'w');

	fwrite($f, "\xEF\xBB\xBF");
	fputcsv($f, array_merge(
		['Date', 'Accession', 'Panel', 'Positive'],
		array_map('csv_safe', $test_names)
	));

	foreach ($pass1 as $row) {
		$pnv = $pos_neg_vls;
		if (in_array($row->btype, ['tox', 'blood'])) {
			$pnv['POS'] = 'Positive';
			$pnv['NEG'] = 'Negative';
		}

		$resultarr = [];
		foreach (explode(',', (string) $row->results) as $re) {
			if (empty($re)) continue;
			$a = explode('|', $re);
			$resultarr[strtolower($a[0])] = ['pos' => $a[2] ?? ''];
		}

		$panels  = [];
		$testids = _pn_extract_testids($row, $all_panels, $panels);

		$posmap = [];
		foreach ($testids as $t) {
			if (!isset($all_tests[$t])) continue;
			$testobj = $all_tests[$t];
			if ($testobj->is_reflex_test && empty($row->has_pos)) {
				if ($row->btype === 'pcr' && empty($row->has_uti)) continue;
				elseif ($row->btype !== 'pcr') continue;
			}
			$drugkey = strtolower($testobj->te_name);
			if (!isset($resultarr[$drugkey]) && !empty($testobj->te_abrev))
				$drugkey = strtolower($testobj->te_abrev);
			if (!isset($resultarr[$drugkey]) && !empty($testobj->te_abrev2))
				$drugkey = strtolower($testobj->te_abrev2);
			if (!isset($resultarr[$drugkey])) continue;

			$pos_neg = $resultarr[$drugkey]['pos'];
			$name    = $testobj->te_name;
			if (!isset($posmap[$name]))
				$posmap[$name] = ['pos_neg' => $pos_neg, 'label' => $pnv[$pos_neg] ?? $pos_neg];
		}

		$is_positive = false;
		foreach ($posmap as $entry) {
			if (in_array($entry['pos_neg'], $POS_CODES, true)) {
				$is_positive = true;
				break;
			}
		}

		$r = [
			csv_safe(date('m/d/Y', strtotime($row->doc))),
			csv_safe($row->assen),
			csv_safe(implode(', ', $panels)),
			$is_positive ? 'Yes' : 'No',
		];
		foreach ($test_names as $tn) {
			$r[] = csv_safe($posmap[$tn]['label'] ?? 'N/A');
		}
		fputcsv($f, $r);
		unset($posmap, $resultarr, $panels, $r);
	}

	fclose($f);
	unset($pass1);
	if (file_exists($filepath)) {
		header('Content-Type: application/csv');
		header('Content-Disposition: attachment; filename="' . $outfile . '"');
		header('Content-Length: ' . filesize($filepath));
		header('Pragma: no-cache');
		readfile($filepath);
		@unlink($filepath);
	}
	exit;
}
?>
<div class="wrap">
	<?php
	if (defined('TRCDBI_PATH') && is_file(TRCDBI_PATH . 'pages/admin_ci_export_data.php')) {
		include(TRCDBI_PATH . 'pages/admin_ci_export_data.php');
	}
	?>
	<h2>Other Exports</h2>
	<ul>
		<li>
			<a href="<?php Tr_Base_Class_V6::link(['trajax' => 'export_doctors']) ?>">Export All Doctors</a>
		</li>
		<li>
			<a href="<?php Tr_Base_Class_V6::link(['trajax' => 'export_clinic_doctors']) ?>">Export Clinics &amp; Doctors</a>
		</li>
		<li>
			<div style="display:inline-flex; align-items:center; gap:8px; flex-wrap:wrap;">
				<label for="pn_from"><strong>From:</strong></label>
				<input type="date" id="pn_from" value="<?php echo esc_attr($from_date); ?>">

				<label for="pn_to"><strong>To:</strong></label>
				<input type="date" id="pn_to" value="<?php echo esc_attr($to_date); ?>">

				<a id="pn_export_btn" class="button button-primary" href="#">Export Positive/Negative CSV</a>
			</div>
		</li>
	</ul>
</div>
<script>
	(function() {
		var baseUrl = <?php echo json_encode(Tr_Base_Class_V6::link(['trajax' => 'export_pos_neg'], false)); ?>;

		function updateLink() {
			var from = document.getElementById('pn_from').value;
			var to = document.getElementById('pn_to').value;
			var url = baseUrl + '&from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to);
			document.getElementById('pn_export_btn').href = url;
		}

		document.getElementById('pn_from').addEventListener('change', updateLink);
		document.getElementById('pn_to').addEventListener('change', updateLink);
		updateLink();
	})();
</script>