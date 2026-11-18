<?php
global $wpdb;

$current_time = time()+(get_option( 'gmt_offset' ) * HOUR_IN_SECONDS);

$batches = $wpdb->get_results("select ba.*,re.* from ci_batches ba
                            JOIN ci_receiving_batches rb ON rb.mid = ba.mid
                            JOIN ci_receiving re ON re.re_ID = rb.re_ID
                            Where ba.batch_status= 'received' and ba.batch_id = 0
                            group by ba.mid
                            order by field(re.re_priority,'high','med','low',''), re.re_timing asc" );
?>
<br>
<form method="get">
    <input type="hidden" name="page" value="<?php echo $_GET['page']?>"/>
    <input type="hidden" name="tab" value="<?php echo $_GET['tab']?>"/>
    <label>Date:</label>
    <input type="text" class="hasdate" name="date" value="<?php echo $date?>"/>
    <label>Accession</label>
    <input type="text" name="assen" value="<?php echo $assen?>" placeholder="Search by Accession"/>
    <input type="submit" class="button bsty6" value="Search"/>
</form>
<br>

<div class="wraptbl">
<table class="wp-list-table widefat striped fixed posts blue_tbl hover_tbl ">
    <thead>
    <tr>
        <th style="width:50px"></th>
        <th>Batch Date</th>
        <th>Barcode</th>
        <th>Patient Name</th>
        <th>Office</th>
        <th>Doctor</th>
        <th>Priority</th>
        <th>Batch</th>
    </tr>
    </thead>
    <?php
    $batches_color = array('red'=>array(),'orange'=>array(),'blue'=>array());
    foreach($batches as $batch) {
        $receiving_time = strtotime($batch->spec_received);
        $end_time = $receiving_time + ($batch->re_timing * 3600);
        $color = '';
        if ($batch->re_priority == 'high') {
            $color = 'red';
        } else if ($batch->re_priority == 'med') {
            $color = 'orange';
            //check hours
            if ($batch->re_timing == '24') {
                if ($end_time - $current_time < (12 * 3600)) {
                    $color = 'red';
                }

            } else {
                if ($end_time - $current_time < (24 * 3600)) {
                    $color = 'red';
                }
            }
        } else if ($batch->re_priority == 'low') {
            $color = 'blue';
            //check hours
            if ($end_time - $current_time < ($batch->re_timing / 4 * 3600)) {
                $color = 'red';
            } else if ($end_time - $current_time < ($batch->re_timing / 2 * 3600)) {
                $color = 'orange';
            }

        }
        $batches_color[$color][] = $batch;
    }

    foreach($batches_color as $color => $batches)
    {
        foreach($batches as $batch) {
            ?>
            <tr>
                <td style="background:<?php echo $color ?>"></td>
                <td title="<?php echo $batch->mid?>"><?php echo $batch->spec_received ?></td>
                <td><?php echo $batch->assen ?></td>
                <td><?php echo $batch->patient_name ?></td>
                <td><?php echo $batch->requesting_practice ?></td>
                <td><?php echo $batch->requesting_physician ?></td>
                <td><?php echo $batch->re_priority ?></td>
                <td>
                    <?php if(!empty($batch->batch_id)):?>
                        <?php echo $batch->batch_id?>
                    <?php else:?>
                        <a class="button add_to_batch" data-id="<?php echo $batch->mid?>">Add to Batch</a>
                    <?php endif;?>
                </td>
            </tr>
            <?php
        }
    }
    ?>
</table>
</div>

<script>
    jQuery(function($){
        $('a.add_to_batch').click(function(){
            var $pr = $(this).parent();
            $.ajax({
                data:{tr_action:'ci_add_to_current_batch',mid:$(this).data('id')},
                type:'post',
                dataType:'json',
                success:function(rs)
                {
                    $pr.html(rs.batch_id);
                }
            })
        })
    })
</script>