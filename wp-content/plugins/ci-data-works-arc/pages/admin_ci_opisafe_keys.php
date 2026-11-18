<?php


$act = $_REQUEST['act'];
$id = $_REQUEST['id'];

if (isset($_POST['key'])) {
    $_POST = tr_pdd_stripslashes_deep($_POST);
    $key = $_POST['key'];
    $clinics = is_array($_POST['cli_IDs']) ? $_POST['cli_IDs'] : array();
    $doctors = is_array($_POST['doc_IDs']) ? $_POST['doc_IDs'] : array();

    $key_id = $key['opi_ID'];

    if ($key_id > 0) {
        $rs = $wpdb->update('ci_opisafe_keys', $key, array('opi_ID' => $key_id));
        if ($rs === false) {
            Tr_Session_Class::add_error('Error: ' . $wpdb->last_error);
        } else {
            Tr_Session_Class::add('Updated Key Successfully!');
        }
    } else {
        $rs = $wpdb->insert('ci_opisafe_keys', $key);
        $key_id = $wpdb->insert_id;
        if ($key_id > 0) {
            Tr_Session_Class::add('Created New Key Successfully!');
            $key = array();
        } else {
            Tr_Session_Class::add_error('Error: ' . $wpdb->last_error);
        }
        $_POST = array();
    }

    if ($key_id > 0) {
        if(isset($_POST['auto_add_doctor'])){
            $opi = new CI_Opisafe($key['opi_key'], $key['opi_secret'], $key['opi_labkey']);
            $rs = $opi->providers();
            if($rs)
            {
                foreach($rs['providers'] as $provider)
                {
                    $doc_ID = $wpdb->get_var("select doc_ID from ci_doc where doc_npi = '{$provider['npi']}'");
                    if(!in_array($doc_ID,$doctors))
                    {
                        $doctors[] = $doc_ID;
                    }
                }
            }
        }

        $wpdb->delete('ci_opisafe_key_relationships', array('opi_ID' => $key_id));
        foreach ($clinics as $cli_ID) {
            $wpdb->insert('ci_opisafe_key_relationships', array('opi_ID' => $key_id, 'target_ID' => $cli_ID,'cType'=>'C'));
        }

        foreach ($doctors as $doc_ID) {
            $wpdb->insert('ci_opisafe_key_relationships', array('opi_ID' => $key_id, 'target_ID' => $doc_ID,'cType'=>'D'));
        }


    }
}

if ($act == 'delete') {
    $wpdb->delete('ci_opisafe_keys', array('opi_ID' => $id));
    $wpdb->delete('ci_opisafe_key_relationships', array('opi_ID' => $id));

    Tr_Session_Class::add('Deleted Key Successfully!');
}


