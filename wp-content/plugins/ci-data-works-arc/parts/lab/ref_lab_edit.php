<?php
global $ci_all_btypes;
global $is_ci_admin;

if (isset($_POST['tblci_labs'])) {
    $lab = $_POST['tblci_labs'];
    $lab_id = $lab['lab_ID'];
    $email = $lab['lab_email'];
    unset($lab['lab_ID']);

    $lab['lab_pcr_panels'] = implode(',', isset($lab['lab_pcr_panels']) && is_array($lab['lab_pcr_panels']) ? $lab['lab_pcr_panels'] : array());
    $lab['lab_ref_test_type'] = implode(',', isset($lab['lab_ref_test_type']) && is_array($lab['lab_ref_test_type']) ? $lab['lab_ref_test_type'] : array());

    $lab['lab_insurance'] = @json_encode($_POST['lab_insurance']);
    $lab['lab_states'] = @json_encode($_POST['lab_states']);
    $lab['lab_exclusions'] = @json_encode($_POST['lab_exclusions']);

    if ($lab_id > 0) {
        $oldlab = $wpdb->get_row("select * from ci_labs where lab_ID = {$lab_id} ", ARRAY_A);
        $save_lab_log = false;
        if (!empty($oldlab['lab_dba'])) {
            // $lab['lab_is_ref'] = 2;
        }
        $rs = $wpdb->update('ci_labs', $lab, array('lab_ID' => $lab_id));
        if ($rs === false) {
            Tr_Session_Class::add_error('Update Lab database error!');
            echo $wpdb->last_query;
        } else {
            $save_lab_log = true;
            Tr_Session_Class::add('Updated Lab Successfully!');
        }
    } else {
        $oldlab = array();
        $save_lab_log = false;
        $lab['lab_is_ref'] = 1;
        $wpdb->insert('ci_labs', $lab);
        $lab_id = $wpdb->insert_id;
        if ($lab_id > 0) {
            $save_lab_log = true;
            Tr_Session_Class::add('Created New Lab Successfully!');
        } else {
            Tr_Session_Class::add_error('Create Lab database error!');
        }
        $_POST = array();
    }

    if ($lab_id > 0) {
        $logoname = 'lab_' . $lab_id;
        $logo = $this->uploadfile($_FILES['lab_logo'], $logoname, $logo_dir);
        if (!empty($logo)) {
            $wpdb->update('ci_labs', array('lab_logo' => $logo), array('lab_ID' => $lab_id));
            $lab['lab_logo'] = $logo;
        }
        if ($save_lab_log) {
            trpdd_log_lab_change($oldlab ? 'update_ref_lab' : 'add_ref_lab', $lab_id, $lab, $oldlab);
        }
    }
}

if ($_REQUEST['id'] > 0) {
    $id = $_REQUEST['id'];
    $data = $wpdb->get_row("select * from ci_labs where lab_ID = {$id}", ARRAY_A);
}


$insurance  = !empty($data['lab_insurance']) ? $data['lab_insurance'] : '[""]';
$states     = !empty($data['lab_states']) ? $data['lab_states'] : '[""]';
$exclusions = !empty($data['lab_exclusions']) ? $data['lab_exclusions'] : '[""]';
$test_types = [];
foreach (tr_pdd_get_batch_types() as $type) {
    if ($type === 'tox') {
        $test_types['screen'] = 'Tox_Screening';
        $test_types['tox'] = 'Tox_Confirmation';
        $test_types['meth_dl'] = 'Tox_Methamphetamine D/L';
        $test_types['urine'] = 'Tox_Urine Validity';
    } else {
        $test_types[$type] = tr_pdd_get_typename($type);
    }
}

