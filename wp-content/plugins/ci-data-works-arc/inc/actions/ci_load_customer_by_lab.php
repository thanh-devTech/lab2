<?php
global $wpdb, $blog_id, $current_role;
$user_id = get_current_user_id();
$enable_pcr_panel = tr_get_option('ci_pdf_data', 'e_ful_panels');
$pcr_panels = array('uri', 'uti', 'wound', 'covid');
$all_types = array();
$all_panels_ids = array();
foreach (tr_pdd_get_batch_types() as $btype) {
	if ($btype == 'tox') {
		$all_types['tox_oral'] = 'Oral';
		$all_types['tox_urine'] = 'Urine';
	} else if ($btype == 'pcr' && $enable_pcr_panel) {
		$all_types['pcr_uri'] = 'URI';
		$all_types['pcr_uti'] = 'UTI';
		$all_types['pcr_wound'] = 'Wound';
		$all_types['pcr_covid'] = 'Covid-19';

		$all_panels_ids['uri'] = $wpdb->get_var("select tg_ID from ci_test_groups where tg_name = 'Upper Respiratory' or tg_short_name = 'uri' ");
		$all_panels_ids['uti'] = $wpdb->get_var("select tg_ID from ci_test_groups where tg_name = 'UTI+ w/ABX Resistance' or tg_short_name = 'UTI+' ");
		$all_panels_ids['wound'] = $wpdb->get_var("select tg_ID from ci_test_groups where tg_name = 'Wound' or tg_short_name = 'WOU' ");
		$all_panels_ids['covid'] = $wpdb->get_var("select tg_ID from ci_test_groups where tg_name = 'nCoV-2019' or tg_is_covid = '1' ");
	} else {
		$all_types[$btype] = tr_pdd_get_typename($btype);
	}
}


$start_time = microtime(true);


$posts_per_page = 30;
$offset = $_REQUEST['offset'] > 0 ? $_REQUEST['offset'] : 0;
$lab_id = $_REQUEST['lab_id'];
$date_order = isset($date_order) ? $date_order : '';
$dateorderlimit = date('Y-m-d', strtotime('-60 days'));
$current_offset = get_option('gmt_offset') * HOUR_IN_SECONDS;
$yesterday = strtotime(date('Y-m-d', time() + $current_offset)) - 86400;

$search = isset($_REQUEST['s']) ? $_REQUEST['s'] : '';
$date_from = isset($_REQUEST['date_from']) ? $_REQUEST['date_from'] : '';
$date_to   = isset($_REQUEST['date_to']) ? $_REQUEST['date_to'] : '';

$month3_from = date_i18n('Y-m-01');
$month3_to = date_i18n('Y-m-t');

$month2_from = date('Y-m-01', strtotime('-1 month', strtotime($month3_from)));
$month2_to = date('Y-m-t', strtotime($month2_from));

$month1_from = date('Y-m-01', strtotime('-1 month', strtotime($month2_from)));
$month1_to = date('Y-m-t', strtotime($month1_from));

$month1 = date('Y M', strtotime($month1_from));
$month2 = date('Y M', strtotime($month2_from));
$month3 = date('Y M', strtotime($month3_from));


$where = " ";
$joins = "";

if ($lab_id > 0) {
	$where .= " and cli.`cli_lab-ID` = {$lab_id}  ";
}

if (!empty($search)) {
	//search clinic , doctor
	$where .= " and ( cli.cli_clinic like '%{$search}%' or doc.doc_first like '%{$search}%' or doc.doc_last like '%{$search}%') ";
}
if (!empty($date_from) || !empty($date_to)) {
	if (!empty($date_from)) {
		$where .= " and (co.ship_date >= '{$date_from}' or od.date >= '{$date_from}') ";
	}

	if (!empty($date_to)) {
		$where .= " and (co.ship_date <= '{$date_to} 23:59:59' or od.date <= '{$date_to} 23:59:59') ";
	}

	//$joins.= " JOIN ci_customer_orders co ON co.doc_ID = doc.doc_ID and co.cli_ID = cli.cID ";
	$joins .= " JOIN (select id,doc_ID,cli_ID,viewed,date,datetime,txn_id 
                        from wp_wp_eStore_customer_tbl group by txn_id) od 
                        ON od.doc_ID = doc.doc_ID and od.cli_ID=cli.cID ";
} else {
	$joins .= " left JOIN (select id,doc_ID,cli_ID,viewed,date,datetime,txn_id 
                        from wp_wp_eStore_customer_tbl where date >'{$dateorderlimit}' group by txn_id) od 
                        ON od.doc_ID = doc.doc_ID and od.cli_ID=cli.cID ";
}

