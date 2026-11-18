<?php
global $is_ci_admin;

$types = array(
	0 => 'tox',
	1 => 'dna',
	2 => 'blood',
	3 => 'allergy',
	5 => 'rpp_gi',
	7 => 'wom',
	14 => 'ngs',
	16 => 'pcr',
	17 => 'study',
	18 => 'gene',
	19 => 'req',
);

// Handle AJAX: save btypes + order
if (isset($_POST['trajax']) && $_POST['trajax'] === 'save_btypes') {
	$response = array('success' => false, 'message' => '');

	try {
		$types_order = isset($_POST['types_order']) && $_POST['types_order'] !== ''
			? explode(',', sanitize_text_field($_POST['types_order']))
			: array();

		$ebtypes = isset($_POST['ebtypes']) ? array_map('sanitize_text_field', (array) $_POST['ebtypes']) : array();

		update_option('ci_btypes_order', array(
			'order'   => $types_order,
			'enabled' => $ebtypes,
		));

		if (function_exists('opcache_reset')) {
			opcache_reset();
		}

		$current_user = wp_get_current_user();
		$log_data = json_encode(array(
			'order'   => $types_order,
			'enabled' => $ebtypes,
		));
		trpdd_log_user($current_user->ID, 'change_btypes', $log_data);

		$response['success'] = true;
		$response['message'] = 'Settings saved successfully!';
	} catch (Exception $e) {
		$response['success'] = false;
		$response['message'] = 'Error saving settings: ' . $e->getMessage();
	}

	wp_send_json($response);
	exit();
}

$btypes = tr_pdd_get_batch_types();

$saved = get_option('ci_btypes_order', null);
$types_order = ($saved && isset($saved['order']) && !empty($saved['order']))
	? $saved['order']
	: array_values($types);

?>
<style>
	.type-item {
		padding: 10px;
		margin: 5px 0;
		background: #fff;
		border: 1px solid #ddd;
		border-radius: 3px;
		cursor: move;
		user-select: none;
	}

	.type-item:hover {
		background: #f5f5f5;
	}

	.type-item.ui-sortable-helper {
		opacity: 0.7;
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
	}

	.type-item.ui-sortable-placeholder {
		border-top: 3px solid #2271b1;
		background: #e8f0fb;
		visibility: visible !important;
	}

	.drag-handle {
		display: inline-block;
		margin-right: 10px;
		color: #999;
		cursor: grab;
	}

	.drag-handle:active {
		cursor: grabbing;
	}
</style>

<div class="wrap">
	<h2>Other Settings</h2>
	<?php Tr_Session_Class::show() ?>

	<div id="save-notice" style="display:none;"></div>

	<div id="types-form">
		<?php if ($is_ci_admin): ?>
			<h3>Enable Types</h3>
			<div id="types-list">
				<?php
				foreach ($types_order as $btype) {
					echo '<div class="type-item" data-type="' . esc_attr($btype) . '">';
					echo '<span class="drag-handle">⋮⋮</span>';
					echo '<label>';
					$selected = in_array($btype, $btypes) ? 'checked' : '';
					echo '<input type="checkbox" class="btype-checkbox" value="' . esc_attr($btype) . '" ' . $selected . '/> ' . esc_html($btype);
					echo '</label>';
					echo '</div>';
				}
				?>
			</div>

			<div class="frow" style="margin-top: 15px;">
				<button id="save-btypes" class="button-primary">Submit</button>
			</div>
		<?php endif; ?>
	</div>
</div>

<script>
	jQuery(function($) {
		$('#types-list').sortable({
			handle: '.drag-handle',
			placeholder: 'type-item ui-sortable-placeholder',
			axis: 'y',
			tolerance: 'pointer'
		});

		function getOrder() {
			return $('#types-list .type-item').map(function() {
				return $(this).data('type');
			}).get();
		}

		function getChecked() {
			return $('#types-list .btype-checkbox:checked').map(function() {
				return $(this).val();
			}).get();
		}

		function showNotice(success, message) {
			var cls = success ? 'notice-success' : 'notice-error';
			$('#save-notice')
				.removeClass('notice-success notice-error')
				.addClass('notice is-dismissible ' + cls)
				.html('<p>' + message + '</p>')
				.show();
		}

		$('#save-btypes').on('click', function(e) {
			e.preventDefault();

			var $btn = $(this);
			$btn.prop('disabled', true).text('Saving...');

			$.ajax({
				type: 'POST',
				dataType: 'json',
				data: {
					trajax: 'save_btypes',
					types_order: getOrder().join(','),
					ebtypes: getChecked()
				},
				success: function(res) {
					showNotice(res.success, res.message);
				},
				error: function() {
					showNotice(false, 'An unexpected error occurred. Please try again.');
				},
				complete: function() {
					$btn.prop('disabled', false).text('Submit');
				}
			});
		});
	});
</script>