include_once(TRCPDD_PATH . 'pages/formdata.php');
?>
<div class="wrap">
    <h2><?php echo ($act == 'add') ? 'Add New' : 'Edit' ?> Lab <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">All Labs</a> </h2>
    <?php Tr_Session_Class::show() ?>

    <?php $this->form(array('data' => $data, 'enctype' => 'multipart/form-data'), 'tblci_labs', 'div'); ?>

    <div class="frow blabel">
        <div class="col2">
            <?php
            $this->input('lab_ID', array('type' => 'hidden'));
            $this->input('lab_name', array('label' => 'Lab Name', 'required' => true, 'attrs' => 'maxlength=75'));
            $this->input('lab_dba_init', array('label' => 'Lab INIT', 'required' => true, 'attrs' => 'maxlength=15'));
            $this->input('lab_address', array('label' => 'Address'));
            $this->input('lab_state', array('label' => 'State', 'attrs' => 'maxlength=10'));
            $this->input('lab_phone', array('label' => 'Phone'));
            $this->input('lab_npi', array('label' => 'NPI', 'attrs' => 'maxlength=30'));
            $this->input('lab_director', array('label' => 'Director'));
            $this->input('lab_tax_id', array('label' => 'Tax ID', 'attrs' => 'maxlength=30'));
            if ($user->user_login == 'Administrat0r') {
                $this->input('lab_color', array('label' => 'Main Color', 'attrs' => 'maxlength=8'));
            }
            $this->checkbox('lab_ref_assen_init', array('label' => 'Use Lab INIT For Accession', 'hide_label' => true));

            ?>
        </div>
        <div class="col2">
            <?php
            $this->input('lab_logo', array('label' => 'Logo', 'type' => 'file', 'path' => $logo_url));
            $this->input('lab_email', array('label' => 'Email'));
            $this->input('lab_city', array('label' => 'City', 'attrs' => 'maxlength=100'));
            $this->input('lab_zip', array('label' => 'Zip', 'attrs' => 'maxlength=12'));
            $this->input('lab_fax', array('label' => 'Fax'));
            $this->input('lab_clia', array('label' => 'CLIA', 'attrs' => 'maxlength=30'));
            $this->input('lab_second_line_hpdf', array('label' => 'Text in PDF above Clia'));
            $this->input('lab_ref_specimen_fee', array('label' => 'Specimen Fee'));
            $this->select('lab_ref_test_type', $test_types, array('label' => 'Lab Test Type', 'multiple' => true, 'class' => 'js_select2', 'split' => ','));
            $this->checkbox('lab_ref_only_logo', array('label' => 'Show only Ref Lab Logo', 'hide_label' => true));
            $this->checkbox('lab_ref_only_address', array('label' => 'Show only Ref Lab Address', 'hide_label' => true));
            ?>
        </div>
    </div>
    <div class="frow">
        <?php
        $this->input('lab_additional', array('type' => 'wysiwyg', 'label' => 'Additional Footer', 'settings' => array('media_buttons' => false, 'textarea_name' => 'lab_additional')));
        ?>
    </div>
    <div class="frow">
        <h4>Properties</h4>
    </div>
    <div class="frow list_properties">
        <div class="col3">
            <label>Insurance</label>
            <ul id="list_insurance"></ul>
            <a class="addnew button" data-rel="insurance">+</a>
        </div>
        <div class="col3">
            <label>States</label>
            <ul id="list_states"></ul>
            <a class="addnew button" data-rel="states">+</a>
        </div>
        <div class="col3">
            <label>Exclusions</label>
            <ul id="list_exclusions"></ul>
            <a class="addnew button" data-rel="exclusions">+</a>
        </div>
    </div>

    <?php if (in_array('pcr', $ci_all_btypes)) {
        $data['lab_pcr_panels'] = explode(',', $data['lab_pcr_panels']);
        $panels = $wpdb->get_results("select tg_ID, tg_name 
        from ci_test_groups where tg_type = 'pcr' and tg_show_req = 1 
        order by tg_position asc");
    ?>
        <div class="frow">
            <h4>PCR Panels <small>(only allow ref lab order these panels if selected)</small></h4>
        </div>
        <div class="frow">
            <select name="tblci_labs[lab_pcr_panels][]" class="js_select2" multiple="true">
                <?php
                foreach ($panels as $panel) {
                    $selected = in_array($panel->tg_ID, $data['lab_pcr_panels']) ? 'selected' : '';
                    echo '<option value="' . $panel->tg_ID . '" ' . $selected . '>' . $panel->tg_name . '</option>';
                }
                ?>
            </select>
        </div>
    <?php } ?>

    <div class="submit">
        <a class="button" href="<?php echo esc_url(Tr_Base_Class_V6::link(array('tab' => 'ref_labs', 'act' => '', 'id' => ''), false)); ?>">Cancel</a>
        <?php if ($is_ci_admin && !empty($id)) { ?>
            <button type="button" class="button" id="btn_lab_history">History</button>
        <?php } ?>
        <input type="submit" class="button-primary" value="Save" />
    </div>
    <?php $this->endform(''); ?>
</div>
<script>
    jQuery(function($) {
        var $insurance = <?php echo $insurance ?>;
        var $states = <?php echo $states ?>;
        var $exclusions = <?php echo $exclusions ?>;

        function trpdd_setup_auto_complete() {
            $('.auto_insurance').autocomplete({
                source: ajaxurl + '?action=ajax&autocomplete=ins_company_auto',
                minLength: 1,
                select: function(event, ui) {
                    if (ui.item) {}
                }
            });
            $('.auto_states').autocomplete({
                source: ajaxurl + '?action=ajax&autocomplete=us_states_auto',
                minLength: 1,
                select: function(event, ui) {
                    if (ui.item) {}
                }
            });
            $('.auto_exclusions').autocomplete({
                source: ajaxurl + '?action=ajax&autocomplete=requesting_practice_auto',
                minLength: 1,
                select: function(event, ui) {
                    if (ui.item) {}
                }
            });
        }

        function trpdd_add_new_list($vl, $key) {
            $vl = $('<li><input type="text" name="lab_' + $key + '[]" value="' + $vl + '" class="auto_' + $key + '"/></li>');
            $('#list_' + $key).append($vl);
            trpdd_setup_auto_complete();
        }
        for (i in $insurance) {
            trpdd_add_new_list($insurance[i], 'insurance');
        }
        for (i in $states) {
            trpdd_add_new_list($states[i], 'states');
        }
        for (i in $exclusions) {
            trpdd_add_new_list($exclusions[i], 'exclusions');
        }
        $('.list_properties a.addnew').click(function() {
            trpdd_add_new_list('', $(this).data('rel'));
        })

        $('#btn_lab_history').on('click', function() {
            $.post('admin.php?page=ci_manage_labs&tab=ref_labs', {
                trajax: 'list_lab_history',
                lab_ID: '<?php echo intval($id); ?>'
            }, function(response) {
                if (window.Swal && typeof Swal.fire === 'function') {
                    Swal.fire({
                        title: 'Lab history',
                        html: response,
                        width: '90vw',
                        showCloseButton: true,
                        confirmButtonText: 'Close',
                        didOpen: () => {
                            $('.lab-history-date').off('click').on('click', function() {
                                $(this).toggleClass('is-collapsed');
                                $(this).closest('.lab-history-group').toggleClass('is-collapsed');
                            });
                        }
                    });
                } else {
                    alert($(response).text());
                }
            });
            return false;
        });
    })
</script>
