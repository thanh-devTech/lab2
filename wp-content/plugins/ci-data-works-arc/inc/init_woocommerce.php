<?php
add_filter('woocommerce_admin_disabled', '__return_true');


add_action('woocommerce_order_status_processing', 'trpdd_woocommerce_order_status_changed', 99);
add_action('woocommerce_order_status_completed', 'trpdd_woocommerce_order_status_changed', 99);
function trpdd_woocommerce_order_status_changed($order_id)
{
	if (!$order_id) return;

	global $wpdb;
	$order = wc_get_order($order_id);
	if (!$order) return;

	$btype = $order->get_meta('btype');
	$assen = $order->get_meta('assen');
	$patient_name = $order->get_meta('patient_name');

	

	$table_name = 'ci_batch_billings';
	$existing_record = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM $table_name WHERE assen = %s",
		$assen
	));

	$data = array(
		'patient'           => $patient_name,
		'claim_no'          => $order_id,
		'assen'             => $assen,
		'servdate'          => date_i18n('Y-m-d H:i:s'),
		'date'              => date_i18n('Y-m-d H:i:s'),
		//'allowed_amount'    => $order->get_total(),
		//'insurance_payment' => $order->get_total(),
		'billed_amount'     => $order->get_total(),
		'received_amount'   => $order->get_total(),
		'balance'           => 0,
		'bill_status'       => 'Paid',
		'patient_balance'   => 0,
		'clam_date'         => date_i18n('Y-m-d H:i:s'),
	);

	if ($existing_record) {
		$wpdb->update(
			$table_name,
			$data,
			array('claim_no' => $order_id),
			array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%f',
				'%f',
				'%f',
				'%f',
				'%f',
				'%s',
				'%f',
				'%s'
			),
			array('%s')
		);
	} else {
		$wpdb->insert($table_name, $data);
	}

	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	if ($batch_tbl && $assen) {
		$wpdb->update(
			$batch_tbl,
			array('paid' => 1),
			array('assen' => $assen),
			array('%d'),
			array('%s')
		);
	}
}

add_action('wp_enqueue_scripts', 'enqueue_thankyou_css');
function enqueue_thankyou_css()
{
	// if (is_wc_endpoint_url('order-received') ) {
	wp_enqueue_style('thankyou-css', TRCPDD_URL . 'css/admin_checkout.css');
	//}
}


add_action('woocommerce_checkout_order_created', 'trpdd_woocommerce_checkout_order_created');
function trpdd_woocommerce_checkout_order_created($order)
{
	$btype = WC()->session->get('btype');
	$assen = WC()->session->get('assen');
	$patient_name = WC()->session->get('patient_name');
	if (!empty($btype)) {
		$order->add_meta_data('btype', $btype, true);
		$order->add_meta_data('assen', $assen, true);
		$order->add_meta_data('patient_name', $patient_name, true);
	}
}

add_action('woocommerce_checkout_order_processed', 'trpdd_update_paid_status_after_checkout');
function trpdd_update_paid_status_after_checkout($order_id)
{
	global $wpdb;
	$order = wc_get_order($order_id);
	$btype = $order->get_meta('btype');
	$assen = $order->get_meta('assen');

	if (empty($btype) || empty($assen)) return;

	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$wpdb->update($batch_tbl, ['paid' => 1], ['assen' => $assen]);
}

add_action('woocommerce_order_status_cancelled', 'trpdd_reset_paid_on_cancel');
function trpdd_reset_paid_on_cancel($order_id)
{
	global $wpdb;
	$order = wc_get_order($order_id);
	$btype = $order->get_meta('btype');
	$assen = $order->get_meta('assen');
	if (empty($btype) || empty($assen)) return;

	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$wpdb->update($batch_tbl, ['paid' => 0], ['assen' => $assen]);
}
