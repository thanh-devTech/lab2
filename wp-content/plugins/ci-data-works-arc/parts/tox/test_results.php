<?php
global $wpdb;

$where .= " and ba.is_saved = 0 ";
if (CI_Base_Lab::has_user_labs()) {
	$lab_str = CI_Base_Lab::get_user_lab_ids_str();
	$where .= " and (ba.lab_id in ( {$lab_str} ) or ba.ref_lab_ID in ( {$lab_str} ) )";
}


if (!empty($_REQUEST['s'])) {
	$has_filter = true;

	if (strpos($_REQUEST['s'], ',') > 0) {
		list($firstname, $lastname) = explode(',', $_REQUEST['s']);
		$firstname = trim($firstname);
		$lastname = trim($lastname);
		$explodeString = "%{$firstname}%{$lastname}%";
		$where .= $wpdb->prepare(" and (ba.patient_name like %s)", $explodeString);
	} else {
		$where .= $wpdb->prepare(" and (ba.assen like %s or ba.patient_name like %s)", $_REQUEST['s'], '%' . $_REQUEST['s'] . '%');
	}
}

if (!empty($_REQUEST['clinic'])) {
	$has_filter = true;
	$where .= $wpdb->prepare(" and ba.requesting_practice like %s ", $_REQUEST['clinic'] . '%');
}

if (!empty($_REQUEST['doctor'])) {
	$has_filter = true;
	$where .= $wpdb->prepare(" and ba.requesting_physician like %s ", $_REQUEST['doctor'] . '%');
}

$show_status = true;
if (isset($_REQUEST['new_results'])) {
	$has_filter = true;
	$last_login = get_user_meta($user_id, '_last_login', true);
	if (empty($last_login)) {
		$last_login = date('Y-m-d H:i:s', strtotime('-2days'));
	}
	$where .= " and re.approve_date >= '{$last_login}' and re.estatus= 'C' ";
	$show_status = false;
}

if (isset($_REQUEST['revised_results'])) {
	$has_filter = true;
	$where .= " and re.internal_notes!='' and re.estatus= 'C' ";
	$show_status = false;
}




if (tr_current_role_in(array('administrator', 'AdminCIDW'))) {
	$show_clinic = true;
	$show_doctor = true;
} else if ($current_role == 'labmanager') {
	$show_clinic = true;
	$show_doctor = true;
} else if ($current_role == 'lab_director') {
	$show_clinic = true;
	$show_doctor = true;
} else {
	$conditions     = trpdd_get_batch_conditions(0);
	$where .= $conditions['where'];
}


$where .= " and (ba.batch_status='completed' or ba.batch_type='E' or ba.batch_type='M' or re.estatus= 'C' ) ";
$where .= " and ba.batch_status != 'not_received' ";
$where .= " and ba.doc > '0001-01-01' and ba.doc <= '{$today} 23:59:59'";


if ($show_filter_approve_date) {
	if (!empty($fromdate)) {
		$where .= " and re.approve_date>= '{$fromdate}' ";
	}

	if (!empty($todate)) {
		$where .= " and re.approve_date <= '{$todate} 23:59:59' ";
	}
}

if (isset($_REQUEST['pos_results'])) {
	$has_filter = true;
	$where .= " and re.estatus='C' and re.has_pos=1 ";
}




$sql_total = " select count(distinct(DATE_FORMAT(ba.doc, '%Y-%m-%d' ) )) as c
		from ci_batches ba
		LEFT JOIN ci_reports re ON ba.assen=re.patient_id
		where 1 {$where}";

if (class_exists('Tr_Cache_Query')) {
	$cache_time = 16400;
	$cache = new Tr_Cache_Query();
	$total = $cache->get_var($sql_total, 0, 0, $cache_time);
} else {
	$total = $wpdb->get_var($sql_total);
}

$row_per_page = 30;
$pagination = new Tr_Pagination_class($row_per_page);
$pagination->setTotal($total);
$offset = $pagination->offset();
$last_login = get_user_meta($user_id, '_last_login', true);

$sql = "select date(ba.doc) as bdoc,
		count(ba.mid) as countall,
		sum(if(re.estatus='C',1,0)) as countapproved,
		sum(if(re.approve_date > '{$last_login}',1,0)) as count_new_result,
		sum(if(FIND_IN_SET('{$user_id}',re.users_printed),1,0)) as countprint
		from ci_batches ba
		LEFT JOIN ci_reports re ON ba.assen = re.patient_id
		where 1 {$where_limit_smart} {$where}
		group by bdoc
		order by bdoc desc
		limit {$offset},{$row_per_page}";

$results = $wpdb->get_results($sql);

if (isset($_REQUEST['debug'])) {
	echo $sql;
}
$col_num = 8;
if ($show_doctor) $col_num++;
if ($show_clinic) $col_num++;
if ($show_status) $col_num++;
?>

