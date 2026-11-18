<?php
global $wpdb, $ci_update_log;

if (!defined('ABSPATH')) {
    http_response_code(403);
    exit('Forbidden');
}
include_once(TRLCN_PATH . '/api/ci_lab_client.php');
$client = new CI_Lab_Client_Api(true);

$trajax = isset($_GET['trajax']) ? $_GET['trajax'] : '';
$action = isset($_GET['action']) ? $_GET['action'] : '';


if ($action == 'abx_get_versions') {
    wp_send_json(['success' => true, 'versions' => $client->get_available_versions()]);
}

if ($action == 'abx_sync_version_step') {
    $table = sanitize_text_field($_REQUEST['table'] ?? '');
    $version_name = sanitize_text_field($_REQUEST['version_name'] ?? '');

    if (empty($table) || empty($version_name)) {
        wp_send_json(['success' => false, 'msg' => 'Missing table or version_name']);
    }

    $named_version = ($version_name === 'latest') ? '' : $version_name;

    $rs = $client->sync_version_table($table, $named_version);
    wp_send_json($rs);
}

if ($action == 'abx_sync_version_finish') {
    $version_name = sanitize_text_field($_REQUEST['version_name'] ?? '');
    $client->finish_sync_version($version_name);
    wp_send_json(['success' => true]);
}

// ─── AJAX: Update 1 table 
if ($action == 'abx_update_table') {
    $return = ['success' => false, 'msg' => ''];
    $table  = sanitize_text_field($_REQUEST['table'] ?? '');

    $abx_version = get_option('ci_abx_table_versions', []);
    $named_version = $abx_version['named_version'] ?? '';

    if (empty($table) || !isset($abx_version['lastversions'][$table])) {
        $return['msg'] = "Table '{$table}' not found.";
        wp_send_json($return);
    }

    update_option('ci_abx_updating_data', time(), false);
    $updrs       = $client->update_abx_table($table, $named_version);
    $lastversion = $abx_version['lastversions'][$table];

    $return['log'] = $ci_update_log;

    if ($updrs === 0) {
        unset($abx_version['tables'][$table]);
        unset($abx_version['lastversions'][$table]);
        update_option('ci_abx_table_versions', $abx_version, false);
        update_option('ci_abx_updating_data', 0, false);
        $return['msg'] = "Table '{$table}' not exist.";
        wp_send_json($return);
    } elseif ($updrs) {
        $abx_version     = get_option('ci_abx_table_versions', array());
        //$abx_version['tables'][$table] = $lastversion;
        //update_option('ci_abx_table_versions', $abx_version, false);
        update_option('ci_abx_updating_data', 0, false);
        update_option('ci_abx_last_change', date_i18n('YmdHis'));
        update_option('_ci_last_update_abx', time(), false);

        // Kiểm tra nếu toàn bộ đã latest
        $all_done = true;
        foreach ($abx_version['lastversions'] as $tbl => $lv) {
            if (($abx_version['tables'][$tbl] ?? 0) < $lv) {
                $all_done = false;
                break;
            }
        }
        if ($all_done && isset($abx_version['version'])) {
            update_option('ci_abx_last_version', $abx_version['version']);
        }

        $return = ['success' => true, 'msg' => "Updated to version {$lastversion}.", 'new_version' => $lastversion];
        wp_send_json($return);
    } else {
        update_option('ci_abx_updating_data', 0, false);
        $return['msg'] = "Update failed.";
        wp_send_json($return);
    }
}

// ─── AJAX: Refresh version list 
if ($action == 'abx_refresh_versions') {
    $client->check_abx_version();
    wp_send_json(['success' => true, 'msg' => 'Refresh successfully.']);
}
//update_option('ci_abx_table_versions', []);
$abx_version       = get_option('ci_abx_table_versions', []);
$last_versions     = $abx_version['lastversions'] ?? [];
$cur_versions      = $abx_version['tables']       ?? [];
$nonce             = wp_create_nonce('abx_nonce');
$current_named_version = get_option('ci_abx_current_named_version', '');

$last_run = get_option('_ci_last_update_abx', 0);
$cron_is_running = false;
if ($last_run > time()) {
    $cron_is_running = true;
}

$need_update_count = 0;
$rows = [];
foreach ($last_versions as $table => $lastversion) {
    if (empty($table)) continue;
    if (stripos($table, 'ci_abx_results_scores_') !== false) continue;
    $cversion  = $cur_versions[$table] ?? 0;
    $is_latest = ($cversion > 0 && $cversion >= $lastversion);
    if (!$is_latest) $need_update_count++;
    $rows[] = compact('table', 'lastversion', 'cversion', 'is_latest');
}

