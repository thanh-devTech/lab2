<?php
global $wpdb, $is_ci_admin;

if (!current_user_can('administrator') && !current_user_can('AdminCIDW')) {
	echo 'Sorry, you are not allowed to access this page.';
	return;
}


if (function_exists('update_site_option')) {
	update_site_option('add_new_users', true);
}


$rolekey        = '';
$all_roles      = get_editable_roles();
$custom_roles   = get_option('ci_custom_roles', array());
$user           = wp_get_current_user();
$act            = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';
$wp_caps        = tr_pdd_core_wp_caps();
$lis_caps       = tr_pdd_lis_caps();
if (!empty($is_ci_admin)) {
	$dashboard_cap_count = 0;
	foreach ($lis_caps as $cap => $arg) {
		if ($cap !== 'ci_dashboard' && strpos($cap, 'ci_dashboard_') !== 0) {
			break;
		}
		$dashboard_cap_count++;
	}
	$lis_caps = array_slice($lis_caps, 0, $dashboard_cap_count, true)
		+ array('ci_data_export' => array('Data Export'))
		+ array_slice($lis_caps, $dashboard_cap_count, null, true);
}
$disabled_option_key = 'disable_role';

$is_admin		= $user->user_login == 'Administrat0r';

$other_options = array(
	$disabled_option_key => array('Disabled'),
	'only_rs_final' => array('Only can view Final Result'),
	'dont_allow_delete' => array('Not allow Delete (req,result)'),
	'manage_antibiotics_pathogens' => array('Manage Antibiotics & Pathogens'),
);
$all_caps = tr_pdd_core_wp_caps();
$all_caps = array_merge($all_caps, $lis_caps);
$other_caps = $other_options;
$all_caps = array_merge($all_caps, $other_caps);

$base_roles_keys = CI_Base_Lab::$base_roles;
$role_user_counts = [];
$role_counts_raw = $wpdb->get_results(
	"SELECT meta_value, COUNT(*) as cnt 
	 FROM {$wpdb->usermeta} 
	 WHERE meta_key = '{$wpdb->prefix}capabilities' 
	 GROUP BY meta_value",
	ARRAY_A
);
foreach ($role_counts_raw as $row) {
	$caps = maybe_unserialize($row['meta_value']);
	if (is_array($caps)) {
		foreach ($caps as $role_key => $enabled) {
			if ($enabled) {
				$role_user_counts[$role_key] = ($role_user_counts[$role_key] ?? 0) + (int) $row['cnt'];
			}
		}
	}
}

function ci_render_role_options($roles, $selected_key, $base_roles_keys, $role_user_counts, $show_optgroup = true)
{
	$active_roles   = [];
	$inactive_roles = [];

	foreach ($roles as $key => $rolename) {
		$is_active = isset($role_user_counts[$key]) && $role_user_counts[$key] > 0;
		$is_custom = !in_array($key, $base_roles_keys);

		$entry = [
			'key'       => $key,
			'label'     => $rolename,
			'is_active' => $is_active,
			'is_custom' => $is_custom,
			'is_disabled' => trpdd_is_role_disabled($key),
		];

		if ($is_active) {
			$active_roles[] = $entry;
		} else {
			$inactive_roles[] = $entry;
		}
	}

	$render_options = function ($list) use ($selected_key) {
		foreach ($list as $entry) {
			$selected   = $entry['key'] === $selected_key ? 'selected' : '';
			$data_attrs = 'data-active="' . ($entry['is_active'] ? '1' : '0') . '"';
			if ($entry['is_custom']) $data_attrs .= ' data-custom="1"';
			if ($entry['is_disabled']) $data_attrs .= ' data-disabled="1"';
			echo '<option value="' . esc_attr($entry['key']) . '" ' . $selected . ' ' . $data_attrs . '>'
				. $entry['label']
				. '</option>';
		}
	};

	if ($show_optgroup) {
		if (!empty($active_roles)) {
			echo '<optgroup label="── Active Roles ──">';
			$render_options($active_roles);
			echo '</optgroup>';
		}
		if (!empty($inactive_roles)) {
			echo '<optgroup label="── Unassigned Roles ──">';
			$render_options($inactive_roles);
			echo '</optgroup>';
		}
	} else {
		$render_options($active_roles);
		$render_options($inactive_roles);
	}
}

