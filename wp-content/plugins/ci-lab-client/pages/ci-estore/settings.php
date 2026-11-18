<?php if (!defined('ABSPATH')) { exit; } ?>
<?php if ($can_estore_settings) : ?>
	<div class="ci-estore-admin-panel<?php echo $active_admin_tab === 'settings' ? ' is-active' : ''; ?>" data-admin-panel="settings">
		<form class="ci-estore-settings-layout" method="post" action="<?php echo esc_url($page_url); ?>">
			<input type="hidden" name="act" value="save_settings">
			<input type="hidden" name="tab" value="admin_store">
			<input type="hidden" name="admin_tab" value="settings">
			<input type="hidden" name="notification_email" value="<?php echo esc_attr($estore_settings['notification_email']); ?>">
			<input type="hidden" name="order_success_text" value="<?php echo esc_attr($estore_settings['order_success_text']); ?>">
			<?php wp_nonce_field('ci_estore_save_settings', 'ci_estore_settings_nonce'); ?>
			<div class="ci-estore-settings-top-grid">
				<div class="ci-estore-settings-card">
					<header>
						<h2>Contact Info</h2>
						<p>Displayed to clinics in the Supply Store help area.</p>
					</header>
					<div class="ci-estore-settings-content">
						<div class="ci-estore-settings-field">
							<label for="ci-estore-support-phone">Phone</label>
							<input id="ci-estore-support-phone" name="support_phone" type="text" placeholder="800-555-0100" value="<?php echo esc_attr($estore_settings['support_phone']); ?>">
						</div>
						<div class="ci-estore-settings-field">
							<label for="ci-estore-support-email">Email</label>
							<input id="ci-estore-support-email" name="support_email" type="email" placeholder="supplies@yourlab.com" value="<?php echo esc_attr($estore_settings['support_email']); ?>">
						</div>
					</div>
				</div>
				<div class="ci-estore-settings-card">
					<header>
						<h2>Store Email</h2>
						<p>Emails sent to clinics will come from this address.</p>
					</header>
					<div class="ci-estore-settings-content">
						<div class="ci-estore-settings-grid">
							<div class="ci-estore-settings-field">
								<label for="ci-estore-from-email">From address</label>
								<input id="ci-estore-from-email" type="email" value="<?php echo esc_attr($estore_settings['from_email']); ?>" readonly>
								<small>Email messages are sent from this IMT-managed address.</small>
							</div>
							<div class="ci-estore-settings-field">
								<label for="ci-estore-reply-to-email">Reply-to address</label>
								<input id="ci-estore-reply-to-email" name="reply_to_email" type="email" placeholder="noreply@yourlab.com" value="<?php echo esc_attr($estore_settings['reply_to_email']); ?>">
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="ci-estore-settings-card">
				<header>
					<h2>Email Templates</h2>
					<p>Customize the emails sent when an order status changes.</p>
				</header>
				<div class="ci-estore-settings-content">
					<div class="ci-estore-template-tabs" role="tablist" aria-label="Email templates">
						<button type="button" class="is-active" data-template-tab="received">Order Received</button>
						<button type="button" data-template-tab="shipped">Order Shipped</button>
					</div>
					<div class="ci-estore-template-panel is-active" data-template-panel="received">
						<div class="ci-estore-settings-field">
							<label for="ci-estore-email-subject">Subject line</label>
							<input id="ci-estore-email-subject" name="email_subject" type="text" value="<?php echo esc_attr($estore_settings['email_subject']); ?>">
						</div>
						<div class="ci-estore-settings-field">
							<label for="ci-estore-email-body">Body</label>
							<textarea id="ci-estore-email-body" name="email_body" rows="12"><?php echo esc_textarea($estore_settings['email_body']); ?></textarea>
						</div>
					</div>
					<div class="ci-estore-template-panel" data-template-panel="shipped">
						<div class="ci-estore-settings-field">
							<label for="ci-estore-shipped-subject">Subject line</label>
							<input id="ci-estore-shipped-subject" name="order_shipped_subject" type="text" value="<?php echo esc_attr($estore_settings['order_shipped_subject']); ?>">
						</div>
						<div class="ci-estore-settings-field">
							<label for="ci-estore-shipped-body">Body</label>
							<textarea id="ci-estore-shipped-body" name="order_shipped_body" rows="12"><?php echo esc_textarea($estore_settings['order_shipped_body']); ?></textarea>
						</div>
					</div>
					<div class="ci-estore-template-variables">
						<p>Available variables</p>
						<div>
							<span>{order_id}</span>
							<span>{clinic_name}</span>
							<span>{rep_name}</span>
							<span>{order_date}</span>
							<span>{order_items}</span>
							<span>{tracking_number}</span>
							<span>{ship_date}</span>
						</div>
					</div>
				</div>
			</div>
			<div class="ci-estore-settings-actions"><button type="submit" class="ci-estore-primary"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path><path d="M7 3v4a1 1 0 0 0 1 1h7"></path></svg>Save Settings</button></div>
		</form>
	</div>
<?php endif; ?>