$url_update  = Tr_Base_Class_V6::link(['trajax' => 'update_abx', 'action' => 'abx_update_table'],  false);
$url_refresh = Tr_Base_Class_V6::link(['trajax' => 'update_abx', 'action' => 'abx_refresh_versions'], false);
?>

<style>
    .abx-wrap h2 {
        font-size: 1.4rem;
        margin-bottom: 6px;
    }

    .abx-meta {
        color: #666;
        font-size: 13px;
        margin-bottom: 18px;
    }

    .abx-notice {
        padding: 10px 16px;
        border-radius: 5px;
        margin-bottom: 16px;
        font-size: 13.5px;
        display: none;
    }

    .abx-notice.success {
        background: #edf7ed;
        border-left: 4px solid #4caf50;
        color: #2e7d32;
    }

    .abx-notice.error {
        background: #fdecea;
        border-left: 4px solid #f44336;
        color: #c62828;
    }

    .abx-notice.warning {
        background: #fff8e1;
        border-left: 4px solid #ffc107;
        color: #7b5f00;
    }

    .abx-badge {
        display: inline-block;
        padding: 2px 9px;
        border-radius: 20px;
        font-size: 11.5px;
        font-weight: 700;
    }

    .abx-badge.latest {
        background: #e8f5e9;
        color: #388e3c;
    }

    .abx-badge.outdated {
        background: #fff3e0;
        color: #e65100;
    }

    .abx-badge.unknown {
        background: #f3e5f5;
        color: #7b1fa2;
    }

    .abx-count {
        font-weight: 700;
        color: #0073aa;
    }

    .abx-spinning {
        display: inline-block;
        animation: abx-spin .7s linear infinite;
    }

    @keyframes abx-spin {
        to {
            transform: rotate(360deg);
        }
    }
</style>