if ($act == 'get_caps') {
	$name = $_REQUEST['role'];

	$role = get_role($name);
	$return = array(
		'status' => 'ok',
		'role' => $name,
		'display' => $role->name,
		'caps' => $role->capabilities
	);

	wp_send_json($return);
} else if ($act == 'save_caps') {
	$name = $_REQUEST['role'];
	$caps = isset($_REQUEST['caps']) ? (array) $_REQUEST['caps'] : array();
	$role = get_role($name);
	$was_disabled = isset($role->capabilities[$disabled_option_key]) && $role->capabilities[$disabled_option_key];
	$is_disabled = isset($caps[$disabled_option_key]);

	foreach ($all_caps as $cap => $arg) {
		if (isset($caps[$cap])) {
			$role->add_cap($cap, true);
		} else {
			$role->remove_cap($cap);
		}
	}

	if ($is_disabled && !$was_disabled) {
		if (class_exists('WP_Session_Tokens')) {
			$user_query = new WP_User_Query(array(
				'role__in' => array($name),
				'fields' => 'ids',
				'number' => -1,
			));
			$user_ids = $user_query->get_results();
			if (!empty($user_ids)) {
				foreach ($user_ids as $user_id) {
					$session = WP_Session_Tokens::get_instance($user_id);
					if ($session) {
						$session->destroy_all();
					}
				}
			}
		}
	}

	$logdata = array(
		'role' => $name,
		'caps' => $caps,
		'status_active' => !$is_disabled
	);
	trpdd_log_user(get_current_user_id(), 'save_role', $logdata, $name, '',  '', '', $name);

	$return = array('status' => 'ok');
	wp_send_json($return);
} else if ($act == 'addrole') {
	$rolename = trim($_POST['rolename']);
	$rolekey = $rolename;
	if (stripos($rolename, '|') > 0) {
		list($rolekey, $rolename) = explode('|', $rolename);
	}
	$rolekey = sanitize_key($rolekey);


	$existrole = false;
	foreach ($all_roles as $key => $role) {
		if ($key == $rolekey) {
			$existrole = true;
		}
	}
	if (!$existrole) {
		$baseobj = new Tr_Base_Class_V6();
		$baseobj->create_role($rolekey, $rolename);
		Tr_Session_Class::add('Added new Role Successfully');
		$custom_roles[$rolekey] = $rolename;
		update_option('ci_custom_roles', $custom_roles);
		trpdd_log_user(get_current_user_id(), 'add_role', 'Add successfully role: ' . $rolename, '', '',  '', '', $rolename);
	} else {

		Tr_Session_Class::add_error('Role is exist');
	}
	if (!empty($_POST['baserole'])) {
		$role = get_role($rolekey);
		$baserole = get_role($_POST['baserole']);
		$adding_caps = $baserole->capabilities;
		foreach ($adding_caps as $ck => $bl) {
			if ($bl) {
				$role->add_cap($ck, true);
			}
		}
		update_option('ci_baserole_' . $rolekey, $_POST['baserole'], false);
	} else {
		delete_option('ci_baserole_' . $rolekey);
	}
	$all_roles      = get_editable_roles();
} else if ($act == 'newrole') {
	$roles = array();
	foreach ($all_roles as $key => $role) {
		if (!in_array($key, array('administrator', 'editor', 'author', 'contributor', 'subscriber', 'customer', 'shop_manager', 'clinic'))) {
			$roles[$key] = $role['name'];
		} else if ($user->roles[0] == 'administrator' && isset($_GET['showall'])) {
			$roles[$key] = $role['name'];
		}
	}
	asort($roles);
?>
	<form method="post" id="add_new_role_form">
		<div class="frow">
			<label>Role:</label>
			<input type="text" name="rolename" required />
		</div>
		<div class="frow">
			<label>Base Role</label>
			<select name="baserole" class="njs_select2">
				<option value="">Select Role</option>
				<?php ci_render_role_options($roles, '', $base_roles_keys, $role_user_counts, true); ?>
			</select>
		</div>
		<div class="frow">
			<input type="submit" class="button" value="Add" />
			<input type="hidden" name="act" value="addrole" />
		</div>

	</form>
<?php
	exit;
} else if ($act == 'pdelete' && !empty($_POST['idd'])) {
	$rkey = $_POST['idd'];
	$roleobj = $all_roles[$rkey];
	if (!in_array($rkey, array(
		'administrator',
		'AdminCIDW',
		'administrator_p',
		'BillbyClinic',
		'doctor',
		'lab',
		'lab_rep',
		'lab_rep_w2',
		'lab_sec',
		'lab_tech',
		'labb',
		'labmanager',
		'admin_pcr',
		'office_manager',
		'multi_site_manager',
		'wp_admin',
		'lab_manager',
		'lab_director',
		'results',
		'collector'
	))) {
		remove_role($rkey);

		$all_roles      = get_editable_roles();
		trpdd_log_user(get_current_user_id(), 'delete_role', 'Remove successfully role: ' . $rkey, '', '',  '', '', $rkey);
		Tr_Session_Class::add('Removed role "' . $roleobj['name'] . '" Successfully!');
	} else {
		trpdd_log_user(get_current_user_id(), 'delete_role', 'Remove role: ' . $rkey . 'failed', '', '',  '', '', $rkey);
		Tr_Session_Class::add_error('Cant delete role ' . $roleobj['name']);
	}
}


