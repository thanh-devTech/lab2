<?php
global $wpdb;

$pl_ID = isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;
$can_edit = true;

if (isset($coating_id) && $coating_id > 0) {
    //find plate
    $plate = $wpdb->get_row("select 
    pl.*,
    ra.*,
    pc.ID as coating_ID,
    pc.barcode,
    pc.other_data as pc_other_data,
    pc.service_date,
    pc.type
    from ci_plate_coating pc
    left join ci_racks ra ON ra.re_barcode = pc.barcode
    left join ci_plates pl ON ra.re_ID = pl.pl_rack_ID
    where pc.ID = {$coating_id} ", ARRAY_A);

    $plate['re_priority'] = $plate['service_date'];

    //echo ($wpdb->last_error);
} else if (tr_get_option('ci_pdf_data', 'plate_coating')) {
    $plate = $wpdb->get_row("select
    pl.*,
    ra.*,
    pc.ID as coating_ID,
    pc.barcode,
    pc.other_data as pc_other_data,
    pc.service_date,
    pc.type

    from ci_plates pl
    left join ci_racks ra ON ra.re_ID = pl.pl_rack_ID
    left join ci_plate_coating pc ON ra.re_barcode = pc.barcode
    where pl_ID = {$pl_ID} ", ARRAY_A);
    $can_edit  = false;
} else {


    $plate = $wpdb->get_row("select * 
    from ci_plates pl
    left join ci_racks ra ON ra.re_ID = pl.pl_rack_ID
    where pl_ID = {$pl_ID} ", ARRAY_A);
}

if (empty($plate['re_barcode']) && isset($plate['barcode'])) {
    $plate['re_barcode'] = $plate['barcode'];
}

$plate['old_other_data'] = $wpdb->get_var("select other_data from ci_plates 
where other_data!='' 
order by pl_ID desc 
limit 1");


$plate['other_data']    = json_decode(isset($plate['other_data']) ? $plate['other_data'] : '', true);
$plate['pc_other_data'] = json_decode(isset($plate['pc_other_data']) ? $plate['pc_other_data'] : '', true);
$plate['old_other_data'] = json_decode(isset($plate['old_other_data']) ? $plate['old_other_data'] : '', true);

if (!is_array($plate['other_data'])) $plate['other_data'] = array();
if (!is_array($plate['pc_other_data'])) $plate['pc_other_data'] = array();



foreach ($plate['pc_other_data'] as $k => $vl) {
    if (!isset($plate['other_data'][$k]) || empty($plate['other_data'][$k])) {
        $plate['other_data'][$k] = $vl;
    }
}
foreach ($plate['old_other_data'] as $k => $vl) {

    if (!isset($plate['other_data'][$k]) || empty($plate['other_data'][$k])) {
        $plate['other_data'][$k] = $vl;
    }
}

if (empty($plate['other_data']['expiration_date']) && !empty($plate['re_priority'])) {
    $plate['other_data']['expiration_date'] = date('Y-m-d', strtotime($plate['re_priority']) + 86400 * 7);
}





if (isset($_POST['platedata'])) {

    $return = array('status' => 'ok');


    $platedata = $_POST['platedata'];
    $rackdata  = $_POST['rackdata'];

    $plate['other_data']        = array_merge($plate['other_data'], $platedata['other_data']);
    $platedata['other_data']    = json_encode($plate['other_data']);

    if ($pl_ID > 0) {
        $wpdb->update('ci_plates', $platedata, array('pl_ID' => $pl_ID));
    }

    if ($plate['re_ID'] > 0) {
        $wpdb->update('ci_racks', $rackdata, array('re_ID' => $plate['re_ID']));
    }

    if (isset($plate['coating_ID']) && $plate['coating_ID'] > 0) {
        $coatingdata = array(
            'other_data' => $platedata['other_data']
        );

        $wpdb->update('ci_plate_coating', $coatingdata, array('ID' => $plate['coating_ID']));
    }

    $return['data'] = array();
    if (isset($rackdata['re_priority'])) {
        $return['data']['re_priority'] = $rackdata['re_priority'];
    }
    if (isset($plate['barcode'])) {
        $return['data']['barcode'] = $plate['barcode'];
    }

    wp_send_json($return);
}

$pretitle = isset($plate['type']) ? $plate['type'] : '';
if ($pretitle == 'vcam') {
    $pretitle = 'V';
} else if ($pretitle == 'psel') {
    $pretitle = 'P';
} else if ($pretitle == 'mixed') {
    $pretitle = 'M';
} else if ($pretitle == 'VPV') {
    $pretitle = 'VP';
}

if (empty($pretitle)) {
    $pretitle = preg_replace("/[^VPMT]/", '', $plate['re_subtitle']);
}
$pretitle     = strtoupper($pretitle);


$show_vcam      = false;
$show_psel      = false;
$show_vcam_slot = false;
$show_psel_slot = false;

if (in_array($pretitle, array('V'))) {
    $show_vcam = true;
}
if (in_array($pretitle, array('V', 'VP', 'M'))) {
    $show_vcam_slot = true;
}

if (in_array($pretitle, array('P'))) {
    $show_psel = true;
}
if (in_array($pretitle, array('P', 'VP', 'M'))) {
    $show_psel_slot = true;
}
?>
<form method="post" id="plate_form">
    <div class="frow">
        <div class="col4 fieldtrans">
            <div class="frow"><b>Plate Information</b></div>
            <div class="frow">
                <label>Plate ID</label>
                <input type="text" readonly value="<?php echo $plate['re_subtitle'] ?>" />
            </div>
            <div class="frow">
                <label>Plate Serial Number</label>
                <input type="text" name="rackdata[re_barcode]" value="<?php echo $plate['re_barcode'] ?>" />
            </div>
            <div class="frow">
                <label>Coating Date</label>
                <input type="text" class="coating_date" readonly value="<?php echo $plate['re_priority'] ?>" />
            </div>
            <div class="frow">
                <label>Expiration Date</label>
                <input type="text" class="expiration_date" readonly value="<?php echo $plate['other_data']['expiration_date'] ?>" />
            </div>
        </div>
        <div class="col4">
            <div class="frow"><b>Consumables Information</b></div>
            <?php if ($show_vcam && $show_vcam_slot) { ?>
                <div class="frow">
                    <label>VCam1 Lot #</label>
                    <input type="text" name="platedata[other_data][VCam1]" id="vcam1_slot" value="<?php echo $plate['other_data']['VCam1'] ?>" />
                </div>
                <div class="frow">
                    <label>VCam1 Preparation Date</label>
                    <input type="text" class="hasdate" name="platedata[other_data][VCam1_date]" id="vcam1_date" value="<?php echo $plate['other_data']['VCam1_date'] ?>" />
                </div>
            <?php } ?>
            <?php if ($show_psel && $show_psel_slot) { ?>
                <div class="frow">
                    <label>P-Selectin Lot #</label>
                    <input type="text" name="platedata[other_data][PSelectin]"  value="<?php echo $plate['other_data']['PSelectin'] ?>" />
                </div>
                <div class="frow">
                    <label>P-Selectin Preparation Date</label>
                    <input type="text" class="hasdate" name="platedata[other_data][PSelectin_date]" id="pselectin_date" value="<?php echo $plate['other_data']['PSelectin_date'] ?>" />
                </div>
            <?php } ?>
        </div>
        <div class="col4">
            <div class="frow"><b>&nbsp;</b></div>
            <?php if ($show_vcam_slot) { ?>
                <div class="frow">
                    <label>Vcam1 Test Lot #</label>
                    <input type="text" name="platedata[other_data][Vcam]" id="vcam_slot" value="<?php echo $plate['other_data']['Vcam'] ?>" />
                </div>
                <div class="frow">
                    <label>Vcam1 Test Preparation Date</label>
                    <input type="text" class="hasdate" name="platedata[other_data][Vcam_date]" id="vcam1_date" value="<?php echo $plate['other_data']['Vcam_date'] ?>" />
                </div>
            <?php } ?>
            <?php if ($show_psel_slot) { ?>
                <div class="frow">
                    <label>Psel Test Lot #</label>
                    <input type="text" name="platedata[other_data][Psel]" id="psel_slot" value="<?php echo $plate['other_data']['Psel'] ?>" />
                </div>
                <div class="frow">
                    <label>Psel Test Preparation Date</label>
                    <input type="text" class="hasdate" name="platedata[other_data][Psel_date]" id="psel_date" value="<?php echo $plate['other_data']['Psel_date'] ?>" />
                </div>
            <?php } ?>
        </div>
        <div class="col4">
            <div class="frow"><b>&nbsp;</b></div>
            <div class="frow">
                <label>HBSS Buffer Lot #</label>
                <input type="text" name="platedata[other_data][HBSS]" id="hbss_slot" value="<?php echo $plate['other_data']['HBSS'] ?>" />
            </div>
            <div class="frow">
                <label>HBSS Buffer Preparation Date</label>
                <input type="text" class="hasdate" name="platedata[other_data][HBSS_date]" id="hbss_date" value="<?php echo $plate['other_data']['HBSS_date'] ?>" />
            </div>
            <div class="frow">
                <label>PBS Lot #</label>
                <input type="text" name="platedata[other_data][PBS]" id="pbs_slot" value="<?php echo $plate['other_data']['PBS'] ?>" />
            </div>
            <div class="frow">
                <label>PBS Expiration Date</label>
                <input type="text" class="hasdate" name="platedata[other_data][PBS_date]" id="pbs_date" value="<?php echo $plate['other_data']['PBS_date'] ?>" />
            </div>
        </div>
    </div>
    <?php if ($can_edit) { ?>
        <div class="frow">
            <input type="submit" class="button-primary" value="Save" />
            <input type="hidden" name="trajax" value="consumables" />
            <input type="hidden" name="id" value="<?php echo isset($pl_ID) ? $pl_ID : '' ?>" />
            <input type="hidden" name="cid" value="<?php echo isset($coating_id) ? $coating_id : '' ?>" />
        </div>
    <?php } ?>
</form>
<style>
    .fieldtrans {
        background: #eee;
        padding: 0 5px;
        box-sizing: border-box;
    }

    .fieldtrans input {
        background: transparent !important
    }
</style>
<script>
    jQuery(function($) {
        var $today = '<?php echo date_i18n('Y-m-d')?>';
        function comparewaringdate(obj, numdays, $what, $target)
        {
            var date2 = obj.val();
            if(date2=='')return;

            date2 = new Date(date2);
            var today = new Date($today);
            let Difference_In_Time = today.getTime() - date2.getTime() ;
            let Difference_In_Days = Math.round(Difference_In_Time / (1000 * 3600 * 24));
            if (Difference_In_Days >= numdays) {
                alert(' This '+$what+' lot has expired! Use another lot.');
                $target.val('');
            }
        }

        $('#plate_form .hasdate').each($.tr_setup_datef);
        $('#plate_form').on('submit', function() {
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
        .on('change', '#vcam1_date, #vcam1_slot', function() {
            comparewaringdate($('#vcam1_date'),90,'Vcam1',$('#vcam1_slot'));
        })
        .on('change', '#psel_date, #psel_slot', function() {
            comparewaringdate($('#psel_date'),90,'Psel','#psel_slot');
        })
        .on('change', '#hbss_date, #hbss_slot', function() {
            comparewaringdate($('#hbss_date'),360,'HBSS Buffer','#hbss_slot');
        })
        .on('change', '#pbs_date, #pbs_slot', function() {
            comparewaringdate($('#pbs_date'),0,'HBSS Buffer','#pbs_slot');
        })
    })
</script>