<?php
global $wpdb, $current_role;



$include_inactive = $_REQUEST['include_inactive'] ?? '';
$user_id        = get_current_user_id();
$blood_settings = get_option('ci_req_settings_blood', array());
$act            = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';
$where          = '';
$conditions     = trpdd_get_batch_conditions(0, 'all', array('clinic_inactive' => $include_inactive));

if (strlen($conditions['where_clinic']) > 10) {
    $where = ' and (' . $conditions['where_clinic'] . ')';
}

if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $where .= " and cli.`cli_lab-ID` in ( {$lab_str} ) ";
}



if (!empty($_REQUEST['s'])) {
    $search = stripslashes($_REQUEST['s']);
    $where .= $wpdb->prepare(" and (cli.cli_clinic like %s or cli.cli_street like %s)", '%' . $search . '%', $search . '%');
}

if ($act == 'export') {
    $limit_str = '';
} else {
    $row_per_page   = 100;
    $pagination     = new Tr_Pagination_class($row_per_page);
    $pagination->setTotal($wpdb->get_var("select count(*) as c from ci_clinic cli where 1  {$where}"));
    $offset         = $pagination->offset();
    $limit_str      = " limit {$offset},{$row_per_page} ";
}



$order  = isset($_REQUEST['order']) && $_REQUEST['order'] == 'desc' ? 'desc' : 'asc';

if (isset($_GET['orderby'])) {

    $short = $_GET['orderby'];
} else {
    $short = 'cli.cID';
}

$orderby = "order by $short $order";



$sql = "select cli.*,
                trim(concat(lr.rep_first,' ',lr.rep_last)) as rep_name,
                (select count(*) from ci_batches ba where ba.cli_ID = cli.cID ) count_ba,
                lab.lab_dba_init as lab_name,
                dis.dis_name as distributor
            from ci_clinic cli
            left join ci_lab_reps lr ON lr.rep_ID = cli.`cli_rep-ID`
            left join ci_labs lab ON lab.lab_ID = cli.`cli_lab-ID`
            left join ci_distributors dis ON dis.dis_ID = cli.`cli_distributor_ID`
            where 1 {$where} {$orderby} 
            {$limit_str}";
$results = $wpdb->get_results($sql);


if (isset($_REQUEST['debug'])) {
    echo $sql;
    var_dump($wpdb->last_error);
}


