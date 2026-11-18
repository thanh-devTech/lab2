<?php
global $wpdb;

$plate_ID2   = $wpdb->get_var($sql_plate_id . " and pl_blank1_text = 'plateB' ");
if (count($batches_more['B']) > 0) {
    $arr2 = $arr;
    unset($arr2['pl_ID']);
    unset($arr2['pl_ntc_text']);
    unset($arr2['pl_ntc2_text']);
    unset($arr2['pl_ntc3_text']);

    $arr2['pl_blank1_text'] = 'plateB';
    $arr2['pl_ntc_position'] = 0;
    $arr2['pl_ntc2_position'] = 0;
    $arr2['pl_ntc3_position'] = 0;


    if ($plate_ID2 == 0) {
        $pl_number = $wpdb->get_var("select max(pl_number) from ci_plates where pl_today ='{$rack->re_today}' ") + 1;

        $arr2['pl_rack_ID'] = $re_ID;
        $arr2['pl_today']  = $rack->re_today;
        $arr2['pl_number'] = $pl_number;
        $arr2['created']   = $datetime_now;
        $arr2['created_by'] = $user_id;

        $wpdb->insert('ci_plates', $arr2);
        $plate_ID2 = $wpdb->insert_id;
    } else {
        $wpdb->update('ci_plates', $arr2, array('pl_ID' => $plate_ID2));
    }

    if ($plate_ID2 > 0) {
        $exist_rows_rs = $wpdb->get_results("select ID,mid 
						from ci_plates_batches 
						where pl_ID = {$plate_ID2}");
        $exist_rows = array();
        foreach ($exist_rows_rs as $erow) {
            $exist_rows[$erow->mid] = $erow;
        }
        $parentplb = array();
        $position = 0;
        foreach ($batches_more['B'] as $batch) {
            $position++;
            $rowpb = isset($exist_rows[$batch->mid]) ? $exist_rows[$batch->mid] : false;
            $pbid = $rowpb && $rowpb->ID > 0 ? $rowpb->ID : 0;
            if ($pbid > 0) {
                $updatedata = array(
                    'position' => $position
                );

                $wpdb->update('ci_plates_batches', $updatedata, array('ID' => $pbid));
            } else {

                $insertdata = array(
                    'pl_ID'      => $plate_ID2,
                    'mid'        => $batch->mid,
                    'btype'      => $batch->btype,
                    'position'   => $position,
                    'created'    => $datetime_now,
                    'created_by' => $user_id,
                );
                if (isset($batch->is_rerun) && $batch->is_rerun) {
                    $insertdata['is_rerun'] = $batch->is_rerun;
                }

                $wpdb->insert('ci_plates_batches', $insertdata);
                $pbid = $wpdb->insert_id;
            }

            $parentplb[$batch->ID] = $pbid;
        }

        $notinids = implode(',', $parentplb);
        $wpdb->query("DELETE FROM ci_plates_batches where pl_ID = {$plate_ID2}
						and ID not in ({$notinids})");
    }
}
