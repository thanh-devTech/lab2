<?php
global $wpdb, $current_role;

add_thickbox();

wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);

wp_enqueue_script('admin_result', TRCPDD_URL . 'js/admin_result.min.js', array(), TRCPDD_VS);
wp_enqueue_script('jquery-ui');
wp_enqueue_script('jquery-ui-dialog');
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), TRCPDD_VS);


if ($_GET['page'] == 'ci_test_results') {
	$use_upload = false;
}

$sql = "SELECT stu.*,
			(select count(*) from ci_batches_study ba 
				LEFT JOIN ci_reports_study re ON re.patient_id = ba.assen
				Where ba.project_ID = stu.ID and ba.batch_status != 'archived' and (re.id is null or re.estatus !='C' or re.estatus is null ) ) as count
            FROM `ci_study` stu
            where 1  {$where_study}
            group by stu.ID
             ORDER BY `project_name` asc ";

if (isset($_REQUEST['debug'])) {
	echo $sql;
}
$studies = $wpdb->get_results($sql);



?>
<h2>Study Results</h2>
<?php Tr_Session_Class::show() ?>

<div class="tablenav">
	<form method="get" class="left">
		<input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
		<input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
		<?php if (isset($_GET['view'])) { ?>
			<input type="hidden" name="view" value="1" />
		<?php } ?>
		<label>Accession:</label>
		<input type="text" name="s" value="<?php echo $search ?>" style="width:250px" />
		<input type="submit" class="button bsty6 bsty6" value="Search" />

	</form>
	<?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?>
</div>

<div class="wraptbl">
	<table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl" data-count="<?php echo @$count_rows ?>">
		<thead class="sticky">
			<tr>
				<th style="width:40px">#</th>
				<th style="width: 40px;">Study</th>
				<th style="width: 80px;">Accession</th>
				<th style="width: 10%;">Patient ID</th>
				<th style="width: 30px;">Phase</th>
				<th style="width: 10%;">Time Point</th>
				<th style="width: 8%;">Condition</th>
				<th style="width: 80px;">Doc</th>
				<th style="width: 80px;">Result Type</th>
				<th style="width:100px;text-align: center">Status</th>
				<th style="text-align: center">Actions</th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ($studies as $study) {
				if ($study->count == 0) continue;
			?>
				<tr class="parent parentacts <?php echo $classes ?>" data-id="<?php echo $study->ID ?>">
					<td><i></i></td>
					<td colspan="4"><?php echo $study->project_name ?></td>
					<td colspan="7">
						<?php echo $study->count ?>
					</td>
				</tr>
			<?php
			}
			?>
		</tbody>
	</table>
</div>


<script>
	jQuery(function($) {
		var $final_results = {
			0: 'None',
			2: 'Preliminary',
			1: 'Final',
			3: 'Amended Report'
		};

		function trcpp_load_results($tr) {
			var $obj = $tr.next('tr');
			if ($obj.hasClass('child')) {
				$obj.show();
				$('td', $obj).html('<div class="loading center"></div>');
			} else {
				$tr.after('<tr class="child"><td colspan="11"><div class="loading center"></div></td></tr>');
				$obj = $tr.next('tr');
				$obj.show();
			}
			var $studyid = $tr.data('id');
			$.ajax({
				data: {
					trajax: 1,
					act: 'load_results_study',
					pending: 1,
					studyid: $studyid,
				},
				type: 'post',
				success: function(rs) {
					var $html = $(rs);
					$('td', $obj).html($html);
				},
				error: function(c) {
					trcpp_load_results($tr);
				}
			})
		}

		$('body').on('click', 'a.print_pdf', function() {
			var $url = '<?php echo admin_url('/') ?>' + $(this).attr('href');
			$(this).html('Loading');
			$.print_Pdf($url, $(this));
			return false;
		}).on('change', '.final_results select', function() {
			var $this = $(this);
			var $td = $(this).parent();
			var $tr = $td.parents('tr');
			var $val = $(this).val();
			var $id = $('.cid input', $tr).val();
			$tr.find('.sloading').remove();
			$td.append('<span class="sloading"></span>');
			$.ajax({
				type: 'post',
				dataType: 'json',
				data: {
					trajax: 1,
					act: 'change_final_results',
					id: $id,
					val: $val
				},
				success: function(rs) {
					$tr.find('.sloading').remove();
					if (rs.status == 'ok') {
						if (rs.action == 'remove_hold') {
							$tr.removeClass('bs_hold');
							var $approvebtn = $('a.approvebtn', $tr);
							if ($approvebtn.html() == 'Approve') {
								$approvebtn.click();
							}
						}
					} else {
						$this.change();
					}
				},
				error: function() {
					$this.change();
				}
			})
		}).on('click', 'tr.parent', function(e) {
			if (e.target.tagName == 'A') return;
			var $pr = $(this);
			$('tr.child').hide();
			$('tr.parent').removeClass('active');
			if ($pr.data('showed') != '1') {
				$pr.data('showed', '1');
				$pr.addClass('active');

				trcpp_load_results($pr);
			} else {
				$pr.data('showed', '0');
				$pr.removeClass('active');
			}
			return false;
		}).on('click', '.donwload_csv', function() {
			var $tr = $(this).parents('tr.parent');
			var $this = $(this);
			if ($this.html() != 'CSV') {
				return false;
			}
			$this.html('loading...');

			var $id = $tr.data('id');
			location.href = location.href + '&trajax=1&act=export_csv&id=' + $id;
			setTimeout(function() {
				$this.html('CSV');
			}, 5000);
		})

	})
</script>