<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb;

if(!defined('WC_PLUGIN_FILE')){
	return;
}

$last_id = get_option('_tr_sync_billing_order_item_id',0);
if(empty($last_id))
{
	$last_id = 0;
}

$sql = "select ma.meta_value,
			ma.order_item_id as item_id,
			tb_btype.meta_value as btype,
			bb.id
		from {$wpdb->prefix}woocommerce_order_itemmeta ma
		left join {$wpdb->prefix}woocommerce_order_itemmeta tb_btype ON ma.order_item_id = tb_btype.order_item_id 
											and tb_btype.meta_key='_btype'
		left join ci_batch_billings bb ON bb.assen = ma.meta_value
		where ma.meta_key='Accession'
		and (bb.id is null or bb.received_amount='')
		and ma.order_item_id > {$last_id}
		limit 100
		 ";
$rs = $wpdb->get_results($sql);
echo $wpdb->last_error;
echo count($rs);


foreach($rs as $r)
{
	$last_id = $r->item_id;

	$btype = $r->btype;
	$assen = $r->meta_value;
	$bill_status = 'Paid';

	if(strpos($btype,'-')>0)
	{
		list($btype) = explode('-',$btype);
	}

	$batch_tbl = tr_pdd_get_table_by_btype($btype);

	$amount = $wpdb->get_var("select meta_value from {$wpdb->prefix}woocommerce_order_itemmeta
				where meta_key='_line_total' and order_item_id = {$r->item_id} ");

	$bat = $wpdb->get_row("select batch_created, insurance
					from {$batch_tbl} 
					where assen='{$assen}'");
	if($amount==0)
	{
		$bill_status = 'Need Billing';
		$insurance = $bat->insurance;
		if(stripos($insurance,'No Insurance - Cash')!==false)
		{
			$bill_status = 'Need Billing';
		}
	}

	$billed_date = date('Y-m-d',strtotime($bat->batch_created));

	if($r->id>0)
	{
		$rs2 = $wpdb->update('ci_batch_billings', array(
			'received_amount'=>$amount,
			'billed_amount'=>$amount,
			'claim_date'=>$billed_date,
		),array('id'=>$r->id));
	}else{

		$rs2 = $wpdb->insert('ci_batch_billings',array(
			'assen' => $assen,
			'billed_amount' => $amount,
			'received_amount' => $amount,
			'claim_date' => $billed_date,
			'bill_status' => $bill_status,

		));
	}

	if($rs2===false)
	{
		var_dump($wpdb->last_error);
		continue;
	}


}

update_option('_tr_sync_billing_order_item_id',$last_id,false);

$limit_post_date = date('Y-m-d H:i:s', time() - 3600*2);
$wpdb->query("update `{$wpdb->posts}` set post_status = 'wc-completed' 
where post_status='wc-processing'
and post_date_gmt < '{$limit_post_date}'");