<div class="abx-wrap">
    <div style="margin-bottom:15px; display:flex; align-items:center; gap:10px;">
        <p>
            Current version:
            <strong id="abx_current_version_label">
                <?php echo $current_named_version ? esc_html($current_named_version) : 'Latest'; ?>
            </strong>
        </p>
        <p class="abx-meta" style="margin:0;">
            Tables: <span class="abx-count"><?php echo count($rows); ?></span>
            &nbsp;|&nbsp; Need update: <span class="abx-count abx-need-count" style="color:<?php echo $need_update_count > 0 ? '#e65100' : '#388e3c'; ?>"><?php echo $need_update_count; ?></span>
        </p>

    </div>
    <div style="margin:15px 0;">
        <?php if ($cron_is_running) echo '<div class="abx-notice warning" style="display:block;">Updating in background. Please try again after a few minutes.</div>'; ?>
        <div style="display:flex; align-items:center; gap:10px;">
            <select id="abx_version_select" style="min-width:220px;">
                <option value="">Select version</option>
                <option value="latest">Latest</option>
                <option value="" class="loading">Loading...</option>
            </select>
            <button id="btn_sync_version" class="button button-primary" <?php if ($cron_is_running) echo 'disabled'; ?>>Apply this version</button>
            <button id="btn_refresh" class="button">Refresh Versions</button>
            <button id="btn_update_all" class="button button-primary" style="display: none;" <?php if ($cron_is_running) echo 'disabled'; ?>>Update All</button>
        </div>
    </div>

    <div id="abx_progress" style="display:none; margin-bottom:15px; padding:10px; background:#f0f0f1; border-left:4px solid #2271b1;">
        <strong>Progress: <span id="abx_current">0</span> / <span id="abx_total">0</span></strong>
    </div>

    <div id="abx_notice" class="abx-notice"></div>

    <?php if (empty($rows)): ?>
        <div class="abx-notice warning" style="display:block;">No version data available.</div>
    <?php endif; ?>

    <?php if (!empty($rows)): ?>
        <table class="wp-list-table widefat fixed striped posts">
            <thead>
                <tr>
                    <th style="width:30px;">#</th>
                    <th>Table Name</th>
                    <th style="width:50px;">Rows</th>
                    <th style="width:120px;">Current</th>
                    <th style="width:120px;">Latest</th>
                    <th style="width:70px;">Status</th>
                    <th style="width:60px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $row):

                    $row['count_rows'] = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$row['table']}"));
                ?>
                    <tr data-table="<?php echo esc_attr($row['table']); ?>">
                        <td style="color:#999;"><?php echo $i + 1; ?></td>
                        <td><code style="font-size:12.5px;"><?php echo esc_html($row['table']); ?></code></td>
                        <th><?php echo $row['count_rows'] ?: '<span style="color:#bbb;">—</span>'; ?></th>
                        <td class="abx-cversion"><?php echo $row['cversion'] ?: '<span style="color:#bbb;">—</span>'; ?></td>
                        <td><?php echo esc_html($row['lastversion']); ?></td>
                        <td class="abx-status">
                            <?php if ($row['cversion'] <= 0): ?>
                                <span class="abx-badge unknown">Not synced</span>
                            <?php elseif ($row['is_latest']): ?>
                                <span class="abx-badge latest">Latest</span>
                            <?php else: ?>
                                <span class="abx-badge outdated">Outdated</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$row['is_latest'] && empty($current_named_version)): ?>
                                <button class="btn_update_single button button-small" data-table="<?php echo esc_attr($row['table']); ?>">Update</button>
                            <?php else: ?>
                                <span style="color:#bbb;font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
    jQuery(document).ready(function($) {
        var isUpdating = false;
        var totalCount = 0;
        var currentCount = 0;
        var nonce = <?php echo json_encode($nonce); ?>;
        var urlUpdate = <?php echo json_encode($url_update); ?>;
        var urlRefresh = <?php echo json_encode($url_refresh); ?>;
        var urlGetVersions = <?php echo json_encode(Tr_Base_Class_V6::link(['trajax' => 'update_abx', 'action' => 'abx_get_versions'], false)); ?>;
        var urlSyncVersionStep = <?php echo json_encode(Tr_Base_Class_V6::link(['trajax' => 'update_abx', 'action' => 'abx_sync_version_step'], false)); ?>;
        var urlSyncVersionFinish = <?php echo json_encode(Tr_Base_Class_V6::link(['trajax' => 'update_abx', 'action' => 'abx_sync_version_finish'], false)); ?>;

        function showNotice(type, msg, autohide) {
            var $el = $('#abx_notice');
            $el.attr('class', 'abx-notice ' + type).text(msg).show();
            clearTimeout($el.data('timer'));
            if (autohide !== false) {
                $el.data('timer', setTimeout(function() {
                    $el.hide();
                }, 5000));
            }
        }

        function updateNeedCount(delta) {
            var $el = $('.abx-need-count');
            var count = parseInt($el.text(), 10) + delta;
            $el.text(count).css('color', count > 0 ? '#e65100' : '#388e3c');
        }

        function doUpdate($row, isSingle) {
            var table = $row.data('table');
            $row.find('.abx-status').html('<span class="abx-spinning">⏳</span>');

            $.ajax({
                url: urlUpdate + '&table=' + encodeURIComponent(table),
                dataType: 'json',
                success: function(rs) {
                    if (rs.success) {
                        $row.find('.abx-cversion').text(rs.new_version);
                        $row.find('.abx-status').html('<span class="abx-badge latest">✓ Latest</span>');
                        $row.find('.btn_update_single').replaceWith('<span style="color:#bbb;font-size:12px;">—</span>');
                        updateNeedCount(-1);
                    } else {
                        $row.find('.abx-status').html('<span class="abx-badge outdated">Outdated</span>');
                        if (isSingle) showNotice('error', rs.msg || 'Update failed.');
                    }
                    afterUpdate($row, isSingle);
                },
                error: function() {
                    $row.find('.abx-status').html('<span class="abx-badge outdated">Outdated</span>');
                    if (isSingle) showNotice('error', 'Connect error.');
                    afterUpdate($row, isSingle);
                }
            });
        }

        function afterUpdate($row, isSingle) {
            if (isSingle) return;

            currentCount++;
            $('#abx_current').text(currentCount);

            var $next = $row.nextAll('tr[data-table]').filter(function() {
                return $(this).find('.btn_update_single').length > 0;
            }).first();

            if ($next.length) {
                doUpdate($next, false);
            } else {
                isUpdating = false;
                $('#btn_update_all').prop('disabled', false);
                $('.btn_update_single').prop('disabled', false);
                $('#abx_progress').hide();
                showNotice('success', 'Update successfully! Updated: ' + currentCount + ' / ' + totalCount + ' tables.', false);
            }
        }

        // Update All
        $('#btn_update_all').on('click', function() {
            if (isUpdating) return;
            var $rows = $('table tr[data-table]').filter(function() {
                return $(this).find('.btn_update_single').length > 0;
            });
            if (!$rows.length) return;

            isUpdating = true;
            totalCount = $rows.length;
            currentCount = 0;
            $(this).prop('disabled', true);
            $('.btn_update_single').prop('disabled', true);
            $('#abx_total').text(totalCount);
            $('#abx_current').text(0);
            $('#abx_progress').show();

            doUpdate($rows.first(), false);
        });

        // Update single
        $(document).on('click', '.btn_update_single', function() {
            if (isUpdating) return;
            var $row = $(this).closest('tr');
            doUpdate($row, true);
        });

        // Refresh versions
        $('#btn_refresh').on('click', function() {
            var $btn = $(this).prop('disabled', true).text('Refreshing...');
            $.ajax({
                url: urlRefresh,
                dataType: 'json',
                success: function(rs) {
                    $btn.prop('disabled', false).text('Refresh Versions');
                    if (rs.success) {
                        showNotice('success', rs.msg);
                        tb_remove();
                        setTimeout(function() {
                            $('#abx_tab a.thickbox').trigger('click');

                        }, 500);
                    } else {
                        showNotice('error', rs.msg || 'Refresh failed.');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Refresh Versions');
                    showNotice('error', 'Connect error.');
                }
            });
        });
        $.ajax({
            url: urlGetVersions,
            dataType: 'json',
            success: function(rs) {
                var $sel = $('#abx_version_select');
                $sel.find('option.loading').remove();
                if (rs.success && rs.versions.length) {
                    rs.versions.forEach(function(v) {
                        $sel.append(`<option value="${v.version_name}">${v.version_name} (${v.created_at})</option>`);
                    });
                } else {
                    $sel.append('<option value="">Not found!</option>');
                }
            }
        });
        $('#btn_sync_version').on('click', function() {
            if (isUpdating) return;

            var version = $('#abx_version_select').val();
            if (!version) return;

            var tables = $('table tr[data-table]').map(function() {
                return $(this).data('table');
            }).get();

            if (!tables.length) {
                showNotice('error', 'No tables found to sync.');
                return;
            }

            var isLatest = (version === 'latest');
            var confirmMsg = isLatest ?
                'Are you sure you want to sync the latest version to this lab? Any tables with differences will be overwritten.' :
                'Are you sure you want to apply version ' + version + ' to this lab? Existing data will be overwritten.';
            if (!confirm(confirmMsg)) return;

            isUpdating = true;
            var $btn = $(this).prop('disabled', true);
            $('#btn_update_all, .btn_update_single').prop('disabled', true);

            var total = tables.length;
            var current = 0;
            var doneCount = 0;
            var errors = [];

            $('#abx_total').text(total);
            $('#abx_current').text(0);
            $('#abx_progress').show();

            function syncNext() {
                if (current >= total) {
                    $.ajax({
                        url: urlSyncVersionFinish + '&version_name=' + encodeURIComponent(version),
                        dataType: 'json',
                        complete: function() {
                            isUpdating = false;
                            $('#btn_update_all, .btn_update_single').prop('disabled', false);
                            $('#abx_progress').hide();
                            $btn.prop('disabled', false).text('Apply this version');

                            var msg = errors.length ?
                                `Synced ${doneCount}/${total} tables. Failed: ` + errors.join(', ') :
                                `Successfully synced ${doneCount}/${total} tables to ${isLatest ? 'latest' : version}.`;
                            showNotice(errors.length ? 'error' : 'success', msg, false);

                            if (!errors.length) {
                                tb_remove();
                                setTimeout(function() {
                                    $('#abx_tab a.thickbox').trigger('click');
                                }, 500);
                            }
                        }
                    });
                    return;
                }

                var table = tables[current];
                $btn.text(`Syncing... (${current + 1}/${total})`);

                $.ajax({
                    url: urlSyncVersionStep +
                        '&table=' + encodeURIComponent(table) +
                        '&version_name=' + encodeURIComponent(version),
                    dataType: 'json',
                    success: function(rs) {
                        if (rs.success) {
                            doneCount++;
                        } else {
                            errors.push(table + (rs.msg ? ' (' + rs.msg + ')' : ''));
                        }
                    },
                    error: function() {
                        errors.push(table + ' (connect error)');
                    },
                    complete: function() {
                        current++;
                        $('#abx_current').text(current);
                        syncNext();
                    }
                });
            }

            syncNext();
        });
    });
</script>