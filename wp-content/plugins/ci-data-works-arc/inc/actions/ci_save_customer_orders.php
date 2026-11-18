<?php

global $wpdb;

$orders = $_POST['order'];
$ships = $_POST['ship'];
$return = array('status'=>'ok');

foreach($orders as $txn_id => $order)
{
	$order['co_txn_id'] = $txn_id;
	$order['author_ID'] = get_current_user_id();
	$order['doc_ID'] = $_POST['doc_id'];
	$order['cli_ID'] = $_POST['cli_id'];

	if(empty($order['ship_date']) || $order['ship_date']=='0000-00-00')
	{
		$order['ship_date'] =  date_i18n('Y-m-d');
	}

	if(!empty($order['ship_notes'])){
		$order['ship_notes'] = stripslashes($order['ship_notes']);
	}

	if(!empty($order['order_notes'])){
		$order['order_notes'] = stripslashes($order['order_notes']);
	}
	if(!empty($order['ups'])){
		$order['ups'] = stripslashes($order['ups']);
	}

	$exists = $wpdb->get_var("select co_ID from ci_customer_orders WHERE co_txn_id = '{$txn_id}' ");
	if($exists)
	{
		$rs = $wpdb->update('ci_customer_orders',$order,array('co_txn_id'=>$txn_id));
	}
	else{
		$order['created'] = Tr_Base_Class_V4::date('Y-m-d H:i:s');
		$rs = $wpdb->insert('ci_customer_orders',$order);
	}

	if($rs===false)
	{
		$return['status'] = 'error';
		$return['count_error']++;
		$return['msg_'.$txn_id] = $wpdb->last_error;
	}

}

foreach($ships as $sid => $vl)
{
	$wpdb->update('wp_wp_eStore_customer_tbl',array('ship_qty'=>$vl),array('id'=>$sid));
}

wp_send_json($return);
exit;