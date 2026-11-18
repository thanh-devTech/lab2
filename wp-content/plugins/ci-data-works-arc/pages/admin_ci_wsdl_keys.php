<?php
global $wpdb, $ci_all_btypes;


//check db version
$db_version = get_option('TRCPDD_VS');
if ($db_version != TRCPDD_VS) {
	trpdd_plugin_activate();
}
include_once(TRCPDD_PATH . 'api/ci_hl7_parser.php');
include(TRCPDD_PATH . 'inc/define_apis.php');
include(TRCPDD_PATH . 'inc/define_instruments.php');
include_once(TRCPDD_PATH . 'pages/formdata.php');


$act    = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';
$id     = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';

global $is_ci_admin;

if (!$is_ci_admin) {
	if (!current_user_can('ci_dashboard_system') || !in_array($act, array('log', 'view_xml_request'))) {
		wp_die('You do not have permission to access this page.');
		return;
	}
}

$show_adv = isset($_GET['debug']) || isset($_REQUEST['view']) || isset($_COOKIE['trquery_monitor']);

if ($act == 'view_xml_request') {

	$key_id 	= $_REQUEST['wl_ID'];
	$type 		= $_REQUEST['wl_type'];
	$assen 		= $_REQUEST['wl_key'];
	$wl_at 		= $_REQUEST['wl_at'];
	$sqlrow 	= "select * from ci_wsdl_log where wl_ID={$key_id} and wl_type='{$type}' and wl_key='{$assen}' ";
	$sqlrow 	.= " and wl_at = '{$wl_at}' ";
	$row 		= $wpdb->get_row($sqlrow);


	if (!empty($row->wl_datain)) {
		echo '<h2>Request Raw</h2>';
		echo '<textarea style="width:100%;height:300px">' . html_entity_decode($row->wl_datain) . '</textarea>';
	}

	echo '<h2>Request Parse:</h2>';
	echo '<textarea style="width:100%;height:300px">' . html_entity_decode($row->wl_data) . '</textarea>';
	echo '<h2>Response</h2>';
	echo '<textarea style="width:100%;height:300px">' . html_entity_decode($row->wl_dataout) . '</textarea>';
	exit;
}

$tabs = array(
	'webservice' => array(
		'name' => 'WebServices',
		'subs' => array(
			'server' => 'Server',
			'client' => 'Client'
		)
	),
	'fax_sms' => array(
		'name' => 'Fax and SMS',
		'subs' => array(
			//'box' => 'Box',
			'api_ringcentral' => 'RingCentral',
			'api_faxage' => 'Faxage',
			'sms_notification' => 'SMS Notification',
		)
	),
	'instrument' => array(
		'name' => 'Instruments',
		'subs' => array()
	),
	'other' => array(
		'name' => 'EMR/EHR\'s',
		'subs' => array()
	),
);

foreach ($define_instruments as $k => $arr) {
	if (tr_get_option('ci_pdf_data', $k)) {
		$tabs['instrument']['subs'][$k] = $arr['name'];
	}
}

foreach ($define_apis as $k => $arr) {
	if (tr_get_option('ci_pdf_data', $k)) {
		$tabs['other']['subs'][$k] = $arr['name'];
	}
}

if (tr_get_option('ci_pdf_data', 'fax_api') == '') {
	unset($tabs['fax_sms']['subs']['api_ringcentral']);
	unset($tabs['fax_sms']['subs']['api_faxage']);
} else if (tr_get_option('ci_pdf_data', 'fax_api') == 'CI_Fax_Faxage') {
	unset($tabs['fax_sms']['subs']['api_ringcentral']);
} else {
	unset($tabs['fax_sms']['subs']['api_faxage']);
}
if (!tr_get_option('ci_pdf_data', 'sms_notification')) {
	unset($tabs['fax_sms']['subs']['sms_notification']);
}

