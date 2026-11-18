<?php
global $wpdb;

$pl_ID = $_REQUEST['id'];


if (isset($_POST['platedata'])) {

    $return = array('status' => 'ok','bg'=>array());

    $plate = $wpdb->get_row("select * 
    from ci_plates pl
    left join ci_racks ra ON ra.re_ID = pl.pl_rack_ID
    where pl_ID = {$pl_ID} ", ARRAY_A);

    $plate['other_data'] = json_decode($plate['other_data'], true);
    
    if (!is_array($plate['other_data'])) $plate['other_data'] = array();

    $platedata = isset($_POST['platedata']) && is_array($_POST['platedata'])? $_POST['platedata'] : array();
    $rackdata  = isset($_POST['rackdata']) && is_array($_POST['rackdata'])? $_POST['rackdata'] : array();

    $plate['other_data']        = array_merge($plate['other_data'], $platedata['other_data']);
    $platedata['other_data']    = json_encode($plate['other_data']);
    

    if(count($platedata)>0 && $pl_ID>0)
    {
        $wpdb->update('ci_plates', $platedata, array('pl_ID' => $pl_ID));
    }
    

    if(count($rackdata)>0 && $plate['re_ID']>0)
    {
        $wpdb->update('ci_racks', $rackdata, array('re_ID' => $plate['re_ID']));
    }
    

    $return['data'] = array();
   

    $return['bg']['exclude'] = isset($plate['other_data']['excludes']) && is_array($plate['other_data']['excludes'])? $plate['other_data']['excludes'] : array();
    $return['bg']['cracked'] = isset($plate['other_data']['cracked']) && $plate['other_data']['cracked'] && isset($plate['other_data']['cracked_channel'])? explode(',',$plate['other_data']['cracked_channel']) : array();
    $return['bg']['noflows'] = isset($plate['other_data']['noflow']) && $plate['other_data']['noflow'] && isset($plate['other_data']['noflow_channel'])? explode(',',$plate['other_data']['noflow_channel']) : array();
    $return['bg']['aggregates'] = isset($plate['other_data']['aggregate']) && $plate['other_data']['aggregate'] && isset($plate['other_data']['aggregate_channel'])? explode(',',$plate['other_data']['aggregate_channel']) : array();
    $return['bg']['other'] = isset($plate['other_data']['other']) && $plate['other_data']['other'] && isset($plate['other_data']['other_channel'])? explode(',',$plate['other_data']['other_channel']) : array();

    wp_send_json($return);
}

$plate = $wpdb->get_row("select * 
from ci_plates pl
left join ci_racks ra ON ra.re_ID = pl.pl_rack_ID
where pl_ID = {$pl_ID} ", ARRAY_A);

$plate['other_data'] = json_decode($plate['other_data'], true);
if (!is_array($plate['other_data'])) $plate['other_data'] = array();
if (empty($plate['other_data']['expiration_date']) && !empty($plate['re_priority'])) {
    $plate['other_data']['expiration_date'] = date('Y-m-d', strtotime($plate['re_priority']) + 86400 * 7);
}

$issues_arr = array(
    'cracked' => 'Cracked Plate',
    'noflow' => 'No Flow Channel',
    'aggregate' => 'Aggregate in Channel',
    'other' => 'Other',
);
?>
<form method="post" id="plate_form">
    <div class="frow">
        <div class="col2">
            <div class="frow"><b>Issue</b></div>
            <?php
            foreach ($issues_arr as $isk => $ist) {
                echo '<div class="frow frowissue"><label>';
                echo '<input type="hidden" class="'.$isk.'_issue_ck" name="platedata[other_data][' . $isk . ']" value="0" />';
                echo '<input type="checkbox" class="'.$isk.'_issue_ck" name="platedata[other_data][' . $isk . ']" value="1" ' . checked($plate['other_data'][$isk], '1', false) . '>';
                echo ' ' . $ist;
                echo '</label>';

                echo '<input type="text" class="issue_channel" name="platedata[other_data]['.$isk.'_channel]" value="'.$plate['other_data'][$isk.'_channel'].'" style="width:30%" placeholder="Channel"/>';
                
                if($isk=='other'){
                    echo '<div class="frow issue_other hidden">';
                    echo '<input type="text" placeholder="Other Desc" name="platedata[other_data][issue_other]" value="'.$plate['other_data']['issue_other'].'"/>';
                    echo '</div>';
                }
                echo '</div>';
            }
            ?>
        </div>
        <div class="col2">
            <div class="frow"><b>Excludes</b></div>
            <input type="hidden" name="platedata[other_data][excludes]" value=""/>
            <select name="platedata[other_data][excludes][]" multiple class="select2opt">
                <?php
                for($i=1;$i<=24;$i++)
                {
                    $selected = in_array($i,$plate['other_data']['excludes'])?'selected':'';
                    echo '<option value="'.$i.'" '.$selected.'>'.$i.'</option>';
                }
                ?>
            </select>
        </div>
    </div>

    <div class="frow">
        <input type="submit" class="button-primary" value="Save" />
        <input type="hidden" name="trajax" value="issue_exclude" />
        <input type="hidden" name="id" value="<?php echo $pl_ID ?>" />
    </div>
</form>
<style>
    input.issue_channel{
        height:20px !important;
        min-height: 20px !important;
        margin-left:15px !important;
    }
    .select2-container.select2-container--open{
        z-index: 999999;
    }
    </style>
<script>
    jQuery(function($) {
        $('.frowissue').each(function(){
            var $this = $(this);
            $('label input[type="checkbox"]',$this).change(function(){
                if($(this).is(':checked')){
                    $('.issue_channel',$this).show();
                }else{
                    $('.issue_channel',$this).hide();
                }
            }).change();
        })
        $('#plate_form .hasdate').each($.tr_setup_datef);
        $('.select2opt').select2();
        $('.other_issue_ck').change(function(){
            if($(this).is(':checked')){
                $('div.issue_other').show();
            }else{
                $('div.issue_other').hide();
            }
        })
        $('.other_issue_ck').change();

        $('#plate_form').submit(function() {
            var $block = $('.plate_96h[data-id="<?php echo $pl_ID ?>"]');
            var $btn = $('[type="submit"]', $(this));
            $btn.val('Saving...');
            $.ajax({
                data: $(this).serialize(),
                type: 'post',
                dataType: 'json',
                success: function(rs) {
                    $btn.val('Saved');
                    if (rs.data) {
                        for (i in rs.data) {
                            $('.' + i, $block).html(rs.data[i]);
                        }
                    }
                    if(rs.bg)
                    {
                        $('th,td',$block).removeClass('exclude_bg cracked_bg noflows_bg aggregates_bg other_bg');
                        for(i in rs.bg)
                        {
                            for(j in rs.bg[i]){
                                $('tr .chanel_'+rs.bg[i][j],$block).addClass(i+'_bg');
                            }
                        }
                    }
                    setTimeout(function() {
                        tb_remove();
                    }, 1000);
                },
                error: function(rs) {
                    alert('Error');
                    $btn.val('Save');
                }
            });
            return false;
        })
    })
</script>