<?php

global $wpdb;

if ($act == 'edittemplate') {

	$id = $_REQUEST['id'];

	if (isset($_POST['columns'])) {
		$templatedata = array();
		foreach ($_POST['columns']['name'] as $i => $name) {
			$name = stripslashes($name);
			$vl = stripslashes($_POST['columns']['data'][$i]);

			$templatedata[] = array($name, $vl);
		}
		$templatedata = json_encode($templatedata);
		$update = array(
			'template' => $templatedata,
			'csvbycondition' => isset($_POST['csvbycondition']) ? 1 : 0
		);
		$wpdb->update('ci_study', $update, array('ID' => $id));
		Tr_Session_Class::add('Saved Successfully!');
	}
	$study = $wpdb->get_row("select * from ci_study where ID = {$id} ");
	$templatedata = json_decode($study->template, true);

	if (empty($templatedata)) {
		$templatedata = array();
		$templatedata[] = array('FF_Sample_ID', '[assen]');
		$templatedata[] = array('Subject_ID', '[patientid]');
		$templatedata[] = array('Sample_Visit', '[timeframe]');
		$templatedata[] = array('Barcode_ID', '[sampleid]');
		$templatedata[] = array('Sample_Draw_Date', '[doc]');
		$templatedata[] = array('Sample_Draw_Time', '[doc-time]');
		$templatedata[] = array('', '');
	}
?>
	<div class="wrap">
		<h2><?php echo $study->project_name ?></h2>
		<?php Tr_Session_Class::show() ?>
		<form method="post">
			<table>
				<tr>
					<td>
						<div class="frow" id="listitems" style="width:<?php echo (count($templatedata) * 190) . 'px' ?>">
							<?php
							foreach ($templatedata as $i => $arr) {
							?>
								<div class="item">
									<input type="text" name="columns[name][]" value="<?php echo $arr[0] ?>" />
									<input type="text" name="columns[data][]" value="<?php echo $arr[1] ?>" />
									<a class="remove_column button">-</a>
								</div>
							<?php
							}
							?>

						</div>
					</td>
					<td style="vertical-align: top;">
						<div style="padding-right:20px">
							<a class="button add_column">+</a>
						</div>
					</td>
				</tr>
			</table>

			<br><br>
			<div class="frow center">
				<label><input type="checkbox" name="csvbycondition" value="1" <?php checked($study->csvbycondition) ?> /> by Condition Rows</label>
			</div>
			<div class="frow" style="text-align: center">

				<input type="submit" class="button-primary" value="Save" />
				<a class="button add_column">+</a>
			</div>
		</form>
	</div>
	<style>
		#listitems .item {
			float: left;
			width: 180px;
			margin-right: 4px;
			text-align: center;
			margin-bottom: 30px;
		}

		#listitems .item input {
			margin-bottom: 4px;
			text-align: center;
		}

		.remove_column {
			margin: 0 auto;
		}
	</style>
	<script>
		jQuery(function($) {
			$('body').on('click', '.add_column', function() {
				var $width = ($('#listitems .item').length + 1) * 190;
				var $item = $('<div class="item">' + $('#listitems .item:first-child').html() + '</div>');
				$('input', $item).val('');
				$('#listitems').append($item).css('width', $width + 'px');
			}).on('click', '.remove_column', function() {
				var $pr = $(this).parents('.item');
				$pr.remove();
			})
		})
	</script>
<?php

} else {

	$where_study = '';
	$row_per_page = 100;
	$pagination = new Tr_Pagination_class($row_per_page);
	$pagination->setTotal($wpdb->get_var("SELECT count(*) as count FROM `ci_study` stu
		where 1  {$where_study}"));
	$offset = $pagination->offset();


	$sql = "SELECT stu.*
            FROM `ci_study` stu
            where 1  {$where_study}
            group by stu.ID
             ORDER BY `project_name` asc limit {$offset},{$row_per_page}";

	if (isset($_REQUEST['debug'])) {
		echo $sql;
	}
	$results = $wpdb->get_results($sql);
?>
	<div class="wrap">
		<?php Tr_Session_Class::show() ?>
		<div class="tablenav">

			<?php if ($pagination) echo $pagination->pagination() ?>
		</div>
		<div class="wraptbl">
			<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
				<thead class="sticky">
					<tr>
						<th style="width: 50px">#</th>
						<th>Project Name</th>
						<th style="width:160px">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ($results as $i => $row) {

					?>
						<tr data-id="<?php echo $row->ID; ?>">
							<td><?php echo $i + 1 ?></td>
							<td><?php echo $row->project_name; ?></td>
							<td>
								<a href="<?php Tr_Base_Class_V6::link(array('act' => 'edittemplate', 'id' => $row->ID)) ?>">Customize</a>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>

	</div>
	<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
	</div>
<?php
}
