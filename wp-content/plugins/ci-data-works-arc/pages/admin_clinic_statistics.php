<?php
global $wpdb, $current_role;


wp_enqueue_script('jquery-ui-core');
wp_enqueue_script('jquery-ui-slider');
wp_enqueue_script('jquery-ui', '', array('jquery'));
wp_enqueue_script('jquery-ui-datepicker');

wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');


$google_charts  = array();
$monitored      = array();
$patient_ages   = array();
$user_id        = get_current_user_id();
$cli_ID         = isset($_REQUEST['cli_ID']) ? $_REQUEST['cli_ID'] : 0;
$month          = isset($_REQUEST['month']) ? $_REQUEST['month'] : '';
if (empty($month)) {
    $month = date_i18n('Y-m');
}


if (empty($cli_ID)) {
    $cli_ID = get_user_meta($user_id, '_use_current_clinic_id', true);
}

switch ($current_role) {
    case 'lab_rep':
    case 'lab_rep_w2':

        $rep_id = get_user_meta($user_id, '_rep_id', true);
        $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);


        $clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli where `cli_rep-ID`={$rep_id} order by cli_clinic asc");


        break;
    case 'doctor':
        $doc_ID = get_user_meta($user_id, '_doc_id', true);
        $cli_ID = $wpdb->get_col("select cli_ID from ci_clinic_doc where doc_ID = {$doc_ID} ");
        $cli_ID = implode(',', $cli_ID);
        break;

    case 'collector':
        $col_id = get_user_meta($user_id, '_collector_id', true);
        $cli_ID = $wpdb->get_col("select cli_ID from ci_clinic_collector where col_ID ={$col_id}");
        $cli_ID = implode(',', $cli_ID);
        break;

    case 'clinic':
        $cli_ID = get_user_meta($user_id, '_clinic_id', true);
        break;

    case 'office_manager':
        $cid = get_user_meta($user_id, '_clinic_id', true);
        $cids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");

        $cids = implode(',', $cids);
        $clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli where cli.cID in ({$cids}) order by cli_clinic asc");

        break;

    case 'multi_site_manager':

        $where_cli = '';
        if (!tr_get_option('ci_pdf_data', 'msm_can_show_all')) {
            $ms_cli_ids = get_user_meta($user_id, '_ms_clinic_ids', true);
            if (!is_array($ms_cli_ids) || empty($ms_cli_ids)) {
                $ms_cli_ids = $wpdb->get_col("select cID from ci_clinic where cli_status = 1 ");
                update_user_meta($user_id, '_ms_clinic_ids', $ms_cli_ids);
            }
            $ms_cli_ids_str = implode(',', $ms_cli_ids);
            $where_cli .= " and cli.cID in ({$ms_cli_ids_str}) ";
        }


        $clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
                                    where cli.cli_status = 1 {$where_cli} order by cli_clinic asc");

        break;

    default:
        $clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli
                                    order by cli_clinic asc");
        break;
}


