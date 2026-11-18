<div class="wrap">
    <h2>Lost Providers</h2>
    <form method="get">
        <input type="hidden" name="page" value="<?php echo $_GET['page']?>"/>

        <select name="bm" class="njs_select2">
			<?php

			for($i=1;$i<=12;$i++)
			{
				$selected = isset($_GET['bm']) && $i==$_GET['bm']? 'selected':'';
				$txt = $i>1? $i.' Months': $i.' Month';
				echo '<option value="'.$i.'" '.$selected.'>'.$txt.'</option>';
			}
			?>
        </select>
        <input type="submit" class="button bsty6" value="Report"/>
    </form>

	<?php
	if(isset($_GET['bm']))
	{
		global $wpdb,$current_role;

		$user_id = get_current_user_id();
		$bm = $_GET['bm'];
		$today = date_i18n("Y-m-d");
		$from_date = date('Y-m-d', strtotime("-{$bm} months"));
		$bm1 = $bm+1;
		$from_date1 = date('Y-m-d', strtotime("-{$bm1} months"));

		$where = '';
		if(!tr_current_role_in(array('lab','AdminCIDW','administrator','administrator_p','labmanager'))){
			//find rep in group
			$rep_ids = array();
			$users_reps = tr_dpp_get_users_in_group($user_id);
			foreach($users_reps as $uid)
			{
				$rid = get_user_meta($uid,'_rep_id',true);
				if($rid>0)
				{
					$rep_ids[] = $rid;
				}
			}
			if($current_role=='distributor'){
				$rep_ids2 = $wpdb->get_col("select rep.rep_ID from ci_lab_reps AS rep 
                                    JOIN ci_distributors dis ON dis.dis_ID = rep.rep_distributor_ID
                                    WHERE dis.dis_user_ID = {$user_id}");
				if(count($rep_ids2)>0)
				{
					$rep_ids = array_merge($rep_ids,$rep_ids2);
				}
			}

			if(count($rep_ids)>0)
			{
				$rep_ids = implode(',',$rep_ids);
				$where.= " and cli.`cli_rep-ID` in ({$rep_ids}) ";
			}
			else{
				switch($current_role)
				{
					case 'lab_rep':
					case 'lab_rep_w2':
						$rep_id = get_user_meta($user_id,'_rep_id',true);
						$where.= " and cli.`cli_rep-ID` = {$rep_id} ";
						break;
					case 'office_manager':
					case 'multi_site_manager':
						$cids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID = '{$user_id}'");
						$cids = implode(',',$cids);
						$where .= " and cli.cID in ({$cids})";
						break;
					default:
						$where.= " and 1=2";
						break;
				}
			}
		}

		$childq = array();
		$cli_IDs = array(0);
		$not_cids = array(0);
		foreach(tr_pdd_get_batch_types() as $btype)
        {
	        $batch_tbl = tr_pdd_get_table_by_btype($btype);

	        $cids = $wpdb->get_col("select distinct(ba.cli_ID) from {$batch_tbl} ba 
	                                where ba.spec_received < '{$from_date}' 
                                     and ba.spec_received>= '$from_date1' 
                        ");
	        if(count($cids)>0)
            {
                $cli_IDs = array_merge($cli_IDs, $cids);
            }

            $nocid = $wpdb->get_col("select distinct(ba.cli_ID) from {$batch_tbl} ba 
                            where ba.spec_received >= '{$from_date}'  
                        ");
	        if(count($nocid)>0)
	        {
		        $not_cids = array_merge($not_cids, $nocid);
	        }
        }

		$cli_IDs = implode(',',$cli_IDs);
		$not_cids = implode(',',$not_cids);
		$where.= " and cli.cID in ({$cli_IDs}) ";
		$where.= " and cli.cID not in ({$not_cids}) ";

		$sql = "select cli.*,
                    rep.rep_first,
                    rep.rep_last,
                    (select group_concat(ma_manager) from ci_clinic_manager cm where cm.ma_cli_ID = cli.cID) as office_manager
                    
                    from ci_clinic cli 
                        left join ci_lab_reps rep ON rep.rep_ID = cli.`cli_rep-ID`
                        where  cli.cli_status = 1
                        {$where}
                        order by cli.cli_clinic asc";
		$rows = $wpdb->get_results($sql);
		?>
        <h2>Lost Providers</h2>
        <div>Total: <?php echo count($rows)?></div>
        <div class="wraptbl">
            <table class="wp-list-table widefat striped posts blue_tbl">
                <thead>
                <tr>
                    <th>Clinic</th>
                    <th>Address</th>
                    <th>Phone</th>
                    <th>Office Manager</th>
                    <th>Salesperson</th>
                    <th>Last Date</th>
                </tr>
                </thead>
                <tbody>
				<?php
				foreach($rows as $row)
				{
                    $last_date = '';
                    $childq = array();
					foreach(tr_pdd_get_batch_types() as $btype)
					{
						$batch_tbl = tr_pdd_get_table_by_btype($btype);
						$childq[] = "(select spec_received from {$batch_tbl} ba
	                                 where ba.cli_ID = {$row->cID} 
	                                 order by ba.spec_received desc
	                                 limit 1)";
					}
					$childq = implode(' UNION ALL ', $childq);
					$last_date = $wpdb->get_var("select spec_received from ({$childq}) r 
					                                order by spec_received desc 
					                                limit 1");
					?>
                    <tr>
                        <td><?php echo $row->cli_clinic?></td>
                        <td><?php echo $row->cli_street.' '.$row->cli_city.' '.$row->cli_state.' '.$row->cli_zip?></td>
                        <td><?php echo $row->{'cli_om-phone'}?></td>
                        <td><?php echo $row->office_manager?></td>
                        <td><?php echo $row->rep_first. ' '. $row->rep_last?></td>
                        <td><?php echo $last_date?></td>
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