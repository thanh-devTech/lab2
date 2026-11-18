<?php
global $wpdb;

if (isset($_REQUEST['trajax'])) {
    $act = $_REQUEST['trajax'];
}

if ($act == 'ci_save_table_cell') {
    $return = array('status' => 'ok');
    $field  = $_POST['field'];
    $id     = $_POST['id'];
    $value  = $_POST['value'];
    $wpdb->update(
        'ci_test_specimens',
        array(
            $field => $value,
        ),
        array(
            'ts_ID' => $id,
        )
    );
    wp_send_json($return);
} else if ($act == 'add' || $act == 'edit') {

    $specimen = array();
    if (isset($_POST['specimen'])) {
        $specimen = stripslashes_deep($_POST['specimen']);
        $specimen['ts_type'] = $tes_type;
        $specimen['ts_name'] = trim($specimen['ts_name']);
        $specimen['ts_code'] = trim($specimen['ts_code']);
        if (isset($_POST['has_controlrerun'])) {
            $specimen['ts_controlreruns'] = isset($specimen['ts_controlreruns']) ? implode(',', $specimen['ts_controlreruns']) : '';
        }

        if ($specimen['ts_ID'] > 0) {
            $rs = $wpdb->update('ci_test_specimens', $specimen, array('ts_ID' => $specimen['ts_ID']));
        } else {
            $rs = $wpdb->insert('ci_test_specimens', $specimen);
        }
        $specimen = $_POST = array();
        Tr_Session_Class::add('Saved Specimens');
    }

    if ($_REQUEST['id'] > 0) {
        $id = $_REQUEST['id'];
        $specimen = $wpdb->get_row("select * from ci_test_specimens where ts_ID = {$id}", ARRAY_A);
    }

    $groups = $wpdb->get_results("select * from ci_test_groups 
    where tg_type='{$tes_type}' 
    and tg_show_req = 1
    and tg_parent=0 order by tg_name asc");
    $controltests = array();
    if ($specimen['ts_group_id'] > 0) {
        $controltests = $wpdb->get_results("select * 
        from ci_test_panels te
        JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
        where te_is_control = 1 
        and te_type = '{$tes_type}' 
        and (g.tg_ID = {$specimen['ts_group_id']} or g.tg_parent = {$specimen['ts_group_id']} )");
    }

?>
    <h2><?php echo ($act == 'add') ? 'Add New' : 'Edit' ?> Specimen <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">All Specimens</a>
        <?php if ($act == 'edit'): ?><a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2">Add New</a><?php endif; ?>
    </h2>
    <?php Tr_Session_Class::show() ?>
    <form method="post">
        <div class="frow">
            <div class="col2">
                <div class="frow">
                    <label>Name</label>
                    <input type="text" name="specimen[ts_name]" value="<?php echo $specimen['ts_name'] ?>" required/>
                </div>
                <div class="frow">
                    <label>Code</label>
                    <input type="text" name="specimen[ts_code]" value="<?php echo $specimen['ts_code'] ?>" maxlength="30" />
                </div>
                <div class="frow">
                    <label>Group</label>
                    <select name="specimen[ts_group_id]" required>
                        <option value="">Select Group</option>
                        <?php
                        foreach ($groups as $group) {
                            $selected = $group->tg_ID == $specimen['ts_group_id'] ? 'selected="selected"' : '';
                            echo '<option value="' . $group->tg_ID . '" ' . $selected . '>' . $group->tg_name . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <?php
                if ($tes_type == 'pcr' && tr_get_option('ci_req_settings_' . $tes_type, 'enable_control_by_specimen') && $specimen['ts_group_id'] > 0) {
                ?>
                    <div class="frow">
                        <label>Control Tests:</label>
                        <select name="specimen[ts_controlreruns][]" multiple class="js_select2">
                            <?php
                            $specimen['ts_controlreruns'] = explode(',', $specimen['ts_controlreruns']);
                            //echo '<option value="">Select</option>';
                            foreach ($controltests as $t) {
                                $selected = in_array($t->te_ID, $specimen['ts_controlreruns'])  ? 'selected' : '';
                                echo '<option value="' . $t->te_ID . '" ' . $selected . '>' . $t->te_name . ' - ' . $t->te_abrev . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <input type="hidden" name="has_controlrerun" value="1" />
                <?php
                }
                ?>
                <div class="frow">
                    <input type="submit" class="button-primary" value="Save" />
                    <input type="hidden" name="specimen[ts_ID]" value="<?php echo $specimen['ts_ID'] ?>" />
                </div>
            </div>
        </div>
    </form>
<?php
} else {
    wp_enqueue_script('data-admin-tbl', TRCPDD_URL . 'js/admin_table_edit.js', array(), TRCPDD_VS . time());
    if ($act == 'pdelete' && $_POST['idd'] > 0) {
        $wpdb->delete('ci_test_specimens', array('ts_ID' => $_POST['idd']));
        Tr_Session_Class::add("Deleted Successfully");
    }
    $rs = $wpdb->get_results("select * from ci_test_specimens where ts_type='{$tes_type}' order by ts_group_id, ts_ID asc");
?>
    <h2>Specimens
        <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2">Add New</a>
        <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '', 'tabtype' => '')) ?>" class="add-new-h2">Tests</a>
    </h2>
    <?php Tr_Session_Class::show() ?>


    <div class="wraptbl">
        <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Group</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rs as $i => $te) {
                    $group = tr_pdd_get_panel_groups($te->ts_group_id, $tes_type);
                ?>
                    <tr data-id="<?php echo $te->ts_ID ?>">
                        <td><?php echo $te->ts_ID ?></td>
                        <td><?php echo $te->ts_name ?></td>
                        <td class="editable" data-k="ts_code"><?php echo $te->ts_code ?></td>
                        <td><?php echo $group['label'] ?></td>
                        <td>
                            <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $te->ts_ID)) ?>">Edit</a> -
                            <a class="pdelete" href="#" data-id="<?php echo $te->ts_ID ?>">Delete</a>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>


    <div class="total">
        Total: <?php echo count($rs); ?>
    </div>

<?php
}
