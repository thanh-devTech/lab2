<?php

global $wpdb;
global $current_role;

$table_batch = 'ci_batches_study';
$can_delete = true;




if ($act == 'pdelete') {
	$id = $_POST['idd'];
	$wpdb->delete('ci_batches_study', array('mid' => $id));
	Tr_Session_Class::add("Deleted Successfully!");
}




$where = ' is_saved = 0 ';


if (!empty($_REQUEST['s'])) {
	$search = stripslashes($_REQUEST['s']);

	if (strpos($_REQUEST['s'], ',') > 0) {
		list($firstname, $lastname) = explode(',', $_REQUEST['s']);
		$firstname = trim($firstname);
		$lastname = trim($lastname);
		$explodeString = "%{$firstname}%{$lastname}%";
		$where .= $wpdb->prepare(" and (ba.patient_name like %s)", $explodeString);
	} else {
		$where .= $wpdb->prepare(" and (
                                            ba.patient_name like %s 
                                            or ba.assen like %s
                                            or ba.oldreq = %s
                                            )", '%' . $search . '%', $search . '%', $search);
	}
}

if (!empty($_REQUEST['fromdate'])) {
	$where .= " and ba.doc >= '{$_REQUEST['fromdate']}' ";
}

if (!empty($_REQUEST['todate'])) {
	$where .= " and ba.doc <= '{$_REQUEST['todate']}' ";
}



$row_per_page = 100;
$pagination = new Tr_Pagination_class($row_per_page);
$pagination->setTotal($wpdb->get_var("select count(*) as c from {$table_batch} ba where  {$where}"));
$offset = $pagination->offset();

$sql = "select
        ba.*,
        st.project_name
        from {$table_batch} ba
        left join ci_study st ON st.ID = ba.project_ID
        where {$where} order by doc desc limit {$offset},{$row_per_page}";

if (isset($_REQUEST['debug'])) {
	echo $sql;
}
$results = $wpdb->get_results($sql);

?>
<h2>Completed Req Forms</h2>
<?php Tr_Session_Class::show() ?>

<div class="tablenav">
	<form method="get" style="float:left;margin-right: 10px;">
		<input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
		<input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
		<label>Name:</label>
		<input type="search" name="s" value="<?php echo stripslashes(@$_REQUEST['s']) ?>" />
		<input type="search" class="hasdate" name="fromdate" value="<?php echo $_REQUEST['fromdate'] ?>" placeholder="From Date" />
		<input type="search" class="hasdate" name="todate" value="<?php echo $_REQUEST['todate'] ?>" placeholder="To Date" />
		<input type="submit" class="button bsty6" value="Search" />
		<input type="button" class="button export_list" onclick="location='<?php Tr_Base_Class_V6::link(array('trajax' => 1, 'act' => 'export_list')) ?>'" value="Export" />
	</form>
	<?php if ($pagination) echo $pagination->pagination() ?>
</div>
<div class="wraptbl">
	<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
		<thead>
			<tr>
				<th style="width: 50px">#</th>
				<th>Accession Number</th>
				<th>Study</th>
				<th style="width:105px">Date</th>
				<th style="width:220px;text-align: right">Actions</th>
			</tr>
		</thead>
		<tbody>
			<?php
			$i = 1;
			foreach ($results as $row) {

			?>
				<tr data-mid="<?php echo $row->mid ?>" data-dob="<?php echo $row->dob ?>" data-cul="<?php echo $row->culture ?>" data-panels_print="<?php echo $printpanels ?>">
					<td><?php echo $i++ ?></td>
					<td><span class="assen"><?php echo $row->assen ?></span></td>
					<td><?php echo $row->project_name ?></td>
					<td><?php echo $row->doc ?></td>
					<td class="cactions">

						<a class="bsty1" href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->mid, 'tab' => '')) ?>">Edit</a>

						<?php if ($can_delete && strtotime($row->doc) >= $edit_time) { ?>
							<a class="pdelete bsty6" data-assen="<?php echo $row->assen ?>" href="#" data-id="<?php echo $row->mid ?>">Delete</a>
						<?php } ?>
					</td>
				</tr>
			<?php } ?>
		</tbody>
	</table>
</div>
<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>