if (isset($_GET['act']) && $_GET['act'] == 'monitored') {

    $test_name = $_REQUEST['tname'];
    $where = " ba.cli_ID in ({$cli_ID}) and ba.doc >= '{$month}-01' and ba.doc <= '{$month}-31 23:59:59' ";
    $where .= " and (ba.sync_type != 'H' or ba.sync_type is null)";


    $rs = $wpdb->get_results("select ba.assen,
	                                        ba.doc,
	                                        ba.patient_id,
	                                        ba.patient_name,
	                                        re.created
	                        from ci_batches ba 
                            join ci_reports re ON re.patient_id = ba.assen
                            join ci_results rs ON rs.report_id = re.id  and rs.`pos-neg` = '{$_GET['pn']}'
                            where  {$where} and rs.drug='{$test_name}' 
                            order by ba.doc asc");
    ob_start();
    $patient_ids = array();
?>

    <table class="report_tbl">
        <thead>
            <tr>
                <th>#</th>
                <th>Barcode</th>
                <th>DOC</th>
                <th>Result Date</th>
                <th>Patient Name</th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($rs as $i => $r) {
                $patient_ids[$r->patient_id] = $r->patient_id;
            ?>
                <tr class="childp">
                    <td><?php echo $i + 1 ?></td>
                    <td><?php echo $r->assen ?></td>
                    <td><?php echo tr_pdd_display_date($r->doc) ?></td>
                    <td><?php echo tr_pdd_display_date($r->created) ?></td>
                    <td><?php echo $r->patient_name ?></td>
                </tr>
            <?php
            }
            ?>
        </tbody>
    </table>
<?php
    $content = ob_get_clean();

    echo '<div>Total Patients Testing Positive: ' . count($patient_ids) . '</div>';
    echo $content;

    exit;
}


if ($month &&  !empty($cli_ID)) {
    $submited = true;

    //patient age group
    $age_groups          = array();
    $age_groups['0-18']  = array(0, 18);
    $age_groups['19-30'] = array(19, 30);
    $age_groups['31-40'] = array(31, 40);
    $age_groups['41-50'] = array(41, 50);
    $age_groups['51-60'] = array(51, 60);
    $age_groups['61+']   = array(61);

    $clinic = $wpdb->get_row("select * from ci_clinic where cID in( {$cli_ID} ) ", ARRAY_A);
    $where = " ba.cli_ID in ({$cli_ID}) and ba.doc >= '{$month}-01' and ba.doc <= '{$month}-31 23:59:59' ";
    $where .= " and (ba.sync_type != 'H' or ba.sync_type is null)";


    if (in_array('tox', tr_pdd_get_batch_types())) {
        $medis = tr_pdd_get_report_medis();
        $codes = implode("','", $medis);



        $total_sample = intval($wpdb->get_var("select count(*) from ci_batches ba  where  {$where}"));
        $resulted     = intval($wpdb->get_var("select count(DISTINCT ba.mid) from ci_batches ba 
                            join ci_reports re ON re.patient_id = ba.assen
                            where  {$where}"));

        $pos = intval($wpdb->get_var("select count(DISTINCT ba.mid) from ci_batches ba 
                            join ci_reports re ON re.patient_id = ba.assen
                            join ci_results rs ON rs.report_id = re.id  and rs.`pos-neg` = 'POS'
                            where  {$where} "));

        $neg = $total_sample - $pos;

        $google_charts[1]   = array(array('Title', 'Count'));
        $google_charts[1][] = array('Neg for Rx', $neg);
        $google_charts[1][] = array('Pos for Rx', $pos);


        //chart2
        $notresulted = $total_sample - $resulted;

        $google_charts[2]   = array(array('Title', 'Count'));
        $google_charts[2][] = array('Unexpected Result', $notresulted);
        $google_charts[2][] = array('Expected Result', $resulted);


        //chart3
        //Illicits
        $tests = $wpdb->get_col("select te_name from ci_test_panels t JOIN ci_test_groups g ON g.tg_ID = t.te_group_id and g.tg_name = 'Illicits'");
        $tests = implode("','", $tests);

        $total_ill = intval($wpdb->get_var("select count(DISTINCT ba.mid) from ci_batches ba 
                            join ci_reports re ON re.patient_id = ba.assen
                            join ci_results rs ON rs.report_id = re.id  
                            and rs.drug in ('{$tests}')
                            where  {$where} "));

        $pos                = intval($wpdb->get_var("select count(DISTINCT ba.mid) from ci_batches ba 
                            join ci_reports re ON re.patient_id = ba.assen
                            join ci_results rs ON rs.report_id = re.id  and rs.`pos-neg` = 'POS'
                            and rs.drug in ('{$tests}')
                            where  {$where} "));
        $neg                = $total_ill - $pos;
        $google_charts[3]   = array(array('Title', 'Count'));
        $google_charts[3][] = array('Neg for Illicits', $neg);
        $google_charts[3][] = array('Pos for Illicits', $pos);


        //chart4
        $tests         = $wpdb->get_col("select te_name from ci_test_panels t JOIN ci_test_groups g ON g.tg_ID = t.te_group_id and g.tg_name = 'Alcohol'");
        $tests         = implode("','", $tests);
        $screening_etg = $wpdb->get_row("select * from ci_urine_validity where u_name = 'ETG'");
        $joinalo       = $wherealo = $wherealo_pos = '';
        if ($screening_etg) {
            $joinalo  = "left join ci_result_test_speciments rts ON rts.mid = ba.mid and rts.testid = {$screening_etg->u_ID}";
            $wherealo = ' or rts.checked = 1 ';
            if ($screening_etg->u_low > 0) {
                $wherealo_pos .= " or (rts.conc > 0 and rts.conc < {$screening_etg->u_low}) ";
            }

            if ($screening_etg->u_high > 0) {
                $wherealo_pos .= " or (rts.conc > {$screening_etg->u_high}) ";
            }


            if (!empty($t->conc) && ($t->conc < $test->u_low || ($t->conc > $test->u_high && $test->u_high > 0))) {
                $tests_pos[] = $test->u_name;
            }
        }
        $total_alo = intval($wpdb->get_var("select count(DISTINCT ba.mid) from ci_batches ba 
                            left join ci_reports re ON re.patient_id = ba.assen
                            left join ci_results rs ON rs.report_id = re.id  
                            {$joinalo}
                            where  {$where} and (rs.drug in ('{$tests}') {$wherealo}) "));

        $pos                = intval($wpdb->get_var("select count(DISTINCT ba.mid) from ci_batches ba 
                            left join ci_reports re ON re.patient_id = ba.assen
                            left join ci_results rs ON rs.report_id = re.id  and rs.`pos-neg` = 'POS' 
                            {$joinalo}
                            where  {$where} and (rs.drug in ('{$tests}') {$wherealo_pos}) "));
        $neg                = $total_alo - $pos;
        $google_charts[4]   = array(array('Title', 'Count'));
        $google_charts[4][] = array('Neg for alcohol', $neg);
        $google_charts[4][] = array('Pos for alcohol', $pos);


        //gender chart
        $male = intval($wpdb->get_var("select count(DISTINCT pa.pa_ID) from ci_patients pa 
                                JOIn ci_batches ba ON ba.patient_id = pa.pa_ID and (pa.pa_gender='M' or pa.pa_gender='')
                                where {$where} "));

        $female             = intval($wpdb->get_var("select count(DISTINCT pa.pa_ID) from ci_patients pa 
                                JOIn ci_batches ba ON ba.patient_id = pa.pa_ID and pa.pa_gender='F'
                                where {$where} "));
        $google_charts[5]   = array(array('Title', 'Count'));
        $google_charts[5][] = array('Female', $female);
        $google_charts[5][] = array('Male', $male);


        //patient number of samples
        $patients = $wpdb->get_results("select gr.c,count(*) as num from (select count(*) as c from ci_batches ba 
                                where {$where} group by ba.patient_id order by c asc) gr 
                                group by gr.c ", ARRAY_A);

        $pa_r     = array();
        $pa_total = 0;
        foreach ($patients as $p) {
            if ($p['c'] <= 2) {
                $pa_r[$p['c']] = $p['num'];
            } else {
                $pa_r[3] += $p['num'];
            }
            $pa_total += $p['num'];
        }
        $pa_per    = array();
        if (!isset($pa_r[1])) {
            $pa_r[1] = 0;
        }
        if (!isset($pa_r[2])) {
            $pa_r[2] = 0;
        }
        $pa_per[1] = ($pa_total) > 0 ? round($pa_r[1] / $pa_total * 100, 1) : 0;
        $pa_per[2] = $pa_total > 0 ? round($pa_r[2] / $pa_total * 100, 1) : 0;
        $pa_per[3] = $pa_total > 0 ? 100 - ($pa_per[1] + $pa_per[2]) : 0;



        $patient_ages        = array(array('Title', 'Count'));
        $year                = date_i18n('Y');
        foreach ($age_groups as $k => $vl) {

            $year_from = $year - (isset($vl[1]) ? $vl[1] : 0);
            $year_to   = $year - $vl[0];
            if (count($vl) == 1) {
                $year_from = '0000-00-00';
            }

            $dob_to         = $year_to . '-12-31';
            $dob_from       = $year_from . '-01-01';
            $count          = intval($wpdb->get_var("select count(DISTINCT patient_id) from ci_batches ba 
                                    where {$where} and ba.dob <= '{$dob_to}' and ba.dob >='{$dob_from}' "));
            $patient_ages[] = array($k, $count);
        }


        //Expected Positive Results
        $expected = array();
        foreach ($medis as $code) {
            $sql_all = "select 
                    COUNT(re.id) as c, 
                    SUM(re.has_{$code}_pos) as pos_count
                from ci_reports re
                join (select ba.assen,ba.doc from ci_batches ba where  {$where} ) 
                        as ba ON ba.assen = re.patient_id
                    ";
            $r       = $wpdb->get_row($sql_all, ARRAY_A);

            $expected[$code]['POS'] = $r['pos_count'];
            $expected[$code]['NEG'] = max(0, $r['c'] - $r['pos_count']);
        }

        /*
		$rs = $wpdb->get_results("select dc.dc_code, ct.ct_test from ci_bill_drug_classes_test ct
									JOIN ci_bill_drug_classes as dc ON ct.ct_dc_ID = dc.dc_ID
									where dc.dc_code in ('{$codes}') ");

		$codes_tests = array();
		$tests_arr = array();
		foreach($rs as $r)
		{
			$r->ct_test = strtoupper($r->ct_test);
			$codes_tests[] = $r->ct_test;
			$tests_arr[$r->ct_test] = $r->dc_code;
		}
		$codes_tests = implode("','",$codes_tests);
		$sql_all = "select rs.drug, count(DISTINCT re.id) as c,
							count(DISTINCT rs_pos.report_id) as pos_count
					from ci_reports re
					JOIN ci_results rs on re.id = rs.report_id
					Left JOIN ci_results rs_pos on  rs_pos.id = rs.id and rs_pos.`pos-neg` ='POS'
					join ci_batches ba ON ba.assen = re.patient_id
						where {$where} and rs.drug in ('{$codes_tests}')
						";
		$rs_all = $wpdb->get_results($sql_all, ARRAY_A);
		//echo $sql_all;

		$rs = array();
		foreach($rs_all as $r)
		{
			$r['drug'] = strtoupper($r['drug']);
			$dc_code = $tests_arr[$r['drug']];
			$rs[$dc_code]['pos_count'] += $r['pos_count'];
			$rs[$dc_code]['c'] += $r['c'];
		}

		$expected = array();
		foreach($rs as $dc_code => $r)
		{
			$expected[$dc_code]['POS'] = $r['pos_count'];
			$expected[$dc_code]['NEG'] = max(0,$r['c'] - $r['pos_count']);
		}
		*/


        $qns       = array();
        $total_qns = $wpdb->get_var("select count(ba.mid) from ci_batches ba where {$where} and " . tr_pdd_where_batch_rejected());
        $qns[1]    = $wpdb->get_var("select count(ba.mid) from ci_batches ba where {$where} and ba.type_of_specimen like 'urine%' and " . tr_pdd_where_batch_rejected());
        $qns[2]    = $wpdb->get_var("select count(ba.mid) from ci_batches ba where {$where} and ba.type_of_specimen like 'oral%' and " . tr_pdd_where_batch_rejected());

        $qns_per[1] = $total_sample > 0 ? round($qns[1] / $total_sample * 100, 1) : 0;
        $qns_per[2] = $total_sample > 0 ? round($qns[2] / $total_sample * 100, 1) : 0;


        //$validity
        $urine_tests = $wpdb->get_results("select * from ci_urine_validity where u_status = 1 and u_showinreq = 0", ARRAY_A);
        foreach ($urine_tests as $i => $utest) {
            $urine_tests[$i]['count'] = $wpdb->get_var("select count(ba.mid) from ci_batches ba 
                                    join ci_result_test_speciments rsp ON ba.mid = rsp.mid 
                                                            and rsp.testid = {$utest['u_ID']}
                                    where {$where} 
                                    and (rsp.conc < {$utest['u_low']} or rsp.conc > {$utest['u_high']} or outcome = 'POS' ) ");
            $urine_tests[$i]['percent'] = $total_sample > 0 ? round($urine_tests[$i]['count'] / $total_sample * 100, 1) : 0;
        }


        $monitored = array();
        /*
		$monitored[1]['title'] = 'THC-COOH';
		$monitored[1]['pos'] = intval($wpdb->get_var("select count(DISTINCT ba.mid) from ci_batches ba
								join ci_reports re ON re.patient_id = ba.assen
								join ci_results rs ON rs.report_id = re.id  and rs.`pos-neg` = 'POS'
								where  {$where} and rs.drug='THC-COOH' "));

		$monitored[1]['neg'] = intval($wpdb->get_var("select count(DISTINCT ba.mid) from ci_batches ba
								join ci_reports re ON re.patient_id = ba.assen
								join ci_results rs ON rs.report_id = re.id  and rs.`pos-neg` = 'NEG'
								where  {$where} and rs.drug='THC-COOH' "));
		$monitored[1]['percent'] = number_format($monitored[1]['pos'] / ($monitored[1]['pos'] + $monitored[1]['neg']) * 100,2);


		$monitored[1]['chartdata'] = array(array('Title','Count'));
		$monitored[1]['chartdata'][] = array('Neg',$monitored[1]['neg']);
		$monitored[1]['chartdata'][] = array('Pos',$monitored[1]['pos']);
	*/

        $monitored_tests = tr_get_option('ci_pdf_data', 'cli_static_tests', array());

        $monitored_tests_clinic     = get_option('ci_monitored_tests_clinics', array());
        if (isset($monitored_tests_clinic[$cli_ID]) && count($monitored_tests_clinic[$cli_ID]) > 0) {
            $monitored_tests = $monitored_tests_clinic[$cli_ID];
        }


        if (is_array($monitored_tests) && count($monitored_tests) > 0) {
            $monitored_tests = implode(',', $monitored_tests);
            $monitored_names = $wpdb->get_col("select te_name from ci_test_panels where te_ID in ({$monitored_tests})");
            foreach ($monitored_names as $i => $testname) {
                $key                        = $i + 1;
                $monitored[$key]['title'] = $testname;
                $monitored[$key]['pos']   = intval($wpdb->get_var("select count(DISTINCT ba.mid) from ci_batches ba 
                            join ci_reports re ON re.patient_id = ba.assen
                            join ci_results rs ON rs.report_id = re.id  and rs.`pos-neg` = 'POS'
                            where  {$where} and rs.drug='{$testname}' "));

                $monitored[$key]['neg']     = intval($wpdb->get_var("select count(DISTINCT ba.mid) from ci_batches ba 
                            join ci_reports re ON re.patient_id = ba.assen
                            join ci_results rs ON rs.report_id = re.id  and rs.`pos-neg` = 'NEG'
                            where  {$where} and rs.drug='{$testname}' "));
                $tt                           = $monitored[$key]['pos'] + $monitored[$key]['neg'];
                $monitored[$key]['percent'] = number_format(($tt > 0 ? $monitored[$key]['pos'] / $tt : 0) * 100, 2);


                $monitored[$key]['chartdata']   = array(array('Title', 'Count'));
                $monitored[$key]['chartdata'][] = array('Neg', $monitored[$key]['neg']);
                $monitored[$key]['chartdata'][] = array('Pos', $monitored[$key]['pos']);
            }
        }
    }

    //pcr
    if ($clinic['cli_pcr']) {

        $pcr_sum_group_id = tr_get_option('ci_result_settings_pcr', 'clinic_statistics_groupid', 0);
        if ($pcr_sum_group_id == 0) {
            $pcr_sum_group_id = $wpdb->get_var("select tg_ID from ci_test_groups 
            where tg_name ='Sexually Transmitted Infection' 
            and tg_show_req = 1");
        }


        //gender chart
        $male = intval($wpdb->get_var("select count(DISTINCT pa.pa_ID) from ci_patients pa 
                                JOIn ci_batches_pcr ba ON ba.patient_id = pa.pa_ID and (pa.pa_gender='M' or pa.pa_gender='')
                                where {$where} and panel_tests_requested like '%group_{$pcr_sum_group_id}%' "));

        $female = intval($wpdb->get_var("select count(DISTINCT pa.pa_ID) from ci_patients pa 
                                JOIn ci_batches_pcr ba ON ba.patient_id = pa.pa_ID and pa.pa_gender='F'
                                where {$where} and panel_tests_requested like '%group_{$pcr_sum_group_id}%' "));
        $google_charts[6] = array(array('Title', 'Count'));
        $google_charts[6][] = array('Female', $female);
        $google_charts[6][] = array('Male', $male);


        $patient_ages2 = array(array('Title', 'Count'));
        $year = date_i18n('Y');
        foreach ($age_groups as $k => $vl) {

            $year_from = $year - (isset($vl[1]) ? $vl[1] : 0);
            $year_to = $year - $vl[0];
            if (count($vl) == 1) {
                $year_from = '0000-00-00';
            }

            $dob_to = $year_to . '-12-31';
            $dob_from = $year_from . '-01-01';
            $count = intval($wpdb->get_var("select count(DISTINCT patient_id) from ci_batches_pcr ba 
                                    where {$where} and ba.dob <= '{$dob_to}' and ba.dob >='{$dob_from}' 
                                    and panel_tests_requested like '%group_{$pcr_sum_group_id}%' "));
            $patient_ages2[] = array($k, $count);
        }

        //tests in group
        $sql_tests_pcr = "select * from ci_test_panels where te_in_req = 1";

        if ($pcr_sum_group_id > 0) {

            $pcr_sum_group_ids = array($pcr_sum_group_id);

            $child_groups = $wpdb->get_col("select tg_ID from ci_test_groups
            where tg_parent = {$pcr_sum_group_id}");

            if (count($child_groups) > 0) {
                $pcr_sum_group_ids = array_merge($pcr_sum_group_ids, $child_groups);
            }
            $pcr_sum_group_ids = implode(',', $pcr_sum_group_ids);

            $sql_tests_pcr = "select * from ci_test_panels where te_group_id in ( {$pcr_sum_group_ids} )";
        }

        $pcr_tests = $wpdb->get_results($sql_tests_pcr);



        //by type
        $rs = $wpdb->get_results("select count(DISTINCT ba.mid) as count, 
                                        type_of_specimen
                                    from ci_batches_pcr ba 
                                    where  {$where} and panel_tests_requested like '%group_{$pcr_sum_group_id}%'
                                    group by type_of_specimen");


        $pcr_types = array();
        foreach ($rs as $r) {
            $types = explode(',', $r->type_of_specimen);
            foreach ($types as $type) {
                $type = strtolower($type);
                if (!isset($pcr_types[$type])) {
                    $pcr_types[$type] = 0;
                }
                $pcr_types[$type] += $r->count;
            }
        }
        $sql_specimens = "select * from ci_test_specimens where ts_type='pcr'";
        if ($pcr_sum_group_id > 0) {
            $sql_specimens .= " and ts_group_id={$pcr_sum_group_id} ";
        }
        $rs = $wpdb->get_results($sql_specimens);
        foreach ($rs as $r) {
            $type = strtolower($r->ts_name);
            if (!isset($pcr_types[$type])) {
                $pcr_types[$type] = 0;
            }
            //$pcr_types[$type] += 0;
        }

        $google_charts_pcr_types = array(array('Title', 'Count'));
        foreach ($pcr_types as $type => $count) {
            $type = ucfirst($type);
            $google_charts_pcr_types[] = array($type, $count);
        }
    }
}
?>


<h2>Reports</h2>
<form>
    <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
    <label style="float:left">Month: </label>
    <input type="hidden" id="select_month" name="month" value="<?php echo $month ?>" />
    <div class="select_month" style="float:left;margin-right:10px;" data-month="<?php echo $month ?>"></div>
    <?php
    if (isset($clinics)) {
    ?>
        <select name="cli_ID" class="select2" data-placeholder="Select Clinic">
            <option value=""></option>
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
    <input type="submit" class="button" value="Report" />
</form>

<script>
    jQuery(function($) {
        $('.select2').select2();
    })
</script>

<?php if (isset($submited) && $submited) { ?>
    <?php if (in_array('tox', tr_pdd_get_batch_types())) { ?>
        <div class="frow">
            <h4>Toxicology Summary Information</h4>
        </div>
        <div class="frow">
            <div id="chart_div1" class="chartdiv" style="width:23%; height:350px;float:left;margin-right:2%;"></div>
            <div id="chart_div2" class="chartdiv" style="width:23%; height:350px;float:left;margin-right:2%;"></div>
            <div id="chart_div3" class="chartdiv" title="Illicits" style="width:23%; height:350px;float:left;margin-right:2%;"></div>
            <div id="chart_div4" class="chartdiv" title="Alcohol" style="width:23%; height:350px;float:left"></div>
        </div>

        <br><br>
        <div class="frow">
            <h4>Additional Patient Statistics</h4>
        </div>
        <div class="frow">
            <div class="col2">
                <div class="title wbg">Gender Breakdown</div>
                <div id="chart_div5"></div>
                <table class="dashboard_tbl">
                    <tr class="header2">
                        <th colspan="3">Number of Samples Submited per Patient</th>
                    </tr>
                    <tr>
                        <th>Patient Samples</th>
                        <th>Total</th>
                        <th>%</th>
                    </tr>
                    <tr>
                        <td>One</td>
                        <td class="center"><?php echo isset($pa_r[1]) ? $pa_r[1] : '' ?></td>
                        <td class="center"><?php echo isset($pa_per[1]) ? $pa_per[1] : '' ?></td>
                    </tr>
                    <tr>
                        <td>Two</td>
                        <td class="center"><?php echo isset($pa_r[2]) ? $pa_r[2] : '' ?></td>
                        <td class="center"><?php echo isset($pa_per[2]) ? $pa_per[2] : '' ?></td>
                    </tr>
                    <tr>
                        <td>Three or More</td>
                        <td class="center"><?php echo isset($pa_r[3]) ? $pa_r[3] : '' ?></td>
                        <td class="center"><?php echo isset($pa_per[3]) ? $pa_per[3] : 0 ?></td>
                    </tr>
                </table>
                <br>
                <table class="dashboard_tbl">
                    <tr class="header2">
                        <th colspan="3">QNS - Rejected Results</th>
                    </tr>
                    <tr>
                        <th>Sample Type</th>
                        <th>Total</th>
                        <th>%</th>
                    </tr>
                    <tr>
                        <td>Urine</td>
                        <td class="center"><?php echo intval(isset($qns[1]) ? $qns[1] : 0) ?></td>
                        <td class="center"><?php echo floatval(isset($qns_per[1]) ? $qns_per[1] : 0) ?></td>
                    </tr>
                    <tr>
                        <td>Oral Fluids</td>
                        <td class="center"><?php echo intval(isset($qns[2]) ? $qns[2] : 0) ?></td>
                        <td class="center"><?php echo floatval(isset($qns_per[2]) ? $qns_per[2] : 0) ?></td>
                    </tr>
                </table>
            </div>
            <div class="col2">
                <div class="title wbg">Age Group Breakdown</div>
                <div id="chart_patient_age"></div>
                <table class="dashboard_tbl" style="margin-top:40px">
                    <tr>
                        <th>Abnormal Urine Validity</th>
                        <th>Total</th>
                        <th>%</th>
                    </tr>
                    <?php
                    foreach ($urine_tests as $utest) {
                    ?>
                        <tr>
                            <td><?php echo $utest['u_name'] ?></td>
                            <td class="center"><?php echo intval($utest['count']) ?></td>
                            <td class="center"><?php echo floatval($utest['percent']) ?></td>
                        </tr>
                    <?php
                    }
                    ?>
                </table>
            </div>
        </div>

        <div class="frow">
            <h4>UnExpected Positive Results (Consistent with reported medications by percentage)</h4>
            <div style="float:left;width:35%" class="width35">
                <table class="dashboard_tbl" style="background:#fff">
                    <tr>
                        <th>Tests</th>
                        <th>Pos/Neg</th>
                        <th>Percent Positive</th>
                    </tr>
                    <?php
                    $chart_expected = array();
                    $chart_expected[]  = array('Tests', '# Positive', '# Negative', array('role' => 'annotation'));
                    foreach ($medis as $txt => $code) :

                        $pos = intval($expected[$code]['POS']);
                        $neg = intval($expected[$code]['NEG']);

                        $percent = $pos + $neg == 0 ? 0 : $pos / ($pos + $neg) * 100;

                        $chart_expected[] = array($txt, intval($pos), intval($neg), '');
                    ?>
                        <tr>
                            <td title="<?php echo $code ?>"><?php echo $txt ?></td>
                            <td class="center">
                                <?php echo $pos ?> / <?php echo $neg ?>
                            </td>
                            <td class="center">
                                <?php echo round($percent, 2) ?>%
                            </td>
                        </tr>

                    <?php endforeach; ?>
                </table>
            </div>
            <div style="float:right;width:62%" class="width62">
                <div id="chart_report_expected"></div>
            </div>
        </div>

        <div class="frow">
            <h4>Monitored Tests</h4>
            <div class="frow">
                <?php
                foreach ($monitored as $i => $mo) {
                ?>
                    <div class="chart4">
                        <div id="monitored_div_<?php echo $i ?>"></div>
                        <div class="title"><?php echo $mo['title'] ?></div>
                    </div>
                <?php
                }
                ?>
            </div>

            <div class="col2">
                <table class="dashboard_tbl" style="background: #fff;">
                    <tr>
                        <th>Test name</th>
                        <th>POS</th>
                        <th>NEG</th>
                        <th>%</th>
                    </tr>
                    <?php foreach ($monitored as $item) { ?>
                        <tr>
                            <td><?php echo $item['title'] ?></td>
                            <td class="center"><a title="POS of <?php echo $item['title'] ?>" class="thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'monitored', 'tname' => $item['title'], 'pn' => 'pos')) ?>"><?php echo $item['pos'] ?></a> </td>
                            <td class="center"><?php echo $item['neg'] ?></td>
                            <td class="center"><?php echo $item['percent'] ?></td>
                        </tr>
                    <?php } ?>
                </table>
            </div>
        </div>

    <?php }

    if (in_array('blood', tr_pdd_get_batch_types()) && $clinic['cli_blood']) {
    }

    if (in_array('pcr', tr_pdd_get_batch_types()) && $clinic['cli_pcr']) {

        $pcr_sum_group_name = '';
        if ($pcr_sum_group_id > 0) {
            $pcr_sum_group_name = $wpdb->get_var("select tg_name from ci_test_groups where tg_ID = {$pcr_sum_group_id}");
        }

    ?>

        <div class="frow">
            <h4>PCR Summary</h4>
            <?php if ($pcr_sum_group_id > 0) { ?>
                <h4 class="darkbg"><?php echo $pcr_sum_group_name ?></h4>
            <?php } ?>
            <div class="frow">
                <div id="google_charts_pcr_types"></div>
            </div>
            <div class="frow">
                <div class="col2">
                    <div class="title wbg">Gender Breakdown</div>
                    <div id="chart_div6"></div>
                </div>
                <div class="col2">
                    <div class="title wbg">Age Group Breakdown</div>
                    <div id="chart_patient_age2"></div>
                </div>
            </div>
            <div style="float:left;width:36%" class="width35">
                <table class="dashboard_tbl" style="background:#fff">
                    <tr>
                        <th>Tests</th>
                        <th>Pos/Neg</th>
                        <th>Percent Positive</th>
                    </tr>
                    <?php
                    $chart_expected2 = array();
                    $chart_expected2[]  = array('Tests', '# Positive', '# Negative', array('role' => 'annotation'));
                    foreach ($pcr_tests as $test) {

                        if ($test->te_name == 'Covid-19') {
                            continue;
                        }

                        $sql = $wpdb->prepare("select count(DISTINCT re.id) as c, 
                        count(DISTINCT rs_pos.report_id) as pos_count 
                    from ci_reports_pcr re
                    JOIN ci_results_pcr rs on re.id = rs.report_id 
                    Left JOIN ci_results_pcr rs_pos on rs_pos.id = rs.id 
                                        and rs_pos.`pos-neg` in ('POS','POH','POL','POM','PVH','PVL') 
                    join ci_batches_pcr ba ON ba.assen = re.patient_id
                    where {$where} 
                    and rs.drug = convert(%s using latin1)   ", ($test->te_name));

                        if ($pcr_sum_group_id > 0) {
                            $sql .= " and panel_tests_requested like '%group_{$pcr_sum_group_id}%' ";
                        }
                        $rs = $wpdb->get_row($sql);

                        //var_dump($sql);
                        //var_dump($rs);

                        $pos = intval($rs->pos_count);
                        $neg = intval($rs->c - $rs->pos_count);

                        if ($pos + $neg == 0) continue;

                        $percent = $pos + $neg == 0 ? 0 : $pos / ($pos + $neg) * 100;

                        $chart_expected2[] = array($test->te_name, intval($pos), intval($neg), '');
                    ?>
                        <tr>
                            <td><?php echo $test->te_name ?></td>
                            <td>
                                <?php echo $pos ?> / <?php echo $neg ?>
                            </td>
                            <td>
                                <?php echo round($percent, 2) ?>%
                            </td>
                        </tr>

                    <?php } ?>
                </table>
            </div>
            <div style="float:right;width:62%" class="width62">
                <div id="chart_report_expected2"></div>
            </div>
        </div>

    <?php } ?>


<?php } ?>

<style>
    .editable input {
        width: 80px;
    }

    .ui-datepicker-calendar {
        display: none;
        color: #94cc58;
    }

    .update-nag {
        display: none
    }

    .select_month .ui-datepicker .ui-datepicker-header,
    .select_month .ui-datepicker {
        padding: 0;
    }

    .select_month .ui-widget-header,
    .select_month .ui-widget-content,
    .select_month .ui-datepicker {
        background: none;
        border: 0
    }

    .select_month .ui-datepicker .ui-datepicker-prev,
    .select_month .ui-datepicker .ui-datepicker-next {
        background: #35afe3
    }

    .ui-datepicker-calendar {
        display: none;
    }

    .patient_report {
        width: 100%;
        border: 1px solid #999;
    }

    .patient_report th {
        background: #999;
        color: #fff;
    }

    .frow .title {
        text-align: center;
        font-weight: 700;
        font-size: 16px;
    }

    .frow .title.wbg {
        background: #fff;
        padding-top: 20px;
        padding-bottom: 20px;
    }
</style>
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script type="text/javascript">
    google.charts.load('current', {
        'packages': ['corechart']
    });
    google.charts.setOnLoadCallback(drawChart_all);

    function drawChart_all() {
        // Set chart options
        var options = {
            legend: {
                position: 'top',
                maxLines: 8
            },
            pieSliceText: 'label',
            sliceVisibilityThreshold: 0
        };
        <?php
        foreach ($google_charts as $i => $t) {
        ?>
            var data = google.visualization.arrayToDataTable(<?php echo json_encode($t) ?>);

            var chart = new google.visualization.PieChart(document.getElementById('chart_div<?php echo $i ?>'));
            chart.draw(data, options);
        <?php
        }
        foreach ($monitored as $i => $t) {
        ?>
            var data = google.visualization.arrayToDataTable(<?php echo json_encode($t['chartdata']) ?>);

            var chart = new google.visualization.PieChart(document.getElementById('monitored_div_<?php echo $i ?>'));
            chart.draw(data, options);
        <?php
        }

        ?>

        <?php if (isset($patient_ages) && count($patient_ages) > 0) { ?>
            var data = google.visualization.arrayToDataTable(<?php echo json_encode($patient_ages) ?>);

            var options = {
                legend: 'none',
                bar: {
                    groupWidth: '75%'
                },
                isStacked: true,
                sliceVisibilityThreshold: 0
            };

            // Instantiate and draw our chart, passing in some options.
            var chart = new google.visualization.BarChart(document.getElementById('chart_patient_age'));
            chart.draw(data, options);
        <?php } ?>

        <?php if (isset($patient_ages2)) { ?>
            var data = google.visualization.arrayToDataTable(<?php echo json_encode($patient_ages2) ?>);

            var options = {
                legend: 'none',
                bar: {
                    groupWidth: '75%'
                },
                isStacked: true,
                sliceVisibilityThreshold: 0
            };

            // Instantiate and draw our chart, passing in some options.
            var chart = new google.visualization.BarChart(document.getElementById('chart_patient_age2'));
            chart.draw(data, options);

            <?php if (isset($chart_expected2) && count($chart_expected2) > 1) { ?>
                var data = google.visualization.arrayToDataTable(<?php echo json_encode($chart_expected2) ?>);
                var options = {
                    height: 610,
                    legend: {
                        position: 'top',
                        maxLines: 8
                    },
                    //bar: { groupWidth: '75%' },
                    isStacked: true,
                    colors: ['red', 'blue'],
                    sliceVisibilityThreshold: 0
                };
                // Instantiate and draw our chart, passing in some options.
                var chart = new google.visualization.BarChart(document.getElementById('chart_report_expected2'));
                chart.draw(data, options);
            <?php } ?>


            //pcr types
            var data = google.visualization.arrayToDataTable(<?php echo json_encode(isset($google_charts_pcr_types) ? $google_charts_pcr_types : array()) ?>);
            var options = {
                pieSliceText: 'label',
                sliceVisibilityThreshold: 0
            };
            // Instantiate and draw our chart, passing in some options.
            var chart = new google.visualization.PieChart(document.getElementById('google_charts_pcr_types'));
            chart.draw(data, options);
        <?php } ?>

        //
        var data = google.visualization.arrayToDataTable(<?php echo json_encode(isset($chart_expected) ? $chart_expected : array()) ?>);
        var options = {
            height: 410,
            legend: {
                position: 'top',
                maxLines: 8
            },
            //bar: { groupWidth: '75%' },
            isStacked: true,
            colors: ['red', 'blue'],
            sliceVisibilityThreshold: 0
        };
        // Instantiate and draw our chart, passing in some options.
        var chart = new google.visualization.BarChart(document.getElementById('chart_report_expected'));
        chart.draw(data, options);





    }

    jQuery(function($) {
        $('.select_month').each(function() {
            $date = $(this).data('month') + '-01';
            $(this).datepicker({
                dateFormat: 'yy-mm-dd',
                setDate: $date,
                defaultDate: 0,
                changeMonth: true,
                changeYear: true,
                onChangeMonthYear: function(year, month, inst) {
                    $('#select_month').val(year + '-' + month);
                }
            });
            $(this).datepicker('setDate', $date);
        });

    })
</script>
