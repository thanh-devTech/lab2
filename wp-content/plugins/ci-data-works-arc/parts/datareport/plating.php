<?php
global $wpdb;
$today      = date_i18n('Y-m-d');
$date_from  = isset($_REQUEST['date_from']) ? $_REQUEST['date_from'] : $today;
$date_to    = isset($_REQUEST['date_to']) ? $_REQUEST['date_to'] : $today;
$is_summary = isset($_REQUEST['summary']) ? $_REQUEST['summary'] : 0;
$byDay 		= isset($_REQUEST['byday']) ? $_REQUEST['byday'] : 0;

wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');


$results = array();
if ($date_from && $date_to) {



	if ($is_summary) {
		$new_results = array();


		if (!is_array($rack_panels)) {
			$rack_panels = array();
		}

		$group_style = array();
		if (count($rack_panels) > 0) {
			$panels_ids_str = implode(',', $rack_panels);
			$rack_panels = array();
			$where_style = " tg_ID in ({$panels_ids_str}) ";
		} else {
			$where_style = " tg_plate_style  > 0 ";
		}


		$rs = $wpdb->get_results("select tg_ID, tg_name, tg_plate_style, tg_other_options
                    from ci_test_groups 
                    where {$where_style}
                    order by tg_is_covid desc, tg_plate_style asc, tg_position asc");
		foreach ($rs as $r) {
			if ($r->tg_plate_style > 0) {
				$other_opts = json_decode($r->tg_other_options, true);
				if (isset($other_opts['rack_name']) && !empty($other_opts['rack_name'])) {
					$r->tg_name = $other_opts['rack_name'];
				}

				if ($r->tg_name == 'RPP') {
					$rack_panels[$r->tg_ID] = $r->tg_name;
				} else if (isset($group_style[$r->tg_plate_style])) {
					$rack_panels[$group_style[$r->tg_plate_style]] .= ' / ' . $r->tg_name;
					if (stripos($rack_panels[$group_style[$r->tg_plate_style]], 'uti') !== false && stripos($rack_panels[$group_style[$r->tg_plate_style]], 'wound') !== false) {
						$rack_panels[$group_style[$r->tg_plate_style]] = 'UTI / Wound';
					}
				} else {
					$rack_panels[$r->tg_ID] = $r->tg_name;
					$group_style[$r->tg_plate_style] = $r->tg_ID;
				}
			} else if (stripos($r->tg_name, 'Wound') !== false && stripos($r->tg_name, 'ABX') > 0) {
				if (in_array('UTI', $rack_panels)) {
					$utipid = array_search('UTI', $rack_panels);
					$rack_panels[$utipid] = 'UTI/Wound';
				} else {
					$rack_panels[$r->tg_ID] = 'Wound';
					$rack_panels[$r->tg_ID * -1] = 'ABX';
				}
			} else if (stripos($r->tg_name, 'UTI') !== false && stripos($r->tg_name, 'ABX') > 0) {
				if (in_array('Wound', $rack_panels)) {
					$utipid = array_search('Wound', $rack_panels);
					unset($rack_panels[$utipid]);
					unset($rack_panels[$utipid * -1]);

					$rack_panels[$r->tg_ID]       = 'UTI/Wound';
					$rack_panels[$r->tg_ID * -1] = 'ABX';
				} else {
					$rack_panels[$r->tg_ID]       = 'UTI';
					$rack_panels[$r->tg_ID * -1] = 'ABX';
				}
			} else {
				$rack_panels[$r->tg_ID] = $r->tg_name;
			}
		}


		if (tr_get_option('ci_pdf_data', 'rack_mixed384')) {
			$rack_panels[-300] = 'Mixed';
		}

		if (in_array('UTI', $rack_panels) && in_array('RPP', $rack_panels)) {
			$rack_panels[-400] = 'UTI/RPP - ABX';
		}


		$sql = "select pl_today,
		ra.re_panel_id,
		(select count(*) from ci_plates_batches pb where pb.pl_ID = pl.pl_ID and pb.mid>0) as rf_number_of_assen
		from ci_plates pl
		JOIN ci_racks ra ON ra.re_ID = pl.pl_rack_ID
		where  pl.pl_today >='{$date_from}' and pl.pl_today<='{$date_to}'
		order by pl_today desc, re_panel_id asc";
		$results = $wpdb->get_results($sql);

		foreach ($results as $i => $row) {
			$panel = $rack_panels[$row->re_panel_id];


			$row->panel = $panel;
			$row->date = date('Y/m/d', strtotime($row->pl_today));
			if ($byDay) {

				$panel = $row->date . ' ' . $panel;
			}


			$row->rf_number_of_plates = 1;

			if (!isset($new_results[$panel])) {
				$new_results[$panel] = $row;
			} else {
				$new_results[$panel]->rf_number_of_plates++;
				$new_results[$panel]->rf_number_of_assen += $row->rf_number_of_assen;
			}
		}
		$results = $new_results;
	} else {
		$where = " rf_created >='{$date_from}' and rf_created<='{$date_to} 23:59:59' ";

		$sql = "select *
		    from ci_reports_files rf
		    where {$where}
		    order by rf_created desc";

		$results = $wpdb->get_results($sql);
	}

	foreach ($results as $i => $row) {
		if (!is_numeric($row->rf_number_of_plates)) {

			$batch_tbl = tr_pdd_get_table_by_btype($row->rf_btype);
			$assens = str_replace(',', "','", $row->rf_assen_list);
			$mids = $wpdb->get_col(
				"select ba.mid from  {$batch_tbl} ba  
					where ba.assen in ('{$assens}') "
			);
			$mids = implode(',', $mids);
			$number = $wpdb->get_var("select count(distinct(pd.pl_ID))
						from ci_plates_batches pd
						where pd.btype='{$row->rf_btype}'
						and pd.mid in ({$mids})");

			$results[$i]->rf_number_of_plates = $number;

			if (!$is_summary && $row->rf_ID > 0) {
				$wpdb->update('ci_reports_files', array(
					'rf_number_of_plates' => $number,
				), array('rf_ID' => $row->rf_ID));
			}
		}
	}
}

if (isset($_REQUEST['trajax'])) {
	ob_clean();
	$filename = 'Plating_Report_' . date('md', strtotime($date_from)) . '-' . date('md', strtotime($date_to)) . '.csv';
	if($byDay)
	{
		$filename= 'Daily_'.$filename;
	}
	header("Content-disposition: attachment; filename={$filename}");
	header("Content-Type: text/csv");
	$output = fopen("php://output", 'w');

	$lab = CI_Base_Lab::get_current_lab();
	$labname = $lab->lab_name;
	$headers = array('Plating Report  ' . date('Y/m/d', strtotime($date_from)) . ' - ' . date('Y/m/d', strtotime($date_to)) . ' ' . $labname);
	fputcsv($output, $headers);
	if ($is_summary) {
		if($byDay)
		{
			$headers = array('Date','Plate', 'Number of plates', 'Number of Samples');
		}else{
			$headers = array('Plate', 'Number of plates', 'Number of Samples');
		}
		
		fputcsv($output, $headers);

		$showed_dates = array();
		foreach ($results as $row) {

			if ($byDay) {
				if (!in_array($row->date, $showed_dates)) {
					$showed_dates[] = $row->date;
				} else {
					$row->date = '';
				}
				$datarow = array(
					$row->date,
					$row->panel,
					$row->rf_number_of_plates,
					$row->rf_number_of_assen,
				);
			} else {
				$datarow = array(
					$row->panel,
					$row->rf_number_of_plates,
					$row->rf_number_of_assen,
				);
			}

			fputcsv($output, $datarow);
		}
	} else {
		$headers = array('File Name', 'Date/Time', 'Number of plates', 'Number of Samples');
		fputcsv($output, $headers);

		foreach ($results as $row) {
			$row->rf_number_of_assen = count(explode(',', $row->rf_assen_list));

			$datarow = array(
				$row->rf_file,
				$row->rf_created,
				$row->rf_number_of_plates,
				$row->rf_number_of_assen,
			);
			fputcsv($output, $datarow);
		}
	}

	@fclose($output);

	exit;
}

?>
<div class="wrap">
	<h2>Plating Report</h2>

	<form method="get">
		<input type="hidden" name="page" value="<?php echo isset($_GET['page']) ? $_GET['page'] : '' ?>" />
		<input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />
		<label>From: </label>
		<input type="text" name="date_from" id="from" value="<?php echo $date_from ?>" />
		<label>To: </label>
		<input type="text" name="date_to" id="to" value="<?php echo $date_to ?>" />

		<label><input type="checkbox" name="summary" value="1" <?php checked($is_summary) ?> /> Summary </label>
		&nbsp;&nbsp;
		<label><input type="checkbox" name="byday" value="1" <?php checked($byDay) ?> />By day </label>
		<input type="submit" value="Run Report" class="button bsty6" />
		<input type="submit" name="trajax" value="Export" class="button" />
	</form>
	<br>

	<?php if ($is_summary) {
	?>
		<table class="report_tbl">
			<thead>
				<tr>
					<?php if ($byDay) {
						echo '<th style="width:120px">Date</th>';
					}
					?>
					<th style="width:18%">Plate</th>
					<th style="width:13%">Number of Plates</th>
					<th>Number of Samples</th>
				</tr>
			</thead>
			<tbody>
				<?php
				$showed_dates = array();
				if (count($results) > 0) {
					foreach ($results as $row) {
				?>
						<tr class="childp">
							<?php
							if ($byDay) {
								if (!in_array($row->date, $showed_dates)) {
									echo '<td>' . $row->date . '</td>';
									$showed_dates[] = $row->date;
								} else {
									echo '<td></td>';
								}
							}
							?>
							<td><?php echo $row->panel ?></td>
							<td><?php echo $row->rf_number_of_plates ?></td>
							<td><?php echo $row->rf_number_of_assen ?></td>
						</tr>
				<?php
					}
				}
				?>
			</tbody>
		</table>
	<?php
	} else { ?>
		<table class="report_tbl">
			<thead>
				<tr>
					<th>File Name</th>
					<th style="width:18%">Date/Time</th>
					<th style="width:13%">Number of Plates</th>
					<th style="width:13%">Number of Samples</th>
				</tr>
			</thead>
			<tbody>
				<?php
				if (count($results) > 0) {
					foreach ($results as $row) {
						$row->rf_number_of_assen = count(explode(',', $row->rf_assen_list));
				?>
						<tr class="childp">
							<td><?php echo $row->rf_file ?></td>
							<td><?php echo $row->rf_created ?></td>
							<td><?php echo $row->rf_number_of_plates ?></td>
							<td><?php echo $row->rf_number_of_assen ?></td>
						</tr>
				<?php
					}
				}
				?>
			</tbody>
		</table>
	<?php } ?>

</div>


<script>
	jQuery(function($) {
		$("#from").datepicker({
			changeMonth: true,
			changeYear: true,
			numberOfMonths: 1,
			dateFormat: 'yy-mm-dd',
			onClose: function(selectedDate) {
				$("#to").datepicker("option", "minDate", selectedDate);
			}
		});
		$("#to").datepicker({
			changeMonth: true,
			changeYear: true,
			numberOfMonths: 1,
			dateFormat: 'yy-mm-dd',
			onClose: function(selectedDate) {
				$("#from").datepicker("option", "maxDate", selectedDate);
			}
		});
	})
</script>