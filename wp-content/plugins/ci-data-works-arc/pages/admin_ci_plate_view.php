<?php
global $wpdb;

include_once(TRCPDD_PATH.'pages/formdata.php');

$instruments = $wpdb->get_col("select instrument from `ci_instruments` order by instrument asc");


$date = $_REQUEST['date'];
$assen = $_REQUEST['assen'];
$instrument_selected = $_REQUEST['inst'];
$plate_number = $_REQUEST['pln'];
$batch_id = $_REQUEST['bid'];
$sumited = false;

if(!empty($date) || !empty($assen) || !empty($batch_id) )
{

    $sql = "select pl.*,lab.lab_dba_init as lab_name
                             from ci_plates pl
                             JOIN ci_labs as lab On lab.lab_ID = pl.pl_lab_ID
                             where pl.pl_type = 0 ";

    if(!empty($date))
    {
        $sql.= " and pl.pl_today='{$date}'  ";
    }

    if($plate_number>0)
    {
        $sql.= " and pl.pl_number = {$plate_number} ";
    }

    if(!empty($instrument_selected))
    {
        $sql.= " and pl.pl_instrument = '{$instrument_selected}' ";
    }

    if(!empty($batch_id))
    {
        $sql.= " and pl.pl_batch_ID = '{$batch_id}' ";
    }

    if(!empty($assen))
    {
        $sql_childs = array();

        foreach(tr_pdd_get_batch_types() as $btype)
        {
            if(in_array($btype, array('study')))continue;
                    
            $batch_tbl = tr_pdd_get_table_by_btype($btype);

            $sql_childs[] = "
            ( select pl_ID from ci_plates_batches rb 
            join {$batch_tbl} ba ON ba.mid = rb.mid 
            where assen like '{$assen}%' and rb.btype='{$btype}') 
            ";
        }

        $sql_childs = implode(' UNION ALL ', $sql_childs );

        $pl_ids = $wpdb->get_col("select pl_ID from ({$sql_childs}) as allre limit 10");
        $pl_ids = implode(',',$pl_ids);
        if(empty($pl_ids)){
            $pl_ids = '-1';
        }
        $sql.= " and pl.pl_ID in ({$pl_ids}) ";

    }
    if(CI_Base_Lab::has_user_labs())
    {
        $lab_str = CI_Base_Lab::get_user_lab_ids_str();
        $sql.= " and pl_lab_ID in ( {$lab_str} ) ";
    }

    if(isset($_GET['debug'])){
        echo $sql;
    }
    $results = $wpdb->get_results($sql, ARRAY_A);

    $sumited = true;
}

