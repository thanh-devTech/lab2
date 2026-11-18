<?php
global $wpdb;
$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;

if (isset($_POST['data'])) {

    $data           = tr_pdd_stripslashes_deep($_POST['data']);
    $report_values  = $data['reported_values'];
    $data['reported_values']  = array();
    foreach ($report_values['v'] as $k => $vl) {
        $vl = trim($vl);
        if (empty($vl)) continue;
        $calculation = $report_values['c'][$k];
        if (strlen($calculation) < 5) {
            $calculation = '';
        }
        $data['reported_values'][] = array(
            'v' => $vl,
            'u' => $report_values['u'][$k],
            'c' => $calculation,
            'i' => $report_values['i'][$k],
        );
    }
    $data['reported_values'] = json_encode($data['reported_values']);


    if ($id > 0) {
        $wpdb->update('ci_study_tests', $data, array('ID' => $id));
    } else {
        unset($data['ID']);
        $data['created'] = date_i18n('Y-m-d H:i:s');
        $data['created_by'] = get_current_user_id();

        $wpdb->insert('ci_study_tests', $data);
    }
    Tr_Session_Class::add('Saved Successfully!');
}
if ($id > 0) {
    $data = $wpdb->get_row("select * from ci_study_tests where ID = {$id} ", ARRAY_A);

    $reports = json_decode($data['reported_values'], true);
    if (!is_array($reports)) {
        $reports = array(0 => array('v' => '', 'u' => '', 'i' => ''));
    }
} else {
    $data = array();
    $reports = array(0 => array('v' => '', 'u' => '', 'i' => ''));
}

?>
<div class="wrap">
    <h2>Edit Study
        <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2 bsty4">All Test</a>
        <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'questions', 'type' => $tes_type)) ?>" class="add-new-h2">Questions</a>
        <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'sitenames', 'type' => $tes_type)) ?>" class="add-new-h2">Site Names</a>
        <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'irbs', 'type' => $tes_type)) ?>" class="add-new-h2">IRBs</a>
        <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'inclusion', 'type' => $tes_type)) ?>" class="add-new-h2">Inclusion</a>
        <a href="<?php Tr_Base_Class_V6::link(array('tabtype' => 'exclusion', 'type' => $tes_type)) ?>" class="add-new-h2">Exclusion</a>
        <a href="<?php Tr_Base_Class_V6::link(array('trajax' => 1, 'act' => 'import', 'type' => $tes_type)) ?>" class="add-new-h2 thickbox">Import</a>
    </h2>
    <?php Tr_Session_Class::show() ?>

    <form method="post">
        <div class="frow">
            <div class="col2">
                <label>Abreviation</label>
                <input type="text" name="data[abrev]" value="<?php echo $data['abrev'] ?>" required />
            </div>
            <div class="col2">
                <label>Description</label>
                <input type="text" name="data[desc]" value="<?php echo $data['desc'] ?>" />
            </div>
        </div>

        <div class="frow">
            <div class="col4">
                <label>Units</label>
                <input type="text" name="data[units]" value="<?php echo $data['units'] ?>" />
            </div>
            <div class="col4">
                <label>Minimum Volume Whole Blood (uL)</label>
                <input type="text" name="data[minimumvolumewholebloodul]" value="<?php echo $data['minimumvolumewholebloodul'] ?>" />
            </div>
            <div class="col4">
                <label>Anti-Coagulant</label>
                <input type="text" name="data[anti-coagulant]" value="<?php echo $data['anti-coagulant'] ?>" />
            </div>
            <div class="col4">
                <label>&nbsp;</label><br>
                <input type="hidden" name="data[sysmex_xn_530]" value="0" />
                <label><input type="checkbox" name="data[sysmex_xn_530]" value="1" <?php checked($data['sysmex_xn_530']) ?> /> Ins Sysmex </label>

                <input type="hidden" name="data[phoresis]" value="0" />
                <label><input type="checkbox" name="data[phoresis]" value="1" <?php checked($data['phoresis']) ?> /> Ins Phoresis </label>
            </div>
        </div>

        <div style="max-width:96%;margin:0 auto">
            <div class="frow">
                <div class="col4">
                    <h4>Reportable Value</h4>
                </div>
                <div class="col6">
                    <h4>Unit</h4>
                </div>
                <div class="col3">
                    <h4>Calculation</h4>
                </div>
                <div class="col4">
                    <h4>Instrument Code</h4>
                </div>
            </div>
            <ol id="listreports"></ol>
            <a class="add_row button">+</a>
        </div>
        <br>
        <br>
        <div class="frow">
            <input type="submit" class="button-primary" value="Submit" style="display:none" />
            <input type="hidden" name="id" value="<?php echo $id ?>" />
        </div>
    </form>
</div>
<style>
    #listreports {
        margin-left: 0
    }

    #listreports li {
        line-height: 30px;
    }
</style>
<script>
    jQuery(function($) {
        var $reports = <?php echo json_encode($reports) ?>;
        var $itemi = 0;

        function add_row(data) {
            $itemi++;
            $html = '<li class="item" id="item_' + $itemi + '"><div class="frow">';
            $html += '<div class="col4"><input type="text" class="nametest" name="data[reported_values][v][]" value="' + (data.v ? data.v : '') + '"/></div>';
            $html += '<div class="col6"><input type="text" name="data[reported_values][u][]" value="' + (data.u ? data.u : '') + '"/></div>';
            $html += '<div class="col3"><input type="text" name="data[reported_values][c][]" value="' + (data.c ? data.c : '') + '"/></div>';
            $html += '<div class="col4"><input type="text" name="data[reported_values][i][]" value="' + (data.i ? data.i : '') + '"/></div>';
            $html += '</div></li>';
            var $row = $($html);
            $('#listreports').append($row);
        }
        $('body').on('click', '.add_row', function() {
                if ($('#listreports .item').length >= 25) {
                    alert('Maximun Value');
                } else {
                    add_row({
                        v: '',
                        u: '',
                        i: ''
                    });
                }
            })
            .on('change', 'input.nametest', function() {
                var $hasdup = false;
                var $id = $(this).parents('.item').attr('id');
                var $vl = $(this).val();
                console.log($vl);
                $(this).removeClass('error');
                $('[type="submit"]').prop('disabled', false);
                $('input.nametest').each(function() {
                    var $cid = $(this).parents('.item').attr('id');
                    var $cvl = $(this).val();
                    console.log($cvl);
                    if ($cid != $id && $vl == $cvl) {
                        $hasdup = true;
                    }
                })
                if ($hasdup) {
                    alert('Duplicate');
                    $(this).addClass('error');
                    $('[type="submit"]').prop('disabled', true);
                }
            })
        for (i in $reports) {
            add_row($reports[i]);
        }
        $('[type="submit"]').show();
    })
</script>