if ($act == 'edit' || $act == 'add'){

    $cli_IDs = $doc_IDs = array();
    if ($id > 0 && $act == 'edit') {
        $key = $wpdb->get_row("select * from ci_opisafe_keys where opi_ID = {$id}", ARRAY_A);
        $cli_IDs = $wpdb->get_col("select target_ID from ci_opisafe_key_relationships where opi_ID = {$id} and cType='C'");
        $doc_IDs = $wpdb->get_col("select target_ID from ci_opisafe_key_relationships where opi_ID = {$id} and cType='D'");
    }


    $clinics = $wpdb->get_results("select * from ci_clinic where cli_clinic !='' order by cli_clinic");
    $doctors = $wpdb->get_results("select * from ci_doc where doc_npi !=''  order by doc_last, doc_first asc");
    $groups = $wpdb->get_results("select * from ci_user_groups order by gr_name asc ");
    $labs = $wpdb->get_results("select * from ci_labs where (lab_is_ref = 0 or lab_is_ref = 2) order by lab_dba_init asc");
    ?>

    <h2><?php echo ($act == 'add') ? 'Add New' : 'Edit' ?> Key <a
            href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">All Keys</a>
    </h2>
    <?php Tr_Session_Class::show() ?>

    <form method="post">
        <div class="frow blabel">
            <div class="col2">
                <label>Key</label>
                <input type="text" name="key[opi_key]" id="opi_key" value="<?php echo $key['opi_key'] ?>"
                       maxlength="100" required/>
            </div>
            <div class="col2">
                <label>Secret</label>
                <input type="text" name="key[opi_secret]" id="opi_secret" value="<?php echo $key['opi_secret'] ?>"
                       maxlength="100" required/>
            </div>
        </div>
        <div class="frow blabel">
            <div class="col2">
                <label>Lab Key</label>
                <input type="text" name="key[opi_labkey]" id="opi_labkey" value="<?php echo $key['opi_labkey'] ?>"
                       maxlength="100" required/>
            </div>
            <?php /*
            <div class="col2">
                <label>Lab</label>
                <select name="key[opi_lab_ID]">
                    <option value="">Select Lab</option>
                    <?php
                    foreach ($labs as $l) {
                        $selected = $l->lab_ID == $key['opi_lab_ID'] ? 'selected' : '';
                        echo '<option value="' . $l->lab_ID . '" ' . $selected . '>' . $l->lab_dba_init . '</option>';
                    }
                    ?>
                </select>
            </div>
 */?>
        </div>
        <div class="frow blabel">
            <div class="col2">

                <div class="frow">
                    <label>Clinic</label>
                    <select name="cli_IDs[]" multiple style="height:200px" class="js_select2_tag">
                        <?php

                        foreach ($clinics as $cli) {
                            $selected = in_array($cli->cID, $cli_IDs) ? 'selected' : '';
                            echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="frow">
                    <label>Doctors</label>
                    <select name="doc_IDs[]" multiple style="height:200px" class="js_select2_tag">
                        <?php

                        foreach ($doctors as $doc) {
                            $selected = in_array($doc->doc_ID, $doc_IDs) ? 'selected' : '';
                            echo '<option value="' . $doc->doc_ID . '" ' . $selected . '>' . $doc->doc_last.', '.$doc->doc_first .'('.$doc->doc_npi.')'. '</option>';
                        }
                        ?>
                    </select>
                </div>


            </div>
            <div class="col2">

                <div class="frow">
                    <label>Notes</label>
                    <textarea name="key[opi_desc]"><?php echo $key['opi_desc'] ?></textarea>
                </div>
            </div>
        </div>
        <div class="frow">
            <input type="submit" class="button-primary" value="Submit">
            <input type="submit" class="button-primary" name="auto_add_doctor" value="Auto Add Doctors">
            <a class="button testopi" href="#">Test Ping</a>
            <a class="button testopi" data-type="patients" href="#">Test Patients</a>
            <a class="button testopi" data-type="providers" href="#">Test Providers</a>
            <?php if($key['opi_ID']>0):?>
            <a class="button thickbox"  href="?tr_action=ci_test_opi&type=sync&id=<?php echo $key['opi_ID'] ?>">Sync</a>
            <?php endif;?>
        </div>
        <input type="hidden" name="key[opi_ID]" value="<?php echo $key['opi_ID'] ?>"/>
    </form>
    <div id="display_out"></div>


    <script>
        jQuery(function ($) {
            $("a.testopi").click(function () {
                $('#display_out').html('Waiting...');
                $.ajax({
                    data: {
                        tr_action: "ci_test_opi",
                        key: $("#opi_key").val(),
                        secret: $("#opi_secret").val(),
                        labid: $("#opi_labkey").val(),
                        type:$(this).data('type')
                    },
                    dataType: "json",
                    type: "post",
                    success: function (rs) {
                        $('#display_out').append(rs.html);
                    }
                })
                return false;
            })
        })
    </script>
    <?php
}
else {

    $row_per_page = 100;
    $pagination = new Tr_Pagination_class2($row_per_page);
    $pagination->setTotal($wpdb->get_var("select count(*) as c from ci_opisafe_keys {$where}"));
    $offset = $pagination->offset();

    $opi_keyss = $wpdb->get_results("select k.*,
                                    group_concat(' ',cli.cli_clinic,' ') as cli_clinic,
                                    ug.gr_name,
                                    lab.lab_dba_init

                                    from ci_opisafe_keys k
                                    left JOIN ci_opisafe_key_relationships kc ON kc.opi_ID = k.opi_ID
                                    left JOIN ci_clinic cli ON cli.cID = kc.target_ID and kc.cType='C'
                                    left join ci_user_groups ug ON ug.gr_ID = k.opi_user_group_ID
                                    left join ci_labs lab ON lab.lab_ID = k.opi_lab_ID
                                {$where} group by k.opi_ID limit {$offset},{$row_per_page}");

//echo $wpdb->last_error;
    ?>

    <h2>Keys <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2">Add
            New</a></h2>
    <?php Tr_Session_Class::show() ?>

<div class="wraptbl">
    <table class="wp-list-table widefat striped pages blue_tbl hover_tbl" style="table-layout: auto">
        <thead>
        <tr>
            <th style="width:50px">#</th>
            <th>Key</th>
            <th style="width: 30%">Clinic</th>
            <!--<th style="width:20%">Group</th>
            <th style="width:80px">Lab</th>-->
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php
        if (isset($opi_keyss) && count($opi_keyss) > 0) {
            foreach ($opi_keyss as $i => $row) {
                ?>
                <tr data-id="<?php echo $row->opi_ID ?>">
                    <td><?php echo $i + 1 ?></td>
                    <td><?php echo $row->opi_key ?></td>
                    <td><?php echo $row->cli_clinic ?></td>
                   <!-- <td><?php /*echo $row->gr_name */?></td>
                    <td><?php /*echo $row->lab_dba_init */?></td>-->
                    <td>
                        <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->opi_ID)) ?>">Edit</a>
                        -
                        <a class="delete"
                           href="<?php Tr_Base_Class_V6::link(array('act' => 'delete', 'id' => $row->opi_ID)) ?>">Delete</a>
                    </td>
                </tr>
                <?php
            }
        } else if (isset($opi_keyss)) {
            ?>
            <tr>
                <td colspan="3">Not found</td>
            </tr>
            <?php
        }
        ?>
        </tbody>
    </table>
</div>

    <div class="tablenav"><?php if($pagination)echo $pagination->pagination() ?></div>
    <?php
}
?>

<script>
    jQuery(function ($) {
        $('a.delete').click(function () {
            if (confirm('Do you want delete?') == false)return false;
        });
        $('.js_select2_tag').select2();
    })
</script>