<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="ci-estore-tabs ci-estore-admin-tabs" role="tablist" aria-label="Store management">
	<button type="button" class="ci-estore-tab<?php echo $active_admin_tab === 'fulfillment' ? ' is-active' : ''; ?>" data-admin-tab="fulfillment">Fulfillment</button>
	<button type="button" class="ci-estore-tab<?php echo $active_admin_tab === 'products' ? ' is-active' : ''; ?>" data-admin-tab="products">Products</button>
	<?php if ($can_estore_settings) : ?><button type="button" class="ci-estore-tab<?php echo $active_admin_tab === 'settings' ? ' is-active' : ''; ?>" data-admin-tab="settings">Settings</button><?php endif; ?>
	<button type="button" class="ci-estore-tab<?php echo $active_admin_tab === 'stats' ? ' is-active' : ''; ?>" data-admin-tab="stats">Stats</button>
</div>
