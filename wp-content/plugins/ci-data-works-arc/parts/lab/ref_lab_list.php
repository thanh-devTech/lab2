<?php

if ($act == 'delete') {
    $lab_id = $_REQUEST['id'];
    $wpdb->delete('ci_labs', array('lab_ID' => $lab_id));
    Tr_Session_Class::add('Deleted Lab #' . $lab_id);
}
else if(!empty($act))
{
    $action_file = 'ref_lab_'.$act;
    if($act=='add')$action_file='ref_lab_edit';
    include(trpdd_get_file_path('lab',$action_file));
    return;
}

$labs = $wpdb->get_results("select * from ci_labs order by lab_dba_init");
?>
<div class="wrap">
    <h2>Ref Labs <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2">Add New</a></h2>
    <?php Tr_Session_Class::show() ?>

    <table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
        <thead>
            <tr>
                <th style="width:50px">#</th>
                <th>Name</th>
                <th>Init</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($labs as $i => $lab) :

            ?>
                <tr>
                    <td title="<?php echo $lab->lab_ID ?>"><?php echo $lab->lab_ID ?></td>
                    <td><?php echo $lab->lab_name ?></td>
                    <td><?php echo $lab->lab_dba_init ?></td>
                    <td>
                        <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $lab->lab_ID)) ?>">Edit</a> -
                        <a href="<?php Tr_Base_Class_V6::link(array('act' => 'clinics', 'id' => $lab->lab_ID)) ?>">Clinics</a> -
                        <?php /*<a class="thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax'=>1,'act' => 'users', 'id' => $lab->lab_ID)) ?>">Users</a> -*/?>
                        <a href="<?php Tr_Base_Class_V6::link(array('act' => 'fees', 'id' => $lab->lab_ID)) ?>">Fees</a>
                        <?php if ($lab->lab_is_ref == 1) : ?>
                            -
                            <a class="delete" href="<?php Tr_Base_Class_V6::link(array('act' => 'delete', 'id' => $lab->lab_ID)) ?>">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
    jQuery(function($) {
        $('a.delete').click(function() {
            if (confirm('Do you want delete?') == false) return false;
        })
    })
</script>