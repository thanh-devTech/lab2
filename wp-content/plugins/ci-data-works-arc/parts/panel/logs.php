<?php
global $wpdb;
$from = isset($_GET['from']) ? $_GET['from'] : '';
$to = isset($_GET['to']) ? $_GET['to'] : '';
$tab = isset($_GET['type']) ? $_GET['type'] : '';
$where = "l.user_action like '%_panel_{$tab}%' or l.user_action like '%_test_{$tab}%'";
if (!empty($from)) {
    $where .= " and created >= '{$from}' ";
}
if (!empty($to)) {
    $where .= " and created <= '{$to} 23:59:59' ";
}
if (isset($_GET['fix_panels'])) {
    include_once 'ci_fix_panel_groups.php';
    $where .= " and l.user_action = 'update_panel_pcr' and l.data like '%tg_notestcheckbox\":[\"1\",\"0\"]%' ";
}

$row_per_page = 20;
$pagination = new Tr_Pagination_class2($row_per_page);
$offset = $pagination->offset();
$total = $wpdb->get_var("select count(*) from ci_log_users as l where {$where}");
$pagination->setTotal($total);
$limitstr = " limit {$offset},{$row_per_page} ";
$logs = $wpdb->get_results("select l.* , u.user_email
from ci_log_users l 
join {$wpdb->users} u ON u.ID = l.user_id
where {$where}
order by l.created desc 
{$limitstr}");
?>
<h2>Logs</h2>
<div class="tablenav">
    <form method="get" class="left">
        <input type="hidden" name="page" value="<?php echo isset($_GET['page']) ? $_GET['page'] : '' ?>" />
        <input type="hidden" name="tab" value="<?php echo $tab ?>" />
        <input type="hidden" name="tabtype" value="<?php echo isset($_GET['tabtype']) ? $_GET['tabtype'] : '' ?>" />
        <label>Date From</label>
        <input type="search" class="hasdate" name="from" value="<?php echo $from ?>" style="width:120px" />
        <label>Date To</label>
        <input type="search" class="hasdate" name="to" value="<?php echo $to ?>" style="width:120px" />
        <input type="submit" class="button-primary" value="Filter" />
    </form>
    <?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?>
</div>

<div style="overflow-x: auto; max-width: 100%;width: 100%;">
    <table class="wp-list-table widefat fixed striped users">
        <thead>
            <tr>
                <th style="width:100px">Date</th>
                <th style="width:120px">Action</th>
                <th>Data</th>
                <th style="width:230px">User Email</th>
                <th style="width:120px">IP</th>
            </tr>
        </thead>
        <tbody>
            <?php

            foreach ($logs as $log) {
                //  var_dump($log->data);
                if (isset($_GET['fix_panels']) && $log->user_action == 'update_panel_pcr' && strpos($log->data, 'tg_notestcheckbox":["1","0"]') !== false) {
                    $data = json_decode($log->data, true);
                    if (isset($data['id'])) {
                        $gid = $data['id'];
                        $fixer = new Ci_Fix_Panel_Groups();
                        $fixer->fix_panel_group($gid);
                    }
                }
            ?>
                <tr>
                    <td><?php echo $log->created ?></td>
                    <td><?php echo $log->user_action ?></td>
                    <td style="overflow-x: scroll;"><?php echo render_log_data($log->data, '/wp-admin/admin.php?page=ci_pdf_data&pagetab=ci_test_panels&tab=' . $tab . '&act=edit', $tab) ?></td>
                    <td><?php echo $log->user_email ?></td>
                    <td><?php echo $log->ip ?></td>
                </tr>
            <?php
            }
            if (count($logs) == 0) {
                echo '<tr><td colspan=3>Not found</td></tr>';
            }
            ?>
        </tbody>
    </table>
</div>
<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
<style>
    .change-details tbody tr:nth-child(n+6) {
        display: none;
    }

    .change-details.expanded tbody tr {
        display: table-row;
    }

    .view-toggle-btn {
        margin-top: 8px;
        padding: 4px 12px;
        background: #0073aa;
        color: white;
        border: none;
        border-radius: 3px;
        cursor: pointer;
        font-size: 12px;
    }

    .view-toggle-btn:hover {
        background: #005a87;
    }
</style>

<script>
    jQuery(function($) {
        $('.change-details').each(function() {
            var $table = $(this);
            var $rows = $table.find('.change-tbody > tr');
            var totalRows = $rows.length;

            if (totalRows > 5) {
                var $toggleBtn = $('<button type="button" class="view-toggle-btn">View More (' + (totalRows - 5) + ' more)</button>');

                $table.parent().append($toggleBtn);

                $toggleBtn.on('click', function() {
                    if ($table.hasClass('expanded')) {
                        $table.removeClass('expanded');
                        $(this).text('View More (' + (totalRows - 5) + ' more)');
                    } else {
                        $table.addClass('expanded');
                        $(this).text('View Less');
                    }
                });
            }
        });
    });
</script>

<?php

function render_log_data($json_data, $base_url = '/your-path', $tab)
{
    $data = json_decode($json_data, true);

    if (!$data || !is_array($data)) {
        return esc_html($json_data);
    }

    $id = isset($data['id']) ? $data['id'] : '';
    $name = isset($data['name']) ? $data['name'] : '';

    $changes = $data;
    unset($changes['id']);
    unset($changes['name']);

    $html = '';

    if ($id && $name) {
        if (!empty($changes) && isset($changes['log_type'])) {
            $base_url = '/wp-admin/admin.php?page=ci_pdf_data&pagetab=ci_test_panels&tabtype=' . $changes['log_type'] . '&type=' . $tab . '&act=edit';
        }
        $url = add_query_arg('id', $id, $base_url);
        $html .= '<a href="' . $url . '" target="_blank" class="log-detail-link">' . esc_html($name) . '</a>';
    }

    if (!empty($changes)) {
        $html .= '<table class="change-details" style="margin-top: 8px; width: 100%; border-collapse: collapse; font-size: 12px;">';
        $html .= '<thead><tr>';
        $html .= '<th style="border: 1px solid #ddd; padding: 4px 8px; text-align: left; background: #f5f5f5;">Field</th>';
        $html .= '<th style="border: 1px solid #ddd; padding: 4px 8px; text-align: left; background: #f5f5f5;">Old</th>';
        $html .= '<th style="border: 1px solid #ddd; padding: 4px 8px; text-align: left; background: #f5f5f5;">New</th>';
        $html .= '</tr></thead><tbody class="change-tbody">';

        foreach ($changes as $field => $values) {

            if ($field == 'log_type') continue;
            if ($field === 'cutoffs') {
                $old_cutoffs = isset($values[0]) && is_array($values[0]) ? $values[0] : array();
                $new_cutoffs = isset($values[1]) && is_array($values[1]) ? $values[1] : array();

                $html .= '<tr>';
                $html .= '<td style="border: 1px solid #ddd; padding: 4px 8px; vertical-align: top;"><strong>cutoffs</strong></td>';
                $html .= '<td style="border: 1px solid #ddd; padding: 4px 8px; vertical-align: top;">' . render_cutoffs_table($old_cutoffs) . '</td>';
                $html .= '<td style="border: 1px solid #ddd; padding: 4px 8px; vertical-align: top;">' . render_cutoffs_table($new_cutoffs) . '</td>';
                $html .= '</tr>';
                continue;
            }
            $old_value = '';
            $new_value = '';

            if (is_array($values)) {
                $old_value = isset($values[0]) ? format_value($values[0]) : '';
                $new_value = isset($values[1]) ? format_value($values[1]) : '';
            } else {
                $old_value = format_value($values);
            }

            $html .= '<tr>';
            $html .= '<td style="border: 1px solid #ddd; padding: 4px 8px;"><strong>' . esc_html($field) . '</strong></td>';
            $html .= '<td style="border: 1px solid #ddd; padding: 4px 8px;">' . $old_value . '</td>';
            $html .= '<td style="border: 1px solid #ddd; padding: 4px 8px;">' . $new_value . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
    }

    return $html;
}
function render_cutoffs_table($cutoffs)
{
    if (empty($cutoffs) || !is_array($cutoffs)) {
        return '<em style="color: #999;">—</em>';
    }

    $fields = array();
    foreach ($cutoffs as $cut) {
        if (is_array($cut)) {
            foreach (array_keys($cut) as $k) {
                if (!in_array($k, $fields)) {
                    $fields[] = $k;
                }
            }
        }
    }

    if (empty($fields)) {
        return '<em style="color: #999;">—</em>';
    }

    $field_labels = array(
        'cu_ms'            => 'Instrument',
        'cu_cutoff'        => 'Cutoff',
        'cu_upper_limit'   => 'Upper Limit',
        'cu_medium'        => 'Medium',
        'cu_critical_low'  => 'Critical Low',
        'cu_critical_high' => 'Critical High',
        'cu_age'           => 'Age',
    );

    $th_style  = 'border: 1px solid #ddd; padding: 3px 6px; background: #f9f9f9; white-space: nowrap; font-size: 11px;';
    $td_style  = 'border: 1px solid #ddd; padding: 3px 6px; font-size: 11px; white-space: nowrap;';

    $html  = '<table style="border-collapse: collapse; width: 100%;">';
    $html .= '<thead><tr>';
    foreach ($fields as $f) {
        $label = isset($field_labels[$f]) ? $field_labels[$f] : $f;
        $html .= '<th style="' . $th_style . '">' . esc_html($label) . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    foreach ($cutoffs as $cut) {
        if (!is_array($cut)) continue;
        $html .= '<tr>';
        foreach ($fields as $f) {
            $val   = isset($cut[$f]) ? $cut[$f] : '';
            $html .= '<td style="' . $td_style . '">' . esc_html($val) . '</td>';
        }
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    return $html;
}
function format_value($value)
{
    if (is_array($value)) {
        return esc_html(implode(', ', $value));
    } elseif (is_bool($value)) {
        return $value ? '<span style="color: green;">Yes</span>' : '<span style="color: red;">No</span>';
    } elseif (is_null($value)) {
        return '<em style="color: #999;">null</em>';
    } else {
        return esc_html($value);
    }
}

?>