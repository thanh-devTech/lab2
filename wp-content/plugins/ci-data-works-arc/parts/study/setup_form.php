<?php
global $wpdb, $current_role, $ci_all_btypes;

include_once(TRCPDD_PATH . 'pages/formdata.php');

$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;

$multirows = array('sites', 'exclusions', 'inclusions');




$can_change_status = true;
$can_add_new = true;
$can_delete = true;
$can_edit = true;

if ($act == 'save') {
	$data = tr_pdd_stripslashes_deep($_POST['form']);

	foreach ($multirows as $k) {
		if (is_array($data[$k])) {
			foreach ($data[$k] as $i => $vl) {
				if (empty($vl)) {
					unset($data[$k][$i]);
				}
			}
			$data[$k] = implode('|', $data[$k]);
		} else {
			$data[$k] = '';
		}
	}

	$data['time_points'] = json_encode($data['time_points']);

	if ($data['ID'] > 0) {
		$rs = $wpdb->update('ci_study', $data, array('ID' => $data['ID']));
		if ($rs !== false) {
			Tr_Session_Class::add('Updated Study Successfully!');
		} else {
			Tr_Session_Class::add_error('Error. Please try again!' . $wpdb->last_error);
		}
		$act = 'edit';
	} else {
		$data['created'] = date_i18n('Y-m-d H:i:s');
		$data['created_by'] = get_current_user_id();
		$rs = $wpdb->insert('ci_study', $data);
		$data['ID'] = $wpdb->insert_id;
		if ($rs !== false) {
			Tr_Session_Class::add('Added new Study Successfully!');
		} else {
			Tr_Session_Class::add_error('Error. Please try again!' . $wpdb->last_error);
		}
	}

	if ($rs === false) {
		Tr_Session_Class::reset();
		$exit_cols = $wpdb->get_col("explain ci_study");
		foreach ($data as $k => $vl) {
			if (!in_array($k, $exit_cols)) {
				$datatype = 'varchar(100)';
				if (strlen($vl) > 100) {
					$datatype = 'varchar(150)';
				}

				if (stripos($k, 'num_') === 0) {
					$datatype = 'int(10)';
				} else if (in_array($k, array('conditions', 'time_points', 'sites'))) {
					$datatype = 'text';
				} else if (stripos($k, 'time') !== false) {
					$datatype = 'datetime';
				} else if (stripos($k, 'date') !== false) {
					$datatype = 'date';
				}
				$wpdb->query("alter table ci_study add column `{$k}` {$datatype};");
			}
		}
		if ($data['ID'] > 0) {
			$rs = $wpdb->update('ci_study', $data, array('ID' => $data['ID']));
			if ($rs !== false) {
				Tr_Session_Class::add('Updated Study Successfully!');
			} else {
				Tr_Session_Class::add_error('Error. Please try again!' . $wpdb->last_error);
			}
			$act = 'edit';
		} else {
			$data['created'] = date_i18n('Y-m-d H:i:s');
			$data['created_by'] = get_current_user_id();
			$rs = $wpdb->insert('ci_study', $data);
			$data['ID'] = $wpdb->insert_id;
			if ($rs !== false) {
				Tr_Session_Class::add('Added new Study Successfully!');
			} else {
				Tr_Session_Class::add_error('Error. Please try again!' . $wpdb->last_error);
			}
		}
	}

	if ($rs !== false && $data['ID'] > 0) {

		$data = array();
	}
} else if ($act == 'ci_change_study_status') {
	$return = array('status' => 'ok');
	$id = $_REQUEST['id'];
	$new_status = $_REQUEST['status'];
	$wpdb->update('ci_study', array('istatus' => $new_status), array('ID' => $id));
	wp_send_json($return);
} else if ($act == 'duplicate') {

	$id = $_REQUEST['id'];
	$row = $wpdb->get_row("select * from ci_study where ID = {$id}", ARRAY_A);
	unset($row['ID']);
	$row['project_name'] .= '-copy';
	$wpdb->insert('ci_study', $row);
	$newid = $wpdb->insert_id;
	wp_redirect(Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $newid, 'trajax' => ''), false));
	exit;
}