foreach ($tabs as $tabk => $arr) {
	if (isset($arr['subs'])) {
		foreach ($arr['subs'] as $stabk => $subarr) {
			if (
				!in_array($stabk, array('server', 'client', 'api_ringcentral', 'api_faxage'))
				&& !tr_get_option('ci_pdf_data', $stabk)
			) {
				unset($arr['subs'][$stabk]);
			}
		}
		$tabs[$tabk]['subs'] = $arr['subs'];
		if (count($arr['subs']) == 0) {
			unset($tabs[$tabk]);
		}
	} else if (
		!in_array($tabk, array('server', 'client', 'api_ringcentral', 'api_faxage'))
		&& !tr_get_option('ci_pdf_data', $tabk)
	) {
		unset($tabs[$tabk]);
	}
}


if (tr_get_option('ci_pdf_data', 'api_au680_blood') && isset($tabs['instrument']['subs']['api_au680'])) {
	$tabs['instrument']['subs']['api_au680'] = 'AU680 Tox';
}

$tab    = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);
if (!isset($tabs[$tab])) {
	$tab = key($tabs);
}
$subtabs = isset($tabs[$tab]['subs']) && count($tabs[$tab]['subs']) > 0 ? $tabs[$tab]['subs'] : array();

$subtab = isset($_REQUEST['subtab']) ? $_REQUEST['subtab'] : key($subtabs);

if (empty($subtabs)) {
	$subtab = '';
}
if (!empty($subtab) && !isset($subtabs[$subtab])) {
	$subtab = key($tabs);
}

if (!isset($_REQUEST['trajax'])) {
?>
	<h2 class="nav-tab-wrapper">
		<?php

		foreach ($tabs as $k => $arr) {
			$tabname = is_array($arr) ? $arr['name'] : $arr;
			$class = $tab == $k ? 'nav-tab-active' : '';
			$link  = Tr_Base_Class_V6::link(array(
				'tab'   => $k,
				'subtab'   => '',
				'act'   => '',
				'id'    => '',
				'date'  => '',
				'paged' => '',
				'mt'    => ''
			), false);
			echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $tabname . '</a>';
		}
		?>
	</h2>

	<?php
	if (isset($tabs[$tab]['subs']) && count($tabs[$tab]['subs']) > 0) {
	?>
		<h2 class="nav-tab-wrapper">
			<?php
			foreach ($tabs[$tab]['subs'] as $k => $arr) {
				$tabname = is_array($arr) ? $arr['name'] : $arr;
				$class = $subtab == $k ? 'nav-tab-active' : '';
				$link  = Tr_Base_Class_V6::link(array(
					'subtab'   => $k,
					'act'   => '',
					'id'    => '',
					'date'  => '',
					'paged' => '',
					'mt'    => ''
				), false);
				echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $tabname . '</a>';
			}
			?>
		</h2>
		<?php
	}
}

$tabfile = '';
if (!empty($subtab)) {
	if (isset($tabs[$tab]['subs'][$subtab]['file']) && file_exists($tabs[$tab]['subs'][$subtab]['file'])) {
		$tabfile = $tabs[$tab]['subs'][$subtab]['file'];
	} else {
		if ($subtab == 'api_billing_sftp') {
			$subtab = 'api_telcor_billing_sftp';
		} else if ($subtab == 'api_instrument_architect') {
			$subtab = 'instrument_hl7_architect';
		}

		$tabfile = trpdd_get_file_path('instrument', $subtab);

		if (!file_exists($tabfile)) {
			$subtab = str_replace('ci_', '', $subtab);
			$tabfile = trpdd_get_file_path('instrument', $subtab);
		}
	}
} else {
	if (isset($tabs[$tab]['file']) && file_exists($tabs[$tab]['file'])) {
		$tabfile = $tabs[$tab]['file'];
	} else {
		$tabfile = trpdd_get_file_path('instrument', $subtab);
	}
}


