
<?php

global $wpdb;

$archived_batchs = $wpdb->get_results("SELECT mid, btype, created FROM ci_log_users WHERE user_action = 'archived'");

if ($archived_batchs) {
    foreach ($archived_batchs as $bat) {
        $mid   = (int) $bat->mid;
        $btype = sanitize_key($bat->btype);

        if (!$mid || !$btype) {
            continue;
        }

        $batch_tbl = tr_pdd_get_table_by_btype($btype);
        if (!$batch_tbl) {
            continue;
        }

        $wpdb->update(
            $batch_tbl,
            ['archived_date' => $bat->created],
            ['mid' => $mid],
            ['%s'],
            ['%d']
        );
    }
}