if ($act == 'export') {
    ob_clean();
    if (!isset($_GET['view'])) {
        header("Content-disposition: attachment; filename=clients.csv");
    }

    header("Content-Type: text/csv");
    $output = fopen("php://output", 'w');
    $headers = array(
        'Lab',
        'Doctors',
        'Added Date',
        'Name',
        'Address',
        'City',
        'State',
        'Zip',
        'Phone',
        'Email',
        'Account Manager',
        'Rep',
        'Fax Number',
        'Fax On/Off',
        'Office Manager Names',
        'Office Manager e-mails'
    );
    fputcsv($output, $headers);

    foreach ($results as $row) {
        $docs = $wpdb->get_col("select concat(doc.doc_first,' ',doc.doc_last, ' (', doc.doc_npi,')') from ci_doc doc
                                            JOIN ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
                                            where cd.cli_ID = {$row->cID}");

        $docs = implode(', ', $docs);
        $docs = str_replace('()', '', $docs);

        $managers = $wpdb->get_results("select * from ci_clinic_manager where ma_cli_ID = {$row->cID} group by ma_email", ARRAY_A);
        $row->office_names = array();
        $row->office_emails = array();
        foreach ($managers as $mg) {
            $row->office_names[] = $mg['ma_manager'];
            $row->office_emails[] = $mg['ma_email'];
        }
        $row->office_names = implode(",\n", $row->office_names);
        $row->office_emails = implode(",\n", $row->office_emails);


        $datarow = array(
            $row->lab_name,
            $docs,
            $row->cli_sdate,
            $row->cli_clinic,
            $row->cli_street,
            $row->cli_city,
            $row->cli_state,
            $row->cli_zip,
            $row->{'cli_om-phone'},
            $row->{'cli_om-email'},
            $row->cli_account_manager,
            $row->rep_name,
            $row->{'cli_om-fax'},
            $row->cli_faxres ? 'On' : 'Off',
            $row->office_names,
            $row->office_emails,
        );
        fputcsv($output, $datarow);
    }
    @fclose($output);
    exit;
}

include_once(TRCPDD_PATH . 'pages/formdata.php');
?>

<div class="wrap">
    <h2>Current Clients</h2>

    <div class="tablenav">
        <form method="get" id="formsearch" style="float:left;margin-right: 10px;">
            <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
            <label>Name/Address:</label>
            <input type="text" name="s" value="<?php echo isset($_REQUEST['s']) ? $_REQUEST['s'] : '' ?>" />
            <label><input type="checkbox" name="include_inactive" onclick="document.getElementById('formsearch').submit();" value="1" <?php echo isset($_REQUEST['include_inactive']) ? 'checked' : '' ?> /> Show Inactive</label>
            <input type="submit" class="button bsty6" value="Search" />
            <input type="button" class="button" name="export" value="Export" />
        </form>
        <?php if ($pagination) echo $pagination->pagination() ?>
    </div>
    <div class="wraptbl">
        <table class="wp-list-table widefat striped pages blue_tbl hover_tbl" style="table-layout: auto;width:180%">
            <thead class="sticky">
                <tr>
                    <th style="width:30px">#</th>
                    <?php if (isset($blood_settings['Phlebotomist']) && $blood_settings['Phlebotomist']) { ?>
                        <th>Telcor ID</th>
                    <?php } ?>


                    <?php tr_tbl_sort('lab.lab_dba_init', 'Lab', '', 'min-width:65px') ?>
                    <th style="width:200px;">Doctors</th>
                    <?php tr_tbl_sort('cli.cli_sdate', 'Added', '', 'width:99px') ?>

                    <?php tr_tbl_sort('cli.cli_clinic', 'Name', '', 'width:200px') ?>
                    <th>Address</th>
                    <?php tr_tbl_sort('cli.cli_city', 'City') ?>
                    <?php tr_tbl_sort('cli.cli_state', 'State', '', 'min-width:75px') ?>
                    <th>Zip</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <?php tr_tbl_sort('cli.cli_account_manager', 'Account Manager', '', 'min-width:175px') ?>
                    <th>Distributor</th>
                    <?php tr_tbl_sort('lr.rep_first', 'Rep') ?>
                    <th>Fax Number</th>
                    <th>Fax On/Off</th>
                    <th>Office Manager Names</th>
                    <th>Office Manager Emails</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $i = $offset + 1;
                foreach ($results as $row) {

                    $docs = $wpdb->get_results("select 
				                                doc.doc_ID,
				                                doc.doc_first,
				                                doc.doc_last,
				                                doc.doc_npi
				                            from ci_doc doc
                                            JOIN ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
                                            where cd.cli_ID = {$row->cID}");

                    $managers = $wpdb->get_results("select * from ci_clinic_manager where ma_cli_ID = {$row->cID} group by ma_email", ARRAY_A);
                    $row->office_names = array();
                    $row->office_emails = array();
                    foreach ($managers as $mg) {
                        $row->office_names[] = $mg['ma_manager'];
                        $row->office_emails[] = $mg['ma_email'];
                    }
                    $row->office_names = implode(",\n", $row->office_names);
                    $row->office_emails = implode(",\n", $row->office_emails);

                    if (isset($blood_settings['Phlebotomist']) && $blood_settings['Phlebotomist'] && empty($row->cli_Telcor_ID)) {

                        $row->cli_Telcor_ID = $i;
                        $wpdb->update('ci_clinic', array('cli_Telcor_ID' => $i), array('cID' => $row->cID));
                    }
                    $i++;
                ?>
                    <tr>
                        <td title="<?php echo $row->cID ?>"><?php echo $row->cID ?></td>
                        <?php if (isset($blood_settings['Phlebotomist']) && $blood_settings['Phlebotomist']) { ?>
                            <td><?php echo $row->cli_Telcor_ID ?></td>
                        <?php } ?>

                        <td><?php echo $row->lab_name ?></td>
                        <td class="smaller">
                            <?php
                            foreach ($docs as $doc) {
                            ?>
                                <li>
                                    <a class="thickbox fontmedium" title="<?php echo $doc->doc_npi ?>" href="?tr_action=ci_print_doctor_custom&id=<?php echo $doc->doc_ID ?>&TB_iframe=true&width=800&height=550"><?php echo tr_trim_name($doc->doc_last . ', ' . $doc->doc_first) ?></a>
                                </li>
                            <?php
                            }
                            ?>
                        </td>
                        <td><?php if ($row->cli_sdate != '0000-00-00') echo $row->cli_sdate ?></td>

                        <td title="<?php echo $row->count_ba ?>">
                            <a class="thickbox" href="?tr_action=ci_print_clinic&id=<?php echo $row->cID ?>&TB_iframe=true&width=800&height=550"><?php echo $row->cli_clinic ?></a>
                        </td>

                        <td><?php echo $row->cli_street ?></td>
                        <td><?php echo $row->cli_city ?></td>
                        <td><?php echo $row->cli_state ?></td>
                        <td><?php echo $row->cli_zip ?></td>
                        <td><?php echo $row->{'cli_om-phone'} ?></td>
                        <td><?php echo $row->{'cli_om-email'} ?></td>
                        <td><?php echo $row->cli_account_manager ?></td>
                        <td><?php echo $row->distributor ?></td>
                        <td><?php echo $row->rep_name ?></td>
                        <td><?php echo $row->{'cli_om-fax'} ?></td>
                        <td><?php echo $row->cli_faxres ? 'On' : 'Off' ?></td>
                        <td><?php echo nl2br($row->office_names) ?></td>
                        <td><?php echo nl2br($row->office_emails) ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
</div>

<script>
    jQuery(function($) {
        $('input[name="export"]').click(function() {
            $('#formsearch').append('<input type="hidden" name="trajax" value="1"/>');
            $('#formsearch').append('<input type="hidden" name="act" value="export"/>');
            $('#formsearch').submit();
        })
        $('input[type="submit"]').click(function() {
            $('#formsearch input[name="trajax"]').remove();
            $('#formsearch input[name="act"]').remove();
        })
    })
</script>