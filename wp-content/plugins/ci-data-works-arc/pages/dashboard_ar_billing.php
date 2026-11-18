<?php
global $dashboard_ar_billing;
global $total_ar_billing_change;
global $total_ar_billing_prev;
global $total_ar_billing_td;

$total_ar_billing_change = 0;
$total_ar_billing_prev = 0;
$total_ar_billing_td = 0;

ob_start();

$datey_from = $current_year.'-01-01';
$datey_to = $current_year . '-12-31 23:59:59';
$last_yesterday = date('Y-m-d',$last_time - 86400);

$yesterday = date('Y-m-d',$last_time);



$where2 = " and (WI.payer_commercial!='1' or WI.payer_commercial is null)
            and (WI.payer_medicaid!='1' or WI.payer_medicaid is null)
            and (WI.payer_unknown!='1' or WI.payer_unknown is null)
            and (WI.payer_medicare!='1' or WI.payer_medicare is null)
            and (WI.payer_workmen!='1' or WI.payer_workmen is null)
            and (WI.payer_other!='1' or WI.payer_other is null) 
            and re.estatus = 'C'
            and assen in (select assen from ci_batch_billings where bill_status ='billed') 
            ";


if($lab_id > 0)
{
	$where2 .= " and ba.lab_id ='{$lab_id}' ";
}

$query = "
    select y.insurance,cashbal_td,cashbal_prev from
    (select insurance
     from
    (select ba.insurance from ci_batches ba
     LEFT JOIN `ci_payer_list` WI ON WI.payer_name = ba.insurance
     JOIN ci_reports re ON ba.assen = re.patient_id
    where  re.created_date >= '{$datey_from}' and re.created_date <= '{$datey_to}'  {$where2}  ) as rs group by rs.insurance) as y

    left join
    (select insurance, cashbal_prev
     from
    (
         select ba.insurance,  count(*) AS cashbal_prev
             from ci_batches ba
             JOIN ci_reports re ON ba.assen = re.patient_id
             LEFT JOIN `ci_payer_list` WI ON WI.payer_name = ba.insurance
            where  re.created_date >= '{$last_yesterday}' and re.created_date <= '{$last_yesterday} 23:59:59' {$where2} 
            
     ) as rs group by rs.insurance) as m
    ON m.insurance = y.insurance

    left join
    (select insurance, cashbal_td
     from
    (
        select ba.insurance, count(*) AS cashbal_td
             from ci_batches ba
             LEFT JOIN `ci_payer_list` WI ON WI.payer_name = ba.insurance
             JOIN ci_reports re ON ba.assen = re.patient_id
            where  re.created_date >= '{$yesterday}' and re.created_date <= '{$yesterday} 23:59:59' {$where2} 
            
     ) as rs group by rs.insurance) as d
    ON d.insurance = y.insurance";
//var_dump($query);exit;
$rows = $wpdb->get_results($query);


?>
    <div class="wrap_db">
        <table class="dashboard_tbl">
            <tr>
                <th></th>
                <th><span><?php echo date('M d, Y',$last_time);?></span></th>
                <th><span>Previous</span></th>
                <th><span>Change</span></th>
            </tr>

			<?php foreach($rows as $row):

				if(empty($row->cashbal_prev))$row->cashbal_prev = 0;
				if(empty($row->cashbal_td))$row->cashbal_td = 0;
				if(($row->cashbal_prev==0 && $row->cashbal_td==0) ){
					continue;
				}

				$change = $row->cashbal_td - $row->cashbal_prev;

				$total_ar_billing_prev += $row->cashbal_prev;
				$total_ar_billing_td += ($row->cashbal_td);
				$total_ar_billing_change += $change;

				?>
                <tr>
                    <td><?php echo $row->insurance?></td>
                    <td><?php echo ($row->cashbal_td);?></td>
                    <td><?php echo ($row->cashbal_prev);?></td>
                    <td><?php echo $change?></td>
                </tr>
			<?php endforeach;?>

			<?php

			foreach($ins_mix as $k => $txt)
			{
				$where3 = " and WI.{$k}='1' and re.estatus = 'C' and assen in (select assen from ci_batch_billings where bill_status ='billed') ";
				if($lab_id > 0)
				{
					$where3 .= " and ba.lab_id ='{$lab_id}' ";
				}
				$query = "
                select cashbal_td,cashbal_prev from

                (select count(*) AS cashbal_prev
                from ci_batches ba
                 JOIN `ci_payer_list` WI ON WI.payer_name = ba.insurance
                 JOIN ci_reports re ON ba.assen = re.patient_id
                where  re.created_date >= '{$last_yesterday}' AND re.created_date <= '{$last_yesterday} 23:59:59' {$where3}  
     
                )  as y

                LEFT JOIN

                (select count(*) as cashbal_td
                from ci_batches ba
                 JOIN `ci_payer_list` WI ON WI.payer_name = ba.insurance
                 JOIN ci_reports re ON ba.assen = re.patient_id
                where  re.created_date >=  '{$yesterday}' AND re.created_date <= '{$yesterday} 23:59:59' {$where3}  
              
                ) as d
                ON 1
                ";
				//echo $query;exit;
				$row = $wpdb->get_row($query);

				$change = $row->cashbal_td - $row->cashbal_prev;

				$total_ar_billing_prev += $row->cashbal_prev;
				$total_ar_billing_td += ($row->cashbal_td);

				$total_ar_billing_change += $change;

				?>
                <tr>
                    <td><?php echo $txt?></td>
                    <td><?php echo intval($row->cashbal_td);?></td>
                    <td><?php echo intval($row->cashbal_prev);?></td>
                    <td><?php echo intval($change)?></td>
                </tr>
				<?php
			}
			?>
            <tr class="totalrow">
                <td></td>
                <td><?php echo $total_ar_billing_td?></td>
                <td><?php echo $total_ar_billing_prev?></td>
                <td><?php echo $total_ar_billing_change?></td>
            </tr>
        </table>
    </div>

<?php
$dashboard_ar_billing = ob_get_clean();