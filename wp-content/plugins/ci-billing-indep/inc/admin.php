<?php
add_action('init', 'trcbi_init_admin', 11);
function trcbi_init_admin()
{
	global $wpdb, $blog_id, $current_lab_id;

	$current_role 	= Tr_Base_Class_V4::get_current_role();
	$admin 			= new Tr_Admin_Page_V6(TRCDBI_PATH);

	$can_access_billing = false;

	if (
		in_array($current_role, array('administrator', 'clms', 'AdminCIDW'))
		|| user_can(wp_get_current_user(), 'billing_admin')
	) {
		$can_access_billing  = true;
	} else if (in_array($current_role, array('sales_accounting', 'Billing', 'BillbyClinic', 'BillbyRefLab'))) {
		$can_access_billing = true;
	} else {
		$can_access_billing = get_user_meta(get_current_user_id(), '_access_billing', true);
	}

	if ($can_access_billing) {
		$admin->add_page('ci_billings', 'Specimen Billing', $current_role, 'default', array('position' => 300, 'icon' => 'dashicons-money-alt'));
	}

	$admin->add_subpage('index.php', 'ci_data_export', 'Data Export', 'ci_data_export');

	if (isset($_REQUEST['install_billing'])) {
		trcdbi_plugin_activate();
		echo 'ook';
		exit;
	}
}



register_activation_hook(TRCDBI_FILE, 'trcdbi_plugin_activate');

function trcdbi_plugin_activate()
{
	include(TRCDBI_PATH . 'inc/install.php');
}

add_action('ci_user_access_checkboxes', 'trcdbi_ci_user_access_checkboxes');
function trcdbi_ci_user_access_checkboxes($profileuser)
{
	global $trcdbi_ci_user_access_checkboxes;

	$trcdbi_ci_user_access_checkboxes = true;

	$user_role 	= $profileuser->roles[0];
	$access 	= get_user_meta($profileuser->ID, '_access_billing', true);

	if (!in_array($user_role, array('sales_accounting', 'Billing', 'administrator', 'AdminCIDW'))) {
		echo ' <label><input type="hidden" name="_access_billing" value="0">
               <input type="checkbox" name="_access_billing" value="1" ' . checked('1', $access, false) . '> Billing</label> ';
	}
}

add_action('edit_user_profile', 'trcdbi_edit_user_profile', 99);
function trcdbi_edit_user_profile($profileuser)
{
	global $trcdbi_ci_user_access_checkboxes;
	if (defined('TRPDD_PATIENT_SITE') && TRPDD_PATIENT_SITE) {
		return;
	}
	global $wpdb;

	$user_role = $profileuser->roles[0];
	$ref_labs = $wpdb->get_results("select * from ci_labs where (lab_is_ref = 1 or lab_is_ref = 2) order by lab_name asc");

	echo '<table class="form-table" id="lab_tbl">';

	if (in_array($user_role, array('Billing', 'BillbyRefLab')) && count($ref_labs) > 0) {
		$ref_lab_id = get_user_meta($profileuser->ID, '_ref_lab_id', true);
		echo '<tr><th>Ref Lab</th><td>';
		echo '<select name="_ref_lab_id">';
		echo '<option value=""></option>';
		foreach ($ref_labs as $ref_lab) {
			$selected = $ref_lab->lab_ID == $ref_lab_id ? 'selected' : '';
			echo '<option value="' . $ref_lab->lab_ID . '" ' . $selected . '>' . $ref_lab->lab_dba_init . ' : ' . $ref_lab->lab_name . '</option>';
		}
		echo '</select>';
		echo '</td></tr>';
	}

	if ($user_role == 'BillbyClinic') {
		wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);

		$clinic_selected = get_user_meta($profileuser->ID, '_bill_cli_IDs', true);
		if (!is_array($clinic_selected)) {
			$clinic_selected = array();
		}
		$clinics = $wpdb->get_results("select cID,cli_clinic from ci_clinic ");
		echo '<tr><th>Clinic (BillbyClinic Role)</th><td>
        <input type="hidden" name="has_bill_cli_IDs" value="1"/>
        <select name="bill_cli_IDs[]" multiple class="js_select2" style="width:100%"><option value=""></option>';
		foreach ($clinics as $cli) {
			$selected = (in_array($cli->cID, $clinic_selected)) ? 'selected' : '';
			echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
		}
		echo '</select></td></tr>';
	} else if (!$trcdbi_ci_user_access_checkboxes && !in_array($user_role, array('sales_accounting', 'Billing', 'administrator', 'AdminCIDW'))) {
		$access = get_user_meta($profileuser->ID, '_access_billing', true);
		$accesses = array(
			'0' => 'No',
			'1' => 'Yes',
		);
?>
		<tr>
			<th>Access Billing</th>
			<td>
				<select name="_access_billing">
					<?php
					foreach ($accesses as $k => $txt) {
						$selected = $k == $access ? 'selected' : '';
						echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
					}
					?>
				</select>
			</td>
		</tr>
<?php

	}

	echo '</table>';
}


add_action('edit_user_profile_update', 'trcdbi_edit_user_profile_update');
function trcdbi_edit_user_profile_update($user_id)
{
	global $wpdb;
	if (isset($_POST['_access_billing'])) {
		update_user_meta($user_id, '_access_billing', $_POST['_access_billing']);
	}

	if (isset($_POST['_ref_lab_id'])) {
		update_user_meta($user_id, '_ref_lab_id', $_POST['_ref_lab_id']);
	}

	if (isset($_POST['has_bill_cli_IDs'])) {
		update_user_meta($user_id, '_bill_cli_IDs', $_POST['bill_cli_IDs']);
		trcdbi_update_exclude_billingcid();
	}
}

add_action('delete_user', 'trcdbi_delete_user');
function trcdbi_delete_user($user_id)
{
	global $wpdb;

	$user_obj = get_userdata($user_id);
	if ($user_obj->roles[0] == 'BillbyClinic') {
		trcdbi_update_exclude_billingcid();
	}
}

function trcdbi_update_exclude_billingcid()
{
	//update exclude list for Billing account
	$cids = array();
	$users = get_users(array('role' => 'BillbyClinic'));
	foreach ($users as $u) {
		$clis = get_user_meta($u->ID, '_bill_cli_IDs', true);
		if (is_array($clis) && count($clis) > 0) {
			$cids = array_merge($cids, $clis);
		}
	}
	array_unique($cids);

	update_option('ci_bill_cli_IDs', $cids);
}

add_action('saved_req', 'trcdbi_saved_req', 9, 3);
function trcdbi_saved_req($btype, $mid, $batch)
{
	global $wpdb;

	if ($mid > 0 && isset($_POST['bb'])) {
		$update = $_POST['bb'];

		$batchtbl = tr_pdd_get_table_by_btype($btype);
		$bat = $wpdb->get_row("select * from {$batchtbl} where mid = {$mid} ");

		$where_exist = " assen = '{$bat->assen}' ";
		if (!empty($bat->claim_id) && tr_billing_get_settings('enable_claim_no')) {
			$where_exist .= " or claim_no = '{$bat->claim_id}' ";
		}


		$exist_id = $wpdb->get_var("select id from ci_batch_billings where {$where_exist}");

		if ($exist_id > 0) {
			$wpdb->update('ci_batch_billings', $update, array('id' => $exist_id));
		} else {
			$update['assen'] = $bat->assen;

			$wpdb->insert('ci_batch_billings', $update);
		}

		if (!empty($update['claim_no'])) {
			$wpdb->update($batchtbl, array('claim_id' => $update['claim_no']), array('mid' => $mid));
		}
	}
}
