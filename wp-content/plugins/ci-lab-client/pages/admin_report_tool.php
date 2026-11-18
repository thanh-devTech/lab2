<?php

global $wpdb;


if (isset($_POST['trajax']) && $_POST['trajax'] === 'check_stats') {

    include_once(TRLCN_PATH . '/api/ci_lab_client.php');
    $from =  isset($_POST['from']) ? $_POST['from'] : date_i18n('Y-m-d');
    $to =  isset($_POST['to']) ? $_POST['to'] : $from;
    $is_send = isset($_POST['is_send']) ? $_POST['is_send'] : false;

    try {
        $client = new CI_Lab_Client_Api(true);

        $reports = [];
        $start_timestamp = strtotime($from);
        $end_timestamp = strtotime($to);
        for ($current = $start_timestamp; $current <= $end_timestamp; $current += 86400) {
            $dateStr = date_i18n('Y-m-d', $current);
            $rs = $client->send_statistics($dateStr, $is_send, true);

            if ($rs) {
                $reports[$dateStr] = $rs;
            }
        }

        wp_send_json([
            'success' => true,
            'message' => "Successfully retrieved reports from {$from} to {$to}",
            'data' => $reports,
            'count' => count($reports)
        ]);
    } catch (Exception $e) {
        wp_send_json([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}
wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');
?>

<div class="form-container wrap">
    <h3>Stats report</h3>
    <form id="generate-form">
        <div class="form-group">
            <label>
                From Date
                <input type="text" class="hasdate" name="from" id="from-date" required placeholder="yyyy-mm-dd">
            </label>
            <label>
                To Date
                <input type="text" class="hasdate" name="to" id="to-date" required placeholder="yyyy-mm-dd">
            </label>
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" id="is_send_to_hub" name="is_send_to_hub" value="1">
                Send to hub
            </label>
        </div>

        <button type="button" id="check-btn" class="btn-primary">Send & Check</button>
    </form>
    <div id="results-container" style="margin-top: 20px; display: none;">
        <h4>Results:</h4>
        <div id="results-content"></div>
    </div>
</div>
<style>
    .form-group {
        margin-bottom: 15px;
    }

    .form-group input[type="checkbox"] {
        margin-right: 5px;
    }

    .btn-primary {
        background: #0073aa;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
    }

    .btn-primary:hover {
        background: #005a87;
    }

    .btn-primary:disabled {
        background: #ccc;
        cursor: not-allowed;
    }

    .stats-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
    }

    .stats-table thead {
        background: #0073aa;
        color: white;
    }

    .stats-table th {
        padding: 12px 8px;
        text-align: left;
        font-weight: 600;
        font-size: 13px;
    }

    .stats-table td {
        padding: 10px 8px;
        border-bottom: 1px solid #ddd;
        font-size: 13px;
    }

    .stats-table tbody tr:hover {
        background: #f5f5f5;
    }

    .stats-table .row-total {
        background: #f0f8ff;
        font-weight: 700;
        border-top: 2px solid #0073aa;
    }

    .stats-table .row-total td {
        padding: 12px 8px;
    }

    .btype-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .btype-blood {
        background: #e3f2fd;
        color: #1976d2;
    }

    .btype-pcr {
        background: #f3e5f5;
        color: #7b1fa2;
    }

    .btype-tox {
        background: #fff3e0;
        color: #f57c00;
    }

    .date-header {
        background: #f9f9f9;
        padding: 15px;
        margin-bottom: 15px;
        border-left: 4px solid #0073aa;
        font-size: 16px;
        font-weight: 700;
    }

    .summary-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }

    .summary-card {
        background: white;
        padding: 15px;
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        border-left: 4px solid #0073aa;
    }

    .summary-card h4 {
        margin: 0 0 10px 0;
        font-size: 13px;
        color: #666;
    }

    .summary-card .number {
        font-size: 28px;
        font-weight: 700;
        color: #0073aa;
    }
</style>

<script>
    jQuery(function($) {
        $(".hasdate").datepicker({
            dateFormat: 'yy-mm-dd'
        });
        $('#check-btn').on('click', function() {
            const $btn = $(this);
            const fromDate = $('#from-date').val();
            const toDate = $('#to-date').val();
            const $send_to_hub = $('#is_send_to_hub');

            const is_send = $send_to_hub.is(':checked');
            $btn.prop('disabled', true).text('Checking...');
            $.ajax({
                type: 'POST',
                data: {
                    trajax: 'check_stats',
                    from: fromDate,
                    to: toDate,
                    is_send: is_send
                },
                success: function(res) {
                    if (res.success) {
                        if (res.data && Object.keys(res.data).length > 0) {
                            let html = renderStatsTable(res.data);
                            $('#results-content').html(html);
                            $('#results-container').show();
                        } else {
                            alert('No data found for the selected period');
                        }
                    } else {
                        alert('Error: ' + (res.error || 'Unknown error'));
                    }
                },
                error: function(e) {
                    alert('Error');
                    console.log(e);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Check');
                }
            });

        });

        function renderStatsTable(data) {
            let html = '';
            let grand = {
                received: 0,
                result: 0,
                critical: 0,
                abx: 0
            };
            let grandByType = {};

            $.each(data, function(date, stats) {

                let daily = {
                    received: 0,
                    result: 0,
                    critical: 0,
                    abx: 0
                };

                let groupedByType = {};
                let totalRows = [];

                $.each(stats, function(key, item) {

                    const received = parseInt(item.count_received || 0);
                    const result = parseInt(item.count_result || 0);
                    const critical = parseInt(item.count_result_c || 0);
                    const abx = parseInt(item.count_result_abx || 0);
                    const type = item.btype || 'other';
                    if (!grandByType[type]) {
                        grandByType[type] = {
                            received: 0,
                            result: 0,
                            critical: 0,
                            abx: 0
                        };
                    }

                    if (key.startsWith('total_')) {
                        if (key === 'total_sms' || key.indexOf('total_state_') === 0) return
                        totalRows.push(item);

                        grand.received += received;
                        grand.result += result;
                        grand.critical += critical;


                        grandByType[type].received += received;
                        grandByType[type].result += result;
                        grandByType[type].critical += critical;

                        return;
                    }
                    grand.abx += abx;
                    grandByType[type].abx += abx;

                    daily.received += received;
                    daily.result += result;
                    daily.critical += critical;
                    daily.abx += abx;


                    if (!groupedByType[type]) groupedByType[type] = [];
                    groupedByType[type].push(item);
                });
                html += '<div class="date-header">📅 ' + date + '</div>';

                html += renderSummaryCards(daily);

                $.each(groupedByType, function(type, items) {
                    html += '<h3 style="margin-top:20px;">📋 ' + type.toUpperCase() + ' Tests</h3>';
                    html += renderTable(items, type);
                });

                if (totalRows.length) {
                    html += '<h3 style="margin-top:20px;">📊 Summary Totals</h3>';
                    html += renderTable(totalRows, 'total');
                }
            });
            let overallHtml = '<h3 style="margin-top:20px;">Overall Summary</h3>';
            overallHtml += renderSummaryCards(grand);

            // ===== Overall by type =====
            $.each(grandByType, function(type, s) {
                overallHtml += '<h4 style="margin-top:12px;">📋 ' + type.toUpperCase() + '</h4>';
                overallHtml += renderSummaryCards(s);
            });

            html = overallHtml +
                '<hr style="margin-bottom:20px;border-bottom:4px solid #dcdcde;">' +
                html;

            return html;
        }

        function renderTable(items, type) {
            let html = '<table class="stats-table">';
            html += '<thead><tr>';
            html += '<th>Group Name</th>';
            html += '<th>Short Name</th>';
            html += '<th>Type</th>';
            html += '<th style="text-align: center;">Received</th>';
            html += '<th style="text-align: center;">Results</th>';
            html += '<th style="text-align: center;">Results C</th>';
            html += '<th style="text-align: center;">ABX</th>';
            html += '<th style="text-align: center;">Rejected</th>';
            html += '</tr></thead>';
            html += '<tbody>';

            $.each(items, function(i, item) {
                const isTotal = item.unique_id && item.unique_id.startsWith('total_');
                if (item.unique_id != 'total_sms') {
                    const rowClass = isTotal ? 'row-total' : '';

                    html += '<tr class="' + rowClass + '">';
                    html += '<td>' + (item.group || '-') + '</td>';
                    html += '<td>' + (item.short_name || '-') + '</td>';
                    html += '<td><span class="btype-badge btype-' + item.btype + '">' + item.btype + '</span></td>';
                    html += '<td style="text-align: center;">' + (item.count_received || 0) + '</td>';
                    html += '<td style="text-align: center;">' + (item.count_result || 0) + '</td>';
                    html += '<td style="text-align: center;">' + (item.count_result_c || 0) + '</td>';
                    html += '<td style="text-align: center;">';
                    if (item.count_result_abx > 0) {
                        html += '<strong style="color: #f57c00;">' + item.count_result_abx + '</strong>';
                    } else {
                        html += item.count_result_abx || 0;
                    }
                    html += '</td>';
                    html += '<td style="text-align: center;">' + (item.count_rejected || 0) + '</td>';
                    html += '</tr>';
                }

            });

            html += '</tbody></table>';
            return html;
        }

        function renderSummaryCards(s) {
            return `
                <div class="summary-stats">
                    <div class="summary-card">
                        <h4>Total Received</h4>
                        <div class="number">${s.received}</div>
                    </div>
                    <div class="summary-card">
                        <h4>Total Results</h4>
                        <div class="number">${s.result}</div>
                    </div>
                    <div class="summary-card">
                        <h4>Results C</h4>
                        <div class="number">${s.critical}</div>
                    </div>
                    <div class="summary-card">
                        <h4>ABX Resistance</h4>
                        <div class="number">${s.abx}</div>
                    </div>
                </div>
            `;
        }

    });
</script>