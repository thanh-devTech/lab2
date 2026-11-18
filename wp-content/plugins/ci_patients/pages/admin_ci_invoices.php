<?php

use WPO\WC\PDF_Invoices\Compatibility\WC_Core as WCX;
use WPO\WC\PDF_Invoices\Compatibility\Order as WCX_Order;
use WPO\WC\PDF_Invoices\Compatibility\Product as WCX_Product;

global $wpdb, $current_role;
//update settings
$pa_options = get_option('trpatient_settings', array());
$wpo_wcpdf_settings_general = get_option('wpo_wcpdf_settings_general');
if (is_array($wpo_wcpdf_settings_general) && empty($wpo_wcpdf_settings_general['header_logo'])) {
	$wpo_wcpdf_settings_general['header_logo'] = $pa_options['logo_login'];

	update_option('wpo_wcpdf_settings_general', $wpo_wcpdf_settings_general);
}
$wpo_wcpdf_documents_settings_invoice = get_option('wpo_wcpdf_documents_settings_invoice');
if (is_array($wpo_wcpdf_documents_settings_invoice) && empty($wpo_wcpdf_documents_settings_invoice['attach_to_email_ids'])) {
	$wpo_wcpdf_documents_settings_invoice['attach_to_email_ids'] = array(
		'customer_processing_order' => '1',
		'customer_completed_order' => '1',
		'customer_invoice' => '1'
	);
	$wpo_wcpdf_documents_settings_invoice['disable_for_statuses'] = array('wc-cancelled', 'wc-failed');
	$wpo_wcpdf_documents_settings_invoice['display_date'] = 'order_date';
	$wpo_wcpdf_documents_settings_invoice['display_number'] = 'invoice_number';
	$wpo_wcpdf_documents_settings_invoice['number_format'] = array('prefix' => '[invoice_year][invoice_month]', 'suffix' => '', 'padding' => '');
	$wpo_wcpdf_documents_settings_invoice['my_account_buttons'] = 'always';
	$wpo_wcpdf_documents_settings_invoice['invoice_number_column'] = '1';
	$wpo_wcpdf_documents_settings_invoice['disable_free'] = '1';
	$wpo_wcpdf_documents_settings_invoice['use_latest_settings'] = '1';

	update_option('wpo_wcpdf_documents_settings_invoice', $wpo_wcpdf_documents_settings_invoice);
}

$check_duplicatep = true;
$user_id = get_current_user_id();
$patient_id = tr_patient_get_id_patient($user_id);

if ($current_role != 'patient') {
	$user_id = $wpdb->get_var("select ID from {$wpdb->users} where patient_id = {$patient_id}");
}

if ($patient_id == 0) {
	include('admin_ci_setup_patient.php');
	return;
}

$patient_ids = array($patient_id);
$patient = $wpdb->get_row("select * from ci_patients where pa_ID = {$patient_id}");
if ($check_duplicatep && $patient && !empty($patient->pa_phone) && strlen($patient->pa_phone) > 8) {
	$phones = tr_patient_get_phone_arr($patient->pa_phone);
	$dubpa = array();
	if (!empty($phones)) {
		$phones = implode("','", $phones);
		$dubpa = $wpdb->get_col($wpdb->prepare(
			"select pa_ID from ci_patients
						where 
						(
							(pa_first= %s and pa_last = %s)
							OR (pa_first= %s and pa_last = %s)
						)
						 and pa_dob = %s
						 and pa_phone in ('{$phones}') 
						 and pa_ID != {$patient_id} ",
			$patient->pa_first,
			$patient->pa_last,
			$patient->pa_last,
			$patient->pa_first,
			$patient->pa_dob
		));
	}



	if (count($dubpa) > 0) {
		$patient_ids = array_merge($patient_ids, $dubpa);
	}
}