?>
<div class="wrap">
    <form method="get">
        <input type="hidden" name="page" value="<?php echo $_GET['page']?>"/>
        <input type="hidden" name="tab" value="<?php echo $_GET['tab']?>"/>
        <label>Date:</label>
        <input type="search" class="hasdate" name="date" value="<?php echo $date?>"/>
        <label>Batch</label>
        <input type="search" name="bid" value="<?php echo $batch_id?>" placeholder="Batch"/>
        <label>Accession #:</label>
        <input type="search" name="assen" value="<?php echo $assen?>" placeholder="Accession #"/>
        <select name="inst"  >
            <option value="">Select Instrument</option>
            <?php foreach($instruments as $instrument){
                $selected = $instrument==@$instrument_selected? 'selected':'';
                echo '<option value="'.$instrument.'" '.$selected.'>'.$instrument.'</option>';
            }?>
        </select>
        <input type="submit" class="button bsty6" value="Search"/>
    </form>

    <?php
    if(count($results)>0) {
        foreach ($results as $i => $row) {
            $sql = "
            select * from (
                    (select ba.mid,ba.type_of_specimen,ba.initials1,ba.ref_from_lab_ID,ba.position,ba.assen,ba.doc,ba.patient_name,
                    ba.requesting_practice,ba.requesting_physician,
                    rb.btype 
                    from ci_batches ba
                        JOIN ci_plates_batches rb ON rb.mid=ba.mid and rb.btype='tox'
                        where rb.pl_ID = '{$row['pl_ID']}' ) 
                        
                    UNION ALL
                    
                    (select ba.mid,ba.type_of_specimen,ba.initials1,ba.ref_from_lab_ID,ba.position,ba.assen,ba.doc,ba.patient_name,
                    ba.requesting_practice,ba.requesting_physician,
                    rb.btype 
                    from ci_batches_blood ba
                        JOIN ci_plates_batches rb ON rb.mid=ba.mid and rb.btype='blood'
                        where rb.pl_ID = '{$row['pl_ID']}' )
                        
                        UNION ALL
                    
                    (select ba.mid,ba.type_of_specimen,ba.initials1,ba.ref_from_lab_ID,ba.position,ba.assen,ba.doc,ba.patient_name,
                    ba.requesting_practice,ba.requesting_physician,
                    rb.btype 
                    from ci_batches_dna ba
                        JOIN ci_plates_batches rb ON rb.mid=ba.mid and rb.btype='dna'
                        where rb.pl_ID = '{$row['pl_ID']}' )
                        
                         UNION ALL
                    
                    (select ba.mid,ba.type_of_specimen,ba.initials1,ba.ref_from_lab_ID,ba.position,ba.assen,ba.doc,ba.patient_name,
                    ba.requesting_practice,ba.requesting_physician,
                    rb.btype 
                    from ci_batches_ngs ba
                        JOIN ci_plates_batches rb ON rb.mid=ba.mid and rb.btype='ngs'
                        where rb.pl_ID = '{$row['pl_ID']}' )
                        
                ) as ball
                order by cast(ball.position as unsigned) desc";

            $batches = $wpdb->get_results($sql);
            ?>

            <table class="posts plate_tbl">
                <tr>
                    <td colspan="12" class="headerb">
                        <table>
                            <tr>
                                <th><?php echo $row['pl_number'] ?></th>
                                <th><?php echo $row['lab_name'] ?></th>
                                <th><?php echo $row['pl_instrument'] ?></th>
                                <th><?php echo $row['pl_today'] ?></th>
                                <th><?php echo $row['pl_batch_ID'] ?></th>
                                <th><?php echo count($batches) ?></th>
                            </tr>
                        </table>
                    </td>
                </tr>
                <?php
                $jj = 0;
                echo '<tr class="childp">';
                foreach ($batches as $batch) {
                    $class = '';
                    if (!empty($assen) && stripos($batch->assen, $assen) !== false) {
                        $class = 'found_assen';
                    }
                    if ($jj % 12 == 0 && $jj > 0) {
                        echo '</tr>';
                        echo '<tr class="childp">';
                    }
                    ?>
                    <td class=" assen_<?php echo $batch->assen . ' ' . $class ?>">
                        <div class="row">
                            <span class="pnumber"><?php echo $jj + 1 ?></span>
                        </div>
                        <div class="row">
                            <span class="assen"><?php echo $batch->assen ?></span>
                        </div>

                    </td>
                    <?php
                    $jj++;
                }
                for ($jj; $jj < 96; $jj++) {
                    if ($jj % 12 == 0 && $jj > 0) {
                        echo '</tr>';
                        echo '<tr class="childp">';
                    }
                    echo '<td><span class="pnumber">' . ($jj + 1) . '</span></td>';
                }
                echo '</tr>';
                ?>
            </table>
            <br><br>
            <?php
        }
    }else{
        ?>
        <p>No Results Found</p>
    <?php
    }
    ?>
</div>
<style>
    .plate_tbl{
        width:100%;
        background:#fff
    }
    .plate_tbl .headerb{
        border:1px solid #ddd;
    }
    .plate_tbl .headerb table{
        width:100%;
    }
    .plate_tbl tbody tr.childp td.found_assen{
        background:yellow !important;
    }
    .plate_tbl .childp td{
        border:1px solid #ddd !important;
        min-height:80px;
        height:80px;
        width:8.3% !important;
        vertical-align: top;
        padding:2px !important;
    }
    .plate_tbl .row{
        position: relative;
        clear: both;
    }
    .plate_tbl .childp .pnumber{
        float: left;
    }
    .plate_tbl .childp .assen{
        float:right;
        color:blue;
    }
    table .tright{
        text-align: right !important;
    }
</style>