<div class="wrap">
	<h2>Tox Results</h2>

	<form method="get">
		<label>Patient Name / Accession</label>
		<input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
		<input type="hidden" name="tab" value="<?php echo $tab ?>" />
		<input type="text" name="s" value="<?php echo isset($_REQUEST['s']) ? $_REQUEST['s'] : '' ?>" placeholder="Patient Name / Accession" />
		<?php if ($show_clinic) {
		?>
			<input type="text" name="clinic" value="<?php echo isset($_REQUEST['clinic']) ? $_REQUEST['clinic'] : '' ?>" placeholder="Clinic" />
		<?php
		} ?>
		<?php if ($show_doctor) {
		?>
			<input type="text" name="doctor" value="<?php echo isset($_REQUEST['doctor']) ? $_REQUEST['doctor'] : '' ?>" placeholder="Doctor" />
		<?php
		} ?>

		<label><input type="checkbox" class="checkbox_search" name="new_results" value="1" <?php checked(isset($_REQUEST['new_results']) ? $_REQUEST['new_results'] : '', '1') ?> /> new results since last login</label>
		<label><input type="checkbox" class="checkbox_search" name="revised_results" value="1" <?php checked(isset($_REQUEST['revised_results']) ? $_REQUEST['revised_results'] : '', '1') ?> /> revised results</label>
		<label><input type="checkbox" class="checkbox_search" name="pos_results" value="1" <?php checked(isset($_REQUEST['pos_results']) ? $_REQUEST['pos_results'] : '', '1') ?> /> Pos Results Only</label>

		<?php if ($show_filter_approve_date) { ?>

			<div style="padding-top:5px;padding-left:80px">
				<label>Result Date:</label>
				<input type="search" name="fromdate" value="<?php echo $fromdate ?>" class="hasdate" placeholder="From" />
				<input type="search" name="todate" value="<?php echo $todate ?>" class="hasdate" placeholder="To" />
				<input type="submit" value="Search" class="button bsty6" />
			</div>

		<?php } else {
		?>
			<input type="submit" value="Search" class="button bsty6" />
		<?php
		} ?>


	</form>
	<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>

	<div class="wraptbl">
		<table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl">
			<thead class="sticky">
				<tr>
					<th style="width:2%;">#</th>
					<th style="width:8%">DOC</th>
					<?php if ($show_clinic) : ?>
						<th style="width:15%">Clinic</th>
					<?php endif; ?>
					<?php if ($show_doctor) : ?>
						<th style="width:12%">Doctor</th>
					<?php endif; ?>
					<th style="width: 8%;">Req Type</th>
					<th style="width: 110px;">Accession</th>
					<th style="width: 12%;">Patient Name</th>
					<th style="width: 8%;">DOB</th>
					<th style="width:8%">Batch Status</th>
					<?php if ($show_status) : ?>
						<th style="width:8%">Status</th>
					<?php endif; ?>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ($results as $r) {
					$ii = str_replace('-', '', $r->bdoc);
				?>
					<tr class="parent" data-p="doc_<?php echo $ii ?>">
						<td><i></i></td>
						<td><?php echo $r->bdoc ?></td>
						<td colspan="<?php echo $col_num - 4 ?>" align="right">
							<?php if ($r->count_new_result > 0) : ?>
								<span class="new_results"></span>
							<?php endif; ?>
						</td>
						<td colspan="2" align="right" class="bactions">
							<?php if ($r->countprint >= $r->countapproved && $r->countprint > 0) : ?>
								<span class="dashicons dashicons-yes"></span>
							<?php endif; ?>
							<div class="batch_actions">
								<a class="printbatch bsty1">Print All</a>
								<a class="downloadbatcheachfile bsty7">Download Ind</a>
								<a class="downloadbatch bsty4">Download All</a>
							</div>
						</td>
					</tr>
					<tr class="child p_doc_<?php echo $ii ?>" data-doc="<?php echo $r->bdoc ?>">
						<td colspan="<?php echo $col_num ?>" class="nopadding"></td>
					</tr>
				<?php
				}
				?>
			</tbody>
		</table>
	</div>

	<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
</div>

<style>
	.report_tbl .dashicons-yes {
		color: #00a700 !important;
		font-size: 24px;
		padding: 4px 2px !important;
	}

	.report_tbl .dashicons-yes.orange {
		color: orange !important;
	}

	.dashicons.dashicons-warning {
		font-size: 18px;
		padding: 0 !important;
		line-height: 30px;
	}

	span.red {
		color: red;
	}

	span.yellow {
		color: #ffe74e;
	}

	.report_tbl tr.parent td>span,
	.tls>span {
		white-space: nowrap
	}
