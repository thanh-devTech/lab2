<?php
global $wpdb;
$list 	= get_option('ci_monitored_tests_clinics', array());
$rs 	= $wpdb->get_results("select cID,cli_clinic from ci_clinic order by cli_clinic asc");
$clinics = array();
foreach ($rs as $r) {
	$clinics[$r->cID] = $r->cli_clinic;
}

$rs = $wpdb->get_results("select te_ID,te_name from ci_test_panels where te_type='tox' ");
$tests = array();
foreach ($rs as $r) {
	$tests[$r->te_ID] = $r->te_name;
}
?>
<div class="row">
	<select id="clinicidsl">
		<?php
		echo '<option value="">select clinic </option>';
		foreach ($clinics as $cid => $clinic) {
			echo '<option value="' . $cid . '">' . $clinic . '</option>';
		}
		?>
	</select>
	<a class="button add_new_rowcs">Add</a>
</div>

<div class="row">
	<table id="listcs" class="wp-list-table widefat fixed striped table-view-list pages">
		<thead>
			<tr>
				<th style="width:30%">Clinic</th>
				<th>Tests</th>
				<th style="width:50px"></th>
			</tr>
		</thead>
	</table>
</div>

<script>
	var $list = <?php echo json_encode(is_array($list)? $list : array()) ?>;
	var $clinics = <?php echo json_encode($clinics) ?>;
	var $tests = <?php echo json_encode($tests) ?>;
	jQuery(function($) {
		function addnewrowcs($data) {
			var $cid = $data['cID'];
			var $clinic = $clinics[$cid];
			var $tsed = $data['tests'] || [];
			var $html = '<tr class="cli_'+$cid+'">';
			$html += '<th>' + $clinic + '</th>';
			$html += '<td><select style="width:100%" class="jsselect2" name="listcs[' + $cid + '][]" multiple>';
			for (i in $tests) {
				$selected = $tsed.includes(i)? 'selected':'';
				$html += '<option value="' + i + '" '+$selected+'>' + $tests[i] + '</option>';
			}
			$html += '</select></td>';
			$html+='<td><a class="removerow button">-</a></td>';
			$html += '</tr>';
			var $row = $($html);
			$('table#listcs').append($row);
			$('.jsselect2',$row).select2();
		}

		$('body').on('click','a.removerow',function(){
			$(this).parents('tr').remove();
		})
		.on('click','.add_new_rowcs',function() {
			var $cid = $('select#clinicidsl').val();
			if ($cid > 0) {
				if($('table#listcs tr.cli_'+$cid).length>0)
				{
					alert('Clinic exist');
					return;
				}
				addnewrowcs({
					'cID': $cid,
					'tests': []
				});
				$('select#clinicidsl option[value="' + $cid + '"]').remove();
			}

		})
		for (i in $list) {
			addnewrowcs({
					'cID': i,
					'tests': $list[i]? $list[i] : []
				});
		}
	})
</script>