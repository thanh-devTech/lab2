<?php

global $wpdb;

// ── AJAX handler 
if (isset($_POST['trajax']) && $_POST['trajax'] === 'pcr_check') {

    $from         = isset($_POST['from'])          ? sanitize_text_field($_POST['from'])                      : date_i18n('Y-m-d');
    $to           = isset($_POST['to'])            ? sanitize_text_field($_POST['to'])                        : $from;
    $cli_ids      = isset($_POST['cli_ids'])       ? array_map('intval',  (array)$_POST['cli_ids'])           : [];
    $test_ids     = isset($_POST['test_ids'])      ? array_map('intval',  (array)$_POST['test_ids'])          : [];
    $detected_ids = isset($_POST['detected_ids'])  ? array_map('intval',  (array)$_POST['detected_ids'])      : [];

    try {
        $batch_tbl  = 'ci_batches_pcr';
        $report_tbl = 'ci_reports_pcr';
        $result_tbl = 'ci_results_pcr';
        $panel_tbl  = 'ci_test_panels';

        $selected_tests = [];
        $detected_tests = [];

        if (!empty($test_ids)) {
            $te_ph   = implode(',', array_fill(0, count($test_ids), '%d'));
            $panels  = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT te_ID, te_name, te_abrev, te_abrev2
                     FROM `{$panel_tbl}`
                     WHERE te_ID IN ($te_ph)",
                    ...$test_ids
                ),
                ARRAY_A
            );
            foreach ($panels as $p) {
                $selected_tests[$p['te_ID']] = $p;
            }
            if (!empty($detected_ids)) {
                foreach ($detected_ids as $did) {
                    if (isset($selected_tests[$did])) {
                        $detected_tests[$did] = $selected_tests[$did];
                    }
                }
            }
        }

        // Hàm trả về tất cả drug strings (name + abrev + abrev2) của một panel row
        $panel_drug_strings = function (array $panel): array {
            $strings = [];
            foreach (['te_name', 'te_abrev', 'te_abrev2'] as $col) {
                if (!empty($panel[$col])) {
                    $strings[] = strtoupper(trim($panel[$col]));
                }
            }
            return $strings;
        };

        // ── Build WHERE ────────────────────────────────────────────────────
        $where  = [];
        $params = [];

        $where[]  = 'DATE(b.doc) BETWEEN %s AND %s';
        $params[] = $from;
        $params[] = $to;

        if (!empty($cli_ids)) {
            $placeholders = implode(',', array_fill(0, count($cli_ids), '%d'));
            $where[]      = "b.cli_ID IN ($placeholders)";
            $params       = array_merge($params, $cli_ids);
        }

        // ── JOIN ci_results_pcr qua drug string match ───────────────────────
        $test_join = '';

        if (!empty($selected_tests)) {
            $filter_tests  = !empty($detected_tests) ? $detected_tests : $selected_tests;
            $all_drug_strs  = [];
            foreach ($filter_tests as $t) {
                $all_drug_strs = array_merge($all_drug_strs, $panel_drug_strings($t));
            }
            $all_drug_strs = array_unique($all_drug_strs);

            if (!empty($all_drug_strs)) {
                $d_ph = implode(',', array_fill(0, count($all_drug_strs), '%s'));

                if (!empty($detected_tests)) {
                    $test_join = '';
                    $d_ph_params = [];
                    foreach ($detected_tests as $dt) {
                        $dt_strings = array_unique($panel_drug_strings($dt));
                        if (empty($dt_strings)) continue;

                        $dt_ph      = implode(',', array_fill(0, count($dt_strings), '%s'));
                        $alias      = 'res_' . $dt['te_ID']; // alias unique per test
                        $alias_rpt  = 'rpt_' . $dt['te_ID'];

                        $test_join .= " INNER JOIN `{$report_tbl}` {$alias_rpt}
                            ON {$alias_rpt}.patient_id = b.assen
                        INNER JOIN `{$result_tbl}` {$alias}
                            ON {$alias}.report_id = {$alias_rpt}.id
                           AND UPPER({$alias}.drug) IN ($dt_ph)
                           AND {$alias}.`pos-neg` IN ('POS','POH','POL','POM','PVH','PVL')
                           AND {$alias}.has_order = 1";

                        $d_ph_params = array_merge($d_ph_params, $dt_strings);
                    }
                    $params = array_merge($d_ph_params, $params);
                } else {
                    $test_join = "INNER JOIN `{$report_tbl}` rpt
                                      ON rpt.patient_id = b.assen
                                  INNER JOIN `{$result_tbl}` res
                                      ON res.report_id = rpt.id
                                     AND UPPER(res.drug) IN ($d_ph)
                                     AND res.has_order = 1";
                    $params = array_merge($all_drug_strs, $params);
                }
            }
        }

        $where_sql = implode(' AND ', $where);

        $sql = "SELECT DISTINCT
                    b.mid,
                    b.batch_id,
                    b.assen,
                    b.patient_name,
                    b.dob,
                    b.doc,
                    b.batch_status,
                    b.cli_ID,
                    c.cli_clinic
                FROM `{$batch_tbl}` b
                {$test_join}
                LEFT JOIN `ci_clinic` c ON c.cID = b.cli_ID
                WHERE {$where_sql}
                ORDER BY b.doc DESC
                LIMIT 500";

        $query   = $wpdb->prepare($sql, ...$params);
        $batches = $wpdb->get_results($query, ARRAY_A);

        if ($wpdb->last_error) {
            throw new Exception($wpdb->last_error);
        }


        $batch_tests = [];

        if (!empty($batches) && !empty($selected_tests)) {
            $assen_list = array_column($batches, 'assen'); // dùng assen thay vì batch_id

            $display_drug_map = [];
            foreach ($selected_tests as $t) {
                $canonical = $t['te_name'];
                foreach ($panel_drug_strings($t) as $ds) {
                    $display_drug_map[$ds] = $canonical;
                }
            }

            $all_display_strs = array_keys($display_drug_map);
            $b_ph = implode(',', array_fill(0, count($assen_list), '%s'));
            $d_ph = implode(',', array_fill(0, count($all_display_strs), '%s'));

            $r_sql = "SELECT res.report_id, rpt.patient_id, res.drug, res.`pos-neg`
              FROM `{$result_tbl}` res
              INNER JOIN `{$report_tbl}` rpt ON rpt.id = res.report_id
              WHERE rpt.patient_id IN ($b_ph)
                AND UPPER(res.drug) IN ($d_ph)
                AND res.has_order = 1";

            $r_params = array_merge($assen_list, $all_display_strs);
            $r_query  = $wpdb->prepare($r_sql, ...$r_params);
            $rows     = $wpdb->get_results($r_query, ARRAY_A);


            $seen = [];
            foreach ($rows as $row) {
                $assen      = $row['patient_id'];
                $drug_upper = strtoupper(trim($row['drug']));
                $te_name    = $display_drug_map[$drug_upper] ?? $row['drug'];
                $detected   = in_array($row['pos-neg'], ['POS', 'POH', 'POL', 'POM', 'PVH', 'PVL']) ? 1 : 0;

                if (!isset($seen[$assen][$te_name]) || $detected) {
                    $seen[$assen][$te_name] = [
                        'assen'           => $assen,
                        'te_name'         => $te_name,
                        'drug'            => $row['drug'],
                        'result_detected' => $detected,
                    ];
                }
            }

            foreach ($seen as $assen => $tests_by_name) {
                $batch_tests[$assen] = array_values($tests_by_name);
            }
        }

        wp_send_json([
            'success'     => true,
            'batches'     => $batches,
            'batch_tests' => $batch_tests,
            'count'       => count($batches),
            'message'     => count($batches) . " record(s) found ({$from} → {$to})",
        ]);
    } catch (Exception $e) {
        wp_send_json(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}


if (isset($_POST['trajax']) && $_POST['trajax'] === 'pcr_load_filters') {
    $clinics = $wpdb->get_results(
        "SELECT cID, cli_clinic FROM ci_clinic WHERE cli_pcr = 1 AND cli_status = 1 ORDER BY cli_clinic",
        ARRAY_A
    );
    $tests = $wpdb->get_results(
        "SELECT p.te_ID, p.te_name, p.te_group_id, g.tg_parent
        FROM ci_test_panels p
        INNER JOIN ci_test_groups g ON g.tg_ID = p.te_group_id
        WHERE p.te_type = 'pcr' AND p.te_in_req = 1
        ORDER BY p.te_name",
        ARRAY_A
    );
    $panels = $wpdb->get_results(
        "SELECT tg_ID, tg_name FROM ci_test_groups WHERE tg_type = 'pcr' AND tg_show_req = 1  AND tg_parent = 0 ORDER BY tg_name",
        ARRAY_A
    );
    wp_send_json(['success' => true, 'clinics' => $clinics, 'tests' => $tests, 'panels' => $panels]);
    exit;
}

// ── Enqueue assets 
wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', [], '1.1.9');
?>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?' . TRCPDD_VS ?>"></script>
<style>
    .pcr-wrap .form-container {
        background: #fff;
        border: 1px solid #dcdcde;
        border-radius: 4px;
        padding: 20px 24px;
        margin-bottom: 20px;
    }

    .pcr-wrap h3 {
        margin-top: 0;
        color: #1d2327;
    }

    /* Filters grid */
    .pcr-filters {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 16px;
        align-items: end;
    }

    .pcr-filters .form-group {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .pcr-filters label {
        font-size: 13px;
        font-weight: 600;
        color: #50575e;
    }

    .pcr-filters input[type=text],
    .pcr-filters select {
        border: 1px solid #8c8f94;
        border-radius: 3px;
        padding: 6px 8px;
        font-size: 14px;
        width: 100%;
        box-sizing: border-box;
    }

    /* Multi-select */
    .pcr-filters select[multiple] {
        height: 120px;
    }

    /* Action row */
    .pcr-action-row {
        margin-top: 16px;
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .btn-primary {
        background: #0073aa;
        color: #fff;
        border: none;
        border-radius: 3px;
        padding: 8px 18px;
        font-size: 14px;
        cursor: pointer;
    }

    .btn-primary:hover {
        background: #006291;
    }

    .btn-primary:disabled {
        opacity: .6;
        cursor: default;
    }

    .btn-secondary {
        background: #f0f0f1;
        color: #50575e;
        border: 1px solid #8c8f94;
        border-radius: 3px;
        padding: 8px 14px;
        font-size: 14px;
        cursor: pointer;
    }

    .btn-secondary:hover {
        background: #dcdcde;
    }

    /* Selected tests mini-table */
    #selected-tests-wrap {
        margin-top: 20px;
    }

    #selected-tests-wrap h4 {
        margin: 0 0 8px;
        font-size: 13px;
        color: #1d2327;
    }

    .tests-mini-table {
        border-collapse: collapse;
        font-size: 13px;
    }

    .tests-mini-table th,
    .tests-mini-table td {
        border: 1px solid #dcdcde;
        padding: 5px 10px;
        text-align: left;
    }

    .tests-mini-table th {
        background: #f6f7f7;
        font-weight: 600;
        color: #50575e;
    }

    .tests-mini-table td label {
        display: flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
    }

    /* Results */
    #pcr-results {
        margin-top: 20px;
    }

    .result-meta {
        font-size: 13px;
        color: #50575e;
        margin-bottom: 12px;
    }

    /* Main results table */
    .pcr-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .pcr-table th {
        background: #1d2327;
        color: #fff;
        padding: 8px 10px;
        text-align: left;
        white-space: nowrap;
    }

    .pcr-table td {
        border-bottom: 1px solid #f0f0f1;
        padding: 7px 10px;
        vertical-align: top;
    }

    .pcr-table tr:hover td {
        background: #f6f9fc;
    }

    .pcr-table tr:nth-child(even) td {
        background: #fafafa;
    }

    .pcr-table tr:nth-child(even):hover td {
        background: #f0f6fb;
    }

    /* Inline test pills */
    .test-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
    }

    .pill {
        display: inline-block;
        padding: 2px 7px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 600;
    }

    .pill-detected {
        background: #fde8e8;
        color: #c0392b;
    }

    .pill-negative {
        background: #eafaf1;
        color: #1e8449;
    }

    /* Status badge */
    .status-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 600;
        background: #e8f5e9;
        color: #2e7d32;
    }

    /* Loading overlay */
    #pcr-loading {
        display: none;
        color: #50575e;
        font-size: 13px;
        margin-left: 10px;
    }