if (!isset($tabs[$tab])) {
	return;
} else if (!empty($tabfile) && file_exists($tabfile)) {

	include($tabfile);
} else if ($tab == 'webservice') {


	$types = array('' => 'All');
	$types['json'] = 'JSon API(all types)';
	foreach (tr_pdd_get_batch_types() as $btype) {
		$types['json_' . $btype] = 'JSon API - ' . tr_pdd_get_typename($btype);
	}
	$types['pdf'] =  'Only PDF Result';
	$types['ref'] =  'Only Ref';
	$types['getrba'] =  'Only GET Req by Accession';
	$types['blood_covid'] =  'Only Blood Covid';
	$types['covid'] =  'Only PCR Covid Tests';
	$types['acovid'] =  'Only Antigen & PCR Covid Tests';
	$types['ropcr'] =  'Only PCR ReopenLabs';
	$types['powerbi'] =  'Only PCR PowerBI Results';


	foreach (tr_pdd_get_batch_types() as $btype) {
		$types[$btype] = 'Only ' . tr_pdd_get_typename($btype);
		$types['put_req_' . $btype] = 'Only put_req_' . $btype;
		$types['put_result_' . $btype] = 'Only put_result_' . $btype;
	}
	$types['mha'] = 'Medhiwa';


	if ($subtab == 'client') {
		include('admin_ci_webservice_client.php');
	} else {
		$where = " and (k.wsdl_url='' or k.wsdl_url is null) ";

		if (isset($_POST['key'])) {
			$_POST  = tr_pdd_stripslashes_deep($_POST);
			$key    = $_POST['key'];
			$clinics = is_array($_POST['cli_IDs']) ? $_POST['cli_IDs'] : array();

			$key_id = $key['wsdl_ID'];
			if ($key_id > 0) {
				$rs = $wpdb->update('ci_wsdl_keys', $key, array('wsdl_ID' => $key_id));
				if ($rs === false) {
					Tr_Session_Class::add_error('Error: ' . $wpdb->last_error);
				} else {
					Tr_Session_Class::add('Updated Key Successfully!');
				}
			} else {
				$rs = $wpdb->insert('ci_wsdl_keys', $key);
				$key_id = $wpdb->insert_id;
				if ($key_id > 0) {
					Tr_Session_Class::add('Created New Key Successfully!');
					$key = array();
				} else {
					Tr_Session_Class::add_error('Error: ' . $wpdb->last_error);
				}
				$_POST = array();
			}

			if ($key_id > 0) {
				$wpdb->delete('ci_wsdl_key_clinics', array('wsdl_ID' => $key_id));
				foreach ($clinics as $cli_ID) {
					$wpdb->insert('ci_wsdl_key_clinics', array('wsdl_ID' => $key_id, 'cli_ID' => $cli_ID));
				}
			}
		}

		if ($act == 'delete') {
			$wpdb->delete('ci_wsdl_keys', array('wsdl_ID' => $id));
			$wpdb->delete('ci_wsdl_key_clinics', array('wsdl_ID' => $id));

			Tr_Session_Class::add('Deleted Key Successfully!');
		}

		if ($act == 'edit' || $act == 'add') {

			$cli_IDs = array();
			if ($id > 0 && $act == 'edit') {
				$key        = $wpdb->get_row("select * from ci_wsdl_keys where wsdl_ID = {$id}", ARRAY_A);
				$cli_IDs    = $wpdb->get_col("select cli_ID from ci_wsdl_key_clinics where wsdl_ID = {$id}");
			} else {
				$key        = array();
			}


			$clinics    = $wpdb->get_results("select * from ci_clinic where cli_clinic !='' order by cli_clinic");
			$groups     = $wpdb->get_results("select * from ci_user_groups order by gr_name asc ");
			$labs       = $wpdb->get_results("select * from ci_labs where (lab_is_ref = 0 or lab_is_ref = 2) order by lab_dba_init asc");
			$ref_labs   = $wpdb->get_results("select * from ci_labs where (lab_is_ref = 1 or lab_is_ref = 2)  order by lab_dba_init asc");
			$ref_labs[] = (object) array(
				'lab_ID' => -1,
				'lab_name' => 'has ref Lab'
			);
		?>
			<div class="wrap">
				<h2><?php echo ($act == 'add') ? 'Add New' : 'Edit' ?> Key <a href="<?php Tr_Base_Class_V6::link(array('act' => '', 'id' => '')) ?>" class="add-new-h2">All Keys</a> </h2>
				<?php Tr_Session_Class::show() ?>

				<form method="post">
					<div class="frow blabel">
						<div class="col2">
							<label>Key <a class="generate_key ">Generate</a></label>
							<input type="text" name="key[wsdl_key]" value="<?php echo isset($key['wsdl_key']) ? $key['wsdl_key'] : '' ?>" maxlength="50" required />
						</div>
						<div class="col2">
							<label>Url</label>
							<input type="text" style="width:100%;border:0" readonly value="<?php bloginfo('url') ?>/wsdl?wsdl" onClick="this.select();" />
						</div>
					</div>
					<div class="frow blabel">
						<div class="col2">

							<div class="frow">
								<label>Clinic</label>
								<select name="cli_IDs[]" multiple required style="height:200px" class="js_select2_tag">
									<?php
									$selected = in_array('-1', $cli_IDs) ? 'selected' : '';
									echo '<option value="-1" ' . $selected . '>All Clinics</option>';
									foreach ($clinics as $cli) {
										$selected = in_array($cli->cID, $cli_IDs) ? 'selected' : '';
										echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
									}
									?>
								</select>
							</div>
							<div class="frow">
								<label>Group</label>
								<select name="key[wsdl_user_group_ID]" class="njs_select2">
									<option value="">Select Group</option>
									<?php

									foreach ($groups as $g) {
										$selected = $g->gr_ID == $key['wsdl_user_group_ID'] ? 'selected' : '';
										echo '<option value="' . $g->gr_ID . '" ' . $selected . '>' . $g->gr_name . '</option>';
									}
									?>
								</select>
							</div>
							<div class="frow">
								<label>Lab</label>
								<select name="key[wsdl_lab_ID]" class="njs_select2">
									<option value="">Select Lab</option>
									<?php
									foreach ($labs as $l) {
										$selected = isset($key['wsdl_lab_ID']) && $l->lab_ID == $key['wsdl_lab_ID'] ? 'selected' : '';
										echo '<option value="' . $l->lab_ID . '" ' . $selected . '>' . $l->lab_dba_init . '  - ' . $l->lab_name . '</option>';
									}
									?>
								</select>
							</div>
							<div class="frow">
								<label>Ref Lab</label>
								<select name="key[wsdl_ref_lab_ID]">
									<option value="">Select Ref Lab</option>
									<?php
									foreach ($ref_labs as $l) {
										$selected = isset($key['wsdl_ref_lab_ID']) && $l->lab_ID == $key['wsdl_ref_lab_ID'] ? 'selected' : '';
										echo '<option value="' . $l->lab_ID . '" ' . $selected . '>' . $l->lab_dba_init . ' - ' . $l->lab_name . '</option>';
									}
									?>
								</select>
							</div>
							<div class="frow">
								<label>Start Date</label>
								<input type="text" name="key[wsdl_start]" value="<?php if (isset($key['wsdl_start']) && $key['wsdl_start'] != '0000-00-00') echo $key['wsdl_start'] ?>" class="hasdate" data-write="1" />
							</div>
						</div>
						<div class="col2">
							<div class="frow">
								<label>Access</label>
								<select name="key[wsdl_type]" class="njs_select2">
									<?php
									foreach ($types as $k => $vl) {
										$selected = isset($key['wsdl_type']) && $k == $key['wsdl_type'] ? 'selected' : '';
										echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
									}
									?>
								</select>
							</div>

							<div class="frow">
								<label>Notes</label>
								<textarea name="key[wsdl_desc]"><?php echo isset($key['wsdl_desc']) ? $key['wsdl_desc'] : '' ?></textarea>
							</div>
							<div class="frow">
								<input type="hidden" name="key[wsdl_fax_result]" value="0" />
								<label><input type="checkbox" name="key[wsdl_fax_result]" value="1" <?php checked(isset($key['wsdl_fax_result']) ? $key['wsdl_fax_result'] : '') ?> /> Auto Fax Result</label>
							</div>
							<?php if ($show_adv) { ?>
								<div class="frow">
									<input type="hidden" name="key[wsdl_sync_clinic]" value="0" />
									<label><input type="checkbox" name="key[wsdl_sync_clinic]" value="1" <?php checked(isset($key['wsdl_sync_clinic']) ? $key['wsdl_sync_clinic'] : '') ?> /> Allow Sync Clinic</label>
								</div>
								<div class="frow only_tox only_put_req_tox">
									<input type="hidden" name="key[wsdl_dont_reflex]" value="0" />
									<label><input type="checkbox" name="key[wsdl_dont_reflex]" value="1" <?php checked(isset($key['wsdl_dont_reflex']) ? $key['wsdl_dont_reflex'] : '') ?> /> Don't Reflex</label>
								</div>
								<div class="frow only_tox only_put_req_tox">
									<input type="hidden" name="key[wsdl_dont_approve]" value="0" />
									<label><input type="checkbox" name="key[wsdl_dont_approve]" value="1" <?php checked(isset($key['wsdl_dont_approve']) ? $key['wsdl_dont_approve'] : '') ?> /> Auto Reverse</label>
								</div>
								<div class="frow">
									<input type="hidden" name="key[wsdl_dont_send_screening]" value="0" />
									<label><input type="checkbox" name="key[wsdl_dont_send_screening]" value="1" <?php checked(isset($key['wsdl_dont_send_screening']) ? $key['wsdl_dont_send_screening'] : '') ?> /> Screening only to show</label>
								</div>
							<?php } ?>
						</div>
					</div>
					<div class="frow">
						<input type="submit" class="button-primary" value="Submit">
						<?php if ($act == 'edit') { ?>
							<a class="button thickbox" href="<?php echo esc_url(admin_url('admin.php?page=ci_pdf_data&pagetab=ci_wsdl_keys&tab=webservice&subtab=server&debug&act=log&id=' . $id . '&trajax=1&width=1200&height=700')) ?>">Log</a>
							<script>
								jQuery(function($) {
									$(document)
										.on('click', '#TB_ajaxContent .ci-wsdl-log-box .tablenav a', function(e) {
											e.preventDefault();
											$('#TB_ajaxContent').load(this.href);
										})
										.on('submit', '#TB_ajaxContent .ci-wsdl-log-box form', function(e) {
											e.preventDefault();
											$('#TB_ajaxContent').load(this.action + '?' + $(this).serialize());
										});
								});
							</script>
						<?php } ?>
					</div>
					<input type="hidden" name="key[wsdl_ID]" value="<?php echo isset($key['wsdl_ID']) ? $key['wsdl_ID'] : '' ?>" />
				</form>
			</div>


		<?php
		} else if ($act == 'log') {

			$method = isset($_REQUEST['mt']) ? $_REQUEST['mt'] : '';
			$where = " wl_ID = {$id} ";

			$date = tr_pdd_display_date($_REQUEST['d'], 'Y-m-d');
			if (!empty($_REQUEST['d'])) {
				$where .= " and wl_at >= '{$date}' and wl_at <= '{$date} 23:59:59'";
			}

			if (!empty($_REQUEST['s'])) {
				$where .= " and wl_key ='{$_REQUEST['s']}' ";
			}

			if (!empty($method)) {
				$where .= " and wl_type='{$method}' ";
			}

			$row_per_page = 100;
			$pagination = new Tr_Pagination_class2($row_per_page);
			$pagination->setTotal($wpdb->get_var("select count(*) as c from ci_wsdl_log k where {$where}"));
			$offset = $pagination->offset();

			$sql = "select * from ci_wsdl_log where {$where}
            order by wl_at desc
            limit {$offset},{$row_per_page}";


			$methods = $wpdb->get_col("select distinct(wl_type) from ci_wsdl_log");
			if (isset($_GET['debug'])) {
				var_dump($sql);
			}
			$rs = $wpdb->get_results($sql);
		?>
			<div class="ci-wsdl-log-box">
				<h2>Log <small><?php echo date_i18n('Y-m-d H:i:s') ?></small></h2>
				<form method="get" action="<?php echo esc_url(admin_url('admin.php')) ?>">

					<input type="search" name="s" value="<?php echo $_REQUEST['s'] ?>" placeholder="Accession" />
					<input type="date" class="hasdate" name="d" value="<?php echo $_REQUEST['d'] ?>" placeholder="Date" />
					<select name="mt" class="njs_select2">
						<option value="">Select Function</option>
						<?php
						foreach ($methods as $mt) {
							$selected = ($mt == $method) ? 'selected' : '';
							echo '<option value="' . $mt . '" ' . $selected . '>' . $mt . '</option>';
						}
						?>
					</select>
					<input type="submit" value="Filter" class="button" />
					<input type="hidden" name="page" value="<?php echo $_GET['page'] ?>">
					<input type="hidden" name="pagetab" value="<?php echo $_GET['pagetab'] ?>">
					<input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>">
					<input type="hidden" name="subtab" value="<?php echo $_GET['subtab'] ?>">
					<input type="hidden" name="act" value="<?php echo $_GET['act'] ?>">
					<input type="hidden" name="id" value="<?php echo $_GET['id'] ?>">
					<?php if (isset($_GET['trajax'])) { ?>
						<input type="hidden" name="trajax" value="1">
					<?php } ?>
					<?php if (isset($_GET['debug'])) { ?>
						<input type="hidden" name="debug" value="1">
					<?php } ?>
					<input type="hidden" name="paged" value="0">
				</form>

				<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
				<div class="wraptbl">
					<table class="wp-list-table widefat striped pages blue_tbl hover_tbl">
						<thead>
							<tr>
								<th>Function</th>
								<th>Accession</th>
								<th>Date</th>
								<th>Count</th>
								<th>IP</th>
								<th>Log</th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ($rs as $row) {

								if (isset($_GET['debug1'])) {
									$wpdb->update('ci_reports', array('re_import_ID' => time()), array('patient_id' => $row->wl_key));
								}
							?>
								<tr>
									<td><?php echo $row->wl_type ?></td>
									<td><?php echo $row->wl_key ?></td>
									<td><?php echo $row->wl_at ?></td>
									<td><?php echo $row->wl_count ?></td>
									<td><?php echo $row->wl_ip ?></td>
									<td><a class="thickbox" href="?page=ci_wsdl_keys&act=view_xml_request&trajax=1<?php echo '&wl_ID=' . $row->wl_ID . '&wl_type=' . $row->wl_type . '&wl_key=' . $row->wl_key . '&wl_at=' . $row->wl_at ?>">Log</a></td>
								</tr>
							<?php
							}
							?>
						</tbody>
					</table>
				</div>

				<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
			</div>
		<?php
		} else {

			$row_per_page = 100;
			$pagination = new Tr_Pagination_class2($row_per_page);
			$pagination->setTotal($wpdb->get_var("select count(*) as c from ci_wsdl_keys k where 1 {$where}"));
			$offset = $pagination->offset();

			$wsdl_keyss = $wpdb->get_results("select k.*,
                                    group_concat(' ',cli.cli_clinic,' ') as cli_clinic,
                                    ug.gr_name,
                                    lab.lab_dba_init,
                                    (select count(*) from ci_wsdl_log wl where wl.wl_ID = k.wsdl_ID) as wsdl_count

                                    from ci_wsdl_keys k
                                    left JOIN ci_wsdl_key_clinics kc ON kc.wsdl_ID = k.wsdl_ID
                                    left JOIN ci_clinic cli ON cli.cID = kc.cli_ID
                                    left join ci_user_groups ug ON ug.gr_ID = k.wsdl_user_group_ID
                                    left join ci_labs lab ON lab.lab_ID = k.wsdl_lab_ID
                                    where 1 {$where} 
                                    group by k.wsdl_ID 
                                    limit {$offset},{$row_per_page}");

			//echo $wpdb->last_error;
		?>
			<div class="wrap">
				<h2>Keys <a href="<?php Tr_Base_Class_V6::link(array('act' => 'add', 'id' => '')) ?>" class="add-new-h2">Add
						New</a></h2>
				<?php Tr_Session_Class::show() ?>

				<div class="wraptbl">
					<table class="wp-list-table widefat striped pages blue_tbl hover_tbl" style="table-layout: auto">
						<thead>
							<tr>
								<th style="width:20px">#</th>
								<th>Key</th>
								<th style="width: 20%">Clinic</th>
								<th style="width:12%">Group</th>
								<th style="width:40px">Lab</th>
								<th>Access</th>
								<th>Notes</th>
								<th>Count</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php
							if (isset($wsdl_keyss) && count($wsdl_keyss) > 0) {
								foreach ($wsdl_keyss as $i => $row) {
							?>
									<tr data-id="<?php echo $row->wsdl_ID ?>">
										<td><?php echo $i + 1 ?></td>
										<td><?php echo $row->wsdl_key ?></td>
										<td><?php echo $row->cli_clinic ?></td>
										<td><?php echo $row->gr_name ?></td>
										<td><?php echo $row->lab_dba_init ?></td>
										<td><?php echo $types[$row->wsdl_type] ?></td>
										<td><?php echo $row->wsdl_desc ?></td>
										<td align="right"><?php echo $row->wsdl_count ?></td>
										<td>
											<a href="<?php Tr_Base_Class_V6::link(array('act' => 'log', 'id' => $row->wsdl_ID)) ?>">Log</a> -
											<a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->wsdl_ID)) ?>">Edit</a> -
											<a class="delete" href="<?php Tr_Base_Class_V6::link(array('act' => 'delete', 'id' => $row->wsdl_ID)) ?>">Delete</a>
										</td>
									</tr>
								<?php
								}
							} else if (isset($wsdl_keyss)) {
								?>
								<tr>
									<td colspan="7">Not found</td>
								</tr>
							<?php
							}
							?>
						</tbody>
					</table>
				</div>

				<div class="tablenav"><?php if ($pagination) echo $pagination->pagination() ?></div>
				<div class="info">
					<p>
					<h4>WSDL Server Url:</h4>
					</p>
					<input type="text" style="width:100%;border:0" readonly value="<?php bloginfo('url') ?>/wsdl?wsdl" onClick="this.select();" />
					<p>
					<h4>WSDL Server Index:</h4>
					</p>
					<input type="text" style="width:100%;border:0" readonly value="<?php bloginfo('url') ?>/wsdl?data-server" onClick="this.select();" />

					<?php do_action('wsdl_apis_info') ?>

					<p>
					<h4>Json API(order/result):</h4>
					</p>
					<input type="text" style="width:100%;border:0" readonly value="<?php bloginfo('url') ?>/jsonapi/info?key=viewinfo" onClick="this.select();" />


					<p>
					<h4>Cerner (reopenlabs) API:</h4>
					</p>
					<input type="text" style="width:100%;border:0" readonly value="<?php bloginfo('url') ?>/reopenlabs/info?key=viewinfo" onClick="this.select();" />

					<p>
					<h4>PowerBI API:</h4>
					</p>
					<input type="text" style="width:100%;border:0" readonly value="<?php bloginfo('url') ?>/powerbi/info?key=viewinfo" onClick="this.select();" />

					<?php
					if (isset($tabs['api_medhiwa'])) {
					?>
						<p>
						<h4>Medhiwa API:</h4>
						</p>
						<input type="text" style="width:100%;border:0" readonly value="<?php bloginfo('url') ?>/medhiwa/info?key=viewinfo" onClick="this.select();" />
					<?php
					}
					?>
				</div>
			<?php
		}
			?>
			</div>
			<script>
				function makeid() {
					var text = "";
					var possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";

					for (var i = 0; i < 20; i++)
						text += possible.charAt(Math.floor(Math.random() * possible.length));

					return text;
				}
				jQuery(function($) {
					$('a.delete').click(function() {
						if (confirm('Do you want delete?') == false) return false;
					});
					$('.generate_key').click(function() {
						var $vl = makeid();
						$('[name="key[wsdl_key]"]').val($vl);
					})
					$('.js_select2_tag').select2({
						tags: true,
						createTag: function() {
							return false;
						}
					});
				})
			</script>
	<?php
	}
} else {

	include(trpdd_get_file_path('instrument', 'default_settings'));
}
