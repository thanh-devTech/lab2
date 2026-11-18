<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="ci-estore-admin-panel<?php echo $active_admin_tab === 'stats' ? ' is-active' : ''; ?>" data-admin-panel="stats">
	<div class="ci-estore-stats-layout">
		<div class="ci-estore-stats-grid">
			<article class="ci-estore-stats-metric"><div><p><?php echo esc_html($stats_summary['active']); ?></p><span class="ci-estore-stats-pill is-active">All Active</span></div></article>
			<article class="ci-estore-stats-metric"><div><p><?php echo esc_html($stats_summary['pending']); ?></p><span class="ci-estore-stats-pill is-pending">Pending</span></div></article>
			<article class="ci-estore-stats-metric"><div><p><?php echo esc_html($stats_summary['processing']); ?></p><span class="ci-estore-stats-pill is-processing">Processing</span></div></article>
			<article class="ci-estore-stats-metric"><div><p><?php echo esc_html($stats_summary['shipped']); ?></p><span class="ci-estore-stats-pill is-shipped">Shipped</span></div></article>
		</div>
		<section class="ci-estore-stats-card">
			<header><h2>Supply Orders by Product <span>— last 3 months</span></h2></header>
			<div class="ci-estore-stats-table-wrap">
				<table class="ci-estore-stats-table">
					<thead><tr><th>Product</th><th>Total Ordered</th><th>Orders</th><th>Avg per Order</th></tr></thead>
					<tbody>
						<?php if (empty($stats_product_rows)) : ?><tr><td colspan="4" class="ci-estore-no-results">No product order activity in the last 3 months.</td></tr><?php endif; ?>
						<?php foreach ($stats_product_rows as $row_index => $row) : ?>
							<tr<?php echo $row_index % 2 ? ' class="is-alt"' : ''; ?>>
								<td><?php echo esc_html($row['title']); ?></td>
								<td><?php echo esc_html(absint($row['total_ordered'])); ?></td>
								<td><?php echo esc_html(absint($row['order_count'])); ?></td>
								<td><?php echo esc_html(number_format(absint($row['total_ordered']) / max(1, absint($row['order_count'])), 1)); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
		<section class="ci-estore-stats-card">
			<header><h2>Clinics — Order Summary <span>— last 3 months</span></h2></header>
			<div class="ci-estore-stats-table-wrap">
				<table class="ci-estore-stats-table ci-estore-stats-clinic-table">
					<thead>
						<tr>
							<th>Clinic</th>
							<?php foreach ($store_activity_types as $store_activity_type => $store_activity_label) : ?><th class="ci-estore-test-total"><?php echo esc_html($store_activity_label); ?></th><?php endforeach; ?>
							<th>Supply Items</th>
							<th>Status</th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($stats_clinics)) : ?><tr><td colspan="<?php echo esc_attr(count($store_activity_types) + 3); ?>" class="ci-estore-no-results">No clinic order activity in the last 3 months.</td></tr><?php endif; ?>
						<?php foreach ($stats_clinics as $clinic) : ?>
							<tr>
								<td><strong><?php echo esc_html($clinic['clinic_name']); ?></strong><?php if ($clinic['clinic_address'] !== '') : ?><small><?php echo esc_html($clinic['clinic_address']); ?></small><?php endif; ?></td>
								<?php foreach ($store_activity_types as $store_activity_type => $store_activity_label) : ?>
									<td class="ci-estore-test-total"><?php if (!empty($clinic['counts'][$store_activity_type])) : ?><span class="ci-estore-activity-tooltip-wrap"><?php echo esc_html($clinic['counts'][$store_activity_type]); ?><span class="ci-estore-activity-tooltip" role="tooltip"><?php foreach ($clinic['breakdowns'][$store_activity_type] as $ci_estore_activity_month) : ?><span><b><?php echo esc_html($ci_estore_activity_month['label']); ?></b><em><?php echo esc_html($ci_estore_activity_month['count']); ?></em></span><?php endforeach; ?></span></span><?php else : ?><span class="ci-estore-muted">—</span><?php endif; ?></td>
								<?php endforeach; ?>
								<td><?php echo esc_html($clinic['supply_items']); ?></td>
								<td><span class="ci-estore-status is-<?php echo esc_attr(strtolower($clinic['status'])); ?>"><?php echo esc_html($clinic['status']); ?></span></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
	</div>
</div>