</style>

<div class="pcr-wrap wrap">
    <div class="form-container">
        <h3>PCR Batch Checker</h3>

        <div class="pcr-filters">
            <div class="form-group">
                <label for="pcr-from">From Date</label>
                <input type="text" class="hasdate" id="pcr-from" placeholder="yyyy-mm-dd">
            </div>
            <div class="form-group">
                <label for="pcr-to">To Date</label>
                <input type="text" class="hasdate" id="pcr-to" placeholder="yyyy-mm-dd">
            </div>
            <div class="form-group">
                <label for="pcr-clinic">Clinic <small style="font-weight:400">(multi)</small></label>
                <select id="pcr-clinic" multiple data-multi="1" data-search="1" data-placeholder="Select clinics..."></select>
            </div>
            <div class="form-group">
                <label for="pcr-panel">Panel <small style="font-weight:400">(multi)</small></label>
                <select id="pcr-panel" multiple data-multi="1" data-search="1" data-placeholder="Select panels..."></select>
            </div>
            <div class="form-group">
                <label for="pcr-test">Tests <small style="font-weight:400">(multi)</small></label>
                <select id="pcr-test" multiple data-multi="1" data-search="1" data-placeholder="Select tests..."></select>
            </div>
        </div>

        <div class="pcr-action-row">
            <button id="pcr-search-btn" class="btn-primary">Search</button>
            <button id="pcr-reset-btn" class="btn-secondary">Reset</button>
            <span id="pcr-loading">⏳ Loading…</span>
        </div>

        <!-- Selected tests mini-table with "Detected" checkboxes -->
        <div id="selected-tests-wrap" style="display:none;">
            <h4>Selected Tests — check "Detected" to filter only positive results:</h4>
            <table class="tests-mini-table">
                <thead>
                    <tr>
                        <th>Test Name</th>
                        <th>Detected only</th>
                    </tr>
                </thead>
                <tbody id="selected-tests-body"></tbody>
            </table>
        </div>
    </div>

    <!-- Results -->
    <div id="pcr-results" style="display:none;">
        <div class="form-container">
            <div class="result-meta" id="pcr-result-meta"></div>
            <div style="overflow-x:auto;">
                <table class="pcr-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Accession</th>
                            <th>Patient Name</th>
                            <th>DOB</th>
                            <th>Batch#</th>
                            <th>DOC</th>
                            <th>Clinic</th>
                            <th>Status</th>
                            <th>Tests</th>
                        </tr>
                    </thead>
                    <tbody id="pcr-result-body"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    jQuery(function($) {
        var allTestsByPanel = {};
        var allTests = [];
        $('.hasdate').datepicker({
            dateFormat: 'yy-mm-dd'
        });

        $.ajax({
            type: 'POST',
            data: {
                trajax: 'pcr_load_filters'
            },
            success: function(res) {
                if (!res.success) return;
                const urlParams = new URLSearchParams(location.search);
                if (urlParams.get('from')) $('#pcr-from').val(urlParams.get('from'));
                if (urlParams.get('to')) $('#pcr-to').val(urlParams.get('to'));

                const urlTests = [];
                const urlClis = [];
                const urlDetected = [];
                const urlPanels = [];

                urlParams.forEach((val, key) => {
                    if (key.startsWith('test_ids')) urlTests.push(val);
                    if (key.startsWith('cli_ids')) urlClis.push(val);
                    if (key.startsWith('detected_ids')) urlDetected.push(val);
                    if (key.startsWith('panel_ids')) urlPanels.push(val);
                });


                $.each(res.clinics, function(_, c) {
                    $('#pcr-clinic').append($('<option>', {
                        value: c.cID,
                        text: c.cli_clinic
                    }));
                });
                $.each(res.panels, function(_, p) {
                    $('#pcr-panel').append($('<option>', {
                        value: p.tg_ID,
                        text: p.tg_name
                    }));
                });
                allTests = res.tests || [];
                allTestsByPanel = {};
                $.each(allTests, function(_, t) {
                    var parentId = t.tg_parent != 0 ? String(t.tg_parent) : String(t.te_group_id);
                    if (!allTestsByPanel[parentId]) allTestsByPanel[parentId] = [];
                    allTestsByPanel[parentId].push(t);
                });
                if (urlPanels.length === 0) {
                    $.each(res.tests, function(_, t) {
                        $('#pcr-test').append($('<option>', {
                            value: t.te_ID,
                            text: t.te_name
                        }));
                    });
                } else {
                    const panelsToShow = urlPanels.map(id => parseInt(id));
                    const testsToShow = [];
                    panelsToShow.forEach(function(pid) {
                        const group = allTestsByPanel[String(pid)] || [];
                        testsToShow.push(...group);
                    });
                    console.log(testsToShow)
                    $.each(testsToShow, function(_, t) {
                        $('#pcr-test').append($('<option>', {
                            value: t.te_ID,
                            text: t.te_name
                        }));
                    });
                }

                if (typeof initCustomSelect === 'function') {
                    initCustomSelect('select');
                }
                if (urlPanels.length) {
                    $('#pcr-panel').val(urlPanels);
                    refreshCustomSelect('pcr-panel');
                }

                if (urlClis.length) {
                    $('#pcr-clinic').val(urlClis);
                    refreshCustomSelect('pcr-clinic');
                }
                console.log(urlTests);
                if (urlTests.length) {
                    $('#pcr-test').val(urlTests).trigger("change");
                    refreshCustomSelect('pcr-test');
                }

                if (urlTests.length) {
                    const urlDetected = [];
                    urlParams.forEach((val, key) => {
                        if (key.startsWith('detected_ids')) urlDetected.push(val);
                    });
                    urlDetected.forEach(function(id) {
                        $('.detected-chk[data-id="' + id + '"]').prop('checked', true);
                    });
                }
                if (urlParams.get('from') && urlParams.get('to')) {
                    $('#pcr-search-btn').trigger('click');
                }
            }
        });
        $('#pcr-panel').on('change', function() {
            var panelIds = $(this).val() || [];
            var $testSel = $('#pcr-test');

            var prevSelected = $testSel.val() || [];

            $testSel.empty();

            if (panelIds.length === 0) {
                $testSel.prop('disabled', true);
                $('#selected-tests-body').empty();
                $('#selected-tests-wrap').hide();
                if (typeof refreshCustomSelect === 'function') {
                    refreshCustomSelect('pcr-test');
                }
                return;
            }

            var testsToShow = [];
            var seenIds = {};
            $.each(panelIds, function(_, pid) {
                var group = allTestsByPanel[String(pid)] || [];
                $.each(group, function(_, t) {
                    if (!seenIds[t.te_ID]) {
                        seenIds[t.te_ID] = true;
                        testsToShow.push(t);
                    }
                });
            });

            testsToShow.sort(function(a, b) {
                return a.te_name.localeCompare(b.te_name);
            });

            $.each(testsToShow, function(_, t) {
                var isSelected = prevSelected.indexOf(String(t.te_ID)) !== -1;
                $testSel.append($('<option>', {
                    value: t.te_ID,
                    text: t.te_name,
                    selected: isSelected
                }));
            });

            if (typeof refreshCustomSelect === 'function') {
                refreshCustomSelect('pcr-test');
            }

            $testSel.trigger('change');
        });

        $('#pcr-test').on('change', function() {
            const $select = $(this);
            const values = $select.val() || [];
            const $body = $('#selected-tests-body');

            const prevChecked = {};
            $body.find('.detected-chk').each(function() {
                prevChecked[$(this).data('id')] = $(this).is(':checked');
            });

            $body.empty();

            if (values.length === 0) {
                $('#selected-tests-wrap').hide();
                return;
            }

            values.forEach(function(id) {
                const name = $select.find('option[value="' + id + '"]').text();
                const checked = prevChecked[id] ? 'checked' : '';
                $body.append(`
                <tr>
                    <td>${esc(name)}</td>
                    <td>
                        <label>
                            <input type="checkbox" class="detected-chk" data-id="${id}" ${checked}>
                            Detected
                        </label>
                    </td>
                </tr>`);
            });

            $('#selected-tests-wrap').show();
        });

        // ── Search 
        $('#pcr-search-btn').on('click', function() {
            const from = $('#pcr-from').val();
            const to = $('#pcr-to').val();
            const cli_ids = $('#pcr-clinic').val() || [];
            const panel_ids = $('#pcr-panel').val() || [];
            const test_ids = $('#pcr-test').val() || [];
            const detected_ids = [];

            $('.detected-chk:checked').each(function() {
                detected_ids.push($(this).data('id'));
            });

            if (!from || !to) {
                alert('Please select a date range.');
                return;
            }
            const params = new URLSearchParams();
            params.set('page', 'admin_tools');
            params.set('tab', 'tools');
            params.set('act', 'abx_tool');
            if (from) params.set('from', from);
            if (to) params.set('to', to);
            cli_ids.forEach(id => params.append('cli_ids[]', id));
            panel_ids.forEach(id => params.append('panel_ids[]', id));
            test_ids.forEach(id => params.append('test_ids[]', id));
            detected_ids.forEach(id => params.append('detected_ids[]', id));
            history.replaceState(null, '', '?' + params.toString());

            const $btn = $(this);
            $btn.prop('disabled', true).text('Searching…');
            $('#pcr-loading').show();
            $('#pcr-results').hide();

            $.ajax({
                type: 'POST',
                data: {
                    trajax: 'pcr_check',
                    from: from,
                    to: to,
                    cli_ids: cli_ids,
                    test_ids: test_ids,
                    detected_ids: detected_ids,
                },
                success: function(res) {
                    if (!res.success) {
                        alert('Error: ' + (res.error || 'Unknown error'));
                        return;
                    }
                    renderResults(res);
                },
                error: function(e) {
                    alert('Request failed.');
                    console.error(e);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Search');
                    $('#pcr-loading').hide();
                }
            });
        });

        // ── Reset 
        $('#pcr-reset-btn').on('click', function() {
            $('#pcr-from, #pcr-to').val('');
            $('#pcr-clinic, #pcr-test, #pcr-panel').val([]).trigger('change');
            if (typeof refreshCustomSelect === 'function') {
                refreshCustomSelect('pcr-clinic');
                refreshCustomSelect('pcr-test');
                refreshCustomSelect('pcr-panel');
            }
            $('#pcr-results').hide();
        });

        // ── Render results
        function renderResults(res) {
            const $body = $('#pcr-result-body');
            $body.empty();
            $('#pcr-result-meta').text(res.message);

            if (!res.batches || res.batches.length === 0) {
                $body.append('<tr><td colspan="8" style="text-align:center;color:#999;padding:20px;">No records found.</td></tr>');
            } else {
                $.each(res.batches, function(i, b) {
                    const tests = res.batch_tests[b.assen] || [];
                    const pillsHtml = buildPills(tests);

                    $body.append(`
                    <tr>
                        <td>${i + 1}</td>
                        <td><strong>${esc(b.assen)}</strong></td>
                        <td>${esc(b.patient_name || '—')}</td>
                        <td>${esc(b.dob || '—')}</td>
                        <td>${esc(b.batch_id || '—')}</td>                        
                        <td>${esc(b.doc || '—')}</td>
                        <td>${esc(b.cli_clinic || '—')}</td>
                        <td><span class="status-badge">${esc(b.batch_status || '—')}</span></td>
                        <td><div class="test-pills">${pillsHtml || '<em style="color:#999">—</em>'}</div></td>
                    </tr>`);
                });
            }

            $('#pcr-results').show();
        }

        function buildPills(tests) {
            return tests.map(function(t) {
                const detected = parseInt(t.result_detected) === 1;
                const cls = detected ? 'pill-detected' : 'pill-negative';
                const icon = detected ? '🔴' : '🟢';
                return `<span class="pill ${cls}">${icon} ${esc(t.te_name)}</span>`;
            }).join('');
        }

        function esc(str) {
            if (str == null) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }
    });
</script>