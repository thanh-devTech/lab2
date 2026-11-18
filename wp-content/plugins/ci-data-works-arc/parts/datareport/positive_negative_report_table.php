<?php
$num_fcolumns = 0;
?>
<table class="wp-list-table widefat striped posts blue_tbl hover_tbl border_tbl">
    <thead>
        <tr>
            <?php
            foreach ($headers as $k => $col) {
                $styleth = '';
                if (in_array($k, array('pa_dob', 'pa_gender', 'age', 'phase'))) {
                    $styleth .= 'width:80px';
                } else if ($k == 'doc') {
                    $styleth .= 'width:100px';
                } else if ($k == 'assen') {
                    $styleth .= 'width:100px';
                }

                if (stripos($k, 'result_') !== false || isset($export_pdf)) {
                    echo '<th style="' . $styleth . '">' . $col . '</th>';
                } else {
                    tr_tbl_sort($k, $col, '', $styleth);
                    $num_fcolumns++;
                }
            }
            ?>
        </tr>
    </thead>
    <tbody>
        <?php
        $count_pos_arr = array();
        foreach ($reports as $row) {
            $rowhtml = '<tr>';
            $has_pos = true;
            foreach ($headers as $k => $col) {

                $class = in_array($k, array('age', 'pa_dob', 'phase', 'pa_gender', 'assen')) ? ' center ' : '';
                if (strpos($k, 'result_') !== false) {
                    $class .= ' center ';
                    $drug = str_ireplace('result_', '', $k);
                    $pos_neg = $row->{'pos_neg_' . $drug};
                    if (!isset($count_pos_arr[$k])) {
                        $count_pos_arr[$k] = 0;
                    }
                    if (in_array($pos_neg, array('POS', 'POH', 'POM', 'POL', 'PVL', 'PVH'))) {
                        $count_pos_arr[$k]++;
                        $has_pos = true;
                    }
                }
                $rowhtml .= '<td class="' . $class . '">';
                $after_vl = '';
                if ($k == 'assen' && !isset($export_pdf)) {
                    $rowhtml .= '<a class="thickbox" href="?page=ci_datareport_positive_negative_report&act=view&mid=' . $row->mid . '&btype=' . $btype_selected . '&trajax=1&width=1200" target=_blank>';
                    $after_vl = '</a>';
                }
                $rowhtml .= $row->{$k};
                $rowhtml .= $after_vl;
                $rowhtml .= '</td>';
            }
            $rowhtml .= '</tr>';
            $pass_row = true;
            if (isset($onlypos) && $onlypos && !$has_pos) {
                $pass_row = false;
            }
            if ($pass_row) {
                echo $rowhtml;
            }
        }
        if (count($reports) == 0) {
            echo '<tr><td colspan=' . count($headers) . ' class="center">Not found</td></tr>';
        } else {
            //show percent pos
            echo '<tr>';
            echo '<th style="text-align:right" colspan="' . $num_fcolumns . '">Percent of Positive: </th>';
            foreach ($headers as $k => $col) {
                if (stripos($k, 'result_') !== false) {
                    $percent = number_format($count_pos_arr[$k] / $total_reports * 100, 2);
                    echo '<td class="center">' . $percent . '%</td>';
                }
            }
            echo '</tr>';
        }
        ?>
    </tbody>
</table>