$users_id = array($patient_id => $user_id);
$kids = tr_patient_get_kids($patient_id, $user_id);
if (is_array($kids)) {
	foreach ($kids as $kid => $arr) {
		$uid = $wpdb->get_var("select ID from {$wpdb->users} where patient_id = {$kid} ");
		$patient_ids[] = $kid;
		if ($uid > 0) {
			$users_id[$kid] = $uid;
		}
	}
}


wp_dequeue_style('Admin_Page_Class');
include_once(TRCPDD_PATH . 'pages/formdata.php');

/*
$order_statuses = array('wc-on-hold', 'wc-processing', 'wc-completed');
$orders = wc_get_orders( array(
	'meta_key' => '_customer_user',
	'meta_value' => $users_id,
	'post_status' => $order_statuses,
	'numberposts' => -1
) );
*/

$users_id_str = implode(',', $users_id);
$patient_ids_str = implode(',', $patient_ids);
if (empty($users_id_str)) {
	$users_id_str = '-1';
}
if (empty($patient_ids_str)) {
	$patient_ids_str = '-1';
}
$orders = $wpdb->get_col("SELECT p.ID
                    FROM {$wpdb->posts} as p
                    LEFT JOIN {$wpdb->postmeta} cu ON p.ID = cu.post_id and cu.meta_key='_customer_user'
                    LEFT JOIN {$wpdb->postmeta} pi ON p.ID = pi.post_id and pi.meta_key='_patient_id'
                    WHERE p.post_type IN ('shop_order', 'shop_order_refund')
                    AND (p.post_status = 'wc-processing'
                            OR p.post_status = 'wc-on-hold'
                            OR p.post_status = 'wc-completed')
                    AND (cu.meta_value IN ({$users_id_str}) 
                            OR pi.meta_value IN ({$patient_ids_str})
                        )
                     GROUP BY p.ID
                     order by p.post_date desc");
?>
<div class="wrap">
	<h2>Invoices</h2>
	<?php Tr_Session_Class::show() ?>
	<br>

	<table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl">
		<thead>
			<tr>
				<th>Invoice Number</th>
				<th>Order Date</th>
				<th>Patient Name</th>
				<th>Amount</th>
				<th>Invoice</th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ($orders as $orderid) {
				$order = wc_get_order($orderid);

				$uid = $order->get_customer_id();
				$pid = array_search($uid, $users_id);

				$invoice = wcpdf_get_invoice($order);

				$btype = 'pcr';
				$mid = get_post_meta($order->get_id(), '_pcr_mid', true);
				if (empty($mid)) {
					$mid = get_post_meta($order->get_id(), '_blood_mid', true);
					$btype = 'blood';
				}
				if ($mid > 0) {
					$batch_tbl = tr_pdd_get_table_by_btype($btype);
					$order->patient_name = $wpdb->get_var("select patient_name from {$batch_tbl} where mid = {$mid} ");
				}

				if (empty($order->patient_name)) {
					$patient = $wpdb->get_row("select * from ci_patients where pa_ID = {$pid}");
					$order->patient_name = $patient->pa_last . ', ' . $patient->pa_first;
				}

				$order->invoice_number = $invoice ? $invoice->get_number() : '';
				if (empty($order->invoice_number)) {

					$order->invoice_number = $order->get_id();
					update_post_meta($order->get_id(), '_wcpdf_invoice_number', $order->invoice_number);
				}

			?>
				<tr class="childp">
					<td><?php echo $order->invoice_number ?></td>
					<td><?php echo $order->order_date ?></td>
					<td><?php echo $order->patient_name ?></td>
					<td><?php echo $order->get_total() ?></td>
					<td>
						<?php
						if ($invoice && $invoice->is_enabled()) {
							$pdf_url = wp_nonce_url(admin_url('admin-ajax.php?action=generate_wpo_wcpdf&document_type=invoice&order_ids=' . WCX_Order::get_id($order) . '&my-account'), 'generate_wpo_wcpdf');
						?>
							<a href="<?php echo $pdf_url ?>" target="_blank">Invoice Pdf</a>
						<?php
						}
						?>

					</td>
				</tr>
			<?php
			}
			?>
		</tbody>
	</table>
</div>