if ($act == 'list') {
	if ($_REQUEST['act_save'] == 'delete' && $_GET['id'] > 0) {
		$wpdb->delete('ci_study', array('ID' => $_GET['id']));
		Tr_Session_Class::add('Deleted Req record');
	}

	if (!empty($_REQUEST['s'])) {
		$where_study .= $wpdb->prepare(" and project_name like %s", '%' . $_REQUEST['s'] . '%');
	}

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
		<h2>All Studies
			<?php if ($can_add_new) : ?><a class="add-new-h2" href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '', 'paged' => '')) ?>">Add New</a><?php endif; ?></h2>
		<?php Tr_Session_Class::show() ?>
		<div class="tablenav">
			<form method="get" style="float:left">
				<input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
				<input type="hidden" name="act" value="<?php echo $_GET['act'] ?>" />
				<label>Name</label>
				<input type="text" name="s" value="<?php echo $_REQUEST['s'] ?>" />
				<input type="submit" class="button bsty6" value="Search" />

			</form>
			<?php if ($pagination) echo $pagination->pagination() ?>
		</div>
		<div class="wraptbl">
			<table class="wp-list-table widefat fixed striped posts blue_tbl hover_tbl">
				<thead class="sticky">
					<tr>
						<th style="width: 50px">#</th>
						<th>Project Name</th>
						<th style="width:177px">Phase</th>
						<th style="width:177px">Date</th>
						<?php if ($can_change_status) : ?>
							<th style="width:80px">Status</th>
						<?php endif; ?>
						<th style="width:160px">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php
					$i = 1;
					foreach ($results as $row) {

					?>
						<tr data-id="<?php echo $row->ID; ?>">
							<td><?php echo $row->ID ?></td>
							<td><?php echo $row->project_name; ?></td>
							<td><?php echo $row->phase; ?></td>
							<td><?php echo $row->created; ?></td>
							<?php if ($can_change_status) : ?>
								<td class="stu_status" data-status="<?php echo $row->istatus; ?>"></td>
							<?php endif; ?>
							<td>
								<a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->ID)) ?>">Edit</a>
								- <a href="<?php Tr_Base_Class_V6::link(array('trajax' => 1, 'act' => 'duplicate', 'id' => $row->ID)) ?>">Duplicate</a>
								<?php if ($can_delete) : ?>
									- <a class="delete" data-assen="<?php echo $row->project_name ?>" href="<?php Tr_Base_Class_V6::link(array('act_save' => 'delete', 'id' => $row->ID)) ?>">Delete</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>

	</div>
	<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
	<script>
		jQuery(function($) {

			var $statuses = {
				1: 'Active',
				0: 'Inactive',
				2: 'Completed',
				3: 'Closed'
			};
			$('.stu_status').each(function() {
				var $html = '<select class="njs_select2">';
				for (k in $statuses) {
					$html += '<option value="' + k + '" ' + (k == $(this).data('status') ? 'selected' : '') + '>' + $statuses[k] + '</option>';
				}
				$html += '</select>';
				$(this).html($html);
				if ($(this).data('status') == '0') {
					$(this).parents('tr').addClass('row_inactive');
				}

			});
			$('.stu_status select').change(function() {
				var $this = $(this);
				var $pr = $(this).parent();
				$pr.find('.sloading').remove();
				$pr.append('<div class="sloading"></div>');
				var $id = $pr.parents('tr').data('id');
				var $vl = $(this).val();
				$.ajax({
					type: 'post',
					dataType: 'json',
					data: {
						trajax: 1,
						act: 'ci_change_study_status',
						id: $id,
						status: $vl
					},
					success: function(rs) {
						$pr.find('.sloading').remove();
						if (rs.status == 'ok') {
							if ($vl == '0') {
								$pr.parents('tr').addClass('row_inactive');
							} else {
								$pr.parents('tr').removeClass('row_inactive');
							}
						} else {
							$this.change();
						}
					},
					error: function() {
						$this.change();
					}
				})
			});

			$('a.delete').click(function() {
				if (confirm('Are you sure you want to delete #' + $(this).data('assen') + '?') == false) return false;
			})
		})
	</script>