$roles = array();
foreach ($all_roles as $key => $role) {
	if (!in_array($key, array('administrator', 'editor', 'author', 'contributor', 'subscriber', 'customer', 'shop_manager', 'clinic'))) {
		$roles[$key] = $role['name'];
	} else if ($user->roles[0] == 'administrator' && isset($_GET['showall'])) {
		$roles[$key] = $role['name'];
	}
}
asort($roles);


add_thickbox();
wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);
?>

<div class="wrap">
	<h2>Roles
		<a class="add-new-h2 thickbox" href="<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'newrole')) ?>">Add New Role</a>
	</h2>
	<?php Tr_Session_Class::show() ?>

	<form method="post" id="caps_form">
		<div class="frow box_sticky">
			<div>
				<label>Select Role: </label>
				<select name="role" style="width:400px" class="njs_select2">
					<option value="">Select Role</option>
					<?php ci_render_role_options($roles, '', $base_roles_keys, $role_user_counts, true); ?>
				</select>
				<input type="submit" disabled class="button updatebtn" value="Update"> &nbsp;
				<a disabled class="button-primary updatebtn pdelete">Delete</a>
				<input type="hidden" name="act" value="save_caps">
			</div>
			<div style="margin-top: 4px;">
				Note: [<span style="display: inline-block; width: 8px; height: 8px; border-radius:50%; background: green;"></span>]: Base,
				[<span style="display: inline-block; width: 8px; height: 8px; border-radius:50%; background: #1276e2;"></span>]: Custom
			</div>
		</div>

		<div id="list_caps">
			<div class="frow">
				<div class="col2" <?php if (!$is_admin) echo 'style="display:none"' ?>>
					<h4>Core Capabilities <a class="checkall" href="#">All</a></h4>
					<div class="frow">
						<?php
						$inow = 0;
						$half_caps = ceil(count($wp_caps) / 2) + 2;
						echo '<div class="col2">';
						foreach ($wp_caps as $k => $tmp) {
							$inow++;
							if ($inow == $half_caps) echo '</div><div class="col2">';
							echo '<div>';
							echo '<label>';
							echo '<input type="checkbox" name="caps[' . $k . ']" id="' . $k . '" value="' . $k . '"> ';
							echo $k;
							if (isset($tmp[2])) {
								echo ' ( ' . $tmp[2] . ' )';
							}
							echo '</label>';
							echo '</div>';
						}
						echo '</div>';
						?>
					</div>
				</div>
				<div class="col2">
					<h4>LIS Capabilities <a class="checkall" href="#">All</a></h4>
					<div class="frow">
						<?php
						$inow = 0;
						$incol2 = false;
						$half_caps = ceil(count($lis_caps) / 2);
						echo '<div class="col2">';
						foreach ($lis_caps as $k => $arg) {
							$inow++;

							$header_tag_start = '';
							$header_tag_end = '';
							if (isset($arg['main'])) {
								$header_tag_start = '<b>';
								$header_tag_end = '</b>';
							}

							if ($inow >= $half_caps && !$incol2 && $arg['main']) {
								$incol2 = true;
								echo '</div><div class="col2">';
							}
							echo '<div ' . (isset($arg['main']) ? 'style="margin-top:10px"' : '') . '>';
							echo '<label>';
							echo '<input type="checkbox" name="caps[' . $k . ']" id="' . $k . '" value="' . $k . '"> ';
							echo $header_tag_start;
							echo $arg[0];
							echo $header_tag_end;
							echo '</label>';
							echo '</div>';
						}
						echo '</div>';
						?>
					</div>
				</div>
			</div>
			<div class="frow">
				<h4>Other Options</h4>
				<?php
				foreach ($other_options as $k => $arg) {
					echo '<div>';
					echo '<label>';
					echo '<input type="checkbox" name="caps[' . $k . ']" id="' . $k . '" value="' . $k . '">';
					echo $arg[0];
					echo '</label>';
					echo '</div>';
				}
				?>
			</div>
		</div>
	</form>
