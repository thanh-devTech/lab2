<?php
global $wpdb, $blog_id, $current_role;
$user_id = get_current_user_id();
$types = tr_pdd_get_batch_types();
unset($types[0]);




$lab_id = $_REQUEST['lab_id'];
$date_order = isset($date_order) ? $date_order : '';
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

$where = "";
$where_date_order = "";

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
		$where_date_order .= " and od3.date <= '{$date_to} 23:59:59' ";
	}
}


$sql_types = "
(SELECT count(*) FROM ci_batches ba WHERE ba.doc_ID=doc.doc_ID AND ba.cli_ID=cli.cID AND ba.type_of_specimen='oral')AS count_oral,
    (SELECT count(*) FROM ci_batches ba WHERE ba.doc_ID=doc.doc_ID AND ba.cli_ID=cli.cID AND ba.type_of_specimen='oral' and ba.doc >='{$month1_from}' and ba.doc<='{$month1_to}')AS count_oral_last1,
    (SELECT count(*) FROM ci_batches ba WHERE ba.doc_ID=doc.doc_ID AND ba.cli_ID=cli.cID AND ba.type_of_specimen='oral' and ba.doc >='{$month2_from}' and ba.doc<='{$month2_to}')AS count_oral_last2,
    (SELECT count(*) FROM ci_batches ba WHERE ba.doc_ID=doc.doc_ID AND ba.cli_ID=cli.cID AND ba.type_of_specimen='oral' and ba.doc >='{$month3_from}' and ba.doc<='{$month3_to}')AS count_oral_last3,
    
    (SELECT count(*) FROM ci_batches ba WHERE ba.doc_ID=doc.doc_ID AND ba.cli_ID=cli.cID AND ba.type_of_specimen='urine')AS count_urine,
    (SELECT count(*) FROM ci_batches ba WHERE ba.doc_ID=doc.doc_ID AND ba.cli_ID=cli.cID AND ba.type_of_specimen='urine' and ba.doc >='{$month1_from}' and ba.doc<='{$month1_to}')AS count_urine_last1,
    (SELECT count(*) FROM ci_batches ba WHERE ba.doc_ID=doc.doc_ID AND ba.cli_ID=cli.cID AND ba.type_of_specimen='urine' and ba.doc >='{$month2_from}' and ba.doc<='{$month2_to}')AS count_urine_last2,
    (SELECT count(*) FROM ci_batches ba WHERE ba.doc_ID=doc.doc_ID AND ba.cli_ID=cli.cID AND ba.type_of_specimen='urine' and ba.doc >='{$month3_from}' and ba.doc<='{$month3_to}')AS count_urine_last3,
   
   ";
foreach ($types as $btype) {
	$btable = tr_pdd_get_table_by_btype($btype);
	$sql_types .= "(SELECT count(*) FROM {$btable} ba WHERE ba.doc_ID=doc.doc_ID AND ba.cli_ID=cli.cID)AS count_{$btype},\n";
	$sql_types .= "(SELECT count(*) FROM {$btable} ba WHERE ba.doc_ID=doc.doc_ID AND ba.cli_ID=cli.cID and ba.doc >='{$month1_from}' and ba.doc<='{$month1_to}')AS count_{$btype}_last1,\n";
	$sql_types .= "(SELECT count(*) FROM {$btable} ba WHERE ba.doc_ID=doc.doc_ID AND ba.cli_ID=cli.cID and ba.doc >='{$month2_from}' and ba.doc<='{$month2_to}')AS count_{$btype}_last2,\n";
	$sql_types .= "(SELECT count(*) FROM {$btable} ba WHERE ba.doc_ID=doc.doc_ID AND ba.cli_ID=cli.cID and ba.doc >='{$month3_from}' and ba.doc<='{$month3_to}')AS count_{$btype}_last3,\n";
}