$more_cli_fields = '';
if ($enable_pcr_panel) {
	$more_cli_fields .= ",cli_pcr_uri,cli_pcr_uti,cli_pcr_wound,cli_pcr_covid";
}


$sql = "
    select doc.doc_ID,
        doc.doc_first,
        doc.doc_last,
        doc.doc_phone,
        if(od.date is null, cd.date_order, od.datetime) as date_order,
        
        cli.cID,
        cli.cli_clinic,
        lr.rep_first,
        lr.rep_last,
        lr.rep_phone,
        if(co.ship_date is null and od.id >0, 1, 0) as notcompleted
        {$more_cli_fields}
        
    from ci_doc doc
    JOIN ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
    JOIN ci_clinic  cli ON cd.cli_ID = cli.cID
    left JOIN ci_lab_reps lr ON lr.rep_ID = cli.`cli_rep-ID`
    {$joins}
    left JOIN ci_customer_orders co ON co.doc_ID = doc.doc_ID and co.cli_ID = cli.cID and co.co_txn_id = od.txn_id
    WHERE 1 {$where}
    ORDER BY  notcompleted desc, date_order desc, cli.cli_clinic asc, doc.doc_last ASC 
    
   ";


if (isset($_REQUEST['debug'])) {
	var_dump($sql);
	echo '<br>';
}


$rows = $wpdb->get_results($sql);



$query_time = microtime(true) - $start_time;
if (!empty($wpdb->last_error)) {
	echo '<br>';
	echo $wpdb->last_error;
	echo '<br>';
}


$count_rows = count($rows);
$count_type = count($all_types);
$cli_pos = 0;
$has_more = false;


$clinics = array();
foreach ($rows as $row) {
	if (!isset($clinics[$row->cID])) {
		$cli_pos++;
		$clinics[$row->cID] = array(
			'clinic' => $row->cli_clinic,
			'pos' => $cli_pos,
			'uri' => $row->cli_pcr_uri,
			'uti' => $row->cli_pcr_uti,
			'wound' => $row->cli_pcr_wound,
			'covid' => $row->cli_pcr_covid,
			'child' => '',
			'docs' => array()
		);
	}
	if (!isset($clinics[$row->cID]['docs'][$row->doc_ID])) {
		$clinics[$row->cID]['docs'][$row->doc_ID] = $row;
	}
}
unset($rows);