</div>

<script>
	jQuery(function($) {
		function ciFormatRole(option) {
			if (!option.id) return option.text;

			var $option = $(option.element);
			var isCustom = $option.data('custom') == '1';
			var $el = $('<span></span>').text(option.text);
			if (isCustom) {
				$el.prepend('<span title="Custom role" style="display:inline-block; width:9px; height:9px; border-radius:50%; background:#1276e2; margin-right:5px; vertical-align:middle;"></span>');
			} else {
				$el.prepend('<span title="Base role" style="display:inline-block; width:9px; height:9px; border-radius:50%; background:green; margin-right:5px; vertical-align:middle;"></span>');
			}
			return $el;
		}

		$('[name="role"], [name="baserole"]').select2({
			templateResult: ciFormatRole,
			templateSelection: ciFormatRole,
			escapeMarkup: function(m) {
				return m;
			}
		});
		var $loading = true;
		$('body').on('change', '[name="role"]', function() {
			if ($(this).val() == '') {
				return;
			}
			$('.pdelete').data('id', $(this).val());
			$loading = true;
			$('#list_caps input').removeAttr('checked');
			$('.updatebtn').attr('disabled', 'disabled');
			$.ajax({
				type: 'post',
				data: {
					trajax: 1,
					act: 'get_caps',
					role: $(this).val()
				},
				dataType: 'json',
				success: function(json) {
					if (json.status == 'ok') {
						for (var i in json.caps) {
							var checked = json.caps[i];
							$('#list_caps input#' + i).prop('checked', checked);
						}

						$loading = false;
						$('.updatebtn').removeAttr('disabled');
					} else {
						setTimeout(function() {
							$('[name="role"]').change();
						}, 1000);
					}
				},
				error: function() {
					setTimeout(function() {
						$('[name="role"]').change();
					}, 1000);
				}
			});
		}).on('submit', '#caps_form', function() {
			if (!$loading && confirm('Please confirm permissions update')) {
				var $btn = $('input[type="submit"]', $(this));
				if ($btn.val() == 'Saving...') return;
				$btn.val('Saving...');
				$.ajax({
					type: 'post',
					data: $(this).serialize() + '&trajax=1',
					dataType: 'json',
					success: function(rs) {
						if (rs.status == 'ok') {
							$btn.val('Saved');
							setTimeout(function() {
								$btn.val('Update');
							}, 3000);
						} else {
							$btn.val('Update');
							alert('Error. Please try again!');
						}
					},
					error: function() {
						$btn.val('Update');
						alert('Error. Please try again!');
					}
				});
			}
			return false;
		}).on('click', 'a.checkall', function() {
			var $pr = $(this).parent().parent();
			if ($(this).data('checked')) {
				$(this).data('checked', false);
				$('input[type="checkbox"]', $pr).removeAttr('checked');
			} else {
				$(this).data('checked', true);
				$('input[type="checkbox"]', $pr).prop('checked', true);
			}
		})

		$('select[name="role"]').change();
	})
</script>