<?php
global $wpdb;
global $current_role;

include_once(TRCPDD_PATH . 'pages/formdata.php');

$from_date  = !empty($_GET['from']) ? $_GET['from'] : date_i18n('Y-m-d');
$to_date    = !empty($_GET['to']) ? $_GET['to'] : $from_date;
$to_date    = $to_date . ' 23:59:59';
$cli_ID     = isset($_REQUEST['cli_ID']) ? $_REQUEST['cli_ID'] : 0;
$rtype      = isset($_REQUEST['rtype']) ? $_REQUEST['rtype'] : '';

$clinics    = array();

$mf_group_id = $wpdb->get_var("select tg_ID from ci_test_groups where tg_short_name='Adhesion Panel' ");
$fa_group_id = $wpdb->get_var("select tg_ID from ci_test_groups where tg_short_name='Fragility Panel' ");

switch ($current_role) {
    case 'lab_rep':
    case 'lab_rep_w2':

        $rep_id = get_user_meta($user_id, '_rep_id', true);
        $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);


        $clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
                                        where cli.cli_status = 1 and `cli_rep-ID`={$rep_id} order by cli_clinic asc");


        break;
    case 'doctor':
        $doc_ID = get_user_meta($user_id, '_doc_id', true);

        $clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
		                                join ci_clinic_doc cd ON cli.cID = cd.cli_ID
                                    where cli.cli_status = 1 and cd.doc_ID = {$doc_ID}
                                     order by cli.cli_clinic asc");
        break;

    case 'collector':
        $col_id = get_user_meta($user_id, '_collector_id', true);
        $cids = $wpdb->get_col("select cli_ID from ci_clinic_collector where col_ID ={$col_id}");
        $cids = implode(',', $cids);
        $clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
                                    where cli.cli_status = 1 and cli.cID in ({$cids}) order by cli_clinic asc");
        break;

    case 'clinic':
        $cli_ID = get_user_meta($user_id, '_clinic_id', true);
        break;

    case 'office_manager':
    case 'multi_site_manager':
        $cid = get_user_meta($user_id, '_clinic_id', true);
        $where_cli = "";
        if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
            $cids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
            $cids = implode(',', $cids);
            $where_cli .= " and cli.cID in ({$cids}) ";
        }

        $clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
                                    where cli.cli_status = 1 {$where_cli} order by cli_clinic asc");

        break;

    default:
        $clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic 
		                                    from ci_clinic cli
                                            where cli.cli_status = 1
                                            group by cli.cID
                                            order by cli_clinic asc");
        $show_all = true;
        break;
}

$where = '';
if ($cli_ID > 0) {
    $where .= " and ba.cli_ID = {$cli_ID} ";
}

$where_received = $where . " and ba.spec_received >= '{$from_date}' and ba.spec_received <= '{$to_date}'";
$where_doc      = $where . " and ba.doc >= '{$from_date}' and ba.doc <= '{$to_date}'";
$where_resulted = $where . " and ba.doc >= '{$from_date}' and ba.doc <= '{$to_date}'";
$where_approved = $where . " and re.approve_date >= '{$from_date}' and re.approve_date <= '{$to_date}'";


$btype = 'blood';

$batch_tbl  = tr_pdd_get_table_by_btype($btype);
$report_tbl = tr_pdd_get_table_report_by_btype($btype);
$result_tbl = tr_pdd_get_table_result_by_btype($btype);

$inbound = $wpdb->get_var("select count(*) from {$batch_tbl} ba 
WHERE batch_id = 0 {$where_doc}");

$received_mf = $wpdb->get_var("select count(*) from {$batch_tbl} ba 
where find_in_set('group_{$mf_group_id}', ba.panel_tests_requested)  
{$where_received}"); 

$received_fa = $wpdb->get_var("select count(*) from {$batch_tbl} ba 
where find_in_set('group_{$fa_group_id}', ba.panel_tests_requested)  
{$where_received}");


$resulted_mf = $wpdb->get_var("select count(*) from {$batch_tbl} ba 
                    JOIN {$report_tbl} re ON ba.assen = re.patient_id
                    join {$result_tbl} rs ON re.id = rs.report_id and drug = 'MFI-3'
                    where 1  {$where_resulted} 
                    {$where_resulted}");

$resulted_fa = $wpdb->get_var("select count(*) from {$batch_tbl} ba 
                    JOIN {$report_tbl} re ON ba.assen = re.patient_id
                    join {$result_tbl} rs ON re.id = rs.report_id and drug = 'PSEL'
                    where 1  {$where_resulted} 
                    {$where_resulted}");

$approved = $wpdb->get_var("select count(*) from {$batch_tbl} ba 
                    JOIN {$report_tbl} re ON ba.assen = re.patient_id
                    where 1  {$where_approved}  ");

?>
<div class="wrap">
    <h2>MF/FA Reporting</h2>

    <form method="get" style="margin-bottom:20px" id="formsearch">
        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
        <input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />
        From Date: <input type="text" class="hasdate" name="from" style="width:100px" value="<?php echo isset($from_date) ? $from_date : '' ?>" required />
        <label id="summarytodate">To:</label>
        <input type="text" class="hasdate today" name="to" style="width:100px" value="<?php echo isset($to_date) ? $to_date : '' ?>" required />
        <?php
        if (isset($clinics)) {
        ?>
            <select name="cli_ID" class="select2" style="max-width:350px" data-placeholder="Select Clinic" class="njs_select2">
                <option value="">All Clinic</option>
                <?php
                foreach ($clinics as $cli) {
                    $selected = $cli->cID == $cli_ID ? 'selected' : '';
                    echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
                }
                ?>
            </select>
        <?php
        }
        ?>

        <input type="submit" class="button-primary" value="Report" />

    </form>


    <div class="wraptbl">
        <table class="dashboard_tbl" style="width:400px">
            <tr>
                <th>Inbound</th>
                <td><?php echo $inbound ?></td>
            </tr>
            <tr>
                <th>Received for MF</th>
                <td><?php echo $received_mf ?></td>
            </tr>
            <tr>
                <th>Received for FA</th>
                <td><?php echo $received_fa ?></td>
            </tr>
            <tr>
                <th>Resulted for MF</th>
                <td><?php echo $resulted_mf ?></td>
            </tr>
            <tr>
                <th>Resulted for FA</th>
                <td><?php echo $resulted_fa ?></td>
            </tr>
            <tr>
                <th>Approved</th>
                <td><?php echo $approved ?></td>
            </tr>
        </table>
    </div>
</div>
<style>
    .dashboard_tbl th{
        border-bottom:1px solid #ddd;
        background:#ccc;
        color:#000;
    }
</style>