<?php
	return;
} else {
	if ($act == 'edit' && $id > 0) {
		$data = $wpdb->get_row("select * from ci_study where ID = {$id}", ARRAY_A);

		foreach ($multirows as $k) {

			$data[$k] = !empty($data[$k]) ? explode('|', $data[$k]) : array();
		}
		$data['time_points'] = json_decode($data['time_points'], true);
	} else {
		$data = array();
		$id = 0;
	}
	foreach ($data as $k => $vl) {
		if ($vl === '0000-00-00') {
			$data[$k] = '';
		}
	}
	$sites = $wpdb->get_results('select * from ci_study_sites order by `iorder` asc');
	$exclusions = $wpdb->get_col('select `desc` from ci_study_exclusion order by iorder asc');
	$inclusions = $wpdb->get_col('select `desc` from ci_study_inclusion order by iorder asc');

	$tests = $wpdb->get_results("select *
	from ci_study_tests te
	order by iorder asc ");
	if (isset($_GET['debug'])) {
		var_dump($data);
	}
?>
	<div class="wrap">
		<h2>Study Setup Form
			<a class="add-new-h2 right" href="<?php echo Tr_Base_Class_V6::link(array('act' => 'list', 'id' => '')) ?>">Saved Forms</a>
		</h2>
		<?php Tr_Session_Class::show() ?>
		<form method="post" class="need_validate main_form" enctype="multipart/form-data">

			<div class="frow">
				<h4>Project Details</h4>
			</div>
			<div class="frow">
				<div class="col2">
					<label>Project Name</label>
					<input type="text" name="form[project_name]" value="<?php echo $data['project_name'] ?>" required />
				</div>
				<div class="col4">
					<label>4 char name</label>
					<input type="text" name="form[short_name]" value="<?php echo $data['short_name'] ?>" required />
				</div>
				<div class="col4">
					<label>Phase Of Study</label>
					<select name="form[phase]">
						<?php
						foreach (array('NA', '0', '1', '2', '3', '4') as $k) {
							$selected = $data['phase'] === $k ? 'selected' : '';
							if ($data['phase'] === '' && $k === 'NA') {
								$selected = 'selected';
							}
							echo '<option value="' . $k . '" ' . $selected . '>' . $k . '</option>';
						}
						?>
					</select>
				</div>
			</div>
			<div class="frow">
				<div class="col3">
					<label>Company Name</label>
					<input type="text" name="form[company]" id="company" value="<?php echo $data['company'] ?>" class="changeprojectname" />
				</div>
				<div class="col3">
					<label>Drug</label>
					<input type="text" name="form[drug]" id="drug" value="<?php echo $data['drug'] ?>" class="changeprojectname" />
				</div>
				<div class="col3">
					<label>Contact Number</label>
					<input type="text" name="form[contact_number]" id="contact_number" value="<?php echo $data['contact_number'] ?>" class="changeprojectname" />
				</div>
			</div>
			<div class="frow">
				<div class="col2">
					<label>Project Type</label>
					<select name="form[project_type]" id="project_type" class="changeprojectname">
						<?php
						echo '<option value=""></option>';
						foreach (array('CT', 'PCV', 'PMV', 'OPT', 'DD', 'GC', 'Grant', 'RD', 'U') as $k) {
							$selected = $data['project_type'] === $k ? 'selected' : '';
							echo '<option value="' . $k . '" ' . $selected . '>' . $k . '</option>';
						}
						?>
					</select>
				</div>
				<div class="col2">
					<label class="change_by" data-by="project_type" data-gc="Grant Collaboration">Customer Name</label>
					<input type="text" name="form[customer_name]" value="<?php echo $data['customer_name'] ?>" />
				</div>
			</div>

			<div class="frow">
				<h4>Specimen Required </h4>
			</div>

			<div class="frow">
				<div class="col3">
					<label># of ML Citrate</label>
					<input type="number" id="citrate" name="form[num_ml_citrate]" value="<?php echo empty($data['num_ml_citrate']) ? 0 : $data['num_ml_citrate'] ?>" required />
				</div>
				<div class="col3">
					<label># of ML EDTA</label>
					<input type="number" id="edta" name="form[num_ml_edta]" value="<?php echo empty($data['num_ml_edta']) ? 0 : $data['num_ml_edta'] ?>" required />
				</div>
				<div class="col3">
					<label># of SST Tube</label>
					<input type="number" id="edta" name="form[num_sst_tube]" value="<?php echo empty($data['num_sst_tube']) ? 0 : $data['num_sst_tube'] ?>" required />
				</div>
			</div>

			<div class="frow">
				<div class="col3">
					<label>Max Slides</label>
					<input type="number" id="maxslide" name="form[max_slides]" value="<?php echo isset($data['max_slides']) ? $data['max_slides'] : '0' ?>" min="0" max="100" required />
				</div>
				<div class="col3">
					<label>Frozen Blood</label>
					<input type="number" id="frozenblood" name="form[frozen_blood]" value="<?php echo isset($data['frozen_blood']) ? $data['frozen_blood'] : '0' ?>" min="0" max="100" required />
				</div>
				<div class="col3">
					<label>Freezer Code</label>
					<input type="text" id="frozenblood" name="form[freezer_code]" value="<?php echo isset($data['freezer_code']) ? $data['freezer_code'] : '' ?>" />
				</div>
			</div>

			<div class="frow">
				<div class="col2">
					<h4># of Sites</h4>
					<select name="form[num_sites]" id="num_sites">
						<?php
						for ($i = 0; $i <= 20; $i++) {
							$selected = $data['num_sites'] == $i ? 'selected' : '';
							echo '<option value="' . $i . '" ' . $selected . '>' . $i . '</option>';
						}
						?>
					</select>
				</div>
				<div class="col2">
					<h4>Sites</h4>
					<select multiple name="form[sites][]" id="sites" class="sites_select2">
						<?php
						foreach ($sites as $site) {
							$selected = in_array($site->ID, $data['sites']) ? 'selected' : '';
							echo '<option value="' . $site->ID . '" ' . $selected . '>' . $site->desc . '</option>';
						}
						?>
					</select>
				</div>
			</div>
			<div class="frow">
				<div class="col3">
					<h4>Target Number Of Patients</h4>
					<input type="text" name="form[number_patients]" value="<?php echo $data['number_patients'] ?>" />
				</div>
				<div class="col3">
					<h4>Exclusion</h4>
					<select name="form[exclusions][]" multiple class="js_select2 ">
						<?php
						foreach ($exclusions as $exclusion) {
							$selected = in_array($exclusion, $data['exclusions']) ? 'selected' : '';
							echo '<option value="' . $exclusion . '" ' . $selected . '>' . $exclusion . '</option>';
						}
						?>
					</select>
				</div>
				<div class="col3">
					<h4>Inclusion</h4>
					<select name="form[inclusions][]" multiple class="js_select2 editable">
						<?php
						foreach ($data['inclusions'] as $inmore) {
							if (!in_array($inmore, $inclusions)) {
								$inclusions[] = $inmore;
							}
						}
						foreach ($inclusions as $inclusion) {
							$selected = in_array($inclusion, $data['inclusions']) ? 'selected' : '';
							echo '<option value="' . $inclusion . '" ' . $selected . '>' . $inclusion . '</option>';
						}

						?>
					</select>
				</div>
			</div>

			<div class="frow">
				<div class="col2">
					<label>Start Date</label>
					<input type="text" class="hasdate" name="form[start_date]" value="<?php echo $data['start_date'] ?>" />
				</div>
				<div class="col2">
					<label>Expected Completion Date</label>
					<input type="text" class="hasdate" name="form[expected_date]" value="<?php echo $data['expected_date'] ?>" />
				</div>
			</div>

			<div class="frow">
				<h4>Experimental Conditions</h4>
				<ol id="time_points" class="multirows showlistnum">
					<?php
					$max_j = isset($data['time_points']) && count($data['time_points']) > 0 ? count($data['time_points']) : ($act == 'print' && $id == 0 ? 2 : 1);
					for ($j = 0; $j < $max_j; $j++) {
						$timename = 'form[time_points][' . $j . ']';
						$timeitem = isset($data['time_points'][$j]) ? $data['time_points'][$j] : array();
					?>
						<li class="frow blabel pcorow <?php echo ($j == 0) ? 'default' : 'secondrow' ?>">
							<label>Time Point Name</label>
							<div class="withremove">
								<input type="text" name="<?php echo $timename . '[name]' ?>" value="<?php echo isset($timeitem['name']) ? $timeitem['name'] : '' ?>" />
								<a class="remove_multi_row button">-</a>
							</div>
							<div class="multirowschildw">
								<div class="frow">
									<div class="col3">Conditions</div>
									<div class="col8">Sample Req</div>
									<div class="col25">Tests</div>
								</div>
								<ol id="conditions_<?php echo $j ?>" class="multirows showlistnum">
									<?php
									$max_j2 = isset($timeitem['conditions']) && count($timeitem['conditions']) > 0 ? count($timeitem['conditions']) : ($act == 'print' && $id == 0 ? 2 : 1);
									for ($j2 = 0; $j2 < $max_j2; $j2++) {
										$conditionname = $timename . '[conditions][' . $j2 . ']';
										$conditionitem = isset($timeitem['conditions'][$j2]) ? $timeitem['conditions'][$j2] : array();
										$conditionitem['tests'] = is_array($conditionitem['tests']) ? $conditionitem['tests'] : array();
									?>
										<li class="frow blabel pcorow <?php echo ($j2 == 0) ? 'default' : 'secondrow' ?>">
											<div class="col3">
												<input type="text" name="<?php echo $conditionname . '[name]' ?>" value="<?php echo isset($conditionitem['name']) ? $conditionitem['name'] : '' ?>" />
											</div>
											<div class="col8">
												<input type="checkbox" name="<?php echo $conditionname . '[req]' ?>" value="1" <?php checked(isset($conditionitem['req']) && $conditionitem['req']) ?> />
											</div>
											<div class="col25 withremove">
												<select class="js_select2" multiple name="<?php echo $conditionname . '[tests][]' ?>">
													<?php
													foreach ($tests as $t) {
														$selected = in_array($t->ID, $conditionitem['tests']) ? 'selected' : '';
														echo '<option value="' . $t->ID . '" ' . $selected . '>' . $t->abrev . '</option>';
													}
													?>
												</select>
												<a class="remove_multi_row button">-</a>
											</div>
										</li>
									<?php } ?>
								</ol>

								<a class="button add_multi_row" data-key="conditions" data-id="<?php echo $j2 ?>">+</a>

							</div>
						</li>
					<?php } ?>
				</ol>
				<a class="button add_multi_row" data-key="time_points" data-id="<?php echo $j ?>">+</a>

			</div>
			<?php /*

			<div class="frow">
				<h4>Tests</h4>
			</div>
			<div class="frow">
				<?php

				foreach ($tests as $test) {
				?>
					<div class="col4">
						<label><input type="checkbox" name="tests[]" value="<?php echo $test->te_ID ?>" <?php checked($test->has_test > 0) ?> />
							<?php echo $test->te_name ?> </label>
					</div>
				<?php
				}
				?>
			</div>
			*/ ?>

			<div class="frow">
				<h4>&nbsp;</h4>
			</div>

			<div class="frow">
				<input type="submit" class="button-primary" value="Submit">
				<input type="hidden" name="form[ID]" value="<?php echo @$data['ID'] ?>">
				<input type="hidden" name="act" value="save" />
			</div>


		</form>
	</div>
	<style>
		input[readonly] {
			background: #eee !important;
		}
	</style>
	<script>
		jQuery(function($) {
			$('.change_by').each(function() {
				var $label = $(this);
				var $by = $(this).data('by');
				var $dfault = $(this).html();
				$('select#' + $by).on('change', function() {
					var $bvl = $(this).val().toLowerCase();
					if ($label.data($bvl)) {
						$label.html($label.data($bvl));
					} else {
						$label.html($dfault);
					}
				}).change();
			})
			<?php /*
			$('body').on('change', '#num_sites', function() {
				var $num = $(this).val();
				var $data = $('#sites').data('rows');
				$html='';
				for($i = 0;$i < $num;$i++)
				{
					$vl = $data[$i]? $data[$i] : '';
					$html+='<li><input type="text" name="form[sites][]" value="'+$vl+'"/></li>';
				}
				$('#sites').html($html);
			})

			*/ ?>

			$('body').on('change', '#num_sites', function() {
				var $num = $(this).val();
				var $cn = 0;
				$('select#sites option:selected').each(function() {
					$cn++;
					if ($cn > $num) {
						$(this).prop('selected', false);
					}
				})
				$('.sites_select2').select2({
					'maximumSelectionLength': $num
				});
			}).on('submit', 'form.main_form', function() {
				var $numsite = $('#num_sites').val();
				if ($('select#sites option:selected').length != $numsite) {
					alert("Please select Site");
					$('select#sites').focus();
					return false;
				}
			}).on('change', '.changeprojectname', function() {
				var $name = $('#project_type').val();
				$name += '-' + $('#company').val();
				$name += '-' + $('#drug').val();
				$name += '-' + $('#contact_number').val();
				$('input[name="form[project_name]"]').val($name);
			}).on('change', '#citrate,#edta,#maxslide,#frozenblood', function() {
				$('#citrate,#edta,#maxslide,#frozenblood').prop('readonly', false);
				if ($('#citrate').val() != '0' || $('#edta').val() != '0') {
					$('#maxslide,#frozenblood').val(0).prop('readonly', true);
				} else if ($('#maxslide').val() != '0') {
					$('#citrate,#edta,#frozenblood').val(0).prop('readonly', true);
				} else if ($('#frozenblood').val() != '0') {
					$('#citrate,#edta,#maxslide').val(0).prop('readonly', true);
				}
			})

			$('.sites_select2').select2({
				'maximumSelectionLength': 1
			});

			$('#num_sites').change();
			$('#citrate').change();


		})
	</script>
<?php
}
