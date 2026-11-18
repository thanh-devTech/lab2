<?php
global $wpdb;

$btype = 'blood';

if(($patient_id > 0 || $summary) && !empty($from_date)) {

	$where = '';
	$where_tests = "";
	if($selected_panelid>0)
	{
		$where_tests.= " and te.te_group_id = {$selected_panelid} ";
	}

	$where .= " and ba.doc >= '{$from_date}' 
               and ba.doc <= '{$to_date} 23:59:59' ";

	if ($current_role == 'doctor') {
		$doc_id = get_user_meta($user_id, '_doc_id', true);
		$where .= " and ba.doc_ID = {$doc_id}";
	} else if ($current_role == 'clinic') {
		$cli_id = get_user_meta($user_id, '_clinic_id', true);
		$where .= " and ba.cli_ID = {$cli_id}";
	} else if ($current_role == 'office_manager' || $current_role == 'clinic') {

		$cid = get_user_meta($user_id, '_clinic_id', true);
		$clinics = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
		$clinics = implode(',', $clinics);
		$where .= " and ba.cli_ID in ({$clinics}) ";

	}

	if($selected_panelid>0)
	{
		$where.= " and FIND_IN_SET ('GROUP_{$selected_panelid}', ba.panel_tests_requested) ";
	}


	$tests = array();
	if($summary)
	{
		$wpdb->query("SET SESSION group_concat_max_len = 1000000;");

		$sql = "
            select group_concat(re.id) as id,
            date_format(ba.doc,'%m/%d/%y') AS date_month
            
            from ci_reports_blood re
            JOIN ci_batches_blood ba ON ba.assen = re.patient_id
            where 1 {$where} 
            group by date_month
            order by date_month asc";
		$all_dates = $wpdb->get_results($sql);


		foreach ($all_dates as $i=> $d) {
			$has_data = false;
			$sql = "
		        SELECT rs.drug,
		            AVG(rs.conc) as conc,
		            te.*
		    FROM ci_results_blood rs
		    join ci_test_panels te ON rs.drug = te.te_name and te.te_type='blood'
		    WHERE report_id in ({$d->id} )
		     {$where_tests}
		     group by drug
		    ";
			$all_res = $wpdb->get_results($sql, ARRAY_A);

			if(isset($_GET['debug'])){
				var_dump($sql);echo count($all_res);
				echo '<br><br>';
			}


			foreach ($all_res as $arr) {
				$arr['drug'] = strtoupper($arr['drug']);

				$arr = tr_pdd_parse_blood_test($arr, $d->instrument,$arr['te_group_id'],array('created_date'=> $d->created_date));

				if (!isset($tests[$arr['drug']])) {
					$tests[$arr['drug']] = array();
				}
				$arr['conc'] = number_format($arr['conc'],2);
				$tests[$arr['drug']][$d->date_month] = $arr;
				$has_data = true;
			}
			if(!$has_data)
			{
				unset($all_dates[$i]);
			}

		}
	}
	else{
		$where.= " and ba.patient_id  = '{$patient_id}' ";

		$sql = "
            select re.id, 
            re.instrument,
            re.created_date,
            ba.notes,
            ba.notes_report,
            date_format(ba.doc,'%m/%d/%y') AS date_month,
            ba.assen
            from ci_reports_blood re
            JOIN ci_batches_blood ba ON ba.assen = re.patient_id
            where 1 {$where} 
            order by ba.doc asc";
		$all_dates = $wpdb->get_results($sql);

		foreach ($all_dates as $i=> $d) {

			$sql = "
		        SELECT *
		    FROM ci_results_blood rs
		    join ci_test_panels te ON rs.drug = te.te_name and te.te_type='blood'
		    WHERE report_id='" . $d->id . "' {$where_tests}
		    ";

			if(isset($_GET['debug'])){
				var_dump($sql);
			}
			$all_res = $wpdb->get_results($sql, ARRAY_A);

			foreach ($all_res as $arr) {
				$arr['drug'] = strtoupper($arr['drug']);

				$arr = tr_pdd_parse_blood_test($arr, $d->instrument,$arr['te_group_id'],$d->created_date);

				if (!isset($tests[$arr['drug']])) {
					$tests[$arr['drug']] = array();
				}
				$tests[$arr['drug']][$d->id] = $arr;
			}
		}

	}


	if(isset($_REQUEST['trajax']) && stripos($_REQUEST['trajax'],'export')!==false)
	{
		ob_clean();
		$filename = 'export.csv';
		header("Content-disposition: attachment; filename={$filename}");
		header("Content-Type: text/csv");
		$f = fopen('php://output','w');
		$headers = array('Test');
		$barcode_row = array('Barcode');
		$pnotes_row = array('Physician Notes');
		$reportnotes_row = array('Report Notes');
		foreach($all_dates as $d)
		{
			$headers[] = $d->date_month;
			$barcode_row[] = $d->assen;
			$pnotes_row[] = $d->notes;
			$reportnotes_row[] = $d->notes_report;
		}
		fputcsv($f,$headers);
		if(!$summary)
		{
			fputcsv($f,$barcode_row);
		}


		foreach($tests as $drug => $data)
		{
			$row = array(
				$drug,
			);
			foreach($all_dates as $d)
			{
				if($summary)
				{
					$row[] = $data[$d->date_month]['conc'];
				}else{
					$row[] = $data[$d->id]['conc'];
				}

			}
			fputcsv($f,$row);
		}
		if(!$summary)
		{
			fputcsv($f,$pnotes_row);
			fputcsv($f,$reportnotes_row);
		}


		exit;
	}

	?>
    <div class="wraptbl">
        <table class="wp-list-table widefat striped posts blue_tbl hover_tbl border_tbl">
            <thead>
            <tr>
                <th>Test</th>
				<?php
				foreach ($all_dates as $i=> $d) {
					?>
                    <th class="center" title="<?php echo $d->id ?>"><?php echo $d->date_month ?></th>
					<?php
				}
				?>
            </tr>
            </thead>
            <tbody>
			<?php
			if(count($all_dates)>0 || count($tests)>0){
				if(!$summary)
				{
					?>
                    <tr>
                        <td>Barcode</td>
						<?php
						foreach($all_dates as $d)
						{
							?>
                            <td class="center"><?php echo $d->assen?></td>
							<?php
						}
						?>
                    </tr>
                    <tr>
                        <td>Prescribed</td>
						<?php
						foreach($all_dates as $d)
						{
							?>
                            <td><?php echo str_replace(',',', ',$d->medications)?></td>
							<?php
						}
						?>
                    </tr>
					<?php
				}
				foreach ($tests as $drug => $data)
				{
					ob_start();
					$json = array(array('Date','Cons','Cons','Cons'));
					$novalue = array();
					$inovalue = 0;
					$test_cutoffs = array();
					$test_limits = array();

					$i = 0;
					foreach ($all_dates as $d)
					{
						if($summary)
						{
							$arr = $data[$d->date_month];
						}else{
							$arr = $data[$d->id];
						}

						if (!empty($arr['conc']))
						{
							$conc = str_replace(',', '', $arr['conc']);
							if (strpos($conc, '>') !== false) {
								$conc = $arr['limit'];
								if(!in_array($arr['limit'],$test_limits) && $arr['limit']>0)
								{
									$test_limits[] = $arr['limit'];
								}
							}

							if($arr['conc']=='<' && empty($arr['pos-neg'])){
								$arr['conc'] = '';
								$arr['pos-neg'] = 'NEG';
							}

							if (strpos($conc, '<') !== false) {
								$conc = $arr['cutoff'];
								if(!in_array($arr['cutoff'],$test_cutoffs) && $arr['cutoff']>0)
								{
									$test_cutoffs[] = $arr['cutoff'];
								}
								$json[$i+1] = array($d->date_month,floatval($conc),floatval($conc), ($i==0? floatval($conc)  : null));
							}
							else{
								$json[$i+1] = array($d->date_month,floatval($conc),floatval($conc) ,floatval($conc));
							}


							if(!isset($novalue[$inovalue]['last']) && isset($novalue[$inovalue]['first']))
								$novalue[$inovalue]['last'] = floatval($conc);
							$inovalue++;


						}else{
							$json[$i+1] = array($d->date_month,floatval($conc),null,null);

							$novalue[$inovalue]['vl'][] = $i+1;
							if(!isset($novalue[$inovalue]['first']))
								$novalue[$inovalue]['first'] = $conc;
						}
						$i++;
						?>
                        <td class="is_pos_<?php echo $arr['pos-neg'] ?>"><?php echo $arr['conc'] ?></td>
						<?php
					}

					foreach($novalue as  $novl)
					{
						if(count($novl)==0)continue;
						$min = min($novl['first'],$novl['last']);
						$max = max($novl['last'],$novl['first']);
						if($min==0)$min=$max;
						$stepvl = ($max - $min) / (count($novl['vl']) + 1);


						foreach($novl['vl'] as $j => $pos)
						{
							$ii = ($novl['first']<$novl['last'])? $j + 1: count($novl['vl']) - $j;
							$vl = $min + $stepvl * $ii;
							$json[$pos][1] = $vl;
						}

					}

					//add cutoff & limit lines
					foreach($json as $k =>$vl)
					{
						foreach($test_cutoffs as $cut)
						{
							if($k==0)
							{
								$json[$k][] = 'Cutoff';
							}else{
								$json[$k][] = floatval($cut);
							}
						}
						foreach($test_limits as $cut)
						{
							if($k==0)
							{
								$json[$k][] = 'Limit';
							}else{
								$json[$k][] = floatval($cut);
							}
						}
					}
					if(isset($_GET['debug'])) {
					    echo '<pre>';
						var_export( $json );
						echo '</pre>';
						echo '<br><br>';
					}
					$cols = ob_get_clean();
					?>
                    <tr class="childp">
                        <td><a href="#" class="show_chart_trends" data-json="<?php echo esc_attr(json_encode($json))?>"><?php echo $drug ?></a>
                        </td>
						<?php echo $cols ?>
                    </tr>
					<?php
				}
			}
			else{
				echo '<tr><td><h4>No Results Found!</h4></td></tr>';
			}
			?>
            </tbody>
        </table>
    </div>

	<?php
}

