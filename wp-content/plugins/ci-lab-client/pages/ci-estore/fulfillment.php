<?php if (!defined('ABSPATH')) { exit; } ?>
<?php
$ci_estore_status_counts = array_count_values(wp_list_pluck($orders, 'status'));
$ci_estore_statuses = array('Pending', 'Processing', 'Shipped', 'Cancelled');
?>
<div class="ci-estore-admin-panel<?php echo $active_admin_tab === 'fulfillment' ? ' is-active' : ''; ?>" data-admin-panel="fulfillment">
	<div class="ci-estore-list-toolbar">
		<div class="ci-estore-status-summary">
			<?php foreach ($ci_estore_statuses as $ci_estore_status) : ?>
				<button type="button" data-status-filter="<?php echo esc_attr($ci_estore_status); ?>">
					<?php echo esc_html($ci_estore_status); ?>
					<span><?php echo esc_html(isset($ci_estore_status_counts[$ci_estore_status]) ? $ci_estore_status_counts[$ci_estore_status] : 0); ?></span>
				</button>
			<?php endforeach; ?>
		</div>
		<div class="ci-estore-fulfillment-search">
			<label class="ci-estore-search">
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
				<input type="search" data-search-target=".ci-estore-order-row" placeholder="Search clinic…">
			</label>
		</div>
	</div>
	<div class="ci-estore-table-card">
		<div class="ci-estore-table-scroll">
			<table class="ci-estore-table ci-estore-fulfillment-table">
				<thead>
					<tr>
						<th>#</th>
						<th>Clinic</th>
						<th class="ci-estore-order-date-head">Order Date</th>
						<?php foreach ($store_activity_types as $store_activity_type => $store_activity_label) : ?><th class="ci-estore-test-total"><?php echo esc_html($store_activity_label); ?></th><?php endforeach; ?>
						<th><button type="button" class="ci-estore-status-sort">Status<span><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 15-6-6-6 6"></path></svg><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg></span></button></th>
						<th>Items</th>
						<th></th>
					</tr>
					<?php if (!empty($store_activity_types)) : ?>
						<tr class="ci-estore-activity-row">
							<th colspan="3"></th>
							<th colspan="<?php echo esc_attr(count($store_activity_types)); ?>">3-Month Test Activity</th>
							<th colspan="3"></th>
						</tr>
					<?php endif; ?>
				</thead>
				<tbody>
					<?php if (empty($orders)) : ?><tr><td colspan="<?php echo esc_attr(count($store_activity_types) + 6); ?>" class="ci-estore-no-results">No Supply Store orders have been submitted yet.</td></tr><?php endif; ?>
					<?php foreach ($orders as $index => $order) : ?>
						<tr class="ci-estore-order-row">
							<td><?php echo esc_html($index + 1); ?></td>
							<td><strong><?php echo esc_html($order['clinic_name']); ?></strong><small><?php echo esc_html($order['clinic_address']); ?></small></td>
							<td><?php echo esc_html(date_i18n('Y-m-d g:i A', strtotime($order['created']))); ?></td>
							<?php foreach ($store_activity_types as $store_activity_type => $store_activity_label) : ?>
								<td class="ci-estore-test-total"><?php if (!empty($order['counts'][$store_activity_type])) : ?><span class="ci-estore-activity-tooltip-wrap"><?php echo esc_html($order['counts'][$store_activity_type]); ?><span class="ci-estore-activity-tooltip" role="tooltip"><?php foreach ($order['breakdowns'][$store_activity_type] as $ci_estore_activity_month) : ?><span><b><?php echo esc_html($ci_estore_activity_month['label']); ?></b><em><?php echo esc_html($ci_estore_activity_month['count']); ?></em></span><?php endforeach; ?></span></span><?php else : ?><span class="ci-estore-muted">—</span><?php endif; ?></td>
							<?php endforeach; ?>
							<td><span class="ci-estore-status is-<?php echo esc_attr(strtolower($order['status'])); ?>"><?php echo esc_html($order['status']); ?></span></td>
							<td class="ci-estore-order-items-summary"><?php echo esc_html(implode(', ', wp_list_pluck($order['items'], 'title'))); ?></td>
							<td><button type="button" class="ci-estore-fulfillment-edit" data-order-modal="<?php echo esc_attr($order['ID']); ?>" title="Edit order"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path></svg></button></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php foreach ($orders as $order) : ?>
		<div class="ci-estore-modal" data-order-modal-content="<?php echo esc_attr($order['ID']); ?>" aria-hidden="true">
			<div class="ci-estore-modal-backdrop" data-modal-close></div>
			<form class="ci-estore-modal-dialog" method="post" action="<?php echo esc_url($page_url); ?>">
				<input type="hidden" name="act" value="save_fulfillment">
				<input type="hidden" name="order_id" value="<?php echo esc_attr($order['ID']); ?>">
				<input type="hidden" name="tab" value="admin_store">
				<input type="hidden" name="admin_tab" value="fulfillment">
				<?php wp_nonce_field('ci_estore_save_fulfillment', 'ci_estore_fulfillment_nonce'); ?>
				<header class="ci-estore-modal-head">
					<div class="ci-estore-modal-title">
						<p>Order #<?php echo esc_html($order['ID']); ?> · <?php echo esc_html(date_i18n('Y-m-d g:i A', strtotime($order['created']))); ?></p>
						<h2><?php echo esc_html($order['clinic_name']); ?></h2>
						<span><?php echo esc_html($order['clinic_address']); ?></span>
					</div>
					<div class="ci-estore-modal-head-actions">
						<button type="button" data-modal-close aria-label="Close"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"></path></svg></button>
						<span class="ci-estore-status is-<?php echo esc_attr(strtolower($order['status'])); ?>"><?php echo esc_html($order['status']); ?></span>
					</div>
					<div class="ci-estore-modal-contact">
						<span><?php echo esc_html($order['contact_name']); ?></span>
						<a href="tel:<?php echo esc_attr($order['clinic_phone']); ?>"><?php echo esc_html($order['clinic_phone']); ?></a>
						<?php $ci_estore_contact_email = !empty($order['order_user_email']) ? $order['order_user_email'] : $order['clinic_email']; ?>
						<a href="mailto:<?php echo esc_attr($ci_estore_contact_email); ?>"><?php echo esc_html($ci_estore_contact_email); ?></a>
					</div>
					<div class="ci-estore-modal-counts">
						<?php foreach ($store_activity_types as $count_key => $count_label) : ?><span><b><?php echo esc_html($count_label); ?>:</b> <?php echo !empty($order['counts'][$count_key]) ? esc_html($order['counts'][$count_key]) : '—'; ?></span><?php endforeach; ?>
						<em>3-month test activity</em>
					</div>
				</header>
				<div class="ci-estore-modal-items">
					<div class="ci-estore-modal-columns"><b>Ordered</b><b>Fulfillment</b></div>
					<?php foreach ($order['items'] as $item_index => $order_item) : ?>
						<div class="ci-estore-modal-item<?php echo $item_index % 2 ? ' is-alt' : ''; ?>">
							<div><span><?php echo esc_html($order_item['title']); ?></span><strong><?php echo esc_html($order_item['ordered_qty']); ?></strong></div>
							<div><span><?php echo esc_html($order_item['title']); ?></span><input type="number" name="fulfilled_qty[<?php echo esc_attr($order_item['ID']); ?>]" min="0" value="<?php echo esc_attr($order_item['fulfilled_qty']); ?>"></div>
						</div>
					<?php endforeach; ?>
					<div class="ci-estore-modal-notes">
						<div><b>Clinic notes</b><p><?php echo esc_html($order['order_notes']); ?></p></div>
						<div><b>Lab notes</b><textarea name="lab_notes" rows="2" placeholder="Internal notes..."><?php echo esc_textarea($order['lab_notes']); ?></textarea></div>
					</div>
					<div class="ci-estore-modal-shipping">
						<div></div>
						<div>
							<label>Tracking<span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2M15 18H9M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"></path><circle cx="17" cy="18" r="2"></circle><circle cx="7" cy="18" r="2"></circle></svg><input name="tracking" value="<?php echo esc_attr($order['tracking']); ?>" placeholder="UPS1Z..."></span></label>
							<label>Ship date<input type="date" name="ship_date" value="<?php echo esc_attr($order['ship_date']); ?>"></label>
						</div>
					</div>
				</div>
				<footer class="ci-estore-modal-footer">
					<button type="button" class="ci-estore-secondary" data-modal-close>Cancel</button>
					<div class="ci-estore-status-save">
						<button type="submit" class="ci-estore-modal-save"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 0-1 1v7M7 3v4a1 1 0 0 0 1 1h7"></path></svg>Save as <span class="ci-estore-save-label"><?php echo esc_html($order['status']); ?></span></button>
						<button type="button" class="ci-estore-status-menu-toggle" aria-label="Choose status"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg></button>
						<div class="ci-estore-status-menu"><button type="button" data-status="Pending">Pending</button><button type="button" data-status="Processing">Processing</button><button type="button" data-status="Shipped">Shipped</button></div>
						<input type="hidden" name="status" value="<?php echo esc_attr($order['status']); ?>">
					</div>
				</footer>
			</form>
		</div>
	<?php endforeach; ?>
</div>