$sql = "
    select doc.doc_ID,
            doc.doc_first,
            doc.doc_last,
            doc.doc_phone,
            doc.doc_last_order as date_order,

    sum(if(od.id>0,1,0)) AS count_order,
    sum(if(co.ship_date is not null , 1, 0)) AS count_completed,
    cli.cID,
    cli.cli_clinic,
    lr.rep_first,
    lr.rep_last,
    lr.rep_phone,
    cus.cus_ID AS has_profile
    from ci_doc doc
    JOIN ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
    JOIN ci_clinic  cli ON cd.cli_ID = cli.cID
    left JOIN ci_lab_reps lr ON lr.rep_ID = cli.`cli_rep-ID`
    LEFT JOIN ci_custom_providers cus ON cus.doc_ID = doc.doc_ID
    
    left join (select id,doc_ID,cli_ID,viewed,date,txn_id 
                        from wp_wp_eStore_customer_tbl group by txn_id) od 
                        ON od.doc_ID = doc.doc_ID and od.cli_ID=cli.cID 

    left JOIN ci_customer_orders co ON co.co_txn_id = od.txn_id 

    WHERE cli.`cli_lab-ID` = {$lab_id} 
    {$where}
    GROUP BY doc.doc_ID, cli.cID
    ORDER BY doc.doc_last_order desc, od.id desc ,cli.cli_clinic asc, doc.doc_last ASC 
    limit 100
   ";


if (isset($_REQUEST['debug'])) {
	var_dump($sql);
	echo '<br>';
}

$rows = $wpdb->get_results($sql);

if (!empty($wpdb->last_error)) {
	echo '<br>';
	echo $wpdb->last_error;
	echo '<br>';
}

echo '<table class="report_tbl">';
$count_rows = count($rows);
$parents = array();
$count_type = count($types);
foreach ($rows as $i => $row) {

	if (!isset($parents[$row->cID])) {
		$parents[$row->cID] = array('child' => '');
	}

	$parents[$row->cID]['oral'] += $row->count_oral;
	$parents[$row->cID]['oral_last1'] += $row->count_oral_last1;
	$parents[$row->cID]['oral_last2'] += $row->count_oral_last2;
	$parents[$row->cID]['oral_last3'] += $row->count_oral_last3;

	$parents[$row->cID]['urine'] += $row->count_urine;
	$parents[$row->cID]['urine_last1'] += $row->count_urine_last1;
	$parents[$row->cID]['urine_last2'] += $row->count_urine_last2;
	$parents[$row->cID]['urine_last3'] += $row->count_urine_last3;

	foreach ($types as $btype) {
		$parents[$row->cID][$btype] += $row->{'count_' . $btype};

		$parents[$row->cID][$btype . '_last1'] += $row->{'count_' . $btype . '_last1'};
		$parents[$row->cID][$btype . '_last2'] += $row->{'count_' . $btype . '_last2'};
		$parents[$row->cID][$btype . '_last3'] += $row->{'count_' . $btype . '_last3'};
	}

	$parents[$row->cID]['order'] += $row->count_order;
	$parents[$row->cID]['completed'] += $row->count_completed;


	$d_order = strtotime($row->date_order);
	if ($d_order > $date_order && empty($parents[$row->cID]['date_order'])) {
		$parents[$row->cID]['date_order'] = $d_order;
	}

	$oral_last = $month1 . ': ' . $row->count_oral_last1 . "\n";
	$oral_last .= $month2 . ': ' . $row->count_oral_last2 . "\n";
	$oral_last .= $month3 . ': ' . $row->count_oral_last3;

	$urine_last = $month1 . ': ' . $row->count_urine_last1 . "\n";
	$urine_last .= $month2 . ': ' . $row->count_urine_last2 . "\n";
	$urine_last .= $month3 . ': ' . $row->count_urine_last3;

	ob_start();
?>

	<tr class="childp">
		<td style="width:40px"></td>
		<td style="width: 15%;">
			<a class="thickbox"
				href="?tr_action=ci_print_doctor_custom&id=<?php echo $row->doc_ID ?>&TB_iframe=true&width=800&height=550"><?php echo $row->doc_last . ', ' . $row->doc_first ?></a>
			<?php
			if ($row->has_profile) {
				echo '<i class="dashicons dashicons-yes"></i>';
			} else {
				echo '<i class="dashicons dashicons-dismiss"></i>';
			}
			?>
		</td>
		<td style="width:15%"><?php echo $row->doc_phone ?></td>
		<td style="width:7%" title="<?php echo $oral_last ?>"><?php echo $row->count_oral ?></td>
		<td style="width:7%" title="<?php echo $urine_last ?>"><?php echo $row->count_urine ?></td>
		<?php
		foreach ($types as $btype) {
			$last_btype = $month1 . ': ' . $row->{'count_' . $btype . '_last1'} . "\n";
			$last_btype .= $month2 . ': ' . $row->{'count_' . $btype . '_last2'} . "\n";
			$last_btype .= $month3 . ': ' . $row->{'count_' . $btype . '_last3'};

			echo '<td style="width:6%" title="' . $last_btype . '">' . $row->{'count_' . $btype} . '</td>';
		}
		?>
		<td style="width:12%"><?php echo $row->rep_last . ', ' . $row->rep_first ?></td>
		<td style="width:12%"><?php echo $row->rep_phone ?></td>
		<td title="<?php echo $row->count_order ?>">
			<?php if ($row->count_order > 0): ?>
				<a class="doc_order button" data-doc_id="<?php echo $row->doc_ID ?>"
					data-cli_id="<?php echo $row->cID ?>">Orders</a>
			<?php endif; ?>
		</td>
	</tr>
	<tr class="childp child_orders order_<?php echo $row->cID . '_' . $row->doc_ID ?>" style="display: none;">
		<td colspan="<?php echo 8 + $count_type ?>" class="padding50"></td>
	</tr>
<?php
	$html = ob_get_clean();

	$parents[$row->cID]['child'] .= $html;
	$parents[$row->cID]['clinic'] = $row->cli_clinic;
}

