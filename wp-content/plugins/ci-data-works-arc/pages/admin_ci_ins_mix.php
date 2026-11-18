<?php
global $wpdb;


$rows = $wpdb->get_results("select * from ci_payer_list ");

$settings       = [];
if (function_exists('tr_billing_get_settings')) $settings =  tr_billing_get_settings();
$enable_comm_kit = $settings['enable_commercial_kit'] == 1;

function tr_payer_vl($row, $k)
{
    $vl = $row->{$k};
    $vl = $vl == '1' ? 'yes' : 'no';
    $id = $row->payer_ID;
    return '<a class="change_status vl_' . $vl . '" data-id="' . $id . '" data-k="' . $k . '">' . $vl . '</a>';
}
?>
<h2>Insurance Mix</h2>
<div class="wraptbl">
    <table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl">
        <thead class="sticky">
            <tr>
                <th style="width:40%">Insurance</th>
                <th class="center">Commercial</th>
                <?php if ($enable_comm_kit) { ?>
                    <th class="center">Commercial Kit</th>
                <?php } ?>
                <th class="center">Medicaid</th>
                <th class="center">Unknown</th>
                <th class="center">Medicare</th>
                <th class="center">Workmen Comp</th>
                <th class="center">Other</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo $row->payer_name ?></td>
                    <td class="center"><?php echo tr_payer_vl($row, 'payer_commercial') ?></td>
                    <?php if ($enable_comm_kit) { ?>
                        <td class="center"><?php echo tr_payer_vl($row, 'payer_commercial_kit') ?></td>
                    <?php } ?>
                    <td class="center"><?php echo tr_payer_vl($row, 'payer_medicaid') ?></td>
                    <td class="center"><?php echo tr_payer_vl($row, 'payer_unknown') ?></td>
                    <td class="center"><?php echo tr_payer_vl($row, 'payer_medicare') ?></td>
                    <td class="center"><?php echo tr_payer_vl($row, 'payer_workmen') ?></td>
                    <td class="center"><?php echo tr_payer_vl($row, 'payer_other') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
    jQuery(function($) {

        $('a.change_status').click(function() {
            if (confirm('Do you want change?') == false) return false;
            var $this = $(this);
            var $vl = $this.html();
            if ($vl == 'saving...') return false;
            $this.html('saving...');
            $.post(ajaxurl, {
                action: 'trajax',
                tr_action: 'save_payer_mix',
                id: $(this).data('id'),
                vl: $vl,
                k: $(this).data('k')
            }, function(rs) {
                $this.html(rs.html);
                $this.removeClass(rs.classold);
                $this.addClass(rs.classnew);
            }, 'json');
            return false;
        })
    })
</script>