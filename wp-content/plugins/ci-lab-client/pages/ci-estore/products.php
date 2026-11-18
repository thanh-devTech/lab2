<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="ci-estore-admin-panel<?php echo $active_admin_tab === 'products' ? ' is-active' : ''; ?>" data-admin-panel="products">
	<div class="ci-estore-products-toolbar">
		<?php if (empty($products)) : ?><form class="ci-estore-import-demo-form" method="post" action="<?php echo esc_url($page_url); ?>"><input type="hidden" name="act" value="import_demo_products"><input type="hidden" name="tab" value="admin_store"><input type="hidden" name="admin_tab" value="products"><?php wp_nonce_field('ci_estore_import_demo_products', 'ci_estore_import_demo_nonce'); ?><button type="submit" class="ci-estore-secondary ci-estore-import-demo"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="m7 10 5 5 5-5"></path><path d="M12 15V3"></path></svg>Import Sample Products</button></form><?php endif; ?>
		<button type="button" class="ci-estore-primary ci-estore-open-product"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M12 5v14"></path></svg>Add Product</button>
	</div>
	<div class="ci-estore-product-form<?php echo $action === 'edit' || !empty($notice) ? ' is-open' : ''; ?>">
		<h2><?php echo $product['ID'] > 0 ? 'Edit Product' : 'New Product'; ?></h2>
		<form method="post" action="<?php echo esc_url($page_url); ?>" enctype="multipart/form-data" autocomplete="off">
			<input type="hidden" name="act" value="save">
			<input type="hidden" name="id" value="<?php echo esc_attr($product['ID']); ?>">
			<input type="hidden" name="image" value="<?php echo esc_url($product['image']); ?>">
			<input type="hidden" name="tab" value="admin_store">
			<input type="hidden" name="admin_tab" value="products">
			<?php wp_nonce_field('ci_estore_save', 'ci_estore_nonce'); ?>
				<div class="ci-estore-form-grid">
					<div><label for="ci-estore-title">Product Name</label><input id="ci-estore-title" name="title" type="text" placeholder="e.g. Gray Top Tubes" required value="<?php echo esc_attr($product['title']); ?>"></div>
					<div><label for="ci-estore-sku">SKU / Part Number</label><input id="ci-estore-sku" name="sku" type="text" placeholder="e.g. BD364951Z" value="<?php echo esc_attr($product['sku']); ?>"></div>
					<div class="ci-estore-form-wide"><label for="ci-estore-description">Description</label><input id="ci-estore-description" name="description" type="text" placeholder="Pack size, type, usage..." value="<?php echo esc_attr(wp_strip_all_tags($product['description'])); ?>"></div>
					<div><label for="ci-estore-category">Category</label><select id="ci-estore-category" name="category" required<?php echo empty($store_categories) ? ' disabled' : ''; ?>><?php if (empty($store_categories)) : ?><option value="">No LIS test types are enabled</option><?php else : ?><?php foreach ($store_categories as $category_key => $category_label) : ?><option value="<?php echo esc_attr($category_key); ?>"<?php selected($product['category'], $category_key); ?>><?php echo esc_html($category_label); ?></option><?php endforeach; ?><?php endif; ?></select></div>
					<div><label for="ci-estore-max-qty">Max Qty per Order</label><input id="ci-estore-max-qty" name="max_qty" type="number" min="1" step="1" required value="<?php echo esc_attr($product['max_qty']); ?>"></div>
					<div class="ci-estore-form-wide"><label for="ci-estore-image">Product Image</label><div class="ci-estore-product-image-field"><div class="ci-estore-product-image-preview"><?php if ($product['image'] !== '') : ?><img src="<?php echo esc_url($product['image']); ?>" alt="<?php echo esc_attr($product['title']); ?>"><?php else : ?><span class="dashicons dashicons-format-image"></span><?php endif; ?></div><label class="ci-estore-image-upload-button" for="ci-estore-image"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"></path><path d="M12 22V12"></path><polyline points="3.29 7 12 12 20.71 7"></polyline><path d="m7.5 4.27 9 5.15"></path></svg><?php echo $product['image'] !== '' ? 'Replace image' : 'Upload image'; ?><input id="ci-estore-image" name="image_file" type="file" accept="image/*"></label></div></div>
				</div>
			<div class="ci-estore-form-actions"><button type="submit" class="ci-estore-primary"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zM17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7M7 3v4a1 1 0 0 0 1 1h7"></path></svg><?php echo $product['ID'] > 0 ? 'Update Product' : 'Save Product'; ?></button><a href="<?php echo esc_url($admin_url); ?>" class="ci-estore-secondary">Cancel</a></div>
		</form>
	</div>
	<div class="ci-estore-table-card">
			<div class="ci-estore-table-scroll">
				<table class="ci-estore-table ci-estore-product-table">
					<thead><tr><th></th><th>Product</th><th>SKU</th><th>Category</th><th>Max Qty</th><th></th></tr></thead>
					<tbody class="ci-estore-product-sortable">
						<?php if (empty($products)) : ?><tr><td colspan="6" class="ci-estore-no-results"><strong>No products have been added yet.</strong><span>Import sample products from the demo store or add your first product manually.</span></td></tr><?php endif; ?>
						<?php foreach ($products as $item) : ?>
							<tr class="ci-estore-product-row" draggable="true" data-product-id="<?php echo esc_attr($item['ID']); ?>">
								<td><span class="ci-estore-drag-handle" aria-label="Drag to reorder"><svg width="12" height="18" viewBox="0 0 12 18" fill="currentColor" aria-hidden="true"><circle cx="3" cy="3" r="1.5"></circle><circle cx="9" cy="3" r="1.5"></circle><circle cx="3" cy="9" r="1.5"></circle><circle cx="9" cy="9" r="1.5"></circle><circle cx="3" cy="15" r="1.5"></circle><circle cx="9" cy="15" r="1.5"></circle></svg></span></td>
								<td><strong><?php echo esc_html($item['title']); ?></strong><small><?php echo esc_html(wp_strip_all_tags($item['description'])); ?></small></td>
								<td class="ci-estore-product-sku"><?php echo esc_html($item['sku']); ?></td>
							<td><span class="ci-estore-category" data-category="<?php echo esc_attr(strtolower($item['category'] !== '' ? $item['category'] : 'general supplies')); ?>"><?php echo esc_html($item['category'] !== '' ? $store_category_label($item['category']) : 'General supplies'); ?></span></td>
							<td><?php echo esc_html($item['max_qty']); ?></td>
							<td><a class="ci-estore-icon-button" title="Edit product" href="<?php echo esc_url(add_query_arg(array('act' => 'edit', 'id' => $item['ID']), $admin_url)); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2 2v-7M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"></path></svg></a><a class="ci-estore-icon-button is-delete ci-estore-delete" title="Delete product" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('act' => 'delete', 'id' => $item['ID']), $admin_url), 'ci_estore_delete_' . $item['ID'])); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6M8 6V4c0-1 1-2 2-2h4c1 0 1 2 2 2v2M10 11v6M14 11v6"></path></svg></a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				</table>
			</div>
			<form class="ci-estore-product-order-form" method="post" action="<?php echo esc_url($page_url); ?>"><input type="hidden" name="act" value="save_product_order"><input type="hidden" name="tab" value="admin_store"><input type="hidden" name="admin_tab" value="products"><input type="hidden" name="product_order" value=""><?php wp_nonce_field('ci_estore_save_product_order', 'ci_estore_order_nonce'); ?></form>
		</div>
	</div>
