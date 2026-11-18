<?php
wp_enqueue_style( 'jquery-ui', TRCPDD_URL. 'css/jquery-ui.css',array(),'1.2' );
wp_enqueue_script('jquery-ui-autocomplete');
wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_script('jquery-ui-dialog');

wp_enqueue_script('data-admin',TRCPDD_URL. 'js/admin.min.js',array(),TRCPDD_VS );



$from = isset($_GET['from']) ? $_GET['from'] : date_i18n('Y-m-d');
$to   = isset($_GET['to']) ? $_GET['to'] : date_i18n('Y-m-d');

if(!empty($from))
{
    $where = " re_priority >= '{$from}' ";
    $where .= " and re_priority <= '{$to}' ";

    $sql = "select * 
    from ci_plates pl
    join ci_racks ra ON ra.re_ID = pl.pl_rack_ID
    where {$where}
    order by re_priority desc";
    $rows = $wpdb->get_results($sql);
}
?>
<div class="wrap">
    <h2>Plate Log</h2>
    <form method="get">
        <input type="hidden" name="page" value="<?php echo $_GET['page']?>"/>
        <input type="hidden" name="tab" value="<?php echo isset($_GET['tab'])? $_GET['tab'] : ''?>"/>
        <label>From:</label>
        <input type="search" class="hasdate" name="from" value="<?php echo $from?>"/>
        <label>To:</label>
        <input type="search" class="hasdate" name="to" value="<?php echo $to?>"/>
        
        <input type="submit" class="button-primary" value="Filter"/>
    </form>

    <div class="wraptbl">
        <table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl" style="min-width:1200px">
        <thead class="sticky">
            <tr>
                <th>Plate</th>
                <th>Serial Number</th>
                <th>Coating</th>
                <th>Expiration</th>
                <th>VCam1 Lot</th>
                <th>VCam1 Prep</th>
                <th>P-Selectin Lot</th>
                <th>P-Selectin Prep</th>
                <th>HBSS Lot</th>
                <th>HBSS Prep</th>
                <th>Psel Lot</th>
                <th>Psel Prep</th>
                <th>PBS Lot</th>
                <th>PBS Expiration</th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach($rows as $row)
            {
                $row->other_data = json_decode($row->other_data,true);
                if(empty($row->other_data['expiration_date']) && !empty($row->re_priority))
                {
                    $row->other_data['expiration_date'] = date('Y-m-d',strtotime($row->re_priority)+86400*7);
                }
                ?>
                <tr class="childp">
                    <td><?php echo $row->re_subtitle?></td>
                    <td><?php echo $row->re_barcode?></td>
                    <td><?php echo tr_pdd_display_date($row->re_priority)?></td>
                    <td><?php echo tr_pdd_display_date($row->other_data['expiration_date'])?></td>
                    <td><?php echo $row->other_data['VCam1']?></td>
                    <td><?php echo tr_pdd_display_date($row->other_data['VCam1_date'])?></td>
                    <td><?php echo $row->other_data['PSelectin']?></td>
                    <td><?php echo tr_pdd_display_date($row->other_data['PSelectin_date'])?></td>
                    <td><?php echo $row->other_data['HBSS']?></td>
                    <td><?php echo tr_pdd_display_date($row->other_data['HBSS_date'])?></td>
                    <td><?php echo $row->other_data['Psel']?></td>
                    <td><?php echo tr_pdd_display_date($row->other_data['Psel_date'])?></td>
                    <td><?php echo $row->other_data['PBS']?></td>
                    <td><?php echo tr_pdd_display_date($row->other_data['PBS_date'])?></td>
                </tr>
                <?php
            }
            ?>
        </tbody>
        </table>
    </div>
</div>