</style>
<script type="text/javascript" src="<?php echo TRCPDD_URL ?>js/pdf.js"></script>
<script type="text/javascript">
	PDFJS.workerSrc = '<?php echo TRCPDD_URL ?>/js/pdf.worker.js';
	jQuery(function($) {


		function trcpp_load_results($ii) {
			var $trpr = $('tr[data-p="' + $ii + '"]');
			var $obj = $('tr.p_' + $ii + ' td');
			var $doc = $('tr.p_' + $ii).data('doc');
			if ($obj.html() != '') {
				$('a.printbatch', $trpr).show();
				return;
			} else {
				$obj.html('<div class="loading center"></div>');
			}
			var $data = {
				tr_action: 'ci_load_test_results',
				doc: $doc,

			};
			<?php if (isset($_GET['view'])) {
				echo '$data.view=1;';
			} ?>
			$.ajax({
				data: $data,
				type: 'post',
				success: function(rs) {
					var $html = $(rs);
					$obj.html($html);
					$('a.printbatch', $trpr).show();
					$('body,html').scrollTop($obj.offset().top - 150);
				}
			})
		}

		$('body').on('click', 'tr.parent', function(e) {
			if (e.target.tagName != 'TD' && e.target.tagName != 'TR' && e.target.tagName != 'I') return;
			$('tr.child').hide();
			$('tr.parent.active').removeClass('active');
			if ($(this).data('showed') != '1') {
				var $obj = $('tr.p_' + $(this).data('p'));
				$obj.show();
				$('tr.parent').data('showed', '0');
				$(this).data('showed', '1');
				$(this).addClass('active');
				setCookie('active_test_results', $(this).data('p'));
				trcpp_load_results($(this).data('p'));
			} else {
				$('a.printbatch', $(this)).hide();
				$('tr.p_' + $(this).data('p')).hide();
				$(this).data('showed', '0');
				$(this).removeClass('active');
			}
			return false;
		});
		$('body').on('click', '.checkbox_search', function() {
			$(this).parents('form').submit();
		})

		$('body').on('click', 'a.print_pdf', function() {
			var $url = '<?php echo admin_url('/') ?>' + $(this).attr('href');
			$(this).html('Loading');
			$.print_Pdf($url, $(this));
			return false;
		}).on('printed', 'a.print_pdf', function() {
			var $obj = $(this);
			$obj.parent().find('.dashicons-yes').remove();
			$obj.parent().append('<span class="dashicons dashicons-yes"></span>');
			$obj.html('Printed');
		}).on('print_error', 'a.print_pdf, a.printbatch', function() {
			$(this).html('Print');
		}).on('click', 'a.printbatch', function() {
			var $urls = [];
			var $trpr = $(this).parents('tr.parent');
			var $trchild = $('tr.p_' + $trpr.data('p'));
			$('a.print_pdf', $trchild).each(function() {
				$urls.push($(this).attr('href'));
			})
			if ($urls.length == 0) {
				alert('Not found');
				return false;
			}
			$.print_Pdfs($urls, $(this));
			return false;
		}).on('printed', 'a.printbatch', function() {
			var $obj = $(this);
			$obj.html('Printed');
		}).on('click', 'a.downloadbatcheachfile', function(e) {

			var $ids = [];
			var $trpr = $(this).parents('tr.parent_c');
			if ($trpr.length == 0) {
				$trpr = $(this).parents('tr.parent');
			}
			var $trchild = $('tr.p_' + $trpr.data('p'));
			$('tr.childp', $trchild).each(function() {
				var $cbk = $('input[type="checkbox"]:checked', $(this));
				if ($cbk.length > 0) {
					$ids.push($(this).data('id'));
				}
			})
			if ($ids.length == 0) {
				alert('Not found');
				return false;
			}
			$ids = $ids.join(',');
			location.href = '?tr_action=ci_download_pdfs&downloaded=1&btype=blood&ids=' + $ids;
			return false;
		}).on('download_error', 'a.downloadbatch', function() {
			$(this).html('Download');
		}).on('click', 'a.downloadbatch', function(e) {
			var $ids = [];
			var $trpr = $(this).parents('tr.parent_c');
			if ($trpr.length == 0) {
				$trpr = $(this).parents('tr.parent');
			}
			var $trchild = $('tr.p_' + $trpr.data('p'));
			$('tr.childp', $trchild).each(function() {
				$ids.push($(this).data('id'));
			})
			if ($ids.length == 0) {
				alert('Not found');
				return false;
			}
			$ids = $ids.join(',');
			location.href = '?tr_action=ci_download_pdfs&downloaded=1&btype=tox&ids=' + $ids;
			return false;
		}).on('click', 'a.download_pdf', function() {
			var $obj = $(this);
			$obj.parent().find('.dashicons-yes').remove();
			$obj.parent().append('<span class="dashicons dashicons-yes"></span>');
		});



		var $old_active = getCookie('active_test_results');
		if ($old_active && $old_active != null) {
			var $obj = $('tr.p_' + $old_active);
			try {
				$obj.show();
				$('[data-p="' + $old_active + '"]').data('showed', '1').addClass('active');
				if ($old_active.indexOf('lab_') > -1) {
					$obj.parents('.child').show();
				}
				trcpp_load_results($old_active);
			} catch (e) {}

		}



	})
</script>