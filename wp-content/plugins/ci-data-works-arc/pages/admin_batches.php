<?php
global $wpdb;

if($_GET['action']=='billing')
{
    $batch_id = $_GET['id'];
    $row_per_page = 1000;
    $pagination = new Tr_Pagination_class($row_per_page);
    $offset = $pagination->offset();
    $where = " from ci_batches ba JOIN ci_batch_billings bi ON ba.patient_name = bi.patient and ba.doc = bi.servdate
                where ba.batch_id = '{$batch_id}' ";
    $pagination->setTotal($wpdb->get_var("select count(*) as count ".$where));
    $rows = $wpdb->get_results("select * {$where} limit {$offset},{$row_per_page}");
    ?>
    <h2>Batch #<?php echo $batch_id?></h2>
    <table class="wp-list-table widefat fixed striped posts hover_tbl">
        <thead>
        <tr>
            <th>#</th>
            <th>DOC</th>
            <th>SD</th>
            <th>ID#</th>
            <th>Patient Name</th>
            <th>Patient Name</th>
            <th>Patient ID</th>
            <th># of claims</th>
            <th>billed</th>
            <th>cashbal</th>
        </tr>
        </thead>
        <tbody>

        <?php foreach ($rows as $i => $row): ?>
            <tr>
                <td><?php echo $i+1 ?></td>
                <td><?php echo $row->doc?></td>
                <td><?php echo $row->servdate?></td>
                <td><?php echo $row->id?></td>
                <td><?php echo $row->patient_name?></td>
                <td><?php echo $row->patient?></td>
                <td><?php echo $row->patient_id?></td>
                <td><?php echo $row->claim_no?></td>
                <td><?php echo $row->billed?></td>
                <td><?php echo $row->cashbal?></td>

            </tr>
        <?php endforeach;?>
        </tbody>
    </table>
    <div class="tablenav"><?php if($pagination)echo $pagination->pagination() ?></div>div>
<?php
}
else if($_GET['action']=='viewbatch')
{
    $batch_id = $_GET['id'];
    $row_per_page = 1000;
    $pagination = new Tr_Pagination_class($row_per_page);
    $offset = $pagination->offset();
    $pagination->setTotal($wpdb->get_var("select count(*) as count from ci_batches where batch_id = '{$batch_id}' "));
    $rows = $wpdb->get_results("select * from ci_batches where batch_id = '{$batch_id}' limit {$offset},{$row_per_page}");
    ?>
    <h2>Batch #<?php echo $batch_id?></h2>
    <table class="wp-list-table widefat fixed striped posts hover_tbl">
        <thead>
        <tr>
            <th>#</th>
            <th>DOC</th>
            <th>ID#</th>
            <th>Patient Name</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>

        <?php foreach ($rows as $i => $row): ?>
            <tr>
                <td><?php echo $i + 1 ?></td>
                <td><?php echo $row->doc?></td>
                <td><?php echo $row->id?></td>
                <td><?php echo $row->patient_name?></td>
                <td><a href="<?php Tr_Base_Class_V6::link(array('id' => $row->batch_id, 'action' => 'billing')) ?>">Billing</a>
                </td>
            </tr>
        <?php endforeach;?>
        </tbody>
    </table>
    <div class="tablenav"><?php if($pagination)echo $pagination->pagination() ?></div>
    <?php
}
else {
    $row_per_page = 100;
    $pagination = new Tr_Pagination_class($row_per_page);
    $offset = $pagination->offset();
    $pagination->setTotal($wpdb->get_var("select count(*) as count from (select batch_id from ci_batches group by batch_id) as ba"));
    $rows = $wpdb->get_results("select *,count(batch_id) as count from ci_batches group by batch_id limit {$offset},{$row_per_page}");

    ?>
    <h2>Batches</h2>
    <table class="wp-list-table widefat fixed striped posts hover_tbl">
        <thead>
        <tr>
            <th>#</th>
            <th>Batch Name</th>
            <th>Count</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>

        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?php echo $row->batch_id ?></td>
                <td>Batch <?php echo $row->batch_id ?></td>
                <td><?php echo $row->count ?></td>
                <td><a href="<?php Tr_Base_Class_V6::link(array('id' => $row->batch_id, 'action' => 'viewbatch','paged'=>'')) ?>">View</a>
                </td>
            </tr>
        <?php endforeach;?>
        </tbody>
    </table>
    <div class="tablenav"><?php if($pagination)echo $pagination->pagination() ?></div>
<?php
}
?>