foreach ($clinics as $cID => $arr) {

	if ($arr['pos'] <= $offset * $posts_per_page) {
		unset($clinics[$cID]);
		continue;
	}
	if ($arr['pos'] > ($offset + 1) * $posts_per_page) {
		$has_more = true;
		break;
	}

	foreach ($arr['docs'] as $row) {
		$where_doc_cli = " ba.doc_ID={$row->doc_ID} AND ba.cli_ID={$row->cID} ";
		foreach ($all_types as $btype => $txt) {
			if ($btype == 'tox_oral') {
				$row->{'count_' . $btype}           = $wpdb->get_var("SELECT count(*) FROM ci_batches ba WHERE {$where_doc_cli} AND ba.type_of_specimen='oral'");
				$row->{'count_' . $btype . '_last1'} = $wpdb->get_var("SELECT count(*) FROM ci_batches ba WHERE {$where_doc_cli} AND ba.type_of_specimen='oral' and ba.doc >='{$month1_from}' and ba.doc<='{$month1_to}'");
				$row->{'count_' . $btype . '_last2'} = $wpdb->get_var("SELECT count(*) FROM ci_batches ba WHERE {$where_doc_cli} AND ba.type_of_specimen='oral' and ba.doc >='{$month2_from}' and ba.doc<='{$month2_to}'");
				$row->{'count_' . $btype . '_last3'} = $wpdb->get_var("SELECT count(*) FROM ci_batches ba WHERE {$where_doc_cli} AND ba.type_of_specimen='oral' and ba.doc >='{$month3_from}' and ba.doc<='{$month3_to}'");
			} else if ($btype == 'tox_urine') {
				$row->{'count_' . $btype}           = $wpdb->get_var("SELECT count(*) FROM ci_batches ba WHERE {$where_doc_cli} AND ba.type_of_specimen='urine'");
				$row->{'count_' . $btype . '_last1'} = $wpdb->get_var("SELECT count(*) FROM ci_batches ba WHERE {$where_doc_cli} AND ba.type_of_specimen='urine' and ba.doc >='{$month1_from}' and ba.doc<='{$month1_to}'");
				$row->{'count_' . $btype . '_last2'} = $wpdb->get_var("SELECT count(*) FROM ci_batches ba WHERE {$where_doc_cli} AND ba.type_of_specimen='urine' and ba.doc >='{$month2_from}' and ba.doc<='{$month2_to}'");
				$row->{'count_' . $btype . '_last3'} = $wpdb->get_var("SELECT count(*) FROM ci_batches ba WHERE {$where_doc_cli} AND ba.type_of_specimen='urine' and ba.doc >='{$month3_from}' and ba.doc<='{$month3_to}'");
			} else if (stripos($btype, 'pcr_') !== false) {
				list($pptype2, $panel) = explode('_', $btype);
				$btable = tr_pdd_get_table_by_btype('pcr');
				$panel_id = $all_panels_ids[$panel];
				$where_panel = " and FIND_IN_SET( 'GROUP_{$panel_id}', ba.panel_tests_requested )";
				$row->{'count_' . $btype} = $wpdb->get_var("SELECT count(*) FROM {$btable} ba WHERE {$where_doc_cli} {$where_panel}");
				$row->{'count_' . $btype . '_last1'} = $wpdb->get_var("SELECT count(*) FROM {$btable} ba WHERE {$where_doc_cli} {$where_panel} and ba.doc >='{$month1_from}' and ba.doc<='{$month1_to}'");
				$row->{'count_' . $btype . '_last2'} = $wpdb->get_var("SELECT count(*) FROM {$btable} ba WHERE {$where_doc_cli} {$where_panel} and ba.doc >='{$month2_from}' and ba.doc<='{$month2_to}'");
				$row->{'count_' . $btype . '_last3'} = $wpdb->get_var("SELECT count(*) FROM {$btable} ba WHERE {$where_doc_cli} {$where_panel} and ba.doc >='{$month3_from}' and ba.doc<='{$month3_to}'");
			} else {
				$btable = tr_pdd_get_table_by_btype($btype);
				$row->{'count_' . $btype} = $wpdb->get_var("SELECT count(*) FROM {$btable} ba WHERE {$where_doc_cli}");
				$row->{'count_' . $btype . '_last1'} = $wpdb->get_var("SELECT count(*) FROM {$btable} ba WHERE {$where_doc_cli} and ba.doc >='{$month1_from}' and ba.doc<='{$month1_to}'");
				$row->{'count_' . $btype . '_last2'} = $wpdb->get_var("SELECT count(*) FROM {$btable} ba WHERE {$where_doc_cli} and ba.doc >='{$month2_from}' and ba.doc<='{$month2_to}'");
				$row->{'count_' . $btype . '_last3'} = $wpdb->get_var("SELECT count(*) FROM {$btable} ba WHERE {$where_doc_cli} and ba.doc >='{$month3_from}' and ba.doc<='{$month3_to}'");
			}

			$clinics[$cID][$btype] += $row->{'count_' . $btype};
			$clinics[$cID][$btype . '_last1'] += $row->{'count_' . $btype . '_last1'};
			$clinics[$cID][$btype . '_last2'] += $row->{'count_' . $btype . '_last2'};
			$clinics[$cID][$btype . '_last3'] += $row->{'count_' . $btype . '_last3'};

			$clinics[$cID]['red_' . $btype] = false;
		}


		$d_order = strtotime($row->date_order);
		if ($d_order > $date_order && empty($clinics[$cID]['date_order'])) {
			$clinics[$cID]['date_order'] = $d_order;
		}

		if ($enable_pcr_panel) {
			foreach ($pcr_panels as $pp) {
				$btype = 'pcr_' . $pp;
				if ($clinics[$cID][$pp] > 1) {
					$half = floor($clinics[$cID][$pp] / 2);
					if ($clinics[$cID][$btype . '_last1'] >= $half) {
						$clinics[$cID]['red_' . $btype] = true;
					}
				}
			}
		}


		ob_start();
?>

		<tr class="childp">
			<td style="width:80px"></td>
			<td style="width: 15%;">
				<a class="thickbox"
					href="?tr_action=ci_print_doctor_custom&id=<?php echo $row->doc_ID ?>&TB_iframe=true&width=800&height=550"><?php echo $row->doc_last . ', ' . $row->doc_first ?></a>
				<?php
				if ($row->has_profile) {
					echo '<i class="dashicons dashicons-yes"></i>';
				} else {
					echo '<i class="dashicons dashicons-dismiss"></i>';
				}
				2
				?>
			</td>
			<td style="width:15%"><?php echo $row->doc_phone ?></td>
			<?php
			foreach ($all_types as $btype => $txt) {
				$last_btype = $month1 . ': ' . $row->{'count_' . $btype . '_last1'} . "\n";
				$last_btype .= $month2 . ': ' . $row->{'count_' . $btype . '_last2'} . "\n";
				$last_btype .= $month3 . ': ' . $row->{'count_' . $btype . '_last3'};


				echo '<td style="width:6%" title="' . $last_btype . '">' . $row->{'count_' . $btype} . '</td>';
			}
			?>
			<td style="width:12%"><?php echo $row->rep_last . ', ' . $row->rep_first ?></td>
			<td style="width:12%"><?php echo $row->rep_phone ?></td>
			<td>
				<a class="doc_order button" data-doc_id="<?php echo $row->doc_ID ?>"
					data-cli_id="<?php echo $row->cID ?>">Orders</a>
			</td>
		</tr>
		<tr class="childp child_orders order_<?php echo $row->cID . '_' . $row->doc_ID ?>" style="display: none;">
			<td colspan="<?php echo 8 + $count_type ?>" class="padding50"></td>
		</tr>
	<?php
		$html = ob_get_clean();

		$clinics[$cID]['child'] .= $html;
		$clinics[$cID]['doc_ids'][] = $row->doc_ID;
	}
}