foreach ($parents as $cID => $data) {
	$parent_id = 'c_' . $cID . '_' . $lab_id;
	$oral_last = $month1 . ': ' . $data['oral_last1'] . "\n";
	$oral_last .= $month2 . ': ' . $data['oral_last2'] . "\n";
	$oral_last .= $month3 . ': ' . $data['oral_last3'];

	$urine_last = $month1 . ': ' . $data['urine_last1'] . "\n";
	$urine_last .= $month2 . ': ' . $data['urine_last2'] . "\n";
	$urine_last .= $month3 . ': ' . $data['urine_last3'];
?>
	<tr class="parent_c" data-p="<?php echo $parent_id ?>" data-order="<?php echo $data['order'] ?>">
		<td style="width: 40px;"><i></i></td>
		<td colspan="2" style="width:30%"><?php echo $data['clinic'] ?></td>
		<td style="width:7%" title="<?php echo $oral_last ?>"><?php echo $data['oral'] ?></td>
		<td style="width:7%" title="<?php echo $urine_last ?>"><?php echo $data['urine'] ?></td>
		<?php
		foreach ($types as $btype) {
			$last_btype = $month1 . ': ' . $data[$btype . '_last1'] . "\n";
			$last_btype .= $month2 . ': ' . $data[$btype . '_last2'] . "\n";
			$last_btype .= $month3 . ': ' . $data[$btype . '_last3'];

			echo '<td style="width:6%" title="' . $last_btype . '">' . $data[$btype] . '</td>';
		}
		?>
		<td style="width:12%"></td>
		<td style="width:12%"></td>
		<td title="<?php echo date('Y-m-d', $data['date_order']) ?>">
			<?php
			if ($data['order'] > 0) {

				$color = 'green';
				if ($data['order'] > $data['completed']) {
					$color = 'yellow';
				}
				echo '<span class="dashicons dashicons-yes ' . $color . '"></span>';
			}
			?>
		</td>
	</tr>
	<tr class="child_c p_<?php echo $parent_id ?>" data-labid="<?php echo $lab_id ?>">
		<td colspan="<?php echo 8 + $count_type ?>" class="nopadding">
			<table class="report_tbl">
				<?php echo $data['child'] ?>
			</table>
		</td>
	</tr>
<?php

}

echo '</table>';
