<?php
global $wpdb;

$plate_ID_acc   = $wpdb->get_var($sql_plate_id . " and pl_blank1_text = 'plateAcc' ");
$arr2 = $arr;
unset($arr2['pl_ID']);

$arr2['pl_blank1_text'] = 'plateAcc';
$arr2['pl_ntc_position'] = 58;
$arr2['pl_ntc2_position'] = 59;
$arr2['pl_ntc3_position'] = 60;


if ($plate_ID_acc == 0) {
    $pl_number = $wpdb->get_var("select max(pl_number) from ci_plates where pl_today ='{$rack->re_today}' ") + 1;

    $arr2['pl_rack_ID'] = $re_ID;
    $arr2['pl_today']  = $rack->re_today;
    $arr2['pl_number'] = $pl_number;
    $arr2['created']   = $datetime_now;
    $arr2['created_by'] = $user_id;

    $wpdb->insert('ci_plates', $arr2);
    $plate_ID_acc = $wpdb->insert_id;
} else {
    $wpdb->update('ci_plates', $arr2, array('pl_ID' => $plate_ID_acc));
}

if ($plate_ID_acc > 0) {
    $exist_rows_rs = $wpdb->get_results("select ID,mid 
    from ci_plates_batches 
    where pl_ID = {$plate_ID_acc}");
    $exist_rows = array();
    foreach ($exist_rows_rs as $erow) {
        $exist_rows[$erow->mid] = $erow;
    }
    $parentplb = array();
    $i = 0;
    $a = 0;
    $b = 0;
    $positions = array();
    foreach ($batches as $batch) {
        $i++;
        $adding = false;
        if ($i == 74){$i++;$a++;};
        if ($i == 82){$i++;$a++;};
        if ($i == 90){$i++;$a++;};
        $position = $i;
        if (($i - 1) % 8 >= 4) {
            //blate B
            $b++;
            if($adding)$b++;
            if(($b-1)%4<=1){
                $position = floor($b/4) +(($b-1)%2 * 24*2) + 1 +96;
            }else{
                $position = floor(($b-3)/4) + (($b-1)%2 * 24*2) + 13 +96;
            }
        }else{
            $a++;
            if($adding)$a++;
            if(($a-1)%4<=1){
                $position = floor($a/4) +(($a-1)%2 * 24*2) + 1;
            }else{
                $position = floor(($a-3)/4) + (($a-1)%2 * 24*2) + 13;
            }
        }

        if(in_array($position,$positions))continue;
        $positions[] = $position;

        
        
        $rowpb = isset($exist_rows[$batch->mid]) ? $exist_rows[$batch->mid] : false;
        $pbid = $rowpb && $rowpb->ID > 0 ? $rowpb->ID : 0;
        if ($pbid > 0) {
            $updatedata = array(
                'position' => $position
            );

            $wpdb->update('ci_plates_batches', $updatedata, array('ID' => $pbid));
        } else {

            $insertdata = array(
                'pl_ID'      => $plate_ID_acc,
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
    $wpdb->query("DELETE FROM ci_plates_batches where pl_ID = {$plate_ID_acc}
    and ID not in ({$notinids})");
}//end $plate_ID_acc