$top_table_html = '';
$table_html = '';
foreach ($clinics as $cID => $data) {
	if (empty($data['child'])) continue;

	$parent_id = 'c_' . $cID . '_' . $lab_id;

	$doc_ids = implode(',', $data['doc_ids']);
	$where_od = " where cli_ID = {$cID} and doc_ID in ({$doc_ids}) ";
	$count_order = $wpdb->get_var("select count(distinct(txn_id)) from wp_wp_eStore_customer_tbl {$where_od} ");
	$count_order_completed = $wpdb->get_var("select count(*) from ci_customer_orders {$where_od} and ship_date is not null ");
	$has_red = false;
	ob_start();
	?>
	<tr class="parent_c" data-p="<?php echo $parent_id ?>" data-order="<?php echo $count_order ?>">
		<td style="width: 30px;"><i></i></td>
		<td style="width:40px"><?php echo $data['pos'] ?></td>
		<td colspan="2" style="width:30%"><?php echo $data['clinic'] ?></td>
		<?php
		foreach ($all_types as $btype => $txt) {
			$last_btype = $month1 . ': ' . $data[$btype . '_last1'] . "\n";
			$last_btype .= $month2 . ': ' . $data[$btype . '_last2'] . "\n";
			$last_btype .= $month3 . ': ' . $data[$btype . '_last3'];

			$cclass = '';
			if ($clinics[$cID]['red_' . $btype]) {
				$cclass .= ' is_red ';
				$has_red = true;
			}

			echo '<td style="width:6%" class="' . $cclass . '" title="' . $last_btype . '">' . $data[$btype] . '</td>';
		}
		?>
		<td style="width:12%"></td>
		<td style="width:12%"></td>
		<td title="<?php echo date('Y-m-d', $data['date_order']) . '|' . $count_order_completed . '/' . $count_order ?>">
			<?php
			if ($count_order > 0) {
				$color = 'green';
				if ($count_order > $count_order_completed) {
					$color = 'yellow';
				}
				echo '<span class="dashicons dashicons-yes ' . $color . '"></span>';
			}

			?>
		</td>
	</tr>
	<tr class="child_c p_<?php echo $parent_id ?>" data-labid="<?php echo $lab_id ?>">
		<td colspan="<?php echo 7 + $count_type ?>" class="nopadding">
			<table class="report_tbl">
				<?php echo $data['child'] ?>
			</table>
		</td>
	</tr>
<?php
	$cli_html = ob_get_clean();
	if ($has_red) {
		$top_table_html .= $cli_html;
	} else {
		$table_html .= $cli_html;
	}
}

echo '<table class="report_tbl">';
echo $top_table_html;
echo $table_html;
echo '</table>';

if ($has_more) {
	echo '<div class="center loadmorediv"><a class="button loadmore" data-off="' . ($offset + 1) . '" data-lab_id="' . $lab_id . '">Load More</a></div>';
}

$load_time = microtime(true) - $start_time;
echo "\n" . '<div style="display:none">query:' . $query_time . '</div>';
echo "\n" . '<div style="display:none">total:' . $load_time . '</div>';
