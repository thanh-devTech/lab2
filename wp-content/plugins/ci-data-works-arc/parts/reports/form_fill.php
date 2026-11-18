<?php
global $wpdb;

$batch_id = isset($_GET['batch_id']) ? $_GET['batch_id'] : '';
?>

<div class="wrap">
    <h2>Form Fill Report</h2>
    <form method="get">
        <input type="hidden" name="page" value="<?php echo $_GET['page']?>"/>
        <input type="hidden" name="tab" value="<?php echo $_GET['tab']?>"/>
        <label>Batch ID</label>
        <select name="batch_id" class="njs_select2">
			<?php
			$where_option = '';
			
			if (CI_Base_Lab::has_user_labs()) {
				$lab_str = CI_Base_Lab::get_user_lab_ids_str();
				$where_option.= " and ( lab_id in ( {$lab_str} ) or ref_lab_ID in ( {$lab_str} ) )";
			}

			$sql = "select distinct(batch_id) from ci_batches where batch_id!='' {$where_option}  order by batch_id desc";
			$batches = $wpdb->get_results($sql);
			foreach ($batches as $b) {
				$selected = $b->batch_id == $batch_id ? 'selected' : '';
				echo '<option value="' . $b->batch_id . '" ' . $selected . '>' . $b->batch_id . '</option>';
			}
			?>
        </select>
        <input type="submit" name="view" value="Report" class="button-primary">
    </form>

	<?php
	if(!empty($batch_id))
	{

		$where = " batch_id = '{$batch_id}' ";
		$rs = $wpdb->get_results("select * from ci_batches where {$where} order by mid desc");
		$summary = array();

		?>
        Total: <?php echo count($rs)?>

        <div class="wraptbl">
            <table class="wp-list-table widefat striped pages blue_tbl hover_tbl" style="table-layout: auto">
                <thead>
                <tr>
                    <th>Accession Number</th>
                    <th>Short Form</th>
                    <th>Time</th>
                    <th>REQ Form</th>
                    <th>Time</th>
                </tr>
                </thead>
                <tbody>
				<?php
				foreach($rs as $r)
				{
					$req_username = $short_username = '';
					if($r->short_form_by>0)
					{
						if(isset($summary[$r->short_form_by])){
							$short_username = $summary[$r->short_form_by]['username'];
						}else{
							$short_username = $wpdb->get_var("select user_login from {$wpdb->users} where ID = '{$r->short_form_by}'");
							$summary[$r->short_form_by]['username'] = $short_username;
						}
						if(!isset($summary[$r->short_form_by]['short']))
                        {
	                        $summary[$r->short_form_by]['short'] = 0;
                        }
						$summary[$r->short_form_by]['short']++;
					}

					if($r->req_form_by>0){
						if(isset($summary[$r->req_form_by])){
							$req_username = $summary[$r->req_form_by]['username'];
						}else {
							$req_username = $wpdb->get_var("select user_login from {$wpdb->users} where ID = '{$r->req_form_by}'");
							$summary[$r->req_form_by]['username'] = $req_username;
						}
						if(!isset($summary[$r->req_form_by]['req']))
                        {
	                        $summary[$r->req_form_by]['req'] = 0;
                        }
						$summary[$r->req_form_by]['req']++;
					}


					?>
                    <tr>
                        <td><?php echo $r->assen?></td>
                        <td><?php echo $short_username?></td>
                        <td><?php if($r->short_form_by>0)echo $r->short_form_time?></td>
                        <td><?php echo $req_username?></td>
                        <td><?php if($r->req_form_by>0)echo $r->req_form_time?></td>
                    </tr>
					<?php
				}
				?>
                </tbody>
            </table>
        </div>
        <br><br>

        <h2>Summary</h2>
        <div class="wraptbl">
            <table class="wp-list-table widefat striped pages blue_tbl hover_tbl" style="width:500px">
                <thead>
                <tr>
                    <th>Username</th>
                    <th>Short Form</th>
                    <th>Req Form</th>
                </tr>
                </thead>
                <tbody>
				<?php
				foreach ($summary as $userid => $r)
				{
					?>
                    <tr>
                        <td><?php echo isset($r['username'])? $r['username'] : ''?></td>
                        <td><?php echo intval(isset($r['short'])? $r['short'] : 0)?></td>
                        <td><?php echo intval(isset($r['req'])? $r['req'] : 0)?></td>
                    </tr>
					<?php
				}
				?>
                </tbody>
            </table>
        </div>
		<?php
	}
	?>
</div>